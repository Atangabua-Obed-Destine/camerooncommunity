<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Presenters\MarketSellPresenter;
use App\Models\PlatformSetting;
use App\Services\Market\ListingInsightsService;
use App\Services\Market\MyListingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * My listings for the app (the web's MyListings and ListingInsights),
 * through the same MyListingsService. Another member's listing is a 404.
 */
class MarketMineController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private MyListingsService $mine,
        private MarketSellPresenter $present,
    ) {}

    /** GET /market/mine?status&page */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'nullable|in:'.implode(',', MyListingsService::FILTERS),
            'page' => 'nullable|integer|min:1',
        ]);
        $page = $this->mine->query($request->user(), $data['status'] ?? 'all')
            ->paginate(self::PER_PAGE, ['*'], 'page', $data['page'] ?? 1);

        return response()->json([
            'listings' => collect($page->items())->map(fn ($l) => $this->present->mine($l))->values(),
            'total' => $page->total(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /** POST /market/mine/{id}/pause */
    public function pause(Request $request, int $id): JsonResponse
    {
        return $this->after($request, $id, $this->mine->pause($request->user(), $id));
    }

    /** POST /market/mine/{id}/reactivate */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        return $this->after($request, $id, $this->mine->reactivate($request->user(), $id));
    }

    /** POST /market/mine/{id}/bump: to the top of the feed, once a day. */
    public function bump(Request $request, int $id): JsonResponse
    {
        $r = $this->mine->bump($request->user(), $id);
        if (isset($r['wait_minutes'])) {
            return response()->json([
                'message' => __('You can bump again in :min min', ['min' => $r['wait_minutes']]),
                'code' => 'cooldown',
            ], 429);
        }

        return $r['ok']
            ? $this->after($request, $id, true, __('Listing bumped to top'))
            : $this->notFound();
    }

    /** DELETE /market/mine/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->mine->remove($request->user(), $id)
            ? response()->json(['message' => __('Listing removed')])
            : $this->notFound();
    }

    /** GET /market/mine/{id}/sale?q: the Mark-as-sold sheet's defaults and buyers. */
    public function sale(Request $request, int $id): JsonResponse
    {
        $l = $this->mine->find($request->user(), $id);
        if (! $l) {
            return $this->notFound();
        }
        $q = (string) ($request->validate(['q' => 'nullable|string|max:80'])['q'] ?? '');
        $d = $this->mine->saleDefaults($l);
        $buyer = $d['buyer_id'] ? \App\Models\User::find($d['buyer_id']) : null;

        return response()->json([
            'listing' => $this->present->mine($l),
            'buyer' => $buyer ? $this->present->person($buyer) : null,
            // A decimal column reads "45000.00": the field shows "45000".
            'price' => str_contains($d['price'], '.') ? rtrim(rtrim($d['price'], '0'), '.') : $d['price'],
            'currency' => $d['currency'],
            'candidates' => $this->mine->buyerCandidates($request->user(), $l, $q)->map(fn ($u) => $this->present->person($u))->values(),
        ]);
    }

    /** POST /market/mine/{id}/sold {buyer_id?, price?, currency?} */
    public function sold(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'buyer_id' => 'nullable|integer|exists:users,id',
            'price' => 'nullable|numeric|min:0|max:1000000000',
            'currency' => 'nullable|string|size:3',
        ]);

        $result = $this->mine->markSold(
            $request->user(), $id, $d['buyer_id'] ?? null,
            isset($d['price']) ? (string) $d['price'] : null, $d['currency'] ?? null,
        );

        return match ($result) {
            'missing' => $this->notFound(),
            'already' => response()->json(['message' => __('Listing is already marked as sold to this buyer.'), 'code' => 'already'], 409),
            'partial' => $this->after($request, $id, true, __('1 item sold. Quantity updated.')),
            default => $this->after($request, $id, true, __('Listing marked as sold')),
        };
    }

    /** POST /market/mine/bulk {action: pause|reactivate|remove, ids} */
    public function bulk(Request $request): JsonResponse
    {
        $d = $request->validate([
            'action' => 'required|in:pause,reactivate,remove',
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
        ]);
        $user = $request->user();

        $n = match ($d['action']) {
            'pause' => $this->mine->bulkPause($user, $d['ids']),
            'reactivate' => $this->mine->bulkReactivate($user, $d['ids']),
            'remove' => $this->mine->bulkRemove($user, $d['ids']),
        };

        if ($n === 0) {
            return response()->json(['message' => __('None of the selected listings can do that.'), 'changed' => 0], 422);
        }

        return response()->json([
            'changed' => $n,
            'message' => match ($d['action']) {
                'pause' => __('Selected listings paused'),
                'reactivate' => __('Selected listings reactivated'),
                'remove' => __('Selected listings removed'),
            },
        ]);
    }

    /** GET /market/mine/{id}/insights?days=7|30|90 */
    public function insights(Request $request, int $id, ListingInsightsService $insights): JsonResponse
    {
        $l = $this->mine->find($request->user(), $id);
        if (! $l) {
            return $this->notFound();
        }
        $days = $insights->days((int) $request->query('days', 30));
        $fr = app()->getLocale() === 'fr';

        return response()->json([
            'listing' => ['id' => $l->id, 'slug' => $l->slug, 'title' => $l->title, 'has_attributes' => ! empty($l->attributes)],
            'days' => $days,
            'stats' => $insights->stats($l, $days),
            'series' => collect($insights->viewsSeries($l, $days))->map(fn ($p) => $p + [
                'label' => Carbon::parse($p['date'])->translatedFormat('j M'),
            ])->all(),
            'regions' => $insights->topRegions($l, $days),
            'sources' => $insights->topSources($l, $days),
            // Offers are switched off on the platform: their figures are noise.
            'offers_enabled' => filter_var(PlatformSetting::getValue('marketplace_offers_enabled', 'false'), FILTER_VALIDATE_BOOL),
            'locale' => $fr ? 'fr' : 'en',
        ]);
    }

    /** The listing as it now is, with a message. */
    private function after(Request $request, int $id, bool $ok, ?string $message = null): JsonResponse
    {
        $l = $this->mine->find($request->user(), $id);
        if (! $l) {
            return $this->notFound();
        }
        if (! $ok) {
            return response()->json(['message' => __('This listing can’t do that right now.'), 'listing' => $this->present->mine($l)], 422);
        }

        return response()->json(['listing' => $this->present->mine($l), 'message' => $message]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('Not found.')], 404);
    }
}
