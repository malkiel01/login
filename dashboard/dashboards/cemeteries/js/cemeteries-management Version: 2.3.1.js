/*
 * File: dashboards/dashboard/cemeteries/assets/js/cemeteries-management.js
 * Version: 2.3.1
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - תוקן באג קריטי: שונה apiUrl ל-endpoint בקונפיגורציה של UniversalSearch
 * - זה התיקון היחיד הדרוש - שם הפרמטר היה שגוי
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let cemeteriesSearch = null;
let cemeteriesTable = null;
const CEMETERIES_ITEMS_PER_PAGE = 100;
const CEMETERIES_SCROLL_THRESHOLD = 200;

// ===================================================================
// טעינת בתי עלמין
// ===================================================================
async function loadCemeteries() {
    console.log('📋 Loading cemeteries - v2.3.1 (Configuration Fix)...');

    try {
        // ניקוי הדשבורד - רק את מה שצריך
        if (typeof clearDashboard === 'function') {
            clearDashboard({
                targetLevel: 'cemetery',
                keepBreadcrumb: false,
                keepSidebar: false,
                keepCard: false,
                fullReset: false
            });
        }

        // בניית קונטיינר בתי עלמין
        buildCemeteriesContainer();

        // אתחול UniversalSearch
        initUniversalSearch();

        // טעינת נתוני בתי עלמין
        await cemeteriesSearch.search('', []);

        console.log('✅ Cemeteries loaded successfully');

    } catch (error) {
        console.error('❌ Error loading cemeteries:', error);
        alert('שגיאה בטעינת בתי עלמין. נסה שוב.');
    }
}

// ===================================================================
// בניית קונטיינר בתי עלמין
// ===================================================================
function buildCemeteriesContainer() {
    console.log('🏗️ Building cemeteries container - v2.3.1...');

    // מציאת או יצירת main-container
    let mainContainer = document.getElementById('main-container');
    if (!mainContainer) {
        console.log('⚠️ main-container not found, creating one...');
        mainContainer = document.createElement('div');
        mainContainer.id = 'main-container';
        mainContainer.className = 'main-content';
        
        const dashboardElement = document.querySelector('.dashboard, #dashboard');
        if (dashboardElement) {
            dashboardElement.appendChild(mainContainer);
        } else {
            document.body.appendChild(mainContainer);
        }
    }

    // יצירת מבנה HTML חדש - עם קונטיינר תוצאות ריק (כמו customers)
    mainContainer.innerHTML = `
        <div class="search-section">
            <div class="search-header">
                <h2>בתי עלמין</h2>
            </div>
            <div id="universal-search-container"></div>
        </div>
        
        <div class="results-section">
            <div id="cemeteries-results-container"></div>
        </div>
    `;

    console.log('✅ Cemeteries container built (v2.3.1)');
}

// ===================================================================
// אתחול UniversalSearch
// ===================================================================
function initUniversalSearch() {
    console.log('🔍 Initializing UniversalSearch for cemeteries - v2.3.1...');

    cemeteriesSearch = new UniversalSearch({
        dataSource: {
            endpoint: 'api/universal-search-api.php', // ✅ תוקן: היה apiUrl, עכשיו endpoint
            table: 'cemeteries',
            primaryKey: 'cemetery_id',
            displayName: 'name'
        },
        searchableFields: [
            'name',
            'location',
            'city',
            'ground_type',
            'ownership_type',
            'contact_person',
            'phone',
            'email'
        ],
        display: {
            title: 'name',
            subtitle: 'location',
            badge: (item) => item.block_count ? `${item.block_count} גושים` : null
        },
        results: {
            containerId: 'cemeteries-results-container',
            renderCallback: renderCemeteriesRows
        },
        behavior: {
            searchOnInit: true,
            minSearchLength: 0,
            debounceMs: 300
        },
        ui: {
            containerSelector: '#universal-search-container',
            placeholder: 'חיפוש בתי עלמין...',
            noResultsMessage: 'לא נמצאו בתי עלמין',
            theme: 'default'
        }
    });

    console.log('✅ UniversalSearch initialized for cemeteries (v2.3.1)');
}

// ===================================================================
// רינדור שורות בתי עלמין
// ===================================================================
function renderCemeteriesRows(results) {
    console.log('🎨 renderCemeteriesRows called with', results.length, 'items (v2.3.1)');

    const resultsContainer = document.getElementById('cemeteries-results-container');
    if (!resultsContainer) {
        console.error('❌ Results container not found!');
        return;
    }

    // ניקוי הקונטיינר
    resultsContainer.innerHTML = '';

    // אם אין תוצאות - הצגת הודעה
    if (!results || results.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">לא נמצאו בתי עלמין</div>';
        return;
    }

    // יצירת הטבלה - כעת בזמן הנכון!
    const tableContainer = document.createElement('div');
    tableContainer.className = 'table-container';
    
    tableContainer.innerHTML = `
        <table id="cemeteries-table" class="data-table">
            <thead>
                <tr>
                    <th>שם בית העלמין</th>
                    <th>מיקום</th>
                    <th>שטח (מ"ר)</th>
                    <th>סוג קרקע</th>
                    <th>סוג בעלות</th>
                    <th>מספר גושים</th>
                    <th>מספר חלקות</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody id="cemeteries-table-body"></tbody>
        </table>
    `;

    resultsContainer.appendChild(tableContainer);

    // כעת הטבלה קיימת ב-DOM - אפשר להוסיף שורות
    const tableBody = document.getElementById('cemeteries-table-body');
    if (!tableBody) {
        console.error('❌ Table body not found after creation!');
        return;
    }

    // הוספת שורות לטבלה
    results.forEach(cemetery => {
        const row = document.createElement('tr');
        row.dataset.id = cemetery.cemetery_id;
        row.dataset.name = cemetery.name || '';
        row.classList.add('data-row', 'clickable-row');

        row.innerHTML = `
            <td>${cemetery.name || ''}</td>
            <td>${cemetery.location || ''}</td>
            <td>${cemetery.total_area || ''}</td>
            <td>${cemetery.ground_type || ''}</td>
            <td>${cemetery.ownership_type || ''}</td>
            <td>${cemetery.block_count || 0}</td>
            <td>${cemetery.plot_count || 0}</td>
            <td>
                <button class="btn-icon btn-edit" onclick="editCemetery(${cemetery.cemetery_id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-delete" onclick="deleteCemetery(${cemetery.cemetery_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        // הוספת מאזין לקליק על השורה
        row.addEventListener('click', (e) => {
            if (!e.target.closest('button')) {
                loadBlocks(cemetery.cemetery_id, cemetery.name);
            }
        });

        tableBody.appendChild(row);
    });

    // יצירת TableManager רק אחרי שהטבלה והשורות קיימות ב-DOM
    console.log('✅ Creating TableManager with', results.length, 'items (v2.3.1)');
    
    cemeteriesTable = new TableManager({
        tableId: 'cemeteries-table',
        bodyId: 'cemeteries-table-body',
        itemsPerPage: CEMETERIES_ITEMS_PER_PAGE,
        totalItems: results.length,
        scrollThreshold: CEMETERIES_SCROLL_THRESHOLD,
        onScroll: () => {
            console.log('📜 User scrolled in cemeteries table');
        }
    });

    console.log('📊 Cemeteries table statistics:');
    console.log('  - Total items:', results.length);
    console.log('  - Items per page:', CEMETERIES_ITEMS_PER_PAGE);
    console.log('  - Scroll threshold:', CEMETERIES_SCROLL_THRESHOLD);
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
function editCemetery(cemeteryId) {
    console.log('✏️ Edit cemetery:', cemeteryId);
    // מימוש עריכה
    alert(`עריכת בית עלמין: ${cemeteryId}`);
}

function deleteCemetery(cemeteryId) {
    console.log('🗑️ Delete cemetery:', cemeteryId);
    
    if (!confirm('האם אתה בטוח שברצונך למחוק את בית העלמין?')) {
        return;
    }

    // מימוש מחיקה
    alert(`מחיקת בית עלמין: ${cemeteryId}`);
}

// ===================================================================
// פונקציות דיבאג
// ===================================================================
window.checkScrollStatus = function() {
    if (!cemeteriesTable) {
        console.warn('⚠️ TableManager not initialized yet');
        return;
    }

    const status = cemeteriesTable.getStatus();
    console.log('📊 Cemeteries Table Status:', status);
    return status;
};

// ===================================================================
// אתחול מודול
// ===================================================================
console.log('✅ Cemeteries Management Module Loaded - v2.3.1: Configuration Fix');
console.log('💡 Commands: checkScrollStatus() - בדוק כמה רשומות נטענו');