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
        prompt: { country: '', region: '', activeCountry: '', activeRegion: '', isCountryChange: true },
        progress: 100,
        _interval: null,

        config: @js([
            'knownCountry'  => $knownCountry,
            'knownRegion'   => $knownRegion,
            'activeCountry' => $activeCountry,
            'activeRegion'  => $activeRegion,
            'mode'          => $locationMode,
        ]),

        {{-- ─── Bootstrap ─── --}}
        init() {
            {{-- Small delay so location detection doesn't compete with page paint --}}
            setTimeout(() => this.detect(), 2500);

            {{-- Server tells us a switch should be offered --}}
            window.addEventListener('location-switch-prompt', (e) => {
                const d = e.detail?.[0] ?? e.detail ?? {};
                this.prompt = {
                    country:        d.detectedCountry || '',
                    region:         d.detectedRegion || '',
                    activeCountry:  d.activeCountry || '',
                    activeRegion:   d.activeRegion || '',
                    isCountryChange: !! d.isCountryChange,
                };
                this.showToast = true;
                this.startCountdown();
            });

            {{-- Switch completed — show a small confirmation flash --}}
            window.addEventListener('location-switch-completed', () => {
                this.dismiss();
            });
        },

        {{-- ─── Main detection flow ─── --}}
        async detect() {
            const CACHE_KEY = 'cc_location';
            const CACHE_TTL = 30 * 60 * 1000; {{-- 30 minutes --}}

            {{-- IP mode is meant for VPN/testing — always re-detect so a
                 VPN switch is reflected immediately. In GPS mode we still
                 honour the cache, but invalidate it when the public IP
                 has changed since the last detection. --}}
            let cached = null;
            try {
                cached = JSON.parse(sessionStorage.getItem(CACHE_KEY) || 'null');
            } catch { cached = null; }

            if (this.config.mode !== 'ip' && cached && (Date.now() - cached.ts) < CACHE_TTL) {
                {{-- Cheap IP check to bust the cache when network changed (VPN on/off) --}}
                try {
                    const ipResp = await fetch('http://ip-api.com/json/?fields=status,query');
                    const ipData = await ipResp.json();
                    if (ipData.status === 'success' && cached.ip && ipData.query === cached.ip) {
                        return; {{-- Same IP, cached result still valid --}}
                    }
                } catch { return; /* network down — keep cached result */ }
            }

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

                {{-- Push update to the server (Livewire). The server decides
                     whether to fire the switch prompt event. --}}
                $wire.updateLocation(result.lat, result.lng, result.country, result.region);
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
                const resp = await fetch('http://ip-api.com/json/?fields=status,country,regionName,lat,lon,query');
                const data = await resp.json();
                if (data.status !== 'success') return null;
                return { lat: data.lat, lng: data.lon, country: data.country, region: data.regionName || '', ip: data.query || '' };
            } catch { return null; }
        },

        {{-- ─── Auto-dismiss progress bar (20 s) ─── --}}
        startCountdown() {
            if (this._interval) clearInterval(this._interval);
            this.progress = 100;
            this._interval = setInterval(() => {
                this.progress -= 0.5; {{-- 20 s total --}}
                if (this.progress <= 0) this.dismiss();
            }, 100);
        },

        confirmSwitch() {
            const c = this.prompt.country;
            const r = this.prompt.region || '';
            this.dismiss();
            $wire.confirmSwitch(c, r);
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
         Location Switch Prompt
         Triggered server-side via `location-switch-prompt` event when the
         detected location differs from the user's `active_*` location.
         ═══════════════════════════════════════════════════════════ --}}
    <div
        x-show="showToast"
        x-transition:enter="transform transition ease-out duration-500"
        x-transition:enter-start="translate-x-[120%] opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transform transition ease-in duration-300"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-[120%] opacity-0"
        class="pointer-events-auto w-[360px] rounded-2xl bg-white shadow-2xl shadow-black/10 border border-slate-200/80 overflow-hidden"
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
                    <h4 class="text-sm font-bold text-slate-900">
                        <template x-if="prompt.isCountryChange">
                            <span x-text="$store.lang.t('You\u2019ve moved to a new country', 'Vous avez changé de pays')"></span>
                        </template>
                        <template x-if="!prompt.isCountryChange">
                            <span x-text="$store.lang.t('You\u2019re in a new region', 'Vous êtes dans une nouvelle région')"></span>
                        </template>
                    </h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                        <span x-text="$store.lang.t('We detected you in ', 'Nous vous avons détecté à ')"></span>
                        <span class="font-semibold text-cm-green">
                            <span x-text="prompt.region"></span><template x-if="prompt.region && prompt.country">, </template><span x-text="prompt.country"></span>
                        </span>.
                        <br>
                        <span class="text-slate-500">
                            <template x-if="prompt.isCountryChange">
                                <span x-text="$store.lang.t('Switch your active location? Rooms from ', 'Changer votre lieu actif ? Les salons de ')"></span>
                            </template>
                            <template x-if="!prompt.isCountryChange">
                                <span x-text="$store.lang.t('Switch active region? Other regional rooms in ', 'Changer de région active ? Les autres salons régionaux du ')"></span>
                            </template>
                            <span class="font-medium text-slate-700" x-text="prompt.activeCountry"></span>
                            <template x-if="!prompt.isCountryChange && prompt.activeRegion">
                                <span> (<span x-text="prompt.activeRegion"></span>)</span>
                            </template>
                            <span x-text="$store.lang.t(' will be archived until you return. Your private chats and groups stay open.', ' seront archivés jusqu\u2019à votre retour. Vos discussions privées et groupes restent ouverts.')"></span>
                        </span>
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
                <button @click="confirmSwitch()"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-cm-green px-3 py-2.5 text-xs font-semibold text-white shadow-sm shadow-cm-green/20 hover:bg-cm-green-dark transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7l4-4m0 0l4 4m-4-4v18"/>
                    </svg>
                    <span x-text="$store.lang.t('Switch location', 'Changer de lieu')"></span>
                </button>
                <button @click="dismiss()"
                    class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-medium text-slate-500 hover:bg-slate-50 transition-colors"
                    x-text="$store.lang.t('Stay here', 'Rester ici')">
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
