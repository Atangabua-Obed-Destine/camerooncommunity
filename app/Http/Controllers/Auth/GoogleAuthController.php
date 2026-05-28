<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle the callback from Google after the user authorises.
     *
     * Three branches:
     *  1. Existing user with google_id  → log in straight to yard
     *  2. Existing user matching email  → link google_id then log in
     *  3. Brand new user                → stash profile in session and send
     *     them through the GPS-required register wizard so the location step
     *     is honoured (community accuracy rule).
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            logger()->warning('Google OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in was cancelled or failed. Please try again.'),
            ]);
        }

        $googleId = (string) $googleUser->getId();
        $email = strtolower((string) $googleUser->getEmail());
        $name = (string) ($googleUser->getName() ?: $googleUser->getNickname() ?: Str::before($email, '@'));
        $avatar = (string) ($googleUser->getAvatar() ?: '');
        $locale = $this->normaliseLocale($googleUser);

        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google did not return an email address. Please use a Google account with a verified email.'),
            ]);
        }

        // 1) Existing google_id → straight log in
        $existingByGoogle = User::withoutGlobalScopes()->where('google_id', $googleId)->first();
        if ($existingByGoogle) {
            return $this->loginAndRedirect($existingByGoogle, $avatar);
        }

        // 2) Existing email → link Google account
        $existingByEmail = User::withoutGlobalScopes()->where('email', $email)->first();
        if ($existingByEmail) {
            $existingByEmail->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $existingByEmail->email_verified_at ?: now(),
            ])->save();
            return $this->loginAndRedirect($existingByEmail, $avatar);
        }

        // 3) New user → stash profile and send into register wizard.
        //    The wizard still enforces the GPS location step before creating the account.
        session([
            'google_signup' => [
                'google_id' => $googleId,
                'email'     => $email,
                'name'      => $name,
                'avatar'    => $avatar,
                'locale'    => $locale,
            ],
        ]);

        return redirect()->route('register', ['step' => 1]);
    }

    /**
     * Map Google's locale string (e.g. "fr-CA", "en") to our two supported values.
     */
    protected function normaliseLocale(\Laravel\Socialite\Contracts\User $googleUser): string
    {
        $raw = '';
        if (method_exists($googleUser, 'getRaw')) {
            $raw = (string) ($googleUser->getRaw()['locale'] ?? '');
        }
        return Str::startsWith(strtolower($raw), 'fr') ? 'fr' : 'en';
    }

    /**
     * Log a user in and refresh their avatar from the Google profile if missing,
     * then send them to the post-login destination.
     */
    protected function loginAndRedirect(User $user, string $googleAvatar): RedirectResponse
    {
        if ($googleAvatar && empty($user->avatar)) {
            $user->forceFill(['avatar' => $googleAvatar])->save();
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('yard'));
    }
}
