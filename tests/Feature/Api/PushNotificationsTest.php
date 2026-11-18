<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\ConnectionService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Push notifications, for when the app is closed.
 *
 * Google is faked: the OAuth token exchange and FCM's send endpoint. What is
 * checked is who gets a push, what it says, and the data the app routes a tap
 * with (kind, room_id, user_id, notification_id, link), because that payload
 * is the contract with the app.
 */
class PushNotificationsTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        YardRoomMember::$suppressJoinAnnouncement = true;

        // A service account key in Google's format, around a throwaway RSA key
        // made only for these tests.
        $this->keyFile = storage_path('framework/testing/fcm-'.uniqid().'.json');
        @mkdir(dirname($this->keyFile), 0777, true);
        file_put_contents($this->keyFile, json_encode([
            'type' => 'service_account',
            'project_id' => 'cameroon-network-test',
            'client_email' => 'push@cameroon-network-test.iam.gserviceaccount.com',
            'private_key' => file_get_contents(base_path('tests/Fixtures/fcm-test-only.pem')),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $this->keyFile]);

        $this->fakeGoogle();
        // Pushes go out after the response; outside a request there is none,
        // so send them as they are made.
        $this->withoutDefer();
    }

    protected function tearDown(): void
    {
        YardRoomMember::$suppressJoinAnnouncement = false;
        @unlink($this->keyFile);
        parent::tearDown();
    }

    /** What FCM answers a send with; a test may change it. */
    private array $fcmAnswer = [['name' => 'projects/x/messages/1'], 200];

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => fn () => Http::response(...$this->fcmAnswer),
        ]);
    }

    /** @return list<array> the FCM messages sent, in order */
    private function sent(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com'))
            ->map(fn ($pair) => $pair[0]->data()['message'])
            ->values()
            ->all();
    }

    private function phone(User $user, string $token): void
    {
        DeviceToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);
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

    public function test_the_app_registers_its_token_and_it_follows_whoever_is_signed_in(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.devices.store'), ['token' => 'tok-1', 'platform' => 'android'])
            ->assertOk()->assertJsonPath('registered', true);
        // Ben signs in on the same phone: the token is his now, not both.
        $this->actingAs($ben, 'sanctum')->postJson(route('api.v1.devices.store'), ['token' => 'tok-1', 'platform' => 'android'])->assertOk();

        $this->assertSame([$ben->id], DeviceToken::where('token', 'tok-1')->pluck('user_id')->all());

        $this->deleteJson(route('api.v1.devices.destroy'), ['token' => 'tok-1'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_direct_message_reaches_the_other_members_phone_and_opens_that_chat(): void
    {
        [$ama, $ben] = [$this->createUser(['username' => 'ama-a1b2c']), $this->createUser()];
        $dm = $this->room(['room_type' => RoomType::DirectMessage, 'is_system_room' => false, 'origin' => 'marketplace']);
        $this->join($ama, $dm);
        $this->join($ben, $dm);
        $this->phone($ama, 'ama-phone');
        $this->phone($ben, 'ben-phone');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.store', $dm), ['content' => 'Is the chair still available?'])
            ->assertCreated();

        $sent = $this->sent();
        $this->assertCount(1, $sent, 'the sender is never pushed their own message');
        $this->assertSame('ben-phone', $sent[0]['token']);
        $this->assertSame('Is the chair still available?', $sent[0]['notification']['body']);
        $this->assertSame('message', $sent[0]['data']['kind']);
        $this->assertSame((string) $dm->id, $sent[0]['data']['room_id']);
        $this->assertStringContainsString('?c='.$dm->id, $sent[0]['data']['link']);
        $this->assertSame('messages', $sent[0]['android']['notification']['channel_id']);
        $this->assertSame('room-'.$dm->id, $sent[0]['android']['notification']['tag']);
    }

    public function test_a_muted_chat_stays_quiet(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser()];
        $dm = $this->room(['room_type' => RoomType::DirectMessage, 'is_system_room' => false, 'origin' => 'marketplace']);
        $this->join($ama, $dm);
        $this->join($ben, $dm, ['is_muted' => true]);
        $this->phone($ben, 'ben-phone');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.store', $dm), ['content' => 'hello'])->assertCreated();

        $this->assertSame([], $this->sent());
    }

    public function test_a_public_room_only_pushes_the_members_it_mentions(): void
    {
        [$ama, $ben, $cho] = [$this->createUser(['username' => 'ama-a1b2c']), $this->createUser(['username' => 'ben-x9y8z']), $this->createUser()];
        $city = $this->room(['room_type' => RoomType::City, 'name' => 'Douala']);
        foreach ([$ama, $ben, $cho] as $u) {
            $this->join($u, $city);
        }
        $this->phone($ben, 'ben-phone');
        $this->phone($cho, 'cho-phone');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.store', $city), ['content' => 'Who is around tonight?'])->assertCreated();
        $this->assertSame([], $this->sent(), 'nobody is pinged for every message in a public room');

        $this->postJson(route('api.v1.yard.messages.store', $city), ['content' => '@ben-x9y8z are you coming?'])->assertCreated();
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('ben-phone', $sent[0]['token']);
        $this->assertSame('mention', $sent[0]['data']['kind']);
        $this->assertStringContainsString('Douala', $sent[0]['notification']['title']);
        // Handles carry a hyphen; the mention used to stop at it ("@ben").
        $this->assertStringContainsString('@ben-x9y8z', $sent[0]['notification']['body']);
    }

    public function test_a_mention_gets_through_a_muted_room(): void
    {
        [$ama, $ben] = [$this->createUser(['username' => 'ama-a1b2c']), $this->createUser(['username' => 'ben-x9y8z'])];
        $city = $this->room(['room_type' => RoomType::City, 'name' => 'Douala']);
        $this->join($ama, $city);
        $this->join($ben, $city, ['is_muted' => true]);
        $this->phone($ben, 'ben-phone');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.store', $city), ['content' => '@ben-x9y8z look'])->assertCreated();

        $this->assertCount(1, $this->sent());
    }

    public function test_a_connection_request_reaches_the_member_it_is_for_in_their_language(): void
    {
        [$ama, $ben] = [$this->createUser(['username' => 'ama-a1b2c']), $this->createUser(['language_pref' => 'fr'])];
        $this->phone($ben, 'ben-phone');

        app(ConnectionService::class)->request($ama, $ben);

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('Nouvelle demande de connexion', $sent[0]['notification']['title']);
        $this->assertSame('connection', $sent[0]['data']['kind']);
        $this->assertSame((string) $ama->id, $sent[0]['data']['user_id']);
    }

    public function test_a_bell_notification_is_pushed_with_the_id_that_marks_it_read(): void
    {
        $ben = $this->createUser();
        $this->phone($ben, 'ben-phone');

        app(NotificationService::class)->send($ben, 'order_paid', 'Payment received', 'Ama paid for the chair.', ['url' => 'https://example.test/marketplace/orders']);

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('stored', $sent[0]['data']['kind']);
        $this->assertSame($ben->notifications()->first()->id, $sent[0]['data']['notification_id']);
        $this->assertSame('https://example.test/marketplace/orders', $sent[0]['data']['link']);
        $this->assertSame('activity', $sent[0]['android']['notification']['channel_id']);
    }

    public function test_a_token_firebase_no_longer_knows_is_forgotten(): void
    {
        $ben = $this->createUser();
        $this->phone($ben, 'uninstalled');
        $this->fcmAnswer = [['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404];

        app(NotificationService::class)->send($ben, 'x', 'Title', 'Body');

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_deleted_account_stops_reaching_its_phones(): void
    {
        $ben = $this->createUser();
        $this->phone($ben, 'ben-phone');

        app(\App\Services\AccountDeletion::class)->anonymise($ben);

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_without_the_firebase_key_nothing_is_sent_and_nothing_breaks(): void
    {
        config(['services.fcm.credentials' => null]);
        $this->app->forgetInstance(\App\Services\Push\FcmClient::class);
        $ben = $this->createUser();
        $this->phone($ben, 'ben-phone');

        app(NotificationService::class)->send($ben, 'x', 'Title', 'Body');

        $this->assertSame([], $this->sent());
        $this->assertSame(1, $ben->notifications()->count(), 'the bell still gets it');
    }
}
