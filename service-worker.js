/**
 * Service Worker for PWA - Enhanced Version
 * כולל את כל הפונקציונליות הקיימת + Background Sync
 * חייב להיות בשורש האתר!
 */

const CACHE_NAME = 'pwa-cache-v10'; // v10: הסרת login.php מהcache
const API_URL = '/api/notifications.php';

const urlsToCache = [
    '/',
    // '/auth/login.php', // v16: לא לאחסן login ב-cache - זה גורם לבעיות back button!
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

    // ===== Share Target Handler =====
    // טיפול בקבלת שיתופים מאפליקציות אחרות
    if (url.pathname === '/share-target/' && request.method === 'POST') {
        event.respondWith(handleShareTarget(request));
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
                const isApproval = notif.requires_approval || notif.isApproval || (notif.url && (notif.url.includes('/approve.php') || notif.url.includes('entity-approve.php')));
                await self.registration.showNotification(notif.title, {
                    body: notif.body,
                    icon: '/pwa/icons/android/android-launchericon-192-192.png',
                    badge: '/pwa/icons/android/android-launchericon-72-72.png',
                    tag: 'notification-' + notif.id,
                    data: {
                        url: notif.url || '/dashboard/',
                        id: notif.id,
                        isApproval: isApproval,
                        timestamp: Date.now()
                    },
                    requireInteraction: isApproval, // Approval notifications stay visible
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
    console.log('[ServiceWorker] ==================');
    console.log('[ServiceWorker] PUSH EVENT RECEIVED!');
    console.log('[ServiceWorker] Time:', new Date().toISOString());
    console.log('[ServiceWorker] Has data:', !!event.data);
    if (event.data) {
        try {
            console.log('[ServiceWorker] Data:', event.data.text());
        } catch(e) {
            console.log('[ServiceWorker] Could not read data');
        }
    }
    console.log('[ServiceWorker] ==================');

    let notificationData = {
        title: 'חברה קדישא',
        body: 'יש לך עדכון חדש!',
        icon: '/pwa/icons/android/android-launchericon-192-192.png',
        badge: '/pwa/icons/android/android-launchericon-72-72.png',
        vibrate: [200, 100, 200],
        requireInteraction: true, // Keep notification visible until user interacts
        renotify: true,
        tag: 'notification-' + Date.now(),
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        }
    };

    // נסה לפרש את הנתונים אם יש
    if (event.data) {
        try {
            const data = event.data.json();
            const url = data.url || '/dashboard/';
            // Detect approval by explicit flag OR URL pattern
            const isApprovalRequest = data.requiresApproval || data.requires_approval || data.isApproval || url.includes('/approve.php') || url.includes('entity-approve.php');

            notificationData = {
                ...notificationData,
                title: data.title || notificationData.title,
                body: data.body || notificationData.body,
                tag: 'notification-' + (data.id || Date.now()),
                // Approval requests stay visible until user interacts
                requireInteraction: isApprovalRequest || data.requireInteraction || false,
                data: {
                    ...notificationData.data,
                    url: url,
                    isApproval: isApprovalRequest,
                    id: data.id
                }
            };

            // Add action buttons for approval requests
            if (isApprovalRequest) {
                notificationData.actions = [
                    { action: 'open', title: 'פתח', icon: '/pwa/icons/action-open.png' }
                ];
            }
        } catch (e) {
            notificationData.body = event.data.text();
        }
    }

    console.log('[ServiceWorker] Showing notification:', notificationData.title);
    console.log('[ServiceWorker] Notification options:', JSON.stringify(notificationData));

    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
            .then(() => {
                console.log('[ServiceWorker] ✅ Notification shown successfully!');
            })
            .catch(err => {
                console.error('[ServiceWorker] ❌ Failed to show notification:', err);
            })
    );
});

