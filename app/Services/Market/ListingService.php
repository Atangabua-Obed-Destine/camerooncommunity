<?php

namespace App\Services\Market;

use App\Enums\MessageType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\MarketplaceFavorite;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingView;
use App\Models\Report;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Services\MarketplaceChatService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * What a member does on a listing: viewing it, saving it, messaging the
 * seller, reporting it, blocking the seller. Shared by the web's ListingDetail
 * and /api/v1/market, so a save or an inquiry from the app counts the same.
 */
class ListingService
{
    public function __construct(private MarketplaceChatService $chat) {}

    /** Active and sold listings are public; the rest are the owner's previews. */
    public function viewable(MarketplaceListing $listing, ?User $viewer): bool
    {
        return $listing->status->isViewable() || $listing->isOwnedBy($viewer?->id);
    }

    /**
     * Count a view, once a day per visitor (the web hashes IP and browser;
     * the app passes the member). Feeds the listing's ranking.
     */
    public function recordView(MarketplaceListing $listing, ?User $viewer, string $sessionHash, string $source = 'direct'): void
    {
        $already = MarketplaceListingView::query()
            ->where('listing_id', $listing->id)
            ->where('session_hash', $sessionHash)
            ->whereDate('viewed_at', today())
            ->exists();
        if ($already) {
            return;
        }

        MarketplaceListingView::create([
            'listing_id' => $listing->id,
            'user_id' => $viewer?->id,
            'session_hash' => $sessionHash,
            'country' => $viewer?->current_country ? mb_substr($viewer->current_country, 0, 4) : null,
            'region' => $viewer?->current_region ? mb_substr($viewer->current_region, 0, 80) : null,
            'source' => mb_substr($source, 0, 40),
            'viewed_at' => now(),
        ]);

        DB::table('marketplace_listings')->where('id', $listing->id)->increment('views_count');
        $listing->refresh();
        $listing->recomputeRankScore();
    }

    public function isFavorited(MarketplaceListing $listing, ?User $user): bool
    {
        return $user !== null && MarketplaceFavorite::where('listing_id', $listing->id)->where('user_id', $user->id)->exists();
    }

    /** Save or unsave. Returns the new state. */
    public function toggleFavorite(MarketplaceListing $listing, User $user): bool
    {
        $fav = MarketplaceFavorite::where('listing_id', $listing->id)->where('user_id', $user->id)->first();
        if ($fav) {
            $fav->delete();
            DB::table('marketplace_listings')->where('id', $listing->id)->where('favorites_count', '>', 0)->decrement('favorites_count');
        } else {
            MarketplaceFavorite::create(['listing_id' => $listing->id, 'user_id' => $user->id]);
            DB::table('marketplace_listings')->where('id', $listing->id)->increment('favorites_count');
        }
        $listing->refresh();
        $listing->recomputeRankScore();

        return $fav === null;
    }

    /**
     * Up to 8 similar listings: same category or same region, best ranked.
     * Through forFeed() and the viewer's blocks, like the feed itself; the
     * web's version skipped both, so a hidden, flagged or blocked seller's
     * listing could appear here.
     *
     * @return Collection<int, MarketplaceListing>
     */
    public function similar(MarketplaceListing $listing, ?User $viewer): Collection
    {
        $q = MarketplaceListing::query()->forFeed()
            ->where('id', '!=', $listing->id)
            ->where(function ($q) use ($listing) {
                $q->where('category_id', $listing->category_id);
                if ($listing->region) {
                    $q->orWhere('region', $listing->region);
                }
            });

        $blocked = $viewer?->blockedOrBlockingUserIds() ?? [];
        if ($blocked) {
            $q->whereNotIn('user_id', $blocked);
        }

        return $q->with(['cover', 'category'])->orderByDesc('rank_score')->limit(8)->get();
    }

    /** Whether this member has already asked the seller about this listing. */
    public function hasMessaged(MarketplaceListing $listing, ?User $user): bool
    {
        if (! $user || $listing->isOwnedBy($user->id)) {
            return false;
        }

        return YardMessage::where('user_id', $user->id)
            ->where('message_type', MessageType::ShareCard->value)
            ->where('shareable_type', MarketplaceListing::class)
            ->where('shareable_id', $listing->id)
            ->exists();
    }

    /** "Hi Ama, is this still available?": the seller's first name, the member's language. */
    public function defaultInquiry(MarketplaceListing $listing, ?string $lang = null): string
    {
        $first = trim((string) Str::of($listing->seller?->name ?? '')->before(' '));
        $first = $first !== '' ? ' '.$first : '';

        return ($lang ?? app()->getLocale()) === 'fr'
            ? trim("Bonjour{$first}, cet article est-il toujours disponible ?")
            : trim("Hi{$first}, is this still available?");
    }

    /**
     * Message the seller about this listing: opens (or reuses) the chat, drops
     * the product card in once, and posts the text. 20 an hour per buyer.
     *
     * @return array{room?: YardRoom, error?: string, retry_minutes?: int}
     */
    public function inquire(MarketplaceListing $listing, User $buyer, ?string $text): array
    {
        $seller = $listing->seller;
        if (! $seller || $listing->isOwnedBy($buyer->id)) {
            return ['error' => 'own_listing'];
        }

        $key = 'mp:inquiry:'.$buyer->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return ['error' => 'rate_limited', 'retry_minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)];
        }

        $result = $this->chat->openConversation($buyer, $seller, $listing);
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $text = trim((string) $text);
        if ($text !== '') {
            $this->chat->postMessage($result['room'], $buyer, $text);
            RateLimiter::hit($key, 3600);
        }

        return ['room' => $result['room']];
    }

    public function hasReported(MarketplaceListing $listing, ?User $user): bool
    {
        return $user !== null && Report::where('reportable_type', MarketplaceListing::class)
            ->where('reportable_id', $listing->id)->where('reporter_id', $user->id)->exists();
    }

    /**
     * Report a listing: once per member per listing, 5 an hour; three
     * reports from different members flag it out of the feed.
     *
     * @return string|null an error code, or null when reported
     */
    public function report(MarketplaceListing $listing, User $user, string $reason, ?string $details): ?string
    {
        if ($listing->isOwnedBy($user->id)) {
            return 'own_listing';
        }
        if (! ReportReason::tryFrom($reason)) {
            return 'invalid_reason';
        }
        $key = 'mp:report:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return 'rate_limited';
        }
        if ($this->hasReported($listing, $user)) {
            return 'already_reported';
        }

        Report::create([
            'reporter_id' => $user->id,
            'reportable_type' => MarketplaceListing::class,
            'reportable_id' => $listing->id,
            'reason' => $reason,
            'description' => $details ?: null,
            'status' => ReportStatus::Pending,
        ]);
        RateLimiter::hit($key, 3600);

        $count = Report::where('reportable_type', MarketplaceListing::class)->where('reportable_id', $listing->id)->count();
        if ($count >= 3) {
            DB::table('marketplace_listings')->where('id', $listing->id)->update(['is_flagged' => true]);
        }

        return null;
    }

    /** Block the seller: their listings and messages disappear for this member. */
    public function blockSeller(MarketplaceListing $listing, User $user): bool
    {
        $sellerId = (int) $listing->user_id;
        if ($sellerId === $user->id) {
            return false;
        }

        UserConnection::updateOrCreate(
            ['user_a_id' => min($user->id, $sellerId), 'user_b_id' => max($user->id, $sellerId)],
            ['status' => UserConnection::STATUS_BLOCKED, 'requested_by' => $user->id, 'responded_at' => now()],
        );

        return true;
    }
}
