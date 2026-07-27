<?php

namespace App\Livewire\Marketplace;

use App\Enums\OfferStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
class MyListings extends Component
{
    public string $filter = 'all'; // all|active|sold|expired|draft|paused

    // ─── Mark-as-Sold modal state ───
    public ?int $sellListingId = null;
    public ?int $sellBuyerId = null;
    public string $sellBuyerSearch = '';
    public string $sellPrice = '';
    public string $sellCurrency = 'XAF';

    // ─── Bulk Actions state ───
    public array $selected = [];



    public function setFilter(string $f): void
    {
        $this->filter = $f;
    }

    #[Computed]
    public function listings()
    {
        $q = MarketplaceListing::withTrashed()
            ->where('user_id', Auth::id())
            ->with(['category', 'media' => fn ($m) => $m->limit(1)])
            ->orderByDesc('updated_at');

        if ($this->filter !== 'all') {
            $q->where('status', $this->filter);
        }
        return $q->paginate(20);
    }

    public function pause(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'paused']); }
    }
    public function reactivate(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'active', 'published_at' => $l->published_at ?? now()]); }
    }

    /** Bump a listing to the top of the feed (cooldown 24h). */
    public function bump(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if (! $l) { return; }

        if ($l->bumped_at && $l->bumped_at->gt(now()->subHours(24))) {
            $remaining = (int) ceil($l->bumped_at->copy()->addHours(24)->diffInMinutes(now(), absolute: true));
            $this->dispatch('toast', type: 'error', message: __('You can bump again in :min min', ['min' => $remaining]));
            return;
        }

        $l->forceFill([
            'bumped_at'  => now(),
            'bump_count' => (int) $l->bump_count + 1,
            'renewed_at' => now(),
        ])->save();

        $this->dispatch('toast', type: 'success', message: __('Listing bumped to top'));
    }

    /** Open the Mark-as-Sold modal with smart defaults. */
    public function openMarkSold(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if (! $l) { return; }
        $this->sellListingId = $id;
        $this->sellPrice = (string) ($l->price ?: '');
        $this->sellCurrency = $l->currency ?: 'XAF';
        $this->sellBuyerSearch = '';
        $this->sellBuyerId = null;

        // Pre-pick the most recent accepted-offer buyer (if any).
        $accepted = MarketplaceOffer::where('listing_id', $id)
            ->where('status', OfferStatus::Accepted)
            ->orderByDesc('responded_at')
            ->first();
        if ($accepted) {
            $this->sellBuyerId = $accepted->buyer_id;
            $this->sellPrice = (string) ($accepted->amount ?: $this->sellPrice);
            $this->sellCurrency = $accepted->currency ?: $this->sellCurrency;
        }
    }

    public function cancelMarkSold(): void
    {
        $this->reset(['sellListingId', 'sellBuyerId', 'sellBuyerSearch', 'sellPrice', 'sellCurrency']);
        $this->sellCurrency = 'XAF';
    }

    /**
     * Buyer suggestions for the modal:
     *  - With a search term: matches username/name.
     *  - Without: prior offer buyers on this listing.
     */
    #[Computed]
    public function buyerCandidates()
    {
        if (! $this->sellListingId) { return collect(); }

        $search = trim($this->sellBuyerSearch);

        if ($search !== '') {
            $like = '%' . $search . '%';
            return User::where('id', '!=', Auth::id())
                ->where(function ($w) use ($like) {
                    $w->where('username', 'like', $like)->orWhere('name', 'like', $like);
                })
                ->limit(8)
                ->get(['id', 'name', 'username', 'avatar']);
        }

        $offerBuyerIds = MarketplaceOffer::where('listing_id', $this->sellListingId)
            ->whereIn('status', [OfferStatus::Accepted, OfferStatus::Countered, OfferStatus::Pending])
            ->orderByDesc('responded_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->pluck('buyer_id')
            ->unique()
            ->values();

        if ($offerBuyerIds->isEmpty()) { return collect(); }

        return User::whereIn('id', $offerBuyerIds)
            ->where('id', '!=', Auth::id())
            ->limit(8)
            ->get(['id', 'name', 'username', 'avatar']);
    }

    public function pickBuyer(int $userId): void
    {
        $this->sellBuyerId = $userId;
        $this->sellBuyerSearch = '';
    }

    public function clearBuyer(): void
    {
        $this->sellBuyerId = null;
    }

    /** Commit the sale, attribute buyer, notify them to leave a review. */
    public function saveSold(NotificationService $notifier): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($this->sellListingId);
        if (! $l) { $this->cancelMarkSold(); return; }

        // Spam prevention: if already sold to this buyer and quantity <= 0, do nothing
        if ($l->status?->value === 'sold' && $l->buyer_id === $this->sellBuyerId) {
            $this->cancelMarkSold();
            $this->dispatch('toast', type: 'error', message: __('Listing is already marked as sold to this buyer.'));
            return;
        }

        $price = trim($this->sellPrice) === '' ? null : (float) $this->sellPrice;
        $currency = trim($this->sellCurrency) ?: ($l->currency ?: 'XAF');

        $isPartialSale = false;
        if ($l->quantity > 1) {
            $l->update(['quantity' => $l->quantity - 1]);
            $isPartialSale = true;
        } else {
            $l->update([
                'status'        => 'sold',
                'sold_at'       => now(),
                'sold_price'    => $price,
                'sold_currency' => $currency,
                'buyer_id'      => $this->sellBuyerId,
                'quantity'      => 0,
            ]);
        }

        if ($this->sellBuyerId) {
            $buyer = User::find($this->sellBuyerId);
            if ($buyer) {
                $sellerName = Auth::user()->name ?: Auth::user()->username;
                $notifier->send(
                    $buyer,
                    'marketplace.purchase_confirmed',
                    __('Leave a review for :seller', ['seller' => $sellerName]),
                    __('Rate your experience buying ":title"', ['title' => $l->title]),
                    [
                        'listing_id' => $l->id,
                        'url'        => route('marketplace.review', ['slug' => $l->slug]),
                    ],
                );
            }
        }

        $this->cancelMarkSold();
        if ($isPartialSale) {
            $this->dispatch('toast', type: 'success', message: __('1 item sold. Quantity updated.'));
        } else {
            $this->dispatch('toast', type: 'success', message: __('Listing marked as sold'));
        }
    }

    // ─── Bulk Actions ───

    public function toggleSelectAll(): void
    {
        $visibleIds = $this->listings->pluck('id')->map(fn($id) => (string)$id)->toArray();
        $allSelected = count(array_intersect($visibleIds, $this->selected)) === count($visibleIds);

        if ($allSelected) {
            $this->selected = array_diff($this->selected, $visibleIds);
        } else {
            $this->selected = array_unique(array_merge($this->selected, $visibleIds));
        }
    }

    public function bulkPause(): void
    {
        if (empty($this->selected)) return;
        MarketplaceListing::where('user_id', Auth::id())
            ->whereIn('id', $this->selected)
            ->update(['status' => 'paused']);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: __('Selected listings paused'));
    }

    public function bulkReactivate(): void
    {
        if (empty($this->selected)) return;
        MarketplaceListing::where('user_id', Auth::id())
            ->whereIn('id', $this->selected)
            ->update([
                'status' => 'active',
                'published_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(published_at, NOW())')
            ]);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: __('Selected listings reactivated'));
    }

    public function bulkRemove(): void
    {
        if (empty($this->selected)) return;
        $listings = MarketplaceListing::where('user_id', Auth::id())->whereIn('id', $this->selected)->get();
        foreach ($listings as $l) {
            $l->update(['status' => 'removed']);
            $l->delete();
        }
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: __('Selected listings removed'));
    }

    public function remove(int $id): void
    {
        $l = MarketplaceListing::where('user_id', Auth::id())->find($id);
        if ($l) { $l->update(['status' => 'removed']); $l->delete(); }
    }

    public function render()
    {
        return view('livewire.marketplace.my-listings');
    }
}

