// dashboards/cemeteries/js/main-cemeteries.js
// ניהול בתי עלמין
// פונקציה מעודכנת לניהול הסידבר
function updateSidebarSelection(type, id, name) {
    console.log('updateSidebarSelection called:', type, id, name);
    // 1. הסר את כל ה-active מהכותרות
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
    
    // 2. הוסף active לכותרת הנוכחית
    const headers = {
        'cemetery': 0,
        'block': 1,
        'plot': 2,
        'area_grave': 3,
        'grave': 4
    };
    
    const headerElements = document.querySelectorAll('.hierarchy-header');
    if (headerElements[headers[type]]) {
        headerElements[headers[type]].classList.add('active');
    }
    
    // 3. נקה את כל הבחירות מתחת לרמה הנוכחית
    clearSidebarBelow(type);
    
    // 4. הצג את הפריט הנבחר
    const container = document.getElementById(`${type}SelectedItem`);
    if (container) {
        container.innerHTML = `
            <div class="selected-item" onclick="goToItem('${type}', ${id})">
                <span class="selected-icon">📍</span>
                <span class="selected-name">${name}</span>
            </div>
        `;
        container.style.display = 'block';
    }
}

// פונקציה לניקוי כל הבחירות
function clearAllSidebarSelections() {
    // הסר active מכל הכותרות
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
    
    // נקה את כל הפריטים הנבחרים
    const containers = [
        'cemeterySelectedItem',
        'blockSelectedItem', 
        'plotSelectedItem',
        'areaGraveSelectedItem',
        'graveSelectedItem'
    ];
    
    containers.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.innerHTML = '';
            element.style.display = 'none';
        }
    });
}

// פונקציה למעבר לפריט שנבחר
window.goToItem = function(type, id) {
    // כאן אפשר להוסיף לוגיקה למעבר לפריט
    console.log(`Going to ${type} with id ${id}`);
}
// ניקוי הסידבר מתחת לרמה מסוימת
function clearSidebarBelow(type) {
    const hierarchy = ['cemetery', 'block', 'plot', 'area_grave', 'grave'];
    const currentIndex = hierarchy.indexOf(type);
    
    // נקה רק את הרמות מתחת לרמה הנוכחית
    for (let i = currentIndex + 1; i < hierarchy.length; i++) {
        const container = document.getElementById(`${hierarchy[i]}SelectedItem`);
        if (container) {
            container.innerHTML = '';
            container.style.display = 'none';
        }
    }
}

// טעינת כל בתי העלמין
async function loadAllCemeteries() {
    console.log('Loading all cemeteries...');
    
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
    updateBreadcrumb(`בתי עלמין › ${cemeteryName}`);
}

// פונקציה לניקוי כל הפריטים הנבחרים בסידבר
function clearAllSidebarSelections2() {
    const containers = [
        'cemeterySelectedItem',
        'blockSelectedItem', 
        'plotSelectedItem',
        'areaGraveSelectedItem',
        'graveSelectedItem'
    ];
    
    containers.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.innerHTML = '';
            element.style.display = 'none';
        }
    });
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
    
    updateBreadcrumb('בתי עלמין');
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

// עדכון הבחירה בסידבר
function updateSidebarSelection(type, id, name) {
    // הסר את כל האיתים מהרמות הנמוכות יותר
    clearSidebarBelow(type);
    
    // הוסף את הפריט הנבחר לסידבר
    const container = document.getElementById(`${type}SelectedItem`);
    if (container) {
        container.innerHTML = `
            <div class="selected-item">
                <span class="selected-icon">📍</span>
                <span class="selected-name">${name}</span>
            </div>
        `;
    }
}

// הפוך את הפונקציות לגלובליות
window.updateSidebarSelection = updateSidebarSelection;
window.clearAllSidebarSelections = clearAllSidebarSelections;
