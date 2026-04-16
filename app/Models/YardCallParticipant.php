<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YardCallParticipant extends Model
{
    protected $fillable = [
        'call_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
        'is_muted',
        'is_video_off',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_muted' => 'boolean',
        'is_video_off' => 'boolean',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(YardCall::class, 'call_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
