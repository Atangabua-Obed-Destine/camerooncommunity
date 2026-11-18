<?php

namespace App\Livewire\Auth;

use App\Enums\Language;
use App\Models\Tenant;
use App\Models\User;
use App\Models\YardRoom;
use App\Services\LocationService;
use App\Services\Registration;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Auth;
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
        // Restore an in-progress location that the visitor completed before
        // bouncing out to Google (the redirectToGoogle() method stashes it).
        // This avoids forcing the user to re-do the GPS step on their return.
        $pending = session('register_pending_location');
        if (is_array($pending) && !empty($pending['current_country'])) {
            $this->current_country = (string) $pending['current_country'];
            $this->current_region  = (string) ($pending['current_region'] ?? '');
            $this->current_lat     = isset($pending['current_lat']) ? (float) $pending['current_lat'] : null;
            $this->current_lng     = isset($pending['current_lng']) ? (float) $pending['current_lng'] : null;
            $this->gps_detected    = (bool) ($pending['gps_detected'] ?? false);
            // Consume the stash so a later visit doesn't restore stale data.
            session()->forget('register_pending_location');
            // Jump straight to the account step — they already finished location.
            if ($this->gps_detected) {
                $this->step = max($this->step, 2);
            }
        }

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

        // ?step= is in the address, so a link or a reload can land on a step whose
        // earlier fields were never filled. Those fields' errors only show on their
        // own step, so step 3 would fail with nothing on screen. Go back to the
        // first step that isn't complete.
        $this->step = max(1, min($this->step, $this->totalSteps));
        for ($earlier = 1; $earlier < $this->step; $earlier++) {
            if (! $this->stepIsComplete($earlier)) {
                $this->step = $earlier;
                break;
            }
        }
    }

    private function stepIsComplete(int $step): bool
    {
        $current = $this->step;
        $this->step = $step;
        $rules = $this->rules();
        $this->step = $current;

        return validator($this->all(), $rules)->passes();
    }

    /** How many founding members a tenant has: its setting, else 20. */
    public static function foundingCap(?Tenant $tenant): int
    {
        return Registration::foundingCap($tenant);
    }

    /**
     * Founding seats still free. The review step promised "You could be a
     * Founding Member" to everyone, long after the seats were gone.
     */
    public function foundingSeatsLeft(): int
    {
        return Registration::foundingSeatsLeft();
    }

    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'current_country' => 'required|string|max:100',
                'current_region' => 'nullable|string|max:100',
            ],
            2 => [
                'username' => Registration::usernameRules(),
                'email' => Registration::emailRules(),
                'password' => $this->isGoogle ? 'nullable' : PasswordPolicy::rules(),
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
        return Registration::messages();
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

    /**
     * Stash the already-detected location in the session and bounce the visitor
     * to the Google OAuth flow. On return, mount() restores the location and
     * skips them straight to step 2 so they never re-do the GPS step.
     */
    public function redirectToGoogle()
    {
        if ($this->gps_detected && $this->current_country) {
            session([
                'register_pending_location' => [
                    'current_country' => $this->current_country,
                    'current_region'  => $this->current_region,
                    'current_lat'     => $this->current_lat,
                    'current_lng'     => $this->current_lng,
                    'gps_detected'    => true,
                ],
            ]);
        }
        return $this->redirect(route('auth.google'), navigate: false);
    }

    public function setLocation(float $lat, float $lng, string $country, string $region = '', string $city = ''): void
    {
        $this->current_lat = $lat;
        $this->current_lng = $lng;
        $this->current_country = $country;

        // Normalised exactly as registration will save it, so the locked
        // field shows what will be saved (the app asks the same method).
        $region = app(LocationService::class)->signupRegion($country, $region, $city, $lat, $lng);

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
            $countryUsers = Registration::countryMemberCount($this->current_country);
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
            ? "Trouvé ! 📍 {$loc}. Vous serez parmi les premiers, un vrai pionnier ! 🚀"
            : "Found you! 📍 {$loc}. You'll be among the first here, a true pioneer! 🚀";
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
            'Centre'    => ['en' => "Centre! Yaoundé vibes, the heartbeat of Cameroon 💚", 'fr' => "Le Centre ! Ambiance Yaoundé, le cœur du Cameroun 💚"],
            'Littoral'  => ['en' => "Littoral! Douala hustle and the sweet smell of soya 🔥", 'fr' => "Le Littoral ! L'effervescence de Douala et l'odeur du soya 🔥"],
            'West'      => ['en' => "West! The Bamiléké spirit, business in the blood 💪", 'fr' => "L'Ouest ! L'esprit Bamiléké, le business dans le sang 💪"],
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
            ? "Dites-moi d'où vous venez au Cameroun, je suis curieux ! 🇨🇲"
            : "Tell me where you're from in Cameroon, I'm curious! 🇨🇲";
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
            'password' => $this->isGoogle ? 'nullable' : PasswordPolicy::rules(),
            'country_of_origin' => 'required|string|max:100',
            'language_pref' => 'required|in:en,fr',
        ]);

        $registration = app(Registration::class);
        $user = $registration->register([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->isGoogle ? null : $this->password,
            'google_id' => $this->isGoogle ? ($this->googleId ?: null) : null,
            'avatar' => $this->isGoogle ? ($this->googleAvatar ?: null) : null,
            'phone' => $this->phone,
            'country_of_origin' => $this->country_of_origin,
            'home_region' => $this->home_region,
            'home_city' => $this->home_city,
            'language_pref' => $this->language_pref,
            'current_country' => $this->current_country,
            'current_region' => $this->current_region,
            'current_lat' => $this->current_lat,
            'current_lng' => $this->current_lng,
        ]);

        // Clear the Google sign-up stash so a refresh of /register doesn't
        // re-prefill another visitor's session with stale data.
        if ($this->isGoogle) {
            session()->forget('google_signup');
        }

        Auth::login($user, remember: true);

        // Kamer's first message, shown on /welcome.
        if ($welcome = $registration->welcomeMessage($user)) {
            session(['kamer_chat_history' => [['role' => 'assistant', 'content' => $welcome]]]);
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
