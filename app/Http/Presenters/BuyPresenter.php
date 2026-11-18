<?php

namespace App\Http\Presenters;

use App\Enums\OrderStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSavedSearch;
use App\Models\User;
use App\Services\Market\OrderService;
use App\Support\CameroonGeo;
use App\Support\StorageUrl;

/**
 * GoMarket's buying side as JSON for the app: orders, the checkout, saved
 * searches and the inbox, in the member's language, with absolute URLs. What
 * a member may do with an order is decided by OrderService and sent along,
 * so the app never re-derives a rule.
 */
class BuyPresenter
{
    public function __construct(private OrderService $orders) {}

    /** The status chip: the web's label and colour family. */
    public function status(OrderStatus $s): array
    {
        $fr = app()->getLocale() === 'fr';

        return [
            'value' => $s->value,
            'label' => $fr ? $s->labelFr() : $s->label(),
            'tone' => match ($s) {
                OrderStatus::Initiated, OrderStatus::AwaitingPayment => 'amber',
                OrderStatus::Paid => 'blue',
                OrderStatus::Released => 'emerald',
                OrderStatus::Cancelled => 'slate',
                OrderStatus::Disputed => 'red',
            },
        ];
    }

    /** A listing, as small as an order row or the checkout's header needs. */
    public function listing(?MarketplaceListing $l): ?array
    {
        return $l ? [
            'id' => $l->id,
            'slug' => $l->slug,
            'title' => $l->title,
            'image_url' => $l->coverUrl(),
        ] : null;
    }

    public function person(?User $u): ?array
    {
        return $u ? [
            'id' => $u->id,
            'name' => $u->name ?: $u->username,
            'username' => $u->username,
            'avatar_url' => StorageUrl::for($u->avatar),
        ] : null;
    }

    /** One order as [viewer] sees it (orders.blade.php's card). */
    public function order(MarketplaceOrder $o, User $viewer): array
    {
        $buying = $o->isBuyer((int) $viewer->id);

        return [
            'id' => $o->id,
            'reference' => $o->reference,
            'status' => $this->status($o->status),
            'amount' => $o->formattedAmount(),
            'role' => $buying ? 'buying' : 'selling',
            'listing' => $this->listing($o->listing),
            // The other side: the seller when buying, the buyer when selling.
            'counterpart' => $this->person($buying ? $o->seller : $o->buyer),
            'provider_ref' => $o->provider_ref,
            'buyer_note' => $o->buyer_note,
            'created_at' => $o->created_at?->toIso8601String(),
            'created_ago' => $o->created_at?->diffForHumans(),
            'actions' => $this->orders->actions($o, $viewer),
        ];
    }

    /** checkout.blade.php: the listing, then either the methods or the order. */
    public function checkout(MarketplaceListing $l, User $buyer, ?MarketplaceOrder $order): array
    {
        $lang = app()->getLocale();
        $l->loadMissing('seller');

        return [
            'listing' => $this->listing($l) + [
                // The checkout's own figure: number_format(price, 0) currency.
                'price_label' => number_format((float) $l->price, 0).' '.$l->currency,
                'seller_name' => $l->seller?->name ?: $l->seller?->username,
            ],
            'drivers' => $order ? [] : collect($this->orders->driversFor($l, $buyer))->map(fn ($d) => [
                'id' => $d->id(),
                'label' => $d->label($lang),
                'icon' => $d->icon(),
            ])->values()->all(),
            'order' => $order ? $this->order($order, $buyer) + [
                'buyer_note' => $order->buyer_note,
                'instructions' => $this->orders->instructions($order, $lang),
            ] : null,
        ];
    }

    /** A saved search, with the app's own feed query to reproduce it. */
    public function savedSearch(MarketplaceSavedSearch $s): array
    {
        $f = is_array($s->filters) ? $s->filters : [];
        // Searches saved on the web may carry a free-text region; the app's
        // feed filters by region key.
        $loc = ! empty($f['loc']) ? (string) $f['loc'] : (! empty($f['region']) ? CameroonGeo::matchRegion((string) $f['region']) : '');

        return [
            'id' => $s->id,
            'name' => $s->name,
            'summary' => $s->getAttribute('summary'),
            'new_matches' => (int) $s->getAttribute('new_matches'),
            'notify_push' => (bool) $s->notify_push,
            'notify_email' => (bool) $s->notify_email,
            'created_ago' => $s->created_at?->diffForHumans(),
            'query' => array_filter([
                'q' => $f['query'] ?? null,
                'cat' => $f['categorySlug'] ?? null,
                'loc' => $loc ?: null,
                'radius' => $loc && ! empty($f['radius']) ? (int) $f['radius'] : null,
                'condition' => $f['condition'] ?? null,
                'fulfillment' => $f['fulfillment'] ?? null,
                'price_min' => isset($f['priceMin']) ? (int) $f['priceMin'] : null,
                'price_max' => isset($f['priceMax']) ? (int) $f['priceMax'] : null,
                // 'ranked' and the like are the web's; the app has four.
                'sort' => in_array($f['sort'] ?? null, ['price_asc', 'price_desc', 'popular'], true) ? $f['sort'] : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /** One inbox row (MarketplaceChatService::conversations). */
    public function conversation(object $c): array
    {
        $l = $c->listing;

        return [
            'room_id' => $c->room_id,
            'role' => $c->role,
            'partner' => $this->person($c->partner),
            'listing' => $l ? $this->listing($l) + ['price_label' => $l->formattedPrice(app()->getLocale())] : null,
            'preview' => $c->preview,
            'time' => $c->time ? \Illuminate\Support\Carbon::parse($c->time)->toIso8601String() : null,
            'time_ago' => $c->time ? \Illuminate\Support\Carbon::parse($c->time)->diffForHumans(null, true) : null,
            'unread' => (int) $c->unread,
        ];
    }
}
