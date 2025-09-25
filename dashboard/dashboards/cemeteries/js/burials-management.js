// burials-management.js - ניהול קבורות

// משתנים גלובליים
let allBurials = [];
let currentBurialPage = 1;
let currentBurialSort = { field: 'createDate', order: 'DESC' };

// טעינת כל הקבורות - הגדר גלובלית מיד
async function loadAllBurials(page = 1) {
    console.log('Loading all burials...');
    currentBurialPage = page;
    
    // עדכן סוג נוכחי
    window.currentType = 'burial';
    window.currentParentId = null;
    DashboardCleaner.clear({ targetLevel: 'burial' });
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=list&page=${page}&limit=50&sort=${currentBurialSort.field}&order=${currentBurialSort.order}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            allBurials = data.data || [];
            displayBurialsTable(data.data || []);
            updateBurialsPagination(data.pagination);
            updateBurialStats();
            
            // עדכן breadcrumb אם הפונקציה קיימת
            if (typeof updateBreadcrumb === 'function') {
                updateBreadcrumb({ burial: { name: 'קבורות' } });
            }
        } else {
            throw new Error(data.error || 'Failed to load burials');
        }
    } catch (error) {
        console.error('Error loading burials:', error);
        showError('שגיאה בטעינת קבורות: ' + error.message);
    }
}

