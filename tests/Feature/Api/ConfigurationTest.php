<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The payload the app reads before it draws anything.
 *
 * We are not building an admin screen into the client, so this endpoint is the
 * only way a setting changed at /admin/settings reaches a phone. That makes two
 * things worth guarding: that it answers with nobody signed in, and that the
 * switches really are read from the database rather than baked in at build
 * time — a release just to turn calls back on would defeat the point.
 */
class ConfigurationTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_it_answers_without_a_token(): void
    {
        // The app needs to learn it is out of date, or that the platform is
        // down, before it can show a login form at all.
        $this->getJson(route('api.v1.configuration'))
            ->assertOk()
            ->assertJsonStructure([
                'app' => ['name', 'logo_url', 'minimum_version', 'maintenance_mode'],
                'features' => ['calls_enabled'],
                'modules' => ['live', 'coming_soon'],
                'marketplace' => ['currency', 'max_images'],
                'payment_methods',
                'languages',
                'realtime' => ['key', 'host', 'port', 'scheme'],
            ]);
    }

    public function test_behind_tls_the_socket_is_where_the_request_arrived(): void
    {
        // Production broadcasts to Reverb on 127.0.0.1:8080 over plain http,
        // which a phone cannot reach. The public socket is nginx's /app on the
        // site's own host and port 443, the place the web's Echo goes too.
        config([
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
        ]);

        $this->getJson('https://cameroonnetwork.org/api/v1/configuration')
            ->assertJsonPath('realtime.host', 'cameroonnetwork.org')
            ->assertJsonPath('realtime.port', 443)
            ->assertJsonPath('realtime.scheme', 'https');
    }

    public function test_without_tls_the_configured_socket_is_announced(): void
    {
        // A development machine: the app maps 127.0.0.1 onto adb reverse.
        config([
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
        ]);

        $this->getJson(route('api.v1.configuration'))
            ->assertJsonPath('realtime.host', '127.0.0.1')
            ->assertJsonPath('realtime.port', 8080)
            ->assertJsonPath('realtime.scheme', 'http');
    }

    public function test_calls_are_off_until_the_setting_says_otherwise(): void
    {
        $this->getJson(route('api.v1.configuration'))
            ->assertJsonPath('features.calls_enabled', false);
    }

    public function test_turning_calls_on_reaches_the_app(): void
    {
        // The whole contract of this endpoint in one test: an admin flips a
        // row, and the next launch of the app sees it. No deploy, no release.
        PlatformSetting::setValue('calls_enabled', 'true');

        $this->getJson(route('api.v1.configuration'))
            ->assertJsonPath('features.calls_enabled', true);
    }

    public function test_the_coming_soon_list_matches_the_one_the_web_routes(): void
    {
        // Both read config('cameroon.modules.coming_soon'). The list used to be
        // a literal inside routes/web.php; if it ever goes back to being two
        // copies, the app will grey out the wrong modules.
        $slugs = collect($this->getJson(route('api.v1.configuration'))->json('modules.coming_soon'))
            ->pluck('slug')
            ->all();

        $this->assertSame(
            array_keys(config('cameroon.modules.coming_soon')),
            $slugs
        );

        foreach ($slugs as $slug) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has("module.{$slug}"),
                "No placeholder route exists for the '{$slug}' module."
            );
        }
    }

    public function test_the_live_modules_are_named(): void
    {
        $live = collect($this->getJson(route('api.v1.configuration'))->json('modules.live'))
            ->pluck('name', 'slug');

        $this->assertSame('GoConnect', $live['goconnect']);
        $this->assertSame('GoMarket', $live['gomarket']);
    }

    public function test_the_three_renamed_modules_keep_the_names_the_navigation_shows(): void
    {
        // These three are the only coming-soon slugs the navigation names, and
        // the two lists are edited in different files. They disagreed once
        // already — the API said EasyGoParcel/RoadFam/WorkConnect while every
        // nav surface said GoParcel/GoRide/GoPartner — which would have given
        // the app a different label from the web for the same module.
        $soon = collect($this->getJson(route('api.v1.configuration'))->json('modules.coming_soon'))
            ->pluck('name', 'slug');

        $this->assertSame('GoParcel', $soon['easygoparcel']);
        $this->assertSame('GoRide', $soon['roadfam']);
        $this->assertSame('GoPartner', $soon['workconnect']);
    }
    public function test_a_module_is_only_live_if_a_member_can_reach_it(): void
    {
        // Solidarity used to sit in modules.live. The components exist, but
        // nothing mounts them: no route, no @livewire call, no <livewire: tag,
        // and the only solidarity routes are admin moderation. Every nav
        // surface on the web already shows it as Coming Soon, so advertising
        // it as live gave the app a module with no way into it. If somebody
        // wires up a member-facing entry point, delete this test and put it
        // back in the registry.
        $live = collect($this->getJson(route('api.v1.configuration'))->json('modules.live'))
            ->pluck('slug');

        $this->assertFalse(
            $live->contains('solidarity'),
            'Solidarity has no member-facing entry point and must not be advertised as live.'
        );

        // Kamer AI is the real example of the shape Solidarity was claiming:
        // live, mounted as an overlay by the app layout, no route of its own.
        $this->assertTrue($live->contains('kamer_ai'));
    }
    public function test_marketplace_limits_come_from_the_shipped_defaults(): void
    {
        // Nothing has written these rows, so the defaults in
        // config/cameroon.php are what actually answer. The composer enforces
        // them before upload rather than after, which only works if they
        // arrive as numbers.
        $response = $this->getJson(route('api.v1.configuration'))->assertOk();

        $this->assertSame(10, $response->json('marketplace.max_images'));
        $this->assertSame('XAF', $response->json('marketplace.currency'));
        $this->assertIsInt($response->json('marketplace.min_title_length'));
    }

    public function test_an_admin_override_beats_the_default(): void
    {
        PlatformSetting::setValue('marketplace_max_images', '4');

        $this->getJson(route('api.v1.configuration'))
            ->assertJsonPath('marketplace.max_images', 4);
    }

    public function test_the_manual_payment_driver_is_offered(): void
    {
        $methods = collect($this->getJson(route('api.v1.configuration'))->json('payment_methods'))
            ->pluck('id');

        $this->assertTrue($methods->contains('manual'));
    }

    public function test_it_does_not_leak_the_broadcasting_secret(): void
    {
        // The app needs the public app key to connect to Reverb. It must never
        // be handed the secret that signs events.
        $body = $this->getJson(route('api.v1.configuration'))->getContent();

        $this->assertStringNotContainsString('secret', strtolower($body));
    }
}
