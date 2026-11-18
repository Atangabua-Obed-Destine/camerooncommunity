<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\RoomType;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\ConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Deleting your account (owner's decision, 2026-11-11: anonymise). Apple
 * requires it inside the app (guideline 5.1.1(v)), and the web has it too.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function member(array $extra = []): User
    {
        return $this->createUser(array_merge([
            'username' => 'ama', 'name' => 'Ama Ngu', 'email' => 'ama@example.com',
            'password' => Hash::make('Kamer2026'), 'onboarded_at' => now(), 'current_country' => 'Cameroon',
        ], $extra));
    }

    public function test_the_account_is_anonymised_and_what_others_need_stays(): void
    {
        $ama = $this->member();
        $friend = $this->createUser(['username' => 'friend', 'name' => 'Friend']);

        app(ConnectionService::class)->request($friend, $ama);
        app(ConnectionService::class)->accept($ama, $friend->id);

        $dm = YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => RoomType::DirectMessage]);
        $city = YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => RoomType::City]);
        foreach ([$dm, $city] as $room) {
            YardRoomMember::create(['tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $ama->id, 'role' => 'member', 'joined_at' => now()]);
        }
        $said = YardMessage::factory()->create(['tenant_id' => $this->tenant->id, 'room_id' => $dm->id, 'user_id' => $ama->id, 'content' => 'See you Sunday']);

        $category = MarketplaceCategory::create(['tenant_id' => $this->tenant->id, 'name_en' => 'G', 'name_fr' => 'G', 'slug' => 'g', 'is_active' => true]);
        $listing = MarketplaceListing::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $ama->id, 'category_id' => $category->id, 'title' => 'Lamp',
            'slug' => 'lamp', 'description' => 'Bright.', 'price' => 10, 'currency' => 'XAF', 'condition' => 'good',
            'status' => 'active', 'published_at' => now(),
        ]);
        $offer = MarketplaceOffer::create([
            'listing_id' => $listing->id, 'buyer_id' => $friend->id, 'seller_id' => $ama->id,
            'amount' => 8, 'currency' => 'XAF', 'status' => OfferStatus::Pending,
        ]);

        $this->actingAs($ama)->delete(route('account.destroy'), ['delete_confirm' => 'Kamer2026'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('account_deleted');
        $this->assertGuest();

        $ama->refresh();
        $this->assertTrue($ama->isAnonymised());
        $this->assertSame('Deleted member', $ama->name);
        $this->assertStringNotContainsString('ama', $ama->email);
        $this->assertNull($ama->current_country);
        $this->assertFalse($ama->is_active);

        // Gone: connections, the listing, the city room, the open offer.
        $this->assertNull(UserConnection::between($ama->id, $friend->id));
        $this->assertSoftDeleted($listing);
        $this->assertFalse(YardRoomMember::where('room_id', $city->id)->where('user_id', $ama->id)->exists());
        $this->assertSame(OfferStatus::Withdrawn, $offer->refresh()->status);

        // Kept: the friend's one-to-one chat, with what Ama said in it.
        $this->assertTrue(YardRoomMember::where('room_id', $dm->id)->where('user_id', $ama->id)->exists());
        $this->assertSame('See you Sunday', $said->refresh()->content);
        $this->assertSame('Deleted member', $said->user->name);

        // Nobody can find or sign in as Ama any more.
        $this->actingAs($friend)->get(route('people', ['tab' => 'all']))->assertDontSee('title="ama"', false);
        auth()->logout();
        $this->post('/login', ['email' => 'ama@example.com', 'password' => 'Kamer2026'])->assertSessionHasErrors('email');
    }

    public function test_it_needs_the_password(): void
    {
        $ama = $this->member();

        $this->actingAs($ama)->from('/profile')->delete(route('account.destroy'), ['delete_confirm' => 'wrong'])
            ->assertSessionHasErrors('delete_confirm');

        $this->assertFalse($ama->refresh()->isAnonymised());
    }

    public function test_a_google_account_confirms_with_the_word_delete(): void
    {
        $ama = $this->member(['password' => null, 'google_id' => 'g-123']);

        $this->actingAs($ama)->delete(route('account.destroy'), ['delete_confirm' => 'delete'])->assertRedirect(route('home'));

        $this->assertTrue($ama->refresh()->isAnonymised());
        $this->assertNull($ama->google_id);
    }

    public function test_the_app_can_delete_the_account_and_its_tokens_stop_working(): void
    {
        $ama = $this->member();
        $token = $ama->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['delete_confirm' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors('delete_confirm');

        $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['delete_confirm' => 'Kamer2026'])
            ->assertOk()->assertJson(['status' => 'deleted']);

        $this->assertSame(0, $ama->tokens()->count());
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertUnauthorized();
    }

    public function test_settings_offers_it(): void
    {
        $ama = $this->member();

        $this->actingAs($ama)->get(route('marketplace.seller', ['username' => 'ama']))
            ->assertOk()
            ->assertSee(route('account.destroy'), false);
    }
}
