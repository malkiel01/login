// js/pwa-notifications.js - מנהל התראות מאוחד ומשופר

class PWANotificationManager {
    constructor() {
        this.registration = null;
        this.isSupported = 'Notification' in window && 'serviceWorker' in navigator;
        this.debugMode = true;
        
        // אתחול
        this.init();
    }
    
    async init() {
        if (!this.isSupported) {
            console.log('PWA Notifications: Not supported in this browser');
            return;
        }
        
        try {
            // רישום Service Worker
            this.registration = await navigator.serviceWorker.register('/login/service-worker.js', {
                scope: '/login/'
            });
            
            console.log('PWA Notifications: Service Worker registered');
            
            // האזנה להודעות מה-Service Worker
            this.setupMessageListeners();
            
            // בדיקה אם זו התקנה חדשה
            this.checkForNewInstall();
            
        } catch (error) {
            console.error('PWA Notifications: Service Worker registration failed:', error);
        }
    }
    
    setupMessageListeners() {
        navigator.serviceWorker.addEventListener('message', async (event) => {
            console.log('PWA Notifications: Message from Service Worker:', event.data);
            
            switch (event.data.type) {
                case 'REQUEST_NOTIFICATION_PERMISSION':
                    await this.handlePermissionRequest();
                    break;
                    
                case 'SHOW_NOTIFICATION':
                    await this.showNotification(event.data.payload);
                    break;
                    
                default:
                    console.log('PWA Notifications: Unknown message type:', event.data.type);
            }
        });
    }
    
    // בדיקה אם זו התקנה חדשה או activation ראשון
    checkForNewInstall() {
        // בדוק אם זה הביקור הראשון אחרי התקנה
        const isFirstVisitAfterInstall = localStorage.getItem('pwa-first-visit') === null;
        
        if (isFirstVisitAfterInstall) {
            localStorage.setItem('pwa-first-visit', 'true');
            
            // המתן קצת ואז בדוק הרשאות
            setTimeout(() => {
                this.handlePermissionRequest();
            }, 2000);
        }
    }
    
    async handlePermissionRequest() {
        console.log('PWA Notifications: Handling permission request');
        
        const currentPermission = Notification.permission;
        
        if (currentPermission === 'default') {
            // הצג חלון בקשת הרשאה
            this.showPermissionDialog();
        } else if (currentPermission === 'granted') {
            console.log('PWA Notifications: Already granted');
            // שלח התראת ברוכים הבאים אם זה הביקור הראשון
            if (localStorage.getItem('welcome-notification-sent') !== 'true') {
                setTimeout(() => {
                    this.showWelcomeNotification();
                }, 1000);
            }
        } else {
            console.log('PWA Notifications: Permission denied');
        }
    }
    
    showPermissionDialog() {
        // בדוק אם כבר דחה בעבר
        const previouslyDismissed = localStorage.getItem('notifications-dismissed');
        const dismissedTime = localStorage.getItem('notifications-dismissed-time');
        
        if (previouslyDismissed && dismissedTime) {
            const daysPassed = (Date.now() - parseInt(dismissedTime)) / (1000 * 60 * 60 * 24);
            if (daysPassed < 7) {
                console.log('PWA Notifications: User dismissed recently, skipping');
                return;
            }
        }
        
        const modal = this.createPermissionModal();
        document.body.appendChild(modal);
        
        // הצג עם אנימציה
        requestAnimationFrame(() => {
            modal.classList.add('show');
        });
    }
    
