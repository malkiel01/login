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
// בניית המבנה
// ===================================================================
async function buildCustomersContainer(signal) {
    
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
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
            },
            
            onSearch: (query, filters) => {
                
                // ⭐ כאשר מתבצע חיפוש - הפעל מצב חיפוש
                customersIsSearchMode = true;
                customersCurrentQuery = query;
            },

            onResults: async (data, signal) => {
                
                // ⭐ אם נכנסנו למצב חיפוש - הצג רק תוצאות חיפוש
                if (customersIsSearchMode && customersCurrentQuery) {
                    customersSearchResults = data.data;
                    
                    const tableBody = document.getElementById('tableBody');
                    if (tableBody) {
                        await renderCustomersRows(customersSearchResults, tableBody, data.pagination, signal);
                    }
                    return;
                }
                
                // ⭐⭐⭐ בדיקה קריטית - אם עברנו לרשומה אחרת, לא להמשיך!
                if (window.currentType !== 'customer') {
                    return;
                }
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש לקוחות', 'error');
            },

            onEmpty: () => {
            },
            
            onClear: async () => {
                
                // ⭐ איפוס מצב חיפוש
                customersIsSearchMode = false;
                customersCurrentQuery = '';
                customersSearchResults = [];
                
                // ⭐ חזרה למצב Browse
                await loadCustomersBrowseData(signal);
            }
        }
    };
    
    const searchInstance = await window.initUniversalSearch(config);
    
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
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            const count = customersTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        },

        // ⭐ לחיצה כפולה - פתיחת כרטיס לקוח
        onRowDoubleClick: (customer) => {
            if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
                FormHandler.openForm('customerCard', null, customer.unicId);
            }
        }
    });
    
    window.customersTable = customersTable;
    return customersTable;
}


// ===================================================================
// רינדור שורות - עם תמיכה ב-Search Mode
// ===================================================================
async function renderCustomersRows(data, container, pagination = null, signal = null) {
    
    // ⭐⭐ במצב חיפוש - הצג תוצאות חיפוש בלי טבלה מורכבת
    if (customersIsSearchMode && customersCurrentQuery) {
        
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
        customersTable = null;
        window.customersTable = null;
    }
    
    if (!customersTable || !tableWrapperExists) {
        await initCustomersTable(data, totalItems, signal);
    } else {
        if (customersTable.config) {
            customersTable.config.totalItems = totalItems;
        }
        customersTable.setData(data);
    }
}

// // ===================================================================
// // הפנייה לפונקציות גלובליות
// // ===================================================================

// function checkCustomersScrollStatus() {
//     checkEntityScrollStatus(customersTable, 'Customers');
// }

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

function formatCustomerStatus(status) {
    return formatEntityStatus('customer', status);
}

// ===================================================================
// דאבל-קליק על לקוח - פתיחת כרטיס לקוח
// ===================================================================
async function handleCustomerDoubleClick(customer) {

    // תמיכה גם באובייקט וגם ב-ID
    let customerId;
    if (typeof customer === 'object' && customer !== null) {
        customerId = customer.unicId || customer.id;
    } else {
        customerId = customer;
    }


    // פתיחת כרטיס לקוח חדש
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('customerCard', null, customerId);
    } else {
        console.error('❌ FormHandler not available');
        showToast('שגיאה בפתיחת כרטיס לקוח', 'error');
    }
}

window.handleCustomerDoubleClick = handleCustomerDoubleClick;
// ===================================================================
// הפוך לגלובלי
// ===================================================================

window.customersTable = customersTable;

window.customerSearch = customerSearch;