// ============= טיפול בלחיצה על התראה - פותח מסך ייעודי =============
self.addEventListener('notificationclick', event => {
    console.log('[Service Worker] Notification click received.');

    event.notification.close();

    // קבל מידע מההתראה
    const notificationData = event.notification.data || {};
    const notificationId = notificationData.id || Date.now();
    const notificationTitle = event.notification.title || '';
    const notificationBody = event.notification.body || '';
    const notificationUrl = notificationData.url || '/dashboard/';
    const isApproval = notificationData.isApproval || notificationUrl.includes('/approve.php') || notificationUrl.includes('entity-approve.php');

    event.waitUntil(
        (async () => {
            // חפש חלון פתוח
            const windowClients = await clients.matchAll({
                type: 'window',
                includeUncontrolled: true
            });

            // בדוק אם יש חלון פתוח
            // Navigate to the dedicated notification page
            const notificationPageUrl = isApproval
                ? '/dashboard/dashboards/cemeteries/notifications/notification-action.php?id=' + notificationId
                : '/dashboard/dashboards/cemeteries/notifications/notification-info.php?id=' + notificationId;

            for (let client of windowClients) {
                if (client.url.includes(self.location.origin)) {
                    // אם המשתמש בדף login - שמור redirect לאחר התחברות
                    if (client.url.includes('/auth/login.php')) {
                        await client.focus();
                        client.postMessage({
                            type: 'REDIRECT_AFTER_LOGIN',
                            url: notificationPageUrl
                        });
                        return;
                    } else {
                        // המשתמש מחובר - הפנה ישירות לדף ההתראה
                        await client.focus();
                        client.postMessage({
                            type: 'NAVIGATE_TO_NOTIFICATION',
                            url: notificationPageUrl
                        });
                        console.log('[SW] Navigating to notification page:', notificationPageUrl);
                        return;
                    }
                }
            }

            // אם אין חלון פתוח - פתח את דף ההתראה
            if (clients.openWindow) {
                return clients.openWindow(notificationPageUrl);
            }
        })()
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

// ============= Share Target Handler =============
async function handleShareTarget(request) {
    console.log('[SW] 📥 Handling share target request');

    try {
        const formData = await request.formData();

        // חלץ את הנתונים המשותפים
        const title = formData.get('title') || '';
        const text = formData.get('text') || '';
        const url = formData.get('url') || '';
        const files = formData.getAll('files');

        console.log('[SW] Share data:', { title, text, url, filesCount: files.length });

        // אם יש קבצים, שמור אותם זמנית ב-Cache
        const sharedFiles = [];
        if (files && files.length > 0) {
            const cache = await caches.open('shared-files-cache');

            for (const file of files) {
                if (file && file.size > 0) {
                    const fileName = `shared_${Date.now()}_${file.name}`;
                    const fileUrl = `/shared-files/${fileName}`;

                    // שמור את הקובץ ב-cache
                    const response = new Response(file, {
                        headers: {
                            'Content-Type': file.type,
                            'Content-Length': file.size
                        }
                    });

                    await cache.put(fileUrl, response);

                    sharedFiles.push({
                        name: file.name,
                        path: fileUrl,
                        type: file.type,
                        size: file.size
                    });
                }
            }
        }

        // בנה URL עם הפרמטרים
        const params = new URLSearchParams();
        if (title) params.set('title', title);
        if (text) params.set('text', text);
        if (url) params.set('url', url);
        if (sharedFiles.length > 0) {
            params.set('cached_files', JSON.stringify(sharedFiles));
        }

        const redirectUrl = `/share-target/?${params.toString()}`;

        // הפנה לדף השיתוף
        return Response.redirect(redirectUrl, 303);

    } catch (error) {
        console.error('[SW] Share target error:', error);
        return Response.redirect('/share-target/?error=1', 303);
    }
}

// מענה על בקשות לקבצים משותפים מה-cache
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    if (url.pathname.startsWith('/shared-files/')) {
        event.respondWith(
            caches.open('shared-files-cache').then(cache => {
                return cache.match(event.request).then(response => {
                    if (response) {
                        // מחק מה-cache אחרי שימוש
                        cache.delete(event.request);
                        return response;
                    }
                    return new Response('File not found', { status: 404 });
                });
            })
        );
        return;
    }
});

console.log('[SW] ✨ Service Worker loaded with Background Sync & Share Target support');