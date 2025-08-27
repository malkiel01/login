/**
 * Service Worker for PWA
 * חייב להיות בשורש האתר!
 */

const CACHE_NAME = 'pwa-cache-v1';
const urlsToCache = [
    '/',
    '/auth/login.php',
    '/dashboard/index.php',
    '/offline.html',
    '/manifest.json',
    
    // PWA specific files
    '/pwa/js/pwa-install-manager.js',
    '/pwa/css/pwa-custom.css',
    
    // CSS files
    '/auth/css/styles.css',
    '/auth/css/style2.css',
    '/dashboard/assets/css/dashboard.css',
    
    // JS files
    '/dashboard/assets/js/dashboard.js',
    
    // Images - עדכון לנתיבי האייקונים החדשים
    '/pwa/icons/android/android-launchericon-192-192.png',
    '/pwa/icons/android/android-launchericon-512-512.png',
    '/pwa/icons/ios/152.png',
    '/pwa/icons/ios/180.png'
];

// התקנת Service Worker
self.addEventListener('install', event => {
    console.log('[ServiceWorker] Install');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[ServiceWorker] Caching app shell');
                // נסה לשמור בקאש, אבל אל תיכשל אם חלק מהקבצים לא קיימים
                return Promise.allSettled(
                    urlsToCache.map(url => 
                        cache.add(url).catch(err => 
                            console.warn(`Failed to cache ${url}:`, err)
                        )
                    )
                );
            })
    );
    
    // הפעל מיד את ה-Service Worker החדש
    self.skipWaiting();
});

// הפעלת Service Worker
self.addEventListener('activate', event => {
    console.log('[ServiceWorker] Activate');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[ServiceWorker] Removing old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    
    // השתלט על כל הלקוחות מיד
    return self.clients.claim();
});

// טיפול בבקשות
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // דלג על בקשות שאינן HTTP/HTTPS
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // דלג על בקשות API וקבצי PHP (תמיד טריים)
    if (url.pathname.includes('/api/') || 
        url.pathname.includes('.php') ||
        url.pathname.includes('/auth/google-auth.php')) {
        event.respondWith(
            fetch(request).catch(() => {
                // אם זה דף PHP ואין רשת, הצג דף אופליין
                if (request.mode === 'navigate') {
                    return caches.match('/offline.html');
                }
            })
        );
        return;
    }
    
    // אסטרטגיית Network First with Cache Fallback
    event.respondWith(
        fetch(request)
            .then(response => {
                // בדוק שהתגובה תקינה
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                
                // שמור בקאש
                const responseToCache = response.clone();
                
                caches.open(CACHE_NAME)
                    .then(cache => {
                        // שמור רק קבצים סטטיים
                        if (request.method === 'GET' && 
                            (url.pathname.includes('.js') || 
                             url.pathname.includes('.css') || 
                             url.pathname.includes('.png') || 
                             url.pathname.includes('.jpg') || 
                             url.pathname.includes('.jpeg') || 
                             url.pathname.includes('.svg') || 
                             url.pathname.includes('.ico'))) {
                            cache.put(request, responseToCache);
                        }
                    });
                
                return response;
            })
            .catch(() => {
                // אם הרשת נכשלה, נסה מהקאש
                return caches.match(request)
                    .then(response => {
                        if (response) {
                            return response;
                        }
                        
                        // אם זה ניווט ואין בקאש, הצג דף אופליין
                        if (request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                        
                        // החזר תמונת placeholder לתמונות חסרות
                        if (request.destination === 'image') {
                            return caches.match('/images/placeholder.png');
                        }
                    });
            })
    );
});

// טיפול בהודעות Push (אופציונלי)
self.addEventListener('push', event => {
    console.log('[ServiceWorker] Push Received');
    
    const title = 'קניות משפחתיות';
    const options = {
        body: event.data ? event.data.text() : 'יש לך עדכון חדש!',
        icon: '/images/icon-192x192.png',
        badge: '/images/badge-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        }
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// טיפול בלחיצה על התראה
self.addEventListener('notificationclick', event => {
    console.log('[ServiceWorker] Notification click');
    
    event.notification.close();
    
    event.waitUntil(
        clients.openWindow('/dashboard/index.php')
    );
});

// עדכון אוטומטי כל 24 שעות
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'UPDATE_CACHE') {
        caches.open(CACHE_NAME).then(cache => {
            cache.addAll(urlsToCache);
        });
    }
});

// הוסף את הקוד הזה ל-service-worker.js שלך

// טיפול בלחיצה על התראה
self.addEventListener('notificationclick', event => {
    console.log('[Service Worker] Notification click received.');
    
    event.notification.close(); // סגור את ההתראה
    
    // אם יש URL ספציפי בהתראה
    const notificationData = event.notification.data || {};
    // const targetUrl = notificationData.url || '/permissions/manager.php';
    const targetUrl = notificationData.url || '/notifications/manager.php';
    
    // פתח או מקד את החלון
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(clientList => {
            // בדוק אם יש חלון פתוח
            for (let client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            // אם לא, פתח חלון חדש
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// שמירת התראה ב-localStorage (דרך הדף)
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SAVE_NOTIFICATION') {
        // שלח הודעה לכל החלונות לעדכן את רשימת ההתראות
        clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'NEW_NOTIFICATION',
                    notification: event.data.notification
                });
            });
        });
    }
});