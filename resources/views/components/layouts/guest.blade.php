<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" x-data x-bind:lang="$store.lang.current">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Cameroon Network — Connecting Cameroonians. Wherever They Are.' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'Connect with Cameroonians in your city and country. Find housing, send packages home, get help — all in one place.' }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|dm-sans:400,500,600,700" rel="stylesheet">

    <!-- Favicons: Cameroon flag SVG (vector default) + ICO fallback -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
    {{ $slot }}

    {{-- Kamer AI Assistant for visitors (only on non-auth pages) --}}
    @guest
        @if(!request()->routeIs('login', 'register', 'password.*'))
            @livewire('a-i.kamer-chat')
        @endif
    @endguest

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
</body>
</html>
