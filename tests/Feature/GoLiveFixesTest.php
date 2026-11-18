<?php

namespace Tests\Feature;

use App\Enums\Language;
use App\Enums\OrderStatus;
use App\Enums\RoomType;
use App\Livewire\AI\KamerChat;
use App\Livewire\Auth\RegisterWizard;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSavedSearch;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\ConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Fixes made while readying the site to go live (2026-11-11/12). Each test
 * names what was broken.
 */
class GoLiveFixesTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function member(array $extra = []): User
    {
        return $this->createUser(array_merge(['onboarded_at' => now(), 'current_country' => 'Cameroon'], $extra));
    }

    public function test_google_must_have_verified_the_email_before_it_is_linked(): void
    {
        $owner = $this->member(['email' => 'ama@example.com', 'username' => 'ama']);

        $google = Mockery::mock(\Laravel\Socialite\Two\User::class)->makePartial();
        $google->id = 'g-attacker';
        $google->email = 'ama@example.com';
        $google->name = 'Not Ama';
        $google->user = ['email_verified' => false];
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertNull($owner->refresh()->google_id);
        $this->assertGuest();
    }

    public function test_the_founding_member_tease_only_shows_while_seats_remain(): void
    {
        $this->tenant->update(['settings' => ['founding_member_cap' => 1]]);
        $this->member();

        $this->assertSame(0, Livewire::test(RegisterWizard::class)->instance()->foundingSeatsLeft());
    }

    public function test_the_blocker_sees_the_profile_with_unblock_and_the_blocked_member_does_not(): void
    {
        $blocker = $this->member(['username' => 'blocker']);
        $blocked = $this->member(['username' => 'blocked']);
        app(ConnectionService::class)->block($blocker, $blocked->id);

        $this->actingAs($blocker)->get(route('marketplace.seller', ['username' => 'blocked']))
            ->assertOk()->assertSee('wire:click="unblock"', false);

        $this->actingAs($blocked)->get(route('marketplace.seller', ['username' => 'blocker']))->assertNotFound();
    }

    public function test_rooms_counts_community_rooms_not_private_chats(): void
    {
        $ama = $this->member(['username' => 'ama']);
        foreach ([RoomType::City, RoomType::DirectMessage, RoomType::DirectMessage, RoomType::PrivateGroup] as $type) {
            $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => $type]);
            YardRoomMember::create(['tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $ama->id, 'role' => 'member', 'joined_at' => now()]);
        }

        $this->assertSame(1, YardRoomMember::where('user_id', $ama->id)->inCommunityRooms()->count());
    }

    public function test_solidaritys_always_zero_stats_wait_for_it_to_launch(): void
    {
        $ama = $this->member(['username' => 'ama']);

        $this->actingAs($ama)->get(route('marketplace.seller', ['username' => 'ama']))
            ->assertOk()->assertDontSee("t('Contributions', 'Contributions')", false);

        config(['cameroon.modules.solidarity_live' => true]);
        $this->actingAs($ama)->get(route('marketplace.seller', ['username' => 'ama']))
            ->assertSee("t('Contributions', 'Contributions')", false);
    }

    public function test_kamer_speaks_french_to_a_french_member_and_shows_their_message_before_replying(): void
    {
        $ama = $this->member(['language_pref' => Language::French]);
        app()->setLocale('fr');

        $kamer = Livewire::actingAs($ama)->test(KamerChat::class)->call('open');
        $this->assertStringStartsWith('Salut', $kamer->get('messages')[0]['content']);

        // Step one shows the message and the dots; the reply is a second request.
        $kamer->set('input', 'Bonjour')->call('send')
            ->assertSet('isLoading', true)
            ->assertDispatched('kamer-thinking');
        $this->assertSame('Bonjour', last($kamer->get('messages'))['content']);
    }

    public function test_a_saved_search_badge_waits_for_the_member_not_the_alert_job(): void
    {
        $ama = $this->member();
        $search = MarketplaceSavedSearch::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $ama->id, 'name' => 'Lamps', 'filters' => [],
            'notify_email' => true, 'last_notified_at' => now()->subDays(2), 'last_seen_at' => now()->subDays(2),
        ]);

        // The hourly job sends an alert...
        $search->update(['last_notified_at' => now()]);

        // ...and the badge still counts from when Ama last looked.
        $this->assertTrue($search->refresh()->last_seen_at->lt(now()->subDay()));
    }

    public function test_orders_needing_attention_count_the_same_everywhere(): void
    {
        $seller = $this->member();
        $buyer = $this->member();
        $category = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'G', 'name_fr' => 'G', 'slug' => 'g', 'is_active' => true]);
        $listing = MarketplaceListing::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Lamp',
            'slug' => 'lamp', 'description' => 'x', 'price' => 10, 'currency' => 'XAF', 'condition' => 'good', 'status' => 'active',
        ]);
        foreach ([OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Released] as $i => $status) {
            MarketplaceOrder::create([
                'tenant_id' => $this->tenant->id, 'listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
                'reference' => "GM-{$i}", 'status' => $status, 'provider' => 'momo', 'amount' => 10, 'currency' => 'XAF', 'quantity' => 1,
            ]);
        }

        $this->assertSame(2, MarketplaceOrder::needingSellerAttention($seller->id));
    }

    public function test_every_response_carries_the_security_headers_and_errors_are_branded(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('Back to the home page');
    }

    public function test_the_homepage_shows_real_numbers_and_no_invented_testimonials(): void
    {
        $this->member(['current_country' => 'United Kingdom', 'current_region' => 'London']);
        $this->member(['current_country' => 'France', 'current_region' => 'Île-de-France']);
        $this->member(['current_country' => 'France', 'anonymised_at' => now()]);   // deleted: not counted
        \Illuminate\Support\Facades\Cache::flush();

        $stats = \App\Http\Controllers\HomeController::communityStats();
        $this->assertSame(['members' => 2, 'countries' => 2, 'regions' => 2], array_intersect_key($stats, array_flip(['members', 'countries', 'regions'])));

        $this->get('/')->assertOk()
            ->assertSee('data-count-to="2"', false)
            ->assertDontSee('Afi Tanyi')
            ->assertDontSee('KamerNest, send packages')
            ->assertDontSee('Solidarity campaigns completed')
            ->assertDontSee('30+');
    }

    public function test_the_site_is_named_cameroon_network(): void
    {
        \App\Models\PlatformSetting::setValue('site_name', 'Cameroon Community', 'branding');

        $migration = require database_path('migrations/2026_11_11_000001_name_the_site_cameroon_network.php');
        $migration->up();

        $this->assertSame('Cameroon Network', \App\Models\PlatformSetting::getValue('site_name'));
    }
}
