// dashboards/cemeteries/js/customers-management.js
// ניהול לקוחות עם UniversalSearch

let currentCustomers = [];
let customerSearch = null;
let editingCustomerId = null;

// טעינת לקוחות (הפונקציה הראשית)
async function loadCustomers() {
    console.log('Loading customers...');

    setActiveMenuItem('customersItem');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'customer';
    window.currentParentId = null;
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
    
    // הוסף קונטיינר חיפוש אם לא קיים
    const mainContent = document.querySelector('.main-content');
    let searchSection = document.getElementById('customerSearchSection');
    
    if (!searchSection) {
        searchSection = document.createElement('div');
        searchSection.id = 'customerSearchSection';
        searchSection.className = 'search-section';
        
        // הוסף לפני הטבלה
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            mainContent.insertBefore(searchSection, tableContainer);
        }
    }
    
    // וודא שמבנה הטבלה קיים
    const table = document.getElementById('mainTable');
    if (table) {
        let thead = table.querySelector('thead');
        if (!thead) {
            thead = document.createElement('thead');
            table.insertBefore(thead, table.querySelector('tbody'));
        }
        
        let headerRow = thead.querySelector('tr');
        if (!headerRow) {
            headerRow = document.createElement('tr');
            headerRow.id = 'tableHeaders';
            thead.appendChild(headerRow);
        }
        
        // עדכן את הכותרות - בדיוק לפי המבנה שלך
        headerRow.innerHTML = `
            <th style="width: 40px;">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
            </th>
            <th>ת.ז.</th>
            <th>שם מלא</th>
            <th>טלפון</th>
            <th>כתובת</th>
            <th>עיר</th>
            <th>סטטוס</th>
            <th>סוג</th>
            <th>תאריך</th>
            <th style="width: 120px;">פעולות</th>
        `;
    }
    
    // אתחל את UniversalSearch
    if (!customerSearch) {
        await initUniversalSearch();
        // 🆕 טען את כל הלקוחות מיד לאחר האתחול
        customerSearch.search();
    } else {
        customerSearch.refresh();
    }
    
    // טען סטטיסטיקות
    await loadCustomerStats();
}

// אתחול UniversalSearch
async function initUniversalSearch() {
    customerSearch = new UniversalSearch({
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/customers-api.php',
            action: 'list',
            method: 'GET',
            tables: ['customers'],
            joins: []
        },
        
        // 🎯 כאן אתה מגדיר בדיוק אלו שדות יהיו בפילטר!
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
        
        display: {
            containerSelector: '#customerSearchSection',
            showAdvanced: true,
            showFilters: true,
            placeholder: 'חיפוש לקוחות לפי שם, ת.ז, טלפון...',
            layout: 'horizontal',
            minSearchLength: 0
        },
        
        results: {
            containerSelector: '#tableBody',
            itemsPerPage: 50,
            showPagination: true,
            showCounter: true,
            columns: ['numId', 'firstName', 'lastName', 'phone', 'streetAddress', 'city_name', 'statusCustomer', 'statusResident', 'createDate'],
            renderFunction: renderCustomersRows
        },
        
        behavior: {
            realTime: true,
            autoSubmit: true,
            highlightResults: true
        },
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for customers');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'customers found');
                currentCustomers = data.data;
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
    
    return customerSearch;
}

// רינדור שורות לקוחות
function renderCustomersRows(data, container) {
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
    
    container.innerHTML = data.map(customer => {
        return `
            <tr data-id="${customer.unicId}">
                <td>
                    <input type="checkbox" class="customer-checkbox" value="${customer.unicId}">
                </td>
                <td>${customer.numId || '-'}</td>
                <td>
                    <strong>${customer.firstName || ''} ${customer.lastName || ''}</strong>
                    ${customer.nomPerson ? '<br><small style="color:#666;">' + customer.nomPerson + '</small>' : ''}
                </td>
                <td>
                    ${customer.phone || '-'}
                    ${customer.phoneMobile ? '<br><small style="color:#666;">' + customer.phoneMobile + '</small>' : ''}
                </td>
                <td>${customer.streetAddress || '-'}</td>
                <td>${customer.city_name || '-'}</td>
                <td>${formatCustomerStatus(customer.statusCustomer)}</td>
                <td>${formatCustomerType(customer.statusResident)}</td>
                <td>${formatDate(customer.createDate)}</td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="editCustomer('${customer.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${customer.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// פורמט סוג לקוח
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

// מחיקת לקוח
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
            customerSearch.refresh();
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
    // הקוד שלך לעריכה...
}

// טעינת סטטיסטיקות
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
    `;
    
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// פונקציה לרענון נתונים
async function refreshData() {
    if (customerSearch) {
        customerSearch.refresh();
    }
}

// הפוך את הפונקציות לגלובליות
window.loadCustomers = loadCustomers;
window.deleteCustomer = deleteCustomer;
window.editCustomer = editCustomer;
window.refreshData = refreshData;