// dashboard/dashboards/cemeteries/js/main.js

// משתנים גלובליים
window.currentType = 'cemetery';
window.currentParentId = null;

window.currentCemeteryId = null;
window.currentBlockId = null;
window.currentPlotId = null;
window.currentRowId = null;
window.currentAreaGraveId = null;

window.selectedItems = {};
let currentPage = 1;
let isLoading = false;

// הגדרות API - נתיב מלא מהשורש
const API_BASE = '/dashboard/dashboards/cemeteries/api/';

// אתחול הדשבורד
function initDashboard() {
    console.log('Initializing Cemetery Dashboard...');
    
    // אתחול משתנים גלובליים
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = window.selectedItems || {};
    
    // אתחול אירועים וסטטיסטיקות
    setupEventListeners();
    loadStats();
    
    // טען נתונים ראשוניים
    if (typeof loadAllCemeteries === 'function') {
        loadAllCemeteries();
    }
}

// הגדרת מאזינים לאירועים
function setupEventListeners() {
    // חיפוש בסרגל צד
    const searchInput = document.getElementById('sidebarSearch');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performQuickSearch(e.target.value);
            }, 300);
        });
    }
    
    // קיצורי מקלדת
    document.addEventListener('keydown', (e) => {
        // Ctrl/Cmd + N - הוספה חדשה
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            
            tableRenderer.openAddModal();
        }
        
        // Ctrl/Cmd + S - שמירה
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveCurrentItem();
        }
        
        // ESC - סגירת מודל
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

// טעינת סטטיסטיקות
async function loadStats() {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=stats`);
        const data = await response.json();
        
        if (data.success) {
            updateHeaderStats(data.stats);
            updateSidebarCounts(data.stats);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// עדכון סטטיסטיקות בכותרת
function updateHeaderStats(stats) {
    let totalGraves = 0;
    let available = 0;
    let reserved = 0;
    let occupied = 0;
    
    if (stats.grave_status) {
        stats.grave_status.forEach(status => {
            totalGraves += parseInt(status.count);
            switch(status.status) {
                case 1: available = status.count; break;
                case 2: reserved = status.count; break;
                case 3: occupied = status.count; break;
            }
        });
    }
    
    const headerTotal = document.getElementById('headerTotalGraves');
    const headerAvailable = document.getElementById('headerAvailableGraves');
    const headerReserved = document.getElementById('headerReservedGraves');
    const headerOccupied = document.getElementById('headerOccupiedGraves');
    
    if (headerTotal) headerTotal.textContent = totalGraves.toLocaleString();
    if (headerAvailable) headerAvailable.textContent = available.toLocaleString();
    if (headerReserved) headerReserved.textContent = reserved.toLocaleString();
    if (headerOccupied) headerOccupied.textContent = occupied.toLocaleString();
}

// עדכון ספירות בסרגל צד
function updateSidebarCounts(stats) {
    if (stats.counts) {
        updateSidebarCount('cemeteriesCount', stats.counts.cemeteries?.count || 0);
        updateSidebarCount('blocksCount', stats.counts.blocks?.count || 0);
        updateSidebarCount('plotsCount', stats.counts.plots?.count || 0);
        updateSidebarCount('areaGravesCount', stats.counts.area_graves?.count || 0);
        updateSidebarCount('gravesCount', stats.counts.graves?.count || 0);
    }
}

function updateSidebarCount(elementId, count) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = count;
    }
}

// עדכון פריט נבחר בסרגל צד
function updateSelectedItem(type, id) {
    // הסרת בחירה קודמת
    document.querySelectorAll('.hierarchy-item.selected').forEach(item => {
        item.classList.remove('selected');
    });
    
    // הוספת בחירה חדשה
    const item = document.querySelector(`.hierarchy-item[data-id="${id}"]`);
    if (item) {
        item.classList.add('selected');
    }
}

// עדכון טבלת נתונים
function updateTableData(type, data) {
    const tbody = document.getElementById('tableBody');
    const thead = document.getElementById('tableHeaders');
    
    if (!tbody || !thead) return;
    
    // עדכון כותרות
    thead.innerHTML = `
        <th>מזהה</th>
        <th>שם</th>
        <th>קוד</th>
        <th>סטטוס</th>
        <th>נוצר בתאריך</th>
        <th>פעולות</th>
    `;
    
    // עדכון נתונים
    tbody.innerHTML = '';
    
    data.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.id}</td>
            <td>${item.name || item.grave_number || '-'}</td>
            <td>${item.code || '-'}</td>
            <td><span class="badge badge-success">פעיל</span></td>
            <td>${formatDate(item.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editItem(${item.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteItem(${item.id})">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// פעולות החלפה בין רמות היררכיה
function toggleHierarchyLevel(level) {
    const list = document.getElementById(`${level}List`);
    if (list) {
        list.classList.toggle('collapsed');
    }
}

// החלפת סרגל צד
function toggleSidebar() {
    const sidebar = document.getElementById('dashboardSidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
    }
}

// מסך מלא
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// רענון כל הנתונים
async function refreshAllData() {
    console.log('Refreshing all data...');
    isLoading = true;
    
    await loadStats();
    
    // רענן לפי מה שקיים
    if (typeof loadAllCemeteries === 'function') {
        loadAllCemeteries();
    }
    
    isLoading = false;
    showSuccess('הנתונים עודכנו בהצלחה');
}

// חיפוש מהיר
async function performQuickSearch(query) {
    if (query.length < 2) return;
    
    console.log('Searching for:', query);
    // TODO: implement search
}

// // פתיחת מודל הוספה - עם בדיקת הקשר
// window.openAddModal = function() {
//     alert('test 1')
//     if (window.tableRenderer) {
//         window.tableRenderer.openAddModal();
//     } else {
//         console.error('TableRenderer not initialized');
//     }
// };

// // פתיחת טופס עם בחירת parent
// async function openAddWithParentSelection() {
//     const parentSelectionMap = {
//         'block': { type: 'cemetery', label: 'בחר בית עלמין' },
//         'plot': { type: 'block', label: 'בחר גוש' },
//         'row': { type: 'plot', label: 'בחר חלקה' },
//         'area_grave': { type: 'row', label: 'בחר שורה' },
//         'grave': { type: 'area_grave', label: 'בחר אחוזת קבר' }
//     };
    
//     const parentInfo = parentSelectionMap[window.currentType];
//     if (!parentInfo) {
//         showError('לא ניתן להוסיף רשומה ללא בחירת רשומת אב');
//         return;
//     }
    
//     // טען את רשימת ה-parents האפשריים
//     const parents = await loadParentsList(parentInfo.type);
//     if (!parents || parents.length === 0) {
//         showWarning(`אין ${parentInfo.label} במערכת`);
//         return;
//     }
    
//     // הצג מודל לבחירת parent
//     showParentSelectionModal(parents, parentInfo);
// }

// טעינת רשימת parents
async function loadParentsList(type) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=${type}`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
    } catch (error) {
        console.error('Error loading parents:', error);
    }
    return [];
}

