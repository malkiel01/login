// dashboard/dashboards/cemeteries/js/main.js

// משתנים גלובליים
let currentType = 'cemetery';
let currentParentId = null;
let selectedItems = {};
let currentPage = 1;
let isLoading = false;

// הגדרות API - נתיב מלא מהשורש
const API_BASE = '/dashboard/dashboards/cemeteries/api/';

// אתחול הדשבורד
function initDashboard() {
    console.log('Initializing Cemetery Dashboard...');
    
    // טעינת נתונים ראשוניים
    loadCemeteries();
    loadStats();
    
    // הגדרת אירועים
    setupEventListeners();
    
    // רענון אוטומטי כל 5 דקות
    setInterval(() => {
        if (!isLoading) {
            refreshAllData();
        }
    }, 5 * 60 * 1000);
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
    
    document.getElementById('headerTotalGraves').textContent = totalGraves.toLocaleString();
    document.getElementById('headerAvailableGraves').textContent = available.toLocaleString();
    document.getElementById('headerReservedGraves').textContent = reserved.toLocaleString();
    document.getElementById('headerOccupiedGraves').textContent = occupied.toLocaleString();
}

// עדכון ספירות בסרגל צד
function updateSidebarCounts(stats) {
    if (stats.counts) {
        document.getElementById('cemeteriesCount').textContent = stats.counts.cemeteries?.count || 0;
        document.getElementById('blocksCount').textContent = stats.counts.blocks?.count || 0;
        document.getElementById('plotsCount').textContent = stats.counts.plots?.count || 0;
        document.getElementById('rowsCount').textContent = stats.counts.rows?.count || 0;
        document.getElementById('areaGravesCount').textContent = stats.counts.area_graves?.count || 0;
        document.getElementById('gravesCount').textContent = stats.counts.graves?.count || 0;
    }
}

