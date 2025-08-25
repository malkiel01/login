// js/notification-listener.js - מאזין התראות אוטומטי

class NotificationListener {
    constructor() {
        this.checkInterval = 3000; // כל 3 שניות
        this.intervalId = null;
        this.isActive = true;
        this.lastCheck = Date.now();
        this.notificationQueue = [];
        this.hasPermission = false;
        this.audioEnabled = true;
        this.vibrationEnabled = true;
        
        // צלילי התראה
        this.notificationSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLYiTcIGWi77eeeSxEMUqzn1LptIAUmcMu74Jm9aRUKFWC76K2oWRI=');
        
        this.init();
    }
    
    async init() {
        console.log('🔔 Initializing Notification Listener...');
        
        // בדוק הרשאות
        await this.checkPermission();
        
        // התחל מאזין
        this.startListening();
        
        // האזן לאירועי דף
        this.setupEventListeners();
        
        // רישום Service Worker אם צריך
        this.registerServiceWorker();
    }
    
    async checkPermission() {
        if (!('Notification' in window)) {
            console.warn('Browser does not support notifications');
            return false;
        }
        
        const permission = Notification.permission;
        
        if (permission === 'default') {
            // בקש הרשאה באופן עדין
            this.showPermissionPrompt();
        } else if (permission === 'granted') {
            this.hasPermission = true;
            console.log('✅ Notification permission granted');
        } else {
            console.warn('❌ Notification permission denied');
        }
        
        return this.hasPermission;
    }
    
