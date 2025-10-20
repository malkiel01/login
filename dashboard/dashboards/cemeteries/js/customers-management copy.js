// dashboards/cemeteries/js/customers-management.js
// ניהול לקוחות

// משתנים גלובליים
let currentCustomers = [];
let currentCustomerPage = 1;
let editingCustomerId = null;

// טעינת לקוחות
async function loadCustomers() {
    console.log('Loading customers...');
    
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
    
    // וודא שמבנה הטבלה קיים
    const table = document.getElementById('mainTable');
    if (table) {
        // בדוק אם יש thead
        let thead = table.querySelector('thead');
        if (!thead) {
            thead = document.createElement('thead');
            table.insertBefore(thead, table.querySelector('tbody'));
        }
        
        // בדוק אם יש tr בתוך thead
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
            <th>כתובת</th>
            <th>עיר</th>
            <th>סטטוס</th>
            <th>סוג</th>
            <th>תאריך</th>
            <th style="width: 120px;">פעולות</th>
        `;
    }
    
    const tableBody = document.getElementById('tableBody');
    
    if (!tableBody) {
        console.error('Table body not found');
        return;
    }
    
    // הצג הודעת טעינה
    tableBody.innerHTML = `
        <tr>
            <td colspan="10" style="text-align: center; padding: 40px;">
                טוען לקוחות...
            </td>
        </tr>
    `;
    
    console.log('About to fetch customers...');
    
    // טען את הנתונים
    await fetchCustomers();
    await loadCustomerStats();
}

// פונקציה נפרדת לרענון נתונים
async function refreshData() {
    if (document.querySelector('[data-customer-view]')) {
        // אנחנו במצב לקוחות
        await fetchCustomers();
    } else {
        // אנחנו במצב רגיל - קרא לפונקציה המקורית
        if (typeof refreshAllData === 'function') {
            refreshAllData();
        }
    }
}

// טעינת נתונים מהשרת
async function fetchCustomers() {
    try {
        const params = new URLSearchParams({
            action: 'list',
            page: currentCustomerPage,
            limit: 20
        });
        
        const url = `/dashboard/dashboards/cemeteries/api/customers-api.php?${params}`;
        console.log('Fetching from URL:', url);
        
        const response = await fetch(url);
        console.log('Response status:', response.status);
        
        const responseText = await response.text();
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON:', parseError);
            showError('שגיאה בפענוח התגובה מהשרת');
            return;
        }
        
        console.log('Parsed data:', data);
        
        if (data.success) {
            currentCustomers = data.data;
            displayCustomersInTable(data.data);
        } else {
            console.error('Server returned error:', data.error);
            showError(data.error || 'שגיאה בטעינת לקוחות');
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        showError('שגיאה בטעינת נתונים');
    }
}

// הצגת לקוחות בטבלה הקיימת
function displayCustomersInTable(customers) {
    const tableBody = document.getElementById('tableBody');
    
    if (!tableBody) {
        console.error('Table body not found');
        return;
    }
    
    // סמן שאנחנו במצב לקוחות
    tableBody.setAttribute('data-customer-view', 'true');
    
    if (customers.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">👥</div>
                        <div>לא נמצאו לקוחות</div>
                        <button class="btn btn-primary mt-3" onclick="openAddCustomer()">
                            הוסף לקוח חדש
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = customers.map(customer => `
        <tr data-id="${customer.unicId}">
            <td><input type="checkbox" class="customer-checkbox" value="${customer.unicId}"></td>
            <td>${customer.numId || '-'}</td>
            <td>
                <strong>${customer.firstName || ''} ${customer.lastName || ''}</strong>
                ${customer.nom ? `<br><small style="color: #666;">(${customer.nom})</small>` : ''}
            </td>
            <td>${customer.phoneMobile || customer.phone || '-'}</td>
            <td>${customer.address || '-'}</td>
            <td>${customer.city_name || customer.cityNameHe || '-'}</td>
            <td>${getCustomerStatusBadge(customer.statusCustomer)}</td>
            <td>${getCustomerTypeBadge(customer.typeId)}</td>
            <td>${formatDate(customer.createDate)}</td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-sm btn-info" onclick="viewCustomer('${customer.unicId}')">
                        צפה
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="editCustomer('${customer.unicId}')">
                        ערוך
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${customer.unicId}')">
                        מחק
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// כשעוברים חזרה לבתי עלמין - נקה את סימון הלקוחות
window.addEventListener('load', function() {
    // הוסף listener לכל הפונקציות של בתי עלמין
    const hierarchyFunctions = [
        'loadAllCemeteries',
        'loadAllBlocks', 
        'loadAllPlots',
        'loadAllAreaGraves',
        'loadAllGraves'
    ];
    
    hierarchyFunctions.forEach(funcName => {
        const originalFunc = window[funcName];
        if (originalFunc) {
            window[funcName] = function() {
                // הסר את הסימון של לקוחות
                const tableBody = document.getElementById('tableBody');
                if (tableBody) {
                    tableBody.removeAttribute('data-customer-view');
                }
                
                // קרא לפונקציה המקורית
                return originalFunc.apply(this, arguments);
            };
        }
    });
});

// פונקציות עזר לתגיות סטטוס
function getCustomerStatusBadge(status) {
    const statuses = {
        1: { label: 'פעיל', color: '#10b981' },
        2: { label: 'רכש', color: '#3b82f6' },
        3: { label: 'נפטר', color: '#6b7280' }
    };
    
    const statusInfo = statuses[status] || { label: 'לא ידוע', color: '#999' };
    return `<span style="background: ${statusInfo.color}20; color: ${statusInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${statusInfo.label}</span>`;
}

function getCustomerTypeBadge(type) {
    const types = {
        1: 'ת.ז.',
        2: 'דרכון',
        3: 'אלמוני',
        4: 'תינוק'
    };
    
    return types[type] || 'לא מוגדר';
}

// פתיחת טופס הוספת לקוח
function openAddCustomer() {
    window.currentType = 'customer';
    window.currentParentId = null;
    
    FormHandler.openForm('customer', null, null);
}

// עריכת לקוח
async function editCustomer(id) {
    window.currentType = 'customer';  
    FormHandler.openForm('customer', null, id);
}

// מחיקת לקוח
async function deleteCustomer(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק לקוח זה?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('הלקוח נמחק בהצלחה');
            fetchCustomers();
        } else {
            showError(result.error || 'שגיאה במחיקת הלקוח');
        }
    } catch (error) {
        console.error('Error deleting customer:', error);
        showError('שגיאה במחיקה');
    }
}