// טעינת בתי עלמין
async function loadCemeteries() {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=cemetery`);
        const data = await response.json();
        
        if (data.success) {
            displayCemeteries(data.data);
            updateTableData('cemetery', data.data);
        }
    } catch (error) {
        console.error('Error loading cemeteries:', error);
        showError('שגיאה בטעינת בתי העלמין');
    }
}

// הצגת בתי עלמין בסרגל צד
function displayCemeteries(cemeteries) {
    const list = document.getElementById('cemeteriesList');
    if (!list) return;
    
    list.innerHTML = '';
    
    cemeteries.forEach(cemetery => {
        const item = document.createElement('div');
        item.className = 'hierarchy-item';
        item.dataset.id = cemetery.id;
        item.onclick = () => selectCemetery(cemetery.id, cemetery.name);
        
        item.innerHTML = `
            <span class="hierarchy-item-name">${cemetery.name}</span>
            <span class="hierarchy-item-badge">${cemetery.code || ''}</span>
        `;
        
        list.appendChild(item);
    });
}

// בחירת בית עלמין
async function selectCemetery(id, name) {
    selectedItems.cemetery = {id, name};
    currentType = 'block';
    currentParentId = id;
    
    // עדכון UI
    updateSelectedItem('cemetery', id);
    updateBreadcrumb();
    
    // טעינת גושים
    await loadBlocks(id);
}

// טעינת גושים
async function loadBlocks(cemeteryId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
        const data = await response.json();
        
        if (data.success) {
            displayBlocks(data.data);
            updateTableData('block', data.data);
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

// הצגת גושים
function displayBlocks(blocks) {
    const list = document.getElementById('blocksList');
    if (!list) return;
    
    list.innerHTML = '';
    list.classList.remove('collapsed');
    
    blocks.forEach(block => {
        const item = document.createElement('div');
        item.className = 'hierarchy-item';
        item.dataset.id = block.id;
        item.onclick = () => selectBlock(block.id, block.name);
        
        item.innerHTML = `
            <span class="hierarchy-item-name">${block.name}</span>
            <span class="hierarchy-item-badge">${block.code || ''}</span>
        `;
        
        list.appendChild(item);
    });
}

// בחירת גוש
async function selectBlock(id, name) {
    selectedItems.block = {id, name};
    currentType = 'plot';
    currentParentId = id;
    
    updateSelectedItem('block', id);
    updateBreadcrumb();
    
    await loadPlots(id);
}

// טעינת חלקות
async function loadPlots(blockId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}`);
        const data = await response.json();
        
        if (data.success) {
            displayPlots(data.data);
            updateTableData('plot', data.data);
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

// הצגת חלקות
function displayPlots(plots) {
    const list = document.getElementById('plotsList');
    if (!list) return;
    
    list.innerHTML = '';
    list.classList.remove('collapsed');
    
    plots.forEach(plot => {
        const item = document.createElement('div');
        item.className = 'hierarchy-item';
        item.dataset.id = plot.id;
        item.onclick = () => selectPlot(plot.id, plot.name);
        
        item.innerHTML = `
            <span class="hierarchy-item-name">${plot.name}</span>
            <span class="hierarchy-item-badge">${plot.code || ''}</span>
        `;
        
        list.appendChild(item);
    });
}

// בחירת חלקה
async function selectPlot(id, name) {
    selectedItems.plot = {id, name};
    currentType = 'row';
    currentParentId = id;
    
    updateSelectedItem('plot', id);
    updateBreadcrumb();
    
    await loadRows(id);
}

// טעינת שורות
async function loadRows(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        if (data.success) {
            displayRows(data.data);
            updateTableData('row', data.data);
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
}

// הצגת שורות
function displayRows(rows) {
    const list = document.getElementById('rowsList');
    if (!list) return;
    
    list.innerHTML = '';
    list.classList.remove('collapsed');
    
    rows.forEach(row => {
        const item = document.createElement('div');
        item.className = 'hierarchy-item';
        item.dataset.id = row.id;
        item.onclick = () => selectRow(row.id, row.name);
        
        item.innerHTML = `
            <span class="hierarchy-item-name">${row.name}</span>
            <span class="hierarchy-item-badge">${row.code || ''}</span>
        `;
        
        list.appendChild(item);
    });
}

// בחירת שורה
async function selectRow(id, name) {
    selectedItems.row = {id, name};
    currentType = 'area_grave';
    currentParentId = id;
    
    updateSelectedItem('row', id);
    updateBreadcrumb();
    
    await loadAreaGraves(id);
}

// טעינת אחוזות קבר
async function loadAreaGraves(rowId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
        const data = await response.json();
        
        if (data.success) {
            displayAreaGraves(data.data);
            updateTableData('area_grave', data.data);
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
    }
}

// הצגת אחוזות קבר
function displayAreaGraves(areaGraves) {
    const list = document.getElementById('areaGravesList');
    if (!list) return;
    
    list.innerHTML = '';
    list.classList.remove('collapsed');
    
    areaGraves.forEach(area => {
        const item = document.createElement('div');
        item.className = 'hierarchy-item';
        item.dataset.id = area.id;
        item.onclick = () => selectAreaGrave(area.id, area.name);
        
        item.innerHTML = `
            <span class="hierarchy-item-name">${area.name}</span>
            <span class="hierarchy-item-badge">${area.grave_type || ''}</span>
        `;
        
        list.appendChild(item);
    });
}

// בחירת אחוזת קבר
async function selectAreaGrave(id, name) {
    selectedItems.areaGrave = {id, name};
    currentType = 'grave';
    currentParentId = id;
    
    updateSelectedItem('area_grave', id);
    updateBreadcrumb();
    
    await loadGraves(id);
}

// טעינת קברים
async function loadGraves(areaGraveId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
        const data = await response.json();
        
        if (data.success) {
            displayGraves(data.data);
            updateTableData('grave', data.data);
        }
    } catch (error) {
        console.error('Error loading graves:', error);
    }
}

// הצגת קברים
function displayGraves(graves) {
    const list = document.getElementById('gravesList');
    if (!list) return;
    
    list.innerHTML = '';
    list.classList.remove('collapsed');
    
    graves.forEach(grave => {
        const item = document.createElement('div');
        item.className = 'hierarchy-item';
        item.dataset.id = grave.id;
        
        item.innerHTML = `
            <span class="hierarchy-item-name">קבר ${grave.grave_number}</span>
            <span class="hierarchy-item-badge">${grave.grave_status}</span>
        `;
        
        list.appendChild(item);
    });
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

// עדכון שביל פירורים (Breadcrumb)
function updateBreadcrumb() {
    const breadcrumb = document.getElementById('breadcrumb');
    if (!breadcrumb) return;
    
    let path = [];
    
    if (selectedItems.cemetery) {
        path.push(`<span class="breadcrumb-item">🏛️ ${selectedItems.cemetery.name}</span>`);
    }
    if (selectedItems.block) {
        path.push(`<span class="breadcrumb-separator">›</span>`);
        path.push(`<span class="breadcrumb-item">📦 ${selectedItems.block.name}</span>`);
    }
    if (selectedItems.plot) {
        path.push(`<span class="breadcrumb-separator">›</span>`);
        path.push(`<span class="breadcrumb-item">📋 ${selectedItems.plot.name}</span>`);
    }
    if (selectedItems.row) {
        path.push(`<span class="breadcrumb-separator">›</span>`);
        path.push(`<span class="breadcrumb-item">📏 ${selectedItems.row.name}</span>`);
    }
    if (selectedItems.areaGrave) {
        path.push(`<span class="breadcrumb-separator">›</span>`);
        path.push(`<span class="breadcrumb-item">🏘️ ${selectedItems.areaGrave.name}</span>`);
    }
    
    breadcrumb.innerHTML = path.length > 0 ? path.join('') : '<span class="breadcrumb-item">ראשי</span>';
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
    await loadCemeteries();
    
    if (selectedItems.cemetery) {
        await loadBlocks(selectedItems.cemetery.id);
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

// תקן את openAddModal (במקום השורה 540)
function openAddModal() {
    console.log('Opening add modal for type:', currentType);
    
    // בדיקה שיש הורה אם צריך
    if (currentType !== 'cemetery' && !currentParentId) {
        showWarning('יש לבחור ' + getParentName(currentType) + ' תחילה');
        return;
    }
    
    // בדיקה אם פונקציית המודל קיימת
    if (typeof window.openModal === 'function') {
        window.openModal(currentType, currentParentId, null);
    } else {
        // אם המודל לא נטען, הצג טופס פשוט
        createSimpleAddForm();
    }
}

// הוסף פונקציה חדשה לטופס פשוט
function createSimpleAddForm() {
    const existingForm = document.getElementById('simpleAddForm');
    if (existingForm) existingForm.remove();
    
    const formHtml = `
        <div id="simpleAddForm" style="
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
        ">
            <h3>הוסף ${getHierarchyLevel(currentType)}</h3>
            <form onsubmit="submitSimpleForm(event)">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">שם:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">קוד:</label>
                    <input type="text" name="code" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                ${currentType === 'cemetery' ? `
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">כתובת:</label>
                    <input type="text" name="address" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                ` : ''}
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
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', formHtml);
}

// הוסף פונקציה לשליחת הטופס
window.submitSimpleForm = async function(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    const data = {
        name: formData.get('name'),
        code: formData.get('code'),
        is_active: 1
    };
    
    if (formData.get('address')) {
        data.address = formData.get('address');
    }
    
    // הוסף parent_id אם צריך
    const parentColumn = getParentColumn(currentType);
    if (parentColumn && currentParentId) {
        data[parentColumn] = currentParentId;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=create&type=${currentType}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('simpleAddForm').remove();
            showSuccess('נוסף בהצלחה!');
            refreshAllData();
        } else {
            alert('שגיאה: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        alert('שגיאה בשמירה');
        console.error(error);
    }
}

// הוסף את הפונקציות העזר
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

// פונקציות עזר להודעות
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
window.selectCemetery = selectCemetery;
window.selectBlock = selectBlock;
window.selectPlot = selectPlot;
window.getHierarchyLevel = getHierarchyLevel;
window.getParentColumn = getParentColumn;