// הצגת מודל לבחירת parent
function showParentSelectionModal(parents, parentInfo) {
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
        <h3>${parentInfo.label}</h3>
        <p>יש לבחור ${parentInfo.label} להוספת ${getTypeName(window.currentType)}:</p>
        <div style="margin: 20px 0;">
            <select id="parentSelect" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">-- בחר ${parentInfo.label} --</option>
                ${parents.map(parent => `
                    <option value="${parent.id}">${parent.name}</option>
                `).join('')}
            </select>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="this.closest('div[style*=fixed]').remove()" 
                    style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                ביטול
            </button>
            <button onclick="proceedWithParentSelection('${parentInfo.type}')" 
                    style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                המשך
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// המשך עם ה-parent שנבחר
window.proceedWithParentSelection = function(parentType) {
    const select = document.getElementById('parentSelect');
    const parentId = select.value;
    
    if (!parentId) {
        alert('יש לבחור ' + getTypeName(parentType));
        return;
    }
    
    // סגור את המודל
    select.closest('div[style*=fixed]').remove();
    
    // עדכן את ה-parent הנוכחי
    window.currentParentId = parentId;
    
    // פתח את הטופס
    FormHandler.openForm(window.currentType, parentId, null);
}

// בדיקה אם יש שורות בחלקה
async function checkIfPlotHasRows(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        if (data.success) {
            window.hasRowsInCurrentPlot = data.data && data.data.length > 0;
            return window.hasRowsInCurrentPlot;
        }
    } catch (error) {
        console.error('Error checking rows:', error);
    }
    return false;
}

// פונקציית עזר לקבלת שם הסוג
function getTypeName(type) {
    const typeNames = {
        'cemetery': 'בית עלמין',
        'block': 'גוש',
        'plot': 'חלקה',
        'row': 'שורה',
        'area_grave': 'אחוזת קבר',
        'grave': 'קבר'
    };
    return typeNames[type] || type;
}

