{{-- Marketplace feed — FB-inspired, light theme always --}}
<div class="min-h-[calc(100vh-96px)] bg-slate-100"
     x-data="{
        filtersOpen: false,
        modalOpen: @js((bool) $modalSlug),
        init() {
            // Sync the in-grid item modal with browser history so Back / the ✕
            // button behave exactly like Facebook Marketplace.
            window.addEventListener('popstate', (e) => {
                const slug = e.state && e.state.mpItem;
                if (slug) { this.modalOpen = true; this.$wire.openListing(slug); }
                else { this.modalOpen = false; this.$wire.closeListing(); }
            });
        },
        openItem(slug, url) {
            this.modalOpen = true;
            history.pushState({ mpItem: slug }, '', url);
            this.$wire.openListing(slug);
        },
        closeItem() {
            if (!this.modalOpen) return;
            // Step back in history; the popstate handler finishes the close.
            if (history.state && history.state.mpItem) { history.back(); }
            else { this.modalOpen = false; this.$wire.closeListing(); }
        }
     }"
     x-on:mp-open-listing.window="openItem($event.detail.slug, $event.detail.url)"
     x-on:mp-close-listing.window="closeItem()">

    {{-- ─── Mobile sticky bar with title + filters trigger ─── --}}
    <div class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-200 px-3 py-2.5 flex items-center justify-between gap-2 shadow-sm">
        <h1 class="text-lg font-extrabold text-slate-900 truncate">
            @if ($this->activeCategory)
                <span class="mr-1">{{ $this->activeCategory->icon }}</span>{{ $this->activeCategory->localizedName() }}
            @else
                <span x-data x-text="$store.lang.t('GoMarket','GoMarket')"></span>
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
            <aside class="hidden lg:block lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-128px)] lg:overflow-y-auto">
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
                            @if ($locLabel !== '')
                                <span x-data x-text="$store.lang.t('listings','annonces')"></span>
                                <span class="text-slate-400">·</span>
                                <span class="inline-flex items-center gap-0.5 font-medium text-slate-700">
                                    <svg class="w-3.5 h-3.5 text-cm-red" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                                    {{ $locLabel }}@if ($radius) · {{ $radius }} km @endif
                                </span>
                            @else
                                <span x-data x-text="$store.lang.t('listings in your area','annonces dans votre région')"></span>
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        {{-- View mode toggle: grid vs map --}}
                        <div class="inline-flex rounded-full ring-1 ring-slate-300 bg-white overflow-hidden text-sm font-semibold">
                            <button type="button" wire:click="$set('view','grid')"
                                    class="px-3 py-1.5 flex items-center gap-1.5 transition {{ $view === 'grid' ? 'bg-cm-green text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h6v6H4zM14 6h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                                <span x-data x-text="$store.lang.t('Grid','Grille')"></span>
                            </button>
                            <button type="button" wire:click="$set('view','map')"
                                    class="px-3 py-1.5 flex items-center gap-1.5 transition {{ $view === 'map' ? 'bg-cm-green text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span x-data x-text="$store.lang.t('Map','Carte')"></span>
                            </button>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <span class="text-slate-600 font-medium" x-data x-text="$store.lang.t('Sort by','Trier par')"></span>
                            <select wire:model.live="sort"
                                    class="rounded-full bg-white ring-1 ring-slate-300 text-slate-900 px-3 py-1.5 text-sm font-medium focus:ring-cm-green focus:outline-none cursor-pointer">
                                <option value="newest">{{ app()->getLocale() === 'fr' ? 'Plus récent' : 'Newest' }}</option>
                                @if (trim($query) !== '')
                                    <option value="relevance">{{ app()->getLocale() === 'fr' ? 'Pertinence' : 'Relevance' }}</option>
                                @endif
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

                {{-- ─── Save this search strip (only when filters are active) ─── --}}
                @if ($this->hasActiveFilters)
                    <div x-data="{ open: false, name: '' }"
                         class="mb-3 bg-white ring-1 ring-cm-green/30 rounded-2xl shadow-sm p-2.5 sm:p-3 flex items-center gap-2 sm:gap-3">
                        <div class="w-9 h-9 shrink-0 grid place-items-center rounded-full bg-cm-green/10 text-cm-green">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-semibold text-slate-900 truncate">
                                <span x-data x-text="$store.lang.t('Save these filters','Enregistrer ces filtres')"></span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate">
                                <span x-data x-text="$store.lang.t('Get an alert when new items match.','Recevez une alerte quand de nouvelles annonces correspondent.')"></span>
                            </div>
                        </div>
                        <template x-if="!open">
                            <button @click="open = true; $nextTick(() => $refs.nameInput?.focus())"
                                    class="text-xs font-semibold bg-cm-green hover:bg-cm-green/90 text-white rounded-full px-3 py-1.5 transition shrink-0">
                                <span x-data x-text="$store.lang.t('Save this search','Enregistrer')"></span>
                            </button>
                        </template>
                        <template x-if="open">
                            <div class="flex items-center gap-2 shrink-0">
                                <input type="text" x-model="name" x-ref="nameInput" maxlength="120"
                                       :placeholder="$store.lang.t('Name (optional)','Nom (optionnel)')"
                                       @keydown.enter.prevent="$wire.call('saveCurrentSearch', name); open = false; name = ''"
                                       @keydown.escape="open = false; name = ''"
                                       class="rounded-full ring-1 ring-slate-300 px-3 py-1.5 text-xs focus:ring-2 focus:ring-cm-green focus:outline-none w-40 sm:w-56">
                                <button @click="$wire.call('saveCurrentSearch', name); open = false; name = ''"
                                        class="text-xs font-semibold bg-cm-green hover:bg-cm-green/90 text-white rounded-full px-3 py-1.5 transition">
                                    <span x-data x-text="$store.lang.t('Save','Enregistrer')"></span>
                                </button>
                                <button @click="open = false; name = ''"
                                        class="text-xs font-semibold text-slate-600 hover:text-slate-900 rounded-full px-2 py-1.5">✕</button>
                            </div>
                        </template>
                    </div>
                @endif

                {{-- Skeleton loading --}}
                <div wire:loading.flex wire:target="query,sort,condition,fulfillment,priceMin,priceMax,region,radius,locLabel,setLocation"
                     class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-3 gap-y-4 mb-3">
                    @for ($i = 0; $i < 12; $i++)
                        <div class="animate-pulse">
                            <div class="aspect-square rounded-lg bg-slate-200"></div>
                            <div class="pt-1.5 px-0.5 space-y-1.5">
                                <div class="h-3.5 w-1/2 bg-slate-200 rounded"></div>
                                <div class="h-3 w-3/4 bg-slate-200 rounded"></div>
                                <div class="h-2.5 w-1/3 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    @endfor
                </div>

                <div wire:loading.remove wire:target="query,sort,condition,fulfillment,priceMin,priceMax,region,radius,locLabel,setLocation">
                    @if ($view === 'map')
                        @include('livewire.marketplace.partials.map-view', ['points' => $this->mapPoints, 'center' => \App\Support\CameroonGeo::center()])
                    @elseif ($this->listings->isEmpty())
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
                        <div class="mp-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-3 gap-y-4">
                            @foreach ($this->listings as $listing)
                                <x-marketplace.listing-card :listing="$listing" :as-modal="true" />
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

    {{-- ═══ FB-style in-grid item modal (loads over the dimmed grid) ═══ --}}
    @if ($modalSlug)
        <livewire:marketplace.listing-detail :slug="$modalSlug" :as-modal="true" :key="'mp-listing-'.$modalSlug" />
    @endif
</div>
