<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * The phone's half of "Continue with Google".
 *
 * The web signs in through Socialite: the browser goes to Google and comes
 * back to /auth/google/callback with a code. A native app cannot take that
 * redirect. Instead Android's own Google sign-in hands the app an ID token,
 * a JWT signed by Google, and the app sends it here.
 *
 * Google's tokeninfo endpoint checks the signature and expiry for us. What it
 * cannot know is who the token was for, so the audience is checked here: it
 * must be this site's own OAuth client (the web client id the app passes to
 * Google as its serverClientId). Without that check, a token any other app
 * obtained for its own users would sign them in here.
 */
class GoogleIdToken
{
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * The Google identity in a valid token, or a 422 on `id_token`.
     *
     * @return array{google_id: string, email: string, email_verified: bool, name: string, picture: string, locale: string}
     */
    public function verify(string $idToken): array
    {
        $clientId = (string) config('services.google.client_id');

        try {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            logger()->warning('Google token check failed: '.$e->getMessage());
            throw $this->invalid();
        }

        $claims = $response->successful() ? (array) $response->json() : [];

        $valid = $clientId !== ''
            && ($claims['aud'] ?? null) === $clientId
            && in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && (int) ($claims['exp'] ?? 0) > time()
            && ! empty($claims['sub'])
            && ! empty($claims['email']);

        if (! $valid) {
            throw $this->invalid();
        }

        $email = strtolower((string) $claims['email']);

        return [
            'google_id' => (string) $claims['sub'],
            'email' => $email,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => trim((string) ($claims['name'] ?? '')) ?: strstr($email, '@', true),
            'picture' => (string) ($claims['picture'] ?? ''),
            'locale' => str_starts_with(strtolower((string) ($claims['locale'] ?? '')), 'fr') ? 'fr' : 'en',
        ];
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'id_token' => __('Google sign-in was cancelled or failed. Please try again.'),
        ]);
    }
}
