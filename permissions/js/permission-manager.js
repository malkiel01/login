/**
 * Permissions Manager - JavaScript
 * permissions/js/permissions-manager.js
 */

class PermissionsManager {
    constructor(options = {}) {
        this.options = {
            autoCheck: true,
            checkInterval: 60000, // בדיקה כל דקה
            showBanners: true,
            apiEndpoint: '/permissions/api/',
            debug: false,
            ...options
        };

        // סוגי הרשאות
        this.TYPES = {
            NOTIFICATION: 'notification',
            PUSH: 'push',
            CAMERA: 'camera',
            MICROPHONE: 'microphone',
            LOCATION: 'geolocation',
            STORAGE: 'persistent-storage',
            CLIPBOARD: 'clipboard-read',
            BACKGROUND_SYNC: 'background-sync',
            BACKGROUND_FETCH: 'background-fetch',
            MIDI: 'midi',
            BLUETOOTH: 'bluetooth',
            ACCELEROMETER: 'accelerometer',
            GYROSCOPE: 'gyroscope',
            MAGNETOMETER: 'magnetometer',
            AMBIENT_LIGHT: 'ambient-light-sensor'
        };

        // מצבי הרשאות
        this.STATES = {
            GRANTED: 'granted',
            DENIED: 'denied',
            PROMPT: 'prompt',
            DEFAULT: 'default',
            BLOCKED: 'blocked',
            NOT_SUPPORTED: 'not_supported'
        };

        // מצב נוכחי
        this.permissions = {};
        this.banners = new Map();
        this.callbacks = new Map();
        
        // אתחול
        this.init();
    }

    /**
     * אתחול
     */
    async init() {
        this.log('Initializing Permissions Manager...');
        
        // בדיקת תמיכה
        if (!this.checkBrowserSupport()) {
            console.warn('Browser does not support Permissions API');
        }

        // בדיקה ראשונית
        if (this.options.autoCheck) {
            await this.checkAllPermissions();
        }

        // הפעלת בדיקה מחזורית
        if (this.options.checkInterval > 0) {
            this.startPeriodicCheck();
        }

        // האזנה לשינויים
        this.setupListeners();
        
        this.log('Permissions Manager initialized');
    }

    /**
     * בדיקת כל ההרשאות
     */
    async checkAllPermissions() {
        const results = {};

        for (const [key, type] of Object.entries(this.TYPES)) {
            results[type] = await this.checkPermission(type);
        }

        this.permissions = results;
        this.onPermissionsUpdated(results);
        
        return results;
    }

    /**
     * בדיקת הרשאה בודדת
     */
    async checkPermission(type) {
        const result = {
            type,
            status: this.STATES.NOT_SUPPORTED,
            timestamp: Date.now(),
            browser_support: false,
            api_support: false
        };

        try {
            // בדיקת תמיכה
            if (!navigator.permissions) {
                result.status = this.STATES.NOT_SUPPORTED;
                return result;
            }

            // בדיקות מיוחדות לסוגים שונים
            switch (type) {
                case this.TYPES.NOTIFICATION:
                    result.status = await this.checkNotificationPermission();
                    result.browser_support = 'Notification' in window;
                    result.api_support = true;
                    break;

                case this.TYPES.PUSH:
                    result.status = await this.checkPushPermission();
                    result.browser_support = 'PushManager' in window;
                    result.api_support = 'serviceWorker' in navigator;
                    break;

                case this.TYPES.CAMERA:
                case this.TYPES.MICROPHONE:
                    result.status = await this.checkMediaPermission(type);
                    result.browser_support = 'mediaDevices' in navigator;
                    result.api_support = true;
                    break;

                case this.TYPES.LOCATION:
                    result.status = await this.checkLocationPermission();
                    result.browser_support = 'geolocation' in navigator;
                    result.api_support = true;
                    break;

                case this.TYPES.STORAGE:
                    result.status = await this.checkStoragePermission();
                    result.browser_support = 'storage' in navigator && 'persist' in navigator.storage;
                    result.api_support = true;
                    break;

                case this.TYPES.CLIPBOARD:
                    result.status = await this.checkClipboardPermission();
                    result.browser_support = 'clipboard' in navigator;
                    result.api_support = true;
                    break;

                case this.TYPES.BACKGROUND_SYNC:
                    result.status = await this.checkBackgroundSyncPermission();
                    result.browser_support = 'sync' in ServiceWorkerRegistration.prototype;
                    result.api_support = 'serviceWorker' in navigator;
                    break;

                default:
                    // נסה בדיקה גנרית
                    result.status = await this.checkGenericPermission(type);
            }

            // עדכון בשרת
            await this.updateServerStatus(type, result.status);

        } catch (error) {
            this.log(`Error checking permission ${type}:`, error);
            result.error = error.message;
        }

        return result;
    }

