/*
 * File: dashboards/dashboard/cemeteries/assets/js/customers-management.js
 * Version: 4.0.0
 * Updated: 2025-11-18
 * Author: Malkiel
 * Change Summary:
 * - v4.0.0: 🔥 שיטה זהה 100% ל-area-graves ו-graves
 *   ✅ הוספת משתני חיפוש ו-pagination:
 *   - customersIsSearchMode, customersCurrentQuery, customersSearchResults
 *   - customersCurrentPage, customersTotalPages, customersIsLoadingMore
 *   ✅ הוספת פונקציות חסרות:
 *   - loadCustomersBrowseData() - טעינה ישירה מ-API
 *   - appendMoreCustomers() - Infinite Scroll
 *   ✅ התאמת כל הפונקציות לשיטה המאוחדת
 * - v3.3.0: תיקון קונפליקטים בפונקציות גלובליות
 * - v3.2.0: אחידות מלאה עם cemeteries-management
 * - v3.0.0: שיטה זהה לבתי עלמין - UniversalSearch + TableManager
 */

console.log('🚀 customers-management.js v4.0.0 - Loading...');

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCustomers = [];
let customerSearch = null;
let customersTable = null;
let editingCustomerId = null;

let customersIsSearchMode = false;      // האם אנחנו במצב חיפוש?
let customersCurrentQuery = '';         // מה החיפוש הנוכחי?
let customersSearchResults = [];        // תוצאות החיפוש

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let customersCurrentPage = 1;
let customersTotalPages = 1;
let customersIsLoadingMore = false;


// ===================================================================
// טעינת לקוחות (הפונקציה הראשית)
// ===================================================================
async function loadCustomersBrowseData(signal = null) {
    customersCurrentPage = 1;
    currentCustomers = [];
    
    let apiUrl = '/dashboard/dashboards/cemeteries/api/customers-api.php?action=list&limit=200&page=1';
    apiUrl += '&orderBy=createDate&sortDirection=DESC';
    
    const response = await fetch(apiUrl, { signal });
    const result = await response.json();
    
    if (result.success && result.data) {
        currentCustomers = result.data;
        
        if (result.pagination) {
            customersTotalPages = result.pagination.pages;
            customersCurrentPage = result.pagination.page;
        }
        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            renderCustomersRows(result.data, tableBody, result.pagination, signal);
        }
    }
}
async function loadCustomers() {
    const signal = OperationManager.start('customer');

    // ⭐ איפוס מצב חיפוש
    customersIsSearchMode = false;
    customersCurrentQuery = '';
    customersSearchResults = [];
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'customer';
    window.currentParentId = null;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'customer';
    }

    // ⭐ נקה
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'customer' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'customer' });
    }
    
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('customersItem');
    }
    
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ customer: { name: 'לקוחות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול לקוחות - מערכת בתי עלמין';
    
    // ⭐ בנה מבנה
    await buildCustomersContainer(signal);
    
    if (OperationManager.shouldAbort('customer')) {
        return;
    }

    // ⭐ ספירת טעינות גלובלית
    if (!window.customersLoadCounter) {
        window.customersLoadCounter = 0;
    }
    window.customersLoadCounter++;
    
    // השמד חיפוש קודם
    if (customerSearch && typeof customerSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous customerSearch instance...');
        customerSearch.destroy();
        customerSearch = null; 
        window.customerSearch = null;
    }
    
    // ⭐ אתחול UniversalSearch - פעם אחת!
    console.log('🆕 Creating fresh customerSearch instance...');
    customerSearch = await initCustomersSearch(signal);
    
    if (OperationManager.shouldAbort('customer')) {
        console.log('⚠️ Customer operation aborted');
        return;
    }

    // ⭐ טעינה ישירה (Browse Mode) - פעם אחת!
    await loadCustomersBrowseData(signal);
    
    // טען סטטיסטיקות
    await loadCustomerStats(signal);
}


