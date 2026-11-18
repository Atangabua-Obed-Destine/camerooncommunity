<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Payments\PaymentDriverManager;
use App\Services\Registration;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the app needs to know before it draws anything.
 *
 * Fetched at splash, before sign-in, and it is the one way the admin panel
 * reaches the app: we are not building an admin screen into the client, so a
 * toggle flipped at /admin/settings has to arrive here or not at all. The
 * E-ride app works the same way, and its hard-won lesson applies to us too —
 * when a feature "isn't showing up", read this payload before suspecting the
 * widget.
 *
 * Deliberately unauthenticated. The app must be able to learn that it is
 * below the minimum version, or that the platform is in maintenance, while
 * nobody is signed in.
 */
class ConfigurationController extends Controller
{
    public function __invoke(Request $request, PaymentDriverManager $payments): JsonResponse
    {
        return response()->json([
            'app' => [
                'name' => SiteSettings::name(),
                'logo_url' => SiteSettings::logoUrl(),
                // The same logo with "Cameroon" in navy, for white pages
                // (forgot, reset, verify), where the white wordmark vanishes.
                'logo_on_light_url' => SiteSettings::logoOnLightUrl(),

                // The client compares its own build against this and offers an
                // update rather than failing against an API it cannot speak to.
                'minimum_version' => $this->setting('minimum_app_version'),
                'maintenance_mode' => $this->boolSetting('maintenance_mode'),
            ],

            'features' => [
                // Same switch the web reads, so calls stay off in both places
                // until they actually connect, and come back without a release.
                'calls_enabled' => SiteSettings::callsEnabled(),
                'marketplace_enabled' => $this->boolSetting('marketplace_enabled'),
                'marketplace_offers_enabled' => $this->boolSetting('marketplace_offers_enabled'),
                'marketplace_ratings_enabled' => $this->boolSetting('marketplace_ratings_enabled'),
                'ai_enabled' => $this->boolSetting('openai_enabled'),
            ],

            'modules' => [
                'live' => collect(config('cameroon.modules.live', []))
                    ->map(fn (array $m, string $slug) => [
                        'slug' => $slug,
                        'name' => $m['name'],
                    ])
                    ->values(),

                // The app greys these out and shows the "coming soon" sheet,
                // matching the placeholder pages the web serves.
                'coming_soon' => collect(config('cameroon.modules.coming_soon', []))
                    ->map(fn (string $name, string $slug) => [
                        'slug' => $slug,
                        'name' => $name,
                    ])
                    ->values(),
            ],

            // Limits the composer must enforce before upload, not after: a
            // rejected 8MB photo on a mobile connection is a wasted minute.
            'marketplace' => [
                'currency' => $this->setting('marketplace_default_currency'),
                'max_images' => (int) $this->setting('marketplace_max_images'),
                'min_title_length' => (int) $this->setting('marketplace_min_title_length'),
                'min_description_length' => (int) $this->setting('marketplace_min_description_length'),
                'require_phone' => $this->boolSetting('marketplace_require_phone'),
                'allow_free' => $this->boolSetting('marketplace_allow_free'),
                'listing_expiry_days' => (int) $this->setting('marketplace_listing_expiry_days'),
            ],

            'payment_methods' => collect($payments->all())
                ->map(fn ($driver) => [
                    'id' => $driver->id(),
                    'label' => $driver->label(app()->getLocale()),
                    'icon' => $driver->icon(),
                ])
                ->values(),

            'auth' => [
                // Google: on once the site has its OAuth client. The phone asks
                // Google for an ID token addressed to that same web client
                // (its serverClientId), which is what /auth/google accepts.
                // The Android client registered beside it in Google Cloud is
                // what lets this app ask; it needs no secret here.
                'google' => filled(config('services.google.client_id')),
                'google_client_id' => config('services.google.client_id') ?: null,
                // Apple needs an Apple Services id, and has to ship with
                // Google on iOS (App Store rule 4.8): off until then.
                'apple' => false,
                'require_email_verification' => Registration::requiresVerification(),
                // Register step 3's "You could be a Founding Member" shows only
                // while this is above zero.
                'founding_seats_left' => Registration::foundingSeatsLeft(),
            ],

            // Register step 1's country list, as the web's select offers it.
            'countries' => array_values(config('cameroon.seeded_countries', [])),

            // The static pages, rendered for the in-app browser: the page alone
            // (?app=1), in the member's language, which the app appends.
            'links' => [
                'terms' => route('legal.terms', ['app' => 1]),
                'privacy' => route('legal.privacy', ['app' => 1]),
                'about' => route('about', ['app' => 1]),
                'contact' => route('contact', ['app' => 1]),
            ],

            'languages' => collect(\App\Enums\Language::cases())
                ->map(fn ($lang) => $lang->value)
                ->values(),

            'realtime' => [
                // The app builds its Reverb connection from these rather than
                // compiling them in, so moving the socket server does not need
                // a new build in the store.
                'key' => config('broadcasting.connections.reverb.key'),
                ...$this->socketEndpoint($request),
            ],
        ]);
    }

    /**
     * Where a phone opens the socket, worked out as the web's Echo does it.
     *
     * The broadcasting config is where *this server* sends events: Reverb on
     * the same machine, 127.0.0.1:8080 over plain http in production. A phone
     * cannot use that. Behind TLS the site's nginx proxies /app to Reverb on
     * the standard port, so the socket lives where this request arrived:
     * same host, 443, wss. Without TLS (a development machine) the configured
     * values are the reachable ones, and the app maps "127.0.0.1" onto the
     * host it reached the API at.
     *
     * @return array{host: string, port: int, scheme: string}
     */
    private function socketEndpoint(Request $request): array
    {
        if ($request->isSecure()) {
            return ['host' => $request->getHost(), 'port' => 443, 'scheme' => 'https'];
        }

        return [
            'host' => (string) config('broadcasting.connections.reverb.options.host'),
            'port' => (int) config('broadcasting.connections.reverb.options.port'),
            'scheme' => (string) config('broadcasting.connections.reverb.options.scheme'),
        ];
    }

    /**
     * A platform setting, falling back to the shipped default.
     *
     * `PlatformSetting::getValue()` returns null for a key an admin has never
     * touched, and most of these have never been written, so the defaults in
     * config/cameroon.php are what actually answer here.
     */
    private function setting(string $key): mixed
    {
        return PlatformSetting::getValue(
            $key,
            config("cameroon.default_settings.{$key}")
        );
    }

    private function boolSetting(string $key): bool
    {
        return filter_var($this->setting($key), FILTER_VALIDATE_BOOLEAN);
    }
}
