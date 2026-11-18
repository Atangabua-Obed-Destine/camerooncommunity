<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Presenters\MarketPresenter;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceFavorite;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\Market\ListingService;
use App\Support\CameroonGeo;
use App\Support\MarketplaceQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GoMarket for the app: the feed (the web's MarketplaceQueryBuilder, so the
 * filters and orderings are the web's), a listing, and what a member does on
 * it (ListingService, shared with the web's ListingDetail).
 */
class MarketController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(
        private ListingService $listings,
        private MarketPresenter $present,
    ) {}

    /** Everything the filters sheet offers. */
    public function filters(): JsonResponse
    {
        $fr = app()->getLocale() === 'fr';

        return response()->json([
            'categories' => MarketplaceCategory::roots()->active()->with('children')->orderBy('position')->get()
                ->map(fn ($c) => $this->present->category($c))->values(),
            'regions' => collect(CameroonGeo::regionLabels())
                ->map(fn ($l, $key) => ['value' => $key, 'label' => $fr ? $l['fr'] : $l['en']])->values(),
            'radii' => [10, 25, 50, 100, 250, 500],
            'conditions' => collect(ListingCondition::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $fr ? $c->labelFr() : $c->label()])->values(),
            'fulfillments' => collect(ListingFulfillment::cases())->map(fn ($f) => ['value' => $f->value, 'label' => $fr ? $f->labelFr() : $f->label(), 'icon' => $f->icon()])->values(),
            // The phone's four orderings (the web's mobile sort row).
            'sorts' => [
                ['value' => 'newest', 'label' => $fr ? 'Plus récents' : 'Newest'],
                ['value' => 'price_asc', 'label' => $fr ? 'Prix croissant' : 'Price: low to high'],
                ['value' => 'price_desc', 'label' => $fr ? 'Prix décroissant' : 'Price: high to low'],
                ['value' => 'popular', 'label' => $fr ? 'Populaires' : 'Popular'],
            ],
            'report_reasons' => collect([
                // The web's report modal, word for word.
                'scam' => ['Scam / fraud', 'Arnaque / fraude'],
                'spam' => ['Spam or duplicate', 'Spam ou doublon'],
                'inappropriate' => ['Inappropriate content', 'Contenu inapproprié'],
                'misinformation' => ['Misleading description', 'Fausse information / description trompeuse'],
                'harassment' => ['Harassment', 'Harcèlement'],
                'other' => ['Other', 'Autre'],
            ])->map(fn ($l, $v) => ['value' => $v, 'label' => $fr ? $l[1] : $l[0]])->values(),
        ]);
    }

    /** GET /market/listings?q&cat&loc&radius&condition&fulfillment&price_min&price_max&sort&page */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'cat' => 'nullable|string|max:120',
            'loc' => 'nullable|string|max:120',
            // The filters sheet's slider, as the web's: 0 (that region) to 500 km.
            'radius' => 'nullable|integer|min:0|max:500',
            'condition' => 'nullable|string|max:40',
            'fulfillment' => 'nullable|string|max:40',
            'price_min' => 'nullable|integer|min:0',
            'price_max' => 'nullable|integer|min:0',
            'sort' => 'nullable|in:newest,price_asc,price_desc,popular',
            'page' => 'nullable|integer|min:1',
        ]);
        $user = $request->user();

        $q = MarketplaceQueryBuilder::build([
            'query' => $data['q'] ?? null,
            'categorySlug' => $data['cat'] ?? null,
            'condition' => $data['condition'] ?? null,
            'fulfillment' => $data['fulfillment'] ?? null,
            'priceMin' => $data['price_min'] ?? null,
            'priceMax' => $data['price_max'] ?? null,
            'sort' => $data['sort'] ?? 'newest',
        ])->with(['category', 'cover']);

        // An area chosen: that region and the radius around it (the web's
        // ?loc=&radius=). None: the whole market, the member's region first.
        [$fromLat, $fromLng] = $this->home($user);
        if (! empty($data['loc']) && ($c = CameroonGeo::centroidForRegion($data['loc']))) {
            MarketplaceQueryBuilder::applyRadius($q, $c['lat'], $c['lng'], $data['radius'] ?? null);
            [$fromLat, $fromLng] = [$c['lat'], $c['lng']];
        } elseif ($user?->current_region) {
            MarketplaceQueryBuilder::applyLocalFirst($q, (string) $user->current_region);
        }

        $page = $q->paginate(self::PER_PAGE, ['*'], 'page', $data['page'] ?? 1);

        return response()->json([
            'listings' => collect($page->items())->map(fn ($l) => $this->present->card($l, $fromLat, $fromLng))->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /** GET /market/listings/{slug} */
    public function show(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $listing = MarketplaceListing::where('slug', $slug)->with(['seller', 'category', 'media'])->first();
        if (! $listing || ! $this->listings->viewable($listing, $user)
            || ($user && in_array($listing->user_id, $user->blockedOrBlockingUserIds(), true))) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        // One view a day per member (the web counts per browser).
        $this->listings->recordView($listing, $user, hash('sha256', 'user:'.$user->id), 'app');

        [$lat, $lng] = $this->home($user);

        return response()->json(['listing' => $this->present->detail($listing->fresh(['seller', 'category', 'media']), $user, [
            'default_inquiry' => $this->listings->defaultInquiry($listing),
            'is_favorited' => $this->listings->isFavorited($listing, $user),
            'has_messaged' => $this->listings->hasMessaged($listing, $user),
            'has_reported' => $this->listings->hasReported($listing, $user),
            'is_following' => $user && $listing->user_id !== $user->id
                && UserFollow::where('follower_id', $user->id)->where('following_id', $listing->user_id)->exists(),
            'similar' => $this->listings->similar($listing, $user)->map(fn ($s) => $this->present->card($s, $lat, $lng))->values()->all(),
        ])]);
    }

    /** POST /market/listings/{listing}/favorite */
    public function favorite(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->reachable($listing, $request->user())) {
            return $this->notFound();
        }
        $on = $this->listings->toggleFavorite($listing, $request->user());

        return response()->json(['is_favorited' => $on, 'saves' => (int) $listing->fresh()->favorites_count]);
    }

    /** GET /market/favorites?page */
    public function favorites(Request $request): JsonResponse
    {
        $page = MarketplaceFavorite::where('user_id', $request->user()->id)
            ->whereHas('listing')
            ->with(['listing.category', 'listing.cover'])
            ->latest()
            ->paginate(self::PER_PAGE);

        return response()->json([
            'listings' => collect($page->items())->map(fn ($f) => $this->present->card($f->listing))->values(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /** POST /market/listings/{listing}/inquiry {text?}: message the seller; returns the chat. */
    public function inquire(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->reachable($listing, $request->user())) {
            return $this->notFound();
        }
        $text = $request->validate(['text' => 'nullable|string|max:1000'])['text'] ?? null;
        $result = $this->listings->inquire($listing, $request->user(), $text);

        return match ($result['error'] ?? null) {
            null => response()->json(['room_id' => $result['room']->id]),
            'rate_limited' => response()->json(['message' => __('Too many messages. Try again in :n minutes.', ['n' => $result['retry_minutes']]), 'code' => 'rate_limited'], 429),
            'blocked' => response()->json(['message' => __('You can no longer message this user.'), 'code' => 'blocked'], 403),
            default => response()->json(['message' => __('Could not open the conversation.'), 'code' => $result['error']], 422),
        };
    }

    /** POST /market/listings/{listing}/report {reason, details?} */
    public function report(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->reachable($listing, $request->user())) {
            return $this->notFound();
        }
        $data = $request->validate([
            'reason' => 'required|in:'.collect(ReportReason::cases())->pluck('value')->implode(','),
            'details' => 'nullable|string|max:1000',
        ]);

        return match ($this->listings->report($listing, $request->user(), $data['reason'], $data['details'] ?? null)) {
            null => response()->json(['message' => __('Thanks, our team will review this listing.')]),
            'already_reported' => response()->json(['message' => __('You already reported this listing.'), 'code' => 'already_reported'], 409),
            'rate_limited' => response()->json(['message' => __('Too many reports. Try again in :n minutes.', ['n' => 60]), 'code' => 'rate_limited'], 429),
            default => response()->json(['message' => __('Not found.'), 'code' => 'own_listing'], 422),
        };
    }

    /** POST /market/listings/{listing}/block-seller */
    public function blockSeller(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->listings->blockSeller($listing, $request->user())) {
            return response()->json(['message' => __('Not found.')], 422);
        }

        return response()->json(['message' => __('Seller blocked. Their listings will be hidden.')]);
    }

    /** POST /market/sellers/{user}/follow: toggles, across no block, same community. */
    public function follow(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        if ($me->id === $user->id || $me->hasBlockedOrIsBlockedBy($user->id) || $user->tenant_id !== $me->tenant_id) {
            return response()->json(['message' => __('Not found.')], 422);
        }

        $existing = UserFollow::where('follower_id', $me->id)->where('following_id', $user->id)->first();
        $existing ? $existing->delete() : UserFollow::create(['follower_id' => $me->id, 'following_id' => $user->id]);

        return response()->json([
            'is_following' => $existing === null,
            'message' => $existing ? __('Unfollowed') : __('Following! You\'ll see their new listings'),
        ]);
    }

    /** A listing this member may act on: viewable, and not across a block. */
    private function reachable(MarketplaceListing $listing, User $user): bool
    {
        return $this->listings->viewable($listing, $user)
            && ! in_array($listing->user_id, $user->blockedOrBlockingUserIds(), true);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('Not found.')], 404);
    }

    /** Where the member is, for "4 km away": their GPS fix, else their region's centre. */
    private function home(?User $user): array
    {
        if ($user?->current_lat !== null && $user?->current_lng !== null) {
            return [(float) $user->current_lat, (float) $user->current_lng];
        }
        $c = $user?->current_region ? CameroonGeo::centroidForRegion((string) $user->current_region) : null;

        return $c ? [$c['lat'], $c['lng']] : [null, null];
    }
}
