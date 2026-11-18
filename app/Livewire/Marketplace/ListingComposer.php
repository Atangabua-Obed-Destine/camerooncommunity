<?php

namespace App\Livewire\Marketplace;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Enums\ListingPriceType;
use App\Enums\ListingStatus;
use App\Enums\ListingVisibility;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingMedia;
use App\Models\PlatformSetting;
use App\Services\Market\ListingComposerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
class ListingComposer extends Component
{
    use WithFileUploads;

    public ?MarketplaceListing $listing = null;

    // Category
    public ?int $categoryId = null;

    // Photos — single list ordered by position; the first item is the cover.
    public array $photos = [];
    public array $mediaItems = [];

    /** FB listing-type chooser: null = show chooser; then 'item' | 'vehicle' | 'home'. */
    public ?string $listingType = null;

    // Step 3: details
    public string $title = '';
    public string $description = '';
    public string $priceType = 'fixed';
    public ?string $price = null;
    public string $currency = 'XAF';
    public string $condition = 'good';
    public int $quantity = 1;

    // Step 4: fulfillment + location
    public array $fulfillment = ['pickup'];
    public string $country = 'CM';
    public string $region = '';
    public string $city = '';
    public string $neighborhood = '';
    public ?float $latitude = null;
    public ?float $longitude = null;
    public string $locationLabel = '';

    // Step 5: visibility + review
    public string $visibility = 'public';
    public array $tags = [];
    public string $tagInput = '';

    /** Category-specific attributes (Phase 4). Keyed by schema 'key' (e.g. 'make','year','bedrooms'). */
    public array $attrs = [];

    /** Friendly field names for validation messages (FB-style clean copy). */
    protected function validationAttributes(): array
    {
        return [
            'categoryId'  => __('category'),
            'priceType'   => __('price type'),
            'title'       => __('title'),
            'price'       => __('price'),
            'condition'   => __('condition'),
            'photos'      => __('photos'),
            'fulfillment' => __('delivery method'),
        ];
    }

    public function getCurrenciesProperty(): array
    {
        return ListingComposerService::CURRENCIES;
    }

    /** The service's field names → this component's properties. */
    private const FIELDS = [
        'category_id' => 'categoryId',
        'price_type' => 'priceType',
        'location_label' => 'locationLabel',
    ];

    /** Everything publishing checks (ListingComposerService::rules). */
    protected function publishRules(): array
    {
        return app(ListingComposerService::class)->rules($this->categoryId, self::FIELDS, 'attrs');
    }

    /** This form as the service's fields. */
    protected function fields(): array
    {
        return [
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'description' => $this->description,
            'price_type' => $this->priceType,
            'price' => $this->price,
            'currency' => $this->currency,
            'condition' => $this->condition,
            'quantity' => $this->quantity,
            'fulfillment' => $this->fulfillment,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'neighborhood' => $this->neighborhood,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_label' => $this->locationLabel,
            'visibility' => $this->visibility,
            'tags' => $this->tags,
            'attributes' => $this->attrs,
        ];
    }

