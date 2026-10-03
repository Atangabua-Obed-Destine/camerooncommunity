<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class TurnCredentialsTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        Cache::forget('turn_credentials');
        config(['services.metered.domain' => 'turn.example.test', 'services.metered.secret_key' => 'k']);
    }

    public function test_an_unreachable_provider_still_yields_an_ice_list(): void
    {
        // This used to throw, and the 500 left the call engine with no servers.
        Http::fake(fn () => throw new ConnectionException('dns failure'));

        $this->actingAs($this->createUser())
            ->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->assertJsonFragment(['urls' => 'stun:stun.l.google.com:19302']);
    }

    public function test_a_provider_error_response_yields_the_fallback(): void
    {
        Http::fake(['*' => Http::response('nope', 503)]);

        $this->actingAs($this->createUser())
            ->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->assertJsonFragment(['urls' => 'stun:stun.l.google.com:19302']);
    }

    public function test_real_turn_servers_are_passed_through_and_cached(): void
    {
        Http::fake(['*' => Http::response([['urls' => 'turn:relay.test:80', 'username' => 'u', 'credential' => 'c']])]);

        $user = $this->createUser();

        $this->actingAs($user)->getJson(route('api.turn-credentials'))
            ->assertOk()
            ->assertJsonFragment(['urls' => 'turn:relay.test:80']);

        // Asked for on every page load, so it must not hit the provider each time.
        $this->actingAs($user)->getJson(route('api.turn-credentials'))->assertOk();
        Http::assertSentCount(1);
    }
}
