<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Models\Tenant;
use App\Models\YardRoom;
use App\Services\RoomNamingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class YardRoomSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('domain', 'camerooncommunity.net')->first();

        if (! $tenant) {
            return;
        }

        app()->instance('currentTenant', $tenant);

        $countries = config('cameroon.seeded_countries');
        $cities = config('cameroon.seeded_cities');
        $regions = config('cameroon.seeded_regions', []);

        // Create National Rooms for every seeded country
        foreach ($countries as $code => $name) {
            YardRoom::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'room_type' => RoomType::National,
                    'country' => $name,
                ],
                [
                    'name' => RoomNamingService::national($name),
                    'slug' => Str::slug(RoomNamingService::shortCountry($name) . '-kamer'),
                    'description' => RoomNamingService::nationalDescription($name) . ' Automatically joined when you arrive.',
                    'country' => $name,
                    'is_active' => true,
                    'is_system_room' => true,
                    'members_count' => 0,
                ],
            );
        }

        // Create Regional Rooms for seeded regions per country
        foreach ($regions as $countryCode => $regionNames) {
            $countryName = $countries[$countryCode] ?? $countryCode;

            foreach ($regionNames as $region) {
                YardRoom::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'room_type' => RoomType::Regional,
                        'country' => $countryName,
                        'region' => $region,
                    ],
                    [
                        'name' => RoomNamingService::regional($region),
                        'slug' => Str::slug("{$region}-kamer-" . RoomNamingService::shortCountry($countryName)),
                        'description' => RoomNamingService::regionalDescription($region, $countryName),
                        'is_active' => true,
                        'is_system_room' => true,
                        'members_count' => 0,
                    ],
                );
            }
        }

        // Create City Rooms for pre-seeded cities
        foreach ($cities as $countryCode => $cityNames) {
            $countryName = $countries[$countryCode] ?? $countryCode;

            foreach ($cityNames as $city) {
                YardRoom::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'room_type' => RoomType::City,
                        'country' => $countryName,
                        'city' => $city,
                    ],
                    [
                        'name' => RoomNamingService::city($city),
                        'slug' => Str::slug("{$city}-kamer"),
                        'description' => RoomNamingService::cityDescription($city, $countryName),
                        'is_active' => false,
                        'is_system_room' => true,
                        'members_count' => 0,
                    ],
                );
            }
        }
    }
}
