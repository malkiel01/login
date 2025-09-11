// purchases.js - ניהול רכישות
// גרסה מתוקנת וסופית

// משתנים גלובליים
let allPurchases = [];
let currentPurchasePage = 1;
let currentSort = { field: 'created_at', order: 'DESC' };

// טעינת כל הרכישות
async function loadAllPurchases(page = 1) {
    console.log('Loading all purchases...');
    currentPurchasePage = page;
    
    clearItemCard(); // נקה את הכרטיס כשעוברים לתצוגה כללית
    
    // נקה את כל הסידבר
    clearAllSidebarSelections();
    
    // עדכן סוג נוכחי
    window.currentType = 'purchase';
    window.currentParentId = null;
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    try {
        // השתמש ישירות ב-cemetery-hierarchy.php שעובד
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=purchase&page=${page}&limit=50&sort=${currentSort.field}&order=${currentSort.order}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            allPurchases = data.data || [];
            displayPurchasesTable(data.data || []);
            updatePurchasesPagination(data.pagination);
            updatePurchaseStats();
            
            // עדכן breadcrumb אם הפונקציה קיימת
            if (typeof updateBreadcrumb === 'function') {
                updateBreadcrumb('רכישות');
            }
        } else {
            throw new Error(data.error || 'Failed to load purchases');
        }
    } catch (error) {
        console.error('Error loading purchases:', error);
        showError('שגיאה בטעינת רכישות: ' + error.message);
    }
}

// הצגת טבלת רכישות
function displayPurchasesTable(purchases) {
    const tableHeaders = document.getElementById('tableHeaders');
    const tableBody = document.getElementById('tableBody');
    
    if (!tableHeaders || !tableBody) {
        console.error('Table elements not found');
        return;
    }
    
    // כותרות הטבלה
    tableHeaders.innerHTML = `
        <tr>
            <th>מס' רכישה</th>
            <th>תאריך רכישה</th>
            <th>לקוח</th>
            <th>מיקום קבר</th>
            <th>סכום</th>
            <th>סטטוס</th>
            <th>פעולות</th>
        </tr>
    `;
    
    // נתוני הטבלה
    if (!purchases || purchases.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div style="padding: 40px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📋</div>
                        <div>אין רכישות במערכת</div>
                        <button class="btn btn-primary mt-3" onclick="openAddPurchase()">
                            הוסף רכישה ראשונה
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tableBody.innerHTML = purchases.map(purchase => {
        const statusInfo = getPurchaseStatusInfo(purchase.purchase_status);
        return `
            <tr>
                <td>${purchase.id}</td>
                <td>${formatDate(purchase.purchase_date)}</td>
                <td>
                    ${purchase.customer_name || 'לא ידוע'}
                    ${purchase.customer_id_number ? `<br><small>${purchase.customer_id_number}</small>` : ''}
                </td>
                <td>${purchase.grave_location || purchase.grave_number || 'לא הוגדר'}</td>
                <td>${purchase.amount ? '₪' + formatNumber(purchase.amount) : '-'}</td>
                <td>
                    <span class="status-badge" style="background: ${statusInfo.color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                        ${statusInfo.name}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="viewPurchase(${purchase.id})" title="צפייה">
                            <svg class="icon"><use xlink:href="#icon-view"></use></svg>
                            👁️
                        </button>
                        <button class="btn-icon" onclick="editPurchase(${purchase.id})" title="עריכה">
                            <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            ✏️
                        </button>
                        <button class="btn-icon btn-danger" onclick="deletePurchase(${purchase.id})" title="מחיקה">
                            <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            🗑️
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// קבלת מידע על סטטוס רכישה
function getPurchaseStatusInfo(status) {
    const statuses = {
        1: { name: 'טיוטה', color: '#6b7280' },
        2: { name: 'אושר', color: '#3b82f6' },
        3: { name: 'שולם', color: '#10b981' },
        4: { name: 'בוטל', color: '#dc2626' }
    };
    return statuses[status] || { name: 'לא ידוע', color: '#6b7280' };
}

// עדכון עמודים
function updatePurchasesPagination(pagination) {
    if (!pagination) return;
    
    console.log('Pagination:', pagination);
    
    // כאן אפשר להוסיף UI לעמודים
    const paginationContainer = document.getElementById('paginationContainer');
    if (paginationContainer) {
        let html = `
            <div class="pagination">
                <span>עמוד ${pagination.page} מתוך ${pagination.pages}</span>
                <span>סה"כ: ${pagination.total} רכישות</span>
        `;
        
        if (pagination.page > 1) {
            html += `<button onclick="loadAllPurchases(${pagination.page - 1})">הקודם</button>`;
        }
        
        if (pagination.page < pagination.pages) {
            html += `<button onclick="loadAllPurchases(${pagination.page + 1})">הבא</button>`;
        }
        
        html += `</div>`;
        paginationContainer.innerHTML = html;
    }
}

// עדכון סטטיסטיקות
async function updatePurchaseStats() {
    try {
        // נסה לטעון סטטיסטיקות
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats`);
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                console.log('Purchase stats:', data.data);
                // כאן תוסיף הצגת סטטיסטיקות ב-UI
            }
        }
    } catch (error) {
        console.log('Could not load stats:', error);
    }
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// פורמט מספר
function formatNumber(num) {
    return new Intl.NumberFormat('he-IL').format(num);
}

