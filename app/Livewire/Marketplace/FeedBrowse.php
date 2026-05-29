<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceSavedSearch;
use App\Support\MarketplaceQueryBuilder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
#[Title('Marketplace')]
class FeedBrowse extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'cat', except: '')]
    public string $categorySlug = '';

    #[Url(except: '')]
    public string $region = '';

    #[Url(except: '')]
    public string $country = '';

    #[Url(except: '')]
    public string $condition = '';

    #[Url(except: '')]
    public string $fulfillment = '';

    #[Url(except: '')]
    public ?int $priceMin = null;

    #[Url(except: '')]
    public ?int $priceMax = null;

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    /** UI view mode: 'grid' (cards) or 'map' (region markers). */
    #[Url(except: 'grid')]
    public string $view = 'grid';

    /** Category-specific filters (Phase 4). Lives in the URL as ?a[brand]=Toyota etc. */
    #[Url(as: 'a', except: [])]
    public array $attrs = [];

    public bool $filtersOpen = false;

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $this->categorySlug = $slug;
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['query', 'categorySlug', 'region', 'country', 'condition', 'fulfillment', 'priceMin', 'priceMax', 'sort'])
            || str_starts_with($name, 'attrs.')) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function categories()
    {
        return MarketplaceCategory::roots()->active()->orderBy('position')->get();
    }

    #[Computed]
    public function activeCategory(): ?MarketplaceCategory
    {
        return $this->categorySlug
            ? MarketplaceCategory::where('slug', $this->categorySlug)->first()
            : null;
    }

    #[Computed]
    public function listings()
    {
        $q = MarketplaceQueryBuilder::build($this->currentFilters())
            ->with(['user', 'category', 'media' => fn ($m) => $m->limit(1)]);

        return $q->paginate(24);
    }

    /**
     * Lightweight payload for the map view — up to 120 listings with their
     * region resolved to approximate centroid coordinates (jittered so
     * multiple listings in the same region don't stack into a single pin).
     */
    #[Computed]
    public function mapPoints(): array
    {
        $centroids = config('cameroon.region_centroids', \App\Support\CameroonGeo::centroids());

        $rows = MarketplaceQueryBuilder::build($this->currentFilters())
            ->with(['media' => fn ($m) => $m->limit(1)])
            ->limit(120)
            ->get(['id', 'slug', 'title', 'price', 'price_type', 'currency', 'region']);

        $out = [];
        foreach ($rows as $r) {
            $key = \App\Support\CameroonGeo::matchRegion((string) $r->region);
            $c = $centroids[$key] ?? null;
            if (! $c) { continue; }
            // Deterministic jitter from listing id so pins are stable but spread.
            $jLat = (($r->id * 9301 + 49297) % 233280) / 233280 - 0.5;
            $jLng = (($r->id * 49297 + 9301) % 233280) / 233280 - 0.5;
            $out[] = [
                'id'    => $r->id,
                'slug'  => $r->slug,
                'title' => $r->title,
                'price' => $r->formattedPrice(),
                'lat'   => $c['lat'] + $jLat * 0.18,
                'lng'   => $c['lng'] + $jLng * 0.18,
                'thumb' => $r->coverUrl(),
                'url'   => route('marketplace.show', ['slug' => $r->slug]),
            ];
        }
        return $out;
    }

    /** Snapshot of the current filter state (used by listings + saveCurrentSearch). */
    public function currentFilters(): array
    {
        return MarketplaceQueryBuilder::normalize([
            'query'        => $this->query,
            'categorySlug' => $this->categorySlug,
            'region'       => $this->region,
            'country'      => $this->country,
            'condition'    => $this->condition,
            'fulfillment'  => $this->fulfillment,
            'priceMin'     => $this->priceMin,
            'priceMax'     => $this->priceMax,
            'sort'         => $this->sort,
            'attrs'        => $this->attrs,
        ]);
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->currentFilters() !== [];
    }

    /**
     * Persist the current filter state as a MarketplaceSavedSearch for the
     * authenticated user. Default name is auto-derived from the filters when
     * the caller doesn't pass one in. Notifications are enabled by default
     * (in-app + email opt-in via the saved-searches page).
     */
    public function saveCurrentSearch(?string $name = null): void
    {
        $user = auth()->user();
        if (! $user) { return; }

        $filters = $this->currentFilters();
        if ($filters === []) {
            $this->dispatch('toast', type: 'error', message: __('Pick at least one filter before saving'));
            return;
        }

        // Cap per user to avoid runaway alerts.
        if (MarketplaceSavedSearch::where('user_id', $user->id)->count() >= 20) {
            $this->dispatch('toast', type: 'error', message: __('You can save up to 20 searches'));
            return;
        }

        $label = trim((string) $name);
        if ($label === '') {
            $label = \Illuminate\Support\Str::limit(MarketplaceQueryBuilder::summarize($filters, app()->getLocale()), 100);
        }

        MarketplaceSavedSearch::create([
            'tenant_id'    => $user->tenant_id,
            'user_id'      => $user->id,
            'name'         => $label,
            'filters'      => $filters,
            'notify_email' => false,
            'notify_push'  => true,
            // Seed last_notified_at to now so we never spam the user with
            // the entire existing feed on first scheduler run.
            'last_notified_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: __('Search saved — we\u2019ll alert you when new matches arrive'));
        $this->dispatch('savedSearchesUpdated');
    }

    public function clearFilters(): void
    {
        $this->reset(['query', 'region', 'country', 'condition', 'fulfillment', 'priceMin', 'priceMax', 'attrs']);
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function conditionOptions(): array
    {
        return collect(ListingCondition::cases())
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label(), 'fr' => $c->labelFr()])
            ->all();
    }

    public function fulfillmentOptions(): array
    {
        return collect(ListingFulfillment::cases())
            ->map(fn ($f) => ['value' => $f->value, 'label' => $f->label(), 'fr' => $f->labelFr(), 'icon' => $f->icon()])
            ->all();
    }

    public function render()
    {
        return view('livewire.marketplace.feed-browse');
    }
}
