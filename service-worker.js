// service-worker.js - Service Worker עם תמיכה בהתראות Push

const CACHE_NAME = 'panan-bakan-v1.0.0';
const urlsToCache = [
  'family/',
  'family/dashboard.php',
  '/auth/login.php',
  '/css/dashboard.css',
  '/css/group.css',
  '/css/styles.css',
  '/js/group.js',
  '/js/notifications.js',
  '/offline.html',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// התקנת Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting())
  );
});

// הפעלת Service Worker
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// אסטרטגיית Cache - Network First with Cache Fallback
self.addEventListener('fetch', event => {
  // דלג על בקשות שאינן GET
  if (event.request.method !== 'GET') return;

  // דלג על בקשות API
  if (event.request.url.includes('/api/') || 
      event.request.url.includes('.php') && 
      event.request.url.includes('action=')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // אם הרשת זמינה, שמור בקאש ותחזיר
        if (!response || response.status !== 200 || response.type !== 'basic') {
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
        // אם הרשת לא זמינה, נסה מהקאש
        return caches.match(event.request)
          .then(response => {
            if (response) {
              return response;
            }
            // אם אין בקאש, החזר דף offline
            return caches.match('/offline.html');
          });
      })
  );
});

// טיפול בהתראות Push
self.addEventListener('push', event => {
  console.log('Push notification received:', event);
  
  let notificationData = {
    title: 'פנאן בפקאן',
    body: 'יש לך התראה חדשה',
    icon: '/images/icons/icon-192x192.png',
    badge: '/images/icons/badge-72x72.png',
    vibrate: [200, 100, 200],
    tag: 'panan-notification',
    requireInteraction: false,
    silent: false,
    dir: 'rtl',
    lang: 'he',
    data: {}
  };

  // נסה לפרסר את הנתונים מהשרת
  if (event.data) {
    try {
      const data = event.data.json();
      notificationData = {
        ...notificationData,
        ...data,
        actions: []
      };

      // הוסף פעולות בהתאם לסוג ההתראה
      switch (data.type) {
        case 'group_invitation':
          notificationData.actions = [
            { action: 'accept', title: 'אשר', icon: '/images/icons/check.png' },
            { action: 'reject', title: 'דחה', icon: '/images/icons/x.png' }
          ];
          notificationData.tag = `invitation-${data.invitation_id}`;
          break;
          
        case 'invitation_response':
          notificationData.tag = `response-${data.group_id}`;
          break;
          
        case 'new_purchase':
          notificationData.actions = [
            { action: 'view', title: 'צפה', icon: '/images/icons/eye.png' }
          ];
          notificationData.tag = `purchase-${data.purchase_id}`;
          break;
      }
    } catch (e) {
      console.error('Error parsing push data:', e);
    }
  }

  // הצג את ההתראה
  event.waitUntil(
    self.registration.showNotification(notificationData.title, notificationData)
  );
});

// טיפול בלחיצה על התראה
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event);
  event.notification.close();

  let targetUrl = '/dashboard.php';
  const notificationData = event.notification.data;

  // טיפול בפעולות שונות
  if (event.action) {
    switch (event.action) {
      case 'accept':
        targetUrl = `/api/respond-invitation.php?id=${notificationData.invitation_id}&response=accept`;
        break;
      case 'reject':
        targetUrl = `/api/respond-invitation.php?id=${notificationData.invitation_id}&response=reject`;
        break;
      case 'view':
        targetUrl = `/group.php?id=${notificationData.group_id}#purchases`;
        break;
    }
  } else {
    // לחיצה רגילה על ההתראה
    switch (notificationData.type) {
      case 'group_invitation':
        targetUrl = '/dashboard.php#invitations';
        break;
      case 'invitation_response':
      case 'new_purchase':
        targetUrl = `/group.php?id=${notificationData.group_id}`;
        break;
    }
  }

  // פתח או מקד את החלון
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // חפש חלון קיים
        for (const client of clientList) {
          if (client.url === targetUrl && 'focus' in client) {
            return client.focus();
          }
        }
        // אם אין חלון קיים, פתח חדש
        if (clients.openWindow) {
          return clients.openWindow(targetUrl);
        }
      })
  );
});

// סנכרון ברקע
self.addEventListener('sync', event => {
  if (event.tag === 'sync-purchases') {
    event.waitUntil(syncPurchases());
  }
});

// פונקציה לסנכרון קניות
async function syncPurchases() {
  try {
    const cache = await caches.open(CACHE_NAME);
    const requests = await cache.keys();
    
    // סנכרן רק בקשות POST שנשמרו
    for (const request of requests) {
      if (request.method === 'POST' && request.url.includes('/api/')) {
        try {
          const response = await fetch(request);
          if (response.ok) {
            await cache.delete(request);
          }
        } catch (e) {
          console.error('Sync failed for:', request.url);
        }
      }
    }
  } catch (e) {
    console.error('Sync error:', e);
  }
}

// עדכון תקופתי
self.addEventListener('periodicsync', event => {
  if (event.tag === 'update-data') {
    event.waitUntil(updateData());
  }
});

async function updateData() {
  // עדכן נתונים חשובים מהשרת
  try {
    const response = await fetch('/api/sync-data.php');
    if (response.ok) {
      const data = await response.json();
      // שמור בקאש או שלח הודעה לקליינט
      const allClients = await clients.matchAll();
      allClients.forEach(client => {
        client.postMessage({
          type: 'data-updated',
          data: data
        });
      });
    }
  } catch (e) {
    console.error('Update failed:', e);
  }
}