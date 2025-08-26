<?php
/**
 * Permissions Initialization
 * מערכת הרשאות פשוטה ל-Push Notifications
 * 
 * שימוש:
 * require_once 'permissions/init.php';
 * echo getPermissionsScript();
 */

/**
 * מחזיר את הסקריפט לניהול הרשאות
 */
function getPermissionsScript() {
    return '
    <script>
    // מערכת הרשאות פשוטה
    window.PermissionsManager = {
        
        // בקשת הרשאה ל-Notifications
        async requestNotificationPermission() {
            try {
                // בדוק אם הדפדפן תומך
                if (!("Notification" in window)) {
                    console.log("הדפדפן לא תומך בהתראות");
                    return false;
                }
                
                // בדוק סטטוס נוכחי
                if (Notification.permission === "granted") {
                    console.log("הרשאות התראות כבר ניתנו");
                    return true;
                }
                
                // בקש הרשאה
                if (Notification.permission !== "denied") {
                    const permission = await Notification.requestPermission();
                    if (permission === "granted") {
                        console.log("הרשאות התראות ניתנו!");
                        // הצג התראת בדיקה
                        new Notification("התראות פעילות! 🎉", {
                            body: "מעכשיו תקבל עדכונים חשובים",
                            icon: "/pwa/icons/android/android-launchericon-192-192.png"
                        });
                        return true;
                    }
                }
                
                console.log("הרשאות התראות נדחו");
                return false;
                
            } catch (error) {
                console.error("שגיאה בבקשת הרשאות:", error);
                return false;
            }
        },
        
        // בקשת הרשאה ל-Push (דורש Service Worker)
        async requestPushPermission() {
            try {
                // בדוק תמיכה
                if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
                    console.log("הדפדפן לא תומך ב-Push");
                    return false;
                }
                
                // המתן ל-Service Worker
                const registration = await navigator.serviceWorker.ready;
                
                // בדוק מנוי קיים
                let subscription = await registration.pushManager.getSubscription();
                
                if (subscription) {
                    console.log("כבר רשום ל-Push:", subscription);
                    return subscription;
                }
                
                // צור מנוי חדש
                const publicKey = "YOUR_PUBLIC_VAPID_KEY"; // יש להחליף במפתח אמיתי
                
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    // applicationServerKey: publicKey // הערה: נדרש מפתח VAPID
                });
                
                console.log("נרשם ל-Push בהצלחה:", subscription);
                
                // שלח לשרת (אופציונלי)
                // await this.sendSubscriptionToServer(subscription);
                
                return subscription;
                
            } catch (error) {
                console.error("שגיאה ברישום Push:", error);
                return false;
            }
        },
        
        // בדיקת סטטוס הרשאות
        checkPermissions() {
            const status = {
                notifications: false,
                push: false
            };
            
            // בדוק התראות
            if ("Notification" in window) {
                status.notifications = Notification.permission === "granted";
            }
            
            // בדוק Push (אסינכרוני)
            if ("serviceWorker" in navigator && "PushManager" in window) {
                navigator.serviceWorker.ready.then(registration => {
                    registration.pushManager.getSubscription().then(subscription => {
                        status.push = !!subscription;
                        console.log("סטטוס הרשאות:", status);
                    });
                });
            }
            
            return status;
        },
        
        // הצגת התראה פשוטה
        showNotification(title, options = {}) {
            if (Notification.permission !== "granted") {
                console.log("אין הרשאות להתראות");
                return false;
            }
            
            const defaultOptions = {
                icon: "/pwa/icons/android/android-launchericon-192-192.png",
                badge: "/pwa/icons/android/android-launchericon-72-72.png",
                vibrate: [200, 100, 200],
                dir: "rtl",
                lang: "he"
            };
            
            const notification = new Notification(title, {...defaultOptions, ...options});
            
            // סגור אוטומטית אחרי 5 שניות
            setTimeout(() => notification.close(), 5000);
            
            return notification;
        }
    };
    
    // חשוף גלובלית
    window.Permissions = window.PermissionsManager;
    
    console.log("מערכת הרשאות מוכנה. השתמש ב-Permissions.requestNotificationPermission() או Permissions.requestPushPermission()");
    </script>
    ';
}

/**
 * מחזיר כפתורי בקשת הרשאות
 */
function getPermissionsButtons() {
    return '
    <div class="permissions-buttons" style="padding: 20px; text-align: center;">
        <button onclick="Permissions.requestNotificationPermission()" 
                style="background: #10b981; color: white; border: none; padding: 12px 24px; 
                       border-radius: 8px; margin: 10px; cursor: pointer; font-size: 16px;">
            🔔 אפשר התראות
        </button>
        
        <button onclick="Permissions.requestPushPermission()" 
                style="background: #667eea; color: white; border: none; padding: 12px 24px; 
                       border-radius: 8px; margin: 10px; cursor: pointer; font-size: 16px;">
            📬 אפשר Push Notifications
        </button>
        
        <button onclick="Permissions.showNotification(\'בדיקה!\', {body: \'זו התראת בדיקה\'})" 
                style="background: #f59e0b; color: white; border: none; padding: 12px 24px; 
                       border-radius: 8px; margin: 10px; cursor: pointer; font-size: 16px;">
            🧪 בדיקת התראה
        </button>
    </div>
    ';
}

/**
 * בדיקה בצד השרת אם יש הרשאות (לפי cookies)
 */
function hasNotificationPermission() {
    return isset($_COOKIE['notification_permission']) && 
           $_COOKIE['notification_permission'] === 'granted';
}

function hasPushPermission() {
    return isset($_COOKIE['push_permission']) && 
           $_COOKIE['push_permission'] === 'granted';
}