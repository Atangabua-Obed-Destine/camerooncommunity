<?php

namespace App\Models;

use App\Enums\BadgeType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBadge extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'badge_type',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'badge_type' => BadgeType::class,
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
