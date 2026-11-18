<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * About, Contact, Privacy and Terms. The mobile app opens them in an in-app
 * browser as /privacy?app=1&lang=fr and so on, rather than rebuilding them.
 */
class StaticPagesTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private const NAVBAR = 'mobileOpen: false';

    private const PAGES = ['about', 'contact', 'legal.privacy', 'legal.terms'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_policy_is_in_the_html_not_only_in_javascript(): void
    {
        // App Review, search engines and link previews read the HTML without running
        // Alpine. Before, every heading and paragraph here was an empty x-text.
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('>Built on respect.</h1>', false)
            ->assertSee('Your messages belong to you</h2>', false);
    }

    public function test_the_language_can_be_chosen_in_the_address(): void
    {
        // The in-app browser shares no storage with the app, so the app says it each time.
        $this->get(route('legal.privacy', ['lang' => 'fr']))
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('>Bâti sur le respect.</h1>', false);

        $this->get(route('legal.terms', ['lang' => 'xx']))->assertSee('<html lang="en"', false);
    }

    public function test_the_website_keeps_its_navbar_and_links_out(): void
    {
        foreach (self::PAGES as $page) {
            $this->get(route($page))->assertOk()->assertSee(self::NAVBAR, false);
        }

        $this->get(route('about'))
            ->assertSee(route('register'), false)
            ->assertSee('x-data="pwaInstall()"', false);
    }

    public function test_in_the_app_they_are_the_page_alone(): void
    {
        foreach (self::PAGES as $page) {
            $this->get(route($page, ['app' => 1]))
                ->assertOk()
                ->assertDontSee(self::NAVBAR, false)
                ->assertDontSee('x-data="pwaInstall()"', false)
                ->assertDontSee('"' . route('home') . '"', false);
        }

        // Someone reading About in the app already has an account.
        $this->get(route('about', ['app' => 1]))->assertDontSee(route('register'), false);
    }

    public function test_links_between_the_pages_stay_in_the_app(): void
    {
        $this->get(route('about', ['app' => 1, 'lang' => 'fr']))
            ->assertSee(e(route('legal.privacy', ['app' => 1, 'lang' => 'fr'])), false)
            ->assertSee(e(route('contact', ['app' => 1, 'lang' => 'fr'])), false);
    }

    public function test_a_signed_in_member_in_the_app_still_gets_the_bare_contact_page(): void
    {
        $member = $this->createUser(['username' => 'reader']);

        // On the website Contact switches to the app header for members.
        $this->actingAs($member)->get(route('contact'))->assertSee("\$dispatch('open-app-menu')", false);

        $this->actingAs($member)->get(route('contact', ['app' => 1]))
            ->assertOk()
            ->assertDontSee("\$dispatch('open-app-menu')", false)
            ->assertDontSee(self::NAVBAR, false);
    }
}
