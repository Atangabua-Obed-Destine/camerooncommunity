<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class ContactLinkTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_landing_page_offers_contact_us(): void
    {
        $this->get('/')->assertOk()->assertSee(route('contact'), false);
    }

    public function test_the_yard_menus_offer_contact_us(): void
    {
        // Both the desktop rail and the phone drawer render on this page.
        $response = $this->actingAs($this->createUser(['username' => 'contactme']))->get('/yard');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), route('contact')),
            'Contact us should appear in both the rail and the drawer.'
        );
    }

    public function test_the_contact_page_still_loads(): void
    {
        $this->get(route('contact'))->assertOk();
    }

    public function test_signed_in_visitors_get_the_app_header_on_contact(): void
    {
        $response = $this->actingAs($this->createUser(['username' => 'headercheck']))
            ->get(route('contact'));

        $response->assertOk();
        // The app header carries the nav tabs and the notification bell; the
        // marketing navbar does not.
        $response->assertSee(route('marketplace.index'), false);
        $response->assertSee('open-app-menu', false);
        $response->assertDontSee('Join Free');
    }

    public function test_guests_keep_the_public_navbar_on_contact(): void
    {
        $response = $this->get(route('contact'));

        $response->assertOk();
        $response->assertSee('Join Free');
    }
}
