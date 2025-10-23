/**
 * cemeteries-config.js
 * קונפיגורציה לניהול בתי עלמין
 */

const CEMETERIES_CONFIG = {
    // מזהה וכותרות
    type: 'cemetery',
    title: 'בתי עלמין',
    singular: 'בית עלמין',
    menuItemId: 'cemeteryItem',
    nameField: 'cemeteryNameHe',
    
    // API
    api: {
        endpoint: '/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?',
        table: 'cemeteries',
        primaryKey: 'unicId'
    },
    
    // פיצ'רים
    features: {
        search: true,
        export: true,
        reports: true,
        actions: true,
        edit: true,
        delete: true,
        open: true,
        stats: true
    },
    
    // חיפוש
    searchPlaceholder: 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
    
    searchFields: [
        {
            name: 'cemeteryNameHe',
            label: 'שם בית עלמין (עברית)',
            table: 'cemeteries',
            type: 'text',
            matchType: ['exact', 'fuzzy', 'startsWith']
        },
        {
            name: 'cemeteryNameEn',
            label: 'שם בית עלמין (אנגלית)',
            table: 'cemeteries',
            type: 'text',
            matchType: ['exact', 'fuzzy', 'startsWith']
        },
        {
            name: 'cemeteryCode',
            label: 'קוד בית עלמין',
            table: 'cemeteries',
            type: 'text',
            matchType: ['exact', 'startsWith']
        },
        {
            name: 'address',
            label: 'כתובת',
            table: 'cemeteries',
            type: 'text',
            matchType: ['exact', 'fuzzy']
        },
        {
            name: 'contactName',
            label: 'איש קשר',
            table: 'cemeteries',
            type: 'text',
            matchType: ['exact', 'fuzzy']
        },
        {
            name: 'createDate',
            label: 'תאריך יצירה',
            table: 'cemeteries',
            type: 'date',
            matchType: ['exact', 'before', 'after', 'between']
        }
    ],
    
    // עמודות טבלה
    columns: [
        {
            field: 'cemeteryNameHe',
            label: 'שם בית עלמין',
            width: '250px',
            sortable: true,
            secondaryField: 'cemeteryNameEn'
        },
        {
            field: 'cemeteryCode',
            label: 'קוד',
            width: '100px',
            sortable: true
        },
        {
            field: 'address',
            label: 'כתובת',
            width: '200px',
            sortable: true,
            secondaryField: 'coordinates',
            secondaryIcon: '📍 '
        },
        {
            field: 'contactName',
            label: 'איש קשר',
            width: '150px',
            sortable: true,
            secondaryField: 'contactPhoneName',
            secondaryIcon: '📞 '
        },
        {
            field: 'createDate',
            label: 'תאריך יצירה',
            width: '120px',
            type: 'date',
            sortable: true,
            render: (cemetery) => {
                if (!cemetery.createDate) return '';
                const date = new Date(cemetery.createDate);
                return date.toLocaleDateString('he-IL');
            }
        }
    ],
    
    // פונקציית פתיחה
    onOpen: (cemeteryId, cemeteryName) => {
        console.log('🏛️ Opening cemetery:', cemeteryId, cemeteryName);
        
        window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
        window.currentType = 'block';
        window.currentParentId = cemeteryId;
        window.currentCemeteryId = cemeteryId;
        
        // עדכן Breadcrumb
        if (typeof BreadcrumbManager !== 'undefined') {
            BreadcrumbManager.update(window.selectedItems);
        }
        
        // טען גושים
        if (window.tableRenderer && typeof window.tableRenderer.loadAndDisplay === 'function') {
            window.tableRenderer.loadAndDisplay('block', cemeteryId);
        }
    }
};

console.log('✅ Cemeteries Config Loaded');