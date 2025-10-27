/*
 * File: dashboards/dashboard/cemeteries/assets/js/cemeteries-management.js
 * Version: 5.1.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - v5.0.0: שיטה זהה ללקוחות - UniversalSearch + TableManager
 * - v5.1.0: תיקון קונפליקט שמות - initCemeteriesSearch (במקום initUniversalSearch)
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCemeteries = [];
let cemeterySearch = null;
let cemeteriesTable = null;
let editingCemeteryId = null;

// ===================================================================
// טעינת בתי עלמין (הפונקציה הראשית)
// ===================================================================
async function loadCemeteries() {
    console.log('📋 Loading cemeteries - v5.1.0 (תוקן קונפליקט שמות)...');

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('cemeteriesItem');
    }
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'cemetery';
    window.currentParentId = null;
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'cemetery' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'cemetery' });
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
        updateBreadcrumb({ cemetery: { name: 'בתי עלמין' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול בתי עלמין - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildCemeteriesContainer();
    
    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (cemeterySearch && typeof cemeterySearch.destroy === 'function') {
        console.log('🗑️ Destroying previous cemeterySearch instance...');
        cemeterySearch.destroy();
        cemeterySearch = null;
        window.cemeterySearch = null;
    }

    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh cemeterySearch instance...');
    await initCemeteriesSearch();
    cemeterySearch.search();
    
    // טען סטטיסטיקות
    await loadCemeteryStats();
}

// ===================================================================
// ⭐ פונקציה חדשה - בניית המבנה של בתי עלמין ב-main-container
// ===================================================================
async function buildCemeteriesContainer() {
    console.log('🏗️ Building cemeteries container...');
    
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
    
    // ⭐ בנה את התוכן של בתי עלמין - זהה ללקוחות!
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="cemeterySearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען בתי עלמין...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Cemeteries container built');
}

// ===================================================================
// אתחול UniversalSearch - שימוש בפונקציה גלובלית!
// ===================================================================
async function initCemeteriesSearch() {
    cemeterySearch = window.initUniversalSearch({
        entityType: 'cemetery',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/cemeteries-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'cemeteryNameHe',
                label: 'שם בית עלמין (עברית)',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'cemeteryNameEn',
                label: 'שם בית עלמין (אנגלית)',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'cemeteryCode',
                label: 'קוד בית עלמין',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'address',
                label: 'כתובת',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'contactName',
                label: 'איש קשר',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'contactPhoneName',
                label: 'טלפון',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'cemeteries',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['cemeteryNameHe', 'cemeteryCode', 'address', 'contactName', 'contactPhoneName', 'blocks_count', 'createDate'],
        
        searchContainerSelector: '#cemeterySearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
        itemsPerPage: 999999,
        
        renderFunction: renderCemeteriesRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for cemeteries');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'cemeteries found');
                currentCemeteries = data.data;
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
    
    // ⭐ עדכן את window.cemeterySearch מיד!
    window.cemeterySearch = cemeterySearch;
    
    return cemeterySearch;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
async function initCemeteriesTable(data, totalItems = null) {
    // ⭐ אם לא קיבלנו totalItems, השתמש ב-data.length
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (cemeteriesTable) {
        cemeteriesTable.config.totalItems = actualTotalItems;  // ⭐ עדכן totalItems!
        cemeteriesTable.setData(data);
        return cemeteriesTable;
    }

    cemeteriesTable = new TableManager({
        tableSelector: '#mainTable',  // ⭐ זה הכי חשוב!
        
        // ⭐ הוספת totalItems כפרמטר!
        totalItems: actualTotalItems,

        columns: [
            {
                field: 'cemeteryNameHe',
                label: 'שם בית עלמין',
                width: '200px',
                sortable: true,
                render: (cemetery) => {
                    return `<a href="#" onclick="loadBlocks('${cemetery.unicId}', '${cemetery.cemeteryNameHe.replace(/'/g, "\\'")}'); return false;" 
                               style="color: #2563eb; text-decoration: none; font-weight: 500;">
                        ${cemetery.cemeteryNameHe}
                    </a>`;
                }
            },
            {
                field: 'cemeteryCode',
                label: 'קוד',
                width: '100px',
                sortable: true
            },
            {
                field: 'address',
                label: 'כתובת',
                width: '250px',
                sortable: true
            },
            {
                field: 'contactName',
                label: 'איש קשר',
                width: '150px',
                sortable: true
            },
            {
                field: 'contactPhoneName',
                label: 'טלפון',
                width: '120px',
                sortable: true
            },
            {
                field: 'blocks_count',
                label: 'גושים',
                width: '80px',
                type: 'number',
                sortable: true,
                render: (cemetery) => {
                    const count = cemetery.blocks_count || 0;
                    return `<span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">${count}</span>`;
                }
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (cemetery) => formatDate(cemetery.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (cemetery) => `
                    <button class="btn btn-sm btn-secondary" onclick="editCemetery('${cemetery.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCemetery('${cemetery.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                `
            }
        ],

        onRowDoubleClick: (cemetery) => {                    // ⭐ שורה חדשה
            handleCemeteryDoubleClick(cemetery.unicId, cemetery.cemeteryNameHe);
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
            const count = cemeteriesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    // ⭐ עדכן את window.cemeteriesTable מיד!
    window.cemeteriesTable = cemeteriesTable;
    
    return cemeteriesTable;
}

// ===================================================================
// רינדור שורות בתי עלמין - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderCemeteriesRows(data, container, pagination = null) {
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
    const totalItems = pagination?.total || data.length;

    if (data.length === 0) {
        if (cemeteriesTable) {
            cemeteriesTable.setData([]);
        }
        
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
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && cemeteriesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting cemeteriesTable variable');
        cemeteriesTable = null;
        window.cemeteriesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!cemeteriesTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        initCemeteriesTable(data, totalItems);  // ⭐ העברת totalItems!
    } else {
          // ⭐ עדכן גם את totalItems ב-TableManager!
        if (cemeteriesTable.config) {
            cemeteriesTable.config.totalItems = totalItems;
        }
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (cemeterySearch && cemeterySearch.state) {
            const allData = cemeterySearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                cemeteriesTable.setData(allData);
                return;
            }
        }

        cemeteriesTable.setData(data);
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
// פונקציות עזר - טעינת גושים
// ===================================================================
function loadBlocks(cemeteryId, cemeteryName) {
    console.log(`📦 Loading blocks for cemetery: ${cemeteryName} (ID: ${cemeteryId})`);
    
    // עדכון breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({
            cemetery: { id: cemeteryId, name: cemeteryName }
        });
    }

    // טעינת גושים (מימוש קיים במערכת)
    if (typeof loadBlocksData === 'function') {
        loadBlocksData(cemeteryId, cemeteryName);
    } else {
        console.warn('⚠️ loadBlocksData function not found');
    }
}

// ===================================================================
// פונקציות CRUD
// ===================================================================
async function editCemetery(cemeteryId) {
    console.log('✏️ Edit cemetery:', cemeteryId);
    editingCemeteryId = cemeteryId;
    
    // פתיחת טופס עריכה
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, cemeteryId);
    } else {
        showToast('עריכה בפיתוח...', 'info');
    }
}

async function deleteCemetery(cemeteryId) {
    console.log('🗑️ Delete cemetery:', cemeteryId);
    
    if (!confirm('האם אתה בטוח שברצונך למחוק את בית העלמין?')) {
        return;
    }

    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=delete&id=${cemeteryId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('בית העלמין נמחק בהצלחה', 'success');
            
            if (cemeterySearch) {
                cemeterySearch.refresh();
            }
        } else {
            showToast(data.error || 'שגיאה במחיקת בית עלמין', 'error');
        }
    } catch (error) {
        console.error('Error deleting cemetery:', error);
        showToast('שגיאה במחיקת בית עלמין', 'error');
    }
}

// ===================================================================
// בחירת הכל
// ===================================================================
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.cemetery-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadCemeteryStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('Cemetery stats:', data.data);
        }
    } catch (error) {
        console.error('Error loading cemetery stats:', error);
    }
}

// ===================================================================
// הצגת הודעת Toast
// ===================================================================
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

// ===================================================================
// פונקציה לרענון נתונים
// ===================================================================
async function refreshData() {
    if (cemeterySearch) {
        cemeterySearch.refresh();
    }
}

// ===================================================================
// פונקציה לבדיקת סטטוס הטעינה
// ===================================================================
function checkScrollStatus() {
    if (!cemeteriesTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = cemeteriesTable.getFilteredData().length;
    const displayed = cemeteriesTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(cemeteriesTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// יצירת כרטיס מידע ללקוח
// ===================================================
async function createCustomerCard(customerId) {
    try {
        const response = await fetch(`${API_BASE}customers-api.php?action=get&id=${customerId}`);
        const data = await response.json();
        
        if (!data.success) {
            console.warn('Failed to fetch customer data');
            return '';
        }
        
        const customer = data.data;
        
        // פורמט סוג תושבות
        const typeLabels = {
            1: 'תושב העיר',
            2: 'תושב הארץ',
            3: 'תושב חו"ל'
        };
        const residentType = typeLabels[customer.statusResident] || 'לא מוגדר';
        
        // פורמט סטטוס
        const statusBadge = customer.statusCustomer == 1 
            ? '<span class="status-badge-large status-active">פעיל</span>'
            : '<span class="status-badge-large status-inactive">לא פעיל</span>';
        
        // ספירת רכישות
        const purchasesCount = customer.purchases ? customer.purchases.length : 0;
        
        return `
            <div class="info-card" id="customerCard">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">👤</span>
                        <div>
                            <div class="info-card-type">לקוח</div>
                            <h2 class="info-card-name">${customer.firstName} ${customer.lastName}</h2>
                            <div class="info-card-code">ת.ז: ${customer.numId}</div>
                        </div>
                    </div>
                    <div class="info-card-actions">
                        <button class="btn-secondary" onclick="editCustomer('${customer.unicId}')">
                            <span>✏️</span> עריכה
                        </button>
                        <button class="btn-primary" onclick="printCustomerReport('${customer.unicId}')">
                            <span>🖨️</span> הדפסה
                        </button>
                    </div>
                </div>
                
                <div class="info-card-content">
                    <div class="info-row">
                        <div class="info-group">
                            <div class="info-label">טלפון</div>
                            <div class="info-value">${customer.phone || '-'}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">נייד</div>
                            <div class="info-value">${customer.mobile || '-'}</div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-group full-width">
                            <div class="info-label">אימייל</div>
                            <div class="info-value">${customer.email || '-'}</div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-group full-width">
                            <div class="info-label">כתובת</div>
                            <div class="info-value">${customer.address || '-'}</div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-group">
                            <div class="info-label">סוג תושבות</div>
                            <div class="info-value">${residentType}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">סטטוס</div>
                            <div class="info-value">${statusBadge}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            ${purchasesCount > 0 ? `
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-value">${purchasesCount}</div>
                    <div class="stat-label">רכישות</div>
                </div>
            </div>
            ` : ''}
        `;
    } catch (error) {
        console.error('Error creating customer card:', error);
        return '';
    }
}

function printCustomerReport(customerId) {
    console.log('📄 Printing customer report:', customerId);
    // TODO: implement print
}

// ===================================================
// פונקציה לטיפול בדאבל-קליק על בית עלמין
// ===================================================
async function handleCemeteryDoubleClick(cemeteryId, cemeteryName) {
    console.log('🖱️ Double-click on cemetery:', cemeteryName, cemeteryId);
    
    try {
        // יצירת והצגת כרטיס
        if (typeof createCemeteryCard === 'function') {
            const cardHtml = await createCemeteryCard(cemeteryId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        }
        
        // טעינת גושים
        console.log('📦 Loading blocks for cemetery:', cemeteryName);
        loadBlocks(cemeteryId, cemeteryName);
        
    } catch (error) {
        console.error('❌ Error in handleCemeteryDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי בית העלמין', 'error');
    }
}

window.handleCemeteryDoubleClick = handleCemeteryDoubleClick;

// ===================================================================
// Backward Compatibility - Aliases
// ===================================================================
window.loadAllCemeteries = loadCemeteries; // ✅ Alias לשם הישן

// ===================================================================
// הפוך את הפונקציות לגלובליות
// ===================================================================
window.loadCemeteries = loadCemeteries;
window.deleteCemetery = deleteCemetery;
window.editCemetery = editCemetery;
window.refreshData = refreshData;
window.cemeteriesTable = cemeteriesTable;
window.checkScrollStatus = checkScrollStatus;