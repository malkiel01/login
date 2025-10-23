/**
 * cemeteries-universal-search-fixed.js
 * תיקון סופי - טעינת בתי עלמין בשיטה החדשה
 * עובד עם UniversalSearch + TableManager
 */

// משתנים גלובליים לבתי עלמין
let cemeteriesSearch = null;
let cemeteriesTable = null;
let currentCemeteries = [];

/**
 * פונקציה ראשית - טעינת בתי עלמין
 */
window.loadAllCemeteries = async function() {
    console.log('📋 Loading cemeteries with UniversalSearch - FIXED VERSION');

    // הגדר את הפריט הפעיל בסיידבר
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('cemeteryItem');
    }
    
    // עדכן משתנים גלובליים
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {};
    
    // נקה את הדשבורד
    if (typeof DashboardCleaner !== 'undefined' && DashboardCleaner.clear) {
        DashboardCleaner.clear({ targetLevel: 'cemetery' });
    }
    
    // נקה את הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכן כפתור הוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof BreadcrumbManager !== 'undefined') {
        BreadcrumbManager.update({ cemetery: { name: 'בתי עלמין' } }, 'cemetery');
    } else if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ cemetery: { name: 'בתי עלמין' } });
    }
    
    // עדכון כותרת
    document.title = 'ניהול בתי עלמין - מערכת בתי עלמין';
    
    // בנה את המבנה
    await buildCemeteriesContainer();
    
    // שיטה אחרת - טען נתונים ישירות בלי UniversalSearch אם יש בעיה
    const USE_UNIVERSAL_SEARCH = true; // אפשר לשנות ל-false אם UniversalSearch לא עובד
    
    if (USE_UNIVERSAL_SEARCH) {
        // נסה עם UniversalSearch
        try {
            if (!cemeteriesSearch) {
                await initCemeteriesUniversalSearch();
                cemeteriesSearch.search();
            } else {
                cemeteriesSearch.refresh();
            }
        } catch (error) {
            console.error('❌ UniversalSearch failed, falling back to direct load:', error);
            await loadCemeteriesDirectly();
        }
    } else {
        // טען ישירות
        await loadCemeteriesDirectly();
    }
    
    // טען סטטיסטיקות
    await loadCemeteriesStats();
};

/**
 * בניית המבנה
 */
async function buildCemeteriesContainer() {
    console.log('🏗️ Building cemeteries container...');
    
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
    
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="cemeteriesSearchSection" class="search-section"></div>
        
        <!-- table-container -->
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
 * אתחול UniversalSearch - עם תיקון!
 */
async function initCemeteriesUniversalSearch() {
    console.log('🔍 Initializing UniversalSearch for cemeteries...');
    
    // בניית ה-endpoint עם type
    const baseEndpoint = '/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php';
    const typeParam = 'type=cemetery';
    
    // שים לב: אנחנו מוסיפים את type ל-endpoint ישירות
    const fullEndpoint = `${baseEndpoint}?${typeParam}`;
    
    console.log('📡 Using endpoint:', fullEndpoint);
    
    cemeteriesSearch = new UniversalSearch({
        dataSource: {
            type: 'api',
            endpoint: fullEndpoint, // כבר כולל ?type=cemetery
            action: 'list',         // יתווסף כ-&action=list
            method: 'GET',
            tables: ['cemeteries'],
            joins: []
        },
        
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
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'cemeteries',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between']
            }
        ],
        
        display: {
            containerSelector: '#cemeteriesSearchSection',
            showAdvanced: true,
            showFilters: true,
            placeholder: 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
            layout: 'horizontal',
            minSearchLength: 0
        },
        
        results: {
            containerSelector: '#tableBody',
            itemsPerPage: 10000,
            showPagination: false,
            showCounter: true,
            columns: ['cemeteryNameHe', 'cemeteryCode', 'address', 'contactName', 'createDate'],
            renderFunction: renderCemeteriesRows
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
                // נסה טעינה ישירה
                loadCemeteriesDirectly();
            },
            
            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    });
    
    window.cemeteriesSearch = cemeteriesSearch;
    return cemeteriesSearch;
}

