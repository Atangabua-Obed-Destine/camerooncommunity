<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSavedSearch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'name', 'filters',
        'notify_email', 'notify_push', 'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
