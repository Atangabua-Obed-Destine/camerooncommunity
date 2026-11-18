<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ProfileSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The profile's Settings dialog (seller-profile.blade.php's self-view, and
 * /profile): display name, language, region and city of origin, the "away
 * chats" switch, the profile and cover photos. ProfileSettings does the work
 * for the web's ProfileController too. Every answer carries the member as
 * UserResource, so the app's header, drawer and Home follow at once.
 */
class ProfileController extends Controller
{
    public function __construct(private ProfileSettings $settings) {}

    /** What the form needs beyond the member: its region list and how deletion confirms. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'regions' => $this->settings->regions(),
            'delete_by_password' => $this->settings->deletesByPassword($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->settings->update($request->user(), $request->validate(ProfileSettings::RULES));

        return $this->answer($user, __('Profile updated successfully.'));
    }

    public function avatar(Request $request): JsonResponse
    {
        $request->validate(ProfileSettings::AVATAR_RULES);

        return $this->answer($this->settings->setAvatar($request->user(), $request->file('avatar')), __('Profile photo updated.'));
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        return $this->answer($this->settings->removeAvatar($request->user()), __('Profile photo removed.'));
    }

    public function cover(Request $request): JsonResponse
    {
        $request->validate(ProfileSettings::COVER_RULES);

        return $this->answer($this->settings->setCover($request->user(), $request->file('cover_photo')), __('Cover photo updated.'));
    }

    public function removeCover(Request $request): JsonResponse
    {
        return $this->answer($this->settings->removeCover($request->user()), __('Cover photo removed.'));
    }

    private function answer($user, string $message): JsonResponse
    {
        return response()->json(['user' => new UserResource($user->refresh()), 'message' => $message]);
    }
}
