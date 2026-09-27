<?php

namespace Tests\Feature;

use App\Livewire\Yard\ChatRoom;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class ChatRoomOpenCostTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function roomWithMessages(int $count, $me, $other): YardRoom
    {
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([$me, $other] as $u) {
            YardRoomMember::create([
                'tenant_id' => $this->tenant->id,
                'room_id'   => $room->id,
                'user_id'   => $u->id,
                'role'      => 'member',
                'joined_at' => now()->subYear(),
            ]);
        }

        for ($i = 0; $i < $count; $i++) {
            YardMessage::create([
                'tenant_id'  => $this->tenant->id,
                'room_id'    => $room->id,
                'user_id'    => $i % 2 ? $me->id : $other->id,
                'uuid'       => (string) Str::uuid(),
                'content'    => 'Message ' . $i,
                'created_at' => now()->subMinutes($count - $i),
            ]);
        }

        return $room;
    }

    public function test_opening_a_room_loads_one_screenful_not_the_whole_history(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser(['username' => 'other']);
        $room  = $this->roomWithMessages(120, $me, $other);

        // The first page is what the user waits for, and every bubble is several
        // KB of HTML. Older messages arrive on scroll.
        $component = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room]);

        $this->assertCount(25, $component->viewData('roomMessages') ?? $component->instance()->roomMessages);
        $component->assertSee('Message 119')->assertDontSee('Message 10 ');
    }

    public function test_reopening_the_room_already_open_is_a_no_op(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser(['username' => 'other2']);
        $room  = $this->roomWithMessages(3, $me, $other);

        // The room list opens a room from the browser and again from its own
        // server response; the second must not redo the work.
        Livewire::actingAs($me)
            ->test(ChatRoom::class, ['room' => $room])
            ->call('loadMore')                 // perPage 25 -> 75
            ->call('loadRoom', $room->id)
            ->assertSet('perPage', 75);        // untouched, so loadRoom bailed out
    }

    public function test_a_locked_away_room_cannot_be_opened_from_the_browser(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser(['username' => 'other3']);
        $open  = $this->roomWithMessages(2, $me, $other);
        $away  = $this->roomWithMessages(2, $me, $other);

        YardRoomMember::where('room_id', $away->id)
            ->where('user_id', $me->id)
            ->update(['auto_archived_at' => now()]);

        Livewire::actingAs($me)
            ->test(ChatRoom::class, ['room' => $open])
            ->call('loadRoom', $away->id)
            ->assertDispatched('toast')
            ->assertSet('room.id', $open->id);
    }

    public function test_rendering_never_waits_on_the_translation_service(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser(['username' => 'other4']);
        $room  = $this->roomWithMessages(4, $me, $other);

        YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $me->id)
            ->update(['auto_translate_lang' => 'fr']);

        // Opening a room must not make network calls, one per message.
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldNotReceive('translate');
        });

        $component = Livewire::actingAs($me)->test(ChatRoom::class, ['room' => $room]);
        $component->assertSee('Message 0');
        $this->assertTrue($component->instance()->hasPendingTranslations());
    }

    public function test_the_deferred_call_is_what_translates(): void
    {
        $me    = $this->createUser();
        $other = $this->createUser(['username' => 'other5']);
        $room  = $this->roomWithMessages(2, $me, $other);

        YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $me->id)
            ->update(['auto_translate_lang' => 'fr']);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('translate')->atLeast()->once()->andReturn('Bonjour');
        });

        Livewire::actingAs($me)
            ->test(ChatRoom::class, ['room' => $room])
            ->call('translatePending')
            ->assertSee('Bonjour');
    }
}
