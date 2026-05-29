<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceFavorite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
class Favorites extends Component
{
    #[Computed]
    public function favorites()
    {
        return MarketplaceFavorite::query()
            ->where('user_id', Auth::id())
            ->with(['listing.category', 'listing.media' => fn ($m) => $m->limit(1)])
            ->orderByDesc('created_at')
            ->paginate(24);
    }

    public function remove(int $listingId): void
    {
        MarketplaceFavorite::where('user_id', Auth::id())
            ->where('listing_id', $listingId)
            ->delete();
    }

    public function render()
    {
        return view('livewire.marketplace.favorites');
    }
}
