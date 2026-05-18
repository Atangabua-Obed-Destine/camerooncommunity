@props([
    'listing',
    'compact' => false,
])
@php
    /** @var \App\Models\MarketplaceListing $listing */
    $cover = $listing->coverUrl();
    $lang = app()->getLocale();
    $loc = trim(($listing->city ? $listing->city . ', ' : '') . ($listing->region ?? $listing->country ?? '')) ?: '—';
    $isNew = $listing->created_at && $listing->created_at->gt(now()->subDays(2));
@endphp
<a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}"
   wire:navigate
   class="mp-card group block rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden hover:ring-cm-green/50 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">

    {{-- Image --}}
    <div class="relative aspect-square bg-gradient-to-br from-slate-100 to-slate-200 overflow-hidden">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $listing->title }}" loading="lazy"
                 class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-300">
        @else
            <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                <div class="text-5xl mb-1 opacity-70">{{ $listing->category?->icon ?? '🛍️' }}</div>
                <div class="text-[10px] uppercase tracking-wider font-semibold">{{ $listing->category?->localizedName() ?? ($lang === 'fr' ? 'Annonce' : 'Listing') }}</div>
            </div>
        @endif

        {{-- Top-left badges --}}
        <div class="absolute top-2 left-2 flex flex-col gap-1">
            @if ($listing->is_featured)
                <span class="bg-cm-yellow text-slate-900 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full shadow">
                    {{ $lang === 'fr' ? '★ Vedette' : '★ Featured' }}
                </span>
            @endif
            @if ($isNew && !$listing->is_featured)
                <span class="bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full shadow">
                    {{ $lang === 'fr' ? 'Nouveau' : 'Just listed' }}
                </span>
            @endif
        </div>

        {{-- Top-right category icon (only if cover image exists) --}}
        @if ($listing->category?->icon && $cover)
            <span class="absolute top-2 right-2 text-base bg-white/95 backdrop-blur rounded-full w-7 h-7 flex items-center justify-center shadow-sm">
                {{ $listing->category->icon }}
            </span>
        @endif

        {{-- Sold overlay --}}
        @if ($listing->status?->value === 'sold')
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[1px] flex items-center justify-center">
                <span class="bg-cm-red text-white text-xs font-bold uppercase px-3 py-1.5 rounded-full shadow-lg">
                    {{ $lang === 'fr' ? 'Vendu' : 'Sold' }}
                </span>
            </div>
        @endif
    </div>

    {{-- Body (FB-style: price prominent, title smaller, location) --}}
    <div class="p-3">
        <div class="text-[16px] font-bold text-slate-900 truncate leading-tight">
            {{ $listing->formattedPrice($lang) }}
        </div>
        <div class="mt-0.5 text-[13px] text-slate-700 line-clamp-2 leading-snug min-h-[34px]">
            {{ $listing->title }}
        </div>
        <div class="mt-1.5 flex items-center gap-1 text-[11px] text-slate-500 truncate">
            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
            <span class="truncate">{{ $loc }}</span>
        </div>
    </div>
</a>
