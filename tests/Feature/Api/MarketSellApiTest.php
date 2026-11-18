<?php

namespace Tests\Feature\Api;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingMedia;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Selling from the app: the composer, My listings and the member profile go
 * through the services the web's Livewire pages use, so a listing made on
 * either is the same, and nobody reaches another member's listing.
 */
class MarketSellApiTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private MarketplaceCategory $phones;

    private MarketplaceCategory $vehicles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        YardRoomMember::$suppressJoinAnnouncement = true;
        Storage::fake('public');
        $this->phones = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Phones', 'name_fr' => 'Téléphones', 'slug' => 'phones', 'icon' => '📱', 'is_active' => true]);
        $this->vehicles = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Vehicles', 'name_fr' => 'Véhicules', 'slug' => 'vehicles', 'icon' => '🚗', 'is_active' => true]);
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
            'tenant_id' => $this->tenant->id, 'user_id' => $seller->id, 'category_id' => $this->phones->id,
            'title' => "Tecno Spark $n", 'slug' => "tecno-spark-$n", 'price' => 45000, 'price_type' => 'fixed',
            'currency' => 'XAF', 'condition' => 'good', 'status' => 'active', 'visibility' => 'public',
            'region' => 'Littoral', 'city' => 'Douala', 'country' => 'CM', 'fulfillment' => ['pickup'], 'published_at' => now(),
        ], $attrs));
    }

    private function publishBody(array $over = []): array
    {
        return array_merge([
            'category_id' => $this->phones->id, 'title' => 'Samsung Galaxy A54', 'description' => 'Like new, boxed',
            'price_type' => 'fixed', 'price' => '150000', 'currency' => 'XAF', 'condition' => 'like_new', 'quantity' => 1,
            'fulfillment' => ['pickup', 'local_delivery'], 'country' => 'CM', 'region' => 'Littoral', 'city' => 'Douala',
            'location_label' => 'Douala, Littoral, CM', 'visibility' => 'public', 'tags' => ['samsung', 'android'],
        ], $over);
    }

    public function test_the_options_carry_categories_with_their_own_fields_and_the_members_place(): void
    {
        $me = $this->createUser(['current_region' => 'Littoral', 'current_city' => 'Douala']);

        $r = $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.sell.options'))->assertOk();

        $vehicles = collect($r->json('categories'))->firstWhere('slug', 'vehicles');
        $this->assertSame('make', $vehicles['fields'][0]['key']);
        $this->assertSame($this->vehicles->id, collect($r->json('listing_types'))->firstWhere('value', 'vehicle')['category_id']);
        $this->assertSame('XAF', $r->json('currencies.0.code'));
        $this->assertSame('Douala', $r->json('blank.city'));
    }

    public function test_a_draft_made_from_a_photo_publishes_with_a_real_address(): void
    {
        $me = $this->createUser();
        $this->actingAs($me, 'sanctum');

        $id = $this->postJson(route('api.v1.market.sell.store'), [])->assertCreated()->json('listing.id');
        $this->assertSame('', MarketplaceListing::find($id)->status->value === 'draft' ? '' : 'not a draft');

        $this->post(route('api.v1.market.sell.photos.add', $id), ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('media.0.is_cover', true);

        $r = $this->postJson(route('api.v1.market.sell.publish', $id), $this->publishBody())->assertOk()
            ->assertJsonPath('status', 'active');

        $l = MarketplaceListing::find($id);
        $this->assertStringStartsWith('samsung-galaxy-a54-', $l->slug);
        $this->assertSame($l->slug, $r->json('slug'));
        $this->assertNotNull($l->published_at);
        $this->assertSame(['samsung', 'android'], $l->tags);
    }

    public function test_publishing_checks_what_the_web_checks_and_wants_a_photo(): void
    {
        $me = $this->createUser();
        $draft = $this->listing($me, ['status' => 'draft']);
        $this->actingAs($me, 'sanctum');

        $this->postJson(route('api.v1.market.sell.publish', $draft->id), $this->publishBody(['title' => 'abc', 'price' => null, 'location_label' => '', 'fulfillment' => []]))
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'price', 'location_label', 'fulfillment']);

        // Valid, but no photo yet.
        $this->postJson(route('api.v1.market.sell.publish', $draft->id), $this->publishBody())
            ->assertStatus(422)->assertJsonValidationErrors(['photos']);

        // Free needs no price.
        MarketplaceListingMedia::create(['listing_id' => $draft->id, 'type' => 'image', 'path' => 'x.jpg', 'position' => 0, 'is_cover' => true]);
        $this->postJson(route('api.v1.market.sell.publish', $draft->id), $this->publishBody(['price_type' => 'free', 'price' => null]))->assertOk();
        $this->assertEquals(0, (float) $draft->fresh()->price);
    }

    public function test_saving_an_edit_keeps_a_sold_listing_sold_and_its_date(): void
    {
        $me = $this->createUser();
        $published = now()->subDays(10)->startOfSecond();
        $sold = $this->listing($me, ['status' => 'sold', 'published_at' => $published]);
        MarketplaceListingMedia::create(['listing_id' => $sold->id, 'type' => 'image', 'path' => 'x.jpg', 'position' => 0, 'is_cover' => true]);

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.market.sell.publish', $sold->id), $this->publishBody(['title' => 'Renamed phone']))
            ->assertOk()->assertJsonPath('status', 'sold');

        $sold->refresh();
        $this->assertSame('Renamed phone', $sold->title);
        $this->assertTrue($sold->published_at->equalTo($published));
    }

    public function test_another_members_listing_is_not_found_anywhere(): void
    {
        [$me, $other] = [$this->createUser(), $this->createUser()];
        $theirs = $this->listing($other, ['status' => 'draft']);
        $this->actingAs($me, 'sanctum');

        $this->getJson(route('api.v1.market.sell.show', $theirs->id))->assertNotFound();
        $this->patchJson(route('api.v1.market.sell.autosave', $theirs->id), ['title' => 'Mine now'])->assertNotFound();
        $this->post(route('api.v1.market.sell.photos.add', $theirs->id), ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertNotFound();
        $this->postJson(route('api.v1.market.sell.publish', $theirs->id), $this->publishBody())->assertNotFound();
        $this->postJson(route('api.v1.market.mine.pause', $theirs->id))->assertNotFound();
        $this->postJson(route('api.v1.market.mine.sold', $theirs->id))->assertNotFound();
        $this->deleteJson(route('api.v1.market.mine.destroy', $theirs->id))->assertNotFound();
        $this->getJson(route('api.v1.market.mine.insights', $theirs->id))->assertNotFound();
        $this->postJson(route('api.v1.market.mine.bulk'), ['action' => 'remove', 'ids' => [$theirs->id]])->assertStatus(422);

        $this->assertSame('Tecno Spark', substr($theirs->fresh()->title, 0, 11));
        $this->assertNotNull(MarketplaceListing::find($theirs->id));
    }

    public function test_the_starred_photo_moves_to_the_front_and_removing_the_cover_promotes_the_next(): void
    {
        $me = $this->createUser();
        $l = $this->listing($me, ['status' => 'draft']);
        $m = collect([0, 1, 2])->map(fn ($i) => MarketplaceListingMedia::create(['listing_id' => $l->id, 'type' => 'image', 'path' => "p$i.jpg", 'position' => $i, 'is_cover' => $i === 0]));
        $this->actingAs($me, 'sanctum');

        $this->postJson(route('api.v1.market.sell.photos.order', $l->id), ['ids' => [$m[2]->id, $m[0]->id, $m[1]->id]])->assertOk()
            ->assertJsonPath('media.0.id', $m[2]->id)->assertJsonPath('media.0.is_cover', true)->assertJsonPath('media.1.is_cover', false);

        $this->deleteJson(route('api.v1.market.sell.photos.remove', [$l->id, $m[2]->id]))->assertOk()
            ->assertJsonCount(2, 'media')->assertJsonPath('media.0.id', $m[0]->id)->assertJsonPath('media.0.is_cover', true);
    }

    public function test_autosave_keeps_a_draft_but_never_touches_a_live_listing(): void
    {
        $me = $this->createUser();
        $draft = $this->listing($me, ['status' => 'draft', 'title' => 'Untitled draft']);
        $live = $this->listing($me);
        $this->actingAs($me, 'sanctum');

        $this->patchJson(route('api.v1.market.sell.autosave', $draft->id), ['title' => 'Infinix Hot 30', 'attributes' => ['make' => 'x']])->assertOk()->assertJsonPath('saved', true);
        $this->assertSame('Infinix Hot 30', $draft->fresh()->title);
        $this->getJson(route('api.v1.market.sell.show', $draft->id))->assertJsonPath('listing.title', 'Infinix Hot 30')->assertJsonPath('listing.is_edit', false);

        $this->patchJson(route('api.v1.market.sell.autosave', $live->id), ['title' => 'Sneaky'])->assertOk()->assertJsonPath('saved', false);
        $this->assertNotSame('Sneaky', $live->fresh()->title);
    }

    public function test_my_listings_filter_pause_reactivate_and_bump_once_a_day(): void
    {
        $me = $this->createUser();
        $live = $this->listing($me);
        $this->listing($me, ['status' => 'draft']);
        $this->listing($this->createUser());
        $this->actingAs($me, 'sanctum');

        $this->getJson(route('api.v1.market.mine.index'))->assertOk()->assertJsonPath('total', 2);
        $this->getJson(route('api.v1.market.mine.index', ['status' => 'draft']))->assertJsonPath('total', 1)->assertJsonPath('listings.0.status', 'draft');

        $this->postJson(route('api.v1.market.mine.pause', $live->id))->assertOk()->assertJsonPath('listing.status', 'paused');
        $this->postJson(route('api.v1.market.mine.pause', $live->id))->assertStatus(422);
        $this->postJson(route('api.v1.market.mine.reactivate', $live->id))->assertOk()->assertJsonPath('listing.status', 'active');

        $this->postJson(route('api.v1.market.mine.bump', $live->id))->assertOk()->assertJsonPath('listing.can_bump', false);
        $this->postJson(route('api.v1.market.mine.bump', $live->id))->assertStatus(429)->assertJsonPath('code', 'cooldown');
    }

    public function test_marking_sold_takes_one_from_stock_then_sells_to_the_buyer(): void
    {
        [$me, $buyer] = [$this->createUser(), $this->createUser(['username' => 'buyer_bob'])];
        $l = $this->listing($me, ['quantity' => 2]);
        $this->actingAs($me, 'sanctum');

        $this->getJson(route('api.v1.market.mine.sale', [$l->id, 'q' => 'buyer_b']))->assertOk()
            ->assertJsonPath('candidates.0.id', $buyer->id)->assertJsonPath('price', '45000');

        $this->postJson(route('api.v1.market.mine.sold', $l->id), ['buyer_id' => $buyer->id])->assertOk();
        $this->assertSame('active', $l->fresh()->status->value);
        $this->assertSame(1, (int) $l->fresh()->quantity);

        $this->postJson(route('api.v1.market.mine.sold', $l->id), ['buyer_id' => $buyer->id, 'price' => 40000])->assertOk()
            ->assertJsonPath('listing.status', 'sold');
        $this->assertSame($buyer->id, $l->fresh()->buyer_id);
        $this->postJson(route('api.v1.market.mine.sold', $l->id), ['buyer_id' => $buyer->id])->assertStatus(409);
    }

    public function test_bulk_actions_only_change_what_can_change(): void
    {
        $me = $this->createUser();
        [$a, $b] = [$this->listing($me), $this->listing($me, ['status' => 'sold'])];
        $this->actingAs($me, 'sanctum');

        $this->postJson(route('api.v1.market.mine.bulk'), ['action' => 'pause', 'ids' => [$a->id, $b->id]])->assertOk()->assertJsonPath('changed', 1);
        $this->assertSame('sold', $b->fresh()->status->value);
        $this->postJson(route('api.v1.market.mine.bulk'), ['action' => 'remove', 'ids' => [$a->id, $b->id]])->assertOk()->assertJsonPath('changed', 2);
        $this->getJson(route('api.v1.market.mine.index'))->assertJsonPath('total', 0);
    }

    public function test_insights_count_the_window_and_hide_offers_when_they_are_off(): void
    {
        $me = $this->createUser();
        $l = $this->listing($me);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.mine.insights', [$l->id, 'days' => 7]))->assertOk()
            ->assertJsonPath('days', 7)->assertJsonCount(7, 'series')->assertJsonPath('offers_enabled', false);
        $this->getJson(route('api.v1.market.mine.insights', [$l->id, 'days' => 12]))->assertJsonPath('days', 30);
    }

    public function test_a_profile_shows_the_feed_to_others_and_everything_but_removed_to_its_owner(): void
    {
        [$me, $seller] = [$this->createUser(['username' => 'me_myself']), $this->createUser(['username' => 'shopkeeper'])];
        $this->listing($seller);
        $this->listing($seller, ['status' => 'paused']);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.seller.show', 'shopkeeper'))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('profile.is_self', false)
            ->assertJsonPath('profile.connection_state', 'none')->assertJsonPath('profile.stats.active', 1);

        $this->actingAs($seller, 'sanctum')->getJson(route('api.v1.market.seller.show', 'shopkeeper'))->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('profile.is_self', true)->assertJsonPath('profile.connection_state', 'self');
        $own = collect($this->getJson(route('api.v1.market.seller.show', 'shopkeeper'))->json('listings'));
        $this->assertSame(['Paused'], $own->pluck('status_label')->filter()->values()->all());

        // The app asks for ?page=1: the header comes with it (the query's "1"
        // is a string; compared strictly, every profile in the app failed).
        $this->getJson(route('api.v1.market.seller.show', ['username' => 'shopkeeper', 'page' => 1]))->assertOk()
            ->assertJsonPath('profile.username', 'shopkeeper');
        $this->getJson(route('api.v1.market.seller.show', ['username' => 'shopkeeper', 'page' => 2]))->assertOk()
            ->assertJsonPath('profile', null);
    }

    public function test_someone_who_blocked_you_has_no_profile_but_the_blocker_can_unblock(): void
    {
        [$me, $them] = [$this->createUser(['username' => 'meme']), $this->createUser(['username' => 'blocker'])];
        UserConnection::create(['user_a_id' => min($me->id, $them->id), 'user_b_id' => max($me->id, $them->id), 'status' => UserConnection::STATUS_BLOCKED, 'requested_by' => $them->id]);

        $this->actingAs($me, 'sanctum')->getJson(route('api.v1.market.seller.show', 'blocker'))->assertNotFound();

        $this->actingAs($them, 'sanctum')->getJson(route('api.v1.market.seller.show', 'meme'))->assertOk()
            ->assertJsonPath('profile.connection_state', 'blocked-by-me');
        $this->postJson(route('api.v1.market.seller.connection', $me->id), ['action' => 'unblock'])->assertOk()
            ->assertJsonPath('connection_state', 'none');
    }

    public function test_connecting_runs_the_whole_row_and_messaging_waits_for_it(): void
    {
        [$me, $them] = [$this->createUser(), $this->createUser()];

        $this->actingAs($me, 'sanctum')->postJson(route('api.v1.market.seller.message', $them->id))->assertForbidden()->assertJsonPath('code', 'not_connected');
        $this->postJson(route('api.v1.market.seller.connection', $them->id), ['action' => 'request'])->assertOk()->assertJsonPath('connection_state', 'outgoing');
        // Out of turn: only the other side accepts.
        $this->postJson(route('api.v1.market.seller.connection', $them->id), ['action' => 'accept'])->assertStatus(409);

        $this->actingAs($them, 'sanctum')->postJson(route('api.v1.market.seller.connection', $me->id), ['action' => 'accept'])->assertOk()
            ->assertJsonPath('connection_state', 'connected');

        $room = $this->actingAs($me, 'sanctum')->postJson(route('api.v1.market.seller.message', $them->id))->assertOk()->json('room_id');
        $this->assertSame($room, $this->postJson(route('api.v1.market.seller.message', $them->id))->json('room_id'));
    }

    public function test_the_location_search_asks_nominatim_and_answers_in_the_composers_terms(): void
    {
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([[
            'lat' => '4.05', 'lon' => '9.7', 'display_name' => 'Akwa, Douala, Littoral, Cameroon',
            'address' => ['country_code' => 'cm', 'state' => 'Littoral', 'city' => 'Douala', 'suburb' => 'Akwa'],
        ], [
            // Typed into OpenStreetMap with an HTML entity in the name.
            'lat' => '4.06', 'lon' => '9.71', 'display_name' => "École la Pouponnière d&apos;AKWA, Douala, Cameroon",
            'address' => ['country_code' => 'cm', 'city' => 'Douala', 'suburb' => 'Quartier d&apos;Akwa'],
        ]])]);

        $this->actingAs($this->createUser(), 'sanctum')->getJson(route('api.v1.market.sell.places', ['q' => 'Akwa']))->assertOk()
            ->assertJsonPath('places.0.country', 'CM')->assertJsonPath('places.0.region', 'Littoral')
            ->assertJsonPath('places.0.neighborhood', 'Akwa')->assertJsonPath('places.0.lng', 9.7)
            ->assertJsonPath('places.1.label', "École la Pouponnière d'AKWA, Douala, Cameroon")
            ->assertJsonPath('places.1.neighborhood', "Quartier d'Akwa");
    }

    public function test_the_web_composer_still_publishes_through_the_shared_service(): void
    {
        $me = $this->createUser();

        Livewire::actingAs($me)->test(\App\Livewire\Marketplace\ListingComposer::class)
            ->call('chooseType', 'item')
            ->set('photos', [UploadedFile::fake()->image('a.jpg', 600, 400), UploadedFile::fake()->image('b.jpg', 600, 400)])
            ->call('makeCover', MarketplaceListingMedia::query()->max('id'))
            ->set('categoryId', $this->phones->id)
            ->set('title', 'Itel A70 boxed')
            ->set('price', '35000')
            ->set('locationLabel', 'Douala, Littoral')
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect();

        $l = MarketplaceListing::where('user_id', $me->id)->first();
        $this->assertSame('active', $l->status->value);
        $this->assertStringStartsWith('itel-a70-boxed-', $l->slug);
        // The starred photo moved to the front and is the only cover.
        $first = $l->media()->orderBy('position')->first();
        $this->assertTrue((bool) $first->is_cover);
        $this->assertSame(1, $l->media()->where('is_cover', true)->count());
    }
}

