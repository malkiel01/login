/*
 * File: dashboards/dashboard/cemeteries/assets/js/burials-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('burial')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentBurials = [];
let burialSearch = null;
let burialsTable = null;
let editingBurialId = null;

let burialsIsSearchMode = false;
let burialsCurrentQuery = '';
let burialsSearchResults = [];

let burialsCurrentPage = 1;
let burialsTotalPages = 1;
let burialsIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getBurialStatusBadge(status) {
    const statuses = {
        'pending': { label: 'ממתין', color: '#f59e0b' },
        'scheduled': { label: 'מתוכנן', color: '#3b82f6' },
        'completed': { label: 'הושלם', color: '#10b981' },
        'cancelled': { label: 'בוטל', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || { label: status || '-', color: '#6b7280' };
    return `<span style="background: ${statusInfo.color}20; color: ${statusInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${statusInfo.label}</span>`;
}

function getBurialTypeBadge(burialType) {
    const types = {
        'regular': { label: 'רגילה', color: '#3b82f6' },
        'military': { label: 'צבאית', color: '#22c55e' },
        'religious': { label: 'דתית', color: '#8b5cf6' }
    };
    const typeInfo = types[burialType] || { label: burialType || '-', color: '#6b7280' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getDeceasedBadge(deceasedName) {
    const color = '#64748b';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${deceasedName || '-'}</span>`;
}

function getGraveBadge(graveName) {
    const color = '#8e2de2';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${graveName || '-'}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

function openAddBurial() {
    window.currentType = 'burial';
    window.currentParentId = null;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('burial', null, null);
    }
}

async function editBurial(id) {
    window.currentType = 'burial';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('burial', null, id);
    }
}

async function viewBurial(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('burialCard', null, id);
    } else {
        editBurial(id);
    }
}

// דאבל-קליק על שורת קבורה - פתיחת כרטיס
async function handleBurialDoubleClick(burial) {
    const burialId = typeof burial === 'object' ? (burial.id || burial.unicId) : burial;

    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('burialCard', null, burialId);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderBurialColumn(burial, column) {
    switch(column.field) {
        case 'status':
            return getBurialStatusBadge(burial.status);
        case 'burialType':
            return getBurialTypeBadge(burial.burialType);
        case 'deceasedName':
            return getDeceasedBadge(burial.deceasedName);
        case 'graveName':
            return getGraveBadge(burial.graveName);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.burialSearch = burialSearch;
window.burialsTable = burialsTable;
window.currentBurials = currentBurials;

window.getBurialStatusBadge = getBurialStatusBadge;
window.getBurialTypeBadge = getBurialTypeBadge;
window.getDeceasedBadge = getDeceasedBadge;
window.getGraveBadge = getGraveBadge;

window.openAddBurial = openAddBurial;
window.editBurial = editBurial;
window.viewBurial = viewBurial;
window.handleBurialDoubleClick = handleBurialDoubleClick;
window.renderBurialColumn = renderBurialColumn;
