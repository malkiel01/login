/*
 * File: dashboards/dashboard/cemeteries/assets/js/area-graves-management.js
 * Version: 1.5.4
 * Updated: 2025-11-16
 * Author: Malkiel
 * Change Summary:
 * - v1.5.4: 🐛 תיקון שתי בעיות קריטיות:
 *   - תיקון: שדה חיפוש מוסתר - הסרת style="display: none;"
 *   - תיקון: שכפול טבלה - הוספת await ל-initAreaGravesTable
 *   - הפיכת renderAreaGravesRows ל-async function
 */


console.log('🚀 area-graves-management.js v1.5.4 - Loading...');


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentAreaGraves = [];
let areaGraveSearch = null;
let areaGravesTable = null;
let editingAreaGraveId = null;

let areaGravesIsSearchMode = false;      // האם אנחנו במצב חיפוש?
let areaGravesCurrentQuery = '';         // מה החיפוש הנוכחי?
let areaGravesSearchResults = [];        // תוצאות החיפוש

// ⭐ שמירת ה-plot context הנוכחי
let areaGravesFilterPlotId = null;
let areaGravesFilterPlotName = null;

// ⭐ Infinite Scroll - מעקב אחרי עמוד נוכחי (שמות ייחודיים!)
let areaGravesCurrentPage = 1;
let areaGravesTotalPages = 1;
let areaGravesIsLoadingMore = false;

