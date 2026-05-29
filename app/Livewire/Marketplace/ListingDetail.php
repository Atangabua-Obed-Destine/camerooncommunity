<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingStatus;
use App\Enums\OfferStatus;
use App\Models\MarketplaceFavorite;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingView;
use App\Models\MarketplaceOffer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
class ListingDetail extends Component
{
    public MarketplaceListing $listing;
    public int $activeMediaIndex = 0;

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

    public function mount(string $slug): void
    {
        $this->listing = MarketplaceListing::where('slug', $slug)
            ->with(['seller', 'category', 'media'])
            ->firstOrFail();

        // Only publicly viewable listings (or owner can preview their own draft).
        if ($this->listing->status !== ListingStatus::Active && ! $this->listing->isOwnedBy(Auth::id())) {
            abort(404);
        }

        $this->recordView();
        \App\Support\RecentlyViewed::track($this->listing->slug);
    }

    protected function recordView(): void
    {
        $sessionHash = hash('sha256', request()->ip() . '|' . request()->userAgent());
        $alreadyToday = MarketplaceListingView::query()
            ->where('listing_id', $this->listing->id)
            ->where('session_hash', $sessionHash)
            ->whereDate('viewed_at', today())
            ->exists();

        if ($alreadyToday) {
            return;
        }

        $country = Auth::user()?->current_country;
        $region = Auth::user()?->current_region;

        MarketplaceListingView::create([
            'listing_id' => $this->listing->id,
            'user_id' => Auth::id(),
            'session_hash' => $sessionHash,
            'country' => $country ? mb_substr($country, 0, 4) : null,
            'region' => $region ? mb_substr($region, 0, 80) : null,
            'source' => mb_substr((string) request()->query('src', 'direct'), 0, 40),
            'viewed_at' => now(),
        ]);

        DB::table('marketplace_listings')->where('id', $this->listing->id)->increment('views_count');
        $this->listing->refresh();
        $this->listing->recomputeRankScore();
    }

    public function toggleFavorite(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }
        $userId = Auth::id();
        $fav = MarketplaceFavorite::where('listing_id', $this->listing->id)
            ->where('user_id', $userId)
            ->first();

