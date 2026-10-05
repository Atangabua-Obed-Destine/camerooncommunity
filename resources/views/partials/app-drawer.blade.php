{{-- ═══════════════════════════════════════════════════════════════
     Slide-out menu, opened by the ☰ button beside the logo.

     On a phone there is no Yard rail (it only appears from 768px, and only in
     the Yard), so this is how everything the rail offers — Discover, Kamer AI,
     the upcoming modules, language, profile — is reachable on mobile at all.
     Same destinations, same order, so the two never tell different stories.

     Opened from anywhere with:  $dispatch('open-app-menu')
     Teleported to <body>: the header is a stacking context, and a drawer
     rendered inside it would be painted underneath.
     ═══════════════════════════════════════════════════════════════ --}}
@auth
{{-- Back closes this instead of leaving the page (see resources/js/overlay-history.js). --}}
<div x-data="{ open: false }"
     x-overlay="open"
     @open-app-menu.window="open = true"
     @keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] md:hidden">
            {{-- Backdrop --}}
            <div x-show="open" x-transition.opacity.duration.200ms
                 class="absolute inset-0 bg-slate-900/50"
                 @click="open = false"></div>

            {{-- Panel --}}
            <aside x-show="open"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-150"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full"
                   class="absolute inset-y-0 left-0 flex w-[82vw] max-w-xs flex-col bg-white shadow-2xl">

                {{-- Header: wordmark + close --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <span class="text-lg font-extrabold tracking-tight">
                        <span style="color:#009639">KA</span><span style="color:var(--color-cm-red)">M</span><span style="color:var(--color-cm-yellow)">ER</span>
                    </span>
                    <button type="button" @click="open = false"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                            :aria-label="$store.lang.t('Close', 'Fermer')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Destinations --}}
                <nav class="flex-1 overflow-y-auto px-2 py-3">
                    @php
                        $itemClass = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-slate-700 transition-colors hover:bg-slate-100 w-full text-left';
                        $activeClass = 'bg-cm-green/10 text-cm-green-dark';
                    @endphp

                    <a href="{{ route('home') }}" class="{{ $itemClass }} {{ request()->routeIs('home') ? $activeClass : '' }}">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M11.03 2.59a1.5 1.5 0 0 1 1.94 0l8.5 7.25a1.5 1.5 0 0 1 .53 1.14V20a2 2 0 0 1-2 2h-4a1 1 0 0 1-1-1v-6a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2v-9.02c0-.44.19-.86.53-1.14l8.5-7.25z"/></svg>
                        <span x-text="$store.lang.t('Home', 'Accueil')">Home</span>
                    </a>

                    <a href="{{ route('yard') }}" class="{{ $itemClass }} {{ request()->routeIs('yard*') ? $activeClass : '' }}">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span>GoConnect</span>
                    </a>

                    <button type="button" class="{{ $itemClass }}"
                            @click="open = false; setTimeout(() => window.dispatchEvent(new CustomEvent('open-discover')), 180)">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                        <span x-text="$store.lang.t('Discover', 'Découvrir')">Discover</span>
                    </button>

                    <button type="button" class="{{ $itemClass }}"
                            @click="open = false; setTimeout(() => window.Livewire.dispatch('open-kamer-ai'), 180)">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                        <span>Kamer AI</span>
                    </button>

                    <a href="{{ route('marketplace.index') }}" class="{{ $itemClass }} {{ request()->routeIs('marketplace.*') && ! request()->routeIs('marketplace.seller') ? $activeClass : '' }}">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                        <span>GoMarket</span>
                    </a>

                    {{-- Not built yet: shown so people know what is coming, but inert. --}}
                    <p class="mt-4 mb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                       x-text="$store.lang.t('Coming soon', 'Bientôt')">Coming soon</p>

                    @foreach([
                        ['Solidarity', 'Solidarité', 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z'],
                        ['GoParcel', 'GoParcel', 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
                        ['GoRide', 'GoRide', 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
                        ['GoPartner', 'GoPartner', 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0'],
                    ] as [$labelEn, $labelFr, $path])
                    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-slate-400">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                        <span x-text="$store.lang.t(@js($labelEn), @js($labelFr))">{{ $labelEn }}</span>
                        <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700"
                              x-text="$store.lang.t('Soon', 'Bientôt')">Soon</span>
                    </div>
                    @endforeach
                </nav>

                {{-- Account --}}
                <div class="border-t border-slate-100 px-2 py-3">
                    <a href="{{ route('contact') }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                        <span x-text="$store.lang.t('Contact us', 'Nous contacter')">Contact us</span>
                    </a>

                    <button type="button" @click="$store.lang.toggle()"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[15px] font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                        <span class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full border border-slate-300 text-[10px] font-extrabold text-slate-600"
                              x-text="$store.lang.isEn ? 'FR' : 'EN'">FR</span>
                        <span x-text="$store.lang.isEn ? 'Français' : 'English'">Français</span>
                    </button>

                    <a href="{{ auth()->user()?->profileUrl() ?? route('profile') }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                        <x-user-avatar size="h-[22px] w-[22px]" text="text-[11px]" />
                        <span class="truncate">{{ auth()->user()->username ?? auth()->user()->name }}</span>
                    </a>

                    @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('admin'))
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                        <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                        <span x-text="$store.lang.t('Admin Panel', 'Panneau admin')">Admin Panel</span>
                    </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[15px] font-semibold text-cm-red transition-colors hover:bg-red-50">
                            <svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                            <span x-text="$store.lang.t('Logout', 'Déconnexion')">Logout</span>
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </template>
</div>
@endauth
