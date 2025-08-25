// service-worker.js - עם מערכת Polling להתראות Push
// גירסה שעובדת לפוש מקומי
const CACHE_NAME = 'panan-bakan-v1.0.4';
const urlsToCache = [
  '/family/',
  '/family/dashboard.php',
  '/family/auth/login.php',
  '/family/css/dashboard.css',
  '/family/css/group.css',
  '/family/css/styles.css',
  '/family/js/group.js',
  '/family/offline.html',
  '/family/images/icons/android/android-launchericon-192-192.png',
  '/family/images/icons/android/android-launchericon-512-512.png'
];

// Polling interval - בדוק כל 3 שניות
const POLL_INTERVAL = 3000;
let pollInterval = null;

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
    }).then(() => {
      // התחל Polling כשה-Service Worker מופעל
      startPolling();
      return self.clients.claim();
    })
  );
});

// פונקציה להתחלת Polling
function startPolling() {
  console.log('Starting notification polling...');
  
  // נקה interval קיים אם יש
  if (pollInterval) {
    clearInterval(pollInterval);
  }
  
  // בדוק מיידית
  checkForNotifications();
  
  // הגדר בדיקה כל 3 שניות
  pollInterval = setInterval(() => {
    checkForNotifications();
  }, POLL_INTERVAL);
}

// פונקציה לעצירת Polling
function stopPolling() {
  console.log('Stopping notification polling...');
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
}

// בדיקת התראות ממתינות
async function checkForNotifications() {
  try {
    // שלח בקשה לשרת לקבלת התראות ממתינות
    const response = await fetch('/family/api/send-push-notification.php?action=get-pending', {
      method: 'GET',
      credentials: 'include', // חשוב לשלוח cookies
      headers: {
        'Accept': 'application/json'
      }
    });
    
    if (!response.ok) {
      console.error('Failed to fetch notifications:', response.status);
      return;
    }
    
    const data = await response.json();
    
    if (data.success && data.notifications && data.notifications.length > 0) {
      console.log(`Found ${data.notifications.length} pending notifications`);
      
      // הצג כל התראה
      for (const notification of data.notifications) {
        await showNotification(notification);
      }
    }
    
  } catch (error) {
    console.error('Error checking notifications:', error);
  }
}

// הצגת התראה
async function showNotification(data) {
  try {
    const title = data.title || 'התראה חדשה';
    const options = {
      body: data.body || '',
      icon: data.icon || '/family/images/icons/android/android-launchericon-192-192.png',
      badge: data.badge || '/family/images/icons/android/android-launchericon-96-96.png',
      tag: data.type || 'notification',
      data: {
        url: data.url || '/family/dashboard.php',
        type: data.type,
        ...data
      },
      vibrate: [200, 100, 200],
      requireInteraction: false,
      dir: 'rtl',
      lang: 'he',
      timestamp: Date.now()
    };
    
    // הוסף actions לפי סוג ההתראה
    if (data.type === 'group_invitation') {
      options.actions = [
        {
          action: 'accept',
          title: 'קבל הזמנה',
          icon: '/family/images/icons/android/android-launchericon-96-96.png'
        },
        {
          action: 'view',
          title: 'צפה',
          icon: '/family/images/icons/android/android-launchericon-96-96.png'
        }
      ];
      options.requireInteraction = true; // השאר את ההתראה עד שהמשתמש מגיב
    }
    
    await self.registration.showNotification(title, options);
    console.log('Notification shown:', title);
    
  } catch (error) {
    console.error('Error showing notification:', error);
  }
}

// טיפול בלחיצה על התראה
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event.notification.tag);
  
  event.notification.close();
  
  const data = event.notification.data;
  let url = data.url || '/family/dashboard.php';
  
  // טיפול ב-actions
  if (event.action === 'accept' && data.invitation_id) {
    url = `/family/dashboard.php?action=accept_invitation&id=${data.invitation_id}`;
  } else if (event.action === 'view') {
    url = data.url || '/family/dashboard.php#invitations';
  }
  
  // פתח או פוקס על החלון
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(windowClients => {
        // חפש חלון קיים
        for (let client of windowClients) {
          if (client.url.includes('/family/') && 'focus' in client) {
            return client.focus().then(() => {
              // שלח הודעה לחלון
              client.postMessage({
                type: 'notification-clicked',
                data: data
              });
            });
          }
        }
        // אם אין חלון פתוח, פתח חדש
        if (clients.openWindow) {
          return clients.openWindow(url);
        }
      })
  );
});

// טיפול בסגירת התראה
self.addEventListener('notificationclose', event => {
  console.log('Notification closed:', event.notification.tag);
});

// טיפול ב-Push Events (אם יש)
self.addEventListener('push', event => {
  console.log('Push event received');
  
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = {
        title: 'התראה חדשה',
        body: event.data.text()
      };
    }
  }
  
  event.waitUntil(showNotification(data));
});

// הקשבה להודעות מהאפליקציה
self.addEventListener('message', event => {
  console.log('Service Worker received message:', event.data);
  
  if (event.data.type === 'START_POLLING') {
    startPolling();
  } else if (event.data.type === 'STOP_POLLING') {
    stopPolling();
  } else if (event.data.type === 'CHECK_NOW') {
    checkForNotifications();
  }
});

// Fetch event עם cache strategy
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

// Background Sync (אם נתמך)
self.addEventListener('sync', event => {
  console.log('Background sync event:', event.tag);
  
  if (event.tag === 'check-notifications') {
    event.waitUntil(checkForNotifications());
  }
});

// Periodic Background Sync (אם נתמך)
self.addEventListener('periodicsync', event => {
  console.log('Periodic sync event:', event.tag);
  
  if (event.tag === 'check-notifications') {
    event.waitUntil(checkForNotifications());
  }
});

console.log('Service Worker script loaded with polling support');