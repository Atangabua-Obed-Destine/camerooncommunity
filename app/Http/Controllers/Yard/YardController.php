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

        $rooms = YardRoom::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->withCount('members')
            ->orderByDesc('last_message_at')
            ->get();

        return view('yard.index', compact('rooms'));
    }

    public function room(Request $request, YardRoom $room)
    {
        $user = $request->user();

        $isMember = $room->members()->where('user_id', $user->id)->exists();
        if (! $isMember) {
            abort(403);
        }

        return view('yard.room', compact('room'));
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

        // Find existing DM between these two users
        $existingRoom = YardRoom::where('room_type', RoomType::DirectMessage)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $targetId))
            ->first();

        if ($existingRoom) {
            // slug lets callers outside the Yard navigate straight to the room:
            // /yard only understands ?open=connections, not ?room=.
            return response()->json(['room_id' => $existingRoom->id, 'slug' => $existingRoom->slug]);
        }

        $target = User::findOrFail($targetId);

        $room = YardRoom::create([
            'tenant_id' => $user->tenant_id,
            'name' => ($user->username ?? $user->name) . ' & ' . ($target->username ?? $target->name),
            'slug' => 'dm-' . Str::uuid()->toString(),
            'country' => $user->current_country ?? 'Cameroon',
            'room_type' => RoomType::DirectMessage,
            'created_by' => $user->id,
            'is_system_room' => false,
            'members_count' => 2,
        ]);

        foreach ([$user->id, $targetId] as $memberId) {
            YardRoomMember::create([
                'tenant_id' => $user->tenant_id,
                'room_id' => $room->id,
                'user_id' => $memberId,
                'role' => 'member',
            ]);
        }

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
        $query = $request->input('q');

        $users = User::where('tenant_id', $user->tenant_id)
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%')
                  ->orWhere('email', 'like', '%' . $query . '%')
                  ->orWhere('username', 'like', '%' . $query . '%');
            })
            ->select('id', 'name', 'username', 'avatar', 'current_region')
            ->limit(20)
            ->get();

        // Annotate each result with the current viewer's connection state.
        $userIds = $users->pluck('id')->all();
        $connections = \App\Models\UserConnection::where(function ($q) use ($user, $userIds) {
                $q->where(function ($qq) use ($user, $userIds) {
                    $qq->where('user_a_id', $user->id)->whereIn('user_b_id', $userIds);
                })->orWhere(function ($qq) use ($user, $userIds) {
                    $qq->where('user_b_id', $user->id)->whereIn('user_a_id', $userIds);
                });
            })
            ->get(['user_a_id', 'user_b_id', 'status', 'requested_by'])
            ->keyBy(fn ($c) => $c->user_a_id === $user->id ? $c->user_b_id : $c->user_a_id);

        $payload = $users->map(function ($u) use ($connections, $user) {
            $c = $connections->get($u->id);
            $state = 'none';
            if ($c) {
                if ($c->status === \App\Models\UserConnection::STATUS_ACCEPTED) {
                    $state = 'connected';
                } elseif ($c->status === \App\Models\UserConnection::STATUS_PENDING) {
                    $state = $c->requested_by === $user->id ? 'outgoing' : 'incoming';
                } elseif ($c->status === \App\Models\UserConnection::STATUS_BLOCKED) {
                    $state = $c->requested_by === $user->id ? 'blocked-by-me' : 'blocked-by-them';
                }
            }
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar,
                'current_region' => $u->current_region,
                'connection_state' => $state,
            ];
        });

        return response()->json($payload);
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
            app(\App\Services\ConnectionService::class)->accept($user, $targetId);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'accept_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok', 'connection_state' => 'connected']);
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
        if ($otherId === $viewer->id) {
            return 'self';
        }

        $c = \App\Models\UserConnection::between($viewer->id, $otherId);

        if (! $c) {
            return 'none';
        }

        if ($c->status === \App\Models\UserConnection::STATUS_ACCEPTED) {
            return 'connected';
        }

        if ($c->status === \App\Models\UserConnection::STATUS_PENDING) {
            return $c->requested_by === $viewer->id ? 'outgoing' : 'incoming';
        }

        if ($c->status === \App\Models\UserConnection::STATUS_BLOCKED) {
            return $c->requested_by === $viewer->id ? 'blocked-by-me' : 'blocked-by-them';
        }

        return 'none';
    }

    /**
     * Everything the WhatsApp-style profile preview card needs, in one call.
     *
     * Accepts an id or a username, because @mentions in chat carry a username
     * while every other caller has an id to hand.
     */
    public function userPreview(Request $request, string $identifier)
    {
        $viewer = $request->user();

        $other = ctype_digit($identifier)
            ? User::find((int) $identifier)          // tenant scope applies automatically
            : User::where('username', $identifier)->first();

        if (! $other) {
            abort(404);
        }

        $state = $this->connectionStateFor($viewer, $other->id);
        $isSelf = $other->id === $viewer->id;
        $blocked = in_array($state, ['blocked-by-me', 'blocked-by-them'], true);

        $location = trim(implode(', ', array_filter([
            $other->current_city,
            config("cameroon.countries.{$other->current_country}", $other->current_country),
        ])));

        // Message should open the conversation that already exists, not start a new one.
        $dmRoom = $isSelf ? null : YardRoom::where('room_type', RoomType::DirectMessage)
            ->whereHas('members', fn ($q) => $q->where('user_id', $viewer->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $other->id))
            ->first(['id', 'slug']);

        return response()->json([
            'id'          => $other->id,
            'name'        => $viewer->displayNameFor($other),   // a saved nickname wins
            'username'    => $other->username,
            'avatar'      => $other->avatar ? asset('storage/' . $other->avatar) : null,
            // Same seed the chat and member lists use, so the enlarged initial is
            // the colour the viewer just tapped.
            'avatar_bg'   => \App\Support\AvatarPalette::colorClass('user:' . $other->id),
            // Withheld when either side has blocked: the card shows only the notice.
            'bio'         => $blocked ? null : $other->bio,
            'location'    => $blocked ? null : ($location ?: null),
            'nickname'    => $isSelf ? null : $viewer->nicknameFor($other->id),
            'state'       => $state,
            'is_self'     => $isSelf,
            'dm_room_id'   => $dmRoom?->id,
            'dm_room_slug' => $dmRoom?->slug,
            'profile_url' => $other->profileUrl(),
        ]);
    }

    /**
     * Save (or clear) a per-viewer nickname for another user — WhatsApp-style
     * "Save contact as...". Pass an empty `nickname` to delete it.
     */
    public function saveNickname(Request $request)
    {
        $data = $request->validate([
            'user_id'  => 'required|integer|exists:users,id|different:_self',
            'nickname' => 'nullable|string|max:60',
        ]);

        $user = $request->user();
        $targetId = (int) $data['user_id'];
        if ($targetId === $user->id) {
            return response()->json(['error' => 'cannot_rename_self'], 422);
        }

        $nickname = trim((string) ($data['nickname'] ?? ''));

        if ($nickname === '') {
            \App\Models\UserContactName::where('owner_user_id', $user->id)
                ->where('contact_user_id', $targetId)
                ->delete();
            \Cache::driver('array')->forget("ucn.{$user->id}.{$targetId}");
            return response()->json(['status' => 'cleared', 'nickname' => null]);
        }

        \App\Models\UserContactName::updateOrCreate(
            ['owner_user_id' => $user->id, 'contact_user_id' => $targetId],
            ['tenant_id' => $user->tenant_id, 'nickname' => $nickname],
        );
        \Cache::driver('array')->forget("ucn.{$user->id}.{$targetId}");

        return response()->json(['status' => 'saved', 'nickname' => $nickname]);
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
