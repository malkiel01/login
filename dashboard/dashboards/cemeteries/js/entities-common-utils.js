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
const ENTITY_CONFIG_OLD = {
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

const ENTITY_CONFIG = {
    purchase: {
        singular: 'רכישה',
        singularArticle: 'את הרכישה',
        plural: 'רכישות',
        apiFile: 'purchases-api.php',
        searchVar: 'purchaseSearch',
        tableVar: 'purchasesTable',                    // 🆕
        currentPageVar: 'purchasesCurrentPage',        // 🆕
        totalPagesVar: 'purchasesTotalPages',          // 🆕
        dataArrayVar: 'currentPurchases',              // 🆕
        isLoadingVar: 'purchasesIsLoadingMore'         // 🆕
    },
    customer: {
        singular: 'לקוח',
        singularArticle: 'את הלקוח',
        plural: 'לקוחות',
        apiFile: 'customers-api.php',
        searchVar: 'customerSearch',
        tableVar: 'customersTable',
        currentPageVar: 'customersCurrentPage',
        totalPagesVar: 'customersTotalPages',
        dataArrayVar: 'currentCustomers',
        isLoadingVar: 'customersIsLoadingMore'
    },
    burial: {
        singular: 'קבורה',
        singularArticle: 'את הקבורה',
        plural: 'קבורות',
        apiFile: 'burials-api.php',
        searchVar: 'burialSearch',
        tableVar: 'burialsTable',
        currentPageVar: 'burialsCurrentPage',
        totalPagesVar: 'burialsTotalPages',
        dataArrayVar: 'currentBurials',
        isLoadingVar: 'burialsIsLoadingMore'
    },
    plot: {
        singular: 'חלקה',
        singularArticle: 'את החלקה',
        plural: 'חלקות',
        apiFile: 'plots-api.php',
        searchVar: 'plotSearch',
        tableVar: 'plotsTable',
        currentPageVar: 'plotsCurrentPage',
        totalPagesVar: 'plotsTotalPages',
        dataArrayVar: 'currentPlots',
        isLoadingVar: 'plotsIsLoadingMore',
        parentParam: 'blockId'                         // 🆕
    },
    areaGrave: {
        singular: 'אחוזת קבר',
        singularArticle: 'את אחוזת הקבר',
        plural: 'אחוזות קבר',
        apiFile: 'areaGraves-api.php',
        searchVar: 'areaGraveSearch',
        tableVar: 'areaGravesTable',
        currentPageVar: 'areaGravesCurrentPage',
        totalPagesVar: 'areaGravesTotalPages',
        dataArrayVar: 'currentAreaGraves',
        isLoadingVar: 'areaGravesIsLoadingMore',
        parentParam: 'plotId'
    },
    grave: {
        singular: 'קבר',
        singularArticle: 'את הקבר',
        plural: 'קברים',
        apiFile: 'graves-api.php',
        searchVar: 'graveSearch',
        tableVar: 'gravesTable',
        currentPageVar: 'gravesCurrentPage',
        totalPagesVar: 'gravesTotalPages',
        dataArrayVar: 'currentGraves',
        isLoadingVar: 'gravesIsLoadingMore',
        parentParam: 'areaGraveId'
    }
};

// ===================================================================
// 6️⃣ טעינת סטטיסטיקות יישות - גלובלי
// ===================================================================
/**
 * טוען סטטיסטיקות של יישות
 * @param {string} entityType - סוג היישות
 * @param {AbortSignal} signal - signal לביטול
 * @param {string|null} parentId - מזהה הורה (עבור אחוזות/חלקות/קברים)
 */
async function loadEntityStats(entityType, signal, parentId = null) {
    const statsConfig = {
        customer: { 
            elements: {
                'totalCustomers': 'total_customers',
                'activeCustomers': 'active',
                'newThisMonth': 'new_this_month'
            }
        },
        purchase: { 
            elements: {
                'totalPurchases': 'total_purchases',
                'completedPurchases': 'completed',
                'newThisMonth': 'new_this_month'
            }
        },
        burial: { 
            elements: {
                'totalBurials': 'total_burials',
                'completedBurials': 'completed',
                'newThisMonth': 'new_this_month'
            }
        },
        areaGrave: { 
            elements: {
                'totalAreaGraves': 'total_area_graves',
                'totalGraves': 'total_graves',
                'newThisMonth': 'new_this_month'
            },
            parentParam: 'plotId'
        },
        plot: { 
            elements: {
                'totalPlots': 'total_plots',
                'totalAreaGraves': 'total_area_graves',
                'newThisMonth': 'new_this_month'
            },
            parentParam: 'blockId'
        },
        grave: { 
            elements: {
                'totalGraves': 'total_graves',
                'occupiedGraves': 'occupied',
                'newThisMonth': 'new_this_month'
            },
            parentParam: 'areaGraveId'
        }
    };
    
    const config = ENTITY_CONFIG[entityType];
    const stats = statsConfig[entityType];
    
    if (!config || !stats) {
        console.error(`❌ Unknown entity type: ${entityType}`);
        return;
    }
    
    try {
        let url = `/dashboard/dashboards/cemeteries/api/${config.apiFile}?action=stats`;
        
        // הוסף parent ID אם נדרש
        if (parentId && stats.parentParam) {
            url += `&${stats.parentParam}=${parentId}`;
        }
        
        const response = await fetch(url, { signal });
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log(`📊 ${entityType} stats:`, result.data);
            
            // עדכן אלמנטים בדום
            Object.entries(stats.elements).forEach(([elementId, fieldName]) => {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = result.data[fieldName] || 0;
                }
            });
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            console.log(`⚠️ ${entityType} stats loading aborted - this is expected`);
            return;
        }
        console.error(`Error loading ${entityType} stats:`, error);
    }
}

