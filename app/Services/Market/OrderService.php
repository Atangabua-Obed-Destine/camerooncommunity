<?php

namespace App\Services\Market;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Payments\PaymentDriver;
use App\Services\Payments\PaymentDriverManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A GoMarket purchase, from Buy now to handover: the web's Checkout and
 * Orders components and the app's API both go through here, so a rule
 * (who may confirm a payment, what completing an order does to the listing)
 * exists once.
 *
 * Every step answers null when it went through, or an error code; message()
 * turns a code into the words the web has always shown.
 *
 * Transitions are conditional updates (`where status = expected`), so a
 * double tap, or both sides acting at once, moves an order exactly once.
 */
class OrderService
{
    /** The statuses an order can still be paid or handed over in. */
    public const OPEN = [OrderStatus::Initiated, OrderStatus::AwaitingPayment, OrderStatus::Paid];

    public function __construct(
        private PaymentDriverManager $drivers,
        private NotificationService $notifier,
    ) {}

    /** The buyer's order for this listing that is still going, if any. */
    public function openOrderFor(MarketplaceListing $listing, User $buyer): ?MarketplaceOrder
    {
        return MarketplaceOrder::where('listing_id', $listing->id)
            ->where('buyer_id', $buyer->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN))
            ->latest('id')
            ->first();
    }

    /**
     * May this member open the checkout? 'own_listing', or 'unavailable' for
     * a listing no longer on sale: unless they already have an order going
     * for it, which they can always come back to (a seller pausing the
     * listing must not strand a buyer who has already paid).
     */
    public function checkoutBlocker(MarketplaceListing $listing, User $buyer): ?string
    {
        if ((int) $listing->user_id === (int) $buyer->id) {
            return 'own_listing';
        }
        if ($listing->status !== ListingStatus::Active && ! $this->openOrderFor($listing, $buyer)) {
            return 'unavailable';
        }

        return null;
    }

    /** @return PaymentDriver[] The ways this seller can be paid. */
    public function driversFor(MarketplaceListing $listing, User $buyer, ?MarketplaceOrder $order = null): array
    {
        // A phantom order, so a driver can look at the seller.
        $probe = $order ?: new MarketplaceOrder([
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
            'buyer_id' => $buyer->id,
            'amount' => $listing->price ?? 0,
            'currency' => $listing->currency ?? 'XAF',
        ]);
        $probe->setRelation('seller', $listing->seller);

        return $this->drivers->availableFor($probe);
    }

    public function instructions(MarketplaceOrder $order, string $locale): ?array
    {
        return $this->drivers->find((string) $order->provider)?->checkoutInstructions($order, $locale);
    }

    public function driverLabel(MarketplaceOrder $order, string $locale): ?string
    {
        return $this->drivers->find((string) $order->provider)?->label($locale);
    }

    /**
     * Buy now: the order in Initiated, ready for the payment instructions.
     * An order already going for this listing is the one returned: tapping
     * Continue twice, or on two phones, must not make two.
     *
     * @return array{order?: MarketplaceOrder, error?: string}
     */
    public function start(MarketplaceListing $listing, User $buyer, string $provider): array
    {
        if ($blocked = $this->checkoutBlocker($listing, $buyer)) {
            return ['error' => $blocked];
        }
        if ($existing = $this->openOrderFor($listing, $buyer)) {
            return ['order' => $existing];
        }

        $price = (float) ($listing->price ?? 0);
        if ($price <= 0 || ! in_array($listing->price_type?->value, ['fixed', 'negotiable'], true)) {
            return ['error' => 'no_price'];
        }

        $listing->loadMissing('seller');
        $driver = collect($this->driversFor($listing, $buyer))->first(fn (PaymentDriver $d) => $d->id() === $provider);
        if (! $driver) {
            // Unknown, or not offered for this seller (no MoMo number): the
            // web used to accept any known driver here and show instructions
            // with no number to send to.
            return ['error' => $this->drivers->find($provider) ? 'no_driver_for_seller' : 'no_driver'];
        }

        $order = MarketplaceOrder::create([
            'tenant_id' => $listing->tenant_id,
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $listing->user_id,
            'status' => OrderStatus::Initiated->value,
            'provider' => $driver->id(),
            'amount' => $price,
            'currency' => $listing->currency ?? 'XAF',
            'quantity' => 1,
        ]);

        return ['order' => $order];
    }

    /**
     * "I have paid" (or "Update reference"): the driver checks the evidence
     * and moves the order to AwaitingPayment; the seller is told.
     *
     * @return array{ok: bool, message?: string, errors?: array<string,string>, error?: string}
     */
    public function submitProof(MarketplaceOrder $order, User $buyer, array $payload): array
    {
        if (! $order->isBuyer((int) $buyer->id)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        if (! in_array($order->status, [OrderStatus::Initiated, OrderStatus::AwaitingPayment], true)) {
            return ['ok' => false, 'error' => 'not_payable'];
        }
        $driver = $this->drivers->find((string) $order->provider);
        if (! $driver) {
            return ['ok' => false, 'error' => 'driver_unavailable'];
        }

        $result = $driver->submitProof($order, [
            'provider_ref' => (string) ($payload['provider_ref'] ?? ''),
            'buyer_note' => (string) ($payload['buyer_note'] ?? ''),
        ]);
        if (! ($result['ok'] ?? false)) {
            return $result + ['ok' => false];
        }

        $order->refresh();
        if ($seller = $order->seller) {
            $this->notifier->send(
                $seller,
                'marketplace.order_payment_claimed',
                ['Payment evidence for :ref', ['ref' => $order->reference]],
                [':buyer says they sent :amount via :provider.', [
                    'buyer' => $buyer->name ?: $buyer->username,
                    'amount' => $order->formattedAmount(),
                    'provider' => $driver->label($seller->language_pref?->value ?? 'en'),
                ]],
                ['order_id' => $order->id, 'url' => route('marketplace.orders')],
            );
            $this->forgetBadges($seller);
        }

        return $result;
    }

    /** The buyer calls it off before the seller has confirmed the money. */
    public function cancel(MarketplaceOrder $order, User $buyer): ?string
    {
        if (! $order->isBuyer((int) $buyer->id)) {
            return 'forbidden';
        }
        if ($order->status === OrderStatus::Paid) {
            return 'already_paid';
        }

        $moved = $this->move($order, [OrderStatus::Initiated, OrderStatus::AwaitingPayment], [
            'status' => OrderStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
        if (! $moved) {
            return 'closed';
        }
        $this->forgetBadges($order->seller);

        return null;
    }

    /** The seller has the money: Paid, and the buyer is told. */
    public function markPaid(MarketplaceOrder $order, User $seller): ?string
    {
        if (! $order->isSeller((int) $seller->id)) {
            return 'forbidden';
        }
        if (! $this->move($order, [OrderStatus::AwaitingPayment], ['status' => OrderStatus::Paid->value, 'paid_at' => now()])) {
            return 'not_awaiting';
        }

        if ($buyer = $order->buyer) {
            $this->notifier->send(
                $buyer,
                'marketplace.order_paid',
                ['Payment confirmed for :ref', ['ref' => $order->reference]],
                ['The seller confirmed receipt of :amount.', ['amount' => $order->formattedAmount()]],
                ['order_id' => $order->id, 'url' => route('marketplace.orders')],
            );
        }
        $this->forgetBadges($seller);

        return null;
    }

    /**
     * Either side: the item has changed hands. The listing goes Sold to this
     * buyer, or, with more than one for sale, loses one unit and stays up
     * (as Mark as sold does); the buyer can review through this order.
     */
    public function release(MarketplaceOrder $order, User $user): ?string
    {
        if (! $order->isBuyer((int) $user->id) && ! $order->isSeller((int) $user->id)) {
            return 'forbidden';
        }

        $moved = DB::transaction(function () use ($order) {
            if (! $this->move($order, [OrderStatus::Paid], ['status' => OrderStatus::Released->value, 'released_at' => now()])) {
                return false;
            }
            $listing = $order->listing()->lockForUpdate()->first();
            if ($listing && (int) $listing->quantity > 1) {
                $listing->forceFill(['quantity' => (int) $listing->quantity - 1])->save();
            } elseif ($listing && $listing->status !== ListingStatus::Sold) {
                $listing->forceFill([
                    'status' => ListingStatus::Sold->value,
                    'buyer_id' => $order->buyer_id,
                    'sold_price' => $order->amount,
                    'sold_currency' => $order->currency,
                    'sold_at' => now(),
                ])->save();
            }

            return true;
        });
        if (! $moved) {
            return 'not_paid';
        }

        $other = $order->isBuyer((int) $user->id) ? $order->seller : $order->buyer;
        if ($other) {
            $this->notifier->send(
                $other,
                'marketplace.order_released',
                ['Order :ref completed', ['ref' => $order->reference]],
                ['The order was marked complete. Leave a review when you can.'],
                ['order_id' => $order->id, 'url' => route('marketplace.show', ['slug' => $order->listing?->slug])],
            );
        }
        $this->forgetBadges($order->seller);

        return null;
    }

    /** Either side: something is wrong. Closed orders cannot be disputed. */
    public function dispute(MarketplaceOrder $order, User $user): ?string
    {
        if (! $order->isBuyer((int) $user->id) && ! $order->isSeller((int) $user->id)) {
            return 'forbidden';
        }
        // isOpen(): the web's Orders page only ever offered the button there,
        // but its action also let a Disputed order be disputed again.
        if (! $this->move($order, self::OPEN, ['status' => OrderStatus::Disputed->value])) {
            return 'closed';
        }

        $other = $order->isBuyer((int) $user->id) ? $order->seller : $order->buyer;
        if ($other) {
            $this->notifier->send(
                $other,
                'marketplace.order_disputed',
                ['Dispute opened on :ref', ['ref' => $order->reference]],
                ['The other party opened a dispute. Please review the order.'],
                ['order_id' => $order->id, 'url' => route('marketplace.orders')],
            );
        }
        $this->forgetBadges($order->seller);

        return null;
    }

    /** The Orders page: one side of the member's orders, newest first. */
    public function query(User $user, string $tab, string $status = ''): Builder
    {
        return MarketplaceOrder::query()
            ->with(['listing.cover', 'listing.media' => fn ($m) => $m->limit(1), 'buyer', 'seller'])
            ->where($tab === 'selling' ? 'seller_id' : 'buyer_id', $user->id)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id');
    }

    /** @return array{buying: int, selling: int, open_selling: int} */
    public function counts(User $user): array
    {
        return [
            'buying' => MarketplaceOrder::where('buyer_id', $user->id)->count(),
            'selling' => MarketplaceOrder::where('seller_id', $user->id)->count(),
            'open_selling' => MarketplaceOrder::needingSellerAttention((int) $user->id),
        ];
    }

    /**
     * What this member may do with this order now: the web's Orders buttons,
     * decided once for the page and the app.
     *
     * @return array{continue_payment: bool, mark_paid: bool, release: bool, dispute: bool, review: bool}
     */
    public function actions(MarketplaceOrder $order, User $user): array
    {
        $buying = $order->isBuyer((int) $user->id);
        $s = $order->status;

        return [
            'continue_payment' => $buying && in_array($s, [OrderStatus::Initiated, OrderStatus::AwaitingPayment], true),
            'mark_paid' => ! $buying && $s === OrderStatus::AwaitingPayment,
            'release' => $s === OrderStatus::Paid,
            'dispute' => $s->isOpen(),
            'review' => $review = $buying && $s === OrderStatus::Released && $order->listing !== null,
            // Already reviewed: the link reads "Edit your review" (the review
            // page updates it in place) instead of asking again.
            'reviewed' => $review && $this->hasReviewed($order, $user),
        ];
    }

    public function hasReviewed(MarketplaceOrder $order, User $user): bool
    {
        return \App\Models\MarketplaceReview::query()
            ->where('listing_id', $order->listing_id)
            ->where('reviewer_id', $user->id)
            ->exists();
    }

    /** The words for an error code, as the web has shown them. */
    public static function message(string $code): string
    {
        return match ($code) {
            'own_listing' => __("You can't buy your own listing."),
            'unavailable' => __('This listing is no longer available.'),
            'no_price' => __('This listing has no fixed price, message the seller instead.'),
            'no_driver' => __('Pick a payment method'),
            'no_driver_for_seller' => __('Seller has not set up Mobile Money yet'),
            'driver_unavailable' => __('Driver unavailable'),
            'not_payable', 'closed' => __('Order is already closed.'),
            'already_paid' => __('Order already paid, ask the seller to refund.'),
            'not_awaiting' => __('Order is not awaiting payment.'),
            'not_paid' => __('Order must be paid before release.'),
            'forbidden' => __('This action is unauthorized.'),
            default => __('Something went wrong.'),
        };
    }

    /**
     * Moves the order on only if it is still in one of [from]; refreshes it.
     *
     * @param  OrderStatus[]  $from
     */
    private function move(MarketplaceOrder $order, array $from, array $changes): bool
    {
        $n = MarketplaceOrder::whereKey($order->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, $from))
            ->update($changes + ['updated_at' => now()]);
        $order->refresh();

        return $n > 0;
    }

    /** The menu's "orders to deal with" badge is cached for two minutes. */
    private function forgetBadges(?User $seller): void
    {
        if ($seller) {
            Cache::forget('mp:orders-pending:'.$seller->id);
        }
    }
}
