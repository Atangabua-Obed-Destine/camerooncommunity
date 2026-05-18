<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
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

    public bool $filtersOpen = false;

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $this->categorySlug = $slug;
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['query', 'categorySlug', 'region', 'country', 'condition', 'fulfillment', 'priceMin', 'priceMax', 'sort'])) {
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
        $cat = $this->activeCategory;
        $q = MarketplaceListing::query()->forFeed()->with(['user', 'category', 'media' => fn ($m) => $m->limit(1)]);

        if ($this->query !== '') {
            $term = '%' . trim($this->query) . '%';
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }
        if ($cat) {
            // If root category, include children
            $childIds = $cat->children()->pluck('id')->all();
            $ids = array_merge([$cat->id], $childIds);
            $q->whereIn('category_id', $ids);
        }
        $q->inRegion($this->region ?: null);
        $q->inCountry($this->country ?: null);
        if ($this->condition) {
            $q->where('condition', $this->condition);
        }
        if ($this->fulfillment) {
            $q->where('fulfillment', $this->fulfillment);
        }
        if ($this->priceMin !== null) {
            $q->where('price', '>=', $this->priceMin);
        }
        if ($this->priceMax !== null) {
            $q->where('price', '<=', $this->priceMax);
        }

        $q = match ($this->sort) {
            'price_asc'  => $q->orderBy('price'),
            'price_desc' => $q->orderByDesc('price'),
            'popular'    => $q->orderByDesc('views_count')->orderByDesc('favorites_count'),
            'ranked'     => $q->ranked(),
            default      => $q->orderByDesc('published_at')->orderByDesc('created_at'),
        };

        return $q->paginate(24);
    }

    public function clearFilters(): void
    {
        $this->reset(['query', 'region', 'country', 'condition', 'fulfillment', 'priceMin', 'priceMax']);
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
