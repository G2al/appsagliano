const APP_VERSION = '7';
const CACHE_PREFIX = 'vialo-worker-';
const CACHE_NAME = `${CACHE_PREFIX}v${APP_VERSION}`;
const withVersion = (path) => `${path}?v=${APP_VERSION}`;
const ASSETS = [
  '/worker/',
  '/worker/home.html',
  '/worker/maintenance.html',
  '/worker/documents.html',
  '/worker/login.html',
  '/worker/signup.html',
  withVersion('/worker/css/style.css'),
  withVersion('/worker/css/GTWalsheimPro.css'),
  withVersion('/worker/css/vendors/bootstrap.css'),
  withVersion('/worker/css/vendors/iconsax.css'),
  withVersion('/worker/js/movements.js'),
  withVersion('/worker/js/maintenances.js'),
  withVersion('/worker/js/documents.js'),
  withVersion('/worker/js/auth.js'),
  withVersion('/worker/js/script.js'),
  withVersion('/worker/js/template-setting.js'),
  withVersion('/worker/js/sticky-header.js'),
  withVersion('/worker/js/bootstrap.bundle.min.js'),
  withVersion('/worker/js/iconsax.js'),
  '/worker/fonts/GTWalsheimPro-Regular.woff2',
  '/worker/images/logo/logo-vialo.png',
  '/worker/images/logo/logo-vialo-white.png',
  '/worker/images/logo/logo-vialo-pwa-worker.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

const isAssetRequest = (request) =>
  request.destination === 'style' ||
  request.destination === 'script' ||
  request.destination === 'image' ||
  request.url.endsWith('.woff2');

const isHtmlNavigate = (request) =>
  request.mode === 'navigate' || (request.destination === '' && request.headers.get('accept')?.includes('text/html'));

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') return;

  const fetchFresh = () => fetch(request, { cache: 'no-store' });

  // Navigation: network first (always fresh), fallback cache, then offline shell.
  if (isHtmlNavigate(request)) {
    event.respondWith(
      fetchFresh()
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() =>
          caches.match(request).then((cached) => cached || caches.match('/worker/home.html'))
        )
    );
    return;
  }

  // Assets: network first (always fresh), fallback cache.
  if (isAssetRequest(request)) {
    event.respondWith(
      fetchFresh()
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // Other GET (e.g., API): network first, fallback cache if present.
  event.respondWith(
    fetchFresh().catch(() => caches.match(request))
  );
});
