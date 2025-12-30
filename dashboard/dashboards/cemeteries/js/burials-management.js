/*
 * File: dashboards/dashboard/cemeteries/assets/js/burials-management.js
 * Version: 5.0.0
 * Updated: 2025-11-18
 * Author: Malkiel
 * Change Summary:
 * - v5.0.0: 🔥 יצירה מחדש מאפס - זהה 100% לרכישות
 *   ✅ העתקה מלאה של purchases-management.js v4.0.1
 *   ✅ התאמת כל השמות: purchase → burial
 *   ✅ התאמת כל הטקסטים: רכישות → קבורות
 *   ✅ התאמת השדות הספציפיים לקבורות
 *   ✅ searchableFields מותאם לקבורות (9 שדות)
 *   ✅ displayColumns מותאם לקבורות
 */

console.log('🚀 burials-management.js v5.0.0 - Loading...');

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentBurials = [];
let burialSearch = null;
let burialsTable = null;
let editingBurialId = null;

let burialsIsSearchMode = false;      // האם אנחנו במצב חיפוש?
let burialsCurrentQuery = '';         // מה החיפוש הנוכחי?
let burialsSearchResults = [];        // תוצאות החיפוש

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let burialsCurrentPage = 1;
let burialsTotalPages = 1;
let burialsIsLoadingMore = false;

// ===================================================================
// בניית המבנה
// ===================================================================
async function buildBurialsContainer(signal) {
    console.log('🏗️ Building burials container...');
    
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
    
    mainContainer.innerHTML = `
        <div id="burialSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען קבורות...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Burials container built');
}


// ===================================================================
// אתחול UniversalSearch
// ===================================================================
async function initBurialsSearch(signal) {
    const config = {
        entityType: 'burial',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/burials-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'serialBurialId',
                label: 'מס׳ תיק קבורה',
                table: 'burials',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'customerLastName',
                label: 'שם משפחה נפטר',
                table: 'burials',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'customerFirstName',
                label: 'שם פרטי נפטר',
                table: 'burials',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'customerNumId',
                label: 'ת.ז. נפטר',
                table: 'burials',
                type: 'text',
                matchType: ['exact']
            },
            {
                name: 'dateDeath',
                label: 'תאריך פטירה',
                table: 'burials',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            },
            {
                name: 'dateBurial',
                label: 'תאריך קבורה',
                table: 'burials',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            },
            {
                name: 'burialStatus',
                label: 'סטטוס קבורה',
                table: 'burials',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: '1', label: 'ברישום' },
                    { value: '2', label: 'אושרה' },
                    { value: '3', label: 'בוצעה' },
                    { value: '4', label: 'בוטלה' }
                ]
            },
            {
                name: 'nationalInsuranceBurial',
                label: 'ביטוח לאומי',
                table: 'burials',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: 'כן', label: 'כן' },
                    { value: 'לא', label: 'לא' }
                ]
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'burials',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['serialBurialId', 'customerLastName', 'customerNumId', 'dateDeath', 'dateBurial', 'timeBurial', 'fullLocation', 'burialStatus', 'nationalInsuranceBurial'],
        
        searchContainerSelector: '#burialSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש קבורות לפי מספר תיק, שם נפטר, תאריך...',
        itemsPerPage: 999999,
        
        renderFunction: renderBurialsRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for burials');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
                
                // ⭐ כאשר מתבצע חיפוש - הפעל מצב חיפוש
                burialsIsSearchMode = true;
                burialsCurrentQuery = query;
            },

            onResults: async (data, signal) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'burials');
                
                // ⭐ אם נכנסנו למצב חיפוש - הצג רק תוצאות חיפוש
                if (burialsIsSearchMode && burialsCurrentQuery) {
                    console.log('🔍 Search mode active - showing search results only');
                    burialsSearchResults = data.data;
                    
                    const tableBody = document.getElementById('tableBody');
                    if (tableBody) {
                        await renderBurialsRows(burialsSearchResults, tableBody, data.pagination, signal);
                    }
                    return;
                }
                
                // ⭐⭐⭐ בדיקה קריטית - אם עברנו לרשומה אחרת, לא להמשיך!
                if (window.currentType !== 'burial') {
                    console.log('⚠️ Type changed during search - aborting burial results');
                    console.log(`   Current type is now: ${window.currentType}`);
                    return;
                }
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש קבורות', 'error');
            },

            onEmpty: () => {
                console.log('📭 No results');
            },
            
            onClear: async () => {
                console.log('🧹 Search cleared - returning to browse mode');
                
                // ⭐ איפוס מצב חיפוש
                burialsIsSearchMode = false;
                burialsCurrentQuery = '';
                burialsSearchResults = [];
                
                // ⭐ חזרה למצב Browse
                await loadBurialsBrowseData(signal);
            }
        }
    };
    
    const searchInstance = await window.initUniversalSearch(config);
    
    return searchInstance;
}


// ===================================================================
// אתחול TableManager
// ===================================================================
async function initBurialsTable(data, totalItems = null, signal = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (burialsTable) {
        burialsTable.config.totalItems = actualTotalItems;
        burialsTable.setData(data);
        return burialsTable;
    }
        
    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'burial') {
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
                    case 'date':
                        column.render = (burial) => formatDate(burial[column.field]);
                        break;
                        
                    case 'time':
                        column.render = (burial) => burial[column.field] || '-';
                        break;
                        
                    case 'status':
                        if (col.render === 'formatBurialStatus') {
                            column.render = (burial) => formatBurialStatus(burial[column.field]);
                        }
                        break;
                        
                    case 'boolean':
                        column.render = (burial) => burial[column.field] === 'כן' ? 
                            '<span style="color: #10b981;">✓ כן</span>' : 
                            '<span style="color: #ef4444;">✗ לא</span>';
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-info" 
                                    onclick="event.stopPropagation(); handleBurialDoubleClick('${item.unicId}')" 
                                    title="צפייה">
                                <svg class="icon"><use xlink:href="#icon-view"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteBurial('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;
                        
                    default:
                        if (!column.render) {
                            column.render = (burial) => burial[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
            
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('⚠️ Column config loading aborted - this is expected');
                return [];
            }
            
            console.error('❌ Failed to load columns config:', error);
            return [];
        }
    }

    burialsTable = new TableManager({
        tableSelector: '#mainTable',
        
        totalItems: actualTotalItems,

        columns: await loadColumnsFromConfig('burial'),

        data: data,
        
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        infiniteScroll: true,
        scrollThreshold: 200,
        onLoadMore: async () => {
            if (burialsIsSearchMode) {
                // במצב חיפוש - טען דרך UniversalSearch
                if (burialSearch && typeof burialSearch.loadNextPage === 'function') {
                    if (burialSearch.state.currentPage >= burialSearch.state.totalPages) {
                        burialsTable.state.hasMoreData = false;
                        return;
                    }
                    await burialSearch.loadNextPage();
                }
            } else {
                // במצב Browse - טען ישירות
                const success = await appendMoreBurials();
                if (!success) {
                    burialsTable.state.hasMoreData = false;
                }
            }
        },
        
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = burialsTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    window.burialsTable = burialsTable;
    return burialsTable;
}


// ===================================================================
// רינדור שורות - עם תמיכה ב-Search Mode
// ===================================================================
async function renderBurialsRows(data, container, pagination = null, signal = null) {
    console.log(`📝 renderBurialsRows called with ${data.length} items`);
    console.log(`   Pagination:`, pagination);
    console.log(`   burialsIsSearchMode: ${burialsIsSearchMode}`);
    console.log(`   burialsTable exists: ${!!burialsTable}`);
    
    // ⭐⭐ במצב חיפוש - הצג תוצאות חיפוש בלי טבלה מורכבת
    if (burialsIsSearchMode && burialsCurrentQuery) {
        console.log('🔍 Rendering search results...');
        
        if (data.length === 0) {
            container.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align: center; padding: 60px;">
                        <div style="color: #9ca3af;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                            <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                        </div>
                    </td>
                </tr>
            `;
            console.log('   → Empty search results displayed');
            return;
        }
        
        const totalItems = data.length;
        console.log(`   → Initializing table with ${totalItems} search results`);
        await initBurialsTable(data, totalItems, signal);
        console.log('   ✅ Search results table initialized');
        return;
    }
    
    // ⭐⭐ מצב רגיל (Browse) - הצג עם TableManager
    const totalItems = pagination?.total || data.length;
    console.log(`📊 Total items to display: ${totalItems}`);

    if (data.length === 0) {
        console.log('   → No data to display');
        if (burialsTable) {
            burialsTable.setData([]);
        }
        
        container.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 60px;">
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
    
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    console.log(`   tableWrapperExists: ${!!tableWrapperExists}`);
    
    if (!tableWrapperExists && burialsTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting burialsTable variable');
        burialsTable = null;
        window.burialsTable = null;
    }

    // ⭐⭐⭐ אתחול או עדכון טבלה
    if (!burialsTable || !tableWrapperExists) {
        console.log(`🆕 Initializing TableManager with ${totalItems} items`);
        await initBurialsTable(data, totalItems, signal);
        console.log('   ✅ TableManager initialized');
    } else {
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (burialsTable.config) {
            burialsTable.config.totalItems = totalItems;
        }
        burialsTable.setData(data);
        console.log('   ✅ TableManager updated');
    }
}

