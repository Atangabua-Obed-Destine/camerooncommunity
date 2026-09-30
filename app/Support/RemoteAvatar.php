<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a remote profile picture (Google sign-in) onto our own disk.
 *
 * Every avatar in the app is rendered as asset('storage/' . $user->avatar), so
 * an absolute URL in that column produces
 * https://ourdomain/storage/https://lh3.googleusercontent.com/... and a 404.
 * Rather than teach 30+ call sites about two kinds of avatar, the column only
 * ever holds a path on the public disk.
 */
class RemoteAvatar
{
    /** Anything bigger than this is not a profile picture. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * Download $url to storage/app/public/avatars and return the relative path
     * ('avatars/google-xxxx.jpg'), or null if it could not be fetched. Callers
     * treat null as "no avatar" and fall back to the initial.
     */
    public static function fetch(string $url): ?string
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }

        try {
            $response = Http::timeout(8)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            $type = Str::before((string) $response->header('Content-Type'), ';');
            $extension = self::EXTENSIONS[trim(strtolower($type))] ?? null;

            if (! $extension) {
                return null;
            }

            $path = 'avatars/google-' . Str::random(24) . '.' . $extension;
            Storage::disk('public')->put($path, $body);

            return $path;
        } catch (\Throwable $e) {
            logger()->warning('Could not copy remote avatar: ' . $e->getMessage());

            return null;
        }
    }
}
