<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceReview;
use App\Models\MarketplaceSavedSearch;
use App\Models\User;
use App\Models\YardRoomMember;
use App\Services\MarketplaceChatService;
use App\Support\CameroonGeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * GoMarket's buying side from the app: Buy now through to a completed order
 * and a review, the rules each step keeps (and the holes in the web's that
 * closed on the way), saved searches and the inbox.
 */
class MarketBuyApiTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private MarketplaceCategory $category;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        YardRoomMember::$suppressJoinAnnouncement = true;
        $this->category = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Phones', 'name_fr' => 'Téléphones', 'slug' => 'phones', 'icon' => '📱', 'is_active' => true]);
        $this->seller = $this->createUser(['username' => 'seller', 'momo_number' => '677000000', 'momo_provider' => 'mtn']);
        $this->buyer = $this->createUser(['username' => 'buyer']);
    }

    protected function tearDown(): void
    {
        YardRoomMember::$suppressJoinAnnouncement = false;
        parent::tearDown();
    }

    private function listing(array $attrs = []): MarketplaceListing
    {
        static $n = 0;
        $n++;

        return MarketplaceListing::create(array_merge([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->seller->id, 'category_id' => $this->category->id,
            'title' => "Tecno Spark $n", 'slug' => "spark-$n", 'description' => 'Good phone', 'price' => 45000,
            'price_type' => 'fixed', 'currency' => 'XAF', 'condition' => 'good', 'status' => 'active', 'visibility' => 'public',
            'region' => 'Littoral', 'city' => 'Douala', 'country' => 'CM', 'fulfillment' => ['pickup'], 'published_at' => now(),
        ], $attrs));
    }

    public function test_a_purchase_runs_from_buy_now_to_a_review(): void
    {
        $listing = $this->listing();
        $this->actingAs($this->buyer, 'sanctum');

        $this->getJson(route('api.v1.market.checkout', $listing->slug))
            ->assertOk()
            ->assertJsonPath('listing.price_label', '45,000 XAF')
            ->assertJsonPath('drivers.0.id', 'manual')
            ->assertJsonPath('order', null);

        $start = $this->postJson(route('api.v1.market.checkout.start', $listing), ['provider' => 'manual'])
            ->assertOk()
            ->assertJsonPath('order.status.value', 'initiated')
            ->assertJsonPath('order.instructions.recipient.number', '677000000');
        $orderId = $start->json('order.id');

        // Continue tapped twice makes one order, not two.
        $this->postJson(route('api.v1.market.checkout.start', $listing), ['provider' => 'manual'])->assertJsonPath('order.id', $orderId);
        $this->assertSame(1, MarketplaceOrder::count());
        $order = MarketplaceOrder::find($orderId);

        // The evidence is checked, then the seller hears of it.
        $this->postJson(route('api.v1.market.orders.proof', $order), ['provider_ref' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('provider_ref');
        $this->postJson(route('api.v1.market.orders.proof', $order), ['provider_ref' => 'CI230415.1234', 'buyer_note' => 'Akwa, 5pm'])
            ->assertOk()
            ->assertJsonPath('checkout.order.status.value', 'awaiting_payment');
        $this->assertSame(1, $this->seller->notifications()->count());

        // Only the seller confirms the money; the buyer cannot.
        $this->postJson(route('api.v1.market.orders.paid', $order))->assertForbidden();
        $this->actingAs($this->seller, 'sanctum');
        $this->getJson(route('api.v1.market.orders', ['tab' => 'selling']))
            ->assertOk()
            ->assertJsonPath('counts.open_selling', 1)
            ->assertJsonPath('orders.0.counterpart.username', 'buyer')
            ->assertJsonPath('orders.0.actions.mark_paid', true)
            ->assertJsonPath('orders.0.provider_ref', 'CI230415.1234');
        $this->postJson(route('api.v1.market.orders.paid', $order))->assertOk()->assertJsonPath('order.status.value', 'paid');
        // Twice is refused rather than paid twice.
        $this->postJson(route('api.v1.market.orders.paid', $order))->assertUnprocessable()->assertJsonPath('code', 'not_awaiting');

        // Handed over: the listing is sold to this buyer.
        $this->actingAs($this->buyer, 'sanctum');
        $this->postJson(route('api.v1.market.orders.cancel', $order))->assertUnprocessable()->assertJsonPath('code', 'already_paid');
        $this->postJson(route('api.v1.market.orders.release', $order))->assertOk()
            ->assertJsonPath('order.status.value', 'released')
            ->assertJsonPath('order.actions.review', true)
            ->assertJsonPath('order.actions.reviewed', false);
        $listing->refresh();
        $this->assertSame('sold', $listing->status->value);
        $this->assertSame($this->buyer->id, (int) $listing->buyer_id);

        // A closed order cannot be disputed.
        $this->postJson(route('api.v1.market.orders.dispute', $order))->assertUnprocessable()->assertJsonPath('code', 'closed');

        $this->getJson(route('api.v1.market.review', $listing->slug))->assertOk()->assertJsonPath('review', null);
        $this->postJson(route('api.v1.market.review.save', $listing), ['rating' => 6])->assertUnprocessable();
        $this->postJson(route('api.v1.market.review.save', $listing), ['rating' => 4, 'comment' => 'Smooth'])
            ->assertOk()->assertJsonPath('created', true);
        $this->postJson(route('api.v1.market.review.save', $listing), ['rating' => 5])->assertOk()->assertJsonPath('created', false);
        $this->assertSame(5, (int) MarketplaceReview::sole()->rating);
        $this->getJson(route('api.v1.market.review', $listing->slug))->assertJsonPath('review.rating', 5);

        // Reviewed: the order offers to edit it rather than asking again.
        $this->getJson(route('api.v1.market.orders', ['tab' => 'buying']))
            ->assertOk()
            ->assertJsonPath('orders.0.actions.review', true)
            ->assertJsonPath('orders.0.actions.reviewed', true);
    }

    public function test_checkout_refuses_what_the_web_should_have(): void
    {
        $this->actingAs($this->buyer, 'sanctum');

        // A seller without Mobile Money: no method offered, none accepted.
        $noMomo = $this->createUser();
        $listing = $this->listing(['user_id' => $noMomo->id]);
        $this->getJson(route('api.v1.market.checkout', $listing->slug))->assertOk()->assertJsonCount(0, 'drivers');
        $this->postJson(route('api.v1.market.checkout.start', $listing), ['provider' => 'manual'])
            ->assertUnprocessable()->assertJsonPath('code', 'no_driver_for_seller');

        // A price to discuss, not to pay.
        $free = $this->listing(['price_type' => 'contact', 'price' => null]);
        $this->postJson(route('api.v1.market.checkout.start', $free), ['provider' => 'manual'])
            ->assertUnprocessable()->assertJsonPath('code', 'no_price');

        // Your own, and one no longer on sale.
        $this->actingAs($this->seller, 'sanctum');
        $mine = $this->listing();
        $this->getJson(route('api.v1.market.checkout', $mine->slug))->assertForbidden();
        $this->actingAs($this->buyer, 'sanctum');
        $paused = $this->listing(['status' => 'paused']);
        $this->getJson(route('api.v1.market.checkout', $paused->slug))->assertNotFound();
    }

    public function test_a_paused_listing_does_not_strand_an_order_already_going(): void
    {
        $listing = $this->listing();
        $this->actingAs($this->buyer, 'sanctum')->postJson(route('api.v1.market.checkout.start', $listing), ['provider' => 'manual'])->assertOk();
        $listing->update(['status' => 'paused']);

        $this->getJson(route('api.v1.market.checkout', $listing->slug))->assertOk()->assertJsonPath('order.status.value', 'initiated');
        $order = MarketplaceOrder::sole();
        $this->postJson(route('api.v1.market.orders.cancel', $order))->assertOk()->assertJsonPath('order.status.value', 'cancelled');
    }

    public function test_someone_elses_order_is_out_of_reach(): void
    {
        $listing = $this->listing();
        $order = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id, 'listing_id' => $listing->id, 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'status' => OrderStatus::AwaitingPayment, 'provider' => 'manual',
            'amount' => 45000, 'currency' => 'XAF', 'quantity' => 1,
        ]);
        $stranger = $this->createUser();
        $this->actingAs($stranger, 'sanctum');

        foreach (['proof', 'cancel', 'paid', 'release', 'dispute'] as $step) {
            $this->postJson(route("api.v1.market.orders.$step", $order), ['provider_ref' => 'ABCDEF'])->assertForbidden();
        }
        $this->getJson(route('api.v1.market.orders'))->assertOk()->assertJsonCount(0, 'orders');
        $this->getJson(route('api.v1.market.review', $listing->slug))->assertForbidden();
        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
    }

    public function test_saved_searches_keep_the_area_and_open_in_the_app(): void
    {
        $this->actingAs($this->buyer, 'sanctum');

        $this->postJson(route('api.v1.market.saved.store'), [])->assertUnprocessable()->assertJsonPath('code', 'empty');
        $this->postJson(route('api.v1.market.saved.store'), ['q' => 'tecno', 'loc' => 'littoral', 'radius' => 50])
            ->assertCreated()->assertJsonPath('existing', false);
        // The same search again is the same search.
        $this->postJson(route('api.v1.market.saved.store'), ['q' => 'tecno', 'loc' => 'littoral', 'radius' => 50])
            ->assertOk()->assertJsonPath('existing', true);
        $this->assertSame(1, MarketplaceSavedSearch::count());

        // A new listing in the area counts; one far away does not.
        $this->travel(1)->minutes();
        $this->listing(['title' => 'Tecno Camon']);
        $this->listing(['title' => 'Tecno Pop', 'region' => 'Far North', 'city' => 'Maroua']);

        $s = MarketplaceSavedSearch::sole();
        $this->getJson(route('api.v1.market.saved'))
            ->assertOk()
            ->assertJsonPath('total_new', 1)
            ->assertJsonPath('searches.0.query', ['q' => 'tecno', 'loc' => 'littoral', 'radius' => 50])
            ->assertJsonPath('searches.0.summary', '"tecno" • 📍 Littoral · 50 km');

        $this->postJson(route('api.v1.market.saved.seen', $s->id))->assertOk();
        $this->getJson(route('api.v1.market.saved'))->assertJsonPath('total_new', 0);

        $this->patchJson(route('api.v1.market.saved.update', $s->id), ['name' => ' '])->assertUnprocessable();
        $this->patchJson(route('api.v1.market.saved.update', $s->id), ['name' => 'Phones', 'notify_email' => true])->assertOk();
        $this->assertSame('Phones', $s->refresh()->name);
        $this->assertTrue($s->notify_email);

        // Not yours: not found.
        $this->actingAs($this->seller, 'sanctum')->deleteJson(route('api.v1.market.saved.destroy', $s->id))->assertNotFound();
        $this->actingAs($this->buyer, 'sanctum')->deleteJson(route('api.v1.market.saved.destroy', $s->id))->assertOk();
        $this->assertSame(0, MarketplaceSavedSearch::count());
    }

    public function test_far_north_is_an_area_the_feed_can_filter_to(): void
    {
        $this->assertSame('far_north', CameroonGeo::matchRegion('far_north'));
        $this->listing(['region' => 'Far North', 'city' => 'Maroua']);
        $this->listing();

        $this->actingAs($this->buyer, 'sanctum')->getJson(route('api.v1.market.index', ['loc' => 'far_north']))
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_the_inbox_sorts_conversations_into_buying_and_selling_with_badges(): void
    {
        $listing = $this->listing();
        $chat = app(MarketplaceChatService::class);
        $room = $chat->openConversation($this->buyer, $this->seller, $listing)['room'];
        $chat->postMessage($room, $this->buyer, 'Is it still available?');

        $this->actingAs($this->seller, 'sanctum')->getJson(route('api.v1.market.inbox'))
            ->assertOk()
            ->assertJsonPath('conversations.0.role', 'selling')
            ->assertJsonPath('conversations.0.partner.username', 'buyer')
            ->assertJsonPath('conversations.0.listing.slug', $listing->slug)
            ->assertJsonPath('counts.selling', $this->json('GET', route('api.v1.market.inbox'))->json('conversations.0.unread'));

        $badges = $this->getJson(route('api.v1.market.badges'))->assertOk();
        $this->assertGreaterThan(0, $badges->json('inbox_unread'));
        $this->assertSame(0, $badges->json('orders_pending'));

        $this->actingAs($this->buyer, 'sanctum')->getJson(route('api.v1.market.inbox'))
            ->assertJsonPath('conversations.0.role', 'buying');
    }
}
