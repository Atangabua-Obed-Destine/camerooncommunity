@props(['yardMode' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" x-data x-bind:lang="$store.lang.current">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($__siteName ?? 'Cameroon Network') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|dm-sans:400,500,600,700" rel="stylesheet">

    @if($__siteFavicon ?? null)
    <link rel="icon" type="image/png" href="{{ $__siteFavicon }}">
    @else
    {{-- Default: Cameroon flag SVG (vector, scales perfectly in any browser) --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased" x-data="{ sidebarOpen: false }">
    {{-- Fixed Header Wrapper --}}
    <div class="fixed top-0 left-0 right-0 z-50" style="position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: linear-gradient(to right, #1b2d4a 0%, #243a5c 30%, #2e4a6e 60%, #3a5a80 100%)">
        {{-- Location strip (seamless, no border) --}}
        @auth
        <div class="hidden sm:flex h-7 items-center justify-end px-4 lg:px-6"
             x-data="{
                country: @js(auth()->user()->current_country ?? ''),
                region: @js(auth()->user()->current_region ?? ''),
             }"
             x-on:location-changed.window="
                country = $event.detail?.country || $event.detail?.[0]?.country || country;
                region  = $event.detail?.region  || $event.detail?.[0]?.region  || region;
             ">
            <div class="flex items-center gap-1.5 text-[11px] font-medium text-white/70">
                <span>🇨🇲</span>
                <span x-text="(region ? region + ', ' : '') + (country || $store.lang.t('Detecting…', 'Détection…'))"></span>
            </div>
        </div>
        @else
        <div class="hidden sm:flex h-7 items-center justify-end px-4 lg:px-6"
             x-data="{
                loc: localStorage.getItem('guest_location') || '',
                mode: @js(\App\Models\PlatformSetting::getValue('location_detection_mode', 'gps')),
                async detect() {
                    if (this.loc) return;
                    if (this.mode === 'gps' && navigator.geolocation) {
                        try {
                            const pos = await new Promise((res, rej) => navigator.geolocation.getCurrentPosition(res, rej, { timeout: 8000 }));
                            const r = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&format=json&accept-language=en`);
                            const d = await r.json();
                            const a = d.address || {};
                            const parts = [];
                            if (a.state) parts.push(a.state);
                            if (a.country) parts.push(a.country);
                            if (parts.length) {
                                this.loc = parts.join(', ');
                                localStorage.setItem('guest_location', this.loc);
                                return;
                            }
                        } catch(e) { /* fall through to IP */ }
                    }
                    try {
                        const r = await fetch('{{ route("geo.ip") }}');
                        const d = await r.json();
                        if (!d.error) {
                            const parts = [];
                            if (d.region) parts.push(d.region);
                            if (d.country_name) parts.push(d.country_name);
                            this.loc = parts.join(', ') || (d.country_name || '');
                            localStorage.setItem('guest_location', this.loc);
                        }
                    } catch(e) {}
                }
             }"
             x-init="
                detect();
                if (window.Echo) {
                    window.Echo.channel('platform-settings').listen('.setting.updated', (e) => {
                        if (e.key === 'location_detection_mode') {
                            mode = e.value;
                            loc = '';
                            try { localStorage.removeItem('guest_location'); } catch {}
                            detect();
                        }
                    });
                }
             ">
            <div class="flex items-center gap-1.5 text-[11px] font-medium text-white/70">
                <span>🌍</span>
                <span x-text="loc || $store.lang.t('Detecting location…', 'Détection de la localisation…')"></span>
            </div>
        </div>
        @endauth

        {{-- Main navigation (Facebook-style: logo left, icon tabs centered, actions right).
             `overflow-visible` lets the logo extend slightly beyond the 64px nav row
             so we can render it bigger without changing header height. --}}
        <nav class="h-14 sm:h-16 overflow-visible">
            <div class="flex h-full items-center px-3 sm:px-4 lg:px-6 relative overflow-visible">
                {{-- Logo (inline, left). Sized in viewport units with min/max clamps so it
                     scales smoothly between phone & desktop, "bleeding" a bit above/below
                     the 64px bar via negative margins for visual weight. --}}
                <a href="{{ route('home') }}"
                   class="group flex items-center shrink-0 mr-auto -my-2 transition-transform duration-200 ease-out hover:scale-[1.04] active:scale-[0.98]">
                    @if($__siteLogo ?? null)
                    <img src="{{ $__siteLogo }}"
                         alt="{{ $__siteName ?? 'Logo' }}"
                         class="h-12 sm:h-14 md:h-16 lg:h-[72px] xl:h-20 w-auto max-w-[42vw] sm:max-w-none object-contain drop-shadow-[0_2px_6px_rgba(0,0,0,0.35)] transition-all duration-200 group-hover:drop-shadow-[0_3px_10px_rgba(252,209,22,0.45)]">
                    @else
                    <span class="text-3xl sm:text-4xl lg:text-5xl leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.35)]">🇨🇲</span>
                    @endif
                </a>

                {{-- Icon tabs (Facebook-style) — desktop: centered absolute; mobile hidden (rendered as second row below) --}}
                <div class="hidden lg:flex absolute left-1/2 top-0 h-full -translate-x-1/2 items-center gap-1">
                    @include('partials.fb-nav-tabs', ['mode' => 'desktop', 'yardMode' => $yardMode])
                </div>

                {{-- Right-side actions --}}
                <div class="ml-auto flex items-center gap-1">
                    {{-- Kamer AI quick-launch --}}
                    @auth
                    <button type="button"
                            @click="Livewire.dispatch('open-kamer-ai')"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-white/20 text-white/85 transition-colors hover:border-white/40 hover:text-white hover:bg-white/10"
                            :title="$store.lang.t('Ask Kamer AI', 'Demander à Kamer AI')"
                            aria-label="Kamer AI">
                        <span class="text-base leading-none">🤖</span>
                    </button>
                    @endauth

                    {{-- Language Toggle --}}
                    <button @click="$store.lang.toggle()" class="flex items-center gap-1.5 rounded-full border border-white/20 px-3 py-1.5 text-xs font-semibold text-white/80 transition-colors hover:border-white/40 hover:text-white"
                            :title="$store.lang.isEn ? 'Passer en français' : 'Switch to English'">
                        <span x-text="$store.lang.isEn ? 'FR' : 'EN'"></span>
                    </button>

                    {{-- Discover (kept here, smaller) --}}
                    <button type="button"
                            @click="window.dispatchEvent(new CustomEvent('open-discover'))"
                            class="hidden md:inline-block px-3 py-2 rounded-lg text-sm font-bold text-white hover:text-cm-yellow hover:bg-white/10 transition-colors"
                            x-text="$store.lang.t('Discover', 'Découvrir')"></button>

                    {{-- Notifications (real-time bell) --}}
                    @auth
                        <livewire:notifications.notification-bell />
                    @endauth

                    {{-- User Menu --}}
                    @auth
                    <div x-data="{ open: false }" class="relative ml-1">
                        <button @click="open = !open" class="flex items-center gap-2 rounded-full p-1 hover:bg-white/10 transition-colors">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-cm-yellow text-sm font-bold text-cm-green">
                                {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <svg class="hidden sm:block h-4 w-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" x-text="$store.lang.t('Profile', 'Profil')"></a>
                            @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('admin'))
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" x-text="$store.lang.t('Admin Panel', 'Panneau admin')"></a>
                            @endif
                            <hr class="my-1 border-slate-100">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-cm-red hover:bg-red-50" x-text="$store.lang.t('Logout', 'Déconnexion')"></button>
                            </form>
                        </div>
                    </div>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- Mobile icon tab row (its own line under the logo/actions, like Facebook mobile).
             No divider border: keeps the header feeling like one continuous surface. --}}
        <nav class="lg:hidden">
            <div class="flex items-stretch justify-around h-10 px-1">
                @include('partials.fb-nav-tabs', ['mode' => 'mobile', 'yardMode' => $yardMode])
            </div>
        </nav>
    </div>{{-- /Fixed Header Wrapper --}}

    {{-- Main Content --}}
    <main class="pt-[96px] lg:pt-[92px]">
        {{ $slot }}
    </main>

    {{-- Location Tracker (silent — detects country changes, shows toast) --}}
    @auth
        @livewire('location-tracker')
    @endauth

    {{-- Kamer AI Assistant (mounted on every authenticated page so the sidebar button works in The Yard too).
         The floating bubble is hidden in yardMode (passed down to the component) because The Yard already
         exposes Kamer AI through its dedicated sidebar icon, and the bubble overlaps the chatroom UI. --}}
    @auth
        @livewire('a-i.kamer-chat', ['hideBubble' => $yardMode])
    @endauth

    {{-- Discover modal — teaser of live + upcoming KAMER features --}}
    @auth
        @include('partials.discover-modal')
    @endauth

    {{-- Real-time connection request / accept notifier (toast + chime + confetti) --}}
    <x-connection-notifier />

    @livewireScripts
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status }) => {
                    if (status === 419) {
                        window.location.reload();
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
