@php $lang = app()->getLocale(); @endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-[1400px] mx-auto px-3 sm:px-4 lg:px-6 py-4 lg:py-6">

        {{-- Header card --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 sm:p-5 mb-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-full bg-cm-red/10 grid place-items-center shrink-0">
                    <svg class="w-5 h-5 text-cm-red" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight truncate">
                        {{ $lang === 'fr' ? 'Annonces sauvegardées' : 'Saved listings' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $lang === 'fr' ? 'Retrouvez ici toutes les annonces que vous avez aimées.' : 'Everything you’ve hearted, ready when you are.' }}
                    </p>
                </div>
            </div>
            <a href="{{ route('marketplace.index') }}" wire:navigate
               class="hidden sm:inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs px-3.5 py-2 rounded-full transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ $lang === 'fr' ? 'GoMarket' : 'GoMarket' }}
            </a>
        </div>

        @if ($this->favorites->isEmpty())
            <div class="text-center py-16 sm:py-24 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200">
                <div class="w-20 h-20 mx-auto rounded-full bg-cm-red/10 grid place-items-center mb-4">
                    <svg class="w-10 h-10 text-cm-red/70" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
                </div>
                <div class="text-lg font-bold text-slate-900">
                    {{ $lang === 'fr' ? 'Aucun favori pour le moment' : 'No favorites yet' }}
                </div>
                <p class="mt-1.5 text-sm text-slate-500 max-w-sm mx-auto">
                    {{ $lang === 'fr' ? 'Touchez le cœur sur une annonce pour la garder à portée de main.' : 'Tap the heart on any listing to save it here.' }}
                </p>
                <a href="{{ route('marketplace.index') }}" wire:navigate
                   class="mt-5 inline-flex items-center gap-2 bg-cm-green text-white px-5 py-2.5 rounded-full text-sm font-bold hover:bg-cm-green/90 shadow-md transition">
                    {{ $lang === 'fr' ? 'Parcourir GoMarket' : 'Browse GoMarket' }} →
                </a>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-3 gap-y-4">
                @foreach ($this->favorites as $fav)
                    @if ($fav->listing)
                        <div class="relative group">
                            <x-marketplace.listing-card :listing="$fav->listing" />
                            <button wire:click="remove({{ $fav->listing_id }})"
                                    wire:confirm="{{ $lang === 'fr' ? 'Retirer des favoris ?' : 'Remove from favorites?' }}"
                                    title="{{ $lang === 'fr' ? 'Retirer' : 'Remove' }}"
                                    class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white/95 backdrop-blur text-cm-red text-sm shadow-lg ring-1 ring-slate-200 hover:bg-cm-red hover:text-white hover:scale-110 transition grid place-items-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="mt-6">{{ $this->favorites->onEachSide(1)->links() }}</div>
        @endif
    </div>
</div>
