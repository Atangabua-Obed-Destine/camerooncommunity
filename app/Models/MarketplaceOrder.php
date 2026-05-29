<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplaceOrder extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'listing_id', 'buyer_id', 'seller_id',
        'reference', 'status', 'provider', 'provider_ref',
        'amount', 'currency', 'quantity',
        'buyer_note', 'seller_note',
        'paid_at', 'released_at', 'cancelled_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'status'       => OrderStatus::class,
        'paid_at'      => 'datetime',
        'released_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $o) {
            $o->reference ??= self::generateReference();
        });
    }

    public static function generateReference(): string
    {
        // GM-XXX-YYYY  (GM = GoMarket, 7 base32 chars total)
        $part1 = strtoupper(Str::random(3));
        $part2 = strtoupper(Str::random(4));
        return "GM-$part1-$part2";
    }

    public function listing(): BelongsTo { return $this->belongsTo(MarketplaceListing::class, 'listing_id'); }
    public function buyer(): BelongsTo   { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller(): BelongsTo  { return $this->belongsTo(User::class, 'seller_id'); }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where(fn ($w) => $w->where('buyer_id', $userId)->orWhere('seller_id', $userId));
    }

    public function isBuyer(int $userId): bool  { return (int) $this->buyer_id === $userId; }
    public function isSeller(int $userId): bool { return (int) $this->seller_id === $userId; }

    public function formattedAmount(): string
    {
        $cur = $this->currency ?: 'XAF';
        return number_format((float) $this->amount, 0, '.', ',') . ' ' . $cur;
    }
}
