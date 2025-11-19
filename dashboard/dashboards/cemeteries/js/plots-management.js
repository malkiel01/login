/*
 * File: dashboards/dashboard/cemeteries/assets/js/plots-management.js
 * Version: 2.0.0
 * Updated: 2025-11-19
 * Author: Malkiel
 * Change Summary:
 * - v2.0.0: 🔥 התאמה מלאה לשיטה המאוחדת - זהה 100% לכל היישויות
 *   ✅ הוספת משתני חיפוש ו-pagination:
 *   - plotsIsSearchMode, plotsCurrentQuery, plotsSearchResults
 *   - plotsCurrentPage, plotsTotalPages, plotsIsLoadingMore
 *   ✅ הוספת פונקציות חסרות:
 *   - loadPlotsBrowseData() - טעינה ישירה מ-API
 *   - appendMorePlots() - Infinite Scroll
 *   ✅ התאמת כל הפונקציות לשיטה המאוחדת
 *   ✅ שמות ייחודיים: plotsRefreshData, plotsCheckScrollStatus
 *   ✅ לוגים מפורטים זהים לכולם
 * - v1.4.0: תיקון קריטי - שמות ייחודיים
 */

console.log('🚀 plots-management.js v2.0.0 - Loading...');

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentPlots = [];
let plotSearch = null;
let plotsTable = null;
let editingPlotId = null;

let plotsIsSearchMode = false;      // האם אנחנו במצב חיפוש?
let plotsCurrentQuery = '';         // מה החיפוש הנוכחי?
let plotsSearchResults = [];        // תוצאות החיפוש

// ⭐ שמירת ה-block context הנוכחי
let plotsFilterBlockId = null;
let plotsFilterBlockName = null;

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let plotsCurrentPage = 1;
let plotsTotalPages = 1;
let plotsIsLoadingMore = false;


// ===================================================================
// טעינת חלקות (הפונקציה הראשית)
// ===================================================================
async function loadPlotsBrowseData(blockId = null, signal = null) {
    plotsCurrentPage = 1;
    currentPlots = [];
    
    try {
        let apiUrl = '/dashboard/dashboards/cemeteries/api/plots-api.php?action=list&limit=200&page=1';
        apiUrl += '&orderBy=createDate&sortDirection=DESC';
        
        if (blockId) {
            apiUrl += `&blockId=${blockId}`;
        }
        
        const response = await fetch(apiUrl, { signal });
        const result = await response.json();
        
        if (result.success && result.data) {
            currentPlots = result.data;
            
            if (result.pagination) {
                plotsTotalPages = result.pagination.pages;
                plotsCurrentPage = result.pagination.page;
            }
            
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
                renderPlotsRows(result.data, tableBody, result.pagination, signal);
            }
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            console.log('⚠️ Browse data loading aborted - this is expected');
            return;
        }
        console.error('❌ Error loading browse data:', error);
        showToast('שגיאה בטעינת חלקות', 'error');
    }
}

