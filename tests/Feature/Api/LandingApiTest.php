<?php

namespace Tests\Feature\Api;

use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The app's landing screen: the web's guest home page, before anyone has an
 * account.
 *
 * Its copy is fixed, so what the server answers is the one thing that
 * changes: whether the Kamer bubble shows, and Kamer's conversation with a
 * visitor once they tap it. A visitor has no token and no session, only the
 * id their phone made up, so the tests are about that id: it is required, it
 * keeps one visitor's conversation apart from another's, and it never lets a
 * visitor read a member's.
 */
class LandingApiTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private const VISITOR = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_landing_answers_with_nobody_signed_in(): void
    {
        $this->getJson(route('api.v1.landing'))
            ->assertOk()
            ->assertJsonStructure(['kamer' => ['available']]);
    }

    public function test_the_kamer_bubble_shows_only_while_the_ai_is_available(): void
    {
        // The guest layout's @if(app(AIService::class)->isAvailable()): an
        // admin switching the AI off at /admin/ai takes the bubble away.
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true, false);

        $this->getJson(route('api.v1.landing'))->assertJsonPath('kamer.available', true);
        $this->getJson(route('api.v1.landing'))->assertJsonPath('kamer.available', false);
    }

    public function test_kamer_greets_a_visitor_as_friend_answers_and_clears(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('chat')->once()->andReturn('Join free, then pick your city.');

        $visitor = ['X-Guest-Id' => self::VISITOR];

        // A visitor has no name, so Kamer says "friend", as the web's guest panel does.
        $this->withHeaders($visitor)->getJson(route('api.v1.landing.kamer.show'))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', "Hi friend! 👋 I'm Kamer, your guide on Cameroon Network. What would you like to explore today?")
            ->assertJsonPath('suggestions.0', 'What can I do here?');

        $this->withHeaders($visitor)->postJson(route('api.v1.landing.kamer.send'), ['message' => 'How do I start?'])
            ->assertOk()
            ->assertJsonPath('reply.content', 'Join free, then pick your city.');

        $this->withHeaders($visitor)->getJson(route('api.v1.landing.kamer.show'))->assertJsonCount(3, 'messages');

        $this->withHeaders($visitor)->deleteJson(route('api.v1.landing.kamer.clear'))
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Conversation cleared! How can I help you, friend?');
        $this->assertNull(Cache::get('kamer:guest:'.self::VISITOR));
    }

    public function test_each_visitor_keeps_their_own_conversation_in_their_language(): void
    {
        $ai = $this->mock(AIService::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('chat')->andReturn('ok');

        $this->withHeaders(['X-Guest-Id' => self::VISITOR])
            ->postJson(route('api.v1.landing.kamer.send'), ['message' => 'mine'])->assertOk();

        $other = str_repeat('f', 32);
        $this->withHeaders(['X-Guest-Id' => $other, 'X-Localization' => 'fr'])
            ->getJson(route('api.v1.landing.kamer.show'))
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', "Salut friend ! 👋 Je suis Kamer, ton guide sur Cameroon Network. Comment puis-je t'aider aujourd'hui ?");
    }

    public function test_a_visitor_needs_a_well_formed_id_and_cannot_reach_a_members_conversation(): void
    {
        $member = $this->createUser();
        Cache::put("kamer:history:{$member->id}", [['role' => 'user', 'content' => 'private']], now()->addDay());

        // No id, or one that is not 32 hex characters: refused, so the id can
        // only ever be a key of its own and never steer into someone else's.
        $this->getJson(route('api.v1.landing.kamer.show'))->assertStatus(422);
        $this->withHeaders(['X-Guest-Id' => "history:{$member->id}"])
            ->getJson(route('api.v1.landing.kamer.show'))->assertStatus(422);
        $this->withHeaders(['X-Guest-Id' => strtoupper(self::VISITOR)])
            ->postJson(route('api.v1.landing.kamer.send'), ['message' => 'hi'])->assertStatus(422);
    }

    public function test_a_visitors_message_is_checked_like_a_members(): void
    {
        $visitor = ['X-Guest-Id' => self::VISITOR];

        $this->withHeaders($visitor)->postJson(route('api.v1.landing.kamer.send'), ['message' => '   '])->assertStatus(422);
        $this->withHeaders($visitor)->postJson(route('api.v1.landing.kamer.send'), ['message' => str_repeat('a', 2001)])->assertStatus(422);
    }
}
