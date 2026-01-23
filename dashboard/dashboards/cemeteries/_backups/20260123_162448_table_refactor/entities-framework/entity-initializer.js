/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-initializer.js
 * Version: 1.0.0
 * Updated: 2025-11-20
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: 🆕 אתחול אוטומטי של מערכת ניהול היישויות
 *   ✅ בדיקת תלויות
 *   ✅ אתחול כל המודולים
 *   ✅ חיבור ל-window globals
 *   ✅ רישום event listeners
 *   ✅ דיווח סטטוס מפורט
 */


// ===================================================================
// מנהל אתחול מערכת ניהול היישויות
// ===================================================================
class EntityInitializer {

    /**
     * אתחול מלא של המערכת
     */
    static async initialize() {
        
        try {
            // שלב 1: בדיקת תלויות
            const dependenciesOk = this.checkDependencies();
            
            if (!dependenciesOk) {
                throw new Error('Missing required dependencies');
            }
            
            // שלב 2: אתחול state manager
            if (!window.entityState) {
                window.entityState = new EntityStateManager();
            }
            
            // שלב 3: רישום פונקציות גלובליות
            this.registerGlobalFunctions();
            
            // שלב 4: אתחול utilities
            this.initializeUtilities();
            
            // שלב 5: חיבור event listeners
            this.attachEventListeners();
            
            // שלב 6: סיכום
            this.printSummary();
            
            
            return true;
            
        } catch (error) {
            console.error('╠════════════════════════════════════════════════════════════════');
            console.error('║ ❌ INITIALIZATION FAILED');
            console.error('║ Error:', error.message);
            console.error('╚════════════════════════════════════════════════════════════════\n');
            
            return false;
        }
    }

    /**
     * בדיקת תלויות נדרשות
     */
    static checkDependencies() {
        const required = [
            'ENTITY_CONFIG',
            'EntityStateManager',
            'EntityLoader',
            'EntityRenderer',
            'EntityManager'
        ];
        
        const missing = [];
        
        required.forEach(dep => {
            if (typeof window[dep] === 'undefined') {
                missing.push(dep);
            }
        });
        
        if (missing.length > 0) {
            console.error('║ ❌ Missing dependencies:', missing.join(', '));
            return false;
        }
        
        return true;
    }

    /**
     * רישום פונקציות גלובליות
     */
    static registerGlobalFunctions() {
        // הפונקציות כבר נרשמו ב-entity-manager.js
        // כאן רק נוודא שהן קיימות
        
        const functions = [
            'loadCustomers',
            'loadPurchases',
            'loadBurials',
            'loadPlots',
            'loadAreaGraves',
            'loadGraves',
            'deleteCustomer',
            'deletePurchase',
            'deleteBurial',
            'deletePlot',
            'deleteAreaGrave',
            'deleteGrave'
        ];
        
        functions.forEach(fn => {
            if (typeof window[fn] !== 'function') {
            }
        });
    }

    /**
     * אתחול utilities
     */
    static initializeUtilities() {
        // וידוא שפונקציות העזר מ-entities-common-utils זמינות
        const utilities = [
            'showToast',
            'formatDate',
            'formatCurrency',
            'formatEntityStatus',
            'checkEntityScrollStatus',
            'deleteEntity',
            'refreshEntityData',
            'loadEntityStats'
        ];
        
        utilities.forEach(util => {
            if (typeof window[util] !== 'function') {
            }
        });
    }

    /**
     * חיבור event listeners
     */
    static attachEventListeners() {
        // Event listener לדיבאג - Ctrl+Shift+D
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                EntityManager.dumpSystemState();
            }
        });
        
        // Event listener לדיבאג - Ctrl+Shift+S
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                entityState.dumpStates();
            }
        });
    }

    /**
     * הדפסת סיכום
     */
    static printSummary() {
        const entityTypes = Object.keys(ENTITY_CONFIG);
        
    }

    /**
     * בדיקת תקינות המערכת
     */
    static healthCheck() {
        
        const checks = {
            'Config loaded': typeof ENTITY_CONFIG !== 'undefined',
            'State manager': typeof window.entityState !== 'undefined',
            'Entity loader': typeof EntityLoader !== 'undefined',
            'Entity renderer': typeof EntityRenderer !== 'undefined',
            'Entity manager': typeof EntityManager !== 'undefined',
            'Common utils': typeof showToast === 'function',
            'OperationManager': typeof OperationManager !== 'undefined',
            'TableManager': typeof TableManager !== 'undefined',
            'UniversalSearch': typeof UniversalSearch !== 'undefined'
        };
        
        let allHealthy = true;
        
        Object.entries(checks).forEach(([name, status]) => {
            const icon = status ? '✅' : '❌';
            if (!status) allHealthy = false;
        });
        
        
        if (allHealthy) {
        } else {
        }
        
        
        return allHealthy;
    }

    /**
     * מידע על גרסה
     */
    static version() {
    }
}

// ===================================================================
// אתחול אוטומטי
// ===================================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        EntityInitializer.initialize();
    });
} else {
    // אם ה-DOM כבר נטען
    EntityInitializer.initialize();
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.EntityInitializer = EntityInitializer;

// פונקציות קיצור נוחות
window.systemHealth = () => EntityInitializer.healthCheck();
window.systemVersion = () => EntityInitializer.version();
window.systemState = () => EntityManager.dumpSystemState();

