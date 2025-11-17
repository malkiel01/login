/*
 * File: dashboards/dashboard/cemeteries/assets/js/area-graves-management.js
 * Version: 1.5.4
 * Updated: 2025-11-16
 * Author: Malkiel
 * Change Summary:
 * - v1.5.4: 🐛 תיקון שתי בעיות קריטיות:
 *   - תיקון: שדה חיפוש מוסתר - הסרת style="display: none;"
 *   - תיקון: שכפול טבלה - הוספת await ל-initAreaGravesTable
 *   - הפיכת renderAreaGravesRows ל-async function
 */

console.log('🚀 area-graves-management.js v1.5.4 - Loading...');

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentAreaGraves = [];
let areaGraveSearch = null;
let areaGravesTable = null;
let editingAreaGraveId = null;

let isSearchMode = false;      // האם אנחנו במצב חיפוש?
let currentQuery = '';         // מה החיפוש הנוכחי?
let searchResults = [];        // תוצאות החיפוש

// ⭐ שמירת ה-plot context הנוכחי
let currentPlotId = null;
let currentPlotName = null;

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let areaGravesCurrentPage = 1;
let areaGravesTotalPages = 1;
let areaGravesIsLoadingMore = false;


// ===================================================================
// טעינת אחוזות קבר (הפונקציה הראשית)
// ===================================================================
async function loadAreaGraves_old(plotId = null, plotName = null, forceReset = false) {
    const signal = OperationManager.start('areaGrave');

    // ⭐ לוגיקת סינון
    if (plotId === null && plotName === null && !forceReset) {
        if (window.currentPlotId !== null || currentPlotId !== null) {
            currentPlotId = null;
            currentPlotName = null;
            window.currentPlotId = null;
            window.currentPlotName = null;
        }
    } else if (forceReset) {
        currentPlotId = null;
        currentPlotName = null;
        window.currentPlotId = null;
        window.currentPlotName = null;
    } else {
        currentPlotId = plotId;
        currentPlotName = plotName;
        window.currentPlotId = plotId;
        window.currentPlotName = plotName;
    }
    
    window.currentPlotId = currentPlotId;
    window.currentPlotName = currentPlotName;
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'areaGrave';
    window.currentParentId = plotId;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'areaGrave';
    }
    
    // ⭐ נקה
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'areaGrave' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'areaGrave' });
    }
    
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('areaGravesItem');
    }
    
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        const breadcrumbData = { 
            areaGrave: { name: plotName ? `אחוזות קבר של ${plotName}` : 'אחוזות קבר' }
        };
        if (plotId && plotName) {
            breadcrumbData.plot = { id: plotId, name: plotName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = plotName ? `אחוזות קבר - ${plotName}` : 'ניהול אחוזות קבר - מערכת בתי עלמין';
    
    // ⭐ בנה מבנה
    await buildAreaGravesContainer(signal, plotId, plotName);
    
    if (OperationManager.shouldAbort('areaGrave')) {
        return;
    }

    // ⭐ ספירת טעינות גלובלית
    if (!window.areaGravesLoadCounter) {
        window.areaGravesLoadCounter = 0;
    }
    window.areaGravesLoadCounter++;
    
    // ⭐ השבתה זמנית של UniversalSearch לבדיקת ביצועים
    // השמד חיפוש קודם
    if (areaGraveSearch && typeof areaGraveSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous areaGraveSearch instance...');
        areaGraveSearch.destroy();
        areaGraveSearch = null; 
        window.areaGraveSearch = null;
    }
    
    // אתחל חיפוש חדש
    console.log('🆕 Creating fresh areaGraveSearch instance...');
    
    areaGraveSearch = await initAreaGravesSearch(signal, plotId);    
    
    if (OperationManager.shouldAbort('areaGrave')) {
        console.log('⚠️ AreaGrave operation aborted');
        return;
    }


    try {
        // ⭐ איפוס מצב לפני טעינה חדשה
        areaGravesCurrentPage = 1;
        currentAreaGraves = [];
        
        // בנה את ה-URL
        let apiUrl = '/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=list&limit=200&page=1';
        apiUrl += '&orderBy=createDate&sortDirection=DESC';
        
        if (plotId) {
            apiUrl += `&plotId=${plotId}`;
        }
        
        // שלח בקשה לשרת
        const response = await fetch(apiUrl, { signal: signal });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        // ⭐ עדכון מצב Infinite Scroll
        if (result.pagination) {
            areaGravesTotalPages = result.pagination.pages;
            areaGravesCurrentPage = result.pagination.page;
        }
        
        if (result.success && result.data) {
            currentAreaGraves = result.data;
            
            // ⭐⭐⭐ לוג פשוט ומסודר
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ טעינה: ${window.areaGravesLoadCounter}
╠════════════════════════════════════════════════════════════════════
║ כמות ערכים בטעינה: ${result.data.length}
║ מספר ערך תחילת טעינה נוכחית: ${result.debug?.results_info?.from_index || 1}
║ מספר ערך סוף טעינה נוכחית: ${result.debug?.results_info?.to_index || result.data.length}
║ סך כל הערכים שנטענו עד כה: ${currentAreaGraves.length}
║ שדה למיון: ${result.debug?.sql_info?.order_field || 'createDate'}
║ סוג מיון: ${result.debug?.sql_info?.sort_direction || 'DESC'}
╠════════════════════════════════════════════════════════════════════
║ הערכים שנטענו כעת:
╚════════════════════════════════════════════════════════════════════
            `);
            console.table(result.data.map((item, idx) => ({
                '#': idx + 1,
                'unicId': item.unicId,
                'שם': item.areaGraveNameHe,
                'קואורדינטות': item.coordinates || '-',
                'תאריך יצירה': item.createDate
            })));
            
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ הערכים שנטענו עד כה (סה"כ):
╚════════════════════════════════════════════════════════════════════
            `);
            console.table(currentAreaGraves.map((item, idx) => ({
                '#': idx + 1,
                'unicId': item.unicId,
                'שם': item.areaGraveNameHe
            })));
            
            // קרא לרינדור ישירות
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
                renderAreaGravesRows(result.data, tableBody, result.pagination, signal);
            } else {
                console.error('❌ tableBody element not found!');
            }
        } else {
            console.error('❌ טעינה נכשלה:', result.error || 'Unknown error');
            showToast('שגיאה בטעינת נתונים: ' + (result.error || 'שגיאה לא ידועה'), 'error');
            
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px;">
                            <div style="color: #ef4444;">
                                <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">שגיאה בטעינת נתונים</div>
                                <div>${result.error || 'שגיאה לא ידועה'}</div>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('❌ שגיאה קריטית:', error.message);
        showToast('שגיאה בטעינת נתונים: ' + error.message, 'error');
        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #ef4444;">
                            <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">שגיאה בטעינת נתונים</div>
                            <div>${error.message}</div>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    // טען סטטיסטיקות
    await loadAreaGraveStats(signal, plotId);
}
async function loadBrowseData(plotId = null, signal = null) {
    areaGravesCurrentPage = 1;
    currentAreaGraves = [];
    
    let apiUrl = '/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=list&limit=200&page=1';
    apiUrl += '&orderBy=createDate&sortDirection=DESC';
    
    if (plotId) {
        apiUrl += `&plotId=${plotId}`;
    }
    
    const response = await fetch(apiUrl, { signal });
    const result = await response.json();
    
    if (result.success && result.data) {
        currentAreaGraves = result.data;
        
        if (result.pagination) {
            areaGravesTotalPages = result.pagination.pages;
            areaGravesCurrentPage = result.pagination.page;
        }
        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            renderAreaGravesRows(result.data, tableBody, result.pagination, signal);
        }
    }
}
async function loadAreaGraves(plotId = null, plotName = null, forceReset = false) {
    const signal = OperationManager.start('areaGrave');

    // ⭐ איפוס מצב חיפוש
    isSearchMode = false;
    currentQuery = '';
    searchResults = [];

    // ⭐ לוגיקת סינון
    if (plotId === null && plotName === null && !forceReset) {
        if (window.currentPlotId !== null || currentPlotId !== null) {
            currentPlotId = null;
            currentPlotName = null;
            window.currentPlotId = null;
            window.currentPlotName = null;
        }
    } else if (forceReset) {
        currentPlotId = null;
        currentPlotName = null;
        window.currentPlotId = null;
        window.currentPlotName = null;
    } else {
        currentPlotId = plotId;
        currentPlotName = plotName;
        window.currentPlotId = plotId;
        window.currentPlotName = plotName;
    }
    
    window.currentPlotId = currentPlotId;
    window.currentPlotName = currentPlotName;
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'areaGrave';
    window.currentParentId = plotId;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'areaGrave';
    }
    
    // ⭐ נקה
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'areaGrave' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'areaGrave' });
    }
    
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('areaGravesItem');
    }
    
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        const breadcrumbData = { 
            areaGrave: { name: plotName ? `אחוזות קבר של ${plotName}` : 'אחוזות קבר' }
        };
        if (plotId && plotName) {
            breadcrumbData.plot = { id: plotId, name: plotName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = plotName ? `אחוזות קבר - ${plotName}` : 'ניהול אחוזות קבר - מערכת בתי עלמין';
    
    // ⭐ בנה מבנה
    await buildAreaGravesContainer(signal, plotId, plotName);
    
    if (OperationManager.shouldAbort('areaGrave')) {
        return;
    }

    // ⭐ ספירת טעינות גלובלית
    if (!window.areaGravesLoadCounter) {
        window.areaGravesLoadCounter = 0;
    }
    window.areaGravesLoadCounter++;
    
    // השמד חיפוש קודם
    if (areaGraveSearch && typeof areaGraveSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous areaGraveSearch instance...');
        areaGraveSearch.destroy();
        areaGraveSearch = null; 
        window.areaGraveSearch = null;
    }
    
    // ⭐ אתחול UniversalSearch - פעם אחת!
    console.log('🆕 Creating fresh areaGraveSearch instance...');
    areaGraveSearch = await initAreaGravesSearch(signal, plotId);
    
    if (OperationManager.shouldAbort('areaGrave')) {
        console.log('⚠️ AreaGrave operation aborted');
        return;
    }

    // ⭐ טעינה ישירה (Browse Mode) - פעם אחת!
    await loadBrowseData(plotId, signal);
    
    // טען סטטיסטיקות
    await loadAreaGraveStats(signal, plotId);
}


// ===================================================================
// 📥 טעינת עוד אחוזות קבר (Infinite Scroll)
// ===================================================================
async function appendMoreAreaGraves() {
    // בדיקות בסיסיות
    if (areaGravesIsLoadingMore) {
        return false;
    }
    
    if (areaGravesCurrentPage >= areaGravesTotalPages) {
        return false;
    }
    
    areaGravesIsLoadingMore = true;
    const nextPage = areaGravesCurrentPage + 1;
    
    // ⭐ עדכון מונה טעינות
    if (!window.areaGravesLoadCounter) {
        window.areaGravesLoadCounter = 0; 
    }
    window.areaGravesLoadCounter++;
    
    try {
        // בנה URL לעמוד הבא
        let apiUrl = `/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=list&limit=200&page=${nextPage}`;
        apiUrl += '&orderBy=createDate&sortDirection=DESC';
        
        if (currentPlotId) {
            apiUrl += `&plotId=${currentPlotId}`;
        }
        
        // שלח בקשה
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
            // ⭐ שמור את הגודל הקודם לפני ההוספה
            const previousTotal = currentAreaGraves.length;
            
            // ⭐ הוסף לנתונים הקיימים
            currentAreaGraves = [...currentAreaGraves, ...result.data];
            areaGravesCurrentPage = nextPage;
            
            // ⭐⭐⭐ לוג פשוט ומסודר
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ טעינה: ${window.areaGravesLoadCounter}
╠════════════════════════════════════════════════════════════════════
║ כמות ערכים בטעינה: ${result.data.length}
║ מספר ערך תחילת טעינה נוכחית: ${result.debug?.results_info?.from_index || (previousTotal + 1)}
║ מספר ערך סוף טעינה נוכחית: ${result.debug?.results_info?.to_index || currentAreaGraves.length}
║ סך כל הערכים שנטענו עד כה: ${currentAreaGraves.length}
║ שדה למיון: ${result.debug?.sql_info?.order_field || 'createDate'}
║ סוג מיון: ${result.debug?.sql_info?.sort_direction || 'DESC'}
╠════════════════════════════════════════════════════════════════════
║ הערכים שנטענו כעת:
╚════════════════════════════════════════════════════════════════════
            `);
            console.table(result.data.map((item, idx) => ({
                '#': previousTotal + idx + 1,
                'unicId': item.unicId,
                'שם': item.areaGraveNameHe,
                'קואורדינטות': item.coordinates || '-',
                'תאריך יצירה': item.createDate
            })));
            
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ הערכים שנטענו עד כה (סה"כ):
╚════════════════════════════════════════════════════════════════════
            `);
            console.table(currentAreaGraves.map((item, idx) => ({
                '#': idx + 1,
                'unicId': item.unicId,
                'שם': item.areaGraveNameHe
            })));
            
            // ⭐ עדכן את הטבלה
            if (areaGravesTable) {
                areaGravesTable.setData(currentAreaGraves);
            }
            
            return true;
        } else {
            return false;
        }
        
    } catch (error) {
        console.error('❌ Error loading more data:', error);
        showToast('שגיאה בטעינת נתונים נוספים: ' + error.message, 'error');
        return false;
    } finally {
        areaGravesIsLoadingMore = false;
    }
}


