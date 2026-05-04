<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UserContactName extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'owner_user_id',
        'contact_user_id',
        'nickname',
    ];

    /**
     * Resolve the nickname `$ownerId` has assigned to `$contactId`, or null.
     * Cached per (owner, contact) for the request lifetime.
     */
    public static function nickname(int $ownerId, int $contactId): ?string
    {
        $key = "ucn.{$ownerId}.{$contactId}";

        return Cache::driver('array')->remember($key, 60, function () use ($ownerId, $contactId) {
            return static::where('owner_user_id', $ownerId)
                ->where('contact_user_id', $contactId)
                ->value('nickname');
        });
    }

    /**
     * Bulk-load nicknames `$ownerId` has assigned to a list of contact ids.
     * Returns [contact_id => nickname].
     */
    public static function nicknamesFor(int $ownerId, array $contactIds): array
    {
        if (empty($contactIds)) return [];

        return static::where('owner_user_id', $ownerId)
            ->whereIn('contact_user_id', $contactIds)
            ->pluck('nickname', 'contact_user_id')
            ->toArray();
    }

    /**
     * Convenience: prefer nickname, fall back to username, then name.
     */
    public static function displayName(int $ownerId, ?User $other): string
    {
        if (! $other) return 'Unknown';
        $nick = static::nickname($ownerId, $other->id);
        return $nick ?: ($other->username ?? $other->name ?? 'Unknown');
    }
}
