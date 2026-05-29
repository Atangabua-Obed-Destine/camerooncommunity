<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceReview extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'listing_id', 'seller_id', 'reviewer_id',
        'rating', 'comment', 'is_buyer_verified',
        'reply', 'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_buyer_verified' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $r) => $r->recomputeSellerStats());
        static::deleted(fn (self $r) => $r->recomputeSellerStats());
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Refresh the denormalized rating columns on the seller (users table)
     * so listing cards/profile widgets can render the score without a JOIN.
     */
    public function recomputeSellerStats(): void
    {
        $sellerId = $this->seller_id;
        if (! $sellerId) { return; }

        $stats = static::query()
            ->where('seller_id', $sellerId)
            ->selectRaw('COUNT(*) as c, COALESCE(AVG(rating), 0) as a')
            ->first();

        User::where('id', $sellerId)->update([
            'seller_rating_avg'   => round((float) ($stats->a ?? 0), 2),
            'seller_rating_count' => (int) ($stats->c ?? 0),
        ]);
    }
}
