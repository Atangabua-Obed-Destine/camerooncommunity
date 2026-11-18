<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" x-data x-bind:lang="$store.lang.current">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Language, before anything renders. ?lang=en|fr (how the mobile app opens About,
         Contact, Privacy and Terms) becomes the saved choice that $store.lang reads.
         Those pages also render their x-text on the server, in the server's language;
         where the saved choice differs, that text stays hidden until Alpine swaps it,
         as it was before, rather than showing one language and then the other. --}}
    <script>
        (function () {
            var root = document.documentElement, saved = 'en';
            try {
                var param = new URLSearchParams(location.search).get('lang');
                if (param === 'en' || param === 'fr') localStorage.setItem('cc_lang', JSON.stringify(param));
                saved = JSON.parse(localStorage.getItem('cc_lang') || '"en"');
            } catch (e) {}
            if (saved !== root.lang) {
                root.classList.add('lt-wait');
                var show = function () { root.classList.remove('lt-wait'); };
                document.addEventListener('alpine:initialized', show);
                setTimeout(show, 3000);
            }
        })();
    </script>
    <style>.lt-wait [x-text] { visibility: hidden; }</style>
    <title>{{ $title ?? 'Cameroon Network | Connecting Cameroonians. Wherever They Are.' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'Connect with Cameroonians in your city and country. Find housing, send packages home, get help, all in one place.' }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|dm-sans:400,500,600,700" rel="stylesheet">

    <!-- Favicons: Cameroon flag SVG (vector default) + ICO fallback -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    {{-- PWA: manifest, icons, service worker --}}
    @include('partials.pwa-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- Every guest page gets the larger mobile type scale EXCEPT the landing
     page, which keeps its own marketing typography. Scoped by route rather
     than by layout, because both share this shell. --}}
<body @class(['cn-app' => ! request()->routeIs('home'), 'min-h-[100dvh] bg-slate-900 text-slate-900 antialiased overflow-x-hidden'])>
    {{ $slot }}

    {{-- After deleting an account (AccountController): the one message a
         signed-out page has to show, wherever it lands. --}}
    @if(session('account_deleted'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 8000)" x-transition.opacity
         role="status" aria-live="polite"
         class="fixed inset-x-4 top-4 z-[200] mx-auto max-w-md rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-xl">
        {{ \App\Support\Bilingual::t('Your account has been deleted. Thank you for being part of the community.', 'Votre compte a été supprimé. Merci d\'avoir fait partie de la communauté.') }}
    </div>
    @endif

    {{-- Kamer AI Assistant for visitors (only on non-auth pages, and not inside the app,
         which has Kamer of its own) --}}
    @guest
        @if(!request()->routeIs('login', 'register', 'password.*') && ! ($inApp ?? false))
            @livewire('a-i.kamer-chat')
        @endif
    @endguest

    {{-- PWA install banner. Never inside the app: it is the app. --}}
    @unless($inApp ?? false)
        @include('partials.pwa-install')
    @endunless

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