// ===================================================================
// 📥 טעינת עוד לקוחות (Infinite Scroll)
// ===================================================================
async function appendMoreCustomers() {
    // בדיקות בסיסיות
    if (customersIsLoadingMore) {
        return false;
    }
    
    if (customersCurrentPage >= customersTotalPages) {
        return false;
    }
    
    customersIsLoadingMore = true;
    const nextPage = customersCurrentPage + 1;
    
    // ⭐ עדכון מונה טעינות
    if (!window.customersLoadCounter) {
        window.customersLoadCounter = 0; 
    }
    window.customersLoadCounter++;
    
    try {
        // בנה URL לעמוד הבא
        let apiUrl = `/dashboard/dashboards/cemeteries/api/customers-api.php?action=list&limit=200&page=${nextPage}`;
        apiUrl += '&orderBy=createDate&sortDirection=DESC';
        
        // שלח בקשה
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
            // ⭐ שמור את הגודל הקודם לפני ההוספה
            const previousTotal = currentCustomers.length;
            
            // ⭐ הוסף לנתונים הקיימים
            currentCustomers = [...currentCustomers, ...result.data];
            customersCurrentPage = nextPage;
            
            // ⭐⭐⭐ לוג פשוט ומסודר
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ טעינה: ${window.customersLoadCounter}
╠════════════════════════════════════════════════════════════════════
║ כמות ערכים בטעינה: ${result.data.length}
║ מספר ערך תחילת טעינה נוכחית: ${result.debug?.results_info?.from_index || (previousTotal + 1)}
║ מספר ערך סוף טעינה נוכחית: ${result.debug?.results_info?.to_index || currentCustomers.length}
║ סך כל הערכים שנטענו עד כה: ${currentCustomers.length}
║ שדה למיון: ${result.debug?.sql_info?.order_field || 'createDate'}
║ סוג מיון: ${result.debug?.sql_info?.sort_direction || 'DESC'}
╠════════════════════════════════════════════════════════════════════
║ עמוד: ${customersCurrentPage} / ${customersTotalPages}
║ נותרו עוד: ${customersTotalPages - customersCurrentPage} עמודים
╚════════════════════════════════════════════════════════════════════
`);
            
            // ⭐ עדכן את הטבלה
            if (customersTable) {
                customersTable.setData(currentCustomers);
            }
            
            customersIsLoadingMore = false;
            return true;
        } else {
            console.log('📭 No more data to load');
            customersIsLoadingMore = false;
            return false;
        }
    } catch (error) {
        console.error('❌ Error loading more customers:', error);
        customersIsLoadingMore = false;
        return false;
    }
}


// ===================================================================
// בניית המבנה
// ===================================================================
async function buildCustomersContainer(signal) {
    console.log('🏗️ Building customers container...');
    
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
        <div id="customerSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען לקוחות...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Customers container built');
}


// ===================================================================
// אתחול UniversalSearch
// ===================================================================
async function initCustomersSearch(signal) {
    const config = {
        entityType: 'customer',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/customers-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'firstName',
                label: 'שם פרטי',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'lastName',
                label: 'שם משפחה',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'numId',
                label: 'תעודת זהות',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'phone',
                label: 'טלפון',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'phoneMobile',
                label: 'נייד',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'cityId',
                label: 'עיר',
                table: 'customers',
                type: 'text',
                matchType: ['exact']
            },
            {
                name: 'statusCustomer',
                label: 'סטטוס',
                table: 'customers',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: '1', label: 'פעיל' },
                    { value: '0', label: 'לא פעיל' }
                ]
            },
            {
                name: 'statusResident',
                label: 'סוג תושבות',
                table: 'customers',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: '1', label: 'תושב' },
                    { value: '2', label: 'תושב חוץ' },
                    { value: '3', label: 'אחר' }
                ]
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'customers',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['numId', 'firstName', 'lastName', 'phone', 'streetAddress', 'city_name', 'statusCustomer', 'statusResident', 'createDate'],
        
        searchContainerSelector: '#customerSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש לקוחות לפי שם, ת.ז, טלפון...',
        itemsPerPage: 999999,
        
        renderFunction: renderCustomersRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for customers');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
                
                // ⭐ כאשר מתבצע חיפוש - הפעל מצב חיפוש
                customersIsSearchMode = true;
                customersCurrentQuery = query;
            },

            onResults: async (data, signal) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'customers');
                
                // ⭐ אם נכנסנו למצב חיפוש - הצג רק תוצאות חיפוש
                if (customersIsSearchMode && customersCurrentQuery) {
                    console.log('🔍 Search mode active - showing search results only');
                    customersSearchResults = data.data;
                    
                    const tableBody = document.getElementById('tableBody');
                    if (tableBody) {
                        await renderCustomersRows(customersSearchResults, tableBody, data.pagination, signal);
                    }
                    return;
                }
                
                // ⭐⭐⭐ בדיקה קריטית - אם עברנו לרשומה אחרת, לא להמשיך!
                if (window.currentType !== 'customer') {
                    console.log('⚠️ Type changed during search - aborting customer results');
                    console.log(`   Current type is now: ${window.currentType}`);
                    return;
                }
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש לקוחות', 'error');
            },

            onEmpty: () => {
                console.log('📭 No results');
            },
            
            onClear: async () => {
                console.log('🧹 Search cleared - returning to browse mode');
                
                // ⭐ איפוס מצב חיפוש
                customersIsSearchMode = false;
                customersCurrentQuery = '';
                customersSearchResults = [];
                
                // ⭐ חזרה למצב Browse
                await loadCustomersBrowseData(signal);
            }
        }
    };
    
    const searchInstance = window.initUniversalSearch(config);
    
    return searchInstance;
}


// ===================================================================
// אתחול TableManager
// ===================================================================
async function initCustomersTable(data, totalItems = null, signal = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (customersTable) {
        customersTable.config.totalItems = actualTotalItems;
        customersTable.setData(data);
        return customersTable;
    }
        
    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'customer') {
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
            
            // המרת הקונפיג מ-PHP לפורמט של TableManager
            const columns = result.data.map(col => {
                const column = {
                    field: col.field,
                    label: col.title,
                    width: col.width || 'auto',
                    sortable: col.sortable !== false,
                    type: col.type || 'text'
                };
                
                // טיפול בסוגי עמודות מיוחדות
                switch(col.type) {
                    case 'date':
                        column.render = (customer) => formatDate(customer[column.field]);
                        break;
                        
                    case 'status':
                        if (column.render === 'formatCustomerStatus') {
                            column.render = (customer) => formatCustomerStatus(customer[column.field]);
                        }
                        break;
                        
                    case 'type':
                        if (column.render === 'formatCustomerType') {
                            column.render = (customer) => formatCustomerType(customer[column.field]);
                        }
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteCustomer('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;
                        
                    default:
                        if (!column.render) {
                            column.render = (customer) => customer[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
            
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('⚠️ Column config loading aborted - this is expected');
                return [];
            }
            
            console.error('❌ Failed to load columns config:', error);
            return [];
        }
    }

    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
        totalItems: actualTotalItems,

        columns: await loadColumnsFromConfig('customer'),

        data: data,
        
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        infiniteScroll: true,
        scrollThreshold: 200,
        onLoadMore: async () => {
            if (customersIsSearchMode) {
                // במצב חיפוש - טען דרך UniversalSearch
                if (customerSearch && typeof customerSearch.loadNextPage === 'function') {
                    if (customerSearch.state.currentPage >= customerSearch.state.totalPages) {
                        customersTable.state.hasMoreData = false;
                        return;
                    }
                    await customerSearch.loadNextPage();
                }
            } else {
                // במצב Browse - טען ישירות
                const success = await appendMoreCustomers();
                if (!success) {
                    customersTable.state.hasMoreData = false;
                }
            }
        },
        
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = customersTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    window.customersTable = customersTable;
    return customersTable;
}


// ===================================================================
// רינדור שורות - עם תמיכה ב-Search Mode
// ===================================================================
async function renderCustomersRows(data, container, pagination = null, signal = null) {
    console.log(`📝 renderCustomersRows called with ${data.length} items`);
    
    // ⭐⭐ במצב חיפוש - הצג תוצאות חיפוש בלי טבלה מורכבת
    if (customersIsSearchMode && customersCurrentQuery) {
        console.log('🔍 Rendering search results...');
        
        if (data.length === 0) {
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
        
        const totalItems = data.length;
        await initCustomersTable(data, totalItems, signal);
        return;
    }
    
    // ⭐⭐ מצב רגיל (Browse) - הצג עם TableManager
    const totalItems = pagination?.total || data.length;
    console.log(`📊 Total items to display: ${totalItems}`);

    if (data.length === 0) {
        if (customersTable) {
            customersTable.setData([]);
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
    
    if (!tableWrapperExists && customersTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting customersTable variable');
        customersTable = null;
        window.customersTable = null;
    }
    
    if (!customersTable || !tableWrapperExists) {
        console.log(`🏗️ Creating new TableManager with ${totalItems} items`);
        await initCustomersTable(data, totalItems, signal);
    } else {
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (customersTable.config) {
            customersTable.config.totalItems = totalItems;
        }
        customersTable.setData(data);
    }
}

// ===================================================================
// הפנייה לפונקציות גלובליות
// ===================================================================

function checkCustomersScrollStatus() {
    checkEntityScrollStatus(customersTable, 'Customers');
}

// ===================================================================
// פונקציות עזר לפורמט
// ===================================================================
function formatCustomerType(type) {
    const types = {
        1: 'תושב',
        2: 'תושב חוץ',
        3: 'אחר'
    };
    return types[type] || '-';
}

// function formatCustomerStatus(status) {
//     const statuses = {
//         1: { text: 'פעיל', color: '#10b981' },
//         0: { text: 'לא פעיל', color: '#ef4444' }
//     };
//     const statusInfo = statuses[status] || statuses[1];
//     return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
// }
function formatCustomerStatus(status) {
    return formatEntityStatus('customer', status);
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
// async function loadCustomerStats(signal) {
//     try {
//         const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats', { signal: signal });
//         const result = await response.json();
        
//         if (result.success && result.data) {
//             console.log('📊 Customer stats:', result.data);
            
//             if (document.getElementById('totalCustomers')) {
//                 document.getElementById('totalCustomers').textContent = result.data.total_customers || 0;
//             }
//             if (document.getElementById('activeCustomers')) {
//                 document.getElementById('activeCustomers').textContent = result.data.active || 0;
//             }
//             if (document.getElementById('newThisMonth')) {
//                 document.getElementById('newThisMonth').textContent = result.data.new_this_month || 0;
//             }
//         }
//     } catch (error) {
//         if (error.name === 'AbortError') {
//             console.log('⚠️ Customer stats loading aborted - this is expected');
//             return;
//         }
//         console.error('Error loading customer stats:', error);
//     }
// }
async function loadCustomerStats(signal) {
    await loadEntityStats('customer', signal);
}

// ===================================================================
// מחיקת לקוח
// ===================================================================
async function deleteCustomer(customerId) {
    await deleteEntity('customer', customerId);
}

// ===================================================================
// רענון נתונים
// ===================================================================
async function customersRefreshData() {
    await refreshEntityData('customer');
}

// ===================================================================
// דאבל-קליק על לקוח
// ===================================================================
async function handleCustomerDoubleClick(customerId) {
    console.log('🖱️ Double-click on customer:', customerId);
    
    try {
        if (typeof createCustomerCard === 'function') {
            const cardHtml = await createCustomerCard(customerId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        } else {
            console.warn('⚠️ createCustomerCard not found - opening edit form');
            if (typeof window.tableRenderer !== 'undefined' && window.tableRenderer.editItem) {
                window.tableRenderer.editItem(customerId);
            } else {
                console.error('❌ tableRenderer.editItem not available');
                showToast('שגיאה בפתיחת טופס עריכה', 'error');
            }
        }
    } catch (error) {
        console.error('❌ Error in handleCustomerDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי לקוח', 'error');
    }
}

window.handleCustomerDoubleClick = handleCustomerDoubleClick;
// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.loadCustomers = loadCustomers;

window.appendMoreCustomers = appendMoreCustomers;

window.deleteCustomer = deleteCustomer;

window.customersRefreshData = customersRefreshData;

window.customersTable = customersTable;

window.checkCustomersScrollStatus = checkCustomersScrollStatus;

window.customerSearch = customerSearch;

window.loadCustomersBrowseData = loadCustomersBrowseData;

console.log('✅ customers-management.js v4.0.0 - Loaded successfully!');