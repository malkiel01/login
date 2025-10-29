/*
 * File: dashboards/dashboard/cemeteries/assets/js/area-graves-management.js
 * Version: 1.2.2
 * Updated: 2025-10-28
 * Author: Malkiel
 * Change Summary:
 * - v1.2.2: תיקון קריטי - הוספת סינון client-side בחזרה!
 *   - additionalParams לא מועבר נכון ל-API
 *   - פתרון: סינון כפול (server + client) כמו ב-blocks
 *   - renderAreaGravesRows מסנן לפי plot_id
 * - v1.2.1: ניסיון להסיר סינון client-side (לא עבד)
 * - v1.2.0: הוספת Pagination
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentAreaGraves = [];
let areaGraveSearch = null;
let areaGravesTable = null;
let editingAreaGraveId = null;

let currentPlotId = null;
let currentPlotName = null;


// ===================================================================
// טעינת אחוזות קבר
// ===================================================================
async function loadAreaGraves(plotId = null, plotName = null, forceReset = false) {
    console.log('📋 Loading area graves - v1.2.2 (תוקן סינון client-side)...');
    
    // ⭐ לוגיקת סינון
    if (plotId === null && plotName === null && !forceReset) {
        if (window.currentPlotId !== null || currentPlotId !== null) {
            console.log('🔄 Resetting filter');
            currentPlotId = null;
            currentPlotName = null;
            window.currentPlotId = null;
            window.currentPlotName = null;
        }
        console.log('🔍 Plot filter: None');
    } else if (forceReset) {
        console.log('🔄 Force reset');
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
       
    // עדכן את הסוג הנוכחי 
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
       
    // ⭐ נקה 
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'area_grave' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'area_grave' });
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
            area_grave: { name: plotName ? `אחוזות קבר של ${plotName}` : 'אחוזות קבר' }
        };
        if (plotId && plotName) {
            breadcrumbData.plot = { id: plotId, name: plotName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = plotName ? `אחוזות קבר - ${plotName}` : 'ניהול אחוזות קבר - מערכת בתי עלמין';
    
    // ⭐ בנה מבנה
    await buildAreaGravesContainer(plotId, plotName);
    
    // ⭐ השמד חיפוש קודם
    if (areaGraveSearch && typeof areaGraveSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous search...');
        areaGraveSearch.destroy();
        areaGraveSearch = null;
        window.areaGraveSearch = null;
    }
    
    // אתחל חיפוש חדש
    console.log('🆕 Creating fresh search...');
    await initAreaGravesSearch(plotId);
    areaGraveSearch.search();
    
    // טען סטטיסטיקות
    await loadAreaGraveStats(plotId);
}

// ===================================================================
// בניית מבנה
// ===================================================================
async function buildAreaGravesContainer(plotId = null, plotName = null) {
    console.log('🏗️ Building container...');
    
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
        console.log('⚠️ Creating main-container...');
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
                                <span class="visually-hidden">טוען...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Container built');
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
        
        placeholder: 'חיפוש אחוזות קבר...',
        itemsPerPage: 999999,  // ⭐ שינוי! טעינה מדורגת
        
        renderFunction: renderAreaGravesRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ Search initialized');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Raw results from API:', data.data.length, 'area graves');
                console.log('📦 Pagination total:', data.pagination?.total || data.total || 0);
                
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    // דף ראשון - התחל מחדש
                    currentAreaGraves = data.data;
                } else {
                    // דפים נוספים - הוסף לקיימים
                    currentAreaGraves = [...currentAreaGraves, ...data.data];
                    console.log(`📦 Added page ${currentPage}, total now: ${currentAreaGraves.length}`);
                }
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש', 'error');
            },

            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    };
    
    if (plotId) {
        console.log('🎯 Adding plotId to API (may not work, using client-side filter as backup):', plotId);
        config.additionalParams = { plotId: plotId };
    }
    
    areaGraveSearch = window.initUniversalSearch(config);
    window.areaGraveSearch = areaGraveSearch;
    
    return areaGraveSearch;
}

// ===================================================================
// אתחול TableManager
// ===================================================================
async function initAreaGravesTable(data, totalItems = null) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    console.log(`📊 Init TableManager: ${data.length} items (total: ${actualTotalItems})`);
    
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
                render: (ag) => {
                    return `<a href="#" onclick="handleAreaGraveDoubleClick('${ag.unicId}', '${(ag.areaGraveNameHe || '').replace(/'/g, "\\'")}'); return false;" 
                               style="color: #2563eb; text-decoration: none; font-weight: 500;">
                        ${ag.areaGraveNameHe || 'ללא שם'}
                    </a>`;
                }
            },
            {
                field: 'coordinates',
                label: 'קואורדינטות',
                width: '150px',
                sortable: true,
                render: (ag) => `<span style="font-family: monospace; font-size: 12px;">${ag.coordinates || '-'}</span>`
            },
            {
                field: 'graveType',
                label: 'סוג',
                width: '120px',
                sortable: true,
                render: (ag) => {
                    const type = getGraveTypeName(ag.graveType);
                    return `<span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${type}</span>`;
                }
            },
            {
                field: 'row_name',
                label: 'שורה',
                width: '150px',
                sortable: true,
                render: (ag) => `<span style="color: #6b7280;">📏 ${ag.row_name || ag.lineNameHe || '-'}</span>`
            },
            {
                field: 'graves_count',
                label: 'קברים',
                width: '80px',
                type: 'number',
                sortable: true,
                render: (ag) => {
                    const count = ag.graves_count || 0;
                    return `<span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">${count}</span>`;
                }
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (ag) => formatDate(ag.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (ag) => `
                    <button class="btn btn-sm btn-secondary" onclick="editAreaGrave('${ag.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAreaGrave('${ag.unicId}')" title="מחיקה">
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
            console.log(`📊 Sorted: ${field} ${order}`);
            showToast(`ממוין לפי ${field}`, 'info');
        },
        onFilter: (filters) => {
            console.log('🔍 Filters:', filters);
            showToast(`נמצאו ${areaGravesTable.getFilteredData().length} תוצאות`, 'info');
        }
    });

    // Scroll loading
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && areaGraveSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!areaGraveSearch.state.isLoading && areaGraveSearch.state.currentPage < areaGraveSearch.state.totalPages) {
                    console.log('📥 Loading more...');
                    areaGraveSearch.state.currentPage++;
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
// רינדור שורות - עם סינון client-side! (כמו ב-blocks)
// ===================================================================
function renderAreaGravesRows(data, container, pagination = null) {
    console.log(`📝 renderAreaGravesRows: ${data.length} items`);
    
    // ⭐ סינון client-side לפי plotId - זה הפתרון!
    let filteredData = data;
    if (currentPlotId) {
        filteredData = data.filter(ag => ag.plot_id === currentPlotId);
        console.log(`🎯 Client-side filter: ${data.length} → ${filteredData.length} area graves`);
    }
    
    const totalItems = filteredData.length;
    console.log(`📊 Total to display: ${totalItems}`);

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
                        <div>נסה לשנות את החיפוש</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    if (!tableWrapperExists && areaGravesTable) {
        console.log('🗑️ DOM deleted, resetting');
        areaGravesTable = null;
        window.areaGravesTable = null;
    }
    
    if (!areaGravesTable || !tableWrapperExists) {
        console.log(`🏗️ Creating TableManager: ${totalItems} items`);
        initAreaGravesTable(filteredData, totalItems);
    } else {
        console.log(`♻️ Updating TableManager: ${totalItems} items`);
        if (areaGravesTable.config) {
            areaGravesTable.config.totalItems = totalItems;
        }
        areaGravesTable.setData(filteredData);
    }
    
    if (areaGraveSearch) {
        areaGraveSearch.state.totalResults = totalItems;
        areaGraveSearch.updateCounter();
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('he-IL');
}

function getGraveTypeName(type) {
    const types = { 1: 'שדה', 2: 'רוויה', 3: 'סנהדרין' };
    return types[type] || 'לא מוגדר';
}

async function loadAreaGraveStats(plotId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/area-graves-api.php?action=stats';
        if (plotId) url += `&plotId=${plotId}`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Stats:', result.data);
            if (document.getElementById('totalAreaGraves')) {
                document.getElementById('totalAreaGraves').textContent = result.data.total_area_graves || 0;
            }
            if (document.getElementById('totalGraves')) {
                document.getElementById('totalGraves').textContent = result.data.total_graves || 0;
            }
        }
    } catch (error) {
        console.error('Stats error:', error);
    }
}

async function editAreaGrave(id) {
    console.log('✏️ Edit:', id);
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/area-graves-api.php?action=get&id=${id}`);
        const result = await response.json();
        if (!result.success) throw new Error(result.error);
        if (typeof openFormModal === 'function') {
            openFormModal('area_grave', result.data);
        }
    } catch (error) {
        console.error('Edit error:', error);
        showToast('שגיאה בעריכה', 'error');
    }
}

async function deleteAreaGrave(id) {
    if (!confirm('למחוק?')) return;
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/area-graves-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.error);
        showToast('נמחק בהצלחה', 'success');
        if (areaGraveSearch) areaGraveSearch.refresh();
    } catch (error) {
        console.error('Delete error:', error);
        showToast(error.message, 'error');
    }
}

function showToast(message, type = 'info') {
    const colors = { success: '#10b981', error: '#ef4444', info: '#3b82f6' };
    const icons = { success: '✓', error: '✗', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.style.cssText = `position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: ${colors[type]}; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10000; display: flex; align-items: center; gap: 10px;`;
    toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function refreshData() {
    if (areaGraveSearch) areaGraveSearch.refresh();
}

function checkScrollStatus() {
    if (!areaGravesTable) {
        console.log('❌ Not initialized');
        return;
    }
    const total = areaGravesTable.getFilteredData().length;
    const displayed = areaGravesTable.getDisplayedData().length;
    console.log(`📊 Status: ${displayed}/${total} (${Math.round((displayed/total)*100)}%)`);
}

async function handleAreaGraveDoubleClick(id, name) {
    console.log('🖱️ Double-click:', name);
    try {
        if (typeof createAreaGraveCard === 'function') {
            const card = await createAreaGraveCard(id);
            if (card && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(card);
            }
        }
        if (typeof loadGraves === 'function') {
            loadGraves(id, name);
        }
    } catch (error) {
        console.error('❌ Error:', error);
        showToast('שגיאה', 'error');
    }
}

window.handleAreaGraveDoubleClick = handleAreaGraveDoubleClick;
window.loadAllAreaGraves = loadAreaGraves;
window.loadAreaGraves = loadAreaGraves;
window.deleteAreaGrave = deleteAreaGrave;
window.editAreaGrave = editAreaGrave;
window.refreshData = refreshData;
window.areaGravesTable = areaGravesTable;
window.checkScrollStatus = checkScrollStatus;
window.currentPlotId = currentPlotId;
window.currentPlotName = currentPlotName;
window.areaGraveSearch = areaGraveSearch;

console.log('✅ Area Graves v1.2.2 - Fixed Client-Side Filter');
console.log('💡 checkScrollStatus()');