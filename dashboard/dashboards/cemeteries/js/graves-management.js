/*
 * File: dashboards/dashboard/cemeteries/assets/js/graves-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('grave', areaGraveId)
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ תמיכה בסינון לפי אחוזת קבר (parent)
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentGraves = [];
let graveSearch = null;
let gravesTable = null;
let editingGraveId = null;

let gravesIsSearchMode = false;
let gravesCurrentQuery = '';
let gravesSearchResults = [];

let gravesCurrentPage = 1;
let gravesTotalPages = 1;
let gravesIsLoadingMore = false;

// שמירת ה-areaGrave context הנוכחי
let gravesFilterAreaGraveId = null;
let gravesFilterAreaGraveName = null;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getGraveStatusBadge(status) {
    const statuses = {
        'available': { label: 'פנוי', color: '#10b981' },
        'reserved': { label: 'שמור', color: '#f59e0b' },
        'occupied': { label: 'תפוס', color: '#ef4444' },
        'maintenance': { label: 'בתחזוקה', color: '#6b7280' }
    };
    const statusInfo = statuses[status] || { label: status || '-', color: '#6b7280' };
    return `<span style="background: ${statusInfo.color}20; color: ${statusInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${statusInfo.label}</span>`;
}

function getGraveTypeBadge(graveType) {
    const types = {
        'single': { label: 'יחיד', color: '#3b82f6' },
        'double': { label: 'זוגי', color: '#8b5cf6' }
    };
    const typeInfo = types[graveType] || { label: graveType || '-', color: '#6b7280' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getAreaGraveBadge(areaGraveName) {
    const color = '#f59e0b';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${areaGraveName || '-'}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

function openAddGrave(areaGraveId = null) {
    window.currentType = 'grave';
    window.currentParentId = areaGraveId || gravesFilterAreaGraveId;

    const parentId = areaGraveId || gravesFilterAreaGraveId;
    const formUrl = `/dashboard/dashboards/cemeteries/forms/iframe/graveForm-iframe.php${parentId ? '?parentId=' + parentId : ''}`;

    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            title: 'הוספת קבר חדש',
            type: 'iframe',
            src: formUrl,
            width: 900,
            height: 700
        });
    }
}

async function editGrave(id) {
    window.currentType = 'grave';

    const formUrl = `/dashboard/dashboards/cemeteries/forms/iframe/graveForm-iframe.php?itemId=${id}`;

    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            title: 'עריכת קבר',
            type: 'iframe',
            src: formUrl,
            width: 900,
            height: 700
        });
    }
}

async function viewGrave(id) {
    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            id: 'graveCard-' + id,
            type: 'iframe',
            src: '/dashboard/dashboards/cemeteries/forms/iframe/graveCard-iframe.php?itemId=' + id,
            title: 'כרטיס קבר',
            width: 1200,
            height: 700
        });
    }
}

// דאבל-קליק על שורת קבר - פתיחת כרטיס
async function handleGraveDoubleClick(grave) {
    const graveId = typeof grave === 'object' ? grave.unicId : grave;
    viewGrave(graveId);
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderGraveColumn(grave, column) {
    switch(column.field) {
        case 'status':
            return getGraveStatusBadge(grave.status);
        case 'graveType':
            return getGraveTypeBadge(grave.graveType);
        case 'areaGraveName':
            return getAreaGraveBadge(grave.areaGraveName);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.graveSearch = graveSearch;
window.gravesTable = gravesTable;
window.currentGraves = currentGraves;
window.gravesFilterAreaGraveId = gravesFilterAreaGraveId;
window.gravesFilterAreaGraveName = gravesFilterAreaGraveName;

window.getGraveStatusBadge = getGraveStatusBadge;
window.getGraveTypeBadge = getGraveTypeBadge;
window.getAreaGraveBadge = getAreaGraveBadge;

window.openAddGrave = openAddGrave;
window.editGrave = editGrave;
window.viewGrave = viewGrave;
window.handleGraveDoubleClick = handleGraveDoubleClick;
window.renderGraveColumn = renderGraveColumn;
