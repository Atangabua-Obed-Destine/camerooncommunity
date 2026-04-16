<?php

namespace App\Models;

use App\Enums\JoinPromptAction;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YardRoomJoinPrompt extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'user_id',
        'prompted_at',
        'action',
        'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => JoinPromptAction::class,
            'prompted_at' => 'datetime',
            'actioned_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(YardRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
