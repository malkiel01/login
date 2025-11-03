/*
 * File: dashboards/dashboard/cemeteries/js/purchases-management.js
 * Version: 3.0.0
 * Updated: 2025-11-03
 * Author: Malkiel
 * Change Summary:
 * - v3.0.0: זהה לחלוטין למבנה customers-management.js
 * - שיטה זהה - UniversalSearch + TableManager
 * - תיקון Virtual Scroll - itemsPerPage: 200
 * - תיקון קונפליקט שמות - initPurchasesSearch (במקום initUniversalSearch)
 * - הוספת Backward Compatibility
 * - שיפור הערות והפרדה ויזואלית
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
    console.log('📋 Loading purchases - v3.0.0 (תוקן Virtual Scroll וקונפליקט שמות)...');

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
    await initPurchasesSearch();
    
    // טען את הנתונים לראשונה
    if (purchaseSearch) {
        purchaseSearch.search();
    }
    
    // טען סטטיסטיקות
    await loadPurchaseStats();
}

/**
 * ⭐ פונקציה חדשה - בניית המבנה של רכישות ב-main-container
 */
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
        <!-- סטטיסטיקות רכישות -->
        <div class="stats-container" id="purchaseStatsContainer">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <div class="stat-value" id="totalPurchasesCount">0</div>
                    <div class="stat-label">סך הכל רכישות</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-value" id="activePurchasesCount">0</div>
                    <div class="stat-label">רכישות פעילות</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <div class="stat-value" id="thisMonthPurchasesCount">0</div>
                    <div class="stat-label">רכישות חודש זה</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <div class="stat-value" id="totalRevenueAmount">₪0</div>
                    <div class="stat-label">סכום כולל</div>
                </div>
            </div>
        </div>

        <!-- כרטיס הטבלה -->
        <div class="table-card">
            <div class="table-header">
                <h2 class="table-title">רשימת רכישות</h2>
                <div class="table-actions">
                    <div class="search-container">
                        <input 
                            type="text" 
                            id="purchasesSearchInput" 
                            class="search-input" 
                            placeholder="🔍 חפש רכישה..."
                            autocomplete="off"
                        />
                        <span class="search-counter" id="purchasesSearchCounter"></span>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-scroll-wrapper">
                    <table class="data-table" id="purchasesTable">
                        <thead id="purchasesTableHead">
                            <!-- TableManager ייצור את הכותרות -->
                        </thead>
                        <tbody id="purchasesTableBody">
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px;">
                                    <div class="loading-spinner">טוען רכישות...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    console.log('✅ Purchases container built successfully');
}

/**
 * ⭐ אתחול UniversalSearch לרכישות
 */
async function initPurchasesSearch() {
    console.log('🔍 Initializing UniversalSearch for purchases...');
    
    // וודא שיש DOM מוכן
    const searchInput = document.getElementById('purchasesSearchInput');
    const searchCounter = document.getElementById('purchasesSearchCounter');
    const tableBody = document.getElementById('purchasesTableBody');
    
    if (!searchInput || !searchCounter || !tableBody) {
        console.error('❌ Required DOM elements not found');
        return;
    }
    
    // ⭐ צור UniversalSearch חדש
    purchaseSearch = new UniversalSearch({
        apiUrl: '/dashboard/dashboards/cemeteries/api/purchases-api.php',
        searchInputId: 'purchasesSearchInput',
        counterElementId: 'purchasesSearchCounter',
        resultContainerId: 'purchasesTableBody',
        searchableFields: ['serialPurchaseId', 'customerName', 'graveName', 'purchaseAmount'],
        debounceDelay: 300,
        itemsPerPage: 200,
        
        onDataReceived: (data, searchInstance) => {
            console.log('📊 Purchases data received:', data.length, 'items');
            currentPurchases = data;
            
            // ⭐ אם אין TableManager או שהוא לא מאותחל - צור חדש
            if (!purchasesTable || !document.querySelector('.table-wrapper[data-fixed-width="true"]')) {
                console.log('📦 Creating new TableManager for purchases');
                createPurchasesTable(data);
            } else {
                console.log('📝 Updating existing TableManager');
                purchasesTable.setData(data);
            }
        },
        
        onError: (error) => {
            console.error('❌ Search error:', error);
            showError('שגיאה בחיפוש רכישות: ' + error.message);
        }
    });
    
    // שמור ב-window
    window.purchaseSearch = purchaseSearch;
    
    console.log('✅ UniversalSearch initialized for purchases');
}

/**
 * ⭐ יצירת TableManager לרכישות
 */
