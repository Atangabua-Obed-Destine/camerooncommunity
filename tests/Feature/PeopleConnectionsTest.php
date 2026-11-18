<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConnection;
use App\Services\ConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The People page's connection cards talk to the GoConnect JSON endpoints. These
 * tests pin what a card relies on: a failure comes back as a failure (so the card
 * can say so instead of flipping), and a request can be declined from the card.
 */
class PeopleConnectionsTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function requestFrom(User $from, User $to): void
    {
        app(ConnectionService::class)->request($from, $to);
    }

    public function test_a_request_can_be_declined_from_the_card(): void
    {
        $me   = $this->createUser(['username' => 'me']);
        $them = $this->createUser(['username' => 'them']);
        $this->requestFrom($them, $me);

        $this->actingAs($me)
            ->postJson(route('yard.connections.decline'), ['user_id' => $them->id])
            ->assertOk()
            ->assertJson(['status' => 'declined', 'connection_state' => 'none']);

        $this->assertNull(UserConnection::between($me->id, $them->id));

        // A second tap finds nothing left to decline, which is the outcome asked for.
        $this->actingAs($me)
            ->postJson(route('yard.connections.decline'), ['user_id' => $them->id])
            ->assertOk();
    }

    public function test_the_sender_cannot_decline_their_own_request(): void
    {
        $me   = $this->createUser(['username' => 'sender']);
        $them = $this->createUser(['username' => 'receiver']);
        $this->requestFrom($me, $them);

        $this->actingAs($me)
            ->postJson(route('yard.connections.decline'), ['user_id' => $them->id])
            ->assertStatus(422)
            ->assertJson(['error' => 'no_pending_request', 'connection_state' => 'outgoing']);

        $this->assertNotNull(UserConnection::between($me->id, $them->id));
    }

    public function test_accepting_a_withdrawn_request_is_a_failure_not_a_connection(): void
    {
        $me   = $this->createUser(['username' => 'late']);
        $them = $this->createUser(['username' => 'changed-mind']);
        $this->requestFrom($them, $me);
        app(ConnectionService::class)->cancel($them, $me->id);

        // Before, this answered "connected", and the card showed "Connected ✓".
        $this->actingAs($me)
            ->postJson(route('yard.connections.accept'), ['user_id' => $them->id])
            ->assertStatus(422)
            ->assertJson(['error' => 'no_pending_request', 'connection_state' => 'none']);
    }

    public function test_a_second_accept_is_still_a_success(): void
    {
        $me   = $this->createUser(['username' => 'double']);
        $them = $this->createUser(['username' => 'tap']);
        $this->requestFrom($them, $me);

        foreach ([1, 2] as $tap) {
            $this->actingAs($me)
                ->postJson(route('yard.connections.accept'), ['user_id' => $them->id])
                ->assertOk()
                ->assertJson(['connection_state' => 'connected']);
        }
    }

    public function test_search_narrows_my_connections(): void
    {
        $me    = $this->createUser(['username' => 'searcher']);
        $amara = $this->createUser(['username' => 'amarafon', 'name' => 'Amara Fon']);
        $bello = $this->createUser(['username' => 'bellondi', 'name' => 'Bello Ndi']);

        foreach ([$amara, $bello] as $friend) {
            $this->requestFrom($friend, $me);
            app(ConnectionService::class)->accept($me, $friend->id);
        }

        // The cards' title attributes; the notification bell also names them.
        $all = $this->actingAs($me)->get(route('people', ['tab' => 'connections']));
        $all->assertOk()->assertSee('title="amarafon"', false)->assertSee('title="bellondi"', false);

        // By full name, which the card doesn't show: the search covers it anyway.
        $found = $this->actingAs($me)->get(route('people', ['tab' => 'connections', 'q' => 'Amara F']));
        $found->assertOk()->assertSee('title="amarafon"', false)->assertDontSee('title="bellondi"', false);
    }

    public function test_a_request_card_offers_accept_and_decline(): void
    {
        $me   = $this->createUser(['username' => 'host']);
        $them = $this->createUser(['username' => 'guest']);
        $this->requestFrom($them, $me);

        $this->actingAs($me)->get(route('people', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee(route('yard.connections.accept'), false)
            ->assertSee(route('yard.connections.decline'), false)
            ->assertSee("t('Accept', 'Accepter')", false)
            ->assertDontSee("t('Confirm', 'Accepter')", false);
    }

    public function test_pages_outside_goconnect_can_show_toasts(): void
    {
        $me = $this->createUser(['username' => 'toasty']);

        $this->actingAs($me)->get(route('people'))->assertOk()->assertSee('x-data="cnToasts()"', false);
    }
}
