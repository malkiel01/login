/*
 * File: dashboards/dashboard/cemeteries/assets/js/blocks-management.js
 * Version: 1.0.2
 * Updated: 2025-10-26
 * Author: Malkiel
 * Change Summary:
 * - v1.0.2: תיקון מוחלט - זהה בדיוק ל-cemeteries-management.js
 * - שימוש ב-initBlocksTable() נפרד
 * - תיקון tableSelector במקום tableId
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentBlocks = [];
let blockSearch = null;
let blocksTable = null;
let editingBlockId = null;

// ===================================================================
// טעינת גושים (הפונקציה הראשית)
// ===================================================================
async function loadBlocks(cemeteryId = null, cemeteryName = null) {
    console.log('📋 Loading blocks - v1.0.2 (תוקן TableManager - זהה לcemeteries)...');
    
    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('blocksItem');
    }
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    
    // ⭐ נקה - DashboardCleaner ימחק גם את TableManager!
    if (typeof DashboardCleaner !== 'undefined') {
        DashboardCleaner.clear({ targetLevel: 'block' });
    } else if (typeof clearDashboard === 'function') {
        clearDashboard({ targetLevel: 'block' });
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
            block: { name: cemeteryName ? `גושים של ${cemeteryName}` : 'גושים' }
        };
        if (cemeteryId && cemeteryName) {
            breadcrumbData.cemetery = { id: cemeteryId, name: cemeteryName };
        }
        updateBreadcrumb(breadcrumbData);
    }
    
    // עדכון כותרת החלון
    document.title = cemeteryName ? `גושים - ${cemeteryName}` : 'ניהול גושים - מערכת בתי עלמין';
    
    // ⭐ בנה את המבנה החדש ב-main-container
    await buildBlocksContainer();
    
    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (blockSearch && typeof blockSearch.destroy === 'function') {
        console.log('🗑️ Destroying previous blockSearch instance...');
        blockSearch.destroy();
        blockSearch = null;
        window.blockSearch = null;
    }
    
    // אתחל את UniversalSearch מחדש תמיד
    console.log('🆕 Creating fresh blockSearch instance...');
    await initBlocksSearch(cemeteryId);
    blockSearch.search();
    
    // טען סטטיסטיקות
    await loadBlockStats(cemeteryId);
}

// ===================================================================
// ⭐ פונקציה חדשה - בניית המבנה של גושים ב-main-container
// ===================================================================
async function buildBlocksContainer() {
    console.log('🏗️ Building blocks container...');
    
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
    
    // ⭐ בנה את התוכן של גושים - זהה ללקוחות ובתי עלמין!
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש -->
        <div id="blockSearchSection" class="search-section"></div>
        
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
                                <span class="visually-hidden">טוען גושים...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Blocks container built');
}

// ===================================================================
// אתחול UniversalSearch - שימוש בפונקציה גלובלית!
// ===================================================================
async function initBlocksSearch(cemeteryId = null) {
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
                name: 'cemeteryNameHe',
                label: 'בית עלמין',
                table: 'cemeteries',
                type: 'text',
                matchType: ['exact', 'fuzzy']
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
        
        displayColumns: ['blockNameHe', 'blockCode', 'blockLocation', 'cemetery_name', 'comments', 'plots_count', 'createDate'],
        
        searchContainerSelector: '#blockSearchSection',
        resultsContainerSelector: '#tableBody',
        
        placeholder: 'חיפוש גושים לפי שם, קוד, מיקום...',
        itemsPerPage: 999999,
        
        renderFunction: renderBlocksRows,
        
        callbacks: {
            onInit: () => {
                console.log('✅ UniversalSearch initialized for blocks');
            },
            
            onSearch: (query, filters) => {
                console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
            },
            
            onResults: (data) => {
                console.log('📦 Results:', data.pagination?.total || data.total || 0, 'blocks found');
                currentBlocks = data.data;
            },
            
            onError: (error) => {
                console.error('❌ Search error:', error);
            },
            
            onEmpty: () => {
                console.log('📭 No results');
            }
        }
    };
    
    // אם יש סינון לפי בית עלמין, הוסף פרמטר
    if (cemeteryId) {
        config.additionalParams = { cemeteryId: cemeteryId };
    }
    
    blockSearch = window.initUniversalSearch(config);
    
    // ⭐ עדכן את window.blockSearch מיד!
    window.blockSearch = blockSearch;
    
    return blockSearch;
}

// ===================================================================
// אתחול TableManager - עם תמיכה ב-totalItems
// ===================================================================
async function initBlocksTable(data, totalItems = null) {
    // ⭐ אם לא קיבלנו totalItems, השתמש ב-data.length
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (blocksTable) {
        blocksTable.config.totalItems = actualTotalItems;  // ⭐ עדכן totalItems!
        blocksTable.setData(data);
        return blocksTable;
    }

    blocksTable = new TableManager({
        tableSelector: '#mainTable',  // ⭐ זה הכי חשוב!
        
        // ⭐ הוספת totalItems כפרמטר!
        totalItems: actualTotalItems,

        columns: [
            {
                field: 'blockNameHe',
                label: 'שם גוש',
                width: '200px',
                sortable: true,
                render: (block) => {
                    return `<a href="#" onclick="handleBlockDoubleClick('${block.unicId}', '${block.blockNameHe.replace(/'/g, "\\'")}'); return false;" 
                               style="color: #2563eb; text-decoration: none; font-weight: 500;">
                        ${block.blockNameHe}
                    </a>`;
                }
            },
            {
                field: 'blockCode',
                label: 'קוד',
                width: '100px',
                sortable: true
            },
            {
                field: 'blockLocation',
                label: 'מיקום',
                width: '100px',
                sortable: true
            },
            {
                field: 'cemetery_name',
                label: 'בית עלמין',
                width: '200px',
                sortable: true
            },
            {
                field: 'comments',
                label: 'הערות',
                width: '250px',
                sortable: true,
                render: (block) => {
                    const comments = block.comments || '';
                    return comments.length > 50 ? comments.substring(0, 50) + '...' : comments;
                }
            },
            {
                field: 'plots_count',
                label: 'חלקות',
                width: '80px',
                type: 'number',
                sortable: true,
                render: (block) => {
                    const count = block.plots_count || 0;
                    return `<span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">${count}</span>`;
                }
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (block) => formatDate(block.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (block) => `
                    <button class="btn btn-sm btn-secondary" onclick="editBlock('${block.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteBlock('${block.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                `
            }
        ],

        onRowDoubleClick: (block) => {
            handleBlockDoubleClick(block.unicId, block.blockNameHe);
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
            const count = blocksTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    // ⭐ עדכן את window.blocksTable מיד!
    window.blocksTable = blocksTable;
    
    return blocksTable;
}

// ===================================================================
// רינדור שורות גושים - עם תמיכה ב-totalItems מ-pagination
// ===================================================================
function renderBlocksRows(data, container, pagination = null) {
    
    // ⭐ חלץ את הסכום הכולל מ-pagination אם קיים
    const totalItems = pagination?.total || data.length;

    if (data.length === 0) {
        if (blocksTable) {
            blocksTable.setData([]);
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
    if (!tableWrapperExists && blocksTable) {
        console.log('🗑️ TableManager DOM was deleted, resetting blocksTable variable');
        blocksTable = null;
        window.blocksTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!blocksTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        initBlocksTable(data, totalItems);  // ⭐ העברת totalItems!
    } else {
          // ⭐ עדכן גם את totalItems ב-TableManager!
        if (blocksTable.config) {
            blocksTable.config.totalItems = totalItems;
        }
        
        // ⭐ אם יש עוד נתונים ב-UniversalSearch, הוסף אותם!
        if (blockSearch && blockSearch.state) {
            const allData = blockSearch.state.results || [];
            if (allData.length > data.length) {
                console.log(`📦 UniversalSearch has ${allData.length} items, updating TableManager...`);
                blocksTable.setData(allData);
                return;
            }
        }

        blocksTable.setData(data);
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
// טעינת סטטיסטיקות גושים
// ===================================================================
async function loadBlockStats(cemeteryId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/blocks-api.php?action=stats';
        if (cemeteryId) {
            url += `&cemeteryId=${cemeteryId}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            console.log('📊 Block stats:', result.data);
            
            // עדכון מונים בממשק אם קיימים
            if (document.getElementById('totalBlocks')) {
                document.getElementById('totalBlocks').textContent = result.data.total_blocks || 0;
            }
            if (document.getElementById('totalPlots')) {
                document.getElementById('totalPlots').textContent = result.data.total_plots || 0;
            }
            if (document.getElementById('newThisMonth')) {
                document.getElementById('newThisMonth').textContent = result.data.new_this_month || 0;
            }
        }
    } catch (error) {
        console.error('Error loading block stats:', error);
    }
}

// ===================================================================
// עריכת גוש
// ===================================================================
async function editBlock(blockId) {
    console.log('✏️ Editing block:', blockId);
    editingBlockId = blockId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/blocks-api.php?action=get&id=${blockId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'שגיאה בטעינת נתוני הגוש');
        }
        
        const block = result.data;
        
        // פתח את הטופס במודל
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

// ===================================================================
// מחיקת גוש
// ===================================================================
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
        
        // רענן את הנתונים
        if (blockSearch) {
            blockSearch.refresh();
        }
        
    } catch (error) {
        console.error('Error deleting block:', error);
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
    if (blockSearch) {
        blockSearch.refresh();
    }
}

// ===================================================================
// פונקציה לבדיקת סטטוס הטעינה
// ===================================================================
function checkScrollStatus() {
    if (!blocksTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = blocksTable.getFilteredData().length;
    const displayed = blocksTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(blocksTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================
// פונקציה לטיפול בדאבל-קליק על גוש
// ===================================================
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

window.handleBlockDoubleClick = handleBlockDoubleClick;

// ===================================================================
// Backward Compatibility - Aliases
// ===================================================================
window.loadAllBlocks = loadBlocks; // ✅ Alias לשם הישן

// ===================================================================
// הפוך את הפונקציות לגלובליות
// ===================================================================
window.loadBlocks = loadBlocks;
window.deleteBlock = deleteBlock;
window.editBlock = editBlock;
window.refreshData = refreshData;
window.blocksTable = blocksTable;
window.checkScrollStatus = checkScrollStatus;