<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceFavorite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
class Favorites extends Component
{
    // Keeps the page number right after a remove; see MyListings.
    use WithPagination;

    #[Computed]
    public function favorites()
    {
        return MarketplaceFavorite::query()
            ->where('user_id', Auth::id())
            ->with(['listing.category', 'listing.cover'])
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

