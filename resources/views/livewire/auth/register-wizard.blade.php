{{--
    ═══════════════════════════════════════════════════════════════════
    Cameroon Community — Interactive Registration Wizard (Split-Screen)
    Left:  Cameroon-themed branding panel with features & stats
    Right: 4-step wizard: Location → Account → Roots → Welcome Home
    ═══════════════════════════════════════════════════════════════════
--}}
<div class="min-h-screen flex flex-col lg:flex-row"
     x-data="{
        detecting: false,
        detected: @entangle('gps_detected'),
        locationMode: @js(\App\Models\PlatformSetting::getValue('location_detection_mode', 'gps')),

        {{-- ── GPS / IP detection ── --}}
        async detectLocation() {
            this.detecting = true;
            if (this.locationMode === 'ip') { await this.detectByIP(); return; }
            if (!navigator.geolocation) { await this.detectByIP(); return; }
            try {
                const pos = await new Promise((resolve, reject) =>
                    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 10000 })
                );
                const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&format=json&accept-language=en`);
                const data = await resp.json();
                const country = data.address?.country || '';
                const region = data.address?.state || data.address?.region || '';
                $wire.setLocation(pos.coords.latitude, pos.coords.longitude, country, region);
            } catch { await this.detectByIP(); }
            this.detecting = false;
        },
        async detectByIP() {
            try {
                const resp = await fetch('http://ip-api.com/json/?fields=status,country,city,regionName,lat,lon');
                const data = await resp.json();
                if (data.status === 'success') $wire.setLocation(data.lat, data.lon, data.country, data.regionName || '');
            } catch {}
            this.detecting = false;
        }
     }"
     x-init="$nextTick(() => detectLocation())"
>
    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- LEFT PANEL — Branding, Features & Stats                      --}}
    {{-- Hidden on mobile, shown lg+                                   --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    <div class="hidden lg:flex lg:w-[42%] xl:w-[38%] relative overflow-hidden flex-col justify-between
                bg-gradient-to-br from-cm-green-deeper via-cm-green to-cm-green-deep">

        {{-- Decorative Elements --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-white/[.04] blur-3xl"></div>
            <div class="absolute -bottom-32 -left-20 w-96 h-96 rounded-full bg-cm-yellow/[.06] blur-3xl"></div>
            <div class="absolute top-1/3 right-0 w-64 h-64 rounded-full bg-cm-red/[.04] blur-3xl"></div>
            {{-- Subtle grid pattern --}}
            <div class="absolute inset-0 opacity-[.03]" style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 32px 32px;"></div>

            {{-- Concentric circles (from hero section) --}}
            <svg class="absolute -top-20 -left-20 w-96 h-96 animate-pulse-soft opacity-20" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="80" fill="none" stroke="#FCD116" stroke-width="1.5"/>
                <circle cx="100" cy="100" r="60" fill="none" stroke="#FCD116" stroke-width="1"/>
                <circle cx="100" cy="100" r="40" fill="none" stroke="#FCD116" stroke-width="0.5"/>
            </svg>
            <svg class="absolute -bottom-16 -right-16 w-80 h-80 animate-pulse-soft opacity-15" viewBox="0 0 200 200" style="animation-delay: 1s">
                <circle cx="100" cy="100" r="80" fill="none" stroke="#FCD116" stroke-width="1.5"/>
                <circle cx="100" cy="100" r="60" fill="none" stroke="#FCD116" stroke-width="1"/>
                <circle cx="100" cy="100" r="40" fill="none" stroke="#FCD116" stroke-width="0.5"/>
            </svg>
        </div>

        <div class="relative z-10 flex flex-col justify-between h-full p-8 xl:p-10">
            {{-- Logo --}}
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center group">
                    @if($__siteLogo ?? null)
                        <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Cameroon Community' }}" class="h-[120px] object-contain transition-transform group-hover:scale-110">
                    @else
                        <span class="text-3xl transition-transform group-hover:scale-110">🇨🇲</span>
                    @endif
                </a>
            </div>

            {{-- Hero Section --}}
            <div class="mt-10 mb-8">
                <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight">
                    <span x-text="$store.lang.t('Join the Family', 'Rejoignez la Famille')"></span> 🎉
                </h1>
                <p class="mt-3 text-sm text-white/70 leading-relaxed max-w-xs"
                   x-text="$store.lang.t(
                       'Connect with Cameroonians wherever you are. Your community is waiting.',
                       'Connectez-vous avec les Camerounais où que vous soyez. Votre communauté vous attend.'
                   )"></p>
            </div>

            {{-- Feature Cards --}}
            <div class="space-y-3 mb-8">
                <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-cm-yellow/20 flex items-center justify-center">
                        <span class="text-lg">💬</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white" x-text="$store.lang.t('The Yard', 'Le Yard')"></p>
                        <p class="text-xs text-white/60" x-text="$store.lang.t('Live chat rooms by city & country', 'Salons de discussion par ville et pays')"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-cm-red/20 flex items-center justify-center">
                        <span class="text-lg">🤝</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white" x-text="$store.lang.t('Solidarity', 'Solidarité')"></p>
                        <p class="text-xs text-white/60" x-text="$store.lang.t('Crowdfund & support each other', 'Financez et soutenez-vous mutuellement')"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-400/20 flex items-center justify-center">
                        <span class="text-lg">🤖</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">Kamer AI</p>
                        <p class="text-xs text-white/60" x-text="$store.lang.t('Your personal Cameroon guide', 'Votre guide personnel du Cameroun')"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-400/20 flex items-center justify-center">
                        <span class="text-lg">🌍</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white" x-text="$store.lang.t('Global Network', 'Réseau Mondial')"></p>
                        <p class="text-xs text-white/60" x-text="$store.lang.t('Cameroonians in 20+ countries', 'Camerounais dans 20+ pays')"></p>
                    </div>
                </div>
            </div>

            {{-- Stats Footer --}}
            <div class="flex items-center gap-6 pt-6 border-t border-white/10">
                <div>
                    <p class="text-2xl font-extrabold text-white">{{ number_format(\App\Models\User::withoutGlobalScopes()->count()) }}+</p>
                    <p class="text-[11px] text-white/50 font-medium" x-text="$store.lang.t('Members', 'Membres')"></p>
                </div>
                <div class="w-px h-8 bg-white/10"></div>
                <div>
                    <p class="text-2xl font-extrabold text-white">{{ \App\Models\YardRoom::where('is_active', true)->distinct('country')->count('country') }}+</p>
                    <p class="text-[11px] text-white/50 font-medium" x-text="$store.lang.t('Countries', 'Pays')"></p>
                </div>
                <div class="w-px h-8 bg-white/10"></div>
                <div>
                    <p class="text-2xl font-extrabold text-white">24/7</p>
                    <p class="text-[11px] text-white/50 font-medium" x-text="$store.lang.t('Active', 'Actif')"></p>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- RIGHT PANEL — Registration Wizard Form                       --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    <div class="flex-1 min-h-screen bg-slate-50/50 flex items-start lg:items-center justify-center overflow-y-auto relative">
        {{-- Cameroon Flag Ribbon --}}
        <x-cameroon-ribbon size="sm" />

        <div class="w-full max-w-lg px-5 sm:px-8 py-8 lg:py-6 relative z-10">

            {{-- Mobile-only logo (hidden on lg+) --}}
            <div class="text-center mb-5 lg:hidden">
                <a href="{{ route('home') }}" class="inline-flex items-center group">
                    @if($__siteLogo ?? null)
                        <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Cameroon Community' }}" class="h-20 object-contain transition-transform group-hover:scale-110">
                    @else
                        <span class="text-3xl transition-transform group-hover:scale-110">🇨🇲</span>
                    @endif
                </a>
            </div>

            {{-- ══ Progress Bar ══ --}}
            @php
                $stepIcons = [
                    1 => ['icon' => '�', 'en' => 'Welcome', 'fr' => 'Bienvenue'],
                    2 => ['icon' => '👤', 'en' => 'Account', 'fr' => 'Compte'],
                    3 => ['icon' => '🏠', 'en' => 'Roots', 'fr' => 'Origines'],
                    4 => ['icon' => '🚀', 'en' => 'Launch', 'fr' => 'Lancer'],
                ];
            @endphp
            <div class="flex items-center justify-between mb-5 px-1">
                @foreach($stepIcons as $i => $meta)
                <div class="flex items-center {{ $i < 4 ? 'flex-1' : '' }}">
                    <div class="flex flex-col items-center gap-1">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full text-sm transition-all duration-500
                            {{ $step > $i ? 'bg-cm-green text-white shadow-md shadow-cm-green/30' :
                               ($step === $i ? 'bg-cm-green text-white shadow-lg shadow-cm-green/40 scale-110 ring-4 ring-cm-green/20' :
                               'bg-slate-100 text-slate-400') }}">
                            @if($step > $i)
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <span>{{ $meta['icon'] }}</span>
                            @endif
                        </div>
                        <span class="text-[10px] font-semibold hidden sm:block transition-colors {{ $step >= $i ? 'text-cm-green' : 'text-slate-400' }}"
                              x-text="$store.lang.t('{{ $meta['en'] }}', '{{ $meta['fr'] }}')"></span>
                    </div>
                    @if($i < 4)
                    <div class="flex-1 mx-2 h-0.5 rounded-full transition-all duration-700 {{ $step > $i ? 'bg-cm-green' : 'bg-slate-200' }}"></div>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- ══ Kamer AI Companion Bubble ══ --}}
            <div class="mb-4 flex items-start gap-3 px-1 transition-all duration-500"
                 x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 300)"
                 x-show="shown" x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-cm-green to-blue-700 flex items-center justify-center shadow-lg shadow-cm-green/20">
                    <span class="text-lg">🤖</span>
                </div>
                <div class="flex-1 relative bg-white rounded-2xl rounded-tl-md px-4 py-3 shadow-sm border border-slate-200/80">
                    <div class="absolute -left-2 top-3 w-2 h-2 bg-white border-l border-t border-slate-200/80 rotate-[-45deg]"></div>
                    <p class="text-sm text-slate-700 leading-relaxed font-medium"
                       wire:key="ai-msg-{{ $step }}-{{ $current_country }}-{{ $username }}-{{ $home_region }}">
                        {{ $aiMessage }}
                    </p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-cm-green animate-pulse"></span>
                        <span class="text-[10px] font-semibold text-cm-green">Kamer AI</span>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════ --}}
            {{-- Main Card                                              --}}
            {{-- ══════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl shadow-xl shadow-black/5 border border-slate-200/80 overflow-hidden">

                {{-- Tricolour top line --}}
                <div class="h-1 bg-gradient-to-r from-cm-green via-cm-red to-cm-yellow"></div>

                <div class="p-6 sm:p-7">

                    {{-- ═══════════ STEP 1 — Personalize ═══════════ --}}
                    @if($step === 1)
                    <div wire:key="step-1">
                        <h2 class="text-2xl font-extrabold text-slate-900"
                            x-text="$store.lang.t('Let’s personalize your experience', 'Personnalisons votre expérience')"></h2>
                        <p class="mt-1 text-sm text-slate-500"
                           x-text="$store.lang.t(
                               'Tell us which community you’d like to join — you can change this anytime.',
                               'Dites-nous à quelle communauté vous souhaitez vous joindre — modifiable à tout moment.'
                           )"></p>

                        <div class="mt-6 space-y-4">
                            {{-- GPS Radar Animation --}}
                            <div x-show="detecting" x-transition class="flex flex-col items-center py-6 gap-3">
                                <div class="relative w-20 h-20">
                                    <div class="absolute inset-0 rounded-full border-2 border-cm-green/30 animate-ping"></div>
                                    <div class="absolute inset-2 rounded-full border-2 border-cm-green/40 animate-ping" style="animation-delay:.4s"></div>
                                    <div class="absolute inset-4 rounded-full border-2 border-cm-green/50 animate-ping" style="animation-delay:.8s"></div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-cm-green" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-cm-green animate-pulse"
                                      x-text="$store.lang.t('Setting things up for you...', 'Préparation de votre espace...')"></span>
                            </div>

                            {{-- Detected Success Banner --}}
                            <div x-show="detected && !detecting" x-transition
                                 class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-cm-green/5 to-blue-50 border border-cm-green/20 px-4 py-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-cm-green/10 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-cm-green" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm text-cm-green font-semibold">
                                        <span x-text="$store.lang.t('Welcome! ', 'Bienvenue ! ')"></span>
                                        <span>{{ $current_region }}{{ $current_region && $current_country ? ', ' : '' }}{{ $current_country }}</span>
                                    </p>
                                    @if($this->communityStats['country_users'] > 0)
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ $this->communityStats['country_users'] }}
                                        <span x-text="$store.lang.t(' Cameroonians already here', ' Camerounais déjà ici')"></span> 🎉
                                    </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Country Selector --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Current Country', 'Pays Actuel')"></label>
                                <select wire:model.live="current_country" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green bg-white disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed" {{ $gps_detected ? 'disabled' : '' }}>
                                    <option value="" x-text="$store.lang.t('Select country...', 'Sélectionnez un pays...')"></option>
                                    @if($current_country && !in_array($current_country, $countries))
                                        <option value="{{ $current_country }}" selected>{{ $current_country }}</option>
                                    @endif
                                    @foreach($countries as $code => $cName)
                                        <option value="{{ $cName }}">{{ $cName }}</option>
                                    @endforeach
                                </select>
                                @error('current_country') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Region / State / Province --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Region / State / Province', 'R\u00e9gion / \u00c9tat / Province')"></label>
                                <input wire:model="current_region" type="text"
                                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green read-only:bg-slate-100 read-only:text-slate-500 read-only:cursor-not-allowed"
                                       placeholder="England, Bavaria, California..."
                                       {{ $gps_detected ? 'readonly' : '' }}>
                                @error('current_region') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Language Toggle (early — so AI messages match) --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2"
                                       x-text="$store.lang.t('Preferred Language', 'Langue Préférée')"></label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="relative cursor-pointer">
                                        <input wire:model.live="language_pref" type="radio" value="en" class="peer sr-only"
                                               @change="$store.lang.current = 'en'">
                                        <div class="rounded-xl border-2 p-3 text-center transition-all peer-checked:border-cm-green peer-checked:bg-cm-green/5 peer-checked:shadow-md peer-checked:shadow-cm-green/10 border-slate-200 hover:border-slate-300">
                                            <span class="text-xl block mb-0.5">🇬🇧</span>
                                            <span class="text-xs font-bold text-slate-800">English</span>
                                        </div>
                                    </label>
                                    <label class="relative cursor-pointer">
                                        <input wire:model.live="language_pref" type="radio" value="fr" class="peer sr-only"
                                               @change="$store.lang.current = 'fr'">
                                        <div class="rounded-xl border-2 p-3 text-center transition-all peer-checked:border-cm-green peer-checked:bg-cm-green/5 peer-checked:shadow-md peer-checked:shadow-cm-green/10 border-slate-200 hover:border-slate-300">
                                            <span class="text-xl block mb-0.5">🇫🇷</span>
                                            <span class="text-xs font-bold text-slate-800">Français</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {{-- Retry GPS --}}
                            <button type="button" @click="detectLocation()" x-show="!detecting"
                                    class="text-xs text-cm-green font-medium hover:underline flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span x-text="$store.lang.t('Not your area? Refresh', 'Mauvaise zone ? Actualiser')"></span>
                            </button>
                        </div>

                        <button wire:click="nextStep"
                                class="mt-6 w-full rounded-xl bg-gradient-to-r from-cm-green to-blue-700 py-3.5 text-sm font-bold text-white shadow-lg shadow-cm-green/20 transition-all hover:shadow-xl hover:shadow-cm-green/30 hover:-translate-y-0.5 disabled:opacity-50"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="nextStep" class="flex items-center justify-center gap-2">
                                <span x-text="$store.lang.t('Continue', 'Continuer')"></span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </span>
                            <span wire:loading wire:target="nextStep">
                                <svg class="inline w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                        </button>
                    </div>

                    {{-- ═══════════ STEP 2 — "Your Identity" — Account ═══════════ --}}
                    @elseif($step === 2)
                    <div wire:key="step-2">
                        <h2 class="text-2xl font-extrabold text-slate-900"
                            x-text="$store.lang.t('Create Your Account', 'Créez Votre Compte')"></h2>
                        <p class="mt-1 text-sm text-slate-500"
                           x-text="$store.lang.t('This takes under a minute — we promise.', 'Ça prend moins d\'une minute — promis.')"></p>

                        <div class="mt-6 space-y-4">
                            {{-- Username --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Username', 'Nom d\'utilisateur')"></label>
                                <input wire:model.live.debounce.500ms="username" type="text"
                                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                       placeholder="emmanuel">
                                @if($username)
                                    <p class="mt-1 text-xs text-slate-500">
                                        <span x-text="$store.lang.t('You\'ll be known as:', 'Vous serez connu sous:')"></span>
                                        <span class="font-semibold text-cm-green">{{ strtolower(preg_replace('/\s+/', '', trim($username))) }}<span class="text-slate-400">*****</span></span>
                                    </p>
                                @endif
                                @error('username') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Email --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Email Address', 'Adresse Email')"></label>
                                <input wire:model.blur="email" type="email"
                                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                       placeholder="you@example.com">
                                @error('email') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Password --}}
                            <div x-data="{ show: false, strength: 0 }">
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Password', 'Mot de Passe')"></label>
                                <div class="relative">
                                    <input wire:model.blur="password" :type="show ? 'text' : 'password'"
                                        @input="
                                            let v = $event.target.value;
                                            strength = 0;
                                            if (v.length >= 8) strength++;
                                            if (/[A-Z]/.test(v)) strength++;
                                            if (/[0-9]/.test(v)) strength++;
                                            if (/[^A-Za-z0-9]/.test(v)) strength++;
                                        "
                                        class="w-full rounded-xl border border-slate-300 px-4 py-3 pr-12 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                        placeholder="Min 8 chars, 1 uppercase, 1 number">
                                    <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                                {{-- Strength bar --}}
                                <div class="mt-2 flex gap-1">
                                    <template x-for="i in 4" :key="i">
                                        <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                             :class="i <= strength ? (strength <= 1 ? 'bg-cm-red' : (strength <= 2 ? 'bg-cm-yellow' : 'bg-cm-green')) : 'bg-slate-100'"></div>
                                    </template>
                                </div>
                                <p class="mt-1 text-xs font-medium" :class="strength <= 1 ? 'text-cm-red' : (strength <= 2 ? 'text-cm-yellow-dark' : 'text-cm-green')"
                                   x-text="strength === 0 ? '' : (strength <= 1 ? $store.lang.t('Weak — like watered garri', 'Faible — comme du garri dilué') : (strength <= 2 ? $store.lang.t('Getting there...', 'On y arrive...') : (strength <= 3 ? $store.lang.t('Solid! 💪', 'Solide ! 💪') : $store.lang.t('Fort like ndolé! 🔥', 'Fort comme le ndolé ! 🔥'))))"></p>
                                @error('password') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Confirm Password --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Confirm Password', 'Confirmer le Mot de Passe')"></label>
                                <input wire:model.blur="password_confirmation" type="password"
                                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                            </div>

                            {{-- Phone --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">
                                    <span x-text="$store.lang.t('Phone Number', 'Numéro de Téléphone')"></span>
                                    <span class="text-slate-400 font-normal" x-text="$store.lang.t(' (optional)', ' (optionnel)')"></span>
                                </label>
                                <input wire:model.blur="phone" type="tel"
                                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                       placeholder="+44 7911 123456">
                                @error('phone') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button wire:click="previousStep"
                                    class="flex-1 rounded-xl border border-slate-300 py-3.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-50">
                                <span x-text="$store.lang.t('Back', 'Retour')"></span>
                            </button>
                            <button wire:click="nextStep"
                                    class="flex-[2] rounded-xl bg-gradient-to-r from-cm-green to-blue-700 py-3.5 text-sm font-bold text-white shadow-lg shadow-cm-green/20 transition-all hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="nextStep" class="flex items-center justify-center gap-2">
                                    <span x-text="$store.lang.t('Continue', 'Continuer')"></span>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </span>
                                <span wire:loading wire:target="nextStep">
                                    <svg class="inline w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- ═══════════ STEP 3 — "Your Roots" — Origin ═══════════ --}}
                    @elseif($step === 3)
                    <div wire:key="step-3">
                        <h2 class="text-2xl font-extrabold text-slate-900"
                            x-text="$store.lang.t('Your Cameroonian Roots', 'Vos Racines Camerounaises')"></h2>
                        <p class="mt-1 text-sm text-slate-500"
                           x-text="$store.lang.t('Where does your heart call home?', 'D\'où vient votre cœur ?')"></p>

                        <div class="mt-6 space-y-4">
                            {{-- Country of Origin --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Country of Origin', 'Pays d\'Origine')"></label>
                                <select wire:model="country_of_origin"
                                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green bg-white">
                                    <option value="Cameroon">🇨🇲 Cameroon</option>
                                    <option value="Nigeria">🇳🇬 Nigeria</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            {{-- Home Region (card grid for visual appeal) --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2"
                                       x-text="$store.lang.t('Home Region', 'Région d\'Origine')"></label>
                                @php
                                    $regionEmojis = [
                                        'Adamawa' => '🌅', 'Centre' => '💚', 'East' => '🌿',
                                        'Far North' => '🎨', 'Littoral' => '🔥', 'North' => '☀️',
                                        'Northwest' => '🌄', 'South' => '🌊', 'Southwest' => '🏖️', 'West' => '💪',
                                    ];
                                @endphp
                                <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1">
                                    @foreach($regions as $key => $region)
                                    <label class="relative cursor-pointer group">
                                        <input wire:model.live="home_region" type="radio" value="{{ $region }}" class="peer sr-only">
                                        <div class="rounded-xl border-2 px-3 py-2.5 text-center transition-all peer-checked:border-cm-green peer-checked:bg-cm-green/5 peer-checked:shadow-md peer-checked:shadow-cm-green/10 border-slate-200 hover:border-slate-300 group-hover:bg-slate-50">
                                            <span class="text-lg block">{{ $regionEmojis[$region] ?? '📍' }}</span>
                                            <span class="text-xs font-semibold text-slate-800">{{ $region }}</span>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Home City --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"
                                       x-text="$store.lang.t('Home City/Town', 'Ville/Village d\'Origine')"></label>
                                <input wire:model="home_city" type="text"
                                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                       placeholder="Bamenda, Douala, Yaoundé...">
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button wire:click="previousStep"
                                    class="flex-1 rounded-xl border border-slate-300 py-3.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-50">
                                <span x-text="$store.lang.t('Back', 'Retour')"></span>
                            </button>
                            <button wire:click="nextStep"
                                    class="flex-[2] rounded-xl bg-gradient-to-r from-cm-green to-blue-700 py-3.5 text-sm font-bold text-white shadow-lg shadow-cm-green/20 transition-all hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="nextStep" class="flex items-center justify-center gap-2">
                                    <span x-text="$store.lang.t('Almost There!', 'Presque Fini !')"></span>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </span>
                                <span wire:loading wire:target="nextStep">
                                    <svg class="inline w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- ═══════════ STEP 4 — "Welcome Home" — Review & Register ═══════════ --}}
                    @elseif($step === 4)
                    <div wire:key="step-4">
                        <h2 class="text-2xl font-extrabold text-slate-900"
                            x-text="$store.lang.t('Welcome Home! 🎉', 'Bienvenue Chez Vous ! 🎉')"></h2>
                        <p class="mt-1 text-sm text-slate-500"
                           x-text="$store.lang.t('Review your details and join the family.', 'Vérifiez vos informations et rejoignez la famille.')"></p>

                        {{-- Summary Cards --}}
                        <div class="mt-5 space-y-3">
                            {{-- Location --}}
                            <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 border border-slate-100">
                                <span class="text-xl">📍</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-slate-500 font-medium" x-text="$store.lang.t('Location', 'Position')"></p>
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $current_region ? "$current_region, " : '' }}{{ $current_country }}</p>
                                </div>
                                <button wire:click="$set('step', 1)" class="text-xs text-cm-green font-medium hover:underline"
                                        x-text="$store.lang.t('Edit', 'Modifier')"></button>
                            </div>

                            {{-- Account --}}
                            <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 border border-slate-100">
                                <span class="text-xl">👤</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-slate-500 font-medium" x-text="$store.lang.t('Account', 'Compte')"></p>
                                    <p class="text-sm font-semibold text-slate-900 truncate">@ {{ strtolower(preg_replace('/\s+/', '', trim($username))) }}****</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $email }}</p>
                                </div>
                                <button wire:click="$set('step', 2)" class="text-xs text-cm-green font-medium hover:underline"
                                        x-text="$store.lang.t('Edit', 'Modifier')"></button>
                            </div>

                            {{-- Roots --}}
                            <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 border border-slate-100">
                                <span class="text-xl">🏠</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-slate-500 font-medium" x-text="$store.lang.t('Roots', 'Origines')"></p>
                                    <p class="text-sm font-semibold text-slate-900 truncate">
                                        {{ $home_region ?: $country_of_origin }}{{ $home_city ? ", $home_city" : '' }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ $language_pref === 'fr' ? '🇫🇷 Français' : '🇬🇧 English' }}</p>
                                </div>
                                <button wire:click="$set('step', 3)" class="text-xs text-cm-green font-medium hover:underline"
                                        x-text="$store.lang.t('Edit', 'Modifier')"></button>
                            </div>
                        </div>

                        {{-- What Awaits You --}}
                        <div class="mt-5 rounded-xl bg-gradient-to-br from-cm-green/5 to-blue-50 border border-cm-green/10 p-4">
                            <p class="text-xs font-bold text-cm-green uppercase tracking-wide mb-2"
                               x-text="$store.lang.t('What Awaits You', 'Ce Qui Vous Attend')"></p>
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <span class="text-xl block">💬</span>
                                    <span class="text-[11px] font-medium text-slate-700 block mt-0.5"
                                          x-text="$store.lang.t('The Yard', 'Le Yard')"></span>
                                    <span class="text-[10px] text-slate-500" x-text="$store.lang.t('Chat rooms', 'Salons')"></span>
                                </div>
                                <div>
                                    <span class="text-xl block">🤝</span>
                                    <span class="text-[11px] font-medium text-slate-700 block mt-0.5"
                                          x-text="$store.lang.t('Solidarity', 'Solidarité')"></span>
                                    <span class="text-[10px] text-slate-500" x-text="$store.lang.t('Crowdfund', 'Financer')"></span>
                                </div>
                                <div>
                                    <span class="text-xl block">🤖</span>
                                    <span class="text-[11px] font-medium text-slate-700 block mt-0.5">Kamer AI</span>
                                    <span class="text-[10px] text-slate-500" x-text="$store.lang.t('Your guide', 'Votre guide')"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Founding Member Tease --}}
                        <div class="mt-4 flex items-center gap-2 rounded-xl bg-cm-yellow/10 border border-cm-yellow/30 px-4 py-2.5"
                             x-data="{ show: false }" x-init="setTimeout(() => show = true, 500)" x-show="show" x-transition>
                            <span class="text-lg">⭐</span>
                            <p class="text-xs font-medium text-cm-yellow-dark"
                               x-text="$store.lang.t(
                                   'You\'re early! You could be a Founding Member.',
                                   'Vous êtes parmi les premiers ! Vous pourriez être Membre Fondateur.'
                               )"></p>
                        </div>

                        {{-- Terms --}}
                        <div class="mt-4">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input wire:model="terms" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-cm-green focus:ring-cm-green">
                                <span class="text-sm text-slate-600" x-html="$store.lang.t(
                                    'I agree to the <a href=\'#\' class=\'text-cm-green hover:underline font-medium\'>Terms of Service</a> and <a href=\'#\' class=\'text-cm-green hover:underline font-medium\'>Privacy Policy</a>',
                                    'J\'accepte les <a href=\'#\' class=\'text-cm-green hover:underline font-medium\'>Conditions d\'Utilisation</a> et la <a href=\'#\' class=\'text-cm-green hover:underline font-medium\'>Politique de Confidentialité</a>'
                                )"></span>
                            </label>
                            @error('terms') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button wire:click="previousStep"
                                    class="flex-1 rounded-xl border border-slate-300 py-3.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-50">
                                <span x-text="$store.lang.t('Back', 'Retour')"></span>
                            </button>
                            <button wire:click="register"
                                    class="flex-[2] rounded-xl bg-gradient-to-r from-cm-green to-blue-700 py-4 text-sm font-bold text-white shadow-lg shadow-cm-green/25 transition-all hover:shadow-xl hover:shadow-cm-green/30 hover:-translate-y-0.5 disabled:opacity-50"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="register" class="flex items-center justify-center gap-2">
                                    <span x-text="$store.lang.t('Join the Family 🎊', 'Rejoindre la Famille 🎊')"></span>
                                </span>
                                <span wire:loading wire:target="register" class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-text="$store.lang.t('Creating your space...', 'Création de votre espace...')"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                    @endif

                </div>
            </div>

            {{-- Login link --}}
            <p class="mt-6 text-center text-sm text-slate-500">
                <span x-text="$store.lang.t('Already have an account?', 'Vous avez déjà un compte ?')"></span>
                <a href="{{ route('login') }}" class="ml-1 font-semibold text-cm-green hover:underline" x-text="$store.lang.t('Sign in', 'Connectez-vous')"></a>
            </p>
        </div>
    </div>
</div>