async function loadPlots(blockId = null, blockName = null, forceReset = false) {
    console.log('══════════════════════════════════════════════════');
    console.log('🚀 loadPlots() STARTED');
    console.log('══════════════════════════════════════════════════');
    
    const signal = OperationManager.start('plot');
    console.log('✅ Step 1: OperationManager started');

    // ⭐ איפוס מצב חיפוש
    plotsIsSearchMode = false;
    plotsCurrentQuery = '';
    plotsSearchResults = [];
    console.log('✅ Step 2: Search state reset');

    // ⭐ לוגיקת סינון
    if (blockId === null && blockName === null && !forceReset) {
        if (window.plotsFilterBlockId !== null || plotsFilterBlockId !== null) {
            console.log('🔄 Resetting filter - called from menu without params');
            plotsFilterBlockId = null;
            plotsFilterBlockName = null;
            window.plotsFilterBlockId = null;
            window.plotsFilterBlockName = null;
        }
    } else if (forceReset) {
        console.log('🔄 Force reset filter');
        plotsFilterBlockId = null;
        plotsFilterBlockName = null;
        window.plotsFilterBlockId = null;
        window.plotsFilterBlockName = null;
    } else {
        console.log('🔄 Setting filter:', { blockId, blockName });
        plotsFilterBlockId = blockId;
        plotsFilterBlockName = blockName;
        window.plotsFilterBlockId = blockId;
        window.plotsFilterBlockName = blockName;
    }
    
    window.plotsFilterBlockId = plotsFilterBlockId;
    window.plotsFilterBlockName = plotsFilterBlockName;
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'plot';
    window.currentParentId = blockId;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'plot';
    }
    console.log('✅ Step 3: Current type set to plot');

    // ⭐ נקה
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'plot' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'plot' });
    }
    console.log('✅ Step 4: Dashboard cleared');
    
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('plotsItem');
    }
    
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
    console.log('✅ Step 5: UI updated');
    
    // ⭐ בנה מבנה
    await buildPlotsContainer(signal, blockId, blockName);
    console.log('✅ Step 6: Container built');
    
    if (OperationManager.shouldAbort('plot')) {
        console.log('⚠️ ABORTED at step 6');
        return;
    }

    // ⭐ ספירת טעינות גלובלית
    if (!window.plotsLoadCounter) {
        window.plotsLoadCounter = 0;
    }
    window.plotsLoadCounter++;
    console.log(`✅ Step 7: Load counter = ${window.plotsLoadCounter}`);
    
    // ⭐ השמד חיפוש קודם
    if (plotSearch && typeof plotSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous plotSearch instance...');
        plotSearch.destroy();
        plotSearch = null; 
        window.plotSearch = null;
    }
    
    // ⭐ איפוס טבלה קודמת
    if (plotsTable) {
        console.log('🗑️ Resetting previous plotsTable instance...');
        plotsTable = null;
        window.plotsTable = null;
    }
    console.log('✅ Step 8: Previous instances destroyed');
    
    // ⭐ אתחול UniversalSearch - פעם אחת!
    console.log('🆕 Creating fresh plotSearch instance...');
    plotSearch = await initPlotsSearch(signal, blockId);
    console.log('✅ Step 9: UniversalSearch initialized');
    
    if (OperationManager.shouldAbort('plot')) {
        console.log('⚠️ ABORTED at step 9');
        console.log('⚠️ Plot operation aborted');
        return;
    }

    // ⭐ טעינה ישירה (Browse Mode) - פעם אחת!
    console.log('📥 Loading browse data...');
    await loadPlotsBrowseData(blockId, signal);
    console.log('✅ Step 10: Browse data loaded');
    
    // טען סטטיסטיקות
    console.log('📊 Loading stats...');
    await loadPlotStats(signal, blockId);
    console.log('✅ Step 11: Stats loaded');
    
    console.log('══════════════════════════════════════════════════');
    console.log('✅ loadPlots() COMPLETED SUCCESSFULLY');
    console.log('══════════════════════════════════════════════════');
}


// ===================================================================
// 📥 טעינת עוד חלקות (Infinite Scroll)
// ===================================================================
async function appendMorePlots() {
    // בדיקות בסיסיות
    if (plotsIsLoadingMore) {
        return false;
    }
    
    if (plotsCurrentPage >= plotsTotalPages) {
        return false;
    }
    
    plotsIsLoadingMore = true;
    const nextPage = plotsCurrentPage + 1;
    
    // ⭐ עדכון מונה טעינות
    if (!window.plotsLoadCounter) {
        window.plotsLoadCounter = 0; 
    }
    window.plotsLoadCounter++;
    
    try {
        // בנה URL לעמוד הבא
        let apiUrl = `/dashboard/dashboards/cemeteries/api/plots-api.php?action=list&limit=200&page=${nextPage}`;
        apiUrl += '&orderBy=createDate&sortDirection=DESC';
        
        if (plotsFilterBlockId) {
            apiUrl += `&blockId=${plotsFilterBlockId}`;
        }
        
        // שלח בקשה
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
            // ⭐ שמור את הגודל הקודם לפני ההוספה
            const previousTotal = currentPlots.length;
            
            // ⭐ הוסף לנתונים הקיימים
            currentPlots = [...currentPlots, ...result.data];
            plotsCurrentPage = nextPage;
            
            // ⭐⭐⭐ לוג פשוט ומסודר
            console.log(`
╔════════════════════════════════════════════════════════════════════
║ טעינה: ${window.plotsLoadCounter}
╠════════════════════════════════════════════════════════════════════
║ כמות ערכים בטעינה: ${result.data.length}
║ מספר ערך תחילת טעינה נוכחית: ${result.debug?.results_info?.from_index || (previousTotal + 1)}
║ מספר ערך סוף טעינה נוכחית: ${result.debug?.results_info?.to_index || currentPlots.length}
║ סך כל הערכים שנטענו עד כה: ${currentPlots.length}
║ שדה למיון: ${result.debug?.sql_info?.order_field || 'createDate'}
║ סוג מיון: ${result.debug?.sql_info?.sort_direction || 'DESC'}
╠════════════════════════════════════════════════════════════════════
║ עמוד: ${plotsCurrentPage} / ${plotsTotalPages}
║ נותרו עוד: ${plotsTotalPages - plotsCurrentPage} עמודים
╚════════════════════════════════════════════════════════════════════
`);
            
            // ⭐ עדכן את הטבלה
            if (plotsTable) {
                plotsTable.setData(currentPlots);
            }
            
            plotsIsLoadingMore = false;
            return true;
        } else {
            console.log('📭 No more data to load');
            plotsIsLoadingMore = false;
            return false;
        }
    } catch (error) {
        console.error('❌ Error loading more plots:', error);
        plotsIsLoadingMore = false;
        return false;
    }
}


