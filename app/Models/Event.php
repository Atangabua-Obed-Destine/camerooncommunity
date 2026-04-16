<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'cover_photo',
        'event_date',
        'end_date',
        'venue',
        'country',
        'city',
        'latitude',
        'longitude',
        'is_online',
        'online_link',
        'ticket_price',
        'currency',
        'max_attendees',
        'is_published',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'end_date' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_online' => 'boolean',
            'ticket_price' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EventTicket::class, 'event_id');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', now())->where('is_published', true);
    }
}
