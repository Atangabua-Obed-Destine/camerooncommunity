<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Models\UserFollow;

/**
 * Follow / unfollow another user (GoMarket "Follow seller"). Shared by the
 * seller profile and the listing-detail seller popover. Uses the existing
 * UserFollow model (follower_id → following_id).
 */
trait InteractsWithFollows
{
    public function isFollowing(int $userId): bool
    {
        $me = auth()->id();
        if (! $me || $me === $userId) {
            return false;
        }
        return UserFollow::where('follower_id', $me)
            ->where('following_id', $userId)
            ->exists();
    }

    public function followerCount(int $userId): int
    {
        return UserFollow::where('following_id', $userId)->count();
    }

    public function toggleFollow(int $userId): void
    {
        $me = auth()->user();
        if (! $me) {
            $this->redirectRoute('login');
            return;
        }
        if ($me->id === $userId) {
            return;
        }

        // Don't allow following across a block in either direction.
        if (method_exists($me, 'hasBlockedOrIsBlockedBy') && $me->hasBlockedOrIsBlockedBy($userId)) {
            return;
        }
        if (! User::where('tenant_id', $me->tenant_id)->whereKey($userId)->exists()) {
            return;
        }

        $existing = UserFollow::where('follower_id', $me->id)
            ->where('following_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->dispatch('toast', type: 'success', message: __('Unfollowed'));
        } else {
            UserFollow::create([
                'follower_id'  => $me->id,
                'following_id' => $userId,
            ]);
            $this->dispatch('toast', type: 'success', message: __('Following — you\'ll see their new listings'));
        }
    }
}
