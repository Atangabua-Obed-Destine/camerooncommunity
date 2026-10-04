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

/**
 * What opening a room costs to send.
 *
 * Livewire re-renders the whole component for every round trip, so anything
 * carried in its HTML is paid for again on each send, reaction and scroll-back
 * — not only on the first paint. Two things had grown quietly: a 66 KB script
 * tag, and a hover toolbar repeated on every single message. These are the
 * guards so they do not come back.
 */
class ChatRoomPayloadTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function busyRoom(int $messages, int $speakers, $me): YardRoom
    {
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        $people = [$me];
        for ($i = 0; $i < $speakers; $i++) {
            $people[] = $this->createUser(['username' => 'talker' . $i . Str::random(4)]);
        }

        foreach ($people as $u) {
            YardRoomMember::create([
                'tenant_id' => $this->tenant->id,
                'room_id'   => $room->id,
                'user_id'   => $u->id,
                'role'      => 'member',
                'joined_at' => now()->subYear(),
            ]);
        }

        for ($i = 0; $i < $messages; $i++) {
            YardMessage::create([
                'tenant_id'  => $this->tenant->id,
                'room_id'    => $room->id,
                'user_id'    => $people[$i % count($people)]->id,
                'uuid'       => (string) Str::uuid(),
                'content'    => 'Message ' . $i . ' with a realistic amount of text in it.',
                'created_at' => now()->subMinutes($messages - $i),
            ]);
        }

        return $room;
    }

    public function test_the_chat_script_is_bundled_not_inlined_in_the_component(): void
    {
        $me   = $this->createUser();
        $room = $this->busyRoom(5, 1, $me);

        $html = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room])->html();

        $this->assertStringNotContainsString('<script', $html,
            'The chat behaviour belongs in resources/js/yard-chat.js, where it is '
            . 'cached, not in the component HTML, which is re-sent on every round trip.');
    }

    public function test_a_message_does_not_carry_its_own_hover_toolbar(): void
    {
        $me   = $this->createUser();
        $room = $this->busyRoom(25, 3, $me);

        $html = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room])->html();

        // One shared toolbar for the thread; nothing per bubble.
        $this->assertSame(1, substr_count($html, 'yard-hov__row'));
        $this->assertStringNotContainsString('yard-msg__quick-emoji ', $html);
        $this->assertStringNotContainsString('yard-msg__chevron', $html);
    }

    public function test_a_full_page_of_messages_stays_within_a_sane_size(): void
    {
        $me   = $this->createUser();
        $room = $this->busyRoom(25, 5, $me);

        $html = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room])->html();
        $kb = strlen($html) / 1024;

        // Was 369 KB; roughly 195 KB after the script and the per-message
        // toolbar came out. The ceiling leaves room to grow without hiding
        // another regression of that size.
        $this->assertLessThan(260, $kb, "Opening a room now ships {$kb} KB of HTML.");
    }

    public function test_opening_a_room_does_not_query_per_author(): void
    {
        $me   = $this->createUser();
        $room = $this->busyRoom(25, 6, $me);

        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room])->html();

        // Six people talking used to mean six separate nickname lookups before
        // the first bubble rendered; they are primed in one query now.
        $this->assertLessThan(30, $queries, "Opening a room ran {$queries} queries.");
    }
}
