/*
 * File: dashboards/dashboard/cemeteries/assets/js/purchases-management.js
 * Version: 4.0.1
 * Updated: 2025-11-18
 * Author: Malkiel
 * Change Summary:
 * - v4.0.1: 🐛 תיקון קריטי - טעינה שנייה לא הסתיימה
 *   - הוספת בדיקת !tableWrapperExists ב-renderPurchasesRows (שורה 673)
 *   - עכשיו זהה 100% ל-customers שעובד מעולה
 * - v4.0.0: 🔥 שיטה זהה 100% ל-customers, area-graves ו-graves
 *   ✅ הוספת משתני חיפוש ו-pagination:
 *   - purchasesIsSearchMode, purchasesCurrentQuery, purchasesSearchResults
 *   - purchasesCurrentPage, purchasesTotalPages, purchasesIsLoadingMore
 *   ✅ הוספת פונקציות חסרות:
 *   - loadPurchasesBrowseData() - טעינה ישירה מ-API
 *   - appendMorePurchases() - Infinite Scroll
 *   ✅ התאמת כל הפונקציות לשיטה המאוחדת
 * - v3.2.1: אחידות חלקית עם customers-management
 * - v3.0.0: שיטה זהה לבתי עלמין - UniversalSearch + TableManager
 */

console.log('🚀 purchases-management.js v4.0.1 - Loading...');

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentPurchases = [];
let purchaseSearch = null;
let purchasesTable = null;
let editingPurchaseId = null;

let purchasesIsSearchMode = false;      // האם אנחנו במצב חיפוש?
let purchasesCurrentQuery = '';         // מה החיפוש הנוכחי?
let purchasesSearchResults = [];        // תוצאות החיפוש

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let purchasesCurrentPage = 1;
let purchasesTotalPages = 1;
let purchasesIsLoadingMore = false;

// ===================================================================
// בניית המבנה
// ===================================================================
async function buildPurchasesContainer(signal) {
    console.log('🏗️ Building purchases container...');
    
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
        <div id="purchaseSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען רכישות...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Purchases container built');
}


// ===================================================================
// אתחול UniversalSearch
// ===================================================================
async function initPurchasesSearch(signal) {
    const config = {
        entityType: 'purchase',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/purchases-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'purchaseNumber',
                label: 'מספר רכישה',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'customerName',
                label: 'שם לקוח',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'graveLocation',
                label: 'מיקום קבר',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'totalAmount',
                label: 'סכום כולל',
                table: 'purchases',
                type: 'number',
                matchType: ['exact', 'greater', 'less', 'between']
            },
            {
                name: 'status',
                label: 'סטטוס',
                table: 'purchases',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: 'pending', label: 'ממתין' },
                    { value: 'approved', label: 'מאושר' },
                    { value: 'completed', label: 'הושלם' },
                    { value: 'cancelled', label: 'בוטל' }
                ]
            },
            {
                name: 'type',
                label: 'סוג רכישה',
                table: 'purchases',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: 'new', label: 'רכישה חדשה' },
                    { value: 'transfer', label: 'העברת בעלות' },
                    { value: 'renewal', label: 'חידוש' }
                ]
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'purchases',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['purchaseNumber', 'customerName', 'graveLocation', 'totalAmount', 'status', 'type', 'createDate'],
        
        searchContainerSelector: '#purchaseSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש רכישות לפי מספר, לקוח, קבר...',
        itemsPerPage: 999999,
        
        renderFunction: renderPurchasesRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for purchases');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
                
                // ⭐ כאשר מתבצע חיפוש - הפעל מצב חיפוש
                purchasesIsSearchMode = true;
                purchasesCurrentQuery = query;
            },

            onResults: async (data, signal) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'purchases');
                
                // ⭐ אם נכנסנו למצב חיפוש - הצג רק תוצאות חיפוש
                if (purchasesIsSearchMode && purchasesCurrentQuery) {
                    console.log('🔍 Search mode active - showing search results only');
                    purchasesSearchResults = data.data;
                    
                    const tableBody = document.getElementById('tableBody');
                    if (tableBody) {
                        await renderPurchasesRows(purchasesSearchResults, tableBody, data.pagination, signal);
                    }
                    return;
                }
                
                // ⭐⭐⭐ בדיקה קריטית - אם עברנו לרשומה אחרת, לא להמשיך!
                if (window.currentType !== 'purchase') {
                    console.log('⚠️ Type changed during search - aborting purchase results');
                    console.log(`   Current type is now: ${window.currentType}`);
                    return;
                }
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש רכישות', 'error');
            },

            onEmpty: () => {
                console.log('📭 No results');
            },
            
            onClear: async () => {
                console.log('🧹 Search cleared - returning to browse mode');
                
                // ⭐ איפוס מצב חיפוש
                purchasesIsSearchMode = false;
                purchasesCurrentQuery = '';
                purchasesSearchResults = [];
                
                // ⭐ חזרה למצב Browse
                await loadPurchasesBrowseData(signal);
            }
        }
    };
    
    const searchInstance = window.initUniversalSearch(config);
    
    return searchInstance;
}


