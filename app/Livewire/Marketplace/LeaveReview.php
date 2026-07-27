<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceReview;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
#[Title('Leave a review')]
class LeaveReview extends Component
{
    public MarketplaceListing $listing;

    public int $rating = 0;
    public string $comment = '';

    /** Existing review by the current user for this listing (edit-in-place). */
    public ?int $existingId = null;

    public function mount(string $slug): void
    {
        $this->listing = MarketplaceListing::where('slug', $slug)
            ->with('seller')
            ->firstOrFail();

        abort_unless($this->canReview(), 403);

        $existing = MarketplaceReview::where('listing_id', $this->listing->id)
            ->where('reviewer_id', Auth::id())
            ->first();
        if ($existing) {
            $this->existingId = $existing->id;
            $this->rating = $existing->rating;
            $this->comment = (string) $existing->comment;
        }
    }

    /**
     * Who may write a review for this listing?
     *   - Sold listings: only the attributed buyer.
     *   - Anyone else: not allowed (no "anonymous" reviews — Phase 7 may add buyer-verified-via-DM).
     */
    public function canReview(): bool
    {
        if (! Auth::check()) { return false; }
        if (Auth::id() === (int) $this->listing->user_id) { return false; } // can't review own listing

        $isSold = $this->listing->status === ListingStatus::Sold
            || (is_string($this->listing->status) && $this->listing->status === 'sold');

        return $isSold && (int) $this->listing->buyer_id === Auth::id();
    }

    public function setRating(int $r): void
    {
        $this->rating = max(0, min(5, $r));
    }

    public function save(NotificationService $notifier): void
    {
        if (! $this->canReview()) {
            $this->dispatch('toast', type: 'error', message: __('Not allowed'));
            return;
        }
        $this->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $isNew = ! $this->existingId;

        $review = MarketplaceReview::updateOrCreate(
            [
                'listing_id'  => $this->listing->id,
                'reviewer_id' => Auth::id(),
            ],
            [
                'tenant_id'         => $this->listing->tenant_id,
                'seller_id'         => $this->listing->user_id,
                'rating'            => $this->rating,
                'comment'           => trim($this->comment) ?: null,
                'is_buyer_verified' => true,
            ]
        );

        $this->existingId = $review->id;

        if ($isNew && $this->listing->seller) {
            $reviewerName = Auth::user()->name ?: Auth::user()->username;
            $notifier->send(
                $this->listing->seller,
                'marketplace.review_received',
                __(':name left you a :stars-star review', [
                    'name'  => $reviewerName,
                    'stars' => $this->rating,
                ]),
                \Illuminate\Support\Str::limit((string) $this->comment, 140) ?: __('No comment'),
                [
                    'listing_id' => $this->listing->id,
                    'review_id'  => $review->id,
                    'url'        => route('marketplace.show', ['slug' => $this->listing->slug]),
                ],
            );
        }

        $this->dispatch('toast', type: 'success', message: $isNew ? __('Review posted — thanks!') : __('Review updated'));
        $this->redirectRoute('marketplace.show', ['slug' => $this->listing->slug], navigate: true);
    }

    #[Computed]
    public function sellerName(): string
    {
        return $this->listing->seller?->name ?: $this->listing->seller?->username ?: 'Seller';
    }

    public function render()
    {
        return view('livewire.marketplace.leave-review');
    }
}

