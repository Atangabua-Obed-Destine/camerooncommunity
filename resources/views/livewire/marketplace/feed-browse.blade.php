{{-- Marketplace feed — FB-inspired, light theme always --}}
<div class="min-h-[calc(100vh-96px)] bg-slate-100" x-data="{ filtersOpen: false }">

    {{-- ─── Mobile sticky bar with title + filters trigger ─── --}}
    <div class="lg:hidden sticky top-[64px] z-30 bg-white border-b border-slate-200 px-3 py-2.5 flex items-center justify-between gap-2 shadow-sm">
        <h1 class="text-lg font-extrabold text-slate-900 truncate">
            @if ($this->activeCategory)
                <span class="mr-1">{{ $this->activeCategory->icon }}</span>{{ $this->activeCategory->localizedName() }}
            @else
                <span x-data x-text="$store.lang.t('Marketplace','Marketplace')"></span>
            @endif
        </h1>
        <div class="flex items-center gap-1.5">
            <button type="button" @click="filtersOpen = true"
                    class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs rounded-full px-3 py-1.5 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M3 4h18M6 12h12M10 20h4"/></svg>
                <span x-data x-text="$store.lang.t('Filters','Filtres')"></span>
            </button>
            <a href="{{ route('marketplace.sell') }}" wire:navigate
               class="inline-flex items-center gap-1 bg-cm-green hover:bg-cm-green/90 text-white font-semibold rounded-full px-3 py-1.5 text-xs shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                <span x-data x-text="$store.lang.t('Sell','Vendre')"></span>
            </a>
        </div>
    </div>

    {{-- ─── Mobile drawer ─── --}}
    <div x-cloak x-show="filtersOpen" class="lg:hidden">
        <div x-show="filtersOpen" x-transition.opacity
             @click="filtersOpen = false"
             class="fixed inset-0 z-40 bg-black/50"></div>
        <aside x-show="filtersOpen"
               x-transition:enter="transition transform duration-300"
               x-transition:enter-start="-translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition transform duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="-translate-x-full"
               class="fixed top-0 left-0 z-50 h-full w-[88%] max-w-sm bg-white p-4 overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between mb-3">
                <span class="font-bold text-slate-900" x-data x-text="$store.lang.t('Filters & categories','Filtres & catégories')"></span>
                <button @click="filtersOpen = false" class="text-2xl text-slate-500 hover:text-slate-800 w-9 h-9 grid place-items-center rounded-full hover:bg-slate-100">✕</button>
            </div>
            @include('livewire.marketplace.partials.sidebar')
        </aside>
    </div>

    {{-- ─── Page grid ─── --}}
    <div class="max-w-[1400px] mx-auto px-3 sm:px-4 lg:px-5 py-4 lg:py-5">
        <div class="grid lg:grid-cols-[300px_1fr] gap-5">

            {{-- ─── Desktop sidebar (FB-style panel) ─── --}}
            <aside class="hidden lg:block lg:sticky lg:top-[108px] lg:self-start lg:max-h-[calc(100vh-128px)] lg:overflow-y-auto">
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-3">
                    @include('livewire.marketplace.partials.sidebar')
                </div>
            </aside>

            {{-- ─── Main column ─── --}}
            <main class="min-w-0">

                {{-- Hero header (desktop only) --}}
                <div class="hidden lg:flex items-center justify-between mb-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight truncate">
                            @if ($this->activeCategory)
                                <span class="mr-2">{{ $this->activeCategory->icon }}</span>{{ $this->activeCategory->localizedName() }}
                            @else
                                <span x-data x-text="$store.lang.t(&quot;Today\'s picks&quot;,&quot;Sélection du jour&quot;)"></span>
                            @endif
                        </h1>
                        <p class="text-sm text-slate-600 mt-0.5">
                            {{ $this->listings->total() }}
                            <span x-data x-text="$store.lang.t('listings in your area','annonces dans votre région')"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <span class="text-slate-600 font-medium" x-data x-text="$store.lang.t('Sort by','Trier par')"></span>
                            <select wire:model.live="sort"
                                    class="rounded-full bg-white ring-1 ring-slate-300 text-slate-900 px-3 py-1.5 text-sm font-medium focus:ring-cm-green focus:outline-none cursor-pointer">
                                <option value="newest">{{ app()->getLocale() === 'fr' ? 'Plus récent' : 'Newest' }}</option>
                                <option value="price_asc">{{ app()->getLocale() === 'fr' ? 'Prix ↑' : 'Price ↑' }}</option>
                                <option value="price_desc">{{ app()->getLocale() === 'fr' ? 'Prix ↓' : 'Price ↓' }}</option>
                                <option value="popular">{{ app()->getLocale() === 'fr' ? 'Populaire' : 'Popular' }}</option>
                                <option value="ranked">{{ app()->getLocale() === 'fr' ? 'Recommandé' : 'Recommended' }}</option>
                            </select>
                        </label>
                        <a href="{{ route('marketplace.sell') }}" wire:navigate
                           class="inline-flex items-center gap-2 bg-cm-green hover:bg-cm-green/90 text-white font-semibold rounded-full px-5 py-2 text-sm shadow-md transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                            <span x-data x-text="$store.lang.t('Sell','Vendre')"></span>
                        </a>
                    </div>
                </div>

                {{-- Mobile sort row --}}
                <div class="lg:hidden flex items-center justify-between mb-3 px-1">
                    <div class="text-sm text-slate-600">
                        {{ $this->listings->total() }}
                        <span x-data x-text="$store.lang.t('listings','annonces')"></span>
                    </div>
                    <label class="flex items-center gap-1.5 text-xs">
                        <span class="text-slate-500" x-data x-text="$store.lang.t('Sort','Trier')"></span>
                        <select wire:model.live="sort" class="rounded-full bg-white ring-1 ring-slate-300 text-slate-800 px-2.5 py-1 text-xs font-medium focus:ring-cm-green focus:outline-none">
                            <option value="newest">{{ app()->getLocale() === 'fr' ? 'Récent' : 'Newest' }}</option>
                            <option value="price_asc">{{ app()->getLocale() === 'fr' ? 'Prix ↑' : 'Price ↑' }}</option>
                            <option value="price_desc">{{ app()->getLocale() === 'fr' ? 'Prix ↓' : 'Price ↓' }}</option>
                            <option value="popular">{{ app()->getLocale() === 'fr' ? 'Populaire' : 'Popular' }}</option>
                        </select>
                    </label>
                </div>

                {{-- Skeleton loading --}}
                <div wire:loading.flex wire:target="query,sort,condition,fulfillment,priceMin,priceMax,region"
                     class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 mb-3">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="animate-pulse bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
                            <div class="aspect-square bg-slate-200"></div>
                            <div class="p-3 space-y-2">
                                <div class="h-3.5 w-1/2 bg-slate-200 rounded"></div>
                                <div class="h-3 w-3/4 bg-slate-200 rounded"></div>
                                <div class="h-2.5 w-1/3 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    @endfor
                </div>

                <div wire:loading.remove wire:target="query,sort,condition,fulfillment,priceMin,priceMax,region">
                    @if ($this->listings->isEmpty())
                        <div class="text-center py-20 bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm">
                            <div class="text-6xl mb-3">🛍️</div>
                            <div class="text-xl font-bold text-slate-900" x-data x-text="$store.lang.t('No listings yet','Aucune annonce')"></div>
                            <p class="mt-1 text-sm text-slate-600 max-w-sm mx-auto" x-data x-text="$store.lang.t('Try adjusting your filters or be the first to sell something in this category.','Essayez d\'ajuster vos filtres ou soyez le premier à vendre quelque chose dans cette catégorie.')"></p>
                            <a href="{{ route('marketplace.sell') }}" wire:navigate
                               class="mt-5 inline-flex items-center gap-2 bg-cm-green text-white px-5 py-2.5 rounded-full text-sm font-semibold hover:bg-cm-green/90 shadow-md">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                <span x-data x-text="$store.lang.t('Create your first listing','Créer ma première annonce')"></span>
                            </a>
                        </div>
                    @else
                        <div class="mp-grid grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            @foreach ($this->listings as $listing)
                                <x-marketplace.listing-card :listing="$listing" />
                            @endforeach
                        </div>
                        <div class="mt-6">
                            {{ $this->listings->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>
</div>
