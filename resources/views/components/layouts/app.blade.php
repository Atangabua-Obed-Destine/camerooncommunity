@props(['yardMode' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" x-data x-bind:lang="$store.lang.current">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($__siteName ?? 'Cameroon Community') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|dm-sans:400,500,600,700" rel="stylesheet">

    @if($__siteFavicon ?? null)
    <link rel="icon" type="image/png" href="{{ $__siteFavicon }}">
    @else
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🇨🇲</text></svg>">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased" x-data="{ sidebarOpen: false }">
    {{-- Fixed Header Wrapper --}}
    <div class="fixed top-0 left-0 right-0 z-50 relative" style="background: linear-gradient(to right, #1b2d4a 0%, #243a5c 30%, #2e4a6e 60%, #3a5a80 100%)">
        {{-- Logo — absolutely positioned, centered across full header height --}}
        <a href="{{ route('home') }}" class="absolute left-4 lg:left-6 top-1/2 -translate-y-1/2 z-10 flex items-center shrink-0">
            @if($__siteLogo ?? null)
            <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Logo' }}" class="h-[120px] object-contain">
            @else
            <span class="text-5xl">🇨🇲</span>
            @endif
        </a>

        {{-- Location strip (seamless, no border) --}}
        @auth
        <div class="hidden sm:flex h-7 items-center justify-end px-4 lg:px-6">
            <div class="flex items-center gap-1.5 text-[11px] font-medium text-white/70">
                <span>🇨🇲</span>
                <span>{{ auth()->user()->current_region ? auth()->user()->current_region . ', ' : '' }}{{ auth()->user()->current_country ?? __('Unknown') }}</span>
            </div>
        </div>
        @endauth

        {{-- Main navigation --}}
        <nav class="h-16">
            <div class="flex h-full items-center px-4 lg:px-6">
                {{-- Spacer for logo --}}
                <div class="shrink-0 mr-auto w-48"></div>

                {{-- Nav Links + Actions (right) --}}
                <div class="flex items-center gap-1">
                    {{-- Desktop Nav Links --}}
                    <div class="hidden lg:flex items-center gap-0.5">
                        <a href="{{ route('yard') }}" class="px-3 py-2 rounded-lg text-sm font-bold transition-colors {{ $yardMode ? 'text-cm-yellow' : 'text-white hover:text-cm-yellow hover:bg-white/10' }}"
                           x-text="$store.lang.t('The Yard', 'Le Yard')"></a>
                        <a href="#" class="px-3 py-2 rounded-lg text-sm font-bold text-white hover:text-cm-yellow hover:bg-white/10 transition-colors"
                           x-text="$store.lang.t('Discover', 'Découvrir')"></a>
                    </div>

                    {{-- Language Toggle --}}
                    <button @click="$store.lang.toggle()" class="flex items-center gap-1.5 rounded-full border border-white/20 px-3 py-1.5 text-xs font-semibold text-white/80 transition-colors hover:border-white/40 hover:text-white ml-2">
                        <span x-text="$store.lang.isEn ? 'EN' : 'FR'"></span>
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    </button>

                    {{-- Notifications --}}
                    <button class="relative rounded-full p-2 text-white/70 hover:bg-white/10 hover:text-white transition-colors ml-1">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-cm-red text-[10px] font-bold text-white" x-show="false">0</span>
                    </button>

                    {{-- User Menu --}}
                    <div x-data="{ open: false }" class="relative ml-1">
                        <button @click="open = !open" class="flex items-center gap-2 rounded-full p-1 hover:bg-white/10 transition-colors">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-cm-yellow text-sm font-bold text-cm-green">
                                {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <svg class="hidden sm:block h-4 w-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" x-text="$store.lang.t('Profile', 'Profil')"></a>
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" x-text="$store.lang.t('Settings', 'Paramètres')"></a>
                            @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('admin'))
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" x-text="$store.lang.t('Admin Panel', 'Panneau admin')"></a>
                            @endif
                            <hr class="my-1 border-slate-100">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-cm-red hover:bg-red-50" x-text="$store.lang.t('Logout', 'Déconnexion')"></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </div>{{-- /Fixed Header Wrapper --}}

    {{-- Mobile Bottom Navigation --}}
    <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-slate-200 bg-white/95 backdrop-blur-sm lg:hidden transition-transform duration-300"
         x-data="{ inChat: false }"
         @chatroom-entered.window="inChat = true"
         @chatroom-exited.window="inChat = false"
         :class="inChat && 'translate-y-full'">
        <div class="flex h-14 items-center justify-around">
            <a href="{{ route('yard') }}" class="flex flex-col items-center gap-0.5 {{ request()->routeIs('yard*') ? 'text-cm-green' : 'text-slate-400' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span class="text-[10px] font-medium" x-text="$store.lang.t('Yard', 'Yard')">Yard</span>
            </a>
            <a href="#" class="flex flex-col items-center gap-0.5 text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span class="text-[10px] font-medium" x-text="$store.lang.t('Discover', 'Découvrir')">Discover</span>
            </a>
            <a href="#" class="flex flex-col items-center gap-0.5 text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span class="text-[10px] font-medium" x-text="$store.lang.t('Alerts', 'Alertes')">Alerts</span>
            </a>
            <a href="{{ route('profile') }}" class="flex flex-col items-center gap-0.5 {{ request()->routeIs('profile*') ? 'text-cm-green' : 'text-slate-400' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="text-[10px] font-medium" x-text="$store.lang.t('Profile', 'Profil')">Profile</span>
            </a>
        </div>
    </nav>

    {{-- Main Content --}}
    <main class="{{ auth()->check() ? 'pt-[92px]' : 'pt-16' }} pb-14 lg:pb-0">
        {{ $slot }}
    </main>

    {{-- Location Tracker (silent — detects country changes, shows toast) --}}
    @auth
        @livewire('location-tracker')
    @endauth

    {{-- Kamer AI Assistant --}}
    @auth
        @unless($yardMode)
            @livewire('a-i.kamer-chat')
        @endunless
    @endauth

    {{-- Real-time connection request / accept notifier (toast + chime + confetti) --}}
    <x-connection-notifier />

    @livewireScripts
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status }) => {
                    if (status === 419) {
                        window.location.reload();
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
