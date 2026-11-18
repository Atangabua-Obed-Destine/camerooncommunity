<?php

namespace App\Services\Market;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingView;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A listing's figures over the last 7, 30 or 90 days (the web's
 * ListingInsights), for the Livewire page and the API alike.
 */
class ListingInsightsService
{
    public const RANGES = [7, 30, 90];

    public function days(int $days): int
    {
        return in_array($days, self::RANGES, true) ? $days : 30;
    }

    private function from(int $days): Carbon
    {
        return Carbon::today()->subDays($days - 1);
    }

    /** Daily view counts for the window, zero-filled. @return array<int, array{date:string, count:int}> */
    public function viewsSeries(MarketplaceListing $listing, int $days): array
    {
        $from = $this->from($days);
        $rows = MarketplaceListingView::query()
            ->selectRaw('DATE(viewed_at) as d, COUNT(*) as c')
            ->where('listing_id', $listing->id)
            ->where('viewed_at', '>=', $from)
            ->groupBy('d')
            ->pluck('c', 'd')
            ->all();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }

        return $out;
    }

    /** @return array<int, array{region:string, count:int}> */
    public function topRegions(MarketplaceListing $listing, int $days): array
    {
        return MarketplaceListingView::query()
            ->selectRaw('COALESCE(NULLIF(region, ""), "Unknown") as region, COUNT(*) as c')
            ->where('listing_id', $listing->id)
            ->where('viewed_at', '>=', $this->from($days))
            ->groupBy('region')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($r) => ['region' => $r->region, 'count' => (int) $r->c])
            ->all();
    }

    /** @return array<int, array{source:string, count:int}> */
    public function topSources(MarketplaceListing $listing, int $days): array
    {
        return MarketplaceListingView::query()
            ->selectRaw('COALESCE(NULLIF(source, ""), "direct") as source, COUNT(*) as c')
            ->where('listing_id', $listing->id)
            ->where('viewed_at', '>=', $this->from($days))
            ->groupBy('source')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'count' => (int) $r->c])
            ->all();
    }

    /** @return array{views:int, unique:int, favorites:int, offers:int, accepted:int, orders:int, paid:int, ctr:float} */
    public function stats(MarketplaceListing $listing, int $days): array
    {
        $from = $this->from($days);
        $views = MarketplaceListingView::query()->where('listing_id', $listing->id)->where('viewed_at', '>=', $from)->count();
        $unique = MarketplaceListingView::query()->where('listing_id', $listing->id)->where('viewed_at', '>=', $from)->distinct('session_hash')->count('session_hash');

        $favorites = DB::table('marketplace_favorites')->where('listing_id', $listing->id)->where('created_at', '>=', $from)->count();
        $offers = MarketplaceOffer::query()->where('listing_id', $listing->id)->where('created_at', '>=', $from)->count();
        $accepted = MarketplaceOffer::query()->where('listing_id', $listing->id)->where('status', OfferStatus::Accepted->value)->where('updated_at', '>=', $from)->count();
        $orders = MarketplaceOrder::query()->where('listing_id', $listing->id)->where('created_at', '>=', $from)->count();
        $paid = MarketplaceOrder::query()->where('listing_id', $listing->id)->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Released->value])->where('created_at', '>=', $from)->count();

        $ctr = $views > 0 ? round(($favorites + $offers) / $views * 100, 1) : 0.0;

        return compact('views', 'unique', 'favorites', 'offers', 'accepted', 'orders', 'paid', 'ctr');
    }
}
