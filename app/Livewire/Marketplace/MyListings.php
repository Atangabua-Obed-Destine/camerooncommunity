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

#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
class MyListings extends Component
{
    public string $filter = 'all'; // all|active|sold|expired|draft|paused

    // ─── Mark-as-Sold modal state ───
    public ?int $sellListingId = null;
    public ?int $sellBuyerId = null;
    public string $sellBuyerSearch = '';
    public string $sellPrice = '';
    public string $sellCurrency = 'XAF';

    // ─── MoMo (Mobile Money) seller settings ───
    public bool $showMomoModal = false;
    public string $momoProvider = 'mtn';
    public string $momoNumber = '';

    public function openMomoSettings(): void
    {
        $u = Auth::user();
        $this->momoProvider = $u?->momo_provider ?: 'mtn';
        $this->momoNumber   = $u?->momo_number ?: '';
        $this->showMomoModal = true;
    }

    public function closeMomoSettings(): void
    {
        $this->showMomoModal = false;
    }

    public function saveMomo(): void
    {
        $data = $this->validate([
            'momoProvider' => 'required|in:mtn,orange,express',
            'momoNumber'   => ['required', 'string', 'regex:/^[0-9+\s\-]{8,20}$/'],
        ]);

        $u = Auth::user();
        $u->forceFill([
            'momo_provider' => $data['momoProvider'],
            'momo_number'   => preg_replace('/\s+/', '', $data['momoNumber']),
        ])->save();

        \App\Support\TrustBadges::forget($u->id);

        $this->showMomoModal = false;
        $this->dispatch('toast', type: 'success', message: __('Mobile Money saved'));
    }

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

        $price = trim($this->sellPrice) === '' ? null : (float) $this->sellPrice;
        $currency = trim($this->sellCurrency) ?: ($l->currency ?: 'XAF');

        $l->update([
            'status'        => 'sold',
            'sold_at'       => now(),
            'sold_price'    => $price,
            'sold_currency' => $currency,
            'buyer_id'      => $this->sellBuyerId,
        ]);

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
        $this->dispatch('toast', type: 'success', message: __('Listing marked as sold'));
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
