{{--
    Facebook-style icon tab row.
    Shared between desktop (centered, in header) and mobile (own row under logo).
    Props (in $__data):
        - $mode    : 'desktop' | 'mobile'
        - $yardMode: bool  (true when current page is The Yard)
--}}
@php
    $isMobile  = ($mode ?? 'desktop') === 'mobile';
    $yardMode  = $yardMode ?? false;
    $isHome    = request()->routeIs('home');
    $isYard    = $yardMode || request()->routeIs('yard*');
    $isPeople  = request()->routeIs('people') || request()->routeIs('user.profile');

    // Shared geometry — desktop tabs are wider with hover backgrounds; mobile tabs fill row width.
    $tabBase = $isMobile
        ? 'flex-1 flex items-center justify-center h-full'
        : 'flex items-center justify-center h-full px-8 lg:px-10 min-w-[88px] relative';

    // Active underline / color
    $activeBar = 'absolute bottom-0 left-2 right-2 h-1 rounded-t-full bg-cm-yellow';
    $iconActive   = 'text-cm-yellow';
    $iconInactive = 'text-white/70 hover:text-white';
@endphp

{{-- HOME / FEED --}}
<a href="{{ route('home') }}"
   class="{{ $tabBase }} group transition-colors {{ $isHome ? '' : 'hover:bg-white/5' }}"
   :title="$store.lang.t('Home', 'Accueil')"
   aria-label="Home">
    <svg class="h-7 w-7 {{ $isHome ? $iconActive : $iconInactive }} transition-colors" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2.69 3 11h2v9h5v-6h4v6h5v-9h2L12 2.69z"/>
    </svg>
    @if($isHome && !$isMobile)<span class="{{ $activeBar }}"></span>@endif
</a>

{{-- PEOPLE / CONNECTIONS --}}
<a href="{{ route('people') }}"
   class="{{ $tabBase }} relative group transition-colors {{ $isPeople ? '' : 'hover:bg-white/5' }}"
   :title="$store.lang.t('People', 'Personnes')"
   aria-label="People">
    <svg class="h-7 w-7 {{ $isPeople ? $iconActive : $iconInactive }} transition-colors" fill="currentColor" viewBox="0 0 24 24">
        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
    </svg>
    @auth
        <livewire:yard.connections-badge :variant="'dot'" />
    @endauth
    @if($isPeople && !$isMobile)<span class="{{ $activeBar }}"></span>@endif
</a>

{{-- THE YARD / MESSENGER --}}
<a href="{{ route('yard') }}"
   class="{{ $tabBase }} group transition-colors {{ $isYard ? '' : 'hover:bg-white/5' }}"
   :title="$store.lang.t('The Yard', 'Le Yard')"
   aria-label="The Yard">
    <svg class="h-7 w-7 {{ $isYard ? $iconActive : $iconInactive }} transition-colors" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.13 2 11.2c0 2.88 1.46 5.45 3.75 7.13V22l3.43-1.88c.9.25 1.85.38 2.82.38 5.52 0 10-4.13 10-9.2S17.52 2 12 2zm1.05 12.3-2.55-2.72-4.95 2.72 5.45-5.78 2.6 2.72 4.9-2.72-5.45 5.78z"/>
    </svg>
    @if($isYard && !$isMobile)<span class="{{ $activeBar }}"></span>@endif
</a>

{{-- MARKETPLACE --}}
<button type="button"
        @click="window.dispatchEvent(new CustomEvent('open-discover'))"
        class="{{ $tabBase }} group transition-colors hover:bg-white/5"
        :title="$store.lang.t('Marketplace', 'Marketplace')"
        aria-label="Marketplace">
    <svg class="h-7 w-7 {{ $iconInactive }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 13h6"/>
    </svg>
</button>

{{-- Notifications removed from center tabs — available in header right-side actions. --}}
