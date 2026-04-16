<?php

namespace Tests\Unit;

use App\Enums\Solidarity\CampaignStatus;
use App\Models\SolidarityCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class SolidarityCampaignTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_progress_percent_is_correct(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 1000,
            'current_amount' => 250,
        ]);

        $this->assertEquals(25.0, $campaign->progress_percent);
    }

    public function test_progress_percent_caps_at_100(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 100,
            'current_amount' => 150,
        ]);

        $this->assertEquals(100, $campaign->progress_percent);
    }

    public function test_progress_percent_zero_target(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 0,
            'current_amount' => 0,
        ]);

        $this->assertEquals(0, $campaign->progress_percent);
    }

    public function test_net_amount_raised_deducts_platform_cut(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 1000,
            'current_amount' => 1000,
            'platform_cut_percent' => 5.00,
        ]);

        $this->assertEquals(950.00, $campaign->net_amount_raised);
    }

    public function test_days_remaining_returns_null_when_no_deadline(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'deadline' => null,
        ]);

        $this->assertNull($campaign->days_remaining);
    }

    public function test_days_remaining_returns_zero_when_past(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'deadline' => now()->subDays(5),
        ]);

        $this->assertEquals(0, $campaign->days_remaining);
    }

    public function test_pending_scope(): void
    {
        SolidarityCampaign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => CampaignStatus::PendingApproval]);
        SolidarityCampaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);

        $this->assertCount(1, SolidarityCampaign::pending()->get());
    }

    public function test_active_scope(): void
    {
        SolidarityCampaign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => CampaignStatus::PendingApproval]);
        SolidarityCampaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);

        $this->assertCount(1, SolidarityCampaign::active()->get());
    }
}
