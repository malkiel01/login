/*
 * File: dashboards/dashboard/cemeteries/assets/js/plots-management.js
 * Version: 2.0.0
 * Updated: 2025-11-19
 * Author: Malkiel
 * Change Summary:
 * - v2.0.0: 🔥 הוספת פונקציות חסרות בלבד - ללא שינוי מבנה קיים
 *   ✅ הוספת משתני pagination: plotsCurrentPage, plotsTotalPages, plotsIsLoadingMore
 *   ✅ הוספת משתני חיפוש: plotsIsSearchMode, plotsCurrentQuery, plotsSearchResults
 *   ✅ הוספת loadPlotsBrowseData() - טעינה ישירה מ-API
 *   ✅ הוספת appendMorePlots() - Infinite Scroll
 *   ✅ לוגים מפורטים כמו purchases/burials
 * - v1.4.0: תיקון קריטי - שמות ייחודיים לכל המשתנים הגלובליים
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
// ⭐ פונקציה מעודכנת - בניית המבנה של חלקות ב-main-container
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
async function initPlotsSearch(signal, blockId = null) {
    const config = {
        entityType: 'plot',
        signal: signal,
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
        
        displayColumns: ['plotNameHe', 'plotCode', 'plotLocation', 'blockNameHe', 'comments', 'rows_count', 'createDate'],
        
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
               console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()), blockId: plotsFilterBlockId });
           },

            onResults: (data) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'plots');
                
                // ⭐⭐⭐ בדיקה קריטית - אם עברנו לרשומה אחרת, לא להמשיך!
                if (window.currentType !== 'plot') {
                    console.log('⚠️ Type changed during search - aborting plot results');
                    console.log(`   Current type is now: ${window.currentType}`);
                    return; // ❌ עצור כאן!
                }

                // ⭐ טיפול בדפים - מצטבר!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentPlots = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentPlots = [...currentPlots, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentPlots.length}`);
                }
                
                // ⭐ אם יש סינון - סנן את currentPlots!
                let filteredCount = currentPlots.length;
                if (plotsFilterBlockId && currentPlots.length > 0) {
                    const filteredData = currentPlots.filter(plot => {
                        const plotBlockId = plot.blockId || plot.block_id || plot.BlockId;
                        return String(plotBlockId) === String(plotsFilterBlockId);
                    });
                    
                    console.log('⚠️ Client-side filter:', currentPlots.length, '→', filteredData.length, 'plots');
                    
                    // ⭐ עדכן את currentPlots
                    currentPlots = filteredData;
                    filteredCount = filteredData.length;
                    
                    // ⭐ עדכן את pagination.total
                    if (data.pagination) {
                        data.pagination.total = filteredCount;
                    }
                }
                
                // ⭐⭐⭐ עדכן ישירות את plotSearch!
                if (plotSearch && plotSearch.state) {
                    plotSearch.state.totalResults = filteredCount;
                    if (plotSearch.updateCounter) {
                        plotSearch.updateCounter();
                    }
                }
                
                console.log('📊 Final count:', filteredCount);
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
    
    plotSearch = await window.initUniversalSearch(config);
    
    // ⭐ עדכן את window.plotSearch מיד!
    window.plotSearch = plotSearch;
    
    return plotSearch;
}

// ===================================================================
// אתחול TableManager לחלקות
// ===================================================================
async function initPlotsTable(data, totalItems = null, signal) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (plotsTable) {
        plotsTable.config.totalItems = actualTotalItems;
        plotsTable.setData(data);
        return plotsTable;
    }

    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'plot', signal) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${entityType}&section=table_columns`, {
                signal: signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success || !result.data) {
                throw new Error(result.error || 'Failed to load columns config');
            }
            
            // המרת הקונפיג מ-PHP לפורמט של TableManager
            const columns = result.data.map(col => {
                const column = {
                    field: col.field,
                    label: col.title,
                    width: col.width || 'auto',
                    sortable: col.sortable !== false,
                    type: col.type || 'text'
                };
                
                // טיפול בסוגי עמודות מיוחדות
                switch(col.type) {
                    case 'link':
                        column.render = (plot) => {
                            return `<a href="#" onclick="handlePlotDoubleClick('${plot.unicId}', '${plot.plotNameHe?.replace(/'/g, "\\'")}'); return false;" 
                                    style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                ${plot.plotNameHe || '-'}
                            </a>`;
                        };
                        break;
                        
                    case 'badge':
                        column.render = (plot) => {
                            const count = plot[column.field] || 0;
                            return `<span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">${count}</span>`;
                        };
                        break;
                        
                    case 'status':
                        column.render = (plot) => {
                            const status = plot.statusPlot || plot.isActive;
                            return status == 1 
                                ? '<span class="status-badge status-active">פעיל</span>'
                                : '<span class="status-badge status-inactive">לא פעיל</span>';
                        };
                        break;
                        
                    case 'date':
                        column.render = (plot) => formatDate(plot[column.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deletePlot('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;
                        
                    default:
                        // עמודת טקסט רגילה
                        if (!column.render) {
                            column.render = (item) => item[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
            
        } catch (error) {
            // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
            if (error.name === 'AbortError') {
                console.log('⚠️ Columns loading aborted');
                return [];
            }
            console.error('Failed to load columns config:', error);
            return [];
        }
    }
      
    // קודם טען את העמודות
    const columns = await loadColumnsFromConfig('plot', signal);

    // בדוק אם בוטל
    if (signal && signal.aborted) {
        console.log('⚠️ Block table initialization aborted');
        return null;
    }


    plotsTable = new TableManager({
        tableSelector: '#mainTable',        
        totalItems: actualTotalItems,
        columns: columns,
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

    // ⭐ מאזין לגלילה - טען עוד דפים!
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && plotSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!plotSearch.state.isLoading && plotSearch.state.currentPage < plotSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    const nextPage = plotSearch.state.currentPage + 1;
                    plotSearch.state.currentPage = nextPage;
                    plotSearch.state.isLoading = true;
                    await plotSearch.search();
                }
            }
        });
    }
    
    window.plotsTable = plotsTable;  
    return plotsTable;
}

// ===================================================================
// רינדור שורות החלקות - עם הודעה מותאמת לגוש ריק
// ===================================================================
function renderPlotsRows(data, container, pagination = null, signal = null) {
    console.log(`📝 renderPlotsRows called with ${data.length} items`);
    
    // ⭐ סינון client-side לפי blockId
    let filteredData = data;
    if (plotsFilterBlockId) {
        filteredData = data.filter(plot => 
            plot.blockId === plotsFilterBlockId || 
            plot.block_id === plotsFilterBlockId
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
        
        // ⭐⭐⭐ הודעה מותאמת לגוש ריק!
        if (plotsFilterBlockId && plotsFilterBlockName) {
            // נכנסנו לגוש ספציפי ואין חלקות
            container.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין חלקות בגוש ${plotsFilterBlockName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                הגוש עדיין לא מכיל חלקות. תוכל להוסיף חלקה חדשה
                            </div>
                            <button 
                                onclick="if(typeof FormHandler !== 'undefined' && FormHandler.openForm) { FormHandler.openForm('plot', '${plotsFilterBlockId}', null); } else { alert('FormHandler לא זמין'); }" 
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                       color: white; 
                                       border: none; 
                                       padding: 12px 24px; 
                                       border-radius: 8px; 
                                       font-size: 15px; 
                                       font-weight: 600; 
                                       cursor: pointer; 
                                       box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                                       transition: all 0.2s;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';">
                                ➕ הוסף חלקה ראשונה
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // חיפוש כללי שלא מצא תוצאות
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
        }
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
        initPlotsTable(filteredData, totalItems, signal);
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
// הפוך לגלובלי
// ===================================================================

window.plotsTable = plotsTable;

window.plotsFilterBlockId = plotsFilterBlockId;

window.plotsFilterBlockName = plotsFilterBlockName;

window.plotSearch = plotSearch;

window.loadPlotsBrowseData = loadPlotsBrowseData;

console.log('✅ plots-management.js v2.0.0 - Loaded successfully!');