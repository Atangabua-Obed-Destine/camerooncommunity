<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceListing;
use App\Services\Market\ListingInsightsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
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
        $this->days = app(ListingInsightsService::class)->days($days);
    }

    /** Daily view counts for the active window (zero-filled). */
    #[Computed]
    public function viewsSeries(): array
    {
        return app(ListingInsightsService::class)->viewsSeries($this->listing, $this->days);
    }

    /** Top viewer regions for the active window. */
    #[Computed]
    public function topRegions(): array
    {
        return app(ListingInsightsService::class)->topRegions($this->listing, $this->days);
    }

    /** Source breakdown for the active window. */
    #[Computed]
    public function topSources(): array
    {
        return app(ListingInsightsService::class)->topSources($this->listing, $this->days);
    }

    #[Computed]
    public function stats(): array
    {
        return app(ListingInsightsService::class)->stats($this->listing, $this->days);
    }

    public function render()
    {
        return view('livewire.marketplace.listing-insights');
    }
}

