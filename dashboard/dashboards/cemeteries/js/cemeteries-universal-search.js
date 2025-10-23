/**
 * cemeteries-universal-search.js
 * טעינת בתי עלמין בשיטה החדשה - כמו לקוחות
 * משתמש ב-UniversalSearch + TableManager
 */

// משתנים גלובליים לבתי עלמין
let cemeteriesSearch = null;
let cemeteriesTable = null;
let currentCemeteries = [];

/**
 * פונקציה ראשית - טעינת בתי עלמין
 * מחליפה את loadAllCemeteries הישנה
 */
window.loadAllCemeteries = async function() {
    console.log('📋 Loading cemeteries with UniversalSearch...');

    // הגדר את הפריט הפעיל בסיידבר
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('cemeteryItem');
    }
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {};
    
    // נקה את הדשבורד
    if (typeof DashboardCleaner !== 'undefined' && DashboardCleaner.clear) {
        DashboardCleaner.clear({ targetLevel: 'cemetery' });
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof BreadcrumbManager !== 'undefined') {
        BreadcrumbManager.update({ cemetery: { name: 'בתי עלמין' } }, 'cemetery');
    } else if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ cemetery: { name: 'בתי עלמין' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול בתי עלמין - מערכת בתי עלמין';
    
    // בנה את המבנה החדש
    await buildCemeteriesContainer();
    
    // אתחל את UniversalSearch
    if (!cemeteriesSearch) {
        await initCemeteriesUniversalSearch();
        cemeteriesSearch.search(); // חיפוש ראשוני
    } else {
        cemeteriesSearch.refresh();
    }
    
    // טען סטטיסטיקות
    await loadCemeteriesStats();
};

/**
 * בניית המבנה של בתי עלמין
 */
