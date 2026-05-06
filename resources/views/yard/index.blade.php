<x-layouts.app :yardMode="true">
    <x-slot:title>The Yard — Cameroon Network</x-slot:title>

    <div class="yard-container" x-data="yardApp()" @room-selected.window="onRoomSelected($event.detail)" @yard-back.window="goBack()" @yard-open-new-chat.window="openNewChat()" @toggle-room-info.window="toggleInfo()" @open-room-info.window="openInfo($event.detail?.roomId)" @room-type-changed.window="activeRoomType = $event.detail.roomType"
         @open-dm.window="startDmWith($event.detail.userId)"
         @connection-updated.window="syncConnectionState($event.detail)"
         @connection-failed.window="rollbackConnectionState($event.detail)"
         :class="{ 'yard-container--fullscreen': activeRoom && isMobile }">

        {{-- ══════════════════════════════════════════════════════
             WHATSAPP-STYLE ICON SIDEBAR (desktop only)
        ══════════════════════════════════════════════════════ --}}
        @include('yard.partials.icon-sidebar', ['active' => 'yard'])

        {{-- ══════════════════════════════════════════════════════
             PANEL 1 — ROOM LIST
        ══════════════════════════════════════════════════════ --}}
        <div class="yard-panel yard-panel--list"
             :class="{ 'yard-panel--hidden-left': activeRoom && isMobile }"
             x-ref="listPanel">

            <header class="yard-header">
                {{-- Default header --}}
                <div class="flex items-center gap-2 w-full" x-show="!newGroupStep">
                    <h1 class="yard-header__title" x-text="$store.lang.t('The Yard', 'Le Yard')"></h1>
                    <div class="flex items-center gap-1">
                        <button @click="Livewire.dispatch('refreshRoomList'); Livewire.dispatch('room-updated'); window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: $store.lang.t('Refreshed', 'Actualisé') } }))" class="yard-header__btn" :title="$store.lang.t('Refresh', 'Actualiser')">
                            <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M21.015 4.356v4.992"/></svg>
                        </button>
                        <div class="relative" x-data="{ newOpen: false }">
                            <button @click="newOpen = !newOpen" class="yard-header__btn relative" :title="$store.lang.t('New Chat', 'Nouvelle discussion')">
                                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                                @auth
                                    <livewire:yard.connections-badge :variant="'dot'" />
                                @endauth
                            </button>
                            <div x-show="newOpen" @click.away="newOpen = false" x-transition
                                 class="absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-100 py-1 z-50">
                                <button @click="newOpen = false; openNewChat()" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                                    <span x-text="$store.lang.t('New chat', 'Nouvelle discussion')"></span>
                                </button>
                                <button @click="newOpen = false; Livewire.dispatch('open-connections', { tab: 'requests' })" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                                    <span x-text="$store.lang.t('Connections', 'Connexions')"></span>
                                    @auth
                                        <livewire:yard.connections-badge :variant="'pill'" />
                                    @endauth
                                </button>
                                <button @click="newOpen = false; Livewire.dispatch('open-communities')" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                                    <span x-text="$store.lang.t('Group', 'Groupe')"></span>
                                </button>
                            </div>
                        </div>
                        {{-- Three-dot overflow menu (Archived, etc.) --}}
                        <div class="relative" x-data="{ moreOpen: false }">
                            <button @click="moreOpen = !moreOpen" class="yard-header__btn" :title="$store.lang.t('More', 'Plus')" aria-label="More options">
                                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/>
                                </svg>
                            </button>
                            <div x-show="moreOpen" @click.away="moreOpen = false" x-transition x-cloak
                                 class="absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-100 py-1 z-50">
                                <button @click="moreOpen = false; Livewire.dispatch('open-archived')" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M6 8l1 11a2 2 0 002 2h6a2 2 0 002-2l1-11M10 12h4M4 4h16v4H4z"/></svg>
                                    <span x-text="$store.lang.t('Archived', 'Archivés')"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- New Group header (Step 1) --}}
                <div class="flex items-center gap-3 w-full" x-show="newGroupStep === 1" x-cloak>
                    <button @click="closeNewGroup()" class="yard-header__btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    </button>
                    <div class="flex-1">
                        <h2 class="text-base font-semibold text-white" x-text="$store.lang.t('Add group members', 'Ajouter des membres')"></h2>
                        <p class="text-xs text-white/50" x-text="selectedMembers.length + ' ' + $store.lang.t('selected', 'sélectionnés')"></p>
                    </div>
                </div>

                {{-- New Group header (Step 2) --}}
                <div class="flex items-center gap-3 w-full" x-show="newGroupStep === 2" x-cloak>
                    <button @click="newGroupStep = 1" class="yard-header__btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    </button>
                    <h2 class="text-base font-semibold text-white" x-text="$store.lang.t('New group', 'Nouveau groupe')"></h2>
                </div>
            </header>

            {{-- ═══ Default view: search + rooms ═══ --}}
            <div class="flex flex-col flex-1 min-h-0" x-show="!newGroupStep">
                <div class="yard-search">
                    <div class="yard-search__inner">
                        <svg class="yard-search__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                        <input type="text" class="yard-search__input"
                               :placeholder="$store.lang.t('Search...', 'Rechercher...')"
                               @input.debounce.300ms="$dispatch('yard-search', { query: $event.target.value })">
                    </div>
                </div>

                <div class="yard-rooms">
                    <livewire:yard.room-list />
                </div>
            </div>

            {{-- ═══ New Chat: now rendered as a centered modal below (outside the panel) ═══ --}}

            {{-- ═══ New Group panel — Step 1: Add members ═══ --}}
            <div class="flex flex-col flex-1 min-h-0" x-show="newGroupStep === 1" x-cloak>
                {{-- Search users --}}
                <div class="yard-search">
                    <div class="yard-search__inner">
                        <svg class="yard-search__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                        <input type="text" class="yard-search__input" x-model="userQuery"
                               @input.debounce.400ms="searchGroupUsers()"
                               :placeholder="$store.lang.t('Search users to add...', 'Rechercher des utilisateurs...')"
                               x-ref="groupSearch">
                    </div>
                </div>

                {{-- Selected member chips --}}
                <div class="flex flex-wrap gap-2 px-4 py-2 border-b border-slate-100" x-show="selectedMembers.length > 0" x-transition>
                    <template x-for="m in selectedMembers" :key="m.id">
                        <span class="inline-flex items-center gap-1.5 bg-cm-green/10 text-cm-green rounded-full pl-1 pr-2.5 py-1 text-xs font-medium">
                            <span class="w-6 h-6 rounded-full bg-cm-green/20 flex items-center justify-center text-[10px] font-bold" x-text="(m.username || m.name).charAt(0).toUpperCase()"></span>
                            <span x-text="m.username || m.name" class="max-w-20 truncate"></span>
                            <button @click="toggleGroupMember(m)" class="text-cm-green/60 hover:text-cm-green transition-colors">&times;</button>
                        </span>
                    </template>
                </div>

                {{-- User results --}}
                <div class="flex-1 overflow-y-auto">
                    <template x-for="user in searchResults" :key="user.id">
                        <button @click="toggleGroupMember(user)" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors text-left">
                            <div class="w-11 h-11 rounded-full bg-slate-200 flex items-center justify-center shrink-0 text-slate-500 font-bold text-sm">
                                <template x-if="user.avatar">
                                    <img :src="user.avatar" alt="" class="w-11 h-11 rounded-full object-cover">
                                </template>
                                <template x-if="!user.avatar">
                                    <span x-text="(user.username || user.name).charAt(0).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate" x-text="user.username || user.name"></p>
                                <p class="text-xs text-slate-400 truncate" x-text="user.current_region || ''"></p>
                            </div>
                            {{-- Checkmark for selected --}}
                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors"
                                 :class="selectedMembers.find(m => m.id === user.id) ? 'bg-cm-green border-cm-green' : 'border-slate-300'">
                                <svg x-show="selectedMembers.find(m => m.id === user.id)" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </div>
                        </button>
                    </template>

                    <div x-show="userQuery.length < 2 && searchResults.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                        <svg class="w-12 h-12 mb-3 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                        <p class="text-sm" x-text="$store.lang.t('Search for users to add', 'Recherchez des utilisateurs à ajouter')"></p>
                    </div>
                </div>

                {{-- Next button (floating, WhatsApp-style) --}}
                <div class="absolute bottom-5 right-5 z-10" x-show="selectedMembers.length > 0" x-transition>
                    <button @click="newGroupStep = 2" class="w-14 h-14 rounded-full bg-cm-green text-white flex items-center justify-center shadow-lg hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </div>

            {{-- ═══ New Group panel — Step 2: Group name & create ═══ --}}
            <div class="flex flex-col flex-1 min-h-0" x-show="newGroupStep === 2" x-cloak>
                <div class="flex flex-col items-center px-6 pt-8 pb-4">
                    {{-- Group icon placeholder --}}
                    <div class="w-20 h-20 rounded-full bg-slate-200 flex items-center justify-center mb-5">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    </div>

                    {{-- Group name input --}}
                    <div class="w-full border-b-2 border-cm-green pb-1">
                        <input type="text" x-model="groupName"
                               :placeholder="$store.lang.t('Group subject (optional)', 'Nom du groupe (optionnel)')"
                               class="w-full text-base text-slate-800 placeholder-slate-400 bg-transparent border-none outline-none focus:ring-0 p-0"
                               maxlength="100"
                               x-ref="groupNameInput">
                    </div>
                </div>

                {{-- Selected members list --}}
                <div class="px-4 pt-4 pb-2">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2" x-text="$store.lang.t('Members', 'Membres') + ': ' + selectedMembers.length"></p>
                </div>
                <div class="flex-1 overflow-y-auto px-4">
                    <div class="flex flex-wrap gap-4">
                        <template x-for="m in selectedMembers" :key="m.id">
                            <div class="flex flex-col items-center w-16">
                                <div class="w-12 h-12 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold text-sm relative">
                                    <span x-text="(m.username || m.name).charAt(0).toUpperCase()"></span>
                                    <button @click="toggleGroupMember(m)" class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-slate-400 text-white flex items-center justify-center text-[10px] hover:bg-red-500 transition-colors">&times;</button>
                                </div>
                                <p class="text-[11px] text-slate-600 mt-1 text-center truncate w-full" x-text="(m.username || m.name).split(' ')[0]"></p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Create button (floating, WhatsApp-style checkmark) --}}
                <div class="absolute bottom-5 right-5 z-10">
                    <button @click="submitCreateGroup()" class="w-14 h-14 rounded-full bg-cm-green text-white flex items-center justify-center shadow-lg hover:scale-105 transition-transform"
                            :disabled="!groupName.trim() && selectedMembers.length === 0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    </button>
                </div>
            </div>

            {{-- FAB (mobile only) --}}
            <div class="yard-fab-wrap md:hidden" x-data="{ fabOpen: false }" x-show="!newGroupStep">
                <button class="yard-fab" @click="fabOpen = !fabOpen">
                    <svg class="w-6 h-6 transition-transform" :class="fabOpen && 'rotate-45'" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                </button>
                <div x-show="fabOpen" @click.away="fabOpen = false" x-transition class="yard-fab-menu">
                    <button @click="fabOpen = false; Livewire.dispatch('open-communities')" class="yard-fab-menu__item">
                        <span class="yard-fab-menu__icon bg-violet-500">👥</span>
                        <span x-text="$store.lang.t('Communities', 'Communautés')"></span>
                    </button>
                    <button @click="fabOpen = false; Livewire.dispatch('open-connections')" class="yard-fab-menu__item">
                        <span class="yard-fab-menu__icon bg-cm-green">🤝</span>
                        <span x-text="$store.lang.t('Connections', 'Connexions')"></span>
                    </button>
                    <button @click="fabOpen = false; openNewChat()" class="yard-fab-menu__item">
                        <span class="yard-fab-menu__icon bg-amber-500">💬</span>
                        <span x-text="$store.lang.t('New Message', 'Nouveau Message')"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             PANEL 2 — CHAT ROOM
        ══════════════════════════════════════════════════════ --}}
        <div class="yard-panel yard-panel--chat"
             :class="{
                 'yard-panel--active': activeRoom,
                 'yard-panel--hidden-right': !activeRoom && isMobile
             }">

            <div x-show="activeRoom" x-cloak class="h-full">
                <livewire:yard.chat-room />
            </div>

            <div x-show="!activeRoom" class="yard-empty">
                <div class="yard-empty__icon">💬</div>
                <h2 class="yard-empty__title" x-text="$store.lang.t('Welcome to The Yard', 'Bienvenue au Yard')"></h2>
                <p class="yard-empty__desc" x-text="$store.lang.t(
                    'Select a conversation from the sidebar, or join your national and regional rooms to start chatting with fellow Cameroonians.',
                    'Choisissez une conversation dans la barre latérale, ou rejoignez vos salles nationales et régionales pour discuter avec vos compatriotes.'
                )"></p>
                @if(auth()->user()->current_country)
                <p class="mt-3 text-sm text-slate-500"
                   x-data="{
                        country: @js(auth()->user()->current_country),
                        region: @js(auth()->user()->current_region ?? ''),
                   }"
                   x-on:location-changed.window="
                        country = $event.detail?.country || $event.detail?.[0]?.country || country;
                        region  = $event.detail?.region  || $event.detail?.[0]?.region  || region;
                   ">
                    📍 <span x-text="$store.lang.t('Your location: ', 'Votre position : ') + (region ? region + ', ' : '') + country"></span>
                </p>
                @endif
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             PANEL 3 — ROOM INFO SIDEBAR
        ══════════════════════════════════════════════════════ --}}
        <div class="yard-panel yard-panel--info"
             x-show="showInfo && activeRoom" x-transition.opacity x-cloak
             :class="{ 'yard-info-overlay': isMobile }">
            <div class="yard-info-content">
                {{-- WhatsApp-style header --}}
                <div class="wa-info-header">
                    <button @click="showInfo = false" class="wa-info-header__close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <h3 class="wa-info-header__title" x-text="activeRoomType === 'direct_message' ? $store.lang.t('Contact info', 'Info du contact') : $store.lang.t('Group info', 'Info du groupe')"></h3>
                </div>

                {{-- Room details injected from Livewire --}}
                <div class="yard-info__body" id="room-info-panel">
                    <livewire:yard.room-info />
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             PANEL 4 — SPONSORED ADS SIDEBAR (desktop only)
        ══════════════════════════════════════════════════════ --}}
        @include('yard.partials.ads-sidebar')

        {{-- Drawer backdrop --}}
        <div class="yard-backdrop" x-show="menuOpen" x-transition.opacity @click="menuOpen = false" x-cloak></div>

        {{-- Hamburger drawer --}}
        <aside class="yard-drawer" :class="{ 'yard-drawer--open': menuOpen }" x-cloak>
            <div class="yard-drawer__profile">
                <div class="w-14 h-14 rounded-full bg-cm-green flex items-center justify-center text-white text-xl font-bold shadow-lg">
                    {{ substr(auth()->user()->username ?? auth()->user()->name ?? 'U', 0, 1) }}
                </div>
                <p class="mt-3 font-bold text-[15px] text-slate-900 truncate">{{ auth()->user()->username ?? auth()->user()->name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
            </div>
            <nav class="yard-drawer__nav">
                <a href="{{ route('yard') }}" class="yard-drawer__item yard-drawer__item--active">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
                    <span x-text="$store.lang.t('The Yard', 'Le Yard')"></span>
                </a>
                <a href="{{ route('home') }}" class="yard-drawer__item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                    <span x-text="$store.lang.t('Home', 'Accueil')"></span>
                </a>
                <a href="{{ route('profile') }}" class="yard-drawer__item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    <span x-text="$store.lang.t('Profile', 'Profil')"></span>
                </a>
                @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('admin'))
                <a href="{{ route('admin.dashboard') }}" class="yard-drawer__item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                    <span x-text="$store.lang.t('Admin', 'Admin')"></span>
                </a>
                @endif
                <div class="yard-drawer__divider"></div>
                <button @click="$store.lang.toggle(); menuOpen = false" class="yard-drawer__item w-full text-left">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802"/></svg>
                    <span x-text="$store.lang.isEn ? 'Français' : 'English'"></span>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="yard-drawer__item yard-drawer__item--danger w-full text-left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                        <span x-text="$store.lang.t('Logout', 'Déconnexion')"></span>
                    </button>
                </form>
            </nav>
        </aside>

        {{-- ═══════════════════════════════════════════════════════════
             NEW CHAT MODAL — Centered overlay popup (WhatsApp / Slack style)
             Triggered by openNewChat(); shows search + picker + Cancel/Select
             ═══════════════════════════════════════════════════════════ --}}
        <div x-show="newChatView" x-cloak
             class="yard-modal-overlay"
             x-transition.opacity.duration.200ms
             @keydown.escape.window="closeNewChat()">
            {{-- Backdrop --}}
            <div class="yard-modal-overlay__backdrop" @click="closeNewChat()"></div>

            {{-- Dialog --}}
            <div class="yard-modal-dialog"
                 x-transition:enter="transition ease-out duration-250"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @click.stop>

                {{-- Header --}}
                <div class="yard-modal-dialog__header">
                    <div class="yard-modal-dialog__icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                    </div>
                    <h2 class="yard-modal-dialog__title" x-text="$store.lang.t('New conversation', 'Nouvelle conversation')"></h2>
                    <button @click="closeNewChat()" class="yard-modal-dialog__close" :title="$store.lang.t('Close', 'Fermer')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Search bar --}}
                <div class="yard-modal-dialog__search-wrap">
                    <div class="yard-modal-dialog__search">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                        <input type="text" class="yard-modal-dialog__search-input"
                               x-model="dmQuery"
                               @input.debounce.400ms="searchDmUsers()"
                               :placeholder="$store.lang.t('Search by name or email...', 'Rechercher par nom ou e-mail...')"
                               x-ref="newChatSearch">
                        <button x-show="dmQuery.length > 0" @click="dmQuery=''; dmResults=[]"
                                class="text-slate-300 hover:text-slate-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Body: results / empty / suggestions --}}
                <div class="yard-modal-dialog__body">
                    {{-- Results list --}}
                    <template x-if="dmResults.length > 0">
                        <div class="yard-modal-dialog__results">
                            <template x-for="user in dmResults" :key="user.id">
                                <div class="yard-modal-dialog__user"
                                     :class="dmSelected && dmSelected.id === user.id ? 'yard-modal-dialog__user--selected' : ''"
                                     @click="toggleDmSelected(user)"
                                     :style="user.connection_state === 'connected' ? 'cursor:pointer' : 'cursor:default'">
                                    <div class="yard-modal-dialog__avatar">
                                        <template x-if="user.avatar">
                                            <img :src="'{{ asset('storage') }}/' + user.avatar" alt="" class="w-full h-full rounded-full object-cover">
                                        </template>
                                        <template x-if="!user.avatar">
                                            <span x-text="(user.username || user.name).charAt(0).toUpperCase()"></span>
                                        </template>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 truncate" x-text="user.username || user.name"></p>
                                        <p class="text-xs text-slate-500 truncate" x-text="user.current_region || user.email || ''"></p>
                                    </div>

                                    {{-- Per-state action (siblings, no nested templates) --}}
                                    <div x-show="user.connection_state === 'connected'"
                                         class="yard-modal-dialog__check"
                                         :class="dmSelected && dmSelected.id === user.id ? 'yard-modal-dialog__check--on' : ''">
                                        <svg x-show="dmSelected && dmSelected.id === user.id" class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                    </div>
                                    <button x-show="user.connection_state === 'none'"
                                            type="button"
                                            onclick="console.log('[Connect btn] native onclick fired')"
                                            @click.stop.prevent="console.log('[Connect btn] alpine @click fired'); connectUser(user)"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-cm-green text-white hover:bg-cm-green/90 transition-colors whitespace-nowrap"
                                            x-text="$store.lang.t('Connect', 'Connecter')"></button>
                                    <span x-show="user.connection_state === 'outgoing'"
                                          class="px-2.5 py-1 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap"
                                          x-text="$store.lang.t('Pending', 'En attente')"></span>
                                    <button x-show="user.connection_state === 'incoming'"
                                            type="button" @click.stop="acceptUser(user)" :disabled="user.busy"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-60 transition-colors whitespace-nowrap"
                                            x-text="$store.lang.t('Accept', 'Accepter')"></button>
                                    <span x-show="user.connection_state === 'blocked-by-me' || user.connection_state === 'blocked-by-them'"
                                          class="px-2.5 py-1 text-[11px] font-semibold rounded-md bg-slate-100 text-slate-500 whitespace-nowrap"
                                          x-text="$store.lang.t('Unavailable', 'Indisponible')"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Empty state: typing less than 2 chars --}}
                    <div x-show="dmResults.length === 0 && dmQuery.length < 2" class="yard-modal-dialog__empty">
                        <div class="yard-modal-dialog__empty-icon">
                            <svg class="w-7 h-7 text-sky-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.501 20.118a7.5 7.5 0 0114.998 0"/>
                                <circle cx="18" cy="17" r="3" stroke-width="1.8"/>
                                <path stroke-linecap="round" d="m20.5 19.5 1.5 1.5"/>
                            </svg>
                        </div>
                        <p class="yard-modal-dialog__empty-title" x-text="$store.lang.t('Find someone', 'Trouver quelqu\'un')"></p>
                        <p class="yard-modal-dialog__empty-text" x-text="$store.lang.t('Type at least 2 characters to search for a user.', 'Tapez au moins 2 caractères pour rechercher un utilisateur.')"></p>
                    </div>

                    {{-- Empty state: no results --}}
                    <div x-show="dmResults.length === 0 && dmQuery.length >= 2" class="yard-modal-dialog__empty">
                        <div class="yard-modal-dialog__empty-icon yard-modal-dialog__empty-icon--none">
                            <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                        </div>
                        <p class="yard-modal-dialog__empty-title" x-text="$store.lang.t('No users found', 'Aucun utilisateur trouvé')"></p>
                        <p class="yard-modal-dialog__empty-text" x-text="$store.lang.t('Try a different name or email.', 'Essayez un autre nom ou e-mail.')"></p>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="yard-modal-dialog__footer">
                    <button @click="closeNewChat()" class="yard-modal-dialog__btn yard-modal-dialog__btn--ghost"
                            x-text="$store.lang.t('Cancel', 'Annuler')"></button>
                    <button @click="dmSelected && startDmWith(dmSelected.id)"
                            :disabled="!dmSelected"
                            class="yard-modal-dialog__btn yard-modal-dialog__btn--primary"
                            x-text="$store.lang.t('Select', 'Sélectionner')"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ In-app message toasts (top-right) ══════════════════════════ --}}
    {{-- Self-contained x-data so it works even outside yardApp scope. --}}
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"
         x-data="yardToasts()" x-cloak>
        <template x-for="t in toasts" :key="t.id">
            <button @click="t.roomId ? openRoom(t) : dismiss(t.id)"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-x-4"
                    x-transition:enter-end="opacity-100 translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="pointer-events-auto bg-white shadow-lg rounded-xl border px-4 py-3 w-72 text-left hover:bg-slate-50 transition flex items-start gap-3"
                    :class="{
                        'border-slate-200': !t.kind || t.kind === 'info',
                        'border-emerald-200': t.kind === 'success',
                        'border-amber-200': t.kind === 'warning',
                        'border-rose-200': t.kind === 'error',
                    }">
                <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center"
                     :class="{
                         'bg-cm-green/10 text-cm-green': !t.kind || t.kind === 'info',
                         'bg-emerald-100 text-emerald-600': t.kind === 'success',
                         'bg-amber-100 text-amber-600': t.kind === 'warning',
                         'bg-rose-100 text-rose-600': t.kind === 'error',
                     }">
                    <svg x-show="!t.kind || t.kind === 'info'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                    <svg x-show="t.kind === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    <svg x-show="t.kind === 'warning' || t.kind === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900" x-text="t.title"></p>
                    <p x-show="t.body" class="text-xs text-slate-500 truncate" x-text="t.body"></p>
                </div>
            </button>
        </template>
    </div>

    @push('scripts')
    <script>
        function yardApp() {
            return {
                activeRoom: null,
                activeRoomType: null,
                showInfo: false,
                menuOpen: false,
                searchOpen: false,
                isMobile: window.innerWidth < 768,

                // New Chat panel
                newChatView: false,
                dmQuery: '',
                dmResults: [],
                dmSelected: null,

                // New Group flow (step 1 = add members, step 2 = name, null = off)
                newGroupStep: null,
                groupName: '',
                userQuery: '',
                searchResults: [],
                selectedMembers: [],

                init() {
                    window.addEventListener('resize', () => {
                        this.isMobile = window.innerWidth < 768;
                    });
                    window.addEventListener('popstate', () => {
                        if (this.activeRoom && this.isMobile) {
                            this.activeRoom = null;
                            this.showInfo = false;
                            window.dispatchEvent(new CustomEvent('chatroom-exited'));
                        }
                    });

                    // ── In-app message toast + chime ──
                    // RoomList dispatches 'yard-incoming-message' (via Livewire) when a
                    // message arrives in a room the user is NOT currently viewing.
                    window.addEventListener('yard-incoming-message', (e) => {
                        const d = (e && e.detail) || {};
                        // Livewire wraps event payloads in an array — unwrap when needed.
                        const data = Array.isArray(d) ? d[0] : d;
                        if (!data || !data.roomId) return;
                        if (this.activeRoom && Number(this.activeRoom) === Number(data.roomId)) return;

                        this.pushToast({
                            roomId: data.roomId,
                            title: data.senderName || data.roomName || 'New message',
                            body: data.preview || '',
                        });
                        this.playChime();
                    });

                    // ── Generic Livewire 'toast' events are handled directly by
                    // the yardToasts() component below — do NOT subscribe here too,
                    // or each notification would render twice.
                },

                // ── Toast queue is owned by the separate yardToasts() component
                // (mounted on a sibling element) so it can live outside this DOM
                // subtree without losing scope. We just dispatch events here.
                pushToast(t) {
                    window.dispatchEvent(new CustomEvent('yard-toast', { detail: t }));
                },

                // Soft web-audio chime (no asset required, won't 404).
                playChime() {
                    try {
                        const Ctx = window.AudioContext || window.webkitAudioContext;
                        if (!Ctx) return;
                        if (!this._audioCtx) this._audioCtx = new Ctx();
                        const ctx = this._audioCtx;
                        // Some browsers suspend AudioContext until a user gesture; ignore failures.
                        if (ctx.state === 'suspended') ctx.resume().catch(() => {});
                        const now = ctx.currentTime;
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.type = 'sine';
                        o.frequency.setValueAtTime(880, now);
                        o.frequency.exponentialRampToValueAtTime(1320, now + 0.12);
                        g.gain.setValueAtTime(0.0001, now);
                        g.gain.exponentialRampToValueAtTime(0.18, now + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
                        o.connect(g).connect(ctx.destination);
                        o.start(now);
                        o.stop(now + 0.4);
                    } catch (_) { /* noop */ }
                },

                onRoomSelected(detail) {
                    const roomId = detail.roomId ?? detail;
                    if (roomId) {
                        this.activeRoom = roomId;
                        this.showInfo = false;
                        if (this.isMobile) {
                            history.pushState({ room: roomId }, '');
                            window.dispatchEvent(new CustomEvent('chatroom-entered'));
                        }
                    } else {
                        this.activeRoom = null;
                        this.showInfo = false;
                        if (this.isMobile) window.dispatchEvent(new CustomEvent('chatroom-exited'));
                    }
                },

                toggleInfo() {
                    this.showInfo = !this.showInfo;
                    if (this.showInfo && this.activeRoom) {
                        Livewire.dispatch('show-room-info', { roomId: this.activeRoom });
                    }
                },

                openInfo(roomId) {
                    const target = roomId || this.activeRoom;
                    if (!target) return;
                    this.showInfo = true;
                    Livewire.dispatch('show-room-info', { roomId: target });
                },

                goBack() {
                    this.activeRoom = null;
                    this.showInfo = false;
                    if (this.isMobile) {
                        window.dispatchEvent(new CustomEvent('chatroom-exited'));
                        history.back();
                    }
                },

                // ── New Chat panel ──
                openNewChat() {
                    this.newGroupStep = null;
                    this.newChatView = true;
                    this.dmQuery = '';
                    this.dmResults = [];
                    this.dmSelected = null;
                    this.$nextTick(() => { this.$refs.newChatSearch?.focus(); });
                },
                closeNewChat() {
                    this.newChatView = false;
                    this.dmQuery = '';
                    this.dmResults = [];
                    this.dmSelected = null;
                },

                // ── New Group flow ──
                openNewGroup() {
                    this.newChatView = false;
                    this.newGroupStep = 1;
                    this.groupName = '';
                    this.userQuery = '';
                    this.searchResults = [];
                    this.selectedMembers = [];
                    this.$nextTick(() => { this.$refs.groupSearch?.focus(); });
                },
                closeNewGroup() {
                    this.newGroupStep = null;
                    this.groupName = '';
                    this.userQuery = '';
                    this.searchResults = [];
                    this.selectedMembers = [];
                },

                // ── Create Group ──
                async searchGroupUsers() {
                    if (this.userQuery.length < 1) { this.searchResults = []; return; }
                    try {
                        const res = await fetch(`{{ route('yard.users.search') }}?q=${encodeURIComponent(this.userQuery)}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (res.ok) {
                            this.searchResults = await res.json();
                        } else {
                            console.error('Search failed:', res.status);
                            this.searchResults = [];
                        }
                    } catch (e) {
                        console.error('Search error:', e);
                        this.searchResults = [];
                    }
                },

                toggleGroupMember(user) {
                    const idx = this.selectedMembers.findIndex(m => m.id === user.id);
                    if (idx >= 0) this.selectedMembers.splice(idx, 1);
                    else this.selectedMembers.push(user);
                },

                async submitCreateGroup() {
                    if (!this.groupName.trim()) return;
                    try {
                        const res = await fetch('{{ route("yard.group.create") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                name: this.groupName,
                                member_ids: this.selectedMembers.map(m => m.id)
                            })
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.closeNewGroup();
                            if (data.room_id) {
                                this.onRoomSelected({ roomId: data.room_id });
                                Livewire.dispatch('room-selected', { roomId: data.room_id });
                            }
                        } else {
                            console.error('Group creation failed:', res.status, await res.text());
                        }
                    } catch (e) {
                        console.error('Group creation error:', e);
                    }
                },

                // ── New DM ──
                async searchDmUsers() {
                    if (this.dmQuery.length < 1) { this.dmResults = []; return; }
                    try {
                        const res = await fetch(`{{ route('yard.users.search') }}?q=${encodeURIComponent(this.dmQuery)}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (res.ok) {
                            this.dmResults = await res.json();
                        } else {
                            console.error('Search failed:', res.status);
                            this.dmResults = [];
                        }
                    } catch (e) {
                        console.error('Search error:', e);
                        this.dmResults = [];
                    }
                },

                toggleDmSelected(user) {
                    if (user.connection_state !== 'connected') return;
                    if (this.dmSelected && this.dmSelected.id === user.id) {
                        this.dmSelected = null;
                    } else {
                        this.dmSelected = user;
                    }
                },

                connectUser(user) {
                    console.log('[connectUser] called with', user);
                    if (!user) { console.warn('[connectUser] no user'); return; }
                    if (user.busy) { console.warn('[connectUser] user is busy, skipping'); return; }
                    user.busy = true;
                    // Optimistic UI flip — server will confirm via 'connection-updated' event.
                    user.connection_state = 'outgoing';
                    try {
                        if (!window.Livewire) {
                            console.error('[connectUser] window.Livewire not loaded yet');
                            user.connection_state = 'none';
                            user.busy = false;
                            alert('Livewire not loaded — please refresh the page.');
                            return;
                        }
                        console.log('[connectUser] dispatching Livewire event connect-user', { userId: user.id });
                        window.Livewire.dispatch('connect-user', { userId: user.id });
                        console.log('[connectUser] dispatch succeeded');
                    } catch (e) {
                        console.error('[connectUser] dispatch threw:', e);
                        user.connection_state = 'none';
                        alert('Connect failed: ' + (e?.message || e));
                    } finally {
                        // Release busy after a short tick so users can't double-click.
                        setTimeout(() => { user.busy = false; }, 1500);
                    }
                },

                /**
                 * Reconcile a row in dmResults when the Livewire Connections component
                 * confirms a state change (or fails). Detail shape: { userId, state }.
                 */
                syncConnectionState(detail) {
                    if (!detail || !detail.userId) return;
                    const u = (this.dmResults || []).find(r => r.id === detail.userId);
                    if (u && detail.state) {
                        u.connection_state = detail.state;
                        u.busy = false;
                    }
                },
                rollbackConnectionState(detail) {
                    if (!detail || !detail.userId) return;
                    const u = (this.dmResults || []).find(r => r.id === detail.userId);
                    if (u) {
                        u.connection_state = 'none';
                        u.busy = false;
                    }
                    if (detail.message) alert(detail.message);
                },

                acceptUser(user) {
                    if (!user || user.busy) return;
                    user.busy = true;
                    user.connection_state = 'connected';
                    try {
                        window.Livewire.dispatch('accept-user', { userId: user.id });
                    } catch (e) {
                        console.error('accept-user dispatch failed:', e);
                    } finally {
                        setTimeout(() => { user.busy = false; }, 600);
                    }
                },

                async startDmWith(userId) {
                    try {
                        const res = await fetch('{{ route("yard.dm.create") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ user_id: userId })
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.closeNewChat();
                            if (data.room_id) {
                                this.onRoomSelected({ roomId: data.room_id });
                                Livewire.dispatch('room-selected', { roomId: data.room_id });
                            }
                        } else {
                            if (res.status === 403) {
                                const data = await res.json().catch(() => ({}));
                                Livewire.dispatch('toast', { type: 'warning', message: data.message || 'You must connect with this user first.' });
                                this.closeNewChat();
                                Livewire.dispatch('open-connections', { tab: 'search' });
                            } else {
                                console.error('DM creation failed:', res.status, await res.text());
                            }
                        }
                    } catch (e) {
                        console.error('DM creation error:', e);
                    }
                }
            };
        }

        function yardAds() {
            return {
                ads: [],

                init() {
                    this.loadAds();
                    // Refresh ads every 5 minutes
                    setInterval(() => this.loadAds(), 300000);
                },

                async loadAds() {
                    try {
                        const res = await fetch('{{ route("ads.yard") }}', {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.ads = await res.json();
                        }
                    } catch (e) {
                        console.warn('Failed to load ads', e);
                    }
                },

                recordImpClick(adId) {
                    // Click is tracked server-side via the redirect route
                }
            };
        }

        // ── Standalone Alpine component for in-app toasts ──
        // Lives outside yardApp() scope so it survives DOM moves and is reachable
        // even when the yard root is teleported by Livewire morphs.
        function yardToasts() {
            return {
                toasts: [],
                _id: 0,
                init() {
                    // Generic toast event from yardApp.pushToast() (and anywhere else).
                    window.addEventListener('yard-toast', (e) => {
                        const raw = (e && e.detail) || {};
                        const data = Array.isArray(raw) ? raw[0] : raw;
                        if (!data) return;
                        this._enqueue(data);
                    });
                    // Direct Livewire dispatch('toast', { message, type })
                    window.addEventListener('toast', (e) => {
                        const raw = (e && e.detail) || {};
                        const data = Array.isArray(raw) ? raw[0] : raw;
                        if (!data || !data.message) return;
                        this._enqueue({
                            roomId: null,
                            title: data.message,
                            body: '',
                            kind: data.type || 'info',
                        });
                    });
                },
                _enqueue(t) {
                    const id = ++this._id;
                    this.toasts.push({ id, ...t });
                    if (this.toasts.length > 3) this.toasts.shift();
                    setTimeout(() => this.dismiss(id), 5000);
                },
                dismiss(id) {
                    this.toasts = this.toasts.filter(x => x.id !== id);
                },
                openRoom(t) {
                    this.dismiss(t.id);
                    if (t.roomId) {
                        window.dispatchEvent(new CustomEvent('room-selected', {
                            detail: { roomId: t.roomId },
                        }));
                    }
                },
            };
        }
    </script>
    @endpush

    {{-- ── Communities Modal ── --}}
    @auth
    <livewire:yard.communities />
    @endauth

    {{-- ── Connections Modal ── --}}
    @auth
    <livewire:yard.connections />
    @endauth

    {{-- Auto-open Connections modal via ?open=connections&tab=requests --}}
    @auth
    <script>
        document.addEventListener('livewire:initialized', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('open') === 'connections') {
                const tab = params.get('tab') || 'mine';
                window.Livewire.dispatch('open-connections', { tab });
                // Strip the query so reloads don't keep re-opening it.
                const clean = window.location.pathname + window.location.hash;
                window.history.replaceState({}, '', clean);
            }
        });
    </script>
    @endauth

    {{-- ── Call Manager (WebRTC voice/video overlay) ── --}}
    @auth
    <livewire:yard.call-manager />
    <livewire:yard.share-to-chat />
    @endauth
</x-layouts.app>
