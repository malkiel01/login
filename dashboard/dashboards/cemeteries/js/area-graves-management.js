/*
 * File: dashboards/dashboard/cemeteries/assets/js/area-graves-management.js
 * Version: 1.0.1
 * Updated: 2025-10-28
 * Author: Malkiel
 * Change Summary:
 * - v1.0.1: תיקון תאימות למבנה הטבלאות האמיתי
 *   - שינוי שמות שדות: graveType, lineId, comments
 *   - הסרת שדות לא קיימים: areaGraveCode, areaGraveNameEn
 *   - התאמת renderFunction לשדות הנכונים
 * - v1.0.0: יצירה ראשונית
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
    console.log('📋 Loading area graves - v1.0.1 (תוקן תאימות טבלאות)...');
    
    // ⭐ לוגיקת סינון: אם קוראים ללא פרמטרים - אפס את הסינון
    if (plotId === null && plotName === null && !forceReset) {
        // בדוק אם יש סינון קיים מהעבר
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
        // יש plotId - עדכן את הסינון
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
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'area_grave' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'area_grave' });
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
            area_grave: { name: plotName ? `אחוזות קבר של ${plotName}` : 'אחוזות קבר' }
        };
        if (plotId && plotName) {
            breadcrumbData.plot = { id: plotId, name: plotName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = plotName ? `אחוזות קבר - ${plotName}` : 'ניהול אחוזות קבר - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildAreaGravesContainer(plotId, plotName);
    
    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (areaGraveSearch && typeof areaGraveSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous areaGraveSearch instance...');
        areaGraveSearch.destroy();
        areaGraveSearch = null;
        window.areaGraveSearch = null;
    }
    
    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh areaGraveSearch instance...');
    await initAreaGravesSearch(plotId);
    areaGraveSearch.search();
    
    // טען סטטיסטיקות
    await loadAreaGraveStats(plotId);
}

// ===================================================================
// ⭐ פונקציה - בניית המבנה של אחוזות קבר ב-main-container
// ===================================================================
async function buildAreaGravesContainer(plotId = null, plotName = null) {
    console.log('🏗️ Building area graves container...');
    
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
    
    // ⭐ הוסף אינדיקטור סינון אם יש חלקה נבחרת
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
    
    // ⭐ בנה את התוכן של אחוזות קבר
    mainContainer.innerHTML = `
        ${filterIndicator}
        
        <!-- סקשן חיפוש -->
        <div id="areaGraveSearchSection" class="search-section"></div>
        
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
// אתחול UniversalSearch - עם שמות שדות מתוקנים!
// ===================================================================
async function initAreaGravesSearch(plotId = null) {
    const config = {
        entityType: 'area_grave',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/area-graves-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'areaGraveNameHe',
                label: 'שם אחוזת קבר (עברית)',
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
                options: {
                    '': 'הכל',
                    '1': 'שדה',
                    '2': 'רוויה',
                    '3': 'סנהדרין'
                },
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
        itemsPerPage: 999999,
        
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
                currentAreaGraves = data.data;
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
            }
        }
    };
    
    // ⭐ הוסף פרמטר plotId אם קיים - לסינון server-side
    if (plotId) {
        config.additionalParams = {
            plotId: plotId,
            filter_by_plot: 'true'
        };
        console.log('🔍 Adding server-side filter for plotId:', plotId);
    }
    
    areaGraveSearch = window.initUniversalSearch(config);
    window.areaGraveSearch = areaGraveSearch;
    
    console.log('✅ Area graves search initialized', { plotId });
}

// ===================================================================
// רינדור שורות טבלה - עם שמות שדות מתוקנים!
// ===================================================================
function renderAreaGravesRows(areaGraves) {
    console.log('🎨 Rendering area graves rows...', areaGraves.length);
    
    if (!areaGraves || areaGraves.length === 0) {
        return `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🏚️</div>
                    <div style="font-size: 18px; font-weight: 500; margin-bottom: 8px;">לא נמצאו אחוזות קבר</div>
                    <div style="font-size: 14px; opacity: 0.7;">נסה לשנות את פרמטרי החיפוש</div>
                </td>
            </tr>
        `;
    }
    
    // ⭐ סינון client-side נוסף אם יש plotId
    let filteredAreaGraves = areaGraves;
    if (currentPlotId && window.currentPlotId) {
        console.log('🔍 Applying client-side filter for plotId:', currentPlotId);
        // נצטרך לקבל את נתוני השורות כדי לסנן
        // אבל אם ה-API כבר מחזיר מסונן, זה בסדר
        filteredAreaGraves = areaGraves; // כרגע נניח שהסינון server-side עובד
    }
    
    return filteredAreaGraves.map(areaGrave => {
        const rowId = areaGrave.id;
        const unicId = areaGrave.unicId;
        const nameHe = areaGrave.areaGraveNameHe || 'ללא שם';
        const coordinates = areaGrave.coordinates || '-';
        const graveType = getGraveTypeName(areaGrave.graveType);
        const rowName = areaGrave.row_name || areaGrave.lineNameHe || '-';
        const gravesCount = areaGrave.graves_count || 0;
        const createDate = formatDate(areaGrave.createDate);
        
        return `
            <tr class="table-row" 
                data-id="${rowId}" 
                data-unic-id="${unicId}"
                ondblclick="handleAreaGraveDoubleClick('${unicId}', '${nameHe.replace(/'/g, "\\'")}')">
                
                <td style="font-weight: 600;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">🏘️</span>
                        <span>${nameHe}</span>
                    </div>
                </td>
                
                <td style="text-align: center;">
                    <span style="font-family: monospace; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                        ${coordinates}
                    </span>
                </td>
                
                <td style="text-align: center;">
                    <span style="padding: 4px 12px; background: #e0e7ff; color: #4338ca; border-radius: 12px; font-size: 12px; font-weight: 500;">
                        ${graveType}
                    </span>
                </td>
                
                <td style="text-align: center;">
                    <span style="color: #6b7280;">
                        📏 ${rowName}
                    </span>
                </td>
                
                <td style="text-align: center; font-weight: 600;">
                    <span style="color: #059669;">
                        ${gravesCount} 🪦
                    </span>
                </td>
                
                <td style="text-align: center; color: #6b7280; font-size: 13px;">
                    ${createDate}
                </td>
                
                <td style="text-align: center;">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <button class="btn-icon" onclick="event.stopPropagation(); editAreaGrave('${unicId}')" title="עריכה">
                            ✏️
                        </button>
                        <button class="btn-icon" onclick="event.stopPropagation(); deleteAreaGrave('${unicId}')" title="מחיקה">
                            🗑️
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
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
// פונקציית עזר לשם סוג קבר - מתוקן לפי הערות בטבלה!
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
// טעינת סטטיסטיקות אחוזות קבר
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
            
            // עדכון מונים בממשק אם קיימים
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
        
        // פתח את הטופס במודל
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
        
        // רענן את הנתונים
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
// פונקציה לרענון נתונים
// ===================================================================
async function refreshData() {
    if (areaGraveSearch) {
        areaGraveSearch.refresh();
    }
}

// ===================================================================
// פונקציה לבדיקת סטטוס הטעינה
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
        console.log(`   🔽 Scroll down to load ${Math.min(areaGravesTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// ⭐ פונקציה לטיפול בדאבל-קליק על אחוזת קבר
// ===================================================
async function handleAreaGraveDoubleClick(areaGraveId, areaGraveName) {
    console.log('🖱️ Double-click on area grave:', areaGraveName, areaGraveId);
    
    try {
        // 1. יצירת והצגת כרטיס (אם קיים)
        if (typeof createAreaGraveCard === 'function') {
            const cardHtml = await createAreaGraveCard(areaGraveId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        }
        
        // 2. טעינת קברים (ילדים ישירים)
        console.log('🪦 Loading graves for area grave:', areaGraveName);
        if (typeof loadGraves === 'function') {
            loadGraves(areaGraveId, areaGraveName);
        } else {
            console.warn('loadGraves function not found - צריך ליצור את graves-management.js');
        }
        
    } catch (error) {
        console.error('❌ Error in handleAreaGraveDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי אחוזת הקבר', 'error');
    }
}

window.handleAreaGraveDoubleClick = handleAreaGraveDoubleClick;

// ===================================================================
// Backward Compatibility - Aliases
// ===================================================================
window.loadAllAreaGraves = loadAreaGraves; // ✅ Alias לשם הישן

// ===================================================================
// הפוך את הפונקציות לגלובליות
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