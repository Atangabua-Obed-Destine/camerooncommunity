<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The browser must always get a usable ICE list.
 *
 * A call between two networks needs a TURN relay. When the hosted provider
 * refuses credentials — as it is doing now, with 401 Invalid API Key — the
 * endpoint used to hand back STUN alone, which cannot cross carrier NAT, and
 * every such call failed with nothing to show for it. A relay configured in
 * .env is offered whatever the provider does.
 */
class TurnCredentialsTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        Cache::forget('turn_credentials');
    }

    private function useStaticRelay(): void
    {
        config([
            'services.turn.urls' => [
                'turn:global.relay.example.com:80',
                'turns:global.relay.example.com:443?transport=tcp',
            ],
            'services.turn.username' => 'relay-user',
            'services.turn.password' => 'relay-pass',
        ]);
    }

    public function test_a_configured_relay_is_served_even_when_the_provider_refuses(): void
    {
        $this->useStaticRelay();
        config(['services.metered.domain' => 'example.metered.live', 'services.metered.secret_key' => 'bad']);

        Http::fake(['*' => Http::response(['error' => 'Invalid API Key'], 401)]);

        $servers = $this->actingAs($this->createUser())
            ->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->json();

        $first = $servers[0];

        $this->assertContains('turn:global.relay.example.com:80', $first['urls']);
        $this->assertSame('relay-user', $first['username']);
        $this->assertSame('relay-pass', $first['credential']);
    }

    public function test_the_configured_relay_comes_before_the_fallback(): void
    {
        // Order matters: the browser tries them in sequence, and the relay is
        // the only entry that can carry media when both peers are behind NAT.
        $this->useStaticRelay();
        config(['services.metered.domain' => null, 'services.metered.secret_key' => null]);

        $servers = $this->actingAs($this->createUser())
            ->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->json();

        $this->assertStringStartsWith('turn', $servers[0]['urls'][0]);
        $this->assertStringStartsWith('stun:', end($servers)['urls']);
    }

    public function test_without_a_configured_relay_the_provider_still_answers(): void
    {
        config([
            'services.turn.urls' => [],
            'services.metered.domain' => 'example.metered.live',
            'services.metered.secret_key' => 'good',
        ]);

        Http::fake(['*' => Http::response([
            ['urls' => 'turn:provider.example:80', 'username' => 'u', 'credential' => 'c'],
        ], 200)]);

        $servers = $this->actingAs($this->createUser())
            ->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->json();

        $this->assertSame('turn:provider.example:80', $servers[0]['urls']);
    }

    public function test_a_provider_outage_never_breaks_the_endpoint(): void
    {
        // Http::get throws on DNS failure; an uncaught throw here turned the
        // endpoint into a 500 and the engine got no ICE servers at all.
        config([
            'services.turn.urls' => [],
            'services.metered.domain' => 'example.metered.live',
            'services.metered.secret_key' => 'good',
        ]);

        Http::fake(fn () => throw new \RuntimeException('getaddrinfo failed'));

        $this->actingAs($this->createUser())
            ->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->assertJsonFragment(['urls' => 'stun:stun.l.google.com:19302']);
    }
}
