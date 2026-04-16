<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParcelTrip extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'origin_country',
        'origin_city',
        'destination_country',
        'destination_city',
        'travel_date',
        'max_kg',
        'price_per_kg',
        'currency',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'max_kg' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ParcelBooking::class, 'trip_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('travel_date', '>=', now());
    }
}
