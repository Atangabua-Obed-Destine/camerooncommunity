<?php

namespace App\Models;

use App\Enums\NotificationPref;
use App\Enums\RoomMemberRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YardRoomMember extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'room_id',
        'user_id',
        'role',
        'joined_at',
        'last_read_at',
        'last_seen_message_id',
        'is_muted',
        'is_favorited',
        'muted_until',
        'notification_pref',
        'auto_archived_at',
        'notification_pref_before_archive',
    ];

    protected function casts(): array
    {
        return [
            'role' => RoomMemberRole::class,
            'notification_pref' => NotificationPref::class,
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
            'muted_until' => 'datetime',
            'auto_archived_at' => 'datetime',
            'is_muted' => 'boolean',
            'is_favorited' => 'boolean',
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
