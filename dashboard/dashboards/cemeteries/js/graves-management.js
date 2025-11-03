/*
 * File: dashboards/dashboard/cemeteries/assets/js/graves-management.js
 * Version: 1.0.0
 * Updated: 2025-11-03
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת מודול ניהול קברים
 *   - תמיכה ב-30,000+ רשומות עם pagination (200 לדף)
 *   - סינון client-side לפי areaGraveId
 *   - טעינת כרטיס מלא של createAreaGraveCard
 *   - תמיכה במבנה הטבלה האמיתי
 *   - אתחול UniversalSearch עם new UniversalSearch()
 *   - דאבל-קליק פותח עריכה בלבד (ללא ניווט לילדים)
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentGraves = [];
let graveSearch = null;
let gravesTable = null;
let editingGraveId = null;

// ⭐ שמירת ה-area grave context הנוכחי
let currentAreaGraveId = null;
let currentAreaGraveName = null;

// ===================================================================
// טעינת קברים (הפונקציה הראשית)
// ===================================================================
async function loadGraves(areaGraveId = null, areaGraveName = null, forceReset = false) {
    console.log('📋 Loading graves - v1.0.0 (30K+ records with pagination)...');
    
    // ⭐ לוגיקת סינון
    if (areaGraveId === null && areaGraveName === null && !forceReset) {
        if (window.currentAreaGraveId !== null || currentAreaGraveId !== null) {
            console.log('🔄 Resetting filter - called from menu without params');
            currentAreaGraveId = null;
            currentAreaGraveName = null;
            window.currentAreaGraveId = null;
            window.currentAreaGraveName = null;
        }
        console.log('🔍 Area grave filter: None (showing all graves)');
    } else if (forceReset) {
        console.log('🔄 Force reset filter');
        currentAreaGraveId = null;
        currentAreaGraveName = null;
        window.currentAreaGraveId = null;
        window.currentAreaGraveName = null;
    } else {
        console.log('🔄 Setting filter:', { areaGraveId, areaGraveName });
        currentAreaGraveId = areaGraveId;
        currentAreaGraveName = areaGraveName;
        window.currentAreaGraveId = areaGraveId;
        window.currentAreaGraveName = areaGraveName;
    }
    
    console.log('🔍 Final filter:', { areaGraveId: currentAreaGraveId, areaGraveName: currentAreaGraveName });
        
    window.currentAreaGraveId = currentAreaGraveId;
    window.currentAreaGraveName = currentAreaGraveName;
    
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('gravesItem');
    }
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    
    // ⭐ נקה
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'grave' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'grave' });
    }
    
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        const breadcrumbData = { 
            grave: { name: areaGraveName ? `קברים של ${areaGraveName}` : 'קברים' }
        };
        if (areaGraveId && areaGraveName) {
            breadcrumbData.area_grave = { id: areaGraveId, name: areaGraveName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = areaGraveName ? `קברים - ${areaGraveName}` : 'ניהול קברים - מערכת בתי עלמין';
    
    // ⭐ בנה מבנה
    await buildGravesContainer(areaGraveId, areaGraveName);
    
    // ⭐ השמד חיפוש קודם
    if (graveSearch && typeof graveSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous graveSearch instance...');
        graveSearch.destroy();
        graveSearch = null;
        window.graveSearch = null;
    }
    
    // אתחל חיפוש חדש
    console.log('🆕 Creating fresh graveSearch instance...');
    await initGravesSearch(areaGraveId);
    graveSearch.search();
    
    // טען סטטיסטיקות
    await loadGraveStats(areaGraveId);
}

// ===================================================================
// בניית המבנה - עם כרטיס מלא של אחוזת הקבר! ⭐⭐⭐
// ===================================================================
async function buildGravesContainer(areaGraveId = null, areaGraveName = null) {
    console.log('🏗️ Building graves container...');
    
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
    
    // ⭐⭐⭐ טעינת כרטיס מלא של אחוזת הקבר במקום indicator פשוט!
    let topSection = '';
    if (areaGraveId && areaGraveName) {
        console.log('🎴 Creating full area grave card...');
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createAreaGraveCard === 'function') {
            try {
                topSection = await createAreaGraveCard(areaGraveId);
                console.log('✅ Area grave card created successfully');
            } catch (error) {
                console.error('❌ Error creating area grave card:', error);
            }
        } else {
            console.warn('⚠️ createAreaGraveCard function not found');
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            console.log('⚠️ Using simple filter indicator as fallback');
            topSection = `
                <div class="filter-indicator" style="background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🪦</span>
                        <div>
                            <div style="font-size: 12px; opacity: 0.9;">מציג קברים עבור</div>
                            <div style="font-size: 16px; font-weight: 600;">${areaGraveName}</div>
                        </div>
                    </div>
                    <button onclick="loadGraves(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        ✕ הצג הכל
                    </button>
                </div>
            `;
        }
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
        <div id="graveSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען קברים...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Graves container built');
}

// ===================================================================
// אתחול UniversalSearch - עם Pagination!
// ===================================================================
async function initGravesSearch(areaGraveId = null) {
    const config = {
        entityType: 'grave',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/graves-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'graveNameHe',
                label: 'שם קבר',
                table: 'graves',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'graveStatus',
                label: 'סטטוס',
                table: 'graves',
                type: 'select',
                options: [
                    { value: '', label: 'הכל' },
                    { value: '1', label: 'פנוי' },
                    { value: '2', label: 'נרכש' },
                    { value: '3', label: 'קבור' },
                    { value: '4', label: 'שמור' }
                ],
                matchType: ['exact']
            },
            {
                name: 'plotType',
                label: 'סוג חלקה',
                table: 'graves',
                type: 'select',
                options: [
                    { value: '', label: 'הכל' },
                    { value: '1', label: 'פטורה' },
                    { value: '2', label: 'חריגה' },
                    { value: '3', label: 'סגורה' }
                ],
                matchType: ['exact']
            },
            {
                name: 'isSmallGrave',
                label: 'גודל',
                table: 'graves',
                type: 'select',
                options: [
                    { value: '', label: 'הכל' },
                    { value: '1', label: 'קבר קטן' },
                    { value: '0', label: 'קבר רגיל' }
                ],
                matchType: ['exact']
            },
            {
                name: 'comments',
                label: 'הערות',
                table: 'graves',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'graves',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['graveNameHe', 'graveStatus', 'plotType', 'area_grave_name', 'isSmallGrave', 'createDate'],
        
        searchContainerSelector: '#graveSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש קברים לפי שם, סטטוס, סוג...',
        itemsPerPage: 999999,  // ⭐ 200 לכל דף!
        
        renderFunction: renderGravesRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for graves');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults2: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'graves found');
                
                // ⭐ טיפול בדפים - מצטבר
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    currentGraves = data.data;
                } else {
                    currentGraves = [...currentGraves, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentGraves.length}`);
                }
                
                console.log('📊 Final count:', data.pagination?.total || data.data.length);
            },

            onResults: (data) => {
                console.log('📦 API returned:', data.pagination?.total || data.data.length, 'graves');
                
                // ⭐ טיפול בדפים - מצטבר!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentGraves = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentGraves = [...currentGraves, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentGraves.length}`);
                }
                
                // ⭐ אם יש סינון - סנן את currentGraves!
                let filteredCount = currentGraves.length;
                if (currentAreaGraveId && currentGraves.length > 0) {
                    const filteredData = currentGraves.filter(grave => {
                        const graveAreaGraveId = grave.areaGraveId || grave.area_grave_id || grave.AreaGraveId;
                        return String(graveAreaGraveId) === String(currentAreaGraveId);
                    });
                    
                    console.log('⚠️ Client-side filter:', currentGraves.length, '→', filteredData.length, 'graves');
                    
                    // ⭐ עדכן את currentGraves
                    currentGraves = filteredData;
                    filteredCount = filteredData.length;
                    
                    // ⭐ עדכן את pagination.total
                    if (data.pagination) {
                        data.pagination.total = filteredCount;
                    }
                }
                
                // ⭐⭐⭐ עדכן ישירות את graveSearch!
                if (graveSearch && graveSearch.state) {
                    graveSearch.state.totalResults = filteredCount;
                    if (graveSearch.updateCounter) {
                        graveSearch.updateCounter();
                    }
                }
                
                console.log('📊 Final count:', filteredCount);
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש קברים', 'error');
            },

            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    };
    
    if (areaGraveId) {
        console.log('🎯 Adding areaGraveId filter to API request:', areaGraveId);
        config.additionalParams = { areaGraveId: areaGraveId };
    }
    
    graveSearch = window.initUniversalSearch(config);
    window.graveSearch = graveSearch;
    
    return graveSearch;
}

// ===================================================================
// אתחול TableManager - עם Scroll Loading!
// ===================================================================
async function initGravesTable(data, totalItems = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (gravesTable) {
        gravesTable.config.totalItems = actualTotalItems;
        gravesTable.setData(data);
        return gravesTable;
    }
        
    // טעינת העמודות מהשרת
    async function loadColumnsFromConfig(entityType = 'grave') {
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
                
                // טיפול בסוגי עמודות מיוחדות - ספציפי לקברים
                switch(col.type) {
                    case 'link':
                        // קישור לדאבל-קליק - לעריכת קבר
                        column.render = (grave) => {
                            return `<a href="#" onclick="handleGraveDoubleClick('${grave.unicId}', '${(grave.graveNameHe || '').replace(/'/g, "\\'")}'); return false;" 
                                    style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                ${grave.graveNameHe || 'ללא שם'}
                            </a>`;
                        };
                        break;
                        
                    case 'graveStatus':
                        // סטטוס קבר עם צבעים דינמיים
                        column.render = (grave) => {
                            const status = getGraveStatusInfo(grave.graveStatus);
                            return `<span style="background: ${status.color}; color: white; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${status.label}</span>`;
                        };
                        break;
                        
                    case 'plotType':
                        // סוג חלקה עם badge סגול
                        column.render = (grave) => {
                            const type = getPlotTypeName(grave.plotType);
                            return `<span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 4px; font-size: 12px;">${type}</span>`;
                        };
                        break;
                        
                    case 'area_grave_name':
                        // שם אחוזת קבר עם אייקון
                        column.render = (grave) => {
                            return `<span style="color: #6b7280;">🏘️ ${grave.area_grave_name || '-'}</span>`;
                        };
                        break;
                        
                    case 'isSmallGrave':
                        // גודל קבר עם אייקונים
                        column.render = (grave) => {
                            return grave.isSmallGrave ? 
                                `<span style="font-size: 12px;">📏 קטן</span>` : 
                                `<span style="font-size: 12px;">📐 רגיל</span>`;
                        };
                        break;
                        
                    case 'constructionCost':
                        // עלות בנייה עם סימן מטבע
                        column.render = (grave) => {
                            const cost = grave.constructionCost || '0';
                            return `<span style="font-family: monospace; font-size: 12px;">₪${cost}</span>`;
                        };
                        break;
                        
                    case 'date':
                        // תאריך - פורמט עברי
                        column.render = (grave) => formatDate(grave[column.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteGrave('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;
                        
                    default:
                        // עמודת טקסט רגילה
                        if (!column.render) {
                            column.render = (grave) => grave[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;
            
        } catch (error) {
            console.error('❌ Failed to load columns config:', error);
            // החזר מערך רק במקרה של שגיאה
            return [];
        }
    }

    gravesTable = new TableManager({
        tableSelector: '#mainTable',
        
        totalItems: actualTotalItems,

        columns: await loadColumnsFromConfig('grave'),

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
            const count = gravesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });

    // ⭐ מאזין לגלילה - טען עוד דפים!
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && graveSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!graveSearch.state.isLoading && graveSearch.state.currentPage < graveSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    const nextPage = graveSearch.state.currentPage + 1;
                    graveSearch.state.currentPage = nextPage;
                    graveSearch.state.isLoading = true;
                    await graveSearch.search();
                }
            }
        });
    }
    
    window.gravesTable = gravesTable;
    return gravesTable;
}

// ===================================================================
// רינדור שורות - עם סינון client-side! (⭐⭐ כמו ב-area-graves!)
// ===================================================================
function renderGravesRows(data, container, pagination = null) {
    console.log(`📝 renderGravesRows called with ${data.length} items`);
    
    // ⭐⭐ סינון client-side לפי areaGraveId - זה הפתרון!
    let filteredData = data;
    if (currentAreaGraveId) {
        filteredData = data.filter(g => g.areaGraveId === currentAreaGraveId);
        console.log(`🎯 Client-side filtered: ${data.length} → ${filteredData.length} graves`);
    }
    
    const totalItems = filteredData.length;
    console.log(`📊 Total items to display: ${totalItems}`);

    if (filteredData.length === 0) {
        if (gravesTable) {
            gravesTable.setData([]);
        }
        
        container.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px;">
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
    
    if (!tableWrapperExists && gravesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting gravesTable variable');
        gravesTable = null;
        window.gravesTable = null;
    }
    
    if (!gravesTable || !tableWrapperExists) {
        console.log(`🏗️ Creating new TableManager with ${totalItems} items`);
        initGravesTable(filteredData, totalItems);
    } else {
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (gravesTable.config) {
            gravesTable.config.totalItems = totalItems;
        }
        gravesTable.setData(filteredData);
    }
    
    if (graveSearch) {
        graveSearch.state.totalResults = totalItems;
        graveSearch.updateCounter();
    }
}

// ===================================================================
// פונקציות עזר
// ===================================================================
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

function getGraveStatusInfo(status) {
    const statuses = {
        1: { label: 'פנוי', color: '#10b981' },
        2: { label: 'נרכש', color: '#f59e0b' },
        3: { label: 'קבור', color: '#6b7280' },
        4: { label: 'שמור', color: '#3b82f6' }
    };
    return statuses[status] || { label: 'לא מוגדר', color: '#9ca3af' };
}

function getPlotTypeName(type) {
    const types = {
        1: 'פטורה',
        2: 'חריגה',
        3: 'סגורה'
    };
    return types[type] || 'לא מוגדר';
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadGraveStats(areaGraveId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/graves-api.php?action=stats';
        if (areaGraveId) {
            url += `&areaGraveId=${areaGraveId}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Grave stats:', result.data);
            
            if (document.getElementById('totalGraves')) {
                document.getElementById('totalGraves').textContent = result.data.total_graves || 0;
            }
            if (document.getElementById('availableGraves')) {
                document.getElementById('availableGraves').textContent = result.data.available || 0;
            }
            if (document.getElementById('buriedGraves')) {
                document.getElementById('buriedGraves').textContent = result.data.buried || 0;
            }
        }
    } catch (error) {
        console.error('Error loading grave stats:', error);
    }
}

// ===================================================================
// מחיקת קבר
// ===================================================================
async function deleteGrave(graveId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את הקבר?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=delete&id=${graveId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת הקבר');
        }
        
        showToast('הקבר נמחק בהצלחה', 'success');
        
        if (graveSearch) {
            graveSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting grave:', error);
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
async function refreshData() {
    if (graveSearch) {
        graveSearch.refresh();
    }
}

// ===================================================================
// בדיקת סטטוס טעינה
// ===================================================================
function checkScrollStatus() {
    if (!gravesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = gravesTable.getFilteredData().length;
    const displayed = gravesTable.getDisplayedData().length;
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
// דאבל-קליק על קבר
// ===================================================================
async function handleGraveDoubleClick(graveId, graveName) {
    console.log('🖱️ Double-click on grave:', graveName, graveId);
    
    try {
        // פתח עריכה - קבר הוא הרמה האחרונה
        console.log('✏️ Opening edit form for grave:', graveName);
        if (typeof window.tableRenderer !== 'undefined' && window.tableRenderer.editItem) {
            window.tableRenderer.editItem(graveId);
        } else {
            console.warn('tableRenderer.editItem not available');
            showToast('לא ניתן לפתוח טופס עריכה', 'error');
        }
        
    } catch (error) {
        console.error('❌ Error in handleGraveDoubleClick:', error);
        showToast('שגיאה בפתיחת טופס עריכה', 'error');
    }
}

window.handleGraveDoubleClick = handleGraveDoubleClick;

// ===================================================================
// Backward Compatibility
// ===================================================================
window.loadAllGraves = loadGraves;

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.loadGraves = loadGraves;
window.deleteGrave = deleteGrave;
window.refreshData = refreshData;
window.gravesTable = gravesTable;
window.checkScrollStatus = checkScrollStatus;
window.currentAreaGraveId = currentAreaGraveId;
window.currentAreaGraveName = currentAreaGraveName;
window.graveSearch = graveSearch;