// ===================================================================
// הפנייה לפונקציות גלובליות
// ===================================================================
function checkBurialsScrollStatus() {
    checkEntityScrollStatus(burialsTable, 'Burials');
}

// ===================================================================
// פונקציות עזר לפורמט
// ===================================================================
function formatBurialStatus(status) {
    const statuses = {
        '1': { text: 'ברישום', color: '#f59e0b' },
        '2': { text: 'אושרה', color: '#3b82f6' },
        '3': { text: 'בוצעה', color: '#10b981' },
        '4': { text: 'בוטלה', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || statuses['1'];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
}

// ===================================================================
// דאבל-קליק על קבורה
// ===================================================================
async function handleBurialDoubleClick(burial) {
    // תמיכה גם באובייקט וגם ב-ID ישיר
    const burialId = typeof burial === 'object' ? (burial.unicId || burial.id) : burial;
    console.log('🖱️ Double-click on burial:', burialId);

    try {
        // פתיחת כרטיס קבורה דרך FormHandler
        if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
            FormHandler.openForm('burialCard', null, burialId);
        } else {
            console.warn('⚠️ FormHandler not found - opening edit form');
            if (typeof window.tableRenderer !== 'undefined' && window.tableRenderer.editItem) {
                window.tableRenderer.editItem(burialId);
            } else {
                console.error('❌ tableRenderer.editItem not available');
                showToast('שגיאה בפתיחת טופס עריכה', 'error');
            }
        }
    } catch (error) {
        console.error('❌ Error in handleBurialDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי קבורה', 'error');
    }
}

window.handleBurialDoubleClick = handleBurialDoubleClick;
// ===================================================================
// הפוך לגלובלי
// ===================================================================

window.burialsTable = burialsTable;

window.burialSearch = burialSearch;

console.log('✅ burials-management.js v5.0.0 - Loaded successfully!');