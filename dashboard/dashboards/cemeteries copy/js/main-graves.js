// dashboards/cemeteries/js/main-graves.js
// ניהול קברים

// טעינת כל הקברים
async function loadAllGraves() {
    console.log('Loading all graves...');

    clearItemCard(); // נקה את הכרטיס כשעוברים לתצוגה כללית
    
    // נקה את כל הסידבר
    clearAllSidebarSelections();

    // סמן שאנחנו ברמת גושים
    const gravesHeader = document.querySelectorAll('.hierarchy-header')[4];
    if (gravesHeader) {
        gravesHeader.classList.add('active');
    }
    
    window.currentType = 'grave';
    window.currentParentId = null;
    window.selectedItems = {};
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave`);
        const data = await response.json();
        
        if (data.success) {
            displayGravesInMainContent(data.data);
            updateSidebarCount('gravesCount', data.data.length);
            updateAddButtonText();
        }
    } catch (error) {
        console.error('Error loading graves:', error);
        showError('שגיאה בטעינת קברים');
    }
}

// טעינת קברים לאחוזת קבר ספציפית
async function loadGravesForAreaGrave(areaGraveId) {
    console.log('Loading graves for area grave:', areaGraveId);
    try {
        // תחילה הצג את כרטיס אחוזת הקבר
        const cardHtml = await createAreaGraveCard(areaGraveId);
        const mainContent = document.querySelector('.main-content');
        
        const cardContainer = document.getElementById('itemCard') || document.createElement('div');
        cardContainer.id = 'itemCard';
        cardContainer.innerHTML = cardHtml;
        
        const tableWrapper = document.querySelector('.table-wrapper');
        if (tableWrapper && cardHtml) {
            mainContent.insertBefore(cardContainer, tableWrapper);
        }

        // הכנס את התוכן
        cardContainer.innerHTML = cardHtml;
        
        // אז טען את הקברים
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
        const data = await response.json();
        
        if (data.success) {
            displayGravesInMainContent(data.data);
            updateAddButtonText();
        }
    } catch (error) {
        console.error('Error loading graves:', error);
        showError('שגיאה בטעינת קברים');
    }
}

// הצגת קברים בתוכן הראשי
function displayGravesInMainContent(graves, areaGraveName = null) {
    const tbody = document.getElementById('tableBody');
    const thead = document.getElementById('tableHeaders'); // וודא שזה קיים
    
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (graves.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🪦</div>
                        <div>אין קברים ${areaGraveName ? `באחוזת קבר ${areaGraveName}` : 'במערכת'}</div>
                        ${window.selectedItems.areaGrave ? `
                            <button class="btn btn-primary mt-3" onclick="FormHandler.openForm('grave', ${window.selectedItems.areaGrave.id}, null)">
                                הוסף קבר חדש
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // הצג סטטיסטיקות
    const stats = calculateGraveStats(graves);
    const statsRow = document.createElement('tr');
    statsRow.innerHTML = `
        <td colspan="7" style="background: #f8f9fa; padding: 15px;">
            <strong>${areaGraveName ? `אחוזת קבר: ${areaGraveName}` : 'כל הקברים'}</strong> | 
            <span class="badge badge-success">פנויים: ${stats.available}</span>
            <span class="badge badge-warning">נרכשו: ${stats.purchased}</span>
            <span class="badge badge-danger">תפוסים: ${stats.occupied}</span>
            <span class="badge badge-info">שמורים: ${stats.reserved}</span>
            <span style="margin-right: 20px;"><strong>סה"כ: ${graves.length}</strong></span>
        </td>
    `;
    tbody.appendChild(statsRow);
    
    // עדכון כותרות הטבלה לקברים - וודא שזה קיים
    if (thead) {
        thead.innerHTML = `
            <th>מזהה</th>
            <th>מספר קבר</th>
            <th>סוג</th>
            <th>סטטוס</th>
            <th>עלות בנייה</th>
            <th>נוצר בתאריך</th>
            <th>פעולות</th>
        `;
    }
    
    // הצג את הקברים
    graves.forEach(grave => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.ondblclick = () => openGrave(grave.id, grave.grave_number);
        tr.onclick = () => selectTableRow(tr);
        
        const statusBadge = getGraveStatusBadge(grave.grave_status);
        const plotTypeName = getPlotTypeName(grave.plot_type);
        
        tr.innerHTML = `
            <td>${grave.id}</td>
            <td>
                <strong>${grave.grave_number}</strong>
                ${grave.is_small_grave ? '<br><small class="text-muted">קבר קטן</small>' : ''}
            </td>
            <td>${plotTypeName}</td>
            <td>${statusBadge}</td>
            <td>${grave.construction_cost ? `₪${Number(grave.construction_cost).toLocaleString()}` : '-'}</td>
            <td>${formatDate(grave.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editGrave(${grave.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteGrave(${grave.id})" 
                        ${grave.grave_status == 3 ? 'disabled' : ''}>
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); viewGraveDetails(${grave.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-eye"></use></svg>
                    פרטים
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // עדכן breadcrumb
    updateBreadcrumb(window.selectedItems);
}

// חישוב סטטיסטיקות קברים
function calculateGraveStats(graves) {
    const stats = {
        available: 0,
        purchased: 0,
        occupied: 0,
        reserved: 0
    };
    
    graves.forEach(grave => {
        switch(grave.grave_status) {
            case '1': case 1: stats.available++; break;
            case '2': case 2: stats.purchased++; break;
            case '3': case 3: stats.occupied++; break;
            case '4': case 4: stats.reserved++; break;
        }
    });
    
    return stats;
}

// קבלת תג סטטוס לקבר
function getGraveStatusBadge(status) {
    const statuses = {
        1: '<span class="badge badge-success">פנוי</span>',
        2: '<span class="badge badge-warning">נרכש</span>',
        3: '<span class="badge badge-danger">תפוס</span>',
        4: '<span class="badge badge-info">שמור</span>'
    };
    return statuses[status] || '<span class="badge badge-secondary">לא ידוע</span>';
}

// קבלת שם סוג החלקה
function getPlotTypeName(type) {
    const types = {
        1: 'פטור',
        2: 'חריג',
        3: 'סגור'
    };
    return types[type] || 'רגיל';
}

// פתיחת קבר ספציפי
function openGrave(graveId, graveNumber) {
    console.log('Opening grave:', graveId, graveNumber);
    
    // שמור את הבחירה
    window.selectedItems.grave = { id: graveId, number: graveNumber };
    
    // כאן אפשר להציג מידע מפורט על הקבר, קבורות, רכישות וכו'
    viewGraveDetails(graveId);
}

// הצגת פרטי קבר
async function viewGraveDetails(graveId) {
    // TODO: טען את כל המידע על הקבר כולל רכישה וקבורה אם יש
    console.log('Viewing grave details:', graveId);
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=grave&id=${graveId}`);
        const data = await response.json();
        
        if (data.success) {
            showGraveDetailsModal(data.data);
        }
    } catch (error) {
        console.error('Error loading grave details:', error);
        showError('שגיאה בטעינת פרטי הקבר');
    }
}

