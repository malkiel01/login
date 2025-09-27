// payment-display-manager.js
const PaymentDisplayManager = {
    // הגדרות קונפיגורציה
    config: {
        paymentTypes: window.PAYMENT_TYPES_CONFIG || {},
        currency: '₪',
        locale: 'he-IL'
    },
    
    // המרת מצבי תצוגה
    displayModes: {
        VIEW: 'view',
        EDIT: 'edit', 
        SUMMARY: 'summary'
    }
};