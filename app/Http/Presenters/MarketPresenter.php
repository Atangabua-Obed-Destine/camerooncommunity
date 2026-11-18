<?php

namespace App\Http\Presenters;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Enums\ListingStatus;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Support\CameroonGeo;
use App\Support\CategoryAttributeSchema;
use App\Support\StorageUrl;
use App\Support\TrustBadges;

/**
 * GoMarket as JSON for the app, in the member's language (the web prints
 * these strings server-side too: formattedPrice, condition and delivery
 * labels, "Listed 3 days ago"). URLs are absolute.
 */
class MarketPresenter
{
    /** The listing card (components/marketplace/listing-card.blade.php). */
    public function card(MarketplaceListing $l, ?float $fromLat = null, ?float $fromLng = null): array
    {
        $lang = app()->getLocale();
        $location = trim(collect([$l->city, $l->region])->filter()->implode(', ')) ?: ($l->country ?: '-');

        $distance = null;
        if ($fromLat !== null && $fromLng !== null && ($c = CameroonGeo::centroidForRegion((string) $l->region))) {
            $km = CameroonGeo::haversineKm($fromLat, $fromLng, $c['lat'], $c['lng']);
            $distance = $km < 1 ? '<1 km' : round($km).' km';
        }

        return [
            'id' => $l->id,
            'slug' => $l->slug,
            'title' => $l->title,
            'price_label' => $l->formattedPrice($lang),
            'image_url' => $l->coverUrl(),
            'location' => $location,
            'distance' => $distance,
            'is_featured' => (bool) $l->is_featured,
            // "Just listed": under two days old and not featured.
            'is_just_listed' => ! $l->is_featured && $l->created_at && $l->created_at->gt(now()->subDays(2)),
            'is_sold' => $l->status === ListingStatus::Sold,
            'category' => $l->category ? ['icon' => $l->category->icon, 'name' => $l->category->localizedName($lang)] : null,
        ];
    }

    public function category(MarketplaceCategory $c, bool $withChildren = true): array
    {
        $lang = app()->getLocale();

        return array_filter([
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->localizedName($lang),
            'icon' => $c->icon,
            'children' => $withChildren
                ? $c->children->where('is_active', true)->sortBy('position')->map(fn ($k) => $this->category($k, false))->values()->all()
                : null,
        ], fn ($v) => $v !== null);
    }

    /** The listing screen (listing-detail.blade.php, phone layout). */
    public function detail(MarketplaceListing $l, ?User $viewer, array $state): array
    {
        $lang = app()->getLocale();
        $fr = $lang === 'fr';
        $seller = $l->seller;
        $isOwner = $l->isOwnedBy($viewer?->id);
        $location = trim(collect([$l->city, $l->region])->filter()->implode(', ')) ?: ($l->country ?: null);

        $condition = $l->condition instanceof ListingCondition ? $l->condition : ListingCondition::tryFrom((string) $l->condition);
        $conditionEmoji = ['new' => '🆕', 'like_new' => '✨', 'good' => '👍', 'fair' => '🆗', 'for_parts' => '🔩'];

        $fulfillment = collect(is_array($l->fulfillment) ? $l->fulfillment : [$l->fulfillment])
            ->map(fn ($v) => $v instanceof ListingFulfillment ? $v : ListingFulfillment::tryFrom((string) $v))
            ->filter()
            ->map(fn (ListingFulfillment $f) => ['icon' => $f->icon(), 'label' => $fr ? $f->labelFr() : $f->label()])
            ->values()->all();

        $attributes = [];
        $values = is_array($l->attributes) ? $l->attributes : [];
        foreach (CategoryAttributeSchema::forCategory($l->category_id) as $f) {
            $v = $values[$f['key']] ?? null;
            if ($v !== null && $v !== '' && $v !== false) {
                $attributes[] = [
                    'icon' => $f['icon'] ?? '•',
                    'label' => $fr ? ($f['labelFr'] ?? $f['label']) : $f['label'],
                    'value' => CategoryAttributeSchema::displayValue($f, $v, $lang),
                ];
            }
        }

        $canBuy = ! $isOwner
            && $l->status !== ListingStatus::Sold
            && in_array($l->price_type?->value, ['fixed', 'negotiable'], true)
            && (float) $l->price > 0
            && ! empty($seller?->momo_number);

        return [
            ...$this->card($l),
            'status' => $l->status->value,
            'description' => $l->description,
            'tags' => array_values(array_filter((array) ($l->tags ?? []))),
            // "Listed 3 days ago in Douala, Littoral"
            'listed' => trim(($fr ? 'Publié' : 'Listed').' '.($l->published_at?->diffForHumans() ?? '')
                .($location ? ' '.($fr ? 'à' : 'in').' '.$location : '')),
            'location_label' => $location,
            // The listing page's small map. Rounded to ~1 km: the web draws a
            // 1.2 km circle and says "Location is approximate", so the exact
            // point (often the seller's home) never needs to leave the server.
            'geo' => $l->latitude !== null && $l->longitude !== null ? [
                'lat' => round((float) $l->latitude, 2),
                'lng' => round((float) $l->longitude, 2),
            ] : null,
            'category' => $l->category ? [
                'id' => $l->category->id, 'slug' => $l->category->slug,
                'icon' => $l->category->icon, 'name' => $l->category->localizedName($lang),
            ] : null,
            'fulfillment' => $fulfillment,
            'condition' => $condition ? [
                'value' => $condition->value,
                'emoji' => $conditionEmoji[$condition->value] ?? '',
                'label' => $fr ? $condition->labelFr() : $condition->label(),
            ] : null,
            'attributes' => $attributes,
            'gallery' => $l->media->map(fn ($m) => ['url' => $m->url(), 'thumb_url' => $m->thumbnailUrl()])->values()->all(),
            'seller' => $seller ? [
                'id' => $seller->id,
                'name' => $seller->name,
                'username' => $seller->username,
                'avatar_url' => StorageUrl::for($seller->avatar),
                'is_verified' => (bool) $l->is_verified_seller,
                'rating_avg' => $seller->seller_rating_count > 0 ? round((float) $seller->seller_rating_avg, 1) : null,
                'rating_count' => (int) ($seller->seller_rating_count ?? 0),
                'joined' => $seller->created_at?->translatedFormat('M Y'),
                'is_new' => ! $isOwner && TrustBadges::isNewSeller($seller),
                'badges' => collect(TrustBadges::forSeller($seller))->map(fn ($b) => [
                    'icon' => $b['icon'],
                    'label' => $fr ? ($b['labelFr'] ?? $b['label']) : $b['label'],
                    'tone' => $b['tone'] ?? 'slate',
                ])->values()->all(),
            ] : null,
            'stats' => [
                'views' => (int) $l->views_count,
                'saves' => (int) $l->favorites_count,
                'inquiries' => (int) $l->messages_count,
            ],
            'is_owner' => $isOwner,
            'can_buy' => $canBuy,
            'default_inquiry' => $state['default_inquiry'],
            'is_favorited' => $state['is_favorited'],
            'has_messaged' => $state['has_messaged'],
            'has_reported' => $state['has_reported'],
            'is_following' => $state['is_following'],
            'similar' => $state['similar'],
        ];
    }
}