function createPurchasesTable(data) {
    console.log('📦 Creating TableManager with', data.length, 'purchases');
    
    const tableBody = document.getElementById('purchasesTableBody');
    const tableHead = document.getElementById('purchasesTableHead');
    
    if (!tableBody || !tableHead) {
        console.error('❌ Table elements not found');
        return;
    }
    
    // ⭐ צור TableManager חדש
    purchasesTable = new TableManager({
        containerId: 'purchasesTable',
        columns: [
            { 
                field: 'index', 
                label: '#', 
                width: '60px',
                render: (value, row, index) => index + 1
            },
            { 
                field: 'serialPurchaseId', 
                label: 'מספר רכישה', 
                width: '120px',
                render: (value) => `<strong>${value || 'לא זמין'}</strong>`
            },
            { 
                field: 'customerName', 
                label: 'שם לקוח', 
                width: '180px',
                render: (value) => value || 'לא זמין'
            },
            { 
                field: 'graveName', 
                label: 'קבר', 
                width: '150px',
                render: (value) => value || 'לא משויך'
            },
            { 
                field: 'purchaseAmount', 
                label: 'סכום', 
                width: '120px',
                render: (value) => {
                    const amount = parseFloat(value) || 0;
                    return `₪${amount.toLocaleString('he-IL')}`;
                }
            },
            { 
                field: 'purchaseDate', 
                label: 'תאריך רכישה', 
                width: '130px',
                render: (value) => formatDate(value)
            },
            { 
                field: 'statusPurchase', 
                label: 'סטטוס', 
                width: '100px',
                render: (value) => {
                    const statusClass = value === 'active' ? 'status-active' : 'status-inactive';
                    const statusText = value === 'active' ? 'פעיל' : 'לא פעיל';
                    return `<span class="status-badge ${statusClass}">${statusText}</span>`;
                }
            },
            { 
                field: 'createDate', 
                label: 'נוצר בתאריך', 
                width: '130px',
                render: (value) => formatDate(value)
            },
            { 
                field: 'actions', 
                label: 'פעולות', 
                width: '150px',
                render: (value, row) => `
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="viewPurchaseDetails('${row.unicId}')" title="צפייה">
                            <svg class="icon"><use xlink:href="#icon-view"></use></svg>
                        </button>
                        <button class="btn-icon" onclick="editPurchase('${row.unicId}')" title="עריכה">
                            <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                        </button>
                        <button class="btn-icon btn-danger" onclick="deletePurchase('${row.unicId}')" title="מחיקה">
                            <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                        </button>
                    </div>
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
            const count = purchasesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    // ⭐ עדכן את window.purchasesTable מיד!
    window.purchasesTable = purchasesTable;
    
    console.log('📊 Total purchases loaded:', data.length);
    console.log('📄 Items per page:', purchasesTable.config.itemsPerPage);
    console.log('📏 Scroll threshold:', purchasesTable.config.scrollThreshold + 'px');
    
    return purchasesTable;
}

/**
 * ⭐ טעינת סטטיסטיקות רכישות
 */
async function loadPurchaseStats() {
    console.log('📊 Loading purchase statistics...');
    
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats');
        const data = await response.json();
        
        if (data.success && data.data) {
            const stats = data.data;
            
            // עדכן את הכרטיסיות
            document.getElementById('totalPurchasesCount').textContent = stats.total || 0;
            document.getElementById('activePurchasesCount').textContent = stats.active || 0;
            document.getElementById('thisMonthPurchasesCount').textContent = stats.thisMonth || 0;
            
            // עדכן סכום כולל
            const totalRevenue = parseFloat(stats.totalRevenue) || 0;
            document.getElementById('totalRevenueAmount').textContent = 
                `₪${totalRevenue.toLocaleString('he-IL')}`;
            
            console.log('✅ Statistics loaded successfully');
        }
    } catch (error) {
        console.error('❌ Error loading stats:', error);
        // לא נציג שגיאה למשתמש - רק לוג
    }
}

/**
 * ========================================
 * פעולות על רכישות
 * ========================================
 */

function viewPurchaseDetails(purchaseId) {
    console.log('👁️ View purchase:', purchaseId);
    // TODO: פתח מודל או עמוד פרטים
    alert(`צפייה ברכישה מס' ${purchaseId}`);
}

function editPurchase(purchaseId) {
    console.log('✏️ Edit purchase:', purchaseId);
    
    if (typeof openForm === 'function') {
        openForm('purchase', 'edit', purchaseId);
    } else {
        alert(`עריכת רכישה מס' ${purchaseId}`);
    }
}

function deletePurchase(purchaseId) {
    console.log('🗑️ Delete purchase:', purchaseId);
    
    if (!confirm('האם אתה בטוח שברצונך למחוק רכישה זו?')) {
        return;
    }
    
    // TODO: שלח בקשה למחיקה
    alert(`מחיקת רכישה מס' ${purchaseId}`);
}

/**
 * ========================================
 * פונקציות עזר
 * ========================================
 */

function showError(message) {
    // TODO: הצג הודעת שגיאה יפה
    alert(message);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('he-IL');
}

function showToast(message, type = 'info') {
    // TODO: הצג toast notification
    console.log(`[${type.toUpperCase()}] ${message}`);
}

/**
 * ========================================
 * בדיקת סטטוס גלילה (לדיבוג)
 * ========================================
 */
window.checkScrollStatusPurchases = function() {
    if (!window.purchasesTable) {
        console.log('❌ TableManager not initialized');
        return;
    }
    
    const tm = window.purchasesTable;
    console.log('📊 Scroll Status:');
    console.log('  • Total items:', tm.totalItems);
    console.log('  • Rendered items:', tm.currentData.length);
    console.log('  • Items per page:', tm.itemsPerPage);
    console.log('  • Has more data:', tm.hasMoreData);
    console.log('  • Is loading:', tm.isLoading);
};

// ייצוא גלובלי
window.loadPurchases = loadPurchases;
window.viewPurchaseDetails = viewPurchaseDetails;
window.editPurchase = editPurchase;
window.deletePurchase = deletePurchase;

console.log('✅ Purchases Management Module Loaded - v3.0.0: Identical to Customers');
console.log('💡 Commands: checkScrollStatusPurchases() - בדוק כמה רשומות נטענו');