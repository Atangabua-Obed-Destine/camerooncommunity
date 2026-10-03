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
use Livewire\Attributes\On;
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
#[Layout('components.layouts.rails', ['active' => 'profile'])]
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
            $this->toast('success', 'Connection request sent.', 'Demande de connexion envoyée.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }

        $this->refreshConnection();
    }

    /**
     * The other side acted (accepted, cancelled, disconnected, blocked). The
     * browser hears it over Echo and the view calls $refresh; this listener
     * covers components dispatching it server-side, e.g. the Connections panel.
     */
    #[On('connection-updated')]
    #[On('connection-state-changed')]
    public function onConnectionChanged(): void
    {
        $this->refreshConnection();
    }

    /** Withdraw a request that has not been answered yet. */
    public function cancelRequest(): void
    {
        if ($this->connectionState !== 'outgoing') {
            return;
        }

        app(ConnectionService::class)->cancel(auth()->user(), (int) $this->user->id);
        $this->toast('success', 'Request cancelled.', 'Demande annulée.');
        $this->refreshConnection();
    }

    /** Accept a request this person sent us. */
    public function acceptRequest(): void
    {
        if ($this->connectionState !== 'incoming') {
            return;
        }

        app(ConnectionService::class)->accept(auth()->user(), (int) $this->user->id);
        $this->toast('success', 'You are now connected.', 'Vous êtes maintenant connectés.');
        $this->refreshConnection();
        // accept() opens the one-to-one room, so the chat list has a new entry.
        $this->dispatch('refreshRoomList');
    }

    /** Turn down a request without connecting. */
    public function declineRequest(): void
    {
        if ($this->connectionState !== 'incoming') {
            return;
        }

        app(ConnectionService::class)->decline(auth()->user(), (int) $this->user->id);
        $this->toast('success', 'Request declined.', 'Demande refusée.');
        $this->refreshConnection();
    }

    /** End an accepted connection. */
    public function disconnect(): void
    {
        if ($this->connectionState !== 'connected') {
            return;
        }

        app(ConnectionService::class)->disconnect(auth()->user(), (int) $this->user->id);
        $this->toast('success', 'Disconnected.', 'Déconnecté.');
        $this->refreshConnection();
        $this->dispatch('refreshRoomList');
    }

    /** Lift a block this viewer placed. */
    public function unblock(): void
    {
        if ($this->connectionState !== 'blocked-by-me') {
            return;
        }

        app(ConnectionService::class)->unblock(auth()->user(), (int) $this->user->id);
        $this->toast('success', 'Unblocked.', 'Débloqué.');
        $this->refreshConnection();
    }

    /**
     * Open the conversation. Messaging is for connected people only, so when
     * they are not connected this says what to do instead of failing later in
     * the Yard with a 403 the user never sees.
     */
    public function message()
    {
        if ($this->isSelf()) {
            return null;
        }

        if ($this->connectionState !== 'connected') {
            $this->toast(
                'warning',
                'Send a connection request first — you can message each other once it is accepted.',
                'Envoyez d\'abord une demande de connexion : vous pourrez discuter une fois acceptée.'
            );

            return null;
        }

        $room = app(\App\Services\DirectMessageService::class)
            ->findOrCreate(auth()->user(), (int) $this->user->id);

        return redirect()->to(route('yard') . '?room=' . $room->id);
    }

    /**
     * Drop every cached read of the relationship. Called after our own actions
     * and when the other side acts, which arrives over Echo as
     * 'connection-state-changed' and is bound to $refresh in the view.
     */
    protected function refreshConnection(): void
    {
        unset($this->connection, $this->connectionState, $this->dmRoomId);
    }

    protected function toast(string $type, string $en, string $fr): void
    {
        $this->dispatch('toast', type: $type, message: app()->getLocale() === 'fr' ? $fr : $en);
    }

    /**
     * One word for the relationship, matching the vocabulary the Yard already
     * uses: self | connected | outgoing | incoming | blocked-by-me |
     * blocked-by-them | none. The whole action row is driven by this.
     */
    #[Computed]
    public function connectionState(): string
    {
        if ($this->isSelf()) {
            return 'self';
        }

        $c = $this->connection;

        if (! $c) {
            return 'none';
        }

        return match ($c->status) {
            UserConnection::STATUS_ACCEPTED => 'connected',
            UserConnection::STATUS_PENDING  => $c->requested_by === auth()->id() ? 'outgoing' : 'incoming',
            UserConnection::STATUS_BLOCKED  => $c->requested_by === auth()->id() ? 'blocked-by-me' : 'blocked-by-them',
            default                         => 'none',
        };
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

