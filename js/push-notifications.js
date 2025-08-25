// js/push-notifications.js - Client-side Push Notifications Manager

class PushNotificationManager {
    constructor() {
        this.vapidPublicKey = null;
        this.subscription = null;
        this.isSupported = 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
        this.serviceWorkerReady = false;
    }

    /**
     * אתחול מערכת ההתראות
     */
    async init() {
        if (!this.isSupported) {
            console.log('Push notifications are not supported in this browser');
            return false;
        }

        try {
            // המתן ל-Service Worker
            const registration = await navigator.serviceWorker.ready;
            this.serviceWorkerReady = true;
            console.log('Service Worker is ready');

            // קבל את ה-VAPID public key מהשרת
            const response = await fetch('/login/api/push-notifications.php?action=vapid-key');
            const data = await response.json();
            this.vapidPublicKey = data.publicKey;
            console.log('VAPID key received');

            // בדוק אם יש כבר subscription
            this.subscription = await registration.pushManager.getSubscription();
            
            if (this.subscription) {
                console.log('Found existing subscription');
                // וודא שה-subscription פעיל בשרת
                await this.syncSubscriptionWithServer();
            } else {
                console.log('No existing subscription found');
            }

            // בדוק הרשאות
            const permission = Notification.permission;
            console.log('Current permission status:', permission);

            if (permission === 'granted' && !this.subscription) {
                // יש הרשאה אבל אין subscription - צור אחד
                await this.subscribe();
            }

            return true;
        } catch (error) {
            console.error('Failed to initialize push notifications:', error);
            return false;
        }
    }

    /**
     * בקשת הרשאה והרשמה להתראות
     */
    async requestPermissionAndSubscribe() {
        try {
            // בקש הרשאה
            const permission = await Notification.requestPermission();
            console.log('Permission result:', permission);

            if (permission !== 'granted') {
                console.log('Permission denied');
                this.showMessage('ההרשאה להתראות נדחתה', 'error');
                return false;
            }

            // הרשם להתראות
            const subscribed = await this.subscribe();
            
            if (subscribed) {
                this.showMessage('התראות הופעלו בהצלחה!', 'success');
                // שלח התראת בדיקה
                await this.sendTestNotification();
                return true;
            }

            return false;
        } catch (error) {
            console.error('Error requesting permission:', error);
            this.showMessage('שגיאה בהפעלת התראות', 'error');
            return false;
        }
    }

    /**
     * הרשמה ל-Push Notifications
     */
    async subscribe() {
        if (!this.serviceWorkerReady) {
            console.error('Service Worker not ready');
            return false;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            
            // המר את ה-VAPID key
            const convertedVapidKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
            
            // צור subscription
            this.subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            console.log('Push subscription created:', this.subscription);

            // שלח לשרת
            const saved = await this.sendSubscriptionToServer(this.subscription);
            
            if (saved) {
                console.log('Subscription saved to server');
                localStorage.setItem('push-subscription', JSON.stringify(this.subscription));
                return true;
            }

            return false;
        } catch (error) {
            console.error('Failed to subscribe:', error);
            return false;
        }
    }

