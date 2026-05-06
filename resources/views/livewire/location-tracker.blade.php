{{--
    Location Tracker — Invisible Livewire component that runs on every authenticated page.

    On init (2 s after page load), Alpine detects the user's GPS / IP location,
    caches the result for 30 minutes, and calls the server only when the position
    has actually changed.  When the *country* changes, a beautiful Cameroon-themed
    toast slides in to invite the user to discover local rooms.
--}}
<div
    x-data
    id="location-toast-root"
    data-switch-url="{{ route('location.switch') }}"
    class="fixed top-4 right-4 z-[9999] pointer-events-none"
    x-cloak
>
    {{-- ═══════════════════════════════════════════════════════════
         Location Switch Prompt
         Triggered server-side via `location-switch-prompt` event when the
         detected location differs from the user's `active_*` location.
         ═══════════════════════════════════════════════════════════ --}}
    <template x-if="$store.locationToast?.showToast">
        <div
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
                        <template x-if="$store.locationToast?.prompt?.isCountryChange">
                            <span x-text="$store.lang.t('You\u2019ve moved to a new country', 'Vous avez changé de pays')"></span>
                        </template>
                        <template x-if="!$store.locationToast?.prompt?.isCountryChange">
                            <span x-text="$store.lang.t('You\u2019re in a new region', 'Vous êtes dans une nouvelle région')"></span>
                        </template>
                    </h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                        <span x-text="$store.lang.t('We detected you in ', 'Nous vous avons détecté à ')"></span>
                        <span class="font-semibold text-cm-green">
                            <span x-text="$store.locationToast?.prompt?.region"></span><template x-if="$store.locationToast?.prompt?.region && $store.locationToast?.prompt?.country">, </template><span x-text="$store.locationToast?.prompt?.country"></span>
                        </span>.
                        <br>
                        <span class="text-slate-500">
                            <template x-if="$store.locationToast?.prompt?.isCountryChange">
                                <span x-text="$store.lang.t('Switch your active location? Rooms from ', 'Changer votre lieu actif ? Les salons de ')"></span>
                            </template>
                            <template x-if="!$store.locationToast?.prompt?.isCountryChange">
                                <span x-text="$store.lang.t('Switch active region? Other regional rooms in ', 'Changer de région active ? Les autres salons régionaux du ')"></span>
                            </template>
                            <span class="font-medium text-slate-700" x-text="$store.locationToast?.prompt?.activeCountry"></span>
                            <template x-if="!$store.locationToast?.prompt?.isCountryChange && $store.locationToast?.prompt?.activeRegion">
                                <span> (<span x-text="$store.locationToast?.prompt?.activeRegion"></span>)</span>
                            </template>
                            <span x-text="$store.lang.t(' will be archived until you return. Your private chats and groups stay open.', ' seront archivés jusqu\u2019à votre retour. Vos discussions privées et groupes restent ouverts.')"></span>
                        </span>
                    </p>
                </div>

                {{-- Close button --}}
                <button @click="$dispatch('location-dismiss')"
                    class="flex-shrink-0 p-1 -mt-1 -mr-1 rounded-lg text-slate-300 hover:text-slate-500 hover:bg-slate-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- CTA row --}}
            <div class="mt-3 flex items-center gap-2">
                <button @click="$dispatch('location-confirm')"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-cm-green px-3 py-2.5 text-xs font-semibold text-white shadow-sm shadow-cm-green/20 hover:bg-cm-green-dark transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7l4-4m0 0l4 4m-4-4v18"/>
                    </svg>
                    <span x-text="$store.lang.t('Switch location', 'Changer de lieu')"></span>
                </button>
                <button @click="$dispatch('location-dismiss')"
                    class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-medium text-slate-500 hover:bg-slate-50 transition-colors"
                    x-text="$store.lang.t('Stay here', 'Rester ici')">
                </button>
            </div>
        </div>

        {{-- Auto-dismiss progress bar --}}
        <div class="h-0.5 bg-slate-100">
            <div class="h-full bg-cm-green/40 transition-all duration-100 ease-linear"
                 :style="'width:' + ($store.locationToast?.progress ?? 100) + '%'"></div>
        </div>
        </div>
    </template>
