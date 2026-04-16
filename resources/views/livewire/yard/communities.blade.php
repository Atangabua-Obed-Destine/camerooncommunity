<div>
    {{-- ══════════════════════════════════════════════════════════════
         COMMUNITIES MODAL — WhatsApp-style overlay
         ══════════════════════════════════════════════════════════════ --}}
    @if($show)
    {{-- Backdrop --}}
    <div class="comm-backdrop" wire:click="close" x-data x-transition.opacity></div>

    {{-- Modal --}}
    <div class="comm-modal" x-data x-transition.scale.95.origin.center x-trap.noscroll="true">

        {{-- ── Header ── --}}
        <div class="comm-modal__header">
            <div class="comm-modal__header-left">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                <h2 class="comm-modal__title" x-text="$store.lang.t('Communities', 'Communautés')"></h2>
            </div>
            <div class="comm-modal__header-right">
                <button wire:click="close" class="comm-modal__close" title="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- ── Search Bar ── --}}
        <div class="comm-search">
            <div class="comm-search__inner">
                <svg class="comm-search__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="comm-search__input"
                       placeholder="{{ app()->getLocale() === 'fr' ? 'Rechercher une communauté...' : 'Search a community...' }}">
            </div>
        </div>

        {{-- ── Tabs ── --}}
        <div class="comm-tabs">
            <button wire:click="setTab('mine')"
                    class="comm-tabs__tab {{ $tab === 'mine' ? 'comm-tabs__tab--active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                <span x-text="$store.lang.t('My communities', 'Mes communautés')"></span>
                <span class="comm-tabs__badge">{{ $this->myCommunities->count() }}</span>
            </button>
            <button wire:click="setTab('discover')"
                    class="comm-tabs__tab {{ $tab === 'discover' ? 'comm-tabs__tab--active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                <span x-text="$store.lang.t('Discover', 'Découvrir')"></span>
                <span class="comm-tabs__badge">{{ $this->discoverCommunities->count() }}</span>
            </button>
        </div>

        {{-- ── Toolbar (count + create) ── --}}
        <div class="comm-toolbar">
            @if($tab === 'mine')
                <span class="comm-toolbar__count">{{ $this->myCommunities->count() }} {{ $this->myCommunities->count() === 1 ? (app()->getLocale() === 'fr' ? 'communauté' : 'community') : (app()->getLocale() === 'fr' ? 'communautés' : 'communities') }}</span>
            @else
                <span class="comm-toolbar__count">{{ $this->discoverCommunities->count() }} {{ app()->getLocale() === 'fr' ? 'disponibles' : 'available' }}</span>
            @endif
            <button wire:click="startCreate" class="comm-toolbar__create">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span x-text="$store.lang.t('Create', 'Créer')"></span>
            </button>
        </div>

        {{-- ── Create Form (inline, slides down) ── --}}
        @if($creating)
        <div class="comm-create" x-transition>
            <div class="comm-create__inner">
                <input type="text" wire:model="newName" class="comm-create__input"
                       placeholder="{{ app()->getLocale() === 'fr' ? 'Nom de la communauté' : 'Community name' }}"
                       maxlength="100" autofocus>
                <textarea wire:model="newDescription" class="comm-create__textarea"
                          placeholder="{{ app()->getLocale() === 'fr' ? 'Description (optionnel)' : 'Description (optional)' }}"
                          maxlength="500" rows="2"></textarea>

                {{-- Privacy toggle (WhatsApp-style) --}}
                <label class="comm-create__privacy">
                    <div class="comm-create__privacy-info">
                        <svg class="w-4.5 h-4.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                        <div>
                            <span class="comm-create__privacy-label" x-text="$store.lang.t('Private group', 'Groupe privé')"></span>
                            <span class="comm-create__privacy-hint" x-text="$store.lang.t('Members must be approved to join', 'Les membres doivent être approuvés')"></span>
                        </div>
                    </div>
                    <div class="comm-create__toggle" :class="$wire.newIsPrivate && 'comm-create__toggle--on'" wire:click="$toggle('newIsPrivate')">
                        <div class="comm-create__toggle-knob"></div>
                    </div>
                </label>

                @error('newName') <p class="comm-create__error">{{ $message }}</p> @enderror
                <div class="comm-create__actions">
                    <button wire:click="resetCreate" class="comm-create__btn comm-create__btn--cancel"
                            x-text="$store.lang.t('Cancel', 'Annuler')"></button>
                    <button wire:click="createCommunity" class="comm-create__btn comm-create__btn--submit"
                            x-text="$store.lang.t('Create Community', 'Créer la communauté')"></button>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Community Cards Grid ── --}}
        <div class="comm-grid">
            @if($tab === 'mine')
                @forelse($this->myCommunities as $room)
                    @include('livewire.yard.partials.community-card', ['room' => $room, 'isMember' => true])
                @empty
                    <div class="comm-empty">
                        <svg class="w-16 h-16 text-slate-200 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        <p class="text-sm text-slate-400" x-text="$store.lang.t('You haven\'t joined any community yet', 'Vous n\'avez rejoint aucune communauté')"></p>
                    </div>
                @endforelse
            @else
                @forelse($this->discoverCommunities as $room)
                    @include('livewire.yard.partials.community-card', ['room' => $room, 'isMember' => false, 'pendingRequestRoomIds' => $this->pendingRequestRoomIds])
                @empty
                    <div class="comm-empty">
                        <svg class="w-16 h-16 text-slate-200 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3"/></svg>
                        <p class="text-sm text-slate-400" x-text="$store.lang.t('No communities to discover right now', 'Aucune communauté à découvrir pour le moment')"></p>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
    @endif
</div>
