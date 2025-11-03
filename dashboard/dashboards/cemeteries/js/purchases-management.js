/*
 * File: dashboards/dashboard/cemeteries/assets/js/purchases-management.js
 * Version: 3.2.1
 * Updated: 2025-11-03
 * Author: Malkiel
 * Change Summary:
 * - v3.2.1: ⭐ תיקון סופי - עכשיו זהה לחלוטין ל-customers-management
 *   - הוספת if (column.render === 'formatPurchaseStatus') ב-case 'status'
 *   - הוספת case 'type' עם if (column.render === 'formatPurchaseType')
 *   - הוספת formatPurchaseType() function
 *   - שמירה על case 'currency' (ייחודי לרכישות)
 *   - המבנה עכשיו זהה ממש - רק שמות משתנים שונים
 * - v3.2.0: אחידות מלאה עם customers-management
 *   - טעינת עמודות דינמית מ-PHP דרך loadColumnsFromConfig('purchase')
 * - v3.1.0: שיפורים והתאמה לארכיטקטורה המאוחדת
 * - v3.0.0: שיטה זהה לבתי עלמין - UniversalSearch + TableManager
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================

let currentPurchases = [];
let purchaseSearch = null;
let purchasesTable = null;
let editingPurchaseId = null;

// טעינת רכישות (הפונקציה הראשית)
async function loadPurchases() {
    console.log('📋 Loading purchases - v3.2.1 (זהה לחלוטין ל-customers)...');

    setActiveMenuItem('purchasesItem');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'purchase';
    window.currentParentId = null;

    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'purchase' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'purchase' });
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
        updateBreadcrumb({ purchase: { name: 'רכישות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול רכישות - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildPurchasesContainer();

    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (purchaseSearch && typeof purchaseSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous purchaseSearch instance...');
        purchaseSearch.destroy();
        purchaseSearch = null;
        window.purchaseSearch = null;
    }

    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh purchaseSearch instance...');
    await initPurchasesSearch();
    purchaseSearch.search();
    
    // טען סטטיסטיקות
    await loadPurchaseStats();
}

// ===================================================================
// ⭐ פונקציה חדשה - בניית המבנה של רכישות ב-main-container
// ===================================================================
async function buildPurchasesContainer() {
    console.log('🏗️ Building purchases container...');
    
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
    
    // ⭐ בנה את התוכן של רכישות
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="purchaseSearchSection" class="search-section"></div>
        
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
// אתחול UniversalSearch - שימוש בפונקציה גלובלית!
// ===================================================================
async function initPurchasesSearch() {
    purchaseSearch = window.initUniversalSearch({
        entityType: 'purchase',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/purchases-api.php',
        action: 'list',

        searchableFields: [
            {
                name: 'serialPurchaseId',
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
                name: 'customerId',
                label: 'מזהה לקוח',
                table: 'purchases',
                type: 'text',
                matchType: ['exact']
            },
            {
                name: 'graveName',
                label: 'שם קבר',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'graveId',
                label: 'מזהה קבר',
                table: 'purchases',
                type: 'text',
                matchType: ['exact']
            },
            {
                name: 'purchaseAmount',
                label: 'סכום רכישה',
                table: 'purchases',
                type: 'number',
                matchType: ['exact', 'greater', 'less', 'between']
            },
            {
                name: 'statusPurchase',
                label: 'סטטוס רכישה',
                table: 'purchases',
                type: 'select',
                matchType: ['exact'],
                options: [
                    { value: '1', label: 'פעיל' },
                    { value: '0', label: 'לא פעיל' }
                ]
            },
            {
                name: 'purchaseDate',
                label: 'תאריך רכישה',
                table: 'purchases',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'purchases',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['serialPurchaseId', 'customerName', 'graveName', 'purchaseAmount', 'purchaseDate', 'statusPurchase', 'createDate'],
        
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
            },

            onResults2: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'purchases found');
                
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentPurchases = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentPurchases = [...currentPurchases, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentPurchases.length}`);
                }
            },

            onResults: (data) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'purchases');
                
                // ⭐ טיפול בדפים - מצטבר!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentPurchases = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentPurchases = [...currentPurchases, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentPurchases.length}`);
                }
                
                // ⭐ אין סינון client-side - זו רמת השורש!
                let filteredCount = currentPurchases.length;
                
                // ⭐⭐⭐ עדכן ישירות את purchaseSearch!
                if (purchaseSearch && purchaseSearch.state) {
                    purchaseSearch.state.totalResults = filteredCount;
                    if (purchaseSearch.updateCounter) {
                        purchaseSearch.updateCounter();
                    }
                }
                
                console.log('📊 Final count:', filteredCount);
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש: ' + error.message, 'error');
            },
            
            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    });
    
    // ⭐ עדכן את window.purchaseSearch מיד!
    window.purchaseSearch = purchaseSearch;
    
    return purchaseSearch;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
async function initPurchasesTable(data, totalItems = null) {
     const actualTotalItems = totalItems !== null ? totalItems : data.length;
   
    if (purchasesTable) {
        purchasesTable.config.totalItems = actualTotalItems;
        purchasesTable.setData(data);
        return purchasesTable;
    }

    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'purchase') {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${entityType}&section=table_columns`);
            
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
                switch (column.type) {
                    case 'date':
                        column.render = (item) => formatDate(item[column.field]);
                        break;
                        
                    case 'status':
                        // if (column.render === 'formatPurchaseStatus') {
                            column.render = (item) => formatPurchaseStatus(item[column.field]);
                        // }
                        break;
                        
                    case 'type':
                        if (column.render === 'formatPurchaseType') {
                            column.render = (item) => formatPurchaseType(item[column.field]);
                        }
                        break;
                        
                    case 'currency':
                        column.render = (item) => {
                            const value = item[column.field];
                            return value ? `₪${parseFloat(value).toLocaleString('he-IL')}` : '-';
                        };
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
                        // עמודת טקסט רגילה
                        if (!column.render) {
                            column.render = (item) => item[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
        } catch (error) {
            console.error('❌ Failed to load columns config:', error);
            // החזר מערך ריק במקרה של שגיאה
            return [];
        }
    }
    
    purchasesTable = new TableManager({
        tableSelector: '#mainTable',
        
        // containerWidth: '80vw',
        // fixedLayout: true,
        
        // scrolling: {
        //     enabled: true,
        //     headerHeight: '50px',
        //     itemsPerPage: 50,
        //     scrollThreshold: 300
        // },
        
        // ⭐ הוספת totalItems כפרמטר!
        totalItems: actualTotalItems,

        columns: await loadColumnsFromConfig('purchase'),
        
        // columns: [
        //     {
        //         field: 'serialPurchaseId',
        //         label: 'מספר רכישה',
        //         width: '130px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'customerName',
        //         label: 'שם לקוח',
        //         width: '180px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'graveName',
        //         label: 'שם קבר',
        //         width: '150px',
        //         type: 'text',
        //         sortable: true
        //     },
        //     {
        //         field: 'purchaseAmount',
        //         label: 'סכום',
        //         width: '120px',
        //         type: 'currency',
        //         sortable: true,
        //         render: (purchase) => {
        //             const value = purchase.purchaseAmount;
        //             return value ? `₪${parseFloat(value).toLocaleString('he-IL')}` : '-';
        //         }
        //     },
        //     {
        //         field: 'purchaseDate',
        //         label: 'תאריך רכישה',
        //         width: '130px',
        //         type: 'date',
        //         sortable: true,
        //         render: (purchase) => formatDate(purchase.purchaseDate)
        //     },
        //     {
        //         field: 'statusPurchase',
        //         label: 'סטטוס',
        //         width: '100px',
        //         type: 'number',
        //         sortable: true,
        //         render: (purchase) => formatPurchaseStatus(purchase.statusPurchase)
        //     },
        //     {
        //         field: 'createDate',
        //         label: 'תאריך יצירה',
        //         width: '130px',
        //         type: 'date',
        //         sortable: true,
        //         render: (purchase) => formatDate(purchase.createDate)
        //     },
        //     {
        //         field: 'actions',
        //         label: 'פעולות',
        //         width: '120px',
        //         sortable: false,
        //         render: (purchase) => `
        //             <button class="btn btn-sm btn-secondary" onclick="editPurchase('${purchase.unicId}')" title="עריכה">
        //                 <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
        //             </button>
        //             <button class="btn btn-sm btn-danger" onclick="deletePurchase('${purchase.unicId}')" title="מחיקה">
        //                 <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
        //             </button>
        //         `
        //     }
        // ],

        onRowDoubleClick: (purchase) => {
            handlePurchaseDoubleClick(purchase.unicId);
        },
        
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
            const count = purchasesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });

    // מאזין לאירוע גלילה לסוף - טען עוד נתונים
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && purchaseSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            // אם הגענו לתחתית והטעינה עוד לא בתהליך
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!purchaseSearch.state.isLoading && purchaseSearch.state.currentPage < purchaseSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    // בקש עמוד הבא מ-UniversalSearch
                    const nextPage = purchaseSearch.state.currentPage + 1;
                    
                    // עדכן את הדף הנוכחי
                    purchaseSearch.state.currentPage = nextPage;
                    purchaseSearch.state.isLoading = true;
                    
                    // בקש נתונים
                    await purchaseSearch.search();
                }
            }
        });
    }
    
    // ⭐ עדכן את window.purchasesTable מיד!
    window.purchasesTable = purchasesTable;
 
    return purchasesTable;
}

// ===================================================================
// רינדור שורות רכישות - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderPurchasesRows(data, container, pagination = null) {
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
    const totalItems = pagination?.total || data.length;
    
    if (data.length === 0) {
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
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && purchasesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting purchasesTable variable');
        purchasesTable = null;
        window.purchasesTable = null;
    }

    // עכשיו בדוק אם צריך לבנות מחדש
    if (!purchasesTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        initPurchasesTable(data, totalItems);  // ⭐ העברת totalItems!
    } else {    
        // ⭐ עדכן גם את totalItems ב-TableManager!
        if (purchasesTable.config) {
            purchasesTable.config.totalItems = totalItems;
        }
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (purchaseSearch && purchaseSearch.state) {
            const allData = purchaseSearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                purchasesTable.setData(allData);
                return;
            }
        }
        
        purchasesTable.setData(data);
    }
}

// ===================================================================
// פונקציות פורמט ועזר
// ===================================================================
function formatPurchaseType(type) {
    const types = {
        1: 'רגיל',
        2: 'מיוחד',
        3: 'אחר'
    };
    return types[type] || '-';
}

// פורמט סטטוס רכישה
function formatPurchaseStatus(status) {
    const statuses = {
        1: { text: 'פעיל', color: '#10b981' },
        0: { text: 'לא פעיל', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || statuses[1];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// ===================================================================
// פונקציות CRUD
// ===================================================================
async function deletePurchase(purchaseId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק רכישה זו?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=delete&id=${purchaseId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('הרכישה נמחקה בהצלחה', 'success');
            
            if (purchaseSearch) {
                purchaseSearch.refresh();
            }
        } else {
            showToast(data.error || 'שגיאה במחיקת רכישה', 'error');
        }
    } catch (error) {
        console.error('Error deleting purchase:', error);
        showToast('שגיאה במחיקת רכישה', 'error');
    }
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadPurchaseStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('Purchase stats:', data.data);
        }
    } catch (error) {
        console.error('Error loading purchase stats:', error);
    }
}

// הצגת הודעת Toast
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
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

// פונקציה לרענון נתונים
async function refreshData() {
    if (purchaseSearch) {
        purchaseSearch.refresh();
    }
}

// פונקציה לבדיקת סטטוס הטעינה
function checkScrollStatus() {
    if (!purchasesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = purchasesTable.getFilteredData().length;
    const displayed = purchasesTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(purchasesTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// פונקציה לטיפול בדאבל-קליק על רכישה
// ===================================================
async function handlePurchaseDoubleClick(purchaseId) {
    console.log('🖱️ Double-click on purchase:', purchaseId);
    
    try {
        // יצירת והצגת כרטיס
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
// Backward Compatibility
// ===================================================================
window.loadAllPurchases = loadPurchases;  // ✅ Alias לשם הישן

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.loadPurchases = loadPurchases;
window.deletePurchase = deletePurchase;
window.refreshData = refreshData;
window.purchasesTable = purchasesTable;
window.checkScrollStatus = checkScrollStatus;
window.purchaseSearch = purchaseSearch;