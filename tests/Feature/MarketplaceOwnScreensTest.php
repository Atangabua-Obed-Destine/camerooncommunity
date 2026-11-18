<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Livewire\Marketplace\Inbox;
use App\Livewire\Marketplace\LeaveReview;
use App\Livewire\Marketplace\MyListings;
use App\Livewire\Marketplace\Orders;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Models\YardRoomMember;
use App\Services\MarketplaceChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * GoMarket's own-account screens: My listings, Orders, Leave a review and the
 * Inbox. Each test is one of the bugs found in pass 7 of the UI-parity work
 * (docs/ui-parity/07-gomarket-screens.md §12).
 */
class MarketplaceOwnScreensTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private User $seller;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->seller = $this->createUser(['username' => 'seller', 'name' => 'Sali']);
        $this->categoryId = MarketplaceCategory::create([
            'tenant_id' => $this->tenant->id, 'name_en' => 'General', 'name_fr' => 'Général',
            'slug' => 'general', 'is_active' => true,
        ])->id;
    }

    private function listing(string $status, array $extra = []): MarketplaceListing
    {
        $title = ucfirst($status) . ' ' . uniqid();

        return MarketplaceListing::create(array_merge([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->seller->id, 'category_id' => $this->categoryId,
            'title' => $title, 'slug' => str($title)->slug(), 'description' => 'For sale.', 'price' => 5000,
            'currency' => 'XAF', 'condition' => 'good', 'status' => $status, 'published_at' => now(),
        ], $extra));
    }

    public function test_bulk_reactivate_only_brings_back_paused_listings(): void
    {
        $paused = $this->listing('paused');
        $sold = $this->listing('sold');
        $draft = $this->listing('draft');
        $review = $this->listing('pending_review');

        Livewire::actingAs($this->seller)->test(MyListings::class)
            ->set('selected', array_map('strval', [$paused->id, $sold->id, $draft->id, $review->id]))
            ->call('bulkReactivate');

        // Before, all four went live, and the draft skipped validation and review.
        $this->assertSame('active', $paused->refresh()->status->value);
        $this->assertSame('sold', $sold->refresh()->status->value);
        $this->assertSame('draft', $draft->refresh()->status->value);
        $this->assertSame('pending_review', $review->refresh()->status->value);
    }

    public function test_bulk_pause_only_pauses_live_listings_and_says_when_nothing_could(): void
    {
        $sold = $this->listing('sold');

        Livewire::actingAs($this->seller)->test(MyListings::class)
            ->set('selected', [(string) $sold->id])
            ->call('bulkPause')
            ->assertDispatched('toast', type: 'error');

        $this->assertSame('sold', $sold->refresh()->status->value);
    }

    public function test_all_leaves_out_deleted_listings_and_pending_review_has_a_pill(): void
    {
        $gone = $this->listing('active', ['title' => 'Deleted lamp', 'slug' => 'deleted-lamp']);
        $gone->delete();
        $this->listing('pending_review', ['title' => 'Waiting chair', 'slug' => 'waiting-chair']);

        Livewire::actingAs($this->seller)->test(MyListings::class)
            ->assertDontSee('Deleted lamp')
            ->assertSee('Waiting chair')
            ->assertSee("setFilter('pending_review')", false)
            ->call('setFilter', 'pending_review')
            ->assertSee('Waiting chair');
    }

    public function test_a_partial_sale_sends_no_review_link_the_buyer_cannot_use(): void
    {
        $buyer = $this->createUser(['username' => 'buyer']);
        $listing = $this->listing('active', ['quantity' => 3]);

        Livewire::actingAs($this->seller)->test(MyListings::class)
            ->call('openMarkSold', $listing->id)
            ->call('pickBuyer', $buyer->id)
            ->call('saveSold');

        $this->assertSame(2, $listing->refresh()->quantity);
        $this->assertSame(0, $buyer->notifications()->count());
    }

    public function test_completing_an_order_for_one_of_several_keeps_the_listing_up_and_lets_the_buyer_review(): void
    {
        $buyer = $this->createUser(['username' => 'buyer']);
        $listing = $this->listing('active', ['quantity' => 3]);
        $order = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id, 'listing_id' => $listing->id, 'buyer_id' => $buyer->id,
            'seller_id' => $this->seller->id, 'reference' => 'GM-TEST', 'status' => OrderStatus::Paid,
            'provider' => 'momo', 'amount' => 5000, 'currency' => 'XAF', 'quantity' => 1,
        ]);

        Livewire::actingAs($this->seller)->test(Orders::class)->call('release', $order->id);

        $listing->refresh();
        $this->assertSame('active', $listing->status->value);
        $this->assertSame(2, $listing->quantity);

        Livewire::actingAs($buyer)->test(LeaveReview::class, ['slug' => $listing->slug])->assertOk();
    }

    public function test_opening_the_inbox_marks_nothing_read_until_a_conversation_is_on_screen(): void
    {
        $buyer = $this->createUser(['username' => 'buyer']);
        $listing = $this->listing('active');
        $room = app(MarketplaceChatService::class)->openConversation($buyer, $this->seller, $listing)['room'];
        app(MarketplaceChatService::class)->postMessage($room, $buyer, 'Is it still available?');

        $lastRead = fn () => YardRoomMember::where('room_id', $room->id)->where('user_id', $this->seller->id)->value('last_read_at');
        YardRoomMember::where('room_id', $room->id)->where('user_id', $this->seller->id)->update(['last_read_at' => null]);

        // On a phone the list shows first: the conversation stays unread.
        $inbox = Livewire::actingAs($this->seller)->test(Inbox::class, ['tab' => 'selling']);
        $this->assertNull($lastRead());

        // Both panes on screen (a desktop), or the conversation opened: now it's read.
        $inbox->call('showingBothPanes');
        $this->assertNotNull($lastRead());
    }

    public function test_a_link_to_a_conversation_opens_it_on_a_phone(): void
    {
        $buyer = $this->createUser(['username' => 'buyer']);
        $room = app(MarketplaceChatService::class)->openConversation($buyer, $this->seller, $this->listing('active'))['room'];

        Livewire::actingAs($this->seller)
            ->withQueryParams(['tab' => 'selling', 'c' => $room->id])
            ->test(Inbox::class)
            ->assertSet('threadOpen', true);
    }

    public function test_a_long_thread_shows_its_newest_messages(): void
    {
        $buyer = $this->createUser(['username' => 'buyer']);
        $room = app(MarketplaceChatService::class)->openConversation($buyer, $this->seller, $this->listing('active'))['room'];
        foreach (range(1, 205) as $i) {
            \App\Models\YardMessage::factory()->create([
                'tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $buyer->id,
                'content' => "Message number {$i}", 'message_type' => 'text',
            ]);
        }

        $messages = Livewire::actingAs($this->seller)
            ->withQueryParams(['tab' => 'selling', 'c' => $room->id])
            ->test(Inbox::class)
            ->instance()->messages;

        $this->assertCount(200, $messages);
        $this->assertSame('Message number 205', $messages->last()->content);
    }
}
