<?php

namespace App\Http\Controllers\Api\V1\Yard;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\YardPresenter;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserContactName;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\Yard\RoomListService;
use App\Services\Yard\YardMessageService;
use App\Support\StorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GoConnect's chat list and a room's header, for the app. The list is the
 * web's (RoomListService); the header carries what the web's chat header and
 * DM banner read from ChatRoom's computed properties.
 */
class RoomController extends Controller
{
    public function __construct(
        private RoomListService $rooms,
        private YardMessageService $messages,
        private YardPresenter $present,
    ) {}

    /** GET /yard/rooms?filter=all|unread|favorites|groups|archived&search= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => 'nullable|in:'.implode(',', RoomListService::FILTERS),
            'search' => 'nullable|string|max:100',
        ]);
        $user = $request->user();
        $filter = $data['filter'] ?? 'all';

        return response()->json([
            'filter' => $filter,
            'rooms' => $this->rooms->rooms($user, $filter, $data['search'] ?? null)
                ->map(fn ($room) => $this->present->room($room, $user))->values(),
            'counts' => $this->rooms->filterCounts($user),
            'archived_count' => $this->rooms->archivedCount($user),
            // connection.accepted arrives here: a new DM room to show.
            'user_channel' => "tenant.{$user->tenant_id}.user.{$user->id}",
            // Shown under the list when the member hasn't joined their rooms yet.
            'suggested' => $filter === 'all'
                ? $this->rooms->suggested($user)->map(fn ($r) => $this->present->suggestion($r))->values()
                : [],
        ]);
    }

    /** GET /yard/rooms/{room}: the chat header, and whether the member may write. */
    public function show(Request $request, YardRoom $room): JsonResponse
    {
        $user = $request->user();
        $member = $this->messages->membership($user, $room);
        if (! $member) {
            return response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403);
        }

        $isDm = $room->room_type === RoomType::DirectMessage;
        $partner = null;
        if ($isDm) {
            $partnerId = YardRoomMember::where('room_id', $room->id)->where('user_id', '!=', $user->id)->value('user_id');
            $p = $partnerId ? User::find($partnerId) : null;
            if ($p) {
                $state = 'none';
                $c = UserConnection::between($user->id, $p->id);
                if ($c) {
                    $state = match ($c->status) {
                        UserConnection::STATUS_ACCEPTED => 'connected',
                        UserConnection::STATUS_PENDING => $c->requested_by === $user->id ? 'outgoing' : 'incoming',
                        UserConnection::STATUS_BLOCKED => $c->requested_by === $user->id ? 'blocked-by-me' : 'blocked-by-them',
                        default => 'none',
                    };
                }
                $partner = [
                    'id' => $p->id,
                    'name' => UserContactName::nicknamesFor($user->id, [$p->id])[$p->id] ?? ($p->username ?? $p->name),
                    'username' => $p->username,
                    'avatar_url' => $p->avatarUrl(),
                    // Online: active in the last two minutes, as the web's header decides.
                    'is_online' => $p->last_active_at && $p->last_active_at->gt(now()->subMinutes(2)),
                    'last_active_at' => $p->last_active_at?->toIso8601String(),
                    // Marketplace chats never show the "connect first" banner.
                    'connection_state' => $room->origin === 'marketplace' ? null : $state,
                ];
            }
        }

        return response()->json([
            'room' => [
                'id' => $room->id,
                'type' => $room->room_type->value,
                'is_dm' => $isDm,
                'origin' => $room->origin,
                'name' => $isDm && $partner ? $partner['name'] : $room->name,
                'description' => $room->description,
                'avatar_url' => $isDm ? ($partner['avatar_url'] ?? null) : StorageUrl::for($room->avatar),
                'emoji' => YardPresenter::ROOM_EMOJI[$room->room_type->value] ?? '💬',
                'avatar_seed' => $isDm && $partner ? 'user:'.$partner['id'] : 'room:'.$room->id,
                'members_count' => YardRoomMember::where('room_id', $room->id)->count(),
                'is_admin' => $room->created_by === $user->id,
                'is_favorited' => (bool) $member->is_favorited,
                'is_archived' => $member->archived_at !== null,
                'partner' => $partner,
                // Null when the member may post; otherwise why not.
                'cannot_send' => $this->messages->cannotSend($user, $room),
                'channel' => "tenant.{$room->tenant_id}.room.{$room->id}",
                // MessageDelivered / MessageRead for this member's own messages.
                'receipts_channel' => "tenant.{$room->tenant_id}.user.{$user->id}.receipts",
                // The pinned bar over the thread (newest pin first, as the web's).
                'pinned' => app(\App\Services\Yard\RoomInfoService::class)->pinned($user, $room, 5)
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'type' => $m->message_type->value,
                        'sender' => $m->user ? ($m->user->username ?? $m->user->name) : null,
                        'content' => $m->content ? \Illuminate\Support\Str::limit((string) $m->content, 80) : null,
                    ])->values(),
            ],
            'first_unread_id' => $this->messages->firstUnreadId($user, $room),
        ]);
    }

    /** POST /yard/rooms/{room}/join: a national, regional or city room. */
    public function join(Request $request, YardRoom $room): JsonResponse
    {
        if (! $this->rooms->join($request->user(), $room)) {
            return response()->json(['message' => __('This room cannot be joined directly.'), 'code' => 'not_joinable'], 422);
        }

        return response()->json(['joined' => true, 'room_id' => $room->id]);
    }

    /** POST /yard/rooms/{room}/favorite {on?}: "Pin chat". */
    public function favorite(Request $request, YardRoom $room): JsonResponse
    {
        $on = $this->rooms->setFavorite($request->user(), $room->id, $request->has('on') ? $request->boolean('on') : null);

        return $on === null
            ? response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403)
            : response()->json(['is_favorited' => $on]);
    }

    /** POST /yard/rooms/{room}/archive {on?} */
    public function archive(Request $request, YardRoom $room): JsonResponse
    {
        $on = $this->rooms->setArchived($request->user(), $room->id, $request->has('on') ? $request->boolean('on') : null);

        return $on === null
            ? response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403)
            : response()->json(['is_archived' => $on]);
    }

    /** POST /yard/rooms/{room}/read */
    public function read(Request $request, YardRoom $room): JsonResponse
    {
        $this->messages->markRead($request->user(), $room);

        return response()->json(['ok' => true]);
    }

    /** POST /yard/rooms/{room}/typing */
    public function typing(Request $request, YardRoom $room): JsonResponse
    {
        if ($this->messages->membership($request->user(), $room)) {
            $this->messages->typing($request->user(), $room);
        }

        return response()->json(['ok' => true]);
    }
}
