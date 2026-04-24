<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YardMessage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'uuid',
        'room_id',
        'user_id',
        'parent_message_id',
        'message_type',
        'content',
        'media_path',
        'media_thumbnail',
        'media_original_name',
        'media_size',
        'media_metadata',
        'translated_content',
        'is_edited',
        'edited_at',
        'is_deleted',
        'is_flagged',
        'flag_reason',
        'ai_moderation_score',
        'ai_moderation_detail',
        'reactions_count',
        'reply_count',
        'is_pinned',
        'pinned_at',
        'pinned_by',
        'is_forwarded',
        'solidarity_campaign_id',
    ];

    protected function casts(): array
    {
        return [
            'message_type' => MessageType::class,
            'media_metadata' => 'array',
            'translated_content' => 'array',
            'ai_moderation_detail' => 'array',
            'reactions_count' => 'array',
            'is_edited' => 'boolean',
            'is_deleted' => 'boolean',
            'is_flagged' => 'boolean',
            'is_pinned' => 'boolean',
            'edited_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    // ─── Relationships ───

    public function room(): BelongsTo
    {
        return $this->belongsTo(YardRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_message_id');
    }

    public function poll()
    {
        return $this->hasOne(YardPoll::class, 'message_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(YardMessageReaction::class, 'message_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(YardMessageRead::class, 'message_id');
    }

    public function solidarityCampaign(): BelongsTo
    {
        return $this->belongsTo(SolidarityCampaign::class);
    }

    public function pinnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    // ─── Scopes ───

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}
