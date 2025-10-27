/*
 * File: dashboards/dashboard/cemeteries/assets/js/clearDashboard.js
 * Version: 1.5.0
 * Updated: 2025-10-27
 * Author: Malkiel
 * Change Summary:
 * - v1.5.0: תיקון קריטי - ניקוי מלא של כל האלמנטים
 *   - מחיקה מלאה של table-wrapper עם כל התוכן שלו
 *   - מחיקה של כל אלמנטים עם data-fixed-width="true"
 *   - ניקוי יסודי של main-container לפני בנייה מחדש
 *   - תיקון בעיית "ילדים לא מוצגים בפעם השנייה"
 */

const DashboardCleaner = {
    
    /**
     * ניקוי כללי של הדשבורד
     */
    clear(settings = {}) {
        const defaults = {
            targetLevel: null,
            keepBreadcrumb: false,
            keepSidebar: false,
            keepCard: false,
            fullReset: false
        };
        
        settings = { ...defaults, ...settings };
        
        console.log('🧹 Cleaning dashboard with settings:', settings);
        
        if (settings.fullReset) {
            this.fullReset();
            return;
        }
        
        if (!settings.keepCard) {
            this.clearCards();
        }
        
        // ⭐ תמיד נקה את הטבלה/תוכן - גם אם TableManager פעיל!
        this.clearTable();
        
        if (!settings.keepSidebar && settings.targetLevel) {
            this.clearSidebarForLevel(settings.targetLevel);
        }
        
        if (!settings.keepBreadcrumb) {
            if (settings.targetLevel) {
                this.updateBreadcrumbForLevel(settings.targetLevel);
            } else {
                this.resetBreadcrumb();
            }
        }
        
        this.clearMessages();
        this.clearSearch();
        this.closeModals();
        
        console.log('✅ Dashboard cleaned successfully');
    },
    
    /**
     * בדיקה אם TableManager פעיל
     */
    isTableManagerActive() {
        const hasTableManager = window.customersTable && 
                               window.customersTable.elements && 
                               window.customersTable.elements.wrapper;
        
        const wrapperVisible = hasTableManager && 
                              window.customersTable.elements.wrapper.offsetParent !== null;
        
        const isCustomerMode = window.currentType === 'customer';
        
        return hasTableManager && wrapperVisible && isCustomerMode;
    },
    
    /**
     * הסתרת TableManager
     */
    hideTableManager() {
        if (window.customersTable && window.customersTable.elements.wrapper) {
            window.customersTable.elements.wrapper.style.display = 'none';
            console.log('  ✓ TableManager hidden');
        }
    },
    
    /**
     * הצגת TableManager
     */
    showTableManager() {
        if (window.customersTable && window.customersTable.elements.wrapper) {
            window.customersTable.elements.wrapper.style.display = 'flex';
            console.log('  ✓ TableManager shown');
        }
    },
    
    /**
     * ⭐ ניקוי הטבלה/תוכן - תיקון קריטי!
     */
    clearTable() {
        console.log('  🧹 Clearing table/content...');
        
        // ⭐ שלב 1: מחק את כל ה-wrappers של TableManager
        const tableWrappers = document.querySelectorAll('.table-wrapper[data-fixed-width="true"]');
        if (tableWrappers.length > 0) {
            console.log(`  🗑️ Removing ${tableWrappers.length} table-wrapper(s)...`);
            tableWrappers.forEach(wrapper => {
                wrapper.remove();
            });
        }
        
        // ⭐ שלב 2: מחק את כל האינדיקטורים של סינון
        const filterIndicators = document.querySelectorAll('.filter-indicator');
        if (filterIndicators.length > 0) {
            console.log(`  🗑️ Removing ${filterIndicators.length} filter-indicator(s)...`);
            filterIndicators.forEach(indicator => {
                indicator.remove();
            });
        }
        
        // ⭐ שלב 3: בדוק אם יש main-container
        const mainContainer = document.querySelector('.main-container');
        
        if (mainContainer) {
            console.log('  🆕 Found main-container, clearing it completely...');
            
            // מחק את כל התוכן של main-container
            mainContainer.innerHTML = '';
            console.log('  ✓ Main container cleared completely');
            return;
        }
        
        // ⭐ שלב 4: שיטה ישנה - עבודה עם table-container
        console.log('  📜 Using OLD method (table-container)');
        
        const tbody = document.getElementById('tableBody');
        const thead = document.getElementById('tableHeaders');
        
        if (tbody) {
            tbody.innerHTML = '';
            tbody.removeAttribute('data-customer-view');
            tbody.removeAttribute('data-current-type');
            console.log('  ✓ Table body cleared');
        }
        
        if (thead) {
            thead.innerHTML = '';
            console.log('  ✓ Table headers reset');
        }
    },
    
    /**
     * ניקוי כרטיסים
     */
    clearCards() {
        if (typeof clearAllHierarchyCards === 'function') {
            clearAllHierarchyCards();
        }
        
        const itemCard = document.getElementById('itemCard');
        if (itemCard) {
            itemCard.remove();
        }
        
        const infoCards = document.querySelectorAll('.info-card');
        infoCards.forEach(card => {
            if (!card.closest('.stats-grid')) {
                card.remove();
            }
        });
        
        console.log('  ✓ All cards cleared');
    },
    
    /**
     * ניקוי חיפוש
     */
    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
        }
        
        if (window.currentType !== 'customer') {
            const customerSearchSection = document.getElementById('customerSearchSection');
            if (customerSearchSection) {
                customerSearchSection.style.display = 'none';
            }
        }
    },
    
    /**
     * ניקוי סידבר
     */
    clearAllSidebar() {
        document.querySelectorAll('.hierarchy-header').forEach(header => {
            header.classList.remove('active');
        });
        
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
     * סגירת מודלים
     */
    closeModals() {
        const modals = document.querySelectorAll('.modal.show, [role="dialog"][style*="display: block"]');
        modals.forEach(modal => {
            if (modal.classList.contains('show')) {
                modal.classList.remove('show');
            }
            modal.style.display = 'none';
            
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        });
    },
    
    /**
     * איפוס מלא
     */
    fullReset() {
        console.log('🔄 Performing full dashboard reset...');
        
        if (this.isTableManagerActive()) {
            this.hideTableManager();
        }
        
        this.clearCards();
        this.clearTable();
        this.clearAllSidebar();
        this.resetBreadcrumb();
        this.clearMessages();
        this.clearSearch();
        this.closeModals();
        
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
    
    /**
     * ניקוי לפני מעבר בין רמות
     */
    prepareTransition(fromType, toType, keepParentSelection = false) {
        const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        const fromIndex = hierarchy.indexOf(fromType);
        const toIndex = hierarchy.indexOf(toType);
        
        if (toType === 'customer') {
            const mainTable = document.getElementById('mainTable');
            if (mainTable) {
                mainTable.style.display = 'none';
            }
            return;
        }
        
        if (fromType === 'customer') {
            this.hideTableManager();
            const mainTable = document.getElementById('mainTable');
            if (mainTable) {
                mainTable.style.display = 'table';
            }
        }
        
        if (toIndex < fromIndex) {
            this.clear({
                targetLevel: toType,
                keepBreadcrumb: false,
                keepSidebar: keepParentSelection
            });
        } else if (toIndex > fromIndex) {
            this.clear({
                targetLevel: fromType,
                keepBreadcrumb: true,
                keepSidebar: true,
                keepCard: false
            });
        } else {
            this.clear({
                targetLevel: toType,
                keepBreadcrumb: true,
                keepSidebar: true
            });
        }
    }
};

// פונקציות עזר גלובליות
window.clearDashboard = function(options) {
    DashboardCleaner.clear(options);
};

window.clearItemCard = function() {
    DashboardCleaner.clearCards();
};

window.clearAllSidebarSelections = function() {
    DashboardCleaner.clearAllSidebar();
};

window.clearSidebarBelow = function(type) {
    DashboardCleaner.clearSidebarForLevel(type);
};

window.DashboardCleaner = DashboardCleaner;

console.log('✅ DashboardCleaner v1.5.0 loaded - Critical fix for complete cleanup');