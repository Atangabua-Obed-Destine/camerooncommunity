<?php

namespace Tests\Unit;

use App\Enums\RoomType;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class LocationServiceTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_handle_user_location_creates_national_room_and_joins(): void
    {
        $user = $this->createUser(['current_country' => null, 'current_city' => null]);

        $service = new LocationService();
        $result = $service->handleUserLocation($user, 'United Kingdom', 'London');

        $this->assertNotNull($result['national_room_id']);

        $nationalRoom = YardRoom::find($result['national_room_id']);
        $this->assertEquals(RoomType::National, $nationalRoom->room_type);
        $this->assertEquals('United Kingdom', $nationalRoom->country);

        // Room is created but user is NOT auto-joined
        $this->assertDatabaseMissing('yard_room_members', [
            'room_id' => $nationalRoom->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_handle_user_location_creates_city_room(): void
    {
        $user = $this->createUser(['current_country' => null, 'current_city' => null]);

        $service = new LocationService();
        $result = $service->handleUserLocation($user, 'United Kingdom', 'Manchester');

        $this->assertNotNull($result['city_room_id']);

        $cityRoom = YardRoom::find($result['city_room_id']);
        $this->assertEquals(RoomType::City, $cityRoom->room_type);
        $this->assertEquals('Manchester', $cityRoom->city);

        // Room is created but user is NOT auto-joined
        $this->assertDatabaseMissing('yard_room_members', [
            'room_id' => $cityRoom->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_join_room_explicitly_adds_membership(): void
    {
        $user = $this->createUser(['current_country' => 'United Kingdom', 'current_city' => 'London']);

        $service = new LocationService();
        $result = $service->handleUserLocation($user, 'United Kingdom', 'London');

        $room = YardRoom::find($result['national_room_id']);
        $service->joinRoom($user, $room);

        $this->assertDatabaseHas('yard_room_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_handle_user_location_reuses_existing_city_room(): void
    {
        $existingUser = $this->createUser();
        $cityRoom = YardRoom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'room_type' => RoomType::City,
            'country' => 'United Kingdom',
            'city' => 'Birmingham',
            'members_count' => 1,
        ]);
        YardRoomMember::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $cityRoom->id,
            'user_id' => $existingUser->id,
            'role' => 'member',
        ]);

        $newUser = $this->createUser(['current_country' => null, 'current_city' => null]);
        $service = new LocationService();
        $result = $service->handleUserLocation($newUser, 'United Kingdom', 'Birmingham');

        $this->assertEquals($cityRoom->id, $result['city_room_id']);
    }

    public function test_country_change_detected(): void
    {
        $user = $this->createUser(['current_country' => 'United Kingdom', 'current_city' => 'London']);

        $service = new LocationService();
        $result = $service->handleUserLocation($user, 'France', 'Paris');

        $this->assertTrue($result['country_changed']);
    }

    public function test_same_country_not_flagged_as_change(): void
    {
        $user = $this->createUser(['current_country' => 'United Kingdom', 'current_city' => 'London']);

        $service = new LocationService();
        $result = $service->handleUserLocation($user, 'United Kingdom', 'Manchester');

        $this->assertFalse($result['country_changed']);
    }
}
