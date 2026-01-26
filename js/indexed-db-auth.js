/**
 * IndexedDB Auth Storage - שכבת אחסון עמידה לאימות
 * עמיד יותר מ-localStorage ב-iOS PWA
 *
 * @version 1.0.0
 */

class AuthStorage {
    constructor() {
        this.dbName = 'chevra-kadisha-auth';
        this.dbVersion = 1;
        this.storeName = 'auth_tokens';
        this.db = null;
        this.isReady = false;
        this.readyPromise = this.init();
    }

    /**
     * אתחול מסד הנתונים
     */
    async init() {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                console.warn('[AuthStorage] IndexedDB not supported');
                resolve(false);
                return;
            }

            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = (event) => {
                console.error('[AuthStorage] Failed to open DB:', event.target.error);
                resolve(false);
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                this.isReady = true;
                console.log('[AuthStorage] Database ready');
                resolve(true);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // יצירת store לטוקנים
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const store = db.createObjectStore(this.storeName, { keyPath: 'key' });
                    store.createIndex('expires', 'expires', { unique: false });
                    console.log('[AuthStorage] Store created');
                }
            };
        });
    }

    /**
     * המתן עד שה-DB מוכן
     */
    async waitForReady() {
        await this.readyPromise;
        return this.isReady;
    }

    /**
     * שמירת token
     * @param {string} token - ה-access token
     * @param {string} refreshToken - ה-refresh token
     * @param {number} userId - מזהה המשתמש
     * @param {Date} expires - תאריך תפוגה
     */
    async saveToken(token, refreshToken, userId, expires) {
        await this.waitForReady();

        if (!this.db) {
            console.warn('[AuthStorage] DB not available, falling back to localStorage');
            this._localStorageFallback('save', { token, refreshToken, userId, expires });
            return;
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);

            const data = {
                key: 'auth_data',
                token: token,
                refreshToken: refreshToken,
                userId: userId,
                expires: expires.getTime(),
                savedAt: Date.now()
            };

            const request = store.put(data);

            request.onsuccess = () => {
                console.log('[AuthStorage] Token saved to IndexedDB');
                // שמור גם ב-localStorage כגיבוי
                this._localStorageFallback('save', data);
                resolve(true);
            };

            request.onerror = (event) => {
                console.error('[AuthStorage] Failed to save token:', event.target.error);
                // fallback to localStorage
                this._localStorageFallback('save', data);
                resolve(false);
            };
        });
    }

    /**
     * קריאת token
     * @returns {Object|null} - אובייקט עם token, refreshToken, userId, expires
     */
    async getToken() {
        await this.waitForReady();

        if (!this.db) {
            return this._localStorageFallback('get');
        }

        return new Promise((resolve) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.get('auth_data');

            request.onsuccess = (event) => {
                const data = event.target.result;

                if (!data) {
                    // נסה מ-localStorage
                    const fallback = this._localStorageFallback('get');
                    resolve(fallback);
                    return;
                }

                // בדוק אם פג תוקף
                if (data.expires < Date.now()) {
                    console.log('[AuthStorage] Token expired');
                    this.clearToken();
                    resolve(null);
                    return;
                }

                resolve({
                    token: data.token,
                    refreshToken: data.refreshToken,
                    userId: data.userId,
                    expires: new Date(data.expires)
                });
            };

            request.onerror = () => {
                resolve(this._localStorageFallback('get'));
            };
        });
    }

    /**
     * מחיקת token
     */
    async clearToken() {
        await this.waitForReady();

        // מחק מ-localStorage
        this._localStorageFallback('clear');

        if (!this.db) {
            return;
        }

        return new Promise((resolve) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.delete('auth_data');

            request.onsuccess = () => {
                console.log('[AuthStorage] Token cleared');
                resolve(true);
            };

            request.onerror = () => {
                resolve(false);
            };
        });
    }

    /**
     * בדיקה אם יש token תקף
     * @returns {boolean}
     */
    async hasValidToken() {
        const data = await this.getToken();
        return data !== null && data.token !== null;
    }

    /**
     * בדיקה אם ה-token עומד לפוג (פחות מ-24 שעות)
     * @returns {boolean}
     */
    async shouldRefresh() {
        const data = await this.getToken();
        if (!data) return false;

        const hoursUntilExpiry = (data.expires.getTime() - Date.now()) / (1000 * 60 * 60);
        return hoursUntilExpiry < 24;
    }

    /**
     * Fallback ל-localStorage
     * @private
     */
    _localStorageFallback(action, data = null) {
        const key = 'ck_auth_data';

        try {
            switch (action) {
                case 'save':
                    localStorage.setItem(key, JSON.stringify(data));
                    console.log('[AuthStorage] Saved to localStorage (fallback)');
                    break;

                case 'get':
                    const stored = localStorage.getItem(key);
                    if (!stored) return null;

                    const parsed = JSON.parse(stored);
                    if (parsed.expires < Date.now()) {
                        localStorage.removeItem(key);
                        return null;
                    }

                    return {
                        token: parsed.token,
                        refreshToken: parsed.refreshToken,
                        userId: parsed.userId,
                        expires: new Date(parsed.expires)
                    };

                case 'clear':
                    localStorage.removeItem(key);
                    break;
            }
        } catch (e) {
            console.error('[AuthStorage] localStorage error:', e);
        }

        return null;
    }
}

// יצירת instance גלובלי
window.authStorage = new AuthStorage();

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthStorage;
}
