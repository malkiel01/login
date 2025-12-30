/*
 * File: dashboards/dashboard/cemeteries/assets/js/area-graves-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('areaGrave', plotId)
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ תמיכה בסינון לפי חלקה (parent)
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentAreaGraves = [];
let areaGraveSearch = null;
let areaGravesTable = null;
let editingAreaGraveId = null;

let areaGravesIsSearchMode = false;
let areaGravesCurrentQuery = '';
let areaGravesSearchResults = [];

let areaGravesCurrentPage = 1;
let areaGravesTotalPages = 1;
let areaGravesIsLoadingMore = false;

// שמירת ה-plot context הנוכחי
let areaGravesFilterPlotId = null;
let areaGravesFilterPlotName = null;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getAreaGraveStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getAreaGraveTypeBadge(areaGraveType) {
    const types = {
        'single': { label: 'יחיד', color: '#3b82f6' },
        'double': { label: 'זוגי', color: '#8b5cf6' },
        'family': { label: 'משפחתי', color: '#f59e0b' }
    };
    const typeInfo = types[areaGraveType] || { label: areaGraveType || '-', color: '#6b7280' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getGravesCountBadge(count) {
    const color = '#8e2de2';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${count || 0}</span>`;
}

function getPlotBadge(plotName) {
    const color = '#10b981';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${plotName || '-'}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

function openAddAreaGrave(plotId = null) {
    window.currentType = 'areaGrave';
    window.currentParentId = plotId || areaGravesFilterPlotId;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('areaGrave', plotId || areaGravesFilterPlotId, null);
    }
}

async function editAreaGrave(id) {
    window.currentType = 'areaGrave';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('areaGrave', null, id);
    }
}

async function viewAreaGrave(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('areaGraveCard', null, id);
    } else {
        editAreaGrave(id);
    }
}

// דאבל-קליק על שורת אחוזת קבר - מעבר לקברים
async function handleAreaGraveDoubleClick(areaGrave) {
    const areaGraveId = typeof areaGrave === 'object'
        ? (areaGrave.unicId || areaGrave.id)
        : areaGrave;

    // קבלת השם - בדיקת מספר שדות אפשריים
    let areaGraveName = null;
    if (typeof areaGrave === 'object') {
        areaGraveName = areaGrave.areaGraveNameHe
                     || areaGrave.areaGraveName
                     || areaGrave.name
                     || null;
    }
    // fallback אם לא נמצא שם
    if (!areaGraveName) {
        areaGraveName = `אחוזת קבר #${areaGraveId}`;
    }

    // שמירה ב-selectedItems לניווט
    if (!window.selectedItems) {
        window.selectedItems = {};
    }
    window.selectedItems.areaGrave = { id: areaGraveId, name: areaGraveName };

    if (typeof loadGraves === 'function') {
        loadGraves(areaGraveId, areaGraveName);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderAreaGraveColumn(areaGrave, column) {
    switch(column.field) {
        case 'isActive':
            return getAreaGraveStatusBadge(areaGrave.isActive);
        case 'areaGraveType':
            return getAreaGraveTypeBadge(areaGrave.areaGraveType);
        case 'graves_count':
            return getGravesCountBadge(areaGrave.graves_count);
        case 'plotName':
            return getPlotBadge(areaGrave.plotName);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.areaGraveSearch = areaGraveSearch;
window.areaGravesTable = areaGravesTable;
window.currentAreaGraves = currentAreaGraves;
window.areaGravesFilterPlotId = areaGravesFilterPlotId;
window.areaGravesFilterPlotName = areaGravesFilterPlotName;

window.getAreaGraveStatusBadge = getAreaGraveStatusBadge;
window.getAreaGraveTypeBadge = getAreaGraveTypeBadge;
window.getGravesCountBadge = getGravesCountBadge;
window.getPlotBadge = getPlotBadge;

window.openAddAreaGrave = openAddAreaGrave;
window.editAreaGrave = editAreaGrave;
window.viewAreaGrave = viewAreaGrave;
window.handleAreaGraveDoubleClick = handleAreaGraveDoubleClick;
window.renderAreaGraveColumn = renderAreaGraveColumn;
