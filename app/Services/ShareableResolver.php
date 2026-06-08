<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FoodListing;
use App\Models\HousingListing;
use App\Models\JobListing;
use App\Models\MarketplaceListing;
use App\Models\ParcelTrip;
use App\Models\SolidarityCampaign;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a polymorphic shareable type/id pair to a renderable
 * preview card payload, AND maps a stored Eloquent model back to
 * its share-card "kind" string used by the front-end.
 *
 * One place to add a new shareable surface in the future.
 */
class ShareableResolver
{
    /**
     * Map the front-end "type" string to the underlying model class.
     */
    public const TYPES = [
        'solidarity'  => SolidarityCampaign::class,
        'marketplace' => MarketplaceListing::class,
        'event'       => Event::class,
        'job'         => JobListing::class,
        'housing'     => HousingListing::class,
        'food'        => FoodListing::class,
        'parcel'      => ParcelTrip::class,
    ];

    /**
     * Resolve a (type, id) pair to a model instance, or null if the
     * type is unknown or the row no longer exists.
     */
    public function resolve(string $type, int $id): ?Model
    {
        $class = self::TYPES[$type] ?? null;
        if (!$class) {
            return null;
        }
        return $class::query()->find($id);
    }

    /**
     * Convert a model instance into the canonical "kind" key used in views.
     */
    public function kindFor(Model $model): ?string
    {
        $reverse = array_flip(self::TYPES);
        return $reverse[get_class($model)] ?? null;
    }

    /**
     * Build a normalized preview payload for any supported shareable.
     * Returns null when nothing can be rendered (orphan row).
     */
    public function preview(?Model $model): ?array
    {
        if (!$model) {
            return null;
        }

        $kind = $this->kindFor($model);
        if (!$kind) {
            return null;
        }

        return match ($kind) {
            'solidarity'  => $this->previewSolidarity($model),
            'marketplace' => $this->previewMarketplace($model),
            'event'       => $this->previewEvent($model),
            'job'         => $this->previewJob($model),
            'housing'     => $this->previewHousing($model),
            'food'        => $this->previewFood($model),
            'parcel'      => $this->previewParcel($model),
            default       => null,
        };
    }

    protected function previewSolidarity($m): array
    {
        return [
            'kind'     => 'solidarity',
            'id'       => $m->id,
            'title'    => $m->title ?? 'Solidarity campaign',
            'subtitle' => $m->summary ?? null,
            'image'    => $m->cover_image ?? null,
            'meta'     => [
                'goal'    => $m->goal_amount ?? null,
                'raised'  => $m->raised_amount ?? 0,
                'currency' => $m->currency ?? 'XAF',
            ],
            'url'      => route('solidarity.show', $m->id, false),
            'cta'      => __('Support'),
        ];
    }

    protected function previewMarketplace($m): array
    {
        $location = trim(($m->city ? $m->city . ', ' : '') . ($m->region ?? $m->country ?? '')) ?: null;

        return [
            'kind'        => 'marketplace',
            'id'          => $m->id,
            'title'       => $m->title ?? 'Listing',
            'subtitle'    => $location,
            'image'       => method_exists($m, 'coverUrl') ? $m->coverUrl() : null,
            'price_label' => method_exists($m, 'formattedPrice') ? $m->formattedPrice() : null,
            'meta'        => [
                'price'    => $m->price ?? null,
                'currency' => $m->currency ?? 'XAF',
            ],
            'url'         => $m->slug ? route('marketplace.show', ['slug' => $m->slug], false) : '#',
            'cta'         => __('View'),
        ];
    }

    protected function previewEvent($m): array
    {
        return [
            'kind'     => 'event',
            'id'       => $m->id,
            'title'    => $m->title ?? 'Event',
            'subtitle' => optional($m->starts_at)->format('M j, Y · H:i'),
            'image'    => $m->cover_image ?? null,
            'meta'     => [
                'venue'    => $m->venue ?? null,
                'city'     => $m->city ?? null,
            ],
            'url'      => '#',
            'cta'      => __('RSVP'),
        ];
    }

    protected function previewJob($m): array
    {
        return [
            'kind'     => 'job',
            'id'       => $m->id,
            'title'    => $m->title ?? 'Job',
            'subtitle' => $m->company ?? null,
            'image'    => null,
            'meta'     => [
                'location' => $m->location ?? null,
                'salary'   => $m->salary_range ?? null,
            ],
            'url'      => '#',
            'cta'      => __('Apply'),
        ];
    }

    protected function previewHousing($m): array
    {
        return [
            'kind'     => 'housing',
            'id'       => $m->id,
            'title'    => $m->title ?? 'Housing',
            'subtitle' => $m->location ?? null,
            'image'    => $m->images[0] ?? null,
            'meta'     => [
                'price'    => $m->rent_price ?? $m->price ?? null,
                'currency' => $m->currency ?? 'XAF',
            ],
            'url'      => '#',
            'cta'      => __('View'),
        ];
    }

    protected function previewFood($m): array
    {
        return [
            'kind'     => 'food',
            'id'       => $m->id,
            'title'    => $m->title ?? 'Food',
            'subtitle' => $m->location ?? null,
            'image'    => $m->images[0] ?? null,
            'meta'     => [
                'price' => $m->price ?? null,
            ],
            'url'      => '#',
            'cta'      => __('Order'),
        ];
    }

    protected function previewParcel($m): array
    {
        return [
            'kind'     => 'parcel',
            'id'       => $m->id,
            'title'    => __('Parcel trip'),
            'subtitle' => ($m->origin ?? '?') . ' → ' . ($m->destination ?? '?'),
            'image'    => null,
            'meta'     => [
                'date'    => optional($m->travel_date)->format('M j, Y'),
                'kg_left' => $m->capacity_kg ?? null,
            ],
            'url'      => '#',
            'cta'      => __('Book'),
        ];
    }
}
