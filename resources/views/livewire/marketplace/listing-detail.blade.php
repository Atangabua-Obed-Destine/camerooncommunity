@php
    $lang = app()->getLocale();
    $media = $listing->media;
    $cover = $media->isNotEmpty() ? ($media[$activeMediaIndex] ?? $media->first()) : null;
    $seller = $listing->seller;
    $isOwner = $listing->isOwnedBy(auth()->id());
    $isSold = $listing->status->value === 'sold';
    $myOffer = $this->myOffer;
    $pendingOffers = $this->pendingOffers;
    $canOffer = in_array($listing->price_type->value, ['fixed','negotiable']) && ! $isOwner && ! $isSold;
    $saveLabelEn = $this->isFavorited() ? 'Saved' : 'Save';
    $saveLabelFr = $this->isFavorited() ? 'Sauvegardé' : 'Sauvegarder';
    $locStr = trim(($listing->city ? $listing->city . ', ' : '') . ($listing->region ?? $listing->country ?? ''));
@endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100 pb-24 lg:pb-8">
    <div class="max-w-6xl mx-auto px-3 sm:px-4 lg:px-6 py-4 lg:py-5">

        {{-- ─── Breadcrumb ─── --}}
        <nav class="text-xs text-slate-600 mb-4 flex items-center gap-1.5 truncate">
            <a href="{{ route('marketplace.index') }}" wire:navigate class="hover:text-cm-green font-medium">GoMarket</a>
            @if ($listing->category)
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('marketplace.category', ['slug' => $listing->category->slug]) }}" wire:navigate class="hover:text-cm-green font-medium">
                    {{ $listing->category->localizedName() }}
                </a>
            @endif
            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-800 font-semibold truncate">{{ $listing->title }}</span>
        </nav>

        <div class="grid lg:grid-cols-[1.55fr_1fr] gap-4 lg:gap-5">

            {{-- ─── Gallery ─── --}}
            <section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
                <div class="relative aspect-[4/3] sm:aspect-[16/11] bg-slate-900 group">
                    @if ($cover)
                        <img src="{{ $cover->url() }}" alt="{{ $listing->title }}"
                             fetchpriority="high" decoding="async"
                             class="absolute inset-0 w-full h-full object-contain">
                    @else
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-gradient-to-br from-slate-100 to-slate-200">
                            <div class="text-7xl opacity-70">{{ $listing->category?->icon ?? '📦' }}</div>
                            <div class="text-sm uppercase tracking-wider font-bold text-slate-500">{{ $listing->category?->localizedName() ?? ($lang === 'fr' ? 'Aucune photo' : 'No photo') }}</div>
                        </div>
                    @endif

                    @if ($media->count() > 1)
                        <button type="button" wire:click="setMedia({{ max(0, $activeMediaIndex - 1) }})"
                                @class([
                                    'absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/95 hover:bg-white text-slate-900 grid place-items-center shadow-lg transition',
                                    'opacity-30 cursor-not-allowed' => $activeMediaIndex === 0,
                                ])>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" wire:click="setMedia({{ min($media->count() - 1, $activeMediaIndex + 1) }})"
                                @class([
                                    'absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/95 hover:bg-white text-slate-900 grid place-items-center shadow-lg transition',
                                    'opacity-30 cursor-not-allowed' => $activeMediaIndex >= $media->count() - 1,
                                ])>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <span class="absolute bottom-3 right-3 text-[11px] font-bold bg-black/70 text-white px-2.5 py-1 rounded-full">
                            {{ $activeMediaIndex + 1 }} / {{ $media->count() }}
                        </span>
                    @endif

                    @if ($isSold)
                        <div class="absolute top-3 left-3 bg-cm-red text-white text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full shadow-lg">
                            {{ $lang === 'fr' ? 'Vendu' : 'Sold' }}
                        </div>
                    @endif
                    @if ($listing->is_featured && ! $isSold)
                        <div class="absolute top-3 left-3 bg-cm-yellow text-slate-900 text-[11px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-full shadow-lg">
                            ⭐ {{ $lang === 'fr' ? 'En vedette' : 'Featured' }}
                        </div>
                    @endif
                </div>

                @if ($media->count() > 1)
                    <div class="flex gap-2 p-3 overflow-x-auto bg-slate-50 border-t border-slate-200">
                        @foreach ($media as $i => $m)
                            <button type="button" wire:click="setMedia({{ $i }})"
                                    class="relative shrink-0 w-16 h-16 rounded-xl overflow-hidden ring-2 transition
                                    {{ $activeMediaIndex === $i ? 'ring-cm-green scale-105 shadow' : 'ring-transparent hover:ring-slate-300' }}">
                                <img src="{{ $m->thumbnailUrl() }}" loading="lazy" decoding="async" class="w-full h-full object-cover" alt="">
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ─── Info panel ─── --}}
            <aside class="space-y-3 lg:space-y-4">

                {{-- Title + price + actions --}}
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 leading-tight tracking-tight">{{ $listing->title }}</h1>
                    <div class="mt-2 text-3xl font-extrabold text-cm-green leading-none">
                        {{ $listing->formattedPrice($lang) }}
                    </div>
                    <div class="mt-2.5 flex items-center gap-2 text-xs text-slate-600 flex-wrap" title="{{ $listing->published_at }}">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $listing->published_at?->diffForHumans() }}
                        </span>
                        @if ($locStr)
                            <span class="text-slate-400">·</span>
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-cm-red" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                                {{ $locStr }}
                            </span>
                        @endif
                    </div>

                    {{-- Attribute chips --}}
                    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[12px]">
                        @if ($listing->category)
                            <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                                {{ $listing->category->icon }} {{ $listing->category->localizedName() }}
                            </span>
                        @endif
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                            @switch($listing->condition->value)
                                @case('new') 🆕 @break
                                @case('like_new') ✨ @break
                                @case('good') 👍 @break
                                @case('fair') 🆗 @break
                                @case('for_parts') 🔩 @break
                            @endswitch
                            {{ $lang === 'fr' ? $listing->condition->labelFr() : $listing->condition->label() }}
                        </span>
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                            {{ $listing->fulfillment->icon() }} {{ $lang === 'fr' ? $listing->fulfillment->labelFr() : $listing->fulfillment->label() }}
                        </span>
                    </div>

                    {{-- Action buttons (desktop) --}}
                    <div class="mt-4 hidden lg:grid grid-cols-2 gap-2">
                        @if ($isOwner)
                            <a href="{{ route('marketplace.edit', ['listing' => $listing->id]) }}" wire:navigate
                               class="col-span-2 inline-flex items-center justify-center gap-2 bg-cm-green text-white font-bold py-2.5 rounded-full hover:bg-cm-green/90 shadow-md transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                {{ $lang === 'fr' ? 'Modifier l\'annonce' : 'Edit listing' }}
                            </a>
                        @elseif (! $isSold)
                            @if (in_array($listing->price_type->value, ['fixed','negotiable']) && (float) $listing->price > 0 && $seller?->momo_number)
                                <a href="{{ route('marketplace.checkout', ['slug' => $listing->slug]) }}" wire:navigate
                                   class="col-span-2 inline-flex items-center justify-center gap-2 bg-cm-red text-white font-extrabold py-3 rounded-full hover:bg-cm-red/90 shadow-lg text-sm transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    {{ $lang === 'fr' ? 'Acheter maintenant' : 'Buy now' }} · {{ number_format((float)$listing->price,0) }} {{ $listing->currency }}
                                </a>
                            @endif
                            <a href="{{ route('yard') }}?dm={{ $seller?->id }}&listing={{ $listing->uuid }}"
                               class="inline-flex items-center justify-center gap-2 bg-cm-green text-white font-bold py-2.5 rounded-full hover:bg-cm-green/90 shadow-md text-sm transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                {{ $lang === 'fr' ? 'Message' : 'Message' }}
                            </a>
                            <button type="button" wire:click="toggleFavorite"
                                    @class([
                                        'inline-flex items-center justify-center gap-2 font-bold py-2.5 rounded-full text-sm transition',
                                        'bg-cm-red/10 text-cm-red ring-2 ring-cm-red/40 hover:bg-cm-red/15' => $this->isFavorited(),
                                        'bg-slate-100 hover:bg-slate-200 text-slate-800 ring-2 ring-transparent hover:ring-slate-300' => ! $this->isFavorited(),
                                    ])>
                                <svg class="w-4 h-4" fill="{{ $this->isFavorited() ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
                                {{ $lang === 'fr' ? $saveLabelFr : $saveLabelEn }}
                            </button>

                            @if ($canOffer)
                                @if ($myOffer)
                                    @php
                                        $offerColor = match($myOffer->status->value) {
                                            'accepted' => 'ring-emerald-300 bg-emerald-50 text-emerald-900',
                                            'countered' => 'ring-blue-300 bg-blue-50 text-blue-900',
                                            default => 'ring-amber-300 bg-amber-50 text-amber-900',
                                        };
                                    @endphp
                                    <div class="col-span-2 mt-1 p-3 rounded-xl text-xs ring-1 {{ $offerColor }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="font-bold">
                                                    {{ $lang === 'fr' ? 'Votre offre' : 'Your offer' }}: {{ $myOffer->formattedAmount() }}
                                                </div>
                                                <div class="opacity-75 mt-0.5">
                                                    {{ $lang === 'fr' ? $myOffer->status->labelFr() : $myOffer->status->label() }}
                                                    · {{ $myOffer->created_at->diffForHumans() }}
                                                </div>
                                            </div>
                                            @if ($myOffer->status->isOpen())
                                                <button wire:click="withdrawOffer({{ $myOffer->id }})"
                                                        wire:confirm="{{ $lang === 'fr' ? 'Retirer cette offre ?' : 'Withdraw this offer?' }}"
                                                        class="text-xs font-semibold opacity-75 hover:opacity-100 hover:text-cm-red underline">
                                                    {{ $lang === 'fr' ? 'Retirer' : 'Withdraw' }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @elseif ($listing->price_type->value === 'negotiable')
                                    <button type="button" wire:click="openOfferModal"
                                            class="col-span-2 inline-flex items-center justify-center gap-2 bg-cm-yellow text-slate-900 font-extrabold py-2.5 rounded-full hover:bg-cm-yellow/90 shadow-md text-sm transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                        {{ $lang === 'fr' ? 'Faire une offre' : 'Make an offer' }}
                                    </button>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Seller card --}}
                @if ($seller)
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-2.5">{{ $lang === 'fr' ? 'Vendeur' : 'Seller' }}</div>
                        <a href="{{ route('user.profile', ['username' => $seller->username]) }}" class="flex items-center gap-3 group">
                            <div class="relative w-12 h-12 rounded-full bg-cm-green/15 flex items-center justify-center text-cm-green font-bold uppercase overflow-hidden shrink-0">
                                @if ($seller->avatar)
                                    <img src="{{ asset('storage/' . $seller->avatar) }}" alt="" class="w-full h-full object-cover">
                                @else
                                    {{ substr($seller->name ?? 'U', 0, 1) }}
                                @endif
                                @if ($listing->is_verified_seller)
                                    <span class="absolute -bottom-0.5 -right-0.5 bg-blue-500 text-white text-[10px] rounded-full w-4 h-4 grid place-items-center ring-2 ring-white" title="Verified seller">✓</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-slate-900 group-hover:text-cm-green truncate">
                                    {{ $seller->name }}
                                    @if ($listing->is_verified_seller)
                                        <span class="text-blue-500 text-xs ml-0.5" title="Verified">✓</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 truncate">&commat;{{ $seller->username }}</div>
                                @if (($seller->seller_rating_count ?? 0) > 0)
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-cm-yellow text-sm leading-none">
                                            @for ($s = 1; $s <= 5; $s++)
                                                <span class="{{ $s <= round($seller->seller_rating_avg) ? 'text-cm-yellow' : 'text-slate-300' }}">★</span>
                                            @endfor
                                        </span>
                                        <span class="text-[11px] font-semibold text-slate-700">{{ number_format($seller->seller_rating_avg, 1) }}</span>
                                        <span class="text-[11px] text-slate-500">({{ $seller->seller_rating_count }})</span>
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400 mt-0.5 italic">{{ $lang === 'fr' ? 'Aucun avis' : 'No reviews yet' }}</div>
                                @endif
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    {{ $lang === 'fr' ? 'Membre depuis' : 'Joined' }} {{ $seller->created_at?->translatedFormat('M Y') }}
                                </div>
                            </div>
                        </a>
                        {{-- Trust badges (Phase 7) --}}
                        @php $badges = $this->sellerBadges; @endphp
                        @if (! empty($badges))
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($badges as $b)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full ring-1 {{ \App\Support\TrustBadges::chipClasses($b['tone']) }}"
                                          @if (! empty($b['tooltip'])) title="{{ $lang === 'fr' ? ($b['tooltipFr'] ?? $b['tooltip']) : $b['tooltip'] }}" @endif>
                                        <span aria-hidden="true">{{ $b['icon'] }}</span>
                                        {{ $lang === 'fr' ? $b['labelFr'] : $b['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if ($this->sellerIsNew && ! $isOwner)
                            <div class="mt-3 p-2.5 rounded-lg bg-amber-50 ring-1 ring-amber-200 text-[11px] text-amber-800 flex items-start gap-2">
                                <span aria-hidden="true">⚠️</span>
                                <span>{{ $lang === 'fr'
                                    ? 'Nouveau vendeur — payez de préférence à la livraison ou rencontrez-vous dans un lieu public.'
                                    : 'New seller — prefer paying on delivery or meeting in a public place.' }}</span>
                            </div>
                        @endif
                        <div class="mt-3 grid grid-cols-3 text-center text-xs divide-x divide-slate-200 border-t border-slate-100 pt-3">
                            <div class="px-1">
                                <div class="font-extrabold text-slate-900 text-base">{{ $listing->views_count }}</div>
                                <div class="text-[11px] text-slate-500">{{ $lang === 'fr' ? 'Vues' : 'Views' }}</div>
                            </div>
                            <div class="px-1">
                                <div class="font-extrabold text-slate-900 text-base">{{ $listing->favorites_count }}</div>
                                <div class="text-[11px] text-slate-500">{{ $lang === 'fr' ? 'Favoris' : 'Saves' }}</div>
                            </div>
                            <div class="px-1">
                                <div class="font-extrabold text-slate-900 text-base">{{ $listing->messages_count }}</div>
                                <div class="text-[11px] text-slate-500">{{ $lang === 'fr' ? 'Messages' : 'Inquiries' }}</div>
                            </div>
                        </div>
                        {{-- Safety actions (Phase 7) — hide from owner --}}
                        @auth
                            @if (! $isOwner)
                                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <button type="button" wire:click="openReportModal"
                                            class="inline-flex items-center gap-1 text-slate-500 hover:text-cm-red font-semibold transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V5a2 2 0 012-2h11l-2 4 2 4H5"/></svg>
                                        {{ $this->hasReported
                                            ? ($lang === 'fr' ? 'Signalement envoyé' : 'Reported')
                                            : ($lang === 'fr' ? 'Signaler l\'annonce' : 'Report listing') }}
                                    </button>
                                    <button type="button"
                                            wire:click="blockSeller"
                                            wire:confirm="{{ $lang === 'fr' ? 'Bloquer ce vendeur ? Ses annonces seront masquées.' : 'Block this seller? Their listings will be hidden.' }}"
                                            class="inline-flex items-center gap-1 text-slate-500 hover:text-cm-red font-semibold transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-linejoin="round"/><path stroke-linecap="round" d="M5 5l14 14"/></svg>
                                        {{ $lang === 'fr' ? 'Bloquer le vendeur' : 'Block seller' }}
                                    </button>
                                </div>
                            @endif
                        @endauth
                    </div>
                @endif

                {{-- Pending offers (seller side) --}}
                @if ($isOwner && $pendingOffers->isNotEmpty())
                    <div class="bg-white rounded-2xl shadow-sm ring-2 ring-amber-300 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-xs font-bold uppercase tracking-wide text-amber-700 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                {{ $lang === 'fr' ? 'Offres reçues' : 'Offers received' }}
                            </div>
                            <span class="text-[11px] font-bold bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full">{{ $pendingOffers->count() }}</span>
                        </div>
                        <div class="space-y-2.5">
                            @foreach ($pendingOffers as $offer)
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-9 h-9 rounded-full bg-cm-green/15 grid place-items-center text-cm-green text-sm font-bold uppercase shrink-0 overflow-hidden">
                                            @if ($offer->buyer?->avatar)
                                                <img src="{{ asset('storage/' . $offer->buyer->avatar) }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                {{ substr($offer->buyer?->name ?? 'U', 0, 1) }}
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-slate-900 truncate">{{ $offer->buyer?->name }}</div>
                                            <div class="text-lg font-extrabold text-cm-green">{{ $offer->formattedAmount() }}</div>
                                            @if ($offer->message)
                                                <div class="text-xs text-slate-700 mt-1 italic">"{{ $offer->message }}"</div>
                                            @endif
                                            <div class="text-[10px] text-slate-500 mt-1">{{ $offer->created_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                    @if ($counteringOfferId === $offer->id)
                                        <div class="mt-2.5 flex gap-1.5">
                                            <input type="number" min="1" wire:model="counterAmount" wire:keydown.enter="submitCounter"
                                                   class="flex-1 rounded-lg bg-white ring-1 ring-slate-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none">
                                            <button wire:click="submitCounter" class="text-xs font-bold bg-cm-green text-white px-4 rounded-lg hover:bg-cm-green/90">{{ $lang === 'fr' ? 'Envoyer' : 'Send' }}</button>
                                            <button wire:click="cancelCounter" class="text-xs text-slate-500 hover:text-slate-800 px-2">✕</button>
                                        </div>
                                    @else
                                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                                            <button wire:click="acceptOffer({{ $offer->id }})"
                                                    wire:confirm="{{ $lang === 'fr' ? 'Accepter cette offre ?' : 'Accept this offer?' }}"
                                                    class="text-xs font-bold bg-emerald-600 text-white px-3 py-1.5 rounded-full hover:bg-emerald-700 shadow-sm">
                                                ✓ {{ $lang === 'fr' ? 'Accepter' : 'Accept' }}
                                            </button>
                                            <button wire:click="startCounter({{ $offer->id }})"
                                                    class="text-xs font-bold bg-blue-600 text-white px-3 py-1.5 rounded-full hover:bg-blue-700 shadow-sm">
                                                ↩ {{ $lang === 'fr' ? 'Contre-offre' : 'Counter' }}
                                            </button>
                                            <button wire:click="rejectOffer({{ $offer->id }})"
                                                    class="text-xs font-bold bg-slate-200 text-slate-700 px-3 py-1.5 rounded-full hover:bg-slate-300">
                                                ✕ {{ $lang === 'fr' ? 'Refuser' : 'Reject' }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Admin panel --}}
                @if ($this->isAdmin())
                    <div class="bg-white rounded-2xl shadow-sm ring-2 ring-blue-300 p-3.5">
                        <div class="text-xs font-bold uppercase tracking-wide text-blue-700 mb-2.5 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            {{ $lang === 'fr' ? 'Admin' : 'Admin' }}
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="toggleVerifiedSeller"
                                    class="text-xs font-bold py-2 rounded-lg ring-2 transition
                                    {{ $listing->is_verified_seller ? 'bg-blue-600 text-white ring-blue-600 hover:bg-blue-700' : 'bg-white ring-slate-200 text-slate-700 hover:ring-blue-500' }}">
                                ✓ {{ $listing->is_verified_seller ? ($lang === 'fr' ? 'Vérifié' : 'Verified') : ($lang === 'fr' ? 'Vérifier' : 'Verify') }}
                            </button>
                            <button wire:click="toggleFeatured"
                                    class="text-xs font-bold py-2 rounded-lg ring-2 transition
                                    {{ $listing->is_featured ? 'bg-cm-yellow text-slate-900 ring-yellow-500 hover:bg-cm-yellow/90' : 'bg-white ring-slate-200 text-slate-700 hover:ring-yellow-500' }}">
                                ⭐ {{ $listing->is_featured ? ($lang === 'fr' ? 'En vedette' : 'Featured') : ($lang === 'fr' ? 'Mettre en vedette' : 'Feature') }}
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Safety tip --}}
                <div class="text-[12px] text-amber-900 leading-relaxed bg-amber-50 rounded-2xl p-3.5 ring-1 ring-amber-200 flex items-start gap-2">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <div>
                        <strong class="text-amber-800 font-bold">{{ $lang === 'fr' ? 'Conseil de sécurité' : 'Safety tip' }}</strong>
                        <div class="mt-0.5">{{ $lang === 'fr' ? 'Rencontrez-vous dans un lieu public, inspectez l\'article et ne payez jamais à l\'avance.' : 'Meet in a public place, inspect the item, and never pay in advance.' }}</div>
                    </div>
                </div>
            </aside>
        </div>

        {{-- ─── Description ─── --}}
        <section class="mt-5 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
            @php
                $_attrSchema = \App\Support\CategoryAttributeSchema::forCategory($listing->category_id);
                $_attrVals   = is_array($listing->attributes) ? $listing->attributes : [];
            @endphp
            @if (! empty($_attrSchema) && ! empty($_attrVals))
                <div class="mb-4 pb-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900 mb-3">{{ $lang === 'fr' ? 'Caractéristiques' : 'Specifications' }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($_attrSchema as $f)
                            @php($v = $_attrVals[$f['key']] ?? null)
                            @if ($v !== null && $v !== '' && $v !== false)
                                <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-slate-50 ring-1 ring-slate-100">
                                    <span class="text-lg shrink-0">{{ $f['icon'] ?? '•' }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $lang === 'fr' ? $f['labelFr'] : $f['label'] }}</div>
                                        <div class="text-sm font-bold text-slate-900 truncate">{{ \App\Support\CategoryAttributeSchema::displayValue($f, $v, $lang) }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <h2 class="text-base font-bold text-slate-900 mb-3">{{ $lang === 'fr' ? 'Description' : 'Description' }}</h2>
            <div class="prose prose-sm max-w-none whitespace-pre-line text-slate-800 leading-relaxed">
                {{ $listing->description }}
            </div>
            @if (! empty($listing->tags))
                <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap gap-1.5">
                    @foreach ($listing->tags as $tag)
                        <a href="{{ route('marketplace.index', ['query' => $tag]) }}" wire:navigate
                           class="text-xs px-2.5 py-1 rounded-full bg-cm-green/10 text-cm-green font-semibold hover:bg-cm-green hover:text-white transition">#{{ $tag }}</a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ─── Reviews ─── --}}
        @php($_reviews = $this->reviews)
        @if ($isSold || $_reviews->isNotEmpty() || $this->canLeaveReview)
            <section class="mt-5 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 lg:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="text-cm-yellow text-xl">★</span>
                        {{ $lang === 'fr' ? 'Avis sur cette transaction' : 'Reviews of this sale' }}
                        @if ($_reviews->isNotEmpty())
                            <span class="text-sm font-medium text-slate-500">({{ $_reviews->count() }})</span>
                        @endif
                    </h2>
                </div>

                @if ($this->canLeaveReview)
                    <a href="{{ route('marketplace.review', ['slug' => $listing->slug]) }}" wire:navigate
                       class="block mb-4 p-3 rounded-xl bg-cm-green/5 ring-1 ring-cm-green/30 hover:bg-cm-green/10 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 grid place-items-center rounded-full bg-cm-green text-white text-xl">✨</div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-slate-900">
                                    @if ($this->myReview)
                                        {{ $lang === 'fr' ? 'Modifier votre avis' : 'Update your review' }}
                                    @else
                                        {{ $lang === 'fr' ? 'Évaluez votre achat' : 'Rate your purchase' }}
                                    @endif
                                </div>
                                <div class="text-[12px] text-slate-600">
                                    {{ $lang === 'fr' ? 'Partagez votre expérience avec ce vendeur.' : 'Share your experience with this seller.' }}
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-cm-green" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </a>
                @endif

                @if ($_reviews->isEmpty())
                    <div class="text-sm text-slate-500 italic py-2">
                        {{ $lang === 'fr' ? 'Aucun avis pour le moment.' : 'No reviews yet.' }}
                    </div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($_reviews as $rev)
                            <li class="py-3 flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-200 grid place-items-center text-sm font-bold text-slate-700 overflow-hidden shrink-0">
                                    @if ($rev->reviewer?->avatar)
                                        <img src="{{ asset('storage/' . $rev->reviewer->avatar) }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        {{ mb_strtoupper(mb_substr($rev->reviewer?->name ?: $rev->reviewer?->username ?: 'U', 0, 1)) }}
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-slate-900 text-sm">{{ $rev->reviewer?->name ?: $rev->reviewer?->username }}</span>
                                        @if ($rev->is_buyer_verified)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-cm-green bg-cm-green/10 px-2 py-0.5 rounded-full">
                                                ✓ {{ $lang === 'fr' ? 'Acheteur vérifié' : 'Verified buyer' }}
                                            </span>
                                        @endif
                                        <span class="text-cm-yellow text-sm">
                                            @for ($s = 1; $s <= 5; $s++)
                                                <span class="{{ $s <= $rev->rating ? 'text-cm-yellow' : 'text-slate-300' }}">★</span>
                                            @endfor
                                        </span>
                                        <span class="text-[11px] text-slate-400">{{ $rev->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if ($rev->comment)
                                        <p class="mt-1 text-[14px] text-slate-700 leading-snug whitespace-pre-line">{{ $rev->comment }}</p>
                                    @endif
                                    @if ($rev->reply)
                                        <div class="mt-2 ml-2 pl-3 border-l-2 border-cm-green/30">
                                            <div class="text-[11px] font-bold uppercase tracking-wide text-cm-green">{{ $lang === 'fr' ? 'Réponse du vendeur' : 'Seller reply' }}</div>
                                            <p class="text-[13px] text-slate-700 mt-0.5 whitespace-pre-line">{{ $rev->reply }}</p>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- ─── Similar listings ─── --}}
        @if ($this->similarListings->isNotEmpty())
            <section class="mt-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $lang === 'fr' ? 'Annonces similaires' : 'Similar listings' }}
                    </h2>
                    @if ($listing->category)
                        <a href="{{ route('marketplace.category', ['slug' => $listing->category->slug]) }}" wire:navigate
                           class="text-sm text-cm-green font-semibold hover:underline">
                            {{ $lang === 'fr' ? 'Voir plus' : 'See more' }} →
                        </a>
                    @endif
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($this->similarListings as $similar)
                        <x-marketplace.listing-card :listing="$similar" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- ─── Sticky mobile action bar ─── --}}
    @if (! $isOwner && ! $isSold)
        <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-200 px-3 py-2.5 shadow-2xl">
            <div class="flex items-center gap-2">
                <button wire:click="toggleFavorite"
                        class="w-11 h-11 grid place-items-center rounded-full shrink-0 {{ $this->isFavorited() ? 'bg-cm-red/10 text-cm-red ring-2 ring-cm-red/40' : 'bg-slate-100 text-slate-700' }}">
                    <svg class="w-5 h-5" fill="{{ $this->isFavorited() ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
                </button>
                @if ($canOffer && ! $myOffer && $listing->price_type->value === 'negotiable')
                    <button wire:click="openOfferModal"
                            class="flex-1 h-11 rounded-full bg-cm-yellow text-slate-900 font-extrabold text-sm grid place-items-center shadow-md">
                        🏷️ <span class="ml-1">{{ $lang === 'fr' ? 'Offre' : 'Offer' }}</span>
                    </button>
                @endif
                <a href="{{ route('yard') }}?dm={{ $seller?->id }}&listing={{ $listing->uuid }}"
                   class="flex-[1.4] h-11 rounded-full bg-cm-green text-white font-extrabold text-sm grid place-items-center shadow-md">
                    💬 <span class="ml-1">{{ $lang === 'fr' ? 'Message' : 'Message' }}</span>
                </a>
            </div>
            @if (in_array($listing->price_type->value, ['fixed','negotiable']) && (float) $listing->price > 0 && $seller?->momo_number)
                <a href="{{ route('marketplace.checkout', ['slug' => $listing->slug]) }}" wire:navigate
                   class="mt-2 w-full h-11 rounded-full bg-cm-red text-white font-extrabold text-sm grid place-items-center shadow-md">
                    🛒 {{ $lang === 'fr' ? 'Acheter maintenant' : 'Buy now' }} · {{ number_format((float)$listing->price,0) }} {{ $listing->currency }}
                </a>
            @endif
            @if ($myOffer)
                <div class="mt-2 text-center text-[11px] text-slate-600">
                    {{ $lang === 'fr' ? 'Votre offre' : 'Your offer' }}:
                    <span class="font-bold text-cm-green">{{ $myOffer->formattedAmount() }}</span>
                    · {{ $lang === 'fr' ? $myOffer->status->labelFr() : $myOffer->status->label() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ─── Make-an-Offer Modal ─── --}}
    @if ($showOfferModal)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/60 backdrop-blur-sm p-4"
             wire:click.self="$set('showOfferModal', false)"
             x-data x-trap.noscroll>
            <div class="bg-white rounded-2xl shadow-2xl ring-1 ring-slate-200 max-w-md w-full p-5"
                 x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="font-extrabold text-xl text-slate-900 flex items-center gap-2">
                            <span class="w-9 h-9 grid place-items-center rounded-full bg-cm-yellow/20 text-cm-yellow text-lg">🏷️</span>
                            {{ $lang === 'fr' ? 'Faire une offre' : 'Make an offer' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-1 ml-11">
                            {{ $lang === 'fr' ? 'Prix demandé' : 'Asking' }}: <span class="font-bold text-cm-green">{{ $listing->formattedPrice($lang) }}</span>
                        </p>
                    </div>
                    <button wire:click="$set('showOfferModal', false)" class="w-9 h-9 grid place-items-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700 text-xl leading-none">✕</button>
                </div>

                <label class="block">
                    <span class="text-[13px] font-bold text-slate-700">{{ $lang === 'fr' ? 'Votre offre' : 'Your offer' }}</span>
                    <div class="mt-1.5 flex rounded-xl bg-slate-100 focus-within:bg-white focus-within:ring-2 focus-within:ring-cm-green transition overflow-hidden">
                        <input type="number" min="1" step="any" wire:model="offerAmount" autofocus
                               class="flex-1 bg-transparent border-0 px-3.5 py-3 text-xl font-bold text-slate-900 focus:outline-none focus:ring-0">
                        <span class="px-4 py-3 bg-slate-200 text-sm font-bold text-slate-700">{{ $listing->currency }}</span>
                    </div>
                    @error('offerAmount') <p class="text-xs text-cm-red mt-1 font-medium">⚠ {{ $message }}</p> @enderror
                </label>

                <label class="block mt-3">
                    <span class="text-[13px] font-bold text-slate-700">{{ $lang === 'fr' ? 'Message (optionnel)' : 'Message (optional)' }}</span>
                    <textarea wire:model="offerMessage" rows="3" maxlength="500"
                              placeholder="{{ $lang === 'fr' ? 'Ex: Je peux passer prendre aujourd\'hui...' : 'e.g. I can pick it up today...' }}"
                              class="mt-1.5 w-full rounded-xl bg-slate-100 border-0 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition resize-y"></textarea>
                </label>

                <div class="mt-3 text-[11px] text-slate-600 bg-blue-50 rounded-xl p-3 ring-1 ring-blue-100 flex items-start gap-2">
                    <span class="text-base">💡</span>
                    <span>{{ $lang === 'fr' ? 'Votre offre est valable 7 jours. Le vendeur peut accepter, refuser ou faire une contre-offre.' : 'Your offer is valid for 7 days. The seller can accept, decline, or counter.' }}</span>
                </div>

                <div class="mt-5 flex gap-2">
                    <button wire:click="$set('showOfferModal', false)"
                            class="flex-1 py-2.5 rounded-full ring-2 ring-slate-200 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        {{ $lang === 'fr' ? 'Annuler' : 'Cancel' }}
                    </button>
                    <button wire:click="submitOffer" wire:loading.attr="disabled" wire:target="submitOffer"
                            class="flex-[1.5] py-2.5 rounded-full bg-cm-green text-white text-sm font-extrabold hover:bg-cm-green/90 shadow-md disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <svg wire:loading wire:target="submitOffer" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        🏷️ {{ $lang === 'fr' ? 'Envoyer l\'offre' : 'Send offer' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Report modal (Phase 7) --}}
    @if ($showReportModal)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-900/60 backdrop-blur-sm p-4"
             wire:click.self="closeReportModal">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-5">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                            <span aria-hidden="true">🚩</span>
                            {{ $lang === 'fr' ? 'Signaler cette annonce' : 'Report this listing' }}
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ $lang === 'fr'
                                ? 'Aidez-nous à protéger la communauté. Notre équipe examinera ce signalement.'
                                : 'Help keep the community safe. Our team will review this report.' }}
                        </div>
                    </div>
                    <button wire:click="closeReportModal" class="w-9 h-9 grid place-items-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700 text-xl leading-none">✕</button>
                </div>

                <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-600 mb-1">
                    {{ $lang === 'fr' ? 'Raison' : 'Reason' }}
                </label>
                <select wire:model="reportReason"
                        class="w-full rounded-lg ring-1 ring-slate-300 focus:ring-2 focus:ring-cm-green px-3 py-2 text-sm bg-white">
                    <option value="scam">{{ $lang === 'fr' ? 'Arnaque / fraude' : 'Scam / fraud' }}</option>
                    <option value="spam">{{ $lang === 'fr' ? 'Spam ou doublon' : 'Spam or duplicate' }}</option>
                    <option value="inappropriate">{{ $lang === 'fr' ? 'Contenu inapproprié' : 'Inappropriate content' }}</option>
                    <option value="misinformation">{{ $lang === 'fr' ? 'Fausse information / description trompeuse' : 'Misleading description' }}</option>
                    <option value="harassment">{{ $lang === 'fr' ? 'Harcèlement' : 'Harassment' }}</option>
                    <option value="other">{{ $lang === 'fr' ? 'Autre' : 'Other' }}</option>
                </select>
                @error('reportReason') <div class="text-xs text-cm-red mt-1">{{ $message }}</div> @enderror

                <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-600 mt-3 mb-1">
                    {{ $lang === 'fr' ? 'Détails (optionnel)' : 'Details (optional)' }}
                </label>
                <textarea wire:model="reportDetails" rows="3" maxlength="1000"
                          placeholder="{{ $lang === 'fr' ? 'Donnez-nous plus de contexte…' : 'Give us more context…' }}"
                          class="w-full rounded-lg ring-1 ring-slate-300 focus:ring-2 focus:ring-cm-green px-3 py-2 text-sm"></textarea>
                @error('reportDetails') <div class="text-xs text-cm-red mt-1">{{ $message }}</div> @enderror

                <div class="mt-4 flex gap-2">
                    <button wire:click="closeReportModal"
                            class="flex-1 py-2.5 rounded-full ring-2 ring-slate-200 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        {{ $lang === 'fr' ? 'Annuler' : 'Cancel' }}
                    </button>
                    <button wire:click="submitReport" wire:loading.attr="disabled" wire:target="submitReport"
                            class="flex-[1.5] py-2.5 rounded-full bg-cm-red text-white text-sm font-extrabold hover:bg-cm-red/90 shadow-md disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <svg wire:loading wire:target="submitReport" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        🚩 {{ $lang === 'fr' ? 'Envoyer le signalement' : 'Submit report' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
