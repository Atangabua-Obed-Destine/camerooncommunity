@php($active = $active ?? 'yard')
<aside class="yard-icon-sidebar" x-data="{ expanded: true, tooltip: '' }" :class="{ 'yard-icon-sidebar--expanded': expanded }">
    {{-- Top section: Logo + main nav --}}
    <div class="yard-icon-sidebar__top">
        {{-- Sidebar title --}}
        <div class="yard-icon-sidebar__title"><span style="color:#009639">KA</span><span class="kamer-m" style="color:var(--color-cm-red)">M<span class="kamer-star kamer-star--1">★</span><span class="kamer-star kamer-star--2">★</span></span><span style="color:var(--color-cm-yellow)">ER</span></div>

        {{-- The Yard (Chats) --}}
        <a href="{{ route('yard') }}"
           class="yard-icon-sidebar__item {{ $active === 'yard' ? 'yard-icon-sidebar__item--active' : '' }}"
           @mouseenter="tooltip = $store.lang.t('The Yard', 'Le Yard')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span class="yard-icon-sidebar__label" x-text="$store.lang.t('The Yard', 'Le Yard')"></span>
        </a>

        {{-- Discover / Explore --}}
        <button type="button"
           @click="window.dispatchEvent(new CustomEvent('open-discover'))"
           class="yard-icon-sidebar__item {{ $active === 'discover' ? 'yard-icon-sidebar__item--active' : '' }}"
           @mouseenter="tooltip = $store.lang.t('Discover', 'Découvrir')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
            <span class="yard-icon-sidebar__label" x-text="$store.lang.t('Discover', 'Découvrir')"></span>
        </button>

        {{-- Kamer AI --}}
        <button type="button"
           @click="Livewire.dispatch('open-kamer-ai')"
           class="yard-icon-sidebar__item {{ $active === 'ai' ? 'yard-icon-sidebar__item--active' : '' }}"
           @mouseenter="tooltip = 'Kamer AI'" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
            <span class="yard-icon-sidebar__label">Kamer AI</span>
        </button>

        {{-- ── Coming Soon Divider ── --}}
        <div class="yard-icon-sidebar__divider">
            <span class="yard-icon-sidebar__divider-label" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
        </div>

        {{-- Solidarity --}}
        <div class="yard-icon-sidebar__item yard-icon-sidebar__item--soon"
             @mouseenter="tooltip = $store.lang.t('Solidarity — Coming Soon', 'Solidarité — Bientôt')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            <span class="yard-icon-sidebar__label" x-text="$store.lang.t('Solidarity', 'Solidarité')"></span>
            <span class="yard-icon-sidebar__badge-soon" x-text="$store.lang.t('Soon', 'Bientôt')"></span>
        </div>

        {{-- Marketplace --}}
        <div class="yard-icon-sidebar__item yard-icon-sidebar__item--soon"
             @mouseenter="tooltip = $store.lang.t('Marketplace — Coming Soon', 'Marketplace — Bientôt')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
            <span class="yard-icon-sidebar__label" x-text="$store.lang.t('Marketplace', 'Marketplace')"></span>
            <span class="yard-icon-sidebar__badge-soon" x-text="$store.lang.t('Soon', 'Bientôt')"></span>
        </div>

        {{-- EasyGoParcel --}}
        <div class="yard-icon-sidebar__item yard-icon-sidebar__item--soon"
             @mouseenter="tooltip = $store.lang.t('EasyGoParcel — Coming Soon', 'EasyGoParcel — Bientôt')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
            <span class="yard-icon-sidebar__label">EasyGoParcel</span>
            <span class="yard-icon-sidebar__badge-soon" x-text="$store.lang.t('Soon', 'Bientôt')"></span>
        </div>

        {{-- RoadFam --}}
        <div class="yard-icon-sidebar__item yard-icon-sidebar__item--soon"
             @mouseenter="tooltip = $store.lang.t('RoadFam — Coming Soon', 'RoadFam — Bientôt')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
            <span class="yard-icon-sidebar__label">RoadFam</span>
            <span class="yard-icon-sidebar__badge-soon" x-text="$store.lang.t('Soon', 'Bientôt')"></span>
        </div>

        {{-- WorkConnect --}}
        <div class="yard-icon-sidebar__item yard-icon-sidebar__item--soon"
             @mouseenter="tooltip = $store.lang.t('WorkConnect — Coming Soon', 'WorkConnect — Bientôt')" @mouseleave="tooltip = ''">
            <svg class="w-[22px] h-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            <span class="yard-icon-sidebar__label">WorkConnect</span>
            <span class="yard-icon-sidebar__badge-soon" x-text="$store.lang.t('Soon', 'Bientôt')"></span>
        </div>
    </div>

    {{-- Bottom section: Settings + Profile --}}
    <div class="yard-icon-sidebar__bottom">
        {{-- Language Toggle --}}
        <button @click="$store.lang.toggle()"
                class="yard-icon-sidebar__item"
                style="padding-left: 14px;"
                @mouseenter="tooltip = $store.lang.isEn ? 'Français' : 'English'" @mouseleave="tooltip = ''">
            <span class="w-[34px] h-[34px] shrink-0 flex items-center justify-center rounded-full border-2 border-slate-200 bg-slate-50 text-[11px] font-extrabold leading-none text-slate-600"
                  x-text="$store.lang.isEn ? 'FR' : 'EN'"></span>
            <span class="yard-icon-sidebar__label" x-text="$store.lang.isEn ? 'Français' : 'English'"></span>
        </button>

        {{-- Profile avatar --}}
        <div x-data="{ profileOpen: false }" class="relative">
            <button @click="profileOpen = !profileOpen"
                    class="yard-icon-sidebar__profile {{ $active === 'profile' ? 'yard-icon-sidebar__item--active' : '' }}"
                    @mouseenter="tooltip = '{{ auth()->user()->username ?? auth()->user()->name }}'" @mouseleave="tooltip = ''">
                <span class="yard-icon-sidebar__avatar {{ auth()->user()->avatar ? 'p-0 overflow-hidden' : '' }}">
                    @if(auth()->user()->avatar)
                        <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                    @else
                        {{ substr(auth()->user()->username ?? auth()->user()->name ?? 'U', 0, 1) }}
                    @endif
                </span>
                <span class="yard-icon-sidebar__profile-info">
                    <span class="yard-icon-sidebar__profile-name">{{ auth()->user()->username ?? auth()->user()->name }}</span>
                    <span class="yard-icon-sidebar__profile-email">{{ auth()->user()->email }}</span>
                </span>
            </button>

            {{-- Profile dropdown (pops right) --}}
            <div x-show="profileOpen" @click.away="profileOpen = false" x-transition
                 class="absolute left-full bottom-0 ml-2 w-52 rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl z-50">
                <div class="px-4 py-2 border-b border-slate-100">
                    <p class="text-sm font-bold text-slate-900 truncate">{{ auth()->user()->username ?? auth()->user()->name }}</p>
                    <p class="text-[11px] text-slate-500 truncate">{{ auth()->user()->email }}</p>
                </div>
                <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                   x-text="$store.lang.t('Profile', 'Profil')"></a>
                @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('admin'))
                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                   x-text="$store.lang.t('Admin Panel', 'Panneau admin')"></a>
                @endif
                <hr class="my-1 border-slate-100">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-cm-red hover:bg-red-50 transition-colors"
                            x-text="$store.lang.t('Logout', 'Déconnexion')"></button>
                </form>
            </div>
        </div>
    </div>

    {{-- Tooltip --}}
    <div x-show="tooltip" x-transition.opacity.duration.150ms
         class="yard-icon-sidebar__tooltip"
         :style="''"
         x-text="tooltip" x-cloak></div>

    {{-- Edge expand/collapse handle --}}
    <button @click="expanded = !expanded"
            class="yard-icon-sidebar__edge-handle"
            :title="expanded ? $store.lang.t('Collapse', 'Réduire') : $store.lang.t('Expand', 'Agrandir')">
        <svg class="w-3 h-3 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </button>
</aside>
