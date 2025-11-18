/*
 * File: dashboards/dashboard/cemeteries/assets/js/graves-management.js
 * Version: 1.5.4
 * Updated: 2025-11-18
 * Author: Malkiel
 * Change Summary:
 * - v1.5.4: 🐛 תיקון שתי בעיות קריטיות + התאמה מלאה ל-area-graves:
 *   - תיקון: שדה חיפוש מוסתר - הסרת style="display: none;"
 *   - תיקון: שכפול טבלה - הוספת await ל-initGravesTable
 *   - הפיכת renderGravesRows ל-async function
 *   - זהה לחלוטין ל-area-graves-management.js v1.5.4
 */


console.log('🚀 graves-management.js v1.5.4 - Loading...');

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentGraves = [];
let graveSearch = null;
let gravesTable = null;
let editingGraveId = null;

let isSearchMode = false;      // האם אנחנו במצב חיפוש?
let currentQuery = '';         // מה החיפוש הנוכחי?
let searchResults = [];        // תוצאות החיפוש

// ⭐ שמירת ה-area grave context הנוכחי
let currentAreaGraveId = null;
let currentAreaGraveName = null;

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let gravesCurrentPage = 1;
let gravesTotalPages = 1;
let gravesIsLoadingMore = false;


// ===================================================================
// טעינת קברים (הפונקציה הראשית)
// ===================================================================
async function loadBrowseData(areaGraveId = null, signal = null) {
    gravesCurrentPage = 1;
    currentGraves = [];
    
    let apiUrl = '/dashboard/dashboards/cemeteries/api/graves-api.php?action=list&limit=200&page=1';
    apiUrl += '&orderBy=createDate&sortDirection=DESC';
    
    if (areaGraveId) {
        apiUrl += `&areaGraveId=${areaGraveId}`;
    }
    
    const response = await fetch(apiUrl, { signal });
    const result = await response.json();
    
    if (result.success && result.data) {
        currentGraves = result.data;
        
        if (result.pagination) {
            gravesTotalPages = result.pagination.pages;
            gravesCurrentPage = result.pagination.page;
        }
        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            renderGravesRows(result.data, tableBody, result.pagination, signal);
        }
    }
}
async function loadGraves(areaGraveId = null, areaGraveName = null, forceReset = false) {
    const signal = OperationManager.start('grave');

    // ⭐ איפוס מצב חיפוש
    isSearchMode = false;
    currentQuery = '';
    searchResults = [];

    // ⭐ לוגיקת סינון
    if (areaGraveId === null && areaGraveName === null && !forceReset) {
        if (window.currentAreaGraveId !== null || currentAreaGraveId !== null) {
            currentAreaGraveId = null;
            currentAreaGraveName = null;
            window.currentAreaGraveId = null;
            window.currentAreaGraveName = null;
        }
    } else if (forceReset) {
        currentAreaGraveId = null;
        currentAreaGraveName = null;
        window.currentAreaGraveId = null;
        window.currentAreaGraveName = null;
    } else {
        currentAreaGraveId = areaGraveId;
        currentAreaGraveName = areaGraveName;
        window.currentAreaGraveId = areaGraveId;
        window.currentAreaGraveName = areaGraveName;
    }
    
    window.currentAreaGraveId = currentAreaGraveId;
    window.currentAreaGraveName = currentAreaGraveName;
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'grave';
    }
    
    // ⭐ נקה
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'grave' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'grave' });
    }
    
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('gravesItem');
    }
    
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        const breadcrumbData = { 
            grave: { name: areaGraveName ? `קברים של ${areaGraveName}` : 'קברים' }
        };
        if (areaGraveId && areaGraveName) {
            breadcrumbData.areaGrave = { id: areaGraveId, name: areaGraveName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = areaGraveName ? `קברים - ${areaGraveName}` : 'ניהול קברים - מערכת בתי עלמין';
    
    // ⭐ בנה מבנה
    await buildGravesContainer(signal, areaGraveId, areaGraveName);
    
    if (OperationManager.shouldAbort('grave')) {
        return;
    }

    // ⭐ ספירת טעינות גלובלית
    if (!window.gravesLoadCounter) {
        window.gravesLoadCounter = 0;
    }
    window.gravesLoadCounter++;
    
    // השמד חיפוש קודם
    if (graveSearch && typeof graveSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous graveSearch instance...');
        graveSearch.destroy();
        graveSearch = null; 
        window.graveSearch = null;
    }
    
    // ⭐ אתחול UniversalSearch - פעם אחת!
    console.log('🆕 Creating fresh graveSearch instance...');
    graveSearch = await initGravesSearch(signal, areaGraveId);
    
    if (OperationManager.shouldAbort('grave')) {
        console.log('⚠️ Grave operation aborted');
        return;
    }

    // ⭐ טעינה ישירה (Browse Mode) - פעם אחת!
    await loadBrowseData(areaGraveId, signal);
    
    // טען סטטיסטיקות
    await loadGraveStats(signal, areaGraveId);
}

// ===================================================================
// 📥 טעינת עוד קברים (Infinite Scroll)
// ===================================================================
async function appendMoreGraves() {
    // בדיקות בסיסיות
    if (gravesIsLoadingMore) {
        return false;
    }
    
    if (gravesCurrentPage >= gravesTotalPages) {
        return false;
    }
    
    gravesIsLoadingMore = true;
    const nextPage = gravesCurrentPage + 1;
    
    // ⭐ עדכון מונה טעינות
    if (!window.gravesLoadCounter) {
        window.gravesLoadCounter = 0; 
    }
    window.gravesLoadCounter++;
    
    try {
        // בנה URL לעמוד הבא
        let apiUrl = `/dashboard/dashboards/cemeteries/api/graves-api.php?action=list&limit=200&page=${nextPage}`;
        apiUrl += '&orderBy=createDate&sortDirection=DESC';
        
        if (currentAreaGraveId) {
            apiUrl += `&areaGraveId=${currentAreaGraveId}`;
        }
        
        // שלח בקשה
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
            // ⭐ שמור את הגודל הקודם לפני ההוספה
            const previousTotal = currentGraves.length;
            
            // ⭐ הוסף לנתונים הקיימים
            currentGraves = [...currentGraves, ...result.data];
            gravesCurrentPage = nextPage;
            
            // ⭐⭐⭐ לוג פשוט ומסודר
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ טעינה: ${window.gravesLoadCounter}
╠════════════════════════════════════════════════════════════════════
║ כמות ערכים בטעינה: ${result.data.length}
║ מספר ערך תחילת טעינה נוכחית: ${result.debug?.results_info?.from_index || (previousTotal + 1)}
║ מספר ערך סוף טעינה נוכחית: ${result.debug?.results_info?.to_index || currentGraves.length}
║ סך כל הערכים שנטענו עד כה: ${currentGraves.length}
║ שדה למיון: ${result.debug?.sql_info?.order_field || 'createDate'}
║ סוג מיון: ${result.debug?.sql_info?.sort_direction || 'DESC'}
╠════════════════════════════════════════════════════════════════════
║ הערכים שנטענו כעת:
╚════════════════════════════════════════════════════════════════════
            `);
            console.table(result.data.map((item, idx) => ({
                '#': previousTotal + idx + 1,
                'unicId': item.unicId,
                'שם': item.graveName,
                'מספר': item.graveNumber || '-',
                'תאריך יצירה': item.createDate
            })));
            
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ הערכים שנטענו עד כה (סה"כ):
╚════════════════════════════════════════════════════════════════════
            `);
            console.table(currentGraves.map((item, idx) => ({
                '#': idx + 1,
                'unicId': item.unicId,
                'שם': item.graveName
            })));
            
            // ⭐ עדכן את הטבלה
            if (gravesTable) {
                gravesTable.setData(currentGraves);
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
        gravesIsLoadingMore = false;
    }
}


// ===================================================================
// בניית המבנה
// ===================================================================
async function buildGravesContainer(signal, areaGraveId = null, areaGraveName = null) {
    console.log('🏗️ Building graves container...');
    
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
    if (areaGraveId && areaGraveName) {
        console.log('🎴 Creating full area grave card...');
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createAreaGraveCard === 'function') {
            try {
                topSection = await createAreaGraveCard(areaGraveId, signal);
                console.log('✅ Area grave card created successfully');
            } catch (error) {
                // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
                if (error.name === 'AbortError') {
                    console.log('⚠️ Area grave card loading aborted');
                    return;
                }
                console.error('❌ Error creating area grave card:', error);
            }
        } else {
            console.warn('⚠️ createAreaGraveCard function not found');
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            console.log('⚠️ Using simple filter indicator as fallback');
            topSection = `
                <div class="filter-indicator" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🪦</span>
                        <div>
                            <div style="font-size: 12px; opacity: 0.9;">מציג קברים עבור</div>
                            <div style="font-size: 16px; font-weight: 600;">${areaGraveName}</div>
                        </div>
                    </div>
                    <button onclick="loadGraves(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        ✕ הצג הכל
                    </button>
                </div>
            `;
        }
    }

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Build graves container aborted before innerHTML');
        return;
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
        <div id="graveSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען קברים...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
  
    console.log('✅ Graves container built');
}

