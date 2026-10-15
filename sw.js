/* PHILSURIN service worker — SAFE caching policy.
   Pages (.php) and API calls always go to the network (never cached), so
   login/logout, sessions, and code fixes take effect immediately. Only same-
   origin static assets (js/css/images/fonts/manifest) are cached for speed.
   Bump CACHE whenever static assets change; old caches are purged on activate. */
const CACHE = 'philsurin-static-v3';
const STATIC_EXTENSIONS = ['.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.ico', '.woff', '.woff2', '.ttf', '.webmanifest'];

function isStaticAsset(url) {
  return STATIC_EXTENSIONS.some(function (ext) { return url.pathname.endsWith(ext); });
}

self.addEventListener('install', function (event) {
  // No pre-caching of pages: pre-caching HTML is what made browsers serve
  // stale encoding pages forever. skipWaiting so updates apply right away.
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
  // Delete every cache from older versions so stale pages/JS never come back.
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) { return key !== CACHE ? caches.delete(key) : undefined; }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET') return;                 // POST (login/save) untouched
  if (url.origin !== self.location.origin) return;            // cross-origin passthrough
  if (url.pathname.includes('/api/')) return;                 // API always network
  if (!isStaticAsset(url)) return;                            // PHP pages always network
  event.respondWith(
    caches.open(CACHE).then(function (cache) {
      return cache.match(event.request).then(function (hit) {
        return hit || fetch(event.request).then(function (res) {
          if (res && res.ok) {
            const copy = res.clone();
            cache.put(event.request, copy);
          }
          return res;
        });
      });
    })
  );
});
