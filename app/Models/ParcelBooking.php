<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelBooking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'uuid',
        'trip_id',
        'sender_id',
        'kg_requested',
        'total_price',
        'currency',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'kg_requested' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(ParcelTrip::class, 'trip_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
