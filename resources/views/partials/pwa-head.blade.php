{{-- PWA head tags + service worker registration.
     Included by all three root layouts (app, guest, admin).

     The registration script is inline rather than @push('scripts') on purpose:
     guest.blade.php has no @stack('scripts'), so a push would silently vanish on
     every public and auth page. --}}
@php($pwaBase = \App\Http\Controllers\PwaController::base())

<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#015083">
<meta name="application-name" content="{{ $__siteName ?? 'Cameroon Network' }}">

{{-- iOS home-screen support. status-bar-style 'black' makes iOS lay the page out
     below the status bar, so the fixed header needs no safe-area padding. --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="CM Network">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">

{{-- Mark the document when running as an installed app, so "get the app"
     CTAs can hide themselves. Runs in <head> before first paint, so there is no
     flash of a button that is about to disappear.

     Any element carrying data-pwa-hide-when-installed is hidden inside the
     installed app. The rule lives here rather than in app.css so that adding it
     needs no `npm run build` (public/build is committed). --}}
<style>html.pwa-standalone [data-pwa-hide-when-installed]{display:none !important}</style>
<script>
    (function () {
        var standalone = window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.matchMedia('(display-mode: minimal-ui)').matches
            || window.navigator.standalone === true;

        if (standalone) {
            document.documentElement.classList.add('pwa-standalone');
        }

        // Covers an install that happens while this page is open.
        window.addEventListener('appinstalled', function () {
            document.documentElement.classList.add('pwa-standalone');
        });
    })();
</script>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register(@js(route('pwa.sw')), { scope: @js($pwaBase) })
                .then(function (registration) { registration.update(); })
                .catch(function () { /* Non-fatal: the app works fine without it. */ });
        });
    }
</script>
