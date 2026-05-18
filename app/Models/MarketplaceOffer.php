<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplaceOffer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'marketplace_offers';

    protected $fillable = [
        'uuid', 'listing_id', 'buyer_id', 'seller_id',
        'amount', 'currency', 'message', 'status',
        'counter_amount', 'parent_offer_id',
        'responded_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'counter_amount' => 'decimal:2',
            'status'         => OfferStatus::class,
            'responded_at'   => 'datetime',
            'expires_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $offer) {
            if (! $offer->uuid) {
                $offer->uuid = (string) Str::uuid();
            }
            if (! $offer->expires_at) {
                $offer->expires_at = now()->addDays(7);
            }
        });
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_offer_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status === OfferStatus::Pending;
    }

    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, '.', ',') . ' ' . $this->currency;
    }
}
