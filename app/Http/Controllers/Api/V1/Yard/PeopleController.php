<?php

namespace App\Http\Controllers\Api\V1\Yard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\DirectMessageService;
use App\Services\Yard\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * People in GoConnect, for the app: the Connections sheet, the new-chat
 * search, the profile preview card, and every connection action. All of it
 * through the services the web's Yard uses (PeopleService, ConnectionService,
 * DirectMessageService), so a state means the same thing on both.
 */
class PeopleController extends Controller
{
    public function __construct(
        private PeopleService $people,
        private ConnectionService $connections,
    ) {}

    /**
     * GET /yard/connections: every list of the Connections sheet at once
     * (they are short), with their counts and the channel that changes them.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mine = $this->people->connections($user);
        $incoming = $this->people->incoming($user);
        $sent = $this->people->sent($user);
        $suggested = $this->people->suggestions($user);
        $blocked = $this->people->blocked($user);

        return response()->json([
            'connections' => $this->rows($mine, $user, 'connected'),
            'incoming' => $this->rows($incoming, $user, 'incoming'),
            'sent' => $this->rows($sent, $user, 'outgoing'),
            'suggestions' => $this->rows($suggested, $user, 'none'),
            'blocked' => $this->rows($blocked, $user, 'blocked-by-me'),
            'counts' => [
                'connections' => $mine->count(),
                'incoming' => $incoming->count(),
                'sent' => $sent->count(),
                'suggestions' => $suggested->count(),
                'blocked' => $blocked->count(),
            ],
            // connection.requested / .accepted / .state arrive here.
            'user_channel' => "tenant.{$user->tenant_id}.user.{$user->id}",
        ]);
    }

    /**
     * GET /yard/people/search?q=&for=chat|connections
     *
     * chat: the new-conversation search (any length, blocked people shown as
     * unavailable). connections: the Connections sheet's (two characters,
     * blocked people left out).
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|max:100',
            'for' => 'nullable|in:chat,connections',
        ]);
        $user = $request->user();

        $users = ($data['for'] ?? 'chat') === 'connections'
            ? $this->people->searchPeople($user, $data['q'])
            : $this->people->searchForChat($user, $data['q']);

        return response()->json(['people' => $this->rows($users, $user)]);
    }

    /** GET /yard/people/{identifier}: the preview card, by id or username. */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $preview = $this->people->preview($request->user(), $identifier);
        if ($preview === null) {
            return response()->json(['message' => __('That member could not be found.'), 'code' => 'not_found'], 404);
        }

        return response()->json(['person' => $preview]);
    }

    /** POST /yard/people/{user}/nickname {nickname}: empty clears it. */
    public function nickname(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['nickname' => 'nullable|string|max:60']);
        $viewer = $request->user();
        if ($user->id === $viewer->id) {
            return response()->json(['message' => __('You cannot rename yourself.'), 'code' => 'self'], 422);
        }

        $saved = $this->people->saveNickname($viewer, $user->id, $data['nickname'] ?? null);

        return response()->json([
            'nickname' => $saved,
            'name' => $viewer->displayNameFor($user),
        ]);
    }

    /**
     * POST /yard/connections/{user}/{action}: request, accept, decline,
     * cancel, disconnect, block, unblock. Answers with the state now.
     */
    public function act(Request $request, User $user, string $action): JsonResponse
    {
        $viewer = $request->user();
        if ($user->id === $viewer->id) {
            return response()->json(['message' => __('That is you.'), 'code' => 'self'], 422);
        }

        $done = match ($action) {
            'request' => $this->request($viewer, $user),
            'accept' => $this->connections->accept($viewer, $user->id),
            'decline' => $this->connections->decline($viewer, $user->id),
            'cancel' => $this->connections->cancel($viewer, $user->id),
            'disconnect' => $this->connections->disconnect($viewer, $user->id),
            'block' => (bool) $this->connections->block($viewer, $user->id),
            'unblock' => $this->connections->unblock($viewer, $user->id),
        };

        $state = $this->people->state($viewer, $user->id);
        // A repeat tap (or the other side got there first) still ends in the
        // state that was asked for: only a real mismatch is an error.
        $expected = [
            'request' => ['outgoing', 'connected'], 'accept' => ['connected'], 'decline' => ['none'],
            'cancel' => ['none'], 'disconnect' => ['none'], 'block' => ['blocked-by-me'], 'unblock' => ['none', 'connected'],
        ][$action];

        if (! $done && ! in_array($state, $expected, true)) {
            return response()->json([
                'message' => __('That is no longer possible.'),
                'code' => 'not_possible',
                'state' => $state,
            ], 422);
        }

        return response()->json([
            'state' => $state,
            // Accepting makes the DM (ConnectionService::accept): open it from here.
            'dm_room_id' => $state === 'connected' ? $this->people->existingDm($viewer->id, $user->id)?->id : null,
        ]);
    }

    private function request(User $from, User $to): bool
    {
        if ($this->people->state($from, $to->id) !== 'none') {
            return false;
        }
        $this->connections->request($from, $to);

        return true;
    }

    /**
     * POST /yard/dm {user_id}: the conversation with a connected member,
     * opened or made.
     */
    public function dm(Request $request, DirectMessageService $dms): JsonResponse
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        $viewer = $request->user();
        $other = User::discoverable()->find((int) $data['user_id']);

        if (! $other || $other->id === $viewer->id) {
            return response()->json(['message' => __('That member could not be found.'), 'code' => 'not_found'], 404);
        }
        if (! $viewer->isConnectedWith($other->id)) {
            return response()->json([
                'message' => __('You must connect with this user before you can message them.'),
                'code' => 'not_connected',
                'state' => $this->people->state($viewer, $other->id),
            ], 403);
        }

        $room = $dms->findOrCreate($viewer, $other->id);

        return response()->json(['room_id' => $room->id]);
    }

    /** A person as every list draws them, with the viewer's state. */
    private function rows(Collection $users, User $viewer, ?string $state = null): array
    {
        $states = $state === null ? $this->people->states($viewer, $users->pluck('id')->all()) : [];
        $names = \App\Models\UserContactName::nicknamesFor($viewer->id, $users->pluck('id')->all());

        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            // What the viewer calls them, when they saved a name.
            'nickname' => $names[$u->id] ?? null,
            'avatar_url' => $u->avatarUrl(),
            'country' => $u->current_country,
            'region' => $u->current_region,
            'state' => $state ?? ($states[$u->id] ?? 'none'),
        ])->values()->all();
    }
}
