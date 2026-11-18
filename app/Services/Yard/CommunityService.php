<?php

namespace App\Services\Yard;

use App\Enums\MessageType;
use App\Enums\RoomMemberRole;
use App\Enums\RoomType;
use App\Events\JoinRequestReceived;
use App\Events\MessageSent;
use App\Models\User;
use App\Models\YardJoinRequest;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The Communities sheet: the member's groups, the ones they could join, and
 * making a new one. Shared by Livewire Communities and the app's API.
 *
 * Joining is only ever for a room the Discover tab would show this member:
 * the web's join took any room id, a direct message's included.
 */
class CommunityService
{
    public const LEAVE_DEFAULT = 'default_room';

    /** National, regional and private rooms the member is in (no city rooms, no DMs). */
    public function mine(User $user, ?string $search = null): Collection
    {
        $query = YardRoom::join('yard_room_members as m', function ($join) use ($user) {
            $join->on('m.room_id', '=', 'yard_rooms.id')->where('m.user_id', $user->id);
        })
            ->select('yard_rooms.*')
            ->selectRaw('(SELECT COUNT(*) FROM yard_room_members WHERE room_id = yard_rooms.id) as live_members_count')
            ->where('yard_rooms.room_type', '!=', RoomType::DirectMessage)
            ->where(function ($q) use ($user) {
                $q->where('yard_rooms.room_type', RoomType::PrivateGroup)
                    ->orWhere(fn ($q2) => $q2->where('yard_rooms.room_type', RoomType::National)
                        ->where('yard_rooms.country', $user->current_country));
                if ($user->current_region) {
                    $q->orWhere(fn ($q2) => $q2->where('yard_rooms.room_type', RoomType::Regional)
                        ->where('yard_rooms.region', $user->current_region));
                }
            });

        if ($search = trim((string) $search)) {
            $query->where('yard_rooms.name', 'like', '%'.$search.'%');
        }

        return $this->withMessageCounts($query->orderByDesc('yard_rooms.last_message_at')
            ->orderByDesc('yard_rooms.created_at')
            ->get());
    }

    /** Rooms the member could join: their country's, their region's, and every group. */
    public function discover(User $user, ?string $search = null): Collection
    {
        $query = $this->discoverable($user)
            ->selectRaw('yard_rooms.*, (SELECT COUNT(*) FROM yard_room_members WHERE room_id = yard_rooms.id) as live_members_count');

        if ($search = trim((string) $search)) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $this->withMessageCounts($query->orderByDesc('last_message_at')->orderByDesc('created_at')->get());
    }

    private function discoverable(User $user): Builder
    {
        $joined = YardRoomMember::where('user_id', $user->id)->pluck('room_id');

        return YardRoom::where('is_active', true)
            ->where('room_type', '!=', RoomType::DirectMessage)
            ->whereNotIn('id', $joined)
            ->where(function ($q) use ($user) {
                $q->where(fn ($q2) => $q2->where('room_type', RoomType::National)->where('country', $user->current_country));

                // Regional rooms only for a member who has a region, or every
                // region would show.
                if ($user->current_region) {
                    $q->orWhere(fn ($q2) => $q2->where('room_type', RoomType::Regional)->where('region', $user->current_region));
                }
                if ($user->home_region && $user->current_country === 'Cameroon') {
                    $home = config('cameroon.regions.'.$user->home_region, $user->home_region);
                    $q->orWhere(fn ($q2) => $q2->where('room_type', RoomType::Regional)->where('region', $home));
                }

                $q->orWhere('room_type', RoomType::PrivateGroup);
            });
    }

    /**
     * The card's "N messages". The web read a messages_count nothing ever
     * loaded, so every card said 0.
     */
    private function withMessageCounts(Collection $rooms): Collection
    {
        if ($rooms->isEmpty()) {
            return $rooms;
        }
        $counts = YardMessage::whereIn('room_id', $rooms->pluck('id'))
            ->where('message_type', '!=', MessageType::System)
            ->selectRaw('room_id, COUNT(*) as n')
            ->groupBy('room_id')
            ->pluck('n', 'room_id');

        return $rooms->each(fn ($r) => $r->setAttribute('messages_count', (int) ($counts[$r->id] ?? 0)));
    }

    /** @return array<int, int> rooms this member asked to join and is waiting on */
    public function pendingRequestRoomIds(User $user): array
    {
        return YardJoinRequest::where('user_id', $user->id)->where('status', 'pending')->pluck('room_id')->all();
    }

