<?php

namespace Tests\Feature;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * A member should land in a regional room wherever they are.
 *
 * The region used to be read from one key, `address.state`. That is enough
 * almost everywhere and not enough in China: for the four municipalities
 * Nominatim returns no `state` at all, so a user in Beijing arrived with a
 * country and nothing else — and a regional room is only created when there is
 * a region, so they never reached one.
 *
 * The payloads below are the real shapes, captured from Nominatim.
 */
class LocationRegionResolutionTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    /** Beijing: a district for a city, and the ISO code as the only region signal. */
    private function beijingAddress(): array
    {
        return [
            'address' => [
                'suburb'         => 'Donghuamen Subdistrict',
                'city'           => 'Dongcheng District',
                'ISO3166-2-lvl4' => 'CN-BJ',
                'postcode'       => '100010',
                'country'        => 'China',
                'country_code'   => 'cn',
            ],
        ];
    }

    public function test_a_user_in_beijing_gets_a_region(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($this->beijingAddress())]);

        $user = $this->createUser();

        app(LocationService::class)->handleUserLocation(
            $user, 'China', '', null, 39.9042, 116.4074
        );

        $this->assertSame('Beijing', $user->fresh()->current_region);
    }

    public function test_the_beijing_regional_room_is_created(): void
    {
        // The point of resolving a region at all: without one there is no room
        // and the member never reaches a regional community.
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($this->beijingAddress())]);

        $user = $this->createUser(['username' => 'beijinger']);

        app(LocationService::class)->handleUserLocation(
            $user, 'China', '', null, 39.9042, 116.4074
        );

        $this->assertDatabaseHas('yard_rooms', [
            'tenant_id' => $this->tenant->id,
            'room_type' => RoomType::Regional->value,
            'country'   => 'China',
            'region'    => 'Beijing',
        ]);
    }

    public function test_the_iso_code_alone_is_enough(): void
    {
        // When the client already read the code, the server should name the
        // region without paying for a second round trip to the geocoder.
        Http::fake();

        $user = $this->createUser(['username' => 'has-iso']);

        app(LocationService::class)->handleUserLocation(
            $user, 'China', '', null, 39.9042, 116.4074, 'CN-BJ'
        );

        $this->assertSame('Beijing', $user->fresh()->current_region);
        Http::assertNothingSent();
    }

    public function test_a_region_the_geocoder_names_is_used_as_is(): void
    {
        // Shanghai, Guangdong and Sichuan all answer with a state; only the
        // municipalities are missing one, so the common path must not change.
        Http::fake();

        $user = $this->createUser(['username' => 'shanghainese']);

        app(LocationService::class)->handleUserLocation(
            $user, 'China', '', 'Shanghai', 31.2304, 121.4737
        );

        $this->assertSame('Shanghai', $user->fresh()->current_region);
        Http::assertNothingSent();
    }

    public function test_an_unmapped_country_still_resolves_from_the_address(): void
    {
        // Nothing here is China-specific: any country whose answer carries a
        // name under some other key now resolves too.
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'county'       => 'Kilifi',
                'country'      => 'Kenya',
                'country_code' => 'ke',
            ],
        ])]);

        $user = $this->createUser(['username' => 'kenyan']);

        app(LocationService::class)->handleUserLocation(
            $user, 'Kenya', '', null, -3.63, 39.85
        );

        $this->assertSame('Kilifi', $user->fresh()->current_region);
    }

    public function test_a_geocoder_failure_leaves_the_country_saved(): void
    {
        // The provider may be unreachable — plausibly so from China. That must
        // cost the region, not the whole update.
        Http::fake(fn () => throw new \RuntimeException('getaddrinfo failed'));

        $user = $this->createUser(['username' => 'offline']);

        app(LocationService::class)->handleUserLocation(
            $user, 'China', '', null, 39.9042, 116.4074
        );

        $user->refresh();

        $this->assertSame('China', $user->current_country);
        $this->assertNull($user->current_region);
    }

    public function test_the_uk_still_maps_to_its_own_regions(): void
    {
        // The UK has twelve fixed regions and deliberately blanks anything
        // outside them. The general resolver runs after that branch and must
        // not undo it.
        Http::fake();

        $user = $this->createUser(['username' => 'londoner']);

        app(LocationService::class)->handleUserLocation(
            $user, 'United Kingdom', 'London', 'England'
        );

        $this->assertSame('London', $user->fresh()->current_region);
    }

    public function test_the_endpoint_accepts_the_iso_code(): void
    {
        Http::fake();

        $user = $this->createUser(['username' => 'via-http']);

        $this->actingAs($user)
            ->postJson(route('location.update'), [
                'lat'       => 39.9042,
                'lng'       => 116.4074,
                'country'   => 'China',
                'region'    => '',
                'isoRegion' => 'CN-BJ',
                'city'      => '',
            ])
            ->assertOk();

        $this->assertSame('Beijing', $user->fresh()->current_region);
    }
}
