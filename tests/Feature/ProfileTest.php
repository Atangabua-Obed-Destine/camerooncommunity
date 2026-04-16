<?php

namespace Tests\Feature;

use App\Models\CommunityPointsLog;
use App\Models\UserBadge;
use App\Models\YardRoomMember;
use App\Models\YardRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class ProfileTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_guest_cannot_access_profile(): void
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/profile');
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    public function test_profile_shows_stats(): void
    {
        $user = $this->createUser();
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        YardRoomMember::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $response = $this->actingAs($user)->get('/profile');
        $response->assertStatus(200);
    }

    public function test_user_can_update_name(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'New Name',
            'language_pref' => 'en',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_can_update_language_preference(): void
    {
        $user = $this->createUser(['language_pref' => 'en']);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'language_pref' => 'fr',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertEquals('fr', $user->language_pref->value);
    }

    public function test_name_is_required(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => '',
            'language_pref' => 'en',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_language_pref_must_be_valid(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'language_pref' => 'de',
        ]);

        $response->assertSessionHasErrors('language_pref');
    }

    public function test_user_can_set_origin_info(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'language_pref' => 'en',
            'home_region' => 'North West',
            'home_city' => 'Bamenda',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertEquals('North West', $user->home_region);
        $this->assertEquals('Bamenda', $user->home_city);
    }
}
