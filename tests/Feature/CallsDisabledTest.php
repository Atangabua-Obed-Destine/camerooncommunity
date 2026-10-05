<?php

namespace Tests\Feature;

use App\Livewire\Yard\CallManager;
use App\Models\PlatformSetting;
use App\Models\YardCall;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Calling is switched off while it cannot be relied on.
 *
 * A button that starts a call which never connects is worse than one that
 * says the feature is not ready: the caller watches "Calling…" and the person
 * on the other end sees a ring they cannot answer. The switch is a platform
 * setting so it can be turned back on from the admin panel without a deploy.
 */
class CallsDisabledTest extends TestCase
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

    public function test_no_call_is_created_while_calling_is_off(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'them']);
        $room = $this->room($me, $them);

        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'voice')
            ->assertDispatched('calls-unavailable')
            ->assertNotDispatched('call-started');

        $this->assertSame(0, YardCall::where('room_id', $room->id)->count());
    }

    public function test_the_guard_is_on_the_server_not_only_the_button(): void
    {
        // A browser running older JavaScript still reaches this method, and
        // would otherwise create a call row and ring somebody.
        $me   = $this->createUser(['username' => 'me2']);
        $them = $this->createUser(['username' => 'them2']);
        $room = $this->room($me, $them);

        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'voice');

        $this->assertDatabaseCount('yard_calls', 0);
    }

    public function test_turning_the_setting_on_restores_calling(): void
    {
        PlatformSetting::setValue('calls_enabled', true);

        $me   = $this->createUser(['username' => 'me3']);
        $them = $this->createUser(['username' => 'them3']);
        $room = $this->room($me, $them);

        Livewire::actingAs($me)
            ->test(CallManager::class)
            ->call('initiateCall', $room->id, 'voice')
            ->assertNotDispatched('calls-unavailable')
            ->assertDispatched('call-started');

        $this->assertSame(1, YardCall::where('room_id', $room->id)->count());
    }

    public function test_the_chat_header_offers_the_notice_rather_than_a_call(): void
    {
        $me   = $this->createUser(['username' => 'me4']);
        $them = $this->createUser(['username' => 'them4']);
        $room = $this->room($me, $them);

        $html = Livewire::actingAs($me)
            ->test(\App\Livewire\Yard\ChatRoom::class, ['room' => $room])
            ->html();

        $this->assertStringContainsString('callsNotice = true', $html);
        $this->assertStringNotContainsString("initiate-call', { roomId: {$room->id}", $html);
    }
}
