/*
 * File: user-settings/js/user-settings-storage.js
 * Version: 2.0.0
 * Created: 2026-01-23
 * Updated: 2026-01-25
 * Author: Malkiel
 * Description: ניהול cache מקומי להגדרות משתמש עם תמיכה בפרופילי מכשיר
 */

const UserSettingsStorage = (function() {
    const STORAGE_KEY_PREFIX = 'user_settings_cache_';
    const CACHE_EXPIRY = 5 * 60 * 1000; // 5 דקות
    const MOBILE_BREAKPOINT = 768;

    /**
     * זיהוי סוג מכשיר לפי רוחב מסך
     */
    function getDeviceType() {
        return window.innerWidth < MOBILE_BREAKPOINT ? 'mobile' : 'desktop';
    }

    /**
     * קבלת מפתח cache לפי סוג מכשיר
     */
    function getStorageKey(deviceType) {
        const device = deviceType || getDeviceType();
        return STORAGE_KEY_PREFIX + device;
    }

    /**
     * קבלת כל הcache עבור מכשיר מסוים
     */
    function getCache(deviceType) {
        try {
            const storageKey = getStorageKey(deviceType);
            const cached = localStorage.getItem(storageKey);
            if (!cached) return null;

            const data = JSON.parse(cached);
            if (Date.now() > data.expiry) {
                clearCache(deviceType);
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
    function setCache(settings, deviceType) {
        try {
            const storageKey = getStorageKey(deviceType);
            const data = {
                settings: settings,
                deviceType: deviceType || getDeviceType(),
                expiry: Date.now() + CACHE_EXPIRY,
                timestamp: Date.now()
            };
            localStorage.setItem(storageKey, JSON.stringify(data));
        } catch (e) {
            console.error('UserSettingsStorage: Error saving cache', e);
        }
    }

    /**
     * עדכון הגדרה בcache
     */
    function updateCacheItem(key, value, deviceType) {
        try {
            const storageKey = getStorageKey(deviceType);
            const cached = localStorage.getItem(storageKey);
            if (!cached) return;

            const data = JSON.parse(cached);
            if (data.settings && data.settings[key]) {
                data.settings[key].value = value;
                data.settings[key].isDefault = false;
                localStorage.setItem(storageKey, JSON.stringify(data));
            }
        } catch (e) {
            console.error('UserSettingsStorage: Error updating cache', e);
        }
    }

    /**
     * ניקוי cache למכשיר מסוים או לכל המכשירים
     */
    function clearCache(deviceType) {
        try {
            if (deviceType) {
                localStorage.removeItem(getStorageKey(deviceType));
            } else {
                // ניקוי כל הפרופילים
                localStorage.removeItem(getStorageKey('desktop'));
                localStorage.removeItem(getStorageKey('mobile'));
            }
        } catch (e) {
            console.error('UserSettingsStorage: Error clearing cache', e);
        }
    }

    /**
     * בדיקה האם יש cache תקף
     */
    function hasValidCache(deviceType) {
        return getCache(deviceType) !== null;
    }

    /**
     * קבלת הגדרה בודדת מהcache
     */
    function getCachedValue(key, deviceType) {
        const cache = getCache(deviceType);
        if (cache && cache[key]) {
            return cache[key].value;
        }
        return null;
    }

    /**
     * מיגרציה מcache ישן לחדש
     */
    function migrateOldCache() {
        try {
            const oldCache = localStorage.getItem('user_settings_cache');
            if (oldCache) {
                const data = JSON.parse(oldCache);
                if (data.settings) {
                    // שמירה כפרופיל desktop
                    setCache(data.settings, 'desktop');
                }
                // מחיקת הcache הישן
                localStorage.removeItem('user_settings_cache');
                console.log('UserSettingsStorage: Migrated old cache to desktop profile');
            }
        } catch (e) {
            console.error('UserSettingsStorage: Migration error', e);
        }
    }

    // הרצת מיגרציה בטעינה
    migrateOldCache();

    return {
        getDeviceType,
        getStorageKey,
        getCache,
        setCache,
        updateCacheItem,
        clearCache,
        hasValidCache,
        getCachedValue,
        MOBILE_BREAKPOINT
    };
})();

// Export for use
if (typeof window !== 'undefined') {
    window.UserSettingsStorage = UserSettingsStorage;
}
