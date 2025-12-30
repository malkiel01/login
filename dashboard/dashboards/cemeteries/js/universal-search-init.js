/*
 * File: dashboards/dashboard/cemeteries/assets/js/universal-search-init.js
 * Version: 1.0.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: פונקציה גלובלית מרכזית לאתחול UniversalSearch
 * - תומך בכל סוגי הישויות (customers, cemeteries, blocks, וכו')
 * - קוד מרוכז אחד במקום פונקציות כפולות
 */

// ===================================================================
// פונקציה גלובלית לאתחול UniversalSearch
// ===================================================================

/**
 * אתחול UniversalSearch עם קונפיגורציה מותאמת
 * @param {Object} config - קונפיגורציה מלאה
 * @param {string} config.entityType - סוג הישות (customer, cemetery, block, etc.)
 * @param {string} config.apiEndpoint - נתיב ל-API
 * @param {Array} config.searchableFields - שדות לחיפוש
 * @param {Array} config.displayColumns - עמודות לתצוגה
 * @param {string} config.searchContainerSelector - איפה להציג את שדה החיפוש
 * @param {string} config.resultsContainerSelector - איפה להציג את התוצאות
 * @param {Function} config.renderFunction - פונקציית רינדור מותאמת
 * @param {string} config.placeholder - טקסט placeholder
 * @param {number} config.itemsPerPage - כמה פריטים בעמוד
 * @param {Object} config.callbacks - callbacks מותאמים
 * @returns {UniversalSearch} instance של UniversalSearch
 */
window.initUniversalSearch = async function(config) {
    
    // ולידציה
    if (!config.entityType) {
        throw new Error('❌ entityType is required!');
    }

    // ⭐ אם לא קיבלנו apiEndpoint - נטען מהקונפיג
    if (!config.apiEndpoint) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${config.entityType}&section=api`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data && data.data.endpoint) {
                    config.apiEndpoint = data.data.endpoint;
                }
            }
        } catch (e) {
        }
        
        // אם עדיין ריק - זרוק שגיאה
        if (!config.apiEndpoint) {
            throw new Error('❌ apiEndpoint is required and not found in config!');
        }
    }

    // ⭐ אם לא קיבלנו searchableFields - נטען מהקונפיג
    if (!config.searchableFields || config.searchableFields.length === 0) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${config.entityType}&section=searchableFields`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data && data.data.length > 0) {
                    config.searchableFields = data.data;
                }
            }
        } catch (e) {
        }
        
        // אם עדיין ריק - עכשיו זרוק שגיאה
        if (!config.searchableFields || config.searchableFields.length === 0) {
            throw new Error('❌ searchableFields are required and not found in config!');
        }
    }

    // ⭐ אם לא קיבלנו displayColumns - נטען מהקונפיג
    if (!config.displayColumns || config.displayColumns.length === 0) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${config.entityType}&section=table_columns`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data && data.data.length > 0) {
                    // חלץ רק את שמות השדות (ללא actions ו-index)
                    config.displayColumns = data.data
                        .map(col => col.field)
                        .filter(f => f && f !== 'actions' && f !== 'index');
                }
            }
        } catch (e) {
        }
    }

    // ⭐ אם לא קיבלנו placeholder - נטען מהקונפיג
    if (!config.placeholder) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-config.php?type=${config.entityType}&section=search`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data && data.data.placeholder) {
                    config.placeholder = data.data.placeholder;
                }
            }
        } catch (e) {
        }
    }
    
    // בניית הקונפיגורציה המלאה ל-UniversalSearch
    const searchConfig = {
        dataSource: {
            type: 'api',
            endpoint: config.apiEndpoint,
            action: config.action || 'list',
            method: 'GET',
            tables: [config.entityType + 's'],
            joins: config.joins || [],
            params: config.additionalParams || config.apiParams || {}
        },
        
        searchableFields: config.searchableFields,
        
        display: {
            containerSelector: config.searchContainerSelector,
            showAdvanced: config.showAdvanced !== false, // ברירת מחדל: true
            showFilters: config.showFilters !== false,   // ברירת מחדל: true
            placeholder: config.placeholder || `חיפוש ${config.entityType}...`,
            layout: config.layout || 'horizontal',
            minSearchLength: config.minSearchLength || 0
        },
        
        results: {
            containerSelector: config.resultsContainerSelector,
            itemsPerPage: config.itemsPerPage || 200,
            showPagination: config.showPagination || false,
            apiLimit: config.itemsPerPage || 200,  // ⭐ הוסף את זה!
            showCounter: config.showCounter !== false, // ברירת מחדל: true
            columns: config.displayColumns,
            renderFunction: config.renderFunction
        },
        
        behavior: {
            realTime: config.realTime !== false,        // ברירת מחדל: true
            autoSubmit: config.autoSubmit !== false,    // ברירת מחדל: true
            highlightResults: config.highlightResults !== false // ברירת מחדל: true
        },
        
        callbacks: config.callbacks || {}
    };
    
    // יצירת instance חדש
    const searchInstance = new UniversalSearch(searchConfig);
    
    
    return searchInstance;
};

