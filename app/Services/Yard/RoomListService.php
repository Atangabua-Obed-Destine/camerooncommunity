<?php

namespace App\Services\Yard;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\UserContactName;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A member's chat list: the rooms they belong to, the unread and @mention
 * counts, and the filter-pill counts. One query, shared by the web's RoomList
 * component and GET /api/v1/yard/rooms, so both show the same list.
 */
class RoomListService
{
    public const FILTERS = ['all', 'unread', 'favorites', 'groups', 'archived'];

    /**
     * @return Collection<int, YardRoom> each with unread_count, unread_mention_count,
     *     is_favorited, member_archived_at, members_count, dm_other_{id,name,avatar},
     *     last_message_sender_name
     */
    public function rooms(User $user, string $filter = 'all', ?string $search = null): Collection
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';

        $rooms = YardRoom::join('yard_room_members as m', function ($join) use ($user) {
            $join->on('m.room_id', '=', 'yard_rooms.id')
                ->where('m.user_id', $user->id)
                ->whereNull('m.auto_archived_at'); // locked by a location switch
        })
            ->select('yard_rooms.*', 'm.last_read_at as member_last_read_at', 'm.joined_at as member_joined_at', 'm.is_favorited as is_favorited', 'm.archived_at as member_archived_at')
            ->selectRaw('(SELECT COUNT(*) FROM yard_room_members WHERE room_id = yard_rooms.id) as members_count')
            ->selectRaw('(SELECT COALESCE(u.username, u.name) FROM yard_room_members om JOIN users u ON u.id = om.user_id WHERE om.room_id = yard_rooms.id AND om.user_id != ? LIMIT 1) as dm_other_name', [$user->id])
            ->selectRaw('(SELECT u.avatar FROM yard_room_members om JOIN users u ON u.id = om.user_id WHERE om.room_id = yard_rooms.id AND om.user_id != ? LIMIT 1) as dm_other_avatar', [$user->id])
            ->selectRaw('(SELECT om.user_id FROM yard_room_members om WHERE om.room_id = yard_rooms.id AND om.user_id != ? LIMIT 1) as dm_other_id', [$user->id])
            ->selectRaw('(SELECT COALESCE(u.username, u.name) FROM users u WHERE u.id = yard_rooms.last_message_user_id LIMIT 1) as last_message_sender_name');

        if ($search) {
            $rooms->where('yard_rooms.name', 'like', '%'.$search.'%');
        }

        // Archived shows only archived chats; every other filter hides them
        // (WhatsApp's behaviour).
        $filter === 'archived' ? $rooms->whereNotNull('m.archived_at') : $rooms->whereNull('m.archived_at');

        if ($filter === 'favorites') {
            $rooms->where('m.is_favorited', true);
        }
        if ($filter === 'groups') {
            $rooms->where('yard_rooms.room_type', '!=', RoomType::DirectMessage);
        }

        $rooms = $rooms->orderByDesc('m.is_favorited')
            ->orderByDesc('yard_rooms.last_message_at')
            ->orderByDesc('yard_rooms.created_at')
            ->get();

        $this->attachCounts($user, $rooms);
        $this->applyNicknames($user, $rooms);

        if ($filter === 'unread') {
            $rooms = $rooms->filter(fn ($r) => ($r->unread_count ?? 0) > 0)->values();
        }

