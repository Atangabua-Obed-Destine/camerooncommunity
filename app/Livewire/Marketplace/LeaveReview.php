<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceListing;
use App\Services\Market\ReviewService;
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

        if ($existing = app(ReviewService::class)->mine($this->listing, Auth::user())) {
            $this->existingId = $existing->id;
            $this->rating = $existing->rating;
            $this->comment = (string) $existing->comment;
        }
    }

    /** Sold to you, or bought through a completed order: ReviewService::canReview. */
    public function canReview(): bool
    {
        return app(ReviewService::class)->canReview($this->listing, Auth::user());
    }

    public function setRating(int $r): void
    {
        $this->rating = max(0, min(5, $r));
    }

    public function save(): void
    {
        if (! $this->canReview()) {
            $this->dispatch('toast', type: 'error', message: __('Not allowed'));
            return;
        }
        $this->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $saved = app(ReviewService::class)->save($this->listing, Auth::user(), $this->rating, $this->comment);
        $this->existingId = $saved['review']->id;

        $this->dispatch('toast', type: 'success', message: $saved['created'] ? __('Review posted, thanks!') : __('Review updated'));
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