// ===================================================================
// בניית המבנה
// ===================================================================
async function buildAreaGravesContainer_old(signal, plotId = null, plotName = null) {
    console.log('🏗️ Building area graves container...');
    
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
        console.log('⚠️ main-container not found, creating one...');
        const mainContent = document.querySelector('.main-content');
        mainContainer = document.createElement('div');
        mainContainer.className = 'main-container';
        
        const actionBar = mainContent.querySelector('.action-bar');
        if (actionBar) {
            actionBar.insertAdjacentElement('afterend', mainContainer);
        } else {
            mainContent.appendChild(mainContainer);
        }
    }
    
    // ⭐⭐⭐ טעינת כרטיס מלא במקום indicator פשוט!
    let topSection = '';
    if (plotId && plotName) {
        console.log('🎴 Creating full plot card...');
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createPlotCard === 'function') {
            try {
                topSection = await createPlotCard(plotId, signal);
                console.log('✅ Plot card created successfully');
            } catch (error) {
                // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
                if (error.name === 'AbortError') {
                    console.log('⚠️ Plot card loading aborted');
                    return;
                }
                console.error('❌ Error creating block card:', error);
            }
        } else {
            console.warn('⚠️ createPlotCard function not found');
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            console.log('⚠️ Using simple filter indicator as fallback');
            topSection = `
                <div class="filter-indicator" style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🏘️</span>
                        <div>
                            <div style="font-size: 12px; opacity: 0.9;">מציג אחוזות קבר עבור</div>
                            <div style="font-size: 16px; font-weight: 600;">${plotName}</div>
                        </div>
                    </div>
                    <button onclick="loadAreaGraves(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        ✕ הצג הכל
                    </button>
                </div>
            `;
        }
    }

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Build areaGraves container aborted before innerHTML');
        return;
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
        <div id="areaGraveSearchSection" class="search-section" style="display: none;"></div>
        
        <div class="table-container">
            <table id="mainTable" class="data-table">
                <thead>
                    <tr id="tableHeaders">
                        <th style="text-align: center;">טוען...</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td style="text-align: center; padding: 40px;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">טוען אחוזות קבר...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
  
    console.log('✅ Area graves container built');
}
async function buildAreaGravesContainer(signal, plotId = null, plotName = null) {
    console.log('🏗️ Building area graves container...');
    
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
        console.log('⚠️ main-container not found, creating one...');
        const mainContent = document.querySelector('.main-content');
        mainContainer = document.createElement('div');
        mainContainer.className = 'main-container';
        
        const actionBar = mainContent.querySelector('.action-bar');
        if (actionBar) {
            actionBar.insertAdjacentElement('afterend', mainContainer);
        } else {
            mainContent.appendChild(mainContainer);
        }
    }

    // ⭐⭐⭐ טעינת כרטיס מלא במקום indicator פשוט!
    let topSection = '';
    if (plotId && plotName) {
        console.log('🎴 Creating full plot card...');
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createPlotCard === 'function') {
            try {
                topSection = await createPlotCard(plotId, signal);
                console.log('✅ Plot card created successfully');
            } catch (error) {
                // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
                if (error.name === 'AbortError') {
                    console.log('⚠️ Plot card loading aborted');
                    return;
                }
                console.error('❌ Error creating block card:', error);
            }
        } else {
            console.warn('⚠️ createPlotCard function not found');
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            console.log('⚠️ Using simple filter indicator as fallback');
            topSection = `
                <div class="filter-indicator" style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🏘️</span>
                        <div>
                            <div style="font-size: 12px; opacity: 0.9;">מציג אחוזות קבר עבור</div>
                            <div style="font-size: 16px; font-weight: 600;">${plotName}</div>
                        </div>
                    </div>
                    <button onclick="loadAreaGraves(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        ✕ הצג הכל
                    </button>
                </div>
            `;
        }
    }

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Build areaGraves container aborted before innerHTML');
        return;
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
        <div id="areaGraveSearchSection" class="search-section"></div>
        
        <div class="table-container">
            <table id="mainTable" class="data-table">
                <thead>
                    <tr id="tableHeaders">
                        <th style="text-align: center;">טוען...</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td style="text-align: center; padding: 40px;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">טוען אחוזות קבר...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
  
    console.log('✅ Area graves container built');
}

