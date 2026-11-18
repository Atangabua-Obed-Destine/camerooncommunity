<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creating an account. One behaviour, called by the web's register wizard and
 * by the app's POST /api/v1/auth/register, so a member made on a phone is the
 * same as a member made in a browser: the same handle, the same founding-member
 * rule, the same verification rule, the same location normalisation.
 */
class Registration
{
    public const UK_NAMES = ['United Kingdom', 'UK', 'Great Britain'];

    public function __construct(private LocationService $locations) {}

    /** The username rules, as the wizard shows them while typing. */
    public static function usernameRules(): array
    {
        return ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z0-9 _-]+$/'];
    }

    public static function emailRules(): array
    {
        return ['required', 'email', 'max:255', 'unique:users,email'];
    }

    /** @return array<string, mixed> */
    public static function rules(bool $isGoogle = false): array
    {
        return [
            'current_country' => 'required|string|max:100',
            'current_region' => 'nullable|string|max:100',
            'current_lat' => 'nullable|numeric|between:-90,90',
            'current_lng' => 'nullable|numeric|between:-180,180',
            'username' => self::usernameRules(),
            'email' => self::emailRules(),
            'password' => $isGoogle ? 'nullable' : PasswordPolicy::rules(),
            'phone' => 'nullable|string|max:20',
            'language_pref' => 'required|in:en,fr',
            'terms' => 'accepted',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'username.regex' => __('Username can only contain letters, numbers, spaces, hyphens and underscores.'),
            'password.regex' => PasswordPolicy::message(),
            'terms.accepted' => __('You must accept the Terms of Service and Privacy Policy.'),
        ];
    }

    /** How many founding members a tenant has: its setting, else 20. */
    public static function foundingCap(?Tenant $tenant): int
    {
        return (int) ($tenant?->settings['founding_member_cap'] ?? 20);
    }

    /** Founding seats still free. Zero once the cap is reached. */
    public static function foundingSeatsLeft(): int
    {
        $tenant = Tenant::first();
        if (! $tenant) {
            return 0;
        }

        return max(0, self::foundingCap($tenant) - User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public static function requiresVerification(): bool
    {
        return (string) PlatformSetting::getValue('require_email_verification', '0') === '1';
    }

    /**
     * Members already in a country, for "Your community is waiting for you!".
     * Deleted and suspended accounts are not people waiting for anyone.
     */
    public static function countryMemberCount(string $country): int
    {
        if ($country === '') {
            return 0;
        }

        return User::withoutGlobalScopes()
            ->where('current_country', $country)
            ->whereNull('deleted_at')
            ->whereNull('anonymised_at')
            ->where('is_active', true)
            ->count();
    }

    /**
     * The handle a typed name becomes: words joined, lowercased, then "-" and
     * five characters alternating letter and digit (a3k7b). The wizard shows
     * the first half live as "You'll be known as: calann-*****".
     */
    public static function handleBase(string $typed): string
    {
        return strtolower(preg_replace('/\s+/', '', trim($typed)));
    }

    private function uniqueHandle(string $typed): string
    {
        $base = self::handleBase($typed);

        do {
            $suffix = chr(random_int(97, 122)).random_int(0, 9).chr(random_int(97, 122)).random_int(0, 9).chr(random_int(97, 122));
            $handle = "{$base}-{$suffix}";
        } while (User::withoutGlobalScopes()->where('username', $handle)->exists());

        return $handle;
    }

    /**
     * Create the member. $data has already passed rules().
     *
     * @param  array{username: string, email: string, password?: ?string, google_id?: ?string,
     *     avatar?: ?string, phone?: ?string, language_pref: string, current_country: string,
     *     current_region?: ?string, current_lat?: ?float, current_lng?: ?float,
     *     country_of_origin?: ?string, home_region?: ?string, home_city?: ?string}  $data
     */
    public function register(array $data): User
    {
        $tenant = Tenant::first();
        $isGoogle = ! empty($data['google_id']);
        $country = $data['current_country'];
        $lat = isset($data['current_lat']) ? (float) $data['current_lat'] : null;
        $lng = isset($data['current_lng']) ? (float) $data['current_lng'] : null;

        // UK regions are saved as ITL1 ("London", not "England"), resolved from
        // the coordinates when the name alone can't say, so regional rooms are
        // right on the first visit.
        // Done here whatever the client already did, so a region typed by hand
        // ("Manchester") is saved as the region it belongs to ("North West").
        $region = $this->locations->signupRegion($country, (string) ($data['current_region'] ?? ''), '', $lat, $lng) ?: null;

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'uuid' => Str::uuid(),
            'name' => trim($data['username']),
            'username' => $this->uniqueHandle($data['username']),
            'email' => $data['email'],
            'password' => $isGoogle || empty($data['password']) ? null : Hash::make($data['password']),
            'google_id' => $isGoogle ? $data['google_id'] : null,
            'avatar' => $isGoogle && ! empty($data['avatar']) ? $data['avatar'] : null,
            'phone' => ($data['phone'] ?? null) ?: null,
            'country_of_origin' => ($data['country_of_origin'] ?? null) ?: 'Cameroon',
            'home_region' => ($data['home_region'] ?? null) ?: null,
            'home_city' => ($data['home_city'] ?? null) ?: null,
            'language_pref' => $data['language_pref'],
            'current_country' => $country,
            'current_region' => $region,
            'current_lat' => $lat,
            'current_lng' => $lng,
            // The signup location is the active one from the start, so national
            // and regional rooms are offered at once rather than after the
            // IP/GPS detection cycle has run.
            'active_country' => $country,
            'active_region' => $region,
            'location_updated_at' => now(),
            'is_active' => true,
        ]);

        if (User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count() <= self::foundingCap($tenant)) {
            $user->update(['is_founding_member' => true]);
        }

        // Google has already verified the address; with verification off,
        // nobody is asked to.
        $requireVerification = self::requiresVerification();
        if ($isGoogle || ! $requireVerification) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        try {
            if ($requireVerification && ! $isGoogle) {
                event(new Registered($user));
            }
        } catch (\Throwable $e) {
            logger()->warning('Registration event failed: '.$e->getMessage());
        }

        return $user->refresh();
    }

    /** Kamer's first message to a new member, or null when the AI is unavailable. */
    public function welcomeMessage(User $user): ?string
    {
        try {
            return app(AIService::class)->welcomeMessage(
                $user->name,
                $user->current_country ?? 'abroad',
                $user->language_pref?->value ?? 'en',
            ) ?: null;
        } catch (\Throwable $e) {
            logger()->warning('Kamer welcome message failed: '.$e->getMessage());

            return null;
        }
    }
}
