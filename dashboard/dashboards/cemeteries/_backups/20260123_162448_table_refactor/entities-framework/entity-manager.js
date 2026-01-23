/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-manager.js
 * Version: 1.0.0
 * Updated: 2025-11-20
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: 🆕 המנהל המרכזי - מחליף את כל 6 קבצי הניהול!
 *   ✅ load() - טעינה ראשית מלאה
 *   ✅ refresh() - רענון נתונים
 *   ✅ delete() - מחיקת רשומה
 *   ✅ initSearch() - אתחול חיפוש
 *   ✅ תמיכה מלאה ב-parent context
 *   ✅ ניהול מלא של OperationManager
 *   ✅ אינטגרציה עם כל המודולים
 */


// ===================================================================
// המנהל המרכזי לכל היישויות
// ===================================================================
class EntityManager {

    /**
     * טעינה ראשית מלאה של יישות
     * @param {string} entityType - סוג היישות
     * @param {string|null} parentId - מזהה הורה (אופציונלי)
     * @param {string|null} parentName - שם הורה (אופציונלי)
     * @param {boolean} forceReset - אילוץ איפוס סינון
     * @returns {Promise<void>}
     */
    static async load(entityType, parentId = null, parentName = null, forceReset = false) {
        const config = ENTITY_CONFIG[entityType];
        
        if (!config) {
            console.error(`❌ Unknown entity type: ${entityType}`);
            return;
        }
        
        
        // התחל operation
        const signal = OperationManager.start(entityType);
        
        // איפוס מצב חיפוש
        entityState.setSearchMode(entityType, false, '', []);
        
        // טיפול ב-parent context (עבור יישויות היררכיות)
        this.handleParentContext(entityType, parentId, parentName, forceReset);
        
        // עדכון context גלובלי
        window.currentType = entityType;
        window.currentParentId = parentId;

        // אתחול selectedItems אם לא קיים
        if (!window.selectedItems) {
            window.selectedItems = {};
        }

        // שמור את ההורה ב-selectedItems עבור ה-breadcrumb
        if (parentId && parentName && config.hasParent) {
            const parentType = this.getParentType(config.parentParam);
            if (parentType) {
                window.selectedItems[parentType] = {
                    id: parentId,
                    name: parentName
                };
            }
        }

        if (window.tableRenderer) {
            window.tableRenderer.currentType = entityType;
        }
        
        // ניקוי dashboard
        this.clearDashboard(entityType);
        
        // עדכון UI
        this.updateUI(entityType, parentId, parentName);
        
        // בניית container
        await EntityRenderer.buildContainer(entityType, signal, parentId, parentName);
        
        if (OperationManager.shouldAbort(entityType)) {
            return;
        }
        
        // עדכון מונה טעינות
        const loadCounter = entityState.incrementLoadCounter(entityType);
        
        // השמדת instances קודמים
        this.destroyPreviousInstances(entityType);
        
        // אתחול חיפוש
        await this.initSearch(entityType, signal, parentId);
        
        if (OperationManager.shouldAbort(entityType)) {
            return;
        }
        
        // טעינת נתונים
        const result = await EntityLoader.loadBrowseData(entityType, signal, parentId);
        
        if (result.success && result.data) {
            // רינדור לטבלה
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
                await EntityRenderer.render(entityType, result.data, tableBody, result.pagination, signal);
            }
        }
        
