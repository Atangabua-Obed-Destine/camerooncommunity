<?php

namespace Tests\Feature;

use App\Enums\BadgeType;
use App\Enums\ListingStatus;
use App\Livewire\Marketplace\SellerProfile;
use App\Models\CommunityPointsLog;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\UserBadge;
use App\Models\UserConnection;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class SellerProfileIsDefaultTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_old_profile_url_redirects_to_seller_profile(): void
    {
        $viewer = $this->createUser();
        $target = $this->createUser(['username' => 'cristianos5q7c']);

        $this->actingAs($viewer)
            ->get('/u/' . $target->username)
            ->assertRedirect('/marketplace/seller/' . $target->username);
    }

    public function test_seller_profile_renders_for_another_user(): void
    {
        $viewer = $this->createUser();
        $target = $this->createUser([
            'username'     => 'someseller',
            'name'         => 'Ngwa Divine',
            'bio'          => 'Bamenda born, London based.',
            'home_region'  => 'North West',
            'current_city' => 'London',
        ]);

        $response = $this->actingAs($viewer)->get('/marketplace/seller/' . $target->username);

        $response->assertStatus(200);
        $response->assertSee('Ngwa Divine');
        $response->assertSee('Bamenda born, London based.');
        $response->assertSee('North West');          // Intro card
        $response->assertSee('Points', false);        // stats strip
        $response->assertSee('Message');              // action button
        // The retired /u/ page is not linked from here. Assert the URL, not the
        // button label: "View full profile" now appears on every page, because the
        // global profile preview card lives in the layout.
        $response->assertDontSee('/u/' . $target->username, false);
    }

    public function test_profile_carries_points_and_badges(): void
    {
        $viewer = $this->createUser();
        $target = $this->createUser(['username' => 'badgeduser']);

        CommunityPointsLog::create([
            'tenant_id'      => $this->tenant->id,
            'user_id'        => $target->id,
            'action'         => 'test',
            'points_awarded' => 250,
        ]);
        UserBadge::create([
            'tenant_id'  => $this->tenant->id,
            'user_id'    => $target->id,
            'badge_type' => BadgeType::TopContributor,
            'awarded_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get('/marketplace/seller/' . $target->username);

        $response->assertStatus(200);
        $response->assertSee('250');
        $response->assertSee('Top Contributor');
    }

    public function test_own_profile_offers_edit(): void
    {
        $user = $this->createUser(['username' => 'myself']);

        $response = $this->actingAs($user)->get('/marketplace/seller/' . $user->username);

        $response->assertStatus(200);
        // Own profile gets a Settings button that opens the popup.
        $response->assertSee('Settings');
        $response->assertSee(route('profile.avatar'), false);   // photo controls
        $response->assertSee(route('profile.cover'), false);
        // Settings live in a popup on this page now, not only on /profile.
        // Assert on form fields, not the action URL: that URL is just /profile,
        // which the header account menu also links to on every page.
        $response->assertSee('Display Name');
        $response->assertSee('Show away chats in GoConnect');
        $response->assertSee('Save Changes');
    }

    public function test_settings_popup_is_not_shown_on_someone_elses_profile(): void
    {
        $viewer = $this->createUser();
        $target = $this->createUser(['username' => 'notme']);

        $this->actingAs($viewer)
            ->get('/marketplace/seller/' . $target->username)
            ->assertDontSee('Show away chats in GoConnect')
            ->assertDontSee('Save Changes')
            ->assertDontSee(route('profile.avatar'), false);
    }

    public function test_own_profile_shows_draft_listings_but_others_do_not(): void
    {
        $owner  = $this->createUser(['username' => 'seller1']);
        $viewer = $this->createUser();

        $category = MarketplaceCategory::create([
            'tenant_id' => $this->tenant->id,
            'name_en'   => 'Cars',
            'name_fr'   => 'Voitures',
            'slug'      => 'cars',
        ]);

        $base = [
            'tenant_id'   => $this->tenant->id,
            'user_id'     => $owner->id,
            'category_id' => $category->id,
            'currency'    => 'XAF',
            'price'       => 1000,
            'visibility'  => 'public',
        ];

        MarketplaceListing::create($base + [
            'title'        => 'Live Toyota',
            'slug'         => 'live-toyota',
            'status'       => ListingStatus::Active->value,
            'published_at' => now(),
        ]);
        MarketplaceListing::create($base + [
            'title'  => 'Secret Draft Car',
            'slug'   => 'secret-draft-car',
            'status' => ListingStatus::Draft->value,
        ]);

        // The owner sees the draft, flagged as such.
        $this->actingAs($owner)
            ->get('/marketplace/seller/' . $owner->username)
            ->assertSee('Live Toyota')
            ->assertSee('Secret Draft Car')
            ->assertSee('draft');

        // Everyone else still only sees the published one.
        $this->actingAs($viewer)
            ->get('/marketplace/seller/' . $owner->username)
            ->assertSee('Live Toyota')
            ->assertDontSee('Secret Draft Car');
    }

    public function test_profile_offers_connect_and_sends_a_request(): void
    {
        $viewer = $this->createUser();
        $target = $this->createUser(['username' => 'connectme']);

        $this->actingAs($viewer)
            ->get('/marketplace/seller/' . $target->username)
            ->assertSee('Connect');

        Livewire::actingAs($viewer)
            ->test(SellerProfile::class, ['username' => $target->username])
            ->call('connect')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('user_connections', [
            'requested_by' => $viewer->id,
            'status'       => UserConnection::STATUS_PENDING,
        ]);
    }

    public function test_header_profile_link_points_at_the_new_profile_page(): void
    {
        $user = $this->createUser(['username' => 'linkme']);

        $this->actingAs($user)
            ->get('/yard')
            ->assertSee(route('marketplace.seller', ['username' => 'linkme']), false);
    }

    public function test_profile_url_falls_back_when_user_has_no_username(): void
    {
        $withName = $this->createUser(['username' => 'hasname']);
        $noName   = $this->createUser(['username' => null]);

        $this->assertSame(
            route('marketplace.seller', ['username' => 'hasname']),
            $withName->profileUrl()
        );
        $this->assertSame(route('profile'), $noName->profileUrl());
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/marketplace/seller/anyone')->assertRedirect('/login');
    }
}
