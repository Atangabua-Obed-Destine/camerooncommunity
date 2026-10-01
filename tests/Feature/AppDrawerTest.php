<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class AppDrawerTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_header_offers_a_menu_button_and_the_drawer_it_opens(): void
    {
        $response = $this->actingAs($this->createUser(['username' => 'drawer']))->get('/yard');

        $response->assertOk();
        // The button dispatches the event; the drawer listens for it.
        $response->assertSee("\$dispatch('open-app-menu')", false);
        $response->assertSee('@open-app-menu.window', false);
    }

    public function test_the_drawer_reaches_what_the_rail_reaches(): void
    {
        $response = $this->actingAs($this->createUser(['username' => 'drawer2']))->get('/yard');

        // The rail is hidden below 768px, so these are phone-only entry points.
        $response->assertSee('open-discover', false);
        $response->assertSee('open-kamer-ai', false);
        $response->assertSee(route('marketplace.index'), false);
        $response->assertSee(route('logout'), false);
    }

    public function test_guests_get_no_drawer(): void
    {
        $this->get('/')->assertOk()->assertDontSee('open-app-menu', false);
    }
}
