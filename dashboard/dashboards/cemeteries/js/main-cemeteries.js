// dashboards/cemeteries/js/main-cemeteries.js
// ניהול בתי עלמין
// פונקציה למעבר לפריט שנבחר
window.goToItem = function(type, id) {
    // כאן אפשר להוסיף לוגיקה למעבר לפריט
    console.log(`Going to ${type} with id ${id}`);
}


// טעינת כל בתי העלמין
async function loadAllCemeteries() {
    console.log('Loading all cemeteries...');

    clearItemCard(); // נקה את הכרטיס כשעוברים לתצוגה כללית
    
    // נקה את כל הסידבר
    clearAllSidebarSelections();
    
    // סמן שאנחנו ברמת בתי עלמין
    const cemeteriesHeader = document.querySelector('.hierarchy-header');
    if (cemeteriesHeader) {
        cemeteriesHeader.classList.add('active');
    }
    
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {}; // נקה את כל הבחירות
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=cemetery`);
        const data = await response.json();
        
        if (data.success) {
            displayCemeteriesInMainContent(data.data);
            updateSidebarCount('cemeteriesCount', data.data.length);
        }
    } catch (error) {
        console.error('Error loading cemeteries:', error);
        showError('שגיאה בטעינת בתי העלמין');
    }
}

// כשפותחים בית עלמין ספציפי
function openCemetery(cemeteryId, cemeteryName) {
    console.log('Opening cemetery:', cemeteryId, cemeteryName);
    
    // שמור את הבחירה
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    
    // עדכן את הסידבר - הצג את בית העלמין הנבחר
    updateSidebarSelection('cemetery', cemeteryId, cemeteryName);
    
    // טען את הגושים
    loadBlocksForCemetery(cemeteryId);
    
    // עדכן breadcrumb
    // updateBreadcrumb(`בתי עלמין › ${cemeteryName}`);
    updateBreadcrumb(window.selectedItems);
}

// הצגת בתי עלמין בתוכן הראשי (לא בסידבר!)
function displayCemeteriesInMainContent(cemeteries) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    // נקה את הטבלה
    tbody.innerHTML = '';
    
    if (cemeteries.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🏛️</div>
                        <div>אין בתי עלמין במערכת</div>
                        <button class="btn btn-primary mt-3" onclick="openAddCemetery()">
                            הוסף בית עלמין ראשון
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // הצג את בתי העלמין בטבלה
    cemeteries.forEach(cemetery => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.ondblclick = () => openCemetery(cemetery.id, cemetery.name);
        tr.onclick = () => selectTableRow(tr);
        
        tr.innerHTML = `
            <td>${cemetery.id}</td>
            <td>
                <strong>${cemetery.name}</strong>
                ${cemetery.address ? `<br><small class="text-muted">${cemetery.address}</small>` : ''}
            </td>
            <td>${cemetery.code || '-'}</td>
            <td><span class="badge badge-success">פעיל</span></td>
            <td>${formatDate(cemetery.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editCemetery(${cemetery.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteCemetery(${cemetery.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openCemetery(${cemetery.id}, '${cemetery.name}')">
                    <svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg>
                    כניסה
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // updateBreadcrumb('בתי עלמין');
    updateBreadcrumb({}); // או BreadcrumbManager.reset();
}

// הוספת בית עלמין חדש
function openAddCemetery() {
    currentType = 'cemetery';
    currentParentId = null;
    
    if (typeof window.openModal === 'function') {
        window.openModal('cemetery', null, null);
    } else {
        createSimpleAddForm();
    }
}

// עריכת בית עלמין
function editCemetery(id) {
    currentType = 'cemetery';
    if (typeof window.openModal === 'function') {
        window.openModal('cemetery', null, id);
    }
}

// מחיקת בית עלמין
async function deleteCemetery(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק בית עלמין זה?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=cemetery&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('בית העלמין נמחק בהצלחה');
            loadAllCemeteries();
        } else {
            showError(data.error || 'שגיאה במחיקת בית העלמין');
        }
    } catch (error) {
        console.error('Error deleting cemetery:', error);
        showError('שגיאה במחיקת בית העלמין');
    }
}

