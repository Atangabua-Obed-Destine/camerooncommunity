<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\MarketplaceOrder;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
#[Title('My orders')]
class Orders extends Component
{
    /** Which side of the transaction to show: 'buying' | 'selling'. */
    #[Url(except: 'buying')]
    public string $tab = 'buying';

    /** Active status filter. '' = all. */
    #[Url(except: '')]
    public string $status = '';

    #[Computed]
    public function orders()
    {
        $uid = Auth::id();
        $q = MarketplaceOrder::query()
            ->with(['listing.media' => fn ($m) => $m->limit(1), 'buyer', 'seller'])
            ->when($this->tab === 'buying',  fn ($q) => $q->where('buyer_id', $uid))
            ->when($this->tab === 'selling', fn ($q) => $q->where('seller_id', $uid))
            ->when($this->status !== '',     fn ($q) => $q->where('status', $this->status))
            ->latest('id');

        return $q->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        $uid = Auth::id();
        $base = MarketplaceOrder::query();
        return [
            'buying'  => (clone $base)->where('buyer_id',  $uid)->count(),
            'selling' => (clone $base)->where('seller_id', $uid)->count(),
            'open_selling' => (clone $base)
                ->where('seller_id', $uid)
                ->whereIn('status', [OrderStatus::AwaitingPayment->value, OrderStatus::Paid->value])
                ->count(),
        ];
    }

    /** Seller confirms they received the money. */
    public function markPaid(int $orderId, NotificationService $notifier): void
    {
        $o = MarketplaceOrder::with('listing', 'buyer')->findOrFail($orderId);
        if (! $o->isSeller((int) Auth::id())) { abort(403); }
        if ($o->status !== OrderStatus::AwaitingPayment) {
            $this->dispatch('toast', type: 'error', message: __('Order is not awaiting payment.'));
            return;
        }

        $o->forceFill([
            'status'  => OrderStatus::Paid,
            'paid_at' => now(),
        ])->save();

        if ($o->buyer) {
            $notifier->send(
                $o->buyer,
                'marketplace.order_paid',
                __('Payment confirmed for :ref', ['ref' => $o->reference]),
                __('The seller confirmed receipt of :amount.', ['amount' => $o->formattedAmount()]),
                ['order_id' => $o->id, 'url' => route('marketplace.orders')],
            );
        }

        $this->dispatch('toast', type: 'success', message: __('Marked as paid'));
    }

    /** Either side marks the order fully complete (item handed over). */
    public function release(int $orderId, NotificationService $notifier): void
    {
        $o = MarketplaceOrder::with('listing', 'buyer', 'seller')->findOrFail($orderId);
        if (! $o->isBuyer((int) Auth::id()) && ! $o->isSeller((int) Auth::id())) { abort(403); }
        if ($o->status !== OrderStatus::Paid) {
            $this->dispatch('toast', type: 'error', message: __('Order must be paid before release.'));
            return;
        }

        $o->forceFill([
            'status'      => OrderStatus::Released,
            'released_at' => now(),
        ])->save();

        // Auto-mark the listing sold + attribute the buyer when the seller releases.
        $listing = $o->listing;
        if ($listing && ($listing->status?->value ?? $listing->status) !== 'sold') {
            $listing->forceFill([
                'status'         => ListingStatus::Sold->value,
                'buyer_id'       => $o->buyer_id,
                'sold_price'     => $o->amount,
                'sold_currency'  => $o->currency,
                'sold_at'        => now(),
            ])->save();
        }

        // Notify the other party.
        $other = $o->isBuyer((int) Auth::id()) ? $o->seller : $o->buyer;
        if ($other) {
            $notifier->send(
                $other,
                'marketplace.order_released',
                __('Order :ref completed', ['ref' => $o->reference]),
                __('Both sides confirmed — leave a review when you can.'),
                ['order_id' => $o->id, 'url' => route('marketplace.show', ['slug' => $listing?->slug])],
            );
        }

        $this->dispatch('toast', type: 'success', message: __('Marked as completed'));
    }

    public function dispute(int $orderId, NotificationService $notifier): void
    {
        $o = MarketplaceOrder::with('buyer', 'seller')->findOrFail($orderId);
        if (! $o->isBuyer((int) Auth::id()) && ! $o->isSeller((int) Auth::id())) { abort(403); }
        if (in_array($o->status, [OrderStatus::Released, OrderStatus::Cancelled], true)) {
            $this->dispatch('toast', type: 'error', message: __('Order is already closed.'));
            return;
        }

        $o->forceFill(['status' => OrderStatus::Disputed])->save();

        $other = $o->isBuyer((int) Auth::id()) ? $o->seller : $o->buyer;
        if ($other) {
            $notifier->send(
                $other,
                'marketplace.order_disputed',
                __('Dispute opened on :ref', ['ref' => $o->reference]),
                __('The other party opened a dispute. Please review the order.'),
                ['order_id' => $o->id, 'url' => route('marketplace.orders')],
            );
        }

        $this->dispatch('toast', type: 'success', message: __('Dispute opened'));
    }

    public function render()
    {
        return view('livewire.marketplace.orders');
    }
}

