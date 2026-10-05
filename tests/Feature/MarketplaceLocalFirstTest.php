<?php

namespace Tests\Feature;

use App\Livewire\Marketplace\FeedBrowse;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Nearby first, but never only nearby.
 *
 * The feed used to pin itself to the viewer's location on open. Once a pin
 * with no radius came to mean "that region", that quiet pin became a filter:
 * somebody in Centre could not see a listing in Littoral without noticing and
 * clearing a restriction they never set — and on a market this size, hiding
 * most of it is worse than showing something far away.
 *
 * So location orders the feed by default and filters only when chosen.
 */
class MarketplaceLocalFirstTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function listingIn(string $region, string $title, int $minutesAgo = 0): MarketplaceListing
    {
        static $n = 0;
        $n++;

        $category = MarketplaceCategory::firstOrCreate(
            ['slug' => 'general-local-first'],
            [
                'tenant_id' => $this->tenant->id,
                'name_en'   => 'General',
                'name_fr'   => 'Général',
                'is_active' => true,
            ]
        );

        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'username'  => 'seller' . $n . uniqid(),
        ]);

        return MarketplaceListing::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $seller->id,
            'category_id'  => $category->id,
            'title'        => $title,
            'slug'         => str($title)->slug() . '-' . $n,
            'description'  => 'For sale.',
            'price'        => 5000,
            'currency'     => 'XAF',
            'condition'    => 'good',
            'status'       => 'active',
            'region'       => $region,
            'published_at' => now()->subMinutes($minutesAgo),
            'created_at'   => now()->subMinutes($minutesAgo),
        ]);
    }

    private function viewerIn(string $region): User
    {
        return $this->createUser(['current_region' => $region]);
    }

    /** @return array<int, string> */
    private function titles($component): array
    {
        return collect($component->instance()->listings->items())->pluck('title')->all();
    }

    public function test_the_whole_market_is_visible_by_default(): void
    {
        $this->listingIn('Centre', 'Yaounde desk');
        $this->listingIn('Littoral', 'Douala fridge');
        $this->listingIn('Northwest', 'Bamenda bag');

        $titles = $this->titles(
            Livewire::actingAs($this->viewerIn('Centre'))->test(FeedBrowse::class)
        );

        $this->assertCount(3, $titles, 'Nothing should be hidden before the viewer filters.');
    }

    public function test_the_viewers_own_region_comes_first(): void
    {
        // Newest first would otherwise put the far listing on top.
        $this->listingIn('Centre', 'Yaounde desk', 90);
        $this->listingIn('Littoral', 'Douala fridge', 5);

        $titles = $this->titles(
            Livewire::actingAs($this->viewerIn('Centre'))->test(FeedBrowse::class)
        );

        $this->assertSame('Yaounde desk', $titles[0]);
        $this->assertSame('Douala fridge', $titles[1]);
    }

    public function test_the_chosen_sort_still_orders_within_each_group(): void
    {
        $this->listingIn('Centre', 'Older local', 120);
        $this->listingIn('Centre', 'Newer local', 10);
        $this->listingIn('Littoral', 'Far away', 1);

        $titles = $this->titles(
            Livewire::actingAs($this->viewerIn('Centre'))->test(FeedBrowse::class)
        );

        $this->assertSame(['Newer local', 'Older local', 'Far away'], $titles);
    }

    public function test_choosing_an_area_still_narrows_the_feed(): void
    {
        $this->listingIn('Centre', 'Yaounde desk');
        $this->listingIn('Littoral', 'Douala fridge');

        $titles = $this->titles(
            Livewire::actingAs($this->viewerIn('Centre'))
                ->test(FeedBrowse::class)
                ->call('setLocation', 'Littoral')
        );

        $this->assertSame(['Douala fridge'], $titles);
    }

    public function test_the_url_carries_no_location_until_one_is_chosen(): void
    {
        // ?loc= appearing on its own made an automatic pin look deliberate.
        $this->listingIn('Centre', 'Yaounde desk');

        Livewire::actingAs($this->viewerIn('Centre'))
            ->test(FeedBrowse::class)
            ->assertSet('locLabel', '')
            ->assertSet('radius', null);
    }

    public function test_a_viewer_with_no_region_sees_everything_unordered(): void
    {
        $this->listingIn('Centre', 'Yaounde desk', 90);
        $this->listingIn('Littoral', 'Douala fridge', 5);

        $titles = $this->titles(
            Livewire::actingAs($this->createUser(['current_region' => null]))->test(FeedBrowse::class)
        );

        $this->assertSame(['Douala fridge', 'Yaounde desk'], $titles, 'Falls back to newest first.');
    }
}
