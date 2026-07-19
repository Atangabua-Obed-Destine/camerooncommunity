{{-- Marketplace sidebar (FB-style) — light theme always --}}
@php
    $mpLang = app()->getLocale();
    $activeRoute = \Illuminate\Support\Facades\Route::currentRouteName();

    // Lightweight badge: count of "new since last seen" across this user's saved searches.
    // Kept inline (one cached query per request) so the sidebar partial works on any page.
    $savedSearchNewCount = 0;
    if (auth()->check()) {
        $savedSearchNewCount = \Illuminate\Support\Facades\Cache::remember(
            'mp:ss-new:' . auth()->id(),
            now()->addMinutes(2),
            function () {
                $rows = \App\Models\MarketplaceSavedSearch::where('user_id', auth()->id())->get();
                $total = 0;
                foreach ($rows as $r) {
                    $filters = is_array($r->filters) ? $r->filters : [];
                    $since = $r->last_notified_at ?? $r->created_at;
                    $total += \App\Support\MarketplaceQueryBuilder::build($filters)
                        ->where('published_at', '>', $since)
                        ->count();
                }
                return $total;
            }
        );
    }

    // Unread marketplace inbox count (origin='marketplace' DM rooms).
    $mpInboxUnread = 0;
    if (auth()->check()) {
        $mpInboxUnread = \Illuminate\Support\Facades\Cache::remember(
            'mp:inbox-unread:' . auth()->id(),
            60,
            function () {
                $me = auth()->id();
                $members = \App\Models\YardRoomMember::where('user_id', $me)
                    ->whereHas('room', fn ($q) => $q->where('origin', 'marketplace'))
                    ->get(['room_id', 'last_read_at']);
                $total = 0;
                foreach ($members as $m) {
                    $total += \App\Models\YardMessage::where('room_id', $m->room_id)
                        ->where('user_id', '!=', $me)
                        ->where('is_deleted', false)
                        ->when($m->last_read_at, fn ($q, $lr) => $q->where('created_at', '>', $lr))
                        ->count();
                }
                return $total;
            }
        );
    }
@endphp

