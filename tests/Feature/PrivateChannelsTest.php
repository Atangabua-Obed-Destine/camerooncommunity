<?php

namespace Tests\Feature;

use App\Enums\RoomType;
use App\Events\MessageSent;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Room and member channels are private. They were public, with guessable
 * names, so anyone with a socket could read any room, DMs included, and
 * watch anyone's receipts, mentions and calls.
 */
class PrivateChannelsTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // A real (pusher-protocol) broadcaster, so /broadcasting/auth runs
        // routes/channels.php. Nothing connects anywhere: auth is a signature.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        // The rules were registered at boot on the default broadcaster of the
        // moment (null in tests); register them on this one.
        require base_path('routes/channels.php');
    }

    private function authorise($user, string $channel)
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-' . $channel,
        ]);
    }

    public function test_only_members_can_listen_to_a_room(): void
    {
        $member = $this->createUser(['username' => 'member']);
        $stranger = $this->createUser(['username' => 'stranger']);
        $dm = YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => RoomType::DirectMessage]);
        YardRoomMember::create(['tenant_id' => $this->tenant->id, 'room_id' => $dm->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);

        $channel = "tenant.{$this->tenant->id}.room.{$dm->id}";

        $this->authorise($member, $channel)->assertOk()->assertJsonStructure(['auth']);
        $this->authorise($stranger, $channel)->assertForbidden();
    }

    public function test_a_members_own_channels_are_theirs_alone(): void
    {
        $ama = $this->createUser(['username' => 'ama']);
        $other = $this->createUser(['username' => 'other']);
        $t = $this->tenant->id;

        foreach (["tenant.{$t}.user.{$ama->id}", "tenant.{$t}.user.{$ama->id}.receipts", "tenant.{$t}.user.{$ama->id}.calls"] as $channel) {
            $this->authorise($ama, $channel)->assertOk();
            $this->authorise($other, $channel)->assertForbidden();
        }
    }

    public function test_nobody_signed_in_gets_nothing(): void
    {
        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$this->tenant->id}.user.1",
        ])->assertForbidden();
    }

    public function test_messages_are_broadcast_on_the_private_room_channel(): void
    {
        $member = $this->createUser();
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);
        $message = YardMessage::factory()->create(['tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $member->id]);

        $channels = (new MessageSent($message))->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-tenant.{$this->tenant->id}.room.{$room->id}", $channels[0]->name);
    }
}
