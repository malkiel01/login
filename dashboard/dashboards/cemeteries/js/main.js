// dashboard/dashboards/cemeteries/js/main.js

// משתנים גלובליים
window.currentType = 'cemetery';
window.currentParentId = null;
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
            openAddModal();
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

// פתיחת מודל הוספה
function openAddModal2() {
    // שימוש במערכת הטפסים החדשה
    console.log('Opening form for:', currentType, 'Parent:', currentParentId);
    FormHandler.openForm(currentType, currentParentId, null);
}
// פתיחת מודל הוספה - עם טקסט דינמי
function openAddModal() {
    // קבע את טקסט הכפתור לפי הסוג
    const buttonTexts = {
        'cemetery': 'הוספת בית עלמין',
        'block': 'הוספת גוש',
        'plot': 'הוספת חלקה',
        'row': 'הוספת שורה',
        'area_grave': 'הוספת אחוזת קבר',
        'grave': 'הוספת קבר',
        'customer': 'הוספת לקוח',
        'purchase': 'הוספת רכישה',
        'burial': 'הוספת קבורה'
    };
    
    // עדכן את טקסט הכפתור אם קיים
    const addButton = document.querySelector('.btn-primary[onclick="openAddModal()"]');
    if (addButton) {
        const buttonText = buttonTexts[currentType] || 'הוסף';
        addButton.innerHTML = `<svg class="icon"><use xlink:href="#icon-plus"></use></svg> ${buttonText}`;
    }
    
    // בדיקה אם צריך parent
    const parentRequired = ['block', 'plot', 'row', 'area_grave', 'grave'].includes(currentType);
    
    if (parentRequired && !currentParentId) {
        showWarning('יש לבחור ' + getParentName(currentType) + ' תחילה');
        return;
    }
    
    // השתמש במערכת הטפסים החדשה
    console.log('Opening form for:', currentType, 'Parent:', currentParentId);
    FormHandler.openForm(currentType, currentParentId, null);
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
        'burial': 'הוספת קבורה'
    };
    
    const addButton = document.querySelector('.btn-primary[onclick="openAddModal()"]');
    if (addButton) {
        const buttonText = buttonTexts[window.currentType] || 'הוסף';
        addButton.innerHTML = `<svg class="icon"><use xlink:href="#icon-plus"></use></svg> ${buttonText}`;
    }
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
    console.log('Editing item:', id);
    // TODO: implement edit
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