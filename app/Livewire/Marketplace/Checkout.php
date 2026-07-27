<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Services\NotificationService;
use App\Services\Payments\PaymentDriverManager;
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

    public function mount(string $slug, ?PaymentDriverManager $manager = null): void
    {
        $manager ??= app(PaymentDriverManager::class);

        $this->listing = MarketplaceListing::where('slug', $slug)
            ->with('seller')
            ->firstOrFail();

        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }

        if (Auth::id() === (int) $this->listing->user_id) {
            abort(403, "You can't buy your own listing.");
        }

        // Disallow checkout on sold / inactive listings
        $isActive = ($this->listing->status?->value ?? $this->listing->status) === 'active';
        if (! $isActive) {
            abort(404);
        }

        // Reuse an existing open order if there is one.
        $existing = MarketplaceOrder::where('listing_id', $this->listing->id)
            ->where('buyer_id', Auth::id())
            ->whereIn('status', [OrderStatus::Initiated->value, OrderStatus::AwaitingPayment->value, OrderStatus::Paid->value])
            ->latest('id')
            ->first();

        if ($existing) {
            $this->order = $existing;
            $this->provider = $existing->provider;
            $this->provider_ref = (string) ($existing->provider_ref ?? '');
            $this->buyer_note   = (string) ($existing->buyer_note ?? '');
        }
    }

    #[Computed]
    public function drivers(): array
    {
        // Build a phantom order so isAvailableFor can inspect the seller.
        $probe = $this->order ?: new MarketplaceOrder([
            'listing_id' => $this->listing->id,
            'seller_id'  => $this->listing->user_id,
            'buyer_id'   => Auth::id() ?? 0,
            'amount'     => $this->listing->price ?? 0,
            'currency'   => $this->listing->currency ?? 'XAF',
        ]);
        $probe->setRelation('seller', $this->listing->seller);

        return app(PaymentDriverManager::class)->availableFor($probe);
    }

    #[Computed]
    public function instructions(): ?array
    {
        if (! $this->order) { return null; }
        $driver = app(PaymentDriverManager::class)->find($this->order->provider);
        return $driver?->checkoutInstructions($this->order, app()->getLocale());
    }

    /** Create the order in Initiated state and show the instructions. */
    public function startCheckout(NotificationService $notifier): void
    {
        if (! Auth::check()) { return; }
        $driver = app(PaymentDriverManager::class)->find($this->provider);
        if (! $driver) {
            $this->dispatch('toast', type: 'error', message: __('Pick a payment method'));
            return;
        }

        $price = (float) ($this->listing->price ?? 0);
        if ($price <= 0) {
            $this->dispatch('toast', type: 'error', message: __('This listing has no fixed price — message the seller instead.'));
            return;
        }

        $this->order = MarketplaceOrder::create([
            'tenant_id'  => $this->listing->tenant_id,
            'listing_id' => $this->listing->id,
            'buyer_id'   => Auth::id(),
            'seller_id'  => $this->listing->user_id,
            'status'     => OrderStatus::Initiated->value,
            'provider'   => $driver->id(),
            'amount'     => $price,
            'currency'   => $this->listing->currency ?? 'XAF',
            'quantity'   => 1,
        ]);
    }

    /** Buyer submits MoMo transaction reference. */
    public function submitProof(NotificationService $notifier): void
    {
        if (! $this->order) { return; }

        $driver = app(PaymentDriverManager::class)->find($this->order->provider);
        if (! $driver) {
            $this->dispatch('toast', type: 'error', message: __('Driver unavailable'));
            return;
        }

        $result = $driver->submitProof($this->order, [
            'provider_ref' => $this->provider_ref,
            'buyer_note'   => $this->buyer_note,
        ]);

        if (! ($result['ok'] ?? false)) {
            foreach (($result['errors'] ?? []) as $field => $msg) {
                $this->addError($field, $msg);
            }
            $this->dispatch('toast', type: 'error', message: __($result['message'] ?? 'Could not submit'));
            return;
        }

        $this->order->refresh();

        // Notify the seller that money should be incoming.
        if ($this->order->seller) {
            $notifier->send(
                $this->order->seller,
                'marketplace.order_payment_claimed',
                __('Payment evidence for :ref', ['ref' => $this->order->reference]),
                __(':buyer says they sent :amount via :provider.', [
                    'buyer'    => Auth::user()->name ?: Auth::user()->username,
                    'amount'   => $this->order->formattedAmount(),
                    'provider' => $driver->label(app()->getLocale()),
                ]),
                [
                    'order_id' => $this->order->id,
                    'url'      => route('marketplace.orders'),
                ],
            );
        }

        $this->dispatch('toast', type: 'success', message: __($result['message'] ?? 'Submitted'));
    }

    /** Buyer cancels before paying. */
    public function cancelOrder(NotificationService $notifier): void
    {
        if (! $this->order) { return; }
        if (! $this->order->isBuyer((int) Auth::id())) { return; }
        if ($this->order->status === OrderStatus::Paid) {
            $this->dispatch('toast', type: 'error', message: __('Order already paid — ask the seller to refund.'));
            return;
        }

        $this->order->forceFill([
            'status'       => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        $this->dispatch('toast', type: 'success', message: __('Order cancelled'));
        $this->redirectRoute('marketplace.orders', navigate: true);
    }

    public function render()
    {
        return view('livewire.marketplace.checkout');
    }
}

