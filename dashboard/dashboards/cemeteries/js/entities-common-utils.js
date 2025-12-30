/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-common-utils.js
 * Version: 1.1.0
 * Updated: 2025-11-20
 * Author: Malkiel
 * Change Summary:
 * - v1.1.0: 🔧 תיקון טעינה - הסרת fetch שגוי
 *   ✅ הוספת formatCurrency
 *   ✅ הוספת formatEntityStatus
 *   ✅ הוספת checkEntityScrollStatus
 *   ✅ הוספת deleteEntity
 *   ✅ הוספת refreshEntityData
 *   ✅ הוספת loadEntityStats
 */


// ===================================================================
// 1️⃣ הצגת הודעות Toast למשתמש
// ===================================================================
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
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// ===================================================================
// 3️⃣ פורמט מטבע
// ===================================================================
function formatCurrency(amount) {
    if (!amount && amount !== 0) return '-';
    return new Intl.NumberFormat('he-IL', {
        style: 'currency',
        currency: 'ILS'
    }).format(amount);
}

// ===================================================================
// 4️⃣ פורמט סטטוס יישות
// ===================================================================
function formatEntityStatus(entityType, status) {
    const statusConfig = {
        customer: {
            'active': { text: 'פעיל', color: '#10b981' },
            'inactive': { text: 'לא פעיל', color: '#6b7280' }
        },
        purchase: {
            'completed': { text: 'הושלם', color: '#10b981' },
            'pending': { text: 'ממתין', color: '#f59e0b' },
            'cancelled': { text: 'מבוטל', color: '#ef4444' }
        },
        burial: {
            'completed': { text: 'הושלם', color: '#10b981' },
            'pending': { text: 'ממתין', color: '#f59e0b' }
        },
        plot: {
            'active': { text: 'פעיל', color: '#10b981' },
            'inactive': { text: 'לא פעיל', color: '#6b7280' },
            'full': { text: 'מלא', color: '#ef4444' }
        },
        areaGrave: {
            'available': { text: 'פנוי', color: '#10b981' },
            'occupied': { text: 'תפוס', color: '#ef4444' },
            'reserved': { text: 'שמור', color: '#f59e0b' }
        },
        grave: {
            'available': { text: 'פנוי', color: '#10b981' },
            'occupied': { text: 'תפוס', color: '#ef4444' },
            'reserved': { text: 'שמור', color: '#f59e0b' }
        }
    };
    
    const config = statusConfig[entityType];
    if (!config || !config[status]) {
        return `<span style="background: #6b7280; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">לא ידוע</span>`;
    }
    
    const statusInfo = config[status];
    return `<span style="background: ${statusInfo.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">${statusInfo.text}</span>`;
}

// ===================================================================
// 5️⃣ בדיקת סטטוס גלילה של טבלה
// ===================================================================
function checkEntityScrollStatus(tableInstance, entityName = 'Entity') {
    if (!tableInstance) {
        return;
    }
    
    const total = tableInstance.getFilteredData().length;
    const displayed = tableInstance.getDisplayedData().length;
    const remaining = total - displayed;
    
    
    if (remaining > 0) {
    } else {
    }
}

// ===================================================================
// 6️⃣ מחיקת יישות
// ===================================================================
async function deleteEntity(entityType, entityId) {
    // זה יעבוד רק אם EntityManager קיים
    if (typeof EntityManager !== 'undefined' && EntityManager.delete) {
        return await EntityManager.delete(entityType, entityId);
    }
    
    // fallback לשיטה הישנה
    return false;
}

// ===================================================================
// 7️⃣ רענון נתוני יישות
// ===================================================================
async function refreshEntityData(entityType) {
    // זה יעבוד רק אם EntityManager קיים
    if (typeof EntityManager !== 'undefined' && EntityManager.refresh) {
        return await EntityManager.refresh(entityType);
    }
    
    // fallback לשיטה הישנה
}

// ===================================================================
// 8️⃣ טעינת סטטיסטיקות יישות
// ===================================================================
async function loadEntityStats(entityType, signal, parentId = null) {
    // זה יעבוד רק אם EntityLoader קיים
    if (typeof EntityLoader !== 'undefined' && EntityLoader.loadStats) {
        return await EntityLoader.loadStats(entityType, signal, parentId);
    }
    
    // fallback לשיטה הישנה
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.showToast = showToast;
window.formatDate = formatDate;
window.formatCurrency = formatCurrency;
window.formatEntityStatus = formatEntityStatus;
window.checkEntityScrollStatus = checkEntityScrollStatus;
window.deleteEntity = deleteEntity;
window.refreshEntityData = refreshEntityData;
window.loadEntityStats = loadEntityStats;

