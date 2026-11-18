<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardJoinRequest;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\AIService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The app's shell: the profile's Settings dialog, Kamer AI and the header
 * bell. Each goes through the service the web uses (ProfileSettings,
 * KamerConversation, NotificationFeed), so these check the web's rules hold
 * for a phone.
 */
class ProfileShellApiTest extends TestCase
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

    // ── Settings ─────────────────────────────────────────────────────────

    public function test_the_settings_form_saves_what_the_web_form_saves(): void
    {
        $ama = $this->createUser(['name' => 'Ama', 'show_archived_away' => true]);

        $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.me.settings'))
            ->assertOk()
            ->assertJsonPath('user.name', 'Ama')
            ->assertJsonPath('regions.0', 'Adamawa')
            ->assertJsonCount(10, 'regions');

        // An absent switch is off, as an unticked box is on the web.
        $this->putJson(route('api.v1.me.profile'), [
            'name' => '  Ama Ndip ', 'language_pref' => 'fr', 'home_region' => 'Southwest', 'home_city' => 'Buea',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Ama Ndip')
            ->assertJsonPath('user.language_pref', 'fr')
            ->assertJsonPath('user.home_region', 'Southwest')
            ->assertJsonPath('user.home_city', 'Buea')
            ->assertJsonPath('user.show_archived_away', false);

        $this->assertSame('Ama Ndip', $ama->fresh()->name);
    }

    public function test_the_settings_form_refuses_what_the_web_refuses(): void
    {
        $ama = $this->createUser(['name' => 'Ama']);

        $this->actingAs($ama, 'sanctum')->putJson(route('api.v1.me.profile'), [
            'name' => '', 'language_pref' => 'de', 'home_city' => str_repeat('x', 101),
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'language_pref', 'home_city']);

        $this->assertSame('Ama', $ama->fresh()->name);
        $this->putJson(route('api.v1.me.profile'), ['name' => 'X'])->assertStatus(422);
    }

    public function test_photos_replace_the_old_file_and_removing_deletes_it(): void
    {
        Storage::fake('public');
        $ama = $this->createUser();

        $first = $this->actingAs($ama, 'sanctum')->post(route('api.v1.me.avatar'),
            ['avatar' => UploadedFile::fake()->image('me.jpg', 200, 200)], ['Accept' => 'application/json'])->assertOk();
        $old = $ama->fresh()->avatar;
        Storage::disk('public')->assertExists($old);
        $this->assertNotNull($first->json('user.avatar_url'));

        $this->post(route('api.v1.me.avatar'), ['avatar' => UploadedFile::fake()->image('me2.png')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($old);

        $this->deleteJson(route('api.v1.me.avatar'))->assertOk()->assertJsonPath('user.avatar_url', null);
        $this->assertNull($ama->fresh()->avatar);

        $this->post(route('api.v1.me.cover'), ['cover_photo' => UploadedFile::fake()->image('wide.webp', 1200, 400)], ['Accept' => 'application/json'])
            ->assertOk();
        $cover = $ama->fresh()->cover_photo;
        Storage::disk('public')->assertExists($cover);
        $this->deleteJson(route('api.v1.me.cover'))->assertOk();
        Storage::disk('public')->assertMissing($cover);
    }

    public function test_photos_must_be_images_within_the_web_limits(): void
    {
        Storage::fake('public');
        $ama = $this->createUser();
        $json = ['Accept' => 'application/json'];

        $this->actingAs($ama, 'sanctum')
            ->post(route('api.v1.me.avatar'), ['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], $json)
            ->assertStatus(422)->assertJsonValidationErrors('avatar');
        $this->post(route('api.v1.me.avatar'), ['avatar' => UploadedFile::fake()->image('big.jpg')->size(6000)], $json)
            ->assertStatus(422)->assertJsonValidationErrors('avatar');
        // The cover allows 8 MB, more than the avatar's 5.
        $this->post(route('api.v1.me.cover'), ['cover_photo' => UploadedFile::fake()->image('c.jpg')->size(6000)], $json)->assertOk();
        $this->post(route('api.v1.me.cover'), ['cover_photo' => UploadedFile::fake()->image('c.gif')->size(9000)], $json)
            ->assertStatus(422);
    }

    public function test_the_form_says_how_deletion_is_confirmed(): void
    {
        $byPassword = $this->createUser(['password' => bcrypt('secret-pass')]);
        $google = $this->createUser(['google_id' => 'g-123']);

        $this->actingAs($byPassword, 'sanctum')->getJson(route('api.v1.me.settings'))->assertJsonPath('delete_by_password', true);
        $this->actingAs($google, 'sanctum')->getJson(route('api.v1.me.settings'))->assertJsonPath('delete_by_password', false);
    }

    public function test_the_web_settings_still_work_through_the_same_service(): void
    {
        $ama = $this->createUser(['name' => 'Ama']);

        $this->actingAs($ama)->put(route('profile.update'), ['name' => 'Ama B', 'language_pref' => 'en'])->assertRedirect();
        $this->assertSame('Ama B', $ama->fresh()->name);
        $this->assertFalse((bool) $ama->fresh()->show_archived_away);
    }

    // ── Kamer AI ─────────────────────────────────────────────────────────

    public function test_kamer_greets_answers_remembers_and_clears(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('chat')->once()->withArgs(function (array $messages, $lang) {
            // Kamer is told the whole conversation, its welcome included.
            return count($messages) === 2 && $messages[1] === ['role' => 'user', 'content' => 'How do I sell?'] && $lang === 'English';
        })->andReturn('Tap Sell on GoMarket.');

        $ama = $this->createUser(['name' => 'Ama']);

        $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.kamer.show'))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.role', 'assistant')
            ->assertJsonPath('suggestions.1', 'How do I sell on GoMarket?');
        $this->assertStringContainsString('Hi Ama!', $this->getJson(route('api.v1.kamer.show'))->json('messages.0.content'));

        $this->postJson(route('api.v1.kamer.send'), ['message' => '  How do I sell?  '])
            ->assertOk()
            ->assertJsonPath('reply', ['role' => 'assistant', 'content' => 'Tap Sell on GoMarket.']);

        $this->getJson(route('api.v1.kamer.show'))->assertJsonCount(3, 'messages')
            ->assertJsonPath('messages.2.content', 'Tap Sell on GoMarket.');

        $cleared = $this->deleteJson(route('api.v1.kamer.clear'))->assertOk()->assertJsonCount(1, 'messages');
        $this->assertStringContainsString('Conversation cleared! How can I help you, Ama?', $cleared->json('messages.0.content'));
        $this->assertNull(Cache::get("kamer:history:{$ama->id}"));
        // Coming back later starts from the welcome, as a fresh web page does.
        $this->assertStringContainsString('Hi Ama!', $this->getJson(route('api.v1.kamer.show'))->json('messages.0.content'));
    }

    public function test_kamer_apologises_in_the_members_language_when_the_ai_cannot_answer(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(false);
        $ai->shouldReceive('chat')->andReturn(null);

        $ama = $this->createUser(['name' => 'Ama']);

        $this->actingAs($ama, 'sanctum')
            ->withHeader('X-localization', 'fr')
            ->postJson(route('api.v1.kamer.send'), ['message' => 'Bonjour'])
            ->assertOk()
            ->assertJsonPath('reply.content', 'Désolé, je ne suis pas disponible pour le moment. Réessayez plus tard.')
            ->assertJsonPath('available', false);
    }

    public function test_kamer_refuses_an_empty_or_huge_message_and_keeps_each_members_own(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('chat')->andReturn('ok');
        [$ama, $ben] = [$this->createUser(), $this->createUser()];

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.kamer.send'), ['message' => '   '])->assertStatus(422);
        $this->postJson(route('api.v1.kamer.send'), ['message' => str_repeat('a', 2001)])->assertStatus(422);
        $this->postJson(route('api.v1.kamer.send'), ['message' => 'mine'])->assertOk();

        $this->actingAs($ben, 'sanctum')->getJson(route('api.v1.kamer.show'))->assertJsonCount(1, 'messages');
    }

    public function test_the_conversation_keeps_its_last_fifty_turns(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('chat')->andReturn('ok');
        $ama = $this->createUser();

        $long = [];
        for ($i = 0; $i < 49; $i++) {
            $long[] = ['role' => $i % 2 ? 'assistant' : 'user', 'content' => "m{$i}"];
        }
        Cache::put("kamer:history:{$ama->id}", $long, now()->addDay());

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.kamer.send'), ['message' => 'last'])->assertOk();

        $messages = $this->getJson(route('api.v1.kamer.show'))->assertJsonCount(50, 'messages')->json('messages');
        $this->assertSame('m1', $messages[0]['content']);
        $this->assertSame('ok', end($messages)['content']);
    }

    public function test_sending_to_kamer_is_rate_limited(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('chat')->andReturn('ok');
        $ama = $this->createUser();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.kamer.send'), ['message' => "q{$i}"])->assertOk();
        }
        $this->postJson(route('api.v1.kamer.send'), ['message' => 'one more'])->assertStatus(429);
    }

    // ── The bell ─────────────────────────────────────────────────────────

    private function room(array $attrs = []): YardRoom
    {
        return YardRoom::factory()->create(array_merge(['tenant_id' => $this->tenant->id], $attrs));
    }

    private function join(User $user, YardRoom $room, array $attrs = []): void
    {
        YardRoomMember::create(array_merge([
            'tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'joined_at' => now()->subYear(), 'last_read_at' => now()->subDay(),
        ], $attrs));
    }

    private function say(User $user, YardRoom $room, string $text, array $attrs = []): YardMessage
    {
        return YardMessage::create(array_merge([
            'tenant_id' => $this->tenant->id, 'uuid' => (string) Str::uuid(), 'room_id' => $room->id,
            'user_id' => $user->id, 'message_type' => MessageType::Text, 'content' => $text,
        ], $attrs));
    }

    public function test_the_bell_lists_unread_dms_mentions_requests_and_stored_notifications(): void
    {
        $me = $this->createUser(['username' => 'ama']);
        $ben = $this->createUser(['username' => 'ben']);
        $cho = $this->createUser(['username' => 'cho']);

        // 1. A DM with two unread.
        $dm = $this->room(['room_type' => RoomType::DirectMessage]);
        $this->join($me, $dm);
        $this->join($ben, $dm);
        $this->say($ben, $dm, 'One');
        $this->say($ben, $dm, 'Two');
        $dm->update(['last_message_at' => now(), 'last_message_preview' => 'Two']);

        // 2. A mention in a group.
        $group = $this->room(['name' => 'Buea Kamer']);
        $this->join($me, $group);
        $this->join($cho, $group);
        $this->say($cho, $group, '@ama look', ['mentioned_user_ids' => [$me->id]]);

        // 3. A join request for my private group.
        $mine = $this->room(['room_type' => RoomType::PrivateGroup, 'name' => 'Family', 'created_by' => $me->id]);
        YardJoinRequest::create(['room_id' => $mine->id, 'user_id' => $cho->id, 'status' => 'pending']);

        // 4. A connection request to me.
        [$a, $b] = UserConnection::canonicalPair($me->id, $ben->id);
        UserConnection::create(['tenant_id' => $this->tenant->id, 'user_a_id' => $a, 'user_b_id' => $b,
            'requested_by' => $ben->id, 'status' => UserConnection::STATUS_PENDING]);

        // 5. A stored one (an order event).
        app(NotificationService::class)->send($me, 'order', 'Order paid', 'Your buyer paid.', ['url' => route('marketplace.orders')]);

        $res = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.notifications.index'))->assertOk();

        $res->assertJsonPath('counts', ['all' => 2 + 1 + 1 + 1 + 1, 'mentions' => 1, 'groups' => 1, 'connections' => 1])
            ->assertJsonPath('user_channel', "tenant.{$this->tenant->id}.user.{$me->id}");
        $items = collect($res->json('items'))->keyBy('kind');
        $this->assertSame(['connection', 'dm', 'join_request', 'mention', 'stored'], $items->keys()->sort()->values()->all());
        $this->assertSame(2, $items['dm']['unread']);
        $this->assertSame($dm->id, $items['dm']['room_id']);
        $this->assertSame('Two', $items['dm']['body']);
        $this->assertSame("user:{$ben->id}", $items['dm']['seed']);
        $this->assertSame('@cho • Buea Kamer', $items['mention']['title']);
        $this->assertSame('@ama look', $items['mention']['body']);
        $this->assertSame($mine->id, $items['join_request']['room_id']);
        $this->assertSame($ben->id, $items['connection']['user_id']);
        $this->assertSame('Order paid', $items['stored']['title']);
        $this->assertNull($items['stored']['seed']);

        // Opening the stored one reads it and says where it leads.
        $id = Str::after($items['stored']['id'], 'note:');
        $this->postJson(route('api.v1.notifications.open', $id))->assertOk()->assertJsonPath('url', route('marketplace.orders'));
        $this->getJson(route('api.v1.notifications.index'))->assertJsonPath('counts.all', 5);

        // Mark all read empties the chats, not the requests.
        $this->postJson(route('api.v1.notifications.read-all'))->assertOk();
        $this->getJson(route('api.v1.notifications.index'))
            ->assertJsonPath('counts', ['all' => 2, 'mentions' => 0, 'groups' => 1, 'connections' => 1]);
    }

    public function test_nobody_opens_someone_elses_notification(): void
    {
        [$me, $other] = [$this->createUser(), $this->createUser()];
        app(NotificationService::class)->send($other, 'order', 'Theirs', 'Private.', ['url' => '/x']);
        $id = \DB::table('notifications')->where('user_id', $other->id)->value('id');

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.notifications.open', $id))->assertNotFound();
        $this->assertNull(\DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_a_deleted_room_mentions_nobody(): void
    {
        [$me, $cho] = [$this->createUser(), $this->createUser()];
        $group = $this->room();
        $this->join($me, $group);
        $this->say($cho, $group, 'hey', ['mentioned_user_ids' => [$me->id]]);
        $group->delete();

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.notifications.index'))->assertJsonPath('counts.mentions', 0);
    }

    public function test_signed_out_requests_are_refused(): void
    {
        $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
        $this->getJson(route('api.v1.kamer.show'))->assertUnauthorized();
        $this->getJson(route('api.v1.me.settings'))->assertUnauthorized();
    }
}
