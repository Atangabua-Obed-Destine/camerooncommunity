<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingStatus;
use App\Livewire\Concerns\InteractsWithFollows;
use App\Models\CommunityPointsLog;
use App\Models\MarketplaceListing;
use App\Models\SolidarityContribution;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserConnection;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\ConnectionService;
use App\Support\MarketplaceQueryBuilder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The user profile page for the whole app: header (cover, avatar, joined,
 * listings count, Follow / Message / Connect), community stats, Intro and
 * badges, plus a searchable, sortable grid of the member's listings.
 *
 * This replaced the old /u/{username} page, which now redirects here, so it
 * carries everything that page showed. It uses the plain rails shell (icon
 * sidebar + ads sidebar) rather than the GoMarket chrome, because a profile
 * opened from a chat should not drop the viewer into the shop.
 */
#[Layout('components.layouts.rails', ['active' => 'yard'])]
class SellerProfile extends Component
{
    use InteractsWithFollows, WithPagination;

    public User $user;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function mount(string $username): void
    {
        $viewer = auth()->user();

        $seller = User::where('username', $username)
            ->where('tenant_id', $viewer->tenant_id)
            ->firstOrFail();

        if (method_exists($viewer, 'hasBlockedOrIsBlockedBy') && $viewer->hasBlockedOrIsBlockedBy($seller->id)) {
            abort(404);
        }

        $this->user = $seller;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function listings()
    {
        // On your own profile you should see everything you have posted, including
        // drafts and paused items, which forFeed() hides. Other visitors still
        // only ever see the public feed.
        $q = MarketplaceListing::query()
            ->when(
                $this->isSelf(),
                fn ($q) => $q->where('status', '!=', ListingStatus::Removed->value),
                fn ($q) => $q->forFeed(),
            )
            ->where('user_id', $this->user->id)
            ->with(['category', 'media' => fn ($m) => $m->limit(1)]);

        $term = trim($this->search);
        if ($term !== '') {
            $q->where('title', 'like', '%' . $term . '%');
        }

        MarketplaceQueryBuilder::applySort($q, $this->sort);

        return $q->paginate(18);
    }

    /**
     * Send a connection request. Mirrors Yard\Connections::sendRequest so the
     * two entry points behave and read the same.
     */
    public function connect(): void
    {
        if ($this->isSelf()) {
            return;
        }

        try {
            app(ConnectionService::class)->request(auth()->user(), $this->user);
            $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
                ? 'Demande de connexion envoyée.'
                : 'Connection request sent.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }

        unset($this->connection);
    }

    /** True when the viewer is looking at their own profile. */
    #[Computed]
    public function isSelf(): bool
    {
        return (int) auth()->id() === (int) $this->user->id;
    }

    /** Connection state with the viewer, for the Connected / Pending chip. */
    #[Computed]
    public function connection(): ?UserConnection
    {
        return $this->isSelf() ? null : UserConnection::between((int) auth()->id(), (int) $this->user->id);
    }

    /** Existing DM room, so Message opens the real conversation, not a new one. */
    #[Computed]
    public function dmRoomId(): ?int
    {
        if ($this->isSelf()) {
            return null;
        }

        return YardRoom::where('room_type', 'direct_message')
            ->whereHas('members', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereHas('members', fn ($q) => $q->where('user_id', $this->user->id))
            ->value('id');
    }

    /** Awarded badges, distinct from the computed seller trust badges. */
    #[Computed]
    public function badges()
    {
        return UserBadge::where('user_id', $this->user->id)->get();
    }

    /** Community stats carried over from the old profile page. */
    #[Computed]
    public function communityStats(): array
    {
        return [
            'points'        => (int) CommunityPointsLog::where('user_id', $this->user->id)->sum('points_awarded'),
            'rooms'         => YardRoomMember::where('user_id', $this->user->id)->count(),
            'contributions' => SolidarityContribution::where('contributor_id', $this->user->id)->count(),
        ];
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'active'        => MarketplaceListing::query()->forFeed()->where('user_id', $this->user->id)->count(),
            'rating_avg'    => round((float) $this->user->sellerReviews()->avg('rating'), 1),
            'rating_count'  => (int) $this->user->sellerReviews()->count(),
            'followers'     => $this->followerCount($this->user->id),
        ];
    }

    public function render()
    {
        return view('livewire.marketplace.seller-profile');
    }
}

