// dashboards/cemeteries/js/main-area-graves.js
// ניהול אחוזות קבר

// טעינת כל אחוזות הקבר
async function loadAllAreaGraves() {
    console.log('Loading all area graves...');
    
    // נקה את כל הסידבר
    clearAllSidebarSelections();

    // סמן שאנחנו ברמת גושים
    const areaGravesHeader = document.querySelectorAll('.hierarchy-header')[3];
    if (areaGravesHeader) {
        areaGravesHeader.classList.add('active');
    }
    
    window.currentType = 'areaGrave';
    window.currentParentId = null;
    window.selectedItems = {};
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave`);
        const data = await response.json();
        
        if (data.success) {
            displayAreaGravesInMainContent(data.data);
            updateSidebarCount('areaGravesCount', data.data.length);
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
        showError('שגיאה בטעינת אחוזות קבר');
    }
}

// טעינת אחוזות קבר לשורה ספציפית
async function loadAreaGravesForRow(rowId) {
    console.log('Loading area graves for row:', rowId);
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
        const data = await response.json();
        
        if (data.success) {
            displayAreaGravesInMainContent(data.data, window.selectedItems.row?.name);
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
        showError('שגיאה בטעינת אחוזות קבר');
    }
}

// הצגת אחוזות קבר בתוכן הראשי
function displayAreaGravesInMainContent(areaGraves, rowName = null) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (areaGraves.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🏘️</div>
                        <div>אין אחוזות קבר ${rowName ? `בשורה ${rowName}` : 'במערכת'}</div>
                        ${window.selectedItems.row ? `
                            <button class="btn btn-primary mt-3" onclick="openAddAreaGrave()">
                                הוסף אחוזת קבר חדשה
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // הצג את אחוזות הקבר
    areaGraves.forEach(area => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.ondblclick = () => openAreaGrave(area.id, area.name);
        tr.onclick = () => selectTableRow(tr);
        
        tr.innerHTML = `
            <td>${area.id}</td>
            <td>
                <strong>${area.name}</strong>
                ${area.grave_type ? `<br><small class="text-muted">סוג: ${getGraveTypeName(area.grave_type)}</small>` : ''}
            </td>
            <td>${area.coordinates || '-'}</td>
            <td><span class="badge badge-success">פעיל</span></td>
            <td>${formatDate(area.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editAreaGrave(${area.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteAreaGrave(${area.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openAreaGrave(${area.id}, '${area.name}')">
                    <svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg>
                    כניסה
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // עדכן breadcrumb
    let breadcrumbPath = 'אחוזות קבר';
    if (window.selectedItems.cemetery && window.selectedItems.block && window.selectedItems.plot) {
        breadcrumbPath = `בתי עלמין › ${window.selectedItems.cemetery.name} › גושים › ${window.selectedItems.block.name} › חלקות › ${window.selectedItems.plot.name} › אחוזות קבר`;
    }
    updateBreadcrumb(breadcrumbPath);
}

// פתיחת אחוזת קבר ספציפית - מעבר לתצוגת קברים
function openAreaGrave(areaGraveId, areaGraveName) {
    console.log('Opening area grave:', areaGraveId, areaGraveName);
    console.log('Before updateSidebarSelection - currentType:', window.currentType);
    
    // שמור את הבחירה
    window.selectedItems.areaGrave = { id: areaGraveId, name: areaGraveName };
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    
    // עדכן את הסידבר
    console.log('Calling updateSidebarSelection with:', 'areaGrave', areaGraveId, areaGraveName);
    updateSidebarSelection('areaGrave', areaGraveId, areaGraveName);
    console.log('After updateSidebarSelection');
    
    // דיבוג של האלמנט בסידבר
    console.log('=== SIDEBAR ELEMENT DEBUG ===');
    const areaGraveHeader = document.querySelectorAll('.hierarchy-header')[3];
    if (areaGraveHeader) {
        console.log('Area Grave Header found');
        console.log('Header classes:', areaGraveHeader.className);
        console.log('Header has active class:', areaGraveHeader.classList.contains('active'));
        console.log('Header HTML:', areaGraveHeader.outerHTML);
    } else {
        console.log('ERROR: Area Grave Header NOT found!');
    }
    
    const selectedItemContainer = document.getElementById('areaGraveSelectedItem');
    if (selectedItemContainer) {
        console.log('Selected item container found');
        console.log('Container ID:', selectedItemContainer.id);
        console.log('Container display style:', selectedItemContainer.style.display);
        console.log('Container innerHTML:', selectedItemContainer.innerHTML);
    } else {
        console.log('ERROR: Selected item container NOT found!');
    }
    console.log('=== END SIDEBAR DEBUG ===');

    // טען את הקברים
    if (typeof loadGravesForAreaGrave === 'function') {
        loadGravesForAreaGrave(areaGraveId);
    }

    // עדכן breadcrumb
    let breadcrumbPath = `אחוזות קבר › ${areaGraveName}`;
    if (window.selectedItems.cemetery && window.selectedItems.block && window.selectedItems.plot) {
        breadcrumbPath = `בתי עלמין › ${window.selectedItems.cemetery.name} › גושים › ${window.selectedItems.block.name} › חלקות › ${window.selectedItems.plot.name} › אחוזות קבר › ${areaGraveName}`;
    }
    updateBreadcrumb(breadcrumbPath);
}

// הוספת אחוזת קבר חדשה
async function openAddAreaGrave(preselectedRowId = null) {
    // אם אין שורה מוגדרת מראש ואין שורה נבחרת
    if (!preselectedRowId && !window.selectedItems.row) {
        // צריך לטעון את השורות של החלקה ולתת לבחור
        if (window.selectedItems.plot) {
            const rows = await loadRowsForSelection(window.selectedItems.plot.id);
            if (rows && rows.length > 0) {
                showRowSelectionModal(rows);
            } else {
                showError('אין שורות בחלקה. יש להוסיף שורה תחילה');
                return;
            }
        } else {
            showWarning('יש לבחור חלקה תחילה');
            return;
        }
    } else {
        const rowId = preselectedRowId || window.selectedItems.row.id;
        window.currentType = 'areaGrave';
        window.currentParentId = rowId;
        
        if (typeof window.openModal === 'function') {
            window.openModal('areaGrave', rowId, null);
        } else {
            createAreaGraveForm(rowId);
        }
    }
}

// טעינת שורות לבחירה
async function loadRowsForSelection(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
    return [];
}

// הצגת מודל לבחירת שורה
function showRowSelectionModal(rows) {
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
        min-width: 400px;
    `;
    
    modal.innerHTML = `
        <h3>בחר שורה לאחוזת הקבר</h3>
        <div style="margin: 20px 0;">
            <select id="rowSelect" class="form-control" style="width: 100%; padding: 8px;">
                <option value="">-- בחר שורה --</option>
                ${rows.map(row => `<option value="${row.id}">${row.name}</option>`).join('')}
            </select>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="this.closest('div[style*=fixed]').remove()" 
                    style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                ביטול
            </button>
            <button onclick="proceedWithRowSelection()" 
                    style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                המשך
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// המשך עם השורה שנבחרה
window.proceedWithRowSelection = function() {
    const select = document.getElementById('rowSelect');
    const rowId = select.value;
    
    if (!rowId) {
        alert('יש לבחור שורה');
        return;
    }
    
    // סגור את המודל
    select.closest('div[style*=fixed]').remove();
    
    // פתח טופס הוספת אחוזת קבר
    window.currentType = 'areaGrave';
    window.currentParentId = rowId;
    
    if (typeof window.openModal === 'function') {
        window.openModal('areaGrave', rowId, null);
    } else {
        createAreaGraveForm(rowId);
    }
}

// יצירת טופס פשוט לאחוזת קבר
function createAreaGraveForm(rowId) {
    const form = document.createElement('div');
    form.id = 'simpleAddForm';
    form.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 30px rgba(0,0,0,0.3);
        z-index: 10000;
        min-width: 400px;
    `;
    
    form.innerHTML = `
        <h3>הוסף אחוזת קבר</h3>
        <form onsubmit="submitAreaGraveForm(event, ${rowId})">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">שם:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">סוג קבר:</label>
                <select name="grave_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- בחר סוג --</option>
                    <option value="1">פטור</option>
                    <option value="2">חריג</option>
                    <option value="3">סגור</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">קואורדינטות:</label>
                <input type="text" name="coordinates" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('simpleAddForm').remove()" 
                        style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ביטול
                </button>
                <button type="submit" 
                        style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    שמור
                </button>
            </div>
        </form>
    `;
    
    document.body.appendChild(form);
}

// שליחת טופס אחוזת קבר
window.submitAreaGraveForm = async function(event, rowId) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    const data = {
        name: formData.get('name'),
        grave_type: formData.get('grave_type'),
        coordinates: formData.get('coordinates'),
        row_id: rowId,
        is_active: 1
    };
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=create&type=area_grave`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('simpleAddForm').remove();
            showSuccess('אחוזת הקבר נוספה בהצלחה');
            
            // רענן את התצוגה
            if (window.selectedItems.plot) {
                loadAreaGravesForPlot(window.selectedItems.plot.id);
            } else {
                loadAllAreaGraves();
            }
        } else {
            alert('שגיאה: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        alert('שגיאה בשמירה');
        console.error(error);
    }
}

// עריכת אחוזת קבר
function editAreaGrave(id) {
    window.currentType = 'areaGrave';
    if (typeof window.openModal === 'function') {
        window.openModal('areaGrave', window.selectedItems.row?.id, id);
    }
}

// מחיקת אחוזת קבר
async function deleteAreaGrave(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק אחוזת קבר זו?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=area_grave&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('אחוזת הקבר נמחקה בהצלחה');
            
            // רענן את התצוגה
            if (window.selectedItems.plot) {
                loadAreaGravesForPlot(window.selectedItems.plot.id);
            } else if (window.selectedItems.row) {
                loadAreaGravesForRow(window.selectedItems.row.id);
            } else {
                loadAllAreaGraves();
            }
        } else {
            showError(data.error || 'שגיאה במחיקת אחוזת הקבר');
        }
    } catch (error) {
        console.error('Error deleting area grave:', error);
        showError('שגיאה במחיקת אחוזת הקבר');
    }
}

// פונקציות עזר
function getGraveTypeName(type) {
    const types = {
        1: 'פטור',
        2: 'חריג', 
        3: 'סגור'
    };
    return types[type] || 'לא מוגדר';
}