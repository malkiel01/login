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

console.log('🚀 entity-manager.js v1.0.0 - Loading...');

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
        
        console.log('══════════════════════════════════════════════════');
        console.log(`🚀 EntityManager.load('${entityType}') STARTED`);
        console.log('══════════════════════════════════════════════════');
        
        // התחל operation
        const signal = OperationManager.start(entityType);
        console.log('✅ Step 1: OperationManager started');
        
        // איפוס מצב חיפוש
        entityState.setSearchMode(entityType, false, '', []);
        console.log('✅ Step 2: Search state reset');
        
        // טיפול ב-parent context (עבור יישויות היררכיות)
        this.handleParentContext(entityType, parentId, parentName, forceReset);
        console.log('✅ Step 3: Parent context handled');
        
        // עדכון context גלובלי
        window.currentType = entityType;
        window.currentParentId = parentId;
        
        if (window.tableRenderer) {
            window.tableRenderer.currentType = entityType;
        }
        console.log('✅ Step 4: Global context updated');
        
        // ניקוי dashboard
        this.clearDashboard(entityType);
        console.log('✅ Step 5: Dashboard cleared');
        
        // עדכון UI
        this.updateUI(entityType, parentId, parentName);
        console.log('✅ Step 6: UI updated');
        
        // בניית container
        await EntityRenderer.buildContainer(entityType, signal, parentId, parentName);
        console.log('✅ Step 7: Container built');
        
        if (OperationManager.shouldAbort(entityType)) {
            console.log('⚠️ ABORTED at step 7');
            return;
        }
        
        // עדכון מונה טעינות
        const loadCounter = entityState.incrementLoadCounter(entityType);
        console.log(`✅ Step 8: Load counter = ${loadCounter}`);
        
        // השמדת instances קודמים
        this.destroyPreviousInstances(entityType);
        console.log('✅ Step 9: Previous instances destroyed');
        
        // אתחול חיפוש
        console.log('🆕 Creating fresh search instance...');
        await this.initSearch(entityType, signal, parentId);
        console.log('✅ Step 10: UniversalSearch initialized');
        
        if (OperationManager.shouldAbort(entityType)) {
            console.log('⚠️ ABORTED at step 10');
            return;
        }
        
        // טעינת נתונים
        console.log('📥 Loading browse data...');
        const result = await EntityLoader.loadBrowseData(entityType, signal, parentId);
        console.log('✅ Step 11: Browse data loaded');
        
        if (result.success && result.data) {
            // רינדור לטבלה
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
                await EntityRenderer.render(entityType, result.data, tableBody, result.pagination, signal);
            }
        }
        
        // טעינת סטטיסטיקות
        console.log('📊 Loading stats...');
        await EntityLoader.loadStats(entityType, signal, parentId);
        console.log('✅ Step 12: Stats loaded');
        
        console.log('══════════════════════════════════════════════════');
        console.log(`✅ EntityManager.load('${entityType}') COMPLETED`);
        console.log('══════════════════════════════════════════════════');
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
                console.log('🔄 Resetting filter - called from menu without params');
                entityState.setParentContext(entityType, null, null);
            }
        } else if (forceReset) {
            // איפוס מאולץ
            console.log('🔄 Force reset filter');
            entityState.setParentContext(entityType, null, null);
        } else {
            // הגדרת parent context חדש
            console.log('🔄 Setting filter:', { parentId, parentName });
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
            const menuItemId = `${entityType}sItem`;
            setActiveMenuItem(menuItemId);
        }
        
        // עדכון כפתור הוספה
        if (typeof updateAddButtonText === 'function') {
            updateAddButtonText();
        }
        
        // עדכון breadcrumb
        if (typeof updateBreadcrumb === 'function') {
            const breadcrumbData = {};
            
            // הוספת parent אם יש
            if (config.hasParent && parentId && parentName) {
                const parentType = this.getParentType(config.parentParam);
                if (parentType) {
                    breadcrumbData[parentType] = { id: parentId, name: parentName };
                }
            }
            
            // הוספת היישות הנוכחית
            const displayName = (config.hasParent && parentName) 
                ? `${config.plural} של ${parentName}`
                : config.plural;
            
            breadcrumbData[entityType] = { name: displayName };
            
            updateBreadcrumb(breadcrumbData);
        }
        
        // עדכון כותרת החלון
        const title = (config.hasParent && parentName)
            ? `${config.plural} - ${parentName}`
            : `ניהול ${config.plural} - מערכת בתי עלמין`;
        
        document.title = title;
    }

    /**
     * קבלת סוג ההורה לפי פרמטר
     */
    static getParentType(parentParam) {
        const parentTypes = {
            'blockId': 'block',
            'plotId': 'plot',
            'areaGraveId': 'areaGrave'
        };
        return parentTypes[parentParam] || null;
    }

    /**
     * השמדת instances קודמים
     * @param {string} entityType - סוג היישות
     */
    static destroyPreviousInstances(entityType) {
        const state = entityState.getState(entityType);
        
        // השמד חיפוש קודם
        if (state.searchInstance && typeof state.searchInstance.destroy === 'function') {
            console.log('🗑️ Destroying previous search instance...');
            state.searchInstance.destroy();
            entityState.setSearchInstance(entityType, null);
        }
        
        // איפוס טבלה קודמת
        if (state.tableInstance) {
            console.log('🗑️ Resetting previous table instance...');
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
        
        // בדוק אם UniversalSearch קיים
        if (typeof UniversalSearch === 'undefined') {
            console.warn('⚠️ UniversalSearch not available');
            return null;
        }
        
        // הכן קונפיגורציה
        const searchConfig = {
            entityType: entityType,
            apiEndpoint: config.apiEndpoint,
            action: 'list',
            
            searchableFields: config.searchableFields,
            
            displayColumns: config.columns.filter(col => col.type !== 'actions').map(col => ({
                field: col.field,
                label: col.label
            })),
            
            onSearchStart: (query, mode) => {
                console.log(`🔍 Search started: "${query}" (${mode})`);
                entityState.setSearchMode(entityType, true, query, []);
            },
            
            onSearchComplete: async (results) => {
                console.log(`✅ Search completed: ${results.length} results`);
                
                // עדכון state
                entityState.setSearchMode(entityType, true, '', results);
                entityState.setState(entityType, {
                    currentData: results
                });
                
                // רינדור תוצאות
                const tableBody = document.getElementById('tableBody');
                if (tableBody) {
                    await EntityRenderer.render(entityType, results, tableBody, null, signal);
                }
            },
            
            onSearchClear: async () => {
                console.log('🔄 Search cleared, returning to browse mode');
                
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
        };
        
        // הוסף parent param אם נדרש
        if (parentId && config.parentParam) {
            searchConfig.additionalParams = {
                [config.parentParam]: parentId
            };
        }
        
        // יצירת instance
        const searchInstance = new UniversalSearch(searchConfig);
        
        // שמירה ב-state
        entityState.setSearchInstance(entityType, searchInstance);
        
        return searchInstance;
    }

    /**
     * רענון נתונים
     * @param {string} entityType - סוג היישות
     * @returns {Promise<void>}
     */
    static async refresh(entityType) {
        console.log(`🔄 EntityManager.refresh('${entityType}') called`);
        
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        
        // קבל parent context אם יש
        const parentId = config.hasParent ? state.parentId : null;
        
        // בדוק אם יש instance של חיפוש
        if (state.searchInstance && typeof state.searchInstance.refresh === 'function') {
            console.log('   ✅ Using search instance refresh');
            state.searchInstance.refresh();
            return;
        }
        
        // אחרת - טען מחדש
        console.log('   ✅ Reloading browse data');
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
            console.log(`❌ ${config.plural} table not initialized`);
            return;
        }
        
        const total = state.tableInstance.getFilteredData().length;
        const displayed = state.tableInstance.getDisplayedData().length;
        const remaining = total - displayed;
        
        console.log(`📊 ${config.plural} Scroll Status:`);
        console.log(`   Total items: ${total}`);
        console.log(`   Displayed: ${displayed}`);
        console.log(`   Remaining: ${remaining}`);
        console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
        
        if (remaining > 0) {
            console.log(`   🔽 Scroll down to load more items`);
        } else {
            console.log('   ✅ All items loaded');
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
        console.log('\n╔════════════════════════════════════════════════════════════════');
        console.log('║ 📊 ENTITY MANAGEMENT SYSTEM STATE DUMP');
        console.log('╠════════════════════════════════════════════════════════════════');
        
        const entityTypes = Object.keys(ENTITY_CONFIG);
        
        entityTypes.forEach(entityType => {
            const config = ENTITY_CONFIG[entityType];
            const state = entityState.getState(entityType);
            
            console.log(`║`);
            console.log(`║ 📦 ${config.plural.toUpperCase()}`);
            console.log(`║ ├─ Data: ${state.currentData.length} items loaded`);
            console.log(`║ ├─ Pagination: ${state.currentPage}/${state.totalPages}`);
            console.log(`║ ├─ Search Mode: ${state.isSearchMode ? '🔍 Active' : '📋 Browse'}`);
            console.log(`║ ├─ Table: ${state.tableInstance ? '✅ Initialized' : '❌ Not initialized'}`);
            console.log(`║ ├─ Search: ${state.searchInstance ? '✅ Initialized' : '❌ Not initialized'}`);
            console.log(`║ ├─ Load Counter: ${state.loadCounter}`);
            
            if (config.hasParent) {
                console.log(`║ ├─ Parent Context: ${state.parentId ? `${state.parentName} (${state.parentId})` : 'None'}`);
            }
            
            console.log(`║ └─ Last Updated: ${state.lastUpdated || 'Never'}`);
        });
        
        console.log('╚════════════════════════════════════════════════════════════════\n');
    }
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.EntityManager = EntityManager;

// ===================================================================
// יצירת פונקציות wrapper ל-backward compatibility
// ===================================================================

// פונקציות טעינה ראשיות
window.loadCustomers = () => EntityManager.load('customer');
window.loadPurchases = () => EntityManager.load('purchase');
window.loadBurials = () => EntityManager.load('burial');
window.loadPlots = (blockId, blockName, forceReset) => EntityManager.load('plot', blockId, blockName, forceReset);
window.loadAreaGraves = (plotId, plotName, forceReset) => EntityManager.load('areaGrave', plotId, plotName, forceReset);
window.loadGraves = (areaGraveId, areaGraveName, forceReset) => EntityManager.load('grave', areaGraveId, areaGraveName, forceReset);

// פונקציות Browse Data
window.loadCustomersBrowseData = (signal) => EntityLoader.loadBrowseData('customer', signal);
window.loadPurchasesBrowseData = (signal) => EntityLoader.loadBrowseData('purchase', signal);
window.loadBurialsBrowseData = (signal) => EntityLoader.loadBrowseData('burial', signal);
window.loadPlotsBrowseData = (blockId, signal) => EntityLoader.loadBrowseData('plot', signal, blockId);
window.loadAreaGravesBrowseData = (plotId, signal) => EntityLoader.loadBrowseData('areaGrave', signal, plotId);
window.loadGravesBrowseData = (areaGraveId, signal) => EntityLoader.loadBrowseData('grave', signal, areaGraveId);

// פונקציות Append More
window.appendMoreCustomers = () => EntityManager.appendMore('customer');
window.appendMorePurchases = () => EntityManager.appendMore('purchase');
window.appendMoreBurials = () => EntityManager.appendMore('burial');
window.appendMorePlots = () => EntityManager.appendMore('plot');
window.appendMoreAreaGraves = () => EntityManager.appendMore('areaGrave');
window.appendMoreGraves = () => EntityManager.appendMore('grave');

// פונקציות מחיקה
window.deleteCustomer = (id) => EntityManager.delete('customer', id);
window.deletePurchase = (id) => EntityManager.delete('purchase', id);
window.deleteBurial = (id) => EntityManager.delete('burial', id);
window.deletePlot = (id) => EntityManager.delete('plot', id);
window.deleteAreaGrave = (id) => EntityManager.delete('areaGrave', id);
window.deleteGrave = (id) => EntityManager.delete('grave', id);

// פונקציות רענון
window.customersRefreshData = () => EntityManager.refresh('customer');
window.purchasesRefreshData = () => EntityManager.refresh('purchase');
window.burialsRefreshData = () => EntityManager.refresh('burial');
window.plotsRefreshData = () => EntityManager.refresh('plot');
window.refreshAreaGravesData = () => EntityManager.refresh('areaGrave');
window.refreshGravesData = () => EntityManager.refresh('grave');

// פונקציות סטטוס scroll
window.checkCustomersScrollStatus = () => EntityManager.checkScrollStatus('customer');
window.checkPurchasesScrollStatus = () => EntityManager.checkScrollStatus('purchase');
window.checkBurialsScrollStatus = () => EntityManager.checkScrollStatus('burial');
window.checkPlotsScrollStatus = () => EntityManager.checkScrollStatus('plot');
window.checkAreaGravesScrollStatus = () => EntityManager.checkScrollStatus('areaGrave');
window.checkGravesScrollStatus = () => EntityManager.checkScrollStatus('grave');

console.log('✅ entity-manager.js v1.0.0 - Loaded successfully!');
console.log('🎯 All entity management functions are now available');
console.log('📋 Type EntityManager.dumpSystemState() to see full system state');