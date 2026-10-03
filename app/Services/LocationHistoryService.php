<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLocationHistory;

/**
 * Appends a member's position to their trail, with the rules in one place so
 * every caller behaves the same.
 *
 * The location tracker re-detects every ten minutes, on tab focus and on a
 * manual press, so writing a row per report would fill the table with a
 * stationary phone saying the same thing. Only a real move, or a long enough
 * gap, is worth recording.
 */
class LocationHistoryService
{
    /** Two points closer than this are the same place (~200 m). */
    private const SAME_PLACE_DEGREES = 0.002;

    /** Record again after this long even if the member has not moved. */
    private const QUIET_MINUTES = 15;

    /**
     * @param  array{lat: float|string|null, lng: float|string|null, country?: ?string, region?: ?string, city?: ?string}  $point
     */
    public function record(User $user, array $point, string $source = 'gps', ?string $ip = null): ?UserLocationHistory
    {
        $lat = isset($point['lat']) ? (float) $point['lat'] : null;
        $lng = isset($point['lng']) ? (float) $point['lng'] : null;

        // A trail of nulls helps nobody.
        if ($lat === null || $lng === null || ($lat === 0.0 && $lng === 0.0)) {
            return null;
        }

        if ($this->isRepeatOf($user, $lat, $lng)) {
            return null;
        }

        return UserLocationHistory::create([
            'tenant_id'  => $user->tenant_id,
            'user_id'    => $user->id,
            'lat'        => $lat,
            'lng'        => $lng,
            'country'    => $point['country'] ?? $user->current_country,
            'region'     => $point['region']  ?? $user->current_region,
            'city'       => $point['city']    ?? $user->current_city,
            'source'     => $source,
            'ip_address' => $ip,
        ]);
    }

    /** True when the last point is recent and close enough to be the same stop. */
    private function isRepeatOf(User $user, float $lat, float $lng): bool
    {
        $last = UserLocationHistory::where('user_id', $user->id)
            ->latest('created_at')
            ->first(['lat', 'lng', 'created_at']);

        if (! $last || ! $last->created_at) {
            return false;
        }

        if ($last->created_at->lt(now()->subMinutes(self::QUIET_MINUTES))) {
            return false;
        }

        return abs((float) $last->lat - $lat) < self::SAME_PLACE_DEGREES
            && abs((float) $last->lng - $lng) < self::SAME_PLACE_DEGREES;
    }
}
