<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class SiteSettings
{
    protected static array $defaults = [
        'site_name' => 'Cameroon Community',
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
        return static::get('site_name', 'Cameroon Community');
    }

    public static function clearCache(): void
    {
        foreach (array_keys(static::$defaults) as $key) {
            Cache::forget("site_setting:{$key}");
        }
    }
}
