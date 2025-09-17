// purchases-management.js - ניהול רכישות

// משתנים גלובליים
let allPurchases = [];
let currentPurchasePage = 1;
let currentSort = { field: 'createDate', order: 'DESC' };

// טעינת כל הרכישות
async function loadAllPurchases(page = 1) {
    console.log('Loading all purchases...');
    currentPurchasePage = page;
    
    // עדכן סוג נוכחי
    window.currentType = 'purchase';
    window.currentParentId = null;
    DashboardCleaner.clear({ targetLevel: 'purchase' });
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=list&page=${page}&limit=50&sort=${currentSort.field}&order=${currentSort.order}`);
        
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
                updateBreadcrumb({ purchase: { name: 'רכישות' } });
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
            <th>מס׳ רכישה</th>
            <th>תאריך</th>
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
        const statusInfo = getPurchaseStatusInfo(purchase.purchaseStatus || purchase.purchase_status);
        
        // בניית מיקום הקבר המלא
        let graveLocation = '';
        if (purchase.cemeteryNameHe) {
            graveLocation = `${purchase.cemeteryNameHe}`;
            if (purchase.blockNameHe) graveLocation += ` > ${purchase.blockNameHe}`;
            if (purchase.plotNameHe) graveLocation += ` > ${purchase.plotNameHe}`;
            if (purchase.lineNameHe) graveLocation += ` > ${purchase.lineNameHe}`;
            if (purchase.areaGraveNameHe) graveLocation += ` > ${purchase.areaGraveNameHe}`;
            if (purchase.grave_number) graveLocation += ` > ${purchase.grave_number}`;
        } else {
            graveLocation = purchase.grave_location || purchase.grave_number || 'לא הוגדר';
        }
        
        return `
            <tr>
                <td>
                    <strong>${purchase.serialPurchaseId || purchase.purchase_number || purchase.unicId}</strong>
                </td>
                <td>${formatDate(purchase.dateOpening || purchase.purchase_date || purchase.createDate)}</td>
                <td>
                    <strong>${purchase.customer_name || 'לא ידוע'}</strong>
                    ${purchase.customer_id_number ? `<br><small style="color: #666;">${purchase.customer_id_number}</small>` : ''}
                </td>
                <td>
                    <small style="color: #666;">${graveLocation}</small>
                </td>
                <td>${purchase.price || purchase.amount ? '₪' + formatNumber(purchase.price || purchase.amount) : '-'}</td>
                <td>
                    <span class="status-badge" style="background: ${statusInfo.color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                        ${statusInfo.name}
                    </span>
                </td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-info" onclick="viewPurchase('${purchase.unicId}')" title="צפייה">
                            👁️
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editPurchase('${purchase.unicId}')" title="עריכה">
                            ✏️
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deletePurchase('${purchase.unicId}')" title="מחיקה">
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
        1: { name: 'פתוח', color: '#3b82f6' },
        2: { name: 'שולם', color: '#10b981' },
        3: { name: 'סגור', color: '#6b7280' },
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
            <div class="pagination" style="display: flex; align-items: center; gap: 10px; padding: 10px;">
                <span>עמוד ${pagination.page} מתוך ${pagination.pages}</span>
                <span>|</span>
                <span>סה"כ: ${pagination.total} רכישות</span>
        `;
        
        if (pagination.page > 1) {
            html += `<button class="btn btn-sm btn-secondary" onclick="loadAllPurchases(${pagination.page - 1})">הקודם</button>`;
        }
        
        if (pagination.page < pagination.pages) {
            html += `<button class="btn btn-sm btn-secondary" onclick="loadAllPurchases(${pagination.page + 1})">הבא</button>`;
        }
        
        html += `</div>`;
        paginationContainer.innerHTML = html;
    }
}

// עדכון סטטיסטיקות
async function updatePurchaseStats() {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats`);
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                console.log('Purchase stats:', data.data);
                
                // הצג סטטיסטיקות אם יש אלמנט מתאים
                const statsContainer = document.getElementById('purchaseStats');
                if (statsContainer && data.data.totals) {
                    statsContainer.innerHTML = `
                        <div class="stats-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold;">${data.data.totals.total_purchases || 0}</div>
                                <div style="color: #6b7280;">סה"כ רכישות</div>
                            </div>
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold;">${data.data.totals.total_customers || 0}</div>
                                <div style="color: #6b7280;">לקוחות</div>
                            </div>
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold;">₪${formatNumber(data.data.totals.total_revenue || 0)}</div>
                                <div style="color: #6b7280;">סה"כ הכנסות</div>
                            </div>
                        </div>
                    `;
                }
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
    if (typeof showToast === 'function') {
        showToast('error', message);
    } else {
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
}

// הצגת הודעת הצלחה
function showSuccess(message) {
    if (typeof showToast === 'function') {
        showToast('success', message);
    } else {
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }
}

// צפייה ברכישה
async function viewPurchase(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            showPurchaseDetails(data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי הרכישה');
    }
}

// הצגת פרטי רכישה
function showPurchaseDetails(purchase) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    const statusInfo = getPurchaseStatusInfo(purchase.purchaseStatus);
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">רכישה מס׳ ${purchase.serialPurchaseId || purchase.id}</h2>
            </div>
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">פרטי רכישה</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>תאריך:</strong> ${formatDate(purchase.dateOpening)}</div>
                            <div><strong>סטטוס:</strong> ${statusInfo.name}</div>
                            <div><strong>סכום:</strong> ${purchase.price ? '₪' + formatNumber(purchase.price) : '-'}</div>
                            <div><strong>תשלומים:</strong> ${purchase.numOfPayments || '1'}</div>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">פרטי לקוח</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>שם:</strong> ${purchase.firstName || ''} ${purchase.lastName || ''}</div>
                            <div><strong>ת.ז.:</strong> ${purchase.numId || '-'}</div>
                            <div><strong>טלפון:</strong> ${purchase.phone || '-'}</div>
                        </div>
                    </div>
                    
                    ${purchase.comment ? `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">הערות</h4>
                        <div>${purchase.comment}</div>
                    </div>
                    ` : ''}
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editPurchase('${purchase.unicId}')">
                    ערוך
                </button>
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">סגור</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// פתיחת טופס רכישה חדשה
function openAddPurchase() {
    window.currentType = 'purchase';
    FormHandler.openForm('purchase', null, null);
}

// עריכת רכישה
async function editPurchase2(id) {
    window.currentType = 'purchase';
    console.log('נכנסתי ל editPurchase', 'file: purchases-management.js, row: 355');
    
    FormHandler.openForm('purchase', null, id);
}

async function editPurchase(id) {
    window.currentType = 'purchase';
    window.currentParentId = null;
    
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
            loadAllPurchases(currentPurchasePage);
        } else {
            showError(result.error || 'שגיאה במחיקת הרכישה');
        }
    } catch (error) {
        console.error('Error deleting purchase:', error);
        showError('שגיאה במחיקה');
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
async function searchPurchases(query) {
    if (!query || query.length < 2) {
        loadAllPurchases(1);
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=list&search=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            displayPurchasesTable(data.data || []);
        }
    } catch (error) {
        console.error('Error searching purchases:', error);
    }
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

// הגדר פונקציה גלובלית
window.loadAllPurchases = loadAllPurchases;