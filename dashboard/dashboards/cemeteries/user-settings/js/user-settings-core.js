/*
 * File: user-settings/js/user-settings-core.js
 * Version: 2.0.0
 * Created: 2026-01-23
 * Updated: 2026-01-25
 * Author: Malkiel
 * Description: קוד מרכזי להגדרות משתמש עם תמיכה בפרופילי מכשיר
 */

const UserSettings = (function() {
    const API_URL = '/dashboard/dashboards/cemeteries/user-settings/api/api.php';

    let settings = {};
    let isLoaded = false;
    let loadingPromise = null;
    let listeners = [];
    let currentDeviceType = null;

    /**
     * שמירת סוג מכשיר בcookie
     */
    function setDeviceTypeCookie(deviceType) {
        document.cookie = `deviceType=${deviceType}; path=/; max-age=31536000`; // שנה
    }

    /**
     * קבלת סוג המכשיר הנוכחי
     */
    function getDeviceType() {
        if (currentDeviceType) return currentDeviceType;

        if (typeof UserSettingsStorage !== 'undefined') {
            currentDeviceType = UserSettingsStorage.getDeviceType();
        } else {
            currentDeviceType = window.innerWidth < 768 ? 'mobile' : 'desktop';
        }

        // שמירה בcookie לטעינה הבאה (למניעת FOUC)
        setDeviceTypeCookie(currentDeviceType);

        return currentDeviceType;
    }

    /**
     * טעינת הגדרות מהשרת
     */
    async function load(forceRefresh = false) {
        const deviceType = getDeviceType();

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
            const cached = UserSettingsStorage.getCache(deviceType);
            if (cached) {
                settings = cached;
                isLoaded = true;
                return settings;
            }
        }

        // טעינה מהשרת
        loadingPromise = fetch(`${API_URL}?action=get&deviceType=${deviceType}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    settings = data.settings || {};
                    isLoaded = true;

                    // שמירה בcache
                    if (typeof UserSettingsStorage !== 'undefined') {
                        UserSettingsStorage.setCache(settings, deviceType);
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
            const cached = UserSettingsStorage.getCachedValue(key, getDeviceType());
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
        const deviceType = getDeviceType();

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set', key, value, deviceType })
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
                    UserSettingsStorage.updateCacheItem(key, value, deviceType);
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
        const deviceType = getDeviceType();

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set', settings: settingsObj, deviceType })
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
                    UserSettingsStorage.setCache(settings, deviceType);
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
        const deviceType = getDeviceType();

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset', key, deviceType })
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
                    UserSettingsStorage.clearCache(deviceType);
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
        const deviceType = getDeviceType();

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset', category, deviceType })
            });

            const data = await response.json();

            if (data.success) {
                // רענון cache וטעינה מחדש
                if (typeof UserSettingsStorage !== 'undefined') {
                    UserSettingsStorage.clearCache(deviceType);
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
            detail: { key, value, deviceType: getDeviceType() }
        }));
    }

    /**
     * החלת הגדרות על הממשק
     */
    function applyToUI() {
        // מצב כהה (toggle)
        const darkMode = get('darkMode', false);
        const isDark = darkMode === true || darkMode === 'true';
        const theme = isDark ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
        document.body.classList.remove('dark-theme', 'light-theme');
        document.body.classList.add(theme + '-theme');

        // סגנון צבע (רק במצב בהיר)
        const colorScheme = get('colorScheme', 'purple');
        document.documentElement.setAttribute('data-color-scheme', isDark ? '' : colorScheme);
        document.body.classList.remove('color-scheme-purple', 'color-scheme-green');
        if (!isDark) {
            document.body.classList.add('color-scheme-' + colorScheme);
        }

        // גודל גופן (מוגבל בין 10-30)
        let fontSize = get('fontSize', 14);
        fontSize = Math.min(30, Math.max(10, fontSize));
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

        // הצגת סטטיסטיקות בכותרת
        const showHeaderStats = get('showHeaderStats', true);
        const showStats = showHeaderStats === true || showHeaderStats === 'true';
        const headerStats = document.getElementById('headerStats');
        if (headerStats) {
            headerStats.classList.toggle('hidden', !showStats);
        }

        console.log('UserSettings applied:', { deviceType: getDeviceType(), darkMode: isDark, colorScheme, fontSize, compactMode, sidebarCollapsed: isCollapsed, showHeaderStats: showStats });
    }

    /**
     * האזנה לשינוי גודל מסך
     */
    function setupResizeListener() {
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const newDeviceType = typeof UserSettingsStorage !== 'undefined'
                    ? UserSettingsStorage.getDeviceType()
                    : (window.innerWidth < 768 ? 'mobile' : 'desktop');

                if (newDeviceType !== currentDeviceType) {
                    console.log('UserSettings: Device type changed from', currentDeviceType, 'to', newDeviceType);
                    currentDeviceType = newDeviceType;
                    isLoaded = false;
                    settings = {};

                    // עדכון cookie
                    setDeviceTypeCookie(newDeviceType);

                    // טעינה מחדש עבור המכשיר החדש
                    load(true).then(() => {
                        applyToUI();
                        notifyListeners('*', null);
                    });
                }
            }, 300);
        });
    }

    /**
     * אתחול
     */
    async function init() {
        try {
            // הגדרת האזנה לשינוי גודל מסך
            setupResizeListener();

            await load();
            applyToUI();

            // האזנה לשינויים ועדכון UI
            onChange((key, value) => {
                if (['darkMode', 'colorScheme', 'fontSize', 'compactMode', 'sidebarCollapsed', 'showHeaderStats'].includes(key)) {
                    applyToUI();
                }
            });

            console.log('UserSettings initialized for device:', getDeviceType());
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
        init,
        getDeviceType
    };
})();

// Export for use
if (typeof window !== 'undefined') {
    window.UserSettings = UserSettings;
}
