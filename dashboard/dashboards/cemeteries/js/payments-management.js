// dashboards/cemeteries/js/payments-management.js
// ניהול תשלומים - בדיוק כמו customers-management.js

// משתנים גלובליים
let currentPayments = [];
let currentPaymentPage = 1;
let editingPaymentId = null;

// טעינת תשלומים
async function loadPayments() {
    console.log('Loading payments...');

    clearItemCard();
    
    // נקה את כל הסידבר
    clearAllSidebarSelections();
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'payment';
    window.currentParentId = null;
    
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
        updateBreadcrumb({ payment: { name: 'ניהול תשלומים' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול תשלומים - מערכת בתי עלמין';
    
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
            <th>מזהה</th>
            <th>סוג חלקה</th>
            <th>סוג קבר</th>
            <th>תושב</th>
            <th>הגדרת מחיר</th>
            <th>מחיר</th>
            <th>תאריך התחלה</th>
            <th>תאריך יצירה</th>
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
            <td colspan="9" style="text-align: center; padding: 40px;">
                טוען תשלומים...
            </td>
        </tr>
    `;
    
    // טען את הנתונים
    await fetchPayments();
    await loadPaymentStats();
}

// טעינת נתונים מהשרת
async function fetchPayments() {
    try {
        const params = new URLSearchParams({
            action: 'list',
            page: currentPaymentPage,
            limit: 20
        });
        
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/payments-api.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            currentPayments = data.data;
            displayPaymentsInTable(data.data);
        } else {
            showError(data.error || 'שגיאה בטעינת תשלומים');
        }
    } catch (error) {
        console.error('Error loading payments:', error);
        showError('שגיאה בטעינת נתונים');
    }
}

// הצגת תשלומים בטבלה הקיימת
function displayPaymentsInTable(payments) {
    const tableBody = document.getElementById('tableBody');
    
    if (!tableBody) {
        console.error('Table body not found');
        return;
    }
    
    // סמן שאנחנו במצב תשלומים
    tableBody.setAttribute('data-payment-view', 'true');
    
    if (payments.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">💰</div>
                        <div>לא נמצאו תשלומים</div>
                        <button class="btn btn-primary mt-3" onclick="openAddPayment()">
                            הוסף תשלום חדש
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tableBody.innerHTML = payments.map(payment => `
        <tr data-id="${payment.id}">
            <td>${payment.id}</td>
            <td>${getPlotTypeBadge(payment.plotType)}</td>
            <td>${getGraveTypeBadge(payment.graveType)}</td>
            <td>${getResidentBadge(payment.resident)}</td>
            <td>${getPriceDefinitionBadge(payment.priceDefinition)}</td>
            <td>₪${parseFloat(payment.price || 0).toLocaleString()}</td>
            <td>${formatDate(payment.startPayment)}</td>
            <td>${formatDate(payment.createDate)}</td>
            <td>
                <div class="action-buttons" style="display: flex; gap: 5px;">
                    <button class="btn btn-sm" onclick="viewPayment(${payment.id})" title="צפייה">
                        <svg class="icon-sm"><use xlink:href="#icon-search"></use></svg>
                    </button>
                    <button class="btn btn-sm" onclick="editPayment(${payment.id})" title="עריכה">
                        <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm" onclick="deletePayment(${payment.id})" title="מחיקה">
                        <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// פונקציות עזר לתגיות סטטוס
function getPlotTypeBadge(type) {
    const types = {
        1: { label: 'פטורה', color: '#10b981' },
        2: { label: 'חריגה', color: '#f97316' },
        3: { label: 'סגורה', color: '#dc2626' }
    };
    
    const typeInfo = types[type] || { label: '-', color: '#999' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getGraveTypeBadge(type) {
    const types = {
        1: 'שדה',
        2: 'רוויה',
        3: 'סנהדרין'
    };
    return types[type] || '-';
}

function getResidentBadge(type) {
    const types = {
        1: { label: 'ירושלים', color: '#10b981' },
        2: { label: 'חוץ', color: '#f97316' },
        3: { label: 'חו״ל', color: '#dc2626' }
    };
    
    const typeInfo = types[type] || { label: '-', color: '#999' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getPriceDefinitionBadge(type) {
    const types = {
        1: 'עלות קבר',
        2: 'שירותי לוויה',
        3: 'שירותי קבורה',
        4: 'אגרת מצבה',
        5: 'בדיקת עומק',
        6: 'פירוק מצבה',
        7: 'הובלה',
        8: 'טהרה',
        9: 'תכריכים',
        10: 'החלפת שם'
    };
    
    return types[type] || '-';
}

// פתיחת טופס הוספת תשלום
function openAddPayment() {
    window.currentType = 'payment';
    FormHandler.openForm('payment', null, null);
}

// עריכת תשלום
async function editPayment(id) {
    editingPaymentId = id;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/payments-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            openPaymentModal('ערוך תשלום', data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי התשלום');
    }
}

// פתיחת מודל תשלום
function openPaymentModal(title, payment = null) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 900px; max-height: 90vh; overflow-y: auto; width: 90%;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">${title}</h2>
            </div>
            <form id="paymentForm" onsubmit="savePayment(event)">
                <div class="modal-body">
                    <!-- סוגים -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">סוגים</legend>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label>סוג חלקה</label>
                                <select name="plotType" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">בחר סוג</option>
                                    <option value="1" ${payment?.plotType == 1 ? 'selected' : ''}>פטורה</option>
                                    <option value="2" ${payment?.plotType == 2 ? 'selected' : ''}>חריגה</option>
                                    <option value="3" ${payment?.plotType == 3 ? 'selected' : ''}>סגורה</option>
                                </select>
                            </div>
                            <div>
                                <label>סוג קבר</label>
                                <select name="graveType" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">בחר סוג</option>
                                    <option value="1" ${payment?.graveType == 1 ? 'selected' : ''}>שדה</option>
                                    <option value="2" ${payment?.graveType == 2 ? 'selected' : ''}>רוויה</option>
                                    <option value="3" ${payment?.graveType == 3 ? 'selected' : ''}>סנהדרין</option>
                                </select>
                            </div>
                            <div>
                                <label>סוג תושב</label>
                                <select name="resident" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">בחר סוג</option>
                                    <option value="1" ${payment?.resident == 1 ? 'selected' : ''}>ירושלים והסביבה</option>
                                    <option value="2" ${payment?.resident == 2 ? 'selected' : ''}>תושב חוץ</option>
                                    <option value="3" ${payment?.resident == 3 ? 'selected' : ''}>תושב חו״ל</option>
                                </select>
                            </div>
                            <div>
                                <label>סטטוס רוכש</label>
                                <select name="buyerStatus" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">בחר סטטוס</option>
                                    <option value="1" ${payment?.buyerStatus == 1 ? 'selected' : ''}>בחיים</option>
                                    <option value="2" ${payment?.buyerStatus == 2 ? 'selected' : ''}>לאחר פטירה</option>
                                    <option value="3" ${payment?.buyerStatus == 3 ? 'selected' : ''}>בן זוג נפטר</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- מחיר ותשלום -->
                    <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <legend style="padding: 0 10px; font-weight: bold;">מחיר ותשלום</legend>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label>הגדרת מחיר</label>
                                <select name="priceDefinition" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">בחר הגדרה</option>
                                    <option value="1" ${payment?.priceDefinition == 1 ? 'selected' : ''}>מחיר עלות הקבר</option>
                                    <option value="2" ${payment?.priceDefinition == 2 ? 'selected' : ''}>שירותי לוויה</option>
                                    <option value="3" ${payment?.priceDefinition == 3 ? 'selected' : ''}>שירותי קבורה</option>
                                    <option value="4" ${payment?.priceDefinition == 4 ? 'selected' : ''}>אגרת מצבה</option>
                                    <option value="5" ${payment?.priceDefinition == 5 ? 'selected' : ''}>בדיקת עומק קבר</option>
                                    <option value="6" ${payment?.priceDefinition == 6 ? 'selected' : ''}>פירוק מצבה</option>
                                    <option value="7" ${payment?.priceDefinition == 7 ? 'selected' : ''}>הובלה מנתבג</option>
                                    <option value="8" ${payment?.priceDefinition == 8 ? 'selected' : ''}>טהרה</option>
                                    <option value="9" ${payment?.priceDefinition == 9 ? 'selected' : ''}>תכריכי פשתן</option>
                                    <option value="10" ${payment?.priceDefinition == 10 ? 'selected' : ''}>החלפת שם</option>
                                </select>
                            </div>
                            <div>
                                <label>מחיר <span style="color: red;">*</span></label>
                                <input type="number" name="price" required value="${payment?.price || ''}" 
                                       step="0.01" min="0"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label>תאריך התחלת תשלום</label>
                                <input type="date" name="startPayment" value="${payment?.startPayment || ''}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                    </fieldset>
                </div>
                
                <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">ביטול</button>
                    <button type="submit" class="btn btn-primary">שמור</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// סגירת מודל תשלום
function closePaymentModal() {
    const modal = document.querySelector('.modal.show');
    if (modal) {
        modal.remove();
    }
    editingPaymentId = null;
}

// שמירת תשלום
async function savePayment(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        const url = editingPaymentId 
            ? `/dashboard/dashboards/cemeteries/api/payments-api.php?action=update&id=${editingPaymentId}`
            : '/dashboard/dashboards/cemeteries/api/payments-api.php?action=create';
            
        const response = await fetch(url, {
            method: editingPaymentId ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            closePaymentModal();
            fetchPayments();
        } else {
            showError(result.error || 'שגיאה בשמירת התשלום');
        }
    } catch (error) {
        console.error('Error saving payment:', error);
        showError('שגיאה בשמירה');
    }
}

// מחיקת תשלום
async function deletePayment(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק תשלום זה?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/payments-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('התשלום נמחק בהצלחה');
            fetchPayments();
        } else {
            showError(result.error || 'שגיאה במחיקת התשלום');
        }
    } catch (error) {
        console.error('Error deleting payment:', error);
        showError('שגיאה במחיקה');
    }
}

// צפייה בתשלום
async function viewPayment(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/payments-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            showPaymentDetails(data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי התשלום');
    }
}

// הצגת פרטי תשלום
function showPaymentDetails(payment) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">פרטי תשלום #${payment.id}</h2>
            </div>
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">פרטי התשלום</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>סוג חלקה:</strong> ${getPlotTypeBadge(payment.plotType)}</div>
                            <div><strong>סוג קבר:</strong> ${getGraveTypeBadge(payment.graveType)}</div>
                            <div><strong>תושב:</strong> ${getResidentBadge(payment.resident)}</div>
                            <div><strong>הגדרת מחיר:</strong> ${getPriceDefinitionBadge(payment.priceDefinition)}</div>
                            <div><strong>מחיר:</strong> ₪${parseFloat(payment.price || 0).toLocaleString()}</div>
                            <div><strong>תאריך התחלה:</strong> ${formatDate(payment.startPayment)}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editPayment(${payment.id})">
                    ערוך
                </button>
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">סגור</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// טעינת סטטיסטיקות
async function loadPaymentStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('Payment stats loaded:', data.data);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// פונקציות הודעות - כמו בcustomers
function showSuccess(message) {
    showToast('success', message);
}

function showError(message) {
    showToast('error', message);
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
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
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
        <span>${type === 'success' ? '✓' : '✗'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// הפוך את הפונקציה לגלובלית
window.loadPayments = loadPayments;