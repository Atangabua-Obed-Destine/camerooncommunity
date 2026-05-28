<?php

namespace App\Models;

use App\Enums\NotificationPref;
use App\Enums\RoomMemberRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class YardRoomMember extends Model
{
    use BelongsToTenant;

    /**
     * When true, the next ::create() will skip the WhatsApp-style
     * "X joined" system announcement. Used during bulk seeding and
     * when the creator is added as the very first member of a room
     * they just created.
     */
    public static bool $suppressJoinAnnouncement = false;

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
        'archived_at',
        'auto_translate_lang',
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
            'archived_at' => 'datetime',
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

    protected static function booted(): void
    {
        // Always stamp joined_at so the WhatsApp-style history cut-off has
        // something to compare against, even when a caller forgets to set
        // it explicitly (e.g. older code paths that pre-date this feature).
        static::creating(function (YardRoomMember $member) {
            if (empty($member->joined_at)) {
                $member->joined_at = now();
            }
        });

        // WhatsApp-style: announce new members in the room with a public
        // system message ("X joined"). Skipped for DMs, the room creator's
        // own first membership, and any membership flagged for suppression
        // (seeders, programmatic bulk additions).
        static::created(function (YardRoomMember $member) {
            if (self::$suppressJoinAnnouncement) {
                return;
            }

            try {
                $room = $member->room()->first();
                if (! $room) {
                    return;
                }

                // No "X joined" pill in 1:1 conversations.
                if ($room->room_type === \App\Enums\RoomType::DirectMessage) {
                    return;
                }

                // The very first member of a brand-new room is the creator
                // being seeded. Don't post "Creator joined" on their own room.
                if ($room->created_by === $member->user_id) {
                    $otherMembers = self::where('room_id', $room->id)
                        ->where('id', '!=', $member->id)
                        ->exists();
                    if (! $otherMembers) {
                        return;
                    }
                }

                // Avoid double announcements (e.g. when a private-room join
                // request is approved, the approver may also create one).
                $existing = YardMessage::where('room_id', $room->id)
                    ->where('user_id', $member->user_id)
                    ->where('message_type', \App\Enums\MessageType::System->value)
                    ->whereJsonContains('media_metadata->kind', 'member_joined')
                    ->where('created_at', '>=', now()->subMinutes(2))
                    ->exists();
                if ($existing) {
                    return;
                }

                $joiner  = User::find($member->user_id);
                $display = $joiner?->username ?: ($joiner?->name ?: 'A new member');

                $sysMsg = YardMessage::create([
                    'uuid'           => (string) Str::uuid(),
                    'room_id'        => $room->id,
                    'user_id'        => $member->user_id,
                    'message_type'   => \App\Enums\MessageType::System,
                    'content'        => sprintf('%s joined', $display),
                    'media_metadata' => ['kind' => 'member_joined', 'user_id' => $member->user_id],
                ]);

                $room->update([
                    'last_message_at'      => now(),
                    'last_message_preview' => $sysMsg->content,
                    'last_message_user_id' => $member->user_id,
                ]);

                try {
                    broadcast(new \App\Events\MessageSent($sysMsg));
                } catch (\Throwable $e) {
                    \Log::warning('Join broadcast failed: ' . $e->getMessage());
                }
            } catch (\Throwable $e) {
                \Log::warning('Auto join-announcement failed: ' . $e->getMessage());
            }
        });
    }
}
