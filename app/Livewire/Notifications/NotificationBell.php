<?php

namespace App\Livewire\Notifications;

use App\Enums\RoomType;
use App\Models\UserConnection;
use App\Models\YardJoinRequest;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Real-time notification bell shown in the global header.
 *
 * Aggregates four live signals into a single dropdown:
 *   1. Unread DM messages (one row per conversation, latest first).
 *   2. Unread @mentions across any room.
 *   3. Pending join requests for groups the current user owns (admin).
 *   4. Pending incoming connection requests.
 *
 * Realtime: refreshes itself when the existing client-side dispatchers
 * fire ('connection-incoming', 'connection-updated', 'bell-refresh',
 * 'refreshRoomList', etc.) — no extra Echo wiring needed in this PHP
 * class because the blade view re-broadcasts user channel events as
 * Livewire 'bell-refresh' calls.
 */
class NotificationBell extends Component
{
    /** Active filter tab: 'all' | 'mentions' | 'groups' | 'connections'. */
    public string $tab = 'all';

    /** ─────────────────────────────────────────────────────────────────
     * Source #1 — DM rooms with unread messages.
     * Returns array of rows: id, room_id, title, body, time, avatar_url, link, unread.
     * ───────────────────────────────────────────────────────────────── */
    #[Computed]
    public function dmUnreads(): Collection
    {
        $userId = auth()->id();
        if (! $userId) return collect();

        $memberships = YardRoomMember::query()
            ->where('user_id', $userId)
            ->whereHas('room', fn ($q) => $q->where('room_type', RoomType::DirectMessage))
            ->with(['room.members.user'])
            ->get();

        $rows = collect();
        foreach ($memberships as $m) {
            $room = $m->room;
            if (! $room) continue;

            $count = DB::table('yard_messages')
                ->where('room_id', $room->id)
                ->where('user_id', '!=', $userId)
                ->where('is_deleted', false)
                ->when($m->last_read_at, fn ($q) => $q->where('created_at', '>', $m->last_read_at))
                ->count();

            if ($count <= 0) continue;

            $partner = $room->members->first(fn ($mm) => $mm->user_id !== $userId)?->user;
            $viewer = auth()->user();
            $name = ($viewer && $partner ? $viewer->displayNameFor($partner) : null) ?: ($partner?->username ?? $partner?->name ?? __('Someone'));

            $rows->push([
                'kind'      => 'dm',
                'id'        => 'dm:' . $room->id,
                'room_id'   => $room->id,
                'user_id'   => $partner?->id,
                'title'     => $name,
                'body'      => $room->last_message_preview ?: trans_choice('{1} :n new message|[2,*] :n new messages', $count, ['n' => $count]),
                'time'      => $room->last_message_at ?: $m->updated_at,
                'unread'    => $count,
                'link'      => $room->origin === 'marketplace'
                                ? route('marketplace.inbox') . '?c=' . $room->id
                                : route('yard') . '?open=' . $room->id,
                'icon'      => 'chat',
                'palette'   => \App\Support\AvatarPalette::colorClass('user:' . ($partner?->id ?? 0)),
                'initial'   => mb_strtoupper(mb_substr($name, 0, 1)),
            ]);
        }

        return $rows;
    }

