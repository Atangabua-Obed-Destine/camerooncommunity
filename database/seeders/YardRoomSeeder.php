<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Models\Tenant;
use App\Models\YardRoom;
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
                    'name' => "Cameroonians in {$name}",
                    'slug' => Str::slug("cameroonians-in-{$name}"),
                    'description' => "The national room for all Cameroonians in {$name}. Automatically joined when you arrive.",
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
                        'name' => "{$region} Cameroonians",
                        'slug' => Str::slug("{$region}-cameroonians-{$countryName}"),
                        'description' => "The regional room for Cameroonians from {$region}, {$countryName}.",
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
                        'name' => "{$city} Cameroonians",
                        'slug' => Str::slug("{$city}-cameroonians"),
                        'description' => "The city room for Cameroonians in {$city}, {$countryName}.",
                        'is_active' => false,
                        'is_system_room' => true,
                        'members_count' => 0,
                    ],
                );
            }
        }
    }
}