// הצגת טבלת קבורות
function displayBurialsTable(burials) {
    const tableHeaders = document.getElementById('tableHeaders');
    const tableBody = document.getElementById('tableBody');
    
    if (!tableHeaders || !tableBody) {
        console.error('Table elements not found');
        return;
    }
    
    // כותרות הטבלה
    tableHeaders.innerHTML = `
        <th style="width: 50px;">מס׳</th>
        <th onclick="sortBurials('serialBurialId')" style="cursor: pointer;">
            מס׳ תיק קבורה 
            <span class="sort-icon">⇅</span>
        </th>
        <th onclick="sortBurials('customerLastName')" style="cursor: pointer;">
            נפטר/ת
            <span class="sort-icon">⇅</span>
        </th>
        <th>ת.ז.</th>
        <th onclick="sortBurials('dateDeath')" style="cursor: pointer;">
            תאריך פטירה
            <span class="sort-icon">⇅</span>
        </th>
        <th onclick="sortBurials('dateBurial')" style="cursor: pointer;">
            תאריך קבורה
            <span class="sort-icon">⇅</span>
        </th>
        <th>שעת קבורה</th>
        <th>מיקום קבר</th>
        <th>סטטוס</th>
        <th>ביטוח לאומי</th>
        <th>פעולות</th>
    `;
    
    // בניית שורות הטבלה
    if (!burials || burials.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="11" style="text-align: center; padding: 40px; color: #999;">
                    <div style="font-size: 18px;">אין קבורות רשומות</div>
                    <div style="margin-top: 10px;">לחץ על כפתור "הוספת קבורה" להתחלה</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tableBody.innerHTML = burials.map((burial, index) => {
        const customerName = `${burial.customerLastName || ''} ${burial.customerFirstName || ''}`.trim() || 'לא מוגדר';
        const deathDate = formatDate(burial.dateDeath);
        const burialDate = formatDate(burial.dateBurial);
        const burialTime = burial.timeBurial ? burial.timeBurial.substring(0, 5) : '';
        const location = burial.fullLocation || burial.graveName || 'לא מוגדר';
        const status = getBurialStatusBadge(burial.burialStatus);
        const nationalInsurance = burial.nationalInsuranceBurial === 'כן' ? 
            '<span style="color: green;">✓</span>' : 
            '<span style="color: #ccc;">✗</span>';
        
        return `
            <tr ondblclick="viewBurial('${burial.unicId}')" style="cursor: pointer;">
                <td>${(currentBurialPage - 1) * 50 + index + 1}</td>
                <td style="font-weight: bold;">${burial.serialBurialId || '-'}</td>
                <td>
                    <div style="font-weight: bold;">${customerName}</div>
                    ${burial.customerPhone ? `<small style="color: #666;">טל: ${burial.customerPhone}</small>` : ''}
                </td>
                <td>${burial.customerNumId || '-'}</td>
                <td>${deathDate}</td>
                <td style="font-weight: bold;">${burialDate}</td>
                <td>${burialTime}</td>
                <td>
                    <small style="color: #666;">${location}</small>
                </td>
                <td>${status}</td>
                <td style="text-align: center;">${nationalInsurance}</td>
                <td>
                    <div style="display: flex; gap: 5px;">
                        <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); viewBurial('${burial.unicId}')">
                            צפייה
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); editBurial('${burial.unicId}')">
                            עריכה
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteBurial('${burial.unicId}')">
                            מחיקה
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// פונקציית עזר לסטטוס קבורה
function getBurialStatusBadge(status) {
    const statuses = {
        1: { text: 'ברישום', color: '#ffc107' },
        2: { text: 'אושרה', color: '#17a2b8' },
        3: { text: 'בוצעה', color: '#28a745' },
        4: { text: 'בוטלה', color: '#dc3545' }
    };
    
    const statusInfo = statuses[status] || statuses[1];
    return `<span style="
        background: ${statusInfo.color}; 
        color: white; 
        padding: 3px 8px; 
        border-radius: 4px; 
        font-size: 12px;
        display: inline-block;
    ">${statusInfo.text}</span>`;
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// עדכון עימוד
function updateBurialsPagination(pagination) {
    if (!pagination) return;
    
    const paginationContainer = document.getElementById('paginationContainer');
    if (paginationContainer) {
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <span>עמוד ${pagination.page} מתוך ${pagination.pages}</span>
                <span>|</span>
                <span>סה"כ: ${pagination.total} קבורות</span>
        `;
        
        if (pagination.page > 1) {
            html += `<button class="btn btn-sm btn-secondary" onclick="loadAllBurials(${pagination.page - 1})">הקודם</button>`;
        }
        
        if (pagination.page < pagination.pages) {
            html += `<button class="btn btn-sm btn-secondary" onclick="loadAllBurials(${pagination.page + 1})">הבא</button>`;
        }
        
        html += `</div>`;
        paginationContainer.innerHTML = html;
    }
}

// עדכון סטטיסטיקות
async function updateBurialStats() {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=stats`);
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                console.log('Burial stats:', data.data);
                
                // הצג סטטיסטיקות אם יש אלמנט מתאים
                const statsContainer = document.getElementById('burialStats');
                if (statsContainer && data.data.by_status) {
                    statsContainer.innerHTML = `
                        <div class="stats-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold;">${data.data.by_status.total || 0}</div>
                                <div style="color: #6b7280;">סה"כ קבורות</div>
                            </div>
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #e8f5e9; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: #4caf50;">${data.data.by_status.completed || 0}</div>
                                <div style="color: #6b7280;">בוצעו</div>
                            </div>
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #fff3e0; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: #ff9800;">${data.data.by_status.pending || 0}</div>
                                <div style="color: #6b7280;">ממתינות</div>
                            </div>
                            <div class="stat-card" style="flex: 1; padding: 15px; background: #e3f2fd; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: #2196f3;">${data.data.this_month || 0}</div>
                                <div style="color: #6b7280;">החודש</div>
                            </div>
                        </div>
                    `;
                }
            }
        }
    } catch (error) {
        console.error('Error updating burial stats:', error);
    }
}

// צפייה בקבורה
async function viewBurial2(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const burial = data.data;
            
            // יצירת מודל צפייה
            const modal = document.createElement('div');
            modal.className = 'modal show';
            modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
            
            const customerName = `${burial.customerLastName || ''} ${burial.customerFirstName || ''}`.trim();
            const deathDate = formatDate(burial.dateDeath);
            const burialDate = formatDate(burial.dateBurial);
            const burialTime = burial.timeBurial ? burial.timeBurial.substring(0, 5) : '';
            
            modal.innerHTML = `
                <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 800px; max-height: 90vh; overflow-y: auto;">
                    <div class="modal-header" style="margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                        <h2 style="margin: 0; color: #333;">כרטיס קבורה - ${burial.serialBurialId}</h2>
                        <div style="margin-top: 10px;">
                            ${getBurialStatusBadge(burial.burialStatus)}
                        </div>
                    </div>
                    <div class="modal-body">
                        <div style="display: grid; gap: 20px;">
                            <!-- פרטי הנפטר -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <h4 style="margin-bottom: 15px; color: #495057;">פרטי הנפטר/ת</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                    <div><strong>שם:</strong> ${customerName}</div>
                                    <div><strong>ת.ז.:</strong> ${burial.customerNumId || '-'}</div>
                                    <div><strong>טלפון:</strong> ${burial.customerPhone || '-'}</div>
                                    <div><strong>כתובת:</strong> ${burial.customerAddress || '-'}</div>
                                </div>
                            </div>
                            
                            <!-- פרטי פטירה וקבורה -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <h4 style="margin-bottom: 15px; color: #495057;">פרטי פטירה וקבורה</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                    <div><strong>תאריך פטירה:</strong> ${deathDate}</div>
                                    <div><strong>שעת פטירה:</strong> ${burial.timeDeath || '-'}</div>
                                    <div><strong>מקום פטירה:</strong> ${burial.placeDeath || '-'}</div>
                                    <div><strong>פטירה בחו"ל:</strong> ${burial.deathAbroad || 'לא'}</div>
                                    <div style="border-top: 1px solid #dee2e6; padding-top: 10px; grid-column: span 2;"></div>
                                    <div><strong>תאריך קבורה:</strong> ${burialDate}</div>
                                    <div><strong>שעת קבורה:</strong> ${burialTime}</div>
                                    <div><strong>רשיון קבורה:</strong> ${burial.buriaLicense || '-'}</div>
                                    <div><strong>ביטוח לאומי:</strong> ${burial.nationalInsuranceBurial || 'לא'}</div>
                                </div>
                            </div>
                            
                            <!-- פרטי קבר -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <h4 style="margin-bottom: 15px; color: #495057;">פרטי קבר</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                    <div><strong>מיקום:</strong> ${burial.fullLocation || burial.graveName || '-'}</div>
                                    <div><strong>סטטוס קבר:</strong> ${getGraveStatusName(burial.graveStatus)}</div>
                                    ${burial.purchaseSerial ? `
                                        <div><strong>מס׳ רכישה:</strong> ${burial.purchaseSerial}</div>
                                        <div><strong>מחיר רכישה:</strong> ₪${burial.purchasePrice || 0}</div>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <!-- איש קשר -->
                            ${burial.contactId || burial.kinship ? `
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <h4 style="margin-bottom: 15px; color: #495057;">איש קשר</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                    ${burial.contactId ? `<div><strong>מזהה איש קשר:</strong> ${burial.contactId}</div>` : ''}
                                    ${burial.kinship ? `<div><strong>קרבה:</strong> ${burial.kinship}</div>` : ''}
                                </div>
                            </div>
                            ` : ''}
                            
                            <!-- הערות -->
                            ${burial.comment ? `
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px;">
                                <h4 style="margin-bottom: 15px; color: #856404;">הערות</h4>
                                <div>${burial.comment}</div>
                            </div>
                            ` : ''}
                            
                            <!-- תאריכי מערכת -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <h4 style="margin-bottom: 15px; color: #495057;">מידע מערכת</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 14px;">
                                    <div><strong>נוצר:</strong> ${formatDate(burial.createDate)}</div>
                                    <div><strong>עודכן:</strong> ${formatDate(burial.updateDate)}</div>
                                    ${burial.reportingBL ? `<div><strong>דווח לביטוח לאומי:</strong> ${formatDate(burial.reportingBL)}</div>` : ''}
                                    ${burial.cancelDate ? `<div style="color: red;"><strong>בוטל:</strong> ${formatDate(burial.cancelDate)}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button class="btn btn-primary" onclick="printBurial('${burial.unicId}')">
                            הדפסה
                        </button>
                        <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editBurial('${burial.unicId}')">
                            ערוך
                        </button>
                        <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">סגור</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי הקבורה');
    }
}
async function viewBurial(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            showBurialDetails(data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי הקבורה');
    }
}

// הצגת פרטי רכישה
function showBurialDetails(burial) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    const customerName = `${burial.customerLastName || ''} ${burial.customerFirstName || ''}`.trim();
    const deathDate = formatDate(burial.dateDeath);
    const burialDate = formatDate(burial.dateBurial);
    const burialTime = burial.timeBurial ? burial.timeBurial.substring(0, 5) : '';
            
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                <h2 style="margin: 0; color: #333;">כרטיס קבורה - ${burial.serialBurialId}</h2>
                <div style="margin-top: 10px;">
                    ${getBurialStatusBadge(burial.burialStatus)}
                </div>
            </div>
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <!-- פרטי הנפטר -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #495057;">פרטי הנפטר/ת</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>שם:</strong> ${customerName}</div>
                            <div><strong>ת.ז.:</strong> ${burial.customerNumId || '-'}</div>
                            <div><strong>טלפון:</strong> ${burial.customerPhone || '-'}</div>
                            <div><strong>כתובת:</strong> ${burial.customerAddress || '-'}</div>
                        </div>
                    </div>
                    
                    <!-- פרטי פטירה וקבורה -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #495057;">פרטי פטירה וקבורה</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>תאריך פטירה:</strong> ${deathDate}</div>
                            <div><strong>שעת פטירה:</strong> ${burial.timeDeath || '-'}</div>
                            <div><strong>מקום פטירה:</strong> ${burial.placeDeath || '-'}</div>
                            <div><strong>פטירה בחו"ל:</strong> ${burial.deathAbroad || 'לא'}</div>
                            <div style="border-top: 1px solid #dee2e6; padding-top: 10px; grid-column: span 2;"></div>
                            <div><strong>תאריך קבורה:</strong> ${burialDate}</div>
                            <div><strong>שעת קבורה:</strong> ${burialTime}</div>
                            <div><strong>רשיון קבורה:</strong> ${burial.buriaLicense || '-'}</div>
                            <div><strong>ביטוח לאומי:</strong> ${burial.nationalInsuranceBurial || 'לא'}</div>
                        </div>
                    </div>
                    
                    <!-- פרטי קבר -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #495057;">פרטי קבר</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div><strong>מיקום:</strong> ${burial.fullLocation || burial.graveName || '-'}</div>
                            <div><strong>סטטוס קבר:</strong> ${getGraveStatusName(burial.graveStatus)}</div>
                            ${burial.purchaseSerial ? `
                                <div><strong>מס׳ רכישה:</strong> ${burial.purchaseSerial}</div>
                                <div><strong>מחיר רכישה:</strong> ₪${burial.purchasePrice || 0}</div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- איש קשר -->
                    ${burial.contactId || burial.kinship ? `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #495057;">איש קשר</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            ${burial.contactId ? `<div><strong>מזהה איש קשר:</strong> ${burial.contactId}</div>` : ''}
                            ${burial.kinship ? `<div><strong>קרבה:</strong> ${burial.kinship}</div>` : ''}
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- הערות -->
                    ${burial.comment ? `
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #856404;">הערות</h4>
                        <div>${burial.comment}</div>
                    </div>
                    ` : ''}
                    
                    <!-- תאריכי מערכת -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #495057;">מידע מערכת</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 14px;">
                            <div><strong>נוצר:</strong> ${formatDate(burial.createDate)}</div>
                            <div><strong>עודכן:</strong> ${formatDate(burial.updateDate)}</div>
                            ${burial.reportingBL ? `<div><strong>דווח לביטוח לאומי:</strong> ${formatDate(burial.reportingBL)}</div>` : ''}
                            ${burial.cancelDate ? `<div style="color: red;"><strong>בוטל:</strong> ${formatDate(burial.cancelDate)}</div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-primary" onclick="printBurial('${burial.unicId}')">
                    הדפסה
                </button>
                <button class="btn btn-warning" onclick="this.closest('.modal').remove(); editBurial('${burial.unicId}')">
                    ערוך
                </button>
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">סגור</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// פונקציית עזר לסטטוס קבר
function getGraveStatusName(status) {
    const statuses = {
        1: 'פנוי',
        2: 'שמור',
        3: 'תפוס',
        4: 'שמור זמני'
    };
    return statuses[status] || 'לא מוגדר';
}

// הדפסת קבורה
function printBurial(id) {
    window.open(`/dashboard/dashboards/cemeteries/print/burial.php?id=${id}`, '_blank');
}



function showError(message) {
 console.error('Error:', message);
 const alertDiv = document.createElement('div');
 alertDiv.className = 'alert alert-danger';
 alertDiv.textContent = message;
 alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;';
 document.body.appendChild(alertDiv);
 setTimeout(() => alertDiv.remove(), 5000);
}

// פונקציות עזר להודעות
function showSuccess(message) {
 console.log('Success:', message);
 const alertDiv = document.createElement('div');
 alertDiv.className = 'alert alert-success';
 alertDiv.textContent = message;
 alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;';
 document.body.appendChild(alertDiv);
 setTimeout(() => alertDiv.remove(), 3000);
}

// פתיחת טופס קבורה חדשה
function openAddBurial() {
    window.currentType = 'burial';
    FormHandler.openForm('burial', null, null);
}

// עריכת קבורה
async function editBurial(id) {
    window.currentType = 'burial';
    window.currentParentId = null;
    
    FormHandler.openForm('burial', null, id);
}

// מחיקת קבורה
async function deleteBurial(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק קבורה זו?\n\nפעולה זו תשחרר את הקבר ותעדכן את סטטוס הלקוח.')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('הקבורה נמחקה בהצלחה');
            loadAllBurials(currentBurialPage);
        } else {
            showError(result.error || 'שגיאה במחיקת הקבורה');
        }
    } catch (error) {
        console.error('Error deleting burial:', error);
        showError('שגיאה במחיקה');
    }
}

// מיון טבלה
function sortBurials(field) {
    if (currentBurialSort.field === field) {
        currentBurialSort.order = currentBurialSort.order === 'ASC' ? 'DESC' : 'ASC';
    } else {
        currentBurialSort.field = field;
        currentBurialSort.order = 'ASC';
    }
    
    loadAllBurials(1);
}

// חיפוש קבורות
async function searchBurials(query) {
    if (!query || query.length < 2) {
        loadAllBurials(1);
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=list&search=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            displayBurialsTable(data.data || []);
        }
    } catch (error) {
        console.error('Error searching burials:', error);
        showError('שגיאה בחיפוש');
    }
}

// אתחול בטעינת העמוד
document.addEventListener('DOMContentLoaded', function() {
    console.log('Burials module loaded and ready');
    
    // בדוק אם אנחנו בעמוד קבורות
    if (window.location.hash === '#burials' || window.currentView === 'burials') {
        loadAllBurials();
    }
});

// הוסף event listener לשינויים ב-hash
window.addEventListener('hashchange', function() {
    if (window.location.hash === '#burials') {
        loadAllBurials();
    }
});

// אקספורט פונקציות למקרה שצריך גישה גלובלית
window.burialsModule = {
    loadAllBurials,
    displayBurialsTable,
    searchBurials,
    sortBurials,
    openAddBurial,
    editBurial,
    deleteBurial,
    viewBurial
};

// הגדר פונקציה גלובלית
window.loadAllBurials = loadAllBurials;