    showPermissionPrompt() {
        // אם כבר הוצג הפרומפט בסשן הזה, אל תציג שוב
        if (sessionStorage.getItem('notification-prompt-shown')) {
            return;
        }
        
        // צור באנר עדין
        const banner = document.createElement('div');
        banner.className = 'notification-permission-banner';
        banner.innerHTML = `
            <div class="npb-content">
                <div class="npb-icon">🔔</div>
                <div class="npb-text">
                    <div class="npb-title">קבל התראות בזמן אמת</div>
                    <div class="npb-subtitle">הישאר מעודכן על פעילות בקבוצות שלך</div>
                </div>
                <button class="npb-button" onclick="notificationListener.requestPermission()">
                    אפשר התראות
                </button>
                <button class="npb-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // הוסף סגנונות
        if (!document.getElementById('notification-banner-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-banner-styles';
            styles.textContent = `
                .notification-permission-banner {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    padding: 15px;
                    max-width: 380px;
                    z-index: 9999;
                    animation: slideInUp 0.5s ease;
                    direction: rtl;
                }
                
                .npb-content {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                
                .npb-icon {
                    font-size: 32px;
                    flex-shrink: 0;
                }
                
                .npb-text {
                    flex: 1;
                }
                
                .npb-title {
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 2px;
                }
                
                .npb-subtitle {
                    font-size: 13px;
                    color: #666;
                }
                
                .npb-button {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 14px;
                    cursor: pointer;
                    white-space: nowrap;
                }
                
                .npb-button:hover {
                    transform: scale(1.05);
                }
                
                .npb-close {
                    position: absolute;
                    top: 5px;
                    left: 10px;
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #999;
                }
                
                @keyframes slideInUp {
                    from {
                        transform: translateY(100px);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                
                /* Toast notifications */
                .notification-toast {
                    position: fixed;
                    top: 80px;
                    right: 20px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    padding: 15px;
                    max-width: 380px;
                    z-index: 10000;
                    animation: slideInRight 0.5s ease;
                    direction: rtl;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                
                .notification-toast:hover {
                    transform: scale(1.02);
                    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
                }
                
                .notification-toast.hiding {
                    animation: slideOutRight 0.5s ease;
                }
                
                .nt-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 8px;
                }
                
                .nt-icon {
                    width: 40px;
                    height: 40px;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                }
                
                .nt-title {
                    flex: 1;
                    font-weight: 600;
                    color: #333;
                }
                
                .nt-body {
                    color: #666;
                    font-size: 14px;
                    line-height: 1.4;
                }
                
                .nt-time {
                    font-size: 11px;
                    color: #999;
                    margin-top: 5px;
                }
                
                @keyframes slideInRight {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(banner);
        sessionStorage.setItem('notification-prompt-shown', 'true');
        
        // הסר אחרי 30 שניות
        setTimeout(() => {
            if (banner.parentNode) {
                banner.remove();
            }
        }, 30000);
    }
    
    async requestPermission() {
        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                this.hasPermission = true;
                console.log('✅ Permission granted!');
                
                // הסר את הבאנר
                const banner = document.querySelector('.notification-permission-banner');
                if (banner) banner.remove();
                
                // הצג הודעת הצלחה
                this.showToast('התראות הופעלו! 🎉', 'מעכשיו תקבל עדכונים בזמן אמת');
                
                // התחל לבדוק התראות מיד
                this.checkForNotifications();
                
            } else if (permission === 'denied') {
                console.warn('Permission denied');
                alert('ההרשאה נדחתה. כדי לקבל התראות, יש לאפשר בהגדרות הדפדפן');
            }
        } catch (error) {
            console.error('Error requesting permission:', error);
        }
    }
    
    startListening() {
        console.log('🎧 Starting notification listener...');
        
        // בדוק התראות מיד
        this.checkForNotifications();
        
        // הגדר בדיקה תקופתית
        this.intervalId = setInterval(() => {
            if (this.isActive) {
                this.checkForNotifications();
            }
        }, this.checkInterval);
    }
    
    async checkForNotifications() {
        try {
            const response = await fetch('/login/api/notification-system.php?action=check', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.notifications && data.notifications.length > 0) {
                console.log(`📬 Found ${data.notifications.length} new notifications`);
                
                // הצג כל התראה
                for (const notification of data.notifications) {
                    await this.showNotification(notification);
                    
                    // השהייה קטנה בין התראות
                    await this.sleep(500);
                }
            }
            
            this.lastCheck = Date.now();
            
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }
    
    async showNotification(notification) {
        console.log('📢 Showing notification:', notification);
        
        // נסה להציג התראת דפדפן
        if (this.hasPermission && 'Notification' in window) {
            try {
                const browserNotification = new Notification(notification.title, {
                    body: notification.body,
                    icon: notification.icon || '/login/images/icons/android/android-launchericon-192-192.png',
                    badge: '/login/images/icons/android/android-launchericon-96-96.png',
                    tag: `notification-${notification.id}`,
                    requireInteraction: false,
                    silent: false,
                    vibrate: this.vibrationEnabled ? [200, 100, 200] : undefined,
                    data: notification.data
                });
                
                // לחיצה על ההתראה
                browserNotification.onclick = () => {
                    window.focus();
                    if (notification.url) {
                        window.location.href = notification.url;
                    }
                    browserNotification.close();
                    this.markAsRead(notification.id);
                };
                
                // השמע צליל
                if (this.audioEnabled) {
                    this.playSound();
                }
                
            } catch (error) {
                console.error('Error showing browser notification:', error);
                // אם נכשל, הצג toast
                this.showToast(notification.title, notification.body, notification);
            }
        } else {
            // הצג Toast notification בתוך הדף
            this.showToast(notification.title, notification.body, notification);
        }
    }
    
    showToast(title, body, notification = {}) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="nt-header">
                <div class="nt-icon">
                    ${this.getIconForType(notification.type)}
                </div>
                <div class="nt-title">${title}</div>
            </div>
            <div class="nt-body">${body}</div>
            <div class="nt-time">עכשיו</div>
        `;
        
        // חשב את המיקום לפי כמות ההתראות הקיימות
        const existingToasts = document.querySelectorAll('.notification-toast:not(.hiding)');
        const topOffset = 80 + (existingToasts.length * 120);
        toast.style.top = `${topOffset}px`;
        
        document.body.appendChild(toast);
        
        // לחיצה על ה-toast
        toast.onclick = () => {
            if (notification.url) {
                window.location.href = notification.url;
            }
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 500);
            
            if (notification.id) {
                this.markAsRead(notification.id);
            }
        };
        
        // השמע צליל
        if (this.audioEnabled) {
            this.playSound();
        }
        
        // רטט אם אפשר
        if (this.vibrationEnabled && 'vibrate' in navigator) {
            navigator.vibrate([200, 100, 200]);
        }
        
        // הסר אחרי 8 שניות
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 500);
            }
        }, 8000);
    }
    
    getIconForType(type) {
        const icons = {
            'group_invitation': '👥',
            'new_purchase': '🛒',
            'calculation_update': '💰',
            'member_joined': '🎉',
            'member_left': '👋',
            'test': '🧪',
            'default': '🔔'
        };
        
        return icons[type] || icons.default;
    }
    
    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            await fetch('/login/api/notification-system.php?action=mark-read', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            console.log(`✅ Marked notification ${notificationId} as read`);
            
        } catch (error) {
            console.error('Error marking as read:', error);
        }
    }
    
    playSound() {
        try {
            this.notificationSound.play().catch(e => {
                console.log('Could not play sound:', e);
            });
        } catch (error) {
            console.log('Sound playback error:', error);
        }
    }
    
    setupEventListeners() {
        // עצור בדיקות כשהטאב לא פעיל
        document.addEventListener('visibilitychange', () => {
            this.isActive = !document.hidden;
            
            if (this.isActive) {
                console.log('🔄 Tab active - resuming checks');
                this.checkForNotifications();
            } else {
                console.log('💤 Tab inactive - pausing checks');
            }
        });
        
        // בדוק מיד כשחוזרים לטאב
        window.addEventListener('focus', () => {
            console.log('👀 Window focused - checking notifications');
            this.checkForNotifications();
        });
        
        // נקה בעזיבת הדף
        window.addEventListener('beforeunload', () => {
            this.stopListening();
        });
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/login/service-worker.js');
                console.log('Service Worker registered:', registration);
                
                // הגדר handler להודעות מה-Service Worker
                navigator.serviceWorker.addEventListener('message', event => {
                    if (event.data && event.data.type === 'notification-click') {
                        console.log('Notification clicked via SW:', event.data);
                    }
                });
                
            } catch (error) {
                console.error('SW registration failed:', error);
            }
        }
    }
    
    stopListening() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            console.log('🛑 Stopped notification listener');
        }
    }
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    // API ציבורי
    setCheckInterval(interval) {
        this.checkInterval = interval;
        this.stopListening();
        this.startListening();
    }
    
    toggleSound() {
        this.audioEnabled = !this.audioEnabled;
        return this.audioEnabled;
    }
    
    toggleVibration() {
        this.vibrationEnabled = !this.vibrationEnabled;
        return this.vibrationEnabled;
    }
    
    async testNotification() {
        await fetch('/login/api/notification-system.php?action=test', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        console.log('📤 Test notification requested');
    }
}

// יצירת instance גלובלי
const notificationListener = new NotificationListener();

// חשוף ל-window לדיבוג
window.notificationListener = notificationListener;