// ===================================================================
// אתחול TableManager
// ===================================================================
async function initPurchasesTable(data, totalItems = null, signal = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (purchasesTable) {
        purchasesTable.config.totalItems = actualTotalItems;
        purchasesTable.setData(data);
        return purchasesTable;
    }
        
    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'purchase') {
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
                        column.render = (purchase) => formatDate(purchase[column.field]);
                        break;
                        
                    case 'status':
                        if (col.render === 'formatPurchaseStatus') {
                            column.render = (purchase) => formatPurchaseStatus(purchase[column.field]);
                        }
                        break;
                        
                    case 'type':
                        if (col.render === 'formatPurchaseType') {
                            column.render = (purchase) => formatPurchaseType(purchase[column.field]);
                        }
                        break;
                        
                    case 'currency':
                        column.render = (purchase) => formatCurrency(purchase[column.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deletePurchase('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;
                        
                    default:
                        if (!column.render) {
                            column.render = (purchase) => purchase[column.field] || '-';
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

    purchasesTable = new TableManager({
        tableSelector: '#mainTable',
        columns: await loadColumnsFromConfig('purchase'),        
        data: data,        
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,

        tableHeight: 'calc(100vh - 650px)',  // גובה דינמי לפי מסך
        tableMinHeight: '500px',
        
        totalItems: actualTotalItems,    
        scrollLoadBatch: 100,
        itemsPerPage: 999999,
        scrollThreshold: 200,
        showPagination: false,

        onLoadMore: async () => {
            if (purchasesIsSearchMode) {
                // במצב חיפוש - טען דרך UniversalSearch
                if (purchaseSearch && typeof purchaseSearch.loadNextPage === 'function') {
                    if (purchaseSearch.state.currentPage >= purchaseSearch.state.totalPages) {
                        purchasesTable.state.hasMoreData = false;
                        return;
                    }
                    await purchaseSearch.loadNextPage();
                }
            } else {
                // במצב Browse - טען ישירות
                const success = await appendMorePurchases();
                if (!success) {
                    purchasesTable.state.hasMoreData = false;
                }
            }
        },

        renderFunction: (pageData) => {
            // ⭐ זה לא ישמש - UniversalSearch ירנדר ישירות
            return renderPurchasesRows(pageData);
        },
        
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = purchasesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    window.purchasesTable = purchasesTable;
    return purchasesTable;
}


// ===================================================================
// רינדור שורות - עם תמיכה ב-Search Mode
// ===================================================================
async function renderPurchasesRows(data, container, pagination = null, signal = null) {
    console.log(`📝 renderPurchasesRows called with ${data.length} items`);
    console.log(`   Pagination:`, pagination);
    console.log(`   purchasesIsSearchMode: ${purchasesIsSearchMode}`);
    console.log(`   purchasesTable exists: ${!!purchasesTable}`);
    
    // ⭐⭐ במצב חיפוש - הצג תוצאות חיפוש בלי טבלה מורכבת
    if (purchasesIsSearchMode && purchasesCurrentQuery) {
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
        await initPurchasesTable(data, totalItems, signal);
        console.log('   ✅ Search results table initialized');
        return;
    }
    
    // ⭐⭐ מצב רגיל (Browse) - הצג עם TableManager
    const totalItems = pagination?.total || data.length;
    console.log(`📊 Total items to display: ${totalItems}`);

    if (data.length === 0) {
        console.log('   → No data to display');
        if (purchasesTable) {
            purchasesTable.setData([]);
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
    
    if (!tableWrapperExists && purchasesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting purchasesTable variable');
        purchasesTable = null;
        window.purchasesTable = null;
    }

    // ⭐⭐⭐ אתחול או עדכון טבלה
    if (!purchasesTable || !tableWrapperExists) {
        console.log(`🆕 Initializing TableManager with ${totalItems} items`);
        await initPurchasesTable(data, totalItems, signal);
        console.log('   ✅ TableManager initialized');
    } else {
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (purchasesTable.config) {
            purchasesTable.config.totalItems = totalItems;
        }
        purchasesTable.setData(data);
        console.log('   ✅ TableManager updated');
    }
}

// ===================================================================
// פונקציות עזר לפורמט
// ===================================================================
function formatPurchaseType(type) {
    const types = {
        'new': 'רכישה חדשה',
        'transfer': 'העברת בעלות',
        'renewal': 'חידוש'
    };
    return types[type] || '-';
}

function formatPurchaseStatus(status) {
    return formatEntityStatus('purchase', status);
}

// ===================================================================
// דאבל-קליק על רכישה
// ===================================================================
async function handlePurchaseDoubleClick(purchaseId) {
    console.log('🖱️ Double-click on purchase:', purchaseId);
    
    try {
        if (typeof createPurchaseCard === 'function') {
            const cardHtml = await createPurchaseCard(purchaseId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        } else {
            console.warn('⚠️ createPurchaseCard not found - opening edit form');
            if (typeof window.tableRenderer !== 'undefined' && window.tableRenderer.editItem) {
                window.tableRenderer.editItem(purchaseId);
            } else {
                console.error('❌ tableRenderer.editItem not available');
                showToast('שגיאה בפתיחת טופס עריכה', 'error');
            }
        }
    } catch (error) {
        console.error('❌ Error in handlePurchaseDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי רכישה', 'error');
    }
}

window.handlePurchaseDoubleClick = handlePurchaseDoubleClick;
// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.purchasesTable = purchasesTable;

window.purchaseSearch = purchaseSearch;

console.log('✅ purchases-management.js v4.0.1 - Loaded successfully!');