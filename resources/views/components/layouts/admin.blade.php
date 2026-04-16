<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data x-bind:class="$store.lang.current === 'fr' ? 'lang-fr' : 'lang-en'">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} — {{ $__siteName ?? 'Cameroon Community' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-900 font-[Inter] antialiased">
    <div class="flex h-screen overflow-hidden" x-data="adminShell()">
        {{-- Sidebar --}}
        <aside class="w-64 shrink-0 bg-slate-900 text-white flex flex-col transition-all duration-300
                      max-lg:fixed max-lg:inset-y-0 max-lg:left-0 max-lg:z-40"
               :class="{ 'max-lg:-translate-x-full': !sidebarOpen }">

            {{-- Logo --}}
            <div class="h-16 flex items-center gap-3 px-5 border-b border-white/10">
                @if($__siteLogo ?? null)
                <img src="{{ $__siteLogo }}" alt="Logo" class="h-14 object-contain">
                @else
                <span class="text-3xl">🇨🇲</span>
                @endif
                <p class="text-[10px] text-slate-400">Admin Panel</p>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-3">
                @php
                    $navItems = [
                        ['route' => 'admin.dashboard', 'icon' => '📊', 'label_en' => 'Dashboard', 'label_fr' => 'Tableau de Bord'],
                        ['route' => 'admin.users', 'icon' => '👥', 'label_en' => 'Users', 'label_fr' => 'Utilisateurs'],
                        ['route' => 'admin.yard', 'icon' => '💬', 'label_en' => 'The Yard', 'label_fr' => 'Le Yard'],
                        ['route' => 'admin.solidarity', 'icon' => '🤲', 'label_en' => 'Solidarity', 'label_fr' => 'Solidarité'],
                        ['route' => 'admin.moderation', 'icon' => '🛡️', 'label_en' => 'Moderation', 'label_fr' => 'Modération'],
                        ['route' => 'admin.reports', 'icon' => '🚩', 'label_en' => 'Reports', 'label_fr' => 'Signalements'],
                        ['route' => 'admin.cms', 'icon' => '📝', 'label_en' => 'CMS', 'label_fr' => 'CMS'],
                        ['route' => 'admin.settings', 'icon' => '⚙️', 'label_en' => 'Settings', 'label_fr' => 'Paramètres'],
                        ['route' => 'admin.tenants', 'icon' => '🏢', 'label_en' => 'Tenants', 'label_fr' => 'Tenants'],
                        ['route' => 'admin.ai', 'icon' => '🤖', 'label_en' => 'AI Management', 'label_fr' => 'Gestion IA'],
                        ['route' => 'admin.audit', 'icon' => '📋', 'label_en' => 'Audit Log', 'label_fr' => 'Journal d\'Audit'],
                        ['route' => 'admin.analytics', 'icon' => '📈', 'label_en' => 'Analytics', 'label_fr' => 'Analytique'],                        ['route' => 'admin.sponsored-ads', 'icon' => '📢', 'label_en' => 'Sponsored Ads',   'label_fr' => 'Annonces Sponsorisées'],                    ];
                @endphp

                @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors
                          {{ request()->routeIs($item['route'] . '*') ? 'bg-white/10 text-white font-semibold' : 'text-slate-400 hover:text-white hover:bg-white/5' }}">
                    <span class="text-base">{{ $item['icon'] }}</span>
                    <span x-text="$store.lang.t('{{ addslashes($item['label_en']) }}', '{{ addslashes($item['label_fr']) }}')"></span>
                </a>
                @endforeach
            </nav>

            {{-- User info --}}
            <div class="p-3 border-t border-white/10">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="w-8 h-8 rounded-full bg-cm-green/30 flex items-center justify-center text-xs font-bold text-cm-green">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                        <p class="text-[10px] text-slate-400">{{ auth()->user()->getRoleNames()->first() ?? 'Admin' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Mobile sidebar overlay --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="lg:hidden fixed inset-0 bg-black/40 z-30"></div>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            {{-- Top bar --}}
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-lg font-bold text-slate-900">{{ $header ?? 'Dashboard' }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    {{-- Language toggle --}}
                    <button @click="$store.lang.toggle()" class="text-xs font-semibold rounded-lg px-3 py-1.5 border border-slate-200 hover:bg-slate-50 transition-colors"
                            x-text="$store.lang.current === 'en' ? 'FR 🇫🇷' : 'EN 🇬🇧'"></button>

                    {{-- Back to app --}}
                    <a href="{{ route('yard') }}" class="text-sm text-cm-green font-medium hover:underline" x-text="$store.lang.t('← Back to App', '← Retour à l\'App')"></a>

                    {{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-500 hover:text-slate-700" x-text="$store.lang.t('Logout', 'Déconnexion')"></button>
                    </form>
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
        function adminShell() {
            return {
                sidebarOpen: false,
            };
        }
    </script>

    @livewireScripts
    @stack('scripts')
</body>
</html>
