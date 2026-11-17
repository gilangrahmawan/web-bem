/*
 * Service worker dashboard BEM UBHINUS (Fase 9).
 * - Cache HANYA aset statis (build Vite, ikon, halaman offline). Halaman panel berisi data pribadi
 *   TIDAK PERNAH di-cache; bila offline, navigasi panel menampilkan /offline.html.
 * - Web Push: menampilkan notifikasi & membuka URL tujuan saat diklik.
 */
const VERSION = 'bem-v2';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/images/icon-192.png', '/images/icon-maskable-192.png', '/favicon.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
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

    // Aset build Vite (nama berisi hash, aman di-cache) & ikon: cache-first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/icon-')) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(async (cache) => {
                const cached = await cache.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }),
        );

        return;
    }

    // Navigasi halaman: selalu dari jaringan (tidak di-cache); offline → halaman offline.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'BEM UBHINUS', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'BEM UBHINUS';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || '',
            icon: data.icon || '/images/icon-192.png',
            badge: '/images/icon-maskable-192.png',
            tag: data.tag || undefined,
            renotify: Boolean(data.tag),
            data: { url: (data.data && data.data.url) || data.url || '/panel-bem' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/panel-bem', self.location.origin);

    // Hanya buka URL di situs sendiri.
    if (target.origin !== self.location.origin) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (new URL(client.url).origin === target.origin && 'focus' in client) {
                    client.navigate(target.href);

                    return client.focus();
                }
            }

            return self.clients.openWindow(target.href);
        }),
    );
});
