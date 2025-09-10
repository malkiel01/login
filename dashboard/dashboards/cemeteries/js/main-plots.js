// dashboards/cemeteries/js/main-plots.js
// ניהול חלקות

// טעינת כל החלקות
async function loadAllPlots() {
    console.log('Loading all plots...');

    clearItemCard(); // נקה את הכרטיס כשעוברים לתצוגה כללית

    // נקה את כל הסידבר
    clearAllSidebarSelections();

    // סמן שאנחנו ברמת גושים
    const plotsHeader = document.querySelectorAll('.hierarchy-header')[2];
    if (plotsHeader) {
        plotsHeader.classList.add('active');
    }
    
    window.currentType = 'plot';
    window.currentParentId = null;
    window.selectedItems = {};
    
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
        // תחילה הצג את כרטיס הגוש
        const cardHtml = await createBlockCard(blockId);
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
        
        // אז טען את החלקות
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
    const thead = document.getElementById('tableHeaders'); // הוסף שורה זו
    
    if (!tbody) return;
    
    // עדכון כותרות הטבלה לחלקות - הוסף בלוק זה
    if (thead) {
        thead.innerHTML = `
            <th>מזהה</th>
            <th>שם חלקה</th>
            <th>קוד</th>
            <th>מיקום</th>
            <th>נוצר בתאריך</th>
            <th>פעולות</th>
        `;
    }
    
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
    updateBreadcrumb(window.selectedItems);
}

// פתיחת חלקה ספציפית - מעבר לתצוגת אחוזות קבר (דילוג על שורות!)
async function openPlot(plotId, plotName) {
    console.log('Opening plot:', plotId, plotName);
    
    // שמור את הבחירה
    window.selectedItems.plot = { id: plotId, name: plotName };
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
    
    // עדכן את הסידבר - הצג את החלקה הנבחרת
    updateSidebarSelection('plot', plotId, plotName);
    
    // טען את אחוזות הקבר עם כרטיס החלקה
    await loadAreaGravesForPlotWithCard(plotId);
    
    // עדכון breadcrumb
    updateBreadcrumb(window.selectedItems);
}

// הוסף פונקציה חדשה ב-main-plots.js
async function loadAreaGravesForPlotWithCard(plotId) {
    try {
        const cardHtml = await createPlotCard(plotId);
        const mainContent = document.querySelector('.main-content');
        
        let cardContainer = document.getElementById('itemCard');
        if (!cardContainer) {
            cardContainer = document.createElement('div');
            cardContainer.id = 'itemCard';
            
            const statsGrid = document.getElementById('statsGrid');
            if (statsGrid) {
                statsGrid.insertAdjacentElement('afterend', cardContainer);
            } else {
                const tableContainer = document.querySelector('.table-container');
                if (tableContainer) {
                    mainContent.insertBefore(cardContainer, tableContainer);
                }
            }
        }
        
        cardContainer.innerHTML = cardHtml;
        
        // טען אחוזות קבר כרגיל
        loadAreaGravesForPlot(plotId);
    } catch (error) {
        console.error('Error loading area graves with card:', error);
        showError('שגיאה בטעינת אחוזות הקבר');
    }
}

