/**
 * מערכת ניקוי מרכזית לדשבורד בתי עלמין
 * File: /dashboard/dashboards/cemeteries/js/clearDashboard.js
 * 
 * פונקציה זו מחליפה את כל הניקויים המפוזרים במערכת
 * ומספקת ניקוי אחיד ועקבי בכל מעבר מסך
 */

const DashboardCleaner = {
    
    /**
     * פונקציית הניקוי הראשית
     * @param {Object} options - אפשרויות ניקוי
     * @param {string} options.targetLevel - הרמה אליה עוברים (cemetery/block/plot/areaGrave/grave)
     * @param {boolean} options.keepBreadcrumb - האם לשמור את ה-breadcrumb
     * @param {boolean} options.keepSidebar - האם לשמור את הסידבר
     * @param {boolean} options.keepCard - האם לשמור את הכרטיס
     * @param {boolean} options.fullReset - האם לבצע איפוס מלא
     */
    clear(options = {}) {
        const defaults = {
            targetLevel: null,
            keepBreadcrumb: false,
            keepSidebar: false,
            keepCard: false,
            fullReset: false
        };
        
        const settings = { ...defaults, ...options };
        
        console.log('🧹 Cleaning dashboard with settings:', settings);
        
        // 1. ניקוי מלא אם נדרש
        if (settings.fullReset) {
            this.fullReset();
            return;
        }
        
        // 2. ניקוי כרטיסים
        if (!settings.keepCard) {
            this.clearCards();
        }
        
        // 3. ניקוי טבלה
        this.clearTable();
        
        // 4. ניקוי סידבר
        if (!settings.keepSidebar) {
            if (settings.targetLevel) {
                this.clearSidebarForLevel(settings.targetLevel);
            } else {
                this.clearAllSidebar();
            }
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
    // clearCards2() {
    //     // ניקוי כרטיס יחיד
    //     const itemCard = document.getElementById('itemCard');
    //     if (itemCard) {
    //         itemCard.remove();
    //         console.log('  ✓ Item card removed');
    //     }
        
    //     // ניקוי כרטיסי היררכיה
    //     const hierarchyCards = document.querySelectorAll('.hierarchy-card');
    //     if (hierarchyCards.length > 0) {
    //         hierarchyCards.forEach(card => card.remove());
    //         console.log(`  ✓ ${hierarchyCards.length} hierarchy cards removed`);
    //     }
        
    //     // ניקוי כרטיסים נוספים (אם יש)
    //     const additionalCards = document.querySelectorAll('.card-container, .info-card, .detail-card');
    //     additionalCards.forEach(card => {
    //         if (!card.closest('.stats-grid')) { // אל תמחק כרטיסי סטטיסטיקה
    //             card.remove();
    //         }
    //     });
    // },
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
     * ניקוי הטבלה
     */
    clearTable() {
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
            if (window.currentType) {
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
    clearSidebarForLevel(targetLevel) {
        const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        const targetIndex = hierarchy.indexOf(targetLevel);
        
        if (targetIndex === -1) {
            console.warn(`Unknown level: ${targetLevel}`);
            return;
        }
        
        // הסר active מכל הכותרות
        document.querySelectorAll('.hierarchy-header').forEach((header, index) => {
            header.classList.remove('active');
        });
        
        // הוסף active לרמה הנוכחית
        const headers = document.querySelectorAll('.hierarchy-header');
        if (headers[targetIndex]) {
            headers[targetIndex].classList.add('active');
        }
        
        // נקה רק את הרמות מתחת לרמה הנוכחית
        for (let i = targetIndex; i < hierarchy.length; i++) {
            const container = document.getElementById(`${hierarchy[i]}SelectedItem`);
            if (container) {
                container.innerHTML = '';
                container.style.display = 'none';
            }
        }
        
        console.log(`  ✓ Sidebar cleared below level: ${targetLevel}`);
    },
    
    /**
     * איפוס breadcrumb
     */
    resetBreadcrumb() {
        if (window.BreadcrumbManager && typeof window.BreadcrumbManager.reset === 'function') {
            window.BreadcrumbManager.reset();
            console.log('  ✓ Breadcrumb reset');
        } else if (typeof window.updateBreadcrumb === 'function') {
            window.updateBreadcrumb({});
            console.log('  ✓ Breadcrumb updated (legacy)');
        }
    },
    
    /**
     * עדכון breadcrumb לפי רמה
     */
    updateBreadcrumbForLevel(targetLevel) {
        if (window.selectedItems && typeof window.updateBreadcrumb === 'function') {
            // בניית אובייקט הבחירות עד הרמה הנוכחית
            const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
            const targetIndex = hierarchy.indexOf(targetLevel);
            const newSelection = {};
            
            for (let i = 0; i < targetIndex; i++) {
                if (window.selectedItems[hierarchy[i]]) {
                    newSelection[hierarchy[i]] = window.selectedItems[hierarchy[i]];
                }
            }
            
            window.updateBreadcrumb(newSelection);
            console.log('  ✓ Breadcrumb updated for level:', targetLevel);
        }
    },
    
    /**
     * ניקוי הודעות
     */
    clearMessages() {
        // ניקוי toast messages
        const toasts = document.querySelectorAll('.toast, .alert-toast, .notification-toast');
        toasts.forEach(toast => toast.remove());
        
        // ניקוי inline messages
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => alert.remove());
        
        if (toasts.length || alerts.length) {
            console.log(`  ✓ ${toasts.length + alerts.length} messages cleared`);
        }
    },
    
    /**
     * ניקוי שדה חיפוש
     */
    clearSearch() {
        const searchInputs = document.querySelectorAll('#sidebarSearch, #quickSearch, .search-input');
        searchInputs.forEach(input => {
            if (input.value) {
                input.value = '';
                console.log('  ✓ Search field cleared');
            }
        });
        
        // ניקוי תוצאות חיפוש
        const searchResults = document.querySelectorAll('.search-results, .search-dropdown');
        searchResults.forEach(result => result.remove());
    },
    
    /**
     * סגירת מודלים פתוחים
     */
    closeModals() {
        // סגירת Bootstrap modals
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
        
        // הסרת backdrops
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // הסרת class מה-body
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        
        if (modals.length) {
            console.log(`  ✓ ${modals.length} modals closed`);
        }
    },
    
    /**
     * איפוס מלא של הדשבורד
     */
    fullReset() {
        console.log('🔄 Performing full dashboard reset...');
        
        // ניקוי כל האלמנטים
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
     * פונקציה נוחה לשימוש במעברים רגילים
     */
    prepareTransition(fromType, toType, keepParentSelection = false) {
        const hierarchy = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        const fromIndex = hierarchy.indexOf(fromType);
        const toIndex = hierarchy.indexOf(toType);
        
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

/**
 * פונקציה גלובלית לניקוי כללי
 * מחליפה את כל הקריאות הישנות
 */
window.clearDashboard = function(options) {
    DashboardCleaner.clear(options);
};

/**
 * תאימות אחורה - החלפת הפונקציות הישנות
 */
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

// ==========================================
// דוגמאות שימוש
// ==========================================

/**
 * דוגמאות לשימוש בפונקציית הניקוי החדשה:
 * 
 * 1. ניקוי מלא:
 *    DashboardCleaner.fullReset();
 * 
 * 2. מעבר לרשימת בתי עלמין:
 *    DashboardCleaner.clear({ targetLevel: 'cemetery' });
 * 
 * 3. פתיחת בית עלמין ספציפי:
 *    DashboardCleaner.clear({ 
 *        targetLevel: 'block',
 *        keepSidebar: true,
 *        keepBreadcrumb: true 
 *    });
 * 
 * 4. חזרה אחורה בהיררכיה:
 *    DashboardCleaner.prepareTransition('plot', 'block');
 * 
 * 5. ניקוי רק הכרטיסים:
 *    DashboardCleaner.clearCards();
 */

// ייצוא למודול אם נדרש
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardCleaner;
}

// הוסף לאובייקט window לזמינות גלובלית
window.DashboardCleaner = DashboardCleaner;