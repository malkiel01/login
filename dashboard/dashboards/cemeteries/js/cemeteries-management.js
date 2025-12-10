/*
 * File: dashboards/dashboard/cemeteries/assets/js/cemeteries-management.js
 * Version: 5.2.0
 * Updated: 2025-11-03
 * Author: Malkiel
 * Change Summary:
 * - v5.2.0: הוספת תמיכה מלאה בטעינה מדורגת
 *   - pagination מצטברת עם scroll loading אינסופי
 *   - עדכון אוטומטי של state.totalResults
 *   - תיקון כפתורי Delete לקרוא ל-deleteCemetery()
 *   - תמיכה בכמות רשומות בלתי מוגבלת
 *   - רמת שורש (ללא סינון client-side)
 * - v5.1.0: תיקון קונפליקט שמות - initCemeteriesSearch
 * - v5.0.0: שיטה זהה ללקוחות - UniversalSearch + TableManager
 * - v1.0.0: גרסה ראשונית - ניהול בתי עלמין
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCemeteries = [];
let cemeterySearch = null;
let cemeteriesTable = null;
let editingCemeteryId = null;

// ===================================================================
// טעינת בתי עלמין (הפונקציה הראשית)
// ===================================================================
async function loadCemeteries() {
    console.log('📋 Loading cemeteries - v5.1.0 (תוקן קונפליקט שמות)...');

    // ⭐ התחל פעולה חדשה - זה יבטל אוטומטית כל פעולה קודמת!
    const signal = OperationManager.start('cemetery');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'cemetery';
    window.currentParentId = null;

    // ⭐ חדש: אפס את הסינון של גושים!
    window.currentCemeteryId = null;
    window.currentCemeteryName = null;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'cemetery';
    }
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'cemetery' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'cemetery' });
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('cemeteriesItem');
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ cemetery: { name: 'בתי עלמין' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול בתי עלמין - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    buildCemeteriesContainer();

    // ⭐ בדיקה - אם השתנה currentType, עצור!
    if (OperationManager.shouldAbort('cemetery')) {
        console.log('⚠️ Cemetery operation aborted');
        return;
    }
    
    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (cemeterySearch && typeof cemeterySearch.destroy === 'function') {
        console.log('🗑️ Destroying previous cemeterySearch instance...');
        cemeterySearch.destroy();
        cemeterySearch = null;
        window.cemeterySearch = null;
    }

    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh cemeterySearch instance...');
    await initCemeteriesSearch(signal);
    
    // ⭐ בדיקה נוספת
    if (OperationManager.shouldAbort('cemetery')) {
        console.log('⚠️ Cemetery operation aborted');
        return;
    }
    
    cemeterySearch.search();
    
    // טען סטטיסטיקות
    await loadCemeteryStats(signal);
}

// ===================================================================
// ⭐ פונקציה חדשה - בניית המבנה של בתי עלמין ב-main-container
// ===================================================================
function buildCemeteriesContainer() {
    console.log('🏗️ Building cemeteries container...');
    
    // מצא את main-container (צריך להיות קיים אחרי clear)
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
    
    // ⭐ בנה את התוכן של בתי עלמין - זהה ללקוחות!
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="cemeterySearchSection" class="search-section"></div>
        
        <!-- table-container עבור TableManager -->
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
                                <span class="visually-hidden">טוען בתי עלמין...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Cemeteries container built');
}

// ===================================================================
// אתחול UniversalSearch - שימוש בפונקציה גלובלית!
// ===================================================================
async function initCemeteriesSearch(signal) {
    // ═══════════════════════════════════════════════════════════════
    // שלב 1: טעינת כל ההגדרות מהקונפיג
    // ═══════════════════════════════════════════════════════════════
    let displayColumns = ['cemeteryNameHe', 'cemeteryCode', 'createDate']; // ברירת מחדל
    
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/get-config.php?type=cemetery&section=table_columns');
        const data = await response.json();
        if (data.success && data.data) {
            displayColumns = data.data.map(col => col.field).filter(f => f !== 'actions' && f !== 'index');
        }
    } catch (error) {
        console.warn('⚠️ Could not load config, using defaults:', error);
    }

    cemeterySearch = await window.initUniversalSearch({
        entityType: 'cemetery',
        signal: signal,
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/cemeteries-api.php',
        action: 'list',
        
        // searchableFields: [
        //     {
        //         name: 'cemeteryNameHe',
        //         label: 'שם בית עלמין (עברית)',
        //         table: 'cemeteries',
        //         type: 'text',
        //         matchType: ['exact', 'fuzzy', 'startsWith']
        //     },
        //     {
        //         name: 'cemeteryNameEn',
        //         label: 'שם בית עלמין (אנגלית)',
        //         table: 'cemeteries',
        //         type: 'text',
        //         matchType: ['exact', 'fuzzy', 'startsWith']
        //     },
        //     {
        //         name: 'cemeteryCode',
        //         label: 'קוד בית עלמין',
        //         table: 'cemeteries',
        //         type: 'text',
        //         matchType: ['exact', 'startsWith']
        //     },
        //     {
        //         name: 'address',
        //         label: 'כתובת',
        //         table: 'cemeteries',
        //         type: 'text',
        //         matchType: ['exact', 'fuzzy']
        //     },
        //     {
        //         name: 'contactName',
        //         label: 'איש קשר',
        //         table: 'cemeteries',
        //         type: 'text',
        //         matchType: ['exact', 'fuzzy']
        //     },
        //     {
        //         name: 'contactPhoneName',
        //         label: 'טלפון',
        //         table: 'cemeteries',
        //         type: 'text',
        //         matchType: ['exact', 'fuzzy']
        //     },
        //     {
        //         name: 'createDate',
        //         label: 'תאריך יצירה',
        //         table: 'cemeteries',
        //         type: 'date',
        //         matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
        //     }
        // ],

        
        searchableFields: [], 
        displayColumns: displayColumns,

        searchContainerSelector: '#cemeterySearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
        itemsPerPage: 999999,
        
        renderFunction: renderCemeteriesRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for cemeteries');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },

            onResults: (data) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'cemeteries');
          
                // ⭐ בדיקה אוטומטית!
                if (OperationManager.shouldAbort('cemetery')) {
                    console.log('⚠️ Cemetery results aborted - type changed');
                    return;
                }
                
                // ⭐ טיפול בדפים - מצטבר!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentCemeteries = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentCemeteries = [...currentCemeteries, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentCemeteries.length}`);
                }
                
                // ⭐ אין סינון client-side - זו רמת השורש!
                let filteredCount = currentCemeteries.length;
                
                // ⭐⭐⭐ עדכן ישירות את cemeterySearch!
                if (cemeterySearch && cemeterySearch.state) {
                    cemeterySearch.state.totalResults = filteredCount;
                    if (cemeterySearch.updateCounter) {
                        cemeterySearch.updateCounter();
                    }
                }
                
                console.log('📊 Final count:', filteredCount);
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש: ' + error.message, 'error');
            },
            
            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    });
    
    // ⭐ עדכן את window.cemeterySearch מיד!
    window.cemeterySearch = cemeterySearch;
    
    return cemeterySearch;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
async function initCemeteriesTable(data, totalItems = null, signal) {
    // ⭐ אם לא קיבלנו totalItems, השתמש ב-data.length
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (cemeteriesTable) {
        cemeteriesTable.config.totalItems = actualTotalItems;  // ⭐ עדכן totalItems!
        cemeteriesTable.setData(data);
        return cemeteriesTable;
    }

    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'cemetery', signal) {
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

            // המרה לפורמט של TableManager
            const columns = result.data.map(col => {
                const column = {
                    field: col.field,
                    label: col.title,
                    width: col.width || 'auto',
                    sortable: col.sortable !== false,
                    type: col.type || 'text'
                };
                
                // טיפול בסוגים מיוחדים
                switch(col.type) {
                    case 'link':
                        column.render = (item) => {
                            return `<a href="#" onclick="handleCemeteryDoubleClick('${item.unicId}', '${item.cemeteryNameHe?.replace(/'/g, "\\'")}'); return false;" 
                                    style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                ${item[col.field]}
                            </a>`;
                        };
                        break;
                        
                    case 'badge':
                        column.render = (item) => {
                            const count = item[col.field] || 0;
                            return `<span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">${count}</span>`;
                        };
                        break;
                        
                    case 'date':
                        column.render = (item) => formatDate(item[col.field]);
                        break;

                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteCemetery('${item.unicId}')" 
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
    const columns = await loadColumnsFromConfig('cemetery', signal);

    // בדוק אם בוטל
    if (signal && signal.aborted) {
        console.log('⚠️ Cemetery table initialization aborted');
        return null;
    }

    cemeteriesTable = new TableManager({
        tableSelector: '#mainTable',  // ⭐ זה הכי חשוב!
        totalItems: actualTotalItems,
        columns: columns,

        tableHeight: 'calc(100vh - 650px)',  // גובה דינמי לפי מסך
        tableMinHeight: '100px',
        
        data: data,
        
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = cemeteriesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    // ⭐ מאזין לגלילה - טען עוד דפים!
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && cemeterySearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!cemeterySearch.state.isLoading && cemeterySearch.state.currentPage < cemeterySearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    const nextPage = cemeterySearch.state.currentPage + 1;
                    cemeterySearch.state.currentPage = nextPage;
                    cemeterySearch.state.isLoading = true;
                    await cemeterySearch.search();
                }
            }
        });
    }

    window.cemeteriesTable = cemeteriesTable;
    return cemeteriesTable;
}

// ===================================================================
// רינדור שורות בתי עלמין - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderCemeteriesRows(data, container, pagination = null, signal = null) {
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
    const totalItems = pagination?.total || data.length;

    if (data.length === 0) {
        if (cemeteriesTable) {
            cemeteriesTable.setData([]);
        }
        
        container.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 60px;">
                    <div style="color: #9ca3af;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                        <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-table-manager]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && cemeteriesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting cemeteriesTable variable');
        cemeteriesTable = null;
        window.cemeteriesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!cemeteriesTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        initCemeteriesTable(data, totalItems, signal);  // ⭐ העברת totalItems!
    } else {
          // ⭐ עדכן גם את totalItems ב-TableManager!
        if (cemeteriesTable.config) {
            cemeteriesTable.config.totalItems = totalItems;
        }
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (cemeterySearch && cemeterySearch.state) {
            const allData = cemeterySearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                cemeteriesTable.setData(allData);
                return;
            }
        }

        cemeteriesTable.setData(data);
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
// פונקציות CRUD
// ===================================================================
async function deleteCemetery(unicId) {
    console.log('🗑️ Delete cemetery:', unicId);
    
    if (!confirm('האם אתה בטוח שברצונך למחוק את בית העלמין?')) {
        return;
    }

    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=delete&id=${unicId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('בית העלמין נמחק בהצלחה', 'success');
            
            if (cemeterySearch) {
                cemeterySearch.refresh();
            }
        } else {
            showToast(data.error || 'שגיאה במחיקת בית עלמין', 'error');
        }
    } catch (error) {
        console.error('Error deleting cemetery:', error);
        showToast('שגיאה במחיקת בית עלמין', 'error');
    }
}

// ===================================================================
// בחירת הכל
// ===================================================================
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.cemetery-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadCemeteryStats(signal) {
    try {
        // const response = await fetch('/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=stats');
        
        const response = await fetch(
            '/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=stats',
            { signal: signal }  // ⭐ העבר את ה-signal
        );
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Cemetery stats:', data.data);
        }
    } catch (error) {
        // ⭐ אם זה ביטול - זה לא שגיאה!
        if (error.name === 'AbortError') {
            console.log('⚠️ Stats loading aborted - this is expected');
            return;
        }
        
        // רק שגיאות אמיתיות מודפסות
        console.error('Error loading cemetery stats:', error);
    }

}

// ===================================================================
// הצגת הודעת Toast
// ===================================================================
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
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
// פונקציה לרענון נתונים
// ===================================================================
async function refreshData() {
    if (cemeterySearch) {
        cemeterySearch.refresh();
    }
}

// ===================================================================
// פונקציה לבדיקת סטטוס הטעינה
// ===================================================================
function checkScrollStatus() {
    if (!cemeteriesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = cemeteriesTable.getFilteredData().length;
    const displayed = cemeteriesTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(cemeteriesTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// פונקציה לטיפול בדאבל-קליק על בית עלמין
// ===================================================
async function handleCemeteryDoubleClick(unicId, cemeteryName) {
    console.log('🖱️ Double-click on cemetery:', cemeteryName, unicId);
    
    try {
        // טעינת גושים
        console.log('📦 Loading blocks for cemetery:', cemeteryName);
        if (typeof loadBlocks === 'function') {
            loadBlocks(unicId, cemeteryName);
        } else {
            console.warn('loadBlocks function not found');
        }
    } catch (error) {
        console.error('❌ Error in handleCemeteryDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי בית העלמין', 'error');
    }
}

window.handleCemeteryDoubleClick = handleCemeteryDoubleClick;

// ===================================================================
// הפוך את הפונקציות לגלובליות
// ===================================================================
window.loadCemeteries = loadCemeteries;
window.deleteCemetery = deleteCemetery;
window.refreshData = refreshData;
window.cemeteriesTable = cemeteriesTable;
window.checkScrollStatus = checkScrollStatus;
window.cemeterySearch = cemeterySearch;