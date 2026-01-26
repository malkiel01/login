/**
 * Persistent Auth - מנגנון אימות עמיד
 * שילוב של Cookie + localStorage + IndexedDB
 * תומך ב-PWA וב-iOS
 *
 * @version 1.0.0
 * @requires indexed-db-auth.js
 */

class PersistentAuth {
    constructor() {
        this.apiUrl = '/auth/api/token-api.php';
        this.isInitialized = false;
        this.currentUser = null;
        this.initPromise = null;
    }

    /**
     * אתחול - בדיקת token קיים והתחברות אוטומטית
     * @returns {Promise<Object|null>} - פרטי המשתמש או null
     */
    async init() {
        if (this.initPromise) {
            return this.initPromise;
        }

        this.initPromise = this._doInit();
        return this.initPromise;
    }

    async _doInit() {
        console.log('[PersistentAuth] Initializing...');

        // וודא ש-AuthStorage מוכן
        if (window.authStorage) {
            await window.authStorage.waitForReady();
        }

        // נסה לשחזר session
        const userData = await this.validateStoredToken();

        if (userData) {
            this.currentUser = userData;
            this.isInitialized = true;
            console.log('[PersistentAuth] User restored:', userData.name);

            // בדוק אם צריך לרענן
            if (userData.should_refresh) {
                console.log('[PersistentAuth] Token needs refresh');
                this.refreshToken();
            }

            return userData;
        }

        this.isInitialized = true;
        console.log('[PersistentAuth] No valid token found');
        return null;
    }

    /**
     * התחברות עם credentials
     * נקרא אחרי login מוצלח מהשרת
     *
     * @param {Object} tokenData - נתוני ה-token מהשרת
     */
    async saveLogin(tokenData) {
        console.log('[PersistentAuth] Saving login data');

        const { token, refresh_token, expires, user_id } = tokenData;

        // שמור ב-IndexedDB (הכי עמיד)
        if (window.authStorage) {
            await window.authStorage.saveToken(
                token,
                refresh_token,
                user_id,
                new Date(expires)
            );
        }

        // שמור גם ב-localStorage כגיבוי
        try {
            localStorage.setItem('ck_auth_token', token);
            localStorage.setItem('ck_refresh_token', refresh_token);
            localStorage.setItem('ck_token_expires', expires.toString());
            localStorage.setItem('ck_user_id', user_id.toString());
        } catch (e) {
            console.warn('[PersistentAuth] localStorage save failed:', e);
        }

        // Cookie נשמר על ידי השרת
    }

