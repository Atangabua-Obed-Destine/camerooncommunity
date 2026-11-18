<?php

namespace App\Livewire\Marketplace;

use App\Enums\OfferStatus;
use App\Models\MarketplaceOffer;
use App\Services\OfferService;
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
            // Waiting on me: offers to answer as a seller, counters to answer as a buyer.
            'pendingReceived' => MarketplaceOffer::where('seller_id', $userId)
                ->where('status', OfferStatus::Pending)
                ->count(),
            'countersToAnswer' => MarketplaceOffer::where('buyer_id', $userId)
                ->where('status', OfferStatus::Countered)
                ->count(),
        ];
    }

    /** The offer, if this member is on the given side of it. */
    protected function ownedOffer(int $id, string $side): MarketplaceOffer
    {
        $column = $side === 'seller' ? 'seller_id' : 'buyer_id';
        return MarketplaceOffer::where('id', $id)
            ->where($column, Auth::id())
            ->firstOrFail();
    }

    private function after(bool $done, string $type, string $message): void
    {
        unset($this->offers, $this->stats);
        $done
            ? $this->dispatch('toast', type: $type, message: $message)
            : $this->dispatch('toast', type: 'error', message: __('This offer has already been answered.'));
    }

    public function withdrawOffer(int $id, OfferService $offers): void
    {
        $this->after($offers->withdraw($this->ownedOffer($id, 'buyer'), Auth::user()), 'info', __('Offer withdrawn.'));
    }

    public function acceptCounter(int $id, OfferService $offers): void
    {
        $this->after($offers->acceptCounter($this->ownedOffer($id, 'buyer'), Auth::user()), 'success', __('Counter-offer accepted.'));
    }

    public function declineCounter(int $id, OfferService $offers): void
    {
        $this->after($offers->declineCounter($this->ownedOffer($id, 'buyer'), Auth::user()), 'info', __('Counter-offer declined.'));
    }

    public function acceptOffer(int $id, OfferService $offers): void
    {
        $this->after($offers->accept($this->ownedOffer($id, 'seller'), Auth::user()), 'success', __('Offer accepted.'));
    }

    public function rejectOffer(int $id, OfferService $offers): void
    {
        $this->after($offers->reject($this->ownedOffer($id, 'seller'), Auth::user()), 'info', __('Offer rejected.'));
    }

    public function startCounter(int $id): void
    {
        $offer = $this->ownedOffer($id, 'seller');
        $this->counteringOfferId = $offer->id;
        $this->counterAmount = (string) (int) $offer->amount;
    }

    public function cancelCounter(): void
    {
        $this->counteringOfferId = null;
        $this->counterAmount = '';
    }

    public function submitCounter(OfferService $offers): void
    {
        if (! $this->counteringOfferId) {
            return;
        }
        $this->validate([
            'counterAmount' => ['required', 'numeric', 'min:1'],
        ]);

        $done = $offers->counter($this->ownedOffer($this->counteringOfferId, 'seller'), Auth::user(), (float) $this->counterAmount);
        $this->cancelCounter();
        $this->after($done, 'success', __('Counter-offer sent.'));
    }

    public function render()
    {
        return view('livewire.marketplace.my-offers');
    }
}

