<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * "Continue with Google" from the phone.
 *
 * The web's version is a browser round trip through Socialite, which an app
 * cannot make. The app gets an ID token from Android's Google sign-in instead,
 * and the server asks Google whether it is genuine. These tests fake Google's
 * answer and check that the three outcomes match the web's callback: a linked
 * member signs in, a member with the same verified email is linked, and a
 * newcomer is sent through the register wizard rather than created on the spot.
 */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private const CLIENT = 'web-client.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        PlatformSetting::setValue('openai_enabled', 'false', 'ai');
        config(['services.google.client_id' => self::CLIENT]);
    }

    /** Google's tokeninfo answer for a token, with any claims overridden. */
    private function google(array $claims = [], int $status = 200): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(array_merge([
                'iss' => 'https://accounts.google.com',
                'aud' => self::CLIENT,
                'sub' => '1100220033',
                'email' => 'Ama@Example.com',
                'email_verified' => 'true',
                'name' => 'Ama Njoya',
                'picture' => '',
                'locale' => 'fr',
                'exp' => (string) (time() + 3600),
            ], $claims), $status),
            '*' => Http::response('', 404),
        ]);
    }

    private function continueWithGoogle()
    {
        return $this->postJson(route('api.v1.auth.google'), ['id_token' => 'header.payload.sig', 'device_name' => 'Pixel 9']);
    }

    public function test_the_app_is_told_google_is_on_and_which_client_to_ask_for(): void
    {
        $this->getJson(route('api.v1.configuration'))
            ->assertJsonPath('auth.google', true)
            ->assertJsonPath('auth.google_client_id', self::CLIENT);

        config(['services.google.client_id' => null]);
        $this->getJson(route('api.v1.configuration'))->assertJsonPath('auth.google', false);
    }

    public function test_a_member_already_linked_to_google_is_signed_in(): void
    {
        $user = $this->createUser(['google_id' => '1100220033', 'email' => 'someone-else@example.com']);
        $this->google();

        $this->continueWithGoogle()
            ->assertOk()
            ->assertJsonPath('status', 'signed_in')
            ->assertJsonPath('user.email', 'someone-else@example.com')
            ->assertJsonStructure(['token']);

        $this->assertSame(1, $user->tokens()->where('name', 'Pixel 9')->count());
    }

    public function test_a_member_with_the_same_verified_email_is_linked_and_signed_in(): void
    {
        $user = $this->createUser(['email' => 'ama@example.com', 'google_id' => null]);
        $this->google();

        $this->continueWithGoogle()->assertOk()->assertJsonPath('status', 'signed_in');

        $this->assertSame('1100220033', $user->fresh()->google_id);
    }

    public function test_an_unverified_google_email_never_takes_over_an_account(): void
    {
        // Anyone can type someone else's address into a Google account; only
        // Google's own verification makes the email proof of anything.
        $user = $this->createUser(['email' => 'ama@example.com', 'google_id' => null]);
        $this->google(['email_verified' => 'false']);

        $this->continueWithGoogle()->assertJsonValidationErrors('id_token');

        $this->assertNull($user->fresh()->google_id);
    }

    public function test_a_newcomer_is_sent_to_the_wizard_and_nothing_is_created_yet(): void
    {
        $this->google();

        $this->continueWithGoogle()
            ->assertOk()
            ->assertJsonPath('status', 'register')
            ->assertJsonPath('google.email', 'ama@example.com')
            ->assertJsonPath('google.name', 'Ama Njoya')
            ->assertJsonPath('google.locale', 'fr')
            ->assertJsonMissingPath('token');

        $this->assertFalse(User::withoutGlobalScopes()->where('email', 'ama@example.com')->exists());
    }

    public function test_the_wizard_creates_the_google_account_without_a_password(): void
    {
        $this->google();

        $this->postJson(route('api.v1.auth.register'), [
            'google_id_token' => 'header.payload.sig',
            'username' => 'Ama Njoya',
            // Ignored: the account takes Google's address, as the web locks it.
            'email' => 'typed-over@example.com',
            'language_pref' => 'fr',
            'current_country' => 'Cameroon',
            'current_region' => 'Littoral',
            'terms' => true,
            'device_name' => 'Pixel 9',
        ])->assertCreated()->assertJsonStructure(['token']);

        $user = User::withoutGlobalScopes()->where('email', 'ama@example.com')->firstOrFail();
        $this->assertSame('1100220033', $user->google_id);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a_token_meant_for_another_app_is_refused(): void
    {
        // Google vouches for the token, but it was issued to someone else's
        // OAuth client: accepting it would sign in that app's users here.
        $this->createUser(['google_id' => '1100220033']);
        $this->google(['aud' => 'another-app.apps.googleusercontent.com']);

        $this->continueWithGoogle()->assertJsonValidationErrors('id_token')->assertJsonMissingPath('token');
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->createUser(['google_id' => '1100220033']);
        $this->google(['exp' => (string) (time() - 10)]);

        $this->continueWithGoogle()->assertJsonValidationErrors('id_token');
    }

    public function test_a_token_google_does_not_recognise_is_refused(): void
    {
        $this->createUser(['google_id' => '1100220033']);
        $this->google(['error' => 'invalid_token'], 400);

        $this->continueWithGoogle()->assertJsonValidationErrors('id_token');
    }

    public function test_a_suspended_member_gets_no_token(): void
    {
        $this->createUser(['google_id' => '1100220033', 'is_active' => false]);
        $this->google();

        $this->continueWithGoogle()->assertJsonValidationErrors('id_token')->assertJsonMissingPath('token');
    }
}