    createPermissionModal() {
        const modal = document.createElement('div');
        modal.className = 'pwa-notification-modal';
        modal.innerHTML = `
            <div class="pwa-modal-backdrop"></div>
            <div class="pwa-modal-content">
                <div class="pwa-modal-header">
                    <div class="pwa-bell-icon">
                        <div class="bell-animation">🔔</div>
                    </div>
                    <h2>הפעל התראות</h2>
                    <p>קבל עדכונים מיידיים על פעילות בקבוצות שלך</p>
                </div>
                
                <div class="pwa-modal-body">
                    <div class="pwa-feature-list">
                        <div class="pwa-feature">
                            <div class="pwa-feature-icon">👥</div>
                            <div class="pwa-feature-text">
                                <strong>הזמנות לקבוצות</strong>
                                <span>קבל התראה כשמזמינים אותך להצטרף</span>
                            </div>
                        </div>
                        
                        <div class="pwa-feature">
                            <div class="pwa-feature-icon">🛒</div>
                            <div class="pwa-feature-text">
                                <strong>קניות חדשות</strong>
                                <span>דע מיד כשמישהו מוסיף קנייה בקבוצה</span>
                            </div>
                        </div>
                        
                        <div class="pwa-feature">
                            <div class="pwa-feature-icon">💰</div>
                            <div class="pwa-feature-text">
                                <strong>עדכוני חישובים</strong>
                                <span>קבל סיכום מעודכן של המצב הכספי</span>
                            </div>
                        </div>
                        
                        <div class="pwa-feature">
                            <div class="pwa-feature-icon">⚡</div>
                            <div class="pwa-feature-text">
                                <strong>עבודה אופליין</strong>
                                <span>האפליקציה תעבוד גם בלי אינטרנט</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pwa-modal-actions">
                        <button class="pwa-btn-allow" id="pwa-allow-btn">
                            <span>אפשר התראות</span>
                            <span class="btn-icon">✨</span>
                        </button>
                        <button class="pwa-btn-maybe" id="pwa-maybe-btn">
                            אולי מאוחר יותר
                        </button>
                    </div>
                </div>
                
                <div class="pwa-modal-footer">
                    <small>ניתן לשנות בכל עת בהגדרות הדפדפן</small>
                </div>
            </div>
        `;
        
        // הוסף סגנונות
        this.addModalStyles();
        
        // הוסף מאזינים
        this.attachModalListeners(modal);
        
        return modal;
    }
    
    attachModalListeners(modal) {
        const allowBtn = modal.querySelector('#pwa-allow-btn');
        const maybeBtn = modal.querySelector('#pwa-maybe-btn');
        
        allowBtn.onclick = async () => {
            try {
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    this.showToast('התראות הופעלו בהצלחה! 🎉', 'success');
                    localStorage.setItem('notifications-granted', 'true');
                    
                    // שלח התראת ברוכים הבאים
                    setTimeout(() => {
                        this.showWelcomeNotification();
                    }, 1500);
                    
                } else {
                    this.showToast('הרשאת התראות נדחתה 😔', 'error');
                    localStorage.setItem('notifications-dismissed', 'true');
                    localStorage.setItem('notifications-dismissed-time', Date.now().toString());
                }
                
                this.closeModal(modal);
                
            } catch (error) {
                console.error('PWA Notifications: Error requesting permission:', error);
                this.showToast('שגיאה בבקשת הרשאה', 'error');
                this.closeModal(modal);
            }
        };
        
        maybeBtn.onclick = () => {
            localStorage.setItem('notifications-dismissed', 'true');
            localStorage.setItem('notifications-dismissed-time', Date.now().toString());
            this.closeModal(modal);
        };
        
