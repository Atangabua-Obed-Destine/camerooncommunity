<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class YardCall extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'room_id',
        'initiated_by',
        'call_type',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $call) {
            $call->uuid ??= Str::uuid();
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(YardRoom::class, 'room_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(YardCallParticipant::class, 'call_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->where('status', 'joined');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['ringing', 'active']);
    }

    public function end(): void
    {
        $duration = 0;
        if ($this->started_at) {
            $diff = (int) now()->diffInSeconds($this->started_at);
            $duration = max(0, $diff);
        }

        $this->update([
            'status' => 'ended',
            'ended_at' => now(),
            'duration_seconds' => $duration,
        ]);

        $this->participants()
            ->whereIn('status', ['ringing', 'joined'])
            ->update(['status' => 'left', 'left_at' => now()]);
    }
}
