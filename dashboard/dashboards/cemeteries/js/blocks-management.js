/*
 * File: dashboards/dashboard/cemeteries/assets/js/blocks-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('block', cemeteryId)
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ תמיכה בסינון לפי בית עלמין (parent)
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentBlocks = [];
let blockSearch = null;
let blocksTable = null;
let editingBlockId = null;

let blocksIsSearchMode = false;
let blocksCurrentQuery = '';
let blocksSearchResults = [];

let blocksCurrentPage = 1;
let blocksTotalPages = 1;
let blocksIsLoadingMore = false;

// שמירת ה-cemetery context הנוכחי
let currentCemeteryId = null;
let currentCemeteryName = null;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getBlockStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getPlotsCountBadge(count) {
    const color = '#10b981';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${count || 0}</span>`;
}

function getCemeteryBadge(cemeteryName) {
    const color = '#8b5cf6';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${cemeteryName || '-'}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

// פתיחת טופס הוספת גוש
function openAddBlock(cemeteryId = null) {
    window.currentType = 'block';
    window.currentParentId = cemeteryId || currentCemeteryId;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('block', cemeteryId || currentCemeteryId, null);
    }
}

// עריכת גוש
async function editBlock(id) {
    window.currentType = 'block';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('block', null, id);
    }
}

// צפייה בגוש - פתיחת כרטיס
async function viewBlock(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('blockCard', null, id);
    } else {
        editBlock(id);
    }
}

// דאבל-קליק על שורת גוש - מעבר לחלקות
async function handleBlockDoubleClick(block) {
    // תמיכה גם באובייקט וגם ב-ID ישיר
    const blockId = typeof block === 'object'
        ? (block.unicId || block.id)
        : block;

    // קבלת השם מכל השדות האפשריים
    const blockName = typeof block === 'object'
        ? (block.blockNameHe || block.blockName || block.name || `גוש #${blockId}`)
        : `גוש #${blockId}`;

    // שמירה ב-selectedItems לניווט
    if (!window.selectedItems) {
        window.selectedItems = {};
    }
    window.selectedItems.block = { id: blockId, name: blockName };

    // מעבר לחלקות של הגוש
    if (typeof loadPlots === 'function') {
        loadPlots(blockId, blockName);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderBlockColumn(block, column) {
    switch(column.field) {
        case 'isActive':
            return getBlockStatusBadge(block.isActive);
        case 'plots_count':
            return getPlotsCountBadge(block.plots_count);
        case 'cemeteryNameHe':
            return getCemeteryBadge(block.cemeteryNameHe);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.blockSearch = blockSearch;
window.blocksTable = blocksTable;
window.currentBlocks = currentBlocks;
window.currentCemeteryId = currentCemeteryId;
window.currentCemeteryName = currentCemeteryName;

window.getBlockStatusBadge = getBlockStatusBadge;
window.getPlotsCountBadge = getPlotsCountBadge;
window.getCemeteryBadge = getCemeteryBadge;

window.openAddBlock = openAddBlock;
window.editBlock = editBlock;
window.viewBlock = viewBlock;
window.handleBlockDoubleClick = handleBlockDoubleClick;
window.renderBlockColumn = renderBlockColumn;
