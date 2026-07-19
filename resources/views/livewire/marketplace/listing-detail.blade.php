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
    $hasGeo = $listing->latitude && $listing->longitude;
    $mapQuery = $hasGeo ? ($listing->latitude . ',' . $listing->longitude) : ($locStr ?: ($listing->country ?? 'Cameroon'));
    $canBuy = ! $isOwner && ! $isSold && in_array($listing->price_type->value, ['fixed','negotiable']) && (float) $listing->price > 0 && $seller?->momo_number;
    $closeJs = "if (document.referrer.includes(window.location.host)) { window.history.back() } else { window.Livewire.navigate('" . route('marketplace.index') . "') }";
    // In modal mode the FeedBrowse host owns history/scroll, so we just ask it to close.
    $closeAction = $asModal ? "\$dispatch('mp-close-listing')" : $closeJs;
@endphp

{{-- ───────────────────────────────────────────────────────────────────
     Facebook-Marketplace-style item view. Rendered either as a full page
     (route marketplace.show) or as an in-grid modal nested in FeedBrowse
     ($asModal). Sits above the app top bar (z-50), below the chat dock
     (z-70). The ✕ button returns to the grid (modal) or to wherever the
     buyer came from (full page).
──────────────────────────────────────────────────────────────────── --}}
<div>
    {{-- In modal mode, teleport the overlay to <body> so it escapes the
         .yard-container stacking context (position:fixed) and renders above
         the app top bar (z-50) — same trick the chat dock uses. --}}
    @if ($asModal) @teleport('body') @endif
    <div>{{-- single-root wrapper (x-teleport requires one root) --}}
    @if ($asModal)
        {{-- Dimmed grid behind, FB-style --}}
        <div class="fixed inset-0 z-[58] bg-black/70 backdrop-blur-sm"
             x-data x-on:click="{{ $closeAction }}"></div>
    @endif

    <div class="fixed z-[60] bg-white flex flex-col lg:flex-row overflow-hidden {{ $asModal ? 'inset-0 lg:inset-4 xl:inset-8 lg:rounded-2xl lg:shadow-2xl ring-1 ring-black/10' : 'inset-0' }}"
         x-data="{ lb: false, li: 0, limgs: @js($media->map(fn ($m) => $m->url())->values()) }"
         x-on:keydown.escape.window="lb ? (lb = false) : ({{ $closeAction }})"
         x-on:keydown.arrow-left.window="if (lb && limgs.length) li = (li - 1 + limgs.length) % limgs.length"
         x-on:keydown.arrow-right.window="if (lb && limgs.length) li = (li + 1) % limgs.length">

        {{-- ═══ MOBILE HEADER (Sticky top bar with Back button) ═══ --}}
        <header class="lg:hidden flex-none bg-white border-b border-slate-200 px-2 py-2 flex items-center gap-2 shrink-0 relative z-20">
            <button type="button" x-on:click="{{ $closeAction }}" class="p-2 -ml-1 rounded-full hover:bg-slate-100 text-slate-900 transition flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </button>
            <div class="flex-1 min-w-0 font-bold text-slate-900 truncate text-[15px]">
                {{ $listing->title }}
            </div>
            <div class="flex items-center gap-0.5">
                <button type="button" wire:click="toggleFavorite" class="p-2 rounded-full hover:bg-slate-100 {{ $this->isFavorited() ? 'text-cm-red' : 'text-slate-600' }} flex items-center justify-center transition">
                    <svg class="w-5 h-5" fill="{{ $this->isFavorited() ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                </button>
                <button type="button" @click="navigator.share ? navigator.share({ title: @js($listing->title), url: window.location.href }).catch(()=>{}) : (navigator.clipboard.writeText(window.location.href), $dispatch('toast', { type: 'success', message: @js($lang === 'fr' ? 'Lien copié' : 'Link copied') }))" class="p-2 rounded-full hover:bg-slate-100 text-slate-600 flex items-center justify-center transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/></svg>
                </button>
            </div>
        </header>

        {{-- ═══ LEFT · image stage ═══ --}}
        <div class="relative bg-[#18191a] shrink-0 h-[44vh] sm:h-[52vh] lg:h-full lg:flex-1 flex items-center justify-center select-none">

            {{-- Close (Desktop only) --}}
            <button type="button" x-on:click="{{ $closeAction }}"
                    aria-label="{{ $lang === 'fr' ? 'Fermer' : 'Close' }}"
                    class="hidden lg:grid absolute top-3 left-3 z-20 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-slate-900 place-items-center shadow-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

        @if ($cover)
            <img src="{{ $cover->url() }}" alt="{{ $listing->title }}"
                 fetchpriority="high" decoding="async"
                 @click="li = ($wire.activeMediaIndex || 0); lb = true"
                 class="max-w-full max-h-full w-auto h-auto object-contain cursor-zoom-in">
        @else
            <div class="flex flex-col items-center justify-center gap-2 text-slate-300">
                <div class="text-7xl opacity-70">{{ $listing->category?->icon ?? '📦' }}</div>
                <div class="text-sm uppercase tracking-wider font-bold text-slate-400">{{ $listing->category?->localizedName() ?? ($lang === 'fr' ? 'Aucune photo' : 'No photo') }}</div>
            </div>
        @endif

        {{-- Prev / next --}}
        @if ($media->count() > 1)
            <button type="button" wire:click="setMedia({{ max(0, $activeMediaIndex - 1) }})"
                    @class([
                        'absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-slate-900 grid place-items-center shadow-lg transition z-10',
                        'opacity-30 cursor-not-allowed' => $activeMediaIndex === 0,
                    ])>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" wire:click="setMedia({{ min($media->count() - 1, $activeMediaIndex + 1) }})"
                    @class([
                        'absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-slate-900 grid place-items-center shadow-lg transition z-10',
                        'opacity-30 cursor-not-allowed' => $activeMediaIndex >= $media->count() - 1,
                    ])>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
            <span class="absolute bottom-3 right-3 text-[11px] font-bold bg-black/70 text-white px-2.5 py-1 rounded-full z-10">
                {{ $activeMediaIndex + 1 }} / {{ $media->count() }}
            </span>
        @endif

        {{-- Status badge --}}
        @if ($isSold)
            <div class="absolute top-3 left-16 z-10 bg-cm-red text-white text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full shadow-lg">
                {{ $lang === 'fr' ? 'Vendu' : 'Sold' }}
            </div>
        @elseif ($listing->is_featured)
            <div class="absolute top-3 left-16 z-10 bg-cm-yellow text-slate-900 text-[11px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-full shadow-lg">
                ⭐ {{ $lang === 'fr' ? 'En vedette' : 'Featured' }}
            </div>
        @endif

        {{-- Thumbnail strip --}}
        @if ($media->count() > 1)
            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 z-10 flex gap-2 px-3 py-2 max-w-[88%] overflow-x-auto rounded-2xl bg-black/35 backdrop-blur-sm">
                @foreach ($media as $i => $m)
                    <button type="button" wire:click="setMedia({{ $i }})"
                            class="relative shrink-0 w-12 h-12 rounded-lg overflow-hidden ring-2 transition
                            {{ $activeMediaIndex === $i ? 'ring-white scale-105' : 'ring-transparent opacity-70 hover:opacity-100' }}">
                        <img src="{{ $m->thumbnailUrl() }}" loading="lazy" decoding="async" class="w-full h-full object-cover" alt="">
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ RIGHT · scrollable detail panel ═══ --}}
    <aside class="w-full lg:w-[400px] xl:w-[440px] h-full overflow-y-auto bg-white shrink-0 border-l border-slate-200">
        <div class="p-4 sm:p-5 space-y-5">

            {{-- ─── Header: title · price · meta ─── --}}
            <div>
                <h1 class="text-2xl font-bold text-slate-900 leading-tight tracking-tight">{{ $listing->title }}</h1>
                <div class="mt-1.5 text-2xl font-bold text-slate-900 leading-none">
                    {{ $listing->formattedPrice($lang) }}
                </div>
                <div class="mt-2 text-[13px] text-slate-500 leading-snug" title="{{ $listing->published_at }}">
                    {{ $lang === 'fr' ? 'Publié' : 'Listed' }} {{ $listing->published_at?->diffForHumans() }}@if ($locStr) {{ $lang === 'fr' ? 'à' : 'in' }} {{ $locStr }}@endif
                </div>

                {{-- chips --}}
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[12px]">
                    @if ($listing->category)
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                            {{ $listing->category->icon }} {{ $listing->category->localizedName() }}
                        </span>
                    @endif
                    @if (is_array($listing->fulfillment))
                        @foreach ($listing->fulfillment as $fVal)
                            @php $fEnum = \App\Enums\ListingFulfillment::tryFrom($fVal); @endphp
                            @if ($fEnum)
                                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                                    {{ $fEnum->icon() }} {{ $lang === 'fr' ? $fEnum->labelFr() : $fEnum->label() }}
                                </span>
                            @endif
                        @endforeach
                    @elseif ($listing->fulfillment instanceof \App\Enums\ListingFulfillment)
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                            {{ $listing->fulfillment->icon() }} {{ $lang === 'fr' ? $listing->fulfillment->labelFr() : $listing->fulfillment->label() }}
                        </span>
                    @elseif (is_string($listing->fulfillment) && $fEnum = \App\Enums\ListingFulfillment::tryFrom($listing->fulfillment))
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                            {{ $fEnum->icon() }} {{ $lang === 'fr' ? $fEnum->labelFr() : $fEnum->label() }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- ─── Actions ─── --}}
            @if ($isOwner)
                <a href="{{ route('marketplace.edit', ['listing' => $listing->id]) }}" wire:navigate
                   class="w-full inline-flex items-center justify-center gap-2 bg-cm-green text-white font-bold py-2.5 rounded-lg hover:bg-cm-green/90 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ $lang === 'fr' ? 'Modifier l\'annonce' : 'Edit listing' }}
                </a>
            @else
                @if ($canBuy)
                    <a href="{{ route('marketplace.checkout', ['slug' => $listing->slug]) }}" wire:navigate
                       class="w-full inline-flex items-center justify-center gap-2 bg-cm-red text-white font-extrabold py-3 rounded-lg hover:bg-cm-red/90 shadow-sm text-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        {{ $lang === 'fr' ? 'Acheter maintenant' : 'Buy now' }} · {{ number_format((float)$listing->price,0) }} {{ $listing->currency }}
                    </a>
                @endif

                <div class="flex items-stretch gap-2">
                    @unless ($isSold)
                        <button type="button"
                                @click="$dispatch('open-gomarket-chat', { sellerId: {{ $seller?->id }}, listingId: {{ $listing->id }} })"
                                class="flex-1 inline-flex items-center justify-center gap-2 bg-cm-green text-white font-bold py-2.5 rounded-lg hover:bg-cm-green/90 shadow-sm text-sm transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                            @if ($this->hasMessaged)
                                {{ $lang === 'fr' ? 'Message envoyé' : 'Message again' }}
                            @else
                                {{ $lang === 'fr' ? 'Message' : 'Message' }}
                            @endif
                        </button>
                    @endunless
                    <button type="button" wire:click="toggleFavorite"
                            aria-label="{{ $lang === 'fr' ? $saveLabelFr : $saveLabelEn }}"
                            @class([
                                'w-11 grid place-items-center rounded-lg transition shrink-0',
                                'bg-cm-red/10 text-cm-red ring-1 ring-cm-red/40 hover:bg-cm-red/15' => $this->isFavorited(),
                                'bg-slate-100 hover:bg-slate-200 text-slate-700' => ! $this->isFavorited(),
                            ])>
                        <svg class="w-5 h-5" fill="{{ $this->isFavorited() ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                    </button>
                    <button type="button"
                            @click="navigator.share ? navigator.share({ title: @js($listing->title), url: window.location.href }).catch(()=>{}) : (navigator.clipboard.writeText(window.location.href), $dispatch('toast', { type: 'success', message: @js($lang === 'fr' ? 'Lien copié' : 'Link copied') }))"
                            aria-label="{{ $lang === 'fr' ? 'Partager' : 'Share' }}"
                            class="w-11 grid place-items-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/></svg>
                    </button>
                </div>

                {{-- Existing offer status (kept; offers can no longer be created here) --}}
                @if ($canOffer && $myOffer)
                    @php
                        $offerColor = match($myOffer->status->value) {
                            'accepted' => 'ring-emerald-300 bg-emerald-50 text-emerald-900',
                            'countered' => 'ring-blue-300 bg-blue-50 text-blue-900',
                            default => 'ring-amber-300 bg-amber-50 text-amber-900',
                        };
                    @endphp
                    <div class="p-3 rounded-xl text-xs ring-1 {{ $offerColor }}">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <div class="font-bold">{{ $lang === 'fr' ? 'Votre offre' : 'Your offer' }}: {{ $myOffer->formattedAmount() }}</div>
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
                @endif
            @endif

            {{-- ─── Details ─── --}}
            <div class="border-t border-slate-200 pt-4">
                <h2 class="text-lg font-bold text-slate-900 mb-2.5">{{ $lang === 'fr' ? 'Détails' : 'Details' }}</h2>

                <div class="flex items-baseline gap-3 text-sm mb-3">
                    <span class="w-28 shrink-0 text-slate-500">{{ $lang === 'fr' ? 'État' : 'Condition' }}</span>
                    <span class="font-semibold text-slate-900">
                        @switch($listing->condition->value)
                            @case('new') 🆕 @break
                            @case('like_new') ✨ @break
                            @case('good') 👍 @break
                            @case('fair') 🆗 @break
                            @case('for_parts') 🔩 @break
                        @endswitch
                        {{ $lang === 'fr' ? $listing->condition->labelFr() : $listing->condition->label() }}
                    </span>
                </div>

                @php
                    $_attrSchema = \App\Support\CategoryAttributeSchema::forCategory($listing->category_id);
                    $_attrVals   = is_array($listing->attributes) ? $listing->attributes : [];
                @endphp
                @if (! empty($_attrSchema) && ! empty($_attrVals))
                    <div class="space-y-1.5 mb-3">
                        @foreach ($_attrSchema as $f)
                            @php $v = $_attrVals[$f['key']] ?? null; @endphp
                            @if ($v !== null && $v !== '' && $v !== false)
                                <div class="flex items-baseline gap-3 text-sm">
                                    <span class="w-28 shrink-0 text-slate-500">{{ $f['icon'] ?? '•' }} {{ $lang === 'fr' ? $f['labelFr'] : $f['label'] }}</span>
                                    <span class="font-semibold text-slate-900">{{ \App\Support\CategoryAttributeSchema::displayValue($f, $v, $lang) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="prose prose-sm max-w-none whitespace-pre-line text-slate-800 leading-relaxed">
                    {{ $listing->description }}
                </div>

                @if (! empty($listing->tags))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($listing->tags as $tag)
                            <a href="{{ route('marketplace.index', ['query' => $tag]) }}" wire:navigate
                               class="text-xs px-2.5 py-1 rounded-full bg-cm-green/10 text-cm-green font-semibold hover:bg-cm-green hover:text-white transition">#{{ $tag }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ─── Send seller a message ─── --}}
            @if (! $isOwner && $seller)
                <div class="border-t border-slate-200 pt-4">
                    @if ($this->hasMessaged)
                        <div class="flex items-center gap-2 mb-2.5">
                            <svg class="w-5 h-5 text-cm-green" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                            <h2 class="text-base font-bold text-slate-900">{{ $lang === 'fr' ? 'Contacter le vendeur' : 'Contact seller' }}</h2>
                        </div>
                        <button wire:click="messageAgain"
                                class="w-full py-2.5 rounded-lg bg-slate-100 text-slate-900 text-sm font-extrabold hover:bg-slate-200 shadow-sm inline-flex items-center justify-center gap-2 transition ring-1 ring-slate-200">
                            {{ $lang === 'fr' ? 'Envoyer un autre message' : 'Message again' }}
                        </button>
                    @else
                        <div class="flex items-center gap-2 mb-2.5">
                            <svg class="w-5 h-5 text-cm-green" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                            <h2 class="text-base font-bold text-slate-900">{{ $lang === 'fr' ? 'Envoyer un message au vendeur' : 'Send seller a message' }}</h2>
                        </div>
                        <div class="rounded-xl ring-1 ring-slate-200 focus-within:ring-2 focus-within:ring-cm-green overflow-hidden transition">
                            <textarea wire:model="inquiryMessage" rows="2" maxlength="1000"
                                      wire:keydown.enter.prevent="sendInquiry"
                                      placeholder="{{ $lang === 'fr' ? 'Écrivez un message…' : 'Write a message…' }}"
                                      class="w-full border-0 px-3.5 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0 resize-none"></textarea>
                        </div>
                        <button wire:click="sendInquiry" wire:loading.attr="disabled" wire:target="sendInquiry"
                                class="mt-2 w-full py-2.5 rounded-lg bg-cm-green text-white text-sm font-extrabold hover:bg-cm-green/90 shadow-sm disabled:opacity-60 inline-flex items-center justify-center gap-2 transition">
                            <svg wire:loading wire:target="sendInquiry" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            {{ $lang === 'fr' ? 'Envoyer' : 'Send' }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- ─── Location + Google map ─── --}}
            @if ($locStr || $hasGeo)
                <div class="border-t border-slate-200 pt-4">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">{{ $lang === 'fr' ? 'Localisation' : 'Location' }}</h2>
                    @if ($locStr)
                        <div class="text-sm text-slate-600 flex items-center gap-1.5 mb-2.5">
                            <svg class="w-4 h-4 text-cm-red" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                            {{ $locStr }}
                        </div>
                    @endif
                    <div class="rounded-xl overflow-hidden ring-1 ring-slate-200 bg-slate-100 relative h-48 w-full z-10"
                         x-data="{
                            mapLat: {{ $listing->latitude ?? 'null' }},
                            mapLng: {{ $listing->longitude ?? 'null' }},
                            locQuery: @js($mapQuery),
                            initMap() {
                                if (!document.getElementById('leaflet-css')) {
                                    const link = document.createElement('link'); link.id = 'leaflet-css'; link.rel = 'stylesheet'; link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(link);
                                    const script = document.createElement('script'); script.id = 'leaflet-js'; script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; document.body.appendChild(script);
                                }
                                const checkL = setInterval(() => {
                                    if (window.L) {
                                        clearInterval(checkL);
                                        this.$nextTick(() => {
                                            if (this.mapLat && this.mapLng) {
                                                this.renderMap(this.mapLat, this.mapLng);
                                            } else {
                                                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.locQuery)}&limit=1`)
                                                    .then(res => res.json())
                                                    .then(data => {
                                                        if (data && data.length > 0) {
                                                            this.renderMap(parseFloat(data[0].lat), parseFloat(data[0].lon));
                                                        }
                                                    });
                                            }
                                        });
                                    }
                                }, 100);
                            },
                            renderMap(lat, lng) {
                                const map = L.map(this.$refs.displayMap).setView([lat, lng], 13);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);
                                L.circle([lat, lng], {
                                    color: '#10b981',
                                    fillColor: '#10b981',
                                    fillOpacity: 0.25,
                                    radius: 1200
                                }).addTo(map);
                            }
                         }"
                         x-init="initMap()"
                         x-ref="displayMap">
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">{{ $lang === 'fr' ? 'Localisation approximative.' : 'Location is approximate.' }}</p>
                </div>
            @endif

            {{-- ─── Seller card ─── --}}
            @if ($seller)
                <div class="border-t border-slate-200 pt-4">
                    <h2 class="text-lg font-bold text-slate-900 mb-2.5">{{ $lang === 'fr' ? 'Vendeur' : 'Seller details' }}</h2>
                    <div class="flex items-center gap-3">
                        <a href="{{ $seller->username ? route('marketplace.seller', ['username' => $seller->username]) : '#' }}" wire:navigate
                           class="relative w-12 h-12 rounded-full bg-cm-green/15 flex items-center justify-center text-cm-green font-bold uppercase overflow-hidden shrink-0">
                            @if ($seller->avatar)
                                <img src="{{ asset('storage/' . $seller->avatar) }}" alt="" class="w-full h-full object-cover">
                            @else
                                {{ substr($seller->name ?? 'U', 0, 1) }}
                            @endif
                            @if ($listing->is_verified_seller)
                                <span class="absolute -bottom-0.5 -right-0.5 bg-blue-500 text-white text-[10px] rounded-full w-4 h-4 grid place-items-center ring-2 ring-white" title="Verified seller">✓</span>
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <x-marketplace.seller-popover :seller="$seller" :listing="$listing">
                                <span class="font-bold text-slate-900 group-hover/seller:text-cm-green truncate">
                                    {{ $seller->name }}
                                    @if ($listing->is_verified_seller)
                                        <span class="text-blue-500 text-xs ml-0.5" title="Verified">✓</span>
                                    @endif
                                </span>
                            </x-marketplace.seller-popover>
                            <div class="text-xs text-slate-500 truncate">&commat;{{ $seller->username }}</div>
                            @if (($seller->seller_rating_count ?? 0) > 0)
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="text-sm leading-none">
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
                    </div>

                    @unless ($isOwner)
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" wire:click="toggleFollow({{ $seller->id }})"
                                    @class([
                                        'text-xs font-bold rounded-full py-2 transition',
                                        'bg-cm-green text-white hover:bg-cm-green/90' => ! $this->isFollowing($seller->id),
                                        'bg-slate-100 text-slate-700 ring-1 ring-slate-300 hover:bg-slate-200' => $this->isFollowing($seller->id),
                                    ])>
                                {{ $this->isFollowing($seller->id) ? ($lang === 'fr' ? '✓ Abonné' : '✓ Following') : ($lang === 'fr' ? '+ Suivre' : '+ Follow') }}
                            </button>
                            <a href="{{ $seller->username ? route('marketplace.seller', ['username' => $seller->username]) : '#' }}" wire:navigate
                               class="text-xs font-bold rounded-full py-2 bg-slate-100 text-slate-800 hover:bg-slate-200 grid place-items-center transition">
                                {{ $lang === 'fr' ? 'Voir la boutique' : 'View shop' }}
                            </a>
                        </div>
                    @endunless

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

            {{-- ─── Pending offers (seller side) ─── --}}
            @if ($isOwner && $pendingOffers->isNotEmpty())
                <div class="border-t border-slate-200 pt-4">
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

            {{-- ─── Admin panel ─── --}}
            @if ($this->isAdmin())
                <div class="border-t border-slate-200 pt-4">
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

            {{-- ─── Safety tip ─── --}}
            <div class="text-[12px] text-amber-900 leading-relaxed bg-amber-50 rounded-xl p-3.5 ring-1 ring-amber-200 flex items-start gap-2">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <div>
                    <strong class="text-amber-800 font-bold">{{ $lang === 'fr' ? 'Conseil de sécurité' : 'Safety tip' }}</strong>
                    <div class="mt-0.5">{{ $lang === 'fr' ? 'Rencontrez-vous dans un lieu public, inspectez l\'article et ne payez jamais à l\'avance.' : 'Meet in a public place, inspect the item, and never pay in advance.' }}</div>
                </div>
            </div>

            {{-- ─── Reviews ─── --}}
            @php($_reviews = $this->reviews)
            @if ($isSold || $_reviews->isNotEmpty() || $this->canLeaveReview)
                <div class="border-t border-slate-200 pt-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2 mb-3">
                        <span class="text-cm-yellow text-xl">★</span>
                        {{ $lang === 'fr' ? 'Avis sur cette transaction' : 'Reviews of this sale' }}
                        @if ($_reviews->isNotEmpty())
                            <span class="text-sm font-medium text-slate-500">({{ $_reviews->count() }})</span>
                        @endif
                    </h2>

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
                        <div class="text-sm text-slate-500 italic py-1">
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
                </div>
            @endif

            {{-- ─── Sponsored Ads ─── --}}
            <div class="border-t border-slate-200 pt-4" x-data="{
                    ads: [],
                    activeIdx: 0,
                    init() {
                        fetch('{{ route("ads.yard") }}', { headers: { 'Accept': 'application/json' } })
                            .then(r => r.ok ? r.json() : [])
                            .then(data => {
                                this.ads = data;
                                this.$nextTick(() => {
                                    const scrl = this.$refs.adscrl;
                                    if(!scrl) return;
                                    const updateCenter = () => {
                                        const scrlRect = scrl.getBoundingClientRect();
                                        const scrlCenter = scrlRect.left + scrlRect.width / 2;
                                        let closestIdx = 0; let minDiff = Infinity;
                                        const items = [...scrl.children].filter(c => c.tagName !== 'TEMPLATE');
                                        items.forEach((child, i) => {
                                            const rect = child.getBoundingClientRect();
                                            const childCenter = rect.left + rect.width / 2;
                                            const diff = Math.abs(scrlCenter - childCenter);
                                            if (diff < minDiff) { minDiff = diff; closestIdx = i; }
                                        });
                                        if (this.activeIdx !== closestIdx) this.activeIdx = closestIdx;
                                    };
                                    scrl.addEventListener('scroll', updateCenter, { passive: true });
                                    updateCenter();

                                    if(data.length > 1) {
                                        setInterval(() => {
                                            if(!scrl || scrl.matches(':hover') || scrl.matches(':active')) return;
                                            const items = [...scrl.children].filter(c => c.tagName !== 'TEMPLATE');
                                            const nextIdx = (this.activeIdx + 1) % items.length;
                                            if (items[nextIdx]) {
                                                const child = items[nextIdx];
                                                scrl.scrollTo({ left: child.offsetLeft - scrl.clientWidth / 2 + child.offsetWidth / 2, behavior: 'smooth' });
                                            }
                                        }, 6000);
                                    }
                                });
                            })
                            .catch(e => console.warn('Failed to load ads', e));
                    }
                }" x-show="ads.length > 0" x-cloak>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $lang === 'fr' ? 'Sponsorisé' : 'Sponsored' }}
                    </h2>
                </div>
                <div x-ref="adscrl" class="relative flex overflow-x-auto snap-x snap-mandatory gap-3 pb-6 px-[calc(50%-70px)] sm:px-[calc(50%-80px)] -mx-4 sm:mx-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden items-center">
                    <template x-for="(ad, index) in ads" :key="ad.id">
                        <div :class="activeIdx === index ? 'scale-100 opacity-100 shadow-lg z-10 ring-2 ring-cm-green' : 'scale-90 opacity-50 hover:opacity-80 z-0 ring-1 ring-slate-200'" 
                             class="w-[140px] sm:w-[160px] shrink-0 snap-center bg-white rounded-2xl overflow-hidden flex flex-col group relative transition-all duration-500 ease-out origin-center cursor-pointer"
                             @click="if(activeIdx !== index) { $event.preventDefault(); $event.stopPropagation(); $refs.adscrl.scrollTo({ left: $el.offsetLeft - $refs.adscrl.clientWidth / 2 + $el.offsetWidth / 2, behavior: 'smooth' }); }">
                            {{-- Image or Video --}}
                            <div class="relative w-full aspect-square bg-slate-100 flex-shrink-0">
                                <template x-if="ad.video">
                                    <iframe :src="ad.video + '?autoplay=0&mute=1&loop=1&playlist=' + ad.video.split('/').pop()" frameborder="0"
                                            class="absolute inset-0 w-full h-full pointer-events-none"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen loading="lazy"></iframe>
                                </template>
                                <template x-if="!ad.video && ad.image">
                                    <img :src="ad.image" :alt="ad.title" loading="lazy" class="w-full h-full object-cover pointer-events-none">
                                </template>
                                <template x-if="!ad.video && !ad.image">
                                    <div class="w-full h-full grid place-items-center text-2xl">📢</div>
                                </template>
                                <div class="absolute top-1 right-1 bg-black/60 text-white text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wide">Ad</div>
                            </div>
                            
                            {{-- Body --}}
                            <a :href="'{{ url('/') }}/ad/' + ad.id + '/click'" target="_blank" rel="noopener noreferrer" class="p-2.5 flex flex-col flex-1 bg-white">
                                <div class="text-[12px] font-bold text-slate-900 line-clamp-2 leading-snug" x-text="ad.title"></div>
                                <div class="text-[11px] text-slate-500 line-clamp-1 mt-0.5 mb-1.5 flex-1" x-text="ad.description" x-show="ad.description"></div>
                                <div class="flex items-center justify-between mt-auto pt-1.5 border-t border-slate-100">
                                    <span class="text-[10px] text-slate-400 font-medium truncate pr-1" x-text="ad.advertiser" x-show="ad.advertiser"></span>
                                    <span class="text-[10px] font-bold text-cm-green whitespace-nowrap" x-text="ad.cta || '{{ $lang === 'fr' ? 'Plus' : 'More' }}'"></span>
                                </div>
                            </a>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ─── Similar listings ─── --}}
            @if ($this->similarListings->isNotEmpty())
                <div class="border-t border-slate-200 pt-4" x-data="{
                        activeIdx: 0,
                        init() {
                            this.$nextTick(() => {
                                const scrl = this.$refs.simscrl;
                                if(!scrl) return;
                                
                                const updateCenter = () => {
                                    const scrlRect = scrl.getBoundingClientRect();
                                    const scrlCenter = scrlRect.left + scrlRect.width / 2;
                                    let closestIdx = 0; let minDiff = Infinity;
                                    [...scrl.children].forEach((child, i) => {
                                        const rect = child.getBoundingClientRect();
                                        const childCenter = rect.left + rect.width / 2;
                                        const diff = Math.abs(scrlCenter - childCenter);
                                        if (diff < minDiff) { minDiff = diff; closestIdx = i; }
                                    });
                                    if (this.activeIdx !== closestIdx) this.activeIdx = closestIdx;
                                };
                                scrl.addEventListener('scroll', updateCenter, { passive: true });
                                updateCenter();

                                if (scrl.children.length > 1) {
                                    setInterval(() => {
                                        if(!scrl || scrl.matches(':hover') || scrl.matches(':active')) return;
                                        const nextIdx = (this.activeIdx + 1) % scrl.children.length;
                                        const child = scrl.children[nextIdx];
                                        if (child) scrl.scrollTo({ left: child.offsetLeft - scrl.clientWidth / 2 + child.offsetWidth / 2, behavior: 'smooth' });
                                    }, 6000);
                                }
                            });
                        }
                    }">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-slate-900">
                            {{ $lang === 'fr' ? 'Annonces similaires' : 'More like this' }}
                        </h2>
                        @if ($listing->category)
                            <a href="{{ route('marketplace.category', ['slug' => $listing->category->slug]) }}" wire:navigate
                               class="text-sm text-cm-green font-semibold hover:underline">
                                {{ $lang === 'fr' ? 'Voir plus' : 'See more' }} →
                            </a>
                        @endif
                    </div>
                    <div x-ref="simscrl" class="relative flex overflow-x-auto snap-x snap-mandatory gap-3 pb-6 px-[calc(50%-70px)] sm:px-[calc(50%-80px)] -mx-4 sm:mx-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden items-center">
                        @foreach ($this->similarListings as $index => $similar)
                            <div :class="activeIdx === {{ $index }} ? 'scale-100 opacity-100 shadow-lg z-10 ring-2 ring-cm-green rounded-2xl' : 'scale-90 opacity-50 hover:opacity-80 z-0 ring-1 ring-slate-200 rounded-2xl'" 
                                 class="w-[140px] sm:w-[160px] shrink-0 snap-center transition-all duration-500 ease-out origin-center cursor-pointer relative"
                                 @click="if(activeIdx !== {{ $index }}) { $event.preventDefault(); $event.stopPropagation(); $refs.simscrl.scrollTo({ left: $el.offsetLeft - $refs.simscrl.clientWidth / 2 + $el.offsetWidth / 2, behavior: 'smooth' }); }">
                                <x-marketplace.listing-card :listing="$similar" :as-modal="$asModal" />
                                {{-- An invisible overlay to block clicks on non-centered items so they just scroll to center --}}
                                <div x-show="activeIdx !== {{ $index }}" class="absolute inset-0 z-20"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </aside>

    {{-- ═══ Photo lightbox (FB-style fullscreen viewer) — teleported to body so it
         escapes the .yard-container stacking context and covers everything ═══ --}}
    <template x-teleport="body">
    <div x-show="lb" x-cloak @click.self="lb = false" x-transition.opacity
         class="fixed inset-0 z-[90] bg-black flex items-center justify-center">
        <button type="button" @click="lb = false" aria-label="{{ $lang === 'fr' ? 'Fermer' : 'Close' }}"
                class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img :src="limgs[li]" class="max-w-[92vw] max-h-[88vh] w-auto h-auto object-contain select-none" alt="">
        <template x-if="limgs.length > 1">
            <div>
                <button type="button" @click.stop="li = (li - 1 + limgs.length) % limgs.length"
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" @click.stop="li = (li + 1) % limgs.length"
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <span class="absolute bottom-5 left-1/2 -translate-x-1/2 text-sm font-semibold text-white bg-white/10 px-3 py-1 rounded-full"
                      x-text="(li + 1) + ' / ' + limgs.length"></span>
            </div>
        </template>
    </div>
    </template>
    </div>{{-- /panel --}}

    {{-- ═══ Report modal ═══ --}}
    @if ($showReportModal)
        <div class="fixed inset-0 z-[80] grid place-items-center bg-slate-900/60 backdrop-blur-sm p-4"
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
    </div>{{-- /single-root wrapper --}}
    @if ($asModal) @endteleport @endif
</div>
