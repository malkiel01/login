// dashboards/cemeteries/js/main-plots.js
// ניהול חלקות

// טעינת כל החלקות
async function loadAllPlots() {
    console.log('Loading all plots...');
    
    // אל תנקה את הסידבר! רק אפס את הבחירה
    window.currentType = 'plot';
    window.currentParentId = null;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=plot`);
        const data = await response.json();
        
        if (data.success) {
            displayPlotsInMainContent(data.data);
            updateSidebarCount('plotsCount', data.data.length);
        }
    } catch (error) {
        console.error('Error loading plots:', error);
        showError('שגיאה בטעינת חלקות');
    }
}

// טעינת חלקות לגוש ספציפי
async function loadPlotsForBlock(blockId) {
    console.log('Loading plots for block:', blockId);
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}`);
        const data = await response.json();
        
        if (data.success) {
            displayPlotsInMainContent(data.data, window.selectedItems.block?.name);
        }
    } catch (error) {
        console.error('Error loading plots:', error);
        showError('שגיאה בטעינת חלקות');
    }
}

// הצגת חלקות בתוכן הראשי
function displayPlotsInMainContent(plots, blockName = null) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    // נקה את הטבלה
    tbody.innerHTML = '';
    
    if (plots.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📋</div>
                        <div>אין חלקות ${blockName ? `בגוש ${blockName}` : 'במערכת'}</div>
                        ${window.selectedItems.block ? `
                            <button class="btn btn-primary mt-3" onclick="openAddPlot()">
                                הוסף חלקה חדשה
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // הצג את החלקות בטבלה
    plots.forEach(plot => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.ondblclick = () => openPlot(plot.id, plot.name);
        tr.onclick = () => selectTableRow(tr);
        
        tr.innerHTML = `
            <td>${plot.id}</td>
            <td>
                <strong>${plot.name}</strong>
                ${plot.location ? `<br><small class="text-muted">📍 ${plot.location}</small>` : ''}
            </td>
            <td>${plot.code || '-'}</td>
            <td><span class="badge badge-success">פעיל</span></td>
            <td>${formatDate(plot.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editPlot(${plot.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deletePlot(${plot.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openPlot(${plot.id}, '${plot.name}')">
                    <svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg>
                    כניסה
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // עדכן breadcrumb
    let breadcrumbPath = 'חלקות';
    if (window.selectedItems.cemetery && window.selectedItems.block) {
        breadcrumbPath = `בתי עלמין › ${window.selectedItems.cemetery.name} › גושים › ${window.selectedItems.block.name} › חלקות`;
    } else if (blockName) {
        breadcrumbPath = `גושים › ${blockName} › חלקות`;
    }
    updateBreadcrumb(breadcrumbPath);
}

// פתיחת חלקה ספציפית - מעבר לתצוגת אחוזות קבר (דילוג על שורות!)
function openPlot(plotId, plotName) {
    console.log('Opening plot:', plotId, plotName);
    
    // שמור את הבחירה
    window.selectedItems.plot = { id: plotId, name: plotName };
    window.currentType = 'area_grave'; // דילוג ישר לאחוזות קבר
    window.currentParentId = plotId;
    
    // עדכן את הסידבר - הצג את החלקה הנבחרת
    updateSidebarSelection('plot', plotId, plotName);
    
    // טען את אחוזות הקבר של החלקה (עם השורות שלהן)
    loadAreaGravesForPlot(plotId);
    
    // עדכן breadcrumb
    let breadcrumbPath = `חלקות › ${plotName}`;
    if (window.selectedItems.cemetery && window.selectedItems.block) {
        breadcrumbPath = `בתי עלמין › ${window.selectedItems.cemetery.name} › גושים › ${window.selectedItems.block.name} › חלקות › ${plotName}`;
    }
    updateBreadcrumb(breadcrumbPath);
}

// טעינת אחוזות קבר לחלקה (דרך השורות)
async function loadAreaGravesForPlot(plotId) {
    console.log('Loading area graves for plot:', plotId);
    try {
        // קודם טען את השורות של החלקה
        const rowsResponse = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const rowsData = await rowsResponse.json();
        
        if (rowsData.success && rowsData.data.length > 0) {
            // טען את כל אחוזות הקבר של כל השורות
            const areaGraves = [];
            for (const row of rowsData.data) {
                const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${row.id}`);
                const data = await response.json();
                if (data.success) {
                    // הוסף את מידע השורה לכל אחוזת קבר
                    data.data.forEach(area => {
                        area.row_name = row.name;
                        area.row_id = row.id;
                        areaGraves.push(area);
                    });
                }
            }
            
            // הצג את אחוזות הקבר עם מידע על השורות
            displayAreaGravesWithRows(areaGraves, rowsData.data, window.selectedItems.plot?.name);
        } else {
            // אין שורות - הצע ליצור
            displayEmptyPlot(window.selectedItems.plot?.name);
        }
    } catch (error) {
        console.error('Error loading area graves for plot:', error);
        showError('שגיאה בטעינת אחוזות קבר');
    }
}

// הצגת חלקה ריקה
function displayEmptyPlot(plotName) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    tbody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align: center; padding: 40px;">
                <div style="color: #999;">
                    <div style="font-size: 48px; margin-bottom: 20px;">📏</div>
                    <div>אין שורות בחלקה ${plotName || ''}</div>
                    <p style="margin-top: 10px;">יש להוסיף שורות לחלקה לפני הוספת אחוזות קבר</p>
                    <button class="btn btn-primary mt-3" onclick="openAddRow()">
                        הוסף שורה ראשונה
                    </button>
                </div>
            </td>
        </tr>
    `;
}

// הצגת אחוזות קבר עם מידע על השורות
function displayAreaGravesWithRows(areaGraves, rows, plotName) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    // הוסף כותרת עם סיכום שורות
    const summaryRow = document.createElement('tr');
    summaryRow.innerHTML = `
        <td colspan="6" style="background: #f8f9fa; padding: 15px;">
            <strong>חלקה: ${plotName || ''}</strong> | 
            <span>סה"כ ${rows.length} שורות</span> | 
            <span>סה"כ ${areaGraves.length} אחוזות קבר</span>
            <button class="btn btn-sm btn-primary" style="margin-right: 20px;" onclick="manageRows()">
                ניהול שורות
            </button>
        </td>
    `;
    tbody.appendChild(summaryRow);
    
    // הצג את אחוזות הקבר מקובצות לפי שורה
    rows.forEach(row => {
        const rowAreas = areaGraves.filter(area => area.row_id === row.id);
        
        // כותרת שורה
        const rowHeader = document.createElement('tr');
        rowHeader.style.background = '#e9ecef';
        rowHeader.innerHTML = `
            <td colspan="6" style="padding: 10px; font-weight: bold;">
                📏 שורה ${row.name} (${rowAreas.length} אחוזות קבר)
            </td>
        `;
        tbody.appendChild(rowHeader);
        
        // אחוזות הקבר של השורה
        if (rowAreas.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
                <td colspan="6" style="padding: 20px; text-align: center; color: #999;">
                    אין אחוזות קבר בשורה זו
                    <button class="btn btn-sm btn-primary" style="margin-right: 10px;" onclick="openAddAreaGrave(${row.id})">
                        הוסף אחוזת קבר
                    </button>
                </td>
            `;
            tbody.appendChild(emptyRow);
        } else {
            rowAreas.forEach(area => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.ondblclick = () => openAreaGrave(area.id, area.name);
                
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
        }
    });
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

// הוספת חלקה חדשה
function openAddPlot() {
    if (!window.selectedItems.block) {
        showWarning('יש לבחור גוש תחילה');
        return;
    }
    
    window.currentType = 'plot';
    window.currentParentId = window.selectedItems.block.id;
    
    if (typeof window.openModal === 'function') {
        window.openModal('plot', window.selectedItems.block.id, null);
    } else {
        createSimpleAddForm();
    }
}

// עריכת חלקה
function editPlot(id) {
    window.currentType = 'plot';
    if (typeof window.openModal === 'function') {
        window.openModal('plot', window.selectedItems.block?.id, id);
    }
}

// מחיקת חלקה
async function deletePlot(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק חלקה זו?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=plot&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('החלקה נמחקה בהצלחה');
            if (window.selectedItems.block) {
                loadPlotsForBlock(window.selectedItems.block.id);
            } else {
                loadAllPlots();
            }
        } else {
            showError(data.error || 'שגיאה במחיקת החלקה');
        }
    } catch (error) {
        console.error('Error deleting plot:', error);
        showError('שגיאה במחיקת החלקה');
    }
}

// פונקציות לניהול שורות (יופיעו בחלון נפרד)
function manageRows() {
    // TODO: פתח חלון לניהול שורות
    console.log('Opening rows management for plot:', window.selectedItems.plot);
    alert('ניהול שורות - בפיתוח');
}

function openAddRow() {
    // TODO: הוסף שורה חדשה
    console.log('Adding row to plot:', window.selectedItems.plot);
    alert('הוספת שורה - בפיתוח');
}

function openAddAreaGrave(rowId) {
    // TODO: הוסף אחוזת קבר לשורה
    console.log('Adding area grave to row:', rowId);
    alert('הוספת אחוזת קבר - בפיתוח');
}

function openAreaGrave(id, name) {
    // TODO: פתח אחוזת קבר
    console.log('Opening area grave:', id, name);
    alert('פתיחת אחוזת קבר - בפיתוח');
}

function editAreaGrave(id) {
    // TODO: ערוך אחוזת קבר
    console.log('Editing area grave:', id);
    alert('עריכת אחוזת קבר - בפיתוח');
}

function deleteAreaGrave(id) {
    // TODO: מחק אחוזת קבר
    console.log('Deleting area grave:', id);
    alert('מחיקת אחוזת קבר - בפיתוח');
}