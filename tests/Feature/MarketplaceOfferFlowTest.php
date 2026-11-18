<?php

namespace Tests\Feature;

use App\Enums\Language;
use App\Enums\OfferStatus;
use App\Livewire\Marketplace\ListingDetail;
use App\Livewire\Marketplace\MyOffers;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Counter-offers have to reach the buyer.
 *
 * A counter used to create a second offer owned by the seller. It landed in the
 * seller's own Received tab, with Accept on it, and the buyer only ever had
 * Withdraw. The listing page and the Offers screen also did it two different
 * ways, and nothing told the other side about any of it.
 */
class MarketplaceOfferFlowTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private User $seller;
    private User $buyer;
    private MarketplaceListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->seller = $this->createUser(['username' => 'seller', 'name' => 'Sali', 'language_pref' => Language::French]);
        $this->buyer = $this->createUser(['username' => 'buyer', 'name' => 'Ama', 'language_pref' => Language::English]);

        $category = MarketplaceCategory::create([
            'tenant_id' => $this->tenant->id, 'name_en' => 'General', 'name_fr' => 'Général',
            'slug' => 'general', 'is_active' => true,
        ]);
        $this->listing = MarketplaceListing::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->seller->id, 'category_id' => $category->id,
            'title' => 'Fridge', 'slug' => 'fridge', 'description' => 'Cold.', 'price' => 100000,
            'currency' => 'XAF', 'condition' => 'good', 'status' => 'active', 'published_at' => now(),
        ]);
    }

    private function offer(): MarketplaceOffer
    {
        return app(OfferService::class)->make($this->listing, $this->buyer, 80000);
    }

    private function notificationsOf(User $user)
    {
        return $user->notifications()->orderBy('created_at')->get();
    }

    public function test_a_counter_is_the_buyers_to_answer(): void
    {
        $offer = $this->offer();

        Livewire::actingAs($this->seller)->test(MyOffers::class, ['tab' => 'received'])
            ->call('startCounter', $offer->id)
            ->set('counterAmount', '90000')
            ->call('submitCounter');

        // One row, not two: nothing new appears in the seller's Received tab.
        $this->assertSame(1, MarketplaceOffer::count());
        $offer->refresh();
        $this->assertSame(OfferStatus::Countered, $offer->status);
        $this->assertEquals(90000, $offer->counter_amount);

        // The seller can't accept their own counter.
        Livewire::actingAs($this->seller)->test(MyOffers::class, ['tab' => 'received'])
            ->call('acceptOffer', $offer->id)
            ->assertDispatched('toast', type: 'error');
        $this->assertSame(OfferStatus::Countered, $offer->refresh()->status);

        // The buyer can, at the counter's price.
        Livewire::actingAs($this->buyer)->test(MyOffers::class, ['tab' => 'sent'])
            ->assertSee('acceptCounter(' . $offer->id . ')', false)
            ->call('acceptCounter', $offer->id);

        $offer->refresh();
        $this->assertSame(OfferStatus::Accepted, $offer->status);
        $this->assertEquals(90000, $offer->amount);
    }

    public function test_the_listing_page_counters_the_same_way(): void
    {
        $offer = $this->offer();

        Livewire::actingAs($this->seller)->test(ListingDetail::class, ['slug' => 'fridge'])
            ->call('startCounter', $offer->id)
            ->set('counterAmount', '95000')
            ->call('submitCounter');

        $this->assertSame(1, MarketplaceOffer::count());
        $this->assertSame(OfferStatus::Countered, $offer->refresh()->status);

        Livewire::actingAs($this->buyer)->test(ListingDetail::class, ['slug' => 'fridge'])
            ->assertSee('declineCounter(' . $offer->id . ')', false)
            ->call('declineCounter', $offer->id);

        $this->assertSame(OfferStatus::Rejected, $offer->refresh()->status);
    }

    public function test_each_step_tells_the_other_side_in_their_language(): void
    {
        $service = app(OfferService::class);
        $offer = $this->offer();
        $service->counter($offer, $this->seller, 90000);
        $service->acceptCounter($offer->refresh(), $this->buyer);

        // The seller reads French, the buyer English, whoever is acting.
        $toSeller = $this->notificationsOf($this->seller)->pluck('data.title');
        $this->assertSame(['Ama propose 80,000 XAF', 'Ama a accepté votre contre-offre'], $toSeller->all());

        $toBuyer = $this->notificationsOf($this->buyer)->pluck('data.title');
        $this->assertSame(['Sali countered with 90,000 XAF'], $toBuyer->all());
        $this->assertStringContainsString('/marketplace/offers?tab=sent', $this->notificationsOf($this->buyer)->first()->data['url']);
    }

    public function test_an_answered_offer_cannot_be_answered_again(): void
    {
        $service = app(OfferService::class);
        $offer = $this->offer();

        $this->assertTrue($service->reject($offer, $this->seller));
        $this->assertFalse($service->accept($offer->refresh(), $this->seller));
        $this->assertFalse($service->counter($offer, $this->seller, 90000));
        $this->assertFalse($service->acceptCounter($offer, $this->buyer));
    }

    public function test_old_counter_rows_are_folded_into_the_offer_they_answer(): void
    {
        $offer = $this->offer();
        $offer->update(['status' => OfferStatus::Countered]);
        $stray = MarketplaceOffer::create([
            'listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'parent_offer_id' => $offer->id, 'amount' => 88000, 'currency' => 'XAF', 'status' => OfferStatus::Pending,
        ]);

        $migration = require database_path('migrations/2026_11_06_000001_fold_counter_offers_into_their_offer.php');
        $migration->up();

        $this->assertEquals(88000, $offer->refresh()->counter_amount);
        $this->assertSame(OfferStatus::Withdrawn, $stray->refresh()->status);
        $this->assertTrue(app(OfferService::class)->acceptCounter($offer, $this->buyer));
    }
}
