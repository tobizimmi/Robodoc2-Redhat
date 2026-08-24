const CACHE = 'robodoc-v4';
const STATIC = [
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // Skip non-GET, cross-origin API calls, and POST requests
  if (e.request.method !== 'GET') return;
  if (url.pathname.startsWith('/api/')) return;

  // CDN assets: cache-first
  if (url.hostname.includes('jsdelivr.net') || url.hostname.includes('googleapis.com')) {
    e.respondWith(
      caches.match(e.request).then(hit => hit || fetch(e.request).then(r => {
        const clone = r.clone();
        caches.open(CACHE).then(c => c.put(e.request, clone));
        return r;
      }))
    );
    return;
  }

  // App pages: network-first, fall back to cache
  e.respondWith(
    fetch(e.request).then(r => {
      if (r.ok) {
        const clone = r.clone();
        caches.open(CACHE).then(c => c.put(e.request, clone));
      }
      return r;
    }).catch(() => caches.match(e.request).then(hit => {
      if (hit) return hit;
      // A failed fetch() here means the request never got a response at all
      // (connection dropped/reset/timed out) — that happens both when the
      // browser is genuinely offline AND when a slow backend call (e.g. an
      // external API a page depends on) gets cut off mid-request. Only claim
      // "no internet" when the browser itself confirms there is none;
      // otherwise say so honestly instead of sending the user off to check
      // their WiFi for what's actually a server-side timeout.
      const offline = !self.navigator.onLine;
      const title   = offline ? 'Offline' : 'Anfrage fehlgeschlagen';
      const detail  = offline
        ? 'Keine Internetverbindung.'
        : 'Die Verbindung wurde unterbrochen, bevor eine Antwort kam (z. B. durch eine überlastete oder langsam antwortende externe Schnittstelle). Das ist kein Problem deiner Internetverbindung.';
      return new Response(
        `<html><body style="font-family:sans-serif;padding:2rem"><h2>${title}</h2><p>${detail} <a href="/">Erneut versuchen</a></p></body></html>`,
        { headers: { 'Content-Type': 'text/html' } }
      );
    }))
  );
});