// ===================================================================
// 7️⃣ פורמט סטטוס יישות - גלובלי
// ===================================================================
/**
 * ממיר סטטוס לתצוגה עם צבע
 * @param {string} entityType - סוג היישות
 * @param {string|number} status - ערך הסטטוס
 * @returns {string} HTML של הסטטוס
 */
function formatEntityStatus(entityType, status) {
    const statusConfig = {
        customer: {
            1: { text: 'פעיל', color: '#10b981' },
            0: { text: 'לא פעיל', color: '#ef4444' }
        },
        purchase: {
            'pending': { text: 'ממתין', color: '#f59e0b' },
            'approved': { text: 'מאושר', color: '#3b82f6' },
            'completed': { text: 'הושלם', color: '#10b981' },
            'cancelled': { text: 'בוטל', color: '#ef4444' }
        },
        burial: {
            'pending': { text: 'ממתין', color: '#f59e0b' },
            'completed': { text: 'הושלם', color: '#10b981' },
            'cancelled': { text: 'בוטל', color: '#ef4444' }
        },
        areaGrave: {
            1: { text: 'פעיל', color: '#10b981' },
            0: { text: 'לא פעיל', color: '#ef4444' }
        },
        plot: {
            1: { text: 'פעיל', color: '#10b981' },
            0: { text: 'לא פעיל', color: '#ef4444' }
        },
        grave: {
            'available': { text: 'פנוי', color: '#10b981' },
            'occupied': { text: 'תפוס', color: '#ef4444' },
            'reserved': { text: 'שמור', color: '#f59e0b' }
        }
    };
    
    const config = statusConfig[entityType];
    if (!config || !config[status]) {
        return `<span style="background: #6b7280; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">לא ידוע</span>`;
    }
    
    const statusInfo = config[status];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
}

// ===================================================================
// 8️⃣ פורמט מטבע - גלובלי
// ===================================================================
/**
 * ממיר סכום למטבע מפורמט
 * @param {number} amount - הסכום
 * @returns {string} סכום מפורמט בשקלים
 */
function formatCurrency(amount) {
    if (!amount && amount !== 0) return '-';
    return new Intl.NumberFormat('he-IL', {
        style: 'currency',
        currency: 'ILS'
    }).format(amount);
}

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