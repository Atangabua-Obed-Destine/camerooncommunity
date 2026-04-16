<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class AuthTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    // ─── Login ───

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createUser(['email_verified_at' => now()]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect();
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = $this->createUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    // ─── New user without GPS gets redirected to onboarding ───

    public function test_new_user_without_country_redirected_to_onboarding(): void
    {
        $user = $this->createUser([
            'current_country' => null,
            'current_city' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('onboarding'));
    }

    // ─── Registration page ───

    public function test_register_page_loads(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    // ─── Forgot password ───

    public function test_forgot_password_page_loads(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertStatus(200);
    }

    // ─── Email verification ───

    public function test_unverified_user_gets_verification_notice(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $response = $this->actingAs($user)->get(route('verification.notice'));
        $response->assertStatus(200);
    }

    // ─── Inactive user denied access ───

    public function test_inactive_user_is_logged_out(): void
    {
        $user = $this->createUser(['is_active' => false]);

        // Login succeeds initially, but EnsureUserActive middleware on
        // subsequent requests will kick them out.
        // We test the middleware directly by hitting a protected route.
        $this->actingAs($user);

        $response = $this->get('/yard');
        $response->assertRedirect(route('login'));
    }

    // ─── Onboarding enforcement ───

    public function test_non_onboarded_user_redirected_to_onboarding(): void
    {
        $user = $this->createUser([
            'onboarded_at' => null,
        ]);

        $this->actingAs($user);
        $response = $this->get('/yard');
        $response->assertRedirect(route('onboarding'));
    }

    public function test_onboarded_user_can_access_yard(): void
    {
        $user = $this->createUser([
            'onboarded_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->get('/yard');
        $response->assertStatus(200);
    }
}