        // טעינת סטטיסטיקות
        await EntityLoader.loadStats(entityType, signal, parentId);
        
    }

    /**
     * טיפול ב-parent context
     * @param {string} entityType - סוג היישות
     * @param {string|null} parentId - מזהה הורה
     * @param {string|null} parentName - שם הורה
     * @param {boolean} forceReset - אילוץ איפוס
     */
    static handleParentContext(entityType, parentId, parentName, forceReset) {
        const config = ENTITY_CONFIG[entityType];
        
        if (!config.hasParent) {
            return; // אין parent context
        }
        
        const state = entityState.getState(entityType);
        
        // לוגיקת סינון
        if (parentId === null && parentName === null && !forceReset) {
            // נקרא מהתפריט ללא פרמטרים - איפוס סינון
            if (state.parentId !== null) {
                entityState.setParentContext(entityType, null, null);
            }
        } else if (forceReset) {
            // איפוס מאולץ
            entityState.setParentContext(entityType, null, null);
        } else {
            // הגדרת parent context חדש
            entityState.setParentContext(entityType, parentId, parentName);
        }
    }

    /**
     * ניקוי dashboard
     * @param {string} entityType - סוג היישות
     */
    static clearDashboard(entityType) {
        if (typeof DashboardCleaner !== 'undefined') {
            DashboardCleaner.clear({ targetLevel: entityType });
        } else if (typeof clearDashboard === 'function') {
            clearDashboard({ targetLevel: entityType });
        }
        
        if (typeof clearAllSidebarSelections === 'function') {
            clearAllSidebarSelections();
        }
    }

    /**
     * מיפוי סוג ישות ל-ID של פריט התפריט בסיידבר
     * @param {string} entityType - סוג היישות
     * @returns {string} - ID של פריט התפריט
     */
    static getMenuItemId(entityType) {
        const menuItemMap = {
            'cemetery': 'cemeteriesItem',
            'block': 'blocksItem',
            'plot': 'plotsItem',
            'areaGrave': 'areaGravesItem',
            'grave': 'gravesItem',
            'customer': 'customersItem',
            'purchase': 'purchasesItem',
            'burial': 'burialsItem',
            'payment': 'paymentsItem',
            'residency': 'residencyItem',
            'country': 'countriesItem',
            'city': 'citiesItem'
        };
        return menuItemMap[entityType] || `${entityType}sItem`;
    }

    /**
     * עדכון UI
     * @param {string} entityType - סוג היישות
     * @param {string|null} parentId - מזהה הורה
     * @param {string|null} parentName - שם הורה
     */
    static updateUI(entityType, parentId = null, parentName = null) {
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);

        // עדכון פריט תפריט אקטיבי
        if (typeof setActiveMenuItem === 'function') {
            const menuItemId = this.getMenuItemId(entityType);
            setActiveMenuItem(menuItemId);
        }

        // עדכון כפתור הוספה
        if (typeof updateAddButtonText === 'function') {
            updateAddButtonText();
        }

        // עדכון breadcrumb
        if (typeof BreadcrumbManager !== 'undefined') {
            // אם אין parent - זו רמה ראשית, השתמש ב-forceType
            if (!parentId || !parentName) {
                BreadcrumbManager.update(null, entityType);
            } else {
                // יש parent - בנה את ההיררכיה
                const breadcrumbData = this.buildBreadcrumbHierarchy(entityType, parentId, parentName);
                BreadcrumbManager.update(breadcrumbData, null);
            }
        }
        
        // עדכון כותרת החלון
        const title = (config.hasParent && parentName)
            ? `${config.plural} - ${parentName}`
            : `ניהול ${config.plural} - מערכת בתי עלמין`;

        document.title = title;

        // עדכון כותרת הרשומה בדף
        this.updateEntityTitle(entityType, parentName, config);
    }

    /**
     * עדכון כותרת הרשומה בדף
     * @param {string} entityType - סוג היישות
     * @param {string|null} parentName - שם הורה
     * @param {Object} config - קונפיג היישות
     */
    static updateEntityTitle(entityType, parentName, config) {
        const titleElement = document.getElementById('entityTitle');
        const subtitleElement = document.getElementById('entitySubtitle');

        if (!titleElement) return;

        // קבל אייקון מההיררכיה
        const icons = {
            'cemetery': '🏛️',
            'block': '📦',
            'plot': '📋',
            'areaGrave': '🏘️',
            'grave': '⚰️',
            'customer': '👤',
            'purchase': '🛒',
            'burial': '⚰️',
            'payment': '💰',
            'residency': '🏠',
            'country': '🌍',
            'city': '🏙️'
        };

        const icon = icons[entityType] || '📋';
        const entityPlural = config?.plural || entityType;

        // כותרת ראשית
        titleElement.innerHTML = `<span class="entity-icon">${icon}</span> ${entityPlural}`;

        // כותרת משנית (אם יש parent)
        if (subtitleElement) {
            if (parentName) {
                subtitleElement.textContent = `של ${parentName}`;
                subtitleElement.style.display = 'inline';
            } else {
                subtitleElement.textContent = '';
                subtitleElement.style.display = 'none';
            }
        }
    }

    /**
     * קבלת סוג ההורה לפי פרמטר
     */
    static getParentType(parentParam) {
        const parentTypes = {
            'cemeteryId': 'cemetery',
            'blockId': 'block',
            'plotId': 'plot',
            'areaGraveId': 'areaGrave',
            'countryId': 'country'
        };
        return parentTypes[parentParam] || null;
    }

    /**
     * בניית היררכיית breadcrumb מלאה
     * @param {string} entityType - סוג היישות הנוכחית
     * @param {string} parentId - מזהה ההורה
     * @param {string} parentName - שם ההורה
     * @returns {Object} נתוני breadcrumb
     */
    static buildBreadcrumbHierarchy(entityType, parentId, parentName) {
        const config = ENTITY_CONFIG[entityType];
        const breadcrumbData = {};

        // קבל את סוג ההורה
        const parentType = this.getParentType(config.parentParam);

        if (parentType) {
            // הוסף את ההורה הישיר
            breadcrumbData[parentType] = {
                id: parentId,
                name: parentName
            };

            // בדוק אם יש היררכיה עמוקה יותר ב-selectedItems
            if (window.selectedItems) {
                const hierarchyOrder = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
                const parentIndex = hierarchyOrder.indexOf(parentType);

                // הוסף את כל ההורים שמעל
                for (let i = 0; i < parentIndex; i++) {
                    const ancestorType = hierarchyOrder[i];
                    if (window.selectedItems[ancestorType]) {
                        breadcrumbData[ancestorType] = {
                            id: window.selectedItems[ancestorType].id,
                            name: window.selectedItems[ancestorType].name
                        };
                    }
                }
            }
        }

        return breadcrumbData;
    }

    /**
     * השמדת instances קודמים
     * @param {string} entityType - סוג היישות
     */
    static destroyPreviousInstances(entityType) {
        const state = entityState.getState(entityType);
        
        // השמד חיפוש קודם
        if (state.searchInstance && typeof state.searchInstance.destroy === 'function') {
            state.searchInstance.destroy();
            entityState.setSearchInstance(entityType, null);
        }
        
        // איפוס טבלה קודמת
        if (state.tableInstance) {
            entityState.setTableInstance(entityType, null);
        }
    }

    /**
     * אתחול UniversalSearch
     * @param {string} entityType - סוג היישות
     * @param {AbortSignal} signal - signal לביטול
     * @param {string|null} parentId - מזהה הורה
     * @returns {Promise<Object>} instance של UniversalSearch
     */
    static async initSearch(entityType, signal = null, parentId = null) {
        const config = ENTITY_CONFIG[entityType];
        
        // בדוק אם initUniversalSearch קיים
        if (typeof window.initUniversalSearch === 'undefined') {
            return null;
        }
        
        
        // ✅ הכן קונפיגורציה במבנה הנכון (כמו בקבצים הישנים!)
        const searchConfig = {
            entityType: entityType,
            apiEndpoint: config.apiEndpoint,  // ✅ ישירות, לא בתוך dataSource!
            
            searchableFields: config.searchableFields || [],
            
            displayColumns: config.columns
                .filter(col => col.type !== 'actions')
                .map(col => ({
                    key: col.field,
                    label: col.label
                })),
            
            searchContainerSelector: `#${entityType}SearchSection`,
            resultsContainerSelector: '#tableBody',
            
            // הגדרות pagination
            apiLimit: config.defaultLimit || 200,
            showPagination: false,
            
            // ✅ renderFunction - חיבור לרינדור שלנו
            renderFunction: async (data, container, pagination, signal) => {
                
                // עדכון state
                entityState.setSearchMode(entityType, true, '', data);
                entityState.setState(entityType, {
                    currentData: data
                });
                
                // רינדור דרך EntityRenderer
                await EntityRenderer.render(entityType, data, container, pagination, signal);
            },
            
            // ✅ callbacks
            callbacks: {
                onSearch: (query, filters) => {
                    entityState.setSearchMode(entityType, true, query, []);
                },
                
                onDataLoaded: (response) => {
                    
                    // עדכון מונה
                    const state = entityState.getState(entityType);
                    if (state.tableInstance && response.pagination) {
                        state.tableInstance.updateTotalItems(response.pagination.total);
                    }
                },
                
                onClear: async () => {
                    
                    // איפוס מצב חיפוש
                    entityState.setSearchMode(entityType, false, '', []);
                    
                    // טעינה מחדש של browse data
                    const result = await EntityLoader.loadBrowseData(entityType, signal, parentId);
                    
                    if (result.success && result.data) {
                        const tableBody = document.getElementById('tableBody');
                        if (tableBody) {
                            await EntityRenderer.render(entityType, result.data, tableBody, result.pagination, signal);
                        }
                    }
                }
            }
        };
        
        // הוסף parent param אם נדרש
        if (parentId && config.parentParam) {
            searchConfig.apiParams = {
                [config.parentParam]: parentId
            };
            searchConfig.additionalParams = {  // ✅ שם נכון!
                [config.parentParam]: parentId
            };
        }
        
        // ✅ יצירת instance
        const searchInstance = await window.initUniversalSearch(searchConfig);

        // שמירה ב-state
        entityState.setSearchInstance(entityType, searchInstance);

        // ✅ טעינת מצב מכווץ שמור (למסכים גדולים)
        if (typeof UniversalSearch.loadSearchSectionState === 'function') {
            // המתן קצת כדי שה-DOM יסתיים
            setTimeout(() => {
                UniversalSearch.loadSearchSectionState(entityType);
            }, 100);
        }

        return searchInstance;
    }

    /**
     * רענון נתונים
     * @param {string} entityType - סוג היישות
     * @returns {Promise<void>}
     */
    static async refresh(entityType) {
        
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        
        // קבל parent context אם יש
        const parentId = config.hasParent ? state.parentId : null;
        
        // בדוק אם יש instance של חיפוש
        if (state.searchInstance && typeof state.searchInstance.refresh === 'function') {
            state.searchInstance.refresh();
            return;
        }
        
        // אחרת - טען מחדש
        await EntityLoader.refresh(entityType, parentId);
    }

    /**
     * מחיקת רשומה
     * @param {string} entityType - סוג היישות
     * @param {string} entityId - מזהה הרשומה
     * @returns {Promise<boolean>} האם המחיקה הצליחה
     */
    static async delete(entityType, entityId) {
        return await EntityLoader.deleteEntity(entityType, entityId);
    }

    /**
     * בדיקת סטטוס scroll
     * @param {string} entityType - סוג היישות
     */
    static checkScrollStatus(entityType) {
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        
        if (!state.tableInstance) {
            return;
        }
        
        const total = state.tableInstance.getFilteredData().length;
        const displayed = state.tableInstance.getDisplayedData().length;
        const remaining = total - displayed;
        
        
        if (remaining > 0) {
        } else {
        }
    }

    /**
     * טעינת עוד נתונים (Infinite Scroll)
     * @param {string} entityType - סוג היישות
     * @returns {Promise<boolean>}
     */
    static async appendMore(entityType) {
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        const parentId = config.hasParent ? state.parentId : null;
        
        return await EntityLoader.appendMoreData(entityType, parentId);
    }

    /**
     * קבלת state נוכחי
     * @param {string} entityType - סוג היישות
     * @returns {Object} state של היישות
     */
    static getState(entityType) {
        return entityState.getState(entityType);
    }

    /**
     * דאמפ של כל המערכת (לדיבאג)
     */
    static dumpSystemState() {
        
        const entityTypes = Object.keys(ENTITY_CONFIG);
        
        entityTypes.forEach(entityType => {
            const config = ENTITY_CONFIG[entityType];
            const state = entityState.getState(entityType);
            
            
            if (config.hasParent) {
            }
            
        });
        
    }
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.EntityManager = EntityManager;

