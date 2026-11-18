<?php

namespace App\Http\Presenters;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Enums\ListingPriceType;
use App\Enums\ListingStatus;
use App\Enums\ListingVisibility;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Services\Market\ListingComposerService;
use App\Services\Market\MyListingsService;
use App\Support\CategoryAttributeSchema;
use App\Support\StorageUrl;
use App\Support\TrustBadges;

/**
 * The selling side of GoMarket as JSON: the composer's choices and a
 * listing being edited, the seller's own listings, and the member profile.
 * In the member's language; URLs absolute.
 */
class MarketSellPresenter
{
    public function __construct(
        private ListingComposerService $composer,
        private MyListingsService $mine,
        private MarketPresenter $market,
    ) {}

    private function fr(): bool
    {
        return app()->getLocale() === 'fr';
    }

    /** Everything the composer offers (listing-composer.blade.php). */
    public function options(): array
    {
        $fr = $this->fr();
        $all = MarketplaceCategory::active()->orderBy('parent_id')->orderBy('position')->get();
        $category = fn (MarketplaceCategory $c) => array_filter([
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->localizedName(),
            'icon' => $c->icon,
            'fields' => $this->fields(CategoryAttributeSchema::forCategory($c->id)) ?: null,
        ], fn ($v) => $v !== null);

        $preset = fn (string $slug) => $all->firstWhere('slug', $slug)?->id;

        return [
            'categories' => $all->whereNull('parent_id')->values()->map(fn ($root) => $category($root) + [
                'children' => $all->where('parent_id', $root->id)->values()->map($category)->all(),
            ])->all(),
            // Facebook's first screen; vehicle and home preselect their category.
            'listing_types' => [
                ['value' => 'item', 'icon' => '📦', 'category_id' => null,
                    'title' => $fr ? 'Article à vendre' : 'Item for sale',
                    'body' => $fr ? 'Créez une annonce pour un ou plusieurs articles.' : 'Create a single listing for one or more items to sell.'],
                ['value' => 'vehicle', 'icon' => '🚗', 'category_id' => $preset('vehicles'),
                    'title' => $fr ? 'Véhicule à vendre' : 'Vehicle for sale',
                    'body' => $fr ? 'Vendez une voiture, une moto ou autre véhicule.' : 'Sell a car, motorcycle or other vehicle.'],
                ['value' => 'home', 'icon' => '🏠', 'category_id' => $preset('real-estate'),
                    'title' => $fr ? 'Logement à vendre/louer' : 'Home for sale or rent',
                    'body' => $fr ? 'Publiez un bien à vendre ou à louer.' : 'List a property to sell or rent out.'],
            ],
            'conditions' => collect(ListingCondition::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $fr ? $c->labelFr() : $c->label()])->all(),
            'price_types' => collect(ListingPriceType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $fr ? $c->labelFr() : $c->label()])->all(),
            'fulfillments' => collect(ListingFulfillment::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $fr ? $c->labelFr() : $c->label(), 'icon' => $c->icon()])->all(),
            'visibilities' => collect(ListingVisibility::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $fr ? $c->labelFr() : $c->label(), 'icon' => $c->icon()])->all(),
            'currencies' => ListingComposerService::CURRENCIES,
            'default_currency' => $this->composer->defaultCurrency(),
            'max_images' => $this->composer->maxImages(),
            'min_title_length' => $this->composer->minTitleLength(),
        ];
    }

    /** A category's own fields, in the member's language. */
    public function fields(array $schema): array
    {
        $fr = $this->fr();

        return collect($schema)->map(fn ($f) => array_filter([
            'key' => $f['key'],
            'label' => $fr ? ($f['labelFr'] ?? $f['label']) : $f['label'],
            'type' => $f['type'],
            'icon' => $f['icon'] ?? null,
            'suffix' => $f['suffix'] ?? null,
            'help' => $fr ? ($f['helpFr'] ?? $f['help'] ?? null) : ($f['help'] ?? null),
            'options' => isset($f['options'])
                ? collect($f['options'])->map(fn ($o) => ['value' => (string) $o['value'], 'label' => $fr ? ($o['labelFr'] ?? $o['label']) : $o['label']])->all()
                : null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /** A listing in the composer: its fields and photos. */
    public function editable(MarketplaceListing $l): array
    {
        $attributes = is_array($l->attributes) ? $l->attributes : [];

        return [
            'id' => $l->id,
            'slug' => $l->slug,
            'status' => $l->status->value,
            // Not a draft: "Edit item" and "Save changes", no type chooser.
            'is_edit' => $l->status !== ListingStatus::Draft,
            'category_id' => $l->category_id,
            // A draft started from a photo has the placeholder title: show it empty.
            'title' => $l->status === ListingStatus::Draft && $l->title === 'Untitled draft' ? '' : $l->title,
            'description' => (string) ($l->description ?? ''),
            'price_type' => $l->price_type?->value ?? 'fixed',
            'price' => $l->price !== null && ! ($l->price_type?->value === 'free') ? $this->number((float) $l->price) : null,
            'currency' => $l->currency,
            'condition' => $l->condition?->value ?? 'good',
            'quantity' => (int) $l->quantity ?: 1,
            'fulfillment' => array_values(is_array($l->fulfillment) ? $l->fulfillment : array_filter([$l->fulfillment])),
            'country' => $l->country ?? 'CM',
            'region' => (string) ($l->region ?? ''),
            'city' => (string) ($l->city ?? ''),
            'neighborhood' => (string) ($l->neighborhood ?? ''),
            'latitude' => $l->latitude !== null ? (float) $l->latitude : null,
            'longitude' => $l->longitude !== null ? (float) $l->longitude : null,
            'location_label' => collect([$l->neighborhood, $l->city, $l->region, $l->country])->filter()->join(', '),
            'visibility' => $l->visibility?->value ?? 'public',
            'tags' => array_values($l->tags ?? []),
            'attributes' => (object) array_map(fn ($v) => is_bool($v) ? $v : (string) $v, $attributes),
            'media' => $this->composer->mediaItems($l),
        ];
    }

    /** A new listing's starting values: the member's own place and currency. */
    public function blank(User $user): array
    {
        return [
            'currency' => $this->composer->defaultCurrency(),
            'country' => $user->current_country_code ?? 'CM',
            'region' => (string) ($user->current_region ?? ''),
            'city' => (string) ($user->current_city ?? ''),
            'latitude' => $user->current_lat !== null ? (float) $user->current_lat : null,
            'longitude' => $user->current_lng !== null ? (float) $user->current_lng : null,
            'location_label' => collect([$user->current_city, $user->current_region, $user->current_country_code])->filter()->join(', '),
        ];
    }

    /** A card on My listings (my-listings.blade.php). */
    public function mine(MarketplaceListing $l): array
    {
        $fr = $this->fr();
        $wait = $this->mine->bumpWaitMinutes($l);

        return [
            'id' => $l->id,
            'slug' => $l->slug,
            'title' => $l->title,
            'price_label' => $l->formattedPrice(app()->getLocale()),
            'image_url' => $l->coverUrl(),
            'status' => $l->status->value,
            'status_label' => $fr ? $l->status->labelFr() : $l->status->label(),
            'is_featured' => (bool) $l->is_featured,
            'views' => (int) $l->views_count,
            'saves' => (int) $l->favorites_count,
            'inquiries' => (int) $l->messages_count,
            'quantity' => (int) $l->quantity,
            'can_bump' => $wait === 0,
            // "Available in 3 hours", as the disabled Bump says on the web.
            'bump_available' => $wait > 0
                ? ($fr ? 'Disponible ' : 'Available ').$l->bumped_at->copy()->addHours(MyListingsService::BUMP_COOLDOWN_HOURS)->diffForHumans()
                : null,
        ];
    }

    /** A member for the Mark-as-sold buyer picker. */
    public function person(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name ?: $u->username,
            'username' => $u->username,
            'avatar_url' => StorageUrl::for($u->avatar),
        ];
    }

    /**
     * The member profile (seller-profile.blade.php).
     *
     * @param  array{stats: array, community: array, badges: \Illuminate\Support\Collection, state: string, is_following: bool, dm_room_id: ?int}  $s
     */
    public function profile(User $u, User $viewer, array $s): array
    {
        $fr = $this->fr();
        $self = $viewer->id === $u->id;
        // Someone's town is not public: only its owner sees their own city.
        $country = $u->current_country ? config("cameroon.countries.{$u->current_country}", $u->current_country) : null;
        $shownCity = $self ? $u->current_city : null;

        return [
            'id' => $u->id,
            'name' => $u->name ?: $u->username,
            'username' => $u->username,
            'avatar_url' => StorageUrl::for($u->avatar),
            'cover_url' => StorageUrl::for($u->cover_photo),
            'bio' => $u->bio ?: null,
            'joined' => $u->created_at?->translatedFormat('M Y'),
            'joined_long' => $u->created_at?->translatedFormat('F Y'),
            // The header line: "Douala, Cameroon" to its owner, the country to others.
            'location' => trim((($self && $u->current_city) ? $u->current_city.', ' : '').($u->current_country ?? '')) ?: null,
            'from' => $u->home_region ? (($self && $u->home_city) ? $u->home_city.', ' : '').$u->home_region : null,
            'lives_in' => ($shownCity || $country) ? trim($shownCity.($shownCity && $country ? ', ' : '').$country) : null,
            'language' => $u->language_pref ? ($u->language_pref->value === 'fr' ? 'Français' : 'English') : null,
            'is_founding_member' => (bool) $u->is_founding_member,
            'trust_badges' => collect(TrustBadges::forSeller($u))->map(fn ($b) => [
                'icon' => $b['icon'],
                'label' => $fr ? ($b['labelFr'] ?? $b['label']) : $b['label'],
                'tone' => $b['tone'] ?? 'slate',
            ])->values()->all(),
            'badges' => $s['badges']->map(fn ($b) => [
                'icon' => $b->badge_type?->icon() ?? '🏅',
                'label' => (string) $b->badge_type?->label(),
            ])->values()->all(),
            'stats' => $s['stats'],
            'community' => $s['community'] + ['solidarity_live' => (bool) config('cameroon.modules.solidarity_live')],
            'is_self' => $self,
            'connection_state' => $s['state'],
            'is_following' => $s['is_following'],
            'dm_room_id' => $s['dm_room_id'],
        ];
    }

    /** A card on a profile's grid; the owner also sees which aren't live. */
    public function profileCard(MarketplaceListing $l, bool $self): array
    {
        $st = $l->status;

        return $this->market->card($l) + [
            'status_label' => $self && ! in_array($st, [ListingStatus::Active, ListingStatus::Sold], true)
                ? ($this->fr() ? $st->labelFr() : $st->label())
                : null,
        ];
    }

    private function number(float $v): string
    {
        return floor($v) == $v ? (string) (int) $v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