</div>

<script>
    {{-- Wait for Alpine to load before initializing store --}}
    document.addEventListener('alpine:init', () => {
        Alpine.store('locationToast', {
            showToast: false,
            prompt: {
                country: '',
                region: '',
                activeCountry: '',
                activeRegion: '',
                isCountryChange: true
            },
            progress: 100
        });
        console.log('[LocationTracker] ✅ Alpine store initialized via alpine:init');
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const config = @js([
            'knownCountry'  => $knownCountry,
            'knownRegion'   => $knownRegion,
            'activeCountry' => $activeCountry,
            'activeRegion'  => $activeRegion,
            'mode'          => $locationMode,
        ]);

        let _reDetectInterval = null;
        let _lastVisibilityDetect = 0;
        const CACHE_KEY = 'cc_location';
        const CACHE_TTL = 30 * 60 * 1000;

        console.log('[LocationTracker] Initialized with mode:', config.mode);

        const detectByGPS = async () => {
            try {
                const pos = await new Promise((resolve, reject) => {
                    if (!navigator.geolocation) return reject('unavailable');
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        timeout: 10000,
                        maximumAge: 300000
                    });
                });
                const resp = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&format=json&accept-language=en`
                );
                const geo = await resp.json();
                const addr = geo.address || {};
                const country = addr.country || '';
                let region = addr.state || '';
                
                // For UK, map the state to ITL region (or keep as-is if already an ITL region)
                // Otherwise, don't send city — only country and region
                return {
                    lat:     pos.coords.latitude,
                    lng:     pos.coords.longitude,
                    country: country,
                    region:  region,
                };
            } catch { return null; }
        };

        const detectByIP = async () => {
            try {
                const resp = await fetch('https://ipapi.co/json/', { timeout: 5000 });
                if (!resp.ok) return null;
                const data = await resp.json();
                if (data.error) return null;
                return {
                    lat: data.latitude,
                    lng: data.longitude,
                    country: data.country_name || '',
                    region: data.region || '',
                    ip: data.ip || ''
                };
            } catch (e) {
                console.debug('IP detection failed:', e);
                return null;
            }
        };

        const detect = async () => {
            console.log('[LocationTracker] Current mode:', config.mode);
            
            let gpsDenied = false;
            try {
                if (navigator.permissions?.query) {
                    const perm = await navigator.permissions.query({ name: 'geolocation' });
                    console.log('[LocationTracker] GPS permission state:', perm.state);
                    gpsDenied = (perm.state === 'denied');
                }
            } catch (e) {
                console.debug('[LocationTracker] Permission query failed:', e.message);
            }

            if (gpsDenied) {
                console.log('[LocationTracker] GPS permission denied, using IP detection');
                try { sessionStorage.removeItem(CACHE_KEY); } catch {}
            }

            let cached = null;
            try {
                cached = JSON.parse(sessionStorage.getItem(CACHE_KEY) || 'null');
            } catch { cached = null; }

            const expectedSource = (config.mode === 'ip' || gpsDenied) ? 'ip' : 'gps';
            
            if (cached) {
                const cacheAge = (Date.now() - cached.ts);
                const isCacheFresh = cacheAge < CACHE_TTL;
                const sourceMatches = cached.source === expectedSource;
                console.log(`[LocationTracker] Cache exists (age: ${Math.round(cacheAge/1000)}s, fresh: ${isCacheFresh}, source: ${cached.source || 'unknown'}, expected: ${expectedSource}, match: ${sourceMatches})`);
                
                if (!sourceMatches) {
                    console.log('[LocationTracker] Cache source mismatch, invalidating...');
                    try { sessionStorage.removeItem(CACHE_KEY); } catch {}
                    cached = null;
                }
            }

            if (!gpsDenied && config.mode !== 'ip' && cached && (Date.now() - cached.ts) < CACHE_TTL) {
                console.log('[LocationTracker] Using cached location (mode is gps, cache is fresh):', cached);
                return;
            }

            try {
                let result = null;
                if (config.mode === 'ip' || gpsDenied) {
                    console.log('[LocationTracker] Detecting via IP... (mode=ip OR gpsDenied)');
                    result = await detectByIP();
                } else {
                    console.log('[LocationTracker] Detecting via GPS... (mode=gps)');
                    result = await detectByGPS();
                    if (!result) {
                        console.log('[LocationTracker] GPS failed, falling back to IP...');
                        result = await detectByIP();
                    }
                }

                if (!result || !result.country) {
                    console.log('[LocationTracker] Detection failed or no country');
                    return;
                }
                
                console.log('[LocationTracker] Detected location:', result);
                sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ...result, ts: Date.now(), source: expectedSource }));
                {{-- Send location update to server via API endpoint (country + region only, NO city) --}}
                fetch('{{ route("location.update") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        lat: result.lat,
                        lng: result.lng,
                        country: result.country,
                        region: result.region,
                        city: ''
                    })
                }).then(resp => resp.json())
                  .then(data => {
                      console.log('[LocationTracker] Server response:', data);
                      // If the server detected a location change, dispatch the prompt event
                      if (data.event === 'location-switch-prompt') {
                          console.log('[LocationTracker] Dispatching location-switch-prompt event');
                          window.dispatchEvent(new CustomEvent('location-switch-prompt', {
                              detail: [data.data]
                          }));
                      }
                      // Auto-adopted a sub-region (e.g. user was on UK only,
                      // we just resolved them to "London"). Reload so Livewire
                      // computed properties (suggested rooms, etc.) refresh.
                      if (data.autoAdoptedRegion) {
                          console.log('[LocationTracker] Auto-adopted region:', data.autoAdoptedRegion, '— reloading');
                          window.location.reload();
                      }
                  })
                  .catch(e => {
                    console.error('[LocationTracker] Location update failed:', e);
                });
            } catch (e) {
                console.error('[LocationTracker] Unexpected error:', e);
            }
        };

        {{-- Boot detection with delay --}}
        setTimeout(() => {
            console.log('[LocationTracker] Starting location detection...');
            detect();
        }, 2500);
        _reDetectInterval = setInterval(() => {
            console.log('[LocationTracker] Re-detecting location...');
            detect();
        }, 10 * 60 * 1000);

        {{-- Re-detect on tab visibility --}}
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible') return;
            const now = Date.now();
            if (now - _lastVisibilityDetect < 60_000) return;
            _lastVisibilityDetect = now;
            detect();
        });

        {{-- Echo updates --}}
        const checkEchoStatus = () => {
            if (!window.Echo) {
                console.warn('[LocationTracker] ❌ window.Echo NOT available - real-time updates disabled');
                return false;
            }
            
            const connector = window.Echo.connector;
            const pusher = connector?.pusher;
            const state = pusher?.connection?.state || 'unknown';
            
            console.log(`[LocationTracker] Echo connection state: ${state}`);
            
            if (pusher?.connection) {
                pusher.connection.bind('state_change', (states) => {
                    console.log(`[LocationTracker] Echo state changed: ${states.previous} → ${states.current}`);
                });
                pusher.connection.bind('error', (err) => {
                    console.error('[LocationTracker] ❌ Echo connection error:', err);
                });
                pusher.connection.bind('connected', () => {
                    console.log('[LocationTracker] ✅ Echo connected successfully');
                });
                pusher.connection.bind('disconnected', () => {
                    console.warn('[LocationTracker] ⚠️ Echo disconnected');
                });
            }
            
            return true;
        };
        
        if (checkEchoStatus()) {
            const channel = window.Echo.channel('platform-settings');
            
            channel.subscribed(() => {
                console.log('[LocationTracker] ✅ Subscribed to platform-settings channel');
            });
            
            channel.error((err) => {
                console.error('[LocationTracker] ❌ Channel subscription error:', err);
            });
            
            channel.listen('.setting.updated', (e) => {
                console.log('[LocationTracker] 📡 Setting update received:', e);
                if (e.key === 'location_detection_mode') {
                    console.log('[LocationTracker] Mode change detected via Echo:', e.value);
                    config.mode = e.value;
                    try { 
                        sessionStorage.removeItem(CACHE_KEY); 
                        console.log('[LocationTracker] Cache cleared, re-detecting with new mode...');
                    } catch {}
                    detect();
                }
            });
        }

        {{-- Setup location prompt listener that updates Alpine component --}}
        let countdownInterval = null;

        const startCountdown = () => {
            if (countdownInterval) clearInterval(countdownInterval);
            if (typeof Alpine === 'undefined') {
                console.warn('[LocationTracker] Alpine not available for countdown');
                return;
            }
            
            const toastEl = document.querySelector('[x-data*="showToast"]');
            if (!toastEl) return;
            
            const store = Alpine.store('locationToast');
            if (!store) return;
            
            store.progress = 100;
            countdownInterval = setInterval(() => {
                store.progress -= 0.5;
                if (store.progress <= 0) {
                    if (countdownInterval) clearInterval(countdownInterval);
                    store.showToast = false;
                }
            }, 100);
        };

        window.addEventListener('location-switch-prompt', (e) => {
            console.log('[LocationTracker] location-switch-prompt event received:', e.detail);
            if (typeof Alpine === 'undefined') {
                console.warn('[LocationTracker] Alpine not available yet');
                return;
            }
            
            const d = e.detail?.[0] ?? e.detail ?? {};
            const store = Alpine.store('locationToast');
            
            if (store) {
                console.log('[LocationTracker] Updating Alpine store with prompt data');
                store.prompt = {
                    country:        d.detectedCountry || '',
                    region:         d.detectedRegion || '',
                    activeCountry:  d.activeCountry || '',
                    activeRegion:   d.activeRegion || '',
                    isCountryChange: !! d.isCountryChange,
                };
                store.showToast = true;
                console.log('[LocationTracker] ✅ Toast shown');
                startCountdown();
            } else {
                console.warn('[LocationTracker] ❌ Alpine store not found');
            }
        });

        {{-- Handle location confirm event --}}
        window.addEventListener('location-confirm', () => {
            if (typeof Alpine === 'undefined') return;
            
            const store = Alpine.store('locationToast');
            if (!store) return;
            
            const c = store.prompt.country;
            const r = store.prompt.region || '';
            store.showToast = false;
            if (countdownInterval) clearInterval(countdownInterval);
            
            const toastEl = document.getElementById('location-toast-root');
            const switchUrl = toastEl?.dataset.switchUrl;
            if (!switchUrl) {
                console.warn('[LocationTracker] Switch URL not found');
                return;
            }
            
            console.log('[LocationTracker] Switching active location to:', c, r);
            fetch(switchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ country: c, region: r })
            }).then(resp => resp.json())
              .then(data => {
                  console.log('[LocationTracker] Switch response:', data);
                  // Reload page so the header / rooms reflect the new active location
                  window.location.reload();
              })
              .catch(e => {
                console.error('[LocationTracker] Location switch failed:', e);
            });
        });

        {{-- Handle location dismiss event --}}
        window.addEventListener('location-dismiss', () => {
            if (countdownInterval) clearInterval(countdownInterval);
            if (typeof Alpine === 'undefined') return;
            
            const store = Alpine.store('locationToast');
            if (store) {
                store.showToast = false;
            }
        });
    });
</script>
