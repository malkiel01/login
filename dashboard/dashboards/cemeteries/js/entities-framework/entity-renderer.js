/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-renderer.js
 * Version: 1.1.0
 * Updated: 2026-01-24
 * Author: Malkiel
 * Change Summary:
 * - v1.1.0: 🆕 מעבר ל-TableManager v3.0.0
 *   ✅ הוספת entityType לכל הטבלאות
 *   ✅ הפעלת userPreferences - העדפות משתמש לכל entity
 *   ✅ תמיכה בשמירת הגדרות טבלה (רוחב עמודות, מיון וכו')
 * - v1.0.0: 🆕 מנהל רינדור גנרי לטבלאות
 *   ✅ render() - רינדור כללי לכל היישויות
 *   ✅ buildContainer() - בניית HTML container
 *   ✅ initTable() - אתחול TableManager
 *   ✅ renderEmptyState() - הצגת מצב ריק
 *   ✅ תמיכה במצב חיפוש ו-Browse
 */


// ===================================================================
// מנהל רינדור גנרי
// ===================================================================
class EntityRenderer {

    /**
     * רינדור נתונים לטבלה
     * @param {string} entityType - סוג היישות
     * @param {Array} data - המידע לרינדור
     * @param {HTMLElement} container - הקונטיינר (tbody)
     * @param {Object} pagination - מידע pagination
     * @param {AbortSignal} signal - signal לביטול
     * @returns {Promise<void>}
     */
    static async render(entityType, data, container, pagination = null, signal = null) {
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        
        
        // מצב חיפוש - רינדור פשוט
        if (state.isSearchMode && state.currentQuery) {
            
            if (data.length === 0) {
                this.renderEmptyState(container, config, 'search');
                return;
            }
            
            const totalItems = data.length;
            await this.initTable(entityType, data, totalItems, signal);
            return;
        }
        
        // מצב Browse רגיל
        const totalItems = pagination?.total || data.length;
        
        // בדיקה אם אין נתונים
        if (data.length === 0) {
            if (state.tableInstance) {
                state.tableInstance.setData([]);
            }
            this.renderEmptyState(container, config, 'browse', state);
            return;
        }
        
        // בדיקה אם ה-DOM של TableManager קיים
        const tableWrapperExists = document.querySelector('.table-wrapper[data-table-manager]');
        
        // אם המשתנה קיים אבל ה-DOM נמחק - אפס!
        if (!tableWrapperExists && state.tableInstance) {
            entityState.setTableInstance(entityType, null);
        }
        
        // אתחול או עדכון טבלה
        if (!state.tableInstance || !tableWrapperExists) {
            await this.initTable(entityType, data, totalItems, signal);
        } else {
            if (state.tableInstance.config) {
                state.tableInstance.config.totalItems = totalItems;
            }
            state.tableInstance.setData(data);
        }
        
        // עדכון מונה ב-UniversalSearch
        if (state.searchInstance) {
            state.searchInstance.state.totalResults = totalItems;
            state.searchInstance.updateCounter();
        }
    }

    /**
     * בניית HTML container ליישות
     * @param {string} entityType - סוג היישות
     * @param {AbortSignal} signal - signal לביטול
     * @param {string|null} parentId - מזהה הורה
     * @param {string|null} parentName - שם הורה
     * @returns {Promise<void>}
     */
    static async buildContainer(entityType, signal = null, parentId = null, parentName = null) {
        const config = ENTITY_CONFIG[entityType];

        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;

        // מצא או צור main-container
        let mainContainer = document.querySelector('.main-container');

        if (!mainContainer) {
            mainContainer = document.createElement('div');
            mainContainer.className = 'main-container';

            // הכנס אחרי entity-title-container או לפני table-container הקיים
            const entityTitleContainer = mainContent.querySelector('.entity-title-container');
            const existingTableContainer = mainContent.querySelector('.table-container');

            if (entityTitleContainer) {
                entityTitleContainer.insertAdjacentElement('afterend', mainContainer);
            } else if (existingTableContainer) {
                existingTableContainer.insertAdjacentElement('beforebegin', mainContainer);
            } else {
                mainContent.appendChild(mainContainer);
            }
        }

        // הסר table-container ישן אם קיים מחוץ ל-main-container
        const oldTableContainer = mainContent.querySelector(':scope > .table-container');
        if (oldTableContainer) {
            oldTableContainer.remove();
        }

        // בנה את ה-HTML
        mainContainer.innerHTML = `
            <div id="${entityType}SearchSection" class="search-section"></div>

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
                                    <span class="visually-hidden">טוען ${config.plural}...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;

    }

   /**
     * אתחול TableManager
     * @param {string} entityType - סוג היישות
     * @param {Array} data - נתוני הטבלה
     * @param {number} totalItems - סה"כ רשומות
     * @param {AbortSignal} signal - signal לביטול
     * @returns {Promise<Object>} instance של TableManager
     */
    static async initTable(entityType, data, totalItems, signal = null) {
        const config = ENTITY_CONFIG[entityType];


        // המתן ל-DOM
        const tableBody = await this.waitForElement('#tableBody', 5000);
        if (!tableBody) {
            console.error('❌ tableBody not found after 5 seconds');
            return null;
        }

        // בדיקה אם יש הרשאות לפעולות
        const module = window.getModuleForType ? window.getModuleForType(entityType) : entityType;
        const hasEditPermission = window.hasPermission ? window.hasPermission(module, 'edit') : true;
        const hasDeletePermission = window.hasPermission ? window.hasPermission(module, 'delete') : true;
        const hasAnyActionPermission = hasEditPermission || hasDeletePermission;

        // סינון עמודת פעולות אם אין הרשאות
        const filteredColumns = hasAnyActionPermission
            ? config.columns
            : config.columns.filter(col => col.type !== 'actions');

        // הגדרת עמודות
        const columns = filteredColumns.map(col => {
            const columnDef = {
                field: col.field,
                label: col.label,
                width: col.width
            };
            
            // ✅ טיפול בסוגי עמודות - עם הפרמטרים הנכונים!
            if (col.type === 'status') {
                columnDef.render = (row) => {
                    const value = row[col.field];
                    return formatEntityStatus(entityType, value);
                };
            } else if (col.type === 'currency') {
                columnDef.render = (row) => {
                    const value = row[col.field];
                    return formatCurrency(value);
                };
            } else if (col.type === 'date') {
                columnDef.render = (row) => {
                    const value = row[col.field];
                    return formatDate(value);
                };
            } else if (col.type === 'enum') {
                columnDef.render = (row) => {
                    const value = row[col.field];
                    // פונקציות עזר ספציפיות
                    if (col.field === 'purchaseType') {
                        return this.formatPurchaseType(value);
                    } else if (col.field === 'graveType') {
                        return this.getGraveTypeName(value);
                    }
                    return value || '-';
                };
            } else if (col.type === 'link') {
                columnDef.render = (row) => {
                    const idField = this.getIdField(entityType);
                    const nameField = config.nameField || col.field;
                    const entityId = row[idField];
                    const entityName = row[nameField] || row[col.field];
                    
                    // escape של תווים מיוחדים
                    const escapedName = (entityName || '').replace(/'/g, "\\'");
                    
                    return `<a href="#" 
                            onclick="EntityRenderer.handleDoubleClick('${entityType}', '${entityId}', '${escapedName}'); return false;" 
                            style="color: #2563eb; text-decoration: none; font-weight: 500;">
                        ${entityName || '-'}
                    </a>`;
                }    
            } else if (col.type === 'badge') {
                columnDef.render = (row) => {
                    const value = row[col.field] || 0;
                    return `<span style="background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">${value}</span>`;
                };
            } else if (col.type === 'coordinates') {
                columnDef.render = (row) => {
                    const coords = row[col.field] || '-';
                    return `<span style="font-family: monospace; font-size: 12px;">${coords}</span>`;
                };
            } else if (col.type === 'graveType') {
                columnDef.render = (row) => {
                    const value = row[col.field];
                    const typeName = this.getGraveTypeName(value);
                    return `<span style="background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">${typeName}</span>`;
                };
            } else if (col.type === 'actions') {
                // ✅ תיקון קריטי: actions מקבל את השורה המלאה!
                // ✅ v1.2.0: בדיקת הרשאות לפני הצגת כפתורים
                columnDef.render = (row) => {
                    const idField = this.getIdField(entityType);
                    const entityId = row[idField];

                    // בדיקת הרשאות - edit/delete
                    const module = window.getModuleForType ? window.getModuleForType(entityType) : entityType;
                    const hasEditPermission = window.hasPermission ? window.hasPermission(module, 'edit') : true;
                    const hasDeletePermission = window.hasPermission ? window.hasPermission(module, 'delete') : true;

                    let buttons = '';

                    if (hasEditPermission) {
                        buttons += `
                            <button onclick="if(typeof window.tableRenderer !== 'undefined' && window.tableRenderer.editItem) { window.tableRenderer.editItem('${entityId}'); }"
                                    class="btn-icon btn-icon-edit"
                                    title="ערוך"
                                    style="background: transparent; border: none; cursor: pointer; padding: 6px; font-size: 18px; color: #3b82f6; transition: transform 0.15s;">
                                ✏️
                            </button>
                        `;
                    }

                    if (hasDeletePermission) {
                        buttons += `
                            <button onclick="EntityLoader.deleteEntity('${entityType}', '${entityId}')"
                                    class="btn-icon btn-icon-delete"
                                    title="מחק"
                                    style="background: transparent; border: none; cursor: pointer; padding: 6px; font-size: 18px; color: #ef4444; transition: transform 0.15s;">
                                🗑️
                            </button>
                        `;
                    }

                    // אם אין הרשאות כלל - הצג מקף
                    if (!buttons) {
                        return '<span style="color: #9ca3af;">-</span>';
                    }

                    return `
                        <div class="action-buttons" style="display: flex; gap: 8px; justify-content: center;">
                            ${buttons}
                        </div>
                    `;
                };
            }
            
            return columnDef;
        });
        
        // יצירת TableManager v3.0.0 - עם תמיכה בהעדפות משתמש
        const tableManager = new TableManager({
            tableSelector: '#mainTable',
            data: data,
            columns: columns,

            // === חדש v3.0.0 - סוג entity לשמירת העדפות ===
            entityType: entityType,

            // הגדרות Infinite Scroll
            totalItems: totalItems,
            scrollLoadBatch: 100,
            itemsPerPage: 999999,
            showPagination: false,

            // גובה טבלה
            tableHeight: 'calc(100vh - 650px)',
            tableMinHeight: '500px',

            // === חדש v3.0.0 - העדפות משתמש ===
            userPreferences: {
                enabled: true,
                storageKey: `table_${entityType}`
            },

            // callbacks
            onRowDoubleClick: (row) => {
                this.handleDoubleClick(entityType, row);
            },

            onLoadMore: async () => {
                const state = entityState.getState(entityType);
                const parentId = config.hasParent ? state.parentId : null;
                return await EntityLoader.appendMoreData(entityType, parentId);
            },

            onSort: (field, order) => {
                if (typeof showToast === 'function') {
                    showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
                }
            },

            onFilter: (filters) => {
                const state = entityState.getState(entityType);
                if (state.tableInstance) {
                    const count = state.tableInstance.getFilteredData().length;
                    if (typeof showToast === 'function') {
                        showToast(`נמצאו ${count} תוצאות`, 'info');
                    }
                }
            }
        });
        
        // שמור את ה-instance
        entityState.setTableInstance(entityType, tableManager);
        
        return tableManager;
    }

    /**
     * רינדור מצב ריק
     * @param {HTMLElement} container - הקונטיינר
     * @param {Object} config - קונפיגורציה
     * @param {string} mode - 'search' או 'browse'
     * @param {Object} state - state של היישות
     */
    static renderEmptyState(container, config, mode = 'browse', state = null) {
        if (mode === 'search') {
            // אין תוצאות חיפוש
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
        } else if (config.hasParent && state && state.parentId && state.parentName) {
            // יישות עם הורה - אין נתונים בהורה הספציפי
            const parentSingular = this.getParentSingular(config);
            container.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align: center; padding: 60px;">
                        <div style="color: #6b7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🏘️</div>
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 12px; color: #374151;">
                                אין ${config.plural} ${parentSingular} ${state.parentName}
                            </div>
                            <div style="font-size: 14px; margin-bottom: 24px; color: #6b7280;">
                                ${parentSingular} עדיין לא ${config.hasParent ? 'מכיל' : 'מכילה'} ${config.plural}. תוכל להוסיף ${config.singular} ${config.hasParent ? 'חדש' : 'חדשה'}
                            </div>
                            <button
                                onclick="(function() { const type='${config.entityType || Object.keys(ENTITY_CONFIG).find(k => ENTITY_CONFIG[k] === config)}'; const parentId='${state.parentId}'; const addFuncs = { cemetery: openAddCemetery, block: openAddBlock, plot: openAddPlot, areaGrave: openAddAreaGrave, grave: openAddGrave, burial: openAddBurial, purchase: openAddPurchase, city: openAddCity, country: openAddCountry, residency: openAddResidency, payment: openAddPayment }; if(addFuncs[type]) { addFuncs[type](parentId); } else { console.warn('No popup function for:', type); } })()"
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
                                ➕ הוסף ${config.singular} ${config.hasParent ? 'ראשון' : 'ראשונה'}
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // אין נתונים כלליים
            container.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align: center; padding: 60px;">
                        <div style="color: #9ca3af;">
                            <div style="font-size: 48px; margin-bottom: 16px;">📂</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">אין ${config.plural}</div>
                            <div>לא נמצאו ${config.plural} במערכת</div>
                        </div>
                    </td>
                </tr>
            `;
        }
    }

    // /**
    //  * טיפול בדאבל-קליק על שורה
    //  * @param {string} entityType - סוג היישות
    //  * @param {Object} row - נתוני השורה
    //  */
    // static handleDoubleClick(entityType, row) {
    //     const config = ENTITY_CONFIG[entityType];
    //     const idField = this.getIdField(entityType);
    //     const entityId = row[idField];
        
    //     console.log(`🖱️ Double-click on ${entityType}:`, entityId);
        
    //     // קריאה לפונקציית handler ספציפית אם קיימת
    //     const handlerName = `handle${this.capitalize(entityType)}DoubleClick`;
    //     if (typeof window[handlerName] === 'function') {
    //         window[handlerName](entityId, row.name || row[`${entityType}Name`]);
    //     } else {
    //         // ברירת מחדל - פתיחת כרטיס
    //         this.openCard(entityType, entityId);
    //     }
    // }
    /**
     * טיפול בדאבל-קליק על שורה
     * @param {string} entityType - סוג היישות
     * @param {Object|string} rowOrId - נתוני השורה (מ-TableManager) או מזהה (מ-link onclick)
     * @param {string|null} entityName - שם היישות (רק כאשר נקרא מ-link onclick)
     */
    static handleDoubleClick(entityType, rowOrId, entityName = null) {
        let row;

        // בדיקה אם קיבלנו string (מ-link onclick) או object (מ-TableManager)
        if (typeof rowOrId === 'string') {
            // נקרא מ-link onclick עם (entityType, entityId, entityName)
            const config = ENTITY_CONFIG[entityType];
            const idField = this.getIdField(entityType);
            const nameField = config?.nameField || `${entityType}NameHe`;

            // בניית אובייקט row מלאכותי עם כל השדות האפשריים
            row = {
                [idField]: rowOrId,
                unicId: rowOrId,
                id: rowOrId,
                [nameField]: entityName,
                [`${entityType}Name`]: entityName,
                [`${entityType}NameHe`]: entityName,
                name: entityName
            };
        } else {
            // נקרא מ-TableManager עם אובייקט מלא
            row = rowOrId;
        }

        const idField = this.getIdField(entityType);
        const entityId = row[idField] || row.id || row.unicId;

        // מיפוי לשמות הפונקציות הספציפיות
        const handlers = {
            'cemetery': 'handleCemeteryDoubleClick',
            'block': 'handleBlockDoubleClick',
            'plot': 'handlePlotDoubleClick',
            'areaGrave': 'handleAreaGraveDoubleClick',
            'grave': 'handleGraveDoubleClick',
            'customer': 'handleCustomerDoubleClick',
            'purchase': 'handlePurchaseDoubleClick',
            'burial': 'handleBurialDoubleClick',
            'payment': 'handlePaymentDoubleClick',
            'residency': 'handleResidencyDoubleClick',
            'country': 'handleCountryDoubleClick',
            'city': 'handleCityDoubleClick'
        };

        const handlerName = handlers[entityType];

        if (handlerName && typeof window[handlerName] === 'function') {
            // שלח את כל האובייקט כדי שה-handler יוכל לחלץ מה שהוא צריך
            window[handlerName](row);
        } else {
            // fallback - פתיחת כרטיס
            this.openCard(entityType, entityId);
        }
    }


    /**
     * פתיחת כרטיס של יישות
     * @param {string} entityType - סוג היישות
     * @param {string} entityId - מזהה היישות
     */
    static async openCard(entityType, entityId) {
        const cardFunctionName = `create${this.capitalize(entityType)}Card`;
        
        if (typeof window[cardFunctionName] === 'function') {
            const cardHtml = await window[cardFunctionName](entityId);
            if (cardHtml && typeof displayHierarchyCard === 'function') {
                displayHierarchyCard(cardHtml);
            }
        } else {
        }
    }

    // ===================================================================
    // פונקציות עזר
    // ===================================================================

    /**
     * המתנה לאלמנט DOM
     */
    static waitForElement(selector, timeout = 5000) {
        return new Promise((resolve) => {
            const element = document.querySelector(selector);
            if (element) {
                resolve(element);
                return;
            }
            
            const observer = new MutationObserver(() => {
                const element = document.querySelector(selector);
                if (element) {
                    observer.disconnect();
                    resolve(element);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            setTimeout(() => {
                observer.disconnect();
                resolve(null);
            }, timeout);
        });
    }

    /**
     * קבלת שדה ID ליישות
     */
    static getIdField2(entityType) {
        const idFields = {
            customer: 'customerId',
            purchase: 'purchaseId',
            burial: 'burialId',
            plot: 'plotId',
            areaGrave: 'areaGraveId',
            grave: 'graveId'
        };
        return idFields[entityType] || `${entityType}Id`;
    }
    /**
     * קבלת שדה ID ליישות
     */
    static getIdField(entityType) {
        const config = ENTITY_CONFIG[entityType];
        
        // אם מוגדר במפורש בקונפיג - השתמש בו
        if (config && config.idField) {
            return config.idField;
        }
        
        // ברירת מחדל
        return 'unicId';
    }

    /**
     * קבלת שם יחיד של ההורה
     */
    static getParentSingular(config) {
        if (!config.hasParent) return '';
        
        const parentNames = {
            blockId: 'בגוש',
            plotId: 'בחלקה',
            areaGraveId: 'באחוזת הקבר'
        };
        return parentNames[config.parentParam] || '';
    }

    /**
     * Capitalize ראשון
     */
    static capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * פורמט סוג רכישה
     */
    static formatPurchaseType(type) {
        const types = {
            'new': 'רכישה חדשה',
            'transfer': 'העברת בעלות',
            'renewal': 'חידוש'
        };
        return types[type] || '-';
    }

    /**
     * פורמט סוג קבר
     */
    static getGraveTypeName(type) {
        const types = {
            1: 'שדה',
            2: 'רוויה',
            3: 'סנהדרין'
        };
        return types[type] || 'לא מוגדר';
    }
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.EntityRenderer = EntityRenderer;

