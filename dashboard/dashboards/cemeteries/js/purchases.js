// dashboards/cemeteries/js/purchases.js
// מודול ניהול רכישות קברים

// משתנים גלובליים לרכישות
window.currentPurchaseId = null;
window.purchasesCache = {};

// טעינת כל הרכישות
async function loadAllPurchases() {
    console.log('Loading all purchases...');
    
    // נקה את הסידבר
    clearAllSidebarSelections();
    
    // עדכן סוג נוכחי
    window.currentType = 'purchase';
    window.currentParentId = null;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=purchase`);
        const data = await response.json();
        
        if (data.success) {
            displayPurchasesInMainContent(data.data);
            updateBreadcrumb('רכישות');
        } else {
            showError(data.error || 'שגיאה בטעינת רכישות');
        }
    } catch (error) {
        console.error('Error loading purchases:', error);
        showError('שגיאה בטעינת רכישות');
    }
}

// הצגת רכישות בטבלה הראשית
function displayPurchasesInMainContent(purchases) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (purchases.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
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
    
    // הצג רכישות בטבלה
    purchases.forEach(purchase => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.ondblclick = () => viewPurchaseDetails(purchase.id);
        tr.onclick = () => selectTableRow(tr);
        
        tr.innerHTML = `
            <td>${purchase.id}</td>
            <td>${purchase.purchase_number || '-'}</td>
            <td>
                <strong>${purchase.customer_name || 'לקוח #' + purchase.customer_id}</strong>
                ${purchase.customer_id_number ? `<br><small class="text-muted">ת.ז: ${purchase.customer_id_number}</small>` : ''}
            </td>
            <td>
                ${purchase.grave_location || 'קבר #' + purchase.grave_id}
                ${purchase.grave_number ? `<br><small class="text-muted">מספר: ${purchase.grave_number}</small>` : ''}
            </td>
            <td>${getPurchaseStatusBadge(purchase.purchase_status)}</td>
            <td>${purchase.price ? formatMoney(purchase.price) : '-'}</td>
            <td>${formatDate(purchase.opening_date || purchase.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); viewPurchaseDetails(${purchase.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-view"></use></svg>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editPurchase(${purchase.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deletePurchase(${purchase.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
                ${purchase.purchase_status === 1 ? `
                    <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); approvePurchase(${purchase.id})">
                        אשר
                    </button>
                ` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// קבלת תג סטטוס רכישה
function getPurchaseStatusBadge(status) {
    const statuses = {
        1: '<span class="badge badge-secondary">טיוטה</span>',
        2: '<span class="badge badge-info">אושר</span>',
        3: '<span class="badge badge-success">שולם</span>',
        4: '<span class="badge badge-danger">בוטל</span>'
    };
    return statuses[status] || '<span class="badge badge-secondary">לא ידוע</span>';
}

// פתיחת טופס הוספת רכישה
function openAddPurchase() {
    window.currentType = 'purchase';
    window.currentPurchaseId = null;
    
    if (typeof window.openModal === 'function') {
        window.openModal('purchase', null, null);
    } else {
        createPurchaseForm();
    }
}

// עריכת רכישה
async function editPurchase(id) {
    window.currentType = 'purchase';
    window.currentPurchaseId = id;
    
    try {
        // טען את פרטי הרכישה
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=purchase&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            if (typeof window.openModal === 'function') {
                window.openModal('purchase', null, id, data.data);
            } else {
                createPurchaseForm(data.data);
            }
        }
    } catch (error) {
        console.error('Error loading purchase:', error);
        showError('שגיאה בטעינת פרטי הרכישה');
    }
}

// יצירת טופס רכישה
async function createPurchaseForm(purchaseData = null) {
    // טען רשימת לקוחות וקברים פנויים
    const [customersResponse, gravesResponse] = await Promise.all([
        fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=customer`),
        fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&available=true`)
    ]);
    
    const customers = await customersResponse.json();
    const graves = await gravesResponse.json();
    
    const form = document.createElement('div');
    form.id = 'purchaseFormModal';
    form.className = 'modal-overlay';
    form.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    form.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; width: 600px; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 20px;">${purchaseData ? 'עריכת רכישה' : 'רכישה חדשה'}</h3>
            
            <form id="purchaseForm" onsubmit="submitPurchaseForm(event)">
                ${purchaseData ? `<input type="hidden" name="id" value="${purchaseData.id}">` : ''}
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">מספר רכישה</label>
                    <input type="text" name="purchase_number" class="form-control" 
                           value="${purchaseData?.purchase_number || ''}"
                           placeholder="יוצר אוטומטית אם ריק"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">לקוח *</label>
                    <select name="customer_id" class="form-control" required
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">בחר לקוח</option>
                        ${customers.data?.map(customer => `
                            <option value="${customer.id}" ${purchaseData?.customer_id == customer.id ? 'selected' : ''}>
                                ${customer.first_name} ${customer.last_name} - ${customer.id_number || ''}
                            </option>
                        `).join('')}
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">קבר *</label>
                    <select name="grave_id" class="form-control" required
                            onchange="updateGraveInfo(this.value)"
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">בחר קבר</option>
                        ${graves.data?.map(grave => `
                            <option value="${grave.id}" 
                                    ${purchaseData?.grave_id == grave.id ? 'selected' : ''}
                                    ${grave.grave_status != 1 && !purchaseData ? 'disabled' : ''}>
                                קבר ${grave.grave_number} - ${grave.location || ''} 
                                ${grave.grave_status != 1 ? '(תפוס)' : ''}
                            </option>
                        `).join('')}
                    </select>
                    <small id="graveInfo" class="text-muted"></small>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">סטטוס רכישה</label>
                    <select name="purchase_status" class="form-control"
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="1" ${purchaseData?.purchase_status == 1 ? 'selected' : ''}>טיוטה</option>
                        <option value="2" ${purchaseData?.purchase_status == 2 ? 'selected' : ''}>אושר</option>
                        <option value="3" ${purchaseData?.purchase_status == 3 ? 'selected' : ''}>שולם</option>
                        <option value="4" ${purchaseData?.purchase_status == 4 ? 'selected' : ''}>בוטל</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">סטטוס רוכש</label>
                    <select name="buyer_status" class="form-control"
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">בחר סטטוס</option>
                        <option value="1" ${purchaseData?.buyer_status == 1 ? 'selected' : ''}>רוכש לעצמו</option>
                        <option value="2" ${purchaseData?.buyer_status == 2 ? 'selected' : ''}>רוכש לאחר</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">מחיר</label>
                    <input type="number" name="price" class="form-control" 
                           value="${purchaseData?.price || ''}"
                           step="0.01"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">מספר תשלומים</label>
                    <input type="number" name="num_payments" class="form-control" 
                           value="${purchaseData?.num_payments || 1}"
                           min="1"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">תאריך סיום תשלומים</label>
                    <input type="date" name="payment_end_date" class="form-control" 
                           value="${purchaseData?.payment_end_date || ''}"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">תאריך פתיחה</label>
                    <input type="date" name="opening_date" class="form-control" 
                           value="${purchaseData?.opening_date || new Date().toISOString().split('T')[0]}"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">מספר שטר</label>
                    <input type="text" name="deed_number" class="form-control" 
                           value="${purchaseData?.deed_number || ''}"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="has_certificate" value="1" 
                               ${purchaseData?.has_certificate ? 'checked' : ''}>
                        יש תעודה
                    </label>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">קרבה</label>
                    <input type="text" name="kinship" class="form-control" 
                           value="${purchaseData?.kinship || ''}"
                           placeholder="קרבת הרוכש לנפטר (אם רלוונטי)"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">הערות</label>
                    <textarea name="comments" class="form-control" rows="3"
                              style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${purchaseData?.comments || ''}</textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closePurchaseForm()" 
                            style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        ביטול
                    </button>
                    <button type="submit" 
                            style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        ${purchaseData ? 'עדכן' : 'שמור'}
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(form);
}

// סגירת טופס רכישה
function closePurchaseForm() {
    const form = document.getElementById('purchaseFormModal');
    if (form) {
        form.remove();
    }
}

// שליחת טופס רכישה
async function submitPurchaseForm(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const isEdit = formData.get('id');
    
    const data = {
        customer_id: formData.get('customer_id'),
        grave_id: formData.get('grave_id'),
        purchase_number: formData.get('purchase_number') || null,
        purchase_status: formData.get('purchase_status') || 1,
        buyer_status: formData.get('buyer_status') || null,
        price: formData.get('price') || null,
        num_payments: formData.get('num_payments') || 1,
        payment_end_date: formData.get('payment_end_date') || null,
        opening_date: formData.get('opening_date') || null,
        has_certificate: formData.get('has_certificate') ? 1 : 0,
        deed_number: formData.get('deed_number') || null,
        kinship: formData.get('kinship') || null,
        comments: formData.get('comments') || null,
        is_active: 1
    };
    
    try {
        const url = isEdit 
            ? `${API_BASE}cemetery-hierarchy.php?action=update&type=purchase&id=${formData.get('id')}`
            : `${API_BASE}cemetery-hierarchy.php?action=create&type=purchase`;
            
        const method = isEdit ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closePurchaseForm();
            showSuccess(isEdit ? 'הרכישה עודכנה בהצלחה' : 'הרכישה נוספה בהצלחה');
            
            // עדכן סטטוס קבר אם נדרש
            if (data.purchase_status >= 2 && data.grave_id) {
                await updateGraveStatus(data.grave_id, 2); // סטטוס "נרכש"
            }
            
            loadAllPurchases();
        } else {
            showError(result.error || 'שגיאה בשמירת הרכישה');
        }
    } catch (error) {
        console.error('Error saving purchase:', error);
        showError('שגיאה בשמירת הרכישה');
    }
}

// עדכון סטטוס קבר
async function updateGraveStatus(graveId, status) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=update&type=grave&id=${graveId}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ grave_status: status })
        });
        
        const result = await response.json();
        if (!result.success) {
            console.error('Failed to update grave status:', result.error);
        }
    } catch (error) {
        console.error('Error updating grave status:', error);
    }
}

