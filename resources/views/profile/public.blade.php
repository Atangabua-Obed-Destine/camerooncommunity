<x-layouts.app :title="$displayName . ' · ' . config('app.name')" :yardMode="true">
    {{-- ═══════════════════════════════════════════════════════════
         PUBLIC USER PROFILE — Facebook-style (within Yard layout shell)
       ═══════════════════════════════════════════════════════════ --}}
    <div class="yard-container">

        {{-- Left icon sidebar (shared with /yard) --}}
        @include('yard.partials.icon-sidebar', ['active' => $isSelf ? 'profile' : 'yard'])

        {{-- Middle scrollable column --}}
        <main class="flex-1 min-w-0 overflow-y-auto bg-slate-50">
        <div class="max-w-5xl mx-auto pb-12">

        {{-- ── Top bar (back + share) ── --}}
        <div class="flex items-center justify-between px-4 py-3 sm:px-6">
            <button type="button"
                    onclick="history.length > 1 ? history.back() : (window.location.href = '{{ url('/') }}')"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-cm-green transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                <span x-text="$store.lang.t('Back', 'Retour')">Back</span>
            </button>

            <button type="button"
                    x-data
                    @click="navigator.share ? navigator.share({title: @js($displayName), url: window.location.href}).catch(()=>{}) : (navigator.clipboard.writeText(window.location.href), $dispatch('toast', {type:'success', message:'Link copied'}))"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 text-sm font-medium text-slate-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/></svg>
                <span x-text="$store.lang.t('Share', 'Partager')">Share</span>
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             COVER + AVATAR HERO
           ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

            {{-- Cover photo --}}
            <div class="relative h-44 sm:h-64 md:h-72 overflow-hidden
                @if(!$user->cover_photo) bg-gradient-to-br from-cm-green via-emerald-500 to-cm-yellow @endif">
                @if($user->cover_photo)
                    <img src="{{ asset('storage/' . $user->cover_photo) }}" alt=""
                         class="w-full h-full object-cover cursor-zoom-in"
                         @click="$dispatch('open-image', { url: '{{ asset('storage/' . $user->cover_photo) }}' })">
                @endif

                @if($user->is_founding_member)
                    <div class="absolute top-3 right-3 px-3 py-1 bg-white/95 rounded-full text-xs font-bold text-cm-green flex items-center gap-1 shadow">
                        ⭐ <span x-text="$store.lang.t('Founding Member', 'Membre Fondateur')"></span>
                    </div>
                @endif

                @if($isSelf)
                    <a href="{{ route('profile') }}"
                       class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-black/55 hover:bg-black/70 text-white text-xs font-medium backdrop-blur-sm shadow-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897l11.932-11.93z"/></svg>
                        <span x-text="$store.lang.t('Edit cover', 'Modifier')">Edit cover</span>
                    </a>
                @endif
            </div>

            {{-- Avatar + identity row --}}
            <div class="px-4 sm:px-6 pb-5 relative">
                <div class="flex flex-col sm:flex-row sm:items-end sm:gap-5 -mt-14 sm:-mt-16">
                    {{-- Avatar --}}
                    <div class="relative w-28 h-28 sm:w-36 sm:h-36 shrink-0 mx-auto sm:mx-0">
                        <div class="w-full h-full rounded-full border-4 border-white shadow-xl overflow-hidden bg-cm-green/10 flex items-center justify-center text-4xl font-bold text-cm-green">
                            @if($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" alt=""
                                     class="w-full h-full object-cover cursor-zoom-in"
                                     @click="$dispatch('open-image', { url: '{{ asset('storage/' . $user->avatar) }}' })">
                            @else
                                {{ strtoupper(substr($user->username ?? $user->name, 0, 2)) }}
                            @endif
                        </div>
                        {{-- Online dot --}}
                        @if($user->last_active_at && $user->last_active_at->gt(now()->subMinutes(5)))
                            <span class="absolute bottom-2 right-2 w-4 h-4 rounded-full bg-emerald-500 border-2 border-white" title="Online"></span>
                        @endif
                    </div>

                    {{-- Name + handle --}}
                    <div class="mt-3 sm:mt-0 sm:pb-3 text-center sm:text-left flex-1 min-w-0">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 truncate">{{ $displayName }}</h1>
                        @if($savedNickname && $savedNickname !== ($user->username ?? $user->name))
                            <p class="text-sm text-slate-400">{{ '@' . ($user->username ?? $user->name) }}</p>
                        @endif
                        <div class="mt-1 flex flex-wrap items-center justify-center sm:justify-start gap-x-3 gap-y-1 text-xs text-slate-500">
                            @if($user->current_country)
                                <span class="inline-flex items-center gap-1">📍 {{ config("cameroon.countries.{$user->current_country}", $user->current_country) }}</span>
                            @endif
                            @if($user->home_region)
                                <span class="inline-flex items-center gap-1">🏠 {{ $user->home_region }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1">📅 <span x-text="$store.lang.t('Joined', 'Rejoint')"></span> {{ $user->created_at->format('M Y') }}</span>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="mt-4 sm:mt-0 sm:pb-3 flex items-center justify-center gap-2 shrink-0">
                        @if($isSelf)
                            <a href="{{ route('profile') }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-cm-green text-white text-sm font-semibold hover:bg-cm-green/90 shadow transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897l11.932-11.93z"/></svg>
                                <span x-text="$store.lang.t('Edit profile', 'Modifier le profil')">Edit profile</span>
                            </a>
                        @else
                            {{-- Message CTA --}}
                            @if($dmRoomId)
                                <a href="{{ route('yard') }}?room={{ $dmRoomId }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-cm-green text-white text-sm font-semibold hover:bg-cm-green/90 shadow transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                                    <span x-text="$store.lang.t('Message', 'Message')">Message</span>
                                </a>
                            @else
                                <a href="{{ route('yard') }}?dm={{ $user->id }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-cm-green text-white text-sm font-semibold hover:bg-cm-green/90 shadow transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                                    <span x-text="$store.lang.t('Message', 'Message')">Message</span>
                                </a>
                            @endif

                            {{-- Connect / connection state --}}
                            @php $st = $connection?->status; @endphp
                            @if($st === \App\Models\UserConnection::STATUS_ACCEPTED)
                                <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                    ✓ <span x-text="$store.lang.t('Connected', 'Connecté')"></span>
                                </span>
                            @elseif($st === \App\Models\UserConnection::STATUS_PENDING)
                                <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                                    ⏳ <span x-text="$store.lang.t('Pending', 'En attente')"></span>
                                </span>
                            @endif

                            {{-- More menu --}}
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
                        @endif
                    </div>
                </div>

                {{-- Bio --}}
                @if($user->bio)
                    <p class="mt-5 text-[15px] leading-relaxed text-slate-700 whitespace-pre-line text-center sm:text-left">{{ $user->bio }}</p>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             STATS STRIP
           ═══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 px-4 sm:px-0">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ number_format($stats['points']) }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Points', 'Points')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $stats['rooms'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Rooms', 'Salons')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $stats['contributions'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Contributions', 'Contributions')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $stats['listings'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Listings', 'Annonces')"></p>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             TWO-COLUMN LAYOUT (intro / content)
           ═══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4 px-4 sm:px-0">

            {{-- Intro card --}}
            <aside class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 mb-3" x-text="$store.lang.t('Intro', 'À propos')"></h2>
                    <ul class="space-y-3 text-sm text-slate-700">
                        @if($user->home_region)
                            <li class="flex items-start gap-2">
                                <span>🏠</span>
                                <span><span x-text="$store.lang.t('From', 'De')"></span> <strong>{{ $user->home_city ? $user->home_city . ', ' : '' }}{{ $user->home_region }}</strong></span>
                            </li>
                        @endif
                        @if($user->current_city || $user->current_country)
                            <li class="flex items-start gap-2">
                                <span>📍</span>
                                <span><span x-text="$store.lang.t('Lives in', 'Vit à')"></span> <strong>{{ $user->current_city }}{{ $user->current_city && $user->current_country ? ', ' : '' }}{{ config("cameroon.countries.{$user->current_country}", $user->current_country) }}</strong></span>
                            </li>
                        @endif
                        @if($user->language_pref)
                            <li class="flex items-start gap-2">
                                <span>🗣️</span>
                                <span>{{ $user->language_pref === 'fr' ? 'Français' : 'English' }}</span>
                            </li>
                        @endif
                        <li class="flex items-start gap-2">
                            <span>📅</span>
                            <span><span x-text="$store.lang.t('Joined', 'Rejoint')"></span> {{ $user->created_at->format('F Y') }}</span>
                        </li>
                        @if($user->is_founding_member)
                            <li class="flex items-start gap-2">
                                <span>⭐</span>
                                <span class="text-cm-green font-semibold" x-text="$store.lang.t('Founding member', 'Membre fondateur')"></span>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Badges --}}
                @if($badges->count())
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 mb-3" x-text="$store.lang.t('Badges', 'Badges')"></h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($badges as $badge)
                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-cm-yellow/10 rounded-full border border-cm-yellow/20">
                                <span class="text-sm">{{ $badge->icon ?? '🏅' }}</span>
                                <span class="text-xs font-medium text-slate-700">{{ $badge->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </aside>

            {{-- Content column --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Marketplace listings (Facebook Marketplace style) --}}
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-slate-900">
                            <span x-text="$store.lang.t('GoMarket listings', 'Annonces GoMarket')"></span>
                            <span class="text-slate-400 font-normal text-sm">· {{ $stats['listings'] }}</span>
                        </h2>
                        @if($stats['listings'] > $listings->count())
                            <a href="{{ url('/marketplace?seller=' . $user->id) }}" class="text-sm text-cm-green font-medium hover:underline" x-text="$store.lang.t('See all', 'Voir tout')"></a>
                        @endif
                    </div>

                    @if($listings->isEmpty())
                        <div class="py-8 text-center text-sm text-slate-400">
                            <div class="text-4xl mb-2">🛍️</div>
                            <p x-text="$store.lang.t('No active listings yet', 'Aucune annonce active pour le moment')"></p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($listings as $l)
                                @php
                                    $imgs = is_array($l->images) ? $l->images : (json_decode($l->images, true) ?: []);
                                    $img  = $imgs[0] ?? null;
                                @endphp
                                <a href="{{ url('/marketplace/' . $l->uuid) }}"
                                   class="group block rounded-xl overflow-hidden border border-slate-200 hover:border-cm-green hover:shadow-md transition">
                                    <div class="aspect-square bg-slate-100 overflow-hidden relative">
                                        @if($img)
                                            <img src="{{ asset('storage/' . $img) }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-4xl text-slate-300">🛍️</div>
                                        @endif
                                        @if($l->status === 'sold')
                                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded bg-slate-900/80 text-white text-[10px] font-semibold uppercase">Sold</span>
                                        @endif
                                    </div>
                                    <div class="p-2">
                                        <p class="text-sm font-bold text-slate-900 truncate">{{ $l->currency }} {{ number_format($l->price, 2) }}</p>
                                        <p class="text-xs text-slate-600 truncate">{{ $l->title }}</p>
                                        @if($l->city)
                                            <p class="text-[11px] text-slate-400 truncate mt-0.5">📍 {{ $l->city }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Activity placeholder card (kept lightweight) --}}
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 mb-3" x-text="$store.lang.t('Activity', 'Activité')"></h2>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center gap-2">💬 <span><span x-text="$store.lang.t('Member of', 'Membre de')"></span> <strong>{{ $stats['rooms'] }}</strong> <span x-text="$store.lang.t('rooms', 'salons')"></span></span></li>
                        <li class="flex items-center gap-2">🤝 <span><strong>{{ $stats['contributions'] }}</strong> <span x-text="$store.lang.t('solidarity contributions', 'contributions de solidarité')"></span></span></li>
                        <li class="flex items-center gap-2">🏆 <span><strong>{{ number_format($stats['points']) }}</strong> <span x-text="$store.lang.t('community points earned', 'points communautaires gagnés')"></span></span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
        </main>

        {{-- Right sponsored ads sidebar (shared with /yard) --}}
        @include('yard.partials.ads-sidebar')
    </div>

    {{-- Lightweight image lightbox --}}
    <div x-data="{ url:null }"
         @open-image.window="url = $event.detail.url; document.body.style.overflow='hidden'"
         @keydown.escape.window="url = null; document.body.style.overflow=''"
         x-show="url" x-cloak
         @click="url = null; document.body.style.overflow=''"
         class="fixed inset-0 z-[9999] bg-black/90 flex items-center justify-center p-4 cursor-zoom-out">
        <img :src="url" alt="" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
    </div>
</x-layouts.app>
