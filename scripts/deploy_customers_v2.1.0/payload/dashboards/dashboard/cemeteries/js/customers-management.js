/*
 * File: dashboards/dashboard/cemeteries/js/customers-management.js
 * Version: 2.1.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - תיקון: הוספת initUniversalSearch מותאם ללקוחות
 * - תיקון: container IDs נכונים (#customerSearchSection)
 * - תיקון: API endpoint נכון (/api/customers-api.php)
 * - שיפור: ארגון קוד ברור יותר
 */

/**
 * ========================================
 * טעינת לקוחות - FINAL VERSION
 * ========================================
 */
async function loadCustomers() {
    console.log('📋 Loading customers - FINAL VERSION...');
    
    try {
        // עדכון מצב גלובלי
        window.currentType = 'customer';
        window.currentParentId = null;
        
        // ניקוי דשבורד
        clearDashboard({
            targetLevel: 'customer',
            keepBreadcrumb: false,
            keepSidebar: false,
            keepCard: false
        });
        
        clearAllSidebarSelections();
        BreadcrumbManager.update(window.selectedItems, 'customer');
        
        // בניית המבנה
        buildCustomersContainer();
        
        // ⭐ אתחול UniversalSearch מותאם ללקוחות
        await initCustomersUniversalSearch();
        
        // חיפוש ראשוני
        if (window.customersSearch) {
            await window.customersSearch.search({ query: '', filters: [] });
        }
        
    } catch (error) {
        console.error('❌ Error loading customers:', error);
        showError('שגיאה בטעינת לקוחות: ' + error.message);
    }
}

/**
 * ========================================
 * בניית מבנה HTML ללקוחות
 * ========================================
 */
function buildCustomersContainer() {
    console.log('🏗️ Building customers container...');
    
    const mainContent = document.querySelector('.main-content');
    if (!mainContent) {
        console.error('❌ main-content not found');
        return;
    }
    
    // מצא או צור main-container
    let mainContainer = mainContent.querySelector('.main-container');
    if (!mainContainer) {
        mainContainer = document.createElement('div');
        mainContainer.className = 'main-container';
        
        const actionBar = mainContent.querySelector('.action-bar');
        if (actionBar) {
            actionBar.insertAdjacentElement('afterend', mainContainer);
        } else {
            mainContent.appendChild(mainContainer);
        }
    }
    
    // בנה את המבנה
    mainContainer.innerHTML = `
        <!-- סטטיסטיקות -->
        <div class="stats-grid" id="customerStats">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-label">סך הכל לקוחות</div>
                    <div class="stat-value" id="totalCustomers">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-content">
                    <div class="stat-label">חדשים החודש</div>
                    <div class="stat-value" id="newCustomers">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-content">
                    <div class="stat-label">פעילים</div>
                    <div class="stat-value" id="activeCustomers">0</div>
                </div>
            </div>
        </div>

        <!-- חיפוש אוניברסלי -->
        <div class="search-section" id="customerSearchSection">
            <!-- UniversalSearch יבנה את זה אוטומטית -->
        </div>

        <!-- מיכל לתוצאות -->
        <div id="customersResults" class="results-container">
            <div class="loading-message">טוען נתונים...</div>
        </div>
    `;
    
    console.log('✅ Customers container built');
}

/**
 * ========================================
 * אתחול UniversalSearch מותאם ללקוחות
 * ========================================
 */
