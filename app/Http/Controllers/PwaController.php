<?php

namespace App\Http\Controllers;

use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Progressive Web App endpoints: manifest, service worker and offline page.
 *
 * These are served as routes rather than static files in public/ for three reasons:
 *
 *  1. The app runs under a subdirectory in development
 *     (http://localhost/camerooncommunity/public) but at a domain root in production.
 *     `start_url`, `scope` and the service worker's base path differ between the two;
 *     a static file cannot know which, a route can. Nothing here hardcodes '/'.
 *  2. The app name/branding is runtime-configurable via SiteSettings.
 *  3. It keeps the whole feature out of Vite, so no `npm run build` is needed and
 *     public/build (which is committed) stays untouched.
 *
 * Note: no physical file may exist at public/sw.js or public/manifest.webmanifest,
 * or Apache would serve it and shadow these routes.
 */
class PwaController extends Controller
{
    /** Brand blue — mirrors --color-cm-bar in resources/css/app.css. */
    private const THEME_COLOR = '#015083';

    /** Matches the body background (bg-slate-50) so launch doesn't flash. */
    private const BACKGROUND_COLOR = '#f8fafc';

    /**
     * The app's base path, with a trailing slash.
     * '/camerooncommunity/public/' in development, '/' in production.
     */
    public static function base(): string
    {
        return rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/') . '/';
    }

    /**
     * Cache-busting version for the service worker.
     *
     * Derived from Vite's build manifest, so every asset rebuild renames the cache
     * and the activate handler drops the old one. Bump the literal prefix by hand
     * when changing service worker logic without rebuilding assets.
     */
    public static function version(): string
    {
        $manifest = public_path('build/manifest.json');

        return 'v1-' . (is_file($manifest) ? substr(md5_file($manifest), 0, 8) : 'dev');
    }

    /** The web app manifest. Public: it must be readable before login. */
    public function manifest(): JsonResponse
    {
        $base = self::base();
        $icon = fn (string $file) => asset('icons/' . $file);

        // route(absolute: false) drops the subdirectory prefix ('/yard', not
        // '/camerooncommunity/public/yard'), which would put shortcuts outside
        // `scope` in development. Re-apply the base path.
        $path = fn (string $name) => $base . ltrim(route($name, absolute: false), '/');

        $payload = [
            'id'               => $base,
            'name'             => SiteSettings::name(),
            'short_name'       => 'CM Network',
            'description'      => 'Connect with Cameroonians in your city and country. Chat, buy and sell, and find help — all in one place.',
            // The app root serves the landing page to guests and the feed to
            // signed-in users, so it is the one correct entry point for both.
            'start_url'        => $base . '?src=pwa',
            'scope'            => $base,
            'display'          => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            // Deliberately 'any', not portrait: the app makes WebRTC calls.
            'orientation'      => 'any',
            'background_color' => self::BACKGROUND_COLOR,
            'theme_color'      => self::THEME_COLOR,
            'lang'             => app()->getLocale(),
            'dir'              => 'ltr',
            'categories'       => ['social', 'shopping', 'lifestyle'],
            'icons' => [
                ['src' => $icon('icon-192.png'),          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon('icon-512.png'),          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon('icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => $icon('icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                [
                    'name'       => 'GoConnect',
                    'short_name' => 'GoConnect',
                    'url'        => $path('yard'),
                    'icons'      => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name'       => 'GoMarket',
                    'short_name' => 'GoMarket',
                    'url'        => $path('marketplace.index'),
                    'icons'      => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
                ],
            ],
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * The service worker script.
     *
     * Cache-Control: no-cache is not optional — a cached sw.js means updates never ship.
     */
    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.sw', [
                'base'    => self::base(),
                'version' => self::version(),
            ])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Service-Worker-Allowed', self::base());
    }

    /** Offline fallback, precached by the service worker at install. */
    public function offline(): Response
    {
        return response()->view('pwa.offline');
    }
}
