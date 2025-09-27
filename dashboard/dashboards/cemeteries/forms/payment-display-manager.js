// payment-display-manager.js
const PaymentDisplayManager2 = {
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


// payment-display-manager.js
// זה קובץ חדש שתיצור!

// ====== שלב 1 ======
const PaymentDisplayManager = {
    config: {
        currency: '₪',
        locale: 'he-IL'
    }
};

// ====== שלב 2 ======
// פונקציה ראשית - קובעת איך להציג תשלומים
PaymentDisplayManager.render = function(payments, mode = 'summary') {
    if (!payments || payments.length === 0) {
        return '<p style="color: #999; text-align: center;">לא הוגדרו תשלומים</p>';
    }
    
    switch(mode) {
        case 'summary':
            return this.renderSummary(payments);
        case 'edit':
            return this.renderEditTable(payments);
        case 'view':
            return this.renderViewTable(payments);
        default:
            return this.renderSummary(payments);
    }
};

// חישוב סה"כ
PaymentDisplayManager.calculateTotal = function(payments = null) {
    const list = payments || window.purchasePayments || [];
    return list.reduce((sum, payment) => {
        return sum + (parseFloat(payment.paymentAmount) || 0);
    }, 0).toFixed(2);
};

// ====== שלב 3 ======
// תצוגת סיכום - מחליפה את displayPaymentsSummary
PaymentDisplayManager.renderSummary = function(payments) {
    const paymentTypes = window.PAYMENT_TYPES_CONFIG || {};
    const summary = {};
    
    // קבץ לפי סוגים
    payments.forEach(payment => {
        const name = payment.customPaymentType || 
                    (paymentTypes[payment.paymentType]?.name) || 
                    `תשלום מסוג ${payment.paymentType}`;
        
        const amount = parseFloat(payment.paymentAmount) || 0;
        
        if (!summary[name]) {
            summary[name] = 0;
        }
        summary[name] += amount;
    });
    
    // בנה תצוגה
    const items = Object.entries(summary).map(([type, amount]) => 
        `${type}: ${this.config.currency}${amount.toFixed(2)}`
    ).join(' | ');
    
    const total = this.calculateTotal(payments);
    
    return `${items}<br><strong>סה"כ: ${this.config.currency}${total}</strong>`;
};

// ====== שלב 4 ======
// טבלת עריכה - אופציונלי
PaymentDisplayManager.renderEditTable = function(payments) {
    let html = `
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 8px; text-align: right;">סוג תשלום</th>
                    <th style="padding: 8px; text-align: right;">סכום</th>
                    <th style="padding: 8px; text-align: center;">סטטוס</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    const paymentTypes = window.PAYMENT_TYPES_CONFIG || {};
    
    payments.forEach(payment => {
        const name = payment.customPaymentType || 
                    (paymentTypes[payment.paymentType]?.name) || 
                    `תשלום מסוג ${payment.paymentType}`;
        
        const isLocked = payment.mandatory === true || payment.required === true;
        
        html += `
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">
                    ${name} ${isLocked ? '🔒' : ''}
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">
                    ${this.config.currency}${(payment.paymentAmount || 0).toFixed(2)}
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">
                    ${isLocked ? 
                        '<span style="color: #dc3545;">חובה</span>' : 
                        '<span style="color: #28a745;">רשות</span>'}
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
        <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
            <strong>סה"כ: ${this.config.currency}${this.calculateTotal(payments)}</strong>
        </div>
    `;
    
    return html;
};
