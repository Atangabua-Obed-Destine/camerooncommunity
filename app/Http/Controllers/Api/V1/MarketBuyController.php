<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BuyPresenter;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrder as Order;
use App\Models\YardMessage;
use App\Models\YardRoomMember;
use App\Services\Market\OrderService;
use App\Services\Market\ReviewService;
use App\Services\Market\SavedSearchService;
use App\Services\MarketplaceChatService;
use App\Support\MarketplaceQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GoMarket's buying side for the app: Buy now and the checkout, Orders,
 * reviews, saved searches and the inbox. Every rule is the web's, through
 * the services its Livewire pages use (OrderService, ReviewService,
 * SavedSearchService, MarketplaceChatService).
 */
class MarketBuyController extends Controller
{
    private const ORDERS_PER_PAGE = 15;

    public function __construct(
        private OrderService $orders,
        private ReviewService $reviews,
        private SavedSearchService $searches,
        private BuyPresenter $present,
    ) {}

    // ── Checkout ─────────────────────────────────────────────────────────

    /** GET /market/listings/{slug}/checkout: the methods, or the order going. */
    public function checkout(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $listing = MarketplaceListing::where('slug', $slug)->with('seller')->first();
        if (! $listing || in_array($listing->user_id, $user->blockedOrBlockingUserIds(), true)) {
            return $this->fail('unavailable', 404);
        }
        if ($blocked = $this->orders->checkoutBlocker($listing, $user)) {
            return $this->fail($blocked, $blocked === 'own_listing' ? 403 : 404);
        }

        return response()->json($this->present->checkout($listing, $user, $this->orders->openOrderFor($listing, $user)));
    }

    /** POST /market/listings/{listing}/checkout {provider}: the order starts. */
    public function start(Request $request, MarketplaceListing $listing): JsonResponse
    {
        $data = $request->validate(['provider' => 'required|string|max:40']);
        $user = $request->user();
        if (in_array($listing->user_id, $user->blockedOrBlockingUserIds(), true)) {
            return $this->fail('unavailable', 404);
        }

        $result = $this->orders->start($listing, $user, $data['provider']);
        if (isset($result['error'])) {
            return $this->fail($result['error'], match ($result['error']) {
                'own_listing' => 403,
                'unavailable' => 404,
                default => 422,
            });
        }

        return response()->json($this->present->checkout($listing->fresh('seller'), $user, $result['order']));
    }

    /** POST /market/orders/{order}/proof {provider_ref, buyer_note?}: "I have paid". */
    public function proof(Request $request, MarketplaceOrder $order): JsonResponse
    {
        $data = $request->validate([
            'provider_ref' => 'nullable|string|max:120',
            'buyer_note' => 'nullable|string|max:2000',
        ]);
        $result = $this->orders->submitProof($order, $request->user(), $data);

        if (! ($result['ok'] ?? false)) {
            if (isset($result['error'])) {
                return $this->fail($result['error'], $result['error'] === 'forbidden' ? 403 : 422);
            }

            return response()->json([
                'message' => __($result['message'] ?? 'Could not submit'),
                'errors' => collect($result['errors'] ?? [])->map(fn ($m) => [__($m)])->all(),
            ], 422);
        }

        return response()->json([
            'message' => __($result['message'] ?? 'Submitted'),
            'checkout' => $this->present->checkout($order->listing()->with('seller')->first(), $request->user(), $order->fresh()),
        ]);
    }

    /** POST /market/orders/{order}/cancel */
    public function cancel(Request $request, MarketplaceOrder $order): JsonResponse
    {
        return $this->step($request, $order, $this->orders->cancel($order, $request->user()), __('Order cancelled'));
    }

    /** POST /market/orders/{order}/paid: the seller has the money. */
    public function paid(Request $request, MarketplaceOrder $order): JsonResponse
    {
        return $this->step($request, $order, $this->orders->markPaid($order, $request->user()), __('Marked as paid'));
    }

    /** POST /market/orders/{order}/release: the item has changed hands. */
    public function release(Request $request, MarketplaceOrder $order): JsonResponse
    {
        return $this->step($request, $order, $this->orders->release($order, $request->user()), __('Marked as completed'));
    }

