import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

// The websocket endpoint is derived from the page, NOT from build-time env.
//
// public/build is committed, so whichever machine last ran `npm run build` bakes
// its own VITE_REVERB_* values into the bundle everyone else then serves. A
// bundle built on a dev machine (http, port 8080) would tell production browsers
// to open ws://<domain>:8080 with TLS off — no realtime at all, which takes chat
// delivery, presence and every call signal with it. Reading the scheme and port
// off window.location makes one bundle correct in both places.
const echoSecure = window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    // Behind TLS the proxy serves Reverb on the standard port; in local
    // development it is Reverb's own port, 8080 unless VITE says otherwise.
    wsPort: echoSecure ? 443 : Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: echoSecure ? 443 : Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: echoSecure,
    // Both names stay enabled and forceTLS picks the right one. Listing only
    // one removed pusher's own fallback path for no benefit.
    enabledTransports: ['ws', 'wss'],
});

// ── Echo cleanup ────────────────────────────────────────────────
// Disconnect the WebSocket as soon as the user signs out so the
// Reverb server doesn't keep stale presence/private subscriptions
// (and so the next visitor on the same browser starts fresh).
function disconnectEcho() {
    try { window.Echo?.disconnect(); } catch (_) { /* noop */ }
}

// True if the URL points at the logout endpoint, regardless of leading host.
function isLogoutUrl(url) {
    if (!url) return false;
    try {
        const u = new URL(url, window.location.origin);
        return /\/logout\/?$/.test(u.pathname);
    } catch (_) {
        return /\/logout\/?$/.test(String(url));
    }
}

// Catch logout form submissions (POST → /logout)
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form && form.tagName === 'FORM' && isLogoutUrl(form.action)) {
        disconnectEcho();
    }
}, true);

// Also catch programmatic logouts (Livewire / fetch / XHR POST → /logout).
const _origFetch = window.fetch?.bind(window);
if (_origFetch) {
    window.fetch = function (input, init) {
        const url = typeof input === 'string' ? input : input?.url;
        const method = (init?.method || (typeof input === 'object' && input?.method) || 'GET').toUpperCase();
        if (method === 'POST' && isLogoutUrl(url)) disconnectEcho();
        return _origFetch(input, init);
    };
}

// Allow application code to opt-in: window.dispatchEvent(new Event('auth-logout'))
window.addEventListener('auth-logout', disconnectEcho);

// NOT on 'pagehide'. That fires every time the page is merely hidden — the user
// switching apps, locking the phone, the tab going to the background — and
// pusher-js never reconnects after an explicit disconnect(). A phone left on the
// chat screen would therefore end up with a dead socket: no incoming call rings,
// no live messages, and no sign that anything is wrong. The browser closes the
// socket by itself on a real unload, so there is nothing to clean up here.
//
// Instead, make sure we are connected again whenever the page comes back.
function reconnectEcho() {
    const connection = window.Echo?.connector?.pusher?.connection;
    if (!connection) return;
    if (connection.state === 'connected' || connection.state === 'connecting') return;

    try { window.Echo.connector.pusher.connect(); } catch (_) { /* noop */ }
}

window.addEventListener('pageshow', reconnectEcho);
window.addEventListener('online', reconnectEcho);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') reconnectEcho();
});

