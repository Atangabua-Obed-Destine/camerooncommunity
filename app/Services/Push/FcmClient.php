<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Firebase Cloud Messaging, HTTP v1, with nothing but the service account key.
 *
 * Google's API wants an OAuth access token. A service account gets one by
 * signing a short JWT with its private key and trading it at the token URI;
 * the answer lasts an hour and is cached for a little less. That is all the
 * google/auth library would do here, so it is done directly rather than
 * adding a dependency.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?array $account = null;

    /** Push is on when the key file is there and is a service account key. */
    public function enabled(): bool
    {
        return $this->account() !== null;
    }

    /**
     * Send one message to one install.
     *
     * @param  array<string, mixed>  $message  the v1 `message` without `token`
     * @return string 'sent', 'unregistered' (the install is gone: forget the
     *                token) or 'failed' (try again another time)
     */
    public function send(string $token, array $message): string
    {
        $account = $this->account();
        if ($account === null) {
            return 'failed';
        }

        try {
            $response = Http::withToken($this->accessToken($account))
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                    'message' => ['token' => $token] + $message,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('FCM send failed: '.$e->getMessage());

            return 'failed';
        }

        if ($response->successful()) {
            return 'sent';
        }

        // NOT_FOUND / UNREGISTERED: the app was uninstalled or the token
        // rotated. INVALID_ARGUMENT on the token: it was never valid.
        $status = (string) $response->json('error.status');
        $detail = collect($response->json('error.details', []))->pluck('errorCode')->filter()->first();
        if ($response->status() === 404 || $detail === 'UNREGISTERED'
            || ($status === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'token'))) {
            return 'unregistered';
        }

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($account));
        }
        logger()->warning('FCM send refused', ['status' => $response->status(), 'error' => $status ?: $response->body()]);

        return 'failed';
    }

    private function accessToken(array $account): string
    {
        return Cache::remember($this->cacheKey($account), now()->addMinutes(50), function () use ($account) {
            $now = time();
            $jwt = $this->jwt([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => $account['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key']);

            $token = Http::asForm()->timeout(10)->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('FCM: no access token in the OAuth answer');
            }

            return $token;
        });
    }

    private function jwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64url(json_encode($claims)),
        ];
        $signature = '';
        if (! openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('FCM: could not sign with the service account key');
        }
        $segments[] = $this->base64url($signature);

        return implode('.', $segments);
    }

    private function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function cacheKey(array $account): string
    {
        return 'fcm:access-token:'.md5($account['client_email']);
    }

    /** The service account key, or null when push is not set up. */
    private function account(): ?array
    {
        if ($this->account !== null) {
            return $this->account;
        }

        $path = (string) config('services.fcm.credentials');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || ($json['type'] ?? null) !== 'service_account'
            || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            logger()->warning('FCM: '.$path.' is not a Firebase service account key');

            return null;
        }

        $json['token_uri'] ??= 'https://oauth2.googleapis.com/token';

        return $this->account = $json;
    }
}
