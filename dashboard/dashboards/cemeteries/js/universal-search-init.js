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
window.initUniversalSearch = function(config) {
    console.log(`🔍 Initializing UniversalSearch for: ${config.entityType}`);
    
    // ולידציה
    if (!config.entityType) {
        throw new Error('❌ entityType is required!');
    }
    if (!config.apiEndpoint) {
        throw new Error('❌ apiEndpoint is required!');
    }
    if (!config.searchableFields || config.searchableFields.length === 0) {
        throw new Error('❌ searchableFields are required!');
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
            params: config.apiParams || {}  // ⭐ הוסף את זה!
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
            itemsPerPage: config.itemsPerPage || 222,
            showPagination: config.showPagination || false,
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
    
    console.log(`✅ UniversalSearch created for ${config.entityType}`);
    
    return searchInstance;
};

console.log('✅ Universal Search Initializer Loaded (v1.0.0)');
console.log('💡 Use: initUniversalSearch(config) to create search instances');