/**
 * PayAccess service worker.
 * Caches static assets and provides an offline fallback. Payments, admin pages
 * and uploads are never cached.
 */
const VERSION = 'payaccess-v2';
const STATIC_CACHE = `${VERSION}-static`;
const PRECACHE = [
    './offline.html',
    './assets/css/app.css',
    './assets/js/app.js',
    './assets/img/icon-192.png',
    './assets/img/favicon.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE).catch(() => undefined))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('payaccess-') && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache dynamic areas of the app.
    if (/\/(admin|api|uploads)\//.test(url.pathname) || /\.php$/.test(url.pathname)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('./offline.html').then((cached) => cached || Response.error()))
        );
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                return cached;
            }
            return fetch(request).then((response) => {
                if (!response || response.status !== 200 || response.type === 'opaque') {
                    return response;
                }
                const copy = response.clone();
                caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                return response;
            });
        })
    );
});