        return $rooms;
    }

    /**
     * Unread = messages from others newer than the later of the member's
     * last_read_at and joined_at, so a new member never sees a badge for
     * history from before they joined.
     */
    private function attachCounts(User $user, Collection $rooms): void
    {
        $roomIds = $rooms->pluck('id')->all();
        if ($roomIds === []) {
            return;
        }

        $cutoff = $this->cutoffSql();

        $unread = DB::table('yard_messages')
            ->select('room_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('room_id', $roomIds)
            ->where('user_id', '!=', $user->id)
            ->where('is_deleted', false)
            ->whereRaw("created_at > $cutoff", [$user->id, $user->id])
            ->groupBy('room_id')
            ->pluck('cnt', 'room_id');

        $mentions = collect();
        try {
            $mentions = DB::table('yard_messages')
                ->select('room_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('room_id', $roomIds)
                ->where('user_id', '!=', $user->id)
                ->where('is_deleted', false)
                ->whereNotNull('mentioned_user_ids')
                ->whereRaw('JSON_CONTAINS(mentioned_user_ids, ?)', [(string) $user->id])
                ->whereRaw("created_at > $cutoff", [$user->id, $user->id])
                ->groupBy('room_id')
                ->pluck('cnt', 'room_id');
        } catch (\Throwable $e) {
            // A driver without JSON_CONTAINS (SQLite in tests): no @ badges.
        }

        foreach ($rooms as $room) {
            $room->unread_count = (int) ($unread[$room->id] ?? 0);
            $room->unread_mention_count = (int) ($mentions[$room->id] ?? 0);
        }
    }

    /** The member's saved names ("Save as…") over the SQL-derived ones. */
    private function applyNicknames(User $user, Collection $rooms): void
    {
        $ids = $rooms->flatMap(fn ($r) => [$r->dm_other_id, $r->last_message_user_id])
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $nicknames = UserContactName::nicknamesFor($user->id, $ids);
        foreach ($rooms as $r) {
            if ($r->dm_other_id && isset($nicknames[$r->dm_other_id])) {
                $r->dm_other_name = $nicknames[$r->dm_other_id];
            }
            if ($r->last_message_user_id && isset($nicknames[$r->last_message_user_id])) {
                $r->last_message_sender_name = $nicknames[$r->last_message_user_id];
            }
        }
    }

    private function cutoffSql(): string
    {
        $greatest = DB::getDriverName() === 'sqlite' ? 'MAX' : 'GREATEST';

        return "$greatest(
            COALESCE((SELECT joined_at FROM yard_room_members WHERE room_id = yard_messages.room_id AND user_id = ? LIMIT 1), '1970-01-01 00:00:00'),
            COALESCE((SELECT last_read_at FROM yard_room_members WHERE room_id = yard_messages.room_id AND user_id = ? LIMIT 1), '1970-01-01 00:00:00')
        )";
    }

    /**
     * The pill counts (Unread, Favorites, Groups), independent of the active
     * filter so they stay right while the member switches pills.
     *
     * @return array{unread: int, favorites: int, groups: int}
     */
    public function filterCounts(User $user): array
    {
        $base = YardRoomMember::query()
            ->where('user_id', $user->id)
            ->whereNull('auto_archived_at')
            ->whereNull('archived_at');

        $greatest = DB::getDriverName() === 'sqlite' ? 'MAX' : 'GREATEST';

        return [
            'unread' => (clone $base)->whereExists(function ($sub) use ($user, $greatest) {
                $sub->select(DB::raw(1))
                    ->from('yard_messages')
                    ->whereColumn('yard_messages.room_id', 'yard_room_members.room_id')
                    ->where('yard_messages.user_id', '!=', $user->id)
                    ->where('yard_messages.is_deleted', false)
                    ->whereRaw("yard_messages.created_at > $greatest(
                        COALESCE(yard_room_members.joined_at, '1970-01-01 00:00:00'),
                        COALESCE(yard_room_members.last_read_at, '1970-01-01 00:00:00')
                    )");
            })->count(),
            'favorites' => (clone $base)->where('is_favorited', true)->count(),
            'groups' => (clone $base)
                ->join('yard_rooms', 'yard_rooms.id', '=', 'yard_room_members.room_id')
                ->where('yard_rooms.room_type', '!=', RoomType::DirectMessage->value)
                ->count(),
        ];
    }

    /** How many chats the member archived themselves (the header's archive badge). */
    public function archivedCount(User $user): int
    {
        return YardRoomMember::where('user_id', $user->id)
            ->whereNull('auto_archived_at')
            ->whereNotNull('archived_at')
            ->count();
    }

    /**
     * Community rooms for the member's active location that they haven't
     * joined: the national room, and the regional one when a region is known
     * (plus the home region for members in Cameroon).
     *
     * @return Collection<int, YardRoom>
     */
    public function suggested(User $user): Collection
    {
        if (! $user->active_country) {
            return collect();
        }

        $joined = YardRoomMember::where('user_id', $user->id)->pluck('room_id');

        $regions = array_filter([$user->active_region]);
        if ($user->home_region && $user->active_country === 'Cameroon') {
            $home = config('cameroon.regions.'.$user->home_region, $user->home_region);
            if ($home && ! in_array($home, $regions, true)) {
                $regions[] = $home;
            }
        }

        return YardRoom::where('is_system_room', true)
            ->where('is_active', true)
            ->whereNotIn('id', $joined)
            ->where(function ($q) use ($user, $regions) {
                $q->where(fn ($q2) => $q2->where('room_type', RoomType::National)->where('country', $user->active_country));
                if ($regions !== []) {
                    $q->orWhere(fn ($q2) => $q2->where('room_type', RoomType::Regional)
                        ->where('country', $user->active_country)
                        ->whereIn('region', $regions));
                }
            })
            ->withCount('members')
            ->orderBy('room_type')
            ->get();
    }

    /**
     * Join a community room (national, regional, city). Private groups go
     * through a join request instead. The count only moves for a membership
     * that is actually new: the web's version added one on every tap.
     */
    public function join(User $user, YardRoom $room): bool
    {
        if (! $room->is_system_room || ! $room->is_active) {
            return false;
        }

        $member = YardRoomMember::firstOrCreate(
            ['tenant_id' => $user->tenant_id, 'room_id' => $room->id, 'user_id' => $user->id],
            ['role' => 'member'],
        );

        if ($member->wasRecentlyCreated) {
            $room->increment('members_count');
        }

        return true;
    }

    public function setFavorite(User $user, int $roomId, ?bool $on = null): ?bool
    {
        $member = YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->first();
        if (! $member) {
            return null;
        }
        $member->update(['is_favorited' => $on ?? ! $member->is_favorited]);

        return (bool) $member->is_favorited;
    }

    public function setArchived(User $user, int $roomId, ?bool $on = null): ?bool
    {
        $member = YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->first();
        if (! $member) {
            return null;
        }
        $archive = $on ?? $member->archived_at === null;
        $member->update(['archived_at' => $archive ? now() : null]);

        return $archive;
    }
}
