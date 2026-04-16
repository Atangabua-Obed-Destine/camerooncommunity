<?php

namespace Tests\Unit;

use App\Enums\Solidarity\CampaignStatus;
use App\Models\SolidarityCampaign;
use App\Models\SolidarityContribution;
use App\Services\SolidarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class SolidarityServiceTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_record_contribution_increments_campaign_totals(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 10000,
            'current_amount' => 0,
            'total_contributors' => 0,
        ]);

        $contribution = SolidarityContribution::create([
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'contributor_id' => $this->createUser()->id,
            'amount' => 100.00,
            'platform_fee' => 5.00,
            'net_amount' => 95.00,
            'currency' => 'GBP',
            'payment_method' => 'card',
            'payment_status' => 'confirmed',
        ]);

        $service = new SolidarityService();
        $service->recordContribution($contribution);

        $campaign->refresh();
        $this->assertEquals(95.00, $campaign->current_amount);
        $this->assertEquals(1, $campaign->total_contributors);
    }

    public function test_record_contribution_marks_goal_reached_when_target_met(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 100,
            'current_amount' => 50,
            'total_contributors' => 1,
        ]);

        $contribution = SolidarityContribution::create([
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'contributor_id' => $this->createUser()->id,
            'amount' => 60.00,
            'platform_fee' => 3.00,
            'net_amount' => 57.00,
            'currency' => 'GBP',
            'payment_method' => 'card',
            'payment_status' => 'confirmed',
        ]);

        $service = new SolidarityService();
        $service->recordContribution($contribution);

        $campaign->refresh();
        $this->assertEquals(CampaignStatus::GoalReached, $campaign->status);
    }

    public function test_record_contribution_does_not_change_status_if_below_target(): void
    {
        $campaign = SolidarityCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'target_amount' => 10000,
            'current_amount' => 0,
            'total_contributors' => 0,
        ]);

        $contribution = SolidarityContribution::create([
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'contributor_id' => $this->createUser()->id,
            'amount' => 10.00,
            'platform_fee' => 0.50,
            'net_amount' => 9.50,
            'currency' => 'GBP',
            'payment_method' => 'card',
            'payment_status' => 'confirmed',
        ]);

        $service = new SolidarityService();
        $service->recordContribution($contribution);

        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Active, $campaign->status);
    }
}