async function buildCemeteriesContainer() {
    console.log('🏗️ Building cemeteries container...');
    
    // מצא את main-container
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
    
    // בנה את התוכן
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="cemeteriesSearchSection" class="search-section"></div>
        
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

/**
 * אתחול UniversalSearch לבתי עלמין
 */
async function initCemeteriesUniversalSearch() {
    console.log('🔍 Initializing UniversalSearch for cemeteries...');
    
    cemeteriesSearch = new UniversalSearch({
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php',
            action: 'list',
            method: 'GET',
            // חשוב! צריך להעביר את type כפרמטר
            params: {
                type: 'cemetery'  // ה-API דורש את זה!
            },
            tables: ['cemeteries'],
            joins: []
        },
        
        // שדות לחיפוש
        searchableFields: [
            {
                name: 'cemeteryNameHe',
                label: 'שם בית עלמין (עברית)',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'cemeteryNameEn',
                label: 'שם בית עלמין (אנגלית)',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'cemeteryCode',
                label: 'קוד בית עלמין',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'address',
                label: 'כתובת',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'contactName',
                label: 'איש קשר',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'contactPhoneName',
                label: 'טלפון איש קשר',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'cemeteries',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        display: {
            containerSelector: '#cemeteriesSearchSection',
            showAdvanced: true,
            showFilters: true,
            placeholder: 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
            layout: 'horizontal',
            minSearchLength: 0  // מאפשר חיפוש ריק לטעינת כל הנתונים
        },
        
        results: {
            containerSelector: '#tableBody',
            itemsPerPage: 10000,  // טען הכל
            showPagination: false,
            showCounter: true,
            columns: ['cemeteryNameHe', 'cemeteryCode', 'address', 'contactName', 'createDate'],
            renderFunction: renderCemeteriesRows  // פונקציה מותאמת אישית
        },
        
        behavior: {
            realTime: true,
            autoSubmit: true,
            highlightResults: true
        },
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for cemeteries');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || data.data?.length || 0, 'cemeteries found');
                currentCemeteries = data.data || [];
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                if (typeof showToast === 'function') {
                    showToast('שגיאה בחיפוש: ' + error.message, 'error');
                }
            },
            
            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    });
    
    // שמור במשתנה גלובלי
    window.cemeteriesSearch = cemeteriesSearch;
    
    return cemeteriesSearch;
}

/**
 * רינדור שורות בתי עלמין
 */
function renderCemeteriesRows(data, container) {
    console.log('🎨 renderCemeteriesRows called with', data.length, 'items');
    
    if (data.length === 0) {
        if (cemeteriesTable) {
            cemeteriesTable.setData([]);
        }
        
        container.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 60px;">
                    <div style="color: #9ca3af;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו בתי עלמין</div>
                        <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                        <button class="btn btn-primary mt-3" onclick="addNewCemetery()">
                            <i class="fas fa-plus"></i> הוסף בית עלמין
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה
    if (!tableWrapperExists && cemeteriesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting cemeteriesTable variable');
        cemeteriesTable = null;
        window.cemeteriesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!cemeteriesTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש
        console.log('✅ Creating new TableManager with', data.length, 'total items');
        initCemeteriesTable(data);
    } else {
        // TableManager קיים וגם ה-DOM שלו - רק עדכן נתונים
        console.log('🔄 Updating existing TableManager with', data.length, 'total items');
        cemeteriesTable.setData(data);
    }
}

/**
 * אתחול TableManager לבתי עלמין
 */
function initCemeteriesTable(data) {
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (cemeteriesTable) {
        cemeteriesTable.setData(data);
        return cemeteriesTable;
    }
    
    cemeteriesTable = new TableManager({
        tableSelector: '#mainTable',
        
        containerWidth: '100%',
        fixedLayout: true,
        
        scrolling: {
            enabled: true,
            headerHeight: '50px',
            itemsPerPage: 50,
            scrollThreshold: 300
        },
        
        columns: [
            {
                field: 'index',
                label: '#',
                width: '60px',
                type: 'index',
                sortable: false,
                render: (cemetery, index) => index + 1
            },
            {
                field: 'cemeteryNameHe',
                label: 'שם בית עלמין',
                width: '250px',
                type: 'text',
                sortable: true,
                render: (cemetery) => {
                    const name = cemetery.cemeteryNameHe || cemetery.name || '';
                    const nameEn = cemetery.cemeteryNameEn ? 
                        `<br><small style="color: #6b7280;">${cemetery.cemeteryNameEn}</small>` : '';
                    return `<strong>${name}</strong>${nameEn}`;
                }
            },
            {
                field: 'cemeteryCode',
                label: 'קוד',
                width: '100px',
                type: 'text',
                sortable: true
            },
            {
                field: 'address',
                label: 'כתובת',
                width: '200px',
                type: 'text',
                sortable: true,
                render: (cemetery) => {
                    const address = cemetery.address || '';
                    const coords = cemetery.coordinates ? 
                        `<br><small style="color: #6b7280;">📍 ${cemetery.coordinates}</small>` : '';
                    return address + coords;
                }
            },
            {
                field: 'contactName',
                label: 'איש קשר',
                width: '150px',
                type: 'text',
                sortable: true,
                render: (cemetery) => {
                    const contact = cemetery.contactName || '';
                    const phone = cemetery.contactPhoneName ? 
                        `<br><small style="color: #6b7280;">📞 ${cemetery.contactPhoneName}</small>` : '';
                    return contact + phone;
                }
            },
            {
                field: 'createDate',
                label: 'תאריך יצירה',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (cemetery) => {
                    if (!cemetery.createDate) return '-';
                    const date = new Date(cemetery.createDate);
                    return date.toLocaleDateString('he-IL');
                }
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '180px',
                sortable: false,
                render: (cemetery) => {
                    const cemeteryId = cemetery.unicId || cemetery.id;
                    const cemeteryName = (cemetery.cemeteryNameHe || cemetery.name || 'ללא שם').replace(/'/g, "\\'");
                    
                    return `
                        <button class="btn btn-sm btn-primary" 
                                onclick="openCemetery('${cemeteryId}', '${cemeteryName}')" 
                                title="כניסה">
                            <svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg>
                            כניסה
                        </button>
                        <button class="btn btn-sm btn-secondary" 
                                onclick="editCemetery('${cemeteryId}')" 
                                title="עריכה">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                        </button>
                        <button class="btn btn-sm btn-danger" 
                                onclick="deleteCemetery('${cemeteryId}')" 
                                title="מחיקה">
                            <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                        </button>
                    `;
                }
            }
        ],
        
        data: data,
        
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            if (typeof showToast === 'function') {
                showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
            }
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = cemeteriesTable.getFilteredData().length;
            if (typeof showToast === 'function') {
                showToast(`נמצאו ${count} תוצאות`, 'info');
            }
        }
    });
    
    // שמור במשתנה גלובלי
    window.cemeteriesTable = cemeteriesTable;
    
    console.log('📊 Total cemeteries loaded:', data.length);
    console.log('📄 Items per page:', cemeteriesTable.config.itemsPerPage);
    console.log('📏 Scroll threshold:', cemeteriesTable.config.scrollThreshold + 'px');
    
    return cemeteriesTable;
}

/**
 * טעינת סטטיסטיקות בתי עלמין
 */
async function loadCemeteriesStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=stats&type=cemetery');
        const data = await response.json();
        
        if (data.success && data.stats) {
            console.log('📈 Cemetery stats:', data.stats);
            // כאן אפשר להציג את הסטטיסטיקות ב-UI
        }
    } catch (error) {
        console.error('Error loading cemetery stats:', error);
    }
}

