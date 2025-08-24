// service-worker.js - Simple working version
const CACHE_NAME = 'panan-bakan-v1.0.3';
const urlsToCache = [
  '/family/',
  '/family/dashboard.php',
  '/family/manifest.json',
  '/family/offline.html'
];

self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching files');
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  console.log('Service Worker: Activated');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Clearing old cache');
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  // רק cache דפי HTML וקבצים סטטיים, לא API calls
  if (event.request.method === 'GET' && 
      !event.request.url.includes('/api/') && 
      !event.request.url.includes('action=')) {
    
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // אם הצלחנו להביא מהרשת, שמור בcache
          if (!response || response.status !== 200) {
            return response;
          }
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
          return response;
        })
        .catch(() => {
          // אם נכשל, נסה מהcache
          return caches.match(event.request)
            .then(response => {
              if (response) {
                return response;
              }
              // אם אין בcache, החזר דף offline
              if (event.request.headers.get('accept').includes('text/html')) {
                return caches.match('/family/offline.html');
              }
            });
        })
    );
  }
});