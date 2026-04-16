<?php

namespace App\Services;

use App\Enums\Solidarity\CampaignStatus;
use App\Models\SolidarityCampaign;
use App\Models\SolidarityContribution;
use App\Models\User;

class SolidarityService
{
    /**
     * Record a confirmed contribution and update campaign totals.
     */
    public function recordContribution(SolidarityContribution $contribution): void
    {
        $campaign = $contribution->campaign;

        $campaign->increment('current_amount', $contribution->net_amount);
        $campaign->increment('total_contributors');

        // Check if target reached
        if ($campaign->current_amount >= $campaign->target_amount && $campaign->status === CampaignStatus::Active) {
            $campaign->update(['status' => CampaignStatus::GoalReached]);
        }
    }

    /**
     * Approve a campaign and make it visible.
     */
    public function approve(SolidarityCampaign $campaign, User $admin): void
    {
        $campaign->update([
            'status' => CampaignStatus::Active,
            'approved_by' => $admin->id,
        ]);
    }

    /**
     * Reject a campaign with a reason.
     */
    public function reject(SolidarityCampaign $campaign, string $reason): void
    {
        $campaign->update([
            'status' => CampaignStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
