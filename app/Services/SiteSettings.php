<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class SiteSettings
{
    /**
     * Whether voice calling is offered.
     *
     * A platform setting rather than a constant so it can be turned back on
     * from the admin panel the day calls connect reliably, without a deploy.
     * Off until then: a button that starts a call which never connects is
     * worse than one that says the feature is not ready.
     */
    public static function callsEnabled(): bool
    {
        return filter_var(
            \App\Models\PlatformSetting::getValue('calls_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    protected static array $defaults = [
        'site_name' => 'Cameroon Network',
        'site_logo' => null,
        'site_favicon' => null,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $default ??= static::$defaults[$key] ?? null;

        return Cache::remember("site_setting:{$key}", 300, function () use ($key, $default) {
            return PlatformSetting::getValue($key, $default);
        });
    }

    public static function logoUrl(): ?string
    {
        $logo = static::get('site_logo');

        if (! $logo) {
            return null;
        }

        return asset('storage/' . $logo);
    }

    /**
     * The logo for a white or light background.
     *
     * The uploaded logo has a white "Cameroon" over a yellow "Network": on white,
     * half of it disappears. This is a copy with the white recoloured navy
     * (cm-green), made once per uploaded file and kept beside it, so a new
     * upload gets its own. Falls back to the logo itself when the copy can't be
     * made (an SVG, or no GD).
     */
    public static function logoOnLightUrl(): ?string
    {
        $logo = static::get('site_logo');

        if (! $logo) {
            return null;
        }

        $copy = static::logoOnLightPath($logo);
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (! $disk->exists($copy) && ! static::makeLogoOnLight($disk->path($logo), $disk->path($copy))) {
            return static::logoUrl();
        }

        return asset('storage/' . $copy);
    }

    /** Where the light-background copy of a logo lives, beside it on the public disk. */
    public static function logoOnLightPath(string $logo): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $logo) . '-on-light.png';
    }

    private static function makeLogoOnLight(string $from, string $to): bool
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($from)) {
            return false;
        }

        $source = @imagecreatefromstring((string) file_get_contents($from));
        if (! $source) {
            return false;
        }

        // Shown at most 120px tall; 360px covers a 3x screen and keeps this quick.
        $height = min(360, imagesy($source));
        $image = imagescale($source, (int) round(imagesx($source) * $height / imagesy($source)), $height);
        imagedestroy($source);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        [$navyR, $navyG, $navyB] = [0x24, 0x3A, 0x5C];
        for ($y = 0, $h = imagesy($image); $y < $h; $y++) {
            for ($x = 0, $w = imagesx($image); $x < $w; $x++) {
                $c = imagecolorat($image, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a === 127) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                // White and near-white only: the yellow "Network" has little blue.
                if (min($r, $g, $b) > 170 && max($r, $g, $b) - min($r, $g, $b) < 40) {
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $navyR, $navyG, $navyB, $a));
                }
            }
        }

        $saved = imagepng($image, $to);
        imagedestroy($image);

        return $saved;
    }

    public static function faviconUrl(): ?string
    {
        $favicon = static::get('site_favicon');

        if (! $favicon) {
            return null;
        }

        return asset('storage/' . $favicon);
    }

    public static function name(): string
    {
        return static::get('site_name', 'Cameroon Network');
    }

    public static function clearCache(): void
    {
        foreach (array_keys(static::$defaults) as $key) {
            Cache::forget("site_setting:{$key}");
        }
    }
}
