<?php

namespace App\Livewire\Marketplace;

use App\Enums\OfferStatus;
use App\Livewire\Concerns\InteractsWithFollows;
use App\Models\MarketplaceFavorite;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingView;
use App\Models\MarketplaceOffer;
use App\Services\OfferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
class ListingDetail extends Component
{
    use InteractsWithFollows;

    public MarketplaceListing $listing;
    public int $activeMediaIndex = 0;

    /** True when rendered as an in-grid modal (nested in FeedBrowse), false as a full page. */
    public bool $asModal = false;

    // Offer modal state
    public bool $showOfferModal = false;
    public string $offerAmount = '';
    public string $offerMessage = '';

    // Counter-offer state (seller side)
    public ?int $counteringOfferId = null;
    public string $counterAmount = '';

    // Report modal state
    public bool $showReportModal = false;
    public string $reportReason = 'scam';
    public string $reportDetails = '';

    // Inline "Send seller a message" composer (FB-style panel)
    public string $inquiryMessage = '';

    public function mount(string $slug, bool $asModal = false): void
    {
        $this->asModal = $asModal;
        $this->listing = MarketplaceListing::where('slug', $slug)
            ->with(['seller', 'category', 'media'])
            ->firstOrFail();

        // Active + Sold listings are viewable by anyone (sold ones render a
        // "Sold" state and let the buyer leave a review — FB-style, no 404).
        // Draft/pending/paused/expired/removed remain owner-only previews.
        if (! $this->listing->status->isViewable() && ! $this->listing->isOwnedBy(Auth::id())) {
            abort(404);
        }

        $this->recordView();
        \App\Support\RecentlyViewed::track($this->listing->slug);

        $this->inquiryMessage = $this->defaultInquiry();
    }

    /** FB-style prefilled first message, greeting the seller by first name. */
    protected function defaultInquiry(): string
    {
        return app(\App\Services\Market\ListingService::class)->defaultInquiry($this->listing);
    }

    protected function recordView(): void
    {
        app(\App\Services\Market\ListingService::class)->recordView(
            $this->listing,
            Auth::user(),
            hash('sha256', request()->ip() . '|' . request()->userAgent()),
            (string) request()->query('src', 'direct'),
        );
    }