// ===================================================================
// אתחול UniversalSearch - עם Pagination!
// ===================================================================
async function initAreaGravesSearch_old(signal, plotId) {
    console.log('🔍 אתחול חיפוש שורות קבר...');
    
    // קונפיגורציה
    const config = {
        entityType: 'area-grave',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
        
        searchableFields: [
            { key: 'areaGraveNameHe', label: 'שם אחוזת קבר' },
            { key: 'coordinates', label: 'קואורדינטות' },
            { key: 'graveType', label: 'סוג קבר' }
        ],
        
        displayColumns: [
            { key: 'areaGraveNameHe', label: 'שם' },
            { key: 'coordinates', label: 'מיקום' },
            { key: 'graveType', label: 'סוג' },
            { key: 'graves_count', label: 'כמות קברים' }
        ],

        searchContainerSelector: '#areaGraveSearchSection',
        resultsContainerSelector: '#tableBody',  
        
        // ⭐ Infinite Scroll אמיתי - טעינה מדורגת
        apiLimit: 200,  // ⭐ טוען 200 רשומות מהשרת בכל בקשה
        showPagination: false,  // ⭐ ללא footer - infinite scroll!
        
        apiParams: {
            level: 'area-grave',
            plotId: plotId
        },
        
        renderFunction: (data, container, pagination, signal) => {
            // קריאה לפונקציה המקורית עם כל הפרמטרים
            renderAreaGravesRows(data, container, pagination, signal);
        },
        
        callbacks: {
            // ⭐ כשנתונים נטענו
            onDataLoaded: (response) => {
                console.log('✅ נתונים נטענו:', response.data.length);
                
                // עדכון מונה כולל ב-TableManager
                if (window.areaGravesTable && response.pagination) {
                    window.areaGravesTable.updateTotalItems(response.pagination.total);
                }
            },
            
            // ⭐ כשמחליפים עמוד
            onPageChange: (newPage) => {
                console.log('📄 מעבר לעמוד:', newPage);
            }
        }
    };
    
    // יצירת instance
    const searchInstance = window.initUniversalSearch(config);
    
    // שמירה גלובלית
    window.areaGraveSearch = searchInstance;
    
    // טעינה ראשונית
    await searchInstance.search();
    
    return searchInstance;
}
async function initAreaGravesSearch1(signal, plotId) {
    console.log('🔍 אתחול חיפוש שורות קבר...');
    
    // ⭐ טוען searchableFields מהשרת
    let searchableFields = [];

    try {
        const fieldsResponse = await fetch(
            `/dashboard/dashboards/cemeteries/api/get-config.php?type=areaGrave&section=searchableFields`,
            { signal: signal }
        );
        const fieldsResult = await fieldsResponse.json();
        
        if (fieldsResult.success && fieldsResult.data) {
            searchableFields = fieldsResult.data;
        }
    } catch (error) {
        console.error('❌ Error loading searchableFields:', error);
    }

    // קונפיגורציה
    const config = {
        entityType: 'area-grave',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
        
        searchableFields: searchableFields || [],
        
        displayColumns: [
            { key: 'areaGraveNameHe', label: 'שם' },
            { key: 'coordinates', label: 'מיקום' },
            { key: 'graveType', label: 'סוג' },
            { key: 'graves_count', label: 'כמות קברים' }
        ],

        searchContainerSelector: '#areaGraveSearchSection',
        resultsContainerSelector: '#tableBody',  
        
        // ⭐ Infinite Scroll אמיתי - טעינה מדורגת
        apiLimit: 200,  // ⭐ טוען 200 רשומות מהשרת בכל בקשה
        showPagination: false,  // ⭐ ללא footer - infinite scroll!
        
        apiParams: {
            level: 'area-grave',
            plotId: plotId
        },
        
        renderFunction: (data, container, pagination, signal) => {
            // ⭐ עדכן מצב חיפוש
            isSearchMode = true;
            
            // שמור תוצאות
            if (pagination && pagination.page === 1) {
                searchResults = data;
            } else {
                searchResults = [...searchResults, ...data];
            }

            // קריאה לפונקציה המקורית עם כל הפרמטרים
            renderAreaGravesRows(data, container, pagination, signal);
        },
        
        callbacks: {
            // ⭐ כשנתונים נטענו
            onDataLoaded: (response) => {
                console.log('✅ נתונים נטענו:', response.data.length);
                
                // עדכון מונה כולל ב-TableManager
                if (window.areaGravesTable && response.pagination) {
                    window.areaGravesTable.updateTotalItems(response.pagination.total);
                }
            },
            
            // ⭐ כשמנקים חיפוש
            onClear: () => {
                isSearchMode = false;
                currentQuery = '';
                searchResults = [];
                
                // חזרה ל-Browse
                loadBrowseData(currentPlotId);
            }
        }
    };

    // ⭐ הוסף פילטר plotId אם קיים
    if (plotId) {
        config.dataSource.plotId = plotId;
    }
    
    // יצירת instance
    const searchInstance = window.initUniversalSearch(config);
    
    // שמירה גלובלית
    window.areaGraveSearch = searchInstance;
    
    return searchInstance;
}
async function initAreaGravesSearch(signal, plotId) {
    console.log('🔍 אתחול חיפוש שורות קבר...');
    
    // ⭐ טוען searchableFields מהשרת
    let searchableFields = [];

    try {
        const fieldsResponse = await fetch(
            `/dashboard/dashboards/cemeteries/api/get-config.php?type=areaGrave&section=searchableFields`,
            { signal: signal }
        );
        const fieldsResult = await fieldsResponse.json();
        
        if (fieldsResult.success && fieldsResult.data) {
            searchableFields = fieldsResult.data;
        }
    } catch (error) {
        console.error('❌ Error loading searchableFields:', error);
    }

    // ⭐ השתמש בקונפיג הישן - זה עובד!
    const config = {
        entityType: 'area-grave',  // ⭐ חובה!
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
        
        searchableFields: searchableFields || [],
        
        displayColumns: [
            { key: 'areaGraveNameHe', label: 'שם' },
            { key: 'coordinates', label: 'מיקום' },
            { key: 'graveType', label: 'סוג' },
            { key: 'graves_count', label: 'כמות קברים' }
        ],

        searchContainerSelector: '#areaGraveSearchSection',
        resultsContainerSelector: '#tableBody',  
        
        // ⭐ Infinite Scroll אמיתי - טעינה מדורגת
        apiLimit: 200,
        showPagination: false,
        
        apiParams: {
            level: 'area-grave',
            plotId: plotId
        },
        
        renderFunction: (data, container, pagination, signal) => {
            // ⭐ עדכן מצב חיפוש
            isSearchMode = true;
            
            // שמור תוצאות
            if (pagination && pagination.page === 1) {
                searchResults = data;
            } else {
                searchResults = [...searchResults, ...data];
            }

            // קריאה לפונקציה המקורית עם כל הפרמטרים
            renderAreaGravesRows(data, container, pagination, signal);
        },
        
        callbacks: {
            // ⭐ כשנתונים נטענו
            onDataLoaded: (response) => {
                console.log('✅ נתונים נטענו:', response.data.length);
                
                // עדכון מונה כולל ב-TableManager
                if (window.areaGravesTable && response.pagination) {
                    window.areaGravesTable.updateTotalItems(response.pagination.total);
                }
            },
            
            // ⭐ כשמנקים חיפוש
            onClear: () => {
                isSearchMode = false;
                currentQuery = '';
                searchResults = [];
                
                // חזרה ל-Browse
                loadBrowseData(currentPlotId);
            }
        }
    };
    
    // יצירת instance
    const searchInstance = window.initUniversalSearch(config);
    
    // שמירה גלובלית
    window.areaGraveSearch = searchInstance;
    
    return searchInstance;
}

