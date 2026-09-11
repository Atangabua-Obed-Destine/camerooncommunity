{{-- Service worker source. Served by PwaController::serviceWorker() as JavaScript.
     $base    — app base path with trailing slash ('/camerooncommunity/public/' or '/')
     $version — cache version, derived from Vite's build manifest hash --}}
const BASE     = @json($base, JSON_UNESCAPED_SLASHES);
const VERSION  = @json($version, JSON_UNESCAPED_SLASHES);
const CACHE    = 'cc-' + VERSION;
const OFFLINE  = BASE + 'offline';
const PRECACHE = [OFFLINE, BASE + 'icons/icon-192.png', BASE + 'icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Never touch anything but GET. Livewire posts component updates and file
    // uploads; intercepting or replaying those is how service workers break
    // Livewire apps.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Leave third parties alone: fonts.bunny.net, geo lookups, tile servers, Reverb.
    if (url.origin !== self.location.origin) return;

    const path = url.pathname;
    if (!path.startsWith(BASE)) return;

    // Path relative to the app root, so these rules hold in both the
    // subdirectory (dev) and domain-root (production) layouts.
    const rel = path.slice(BASE.length);

    // Live endpoints and unbounded user uploads must always hit the network.
    //
    // 'livewire' has no trailing slash deliberately: Livewire 4 serves its routes
    // under a hashed prefix (livewire-00374a87/update, /css/{component}.css,
    // /preview-file/...) that differs per install and per deploy. Those component
    // asset URLs do not change when a component changes, so caching them would
    // serve stale CSS/JS after a deploy.
    //
    // storage/ is the symlink to avatars and listing photos — unbounded, it would
    // blow through the cache quota.
    if (rel.startsWith('livewire') ||
        rel.startsWith('broadcasting/') ||
        rel.startsWith('api/') ||
        rel.startsWith('storage/') ||
        rel.startsWith('ads/')) {
        return;
    }

    // HTML documents: network-only, with the offline page as a fallback.
    //
    // Do NOT be tempted to cache navigations. Every page carries a CSRF token, and
    // both layouts react to a 419: app.blade.php redirects to login?expired=1 and
    // guest.blade.php force-reloads. Serving a cached page with a dead token would
    // turn those into a login loop and an infinite reload loop respectively.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    // Static assets: cache-first. Vite content-hashes filenames under build/,
    // so a cached asset is immutable and a new deploy requests a new URL.
    if (rel.startsWith('build/') ||
        rel.startsWith('icons/') ||
        ['style', 'script', 'font', 'image'].includes(req.destination)) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok && res.type === 'basic') {
                    const copy = res.clone();
                    caches.open(CACHE).then((cache) => cache.put(req, copy));
                }
                return res;
            }))
        );
    }
});
