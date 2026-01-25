/**
 * PopupAPI - API לתקשורת בין התוכן לבין הפופ-אפ
 * תומך גם ב-iframe (postMessage) וגם ב-HTML ישיר
 * @version 1.0.1
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
            }
        }
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

        // החלת הגדרות נושא אם התקבלו (בטעינה או בשינוי נושא)
        if ((event === 'loaded' || event === 'themeChanged') && eventData?.theme) {
            this.applyTheme(eventData.theme);
        }

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
    }

    /**
     * החלת הגדרות נושא על ה-iframe
     */
    static applyTheme(theme) {
        if (!theme) return;

        try {
            const root = document.documentElement;
            const body = document.body;

            // הגדרת attributes
            root.setAttribute('data-theme', theme.dataTheme);
            root.setAttribute('data-color-scheme', theme.colorScheme);
            body.setAttribute('data-theme', theme.dataTheme);
            body.setAttribute('data-color-scheme', theme.colorScheme);

            // הגדרת classes
            body.classList.remove('dark-theme', 'light-theme');
            body.classList.add(theme.dataTheme + '-theme');

            // הסרת color-scheme classes ישנים והוספת חדש
            const classesToRemove = [];
            body.classList.forEach(cls => {
                if (cls.startsWith('color-scheme-')) classesToRemove.push(cls);
            });
            classesToRemove.forEach(cls => body.classList.remove(cls));

            if (theme.classes && theme.classes.colorScheme) {
                body.classList.add(theme.classes.colorScheme);
            }

            // הגדרת CSS Variables ישירות
            if (theme.cssVars) {
                for (const [key, value] of Object.entries(theme.cssVars)) {
                    if (value) {
                        const cssVarName = '--' + key.replace(/([A-Z])/g, '-$1').toLowerCase();
                        root.style.setProperty(cssVarName, value);
                    }
                }
            }
        } catch (err) {
            console.error('[PopupAPI] Error in applyTheme:', err);
        }
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

// Fallback: אם ב-iframe, נסה לקרוא את הנושא ישירות מההורה
if (window.self !== window.top) {
    setTimeout(() => {
        try {
            const parentRoot = window.parent.document.documentElement;
            const parentBody = window.parent.document.body;

            const theme = {
                dataTheme: parentRoot.getAttribute('data-theme') || 'light',
                colorScheme: parentRoot.getAttribute('data-color-scheme') || 'purple',
                classes: {
                    colorScheme: Array.from(parentBody.classList).find(c => c.startsWith('color-scheme-')) || ''
                },
                cssVars: {
                    primaryColor: getComputedStyle(parentRoot).getPropertyValue('--primary-color').trim(),
                    primaryDark: getComputedStyle(parentRoot).getPropertyValue('--primary-dark').trim(),
                    bgPrimary: getComputedStyle(parentRoot).getPropertyValue('--bg-primary').trim(),
                    bgSecondary: getComputedStyle(parentRoot).getPropertyValue('--bg-secondary').trim(),
                    bgTertiary: getComputedStyle(parentRoot).getPropertyValue('--bg-tertiary').trim(),
                    textPrimary: getComputedStyle(parentRoot).getPropertyValue('--text-primary').trim(),
                    textSecondary: getComputedStyle(parentRoot).getPropertyValue('--text-secondary').trim(),
                    textMuted: getComputedStyle(parentRoot).getPropertyValue('--text-muted').trim(),
                    borderColor: getComputedStyle(parentRoot).getPropertyValue('--border-color').trim()
                }
            };

            PopupAPI.applyTheme(theme);
        } catch (e) {
            // CORS error - parent not accessible
        }
    }, 100);
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
