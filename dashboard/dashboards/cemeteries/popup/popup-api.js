/**
 * PopupAPI - API לתקשורת בין התוכן לבין הפופ-אפ
 * תומך גם ב-iframe (postMessage) וגם ב-HTML ישיר
 * @version 1.0.0
 */

class PopupAPI {
    static popupId = null;
    static listeners = new Map();
    static isInIframe = window.self !== window.top;
    static isDetached = window.opener !== null;

    /**
     * אתחול ה-API
     * מזהה אוטומטית את ה-popup ID מה-URL או מהקונטקסט
     */
    static init() {
        // ניסיון לזהות popup ID מה-URL
        const urlParams = new URLSearchParams(window.location.search);
        this.popupId = urlParams.get('popupId');

        // אם אנחנו ב-iframe, האזן להודעות מהפופ-אפ
        if (this.isInIframe) {
            window.addEventListener('message', (e) => this.handleMessage(e));
        }

        // אם זה חלון מנותק, טען את המצב
        if (this.isDetached) {
            this.restoreDetachedState();
        }

        console.log(`[PopupAPI] Initialized - ID: ${this.popupId}, InIframe: ${this.isInIframe}, Detached: ${this.isDetached}`);
    }

    /**
     * שליחת פעולה לפופ-אפ
     * @param {string} action - שם הפעולה
     * @param {object} payload - נתונים
     */
    static send(action, payload = {}) {
        if (!this.popupId) {
            console.warn('[PopupAPI] No popup ID - call init() first or provide popupId in URL');
            return;
        }

        const message = {
            type: 'popup-api',
            popupId: this.popupId,
            action,
            payload
        };

        if (this.isInIframe) {
            // שלח postMessage להורה
            window.parent.postMessage(message, '*');
        } else if (this.isDetached && window.opener) {
            // שלח לחלון הפותח
            window.opener.postMessage(message, '*');
        } else {
            // גישה ישירה (HTML בתוך הפופ-אפ)
            const popup = window.PopupManager?.get(this.popupId);
            if (popup) {
                popup.handleMessage({ data: message });
            } else {
                console.warn('[PopupAPI] Cannot send - popup not found');
            }
        }

        console.log(`[PopupAPI] Sent: ${action}`, payload);
    }

    /**
     * פעולות נפוצות - shortcuts
     */
    static setTitle(title) {
        this.send('setTitle', { title });
    }

    static resize(width, height) {
        this.send('resize', { width, height });
    }

    static minimize() {
        this.send('minimize');
    }

    static maximize() {
        this.send('maximize');
    }

    static restore() {
        this.send('restore');
    }

    static close() {
        this.send('close');
    }

    static detach() {
        this.send('detach');
    }

    /**
     * האזנה לאירועים מהפופ-אפ
     * @param {string} event - שם האירוע (loaded, minimized, maximized, restored, closing, detached)
     * @param {function} callback - פונקציית callback
     */
    static on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);

        // האזנה גם ל-custom events (אם HTML ישיר)
        if (!this.isInIframe) {
            document.addEventListener(`popup-${event}`, (e) => {
                callback(e.detail.data);
            });
        }

        console.log(`[PopupAPI] Listening to: ${event}`);
    }

    /**
     * הסרת listener
     */
    static off(event, callback) {
        if (!this.listeners.has(event)) return;

        const listeners = this.listeners.get(event);
        const index = listeners.indexOf(callback);
        if (index > -1) {
            listeners.splice(index, 1);
        }
    }

    /**
     * טיפול בהודעות מהפופ-אפ
     */
    static handleMessage(e) {
        const data = e.data;

        if (data.type !== 'popup-event') return;
        if (data.popupId !== this.popupId) return;

        const { event, data: eventData } = data;

        // קריאה ל-listeners רשומים
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(eventData);
                } catch (err) {
                    console.error(`[PopupAPI] Error in listener for ${event}:`, err);
                }
            });
        }

        console.log(`[PopupAPI] Received event: ${event}`, eventData);
    }

    /**
     * שחזור מצב לחלון מנותק
     */
    static restoreDetachedState() {
        try {
            const stateKey = `popup-detached-${this.popupId}`;
            const stateJSON = localStorage.getItem(stateKey);

            if (stateJSON) {
                const state = JSON.parse(stateJSON);
                console.log('[PopupAPI] Restored detached state:', state);

                // שחזור גלילה
                if (state.state?.scrollPosition) {
                    window.scrollTo(state.state.scrollPosition.x, state.state.scrollPosition.y);
                }

                // ניקוי localStorage
                localStorage.removeItem(stateKey);

                return state;
            }
        } catch (e) {
            console.error('[PopupAPI] Failed to restore detached state:', e);
        }
        return null;
    }

    /**
     * בדיקה אם התוכן נמצא בפופ-אפ
     */
    static isInPopup() {
        return this.popupId !== null;
    }

    /**
     * קבלת מידע על הפופ-אפ
     */
    static getInfo() {
        return {
            popupId: this.popupId,
            isInIframe: this.isInIframe,
            isDetached: this.isDetached,
            isInPopup: this.isInPopup()
        };
    }
}

/**
 * Helper Functions לנוחות
 */

// אתחול אוטומטי כש-DOM מוכן
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PopupAPI.init());
} else {
    PopupAPI.init();
}

// Export
window.PopupAPI = PopupAPI;

// Shortcuts גלובליים (אופציונלי)
window.popupSetTitle = (title) => PopupAPI.setTitle(title);
window.popupResize = (width, height) => PopupAPI.resize(width, height);
window.popupMinimize = () => PopupAPI.minimize();
window.popupMaximize = () => PopupAPI.maximize();
window.popupRestore = () => PopupAPI.restore();
window.popupClose = () => PopupAPI.close();
window.popupDetach = () => PopupAPI.detach();
