<?php

namespace Tests\Feature;

use App\Livewire\Marketplace\ChatDock;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The floating product chat has to answer the back button.
 *
 * It sits over the listing page without being part of history, so on a phone —
 * where back is how anything gets dismissed — pressing it left the page with
 * the conversation still open behind. The dock registers with the overlay
 * stack, which is what makes back close it; these guard the pieces that
 * registration depends on.
 */
class GoMarketChatDockBackTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function listingBy(User $seller): MarketplaceListing
    {
        $category = MarketplaceCategory::create([
            'tenant_id' => $this->tenant->id,
            'name_en'   => 'Appliances',
            'name_fr'   => 'Électroménager',
            'slug'      => 'appliances-' . uniqid(),
            'is_active' => true,
        ]);

        return MarketplaceListing::create([
            'tenant_id'   => $this->tenant->id,
            'user_id'     => $seller->id,
            'category_id' => $category->id,
            'title'       => 'Freezer',
            'slug'        => 'freezer-' . uniqid(),
            'description' => 'A cold box.',
            'price'       => 180000,
            'currency'    => 'XAF',
            'condition'   => 'good',
            'status'      => 'active',
        ]);
    }

    public function test_the_open_dock_registers_itself_with_the_back_stack(): void
    {
        $buyer  = $this->createUser(['username' => 'buyer']);
        $seller = $this->createUser(['username' => 'seller']);
        $listing = $this->listingBy($seller);

        $dock = Livewire::actingAs($buyer)
            ->test(ChatDock::class)
            ->call('openFor', $seller->id, $listing->id);

        $dock->assertSet('open', true)
            ->assertSee('cnOverlay', false);
    }

    public function test_a_closed_dock_registers_nothing(): void
    {
        $buyer = $this->createUser(['username' => 'buyer2']);

        Livewire::actingAs($buyer)
            ->test(ChatDock::class)
            ->assertSet('open', false)
            ->assertDontSee('cnOverlay', false);
    }

    public function test_back_closes_the_dock_through_the_same_method_the_cross_uses(): void
    {
        // The overlay stack closes the dock by calling $wire.close(), so that
        // method has to leave it fully shut and not merely minimized.
        $buyer  = $this->createUser(['username' => 'buyer3']);
        $seller = $this->createUser(['username' => 'seller3']);
        $listing = $this->listingBy($seller);

        Livewire::actingAs($buyer)
            ->test(ChatDock::class)
            ->call('openFor', $seller->id, $listing->id)
            ->call('toggleMinimize')
            ->call('close')
            ->assertSet('open', false)
            ->assertSet('minimized', false);
    }
}
