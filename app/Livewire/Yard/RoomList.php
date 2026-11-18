<?php

namespace App\Livewire\Yard;

use App\Enums\RoomType;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Models\YardMessage;
use App\Services\AIService;
use App\Services\LocationService;
use App\Services\LocationSwitchService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class RoomList extends Component
{
    public ?string $search = '';
    public ?int $activeRoomId = null;
    public string $filter = 'all'; // all, unread, favorites, groups

    // Location switcher modal state
    public bool $showLocationSwitcher = false;
    public ?string $switchCountry = null;
    public ?string $switchRegion = null;

    // Join preview modal state
    public bool $showJoinPreview = false;
    public ?int $previewRoomId = null;
    public ?string $previewRoomName = null;

    public function getListeners(): array
    {
        $listeners = [
            'room-updated' => 'refreshRooms',
            'location-changed' => 'onLocationChanged',
            'open-archived' => 'openArchived',
        ];

        // Subscribe to Echo channels for ALL rooms the user belongs to.
        // When any room gets a new message, the list refreshes instantly.
        $user = auth()->user();
        if ($user) {
            $roomIds = YardRoomMember::where('user_id', $user->id)
                ->pluck('room_id');

            foreach ($roomIds as $roomId) {
                $listeners["echo-private:tenant.{$user->tenant_id}.room.{$roomId},.MessageSent"] = 'onNewMessage';
            }

            // When the user accepts/receives an accepted connection, the server
            // creates a fresh DM room for both sides. Listen on the user's own
            // channel so the chat list refreshes immediately and the new DM
            // shows up without waiting for the first message.
            $listeners["echo-private:tenant.{$user->tenant_id}.user.{$user->id},.connection.accepted"] = 'refreshRooms';
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
        return app(\App\Services\Yard\RoomListService::class)->rooms(auth()->user(), $this->filter, $this->search);
    }

    /**
     * Counts shown next to the filter pills (Unread, Favorites, Groups).
     * Computed independently of the currently active filter so the numbers
     * stay correct as the user switches between pills. Mirrors the same
     * "active list" scope (excludes auto-archived and manually archived).
     */
    #[Computed]
    public function filterCounts(): array
    {
        return app(\App\Services\Yard\RoomListService::class)->filterCounts(auth()->user());
    }


    /**
     * Rooms that were auto-archived because the user travelled to a new
     * location. They reappear in the main list silently when the user
     * returns. Surfaced under "Archived (away)" section.
     */
    #[Computed]
    public function archivedRooms()
    {
        $user = auth()->user();

        return YardRoom::join('yard_room_members as m', function ($join) use ($user) {
                $join->on('m.room_id', '=', 'yard_rooms.id')
                     ->where('m.user_id', $user->id)
                     ->whereNotNull('m.auto_archived_at');
            })
            ->select('yard_rooms.*', 'm.auto_archived_at as archived_at')
            ->orderByDesc('m.auto_archived_at')
            ->get();
    }

    /**
     * Rooms the user hasn't joined but should see based on their location.
     */
    #[Computed]
    public function suggestedRooms()
    {
        return app(\App\Services\Yard\RoomListService::class)->suggested(auth()->user());
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

        $this->previewAiGreeting = $greeting ?? $this->fallbackGreeting($user->language_pref?->value ?? 'en');
    }

    /**
     * Culturally-aware fallback greeting when AI is unavailable.
     */
    private function fallbackGreeting(string $lang): string
    {
        $greetings = [
            'en' => [
                "The door is open and the people are waiting! Step inside and say hello 🔥",
                "Your people are in here! Come join the conversation, we saved you a seat 🪑",
                "This room is buzzing! Jump in and add your voice to the mix 🎉",
                "You're about to meet some amazing Cameroonians. Ready? Let's go! 🚀",
            ],
            'fr' => [
                "La porte est ouverte et les gens vous attendent ! Entrez et dites bonjour 🔥",
                "Vos compatriotes sont ici ! Rejoignez la conversation, on vous a gardé une place 🪑",
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
        if (! app(\App\Services\Yard\RoomListService::class)->join(auth()->user(), YardRoom::findOrFail($roomId))) {
            return;
        }

        unset($this->rooms, $this->suggestedRooms);

        // Auto-open the room
        $this->activeRoomId = $roomId;
        $this->dispatch('room-selected', roomId: $roomId);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'unread', 'favorites', 'groups', 'archived']) ? $filter : 'all';
        unset($this->rooms);
    }

    /**
     * Open the Archived chats view (triggered from the header overflow menu).
     */
    public function openArchived(): void
    {
        $this->setFilter('archived');
    }

    public function toggleFavorite(int $roomId): void
    {
        if (app(\App\Services\Yard\RoomListService::class)->setFavorite(auth()->user(), $roomId) !== null) {
            unset($this->rooms);
        }
    }

    /**
     * Manually archive / unarchive a chat (WhatsApp parity). Distinct from
     * `auto_archived_at` which is reserved for location-driven archiving.
     */
    public function toggleArchive(int $roomId): void
    {
        $member = YardRoomMember::where('room_id', $roomId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $member) {
            return;
        }

        $isArchived = (bool) $member->archived_at;
        $member->update(['archived_at' => $isArchived ? null : now()]);

        // If we just archived the currently-open chat, clear the active id
        // so the right pane resets.
        if (! $isArchived && $this->activeRoomId === $roomId) {
            $this->activeRoomId = null;
            $this->dispatch('room-deselected');
        }

        unset($this->rooms);

        // Notify the topbar archive badge so its count refreshes without a
        // page reload.
        $newCount = YardRoomMember::where('user_id', auth()->id())
            ->whereNotNull('archived_at')
            ->whereNull('auto_archived_at')
            ->count();
        $this->dispatch('archived-count-changed', count: $newCount);

        $this->dispatch('toast',
            type: 'success',
            message: $isArchived
                ? __('Chat unarchived')
                : __('Chat archived'),
        );
    }

    public function selectRoom(int $roomId)
    {
        // Defense-in-depth: archived (away) memberships are visible in the
        // sidebar but locked. Refuse to open them even if the client crafts
        // the request. They auto-unlock when the user returns to that location.
        $isArchived = \App\Models\YardRoomMember::query()
            ->where('user_id', auth()->id())
            ->where('room_id', $roomId)
            ->whereNotNull('auto_archived_at')
            ->exists();

        if ($isArchived) {
            $this->dispatch('toast',
                type: 'info',
                message: __('This room is locked. Switch back to its location to reopen it.'),
            );
            return;
        }

        $this->activeRoomId = $roomId;
        $this->dispatch('room-selected', roomId: $roomId);
    }

    public function render()
    {
        $user = auth()->user();
        $config = config('cameroon');

        return view('livewire.yard.room-list', [
            'groupedRooms' => $this->rooms,
            'suggested' => $this->suggestedRooms,
            'archived' => $this->archivedRooms,
            'activeFilter' => $this->filter,
            'activeCountry' => $user->active_country ?: $user->current_country,
            'activeRegion' => $user->active_region ?: $user->current_region,
            'detectedCountry' => $user->current_country,
            'detectedRegion' => $user->current_region,
            'switcherCountries' => $config['seeded_countries'] ?? [],
            'switcherRegionsMap' => $config['seeded_regions'] ?? [],
        ]);
    }

    /**
     * Open the manual location switcher modal.
     * Pre-fills with the user's current detected location so the most
     * common case (switch to where I am) is one click.
     */
    public function openLocationSwitcher(): void
    {
        $user = auth()->user();
        $this->switchCountry = $user->current_country ?: $user->active_country;
        $this->switchRegion = $user->current_region ?: $user->active_region;
        $this->showLocationSwitcher = true;
    }

    public function closeLocationSwitcher(): void
    {
        $this->showLocationSwitcher = false;
        $this->switchCountry = null;
        $this->switchRegion = null;
    }

    /**
     * Confirm a manual location switch from the modal.
     */
    public function confirmLocationSwitch(): void
    {
        $user = auth()->user();
        $country = trim((string) $this->switchCountry);
        $region = trim((string) $this->switchRegion);

        if (! $country || mb_strlen($country) > 100 || mb_strlen($region) > 100) {
            return;
        }

        // Don't fire when nothing actually changes
        if ($user->active_country === $country && (string) $user->active_region === $region) {
            $this->closeLocationSwitcher();
            return;
        }

        app(LocationSwitchService::class)->switchTo($user, $country, $region ?: null);

        unset($this->rooms);
        unset($this->suggestedRooms);
        unset($this->archivedRooms);

        $this->closeLocationSwitcher();
        $this->dispatch('location-changed');
    }

    /**
     * Switch to the detected location only — no free choice. Keeps room
     * memberships grounded in the user's actual physical location.
     */
    public function confirmDetectedSwitch(): void
    {
        $user = auth()->user();
        $country = (string) ($user->current_country ?? '');
        $region = (string) ($user->current_region ?? '');

        if ($country === '' || mb_strlen($country) > 100 || mb_strlen($region) > 100) {
            return;
        }

        if ($user->active_country === $country && (string) $user->active_region === $region) {
            $this->closeLocationSwitcher();
            return;
        }

        app(LocationSwitchService::class)->switchTo($user, $country, $region ?: null);

        unset($this->rooms);
        unset($this->suggestedRooms);
        unset($this->archivedRooms);

        $this->closeLocationSwitcher();
        $this->dispatch('location-changed');
    }

    /**
     * Refresh rooms when another component signals a change.
     */
    #[On('room-updated')]
    #[On('refreshRoomList')]
    #[On('location-changed')]
    public function refreshRooms(): void
    {
        unset($this->rooms);
        unset($this->suggestedRooms);
        unset($this->archivedRooms);
    }

    public function onLocationChanged(): void
    {
        $this->refreshRooms();
    }

    /**
     * Handle a new message broadcast on any room the user belongs to.
     * Refreshes the list so unread badges, preview text, and sort order update instantly.
     * Also fires a browser event so the UI can show a toast + chime when the
     * message arrives in a room that is NOT currently open.
     */
    public function onNewMessage($data): void
    {
        unset($this->rooms);

        try {
            $payload = is_array($data) ? $data : (array) $data;
            $roomId = (int) ($payload['room_id'] ?? 0);
            $userId = (int) ($payload['user_id'] ?? 0);
            $myId = (int) (auth()->id() ?? 0);

            // Suppress: own messages, currently-open room, or missing data.
            if ($roomId <= 0 || $userId === $myId || $roomId === (int) $this->activeRoomId) {
                return;
            }

            $room = YardRoom::find($roomId);
            if (! $room) {
                return;
            }

            $this->dispatch('yard-incoming-message',
                roomId: $room->id,
                roomName: $room->name,
                senderName: (string) ($payload['user_name'] ?? __('Someone')),
                preview: mb_substr((string) ($payload['content'] ?? ''), 0, 120),
            );
        } catch (\Throwable $e) {
            // Never let a notification failure break the list refresh.
            \Log::warning('yard.onNewMessage notify failed: ' . $e->getMessage());
        }
    }
}
