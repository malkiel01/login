/*
 * File: dashboards/dashboard/cemeteries/assets/js/customers-management.js
 * Version: 4.0.0
 * Updated: 2025-10-25
 * Author: Malkiel
 * Change Summary:
 * - v4.0.0: הסרה מלאה של UniversalSearch
 * - טעינה ישירה של כל הלקוחות מ-API
 * - שימוש ב-TableManager בלבד עם Virtual Scroll
 * - חיפוש מקומי פשוט (אופציונלי) - filter בצד לקוח
 * - קוד פשוט, נקי, ויציב
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================

let currentCustomers = [];      // כל הלקוחות שנטענו
let customersTable = null;      // instance של TableManager
let editingCustomerId = null;
let simpleSearchTimeout = null; // timeout לחיפוש מקומי

// ===================================================================
// טעינת לקוחות - הפונקציה הראשית (גרסה פשוטה)
// ===================================================================
async function loadCustomers() {
    console.log('📋 Loading customers - v4.0.0 (ללא UniversalSearch)...');

    setActiveMenuItem('customersItem');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'customer';
    window.currentParentId = null;
    
    // נקה את הדשבורד
    DashboardCleaner.clear({ targetLevel: 'customer' });
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ customer: { name: 'לקוחות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'ניהול לקוחות - מערכת בתי עלמין';
    
    // בנה את המבנה
    await buildCustomersContainer();

    // טען את הלקוחות מה-API
    await fetchAndDisplayCustomers();
    
    // טען סטטיסטיקות
    await loadCustomerStats();
}

// ===================================================================
// בניית המבנה HTML של לקוחות
// ===================================================================
async function buildCustomersContainer() {
    console.log('🏗️ Building customers container...');
    
    // מצא את main-container
    let mainContainer = document.querySelector('.main-container');
    
    if (!mainContainer) {
        console.log('⚠️ main-container not found, creating one...');
        const mainContent = document.querySelector('.main-content');
        mainContainer = document.createElement('div');
        mainContainer.className = 'main-container';
        
        const actionBar = mainContent.querySelector('.action-bar');
        if (actionBar) {
            actionBar.insertAdjacentElement('afterend', mainContainer);
        } else {
            mainContent.appendChild(mainContainer);
        }
    }
    
    // בנה את התוכן
    mainContainer.innerHTML = `
        <!-- סקשן חיפוש פשוט (אופציונלי) -->
        <div class="simple-search-section" style="margin-bottom: 20px;">
            <div style="max-width: 600px;">
                <input 
                    type="text" 
                    id="simpleSearchInput" 
                    class="form-control" 
                    placeholder="🔍 חיפוש מהיר לפי שם, ת.ז, טלפון..."
                    style="padding: 12px 16px; font-size: 15px; border-radius: 8px; border: 1px solid #e5e7eb;"
                />
                <small style="display: block; margin-top: 8px; color: #6b7280;">
                    ניתן לחפש בכל השדות הגלויים בטבלה
                </small>
            </div>
        </div>
        
        <!-- table-container עבור TableManager -->
        <div class="table-container">
            <table id="mainTable" class="data-table">
                <thead>
                    <tr id="tableHeaders">
                        <th style="text-align: center;">טוען לקוחות...</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td style="text-align: center; padding: 60px;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">טוען לקוחות...</span>
                            </div>
                            <div style="margin-top: 16px; color: #6b7280;">
                                טוען את רשימת הלקוחות...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    // חיבור אירוע חיפוש מקומי
    const searchInput = document.getElementById('simpleSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', handleSimpleSearch);
    }
    
    console.log('✅ Customers container built');
}

// ===================================================================
// טעינה ישירה של לקוחות מה-API
// ===================================================================
async function fetchAndDisplayCustomers() {
    console.log('📡 Fetching customers from API...');
    
    try {
        // קריאה ישירה ל-API
        const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=list');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('📦 API Response:', result);
        
        if (result.success && result.data) {
            currentCustomers = result.data;
            console.log(`✅ Loaded ${currentCustomers.length} customers`);
            
            // בנה את TableManager
            initCustomersTable(currentCustomers);
        } else {
            throw new Error(result.error || 'Failed to load customers');
        }
        
    } catch (error) {
        console.error('❌ Error loading customers:', error);
        showToast('שגיאה בטעינת לקוחות: ' + error.message, 'error');
        
        // הצג הודעת שגיאה בטבלה
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td style="text-align: center; padding: 60px;">
                        <div style="color: #ef4444; font-size: 48px; margin-bottom: 16px;">⚠️</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">שגיאה בטעינת לקוחות</div>
                        <div style="color: #6b7280;">${error.message}</div>
                        <button 
                            onclick="loadCustomers()" 
                            style="margin-top: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;"
                        >
                            נסה שוב
                        </button>
                    </td>
                </tr>
            `;
        }
    }
}

// ===================================================================
// אתחול TableManager
// ===================================================================
function initCustomersTable(data) {
    console.log(`🎨 Initializing TableManager with ${data.length} customers...`);
    
    // הגדרת עמודות
    const columns = [
        {
            id: 'numId',
            label: 'ת.ז',
            sortable: true,
            width: '120px',
            align: 'center'
        },
        {
            id: 'firstName',
            label: 'שם פרטי',
            sortable: true,
            width: '150px'
        },
        {
            id: 'lastName',
            label: 'שם משפחה',
            sortable: true,
            width: '150px'
        },
        {
            id: 'phone',
            label: 'טלפון',
            sortable: true,
            width: '120px'
        },
        {
            id: 'phoneMobile',
            label: 'נייד',
            sortable: true,
            width: '120px'
        },
        {
            id: 'streetAddress',
            label: 'כתובת',
            sortable: true,
            width: '200px'
        },
        {
            id: 'city_name',
            label: 'עיר',
            sortable: true,
            width: '120px'
        },
        {
            id: 'statusCustomer',
            label: 'סטטוס',
            sortable: true,
            width: '100px',
            align: 'center'
        },
        {
            id: 'statusResident',
            label: 'סוג תושבות',
            sortable: true,
            width: '120px',
            align: 'center'
        },
        {
            id: 'createDate',
            label: 'תאריך יצירה',
            sortable: true,
            width: '120px',
            align: 'center'
        },
        {
            id: 'actions',
            label: 'פעולות',
            sortable: false,
            width: '120px',
            align: 'center'
        }
    ];
    
    // יצירת TableManager
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        columns: columns,
        data: data,
        totalItems: data.length,
        
        // הגדרות Virtual Scroll
        infiniteScroll: true,
        itemsPerPage: 100,  // 100 שורות בכל פעם
        scrollThreshold: 200,
        
        // פונקציית רינדור מותאמת אישית
        renderCell: (column, row) => {
            switch (column.id) {
                case 'statusCustomer':
                    return formatCustomerStatus(row.statusCustomer);
                
                case 'statusResident':
                    return formatCustomerType(row.statusResident);
                
                case 'createDate':
                    return formatDate(row.createDate);
                
                case 'actions':
                    return `
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <button 
                                onclick="editCustomer(${row.id})"
                                class="btn btn-sm btn-primary"
                                title="ערוך לקוח"
                                style="padding: 4px 8px; font-size: 12px;"
                            >
                                ✏️
                            </button>
                            <button 
                                onclick="deleteCustomer(${row.id})"
                                class="btn btn-sm btn-danger"
                                title="מחק לקוח"
                                style="padding: 4px 8px; font-size: 12px;"
                            >
                                🗑️
                            </button>
                        </div>
                    `;
                
                default:
                    return row[column.id] || '';
            }
        }
    });
    
    // שמור global reference
    window.customersTable = customersTable;
    
    console.log(`✅ TableManager initialized successfully with ${data.length} customers`);
}

// ===================================================================
// חיפוש מקומי פשוט (filter בצד לקוח)
// ===================================================================
function handleSimpleSearch(event) {
    const searchTerm = event.target.value.trim().toLowerCase();
    
    // debounce - חכה 300ms אחרי הקלדה
    clearTimeout(simpleSearchTimeout);
    
    simpleSearchTimeout = setTimeout(() => {
        console.log('🔍 Simple search:', searchTerm);
        
        if (!customersTable) {
            console.warn('⚠️ TableManager not initialized');
            return;
        }
        
        if (!searchTerm) {
            // אם אין חיפוש - הצג הכל
            customersTable.setData(currentCustomers);
            console.log(`✅ Showing all ${currentCustomers.length} customers`);
            return;
        }
        
        // סנן לקוחות לפי החיפוש
        const filtered = currentCustomers.filter(customer => {
            // חפש בכל השדות הרלוונטיים
            const searchableFields = [
                customer.firstName,
                customer.lastName,
                customer.numId,
                customer.phone,
                customer.phoneMobile,
                customer.streetAddress,
                customer.city_name
            ];
            
            return searchableFields.some(field => 
                field && field.toString().toLowerCase().includes(searchTerm)
            );
        });
        
        console.log(`✅ Found ${filtered.length} matching customers`);
        customersTable.setData(filtered);
        
    }, 300); // 300ms debounce
}

// ===================================================================
// פונקציות פורמט ועזר
// ===================================================================

// פורמט סוג תושבות
function formatCustomerType(type) {
    const types = {
        1: 'תושב',
        2: 'תושב חוץ',
        3: 'אחר'
    };
    return types[type] || '-';
}

// פורמט סטטוס לקוח
function formatCustomerStatus(status) {
    const statuses = {
        1: { text: 'פעיל', color: '#10b981' },
        0: { text: 'לא פעיל', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || statuses[1];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
}

// פורמט תאריך
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// ===================================================================
// פונקציות CRUD
// ===================================================================

// מחיקת לקוח
async function deleteCustomer(customerId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק לקוח זה?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=delete&id=${customerId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('הלקוח נמחק בהצלחה', 'success');
            
            // טען מחדש את הלקוחות
            await fetchAndDisplayCustomers();
        } else {
            showToast(data.error || 'שגיאה במחיקת לקוח', 'error');
        }
    } catch (error) {
        console.error('Error deleting customer:', error);
        showToast('שגיאה במחיקת לקוח', 'error');
    }
}

// עריכת לקוח
async function editCustomer(customerId) {
    console.log('Edit customer:', customerId);
    editingCustomerId = customerId;
    showToast('עריכה בפיתוח...', 'info');
}

// ===================================================================
// טעינת סטטיסטיקות
// ===================================================================
async function loadCustomerStats() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            console.log('📊 Customer stats:', data.data);
        }
    } catch (error) {
        console.error('Error loading customer stats:', error);
    }
}

// ===================================================================
// פונקציות עזר
// ===================================================================

// הצגת הודעת Toast
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease-out;
    `;
    
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideUp 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// רענון נתונים
async function refreshData() {
    console.log('🔄 Refreshing customers data...');
    await fetchAndDisplayCustomers();
}

// בדיקת סטטוס טעינה
function checkScrollStatus() {
    if (!customersTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = customersTable.getFilteredData().length;
    const displayed = customersTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(customersTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// ===================================================================
// חשיפת פונקציות גלובליות
// ===================================================================
window.loadCustomers = loadCustomers;
window.deleteCustomer = deleteCustomer;
window.editCustomer = editCustomer;
window.refreshData = refreshData;
window.customersTable = customersTable;
window.checkScrollStatus = checkScrollStatus;

console.log('✅ Customers Management Module Loaded v4.0.0');
console.log('📋 Simple & Clean - No UniversalSearch');
console.log('💡 Commands:');
console.log('   - checkScrollStatus() - בדוק סטטוס טעינה');
console.log('   - refreshData() - רענן נתונים');