        // סגירה בלחיצה על הרקע
        modal.querySelector('.pwa-modal-backdrop').onclick = () => {
            this.closeModal(modal);
        };
    }
    
    closeModal(modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }, 300);
    }
    
    async showWelcomeNotification() {
        if (Notification.permission === 'granted') {
            try {
                const notification = new Notification('ברוכים הבאים! 🎉', {
                    body: 'האפליקציה מותקנת ומוכנה לשימוש. תקבל התראות על כל הפעילות בקבוצות שלך.',
                    icon: '/login/images/icons/android/android-launchericon-192-192.png',
                    badge: '/login/images/icons/android/android-launchericon-96-96.png',
                    tag: 'welcome',
                    dir: 'rtl',
                    lang: 'he',
                    vibrate: [200, 100, 200, 100, 200],
                    silent: false,
                    requireInteraction: false
                });
                
                // סמן שהתראת הברוכים הבאים נשלחה
                localStorage.setItem('welcome-notification-sent', 'true');
                
                // סגור אוטומטית אחרי 5 שניות
                setTimeout(() => {
                    notification.close();
                }, 5000);
                
                console.log('PWA Notifications: Welcome notification sent');
                
            } catch (error) {
                console.error('PWA Notifications: Error showing welcome notification:', error);
            }
        }
    }
    
    async showNotification(data) {
        if (Notification.permission === 'granted') {
            try {
                const notification = new Notification(data.title || 'התראה חדשה', {
                    body: data.body || '',
                    icon: data.icon || '/login/images/icons/android/android-launchericon-192-192.png',
                    badge: data.badge || '/login/images/icons/android/android-launchericon-96-96.png',
                    tag: data.tag || 'general',
                    dir: 'rtl',
                    lang: 'he',
                    vibrate: data.vibrate || [200, 100, 200],
                    data: data.data || {},
                    requireInteraction: data.requireInteraction || false
                });
                
                // אם יש URL, פתח אותו בלחיצה
                if (data.url) {
                    notification.onclick = () => {
                        window.open(data.url, '_blank');
                        notification.close();
                    };
                }
                
                return notification;
                
            } catch (error) {
                console.error('PWA Notifications: Error showing notification:', error);
                return null;
            }
        }
    }
    
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `pwa-toast pwa-toast-${type}`;
        
        const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        // הצג עם אנימציה
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
        
        // הסר אחרי 4 שניות
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 4000);
    }
    
    addModalStyles() {
        if (document.getElementById('pwa-notification-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'pwa-notification-styles';
        style.textContent = `
            .pwa-notification-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .pwa-notification-modal.show {
                opacity: 1;
                visibility: visible;
            }
            
            .pwa-modal-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(5px);
            }
            
            .pwa-modal-content {
                position: relative;
                background: white;
                border-radius: 20px;
                max-width: 480px;
                width: 90%;
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
                transform: scale(0.8) translateY(50px);
                transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                overflow: hidden;
            }
            
            .pwa-notification-modal.show .pwa-modal-content {
                transform: scale(1) translateY(0);
            }
            
            .pwa-modal-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 40px 30px 30px;
                text-align: center;
                color: white;
                position: relative;
            }
            
            .pwa-bell-icon {
                width: 100px;
                height: 100px;
                background: white;
                border-radius: 50%;
                margin: 0 auto 25px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                position: relative;
            }
            
            .bell-animation {
                font-size: 50px;
                animation: bellRing 3s ease-in-out infinite;
            }
            
            @keyframes bellRing {
                0%, 85%, 100% { transform: rotate(0deg); }
                5%, 15% { transform: rotate(15deg); }
                10%, 20% { transform: rotate(-15deg); }
                25%, 35% { transform: rotate(10deg); }
                30%, 40% { transform: rotate(-10deg); }
                45% { transform: rotate(0deg); }
            }
            
            .pwa-modal-header h2 {
                font-size: 28px;
                margin: 0 0 10px 0;
                font-weight: bold;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .pwa-modal-header p {
                font-size: 16px;
                margin: 0;
                opacity: 0.95;
                line-height: 1.5;
            }
            
            .pwa-modal-body {
                padding: 35px 30px;
            }
            
            .pwa-feature-list {
                margin-bottom: 30px;
            }
            
            .pwa-feature {
                display: flex;
                align-items: flex-start;
                gap: 20px;
                padding: 15px;
                background: #f8f9ff;
                border-radius: 15px;
                margin-bottom: 15px;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            
            .pwa-feature:hover {
                background: #f0f2ff;
                border-color: #667eea;
                transform: translateX(5px);
            }
            
            .pwa-feature-icon {
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                flex-shrink: 0;
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            }
            
            .pwa-feature-text {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .pwa-feature-text strong {
                font-size: 16px;
                color: #333;
                font-weight: 700;
            }
            
            .pwa-feature-text span {
                font-size: 14px;
                color: #666;
                line-height: 1.4;
            }
            
            .pwa-modal-actions {
                display: flex;
                gap: 15px;
            }
            
            .pwa-btn-allow {
                flex: 2;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 16px 24px;
                border-radius: 15px;
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
                position: relative;
                overflow: hidden;
            }
            
            .pwa-btn-allow:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
            }
            
            .pwa-btn-allow .btn-icon {
                animation: sparkle 2s ease-in-out infinite;
            }
            
            @keyframes sparkle {
                0%, 100% { transform: scale(1) rotate(0deg); }
                50% { transform: scale(1.2) rotate(180deg); }
            }
            
            .pwa-btn-maybe {
                flex: 1;
                background: #f8f9fa;
                color: #6c757d;
                border: 2px solid #e9ecef;
                padding: 16px 24px;
                border-radius: 15px;
                font-size: 16px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .pwa-btn-maybe:hover {
                background: #e9ecef;
                border-color: #dee2e6;
                color: #495057;
            }
            
            .pwa-modal-footer {
                padding: 20px 30px;
                background: #f8f9fa;
                text-align: center;
                border-top: 1px solid #e9ecef;
            }
            
            .pwa-modal-footer small {
                color: #6c757d;
                font-size: 13px;
            }
            
            /* Toast Notifications */
            .pwa-toast {
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                background: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                z-index: 10002;
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 600;
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                max-width: 90%;
            }
            
            .pwa-toast.show {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
            
            .pwa-toast-success {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
            }
            
            .pwa-toast-error {
                background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
                color: white;
            }
            
            .pwa-toast-info {
                background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
                color: white;
            }
            
            .toast-icon {
                font-size: 20px;
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
            }
            
            .toast-message {
                font-size: 15px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .pwa-modal-content {
                    width: 95%;
                    margin: 20px;
                }
                
                .pwa-modal-header {
                    padding: 30px 20px 25px;
                }
                
                .pwa-bell-icon {
                    width: 80px;
                    height: 80px;
                    margin-bottom: 20px;
                }
                
                .bell-animation {
                    font-size: 40px;
                }
                
                .pwa-modal-header h2 {
                    font-size: 24px;
                }
                
                .pwa-modal-body {
                    padding: 25px 20px;
                }
                
                .pwa-modal-actions {
                    flex-direction: column;
                    gap: 12px;
                }
                
                .pwa-btn-allow, .pwa-btn-maybe {
                    width: 100%;
                }
                
                .pwa-toast {
                    width: 90%;
                    max-width: 400px;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    // פונקציות ציבוריות
    async requestPermission() {
        return this.handlePermissionRequest();
    }
    
    async sendTestNotification() {
        return this.showNotification({
            title: 'התראת בדיקה 🧪',
            body: 'זוהי התראת בדיקה כדי לוודא שהמערכת עובדת',
            tag: 'test'
        });
    }
    
    getPermissionStatus() {
        return Notification.permission;
    }
    
    isInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    }
}

// יצירת אינסטנס גלובלי
window.pwaNotifications = new PWANotificationManager();

// חשוף פונקציות לדיבוג
window.debugPWANotifications = {
    requestPermission: () => window.pwaNotifications.requestPermission(),
    sendTest: () => window.pwaNotifications.sendTestNotification(),
    getStatus: () => window.pwaNotifications.getPermissionStatus(),
    isInstalled: () => window.pwaNotifications.isInstalled()
};

console.log('PWA Notifications Manager loaded successfully! 🔔');
console.log('Debug functions available in window.debugPWANotifications');