<div class="space-y-3">

    {{-- ─── Title + settings ─── --}}
    <div class="flex items-center justify-between px-1">
        <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">
            <span x-data x-text="$store.lang.t('GoMarket','GoMarket')"></span>
        </h2>
        <button type="button" title="{{ $mpLang === 'fr' ? 'Paramètres' : 'Settings' }}"
                class="w-9 h-9 grid place-items-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </button>
    </div>

    {{-- ─── Search ─── --}}
    <div class="relative">
        <input type="text" wire:model.live.debounce.800ms="query"
               placeholder="{{ $mpLang === 'fr' ? 'Rechercher dans Marketplace' : 'Search Marketplace' }}"
               class="w-full rounded-full bg-slate-100 border-0 pl-10 pr-3 py-2.5 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
        <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
    </div>

    {{-- ─── Primary nav (FB vertical style) ─── --}}
    <nav class="space-y-0.5">
        <a href="{{ route('marketplace.index') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.index' && !$this->activeCategory ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.index' && !$this->activeCategory ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1V9.5z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('Browse all','Tout parcourir')"></span>
        </a>

        {{-- Inbox — marketplace-scoped conversations (Buying / Selling) --}}
        <a href="{{ route('marketplace.inbox') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.inbox' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.inbox' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            </span>
            <span class="flex-1" x-data x-text="$store.lang.t('Inbox','Boîte de réception')"></span>
            @if ($mpInboxUnread > 0)
                <span class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-cm-red text-white text-[11px] font-bold">{{ $mpInboxUnread > 99 ? '99+' : $mpInboxUnread }}</span>
            @endif
        </a>

        <a href="{{ route('marketplace.favorites') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.favorites' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.favorites' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-9.6-9.2C1 7.7 4.3 4 8 4c2 0 3.4 1.2 4 2 .6-.8 2-2 4-2 3.7 0 7 3.7 5.6 7.8C19.5 16.4 12 21 12 21z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('Saved','Favoris')"></span>
        </a>

        <a href="{{ route('marketplace.offers') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.offers' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.offers' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('My Offers','Mes Offres')"></span>
        </a>

        @php
            $pendingOrders = 0;
            if (auth()->check()) {
                $pendingOrders = \Illuminate\Support\Facades\Cache::remember(
                    'mp:orders-pending:'.auth()->id(),
                    120,
                    fn () => \App\Models\MarketplaceOrder::query()
                        ->where('seller_id', auth()->id())
                        ->where('status', \App\Enums\OrderStatus::AwaitingPayment->value)
                        ->count()
                );
            }
        @endphp
        <a href="{{ route('marketplace.orders') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.orders' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.orders' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </span>
            <span class="flex-1" x-data x-text="$store.lang.t('Orders','Commandes')"></span>
            @if ($pendingOrders > 0)
                <span class="text-[10px] font-bold bg-cm-red text-white rounded-full px-1.5 py-0.5">{{ $pendingOrders }}</span>
            @endif
        </a>

        <a href="{{ route('marketplace.mine') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.mine' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.mine' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1 12H6L5 9z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('My Listings','Mes Annonces')"></span>
        </a>

        <a href="{{ route('marketplace.saved') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.saved' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.saved' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/></svg>
            </span>
            <span class="flex-1" x-data x-text="$store.lang.t('Saved searches','Recherches enregistrées')"></span>
            @if ($savedSearchNewCount > 0)
                <span class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-cm-red text-white text-[11px] font-bold">{{ $savedSearchNewCount > 99 ? '99+' : $savedSearchNewCount }}</span>
            @endif
        </a>
    </nav>

    {{-- ─── Create new listing CTA ─── --}}
    <a href="{{ route('marketplace.sell') }}" wire:navigate
       class="flex items-center justify-center gap-2 w-full bg-cm-green/10 hover:bg-cm-green/20 text-cm-green font-bold rounded-xl py-2.5 text-sm transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span x-data x-text="$store.lang.t('Create new listing','Créer une annonce')"></span>
    </a>

    <div class="h-px bg-slate-200"></div>

    {{-- ─── Filters ─── --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-[15px] font-bold text-slate-900" x-data x-text="$store.lang.t('Filters','Filtres')"></h3>
            <button wire:click="clearFilters" class="text-[12px] text-cm-red hover:underline font-medium" x-data x-text="$store.lang.t('Reset','Réinitialiser')"></button>
        </div>

        {{-- Location + radius (FB-style "within X km of …") --}}
        @php
            $_radiusOpts = $this->radiusOptions();
            $_locText = $locLabel !== '' ? $locLabel : ($mpLang === 'fr' ? 'Tout le Cameroun' : 'All of Cameroon');
            $_radiusText = $radius ? ('· ' . $radius . ' km') : '';
        @endphp
        <div class="px-1" x-data="{ 
                open: false, 
                q: @js($locLabel),
                results: [],
                isSearching: false,
                timeout: null,
                mapOpen: false,
                pickerMap: null,
                pickerMarker: null,
                mapLat: @js($locLat ?? 4.22),
                mapLng: @js($locLng ?? 12.0),
                init() {
                    if (!@js($locLat) && !localStorage.getItem('mp_loc_prompted')) {
                        localStorage.setItem('mp_loc_prompted', '1');
                        this.useCurrentLoc(true);
                    }
                },
                searchLoc() {
                    if (this.q.length < 3) { this.results = []; return; }
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => {
                        this.isSearching = true;
                        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(this.q) + '&limit=5')
                            .then(res => res.json())
                            .then(data => {
                                this.results = data;
                                this.isSearching = false;
                            }).catch(() => this.isSearching = false);
                    }, 500);
                },
                selectLoc(lat, lon, name) {
                    this.q = name;
                    this.results = [];
                    $wire.setLocationByCoords(lat, lon, name);
                    this.open = false;
                },
                useCurrentLoc(silent = false) {
                    if (!navigator.geolocation) return;
                    navigator.geolocation.getCurrentPosition(pos => {
                        const lat = pos.coords.latitude;
                        const lon = pos.coords.longitude;
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`)
                            .then(res => res.json())
                            .then(data => {
                                this.selectLoc(lat, lon, data.display_name || 'Current Location');
                            }).catch(() => this.selectLoc(lat, lon, 'Current Location'));
                    }, err => { if(!silent) alert('Geolocation failed or denied.'); });
                },
                initPickerMap() {
                    if (!document.getElementById('leaflet-css')) {
                        const link = document.createElement('link'); link.id = 'leaflet-css'; link.rel = 'stylesheet'; link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(link);
                        const script = document.createElement('script'); script.id = 'leaflet-js'; script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; document.body.appendChild(script);
                    }
                    const checkL = setInterval(() => {
                        if (window.L) {
                            clearInterval(checkL);
                            this.$nextTick(() => {
                                if (!this.pickerMap) {
                                    this.pickerMap = L.map(this.$refs.mapContainer).setView([this.mapLat, this.mapLng], 6);
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(this.pickerMap);
                                    this.pickerMarker = L.marker([this.mapLat, this.mapLng], {draggable: true}).addTo(this.pickerMap);
                                    this.pickerMap.on('click', (e) => { this.pickerMarker.setLatLng(e.latlng); });
                                }
                                setTimeout(() => this.pickerMap.invalidateSize(), 100);
                            });
                        }
                    }, 100);
                },
                confirmMapSelection() {
                    const pos = this.pickerMarker.getLatLng();
                    this.mapOpen = false;
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${pos.lat}&lon=${pos.lng}`)
                        .then(res => res.json())
                        .then(data => {
                            this.selectLoc(pos.lat, pos.lng, data.display_name || 'Map Selection');
                        }).catch(() => this.selectLoc(pos.lat, pos.lng, 'Map Selection'));
                }
            }">
            <div class="text-[13px] font-semibold text-slate-700 mb-1" x-data x-text="$store.lang.t('Location','Lieu')"></div>

            {{-- Trigger button --}}
            <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-2 rounded-lg bg-slate-100 hover:bg-slate-200 px-3 py-2 text-left transition">
                <svg class="w-4 h-4 text-cm-green shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="flex-1 min-w-0 truncate text-sm font-medium text-slate-800">{{ $_locText }} <span class="text-slate-400 font-normal">{{ $_radiusText }}</span></span>
                <svg class="w-3.5 h-3.5 text-slate-500 shrink-0 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            {{-- Panel --}}
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-2 rounded-xl bg-white ring-1 ring-slate-200 p-3 shadow-sm relative z-40">
                <div class="relative">
                    <input type="text" x-model="q" @input="searchLoc"
                           @keydown.enter.prevent="if(results.length > 0) selectLoc(results[0].lat, results[0].lon, results[0].display_name)"
                           placeholder="{{ $mpLang === 'fr' ? 'Rechercher une ville…' : 'Search for a city…' }}"
                           class="w-full rounded-lg bg-slate-100 border-0 pl-9 pr-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                    
                    {{-- Autocomplete dropdown --}}
                    <div x-show="results.length > 0 || isSearching" x-cloak class="absolute left-0 right-0 top-full mt-1 bg-white ring-1 ring-slate-200 shadow-xl rounded-lg overflow-hidden z-50">
                        <div x-show="isSearching" class="p-3 text-sm text-slate-500 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-cm-green" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span x-data x-text="$store.lang.t('Searching...','Recherche...')"></span>
                        </div>
                        <ul x-show="!isSearching && results.length > 0" class="max-h-48 overflow-y-auto">
                            <template x-for="res in results" :key="res.place_id">
                                <li>
                                    <button type="button" @click="selectLoc(res.lat, res.lon, res.display_name)"
                                            class="w-full text-left px-3 py-2 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none transition border-b border-slate-50 last:border-0">
                                        <div class="text-[13px] font-semibold text-slate-900 truncate" x-text="res.display_name"></div>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <div class="flex gap-2 mt-2.5">
                    <button type="button" @click="useCurrentLoc()" class="flex-1 flex items-center justify-center gap-1.5 text-xs text-cm-green hover:text-cm-green/80 font-bold py-2 bg-cm-green/10 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                        <span x-data x-text="$store.lang.t('Current Loc','Pos. actuelle')"></span>
                    </button>
                    <button type="button" @click="mapOpen = true; initPickerMap()" class="flex-1 flex items-center justify-center gap-1.5 text-xs text-slate-700 hover:bg-slate-200 font-bold py-2 bg-slate-100 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <span x-data x-text="$store.lang.t('Map picker','Carte')"></span>
                    </button>
                </div>
                
                {{-- Map Picker Modal --}}
                <template x-teleport="body">
                    <div x-show="mapOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div @click.away="mapOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="font-bold text-slate-900" x-data x-text="$store.lang.t('Choose location on map','Choisir sur la carte')"></h3>
                                <button type="button" @click="mapOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
                            </div>
                            <div class="h-[60vh] sm:h-96 w-full bg-slate-100 relative" x-ref="mapContainer"></div>
                            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                                <button type="button" @click="mapOpen = false" class="px-4 py-2 rounded-lg font-semibold text-slate-600 hover:bg-slate-200 transition" x-data x-text="$store.lang.t('Cancel','Annuler')"></button>
                                <button type="button" @click="confirmMapSelection" class="px-4 py-2 rounded-lg font-bold text-white bg-cm-green hover:bg-cm-green/90 shadow-sm transition" x-data x-text="$store.lang.t('Confirm Location','Confirmer')"></button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="pt-3 mt-3 border-t border-slate-100">
                    <div class="flex justify-between items-center mb-2">
                        <div class="text-[12px] font-semibold text-slate-600" x-data x-text="$store.lang.t('Radius','Rayon')"></div>
                        <div class="text-[12px] font-bold text-slate-900">{{ $radius ? $radius . ' km' : ($mpLang === 'fr' ? 'Tout' : 'All') }}</div>
                    </div>
                    <input type="range" wire:model.live.debounce.800ms="radius" min="0" max="500" step="5" class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-cm-green">
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1.5">
                        <span x-data x-text="$store.lang.t('Any','Tout')"></span>
                        <span>500 km</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <button type="button" @click="$wire.setLocation(q); open = false"
                            class="flex-1 text-xs font-bold bg-cm-green text-white rounded-full py-2 hover:bg-cm-green/90 transition">
                        {{ $mpLang === 'fr' ? 'Appliquer' : 'Apply' }}
                    </button>
                    <button type="button" @click="q = ''; $wire.clearLocation(); open = false"
                            class="text-xs font-semibold text-slate-500 hover:text-cm-red px-2 py-2">
                        {{ $mpLang === 'fr' ? 'Effacer' : 'Clear' }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Condition --}}
        <div class="px-1">
            <div class="text-[13px] font-semibold text-slate-700 mb-1" x-data x-text="$store.lang.t('Condition','État')"></div>
            <select wire:model.live="condition" class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                <option value="">{{ $mpLang === 'fr' ? 'Tous' : 'Any' }}</option>
                @foreach ($this->conditionOptions() as $opt)
                    <option value="{{ $opt['value'] }}">{{ $mpLang === 'fr' ? $opt['fr'] : $opt['label'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Fulfillment --}}
        <div class="px-1">
            <div class="text-[13px] font-semibold text-slate-700 mb-1" x-data x-text="$store.lang.t('Delivery','Livraison')"></div>
            <select wire:model.live="fulfillment" class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                <option value="">{{ $mpLang === 'fr' ? 'Tous' : 'Any' }}</option>
                @foreach ($this->fulfillmentOptions() as $opt)
                    <option value="{{ $opt['value'] }}">{{ $opt['icon'] }} {{ $mpLang === 'fr' ? $opt['fr'] : $opt['label'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Price range --}}
        <div class="px-1">
            <div class="text-[13px] font-semibold text-slate-700 mb-1" x-data x-text="$store.lang.t('Price (XAF)','Prix (XAF)')"></div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" min="0" wire:model.live.debounce.800ms="priceMin"
                       placeholder="{{ $mpLang === 'fr' ? 'Min' : 'Min' }}"
                       class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                <input type="number" min="0" wire:model.live.debounce.800ms="priceMax"
                       placeholder="{{ $mpLang === 'fr' ? 'Max' : 'Max' }}"
                       class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
            </div>
        </div>

        {{-- Category-specific quick filters (Phase 4) --}}
        @php
            $_filterableAttrs = collect(\App\Support\CategoryAttributeSchema::forCategory($this->activeCategory?->slug))
                ->where('filter', true)
                ->values()
                ->all();
        @endphp
        @if (! empty($_filterableAttrs))
            <div class="px-1 pt-1 border-t border-slate-100">
                <div class="text-[11px] uppercase tracking-wide font-bold text-cm-green mb-2 mt-2 flex items-center gap-1">
                    <span>{{ $this->activeCategory->icon }}</span>
                    <span>{{ $this->activeCategory->localizedName() }}</span>
                </div>
                <div class="space-y-2">
                    @foreach ($_filterableAttrs as $f)
                        <div>
                            <div class="text-[12px] font-semibold text-slate-700 mb-1 flex items-center gap-1">
                                @if (! empty($f['icon'])) <span>{{ $f['icon'] }}</span> @endif
                                <span>{{ $mpLang === 'fr' ? $f['labelFr'] : $f['label'] }}</span>
                            </div>
                            @if ($f['type'] === 'select')
                                <select wire:model.live="attrs.{{ $f['key'] }}"
                                        class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none">
                                    <option value="">{{ $mpLang === 'fr' ? 'Tous' : 'Any' }}</option>
                                    @foreach ($f['options'] as $opt)
                                        <option value="{{ $opt['value'] }}">{{ $mpLang === 'fr' ? ($opt['labelFr'] ?? $opt['label']) : $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            @elseif ($f['type'] === 'toggle')
                                <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                                    <input type="checkbox" wire:model.live="attrs.{{ $f['key'] }}" class="w-4 h-4 rounded text-cm-green focus:ring-cm-green">
                                    <span>{{ $mpLang === 'fr' ? 'Oui' : 'Yes' }}</span>
                                </label>
                            @elseif ($f['type'] === 'number')
                                <input type="number" min="0" wire:model.live.debounce.700ms="attrs.{{ $f['key'] }}"
                                       placeholder="{{ $f['help'] ?? '' }}"
                                       class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none">
                            @else
                                <input type="text" wire:model.live.debounce.600ms="attrs.{{ $f['key'] }}"
                                       placeholder="{{ $f['help'] ?? '' }}"
                                       class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="h-px bg-slate-200"></div>

    {{-- ─── Categories ─── --}}
    <div>
        <h3 class="text-[15px] font-bold text-slate-900 px-1 mb-2" x-data x-text="$store.lang.t('Categories','Catégories')"></h3>
        <div class="space-y-0.5">
            @foreach ($this->categories as $cat)
                @php($isActive = $this->activeCategory?->id === $cat->id)
                <a href="{{ route('marketplace.category', ['slug' => $cat->slug]) }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-[14px] transition
                   {{ $isActive ? 'bg-cm-green/10 text-cm-green font-semibold' : 'text-slate-800 hover:bg-slate-100' }}">
                    <span class="w-9 h-9 grid place-items-center rounded-full text-lg {{ $isActive ? 'bg-cm-green/20' : 'bg-slate-100' }}">
                        {{ $cat->icon }}
                    </span>
                    <span class="truncate">{{ $cat->localizedName() }}</span>
                </a>
            @endforeach
        </div>
    {{-- ─── Recently viewed ─── --}}
    @php($_recent = \App\Support\RecentlyViewed::listings(null, 6))
    @if ($_recent->isNotEmpty())
        <div class="h-px bg-slate-200"></div>
        <div>
            <h3 class="text-[15px] font-bold text-slate-900 px-1 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-data x-text="$store.lang.t('Recently viewed','Vu récemment')"></span>
            </h3>
            <div class="space-y-1">
                @foreach ($_recent as $r)
                    <a href="{{ route('marketplace.show', ['slug' => $r->slug]) }}" wire:navigate
                       class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition">
                        @if ($r->coverUrl())
                            <img src="{{ $r->coverUrl() }}" alt="" loading="lazy" class="w-10 h-10 rounded-lg object-cover ring-1 ring-slate-200 shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-lg bg-slate-200 grid place-items-center text-lg shrink-0">📦</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-medium text-slate-900 truncate">{{ $r->title }}</div>
                            <div class="text-[11px] text-cm-green font-bold">{{ $r->formattedPrice() }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