async function initCustomersUniversalSearch() {
    console.log('🔍 Initializing UniversalSearch for customers...');
    
    try {
        window.customersSearch = new UniversalSearch({
            dataSource: {
                endpoint: '/dashboard/dashboards/cemeteries/api/customers-api.php',
                action: 'list',
                tables: ['customers'],
                joins: [],
                additionalParams: {}
            },
            
            searchableFields: [
                { 
                    field: 'customer_id_number', 
                    label: 'ת.ז', 
                    type: 'text',
                    placeholder: 'הקלד מספר ת.ז'
                },
                { 
                    field: 'customer_first_name_he', 
                    label: 'שם פרטי (עברית)', 
                    type: 'text',
                    placeholder: 'הקלד שם פרטי'
                },
                { 
                    field: 'customer_last_name_he', 
                    label: 'שם משפחה (עברית)', 
                    type: 'text',
                    placeholder: 'הקלד שם משפחה'
                },
                { 
                    field: 'customer_first_name_en', 
                    label: 'שם פרטי (אנגלית)', 
                    type: 'text',
                    placeholder: 'Enter first name'
                },
                { 
                    field: 'customer_last_name_en', 
                    label: 'שם משפחה (אנגלית)', 
                    type: 'text',
                    placeholder: 'Enter last name'
                },
                { 
                    field: 'customer_phone', 
                    label: 'טלפון', 
                    type: 'text',
                    placeholder: 'הקלד מספר טלפון'
                },
                { 
                    field: 'customer_email', 
                    label: 'אימייל', 
                    type: 'text',
                    placeholder: 'הקלד כתובת אימייל'
                },
                { 
                    field: 'customer_status', 
                    label: 'סטטוס', 
                    type: 'select',
                    options: [
                        { value: 'active', label: 'פעיל' },
                        { value: 'inactive', label: 'לא פעיל' }
                    ]
                }
            ],
            
            display: {
                container: '#customerSearchSection',
                resultsContainer: '#customersResults',
                showCount: true,
                emptyMessage: 'לא נמצאו לקוחות',
                columns: [
                    { field: 'customer_id_number', label: 'ת.ז', width: '120px' },
                    { field: 'customer_full_name_he', label: 'שם מלא (עברית)', width: '200px' },
                    { field: 'customer_full_name_en', label: 'שם מלא (אנגלית)', width: '200px' },
                    { field: 'customer_phone', label: 'טלפון', width: '130px' },
                    { field: 'customer_email', label: 'אימייל', width: '200px' },
                    { field: 'customer_status', label: 'סטטוס', width: '100px' }
                ]
            },
            
            results: {
                itemsPerPage: 50,
                scrollThreshold: 200,
                renderCallback: renderCustomersRows
            },
            
            behavior: {
                debounceMs: 300,
                autoSearch: true,
                clearOnEmpty: true
            },
            
            callbacks: {
                onSearchStart: () => console.log('🔎 Searching customers...'),
                onSearchComplete: (results) => {
                    console.log(`📦 Results: ${results.data.length} customers found`);
                    updateCustomersStats(results.stats);
                },
                onError: (error) => {
                    console.error('❌ Search error:', error);
                    showError('שגיאה בחיפוש לקוחות');
                }
            }
        });
        
        console.log('✅ UniversalSearch initialized for customers');
        
    } catch (error) {
        console.error('❌ Error initializing UniversalSearch:', error);
        throw error;
    }
}

/**
 * ========================================
 * עדכון סטטיסטיקות לקוחות
 * ========================================
 */
function updateCustomersStats(stats) {
    if (!stats) return;
    
    console.log('Customer stats:', stats);
    
    // עדכן את הערכים
    const totalEl = document.getElementById('totalCustomers');
    const newEl = document.getElementById('newCustomers');
    const activeEl = document.getElementById('activeCustomers');
    
    if (totalEl) totalEl.textContent = stats.total_customers || 0;
    if (newEl) newEl.textContent = stats.new_this_month || 0;
    if (activeEl) activeEl.textContent = stats.active_customers || 0;
}

/**
 * ========================================
 * רינדור שורות לקוחות בטבלה
 * ========================================
 */
