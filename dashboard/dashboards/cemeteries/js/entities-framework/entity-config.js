/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-config.js
 * Version: 2.0.0
 * Updated: 2025-11-20
 * Author: Malkiel
 * Change Summary:
 * - v2.0.0: 🔥 הרחבה מלאה של הקונפיגורציה
 *   ✅ הוספת כל השדות הנדרשים לכל יישות
 *   ✅ הוספת קונפיגורציית טבלה (columns)
 *   ✅ הוספת קונפיגורציית חיפוש (searchableFields)
 *   ✅ הוספת קונפיגורציית סטטיסטיקות
 *   ✅ הוספת פונקציות עזר ייחודיות (adapters)
 */

console.log('🚀 entity-config.js v2.0.0 - Loading...');

// ===================================================================
// קונפיגורציה מרכזית לכל היישויות
// ===================================================================
const ENTITY_CONFIG = {
    // ===================================================================
    // לקוחות (Customers)
    // ===================================================================
    customer: {
        // מידע בסיסי
        singular: 'לקוח',
        singularArticle: 'את הלקוח',
        plural: 'לקוחות',
        
        // API
        apiFile: 'customers-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/customers-api.php',
        
        // משתנים גלובליים
        searchVar: 'customerSearch',
        tableVar: 'customersTable',
        currentPageVar: 'customersCurrentPage',
        totalPagesVar: 'customersTotalPages',
        dataArrayVar: 'currentCustomers',
        isLoadingVar: 'customersIsLoadingMore',
        isSearchModeVar: 'customersIsSearchMode',
        currentQueryVar: 'customersCurrentQuery',
        searchResultsVar: 'customersSearchResults',
        
        // פונקציות
        renderFunctionName: 'renderCustomersRows',
        loadFunctionName: 'loadCustomers',
        loadBrowseFunctionName: 'loadCustomersBrowseData',
        appendMoreFunctionName: 'appendMoreCustomers',
        
        // פרמטרים
        hasParent: false,
        parentParam: null,
        defaultLimit: 200,
        defaultOrderBy: 'createDate',
        defaultSortDirection: 'DESC',
        
        // עמודות טבלה
        columns: [
            { field: 'firstName', label: 'שם פרטי', width: '15%' },
            { field: 'lastName', label: 'שם משפחה', width: '15%' },
            { field: 'idNumber', label: 'ת.ז', width: '12%' },
            { field: 'phone', label: 'טלפון', width: '12%' },
            { field: 'email', label: 'אימייל', width: '18%' },
            { field: 'city', label: 'עיר', width: '12%' },
            { field: 'status', label: 'סטטוס', width: '10%', type: 'status' },
            { field: 'actions', label: 'פעולות', width: '6%', type: 'actions' }
        ],
        
        // שדות חיפוש
        searchableFields: [
            { name: 'firstName', label: 'שם פרטי', table: 'customers', type: 'text', matchType: ['exact', 'fuzzy', 'startsWith'] },
            { name: 'lastName', label: 'שם משפחה', table: 'customers', type: 'text', matchType: ['exact', 'fuzzy', 'startsWith'] },
            { name: 'idNumber', label: 'ת.ז', table: 'customers', type: 'text', matchType: ['exact'] },
            { name: 'phone', label: 'טלפון', table: 'customers', type: 'text', matchType: ['exact', 'fuzzy'] },
            { name: 'email', label: 'אימייל', table: 'customers', type: 'text', matchType: ['exact', 'fuzzy'] },
            { name: 'city', label: 'עיר', table: 'customers', type: 'text', matchType: ['exact', 'fuzzy'] },
            { name: 'status', label: 'סטטוס', table: 'customers', type: 'select', matchType: ['exact'], 
              options: [
                  { value: 'active', label: 'פעיל' },
                  { value: 'inactive', label: 'לא פעיל' }
              ]
            }
        ],
        
        // סטטיסטיקות
        statsConfig: {
            elements: {
                'totalCustomers': 'total_customers',
                'activeCustomers': 'active',
                'newThisMonth': 'new_this_month'
            }
        },
        
        // סטטוסים
        statuses: {
            'active': { text: 'פעיל', color: '#10b981' },
            'inactive': { text: 'לא פעיל', color: '#6b7280' }
        }
    },

    // ===================================================================
    // רכישות (Purchases)
    // ===================================================================
    purchase: {
        singular: 'רכישה',
        singularArticle: 'את הרכישה',
        plural: 'רכישות',
        
        apiFile: 'purchases-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/purchases-api.php',
        
        searchVar: 'purchaseSearch',
        tableVar: 'purchasesTable',
        currentPageVar: 'purchasesCurrentPage',
        totalPagesVar: 'purchasesTotalPages',
        dataArrayVar: 'currentPurchases',
        isLoadingVar: 'purchasesIsLoadingMore',
        isSearchModeVar: 'purchasesIsSearchMode',
        currentQueryVar: 'purchasesCurrentQuery',
        searchResultsVar: 'purchasesSearchResults',
        
        renderFunctionName: 'renderPurchasesRows',
        loadFunctionName: 'loadPurchases',
        loadBrowseFunctionName: 'loadPurchasesBrowseData',
        appendMoreFunctionName: 'appendMorePurchases',
        
        hasParent: false,
        parentParam: null,
        defaultLimit: 200,
        defaultOrderBy: 'createDate',
        defaultSortDirection: 'DESC',
        
        columns: [
            { field: 'purchaseNumber', label: 'מספר רכישה', width: '10%' },
            { field: 'customerName', label: 'שם לקוח', width: '18%' },
            { field: 'purchaseType', label: 'סוג רכישה', width: '12%', type: 'enum' },
            { field: 'totalAmount', label: 'סכום', width: '12%', type: 'currency' },
            { field: 'paidAmount', label: 'שולם', width: '12%', type: 'currency' },
            { field: 'purchaseDate', label: 'תאריך', width: '12%', type: 'date' },
            { field: 'status', label: 'סטטוס', width: '10%', type: 'status' },
            { field: 'actions', label: 'פעולות', width: '6%', type: 'actions' }
        ],
        
        searchableFields: [
            { name: 'purchaseNumber', label: 'מספר רכישה', table: 'purchases', type: 'text', matchType: ['exact'] },
            { name: 'firstName', label: 'שם פרטי לקוח', table: 'customers', type: 'text', matchType: ['fuzzy'] },
            { name: 'lastName', label: 'שם משפחה לקוח', table: 'customers', type: 'text', matchType: ['fuzzy'] },
            { name: 'idNumber', label: 'ת.ז לקוח', table: 'customers', type: 'text', matchType: ['exact'] },
            { name: 'purchaseType', label: 'סוג רכישה', table: 'purchases', type: 'select', matchType: ['exact'],
              options: [
                  { value: 'new', label: 'רכישה חדשה' },
                  { value: 'transfer', label: 'העברת בעלות' },
                  { value: 'renewal', label: 'חידוש' }
              ]
            },
            { name: 'status', label: 'סטטוס', table: 'purchases', type: 'select', matchType: ['exact'],
              options: [
                  { value: 'completed', label: 'הושלם' },
                  { value: 'pending', label: 'ממתין' },
                  { value: 'cancelled', label: 'מבוטל' }
              ]
            }
        ],
        
        statsConfig: {
            elements: {
                'totalPurchases': 'total_purchases',
                'completedPurchases': 'completed',
                'newThisMonth': 'new_this_month'
            }
        },
        
        statuses: {
            'completed': { text: 'הושלם', color: '#10b981' },
            'pending': { text: 'ממתין', color: '#f59e0b' },
            'cancelled': { text: 'מבוטל', color: '#ef4444' }
        }
    },

    // ===================================================================
    // קבורות (Burials)
    // ===================================================================
    burial: {
        singular: 'קבורה',
        singularArticle: 'את הקבורה',
        plural: 'קבורות',
        
        apiFile: 'burials-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/burials-api.php',
        
        searchVar: 'burialSearch',
        tableVar: 'burialsTable',
        currentPageVar: 'burialsCurrentPage',
        totalPagesVar: 'burialsTotalPages',
        dataArrayVar: 'currentBurials',
        isLoadingVar: 'burialsIsLoadingMore',
        isSearchModeVar: 'burialsIsSearchMode',
        currentQueryVar: 'burialsCurrentQuery',
        searchResultsVar: 'burialsSearchResults',
        
        renderFunctionName: 'renderBurialsRows',
        loadFunctionName: 'loadBurials',
        loadBrowseFunctionName: 'loadBurialsBrowseData',
        appendMoreFunctionName: 'appendMoreBurials',
        
        hasParent: false,
        parentParam: null,
        defaultLimit: 200,
        defaultOrderBy: 'createDate',
        defaultSortDirection: 'DESC',
        
        columns: [
            { field: 'burialNumber', label: 'מספר קבורה', width: '10%' },
            { field: 'deceasedName', label: 'שם המנוח', width: '18%' },
            { field: 'customerName', label: 'שם לקוח', width: '18%' },
            { field: 'burialDate', label: 'תאריך קבורה', width: '12%', type: 'date' },
            { field: 'graveName', label: 'קבר', width: '15%' },
            { field: 'status', label: 'סטטוס', width: '10%', type: 'status' },
            { field: 'actions', label: 'פעולות', width: '6%', type: 'actions' }
        ],
        
        searchableFields: [
            { name: 'burialNumber', label: 'מספר קבורה', table: 'burials', type: 'text', matchType: ['exact'] },
            { name: 'deceasedFirstName', label: 'שם פרטי מנוח', table: 'burials', type: 'text', matchType: ['fuzzy'] },
            { name: 'deceasedLastName', label: 'שם משפחה מנוח', table: 'burials', type: 'text', matchType: ['fuzzy'] },
            { name: 'firstName', label: 'שם פרטי לקוח', table: 'customers', type: 'text', matchType: ['fuzzy'] },
            { name: 'lastName', label: 'שם משפחה לקוח', table: 'customers', type: 'text', matchType: ['fuzzy'] },
            { name: 'status', label: 'סטטוס', table: 'burials', type: 'select', matchType: ['exact'],
              options: [
                  { value: 'completed', label: 'הושלם' },
                  { value: 'pending', label: 'ממתין' }
              ]
            }
        ],
        
        statsConfig: {
            elements: {
                'totalBurials': 'total_burials',
                'completedBurials': 'completed',
                'newThisMonth': 'new_this_month'
            }
        },
        
        statuses: {
            'completed': { text: 'הושלם', color: '#10b981' },
            'pending': { text: 'ממתין', color: '#f59e0b' }
        }
    },

    // ===================================================================
    // חלקות (Plots)
    // ===================================================================
    plot: {
        singular: 'חלקה',
        singularArticle: 'את החלקה',
        plural: 'חלקות',
        
        apiFile: 'plots-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/plots-api.php',
        
        searchVar: 'plotSearch',
        tableVar: 'plotsTable',
        currentPageVar: 'plotsCurrentPage',
        totalPagesVar: 'plotsTotalPages',
        dataArrayVar: 'currentPlots',
        isLoadingVar: 'plotsIsLoadingMore',
        isSearchModeVar: 'plotsIsSearchMode',
        currentQueryVar: 'plotsCurrentQuery',
        searchResultsVar: 'plotsSearchResults',
        
        renderFunctionName: 'renderPlotsRows',
        loadFunctionName: 'loadPlots',
        loadBrowseFunctionName: 'loadPlotsBrowseData',
        appendMoreFunctionName: 'appendMorePlots',
        
        hasParent: true,
        parentParam: 'blockId',
        parentFilterIdVar: 'plotsFilterBlockId',
        parentFilterNameVar: 'plotsFilterBlockName',
        defaultLimit: 200,
        defaultOrderBy: 'createDate',
        defaultSortDirection: 'DESC',
        
        columns: [
            { field: 'plotNumber', label: 'מספר חלקה', width: '12%' },
            { field: 'plotName', label: 'שם חלקה', width: '20%' },
            { field: 'blockName', label: 'גוש', width: '18%' },
            { field: 'totalAreaGraves', label: 'אחוזות קבר', width: '12%' },
            { field: 'totalGraves', label: 'קברים', width: '12%' },
            { field: 'status', label: 'סטטוס', width: '10%', type: 'status' },
            { field: 'actions', label: 'פעולות', width: '6%', type: 'actions' }
        ],
        
        searchableFields: [
            { name: 'plotNumber', label: 'מספר חלקה', table: 'plots', type: 'text', matchType: ['exact', 'startsWith'] },
            { name: 'plotName', label: 'שם חלקה', table: 'plots', type: 'text', matchType: ['fuzzy'] },
            { name: 'blockName', label: 'שם גוש', table: 'blocks', type: 'text', matchType: ['fuzzy'] },
            { name: 'status', label: 'סטטוס', table: 'plots', type: 'select', matchType: ['exact'],
              options: [
                  { value: 'active', label: 'פעיל' },
                  { value: 'inactive', label: 'לא פעיל' },
                  { value: 'full', label: 'מלא' }
              ]
            }
        ],
        
        statsConfig: {
            elements: {
                'totalPlots': 'total_plots',
                'totalAreaGraves': 'total_area_graves',
                'newThisMonth': 'new_this_month'
            },
            parentParam: 'blockId'
        },
        
        statuses: {
            'active': { text: 'פעיל', color: '#10b981' },
            'inactive': { text: 'לא פעיל', color: '#6b7280' },
            'full': { text: 'מלא', color: '#ef4444' }
        }
    },

    // ===================================================================
    // אחוזות קבר (Area Graves)
    // ===================================================================
    'areaGrave2': {
        singular: 'אחוזת קבר',
        singularArticle: 'את אחוזת הקבר',
        plural: 'אחוזות קבר',
        
        apiFile: 'areaGraves-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
        
        idField: 'areaGraveId',
        nameField: 'areaGraveNameHe',
        
        hasParent: true,
        parentParam: 'plotId',
        
        defaultLimit: 200,
        defaultSort: 'createDate',
        defaultSortDirection: 'DESC',
        
        searchableFields: [
            'areaGraveNameHe',
            'areaGraveNameEn', 
            'areaGraveNameAr',
            'coordinates',
            'lineNameHe'
        ],
        
        columns: [
            { 
                field: 'areaGraveNameHe', 
                label: 'שם אחוזת קבר', 
                type: 'link', 
                width: '200px',
                sortable: true
            },
            { 
                field: 'coordinates', 
                label: 'קואורדינטות', 
                type: 'text', 
                width: '150px',
                sortable: true
            },
            { 
                field: 'lineNameHe', 
                label: 'שורה', 
                type: 'text', 
                width: '120px',
                sortable: true
            },
            { 
                field: 'graveType', 
                label: 'סוג', 
                type: 'graveType',  // ✅ שינוי מ-enum ל-graveType
                width: '100px',
                sortable: true
            },
            { 
                field: 'graves_count', 
                label: 'כמות קברים', 
                type: 'badge', 
                width: '120px',
                sortable: true
            },
            { 
                field: 'createDate', 
                label: 'תאריך יצירה', 
                type: 'date', 
                width: '120px',
                sortable: true
            },
            { 
                field: 'actions', 
                label: 'פעולות', 
                type: 'actions', 
                width: '120px',
                sortable: false
            }
        ],
        
        statsConfig: {
            endpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=stats',
            elements: {
                'areaGravesTotalCount': 'total_area_graves',
                'gravesTotalCount': 'total_graves',
                'areaGravesNewThisMonth': 'new_this_month'
            },
            parentParam: 'plotId'
        }
    },
    'areaGrave': {
        singular: 'אחוזת קבר',
        singularArticle: 'את אחוזת הקבר',
        plural: 'אחוזות קבר',
        
        apiFile: 'areaGraves-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
        
        idField: 'unicId',  // ✅ תיקון!
        nameField: 'areaGraveNameHe',
        
        hasParent: true,
        parentParam: 'plotId',
        
        defaultLimit: 200,
        defaultSort: 'createDate',
        defaultSortDirection: 'DESC',
        
        searchableFields: [
            'areaGraveNameHe',
            'areaGraveNameEn', 
            'areaGraveNameAr',
            'coordinates',
            'lineNameHe'
        ],
        
        columns: [
            { 
                field: 'areaGraveNameHe', 
                label: 'שם אחוזת קבר', 
                type: 'link', 
                width: '200px',
                sortable: true
            },
            { 
                field: 'coordinates', 
                label: 'קואורדינטות', 
                type: 'text', 
                width: '150px',
                sortable: true
            },
            { 
                field: 'lineNameHe', 
                label: 'שורה', 
                type: 'text', 
                width: '120px',
                sortable: true
            },
            { 
                field: 'graveType', 
                label: 'סוג', 
                type: 'graveType',
                width: '100px',
                sortable: true
            },
            { 
                field: 'graves_count', 
                label: 'כמות קברים', 
                type: 'badge', 
                width: '120px',
                sortable: true
            },
            { 
                field: 'createDate', 
                label: 'תאריך יצירה', 
                type: 'date', 
                width: '120px',
                sortable: true
            },
            { 
                field: 'actions', 
                label: 'פעולות', 
                type: 'actions', 
                width: '120px',
                sortable: false
            }
        ],
        
        statsConfig: {
            endpoint: '/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=stats',
            elements: {
                'areaGravesTotalCount': 'total_area_graves',
                'gravesTotalCount': 'total_graves',
                'areaGravesNewThisMonth': 'new_this_month'
            },
            parentParam: 'plotId'
        }
    },

    // ===================================================================
    // קברים (Graves)
    // ===================================================================
    'grave': {
        singular: 'קבר',
        singularArticle: 'את הקבר',
        plural: 'קברים',
        
        apiFile: 'graves-api.php',
        apiEndpoint: '/dashboard/dashboards/cemeteries/api/graves-api.php',
        
        idField: 'unicId',
        nameField: 'graveNameHe',
        
        hasParent: true,
        parentParam: 'areaGraveId',
        
        defaultLimit: 200,
        defaultSort: 'createDate',
        defaultSortDirection: 'DESC',
        
        searchableFields: [
            'graveNameHe',
            'area_grave_name',
            'comments'
        ],
        
        columns: [
            { 
                field: 'graveNameHe', 
                label: 'שם קבר', 
                type: 'link',
                width: '200px',
                sortable: true
            },
            { 
                field: 'area_grave_name', 
                label: 'אחוזת קבר', 
                type: 'text',
                width: '180px',
                sortable: true
            },
            { 
                field: 'plotType', 
                label: 'סוג חלקה', 
                type: 'plotType',
                width: '120px',
                sortable: true
            },
            { 
                field: 'graveStatus', 
                label: 'סטטוס', 
                type: 'graveStatus',
                width: '110px',
                sortable: true
            },
            { 
                field: 'createDate', 
                label: 'תאריך יצירה', 
                type: 'date',
                width: '120px',
                sortable: true
            },
            { 
                field: 'actions', 
                label: 'פעולות', 
                type: 'actions',
                width: '120px',
                sortable: false
            }
        ],
        
        statsConfig: {
            endpoint: '/dashboard/dashboards/cemeteries/api/graves-api.php?action=stats',
            elements: {
                'gravesTotalCount': 'total_graves',
                'gravesAvailable': 'available',
                'gravesPurchased': 'purchased',
                'gravesBuried': 'buried',
                'gravesReserved': 'reserved',
                'gravesNewThisMonth': 'new_this_month'
            },
            parentParam: 'areaGraveId'
        }
    },
};

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.ENTITY_CONFIG = ENTITY_CONFIG;

console.log('✅ entity-config.js v2.0.0 - Loaded successfully!');
console.log('📊 Configured entities:', Object.keys(ENTITY_CONFIG).join(', '));