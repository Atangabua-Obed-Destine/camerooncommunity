<?php

namespace App\Services\Market;

use App\Enums\OfferStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a seller does with their own listings (the web's MyListings): pause,
 * reactivate, bump, mark sold, remove, and the same in bulk. Shared by the
 * Livewire page and the app's API. Every method only ever touches listings
 * the member owns.
 */
class MyListingsService
{
    public const FILTERS = ['all', 'active', 'pending_review', 'paused', 'sold', 'expired', 'draft'];

    public const BUMP_COOLDOWN_HOURS = 24;

    public function __construct(private NotificationService $notifier) {}

    /** The member's listings under a status pill, newest change first. Deleted ones stay out. */
    public function query(User $user, string $filter = 'all'): Builder
    {
        $q = MarketplaceListing::where('user_id', $user->id)
            ->with(['category', 'cover', 'media' => fn ($m) => $m->limit(1)])
            ->orderByDesc('updated_at');

        if ($filter !== 'all' && in_array($filter, self::FILTERS, true)) {
            $q->where('status', $filter);
        }

        return $q;
    }

    public function find(User $user, int $id): ?MarketplaceListing
    {
        return MarketplaceListing::where('user_id', $user->id)->find($id);
    }

    public function pause(User $user, int $id): bool
    {
        return (bool) MarketplaceListing::where('user_id', $user->id)->where('status', 'active')->whereKey($id)
            ->update(['status' => 'paused']);
    }

    public function reactivate(User $user, int $id): bool
    {
        $l = MarketplaceListing::where('user_id', $user->id)->where('status', 'paused')->find($id);
        if (! $l) {
            return false;
        }
        $l->update(['status' => 'active', 'published_at' => $l->published_at ?? now()]);

        return true;
    }

    /** Minutes until this listing may be bumped again; 0 when it may now. */
    public function bumpWaitMinutes(MarketplaceListing $l): int
    {
        if (! $l->bumped_at || $l->bumped_at->lte(now()->subHours(self::BUMP_COOLDOWN_HOURS))) {
            return 0;
        }

        return (int) ceil($l->bumped_at->copy()->addHours(self::BUMP_COOLDOWN_HOURS)->diffInMinutes(now(), absolute: true));
    }

    /**
     * Push a live listing to the top of the feed, once a day.
     *
     * @return array{ok: bool, wait_minutes?: int}
     */
    public function bump(User $user, int $id): array
    {
        $l = $this->find($user, $id);
        if (! $l) {
            return ['ok' => false];
        }
        if ($wait = $this->bumpWaitMinutes($l)) {
            return ['ok' => false, 'wait_minutes' => $wait];
        }

        $l->forceFill([
            'bumped_at' => now(),
            'bump_count' => (int) $l->bump_count + 1,
            'renewed_at' => now(),
        ])->save();

        return ['ok' => true];
    }

    /**
     * The sale's starting values: the listing's price, or the most recent
     * accepted offer's buyer and amount.
     *
     * @return array{buyer_id: ?int, price: string, currency: string}
     */
    public function saleDefaults(MarketplaceListing $l): array
    {
        $out = ['buyer_id' => null, 'price' => (string) ($l->price ?: ''), 'currency' => $l->currency ?: 'XAF'];

        $accepted = MarketplaceOffer::where('listing_id', $l->id)
            ->where('status', OfferStatus::Accepted)
            ->orderByDesc('responded_at')
            ->first();
        if ($accepted) {
            $out['buyer_id'] = $accepted->buyer_id;
            $out['price'] = (string) ($accepted->amount ?: $out['price']);
            $out['currency'] = $accepted->currency ?: $out['currency'];
        }

        return $out;
    }

    /**
     * Who the buyer might be: members matching a search, or without one the
     * people who made offers on this listing.
     */
    public function buyerCandidates(User $seller, MarketplaceListing $l, string $search = ''): Collection
    {
        $search = trim($search);
        if ($search !== '') {
            $like = '%'.ltrim($search, '@').'%';

            return User::where('id', '!=', $seller->id)
                ->discoverable()
                ->whereNotIn('id', $seller->blockedOrBlockingUserIds())
                ->where(fn ($w) => $w->where('username', 'like', $like)->orWhere('name', 'like', $like))
                ->limit(8)
                ->get(['id', 'name', 'username', 'avatar']);
        }

        $ids = MarketplaceOffer::where('listing_id', $l->id)
            ->whereIn('status', [OfferStatus::Accepted, OfferStatus::Countered, OfferStatus::Pending])
            ->orderByDesc('responded_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->pluck('buyer_id')
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : User::whereIn('id', $ids)->where('id', '!=', $seller->id)->limit(8)->get(['id', 'name', 'username', 'avatar']);
    }

    /**
     * Record a sale. One of several in stock takes one off the quantity and
     * keeps the listing up; the last one marks it sold, to the buyer if given,
     * who is then asked for a review.
     *
     * @return 'sold'|'partial'|'already'|'missing'
     */
    public function markSold(User $seller, int $id, ?int $buyerId, ?string $price, ?string $currency): string
    {
        $l = $this->find($seller, $id);
        if (! $l) {
            return 'missing';
        }
        if ($buyerId === $seller->id) {
            $buyerId = null;
        }

        if ($l->status?->value === 'sold' && $l->buyer_id === $buyerId) {
            return 'already';
        }

        $amount = $price === null || trim($price) === '' ? null : (float) $price;
        $currency = trim((string) $currency) ?: ($l->currency ?: 'XAF');

        if ($l->quantity > 1) {
            $l->update(['quantity' => $l->quantity - 1]);

            // A review needs the listing sold to this buyer (LeaveReview::canReview).
            // A partial sale leaves it on sale, so the link would be a 403: don't send it.
            return 'partial';
        }

        $l->update([
            'status' => 'sold',
            'sold_at' => now(),
            'sold_price' => $amount,
            'sold_currency' => $currency,
            'buyer_id' => $buyerId,
            'quantity' => 0,
        ]);

        if ($buyerId && ($buyer = User::find($buyerId))) {
            $sellerName = $seller->name ?: $seller->username;
            $this->notifier->send(
                $buyer,
                'marketplace.purchase_confirmed',
                ['Leave a review for :seller', ['seller' => $sellerName]],
                ['Rate your experience buying ":title"', ['title' => $l->title]],
                [
                    'listing_id' => $l->id,
                    'url' => route('marketplace.review', ['slug' => $l->slug]),
                ],
            );
        }

        return 'sold';
    }

    public function remove(User $user, int $id): bool
    {
        $l = $this->find($user, $id);
        if (! $l) {
            return false;
        }
        $l->update(['status' => 'removed']);
        $l->delete();

        return true;
    }

    /** @param array<int|string> $ids */
    public function bulkPause(User $user, array $ids): int
    {
        // Only live listings pause, as with the single button.
        return MarketplaceListing::where('user_id', $user->id)->whereIn('id', $ids)->where('status', 'active')
            ->update(['status' => 'paused']);
    }

    /** @param array<int|string> $ids */
    public function bulkReactivate(User $user, array $ids): int
    {
        // Only paused listings come back. Sold and expired ones stay put, and a draft
        // or one pending review must go through the composer and review first.
        return MarketplaceListing::where('user_id', $user->id)->whereIn('id', $ids)->where('status', 'paused')
            ->update([
                'status' => 'active',
                'published_at' => DB::raw("COALESCE(published_at, '".now()->toDateTimeString()."')"),
            ]);
    }

    /** @param array<int|string> $ids */
    public function bulkRemove(User $user, array $ids): int
    {
        $n = 0;
        foreach (MarketplaceListing::where('user_id', $user->id)->whereIn('id', $ids)->get() as $l) {
            $l->update(['status' => 'removed']);
            $l->delete();
            $n++;
        }

        return $n;
    }
}