    /**
     * בדיקת token שמור ואימות מול השרת
     * @returns {Promise<Object|null>}
     */
    async validateStoredToken() {
        // נסה לקבל token מכל המקורות
        const token = await this.getStoredToken();

        if (!token) {
            return null;
        }

        // אמת מול השרת
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'validate',
                    token: token
                }),
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                return {
                    ...data.user,
                    should_refresh: data.should_refresh,
                    expires_at: data.expires_at
                };
            }

            // Token לא תקף - נקה
            await this.clearAuth();
            return null;

        } catch (error) {
            console.error('[PersistentAuth] Validation error:', error);

            // במצב offline, נסה להשתמש בנתונים מקומיים
            if (!navigator.onLine) {
                return this.getOfflineUserData();
            }

            return null;
        }
    }

    /**
     * קבלת token שמור (מכל המקורות)
     * @returns {Promise<string|null>}
     */
    async getStoredToken() {
        // 1. נסה מ-Cookie
        const cookieToken = this.getCookie('auth_token');
        if (cookieToken) {
            console.log('[PersistentAuth] Found token in cookie');
            return cookieToken;
        }

        // 2. נסה מ-IndexedDB
        if (window.authStorage) {
            const dbData = await window.authStorage.getToken();
            if (dbData && dbData.token) {
                console.log('[PersistentAuth] Found token in IndexedDB');
                return dbData.token;
            }
        }

        // 3. נסה מ-localStorage
        const lsToken = localStorage.getItem('ck_auth_token');
        if (lsToken) {
            const expires = parseInt(localStorage.getItem('ck_token_expires') || '0');
            if (expires > Date.now()) {
                console.log('[PersistentAuth] Found token in localStorage');
                return lsToken;
            }
        }

        return null;
    }

    /**
     * קבלת refresh token
     * @returns {Promise<string|null>}
     */
    async getRefreshToken() {
        // מ-IndexedDB
        if (window.authStorage) {
            const dbData = await window.authStorage.getToken();
            if (dbData && dbData.refreshToken) {
                return dbData.refreshToken;
            }
        }

        // מ-localStorage
        return localStorage.getItem('ck_refresh_token');
    }

    /**
     * רענון token
     * @returns {Promise<boolean>}
     */
    async refreshToken() {
        const refreshToken = await this.getRefreshToken();

        if (!refreshToken) {
            console.log('[PersistentAuth] No refresh token available');
            return false;
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'refresh',
                    refresh_token: refreshToken
                }),
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                // שמור את ה-tokens החדשים
                await this.saveLogin({
                    token: data.token,
                    refresh_token: data.refresh_token,
                    expires: data.expires,
                    user_id: this.currentUser?.id
                });

                console.log('[PersistentAuth] Token refreshed successfully');
                return true;
            }

            console.log('[PersistentAuth] Token refresh failed:', data.message);
            return false;

        } catch (error) {
            console.error('[PersistentAuth] Refresh error:', error);
            return false;
        }
    }

    /**
     * התנתקות
     */
    async logout() {
        const token = await this.getStoredToken();

        // בטל token בשרת
        if (token) {
            try {
                await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'revoke',
                        token: token
                    }),
                    credentials: 'include'
                });
            } catch (e) {
                console.warn('[PersistentAuth] Server revoke failed:', e);
            }
        }

        // נקה הכל
        await this.clearAuth();

        this.currentUser = null;
    }

    /**
     * ניקוי כל נתוני האימות
     */
    async clearAuth() {
        // מחק מ-IndexedDB
        if (window.authStorage) {
            await window.authStorage.clearToken();
        }

        // מחק מ-localStorage
        localStorage.removeItem('ck_auth_token');
        localStorage.removeItem('ck_refresh_token');
        localStorage.removeItem('ck_token_expires');
        localStorage.removeItem('ck_user_id');
        localStorage.removeItem('ck_user_data');

        // Cookie נמחק על ידי השרת
    }

    /**
     * קבלת נתוני משתמש במצב offline
     * @returns {Object|null}
     */
    getOfflineUserData() {
        try {
            const userData = localStorage.getItem('ck_user_data');
            if (userData) {
                return JSON.parse(userData);
            }
        } catch (e) {
            console.error('[PersistentAuth] Failed to get offline data:', e);
        }
        return null;
    }

    /**
     * שמירת נתוני משתמש לשימוש offline
     * @param {Object} userData
     */
    saveUserDataForOffline(userData) {
        try {
            localStorage.setItem('ck_user_data', JSON.stringify(userData));
        } catch (e) {
            console.warn('[PersistentAuth] Failed to save offline data:', e);
        }
    }

    /**
     * קבלת cookie לפי שם
     * @param {string} name
     * @returns {string|null}
     */
    getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    /**
     * בדיקה אם מחובר
     * @returns {boolean}
     */
    isLoggedIn() {
        return this.currentUser !== null;
    }

    /**
     * קבלת המשתמש הנוכחי
     * @returns {Object|null}
     */
    getUser() {
        return this.currentUser;
    }
}

// יצירת instance גלובלי
window.persistentAuth = new PersistentAuth();

// אתחול אוטומטי כשהדף נטען
document.addEventListener('DOMContentLoaded', async () => {
    // אתחל רק אם לא בדף login
    if (!window.location.pathname.includes('/auth/login')) {
        const user = await window.persistentAuth.init();

        if (user) {
            // שמור לשימוש offline
            window.persistentAuth.saveUserDataForOffline(user);

            // trigger event
            window.dispatchEvent(new CustomEvent('auth:ready', { detail: user }));
        } else {
            // אין משתמש מחובר - בדוק אם צריך להפנות ל-login
            window.dispatchEvent(new CustomEvent('auth:guest'));
        }
    }
});

// האזנה לחזרה מ-offline
window.addEventListener('online', async () => {
    console.log('[PersistentAuth] Back online, validating token...');

    if (window.persistentAuth.isInitialized) {
        const userData = await window.persistentAuth.validateStoredToken();

        if (userData) {
            window.persistentAuth.currentUser = userData;
            window.dispatchEvent(new CustomEvent('auth:restored', { detail: userData }));
        } else {
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }
    }
});

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PersistentAuth;
}