// ===================================================================
// יצירת פונקציות wrapper ל-backward compatibility
// ===================================================================

// Cemetery wrappers
window.loadCemeteries = () => EntityManager.load('cemetery');
window.loadCemeteriesBrowseData = (signal) => EntityLoader.loadBrowseData('cemetery', signal);
window.appendMoreCemeteries = () => EntityManager.appendMore('cemetery');
window.deleteCemetery = (id) => EntityManager.delete('cemetery', id);
window.cemeteriesRefreshData = () => EntityManager.refresh('cemetery');
window.checkCemeteriesScrollStatus = () => EntityManager.checkScrollStatus('cemetery');

// Block wrappers
window.loadBlocks = (cemeteryId, cemeteryName, forceReset) => EntityManager.load('block', cemeteryId, cemeteryName, forceReset);
window.loadBlocksBrowseData = (cemeteryId, signal) => EntityLoader.loadBrowseData('block', signal, cemeteryId);
window.appendMoreBlocks = () => EntityManager.appendMore('block');
window.deleteBlock = (id) => EntityManager.delete('block', id);
window.blocksRefreshData = () => EntityManager.refresh('block');
window.checkBlocksScrollStatus = () => EntityManager.checkScrollStatus('block');

// פונקציות טעינה ראשיות
window.loadCustomers = () => EntityManager.load('customer');
window.loadPurchases = () => EntityManager.load('purchase');
window.loadBurials = () => EntityManager.load('burial');
window.loadPayments = () => EntityManager.load('payment');
window.loadPlots = (blockId, blockName, forceReset) => EntityManager.load('plot', blockId, blockName, forceReset);
window.loadAreaGraves = (plotId, plotName, forceReset) => EntityManager.load('areaGrave', plotId, plotName, forceReset);
window.loadGraves = (areaGraveId, areaGraveName, forceReset) => EntityManager.load('grave', areaGraveId, areaGraveName, forceReset);

