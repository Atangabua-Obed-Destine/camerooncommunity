<?php

namespace App\Services;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardJoinRequest;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Support\AvatarPalette;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What the header bell lists (Livewire Notifications\NotificationBell on the
 * web, GET /api/v1/notifications in the app). It is built from live signals,
 * so a row disappears when the thing behind it is handled:
 *
 *   1. DMs with unread messages, one row per conversation;
 *   2. unread @mentions in any room;
 *   3. pending join requests for private groups the member created;
 *   4. pending incoming connection requests;
 *   5. stored notifications (orders, reviews: NotificationService), the
 *      only rows with a read state of their own.
 *
 * Each row: kind, id, room_id, user_id, title, body, time, unread, link (the
 * web's), icon, palette (the web's avatar class), seed (the same colour for
 * the app's CnAvatar) and initial.
 */
class NotificationFeed
{
    public const TABS = ['all', 'mentions', 'groups', 'connections'];

    public function dmUnreads(User $viewer): Collection
    {
        $userId = $viewer->id;

        // One grouped count instead of a query per conversation.
        $counts = DB::table('yard_messages as m')
            ->join('yard_room_members as rm', function ($j) use ($userId) {
                $j->on('rm.room_id', '=', 'm.room_id')->where('rm.user_id', '=', $userId);
            })
            ->join('yard_rooms as r', 'r.id', '=', 'm.room_id')
            ->where('r.room_type', RoomType::DirectMessage->value)
            ->whereNull('r.deleted_at')
            ->where('m.user_id', '!=', $userId)
            ->where('m.is_deleted', false)
            ->whereRaw('(rm.last_read_at IS NULL OR m.created_at > rm.last_read_at)')
            ->groupBy('m.room_id')
            ->selectRaw('m.room_id as room_id, COUNT(*) as n')
            ->get()
            ->pluck('n', 'room_id');

        if ($counts->isEmpty()) {
            return collect();
        }

        $memberships = YardRoomMember::query()
            ->where('user_id', $userId)
            ->whereIn('room_id', $counts->keys())
            ->with(['room.members.user'])
            ->get()
            ->keyBy('room_id');

        $rows = collect();
        foreach ($counts as $roomId => $count) {
            $m = $memberships->get($roomId);
            $room = $m?->room;
            if (! $room || $count <= 0) {
                continue;
            }

            $partner = $room->members->first(fn ($mm) => $mm->user_id !== $userId)?->user;
            $name = ($partner ? $viewer->displayNameFor($partner) : null) ?: ($partner?->username ?? $partner?->name ?? __('Someone'));

            $rows->push([
                'kind' => 'dm',
                'id' => 'dm:' . $room->id,
                'room_id' => $room->id,
                'user_id' => $partner?->id,
                'title' => $name,
                'body' => $room->last_message_preview ?: trans_choice('{1} :n new message|[2,*] :n new messages', $count, ['n' => $count]),
                'time' => $room->last_message_at ?: $m->updated_at,
                'unread' => (int) $count,
                // ?room= is what the Yard resolves and opens; ?open= only ever
                // meant ?open=connections, so these rows used to land the user on
                // the room list with the message still unread.
                'link' => $room->origin === 'marketplace'
                    ? route('marketplace.inbox') . '?c=' . $room->id
                    : route('yard') . '?room=' . $room->id,
                'icon' => 'chat',
                'seed' => 'user:' . ($partner?->id ?? 0),
                'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            ]);
        }

        return $rows;
    }

    public function mentions(User $viewer): Collection
    {
        $userId = $viewer->id;

        try {
            $rows = DB::table('yard_messages as m')
                ->join('yard_room_members as rm', function ($j) use ($userId) {
                    $j->on('rm.room_id', '=', 'm.room_id')->where('rm.user_id', '=', $userId);
                })
                ->join('yard_rooms as r', 'r.id', '=', 'm.room_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                // A deleted room's mentions can't be opened, so they don't count.
                ->whereNull('r.deleted_at')
                ->where('m.is_deleted', false)
                ->where('m.user_id', '!=', $userId)
                ->whereNotNull('m.mentioned_user_ids')
                ->whereRaw(DB::getDriverName() === 'sqlite'
                    ? 'EXISTS (SELECT 1 FROM json_each(m.mentioned_user_ids) WHERE json_each.value = ?)'
                    : 'JSON_CONTAINS(m.mentioned_user_ids, ?)', [DB::getDriverName() === 'sqlite' ? $userId : (string) $userId])
                ->whereRaw('(rm.last_read_at IS NULL OR m.created_at > rm.last_read_at)')
                ->orderByDesc('m.created_at')
                ->limit(20)
                ->get([
                    // The text is `content`: this read `body`, a column that
                    // doesn't exist, so the query always failed into the catch
                    // below and the bell never showed a single mention.
                    'm.id', 'm.room_id', 'm.content', 'm.created_at',
                    'r.name as room_name', 'r.room_type as room_type',
                    'u.id as sender_id', 'u.name as sender_name', 'u.username as sender_username',
                ]);
        } catch (\Throwable $e) {
            return collect();
        }

        return $rows->map(function ($r) {
            $sender = $r->sender_username ?: $r->sender_name ?: __('Someone');
            $where = $r->room_type === RoomType::DirectMessage->value ? '' : ' • ' . ($r->room_name ?: __('Group'));

            return [
                'kind' => 'mention',
                'id' => 'mention:' . $r->id,
                'room_id' => $r->room_id,
                'user_id' => $r->sender_id,
                'title' => '@' . $sender . $where,
                'body' => Str::limit(strip_tags((string) $r->content), 80),
                'time' => Carbon::parse($r->created_at),
                'unread' => 1,
                'link' => route('yard') . '?room=' . $r->room_id,
                'icon' => 'at',
                'seed' => 'user:' . (int) $r->sender_id,
                'initial' => mb_strtoupper(mb_substr($sender, 0, 1)),
            ];
        });
    }

    public function joinRequests(User $viewer): Collection
    {
        $myRoomIds = YardRoom::query()
            ->where('created_by', $viewer->id)
            ->where('room_type', RoomType::PrivateGroup)
            ->pluck('id');

        if ($myRoomIds->isEmpty()) {
            return collect();
        }

        return YardJoinRequest::query()
            ->where('status', 'pending')
            ->whereIn('room_id', $myRoomIds)
            ->with(['user', 'room'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($jr) {
                $name = $jr->user?->username ?: $jr->user?->name ?: __('Someone');

                return [
                    'kind' => 'join_request',
                    'id' => 'jr:' . $jr->id,
                    'room_id' => $jr->room_id,
                    'user_id' => $jr->user_id,
                    'title' => $name,
                    'body' => __('wants to join :group', ['group' => $jr->room?->name ?: __('your group')]),
                    'time' => $jr->created_at,
                    'unread' => 1,
                    'link' => route('yard') . '?room=' . $jr->room_id . '&info=1',
                    'icon' => 'user-plus',
                    'seed' => 'user:' . (int) $jr->user_id,
                    'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
                ];
            });
    }

    public function connectionRequests(User $viewer): Collection
    {
        $userId = $viewer->id;

        $rows = UserConnection::query()
            ->where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', '!=', $userId)
            ->where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $otherIds = $rows->map(fn ($r) => $r->user_a_id === $userId ? $r->user_b_id : $r->user_a_id)->unique();
        $users = User::query()->whereIn('id', $otherIds)->get()->keyBy('id');

        return $rows->map(function ($r) use ($userId, $users) {
            $otherId = $r->user_a_id === $userId ? $r->user_b_id : $r->user_a_id;
            $u = $users->get($otherId);
            $name = $u?->username ?: $u?->name ?: __('Someone');

            return [
                'kind' => 'connection',
                'id' => 'conn:' . $r->id,
                'room_id' => null,
                'user_id' => $otherId,
                'title' => $name,
                'body' => __('wants to connect with you'),
                'time' => $r->created_at,
                'unread' => 1,
                'link' => route('yard') . '?open=connections&tab=requests',
                'icon' => 'link',
                'seed' => 'user:' . (int) $otherId,
                'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            ];
        });
    }

    public function stored(User $viewer): Collection
    {
        return DB::table('notifications')
            ->where('user_id', $viewer->id)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                $data = json_decode($r->data, true) ?: [];
                $title = $data['title'] ?? __('Notification');

                return [
                    'kind' => 'stored',
                    'id' => 'note:' . $r->id,
                    'room_id' => null,
                    'user_id' => null,
                    'title' => $title,
                    'body' => $data['body'] ?? '',
                    'time' => $r->created_at,
                    'unread' => 1,
                    'link' => $data['url'] ?? route('yard'),
                    'icon' => 'tag',
                    'palette' => 'bg-gradient-to-br from-emerald-400 to-emerald-600',
                    'seed' => null,
                    'initial' => mb_strtoupper(mb_substr($title, 0, 1)),
                ];
            });
    }

    /**
     * Everything at once: the rows (newest first, each with the web's avatar
     * class) and the per-tab counts the segmented control and the badge show.
     *
     * @return array{items: Collection, counts: array<string, int>}
     */
    public function build(User $viewer): array
    {
        $dms = $this->dmUnreads($viewer);
        $mentions = $this->mentions($viewer);
        $joins = $this->joinRequests($viewer);
        $connections = $this->connectionRequests($viewer);
        $stored = $this->stored($viewer);

        $items = collect()
            ->merge($dms)->merge($mentions)->merge($joins)->merge($connections)->merge($stored)
            ->map(fn ($n) => $n + ['palette' => AvatarPalette::colorClass($n['seed'])])
            ->sortByDesc(fn ($n) => $n['time'] ? Carbon::parse($n['time'])->getTimestamp() : 0)
            ->values();

        return [
            'items' => $items,
            'counts' => [
                'all' => (int) $dms->sum('unread') + $mentions->count() + $joins->count() + $connections->count() + $stored->count(),
                'mentions' => $mentions->count(),
                'groups' => $joins->count(),
                'connections' => $connections->count(),
            ],
        ];
    }

    public static function filter(Collection $items, string $tab): Collection
    {
        return match ($tab) {
            'mentions' => $items->where('kind', 'mention')->values(),
            'groups' => $items->where('kind', 'join_request')->values(),
            'connections' => $items->where('kind', 'connection')->values(),
            default => $items,
        };
    }

    /**
     * "Mark all read": every chat read, and the stored notifications with
     * them. Requests are untouched; they go when they are answered.
     */
    public function markAllChatsRead(User $viewer): void
    {
        YardRoomMember::where('user_id', $viewer->id)->update(['last_read_at' => now()]);

        DB::table('notifications')
            ->where('user_id', $viewer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Opening a stored notification marks it read (it would count forever
     * otherwise). Returns its data (url, order_id, ...), or null if it isn't
     * this member's.
     */
    public function openStored(User $viewer, string $id): ?array
    {
        $row = DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $viewer->id)
            ->first();

        if (! $row) {
            return null;
        }

        DB::table('notifications')->where('id', $id)->update(['read_at' => now()]);

        return json_decode($row->data, true) ?: [];
    }
}
