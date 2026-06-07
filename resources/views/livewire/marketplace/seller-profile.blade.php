{{-- GoMarket seller profile — Facebook Marketplace style --}}
@php
    $lang = app()->getLocale();
    $isSelf = (int) auth()->id() === (int) $user->id;
    $stats = $this->stats;
    $name = $user->name ?: $user->username;
    $loc = trim(($user->current_city ? $user->current_city . ', ' : '') . ($user->current_country ?? ''));
    $badges = \App\Support\TrustBadges::forSeller($user);
@endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-5xl mx-auto px-3 sm:px-4 lg:px-5 py-4 lg:py-5">

        {{-- Back to marketplace --}}
        <a href="{{ route('marketplace.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-cm-green mb-3 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <span x-data x-text="$store.lang.t('GoMarket','GoMarket')"></span>
        </a>

        {{-- ── Header card ── --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden mb-5">
            <div class="h-24 sm:h-32 bg-gradient-to-r from-cm-green to-cm-green-light
                @if($user->cover_photo) bg-cover bg-center @endif"
                 @if($user->cover_photo) style="background-image:url('{{ asset('storage/' . $user->cover_photo) }}')" @endif></div>

            <div class="px-4 sm:px-6 pb-5">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-12">
                    {{-- Avatar --}}
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full border-4 border-white shadow-lg overflow-hidden bg-cm-green/10 grid place-items-center text-3xl font-bold text-cm-green shrink-0 mx-auto sm:mx-0">
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
                            <button type="button"
                                    @click="$dispatch('open-gomarket-chat', { sellerId: {{ $user->id }} })"
                                    class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-sm rounded-full px-4 py-2 transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                <span x-data x-text="$store.lang.t('Message','Message')"></span>
                            </button>
                        </div>
                    @endunless
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 text-center sm:text-left">
                    <a href="{{ route('user.profile', ['username' => $user->username]) }}" wire:navigate
                       class="text-[13px] font-semibold text-cm-green hover:underline">
                        {{ $lang === 'fr' ? 'Voir le profil complet' : 'View full profile' }} →
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Listings ── --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <h2 class="text-lg font-extrabold text-slate-900">
                {{ $isSelf ? ($lang === 'fr' ? 'Mes annonces' : 'Your listings') : ($name . ($lang === 'fr' ? ' — annonces' : "'s listings")) }}
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
                <div class="font-bold text-slate-900">{{ $lang === 'fr' ? 'Aucune annonce active' : 'No active listings' }}</div>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-x-3 gap-y-4">
                @foreach($this->listings as $listing)
                    <x-marketplace.listing-card :listing="$listing" />
                @endforeach
            </div>
            <div class="mt-6">{{ $this->listings->onEachSide(1)->links() }}</div>
        @endif
    </div>
</div>