// ===================================================================
// בניית המבנה
// ===================================================================
async function buildAreaGravesContainer(signal, plotId = null, plotName = null) {
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

    // ⭐⭐⭐ טעינת כרטיס מלא במקום indicator פשוט!
    let topSection = '';
    if (plotId && plotName) {
        console.log('🎴 Creating full plot card...');
        
        // נסה ליצור את הכרטיס המלא
        if (typeof createPlotCard === 'function') {
            try {
                topSection = await createPlotCard(plotId, signal);
                console.log('✅ Plot card created successfully');
            } catch (error) {
                // בדיקה: אם זה ביטול מכוון - זה לא שגיאה
                if (error.name === 'AbortError') {
                    console.log('⚠️ Plot card loading aborted');
                    return;
                }
                console.error('❌ Error creating block card:', error);
            }
        } else {
            console.warn('⚠️ createPlotCard function not found');
        }
        
        // אם לא הצלחנו ליצור כרטיס, נשתמש ב-fallback פשוט
        if (!topSection) {
            console.log('⚠️ Using simple filter indicator as fallback');
            topSection = `
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
            `;
        }
    }

    // ⭐ בדיקה - אם הפעולה בוטלה, אל תמשיך!
    if (signal && signal.aborted) {
        console.log('⚠️ Build areaGraves container aborted before innerHTML');
        return;
    }
    
    mainContainer.innerHTML = `
        ${topSection}
        
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
async function initAreaGravesSearch(signal, plotId) {
    console.log('🔍 אתחול חיפוש שורות קבר...');
    
    // ⭐ טוען searchableFields מהשרת
    let searchableFields = [];

    try {
        const fieldsResponse = await fetch(
            `/dashboard/dashboards/cemeteries/api/get-config.php?type=areaGrave&section=searchableFields`,
            { signal: signal }
        );
        const fieldsResult = await fieldsResponse.json();
        
        if (fieldsResult.success && fieldsResult.data) {
            searchableFields = fieldsResult.data;
        }
    } catch (error) {
        console.error('❌ Error loading searchableFields:', error);
    }

    // ⭐ השתמש בקונפיג הישן - זה עובד!
    const config = {
        entityType: 'area-grave',  // ⭐ חובה!
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
        
        searchableFields: searchableFields || [],
        
        displayColumns: [
            { key: 'areaGraveNameHe', label: 'שם' },
            { key: 'coordinates', label: 'מיקום' },
            { key: 'graveType', label: 'סוג' },
            { key: 'graves_count', label: 'כמות קברים' }
        ],

        searchContainerSelector: '#areaGraveSearchSection',
        resultsContainerSelector: '#tableBody',  
        
        // ⭐ Infinite Scroll אמיתי - טעינה מדורגת
        apiLimit: 200,
        showPagination: false,
        
        apiParams: {
            level: 'area-grave',
            plotId: plotId
        },
        
        renderFunction: (data, container, pagination, signal) => {
            // ⭐ עדכן מצב חיפוש
            areaGravesIsSearchMode = true;
            
            // שמור תוצאות
            if (pagination && pagination.page === 1) {
                areaGravesSearchResults = data;
            } else {
                areaGravesSearchResults = [...areaGravesSearchResults, ...data];
            }

            // קריאה לפונקציה המקורית עם כל הפרמטרים
            renderAreaGravesRows(data, container, pagination, signal);
        },

        callbacks: {
            // ⭐ לפני חיפוש - נקה הכל והצג spinner
            onSearch: (query, filters) => {
                console.log('🔍 מתחיל חיפוש:', query);
                
                // ⭐ מחק את TableManager הישן
                const existingWrapper = document.querySelector('.table-wrapper[data-table-manager]');
                if (existingWrapper) {
                    console.log('🗑️ מוחק table-wrapper קיים');
                    existingWrapper.remove();
                }
                
                // ⭐ אפס את המשתנה
                if (areaGravesTable) {
                    areaGravesTable = null;
                    window.areaGravesTable = null;
                }
                
                // ⭐ הצג spinner בטבלה המקורית
                const originalTableBody = document.getElementById('tableBody');
                if (originalTableBody) {
                    // ⭐ הצג את הטבלה המקורית
                    const mainTable = document.getElementById('mainTable');
                    if (mainTable) {
                        mainTable.style.display = 'table';
                    }
                    
                    originalTableBody.innerHTML = `
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 60px;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
                                    <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; border-width: 0.3em;">
                                        <span class="visually-hidden">מחפש...</span>
                                    </div>
                                    <div style="font-size: 16px; color: #6b7280;">מחפש "${query}"...</div>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            },
            
            // ⭐ כשנתונים נטענו
            onDataLoaded: (response) => {
                console.log('✅ נתונים נטענו:', response.data.length);
                
                // עדכון מונה כולל ב-TableManager
                if (window.areaGravesTable && response.pagination) {
                    window.areaGravesTable.updateTotalItems(response.pagination.total);
                }
            },
            
            // ⭐ כשמנקים חיפוש
            onClear: () => {
                console.log('🧹 מנקה חיפוש...');
                
                areaGravesIsSearchMode = false;
                areaGravesCurrentQuery = '';
                areaGravesSearchResults = [];
                
                // ⭐ מחק את TableManager
                const existingWrapper = document.querySelector('.table-wrapper[data-table-manager]');
                if (existingWrapper) {
                    existingWrapper.remove();
                }
                
                if (areaGravesTable) {
                    areaGravesTable = null;
                    window.areaGravesTable = null;
                }
                
                // חזרה ל-Browse
                loadAreaGravesBrowseData(areaGravesFilterPlotId);
            }
        }
    };
    
    // יצירת instance
    const searchInstance = window.initUniversalSearch(config);
    
    // שמירה גלובלית
    window.areaGraveSearch = searchInstance;
    
    return searchInstance;
}

