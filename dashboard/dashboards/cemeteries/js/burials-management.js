/*
 * File: dashboards/dashboard/cemeteries/assets/js/burials-management.js
 * Version: 1.0.1
 * Updated: 2025-11-04
 * Author: Malkiel
 * Change Summary:
 * - v1.0.1: ⭐ תיקון סופי - עכשיו זהה לחלוטין ל-purchases-management
 *   - הוספת כל ההערות המסבירות
 *   - הוספת כל השורות הריקות
 *   - הוספת case 'time' ו-case 'boolean' במקום 'currency'
 *   - searchableFields מותאם לקבורות (9 שדות)
 *   - הוספת כפתור "צפייה" ב-actions (3 כפתורים)
 *   - המבנה עכשיו זהה ממש - רק שמות משתנים שונים
 * - v1.0.0: מבנה חדש לחלוטין - זהה ל-purchases
 *   - שימוש ב-UniversalSearch + TableManager
 *   - טעינת עמודות דינמית מ-PHP דרך loadColumnsFromConfig('burial')
 *   - מאזין גלילה + onResults עם state.totalResults
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================

let currentBurials = [];
let burialSearch = null;
let burialsTable = null;
let editingBurialId = null;

// טעינת קבורות (הפונקציה הראשית)
async function loadBurials2() {
    console.log('📋 Loading burials - v1.0.1 (זהה לחלוטין ל-customers)...');

    // עדכן את הסוג הנוכחי
    window.currentType = 'burial';
    window.currentParentId = null;

    // ⭐ עדכן גם את tableRenderer!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'burial';
    }

    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'burial' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'burial' });
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
            
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('burialsItem');
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ burial: { name: 'קבורות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול קבורות - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildBurialsContainer();

    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (burialSearch && typeof burialSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous burialSearch instance...');
        burialSearch.destroy();
        burialSearch = null;
        window.burialSearch = null;
    }

    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh burialSearch instance...');
    await initBurialsSearch();
    burialSearch.search();
    
    // טען סטטיסטיקות
    await loadBurialStats();
}

async function loadBurials() {
    console.log('📋 Loading burials - v1.0.2-debug...');
    
    // 🔍 דיבאג - לפני עדכון
    console.log('🔍 DEBUG [loadBurials] - BEFORE UPDATE:');
    console.log('   window.currentType:', window.currentType);
    console.log('   tableRenderer exists:', typeof window.tableRenderer !== 'undefined');
    if (window.tableRenderer) {
        console.log('   tableRenderer.currentType:', window.tableRenderer.currentType);
    }

    // עדכן את הסוג הנוכחי
    window.currentType = 'burial';
    window.currentParentId = null;
    
    // 🔍 דיבאג - אחרי עדכון
    console.log('🔍 DEBUG [loadBurials] - AFTER UPDATE:');
    console.log('   window.currentType:', window.currentType);
    if (window.tableRenderer) {
        console.log('   tableRenderer.currentType:', window.tableRenderer.currentType);
    }

    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'burial' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'burial' });
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
            
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('burialsItem');
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ burial: { name: 'קבורות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול קבורות - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildBurialsContainer();

    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (burialSearch && typeof burialSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous burialSearch instance...');
        burialSearch.destroy();
        burialSearch = null;
        window.burialSearch = null;
    }

    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh burialSearch instance...');
    await initBurialsSearch();
    burialSearch.search();
    
    // טען סטטיסטיקות
    await loadBurialStats();
    
    // 🔍 דיבאג סופי - אחרי שהכל נטען
    console.log('🔍 DEBUG [loadBurials] - FINAL STATE:');
    console.log('   window.currentType:', window.currentType);
    if (window.tableRenderer) {
        console.log('   tableRenderer.currentType:', window.tableRenderer.currentType);
    }
}

// ===================================================================
// ⭐ פונקציה חדשה - בניית המבנה של קבורות ב-main-container
// ===================================================================
async function buildBurialsContainer() {
    console.log('🏗️ Building burials container...');
    
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
    
    // ⭐ בנה את התוכן של קבורות
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="burialSearchSection" class="search-section"></div>
        
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
// אתחול UniversalSearch - שימוש בפונקציה גלובלית!
// ===================================================================
async function initBurialsSearch() {
    burialSearch = window.initUniversalSearch({
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
            },

            onResults2: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'burials found');
                
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentBurials = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentBurials = [...currentBurials, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentBurials.length}`);
                }
            },

            onResults: (data) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'burials');
                
                // ⭐ טיפול בדפים - מצטבר!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentBurials = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentBurials = [...currentBurials, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentBurials.length}`);
                }
                
                // ⭐ אין סינון client-side - זו רמת השורש!
                let filteredCount = currentBurials.length;
                
                // ⭐⭐⭐ עדכן ישירות את burialSearch!
                if (burialSearch && burialSearch.state) {
                    burialSearch.state.totalResults = filteredCount;
                    if (burialSearch.updateCounter) {
                        burialSearch.updateCounter();
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
    
    // ⭐ עדכן את window.burialSearch מיד!
    window.burialSearch = burialSearch;
    
    return burialSearch;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
async function initBurialsTable(data, totalItems = null) {
     const actualTotalItems = totalItems !== null ? totalItems : data.length;
   
    if (burialsTable) {
        burialsTable.config.totalItems = actualTotalItems;
        burialsTable.setData(data);
        return burialsTable;
    }

    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'burial') {
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
                        // if (column.render === 'formatBurialStatus') {
                            column.render = (item) => formatBurialStatus(item[column.field]);
                        // }
                        break;
                        
                    case 'type':
                        if (column.render === 'formatBurialType') {
                            column.render = (item) => formatBurialType(item[column.field]);
                        }
                        break;
                        
                    case 'time':
                        column.render = (item) => {
                            const value = item[column.field];
                            return value ? value.substring(0, 5) : '-';
                        };
                        break;
                        
                    case 'boolean':
                        column.render = (item) => {
                            const value = item[column.field];
                            if (value === 'כן' || value === 1 || value === true) {
                                return '<span style="color: green;">✓</span>';
                            }
                            return '<span style="color: #ccc;">✗</span>';
                        };
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-info" 
                                    onclick="event.stopPropagation(); viewBurial('${item.unicId}')" 
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

    async function loadColumnsFromConfig3(entityType) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/table-columns-api.php?entity=${entityType}`);
            const data = await response.json();
            
            if (!data.success || !data.columns) {
                throw new Error('Failed to load columns configuration');
            }
            
            const columns = data.columns.map(column => {
                switch (column.type) {
                    // ... כל ה-cases האחרים ...
                    
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-info" 
                                    onclick="event.stopPropagation(); console.log('🔍 [VIEW] Clicked burial:', '${item.unicId}'); viewBurial('${item.unicId}')" 
                                    title="צפייה">
                                <svg class="icon"><use xlink:href="#icon-view"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); 
                                            console.log('🔍 [EDIT CLICK] burialId:', '${item.unicId}'); 
                                            console.log('🔍 [EDIT CLICK] window.currentType:', window.currentType); 
                                            console.log('🔍 [EDIT CLICK] tableRenderer.currentType:', window.tableRenderer?.currentType);
                                            window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); console.log('🔍 [DELETE] Clicked burial:', '${item.unicId}'); deleteBurial('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;
                        
                    default:
                        if (!column.render) {
                            column.render = (item) => item[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
        } catch (error) {
            console.error('❌ Failed to load columns config:', error);
            return [];
        }
    }
    
    burialsTable = new TableManager({
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

        columns: await loadColumnsFromConfig('burial'),

        onRowDoubleClick: (burial) => {
            handleBurialDoubleClick(burial.unicId);
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
            const count = burialsTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });

    // מאזין לאירוע גלילה לסוף - טען עוד נתונים
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && burialSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            // אם הגענו לתחתית והטעינה עוד לא בתהליך
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!burialSearch.state.isLoading && burialSearch.state.currentPage < burialSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    // בקש עמוד הבא מ-UniversalSearch
                    const nextPage = burialSearch.state.currentPage + 1;
                    
                    // עדכן את הדף הנוכחי
                    burialSearch.state.currentPage = nextPage;
                    burialSearch.state.isLoading = true;
                    
                    // בקש נתונים
                    await burialSearch.search();
                }
            }
        });
    }
    
    // ⭐ עדכן את window.burialsTable מיד!
    window.burialsTable = burialsTable;
 
    return burialsTable;
}

// ===================================================================
// רינדור שורות קבורות - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderBurialsRows(data, container, pagination = null) {
    console.log(`📝 renderBurialsRows called with ${data.length} items`);
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
    const totalItems = pagination?.total || data.length;
    
    if (data.length === 0) {
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
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && burialsTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting burialsTable variable');
        burialsTable = null;
        window.burialsTable = null;
    }

    // עכשיו בדוק אם צריך לבנות מחדש
    if (!burialsTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        initBurialsTable(data, totalItems);  // ⭐ העברת totalItems!
    } else {    
        // ⭐ עדכן גם את totalItems ב-TableManager!
        if (burialsTable.config) {
            burialsTable.config.totalItems = totalItems;
        }
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (burialSearch && burialSearch.state) {
            const allData = burialSearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                burialsTable.setData(allData);
                return;
            }
        }
        
        burialsTable.setData(data);
    }
}

// ===================================================================
// פונקציות פורמט ועזר
// ===================================================================
function formatBurialType(type) {
    const types = {
        1: 'רגיל',
        2: 'מיוחד',
        3: 'אחר'
    };
    return types[type] || '-';
}

// פורמט סטטוס קבורה
function formatBurialStatus(status) {
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
async function deleteBurial(burialId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק קבורה זו?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=delete&id=${burialId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('הקבורה נמחקה בהצלחה', 'success');
            
            if (burialSearch) {
                burialSearch.refresh();
            }
        } else {
            showToast(data.error || 'שגיאה במחיקת קבורה', 'error');
        }
    } catch (error) {
        console.error('Error deleting burial:', error);
        showToast('שגיאה במחיקת קבורה', 'error');
    }
}

// צפייה בקבורה
async function viewBurial(burialId) {
    console.log('👁️ View burial:', burialId);
    
    try {
        if (typeof createBurialCard === 'function') {
            const cardHtml = await createBurialCard(burialId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        } else {
            console.warn('⚠️ createBurialCard not found');
            showToast('פונקציית תצוגה לא זמינה', 'warning');
        }
    } catch (error) {
        console.error('❌ Error in viewBurial:', error);
        showToast('שגיאה בטעינת פרטי קבורה', 'error');
    }
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadBurialStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/burials-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('Burial stats:', data.data);
        }
    } catch (error) {
        console.error('Error loading burial stats:', error);
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
    if (burialSearch) {
        burialSearch.refresh();
    }
}

// פונקציה לבדיקת סטטוס הטעינה
function checkScrollStatus() {
    if (!burialsTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = burialsTable.getFilteredData().length;
    const displayed = burialsTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(burialsTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// פונקציה לטיפול בדאבל-קליק על קבורה
// ===================================================
async function handleBurialDoubleClick(burialId) {
    console.log('🖱️ Double-click on burial:', burialId);
    
    try {
        // יצירת והצגת כרטיס
        if (typeof createBurialCard === 'function') {
            const cardHtml = await createBurialCard(burialId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        } else {
            console.warn('⚠️ createBurialCard not found - opening edit form');
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
window.loadBurials = loadBurials;
window.deleteBurial = deleteBurial;
window.viewBurial = viewBurial;
window.refreshData = refreshData;
window.burialsTable = burialsTable;
window.checkScrollStatus = checkScrollStatus;
window.burialSearch = burialSearch;