<?php

namespace Tests\Feature;

use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The back button is browser behaviour, so these assert the wiring is present
 * on every overlay rather than the interaction itself — a regression here is
 * someone adding a popup and forgetting the attribute.
 */
class OverlayBackButtonTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_global_overlays_register_with_the_back_stack(): void
    {
        $user = $this->createUser(['username' => 'backtest']);

        $body = $this->actingAs($user)->get('/yard')->assertOk()->getContent();

        foreach ([
            'x-overlay="isOpen"',      // profile preview card
            'x-overlay="photo.open"',  // full-size photo
            'x-overlay="open"',        // discover modal / drawer
            'x-overlay="showInfo"',    // room info panel
            'x-overlay="sel.on"',      // message selection
            'x-overlay="show"',        // forward modal
            'x-overlay="lightboxOpen"',
        ] as $hook) {
            $this->assertStringContainsString($hook, $body, "missing back-button wiring: {$hook}");
        }
    }

    public function test_the_chat_does_not_also_consume_a_press_an_overlay_took(): void
    {
        $user = $this->createUser(['username' => 'presser']);
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        YardRoomMember::create([
            'tenant_id' => $this->tenant->id,
            'room_id'   => $room->id,
            'user_id'   => $user->id,
            'role'      => 'member',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/yard?room=' . $room->id)
            ->assertOk()
            ->assertSee('__cnOverlayConsumedPop', false);
    }

    public function test_the_profile_settings_popup_answers_back(): void
    {
        $user = $this->createUser(['username' => 'settingsback']);

        $this->actingAs($user)
            ->get('/marketplace/seller/' . $user->username)
            ->assertOk()
            ->assertSee('x-overlay="settingsOpen"', false);
    }
}
