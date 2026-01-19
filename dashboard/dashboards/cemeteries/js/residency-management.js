/*
 * File: dashboards/dashboard/cemeteries/assets/js/residency-management.js
 * Version: 5.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v5.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('residency')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentResidencies = [];
let residencySearch = null;
let residenciesTable = null;
let editingResidencyId = null;

let residenciesIsSearchMode = false;
let residenciesCurrentQuery = '';
let residenciesSearchResults = [];

let residenciesCurrentPage = 1;
let residenciesTotalPages = 1;
let residenciesIsLoadingMore = false;


// ===================================================================
// קונפיגורציה של סוגי תושבות
// ===================================================================
const RESIDENCY_TYPES = {
    'jerusalem_area': 'תושבי ירושלים והסביבה',
    'israel': 'תושבי ישראל',
    'abroad': 'תושבי חו״ל'
};


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getResidencyTypeBadge(type) {
    const types = {
        'jerusalem_area': { label: 'ירושלים והסביבה', color: '#667eea' },
        'israel': { label: 'ישראל', color: '#3b82f6' },
        'abroad': { label: 'חו״ל', color: '#f59e0b' }
    };

    const typeInfo = types[type] || { label: type || '-', color: '#999' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getResidencyStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

// פתיחת טופס הוספת חוק תושבות
function openAddResidency() {
    window.currentType = 'residency';
    window.currentParentId = null;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('residency', null, null);
    }
}

// עריכת הגדרת תושבות
async function editResidency(id) {
    window.currentType = 'residency';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('residency', null, id);
    }
}

// צפייה בהגדרת תושבות - פתיחת כרטיס
async function viewResidency(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('residencyCard', null, id);
    } else {
        editResidency(id);
    }
}

// דאבל-קליק על שורת הגדרת תושבות
async function handleResidencyDoubleClick(residency) {
    const residencyId = typeof residency === 'object' ? residency.unicId : residency;

    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('residencyCard', null, residencyId);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

// רינדור עמודות מיוחדות - נקרא מ-EntityRenderer
function renderResidencyColumn(residency, column) {
    switch(column.field) {
        case 'residencyType':
            return getResidencyTypeBadge(residency.residencyType);
        case 'isActive':
            return getResidencyStatusBadge(residency.isActive);
        default:
            return null; // תן ל-EntityRenderer לטפל
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.residencySearch = residencySearch;
window.residenciesTable = residenciesTable;
window.currentResidencies = currentResidencies;

window.RESIDENCY_TYPES = RESIDENCY_TYPES;
window.getResidencyTypeBadge = getResidencyTypeBadge;
window.getResidencyStatusBadge = getResidencyStatusBadge;

window.openAddResidency = openAddResidency;
window.editResidency = editResidency;
window.viewResidency = viewResidency;
window.handleResidencyDoubleClick = handleResidencyDoubleClick;
window.renderResidencyColumn = renderResidencyColumn;