    public function mount(MarketplaceListing|int|null $listing = null): void
    {
        $this->currency = (string) PlatformSetting::getValue('marketplace_default_currency', 'XAF');

        $user = Auth::user();
        if ($user) {
            $this->country = $user->current_country_code ?? 'CM';
            $this->region = $user->current_region ?? '';
            $this->city = $user->current_city ?? '';
            $this->latitude = $user->current_lat !== null ? (float) $user->current_lat : null;
            $this->longitude = $user->current_lng !== null ? (float) $user->current_lng : null;
            $this->locationLabel = collect([$user->current_city, $user->current_region, $user->current_country_code])->filter()->join(', ');
        }

        if ($listing) {
            $l = $listing instanceof MarketplaceListing
                ? $listing->load('media')
                : MarketplaceListing::with('media')->findOrFail($listing);
            if (! $l->isOwnedBy(Auth::id())) {
                abort(403);
            }
            $this->listing = $l;
            $this->categoryId = $l->category_id;
            $this->title = $l->title;
            $this->description = $l->description ?? '';
            $this->priceType = $l->price_type?->value ?? 'fixed';
            $this->price = $l->price !== null ? (string) $l->price : null;
            $this->currency = $l->currency;
            $this->condition = $l->condition?->value ?? 'good';
            $this->quantity = $l->quantity;
            $this->fulfillment = is_array($l->fulfillment) ? $l->fulfillment : (is_string($l->fulfillment) ? [$l->fulfillment] : []);
            $this->country = $l->country ?? 'CM';
            $this->region = $l->region ?? '';
            $this->city = $l->city ?? '';
            $this->neighborhood = $l->neighborhood ?? '';
            $this->latitude = $l->latitude !== null ? (float) $l->latitude : null;
            $this->longitude = $l->longitude !== null ? (float) $l->longitude : null;
            $this->locationLabel = collect([$this->neighborhood, $this->city, $this->region, $this->country])->filter()->join(', ');
            $this->visibility = $l->visibility?->value ?? 'public';
            $this->tags = $l->tags ?? [];
            $this->attrs = is_array($l->attributes) ? $l->attributes : [];
            $this->listingType = 'item'; // editing skips the type chooser
            $this->refreshMedia();
        }
    }

    /** Choose what kind of listing this is (Facebook's first screen). */
    public function chooseType(string $type): void
    {
        $this->listingType = in_array($type, ['item', 'vehicle', 'home'], true) ? $type : 'item';

        // Preselect the matching category for vehicle / home so the right
        // attribute fields appear, exactly like Facebook's dedicated flows.
        $slug = ['vehicle' => 'vehicles', 'home' => 'real-estate'][$type] ?? null;
        if ($slug && ! $this->categoryId) {
            $cat = MarketplaceCategory::where('slug', $slug)->first();
            if ($cat) {
                $this->categoryId = $cat->id;
            }
        }
    }

    /** Return to the listing-type chooser (create flow only). */
    public function backToTypes(): void
    {
        if (! ($this->listing && $this->listing->status?->value !== 'draft')) {
            $this->listingType = null;
        }
    }

    /** Reload the ordered media list from the database (first = cover). */
    protected function refreshMedia(): void
    {
        if (! $this->listing) {
            $this->mediaItems = [];
            return;
        }
        $this->mediaItems = collect(app(ListingComposerService::class)->mediaItems($this->listing))
            ->map(fn ($m) => ['id' => $m['id'], 'url' => $m['thumb_url'], 'is_cover' => $m['is_cover']])
            ->all();
    }

    public function updatedPhotos(): void
    {
        $this->validate(['photos.*' => 'nullable|'.ListingComposerService::PHOTO_RULE]);
        if (empty($this->photos) || count($this->mediaItems) >= app(ListingComposerService::class)->maxImages()) {
            $this->photos = [];
            return;
        }

        // Need a parent listing to attach to; create draft early if needed.
        $this->ensureDraftListing();
        app(ListingComposerService::class)->addPhotos($this->listing, $this->photos);
        $this->photos = [];
        $this->refreshMedia();
    }

    public function removeMedia(int $mediaId): void
    {
        if (! $this->listing) { return; }
        app(ListingComposerService::class)->removePhoto($this->listing, $mediaId);
        $this->refreshMedia();
    }

    /** The starred photo becomes the cover, which is the first one. */
    public function makeCover(int $mediaId): void
    {
        if (! $this->listing) { return; }
        app(ListingComposerService::class)->makeCover($this->listing, $mediaId);
        $this->refreshMedia();
    }

    /**
     * Persist a new photo order from drag-and-drop. The first photo becomes
     * the cover, exactly like Facebook Marketplace.
     *
     * @param  array<int>  $ids  media ids in the new display order
     */
    public function reorderMedia(array $ids): void
    {
        if (! $this->listing) { return; }
        app(ListingComposerService::class)->reorder($this->listing, $ids);
        $this->refreshMedia();
    }

