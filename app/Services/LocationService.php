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
    public function handleUserLocation(User $user, string $country, string $city, ?string $region = null, ?float $lat = null, ?float $lng = null, ?string $isoRegion = null): array
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
            [$region, $city] = $this->ukRegionAndCity($city, $region, $lat, $lng);
        }

        // Everyone else. The geocoder answered with a country but no region:
        // Nominatim gives no 'state' at all for China's municipalities, so a
        // user in Beijing arrived here with country "China" and nothing else,
        // and a blank region means no regional room is ever created for them.
        //
        // Left until last so the UK keeps its own mapping, which deliberately
        // returns null when a place is outside the twelve ITL1 regions.
        if (! $region) {
            $region = $this->resolveRegionName($isoRegion)
                ?: (($lat && $lng) ? $this->resolveRegionFromCoordinates($lat, $lng) : null);
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
    /**
     * The ITL1 region (London, South East, …) for a UK location, or null. Pure:
     * no user, no save. The register wizard shows this before the account
     * exists, so what is shown is what is saved.
     */
    public function normaliseUkRegion(string $city, ?string $region, ?float $lat = null, ?float $lng = null): ?string
    {
        return $this->ukRegionAndCity($city, $region, $lat, $lng)[0];
    }

    /** @return array{0: ?string, 1: string} the region, and the city that matched */
    private function ukRegionAndCity(string $city, ?string $region, ?float $lat, ?float $lng): array
    {
        // First try normal normalization with what we already have
        $normalized = $this->normalizeUkRegion($city, $region);

        // If that failed and we have coordinates, ask Nominatim for richer
        // location candidates (city / state_district / county) and try each.
        if (! $normalized && $lat && $lng) {
            foreach ($this->reverseGeocodeCandidates($lat, $lng) as $candidate) {
                $normalized = $this->normalizeUkRegion($candidate, $region);
                if ($normalized) {
                    $city = $candidate;
                    break;
                }
            }
        }

        return [$normalized, $city];
    }

    /**
     * The region a signup shows and saves, from what the geocoder said. UK
     * regions become ITL1 ("London", not "England"): the city first, because
     * geocoders answer "England" for any English city, then the alias maps,
     * then the coordinates. Elsewhere the region is kept as given.
     */
    public function signupRegion(string $country, string $region, string $city, ?float $lat, ?float $lng): string
    {
        $region = trim($region);
        $city = trim($city);

        if (! in_array($country, Registration::UK_NAMES, true)) {
            return $region;
        }

        $valid = config('cameroon.seeded_regions.GB', []);
        $cityMap = config('cameroon.gb_city_to_region', []);
        $aliasMap = config('cameroon.gb_region_aliases', []);

        if ($city && isset($cityMap[Str::lower($city)])) {
            $region = $cityMap[Str::lower($city)];
        } elseif ($region && ! in_array($region, $valid, true)) {
            $key = Str::lower($region);
            $region = $cityMap[$key] ?? ($aliasMap[$key] ?? $region);
        }

        if (! in_array($region, $valid, true)) {
            try {
                $region = $this->normaliseUkRegion($city, $region ?: null, $lat, $lng) ?? $region;
            } catch (\Throwable $e) {
                logger()->warning('UK region lookup failed: '.$e->getMessage());
            }
        }

        return $region;
    }

    /**
     * Country, region and city for a position, in English, at the zoom the
     * web's register wizard asks Nominatim for (10: city level). Done on the
     * server for the app, so Nominatim sees one identified client (its usage
     * policy asks for that) and what is shown is what will be saved.
     *
     * @return array{country: string, region: string, city: string}|null
     */
    public function placeFor(float $lat, float $lng): ?array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(8)
                ->withUserAgent('CameroonNetwork/1.0 (+https://cameroonnetwork.org)')
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'accept-language' => 'en',
                    'zoom' => 10,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('Nominatim place lookup failed: '.$e->getMessage());

            return null;
        }

        $a = $response->successful() ? ($response->json('address') ?? []) : [];
        if (empty($a['country'])) {
            return null;
        }

        return [
            'country' => (string) $a['country'],
            'region' => (string) ($a['state'] ?? $a['region'] ?? ''),
            'city' => (string) ($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['suburb'] ?? $a['county'] ?? ''),
        ];
    }

    /**
     * Where an IP address is, through ipapi.co, cached for six hours. Private
     * addresses (a dev machine) ask about the server's own public address.
     *
     * @return array{latitude: ?float, longitude: ?float, country_name: ?string, country_code: ?string, region: ?string, city: ?string}|array{error: string}
     */
    public function placeForIp(?string $ip): array
    {
        $ip = $ip ?: '';

        return \Illuminate\Support\Facades\Cache::remember('geo:ip:'.$ip, now()->addHours(6), function () use ($ip) {
            try {
                $private = ! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                $resp = \Illuminate\Support\Facades\Http::timeout(5)
                    ->get($private ? 'https://ipapi.co/json/' : "https://ipapi.co/{$ip}/json/");
                if ($resp->successful()) {
                    $d = $resp->json();

                    return [
                        'latitude' => $d['latitude'] ?? null,
                        'longitude' => $d['longitude'] ?? null,
                        'country_name' => $d['country_name'] ?? null,
                        'country_code' => $d['country_code'] ?? null,
                        'region' => $d['region'] ?? null,
                        'city' => $d['city'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // The caller sees the error payload and falls back.
            }

            return ['error' => 'unavailable'];
        });
    }

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
    /**
     * Map an ISO 3166-2 subdivision code to a readable region name.
     *
     * The last resort, and the only signal present for some places: Nominatim
     * returns ISO3166-2-lvl4 for essentially every address, including the ones
     * where every name key is missing.
     */
    private function resolveRegionName(?string $isoRegion): ?string
    {
        if (! $isoRegion) {
            return null;
        }

        $map = config('cameroon.iso_subdivisions', []);

        return $map[strtoupper(trim($isoRegion))] ?? null;
    }

    /**
     * Ask the geocoder what region a coordinate sits in.
     *
     * Tried in order of how specific the answer is, stopping at the first one
     * that names something. 'city' is deliberately not in the list: for
     * Beijing it reads "Dongcheng District", and a regional room per city
     * district is worse than none at all.
     */
    private function resolveRegionFromCoordinates(float $lat, float $lng): ?string
    {
        $address = $this->reverseGeocodeAddress($lat, $lng);

        if ($address === []) {
            return null;
        }

        foreach (['state', 'region', 'state_district', 'county'] as $key) {
            $value = trim((string) ($address[$key] ?? ''));

            if ($value !== '') {
                return mb_substr($value, 0, 100);
            }
        }

        return $this->resolveRegionName($address['ISO3166-2-lvl4'] ?? null);
    }

    /**
     * One reverse-geocode request, shared by everything that needs an address.
     *
     * @return array<string, string>
     */
    private function reverseGeocodeAddress(float $lat, float $lng): array
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

            return $response->json('address') ?? [];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug('Nominatim reverse-geocoding failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    private function reverseGeocodeCandidates(float $lat, float $lng): array
    {
        $address = $this->reverseGeocodeAddress($lat, $lng);

        if ($address !== []) {
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
