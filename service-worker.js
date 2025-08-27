/**
 * Service Worker for PWA - Enhanced Version
 * כולל את כל הפונקציונליות הקיימת + Background Sync
 * חייב להיות בשורש האתר!
 */

const CACHE_NAME = 'pwa-cache-v1';
const API_URL = '/api/notifications.php';

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
    
    // Images
    '/pwa/icons/android/android-launchericon-192-192.png',
    '/pwa/icons/android/android-launchericon-512-512.png',
    '/pwa/icons/ios/152.png',
    '/pwa/icons/ios/180.png'
];

// ============= התקנת Service Worker (הקוד המקורי שלך) =============
self.addEventListener('install', event => {
    console.log('[ServiceWorker] Install');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[ServiceWorker] Caching app shell');
                return Promise.allSettled(
                    urlsToCache.map(url => 
                        cache.add(url).catch(err => 
                            console.warn(`Failed to cache ${url}:`, err)
                        )
                    )
                );
            })
    );
    
    self.skipWaiting();
});

// ============= הפעלת Service Worker (משופר) =============
self.addEventListener('activate', async event => {
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
    
    // ===== תוספת חדשה: רישום לסנכרון תקופתי =====
    if ('periodicSync' in self.registration) {
        try {
            await self.registration.periodicSync.register('check-notifications', {
                minInterval: 30 * 60 * 1000 // 30 דקות
            });
            console.log('[ServiceWorker] ✅ Periodic sync registered');
        } catch (error) {
            console.log('[ServiceWorker] ⚠️ Periodic sync failed:', error);
        }
    }
    
    return self.clients.claim();
});

// ============= טיפול בבקשות (הקוד המקורי שלך) =============
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // דלג על בקשות API וקבצי PHP
    if (url.pathname.includes('/api/') || 
        url.pathname.includes('.php') ||
        url.pathname.includes('/auth/google-auth.php')) {
        event.respondWith(
            fetch(request).catch(() => {
                if (request.mode === 'navigate') {
                    return caches.match('/offline.html');
                }
            })
        );
        return;
    }
    
    // Network First with Cache Fallback
    event.respondWith(
        fetch(request)
            .then(response => {
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                
                const responseToCache = response.clone();
                
                caches.open(CACHE_NAME)
                    .then(cache => {
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
                return caches.match(request)
                    .then(response => {
                        if (response) {
                            return response;
                        }
                        
                        if (request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                        
                        if (request.destination === 'image') {
                            return caches.match('/images/placeholder.png');
                        }
                    });
            })
    );
});

// ============= תוספות חדשות: BACKGROUND SYNC =============

// פונקציה לבדיקת והצגת התראות ברקע
async function checkAndShowNotifications() {
    console.log('[SW] 📡 Checking for notifications in background...');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'check_undelivered'
            }),
            credentials: 'include' // חשוב! שולח cookies
        });
        
        if (!response.ok) throw new Error('Network error');
        
        const data = await response.json();
        
        if (data.success && data.notifications && data.notifications.length > 0) {
            console.log(`[SW] 📨 Found ${data.notifications.length} notifications`);
            
            // הצג כל התראה
            for (const notif of data.notifications) {
                await self.registration.showNotification(notif.title, {
                    body: notif.body,
                    icon: '/pwa/icons/android/android-launchericon-192-192.png',
                    badge: '/pwa/icons/android/android-launchericon-72-72.png',
                    tag: 'notification-' + notif.id,
                    data: { 
                        url: notif.url || '/notifications/manager.php',
                        id: notif.id,
                        timestamp: Date.now()
                    },
                    requireInteraction: false,
                    vibrate: [200, 100, 200],
                    renotify: true
                });
            }
            
            // עדכן badge אם אפשר
            if (self.registration.setAppBadge) {
                self.registration.setAppBadge(data.notifications.length);
            }
            
            return true;
        }
        
        console.log('[SW] ✅ No new notifications');
        return true;
        
    } catch (error) {
        console.error('[SW] ❌ Error checking notifications:', error);
        throw error;
    }
}

// Background Sync - מופעל כשחוזרים לאונליין
self.addEventListener('sync', event => {
    console.log('[SW] 🔄 Background sync triggered:', event.tag);
    
    if (event.tag === 'check-notifications') {
        event.waitUntil(checkAndShowNotifications());
    }
});

