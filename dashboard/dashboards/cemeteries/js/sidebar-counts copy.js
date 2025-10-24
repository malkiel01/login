/**
 * Sidebar Counts Updater
 * ======================
 * מעדכן את כל המונים ב-Sidebar באופן אוטומטי
 * 
 * הוסף קוד זה ל: js/main.js
 * או צור קובץ חדש: js/sidebar-counts.js
 */

/**
 * עדכון כל המונים ב-Sidebar
 */
async function updateAllSidebarCounts() {
    console.log('🔄 מעדכן מונים ב-Sidebar...');
    
    // הצג אנימציית Loading על כל המונים
    document.querySelectorAll('.hierarchy-count').forEach(el => {
        el.classList.add('loading');
    });
    
    try {
        // 1️⃣ בתי עלמין
        await updateCemeteriesCount();
        
        // 2️⃣ גושים
        await updateBlocksCount();
        
        // 3️⃣ חלקות
        await updatePlotsCount();
        
        // 4️⃣ אחוזות קבר
        await updateAreaGravesCount();
        
        // 5️⃣ קברים
        await updateGravesCount();
        
        // 6️⃣ לקוחות
        await updateCustomersCount();
        
        // 7️⃣ רכישות
        await updatePurchasesCount();
        
        // 8️⃣ קבורות
        await updateBurialsCount();
        
        // 9️⃣ תשלומים
        await updatePaymentsCount();
        
        // 🔟 תושבויות
        await updateResidencyCount();
        
        // 1️⃣1️⃣ מדינות
        await updateCountriesCount();
        
        // 1️⃣2️⃣ ערים
        await updateCitiesCount();
        
        console.log('✅ כל המונים עודכנו בהצלחה!');
        
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
 */
async function updateCemeteriesCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=cemetery&limit=1');
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
 */
async function updateBlocksCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=block&limit=1');
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
 */
async function updatePlotsCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=plot&limit=1');
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
 */
async function updateAreaGravesCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=areaGrave&limit=1');
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
 */
async function updateGravesCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=grave&limit=1');
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
 */
async function updateCustomersCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
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
 */
async function updatePurchasesCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats');
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
 */
async function updateBurialsCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/burials-api.php?action=stats');
        const data = await response.json();
        
        if (data.success && data.data) {
            // סה"כ קבורות השנה
            updateCount('burialsCount', data.data.this_year || 0);
        }
    } catch (error) {
        console.warn('Failed to load burials count:', error);
        updateCount('burialsCount', 0);
    }
}

/**
 * 9. תשלומים
 */
async function updatePaymentsCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=list&limit=1');
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
 */
async function updateResidencyCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/residency-api.php?action=list&limit=1');
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
 */
async function updateCountriesCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/countries-api.php?action=list&limit=1');
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
 */
async function updateCitiesCount() {
    try {
        const response = await 
fetch('/dashboard/dashboards/cemeteries/api/cities-api.php?action=list&limit=1');
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

console.log('✅ Sidebar Counts Updater initialized');
