/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-config.js
 * Version: 3.0.0
 * Updated: 2025-12-10
 * Author: Malkiel
 * Change Summary:
 * - v3.0.0: 🔥 מעבר לטעינה דינמית מ-API
 *   ✅ הקונפיג נטען מ-cemetery-hierarchy-config.php דרך API
 *   ✅ מקור אמת יחיד - PHP בלבד
 *   ✅ תמיכה בטעינה סינכרונית ואסינכרונית
 *   ✅ Cache לביצועים
 * - v2.0.0: הרחבה מלאה של הקונפיגורציה (hardcoded)
 * - v1.0.0: גרסה ראשונית
 */


// ===================================================================
// קונפיגורציה מרכזית - תיטען מה-API
// ===================================================================
let ENTITY_CONFIG = {};

// רשימת הישויות שצריך לטעון
const ENTITY_TYPES = ['plot', 'areaGrave', 'grave', 'customer', 'purchase', 'burial', 'payment', 'residency', 'country'];

// נתיב ל-API
const CONFIG_API_ENDPOINT = '/dashboard/dashboards/cemeteries/api/get-config.php';

// ===================================================================
// פונקציה לטעינת קונפיג מה-API
// ===================================================================
async function loadEntityConfig(entityType) {
    try {
        const url = `${CONFIG_API_ENDPOINT}?type=${entityType}&section=entity`;
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Unknown error');
        }
        
        return result.data;
        
    } catch (error) {
        console.error(`❌ Failed to load config for ${entityType}:`, error);
        return null;
    }
}

// ===================================================================
// פונקציה לטעינת כל הקונפיגים
// ===================================================================
async function loadAllEntityConfigs() {
    
    const startTime = performance.now();
    
    // טעינה מקבילית של כל הישויות
    const promises = ENTITY_TYPES.map(async (type) => {
        const config = await loadEntityConfig(type);
        if (config) {
            ENTITY_CONFIG[type] = config;
            return { type, success: true };
        }
        return { type, success: false };
    });
    
    const results = await Promise.all(promises);
    
    const endTime = performance.now();
    const duration = (endTime - startTime).toFixed(2);
    
    // סיכום
    const successful = results.filter(r => r.success).length;
    const failed = results.filter(r => !r.success);
    
    
    if (failed.length > 0) {
    }
    
    
    // הפוך לגלובלי
    window.ENTITY_CONFIG = ENTITY_CONFIG;
    
    return ENTITY_CONFIG;
}

// ===================================================================
// פונקציה לטעינת ישות בודדת (on-demand)
// ===================================================================
async function ensureEntityConfig(entityType) {
    if (ENTITY_CONFIG[entityType]) {
        return ENTITY_CONFIG[entityType];
    }
    
    const config = await loadEntityConfig(entityType);
    if (config) {
        ENTITY_CONFIG[entityType] = config;
        window.ENTITY_CONFIG = ENTITY_CONFIG;
    }
    
    return config;
}

// ===================================================================
// פונקציה סינכרונית לקבלת קונפיג (אם כבר נטען)
// ===================================================================
function getEntityConfig(entityType) {
    if (!ENTITY_CONFIG[entityType]) {
        return null;
    }
    return ENTITY_CONFIG[entityType];
}

// ===================================================================
// פונקציה לבדיקת מוכנות
// ===================================================================
function isConfigReady() {
    return Object.keys(ENTITY_CONFIG).length > 0;
}

// ===================================================================
// פונקציה לקבלת רשימת ישויות זמינות
// ===================================================================
function getAvailableEntities() {
    return Object.keys(ENTITY_CONFIG);
}

// ===================================================================
// אתחול - טעינת כל הקונפיגים
// ===================================================================
let configLoadPromise = null;

function initEntityConfig() {
    if (!configLoadPromise) {
        configLoadPromise = loadAllEntityConfigs();
    }
    return configLoadPromise;
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.ENTITY_CONFIG = ENTITY_CONFIG;
window.loadEntityConfig = loadEntityConfig;
window.loadAllEntityConfigs = loadAllEntityConfigs;
window.ensureEntityConfig = ensureEntityConfig;
window.getEntityConfig = getEntityConfig;
window.isConfigReady = isConfigReady;
window.getAvailableEntities = getAvailableEntities;
window.initEntityConfig = initEntityConfig;

// ===================================================================
// טעינה אוטומטית בעת טעינת הקובץ
// ===================================================================
initEntityConfig().then(() => {
    
    // שליחת אירוע שהקונפיג מוכן
    window.dispatchEvent(new CustomEvent('entityConfigReady', { 
        detail: { entities: Object.keys(ENTITY_CONFIG) }
    }));
}).catch(error => {
    console.error('❌ Failed to initialize entity config:', error);
});