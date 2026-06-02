<?php

namespace App\Livewire\Marketplace;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingView;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
#[Title('Listing insights')]
class ListingInsights extends Component
{
    public MarketplaceListing $listing;

    #[Url(except: 30)]
    public int $days = 30; // 7 | 30 | 90

    public function mount(MarketplaceListing|int $listing): void
    {
        $l = $listing instanceof MarketplaceListing
            ? $listing
            : MarketplaceListing::findOrFail($listing);
        if ((int) $l->user_id !== (int) Auth::id()) {
            abort(403);
        }
        $this->listing = $l;
    }

    public function setRange(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    /** Daily view counts for the active window (zero-filled). */
    #[Computed]
    public function viewsSeries(): array
    {
        $from = Carbon::today()->subDays($this->days - 1);

        $rows = MarketplaceListingView::query()
            ->selectRaw('DATE(viewed_at) as d, COUNT(*) as c')
            ->where('listing_id', $this->listing->id)
            ->where('viewed_at', '>=', $from)
            ->groupBy('d')
            ->pluck('c', 'd')
            ->all();

        $out = [];
        for ($i = 0; $i < $this->days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }
        return $out;
    }

    /** Top viewer regions for the active window. */
    #[Computed]
    public function topRegions(): array
    {
        $from = Carbon::today()->subDays($this->days - 1);
        return MarketplaceListingView::query()
            ->selectRaw('COALESCE(NULLIF(region, ""), "Unknown") as region, COUNT(*) as c')
            ->where('listing_id', $this->listing->id)
            ->where('viewed_at', '>=', $from)
            ->groupBy('region')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($r) => ['region' => $r->region, 'count' => (int) $r->c])
            ->all();
    }

    /** Source breakdown for the active window. */
    #[Computed]
    public function topSources(): array
    {
        $from = Carbon::today()->subDays($this->days - 1);
        return MarketplaceListingView::query()
            ->selectRaw('COALESCE(NULLIF(source, ""), "direct") as source, COUNT(*) as c')
            ->where('listing_id', $this->listing->id)
            ->where('viewed_at', '>=', $from)
            ->groupBy('source')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'count' => (int) $r->c])
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        $from   = Carbon::today()->subDays($this->days - 1);
        $views  = MarketplaceListingView::query()->where('listing_id', $this->listing->id)->where('viewed_at', '>=', $from)->count();
        $unique = MarketplaceListingView::query()->where('listing_id', $this->listing->id)->where('viewed_at', '>=', $from)->distinct('session_hash')->count('session_hash');

        $favorites = DB::table('marketplace_favorites')->where('listing_id', $this->listing->id)->where('created_at', '>=', $from)->count();
        $offers    = MarketplaceOffer::query()->where('listing_id', $this->listing->id)->where('created_at', '>=', $from)->count();
        $accepted  = MarketplaceOffer::query()->where('listing_id', $this->listing->id)->where('status', OfferStatus::Accepted->value)->where('updated_at', '>=', $from)->count();
        $orders    = MarketplaceOrder::query()->where('listing_id', $this->listing->id)->where('created_at', '>=', $from)->count();
        $paid      = MarketplaceOrder::query()->where('listing_id', $this->listing->id)->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Released->value])->where('created_at', '>=', $from)->count();

        $ctr = $views > 0 ? round(($favorites + $offers) / $views * 100, 1) : 0.0;

        return compact('views', 'unique', 'favorites', 'offers', 'accepted', 'orders', 'paid', 'ctr');
    }

    public function render()
    {
        return view('livewire.marketplace.listing-insights');
    }
}