// ===================================================================
// אתחול UniversalSearch - עם Pagination!
// ===================================================================
async function initGravesSearch(signal, areaGraveId) {
    console.log('🔍 אתחול חיפוש קברים...');
    
    // ⭐ טוען searchableFields מהשרת
    let searchableFields = [];

    try {
        const fieldsResponse = await fetch(
            `/dashboard/dashboards/cemeteries/api/get-config.php?type=grave&section=searchableFields`,
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
        entityType: 'grave',  // ⭐ חובה!
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/graves-api.php',
        
        searchableFields: searchableFields || [],
        
        displayColumns: [
            { key: 'graveName', label: 'שם' },
            { key: 'graveNumber', label: 'מספר' },
            { key: 'status', label: 'מצב' },
            { key: 'graves_count', label: 'כמות קברים' }
        ],

        searchContainerSelector: '#graveSearchSection',
        resultsContainerSelector: '#tableBody',  
        
        // ⭐ Infinite Scroll אמיתי - טעינה מדורגת
        apiLimit: 200,
        showPagination: false,
        
        apiParams: {
            level: 'grave',
            areaGraveId: areaGraveId
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
            renderGravesRows(data, container, pagination, signal);
        },
        
        // callbacks: {
        //     // ⭐ כשנתונים נטענו
        //     onDataLoaded: (response) => {
        //         console.log('✅ נתונים נטענו:', response.data.length);
                
        //         // עדכון מונה כולל ב-TableManager
        //         if (window.gravesTable && response.pagination) {
        //             window.gravesTable.updateTotalItems(response.pagination.total);
        //         }
        //     },
            
        //     // ⭐ כשמנקים חיפוש
        //     onClear: () => {
        //         isSearchMode = false;
        //         currentQuery = '';
        //         searchResults = [];
                
        //         // חזרה ל-Browse
        //         loadBrowseData(currentAreaGraveId);
        //     }
        // }

        callbacks: {
            // ⭐ לפני חיפוש - נקה הכל והצג spinner
            onSearch: (query, filters) => {
                console.log('🔍 מתחיל חיפוש:', query);
                
                // ⭐ מחק את TableManager הישן
                const existingWrapper = document.querySelector('.table-wrapper[data-table-manager]');
                if (existingWrapper) {
                    console.log('🗑️ מוחק table-wrapper קיים');
                    existingWrapper.remove();
                }
                
                // ⭐ אפס את המשתנה
                if (gravesTable) {
                    gravesTable = null;
                    window.gravesTable = null;
                }
                
                // ⭐ הצג spinner בטבלה המקורית
                const originalTableBody = document.getElementById('tableBody');
                if (originalTableBody) {
                    // ⭐ הצג את הטבלה המקורית
                    const mainTable = document.getElementById('mainTable');
                    if (mainTable) {
                        mainTable.style.display = 'table';
                    }
                    
                    originalTableBody.innerHTML = `
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 60px;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
                                    <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; border-width: 0.3em;">
                                        <span class="visually-hidden">מחפש...</span>
                                    </div>
                                    <div style="font-size: 16px; color: #6b7280;">מחפש "${query}"...</div>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            },
            
            // ⭐ כשנתונים נטענו
            onDataLoaded: (response) => {
                console.log('✅ נתונים נטענו:', response.data.length);
                
                // עדכון מונה כולל ב-TableManager
                if (window.gravesTable && response.pagination) {
                    window.gravesTable.updateTotalItems(response.pagination.total);
                }
            },
            
            // ⭐ כשמנקים חיפוש
            onClear: () => {
                console.log('🧹 מנקה חיפוש...');
                
                isSearchMode = false;
                currentQuery = '';
                searchResults = [];
                
                // ⭐ מחק את TableManager
                const existingWrapper = document.querySelector('.table-wrapper[data-table-manager]');
                if (existingWrapper) {
                    existingWrapper.remove();
                }
                
                if (gravesTable) {
                    gravesTable = null;
                    window.gravesTable = null;
                }
                
                // חזרה ל-Browse
                loadBrowseData(currentAreaGraveId);
            }
        }
    };
    
    // יצירת instance
    const searchInstance = window.initUniversalSearch(config);
    
    // שמירה גלובלית
    window.graveSearch = searchInstance;
    
    return searchInstance;
}

// ===================================================================
// אתחול TableManager - עם Scroll Loading!
// ===================================================================
async function initGravesTable(data, totalItems = null, signal) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (gravesTable) {
        gravesTable.config.totalItems = actualTotalItems;
        gravesTable.setData(data);
        return gravesTable;
    }

    async function loadColumnsFromConfig(entityType = 'grave', signal) {
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
                        column.render = (grave) => {
                            return `<a href="#" onclick="handleGraveDoubleClick('${grave.unicId}', '${grave.graveName?.replace(/'/g, "\\'")}'); return false;" 
                                    style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                ${grave.graveName}
                            </a>`;
                        };
                        break;
                        
                    case 'graveNumber':
                        column.render = (grave) => {
                            const number = grave.graveNumber || '-';
                            return `<span style="font-family: monospace; font-size: 12px;">${number}</span>`;
                        };
                        break;
                        
                    case 'status':
                        column.render = (grave) => {
                            const statusInfo = getGraveStatusInfo(grave.status);
                            return `<span style="background: ${statusInfo.color}20; color: ${statusInfo.color}; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${statusInfo.label}</span>`;
                        };
                        break;
                        
                    case 'areaGrave':
                        column.render = (grave) => {
                            const areaGraveName = grave.areaGrave_name || grave.areaGraveName || '-';
                            return `<span style="color: #6b7280;">🏘️ ${areaGraveName}</span>`;
                        };
                        break;
                        
                    case 'badge':
                        column.render = (grave) => {
                            const count = grave[col.field] || 0;
                            return `<span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">${count}</span>`;
                        };
                        break;
                        
                    case 'date':
                        column.render = (grave) => formatDate(grave[col.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteGrave('${item.unicId}')" 
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
    const columns = await loadColumnsFromConfig('grave', signal);

    // בדוק אם בוטל
    if (signal && signal.aborted) {
        console.log('⚠️ Grave table initialization aborted');
        return null;
    }

    gravesTable = new TableManager({

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

        onLoadMore: async () => {
            if (isSearchMode) {
                // ⭐ חיפוש - טען דרך UniversalSearch
                if (graveSearch && typeof graveSearch.loadNextPage === 'function') {
                    if (graveSearch.state.currentPage >= graveSearch.state.totalPages) {
                        gravesTable.state.hasMoreData = false;
                        return;
                    }
                    await graveSearch.loadNextPage();
                }
            } else {
                // ⭐ Browse - טען ישירות
                const success = await appendMoreGraves();
                if (!success) {
                    gravesTable.state.hasMoreData = false;
                }
            }
        },

        renderFunction: (pageData) => {
            // ⭐ זה לא ישמש - UniversalSearch ירנדר ישירות
            return renderGravesRows(pageData);
        },
    

        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = gravesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    window.gravesTable = gravesTable;
    
    return gravesTable;
}


// ===================================================================
// רינדור שורות - עם סינון client-side! (⭐⭐ כמו ב-blocks!)
// ===================================================================

/**
 * רינדור שורות טבלה - פונקציה מלאה עם כל הלוגיקה!
 * v1.3.2 - שוחזרה הפונקציה המקורית המלאה
 */
function renderGravesRows(data, container, pagination = null, signal = null) {
    // ⭐⭐ סינון client-side לפי areaGraveId
    let filteredData = data;

    if (!isSearchMode && currentAreaGraveId) {
        filteredData = data.filter(grave => {
            const graveAreaGraveId = grave.areaGraveId || grave.area_grave_id || grave.AreaGraveId;
            return String(graveAreaGraveId) === String(currentAreaGraveId);
        });
    }
    
    // ⭐ עדכן את totalItems מה-pagination (סה"כ במערכת, לא רק מה שנטען!)
    const totalItems = pagination?.totalAll || pagination?.total || filteredData.length;
    
    console.log('🔍 [DEBUG renderGravesRows]');
    console.log('  pagination:', pagination);
    console.log('  totalItems calculated:', totalItems);
    console.log('  filteredData.length:', filteredData.length);

    if (filteredData.length === 0) {
        if (gravesTable) {
            gravesTable.setData([]);
        }
        
        // ⭐⭐⭐ הודעה מותאמת לאחוזת קבר ריקה!
        if (currentAreaGraveId && currentAreaGraveName) {
            // נכנסנו לאחוזת קבר ספציפית ואין קברים
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🪦</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין קברים באחוזת קבר ${currentAreaGraveName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                אחוזת הקבר עדיין לא מכילה קברים. תוכל להוסיף קבר חדש
                            </div>
                            <button 
                                onclick="if(typeof FormHandler !== 'undefined' && FormHandler.openForm) { FormHandler.openForm('grave', '${currentAreaGraveId}', null); } else { alert('FormHandler לא זמין'); }" 
                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
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
                                ➕ הוסף קבר ראשון
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
    if (!tableWrapperExists && gravesTable) {
        console.log('⚠️ TableManager DOM missing, resetting variable');
        gravesTable = null;
        window.gravesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!gravesTable || !tableWrapperExists) {
        console.log('🆕 Creating new TableManager');
        initGravesTable(filteredData, totalItems, signal);
    } else {
        console.log('♻️ Updating existing TableManager');
        if (gravesTable.config) {
            gravesTable.config.totalItems = totalItems;
        }
        
        gravesTable.setData(filteredData);
    }


    // // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    // if (!tableWrapperExists && gravesTable) {
    //     gravesTable = null;
    //     window.gravesTable = null;
    // }
    
    // // עכשיו בדוק אם צריך לבנות מחדש
    // if (!gravesTable || !tableWrapperExists) {
    //     initGravesTable(filteredData, totalItems, signal);
    // } else {
    //     if (gravesTable.config) {
    //         gravesTable.config.totalItems = totalItems;
    //     }
        
    //     gravesTable.setData(filteredData);
    // }
    
    // ⭐ עדכן את התצוגה של UniversalSearch
    if (graveSearch) {
        graveSearch.state.totalResults = totalItems;
        graveSearch.updateCounter();
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
// פונקציית עזר למצב קבר
// ===================================================================
function getGraveStatusInfo(status) {
    const statuses = {
        1: { label: 'פנוי', color: '#10b981' },
        2: { label: 'נרכש', color: '#f59e0b' },
        3: { label: 'קבור', color: '#6b7280' },
        4: { label: 'שמור', color: '#3b82f6' }
    };
    return statuses[status] || { label: 'לא מוגדר', color: '#9ca3af' };
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadGraveStats(signal, areaGraveId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/graves-api.php?action=stats';
        if (areaGraveId) {
            url += `&areaGraveId=${areaGraveId}`;
        }
        
        const response = await fetch(url, { signal: signal });
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Grave stats:', result.data);
            
            if (document.getElementById('totalGraves')) {
                document.getElementById('totalGraves').textContent = result.data.total_graves || 0;
            }
            if (document.getElementById('availableGraves')) {
                document.getElementById('availableGraves').textContent = result.data.available || 0;
            }
            if (document.getElementById('buriedGraves')) {
                document.getElementById('buriedGraves').textContent = result.data.buried || 0;
            }
        }
    } catch (error) {
        // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
        if (error.name === 'AbortError') {
            console.log('⚠️ Grave stats loading aborted - this is expected');
            return;
        }
        console.error('Error loading grave stats:', error);
    }
}

// ===================================================================
// מחיקת קבר
// ===================================================================
async function deleteGrave(graveId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את הקבר?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=delete&id=${graveId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת הקבר');
        }
        
        showToast('הקבר נמחק בהצלחה', 'success');
        
        if (graveSearch) {
            graveSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting grave:', error);
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
    await loadGraves(currentAreaGraveId, currentAreaGraveName, false);
}

// ===================================================================
// בדיקת סטטוס טעינה
// ===================================================================

function checkScrollStatus() {
    if (!gravesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = gravesTable.getFilteredData().length;
    const displayed = gravesTable.getDisplayedData().length;
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
// דאבל-קליק על קבר
// ===================================================================

async function handleGraveDoubleClick(graveId, graveName) {
    console.log('🖱️ Double-click on grave:', graveName, graveId);
    
    try {
        // פתיחת עריכה - קבר הוא הרמה האחרונה
        console.log('✏️ Opening edit form for grave:', graveName);
        if (typeof window.tableRenderer !== 'undefined' && window.tableRenderer.editItem) {
            window.tableRenderer.editItem(graveId);
        } else {
            console.warn('tableRenderer.editItem not available');
            showToast('לא ניתן לפתוח טופס עריכה', 'error');
        }
        
    } catch (error) {
        console.error('❌ Error in handleGraveDoubleClick:', error);
        showToast('שגיאה בפתיחת טופס עריכה', 'error');
    }
}






window.handleGraveDoubleClick = handleGraveDoubleClick;

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.loadGraves = loadGraves;
window.appendMoreGraves = appendMoreGraves;
window.deleteGrave = deleteGrave;
window.refreshData = refreshData;
window.gravesTable = gravesTable;
window.checkScrollStatus = checkScrollStatus;
window.currentAreaGraveId = currentAreaGraveId;
window.currentAreaGraveName = currentAreaGraveName;
window.graveSearch = graveSearch;
console.log('✅ graves-management.js v1.5.4 - Loaded successfully!');