// צפייה בלקוח
async function viewCustomer(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            showCustomerDetails(data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי הלקוח');
    }
}

// הצגת פרטי לקוח
function showCustomerDetails(customer) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">פרטי לקוח - ${customer.firstName || ''} ${customer.lastName || ''}</h2>
            </div>
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">פרטים אישיים</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>ת.ז.:</strong> ${customer.numId || '-'}</div>
                            <div><strong>טלפון נייד:</strong> ${customer.phoneMobile || '-'}</div>
                            <div><strong>טלפון:</strong> ${customer.phone || '-'}</div>
                            <div><strong>כתובת:</strong> ${customer.address || '-'}</div>
                            <div><strong>עיר:</strong> ${customer.cityNameHe || customer.city_name || '-'}</div>
                            <div><strong>סטטוס:</strong> ${getCustomerStatusBadge(customer.statusCustomer)}</div>
                            ${customer.nameFather ? `<div><strong>שם אב:</strong> ${customer.nameFather}</div>` : ''}
                            ${customer.nameMother ? `<div><strong>שם אם:</strong> ${customer.nameMother}</div>` : ''}
                            ${customer.dateBirth ? `<div><strong>תאריך לידה:</strong> ${formatDate(customer.dateBirth)}</div>` : ''}
                            ${customer.age ? `<div><strong>גיל:</strong> ${customer.age}</div>` : ''}
                        </div>
                    </div>
                    ${customer.comment ? `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">הערות</h4>
                        <div>${customer.comment}</div>
                    </div>
                    ` : ''}
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editCustomer('${customer.unicId}')">
                    ערוך
                </button>
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">סגור</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// טעינת סטטיסטיקות
async function loadCustomerStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.data;
            console.log('Customer stats loaded:', stats);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// בחירת כל הלקוחות
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// ייצוא לקוחות
function exportCustomers() {
    showInfo('פונקציית הייצוא בפיתוח');
}

// פונקציות הודעות
function showSuccess(message) {
    showToast('success', message);
}

function showError(message) {
    showToast('error', message);
}

function showInfo(message) {
    showToast('info', message);
}

function showToast(type, message) {
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = 'toast';
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

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// הפוך את הפונקציה לגלובלית
window.loadCustomers = loadCustomers;