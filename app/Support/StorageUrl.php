<?php

namespace App\Support;

/**
 * The public URL of a file on the public disk, from what a column holds.
 *
 * Avatar and cover columns hold a path ('avatars/x.jpg'). Older Google
 * sign-ups hold Google's own URL instead (users:localise-avatars copies those
 * over), and asset('storage/' . $url) turned them into a 404 and an empty
 * circle. Both kinds come out right here.
 */
final class StorageUrl
{
    public static function for(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : asset('storage/' . ltrim($path, '/'));
    }
}