// פונקציות Browse Data
window.loadCustomersBrowseData = (signal) => EntityLoader.loadBrowseData('customer', signal);
window.loadPurchasesBrowseData = (signal) => EntityLoader.loadBrowseData('purchase', signal);
window.loadBurialsBrowseData = (signal) => EntityLoader.loadBrowseData('burial', signal);
window.loadPaymentsBrowseData = (signal) => EntityLoader.loadBrowseData('payment', signal);
window.loadPlotsBrowseData = (blockId, signal) => EntityLoader.loadBrowseData('plot', signal, blockId);
window.loadAreaGravesBrowseData = (plotId, signal) => EntityLoader.loadBrowseData('areaGrave', signal, plotId);
window.loadGravesBrowseData = (areaGraveId, signal) => EntityLoader.loadBrowseData('grave', signal, areaGraveId);

// פונקציות Append More
window.appendMoreCustomers = () => EntityManager.appendMore('customer');
window.appendMorePurchases = () => EntityManager.appendMore('purchase');
window.appendMoreBurials = () => EntityManager.appendMore('burial');
window.appendMorePayments = () => EntityManager.appendMore('payment');
window.appendMorePlots = () => EntityManager.appendMore('plot');
window.appendMoreAreaGraves = () => EntityManager.appendMore('areaGrave');
window.appendMoreGraves = () => EntityManager.appendMore('grave');

