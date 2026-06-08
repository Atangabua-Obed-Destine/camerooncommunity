<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Concerns\InteractsWithFollows;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Support\MarketplaceQueryBuilder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Facebook-Marketplace-style seller profile: header (avatar, joined, active
 * listings count, Follow / Message / View full profile) + a searchable,
 * sortable grid of the seller's active listings. Lives inside the GoMarket
 * rails chrome.
 */
#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
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
        $q = MarketplaceListing::query()
            ->forFeed()
            ->where('user_id', $this->user->id)
            ->with(['category', 'media' => fn ($m) => $m->limit(1)]);

        $term = trim($this->search);
        if ($term !== '') {
            $q->where('title', 'like', '%' . $term . '%');
        }

        MarketplaceQueryBuilder::applySort($q, $this->sort);

        return $q->paginate(18);
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
