<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RidePassenger extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'ride_id',
        'user_id',
        'seats_booked',
        'status',
    ];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
