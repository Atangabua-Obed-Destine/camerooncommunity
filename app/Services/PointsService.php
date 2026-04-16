<?php

namespace App\Services;

use App\Models\CommunityPointsLog;
use App\Models\User;

class PointsService
{
    /**
     * Award community points to a user.
     */
    public function award(User $user, string $action, ?int $points = null): void
    {
        $points = $points ?? config("cameroon.points.{$action}", 0);

        if ($points <= 0) {
            return;
        }

        CommunityPointsLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => $action,
            'points' => $points,
        ]);

        $user->increment('community_points', $points);
    }
}