// ===================================================================
// בניית המבנה
// ===================================================================
async function buildPlotsContainer(signal, blockId = null, blockName = null) {
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
                topSection = await createBlockCard(blockId, signal);
                console.log('✅ Block card created successfully');
            } catch (error) {
                // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
                if (error.name === 'AbortError') {
                    console.log('⚠️ Block card loading aborted');
                    return;
                }
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

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Build plots container aborted before innerHTML');
        return;
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
        <!-- סקשן חיפוש -->
        <div id="plotSearchSection" class="search-section"></div>
        
        <!-- סקשן סטטיסטיקות -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">סה"כ חלקות</div>
                <div style="font-size: 32px; font-weight: bold;" id="totalPlots">0</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">סה"כ שורות</div>
                <div style="font-size: 32px; font-weight: bold;" id="totalRows">0</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">חדשות החודש</div>
                <div style="font-size: 32px; font-weight: bold;" id="newThisMonth">0</div>
            </div>
        </div>
        
        <!-- סקשן טבלה -->
        <div id="plotTableSection" class="table-section">
            <table class="data-table" id="plotsTable">
                <thead>
                    <tr>
                        <th>מספר חלקה</th>
                        <th>שם חלקה</th>
                        <th>תיאור</th>
                        <th>סטטוס</th>
                        <th>תאריך יצירה</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div class="loading-spinner"></div>
                            <div style="margin-top: 10px; color: #64748b;">טוען נתונים...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
}


// ===================================================================
// אתחול חיפוש
// ===================================================================
async function initPlotsSearch(signal, blockId = null) {
    const searchSection = document.getElementById('plotSearchSection');
    if (!searchSection) {
        console.error('❌ plotSearchSection not found');
        return null;
    }

    // ⭐ בנייה מפורשת של config
    const searchConfig = {
        searchInputId: 'plotSearchInput',
        containerId: 'plotSearchSection',
        entityType: 'plot',
        entityNameHebrew: 'חלקה',
        entityNamePluralHebrew: 'חלקות',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/plots-api.php',
        onResultsReceived: async (data, query, totalResults) => {
            console.log(`🔍 Search results received: ${totalResults} plots found`);
            
            plotsIsSearchMode = true;
            plotsCurrentQuery = query;
            plotsSearchResults = data;
            
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
                await renderPlotsRows(data, tableBody, { total: totalResults }, signal);
            }
        },
        searchableFields: [
            'plotNumber',
            'plotName', 
            'description',
            'blockName'
        ],
        limit: 200,
        orderBy: 'createDate',
        sortDirection: 'DESC'
    };

    // ⭐ הוסף blockId ל-config אם קיים
    if (blockId) {
        searchConfig.filterParams = { blockId: blockId };
    }

    console.log('🔍 Initializing UniversalSearch with config:', searchConfig);
    
    const searchInstance = new UniversalSearch(searchConfig);
    window.plotSearch = searchInstance;
    
    return searchInstance;
}


// ===================================================================
// אתחול טבלה
// ===================================================================
async function initPlotsTable(data, totalItems, signal) {
    console.log('🏗️ Initializing TableManager...');
    console.log(`   Data items: ${data.length}`);
    console.log(`   Total items: ${totalItems}`);
    
    const tableBody = document.getElementById('tableBody');
    if (!tableBody) {
        console.error('❌ tableBody element not found');
        return;
    }

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Table initialization aborted');
        return;
    }

    const config = {
        tableBodyId: 'tableBody',
        itemsPerPage: 50,
        totalItems: totalItems,
        onLoadMore: async () => {
            console.log('📥 TableManager requesting more data...');
            return await appendMorePlots();
        },
        renderRow: (plot) => {
            const statusClass = plot.isActive === 1 ? 'status-active' : 'status-inactive';
            const statusText = plot.isActive === 1 ? 'פעיל' : 'לא פעיל';
            
            return `
                <tr data-id="${plot.unicId}" ondblclick="handlePlotDoubleClick('${plot.unicId}', '${plot.plotName || 'ללא שם'}')">
                    <td>${plot.plotNumber || '-'}</td>
                    <td>${plot.plotName || '-'}</td>
                    <td>${plot.description || '-'}</td>
                    <td><span class="${statusClass}">${statusText}</span></td>
                    <td>${formatDate(plot.createDate)}</td>
                    <td>
                        <button onclick="window.tableRenderer.editItem('${plot.unicId}')" class="btn-edit">✏️</button>
                        <button onclick="deletePlot('${plot.unicId}')" class="btn-delete">🗑️</button>
                    </td>
                </tr>
            `;
        }
    };

    console.log('⚙️ TableManager config:', config);
    
    plotsTable = new TableManager(config);
    window.plotsTable = plotsTable;
    
    plotsTable.setData(data);
    
    console.log('✅ TableManager initialized successfully');
}


