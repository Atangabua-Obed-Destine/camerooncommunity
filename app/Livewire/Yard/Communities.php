<?php

namespace App\Livewire\Yard;

use App\Enums\RoomMemberRole;
use App\Enums\RoomType;
use App\Events\JoinRequestReceived;
use App\Models\YardJoinRequest;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Communities extends Component
{
    public bool $show = false;
    public string $tab = 'mine'; // mine | discover
    public string $search = '';

    // Create group inline
    public bool $creating = false;
    public string $newName = '';
    public string $newDescription = '';
    public bool $newIsPrivate = true;

    #[On('open-communities')]
    public function open(): void
    {
        $this->show = true;
        $this->tab = 'mine';
        $this->search = '';
        $this->creating = false;
    }

    public function close(): void
    {
        $this->show = false;
        $this->creating = false;
        $this->resetCreate();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
    }

    #[Computed]
    public function myCommunities()
    {
        $user = auth()->user();

        $query = YardRoom::join('yard_room_members as m', function ($join) use ($user) {
                $join->on('m.room_id', '=', 'yard_rooms.id')
                     ->where('m.user_id', $user->id);
            })
            ->select('yard_rooms.*')
            ->selectRaw('(SELECT COUNT(*) FROM yard_room_members WHERE room_id = yard_rooms.id) as live_members_count')
            ->where('yard_rooms.room_type', '!=', RoomType::DirectMessage)
            // Show only National + Regional + Private rooms (NO city rooms)
            // Filter default location-based rooms by user's active location
            ->where(function ($q) use ($user) {
                $q->where('yard_rooms.room_type', RoomType::PrivateGroup)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('yard_rooms.room_type', RoomType::National)
                         ->where('yard_rooms.country', $user->current_country);
                  });

                // Only show regional room if user has a current_region
                if ($user->current_region) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('yard_rooms.room_type', RoomType::Regional)
                           ->where('yard_rooms.region', $user->current_region);
                    });
                }
            });

        if ($this->search) {
            $query->where('yard_rooms.name', 'like', '%' . $this->search . '%');
        }

        return $query->orderByDesc('yard_rooms.last_message_at')
            ->orderByDesc('yard_rooms.created_at')
            ->get();
    }

    #[Computed]
    public function discoverCommunities()
    {
        $user = auth()->user();
        $joinedIds = YardRoomMember::where('user_id', $user->id)->pluck('room_id');

        $query = YardRoom::where('is_active', true)
            ->where('room_type', '!=', RoomType::DirectMessage)
            ->whereNotIn('id', $joinedIds)
            ->where(function ($q) use ($user) {
                // National rooms: only user's country
                $q->where(function ($q2) use ($user) {
                    $q2->where('room_type', RoomType::National)
                       ->where('country', $user->current_country);
                });

                // Regional rooms: ONLY if user has a current_region
                // (prevents showing all regions when region is not set)
                if ($user->current_region) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('room_type', RoomType::Regional)
                           ->where('region', $user->current_region);
                    });
                }

                // Also check home_region as fallback for Cameroon users
                if ($user->home_region && $user->current_country === 'Cameroon') {
                    $regionName = config('cameroon.regions.' . $user->home_region, $user->home_region);
                    $q->orWhere(function ($q2) use ($regionName) {
                        $q2->where('room_type', RoomType::Regional)
                           ->where('region', $regionName);
                    });
                }

                // Private groups: show all
                $q->orWhere('room_type', RoomType::PrivateGroup);
            })
            ->selectRaw('yard_rooms.*, (SELECT COUNT(*) FROM yard_room_members WHERE room_id = yard_rooms.id) as live_members_count');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        return $query->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get pending join request IDs for current user (used in discover view).
     */
    #[Computed]
    public function pendingRequestRoomIds(): array
    {
        return YardJoinRequest::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->pluck('room_id')
            ->toArray();
    }

    public function joinCommunity(int $roomId): void
    {
        $room = YardRoom::findOrFail($roomId);
        $user = auth()->user();

        if (YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->exists()) {
            return;
        }

        // Private group → redirect to request flow
        if ($room->is_private) {
            $this->requestToJoin($roomId);
            return;
        }

        YardRoomMember::create([
            'room_id' => $roomId,
            'user_id' => $user->id,
            'role' => RoomMemberRole::Member,
            'joined_at' => now(),
        ]);

        $room->increment('members_count');

        $this->dispatch('room-updated');
    }

    public function requestToJoin(int $roomId): void
    {
        $user = auth()->user();

        // Already a member
        if (YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->exists()) {
            return;
        }

        // Already has a pending request
        if (YardJoinRequest::where('room_id', $roomId)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return;
        }

        // Delete any old resolved request so they can re-request (e.g. rejected, or approved then removed)
        YardJoinRequest::where('room_id', $roomId)->where('user_id', $user->id)->whereIn('status', ['rejected', 'approved'])->delete();

        $joinRequest = YardJoinRequest::create([
            'room_id' => $roomId,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Drop a WhatsApp-style system message into the room (admin-only visibility).
        try {
            $room = YardRoom::find($roomId);
            $sysMsg = \App\Models\YardMessage::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'room_id' => $roomId,
                'user_id' => $user->id,
                'message_type' => \App\Enums\MessageType::System,
                'content' => sprintf('%s requested to join', $user->username ?? $user->name),
                'media_metadata' => [
                    'visibility' => 'admins',
                    'kind' => 'join_request',
                    'request_id' => $joinRequest->id,
                    'requester_id' => $user->id,
                ],
            ]);

            // Bump the room so it surfaces at the top of the chat list. Use a
            // neutral preview so existing non-admin members don't learn the
            // requester's identity from the chat list.
            if ($room) {
                $room->update([
                    'last_message_at'      => now(),
                    'last_message_preview' => 'Pending join request',
                    'last_message_user_id' => null,
                ]);
            }

            broadcast(new \App\Events\MessageSent($sysMsg));
        } catch (\Throwable $e) {
            \Log::warning('Join-request system message failed: ' . $e->getMessage());
        }

        // Broadcast to room so admin sees it in real-time
        $joinRequest->load('room', 'user');
        broadcast(new JoinRequestReceived($joinRequest));
    }

    public function cancelRequest(int $roomId): void
    {
        YardJoinRequest::where('room_id', $roomId)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->delete();
    }

    public function leaveCommunity(int $roomId): void
    {
        $user = auth()->user();
        $room = YardRoom::find($roomId);

        if (! $room) {
            return;
        }

        // Default location-based rooms (National / Regional) cannot be left.
        // Membership is managed automatically by the location switch system.
        if (in_array($room->room_type, [RoomType::National, RoomType::Regional], true)) {
            $this->dispatch('toast', type: 'info', message: app()->getLocale() === 'fr'
                ? 'Vous ne pouvez pas quitter votre groupe national ou régional par défaut.'
                : "You can't leave your default country or regional group.");
            return;
        }

        $membership = YardRoomMember::where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return;
        }

        $membership->delete();

        YardRoom::where('id', $roomId)->decrement('members_count');

        $this->dispatch('room-updated');
    }

    public function openCommunity(int $roomId): void
    {
        $this->show = false;
        $this->dispatch('room-selected', roomId: $roomId);
    }

    public function startCreate(): void
    {
        $this->creating = true;
        $this->newName = '';
        $this->newDescription = '';
        $this->newIsPrivate = true;
    }

    public function resetCreate(): void
    {
        $this->creating = false;
        $this->newName = '';
        $this->newDescription = '';
        $this->newIsPrivate = true;
    }

    public function createCommunity(): void
    {
        $this->validate([
            'newName' => 'required|string|min:2|max:100',
            'newDescription' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $room = YardRoom::create([
            'name' => $this->newName,
            'slug' => Str::slug($this->newName) . '-' . Str::random(5),
            'room_type' => RoomType::PrivateGroup,
            'description' => $this->newDescription ?: null,
            'country' => $user->current_country,
            'region' => $user->current_region,
            'created_by' => $user->id,
            'is_system_room' => false,
            'is_private' => $this->newIsPrivate,
            'members_count' => 1,
        ]);

        YardRoomMember::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => RoomMemberRole::Admin,
            'joined_at' => now(),
        ]);

        $this->resetCreate();
        $this->dispatch('room-updated');
    }

    public function render()
    {
        return view('livewire.yard.communities');
    }
}
