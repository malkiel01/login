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
        
        // 3. ⭐ ניקוי TableManager אם עוברים ממצב לקוחות
        if (window.currentType === 'customer' && settings.targetLevel !== 'customer') {
            this.destroyTableManager();
        }
        
        // 4. ניקוי טבלה
        if (!this.isTableManagerActive() || settings.targetLevel !== 'customer') {
            this.clearTable();
        }
        
        // 5. ניקוי sidebar
        if (!settings.keepSidebar && settings.targetLevel) {
            this.clearSidebarForLevel(settings.targetLevel);
        }
        
        // 6. ניקוי/עדכון breadcrumb
        if (!settings.keepBreadcrumb) {
            if (settings.targetLevel) {
                this.updateBreadcrumbForLevel(settings.targetLevel);
            } else {
                this.resetBreadcrumb();
            }
        }
        
        // 7. ניקוי הודעות
        this.clearMessages();
        
        // 8. ניקוי חיפוש
        this.clearSearch();
        
        // 9. ניקוי מודלים פתוחים
        this.closeModals();
        
        console.log('✅ Dashboard cleaned successfully');
    },
    
    /**
     * ⭐ NEW: בדיקה אם TableManager פעיל
     */
    isTableManagerActive() {
        // בדוק אם יש wrapper של TableManager
        const wrapper = document.querySelector('.table-wrapper[data-fixed-width="true"]');
        
        // בדוק אם הוא מוצג
        const isVisible = wrapper && 
                         window.getComputedStyle(wrapper).display !== 'none' &&
                         wrapper.offsetParent !== null;
        
        // בדוק אם אנחנו במצב לקוחות
        const isCustomerMode = window.currentType === 'customer';
        
        return isVisible && isCustomerMode;
    },
    
    /**
     * ⭐ NEW: הסתרת TableManager (במקום מחיקה)
     */
    hideTableManager() {
        const wrapper = document.querySelector('.table-wrapper[data-fixed-width="true"]');
        if (wrapper) {
            wrapper.style.display = 'none';
            console.log('  ✓ TableManager hidden');
        }
        
        // הסתר גם את סקשן החיפוש
        const searchSection = document.getElementById('customerSearchSection');
        if (searchSection) {
            searchSection.style.display = 'none';
        }
        
        // הצג את הטבלה הרגילה
        const mainTable = document.getElementById('mainTable');
        if (mainTable) {
            mainTable.style.display = 'table';
            console.log('  ✓ Main table shown');
        }
        
        // ⭐ NEW: הסתר את כל ה-table-container אם רוצים
        // (רק אם אין בו תוכן אחר)
        const container = document.querySelector('.table-container');
        if (container && !this.hasVisibleContent(container)) {
            container.style.display = 'none';
            console.log('  ✓ table-container hidden (empty)');
        }
    },
    
    /**
     * ⭐ NEW: בדיקה אם יש תוכן גלוי ב-container
     */
    hasVisibleContent(container) {
        const mainTable = container.querySelector('#mainTable');
        if (mainTable && window.getComputedStyle(mainTable).display !== 'none') {
            return true;
        }
        
        const wrapper = container.querySelector('.table-wrapper[data-fixed-width="true"]');
        if (wrapper && window.getComputedStyle(wrapper).display !== 'none') {
            return true;
        }
        
        return false;
    },
    
    /**
     * ⭐ NEW: הצגת TableManager
     */
    showTableManager() {
        // הצג את ה-container אם הוא מוסתר
        const container = document.querySelector('.table-container');
        if (container && window.getComputedStyle(container).display === 'none') {
            container.style.display = 'block';
            console.log('  ✓ table-container shown');
        }
        
        const wrapper = document.querySelector('.table-wrapper[data-fixed-width="true"]');
        if (wrapper) {
            wrapper.style.display = 'flex';
            console.log('  ✓ TableManager shown');
        }
        
        // הצג גם את סקשן החיפוש
        const searchSection = document.getElementById('customerSearchSection');
        if (searchSection) {
            searchSection.style.display = 'block';
        }
        
        // הסתר את הטבלה הרגילה
        const mainTable = document.getElementById('mainTable');
        if (mainTable && !mainTable.closest('.table-wrapper')) {
            mainTable.style.display = 'none';
            console.log('  ✓ Main table hidden');
        }
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
     * ניקוי הטבלה - ⭐ FIX: לא נוגע ב-TableManager
     */
    clearTable() {
        // אם TableManager פעיל, הסתר אותו במקום למחוק
        if (this.isTableManagerActive()) {
            this.hideTableManager();
            return;
        }
        
        // וודא שהטבלה הרגילה מוצגת
        const container = document.querySelector('.table-container');
        if (container) {
            container.style.display = 'block';
        }
        
        const mainTable = document.getElementById('mainTable');
        if (mainTable) {
            mainTable.style.display = 'table';
        }
        
        const tbody = document.getElementById('tableBody');
        const thead = document.getElementById('tableHeaders');
        
        if (tbody) {
            tbody.innerHTML = '';
            // הסר מאפיינים מיוחדים
            tbody.removeAttribute('data-customer-view');
            tbody.removeAttribute('data-current-type');
            console.log('  ✓ Table body cleared');
        }
        
        if (thead) {
            // שמור כותרות ברירת מחדל או נקה לגמרי
            if (window.currentType && window.currentType !== 'customer') {
                this.setDefaultHeaders(window.currentType);
            } else {
                thead.innerHTML = '';
            }
            console.log('  ✓ Table headers reset');
        }
    },
    
    /**
     * הגדרת כותרות ברירת מחדל לטבלה
     */
    setDefaultHeaders(type) {
        const thead = document.getElementById('tableHeaders');
        if (!thead) return;
        
        const headers = {
            cemetery: `
                <th>מזהה</th>
                <th>שם</th>
                <th>קוד</th>
                <th>סטטוס</th>
                <th>נוצר בתאריך</th>
                <th>פעולות</th>
            `,
            block: `
                <th>מזהה</th>
                <th>שם גוש</th>
                <th>קוד</th>
                <th>סטטוס</th>
                <th>נוצר בתאריך</th>
                <th>פעולות</th>
            `,
            plot: `
                <th>מזהה</th>
                <th>שם חלקה</th>
                <th>קוד</th>
                <th>סטטוס</th>
                <th>נוצר בתאריך</th>
                <th>פעולות</th>
            `,
            areaGrave: `
                <th>מזהה</th>
                <th>שם אחוזת קבר</th>
                <th>סוג</th>
                <th>סטטוס</th>
                <th>נוצר בתאריך</th>
                <th>פעולות</th>
            `,
            grave: `
                <th>מזהה</th>
                <th>שם הנפטר</th>
                <th>תאריך פטירה</th>
                <th>מיקום</th>
                <th>סטטוס</th>
                <th>פעולות</th>
            `
        };
        
        if (headers[type]) {
            thead.innerHTML = headers[type];
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
        
        // הסתר את חיפוש הלקוחות אם לא במצב לקוחות
        if (window.currentType !== 'customer') {
            const customerSearchSection = document.getElementById('customerSearchSection');
            if (customerSearchSection) {
                customerSearchSection.style.display = 'none';
                console.log('  ✓ Customer search hidden');
            }
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
     * ⭐ NEW: איפוס מלא - עם תמיכה ב-TableManager
     */
    fullReset() {
        console.log('🔄 Performing full dashboard reset...');
        
        // הסתר TableManager אם קיים
        if (this.isTableManagerActive()) {
            this.hideTableManager();
        }
        
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
    
    /**
     * ניקוי לפני מעבר בין רמות
     */
    prepareTransition(fromType, toType, keepParentSelection = false) {
        const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        const fromIndex = hierarchy.indexOf(fromType);
        const toIndex = hierarchy.indexOf(toType);
        
        // אם עוברים ללקוחות, הסתר את הטבלה הרגילה
        if (toType === 'customer') {
            // הסתר הטבלה הרגילה אבל אל תמחק
            const mainTable = document.getElementById('mainTable');
            if (mainTable) {
                mainTable.style.display = 'none';
            }
            return;
        }
        
        // אם יוצאים מלקוחות, הסתר את TableManager
        if (fromType === 'customer') {
            this.hideTableManager();
            // הצג את הטבלה הרגילה
            const mainTable = document.getElementById('mainTable');
            if (mainTable) {
                mainTable.style.display = 'table';
            }
        }
        
        // קבע מה לנקות לפי כיוון המעבר
        if (toIndex < fromIndex) {
            // חוזרים אחורה בהיררכיה
            this.clear({
                targetLevel: toType,
                keepBreadcrumb: false,
                keepSidebar: keepParentSelection
            });
        } else if (toIndex > fromIndex) {
            // מתקדמים בהיררכיה
            this.clear({
                targetLevel: fromType,
                keepBreadcrumb: true,
                keepSidebar: true,
                keepCard: false
            });
        } else {
            // נשארים באותה רמה
            this.clear({
                targetLevel: toType,
                keepBreadcrumb: true,
                keepSidebar: true
            });
        }
    }
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

console.log('✅ DashboardCleaner loaded with TableManager protection');