// עדכון טקסט כפתור הוספה
function updateAddButtonText() {
    const buttonTexts = {
        'cemetery': 'הוספת בית עלמין',
        'block': 'הוספת גוש',
        'plot': 'הוספת חלקה',
        'row': 'הוספת שורה',
        'area_grave': 'הוספת אחוזת קבר',
        'grave': 'הוספת קבר',
        'customer': 'הוספת לקוח',
        'purchase': 'הוספת רכישה',
        'burial': 'הוספת קבורה',
        'residency': 'הוספת חוק תושבות',
        'payment': 'הוספת חוק תשלום'
    };
    
    // עדכן את הסלקטור לחפש את הפונקציה החדשה
    const buttons = document.querySelectorAll('.btn-primary[onclick="tableRenderer.openAddModal()"]');
    buttons.forEach(button => {
        const buttonText = buttonTexts[window.currentType] || 'הוסף';
        
        // בדיקה האם להציג או להסתיר את הכפתור
        if (shouldHideAddButton()) {
            button.style.display = 'none';
        } else if (shouldDisableAddButton()) {
            button.disabled = true;
            button.innerHTML = `<svg class="icon"><use xlink:href="#icon-plus"></use></svg> ${buttonText}`;
            button.style.opacity = '0.5';
            button.style.cursor = 'not-allowed';
        } else {
            button.style.display = '';
            button.disabled = false;
            button.innerHTML = `<svg class="icon"><use xlink:href="#icon-plus"></use></svg> ${buttonText}`;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
        }
    });
}

// בדיקה האם להסתיר את כפתור ההוספה
function shouldHideAddButton() {
    // הסתר כפתור הוספה באחוזות קבר וקברים כלליים (ללא parent)
    if ((window.currentType === 'areaGrave' || window.currentType === 'grave') && !window.currentParentId) {
        return true;
    }
    return false;
}

// בדיקה האם לבטל את כפתור ההוספה
function shouldDisableAddButton() {
    // אם אנחנו בחלקה ספציפית ורוצים להוסיף אחוזת קבר
    if (window.currentType === 'area_grave' && window.selectedItems.plot && !window.currentParentId) {
        // רק אם אנחנו בתצוגה כללית של החלקה (לא בחרנו שורה ספציפית)
        return !window.hasRowsInCurrentPlot;
    }
    return false;
}

// פונקציות עזר
function getHierarchyLevel(type) {
    const levels = {
        'cemetery': 'בית עלמין',
        'block': 'גוש',
        'plot': 'חלקה',
        'row': 'שורה',
        'area_grave': 'אחוזת קבר',
        'grave': 'קבר'
    };
    return levels[type] || type;
}

function getParentColumn(type) {
    const parents = {
        'block': 'cemetery_id',
        'plot': 'block_id',
        'row': 'plot_id',
        'area_grave': 'row_id',
        'grave': 'area_grave_id'
    };
    return parents[type] || null;
}

function getParentName(type) {
    const parents = {
        'block': 'בית עלמין',
        'plot': 'גוש',
        'row': 'חלקה',
        'area_grave': 'שורה',
        'grave': 'אחוזת קבר'
    };
    return parents[type] || '';
}

// פתיחת הוספה מהירה
function openQuickAdd() {
    console.log('Opening quick add');
    // TODO: implement quick add
}

// ייצוא נתונים
function exportData() {
    console.log('Exporting data');
    // TODO: implement export
}

// עריכת פריט
async function editItem(id) {
    console.log('Editing item:', id, 'Type:', window.currentType);
    
    // השתמש ב-tableRenderer לעריכה
    tableRenderer.editItem(id);
}

// מחיקת פריט
async function deleteItem(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק פריט זה?')) return;
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=delete&type=${currentType}&id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('הפריט נמחק בהצלחה');
            refreshAllData();
        } else {
            showError(data.error || 'שגיאה במחיקת הפריט');
        }
    } catch (error) {
        console.error('Error deleting item:', error);
        showError('שגיאה במחיקת הפריט');
    }
}

// סגירת כל המודלים
function closeAllModals() {
    document.querySelectorAll('.modal.show').forEach(modal => {
        modal.classList.remove('show');
    });
}

// שמירת הפריט הנוכחי (למקלדת)
function saveCurrentItem() {
    const saveBtn = document.querySelector('form button[type="submit"]');
    if (saveBtn) {
        saveBtn.click();
    }
}

// פונקציות הודעות
function showSuccess(message) {
    if (typeof showToast === 'function') {
        showToast('success', message);
    } else {
        console.log('Success:', message);
    }
}

function showError(message) {
    if (typeof showToast === 'function') {
        showToast('error', message);
    } else {
        console.error('Error:', message);
    }
}

