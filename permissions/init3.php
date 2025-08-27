<?php
/**
 * Permissions System - WORKING VERSION
 */

function getPermissionsScript() {
    $script = <<<'EOT'
<script>
// יצירת אובייקט Permissions גלובלי
window.Permissions = {
    requestNotificationPermission: async function() {
        try {
            if (!("Notification" in window)) {
                alert("הדפדפן שלך לא תומך בהתראות");
                return false;
            }
            
            if (Notification.permission === "granted") {
                alert("התראות כבר מאופשרות!");
                return true;
            }
            
            const permission = await Notification.requestPermission();
            
            if (permission === "granted") {
                new Notification("התראות מופעלות! 🎉", {
                    body: "מעולה! עכשיו תקבל התראות מהאפליקציה",
                    icon: "/pwa/icons/android/android-launchericon-192-192.png"
                });
                return true;
            } else {
                alert("לא ניתנו הרשאות להתראות");
                return false;
            }
        } catch (error) {
            alert("שגיאה: " + error.message);
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
            
            let registration = await navigator.serviceWorker.getRegistration();
            if (!registration) {
                registration = await navigator.serviceWorker.register("/service-worker.js");
                await new Promise(r => setTimeout(r, 1000));
            }
            
            let subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                alert("כבר רשום ל-Push Notifications!");
                return subscription;
            }
            
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true
            });
            
            alert("נרשמת בהצלחה ל-Push Notifications!");
            return subscription;
            
        } catch (error) {
            alert("שגיאה ב-Push: " + error.message);
            return false;
        }
    },
    
    showNotification: function(title, options) {
        if (!("Notification" in window)) {
            alert("הדפדפן לא תומך בהתראות");
            return false;
        }
        
        if (Notification.permission !== "granted") {
            alert("אין הרשאות להתראות - לחץ על כפתור הפעלת התראות");
            return false;
        }
        
        const notification = new Notification(title, {
            body: (options && options.body) || "זו התראת בדיקה",
            icon: "/pwa/icons/android/android-launchericon-192-192.png"
        });
        
        setTimeout(function() { notification.close(); }, 5000);
        return notification;
    }
};

// בדיקה שהאובייקט נוצר
console.log("Permissions object created:", window.Permissions);
</script>
EOT;
    
    return $script;
}

function getPermissionsButtons() {
    $html = <<<'EOT'
<div id="permission-buttons" style="padding: 20px; text-align: center;">
    <button onclick="window.Permissions.requestNotificationPermission()" 
            style="background: #10b981; color: white; border: none; 
                   padding: 12px 24px; border-radius: 8px; margin: 5px; 
                   cursor: pointer; font-size: 16px;">
        🔔 הפעל התראות
    </button>
    <button onclick="window.Permissions.requestPushPermission()" 
            style="background: #667eea; color: white; border: none; 
                   padding: 12px 24px; border-radius: 8px; margin: 5px; 
                   cursor: pointer; font-size: 16px;">
        📬 הפעל Push
    </button>
    <button onclick="window.Permissions.showNotification('בדיקה!', {body: 'ההתראות עובדות מצוין!'})" 
            style="background: #f59e0b; color: white; border: none; 
                   padding: 12px 24px; border-radius: 8px; margin: 5px; 
                   cursor: pointer; font-size: 16px;">
        🧪 בדיקת התראה
    </button>
</div>
EOT;
    
    return $html;
}
?>