<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConnection extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_BLOCKED  = 'blocked';

    protected $fillable = [
        'tenant_id',
        'user_a_id',
        'user_b_id',
        'requested_by',
        'status',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Canonical ordering of a user pair (smaller id first). */
    public static function canonicalPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    /** Find the connection record between two users, regardless of order. */
    public static function between(int $a, int $b): ?self
    {
        if ($a === $b) {
            return null;
        }
        [$x, $y] = static::canonicalPair($a, $b);
        return static::where('user_a_id', $x)->where('user_b_id', $y)->first();
    }

    public function otherUserId(int $viewerId): int
    {
        return $this->user_a_id === $viewerId ? $this->user_b_id : $this->user_a_id;
    }
}
