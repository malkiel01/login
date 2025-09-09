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
    clearAllSidebarSelections();
    
    // עדכון כותרת
    document.getElementById('pageTitle').textContent = 'ניהול לקוחות';
    
    // בניית ממשק הטבלה
    const mainContent = document.getElementById('mainContent');
    mainContent.innerHTML = `
        <div class="content-header" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 20px; align-items: center;">
                    <h2 style="margin: 0; color: #1a1a1a;">
                        <i class="fas fa-users" style="margin-left: 10px; color: #667eea;"></i>
                        ניהול לקוחות
                    </h2>
                    <div id="customersStats" style="display: flex; gap: 15px;">
                        <!-- סטטיסטיקות יוטענו כאן -->
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-success" onclick="exportCustomers()">
                        <i class="fas fa-download"></i> ייצוא
                    </button>
                    <button class="btn btn-primary" onclick="openAddCustomer()">
                        <i class="fas fa-plus"></i> לקוח חדש
                    </button>
                </div>
            </div>
        </div>
        
        <!-- סרגל חיפוש וסינון -->
        <div class="filters-bar" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: center;">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                    <input type="text" id="customerSearch" placeholder="חיפוש לפי שם, ת.ז., טלפון או אימייל..." 
                           style="width: 100%; padding: 10px 40px; border: 1px solid #e5e7eb; border-radius: 8px;"
                           onkeyup="debounceSearch()">
                </div>
                <select id="customerStatusFilter" onchange="loadCustomers()" 
                        style="padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <option value="">כל הסטטוסים</option>
                    <option value="1">פעיל</option>
                    <option value="2">רכש</option>
                    <option value="3">נפטר</option>
                </select>
                <select id="customerTypeFilter" onchange="loadCustomers()"
                        style="padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <option value="">כל הסוגים</option>
                    <option value="1">רגיל</option>
                    <option value="2">VIP</option>
                </select>
                <button class="btn btn-secondary" onclick="clearFilters()">
                    <i class="fas fa-times"></i> נקה
                </button>
            </div>
        </div>
        
        <!-- טבלת לקוחות -->
        <div class="table-container" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <table class="data-table">
                <thead>
                    <tr>
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
                        <th>תאריך הצטרפות</th>
                        <th style="width: 120px;">פעולות</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">
                            <div class="spinner"></div>
                            טוען נתונים...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- עימוד -->
        <div id="customersPagination" style="margin-top: 20px; display: flex; justify-content: center;">
            <!-- כפתורי עימוד יוטענו כאן -->
        </div>
    `;
    
    // טעינת נתונים
    await fetchCustomers();
    await loadCustomerStats();
}

// טעינת נתונים מהשרת
async function fetchCustomers() {
    try {
        const search = document.getElementById('customerSearch')?.value || '';
        const status = document.getElementById('customerStatusFilter')?.value || '';
        const type = document.getElementById('customerTypeFilter')?.value || '';
        
        const params = new URLSearchParams({
            action: 'list',
            page: currentCustomerPage,
            limit: 20,
            search: search,
            status: status
        });
        
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            currentCustomers = data.data;
            displayCustomers(data.data);
            displayPagination(data.pagination);
        } else {
            showError('שגיאה בטעינת לקוחות');
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        showError('שגיאה בטעינת נתונים');
    }
}