// הצגת שגיאה
function showError(message) {
    const tableBody = document.getElementById('tableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger">
                    ${message}
                </td>
            </tr>
        `;
    } else {
        alert(message);
    }
}

// הצגת הודעת הצלחה
// הצגת הודעת הצלחה
function showSuccess(message) {
    // השתמש בפונקציה אחרת אם קיימת
    if (typeof window.showToast === 'function') {
        window.showToast('success', message);
    } else if (typeof window.displaySuccess === 'function') {
        window.displaySuccess(message);
    } else {
        // הצג הודעה בסיסית
        alert('✅ ' + message);
        
        // או צור הודעה ב-DOM
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }
}

// פונקציה זו מוגדרת ב-purchase-form.js
// היא נשארת כאן רק כ-fallback

// צפייה ברכישה
function viewPurchase(id) {
    console.log('Viewing purchase:', id);
    // כאן תוסיף את הלוגיקה לצפייה ברכישה
    alert('צפייה ברכישה #' + id);
}

// פתיחת טופס רכישה חדשה
function openAddPurchase() {
    window.currentType = 'purchase';
    FormHandler.openForm('purchase', null, null);
}

// עריכת רכישה
function editPurchase(id) {
    window.currentType = 'purchase';
    FormHandler.openForm('purchase', null, id);
}

// מחיקת רכישה
async function deletePurchase(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק רכישה זו?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('הרכישה נמחקה בהצלחה');
            loadAllPurchases(); // רענן את הרשימה
        } else {
            showError(result.error || 'שגיאה במחיקת הרכישה');
        }
    } catch (error) {
        console.error('Error deleting purchase:', error);
        showError('שגיאה במחיקה');
    }
}

// עריכת רכישה
function editPurchase2(id) {
    console.log('Editing purchase:', id);
    
    // אם יש פונקציה גלובלית לפתיחת מודל
    if (typeof window.openModal === 'function') {
        window.openModal('purchase', null, id);
    } else {
        alert('עריכת רכישה #' + id);
    }
}

// מחיקת רכישה
async function deletePurchase2(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק רכישה זו?')) {
        return;
    }
    
    try {
        // נסה קודם עם purchases-api.php
        let response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        // אם לא קיים, נסה עם cemetery-hierarchy.php
        if (!response.ok && response.status === 404) {
            response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=delete&type=purchase&id=${id}`, {
                method: 'DELETE'
            });
        }
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('הרכישה נמחקה בהצלחה');
            loadAllPurchases(currentPurchasePage);
        } else {
            throw new Error(data.error || 'Failed to delete purchase');
        }
    } catch (error) {
        console.error('Error deleting purchase:', error);
        showError('שגיאה במחיקת הרכישה: ' + error.message);
    }
}

// מיון טבלה
function sortPurchases(field) {
    if (currentSort.field === field) {
        currentSort.order = currentSort.order === 'ASC' ? 'DESC' : 'ASC';
    } else {
        currentSort.field = field;
        currentSort.order = 'ASC';
    }
    
    loadAllPurchases(1);
}

// חיפוש רכישות
function searchPurchases(query) {
    console.log('Searching purchases:', query);
    // כאן תוסיף לוגיקת חיפוש
}

// אתחול בטעינת העמוד
document.addEventListener('DOMContentLoaded', function() {
    console.log('Purchases module loaded');
    
    // בדוק אם אנחנו בעמוד רכישות
    if (window.location.hash === '#purchases' || window.currentView === 'purchases') {
        loadAllPurchases();
    }
});

// הוסף event listener לשינויים ב-hash
window.addEventListener('hashchange', function() {
    if (window.location.hash === '#purchases') {
        loadAllPurchases();
    }
});

// אקספורט פונקציות למקרה שצריך גישה גלובלית
window.purchasesModule = {
    loadAllPurchases,
    displayPurchasesTable,
    searchPurchases,
    sortPurchases,
    openAddPurchase,
    editPurchase,
    deletePurchase,
    viewPurchase
};