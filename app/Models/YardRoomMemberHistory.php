<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only log of group exits — one row per leave or removal event.
 * Powers the "Past members" view in group info.
 */
class YardRoomMemberHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'yard_room_member_history';

    protected $fillable = [
        'room_id',
        'user_id',
        'removed_by',
        'reason',
        'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'exited_at' => 'datetime',
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

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
