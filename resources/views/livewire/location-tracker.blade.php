{{--
    Location Tracker — Invisible Livewire component that runs on every authenticated page.

    On init (2 s after page load), Alpine detects the user's GPS / IP location,
    caches the result for 30 minutes, and calls the server only when the position
    has actually changed.  When the *country* changes, a beautiful Cameroon-themed
    toast slides in to invite the user to discover local rooms.
--}}
<div
    x-data="{
        showToast: false,
        newCountry: '',
        newRegion: '',
        progress: 100,
        _interval: null,

        config: @js([
            'knownCountry' => $knownCountry,
            'knownRegion'  => $knownRegion,
            'mode'         => $locationMode,
        ]),

        {{-- ─── Bootstrap ─── --}}
        init() {
            {{-- Small delay so location detection doesn't compete with page paint --}}
            setTimeout(() => this.detect(), 2500);
        },

        {{-- ─── Main detection flow ─── --}}
        async detect() {
            const CACHE_KEY = 'cc_location';
            const CACHE_TTL = 30 * 60 * 1000; {{-- 30 minutes --}}

            try {
                const cached = JSON.parse(sessionStorage.getItem(CACHE_KEY) || 'null');
                if (cached && (Date.now() - cached.ts) < CACHE_TTL) return;
            } catch { /* corrupt cache — re-detect */ }

            try {
                let result = null;

                if (this.config.mode === 'ip') {
                    result = await this.detectByIP();
                } else {
                    result = await this.detectByGPS();
                    if (!result) result = await this.detectByIP(); {{-- GPS denied → graceful IP fallback --}}
                }

                if (!result || !result.country) return;

                {{-- Cache so we don't re-detect on every navigation --}}
                sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ...result, ts: Date.now() }));

                {{-- Push update to the server (Livewire) --}}
                $wire.updateLocation(result.lat, result.lng, result.country, result.region);

                {{-- Country changed? Show toast --}}
                if (this.config.knownCountry && result.country !== this.config.knownCountry) {
                    this.newCountry = result.country;
                    this.newRegion  = result.region;
                    this.showToast  = true;
                    this.startCountdown();
                }
            } catch { /* best-effort — silent fail */ }
        },

        {{-- ─── GPS Detection (Nominatim reverse-geocode) ─── --}}
        async detectByGPS() {
            try {
                const pos = await new Promise((resolve, reject) => {
                    if (!navigator.geolocation) return reject('unavailable');
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        timeout: 10000,
                        maximumAge: 300000 {{-- Accept a 5-min-old cached fix --}}
                    });
                });

                const resp = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&format=json&accept-language=en`
                );
                const geo = await resp.json();

                return {
                    lat:     pos.coords.latitude,
                    lng:     pos.coords.longitude,
                    country: geo.address?.country || '',
                    region:  geo.address?.state || '',
                };
            } catch { return null; }
        },

        {{-- ─── IP-based Detection (ip-api.com) ─── --}}
        async detectByIP() {
            try {
                const resp = await fetch('http://ip-api.com/json/?fields=status,country,regionName,lat,lon');
                const data = await resp.json();
                if (data.status !== 'success') return null;
                return { lat: data.lat, lng: data.lon, country: data.country, region: data.regionName || '' };
            } catch { return null; }
        },

        {{-- ─── Auto-dismiss progress bar (15 s) ─── --}}
        startCountdown() {
            this.progress = 100;
            this._interval = setInterval(() => {
                this.progress -= 0.67;
                if (this.progress <= 0) this.dismiss();
            }, 100);
        },

        dismiss() {
            this.showToast = false;
            if (this._interval) { clearInterval(this._interval); this._interval = null; }
        }
    }"
    class="fixed top-4 right-4 z-[9999] pointer-events-none"
    x-cloak
>
    {{-- ═══════════════════════════════════════════════════════════
         Country-Change Toast Notification
         ═══════════════════════════════════════════════════════════ --}}
    <div
        x-show="showToast"
        x-transition:enter="transform transition ease-out duration-500"
        x-transition:enter-start="translate-x-[120%] opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transform transition ease-in duration-300"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-[120%] opacity-0"
        class="pointer-events-auto w-[340px] rounded-2xl bg-white shadow-2xl shadow-black/10 border border-slate-200/80 overflow-hidden"
    >
        {{-- Cameroon tricolour gradient bar --}}
        <div class="h-1 bg-gradient-to-r from-cm-green via-cm-red to-cm-yellow"></div>

        <div class="p-4">
            {{-- Header row --}}
            <div class="flex items-start gap-3">
                {{-- Pulsing location pin --}}
                <div class="relative mt-0.5 flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-cm-green/10">
                    <svg class="h-5 w-5 text-cm-green" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="absolute -top-0.5 -right-0.5 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cm-green opacity-60"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-cm-green"></span>
                    </span>
                </div>

                {{-- Text --}}
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-bold text-slate-900"
                        x-text="$store.lang.t('New Location Detected', 'Nouvelle Position Détectée')"></h4>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
                        <span x-text="$store.lang.t('Welcome to ', 'Bienvenue à ')"></span>
                        <span class="font-semibold text-cm-green" x-text="newCountry"></span><span x-text="$store.lang.t('!', ' !')"></span>
                        <span x-text="$store.lang.t(
                            ' We found Cameroonians here — discover local rooms and connect.',
                            ' Nous avons trouvé des Camerounais ici — découvrez les salons locaux.'
                        )"></span>
                    </p>
                </div>

                {{-- Close button --}}
                <button @click="dismiss()"
                    class="flex-shrink-0 p-1 -mt-1 -mr-1 rounded-lg text-slate-300 hover:text-slate-500 hover:bg-slate-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- CTA row --}}
            <div class="mt-3 flex items-center gap-2">
                <a href="{{ route('yard') }}"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-cm-green px-3 py-2.5 text-xs font-semibold text-white shadow-sm shadow-cm-green/20 hover:bg-cm-green-dark transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span x-text="$store.lang.t('Discover Rooms', 'Découvrir les Salons')"></span>
                </a>
                <button @click="dismiss()"
                    class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-medium text-slate-500 hover:bg-slate-50 transition-colors"
                    x-text="$store.lang.t('Later', 'Plus tard')">
                </button>
            </div>
        </div>

        {{-- Auto-dismiss progress bar --}}
        <div class="h-0.5 bg-slate-100">
            <div class="h-full bg-cm-green/40 transition-all duration-100 ease-linear"
                 :style="'width:' + progress + '%'"></div>
        </div>
    </div>
</div>
