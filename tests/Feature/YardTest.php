<?php

namespace Tests\Feature;

use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class YardTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_guest_cannot_access_yard(): void
    {
        $response = $this->get('/yard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_yard(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/yard');
        $response->assertStatus(200);
    }

    public function test_user_can_view_room_they_belong_to(): void
    {
        $user = $this->createUser();
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        YardRoomMember::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $response = $this->actingAs($user)->get("/yard/room/{$room->slug}");
        $response->assertStatus(200);
    }

    public function test_user_cannot_view_room_they_do_not_belong_to(): void
    {
        $user = $this->createUser();
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($user)->get("/yard/room/{$room->slug}");
        $response->assertStatus(403);
    }

    public function test_user_can_join_system_room(): void
    {
        $user = $this->createUser();
        $room = YardRoom::factory()->national()->create([
            'tenant_id' => $this->tenant->id,
            'is_system_room' => true,
        ]);

        $response = $this->actingAs($user)->post("/yard/room/{$room->id}/join");
        $response->assertRedirect();

        $this->assertDatabaseHas('yard_room_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_join_private_room_via_join_endpoint(): void
    {
        $user = $this->createUser();
        $room = YardRoom::factory()->privateGroup()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($user)->post("/yard/room/{$room->id}/join");
        $response->assertStatus(403);
    }

    public function test_joining_same_room_twice_does_not_duplicate(): void
    {
        $user = $this->createUser();
        $room = YardRoom::factory()->national()->create([
            'tenant_id' => $this->tenant->id,
            'is_system_room' => true,
            'members_count' => 0,
        ]);

        $this->actingAs($user)->post("/yard/room/{$room->id}/join");
        $this->actingAs($user)->post("/yard/room/{$room->id}/join");

        $this->assertEquals(1, YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->count());
    }
}
