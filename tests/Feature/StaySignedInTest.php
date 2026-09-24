<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class StaySignedInTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_sessions_last_a_long_time_and_survive_closing_the_browser(): void
    {
        // A short lifetime is what produced the 419 "page expired" bounces.
        $this->assertGreaterThanOrEqual(43200, config('session.lifetime'), 'at least 30 days');
        $this->assertFalse((bool) config('session.expire_on_close'));
    }

    public function test_logging_in_sets_the_remember_cookie_without_ticking_a_box(): void
    {
        $user = $this->createUser([
            'email'    => 'stay@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->post('/login', [
            'email'    => 'stay@example.test',
            'password' => 'secret1234',
            // deliberately no 'remember' field
        ]);

        $this->assertAuthenticatedAs($user);
        // The recaller cookie is what signs them back in if the session is ever lost.
        $this->assertNotNull(
            $response->headers->getCookies()[array_search(
                Auth::getRecallerName(),
                array_map(fn ($c) => $c->getName(), $response->headers->getCookies()),
                true
            )] ?? null,
            'expected the remember-me cookie to be set'
        );
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_signing_out_still_ends_the_session(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
