/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-common-utils.js
 * Version: 1.0.0
 * Updated: 2025-11-19
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת קובץ גלובלי לפונקציות משותפות
 *   ✅ showToast() - הצגת הודעות למשתמש
 *   ✅ formatDate() - פורמט תאריך לעברית
 *   ✅ checkEntityScrollStatus() - בדיקת סטטוס גלילה
 */

console.log('🚀 entities-common-utils.js v1.0.0 - Loading...');

// ===================================================================
// 1️⃣ הצגת הודעות Toast למשתמש
// ===================================================================
/**
 * מציג הודעת Toast למשתמש
 * @param {string} message - הטקסט להצגה
 * @param {string} type - סוג ההודעה: 'success' | 'error' | 'info'
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast-message';
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


// ===================================================================
// 2️⃣ פורמט תאריך לעברית
// ===================================================================
/**
 * ממיר תאריך לפורמט עברי
 * @param {string} dateString - תאריך בפורמט ISO
 * @returns {string} תאריך מפורמט בעברית
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}


// ===================================================================
// 3️⃣ בדיקת סטטוס גלילה של טבלה
// ===================================================================
/**
 * בודק ומציג את סטטוס הגלילה של טבלה
 * @param {Object} tableInstance - אובייקט TableManager
 * @param {string} entityName - שם היישות (לתצוגה בלוג)
 */
function checkEntityScrollStatus(tableInstance, entityName = 'Entity') {
    if (!tableInstance) {
        console.log(`❌ ${entityName} table not initialized`);
        return;
    }
    
    const total = tableInstance.getFilteredData().length;
    const displayed = tableInstance.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log(`📊 ${entityName} Scroll Status:`);
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.showToast = showToast;
window.formatDate = formatDate;
window.checkEntityScrollStatus = checkEntityScrollStatus;

console.log('✅ entities-common-utils.js v1.0.0 - Loaded successfully!');