    public function toggleFavorite(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }
        app(\App\Services\Market\ListingService::class)->toggleFavorite($this->listing, Auth::user());
        $this->listing->refresh();
        $this->dispatch('favorite-toggled');
    }

    public function isFavorited(): bool
    {
        return app(\App\Services\Market\ListingService::class)->isFavorited($this->listing, Auth::user());
    }

    public function setMedia(int $idx): void
    {
        $this->activeMediaIndex = $idx;
    }

    // ─── Offer flow ───────────────────────────────────────────────

    #[Computed]
    public function similarListings()
    {
        return app(\App\Services\Market\ListingService::class)->similar($this->listing, Auth::user());
    }

    #[Computed]
    public function myOffer(): ?MarketplaceOffer
    {
        if (! Auth::check() || $this->listing->isOwnedBy(Auth::id())) {
            return null;
        }
        return MarketplaceOffer::where('listing_id', $this->listing->id)
            ->where('buyer_id', Auth::id())
            ->whereIn('status', [OfferStatus::Pending->value, OfferStatus::Countered->value, OfferStatus::Accepted->value])
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function pendingOffers()
    {
        if (! Auth::check() || ! $this->listing->isOwnedBy(Auth::id())) {
            return collect();
        }
        // Pending ones are the seller's to answer; countered ones show as waiting.
        return MarketplaceOffer::where('listing_id', $this->listing->id)
            ->whereIn('status', [OfferStatus::Pending->value, OfferStatus::Countered->value])
            ->with('buyer')
            ->latest('id')
            ->get();
    }

    // ─── Reviews ──────────────────────────────────────────────────

    #[Computed]
    public function reviews()
    {
        return \App\Models\MarketplaceReview::where('listing_id', $this->listing->id)
            ->with('reviewer:id,name,username,avatar')
            ->latest()
            ->limit(10)
            ->get();
    }

    /** Buyer can leave a review when listing is sold to them and they haven't yet. */
    #[Computed]
    public function canLeaveReview(): bool
    {
        // The review page's own rule, which also lets in the buyer of one of
        // several units (the listing stays up, so it never reads as sold).
        return app(\App\Services\Market\ReviewService::class)->canReview($this->listing, Auth::user());
    }

    #[Computed]
    public function myReview(): ?\App\Models\MarketplaceReview
    {
        if (! Auth::check()) { return null; }
        return \App\Models\MarketplaceReview::where('listing_id', $this->listing->id)
            ->where('reviewer_id', Auth::id())
            ->first();
    }

    public function openOfferModal(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }
        $this->offerAmount = (string) (int) ($this->listing->price ?? 0);
        $this->offerMessage = '';
        $this->showOfferModal = true;
    }

    public function submitOffer(): void
    {
        $this->validate([
            'offerAmount'  => 'required|numeric|min:1',
            'offerMessage' => 'nullable|string|max:500',
        ]);

        if ($this->listing->isOwnedBy(Auth::id())) {
            return;
        }

        // Rate-limit: 10 offers per user per hour to deter spam.
        $rlKey = 'mp:offer:' . Auth::id();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rlKey, 10)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rlKey);
            $this->dispatch('toast', type: 'error',
                message: __('Too many offers. Try again in :n minutes.', ['n' => (int) ceil($seconds / 60)]));
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rlKey, 3600);

        app(OfferService::class)->make($this->listing, Auth::user(), (float) $this->offerAmount, $this->offerMessage);

        $this->showOfferModal = false;
        $this->dispatch('toast', message: __('Offer sent ✓'), type: 'success');
        unset($this->myOffer);
    }

    public function withdrawOffer(int $offerId, OfferService $offers): void
    {
        $this->answered($offers->withdraw($this->buyerOffer($offerId), Auth::user()), __('Offer withdrawn.'));
    }

    public function acceptCounter(int $offerId, OfferService $offers): void
    {
        $this->answered($offers->acceptCounter($this->buyerOffer($offerId), Auth::user()), __('Counter-offer accepted.'));
    }

    public function declineCounter(int $offerId, OfferService $offers): void
    {
        $this->answered($offers->declineCounter($this->buyerOffer($offerId), Auth::user()), __('Counter-offer declined.'));
    }

    protected function buyerOffer(int $id): MarketplaceOffer
    {
        return MarketplaceOffer::where('id', $id)
            ->where('listing_id', $this->listing->id)
            ->where('buyer_id', Auth::id())
            ->firstOrFail();
    }

    private function answered(bool $done, string $message): void
    {
        unset($this->myOffer, $this->pendingOffers);
        $done
            ? $this->dispatch('toast', message: $message, type: 'success')
            : $this->dispatch('toast', message: __('This offer has already been answered.'), type: 'error');
    }

    /**
     * Send the inline "Send seller a message" inquiry (FB-style panel composer).
     * Uses the same no-connection MarketplaceChatService as the floating dock,
     * then pops the dock open on the conversation.
     */
    public function sendInquiry(): void
    {
        $buyer = Auth::user();
        if (! $buyer) {
            $this->redirectRoute('login');
            return;
        }

        $text = trim($this->inquiryMessage);
        if ($text === '' || $this->listing->isOwnedBy($buyer->id) || ! $this->listing->seller) {
            return;
        }

        $result = app(\App\Services\Market\ListingService::class)->inquire($this->listing, $buyer, $text);

        if (isset($result['error'])) {
            $msg = match ($result['error']) {
                'rate_limited' => __('Too many messages. Try again in :n minutes.', ['n' => $result['retry_minutes'] ?? 60]),
                'blocked' => __('You can no longer message this user.'),
                default => __('Could not open the conversation.'),
            };
            $this->dispatch('toast', type: 'error', message: $msg);
            return;
        }

        // Open the floating dock on this conversation so the buyer can keep chatting.
        $this->dispatch('open-gomarket-chat', sellerId: $this->listing->seller->id, listingId: $this->listing->id);
        $this->dispatch('toast', type: 'success', message: __('Message sent ✓'));

        $this->inquiryMessage = $this->defaultInquiry();
        unset($this->hasMessaged);
    }

    public function messageAgain(): void
    {
        $buyer = Auth::user();
        if (! $buyer || $this->listing->isOwnedBy($buyer->id)) return;
        
        $seller = $this->listing->seller;
        if (! $seller) return;

        $svc = app(\App\Services\MarketplaceChatService::class);
        $result = $svc->openConversation($buyer, $seller, $this->listing);

        if (isset($result['error'])) {
            $this->dispatch('toast', type: 'error', message: __('Could not open the conversation.'));
            return;
        }

        $this->dispatch('open-gomarket-chat', sellerId: $seller->id, listingId: $this->listing->id);
    }

    #[Computed]
    public function hasMessaged(): bool
    {
        return app(\App\Services\Market\ListingService::class)->hasMessaged($this->listing, Auth::user());
    }

    public function acceptOffer(int $offerId, OfferService $offers): void
    {
        $this->answered($offers->accept($this->ownedOffer($offerId), Auth::user()), __('Offer accepted ✓'));
    }

    public function rejectOffer(int $offerId, OfferService $offers): void
    {
        $this->answered($offers->reject($this->ownedOffer($offerId), Auth::user()), __('Offer rejected.'));
    }

    public function startCounter(int $offerId): void
    {
        $offer = $this->ownedOffer($offerId);
        $this->counteringOfferId = $offer->id;
        $this->counterAmount = (string) (int) $offer->amount;
    }

    public function submitCounter(OfferService $offers): void
    {
        $this->validate(['counterAmount' => 'required|numeric|min:1']);
        if (! $this->counteringOfferId) { return; }

        $done = $offers->counter($this->ownedOffer($this->counteringOfferId), Auth::user(), (float) $this->counterAmount);
        $this->counteringOfferId = null;
        $this->counterAmount = '';
        $this->answered($done, __('Counter-offer sent ✓'));
    }

    public function cancelCounter(): void
    {
        $this->counteringOfferId = null;
        $this->counterAmount = '';
    }

    protected function ownedOffer(int $id): MarketplaceOffer
    {
        return MarketplaceOffer::where('id', $id)
            ->where('seller_id', Auth::id())
            ->firstOrFail();
    }

    // ─── Admin actions ────────────────────────────────────────────

    public function isAdmin(): bool
    {
        $u = Auth::user();
        return $u && method_exists($u, 'hasRole') && $u->hasRole('admin');
    }

    public function toggleVerifiedSeller(): void
    {
        abort_unless($this->isAdmin(), 403);
        $this->listing->update(['is_verified_seller' => ! $this->listing->is_verified_seller]);
        $this->listing->refresh();
        $this->dispatch('toast',
            type: 'success',
            message: $this->listing->is_verified_seller ? __('Marked as verified seller ✓') : __('Verification removed')
        );
    }

    public function toggleFeatured(): void
    {
        abort_unless($this->isAdmin(), 403);
        $this->listing->update(['is_featured' => ! $this->listing->is_featured]);
        $this->listing->refresh();
        $this->dispatch('toast',
            type: 'success',
            message: $this->listing->is_featured ? __('Listing featured ⭐') : __('Featured removed')
        );
    }

    // ─── Report listing ───────────────────────────────────────────

    /** True when the current user has already reported this listing. */
    #[Computed]
    public function hasReported(): bool
    {
        if (! Auth::check()) {
            return false;
        }
        return \App\Models\Report::query()
            ->where('reportable_type', MarketplaceListing::class)
            ->where('reportable_id', $this->listing->id)
            ->where('reporter_id', Auth::id())
            ->exists();
    }

    public function openReportModal(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }
        $this->reportReason = 'scam';
        $this->reportDetails = '';
        $this->showReportModal = true;
    }

    public function closeReportModal(): void
    {
        $this->showReportModal = false;
    }

    public function submitReport(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }

        $this->validate([
            'reportReason'  => 'required|in:' . collect(\App\Enums\ReportReason::cases())->pluck('value')->implode(','),
            'reportDetails' => 'nullable|string|max:1000',
        ]);

        $error = app(\App\Services\Market\ListingService::class)->report($this->listing, Auth::user(), $this->reportReason, $this->reportDetails);

        if ($error === 'rate_limited') {
            $this->dispatch('toast', type: 'error', message: __('Too many reports. Try again in :n minutes.', ['n' => 60]));
            return;
        }

        $this->showReportModal = false;
        unset($this->hasReported);

        match ($error) {
            'already_reported' => $this->dispatch('toast', type: 'info', message: __('You already reported this listing.')),
            null => $this->dispatch('toast', type: 'success', message: __('Thanks, our team will review this listing.')),
            default => null,
        };
    }

    // ─── Block seller ─────────────────────────────────────────────

    public function blockSeller(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }

        if (! app(\App\Services\Market\ListingService::class)->blockSeller($this->listing, Auth::user())) {
            return;
        }

        $this->dispatch('toast', type: 'success', message: __('Seller blocked. Their listings will be hidden.'));
        // marketplace.feed never existed: blocking ended on an error page.
        $this->redirectRoute('marketplace.index');
    }

    // ─── Trust badges (cached on the model side) ─────────────────

    #[Computed]
    public function sellerBadges(): array
    {
        return \App\Support\TrustBadges::forSeller($this->listing->seller);
    }

    #[Computed]
    public function sellerIsNew(): bool
    {
        return $this->listing->seller
            ? \App\Support\TrustBadges::isNewSeller($this->listing->seller)
            : false;
    }

    public function render()
    {
        return view('livewire.marketplace.listing-detail');
    }
}

