<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Services\Market\OrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
#[Title('Checkout')]
class Checkout extends Component
{
    public MarketplaceListing $listing;
    public ?MarketplaceOrder $order = null;

    /** Step 1 selection. */
    public string $provider = 'manual';

    /** Step 2 evidence form. */
    public string $provider_ref = '';
    public string $buyer_note = '';

    public function mount(string $slug): void
    {
        $this->listing = MarketplaceListing::where('slug', $slug)
            ->with('seller')
            ->firstOrFail();

        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }

        // Your own listing, or one no longer on sale (unless you already have
        // an order going for it: OrderService::checkoutBlocker).
        match ($this->orders()->checkoutBlocker($this->listing, Auth::user())) {
            'own_listing' => abort(403, "You can't buy your own listing."),
            'unavailable' => abort(404),
            default => null,
        };

        // Reuse an existing open order if there is one.
        if ($existing = $this->orders()->openOrderFor($this->listing, Auth::user())) {
            $this->order = $existing;
            $this->provider = $existing->provider;
            $this->provider_ref = (string) ($existing->provider_ref ?? '');
            $this->buyer_note   = (string) ($existing->buyer_note ?? '');
        }
    }

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    #[Computed]
    public function drivers(): array
    {
        return $this->orders()->driversFor($this->listing, Auth::user(), $this->order);
    }

    #[Computed]
    public function instructions(): ?array
    {
        return $this->order ? $this->orders()->instructions($this->order, app()->getLocale()) : null;
    }

    /** Create the order in Initiated state and show the instructions. */
    public function startCheckout(): void
    {
        if (! Auth::check()) { return; }

        $result = $this->orders()->start($this->listing, Auth::user(), $this->provider);
        if (isset($result['error'])) {
            $this->dispatch('toast', type: 'error', message: OrderService::message($result['error']));
            return;
        }
        $this->order = $result['order'];
        unset($this->instructions);
    }

    /** Buyer submits MoMo transaction reference. */
    public function submitProof(): void
    {
        if (! $this->order) { return; }

        $result = $this->orders()->submitProof($this->order, Auth::user(), [
            'provider_ref' => $this->provider_ref,
            'buyer_note'   => $this->buyer_note,
        ]);

        if (! ($result['ok'] ?? false)) {
            foreach (($result['errors'] ?? []) as $field => $msg) {
                $this->addError($field, __($msg));
            }
            $message = isset($result['error']) ? OrderService::message($result['error']) : __($result['message'] ?? 'Could not submit');
            $this->dispatch('toast', type: 'error', message: $message);
            return;
        }

        $this->order->refresh();
        $this->dispatch('toast', type: 'success', message: __($result['message'] ?? 'Submitted'));
    }

    /** Buyer cancels before paying. */
    public function cancelOrder(): void
    {
        if (! $this->order) { return; }

        if ($error = $this->orders()->cancel($this->order, Auth::user())) {
            if ($error !== 'forbidden') {
                $this->dispatch('toast', type: 'error', message: OrderService::message($error));
            }
            return;
        }

        $this->dispatch('toast', type: 'success', message: __('Order cancelled'));
        $this->redirectRoute('marketplace.orders', navigate: true);
    }

    public function render()
    {
        return view('livewire.marketplace.checkout');
    }
}

