<?php

namespace Tests\Feature\Api;

use App\Enums\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserContactName;
use App\Models\YardJoinRequest;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\ConnectionService;
use App\Services\DirectMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Finding people and starting chats from the app: the Connections sheet,
 * the new-chat search, the preview card, connection actions, DMs and
 * Communities. The rules are the web's (same services); these check a phone
 * can't step around them.
 */
class YardPeopleApiTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        YardRoomMember::$suppressJoinAnnouncement = true;
    }

    protected function tearDown(): void
    {
        YardRoomMember::$suppressJoinAnnouncement = false;
        parent::tearDown();
    }

    private function connect(User $a, User $b): void
    {
        app(ConnectionService::class)->request($a, $b);
        app(ConnectionService::class)->accept($b, $a->id);
    }

    private function room(array $attrs = []): YardRoom
    {
        return YardRoom::factory()->create(array_merge(['tenant_id' => $this->tenant->id, 'is_active' => true], $attrs));
    }

    private function member(User $u, YardRoom $r, string $role = 'member'): void
    {
        YardRoomMember::create(['tenant_id' => $this->tenant->id, 'room_id' => $r->id, 'user_id' => $u->id, 'role' => $role, 'joined_at' => now()]);
    }

    public function test_the_connections_sheet_lists_each_relationship_from_the_viewers_side(): void
    {
        $me = $this->createUser(['username' => 'ama']);
        [$friend, $asker, $asked, $stranger, $pest, $blocker] = [
            $this->createUser(['username' => 'friend']), $this->createUser(['username' => 'asker']),
            $this->createUser(['username' => 'asked']), $this->createUser(['username' => 'stranger']),
            $this->createUser(['username' => 'pest']), $this->createUser(['username' => 'blocker']),
        ];
        $this->connect($me, $friend);
        app(ConnectionService::class)->request($asker, $me);
        app(ConnectionService::class)->request($me, $asked);
        app(ConnectionService::class)->block($me, $pest->id);
        app(ConnectionService::class)->block($blocker, $me->id);
        UserContactName::create(['tenant_id' => $this->tenant->id, 'owner_user_id' => $me->id, 'contact_user_id' => $friend->id, 'nickname' => 'Bestie']);

        // The stranger shares a room: a suggestion. Blocker shares it too, and must not be.
        $room = $this->room();
        foreach ([$me, $stranger, $blocker] as $u) {
            $this->member($u, $room);
        }

        $res = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.connections.index'))->assertOk();

        $this->assertSame([$friend->id], collect($res->json('connections'))->pluck('id')->all());
        $res->assertJsonPath('connections.0.nickname', 'Bestie')
            ->assertJsonPath('connections.0.state', 'connected')
            ->assertJsonPath('incoming.0.id', $asker->id)
            ->assertJsonPath('incoming.0.state', 'incoming')
            ->assertJsonPath('sent.0.id', $asked->id)
            ->assertJsonPath('blocked.0.id', $pest->id)
            ->assertJsonPath('counts.incoming', 1)
            ->assertJsonPath('user_channel', "tenant.{$this->tenant->id}.user.{$me->id}");

        $this->assertSame([$stranger->id], collect($res->json('suggestions'))->pluck('id')->all());
        // Being blocked by someone is never shown.
        $this->assertNotContains($blocker->id, collect($res->json('blocked'))->pluck('id'));
    }

    public function test_new_chat_search_matches_names_and_whole_addresses_only(): void
    {
        $me = $this->createUser(['username' => 'me']);
        $ngwa = $this->createUser(['name' => 'Ngwa Peter', 'username' => 'ngwa', 'email' => 'ngwa@example.com']);
        $this->createUser(['name' => 'Other', 'username' => 'other', 'email' => 'other@example.com']);
        $gone = $this->createUser(['name' => 'Ngwa Gone', 'username' => 'ngwagone']);
        $gone->forceFill(['anonymised_at' => now()])->save();

        $byName = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.search', ['q' => 'ngw']))->assertOk();
        $this->assertSame([$ngwa->id], collect($byName->json('people'))->pluck('id')->all());
        $byName->assertJsonPath('people.0.state', 'none');

        // A fragment of an address finds nobody; the whole address does.
        $this->assertSame([], $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.search', ['q' => 'example.com']))->json('people'));
        $this->assertSame([$ngwa->id], collect($this->actingAs($me, 'sanctum')
            ->getJson(route('api.v1.yard.people.search', ['q' => 'ngwa@example.com']))->json('people'))->pluck('id')->all());

        // Never yourself.
        $this->assertSame([], $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.search', ['q' => 'me']))->json('people'));
    }

    public function test_connections_search_leaves_out_blocked_people_either_way(): void
    {
        $me = $this->createUser(['username' => 'me']);
        $open = $this->createUser(['name' => 'Kamer One', 'username' => 'kamer1']);
        $mine = $this->createUser(['name' => 'Kamer Two', 'username' => 'kamer2']);
        $theirs = $this->createUser(['name' => 'Kamer Three', 'username' => 'kamer3']);
        app(ConnectionService::class)->block($me, $mine->id);
        app(ConnectionService::class)->block($theirs, $me->id);

        $res = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.search', ['q' => 'kamer', 'for' => 'connections']))->assertOk();
        $this->assertSame([$open->id], collect($res->json('people'))->pluck('id')->all());

        // One character is not a search there.
        $this->assertSame([], $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.search', ['q' => 'k', 'for' => 'connections']))->json('people'));
    }

    public function test_a_request_goes_through_its_whole_life_from_the_app(): void
    {
        $me = $this->createUser();
        $them = $this->createUser();
        $act = fn (User $who, User $whom, string $a) => $this->actingAs($who, 'sanctum')
            ->postJson(route('api.v1.yard.connections.act', ['user' => $whom->id, 'action' => $a]));

        $act($me, $them, 'request')->assertOk()->assertJsonPath('state', 'outgoing');
        // A second tap is still the state asked for.
        $act($me, $them, 'request')->assertOk()->assertJsonPath('state', 'outgoing');
        // The sender can't accept their own request.
        $act($me, $them, 'accept')->assertStatus(422)->assertJsonPath('state', 'outgoing');

        $res = $act($them, $me, 'accept')->assertOk()->assertJsonPath('state', 'connected');
        // Accepting makes the DM, and says which it is.
        $this->assertNotNull($res->json('dm_room_id'));
        $this->assertTrue(YardRoomMember::where('room_id', $res->json('dm_room_id'))->where('user_id', $me->id)->exists());

        $act($me, $them, 'disconnect')->assertOk()->assertJsonPath('state', 'none');
        $act($me, $them, 'block')->assertOk()->assertJsonPath('state', 'blocked-by-me');
        // Only the blocker lifts a block.
        $act($them, $me, 'unblock')->assertStatus(422)->assertJsonPath('state', 'blocked-by-them');
        $act($me, $them, 'unblock')->assertOk();

        // Not on yourself, and not an action that doesn't exist.
        $act($me, $me, 'request')->assertStatus(422);
        $this->actingAs($me, 'sanctum')->postJson("/api/v1/yard/connections/{$them->id}/befriend")->assertNotFound();
    }

    public function test_a_request_can_be_cancelled_and_declined(): void
    {
        $me = $this->createUser();
        $them = $this->createUser();
        app(ConnectionService::class)->request($me, $them);

        $this->actingAs($them, 'sanctum')->postJson(route('api.v1.yard.connections.act', ['user' => $me->id, 'action' => 'decline']))
            ->assertOk()->assertJsonPath('state', 'none');
        $this->assertNull(UserConnection::between($me->id, $them->id));

        app(ConnectionService::class)->request($me, $them);
        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.connections.act', ['user' => $them->id, 'action' => 'cancel']))
            ->assertOk()->assertJsonPath('state', 'none');
        $this->assertNull(UserConnection::between($me->id, $them->id));
    }

    public function test_a_dm_needs_a_connection_and_opens_the_one_that_exists(): void
    {
        $me = $this->createUser();
        $them = $this->createUser();

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.dm.create'), ['user_id' => $them->id])
            ->assertStatus(403)->assertJsonPath('code', 'not_connected')->assertJsonPath('state', 'none');

        $this->connect($me, $them);
        $first = $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.dm.create'), ['user_id' => $them->id])->assertOk()->json('room_id');
        $again = $this->actingAs($them, 'sanctum')->postJson(route('api.v1.yard.dm.create'), ['user_id' => $me->id])->assertOk()->json('room_id');
        $this->assertSame($first, $again);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.dm.create'), ['user_id' => $me->id])->assertNotFound();
    }

    public function test_the_preview_card_by_id_or_username_withholds_details_when_blocked(): void
    {
        $me = $this->createUser();
        $them = $this->createUser(['username' => 'ngwa', 'bio' => 'Hello from Bamenda', 'current_country' => 'Cameroon']);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.show', ['identifier' => $them->id]))
            ->assertOk()
            ->assertJsonPath('person.username', 'ngwa')
            ->assertJsonPath('person.bio', 'Hello from Bamenda')
            ->assertJsonPath('person.state', 'none')
            ->assertJsonPath('person.is_self', false);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.show', ['identifier' => '@ngwa']))
            ->assertOk()->assertJsonPath('person.id', $them->id);

        app(ConnectionService::class)->block($them, $me->id);
        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.show', ['identifier' => 'ngwa']))
            ->assertOk()
            ->assertJsonPath('person.state', 'blocked-by-them')
            ->assertJsonPath('person.bio', null);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.show', ['identifier' => 'nobody_here']))->assertNotFound();
        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.show', ['identifier' => $me->id]))
            ->assertOk()->assertJsonPath('person.is_self', true)->assertJsonPath('person.state', 'self');
    }

    public function test_a_member_of_another_tenant_cannot_be_found_or_previewed(): void
    {
        $me = $this->createUser();
        $other = Tenant::factory()->create(['slug' => 'elsewhere', 'domain' => 'elsewhere.test', 'name' => 'Elsewhere']);
        $far = User::factory()->create(['tenant_id' => $other->id, 'username' => 'faraway', 'name' => 'Far Away']);

        $this->assertSame([], $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.search', ['q' => 'faraway']))->json('people'));
        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.people.show', ['identifier' => $far->id]))->assertNotFound();
    }

    public function test_a_nickname_is_saved_and_cleared(): void
    {
        $me = $this->createUser();
        $them = $this->createUser(['username' => 'ngwa']);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.people.nickname', ['user' => $them->id]), ['nickname' => '  Papa Ngwa '])
            ->assertOk()->assertJsonPath('nickname', 'Papa Ngwa')->assertJsonPath('name', 'Papa Ngwa');

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.people.nickname', ['user' => $them->id]), ['nickname' => ''])
            ->assertOk()->assertJsonPath('nickname', null)->assertJsonPath('name', 'ngwa');

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.people.nickname', ['user' => $me->id]), ['nickname' => 'Me'])
            ->assertStatus(422);
    }

    public function test_communities_lists_mine_and_discover_and_counts_real_messages(): void
    {
        $me = $this->createUser(['current_country' => 'Cameroon', 'current_region' => 'Littoral']);
        $national = $this->room(['room_type' => RoomType::National, 'country' => 'Cameroon', 'name' => 'Cameroon']);
        $otherNational = $this->room(['room_type' => RoomType::National, 'country' => 'Nigeria', 'name' => 'Nigeria']);
        $group = $this->room(['room_type' => RoomType::PrivateGroup, 'name' => 'Bayam Sellam', 'is_private' => false]);
        $private = $this->room(['room_type' => RoomType::PrivateGroup, 'name' => 'Secret', 'is_private' => true]);
        $this->member($me, $national);

        $res = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.communities.index'))->assertOk();

        $this->assertSame([$national->id], collect($res->json('mine'))->pluck('id')->all());
        $res->assertJsonPath('mine.0.is_default', true)->assertJsonPath('mine.0.is_member', true);
        $discover = collect($res->json('discover'))->pluck('id');
        $this->assertTrue($discover->contains($group->id) && $discover->contains($private->id));
        $this->assertFalse($discover->contains($otherNational->id));
    }

    public function test_joining_is_only_for_rooms_discover_offers(): void
    {
        $me = $this->createUser(['current_country' => 'Cameroon']);
        $a = $this->createUser();
        $b = $this->createUser();
        $group = $this->room(['room_type' => RoomType::PrivateGroup, 'is_private' => false]);
        $foreign = $this->room(['room_type' => RoomType::National, 'country' => 'Nigeria']);
        $dm = app(DirectMessageService::class)->findOrCreate($a, $b->id);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.join', ['room' => $group->id]))
            ->assertOk()->assertJsonPath('result', 'joined');
        $this->assertTrue(YardRoomMember::where('room_id', $group->id)->where('user_id', $me->id)->exists());

        // Someone else's direct messages, another country's room: no.
        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.join', ['room' => $dm->id]))->assertStatus(422);
        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.join', ['room' => $foreign->id]))->assertStatus(422);
        $this->assertFalse(YardRoomMember::where('room_id', $dm->id)->where('user_id', $me->id)->exists());
    }

    public function test_a_private_group_is_asked_and_the_ask_can_be_withdrawn(): void
    {
        $me = $this->createUser();
        $private = $this->room(['room_type' => RoomType::PrivateGroup, 'is_private' => true]);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.join', ['room' => $private->id]))
            ->assertOk()->assertJsonPath('result', 'requested');
        $this->assertFalse(YardRoomMember::where('room_id', $private->id)->where('user_id', $me->id)->exists());
        $this->assertTrue(YardJoinRequest::where('room_id', $private->id)->where('user_id', $me->id)->where('status', 'pending')->exists());

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.yard.communities.index'))
            ->assertJsonPath('discover.0.pending', true);

        $this->actingAs($me, 'sanctum')->deleteJson(route('api.v1.yard.communities.cancel-request', ['room' => $private->id]))->assertOk();
        $this->assertFalse(YardJoinRequest::where('room_id', $private->id)->where('user_id', $me->id)->exists());
    }

    public function test_a_group_is_created_and_left_but_default_rooms_stay(): void
    {
        $me = $this->createUser(['current_country' => 'Cameroon']);
        $id = $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.store'), ['name' => 'Njangi Douala', 'is_private' => true])
            ->assertCreated()->json('room_id');

        $room = YardRoom::find($id);
        $this->assertSame(RoomType::PrivateGroup, $room->room_type);
        $this->assertTrue($room->is_private);
        $this->assertSame('admin', YardRoomMember::where('room_id', $id)->where('user_id', $me->id)->value('role')?->value ?? YardRoomMember::where('room_id', $id)->where('user_id', $me->id)->value('role'));

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.leave', ['room' => $id]))->assertOk();
        $this->assertFalse(YardRoomMember::where('room_id', $id)->where('user_id', $me->id)->exists());

        $national = $this->room(['room_type' => RoomType::National, 'country' => 'Cameroon']);
        $this->member($me, $national);
        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.leave', ['room' => $national->id]))
            ->assertStatus(422)->assertJsonPath('code', 'default_room');

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.yard.communities.store'), ['name' => 'x'])->assertStatus(422);
    }
}
