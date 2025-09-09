// dashboards/cemeteries/js/customers-management.js
// ניהול לקוחות

// משתנים גלובליים
let currentCustomers = [];
let currentCustomerPage = 1;
let editingCustomerId = null;

// טעינת לקוחות
async function loadCustomers() {
    console.log('Loading customers...');
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכון ה-breadcrumb
    const breadcrumb = document.getElementById('breadcrumb');
    if (breadcrumb) {
        breadcrumb.innerHTML = '<span class="breadcrumb-item">ראשי</span> / <span class="breadcrumb-item active">לקוחות</span>';
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
            <th>אימייל</th>
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
    
    // עדכן את כפתורי הפעולה
    const actionButtons = document.querySelector('.action-buttons');
    if (actionButtons) {
        actionButtons.innerHTML = `
            <button class="btn btn-secondary" onclick="refreshData()">
                <svg class="icon"><use xlink:href="#icon-refresh"></use></svg>
                רענון
            </button>
            <button class="btn btn-primary" onclick="openAddCustomer()">
                <svg class="icon"><use xlink:href="#icon-plus"></use></svg>
                לקוח חדש
            </button>
        `;
    }
    
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
        console.log('Response headers:', response.headers.get('content-type'));
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON:', parseError);
            console.error('Response was:', responseText);
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
        console.error('Error details:', {
            message: error.message,
            stack: error.stack
        });
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
    
    // אל תנסה לעדכן את tableHeaders כאן - זה כבר נעשה ב-loadCustomers
    
    // סמן שאנחנו במצב לקוחות
    tableBody.setAttribute('data-customer-view', 'true');
    
    // עדכן את כפתורי הפעולה
    const actionButtons = document.querySelector('.action-buttons');
    if (actionButtons) {
        actionButtons.innerHTML = `
            <button class="btn btn-secondary" onclick="refreshData()">
                <svg class="icon"><use xlink:href="#icon-refresh"></use></svg>
                רענון
            </button>
            <button class="btn btn-primary" onclick="openAddCustomer()">
                <svg class="icon"><use xlink:href="#icon-plus"></use></svg>
                לקוח חדש
            </button>
        `;
    }
    
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
        <tr data-id="${customer.id}">
            <td><input type="checkbox" class="customer-checkbox" value="${customer.id}"></td>
            <td>${customer.id_number || '-'}</td>
            <td>
                <strong>${customer.first_name} ${customer.last_name}</strong>
                ${customer.nickname ? `<br><small style="color: #666;">(${customer.nickname})</small>` : ''}
            </td>
            <td>
                ${customer.mobile_phone || customer.phone || '-'}
            </td>
            <td>${customer.email || '-'}</td>
            <td>${customer.city || '-'}</td>
            <td>${getCustomerStatusBadge(customer.customer_status)}</td>
            <td>${getCustomerTypeBadge(customer.type_id)}</td>
            <td>${formatDate(customer.created_at)}</td>
            <td>
                <div class="action-buttons" style="display: flex; gap: 5px;">
                    <button class="btn btn-sm" onclick="viewCustomer(${customer.id})" title="צפייה">
                        <svg class="icon-sm"><use xlink:href="#icon-search"></use></svg>
                    </button>
                    <button class="btn btn-sm" onclick="editCustomer(${customer.id})" title="עריכה">
                        <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm" onclick="deleteCustomer(${customer.id})" title="מחיקה">
                        <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
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
                
                // נקה את הכותרות אם צריך - הפונקציות המקוריות ידאגו לזה
                
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
        1: { label: 'רגיל', color: '#6b7280' },
        2: { label: 'VIP', color: '#f59e0b' }
    };
    
    const typeInfo = types[type] || { label: 'רגיל', color: '#6b7280' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

// פתיחת טופס הוספת לקוח
function openAddCustomer() {
    editingCustomerId = null;
    openCustomerModal('הוסף לקוח חדש');
}

// עריכת לקוח
async function editCustomer(id) {
    editingCustomerId = id;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            openCustomerModal('ערוך לקוח', data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי הלקוח');
    }
}

// פתיחת מודל לקוח
function openCustomerModal(title, customer = null) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 800px; max-height: 90vh; overflow-y: auto; width: 90%;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">${title}</h2>
            </div>
            <form id="customerForm" onsubmit="saveCustomer(event)">
                <div class="modal-body">
                    <!-- פרטים אישיים -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">פרטים אישיים</legend>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div>
                                <label>שם פרטי <span style="color: red;">*</span></label>
                                <input type="text" name="first_name" required value="${customer?.first_name || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label>שם משפחה <span style="color: red;">*</span></label>
                                <input type="text" name="last_name" required value="${customer?.last_name || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label>תעודת זהות</label>
                                <input type="text" name="id_number" value="${customer?.id_number || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- פרטי התקשרות -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">פרטי התקשרות</legend>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label>טלפון נייד</label>
                                <input type="tel" name="mobile_phone" value="${customer?.mobile_phone || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label>טלפון</label>
                                <input type="tel" name="phone" value="${customer?.phone || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label>אימייל</label>
                                <input type="email" name="email" value="${customer?.email || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label>עיר</label>
                                <input type="text" name="city" value="${customer?.city || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                    </fieldset>
                </div>
                
                <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">ביטול</button>
                    <button type="submit" class="btn btn-primary">שמור</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// סגירת מודל לקוח
function closeCustomerModal() {
    const modal = document.querySelector('.modal.show');
    if (modal) {
        modal.remove();
    }
    editingCustomerId = null;
}

// שמירת לקוח
async function saveCustomer(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const url = editingCustomerId 
            ? `/dashboard/dashboards/cemeteries/api/customers-api.php?action=update&id=${editingCustomerId}`
            : '/dashboard/dashboards/cemeteries/api/customers-api.php?action=create';
            
        const response = await fetch(url, {
            method: editingCustomerId ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            closeCustomerModal();
            fetchCustomers();
        } else {
            showError(result.error || 'שגיאה בשמירת הלקוח');
        }
    } catch (error) {
        console.error('Error saving customer:', error);
        showError('שגיאה בשמירה');
    }
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
                <h2 style="margin: 0;">פרטי לקוח - ${customer.first_name} ${customer.last_name}</h2>
            </div>
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">פרטים אישיים</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>ת.ז.:</strong> ${customer.id_number || '-'}</div>
                            <div><strong>טלפון נייד:</strong> ${customer.mobile_phone || '-'}</div>
                            <div><strong>טלפון:</strong> ${customer.phone || '-'}</div>
                            <div><strong>אימייל:</strong> ${customer.email || '-'}</div>
                            <div><strong>עיר:</strong> ${customer.city || '-'}</div>
                            <div><strong>סטטוס:</strong> ${getCustomerStatusBadge(customer.customer_status)}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editCustomer(${customer.id})">
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
            // לא נציג כרגע - אין לנו את האלמנט הזה
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