    /**
     * בקשת הרשאה
     */
    async requestPermission(type, options = {}) {
        this.log(`Requesting permission: ${type}`);
        
        const config = {
            showBanner: this.options.showBanners,
            showRationale: true,
            fallbackToSettings: true,
            ...options
        };

        try {
            // בדיקה אם אפשר לבקש
            const canRequest = await this.canRequestPermission(type);
            if (!canRequest.allowed) {
                if (config.fallbackToSettings) {
                    this.showSettingsInstructions(type, canRequest.reason);
                }
                return { success: false, reason: canRequest.reason };
            }

            // הצגת באנר הסבר
            if (config.showRationale) {
                const proceed = await this.showRationaleBanner(type);
                if (!proceed) {
                    return { success: false, reason: 'user_cancelled' };
                }
            }

            // בקשה ספציפית לסוג ההרשאה
            let result;
            switch (type) {
                case this.TYPES.NOTIFICATION:
                    result = await this.requestNotificationPermission();
                    break;
                case this.TYPES.PUSH:
                    result = await this.requestPushPermission();
                    break;
                case this.TYPES.CAMERA:
                case this.TYPES.MICROPHONE:
                    result = await this.requestMediaPermission(type);
                    break;
                case this.TYPES.LOCATION:
                    result = await this.requestLocationPermission();
                    break;
                case this.TYPES.STORAGE:
                    result = await this.requestStoragePermission();
                    break;
                default:
                    result = await this.requestGenericPermission(type);
            }

            // עדכון סטטוס
            await this.updateServerStatus(type, result.status);
            this.onPermissionChanged(type, result.status);

            return result;

        } catch (error) {
            this.log(`Error requesting permission ${type}:`, error);
            return { success: false, error: error.message };
        }
    }

    /**
     * בקשות ספציפיות להרשאות
     */
    
    async checkNotificationPermission() {
        if (!('Notification' in window)) {
            return this.STATES.NOT_SUPPORTED;
        }
        return Notification.permission;
    }

    async requestNotificationPermission() {
        const permission = await Notification.requestPermission();
        return { success: permission === 'granted', status: permission };
    }

    async checkPushPermission() {
        if (!('PushManager' in window)) {
            return this.STATES.NOT_SUPPORTED;
        }
        
        const reg = await navigator.serviceWorker.ready;
        const subscription = await reg.pushManager.getSubscription();
        
        if (subscription) {
            return this.STATES.GRANTED;
        }
        
        // בדוק הרשאת notification כבסיס
        const notificationStatus = await this.checkNotificationPermission();
        return notificationStatus === 'granted' ? this.STATES.PROMPT : notificationStatus;
    }

