/*
 * Service worker of the employee portal (served by PortalController::serviceWorker, which fills in the placeholders).
 * It makes the portal installable, keeps the stylesheet, script and logo at hand, and shows a friendly page when
 * there is no network. Portal pages hold personal data, so they are never cached: they always come from the server.
 */
const CACHE = 'aim-portal-__VERSION__';
const OFFLINE = '__OFFLINE__';
const SHELL = __SHELL__;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll([...SHELL, OFFLINE])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => k.startsWith('aim-portal-') && k !== CACHE).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }
    if (new URL(req.url).pathname.includes('/assets/')) {
        // Network first, so script modules never mix versions after an update; the cached copy is the offline fallback.
        event.respondWith(fetch(req).then((res) => {
            if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
            return res;
        }).catch(async () => (await caches.match(req, { ignoreSearch: true })) || Response.error()));
    }
});
