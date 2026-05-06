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
     * 
     * When UK is detected with generic "England" region, uses coordinates to
     * reverse-geocode to a specific ITL region via Nominatim.
     */
    public function handleUserLocation(User $user, string $country, string $city, ?string $region = null, ?float $lat = null, ?float $lng = null): array
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
        // If we get "England" as the region, try to reverse-geocode with coordinates.
        if (in_array($country, ['United Kingdom', 'UK', 'Great Britain'], true)) {
            // First try normal normalization with what we already have
            $normalized = $this->normalizeUkRegion($city, $region);

            // If that failed and we have coordinates, ask Nominatim for richer
            // location candidates (city / state_district / county) and try each.
            if (! $normalized && $lat && $lng) {
                $candidates = $this->reverseGeocodeCandidates($lat, $lng);
                foreach ($candidates as $candidate) {
                    $normalized = $this->normalizeUkRegion($candidate, $region);
                    if ($normalized) {
                        $city = $candidate;
                        break;
                    }
                }
            }

            $region = $normalized;
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
     * Reverse-geocode coordinates to a list of candidate location strings
     * (city, state_district, county) using Nominatim. Returns an ordered
     * array of candidates to try when normalising to an ITL region.
     *
     * Nominatim returns names like "City of Westminster" or
     * "City of Edinburgh", which won't match plain "westminster" /
     * "edinburgh" entries in our map, so we also include a "City of"-stripped
     * variant for each candidate.
     */
    private function reverseGeocodeCandidates(float $lat, float $lng): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->withUserAgent('CameroonCommunity/1.0')
                ->get(
                    'https://nominatim.openstreetmap.org/reverse',
                    [
                        'lat' => $lat,
                        'lon' => $lng,
                        'format' => 'json',
                        'accept-language' => 'en'
                    ]
                );

            if (! $response->successful()) {
                return [];
            }

            $address = $response->json('address') ?? [];
            $raw = array_filter([
                $address['city']           ?? null,
                $address['town']           ?? null,
                $address['village']        ?? null,
                $address['suburb']         ?? null,
                $address['state_district'] ?? null,
                $address['county']         ?? null,
            ]);

            $candidates = [];
            foreach ($raw as $value) {
                $candidates[] = $value;
                // Strip leading "City of " prefix (e.g. "City of Westminster" => "Westminster")
                if (Str::startsWith(Str::lower($value), 'city of ')) {
                    $candidates[] = trim(substr($value, 8));
                }
            }

            \Illuminate\Support\Facades\Log::debug('Nominatim reverse-geocode candidates', [
                'lat' => $lat,
                'lng' => $lng,
                'address' => $address,
                'candidates' => $candidates,
            ]);

            return array_values(array_unique($candidates));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug('Nominatim reverse-geocoding failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Reverse-geocode coordinates to a single city name (legacy helper).
     */
    private function reverseGeocodeToCity(float $lat, float $lng): string
    {
        $candidates = $this->reverseGeocodeCandidates($lat, $lng);
        return $candidates[0] ?? '';
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
