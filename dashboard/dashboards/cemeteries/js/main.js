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
async function initDashboard() {

    // אתחול משתנים גלובליים
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = window.selectedItems || {};

    // אתחול אירועים וסטטיסטיקות
    setupEventListeners();
    loadStats();

    // ⭐ המתן לטעינת הקונפיג לפני טעינת נתונים
    if (typeof initEntityConfig === 'function') {
        await initEntityConfig();
    }

    // טען נתונים ראשוניים - מצא את המודול הראשון שיש למשתמש הרשאה אליו
    loadFirstAvailableModule();
}

/**
 * טוען את המודול הראשון שיש למשתמש הרשאה אליו
 * סדר העדיפויות: בתי עלמין, גושים, חלקות, אחוזות קבר, קברים, לקוחות, רכישות, קבורות
 */
function loadFirstAvailableModule() {
    // רשימת מודולים בסדר עדיפות
    const moduleOrder = [
        { module: 'cemeteries', loader: 'loadCemeteries', sidebarItem: 'cemeteriesItem' },
        { module: 'blocks', loader: 'loadBlocks', sidebarItem: 'blocksItem' },
        { module: 'plots', loader: 'loadPlots', sidebarItem: 'plotsItem' },
        { module: 'areaGraves', loader: 'loadAreaGraves', sidebarItem: 'areaGravesItem' },
        { module: 'graves', loader: 'loadGraves', sidebarItem: 'gravesItem' },
        { module: 'customers', loader: 'loadCustomers', sidebarItem: 'customersItem' },
        { module: 'purchases', loader: 'loadPurchases', sidebarItem: 'purchasesItem' },
        { module: 'burials', loader: 'loadBurials', sidebarItem: 'burialsItem' },
        { module: 'payments', loader: 'loadPayments', sidebarItem: 'paymentsItem' }
    ];

    // מצא את המודול הראשון שיש למשתמש הרשאה אליו
    for (const { module, loader, sidebarItem } of moduleOrder) {
        if (window.canView && window.canView(module)) {
            const loaderFunc = window[loader];
            if (typeof loaderFunc === 'function') {
                console.log(`📍 טוען מודול ראשון זמין: ${module}`);
                loaderFunc();

                // סמן את הפריט בסיידבר כפעיל
                if (typeof setActiveSidebarItem === 'function') {
                    setActiveSidebarItem(sidebarItem);
                }
                return;
            }
        }
    }

    console.log('⚠️ אין למשתמש הרשאה לאף מודול');
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
        
        // 👇 בדיקה!

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
        updateSidebarCount('areaGravesCount', stats.counts.areaGraves?.count || 0);
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
    isLoading = true;
    
    await loadStats();
    
    // רענן לפי מה שקיים
    if (typeof loadCemeteries === 'function') {
        loadCemeteries();
    }
    
    isLoading = false;
    showSuccess('הנתונים עודכנו בהצלחה');
}

// חיפוש מהיר
async function performQuickSearch(query) {
    if (query.length < 2) return;
    
    // TODO: implement search
}

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

    // פתח את הטופס באמצעות פונקציות ייעודיות
    const type = window.currentType;
    if (type === 'block' && typeof openAddBlock === 'function') {
        openAddBlock(parentId);
    } else if (type === 'plot' && typeof openAddPlot === 'function') {
        openAddPlot(parentId);
    } else if (type === 'city' && typeof openAddCity === 'function') {
        openAddCity(parentId);
    } else if (type === 'areaGrave' && typeof openAddAreaGrave === 'function') {
        openAddAreaGrave(parentId);
    } else if (type === 'grave' && typeof openAddGrave === 'function') {
        openAddGrave(parentId);
    } else {
        console.warn('No popup function found for type:', type);
    }
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
        'areaGrave': 'אחוזת קבר',
        'grave': 'קבר'
    };
    return typeNames[type] || type;
}

// מיפוי סוגים למודולי הרשאות
const typeToModuleMap = {
    'cemetery': 'cemeteries',
    'block': 'blocks',
    'plot': 'plots',
    'row': 'rows',
    'areaGrave': 'areaGraves',
    'grave': 'graves',
    'customer': 'customers',
    'purchase': 'purchases',
    'burial': 'burials',
    'residency': 'residency',
    'payment': 'payments',
    'country': 'countries',
    'city': 'cities',
    'user': 'users',
    'role': 'roles',
    'map': 'map',
    'report': 'reports'
};

// קבלת שם המודול מסוג הרשומה
function getModuleForType(type) {
    return typeToModuleMap[type] || type;
}

// בדיקה אם יש הרשאת יצירה למודול הנוכחי
function canCreate(type) {
    if (!type) type = window.currentType;
    const module = getModuleForType(type);
    return window.hasPermission ? window.hasPermission(module, 'create') : true;
}

// בדיקה אם יש הרשאת עריכה למודול הנוכחי
function canEdit(type) {
    if (!type) type = window.currentType;
    const module = getModuleForType(type);
    return window.hasPermission ? window.hasPermission(module, 'edit') : true;
}

// בדיקה אם יש הרשאת מחיקה למודול הנוכחי
function canDelete(type) {
    if (!type) type = window.currentType;
    const module = getModuleForType(type);
    return window.hasPermission ? window.hasPermission(module, 'delete') : true;
}

