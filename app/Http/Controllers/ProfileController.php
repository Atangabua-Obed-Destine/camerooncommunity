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
}
