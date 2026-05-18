@php
    $lang = app()->getLocale();
    $stats = $this->stats;
    $offers = $this->offers;
@endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-4xl mx-auto px-3 sm:px-4 lg:px-6 py-4 lg:py-6">

        {{-- Header card --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 sm:p-5 mb-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-full bg-cm-yellow/20 grid place-items-center shrink-0">
                    <svg class="w-5 h-5 text-cm-yellow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight truncate">
                        {{ $lang === 'fr' ? 'Mes offres' : 'My Offers' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $lang === 'fr' ? 'Suivez vos offres envoyées et reçues.' : 'Track offers you’ve sent and received.' }}
                    </p>
                </div>
            </div>
            <a href="{{ route('marketplace.index') }}" wire:navigate
               class="hidden sm:inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs px-3.5 py-2 rounded-full transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ $lang === 'fr' ? 'Marketplace' : 'Marketplace' }}
            </a>
        </div>

        {{-- Tabs + List card --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <div class="flex border-b border-slate-200">
                <button wire:click="setTab('sent')"
                        @class([
                            'flex-1 py-3.5 text-sm font-bold transition relative inline-flex items-center justify-center gap-2',
                            'text-cm-green bg-cm-green/5' => $tab === 'sent',
                            'text-slate-500 hover:text-slate-800 hover:bg-slate-50' => $tab !== 'sent',
                        ])>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                    {{ $lang === 'fr' ? 'Envoyées' : 'Sent' }}
                    <span @class([
                        'text-[11px] font-bold px-1.5 py-0.5 rounded-full',
                        'bg-cm-green text-white' => $tab === 'sent',
                        'bg-slate-100 text-slate-600' => $tab !== 'sent',
                    ])>{{ $stats['sent'] }}</span>
                    @if ($tab === 'sent')<span class="absolute bottom-0 left-0 right-0 h-0.5 bg-cm-green"></span>@endif
                </button>
                <button wire:click="setTab('received')"
                        @class([
                            'flex-1 py-3.5 text-sm font-bold transition relative inline-flex items-center justify-center gap-2',
                            'text-cm-green bg-cm-green/5' => $tab === 'received',
                            'text-slate-500 hover:text-slate-800 hover:bg-slate-50' => $tab !== 'received',
                        ])>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                    {{ $lang === 'fr' ? 'Reçues' : 'Received' }}
                    <span @class([
                        'text-[11px] font-bold px-1.5 py-0.5 rounded-full',
                        'bg-cm-green text-white' => $tab === 'received',
                        'bg-slate-100 text-slate-600' => $tab !== 'received',
                    ])>{{ $stats['received'] }}</span>
                    @if ($stats['pendingReceived'] > 0)
                        <span class="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded-full font-bold">{{ $stats['pendingReceived'] }} {{ $lang === 'fr' ? 'nouv.' : 'new' }}</span>
                    @endif
                    @if ($tab === 'received')<span class="absolute bottom-0 left-0 right-0 h-0.5 bg-cm-green"></span>@endif
                </button>
            </div>

            {{-- List --}}
            <div class="divide-y divide-slate-100">
                @forelse ($offers as $offer)
                    @php
                        $listing = $offer->listing;
                        $cover = $listing?->media->first();
                        $otherParty = $tab === 'sent' ? $offer->seller : $offer->buyer;
                        $statusColor = $offer->status->color();
                        $badge = match($statusColor) {
                            'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
                            'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                            'rose' => 'bg-rose-50 text-rose-800 ring-rose-200',
                            'blue' => 'bg-blue-50 text-blue-800 ring-blue-200',
                            default => 'bg-slate-100 text-slate-700 ring-slate-200',
                        };
                    @endphp
                    <div class="p-4 hover:bg-slate-50 transition">
                        <div class="flex items-start gap-3">
                            {{-- Listing thumb --}}
                            <a href="{{ $listing ? route('marketplace.show', ['slug' => $listing->slug]) : '#' }}" wire:navigate
                               class="shrink-0 w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-slate-100 overflow-hidden grid place-items-center ring-1 ring-slate-200">
                                @if ($cover)
                                    <img src="{{ $cover->thumbnailUrl() }}" class="w-full h-full object-cover" alt="">
                                @else
                                    <span class="text-2xl">{{ $listing?->category?->icon ?? '📦' }}</span>
                                @endif
                            </a>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ $listing ? route('marketplace.show', ['slug' => $listing->slug]) : '#' }}" wire:navigate
                                       class="font-bold text-sm text-slate-900 hover:text-cm-green line-clamp-1">
                                        {{ $listing?->title ?? ($lang === 'fr' ? '(Annonce supprimée)' : '(Listing removed)') }}
                                    </a>
                                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full ring-1 shrink-0 {{ $badge }}">
                                        {{ $lang === 'fr' ? $offer->status->labelFr() : $offer->status->label() }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-baseline gap-2 flex-wrap">
                                    <span class="text-xl font-extrabold text-cm-green">{{ $offer->formattedAmount() }}</span>
                                    @if ($listing)
                                        <span class="text-[11px] text-slate-400 line-through">{{ $listing->formattedPrice($lang) }}</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $tab === 'sent' ? ($lang === 'fr' ? 'À' : 'To') : ($lang === 'fr' ? 'De' : 'From') }}
                                    <span class="font-semibold text-slate-700">{{ $otherParty?->name ?? '—' }}</span>
                                    · {{ $offer->created_at->diffForHumans() }}
                                </div>
                                @if ($offer->message)
                                    <div class="mt-2 text-xs text-slate-700 italic bg-slate-50 ring-1 ring-slate-100 rounded-lg px-3 py-2 line-clamp-2">"{{ $offer->message }}"</div>
                                @endif

                                {{-- Actions --}}
                                @if ($tab === 'sent' && $offer->status->isOpen())
                                    <div class="mt-2.5 flex gap-2">
                                        <button wire:click="withdrawOffer({{ $offer->id }})"
                                                wire:confirm="{{ $lang === 'fr' ? 'Retirer cette offre ?' : 'Withdraw this offer?' }}"
                                                class="text-xs font-bold text-slate-600 hover:text-cm-red underline underline-offset-2">
                                            {{ $lang === 'fr' ? 'Retirer l\'offre' : 'Withdraw offer' }}
                                        </button>
                                    </div>
                                @elseif ($tab === 'received' && $offer->status->isOpen())
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
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-10 sm:p-14 text-center">
                        <div class="w-20 h-20 mx-auto rounded-full bg-cm-yellow/20 grid place-items-center mb-4 text-4xl">🏷️</div>
                        <h3 class="font-bold text-slate-900 text-lg">
                            {{ $tab === 'sent'
                                ? ($lang === 'fr' ? 'Aucune offre envoyée' : 'No offers sent yet')
                                : ($lang === 'fr' ? 'Aucune offre reçue' : 'No offers received yet') }}
                        </h3>
                        <p class="text-sm text-slate-500 mt-1.5 max-w-sm mx-auto">
                            {{ $tab === 'sent'
                                ? ($lang === 'fr' ? 'Parcourez le marketplace et faites une offre.' : 'Browse the marketplace and make an offer.')
                                : ($lang === 'fr' ? 'Quand un acheteur fait une offre, elle apparaît ici.' : 'When a buyer makes an offer, it appears here.') }}
                        </p>
                        <a href="{{ route('marketplace.index') }}" wire:navigate
                           class="mt-5 inline-flex items-center gap-2 bg-cm-green text-white text-sm font-bold px-5 py-2.5 rounded-full hover:bg-cm-green/90 shadow-md transition">
                            {{ $lang === 'fr' ? 'Aller au marketplace' : 'Go to marketplace' }} →
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($offers->hasPages())
            <div class="mt-4">{{ $offers->links() }}</div>
        @endif
    </div>
</div>