    /** ─────────────────────────────────────────────────────────────────
     * Source #2 — Unread @mentions in any room.
     * ───────────────────────────────────────────────────────────────── */
    #[Computed]
    public function mentions(): Collection
    {
        $userId = auth()->id();
        if (! $userId) return collect();

        try {
            $rows = DB::table('yard_messages as m')
                ->join('yard_room_members as rm', function ($j) use ($userId) {
                    $j->on('rm.room_id', '=', 'm.room_id')->where('rm.user_id', '=', $userId);
                })
                ->join('yard_rooms as r', 'r.id', '=', 'm.room_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.is_deleted', false)
                ->where('m.user_id', '!=', $userId)
                ->whereNotNull('m.mentioned_user_ids')
                ->whereRaw('JSON_CONTAINS(m.mentioned_user_ids, ?)', [(string) $userId])
                ->whereRaw('(rm.last_read_at IS NULL OR m.created_at > rm.last_read_at)')
                ->orderByDesc('m.created_at')
                ->limit(20)
                ->get([
                    'm.id', 'm.room_id', 'm.body', 'm.created_at',
                    'r.name as room_name', 'r.room_type as room_type',
                    'u.id as sender_id', 'u.name as sender_name', 'u.username as sender_username',
                ]);
        } catch (\Throwable $e) {
            return collect();
        }

        return $rows->map(function ($r) {
            $sender = $r->sender_username ?: $r->sender_name ?: __('Someone');
            $where  = $r->room_type === RoomType::DirectMessage->value ? '' : ' • ' . ($r->room_name ?: __('Group'));

            return [
                'kind'    => 'mention',
                'id'      => 'mention:' . $r->id,
                'room_id' => $r->room_id,
                'user_id' => $r->sender_id,
                'title'   => '@' . $sender . $where,
                'body'    => \Illuminate\Support\Str::limit(strip_tags((string) $r->body), 80),
                'time'    => Carbon::parse($r->created_at),
                'unread'  => 1,
                'link'    => route('yard') . '?open=' . $r->room_id,
                'icon'    => 'at',
                'palette' => \App\Support\AvatarPalette::colorClass('user:' . (int) $r->sender_id),
                'initial' => mb_strtoupper(mb_substr($sender, 0, 1)),
            ];
        });
    }

