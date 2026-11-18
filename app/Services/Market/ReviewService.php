<?php

namespace App\Services\Market;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceReview;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Str;

/**
 * Reviewing a purchase: who may, and writing it. The web's LeaveReview page,
 * the listing's "Rate your purchase" card and the app all ask here, so they
 * agree (the listing's card used to ignore buyers of a multi-unit listing,
 * whom the review page itself let in).
 */
class ReviewService
{
    public function __construct(private NotificationService $notifier) {}

    /**
     * Sold to this member, or bought by them through an order that completed
     * (the listing may stay on sale when more than one was for sale). Never
     * your own listing.
     */
    public function canReview(MarketplaceListing $listing, ?User $user): bool
    {
        if (! $user || (int) $user->id === (int) $listing->user_id) {
            return false;
        }
        if ($listing->status === ListingStatus::Sold && (int) $listing->buyer_id === (int) $user->id) {
            return true;
        }

        return MarketplaceOrder::where('listing_id', $listing->id)
            ->where('buyer_id', $user->id)
            ->where('status', OrderStatus::Released)
            ->exists();
    }

    public function mine(MarketplaceListing $listing, ?User $user): ?MarketplaceReview
    {
        return $user ? MarketplaceReview::where('listing_id', $listing->id)->where('reviewer_id', $user->id)->first() : null;
    }

    /**
     * Writes (or rewrites) the member's review; the seller hears about a new
     * one. Validate rating 1-5 and comment ≤1000 before calling.
     *
     * @return array{review: MarketplaceReview, created: bool}
     */
    public function save(MarketplaceListing $listing, User $user, int $rating, ?string $comment): array
    {
        $comment = trim((string) $comment);
        $review = MarketplaceReview::updateOrCreate(
            ['listing_id' => $listing->id, 'reviewer_id' => $user->id],
            [
                'tenant_id' => $listing->tenant_id,
                'seller_id' => $listing->user_id,
                'rating' => max(1, min(5, $rating)),
                'comment' => $comment !== '' ? $comment : null,
                'is_buyer_verified' => true,
            ],
        );

        if ($review->wasRecentlyCreated && ($seller = $listing->seller)) {
            $this->notifier->send(
                $seller,
                'marketplace.review_received',
                [':name left you a :stars-star review', ['name' => $user->name ?: $user->username, 'stars' => $review->rating]],
                Str::limit($comment, 140) ?: ['No comment'],
                [
                    'listing_id' => $listing->id,
                    'review_id' => $review->id,
                    'url' => route('marketplace.show', ['slug' => $listing->slug]),
                ],
            );
        }

        return ['review' => $review, 'created' => $review->wasRecentlyCreated];
    }
}
