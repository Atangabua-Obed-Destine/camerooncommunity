@props([
    'listing',
    'compact' => false,
    // When true, clicking opens the FB-style in-grid modal (via FeedBrowse host)
    // instead of navigating to the full page. Falls back to normal navigation
    // for new-tab / modifier clicks and when JS is unavailable.
    'asModal' => false,
])
@php
    /** @var \App\Models\MarketplaceListing $listing */
    $cover = $listing->coverUrl();
    $lang = app()->getLocale();
    $loc = trim(($listing->city ? $listing->city . ', ' : '') . ($listing->region ?? $listing->country ?? '')) ?: '—';
    $isNew = $listing->created_at && $listing->created_at->gt(now()->subDays(2));
    // Region-approx distance from the viewer's pin, set by FeedBrowse (may be null).
    $dist = $listing->distance_km ?? null;
    $distLabel = $dist !== null ? ($dist < 1 ? '<1 km' : round($dist) . ' km') : null;
@endphp
{{-- Facebook-style card: rounded image sitting on the page background, text
     directly beneath — no card surface, border, lift or zoom. --}}
<a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}"
   @if ($asModal)
       x-on:click="if (!$event.metaKey && !$event.ctrlKey && !$event.shiftKey && !$event.altKey) { $event.preventDefault(); $dispatch('mp-open-listing', { slug: @js($listing->slug), url: @js(route('marketplace.show', ['slug' => $listing->slug])) }); }"
   @else
       wire:navigate
   @endif
   class="mp-card group block">

    {{-- Image --}}
    <div class="relative aspect-square rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 overflow-hidden ring-1 ring-black/5">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $listing->title }}" loading="lazy"
                 class="absolute inset-0 w-full h-full object-cover">
        @else
            <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                <div class="text-5xl mb-1 opacity-70">{{ $listing->category?->icon ?? '🛍️' }}</div>
                <div class="text-[10px] uppercase tracking-wider font-semibold">{{ $listing->category?->localizedName() ?? ($lang === 'fr' ? 'Annonce' : 'Listing') }}</div>
            </div>
        @endif

        {{-- Subtle hover veil (FB signals clickability without moving the card) --}}
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-150"></div>

        {{-- Top-left badges --}}
        <div class="absolute top-2 left-2 flex flex-col gap-1">
            @if ($listing->is_featured)
                <span class="bg-cm-yellow text-slate-900 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md shadow">
                    {{ $lang === 'fr' ? '★ Vedette' : '★ Featured' }}
                </span>
            @endif
            @if ($isNew && !$listing->is_featured)
                <span class="bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md shadow">
                    {{ $lang === 'fr' ? 'Nouveau' : 'Just listed' }}
                </span>
            @endif
        </div>

        {{-- Sold overlay --}}
        @if ($listing->status?->value === 'sold')
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[1px] flex items-center justify-center">
                <span class="bg-cm-red text-white text-xs font-bold uppercase px-3 py-1.5 rounded-full shadow-lg">
                    {{ $lang === 'fr' ? 'Vendu' : 'Sold' }}
                </span>
            </div>
        @endif
    </div>

    {{-- Body — FB order: price (bold) · title · location/distance --}}
    <div class="pt-1.5 px-0.5">
        <div class="text-[15px] font-semibold text-slate-900 truncate leading-tight">
            {{ $listing->formattedPrice($lang) }}
        </div>
        <div class="mt-0.5 text-[13px] text-slate-600 truncate leading-snug">
            {{ $listing->title }}
        </div>
        <div class="mt-0.5 text-[12px] text-slate-500 truncate">
            {{ $loc }}@if ($distLabel) <span class="text-slate-400">· {{ $distLabel }}</span>@endif
        </div>
    </div>
</a>
