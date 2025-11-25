/*
 * File: dashboard/dashboards/cemeteries/js/graveCard-handler.js
 * Version: 1.0.0
 * Updated: 2025-11-25
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת handler לכרטיס קבר
 *   - שמירת קבר (סטטוס 1→4)
 *   - ביטול שמירה (סטטוס 4→1)
 *   - פתיחת רכישה/קבורה חדשה
 *   - עריכת רכישה/קבורה קיימת
 */

const GraveCardHandler = {
    
    // נתוני הקבר הנוכחי
    currentGrave: null,
    
    /**
     * אתחול הכרטיס
     * @param {string} graveId - מזהה הקבר
     */
    init: function(graveId) {
        console.log('🪦 [GraveCardHandler] אתחול עבור קבר:', graveId);
        
        // שמור נתונים מה-window (הוגדרו ב-PHP)
        if (window.graveCardData) {
            this.currentGrave = window.graveCardData;
            console.log('📋 [GraveCardHandler] נתוני קבר:', this.currentGrave);
        }
    },
    
    /**
     * שמירת קבר - שינוי סטטוס מ-1 (פנוי) ל-4 (שמור)
     */
    saveGrave: async function() {
        console.log('💾 [GraveCardHandler] שמירת קבר...');
        
        const graveId = this.currentGrave?.unicId || window.graveCardData?.unicId;
        
        if (!graveId) {
            alert('שגיאה: מזהה קבר חסר');
            return;
        }
        
        // אישור מהמשתמש
        if (!confirm('האם לשמור את הקבר?\n\nקבר שמור לא יהיה זמין לרכישה או קבורה עד לביטול השמירה.')) {
            return;
        }
        
        try {
            const response = await fetch('/dashboard/dashboards/cemeteries/api/graves-api.php?action=update&id=' + graveId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    graveStatus: 4,
                    saveDate: new Date().toISOString().split('T')[0]
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ [GraveCardHandler] קבר נשמר בהצלחה');
                alert('הקבר נשמר בהצלחה!');
                
                // סגור את הכרטיס
                FormHandler.closeForm('graveCard');
                
                // רענן את הטבלה
                if (typeof refreshGravesTable === 'function') {
                    refreshGravesTable();
                } else if (typeof loadGraves === 'function') {
                    loadGraves();
                }
                
            } else {
                throw new Error(result.error || 'שגיאה לא ידועה');
            }
            
        } catch (error) {
            console.error('❌ [GraveCardHandler] שגיאה בשמירה:', error);
            alert('שגיאה בשמירת הקבר:\n' + error.message);
        }
    },
    
    /**
     * ביטול שמירת קבר - שינוי סטטוס מ-4 (שמור) ל-1 (פנוי)
     */
    cancelSavedGrave: async function() {
        console.log('🚫 [GraveCardHandler] ביטול שמירת קבר...');
        
        const graveId = this.currentGrave?.unicId || window.graveCardData?.unicId;
        
        if (!graveId) {
            alert('שגיאה: מזהה קבר חסר');
            return;
        }
        
        // אישור מהמשתמש
        if (!confirm('האם לבטל את שמירת הקבר?\n\nהקבר יחזור להיות פנוי וזמין לרכישה או קבורה.')) {
            return;
        }
        
        try {
            const response = await fetch('/dashboard/dashboards/cemeteries/api/graves-api.php?action=update&id=' + graveId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    graveStatus: 1,
                    saveDate: null
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ [GraveCardHandler] שמירה בוטלה בהצלחה');
                alert('שמירת הקבר בוטלה!\n\nהקבר חזר להיות פנוי.');
                
                // סגור את הכרטיס
                FormHandler.closeForm('graveCard');
                
                // רענן את הטבלה
                if (typeof refreshGravesTable === 'function') {
                    refreshGravesTable();
                } else if (typeof loadGraves === 'function') {
                    loadGraves();
                }
                
            } else {
                throw new Error(result.error || 'שגיאה לא ידועה');
            }
            
        } catch (error) {
            console.error('❌ [GraveCardHandler] שגיאה בביטול שמירה:', error);
            alert('שגיאה בביטול השמירה:\n' + error.message);
        }
    },
    
    /**
     * פתיחת טופס רכישה חדשה עבור הקבר
     */
    openNewPurchase: function() {
        console.log('🛒 [GraveCardHandler] פתיחת רכישה חדשה...');
        
        const graveId = this.currentGrave?.unicId || window.graveCardData?.unicId;
        
        if (!graveId) {
            alert('שגיאה: מזהה קבר חסר');
            return;
        }
        
        // סגור את כרטיס הקבר
        FormHandler.closeForm('graveCard');
        
        // המתן לסגירה ואז פתח רכישה
        setTimeout(() => {
            // פתח טופס רכישה עם graveId מוגדר מראש
            FormHandler.openForm('purchase', graveId, null);
        }, 300);
    },
    
    /**
     * פתיחת טופס קבורה חדשה עבור הקבר
     */
    openNewBurial: function() {
        console.log('⚰️ [GraveCardHandler] פתיחת קבורה חדשה...');
        
        const graveId = this.currentGrave?.unicId || window.graveCardData?.unicId;
        
        if (!graveId) {
            alert('שגיאה: מזהה קבר חסר');
            return;
        }
        
        // סגור את כרטיס הקבר
        FormHandler.closeForm('graveCard');
        
        // המתן לסגירה ואז פתח קבורה
        setTimeout(() => {
            // פתח טופס קבורה עם graveId מוגדר מראש
            FormHandler.openForm('burial', graveId, null);
        }, 300);
    },
    
    /**
     * עריכת רכישה קיימת
     * @param {string} purchaseId - מזהה הרכישה
     */
    editPurchase: function(purchaseId) {
        console.log('✏️ [GraveCardHandler] עריכת רכישה:', purchaseId);
        
        // סגור את כרטיס הקבר
        FormHandler.closeForm('graveCard');
        
        // המתן לסגירה ואז פתח עריכת רכישה
        setTimeout(() => {
            FormHandler.openForm('purchase', null, purchaseId);
        }, 300);
    },
    
    /**
     * עריכת קבורה קיימת
     * @param {string} burialId - מזהה הקבורה
     */
    editBurial: function(burialId) {
        console.log('✏️ [GraveCardHandler] עריכת קבורה:', burialId);
        
        // סגור את כרטיס הקבר
        FormHandler.closeForm('graveCard');
        
        // המתן לסגירה ואז פתח עריכת קבורה
        setTimeout(() => {
            FormHandler.openForm('burial', null, burialId);
        }, 300);
    }
};

// הוסף לחלון הגלובלי
window.GraveCardHandler = GraveCardHandler;

console.log('✅ [GraveCardHandler] מודול נטען בהצלחה');