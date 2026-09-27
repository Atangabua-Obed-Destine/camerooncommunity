<?php

namespace Tests\Feature;

use App\Livewire\Yard\ChatRoom;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class YardRoomDeepLinkTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function room(string $name = 'Bamenda Boys'): YardRoom
    {
        return YardRoom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => $name,
        ]);
    }

    private function join($user, YardRoom $room): void
    {
        YardRoomMember::create([
            'tenant_id' => $this->tenant->id,
            'room_id'   => $room->id,
            'user_id'   => $user->id,
            'role'      => 'member',
        ]);
    }

    public function test_room_id_in_the_url_opens_that_room(): void
    {
        $user = $this->createUser();
        $room = $this->room();
        $this->join($user, $room);

        // This is what a refresh does: the room must come back with the page,
        // not be lost to the room list.
        $response = $this->actingAs($user)
            ->get('/yard?room=' . $room->id)
            ->assertOk()
            ->assertSee($room->name);

        // The Alpine shell is seeded too, so the chat panel is open on first paint
        // instead of flashing the room list.
        $response->assertSee('yardApp(' . $room->id . ',', false);
    }

    public function test_a_slug_works_in_the_url_too(): void
    {
        $user = $this->createUser();
        $room = $this->room('Douala Traders');
        $this->join($user, $room);

        $this->actingAs($user)
            ->get('/yard?room=' . $room->slug)
            ->assertOk()
            ->assertSee($room->name);
    }

    public function test_a_room_you_are_not_in_is_ignored_not_opened(): void
    {
        $user = $this->createUser();
        $room = $this->room('Private Cabal');

        // A stale or hand-typed link must still land on a working Yard.
        $this->actingAs($user)
            ->get('/yard?room=' . $room->id)
            ->assertOk()
            ->assertDontSee('Private Cabal');
    }

    public function test_an_unknown_room_is_ignored(): void
    {
        $this->actingAs($this->createUser())
            ->get('/yard?room=nonsense-slug')
            ->assertOk();

        $this->actingAs($this->createUser())
            ->get('/yard?room=999999')
            ->assertOk();
    }

    public function test_the_room_page_renders_the_same_yard_shell(): void
    {
        $user = $this->createUser();
        $room = $this->room('Kumba Crew');
        $this->join($user, $room);

        // /yard/room/{slug} used to be a second, divergent layout.
        $this->actingAs($user)
            ->get('/yard/room/' . $room->slug)
            ->assertOk()
            ->assertSee('yard-container', false)
            ->assertSee($room->name);
    }

    public function test_entering_a_room_requires_membership(): void
    {
        $user    = $this->createUser();
        $mine    = $this->room('My Room');
        $notMine = $this->room('Not My Room');
        $this->join($user, $mine);

        // 'room-selected' comes from the browser, so the id cannot be trusted.
        Livewire::actingAs($user)
            ->test(ChatRoom::class, ['room' => $mine])
            ->call('loadRoom', $notMine->id)
            ->assertDispatched('toast')
            ->assertSet('room.id', $mine->id);
    }

    public function test_switching_to_a_room_you_belong_to_still_works(): void
    {
        $user  = $this->createUser();
        $first = $this->room('First Room');
        $next  = $this->room('Next Room');
        $this->join($user, $first);
        $this->join($user, $next);

        Livewire::actingAs($user)
            ->test(ChatRoom::class, ['room' => $first])
            ->call('loadRoom', $next->id)
            ->assertSet('room.id', $next->id);
    }
}
