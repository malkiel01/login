/*
 * File: dashboards/dashboard/cemeteries/assets/js/customers-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('customer')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCustomers = [];
let customerSearch = null;
let customersTable = null;
let editingCustomerId = null;

let customersIsSearchMode = false;
let customersCurrentQuery = '';
let customersSearchResults = [];

let customersCurrentPage = 1;
let customersTotalPages = 1;
let customersIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getCustomerStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getCustomerTypeBadge(customerType) {
    const types = {
        'individual': { label: 'יחיד', color: '#3b82f6' },
        'organization': { label: 'ארגון', color: '#8b5cf6' },
        'company': { label: 'חברה', color: '#f59e0b' }
    };
    const typeInfo = types[customerType] || { label: customerType || 'יחיד', color: '#3b82f6' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getPurchasesCountBadge(count) {
    const color = '#ec4899';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${count || 0}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

function openAddCustomer() {
    console.log('🆕 openAddCustomer called - NEW VERSION');
    console.log('PopupManager exists:', typeof PopupManager !== 'undefined');
    if (typeof PopupManager !== 'undefined') {
        console.log('Opening iframe: /dashboard/dashboards/cemeteries/forms/iframe/customerForm-iframe.php');
        PopupManager.create({
            id: 'customerForm-new-' + Date.now(),
            type: 'iframe',
            src: '/dashboard/dashboards/cemeteries/forms/iframe/customerForm-iframe.php',
            title: 'הוספת לקוח חדש',
            width: 900,
            height: 700
        });
    } else {
        console.error('❌ PopupManager not found!');
    }
}

async function editCustomer(id) {
    console.log('✏️ editCustomer called - NEW VERSION, id:', id);
    console.log('PopupManager exists:', typeof PopupManager !== 'undefined');
    if (typeof PopupManager !== 'undefined') {
        console.log('Opening iframe: /dashboard/dashboards/cemeteries/forms/iframe/customerForm-iframe.php?itemId=' + id);
        PopupManager.create({
            id: 'customerForm-' + id,
            type: 'iframe',
            src: '/dashboard/dashboards/cemeteries/forms/iframe/customerForm-iframe.php?itemId=' + id,
            title: 'עריכת לקוח',
            width: 900,
            height: 700
        });
    } else {
        console.error('❌ PopupManager not found!');
    }
}

async function viewCustomer(id) {
    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            id: 'customerCard-' + id,
            type: 'iframe',
            src: '/dashboard/dashboards/cemeteries/forms/iframe/customerCard-iframe.php?itemId=' + id,
            title: 'כרטיס לקוח',
            width: 1000,
            height: 700
        });
    }
}

// דאבל-קליק על שורת לקוח - פתיחת כרטיס
async function handleCustomerDoubleClick(customer) {
    const customerId = typeof customer === 'object' ? customer.unicId : customer;
    viewCustomer(customerId);
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderCustomerColumn(customer, column) {
    switch(column.field) {
        case 'isActive':
            return getCustomerStatusBadge(customer.isActive);
        case 'customerType':
            return getCustomerTypeBadge(customer.customerType);
        case 'purchases_count':
            return getPurchasesCountBadge(customer.purchases_count);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.customerSearch = customerSearch;
window.customersTable = customersTable;
window.currentCustomers = currentCustomers;

window.getCustomerStatusBadge = getCustomerStatusBadge;
window.getCustomerTypeBadge = getCustomerTypeBadge;
window.getPurchasesCountBadge = getPurchasesCountBadge;

window.openAddCustomer = openAddCustomer;
window.editCustomer = editCustomer;
window.viewCustomer = viewCustomer;
window.handleCustomerDoubleClick = handleCustomerDoubleClick;
window.renderCustomerColumn = renderCustomerColumn;
