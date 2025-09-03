/**
 * קובץ הגדרות למערכת החיפוש
 * dashboard/search/assets/js/config.js
 */

// הגדרות נתיבים
const APP_CONFIG = {
    // נתיבי בסיס
    basePath: '/dashboard/search',
    apiPath: '/dashboard/search/api',
    dataPath: '/dashboard/search/data',
    assetsPath: '/dashboard/search/assets',
    
    // נתיבי API
    api: {
        search: '/dashboard/search/api/deceased-search.php',
        stats: '/dashboard/search/api/statistics.php',
        export: '/dashboard/search/api/export.php'
    },
    
    // נתיבי נתונים
    data: {
        deceased: '/dashboard/search/data/deceased.json',
        cities: '/dashboard/search/data/cities.json',
        cemeteries: '/dashboard/search/data/cemeteries.json'
    },
    
    // הגדרות חיפוש
    search: {
        minQueryLength: 2,
        maxResults: 100,
        cacheTimeout: 5 * 60 * 1000, // 5 דקות
        debounceDelay: 300 // מילישניות
    },
    
    // הגדרות ממשק
    ui: {
        animationDuration: 300,
        toastDuration: 3000,
        defaultLanguage: 'he',
        dateFormat: 'DD/MM/YYYY'
    },
    
    // מצב פיתוח/ייצור
    environment: 'development', // 'development' או 'production'
    debug: true,
    
    // גרסה
    version: '1.0.0'
};

// פונקציות עזר
const Config = {
    /**
     * קבלת נתיב מלא
     */
    getFullPath: function(path) {
        if (path.startsWith('http')) {
            return path;
        }
        return window.location.origin + path;
    },
    
    /**
     * קבלת נתיב API
     */
    getApiUrl: function(endpoint) {
        return this.getFullPath(APP_CONFIG.api[endpoint] || APP_CONFIG.apiPath + '/' + endpoint);
    },
    
    /**
     * קבלת נתיב נתונים
     */
    getDataUrl: function(dataFile) {
        return this.getFullPath(APP_CONFIG.data[dataFile] || APP_CONFIG.dataPath + '/' + dataFile);
    },
    
    /**
     * קבלת נתיב נכס
     */
    getAssetUrl: function(assetPath) {
        return this.getFullPath(APP_CONFIG.assetsPath + '/' + assetPath);
    },
    
    /**
     * בדיקת מצב פיתוח
     */
    isDevelopment: function() {
        return APP_CONFIG.environment === 'development';
    },
    
    /**
     * לוג (רק במצב פיתוח)
     */
    log: function(...args) {
        if (APP_CONFIG.debug && this.isDevelopment()) {
            console.log('[Search App]', ...args);
        }
    },
    
    /**
     * שגיאה
     */
    error: function(...args) {
        console.error('[Search App Error]', ...args);
    }
};

// ייצוא גלובלי
window.APP_CONFIG = APP_CONFIG;
window.Config = Config;

// הודעת אתחול
Config.log('Configuration loaded', APP_CONFIG);