    /**
     * שליחת ה-subscription לשרת
     */
    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/login/api/push-notifications.php?action=subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Failed to send subscription to server:', error);
            return false;
        }
    }

    /**
     * סנכרון subscription עם השרת
     */
    async syncSubscriptionWithServer() {
        if (!this.subscription) return false;
        
        return await this.sendSubscriptionToServer(this.subscription);
    }

    /**
     * ביטול הרשמה
     */
    async unsubscribe() {
        try {
            if (this.subscription) {
                await this.subscription.unsubscribe();
                this.subscription = null;
                localStorage.removeItem('push-subscription');
                
                // הודע לשרת על הביטול
                // כאן אפשר להוסיף קריאה לשרת לביטול
                
                this.showMessage('התראות בוטלו', 'info');
                return true;
            }
        } catch (error) {
            console.error('Failed to unsubscribe:', error);
            return false;
        }
    }

    /**
     * שליחת התראת בדיקה
     */
    async sendTestNotification() {
        try {
            const response = await fetch('/login/api/push-notifications.php?action=test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                console.log('Test notification sent');
            } else {
                console.error('Failed to send test notification:', data.message);
            }
        } catch (error) {
            console.error('Error sending test notification:', error);
        }
    }

    /**
     * בדיקה אם המשתמש רשום להתראות
     */
    async isSubscribed() {
        if (!this.serviceWorkerReady) return false;
        
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        return subscription !== null;
    }

    /**
     * הצגת הודעה למשתמש
     */
    showMessage(message, type = 'info') {
        // צור toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideUp 0.3s ease;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * המרת VAPID key
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * יצירת UI לניהול התראות
     */
    createNotificationUI() {
        const container = document.createElement('div');
        container.id = 'notification-settings';
        container.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        `;
        
        container.innerHTML = `
            <h3 style="margin-bottom: 15px; color: #333;">הגדרות התראות</h3>
            <div id="notification-status" style="margin-bottom: 15px;"></div>
            <button id="toggle-notifications" class="btn" style="
                background: #667eea;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
                width: 100%;
            ">טוען...</button>
        `;
        
        document.body.appendChild(container);
        
        // עדכן את הסטטוס
        this.updateNotificationUI();
    }

    /**
     * עדכון ממשק המשתמש
     */
    async updateNotificationUI() {
        const statusDiv = document.getElementById('notification-status');
        const toggleBtn = document.getElementById('toggle-notifications');
        
        if (!statusDiv || !toggleBtn) return;
        
        const isSubscribed = await this.isSubscribed();
        const permission = Notification.permission;
        
        if (permission === 'denied') {
            statusDiv.innerHTML = `<span style="color: #dc3545;">❌ התראות חסומות בדפדפן</span>`;
            toggleBtn.textContent = 'התראות חסומות';
            toggleBtn.disabled = true;
        } else if (isSubscribed) {
            statusDiv.innerHTML = `<span style="color: #28a745;">✅ התראות פעילות</span>`;
            toggleBtn.textContent = 'בטל התראות';
            toggleBtn.onclick = () => this.unsubscribe().then(() => this.updateNotificationUI());
        } else {
            statusDiv.innerHTML = `<span style="color: #ffc107;">⚠️ התראות לא פעילות</span>`;
            toggleBtn.textContent = 'הפעל התראות';
            toggleBtn.onclick = () => this.requestPermissionAndSubscribe().then(() => this.updateNotificationUI());
        }
    }
}

// אתחול אוטומטי בטעינת הדף
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Initializing Push Notification Manager...');
    
    // צור מנהל התראות גלובלי
    window.pushManager = new PushNotificationManager();
    
    // המתן קצת לטעינת Service Worker
    setTimeout(async () => {
        await window.pushManager.init();
        
        // אם אנחנו בדף הדשבורד, הצג אופציה להפעלת התראות
        if (window.location.pathname.includes('dashboard')) {
            // בדוק אם צריך להציג בקשת הרשאה
            const permission = Notification.permission;
            const lastAsk = localStorage.getItem('last-notification-ask');
            const daysSinceLastAsk = lastAsk ? (Date.now() - parseInt(lastAsk)) / (1000 * 60 * 60 * 24) : 999;
            
            if (permission === 'default' && daysSinceLastAsk > 3) {
                // הצג חלון יפה לבקשת התראות
                setTimeout(() => {
                    if (confirm('האם תרצה לקבל התראות על פעילות בקבוצות שלך?')) {
                        window.pushManager.requestPermissionAndSubscribe();
                    }
                    localStorage.setItem('last-notification-ask', Date.now().toString());
                }, 5000);
            }
        }
    }, 1000);
});

// הוסף סגנונות לאנימציות
const style = document.createElement('style');
style.textContent = `
@keyframes slideUp {
    from {
        transform: translateX(-50%) translateY(100px);
        opacity: 0;
    }
    to {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
}

@keyframes slideDown {
    from {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
    to {
        transform: translateX(-50%) translateY(100px);
        opacity: 0;
    }
}
`;
document.head.appendChild(style);

console.log('Push Notification Manager loaded!');