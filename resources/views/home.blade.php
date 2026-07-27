<x-layouts.guest>
    <x-slot:title>Cameroon Network — Connecting Cameroonians. Wherever They Are.</x-slot:title>

    <div class="fixed inset-0 flex flex-col overflow-hidden md:static md:block md:overflow-visible md:h-auto md:min-h-0">
    @include('partials.site-nav', ['staysTransparent' => true])

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 1 — HERO (full viewport)
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="relative z-0 md:min-h-screen md:h-auto flex-1 min-h-0 md:flex-none md:overflow-visible overflow-hidden flex items-start lg:items-center pt-16 sm:pt-20 lg:pt-32 pb-8 md:pb-8 lg:pb-0">
        {{-- Background image --}}
        <div class="absolute inset-0 pointer-events-none">
            <img src="{{ asset('images/hero-bg.jpg') }}" alt="" class="h-full w-full object-cover">
            {{-- Dark overlay for text readability --}}
            <div class="absolute inset-0 bg-gradient-to-r from-cm-green/90 via-cm-green/70 to-cm-green/40"></div>
        </div>

        {{-- Animated geometric SVG background --}}
        <div class="absolute inset-0 overflow-hidden opacity-20 pointer-events-none">
            <svg class="hidden md:block absolute -top-20 -left-20 w-96 h-96 animate-pulse-soft" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="80" fill="none" stroke="#FCD116" stroke-width="1.5"/>
                <circle cx="100" cy="100" r="60" fill="none" stroke="#FCD116" stroke-width="1"/>
                <circle cx="100" cy="100" r="40" fill="none" stroke="#FCD116" stroke-width="0.5"/>
            </svg>
            <svg class="absolute top-1/3 right-10 w-72 h-72" viewBox="0 0 200 200">
                <polygon points="100,10 190,190 10,190" fill="none" stroke="#CE1126" stroke-width="1" opacity="0.4" class="animate-pulse-soft" style="animation-delay: 1s"/>
            </svg>
            <svg class="absolute bottom-20 left-1/4 w-64 h-64" viewBox="0 0 200 200">
                <rect x="30" y="30" width="140" height="140" rx="20" fill="none" stroke="#FCD116" stroke-width="1" opacity="0.3" class="animate-pulse-soft" style="animation-delay: 2s"/>
            </svg>
            {{-- Floating dots pattern --}}
            @for($i = 0; $i < 20; $i++)
                <div class="absolute w-1.5 h-1.5 rounded-full bg-cm-yellow/30 animate-pulse-soft"
                     style="top: {{ rand(5,95) }}%; left: {{ rand(5,95) }}%; animation-delay: {{ $i * 0.3 }}s"></div>
            @endfor
        </div>

        {{-- ═══════════════════════════════════════════════════
             CAMEROON FLAG — Top Right Corner (Mobile & Desktop)
             ═══════════════════════════════════════════════════ --}}
        <div class="absolute top-16 sm:top-[92px] right-[-1rem] md:right-[-1.5rem] lg:right-[-2rem] xl:right-[-2.5rem] z-[2] pointer-events-none transform lg:-rotate-2" style="filter: drop-shadow(0 20px 30px rgba(0,0,0,0.4));">
            <img src="{{ asset('images/cameroonflag.png') }}" alt="Cameroon" class="w-20 sm:w-24 md:w-32 lg:w-40 xl:w-48 select-none opacity-95 animate-pulse-soft" style="animation-duration: 5s;">
        </div>
        {{-- Mini world-map graphic (mobile-only) — cities connected to Cameroon --}}
        <div class="md:hidden absolute top-16 left-2 z-[2] w-44 h-44 pointer-events-none">
            {{-- Faint continents backdrop --}}
            <svg viewBox="0 0 200 200" class="absolute inset-0 w-full h-full opacity-25" fill="none" stroke="white" stroke-width="0.6">
                <path d="M40,40 C55,30 80,30 95,40 C110,35 125,45 120,60 C130,65 125,80 110,75 C105,90 80,90 70,80 C55,85 40,75 45,60 C35,55 30,45 40,40Z"/>
                <path d="M105,55 C120,45 150,45 165,55 C175,65 170,80 160,80 C150,90 130,85 120,75 C110,80 100,70 105,55Z"/>
                <path d="M70,110 C90,100 115,110 120,130 C115,150 90,160 70,150 C50,145 50,120 70,110Z"/>
                <path d="M130,135 C145,125 165,135 165,150 C160,165 140,170 130,160 C120,155 120,140 130,135Z"/>
            </svg>
            {{-- Dashed connection lines from Cameroon (center) to cities --}}
            <svg viewBox="0 0 200 200" class="absolute inset-0 w-full h-full" fill="none" preserveAspectRatio="none">
                <line x1="105" y1="120" x2="25"  y2="35"  stroke="#FCD116" stroke-width="0.8" stroke-dasharray="3 2" class="hero-map-line" style="--delay:0s"/>
                <line x1="105" y1="120" x2="70"  y2="20"  stroke="#FCD116" stroke-width="0.8" stroke-dasharray="3 2" class="hero-map-line" style="--delay:0.3s"/>
                <line x1="105" y1="120" x2="130" y2="25"  stroke="#FCD116" stroke-width="0.8" stroke-dasharray="3 2" class="hero-map-line" style="--delay:0.6s"/>
                <line x1="105" y1="120" x2="175" y2="60"  stroke="#FCD116" stroke-width="0.8" stroke-dasharray="3 2" class="hero-map-line" style="--delay:0.9s"/>
                <line x1="105" y1="120" x2="185" y2="120" stroke="#FCD116" stroke-width="0.8" stroke-dasharray="3 2" class="hero-map-line" style="--delay:1.2s"/>
            </svg>
            {{-- City pills --}}
            <div class="absolute text-[7px] font-bold text-white bg-cm-green/85 rounded-full px-1.5 py-0.5 hero-city-dot whitespace-nowrap" style="top:12%; left:0%; --delay:0s">🇺🇸 NYC</div>
            <div class="absolute text-[7px] font-bold text-white bg-cm-green/85 rounded-full px-1.5 py-0.5 hero-city-dot whitespace-nowrap" style="top:4%; left:30%; --delay:0.3s">🇬🇧 LDN</div>
            <div class="absolute text-[7px] font-bold text-white bg-cm-green/85 rounded-full px-1.5 py-0.5 hero-city-dot whitespace-nowrap" style="top:8%; left:60%; --delay:0.6s">🇫🇷 PAR</div>
            <div class="absolute text-[7px] font-bold text-white bg-cm-green/85 rounded-full px-1.5 py-0.5 hero-city-dot whitespace-nowrap" style="top:25%; right:0%; --delay:0.9s">🇦🇪 DXB</div>
            <div class="absolute text-[7px] font-bold text-white bg-cm-green/85 rounded-full px-1.5 py-0.5 hero-city-dot whitespace-nowrap" style="top:55%; right:0%; --delay:1.2s">🇦🇺 SYD</div>
            {{-- Cameroon flag dot at center --}}
            <div class="absolute" style="top:58%; left:50%; transform:translate(-50%,-50%)">
                <div class="w-3 h-3 bg-cm-yellow rounded-sm animate-pulse-soft shadow shadow-cm-yellow/60"></div>
            </div>
            {{-- Caption --}}
            <div class="absolute left-0 right-0 text-center" style="top:70%;">
                <p class="text-cm-yellow text-[10px] font-extrabold tracking-wider">🇨🇲 CAMEROON</p>
                <p class="text-white/70 text-[8px] mt-0.5" x-text="$store.lang.t('One Nation. Every Continent.', 'Une Nation. Tous les Continents.')"></p>
            </div>
        </div>

        <div class="relative z-10 mx-auto max-w-[1440px] px-4 sm:px-8 lg:px-12 py-4 sm:py-8 lg:py-20 w-full">
            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                {{-- Left: Text Content --}}
                <div data-animate class="space-y-4 sm:space-y-6 lg:space-y-8 min-w-0 pt-40 md:pt-0">
                    {{-- GPS Detection Badge --}}
                    <div class="hidden sm:block" x-data="{ country: null }" x-init="
                        const locMode = @js(\App\Models\PlatformSetting::getValue('location_detection_mode', 'gps'));
                        if (locMode === 'ip') {
                            try {
                                const resp = await fetch('{{ route("geo.ip") }}');
                                const data = await resp.json();
                                if (!data.error) country = data.country_name;
                            } catch(e) {}
                        } else if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(async (pos) => {
                                try {
                                    const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&format=json&accept-language=en`);
                                    const data = await resp.json();
                                    country = data.address?.country || null;
                                } catch(e) {}
                            }, () => {}, { timeout: 5000 });
                        }
                    ">
                        <div x-show="country" x-transition class="inline-flex max-w-full items-center gap-2 rounded-full bg-white/10 backdrop-blur-sm px-3 sm:px-4 py-2 text-xs sm:text-sm text-cm-yellow border border-cm-yellow/20">
                            <span class="relative flex h-2 w-2 shrink-0">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cm-yellow opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-cm-yellow"></span>
                            </span>
                            <span class="truncate" x-text="$store.lang.t(
                                'Welcome — your ' + country + ' community is right here',
                                'Bienvenue — votre communauté ' + country + ' est juste ici'
                            )"></span>
                        </div>
                    </div>

                    <h1 class="font-extrabold text-white tracking-tight">
                        {{-- Mobile variant: stacked, left-aligned, oversized 'Cameroonians' --}}
                        <span class="block md:hidden text-center leading-[1.02]">
                            <span class="block font-bold text-[clamp(26px,8.5vw,42px)] -translate-x-[9vw]" x-text="$store.lang.t('Connecting', 'Connecter les')"></span>
                            <span class="block font-black text-[clamp(34px,11.5vw,58px)] -mt-2" x-text="$store.lang.t('Cameroonians', 'Camerounais')"></span>
                            <span class="block text-center text-cm-yellow font-bold text-[clamp(26px,8.5vw,46px)] mt-3" x-text="$store.lang.t('Wherever You Are', 'Où Que Vous Soyez')"></span>
                        </span>
                        {{-- Desktop/tablet variant: unchanged --}}
                        <span class="hidden md:block text-center lg:text-left leading-[1.15]">
                            <span class="block whitespace-nowrap font-black text-[clamp(22px,7.2vw,44px)] lg:text-6xl" x-text="$store.lang.t('Connecting Cameroonians', 'Connecter les Camerounais')"></span>
                            <span class="block text-cm-yellow text-[clamp(20px,6vw,38px)] sm:text-5xl lg:text-6xl" x-text="$store.lang.t('Wherever You Are', 'Où Que Vous Soyez')"></span>
                        </span>
                    </h1>

                    <p class="text-[15px] sm:text-lg lg:text-xl font-bold text-white max-w-3xl leading-relaxed"
                       x-text="$store.lang.t(
                           'Connect with Cameroonians in your city and country. Find housing, send packages home, get help all in one place built just for you.',
                           'Connectez-vous avec les Camerounais de votre ville et pays. Trouvez un logement, envoyez des colis au pays, obtenez de l\'aide tout en un seul endroit conçu pour vous.'
                       )"></p>

                    {{-- CTAs --}}
                    <div class="flex flex-row flex-nowrap items-stretch gap-2 sm:gap-4">
                        <a href="{{ route('register') }}" class="inline-flex flex-none min-w-0 justify-center items-center gap-1.5 sm:gap-2 rounded-full bg-cm-yellow px-3.5 sm:px-6 py-1.5 sm:py-3 text-sm sm:text-lg font-extrabold text-cm-green-dark shadow-lg shadow-cm-yellow/25 transition-all hover:bg-cm-yellow-light hover:shadow-xl hover:shadow-cm-yellow/30 hover:-translate-y-0.5">
                            <span class="truncate" x-text="$store.lang.t('Join Free', 'Rejoindre Gratuitement')"></span>
                        </a>
                        <a href="#how-it-works" @click.prevent="window.dispatchEvent(new CustomEvent('open-how-it-works-modal'))" class="inline-flex flex-none min-w-0 justify-center items-center gap-1.5 sm:gap-2 rounded-full border border-white/40 px-3.5 sm:px-6 py-1.5 sm:py-3 text-sm sm:text-lg font-extrabold text-white transition-all hover:bg-white/10 hover:border-white/60">
                            <span class="truncate" x-text="$store.lang.t('How It Works', 'Comment Ça Marche')"></span>
                        </a>
                    </div>

                    {{-- Mobile-only Partner With Us blurb (flows in column instead of overlapping) --}}
                    <div class="md:hidden max-w-[55vw] text-white mt-20">
                        <p class="font-semibold text-sm leading-snug" x-text="$store.lang.t('Partner With Us for a Better Way', 'Partenariat — Une Meilleure Voie')"></p>
                        <p class="text-xs font-bold text-white/80 mt-0.5" x-text="$store.lang.t('To amplify your business', 'Pour amplifier votre activité')"></p>
                    </div>

                    {{-- Get the App — Store badges (tablet/desktop; mobile uses fixed bottom bar) --}}
                    <div class="pt-1 hidden md:block">
                        <p class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-white/60 mb-2 text-center lg:text-left"
                           x-text="$store.lang.t('Get the app', 'Téléchargez l’app')"></p>
                        <div class="flex flex-row flex-nowrap items-stretch gap-2 sm:gap-3 justify-center lg:justify-start">
                            {{-- App Store --}}
                            <a href="#"
                               @click.prevent="window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: $store.lang.t('iOS app coming soon!', 'App iOS bientôt disponible !') } }))"
                               class="group relative inline-flex flex-1 sm:flex-none min-w-0 items-center gap-2 sm:gap-3 rounded-xl bg-black px-3 sm:px-4 py-2 sm:py-2.5 text-white shadow-lg ring-1 ring-white/10 transition-all hover:-translate-y-0.5 hover:ring-white/25"
                               :title="$store.lang.t('Download on the App Store — Coming soon', 'Télécharger sur l’App Store — Bientôt')">
                                <svg class="h-7 w-7 sm:h-8 sm:w-8 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.492 0-2.52-1.27-3.439-2.61C3.142 17.43 2 13.95 2 10.68c0-5.25 3.39-8.04 6.73-8.04 1.49 0 2.74.97 3.66.97.88 0 2.28-1.04 3.93-1.04.63 0 2.95.06 4.45 2.22-.12.07-2.62 1.52-2.62 4.54 0 3.55 3.16 4.85 3.16 4.85z"/>
                                </svg>
                                <div class="flex flex-col leading-tight min-w-0">
                                    <span class="text-[9px] sm:text-[10px] uppercase tracking-wide text-white/70" x-text="$store.lang.t('Download on the', 'Télécharger sur l’')"></span>
                                    <span class="text-sm sm:text-base font-semibold truncate">App Store</span>
                                </div>
                                <span class="absolute -top-1.5 -right-1.5 rounded-full bg-cm-yellow px-1.5 py-0.5 text-[8px] sm:text-[9px] font-bold text-cm-green-dark shadow"
                                      x-text="$store.lang.t('SOON', 'BIENTÔT')"></span>
                            </a>

                            {{-- Google Play --}}
                            <a href="#"
                               @click.prevent="window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: $store.lang.t('Android app coming soon!', 'App Android bientôt disponible !') } }))"
                               class="group relative inline-flex flex-1 sm:flex-none min-w-0 items-center gap-2 sm:gap-3 rounded-xl bg-black px-3 sm:px-4 py-2 sm:py-2.5 text-white shadow-lg ring-1 ring-white/10 transition-all hover:-translate-y-0.5 hover:ring-white/25"
                               :title="$store.lang.t('Get it on Google Play — Coming soon', 'Disponible sur Google Play — Bientôt')">
                                <svg class="h-7 w-7 sm:h-8 sm:w-8 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3.609 1.814 13.792 12 3.61 22.186a.996.996 0 0 1-.61-.92V2.734a1 1 0 0 1 .609-.92z" fill="#34a853"/>
                                    <path d="m13.792 12 2.92-2.92 4.06 2.34a1 1 0 0 1 0 1.74l-4.06 2.34L13.792 12z" fill="#fbbc04"/>
                                    <path d="m16.713 9.08-2.92 2.92L3.61 1.814A1 1 0 0 1 4.43 1.7l12.282 7.38z" fill="#ea4335"/>
                                    <path d="m13.792 12 2.92 2.92L4.43 22.3a1 1 0 0 1-.82-.114L13.792 12z" fill="#4285f4"/>
                                </svg>
                                <div class="flex flex-col leading-tight min-w-0">
                                    <span class="text-[9px] sm:text-[10px] uppercase tracking-wide text-white/70" x-text="$store.lang.t('Get it on', 'Disponible sur')"></span>
                                    <span class="text-sm sm:text-base font-semibold truncate">Google Play</span>
                                </div>
                                <span class="absolute -top-1.5 -right-1.5 rounded-full bg-cm-yellow px-1.5 py-0.5 text-[8px] sm:text-[9px] font-bold text-cm-green-dark shadow"
                                      x-text="$store.lang.t('SOON', 'BIENTÔT')"></span>
                            </a>
                        </div>
                    </div>

                    {{-- User Counter (desktop/tablet; mobile copy is rendered below the lady) --}}
                    <div class="hidden md:flex items-center gap-3 text-slate-300 flex-wrap" data-animate>
                        <div class="flex -space-x-2 shrink-0">
                            <div class="w-8 h-8 rounded-full bg-cm-yellow/80 border-2 border-cm-green flex items-center justify-center text-xs font-bold text-cm-green-dark">A</div>
                            <div class="w-8 h-8 rounded-full bg-cm-red/80 border-2 border-cm-green flex items-center justify-center text-xs font-bold text-white">E</div>
                            <div class="w-8 h-8 rounded-full bg-white/80 border-2 border-cm-green flex items-center justify-center text-xs font-bold text-cm-green">N</div>
                        </div>
                        <p class="text-xs sm:text-sm min-w-0">
                            <span class="font-bold text-cm-yellow" x-text="$store.lang.t('Cameroonians worldwide', 'Camerounais dans le monde')"></span>
                            <span class="text-slate-300" x-text="$store.lang.t(' — join your community!', ' — rejoignez votre communauté !')"></span>
                        </p>
                    </div>
                </div>

                {{-- Right: Animated Hero Slider (hidden on mobile — replaced by lady.png overlay) --}}
                <div data-animate class="hidden md:block">
                    <div class="relative mx-auto lg:ml-auto lg:mr-0 w-full max-w-[340px] sm:max-w-[380px] lg:w-[420px] lg:max-w-none"
                         x-data="{
                            current: 0,
                            slides: 3,
                            auto: null,
                            typeTexts: [
                                { en: 'Chat with Cameroonians near you', fr: 'Discutez avec les Camerounais près de vous' },
                                { en: 'One nation. Every continent.', fr: 'Une nation. Tous les continents.' },
                                { en: 'Together, we lift each other up', fr: 'Ensemble, on se soutient' }
                            ],
                            typed: '',
                            typeIdx: 0,
                            typing: false,
                            init() {
                                this.startTyping();
                                this.auto = setInterval(() => { this.next() }, 5500);
                            },
                            next() {
                                this.current = (this.current + 1) % this.slides;
                                this.startTyping();
                            },
                            goTo(i) {
                                this.current = i;
                                this.startTyping();
                                clearInterval(this.auto);
                                this.auto = setInterval(() => { this.next() }, 5500);
                            },
                            startTyping() {
                                this.typed = '';
                                this.typing = true;
                                const text = this.$store.lang.isEn ? this.typeTexts[this.current].en : this.typeTexts[this.current].fr;
                                let i = 0;
                                const iv = setInterval(() => {
                                    if (i < text.length) { this.typed += text[i]; i++; }
                                    else { clearInterval(iv); this.typing = false; }
                                }, 40);
                            }
                         }">

                        {{-- Typewriter Caption --}}
                        <div class="mb-4 h-8 flex items-center justify-center">
                            <p class="text-sm text-white font-bold tracking-wide">
                                <span x-text="typed"></span><span class="inline-block w-0.5 h-4 bg-white ml-0.5 align-middle" :class="typing ? 'animate-blink' : 'opacity-0'"></span>
                            </p>
                        </div>

                        {{-- Slides Container --}}
                        <div class="relative overflow-hidden rounded-[2rem]">
                            <div class="flex transition-transform duration-700 ease-in-out" :style="'transform: translateX(-' + (current * 100) + '%)'">

                                {{-- ═══ SLIDE 1: Yard Chat Preview ═══ --}}
                                <div class="w-full flex-shrink-0">
                                    <div class="rounded-[2rem] border-[6px] border-slate-800 bg-slate-800 shadow-2xl overflow-hidden">
                                        <div class="bg-white rounded-[1.6rem] overflow-hidden">
                                            <div class="bg-cm-green px-4 py-2 flex items-center justify-between text-white text-xs">
                                                <span>9:41</span>
                                                <div class="flex gap-1">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6z"/></svg>
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="10" rx="2"/></svg>
                                                </div>
                                            </div>
                                            <div class="bg-cm-green px-4 py-3 flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-cm-yellow/20 flex items-center justify-center text-sm">🇬🇧</div>
                                                <div>
                                                    <p class="text-white text-sm font-semibold">UK National Room</p>
                                                    <p class="text-slate-400 text-xs">247 members · 12 online</p>
                                                </div>
                                            </div>
                                            <div class="bg-slate-50 p-3 space-y-3 h-72">
                                                <div class="flex gap-2">
                                                    <div class="w-7 h-7 rounded-full bg-cm-red/20 flex items-center justify-center text-[10px] font-bold text-cm-red shrink-0">EN</div>
                                                    <div class="bg-white rounded-lg rounded-tl-none px-3 py-2 shadow-sm max-w-[80%]">
                                                        <p class="text-[10px] font-semibold text-cm-green">Emmanuel N.</p>
                                                        <p class="text-xs text-slate-700 mt-0.5">Does anyone know a good Cameroonian restaurant in London? 🍲</p>
                                                        <p class="text-[9px] text-slate-400 mt-1">10:23 AM</p>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <div class="w-7 h-7 rounded-full bg-cm-yellow/30 flex items-center justify-center text-[10px] font-bold text-cm-green-dark shrink-0">AF</div>
                                                    <div class="bg-white rounded-lg rounded-tl-none px-3 py-2 shadow-sm max-w-[80%]">
                                                        <p class="text-[10px] font-semibold text-cm-green">Afi T.</p>
                                                        <p class="text-xs text-slate-700 mt-0.5">Yes! Try Mama Africa in Peckham. Their ndolé is amazing 🔥</p>
                                                        <p class="text-[9px] text-slate-400 mt-1">10:25 AM</p>
                                                    </div>
                                                </div>
                                                <div class="bg-cm-yellow/10 border border-cm-yellow/30 rounded-lg px-3 py-2">
                                                    <p class="text-[10px] font-semibold text-cm-green flex items-center gap-1">🕊️ Solidarity</p>
                                                    <p class="text-[10px] text-slate-600 mt-0.5">In Memory of Mama Ngozi</p>
                                                    <div class="mt-1.5 h-1.5 bg-slate-200 rounded-full overflow-hidden"><div class="h-full bg-cm-green rounded-full" style="width: 65%"></div></div>
                                                    <p class="text-[9px] text-slate-500 mt-1">£650 of £1,000 · 15 contributors</p>
                                                </div>
                                                <div class="flex gap-2 justify-end">
                                                    <div class="bg-cm-green rounded-lg rounded-tr-none px-3 py-2 shadow-sm max-w-[80%]">
                                                        <p class="text-xs text-white">Thank you! Going there this weekend 🙏</p>
                                                        <p class="text-[9px] text-slate-400 mt-1 text-right">10:26 AM ✓✓</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bg-white border-t border-slate-200 px-3 py-2 flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                </div>
                                                <div class="flex-1 bg-slate-100 rounded-full px-3 py-1.5 text-[10px] text-slate-400">Type a message...</div>
                                                <div class="w-6 h-6 rounded-full bg-cm-green flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ═══ SLIDE 2: Cameroon on the World Map ═══ --}}
                                <div class="w-full flex-shrink-0">
                                    <div class="rounded-[2rem] border-[6px] border-slate-800 bg-slate-900/90 backdrop-blur shadow-2xl overflow-hidden p-6 h-[430px] flex flex-col items-center justify-center relative">
                                        {{-- World map outline (simplified SVG) --}}
                                        <svg viewBox="0 0 500 300" class="w-full opacity-20" fill="none" stroke="white" stroke-width="0.5">
                                            {{-- Simplified continents --}}
                                            <path d="M120,80 C130,60 160,50 170,55 C180,45 200,50 210,60 C220,55 235,65 230,80 C240,75 250,90 240,100 C250,105 245,120 235,115 C240,130 225,140 215,135 C205,145 190,140 185,130 C175,140 160,135 155,125 C145,130 130,120 135,110 C125,105 120,90 120,80Z" />
                                            <path d="M260,70 C275,55 310,50 340,55 C370,50 400,60 420,75 C435,85 440,100 430,110 C440,120 435,135 420,130 C410,140 390,135 380,125 C370,135 350,130 345,120 C330,125 310,120 300,110 C290,120 275,115 270,105 C260,95 255,80 260,70Z" />
                                            <path d="M130,130 C140,125 155,130 165,140 C175,135 190,145 185,160 C195,165 190,185 175,180 C170,195 150,200 140,190 C130,200 115,195 110,185 C100,180 105,165 115,160 C105,150 110,135 130,130Z" />
                                            <path d="M255,130 C260,120 280,125 285,135 C295,130 305,140 300,150 C310,155 305,170 295,165 C290,175 275,170 270,160 C260,165 250,155 255,145 C248,140 250,135 255,130Z" />
                                            <path d="M340,170 C350,160 370,165 380,175 C395,170 410,180 405,195 C415,200 410,220 395,215 C385,225 365,220 360,210 C350,215 335,205 340,195 C330,190 332,175 340,170Z" />
                                            <path d="M160,225 C175,215 200,220 210,235 C225,230 240,240 235,255 C225,270 195,275 180,265 C165,275 145,265 145,250 C135,240 145,228 160,225Z" />
                                        </svg>

                                        {{-- Cameroon highlight (center-left of Africa) --}}
                                        <div class="absolute" style="top: 46%; left: 50%;">
                                            {{-- Cameroon shape --}}
                                            <div class="relative">
                                                <div class="w-4 h-4 bg-cm-yellow rounded-sm animate-pulse-soft shadow-lg shadow-cm-yellow/50"></div>
                                                <div class="absolute -inset-2 border-2 border-cm-yellow/40 rounded-full animate-ping"></div>
                                                <div class="absolute -inset-4 border border-cm-yellow/20 rounded-full animate-ping" style="animation-delay: 0.5s"></div>
                                            </div>
                                        </div>

                                        {{-- Connection lines to diaspora cities --}}
                                        <svg class="absolute inset-0 w-full h-full" viewBox="0 0 420 430" fill="none" style="pointer-events:none">
                                            {{-- Cameroon center point --}}
                                            {{-- Lines from Cameroon to cities --}}
                                            <line x1="210" y1="200" x2="130" y2="85" stroke="#FCD116" stroke-width="1" stroke-dasharray="4 3" class="hero-map-line" style="--delay:0s"/>
                                            <line x1="210" y1="200" x2="155" y2="75" stroke="#FCD116" stroke-width="1" stroke-dasharray="4 3" class="hero-map-line" style="--delay:0.3s"/>
                                            <line x1="210" y1="200" x2="310" y2="100" stroke="#FCD116" stroke-width="1" stroke-dasharray="4 3" class="hero-map-line" style="--delay:0.6s"/>
                                            <line x1="210" y1="200" x2="100" y2="110" stroke="#FCD116" stroke-width="1" stroke-dasharray="4 3" class="hero-map-line" style="--delay:0.9s"/>
                                            <line x1="210" y1="200" x2="345" y2="130" stroke="#FCD116" stroke-width="1" stroke-dasharray="4 3" class="hero-map-line" style="--delay:1.2s"/>
                                            <line x1="210" y1="200" x2="370" y2="200" stroke="#FCD116" stroke-width="1" stroke-dasharray="4 3" class="hero-map-line" style="--delay:1.5s"/>
                                        </svg>

                                        {{-- City labels --}}
                                        <div class="absolute text-[9px] font-bold text-white bg-cm-green/80 rounded-full px-2 py-0.5 hero-city-dot" style="top:16%; left:26%; --delay:0s">🇬🇧 London</div>
                                        <div class="absolute text-[9px] font-bold text-white bg-cm-green/80 rounded-full px-2 py-0.5 hero-city-dot" style="top:12%; left:32%; --delay:0.3s">🇫🇷 Paris</div>
                                        <div class="absolute text-[9px] font-bold text-white bg-cm-green/80 rounded-full px-2 py-0.5 hero-city-dot" style="top:20%; left:70%; --delay:0.6s">🇩🇪 Berlin</div>
                                        <div class="absolute text-[9px] font-bold text-white bg-cm-green/80 rounded-full px-2 py-0.5 hero-city-dot" style="top:22%; left:18%; --delay:0.9s">🇺🇸 New York</div>
                                        <div class="absolute text-[9px] font-bold text-white bg-cm-green/80 rounded-full px-2 py-0.5 hero-city-dot" style="top:28%; left:78%; --delay:1.2s">🇦🇪 Dubai</div>
                                        <div class="absolute text-[9px] font-bold text-white bg-cm-green/80 rounded-full px-2 py-0.5 hero-city-dot" style="top:46%; left:85%; --delay:1.5s">🇦🇺 Sydney</div>

                                        {{-- Center label --}}
                                        <div class="absolute flex flex-col items-center" style="top:52%; left:50%; transform:translate(-50%,0)">
                                            <span class="text-cm-yellow text-xs font-extrabold tracking-wider">🇨🇲 CAMEROON</span>
                                            <span class="text-white/60 text-[9px] mt-0.5">Connecting to the world</span>
                                        </div>

                                        {{-- Stats bar at bottom --}}
                                        <div class="absolute bottom-4 left-4 right-4 flex justify-around text-center">
                                            <div>
                                                <p class="text-cm-yellow text-lg font-extrabold">6</p>
                                                <p class="text-white/50 text-[9px]">Continents</p>
                                            </div>
                                            <div class="w-px bg-white/10"></div>
                                            <div>
                                                <p class="text-cm-yellow text-lg font-extrabold">30+</p>
                                                <p class="text-white/50 text-[9px]">Countries</p>
                                            </div>
                                            <div class="w-px bg-white/10"></div>
                                            <div>
                                                <p class="text-cm-yellow text-lg font-extrabold">24/7</p>
                                                <p class="text-white/50 text-[9px]">Connected</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ═══ SLIDE 3: Solidarity + Kamer AI ═══ --}}
                                <div class="w-full flex-shrink-0">
                                    <div class="rounded-[2rem] border-[6px] border-slate-800 bg-slate-800 shadow-2xl overflow-hidden">
                                        <div class="bg-white rounded-[1.6rem] overflow-hidden">
                                            <div class="bg-cm-green px-4 py-2 flex items-center justify-between text-white text-xs">
                                                <span>9:41</span>
                                                <div class="flex gap-1">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6z"/></svg>
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="10" rx="2"/></svg>
                                                </div>
                                            </div>
                                            <div class="bg-cm-green px-4 py-3">
                                                <p class="text-white text-sm font-semibold text-center">🕊️ Solidarity Campaigns</p>
                                            </div>
                                            <div class="p-4 space-y-3 h-[342px] bg-slate-50">
                                                {{-- Campaign 1 --}}
                                                <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-100">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <div class="w-8 h-8 rounded-lg bg-cm-red/10 flex items-center justify-center text-sm">🏥</div>
                                                        <div>
                                                            <p class="text-xs font-semibold text-slate-800">Medical Fund for Jean-Paul</p>
                                                            <p class="text-[9px] text-slate-500">Douala, Cameroon</p>
                                                        </div>
                                                    </div>
                                                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-cm-yellow to-cm-red rounded-full hero-progress-bar" style="--target:82%"></div>
                                                    </div>
                                                    <div class="flex justify-between mt-1.5 text-[9px] text-slate-500">
                                                        <span>€4,100 raised</span><span class="font-semibold text-cm-green">82%</span>
                                                    </div>
                                                </div>
                                                {{-- Campaign 2 --}}
                                                <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-100">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <div class="w-8 h-8 rounded-lg bg-cm-yellow/10 flex items-center justify-center text-sm">🎓</div>
                                                        <div>
                                                            <p class="text-xs font-semibold text-slate-800">Scholarship for Marie</p>
                                                            <p class="text-[9px] text-slate-500">Yaoundé → London</p>
                                                        </div>
                                                    </div>
                                                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-cm-green to-cm-yellow rounded-full hero-progress-bar" style="--target:45%"></div>
                                                    </div>
                                                    <div class="flex justify-between mt-1.5 text-[9px] text-slate-500">
                                                        <span>£2,250 raised</span><span class="font-semibold text-cm-green">45%</span>
                                                    </div>
                                                </div>
                                                {{-- AI Assistant Teaser --}}
                                                <div class="bg-gradient-to-br from-cm-green to-cm-green-dark rounded-xl p-3 text-white">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-sm">🤖</div>
                                                        <div>
                                                            <p class="text-xs font-semibold">Kamer AI Assistant</p>
                                                            <p class="text-[9px] text-white/60">Powered by AI, built for you</p>
                                                        </div>
                                                    </div>
                                                    <div class="bg-white/10 rounded-lg p-2 mt-1">
                                                        <p class="text-[10px] text-white/80 italic">"How do I open a bank account in France as a Cameroonian?"</p>
                                                        <div class="flex items-center gap-1 mt-1.5">
                                                            <div class="h-1 w-1 rounded-full bg-cm-yellow animate-pulse"></div>
                                                            <p class="text-[9px] text-cm-yellow">Kamer AI is typing...</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Slide Indicators --}}
                        <div class="flex items-center justify-center gap-2 mt-5">
                            <template x-for="i in slides" :key="i">
                                <button @click="goTo(i-1)"
                                        class="transition-all duration-300 rounded-full"
                                        :class="current === i-1 ? 'w-6 h-2 bg-cm-yellow' : 'w-2 h-2 bg-white/30 hover:bg-white/50'">
                                </button>
                            </template>
                        </div>

                        {{-- Floating notification badge --}}
                        <div class="absolute -top-4 -right-4 bg-cm-red text-white text-xs font-bold rounded-full px-3 py-1.5 shadow-lg animate-fade-in" style="animation-delay: 1.5s"
                             x-show="current === 0" x-transition>
                            3 new messages
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile-only: Lady.png + counter overlay (bottom-right of hero) --}}
        <div class="md:hidden absolute inset-x-0 bottom-0 z-[3] pointer-events-none">
            {{-- Lady image (bottom-right corner, scaled smaller to stay clear of text) --}}
            <img src="{{ asset('images/lady.png') }}" alt="" class="absolute -right-[18vw] bottom-8 w-[clamp(240px,46vh,540px)] max-h-[clamp(340px,62vh,620px)] [@media(max-height:820px)]:-right-[8vw] [@media(max-height:820px)]:w-[clamp(180px,32vh,320px)] [@media(max-height:820px)]:max-h-[clamp(260px,45vh,420px)] min-[420px]:-right-[14vw] min-[420px]:w-[clamp(260px,48vh,560px)] min-[420px]:max-h-[clamp(360px,62vh,640px)] object-contain object-bottom select-none drop-shadow-2xl">

            {{-- Mobile user counter (sits at very bottom of hero) --}}
            <div class="absolute inset-x-0 bottom-0 px-4 flex items-center gap-3 text-slate-200">
                <div class="flex -space-x-2 shrink-0">
                    <div class="w-7 h-7 rounded-full bg-cm-yellow/80 border-2 border-cm-green flex items-center justify-center text-[11px] font-bold text-cm-green-dark">A</div>
                    <div class="w-7 h-7 rounded-full bg-cm-red/80 border-2 border-cm-green flex items-center justify-center text-[11px] font-bold text-white">E</div>
                    <div class="w-7 h-7 rounded-full bg-white/80 border-2 border-cm-green flex items-center justify-center text-[11px] font-bold text-cm-green">N</div>
                </div>
                <p class="text-xs font-bold min-w-0">
                    <span class="font-bold text-cm-yellow" x-text="$store.lang.t('Cameroonians worldwide', 'Camerounais dans le monde')"></span>
                    <span x-text="$store.lang.t(' — join your community!', ' — rejoignez votre communauté !')"></span>
                </p>
            </div>
        </div>


    </section>

    {{-- ══════════ HIDDEN SECTIONS (temporarily) ══════════ --}}
    <div style="display:none">

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 2 — THE PROBLEM
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="hidden md:block py-20 lg:py-28 bg-white" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900"
                    x-text="$store.lang.t('Landing abroad is the hardest part.', 'Arriver à l\'étranger est le plus difficile.')"></h2>
                <div class="mt-4 h-1 w-16 bg-cm-red mx-auto rounded-full"></div>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                {{-- Pain Point 1 --}}
                <div class="group relative bg-slate-50 rounded-2xl p-8 transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="w-16 h-16 rounded-2xl bg-cm-green/10 flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cm-green" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3"
                        x-text="$store.lang.t('You don\'t know anyone in this city', 'Vous ne connaissez personne dans cette ville')"></h3>
                    <p class="text-slate-500 leading-relaxed"
                       x-text="$store.lang.t(
                           'You arrive in a new city with no connections. No one who understands your culture, your food, your language.',
                           'Vous arrivez dans une nouvelle ville sans contacts. Personne qui comprend votre culture, votre nourriture, votre langue.'
                       )"></p>
                    <div class="mt-4 flex items-center gap-2 text-cm-green font-semibold text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        <span x-text="$store.lang.t('Solved by GoConnect', 'Résolu par GoConnect')"></span>
                    </div>
                </div>

                {{-- Pain Point 2 --}}
                <div class="group relative bg-slate-50 rounded-2xl p-8 transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="w-16 h-16 rounded-2xl bg-cm-red/10 flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cm-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3"
                        x-text="$store.lang.t('Finding housing is dangerous alone', 'Trouver un logement seul est dangereux')"></h3>
                    <p class="text-slate-500 leading-relaxed"
                       x-text="$store.lang.t(
                           'Scammers target newcomers. Without a community to vouch and verify, you\'re vulnerable.',
                           'Les arnaqueurs ciblent les nouveaux arrivants. Sans communauté pour vérifier, vous êtes vulnérable.'
                       )"></p>
                    <div class="mt-4 flex items-center gap-2 text-cm-red font-semibold text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        <span x-text="$store.lang.t('Solved by KamerNest', 'Résolu par KamerNest')"></span>
                    </div>
                </div>

                {{-- Pain Point 3 --}}
                <div class="group relative bg-slate-50 rounded-2xl p-8 transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="w-16 h-16 rounded-2xl bg-cm-yellow/20 flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cm-yellow-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3"
                        x-text="$store.lang.t('Sending things home costs too much', 'Envoyer des choses au pays coûte trop cher')"></h3>
                    <p class="text-slate-500 leading-relaxed"
                       x-text="$store.lang.t(
                           'Shipping packages to Cameroon is expensive and unreliable. There has to be a better way.',
                           'Envoyer des colis au Cameroun est cher et peu fiable. Il doit y avoir une meilleure solution.'
                       )"></p>
                    <div class="mt-4 flex items-center gap-2 text-cm-yellow-dark font-semibold text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        <span x-text="$store.lang.t('Solved by EasyGoParcel', 'Résolu par EasyGoParcel')"></span>
                    </div>
                </div>
            </div>

            <p class="mt-12 text-center text-lg text-slate-600 italic"
               x-text="$store.lang.t(
                   'Cameroon Network was built because these problems are real.',
                   'Cameroon Network a été construit parce que ces problèmes sont réels.'
               )"></p>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 3 — MODULE PREVIEW GRID
         ═══════════════════════════════════════════════════════════════ --}}
    <section id="features" class="hidden md:block py-20 lg:py-28 bg-slate-50" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900"
                    x-text="$store.lang.t('Everything a Cameroonian needs. In one place.', 'Tout ce dont un Camerounais a besoin. En un seul endroit.')"></h2>
                <div class="mt-4 h-1 w-16 bg-cm-green mx-auto rounded-full"></div>
            </div>

            @php
                $modules = [
                    ['name' => 'GoConnect', 'nameFr' => 'GoConnect', 'icon' => '💬', 'desc' => 'Real-time chat rooms for your country and city', 'descFr' => 'Salons de discussion en temps réel pour votre pays et ville', 'live' => true],
                    ['name' => 'Solidarity', 'nameFr' => 'Solidarité', 'icon' => '🤝', 'desc' => 'Community fundraising for those in need', 'descFr' => 'Collectes communautaires pour ceux dans le besoin', 'live' => false],
                    ['name' => 'Marché', 'nameFr' => 'Marché', 'icon' => '🛒', 'desc' => 'Buy and sell within the community', 'descFr' => 'Achetez et vendez au sein de la communauté', 'live' => false],
                    ['name' => 'EasyGoParcel', 'nameFr' => 'EasyGoParcel', 'icon' => '📦', 'desc' => 'Send parcels home with trusted travellers', 'descFr' => 'Envoyez des colis au pays avec des voyageurs de confiance', 'live' => false],
                    ['name' => 'GoRide', 'nameFr' => 'GoRide', 'icon' => '🚗', 'desc' => 'Ride sharing for community events and travel', 'descFr' => 'Covoiturage pour événements et voyages communautaires', 'live' => false],
                    ['name' => 'CamEvents', 'nameFr' => 'CamEvents', 'icon' => '🎉', 'desc' => 'Discover and create community events', 'descFr' => 'Découvrez et créez des événements communautaires', 'live' => false],
                    ['name' => 'KamerNest', 'nameFr' => 'KamerNest', 'icon' => '🏠', 'desc' => 'Find trusted housing from the community', 'descFr' => 'Trouvez un logement de confiance dans la communauté', 'live' => false],
                    ['name' => 'WorkConnect', 'nameFr' => 'WorkConnect', 'icon' => '💼', 'desc' => 'Job listings and career opportunities', 'descFr' => 'Offres d\'emploi et opportunités de carrière', 'live' => false],
                    ['name' => 'KamerEats', 'nameFr' => 'KamerEats', 'icon' => '🍲', 'desc' => 'Find Cameroonian food near you', 'descFr' => 'Trouvez de la nourriture camerounaise près de chez vous', 'live' => false],
                    ['name' => 'KamerSOS', 'nameFr' => 'KamerSOS', 'icon' => '🆘', 'desc' => 'Emergency help from community leaders', 'descFr' => 'Aide d\'urgence des leaders communautaires', 'live' => false],
                    ['name' => 'CamStories', 'nameFr' => 'CamStories', 'icon' => '📸', 'desc' => '24-hour stories from the diaspora', 'descFr' => 'Stories de 24h de la diaspora', 'live' => false],
                ];
            @endphp

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($modules as $module)
                    <div class="relative bg-white rounded-2xl p-6 border border-slate-200 transition-all hover:shadow-lg hover:-translate-y-1 {{ $module['live'] ? '' : 'opacity-75' }}">
                        @if($module['live'])
                            <span class="absolute top-4 right-4 inline-flex items-center gap-1 rounded-full bg-cm-green/10 px-2.5 py-1 text-[10px] font-bold text-cm-green uppercase tracking-wide">
                                <span class="w-1.5 h-1.5 rounded-full bg-cm-green"></span>
                                Live
                            </span>
                        @else
                            <span class="absolute top-4 right-4 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-wide"
                                  x-text="$store.lang.t('Coming Soon', 'Bientôt')">Coming Soon</span>
                        @endif
                        <div class="text-3xl mb-4">{{ $module['icon'] }}</div>
                        <h3 class="text-lg font-bold text-slate-900" x-text="$store.lang.t('{{ addslashes($module['name']) }}', '{{ addslashes($module['nameFr']) }}')">{{ $module['name'] }}</h3>
                        <p class="mt-2 text-sm text-slate-500" x-text="$store.lang.t('{{ addslashes($module['desc']) }}', '{{ addslashes($module['descFr']) }}')">{{ $module['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 4 — THE YARD FEATURE HIGHLIGHT
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="hidden md:block py-20 lg:py-28 bg-white" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                {{-- Left: Copy --}}
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-full bg-cm-green/10 px-4 py-2 text-sm font-semibold text-cm-green">
                        💬 <span x-text="$store.lang.t('GoConnect', 'GoConnect')"></span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-bold text-slate-900"
                        x-text="$store.lang.t('Where your community gathers.', 'Où votre communauté se rassemble.')"></h2>
                    <p class="text-lg text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'GoConnect is your home base. Country-wide and city-specific chat rooms connect you with Cameroonians wherever you are. Share experiences, ask for help, celebrate together.',
                           'GoConnect est votre base. Des salons nationaux et par ville vous connectent avec les Camerounais où que vous soyez. Partagez, demandez de l\'aide, célébrez ensemble.'
                       )"></p>

                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cm-green text-white">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-slate-600" x-text="$store.lang.t('GPS auto-detection — join your country room instantly', 'Détection GPS automatique — rejoignez votre salon pays instantanément')"></p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cm-green text-white">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-slate-600" x-text="$store.lang.t('Private groups for family, friends, and organisations', 'Groupes privés pour famille, amis et organisations')"></p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cm-green text-white">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-slate-600" x-text="$store.lang.t('Direct messages, voice notes, images and more', 'Messages directs, notes vocales, images et plus')"></p>
                        </div>
                    </div>

                    <p class="text-sm font-semibold text-cm-green italic"
                       x-text="$store.lang.t(
                           'Only Cameroonians physically in your country can join your Yard. Real people. Real proximity. Real community.',
                           'Seuls les Camerounais physiquement dans votre pays peuvent rejoindre votre Yard. De vraies personnes. Une vraie proximité. Une vraie communauté.'
                       )"></p>
                </div>

                {{-- Right: Yard UI Mockup --}}
                <div class="relative">
                    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
                        {{-- Room list header --}}
                        <div class="bg-cm-green px-5 py-4">
                            <h3 class="text-white font-bold text-lg">GoConnect</h3>
                            <p class="text-slate-400 text-sm">Your rooms</p>
                        </div>
                        {{-- Room entries --}}
                        <div class="divide-y divide-slate-100">
                            <div class="flex items-center gap-3 px-5 py-4 bg-cm-green/5">
                                <div class="w-12 h-12 rounded-full bg-cm-green/10 flex items-center justify-center text-xl">🇬🇧</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="font-semibold text-slate-900 truncate">UK National Room</p>
                                        <span class="text-xs text-slate-400">2m ago</span>
                                    </div>
                                    <p class="text-sm text-slate-500 truncate">Afi T.: Try Mama Africa in Peckham...</p>
                                </div>
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-cm-green text-[10px] font-bold text-white">3</span>
                            </div>
                            <div class="flex items-center gap-3 px-5 py-4">
                                <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-xl">📍</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="font-semibold text-slate-900 truncate">London Room</p>
                                        <span class="text-xs text-slate-400">15m ago</span>
                                    </div>
                                    <p class="text-sm text-slate-500 truncate">Paul F.: Anyone going to the meetup...</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 px-5 py-4">
                                <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-xl">🔒</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="font-semibold text-slate-900 truncate">Family Group</p>
                                        <span class="text-xs text-slate-400">1h ago</span>
                                    </div>
                                    <p class="text-sm text-slate-500 truncate">Mama: Did you send the money?</p>
                                </div>
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-cm-green text-[10px] font-bold text-white">1</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 5 — SOLIDARITY HIGHLIGHT
         ═══════════════════════════════════════════════════════════════ --}}
    <section id="solidarity" class="hidden md:block py-20 lg:py-28 bg-gradient-to-br from-cm-yellow/10 via-cm-yellow/5 to-white" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                {{-- Left: Solidarity Card Mockup --}}
                <div class="order-2 lg:order-1">
                    <div class="bg-white rounded-2xl shadow-xl border border-cm-yellow/20 overflow-hidden max-w-md mx-auto">
                        <div class="bg-cm-yellow/10 px-6 py-4 border-b border-cm-yellow/20">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">🕊️</span>
                                <div>
                                    <span class="text-xs font-bold text-cm-green uppercase tracking-wide">Solidarity</span>
                                    <span class="text-xs text-slate-500 ml-2">Bereavement</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <h3 class="text-lg font-bold text-slate-900">In Memory of Mama Ngozi Fru</h3>
                            <p class="text-sm text-slate-500">Mother of community member Emmanuel Fru</p>
                            <p class="text-sm text-slate-600 leading-relaxed">"Our brother Emmanuel lost his mother in Bamenda last week. The community stands with him during this difficult time."</p>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-2xl font-bold text-cm-green">£650</span>
                                    <span class="text-sm text-slate-500">of £1,000</span>
                                </div>
                                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-cm-green to-cm-green-light rounded-full transition-all duration-1000" style="width: 65%"></div>
                                </div>
                                <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
                                    <span>65% reached</span>
                                    <span>23 contributors</span>
                                    <span>5 days left</span>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button class="flex-1 rounded-xl bg-cm-green py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light"
                                        x-text="$store.lang.t('Contribute Now', 'Contribuer Maintenant')"></button>
                                <button class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                        x-text="$store.lang.t('View', 'Voir')"></button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Copy --}}
                <div class="order-1 lg:order-2 space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-full bg-cm-yellow/20 px-4 py-2 text-sm font-semibold text-cm-yellow-dark">
                        🤝 <span x-text="$store.lang.t('Solidarity', 'Solidarité')"></span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-bold text-slate-900"
                        x-text="$store.lang.t('Because community shows up for each other.', 'Parce que la communauté se soutient.')"></h2>
                    <p class="text-lg text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'When a member loses someone or faces hardship, the community comes together. Solidarity campaigns are transparent, secure, and powered by the people who care most.',
                           'Quand un membre perd quelqu\'un ou fait face à des difficultés, la communauté se rassemble. Les campagnes de solidarité sont transparentes, sécurisées et portées par ceux qui comptent le plus.'
                       )"></p>

                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="text-xl">🔒</span>
                            <div>
                                <p class="font-semibold text-slate-900" x-text="$store.lang.t('Admin-verified campaigns', 'Campagnes vérifiées par l\'admin')"></p>
                                <p class="text-sm text-slate-500" x-text="$store.lang.t('Every campaign reviewed before going live', 'Chaque campagne examinée avant publication')"></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-xl">📊</span>
                            <div>
                                <p class="font-semibold text-slate-900" x-text="$store.lang.t('Full transparency', 'Transparence totale')"></p>
                                <p class="text-sm text-slate-500" x-text="$store.lang.t('See every contributor and every penny raised', 'Voyez chaque contributeur et chaque centime collecté')"></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-xl">🤖</span>
                            <div>
                                <p class="font-semibold text-slate-900" x-text="$store.lang.t('AI fraud protection', 'Protection IA contre la fraude')"></p>
                                <p class="text-sm text-slate-500" x-text="$store.lang.t('Kamer AI assesses every campaign for risk', 'L\'IA Kamer évalue chaque campagne pour les risques')"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 6 — HOW IT WORKS
         ═══════════════════════════════════════════════════════════════ --}}
    <section id="how-it-works" class="hidden md:block py-20 lg:py-28 bg-white" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900"
                    x-text="$store.lang.t('How It Works', 'Comment Ça Marche')"></h2>
                <div class="mt-4 h-1 w-16 bg-cm-yellow mx-auto rounded-full"></div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
                @php
                    $steps = [
                        ['num' => '01', 'icon' => '👤', 'title' => 'Create your profile', 'titleFr' => 'Créez votre profil', 'desc' => 'Tell us where you\'re from and choose your language', 'descFr' => 'Dites-nous d\'où vous venez et choisissez votre langue'],
                        ['num' => '02', 'icon' => '📍', 'title' => 'We detect where you are', 'titleFr' => 'Nous détectons où vous êtes', 'desc' => 'GPS connects you to your country — your Yard opens instantly', 'descFr' => 'Le GPS vous connecte à votre pays — votre Yard s\'ouvre instantanément'],
                        ['num' => '03', 'icon' => '🤝', 'title' => 'Connect with community', 'titleFr' => 'Connectez-vous', 'desc' => 'Chat with Cameroonians in your city and country', 'descFr' => 'Discutez avec des Camerounais dans votre ville et pays'],
                        ['num' => '04', 'icon' => '🚀', 'title' => 'Access everything', 'titleFr' => 'Accédez à tout', 'desc' => 'Housing, parcels, events, jobs — built for your life abroad', 'descFr' => 'Logement, colis, événements, emplois — conçu pour votre vie à l\'étranger'],
                    ];
                @endphp

                @foreach($steps as $step)
                    <div class="relative text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-cm-green/10 text-3xl mb-6">
                            {{ $step['icon'] }}
                        </div>
                        <span class="absolute top-0 right-1/4 text-5xl font-black text-cm-green/10">{{ $step['num'] }}</span>
                        <h3 class="text-lg font-bold text-slate-900 mb-2"
                            x-text="$store.lang.t('{{ addslashes($step['title']) }}', '{{ addslashes($step['titleFr']) }}')"></h3>
                        <p class="text-sm text-slate-500"
                           x-text="$store.lang.t('{{ addslashes($step['desc']) }}', '{{ addslashes($step['descFr']) }}')"></p>

                        @if(!$loop->last)
                            <div class="hidden lg:block absolute top-10 -right-4 w-8 text-cm-green/20">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 7 — COMMUNITY STATS
         ═══════════════════════════════════════════════════════════════ --}}
    <section id="community" class="hidden md:block py-20 lg:py-28 bg-cm-green" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 text-center">
                @php
                    $stats = [
                        ['value' => max($memberCount, 150), 'label' => 'Members in the UK', 'labelFr' => 'Membres au Royaume-Uni', 'suffix' => '+'],
                        ['value' => max($regionCount, 12), 'label' => 'Regions with active communities', 'labelFr' => 'Régions avec communautés actives', 'suffix' => ''],
                        ['value' => 8, 'label' => 'Solidarity campaigns completed', 'labelFr' => 'Campagnes de solidarité complétées', 'suffix' => ''],
                        ['value' => 2400, 'label' => 'Messages sent in GoConnect', 'labelFr' => 'Messages envoyés dans GoConnect', 'suffix' => '+'],
                    ];
                @endphp

                @foreach($stats as $stat)
                    <div>
                        <p class="text-4xl sm:text-5xl font-extrabold text-white">
                            <span data-count-to="{{ $stat['value'] }}" data-count-duration="2500">0</span>{{ $stat['suffix'] }}
                        </p>
                        <p class="mt-2 text-slate-400 text-sm"
                           x-text="$store.lang.t('{{ addslashes($stat['label']) }}', '{{ addslashes($stat['labelFr']) }}')"></p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 8 — TESTIMONIALS
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="hidden md:block py-20 lg:py-28 bg-white" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900"
                    x-text="$store.lang.t('What Cameroonians in the UK are saying', 'Ce que disent les Camerounais au Royaume-Uni')"></h2>
                <div class="mt-4 h-1 w-16 bg-cm-red mx-auto rounded-full"></div>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $testimonials = [
                        [
                            'name' => 'Afi Tanyi',
                            'city' => 'London',
                            'initial' => 'AT',
                            'color' => 'bg-cm-green',
                            'quote' => 'When I first moved to London, I didn\'t know a single person. GoConnect connected me with 50 Cameroonians in my city within the first week. It\'s like having a piece of home.',
                            'quoteFr' => 'Quand je suis arrivée à Londres, je ne connaissais personne. GoConnect m\'a connectée avec 50 Camerounais dans ma ville en une semaine. C\'est comme avoir un morceau de chez soi.',
                        ],
                        [
                            'name' => 'Emmanuel Fru',
                            'city' => 'Manchester',
                            'initial' => 'EF',
                            'color' => 'bg-cm-red',
                            'quote' => 'When I lost my mother, the community raised £2,000 in 3 days through Solidarity. I never felt alone. This platform saved my family in our darkest moment.',
                            'quoteFr' => 'Quand j\'ai perdu ma mère, la communauté a collecté 2 000£ en 3 jours grâce à la Solidarité. Je ne me suis jamais senti seul. Cette plateforme a sauvé ma famille.',
                        ],
                        [
                            'name' => 'Ngozi Mbah',
                            'city' => 'Birmingham',
                            'initial' => 'NM',
                            'color' => 'bg-cm-yellow-dark',
                            'quote' => 'I found my flat through KamerNest, send packages home through EasyGoParcel, and found my best friend in the Birmingham Room. This app is everything.',
                            'quoteFr' => 'J\'ai trouvé mon appartement sur KamerNest, j\'envoie des colis au pays via EasyGoParcel, et j\'ai trouvé ma meilleure amie dans le Salon de Birmingham. Cette appli est tout.',
                        ],
                    ];
                @endphp

                @foreach($testimonials as $testimonial)
                    <div class="bg-slate-50 rounded-2xl p-8 relative">
                        <svg class="absolute top-6 right-6 w-8 h-8 text-cm-green/10" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151C7.546 6.068 5.983 8.789 5.983 11H10v10H0z"/></svg>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 rounded-full {{ $testimonial['color'] }} flex items-center justify-center text-sm font-bold text-white">{{ $testimonial['initial'] }}</div>
                            <div>
                                <p class="font-bold text-slate-900">{{ $testimonial['name'] }}</p>
                                <p class="text-sm text-slate-500">{{ $testimonial['city'] }}, UK</p>
                            </div>
                        </div>
                        <p class="text-slate-600 leading-relaxed italic"
                           x-text="$store.lang.t('{{ addslashes($testimonial['quote']) }}', '{{ addslashes($testimonial['quoteFr']) }}')"></p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 9 — THE VISION
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="hidden md:block py-20 lg:py-28 bg-slate-900 text-white" data-animate>
        <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="space-y-6">
                    <h2 class="text-3xl sm:text-4xl font-bold"
                        x-text="$store.lang.t('Built for Cameroon. Designed for Africa.', 'Construit pour le Cameroun. Conçu pour l\'Afrique.')"></h2>
                    <p class="text-lg text-slate-300 leading-relaxed"
                       x-text="$store.lang.t(
                           'Cameroon Network is just the beginning. Our vision is to connect every African diaspora community with the tools they need to thrive abroad — while staying connected to home.',
                           'Cameroon Network n\'est que le début. Notre vision est de connecter chaque communauté de la diaspora africaine avec les outils nécessaires pour prospérer à l\'étranger — tout en restant connecté au pays.'
                       )"></p>

                    <div class="flex flex-wrap gap-3">
                        @php $countries = ['🇳🇬 Nigeria', '🇬🇭 Ghana', '🇸🇳 Senegal', '🇨🇮 Côte d\'Ivoire', '🇰🇪 Kenya']; @endphp
                        @foreach($countries as $country)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-300">
                                {{ $country }}
                                <span class="text-[10px] text-cm-yellow font-bold" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
                            </span>
                        @endforeach
                    </div>

                    <p class="text-slate-400 italic"
                       x-text="$store.lang.t('Bringing this to your community? Let\'s talk.', 'Vous voulez ça pour votre communauté ? Parlons-en.')"></p>
                </div>

                {{-- Globe SVG --}}
                <div class="flex items-center justify-center">
                    <div class="relative w-72 h-72">
                        <svg viewBox="0 0 200 200" class="w-full h-full">
                            <circle cx="100" cy="100" r="90" fill="none" stroke="#334155" stroke-width="1"/>
                            <circle cx="100" cy="100" r="70" fill="none" stroke="#334155" stroke-width="0.5"/>
                            <circle cx="100" cy="100" r="50" fill="none" stroke="#334155" stroke-width="0.5"/>
                            {{-- Cameroon dot --}}
                            <circle cx="98" cy="92" r="6" fill="#243a5c" class="animate-pulse-soft"/>
                            <circle cx="98" cy="92" r="3" fill="#FCD116"/>
                            {{-- Nigeria --}}
                            <circle cx="95" cy="96" r="3" fill="#334155" opacity="0.5"/>
                            {{-- Ghana --}}
                            <circle cx="90" cy="98" r="3" fill="#334155" opacity="0.5"/>
                            {{-- Kenya --}}
                            <circle cx="115" cy="98" r="3" fill="#334155" opacity="0.5"/>
                            {{-- Senegal --}}
                            <circle cx="78" cy="90" r="3" fill="#334155" opacity="0.5"/>
                            {{-- Connection lines --}}
                            <line x1="98" y1="92" x2="60" y2="55" stroke="#243a5c" stroke-width="0.5" opacity="0.3"/>
                            <line x1="98" y1="92" x2="140" y2="50" stroke="#243a5c" stroke-width="0.5" opacity="0.3"/>
                            <line x1="98" y1="92" x2="45" y2="75" stroke="#243a5c" stroke-width="0.5" opacity="0.3"/>
                            {{-- Diaspora dots --}}
                            <circle cx="60" cy="55" r="4" fill="#CE1126" opacity="0.6" class="animate-pulse-soft" style="animation-delay: 0.5s"/>
                            <text x="60" y="48" text-anchor="middle" class="text-[6px] fill-slate-400">UK</text>
                            <circle cx="140" cy="50" r="3" fill="#CE1126" opacity="0.4"/>
                            <text x="140" y="44" text-anchor="middle" class="text-[6px] fill-slate-400">DE</text>
                            <circle cx="45" cy="75" r="3" fill="#CE1126" opacity="0.4"/>
                            <text x="45" y="69" text-anchor="middle" class="text-[6px] fill-slate-400">FR</text>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 10 — FINAL CTA
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="hidden md:block py-20 lg:py-28 bg-cm-green" data-animate>
        <div class="mx-auto max-w-4xl px-6 sm:px-10 lg:px-12 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white"
                x-text="$store.lang.t('Your community is already waiting for you.', 'Votre communauté vous attend déjà.')"></h2>
            <div class="mt-8">
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-cm-yellow px-10 py-5 text-lg font-bold text-cm-green-dark shadow-lg shadow-cm-yellow/25 transition-all hover:bg-cm-yellow-light hover:shadow-xl hover:-translate-y-0.5">
                    <span x-text="$store.lang.t('Join Cameroon Network — It\'s Free', 'Rejoignez Cameroon Network — C\'est Gratuit')"></span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            </div>
            <p class="mt-4 text-slate-400"
               x-text="$store.lang.t('No credit card. No fees. Just your community.', 'Pas de carte bancaire. Pas de frais. Juste votre communauté.')"></p>
        </div>
    </section>

    </div>{{-- /HIDDEN SECTIONS --}}

    {{-- ═══════════════════════════════════════════════════════════════
         SECTION 11 — FOOTER (Removed Privacy Policy and Terms of Service)
         ═══════════════════════════════════════════════════════════════ --}}

    {{-- ═══════════════════════════════════════════════════════════════
         MOBILE-ONLY: "Download Our App" bar (directly below hero)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="md:hidden relative z-40 bg-cm-green/95 backdrop-blur border-t border-white/10 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] flex items-center justify-center gap-2 shadow-[0_-4px_18px_rgba(0,0,0,0.25)]">
        {{-- Profile / Login (pinned to extreme left) --}}
        <a href="{{ route('login') }}"
           aria-label="{{ __('Login') }}"
           class="absolute left-4 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/30 text-white transition-colors hover:bg-white/20">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </a>
        <span class="text-white font-semibold text-sm tracking-wide" x-text="$store.lang.t('Download Our App', 'Téléchargez notre app')"></span>
        {{-- Vertical separator --}}
        <span class="h-5 w-px bg-white" aria-hidden="true"></span>
        {{-- Google Play icon --}}
        <a href="#"
           @click.prevent="window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: $store.lang.t('Android app coming soon!', 'App Android bientôt disponible !') } }))"
           aria-label="Google Play"
           class="flex items-center justify-center text-white transition-transform hover:scale-110">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M3.609 1.814 13.792 12 3.61 22.186a.996.996 0 0 1-.61-.92V2.734a1 1 0 0 1 .609-.92z"/>
                <path d="m13.792 12 2.92-2.92 4.06 2.34a1 1 0 0 1 0 1.74l-4.06 2.34L13.792 12z"/>
                <path d="m16.713 9.08-2.92 2.92L3.61 1.814A1 1 0 0 1 4.43 1.7l12.282 7.38z"/>
                <path d="m13.792 12 2.92 2.92L4.43 22.3a1 1 0 0 1-.82-.114L13.792 12z"/>
            </svg>
        </a>
        {{-- Apple App Store icon --}}
        <a href="#"
           @click.prevent="window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: $store.lang.t('iOS app coming soon!', 'App iOS bientôt disponible !') } }))"
           aria-label="App Store"
           class="-ml-1.5 flex items-center justify-center text-white transition-transform hover:scale-110">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.492 0-2.52-1.27-3.439-2.61C3.142 17.43 2 13.95 2 10.68c0-5.25 3.39-8.04 6.73-8.04 1.49 0 2.74.97 3.66.97.88 0 2.28-1.04 3.93-1.04.63 0 2.95.06 4.45 2.22-.12.07-2.62 1.52-2.62 4.54 0 3.55 3.16 4.85 3.16 4.85z"/>
            </svg>
        </a>
    </div>
    </div>

    {{-- Kamer AI floating bubble is provided by the guest layout via @livewire('a-i.kamer-chat') --}}
</x-layouts.guest>
