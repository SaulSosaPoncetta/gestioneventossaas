/**
 * Service worker del escáner (alcance: /escaner).
 * Guarda la app para que abra sin conexión. La API nunca se cachea.
 */
const CACHE = 'geventos-escaner-v1';

const SHELL = [
    '/escaner',
    '/js/escaner.js?v=1',
    '/escaner.webmanifest',
    '/img/escaner-192.png',
    '/img/escaner-512.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => Promise.all(SHELL.map((url) => cache.add(url).catch(() => null))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('geventos-escaner-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    if (req.method !== 'GET' || url.pathname.startsWith('/api/')) {
        return; // la API va siempre a la red
    }

    // La página: primero la red (para tener la última versión), si no hay, la copia guardada
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put('/escaner', copy));
                    return res;
                })
                .catch(() => caches.match('/escaner'))
        );
        return;
    }

    // Archivos estáticos (incluidas las fuentes de los íconos): primero la copia guardada
    event.respondWith(
        caches.match(req).then((cached) => cached || fetch(req).then((res) => {
            if (res.ok || res.type === 'opaque') {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put(req, copy));
            }
            return res;
        }))
    );
});
