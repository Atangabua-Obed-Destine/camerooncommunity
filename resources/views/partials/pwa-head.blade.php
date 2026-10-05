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
<style>
    html.pwa-standalone [data-pwa-hide-when-installed]{display:none !important}
    /* The other half: shown only inside the installed app. */
    [data-pwa-only-when-installed]{display:none !important}
    html.pwa-standalone [data-pwa-only-when-installed]{display:inline-block !important}
</style>
<script>
    (function () {
        var standalone = window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.matchMedia('(display-mode: minimal-ui)').matches
            || window.navigator.standalone === true;

        if (standalone) {
            document.documentElement.classList.add('pwa-standalone');

            // Script face for the greeting that replaces the install prompt.
            // Loaded here, not in the layout, so it costs nothing for the
            // visitors who never install.
            var script = document.createElement('link');
            script.rel = 'stylesheet';
            script.href = 'https://fonts.bunny.net/css?family=great-vibes:400';
            document.head.appendChild(script);
        }

        // Keep the install prompt event globally. It can fire before Alpine starts, so the
        // banner in partials/pwa-install would miss it if it listened for it itself.
        window.__pwaDeferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            window.__pwaDeferredPrompt = e;
            window.dispatchEvent(new CustomEvent('pwa-installable'));
        });

        // Covers an install that happens while this page is open.
        window.addEventListener('appinstalled', function () {
            window.__pwaDeferredPrompt = null;
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

        // Reload once when a new service worker takes over.
        //
        // The worker already calls skipWaiting() and claim(), so a new build is
        // picked up quickly — but a page that is ALREADY open keeps running the
        // JavaScript it loaded with. An installed app is rarely closed, so it
        // sat on an old bundle for days while a freshly opened browser tab had
        // the new one: the same device behaving two different ways.
        // Only when one worker REPLACES another. The event also fires the
        // first time a worker takes control of a page that had none, and
        // reloading there would bounce every first-time visitor.
        var hadController = !! navigator.serviceWorker.controller;
        var reloading = false;

        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (! hadController || reloading) return;
            reloading = true;
            window.location.reload();
        });
    }
</script>
