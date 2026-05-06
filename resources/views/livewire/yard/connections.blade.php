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
            <button wire:click="setTab('blocked')" class="comm-tabs__tab {{ $tab === 'blocked' ? 'comm-tabs__tab--active' : '' }}">
                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.172l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span x-text="$store.lang.t('Blocked', 'Bloqués')"></span>
                <span class="comm-tabs__badge" x-show="true">{{ $this->blockedConnections->count() }}</span>
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

            {{-- ── BLOCKED CONNECTIONS ── --}}
            @if($tab === 'blocked')
                @forelse($this->blockedConnections as $item)
                    @php
                        $u = $item['user'];
                        $blockedByMe = $item['blockedByMe'];
                    @endphp
                    <div class="relative p-3 rounded-lg border border-red-200 bg-red-50/50 hover:bg-red-50 transition-colors group">
                        <div class="flex items-center gap-3">
                            {{-- Avatar --}}
                            <div class="relative flex-shrink-0">
                                @if($u->avatar)
                                    <img src="{{ asset('storage/' . $u->avatar) }}" alt="{{ $u->name }}" class="w-12 h-12 rounded-full object-cover">
                                @else
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br {{ \App\Support\AvatarPalette::colorClass('user:' . $u->id) }} flex items-center justify-center text-white font-bold text-sm">
                                        {{ substr($u->username ?? $u->name, 0, 1) }}
                                    </div>
                                @endif
                                {{-- Blocked badge --}}
                                <div class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px] font-bold" title="{{ $blockedByMe ? 'Blocked by you' : 'Blocked by them' }}">
                                    🚫
                                </div>
                            </div>

                            {{-- Name + indicator --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-slate-900 truncate">{{ $u->username ?? $u->name }}</p>
                                <p class="text-xs text-red-600 font-medium">
                                    @if($blockedByMe)
                                        <span x-text="$store.lang.t('Blocked by you', 'Bloqué par vous')"></span>
                                    @else
                                        <span x-text="$store.lang.t('Blocked by them', 'Bloqué par eux')"></span>
                                    @endif
                                </p>
                            </div>

                            {{-- Actions --}}
                            <div class="flex gap-1">
                                <button
                                    wire:click="unblock({{ $u->id }})"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors"
                                    x-text="$store.lang.t('Unblock', 'Débloquer')"
                                ></button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="comm-empty">
                        <div class="flex flex-col items-center justify-center py-8">
                            <div class="text-4xl mb-2">✌️</div>
                            <p class="text-sm text-slate-600 font-medium" x-text="$store.lang.t('No blocked connections', 'Aucune connexion bloquée')"></p>
                            <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('Your blocked list is empty — stay friendly!', 'Votre liste bloquée est vide — restez courtois !')"></p>
                        </div>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
    @endif
</div>