// טעינת אחוזות קבר לחלקה (דרך השורות)
async function loadAreaGravesForPlot(plotId) {
    console.log('Loading area graves for plot:', plotId);
    try {
        // תחילה הצג את כרטיס החלקה (כולל השורות)
        const cardHtml = await createPlotCard(plotId);
        const mainContent = document.querySelector('.main-content');
        
        const cardContainer = document.getElementById('itemCard') || document.createElement('div');
        cardContainer.id = 'itemCard';
        cardContainer.innerHTML = cardHtml;
        
        const tableWrapper = document.querySelector('.table-wrapper');
        if (tableWrapper && cardHtml) {
            mainContent.insertBefore(cardContainer, tableWrapper);
        }
        
        // אז טען את אחוזות הקבר
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&plot_id=${plotId}`);
        const data = await response.json();
        
        if (data.success) {
            displayAreaGravesInMainContent(data.data);
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
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
    const thead = document.getElementById('tableHeaders'); // הוסף שורה זו
    
    if (!tbody) return;
    
    // עדכון כותרות הטבלה לחלקות - הוסף בלוק זה
    if (thead) {
        thead.innerHTML = `
            <th>מזהה</th>
            <th>שם אחוזת הקבר</th>
            <th>קוד</th>
            <th>מיקום</th>
            <th>נוצר בתאריך</th>
            <th>פעולות</th>
        `;
    }
    
    tbody.innerHTML = '';
    
    // הוסף כותרת עם סיכום שורות
    const summaryRow = document.createElement('tr');
    summaryRow.innerHTML = `
        <td colspan="6" style="background: #f8f9fa; padding: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>חלקה: ${plotName || ''}</strong> | 
                    <span>סה"כ ${rows.length} שורות</span> | 
                    <span>סה"כ ${areaGraves.length} אחוזות קבר</span>
                </div>
                <div>
                    <button class="btn btn-sm btn-primary" onclick="manageRows()">
                        ניהול שורות
                    </button>
                    <button class="btn btn-sm btn-success" onclick="openAddAreaGrave()" style="margin-right: 10px;">
                        הוסף אחוזת קבר
                    </button>
                </div>
            </div>
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
                <button class="btn btn-xs btn-primary" style="margin-right: 10px; font-size: 11px; padding: 4px 8px;" onclick="openAddAreaGrave(${row.id})">
                    + הוסף כאן
                </button>
            </td>
        `;
        tbody.appendChild(rowHeader);
        
        // אחוזות הקבר של השורה
        if (rowAreas.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
                <td colspan="6" style="padding: 20px; text-align: center; color: #999;">
                    אין אחוזות קבר בשורה זו
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
        1: 'פטורה',
        2: 'חריגה', 
        3: 'סגורה'
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

// פונקציות לניהול שורות
function manageRows() {
    if (!window.selectedItems.plot) {
        showError('לא נבחרה חלקה');
        return;
    }
    
    // פתח חלון לניהול שורות
    openRowsManagementModal(window.selectedItems.plot.id, window.selectedItems.plot.name);
}

// חלון ניהול שורות
async function openRowsManagementModal(plotId, plotName) {
    try {
        // טען את השורות הקיימות
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        if (!data.success) {
            showError('שגיאה בטעינת שורות');
            return;
        }
        
        const rows = data.data;
        
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
            min-width: 600px;
            max-height: 70vh;
            overflow-y: auto;
        `;
        
        modal.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>ניהול שורות - חלקה ${plotName}</h3>
                <button onclick="this.closest('div[style*=fixed]').remove()" 
                        style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <button onclick="openAddRowForm(${plotId})" 
                        style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    + הוסף שורה חדשה
                </button>
            </div>
            
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; border: 1px solid #ddd;">מספר סידורי</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">שם</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">מיקום</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">אחוזות קבר</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.length > 0 ? rows.map(row => `
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;">${row.serial_number || '-'}</td>
                            <td style="padding: 10px; border: 1px solid #ddd;">${row.name}</td>
                            <td style="padding: 10px; border: 1px solid #ddd;">${row.location || '-'}</td>
                            <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">
                                <span id="rowAreaCount_${row.id}">טוען...</span>
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <button onclick="editRow(${row.id})" class="btn btn-sm btn-secondary">ערוך</button>
                                <button onclick="deleteRow(${row.id})" class="btn btn-sm btn-danger">מחק</button>
                            </td>
                        </tr>
                    `).join('') : `
                        <tr>
                            <td colspan="5" style="padding: 20px; text-align: center; color: #999;">
                                אין שורות בחלקה זו
                            </td>
                        </tr>
                    `}
                </tbody>
            </table>
            
            <div style="margin-top: 20px; text-align: left;">
                <button onclick="this.closest('div[style*=fixed]').remove()" 
                        style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    סגור
                </button>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // טען ספירת אחוזות קבר לכל שורה
        rows.forEach(async row => {
            try {
                const res = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${row.id}`);
                const data = await res.json();
                const countElement = document.getElementById(`rowAreaCount_${row.id}`);
                if (countElement) {
                    countElement.textContent = data.data ? data.data.length : '0';
                }
            } catch (error) {
                console.error('Error loading area graves count:', error);
            }
        });
        
    } catch (error) {
        console.error('Error in rows management:', error);
        showError('שגיאה בפתיחת ניהול שורות');
    }
}

// הוספת אחוזת קבר עם בחירת שורה
function openAddAreaGrave(preSelectedRowId = null) {
    if (!window.selectedItems.plot) {
        showWarning('יש לבחור חלקה תחילה');
        return;
    }
    
    // אם כבר יש שורה שנבחרה (מתוך ניהול שורות)
    if (preSelectedRowId) {
        window.currentType = 'area_grave';
        window.currentParentId = preSelectedRowId;
        FormHandler.openForm('area_grave', preSelectedRowId, null);
        return;
    }
    
    // אחרת - טען את השורות ותן למשתמש לבחור
    fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${window.selectedItems.plot.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                showRowSelectionModal(data.data);
            } else {
                showWarning('אין שורות בחלקה זו. יש להוסיף שורה תחילה.');
            }
        })
        .catch(error => {
            console.error('Error loading rows:', error);
            showError('שגיאה בטעינת שורות');
        });
}

// מודל לבחירת שורה
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
        z-index: 10001;
        min-width: 400px;
    `;
    
    modal.innerHTML = `
        <h3>בחר שורה לאחוזת הקבר</h3>
        <p>יש לבחור את השורה שתחתיה תיווצר אחוזת הקבר:</p>
        <div style="margin: 20px 0;">
            <select id="rowSelect" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">-- בחר שורה --</option>
                ${rows.map(row => `
                    <option value="${row.id}">
                        ${row.name} ${row.serial_number ? `(${row.serial_number})` : ''}
                    </option>
                `).join('')}
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
    
    // פתח את הטופס עם השורה שנבחרה כהורה
    window.currentType = 'area_grave';
    window.currentParentId = rowId;
    FormHandler.openForm('area_grave', rowId, null);
}

