// service-worker.js - Enhanced with Push Notifications
// לא עובד!!!
const CACHE_NAME = 'panan-bakan-v1.0.4';
const API_BASE = '/family';

// רשימת קבצים לקאש
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

// התקנת Service Worker
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

// הפעלת Service Worker
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

// טיפול בבקשות רשת
self.addEventListener('fetch', event => {
  // רק cache דפי HTML וקבצים סטטיים, לא API calls
  if (event.request.method === 'GET' && 
      !event.request.url.includes('/api/') && 
      !event.request.url.includes('action=')) {
    
    event.respondWith(
      fetch(event.request)
        .then(response => {
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
          return caches.match(event.request)
            .then(response => {
              if (response) {
                return response;
              }
              if (event.request.headers.get('accept').includes('text/html')) {
                return caches.match('/family/offline.html');
              }
            });
        })
    );
  }
});

// === PUSH NOTIFICATIONS ===

// האזנה לאירוע Push
self.addEventListener('push', event => {
  console.log('Push event received:', event);
  
  let notificationData = {
    title: 'התראה חדשה',
    body: 'יש לך התראה חדשה',
    icon: '/family/images/icons/android/android-launchericon-192-192.png',
    badge: '/family/images/icons/android/android-launchericon-96-96.png',
    vibrate: [200, 100, 200],
    data: {
      url: '/family/dashboard.php'
    }
  };
  
  // נסה לקרוא את הנתונים מהאירוע
  if (event.data) {
    try {
      const data = event.data.json();
      notificationData = {
        title: data.title || notificationData.title,
        body: data.body || notificationData.body,
        icon: data.icon || notificationData.icon,
        badge: data.badge || notificationData.badge,
        vibrate: data.vibrate || notificationData.vibrate,
        tag: data.tag || 'notification-' + Date.now(),
        requireInteraction: data.requireInteraction || false,
        data: data.data || notificationData.data,
        actions: data.actions || []
      };
    } catch (e) {
      console.error('Error parsing push data:', e);
    }
  }
  
  // הצג את ההתראה
  event.waitUntil(
    self.registration.showNotification(notificationData.title, {
      body: notificationData.body,
      icon: notificationData.icon,
      badge: notificationData.badge,
      vibrate: notificationData.vibrate,
      tag: notificationData.tag,
      requireInteraction: notificationData.requireInteraction,
      data: notificationData.data,
      actions: notificationData.actions,
      dir: 'rtl',
      lang: 'he'
    })
  );
});

// האזנה ללחיצה על התראה
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event);
  
  event.notification.close();
  
  // קבל את ה-URL מהנתונים
  const urlToOpen = event.notification.data?.url || '/family/dashboard.php';
  
  // פתח או פוקס על החלון
  event.waitUntil(
    clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    })
    .then(windowClients => {
      // חפש חלון פתוח עם האפליקציה
      for (let client of windowClients) {
        if (client.url.includes('/family/') && 'focus' in client) {
          return client.focus().then(client => {
            client.navigate(urlToOpen);
            return client;
          });
        }
      }
      // אם אין חלון פתוח, פתח חדש
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// פעולות על התראות (אם הוגדרו)
self.addEventListener('notificationclick', event => {
  if (event.action) {
    console.log('Notification action clicked:', event.action);
    
    switch (event.action) {
      case 'view':
        // פתח את הקבוצה/דף הרלוונטי
        clients.openWindow(event.notification.data.url);
        break;
      case 'dismiss':
        // סגור את ההתראה
        event.notification.close();
        break;
    }
  }
});

// בדיקה תקופתית להתראות (כל 5 דקות כשהאפליקציה סגורה)
self.addEventListener('periodicsync', event => {
  if (event.tag === 'check-notifications') {
    console.log('Periodic sync: Checking for notifications');
    event.waitUntil(checkForPendingNotifications());
  }
});

// פונקציה לבדיקת התראות ממתינות
async function checkForPendingNotifications() {
  try {
    // קבל את ה-subscription הנוכחי
    const subscription = await self.registration.pushManager.getSubscription();
    if (!subscription) {
      console.log('No push subscription found');
      return;
    }
    
    // בקש התראות ממתינות מהשרת
    const response = await fetch(`${API_BASE}/api/push-notifications.php?action=check-notifications&endpoint=${encodeURIComponent(subscription.endpoint)}`);
    
    if (response.ok) {
      const data = await response.json();
      const notifications = data.notifications || [];
      
      // הצג כל התראה ממתינה
      for (const notification of notifications) {
        await self.registration.showNotification(notification.title, {
          body: notification.body,
          icon: notification.icon,
          badge: notification.badge,
          vibrate: notification.vibrate,
          data: notification.data,
          tag: 'notification-' + Date.now(),
          dir: 'rtl',
          lang: 'he'
        });
      }
      
      console.log(`Displayed ${notifications.length} pending notifications`);
    }
  } catch (error) {
    console.error('Error checking for notifications:', error);
  }
}

// הפעלת בדיקה תקופתית כשה-Service Worker מתעורר
self.addEventListener('sync', event => {
  if (event.tag === 'check-notifications') {
    event.waitUntil(checkForPendingNotifications());
  }
});

// שליחת הודעות לקליינט
function sendMessageToClient(client, message) {
  return new Promise((resolve, reject) => {
    const channel = new MessageChannel();
    channel.port1.onmessage = event => {
      if (event.data.error) {
        reject(event.data.error);
      } else {
        resolve(event.data);
      }
    };
    client.postMessage(message, [channel.port2]);
  });
}

// עדכון badge על האייקון (אם נתמך)
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'update-badge') {
    const count = event.data.count || 0;
    if ('setAppBadge' in navigator) {
      navigator.setAppBadge(count);
    }
  }
});

console.log('Service Worker with Push Notifications loaded!');