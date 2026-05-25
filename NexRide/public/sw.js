// NexRide Service Worker
const CACHE_NAME = 'nexride-v1';
const STATIC_ASSETS = [
    '/NexRide/',
    '/NexRide/assets/css/app.css',
    '/NexRide/assets/js/app.js',
    '/NexRide/assets/js/offline.js',
];

const DYNAMIC_CACHE = 'nexride-dynamic-v1';

// Install - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(key => key !== CACHE_NAME && key !== DYNAMIC_CACHE).map(key => caches.delete(key)))
        ).then(() => self.clients.claim())
    );
});

// Fetch - Network first, fallback to cache
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Skip non-GET requests
    if (request.method !== 'GET') return;

    // API calls - Network only (no cache for dynamic data)
    if (request.url.includes('/api/')) {
        event.respondWith(
            fetch(request).catch(() => new Response(JSON.stringify({ error: 'offline' }), {
                headers: { 'Content-Type': 'application/json' }
            }))
        );
        return;
    }

    // Static assets - Cache first, then network
    if (request.url.match(/\.(css|js|png|jpg|svg|ico)$/)) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // HTML pages - Network first, fallback to cache
    event.respondWith(
        fetch(request)
            .then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, clone));
                }
                return response;
            })
            .catch(() => caches.match(request).then(cached => cached || caches.match('/NexRide/')))
    );
});