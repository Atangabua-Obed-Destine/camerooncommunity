<?php

namespace Tests\Feature\Api;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardMessage;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * GoMarket and Home from the app: the feed is the web's query, a listing's
 * actions go through the web's ListingService, and the fixes made on the way
 * (inquiries counted, the shelf's country step) hold.
 */
class MarketApiTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private MarketplaceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        YardRoomMember::$suppressJoinAnnouncement = true;
        $this->category = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Phones', 'name_fr' => 'Téléphones', 'slug' => 'phones', 'icon' => '📱', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        YardRoomMember::$suppressJoinAnnouncement = false;
        parent::tearDown();
    }

    private function listing(User $seller, array $attrs = []): MarketplaceListing
    {
        static $n = 0;
        $n++;

        return MarketplaceListing::create(array_merge([
            'tenant_id' => $this->tenant->id, 'user_id' => $seller->id, 'category_id' => $this->category->id,
            'title' => "Tecno Spark $n", 'slug' => "tecno-spark-$n", 'description' => 'Good phone', 'price' => 45000,
            'price_type' => 'fixed', 'currency' => 'XAF', 'condition' => 'good', 'status' => 'active', 'visibility' => 'public',
            'region' => 'Littoral', 'city' => 'Douala', 'country' => 'CM', 'fulfillment' => ['pickup'], 'published_at' => now(),
        ], $attrs));
    }

    public function test_the_feed_lists_cards_and_filters_by_category(): void
    {
        [$me, $seller] = [$this->createUser(), $this->createUser()];
        $phone = $this->listing($seller);
        $other = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Cars', 'name_fr' => 'Voitures', 'slug' => 'cars', 'is_active' => true]);
        $this->listing($seller, ['category_id' => $other->id, 'title' => 'Toyota']);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.index'))->assertOk()->assertJsonPath('total', 2);

        $this->getJson(route('api.v1.market.index', ['cat' => 'phones']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('listings.0.slug', $phone->slug)
            ->assertJsonPath('listings.0.price_label', '45,000 XAF')
            ->assertJsonPath('listings.0.location', 'Douala, Littoral');

        // The filters sheet's radius slider moves in steps of 5, as the web's.
        $this->getJson(route('api.v1.market.index', ['loc' => 'littoral', 'radius' => 35]))->assertOk()->assertJsonPath('total', 2);
        $this->getJson(route('api.v1.market.index', ['loc' => 'littoral', 'radius' => 0]))->assertOk()->assertJsonPath('total', 2);
        $this->getJson(route('api.v1.market.index', ['radius' => 501]))->assertUnprocessable();
    }

    public function test_a_listing_counts_one_view_a_day_per_member(): void
    {
        [$me, $seller] = [$this->createUser(), $this->createUser()];
        $listing = $this->listing($seller);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.show', $listing->slug))
            ->assertOk()
            ->assertJsonPath('listing.title', $listing->title)
            ->assertJsonPath('listing.is_owner', false)
            ->assertJsonPath('listing.condition.label', 'Good');
        $this->getJson(route('api.v1.market.show', $listing->slug))->assertOk();

        $this->assertSame(1, $listing->fresh()->views_count);
    }

    public function test_the_listing_map_gets_an_approximate_point_never_the_exact_one(): void
    {
        [$me, $seller] = [$this->createUser(), $this->createUser()];
        $placed = $this->listing($seller, ['latitude' => 4.051234, 'longitude' => 9.767891]);
        $unplaced = $this->listing($seller);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.show', $placed->slug))
            ->assertOk()
            ->assertJsonPath('listing.geo', ['lat' => 4.05, 'lng' => 9.77]);

        $this->getJson(route('api.v1.market.show', $unplaced->slug))
            ->assertOk()
            ->assertJsonPath('listing.geo', null);
    }

    public function test_drafts_and_blocked_sellers_are_out_of_reach(): void
    {
        [$me, $seller, $blocked] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $draft = $this->listing($seller, ['status' => 'draft']);
        $hidden = $this->listing($blocked);
        UserConnection::create(['user_a_id' => min($me->id, $blocked->id), 'user_b_id' => max($me->id, $blocked->id), 'status' => UserConnection::STATUS_BLOCKED, 'requested_by' => $me->id]);

        $this->actingAs($me, 'sanctum');
        $this->getJson(route('api.v1.market.show', $draft->slug))->assertNotFound();
        $this->getJson(route('api.v1.market.show', $hidden->slug))->assertNotFound();
        $this->postJson(route('api.v1.market.favorite', $draft))->assertNotFound();
    }

    public function test_saving_toggles_and_counts(): void
    {
        [$me, $seller] = [$this->createUser(), $this->createUser()];
        $listing = $this->listing($seller);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.market.favorite', $listing))->assertJsonPath('is_favorited', true)->assertJsonPath('saves', 1);
        $this->getJson(route('api.v1.market.favorites'))->assertJsonPath('listings.0.id', $listing->id);
        $this->postJson(route('api.v1.market.favorite', $listing))->assertJsonPath('is_favorited', false)->assertJsonPath('saves', 0);
    }

    public function test_messaging_the_seller_opens_the_chat_and_counts_one_inquiry_per_buyer(): void
    {
        [$me, $seller] = [$this->createUser(), $this->createUser()];
        $listing = $this->listing($seller);

        $room = $this->actingAs($me, 'sanctum')->postJson(route('api.v1.market.inquiry', $listing), ['text' => 'Is it still available?'])
            ->assertOk()->json('room_id');
        $this->postJson(route('api.v1.market.inquiry', $listing), ['text' => 'Hello?'])->assertOk()->assertJsonPath('room_id', $room);

        $this->assertSame(1, $listing->fresh()->messages_count);
        $this->assertSame(1, YardMessage::where('room_id', $room)->where('message_type', 'share_card')->count());
        $this->getJson(route('api.v1.market.show', $listing->slug))->assertJsonPath('listing.has_messaged', true);
        // The seller's chat is a marketplace one: no connection needed to reply.
        $this->actingAs($seller, 'sanctum')->postJson(route('api.v1.yard.messages.store', $room), ['content' => 'Yes!'])->assertCreated();
    }

    public function test_a_report_is_taken_once(): void
    {
        [$me, $seller] = [$this->createUser(), $this->createUser()];
        $listing = $this->listing($seller);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.market.report', $listing), ['reason' => 'scam'])->assertOk();
        $this->postJson(route('api.v1.market.report', $listing), ['reason' => 'scam'])->assertStatus(409);
    }

    public function test_the_home_shelf_falls_back_to_the_members_country(): void
    {
        $me = $this->createUser(['name' => 'Ama Njoya', 'current_country' => 'Cameroon', 'current_region' => 'Northwest']);
        $seller = $this->createUser();
        // Nothing in Northwest; one in Cameroon (stored as "CM"), one abroad.
        $local = $this->listing($seller, ['region' => 'Littoral', 'country' => 'CM']);
        $this->listing($seller, ['region' => 'London', 'country' => 'GB', 'published_at' => now()->addMinute()]);

        $res = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.home'))->assertOk()
            ->assertJsonPath('first_name', 'Ama')
            ->assertJsonPath('shelf.label', 'Northwest');

        // The country step finds the Cameroon listing first, though it is older.
        $this->assertSame($local->id, $res->json('shelf.listings.0.id'));
    }
}
