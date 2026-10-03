<?php

namespace Tests\Feature;

use App\Livewire\Marketplace\FeedBrowse;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Support\CameroonGeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Picking an area on GoMarket has to narrow the results to it.
 *
 * It used to set the label, write ?loc=Centre&radius= into the URL and change
 * nothing: with no distance chosen the radius filter returned the query
 * untouched, so the whole country still came back and the control looked
 * broken. A place with no distance now means that place.
 */
class MarketplaceAreaFilterTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function listingIn(string $region, string $title): MarketplaceListing
    {
        // Not cached in a static: the database is refreshed between tests, so
        // a kept id points at a row that no longer exists.
        $category = MarketplaceCategory::create([
            'tenant_id' => $this->tenant->id,
            'name_en'   => 'General',
            'name_fr'   => 'Général',
            'slug'      => 'general-' . uniqid(),
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'username'  => 'seller-' . uniqid(),
        ]);

        return MarketplaceListing::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $seller->id,
            'category_id'  => $category->id,
            'title'        => $title,
            'slug'         => str($title)->slug() . '-' . uniqid(),
            'description'  => 'Something for sale.',
            'price'        => 10000,
            'currency'     => 'XAF',
            'condition'    => 'good',
            'status'       => 'active',
            'region'       => $region,
            'published_at' => now(),
        ]);
    }

    /** The feed exposes its results as a computed property, not view data. */
    private function titlesFrom($browse): \Illuminate\Support\Collection
    {
        return collect($browse->instance()->listings->items())->pluck('title');
    }

    public function test_choosing_a_region_without_a_distance_shows_only_that_region(): void
    {
        $this->listingIn('Centre', 'Yaounde desk');
        $this->listingIn('Littoral', 'Douala fridge');

        $browse = Livewire::actingAs($this->createUser())
            ->test(FeedBrowse::class)
            ->call('setLocation', 'Centre');

        $titles = $this->titlesFrom($browse);

        $this->assertContains('Yaounde desk', $titles);
        $this->assertNotContains('Douala fridge', $titles, 'A chosen area must exclude other regions.');
    }

    public function test_widening_the_radius_brings_in_the_neighbouring_regions(): void
    {
        $this->listingIn('Centre', 'Yaounde desk');
        $this->listingIn('South', 'Ebolowa chair');

        $browse = Livewire::actingAs($this->createUser())
            ->test(FeedBrowse::class)
            ->call('setLocation', 'Centre')
            ->set('radius', 200);

        $titles = $this->titlesFrom($browse);

        $this->assertContains('Yaounde desk', $titles);
        $this->assertContains('Ebolowa chair', $titles);
    }

    public function test_clearing_the_area_shows_the_whole_country_again(): void
    {
        $this->listingIn('Centre', 'Yaounde desk');
        $this->listingIn('Littoral', 'Douala fridge');

        $browse = Livewire::actingAs($this->createUser())
            ->test(FeedBrowse::class)
            ->call('setLocation', 'Centre')
            ->call('clearLocation');

        $titles = $this->titlesFrom($browse);

        $this->assertContains('Yaounde desk', $titles);
        $this->assertContains('Douala fridge', $titles);
    }

    public function test_a_coordinate_resolves_to_the_region_it_sits_in(): void
    {
        // Douala, which is Littoral — the picker drops a pin and no distance.
        $this->assertSame('littoral', CameroonGeo::nearestRegion(4.05, 9.77));
        $this->assertSame('centre', CameroonGeo::nearestRegion(3.85, 11.50));
    }

    public function test_every_region_in_the_picker_resolves_back_to_its_own_key(): void
    {
        // The picker sends the display name through setLocation(), which runs
        // matchRegion() over it; a name that does not round-trip is a chip that
        // silently filters nothing.
        foreach (CameroonGeo::regionLabels() as $key => $names) {
            $this->assertSame($key, CameroonGeo::matchRegion($names['en']), "EN name for {$key}");
            $this->assertSame($key, CameroonGeo::matchRegion($names['fr']), "FR name for {$key}");
        }
    }
}