// הצגת מודל עם פרטי קבר
function showGraveDetailsModal(grave) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 30px rgba(0,0,0,0.3);
        z-index: 10000;
        min-width: 500px;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
    `;
    
    const statusBadge = getGraveStatusBadge(grave.grave_status);
    const plotTypeName = getPlotTypeName(grave.plot_type);
    
    modal.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>פרטי קבר מספר ${grave.grave_number}</h3>
            <button onclick="this.closest('div[style*=fixed]').remove()" 
                    style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label style="font-weight: bold;">מספר קבר:</label>
                <p>${grave.grave_number}</p>
            </div>
            <div>
                <label style="font-weight: bold;">סטטוס:</label>
                <p>${statusBadge}</p>
            </div>
            <div>
                <label style="font-weight: bold;">סוג חלקה:</label>
                <p>${plotTypeName}</p>
            </div>
            <div>
                <label style="font-weight: bold;">קבר קטן:</label>
                <p>${grave.is_small_grave ? 'כן' : 'לא'}</p>
            </div>
            <div>
                <label style="font-weight: bold;">עלות בנייה:</label>
                <p>${grave.construction_cost ? `₪${Number(grave.construction_cost).toLocaleString()}` : 'לא מוגדר'}</p>
            </div>
            <div>
                <label style="font-weight: bold;">מיקום:</label>
                <p>${grave.grave_location || 'לא מוגדר'}</p>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="font-weight: bold;">הערות:</label>
                <p>${grave.comments || 'אין הערות'}</p>
            </div>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4>פעולות</h4>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                ${grave.grave_status == 1 ? `
                    <button onclick="markGraveAsPurchased(${grave.id})" 
                            style="padding: 8px 20px; background: #f97316; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        סמן כנרכש
                    </button>
                ` : ''}
                ${grave.grave_status == 2 ? `
                    <button onclick="markGraveAsOccupied(${grave.id})" 
                            style="padding: 8px 20px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        סמן כתפוס
                    </button>
                ` : ''}
                <button onclick="editGrave(${grave.id}); this.closest('div[style*=fixed]').remove();" 
                        style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ערוך פרטים
                </button>
            </div>
        </div>
        
        <div style="margin-top: 20px; text-align: left;">
            <button onclick="this.closest('div[style*=fixed]').remove()" 
                    style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                סגור
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// הוספת קבר חדש
function openAddGrave2() {
    if (!window.selectedItems.areaGrave) {
        showWarning('יש לבחור אחוזת קבר תחילה');
        return;
    }
    
    window.currentType = 'grave';
    window.currentParentId = window.selectedItems.areaGrave.id;
    
    if (typeof window.openModal === 'function') {
        window.openModal('grave', window.selectedItems.areaGrave.id, null);
    } else {
        createSimpleGraveForm();
    }
}
// הוספת קבר חדש
function openAddGrave() {
    if (!window.selectedItems.areaGrave) {
        showWarning('יש לבחור אחוזת קבר תחילה');
        return;
    }
    
    window.currentType = 'grave';
    window.currentParentId = window.selectedItems.areaGrave.id;
    
    // השתמש ב-FormHandler במקום בפונקציה שלא קיימת
    FormHandler.openForm('grave', window.selectedItems.areaGrave.id, null);
}

// עריכת קבר
function editGrave(id) {
    window.currentType = 'grave';
    if (typeof window.openModal === 'function') {
        window.openModal('grave', window.selectedItems.areaGrave?.id, id);
    }
}

// מחיקת קבר
async function deleteGrave(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק קבר זה?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=grave&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('הקבר נמחק בהצלחה');
            
            // רענן את התצוגה
            if (window.selectedItems.areaGrave) {
                loadGravesForAreaGrave(window.selectedItems.areaGrave.id);
            } else {
                loadAllGraves();
            }
        } else {
            showError(data.error || 'שגיאה במחיקת הקבר');
        }
    } catch (error) {
        console.error('Error deleting grave:', error);
        showError('שגיאה במחיקת הקבר');
    }
}

// פונקציות לשינוי סטטוס קבר
window.markGraveAsPurchased = async function(graveId) {
    // TODO: חבר למערכת רכישות
    alert('פונקציה זו תחובר למערכת הרכישות');
}

window.markGraveAsOccupied = async function(graveId) {
    // TODO: חבר למערכת קבורות
    alert('פונקציה זו תחובר למערכת הקבורות');
}