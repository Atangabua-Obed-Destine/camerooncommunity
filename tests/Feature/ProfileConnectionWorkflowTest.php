<?php

namespace Tests\Feature;

use App\Livewire\Marketplace\SellerProfile;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardRoom;
use App\Services\ConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class ProfileConnectionWorkflowTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function profile(User $viewer, User $target)
    {
        return Livewire::actingAs($viewer)->test(SellerProfile::class, ['username' => $target->username]);
    }

    public function test_the_state_drives_the_whole_row(): void
    {
        $me    = $this->createUser(['username' => 'viewer']);
        $them  = $this->createUser(['username' => 'target']);

        // Strangers.
        $this->profile($me, $them)->assertSet('connectionState', 'none');

        // I ask.
        $this->profile($me, $them)->call('connect')->assertSet('connectionState', 'outgoing');
        $this->profile($them, $me)->assertSet('connectionState', 'incoming');

        // They accept.
        $this->profile($them, $me)->call('acceptRequest')->assertSet('connectionState', 'connected');
        $this->profile($me, $them)->assertSet('connectionState', 'connected');
    }

    public function test_a_pending_request_can_be_withdrawn_by_the_sender_only(): void
    {
        $me   = $this->createUser(['username' => 'sender']);
        $them = $this->createUser(['username' => 'receiver']);

        $this->profile($me, $them)->call('connect')->assertSet('connectionState', 'outgoing');

        // The receiver cannot "cancel" someone else's request from their side.
        $this->profile($them, $me)->call('cancelRequest')->assertSet('connectionState', 'incoming');

        $this->profile($me, $them)->call('cancelRequest')->assertSet('connectionState', 'none');
        $this->assertNull(UserConnection::between($me->id, $them->id));
    }

    public function test_the_pending_state_shows_its_status_and_its_action_without_hover(): void
    {
        $me   = $this->createUser(['username' => 'pendingviewer']);
        $them = $this->createUser(['username' => 'pendingtarget']);

        app(ConnectionService::class)->request($me, $them);

        // A phone has no hover, so both the status and the way out must be
        // present and readable at the same time.
        $this->actingAs($me)
            ->get('/marketplace/seller/' . $them->username)
            ->assertOk()
            ->assertSee('Request sent')
            ->assertSee('Cancel request')
            ->assertSee('wire:click="cancelRequest"', false);
    }

    public function test_an_incoming_request_can_be_declined(): void
    {
        $me   = $this->createUser(['username' => 'asker']);
        $them = $this->createUser(['username' => 'decliner']);

        app(ConnectionService::class)->request($me, $them);

        $this->profile($them, $me)->call('declineRequest')->assertSet('connectionState', 'none');
    }

    public function test_connected_people_can_disconnect(): void
    {
        $me   = $this->createUser(['username' => 'one']);
        $them = $this->createUser(['username' => 'two']);

        app(ConnectionService::class)->request($me, $them);
        app(ConnectionService::class)->accept($them, $me->id);

        $this->profile($me, $them)
            ->assertSet('connectionState', 'connected')
            ->call('disconnect')
            ->assertSet('connectionState', 'none');

        $this->assertFalse($me->fresh()->isConnectedWith($them->id));
    }

    public function test_messaging_requires_a_connection(): void
    {
        $me   = $this->createUser(['username' => 'wantstotalk']);
        $them = $this->createUser(['username' => 'stranger']);

        // Not connected: told what to do, and no room is created behind the scenes.
        $this->profile($me, $them)
            ->call('message')
            ->assertDispatched('toast')
            ->assertNoRedirect();

        $this->assertSame(0, YardRoom::where('room_type', 'direct_message')->count());
    }

    public function test_messaging_opens_the_room_once_connected(): void
    {
        $me   = $this->createUser(['username' => 'talker']);
        $them = $this->createUser(['username' => 'friend']);

        app(ConnectionService::class)->request($me, $them);
        app(ConnectionService::class)->accept($them, $me->id);

        $room = YardRoom::where('room_type', 'direct_message')->first();
        $this->assertNotNull($room, 'accepting should open the conversation');

        $this->profile($me, $them)->call('message')->assertRedirect(route('yard') . '?room=' . $room->id);
    }

    public function test_the_card_endpoints_cancel_and_disconnect(): void
    {
        $me   = $this->createUser(['username' => 'apiuser']);
        $them = $this->createUser(['username' => 'apiother']);

        app(ConnectionService::class)->request($me, $them);

        $this->actingAs($me)
            ->postJson(route('yard.connections.cancel'), ['user_id' => $them->id])
            ->assertOk()
            ->assertJsonPath('state', 'none');
        $this->assertNull(UserConnection::between($me->id, $them->id));

        app(ConnectionService::class)->request($me, $them);
        app(ConnectionService::class)->accept($them, $me->id);

        $this->actingAs($me)
            ->postJson(route('yard.connections.disconnect'), ['user_id' => $them->id])
            ->assertOk()
            ->assertJsonPath('state', 'none');
        $this->assertFalse($me->fresh()->isConnectedWith($them->id));
    }
}
