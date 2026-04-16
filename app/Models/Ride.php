<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ride extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'origin',
        'destination',
        'departure_time',
        'seats_available',
        'price_per_seat',
        'currency',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'price_per_seat' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(RidePassenger::class, 'ride_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('departure_time', '>=', now());
    }
}
