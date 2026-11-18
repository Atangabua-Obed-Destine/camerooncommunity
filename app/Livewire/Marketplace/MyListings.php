<?php

namespace App\Livewire\Marketplace;

use App\Services\Market\MyListingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
class MyListings extends Component
{
    // Without it, paginator links rebuilt after an action point at Livewire's
    // AJAX URL and the page resets to 1.
    use WithPagination;

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
        $this->resetPage();
    }

    #[Computed]
    public function listings()
    {
        // Deleted listings stay out: their Edit and Stats links are 404s.
        return $this->mine()->query(Auth::user(), $this->filter)->paginate(20);
    }

    private function mine(): MyListingsService
    {
        return app(MyListingsService::class);
    }

    public function pause(int $id): void
    {
        $this->mine()->pause(Auth::user(), $id);
    }

    public function reactivate(int $id): void
    {
        $this->mine()->reactivate(Auth::user(), $id);
    }

    /** Bump a listing to the top of the feed (cooldown 24h). */
    public function bump(int $id): void
    {
        $r = $this->mine()->bump(Auth::user(), $id);
        if (isset($r['wait_minutes'])) {
            $this->dispatch('toast', type: 'error', message: __('You can bump again in :min min', ['min' => $r['wait_minutes']]));
        } elseif ($r['ok']) {
            $this->dispatch('toast', type: 'success', message: __('Listing bumped to top'));
        }
    }

    /** Open the Mark-as-Sold modal with smart defaults. */
    public function openMarkSold(int $id): void
    {
        $l = $this->mine()->find(Auth::user(), $id);
        if (! $l) { return; }
        $d = $this->mine()->saleDefaults($l);
        $this->sellListingId = $id;
        $this->sellBuyerSearch = '';
        $this->sellBuyerId = $d['buyer_id'];
        $this->sellPrice = $d['price'];
        $this->sellCurrency = $d['currency'];
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
        $l = $this->sellListingId ? $this->mine()->find(Auth::user(), $this->sellListingId) : null;

        return $l ? $this->mine()->buyerCandidates(Auth::user(), $l, $this->sellBuyerSearch) : collect();
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
    public function saveSold(): void
    {
        $result = $this->mine()->markSold(Auth::user(), (int) $this->sellListingId, $this->sellBuyerId, $this->sellPrice, $this->sellCurrency);
        $this->cancelMarkSold();

        match ($result) {
            'already' => $this->dispatch('toast', type: 'error', message: __('Listing is already marked as sold to this buyer.')),
            'partial' => $this->dispatch('toast', type: 'success', message: __('1 item sold. Quantity updated.')),
            'sold' => $this->dispatch('toast', type: 'success', message: __('Listing marked as sold')),
            default => null,
        };
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
        $n = $this->mine()->bulkPause(Auth::user(), $this->selected);
        $this->selected = [];
        $this->bulkToast($n, __('Selected listings paused'));
    }

    public function bulkReactivate(): void
    {
        if (empty($this->selected)) return;
        $n = $this->mine()->bulkReactivate(Auth::user(), $this->selected);
        $this->selected = [];
        $this->bulkToast($n, __('Selected listings reactivated'));
    }

    private function bulkToast(int $changed, string $done): void
    {
        $changed > 0
            ? $this->dispatch('toast', type: 'success', message: $done)
            : $this->dispatch('toast', type: 'error', message: __('None of the selected listings can do that.'));
    }

    public function bulkRemove(): void
    {
        if (empty($this->selected)) return;
        $this->mine()->bulkRemove(Auth::user(), $this->selected);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: __('Selected listings removed'));
    }

    public function remove(int $id): void
    {
        $this->mine()->remove(Auth::user(), $id);
    }

    public function render()
    {
        return view('livewire.marketplace.my-listings');
    }
}