// מחיקת רכישה
async function deletePurchase(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק רכישה זו?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=purchase&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('הרכישה נמחקה בהצלחה');
            loadAllPurchases();
        } else {
            showError(data.error || 'שגיאה במחיקת הרכישה');
        }
    } catch (error) {
        console.error('Error deleting purchase:', error);
        showError('שגיאה במחיקת הרכישה');
    }
}

// אישור רכישה
async function approvePurchase(id) {
    if (!confirm('האם לאשר את הרכישה?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=update&type=purchase&id=${id}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ purchase_status: 2 })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('הרכישה אושרה בהצלחה');
            
            // עדכן סטטוס קבר
            const purchaseResponse = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=purchase&id=${id}`);
            const purchaseData = await purchaseResponse.json();
            if (purchaseData.success && purchaseData.data.grave_id) {
                await updateGraveStatus(purchaseData.data.grave_id, 2);
            }
            
            loadAllPurchases();
        } else {
            showError(result.error || 'שגיאה באישור הרכישה');
        }
    } catch (error) {
        console.error('Error approving purchase:', error);
        showError('שגיאה באישור הרכישה');
    }
}

// הצגת פרטי רכישה
async function viewPurchaseDetails(id) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=purchase&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            showPurchaseDetailsModal(data.data);
        }
    } catch (error) {
        console.error('Error loading purchase details:', error);
        showError('שגיאה בטעינת פרטי הרכישה');
    }
}

// הצגת מודל פרטי רכישה
function showPurchaseDetailsModal(purchase) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; width: 700px; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 20px;">פרטי רכישה #${purchase.purchase_number || purchase.id}</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4 style="margin-bottom: 10px; color: #667eea;">פרטי הרכישה</h4>
                    <p><strong>מספר רכישה:</strong> ${purchase.purchase_number || '-'}</p>
                    <p><strong>סטטוס:</strong> ${getPurchaseStatusBadge(purchase.purchase_status)}</p>
                    <p><strong>סטטוס רוכש:</strong> ${purchase.buyer_status == 1 ? 'רוכש לעצמו' : purchase.buyer_status == 2 ? 'רוכש לאחר' : '-'}</p>
                    <p><strong>תאריך פתיחה:</strong> ${formatDate(purchase.opening_date)}</p>
                    <p><strong>תאריך יצירה:</strong> ${formatDate(purchase.created_at)}</p>
                    ${purchase.deed_number ? `<p><strong>מספר שטר:</strong> ${purchase.deed_number}</p>` : ''}
                    <p><strong>תעודה:</strong> ${purchase.has_certificate ? 'יש' : 'אין'}</p>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 10px; color: #667eea;">פרטי תשלום</h4>
                    <p><strong>מחיר:</strong> ${purchase.price ? formatMoney(purchase.price) : '-'}</p>
                    <p><strong>מספר תשלומים:</strong> ${purchase.num_payments || 1}</p>
                    ${purchase.payment_end_date ? `<p><strong>סיום תשלומים:</strong> ${formatDate(purchase.payment_end_date)}</p>` : ''}
                    ${purchase.refund_amount ? `<p><strong>החזר:</strong> ${formatMoney(purchase.refund_amount)}</p>` : ''}
                    ${purchase.refund_invoice ? `<p><strong>חשבונית החזר:</strong> ${purchase.refund_invoice}</p>` : ''}
                </div>
                
                <div>
                    <h4 style="margin-bottom: 10px; color: #667eea;">פרטי לקוח</h4>
                    <p><strong>שם:</strong> ${purchase.customer_name || 'לקוח #' + purchase.customer_id}</p>
                    ${purchase.customer_id_number ? `<p><strong>ת.ז:</strong> ${purchase.customer_id_number}</p>` : ''}
                    ${purchase.customer_phone ? `<p><strong>טלפון:</strong> ${purchase.customer_phone}</p>` : ''}
                    ${purchase.kinship ? `<p><strong>קרבה:</strong> ${purchase.kinship}</p>` : ''}
                </div>
                
                <div>
                    <h4 style="margin-bottom: 10px; color: #667eea;">פרטי קבר</h4>
                    <p><strong>מיקום:</strong> ${purchase.grave_location || 'קבר #' + purchase.grave_id}</p>
                    ${purchase.grave_number ? `<p><strong>מספר קבר:</strong> ${purchase.grave_number}</p>` : ''}
                    ${purchase.grave_status ? `<p><strong>סטטוס קבר:</strong> ${getGraveStatusBadge(purchase.grave_status)}</p>` : ''}
                </div>
            </div>
            
            ${purchase.comments ? `
                <div style="margin-top: 20px;">
                    <h4 style="margin-bottom: 10px; color: #667eea;">הערות</h4>
                    <p style="background: #f5f5f5; padding: 10px; border-radius: 4px;">${purchase.comments}</p>
                </div>
            ` : ''}
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button onclick="editPurchase(${purchase.id}); this.closest('.modal-overlay').remove();" 
                        style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ערוך
                </button>
                <button onclick="this.closest('.modal-overlay').remove()" 
                        style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    סגור
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// פונקציה לקבלת תג סטטוס קבר
function getGraveStatusBadge(status) {
    const statuses = {
        1: '<span class="badge badge-success">פנוי</span>',
        2: '<span class="badge badge-warning">נרכש</span>',
        3: '<span class="badge badge-danger">תפוס</span>',
        4: '<span class="badge badge-info">שמור</span>'
    };
    return statuses[status] || '<span class="badge badge-secondary">לא ידוע</span>';
}

// פונקציה לעיצוב כסף
function formatMoney(amount) {
    return '₪ ' + parseFloat(amount).toLocaleString('he-IL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// פונקציה לעיצוב תאריך
function formatDate(date) {
    if (!date) return '-';
    const d = new Date(date);
    return d.toLocaleDateString('he-IL');
}

// עדכון מידע על קבר נבחר
async function updateGraveInfo(graveId) {
    if (!graveId) {
        document.getElementById('graveInfo').innerHTML = '';
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=grave&id=${graveId}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const grave = data.data;
            document.getElementById('graveInfo').innerHTML = `
                מיקום: ${grave.location || '-'} | 
                סטטוס: ${getGraveStatusBadge(grave.grave_status)}
            `;
        }
    } catch (error) {
        console.error('Error loading grave info:', error);
    }
}

// פונקציה לבחירת שורה בטבלה
function selectTableRow(tr) {
    // הסר בחירה קודמת
    document.querySelectorAll('#tableBody tr').forEach(row => {
        row.classList.remove('selected');
    });
    // הוסף בחירה לשורה הנוכחית
    tr.classList.add('selected');
}

// חיפוש רכישות
async function searchPurchases(query) {
    if (!query) {
        loadAllPurchases();
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=purchase&search=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            displayPurchasesInMainContent(data.data);
        }
    } catch (error) {
        console.error('Error searching purchases:', error);
    }
}

// טעינת רכישות לקוח ספציפי
async function loadPurchasesForCustomer(customerId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=purchase&customer_id=${customerId}`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
    } catch (error) {
        console.error('Error loading customer purchases:', error);
    }
    return [];
}

// טעינת רכישה לקבר ספציפי
async function loadPurchaseForGrave(graveId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=purchase&grave_id=${graveId}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            return data.data[0]; // החזר רכישה ראשונה פעילה
        }
    } catch (error) {
        console.error('Error loading grave purchase:', error);
    }
    return null;
}