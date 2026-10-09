self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Pass-through puro: nessuna cache gestita da questo service worker (serve solo
// a rendere il pannello installabile come PWA). Non chiamare respondWith()
// lascia che sia il browser a gestire la richiesta normalmente, evitando di
// servire risposte rotte o vecchie quando una fetch fallisce.
self.addEventListener('fetch', () => {});
