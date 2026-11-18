<?php

namespace App\Http\Controllers;

use App\Models\CommunityPointsLog;
use App\Models\YardRoomMember;
use App\Models\SolidarityContribution;
use App\Models\UserBadge;
use App\Services\ProfileSettings;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        return view('profile.index', [
            'user' => $user,
            'totalPoints' => CommunityPointsLog::where('user_id', $user->id)->sum('points_awarded'),
            'roomsJoined' => YardRoomMember::where('user_id', $user->id)->inCommunityRooms()->count(),
            'contributions' => SolidarityContribution::where('contributor_id', $user->id)->count(),
            'badges' => UserBadge::where('user_id', $user->id)->get(),
        ]);
    }

    public function update(Request $request, ProfileSettings $settings)
    {
        $settings->update(auth()->user(), $request->validate(ProfileSettings::RULES));

        return back()->with('success', __('Profile updated successfully.'));
    }

    public function updateAvatar(Request $request, ProfileSettings $settings)
    {
        $request->validate(ProfileSettings::AVATAR_RULES);
        $settings->setAvatar(auth()->user(), $request->file('avatar'));

        return back()->with('success', __('Profile photo updated.'));
    }

    public function removeAvatar(ProfileSettings $settings)
    {
        $settings->removeAvatar(auth()->user());

        return back()->with('success', __('Profile photo removed.'));
    }

    /**
     * Upload a Facebook/WhatsApp-style cover photo. Wide aspect images work
     * best; we accept up to 8 MB and replace any existing one on disk.
     */
    public function updateCover(Request $request, ProfileSettings $settings)
    {
        $request->validate(ProfileSettings::COVER_RULES);
        $settings->setCover(auth()->user(), $request->file('cover_photo'));

        return back()->with('success', __('Cover photo updated.'));
    }

    public function removeCover(ProfileSettings $settings)
    {
        $settings->removeCover(auth()->user());

        return back()->with('success', __('Cover photo removed.'));
    }

    /**
     * Public Facebook-style profile page for any user, looked up by username.
     * Visible to logged-in members of the same tenant. Shows cover, avatar,
     * bio, location, badges, and recent marketplace listings.
     */
    public function showPublic(string $username)
    {
        $viewer = auth()->user();

        $user = \App\Models\User::where('username', $username)
            ->discoverable()
            ->where('tenant_id', $viewer->tenant_id)
            ->firstOrFail();

        // Hidden from someone they blocked, so they can't snoop. The blocker
        // still gets the page, with Unblock (see SellerProfile).
        if ($viewer->isBlockedBy($user->id)) {
            abort(404);
        }

        $isSelf = $viewer->id === $user->id;

        $connection = $isSelf
            ? null
            : \App\Models\UserConnection::between($viewer->id, $user->id);

        $listings = \App\Models\MarketplaceListing::where('seller_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->limit(12)
            ->get();

        $stats = [
            'points'        => CommunityPointsLog::where('user_id', $user->id)->sum('points_awarded'),
            'rooms'         => YardRoomMember::where('user_id', $user->id)->inCommunityRooms()->count(),
            'contributions' => SolidarityContribution::where('contributor_id', $user->id)->count(),
            'listings'      => \App\Models\MarketplaceListing::where('seller_id', $user->id)
                                ->where('status', 'active')->count(),
        ];

        $badges = UserBadge::where('user_id', $user->id)->get();

        // Try to find an existing DM room so the "Message" CTA opens the
        // exact conversation instead of creating a duplicate.
        $dmRoomId = null;
        if (!$isSelf) {
            $dmRoomId = \App\Models\YardRoom::where('room_type', 'direct_message')
                ->whereHas('members', fn($q) => $q->where('user_id', $viewer->id))
                ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
                ->value('id');
        }

        return view('profile.public', [
            'user'       => $user,
            'isSelf'     => $isSelf,
            'connection' => $connection,
            'listings'   => $listings,
            'stats'      => $stats,
            'badges'     => $badges,
            'dmRoomId'   => $dmRoomId,
            'displayName' => $viewer->displayNameFor($user),
            'savedNickname' => $viewer->nicknameFor($user->id),
        ]);
    }
}
