/*
 * File: user-settings/js/user-settings-storage.js
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: ניהול cache מקומי להגדרות משתמש
 */

const UserSettingsStorage = (function() {
    const STORAGE_KEY = 'user_settings_cache';
    const CACHE_EXPIRY = 5 * 60 * 1000; // 5 דקות

    /**
     * קבלת כל הcache
     */
    function getCache() {
        try {
            const cached = localStorage.getItem(STORAGE_KEY);
            if (!cached) return null;

            const data = JSON.parse(cached);
            if (Date.now() > data.expiry) {
                clearCache();
                return null;
            }

            return data.settings;
        } catch (e) {
            console.error('UserSettingsStorage: Error reading cache', e);
            return null;
        }
    }

    /**
     * שמירה בcache
     */
    function setCache(settings) {
        try {
            const data = {
                settings: settings,
                expiry: Date.now() + CACHE_EXPIRY,
                timestamp: Date.now()
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            console.error('UserSettingsStorage: Error saving cache', e);
        }
    }

    /**
     * עדכון הגדרה בcache
     */
    function updateCacheItem(key, value) {
        try {
            const cached = localStorage.getItem(STORAGE_KEY);
            if (!cached) return;

            const data = JSON.parse(cached);
            if (data.settings && data.settings[key]) {
                data.settings[key].value = value;
                data.settings[key].isDefault = false;
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            }
        } catch (e) {
            console.error('UserSettingsStorage: Error updating cache', e);
        }
    }

    /**
     * ניקוי cache
     */
    function clearCache() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            console.error('UserSettingsStorage: Error clearing cache', e);
        }
    }

    /**
     * בדיקה האם יש cache תקף
     */
    function hasValidCache() {
        return getCache() !== null;
    }

    /**
     * קבלת הגדרה בודדת מהcache
     */
    function getCachedValue(key) {
        const cache = getCache();
        if (cache && cache[key]) {
            return cache[key].value;
        }
        return null;
    }

    return {
        getCache,
        setCache,
        updateCacheItem,
        clearCache,
        hasValidCache,
        getCachedValue
    };
})();

// Export for use
if (typeof window !== 'undefined') {
    window.UserSettingsStorage = UserSettingsStorage;
}