/**
 * טעינה ישירה ללא UniversalSearch - כגיבוי
 */
async function loadCemeteriesDirectly() {
    console.log('📡 Loading cemeteries directly (fallback)...');
    
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=cemetery&limit=1000');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('📦 Direct load result:', result);
        
        if (result.success && result.data) {
            renderCemeteriesRows(result.data, document.getElementById('tableBody'));
        } else {
            throw new Error(result.error || 'Failed to load cemeteries');
        }
    } catch (error) {
        console.error('❌ Direct load error:', error);
        const tbody = document.getElementById('tableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-danger">
                        <div style="padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px;"></i>
                            <div style="margin-top: 16px;">שגיאה בטעינת בתי עלמין</div>
                            <div style="margin-top: 8px;">${error.message}</div>
                            <button class="btn btn-primary mt-3" onclick="window.loadAllCemeteries()">
                                <i class="fas fa-sync"></i> נסה שוב
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
}

/**
 * רינדור שורות
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
                        <button class="btn btn-primary mt-3" onclick="addNewCemetery()">
                            <i class="fas fa-plus"></i> הוסף בית עלמין
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    if (!tableWrapperExists && cemeteriesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting');
        cemeteriesTable = null;
        window.cemeteriesTable = null;
    }
    
    if (!cemeteriesTable || !tableWrapperExists) {
        console.log('✅ Creating new TableManager');
        initCemeteriesTable(data);
    } else {
        console.log('🔄 Updating existing TableManager');
        cemeteriesTable.setData(data);
    }
}

/**
 * אתחול TableManager
 */
function initCemeteriesTable(data) {
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
                    return new Date(cemetery.createDate).toLocaleDateString('he-IL');
                }
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '180px',
                sortable: false,
                render: (cemetery) => {
                    const cemeteryId = cemetery.unicId || cemetery.id;
                    const cemeteryName = (cemetery.cemeteryNameHe || cemetery.name || '').replace(/'/g, "\\'");
                    
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
                showToast(`ממוין לפי ${field}`, 'info');
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
    
    window.cemeteriesTable = cemeteriesTable;
    console.log('📊 TableManager initialized with', data.length, 'items');
    
    return cemeteriesTable;
}

/**
 * טעינת סטטיסטיקות
 */
async function loadCemeteriesStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=stats&type=cemetery');
        const data = await response.json();
        
        if (data.success && data.stats) {
            console.log('📈 Cemetery stats:', data.stats);
        }
    } catch (error) {
        console.error('Error loading cemetery stats:', error);
    }
}

// פונקציות עזר
window.addNewCemetery = function() {
    console.log('➕ Add new cemetery');
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, null);
    } else if (window.tableRenderer && window.tableRenderer.addItem) {
        window.tableRenderer.addItem();
    } else {
        alert('מערכת הטפסים לא זמינה');
    }
};

window.editCemetery = function(cemeteryId) {
    console.log('✏️ Edit cemetery:', cemeteryId);
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, cemeteryId);
    } else if (window.tableRenderer && window.tableRenderer.editItem) {
        window.tableRenderer.editItem(cemeteryId);
    } else {
        alert('מערכת הטפסים לא זמינה');
    }
};

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
            if (cemeteriesSearch) {
                cemeteriesSearch.refresh();
            } else {
                window.loadAllCemeteries();
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

window.openCemetery = function(cemeteryId, cemeteryName) {
    console.log('🏛️ Opening cemetery:', cemeteryId, cemeteryName);
    
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    window.currentCemeteryId = cemeteryId;
    
    if (typeof BreadcrumbManager !== 'undefined') {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    if (window.tableRenderer && window.tableRenderer.loadAndDisplay) {
        window.tableRenderer.loadAndDisplay('block', cemeteryId);
    } else if (typeof loadBlocksForCemetery === 'function') {
        loadBlocksForCemetery(cemeteryId);
    }
};

console.log('✅ Cemeteries Universal Search Module Loaded - FIXED VERSION');