/*
 * File: dashboards/dashboard/cemeteries/assets/js/operation-manager.js
 * Version: 1.0.0
 * Updated: 2025-11-10
 * Author: Malkiel
 * Description: מנהל מרכזי לביטול פעולות אסינכרוניות אוטומטית
 * Change Summary:
 * - v1.0.0: יצירת מערכת מרכזית לניהול race conditions
 *   - שימוש ב-AbortController לביטול פעולות
 *   - בדיקה אוטומטית של currentType
 *   - לוג מפורט לצורכי debug
 */

/**
 * מנהל גלובלי לניהול פעולות אסינכרוניות
 * מונע race conditions על ידי ביטול אוטומטי של פעולות ישנות
 */
const OperationManager = {
    // Controller נוכחי
    currentController: null,
    
    // סוג הפעולה הנוכחית
    currentType: null,
    
    /**
     * מתחיל פעולה חדשה
     * מבטל אוטומטית כל פעולה קודמת
     * @param {string} type - סוג הפעולה (cemetery, block, plot, וכו')
     * @returns {AbortSignal} signal לשימוש ב-fetch או פעולות async
     */
    start(type) {
        console.log(`🚀 OperationManager.start('${type}')`);
        
        // אם יש פעולה פעילה - בטל אותה
        if (this.currentController) {
            console.log(`  ⚠️ Aborting previous operation: ${this.currentType}`);
            this.currentController.abort();
        }
        
        // צור controller חדש
        this.currentController = new AbortController();
        this.currentType = type;
        
        // עדכן את המשתנה הגלובלי
        window.currentType = type;
        
        console.log(`  ✅ New operation started: ${type}`);
        
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
            console.log(`  ⚠️ Type changed: ${type} → ${this.currentType}`);
        }
        
        if (wasAborted) {
            console.log(`  ⚠️ Operation was aborted: ${type}`);
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
        if (this.currentController) {
            console.log(`  ❌ Manually aborting: ${this.currentType}`);
            this.currentController.abort();
        }
    },
    
    /**
     * מחזיר את ה-signal הנוכחי
     * @returns {AbortSignal|null}
     */
    getSignal() {
        return this.currentController?.signal || null;
    }
};

// ייצוא גלובלי
window.OperationManager = OperationManager;

console.log('✅ OperationManager loaded successfully');