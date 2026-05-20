{{--
    Facebook-style icon tab row.
    Shared between desktop (centered, in header) and mobile (own row under logo).

    Tabs mirror the Yard sidebar:
        - Home          (live)
        - The Yard      (live)
        - Marketplace   (coming soon — non-clickable, shows label)
        - EasyGoParcel  (coming soon)
        - RoadFam       (coming soon)
        - WorkConnect   (coming soon)

    Props (in $__data):
        - $mode    : 'desktop' | 'mobile'
        - $yardMode: bool  (true when current page is The Yard)
--}}
@php
    $isMobile  = ($mode ?? 'desktop') === 'mobile';
    $yardMode  = $yardMode ?? false;
    $isHome        = request()->routeIs('home');
    $isYard        = $yardMode || request()->routeIs('yard*');
    $isMarketplace = request()->routeIs('marketplace.*');

    // Shared geometry — desktop tabs are wider with hover backgrounds;
    // mobile tabs fill row width but share a tighter padding so 6 tabs fit
    // comfortably on a 360px-wide phone.
    $tabBase = $isMobile
        ? 'flex-1 min-w-0 flex items-center justify-center h-full relative px-0.5'
        : 'flex items-center justify-center h-full px-6 lg:px-7 min-w-[72px] relative';

    // Responsive icon sizing
    $iconSize = $isMobile ? 'h-6 w-6' : 'h-7 w-7';

    // Live (working) tabs always render bright white + bold so they're clearly
    // visible & legible. The currently-selected one gets an extra pill wrapper
    // + slight scale + yellow underline. Coming-soon tabs are dimmed.
    $activeBar     = 'absolute bottom-0 left-2 right-2 h-1 rounded-t-full bg-cm-yellow';
    $iconActive    = 'text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.55)] scale-110';
    $iconInactive  = 'text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]';
    $iconSoon      = 'text-white/40 hover:text-white/70';
    $activeWrapper = 'bg-white/15 ring-1 ring-white/30';
@endphp

{{-- HOME / FEED (labeled "The Yard") --}}
<a href="{{ route('home') }}"
   class="{{ $tabBase }} group transition-colors {{ $isHome ? $activeWrapper : 'hover:bg-white/5' }}"
   :title="$store.lang.t('The Yard', 'Le Yard')"
   aria-label="The Yard">
    <svg class="{{ $iconSize }} {{ $isHome ? $iconActive : $iconInactive }} transition-colors" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2.69 3 11h2v9h5v-6h4v6h5v-9h2L12 2.69z"/>
    </svg>
    @if($isHome && !$isMobile)<span class="{{ $activeBar }}"></span>@endif
</a>

{{-- THE YARD / MESSENGER (labeled "Go Connect") --}}
<a href="{{ route('yard') }}"
   class="{{ $tabBase }} group transition-colors {{ $isYard ? $activeWrapper : 'hover:bg-white/5' }}"
   :title="$store.lang.t('Go Connect', 'Go Connect')"
   aria-label="Go Connect">
    <svg class="{{ $iconSize }} {{ $isYard ? $iconActive : $iconInactive }} transition-colors" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.13 2 11.2c0 2.88 1.46 5.45 3.75 7.13V22l3.43-1.88c.9.25 1.85.38 2.82.38 5.52 0 10-4.13 10-9.2S17.52 2 12 2zm1.05 12.3-2.55-2.72-4.95 2.72 5.45-5.78 2.6 2.72 4.9-2.72-5.45 5.78z"/>
    </svg>
    @if($isYard && !$isMobile)<span class="{{ $activeBar }}"></span>@endif
</a>

{{-- MARKETPLACE (labeled "GoMarket") --}}
<a href="{{ route('marketplace.index') }}"
   class="{{ $tabBase }} group transition-colors {{ $isMarketplace ? $activeWrapper : 'hover:bg-white/5' }}"
   :title="$store.lang.t('GoMarket', 'GoMarket')"
   aria-label="GoMarket">
    <svg class="{{ $iconSize }} {{ $isMarketplace ? $iconActive : $iconInactive }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="{{ $isMarketplace ? '2.75' : '2.5' }}">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 13h6"/>
    </svg>
    @if($isMarketplace && !$isMobile)<span class="{{ $activeBar }}"></span>@endif
</a>

@php
    // Coming-soon tabs share the same Alpine snippet: hover (desktop) or
    // tap (mobile) toggles a small label badge below the icon. Tapping
    // does NOT navigate anywhere.
    $soonTabs = [
        ['key' => 'easygoparcel', 'en' => 'EasyGoParcel', 'fr' => 'EasyGoParcel'],
        ['key' => 'roadfam',      'en' => 'GoRide',       'fr' => 'GoRide'],
        ['key' => 'workconnect',  'en' => 'WorkConnect',  'fr' => 'WorkConnect'],
    ];
@endphp

@foreach($soonTabs as $tab)
    <button type="button"
            x-data="{ show: false, _t: null }"
            @mouseenter="show = true"
            @mouseleave="show = false"
            @click.prevent="show = true; clearTimeout(_t); _t = setTimeout(() => show = false, 1600)"
            class="{{ $tabBase }} group transition-colors cursor-default text-white"
            :title="$store.lang.t(@js($tab['en'] . ' — Coming Soon'), @js($tab['fr'] . ' — Bientôt'))"
            aria-label="{{ $tab['en'] }}">
        @switch($tab['key'])
            @case('easygoparcel')
                <svg class="{{ $iconSize }} {{ $iconSoon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                </svg>
                @break
            @case('roadfam')
                <svg class="{{ $iconSize }} {{ $iconSoon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm10.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-10.5 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                </svg>
                @break
            @case('workconnect')
                <svg class="{{ $iconSize }} {{ $iconSoon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/>
                </svg>
                @break
        @endswitch

        {{-- Floating "Coming Soon" label, anchored under the icon on both
             mobile and desktop. The rightmost tab (workconnect) anchors to
             its right edge so the label never clips the viewport. --}}
        <span x-show="show" x-transition.opacity.duration.150ms x-cloak
              class="absolute top-full mt-1 z-[60] whitespace-nowrap rounded-md bg-cm-yellow px-2 py-1 text-[11px] font-bold text-slate-900 shadow-lg ring-1 ring-black/10
                     @if($loop->last) right-1 @else left-1/2 -translate-x-1/2 @endif"
              x-text="$store.lang.t(@js($tab['en'] . ' — Coming Soon'), @js($tab['fr'] . ' — Bientôt'))"></span>
    </button>
@endforeach
