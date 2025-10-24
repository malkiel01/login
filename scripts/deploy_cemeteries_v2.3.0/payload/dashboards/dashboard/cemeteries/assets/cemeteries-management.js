/*
 * File: dashboards/dashboard/cemeteries/assets/cemeteries-management.js
 * Version: 2.3.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - תיקון בעיית "Table not found: null" בטעינת בתי עלמין
 * - הוספת בדיקה ובנייה מחדש של טבלה #mainTable לפני אתחול TableManager
 * - שיפור בטיפול ב-DOM כדי למנוע מצבים בהם הטבלה לא קיימת
 */

/**
 * cemeteries-management.js - STEP B.1 - תיקון בעיית טעינה
 * ניהול בתי עלמין עם TableManager + UniversalSearch
 * מותאם למבנה החדש עם main-container - זהה ל-customers-management.js
 */

let currentCemeteries = [];
let cemeterySearch = null;
let cemeteriesTable = null;
let editingCemeteryId = null;

// טעינת בתי עלמין (הפונקציה הראשית)
async function loadCemeteries() {
    console.log('📋 Loading cemeteries - v2.3.0 (Fixed TableManager init)...');

    setActiveMenuItem('cemeteryItem');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'cemetery';
    window.currentParentId = null;
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    DashboardCleaner.clear({ targetLevel: 'cemetery' });
    
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
    
    // אתחל את UniversalSearch
    if (!cemeterySearch) {
        await initUniversalSearch();
        cemeterySearch.search();
    } else {
        cemeterySearch.refresh();
    }
    
    // טען סטטיסטיקות
    await loadCemeteryStats();
}

/**
 * ⭐ פונקציה חדשה - בניית המבנה של בתי עלמין ב-main-container
 */
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
    
    // ⭐ בנה את התוכן של בתי עלמין
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

