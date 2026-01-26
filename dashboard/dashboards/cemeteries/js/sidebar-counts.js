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
 * בודק הרשאות לפני כל קריאת API
 */
async function updateAllSidebarCounts() {

    // פונקציית עזר לבדיקת הרשאה
    const canView = (module) => window.hasPermission ? window.hasPermission(module, 'view') : true;

    // הצג אנימציית Loading על כל המונים
    document.querySelectorAll('.hierarchy-count').forEach(el => {
        el.classList.add('loading');
    });

    try {
        // 1️⃣ בתי עלמין
        if (canView('cemeteries')) await updateCemeteriesCount();

        // 2️⃣ גושים
        if (canView('blocks')) await updateBlocksCount();

        // 3️⃣ חלקות
        if (canView('plots')) await updatePlotsCount();

        // 4️⃣ אחוזות קבר
        if (canView('areaGraves')) await updateAreaGravesCount();

        // 5️⃣ קברים
        if (canView('graves')) await updateGravesCount();

        // 6️⃣ לקוחות
        if (canView('customers')) await updateCustomersCount();

        // 7️⃣ רכישות
        if (canView('purchases')) await updatePurchasesCount();

        // 8️⃣ קבורות
        if (canView('burials')) await updateBurialsCount();

        // 9️⃣ תשלומים
        if (canView('payments')) await updatePaymentsCount();

        // 🔟 תושבויות
        if (canView('residency')) await updateResidencyCount();

        // 1️⃣1️⃣ מדינות
        if (canView('countries')) await updateCountriesCount();

        // 1️⃣2️⃣ ערים
        if (canView('cities')) await updateCitiesCount();


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

        if (data.success) {
            // תמיכה בשני פורמטים: pagination.total או total ישירות
            const total = data.pagination?.total ?? data.total ?? 0;
            updateCount('residencyCount', total);
        }
    } catch (error) {
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

