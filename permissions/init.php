<?php
/**
 * Permissions System - FIXED VERSION
 */

function getPermissionsScript() {
    $script = <<<'EOT'
            <script>
            // יצירת אובייקט Permissions גלובלי
            window.Permissions = {
                requestNotificationPermission: async function() {
                    try {
                        if (!("Notification" in window)) {
                            console.log("הדפדפן לא תומך בהתראות");
                            return false;
                        }
                        
                        // אם כבר יש הרשאה
                        if (Notification.permission === "granted") {
                            console.log("התראות כבר מאופשרות");
                            return true;
                        }
                        
                        // אם נחסם
                        if (Notification.permission === "denied") {
                            console.log("התראות נחסמו על ידי המשתמש");
                            return false;
                        }
                        
                        // בקש הרשאה!
                        console.log("מבקש הרשאת התראות...");
                        const permission = await Notification.requestPermission();
                        
                        if (permission === "granted") {
                            alert("התראות הופעלו בהצלחה!");
                            this.showNotification("התראות מופעלות! 🎉", {
                                body: "מעולה! עכשיו תקבל התראות מהאפליקציה"
                            });
                            return true;
                        } else {
                            console.log("המשתמש דחה את ההרשאה");
                            return false;
                        }
                        
                    } catch (error) {
                        console.error("שגיאה:", error);
                        return false;
                    }
                },
                
                requestPushPermission: async function() {
                    try {
                        if (!("serviceWorker" in navigator)) {
                            alert("הדפדפן לא תומך ב-Service Worker");
                            return false;
                        }
                        
                        if (!("PushManager" in window)) {
                            alert("הדפדפן לא תומך ב-Push Notifications");
                            return false;
                        }
                        
                        // קודם בקש הרשאות רגילות
                        if (Notification.permission !== "granted") {
                            alert("צריך קודם לאשר התראות רגילות");
                            await this.requestNotificationPermission();
                            return false;
                        }
                        
                        let registration = await navigator.serviceWorker.getRegistration();
                        if (!registration) {
                            console.log("רושם Service Worker...");
                            registration = await navigator.serviceWorker.register("/service-worker.js");
                            await new Promise(r => setTimeout(r, 1000));
                        }
                        
                        let subscription = await registration.pushManager.getSubscription();
                        
                        if (subscription) {
                            alert("כבר רשום ל-Push Notifications!");
                            return subscription;
                        }
                        
                        // יצירת VAPID key פשוט לבדיקה
                        // בproduction צריך ליצור מפתח אמיתי
                        const vapidPublicKey = 'BIzaSyD2_7N_4CfS9g2O5Sda4Ntz-0LqPgK5nL9-5Tk8GmJQqV3idlBxWwlSMvACDKPAp2oZ2DO3SoFcQu-2s1I8rSE';
                        const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);
                        
                        try {
                            subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: convertedVapidKey
                            });
                            
                            alert("נרשמת בהצלחה ל-Push Notifications!");
                            console.log("Push subscription:", subscription);
                            return subscription;
                        } catch (subError) {
                            // אם נכשל עם VAPID, נסה בלי
                            console.log("Trying without VAPID key...");
                            alert("Push Notifications דורש הגדרות מיוחדות בשרת. כרגע רק התראות רגילות זמינות.");
                            return false;
                        }
                        
                    } catch (error) {
                        alert("שגיאה ב-Push: " + error.message);
                        return false;
                    }
                },
                
                showNotification: async function(title, options) {
                    try {
                        if (!("Notification" in window)) {
                            alert("הדפדפן לא תומך בהתראות");
                            return false;
                        }
                        
                        if (Notification.permission !== "granted") {
                            alert("אין הרשאות להתראות - לחץ על כפתור הפעלת התראות");
                            return false;
                        }
                        
                        // יצירת אובייקט התראה
                        const notificationData = {
                            id: Date.now().toString(),
                            title: title,
                            body: (options && options.body) || "זו התראת בדיקה",
                            timestamp: Date.now(),
                            read: false,
                            url: (options && options.url) || null
                        };
                        
                        // שמירה ב-localStorage - החלק שהיה חסר!
                        let notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
                        notifications.unshift(notificationData);
                        
                        // הגבל ל-50 התראות אחרונות
                        if (notifications.length > 50) {
                            notifications = notifications.slice(0, 50);
                        }
                        
                        localStorage.setItem('notifications', JSON.stringify(notifications));
                        console.log('התראה נשמרה ב-localStorage:', notificationData);
                        
                        // נסה דרך Service Worker אם זמין
                        if ('serviceWorker' in navigator && 'PushManager' in window) {
                            const registration = await navigator.serviceWorker.getRegistration();
                            if (registration) {
                                // סגור התראות קודמות (אופציונלי)
                                const existingNotifications = await registration.getNotifications();
                                existingNotifications.forEach(n => n.close());
                                
                                // השתמש ב-Service Worker להצגת התראה
                                await registration.showNotification(title, {
                                    body: notificationData.body,
                                    icon: "/pwa/icons/android/android-launchericon-192-192.png",
                                    badge: "/pwa/icons/android/android-launchericon-72-72.png",
                                    vibrate: [200, 100, 200],
                                    tag: "msg-" + notificationData.id,
                                    data: {
                                        id: notificationData.id,
                                        url: notificationData.url,
                                        time: new Date().toISOString()
                                    },
                                    requireInteraction: false,
                                    renotify: true,
                                    timestamp: Date.now(),
                                    silent: false,
                                    actions: [
                                        {action: 'view', title: 'צפה'},
                                        {action: 'close', title: 'סגור'}
                                    ]
                                });
                                
                                console.log("Notification shown via Service Worker");
                                return true;
                            }
                        }
                        
                        // אם אין Service Worker, נסה בדרך הישנה (לא תמיד עובד)
                        try {
                            const notification = new Notification(title, {
                                body: (options && options.body) || "זו התראת בדיקה",
                                icon: "/pwa/icons/android/android-launchericon-192-192.png"
                            });
                            
                            setTimeout(function() { notification.close(); }, 5000);
                            return notification;
                        } catch (e) {
                            alert("התראות עובדות רק עם Service Worker פעיל. רענן את הדף ונסה שוב.");
                            return false;
                        }
                        
                    } catch (error) {
                        alert("שגיאה בהצגת התראה: " + error.message);
                        return false;
                    }
                }
            };

            // פונקציית עזר להמרת VAPID key
            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            // בדיקה שהאובייקט נוצר
            console.log("✅ Permissions system loaded successfully!");
            console.log("Available functions:", Object.keys(window.Permissions));
            </script>
        EOT;
                
    return $script;
}

