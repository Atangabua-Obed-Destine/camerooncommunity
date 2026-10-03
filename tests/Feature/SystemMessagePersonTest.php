<?php

namespace Tests\Feature;

use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class SystemMessagePersonTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function join(YardRoom $room, $user): void
    {
        YardRoomMember::create([
            'tenant_id' => $this->tenant->id,
            'room_id'   => $room->id,
            'user_id'   => $user->id,
            'role'      => 'member',
            'joined_at' => now(),
        ]);
    }

    public function test_a_joined_notice_opens_that_members_card(): void
    {
        $viewer  = $this->createUser(['username' => 'watcher']);
        $creator = $this->createUser(['username' => 'owner']);
        $joiner  = $this->createUser(['username' => 'newcomer']);

        $room = YardRoom::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $creator->id,
        ]);

        $this->join($room, $creator);
        $this->join($room, $viewer);
        $this->join($room, $joiner);

        $response = $this->actingAs($viewer)->get('/yard?room=' . $room->id);

        $response->assertOk();
        $response->assertSee('newcomer joined');
        // The pill carries the joiner's id, so tapping it opens their preview.
        $response->assertSee("open-user-preview', { id: {$joiner->id} }", false);
    }

    public function test_your_own_arrival_is_not_a_link(): void
    {
        $creator = $this->createUser(['username' => 'host']);
        $viewer  = $this->createUser(['username' => 'myself']);

        $room = YardRoom::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $creator->id,
        ]);

        $this->join($room, $creator);
        $this->join($room, $viewer);

        $response = $this->actingAs($viewer)->get('/yard?room=' . $room->id);

        $response->assertOk()->assertSee('myself joined');
        // Nothing to look up about yourself.
        $response->assertDontSee("open-user-preview', { id: {$viewer->id} }", false);
    }
}
