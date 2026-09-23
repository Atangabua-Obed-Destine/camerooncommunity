<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserContactName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class UserPreviewTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function connect(User $a, User $b, string $status, ?int $requestedBy = null): void
    {
        [$x, $y] = UserConnection::canonicalPair($a->id, $b->id);

        UserConnection::create([
            'tenant_id'    => $this->tenant->id,
            'user_a_id'    => $x,
            'user_b_id'    => $y,
            'requested_by' => $requestedBy ?? $a->id,
            'status'       => $status,
        ]);
    }

    public function test_preview_returns_the_expected_payload(): void
    {
        $viewer = $this->createUser();
        $other  = $this->createUser([
            'username'        => 'ngwa',
            'name'            => 'Ngwa Divine',
            'bio'             => 'Bamenda born, London based.',
            'current_city'    => 'London',
            'current_country' => 'UK',
        ]);

        $response = $this->actingAs($viewer)->getJson("/yard/user-preview/{$other->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'id', 'name', 'username', 'avatar', 'bio', 'location',
            'nickname', 'state', 'is_self', 'dm_room_id', 'dm_room_slug', 'profile_url',
        ]);
        $response->assertJsonPath('id', $other->id);
        $response->assertJsonPath('username', 'ngwa');
        $response->assertJsonPath('bio', 'Bamenda born, London based.');
        $response->assertJsonPath('is_self', false);
        $response->assertJsonPath('state', 'none');
        $this->assertStringContainsString('London', $response->json('location'));
    }

    public function test_preview_can_be_looked_up_by_username(): void
    {
        $viewer = $this->createUser();
        $other  = $this->createUser(['username' => 'mention-target']);

        // @mentions carry a username, not an id.
        $this->actingAs($viewer)
            ->getJson('/yard/user-preview/mention-target')
            ->assertOk()
            ->assertJsonPath('id', $other->id);
    }

    public function test_a_saved_nickname_becomes_the_displayed_name(): void
    {
        $viewer = $this->createUser();
        $other  = $this->createUser(['username' => 'realhandle', 'name' => 'Real Name']);

        UserContactName::create([
            'tenant_id'       => $this->tenant->id,
            'owner_user_id'   => $viewer->id,
            'contact_user_id' => $other->id,
            'nickname'        => 'Uncle Paul',
        ]);

        $response = $this->actingAs($viewer)->getJson("/yard/user-preview/{$other->id}");

        $response->assertJsonPath('name', 'Uncle Paul');
        $response->assertJsonPath('nickname', 'Uncle Paul');
        // The real handle still shows, so you know who it actually is.
        $response->assertJsonPath('username', 'realhandle');
    }

    public function test_connection_states_drive_the_call_to_action(): void
    {
        $viewer = $this->createUser();

        $connected = $this->createUser(['username' => 'c1']);
        $this->connect($viewer, $connected, UserConnection::STATUS_ACCEPTED);

        $outgoing = $this->createUser(['username' => 'c2']);
        $this->connect($viewer, $outgoing, UserConnection::STATUS_PENDING, $viewer->id);

        $incoming = $this->createUser(['username' => 'c3']);
        $this->connect($viewer, $incoming, UserConnection::STATUS_PENDING, $incoming->id);

        foreach ([[$connected, 'connected'], [$outgoing, 'outgoing'], [$incoming, 'incoming']] as [$user, $expected]) {
            $this->actingAs($viewer)
                ->getJson("/yard/user-preview/{$user->id}")
                ->assertJsonPath('state', $expected);
        }
    }

    public function test_blocked_users_expose_no_bio_or_location(): void
    {
        $viewer  = $this->createUser();
        $blocked = $this->createUser([
            'username'     => 'blocked',
            'bio'          => 'secret bio',
            'current_city' => 'Douala',
        ]);

        $this->connect($viewer, $blocked, UserConnection::STATUS_BLOCKED, $viewer->id);

        $response = $this->actingAs($viewer)->getJson("/yard/user-preview/{$blocked->id}");

        $response->assertJsonPath('state', 'blocked-by-me');
        $response->assertJsonPath('bio', null);
        $response->assertJsonPath('location', null);
        $response->assertDontSee('secret bio');
    }

    public function test_viewing_yourself_is_flagged(): void
    {
        $viewer = $this->createUser(['username' => 'me']);

        $this->actingAs($viewer)
            ->getJson("/yard/user-preview/{$viewer->id}")
            ->assertJsonPath('is_self', true)
            ->assertJsonPath('state', 'self')
            ->assertJsonPath('nickname', null);
    }

    public function test_unknown_identifier_returns_404(): void
    {
        // Bogus @mentions are common: the regex matches any @word.
        $this->actingAs($this->createUser())
            ->getJson('/yard/user-preview/not-a-real-user')
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $other = $this->createUser(['username' => 'someone']);

        $this->get("/yard/user-preview/{$other->id}")->assertRedirect('/login');
    }

    public function test_the_card_is_mounted_on_authenticated_pages(): void
    {
        $this->actingAs($this->createUser())
            ->get('/yard')
            ->assertOk()
            ->assertSee('open-user-preview', false);
    }
}
