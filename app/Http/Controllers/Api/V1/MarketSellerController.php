<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Presenters\MarketSellPresenter;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\DirectMessageService;
use App\Services\Market\SellerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The member profile for the app (the web's SellerProfile, the app-wide
 * profile page): the header, figures and listings, and the connection row's
 * actions through the Yard's own ConnectionService.
 */
class MarketSellerController extends Controller
{
    private const PER_PAGE = 18;

    public function __construct(
        private SellerProfileService $profiles,
        private MarketSellPresenter $present,
    ) {}

    /** GET /market/sellers/{username}?q&sort&page */
    public function show(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $seller = $this->profiles->find($viewer, $username);
        if (! $seller) {
            return response()->json(['message' => __('Not found.')], 404);
        }
        $d = $request->validate([
            'q' => 'nullable|string|max:120',
            'sort' => 'nullable|in:'.implode(',', SellerProfileService::SORTS),
            'page' => 'nullable|integer|min:1',
        ]);

        $self = $viewer->id === $seller->id;
        // A query string's "1" is a string: compared as a number, or ?page=1
        // (what the app sends) lost the header and the profile never opened.
        $pageNo = (int) ($d['page'] ?? 1);
        $page = $this->profiles->listings($viewer, $seller, $d['q'] ?? '', $d['sort'] ?? 'newest')
            ->paginate(self::PER_PAGE, ['*'], 'page', $pageNo);

        return response()->json([
            // The header only with the first page: paging the grid needn't redo it.
            'profile' => $pageNo === 1 ? $this->profile($viewer, $seller) : null,
            'listings' => collect($page->items())->map(fn ($l) => $this->present->profileCard($l, $self))->values(),
            'total' => $page->total(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /**
     * POST /market/sellers/{user}/connection {action}: request | cancel |
     * accept | decline | disconnect | unblock. Each only from the state it
     * belongs to, as the web's buttons are.
     */
    public function connection(Request $request, User $user, ConnectionService $connections): JsonResponse
    {
        $viewer = $request->user();
        if ($viewer->id === $user->id || $user->tenant_id !== $viewer->tenant_id) {
            return response()->json(['message' => __('Not found.')], 404);
        }
        $action = $request->validate(['action' => 'required|in:request,cancel,accept,decline,disconnect,unblock'])['action'];
        $state = $this->profiles->connectionState($viewer, $user);
        $fr = app()->getLocale() === 'fr';

        $allowed = [
            'request' => 'none', 'cancel' => 'outgoing', 'accept' => 'incoming',
            'decline' => 'incoming', 'disconnect' => 'connected', 'unblock' => 'blocked-by-me',
        ][$action];
        if ($state !== $allowed) {
            return response()->json(['message' => __('This changed in the meantime.'), 'connection_state' => $state], 409);
        }

        $message = match ($action) {
            'request' => [$connections->request($viewer, $user), $fr ? 'Demande de connexion envoyée.' : 'Connection request sent.'][1],
            'cancel' => [$connections->cancel($viewer, $user->id), $fr ? 'Demande annulée.' : 'Request cancelled.'][1],
            'accept' => [$connections->accept($viewer, $user->id), $fr ? 'Vous êtes maintenant connectés.' : 'You are now connected.'][1],
            'decline' => [$connections->decline($viewer, $user->id), $fr ? 'Demande refusée.' : 'Request declined.'][1],
            'disconnect' => [$connections->disconnect($viewer, $user->id), $fr ? 'Déconnecté.' : 'Disconnected.'][1],
            'unblock' => [$connections->unblock($viewer, $user->id), $fr ? 'Débloqué.' : 'Unblocked.'][1],
        };

        return response()->json([
            'message' => $message,
            'connection_state' => $this->profiles->connectionState($viewer, $user),
            'dm_room_id' => app(DirectMessageService::class)->existing($viewer, $user->id)?->id,
        ]);
    }

    /**
     * POST /market/sellers/{user}/message: the conversation, for connected
     * people only (it says what to do instead, as the web does).
     */
    public function message(Request $request, User $user, DirectMessageService $dms): JsonResponse
    {
        $viewer = $request->user();
        if ($viewer->id === $user->id || $user->tenant_id !== $viewer->tenant_id) {
            return response()->json(['message' => __('Not found.')], 404);
        }
        if ($this->profiles->connectionState($viewer, $user) !== 'connected') {
            return response()->json([
                'message' => app()->getLocale() === 'fr'
                    ? 'Envoyez d\'abord une demande de connexion : vous pourrez discuter une fois acceptée.'
                    : 'Send a connection request first — you can message each other once it is accepted.',
                'code' => 'not_connected',
            ], 403);
        }

        return response()->json(['room_id' => $dms->findOrCreate($viewer, $user->id)->id]);
    }

    private function profile(User $viewer, User $seller): array
    {
        return $this->present->profile($seller, $viewer, [
            'stats' => $this->profiles->stats($seller),
            'community' => $this->profiles->communityStats($seller),
            'badges' => $this->profiles->badges($seller),
            'state' => $this->profiles->connectionState($viewer, $seller),
            'is_following' => $this->profiles->isFollowing($viewer, $seller),
            'dm_room_id' => $viewer->id === $seller->id ? null : app(DirectMessageService::class)->existing($viewer, $seller->id)?->id,
        ]);
    }
}