function renderCustomersRows(data, append = false) {
    console.log('🎨 renderCustomersRows called with', data.length, 'items');
    
    if (!Array.isArray(data) || data.length === 0) {
        if (!append) {
            const resultsContainer = document.getElementById('customersResults');
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div class="no-results">לא נמצאו לקוחות</div>';
            }
        }
        return;
    }
    
    const resultsContainer = document.getElementById('customersResults');
    if (!resultsContainer) {
        console.error('❌ Results container not found');
        return;
    }
    
    // אם לא append, נקה קודם
    if (!append) {
        resultsContainer.innerHTML = '';
    }
    
    // בדוק אם יש TableManager
    if (!window.customersTableManager) {
        console.log('✅ Creating new TableManager with', data.length, 'total items');
        
        // צור TableManager חדש
        window.customersTableManager = new TableManager({
            container: resultsContainer,
            columns: [
                { field: 'customer_id_number', label: 'ת.ז', width: '120px' },
                { field: 'customer_full_name_he', label: 'שם מלא (עברית)', width: '200px' },
                { field: 'customer_full_name_en', label: 'שם מלא (אנגלית)', width: '200px' },
                { field: 'customer_phone', label: 'טלפון', width: '130px' },
                { field: 'customer_email', label: 'אימייל', width: '200px' },
                { 
                    field: 'customer_status', 
                    label: 'סטטוס', 
                    width: '100px',
                    render: (value) => {
                        const statusClass = value === 'active' ? 'status-active' : 'status-inactive';
                        const statusText = value === 'active' ? 'פעיל' : 'לא פעיל';
                        return `<span class="status-badge ${statusClass}">${statusText}</span>`;
                    }
                },
                { 
                    field: 'actions', 
                    label: 'פעולות', 
                    width: '150px',
                    render: (value, row) => `
                        <div class="action-buttons">
                            <button class="btn-icon" onclick="viewCustomerDetails(${row.customer_id})" title="צפייה">
                                <svg class="icon"><use xlink:href="#icon-view"></use></svg>
                            </button>
                            <button class="btn-icon" onclick="editCustomer(${row.customer_id})" title="עריכה">
                                <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                            </button>
                            <button class="btn-icon btn-danger" onclick="deleteCustomer(${row.customer_id})" title="מחיקה">
                                <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                            </button>
                        </div>
                    `
                }
            ],
            data: data,
            itemsPerPage: 50,
            onRowClick: (row) => {
                console.log('Row clicked:', row);
                viewCustomerDetails(row.customer_id);
            }
        });
        
    } else {
        // עדכן TableManager קיים
        console.log('📝 Updating existing TableManager');
        window.customersTableManager.appendData(data);
    }
    
    console.log('📊 Total customers loaded:', window.customersTableManager.totalItems);
    console.log('📄 Items per page:', window.customersTableManager.itemsPerPage);
    console.log('📏 Scroll threshold:', window.customersTableManager.scrollThreshold);
}

/**
 * ========================================
 * פעולות על לקוחות
 * ========================================
 */

function viewCustomerDetails(customerId) {
    console.log('👁️ View customer:', customerId);
    // TODO: פתח מודל או עמוד פרטים
    alert(`צפייה בלקוח מס' ${customerId}`);
}

function editCustomer(customerId) {
    console.log('✏️ Edit customer:', customerId);
    // TODO: פתח טופס עריכה
    alert(`עריכת לקוח מס' ${customerId}`);
}

function deleteCustomer(customerId) {
    console.log('🗑️ Delete customer:', customerId);
    
    if (!confirm('האם אתה בטוח שברצונך למחוק לקוח זה?')) {
        return;
    }
    
    // TODO: שלח בקשה למחיקה
    alert(`מחיקת לקוח מס' ${customerId}`);
}

/**
 * ========================================
 * פונקציות עזר
 * ========================================
 */

function showError(message) {
    // TODO: הצג הודעת שגיאה יפה
    alert(message);
}

/**
 * ========================================
 * בדיקת סטטוס גלילה (לדיבוג)
 * ========================================
 */
window.checkScrollStatus = function() {
    if (!window.customersTableManager) {
        console.log('❌ TableManager not initialized');
        return;
    }
    
    const tm = window.customersTableManager;
    console.log('📊 Scroll Status:');
    console.log('  • Total items:', tm.totalItems);
    console.log('  • Rendered items:', tm.currentData.length);
    console.log('  • Items per page:', tm.itemsPerPage);
    console.log('  • Has more data:', tm.hasMoreData);
    console.log('  • Is loading:', tm.isLoading);
};

// ייצוא גלובלי
window.loadCustomers = loadCustomers;
window.viewCustomerDetails = viewCustomerDetails;
window.editCustomer = editCustomer;
window.deleteCustomer = deleteCustomer;

console.log('✅ Customers Management Module Loaded - v2.1.0: Fixed UniversalSearch');
console.log('💡 Commands: checkScrollStatus() - בדוק כמה רשומות נטענו');
