/**
 * DashboardCleaner - ניקוי חכם של הדשבורד
 * שלב א: תמיכה בשתי שיטות - ישנה (table-container) וחדשה (main-container)
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
        
        // ניקוי טבלה/תוכן
        if (!this.isTableManagerActive()) {
            this.clearTable();
        } else {
            console.log('  ⚠️ TableManager is active - skipping table clear');
        }
        
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
        
        if (!this.isTableManagerActive()) {
            this.clearSearch();
        }
        
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
     * ⭐ ניקוי הטבלה/תוכן - תומך בשתי שיטות
     */
    clearTable() {
        // ⭐ שיטה חדשה: בדוק אם יש main-container
        const mainContainer = document.querySelector('.main-container');
        
        if (mainContainer) {
            console.log('  🆕 Using NEW method (main-container)');
            
            // ⭐ מחק גם table-wrapper אם קיים (TableManager)
            const tableWrapper = document.querySelector('.table-wrapper[data-fixed-width="true"]');
            if (tableWrapper) {
                tableWrapper.remove();
                console.log('  🗑️ TableManager wrapper removed');
            }
            
            // מחק את main-container
            mainContainer.remove();
            console.log('  ✓ Main container removed');
            
            // בנה main-container חדש ריק
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                const newContainer = document.createElement('div');
                newContainer.className = 'main-container';
                
                // הוסף אחרי action-bar אם קיים
                const actionBar = mainContent.querySelector('.action-bar');
                if (actionBar) {
                    actionBar.insertAdjacentElement('afterend', newContainer);
                } else {
                    mainContent.appendChild(newContainer);
                }
                console.log('  ✓ New main container created');
            }
            return;
        }
        
        // ⭐ שיטה ישנה: עבודה עם table-container
        console.log('  📜 Using OLD method (table-container)');
        
        // אם TableManager פעיל, הסתר אותו
        if (this.isTableManagerActive()) {
            this.hideTableManager();
            return;
        }
        
        const tbody = document.getElementById('tableBody');
        const thead = document.getElementById('tableHeaders');
        
        if (tbody) {
            tbody.innerHTML = '';
            tbody.removeAttribute('data-customer-view');
            tbody.removeAttribute('data-current-type');
            console.log('  ✓ Table body cleared');
        }
        
        if (thead) {
            if (window.currentType && window.currentType !== 'customer' && window.currentType && window.currentType !== 'cemetery' ) {
                this.setDefaultHeaders(window.currentType);
            } else {
                thead.innerHTML = '';
            }
            console.log('  ✓ Table headers reset');
        }
    },
    
    /**
     * הגדרת כותרות ברירת מחדל (שיטה ישנה)
     */
    setDefaultHeaders(type) {
        const thead = document.getElementById('tableHeaders');
        if (!thead) return;
        
        const headers = {
            // cemetery: `<th>מזהה</th><th>שם</th><th>קוד</th><th>סטטוס</th><th>נוצר בתאריך</th><th>פעולות</th>`,
            // block: `<th>מזהה</th><th>שם גוש</th><th>קוד</th><th>סטטוס</th><th>נוצר בתאריך</th><th>פעולות</th>`,
            // plot: `<th>מזהה</th><th>שם חלקה</th><th>קוד</th><th>סטטוס</th><th>נוצר בתאריך</th><th>פעולות</th>`,
            // areaGrave: `<th>מזהה</th><th>שם אחוזת קבר</th><th>סוג</th><th>סטטוס</th><th>נוצר בתאריך</th><th>פעולות</th>`,
            // grave: `<th>מזהה</th><th>שם הנפטר</th><th>תאריך פטירה</th><th>מיקום</th><th>סטטוס</th><th>פעולות</th>`
        };
        
        if (headers[type]) {
            thead.innerHTML = headers[type];
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

console.log('✅ DashboardCleaner loaded - STEP A: Supports both old and new methods');