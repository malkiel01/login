/*
 * File: dashboards/dashboard/cemeteries/assets/js/cemeteries-management.js
 * Version: 5.2.0
 * Updated: 2025-10-27
 * Author: Malkiel
 * Change Summary:
 * - v5.2.0: הוספת תצוגת כרטיס + גושים מתחת לבית עלמין
 * - תיקון handleCemeteryDoubleClick להצגת כרטיס קבוע עם רשימת גושים מתחת
 * - פונקציה חדשה displayCemeteryWithBlocks
 * - v5.1.0: תיקון קונפליקט שמות - initCemeteriesSearch (במקום initUniversalSearch)
 * - v5.0.0: שיטה זהה ללקוחות - UniversalSearch + TableManager
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
    console.log('📋 Loading cemeteries - v5.2.0 (כרטיס + גושים)...');

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
// ⭐ פונקציה חדשה - הצגת כרטיס בית עלמין עם רשימת גושים מתחת
// ===================================================================
async function displayCemeteryWithBlocks(cemeteryId, cemeteryName) {
    console.log('🏗️ Building cemetery card with blocks underneath...', cemeteryId);
    
    try {
        // מצא את main-container
        let mainContainer = document.querySelector('.main-container');
        
        if (!mainContainer) {
            console.error('❌ main-container not found');
            return;
        }
        
        // עדכן את הסוג הנוכחי
        window.currentType = 'block';
        window.currentParentId = cemeteryId;
        
        // עדכן breadcrumb
        if (typeof updateBreadcrumb === 'function') {
            updateBreadcrumb({ 
                cemetery: { id: cemeteryId, name: cemeteryName },
                block: { name: `גושים של ${cemeteryName}` }
            });
        }
        
        // עדכן כותרת החלון
        document.title = `גושים - ${cemeteryName} - מערכת בתי עלמין`;
        
        // ⭐ בנה מבנה חדש: כרטיס למעלה + רשימת גושים למטה
        mainContainer.innerHTML = `
            <!-- אזור הכרטיס של בית העלמין -->
            <div id="cemeteryCardContainer" style="margin-bottom: 20px;">
                <div style="text-align: center; padding: 30px;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">טוען פרטי בית עלמין...</span>
                    </div>
                </div>
            </div>
            
            <!-- אזור הגושים -->
            <div id="blocksSection">
                <!-- כותרת הגושים -->
                <div style="padding: 15px 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 18px; color: #2c3e50;">
                        📦 גושים של ${cemeteryName}
                    </h3>
                </div>
                
                <!-- סקשן חיפוש גושים -->
                <div id="blockSearchSection" class="search-section"></div>
                
                <!-- table-container עבור TableManager של גושים -->
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
                                        <span class="visually-hidden">טוען גושים...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        // ⭐ טען את הכרטיס של בית העלמין
        if (typeof createCemeteryCard === 'function') {
            const cardHtml = await createCemeteryCard(cemeteryId);
            if (cardHtml) {
                const cardContainer = document.getElementById('cemeteryCardContainer');
                if (cardContainer) {
                    cardContainer.innerHTML = cardHtml;
                }
            }
        }
        
        // ⭐ אתחל חיפוש גושים עם סינון לפי בית עלמין
        console.log('🆕 Initializing blocks search for cemetery:', cemeteryId);
        await initBlocksSearchInContext(cemeteryId);
        
        // עדכן כפתור הוספה
        if (typeof updateAddButtonText === 'function') {
            updateAddButtonText();
        }
        
        console.log('✅ Cemetery card with blocks displayed successfully');
        
    } catch (error) {
        console.error('❌ Error in displayCemeteryWithBlocks:', error);
        showToast('שגיאה בטעינת פרטי בית העלמין והגושים', 'error');
    }
}

// ===================================================================
// ⭐ אתחול חיפוש גושים בהקשר של בית עלמין (ללא ניקוי מסך)
// ===================================================================
async function initBlocksSearchInContext(cemeteryId) {
    // השמד אינסטנס קודם של חיפוש גושים אם קיים
    if (window.blockSearch && typeof window.blockSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous blockSearch instance...');
        window.blockSearch.destroy();
        window.blockSearch = null;
    }
    
    // הגדר את הקונפיגורציה של חיפוש גושים
    const config = {
        entityType: 'block',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/blocks-api.php',
        action: 'list',
        
        searchableFields: [
            {
                name: 'blockNameHe',
                label: 'שם גוש (עברית)',
                table: 'blocks',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'blockNameEn',
                label: 'שם גוש (אנגלית)',
                table: 'blocks',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'blockCode',
                label: 'קוד גוש',
                table: 'blocks',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'blockLocation',
                label: 'מיקום גוש',
                table: 'blocks',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'comments',
                label: 'הערות',
                table: 'blocks',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'blocks',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        displayColumns: ['blockNameHe', 'blockCode', 'blockLocation', 'comments', 'plots_count', 'createDate'],
        
        searchContainerSelector: '#blockSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש גושים לפי שם, קוד, מיקום...',
        itemsPerPage: 999999,
        
        renderFunction: renderBlocksRowsInContext,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for blocks in cemetery context');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching blocks:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'blocks found');
                window.currentBlocks = data.data;
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
            },
            
            onEmpty: () => {
                console.log('📭 No blocks found');
            }
        }
    };
    
    // ⭐ הוסף פילטר קבוע לפי בית עלמין
    if (cemeteryId) {
        config.additionalParams = { cemeteryId: cemeteryId };
    }
    
    // צור את אובייקט החיפוש
    window.blockSearch = window.initUniversalSearch(config);
    
    // הפעל חיפוש ראשוני
    if (window.blockSearch) {
        window.blockSearch.search();
    }
}

// ===================================================================
// ⭐ רינדור שורות גושים בהקשר של בית עלמין
// ===================================================================
function renderBlocksRowsInContext(data, container) {
    console.log('📊 Rendering blocks in context:', data.length, 'items');
    
    if (!Array.isArray(data) || data.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="100%" style="text-align: center; padding: 40px; color: #999;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                    <div>לא נמצאו גושים</div>
                </td>
            </tr>
        `;
        return;
    }
    
    // עדכן כותרות טבלה
    const headersRow = document.getElementById('tableHeaders');
    if (headersRow) {
        headersRow.innerHTML = `
            <th style="text-align: right; padding-right: 20px;">שם גוש</th>
            <th style="text-align: center;">קוד</th>
            <th style="text-align: center;">מיקום</th>
            <th style="text-align: center;">הערות</th>
            <th style="text-align: center;">חלקות</th>
            <th style="text-align: center;">תאריך יצירה</th>
            <th style="text-align: center; width: 120px;">פעולות</th>
        `;
    }
    
    // בנה שורות
    const rows = data.map(block => {
        const blockId = block.unicId || block.id;
        const blockName = block.blockNameHe || block.name || 'ללא שם';
        const blockCode = block.blockCode || block.code || '-';
        const blockLocation = block.blockLocation || block.location || '-';
        const comments = block.comments || '-';
        const plotsCount = block.plots_count || 0;
        const createDate = formatDate(block.createDate || block.created_at);
        
        return `
            <tr class="data-row" 
                data-id="${blockId}"
                ondblclick="handleBlockDoubleClick('${blockId}', '${blockName.replace(/'/g, "\\'")}')"
                style="cursor: pointer; transition: background-color 0.2s;"
                onmouseover="this.style.backgroundColor='#f8f9fa'"
                onmouseout="this.style.backgroundColor=''"
            >
                <td style="text-align: right; padding-right: 20px; font-weight: 500;">
                    ${blockName}
                </td>
                <td style="text-align: center;">
                    <span class="badge bg-secondary">${blockCode}</span>
                </td>
                <td style="text-align: center;">${blockLocation}</td>
                <td style="text-align: center; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    ${comments}
                </td>
                <td style="text-align: center;">
                    <span class="badge bg-info">${plotsCount}</span>
                </td>
                <td style="text-align: center;">${createDate}</td>
                <td style="text-align: center;">
                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); editBlock('${blockId}')" title="עריכה">
                        ✏️
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteBlock('${blockId}')" title="מחיקה">
                        🗑️
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    container.innerHTML = rows;
    
    // ⭐ אתחל TableManager אם לא קיים
    if (!window.blocksTable && typeof window.TableManager === 'function') {
        console.log('🆕 Initializing TableManager for blocks...');
        
        window.blocksTable = new window.TableManager({
            tableSelector: '#mainTable',
            itemsPerPage: 999999,
            data: data
        });
        
        console.log('✅ TableManager initialized with', data.length, 'blocks');
    } else if (window.blocksTable) {
        // עדכן נתונים קיימים
        if (window.blockSearch && window.blockSearch.state) {
            const allData = window.blockSearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                window.blocksTable.setData(allData);
                return;
            }
        }

        window.blocksTable.setData(data);
    }
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
            },
            
            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    });
    
    // שמור בגלובלי
    window.cemeterySearch = cemeterySearch;
}

// ===================================================================
// רינדור שורות בתי עלמין
// ===================================================================
function renderCemeteriesRows(data, container) {
    console.log('📊 Rendering cemeteries:', data.length, 'items');
    
    if (!Array.isArray(data) || data.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="100%" style="text-align: center; padding: 40px; color: #999;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                    <div>לא נמצאו בתי עלמין</div>
                </td>
            </tr>
        `;
        return;
    }
    
    // עדכן כותרות טבלה
    const headersRow = document.getElementById('tableHeaders');
    if (headersRow) {
        headersRow.innerHTML = `
            <th style="text-align: right; padding-right: 20px;">שם בית עלמין</th>
            <th style="text-align: center;">קוד</th>
            <th style="text-align: center;">כתובת</th>
            <th style="text-align: center;">איש קשר</th>
            <th style="text-align: center;">טלפון</th>
            <th style="text-align: center;">גושים</th>
            <th style="text-align: center;">תאריך יצירה</th>
            <th style="text-align: center; width: 120px;">פעולות</th>
        `;
    }
    
    // בנה שורות
    const rows = data.map(cemetery => {
        const cemeteryId = cemetery.unicId || cemetery.id;
        const cemeteryName = cemetery.cemeteryNameHe || cemetery.name || 'ללא שם';
        const cemeteryCode = cemetery.cemeteryCode || cemetery.code || '-';
        const address = cemetery.address || '-';
        const contactName = cemetery.contactName || '-';
        const contactPhone = cemetery.contactPhoneName || cemetery.phone || '-';
        const blocksCount = cemetery.blocks_count || 0;
        const createDate = formatDate(cemetery.createDate || cemetery.created_at);
        
        return `
            <tr class="data-row" 
                data-id="${cemeteryId}"
                ondblclick="handleCemeteryDoubleClick('${cemeteryId}', '${cemeteryName.replace(/'/g, "\\'")}')"
                style="cursor: pointer; transition: background-color 0.2s;"
                onmouseover="this.style.backgroundColor='#f8f9fa'"
                onmouseout="this.style.backgroundColor=''"
            >
                <td style="text-align: right; padding-right: 20px; font-weight: 500;">
                    ${cemeteryName}
                </td>
                <td style="text-align: center;">
                    <span class="badge bg-primary">${cemeteryCode}</span>
                </td>
                <td style="text-align: center;">${address}</td>
                <td style="text-align: center;">${contactName}</td>
                <td style="text-align: center;">${contactPhone}</td>
                <td style="text-align: center;">
                    <span class="badge bg-info">${blocksCount}</span>
                </td>
                <td style="text-align: center;">${createDate}</td>
                <td style="text-align: center;">
                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); editCemetery('${cemeteryId}')" title="עריכה">
                        ✏️
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteCemetery('${cemeteryId}')" title="מחיקה">
                        🗑️
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    container.innerHTML = rows;
    
    // ⭐ אתחל TableManager אם לא קיים
    if (!cemeteriesTable && typeof window.TableManager === 'function') {
        console.log('🆕 Initializing TableManager...');
        
        cemeteriesTable = new window.TableManager({
            tableSelector: '#mainTable',
            itemsPerPage: 999999,
            data: data
        });
        
        window.cemeteriesTable = cemeteriesTable;
        console.log('✅ TableManager initialized with', data.length, 'cemeteries');
    } else if (cemeteriesTable) {
        // עדכן נתונים קיימים
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
// טעינת סטטיסטיקות בתי עלמין
// ===================================================================
async function loadCemeteryStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=stats');
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Cemetery stats:', result.data);
            
            // עדכון מונים בממשק אם קיימים
            if (document.getElementById('totalCemeteries')) {
                document.getElementById('totalCemeteries').textContent = result.data.total_cemeteries || 0;
            }
            if (document.getElementById('totalBlocks')) {
                document.getElementById('totalBlocks').textContent = result.data.total_blocks || 0;
            }
            if (document.getElementById('newThisMonth')) {
                document.getElementById('newThisMonth').textContent = result.data.new_this_month || 0;
            }
        }
    } catch (error) {
        console.error('Error loading cemetery stats:', error);
    }
}

// ===================================================================
// עריכת בית עלמין
// ===================================================================
async function editCemetery(cemeteryId) {
    console.log('✏️ Editing cemetery:', cemeteryId);
    editingCemeteryId = cemeteryId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=get&id=${cemeteryId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה בטעינת נתוני בית העלמין');
        }
        
        const cemetery = result.data;
        
        // פתח את הטופס במודל
        if (typeof openFormModal === 'function') {
            openFormModal('cemetery', cemetery);
        } else {
            console.log('📝 Cemetery data:', cemetery);
            alert('פונקציית openFormModal לא זמינה');
        }
        
    } catch (error) {
        console.error('Error editing cemetery:', error);
        showToast('שגיאה בטעינת נתוני בית העלמין', 'error');
    }
}

// ===================================================================
// מחיקת בית עלמין
// ===================================================================
async function deleteCemetery(cemeteryId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את בית העלמין?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=delete&id=${cemeteryId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת בית העלמין');
        }
        
        showToast('בית העלמין נמחק בהצלחה', 'success');
        
        // רענן את הנתונים
        if (cemeterySearch) {
            cemeterySearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting cemetery:', error);
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
// פונקציה לטיפול בדאבל-קליק על בית עלמין
// ⭐ שונתה להצגת כרטיס + גושים מתחת
// ===================================================
async function handleCemeteryDoubleClick(cemeteryId, cemeteryName) {
    console.log('🖱️ Double-click on cemetery:', cemeteryName, cemeteryId);
    
    try {
        // ⭐ הצג כרטיס בית עלמין + גושים מתחת
        await displayCemeteryWithBlocks(cemeteryId, cemeteryName);
        
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
window.displayCemeteryWithBlocks = displayCemeteryWithBlocks;
window.editBlock = editBlock;
window.deleteBlock = deleteBlock;
window.handleBlockDoubleClick = handleBlockDoubleClick;

// ===================================================================
// ⭐ פונקציות עזר לגושים (מיובאות מ-blocks-management.js)
// ===================================================================
async function editBlock(blockId) {
    console.log('✏️ Editing block:', blockId);
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/blocks-api.php?action=get&id=${blockId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה בטעינת נתוני הגוש');
        }
        
        const block = result.data;
        
        if (typeof openFormModal === 'function') {
            openFormModal('block', block);
        } else {
            console.log('📝 Block data:', block);
            alert('פונקציית openFormModal לא זמינה');
        }
        
    } catch (error) {
        console.error('Error editing block:', error);
        showToast('שגיאה בטעינת נתוני הגוש', 'error');
    }
}

async function deleteBlock(blockId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את הגוש?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/blocks-api.php?action=delete&id=${blockId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה במחיקת הגוש');
        }
        
        showToast('הגוש נמחק בהצלחה', 'success');
        
        // רענן את חיפוש הגושים
        if (window.blockSearch) {
            window.blockSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting block:', error);
        showToast(error.message, 'error');
    }
}

async function handleBlockDoubleClick(blockId, blockName) {
    console.log('🖱️ Double-click on block:', blockName, blockId);
    
    try {
        // יצירת והצגת כרטיס
        if (typeof createBlockCard === 'function') {
            const cardHtml = await createBlockCard(blockId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        }
        
        // טעינת חלקות
        console.log('📦 Loading plots for block:', blockName);
        if (typeof loadPlots === 'function') {
            loadPlots(blockId, blockName);
        } else {
            console.warn('loadPlots function not found');
        }
        
    } catch (error) {
        console.error('❌ Error in handleBlockDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי הגוש', 'error');
    }
}