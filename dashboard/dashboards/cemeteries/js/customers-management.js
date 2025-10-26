/*
 * File: dashboards/dashboard/cemeteries/assets/js/customers-management.js
 * Version: 3.0.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - v3.0.0: שיטה זהה לבתי עלמין - UniversalSearch + TableManager
 * - תיקון Virtual Scroll - itemsPerPage: 200 (במקום 999999)
 * - תיקון קונפליקט שמות - initCustomersSearch (במקום initUniversalSearch)
 * - הוספת Backward Compatibility
 * - שיפור הערות והפרדה ויזואלית
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================

let currentCustomers = [];
let customerSearch = null;
let customersTable = null;
let editingCustomerId = null;

// טעינת לקוחות (הפונקציה הראשית)
async function loadCustomers() {
    console.log('📋 Loading customers - v3.0.0 (תוקן Virtual Scroll וקונפליקט שמות)...');

    setActiveMenuItem('customersItem');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'customer';
    window.currentParentId = null;
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    DashboardCleaner.clear({ targetLevel: 'customer' });
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ customer: { name: 'לקוחות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול לקוחות - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildCustomersContainer();

    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (customerSearch && typeof customerSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous customerSearch instance...');
        customerSearch.destroy();
        customerSearch = null;
        window.customerSearch = null;
    }

    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh customerSearch instance...');
    await initCustomersSearch();
    customerSearch.search();
    
    // טען סטטיסטיקות
    await loadCustomerStats();
}

// ===================================================================
// ⭐ פונקציה חדשה - בניית המבנה של לקוחות ב-main-container
// ===================================================================
async function buildCustomersContainer() {
    console.log('🏗️ Building customers container...');
    
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
    
    // ⭐ בנה את התוכן של לקוחות
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="customerSearchSection" class="search-section"></div>
        
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
// אתחול UniversalSearch - שימוש בפונקציה גלובלית!
// ===================================================================
async function initCustomersSearch() {
    customerSearch = window.initUniversalSearch({
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
        itemsPerPage: 280,
        
        renderFunction: renderCustomersRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for customers');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'customers found');
                
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentCustomers = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentCustomers = [...currentCustomers, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentCustomers.length}`);
                }
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
    
    // ⭐ עדכן את window.customerSearch מיד!
    window.customerSearch = customerSearch;
    
    return customerSearch;
}

// ===================================================================
// אתחול TableManager
// ===================================================================
function initCustomersTable2(data) {
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (customersTable) {
        customersTable.setData(data);
        return customersTable;
    }
    
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
        containerWidth: '80vw',
        fixedLayout: true,
        
        scrolling: {
            enabled: true,
            headerHeight: '50px',
            itemsPerPage: 50,
            scrollThreshold: 300
        },
        
        columns: [
            {
                field: 'numId',
                label: 'ת.ז.',
                width: '120px',
                type: 'text',
                sortable: true
            },
            {
                field: 'firstName',
                label: 'שם פרטי',
                width: '150px',
                type: 'text',
                sortable: true
            },
            {
                field: 'lastName',
                label: 'שם משפחה',
                width: '150px',
                type: 'text',
                sortable: true
            },
            {
                field: 'phone',
                label: 'טלפון',
                width: '120px',
                type: 'text',
                sortable: false
            },
            {
                field: 'phoneMobile',
                label: 'נייד',
                width: '120px',
                type: 'text',
                sortable: false
            },
            {
                field: 'email',
                label: 'אימייל',
                width: '200px',
                type: 'text',
                sortable: false
            },
            {
                field: 'streetAddress',
                label: 'רחוב',
                width: '150px',
                type: 'text',
                sortable: false
            },
            {
                field: 'city_name',
                label: 'עיר',
                width: '120px',
                type: 'text',
                sortable: true
            },
            {
                field: 'statusCustomer',
                label: 'סטטוס',
                width: '100px',
                type: 'number',
                sortable: true,
                render: (customer) => formatCustomerStatus(customer.statusCustomer)
            },
            {
                field: 'statusResident',
                label: 'סוג',
                width: '100px',
                type: 'number',
                sortable: true,
                render: (customer) => formatCustomerType(customer.statusResident)
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (customer) => formatDate(customer.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (customer) => `
                    <button class="btn btn-sm btn-secondary" onclick="editCustomer('${customer.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${customer.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
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
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = customersTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });


    // מאזין לאירוע גלילה לסוף - טען עוד נתונים
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && customerSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            // אם הגענו לתחתית והטעינה עוד לא בתהליך
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!customerSearch.state.isLoading && customerSearch.state.currentPage < customerSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    // בקש עמוד הבא מ-UniversalSearch
                    const nextPage = customerSearch.state.currentPage + 1;
                    
                    // עדכן את הדף הנוכחי
                    customerSearch.state.currentPage = nextPage;
                    customerSearch.state.isLoading = true;
                    
                    // בקש נתונים
                    await customerSearch.search();
                }
            }
        });
    }
    
    // ⭐ עדכן את window.customersTable מיד!
    window.customersTable = customersTable;
    
    console.log('📊 Total customers loaded:', data.length);
    console.log('📄 Items per page:', customersTable.config.itemsPerPage);
    console.log('📏 Scroll threshold:', customersTable.config.scrollThreshold + 'px');
    
    return customersTable;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
function initCustomersTable(data, totalItems = null) {
    // ⭐ אם לא קיבלנו totalItems, השתמש ב-data.length
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    console.log(`📊 Initializing TableManager for customers with ${data.length} items (total: ${actualTotalItems})...`);
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (customersTable) {
        customersTable.config.totalItems = actualTotalItems;  // ⭐ עדכן totalItems!
        customersTable.setData(data);
        return customersTable;
    }
    
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
        containerWidth: '80vw',
        fixedLayout: true,
        
        scrolling: {
            enabled: true,
            headerHeight: '50px',
            itemsPerPage: 50,
            scrollThreshold: 300
        },
        
        // ⭐ הוספת totalItems כפרמטר!
        totalItems: actualTotalItems,
        
        columns: [
            {
                field: 'numId',
                label: 'ת.ז.',
                width: '120px',
                type: 'text',
                sortable: true
            },
            {
                field: 'firstName',
                label: 'שם פרטי',
                width: '150px',
                type: 'text',
                sortable: true
            },
            {
                field: 'lastName',
                label: 'שם משפחה',
                width: '150px',
                type: 'text',
                sortable: true
            },
            {
                field: 'phone',
                label: 'טלפון',
                width: '120px',
                type: 'text',
                sortable: false
            },
            {
                field: 'phoneMobile',
                label: 'נייד',
                width: '120px',
                type: 'text',
                sortable: false
            },
            {
                field: 'email',
                label: 'אימייל',
                width: '200px',
                type: 'text',
                sortable: false
            },
            {
                field: 'streetAddress',
                label: 'רחוב',
                width: '150px',
                type: 'text',
                sortable: false
            },
            {
                field: 'city_name',
                label: 'עיר',
                width: '120px',
                type: 'text',
                sortable: true
            },
            {
                field: 'statusCustomer',
                label: 'סטטוס',
                width: '100px',
                type: 'number',
                sortable: true,
                render: (customer) => formatCustomerStatus(customer.statusCustomer)
            },
            {
                field: 'statusResident',
                label: 'סוג',
                width: '100px',
                type: 'number',
                sortable: true,
                render: (customer) => formatCustomerType(customer.statusResident)
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (customer) => formatDate(customer.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (customer) => `
                    <button class="btn btn-sm btn-secondary" onclick="editCustomer('${customer.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${customer.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
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
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = customersTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });

    // מאזין לאירוע גלילה לסוף - טען עוד נתונים
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && customerSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            // אם הגענו לתחתית והטעינה עוד לא בתהליך
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!customerSearch.state.isLoading && customerSearch.state.currentPage < customerSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    // בקש עמוד הבא מ-UniversalSearch
                    const nextPage = customerSearch.state.currentPage + 1;
                    
                    // עדכן את הדף הנוכחי
                    customerSearch.state.currentPage = nextPage;
                    customerSearch.state.isLoading = true;
                    
                    // בקש נתונים
                    await customerSearch.search();
                }
            }
        });
    }
    
    // ⭐ עדכן את window.customersTable מיד!
    window.customersTable = customersTable;
    
    console.log(`📊 Total customers loaded: ${data.length}`);
    console.log(`📄 Items per page: ${customersTable.config.scrolling?.itemsPerPage || 50}`);
    console.log(`📏 Scroll threshold: ${customersTable.config.scrolling?.scrollThreshold || 300}px`);
    console.log(`📦 Total items in database: ${actualTotalItems}`);
    
    return customersTable;
}

// ===================================================================
// רינדור שורות לקוחות
// ===================================================================
function renderCustomersRows2(data, container) {
    console.log('🎨 renderCustomersRows called with', data.length, 'items');
    
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
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && customersTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting customersTable variable');
        customersTable = null;
        window.customersTable = null;
    }

    // עכשיו בדוק אם צריך לבנות מחדש
    if (!customersTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        console.log('✅ Creating new TableManager with', data.length, 'total items');
        initCustomersTable(data);
    } else {
        // TableManager קיים וגם ה-DOM שלו - רק עדכן נתונים
        console.log('🔄 Updating existing TableManager with', data.length, 'total items');
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (customerSearch && customerSearch.state) {
            const allData = customerSearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                customersTable.setData(allData);
                return;
            }
        }
        
        customersTable.setData(data);
    }
}

// ===================================================================
// רינדור שורות לקוחות - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderCustomersRows(data, container, pagination = null) {
    console.log('🎨 renderCustomersRows called with', data.length, 'items');
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
    const totalItems = pagination?.total || data.length;
    console.log(`📊 Total items in database: ${totalItems}`);
    
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
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && customersTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting customersTable variable');
        customersTable = null;
        window.customersTable = null;
    }

    // עכשיו בדוק אם צריך לבנות מחדש
    if (!customersTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        console.log(`✅ Creating new TableManager with ${data.length} visible items, ${totalItems} total`);
        initCustomersTable(data, totalItems);  // ⭐ העברת totalItems!
    } else {
        // TableManager קיים וגם ה-DOM שלו - רק עדכן נתונים
        console.log(`🔄 Updating existing TableManager with ${data.length} visible items, ${totalItems} total`);
        
        // ⭐ עדכן גם את totalItems ב-TableManager!
        if (customersTable.config) {
            customersTable.config.totalItems = totalItems;
        }
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (customerSearch && customerSearch.state) {
            const allData = customerSearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                customersTable.setData(allData);
                return;
            }
        }
        
        customersTable.setData(data);
    }
}

// ===================================================================
// פונקציות פורמט ועזר
// ===================================================================
function formatCustomerType(type) {
    const types = {
        1: 'תושב',
        2: 'תושב חוץ',
        3: 'אחר'
    };
    return types[type] || '-';
}

// פורמט סטטוס לקוח
function formatCustomerStatus(status) {
    const statuses = {
        1: { text: 'פעיל', color: '#10b981' },
        0: { text: 'לא פעיל', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || statuses[1];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// ===================================================================
// פונקציות CRUD
// ===================================================================
async function deleteCustomer(customerId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק לקוח זה?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=delete&id=${customerId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('הלקוח נמחק בהצלחה', 'success');
            
            if (customerSearch) {
                customerSearch.refresh();
            }
        } else {
            showToast(data.error || 'שגיאה במחיקת לקוח', 'error');
        }
    } catch (error) {
        console.error('Error deleting customer:', error);
        showToast('שגיאה במחיקת לקוח', 'error');
    }
}

// עריכת לקוח
async function editCustomer(customerId) {
    console.log('Edit customer:', customerId);
    editingCustomerId = customerId;
    showToast('עריכה בפיתוח...', 'info');
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadCustomerStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('Customer stats:', data.data);
        }
    } catch (error) {
        console.error('Error loading customer stats:', error);
    }
}

// הצגת הודעת Toast
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

// פונקציה לרענון נתונים
async function refreshData() {
    if (customerSearch) {
        customerSearch.refresh();
    }
}

// פונקציה לבדיקת סטטוס הטעינה
function checkScrollStatus() {
    if (!customersTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = customersTable.getFilteredData().length;
    const displayed = customersTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(customersTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// הפוך את הפונקציות לגלובליות
window.loadCustomers = loadCustomers;
window.deleteCustomer = deleteCustomer;
window.editCustomer = editCustomer;
window.refreshData = refreshData;
window.customersTable = customersTable;
window.checkScrollStatus = checkScrollStatus;

console.log('✅ Customers Management Module Loaded - FINAL: Clean & Simple');
console.log('💡 Commands: checkScrollStatus() - בדוק כמה רשומות נטענו');