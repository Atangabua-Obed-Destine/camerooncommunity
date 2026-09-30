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

class CallStaleLockTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function room($me, $them): YardRoom
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

    private function callRow(YardRoom $room, $by, string $status, $createdAt): YardCall
    {
        $call = YardCall::create([
            'tenant_id'    => $this->tenant->id,
            'room_id'      => $room->id,
            'initiated_by' => $by->id,
            'call_type'    => 'voice',
            'status'       => $status,
        ]);

        // created_at/updated_at are what staleness is judged on.
        YardCall::whereKey($call->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);

        return $call->fresh();
    }

    public function test_a_dead_ringing_call_no_longer_blocks_the_room_forever(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'them']);
        $room = $this->room($me, $them);

        // A caller whose tab closed mid-ring never sends endCall.
        $stale = $this->callRow($room, $them, 'ringing', now()->subMinutes(10));

        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'voice')
            ->assertNotDispatched('call-error');

        $this->assertSame('ended', $stale->fresh()->status);
        $this->assertSame(1, YardCall::where('room_id', $room->id)->where('status', 'ringing')->count());
    }

    public function test_a_call_that_is_genuinely_ringing_still_blocks(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'them2']);
        $room = $this->room($me, $them);

        $fresh = $this->callRow($room, $them, 'ringing', now()->subSeconds(20));

        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'voice')
            ->assertDispatched('call-error');

        $this->assertSame('ringing', $fresh->fresh()->status);
    }

    public function test_an_abandoned_active_call_is_reaped_too(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'them3']);
        $room = $this->room($me, $them);

        $stale = $this->callRow($room, $them, 'active', now()->subHours(9));

        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'voice')
            ->assertNotDispatched('call-error');

        $this->assertSame('ended', $stale->fresh()->status);
    }
}
