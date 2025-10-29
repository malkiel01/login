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
    plotSearch.search();
    
    // טען סטטיסטיקות
    await loadPlotStats(blockId);
}

// ===================================================================
// ⭐ פונקציה מעודכנת - בניית המבנה של חלקות ב-main-container
// ===================================================================
async function buildPlotsContainer(blockId = null, blockName = null) {
    console.log('🏗️ Building plots container...');
    
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
    
    // ⭐⭐⭐ טעינת כרטיס מלא של הגוש במקום indicator פשוט!
    let topSection = '';
    if (blockId && blockName) {
        console.log('🎴 Creating full block card...');
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createBlockCard === 'function') {
            try {
                topSection = await createBlockCard(blockId);
                console.log('✅ Block card created successfully');
            } catch (error) {
                console.error('❌ Error creating block card:', error);
            }
        } else {
            console.warn('⚠️ createBlockCard function not found');
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            console.log('⚠️ Using simple filter indicator as fallback');
            topSection = `
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
            `;
        }
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
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
    const config = {
        entityType: 'plot',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/plots-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'plotNameHe',
                label: 'שם חלקה (עברית)',
                table: 'plots',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'plotNameEn',
                label: 'שם חלקה (אנגלית)',
                table: 'plots',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'plotCode',
                label: 'קוד חלקה',
                table: 'plots',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'plotLocation',
                label: 'מיקום חלקה',
                table: 'plots',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'blockNameHe',
                label: 'גוש',
                table: 'blocks',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'comments',
                label: 'הערות',
                table: 'plots',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'plots',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['plotNameHe', 'plotCode', 'plotLocation', 'block_name', 'comments', 'rows_count', 'createDate'],
        
        searchContainerSelector: '#plotSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש חלקות לפי שם, קוד, מיקום...',
        itemsPerPage: 999999,
        
        renderFunction: renderPlotsRows,

       callbacks: {
           onInit: () => {
               console.log('✅ UniversalSearch initialized for plots');
           },
           
           onSearch: (query, filters) => {
               console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()), blockId: currentBlockId });
           },
           
           onResults: (data) => {
               console.log('📦 Raw results from API:', data.data.length, 'plots');
               
               // ⭐ אם יש סינון - סנן את data.data לפני כל דבר אחר!
               if (currentBlockId && data.data) {
                   const filteredData = data.data.filter(plot => 
                       plot.blockId === currentBlockId || 
                       plot.block_id === currentBlockId
                   );
                   
                   console.log('⚠️ Client-side filter:', data.data.length, '→', filteredData.length, 'plots');
                   
                   // ⭐ עדכן את data.data עצמו!
                   data.data = filteredData;
                   
                   // ⭐ עדכן את pagination.total
                   if (data.pagination) {
                       data.pagination.total = filteredData.length;
                   }
               }
               
               currentPlots = data.data;
               console.log('📊 Final count:', data.pagination?.total || data.data.length);
           },
           
           onError: (error) => {
               console.error('❌ Search error:', error);
               showToast('שגיאה בחיפוש חלקות', 'error');
           },

           onEmpty: () => {
               console.log('📭 No results');
           }
       }
    };
    
    // ⭐ אם יש סינון לפי גוש, הוסף פרמטר ל-API
    if (blockId) {
        console.log('🎯 Adding blockId filter to API request:', blockId);
        config.additionalParams = { blockId: blockId };
    }
    
    plotSearch = window.initUniversalSearch(config);
    
    // ⭐ עדכן את window.plotSearch מיד!
    window.plotSearch = plotSearch;
    
    return plotSearch;
}

