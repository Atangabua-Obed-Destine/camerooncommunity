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

    public int $step = 1;
    public int $maxStep = 5;

    // Step 1: category
    public ?int $categoryId = null;

    // Step 2: photos
    public array $photos = [];
    public array $uploadedMedia = []; // ids of MarketplaceListingMedia
    public array $existingMedia = []; // when editing

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

    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => ['categoryId' => 'required|integer|exists:marketplace_categories,id'],
            2 => ['photos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192'],
            3 => array_merge([
                'title' => 'required|string|min:' . (int) PlatformSetting::getValue('marketplace_min_title_length', 6) . '|max:180',
                'description' => 'required|string|min:' . (int) PlatformSetting::getValue('marketplace_min_description_length', 20) . '|max:5000',
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
            $this->existingMedia = $l->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->thumbnailUrl(),
                'is_cover' => $m->is_cover,
            ])->all();
        }
    }

    public function updatedPhotos(): void
    {
        $this->validate(['photos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192']);
        $maxImages = (int) PlatformSetting::getValue('marketplace_max_images', 10);
        $current = count($this->existingMedia) + count($this->uploadedMedia);
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
            $this->uploadedMedia[] = [
                'id' => $media->id,
                'url' => $media->thumbnailUrl(),
                'is_cover' => $media->is_cover,
            ];
            $i++;
        }
        $this->photos = [];
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
        $this->uploadedMedia = array_values(array_filter($this->uploadedMedia, fn ($m) => $m['id'] !== $mediaId));
        $this->existingMedia = array_values(array_filter($this->existingMedia, fn ($m) => $m['id'] !== $mediaId));
    }

    public function makeCover(int $mediaId): void
    {
        if (! $this->listing) { return; }
        MarketplaceListingMedia::where('listing_id', $this->listing->id)->update(['is_cover' => false]);
        MarketplaceListingMedia::where('id', $mediaId)->update(['is_cover' => true]);
        foreach ($this->uploadedMedia as &$m) { $m['is_cover'] = $m['id'] === $mediaId; }
        foreach ($this->existingMedia as &$m) { $m['is_cover'] = $m['id'] === $mediaId; }
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

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step));
        if ($this->step < $this->maxStep) {
            $this->step++;
        }
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
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
        // Validate every step's rules.
        for ($s = 1; $s <= $this->maxStep; $s++) {
            $this->validate($this->rulesForStep($s));
        }

        $this->ensureDraftListing();

        $autoApprove = filter_var(PlatformSetting::getValue('marketplace_auto_approve', 'true'), FILTER_VALIDATE_BOOL);
        $status = $autoApprove ? ListingStatus::Active : ListingStatus::PendingReview;
        $expiresDays = (int) PlatformSetting::getValue('marketplace_listing_expiry_days', 30);

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
