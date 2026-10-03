<?php

namespace Tests\Feature;

use App\Events\CallSignal;
use App\Events\CallUpdated;
use App\Livewire\Yard\CallManager;
use App\Models\YardCall;
use App\Models\YardCallParticipant;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * When the callee picks up, the caller has to hear about it.
 *
 * The caller used to be told only on the room channel, which it is subscribed
 * to only while the room is open and was announced. Miss that and the caller
 * sat on "Calling…" while the other side was already in the call. Each
 * participant's own .calls channel is the one channel a call is guaranteed to
 * reach them on, so every update and every signal now goes there too.
 */
class CallAnswerReachesCallerTest extends TestCase
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

    private function ringingCall(YardRoom $room, $caller, $callee): YardCall
    {
        $call = YardCall::create([
            'tenant_id'    => $this->tenant->id,
            'room_id'      => $room->id,
            'initiated_by' => $caller->id,
            'call_type'    => 'voice',
            'status'       => 'ringing',
        ]);

        foreach ([[$caller, 'joined'], [$callee, 'ringing']] as [$user, $status]) {
            YardCallParticipant::create([
                'call_id'   => $call->id,
                'user_id'   => $user->id,
                'status'    => $status,
                'joined_at' => $status === 'joined' ? now() : null,
            ]);
        }

        return $call;
    }

    /** @return array<int, string> */
    private function channelNames($event): array
    {
        return array_map(fn ($c) => (string) $c, $event->broadcastOn());
    }

    public function test_answering_tells_the_caller_on_their_own_call_channel(): void
    {
        $caller = $this->createUser(['username' => 'caller']);
        $callee = $this->createUser(['username' => 'callee']);
        $room   = $this->room($caller, $callee);
        $call   = $this->ringingCall($room, $caller, $callee);

        Event::fake([CallUpdated::class]);

        Livewire::actingAs($callee)
            ->test(CallManager::class)
            ->call('answerCall', $call->uuid)
            ->assertDispatched('call-answered');

        Event::assertDispatched(CallUpdated::class, function (CallUpdated $event) use ($caller, $room) {
            $this->assertSame('joined', $event->action);

            $channels = $this->channelNames($event);

            $this->assertContains('tenant.' . $this->tenant->id . '.room.' . $room->id, $channels);
            $this->assertContains(
                'tenant.' . $this->tenant->id . '.user.' . $caller->id . '.calls',
                $channels,
                'The caller must be told on their own channel, not only the room\'s.'
            );

            return true;
        });

        $this->assertSame('active', $call->fresh()->status);
    }

    public function test_the_answer_payload_carries_what_the_caller_matches_on(): void
    {
        $caller = $this->createUser(['username' => 'caller2']);
        $callee = $this->createUser(['username' => 'callee2']);
        $room   = $this->room($caller, $callee);
        $call   = $this->ringingCall($room, $caller, $callee);

        Event::fake([CallUpdated::class]);

        Livewire::actingAs($callee)
            ->test(CallManager::class)
            ->call('answerCall', $call->uuid);

        Event::assertDispatched(CallUpdated::class, function (CallUpdated $event) use ($call, $callee) {
            // The engine drops anything whose uuid does not match the call it is
            // on, and anything from itself, before it will leave 'outgoing'.
            $payload = $event->broadcastWith();

            $this->assertSame((string) $call->uuid, $payload['call_uuid']);
            $this->assertSame($callee->id, $payload['user_id']);
            $this->assertSame('joined', $payload['action']);
            $this->assertNotEmpty($payload['user_name']);

            return true;
        });
    }

    public function test_the_caller_can_read_the_answer_back_from_the_server(): void
    {
        // The reconciliation poll: with the broadcast lost entirely, the caller
        // still finds the joined participant and starts the call.
        $caller = $this->createUser(['username' => 'caller3']);
        $callee = $this->createUser(['username' => 'callee3']);
        $room   = $this->room($caller, $callee);
        $call   = $this->ringingCall($room, $caller, $callee);

        YardCallParticipant::where('call_id', $call->id)
            ->where('user_id', $callee->id)
            ->update(['status' => 'joined', 'joined_at' => now()]);

        $participants = Livewire::actingAs($caller)
            ->test(CallManager::class)
            ->set('activeCallId', $call->id)
            ->call('refreshParticipants')
            ->get('participants');

        $joined = collect($participants)
            ->firstWhere('user_id', $callee->id);

        $this->assertNotNull($joined);
        $this->assertSame('joined', $joined['status']);
    }

    public function test_signalling_is_addressed_to_the_peer_as_well_as_the_room(): void
    {
        $caller = $this->createUser(['username' => 'caller4']);
        $callee = $this->createUser(['username' => 'callee4']);
        $room   = $this->room($caller, $callee);
        $call   = $this->ringingCall($room, $caller, $callee);

        Event::fake([CallSignal::class]);

        Livewire::actingAs($caller)
            ->test(CallManager::class)
            ->call('sendSignal', $call->uuid, $callee->id, 'offer', ['sdp' => 'v=0']);

        Event::assertDispatched(CallSignal::class, function (CallSignal $event) use ($callee, $room) {
            $channels = $this->channelNames($event);

            $this->assertContains('tenant.' . $this->tenant->id . '.room.' . $room->id, $channels);
            $this->assertContains(
                'tenant.' . $this->tenant->id . '.user.' . $callee->id . '.calls',
                $channels,
                'An offer that misses its target leaves a call with no audio.'
            );

            return true;
        });
    }
}
