const CACHE_NAME = 'sagliano-worker-v2';
const ASSETS = [
  '/worker/',
  '/worker/home.html',
  '/worker/maintenance.html',
  '/worker/documents.html',
  '/worker/login.html',
  '/worker/signup.html',
  '/worker/css/style.css',
  '/worker/css/GTWalsheimPro.css',
  '/worker/css/vendors/bootstrap.css',
  '/worker/css/vendors/iconsax.css',
  '/worker/js/movements.js',
  '/worker/js/maintenances.js',
  '/worker/js/documents.js',
  '/worker/js/auth.js',
  '/worker/js/script.js',
  '/worker/js/template-setting.js',
  '/worker/js/sticky-header.js',
  '/worker/js/bootstrap.bundle.min.js',
  '/worker/js/iconsax.js',
  '/worker/fonts/GTWalsheimPro-Regular.woff2',
  '/worker/images/logo/user/user-logo.svg',
  '/worker/images/logo/user/144.png',
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
          .filter((key) => key.startsWith('sagliano-worker-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
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
