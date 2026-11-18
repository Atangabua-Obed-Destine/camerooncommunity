<?php

namespace App\Services\Market;

use App\Enums\ListingStatus;
use App\Models\CommunityPointsLog;
use App\Models\MarketplaceListing;
use App\Models\SolidarityContribution;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserConnection;
use App\Models\UserFollow;
use App\Models\YardRoomMember;
use App\Support\MarketplaceQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The member profile (the web's SellerProfile, which is the app-wide
 * profile page): who may see it, the relationship with the viewer, the
 * figures, and the listings grid. Shared by the Livewire page and the API.
 */
class SellerProfileService
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'popular'];

    /**
     * The profile a viewer may open: a discoverable member of the same
     * community who has not blocked them. (The blocker still gets the page,
     * which is where Unblock lives.)
     */
    public function find(User $viewer, string $username): ?User
    {
        $seller = User::where('username', $username)
            ->discoverable()
            ->where('tenant_id', $viewer->tenant_id)
            ->first();

        return $seller && ! $viewer->isBlockedBy($seller->id) ? $seller : null;
    }

    /**
     * One word for the relationship, the Yard's vocabulary: self | connected
     * | outgoing | incoming | blocked-by-me | blocked-by-them | none.
     */
    public function connectionState(User $viewer, User $seller): string
    {
        if ($viewer->id === $seller->id) {
            return 'self';
        }

        $c = UserConnection::between($viewer->id, $seller->id);
        if (! $c) {
            return 'none';
        }

        return match ($c->status) {
            UserConnection::STATUS_ACCEPTED => 'connected',
            UserConnection::STATUS_PENDING => $c->requested_by === $viewer->id ? 'outgoing' : 'incoming',
            UserConnection::STATUS_BLOCKED => $c->requested_by === $viewer->id ? 'blocked-by-me' : 'blocked-by-them',
            default => 'none',
        };
    }

    public function isFollowing(User $viewer, User $seller): bool
    {
        return $viewer->id !== $seller->id
            && UserFollow::where('follower_id', $viewer->id)->where('following_id', $seller->id)->exists();
    }

    /** @return array{active:int, rating_avg:float, rating_count:int, followers:int} */
    public function stats(User $seller): array
    {
        return [
            'active' => MarketplaceListing::query()->forFeed()->where('user_id', $seller->id)->count(),
            'rating_avg' => round((float) $seller->sellerReviews()->avg('rating'), 1),
            'rating_count' => (int) $seller->sellerReviews()->count(),
            'followers' => UserFollow::where('following_id', $seller->id)->count(),
        ];
    }

    /** @return array{points:int, rooms:int, contributions:int} */
    public function communityStats(User $seller): array
    {
        return [
            'points' => (int) CommunityPointsLog::where('user_id', $seller->id)->sum('points_awarded'),
            'rooms' => YardRoomMember::where('user_id', $seller->id)->inCommunityRooms()->count(),
            'contributions' => SolidarityContribution::where('contributor_id', $seller->id)->count(),
        ];
    }

    /** Awarded badges, distinct from the computed seller trust badges. */
    public function badges(User $seller): Collection
    {
        return UserBadge::where('user_id', $seller->id)->get();
    }

    /**
     * The listings grid. Your own profile shows everything you have posted
     * (drafts and paused included, removed not); anyone else sees the feed.
     */
    public function listings(User $viewer, User $seller, string $search = '', string $sort = 'newest'): Builder
    {
        $self = $viewer->id === $seller->id;
        $q = MarketplaceListing::query()
            ->when(
                $self,
                fn ($q) => $q->where('status', '!=', ListingStatus::Removed->value),
                fn ($q) => $q->forFeed(),
            )
            ->where('user_id', $seller->id)
            ->with(['category', 'cover']);

        $term = trim($search);
        if ($term !== '') {
            $q->where('title', 'like', '%'.$term.'%');
        }

        MarketplaceQueryBuilder::applySort($q, in_array($sort, self::SORTS, true) ? $sort : 'newest');

        return $q;
    }
}