// ===================================================================
// אתחול TableManager - עם Scroll Loading!
// ===================================================================
async function initAreaGravesTable(data, totalItems = null, signal) {
    const actualTotalItems = totalItems !== null ? totalItems : data.length;
    
    if (areaGravesTable) {
        areaGravesTable.config.totalItems = actualTotalItems;
        areaGravesTable.setData(data);
        return areaGravesTable;
    }

    async function loadColumnsFromConfig(entityType = 'areaGrave', signal) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${entityType}&section=table_columns`, {
                signal: signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success || !result.data) {
                throw new Error(result.error || 'Failed to load columns config');
            }

            const columns = result.data.map(col => {
                const column = {
                    field: col.field,
                    label: col.title,
                    width: col.width,
                    sortable: col.sortable !== false
                };
                
                // טיפול בסוגים מיוחדים
                switch(col.type) {
                    case 'link':
                        column.render = (areaGrave) => {
                            return `<a href="#" onclick="handleAreaGraveDoubleClick('${areaGrave.unicId}', '${areaGrave.areaGraveNameHe?.replace(/'/g, "\\'")}'); return false;" 
                                    style="color: #2563eb; text-decoration: none; font-weight: 500;">
                                ${areaGrave.areaGraveNameHe}
                            </a>`;
                        };
                        break;
                        
                    case 'coordinates':
                        column.render = (areaGrave) => {
                            const coords = areaGrave.coordinates || '-';
                            return `<span style="font-family: monospace; font-size: 12px;">${coords}</span>`;
                        };
                        break;
                        
                    case 'graveType':
                        column.render = (areaGrave) => {
                            const typeName = getGraveTypeName(areaGrave.graveType);
                            return `<span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${typeName}</span>`;
                        };
                        break;
                        
                    case 'row':
                        column.render = (areaGrave) => {
                            const rowName = areaGrave.row_name || areaGrave.lineNameHe || '-';
                            return `<span style="color: #6b7280;">📏 ${rowName}</span>`;
                        };
                        break;
                        
                    case 'badge':
                        column.render = (areaGrave) => {
                            const count = areaGrave[col.field] || 0;
                            return `<span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">${count}</span>`;
                        };
                        break;
                        
                    case 'date':
                        column.render = (areaGrave) => formatDate(areaGrave[col.field]);
                        break;
                        
                    case 'actions':
                        column.render = (item) => `
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="event.stopPropagation(); window.tableRenderer.editItem('${item.unicId}')" 
                                    title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="event.stopPropagation(); deleteAreaGrave('${item.unicId}')" 
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
                console.log('⚠️ Columns loading aborted');
                return [];
            }
            console.error('Failed to load columns config:', error);
            return [];
        }
    }

    // קודם טען את העמודות
    const columns = await loadColumnsFromConfig('areaGrave', signal);

    // בדוק אם בוטל
    if (signal && signal.aborted) {
        console.log('⚠️ AreaGrave table initialization aborted');
        return null;
    }

    areaGravesTable = new TableManager({
        tableSelector: '#mainTable',   
        columns: columns,
        data: data,      
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: false,

        tableHeight: 'calc(100vh - 650px)',  // גובה דינמי לפי מסך
        tableMinHeight: '500px',

        totalItems: actualTotalItems,        // ⭐ סה"כ רשומות במערכת (מה-pagination)
        scrollLoadBatch: 100,                // ⭐ טען 100 שורות בכל גלילה (client-side)
        itemsPerPage: 999999,                // ⭐ עמוד אחד גדול = כל הנתונים
        scrollThreshold: 200,                // ⭐ התחל טעינה 200px לפני התחתית
        showPagination: false,               // ⭐ ללא footer pagination

        onLoadMore: async () => {
            if (areaGravesIsSearchMode) {
                // ⭐ חיפוש - טען דרך UniversalSearch
                if (areaGraveSearch && typeof areaGraveSearch.loadNextPage === 'function') {
                    if (areaGraveSearch.state.currentPage >= areaGraveSearch.state.totalPages) {
                        areaGravesTable.state.hasMoreData = false;
                        return;
                    }
                    await areaGraveSearch.loadNextPage();
                }
            } else {
                // ⭐ Browse - טען ישירות
                const success = await appendMoreAreaGraves();
                if (!success) {
                    areaGravesTable.state.hasMoreData = false;
                }
            }
        },

        renderFunction: (pageData) => {
            // ⭐ זה לא ישמש - UniversalSearch ירנדר ישירות
            return renderAreaGravesRows(pageData);
        },
    

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
    
    window.areaGravesTable = areaGravesTable;
    
    return areaGravesTable;
}


// ===================================================================
// רינדור שורות - עם סינון client-side! (⭐⭐ כמו ב-blocks!)
// ===================================================================

/**
 * רינדור שורות טבלה - פונקציה מלאה עם כל הלוגיקה!
 * v1.3.2 - שוחזרה הפונקציה המקורית המלאה
 */
function renderAreaGravesRows(data, container, pagination = null, signal = null) {
    // ⭐⭐ סינון client-side לפי plotId
    let filteredData = data;

    if (!areaGravesIsSearchMode && areaGravesFilterPlotId) {
        filteredData = data.filter(ag => {
            const agPlotId = ag.plotId || ag.plot_id || ag.PlotId;
            return String(agPlotId) === String(areaGravesFilterPlotId);
        });
    }
    
    // ⭐ עדכן את totalItems מה-pagination (סה"כ במערכת, לא רק מה שנטען!)
    const totalItems = pagination?.totalAll || pagination?.total || filteredData.length;
    
    console.log('🔍 [DEBUG renderAreaGravesRows]');
    console.log('  pagination:', pagination);
    console.log('  totalItems calculated:', totalItems);
    console.log('  filteredData.length:', filteredData.length);

    if (filteredData.length === 0) {
        if (areaGravesTable) {
            areaGravesTable.setData([]);
        }
        
        // ⭐⭐⭐ הודעה מותאמת לחלקה ריקה!
        if (areaGravesFilterPlotId && areaGravesFilterPlotName) {
            // נכנסנו לחלקה ספציפית ואין אחוזות קבר
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🏘️</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין אחוזות קבר בחלקה ${areaGravesFilterPlotName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                החלקה עדיין לא מכילה אחוזות קבר. תוכל להוסיף אחוזת קבר חדשה
                            </div>
                            <button 
                                onclick="if(typeof FormHandler !== 'undefined' && FormHandler.openForm) { FormHandler.openForm('areaGrave', '${areaGravesFilterPlotId}', null); } else { alert('FormHandler לא זמין'); }" 
                                style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%); 
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
                                ➕ הוסף אחוזת קבר ראשונה
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // חיפוש כללי שלא מצא תוצאות
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
        }
        return;
    }
    
    // ⭐ בדוק אם ה-DOM של TableManager קיים
    const tableWrapperExists = document.querySelector('.table-wrapper[data-table-manager]');
    
    // ⭐ אם המשתנה קיים אבל ה-DOM נמחק - אפס את המשתנה!
    if (!tableWrapperExists && areaGravesTable) {
        console.log('⚠️ TableManager DOM missing, resetting variable');
        areaGravesTable = null;
        window.areaGravesTable = null;
    }
    
    // עכשיו בדוק אם צריך לבנות מחדש
    if (!areaGravesTable || !tableWrapperExists) {
        console.log('🆕 Creating new TableManager');
        initAreaGravesTable(filteredData, totalItems, signal);
    } else {
        console.log('♻️ Updating existing TableManager');
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

// // ===================================================================
// // הפנייה לפונקציות גלובליות
// // ===================================================================

// function checkAreaGravesScrollStatus() {
//     checkEntityScrollStatus(areaGravesTable, 'Area Graves');
// }

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
// הפוך לגלובלי
// ===================================================================

window.areaGravesTable = areaGravesTable;

window.areaGravesFilterPlotId = areaGravesFilterPlotId;

window.areaGravesFilterPlotName = areaGravesFilterPlotName;

window.areaGraveSearch = areaGraveSearch;

// window.loadAreaGravesBrowseData = loadAreaGravesBrowseData;

console.log('✅ area-graves-management.js v1.5.3 - Loaded successfully!');