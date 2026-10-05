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
