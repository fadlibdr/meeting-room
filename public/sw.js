/*
 * Service worker — Meeting Room BPJS Kesehatan (Stage 3.2).
 *
 * Deliberately conservative for an authenticated, CSRF/Livewire app:
 * - Navigations: network-first, falling back to a cached offline page only when
 *   the network is unavailable (never serves a stale authenticated HTML page).
 * - Static build assets + images: stale-while-revalidate (hashed/immutable).
 * - Everything else (POST, Livewire updates, cross-origin): passthrough.
 */
const VERSION = 'v1';
const STATIC_CACHE = `static-${VERSION}`;
const OFFLINE_URL = '/offline';
const PRECACHE = [
    OFFLINE_URL,
    '/images/pwa/icon-192.png',
    '/images/pwa/icon-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== STATIC_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

function isCacheableAsset(url) {
    return url.origin === self.location.origin
        && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/'));
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // App-shell navigations: network-first, offline fallback.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Static, content-hashed assets: stale-while-revalidate.
    if (isCacheableAsset(url)) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(async (cache) => {
                const cached = await cache.match(request);
                const network = fetch(request)
                    .then((response) => {
                        if (response && response.status === 200) {
                            cache.put(request, response.clone());
                        }
                        return response;
                    })
                    .catch(() => cached);
                return cached || network;
            })
        );
    }
});
