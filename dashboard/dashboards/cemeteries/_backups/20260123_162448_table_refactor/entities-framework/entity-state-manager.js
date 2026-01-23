/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-state-manager.js
 * Version: 1.0.0
 * Updated: 2025-11-20
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: 🆕 יצירת מנהל state מרכזי
 *   ✅ ניהול state לכל היישויות במקום אחד
 *   ✅ get/set/reset לכל סוגי ה-state
 *   ✅ תמיכה ב-parent filtering
 */


// ===================================================================
// מנהל State מרכזי לכל היישויות
// ===================================================================
class EntityStateManager {
    constructor() {
        this.states = {};
        this.initializeAllStates();
    }

    /**
     * אתחול state לכל היישויות
     */
    initializeAllStates() {
        const entityTypes = Object.keys(ENTITY_CONFIG);
        
        entityTypes.forEach(entityType => {
            this.states[entityType] = this.createInitialState(entityType);
        });
        
    }

    /**
     * יצירת state ראשוני ליישות
     */
    createInitialState(entityType) {
        const config = ENTITY_CONFIG[entityType];
        
        return {
            // נתונים
            currentData: [],
            
            // pagination
            currentPage: 1,
            totalPages: 1,
            isLoadingMore: false,
            
            // חיפוש
            isSearchMode: false,
            currentQuery: '',
            searchResults: [],
            
            // instances
            searchInstance: null,
            tableInstance: null,
            
            // parent context (אם יש)
            parentId: config.hasParent ? null : undefined,
            parentName: config.hasParent ? null : undefined,
            
            // metadata
            loadCounter: 0,
            lastUpdated: null
        };
    }

    /**
     * קבלת state של יישות
     */
    getState(entityType) {
        if (!this.states[entityType]) {
            this.states[entityType] = this.createInitialState(entityType);
        }
        return this.states[entityType];
    }

    /**
     * עדכון state של יישות
     */
    setState(entityType, updates) {
        const currentState = this.getState(entityType);
        this.states[entityType] = {
            ...currentState,
            ...updates,
            lastUpdated: new Date().toISOString()
        };
        
        // סנכרון למשתנים גלובליים
        this.syncToGlobalVars(entityType);
    }

    /**
     * איפוס state של יישות
     */
    resetState(entityType) {
        this.states[entityType] = this.createInitialState(entityType);
        this.syncToGlobalVars(entityType);
    }

    /**
     * סנכרון ל-window globals (לתאימות לאחור)
     */
    syncToGlobalVars(entityType) {
        const config = ENTITY_CONFIG[entityType];
        const state = this.states[entityType];
        
        // סנכרון משתנים
        if (config.dataArrayVar) {
            window[config.dataArrayVar] = state.currentData;
        }
        if (config.currentPageVar) {
            window[config.currentPageVar] = state.currentPage;
        }
        if (config.totalPagesVar) {
            window[config.totalPagesVar] = state.totalPages;
        }
        if (config.isLoadingVar) {
            window[config.isLoadingVar] = state.isLoadingMore;
        }
        if (config.isSearchModeVar) {
            window[config.isSearchModeVar] = state.isSearchMode;
        }
        if (config.currentQueryVar) {
            window[config.currentQueryVar] = state.currentQuery;
        }
        if (config.searchResultsVar) {
            window[config.searchResultsVar] = state.searchResults;
        }
        if (config.searchVar) {
            window[config.searchVar] = state.searchInstance;
        }
        if (config.tableVar) {
            window[config.tableVar] = state.tableInstance;
        }
        
        // סנכרון parent context (אם יש)
        if (config.hasParent && config.parentFilterIdVar) {
            window[config.parentFilterIdVar] = state.parentId;
        }
        if (config.hasParent && config.parentFilterNameVar) {
            window[config.parentFilterNameVar] = state.parentName;
        }
    }

    /**
     * קבלת נתונים נוכחיים
     */
    getCurrentData(entityType) {
        return this.getState(entityType).currentData;
    }

    /**
     * הוספת נתונים (append)
     */
    appendData(entityType, newData) {
        const state = this.getState(entityType);
        const updatedData = [...state.currentData, ...newData];
        
        this.setState(entityType, {
            currentData: updatedData
        });
        
        return updatedData;
    }

    /**
     * עדכון pagination
     */
    updatePagination(entityType, page, totalPages) {
        this.setState(entityType, {
            currentPage: page,
            totalPages: totalPages
        });
    }

    /**
     * עדכון מצב טעינה
     */
    setLoading(entityType, isLoading) {
        this.setState(entityType, {
            isLoadingMore: isLoading
        });
    }

    /**
     * עדכון מצב חיפוש
     */
    setSearchMode(entityType, isSearchMode, query = '', results = []) {
        this.setState(entityType, {
            isSearchMode,
            currentQuery: query,
            searchResults: results
        });
    }

    /**
     * עדכון parent context
     */
    setParentContext(entityType, parentId, parentName) {
        const config = ENTITY_CONFIG[entityType];
        
        if (!config.hasParent) {
            return;
        }
        
        this.setState(entityType, {
            parentId,
            parentName
        });
    }

    /**
     * הגדלת מונה טעינות
     */
    incrementLoadCounter(entityType) {
        const state = this.getState(entityType);
        this.setState(entityType, {
            loadCounter: state.loadCounter + 1
        });
        return state.loadCounter + 1;
    }

    /**
     * שמירת instance של חיפוש
     */
    setSearchInstance(entityType, searchInstance) {
        this.setState(entityType, {
            searchInstance
        });
    }

    /**
     * שמירת instance של טבלה
     */
    setTableInstance(entityType, tableInstance) {
        this.setState(entityType, {
            tableInstance
        });
    }

    /**
     * בדיקה האם יש עוד נתונים לטעינה
     */
    hasMoreData(entityType) {
        const state = this.getState(entityType);
        return state.currentPage < state.totalPages;
    }

    /**
     * בדיקה האם טוען כרגע
     */
    isLoading(entityType) {
        return this.getState(entityType).isLoadingMore;
    }

    /**
     * דאמפ של כל ה-states (לדיבאג)
     */
    dumpStates() {
        // Debug function - enable console.log when needed
    }
}

// ===================================================================
// יצירת instance גלובלי
// ===================================================================
window.EntityStateManager = EntityStateManager;
window.entityState = new EntityStateManager();

