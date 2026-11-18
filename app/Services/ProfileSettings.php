<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A member's own settings: the profile's Settings dialog (the seller page's
 * self-view and /profile). The web's ProfileController and the app's
 * Api\V1\ProfileController both come through here, so the two can never
 * accept different things.
 */
class ProfileSettings
{
    /** The settings form, as the web validates it. */
    public const RULES = [
        'name' => 'required|string|max:100',
        'language_pref' => 'required|in:en,fr',
        'home_region' => 'nullable|string|max:100',
        'home_city' => 'nullable|string|max:100',
        'show_archived_away' => 'nullable|boolean',
    ];

    public const AVATAR_RULES = ['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'];

    /** Wide images work best; up to 8 MB. */
    public const COVER_RULES = ['cover_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192'];

    /** @param array<string, mixed> $data validated against RULES */
    public function update(User $user, array $data): User
    {
        // An unticked box sends nothing on the web, so absent means off.
        $data['show_archived_away'] = (bool) ($data['show_archived_away'] ?? false);
        $data['name'] = trim((string) $data['name']);
        foreach (['home_region', 'home_city'] as $k) {
            $data[$k] = isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;
        }

        $user->update($data);

        return $user;
    }

    public function setAvatar(User $user, UploadedFile $file): User
    {
        $this->forget($user->avatar);
        $user->update(['avatar' => $file->store('avatars', 'public')]);

        return $user;
    }

    public function removeAvatar(User $user): User
    {
        $this->forget($user->avatar);
        $user->update(['avatar' => null]);

        return $user;
    }

    public function setCover(User $user, UploadedFile $file): User
    {
        $this->forget($user->cover_photo);
        $user->update(['cover_photo' => $file->store('cover-photos', 'public')]);

        return $user;
    }

    public function removeCover(User $user): User
    {
        $this->forget($user->cover_photo);
        $user->update(['cover_photo' => null]);

        return $user;
    }

    /** The regions of origin the form offers (config/cameroon.php). */
    public function regions(): array
    {
        return array_values(config('cameroon.regions', []));
    }

    /**
     * Whether deleting the account asks for the password, or (a Google-only
     * account, with none) for the word DELETE: AccountController::confirm.
     */
    public function deletesByPassword(User $user): bool
    {
        return $user->password && $user->google_id === null;
    }

    /** A replaced or removed photo leaves the disk too (not a Google URL). */
    private function forget(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
