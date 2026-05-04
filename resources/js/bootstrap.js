import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
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

// Final safety net for full-page navigations / tab close
window.addEventListener('pagehide', disconnectEcho);

