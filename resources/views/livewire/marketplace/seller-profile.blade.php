{{-- The app-wide user profile. Replaced the old /u/{username} page, which now
     redirects here, so it carries that page's Intro, badges, stats and Connect
     state alongside the marketplace listings grid. --}}
@php
    $lang = app()->getLocale();
    $isSelf = (int) auth()->id() === (int) $user->id;
    $stats = $this->stats;
    $cstats = $this->communityStats;
    $userBadges = $this->badges;
    $conn = $this->connection;
    $dmRoomId = $this->dmRoomId;
    $name = $user->name ?: $user->username;
    $loc = trim(($user->current_city ? $user->current_city . ', ' : '') . ($user->current_country ?? ''));
    $badges = \App\Support\TrustBadges::forSeller($user);
@endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100"
     x-data="{ settingsOpen: {{ ($errors->any() || session('success')) && $isSelf ? 'true' : 'false' }} }">
    <div class="max-w-5xl mx-auto px-3 sm:px-4 lg:px-5 py-4 lg:py-5">

        {{-- Profiles are reached from chat, People and GoMarket alike, so go back
             where the viewer came from rather than always to the marketplace. --}}
        <button type="button" x-data @click="history.length > 1 ? history.back() : window.location.assign('{{ route('yard') }}')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-cm-green mb-3 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <span x-text="$store.lang.t('Back','Retour')"></span>
        </button>

        {{-- ── Header card ── --}}
        {{-- No overflow-hidden here: it clipped the overflow menu inside the card.
             The cover rounds its own top corners instead (inline, because
             rounded-t-2xl is not in the compiled CSS and adding it would
             force a Vite rebuild). --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 mb-5">
            <div class="h-24 sm:h-32 bg-gradient-to-r from-cm-green to-cm-green-light
                @if($user->cover_photo) bg-cover bg-center @endif"
                 style="border-top-left-radius:1rem;border-top-right-radius:1rem;@if($user->cover_photo)background-image:url('{{ asset('storage/' . $user->cover_photo) }}')@endif"></div>

            <div class="px-4 sm:px-6 pb-5">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-12">
                    {{-- Avatar --}}
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full border-4 border-white shadow-lg overflow-hidden bg-cm-green/10 grid place-items-center text-3xl font-bold text-cm-green shrink-0 mx-auto sm:mx-0"
                         @if($user->avatar)
                             x-data style="cursor:zoom-in"
                             @click="$dispatch('open-user-photo', { url: '{{ asset('storage/' . $user->avatar) }}', name: @js($name) })"
                         @endif>
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(mb_substr($name, 0, 1)) }}
                        @endif
                    </div>

                    <div class="flex-1 min-w-0 text-center sm:text-left">
                        <h1 class="text-2xl font-extrabold text-slate-900 truncate">{{ $name }}</h1>
                        <div class="mt-0.5 flex flex-wrap items-center justify-center sm:justify-start gap-x-3 gap-y-0.5 text-[13px] text-slate-500">
                            <span>{{ $lang === 'fr' ? 'Inscrit' : 'Joined' }} {{ $user->created_at?->translatedFormat('M Y') }}</span>
                            <span class="text-slate-300">·</span>
                            <span class="font-semibold text-slate-700">{{ $stats['active'] }} {{ $lang === 'fr' ? 'annonces actives' : 'active listings' }}</span>
                            @if($stats['rating_count'] > 0)
                                <span class="text-slate-300">·</span>
                                <span class="inline-flex items-center gap-0.5"><span class="text-cm-yellow">★</span> {{ number_format($stats['rating_avg'],1) }} <span class="text-slate-400">({{ $stats['rating_count'] }})</span></span>
                            @endif
                            @if($stats['followers'] > 0)
                                <span class="text-slate-300">·</span>
                                <span>{{ $stats['followers'] }} {{ $lang === 'fr' ? 'abonnés' : 'followers' }}</span>
                            @endif
                        </div>
                        @if($loc)
                            <div class="mt-1 text-[13px] text-slate-500 inline-flex items-center gap-1 justify-center sm:justify-start">
                                <svg class="w-3.5 h-3.5 text-cm-red" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                                {{ $loc }}
                            </div>
                        @endif
                        @if(!empty($badges))
                            <div class="mt-2 flex flex-wrap gap-1.5 justify-center sm:justify-start">
                                @foreach($badges as $b)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full ring-1 {{ \App\Support\TrustBadges::chipClasses($b['tone']) }}">
                                        <span aria-hidden="true">{{ $b['icon'] }}</span>{{ $lang === 'fr' ? $b['labelFr'] : $b['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    @unless($isSelf)
                        <div class="flex items-center gap-2 justify-center sm:justify-end shrink-0">
                            <button type="button" wire:click="toggleFollow({{ $user->id }})"
                                    @class([
                                        'inline-flex items-center gap-1.5 font-bold text-sm rounded-full px-4 py-2 transition',
                                        'bg-cm-green text-white hover:bg-cm-green/90 shadow' => ! $this->isFollowing($user->id),
                                        'bg-slate-100 text-slate-700 ring-1 ring-slate-300 hover:bg-slate-200' => $this->isFollowing($user->id),
                                    ])>
                                @if($this->isFollowing($user->id))
                                    ✓ <span x-data x-text="$store.lang.t('Following','Abonné')"></span>
                                @else
                                    + <span x-data x-text="$store.lang.t('Follow','Suivre')"></span>
                                @endif
                            </button>
                            {{-- Opens the normal Yard DM, not the GoMarket dock: this is a
                                 general profile, so there is no listing context here. --}}
                            <a href="{{ $dmRoomId ? route('yard') . '?room=' . $dmRoomId : route('yard') . '?dm=' . $user->id }}"
                               class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-sm rounded-full px-4 py-2 transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                <span x-data x-text="$store.lang.t('Message','Message')"></span>
                            </a>

                            @if($conn?->status === \App\Models\UserConnection::STATUS_ACCEPTED)
                                <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                    &check; <span x-data x-text="$store.lang.t('Connected', 'Connecté')"></span>
                                </span>
                            @elseif($conn?->status === \App\Models\UserConnection::STATUS_PENDING)
                                <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                                    ⏳ <span x-data x-text="$store.lang.t('Pending', 'En attente')"></span>
                                </span>
                            @elseif(! $conn)
                                {{-- No connection yet: offer to start one, same as the People directory. --}}
                                <button type="button" wire:click="connect" wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1.5 bg-white ring-1 ring-slate-300 hover:bg-slate-50 text-slate-800 font-bold text-sm rounded-full px-4 py-2 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v6m3-3h-6M5.25 21v-1.5a6 6 0 0 1 6-6h2.25a6 6 0 0 1 4.215 1.737M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/>
                                    </svg>
                                    <span x-data x-text="$store.lang.t('Connect', 'Se connecter')"></span>
                                </button>
                            @endif

                            <div x-data="{ open:false }" class="relative">
                                <button @click="open=!open" type="button"
                                        class="w-9 h-9 inline-flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"/></svg>
                                </button>
                                <div x-show="open" @click.away="open=false" x-transition x-cloak
                                     class="absolute right-0 top-full mt-2 w-52 rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl z-30">
                                    <a href="#" @click.prevent="navigator.clipboard.writeText(window.location.href); $dispatch('toast',{type:'success',message:'Link copied'}); open=false"
                                       class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">📋 <span x-text="$store.lang.t('Copy link', 'Copier le lien')"></span></a>
                                    <a href="{{ route('yard') }}?dm={{ $user->id }}#info"
                                       class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">🏷️ <span x-text="$store.lang.t('Save as a contact', 'Enregistrer comme contact')"></span></a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 justify-center sm:justify-end shrink-0">
                            <button type="button" @click="settingsOpen = true"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-cm-green text-white text-sm font-semibold hover:bg-cm-green/90 shadow transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span x-text="$store.lang.t('Settings', 'Paramètres')"></span>
                            </button>
                        </div>
                    @endunless
                </div>

                @if($user->bio)
                    <p class="mt-5 text-[15px] leading-relaxed text-slate-700 whitespace-pre-line text-center sm:text-left">{{ $user->bio }}</p>
                @endif
            </div>
        </div>

        {{-- Community stats, carried over from the old /u/ profile --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ number_format($cstats['points']) }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Points', 'Points')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $cstats['rooms'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Rooms', 'Salons')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $cstats['contributions'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Contributions', 'Contributions')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $stats['active'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-data x-text="$store.lang.t('Listings', 'Annonces')"></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Intro, badges and activity --}}
            <aside class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 mb-3" x-data x-text="$store.lang.t('Intro', 'À propos')"></h2>
                    <ul class="space-y-3 text-sm text-slate-700">
                        @if($user->home_region)
                            <li class="flex items-start gap-2">
                                <span>&#127968;</span>
                                <span><span x-data x-text="$store.lang.t('From', 'De')"></span> <strong>{{ $user->home_city ? $user->home_city . ', ' : '' }}{{ $user->home_region }}</strong></span>
                            </li>
                        @endif
                        @if($user->current_city || $user->current_country)
                            <li class="flex items-start gap-2">
                                <span>&#128205;</span>
                                <span><span x-data x-text="$store.lang.t('Lives in', 'Vit à')"></span> <strong>{{ $user->current_city }}{{ $user->current_city && $user->current_country ? ', ' : '' }}{{ config("cameroon.countries.{$user->current_country}", $user->current_country) }}</strong></span>
                            </li>
                        @endif
                        @if($user->language_pref)
                            <li class="flex items-start gap-2">
                                <span>&#128483;</span>
                                <span>{{ $user->language_pref === 'fr' ? 'Français' : 'English' }}</span>
                            </li>
                        @endif
                        <li class="flex items-start gap-2">
                            <span>&#128197;</span>
                            <span><span x-data x-text="$store.lang.t('Joined', 'Rejoint')"></span> {{ $user->created_at?->translatedFormat('F Y') }}</span>
                        </li>
                        @if($user->is_founding_member)
                            <li class="flex items-start gap-2">
                                <span>&#11088;</span>
                                <span class="text-cm-green font-semibold" x-data x-text="$store.lang.t('Founding member', 'Membre fondateur')"></span>
                            </li>
                        @endif
                    </ul>
                </div>

                @if($userBadges->count())
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <h2 class="font-bold text-slate-900 mb-3" x-data x-text="$store.lang.t('Badges', 'Badges')"></h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach($userBadges as $badge)
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-cm-yellow/10 rounded-full border border-cm-yellow/20">
                                    {{-- badge_type is the real column; the old page read
                                         ->icon / ->name, which do not exist on the model. --}}
                                    <span class="text-sm">{{ $badge->badge_type?->icon() ?? '🏅' }}</span>
                                    <span class="text-xs font-medium text-slate-700">{{ $badge->badge_type?->label() }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 mb-3" x-data x-text="$store.lang.t('Activity', 'Activité')"></h2>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center gap-2">&#128172; <span><span x-data x-text="$store.lang.t('Member of', 'Membre de')"></span> <strong>{{ $cstats['rooms'] }}</strong> <span x-data x-text="$store.lang.t('rooms', 'salons')"></span></span></li>
                        <li class="flex items-center gap-2">&#129309; <span><strong>{{ $cstats['contributions'] }}</strong> <span x-data x-text="$store.lang.t('solidarity contributions', 'contributions de solidarité')"></span></span></li>
                        <li class="flex items-center gap-2">&#127942; <span><strong>{{ number_format($cstats['points']) }}</strong> <span x-data x-text="$store.lang.t('community points earned', 'points communautaires gagnés')"></span></span></li>
                    </ul>
                </div>
            </aside>

            {{-- Listings --}}
            <div class="lg:col-span-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <h2 class="text-lg font-extrabold text-slate-900">
                {{ $isSelf ? ($lang === 'fr' ? 'Mes annonces' : 'Your listings') : ($lang === 'fr' ? 'Annonces de ' . $name : $name . "'s listings") }}
                @if($isSelf)
                    <a href="{{ route('marketplace.mine') }}" wire:navigate
                       class="ml-2 text-[13px] font-semibold text-cm-green hover:underline">
                        {{ $lang === 'fr' ? 'Gérer' : 'Manage' }}
                    </a>
                @endif
            </h2>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.400ms="search"
                           placeholder="{{ $lang === 'fr' ? 'Rechercher…' : 'Search listings' }}"
                           class="rounded-full bg-white ring-1 ring-slate-300 pl-9 pr-3 py-1.5 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none w-44 sm:w-56">
                    <svg class="w-4 h-4 absolute left-3 top-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                </div>
                <select wire:model.live="sort"
                        class="rounded-full bg-white ring-1 ring-slate-300 px-3 py-1.5 text-sm font-medium focus:ring-cm-green focus:outline-none cursor-pointer">
                    <option value="newest">{{ $lang === 'fr' ? 'Plus récent' : 'Newest' }}</option>
                    <option value="price_asc">{{ $lang === 'fr' ? 'Prix ↑' : 'Price ↑' }}</option>
                    <option value="price_desc">{{ $lang === 'fr' ? 'Prix ↓' : 'Price ↓' }}</option>
                    <option value="popular">{{ $lang === 'fr' ? 'Populaire' : 'Popular' }}</option>
                </select>
            </div>
        </div>

        @if($this->listings->isEmpty())
            <div class="text-center py-16 bg-white rounded-2xl ring-1 ring-slate-200">
                <div class="text-5xl mb-2">🛍️</div>
                <div class="font-bold text-slate-900">
                    @if($isSelf)
                        {{ $lang === 'fr' ? "Vous n'avez encore aucune annonce" : 'You have no listings yet' }}
                    @else
                        {{ $lang === 'fr' ? 'Aucune annonce active' : 'No active listings' }}
                    @endif
                </div>
                @if($isSelf)
                    <a href="{{ route('marketplace.sell') }}" wire:navigate
                       class="mt-3 inline-flex items-center gap-1 bg-cm-green hover:bg-cm-green/90 text-white font-semibold rounded-full px-4 py-2 text-sm shadow-sm transition">
                        {{ $lang === 'fr' ? 'Vendre un article' : 'Sell something' }}
                    </a>
                @endif
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-x-3 gap-y-4">
                @foreach($this->listings as $listing)
                    @php($st = $listing->status?->value)
                    @if($isSelf && $st !== 'active' && $st !== 'sold')
                        {{-- Only you see these; the card itself badges 'sold' already. --}}
                        <div class="relative">
                            <span class="absolute top-2 left-2 z-10 px-2 py-0.5 rounded bg-slate-900/80 text-white text-[10px] font-semibold uppercase">
                                {{ str_replace('_', ' ', $st) }}
                            </span>
                            <x-marketplace.listing-card :listing="$listing" />
                        </div>
                    @else
                        <x-marketplace.listing-card :listing="$listing" />
                    @endif
                @endforeach
            </div>
            <div class="mt-6">{{ $this->listings->onEachSide(1)->links() }}</div>
        @endif
            </div>{{-- /listings column --}}
        </div>{{-- /two-column grid --}}
    </div>

    {{-- ── Settings popup (own profile only) ──
         The same form as /profile, in a dialog so it costs no page space. It is a
         plain POST to profile.update, which redirects back here; settingsOpen is
         seeded true on validation errors or a success flash so the dialog
         reappears after that round trip. --}}
    @if($isSelf)
        <div x-show="settingsOpen" x-cloak x-transition.opacity
             class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
             @keydown.escape.window="settingsOpen = false">
            <div @click.outside="settingsOpen = false" x-transition.scale.95
                 class="bg-white w-full max-w-lg rounded-2xl shadow-2xl ring-1 ring-black/5 max-h-[85vh] overflow-y-auto">

                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 sticky top-0 bg-white">
                    <h2 class="font-bold text-slate-900" x-text="$store.lang.t('Settings', 'Paramètres')"></h2>
                    <button type="button" @click="settingsOpen = false"
                            class="w-9 h-9 grid place-items-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 transition"
                            :aria-label="$store.lang.t('Close', 'Fermer')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5">
                    @if(session('success'))
                        <div class="p-3 bg-blue-50 text-blue-700 text-sm rounded-lg border border-blue-200 mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Photos. Separate multipart forms (one per endpoint), each
                         redirecting back here with a success flash, which reopens
                         this dialog. --}}
                    <div class="rounded-xl border border-slate-200 p-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-16 h-16 rounded-full overflow-hidden bg-cm-green/10 grid place-items-center text-xl font-bold text-cm-green shrink-0">
                                @if($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                                @else
                                    {{ strtoupper(mb_substr($name, 0, 1)) }}
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Profile photo', 'Photo de profil')"></p>
                                <div class="mt-1 flex items-center gap-3">
                                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data">
                                        @csrf
                                        <label class="text-[13px] font-semibold text-cm-green hover:underline cursor-pointer">
                                            <span x-text="$store.lang.t('Change', 'Modifier')"></span>
                                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                                                   class="hidden" onchange="this.form.submit()">
                                        </label>
                                    </form>
                                    @if($user->avatar)
                                        <form method="POST" action="{{ route('profile.avatar.remove') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-[13px] font-semibold text-cm-red hover:underline"
                                                    x-text="$store.lang.t('Remove', 'Supprimer')"></button>
                                        </form>
                                    @endif
                                </div>
                                @error('avatar') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100">
                            <p class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Cover photo', 'Photo de couverture')"></p>
                            <div class="mt-1 flex items-center gap-3">
                                <form method="POST" action="{{ route('profile.cover') }}" enctype="multipart/form-data">
                                    @csrf
                                    <label class="text-[13px] font-semibold text-cm-green hover:underline cursor-pointer">
                                        <span x-text="$store.lang.t('Change', 'Modifier')"></span>
                                        <input type="file" name="cover_photo" accept="image/jpeg,image/png,image/webp"
                                               class="hidden" onchange="this.form.submit()">
                                    </label>
                                </form>
                                @if($user->cover_photo)
                                    <form method="POST" action="{{ route('profile.cover.remove') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[13px] font-semibold text-cm-red hover:underline"
                                                x-text="$store.lang.t('Remove', 'Supprimer')"></button>
                                    </form>
                                @endif
                            </div>
                            @error('cover_photo') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Display Name', 'Nom d\'Affichage')"></label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                            @error('name') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Language', 'Langue')"></label>
                            <div class="flex gap-3">
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="language_pref" value="en" {{ $user->language_pref === 'en' ? 'checked' : '' }} class="peer sr-only">
                                    <div class="rounded-xl border-2 border-slate-200 px-4 py-3 text-center text-sm peer-checked:border-cm-green peer-checked:bg-cm-green/5 transition-colors">
                                        🇬🇧 English
                                    </div>
                                </label>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="language_pref" value="fr" {{ $user->language_pref === 'fr' ? 'checked' : '' }} class="peer sr-only">
                                    <div class="rounded-xl border-2 border-slate-200 px-4 py-3 text-center text-sm peer-checked:border-cm-green peer-checked:bg-cm-green/5 transition-colors">
                                        🇫🇷 Français
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Region of Origin', 'Région d\'Origine')"></label>
                            <select name="home_region" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                                <option value="">-</option>
                                @foreach(config('cameroon.regions', []) as $region)
                                    <option value="{{ $region }}" {{ $user->home_region === $region ? 'selected' : '' }}>{{ $region }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('City of Origin', 'Ville d\'Origine')"></label>
                            <input type="text" name="home_city" value="{{ old('home_city', $user->home_city) }}"
                                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                   :placeholder="$store.lang.t('e.g. Bamenda, Douala...', 'ex. Bamenda, Douala...')">
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <label class="flex items-start justify-between gap-3 cursor-pointer">
                                <div class="flex-1">
                                    <span class="block text-sm font-medium text-slate-700"
                                          x-text="$store.lang.t('Show away chats in GoConnect', 'Afficher les discussions absentes dans GoConnect')"></span>
                                    <span class="block text-xs text-slate-500 mt-0.5"
                                          x-text="$store.lang.t('Display chats auto-archived because you switched location.', 'Afficher les discussions auto-archivées suite à un changement de lieu.')"></span>
                                </div>
                                <input type="hidden" name="show_archived_away" value="0">
                                <span class="relative inline-block flex-shrink-0 mt-1">
                                    <input type="checkbox" name="show_archived_away" value="1" class="peer sr-only"
                                           {{ old('show_archived_away', $user->show_archived_away) ? 'checked' : '' }}>
                                    <span class="block w-10 h-6 bg-slate-300 rounded-full peer-checked:bg-cm-green transition-colors"></span>
                                    <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></span>
                                </span>
                            </label>
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-cm-green py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green/90">
                            <span x-text="$store.lang.t('Save Changes', 'Enregistrer')"></span>
                        </button>
                    </form>

                </div>
            </div>
        </div>
    @endif
</div>
