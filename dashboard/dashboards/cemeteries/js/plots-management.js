/*
 * File: dashboards/dashboard/cemeteries/assets/js/plots-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('plot', blockId)
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ תמיכה בסינון לפי גוש (parent)
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentPlots = [];
let plotSearch = null;
let plotsTable = null;
let editingPlotId = null;

let plotsIsSearchMode = false;
let plotsCurrentQuery = '';
let plotsSearchResults = [];

let plotsCurrentPage = 1;
let plotsTotalPages = 1;
let plotsIsLoadingMore = false;

// שמירת ה-block context הנוכחי
let plotsFilterBlockId = null;
let plotsFilterBlockName = null;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getPlotStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getPlotTypeBadge(plotType) {
    const types = {
        'regular': { label: 'רגיל', color: '#3b82f6' },
        'family': { label: 'משפחתי', color: '#8b5cf6' },
        'special': { label: 'מיוחד', color: '#f59e0b' }
    };
    const typeInfo = types[plotType] || { label: plotType || '-', color: '#6b7280' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getAreaGravesCountBadge(count) {
    const color = '#f59e0b';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${count || 0}</span>`;
}

function getBlockBadge(blockName) {
    const color = '#3b82f6';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${blockName || '-'}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

function openAddPlot(blockId = null) {
    window.currentType = 'plot';
    window.currentParentId = blockId || plotsFilterBlockId;

    const parentId = blockId || plotsFilterBlockId;
    const formUrl = `/dashboard/dashboards/cemeteries/forms/iframe/plotForm-iframe.php${parentId ? '?parentId=' + parentId : ''}`;

    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            title: 'הוספת חלקה חדשה',
            type: 'iframe',
            src: formUrl,
            width: 600,
            height: 500
        });
    }
}

async function editPlot(id) {
    window.currentType = 'plot';

    const formUrl = `/dashboard/dashboards/cemeteries/forms/iframe/plotForm-iframe.php?itemId=${id}`;

    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            title: 'עריכת חלקה',
            type: 'iframe',
            src: formUrl,
            width: 600,
            height: 500
        });
    }
}

async function viewPlot(id) {
    // לעת עתה פותחים עריכה - אפשר להוסיף כרטיס צפייה בהמשך
    editPlot(id);
}

// דאבל-קליק על שורת חלקה - מעבר לאחוזות קבר
async function handlePlotDoubleClick(plot) {
    const plotId = typeof plot === 'object'
        ? (plot.unicId || plot.id)
        : plot;

    // קבלת השם - בדיקת מספר שדות אפשריים
    let plotName = null;
    if (typeof plot === 'object') {
        plotName = plot.plotNameHe
                || plot.plotName
                || plot.name
                || null;
    }
    // fallback אם לא נמצא שם
    if (!plotName) {
        plotName = `חלקה #${plotId}`;
    }

    // שמירה ב-selectedItems לניווט
    if (!window.selectedItems) {
        window.selectedItems = {};
    }
    window.selectedItems.plot = { id: plotId, name: plotName };

    if (typeof loadAreaGraves === 'function') {
        loadAreaGraves(plotId, plotName);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderPlotColumn(plot, column) {
    switch(column.field) {
        case 'isActive':
            return getPlotStatusBadge(plot.isActive);
        case 'plotType':
            return getPlotTypeBadge(plot.plotType);
        case 'areaGraves_count':
            return getAreaGravesCountBadge(plot.areaGraves_count);
        case 'blockName':
            return getBlockBadge(plot.blockName);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.plotSearch = plotSearch;
window.plotsTable = plotsTable;
window.currentPlots = currentPlots;
window.plotsFilterBlockId = plotsFilterBlockId;
window.plotsFilterBlockName = plotsFilterBlockName;

window.getPlotStatusBadge = getPlotStatusBadge;
window.getPlotTypeBadge = getPlotTypeBadge;
window.getAreaGravesCountBadge = getAreaGravesCountBadge;
window.getBlockBadge = getBlockBadge;

window.openAddPlot = openAddPlot;
window.editPlot = editPlot;
window.viewPlot = viewPlot;
window.handlePlotDoubleClick = handlePlotDoubleClick;
window.renderPlotColumn = renderPlotColumn;
