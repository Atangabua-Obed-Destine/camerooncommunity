<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class HomeNearbyListingsTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private MarketplaceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->category = MarketplaceCategory::create([
            'tenant_id' => $this->tenant->id,
            'name_en'   => 'Things',
            'name_fr'   => 'Choses',
            'slug'      => 'things',
        ]);
    }

    private function listing(string $title, string $region, $seller, int $minutesAgo = 0): MarketplaceListing
    {
        $l = MarketplaceListing::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $seller->id,
            'category_id'  => $this->category->id,
            'title'        => $title,
            'slug'         => \Illuminate\Support\Str::slug($title),
            'currency'     => 'XAF',
            'price'        => 5000,
            'visibility'   => 'public',
            'status'       => ListingStatus::Active->value,
            'published_at' => now()->subMinutes($minutesAgo),
            'region'       => $region,
            'country'      => 'CM',
        ]);

        MarketplaceListing::whereKey($l->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);

        return $l->fresh();
    }

    public function test_listings_from_the_viewers_region_come_first(): void
    {
        $seller = $this->createUser(['username' => 'seller']);
        $viewer = $this->createUser(['username' => 'viewer', 'current_region' => 'Northwest', 'current_country' => 'CM']);

        // The far one is newer, so only the location rule can put the local one first.
        $this->listing('Local Bamenda Fridge', 'Northwest', $seller, 60);
        $this->listing('Faraway Douala Sofa', 'Littoral', $seller, 1);

        $response = $this->actingAs($viewer)->get('/');

        $response->assertOk();
        $body = $response->getContent();

        $this->assertLessThan(
            strpos($body, 'Faraway Douala Sofa'),
            strpos($body, 'Local Bamenda Fridge'),
            'the viewer\'s own region should lead the shelf'
        );
    }

    public function test_the_shelf_links_to_the_marketplace_filtered_by_that_region(): void
    {
        $seller = $this->createUser(['username' => 'seller2']);
        $viewer = $this->createUser(['username' => 'viewer2', 'current_region' => 'Northwest', 'current_country' => 'CM']);

        $this->listing('Something Local', 'Northwest', $seller);

        $this->actingAs($viewer)->get('/')
            ->assertOk()
            ->assertSee('Northwest')
            ->assertSee(route('marketplace.index', ['region' => 'Northwest']), false);
    }

    public function test_a_region_with_nothing_for_sale_still_fills_the_shelf(): void
    {
        $seller = $this->createUser(['username' => 'seller3']);
        $viewer = $this->createUser(['username' => 'viewer3', 'current_region' => 'Adamawa', 'current_country' => 'CM']);

        $this->listing('Elsewhere Item', 'Littoral', $seller);

        // Nothing nearby is not a reason to show an empty page.
        $this->actingAs($viewer)->get('/')->assertOk()->assertSee('Elsewhere Item');
    }

    public function test_guests_see_the_landing_page_not_the_shelf(): void
    {
        $seller = $this->createUser(['username' => 'seller4']);
        $this->listing('Private Shelf Item', 'Northwest', $seller);

        $this->get('/')->assertOk()->assertDontSee('Private Shelf Item');
    }
}
