<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * A member who has uploaded a photo should see it wherever they are pictured.
 *
 * The chat drew the photo; the header, the drawer and the admin pages each
 * drew their own circle and only ever drew the initial, so the same person was
 * a letter everywhere outside GoConnect. One component now decides, and these
 * cover the places that were wrong.
 */
class UserAvatarEverywhereTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_header_account_button_shows_the_photo(): void
    {
        $user = $this->createUser(['avatar' => 'avatars/me.jpg']);

        $this->actingAs($user)
            ->get('/yard')
            ->assertOk()
            ->assertSee('storage/avatars/me.jpg', false);
    }

    public function test_without_a_photo_it_falls_back_to_the_initial(): void
    {
        $user = $this->createUser(['username' => 'zaza', 'avatar' => null]);

        $response = $this->actingAs($user)->get('/yard')->assertOk();

        // The letter, not a broken image pointing at an empty path.
        $response->assertDontSee('src="' . asset('storage/') . '"', false);
        $this->assertStringContainsString('>Z<', $response->getContent());
    }

    public function test_an_absolute_avatar_url_is_not_prefixed_with_storage(): void
    {
        // Should an avatar ever hold a full URL rather than a path on our disk
        // — a social provider's image used as-is — it must not be glued behind
        // asset('storage/'). Asserted against the component rather than a page,
        // because other views still build that path by hand.
        $user = $this->createUser(['avatar' => 'https://example.test/photo.png']);

        $this->blade('<x-user-avatar :user="$user" />', ['user' => $user])
            ->assertSee('https://example.test/photo.png', false)
            ->assertDontSee('storage/https://example.test', false);
    }

    public function test_the_fallback_colour_is_stable_for_a_person(): void
    {
        // Same seed as the chat uses, so one member is one colour everywhere
        // rather than a different one per screen.
        $user = $this->createUser(['username' => 'stable']);

        $expected = \App\Support\AvatarPalette::colorClass('user:' . $user->id);

        $this->actingAs($user)
            ->get('/yard')
            ->assertOk()
            ->assertSee($expected, false);
    }
}
