// js/push-notifications-manager.js
// מערכת ניהול התראות Push מרכזית עם תמיכה מלאה

class PushNotificationManager {
    constructor() {
        this.isSupported = this.checkSupport();
        this.permission = Notification.permission;
        this.subscription = null;
        this.swRegistration = null;
        this.notificationQueue = [];
        this.listeners = new Map();
        this.config = {
            swPath: '/family/service-worker.js',
            swScope: '/family/',
            apiEndpoint: '/family/api/simple-notifications.php',
            pollInterval: 3000, // בדיקה כל 30 שניות
            maxRetries: 3,
            retryDelay: 200,
            defaultIcon: '/family/images/icons/android/android-launchericon-192-192.png',
            defaultBadge: '/family/images/icons/android/android-launchericon-96-96.png',
            vibrate: [200, 100, 200],
            requireInteraction: false,
            renotify: true,
            silent: false
        };
        
        this.init();
    }
    
    // בדיקת תמיכה
    checkSupport() {
        return 'Notification' in window && 
               'serviceWorker' in navigator && 
               'PushManager' in window;
    }
    
    // אתחול המערכת
    async init() {
        if (!this.isSupported) {
            console.warn('Push notifications are not supported in this browser');
            return false;
        }
        
        try {
            // רישום Service Worker
            await this.registerServiceWorker();
            
            // בדיקת הרשאות
            await this.checkPermission();
            
            // התחל האזנה להודעות
            this.setupMessageListener();
            
            // התחל בדיקה תקופתית
            this.startPolling();
            
            // האזנה לשינויי סטטוס
            this.setupVisibilityListener();
            
            console.log('✅ Push Notification Manager initialized');
            return true;
            
        } catch (error) {
            console.error('Failed to initialize push notifications:', error);
            return false;
        }
    }
    
    // רישום Service Worker
    async registerServiceWorker() {
        try {
            this.swRegistration = await navigator.serviceWorker.register(
                this.config.swPath,
                { scope: this.config.swScope }
            );
            
            console.log('Service Worker registered:', this.swRegistration);
            
            // האזנה לעדכונים
            this.swRegistration.addEventListener('updatefound', () => {
                console.log('New Service Worker update available');
                this.handleSWUpdate();
            });
            
            return this.swRegistration;
            
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            throw error;
        }
    }
    
    // בדיקת הרשאות
    async checkPermission() {
        this.permission = Notification.permission;
        
        if (this.permission === 'granted') {
            await this.subscribe();
        }
        
        return this.permission;
    }
    
    // בקשת הרשאה
    async requestPermission() {
        if (this.permission === 'granted') {
            return 'granted';
        }
        
        if (this.permission === 'denied') {
            this.showPermissionDeniedMessage();
            return 'denied';
        }
        
        try {
            this.permission = await Notification.requestPermission();
            
            if (this.permission === 'granted') {
                await this.subscribe();
                this.showSuccessMessage('התראות הופעלו בהצלחה! 🎉');
                
                // שלח התראת ברכה
                setTimeout(() => {
                    this.showNotification('ברוך הבא! 👋', {
                        body: 'מעכשיו תקבל עדכונים בזמן אמת על כל הפעילות בקבוצות שלך',
                        tag: 'welcome'
                    });
                }, 2000);
            }
            
            return this.permission;
            
        } catch (error) {
            console.error('Failed to request permission:', error);
            return 'denied';
        }
    }
    