// הצגת לקוחות בטבלה
function displayCustomers(customers) {
    const tbody = document.getElementById('customersTableBody');
    
    if (customers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 20px;"></i>
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
    
    tbody.innerHTML = customers.map(customer => `
        <tr data-id="${customer.id}">
            <td><input type="checkbox" class="customer-checkbox" value="${customer.id}"></td>
            <td>${customer.id_number || '-'}</td>
            <td>
                <strong>${customer.first_name} ${customer.last_name}</strong>
                ${customer.nickname ? `<br><small style="color: #666;">(${customer.nickname})</small>` : ''}
            </td>
            <td>
                ${customer.mobile_phone || customer.phone || '-'}
                ${customer.mobile_phone && customer.phone ? '<br>' + customer.phone : ''}
            </td>
            <td>${customer.email || '-'}</td>
            <td>${customer.city || '-'}</td>
            <td>${getCustomerStatusBadge(customer.customer_status)}</td>
            <td>${getCustomerTypeBadge(customer.type_id)}</td>
            <td>${formatDate(customer.created_at)}</td>
            <td>
                <div class="action-buttons" style="display: flex; gap: 5px;">
                    <button class="btn btn-sm btn-info" onclick="viewCustomer(${customer.id})" title="צפייה">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="editCustomer(${customer.id})" title="עריכה">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer(${customer.id})" title="מחיקה">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

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
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeCustomerModal()"></div>
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h2>${title}</h2>
                <button class="close-btn" onclick="closeCustomerModal()">×</button>
            </div>
            <form id="customerForm" onsubmit="saveCustomer(event)">
                <div class="modal-body">
                    <!-- פרטים אישיים -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">פרטים אישיים</legend>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div>
                                <label>שם פרטי <span class="required">*</span></label>
                                <input type="text" name="first_name" required value="${customer?.first_name || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>שם משפחה <span class="required">*</span></label>
                                <input type="text" name="last_name" required value="${customer?.last_name || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>תעודת זהות</label>
                                <input type="text" name="id_number" value="${customer?.id_number || ''}" 
                                       class="form-control" pattern="[0-9]{9}" title="9 ספרות">
                            </div>
                            <div>
                                <label>כינוי</label>
                                <input type="text" name="nickname" value="${customer?.nickname || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>שם קודם</label>
                                <input type="text" name="old_name" value="${customer?.old_name || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>מין</label>
                                <select name="gender" class="form-control">
                                    <option value="">-- בחר --</option>
                                    <option value="1" ${customer?.gender == 1 ? 'selected' : ''}>זכר</option>
                                    <option value="2" ${customer?.gender == 2 ? 'selected' : ''}>נקבה</option>
                                </select>
                            </div>
                            <div>
                                <label>תאריך לידה</label>
                                <input type="date" name="birth_date" value="${customer?.birth_date || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>ארץ לידה</label>
                                <input type="text" name="birth_country" value="${customer?.birth_country || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>מצב משפחתי</label>
                                <select name="marital_status" class="form-control">
                                    <option value="">-- בחר --</option>
                                    <option value="1" ${customer?.marital_status == 1 ? 'selected' : ''}>רווק/ה</option>
                                    <option value="2" ${customer?.marital_status == 2 ? 'selected' : ''}>נשוי/אה</option>
                                    <option value="3" ${customer?.marital_status == 3 ? 'selected' : ''}>גרוש/ה</option>
                                    <option value="4" ${customer?.marital_status == 4 ? 'selected' : ''}>אלמן/ה</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- פרטי משפחה -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">פרטי משפחה</legend>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div>
                                <label>שם האב</label>
                                <input type="text" name="father_name" value="${customer?.father_name || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>שם האם</label>
                                <input type="text" name="mother_name" value="${customer?.mother_name || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>שם בן/בת הזוג</label>
                                <input type="text" name="spouse_name" value="${customer?.spouse_name || ''}" 
                                       class="form-control">
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
                                       class="form-control">
                            </div>
                            <div>
                                <label>טלפון</label>
                                <input type="tel" name="phone" value="${customer?.phone || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>אימייל</label>
                                <input type="email" name="email" value="${customer?.email || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>מדינה</label>
                                <input type="text" name="country" value="${customer?.country || 'ישראל'}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>עיר</label>
                                <input type="text" name="city" value="${customer?.city || ''}" 
                                       class="form-control">
                            </div>
                            <div>
                                <label>כתובת</label>
                                <input type="text" name="address" value="${customer?.address || ''}" 
                                       class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- סטטוס וסיווג -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">סטטוס וסיווג</legend>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div>
                                <label>סוג לקוח</label>
                                <select name="type_id" class="form-control">
                                    <option value="1" ${customer?.type_id == 1 ? 'selected' : ''}>רגיל</option>
                                    <option value="2" ${customer?.type_id == 2 ? 'selected' : ''}>VIP</option>
                                </select>
                            </div>
                            <div>
                                <label>סטטוס לקוח</label>
                                <select name="customer_status" class="form-control">
                                    <option value="1" ${customer?.customer_status == 1 ? 'selected' : ''}>פעיל</option>
                                    <option value="2" ${customer?.customer_status == 2 ? 'selected' : ''}>רכש</option>
                                    <option value="3" ${customer?.customer_status == 3 ? 'selected' : ''}>נפטר</option>
                                </select>
                            </div>
                            <div>
                                <label>סטטוס תושבות</label>
                                <select name="resident_status" class="form-control">
                                    <option value="">-- בחר --</option>
                                    <option value="1" ${customer?.resident_status == 1 ? 'selected' : ''}>תושב</option>
                                    <option value="2" ${customer?.resident_status == 2 ? 'selected' : ''}>תייר</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- הערות -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">הערות</legend>
                        <textarea name="comments" rows="3" class="form-control" 
                                  style="width: 100%;">${customer?.comments || ''}</textarea>
                    </fieldset>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">ביטול</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> שמור
                    </button>
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
    
    // נקה ערכים ריקים
    Object.keys(data).forEach(key => {
        if (data[key] === '') {
            delete data[key];
        }
    });
    
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
    modal.innerHTML = `
        <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>פרטי לקוח - ${customer.first_name} ${customer.last_name}</h2>
                <button class="close-btn" onclick="this.closest('.modal').remove()">×</button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div style="display: grid; gap: 20px;">
                    <!-- פרטים אישיים -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">פרטים אישיים</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>ת.ז.:</strong> ${customer.id_number || '-'}</div>
                            <div><strong>מין:</strong> ${customer.gender == 1 ? 'זכר' : customer.gender == 2 ? 'נקבה' : '-'}</div>
                            <div><strong>תאריך לידה:</strong> ${formatDate(customer.birth_date) || '-'}</div>
                            <div><strong>גיל:</strong> ${customer.age || '-'}</div>
                            <div><strong>ארץ לידה:</strong> ${customer.birth_country || '-'}</div>
                            <div><strong>מצב משפחתי:</strong> ${getMaritalStatus(customer.marital_status)}</div>
                        </div>
                    </div>
                    
                    <!-- פרטי משפחה -->
                    ${(customer.father_name || customer.mother_name || customer.spouse_name) ? `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">פרטי משפחה</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            ${customer.father_name ? `<div><strong>שם האב:</strong> ${customer.father_name}</div>` : ''}
                            ${customer.mother_name ? `<div><strong>שם האם:</strong> ${customer.mother_name}</div>` : ''}
                            ${customer.spouse_name ? `<div><strong>בן/בת זוג:</strong> ${customer.spouse_name}</div>` : ''}
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- פרטי התקשרות -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">פרטי התקשרות</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>טלפון נייד:</strong> ${customer.mobile_phone || '-'}</div>
                            <div><strong>טלפון:</strong> ${customer.phone || '-'}</div>
                            <div><strong>אימייל:</strong> ${customer.email || '-'}</div>
                            <div><strong>כתובת:</strong> ${customer.address || '-'}, ${customer.city || ''}</div>
                        </div>
                    </div>
                    
                    <!-- רכישות -->
                    ${customer.purchases && customer.purchases.length > 0 ? `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">רכישות</h4>
                        <table style="width: 100%;">
                            <thead>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <th style="text-align: right; padding: 5px;">תאריך</th>
                                    <th style="text-align: right; padding: 5px;">קבר</th>
                                    <th style="text-align: right; padding: 5px;">מיקום</th>
                                    <th style="text-align: right; padding: 5px;">סטטוס</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${customer.purchases.map(p => `
                                    <tr>
                                        <td style="padding: 5px;">${formatDate(p.purchase_date)}</td>
                                        <td style="padding: 5px;">${p.grave_number || '-'}</td>
                                        <td style="padding: 5px;">${p.grave_location || '-'}</td>
                                        <td style="padding: 5px;">${getPurchaseStatus(p.purchase_status)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    ` : ''}
                    
                    <!-- הערות -->
                    ${customer.comments ? `
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 10px; color: #856404;">הערות</h4>
                        <p style="margin: 0;">${customer.comments}</p>
                    </div>
                    ` : ''}
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editCustomer(${customer.id})">
                    <i class="fas fa-edit"></i> ערוך
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
            const statsDiv = document.getElementById('customersStats');
            if (statsDiv) {
                statsDiv.innerHTML = `
                    <span style="padding: 5px 15px; background: #10b98120; color: #10b981; border-radius: 20px;">
                        פעילים: ${stats.by_status[1] || 0}
                    </span>
                    <span style="padding: 5px 15px; background: #3b82f620; color: #3b82f6; border-radius: 20px;">
                        רכשו: ${stats.by_status[2] || 0}
                    </span>
                    <span style="padding: 5px 15px; background: #f59e0b20; color: #f59e0b; border-radius: 20px;">
                        VIP: ${stats.by_type[2] || 0}
                    </span>
                    <span style="padding: 5px 15px; background: #667eea20; color: #667eea; border-radius: 20px;">
                        חדשים החודש: ${stats.new_this_month || 0}
                    </span>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// פונקציות עזר
function getMaritalStatus(status) {
    const statuses = {
        1: 'רווק/ה',
        2: 'נשוי/אה',
        3: 'גרוש/ה',
        4: 'אלמן/ה'
    };
    return statuses[status] || '-';
}

function getPurchaseStatus(status) {
    const statuses = {
        1: 'טיוטה',
        2: 'אושר',
        3: 'שולם',
        4: 'בוטל'
    };
    return statuses[status] || '-';
}

// עימוד
function displayPagination(pagination) {
    const paginationDiv = document.getElementById('customersPagination');
    if (!paginationDiv || !pagination) return;
    
    const { page, pages, total } = pagination;
    
    if (pages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }
    
    let html = '<div style="display: flex; gap: 5px; align-items: center;">';
    
    // כפתור קודם
    if (page > 1) {
        html += `<button class="btn btn-sm" onclick="goToCustomerPage(${page - 1})">‹ קודם</button>`;
    }
    
    // מספרי עמודים
    for (let i = 1; i <= Math.min(pages, 5); i++) {
        const active = i === page ? 'btn-primary' : 'btn-secondary';
        html += `<button class="btn btn-sm ${active}" onclick="goToCustomerPage(${i})">${i}</button>`;
    }
    
    if (pages > 5) {
        html += '<span>...</span>';
        html += `<button class="btn btn-sm btn-secondary" onclick="goToCustomerPage(${pages})">${pages}</button>`;
    }
    
    // כפתור הבא
    if (page < pages) {
        html += `<button class="btn btn-sm" onclick="goToCustomerPage(${page + 1})">הבא ›</button>`;
    }
    
    html += `<span style="margin-right: 20px; color: #666;">סה"כ: ${total} לקוחות</span>`;
    html += '</div>';
    
    paginationDiv.innerHTML = html;
}

function goToCustomerPage(page) {
    currentCustomerPage = page;
    fetchCustomers();
}

// חיפוש עם השהייה
let searchTimeout;
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentCustomerPage = 1;
        fetchCustomers();
    }, 500);
}

// ניקוי פילטרים
function clearFilters() {
    document.getElementById('customerSearch').value = '';
    document.getElementById('customerStatusFilter').value = '';
    document.getElementById('customerTypeFilter').value = '';
    currentCustomerPage = 1;
    fetchCustomers();
}

// בחירת כל הלקוחות
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// ייצוא לקוחות
function exportCustomers() {
    // TODO: implement export functionality
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
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    
    container.appendChild(toast);
    
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