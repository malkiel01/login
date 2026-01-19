/*
 * File: dashboards/dashboard/cemeteries/assets/js/purchases-management.js
 * Version: 6.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v6.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('purchase')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentPurchases = [];
let purchaseSearch = null;
let purchasesTable = null;
let editingPurchaseId = null;

let purchasesIsSearchMode = false;
let purchasesCurrentQuery = '';
let purchasesSearchResults = [];

let purchasesCurrentPage = 1;
let purchasesTotalPages = 1;
let purchasesIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getPurchaseStatusBadge(status) {
    const statuses = {
        'pending': { label: 'ממתין', color: '#f59e0b' },
        'approved': { label: 'מאושר', color: '#10b981' },
        'completed': { label: 'הושלם', color: '#3b82f6' },
        'cancelled': { label: 'בוטל', color: '#ef4444' }
    };
    const statusInfo = statuses[status] || { label: status || '-', color: '#6b7280' };
    return `<span style="background: ${statusInfo.color}20; color: ${statusInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${statusInfo.label}</span>`;
}

function getPurchaseTypeBadge(purchaseType) {
    const types = {
        'pre_need': { label: 'מכירה מוקדמת', color: '#8b5cf6' },
        'at_need': { label: 'מכירה מיידית', color: '#3b82f6' }
    };
    const typeInfo = types[purchaseType] || { label: purchaseType || '-', color: '#6b7280' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getPaymentStatusBadge(paymentStatus) {
    const statuses = {
        'unpaid': { label: 'לא שולם', color: '#ef4444' },
        'partial': { label: 'שולם חלקית', color: '#f59e0b' },
        'paid': { label: 'שולם', color: '#10b981' }
    };
    const statusInfo = statuses[paymentStatus] || { label: paymentStatus || '-', color: '#6b7280' };
    return `<span style="background: ${statusInfo.color}20; color: ${statusInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${statusInfo.label}</span>`;
}

function getCustomerBadge(customerName) {
    const color = '#6366f1';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${customerName || '-'}</span>`;
}

function getPriceBadge(price) {
    const color = '#22c55e';
    const formattedPrice = price ? `₪${Number(price).toLocaleString()}` : '-';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">${formattedPrice}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

function openAddPurchase() {
    window.currentType = 'purchase';
    window.currentParentId = null;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('purchase', null, null);
    }
}

async function editPurchase(id) {
    window.currentType = 'purchase';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('purchase', null, id);
    }
}

async function viewPurchase(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('purchaseCard', null, id);
    } else {
        editPurchase(id);
    }
}

// דאבל-קליק על שורת רכישה - פתיחת כרטיס
async function handlePurchaseDoubleClick(purchase) {
    console.log('🔍 DEBUG purchase object:', purchase);
    const purchaseId = typeof purchase === 'object' ? (purchase.id || purchase.unicId) : purchase;
    console.log('🔍 DEBUG purchaseId extracted:', purchaseId);

    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('purchaseCard', null, purchaseId);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

function renderPurchaseColumn(purchase, column) {
    switch(column.field) {
        case 'status':
            return getPurchaseStatusBadge(purchase.status);
        case 'purchaseType':
            return getPurchaseTypeBadge(purchase.purchaseType);
        case 'paymentStatus':
            return getPaymentStatusBadge(purchase.paymentStatus);
        case 'customerName':
            return getCustomerBadge(purchase.customerName);
        case 'totalPrice':
        case 'price':
            return getPriceBadge(purchase.totalPrice || purchase.price);
        default:
            return null;
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.purchaseSearch = purchaseSearch;
window.purchasesTable = purchasesTable;
window.currentPurchases = currentPurchases;

window.getPurchaseStatusBadge = getPurchaseStatusBadge;
window.getPurchaseTypeBadge = getPurchaseTypeBadge;
window.getPaymentStatusBadge = getPaymentStatusBadge;
window.getCustomerBadge = getCustomerBadge;
window.getPriceBadge = getPriceBadge;

window.openAddPurchase = openAddPurchase;
window.editPurchase = editPurchase;
window.viewPurchase = viewPurchase;
window.handlePurchaseDoubleClick = handlePurchaseDoubleClick;
window.renderPurchaseColumn = renderPurchaseColumn;
