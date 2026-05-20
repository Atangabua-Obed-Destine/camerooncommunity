{{-- Marketplace sidebar (FB-style) — light theme always --}}
@php
    $mpLang = app()->getLocale();
    $activeRoute = \Illuminate\Support\Facades\Route::currentRouteName();
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
        <input type="text" wire:model.live.debounce.400ms="query"
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

        <a href="{{ route('marketplace.mine') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.mine' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.mine' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1 12H6L5 9z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('My Listings','Mes Annonces')"></span>
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

        {{-- Location --}}
        <div class="px-1">
            <div class="text-[13px] font-semibold text-slate-700 mb-1" x-data x-text="$store.lang.t('Location','Lieu')"></div>
            <div class="relative">
                <input type="text" wire:model.live.debounce.500ms="region"
                       placeholder="{{ $mpLang === 'fr' ? 'Ex: Douala, Yaoundé…' : 'e.g. Douala, Yaoundé…' }}"
                       class="w-full rounded-lg bg-slate-100 border-0 pl-9 pr-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
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
                <input type="number" min="0" wire:model.live.debounce.600ms="priceMin"
                       placeholder="{{ $mpLang === 'fr' ? 'Min' : 'Min' }}"
                       class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                <input type="number" min="0" wire:model.live.debounce.600ms="priceMax"
                       placeholder="{{ $mpLang === 'fr' ? 'Max' : 'Max' }}"
                       class="w-full rounded-lg bg-slate-100 border-0 px-3 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
            </div>
        </div>
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
    </div>
</div>
