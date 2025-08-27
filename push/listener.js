/**
 * Push Notification Listener - FULLY FIXED VERSION
 * בודק התראות מיד בטעינה + כל 30 שניות
 */

(function() {
    'use strict';
    
    // משתנים גלובליים
    let checkInterval = null;
    let isChecking = false;
    let deliveredNotifications = new Set();
    let lastCheckTime = 0;
    
    // בדיקת התראות חדשות
    async function checkForNotifications(isInitialCheck = false) {
        // מנע בדיקות כפולות
        if (isChecking) {
            console.log('⏳ בדיקה כבר בתהליך...');
            return;
        }
        
        // בבדיקה ראשונה - תמיד בדוק
        // אחרת בדוק רק אם האפליקציה פעילה
        if (!isInitialCheck && (document.hidden || !navigator.onLine)) {
            console.log('⏸️ דחיית בדיקה - אפליקציה ברקע או אופליין');
            return;
        }
        
        isChecking = true;
        console.log(isInitialCheck ? '🚀 בדיקה ראשונית של התראות...' : '🔄 בדיקת התראות...');
        
        try {
            const response = await fetch('/api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'check_undelivered'
                })
            });
            
            if (!response.ok) {
                throw new Error(`Network error: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.notifications && data.notifications.length > 0) {
                console.log(`📨 נמצאו ${data.notifications.length} התראות חדשות!`);
                
                // טיפול בכל התראה
                for (const notif of data.notifications) {
                    // בדוק אם כבר טיפלנו בהתראה
                    if (deliveredNotifications.has(notif.id)) {
                        console.log(`⏭️ התראה ${notif.id} כבר נמסרה - מדלג`);
                        continue;
                    }
                    
                    console.log(`📬 מעבד התראה ${notif.id}: ${notif.title}`);
                    
                    // סמן שטיפלנו בהתראה
                    deliveredNotifications.add(notif.id);
                    
                    // שמור ב-localStorage
                    saveNotificationLocally(notif);
                    
                    // הצג התראה (רק אם יש הרשאות)
                    if (Notification.permission === 'granted') {
                        await showPushNotification(notif);
                    } else {
                        console.log('⚠️ אין הרשאות להתראות - שומר רק ב-localStorage');
                    }
                    
                    // עדכן UI
                    updateNotificationWidget();
                }
                
                // עדכן זמן בדיקה אחרון
                lastCheckTime = Date.now();
                
            } else {
                console.log('✅ אין התראות חדשות');
            }
            
        } catch (error) {
            console.error('❌ שגיאה בבדיקת התראות:', error);
        } finally {
            isChecking = false;
        }
    }
    
    // שמירת התראה ב-localStorage
    function saveNotificationLocally(notif) {
        try {
            let notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
            
            // בדוק אם כבר קיימת
            const exists = notifications.find(n => 
                n.id === notif.id.toString() || 
                (n.title === notif.title && n.body === notif.body)
            );
            
            if (exists) {
                console.log(`📝 התראה ${notif.id} כבר קיימת ב-localStorage`);
                return;
            }
            
            // הוסף התראה חדשה
            const newNotif = {
                id: notif.id.toString(),
                title: notif.title,
                body: notif.body,
                url: notif.url || null,
                timestamp: new Date(notif.created_at).getTime(),
                read: false,
                fromServer: true
            };
            
            notifications.unshift(newNotif);
            console.log(`💾 שומר התראה ${notif.id} ב-localStorage`);
            
            // הגבל ל-50 התראות
            if (notifications.length > 50) {
                notifications = notifications.slice(0, 50);
            }
            
            localStorage.setItem('notifications', JSON.stringify(notifications));
        } catch (error) {
            console.error('❌ שגיאה בשמירת התראה:', error);
        }
    }
    
    // הצגת התראה
    async function showPushNotification(notif) {
        try {
            // בדוק שוב הרשאות
            if (Notification.permission !== 'granted') {
                console.log('❌ אין הרשאות להצגת התראה');
                return false;
            }
            
            console.log(`🔔 מציג התראה: ${notif.title}`);
            
            // העדף Service Worker אם זמין
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.ready;
                
                await registration.showNotification(notif.title, {
                    body: notif.body,
                    icon: '/pwa/icons/android/android-launchericon-192-192.png',
                    badge: '/pwa/icons/android/android-launchericon-72-72.png',
                    tag: 'notification-' + notif.id,
                    data: { 
                        url: notif.url,
                        id: notif.id,
                        timestamp: Date.now()
                    },
                    requireInteraction: false,
                    silent: false
                });
                
                console.log(`✅ התראה ${notif.id} הוצגה בהצלחה`);
                return true;
            } 
            // Fallback לAPI הרגיל
            else if ('Notification' in window) {
                const notification = new Notification(notif.title, {
                    body: notif.body,
                    icon: '/pwa/icons/android/android-launchericon-192-192.png',
                    tag: 'notification-' + notif.id
                });
                
                // סגור אחרי 10 שניות
                setTimeout(() => notification.close(), 10000);
                
                // טיפול בלחיצה
                notification.onclick = function() {
                    if (notif.url) {
                        window.open(notif.url, '_blank');
                    }
                    notification.close();
                };
                
                console.log(`✅ התראה ${notif.id} הוצגה (Notification API)`);
                return true;
            }
            
        } catch (error) {
            console.error(`❌ שגיאה בהצגת התראה ${notif.id}:`, error);
            return false;
        }
    }
    
    // עדכון Widget התראות
    function updateNotificationWidget() {
        try {
            // עדכון Badge
            const badge = document.getElementById('notifBadge');
            if (badge) {
                const notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
                const unreadCount = notifications.filter(n => !n.read).length;
                
                if (unreadCount > 0) {
                    badge.style.display = 'flex';
                    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                } else {
                    badge.style.display = 'none';
                }
                
                console.log(`🏷️ Badge עודכן: ${unreadCount} התראות שלא נקראו`);
            }
            
            // רענן רשימת התראות אם הפונקציה קיימת
            if (typeof window.loadNotifications === 'function') {
                window.loadNotifications();
                console.log('📋 רשימת התראות עודכנה');
            }
        } catch (error) {
            console.error('❌ שגיאה בעדכון Widget:', error);
        }
    }
    
    // התחלת מאזין
    function startListener() {
        console.log('🎬 מפעיל Push Listener...');
        
        // וודא שאין כבר מאזין פעיל
        if (checkInterval) {
            console.log('⚠️ המאזין כבר פעיל');
            return;
        }
        
        // בדיקה ראשונית מיידית - חשוב!
        console.log('🔍 מבצע בדיקה ראשונית של התראות שלא נמסרו...');
        checkForNotifications(true);
        
        // הפעל בדיקות תקופתיות כל 30 שניות
        checkInterval = setInterval(() => {
            checkForNotifications(false);
        }, 3000);
        
        console.log('✅ Push Listener פעיל - בודק כל 30 שניות');
    }
    
    // עצירת מאזין
    function stopListener() {
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
            console.log('🛑 Push Listener נעצר');
        }
    }
    
    // טיפול בחזרה לאפליקציה
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            console.log('😴 האפליקציה עברה לרקע');
        } else {
            console.log('👋 האפליקציה חזרה לחזית - בודק התראות...');
            // בדיקה מיידית בחזרה לאפליקציה
            setTimeout(() => checkForNotifications(true), 500);
        }
    });
    
    // טיפול בחזרה לאונליין
    window.addEventListener('online', () => {
        console.log('🌐 חזרנו לאונליין - בודק התראות...');
        setTimeout(() => checkForNotifications(true), 1000);
    });
    
    window.addEventListener('offline', () => {
        console.log('📵 עברנו לאופליין');
    });
    
    // טיפול בפוקוס על החלון
    window.addEventListener('focus', () => {
        console.log('🎯 החלון קיבל פוקוס - בודק התראות...');
        // בדוק רק אם עברו לפחות 5 שניות מהבדיקה האחרונה
        if (Date.now() - lastCheckTime > 5000) {
            checkForNotifications(true);
        }
    });
    
    // חשוף פונקציות גלובליות
    window.PushListener = {
        start: startListener,
        stop: stopListener,
        check: () => checkForNotifications(true),
        isRunning: () => checkInterval !== null,
        clearDelivered: () => {
            deliveredNotifications.clear();
            console.log('🗑️ רשימת התראות שנמסרו נוקתה');
        }
    };
    
    // התחלה אוטומטית כשהדף נטען
    window.addEventListener('DOMContentLoaded', () => {
        console.log('📄 הדף נטען - בודק אם המשתמש מחובר...');
        
        // בדוק אם המשתמש מחובר
        const isLoggedIn = document.cookie.includes('PHPSESSID') || 
                          localStorage.getItem('user_id') ||
                          document.querySelector('[data-user-id]');
        
        if (isLoggedIn) {
            console.log('👤 משתמש מחובר - מפעיל מאזין');
            setTimeout(() => startListener(), 1000);
        } else {
            console.log('👻 משתמש לא מחובר - ממתין...');
        }
    });
    
    // אם הסקריפט נטען אחרי DOMContentLoaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        const isLoggedIn = document.cookie.includes('PHPSESSID') || 
                          localStorage.getItem('user_id');
        if (isLoggedIn && !window.PushListener.isRunning()) {
            console.log('🚀 הפעלה מאוחרת של המאזין');
            startListener();
        }
    }
    
})();