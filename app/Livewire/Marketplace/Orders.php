<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceOrder;
use App\Services\Market\OrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
#[Title('My orders')]
class Orders extends Component
{
    // Keeps the page number right after an action; see MyListings.
    use WithPagination;

    /** Which side of the transaction to show: 'buying' | 'selling'. */
    #[Url(except: 'buying')]
    public string $tab = 'buying';

    /** Active status filter. '' = all. */
    #[Url(except: '')]
    public string $status = '';

    /** A new tab or filter starts from its first page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'status'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function orders()
    {
        return $this->service()->query(Auth::user(), $this->tab, $this->status)->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        return $this->service()->counts(Auth::user());
    }

    private function service(): OrderService
    {
        return app(OrderService::class);
    }

    /** Seller confirms they received the money. */
    public function markPaid(int $orderId): void
    {
        $this->act($orderId, fn ($o) => $this->service()->markPaid($o, Auth::user()), __('Marked as paid'));
    }

    /** Either side marks the order fully complete (item handed over). */
    public function release(int $orderId): void
    {
        $this->act($orderId, fn ($o) => $this->service()->release($o, Auth::user()), __('Marked as completed'));
    }

    public function dispute(int $orderId): void
    {
        $this->act($orderId, fn ($o) => $this->service()->dispute($o, Auth::user()), __('Dispute opened'));
    }

    /** One of the buttons: forbidden is a 403, any other refusal a toast. */
    private function act(int $orderId, \Closure $step, string $done): void
    {
        $o = MarketplaceOrder::with('listing', 'buyer', 'seller')->findOrFail($orderId);
        $error = $step($o);
        if ($error === 'forbidden') {
            abort(403);
        }
        unset($this->orders, $this->counts);
        $this->dispatch('toast', type: $error ? 'error' : 'success', message: $error ? OrderService::message($error) : $done);
    }

    public function render()
    {
        return view('livewire.marketplace.orders');
    }
}