    // רישום להתראות
    async subscribe() {
        if (!this.swRegistration) {
            await this.registerServiceWorker();
        }
        
        try {
            // בדוק אם כבר רשום
            this.subscription = await this.swRegistration.pushManager.getSubscription();
            
            if (!this.subscription) {
                // צור רישום חדש - נשתמש בגישה פשוטה יותר
                const vapidPublicKey = await this.getVapidKey();
                
                if (vapidPublicKey) {
                    this.subscription = await this.swRegistration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey)
                    });
                }
            }
            
            // שמור בשרת
            if (this.subscription) {
                await this.saveSubscription();
            }
            
            return this.subscription;
            
        } catch (error) {
            console.error('Failed to subscribe:', error);
            // נמשיך לעבוד במצב fallback
            return null;
        }
    }
    
    // קבלת VAPID key (אם קיים)
    async getVapidKey() {
        try {
            const response = await fetch(this.config.apiEndpoint + '?action=get-vapid-key');
            if (response.ok) {
                const data = await response.json();
                return data.publicKey;
            }
        } catch (error) {
            // אם אין VAPID, נעבוד בלעדיו
            console.log('VAPID not available, using fallback mode');
        }
        return null;
    }
    
    // שמירת רישום בשרת
    async saveSubscription() {
        try {
            // במצב פשוט, שמור רק token
            const token = this.subscription ? 
                btoa(JSON.stringify(this.subscription)) : 
                'fallback-token-' + Date.now();
            
            const response = await fetch(this.config.apiEndpoint + '?action=save-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: token,
                    deviceInfo: {
                        userAgent: navigator.userAgent,
                        platform: navigator.platform,
                        language: navigator.language
                    }
                })
            });
            
            const data = await response.json();
            console.log('Subscription saved:', data);
            
        } catch (error) {
            console.error('Failed to save subscription:', error);
        }
    }
    
    // האזנה להודעות מ-Service Worker
    setupMessageListener() {
        navigator.serviceWorker.addEventListener('message', (event) => {
            console.log('Message from SW:', event.data);
            
            if (event.data.type === 'notification-click') {
                this.handleNotificationClick(event.data);
            } else if (event.data.type === 'push-received') {
                this.handlePushReceived(event.data);
            }
            
            // הפעל listeners רשומים
            this.triggerListeners(event.data.type, event.data);
        });
    }
    
    // בדיקה תקופתית להתראות חדשות (fallback)
    startPolling() {
        // בדוק מיד
        this.checkForNotifications();
        
        // המשך בדיקה תקופתית
        this.pollingInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.checkForNotifications();
            }
        }, this.config.pollInterval);
    }
    
    // בדיקת התראות חדשות
    async checkForNotifications() {
        try {
            const response = await fetch(this.config.apiEndpoint + '?action=get-pending', {
                credentials: 'include'
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.notifications && data.notifications.length > 0) {
                for (const notification of data.notifications) {
                    await this.showNotification(notification.title, notification);
                }
            }
            
        } catch (error) {
            // שקט, אין צורך לדווח על כל כשלון
        }
    }
    
    // הצגת התראה
    async showNotification(title, options = {}) {
        if (this.permission !== 'granted') {
            console.warn('Notifications not permitted');
            return false;
        }
        
        try {
            const notificationOptions = {
                body: options.body || '',
                icon: options.icon || this.config.defaultIcon,
                badge: options.badge || this.config.defaultBadge,
                tag: options.tag || 'notification-' + Date.now(),
                data: options.data || {},
                vibrate: options.vibrate || this.config.vibrate,
                requireInteraction: options.requireInteraction || this.config.requireInteraction,
                renotify: options.renotify !== undefined ? options.renotify : this.config.renotify,
                silent: options.silent || this.config.silent,
                timestamp: Date.now(),
                actions: options.actions || [],
                dir: 'rtl',
                lang: 'he'
            };
            
            // אם יש Service Worker, השתמש בו
            if (this.swRegistration) {
                await this.swRegistration.showNotification(title, notificationOptions);
            } else {
                // אחרת, הצג התראה רגילה
                new Notification(title, notificationOptions);
            }
            
            // הוסף להיסטוריה
            this.addToHistory(title, notificationOptions);
            
            // הפעל listeners
            this.triggerListeners('notification-shown', { title, options: notificationOptions });
            
            return true;
            
        } catch (error) {
            console.error('Failed to show notification:', error);
            return false;
        }
    }
    
    // טיפול בלחיצה על התראה
    handleNotificationClick(data) {
        console.log('Notification clicked:', data);
        
        // נווט לדף הרלוונטי
        if (data.url) {
            window.location.href = data.url;
        } else if (data.data && data.data.url) {
            window.location.href = data.data.url;
        }
        
        // הפעל listeners
        this.triggerListeners('notification-click', data);
    }
    
    // טיפול בקבלת Push
    handlePushReceived(data) {
        console.log('Push received:', data);
        
        // רענן נתונים בדף אם צריך
        if (data.data && data.data.type) {
            this.handleDataUpdate(data.data);
        }
        
        // הפעל listeners
        this.triggerListeners('push-received', data);
    }
    
    // עדכון נתונים בדף
    handleDataUpdate(data) {
        switch (data.type) {
            case 'group_invitation':
                this.refreshInvitations();
                break;
            case 'new_purchase':
                this.refreshPurchases();
                break;
            case 'invitation_response':
                this.refreshMembers();
                break;
            default:
                console.log('Unknown data type:', data.type);
        }
    }
    
    // רענון הזמנות
    refreshInvitations() {
        const invitationsSection = document.querySelector('.invitations-section');
        if (invitationsSection) {
            // אם יש פונקציה גלובלית לרענון
            if (typeof window.refreshInvitations === 'function') {
                window.refreshInvitations();
            } else {
                // אחרת, רענן את הדף
                location.reload();
            }
        }
    }
    
    // רענון קניות
    refreshPurchases() {
        const purchasesSection = document.getElementById('purchases');
        if (purchasesSection && purchasesSection.style.display !== 'none') {
            if (typeof window.refreshPurchases === 'function') {
                window.refreshPurchases();
            } else {
                location.reload();
            }
        }
    }
    
    // רענון חברים
    refreshMembers() {
        const membersSection = document.getElementById('members');
        if (membersSection && membersSection.style.display !== 'none') {
            if (typeof window.refreshMembers === 'function') {
                window.refreshMembers();
            } else {
                location.reload();
            }
        }
    }
    
    // האזנה לשינויי visibility
    setupVisibilityListener() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                // כשחוזרים לדף, בדוק התראות
                this.checkForNotifications();
            }
        });
        
        // האזנה לחזרה לאונליין
        window.addEventListener('online', () => {
            console.log('Back online, checking notifications');
            this.checkForNotifications();
        });
    }
    
    // הוספת listener חיצוני
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }
    
    // הסרת listener
    off(event, callback) {
        if (this.listeners.has(event)) {
            const callbacks = this.listeners.get(event);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        }
    }
    
    // הפעלת listeners
    triggerListeners(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Listener error:', error);
                }
            });
        }
    }
    
    // הוספה להיסטוריה
    addToHistory(title, options) {
        const notification = {
            title,
            body: options.body,
            timestamp: options.timestamp || Date.now(),
            data: options.data,
            read: false
        };
        
        this.notificationQueue.push(notification);
        
        // שמור רק 50 אחרונים
        if (this.notificationQueue.length > 50) {
            this.notificationQueue.shift();
        }
        
        // שמור ב-localStorage
        try {
            localStorage.setItem('notification-history', JSON.stringify(this.notificationQueue));
        } catch (error) {
            // אם אין מקום, נקה היסטוריה ישנה
            this.notificationQueue = this.notificationQueue.slice(-20);
            localStorage.setItem('notification-history', JSON.stringify(this.notificationQueue));
        }
        
        // עדכן badge
        this.updateBadge();
    }
    
    // עדכון badge
    updateBadge() {
        const unreadCount = this.notificationQueue.filter(n => !n.read).length;
        
        // עדכן badge בדף
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // עדכן title
        if (unreadCount > 0) {
            const originalTitle = document.title.replace(/^\(\d+\) /, '');
            document.title = `(${unreadCount}) ${originalTitle}`;
        }
    }
    
    // קבלת היסטוריה
    getHistory(unreadOnly = false) {
        const history = this.notificationQueue || [];
        return unreadOnly ? history.filter(n => !n.read) : history;
    }
    
    // סימון כנקרא
    markAsRead(timestamp) {
        const notification = this.notificationQueue.find(n => n.timestamp === timestamp);
        if (notification) {
            notification.read = true;
            localStorage.setItem('notification-history', JSON.stringify(this.notificationQueue));
            this.updateBadge();
        }
    }
    
    // סימון הכל כנקרא
    markAllAsRead() {
        this.notificationQueue.forEach(n => n.read = true);
        localStorage.setItem('notification-history', JSON.stringify(this.notificationQueue));
        this.updateBadge();
    }
    
    // ניקוי היסטוריה
    clearHistory() {
        this.notificationQueue = [];
        localStorage.removeItem('notification-history');
        this.updateBadge();
    }
    
    // טיפול בעדכון Service Worker
    handleSWUpdate() {
        if (confirm('עדכון חדש זמין. לרענן את הדף?')) {
            window.location.reload();
        }
    }
    
    // בדיקת תמיכה בהתראות
    async testNotification() {
        console.log('🧪 Testing notifications...');
        
        const tests = {
            'Browser Support': this.isSupported,
            'Permission Status': this.permission,
            'Service Worker': !!this.swRegistration,
            'Push Subscription': !!this.subscription
        };
        
        console.table(tests);
        
        if (this.permission === 'granted') {
            await this.showNotification('התראת בדיקה 🔔', {
                body: 'אם אתה רואה את זה, ההתראות עובדות!',
                tag: 'test',
                data: { test: true }
            });
        }
        
        return tests;
    }
    
    // הודעות UI
    showSuccessMessage(message) {
        this.showToast(message, 'success');
    }
    
    showErrorMessage(message) {
        this.showToast(message, 'error');
    }
    
    showPermissionDeniedMessage() {
        this.showToast('ההתראות חסומות. יש לאפשר בהגדרות הדפדפן', 'warning');
    }
    
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `notification-toast notification-toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                           type === 'error' ? 'exclamation-circle' : 
                           type === 'warning' ? 'exclamation-triangle' : 
                           'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // סגנונות
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            transition: transform 0.3s ease;
            min-width: 250px;
            max-width: 500px;
            border-right: 4px solid ${
                type === 'success' ? '#28a745' :
                type === 'error' ? '#dc3545' :
                type === 'warning' ? '#ffc107' :
                '#17a2b8'
            };
        `;
        
        document.body.appendChild(toast);
        
        // אנימציה
        setTimeout(() => {
            toast.style.transform = 'translateX(-50%) translateY(0)';
        }, 100);
        
        // הסרה
        setTimeout(() => {
            toast.style.transform = 'translateX(-50%) translateY(100px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // פונקציות עזר
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
    
    // ניקוי
    destroy() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        
        this.listeners.clear();
        this.notificationQueue = [];
    }
}

// יצירת instance גלובלי
window.pushManager = new PushNotificationManager();

// חשיפת API פשוט
window.PushNotifications = {
    // בקשת הרשאה
    requestPermission: () => window.pushManager.requestPermission(),
    
    // שליחת התראה
    show: (title, options) => window.pushManager.showNotification(title, options),
    
    // האזנה לאירועים
    on: (event, callback) => window.pushManager.on(event, callback),
    off: (event, callback) => window.pushManager.off(event, callback),
    
    // היסטוריה
    getHistory: (unreadOnly) => window.pushManager.getHistory(unreadOnly),
    markAsRead: (timestamp) => window.pushManager.markAsRead(timestamp),
    markAllAsRead: () => window.pushManager.markAllAsRead(),
    clearHistory: () => window.pushManager.clearHistory(),
    
    // בדיקה
    test: () => window.pushManager.testNotification(),
    
    // סטטוס
    getStatus: () => ({
        supported: window.pushManager.isSupported,
        permission: window.pushManager.permission,
        subscribed: !!window.pushManager.subscription
    })
};

// אתחול אוטומטי כשהדף נטען
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('🔔 Push Notifications Manager loaded');
    });
} else {
    console.log('🔔 Push Notifications Manager loaded');
}