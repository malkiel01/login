// service-worker.js - עם בקשת הרשאת התראות בהתקנה
const CACHE_NAME = 'panan-bakan-v1.0.4';

const urlsToCache = [
  '/family/',
  '/family/dashboard.php',
  '/family/auth/login.php',
  '/family/css/dashboard.css',
  '/family/css/group.css',
  '/family/css/styles.css',
  '/family/js/group.js',
  '/family/js/notification-prompt.js',
  '/family/offline.html',
  '/family/images/icons/android/android-launchericon-192-192.png',
  '/family/images/icons/android/android-launchericon-512-512.png',
  '/family/images/icons/ios/180.png'
];

self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    Promise.all([
      // Cache הקבצים
      caches.open(CACHE_NAME)
        .then(cache => {
          console.log('Service Worker: Caching files');
          return cache.addAll(urlsToCache);
        }),
      
      // בקש הרשאת התראות מיד עם ההתקנה
      requestNotificationPermissionOnInstall()
    ]).then(() => {
      console.log('Service Worker: Installation complete');
      self.skipWaiting();
    })
  );
});

// פונקציה לבקשת הרשאת התראות בזמן ההתקנה
async function requestNotificationPermissionOnInstall() {
  try {
    // בדוק אם התראות נתמכות
    if (!('Notification' in globalThis)) {
      console.log('Notifications not supported');
      return;
    }
    
    // אם עדיין לא נתנה הרשאה, בקש אותה
    if (Notification.permission === 'default') {
      console.log('Service Worker: Requesting notification permission');
      
      // שלח הודעה לכל הלקוחות (דפים) שפתוחים
      const clients = await self.clients.matchAll();
      clients.forEach(client => {
        client.postMessage({
          type: 'REQUEST_NOTIFICATION_PERMISSION',
          message: 'Requesting notification permission after install'
        });
      });
    }
  } catch (error) {
    console.error('Error requesting notification permission:', error);
  }
}

self.addEventListener('activate', event => {
  console.log('Service Worker: Activated');
  event.waitUntil(
    Promise.all([
      // נקה cache ישן
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('Service Worker: Clearing old cache');
              return caches.delete(cacheName);
            }
          })
        );
      }),
      
      // בקש הרשאת התראות גם אחרי activation
      requestNotificationPermissionOnActivate()
    ]).then(() => {
      self.clients.claim();
    })
  );
});

// פונקציה נוספת לבקשת הרשאה אחרי activation
async function requestNotificationPermissionOnActivate() {
  try {
    if ('Notification' in globalThis && Notification.permission === 'default') {
      const clients = await self.clients.matchAll();
      if (clients.length > 0) {
        clients.forEach(client => {
          client.postMessage({
            type: 'REQUEST_NOTIFICATION_PERMISSION',
            message: 'Service Worker activated - requesting notification permission'
          });
        });
      }
    }
  } catch (error) {
    console.error('Error in activate notification request:', error);
  }
}

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

// טיפול בהתראות Push
self.addEventListener('push', event => {
  console.log('Push message received:', event);
  
  let notificationData = {
    title: 'התראה חדשה',
    body: 'יש לך עדכון חדש',
    icon: '/family/images/icons/android/android-launchericon-192-192.png',
    badge: '/family/images/icons/android/android-launchericon-96-96.png',
    tag: 'general',
    dir: 'rtl',
    lang: 'he'
  };
  
  // אם יש נתונים בהתראה
  if (event.data) {
    try {
      const data = event.data.json();
      notificationData = {
        ...notificationData,
        ...data
      };
    } catch (error) {
      console.error('Error parsing push data:', error);
    }
  }
  
  event.waitUntil(
    self.registration.showNotification(notificationData.title, {
      body: notificationData.body,
      icon: notificationData.icon,
      badge: notificationData.badge,
      tag: notificationData.tag || 'general',
      dir: 'rtl',
      lang: 'he',
      vibrate: [200, 100, 200],
      requireInteraction: false,
      data: notificationData.data || {}
    })
  );
});

// טיפול בלחיצה על התראה
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event.notification);
  
  event.notification.close();
  
  // פתח את האפליקציה או דף ספציפי
  const urlToOpen = event.notification.data?.url || '/family/dashboard.php';
  
  event.waitUntil(
    self.clients.matchAll({ type: 'window' })
      .then(clients => {
        // אם יש דף פתוח, התמקד בו
        for (const client of clients) {
          if (client.url.includes('/family/') && 'focus' in client) {
            return client.focus();
          }
        }
        // אם אין דף פתוח, פתח חדש
        if (self.clients.openWindow) {
          return self.clients.openWindow(urlToOpen);
        }
      })
  );
});