<?php

namespace App\Livewire\Marketplace;

use App\Enums\OfferStatus;
use App\Models\MarketplaceOffer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
class MyOffers extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'sent'; // sent | received

    // Inline counter state (seller side, on Received tab)
    public ?int $counteringOfferId = null;
    public string $counterAmount = '';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['sent', 'received']) ? $tab : 'sent';
        $this->resetPage();
        unset($this->offers, $this->stats);
    }

    #[Computed]
    public function offers()
    {
        $userId = Auth::id();
        $query = MarketplaceOffer::query()
            ->with(['listing.media', 'listing.category', 'buyer', 'seller'])
            ->latest();

        if ($this->tab === 'received') {
            $query->where('seller_id', $userId);
        } else {
            $query->where('buyer_id', $userId);
        }

        return $query->paginate(10);
    }

    #[Computed]
    public function stats(): array
    {
        $userId = Auth::id();

        return [
            'sent' => MarketplaceOffer::where('buyer_id', $userId)->count(),
            'received' => MarketplaceOffer::where('seller_id', $userId)->count(),
            'pendingReceived' => MarketplaceOffer::where('seller_id', $userId)
                ->whereIn('status', [OfferStatus::Pending, OfferStatus::Countered])
                ->count(),
        ];
    }

    protected function ownedOffer(int $id, string $side): MarketplaceOffer
    {
        $column = $side === 'seller' ? 'seller_id' : 'buyer_id';
        return MarketplaceOffer::where('id', $id)
            ->where($column, Auth::id())
            ->firstOrFail();
    }

    public function withdrawOffer(int $id): void
    {
        $offer = $this->ownedOffer($id, 'buyer');
        if (! $offer->status->isOpen()) {
            return;
        }
        $offer->update(['status' => OfferStatus::Withdrawn, 'responded_at' => now()]);
        unset($this->offers);
        $this->dispatch('toast', type: 'info', message: __('Offer withdrawn.'));
    }

    public function acceptOffer(int $id): void
    {
        $offer = $this->ownedOffer($id, 'seller');
        if (! $offer->status->isOpen()) {
            return;
        }
        MarketplaceOffer::where('listing_id', $offer->listing_id)
            ->where('id', '!=', $offer->id)
            ->whereIn('status', [OfferStatus::Pending, OfferStatus::Countered])
            ->update(['status' => OfferStatus::Rejected, 'responded_at' => now()]);

        $offer->update(['status' => OfferStatus::Accepted, 'responded_at' => now()]);
        unset($this->offers, $this->stats);
        $this->dispatch('toast', type: 'success', message: __('Offer accepted.'));
    }

    public function rejectOffer(int $id): void
    {
        $offer = $this->ownedOffer($id, 'seller');
        if (! $offer->status->isOpen()) {
            return;
        }
        $offer->update(['status' => OfferStatus::Rejected, 'responded_at' => now()]);
        unset($this->offers, $this->stats);
        $this->dispatch('toast', type: 'info', message: __('Offer rejected.'));
    }

    public function startCounter(int $id): void
    {
        $offer = $this->ownedOffer($id, 'seller');
        $this->counteringOfferId = $offer->id;
        $this->counterAmount = (string) $offer->amount;
    }

    public function cancelCounter(): void
    {
        $this->counteringOfferId = null;
        $this->counterAmount = '';
    }

    public function submitCounter(): void
    {
        if (! $this->counteringOfferId) {
            return;
        }
        $this->validate([
            'counterAmount' => ['required', 'numeric', 'min:1'],
        ]);

        $original = $this->ownedOffer($this->counteringOfferId, 'seller');
        if (! $original->status->isOpen()) {
            $this->cancelCounter();
            return;
        }

        $original->update(['status' => OfferStatus::Countered, 'counter_amount' => $this->counterAmount, 'responded_at' => now()]);

        MarketplaceOffer::create([
            'listing_id' => $original->listing_id,
            'buyer_id' => $original->buyer_id,
            'seller_id' => $original->seller_id,
            'parent_offer_id' => $original->id,
            'amount' => $this->counterAmount,
            'currency' => $original->currency,
            'status' => OfferStatus::Pending,
            'message' => null,
        ]);

        $this->cancelCounter();
        unset($this->offers, $this->stats);
        $this->dispatch('toast', type: 'success', message: __('Counter-offer sent.'));
    }

    public function render()
    {
        return view('livewire.marketplace.my-offers');
    }
}

