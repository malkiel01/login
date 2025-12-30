/**
 * File: dashboards/dashboard/cemeteries/assets/js/sidebar-counts.js
 * Version: 4.1.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - תיקון: שימוש ב-cemeteries-api.php לבתי עלמין (במקום cemetery-hierarchy.php)
 * - עקביות מלאה עם cemeteries-management.js v4.0.0
 * - השאר (blocks, plots, graves) נשארים עם cemetery-hierarchy.php (אין להם API ספציפי)
 */

/**
 * Sidebar Counts Updater
 * ======================
 * מעדכן את כל המונים ב-Sidebar באופן אוטומטי
 */

/**
 * עדכון כל המונים ב-Sidebar
 */
async function updateAllSidebarCounts() {
    console.log('🔄 מעדכן מונים ב-Sidebar... (v4.1.0)');
    
    // הצג אנימציית Loading על כל המונים
    document.querySelectorAll('.hierarchy-count').forEach(el => {
        el.classList.add('loading');
    });
    
    try {
        // 1️⃣ בתי עלמין - ✅ cemeteries-api.php (מתוקן!)
        await updateCemeteriesCount();
        
        // 2️⃣ גושים - ✅ cemetery-hierarchy.php (נשאר)
        await updateBlocksCount();
        
        // 3️⃣ חלקות - ✅ cemetery-hierarchy.php (נשאר)
        await updatePlotsCount();
        
        // 4️⃣ אחוזות קבר - ✅ cemetery-hierarchy.php (נשאר)
        await updateAreaGravesCount();
        
        // 5️⃣ קברים - ✅ cemetery-hierarchy.php (נשאר)
        await updateGravesCount();
        
        // 6️⃣ לקוחות - ✅ customers-api.php
        await updateCustomersCount();
        
        // 7️⃣ רכישות - ✅ purchases-api.php
        await updatePurchasesCount();
        
        // 8️⃣ קבורות - ✅ burials-api.php
        await updateBurialsCount();
        
        // 9️⃣ תשלומים - ✅ payments-api.php
        await updatePaymentsCount();
        
        // 🔟 תושבויות - ✅ residency-api.php
        await updateResidencyCount();
        
        // 1️⃣1️⃣ מדינות - ✅ countries-api.php
        await updateCountriesCount();
        
        // 1️⃣2️⃣ ערים - ✅ cities-api.php
        await updateCitiesCount();
        
        console.log('✅ כל המונים עודכנו בהצלחה! (v4.1.0)');
        
    } catch (error) {
        console.error('❌ שגיאה בעדכון מונים:', error);
    }
}

/**
 * עדכון מונה בודד
 */
function updateCount(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value || 0;
        element.classList.remove('loading');
    }
}

/**
 * 1. בתי עלמין
 * ✅ תוקן ב-v4.1.0 - שימוש ב-cemeteries-api.php
 */
async function updateCemeteriesCount() {
    try {
        // ✅ v4.1.0: cemeteries-api.php (כמו customers!)
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=list&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('cemeteriesCount', data.pagination.total);
        }
    } catch (error) {
        console.warn('Failed to load cemeteries count:', error);
        updateCount('cemeteriesCount', 0);
    }
}

/**
 * 2. גושים
 * ✅ נשאר עם cemetery-hierarchy.php (אין API ספציפי)
 */
async function updateBlocksCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=block&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('blocksCount', data.pagination.total);
        }
    } catch (error) {
        console.warn('Failed to load blocks count:', error);
        updateCount('blocksCount', 0);
    }
}

/**
 * 3. חלקות
 * ✅ נשאר עם cemetery-hierarchy.php (אין API ספציפי)
 */
async function updatePlotsCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=plot&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('plotsCount', data.pagination.total);
        }
    } catch (error) {
        console.warn('Failed to load plots count:', error);
        updateCount('plotsCount', 0);
    }
}

/**
 * 4. אחוזות קבר
 * ✅ נשאר עם cemetery-hierarchy.php (אין API ספציפי)
 */
