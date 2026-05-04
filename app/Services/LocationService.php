<?php

namespace App\Services;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\RoomNamingService;
use Illuminate\Support\Str;

class LocationService
{
    /**
     * Reverse-geocode coordinates to country & city.
     * Stub — will integrate a geocoding provider later.
     */
    public function reverseGeocode(float $lat, float $lng): array
    {
        // TODO: integrate Google Geocoding / OpenCage / Nominatim
        return [
            'country' => null,
            'city' => null,
            'country_code' => null,
        ];
    }

    /**
     * Process a user's detected location: update profile, ensure rooms exist.
     * Does NOT auto-join the user — rooms are presented for explicit opt-in.
     */
    public function handleUserLocation(User $user, string $country, string $city, ?string $region = null): array
    {
        $countryChanged = $user->current_country !== $country;

        // For Cameroon users, derive region from home_region if not provided
        if (! $region && $country === 'Cameroon' && $user->home_region) {
            $regions = config('cameroon.regions', []);
            $region = $regions[$user->home_region] ?? $user->home_region;
        }

        // Normalize UK regions: route to one of the 12 ITL1 regions
        // (London, South East, …, Scotland, Wales, Northern Ireland) using the
        // city → region map first, then the county/country alias map.
        if (in_array($country, ['United Kingdom', 'UK', 'Great Britain'], true)) {
            $region = $this->normalizeUkRegion($city, $region);
        }

        $user->updateQuietly([
            'current_country' => $country,
            'current_city' => $city,
            'current_region' => $region,
        ]);

        // Ensure national room exists (but don't join)
        $nationalRoom = YardRoom::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'room_type' => RoomType::National,
                'country' => $country,
            ],
            [
                'name' => RoomNamingService::national($country),
                'slug' => Str::slug(RoomNamingService::shortCountry($country) . '-kamer'),
                'description' => RoomNamingService::nationalDescription($country),
                'is_active' => true,
                'is_system_room' => true,
                'members_count' => 0,
            ],
        );

        // Ensure regional room exists (but don't join)
        $regionalRoom = null;
        if ($region) {
            $regionalRoom = YardRoom::firstOrCreate(
                [
                    'tenant_id' => $user->tenant_id,
                    'room_type' => RoomType::Regional,
                    'country' => $country,
                    'region' => $region,
                ],
                [
                    'name' => RoomNamingService::regional($region),
                    'slug' => Str::slug("{$region}-kamer-" . RoomNamingService::shortCountry($country)),
                    'description' => RoomNamingService::regionalDescription($region, $country),
                    'is_active' => true,
                    'is_system_room' => true,
                    'members_count' => 0,
                ],
            );
        }

        return [
            'country_changed' => $countryChanged,
            'national_room_id' => $nationalRoom->id,
            'regional_room_id' => $regionalRoom?->id,
        ];
    }

    /**
     * Explicitly join a user to a room (called when user chooses to join).
     */
    public function joinRoom(User $user, YardRoom $room): void
    {
        // Private groups require admin approval — don't auto-join
        if ($room->is_private) {
            return;
        }

        YardRoomMember::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
        ], [
            'role' => 'member',
        ]);

        $room->increment('members_count');
    }

    /**
     * Normalize a UK location to one of the 12 official ITL1 regions
     * (London, South East, South West, East of England, East Midlands,
     * West Midlands, Yorkshire and the Humber, North West, North East,
     * Scotland, Wales, Northern Ireland).
     *
     * Resolution order:
     *   1. If `$detectedRegion` is already a valid ITL1 region → keep it.
     *   2. Look up the city in `gb_city_to_region`.
     *   3. Look up the detected region/county/country in `gb_region_aliases`.
     *   4. Otherwise return null (user lands in national room only).
     */
    private function normalizeUkRegion(string $city, ?string $detectedRegion): ?string
    {
        $valid = config('cameroon.seeded_regions.GB', []);

        // 1. Already a valid ITL1 region?
        if ($detectedRegion && in_array($detectedRegion, $valid, true)) {
            return $detectedRegion;
        }

        // 2. City lookup
        $cityKey = Str::lower(trim($city));
        $cityMap = config('cameroon.gb_city_to_region', []);
        if ($cityKey !== '' && isset($cityMap[$cityKey])) {
            return $cityMap[$cityKey];
        }

        // 3. Region/county/country alias lookup
        $aliasKey = Str::lower(trim((string) $detectedRegion));
        $aliasMap = config('cameroon.gb_region_aliases', []);
        if ($aliasKey !== '' && array_key_exists($aliasKey, $aliasMap) && $aliasMap[$aliasKey] !== null) {
            return $aliasMap[$aliasKey];
        }

        return null;
    }
}
