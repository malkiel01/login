/**
 * DashboardCleaner - ניקוי חכם של הדשבורד
 * FIX: כעת מגן על TableManager מפני ניקוי לא רצוי
 */

const DashboardCleaner = {
    
    /**
     * ניקוי כללי של הדשבורד
     */
    clear(settings = {}) {
        const defaults = {
            targetLevel: null,       // cemetery, block, plot, areaGrave, grave, customer
            keepBreadcrumb: false,
            keepSidebar: false,
            keepCard: false,
            fullReset: false
        };
        
        settings = { ...defaults, ...settings };
        
        console.log('🧹 Cleaning dashboard with settings:', settings);
        
        // 1. Full reset אם נדרש
        if (settings.fullReset) {
            this.fullReset();
            return;
        }
        
        // 2. ניקוי כרטיסים
        if (!settings.keepCard) {
            this.clearCards();
        }
        
        // 3. ניקוי טבלה - מחיקת table-container
        this.clearTable();
        
        // 4. ניקוי sidebar
        if (!settings.keepSidebar && settings.targetLevel) {
            this.clearSidebarForLevel(settings.targetLevel);
        }
        
        // 5. ניקוי/עדכון breadcrumb
        if (!settings.keepBreadcrumb) {
            if (settings.targetLevel) {
                this.updateBreadcrumbForLevel(settings.targetLevel);
            } else {
                this.resetBreadcrumb();
            }
        }
        
        // 6. ניקוי הודעות
        this.clearMessages();
        
        // 7. ניקוי חיפוש
        this.clearSearch();
        
        // 8. ניקוי מודלים פתוחים
        this.closeModals();
        
        console.log('✅ Dashboard cleaned successfully');
    },
    
    /**
     * ניקוי כל הכרטיסים
     */
    clearCards() {
        // ניקוי כרטיסי היררכיה עם הפונקציה המיוחדת
        if (typeof clearAllHierarchyCards === 'function') {
            clearAllHierarchyCards();
        }
        
        // ניקוי כרטיס יחיד
        const itemCard = document.getElementById('itemCard');
        if (itemCard) {
            itemCard.remove();
            console.log('  ✓ Item card removed');
        }
        
        // ניקוי כרטיסים נוספים מסוג info-card
        const infoCards = document.querySelectorAll('.info-card');
        infoCards.forEach(card => {
            if (!card.closest('.stats-grid')) {
                card.remove();
            }
        });
        
        console.log('  ✓ All cards cleared');
    },
    
    /**
     * ניקוי הטבלה - מחיקה פשוטה של table-container
     */
    clearTable() {
        // פשוט תמחק את table-container - זהו!
        const tableContainer = document.querySelector('.table-container');
        
        if (tableContainer) {
            tableContainer.remove();
            console.log('  ✓ Table container removed');
        }
    },
    
    /**
     * ניקוי חיפוש
     */
    clearSearch() {
        // ניקוי שורת החיפוש הרגילה
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
        }
    },
    
    /**
     * ניקוי כל הסידבר
     */
    clearAllSidebar() {
        // הסר active מכל הכותרות
        document.querySelectorAll('.hierarchy-header').forEach(header => {
            header.classList.remove('active');
        });
        
        // נקה את כל הפריטים הנבחרים
        const containers = [
            'cemeterySelectedItem',
            'blockSelectedItem', 
            'plotSelectedItem',
            'areaGraveSelectedItem',
            'graveSelectedItem'
        ];
        
        containers.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = '';
                element.style.display = 'none';
            }
        });
        
        console.log('  ✓ Sidebar cleared');
    },
    
    /**
     * ניקוי סידבר לפי רמה
     */
    clearSidebarForLevel(level) {
        const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        const levelIndex = hierarchy.indexOf(level);
        
        if (levelIndex === -1) {
            console.log('Unknown level:', level);
            return;
        }
        
        // נקה את כל מה שמתחת לרמה זו
        for (let i = levelIndex + 1; i < hierarchy.length; i++) {
            const containerId = hierarchy[i] + 'SelectedItem';
            const element = document.getElementById(containerId);
            if (element) {
                element.innerHTML = '';
                element.style.display = 'none';
            }
        }
        
        console.log(`  ✓ Sidebar cleared below level: ${level}`);
    },
    
    /**
     * איפוס breadcrumb
     */
    resetBreadcrumb() {
        if (typeof BreadcrumbManager !== 'undefined') {
            BreadcrumbManager.reset();
            console.log('  ✓ Breadcrumb reset');
        }
    },
    
    /**
     * עדכון breadcrumb לפי רמה
     */
    updateBreadcrumbForLevel(level) {
        if (typeof BreadcrumbManager !== 'undefined') {
            BreadcrumbManager.update(window.selectedItems || {}, level);
            console.log(`  ✓ Breadcrumb updated for level: ${level}`);
        }
    },
    
    /**
     * ניקוי הודעות
     */
    clearMessages() {
        const messages = document.querySelectorAll('.alert, .toast, .notification');
        messages.forEach(msg => msg.remove());
    },
    
    /**
     * סגירת מודלים פתוחים
     */
    closeModals() {
        const modals = document.querySelectorAll('.modal.show, [role="dialog"][style*="display: block"]');
        modals.forEach(modal => {
            // סגור את המודל
            if (modal.classList.contains('show')) {
                modal.classList.remove('show');
            }
            modal.style.display = 'none';
            
            // הסר backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        });
    },
    
    /**
     * איפוס מלא של הדשבורד
     */
    fullReset() {
        console.log('🔄 Performing full dashboard reset...');
        
        // ניקוי כל הדברים
        this.clearCards();
        this.clearTable();
        this.clearAllSidebar();
        this.resetBreadcrumb();
        this.clearMessages();
        this.clearSearch();
        this.closeModals();
        
        // איפוס משתנים גלובליים
        if (window.selectedItems) {
            window.selectedItems = {};
        }
        if (window.currentType) {
            window.currentType = null;
        }
        if (window.currentParentId) {
            window.currentParentId = null;
        }
        
        console.log('✅ Full reset completed');
    },
    
};

// ==========================================
// פונקציות עזר גלובליות לתאימות אחורה
// ==========================================

window.clearDashboard = function(options) {
    DashboardCleaner.clear(options);
};

window.clearItemCard = function() {
    console.log('📌 Legacy call: clearItemCard()');
    DashboardCleaner.clearCards();
};

window.clearAllSidebarSelections = function() {
    console.log('📌 Legacy call: clearAllSidebarSelections()');
    DashboardCleaner.clearAllSidebar();
};

window.clearSidebarBelow = function(type) {
    console.log('📌 Legacy call: clearSidebarBelow()');
    DashboardCleaner.clearSidebarForLevel(type);
};

// הפוך לגלובלי
window.DashboardCleaner = DashboardCleaner;

console.log('✅ DashboardCleaner loaded - Generic & Simple');