    protected function ensureDraftListing(): void
    {
        if ($this->listing) { return; }
        $this->listing = app(ListingComposerService::class)->createDraft(Auth::user(), $this->fields());
    }

    /** Public autosave timestamp shown in the UI (ISO string). */
    public ?string $lastAutosavedAt = null;

    /**
     * Silently persist current state to the draft listing. Called by wire:poll.
     * Only saves when the user has meaningful content to avoid creating empty drafts.
     */
    public function autosaveDraft(): void
    {
        $composer = app(ListingComposerService::class);
        // Don't autosave once the listing has been published.
        if ($this->listing && $this->listing->status?->value !== 'draft') {
            return;
        }
        // Don't create a draft until there's something worth saving.
        if (empty($this->photos) && ! $composer->worthSaving($this->fields(), $this->listing)) {
            return;
        }

        $this->ensureDraftListing();
        $composer->autosave($this->listing, $this->fields());

        $this->lastAutosavedAt = now()->toIso8601String();
    }

    public function addTag(): void
    {
        $t = trim($this->tagInput);
        if ($t && ! in_array($t, $this->tags) && count($this->tags) < 10) {
            $this->tags[] = $t;
        }
        $this->tagInput = '';
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_filter($this->tags, fn ($t) => $t !== $tag));
    }

    public function publish(): void
    {
        $composer = app(ListingComposerService::class);
        $this->validate($this->publishRules());

        // Facebook requires at least one photo to publish.
        $mediaCount = count($this->mediaItems);
        if ($this->listing) {
            $mediaCount = max($mediaCount, $this->listing->media()->count());
        }
        if ($mediaCount < 1) {
            $this->addError('photos', __('Add at least one photo before publishing.'));
            return;
        }

        $this->ensureDraftListing();
        $wasEdit = $this->listing->status !== ListingStatus::Draft;
        $status = $composer->publish($this->listing, $this->fields());

        session()->flash('marketplace_flash', $composer->publishedMessage($status, $wasEdit));

        $this->redirectRoute('marketplace.show', ['slug' => $this->listing->slug]);
    }

    public function categories()
    {
        return MarketplaceCategory::active()->orderBy('parent_id')->orderBy('position')->get();
    }

    public function setLocationData(float $lat, float $lng, array $addressData, string $label): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->locationLabel = $label;
        
        if (isset($addressData['country_code'])) {
            $this->country = strtoupper($addressData['country_code']);
        }
        $this->region = $addressData['state'] ?? ($addressData['region'] ?? '');
        $this->city = $addressData['city'] ?? ($addressData['town'] ?? ($addressData['village'] ?? ''));
        $this->neighborhood = $addressData['suburb'] ?? ($addressData['neighbourhood'] ?? '');
    }

    public function conditionOptions(): array
    {
        return array_map(fn ($c) => ['v' => $c->value, 'l' => $c->label(), 'fr' => $c->labelFr()], ListingCondition::cases());
    }
    public function priceTypeOptions(): array
    {
        return array_map(fn ($c) => ['v' => $c->value, 'l' => $c->label(), 'fr' => $c->labelFr()], ListingPriceType::cases());
    }
    public function fulfillmentOptions(): array
    {
        return array_map(fn ($c) => ['v' => $c->value, 'l' => $c->label(), 'fr' => $c->labelFr(), 'icon' => $c->icon()], ListingFulfillment::cases());
    }
    public function visibilityOptions(): array
    {
        return array_map(fn ($c) => ['v' => $c->value, 'l' => $c->label(), 'fr' => $c->labelFr(), 'icon' => $c->icon()], ListingVisibility::cases());
    }

    public function render()
    {
        return view('livewire.marketplace.listing-composer');
    }

    public function toggleFulfillment(string $val): void
    {
        if (in_array($val, $this->fulfillment)) {
            $this->fulfillment = array_values(array_diff($this->fulfillment, [$val]));
        } else {
            $this->fulfillment[] = $val;
        }
    }
}