        if ($fav) {
            $fav->delete();
            DB::table('marketplace_listings')->where('id', $this->listing->id)->decrement('favorites_count');
        } else {
            MarketplaceFavorite::create([
                'listing_id' => $this->listing->id,
                'user_id' => $userId,
            ]);
            DB::table('marketplace_listings')->where('id', $this->listing->id)->increment('favorites_count');
        }
        $this->listing->refresh();
        $this->listing->recomputeRankScore();
        $this->dispatch('favorite-toggled');
    }

    public function isFavorited(): bool
    {
        if (! Auth::check()) {
            return false;
        }
        return MarketplaceFavorite::where('listing_id', $this->listing->id)
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function setMedia(int $idx): void
    {
        $this->activeMediaIndex = $idx;
    }

    // ─── Offer flow ───────────────────────────────────────────────

    #[Computed]
    public function similarListings()
    {
        return MarketplaceListing::query()
            ->active()
            ->where('id', '!=', $this->listing->id)
            ->where(function ($q) {
                $q->where('category_id', $this->listing->category_id);
                if ($this->listing->region) {
                    $q->orWhere('region', $this->listing->region);
                }
            })
            ->with(['media' => fn ($q) => $q->orderBy('position')->limit(1), 'category'])
            ->orderByDesc('rank_score')
            ->limit(8)
            ->get();
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
        if (! Auth::check()) { return false; }
        if (Auth::id() === (int) $this->listing->user_id) { return false; }
        $isSold = ($this->listing->status?->value ?? $this->listing->status) === 'sold';
        if (! $isSold) { return false; }
        return (int) $this->listing->buyer_id === Auth::id();
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

        // Withdraw any existing pending offer from this buyer.
        MarketplaceOffer::where('listing_id', $this->listing->id)
            ->where('buyer_id', Auth::id())
            ->where('status', OfferStatus::Pending->value)
            ->update(['status' => OfferStatus::Withdrawn->value, 'responded_at' => now()]);

        MarketplaceOffer::create([
            'listing_id' => $this->listing->id,
            'buyer_id'   => Auth::id(),
            'seller_id'  => $this->listing->user_id,
            'amount'     => (float) $this->offerAmount,
            'currency'   => $this->listing->currency,
            'message'    => $this->offerMessage ?: null,
            'status'     => OfferStatus::Pending->value,
        ]);

        $this->showOfferModal = false;
        $this->dispatch('toast', message: __('Offer sent ✓'), type: 'success');
        unset($this->myOffer);
    }

    public function withdrawOffer(int $offerId): void
    {
        $offer = MarketplaceOffer::where('id', $offerId)
            ->where('buyer_id', Auth::id())
            ->whereIn('status', [OfferStatus::Pending->value, OfferStatus::Countered->value])
            ->firstOrFail();
        $offer->update(['status' => OfferStatus::Withdrawn->value, 'responded_at' => now()]);
        unset($this->myOffer);
    }

    public function acceptOffer(int $offerId): void
    {
        $offer = $this->ownedOffer($offerId);
        $offer->update(['status' => OfferStatus::Accepted->value, 'responded_at' => now()]);

        // Auto-reject all other open offers on this listing.
        MarketplaceOffer::where('listing_id', $this->listing->id)
            ->where('id', '!=', $offerId)
            ->whereIn('status', [OfferStatus::Pending->value, OfferStatus::Countered->value])
            ->update(['status' => OfferStatus::Rejected->value, 'responded_at' => now()]);

        unset($this->pendingOffers);
        $this->dispatch('toast', message: __('Offer accepted ✓'), type: 'success');
    }

    public function rejectOffer(int $offerId): void
    {
        $offer = $this->ownedOffer($offerId);
        $offer->update(['status' => OfferStatus::Rejected->value, 'responded_at' => now()]);
        unset($this->pendingOffers);
    }

    public function startCounter(int $offerId): void
    {
        $offer = $this->ownedOffer($offerId);
        $this->counteringOfferId = $offer->id;
        $this->counterAmount = (string) (int) $offer->amount;
    }

    public function submitCounter(): void
    {
        $this->validate(['counterAmount' => 'required|numeric|min:1']);
        if (! $this->counteringOfferId) { return; }

        $original = $this->ownedOffer($this->counteringOfferId);
        $original->update(['status' => OfferStatus::Countered->value, 'responded_at' => now()]);

        MarketplaceOffer::create([
            'listing_id'      => $this->listing->id,
            'buyer_id'        => $original->buyer_id,
            'seller_id'       => Auth::id(),
            'amount'          => (float) $this->counterAmount,
            'currency'        => $this->listing->currency,
            'status'          => OfferStatus::Countered->value,
            'parent_offer_id' => $original->id,
            'counter_amount'  => (float) $this->counterAmount,
        ]);

        $this->counteringOfferId = null;
        $this->counterAmount = '';
        unset($this->pendingOffers);
        $this->dispatch('toast', message: __('Counter-offer sent ✓'), type: 'success');
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

        // Block owner reporting themselves.
        if ($this->listing->isOwnedBy(Auth::id())) {
            $this->showReportModal = false;
            return;
        }

        // Rate-limit: 5 reports per user per hour.
        $rlKey = 'mp:report:' . Auth::id();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rlKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rlKey);
            $this->dispatch('toast', type: 'error',
                message: __('Too many reports. Try again in :n minutes.', ['n' => (int) ceil($seconds / 60)]));
            return;
        }

        $this->validate([
            'reportReason'  => 'required|in:' . collect(\App\Enums\ReportReason::cases())->pluck('value')->implode(','),
            'reportDetails' => 'nullable|string|max:1000',
        ]);

        // One report per user per listing.
        $exists = \App\Models\Report::query()
            ->where('reportable_type', MarketplaceListing::class)
            ->where('reportable_id', $this->listing->id)
            ->where('reporter_id', Auth::id())
            ->exists();

        if ($exists) {
            $this->showReportModal = false;
            $this->dispatch('toast', type: 'info', message: __('You already reported this listing.'));
            return;
        }

        \App\Models\Report::create([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'reporter_id'     => Auth::id(),
            'reportable_type' => MarketplaceListing::class,
            'reportable_id'   => $this->listing->id,
            'reason'          => $this->reportReason,
            'details'         => $this->reportDetails ?: null,
            'status'          => \App\Enums\ReportStatus::Pending,
        ]);

        \Illuminate\Support\Facades\RateLimiter::hit($rlKey, 3600);

        // Auto-flag listing after 3 distinct reports.
        $count = \App\Models\Report::query()
            ->where('reportable_type', MarketplaceListing::class)
            ->where('reportable_id', $this->listing->id)
            ->count();
        if ($count >= 3 && \Illuminate\Support\Facades\Schema::hasColumn('marketplace_listings', 'is_flagged')) {
            DB::table('marketplace_listings')->where('id', $this->listing->id)->update(['is_flagged' => true]);
        }

        $this->showReportModal = false;
        unset($this->hasReported);
        $this->dispatch('toast', type: 'success',
            message: __('Thanks — our team will review this listing.'));
    }

    // ─── Block seller ─────────────────────────────────────────────

    public function blockSeller(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');
            return;
        }
        $sellerId = (int) $this->listing->user_id;
        if ($sellerId === Auth::id()) {
            return;
        }

        $a = min(Auth::id(), $sellerId);
        $b = max(Auth::id(), $sellerId);

        \App\Models\UserConnection::updateOrCreate(
            ['user_a_id' => $a, 'user_b_id' => $b],
            [
                'status'        => \App\Models\UserConnection::STATUS_BLOCKED,
                'requested_by'  => Auth::id(),
                'responded_at'  => now(),
            ],
        );

        $this->dispatch('toast', type: 'success', message: __('Seller blocked. Their listings will be hidden.'));
        $this->redirectRoute('marketplace.feed');
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