    /**
     * Join a room from Discover. A private group becomes a join request.
     * Returns 'joined', 'requested', 'member' (already in) or null (not a
     * room this member may join).
     */
    public function join(User $user, int $roomId): ?string
    {
        if (YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->exists()) {
            return 'member';
        }

        $room = $this->discoverable($user)->whereKey($roomId)->first();
        if (! $room) {
            return null;
        }

        if ($room->is_private) {
            return $this->requestToJoin($user, $room) ? 'requested' : null;
        }

        $member = YardRoomMember::firstOrCreate(
            ['tenant_id' => $room->tenant_id, 'room_id' => $room->id, 'user_id' => $user->id],
            ['role' => RoomMemberRole::Member, 'joined_at' => now()],
        );
        if ($member->wasRecentlyCreated) {
            $room->increment('members_count');
        }

        return 'joined';
    }

    /**
     * Ask to join a private group: a pending request, and an admins-only
     * system line in the room so its admins see it in the chat.
     */
    public function requestToJoin(User $user, YardRoom $room): bool
    {
        if (YardRoomMember::where('room_id', $room->id)->where('user_id', $user->id)->exists()) {
            return false;
        }
        if (YardJoinRequest::where('room_id', $room->id)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return true;
        }

        // A resolved request (rejected, or approved then removed) may be asked again.
        YardJoinRequest::where('room_id', $room->id)->where('user_id', $user->id)->whereIn('status', ['rejected', 'approved'])->delete();

        $joinRequest = YardJoinRequest::create(['room_id' => $room->id, 'user_id' => $user->id, 'status' => 'pending']);

        try {
            $sysMsg = YardMessage::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $room->tenant_id,
                'room_id' => $room->id,
                'user_id' => $user->id,
                'message_type' => MessageType::System,
                'content' => sprintf('%s requested to join', $user->username ?? $user->name),
                'media_metadata' => [
                    'visibility' => 'admins',
                    'kind' => 'join_request',
                    'request_id' => $joinRequest->id,
                    'requester_id' => $user->id,
                ],
            ]);

            // A neutral preview: other members don't learn who asked.
            $room->update([
                'last_message_at' => now(),
                'last_message_preview' => 'Pending join request',
                'last_message_user_id' => null,
            ]);

            broadcast(new MessageSent($sysMsg));
        } catch (\Throwable $e) {
            Log::warning('Join-request system message failed: '.$e->getMessage());
        }

        try {
            $joinRequest->load('room', 'user');
            broadcast(new JoinRequestReceived($joinRequest));
        } catch (\Throwable $e) {
            Log::warning('JoinRequestReceived broadcast failed: '.$e->getMessage());
        }

        return true;
    }

    public function cancelRequest(User $user, int $roomId): void
    {
        YardJoinRequest::where('room_id', $roomId)->where('user_id', $user->id)->where('status', 'pending')->delete();
    }

    /**
     * Leave a group. The member's own country and region rooms can't be
     * left (the location switch manages them), and neither can a DM.
     * Returns true, false (not a member) or LEAVE_DEFAULT.
     */
    public function leave(User $user, int $roomId): bool|string
    {
        $room = YardRoom::find($roomId);
        if (! $room || $room->room_type === RoomType::DirectMessage) {
            return false;
        }
        if (in_array($room->room_type, [RoomType::National, RoomType::Regional], true)) {
            return self::LEAVE_DEFAULT;
        }

        $membership = YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->first();
        if (! $membership) {
            return false;
        }
        $membership->delete();
        YardRoom::where('id', $roomId)->where('members_count', '>', 0)->decrement('members_count');

        return true;
    }

    /** A new group, its creator its admin. */
    public function create(User $user, string $name, ?string $description, bool $isPrivate): YardRoom
    {
        $room = YardRoom::create([
            'tenant_id' => $user->tenant_id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'room_type' => RoomType::PrivateGroup,
            'description' => $description ?: null,
            'country' => $user->current_country,
            'region' => $user->current_region,
            'created_by' => $user->id,
            'is_system_room' => false,
            'is_private' => $isPrivate,
            'members_count' => 1,
        ]);

        YardRoomMember::create([
            'tenant_id' => $user->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => RoomMemberRole::Admin,
            'joined_at' => now(),
        ]);

        return $room;
    }
}