    async requestPushPermission() {
        // קודם צריך notification permission
        const notificationResult = await this.requestNotificationPermission();
        if (!notificationResult.success) {
            return notificationResult;
        }

        try {
            const reg = await navigator.serviceWorker.ready;
            const subscription = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.options.vapidPublicKey)
            });

            // שלח לשרת
            await this.sendSubscriptionToServer(subscription);
            
            return { success: true, status: this.STATES.GRANTED, subscription };
        } catch (error) {
            return { success: false, status: this.STATES.DENIED, error: error.message };
        }
    }

    async checkMediaPermission(type) {
        if (!navigator.mediaDevices) {
            return this.STATES.NOT_SUPPORTED;
        }

        try {
            const result = await navigator.permissions.query({ 
                name: type === this.TYPES.CAMERA ? 'camera' : 'microphone' 
            });
            return result.state;
        } catch {
            return this.STATES.PROMPT;
        }
    }

    async requestMediaPermission(type) {
        try {
            const constraints = type === this.TYPES.CAMERA 
                ? { video: true } 
                : { audio: true };
            
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // עצור את הסטרים מיד
            stream.getTracks().forEach(track => track.stop());
            
            return { success: true, status: this.STATES.GRANTED };
        } catch (error) {
            return { success: false, status: this.STATES.DENIED, error: error.message };
        }
    }

    async checkLocationPermission() {
        if (!navigator.geolocation) {
            return this.STATES.NOT_SUPPORTED;
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });
            return result.state;
        } catch {
            return this.STATES.PROMPT;
        }
    }

    async requestLocationPermission() {
        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({ success: true, status: this.STATES.GRANTED, position });
                },
                (error) => {
                    const status = error.code === 1 ? this.STATES.DENIED : this.STATES.PROMPT;
                    resolve({ success: false, status, error: error.message });
                },
                { timeout: 10000 }
            );
        });
    }

    async checkStoragePermission() {
        if (!navigator.storage || !navigator.storage.persist) {
            return this.STATES.NOT_SUPPORTED;
        }

        const isPersisted = await navigator.storage.persisted();
        return isPersisted ? this.STATES.GRANTED : this.STATES.PROMPT;
    }

    async requestStoragePermission() {
        try {
            const isPersisted = await navigator.storage.persist();
            return { 
                success: isPersisted, 
                status: isPersisted ? this.STATES.GRANTED : this.STATES.DENIED 
            };
        } catch (error) {
            return { success: false, status: this.STATES.DENIED, error: error.message };
        }
    }

    async checkClipboardPermission() {
        if (!navigator.clipboard) {
            return this.STATES.NOT_SUPPORTED;
        }

        try {
            const result = await navigator.permissions.query({ name: 'clipboard-read' });
            return result.state;
        } catch {
            return this.STATES.PROMPT;
        }
    }

    async checkBackgroundSyncPermission() {
        if (!('sync' in ServiceWorkerRegistration.prototype)) {
            return this.STATES.NOT_SUPPORTED;
        }

        try {
            const result = await navigator.permissions.query({ name: 'background-sync' });
            return result.state;
        } catch {
            // ברוב הדפדפנים לא נתמך בדיקה ישירה
            return this.STATES.PROMPT;
        }
    }

    async checkGenericPermission(type) {
        try {
            const result = await navigator.permissions.query({ name: type });
            return result.state;
        } catch {
            return this.STATES.NOT_SUPPORTED;
        }
    }

    async requestGenericPermission(type) {
        return { success: false, status: this.STATES.NOT_SUPPORTED };
    }

    /**
     * האם אפשר לבקש הרשאה?
     */
    async canRequestPermission(type) {
        // בדוק בשרת אם ההרשאה נחסמה
        try {
            const response = await fetch(`${this.options.apiEndpoint}check-status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type })
            });
            
            const data = await response.json();
            
            if (data.blocked) {
                return { allowed: false, reason: 'blocked_by_user' };
            }
            
            if (data.denied_count >= 3) {
                return { allowed: false, reason: 'too_many_denials' };
            }
            
            if (data.last_prompted) {
                const hoursSince = (Date.now() - new Date(data.last_prompted)) / (1000 * 60 * 60);
                if (hoursSince < 24) {
                    return { allowed: false, reason: 'too_soon', hours_remaining: 24 - hoursSince };
                }
            }
            
        } catch (error) {
            this.log('Error checking server status:', error);
        }

        return { allowed: true };
    }

    /**
     * עדכון סטטוס בשרת
     */
    async updateServerStatus(type, status) {
        try {
            await fetch(`${this.options.apiEndpoint}update-status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, status, timestamp: Date.now() })
            });
        } catch (error) {
            this.log('Error updating server status:', error);
        }
    }

    /**
     * UI - באנרים ומודלים
     */
    
    async showRationaleBanner(type) {
        return new Promise((resolve) => {
            const banner = this.createRationaleBanner(type, (accepted) => {
                banner.remove();
                resolve(accepted);
            });
            
            document.body.appendChild(banner);
            this.animateBanner(banner, 'show');
        });
    }

    createRationaleBanner(type, callback) {
        const info = this.getPermissionInfo(type);
        
        const banner = document.createElement('div');
        banner.className = 'permission-banner';
        banner.innerHTML = `
            <div class="permission-banner-content">
                <div class="permission-icon">${info.icon}</div>
                <div class="permission-text">
                    <h3>${info.title}</h3>
                    <p>${info.rationale}</p>
                </div>
                <div class="permission-actions">
                    <button class="btn-deny">לא עכשיו</button>
                    <button class="btn-allow">אפשר</button>
                </div>
            </div>
        `;

        banner.querySelector('.btn-allow').onclick = () => callback(true);
        banner.querySelector('.btn-deny').onclick = () => callback(false);

        return banner;
    }

    showSettingsInstructions(type, reason) {
        const info = this.getPermissionInfo(type);
        const instructions = this.getSettingsInstructions();
        
        const modal = document.createElement('div');
        modal.className = 'permission-modal';
        modal.innerHTML = `
            <div class="permission-modal-content">
                <h2>הרשאת ${info.title} נחסמה</h2>
                <p class="reason">${this.getBlockedReasonText(reason)}</p>
                
                <div class="instructions">
                    <h3>כדי להפעיל את ההרשאה:</h3>
                    ${instructions}
                </div>
                
                <button class="btn-close">הבנתי</button>
            </div>
        `;

        modal.querySelector('.btn-close').onclick = () => modal.remove();
        document.body.appendChild(modal);
    }

    getSettingsInstructions() {
        const browser = this.detectBrowser();
        
        const instructions = {
            chrome: `
                <ol>
                    <li>לחץ על האייקון 🔒 בשורת הכתובת</li>
                    <li>לחץ על "הגדרות אתר"</li>
                    <li>מצא את ההרשאה הרצויה</li>
                    <li>שנה ל"אפשר"</li>
                </ol>
            `,
            firefox: `
                <ol>
                    <li>לחץ על האייקון 🔒 בשורת הכתובת</li>
                    <li>לחץ על החץ ליד ההרשאה</li>
                    <li>בחר "אפשר"</li>
                </ol>
            `,
            safari: `
                <ol>
                    <li>פתח העדפות > אתרים</li>
                    <li>בחר את סוג ההרשאה מהרשימה</li>
                    <li>מצא את האתר</li>
                    <li>שנה ל"אפשר"</li>
                </ol>
            `,
            default: `
                <ol>
                    <li>פתח את הגדרות הדפדפן</li>
                    <li>חפש "הרשאות אתר"</li>
                    <li>מצא את האתר שלנו</li>
                    <li>הפעל את ההרשאה הרצויה</li>
                </ol>
            `
        };

        return instructions[browser] || instructions.default;
    }

    getBlockedReasonText(reason) {
        const reasons = {
            'blocked_by_user': 'חסמת את ההרשאה בעבר',
            'too_many_denials': 'ההרשאה נדחתה מספר פעמים',
            'too_soon': 'נסה שוב מאוחר יותר',
            'not_supported': 'הדפדפן לא תומך בהרשאה זו'
        };
        
        return reasons[reason] || 'ההרשאה לא זמינה';
    }

    getPermissionInfo(type) {
        const info = {
            [this.TYPES.NOTIFICATION]: {
                title: 'התראות',
                icon: '🔔',
                rationale: 'נשלח לך התראות חשובות על עדכונים ופעילויות'
            },
            [this.TYPES.PUSH]: {
                title: 'התראות Push',
                icon: '📨',
                rationale: 'נוכל לעדכן אותך גם כשהאתר סגור'
            },
            [this.TYPES.CAMERA]: {
                title: 'מצלמה',
                icon: '📷',
                rationale: 'נוכל לסרוק ברקודים ולצלם תמונות'
            },
            [this.TYPES.MICROPHONE]: {
                title: 'מיקרופון',
                icon: '🎤',
                rationale: 'נוכל להקליט הודעות קוליות'
            },
            [this.TYPES.LOCATION]: {
                title: 'מיקום',
                icon: '📍',
                rationale: 'נציג לך חנויות קרובות ומידע מותאם למיקומך'
            },
            [this.TYPES.STORAGE]: {
                title: 'אחסון',
                icon: '💾',
                rationale: 'נשמור את הנתונים שלך בצורה בטוחה'
            }
        };

        return info[type] || { title: type, icon: '❓', rationale: 'נצטרך הרשאה זו לתפקוד מיטבי' };
    }

    /**
     * כלי עזר
     */
    
    detectBrowser() {
        const ua = navigator.userAgent;
        if (ua.includes('Chrome')) return 'chrome';
        if (ua.includes('Firefox')) return 'firefox';
        if (ua.includes('Safari')) return 'safari';
        return 'default';
    }

    animateBanner(element, action) {
        if (action === 'show') {
            element.style.display = 'block';
            requestAnimationFrame(() => {
                element.classList.add('show');
            });
        } else {
            element.classList.remove('show');
            setTimeout(() => {
                element.style.display = 'none';
            }, 300);
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async sendSubscriptionToServer(subscription) {
        await fetch(`${this.options.apiEndpoint}save-subscription.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subscription)
        });
    }

    /**
     * Event Handlers
     */
    
    setupListeners() {
        // האזנה לשינויי הרשאות
        if (navigator.permissions) {
            Object.values(this.TYPES).forEach(type => {
                this.listenToPermissionChanges(type);
            });
        }

        // האזנה לשינוי focus
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.options.autoCheck) {
                this.checkAllPermissions();
            }
        });
    }

    async listenToPermissionChanges(type) {
        try {
            const permission = await navigator.permissions.query({ name: type });
            permission.addEventListener('change', () => {
                this.checkPermission(type).then(result => {
                    this.onPermissionChanged(type, result.status);
                });
            });
        } catch {
            // לא נתמך
        }
    }

    startPeriodicCheck() {
        setInterval(() => {
            this.checkAllPermissions();
        }, this.options.checkInterval);
    }

    onPermissionsUpdated(permissions) {
        this.log('Permissions updated:', permissions);
        
        // הפעל callbacks
        if (this.callbacks.has('update')) {
            this.callbacks.get('update').forEach(cb => cb(permissions));
        }
    }

    onPermissionChanged(type, status) {
        this.log(`Permission ${type} changed to ${status}`);
        
        // הפעל callbacks
        if (this.callbacks.has('change')) {
            this.callbacks.get('change').forEach(cb => cb(type, status));
        }
    }

    /**
     * Public API
     */
    
    on(event, callback) {
        if (!this.callbacks.has(event)) {
            this.callbacks.set(event, []);
        }
        this.callbacks.get(event).push(callback);
    }

    off(event, callback) {
        if (this.callbacks.has(event)) {
            const callbacks = this.callbacks.get(event);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        }
    }

    getStatus(type) {
        return this.permissions[type] || null;
    }

    getAllStatuses() {
        return { ...this.permissions };
    }

    async requestMultiple(types) {
        const results = {};
        
        for (const type of types) {
            results[type] = await this.requestPermission(type);
        }
        
        return results;
    }

    checkBrowserSupport() {
        return 'permissions' in navigator;
    }

    log(...args) {
        if (this.options.debug) {
            console.log('[PermissionsManager]', ...args);
        }
    }
}

// יצוא גלובלי
window.PermissionsManager = PermissionsManager;

// אתחול אוטומטי
document.addEventListener('DOMContentLoaded', () => {
    window.permissionsManager = new PermissionsManager({
        autoCheck: true,
        showBanners: true,
        debug: true
    });
});