// ===================================================================
// אתחול TableManager - עם Scroll Loading!
// ===================================================================
async function initAreaGravesTable(data, totalItems = null, signal) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (areaGravesTable) {
        areaGravesTable.config.totalItems = actualTotalItems;
        areaGravesTable.setData(data);
        return areaGravesTable;
    }

    async function loadColumnsFromConfig(entityType = 'areaGrave', signal) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${entityType}&section=table_columns`, {
                signal: signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success || !result.data) {
                throw new Error(result.error || 'Failed to load columns config');
            }

            const columns = result.data.map(col => {
                const column = {
                    field: col.field,
                    label: col.title,
                    width: col.width,
                    sortable: col.sortable !== false
                };
                
                // טיפול בסוגים מיוחדים
                switch(col.type) {
                    case 'link':
                        column.render = (areaGrave) => {
                            return `<a href="#" onclick="handleAreaGraveDoubleClick('${areaGrave.unicId}', '${areaGrave.areaGraveNameHe?.replace(/'/g, "\\'")}'); return false;" 
                                    style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                ${areaGrave.areaGraveNameHe}
                            </a>`;
                        };
                        break;
                        
                    case 'coordinates':
                        column.render = (areaGrave) => {
                            const coords = areaGrave.coordinates || '-';
                            return `<span style="font-family: monospace; font-size: 12px;">${coords}</span>`;
                        };
                        break;
                        
                    case 'graveType':
                        column.render = (areaGrave) => {
                            const typeName = getGraveTypeName(areaGrave.graveType);
                            return `<span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${typeName}</span>`;
                        };
                        break;
                        
                    case 'row':
                        column.render = (areaGrave) => {
                            const rowName = areaGrave.row_name || areaGrave.lineNameHe || '-';
                            return `<span style="color: #6b7280;">📏 ${rowName}</span>`;
                        };
                        break;
                        
                    case 'badge':
                        column.render = (areaGrave) => {
                            const count = areaGrave[col.field] || 0;
                            return `<span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">${count}</span>`;
                        };
                        break;
                        
                    case 'date':
                        column.render = (areaGrave) => formatDate(areaGrave[col.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteAreaGrave('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;

                    default:
                        // עמודת טקסט רגילה
                        if (!column.render) {
                            column.render = (item) => item[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
        } catch (error) {
            // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
            if (error.name === 'AbortError') {
                console.log('⚠️ Columns loading aborted');
                return [];
            }
            console.error('Failed to load columns config:', error);
            return [];
        }
    }

    // קודם טען את העמודות
    const columns = await loadColumnsFromConfig('areaGrave', signal);

    // בדוק אם בוטל
    if (signal && signal.aborted) {
        console.log('⚠️ AreaGrave table initialization aborted');
        return null;
    }

    areaGravesTable = new TableManager({

        tableSelector: '#mainTable',   
        columns: columns,
        data: data,      
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: false,

        tableHeight: 'calc(100vh - 650px)',  // גובה דינמי לפי מסך
        tableMinHeight: '500px',

        
        // ============================================
        // ⭐ 3 פרמטרים חדשים - הוסף כאן!
        // ============================================
        totalItems: actualTotalItems,        // ⭐ סה"כ רשומות במערכת (מה-pagination)
        scrollLoadBatch: 100,                // ⭐ טען 100 שורות בכל גלילה (client-side)
        itemsPerPage: 999999,                // ⭐ עמוד אחד גדול = כל הנתונים
        scrollThreshold: 200,                // ⭐ התחל טעינה 200px לפני התחתית
        showPagination: false,               // ⭐ ללא footer pagination

 
        // scrollLoadBatch: 0,                  // ⭐ 0 = ללא infinite scroll
        // itemsPerPage: 100,                   // ⭐ 100 רשומות לעמוד
        // showPagination: true,                // ⭐ הצג footer pagination
        // paginationOptions: [25, 50, 100, 200], // ⭐ אפשרויות בסלקט

        // ============================================
        // הגדרות קיימות
        // ============================================
        
    // ============================================
    // ⭐⭐⭐ Callback לטעינת עוד נתונים מהשרת
    // ============================================

        onLoadMore_old: async () => {
            console.log('📥 TableManager detected scroll - loading more from server...');
            
            const success = await appendMoreAreaGraves();
            
            if (!success) {
                areaGravesTable.state.hasMoreData = false;
                console.log('📭 No more data to load');
            }
        },

        onLoadMore: async () => {
            if (isSearchMode) {
                // ⭐ חיפוש - טען דרך UniversalSearch
                if (areaGraveSearch && typeof areaGraveSearch.loadNextPage === 'function') {
                    if (areaGraveSearch.state.currentPage >= areaGraveSearch.state.totalPages) {
                        areaGravesTable.state.hasMoreData = false;
                        return;
                    }
                    await areaGraveSearch.loadNextPage();
                }
            } else {
                // ⭐ Browse - טען ישירות
                const success = await appendMoreAreaGraves();
                if (!success) {
                    areaGravesTable.state.hasMoreData = false;
                }
            }
        },

        renderFunction: (pageData) => {
            // ⭐ זה לא ישמש - UniversalSearch ירנדר ישירות
            return renderAreaGravesRows(pageData);
        },
    

        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = areaGravesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    window.areaGravesTable = areaGravesTable;
    
    return areaGravesTable;
}


// ===================================================================
// רינדור שורות - עם סינון client-side! (⭐⭐ כמו ב-blocks!)
// ===================================================================

/**
 * רינדור שורות טבלה - פונקציה מלאה עם כל הלוגיקה!
 * v1.3.2 - שוחזרה הפונקציה המקורית המלאה
 */
function renderAreaGravesRows_old(data, container, pagination = null, signal = null) {
    // ⭐⭐ סינון client-side לפי plotId
    let filteredData = data;
    if (currentPlotId) {
        filteredData = data.filter(ag => {
            // ⭐ תמיכה בכל האפשרויות
            const agPlotId = ag.plotId || ag.plot_id || ag.PlotId;
            
            // ⭐ המרה למחרוזת להשוואה אמינה
            return String(agPlotId) === String(currentPlotId);
        });
    }
    
    // ⭐ עדכן את totalItems מה-pagination (סה"כ במערכת, לא רק מה שנטען!)
    const totalItems = pagination?.totalAll || pagination?.total || filteredData.length;
    
    console.log('🔍 [DEBUG renderAreaGravesRows]');
    console.log('  pagination:', pagination);
    console.log('  totalItems calculated:', totalItems);
    console.log('  filteredData.length:', filteredData.length);

    if (filteredData.length === 0) {
        if (areaGravesTable) {
            areaGravesTable.setData([]);
        }
        
        // ⭐⭐⭐ הודעה מותאמת לחלקה ריקה!
        if (currentPlotId && currentPlotName) {
            // נכנסנו לחלקה ספציפית ואין אחוזות קבר
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🏘️</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין אחוזות קבר בחלקה ${currentPlotName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                החלקה עדיין לא מכילה אחוזות קבר. תוכל להוסיף אחוזת קבר חדשה
                            </div>
                            <button 
                                onclick="if(typeof FormHandler !== 'undefined' && FormHandler.openForm) { FormHandler.openForm('areaGrave', '${currentPlotId}', null); } else { alert('FormHandler לא זמין'); }" 
                                style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%); 
                                       color: white; 
                                       border: none; 
                                       padding: 12px 24px; 
                                       border-radius: 8px; 
                                       font-size: 15px; 
                                       font-weight: 600; 
                                       cursor: pointer; 
                                       box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                                       transition: all 0.2s;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';">
                                ➕ הוסף אחוזת קבר ראשונה
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // חיפוש כללי שלא מצא תוצאות
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #9ca3af;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                            <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                        </div>
                    </td>
                </tr>
            `;
        }
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && areaGravesTable) {
        areaGravesTable = null;
        window.areaGravesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!areaGravesTable || !tableWrapperExists) {
        initAreaGravesTable(filteredData, totalItems, signal);
    } else {
        if (areaGravesTable.config) {
            areaGravesTable.config.totalItems = totalItems;
        }
        
        areaGravesTable.setData(filteredData);
    }
    
    // ⭐ עדכן את התצוגה של UniversalSearch
    if (areaGraveSearch) {
        areaGraveSearch.state.totalResults = totalItems;
        areaGraveSearch.updateCounter();
    }
}
function renderAreaGravesRows(data, container, pagination = null, signal = null) {
    // ⭐⭐ סינון client-side לפי plotId
    let filteredData = data;

    if (!isSearchMode && currentPlotId) {
        filteredData = data.filter(ag => {
            const agPlotId = ag.plotId || ag.plot_id || ag.PlotId;
            return String(agPlotId) === String(currentPlotId);
        });
    }
    
    // ⭐ עדכן את totalItems מה-pagination (סה"כ במערכת, לא רק מה שנטען!)
    const totalItems = pagination?.totalAll || pagination?.total || filteredData.length;
    
    console.log('🔍 [DEBUG renderAreaGravesRows]');
    console.log('  pagination:', pagination);
    console.log('  totalItems calculated:', totalItems);
    console.log('  filteredData.length:', filteredData.length);

    if (filteredData.length === 0) {
        if (areaGravesTable) {
            areaGravesTable.setData([]);
        }
        
        // ⭐⭐⭐ הודעה מותאמת לחלקה ריקה!
        if (currentPlotId && currentPlotName) {
            // נכנסנו לחלקה ספציפית ואין אחוזות קבר
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🏘️</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין אחוזות קבר בחלקה ${currentPlotName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                החלקה עדיין לא מכילה אחוזות קבר. תוכל להוסיף אחוזת קבר חדשה
                            </div>
                            <button 
                                onclick="if(typeof FormHandler !== 'undefined' && FormHandler.openForm) { FormHandler.openForm('areaGrave', '${currentPlotId}', null); } else { alert('FormHandler לא זמין'); }" 
                                style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%); 
                                       color: white; 
                                       border: none; 
                                       padding: 12px 24px; 
                                       border-radius: 8px; 
                                       font-size: 15px; 
                                       font-weight: 600; 
                                       cursor: pointer; 
                                       box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                                       transition: all 0.2s;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';">
                                ➕ הוסף אחוזת קבר ראשונה
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // חיפוש כללי שלא מצא תוצאות
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #9ca3af;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                            <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                        </div>
                    </td>
                </tr>
            `;
        }
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-table-manager]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && areaGravesTable) {
        areaGravesTable = null;
        window.areaGravesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!areaGravesTable || !tableWrapperExists) {
        initAreaGravesTable(filteredData, totalItems, signal);
    } else {
        if (areaGravesTable.config) {
            areaGravesTable.config.totalItems = totalItems;
        }
        
        areaGravesTable.setData(filteredData);
    }
    
    // ⭐ עדכן את התצוגה של UniversalSearch
    if (areaGraveSearch) {
        areaGraveSearch.state.totalResults = totalItems;
        areaGraveSearch.updateCounter();
    }
}


// ===================================================================
// פורמט תאריך
// ===================================================================

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// ===================================================================
// פונקציית עזר לשם סוג קבר
// ===================================================================
function getGraveTypeName(type) {
    const types = {
        1: 'שדה',
        2: 'רוויה',
        3: 'סנהדרין'
    };
    return types[type] || 'לא מוגדר';
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadAreaGraveStats(signal, plotId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=stats';
        if (plotId) {
            url += `&plotId=${plotId}`;
        }
        
        const response = await fetch(url, { signal: signal });
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Area grave stats:', result.data);
            
            if (document.getElementById('totalAreaGraves')) {
                document.getElementById('totalAreaGraves').textContent = result.data.total_area_graves || 0;
            }
            if (document.getElementById('totalGraves')) {
                document.getElementById('totalGraves').textContent = result.data.total_graves || 0;
            }
            if (document.getElementById('newThisMonth')) {
                document.getElementById('newThisMonth').textContent = result.data.new_this_month || 0;
            }
        }
    } catch (error) {
        // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
        if (error.name === 'AbortError') {
            console.log('⚠️ AreaGrave stats loading aborted - this is expected');
            return;
        }
        console.error('Error loading area grave stats:', error);
    }
}

// ===================================================================
// מחיקת אחוזת קבר
// ===================================================================
async function deleteAreaGrave(areaGraveId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את אחוזת הקבר?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=delete&id=${areaGraveId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת אחוזת הקבר');
        }
        
        showToast('אחוזת הקבר נמחקה בהצלחה', 'success');
        
        if (areaGraveSearch) {
            areaGraveSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting area grave:', error);
        showToast(error.message, 'error');
    }
}


// ===================================================================
// הצגת הודעות Toast
// ===================================================================

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
// רענון נתונים
// ===================================================================

async function refreshData() {
    // טעינה מחדש ישירה מה-API (כי UniversalSearch מושבת)
    await loadAreaGraves(currentPlotId, currentPlotName, false);
}


// ===================================================================
// בדיקת סטטוס טעינה
// ===================================================================

function checkScrollStatus() {
    if (!areaGravesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = areaGravesTable.getFilteredData().length;
    const displayed = areaGravesTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
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
// דאבל-קליק על אחוזת קבר
// ===================================================================

async function handleAreaGraveDoubleClick(areaGraveId, areaGraveName) {
    console.log('🖱️ Double-click on area grave:', areaGraveName, areaGraveId);
    
    try {
        if (typeof createAreaGraveCard === 'function') {
            const cardHtml = await createAreaGraveCard(areaGraveId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        }
        
        console.log('🪦 Loading graves for area grave:', areaGraveName);
        if (typeof loadGraves === 'function') {
            loadGraves(areaGraveId, areaGraveName);
        } else {
            console.warn('loadGraves function not found');
        }
        
    } catch (error) {
        console.error('❌ Error in handleAreaGraveDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי אחוזת הקבר', 'error');
    }
}


window.handleAreaGraveDoubleClick = handleAreaGraveDoubleClick;


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.loadAreaGraves = loadAreaGraves;

window.appendMoreAreaGraves = appendMoreAreaGraves;

window.deleteAreaGrave = deleteAreaGrave;

window.refreshData = refreshData;

window.areaGravesTable = areaGravesTable;

window.checkScrollStatus = checkScrollStatus;

window.currentPlotId = currentPlotId;

window.currentPlotName = currentPlotName;

window.areaGraveSearch = areaGraveSearch;

console.log('✅ area-graves-management.js v1.5.3 - Loaded successfully!');