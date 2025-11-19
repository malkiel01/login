/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-common-utils.js
 * Version: 1.0.0
 * Updated: 2025-11-19
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת קובץ גלובלי לפונקציות משותפות
 *   ✅ showToast() - הצגת הודעות למשתמש
 *   ✅ formatDate() - פורמט תאריך לעברית
 *   ✅ checkEntityScrollStatus() - בדיקת סטטוס גלילה
 */

console.log('🚀 entities-common-utils.js v1.0.0 - Loading...');

// ===================================================================
// קונפיג יישויות - טקסטים ו-endpoints
// ===================================================================
const ENTITY_CONFIG = {
    purchase: {
        singular: 'רכישה',
        singularArticle: 'את הרכישה',
        apiFile: 'purchases-api.php',
        searchVar: 'purchaseSearch'
    },
    customer: {
        singular: 'לקוח',
        singularArticle: 'את הלקוח',
        apiFile: 'customers-api.php',
        searchVar: 'customerSearch'
    },
    burial: {
        singular: 'קבורה',
        singularArticle: 'את הקבורה',
        apiFile: 'burials-api.php',
        searchVar: 'burialSearch'
    },
    plot: {
        singular: 'חלקה',
        singularArticle: 'את החלקה',
        apiFile: 'plots-api.php',
        searchVar: 'plotSearch'
    },
    areaGrave: {
        singular: 'אחוזת קבר',
        singularArticle: 'את אחוזת הקבר',
        apiFile: 'areaGraves-api.php',
        searchVar: 'areaGraveSearch'
    },
    grave: {
        singular: 'קבר',
        singularArticle: 'את הקבר',
        apiFile: 'graves-api.php',
        searchVar: 'graveSearch'
    }
};

// ===================================================================
// 4️⃣ מחיקת יישות - גלובלי
// ===================================================================
/**
 * מוחק יישות לאחר אישור המשתמש
 * @param {string} entityType - סוג היישות (purchase, customer, burial, וכו')
 * @param {string} entityId - מזהה הרשומה למחיקה
 */
async function deleteEntity(entityType, entityId) {
    const config = ENTITY_CONFIG[entityType];
    
    if (!config) {
        console.error(`❌ Unknown entity type: ${entityType}`);
        showToast('שגיאה: סוג יישות לא מוכר', 'error');
        return;
    }
    
    // ⭐ אישור מחיקה
    if (!confirm(`האם אתה בטוח שברצונך למחוק ${config.singularArticle}?`)) {
        return;
    }
    
    try {
        // ⭐ שליחת בקשת DELETE ל-API
        const response = await fetch(
            `/dashboard/dashboards/cemeteries/api/${config.apiFile}?action=delete&id=${entityId}`, 
            { method: 'DELETE' }
        );
        
        const result = await response.json();
        
        // ⭐ טיפול בשגיאה מה-API
        if (!result.success) {
            throw new Error(result.error || `שגיאה במחיקת ה${config.singular}`);
        }
        
        // ⭐ הודעת הצלחה
        showToast(`ה${config.singular} נמחקה בהצלחה`, 'success');
        
        // ⭐ רענון החיפוש
        const searchInstance = window[config.searchVar];
        if (searchInstance && typeof searchInstance.refresh === 'function') {
            searchInstance.refresh();
        }
        
    } catch (error) {
        console.error(`Error deleting ${entityType}:`, error);
        showToast(error.message, 'error');
    }
}

// ===================================================================
// 5️⃣ רענון נתוני יישות - גלובלי
// ===================================================================
/**
 * מרענן את נתוני היישות (טבלה וחיפוש)
 * @param {string} entityType - סוג היישות (cemetery, plot, burial, customer, purchase, areaGrave)
 * @returns {Promise<void>}
 */
async function refreshEntityData(entityType) {
    console.log(`🔄 refreshEntityData('${entityType}') called`);
    
    // ⭐ בדוק אם יש searchInstance
    const searchVarName = `${entityType}Search`;
    const searchInstance = window[searchVarName];
    
    if (searchInstance && typeof searchInstance.refresh === 'function') {
        // דפוס 1: יש חיפוש מתקדם - השתמש ב-refresh()
        console.log(`   ✅ Using ${searchVarName}.refresh()`);
        searchInstance.refresh();
        return;
    }
    
    // ⭐ דפוס 2: אין חיפוש - קרא ישירות ל-load()
    const loadFunctionName = `load${entityType.charAt(0).toUpperCase() + entityType.slice(1)}s`;
    const loadFunction = window[loadFunctionName];
    
    if (typeof loadFunction === 'function') {
        console.log(`   ✅ Calling ${loadFunctionName}()`);
        
        // ⭐ טיפול מיוחד ל-areaGrave שצריך פרמטרים
        if (entityType === 'areaGrave') {
            const plotId = window.areaGravesFilterPlotId || null;
            const plotName = window.areaGravesFilterPlotName || null;
            await loadFunction(plotId, plotName, false);
        } else {
            await loadFunction();
        }
    } else {
        console.error(`❌ No refresh method found for entity type: ${entityType}`);
        showToast(`שגיאה: לא נמצאה פונקציית רענון עבור ${entityType}`, 'error');
    }
}


// ===================================================================
// 1️⃣ הצגת הודעות Toast למשתמש
// ===================================================================
/**
 * מציג הודעת Toast למשתמש
 * @param {string} message - הטקסט להצגה
 * @param {string} type - סוג ההודעה: 'success' | 'error' | 'info'
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease-out;
    `;
    
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideUp 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}


// ===================================================================
// 2️⃣ פורמט תאריך לעברית
// ===================================================================
/**
 * ממיר תאריך לפורמט עברי
 * @param {string} dateString - תאריך בפורמט ISO
 * @returns {string} תאריך מפורמט בעברית
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}


// ===================================================================
// 3️⃣ בדיקת סטטוס גלילה של טבלה
// ===================================================================
/**
 * בודק ומציג את סטטוס הגלילה של טבלה
 * @param {Object} tableInstance - אובייקט TableManager
 * @param {string} entityName - שם היישות (לתצוגה בלוג)
 */
function checkEntityScrollStatus(tableInstance, entityName = 'Entity') {
    if (!tableInstance) {
        console.log(`❌ ${entityName} table not initialized`);
        return;
    }
    
    const total = tableInstance.getFilteredData().length;
    const displayed = tableInstance.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log(`📊 ${entityName} Scroll Status:`);
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.showToast = showToast;
window.formatDate = formatDate;
window.checkEntityScrollStatus = checkEntityScrollStatus;

console.log('✅ entities-common-utils.js v1.0.0 - Loaded successfully!');