// ===================================================================
// רינדור שורות הטבלה
// ===================================================================
async function renderPlotsRows(data, tableBody, pagination = {}, signal = null) {
    console.log('═══════════════════════════════════════════════════════════');
    console.log(`🎨 renderPlotsRows called with ${data.length} items`);
    console.log(`   Pagination:`, pagination);
    console.log('═══════════════════════════════════════════════════════════');

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Render aborted - operation cancelled');
        return;
    }

    const totalItems = pagination.total || data.length;
    console.log(`   Total items to manage: ${totalItems}`);

    // בדוק אם tableBody קיים
    if (!tableBody) {
        console.error('❌ tableBody element not found!');
        return;
    }

    // אם אין נתונים - הצג הודעה
    if (!data || data.length === 0) {
        console.log('📭 No data to display');
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                    אין חלקות להצגה
                </td>
            </tr>
        `;
        return;
    }
    
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    console.log(`   tableWrapperExists: ${!!tableWrapperExists}`);
    
    if (!tableWrapperExists && plotsTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting plotsTable variable');
        plotsTable = null;
        window.plotsTable = null;
    }

    // ⭐⭐⭐ אתחול או עדכון טבלה
    if (!plotsTable || !tableWrapperExists) {
        console.log(`🆕 Initializing TableManager with ${totalItems} items`);
        await initPlotsTable(data, totalItems, signal);
        console.log('   ✅ TableManager initialized');
    } else {
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (plotsTable.config) {
            plotsTable.config.totalItems = totalItems;
        }
        plotsTable.setData(data);
        console.log('   ✅ TableManager updated');
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
// טעינת סטטיסטיקות
// ===================================================================
async function loadPlotStats(signal, blockId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/plots-api.php?action=stats';
        if (blockId) {
            url += `&blockId=${blockId}`;
        }
        
        const response = await fetch(url, { signal: signal });
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Plot stats:', result.data);
            
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
        if (error.name === 'AbortError') {
            console.log('⚠️ Plot stats loading aborted - this is expected');
            return;
        }
        console.error('Error loading plot stats:', error);
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
// רענון נתונים
// ===================================================================
async function plotsRefreshData() {
    // טעינה מחדש ישירה מה-API (כי UniversalSearch מושבת)
    await loadPlots(plotsFilterBlockId, plotsFilterBlockName, false);
}


// ===================================================================
// בדיקת סטטוס טעינה
// ===================================================================
function plotsCheckScrollStatus() {
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
        console.log(`   🔽 Scroll down to load more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}


// ===================================================================
// דאבל-קליק על חלקה
// ===================================================================
async function handlePlotDoubleClick(plotId, plotName) {
    console.log('🖱️ Double-click on plot:', plotName, plotId);
    
    try {
        // // 1. יצירת והצגת כרטיס (אופציונלי)
        // if (typeof createPlotCard === 'function') {
        //     const cardHtml = await createPlotCard(plotId);
        //     if (cardHtml && typeof displayHierarchyCard === 'function') {
        //         displayHierarchyCard(cardHtml);
        //     }
        // }
        
        // 2. טעינת אחוזות קבר
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
// הפוך לגלובלי
// ===================================================================
window.loadPlots = loadPlots;

window.appendMorePlots = appendMorePlots;

window.deletePlot = deletePlot;

window.plotsRefreshData = plotsRefreshData;

window.plotsTable = plotsTable;

window.plotsCheckScrollStatus = plotsCheckScrollStatus;

window.plotsFilterBlockId = plotsFilterBlockId;

window.plotsFilterBlockName = plotsFilterBlockName;

window.plotSearch = plotSearch;

console.log('✅ plots-management.js v2.0.0 - Loaded successfully!');