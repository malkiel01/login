// dashboards/cemeteries/js/payments-management.js
// ניהול תשלומים - בדיוק כמו customers-management.js

// משתנים גלובליים
let currentPayments = [];
let currentPaymentPage = 1;
let editingPaymentId = null;

// טעינת תשלומים
async function loadPayments() {
    console.log('Loading payments...');

    setActiveMenuItem('paymentsItem'); // ✅ הוסף את זה
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'payment';
    window.currentParentId = null;
    DashboardCleaner.clear({ targetLevel: 'payment' });
    
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
        <tr data-id="${payment.id}" ondblclick="viewPayment(${payment.id})" style="cursor: pointer;">
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
    window.currentParentId = null;
    FormHandler.openForm('payment', null, null);
}

// עריכת תשלום
async function editPayment(id) {
    window.currentType = 'payment';
    FormHandler.openForm('payment', null, id);
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

// צפייה בתשלום - פתיחת כרטיס תשלום
async function viewPayment(id) {
    console.log('💰 Opening payment card:', id);
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('paymentCard', null, id);
    } else {
        // fallback לעריכה
        editPayment(id);
    }
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
