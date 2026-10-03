<?php

namespace Tests\Feature;

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

class MessageMultiSelectTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function room($members): YardRoom
    {
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ($members as $u) {
            YardRoomMember::create([
                'tenant_id' => $this->tenant->id,
                'room_id'   => $room->id,
                'user_id'   => $u->id,
                'role'      => 'member',
                'joined_at' => now()->subYear(),
            ]);
        }

        return $room;
    }

    private function message(YardRoom $room, $author, string $text): YardMessage
    {
        return YardMessage::create([
            'tenant_id' => $this->tenant->id,
            'room_id'   => $room->id,
            'user_id'   => $author->id,
            'uuid'      => (string) Str::uuid(),
            'content'   => $text,
        ]);
    }

    public function test_deleting_a_selection_only_touches_your_own_messages(): void
    {
        $me    = $this->createUser(['username' => 'selector']);
        $them  = $this->createUser(['username' => 'other']);
        $room  = $this->room([$me, $them]);

        $mine1  = $this->message($room, $me, 'mine one');
        $mine2  = $this->message($room, $me, 'mine two');
        $theirs = $this->message($room, $them, 'theirs');

        Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room])
            ->call('deleteMessages', [$mine1->id, $mine2->id, $theirs->id]);

        $this->assertTrue((bool) $mine1->fresh()->is_deleted);
        $this->assertTrue((bool) $mine2->fresh()->is_deleted);
        // Someone else's message is skipped, not an error for the whole batch.
        $this->assertFalse((bool) $theirs->fresh()->is_deleted);
    }

    public function test_starring_a_mixed_selection_stars_everything(): void
    {
        $me   = $this->createUser(['username' => 'starrer']);
        $them = $this->createUser(['username' => 'friend']);
        $room = $this->room([$me, $them]);

        $a = $this->message($room, $them, 'a');
        $b = $this->message($room, $them, 'b');

        $component = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room]);

        // One already starred: the batch should finish with both starred.
        $component->call('toggleStar', $a->id);
        $component->call('starMessages', [$a->id, $b->id]);

        $this->assertSame(2, DB::table('yard_message_stars')->where('user_id', $me->id)->count());

        // All starred: the same action clears them.
        $component->call('starMessages', [$a->id, $b->id]);
        $this->assertSame(0, DB::table('yard_message_stars')->where('user_id', $me->id)->count());
    }

    public function test_forwarding_a_selection_copies_them_all_in_order(): void
    {
        $me   = $this->createUser(['username' => 'forwarder']);
        $them = $this->createUser(['username' => 'mate']);
        $from = $this->room([$me, $them]);
        $to   = $this->room([$me, $them]);

        $first  = $this->message($from, $me, 'first');
        $second = $this->message($from, $me, 'second');

        Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $from])
            ->call('forwardMessages', [$second->id, $first->id], $to->id);

        // The room also holds the "x joined" system messages, so compare the tail.
        $forwarded = YardMessage::where('room_id', $to->id)
            ->where('user_id', $me->id)
            ->where('message_type', '!=', 'system')
            ->orderBy('id')
            ->pluck('content')
            ->all();

        $this->assertSame(['first', 'second'], $forwarded, 'forwards should arrive oldest first');
    }

    public function test_a_reply_quotes_the_original_and_links_back_to_it(): void
    {
        $me   = $this->createUser(['username' => 'replier']);
        $them = $this->createUser(['username' => 'quoted']);
        $room = $this->room([$me, $them]);

        $original = $this->message($room, $them, 'the original line');
        $reply    = $this->message($room, $me, 'my answer');
        $reply->update(['parent_message_id' => $original->id]);

        $response = $this->actingAs($me)->get('/yard?room=' . $room->id);

        $response->assertOk();
        // The quote carries the original's id, so tapping it can jump there.
        $response->assertSee('jumpToMessage(' . $original->id . ')', false);
        $response->assertSee('the original line');
        // And the target is addressable.
        $response->assertSee('id="msg-' . $original->id . '"', false);
    }

    public function test_the_chat_renders_the_selection_machinery(): void
    {
        $me   = $this->createUser(['username' => 'uicheck']);
        $them = $this->createUser(['username' => 'uiother']);
        $room = $this->room([$me, $them]);
        $this->message($room, $them, 'hello');

        $response = $this->actingAs($me)->get('/yard?room=' . $room->id);

        $response->assertOk();
        $response->assertSee('selToggle', false);     // tap to pick
        $response->assertSee('yard-sel-bar', false);  // the bar
        $response->assertSee('Select messages');      // the desktop way in
    }
}
