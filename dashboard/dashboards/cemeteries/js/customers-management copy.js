// dashboards/cemeteries/js/customers-management.js
// ניהול לקוחות עם חיפוש חי

// משתנים גלובליים
let currentCustomers = [];
let customersLiveSearch = null; // 🆕 מופע של LiveSearch
let editingCustomerId = null;

// 🆕 אתחול החיפוש החי
function initCustomersLiveSearch() {
    customersLiveSearch = new LiveSearch({
        searchInputId: 'customerSearchInput',
        counterElementId: 'customerCounter',
        resultContainerId: 'tableBody',
        paginationContainerId: 'paginationContainer',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/customers-api.php',
        instanceName: 'customersLiveSearch',
        debounceDelay: 300,
        itemsPerPage: 50,
        minSearchLength: 2,
        renderFunction: renderCustomersRows
    });
    
    console.log('✅ Customers LiveSearch initialized');
}

// 🆕 פונקציית רינדור מותאמת אישית
function renderCustomersRows(data, container) {
    if (data.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
                        <div>לא נמצאו לקוחות</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    currentCustomers = data;
    
    container.innerHTML = data.map(customer => `
        <tr data-id="${customer.unicId}">
            <td><input type="checkbox" class="customer-checkbox" value="${customer.unicId}"></td>
            <td>${customer.numId || '-'}</td>
            <td>
                <strong>${customer.firstName || ''} ${customer.lastName || ''}</strong>
                ${customer.nomPerson ? `<br><small style="color:#666;">${customer.nomPerson}</small>` : ''}
            </td>
            <td>
                ${customer.phone || '-'}
                ${customer.phoneMobile ? `<br><small>${customer.phoneMobile}</small>` : ''}
            </td>
            <td>${customer.email || '-'}</td>
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
    `).join('');
}

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
    
    // 🆕 הוסף שדה חיפוש לפני הטבלה
    const mainContent = document.querySelector('.main-content');
    let searchSection = document.getElementById('customerSearchSection');
    
    if (!searchSection) {
        searchSection = document.createElement('div');
        searchSection.id = 'customerSearchSection';
        searchSection.className = 'search-section';
        searchSection.innerHTML = `
            <div class="search-container">
                <input 
                    type="text" 
                    id="customerSearchInput" 
                    class="search-input" 
                    placeholder="חיפוש לקוחות לפי שם, ת.ז., טלפון..."
                />
                <svg class="search-icon"><use xlink:href="#icon-search"></use></svg>
            </div>
            <div id="customerCounter" class="search-counter"></div>
        `;
        
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
        
        // עדכן את הכותרות
        headerRow.innerHTML = `
            <th style="width: 40px;">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
            </th>
            <th>ת.ז.</th>
            <th>שם מלא</th>
            <th>טלפון</th>
            <th>אימייל</th>
            <th>כתובת</th>
            <th>עיר</th>
            <th>סטטוס</th>
            <th>סוג</th>
            <th>תאריך</th>
            <th style="width: 120px;">פעולות</th>
        `;
    }
    
    const tableBody = document.getElementById('tableBody');
    if (tableBody) {
        tableBody.setAttribute('data-customer-view', 'true');
    }
    
    // 🆕 הוסף קונטיינר ל-pagination
    let paginationContainer = document.getElementById('paginationContainer');
    if (!paginationContainer) {
        paginationContainer = document.createElement('div');
        paginationContainer.id = 'paginationContainer';
        document.querySelector('.table-container').appendChild(paginationContainer);
    }
    
    // 🆕 אתחל את החיפוש החי
    if (!customersLiveSearch) {
        initCustomersLiveSearch();
    } else {
        customersLiveSearch.refresh();
    }
    
    // טען סטטיסטיקות
    await loadCustomerStats();
}

// שאר הפונקציות נשארות זהות...
// (formatCustomerStatus, formatCustomerType, deleteCustomer, editCustomer, וכו')

// פורמט סטטוס לקוח
function formatCustomerStatus(status) {
    const statuses = {
        1: { text: 'פעיל', color: '#10b981' },
        0: { text: 'לא פעיל', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || statuses[1];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
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
            customersLiveSearch.refresh(); // 🆕 רענון עם LiveSearch
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
    // הקוד הקיים שלך לעריכה...
    console.log('Edit customer:', customerId);
}

// טעינת סטטיסטיקות
async function loadCustomerStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('Customer stats:', data.data);
            // הוסף קוד להצגת סטטיסטיקות אם צריך
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
    if (customersLiveSearch) {
        customersLiveSearch.refresh();
    }
}

// הפוך את הפונקציות לגלובליות
window.loadCustomers = loadCustomers;
window.deleteCustomer = deleteCustomer;
window.editCustomer = editCustomer;
window.refreshData = refreshData;
