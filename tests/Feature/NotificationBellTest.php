<?php

namespace Tests\Feature;

use App\Enums\RoomType;
use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Yard\ChatRoom;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function dmWithUnread($me, $them, int $count = 2): YardRoom
    {
        $room = YardRoom::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'room_type'  => RoomType::DirectMessage,
        ]);

        foreach ([$me, $them] as $u) {
            YardRoomMember::create([
                'tenant_id'    => $this->tenant->id,
                'room_id'      => $room->id,
                'user_id'      => $u->id,
                'role'         => 'member',
                'joined_at'    => now()->subYear(),
                'last_read_at' => now()->subDay(),
            ]);
        }

        for ($i = 0; $i < $count; $i++) {
            YardMessage::create([
                'tenant_id' => $this->tenant->id,
                'room_id'   => $room->id,
                'user_id'   => $them->id,
                'uuid'      => (string) Str::uuid(),
                'content'   => 'Unread ' . $i,
            ]);
        }

        $room->update(['last_message_at' => now()]);

        return $room->fresh();
    }

    public function test_the_badge_counts_unread_messages_and_clears_once_they_are_read(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'sender']);
        $room = $this->dmWithUnread($me, $them, 3);

        Livewire::actingAs($me)->test(NotificationBell::class)
            ->assertSet('tab', 'all')
            ->assertSeeHtml('3');

        $this->assertSame(3, Livewire::actingAs($me)->test(NotificationBell::class)->instance()->total);

        // Reading the room is what should empty the bell.
        YardRoomMember::where('room_id', $room->id)->where('user_id', $me->id)
            ->update(['last_read_at' => now()->addSecond()]);

        $this->assertSame(0, Livewire::actingAs($me)->test(NotificationBell::class)->instance()->total);
    }

    public function test_opening_a_chat_tells_the_bell_to_recount(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'sender2']);
        $room = $this->dmWithUnread($me, $them);

        // Without this the badge kept its old number until a full page load.
        Livewire::actingAs($me)
            ->test(ChatRoom::class)
            ->call('loadRoom', $room->id)
            ->assertDispatched('bell-refresh');
    }

    public function test_reopening_an_already_read_chat_does_not_ping_the_bell(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'sender3']);
        $room = $this->dmWithUnread($me, $them);

        YardRoomMember::where('room_id', $room->id)->where('user_id', $me->id)
            ->update(['last_read_at' => now()->addMinute()]);

        Livewire::actingAs($me)
            ->test(ChatRoom::class)
            ->call('loadRoom', $room->id)
            ->assertNotDispatched('bell-refresh');
    }

    public function test_a_chat_notification_links_to_a_url_that_opens_the_chat(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'sender4']);
        $room = $this->dmWithUnread($me, $them);

        $rows = Livewire::actingAs($me)->test(NotificationBell::class)->instance()->feed;

        // ?open=<id> was a dead link: the Yard only understood ?open=connections.
        $this->assertStringContainsString('?room=' . $room->id, $rows->first()['link']);
    }

    public function test_following_a_chat_notification_lands_in_that_chat(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'pinger']);
        $room = $this->dmWithUnread($me, $them);
        $room->update(['name' => 'Notification Target Room']);

        // Exactly what a user does: open the bell, click the row, land somewhere.
        $link = Livewire::actingAs($me)->test(NotificationBell::class)
            ->instance()->feed->first()['link'];

        $this->actingAs($me)
            ->get($link)
            ->assertOk()
            ->assertSee('Unread 0')          // a message from the conversation
            ->assertSee('yardApp(' . $room->id . ',', false);
    }

    public function test_a_chat_row_opens_the_room_in_place_inside_the_yard(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'inplace']);
        $room = $this->dmWithUnread($me, $them);

        // Inside the Yard a full page load to reach a room you can already see is
        // wasted; the row swaps the chat instead. The href stays for everywhere else.
        $html = Livewire::actingAs($me)->test(NotificationBell::class)->html();

        $this->assertStringContainsString("roomId: {$room->id}", $html);
        $this->assertStringContainsString('?room=' . $room->id, $html);
    }

    public function test_clicking_a_stored_notification_marks_it_read(): void
    {
        $me = $this->createUser();

        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $id,
            'tenant_id'       => $this->tenant->id,
            'user_id'         => $me->id,
            'type'            => 'order',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $me->id,
            'data'            => json_encode(['title' => 'Order paid', 'body' => 'MoMo received', 'url' => '/orders/1']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $bell = Livewire::actingAs($me)->test(NotificationBell::class);
        $this->assertSame(1, $bell->instance()->total);

        $bell->call('openStored', $id)->assertRedirect('/orders/1');

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
        $this->assertSame(0, Livewire::actingAs($me)->test(NotificationBell::class)->instance()->total);
    }
}
