/*
 * File: dashboards/dashboard/cemeteries/assets/js/area-graves-management.js
 * Version: 1.2.2
 * Updated: 2025-10-28
 * Author: Malkiel
 * Change Summary:
 * - v1.2.2: תיקון קריטי - שינוי מיקום סינון client-side
 *   - הועבר הסינון מ-onResults ל-renderAreaGravesRows (כמו ב-blocks)
 *   - כעת renderAreaGravesRows מסנן לפי plot_id לפני הצגה
 * - v1.2.0: הוספת טעינה מדורגת כמו ב-customers
 * - v1.1.0: תיקון TableManager
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentAreaGraves = [];
let areaGraveSearch = null;
let areaGravesTable = null;
let editingAreaGraveId = null;

// ⭐ שמירת ה-plot context הנוכחי
let currentPlotId = null;
let currentPlotName = null;

// ===================================================================
// טעינת אחוזות קבר (הפונקציה הראשית)
// ===================================================================
async function loadAreaGraves(plotId = null, plotName = null, forceReset = false) {
    console.log('📋 Loading area graves - v1.2.2 (תוקן סינון client-side)...');
    
    // ⭐ לוגיקת סינון
    if (plotId === null && plotName === null && !forceReset) {
        if (window.currentPlotId !== null || currentPlotId !== null) {
            console.log('🔄 Resetting filter - called from menu without params');
            currentPlotId = null;
            currentPlotName = null;
            window.currentPlotId = null;
            window.currentPlotName = null;
        }
        console.log('🔍 Plot filter: None (showing all area graves)');
    } else if (forceReset) {
        console.log('🔄 Force reset filter');
        currentPlotId = null;
        currentPlotName = null;
        window.currentPlotId = null;
        window.currentPlotName = null;
    } else {
        console.log('🔄 Setting filter:', { plotId, plotName });
        currentPlotId = plotId;
        currentPlotName = plotName;
        window.currentPlotId = plotId;
        window.currentPlotName = plotName;
    }
    
    console.log('🔍 Final filter:', { plotId: currentPlotId, plotName: currentPlotName });
        
    window.currentPlotId = currentPlotId;
    window.currentPlotName = currentPlotName;
    
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('areaGravesItem');
    }
    
    // // עדכן את הסוג הנוכחי
    // window.currentType = 'area_grave';
    // window.currentParentId = plotId;
    
    // // ⭐ נקה
    // if (typeof DashboardCleaner !== 'undefined') {
    //     DashboardCleaner.clear({ targetLevel: 'area_grave' });
    // } else if (typeof clearDashboard === 'function') {
    //     clearDashboard({ targetLevel: 'area_grave' });
    // }
    
    // if (typeof clearAllSidebarSelections === 'function') {
    //     clearAllSidebarSelections();
    // }
    
    // if (typeof updateAddButtonText === 'function') {
    //     updateAddButtonText();
    // }
    
    // // עדכן breadcrumb
    // if (typeof updateBreadcrumb === 'function') {
    //     const breadcrumbData = { 
    //         area_grave: { name: plotName ? `אחוזות קבר של ${plotName}` : 'אחוזות קבר' }
    //     };
    //     if (plotId && plotName) {
    //         breadcrumbData.plot = { id: plotId, name: plotName };
    //     }
    //     updateBreadcrumb(breadcrumbData);
    // }
    
    // // עדכון כותרת החלון
    // document.title = plotName ? `אחוזות קבר - ${plotName}` : 'ניהול אחוזות קבר - מערכת בתי עלמין';
    
    // // ⭐ בנה מבנה
    // await buildAreaGravesContainer(plotId, plotName);
    
    // // ⭐ השמד חיפוש קודם
    // if (areaGraveSearch && typeof areaGraveSearch.destroy === 'function') {
    //     console.log('🗑️ Destroying previous areaGraveSearch instance...');
    //     areaGraveSearch.destroy();
    //     areaGraveSearch = null;
    //     window.areaGraveSearch = null;
    // }
    
    // // אתחל חיפוש חדש
    // console.log('🆕 Creating fresh areaGraveSearch instance...');
    // await initAreaGravesSearch(plotId);
    // areaGraveSearch.search();
    
    // // טען סטטיסטיקות
    // await loadAreaGraveStats(plotId);
}

// ===================================================================
// בניית המבנה
// ===================================================================
async function buildAreaGravesContainer(plotId = null, plotName = null) {
    console.log('🏗️ Building area graves container...');
    
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
    
    const filterIndicator = plotId && plotName ? `
        <div class="filter-indicator" style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">🏘️</span>
                <div>
                    <div style="font-size: 12px; opacity: 0.9;">מציג אחוזות קבר עבור</div>
                    <div style="font-size: 16px; font-weight: 600;">${plotName}</div>
                </div>
            </div>
            <button onclick="loadAreaGraves(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                ✕ הצג הכל
            </button>
        </div>
    ` : '';
    
    mainContainer.innerHTML = `
        ${filterIndicator}
        
        <div id="areaGraveSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען אחוזות קבר...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Area graves container built');
}

// ===================================================================
// אתחול UniversalSearch - עם Pagination!
// ===================================================================
async function initAreaGravesSearch(plotId = null) {
    const config = {
        entityType: 'area_grave',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/area-graves-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'areaGraveNameHe',
                label: 'שם אחוזת קבר',
                table: 'areaGraves',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'coordinates',
                label: 'קואורדינטות',
                table: 'areaGraves',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'gravesList',
                label: 'רשימת קברים',
                table: 'areaGraves',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'graveType',
                label: 'סוג קבר',
                table: 'areaGraves',
                type: 'select',
                options: [
                    { value: '', label: 'הכל' },
                    { value: '1', label: 'שדה' },
                    { value: '2', label: 'רוויה' },
                    { value: '3', label: 'סנהדרין' }
                ],
                matchType: ['exact']
            },
            {
                name: 'lineId',
                label: 'מזהה שורה',
                table: 'areaGraves',
                type: 'text',
                matchType: ['exact']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'areaGraves',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['areaGraveNameHe', 'coordinates', 'graveType', 'row_name', 'graves_count', 'createDate'],
        
        searchContainerSelector: '#areaGraveSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש אחוזות קבר לפי שם, קואורדינטות, סוג...',
        itemsPerPage: 200,  // ⭐ שינוי! טעינה מדורגת
        
        renderFunction: renderAreaGravesRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for area graves');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'area graves found');
                
                // ⭐ טיפול בדפים - מצטבר כמו ב-customers!
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentAreaGraves = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentAreaGraves = [...currentAreaGraves, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentAreaGraves.length}`);
                }
                
                // ⭐⭐ הסרת סינון מכאן! הסינון עבר ל-renderAreaGravesRows!
                console.log('📊 Final count:', data.pagination?.total || data.data.length);
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש אחוזות קבר', 'error');
            },

            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    };
    
    if (plotId) {
        console.log('🎯 Adding plotId filter to API request:', plotId);
        config.additionalParams = { plotId: plotId };
    }
    
    areaGraveSearch = window.initUniversalSearch(config);
    window.areaGraveSearch = areaGraveSearch;
    
    return areaGraveSearch;
}

// ===================================================================
// אתחול TableManager - עם Scroll Loading!
// ===================================================================
async function initAreaGravesTable(data, totalItems = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    console.log(`📊 Initializing TableManager for area graves with ${data.length} items (total: ${actualTotalItems})...`);
    
    if (areaGravesTable) {
        areaGravesTable.config.totalItems = actualTotalItems;
        areaGravesTable.setData(data);
        return areaGravesTable;
    }

    areaGravesTable = new TableManager({
        tableSelector: '#mainTable',
        
        totalItems: actualTotalItems,

        columns: [
            {
                field: 'areaGraveNameHe',
                label: 'שם אחוזת קבר',
                width: '200px',
                sortable: true,
                render: (areaGrave) => {
                    return `<a href="#" onclick="handleAreaGraveDoubleClick('${areaGrave.unicId}', '${(areaGrave.areaGraveNameHe || '').replace(/'/g, "\\'")}'); return false;" 
                               style="color: #2563eb; text-decoration: none; font-weight: 500;">
                        ${areaGrave.areaGraveNameHe || 'ללא שם'}
                    </a>`;
                }
            },
            {
                field: 'coordinates',
                label: 'קואורדינטות',
                width: '150px',
                sortable: true,
                render: (areaGrave) => {
                    const coords = areaGrave.coordinates || '-';
                    return `<span style="font-family: monospace; font-size: 12px;">${coords}</span>`;
                }
            },
            {
                field: 'graveType',
                label: 'סוג קבר',
                width: '120px',
                sortable: true,
                render: (areaGrave) => {
                    const typeName = getGraveTypeName(areaGrave.graveType);
                    return `<span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${typeName}</span>`;
                }
            },
            {
                field: 'row_name',
                label: 'שורה',
                width: '150px',
                sortable: true,
                render: (areaGrave) => {
                    const rowName = areaGrave.row_name || areaGrave.lineNameHe || '-';
                    return `<span style="color: #6b7280;">📏 ${rowName}</span>`;
                }
            },
            {
                field: 'graves_count',
                label: 'קברים',
                width: '80px',
                type: 'number',
                sortable: true,
                render: (areaGrave) => {
                    const count = areaGrave.graves_count || 0;
                    return `<span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">${count}</span>`;
                }
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (areaGrave) => formatDate(areaGrave.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (areaGrave) => `
                    <button class="btn btn-sm btn-secondary" onclick="editAreaGrave('${areaGrave.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAreaGrave('${areaGrave.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                `
            }
        ],
        
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
            const count = areaGravesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });

    // ⭐ מאזין לגלילה - טען עוד דפים! (כמו ב-customers)
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && areaGraveSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            // אם הגענו לתחתית והטעינה עוד לא בתהליך
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!areaGraveSearch.state.isLoading && areaGraveSearch.state.currentPage < areaGraveSearch.state.totalPages) {
                    console.log('📥 Reached bottom, loading more data...');
                    
                    const nextPage = areaGraveSearch.state.currentPage + 1;
                    
                    areaGraveSearch.state.currentPage = nextPage;
                    areaGraveSearch.state.isLoading = true;
                    
                    await areaGraveSearch.search();
                }
            }
        });
    }
    
    window.areaGravesTable = areaGravesTable;
    
    return areaGravesTable;
}

// ===================================================================
// רינדור שורות - עם סינון client-side! (⭐⭐ כמו ב-blocks!)
// ===================================================================
function renderAreaGravesRows(data, container, pagination = null) {
    console.log(`📝 renderAreaGravesRows called with ${data.length} items`);
    
    // ⭐⭐ סינון client-side לפי plotId - זה הפתרון!
    let filteredData = data;
    if (currentPlotId) {
        filteredData = data.filter(ag => ag.plot_id === currentPlotId);
        console.log(`🎯 Client-side filtered: ${data.length} → ${filteredData.length} area graves`);
    }
    
    // ⭐ עדכן את totalItems להיות המספר המסונן!
    const totalItems = filteredData.length;
    
    console.log(`📊 Total items to display: ${totalItems}`);

    if (filteredData.length === 0) {
        if (areaGravesTable) {
            areaGravesTable.setData([]);
        }
        
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 60px;">
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
    if (!tableWrapperExists && areaGravesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting areaGravesTable variable');
        areaGravesTable = null;
        window.areaGravesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!areaGravesTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        console.log(`🏗️ Creating new TableManager with ${totalItems} items`);
        initAreaGravesTable(filteredData, totalItems);
    } else {
        // ⭐ עדכן גם את totalItems ב-TableManager!
        console.log(`♻️ Updating TableManager with ${totalItems} items`);
        if (areaGravesTable.config) {
            areaGravesTable.config.totalItems = totalItems;
        }
        
        areaGravesTable.setData(filteredData);
    }
    
    // ⭐ עדכן את התצוגה של UniversalSearch
    if (areaGraveSearch) {
        areaGraveSearch.state.totalResults = totalItems;
        areaGraveSearch.updateCounter();
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
// פונקציית עזר לשם סוג קבר
// ===================================================================
function getGraveTypeName(type) {
    const types = {
        1: 'שדה',
        2: 'רוויה',
        3: 'סנהדרין'
    };
    return types[type] || 'לא מוגדר';
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadAreaGraveStats(plotId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/area-graves-api.php?action=stats';
        if (plotId) {
            url += `&plotId=${plotId}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Area grave stats:', result.data);
            
            if (document.getElementById('totalAreaGraves')) {
                document.getElementById('totalAreaGraves').textContent = result.data.total_area_graves || 0;
            }
            if (document.getElementById('totalGraves')) {
                document.getElementById('totalGraves').textContent = result.data.total_graves || 0;
            }
            if (document.getElementById('newThisMonth')) {
                document.getElementById('newThisMonth').textContent = result.data.new_this_month || 0;
            }
        }
    } catch (error) {
        console.error('Error loading area grave stats:', error);
    }
}

// ===================================================================
// עריכת אחוזת קבר
// ===================================================================
async function editAreaGrave(areaGraveId) {
    console.log('✏️ Editing area grave:', areaGraveId);
    editingAreaGraveId = areaGraveId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/area-graves-api.php?action=get&id=${areaGraveId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה בטעינת נתוני אחוזת הקבר');
        }
        
        const areaGrave = result.data;
        
        if (typeof openFormModal === 'function') {
            openFormModal('area_grave', areaGrave);
        } else {
            console.log('📝 Area grave data:', areaGrave);
            alert('פונקציית openFormModal לא זמינה');
        }
        
    } catch (error) {
        console.error('Error editing area grave:', error);
        showToast('שגיאה בטעינת נתוני אחוזת הקבר', 'error');
    }
}

// ===================================================================
// מחיקת אחוזת קבר
// ===================================================================
async function deleteAreaGrave(areaGraveId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את אחוזת הקבר?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/area-graves-api.php?action=delete&id=${areaGraveId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת אחוזת הקבר');
        }
        
        showToast('אחוזת הקבר נמחקה בהצלחה', 'success');
        
        if (areaGraveSearch) {
            areaGraveSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting area grave:', error);
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
    if (areaGraveSearch) {
        areaGraveSearch.refresh();
    }
}

// ===================================================================
// בדיקת סטטוס טעינה
// ===================================================================
function checkScrollStatus() {
    if (!areaGravesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = areaGravesTable.getFilteredData().length;
    const displayed = areaGravesTable.getDisplayedData().length;
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
// דאבל-קליק על אחוזת קבר
// ===================================================================
async function handleAreaGraveDoubleClick(areaGraveId, areaGraveName) {
    console.log('🖱️ Double-click on area grave:', areaGraveName, areaGraveId);
    
    try {
        if (typeof createAreaGraveCard === 'function') {
            const cardHtml = await createAreaGraveCard(areaGraveId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        }
        
        console.log('🪦 Loading graves for area grave:', areaGraveName);
        if (typeof loadGraves === 'function') {
            loadGraves(areaGraveId, areaGraveName);
        } else {
            console.warn('loadGraves function not found');
        }
        
    } catch (error) {
        console.error('❌ Error in handleAreaGraveDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי אחוזת הקבר', 'error');
    }
}

window.handleAreaGraveDoubleClick = handleAreaGraveDoubleClick;

// ===================================================================
// Backward Compatibility
// ===================================================================
window.loadAllAreaGraves = loadAreaGraves;

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.loadAreaGraves = loadAreaGraves;
window.deleteAreaGrave = deleteAreaGrave;
window.editAreaGrave = editAreaGrave;
window.refreshData = refreshData;
window.areaGravesTable = areaGravesTable;
window.checkScrollStatus = checkScrollStatus;
window.currentPlotId = currentPlotId;
window.currentPlotName = currentPlotName;
window.areaGraveSearch = areaGraveSearch;

console.log('✅ Area Graves Management Module Loaded - v1.2.2 (Client-Side Filter Fixed)');
console.log('💡 Commands: checkScrollStatus() - בדוק כמה רשומות נטענו');