// טופס הוספת שורה עם המערכת החדשה
window.openAddRowForm = function(plotId) {
    window.currentType = 'row';
    window.currentParentId = plotId;
    FormHandler.openForm('row', plotId, null);
}

// הסרתי מהשיטה הישנה
// // טופס הוספת שורה
// window.openAddRowForm = function(plotId) {
//     const form = document.createElement('div');
//     form.style.cssText = `
//         position: fixed;
//         top: 50%;
//         left: 50%;
//         transform: translate(-50%, -50%);
//         background: white;
//         padding: 30px;
//         border-radius: 10px;
//         box-shadow: 0 0 30px rgba(0,0,0,0.3);
//         z-index: 10001;
//         min-width: 400px;
//     `;
    
//     form.innerHTML = `
//         <h3>הוסף שורה חדשה</h3>
//         <form onsubmit="submitRowForm(event, ${plotId})">
//             <div style="margin-bottom: 15px;">
//                 <label style="display: block; margin-bottom: 5px;">שם:</label>
//                 <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
//             </div>
//             <div style="margin-bottom: 15px;">
//                 <label style="display: block; margin-bottom: 5px;">מספר סידורי:</label>
//                 <input type="number" name="serial_number" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
//             </div>
//             <div style="margin-bottom: 15px;">
//                 <label style="display: block; margin-bottom: 5px;">מיקום:</label>
//                 <input type="text" name="location" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
//             </div>
//             <div style="display: flex; gap: 10px; justify-content: flex-end;">
//                 <button type="button" onclick="this.closest('div[style*=fixed]').remove()" 
//                         style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
//                     ביטול
//                 </button>
//                 <button type="submit" 
//                         style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
//                     שמור
//                 </button>
//             </div>
//         </form>
//     `;
    
//     document.body.appendChild(form);
// }

// // שליחת טופס שורה
// window.submitRowForm = async function(event, plotId) {
//     event.preventDefault();
//     const formData = new FormData(event.target);
    
//     const data = {
//         name: formData.get('name'),
//         serial_number: formData.get('serial_number'),
//         location: formData.get('location'),
//         plot_id: plotId,
//         is_active: 1
//     };
    
//     try {
//         const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=create&type=row`, {
//             method: 'POST',
//             headers: {'Content-Type': 'application/json'},
//             body: JSON.stringify(data)
//         });
        
//         const result = await response.json();
        
//         if (result.success) {
//             event.target.closest('div[style*=fixed]').remove();
//             showSuccess('השורה נוספה בהצלחה');
            
//             // סגור את החלון הקודם וטען מחדש
//             document.querySelectorAll('div[style*=fixed]').forEach(el => el.remove());
//             openRowsManagementModal(plotId, window.selectedItems.plot?.name);
//         } else {
//             alert('שגיאה: ' + (result.error || 'Unknown error'));
//         }
//     } catch (error) {
//         alert('שגיאה בשמירה');
//         console.error(error);
//     }
// }

// עריכת שורה
window.editRow = function(rowId) {
    // TODO: טען את פרטי השורה ופתח טופס עריכה
    alert('עריכת שורה - בפיתוח');
}

// מחיקת שורה
window.deleteRow = async function(rowId) {
    if (!confirm('האם אתה בטוח? מחיקת שורה תמחק גם את כל אחוזות הקבר והקברים שבה!')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=row&id=${rowId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('השורה נמחקה בהצלחה');
            // סגור וטען מחדש
            document.querySelectorAll('div[style*=fixed]').forEach(el => el.remove());
            openRowsManagementModal(window.selectedItems.plot.id, window.selectedItems.plot.name);
        } else {
            showError(data.error || 'שגיאה במחיקת השורה');
        }
    } catch (error) {
        console.error('Error deleting row:', error);
        showError('שגיאה במחיקת השורה');
    }
}

// // הוספת אחוזת קבר - הפונקציה כבר מוגדרת ב-main-area-graves.js
// // רק מחבר אותה מהקונטקסט של החלקה
// function openAddAreaGrave(rowId) {
//     if (typeof window.openAddAreaGrave === 'function') {
//         window.openAddAreaGrave(rowId);
//     } else {
//         alert('פונקציית הוספת אחוזת קבר אינה זמינה');
//     }
// }


// הוסף את הפונקציה הזו לפני הייצוא בסוף הקובץ (לפני שורה 580)
function openAddRow() {
    if (window.selectedItems.plot) {
        window.openAddRowForm(window.selectedItems.plot.id);
    } else {
        showError('לא נבחרה חלקה');
    }
}

// ייצוא פונקציות גלובליות
window.manageRows = manageRows;
window.openAddRow = openAddRow;
window.openAddAreaGrave = openAddAreaGrave;