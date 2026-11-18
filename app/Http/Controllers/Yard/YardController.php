<?php

namespace App\Http\Controllers\Yard;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class YardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // The open room is part of the URL (?room=<id|slug>), so refreshing,
        // bookmarking or sharing a link puts you back in the same conversation
        // instead of dropping you on the room list. An unknown or non-member
        // room is ignored rather than fatal: stale links still open the Yard.
        return view('yard.index', [
            'rooms'      => $this->roomsFor($user),
            'activeRoom' => $this->memberRoom($request->query('room'), $user),
        ]);
    }

    public function room(Request $request, YardRoom $room)
    {
        $user = $request->user();

        $isMember = $room->members()->where('user_id', $user->id)->exists();
        if (! $isMember) {
            abort(403);
        }

        // Same page as /yard, with this room already open — one Yard, not two
        // divergent layouts.
        return view('yard.index', [
            'rooms'      => $this->roomsFor($user),
            'activeRoom' => $room,
        ]);
    }

    /** The rooms this user belongs to, newest conversation first. */
    protected function roomsFor(User $user)
    {
        return YardRoom::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->withCount('members')
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Resolve a ?room= value (id or slug) to a room this user is a member of,
     * or null. Membership is checked here, not in the browser.
     */
    protected function memberRoom($identifier, User $user): ?YardRoom
    {
        if (! $identifier) {
            return null;
        }

        $room = is_numeric($identifier)
            ? YardRoom::find((int) $identifier)
            : YardRoom::where('slug', $identifier)->first();

        if (! $room) {
            return null;
        }

        return $room->members()->where('user_id', $user->id)->exists() ? $room : null;
    }

    public function joinRoom(Request $request, YardRoom $room)
    {
        $user = $request->user();

        if (! $room->is_system_room) {
            abort(403);
        }

        YardRoomMember::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
        ], [
            'role' => 'member',
        ]);

        $room->increment('members_count');

        if ($request->wantsJson()) {
            return response()->json(['status' => 'joined']);
        }

        return redirect()->route('yard.room', $room);
    }

    /**
     * Withdraw a connection request this user sent and has not had answered.
     */
    public function cancelConnection(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        app(\App\Services\ConnectionService::class)
            ->cancel($request->user(), (int) $request->input('user_id'));

        return response()->json(['status' => 'cancelled', 'state' => 'none']);
    }

    /**
     * End an accepted connection. The conversation survives — only the
     * connection goes, which is what stops either side sending anything new.
     */
    public function disconnectConnection(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        app(\App\Services\ConnectionService::class)
            ->disconnect($request->user(), (int) $request->input('user_id'));

        return response()->json(['status' => 'disconnected', 'state' => 'none']);
    }

    /**
     * Create or open existing Direct Message room between two users.
     */
    public function createDm(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $targetId = (int) $request->input('user_id');

        if ($targetId === $user->id) {
            return response()->json(['error' => 'Cannot DM yourself'], 422);
        }

        // Connection gate: users must be mutually connected before DMing.
        if (! $user->isConnectedWith($targetId)) {
            return response()->json([
                'error' => 'not_connected',
                'message' => 'You must connect with this user before you can message them.',
            ], 403);
        }

        // slug and id both let callers outside the Yard navigate straight in,
        // via /yard/room/{slug} or /yard?room={id}.
        $room = app(\App\Services\DirectMessageService::class)->findOrCreate($user, $targetId);

        return response()->json(['room_id' => $room->id, 'slug' => $room->slug]);
    }

    /**
     * Create a private group room.
     */
    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $user = $request->user();
        $name = $request->input('name');
        $memberIds = array_unique(array_merge(
            [$user->id],
            $request->input('member_ids', [])
        ));

        $room = YardRoom::create([
            'tenant_id' => $user->tenant_id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'country' => $user->current_country ?? 'Cameroon',
            'room_type' => RoomType::PrivateGroup,
            'created_by' => $user->id,
            'is_system_room' => false,
            'members_count' => count($memberIds),
        ]);

        foreach ($memberIds as $memberId) {
            YardRoomMember::create([
                'tenant_id' => $user->tenant_id,
                'room_id' => $room->id,
                'user_id' => $memberId,
                'role' => $memberId === $user->id ? 'admin' : 'member',
            ]);
        }

        return response()->json(['room_id' => $room->id]);
    }

    /**
     * Search users for DM / group member selection.
     */
    public function searchUsers(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $user = $request->user();
        $people = app(\App\Services\Yard\PeopleService::class);
        $users = $people->searchForChat($user, (string) $request->input('q'));
        $states = $people->states($user, $users->pluck('id')->all());

        return response()->json($users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'avatar' => $u->avatar,
            'current_region' => $u->current_region,
            'connection_state' => $states[$u->id] ?? 'none',
        ])->values());
    }

    /**
     * Send a connection request from the current user to the target user.
     */
    public function requestConnection(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $targetId = (int) $request->input('user_id');

        if ($targetId === $user->id) {
            return response()->json(['error' => 'Cannot connect with yourself'], 422);
        }

        $target = User::findOrFail($targetId);

        try {
            app(\App\Services\ConnectionService::class)->request($user, $target);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'request_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok', 'connection_state' => 'outgoing']);
    }

    /**
     * Accept an incoming connection request from the given user.
     */
    public function acceptConnection(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $targetId = (int) $request->input('user_id');

        try {
            $accepted = app(\App\Services\ConnectionService::class)->accept($user, $targetId);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'accept_failed', 'message' => $e->getMessage()], 422);
        }

        // Nothing to accept: the request was withdrawn or declined elsewhere. A second
        // tap on an accepted request is still a success, so only that case gets an ok.
        $state = $this->connectionStateFor($user, $targetId);
        if (! $accepted && $state !== 'connected') {
            return response()->json(['error' => 'no_pending_request', 'connection_state' => $state], 422);
        }

        return response()->json(['status' => 'ok', 'connection_state' => 'connected']);
    }

    /**
     * Decline an incoming connection request. The requester is told only that the
     * request is gone, so their button goes back to "Connect".
     */
    public function declineConnection(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $user = $request->user();
        $targetId = (int) $request->input('user_id');

        $declined = app(\App\Services\ConnectionService::class)->decline($user, $targetId);

        $state = $this->connectionStateFor($user, $targetId);
        if (! $declined && $state !== 'none') {
            return response()->json(['error' => 'no_pending_request', 'connection_state' => $state], 422);
        }

        return response()->json(['status' => 'declined', 'connection_state' => 'none']);
    }

    /**
     * Lightweight read-only lookup of the viewer's connection state with
     * another user. Used by the in-chat profile popup so it can render either
     * a "Connect" or a "Send Message" CTA depending on the relationship.
     */
    public function connectionState(Request $request, int $userId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }
        if ($userId === $user->id) {
            return response()->json(['state' => 'self']);
        }

        return response()->json(['state' => $this->connectionStateFor($user, $userId)]);
    }

    /**
     * Connection state between the viewer and another user, from the viewer's side.
     * Shared by the connection-state endpoint and the profile preview card so the
     * two can never disagree about what to show.
     */
    private function connectionStateFor(User $viewer, int $otherId): string
    {
        return app(\App\Services\Yard\PeopleService::class)->state($viewer, $otherId);
    }

    /**
     * Everything the WhatsApp-style profile preview card needs, in one call.
     *
     * Accepts an id or a username, because @mentions in chat carry a username
     * while every other caller has an id to hand.
     */
    public function userPreview(Request $request, string $identifier)
    {
        $preview = app(\App\Services\Yard\PeopleService::class)->preview($request->user(), $identifier);

        abort_if($preview === null, 404);

        return response()->json($preview);
    }

    /**
     * Save (or clear) a per-viewer nickname for another user — WhatsApp-style
     * "Save contact as...". Pass an empty `nickname` to delete it.
     */
    public function saveNickname(Request $request)
    {
        $data = $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'nickname' => 'nullable|string|max:60',
        ]);

        $user = $request->user();
        $targetId = (int) $data['user_id'];
        if ($targetId === $user->id) {
            return response()->json(['error' => 'cannot_rename_self'], 422);
        }

        $saved = app(\App\Services\Yard\PeopleService::class)->saveNickname($user, $targetId, $data['nickname'] ?? null);

        return response()->json(['status' => $saved === null ? 'cleared' : 'saved', 'nickname' => $saved]);
    }

    /**
     * Lightweight lookup: return the saved nickname (if any) the current
     * viewer has for another user. Used by the call engine so an incoming
     * call shows the saved contact name instead of the raw username.
     */
    public function getNickname(Request $request, int $userId)
    {
        $viewer = $request->user();
        if (!$viewer || $userId === $viewer->id) {
            return response()->json(['nickname' => null]);
        }
        $nickname = $viewer->nicknameFor($userId);
        return response()->json(['nickname' => $nickname]);
    }
}
