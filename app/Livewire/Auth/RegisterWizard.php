<?php

namespace App\Livewire\Auth;

use App\Enums\Language;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\YardRoom;
use App\Services\LocationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class RegisterWizard extends Component
{
    /*
    |----------------------------------------------------------------------
    | Steps:
    |  1 — "We See You" — Location detection (the hook)
    |  2 — "Your Identity" — Account creation
    |  3 — "Your Roots" — Origin, region, language
    |  4 — "Welcome Home" — Review, terms, register
    |----------------------------------------------------------------------
    */
    #[Url(as: 'step', except: 1, history: true)]
    public int $step = 1;
    public int $totalSteps = 3;

    // Step 1 — Location
    public string $current_country = '';
    public string $current_region = '';
    public ?float $current_lat = null;
    public ?float $current_lng = null;
    public bool $gps_detected = false;

    // Step 2 — Account Details
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $phone = '';

    // Step 3 — Origin & Language
    public string $country_of_origin = 'Cameroon';
    public string $home_region = '';
    public string $home_city = '';
    public string $language_pref = 'en';

    // Step 4 — Terms
    public bool $terms = false;

    // ── Google Sign-up integration ───────────────────────────────────
    // Populated from session('google_signup') in mount() when the user
    // arrives via the Google OAuth callback. When set, the password step
    // is skipped and the resulting User row stores google_id instead.
    public bool $isGoogle = false;
    public string $googleId = '';
    public string $googleAvatar = '';

    public function mount(): void
    {
        $google = session('google_signup');
        if (is_array($google) && !empty($google['google_id']) && !empty($google['email'])) {
            $this->isGoogle      = true;
            $this->googleId      = (string) $google['google_id'];
            $this->email         = (string) $google['email'];
            $this->username      = trim((string) ($google['name'] ?? ''));
            $this->googleAvatar  = (string) ($google['avatar'] ?? '');
            $this->language_pref = in_array(($google['locale'] ?? 'en'), ['en', 'fr'], true)
                ? $google['locale']
                : 'en';
        }
    }

    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'current_country' => 'required|string|max:100',
                'current_region' => 'nullable|string|max:100',
            ],
            2 => [
                'username' => 'required|string|min:2|max:50|regex:/^[a-zA-Z0-9 _-]+$/',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => $this->isGoogle ? 'nullable' : 'required|string|min:8|confirmed|regex:/[A-Z]/|regex:/[0-9]/',
                'phone' => 'nullable|string|max:20',
            ],
            3 => [
                'terms' => 'accepted',
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'username.regex' => __('Username can only contain letters, numbers, spaces, hyphens and underscores.'),
            'password.regex' => __('Password must contain at least one uppercase letter and one number.'),
            'terms.accepted' => __('You must accept the Terms of Service and Privacy Policy.'),
        ];
    }

    public function updated(string $property): void
    {
        // Clamp the URL-bound step to a valid range so a manual ?step=99
        // can't break the wizard rendering.
        if ($property === 'step') {
            $this->step = max(1, min($this->step, $this->totalSteps));
            return;
        }

        $this->validateOnly($property);
    }

    public function nextStep(): void
    {
        $this->validate();
        $this->step = min($this->step + 1, $this->totalSteps);
    }

    /**
     * Jump backward to an earlier step via the progress indicator.
     * Only allows going to a step the user has already completed —
     * forward navigation must still go through nextStep() so validation runs.
     */
    public function goToStep(int $target): void
    {
        $target = max(1, min($target, $this->totalSteps));
        if ($target < $this->step) {
            $this->step = $target;
        }
    }

    public function previousStep(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function setLocation(float $lat, float $lng, string $country, string $region = '', string $city = ''): void
    {
        $this->current_lat = $lat;
        $this->current_lng = $lng;
        $this->current_country = $country;

        $region = trim($region);
        $city = trim($city);

        // Normalize UK regions to ITL1 (e.g. "England" / "Greater London"
        // → "London") so the user sees the same value that will be saved.
        if (in_array($country, ['United Kingdom', 'UK', 'Great Britain'], true)) {
            $valid = config('cameroon.seeded_regions.GB', []);
            $cityMap = config('cameroon.gb_city_to_region', []);
            $aliasMap = config('cameroon.gb_region_aliases', []);

            // Prefer city lookup — geo providers return broad regions like
            // "England" for any English city, so the city is more accurate.
            if ($city && isset($cityMap[Str::lower($city)])) {
                $region = $cityMap[Str::lower($city)];
            } elseif ($region && ! in_array($region, $valid, true)) {
                $key = Str::lower($region);
                $region = $cityMap[$key] ?? ($aliasMap[$key] ?? $region);
            }
        }

        $this->current_region = $region;
        $this->gps_detected = true;
    }

    /*
    |----------------------------------------------------------------------
    | Community stats for the user's detected country (shown on step 1)
    |----------------------------------------------------------------------
    */
    #[Computed]
    public function communityStats(): array
    {
        $total = User::withoutGlobalScopes()->count();
        $countryUsers = 0;
        $countryRooms = 0;

        if ($this->current_country) {
            $countryUsers = User::withoutGlobalScopes()
                ->where('current_country', $this->current_country)
                ->count();
            $countryRooms = YardRoom::where('country', $this->current_country)
                ->where('is_active', true)
                ->count();
        }

        return [
            'total' => $total,
            'country_users' => $countryUsers,
            'country_rooms' => $countryRooms,
        ];
    }

    /*
    |----------------------------------------------------------------------
    | Kamer AI contextual message — changes per step and user input
    |----------------------------------------------------------------------
    */
    public function getAiMessage(): string
    {
        return match ($this->step) {
            1 => $this->aiLocationMessage(),
            2 => $this->aiAccountMessage(),
            3 => $this->aiSummaryMessage(),
            default => '',
        };
    }

    private function aiLocationMessage(): string
    {
        $fr = $this->language_pref === 'fr';

        if (! $this->gps_detected && ! $this->current_country) {
            return $fr
                ? "Salut ! 👋 Laissez-moi vous localiser..."
                : "Hey there! 👋 Let me find where you are...";
        }

        $loc = $this->current_region
            ? "{$this->current_region}, {$this->current_country}"
            : $this->current_country;
        $stats = $this->communityStats;

        if ($stats['country_users'] > 0) {
            return $fr
                ? "Trouvé ! 📍 Vous êtes à {$loc}. Votre communauté vous attend ! 🇨🇲"
                : "Found you! 📍 You're in {$loc}. Your community is waiting for you! 🇨🇲";
        }

        return $fr
            ? "Trouvé ! 📍 {$loc}. Vous serez parmi les premiers — un vrai pionnier ! 🚀"
            : "Found you! 📍 {$loc}. You'll be among the first here — a true pioneer! 🚀";
    }

    private function aiAccountMessage(): string
    {
        $fr = $this->language_pref === 'fr';

        if ($this->username) {
            return $fr
                ? "Enchanté, {$this->username} ! 🙌 Encore quelques détails et c'est bon."
                : "Nice to meet you, {$this->username}! 🙌 Just a few more details.";
        }

        return $fr
            ? "Créons votre espace ! C'est rapide, promis. 🚀"
            : "Let's set up your space! This is quick, I promise. 🚀";
    }

    private function aiOriginMessage(): string
    {
        $fr = $this->language_pref === 'fr';

        $regionFacts = [
            'Northwest' => ['en' => "Northwest! Where the hills whisper and fufu is the law 🌄", 'fr' => "Nord-Ouest ! Les collines murmurent et le fufu est roi 🌄"],
            'Southwest' => ['en' => "Southwest! Beach, bush and the best jollof debates 🏖️", 'fr' => "Sud-Ouest ! Plage, brousse et débats sur le jollof 🏖️"],
            'Centre'    => ['en' => "Centre! Yaoundé vibes — the heartbeat of Cameroon 💚", 'fr' => "Le Centre ! Ambiance Yaoundé — le cœur du Cameroun 💚"],
            'Littoral'  => ['en' => "Littoral! Douala hustle and the sweet smell of soya 🔥", 'fr' => "Le Littoral ! L'effervescence de Douala et l'odeur du soya 🔥"],
            'West'      => ['en' => "West! The Bamiléké spirit — business in the blood 💪", 'fr' => "L'Ouest ! L'esprit Bamiléké — le business dans le sang 💪"],
            'East'      => ['en' => "East! Land of forests and untold beauty 🌿", 'fr' => "L'Est ! Terre de forêts et de beauté cachée 🌿"],
            'Adamawa'   => ['en' => "Adamawa! Where savanna meets pure serenity 🌅", 'fr' => "L'Adamaoua ! La savane rencontre la sérénité 🌅"],
            'North'     => ['en' => "The North! Warmth of people that matches the sun ☀️", 'fr' => "Le Nord ! La chaleur des gens rivalise avec le soleil ☀️"],
            'Far North' => ['en' => "Far North! Maroua colours and resilient spirit 🎨", 'fr' => "Extrême-Nord ! Couleurs de Maroua et esprit résilient 🎨"],
            'South'     => ['en' => "The South! Kribi beaches and ocean soul 🌊", 'fr' => "Le Sud ! Plages de Kribi et âme océanique 🌊"],
        ];

        if ($this->home_region && isset($regionFacts[$this->home_region])) {
            return $regionFacts[$this->home_region][$fr ? 'fr' : 'en'];
        }

        return $fr
            ? "Dites-moi d'où vous venez au Cameroun — je suis curieux ! 🇨🇲"
            : "Tell me where you're from in Cameroon — I'm curious! 🇨🇲";
    }

    private function aiSummaryMessage(): string
    {
        $fr = $this->language_pref === 'fr';

        if ($this->username) {
            return $fr
                ? "Tout est prêt, {$this->username} ! 🎊 Un clic et vous êtes de la famille. Bienvenue chez vous !"
                : "Everything's set, {$this->username}! 🎊 One click and you're family. Welcome home!";
        }

        return $fr
            ? "Tout est prêt ! 🎊 Un clic et c'est parti."
            : "All set! 🎊 One click and let's go.";
    }

    /*
    |----------------------------------------------------------------------
    | Registration
    |----------------------------------------------------------------------
    */
    public function register(): void
    {
        // Validate step 4 (terms) plus critical fields from earlier steps
        $this->validate([
            'terms' => 'accepted',
            'current_country' => 'required|string|max:100',
            'username' => 'required|string|min:2|max:50|regex:/^[a-zA-Z0-9 _-]+$/',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => $this->isGoogle ? 'nullable' : 'required|string|min:8|confirmed|regex:/[A-Z]/|regex:/[0-9]/',
            'country_of_origin' => 'required|string|max:100',
            'language_pref' => 'required|in:en,fr',
        ]);

        // Generate unique username: join words, lowercase, append a hyphen
        // and 5 random alphanumeric chars (guaranteed mix of letters/digits)
        $baseUsername = strtolower(preg_replace('/\s+/', '', trim($this->username)));
        $suffix = chr(rand(97, 122)) . rand(0, 9) . chr(rand(97, 122)) . rand(0, 9) . chr(rand(97, 122)); // e.g. a3k7b
        $finalUsername = $baseUsername . '-' . $suffix;

        // Ensure uniqueness (extremely unlikely collision but be safe)
        while (User::withoutGlobalScopes()->where('username', $finalUsername)->exists()) {
            $suffix = chr(rand(97, 122)) . rand(0, 9) . chr(rand(97, 122)) . rand(0, 9) . chr(rand(97, 122));
            $finalUsername = $baseUsername . '-' . $suffix;
        }

        $tenant = Tenant::first();

        // Normalize the location BEFORE creating the user so that UK regions
        // like "England" are mapped to a valid ITL1 region (London, Scotland,
        // ...) using city name + coordinates. This ensures regional rooms are
        // suggested correctly on first yard visit.
        $normalizedRegion = $this->current_region ?: null;
        if (in_array($this->current_country, ['United Kingdom', 'UK', 'Great Britain'], true)) {
            try {
                $svc = app(LocationService::class);
                $tmpUser = new User([
                    'tenant_id' => $tenant->id,
                    'current_country' => $this->current_country,
                    'current_region' => $this->current_region ?: null,
                ]);
                $tmpUser->saveQuietly();
                $svc->handleUserLocation(
                    $tmpUser,
                    $this->current_country,
                    '',
                    $this->current_region ?: null,
                    $this->current_lat,
                    $this->current_lng,
                );
                $tmpUser->refresh();
                $normalizedRegion = $tmpUser->current_region;
                $tmpUser->delete();
            } catch (\Throwable $e) {
                logger()->warning('Register UK normalization failed: ' . $e->getMessage());
            }
        }

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'uuid' => Str::uuid(),
            'name' => trim($this->username),
            'username' => $finalUsername,
            'email' => $this->email,
            'password' => $this->isGoogle ? null : Hash::make($this->password),
            'google_id' => $this->isGoogle ? ($this->googleId ?: null) : null,
            'avatar' => $this->isGoogle && $this->googleAvatar ? $this->googleAvatar : null,
            'phone' => $this->phone ?: null,
            'country_of_origin' => $this->country_of_origin,
            'home_region' => $this->home_region ?: null,
            'home_city' => $this->home_city ?: null,
            'language_pref' => $this->language_pref,
            'current_country' => $this->current_country,
            'current_region' => $normalizedRegion,
            'current_lat' => $this->current_lat,
            'current_lng' => $this->current_lng,
            // Treat the location chosen during signup as the user's active
            // location. This ensures National/Regional room suggestions are
            // available immediately on first yard visit, without waiting for
            // the IP/GPS auto-detection cycle to bootstrap them.
            'active_country' => $this->current_country,
            'active_region' => $normalizedRegion,
            'location_updated_at' => now(),
            'is_active' => true,
        ]);

        // Check founding member status
        $foundingCap = (int) ($tenant->settings['founding_member_cap'] ?? 20);
        $totalUsers = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        if ($totalUsers <= $foundingCap) {
            $user->update(['is_founding_member' => true]);
        }

        // If admin has disabled email verification, auto-mark the user as verified
        // so the `verified` middleware on protected routes does not block them.
        // Google sign-ups always count as verified because Google has already
        // confirmed the email address on our behalf.
        $requireVerification = (string) PlatformSetting::getValue('require_email_verification', '0') === '1';
        if ($this->isGoogle || ! $requireVerification) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // Clear the Google sign-up stash so a refresh of /register doesn't
        // re-prefill another visitor's session with stale data.
        if ($this->isGoogle) {
            session()->forget('google_signup');
        }

        Auth::login($user);

        // Seed Kamer chat with a personalised AI welcome message
        try {
            $welcome = app(\App\Services\AIService::class)->welcomeMessage(
                $user->name,
                $this->current_country ?? 'abroad',
                $this->language_pref ?? 'en',
            );
            if ($welcome) {
                session(['kamer_chat_history' => [
                    ['role' => 'assistant', 'content' => $welcome],
                ]]);
            }
        } catch (\Throwable $e) {
            logger()->warning('Kamer welcome message failed: ' . $e->getMessage());
        }

        try {
            if ($requireVerification && ! $this->isGoogle) {
                event(new Registered($user));
            }
        } catch (\Throwable $e) {
            logger()->warning('Registration event failed: ' . $e->getMessage());
        }

        $this->redirect(route('onboarding'), navigate: true);
    }

    public function render()
    {
        $regions = config('cameroon.regions', []);
        $countries = config('cameroon.seeded_countries', []);

        return view('livewire.auth.register-wizard', [
            'regions' => $regions,
            'countries' => $countries,
            'aiMessage' => $this->getAiMessage(),
        ])->layout('components.layouts.guest');
    }
}
