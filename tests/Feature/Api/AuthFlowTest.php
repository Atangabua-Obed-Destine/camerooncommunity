<?php

namespace Tests\Feature\Api;

use App\Enums\RoomType;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The app's way in: sign up, sign in, and the four onboarding steps. Each
 * endpoint runs the same service as the web screen it stands in for, so these
 * tests check that a phone ends up with exactly what a browser would.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        // No AI in tests: Kamer falls back to its fixed lines.
        PlatformSetting::setValue('openai_enabled', 'false', 'ai');
    }

    private function signup(array $overrides = [])
    {
        return $this->postJson(route('api.v1.auth.register'), array_merge([
            'username' => 'Ama Njoya',
            'email' => 'ama@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'language_pref' => 'fr',
            'current_country' => 'United Kingdom',
            'current_region' => 'England',
            'current_lat' => 53.48,
            'current_lng' => -2.24,
            'terms' => true,
            'device_name' => 'iPhone 15',
        ], $overrides));
    }

    public function test_signing_in_returns_the_members_own_fields_so_the_app_can_route(): void
    {
        $user = $this->createUser(['onboarded_at' => null]);

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.onboarded', false)
            ->assertJsonStructure(['user' => ['email_verified', 'active_country', 'language_pref']]);
    }

    public function test_signing_up_creates_the_member_the_web_would_and_signs_the_phone_in(): void
    {
        Http::fake(); // the UK region comes from the city map, no geocoder needed

        $response = $this->signup(['current_region' => 'Manchester'])->assertCreated();

        $user = User::where('email', 'ama@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^amanjoya-[a-z][0-9][a-z][0-9][a-z]$/', $user->username);
        $this->assertSame('Ama Njoya', $user->name);
        $this->assertSame('North West', $user->current_region);
        $this->assertSame('United Kingdom', $user->active_country);
        $this->assertSame('North West', $user->active_region);
        $this->assertTrue((bool) $user->is_founding_member);
        $this->assertNotNull($user->email_verified_at); // verification is off by default

        $response->assertJsonPath('user.onboarded', false)
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('kamer_welcome', null);

        $this->withToken($response->json('token'))->getJson(route('api.v1.auth.me'))->assertOk();
    }

    public function test_sign_up_refuses_what_the_web_refuses(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $this->signup(['password' => 'weakpass', 'password_confirmation' => 'weakpass'])->assertJsonValidationErrors('password');
        $this->signup(['terms' => false])->assertJsonValidationErrors('terms');
        $this->signup(['email' => 'taken@example.com'])->assertJsonValidationErrors('email');
        $this->signup(['username' => 'a'])->assertJsonValidationErrors('username');
        $this->signup(['current_country' => ''])->assertJsonValidationErrors('current_country');
    }

    public function test_with_verification_on_the_member_is_asked_to_verify(): void
    {
        Event::fake([Registered::class]);
        PlatformSetting::setValue('require_email_verification', '1', 'auth');

        $this->signup(['current_country' => 'France', 'current_region' => 'Île-de-France'])
            ->assertCreated()
            ->assertJsonPath('user.email_verified', false);

        Event::assertDispatched(Registered::class);
    }

    public function test_the_email_check_says_whether_an_address_is_free(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $this->getJson(route('api.v1.auth.email-available', ['email' => 'new@example.com']))->assertJsonPath('available', true);
        $this->getJson(route('api.v1.auth.email-available', ['email' => 'taken@example.com']))->assertJsonPath('available', false);
        $this->getJson(route('api.v1.auth.email-available', ['email' => 'not-an-email']))->assertJsonPath('available', false);
    }

    public function test_the_server_names_the_region_registration_will_save(): void
    {
        $this->createUser(['current_country' => 'United Kingdom']);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => [
            'city' => 'Manchester', 'state' => 'England', 'country' => 'United Kingdom',
        ]])]);

        $this->postJson(route('api.v1.location.resolve'), ['lat' => 53.48, 'lng' => -2.24])
            ->assertOk()
            ->assertJsonPath('country', 'United Kingdom')
            ->assertJsonPath('region', 'North West')
            ->assertJsonPath('source', 'gps')
            ->assertJsonPath('country_users', 1);
    }

    public function test_without_a_position_the_phones_ip_is_used(): void
    {
        Http::fake(['ipapi.co/*' => Http::response([
            'country_name' => 'France', 'region' => 'Île-de-France', 'city' => 'Paris', 'latitude' => 48.85, 'longitude' => 2.35,
        ])]);

        $this->postJson(route('api.v1.location.resolve'))
            ->assertOk()
            ->assertJsonPath('country', 'France')
            ->assertJsonPath('region', 'Île-de-France')
            ->assertJsonPath('source', 'ip');
    }

    public function test_forgot_password_sends_the_same_link_as_the_web(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->postJson(route('api.v1.auth.forgot-password'), ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson(route('api.v1.auth.forgot-password'), ['email' => 'nobody@example.com'])->assertJsonValidationErrors('email');
    }

    public function test_onboarding_from_start_to_finish(): void
    {
        $user = $this->createUser(['current_country' => 'United Kingdom', 'current_region' => 'London', 'onboarded_at' => null, 'language_pref' => 'en']);
        $this->actingAs($user, 'sanctum');

        $show = $this->getJson(route('api.v1.onboarding.show'))->assertOk();
        $rooms = collect($show->json('rooms'));
        $this->assertSame([true, false], $rooms->pluck('is_national')->all()); // national first
        $this->assertStringStartsWith('Hey **', $show->json('welcome'));

        $this->postJson(route('api.v1.onboarding.kamer'), ['messages' => [['role' => 'user', 'content' => 'Just exploring']]])
            ->assertOk()
            ->assertJsonPath('reply', "That's great! Let's discover your communities now. 👇");

        $this->postJson(route('api.v1.onboarding.rooms'), ['room_ids' => $rooms->pluck('id')->all()])->assertOk();
        $this->assertSame(2, YardRoomMember::where('user_id', $user->id)->count());

        $this->patchJson(route('api.v1.me.update'), ['bio' => 'From Douala, now in London.'])
            ->assertJsonPath('user.bio', 'From Douala, now in London.');

        $this->postJson(route('api.v1.onboarding.complete'))->assertJsonPath('user.onboarded', true);
    }

    public function test_only_community_rooms_can_be_joined_from_onboarding(): void
    {
        $user = $this->createUser();
        $private = YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => RoomType::PrivateGroup, 'is_system_room' => false]);

        $this->actingAs($user, 'sanctum')->postJson(route('api.v1.onboarding.rooms'), ['room_ids' => [$private->id]])->assertOk();

        $this->assertFalse(YardRoomMember::where('user_id', $user->id)->where('room_id', $private->id)->exists());
    }

    public function test_the_language_switch_is_saved(): void
    {
        $user = $this->createUser(['language_pref' => 'en']);

        $this->actingAs($user, 'sanctum')->patchJson(route('api.v1.me.update'), ['language_pref' => 'fr'])
            ->assertJsonPath('user.language_pref', 'fr');
    }

    public function test_configuration_tells_the_auth_screens_what_they_need(): void
    {
        $this->getJson(route('api.v1.configuration'))
            ->assertOk()
            ->assertJsonPath('auth.google', false)
            ->assertJsonPath('auth.apple', false)
            ->assertJsonPath('auth.founding_seats_left', 20)
            ->assertJsonPath('countries.0', 'Cameroon')
            ->assertJsonStructure(['links' => ['terms', 'privacy', 'about', 'contact'], 'app' => ['logo_on_light_url']]);
    }
}
