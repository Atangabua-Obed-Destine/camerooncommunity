<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Signing a phone in.
 *
 * The web signs in with a session cookie, which a phone cannot hold. These
 * tests cover the token path that replaces it, and the things that path gets
 * wrong if nobody is watching: a suspended account keeping a working token
 * long after it was suspended, and one device's sign-out taking the others
 * down with it.
 */
class AuthTokenTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function login(User $user, string $device = 'iPhone 14', string $password = 'password')
    {
        return $this->postJson(route('api.v1.auth.login'), [
            'email' => $user->email,
            'password' => $password,
            'device_name' => $device,
        ]);
    }

    /**
     * Forget the guard that an earlier request in this test resolved.
     *
     * Every real request is its own PHP process, so the token is read back out
     * of the database each time. A test reuses one application instance, and
     * `RequestGuard` keeps the user it already resolved — so a token revoked
     * part-way through a test still looks valid, and an assertion about
     * revocation would pass or fail for the wrong reason. Clearing the guards
     * puts the test back in step with production.
     */
    private function forgetAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_a_member_gets_a_token_and_their_own_record(): void
    {
        $user = $this->createUser(['username' => 'ngwa']);

        $response = $this->login($user)->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('ngwa', $response->json('user.username'));

        // Signing in is the one moment the app can be sure of the token, so
        // the row has to exist by the time we answer.
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone 14',
        ]);
    }

    public function test_the_token_actually_authenticates(): void
    {
        $user = $this->createUser();
        $token = $this->login($user)->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = $this->createUser();

        $this->login($user, password: 'not-the-password')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_device_name_is_required(): void
    {
        // Without one we cannot sign a single device out later, and the table
        // grows a row per app launch.
        $user = $this->createUser();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('device_name');
    }

    public function test_a_suspended_account_is_never_given_a_token(): void
    {
        $user = $this->createUser(['is_active' => false]);

        $this->login($user)->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_suspending_a_member_kills_the_token_already_on_their_phone(): void
    {
        // The reason this matters: a token outlives the request that made it.
        // Without this, suspending somebody leaves the app in their pocket
        // working until they happen to sign out on their own.
        $user = $this->createUser();
        $token = $this->login($user)->json('token');

        $user->forceFill(['is_active' => false])->saveQuietly();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'))
            ->assertStatus(403);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_signing_out_one_device_leaves_the_others_signed_in(): void
    {
        $user = $this->createUser();

        $phone = $this->login($user, 'iPhone 14')->json('token');
        $tablet = $this->login($user, 'iPad')->json('token');

        $this->withHeader('Authorization', 'Bearer '.$phone)
            ->postJson(route('api.v1.auth.logout'))
            ->assertOk();

        // Only the phone's row is gone.
        $this->assertSame(['iPad'], $user->tokens()->pluck('name')->all());

        $this->forgetAuth();
        $this->withHeader('Authorization', 'Bearer '.$phone)
            ->getJson(route('api.v1.auth.me'))
            ->assertStatus(401);

        $this->forgetAuth();
        $this->withHeader('Authorization', 'Bearer '.$tablet)
            ->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_a_revoked_token_stops_working(): void
    {
        // Revoked straight out of the database rather than through the
        // endpoint, so nothing in this test has authenticated beforehand and
        // the refusal is unambiguous.
        $user = $this->createUser();
        $token = $this->login($user)->json('token');

        $user->tokens()->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'))
            ->assertStatus(401);
    }

    public function test_signing_in_again_on_the_same_device_replaces_its_token(): void
    {
        $user = $this->createUser();

        $this->login($user, 'iPhone 14');
        $this->login($user, 'iPhone 14');

        $this->assertSame(1, $user->tokens()->where('name', 'iPhone 14')->count());
    }

    public function test_me_needs_a_token(): void
    {
        $this->getJson(route('api.v1.auth.me'))->assertStatus(401);
    }

    public function test_a_member_sees_their_own_private_fields(): void
    {
        // Email, phone and the active-location scoping the client needs to
        // read back. Only ever for the account doing the asking.
        $user = $this->createUser([
            'phone' => '+237600000000',
            'active_country' => 'Cameroon',
            'active_region' => 'Centre',
        ]);

        $token = $this->login($user)->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.phone', '+237600000000')
            ->assertJsonPath('user.active_region', 'Centre')
            ->assertJsonPath('user.onboarded', true);
    }

    public function test_the_avatar_comes_back_as_a_url_not_a_path(): void
    {
        // The client has no asset() to resolve a stored path against.
        $user = $this->createUser(['avatar' => 'avatars/ngwa.jpg']);
        $token = $this->login($user)->json('token');

        $url = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'))
            ->json('user.avatar_url');

        $this->assertStringStartsWith('http', $url);
        $this->assertStringEndsWith('storage/avatars/ngwa.jpg', $url);
    }

    public function test_a_google_avatar_is_left_alone(): void
    {
        // Accounts that arrived through Google store a full URL, not a path.
        $remote = 'https://lh3.googleusercontent.com/a/abc123';
        $user = $this->createUser(['avatar' => $remote]);
        $token = $this->login($user)->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'))
            ->assertJsonPath('user.avatar_url', $remote);
    }
}
