<?php

namespace Database\Factories;

use App\Enums\RoomType;
use App\Models\Tenant;
use App\Models\YardRoom;
use App\Services\RoomNamingService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<YardRoom> */
class YardRoomFactory extends Factory
{
    protected $model = YardRoom::class;

    public function definition(): array
    {
        $city = fake()->city();
        $name = RoomNamingService::city($city);

        return [
            'tenant_id' => Tenant::first()?->id ?? Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'country' => 'United Kingdom',
            'city' => $city,
            'room_type' => RoomType::City,
            'is_active' => true,
            'is_system_room' => true,
            'members_count' => 0,
            'messages_count' => 0,
        ];
    }

    public function national(string $country = 'United Kingdom'): static
    {
        return $this->state(fn () => [
            'name' => RoomNamingService::national($country),
            'slug' => Str::slug(RoomNamingService::shortCountry($country) . '-kamer'),
            'room_type' => RoomType::National,
            'country' => $country,
            'city' => null,
        ]);
    }

    public function privateGroup(): static
    {
        return $this->state(fn () => [
            'room_type' => RoomType::PrivateGroup,
            'is_system_room' => false,
        ]);
    }
}
