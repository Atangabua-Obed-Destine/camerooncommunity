<?php

namespace App\Livewire\Yard;

use App\Enums\RoomType;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Models\YardMessage;
use App\Services\AIService;
use App\Services\LocationService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class RoomList extends Component
{
    public ?string $search = '';
    public ?int $activeRoomId = null;
    public string $filter = 'all'; // all, unread, favorites, groups

    // Join preview modal state
    public bool $showJoinPreview = false;
    public ?int $previewRoomId = null;
    public ?string $previewRoomName = null;

    public function getListeners(): array
    {
        $listeners = [
            'room-updated' => 'refreshRooms',
            'location-changed' => 'onLocationChanged',
        ];

        // Subscribe to Echo channels for ALL rooms the user belongs to.
        // When any room gets a new message, the list refreshes instantly.
        $user = auth()->user();
        if ($user) {
            $roomIds = YardRoomMember::where('user_id', $user->id)
                ->pluck('room_id');

            foreach ($roomIds as $roomId) {
                $listeners["echo:tenant.{$user->tenant_id}.room.{$roomId},.MessageSent"] = 'onNewMessage';
            }
        }

        return $listeners;
    }
    public ?string $previewRoomType = null;
    public ?string $previewRoomCountry = null;
    public ?string $previewRoomRegion = null;
    public int $previewMemberCount = 0;
    public ?string $previewAiGreeting = null;
    public bool $loadingGreeting = false;

    public function mount(?int $activeRoomId = null)
    {
        $this->activeRoomId = $activeRoomId;
    }

    #[Computed]
    public function rooms()
    {
        $user = auth()->user();

        // Single join to get rooms the user belongs to (avoids whereHas subquery)
        $rooms = YardRoom::join('yard_room_members as m', function ($join) use ($user) {
                $join->on('m.room_id', '=', 'yard_rooms.id')
                     ->where('m.user_id', $user->id);
            })
            ->select('yard_rooms.*', 'm.last_read_at as member_last_read_at', 'm.is_favorited as is_favorited')
            ->selectRaw('(SELECT COUNT(*) FROM yard_room_members WHERE room_id = yard_rooms.id) as members_count')
            ->selectRaw('(SELECT COALESCE(u.username, u.name) FROM yard_room_members om JOIN users u ON u.id = om.user_id WHERE om.room_id = yard_rooms.id AND om.user_id != ? LIMIT 1) as dm_other_name', [$user->id])
            ->selectRaw('(SELECT u.avatar FROM yard_room_members om JOIN users u ON u.id = om.user_id WHERE om.room_id = yard_rooms.id AND om.user_id != ? LIMIT 1) as dm_other_avatar', [$user->id])
            ->selectRaw('(SELECT COALESCE(u.username, u.name) FROM users u WHERE u.id = yard_rooms.last_message_user_id LIMIT 1) as last_message_sender_name');

        if ($this->search) {
            $rooms->where('yard_rooms.name', 'like', '%' . $this->search . '%');
        }

        // Filter: favorites only
        if ($this->filter === 'favorites') {
            $rooms->where('m.is_favorited', true);
        }

        // Filter: groups only (PrivateGroup + system rooms that aren't DMs)
        if ($this->filter === 'groups') {
            $rooms->where('yard_rooms.room_type', '!=', RoomType::DirectMessage);
        }

        $rooms = $rooms->orderByDesc('m.is_favorited')
            ->orderByDesc('yard_rooms.last_message_at')
            ->orderByDesc('yard_rooms.created_at')
            ->get();

        // Batch unread counts in a single query
        $roomIds = $rooms->pluck('id')->toArray();
        if (!empty($roomIds)) {
            $unreadCounts = collect();
            $cases = $rooms->filter(fn ($r) => $r->member_last_read_at)
                ->map(fn ($r) => ['id' => $r->id, 'last_read' => $r->member_last_read_at]);

            if ($cases->isNotEmpty()) {
                $unreadCounts = DB::table('yard_messages')
                    ->select('room_id', DB::raw('COUNT(*) as cnt'))
                    ->whereIn('room_id', $cases->pluck('id'))
                    ->where('user_id', '!=', $user->id)
                    ->where('is_deleted', false)
                    ->whereRaw('created_at > (SELECT last_read_at FROM yard_room_members WHERE room_id = yard_messages.room_id AND user_id = ? LIMIT 1)', [$user->id])
                    ->groupBy('room_id')
                    ->pluck('cnt', 'room_id');
            }

            foreach ($rooms as $room) {
                if (!$room->member_last_read_at) {
                    $room->unread_count = $room->messages_count ?? 0;
                } else {
                    $room->unread_count = (int) ($unreadCounts->get($room->id) ?? $unreadCounts->get((string) $room->id) ?? 0);
                }
            }

            // Debug: always log to trace the badge issue
            \Log::info('[RoomList] rooms() computed', [
                'user' => $user->id,
                'unread_counts' => $unreadCounts->toArray(),
                'rooms_with_data' => $rooms->map(fn ($r) => [
                    'room' => $r->id,
                    'unread' => $r->unread_count,
                    'last_read' => $r->member_last_read_at,
                ])->values()->toArray(),
            ]);
        }

        // Filter: unread only (applied post-query since counts are computed)
        if ($this->filter === 'unread') {
            $rooms = $rooms->filter(fn ($r) => ($r->unread_count ?? 0) > 0)->values();
        }

        return $rooms->groupBy(fn ($room) => match ($room->room_type) {
            RoomType::National => 'national',
            RoomType::Regional => 'regional',
            RoomType::City => 'city',       // City rooms hidden for now
            RoomType::PrivateGroup => 'groups',
            RoomType::DirectMessage => 'dms',
        });
    }

    /**
     * Rooms the user hasn't joined but should see based on their location.
     */
    #[Computed]
    public function suggestedRooms()
    {
        $user = auth()->user();

        if (! $user->current_country) {
            return collect();
        }

        $joinedRoomIds = YardRoomMember::where('user_id', $user->id)->pluck('room_id');

        return YardRoom::where('is_system_room', true)
            ->where('is_active', true)
            ->whereNotIn('id', $joinedRoomIds)
            ->where(function ($q) use ($user) {
                // National room for user's country
                $q->where(function ($q2) use ($user) {
                    $q2->where('room_type', RoomType::National)
                       ->where('country', $user->current_country);
                });
                // Regional room for user's region
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('room_type', RoomType::Regional)
                       ->where(function ($q3) use ($user) {
                           if ($user->current_region) {
                               $q3->where('region', $user->current_region);
                           }
                           if ($user->home_region) {
                               $regionName = config('cameroon.regions.' . $user->home_region, $user->home_region);
                               $q3->orWhere('region', $regionName);
                           }
                       });
                });
            })
            ->withCount('members')
            ->orderBy('room_type')
            ->get();
    }

    /**
     * Open the join preview modal for a suggested room.
     */
    public function previewRoom(int $roomId): void
    {
        $room = YardRoom::findOrFail($roomId);

        if (! $room->is_system_room || ! $room->is_active) {
            return;
        }

        $this->previewRoomId = $room->id;
        $this->previewRoomName = $room->name;
        $this->previewRoomType = $room->room_type->value;
        $this->previewRoomCountry = $room->country;
        $this->previewRoomRegion = $room->region;
        $this->previewMemberCount = $room->members_count ?? $room->members()->count();
        $this->previewAiGreeting = null;
        $this->showJoinPreview = true;

        // Generate AI greeting asynchronously-ish
        $this->loadAiGreeting();
    }

    /**
     * Load the AI greeting for the preview modal.
     */
    public function loadAiGreeting(): void
    {
        if (! $this->previewRoomId) {
            return;
        }

        $user = auth()->user();
        $ai = app(AIService::class);

        $typeLabel = match ($this->previewRoomType) {
            'national' => 'national',
            'regional' => 'regional',
            default => 'community',
        };

        $greeting = $ai->roomJoinGreeting(
            $user->name,
            $this->previewRoomName ?? '',
            $typeLabel,
            $this->previewRoomCountry,
            $this->previewRoomRegion,
            $this->previewMemberCount,
            $user->language_pref?->value ?? 'en',
        );

        $this->previewAiGreeting = $greeting ?? $this->fallbackGreeting($user->language_pref ?? 'en');
    }

    /**
     * Culturally-aware fallback greeting when AI is unavailable.
     */
    private function fallbackGreeting(string $lang): string
    {
        $greetings = [
            'en' => [
                "The door is open and the people are waiting! Step inside and say hello 🔥",
                "Your people are in here! Come join the conversation — we saved you a seat 🪑",
                "This room is buzzing! Jump in and add your voice to the mix 🎉",
                "You're about to meet some amazing Cameroonians. Ready? Let's go! 🚀",
            ],
            'fr' => [
                "La porte est ouverte et les gens vous attendent ! Entrez et dites bonjour 🔥",
                "Vos compatriotes sont ici ! Rejoignez la conversation — on vous a gardé une place 🪑",
                "Cette salle est en feu ! Sautez dedans et ajoutez votre voix 🎉",
                "Vous allez rencontrer des Camerounais incroyables. Prêt(e) ? C'est parti ! 🚀",
            ],
        ];

        $pool = $greetings[$lang] ?? $greetings['en'];

        return $pool[array_rand($pool)];
    }

    /**
     * Close the join preview modal.
     */
    public function closeJoinPreview(): void
    {
        $this->showJoinPreview = false;
        $this->previewRoomId = null;
        $this->previewAiGreeting = null;
    }

    /**
     * Confirm joining the room from the preview modal.
     */
    public function confirmJoin(): void
    {
        if (! $this->previewRoomId) {
            return;
        }

        $this->joinRoom($this->previewRoomId);
        $this->showJoinPreview = false;
        $this->previewRoomId = null;
        $this->previewAiGreeting = null;
    }

    /**
     * Join a room and refresh the list.
     */
    public function joinRoom(int $roomId): void
    {
        $user = auth()->user();
        $room = YardRoom::findOrFail($roomId);

        if (! $room->is_system_room || ! $room->is_active) {
            return;
        }

        YardRoomMember::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
        ], [
            'role' => 'member',
        ]);

        $room->increment('members_count');

        // Clear computed caches so lists refresh
        unset($this->rooms);
        unset($this->suggestedRooms);

        // Auto-open the room
        $this->activeRoomId = $roomId;
        $this->dispatch('room-selected', roomId: $roomId);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'unread', 'favorites', 'groups']) ? $filter : 'all';
        unset($this->rooms);
    }

    public function toggleFavorite(int $roomId): void
    {
        $member = YardRoomMember::where('room_id', $roomId)
            ->where('user_id', auth()->id())
            ->first();

        if ($member) {
            $member->update(['is_favorited' => ! $member->is_favorited]);
            unset($this->rooms);
        }
    }

    public function selectRoom(int $roomId)
    {
        $this->activeRoomId = $roomId;
        $this->dispatch('room-selected', roomId: $roomId);
    }

    public function render()
    {
        return view('livewire.yard.room-list', [
            'groupedRooms' => $this->rooms,
            'suggested' => $this->suggestedRooms,
            'activeFilter' => $this->filter,
        ]);
    }

    /**
     * Refresh rooms when another component signals a change.
     */
    #[On('room-updated')]
    #[On('refreshRoomList')]
    public function refreshRooms(): void
    {
        unset($this->rooms);
        unset($this->suggestedRooms);
    }

    /**
     * Handle a new message broadcast on any room the user belongs to.
     * Refreshes the list so unread badges, preview text, and sort order update instantly.
     */
    public function onNewMessage($data): void
    {
        unset($this->rooms);
    }

    /**
     * Refresh suggested rooms when the user's location changes (e.g. travel).
     */
    #[On('location-changed')]
    public function onLocationChanged(): void
    {
        unset($this->rooms);
        unset($this->suggestedRooms);
    }
}
