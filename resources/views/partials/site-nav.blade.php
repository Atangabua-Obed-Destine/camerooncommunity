{{-- ═══════════════════════════════════════════════════════════════
     STICKY NAVBAR (shared across landing + legal pages)
     Accepts:
       $forceScrolled    (bool) — start in white "scrolled" state always.
       $staysTransparent (bool) — never switch to white, even on scroll.
     ═══════════════════════════════════════════════════════════════ --}}
@php($forceScrolled = $forceScrolled ?? false)
@php($staysTransparent = $staysTransparent ?? false)
<nav x-data="{ scrolled: @js($forceScrolled), mobileOpen: false }"
     @if(!$forceScrolled && !$staysTransparent) @scroll.window="scrolled = (window.scrollY > 40)" @endif
     :class="scrolled ? 'bg-white/95 backdrop-blur shadow-sm' : 'bg-transparent'"
     class="fixed top-0 inset-x-0 z-50 transition-all duration-300 pointer-events-auto">
    {{-- Logo — absolutely positioned, centered across full header height --}}
    <a href="{{ route('home') }}" class="absolute left-6 sm:left-10 lg:left-12 top-1/2 -translate-y-1/2 z-10 flex items-center">
        @if($__siteLogo ?? null)
        <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Cameroon Network' }}" class="h-[120px] object-contain">
        @else
        <span class="text-5xl">🇨🇲</span>
        @endif
    </a>

    {{-- Location strip --}}
    @auth
    <div class="hidden sm:flex h-7 items-center justify-end px-6 sm:px-10 lg:px-12 transition-colors duration-300"
         :class="scrolled ? 'text-slate-500' : 'text-white/70'"
         x-data="{
            country: @js(auth()->user()->current_country ?? ''),
            region: @js(auth()->user()->current_region ?? ''),
         }"
         x-on:location-changed.window="
            country = $event.detail?.country || $event.detail?.[0]?.country || country;
            region  = $event.detail?.region  || $event.detail?.[0]?.region  || region;
         ">
        <div class="flex items-center gap-1.5 text-[11px] font-medium">
            <span>🇨🇲</span>
            <span x-text="(region ? region + ', ' : '') + (country || $store.lang.t('Detecting…', 'Détection…'))"></span>
        </div>
    </div>
    @else
    <div class="hidden sm:flex h-7 items-center justify-end px-6 sm:px-10 lg:px-12 transition-colors duration-300"
         :class="scrolled ? 'text-slate-500' : 'text-white/70'"
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
        <div class="flex items-center gap-1.5 text-[11px] font-medium">
            <span>🌍</span>
            <span x-text="loc || $store.lang.t('Detecting location…', 'Détection de la localisation…')"></span>
        </div>
    </div>
    @endauth

    <div class="mx-auto max-w-[1440px] px-6 sm:px-10 lg:px-12 flex items-center justify-between h-16">
        {{-- Spacer for logo --}}
        <div class="shrink-0 w-48"></div>

        {{-- Desktop Links --}}
        <div class="hidden md:flex items-center gap-6 text-sm font-bold">
            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-features-modal'))"
                    :class="scrolled ? 'text-slate-800 hover:text-cm-green' : 'text-white hover:text-cm-yellow'" class="transition-colors drop-shadow-sm cursor-pointer"
                    x-text="$store.lang.t('Features', 'Fonctionnalités')"></button>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-how-it-works-modal'))"
                    :class="scrolled ? 'text-slate-800 hover:text-cm-green' : 'text-white hover:text-cm-yellow'" class="transition-colors drop-shadow-sm cursor-pointer"
                    x-text="$store.lang.t('How It Works', 'Comment Ça Marche')"></button>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-solidarity-modal'))"
                    :class="scrolled ? 'text-slate-800 hover:text-cm-green' : 'text-white hover:text-cm-yellow'" class="transition-colors drop-shadow-sm cursor-pointer"
                    x-text="$store.lang.t('Solidarity', 'Solidarité')"></button>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-community-modal'))"
                    :class="scrolled ? 'text-slate-800 hover:text-cm-green' : 'text-white hover:text-cm-yellow'" class="transition-colors drop-shadow-sm cursor-pointer"
                    x-text="$store.lang.t('Community', 'Communauté')"></button>

            {{-- Language Toggle --}}
            <button @click="$store.lang.toggle()" class="flex items-center gap-1 rounded-full px-3 py-1 border transition-colors text-xs font-bold"
                    :class="scrolled ? 'border-slate-300 text-slate-600 hover:bg-slate-50' : 'border-white/30 text-white hover:bg-white/10'">
                <span x-text="$store.lang.isEn ? 'FR' : 'EN'"></span>
            </button>

            @auth
                <a href="{{ route('yard') }}" class="rounded-full bg-cm-green px-5 py-2 text-white font-bold text-sm hover:bg-cm-green-light transition-colors"
                   x-text="$store.lang.t('Dashboard', 'Tableau de bord')">Dashboard</a>
            @else
                <a href="{{ route('login') }}" :class="scrolled ? 'text-cm-green hover:underline' : 'text-white hover:underline'" class="transition-colors font-bold drop-shadow-sm"
                   x-text="$store.lang.t('Sign In', 'Connexion')">Sign In</a>
                <a href="{{ route('register') }}" class="rounded-full bg-cm-yellow px-5 py-2 text-cm-green-dark font-bold text-sm hover:bg-cm-yellow/90 transition-colors shadow-sm"
                   x-text="$store.lang.t('Join Free', 'Rejoindre')">Join Free</a>
            @endauth
        </div>

        {{-- Mobile Menu Toggle --}}
        <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2" :class="scrolled ? 'text-slate-700' : 'text-white'">
            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Mobile Menu --}}
    <div x-show="mobileOpen" x-cloak x-transition class="md:hidden bg-white border-t border-slate-100 shadow-lg">
        <div class="px-4 py-4 space-y-3 text-sm font-medium">
            <button type="button" @click="mobileOpen = false; window.dispatchEvent(new CustomEvent('open-features-modal'))" class="block w-full text-left text-slate-700 hover:text-cm-green" x-text="$store.lang.t('Features', 'Fonctionnalités')"></button>
            <button type="button" @click="mobileOpen = false; window.dispatchEvent(new CustomEvent('open-how-it-works-modal'))" class="block w-full text-left text-slate-700 hover:text-cm-green" x-text="$store.lang.t('How It Works', 'Comment Ça Marche')"></button>
            <button type="button" @click="mobileOpen = false; window.dispatchEvent(new CustomEvent('open-solidarity-modal'))" class="block w-full text-left text-slate-700 hover:text-cm-green" x-text="$store.lang.t('Solidarity', 'Solidarité')"></button>
            <button type="button" @click="mobileOpen = false; window.dispatchEvent(new CustomEvent('open-community-modal'))" class="block w-full text-left text-slate-700 hover:text-cm-green" x-text="$store.lang.t('Community', 'Communauté')"></button>
            <hr class="border-slate-100">
            <button @click="$store.lang.toggle()" class="flex items-center gap-2 text-slate-600">
                🌐 <span x-text="$store.lang.isEn ? 'Français' : 'English'"></span>
            </button>
            @auth
                <a href="{{ route('yard') }}" class="block w-full text-center rounded-xl bg-cm-green py-3 text-white font-bold hover:bg-cm-green-light"
                   x-text="$store.lang.t('Dashboard', 'Tableau de bord')">Dashboard</a>
            @else
                <div class="flex gap-3">
                    <a href="{{ route('login') }}" class="flex-1 text-center rounded-xl border border-slate-300 py-3 text-slate-700 font-bold hover:bg-slate-50"
                       x-text="$store.lang.t('Sign In', 'Connexion')">Sign In</a>
                    <a href="{{ route('register') }}" class="flex-1 text-center rounded-xl bg-cm-green py-3 text-white font-bold hover:bg-cm-green-light"
                       x-text="$store.lang.t('Join Free', 'Rejoindre')">Join Free</a>
                </div>
            @endauth
        </div>
    </div>
</nav>

@include('partials.site-nav-modals')
