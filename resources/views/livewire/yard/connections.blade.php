<div>
    {{-- ══════════════════════════════════════════════════════════════
         CONNECTIONS MODAL
         ══════════════════════════════════════════════════════════════ --}}
    @if($show)
    <div class="comm-backdrop" wire:click="close" x-data x-transition.opacity></div>

    <div class="comm-modal" x-data x-transition.scale.95.origin.center x-trap.noscroll="true">
        {{-- Header --}}
        <div class="comm-modal__header">
            <div class="comm-modal__header-left">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
                <h2 class="comm-modal__title" x-text="$store.lang.t('Connections', 'Connexions')"></h2>
            </div>
            <div class="comm-modal__header-right">
                <button wire:click="close" class="comm-modal__close" title="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Search bar (only used in 'search' tab — but we keep it visible to nudge discovery) --}}
        <div class="comm-search">
            <div class="comm-search__inner">
                <svg class="comm-search__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="comm-search__input"
                       wire:focus="setTab('search')"
                       placeholder="{{ app()->getLocale() === 'fr' ? 'Rechercher une personne par nom ou pseudo...' : 'Search a person by name or username...' }}">
            </div>
        </div>

        {{-- Tabs --}}
        <div class="comm-tabs">
            <button wire:click="setTab('mine')" class="comm-tabs__tab {{ $tab === 'mine' ? 'comm-tabs__tab--active' : '' }}">
                <span x-text="$store.lang.t('My connections', 'Mes connexions')"></span>
                <span class="comm-tabs__badge">{{ $this->myConnections->count() }}</span>
            </button>
            <button wire:click="setTab('requests')" class="comm-tabs__tab {{ $tab === 'requests' ? 'comm-tabs__tab--active' : '' }}">
                <span x-text="$store.lang.t('Requests', 'Demandes')"></span>
                <span class="comm-tabs__badge">{{ $this->incomingRequests->count() }}</span>
            </button>
            <button wire:click="setTab('suggestions')" class="comm-tabs__tab {{ $tab === 'suggestions' ? 'comm-tabs__tab--active' : '' }}">
                <span x-text="$store.lang.t('Suggested', 'Suggestions')"></span>
                <span class="comm-tabs__badge">{{ $this->suggestions->count() }}</span>
            </button>
            <button wire:click="setTab('search')" class="comm-tabs__tab {{ $tab === 'search' ? 'comm-tabs__tab--active' : '' }}">
                <span x-text="$store.lang.t('Search', 'Rechercher')"></span>
            </button>
        </div>

        {{-- Body --}}
        <div class="comm-grid" style="grid-template-columns: 1fr;">

            {{-- ── MY CONNECTIONS ── --}}
            @if($tab === 'mine')
                @forelse($this->myConnections as $u)
                    @include('livewire.yard.partials.connection-row', ['u' => $u, 'state' => 'connected'])
                @empty
                    <div class="comm-empty">
                        <p class="text-sm text-slate-400" x-text="$store.lang.t('You have no connections yet. Try the Suggestions tab.', 'Vous n’avez aucune connexion. Essayez l’onglet Suggestions.')"></p>
                    </div>
                @endforelse

                @if($this->sentRequests->count() > 0)
                    <div class="px-2 pt-4 pb-1 text-xs uppercase tracking-wide text-slate-400 font-semibold">
                        {{ app()->getLocale() === 'fr' ? 'Demandes envoyées' : 'Sent requests' }}
                    </div>
                    @foreach($this->sentRequests as $u)
                        @include('livewire.yard.partials.connection-row', ['u' => $u, 'state' => 'outgoing'])
                    @endforeach
                @endif
            @endif

            {{-- ── INCOMING REQUESTS ── --}}
            @if($tab === 'requests')
                @forelse($this->incomingRequests as $u)
                    @include('livewire.yard.partials.connection-row', ['u' => $u, 'state' => 'incoming'])
                @empty
                    <div class="comm-empty">
                        <p class="text-sm text-slate-400" x-text="$store.lang.t('No pending requests', 'Aucune demande en attente')"></p>
                    </div>
                @endforelse
            @endif

            {{-- ── SUGGESTIONS ── --}}
            @if($tab === 'suggestions')
                @forelse($this->suggestions as $u)
                    @include('livewire.yard.partials.connection-row', ['u' => $u, 'state' => 'none'])
                @empty
                    <div class="comm-empty">
                        <p class="text-sm text-slate-400" x-text="$store.lang.t('No suggestions yet — join more rooms!', 'Aucune suggestion — rejoignez plus de salons !')"></p>
                    </div>
                @endforelse
            @endif

            {{-- ── SEARCH ── --}}
            @if($tab === 'search')
                @if(strlen(trim($search)) < 2)
                    <div class="comm-empty">
                        <p class="text-sm text-slate-400" x-text="$store.lang.t('Type at least 2 characters to search', 'Tapez au moins 2 caractères pour rechercher')"></p>
                    </div>
                @else
                    @forelse($this->searchResults as $u)
                        @include('livewire.yard.partials.connection-row', ['u' => $u, 'state' => $this->stateFor($u->id)])
                    @empty
                        <div class="comm-empty">
                            <p class="text-sm text-slate-400" x-text="$store.lang.t('No matches', 'Aucun résultat')"></p>
                        </div>
                    @endforelse
                @endif
            @endif
        </div>
    </div>
    @endif
</div>