// עדכון טקסט כפתור הוספה
function updateAddButtonText() {
    const buttonTexts = {
        'cemetery': 'הוספת בית עלמין',
        'block': 'הוספת גוש',
        'plot': 'הוספת חלקה',
        'row': 'הוספת שורה',
        'areaGrave': 'הוספת אחוזת קבר',
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

        // בדיקה האם להציג או להסתיר את הכפתור - כולל בדיקת הרשאת יצירה
        if (shouldHideAddButton() || !canCreate()) {
            button.style.display = 'none';
        } else if (shouldDisableAddButton()) {
            button.disabled = true;
            button.innerHTML = `<svg class="icon"><use xlink:href="#icon-plus"></use></svg><span>${buttonText}</span>`;
            button.style.opacity = '0.5';
            button.style.cursor = 'not-allowed';
        } else {
            button.style.display = '';
            button.disabled = false;
            button.innerHTML = `<svg class="icon"><use xlink:href="#icon-plus"></use></svg><span>${buttonText}</span>`;
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
    if (window.currentType === 'areaGrave' && window.selectedItems.plot && !window.currentParentId) {
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
        'areaGrave': 'אחוזת קבר',
        'grave': 'קבר'
    };
    return levels[type] || type;
}

function getParentColumn(type) {
    const parents = {
        'block': 'cemetery_id',
        'plot': 'block_id',
        'row': 'plot_id',
        'areaGrave': 'row_id',
        'grave': 'area_grave_id'
    };
    return parents[type] || null;
}

function getParentName(type) {
    const parents = {
        'block': 'בית עלמין',
        'plot': 'גוש',
        'row': 'חלקה',
        'areaGrave': 'שורה',
        'grave': 'אחוזת קבר'
    };
    return parents[type] || '';
}

// פתיחת הוספה מהירה
function openQuickAdd() {
    // TODO: implement quick add
}

// ייצוא נתונים
function exportData() {
    // TODO: implement export
}

// עריכת פריט
async function editItem(id) {
    
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
    
    // רענן לפי הסוג הנוכחי
    switch(window.currentType) {
        case 'cemetery':
            if (typeof loadCemeteries === 'function') {
                loadCemeteries();
            }
            break;
        case 'block':
            if (window.selectedItems.cemetery) {
                loadBlocksForCemetery(window.selectedItems.cemetery.id);
            } else {
                loadBlocks();
            }
            break;
        case 'plot':
            if (window.selectedItems.block) {
                loadPlotsForBlock(window.selectedItems.block.id);
            } else {
                loadPlots();
            }
            break;
        case 'areaGrave':
            if (window.selectedItems.plot) {
                loadAreaGravesForPlot(window.selectedItems.plot.id);
            } else {
                loadAreaGraves();
            }
            break;
        case 'grave':
            if (window.selectedItems.areaGrave) {
                loadGravesForAreaGrave(window.selectedItems.areaGrave.id);
            } else {
                loadGraves();
            }
            break;
    }
    
    showSuccess('הנתונים רועננו בהצלחה');
}

window.handleFormSubmit = function(event, type) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    for (let [key, value] of formData.entries()) {
    }
    
    fetch('dashboard/dashboards/cemeteries/handlers/save-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message || 'הנתונים נשמרו בהצלחה');
            // סגור את הפופאפ הפעיל
            if (typeof PopupManager !== 'undefined' && PopupManager.closeActive) {
                PopupManager.closeActive();
            }

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

// טעינת Popup Manager Demo
function loadPopupDemo() {
    // עדכן את הסוג הנוכחי
    window.currentType = 'popup-demo';

    // נקה את האזור המרכזי
    const mainContent = document.querySelector('.main-content');
    if (!mainContent) {
        console.error('❌ לא נמצא אלמנט main-content');
        showError('שגיאה בטעינת הדמו');
        return;
    }

    console.log('✅ נמצא אלמנט main-content, טוען דמו...');

    // הצג iframe עם הדמו
    mainContent.innerHTML = `
        <div style="width: 100%; height: calc(100vh - 120px); display: flex; flex-direction: column;">
            <div style="padding: 20px; background: white; border-bottom: 2px solid #e5e7eb;">
                <h2 style="margin: 0; color: #667eea; display: flex; align-items: center; gap: 10px;">
                    <span>🎯</span>
                    <span>Popup Manager - Demo & Documentation</span>
                </h2>
                <p style="margin: 10px 0 0 0; color: #64748b;">
                    מודול פופ-אפ גנרי לחלוטין עם תקשורת דו-כיוונית
                </p>
            </div>
            <iframe
                src="/dashboard/dashboards/cemeteries/popup/demo.html"
                style="flex: 1; border: none; width: 100%; min-height: 600px;"
                frameborder="0">
            </iframe>
        </div>
    `;

    showSuccess('דמו טעון בהצלחה');
}

// הוסף את הפונקציה לייצוא
window.refreshData = refreshData;
window.loadPopupDemo = loadPopupDemo;

// ייצוא פונקציות גלובליות
window.initDashboard = initDashboard;
window.refreshAllData = refreshAllData;
window.toggleSidebar = toggleSidebar;
window.toggleFullscreen = toggleFullscreen;
window.toggleHierarchyLevel = toggleHierarchyLevel;
window.performQuickSearch = performQuickSearch;
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

// ייצוא פונקציות הרשאות
window.getModuleForType = getModuleForType;
window.canCreate = canCreate;
window.canEdit = canEdit;
window.canDelete = canDelete;
window.typeToModuleMap = typeToModuleMap;