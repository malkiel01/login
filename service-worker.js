// service-worker.js - גרסה מתוקנת עם תמיכה מלאה באופליין
const CACHE_NAME = 'panan-bakan-v1.0.4';
const OFFLINE_URL = '/family/offline.html';

// רשימת קבצים לקאש מראש
const urlsToCache = [
  '/family/',
  '/family/index.php',
  '/family/dashboard.php',
  '/family/auth/login.php',
  '/family/css/dashboard.css',
  '/family/css/group.css',
  '/family/css/styles.css',
  '/family/js/group.js',
  '/family/js/notification-prompt.js',
  '/family/manifest.json',
  OFFLINE_URL, // חשוב! דף האופליין חייב להיות בקאש
  // אייקונים
  '/family/images/icons/android/android-launchericon-192-192.png',
  '/family/images/icons/android/android-launchericon-512-512.png',
  '/family/images/icons/ios/180.png',
  '/family/images/icons/ios/152.png',
  '/family/images/icons/ios/120.png',
  // Font Awesome מ-CDN (אופציונלי)
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// התקנת Service Worker
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[ServiceWorker] Pre-caching offline page');
        // קודם תמיד שמור את דף האופליין
        return cache.add(OFFLINE_URL)
          .then(() => {
            console.log('[ServiceWorker] Offline page cached successfully');
            // אז נסה לשמור את שאר הקבצים
            return cache.addAll(urlsToCache.filter(url => url !== OFFLINE_URL))
              .catch(err => {
                console.warn('[ServiceWorker] Some files failed to cache:', err);
                // אם חלק מהקבצים נכשלו, המשך בכל זאת
                return Promise.resolve();
              });
          });
      })
      .then(() => {
        console.log('[ServiceWorker] Installation complete');
        return self.skipWaiting(); // הפעל מיד
      })
  );
});

// הפעלת Service Worker
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activating...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        // מחק קאש ישן
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('[ServiceWorker] Removing old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[ServiceWorker] Activation complete');
        return self.clients.claim(); // השתלט על כל הטאבים
      })
  );
});

// טיפול בבקשות
self.addEventListener('fetch', event => {
  const { request } = event;
  
  // דלג על בקשות שאינן GET
  if (request.method !== 'GET') {
    return;
  }
  
  // דלג על בקשות chrome-extension
  if (request.url.startsWith('chrome-extension://')) {
    return;
  }
  
  // בדוק אם זו בקשת ניווט (דף HTML)
  const isNavigationRequest = request.mode === 'navigate' ||
    (request.method === 'GET' && request.headers.get('accept').includes('text/html'));
  
  event.respondWith(
    fetch(request)
      .then(response => {
        // אם הצלחנו להביא מהרשת
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }
        
        // שמור בקאש עותק של התגובה
        const responseToCache = response.clone();
        caches.open(CACHE_NAME)
          .then(cache => {
            // אל תשמור בקאש בקשות API או פעולות
            if (!request.url.includes('/api/') && 
                !request.url.includes('action=') &&
                !request.url.includes('.php?')) {
              cache.put(request, responseToCache);
            }
          })
          .catch(err => {
            console.warn('[ServiceWorker] Failed to cache:', err);
          });
        
        return response;
      })
      .catch(error => {
        // אם נכשלנו להביא מהרשת (אין אינטרנט)
        console.log('[ServiceWorker] Fetch failed, trying cache:', error);
        
        // אם זו בקשת ניווט (דף HTML), החזר את דף האופליין
        if (isNavigationRequest) {
          return caches.match(OFFLINE_URL)
            .then(response => {
              if (response) {
                console.log('[ServiceWorker] Returning offline page');
                return response;
              }
              // אם גם דף האופליין לא נמצא, צור תגובה בסיסית
              return new Response(
                `<!DOCTYPE html>
                <html dir="rtl" lang="he">
                <head>
                  <meta charset="UTF-8">
                  <meta name="viewport" content="width=device-width, initial-scale=1.0">
                  <title>אופליין</title>
                  <style>
                    body {
                      font-family: Arial, sans-serif;
                      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                      min-height: 100vh;
                      display: flex;
                      align-items: center;
                      justify-content: center;
                      margin: 0;
                      padding: 20px;
                    }
                    .container {
                      background: white;
                      padding: 40px;
                      border-radius: 20px;
                      text-align: center;
                      max-width: 400px;
                      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    }
                    h1 { color: #333; }
                    p { color: #666; line-height: 1.6; }
                    button {
                      background: #667eea;
                      color: white;
                      border: none;
                      padding: 12px 30px;
                      border-radius: 8px;
                      font-size: 16px;
                      cursor: pointer;
                      margin-top: 20px;
                    }
                  </style>
                </head>
                <body>
                  <div class="container">
                    <h1>📡 אין חיבור לאינטרנט</h1>
                    <p>לא ניתן לטעון את הדף המבוקש.<br>אנא בדוק את החיבור לרשת.</p>
                    <button onclick="location.reload()">נסה שוב</button>
                  </div>
                </body>
                </html>`,
                { 
                  status: 503,
                  statusText: 'Service Unavailable',
                  headers: new Headers({ 'Content-Type': 'text/html; charset=utf-8' })
                }
              );
            });
        }
        
        // לבקשות אחרות (CSS, JS, תמונות), נסה למצוא בקאש
        return caches.match(request)
          .then(response => {
            if (response) {
              console.log('[ServiceWorker] Found in cache:', request.url);
              return response;
            }
            
            // אם לא נמצא בקאש, החזר תגובת שגיאה מתאימה
            console.log('[ServiceWorker] Not found in cache:', request.url);
            
            // לקבצי CSS/JS החזר קובץ ריק
            if (request.url.endsWith('.css')) {
              return new Response('/* Offline CSS */', {
                headers: { 'Content-Type': 'text/css' }
              });
            }
            if (request.url.endsWith('.js')) {
              return new Response('// Offline JS', {
                headers: { 'Content-Type': 'application/javascript' }
              });
            }
            
            // לתמונות החזר placeholder
            if (request.headers.get('accept').includes('image')) {
              // SVG placeholder
              const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
                <rect width="100" height="100" fill="#f0f0f0"/>
                <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999">📷</text>
              </svg>`;
              return new Response(svg, {
                headers: { 'Content-Type': 'image/svg+xml' }
              });
            }
            
            // ברירת מחדל
            return new Response('Offline', { status: 503 });
          });
      })
  );
});

// האזנה להודעות
self.addEventListener('message', event => {
  console.log('[ServiceWorker] Message received:', event.data);
  
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
  
  if (event.data.action === 'clearCache') {
    caches.keys().then(cacheNames => {
      cacheNames.forEach(cacheName => {
        caches.delete(cacheName);
      });
    });
  }
});

// טיפול בעדכונים
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// הוספת listener לבדיקת קישוריות
self.addEventListener('sync', event => {
  if (event.tag === 'check-connection') {
    event.waitUntil(
      fetch('/family/api/ping.php')
        .then(() => {
          // אם יש חיבור, שלח הודעה לכל הלקוחות
          self.clients.matchAll().then(clients => {
            clients.forEach(client => {
              client.postMessage({
                type: 'connection-restored',
                message: 'החיבור לאינטרנט חזר!'
              });
            });
          });
        })
        .catch(() => {
          console.log('[ServiceWorker] Still offline');
        })
    );
  }
});

// דיבוג
console.log('[ServiceWorker] Script loaded, version:', CACHE_NAME);