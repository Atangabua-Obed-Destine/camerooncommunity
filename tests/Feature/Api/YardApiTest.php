<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * GoConnect from the app. Every endpoint goes through the services the web's
 * chat uses, so these check the web's rules hold for a phone: who sees what,
 * who may post, and that nobody acts in a room they are not in.
 */
class YardApiTest extends TestCase
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

    private function room(array $attrs = []): YardRoom
    {
        return YardRoom::factory()->create(array_merge(['tenant_id' => $this->tenant->id], $attrs));
    }

    private function join(User $user, YardRoom $room, array $attrs = []): void
    {
        YardRoomMember::create(array_merge([
            'tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'joined_at' => now()->subDay(),
        ], $attrs));
    }

    private function say(User $user, YardRoom $room, string $text, array $attrs = []): YardMessage
    {
        return YardMessage::create(array_merge([
            'tenant_id' => $this->tenant->id, 'uuid' => (string) \Illuminate\Support\Str::uuid(), 'room_id' => $room->id,
            'user_id' => $user->id, 'message_type' => MessageType::Text, 'content' => $text,
        ], $attrs));
    }

    public function test_the_chat_list_is_the_members_rooms_with_unread_counts(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $room = $this->room(['name' => 'Douala - Kamer']);
        $other = $this->room(['name' => 'Not mine']);
        $this->join($ama, $room, ['last_read_at' => now()->subHour()]);
        $this->join($ben, $room);
        $this->say($ben, $room, 'Bonjour !');
        $room->update(['last_message_at' => now(), 'last_message_preview' => 'Bonjour !', 'last_message_user_id' => $ben->id]);

        $res = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.rooms.index'))->assertOk();

        $this->assertSame([$room->id], collect($res->json('rooms'))->pluck('id')->all());
        $res->assertJsonPath('rooms.0.unread_count', 1)
            ->assertJsonPath('rooms.0.last_message.preview', 'Bonjour !')
            ->assertJsonPath('rooms.0.last_message.is_mine', false)
            ->assertJsonPath('counts.unread', 1);
        $this->assertNotContains($other->id, collect($res->json('rooms'))->pluck('id'));

        // The app listens on every listed room, and on the member's own channel.
        $t = $this->tenant->id;
        $res->assertJsonPath('rooms.0.channel', "tenant.{$t}.room.{$room->id}")
            ->assertJsonPath('user_channel', "tenant.{$t}.user.{$ama->id}");
        $this->getJson(route('api.v1.yard.rooms.show', $room))
            ->assertJsonPath('room.receipts_channel', "tenant.{$t}.user.{$ama->id}.receipts");
    }

    public function test_a_member_sees_nothing_from_before_they_joined_or_from_people_they_blocked(): void
    {
        [$ama, $ben, $cho] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $room = $this->room();
        $this->join($ama, $room, ['joined_at' => now()->subMinutes(10)]);
        $this->join($ben, $room);
        $this->join($cho, $room);

        $this->say($ben, $room, 'before Ama joined')->forceFill(['created_at' => now()->subHour()])->save();
        $this->say($ben, $room, 'after Ama joined');
        $this->say($cho, $room, 'from someone Ama blocked');
        UserConnection::create(['user_a_id' => min($ama->id, $cho->id), 'user_b_id' => max($ama->id, $cho->id), 'status' => UserConnection::STATUS_BLOCKED, 'requested_by' => $ama->id]);

        $texts = collect($this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.messages.index', $room))->assertOk()->json('messages'))->pluck('content');

        $this->assertSame(['after Ama joined'], $texts->all());
    }

    public function test_history_pages_backwards_with_a_cursor(): void
    {
        $ama = $this->createUser();
        $room = $this->room();
        $this->join($ama, $room);
        $ids = collect(range(1, 5))->map(fn ($i) => $this->say($ama, $room, "m$i")->id);

        $first = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.messages.index', ['room' => $room, 'limit' => 3]))->assertOk();
        $this->assertSame(['m3', 'm4', 'm5'], collect($first->json('messages'))->pluck('content')->all());
        $first->assertJsonPath('has_more', true);

        $older = $this->getJson(route('api.v1.yard.messages.index', ['room' => $room, 'limit' => 3, 'before' => $ids[2]]))->assertOk();
        $this->assertSame(['m1', 'm2'], collect($older->json('messages'))->pluck('content')->all());
        $older->assertJsonPath('has_more', false);
    }

    public function test_sending_writes_the_message_and_updates_the_room(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $room = $this->room();
        $other = $this->room();
        $this->join($ama, $room);
        $this->join($ben, $room); // someone else in the room, so the tick starts at "sent"
        $foreign = $this->say($ben, $other, 'elsewhere');

        $res = $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.store', $room), [
            'content' => 'Salut tout le monde', 'reply_to' => $foreign->id,
        ])->assertCreated()
            ->assertJsonPath('message.mine', true)
            ->assertJsonPath('message.status', 'sent')
            // A reply can't quote a message from another room.
            ->assertJsonPath('message.parent', null);

        $this->assertSame('Salut tout le monde', $room->fresh()->last_message_preview);
        $this->assertSame($ama->id, $room->fresh()->last_message_user_id);
        $this->assertDatabaseHas('yard_messages', ['id' => $res->json('message.id'), 'content' => 'Salut tout le monde']);
    }

    public function test_a_dm_needs_a_connection_unless_it_started_on_gomarket(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $dm = $this->room(['room_type' => RoomType::DirectMessage, 'is_system_room' => false]);
        $this->join($ama, $dm);
        $this->join($ben, $dm);

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.store', $dm), ['content' => 'hi'])
            ->assertForbidden()->assertJsonPath('code', 'not_connected');

        $dm->update(['origin' => 'marketplace']);
        $this->postJson(route('api.v1.yard.messages.store', $dm), ['content' => 'is it still available?'])->assertCreated();
    }

    public function test_nobody_reads_writes_or_reacts_in_a_room_they_are_not_in(): void
    {
        [$ama, $stranger] = [$this->createUser(), $this->createUser()];
        $room = $this->room();
        $this->join($ama, $room);
        $msg = $this->say($ama, $room, 'private');

        $this->actingAs($stranger, 'sanctum');
        $this->getJson(route('api.v1.yard.rooms.show', $room))->assertForbidden();
        $this->getJson(route('api.v1.yard.messages.index', $room))->assertForbidden();
        $this->postJson(route('api.v1.yard.messages.store', $room), ['content' => 'x'])->assertForbidden();
        $this->postJson(route('api.v1.yard.messages.react', $msg), ['emoji' => '👍'])->assertForbidden();
        $this->assertDatabaseMissing('yard_message_reactions', ['message_id' => $msg->id]);
    }

    public function test_only_the_author_edits_or_deletes(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $room = $this->room();
        $this->join($ama, $room);
        $this->join($ben, $room);
        $msg = $this->say($ama, $room, 'typo');

        $this->actingAs($ben, 'sanctum')->patchJson(route('api.v1.yard.messages.update', $msg), ['content' => 'hacked'])->assertForbidden();
        $this->deleteJson(route('api.v1.yard.messages.destroy', $msg))->assertForbidden();

        $this->actingAs($ama, 'sanctum')->patchJson(route('api.v1.yard.messages.update', $msg), ['content' => 'fixed'])
            ->assertOk()->assertJsonPath('message.content', 'fixed')->assertJsonPath('message.is_edited', true);
        $this->deleteJson(route('api.v1.yard.messages.destroy', $msg))->assertOk();
        $this->assertTrue($msg->fresh()->is_deleted);
        $this->assertNull($msg->fresh()->content);
    }

    public function test_a_reaction_toggles_and_knows_whose_it_is(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $room = $this->room();
        $this->join($ama, $room);
        $this->join($ben, $room);
        $msg = $this->say($ben, $room, 'Ndolé tonight?');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.react', $msg), ['emoji' => '🔥'])
            ->assertOk()->assertJsonPath('message.reactions.0.emoji', '🔥')
            ->assertJsonPath('message.reactions.0.count', 1)
            ->assertJsonPath('message.reactions.0.mine', true);

        $this->postJson(route('api.v1.yard.messages.react', $msg), ['emoji' => '🔥'])
            ->assertOk()->assertJsonPath('message.reactions', []);
    }

    public function test_reading_a_room_clears_its_unread_badge(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $room = $this->room();
        $this->join($ama, $room, ['last_read_at' => now()->subHour()]);
        $this->join($ben, $room);
        $this->say($ben, $room, 'new');
        $room->update(['last_message_at' => now()]);

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.rooms.read', $room))->assertOk();

        $this->travel(1)->seconds();
        $this->getJson(route('api.v1.yard.rooms.index'))->assertJsonPath('rooms.0.unread_count', 0);
    }

    public function test_a_photo_is_stored_where_the_web_stores_it(): void
    {
        Storage::fake('public');
        $ama = $this->createUser();
        $room = $this->room();
        $this->join($ama, $room);

        $res = $this->actingAs($ama, 'sanctum')->post(route('api.v1.yard.messages.media', $room), [
            'type' => 'image', 'file' => UploadedFile::fake()->image('ndole.jpg'), 'caption' => 'Dinner',
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('message.type', 'image')
            ->assertJsonPath('message.content', 'Dinner');

        $path = YardMessage::find($res->json('message.id'))->media_path;
        $this->assertStringStartsWith("yard/media/{$room->id}/", $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('📷 Photo: Dinner', $room->fresh()->last_message_preview);
    }

    public function test_joining_a_suggested_room_counts_the_member_once(): void
    {
        $ama = $this->createUser(['active_country' => 'United Kingdom']);
        $national = YardRoom::factory()->national('United Kingdom')->create(['tenant_id' => $this->tenant->id, 'members_count' => 5]);

        $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.rooms.index'))
            ->assertJsonPath('suggested.0.id', $national->id);

        $this->postJson(route('api.v1.yard.rooms.join', $national))->assertOk();
        $this->postJson(route('api.v1.yard.rooms.join', $national))->assertOk();

        $this->assertSame(6, $national->fresh()->members_count);
    }

    public function test_the_app_can_authorise_its_private_channels_with_a_token(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        [$ama, $stranger] = [$this->createUser(), $this->createUser()];
        $room = $this->room();
        $this->join($ama, $room);
        $channel = "private-tenant.{$this->tenant->id}.room.{$room->id}";

        $token = $ama->createToken('phone')->plainTextToken;
        $this->withToken($token)->postJson(route('api.v1.broadcasting.auth'), ['socket_id' => '1234.5678', 'channel_name' => $channel])
            ->assertOk()->assertJsonStructure(['auth']);

        $this->app['auth']->forgetGuards();
        $this->withToken($stranger->createToken('phone')->plainTextToken)
            ->postJson(route('api.v1.broadcasting.auth'), ['socket_id' => '1234.5678', 'channel_name' => $channel])
            ->assertForbidden();
    }
}