    /** ─────────────────────────────────────────────────────────────────
     * Source #3 — Pending join requests for groups I created.
     * ───────────────────────────────────────────────────────────────── */
    #[Computed]
    public function joinRequests(): Collection
    {
        $userId = auth()->id();
        if (! $userId) return collect();

        $myRoomIds = YardRoom::query()
            ->where('created_by', $userId)
            ->where('room_type', RoomType::PrivateGroup)
            ->pluck('id');

        if ($myRoomIds->isEmpty()) return collect();

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
                    'kind'    => 'join_request',
                    'id'      => 'jr:' . $jr->id,
                    'room_id' => $jr->room_id,
                    'user_id' => $jr->user_id,
                    'title'   => $name,
                    'body'    => __('wants to join :group', ['group' => $jr->room?->name ?: __('your group')]),
                    'time'    => $jr->created_at,
                    'unread'  => 1,
                    'link'    => route('yard') . '?open=' . $jr->room_id . '&info=1',
                    'icon'    => 'user-plus',
                    'palette' => \App\Support\AvatarPalette::colorClass('user:' . (int) $jr->user_id),
                    'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
                ];
            });
    }

    /** ─────────────────────────────────────────────────────────────────
     * Source #4 — Pending incoming connection requests.
     * ───────────────────────────────────────────────────────────────── */
    #[Computed]
    public function connectionRequests(): Collection
    {
        $userId = auth()->id();
        if (! $userId) return collect();

        $rows = UserConnection::query()
            ->where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', '!=', $userId)
            ->where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) return collect();

        $otherIds = $rows->map(fn ($r) => $r->user_a_id === $userId ? $r->user_b_id : $r->user_a_id)->unique();
        $users = \App\Models\User::query()->whereIn('id', $otherIds)->get()->keyBy('id');

        return $rows->map(function ($r) use ($userId, $users) {
            $otherId = $r->user_a_id === $userId ? $r->user_b_id : $r->user_a_id;
            $u = $users->get($otherId);
            $name = $u?->username ?: $u?->name ?: __('Someone');
            return [
                'kind'    => 'connection',
                'id'      => 'conn:' . $r->id,
                'room_id' => null,
                'user_id' => $otherId,
                'title'   => $name,
                'body'    => __('wants to connect with you'),
                'time'    => $r->created_at,
                'unread'  => 1,
                'link'    => route('yard') . '?open=connections&tab=requests',
                'icon'    => 'link',
                'palette' => \App\Support\AvatarPalette::colorClass('user:' . (int) $otherId),
                'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            ];
        });
    }

    /** ─────────────────────────────────────────────────────────────────
     * Source #5 — Stored DB notifications (e.g. marketplace order events
     * emitted via NotificationService). The bell otherwise builds itself from
     * live signals; this surfaces persisted notifications too.
     * ───────────────────────────────────────────────────────────────── */
    #[Computed]
    public function storedNotifications(): Collection
    {
        $userId = auth()->id();
        if (! $userId) return collect();

        return DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                $data = json_decode($r->data, true) ?: [];
                $title = $data['title'] ?? __('Notification');
                return [
                    'kind'    => 'stored',
                    'id'      => 'note:' . $r->id,
                    'room_id' => null,
                    'user_id' => null,
                    'title'   => $title,
                    'body'    => $data['body'] ?? '',
                    'time'    => $r->created_at,
                    'unread'  => 1,
                    'link'    => $data['url'] ?? route('yard'),
                    'icon'    => 'tag',
                    'palette' => 'bg-gradient-to-br from-emerald-400 to-emerald-600',
                    'initial' => mb_strtoupper(mb_substr($title, 0, 1)),
                ];
            });
    }

    /** Unified, time-sorted notification feed. */
    #[Computed]
    public function feed(): Collection
    {
        $all = collect()
            ->merge($this->dmUnreads)
            ->merge($this->mentions)
            ->merge($this->joinRequests)
            ->merge($this->connectionRequests)
            ->merge($this->storedNotifications);

        $filtered = match ($this->tab) {
            'mentions'    => $all->where('kind', 'mention'),
            'groups'      => $all->where('kind', 'join_request'),
            'connections' => $all->where('kind', 'connection'),
            default       => $all,
        };

        return $filtered
            ->sortByDesc(fn ($n) => $n['time'] ? Carbon::parse($n['time'])->getTimestamp() : 0)
            ->values();
    }

    /** Counts per tab — used to render colored badges on the segmented control. */
    #[Computed]
    public function counts(): array
    {
        return [
            'all'         => $this->dmUnreads->sum('unread') + $this->mentions->count() + $this->joinRequests->count() + $this->connectionRequests->count() + $this->storedNotifications->count(),
            'mentions'    => $this->mentions->count(),
            'groups'      => $this->joinRequests->count(),
            'connections' => $this->connectionRequests->count(),
        ];
    }

    /** Total badge count shown on the bell icon. */
    #[Computed]
    public function total(): int
    {
        return (int) $this->counts['all'];
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['all', 'mentions', 'groups', 'connections'], true) ? $tab : 'all';
    }

    /** Mark every chat room as read for this user (does not touch action items). */
    public function markAllChatsRead(): void
    {
        $userId = auth()->id();
        if (! $userId) return;

        YardRoomMember::where('user_id', $userId)->update(['last_read_at' => now()]);

        // Also clear stored DB notifications (e.g. marketplace order events).
        DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->bust();
        $this->dispatch('refreshRoomList');
        $this->dispatch('toast', type: 'success', message: __('All notifications marked as read'));
    }

    /** Refresh hooks fired by existing client dispatchers. */
    #[On('connection-incoming')]
    #[On('connection-updated')]
    #[On('connection-badge-refresh')]
    #[On('refreshRoomList')]
    #[On('bell-refresh')]
    public function bust(): void
    {
        unset($this->dmUnreads, $this->mentions, $this->joinRequests, $this->connectionRequests, $this->storedNotifications, $this->feed, $this->counts, $this->total);
    }

    public function render()
    {
        return view('livewire.notifications.notification-bell');
    }
}
