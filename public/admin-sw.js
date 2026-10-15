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

self.addEventListener('push', (event) => {
  let data = { title: 'Mega Trasporti', body: '' };

  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'Mega Trasporti', {
      body: data.body || '',
      icon: '/admin-assets/icon-admin.png',
      badge: '/admin-assets/icon-admin.png',
      data: { url: data.url || '/admin' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = (event.notification.data && event.notification.data.url) || '/admin';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsList) => {
      for (const client of clientsList) {
        if (client.url.includes(targetUrl) && 'focus' in client) {
          return client.focus();
        }
      }

      if (clientsList.length > 0 && 'focus' in clientsList[0]) {
        clientsList[0].navigate(targetUrl);
        return clientsList[0].focus();
      }

      return self.clients.openWindow(targetUrl);
    })
  );
});
