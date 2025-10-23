// ==========================================
// 🔄 UPGRADE - הוסף את זה לקובץ unified-table-renderer.js
// החלף את window.loadAllCemeteries הקיים (שורות 866-884)
// ==========================================

window.loadAllCemeteries = async function() {
    console.log('📍 Loading all cemeteries - UPGRADED VERSION');
    
    setActiveMenuItem('cemeteryItem');
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {};

    // ⭐ נקה
    DashboardCleaner.clear({ targetLevel: 'cemetery' });
    
    // ⭐ בנה מבנה חדש עם main-container (כמו loadCustomers)
    await buildCemeteriesMainContainer();
    
    // עדכן breadcrumb
    BreadcrumbManager.update({}, 'cemetery');
    
    // ⭐ טען עם UniversalSearch במקום tableRenderer
    await initCemeteriesUniversalSearch();
};

// ==========================================
// 🆕 פונקציה חדשה - בניית container
// ==========================================

async function buildCemeteriesMainContainer() {
    console.log('🏗️ Building cemeteries main container...');
    
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
    
    console.log('✅ Cemeteries main container built');
}

// ==========================================
// 🆕 פונקציה חדשה - UniversalSearch לבתי עלמין
// ==========================================

let cemeteriesSearch = null;
let cemeteriesTable = null;

async function initCemeteriesUniversalSearch() {
    cemeteriesSearch = new UniversalSearch({
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php',
            action: 'list',
            method: 'GET',
            params: {
                type: 'cemetery'
            },
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
                console.log('✅ UniversalSearch initialized for Cemeteries');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'cemeteries found');
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
    
    window.cemeteriesSearch = cemeteriesSearch;
    cemeteriesSearch.search();
    return cemeteriesSearch;
}

// ==========================================
// 🆕 פונקציה חדשה - רינדור שורות
// ==========================================

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
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                        <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    if (!tableWrapperExists && cemeteriesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting cemeteriesTable variable');
        cemeteriesTable = null;
        window.cemeteriesTable = null;
    }
    
    if (!cemeteriesTable || !tableWrapperExists) {
        console.log('✅ Creating new TableManager with', data.length, 'total items');
        initCemeteriesTable(data);
    } else {
        console.log('🔄 Updating existing TableManager with', data.length, 'total items');
        cemeteriesTable.setData(data);
    }
}

// ==========================================
// 🆕 פונקציה חדשה - אתחול טבלה
// ==========================================

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
                label: 'מס׳',
                width: '60px',
                type: 'index',
                sortable: false
            },
            {
                field: 'cemeteryNameHe',
                label: 'שם בית עלמין',
                width: '250px',
                type: 'text',
                sortable: true,
                render: (cemetery) => {
                    const mainName = cemetery.cemeteryNameHe || '';
                    const subName = cemetery.cemeteryNameEn ? `<div style="font-size: 0.85em; color: #6b7280;">${cemetery.cemeteryNameEn}</div>` : '';
                    return mainName + subName;
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
                    const coords = cemetery.coordinates ? `<div style="font-size: 0.85em; color: #6b7280;">📍 ${cemetery.coordinates}</div>` : '';
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
                    const phone = cemetery.contactPhoneName ? `<div style="font-size: 0.85em; color: #6b7280;">📞 ${cemetery.contactPhoneName}</div>` : '';
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
                    if (!cemetery.createDate) return '';
                    const date = new Date(cemetery.createDate);
                    return date.toLocaleDateString('he-IL');
                }
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '150px',
                sortable: false,
                render: (cemetery) => `
                    <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); tableRenderer.editItem('${cemetery.unicId}')" title="עריכה">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); tableRenderer.deleteItem('${cemetery.unicId}')" title="מחיקה">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openCemetery('${cemetery.unicId}', '${cemetery.cemeteryNameHe}')" title="פתח">
                        <i class="fas fa-folder-open"></i> כניסה
                    </button>
                `
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
    
    window.cemeteriesTable = cemeteriesTable;
    
    console.log('📊 Total cemeteries loaded:', data.length);
    console.log('📄 Items per page:', cemeteriesTable.config.itemsPerPage);
    console.log('📏 Scroll threshold:', cemeteriesTable.config.scrollThreshold + 'px');
    
    return cemeteriesTable;
}

console.log('✅ Cemeteries Upgrade Loaded - Using TableManager + UniversalSearch');