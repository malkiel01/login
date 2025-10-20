/**
 * UniversalSearch Presets
 * תבניות חיפוש מוכנות לשימוש
 */

const SearchPresets = {
    /**
     * חיפוש לקוחות בלבד - משתמש ב-customers-api הקיים
     */
    customers: {
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/customers-api.php',
            action: 'list',
            method: 'GET',  // ← שינוי ל-GET!
            tables: ['customers'],
            joins: []
        },
        
        searchableFields: [
            {
                name: 'firstName',
                label: 'שם פרטי',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'lastName',
                label: 'שם משפחה',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy', 'startsWith']
            },
            {
                name: 'numId',
                label: 'תעודת זהות',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'phone',
                label: 'טלפון',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'email',
                label: 'אימייל',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'createDate',
                label: 'תאריך יצירה',
                table: 'customers',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            }
        ],
        
        display: {
            placeholder: 'חיפוש לקוחות לפי שם, ת.ז, טלפון...',
            showAdvanced: true,
            layout: 'horizontal'
        },
        
        results: {
            columns: ['numId', 'firstName', 'lastName', 'phone', 'email', 'city_name', 'createDate']
        }
    },
    
    /**
     * חיפוש לקוחות + רכישות
     */
    customersWithPurchases: {
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/universal-search-api.php',
            action: 'search',
            tables: ['customers'],
            joins: [
                {
                    table: 'purchases',
                    on: 'customers.unicId = purchases.clientId',
                    type: 'LEFT'
                }
            ]
        },
        
        searchableFields: [
            {
                name: 'firstName',
                label: 'שם לקוח',
                table: 'customers',
                type: 'text',
                matchType: ['fuzzy', 'exact']
            },
            {
                name: 'numId',
                label: 'תעודת זהות',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'serialPurchaseId',
                label: 'מספר רכישה',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'price',
                label: 'מחיר',
                table: 'purchases',
                type: 'number',
                matchType: ['exact', 'greaterThan', 'lessThan', 'between']
            },
            {
                name: 'dateOpening',
                label: 'תאריך רכישה',
                table: 'purchases',
                type: 'date',
                matchType: ['exact', 'before', 'after', 'between']
            }
        ],
        
        display: {
            placeholder: 'חיפוש לקוחות ורכישות...',
            showAdvanced: true,
            layout: 'horizontal'
        },
        
        results: {
            columns: ['firstName', 'lastName', 'numId', 'purchase_serial', 'purchase_price', 'dateOpening']
        }
    },
    
    /**
     * חיפוש רכישות + קבורות
     */
    purchasesWithBurials: {
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/universal-search-api.php',
            action: 'search',
            tables: ['purchases'],
            joins: [
                {
                    table: 'burials',
                    on: 'purchases.clientId = burials.clientId',
                    type: 'LEFT'
                },
                {
                    table: 'customers',
                    on: 'purchases.clientId = customers.unicId',
                    type: 'LEFT'
                }
            ]
        },
        
        searchableFields: [
            {
                name: 'serialPurchaseId',
                label: 'מספר רכישה',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'serialBurialId',
                label: 'מספר קבורה',
                table: 'burials',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'price',
                label: 'מחיר רכישה',
                table: 'purchases',
                type: 'number',
                matchType: ['greaterThan', 'lessThan', 'between']
            },
            {
                name: 'dateOpening',
                label: 'תאריך רכישה',
                table: 'purchases',
                type: 'date',
                matchType: ['before', 'after', 'between', 'thisMonth', 'thisYear']
            },
            {
                name: 'dateOfDeath',
                label: 'תאריך פטירה',
                table: 'burials',
                type: 'date',
                matchType: ['before', 'after', 'between']
            }
        ],
        
        display: {
            placeholder: 'חיפוש רכישות וקבורות...',
            showAdvanced: true,
            layout: 'horizontal'
        },
        
        results: {
            columns: ['customer_name', 'purchase_serial', 'purchase_price', 'dateOpening', 'dateOfDeath']
        }
    },
    
    /**
     * חיפוש כללי בכל הטבלאות
     */
    universal: {
        dataSource: {
            type: 'api',
            endpoint: '/dashboard/dashboards/cemeteries/api/universal-search-api.php',
            action: 'search',
            tables: ['customers'],
            joins: [
                {
                    table: 'purchases',
                    on: 'customers.unicId = purchases.clientId',
                    type: 'LEFT'
                },
                {
                    table: 'burials',
                    on: 'customers.unicId = burials.clientId',
                    type: 'LEFT'
                }
            ]
        },
        
        searchableFields: [
            {
                name: 'firstName',
                label: 'שם לקוח',
                table: 'customers',
                type: 'text',
                matchType: ['fuzzy']
            },
            {
                name: 'numId',
                label: 'תעודת זהות',
                table: 'customers',
                type: 'text',
                matchType: ['exact', 'startsWith']
            },
            {
                name: 'phone',
                label: 'טלפון',
                table: 'customers',
                type: 'text',
                matchType: ['fuzzy']
            },
            {
                name: 'serialPurchaseId',
                label: 'מספר רכישה',
                table: 'purchases',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'serialBurialId',
                label: 'מספר קבורה',
                table: 'burials',
                type: 'text',
                matchType: ['exact', 'fuzzy']
            },
            {
                name: 'price',
                label: 'מחיר',
                table: 'purchases',
                type: 'number',
                matchType: ['greaterThan', 'lessThan', 'between']
            }
        ],
        
        display: {
            placeholder: 'חיפוש כללי בכל המערכת...',
            showAdvanced: true,
            layout: 'horizontal'
        }
    }
};

/**
 * פונקציית עזר ליצירת חיפוש מתבנית
 */
function createSearchFromPreset(presetName, customConfig = {}) {
    const preset = SearchPresets[presetName];
    
    if (!preset) {
        console.error(`Preset "${presetName}" not found`);
        return null;
    }
    
    // Merge עם הגדרות מותאמות
    const config = {
        ...preset,
        ...customConfig,
        dataSource: {
            ...preset.dataSource,
            ...(customConfig.dataSource || {})
        },
        display: {
            ...preset.display,
            ...(customConfig.display || {})
        },
        results: {
            ...preset.results,
            ...(customConfig.results || {})
        }
    };
    
    return new UniversalSearch(config);
}

// הפוך לגלובלי
window.SearchPresets = SearchPresets;
window.createSearchFromPreset = createSearchFromPreset;