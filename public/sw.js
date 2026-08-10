const CACHE = 'filmi-assets-v1';
const ASSETS = [
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    '/assets/icons/favicon.svg',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || !url.pathname.startsWith('/assets/')) {
        return; // pages et API toujours pris sur le réseau
    }
    event.respondWith(
        caches.match(event.request).then((hit) => hit || fetch(event.request))
    );
});