// Periodic Background Sync - בדיקה תקופתית
self.addEventListener('periodicsync', event => {
    console.log('[SW] ⏰ Periodic sync triggered:', event.tag);
    
    if (event.tag === 'check-notifications') {
        event.waitUntil(checkAndShowNotifications());
    }
});

// ============= Push Notifications (משופר) =============
self.addEventListener('push', event => {
    console.log('[ServiceWorker] Push Received');
    
    let notificationData = {
        title: 'קניות משפחתיות',
        body: 'יש לך עדכון חדש!',
        icon: '/pwa/icons/android/android-launchericon-192-192.png',
        badge: '/pwa/icons/android/android-launchericon-72-72.png',
        vibrate: [200, 100, 200],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        }
    };
    
    // נסה לפרש את הנתונים אם יש
    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = {
                ...notificationData,
                title: data.title || notificationData.title,
                body: data.body || notificationData.body,
                data: { 
                    ...notificationData.data,
                    url: data.url || '/notifications/manager.php'
                }
            };
        } catch (e) {
            notificationData.body = event.data.text();
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
    );
});

// ============= טיפול בלחיצה על התראה (הקוד המקורי שלך) =============
self.addEventListener('notificationclick', event => {
    console.log('[Service Worker] Notification click received.');
    
    event.notification.close();
    
    // קבל URL מה-data או השתמש בברירת מחדל
    const finalTarget = event.notification.data?.url || '/notifications/manager.php';
    
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(windowClients => {
            // בדוק אם יש חלון פתוח
            for (let client of windowClients) {
                if (client.url.includes(self.location.origin)) {
                    // אם המשתמש בדף login
                    if (client.url.includes('/auth/login.php')) {
                        return client.focus().then(() => {
                            client.postMessage({
                                type: 'REDIRECT_AFTER_LOGIN',
                                url: finalTarget
                            });
                        });
                    } else {
                        // המשתמש מחובר - נווט לדף ההתראות
                        return client.navigate(finalTarget).then(client => client.focus());
                    }
                }
            }
            
            // אם אין חלון פתוח
            if (clients.openWindow) {
                return clients.openWindow('/?redirect_to=' + encodeURIComponent(finalTarget));
            }
        })
    );
});

// ============= טיפול בהודעות (משופר) =============
self.addEventListener('message', event => {
    // דילוג על עדכון
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    // עדכון קאש
    if (event.data && event.data.type === 'UPDATE_CACHE') {
        caches.open(CACHE_NAME).then(cache => {
            cache.addAll(urlsToCache);
        });
    }
    
    // ===== תוספת חדשה: בדיקה ידנית של התראות =====
    if (event.data && event.data.type === 'CHECK_NOTIFICATIONS') {
        event.waitUntil(checkAndShowNotifications());
    }
    
    // ===== תוספת חדשה: רישום לסנכרון =====
    if (event.data && event.data.type === 'REGISTER_SYNC') {
        event.waitUntil(
            self.registration.sync.register('check-notifications')
        );
    }
    
    // שמירת התראה
    if (event.data && event.data.type === 'SAVE_NOTIFICATION') {
        clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'NEW_NOTIFICATION',
                    notification: event.data.notification
                });
            });
        });
    }
    
    // עדכון badge
    if (event.data && event.data.type === 'NOTIFICATION_UPDATE') {
        if (self.registration.setAppBadge) {
            self.registration.setAppBadge(event.data.count);
        }
    }
});

// זיהוי אוטומטי של PWA - הוסף ל-Service Worker:
self.addEventListener('fetch', event => {
    const request = event.request.clone();
    
    // הוסף header מזהה ל-PWA
    if (request.url.includes('/auth/') || request.url.includes('/dashboard/')) {
        const modifiedHeaders = new Headers(request.headers);
        modifiedHeaders.set('X-Requested-With', 'PWA');
        
        const modifiedRequest = new Request(request, {
            headers: modifiedHeaders
        });
        
        event.respondWith(fetch(modifiedRequest));
    }
});

console.log('[SW] ✨ Service Worker loaded with Background Sync support');