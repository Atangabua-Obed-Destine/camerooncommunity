<?php

namespace Tests\Feature;

use App\Livewire\Yard\CallManager;
use App\Models\YardCall;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class VideoCallsDisabledTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function roomWith($me, $them): YardRoom
    {
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([$me, $them] as $u) {
            YardRoomMember::create([
                'tenant_id' => $this->tenant->id,
                'room_id'   => $room->id,
                'user_id'   => $u->id,
                'role'      => 'member',
                'joined_at' => now()->subYear(),
            ]);
        }

        return $room;
    }

    public function test_the_chatroom_offers_no_video_call_button(): void
    {
        $me   = $this->createUser(['username' => 'novideo']);
        $them = $this->createUser(['username' => 'other']);
        $room = $this->roomWith($me, $them);

        $response = $this->actingAs($me)->get('/yard?room=' . $room->id);

        $response->assertOk();
        $response->assertDontSee("type: 'video'", false);
        // Voice is still there.
        $response->assertSee("type: 'voice'", false);
    }

    public function test_a_video_call_asked_for_anyway_is_placed_as_voice(): void
    {
        $me   = $this->createUser(['username' => 'asker']);
        $them = $this->createUser(['username' => 'callee']);
        $room = $this->roomWith($me, $them);

        // 'initiate-call' is a browser event, so the server has the last word.
        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'video');

        $this->assertSame('voice', YardCall::where('room_id', $room->id)->value('call_type'));
    }
}
