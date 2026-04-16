<?php

namespace App\Services;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
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
                'name' => "Cameroonians in {$country}",
                'slug' => Str::slug("cameroonians-in-{$country}"),
                'description' => "The national room for all Cameroonians in {$country}.",
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
                    'name' => "{$region} Cameroonians",
                    'slug' => Str::slug("{$region}-cameroonians-{$country}"),
                    'description' => "The regional room for Cameroonians from {$region}, {$country}.",
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
}
