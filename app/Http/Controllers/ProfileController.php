<?php

namespace App\Http\Controllers;

use App\Models\CommunityPointsLog;
use App\Models\YardRoomMember;
use App\Models\SolidarityContribution;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        return view('profile.index', [
            'user' => $user,
            'totalPoints' => CommunityPointsLog::where('user_id', $user->id)->sum('points_awarded'),
            'roomsJoined' => YardRoomMember::where('user_id', $user->id)->count(),
            'contributions' => SolidarityContribution::where('contributor_id', $user->id)->count(),
            'badges' => UserBadge::where('user_id', $user->id)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'language_pref' => 'required|in:en,fr',
            'home_region' => 'nullable|string|max:100',
            'home_city' => 'nullable|string|max:100',
        ]);

        $user->update($validated);

        return back()->with('success', __('Profile updated successfully.'));
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = auth()->user();

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', __('Profile photo updated.'));
    }

    public function removeAvatar()
    {
        $user = auth()->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return back()->with('success', __('Profile photo removed.'));
    }

    /**
     * Upload a Facebook/WhatsApp-style cover photo. Wide aspect images work
     * best; we accept up to 8 MB and replace any existing one on disk.
     */
    public function updateCover(Request $request)
    {
        $request->validate([
            'cover_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $user = auth()->user();

        if ($user->cover_photo && Storage::disk('public')->exists($user->cover_photo)) {
            Storage::disk('public')->delete($user->cover_photo);
        }

        $path = $request->file('cover_photo')->store('cover-photos', 'public');
        $user->update(['cover_photo' => $path]);

        return back()->with('success', __('Cover photo updated.'));
    }

    public function removeCover()
    {
        $user = auth()->user();

        if ($user->cover_photo && Storage::disk('public')->exists($user->cover_photo)) {
            Storage::disk('public')->delete($user->cover_photo);
        }

        $user->update(['cover_photo' => null]);

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
            ->where('tenant_id', $viewer->tenant_id)
            ->firstOrFail();

        // Block-aware: hide profile in either direction so blocked users
        // can't snoop and blockers don't see ghost stats.
        if (method_exists($viewer, 'hasBlockedOrIsBlockedBy') && $viewer->hasBlockedOrIsBlockedBy($user->id)) {
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
            'rooms'         => YardRoomMember::where('user_id', $user->id)->count(),
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
