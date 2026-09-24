<?php

namespace Tests\Feature;

use App\Enums\RoomType;
use App\Livewire\Yard\ChatRoom;
use App\Models\YardMessage;
use App\Models\YardMessageRead;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Opening a room used to cost 224 queries and sending one message 98, because of
 * four N+1s: a nickname lookup per message, a translate-setting lookup per message,
 * and a read-receipt SELECT + INSERT + tick recount per message. These tests pin
 * the fixes so the chat cannot quietly get slow again.
 */
class ChatRoomPerformanceTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private $me;
    private $other;
    private YardRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->me    = $this->createUser(['username' => 'me']);
        $this->other = $this->createUser(['username' => 'other']);

        $this->room = YardRoom::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Perf', 'slug' => 'perf',
            'country' => 'Cameroon', 'room_type' => RoomType::City, 'created_by' => $this->me->id,
        ]);

        foreach ([$this->me->id, $this->other->id] as $uid) {
            YardRoomMember::create([
                'tenant_id' => $this->tenant->id, 'room_id' => $this->room->id,
                'user_id' => $uid, 'role' => 'member', 'joined_at' => now()->subDay(),
            ]);
        }

        foreach (range(1, 60) as $i) {
            YardMessage::create([
                'tenant_id' => $this->tenant->id, 'room_id' => $this->room->id,
                'uuid' => (string) Str::uuid(),
                'user_id' => $i % 2 ? $this->me->id : $this->other->id,
                'content' => "message number {$i}",
            ]);
        }
    }

    private function countQueries(callable $fn): int
    {
        $n = 0;
        DB::listen(function () use (&$n) { $n++; });
        $fn();
        return $n;
    }

    public function test_opening_a_room_does_not_scale_with_message_count(): void
    {
        $queries = $this->countQueries(function () {
            Livewire::actingAs($this->me)->test(ChatRoom::class)->call('loadRoom', $this->room->id);
        });

        // 60 messages. Anything near 60+ means a per-message query has crept back in.
        $this->assertLessThan(60, $queries, "opening the room ran {$queries} queries");
    }

    public function test_sending_a_message_is_cheap(): void
    {
        config(['queue.default' => 'database']);   // moderation is queued in production

        $component = Livewire::actingAs($this->me)->test(ChatRoom::class)->call('loadRoom', $this->room->id);

        $queries = $this->countQueries(fn () => $component->call('sendMessage', 'hello there'));

        $this->assertLessThan(40, $queries, "sending ran {$queries} queries");
    }

    public function test_opening_a_room_marks_every_unread_message_read(): void
    {
        Livewire::actingAs($this->me)->test(ChatRoom::class)->call('loadRoom', $this->room->id);

        $fromOther = YardMessage::where('room_id', $this->room->id)
            ->where('user_id', $this->other->id)->pluck('id');

        $read = YardMessageRead::whereIn('message_id', $fromOther)
            ->where('user_id', $this->me->id)
            ->whereNotNull('read_at')
            ->count();

        $this->assertSame($fromOther->count(), $read, 'every message from the other user should be marked read');
    }

    public function test_opening_the_same_room_twice_does_not_duplicate_receipts(): void
    {
        // (message_id, user_id) is unique, so the bulk write has to tolerate a
        // second pass — two tabs, or simply reopening the room.
        Livewire::actingAs($this->me)->test(ChatRoom::class)->call('loadRoom', $this->room->id);
        $after = YardMessageRead::where('user_id', $this->me->id)->count();

        Livewire::actingAs($this->me)->test(ChatRoom::class)->call('loadRoom', $this->room->id);

        $this->assertSame($after, YardMessageRead::where('user_id', $this->me->id)->count());
    }
}