// אתחול UniversalSearch
async function initUniversalSearch() {
    cemeterySearch = new UniversalSearch({
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/cemeteries-api.php',
            action: 'list',
            method: 'GET',
            tables: ['cemeteries'],
            joins: []
        },
        
        // שדות לחיפוש
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
                name: 'cityId',
                label: 'עיר',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'cemeteries',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        display: {
            containerSelector: '#cemeterySearchSection',
            showAdvanced: true,
            showFilters: true,
            placeholder: 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
            layout: 'horizontal',
            minSearchLength: 0
        },
        
        results: {
            containerSelector: '#tableBody',
            itemsPerPage: 10000,
            showPagination: false,
            showCounter: true,
            columns: ['cemeteryNameHe', 'cemeteryCode', 'address', 'city_name', 'contactName', 'contactPhoneName', 'createDate'],
            renderFunction: renderCemeteriesRows
        },
        
        behavior: {
            realTime: true,
            autoSubmit: true,
            highlightResults: true
        },
        
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

/**
 * ⭐ פונקציה חדשה - וידוא שהטבלה קיימת לפני אתחול TableManager
 */
function ensureMainTableExists() {
    let mainTable = document.querySelector('#mainTable');
    
    if (!mainTable) {
        console.log('⚠️ #mainTable not found, rebuilding...');
        
        // מצא את ה-container
        let tableContainer = document.querySelector('.table-container');
        
        if (!tableContainer) {
            console.error('❌ .table-container not found! Cannot rebuild table.');
            return false;
        }
        
        // בנה את הטבלה מחדש
        tableContainer.innerHTML = `
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
        `;
        
        console.log('✅ #mainTable rebuilt successfully');
        return true;
    }
    
    console.log('✅ #mainTable exists');
    return true;
}

// אתחול TableManager
function initCemeteriesTable(data) {
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (cemeteriesTable) {
        cemeteriesTable.setData(data);
        return cemeteriesTable;
    }
    
    // ⭐ וודא שהטבלה קיימת לפני האתחול!
    if (!ensureMainTableExists()) {
        console.error('❌ Cannot initialize TableManager - mainTable does not exist');
        return null;
    }
    
    cemeteriesTable = new TableManager({
        tableSelector: '#mainTable',
        
        containerWidth: '100%',
        fixedLayout: true,
        
        itemsPerPage: 50,
        scrollThreshold: 200,
        renderDelay: 0,
        batchSize: 50,
        
        pagination: {
            enabled: false
        },
        
        columns: [
            {
                field: 'cemeteryCode',
                label: 'קוד',
                width: '100px',
                type: 'text',
                sortable: true
            },
            {
                field: 'cemeteryNameHe',
                label: 'שם בית עלמין',
                width: '200px',
                type: 'text',
                sortable: true,
                render: (cemetery) => cemetery.cemeteryNameHe || cemetery.name || '-'
            },
            {
                field: 'cemeteryNameEn',
                label: 'שם באנגלית',
                width: '180px',
                type: 'text',
                sortable: true
            },
            {
                field: 'address',
                label: 'כתובת',
                width: '200px',
                type: 'text',
                sortable: true
            },
            {
                field: 'city_name',
                label: 'עיר',
                width: '120px',
                type: 'text',
                sortable: true
            },
            {
                field: 'contactName',
                label: 'איש קשר',
                width: '150px',
                type: 'text',
                sortable: true
            },
            {
                field: 'contactPhoneName',
                label: 'טלפון',
                width: '120px',
                type: 'text',
                sortable: true
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
                width: '150px',
                sortable: false,
                render: (cemetery) => `
                    <button class="btn btn-sm btn-primary" onclick="openCemetery('${cemetery.unicId || cemetery.id}', '${(cemetery.cemeteryNameHe || cemetery.name || '').replace(/'/g, "\\'")}')" title="כניסה">
                        <svg class="icon"><use xlink:href="#icon-enter"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="editCemetery('${cemetery.unicId || cemetery.id}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCemetery('${cemetery.unicId || cemetery.id}')" title="מחיקה">
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
            const count = cemeteriesTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    // ⭐ עדכן את window.cemeteriesTable מיד!
    window.cemeteriesTable = cemeteriesTable;
    
    console.log('📊 Total cemeteries loaded:', data.length);
    console.log('📄 Items per page:', cemeteriesTable.config.itemsPerPage);
    console.log('📏 Scroll threshold:', cemeteriesTable.config.scrollThreshold + 'px');
    
    return cemeteriesTable;
}

// רינדור שורות בתי עלמין
function renderCemeteriesRows(data, container) {
    console.log('🎨 renderCemeteriesRows called with', data.length, 'items');
    
    if (data.length === 0) {
        if (cemeteriesTable) {
            cemeteriesTable.setData([]);
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
    if (!tableWrapperExists && cemeteriesTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting cemeteriesTable variable');
        cemeteriesTable = null;
        window.cemeteriesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!cemeteriesTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        console.log('✅ Creating new TableManager with', data.length, 'total items');
        initCemeteriesTable(data);
    } else {
        // TableManager קיים וגם ה-DOM שלו - רק עדכן נתונים
        console.log('🔄 Updating existing TableManager with', data.length, 'total items');
        cemeteriesTable.setData(data);
    }
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// מחיקת בית עלמין
async function deleteCemetery(cemeteryId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק בית עלמין זה?')) {
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

// עריכת בית עלמין
async function editCemetery(cemeteryId) {
    console.log('Edit cemetery:', cemeteryId);
    editingCemeteryId = cemeteryId;
    showToast('עריכה בפיתוח...', 'info');
}

// פתיחת בית עלמין (מעבר לגושים)
function openCemetery(cemeteryId, cemeteryName) {
    console.log('🏛️ Opening cemetery:', cemeteryId, cemeteryName);
    
    // עדכון משתנים גלובליים
    window.selectedItems = window.selectedItems || {};
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    window.currentCemeteryId = cemeteryId;
    
    // עדכן Breadcrumb
    if (typeof BreadcrumbManager !== 'undefined') {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    // טען גושים
    if (window.tableRenderer && typeof window.tableRenderer.loadAndDisplay === 'function') {
        window.tableRenderer.loadAndDisplay('block', cemeteryId);
    } else {
        console.error('tableRenderer not available');
    }
}

// טעינת סטטיסטיקות
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
    if (cemeterySearch) {
        cemeterySearch.refresh();
    }
}

// פונקציה לבדיקת סטטוס הטעינה
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

// הפוך את הפונקציות לגלובליות
window.loadCemeteries = loadCemeteries;
window.loadAllCemeteries = loadCemeteries; // alias לתאימות אחורה
window.deleteCemetery = deleteCemetery;
window.editCemetery = editCemetery;
window.openCemetery = openCemetery;
window.refreshData = refreshData;
window.cemeteriesTable = cemeteriesTable;
window.checkScrollStatus = checkScrollStatus;
window.ensureMainTableExists = ensureMainTableExists; // ⭐ הפוך לפונקציה גלובלית לניפוי באגים

console.log('✅ Cemeteries Management Module Loaded - v2.3.0: Fixed TableManager Init Issue');
console.log('💡 Commands:');
console.log('   checkScrollStatus() - בדוק כמה רשומות נטענו');
console.log('   ensureMainTableExists() - בדוק אם הטבלה קיימת');