    /** POST /market/orders/{order}/dispute */
    public function dispute(Request $request, MarketplaceOrder $order): JsonResponse
    {
        return $this->step($request, $order, $this->orders->dispute($order, $request->user()), __('Dispute opened'));
    }

    // ── Orders ───────────────────────────────────────────────────────────

    /** GET /market/orders?tab=buying|selling&status=&page */
    public function orders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => 'nullable|in:buying,selling',
            'status' => 'nullable|in:'.collect(OrderStatus::cases())->pluck('value')->implode(','),
            'page' => 'nullable|integer|min:1',
        ]);
        $user = $request->user();
        $page = $this->orders->query($user, $data['tab'] ?? 'buying', $data['status'] ?? '')
            ->paginate(self::ORDERS_PER_PAGE, ['*'], 'page', $data['page'] ?? 1);

        return response()->json([
            'orders' => collect($page->items())->map(fn (MarketplaceOrder $o) => $this->present->order($o, $user))->values(),
            'counts' => $this->orders->counts($user),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    // ── Reviews ──────────────────────────────────────────────────────────

    /** GET /market/listings/{slug}/review: the form, filled in if reviewed. */
    public function review(Request $request, string $slug): JsonResponse
    {
        $listing = MarketplaceListing::where('slug', $slug)->with('seller')->first();
        if (! $listing) {
            return $this->fail('unavailable', 404);
        }
        if (! $this->reviews->canReview($listing, $request->user())) {
            return response()->json(['message' => __('Not allowed'), 'code' => 'forbidden'], 403);
        }
        $mine = $this->reviews->mine($listing, $request->user());

        return response()->json([
            'listing' => $this->present->listing($listing) + [
                'seller_name' => $listing->seller?->name ?: $listing->seller?->username ?: 'Seller',
            ],
            'review' => $mine ? ['rating' => (int) $mine->rating, 'comment' => (string) $mine->comment] : null,
        ]);
    }

    /** POST /market/listings/{listing}/review {rating, comment?} */
    public function saveReview(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->reviews->canReview($listing, $request->user())) {
            return response()->json(['message' => __('Not allowed'), 'code' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);
        $saved = $this->reviews->save($listing->loadMissing('seller'), $request->user(), (int) $data['rating'], $data['comment'] ?? null);

        return response()->json([
            'message' => $saved['created'] ? __('Review posted, thanks!') : __('Review updated'),
            'created' => $saved['created'],
        ]);
    }

    // ── Saved searches ───────────────────────────────────────────────────

    /** GET /market/saved-searches */
    public function savedSearches(Request $request): JsonResponse
    {
        $list = $this->searches->list($request->user(), app()->getLocale());

        return response()->json([
            'searches' => $list->map(fn ($s) => $this->present->savedSearch($s))->values(),
            'total_new' => (int) $list->sum('new_matches'),
            'limit' => SavedSearchService::LIMIT,
        ]);
    }

    /** POST /market/saved-searches {name?, q, cat, loc, radius, condition, fulfillment, price_min, price_max, sort} */
    public function saveSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'q' => 'nullable|string|max:120',
            'cat' => 'nullable|string|max:120',
            'loc' => 'nullable|string|max:120',
            'radius' => 'nullable|integer|min:0|max:500',
            'condition' => 'nullable|string|max:40',
            'fulfillment' => 'nullable|string|max:40',
            'price_min' => 'nullable|integer|min:0',
            'price_max' => 'nullable|integer|min:0',
            'sort' => 'nullable|in:newest,price_asc,price_desc,popular',
        ]);

        $result = $this->searches->save($request->user(), [
            'query' => $data['q'] ?? null,
            'categorySlug' => $data['cat'] ?? null,
            'loc' => $data['loc'] ?? null,
            'radius' => $data['radius'] ?? null,
            'condition' => $data['condition'] ?? null,
            'fulfillment' => $data['fulfillment'] ?? null,
            'priceMin' => $data['price_min'] ?? null,
            'priceMax' => $data['price_max'] ?? null,
            'sort' => $data['sort'] ?? null,
        ], $data['name'] ?? null);

        if (isset($result['error'])) {
            return $this->fail($result['error'], 422, SavedSearchService::message($result['error']));
        }

        return response()->json([
            'message' => $result['existing']
                ? __('You already saved this search')
                : __('Search saved, we’ll alert you when new matches arrive'),
            'existing' => $result['existing'],
            'id' => $result['search']->id,
        ], $result['existing'] ? 200 : 201);
    }

    /** PATCH /market/saved-searches/{id} {name?, notify_push?, notify_email?} */
    public function updateSearch(Request $request, int $id): JsonResponse
    {
        $s = $this->searches->owned($request->user(), $id);
        if (! $s) {
            return $this->fail('not_found', 404, __('Not found.'));
        }
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'notify_push' => 'sometimes|boolean',
            'notify_email' => 'sometimes|boolean',
        ]);
        if (array_key_exists('name', $data) && ! $this->searches->rename($s, $data['name'])) {
            return response()->json(['message' => __('Name cannot be empty'), 'errors' => ['name' => [__('Name cannot be empty')]]], 422);
        }
        foreach (['push', 'email'] as $channel) {
            if (array_key_exists("notify_$channel", $data)) {
                $this->searches->setNotify($s, $channel, (bool) $data["notify_$channel"]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** DELETE /market/saved-searches/{id} */
    public function deleteSearch(Request $request, int $id): JsonResponse
    {
        $s = $this->searches->owned($request->user(), $id);
        if (! $s) {
            return $this->fail('not_found', 404, __('Not found.'));
        }
        $this->searches->delete($s);

        return response()->json(['message' => __('Saved search deleted')]);
    }

    /** POST /market/saved-searches/{id}/seen: "View matches" was tapped. */
    public function seenSearch(Request $request, int $id): JsonResponse
    {
        $s = $this->searches->owned($request->user(), $id);
        if (! $s) {
            return $this->fail('not_found', 404, __('Not found.'));
        }
        $this->searches->markSeen($s);

        return response()->json(['ok' => true]);
    }

    /** POST /market/saved-searches/seen: Mark all as seen. */
    public function seenAll(Request $request): JsonResponse
    {
        $this->searches->markAllSeen($request->user());

        return response()->json(['message' => __('All caught up')]);
    }

    // ── Inbox and the menu's badges ──────────────────────────────────────

    /** GET /market/inbox: every GoMarket conversation, both sides. */
    public function inbox(Request $request, MarketplaceChatService $chat): JsonResponse
    {
        $all = $chat->conversations($request->user());

        return response()->json([
            'conversations' => $all->map(fn ($c) => $this->present->conversation($c))->values(),
            'counts' => [
                'buying' => (int) $all->where('role', 'buying')->sum('unread'),
                'selling' => (int) $all->where('role', 'selling')->sum('unread'),
            ],
        ]);
    }

    /** GET /market/badges: the drawer's counts (sidebar-nav.blade.php). */
    public function badges(Request $request): JsonResponse
    {
        $me = $request->user();
        $inbox = 0;
        foreach (YardRoomMember::where('user_id', $me->id)->whereHas('room', fn ($q) => $q->where('origin', 'marketplace'))->get(['room_id', 'last_read_at']) as $m) {
            $inbox += YardMessage::where('room_id', $m->room_id)
                ->where('user_id', '!=', $me->id)
                ->where('is_deleted', false)
                ->when($m->last_read_at, fn ($q, $lr) => $q->where('created_at', '>', $lr))
                ->count();
        }

        return response()->json([
            'inbox_unread' => $inbox,
            'orders_pending' => MarketplaceOrder::needingSellerAttention((int) $me->id),
            'saved_new' => $this->searches->totalNew($me),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** An order step: 403 for someone else's order, 422 for a wrong state. */
    private function step(Request $request, MarketplaceOrder $order, ?string $error, string $done): JsonResponse
    {
        if ($error) {
            return $this->fail($error, $error === 'forbidden' ? 403 : 422);
        }
        $order->refresh()->loadMissing(['listing.cover', 'buyer', 'seller']);

        return response()->json(['message' => $done, 'order' => $this->present->order($order, $request->user())]);
    }

    private function fail(string $code, int $status, ?string $message = null): JsonResponse
    {
        return response()->json(['message' => $message ?? OrderService::message($code), 'code' => $code], $status);
    }
}