// ===================================================================
// אתחול TableManager לחלקות
// ===================================================================
async function initPlotsTable(data, totalItems = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (plotsTable) {
        plotsTable.config.totalItems = actualTotalItems;
        plotsTable.setData(data);
        return plotsTable;
    }

    plotsTable = new TableManager({
        tableSelector: '#mainTable',
        
        totalItems: actualTotalItems,

        columns: [
            {
                field: 'plotNameHe',
                label: 'שם חלקה',
                width: '200px',
                sortable: true,
                render: (plot) => {
                    return `<a href="#" onclick="handlePlotDoubleClick('${plot.unicId}', '${plot.plotNameHe.replace(/'/g, "\\'")}'); return false;" 
                               style="color: #2563eb; text-decoration: none; font-weight: 500;">
                        ${plot.plotNameHe}
                    </a>`;
                }
            },
            {
                field: 'plotCode',
                label: 'קוד',
                width: '100px',
                sortable: true
            },
            {
                field: 'block_name',
                label: 'גוש',
                width: '200px',
                sortable: true
            },
            {
                field: 'rows_count',
                label: 'שורות',
                width: '80px',
                type: 'number',
                sortable: true,
                render: (plot) => {
                    const count = plot.rows_count || 0;
                    return `<span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">${count}</span>`;
                }
            },
            {
                field: 'statusPlot',
                label: 'סטטוס',
                width: '100px',
                sortable: true,
                render: (plot) => {
                    return plot.statusPlot == 1 || plot.isActive == 1
                        ? '<span class="status-badge status-active">פעיל</span>'
                        : '<span class="status-badge status-inactive">לא פעיל</span>';
                }
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (plot) => formatDate(plot.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (plot) => `
                    <button class="btn btn-sm btn-secondary" onclick="editPlot('${plot.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deletePlot('${plot.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                `
            }
        ],

        // onRowDoubleClick: (plot) => {
        //     handlePlotDoubleClick(plot.unicId, plot.plotNameHe);
        // },
        
        data: data,
        
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = plotsTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    window.plotsTable = plotsTable;
    
    return plotsTable;
}

// ===================================================================
// רינדור שורות החלקות - בדיוק כמו בגושים
// ===================================================================
function renderPlotsRows(data, container, pagination = null) {
    console.log(`📝 renderPlotsRows called with ${data.length} items`);
    
    // ⭐ סינון client-side לפי blockId
    let filteredData = data;
    if (currentBlockId) {
        filteredData = data.filter(plot => 
            plot.blockId === currentBlockId || 
            plot.block_id === currentBlockId
        );
        console.log(`🎯 Client-side filtered: ${data.length} → ${filteredData.length} plots`);
    }
    
    // ⭐ עדכן את totalItems להיות המספר המסונן!
    const totalItems = filteredData.length;
    
    console.log(`📊 Total items to display: ${totalItems}`);

    if (filteredData.length === 0) {
        if (plotsTable) {
            plotsTable.setData([]);
        }
        
        container.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 60px;">
                    <div style="color: #9ca3af;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                        <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && plotsTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting plotsTable variable');
        plotsTable = null;
        window.plotsTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!plotsTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        console.log(`🏗️ Creating new TableManager with ${totalItems} items`);
        initPlotsTable(filteredData, totalItems);
    } else {
        // ⭐ עדכן גם את totalItems ב-TableManager!
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (plotsTable.config) {
            plotsTable.config.totalItems = totalItems;
        }
        
        plotsTable.setData(filteredData);
    }
    
    // ⭐ עדכן את התצוגה של UniversalSearch
    if (plotSearch) {
        plotSearch.state.totalResults = totalItems;
        plotSearch.updateCounter();
    }
}

// ===================================================================
// פורמט תאריך
// ===================================================================
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
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
        if (typeof FormHandler.openForm === 'function') {
            // openFormModal('plot', plot);
            FormHandler.openForm('plot', null, plot.unicId); 
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
async function handlePlotDoubleClick2(plotId, plotName) {
    console.log('🖱️ Double-click on plot:', plotName, plotId);
    
    try {
        // טעינת חלקות
        console.log('📦 Loading plots for block:', blockName);
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

// ===================================================
// ⭐ פונקציה מתוקנת - טיפול בדאבל-קליק על חלקה
// ===================================================
async function handlePlotDoubleClick(plotId, plotName) {
    console.log('🖱️ Double-click on plot:', plotName, plotId);
    
    try {
        // // 1. יצירת והצגת כרטיס ✅
        // if (typeof createPlotCard === 'function') {
        //     const cardHtml = await createPlotCard(plotId);
        //     if (cardHtml && typeof displayHierarchyCard === 'function') {
        //         displayHierarchyCard(cardHtml);
        //     }
        // }
        
        // 2. טעינת אחוזות קבר (נכדים דרך השורות) ✅ שינוי!
        console.log('🏘️ Loading area graves for plot:', plotName);
        if (typeof loadAreaGraves === 'function') {
            loadAreaGraves(plotId, plotName);
        } else {
            console.warn('loadAreaGraves function not found');
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