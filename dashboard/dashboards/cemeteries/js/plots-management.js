/*
 * File: dashboards/dashboard/cemeteries/assets/js/plots-management.js
 * Version: 1.1.1
 * Updated: 2025-10-27
 * Author: Malkiel
 * Change Summary:
 * - v1.1.1: תיקון קריטי - שמירת סינון קיים כשקוראים ל-loadPlots ללא פרמטרים
 *   - הוספת פרמטר forceReset לאיפוס מפורש של הסינון
 *   - שמירת currentBlockId/Name גם כשלא מועברים פרמטרים
 *   - תיקון כפתור "הצג הכל" - קורא עם forceReset=true
 *   - מונע איפוס סינון אקראי ע"י sidebar/breadcrumb
 * - v1.1.0: תיקון סינון חלקות לפי גוש נבחר
 *   - הוספת סינון client-side כשכבת הגנה נוספת
 *   - שמירת currentBlockId ב-window לשימוש חוזר
 *   - הוספת אינדיקטור ויזואלי לסינון אקטיבי
 *   - הוספת logging מפורט לזיהוי בעיות
 * - v1.0.2: תיקון מוחלט - זהה בדיוק ל-blocks-management.js
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentPlots = [];
let plotSearch = null;
let plotsTable = null;
let editingPlotId = null;

// ⭐ חדש: שמירת ה-block context הנוכחי
let currentBlockId = null;
let currentBlockName = null;

// ===================================================================
// טעינת חלקות (הפונקציה הראשית)
// ===================================================================
async function loadPlots(blockId = null, blockName = null, forceReset = false) {
    console.log('📋 Loading plots - v1.2.0 (תוקן איפוס סינון)...');
    
    // ⭐ שינוי: אם קוראים ללא פרמטרים (מהתפריט) - אפס את הסינון!
    if (blockId === null && blockName === null && !forceReset) {
        // בדוק אם יש סינון קיים מהעבר
        if (window.currentBlockId !== null || currentBlockId !== null) {
            console.log('🔄 Resetting filter - called from menu without params');
            currentBlockId = null;
            currentBlockName = null;
            window.currentBlockId = null;
            window.currentBlockName = null;
        }
        console.log('🔍 Block filter: None (showing all plots)');
    } else if (forceReset) {
        console.log('🔄 Force reset filter');
        currentBlockId = null;
        currentBlockName = null;
        window.currentBlockId = null;
        window.currentBlockName = null;
    } else {
        // יש blockId - עדכן את הסינון
        console.log('🔄 Setting filter:', { blockId, blockName });
        currentBlockId = blockId;
        currentBlockName = blockName;
        window.currentBlockId = blockId;
        window.currentBlockName = blockName;
    }
    
    console.log('🔍 Final filter:', { blockId: currentBlockId, blockName: currentBlockName });
        
    window.currentBlockId = currentBlockId;
    window.currentBlockName = currentBlockName;
    
    console.log('🔍 Final filter:', { blockId: currentBlockId, blockName: currentBlockName });
  
    window.currentBlockId = currentBlockId;
    window.currentBlockName = currentBlockName;
    
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('plotsItem');
    }
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'plot';
    window.currentParentId = blockId;
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'plot' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'plot' });
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        const breadcrumbData = { 
            plot: { name: blockName ? `חלקות של ${blockName}` : 'חלקות' }
        };
        if (blockId && blockName) {
            breadcrumbData.block = { id: blockId, name: blockName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = blockName ? `חלקות - ${blockName}` : 'ניהול חלקות - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildPlotsContainer(blockId, blockName);
    
    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (plotSearch && typeof plotSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous plotSearch instance...');
        plotSearch.destroy();
        plotSearch = null;
        window.plotSearch = null;
    }
    
    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh plotSearch instance...');
    await initPlotsSearch(blockId);
    
    // ⭐ הפעל את החיפוש
    console.log('🔍 Searching:', { query: '', filters: [], blockId: currentBlockId });
    if (plotSearch && typeof plotSearch.search === 'function') {
        await plotSearch.search();
    }
    
    // קבל את התוצאות
    const results = plotSearch?.tableManager?.getDisplayedData() || [];
    console.log('📦 Results:', results.length, 'plots found');
    
    // טען סטטיסטיקות
    await loadPlotStats(blockId);
}

// ===================================================================
// ⭐ פונקציה מעודכנת - בניית המבנה של חלקות ב-main-container
// ===================================================================
async function buildPlotsContainer(blockId = null, blockName = null) {
    console.log('🏗️ Building plots container...');
    
    // מצא את main-container (צריך להיות קיים אחרי clear)
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
        console.log('⚠️ main-container not found, creating one...');
        const mainContent = document.querySelector('.main-content');
        mainContainer = document.createElement('div');
        mainContainer.className = 'main-container';
        
        const actionBar = mainContent.querySelector('.action-bar');
        if (actionBar) {
            actionBar.insertAdjacentElement('afterend', mainContainer);
        } else {
            mainContent.appendChild(mainContainer);
        }
    }
    
    // ⭐ הוסף אינדיקטור סינון אם יש גוש נבחר
    const filterIndicator = blockId && blockName ? `
        <div class="filter-indicator" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">📦</span>
                <div>
                    <div style="font-size: 12px; opacity: 0.9;">מציג חלקות עבור</div>
                    <div style="font-size: 16px; font-weight: 600;">${blockName}</div>
                </div>
            </div>
            <button onclick="loadPlots(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                ✕ הצג הכל
            </button>
        </div>
    ` : '';
    
    // ⭐ בנה את התוכן של חלקות
    mainContainer.innerHTML = `
        ${filterIndicator}
        
        <!-- סקשן חיפוש -->
        <div id="plotSearchSection" class="search-section"></div>
        
        <!-- table-container עבור TableManager -->
        <div class="table-container">
            <table id="mainTable" class="data-table">
                <thead>
                    <tr id="tableHeaders">
                        <th style="text-align: center;">טוען...</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td style="text-align: center; padding: 40px;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">טוען חלקות...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Plots container built');
}

// ===================================================================
// אתחול UniversalSearch - עם סינון משופר!
// ===================================================================
async function initPlotsSearch(blockId = null) {
    console.log('🔍 Initializing plot search...', { blockId, currentBlockName });
    
    // ⭐ שלב 1: הכן את הפרמטרים הנוספים
    const additionalParams = {};
    if (blockId) {
        console.log('🎯 Adding blockId filter to API request:', blockId);
        additionalParams.blockId = blockId;
    }
    
    const config = {
        entityType: 'plot',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/plots-api.php',
        action: 'list',
        
        // ⭐ פרמטרים נוספים לסינון לפי גוש
        additionalParams: additionalParams,
        
        searchableFields: ['plotNameHe', 'plotNameEn', 'plotCode', 'plotLocation'],
        
        placeholder: blockId 
            ? `חיפוש חלקות ב-${currentBlockName || 'גוש זה'}...` 
            : 'חיפוש חלקות...',
        
        tableConfig: {
            columns: [
                {
                    key: 'plotNameHe',
                    label: 'שם החלקה',
                    sortable: true,
                    render: (value, row) => {
                        const englishName = row.plotNameEn ? `<div class="secondary-text">${row.plotNameEn}</div>` : '';
                        return `
                            <div class="cell-with-secondary">
                                <div class="primary-text">${value || 'לא צוין'}</div>
                                ${englishName}
                            </div>
                        `;
                    }
                },
                {
                    key: 'plotCode',
                    label: 'קוד',
                    sortable: true,
                    render: (value) => {
                        if (!value) return '<span class="badge bg-secondary">ללא קוד</span>';
                        return `<span class="badge bg-primary">${value}</span>`;
                    }
                },
                {
                    key: 'plotLocation',
                    label: 'מיקום',
                    sortable: true,
                    render: (value) => value || '-'
                },
                {
                    key: 'block_name',
                    label: 'גוש',
                    sortable: true,
                    render: (value, row) => {
                        if (!value) return '<span class="text-muted">לא משויך</span>';
                        return `
                            <div class="clickable-cell" onclick="loadBlocks('${row.blockId}', '${value}')">
                                <i class="fas fa-cube" style="margin-left: 5px; color: #667eea;"></i>
                                ${value}
                            </div>
                        `;
                    }
                },
                {
                    key: 'createDate',
                    label: 'תאריך יצירה',
                    sortable: true,
                    render: (value) => {
                        if (!value) return '-';
                        const date = new Date(value);
                        return date.toLocaleDateString('he-IL');
                    }
                },
                {
                    key: 'rows_count',
                    label: 'שורות',
                    sortable: true,
                    className: 'text-center',
                    render: (value) => {
                        const count = parseInt(value) || 0;
                        return `<span class="badge ${count > 0 ? 'bg-info' : 'bg-secondary'}">${count}</span>`;
                    }
                }
            ],
            
            itemsPerPage: 25,
            enableInfiniteScroll: true,
            fixedWidth: true,
            
            rowActions: [
                {
                    icon: 'fas fa-edit',
                    label: 'ערוך',
                    className: 'btn-warning',
                    onClick: (row) => editPlot(row.unicId)
                },
                {
                    icon: 'fas fa-trash',
                    label: 'מחק',
                    className: 'btn-danger',
                    onClick: (row) => deletePlot(row.unicId)
                }
            ],
            
            onRowDoubleClick: (row) => {
                console.log('🖱️ Double-click on plot:', row);
                handlePlotDoubleClick(row.unicId, row.plotNameHe);
            },
            
            noDataMessage: blockId 
                ? `לא נמצאו חלקות בגוש "${currentBlockName || 'זה'}"` 
                : 'לא נמצאו חלקות במערכת'
        },
        
        // ⭐ סינון client-side נוסף
        onDataReceived: (data) => {
            console.log('📦 Raw results from API:', data.length, 'plots');
            
            // אם יש blockId פעיל, סנן client-side
            if (currentBlockId) {
                const beforeCount = data.length;
                const filtered = data.filter(plot => plot.blockId === currentBlockId);
                
                if (filtered.length !== beforeCount) {
                    console.log(`⚠️ Client-side filter applied: ${beforeCount} → ${filtered.length} plots`);
                    console.log('🔍 Filter reason: API returned unfiltered results');
                }
                
                console.log('🎯 Client-side filtered:', beforeCount, '→', filtered.length, 'plots');
                return filtered;
            }
            
            return data;
        }
    };
    
    // ⭐ שלב 2: השתמש ב-initUniversalSearch
    if (typeof initUniversalSearch === 'function') {
        plotSearch = await initUniversalSearch(config);
    } else {
        // Fallback למקרה שאין את הפונקציה
        console.warn('⚠️ initUniversalSearch not found, using direct initialization');
        plotSearch = new UniversalSearch(config);
        await plotSearch.init('plotSearchSection');
    }
    
    // שמירה ב-window
    window.plotSearch = plotSearch;
    plotsTable = plotSearch.tableManager;
    window.plotsTable = plotsTable;
    
    console.log('✅ UniversalSearch initialized for plots');
}

// ===================================================================
// טעינת סטטיסטיקות חלקות
// ===================================================================
async function loadPlotStats(blockId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/plots-api.php?action=stats';
        if (blockId) {
            url += `&blockId=${blockId}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Plot stats:', result.data);
            
            // עדכון מונים בממשק אם קיימים
            if (document.getElementById('totalPlots')) {
                document.getElementById('totalPlots').textContent = result.data.total_plots || 0;
            }
            if (document.getElementById('totalRows')) {
                document.getElementById('totalRows').textContent = result.data.total_rows || 0;
            }
            if (document.getElementById('newThisMonth')) {
                document.getElementById('newThisMonth').textContent = result.data.new_this_month || 0;
            }
        }
    } catch (error) {
        console.error('Error loading plot stats:', error);
    }
}

// ===================================================================
// עריכת חלקה
// ===================================================================
async function editPlot(plotId) {
    console.log('✏️ Editing plot:', plotId);
    editingPlotId = plotId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/plots-api.php?action=get&id=${plotId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה בטעינת נתוני החלקה');
        }
        
        const plot = result.data;
        
        // פתח את הטופס במודל
        if (typeof openFormModal === 'function') {
            openFormModal('plot', plot);
        } else {
            console.log('📝 Plot data:', plot);
            alert('פונקציית openFormModal לא זמינה');
        }
        
    } catch (error) {
        console.error('Error editing plot:', error);
        showToast('שגיאה בטעינת נתוני החלקה', 'error');
    }
}

// ===================================================================
// מחיקת חלקה
// ===================================================================
async function deletePlot(plotId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את החלקה?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/plots-api.php?action=delete&id=${plotId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת החלקה');
        }
        
        showToast('החלקה נמחקה בהצלחה', 'success');
        
        // רענן את הנתונים
        if (plotSearch) {
            plotSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting plot:', error);
        showToast(error.message, 'error');
    }
}

// ===================================================================
// הצגת הודעות Toast
// ===================================================================
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease-out;
    `;
    
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideUp 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===================================================================
// פונקציה לרענון נתונים
// ===================================================================
async function refreshData() {
    if (plotSearch) {
        plotSearch.refresh();
    }
}

// ===================================================================
// פונקציה לבדיקת סטטוס הטעינה
// ===================================================================
function checkScrollStatus() {
    if (!plotsTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = plotsTable.getFilteredData().length;
    const displayed = plotsTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(plotsTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// פונקציה לטיפול בדאבל-קליק על חלקה
// ===================================================
async function handlePlotDoubleClick(plotId, plotName) {
    console.log('🖱️ Double-click on plot:', plotName, plotId);
    
    try {
        // יצירת והצגת כרטיס
        if (typeof createPlotCard === 'function') {
            const cardHtml = await createPlotCard(plotId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        }
        
        // טעינת שורות (כשיהיה מוכן)
        console.log('📦 Loading rows for plot:', plotName);
        if (typeof loadRows === 'function') {
            loadRows(plotId, plotName);
        } else {
            console.warn('loadRows function not found');
        }
        
    } catch (error) {
        console.error('❌ Error in handlePlotDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי החלקה', 'error');
    }
}

window.handlePlotDoubleClick = handlePlotDoubleClick;

// ===================================================================
// Backward Compatibility - Aliases
// ===================================================================
window.loadAllPlots = loadPlots; // ✅ Alias לשם הישן

// ===================================================================
// הפוך את הפונקציות לגלובליות
// ===================================================================
window.loadPlots = loadPlots;
window.deletePlot = deletePlot;
window.editPlot = editPlot;
window.refreshData = refreshData;
window.plotsTable = plotsTable;
window.checkScrollStatus = checkScrollStatus;
window.currentBlockId = currentBlockId;
window.currentBlockName = currentBlockName;