function showWarning(message) {
    if (typeof showToast === 'function') {
        showToast('warning', message);
    } else {
        console.warn('Warning:', message);
    }
}

// עיצוב תאריך
function formatDate(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// ניהול הסידבר
function updateSidebarSelection(type, id, name) {
    console.log('updateSidebarSelection called:', type, id, name);
    
    // הסר את כל ה-active מהכותרות
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
    
    // הוסף active לכותרת הנוכחית
    const headers = {
        'cemetery': 0,
        'block': 1,
        'plot': 2,
        'areaGrave': 3,
        'grave': 4
    };
    
    const headerElements = document.querySelectorAll('.hierarchy-header');
    if (headerElements[headers[type]]) {
        headerElements[headers[type]].classList.add('active');
    }
    
    // נקה את כל הבחירות מתחת לרמה הנוכחית
    clearSidebarBelow(type);
    
    // הצג את הפריט הנבחר
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

// ניקוי כל הבחירות בסידבר
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

// ניקוי הסידבר מתחת לרמה מסוימת
function clearSidebarBelow(type) {
    const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
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

// ניקוי כרטיס פריט
function clearItemCard() {
    const cardContainer = document.getElementById('itemCard');
    if (cardContainer) {
        cardContainer.remove();
    }
}

// בחירת שורה בטבלה
function selectTableRow(row) {
    // הסר בחירה קודמת
    document.querySelectorAll('#tableBody tr.selected').forEach(tr => {
        tr.classList.remove('selected');
    });
    // הוסף בחירה לשורה הנוכחית
    row.classList.add('selected');
}

// רענון נתונים - כפתור הרענון ב-action bar
function refreshData() {
    console.log('Refreshing current view...');
    
    // רענן לפי הסוג הנוכחי
    switch(window.currentType) {
        case 'cemetery':
            if (typeof loadAllCemeteries === 'function') {
                loadAllCemeteries();
            }
            break;
        case 'block':
            if (window.selectedItems.cemetery) {
                loadBlocksForCemetery(window.selectedItems.cemetery.id);
            } else {
                loadAllBlocks();
            }
            break;
        case 'plot':
            if (window.selectedItems.block) {
                loadPlotsForBlock(window.selectedItems.block.id);
            } else {
                loadAllPlots();
            }
            break;
        case 'area_grave':
            if (window.selectedItems.plot) {
                loadAreaGravesForPlot(window.selectedItems.plot.id);
            } else {
                loadAllAreaGraves();
            }
            break;
        case 'grave':
            if (window.selectedItems.areaGrave) {
                loadGravesForAreaGrave(window.selectedItems.areaGrave.id);
            } else {
                loadAllGraves();
            }
            break;
    }
    
    showSuccess('הנתונים רועננו בהצלחה');
}

window.handleFormSubmit = function(event, type) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    console.log('Submitting form - Type:', type);
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    fetch('dashboard/dashboards/cemeteries/handlers/save-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message || 'הנתונים נשמרו בהצלחה');
            FormHandler.closeForm(type);
            
            // רענן את התצוגה הנוכחית
            tableRenderer.loadAndDisplay(window.currentType, window.currentParentId);
        } else {
            showError(data.error || 'שגיאה בשמירת הנתונים');
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showError('שגיאה בשמירת הנתונים');
    });
};

// הוסף את הפונקציה לייצוא
window.refreshData = refreshData;

// ייצוא פונקציות גלובליות
window.initDashboard = initDashboard;
window.refreshAllData = refreshAllData;
window.toggleSidebar = toggleSidebar;
window.toggleFullscreen = toggleFullscreen;
window.toggleHierarchyLevel = toggleHierarchyLevel;
window.performQuickSearch = performQuickSearch;
window.openAddModal = openAddModal;
window.openQuickAdd = openQuickAdd;
window.exportData = exportData;
window.editItem = editItem;
window.deleteItem = deleteItem;
window.getHierarchyLevel = getHierarchyLevel;
window.getParentColumn = getParentColumn;
window.getParentName = getParentName;
window.updateSidebarSelection = updateSidebarSelection;
window.clearAllSidebarSelections = clearAllSidebarSelections;
window.clearSidebarBelow = clearSidebarBelow;
window.updateSidebarCount = updateSidebarCount;
window.formatDate = formatDate;
window.showSuccess = showSuccess;
window.showError = showError;
window.showWarning = showWarning;
window.clearItemCard = clearItemCard;
window.selectTableRow = selectTableRow;
window.API_BASE = API_BASE;

// ייצוא משתנים גלובליים
window.currentType = currentType;
window.currentParentId = currentParentId;
window.selectedItems = selectedItems;