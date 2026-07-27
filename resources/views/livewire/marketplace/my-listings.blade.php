@php $lang = app()->getLocale(); @endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-6xl mx-auto px-3 sm:px-4 lg:px-6 py-4 lg:py-6">

        {{-- Header card --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 sm:p-5 mb-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-full bg-cm-green/10 grid place-items-center shrink-0">
                    <svg class="w-5 h-5 text-cm-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight truncate">
                        {{ $lang === 'fr' ? 'Mes annonces' : 'My listings' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $lang === 'fr' ? 'Gérez vos publications, mises en pause et ventes.' : 'Manage your posts, pauses, and sales.' }}
                    </p>
                </div>
            </div>
            <a href="{{ route('marketplace.sell') }}" wire:navigate
               class="inline-flex items-center gap-1.5 bg-cm-green text-white font-bold px-4 py-2 rounded-full text-sm hover:bg-cm-green/90 shadow-md transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span class="hidden sm:inline">{{ $lang === 'fr' ? 'Nouvelle annonce' : 'New listing' }}</span>
                <span class="sm:hidden">{{ $lang === 'fr' ? 'Nouveau' : 'New' }}</span>
            </a>
        </div>



        {{-- Filter pills --}}
        <div class="flex gap-2 overflow-x-auto mb-4 pb-1 -mx-1 px-1 scrollbar-hide">
            @foreach (['all'=>'All|Tous','active'=>'Active|Actives','paused'=>'Paused|En pause','sold'=>'Sold|Vendues','expired'=>'Expired|Expirées','draft'=>'Drafts|Brouillons'] as $key => $pair)
                @php [$en, $fr] = explode('|', $pair); @endphp
                <button wire:click="setFilter('{{ $key }}')"
                        @class([
                            'shrink-0 text-xs font-bold px-4 py-2 rounded-full transition',
                            'bg-cm-green text-white shadow-md' => $filter === $key,
                            'bg-white ring-1 ring-slate-200 text-slate-700 hover:ring-cm-green/40 hover:text-cm-green' => $filter !== $key,
                        ])>
                    {{ $lang === 'fr' ? $fr : $en }}
                </button>
            @endforeach
        </div>

        @if ($this->listings->isEmpty())
            <div class="text-center py-16 sm:py-24 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200">
                <div class="w-20 h-20 mx-auto rounded-full bg-slate-100 grid place-items-center mb-4 text-4xl">📭</div>
                <div class="text-lg font-bold text-slate-900">
                    {{ $lang === 'fr' ? 'Aucune annonce ici' : 'No listings here' }}
                </div>
                <p class="mt-1.5 text-sm text-slate-500 max-w-sm mx-auto">
                    {{ $lang === 'fr' ? 'Mettez en vente un article en quelques étapes.' : 'List an item to sell in just a few steps.' }}
                </p>
                <a href="{{ route('marketplace.sell') }}" wire:navigate
                   class="mt-5 inline-flex items-center gap-2 bg-cm-green text-white px-5 py-2.5 rounded-full text-sm font-bold hover:bg-cm-green/90 shadow-md transition">
                    {{ $lang === 'fr' ? 'En créer une' : 'Create one' }} →
                </a>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($this->listings as $listing)
                    @php
                        $statusColor = match($listing->status?->value) {
                            'active' => 'bg-emerald-100 text-emerald-800',
                            'sold' => 'bg-blue-100 text-blue-800',
                            'paused' => 'bg-slate-200 text-slate-700',
                            'expired' => 'bg-orange-100 text-orange-800',
                            'draft' => 'bg-amber-100 text-amber-800',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden hover:shadow-md transition flex flex-col relative">
                        <div class="absolute top-2 left-2 z-10 bg-white/90 backdrop-blur rounded p-1 shadow-sm flex items-center justify-center">
                            <input type="checkbox" wire:model.live="selected" value="{{ $listing->id }}" class="w-4 h-4 rounded text-cm-green focus:ring-cm-green border-gray-300 cursor-pointer">
                        </div>
                        <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate
                           class="block aspect-[4/3] bg-slate-100 relative group">
                            @if ($listing->coverUrl())
                                <img src="{{ $listing->coverUrl() }}" class="absolute inset-0 w-full h-full object-cover" alt="">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-5xl text-slate-300">📦</div>
                            @endif
                            <span class="absolute bottom-2.5 left-2.5 text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full shadow-sm {{ $statusColor }}">
                                {{ $lang === 'fr' ? $listing->status?->labelFr() : $listing->status?->label() }}
                            </span>
                            @if ($listing->is_featured)
                                <span class="absolute top-2.5 right-2.5 text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-cm-yellow text-slate-900 shadow-sm">⭐ {{ $lang === 'fr' ? 'En vedette' : 'Featured' }}</span>
                            @endif
                        </a>
                        <div class="p-4 flex-1 flex flex-col">
                            <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate
                               class="font-bold text-slate-900 hover:text-cm-green line-clamp-1 leading-tight">{{ $listing->title }}</a>
                            <div class="text-cm-green font-extrabold text-lg mt-1">{{ $listing->formattedPrice($lang) }}</div>
                            <div class="mt-2.5 flex items-center gap-3 text-[12px] text-slate-500">
                                <span class="flex items-center gap-1" title="{{ $lang === 'fr' ? 'Vues' : 'Views' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    {{ $listing->views_count }}
                                </span>
                                <span class="flex items-center gap-1" title="{{ $lang === 'fr' ? 'Favoris' : 'Saves' }}">
                                    <svg class="w-3.5 h-3.5 text-cm-red" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                    {{ $listing->favorites_count }}
                                </span>
                                <a href="{{ route('marketplace.inbox', ['tab' => 'selling', 'l' => $listing->id]) }}" wire:navigate class="flex items-center gap-1 hover:text-cm-green transition" title="{{ $lang === 'fr' ? 'Messages' : 'Inquiries' }}">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                    {{ $listing->messages_count }}
                                </a>
                            </div>
                            <div class="mt-auto pt-3 flex flex-wrap gap-1.5">
                                <a href="{{ route('marketplace.edit', ['listing' => $listing->id]) }}" wire:navigate
                                   class="text-xs font-bold px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                    ✏️ {{ $lang === 'fr' ? 'Modifier' : 'Edit' }}
                                </a>
                                @if ($listing->status?->value === 'active')
                                    @php $canBump = ! $listing->bumped_at || $listing->bumped_at->lt(now()->subHours(24)); @endphp
                                    <button wire:click="bump({{ $listing->id }})"
                                            @disabled(! $canBump)
                                            title="{{ $canBump ? ($lang === 'fr' ? 'Remonter en tête de liste' : 'Push to top of feed') : ($lang === 'fr' ? 'Disponible dans ' . $listing->bumped_at->copy()->addHours(24)->diffForHumans() : 'Available ' . $listing->bumped_at->copy()->addHours(24)->diffForHumans()) }}"
                                            class="text-xs font-bold px-3 py-1.5 rounded-full {{ $canBump ? 'bg-cm-yellow text-slate-900 hover:bg-cm-yellow/90 ring-1 ring-cm-yellow' : 'bg-slate-100 text-slate-400 ring-1 ring-slate-200 cursor-not-allowed' }} transition">
                                        🚀 {{ $lang === 'fr' ? 'Booster' : 'Bump' }}
                                    </button>
                                    <button wire:click="pause({{ $listing->id }})" class="text-xs font-bold px-3 py-1.5 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-700 ring-1 ring-amber-200 transition">
                                        ⏸ {{ $lang === 'fr' ? 'Pause' : 'Pause' }}
                                    </button>
                                    <button wire:click="openMarkSold({{ $listing->id }})" class="text-xs font-bold px-3 py-1.5 rounded-full bg-blue-50 hover:bg-blue-100 text-blue-700 ring-1 ring-blue-200 transition">
                                        💰 {{ $lang === 'fr' ? 'Vendu' : 'Sold' }}
                                    </button>
                                @elseif ($listing->status?->value === 'paused')
                                    <button wire:click="reactivate({{ $listing->id }})" class="text-xs font-bold px-3 py-1.5 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 transition">
                                        ▶ {{ $lang === 'fr' ? 'Réactiver' : 'Reactivate' }}
                                    </button>
                                @endif
                                <a href="{{ route('marketplace.insights', ['listing' => $listing->id]) }}" wire:navigate
                                   class="text-xs font-bold px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                    📊 {{ $lang === 'fr' ? 'Stats' : 'Stats' }}
                                </a>
                                <button wire:click="remove({{ $listing->id }})"
                                        wire:confirm="{{ $lang === 'fr' ? 'Supprimer cette annonce ?' : 'Remove this listing?' }}"
                                        title="{{ $lang === 'fr' ? 'Supprimer' : 'Delete' }}"
                                        class="ml-auto w-8 h-8 grid place-items-center rounded-full bg-cm-red/10 hover:bg-cm-red text-cm-red hover:text-white transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $this->listings->onEachSide(1)->links() }}</div>
        @endif
    </div>

    {{-- ─── Mark as Sold modal ─── --}}
    @if ($sellListingId)
        @php($_sellListing = collect($this->listings->items())->firstWhere('id', $sellListingId))
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/60 backdrop-blur-sm p-4"
             wire:click.self="cancelMarkSold">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                    <h3 class="text-lg font-extrabold text-slate-900">
                        💰 <span x-data x-text="$store.lang.t('Mark as sold','Marquer comme vendu')"></span>
                    </h3>
                    <button wire:click="cancelMarkSold" class="w-9 h-9 grid place-items-center rounded-full hover:bg-slate-100 text-slate-500 text-xl">✕</button>
                </div>

                <div class="p-5 space-y-4">
                    @if ($_sellListing)
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                            @if ($_sellListing->coverUrl())
                                <img src="{{ $_sellListing->coverUrl() }}" alt="" class="w-14 h-14 rounded-lg object-cover">
                            @else
                                <div class="w-14 h-14 rounded-lg bg-slate-200 grid place-items-center text-2xl">📦</div>
                            @endif
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate">{{ $_sellListing->title }}</div>
                                <div class="text-xs text-slate-600">{{ $_sellListing->formattedPrice() }}</div>
                            </div>
                        </div>
                    @endif

                    {{-- Buyer picker --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-slate-700 mb-1">
                            <span x-data x-text="$store.lang.t('Buyer','Acheteur')"></span>
                        </label>

                        @if ($sellBuyerId)
                            @php($_picked = \App\Models\User::find($sellBuyerId))
                            @if ($_picked)
                                <div class="flex items-center gap-2 p-2 rounded-xl bg-cm-green/10 ring-1 ring-cm-green/30">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-200 grid place-items-center text-xs font-bold text-slate-700">
                                        @if ($_picked->avatar)
                                            <img src="{{ asset('storage/' . $_picked->avatar) }}" alt="" class="w-full h-full object-cover">
                                        @else
                                            {{ mb_strtoupper(mb_substr($_picked->name ?: $_picked->username, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-slate-900 truncate">{{ $_picked->name ?: $_picked->username }}</div>
                                        <div class="text-[11px] text-slate-500 truncate">&#64;{{ $_picked->username }}</div>
                                    </div>
                                    <button wire:click="clearBuyer" class="text-xs text-cm-red hover:underline font-medium">
                                        <span x-data x-text="$store.lang.t('Change','Modifier')"></span>
                                    </button>
                                </div>
                            @endif
                        @else
                            <input type="text" wire:model.live.debounce.300ms="sellBuyerSearch"
                                   placeholder="{{ app()->getLocale() === 'fr' ? 'Rechercher par nom ou @pseudo…' : 'Search by name or @username…' }}"
                                   class="w-full rounded-lg ring-1 ring-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none">

                            <div class="mt-2 space-y-1 max-h-48 overflow-y-auto">
                                @forelse ($this->buyerCandidates as $cand)
                                    <button type="button" wire:click="pickBuyer({{ $cand->id }})"
                                            class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-left">
                                        <div class="w-7 h-7 rounded-full overflow-hidden bg-slate-200 grid place-items-center text-[11px] font-bold text-slate-700">
                                            @if ($cand->avatar)
                                                <img src="{{ asset('storage/' . $cand->avatar) }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                {{ mb_strtoupper(mb_substr($cand->name ?: $cand->username, 0, 1)) }}
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-slate-900 truncate">{{ $cand->name ?: $cand->username }}</div>
                                            <div class="text-[11px] text-slate-500 truncate">&#64;{{ $cand->username }}</div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="text-[12px] text-slate-500 italic px-2 py-2">
                                        @if (trim($sellBuyerSearch) === '')
                                            <span x-data x-text="$store.lang.t('No prior offers — search above to attribute a buyer (optional).','Aucune offre précédente — recherchez ci-dessus pour attribuer un acheteur (optionnel).')"></span>
                                        @else
                                            <span x-data x-text="$store.lang.t('No match found.','Aucun résultat.')"></span>
                                        @endif
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    {{-- Final price --}}
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <label class="block text-[12px] font-semibold text-slate-700 mb-1">
                                <span x-data x-text="$store.lang.t('Final price','Prix final')"></span>
                            </label>
                            <input type="number" min="0" step="1" wire:model="sellPrice"
                                   class="w-full rounded-lg ring-1 ring-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-slate-700 mb-1">
                                <span x-data x-text="$store.lang.t('Currency','Devise')"></span>
                            </label>
                            <select wire:model="sellCurrency"
                                    class="w-full rounded-lg ring-1 ring-slate-300 px-2 py-2 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none">
                                <option value="XAF">XAF</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-500">
                        <span x-data x-text="$store.lang.t('Attributing a buyer lets them rate this transaction.','Attribuer un acheteur lui permet de noter cette transaction.')"></span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-200">
                    <button wire:click="cancelMarkSold"
                            class="text-sm font-semibold text-slate-700 bg-white hover:bg-slate-100 ring-1 ring-slate-300 rounded-full px-4 py-2">
                        <span x-data x-text="$store.lang.t('Cancel','Annuler')"></span>
                    </button>
                    <button wire:click="saveSold"
                            class="text-sm font-semibold text-white bg-cm-green hover:bg-cm-green/90 rounded-full px-5 py-2 shadow">
                        <span x-data x-text="$store.lang.t('Confirm sale','Confirmer la vente')"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif
    {{-- ─── Bulk Actions Bar ─── --}}
    @if (count($selected) > 0)
        <div class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] p-4">
            <div class="max-w-6xl mx-auto w-full flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button wire:click="toggleSelectAll" class="text-sm font-bold text-slate-700 hover:text-cm-green">
                        {{ count($selected) === count($this->listings->pluck('id')) ? ($lang === 'fr' ? 'Désélectionner tout' : 'Deselect All') : ($lang === 'fr' ? 'Sélectionner tout' : 'Select All') }}
                    </button>
                    <span class="text-sm font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">{{ count($selected) }} {{ $lang === 'fr' ? 'sélectionné(s)' : 'selected' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="bulkPause" class="text-sm font-bold px-4 py-2 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-700 ring-1 ring-amber-200 transition">
                        ⏸ {{ $lang === 'fr' ? 'Pause' : 'Pause' }}
                    </button>
                    <button wire:click="bulkReactivate" class="text-sm font-bold px-4 py-2 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 transition">
                        ▶ {{ $lang === 'fr' ? 'Réactiver' : 'Reactivate' }}
                    </button>
                    <button wire:click="bulkRemove" wire:confirm="{{ $lang === 'fr' ? 'Supprimer les annonces sélectionnées ?' : 'Remove selected listings?' }}" class="text-sm font-bold px-4 py-2 rounded-full bg-cm-red/10 hover:bg-cm-red text-cm-red hover:text-white transition">
                        🗑 {{ $lang === 'fr' ? 'Supprimer' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
