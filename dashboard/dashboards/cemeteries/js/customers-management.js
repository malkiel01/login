/*
 * File: dashboards/dashboard/cemeteries/assets/js/customers-management.js
 * Version: 3.2.0
 * Updated: 2025-11-03
 * Author: Malkiel
 * Change Summary:
 * - v3.2.0: אחידות מלאה עם cemeteries-management
 *   - שימוש ב-window.tableRenderer.editItem() במקום editCustomer()
 *   - הסרת פונקציית editCustomer() מיותרת
 *   - הוספת window.loadCustomers export
 *   - מבנה זהה לחלוטין ל-cemeteries (רמת שורש)
 * - v3.1.0: שיפורים והתאמה לארכיטקטורה המאוחדת
 *   - עדכון onResults עם state.totalResults ו-updateCounter()
 *   - הוספת window.customerSearch export
 * - v3.0.0: שיטה זהה לבתי עלמין - UniversalSearch + TableManager
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
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'customer';
    window.currentParentId = null;

    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'customer' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'customer' });
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
            
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('customersItem');
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
        itemsPerPage: 999999,
        
        renderFunction: renderCustomersRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for customers');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults2: (data) => {
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

            onResults: (data) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'customers');
                
                // ⭐ טיפול בדפים - מצטבר!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentCustomers = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentCustomers = [...currentCustomers, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentCustomers.length}`);
                }
                
                // ⭐ אין סינון client-side - זו רמת השורש!
                let filteredCount = currentCustomers.length;
                
                // ⭐⭐⭐ עדכן ישירות את customerSearch!
                if (customerSearch && customerSearch.state) {
                    customerSearch.state.totalResults = filteredCount;
                    if (customerSearch.updateCounter) {
                        customerSearch.updateCounter();
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
    
    // ⭐ עדכן את window.customerSearch מיד!
    window.customerSearch = customerSearch;
    
    return customerSearch;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
async function initCustomersTable(data, totalItems = null) {
     const actualTotalItems = totalItems !== null ? totalItems : data.length;
   
    if (customersTable) {
        customersTable.config.totalItems = actualTotalItems;
        customersTable.setData(data);
        return customersTable;
    }

    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'customer') {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${entityType}&section=table_columns`);
            
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
                
                // טיפול בסוגי עמודות מיוחדות - ספציפי לקברים
                switch (column.type) {
                    case 'date':
                        column.render = (item) => formatDate(item[column.field]);
                        break;
                        
                    case 'status':
                        if (column.render === 'formatCustomerStatus') {
                            column.render = (item) => formatCustomerStatus(item[column.field]);
                        }
                        break;
                        
                    case 'type':
                        if (column.render === 'formatCustomerType') {
                            column.render = (item) => formatCustomerType(item[column.field]);
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
                        // עמודת טקסט רגילה
                        if (!column.render) {
                            column.render = (item) => item[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
        } catch (error) {
            console.error('❌ Failed to load columns config:', error);
            // החזר מערך רק במקרה של שגיאה
            return [];
        }
    }
    
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
        // containerWidth: '80vw',
        // fixedLayout: true,
        
        // scrolling: {
        //     enabled: true,
        //     headerHeight: '50px',
        //     itemsPerPage: 50,
        //     scrollThreshold: 300
        // },
        
        // ⭐ הוספת totalItems כפרמטר!
        totalItems: actualTotalItems,

        columns: await loadColumnsFromConfig('customer'),
        
        // columns: [
        //     {
        //         field: 'numId',
        //         label: 'ת.ז.',
        //         width: '120px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'firstName',
        //         label: 'שם פרטי',
        //         width: '150px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'lastName',
        //         label: 'שם משפחה',
        //         width: '150px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'phone',
        //         label: 'טלפון',
        //         width: '120px',
        //         type: 'text',
        //         sortable: false
        //     },
        //     {
        //         field: 'phoneMobile',
        //         label: 'נייד',
        //         width: '120px',
        //         type: 'text',
        //         sortable: false
        //     },
        //     {
        //         field: 'email',
        //         label: 'אימייל',
        //         width: '200px',
        //         type: 'text',
        //         sortable: false
        //     },
        //     {
        //         field: 'streetAddress',
        //         label: 'רחוב',
        //         width: '150px',
        //         type: 'text',
        //         sortable: false
        //     },
        //     {
        //         field: 'city_name',
        //         label: 'עיר',
        //         width: '120px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'statusCustomer',
        //         label: 'סטטוס',
        //         width: '100px',
        //         type: 'number',
        //         sortable: true,
        //         render: (customer) => formatCustomerStatus(customer.statusCustomer)
        //     },
        //     {
        //         field: 'statusResident',
        //         label: 'סוג',
        //         width: '100px',
        //         type: 'number',
        //         sortable: true,
        //         render: (customer) => formatCustomerType(customer.statusResident)
        //     },
        //     {
        //         field: 'createDate',
        //         label: 'תאריך',
        //         width: '120px',
        //         type: 'date',
        //         sortable: true,
        //         render: (customer) => formatDate(customer.createDate)
        //     },
        //     {
        //         field: 'actions',
        //         label: 'פעולות',
        //         width: '120px',
        //         sortable: false,
        //         render: (customer) => `
        //             <button class="btn btn-sm btn-secondary" onclick="editCustomer('${customer.unicId}')" title="עריכה">
        //                 <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
        //             </button>
        //             <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${customer.unicId}')" title="מחיקה">
        //                 <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
        //             </button>
        //         `
        //     }
        // ],

        onRowDoubleClick: (customer) => {                    // ⭐ שורה חדשה
            handleCustomerDoubleClick(customer.unicId);
        },
        
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
 
    return customersTable;
}

// ===================================================================
// רינדור שורות לקוחות - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderCustomersRows(data, container, pagination = null) {
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
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
        initCustomersTable(data, totalItems);  // ⭐ העברת totalItems!
    } else {    
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

// ===================================================
// פונקציה לטיפול בדאבל-קליק על לקוח
// ===================================================
async function handleCustomerDoubleClick(customerId) {
    console.log('🖱️ Double-click on customer:', customerId);
    
    try {
        // יצירת והצגת כרטיס
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
window.deleteCustomer = deleteCustomer;
window.refreshData = refreshData;
window.customersTable = customersTable;
window.checkScrollStatus = checkScrollStatus;
window.customerSearch = customerSearch;