async function updateAreaGravesCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=areaGrave&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('areaGravesCount', data.pagination.total);
        }
    } catch (error) {
        console.warn('Failed to load area graves count:', error);
        updateCount('areaGravesCount', 0);
    }
}

/**
 * 5. קברים
 * ✅ נשאר עם cemetery-hierarchy.php (אין API ספציפי)
 */
async function updateGravesCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=grave&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('gravesCount', data.pagination.total);
        }
    } catch (error) {
        console.warn('Failed to load graves count:', error);
        updateCount('gravesCount', 0);
    }
}

/**
 * 6. לקוחות
 * ✅ customers-api.php
 */
async function updateCustomersCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
        const data = await response.json();
        
        if (data.success && data.data.by_status) {
            // סכום כל הסטטוסים
            const total = Object.values(data.data.by_status)
                .reduce((sum, count) => sum + parseInt(count || 0), 0);
            updateCount('customersCount', total);
        }
    } catch (error) {
        console.warn('Failed to load customers count:', error);
        updateCount('customersCount', 0);
    }
}

/**
 * 7. רכישות
 * ✅ purchases-api.php
 */
async function updatePurchasesCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats');
        const data = await response.json();

        if (data.success && data.data.totals) {
            updateCount('purchasesCount', data.data.totals.total_purchases || 0);
        }
    } catch (error) {
        console.warn('Failed to load purchases count:', error);
        updateCount('purchasesCount', 0);
    }
}

/**
 * 8. קבורות
 * ✅ burials-api.php
 */
async function updateBurialsCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/burials-api.php?action=stats');
        const data = await response.json();

        if (data.success && data.data && data.data.totals) {
            // סה"כ קבורות פעילות
            updateCount('burialsCount', data.data.totals.total_burials || 0);
        }
    } catch (error) {
        console.warn('Failed to load burials count:', error);
        updateCount('burialsCount', 0);
    }
}

/**
 * 9. תשלומים
 * ✅ payments-api.php
 */
async function updatePaymentsCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=list&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('paymentsCount', data.pagination.totalAll || data.pagination.total || 0);
        }
    } catch (error) {
        console.warn('Failed to load payments count:', error);
        updateCount('paymentsCount', 0);
    }
}

/**
 * 10. תושבויות
 * ✅ residency-api.php
 */
async function updateResidencyCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/residency-api.php?action=list&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('residencyCount', data.pagination.total || 0);
        }
    } catch (error) {
        console.warn('Failed to load residency count:', error);
        updateCount('residencyCount', 0);
    }
}

/**
 * 11. מדינות
 * ✅ countries-api.php
 */
async function updateCountriesCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/countries-api.php?action=list&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('countryCount', data.pagination.total || 0);
        }
    } catch (error) {
        console.warn('Failed to load countries count:', error);
        updateCount('countryCount', 0);
    }
}

/**
 * 12. ערים
 * ✅ cities-api.php
 */
async function updateCitiesCount() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cities-api.php?action=list&limit=1');
        const data = await response.json();
        
        if (data.success && data.pagination) {
            updateCount('cityCount', data.pagination.total || 0);
        }
    } catch (error) {
        console.warn('Failed to load cities count:', error);
        updateCount('cityCount', 0);
    }
}

/**
 * ניהול Active State - מסמן את האייטם הפעיל
 */
function setActiveMenuItem(itemId) {
    // הסר active מכל האייטמים
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
    
    // הוסף active לאייטם הנוכחי
    const activeItem = document.getElementById(itemId);
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

// ייצוא לשימוש גלובלי
window.updateAllSidebarCounts = updateAllSidebarCounts;
window.setActiveMenuItem = setActiveMenuItem;

// עדכון אוטומטי כל 5 דקות
setInterval(updateAllSidebarCounts, 5 * 60 * 1000);

// עדכון ראשוני בטעינת הדף
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateAllSidebarCounts);
} else {
    updateAllSidebarCounts();
}

console.log('✅ Sidebar Counts Updater initialized (v4.1.0 - Fixed Cemetery API)');