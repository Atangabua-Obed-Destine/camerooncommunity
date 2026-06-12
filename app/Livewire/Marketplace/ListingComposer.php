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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
class ListingComposer extends Component
{
    use WithFileUploads;

    public ?MarketplaceListing $listing = null;

    /** Number of rule groups validated on publish (kept for the publish loop). */
    public int $maxStep = 5;

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
    public string $fulfillment = 'pickup';
    public string $country = 'CM';
    public string $region = '';
    public string $city = '';
    public string $neighborhood = '';

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
            'categoryId' => __('category'),
            'priceType'  => __('price type'),
            'title'      => __('title'),
            'price'      => __('price'),
            'condition'  => __('condition'),
            'photos'     => __('photos'),
        ];
    }

    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => ['categoryId' => 'required|integer|exists:marketplace_categories,id'],
            2 => ['photos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192'],
            3 => array_merge([
                'title' => 'required|string|min:' . (int) PlatformSetting::getValue('marketplace_min_title_length', 6) . '|max:180',
                // Description is optional, just like Facebook Marketplace.
                'description' => 'nullable|string|max:5000',
                'priceType' => 'required|in:fixed,negotiable,free,contact',
                'price' => 'nullable|numeric|min:0|max:1000000000|required_if:priceType,fixed,negotiable',
                'currency' => 'required|string|size:3',
                'condition' => 'required|in:new,like_new,good,fair,for_parts',
                'quantity' => 'required|integer|min:1|max:9999',
            ], \App\Support\CategoryAttributeSchema::validationRules(
                \App\Support\CategoryAttributeSchema::forCategory($this->categoryId)
            )),
            4 => [
                'fulfillment' => 'required|in:pickup,local_delivery,diaspora_shippable,digital',
                'country' => 'required|string|size:2',
                'region' => 'nullable|string|max:80',
                'city' => 'nullable|string|max:120',
                'neighborhood' => 'nullable|string|max:160',
            ],
            5 => ['visibility' => 'required|in:public,connections,group'],
            default => [],
        };
    }

    public function mount(MarketplaceListing|int|null $listing = null): void
    {
        $this->currency = (string) PlatformSetting::getValue('marketplace_default_currency', 'XAF');

        $user = Auth::user();
        if ($user) {
            $this->country = $user->current_country_code ?? 'CM';
            $this->region = $user->current_region ?? '';
            $this->city = $user->current_city ?? '';
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
            $this->fulfillment = $l->fulfillment?->value ?? 'pickup';
            $this->country = $l->country ?? 'CM';
            $this->region = $l->region ?? '';
            $this->city = $l->city ?? '';
            $this->neighborhood = $l->neighborhood ?? '';
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
        $this->mediaItems = $this->listing->media()
            ->orderBy('position')->orderBy('id')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->thumbnailUrl(),
                'is_cover' => (bool) $m->is_cover,
            ])->all();
    }

    public function updatedPhotos(): void
    {
        $this->validate(['photos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192']);
        $maxImages = (int) PlatformSetting::getValue('marketplace_max_images', 10);
        $current = count($this->mediaItems);
        $remaining = max(0, $maxImages - $current);

        if ($remaining === 0 || empty($this->photos)) {
            $this->photos = [];
            return;
        }

        // Need a parent listing to attach to; create draft early if needed.
        $this->ensureDraftListing();

        $i = 0;
        foreach ($this->photos as $photo) {
            if ($i >= $remaining) { break; }
            $rawPath = $photo->store('marketplace/' . $this->listing->id, 'public');

            // Optimize + generate thumbnail; gracefully falls back to the original on failure.
            $opt = \App\Support\MarketplaceImageProcessor::process($rawPath);
            $size = Storage::disk('public')->size($opt['path']);

            $media = MarketplaceListingMedia::create([
                'listing_id' => $this->listing->id,
                'type' => 'image',
                'path' => $opt['path'],
                'thumbnail_path' => $opt['thumb'],
                'original_name' => $photo->getClientOriginalName(),
                'size_bytes' => $size,
                'width' => $opt['w'] ?: null,
                'height' => $opt['h'] ?: null,
                'mime_type' => 'image/jpeg',
                'position' => $current + $i,
                'is_cover' => ($current + $i) === 0,
            ]);
            $i++;
        }
        $this->photos = [];
        $this->refreshMedia();
    }

    public function removeMedia(int $mediaId): void
    {
        $media = MarketplaceListingMedia::find($mediaId);
        if (! $media) { return; }
        if (! $this->listing || $media->listing_id !== $this->listing->id) { return; }
        Storage::disk('public')->delete($media->path);
        if ($media->thumbnail_path && $media->thumbnail_path !== $media->path) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }
        $media->delete();

        // If the cover was removed, promote the new first photo (FB behaviour).
        if (! MarketplaceListingMedia::where('listing_id', $this->listing->id)->where('is_cover', true)->exists()) {
            $first = MarketplaceListingMedia::where('listing_id', $this->listing->id)
                ->orderBy('position')->orderBy('id')->first();
            $first?->update(['is_cover' => true]);
        }
        $this->refreshMedia();
    }

    public function makeCover(int $mediaId): void
    {
        if (! $this->listing) { return; }
        MarketplaceListingMedia::where('listing_id', $this->listing->id)->update(['is_cover' => false]);
        MarketplaceListingMedia::where('id', $mediaId)
            ->where('listing_id', $this->listing->id)
            ->update(['is_cover' => true]);
        $this->refreshMedia();
    }

    /**
     * Persist a new photo order from drag-and-drop. The first photo becomes
     * the cover — exactly like Facebook Marketplace.
     *
     * @param  array<int>  $ids  media ids in the new display order
     */
    public function reorderMedia(array $ids): void
    {
        if (! $this->listing) { return; }

        $owned = MarketplaceListingMedia::where('listing_id', $this->listing->id)
            ->pluck('id')->all();
        $ordered = array_values(array_filter(
            array_map('intval', $ids),
            fn ($id) => in_array($id, $owned, true)
        ));
        if (empty($ordered)) { return; }

        foreach ($ordered as $pos => $id) {
            MarketplaceListingMedia::where('id', $id)
                ->where('listing_id', $this->listing->id)
                ->update(['position' => $pos, 'is_cover' => $pos === 0]);
        }
        $this->refreshMedia();
    }

    protected function ensureDraftListing(): void
    {
        if ($this->listing) { return; }
        $this->listing = MarketplaceListing::create([
            'user_id' => Auth::id(),
            'category_id' => $this->categoryId,
            'title' => $this->title ?: 'Untitled draft',
            'description' => $this->description,
            'price_type' => $this->priceType,
            'price' => $this->priceType === 'free' ? 0 : ($this->price ?: null),
            'currency' => $this->currency,
            'condition' => $this->condition,
            'quantity' => $this->quantity,
            'fulfillment' => $this->fulfillment,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'neighborhood' => $this->neighborhood,
            'visibility' => $this->visibility,
            'status' => ListingStatus::Draft->value,
        ]);
    }

    /** Public autosave timestamp shown in the UI (ISO string). */
    public ?string $lastAutosavedAt = null;

    /**
     * Silently persist current state to the draft listing. Called by wire:poll.
     * Only saves when the user has meaningful content to avoid creating empty drafts.
     */
    public function autosaveDraft(): void
    {
        // Don't autosave once the listing has been published.
        if ($this->listing && $this->listing->status?->value !== 'draft') {
            return;
        }
        // Don't create a draft until there's something worth saving.
        $hasContent = mb_strlen(trim((string) $this->title)) >= 3
            || mb_strlen(trim((string) $this->description)) >= 10
            || ! empty($this->photos)
            || ($this->listing && $this->listing->media()->exists());

        if (! $hasContent) {
            return;
        }

        $this->ensureDraftListing();

        $geo = $this->region ? \App\Support\CameroonGeo::centroidForRegion($this->region) : null;

        $this->listing->forceFill(array_filter([
            'category_id' => $this->categoryId,
            'title'       => trim((string) $this->title) ?: $this->listing->title,
            'description' => trim((string) $this->description) ?: $this->listing->description,
            'price_type'  => $this->priceType,
            'price'       => $this->priceType === 'free' ? 0 : ($this->price !== null && $this->price !== '' ? (float) $this->price : null),
            'currency'    => $this->currency,
            'condition'   => $this->condition,
            'quantity'    => $this->quantity,
            'fulfillment' => $this->fulfillment,
            'country'     => $this->country,
            'region'      => $this->region,
            'city'        => $this->city,
            'neighborhood'=> $this->neighborhood,
            'latitude'    => $geo['lat'] ?? null,
            'longitude'   => $geo['lng'] ?? null,
            'visibility'  => $this->visibility,
            'tags'        => array_values($this->tags ?? []),
            'attributes'  => \App\Support\CategoryAttributeSchema::sanitize($this->attrs ?? [], $this->categoryId),
        ], fn ($v) => $v !== null && $v !== ''))->save();

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
        // Validate every rule group.
        for ($s = 1; $s <= $this->maxStep; $s++) {
            $this->validate($this->rulesForStep($s));
        }

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

        $autoApprove = filter_var(PlatformSetting::getValue('marketplace_auto_approve', 'true'), FILTER_VALIDATE_BOOL);
        $status = $autoApprove ? ListingStatus::Active : ListingStatus::PendingReview;
        $expiresDays = (int) PlatformSetting::getValue('marketplace_listing_expiry_days', 30);

        // Geocode the region to a centroid so the listing-detail map is accurate.
        $geo = $this->region ? \App\Support\CameroonGeo::centroidForRegion($this->region) : null;

        $this->listing->fill([
            'category_id' => $this->categoryId,
            'title' => trim($this->title),
            'description' => trim($this->description),
            'price_type' => $this->priceType,
            'price' => $this->priceType === 'free' ? 0 : ($this->price !== null ? (float) $this->price : null),
            'currency' => $this->currency,
            'condition' => $this->condition,
            'quantity' => $this->quantity,
            'fulfillment' => $this->fulfillment,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'neighborhood' => $this->neighborhood,
            'latitude' => $geo['lat'] ?? $this->listing->latitude,
            'longitude' => $geo['lng'] ?? $this->listing->longitude,
            'visibility' => $this->visibility,
            'tags' => array_values($this->tags),
            'attributes' => \App\Support\CategoryAttributeSchema::sanitize($this->attrs, $this->categoryId),
            'status' => $status->value,
            'published_at' => $status === ListingStatus::Active ? now() : null,
            'expires_at' => $status === ListingStatus::Active ? now()->addDays($expiresDays) : null,
        ])->save();

        $this->listing->recomputeRankScore();

        session()->flash('marketplace_flash',
            $status === ListingStatus::Active
                ? 'Your listing is live ✓'
                : 'Submitted for review — you’ll be notified shortly.'
        );

        $this->redirectRoute('marketplace.show', ['slug' => $this->listing->slug]);
    }

    public function categories()
    {
        return MarketplaceCategory::active()->orderBy('parent_id')->orderBy('position')->get();
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
}
