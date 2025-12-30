/*
 * File: dashboards/dashboard/cemeteries/assets/js/operation-manager.js
 * Version: 2.0.0
 * Updated: 2025-11-18
 * Author: Malkiel
 * Description: מנהל מרכזי לביטול פעולות אסינכרוניות אוטומטית
 * Change Summary:
 * - v2.0.0: שדרוג מלא עם טעינת קונפיג מהשרת
 *   - טעינת entity-config.php מהשרת
 *   - Validation מלא של entity types
 *   - תרגום לעברית בכל הלוגים
 *   - Cache חכם של הקונפיג
 *   - תמיכה באייקונים וצבעים
 * - v1.0.0: יצירת מערכת מרכזית לניהול race conditions
 */


/**
 * מנהל גלובלי לניהול פעולות אסינכרוניות
 * מונע race conditions על ידי ביטול אוטומטי של פעולות ישנות
 */
const OperationManager = {
    // ===================================================================
    // משתנים פנימיים
    // ===================================================================
    
    // Controller נוכחי (רק אחד בכל זמן נתון!)
    currentController: null,
    
    // סוג הפעולה הנוכחית
    currentType: null,
    
    // קונפיג שנטען מהשרת (cache)
    entityConfig: null,
    
    // האם הקונפיג כבר נטען
    configLoaded: false,
    
    // Promise של טעינת הקונפיג (למניעת טעינות כפולות)
    configLoadPromise: null,
    
    // ===================================================================
    // טעינת קונפיג מהשרת
    // ===================================================================
    
    /**
     * טוען את הקונפיג מהשרת (פעם אחת בלבד!)
     * @returns {Promise<Object>} הקונפיג שנטען
     */
    async loadConfig() {
        // אם כבר נטען - החזר מה-cache
        if (this.configLoaded && this.entityConfig) {
            return this.entityConfig;
        }
        
        // אם יש טעינה בתהליך - המתן לה
        if (this.configLoadPromise) {
            return this.configLoadPromise;
        }
        
        
        // צור Promise חדש
        this.configLoadPromise = (async () => {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/config/entity-config.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // נסה לקרוא כ-JSON
                const text = await response.text();
                
                // אם זה PHP array, צריך endpoint שמחזיר JSON
                // נניח שיש endpoint: /dashboard/dashboards/cemeteries/api/get-entity-config.php
                const configResponse = await fetch('/dashboard/dashboards/cemeteries/api/get-entity-config.php');
                
                if (!configResponse.ok) {
                    throw new Error('Failed to load entity config');
                }
                
                const result = await configResponse.json();
                
                if (result.success && result.data) {
                    this.entityConfig = result.data;
                    this.configLoaded = true;
                    
                    
                    // הדפס רשימה
                    console.table(
                        Object.entries(this.entityConfig).map(([key, cfg]) => ({
                            'Type': key,
                            'שם': cfg.namePluralHe,
                            'רמה': cfg.level,
                            'אייקון': cfg.icon,
                            'פעיל': cfg.enabled ? '✓' : '✗'
                        }))
                    );
                    
                    return this.entityConfig;
                } else {
                    throw new Error('Invalid config format');
                }
                
            } catch (error) {
                console.error('❌ שגיאה בטעינת קונפיג:', error);
                
                // Fallback - קונפיג מינימלי מקומי
                this.entityConfig = this.getFallbackConfig();
                this.configLoaded = true;
                
                return this.entityConfig;
            } finally {
                this.configLoadPromise = null;
            }
        })();
        
        return this.configLoadPromise;
    },
    
    /**
     * קונפיג מינימלי (fallback) במקרה שהשרת לא עונה
     * @returns {Object} קונפיג בסיסי
     */
    getFallbackConfig() {
        return {
            cemetery: { 
                nameHe: 'בית עלמין', 
                namePluralHe: 'בתי עלמין', 
                level: 1, 
                icon: '🏛️',
                enabled: true 
            },
            block: { 
                nameHe: 'גוש', 
                namePluralHe: 'גושים', 
                level: 2, 
                icon: '📦',
                enabled: true 
            },
            plot: { 
                nameHe: 'חלקה', 
                namePluralHe: 'חלקות', 
                level: 3, 
                icon: '📐',
                enabled: true 
            },
            areaGrave: { 
                nameHe: 'אחוזת קבר', 
                namePluralHe: 'אחוזות קבר', 
                level: 4, 
                icon: '🏘️',
                enabled: true 
            },
            grave: { 
                nameHe: 'קבר', 
                namePluralHe: 'קברים', 
                level: 5, 
                icon: '🪦',
                enabled: true 
            }
        };
    },
    
    // ===================================================================
    // פונקציות עזר
    // ===================================================================
    
    /**
     * בודק אם סוג ה-entity חוקי
     * @param {string} type - סוג ה-entity
     * @returns {boolean}
     */
    isValidType(type) {
        if (!this.entityConfig) {
            return true; // אשר זמנית
        }
        
        const isValid = this.entityConfig[type] && this.entityConfig[type].enabled;
        
        if (!isValid) {
            console.error(`❌ Entity type לא חוקי: "${type}"`);
        }
        
        return isValid;
    },
    
    /**
     * מחזיר את השם בעברית של entity
     * @param {string} type - סוג ה-entity
     * @param {boolean} plural - רבים או יחיד
     * @returns {string}
     */
    getHebrewName(type, plural = false) {
        if (!this.entityConfig || !this.entityConfig[type]) {
            return type; // Fallback
        }
        
        return plural ? 
            this.entityConfig[type].namePluralHe : 
            this.entityConfig[type].nameHe;
    },
    
    /**
     * מחזיר את האייקון של entity
     * @param {string} type - סוג ה-entity
     * @returns {string}
     */
    getIcon(type) {
        if (!this.entityConfig || !this.entityConfig[type]) {
            return '📄'; // אייקון ברירת מחדל
        }
        
        return this.entityConfig[type].icon || '📄';
    },
    
    /**
     * מחזיר את הרמה של entity
     * @param {string} type - סוג ה-entity
     * @returns {number}
     */
    getLevel(type) {
        if (!this.entityConfig || !this.entityConfig[type]) {
            return 0;
        }
        
        return this.entityConfig[type].level || 0;
    },
    
    // ===================================================================
    // פונקציות ראשיות
    // ===================================================================
    
    /**
     * מתחיל פעולה חדשה
     * מבטל אוטומטית כל פעולה קודמת
     * @param {string} type - סוג הפעולה (cemetery, block, plot, וכו')
     * @returns {AbortSignal} signal לשימוש ב-fetch או פעולות async
     */
    start(type) {
        // וידוא שהקונפיג נטען (בדיקה מהירה בלבד)
        if (!this.configLoaded) {
            // אל תמתין - טען ברקע
            this.loadConfig().catch(err => {
                console.error('❌ שגיאה בטעינת קונפיג ברקע:', err);
            });
        }
        
        // בדוק תקינות (אם הקונפיג כבר נטען)
        if (this.configLoaded && !this.isValidType(type)) {
            console.error(`❌ לא ניתן להתחיל פעולה עם type לא חוקי: "${type}"`);
            // אבל בכל זאת המשך - אולי זה entity חדש
        }
        
        const icon = this.getIcon(type);
        const nameHe = this.getHebrewName(type, true);
        
        
        // אם יש פעולה פעילה - בטל אותה
        if (this.currentController && this.currentType) {
            const oldIcon = this.getIcon(this.currentType);
            const oldNameHe = this.getHebrewName(this.currentType, true);
            
            this.currentController.abort();
        }
        
        // צור controller חדש
        this.currentController = new AbortController();
        this.currentType = type;
        
        // עדכן את המשתנה הגלובלי
        window.currentType = type;
        
        
        return this.currentController.signal;
    },
    
    /**
     * בודק אם הפעולה צריכה להיעצר
     * @param {string} type - סוג הפעולה לבדיקה
     * @returns {boolean} true אם צריך להפסיק
     */
    shouldAbort(type) {
        const typeChanged = this.currentType !== type;
        const wasAborted = this.currentController?.signal.aborted;
        
        if (typeChanged) {
            const icon = this.getIcon(type);
            const nameHe = this.getHebrewName(type, true);
            const currentIcon = this.getIcon(this.currentType);
            const currentNameHe = this.getHebrewName(this.currentType, true);
            
        }
        
        if (wasAborted) {
            const icon = this.getIcon(type);
            const nameHe = this.getHebrewName(type, true);
        }
        
        return typeChanged || wasAborted;
    },
    
    /**
     * בודק אם הפעולה עדיין פעילה
     * @param {string} type - סוג הפעולה
     * @returns {boolean} true אם הפעולה עדיין פעילה
     */
    isActive(type) {
        return this.currentType === type && !this.currentController?.signal.aborted;
    },
    
    /**
     * מבטל את הפעולה הנוכחית
     */
    abort() {
        if (this.currentController && this.currentType) {
            const icon = this.getIcon(this.currentType);
            const nameHe = this.getHebrewName(this.currentType, true);
            
            this.currentController.abort();
        }
    },
    
    /**
     * מחזיר את ה-signal הנוכחי
     * @returns {AbortSignal|null}
     */
    getSignal() {
        return this.currentController?.signal || null;
    },
    
    /**
     * מחזיר את הסוג הנוכחי
     * @returns {string|null}
     */
    getCurrentType() {
        return this.currentType;
    },
    
    /**
     * מחזיר את כל הקונפיג
     * @returns {Object|null}
     */
    getConfig() {
        return this.entityConfig;
    },
    
    /**
     * מחזיר רשימת כל ה-types הזמינים
     * @returns {string[]}
     */
    getAvailableTypes() {
        if (!this.entityConfig) {
            return [];
        }
        
        return Object.keys(this.entityConfig).filter(
            type => this.entityConfig[type].enabled
        );
    }
};

// ===================================================================
// טעינה אוטומטית של הקונפיג בהפעלת הקובץ
// ===================================================================
OperationManager.loadConfig().then(() => {
}).catch(err => {
    console.error('❌ שגיאה בטעינת OperationManager:', err);
});

// ייצוא גלובלי
window.OperationManager = OperationManager;