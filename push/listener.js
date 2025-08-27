/**
 * Push Notification Listener - Fixed Version
 * מאזין להתראות חדשות כל 30 שניות
 */

(function() {
    'use strict';
    
    // משתנים גלובליים
    let checkInterval = null;
    let isChecking = false;
    let deliveredNotifications = new Set(); // שמור IDs של התראות שכבר נמסרו
    
    // בדיקת התראות חדשות
    async function checkForNotifications() {
        // מנע בדיקות כפולות
        if (isChecking || document.hidden || !navigator.onLine) {
            return;
        }
        
        isChecking = true;
        
        try {
            const response = await fetch('/api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'check_undelivered' // שינוי ה-action
                })
            });
            
            if (!response.ok) throw new Error('Network error');
            
            const data = await response.json();
            
            if (data.success && data.notifications && data.notifications.length > 0) {
                console.log(`📨 קיבלת ${data.notifications.length} התראות חדשות`);
                
                // עבור על כל התראה
                for (const notif of data.notifications) {
                    // בדוק אם כבר טיפלנו בהתראה הזו
                    if (deliveredNotifications.has(notif.id)) {
                        console.log(`התראה ${notif.id} כבר נמסרה - מדלג`);
                        continue;
                    }
                    
                    // סמן שטיפלנו בהתראה
                    deliveredNotifications.add(notif.id);
                    
                    // שמור ב-localStorage
                    saveNotificationLocally(notif);
                    
                    // הצג התראה
                    await showPushNotification(notif);
                    
                    // עדכן UI אם יש widget
                    updateNotificationWidget();
                }
            }
            
        } catch (error) {
            console.error('Error checking notifications:', error);
        } finally {
            isChecking = false;
        }
    }
    
    // שמירת התראה ב-localStorage
    function saveNotificationLocally(notif) {
        let notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
        
        // בדוק אם כבר קיימת
        const exists = notifications.find(n => n.id === notif.id.toString());
        if (exists) return;
        
        // הוסף התראה חדשה
        notifications.unshift({
            id: notif.id.toString(),
            title: notif.title,
            body: notif.body,
            url: notif.url,
            timestamp: new Date(notif.created_at).getTime(),
            read: notif.is_read === '1' || notif.is_read === true,
            fromServer: true
        });
        
        // הגבל ל-50 התראות
        if (notifications.length > 50) {
            notifications = notifications.slice(0, 50);
        }
        
        localStorage.setItem('notifications', JSON.stringify(notifications));
    }
    
    // הצגת התראה
    async function showPushNotification(notif) {
        // בדוק הרשאות
        if (Notification.permission !== 'granted') {
            console.log('אין הרשאות להתראות');
            return;
        }
        
        // אם יש Permissions.showNotification - השתמש בה
        if (window.Permissions && typeof window.Permissions.showNotification === 'function') {
            // אל תשמור שוב ב-localStorage כי הפונקציה כבר עושה את זה
            const originalShowNotification = window.Permissions.showNotification;
            
            // עוטף זמני שמונע שמירה כפולה
            window.Permissions.showNotification = async function(title, options) {
                // הצג רק את ההתראה בלי לשמור
                if ('serviceWorker' in navigator) {
                    const registration = await navigator.serviceWorker.getRegistration();
                    if (registration) {
                        await registration.showNotification(title, {
                            body: options.body || '',
                            icon: '/pwa/icons/android/android-launchericon-192-192.png',
                            badge: '/pwa/icons/android/android-launchericon-72-72.png',
                            tag: 'push-' + Date.now(),
                            data: { url: options.url }
                        });
                    }
                }
                return true;
            };
            
            await window.Permissions.showNotification(notif.title, {
                body: notif.body,
                url: notif.url
            });
            
            // החזר את הפונקציה המקורית
            window.Permissions.showNotification = originalShowNotification;
        } 
        // אחרת הצג ישירות
        else if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;
            await registration.showNotification(notif.title, {
                body: notif.body,
                icon: '/pwa/icons/android/android-launchericon-192-192.png',
                badge: '/pwa/icons/android/android-launchericon-72-72.png',
                data: { url: notif.url, id: notif.id },
                tag: 'notification-' + notif.id
            });
        } 
        // Fallback - התראה רגילה
        else {
            new Notification(notif.title, {
                body: notif.body,
                icon: '/pwa/icons/android/android-launchericon-192-192.png'
            });
        }
    }
    
    // עדכון Widget התראות (אם קיים)
    function updateNotificationWidget() {
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
        }
        
        // רענן רשימת התראות אם הפונקציה קיימת
        if (typeof window.loadNotifications === 'function') {
            window.loadNotifications();
        }
    }
    
    // התחלת מאזין
    function startListener() {
        // בדיקה ראשונה מיד
        checkForNotifications();
        
        // בדיקה כל 30 שניות
        checkInterval = setInterval(checkForNotifications, 30000);
        
        console.log('🎧 Push Listener started - checking every 30 seconds');
    }
    
    // עצירת מאזין
    function stopListener() {
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
            console.log('🛑 Push Listener stopped');
        }
    }
    
    // האזנה לשינויי מצב הדף
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            console.log('📱 App in background - pausing checks');
        } else {
            console.log('📱 App in foreground - resuming checks');
            checkForNotifications();
        }
    });
    
    // האזנה למצב אינטרנט
    window.addEventListener('online', () => {
        console.log('🌐 Back online - checking notifications');
        checkForNotifications();
    });
    
    window.addEventListener('offline', () => {
        console.log('📵 Offline - pausing checks');
    });
    
    // חשוף פונקציות גלובליות
    window.PushListener = {
        start: startListener,
        stop: stopListener,
        check: checkForNotifications,
        isRunning: () => checkInterval !== null
    };
    
    // התחל אוטומטית אם המשתמש מחובר
    if (document.cookie.includes('PHPSESSID') || localStorage.getItem('user_id')) {
        startListener();
    }
    
})();