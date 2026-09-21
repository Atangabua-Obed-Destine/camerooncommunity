<?php

namespace Tests\Feature;

use App\Models\SponsoredAd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class HomeStoriesTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function ad(array $attributes = []): SponsoredAd
    {
        return SponsoredAd::create(array_merge([
            'title'           => 'Test Ad',
            'description'     => 'An advert',
            'image_url'       => 'https://example.com/ad.jpg',
            'link_url'        => 'https://example.com',
            'link_label'      => 'Shop now',
            'advertiser_name' => 'Acme',
            'placement'       => 'yard_sidebar',
            'status'          => 'active',
        ], $attributes));
    }

    public function test_home_ads_endpoint_returns_ads_of_any_placement(): void
    {
        $this->ad(['title' => 'Sidebar ad', 'placement' => 'yard_sidebar']);
        $this->ad(['title' => 'Home ad', 'placement' => 'home_banner']);

        $response = $this->actingAs($this->createUser())->getJson('/ads/home');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            ['id', 'title', 'description', 'image', 'video', 'advertiser', 'cta', 'has_link'],
        ]);
        $response->assertSee('Sidebar ad');
        $response->assertSee('Home ad');
    }

    public function test_home_ads_endpoint_excludes_inactive_ads(): void
    {
        $this->ad(['title' => 'Draft', 'status' => 'draft']);
        $this->ad(['title' => 'Paused', 'status' => 'paused']);
        $this->ad(['title' => 'Expired', 'status' => 'active', 'expires_at' => now()->subDay()]);
        $this->ad(['title' => 'Not started', 'status' => 'active', 'starts_at' => now()->addDay()]);

        $this->actingAs($this->createUser())
            ->getJson('/ads/home')
            ->assertOk()
            ->assertJsonCount(0);
    }

    /**
     * Regression guard: the sidebar's endpoint counts an impression on every fetch
     * and refetches every 5 minutes, which is why the live data reads ~35k
     * impressions against 1 click. Fetching the strip must never do that.
     */
    public function test_home_ads_endpoint_does_not_record_impressions(): void
    {
        $ad = $this->ad();
        $user = $this->createUser();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->getJson('/ads/home')->assertOk();
        }

        $this->assertSame(0, $ad->fresh()->impressions);
    }

    public function test_impression_endpoint_records_once_per_session(): void
    {
        $ad = $this->ad();
        $user = $this->createUser();

        $this->actingAs($user)->post("/ad/{$ad->id}/impression")->assertNoContent();
        $this->actingAs($user)->post("/ad/{$ad->id}/impression")->assertNoContent();

        $this->assertSame(1, $ad->fresh()->impressions);
    }

    public function test_ad_click_records_click_and_redirects(): void
    {
        $ad = $this->ad(['link_url' => 'https://advertiser.example']);

        $this->actingAs($this->createUser())
            ->get("/ad/{$ad->id}/click")
            ->assertRedirect('https://advertiser.example');

        $this->assertSame(1, $ad->fresh()->clicks);
    }

    public function test_authenticated_home_includes_the_stories_strip(): void
    {
        $this->actingAs($this->createUser())
            ->get('/')
            ->assertOk()
            ->assertSee('homeStories()', false)
            ->assertSee('/ads/home', false);
    }

    public function test_guest_home_does_not_include_the_stories_strip(): void
    {
        // Guests get the marketing page, not the feed.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('homeStories()', false);
    }

    public function test_ad_endpoints_require_login(): void
    {
        $ad = $this->ad();

        $this->get('/ads/home')->assertRedirect('/login');
        $this->post("/ad/{$ad->id}/impression")->assertRedirect('/login');
    }
}