function getPermissionsButtons() {
    $html = <<<'EOT'
<div id="permission-buttons" style="padding: 20px; text-align: center; background: rgba(255,255,255,0.1); border-radius: 10px; margin: 10px 0;">
    <h3 style="margin-bottom: 15px; color: #333;">הגדרות התראות</h3>
    
    <button onclick="window.Permissions.requestNotificationPermission()" 
            style="background: #10b981; color: white; border: none; 
                   padding: 12px 24px; border-radius: 8px; margin: 5px; 
                   cursor: pointer; font-size: 16px; transition: all 0.3s;"
            onmouseover="this.style.opacity='0.8'" 
            onmouseout="this.style.opacity='1'">
        🔔 הפעל התראות
    </button>
    
    <button onclick="window.Permissions.showNotification('בדיקה!', {body: 'ההתראות עובדות מצוין!'})" 
            style="background: #f59e0b; color: white; border: none; 
                   padding: 12px 24px; border-radius: 8px; margin: 5px; 
                   cursor: pointer; font-size: 16px; transition: all 0.3s;"
            onmouseover="this.style.opacity='0.8'" 
            onmouseout="this.style.opacity='1'">
        🧪 בדיקת התראה
    </button>
    
    <div style="margin-top: 10px; font-size: 12px; color: #666;">
        💡 Push Notifications דורש הגדרות שרת מתקדמות
    </div>
</div>
EOT;
    
    return $html;
}

// פונקציה פשוטה לבדיקת סטטוס
function getPermissionStatus() {
    return '<script>
    document.addEventListener("DOMContentLoaded", function() {
        if ("Notification" in window) {
            console.log("📊 Notification Permission Status:", Notification.permission);
        }
    });
    </script>';
}

?>