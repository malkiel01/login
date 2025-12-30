/*
 * File: dashboards/dashboard/cemeteries/assets/js/cemeteries-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('cemetery')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCemeteries = [];
let cemeterySearch = null;
let cemeteriesTable = null;
let editingCemeteryId = null;

let cemeteriesIsSearchMode = false;
let cemeteriesCurrentQuery = '';
let cemeteriesSearchResults = [];

let cemeteriesCurrentPage = 1;
let cemeriesTotalPages = 1;
let cemeteriesIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getCemeteryStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getBlocksCountBadge(count) {
    const color = '#3b82f6';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${count || 0}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

// פתיחת טופס הוספת בית עלמין
function openAddCemetery() {
    window.currentType = 'cemetery';
    window.currentParentId = null;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, null);
    }
}

// עריכת בית עלמין
async function editCemetery(id) {
    window.currentType = 'cemetery';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, id);
    }
}

// צפייה בבית עלמין - פתיחת כרטיס
async function viewCemetery(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemeteryCard', null, id);
    } else {
        editCemetery(id);
    }
}

// דאבל-קליק על שורת בית עלמין - מעבר לגושים
async function handleCemeteryDoubleClick(cemetery) {
    // 🔍 דיבאג - לראות מה יש באובייקט
    console.log('🔍 handleCemeteryDoubleClick - cemetery object:', cemetery);
    console.log('🔍 Available keys:', typeof cemetery === 'object' ? Object.keys(cemetery) : 'not an object');

    // תמיכה גם באובייקט וגם ב-ID ישיר
    const cemeteryId = typeof cemetery === 'object'
        ? (cemetery.unicId || cemetery.id)
        : cemetery;

    // קבלת השם - בדיקת מספר שדות אפשריים
    let cemeteryName = null;
    if (typeof cemetery === 'object') {
        cemeteryName = cemetery.cemeteryNameHe
                    || cemetery.cemeteryName
                    || cemetery.name
                    || null;
        console.log('🔍 Tried fields - cemeteryNameHe:', cemetery.cemeteryNameHe, 'cemeteryName:', cemetery.cemeteryName, 'name:', cemetery.name);
    }
    // fallback אם לא נמצא שם
    if (!cemeteryName) {
        cemeteryName = `בית עלמין #${cemeteryId}`;
    }
    console.log('🔍 Final cemeteryName:', cemeteryName);

    // שמירה ב-selectedItems לניווט
    if (!window.selectedItems) {
        window.selectedItems = {};
    }
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };

    // מעבר לגושים של בית העלמין
    if (typeof loadBlocks === 'function') {
        loadBlocks(cemeteryId, cemeteryName);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

// רינדור עמודות מיוחדות - נקרא מ-EntityRenderer
function renderCemeteryColumn(cemetery, column) {
    switch(column.field) {
        case 'isActive':
            return getCemeteryStatusBadge(cemetery.isActive);
        case 'blocks_count':
            return getBlocksCountBadge(cemetery.blocks_count);
        default:
            return null; // תן ל-EntityRenderer לטפל
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.cemeterySearch = cemeterySearch;
window.cemeteriesTable = cemeteriesTable;
window.currentCemeteries = currentCemeteries;

window.getCemeteryStatusBadge = getCemeteryStatusBadge;
window.getBlocksCountBadge = getBlocksCountBadge;

window.openAddCemetery = openAddCemetery;
window.editCemetery = editCemetery;
window.viewCemetery = viewCemetery;
window.handleCemeteryDoubleClick = handleCemeteryDoubleClick;
window.renderCemeteryColumn = renderCemeteryColumn;
