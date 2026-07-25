/* IDEMS service worker — field-friendly caching.
   Strategy:
     - GET navigations: network first, fall back to the cache, then to a small
       offline page. Successful pages are cached so a screen opened once can be
       reopened without signal.
     - Static assets: cache first, refreshed in the background.
     - Anything non-GET (form posts) is never cached — those are queued in the
       page itself (see offline.js) and replayed when the connection returns.
*/
const CACHE = 'idems-v1';
const SHELL = ['/assets/css/app.css', '/assets/js/offline.js'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()).catch(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

function offlinePage() {
  return new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;display:grid;place-items:center;height:100vh;background:#f4f6f9;color:#1f2937;text-align:center;padding:24px}' +
    'h1{font-size:20px;margin:0 0 8px}p{color:#6b7280;margin:0 0 14px;max-width:34ch}a{color:#1e40af}</style>' +
    '<div><h1>You are offline</h1><p>This screen has not been opened on this device yet. Any report you were filling in is saved on the phone and will sync when the connection returns.</p>' +
    '<a href="/">Try again</a></div>',
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  );
}

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;                        // never cache posts
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;              // leave third parties alone
  if (/\/(logout|login)$/.test(url.pathname)) return;      // never cache auth

  const isAsset = /\.(css|js|png|jpg|jpeg|svg|woff2?)$/i.test(url.pathname);
  if (isAsset) {
    e.respondWith(
      caches.match(req).then(hit => {
        const net = fetch(req).then(res => { if (res && res.ok) caches.open(CACHE).then(c => c.put(req, res.clone())); return res; }).catch(() => hit);
        return hit || net;
      })
    );
    return;
  }
  e.respondWith(
    fetch(req)
      .then(res => { if (res && res.ok && res.type === 'basic') { const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); } return res; })
      .catch(() => caches.match(req).then(hit => hit || offlinePage()))
  );
});
