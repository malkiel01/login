// dashboards/cemeteries/js/main-blocks.js
// ניהול גושים

// טעינת כל הגושים
async function loadAllBlocks() {
    console.log('Loading all blocks...');

    clearItemCard(); // נקה את הכרטיס כשעוברים לתצוגה כללית

    // נקה את כל הסידבר
    clearAllSidebarSelections();

    // סמן שאנחנו ברמת גושים
    const blocksHeader = document.querySelectorAll('.hierarchy-header')[1];
    if (blocksHeader) {
        blocksHeader.classList.add('active');
    }
    
    window.currentType = 'block';
    window.currentParentId = null;
    window.selectedItems = {};

    
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

// כשפותחים גוש ספציפי
async function openBlock(blockId, blockName) {
    console.log('Opening block:', blockId, blockName);
    
    // שמור את הבחירה
    window.selectedItems.block = { id: blockId, name: blockName };
    window.currentType = 'plot';
    window.currentParentId = blockId;
    
    // עדכן את הסידבר - הצג את הגוש הנבחר
    updateSidebarSelection('block', blockId, blockName);
    
    // טען את החלקות עם כרטיס הגוש
    await loadPlotsForBlockWithCard(blockId);
    
    // עדכן breadcrumb
    updateBreadcrumb(window.selectedItems);
}

// הוסף פונקציה חדשה ב-main-blocks.js
async function loadPlotsForBlockWithCard(blockId) {
    try {
        // תחילה הצג את כרטיס הגוש
        const cardHtml = await createBlockCard(blockId);
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
        
        // אז טען את החלקות
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}`);
        const data = await response.json();
        
        if (data.success) {
            displayPlotsInMainContent(data.data, window.selectedItems.block?.name);
        }
    } catch (error) {
        console.error('Error loading plots with card:', error);
        showError('שגיאה בטעינת החלקות');
    }
}

// טעינת גושים לבית עלמין ספציפי
async function loadBlocksForCemetery(cemeteryId) {
    console.log('Loading blocks for cemetery:', cemeteryId);
    try {
        // תחילה הצג את כרטיס בית העלמין
        const cardHtml = await createCemeteryCard(cemeteryId);
        const mainContent = document.querySelector('.main-content');
        
        // בדוק אם כבר יש container לכרטיס, אם לא - צור אחד
        let cardContainer = document.getElementById('itemCard');
        if (!cardContainer) {
            cardContainer = document.createElement('div');
            cardContainer.id = 'itemCard';
            
            // הכנס את הכרטיס אחרי statsGrid
            const statsGrid = document.getElementById('statsGrid');
            if (statsGrid) {
                statsGrid.insertAdjacentElement('afterend', cardContainer);
            } else {
                // אם אין statsGrid, הכנס בתחילת main-content
                const tableContainer = document.querySelector('.table-container');
                if (tableContainer) {
                    mainContent.insertBefore(cardContainer, tableContainer);
                }
            }
        }
        
        // הכנס את התוכן
        cardContainer.innerHTML = cardHtml;
        
        // אז טען את הגושים
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
        const data = await response.json();
        
        if (data.success) {
            displayBlocksInMainContent(data.data, window.selectedItems.cemetery?.name);
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
                        ${window.selectedItems.cemetery ? `
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
    updateBreadcrumb(window.selectedItems);
}

// הוספת גוש חדש
function openAddBlock() {
    if (!window.selectedItems.cemetery) {
        showWarning('יש לבחור בית עלמין תחילה');
        return;
    }
    
    window.currentType = 'block';
    window.currentParentId = window.selectedItems.cemetery.id;
    
    if (typeof window.openModal === 'function') {
        window.openModal('block', window.selectedItems.cemetery.id, null);
    } else {
        createSimpleAddForm();
    }
}

// עריכת גוש
function editBlock(id) {
    window.currentType = 'block';
    if (typeof window.openModal === 'function') {
        window.openModal('block', window.selectedItems.cemetery.id, null);
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
            if (window.selectedItems.cemetery) {
                loadBlocksForCemetery(window.selectedItems.cemetery.id);
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