/*
 * File: user-settings/js/user-settings-core.js
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: קוד מרכזי להגדרות משתמש
 */

const UserSettings = (function() {
    const API_URL = '/dashboard/dashboards/cemeteries/user-settings/api/api.php';

    let settings = {};
    let isLoaded = false;
    let loadingPromise = null;
    let listeners = [];

    /**
     * טעינת הגדרות מהשרת
     */
    async function load(forceRefresh = false) {
        // אם כבר נטען ולא צריך רענון
        if (isLoaded && !forceRefresh) {
            return settings;
        }

        // אם יש בקשה בתהליך, חכה לה
        if (loadingPromise) {
            return loadingPromise;
        }

        // בדיקת cache מקומי
        if (!forceRefresh && typeof UserSettingsStorage !== 'undefined') {
            const cached = UserSettingsStorage.getCache();
            if (cached) {
                settings = cached;
                isLoaded = true;
                return settings;
            }
        }

        // טעינה מהשרת
        loadingPromise = fetch(`${API_URL}?action=get`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    settings = data.settings || {};
                    isLoaded = true;

                    // שמירה בcache
                    if (typeof UserSettingsStorage !== 'undefined') {
                        UserSettingsStorage.setCache(settings);
                    }

                    return settings;
                }
                throw new Error(data.message || 'Failed to load settings');
            })
            .catch(error => {
                console.error('UserSettings: Load error', error);
                throw error;
            })
            .finally(() => {
                loadingPromise = null;
            });

        return loadingPromise;
    }

    /**
     * קבלת הגדרה בודדת
     */
    function get(key, defaultValue = null) {
        if (settings[key]) {
            return settings[key].value;
        }

        // בדיקה בcache
        if (typeof UserSettingsStorage !== 'undefined') {
            const cached = UserSettingsStorage.getCachedValue(key);
            if (cached !== null) return cached;
        }

        return defaultValue;
    }

    /**
     * קבלת הגדרה בודדת אסינכרונית
     */
    async function getAsync(key, defaultValue = null) {
        if (!isLoaded) {
            await load();
        }
        return get(key, defaultValue);
    }

    /**
     * שמירת הגדרה
     */
    async function set(key, value) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set', key, value })
            });

            const data = await response.json();

            if (data.success) {
                // עדכון מקומי
                if (settings[key]) {
                    settings[key].value = value;
                    settings[key].isDefault = false;
                } else {
                    settings[key] = { value };
                }

                // עדכון cache
                if (typeof UserSettingsStorage !== 'undefined') {
                    UserSettingsStorage.updateCacheItem(key, value);
                }

                // שליחת event
                notifyListeners(key, value);

                return true;
            }

            throw new Error(data.message || 'Failed to save setting');

        } catch (error) {
            console.error('UserSettings: Set error', error);
            throw error;
        }
    }

    /**
     * שמירת מספר הגדרות בבת אחת
     */
    async function setMultiple(settingsObj) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set', settings: settingsObj })
            });

            const data = await response.json();

            if (data.success) {
                // עדכון מקומי
                for (const [key, value] of Object.entries(settingsObj)) {
                    if (settings[key]) {
                        settings[key].value = value;
                        settings[key].isDefault = false;
                    } else {
                        settings[key] = { value };
                    }
                    notifyListeners(key, value);
                }

                // עדכון cache
                if (typeof UserSettingsStorage !== 'undefined') {
                    UserSettingsStorage.setCache(settings);
                }

                return true;
            }

            throw new Error(data.message || 'Failed to save settings');

        } catch (error) {
            console.error('UserSettings: SetMultiple error', error);
            throw error;
        }
    }

    /**
     * איפוס הגדרה לברירת מחדל
     */
    async function reset(key) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset', key })
            });

            const data = await response.json();

            if (data.success) {
                // עדכון מקומי - חזרה לברירת מחדל
                if (settings[key] && settings[key].defaultValue !== undefined) {
                    settings[key].value = settings[key].defaultValue;
                    settings[key].isDefault = true;
                    notifyListeners(key, settings[key].value);
                }

                // רענון cache
                if (typeof UserSettingsStorage !== 'undefined') {
                    UserSettingsStorage.clearCache();
                }

                return true;
            }

            throw new Error(data.message || 'Failed to reset setting');

        } catch (error) {
            console.error('UserSettings: Reset error', error);
            throw error;
        }
    }

    /**
     * איפוס כל ההגדרות
     */
    async function resetAll(category = null) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset', category })
            });

            const data = await response.json();

            if (data.success) {
                // רענון cache וטעינה מחדש
                if (typeof UserSettingsStorage !== 'undefined') {
                    UserSettingsStorage.clearCache();
                }

                await load(true);
                notifyListeners('*', null);

                return true;
            }

            throw new Error(data.message || 'Failed to reset settings');

        } catch (error) {
            console.error('UserSettings: ResetAll error', error);
            throw error;
        }
    }

    /**
     * קבלת כל ההגדרות
     */
    function getAll() {
        return settings;
    }

    /**
     * קבלת הגדרות לפי קטגוריה
     */
    function getByCategory(category) {
        const result = {};
        for (const [key, data] of Object.entries(settings)) {
            if (data.category === category) {
                result[key] = data;
            }
        }
        return result;
    }

    /**
     * הרשמה לשינויים
     */
    function onChange(callback) {
        listeners.push(callback);
        return () => {
            listeners = listeners.filter(l => l !== callback);
        };
    }

    /**
     * שליחת עדכון למאזינים
     */
    function notifyListeners(key, value) {
        listeners.forEach(callback => {
            try {
                callback(key, value);
            } catch (e) {
                console.error('UserSettings: Listener error', e);
            }
        });

        // שליחת event גלובלי
        window.dispatchEvent(new CustomEvent('userSettingsChanged', {
            detail: { key, value }
        }));
    }

    /**
     * החלת הגדרות על הממשק
     */
    function applyToUI() {
        // ערכת נושא
        const theme = get('theme', 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.body.classList.remove('dark-theme', 'light-theme');
        document.body.classList.add(theme + '-theme');

        // גודל גופן
        const fontSize = get('fontSize', 14);
        document.documentElement.style.setProperty('--base-font-size', fontSize + 'px');

        // מצב קומפקטי
        const compactMode = get('compactMode', false);
        document.body.classList.toggle('compact-mode', compactMode === true || compactMode === 'true');

        // כיווץ sidebar
        const sidebarCollapsed = get('sidebarCollapsed', false);
        const isCollapsed = sidebarCollapsed === true || sidebarCollapsed === 'true';
        const sidebar = document.querySelector('.sidebar, .side-panel');
        if (sidebar) {
            sidebar.classList.toggle('collapsed', isCollapsed);
        }
        document.body.classList.toggle('sidebar-collapsed', isCollapsed);

        console.log('UserSettings applied:', { theme, fontSize, compactMode, sidebarCollapsed: isCollapsed });
    }

    /**
     * אתחול
     */
    async function init() {
        try {
            await load();
            applyToUI();

            // האזנה לשינויים ועדכון UI
            onChange((key, value) => {
                if (['theme', 'fontSize', 'compactMode', 'sidebarCollapsed'].includes(key)) {
                    applyToUI();
                }
            });

            console.log('UserSettings initialized');
            return true;
        } catch (error) {
            console.error('UserSettings: Init error', error);
            return false;
        }
    }

    return {
        load,
        get,
        getAsync,
        set,
        setMultiple,
        reset,
        resetAll,
        getAll,
        getByCategory,
        onChange,
        applyToUI,
        init
    };
})();

// Export for use
if (typeof window !== 'undefined') {
    window.UserSettings = UserSettings;
}
