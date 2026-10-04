<?php

namespace Tests\Feature;

use App\Livewire\Yard\ChatRoom;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * What an arriving message costs.
 *
 * Livewire re-renders the whole component to answer any call, and the thread
 * is around 190 KB of it. A message used to cost two of those — one to receive
 * it and another, 1.5 seconds later, to mark it read — so a lively room queued
 * re-renders behind each other and messages landed seconds after they arrived.
 *
 * The bubble is now painted from the broadcast itself and the server refresh
 * is coalesced. These guard the server half of that.
 */
class ChatRealtimeCostTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function roomWith($me, $them): YardRoom
    {
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([$me, $them] as $u) {
            YardRoomMember::create([
                'tenant_id' => $this->tenant->id,
                'room_id'   => $room->id,
                'user_id'   => $u->id,
                'role'      => 'member',
                'joined_at' => now()->subYear(),
                'last_read_at' => now()->subHour(),
            ]);
        }

        return $room;
    }

    private function messageFrom($user, YardRoom $room, string $text): YardMessage
    {
        return YardMessage::create([
            'tenant_id' => $this->tenant->id,
            'room_id'   => $room->id,
            'user_id'   => $user->id,
            'uuid'      => (string) Str::uuid(),
            'content'   => $text,
        ]);
    }

    public function test_receiving_a_message_also_marks_it_read_in_the_same_request(): void
    {
        $me   = $this->createUser();
        $them = $this->createUser(['username' => 'them']);
        $room = $this->roomWith($me, $them);

        $message = $this->messageFrom($them, $room, 'Hello there');

        $before = YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $me->id)
            ->value('last_read_at');

        Livewire::actingAs($me)
            ->test(ChatRoom::class, ['room' => $room])
            ->call('onMessageReceived', [
                'id'      => $message->id,
                'user_id' => $them->id,
            ])
            ->assertDispatched('message-sent');

        $after = YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $me->id)
            ->value('last_read_at');

        $this->assertTrue(
            $after > $before,
            'Reading used to cost a second full re-render 1.5s later; it belongs in this one.'
        );
    }

    public function test_receiving_a_message_records_delivery(): void
    {
        $me   = $this->createUser(['username' => 'me2']);
        $them = $this->createUser(['username' => 'them2']);
        $room = $this->roomWith($me, $them);

        $message = $this->messageFrom($them, $room, 'Did this land?');

        Livewire::actingAs($me)
            ->test(ChatRoom::class, ['room' => $room])
            ->call('onMessageReceived', ['id' => $message->id, 'user_id' => $them->id]);

        $this->assertDatabaseHas('yard_message_reads', [
            'message_id' => $message->id,
            'user_id'    => $me->id,
        ]);
    }

    public function test_the_refreshed_thread_contains_the_new_message(): void
    {
        // The painted bubble is replaced by this render, so if the message is
        // missing from it the message visibly disappears a moment after
        // arriving — worse than being slow.
        $me   = $this->createUser(['username' => 'me3']);
        $them = $this->createUser(['username' => 'them3']);
        $room = $this->roomWith($me, $them);

        $component = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room]);

        $message = $this->messageFrom($them, $room, 'Arrived after the room opened');

        $component->call('onMessageReceived', ['id' => $message->id, 'user_id' => $them->id])
            ->assertSee('Arrived after the room opened');
    }

    public function test_an_unknown_message_id_does_not_break_the_refresh(): void
    {
        // Broadcasts can name a message this viewer cannot see — deleted, or
        // from someone they have blocked.
        $me   = $this->createUser(['username' => 'me4']);
        $them = $this->createUser(['username' => 'them4']);
        $room = $this->roomWith($me, $them);

        Livewire::actingAs($me)
            ->test(ChatRoom::class, ['room' => $room])
            ->call('onMessageReceived', ['id' => 999999, 'user_id' => $them->id])
            ->assertOk();
    }
}
