<?php

namespace Tests\Unit;

use App\Enums\RoomType;
use App\Models\YardRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class YardRoomTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_national_scope_filters_correctly(): void
    {
        YardRoom::factory()->national()->create(['tenant_id' => $this->tenant->id]);
        YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => RoomType::City]);

        $this->assertCount(1, YardRoom::national()->get());
    }

    public function test_city_scope_filters_correctly(): void
    {
        YardRoom::factory()->national()->create(['tenant_id' => $this->tenant->id]);
        YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'room_type' => RoomType::City, 'city' => 'London']);

        $this->assertCount(1, YardRoom::city()->get());
    }

    public function test_for_country_scope(): void
    {
        YardRoom::factory()->national('United Kingdom')->create(['tenant_id' => $this->tenant->id]);
        YardRoom::factory()->national('France')->create(['tenant_id' => $this->tenant->id]);

        $this->assertCount(1, YardRoom::forCountry('United Kingdom')->get());
    }

    public function test_for_city_scope(): void
    {
        YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'city' => 'London']);
        YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'city' => 'Manchester']);

        $this->assertCount(1, YardRoom::forCity('London')->get());
    }

    public function test_active_scope(): void
    {
        YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        YardRoom::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->assertCount(1, YardRoom::active()->get());
    }

    public function test_soft_delete(): void
    {
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);
        $room->delete();

        $this->assertSoftDeleted($room);
        $this->assertCount(0, YardRoom::all());
        $this->assertCount(1, YardRoom::withTrashed()->get());
    }
}
