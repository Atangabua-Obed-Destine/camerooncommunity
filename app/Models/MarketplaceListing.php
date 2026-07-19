<?php

namespace App\Models;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Enums\ListingPriceType;
use App\Enums\ListingStatus;
use App\Enums\ListingVisibility;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplaceListing extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'slug', 'user_id', 'buyer_id', 'category_id',
        'title', 'description', 'language',
        'price_type', 'price', 'currency', 'price_min', 'price_max',
        'condition', 'quantity',
        'fulfillment', 'country', 'region', 'city', 'neighborhood', 'latitude', 'longitude',
        'visibility', 'target_regions', 'target_countries', 'tags',
        'status', 'published_at', 'expires_at', 'renewed_at', 'sold_at', 'sold_price', 'sold_currency',
        'bumped_at', 'bump_count',
        'views_count', 'favorites_count', 'messages_count', 'shares_count',
        'rank_score', 'rank_updated_at',
        'is_featured', 'is_verified_seller', 'is_flagged', 'reports_count', 'moderation_meta',
        'attributes', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_type' => ListingPriceType::class,
            'condition' => ListingCondition::class,
            'fulfillment' => 'array',
            'visibility' => ListingVisibility::class,
            'status' => ListingStatus::class,
            'target_regions' => 'array',
            'target_countries' => 'array',
            'tags' => 'array',
            'attributes' => 'array',
            'moderation_meta' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'renewed_at' => 'datetime',
            'sold_at' => 'datetime',
            'bumped_at' => 'datetime',
            'rank_updated_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_verified_seller' => 'boolean',
            'is_flagged' => 'boolean',
            'rank_score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $listing) {
            if (! $listing->uuid) {
                $listing->uuid = (string) Str::uuid();
            }
            if (! $listing->slug) {
                $base = Str::slug($listing->title) ?: 'listing';
                $listing->slug = $base . '-' . Str::lower(Str::random(6));
            }
        });
    }

    // ─── Relationships ───
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MarketplaceListingMedia::class, 'listing_id')->orderBy('position');
    }

    public function cover()
    {
        return $this->hasOne(MarketplaceListingMedia::class, 'listing_id')
            ->where('is_cover', true);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(MarketplaceFavorite::class, 'listing_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(MarketplaceListingView::class, 'listing_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(MarketplaceOffer::class, 'listing_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MarketplaceReview::class, 'listing_id');
    }

    // ─── Scopes ───
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', ListingStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForFeed(Builder $q): Builder
    {
        return $q->active()
            ->where('visibility', ListingVisibility::Public->value)
            ->where(function ($q) { $q->where('is_flagged', false)->orWhereNull('is_flagged'); });
    }

    public function scopeInCategory(Builder $q, int|MarketplaceCategory $cat): Builder
    {
        $id = $cat instanceof MarketplaceCategory ? $cat->id : $cat;
        return $q->where('category_id', $id);
    }

    public function scopeInRegion(Builder $q, ?string $region): Builder
    {
        return $region ? $q->where('region', $region) : $q;
    }

    public function scopeInCountry(Builder $q, ?string $country): Builder
    {
        return $country ? $q->where('country', $country) : $q;
    }

    public function scopeRanked(Builder $q): Builder
    {
        return $q->orderByDesc('is_featured')
            ->orderByDesc('rank_score')
            ->orderByDesc('published_at');
    }

    // ─── Helpers ───
    public function coverUrl(): ?string
    {
        $media = $this->cover ?? $this->media->first();
        return $media ? asset('storage/' . ($media->thumbnail_path ?: $media->path)) : null;
    }

    public function fullCoverUrl(): ?string
    {
        $media = $this->cover ?? $this->media->first();
        return $media ? asset('storage/' . $media->path) : null;
    }

    public function formattedPrice(?string $lang = null): string
    {
        $lang = $lang ?? app()->getLocale();
        $priceType = $this->price_type instanceof ListingPriceType
            ? $this->price_type
            : ListingPriceType::tryFrom((string) $this->price_type);

        if ($priceType === ListingPriceType::Free) {
            return $lang === 'fr' ? 'Gratuit' : 'Free';
        }
        if ($priceType === ListingPriceType::Contact) {
            return $lang === 'fr' ? 'Prix sur demande' : 'Contact for price';
        }
        $currency = $this->currency ?: 'XAF';
        $amount = number_format((float) $this->price, 0, '.', ',');
        $base = "$amount $currency";
        if ($priceType === ListingPriceType::Negotiable) {
            $base .= $lang === 'fr' ? ' (négociable)' : ' (negotiable)';
        }
        return $base;
    }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId && (int) $this->user_id === (int) $userId;
    }

    /**
     * Recompute rank score from denormalized engagement counters.
     * Higher = better. Refreshed on engagement events + nightly job (Phase 2).
     */
    public function recomputeRankScore(): void
    {
        $ageHours = max(1, $this->published_at?->diffInHours(now()) ?? 1);
        $recencyBoost = 1000 / ($ageHours + 24);
        $engagement = ($this->views_count * 0.2)
            + ($this->favorites_count * 6)
            + ($this->messages_count * 8)
            + ($this->shares_count * 4)
            + ($this->is_featured ? 25 : 0);
        $this->rank_score = round($engagement + $recencyBoost, 4);
        $this->rank_updated_at = now();
        $this->saveQuietly();
    }
}