// פונקציות מחיקה
window.deleteCustomer = (id) => EntityManager.delete('customer', id);
window.deletePurchase = (id) => EntityManager.delete('purchase', id);
window.deleteBurial = (id) => EntityManager.delete('burial', id);
window.deletePayment = (id) => EntityManager.delete('payment', id);
window.deletePlot = (id) => EntityManager.delete('plot', id);
window.deleteAreaGrave = (id) => EntityManager.delete('areaGrave', id);
window.deleteGrave = (id) => EntityManager.delete('grave', id);

// פונקציות רענון
window.customersRefreshData = () => EntityManager.refresh('customer');
window.purchasesRefreshData = () => EntityManager.refresh('purchase');
window.burialsRefreshData = () => EntityManager.refresh('burial');
window.paymentsRefreshData = () => EntityManager.refresh('payment');
window.plotsRefreshData = () => EntityManager.refresh('plot');
window.refreshAreaGravesData = () => EntityManager.refresh('areaGrave');
window.refreshGravesData = () => EntityManager.refresh('grave');

// פונקציות סטטוס scroll
window.checkCustomersScrollStatus = () => EntityManager.checkScrollStatus('customer');
window.checkPurchasesScrollStatus = () => EntityManager.checkScrollStatus('purchase');
window.checkBurialsScrollStatus = () => EntityManager.checkScrollStatus('burial');
window.checkPaymentsScrollStatus = () => EntityManager.checkScrollStatus('payment');

// Residency wrappers
window.loadResidencies = () => EntityManager.load('residency');
window.loadResidenciesBrowseData = (signal) => EntityLoader.loadBrowseData('residency', signal);
window.appendMoreResidencies = () => EntityManager.appendMore('residency');
window.deleteResidency = (id) => EntityManager.delete('residency', id);
window.residenciesRefreshData = () => EntityManager.refresh('residency');
window.checkResidenciesScrollStatus = () => EntityManager.checkScrollStatus('residency');

// Country wrappers
window.loadCountries = () => EntityManager.load('country');
window.loadCountriesBrowseData = (signal) => EntityLoader.loadBrowseData('country', signal);
window.appendMoreCountries = () => EntityManager.appendMore('country');
window.deleteCountry = (id) => EntityManager.delete('country', id);
window.countriesRefreshData = () => EntityManager.refresh('country');
window.checkCountriesScrollStatus = () => EntityManager.checkScrollStatus('country');

// City wrappers
window.loadCities = (countryId, countryName, forceReset) => EntityManager.load('city', countryId, countryName, forceReset);
window.loadCitiesBrowseData = (countryId, signal) => EntityLoader.loadBrowseData('city', signal, countryId);
window.appendMoreCities = () => EntityManager.appendMore('city');
window.deleteCity = (id) => EntityManager.delete('city', id);
window.citiesRefreshData = () => EntityManager.refresh('city');
window.checkCitiesScrollStatus = () => EntityManager.checkScrollStatus('city');
window.checkPlotsScrollStatus = () => EntityManager.checkScrollStatus('plot');
window.checkAreaGravesScrollStatus = () => EntityManager.checkScrollStatus('areaGrave');
window.checkGravesScrollStatus = () => EntityManager.checkScrollStatus('grave');

