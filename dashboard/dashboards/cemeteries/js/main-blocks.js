// dashboards/cemeteries/js/main-blocks.js
// ניהול גושים

// טעינת כל הגושים
async function loadAllBlocks() {
    console.log('Loading all blocks...');
    
    // נקה את כל הבחירות בסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // אפס את הבחירות
    window.selectedItems = {};
    window.currentType = 'block';
    window.currentParentId = null;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=block`);
        const data = await response.json();
        
        if (data.success) {
            displayBlocksInMainContent(data.data);
            updateSidebarCount('blocksCount', data.data.length);
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
        showError('שגיאה בטעינת גושים');
    }
}

// טעינת גושים לבית עלמין ספציפי
async function loadBlocksForCemetery(cemeteryId) {
    console.log('Loading blocks for cemetery:', cemeteryId);
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
        const data = await response.json();
        
        if (data.success) {
            displayBlocksInMainContent(data.data, selectedItems.cemetery?.name);
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
        showError('שגיאה בטעינת גושים');
    }
}

// הצגת גושים בתוכן הראשי
function displayBlocksInMainContent(blocks, cemeteryName = null) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    // נקה את הטבלה
    tbody.innerHTML = '';
    
    if (blocks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                        <div>אין גושים ${cemeteryName ? `בבית עלמין ${cemeteryName}` : 'במערכת'}</div>
                        ${selectedItems.cemetery ? `
                            <button class="btn btn-primary mt-3" onclick="openAddBlock()">
                                הוסף גוש חדש
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // הצג את הגושים בטבלה
    blocks.forEach(block => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.ondblclick = () => openBlock(block.id, block.name);
        tr.onclick = () => selectTableRow(tr);
        
        tr.innerHTML = `
            <td>${block.id}</td>
            <td>
                <strong>${block.name}</strong>
                ${block.location ? `<br><small class="text-muted">📍 ${block.location}</small>` : ''}
            </td>
            <td>${block.code || '-'}</td>
            <td><span class="badge badge-success">פעיל</span></td>
            <td>${formatDate(block.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editBlock(${block.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteBlock(${block.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openBlock(${block.id}, '${block.name}')">
                    <svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg>
                    כניסה
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // עדכן breadcrumb
    if (cemeteryName) {
        updateBreadcrumb(`בתי עלמין › ${cemeteryName} › גושים`);
    } else {
        updateBreadcrumb('גושים');
    }
}

// פתיחת גוש ספציפי - מעבר לתצוגת חלקות
function openBlock(blockId, blockName) {
    console.log('Opening block:', blockId, blockName);
    
    // שמור את הבחירה
    selectedItems.block = { id: blockId, name: blockName };
    currentType = 'plot';
    currentParentId = blockId;
    
    // עדכן את הסידבר להציג את הגוש הנבחר
    updateSidebarSelection('block', blockId, blockName);
    
    // טען את החלקות של הגוש
    loadPlotsForBlock(blockId);
    
    // עדכן breadcrumb
    const path = selectedItems.cemetery 
        ? `בתי עלמין › ${selectedItems.cemetery.name} › גושים › ${blockName}`
        : `גושים › ${blockName}`;
    updateBreadcrumb(path);
}

// הוספת גוש חדש
function openAddBlock() {
    if (!selectedItems.cemetery) {
        showWarning('יש לבחור בית עלמין תחילה');
        return;
    }
    
    currentType = 'block';
    currentParentId = selectedItems.cemetery.id;
    
    if (typeof window.openModal === 'function') {
        window.openModal('block', selectedItems.cemetery.id, null);
    } else {
        createSimpleAddForm();
    }
}

// עריכת גוש
function editBlock(id) {
    currentType = 'block';
    if (typeof window.openModal === 'function') {
        window.openModal('block', selectedItems.cemetery?.id, id);
    }
}

// מחיקת גוש
async function deleteBlock(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק גוש זה?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=block&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('הגוש נמחק בהצלחה');
            if (selectedItems.cemetery) {
                loadBlocksForCemetery(selectedItems.cemetery.id);
            } else {
                loadAllBlocks();
            }
        } else {
            showError(data.error || 'שגיאה במחיקת הגוש');
        }
    } catch (error) {
        console.error('Error deleting block:', error);
        showError('שגיאה במחיקת הגוש');
    }
}

// פונקציה עזר לבחירת שורה בטבלה
function selectTableRow(row) {
    // הסר בחירה קודמת
    document.querySelectorAll('#tableBody tr.selected').forEach(tr => {
        tr.classList.remove('selected');
    });
    // הוסף בחירה לשורה הנוכחית
    row.classList.add('selected');
}