/**
 * פונקציות עזר - פעולות על בתי עלמין
 */

// הוספת בית עלמין חדש
window.addNewCemetery = function() {
    console.log('➕ Add new cemetery');
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, null);
    } else if (window.tableRenderer && typeof window.tableRenderer.addItem === 'function') {
        window.tableRenderer.addItem();
    } else {
        alert('מערכת הטפסים לא זמינה');
    }
};

// עריכת בית עלמין
window.editCemetery = function(cemeteryId) {
    console.log('✏️ Edit cemetery:', cemeteryId);
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, cemeteryId);
    } else if (window.tableRenderer && typeof window.tableRenderer.editItem === 'function') {
        window.tableRenderer.editItem(cemeteryId);
    } else {
        alert('מערכת הטפסים לא זמינה');
    }
};

// מחיקת בית עלמין
window.deleteCemetery = async function(cemeteryId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את בית העלמין?')) {
        return;
    }
    
    try {
        const response = await fetch(
            `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=delete&type=cemetery&id=${cemeteryId}`,
            { method: 'DELETE' }
        );
        
        const result = await response.json();
        
        if (result.success) {
            if (typeof showToast === 'function') {
                showToast('בית העלמין נמחק בהצלחה', 'success');
            }
            // רענן את הנתונים
            if (cemeteriesSearch) {
                cemeteriesSearch.refresh();
            }
        } else {
            throw new Error(result.error || 'שגיאה במחיקה');
        }
    } catch (error) {
        console.error('Error deleting cemetery:', error);
        if (typeof showToast === 'function') {
            showToast('שגיאה במחיקת בית העלמין: ' + error.message, 'error');
        } else {
            alert('שגיאה במחיקת בית העלמין: ' + error.message);
        }
    }
};

// פתיחת בית עלמין - מעבר לגושים
window.openCemetery = function(cemeteryId, cemeteryName) {
    console.log('🏛️ Opening cemetery:', cemeteryId, cemeteryName);
    
    // עדכן את הבחירה
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    window.currentCemeteryId = cemeteryId;
    
    // עדכן Breadcrumb
    if (typeof BreadcrumbManager !== 'undefined') {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    // טען גושים - חזרה לשיטה הרגילה
    if (window.tableRenderer && typeof window.tableRenderer.loadAndDisplay === 'function') {
        window.tableRenderer.loadAndDisplay('block', cemeteryId);
    } else if (typeof loadBlocksForCemetery === 'function') {
        loadBlocksForCemetery(cemeteryId);
    }
};

// בדיקת גלילה
window.checkCemeteriesScrollStatus = function() {
    if (!cemeteriesTable) {
        console.log('❌ No cemeteries table instance');
        return;
    }
    
    const status = cemeteriesTable.getStatus();
    console.log('📊 Cemeteries Table Status:', status);
    
    if (status.displayedItems < status.totalItems) {
        console.log(`🚨 Only showing ${status.displayedItems} out of ${status.totalItems} cemeteries!`);
        console.log('💡 Scroll down to load more items');
    } else {
        console.log('✅ All cemeteries are displayed');
    }
    
    return status;
};

console.log('✅ Cemeteries Universal Search Module Loaded');

// הוסף לחלון
window.loadCemeteries = window.loadAllCemeteries; // alias