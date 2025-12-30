/*
 * File: dashboards/dashboard/cemeteries/assets/js/blocks-management.js
 * Version: 1.3.0
 * Updated: 2025-11-03
 * Author: Malkiel
 * Change Summary:
 * - v1.3.0: הוספת תמיכה מלאה בטעינה מדורגת
 *   - pagination מצטברת עם scroll loading אינסופי
 *   - סינון client-side מתקדם לפי cemeteryId
 *   - עדכון אוטומטי של state.totalResults
 *   - תיקון כפתורי Delete לקרוא ל-deleteBlock()
 *   - תמיכה בכמות רשומות בלתי מוגבלת
 * - v1.2.0: תיקון קריטי - שמירת סינון קיים
 * - v1.1.0: תיקון סינון גושים לפי בית עלמין
 * - v1.0.0: גרסה ראשונית - ניהול גושים
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentBlocks = [];
let blockSearch = null;
let blocksTable = null;
let editingBlockId = null;

// ⭐ חדש: שמירת ה-cemetery context הנוכחי
let currentCemeteryId = null;
let currentCemeteryName = null;

// ===================================================================
// טעינת גושים (הפונקציה הראשית)
// ===================================================================
async function loadBlocks(cemeteryId = null, cemeteryName = null, forceReset = false) {

    const signal = OperationManager.start('block');
    
    // ⭐ שינוי: אם קוראים ללא פרמטרים (מהתפריט) - אפס את הסינון!
    if (cemeteryId === null && cemeteryName === null && !forceReset) {
        // בדוק אם יש סינון קיים מהעבר
        if (window.currentCemeteryId !== null || currentCemeteryId !== null) {
            currentCemeteryId = null;
            currentCemeteryName = null;
            window.currentCemeteryId = null;
            window.currentCemeteryName = null;
        }
    } else if (forceReset) {
        currentCemeteryId = null;
        currentCemeteryName = null;
        window.currentCemeteryId = null;
        window.currentCemeteryName = null;
    } else {
        // יש cemeteryId - עדכן את הסינון
        currentCemeteryId = cemeteryId;
        currentCemeteryName = cemeteryName;
        window.currentCemeteryId = cemeteryId;
        window.currentCemeteryName = cemeteryName;
    }
    
    
    window.currentCemeteryId = currentCemeteryId;
    window.currentCemeteryName = currentCemeteryName;
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'block';
    window.currentParentId = cemeteryId;

    // ⭐ עדכן גם את tableRenderer.currentType!
    if (window.tableRenderer) {
        window.tableRenderer.currentType = 'block';
    }
    
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

    // עדכון פריט תפריט אקטיבי
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('blocksItem');
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
    await buildBlocksContainer(signal, cemeteryId, cemeteryName);

    if (OperationManager.shouldAbort('block')) {
        return;
    }

    // ⭐ תמיד השמד את החיפוש הקודם ובנה מחדש
    if (blockSearch && typeof blockSearch.destroy === 'function') {
        blockSearch.destroy();
        blockSearch = null;
        window.blockSearch = null;
    }
    
    // אתחל את UniversalSearch מחדש תמיד
    await initBlocksSearch(signal, cemeteryId);

    if (OperationManager.shouldAbort('block')) {
        return;
    }

    blockSearch.search();
    
    // טען סטטיסטיקות
    await loadBlockStats(signal, cemeteryId);
}

// ===================================================================
// ⭐ פונקציה מעודכנת - בניית המבנה של גושים ב-main-container
// ===================================================================
async function buildBlocksContainer(signal, cemeteryId = null, cemeteryName = null) {
    
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
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
    
    // ⭐⭐⭐ טעינת כרטיס מלא של בית העלמין במקום indicator פשוט!
    let topSection = '';
    if (cemeteryId && cemeteryName) {
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createCemeteryCard === 'function') {
            try {
                topSection = await createCemeteryCard(cemeteryId, signal);
            } catch (error) {
                // ⭐ טפל ב-AbortError!
                if (error.name === 'AbortError') {
                    return; // עצור את הפונקציה
                }
                console.error('❌ Error creating cemetery card:', error);
            }
        } else {
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            topSection = `
                <div class="filter-indicator" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🏛️</span>
                        <div>
                            <div style="font-size: 12px; opacity: 0.9;">מציג גושים עבור</div>
                            <div style="font-size: 16px; font-weight: 600;">${cemeteryName}</div>
                        </div>
                    </div>
                    <button onclick="loadBlocks(null, null, true)" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        ✕ הצג הכל
                    </button>
                </div>
            `;
        }
    }
    
    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        return;
    }

    mainContainer.innerHTML = `
        ${topSection}
        
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
    
}

// ===================================================================
// אתחול UniversalSearch - עם סינון משופר!
// ===================================================================
async function initBlocksSearch(signal, cemeteryId = null) {
    const config = {
        entityType: 'block',
        signal: signal,
        action: 'list',
        
        searchContainerSelector: '#blockSearchSection',
        resultsContainerSelector: '#tableBody',
        
        itemsPerPage: 999999,
        
        renderFunction: renderBlocksRows,

        callbacks: {
            onInit: () => {
            },
            
            onSearch: (query, filters) => {
            },

            onResults: (data) => {

                if (window.currentType !== 'block') {
                    return;
                }
                
                const currentPage = data.pagination?.page || 1;
                
                if (currentPage === 1) {
                    currentBlocks = data.data;
                } else {
                    currentBlocks = [...currentBlocks, ...data.data];
                }
                
                let filteredCount = currentBlocks.length;
                if (currentCemeteryId && currentBlocks.length > 0) {
                    const filteredData = currentBlocks.filter(block => {
                        const blockCemeteryId = block.cemeteryId || block.cemetery_id || block.CemeteryId;
                        return String(blockCemeteryId) === String(currentCemeteryId);
                    });
                    
                    
                    currentBlocks = filteredData;
                    filteredCount = filteredData.length;
                    
                    if (data.pagination) {
                        data.pagination.total = filteredCount;
                    }
                }
                
                if (blockSearch && blockSearch.state) {
                    blockSearch.state.totalResults = filteredCount;
                    if (blockSearch.updateCounter) {
                        blockSearch.updateCounter();
                    }
                }
                
            },
                    
            onError: (error) => {
                console.error('❌ Search error:', error);
                showToast('שגיאה בחיפוש גושים', 'error');
            },

            onEmpty: () => {
            }
        }
    };
    
    // ⭐ אם יש סינון לפי בית עלמין, הוסף פרמטר ל-API
    if (cemeteryId) {
        config.additionalParams = { cemeteryId: cemeteryId };
    }
    
    blockSearch = await window.initUniversalSearch(config);
    
    window.blockSearch = blockSearch;
    
    return blockSearch;
}

// ===================================================================
// אתחול TableManager לגושים
// ===================================================================
async function initBlocksTable(data, totalItems = null, signal) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (blocksTable) {
        blocksTable.config.totalItems = actualTotalItems;
        blocksTable.setData(data);
        return blocksTable;
    }

    async function loadColumnsFromConfig(entityType = 'block', signal) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${entityType}&section=table_columns`, {
                signal: signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // ⭐⭐⭐ בדיקה קריטית - אם עברנו לרשומה אחרת, לא להמשיך!
            if (window.currentType !== 'block') {
                return; // ❌ עצור כאן!
            }

            const result = await response.json();
            
            if (!result.success || !result.data) {
                throw new Error(result.error || 'Failed to load columns config');
            }

            // המרה לפורמט של TableManager
            const columns = result.data.map(col => {
                const column = {
                    field: col.field,
                    label: col.title,
                    width: col.width || 'auto',
                    sortable: col.sortable !== false,
                    type: col.type || 'text'
                };
                
                // טיפול בסוגים מיוחדים - לגושים!
                switch(col.type) {
                    case 'link':
                        if (col.field === 'blockNameHe') {
                            column.render = (block) => {
                                return `<a href="#" onclick="handleBlockDoubleClick('${block.unicId}', '${block.blockNameHe?.replace(/'/g, "\\'")}'); return false;" 
                                        style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                    ${block.blockNameHe}
                                </a>`;
                            };
                        }
                        break;
                        
                    case 'badge':
                        column.render = (block) => {
                            const count = block[col.field] || 0;
                            return `<span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">${count}</span>`;
                        };
                        break;
                        
                    case 'status':
                        column.render = (block) => {
                            return block[col.field] == 1 
                                ? '<span class="status-badge status-active">פעיל</span>'
                                : '<span class="status-badge status-inactive">לא פעיל</span>';
                        };
                        break;
                        
                    case 'date':
                        column.render = (block) => formatDate(block[col.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteBlock('${item.unicId}')" 
                                    title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        `;
                        break;

                    default:
                        // עמודת טקסט רגילה
                        if (!column.render) {
                            column.render = (item) => item[column.field] || '-';
                        }
                }
                
                return column;
            });
            
            return columns;

        } catch (error) {
            // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
            if (error.name === 'AbortError') {
                return [];
            }
            console.error('Failed to load columns config:', error);
            return [];
        }
    }

    // קודם טען את העמודות
    const columns = await loadColumnsFromConfig('block', signal);

    // בדוק אם בוטל
    if (signal && signal.aborted) {
        return null;
    }

    blocksTable = new TableManager({
        tableSelector: '#mainTable',
        totalItems: actualTotalItems,
        columns: columns,
        data: data,     
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        onSort: (field, order) => {
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            const count = blocksTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });

    // ⭐ מאזין לגלילה - טען עוד דפים!
    const bodyContainer = document.querySelector('.table-body-container');
    if (bodyContainer && blockSearch) {
        bodyContainer.addEventListener('scroll', async function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100) {
                if (!blockSearch.state.isLoading && blockSearch.state.currentPage < blockSearch.state.totalPages) {
                    
                    const nextPage = blockSearch.state.currentPage + 1;
                    blockSearch.state.currentPage = nextPage;
                    blockSearch.state.isLoading = true;
                    await blockSearch.search();
                }
            }
        });
    }
    
    window.blocksTable = blocksTable;
    return blocksTable;
}

// ===================================================================
// רינדור שורות הגושים - בדיוק כמו בבתי עלמין
// ===================================================================
function renderBlocksRows(data, container, pagination = null, signal = null) {
    
    // ⭐ סינון client-side לפי cemeteryId
    let filteredData = data;
    if (currentCemeteryId) {
        filteredData = data.filter(block => 
            block.cemeteryId === currentCemeteryId || 
            block.cemetery_id === currentCemeteryId
        );
    }
    
    // ⭐ עדכן את totalItems להיות המספר המסונן!
    const totalItems = filteredData.length;
    

    if (filteredData.length === 0) {
        if (blocksTable) {
            blocksTable.setData([]);
        }
        
        // ⭐⭐⭐ הודעה מותאמת לבית עלמין ריק!
        if (currentCemeteryId && currentCemeteryName) {
            // נכנסנו לבית עלמין ספציפי ואין גושים
            container.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין גושים בבית עלמין ${currentCemeteryName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                בית העלמין עדיין לא מכיל גושים. תוכל להוסיף גוש חדש
                            </div>
                            <button 
                                onclick="if(typeof FormHandler !== 'undefined' && FormHandler.openForm) { FormHandler.openForm('block', '${currentCemeteryId}', null); } else { alert('FormHandler לא זמין'); }" 
                                style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                                       color: white; 
                                       border: none; 
                                       padding: 12px 24px; 
                                       border-radius: 8px; 
                                       font-size: 15px; 
                                       font-weight: 600; 
                                       cursor: pointer; 
                                       box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                                       transition: all 0.2s;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';">
                                ➕ הוסף גוש ראשון
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // חיפוש כללי שלא מצא תוצאות
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
        }
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && blocksTable) {
        blocksTable = null;
        window.blocksTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!blocksTable || !tableWrapperExists) {
        // אין TableManager או שה-DOM שלו נמחק - בנה מחדש!
        initBlocksTable(filteredData, totalItems, signal);
    } else {
        // ⭐ עדכן גם את totalItems ב-TableManager!
        if (blocksTable.config) {
            blocksTable.config.totalItems = totalItems;
        }
        
        blocksTable.setData(filteredData);
    }

    // ⭐ עדכן את התצוגה של UniversalSearch
    if (blockSearch) {
        blockSearch.state.totalResults = totalItems;
        blockSearch.updateCounter();
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
async function loadBlockStats(signal, cemeteryId = null) {
    try {
        let url = '/dashboard/dashboards/cemeteries/api/blocks-api.php?action=stats';
        if (cemeteryId) {
            url += `&cemeteryId=${cemeteryId}`;
        }
        
        const response = await fetch(url, { signal: signal });
        const result = await response.json();
        
        if (result.success && result.data) {
            
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
        // ⭐ בדיקה: אם זה ביטול מכוון - זה לא שגיאה
        if (error.name === 'AbortError') {
            return;
        }
        
        // רק שגיאות אמיתיות נדפסות כשגיאה
        console.error('Error loading block stats:', error);
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
        return;
    }
    
    const total = blocksTable.getFilteredData().length;
    const displayed = blocksTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    
    if (remaining > 0) {
    } else {
    }
}

// ===================================================================
// פונקציה לטיפול בדאבל-קליק על גוש
// ===================================================================
async function handleBlockDoubleClick(blockId, blockName) {
    
    try {
        // טעינת חלקות
        if (typeof loadPlots === 'function') {
            loadPlots(blockId, blockName);
        } else {
        }
        
    } catch (error) {
        console.error('❌ Error in handleBlockDoubleClick:', error);
        showToast('שגיאה בטעינת פרטי הגוש', 'error');
    }
}

window.handleBlockDoubleClick = handleBlockDoubleClick;

// ===================================================================
// הפוך את הפונקציות לגלובליות
// ===================================================================
window.loadBlocks = loadBlocks;
window.deleteBlock = deleteBlock;
window.refreshData = refreshData;
window.blocksTable = blocksTable;
window.checkScrollStatus = checkScrollStatus;
window.currentCemeteryId = currentCemeteryId;
window.currentCemeteryName = currentCemeteryName;
window.blockSearch = blockSearch;