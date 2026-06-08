@props([
    'seller',
    'listing' => null,
])
@php
    /** @var \App\Models\User $seller */
    $lang = app()->getLocale();
    $isSelf = (int) auth()->id() === (int) $seller->id;
    $sName = $seller->name ?: $seller->username;
    $activeCount = \App\Models\MarketplaceListing::query()->forFeed()->where('user_id', $seller->id)->count();
    $isFollowing = auth()->id() && ! $isSelf
        ? \App\Models\UserFollow::where('follower_id', auth()->id())->where('following_id', $seller->id)->exists()
        : false;
    $sellerUrl = $seller->username ? route('marketplace.seller', ['username' => $seller->username]) : '#';
@endphp
<div class="relative inline-block"
     x-data="{ pop: false, t: null }"
     @mouseenter="clearTimeout(t); pop = true"
     @mouseleave="t = setTimeout(() => pop = false, 220)">

    {{-- Trigger (the seller name/avatar passed in as the slot) --}}
    <a href="{{ $sellerUrl }}" wire:navigate class="inline-flex items-center gap-2 group/seller">{{ $slot }}</a>

    {{-- Hovercard --}}
    <div x-show="pop" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         @mouseenter="clearTimeout(t); pop = true"
         @mouseleave="t = setTimeout(() => pop = false, 220)"
         class="absolute left-0 top-full mt-2 z-50 w-64 bg-white rounded-2xl shadow-2xl ring-1 ring-slate-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-cm-green/15 grid place-items-center text-cm-green font-bold overflow-hidden shrink-0">
                @if($seller->avatar)
                    <img src="{{ asset('storage/' . $seller->avatar) }}" alt="" class="w-full h-full object-cover">
                @else
                    {{ strtoupper(mb_substr($sName, 0, 1)) }}
                @endif
            </div>
            <div class="min-w-0">
                <div class="font-bold text-slate-900 truncate">{{ $sName }}</div>
                <div class="text-[12px] text-slate-500">{{ $activeCount }} {{ $lang === 'fr' ? 'annonces' : 'listings' }} · {{ $lang === 'fr' ? 'depuis' : 'since' }} {{ $seller->created_at?->translatedFormat('Y') }}</div>
            </div>
        </div>

        @unless($isSelf)
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" wire:click="toggleFollow({{ $seller->id }})"
                        @class([
                            'text-xs font-bold rounded-full py-1.5 transition',
                            'bg-cm-green text-white hover:bg-cm-green/90' => ! $isFollowing,
                            'bg-slate-100 text-slate-700 ring-1 ring-slate-300 hover:bg-slate-200' => $isFollowing,
                        ])>
                    {{ $isFollowing ? ($lang === 'fr' ? '✓ Abonné' : '✓ Following') : ($lang === 'fr' ? '+ Suivre' : '+ Follow') }}
                </button>
                <button type="button"
                        @click="$dispatch('open-gomarket-chat', { sellerId: {{ $seller->id }}, listingId: {{ $listing?->id ?? 'null' }} })"
                        class="text-xs font-bold rounded-full py-1.5 bg-slate-100 text-slate-800 hover:bg-slate-200 transition">
                    {{ $lang === 'fr' ? 'Message' : 'Message' }}
                </button>
            </div>
        @endunless

        <a href="{{ $sellerUrl }}" wire:navigate
           class="mt-2 block text-center text-[12px] font-semibold text-cm-green hover:underline">
            {{ $lang === 'fr' ? 'Voir la boutique' : 'View GoMarket profile' }} →
        </a>
    </div>
</div>
