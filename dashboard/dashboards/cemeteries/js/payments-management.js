/*
 * File: dashboards/dashboard/cemeteries/assets/js/payments-management.js
 * Version: 5.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v5.0.0: 🔥 מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('payment')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentPayments = [];
let paymentSearch = null;
let paymentsTable = null;
let editingPaymentId = null;

let paymentsIsSearchMode = false;
let paymentsCurrentQuery = '';
let paymentsSearchResults = [];

let paymentsCurrentPage = 1;
let paymentsTotalPages = 1;
let paymentsIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getPlotTypeBadge(type) {
    const types = {
        1: { label: 'פטורה', color: '#10b981' },
        2: { label: 'חריגה', color: '#f97316' },
        3: { label: 'סגורה', color: '#dc2626' }
    };

    const typeInfo = types[type] || { label: '-', color: '#999' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getGraveTypeBadge(type) {
    const types = {
        1: 'שדה',
        2: 'רוויה',
        3: 'סנהדרין'
    };
    return types[type] || '-';
}

function getResidentBadge(type) {
    const types = {
        1: { label: 'ירושלים', color: '#10b981' },
        2: { label: 'חוץ', color: '#f97316' },
        3: { label: 'חו״ל', color: '#dc2626' }
    };

    const typeInfo = types[type] || { label: '-', color: '#999' };
    return `<span style="background: ${typeInfo.color}20; color: ${typeInfo.color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${typeInfo.label}</span>`;
}

function getPriceDefinitionBadge(type) {
    const types = {
        1: 'עלות קבר',
        2: 'שירותי לוויה',
        3: 'שירותי קבורה',
        4: 'אגרת מצבה',
        5: 'בדיקת עומק',
        6: 'פירוק מצבה',
        7: 'הובלה',
        8: 'טהרה',
        9: 'תכריכים',
        10: 'החלפת שם'
    };

    return types[type] || '-';
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

// פתיחת טופס הוספת תשלום
function openAddPayment() {
    window.currentType = 'payment';
    window.currentParentId = null;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('payment', null, null);
    }
}

// עריכת תשלום
async function editPayment(id) {
    window.currentType = 'payment';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('payment', null, id);
    }
}

// צפייה בתשלום - פתיחת כרטיס תשלום
async function viewPayment(id) {
    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            id: 'paymentCard-' + id,
            type: 'iframe',
            src: '/dashboard/dashboards/cemeteries/forms/iframe/paymentCard-iframe.php?itemId=' + id,
            title: 'כרטיס תשלום',
            width: 1000,
            height: 700
        });
    }
}

// דאבל-קליק על שורת תשלום
async function handlePaymentDoubleClick(payment) {
    const paymentId = typeof payment === 'object' ? payment.unicId : payment;
    viewPayment(paymentId);
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

// רינדור עמודות מיוחדות - נקרא מ-EntityRenderer
function renderPaymentColumn(payment, column) {
    switch(column.field) {
        case 'plotType':
            return getPlotTypeBadge(payment.plotType);
        case 'graveType':
            return getGraveTypeBadge(payment.graveType);
        case 'resident':
            return getResidentBadge(payment.resident);
        case 'priceDefinition':
            return getPriceDefinitionBadge(payment.priceDefinition);
        case 'price':
            return `₪${parseFloat(payment.price || 0).toLocaleString()}`;
        default:
            return null; // תן ל-EntityRenderer לטפל
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.paymentSearch = paymentSearch;
window.paymentsTable = paymentsTable;
window.currentPayments = currentPayments;

window.getPlotTypeBadge = getPlotTypeBadge;
window.getGraveTypeBadge = getGraveTypeBadge;
window.getResidentBadge = getResidentBadge;
window.getPriceDefinitionBadge = getPriceDefinitionBadge;

window.openAddPayment = openAddPayment;
window.editPayment = editPayment;
window.viewPayment = viewPayment;
window.handlePaymentDoubleClick = handlePaymentDoubleClick;
window.renderPaymentColumn = renderPaymentColumn;
