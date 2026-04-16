<?php

namespace App\Enums\Solidarity;

enum CampaignStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Paused = 'paused';
    case GoalReached = 'goal_reached';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::GoalReached => 'Goal Reached',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingApproval => 'yellow',
            self::Active => 'blue',
            self::Paused => 'gray',
            self::GoalReached => 'indigo',
            self::Completed => 'blue',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
        };
    }
}
