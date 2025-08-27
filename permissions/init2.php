<?php
/**
 * Permissions System - Simple & Working
 */

function getPermissionsScript() {
    return '<script>
window.Permissions = {
    requestNotificationPermission: async function() {
        try {
            if (!("Notification" in window)) {
                alert("הדפדפן שלך לא תומך בהתראות");
                return false;
            }
            
            if (Notification.permission === "granted") {
                console.log("הרשאות התראות כבר ניתנו");
                alert("התראות כבר מאופשרות!");
                return true;
            }
            
            const permission = await Notification.requestPermission();
            
            if (permission === "granted") {
                // הצג התראת בדיקה
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
            console.error("שגיאה:", error);
            alert("שגיאה בבקשת הרשאות: " + error.message);
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
            
            // רשום Service Worker אם לא רשום
            let registration = await navigator.serviceWorker.getRegistration();
            if (!registration) {
                console.log("רושם Service Worker...");
                registration = await navigator.serviceWorker.register("/service-worker.js");
                await new Promise(r => setTimeout(r, 1000)); // המתן שנייה
            }
            
            // בדוק מנוי קיים
            let subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                alert("כבר רשום ל-Push Notifications!");
                console.log("Push subscription exists:", subscription);
                return subscription;
            }
            
            // צור מנוי חדש
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true
            });
            
            alert("נרשמת בהצלחה ל-Push Notifications!");
            console.log("New push subscription:", subscription);
            return subscription;
            
        } catch (error) {
            console.error("Push error:", error);
            alert("שגיאה ב-Push: " + error.message);
            return false;
        }
    },
    
    showNotification: function(title, options = {}) {
        if (!("Notification" in window)) {
            alert("הדפדפן לא תומך בהתראות");
            return false;
        }
        
        if (Notification.permission !== "granted") {
            alert("אין הרשאות להתראות - לחץ על כפתור הפעלת התראות");
            return false;
        }
        
        const notification = new Notification(title, {
            body: options.body || "זו התראת בדיקה",
            icon: options.icon || "/pwa/icons/android/android-launchericon-192-192.png",
            badge: "/pwa/icons/android/android-launchericon-72-72.png",
            dir: "rtl",
            lang: "he"
        });
        
        setTimeout(() => notification.close(), 5000);
        return notification;
    }
};

// הוסף כפתורים אוטומטית לדף אם יש אלמנט עם ID מתאים
document.addEventListener("DOMContentLoaded", function() {
    console.log("Permissions system ready!");
    
    // אם יש div עם ID="permission-buttons", הוסף לו כפתורים
    const container = document.getElementById("permission-buttons");
    if (container) {
        container.innerHTML = `
            <button onclick="Permissions.requestNotificationPermission()" 
                    style="background: #10b981; color: white; border: none; 
                           padding: 12px 24px; border-radius: 8px; margin: 5px; 
                           cursor: pointer; font-size: 16px;">
                🔔 הפעל התראות
            </button>
            <button onclick="Permissions.requestPushPermission()" 
                    style="background: #667eea; color: white; border: none; 
                           padding: 12px 24px; border-radius: 8px; margin: 5px; 
                           cursor: pointer; font-size: 16px;">
                📬 הפעל Push
            </button>
            <button onclick="Permissions.showNotification(\'בדיקה!\', {body: \'ההתראות עובדות מצוין!\'})" 
                    style="background: #f59e0b; color: white; border: none; 
                           padding: 12px 24px; border-radius: 8px; margin: 5px; 
                           cursor: pointer; font-size: 16px;">
                🧪 בדיקת התראה
            </button>
        `;
    }
});
</script>';
}

function getPermissionsButtons() {
    return '<div id="permission-buttons" style="padding: 20px; text-align: center;">
        <button onclick="Permissions.requestNotificationPermission()" 
                style="background: #10b981; color: white; border: none; 
                       padding: 12px 24px; border-radius: 8px; margin: 5px; 
                       cursor: pointer; font-size: 16px;">
            🔔 הפעל התראות
        </button>
        <button onclick="Permissions.requestPushPermission()" 
                style="background: #667eea; color: white; border: none; 
                       padding: 12px 24px; border-radius: 8px; margin: 5px; 
                       cursor: pointer; font-size: 16px;">
            📬 הפעל Push
        </button>
        <button onclick="Permissions.showNotification(\'בדיקה!\', {body: \'ההתראות עובדות מצוין!\'})" 
                style="background: #f59e0b; color: white; border: none; 
                       padding: 12px 24px; border-radius: 8px; margin: 5px; 
                       cursor: pointer; font-size: 16px;">
            🧪 בדיקת התראה
        </button>
    </div>';
}
?>