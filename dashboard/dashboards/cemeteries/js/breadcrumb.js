/**
 * Improved Breadcrumb Management System
 * File: /dashboard/dashboards/cemeteries/js/breadcrumb.js
 * 
 * מערכת breadcrumb משופרת שמתחילה מהרמה הנוכחית בהיררכיה
 * ולא מציגה "דף ראשי" מיותר
 */

const BreadcrumbManager = {
    
    // Configuration
    config: {
        separator: '›',
        containerSelector: '#breadcrumb'
    },
    
    // Hierarchy structure
    hierarchy: {
        cemetery: {
            name: 'בית עלמין',
            icon: '🏛️',
            plural: 'בתי עלמין',
            level: 0
        },
        block: {
            name: 'גוש',
            icon: '📦',
            parent: 'cemetery',
            plural: 'גושים',
            level: 1
        },
        plot: {
            name: 'חלקה',
            icon: '📋',
            parent: 'block',
            plural: 'חלקות',
            level: 2
        },
        areaGrave: {
            name: 'אחוזת קבר',
            icon: '🏘️',
            parent: 'plot',
            plural: 'אחוזות קבר',
            level: 3
        },
        grave: {
            name: 'קבר',
            icon: '⚰️',
            parent: 'areaGrave',
            plural: 'קברים',
            level: 4
        }
    },
    
    // Current breadcrumb path
    currentPath: [],
    
    // Starting level (where the user started navigating)
    startingLevel: null,
    
    /**
     * Initialize breadcrumb
     */
    init() {
        this.reset();
        this.bindEventHandlers();
    },
    
    /**
     * Reset breadcrumb to empty
     */
    reset() {
        this.currentPath = [];
        this.startingLevel = null;
        this.render();
    },
    
    /**
     * Update breadcrumb path based on selected items
     * @param {Object} items - Selected items in hierarchy
     * @param {string} startLevel - Optional: force starting level
     */
    update(items, startLevel = null) {
        this.currentPath = [];
        
        // אם אין פריטים בכלל, הצג רק את הרמה הנוכחית
        if (!items || Object.keys(items).length === 0) {
            // מצא את הרמה הנוכחית מה-window.currentType
            if (window.currentType) {
                this.setStartingLevel(window.currentType);
                this.currentPath = [{
                    type: 'level',
                    name: this.hierarchy[window.currentType].plural,
                    icon: this.hierarchy[window.currentType].icon,
                    clickable: false
                }];
            }
            this.render();
            return;
        }
        
        // מצא את הרמה הגבוהה ביותר (הראשונה) בפריטים
        const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        let firstLevel = null;
        
        for (let type of order) {
            if (items[type]) {
                if (!firstLevel) {
                    firstLevel = type;
                    this.setStartingLevel(type);
                }
            }
        }
        
        // אם יש startLevel מפורש, השתמש בו
        if (startLevel && this.hierarchy[startLevel]) {
            this.setStartingLevel(startLevel);
        }
        
        // בנה את הנתיב החל מהרמה הראשונה
        let pathStarted = false;
        
        for (let type of order) {
            // התחל רק מהרמה שבה יש פריט או מרמת ההתחלה
            if (!pathStarted) {
                if (type === this.startingLevel || items[type]) {
                    pathStarted = true;
                    
                    // הוסף את שם הרמה הרבים כפריט ראשון (אם זו נקודת ההתחלה)
                    if (type === this.startingLevel && !items[type]) {
                        this.currentPath.push({
                            type: 'level',
                            levelType: type,
                            name: this.hierarchy[type].plural,
                            icon: this.hierarchy[type].icon,
                            clickable: true
                        });
                    } else if (type === this.startingLevel && items[type]) {
                        // אם יש פריט ברמת ההתחלה, הוסף קודם את הרמה
                        this.currentPath.push({
                            type: 'level',
                            levelType: type,
                            name: this.hierarchy[type].plural,
                            icon: this.hierarchy[type].icon,
                            clickable: true
                        });
                    }
                }
            }
            
            // הוסף את הפריט הספציפי אם קיים
            if (items[type] && pathStarted) {
                this.currentPath.push({
                    type: type,
                    id: items[type].id,
                    name: items[type].name,
                    icon: this.hierarchy[type].icon,
                    clickable: true,
                    data: items[type]
                });
            }
        }
        
        // סמן את הפריט האחרון כלא ניתן ללחיצה
        if (this.currentPath.length > 0) {
            this.currentPath[this.currentPath.length - 1].clickable = false;
        }
        
        this.render();
    },
    
    /**
     * Set the starting level for breadcrumb
     */
    setStartingLevel(level) {
        if (!this.startingLevel) {
            this.startingLevel = level;
            console.log('📍 Breadcrumb starting level set to:', level);
        }
    },
    
    /**
     * Render breadcrumb HTML
     */
    render() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        // אם אין נתיב, אל תציג כלום
        if (this.currentPath.length === 0) {
            container.innerHTML = '';
            return;
        }
        
        let html = '';
        
        this.currentPath.forEach((item, index) => {
            // הוסף מפריד (חוץ מהפריט הראשון)
            if (index > 0) {
                html += `<span class="breadcrumb-separator">${this.config.separator}</span>`;
            }
            
            // רנדר את הפריט
            if (item.clickable) {
                html += `
                    <a href="#" 
                       class="breadcrumb-item breadcrumb-clickable"
                       data-type="${item.type}"
                       data-level-type="${item.levelType || ''}"
                       data-id="${item.id || ''}"
                       data-index="${index}">
                        <span class="breadcrumb-icon">${item.icon}</span>
                        <span class="breadcrumb-text">${item.name}</span>
                    </a>
                `;
            } else {
                // פריט נוכחי (לא ניתן ללחיצה)
                html += `
                    <span class="breadcrumb-item breadcrumb-current">
                        <span class="breadcrumb-icon">${item.icon}</span>
                        <span class="breadcrumb-text">${item.name}</span>
                    </span>
                `;
            }
        });
        
        container.innerHTML = html;
        this.attachEventHandlers();
    },
    
    /**
     * Attach click event handlers
     */
    attachEventHandlers() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        container.querySelectorAll('.breadcrumb-clickable').forEach(item => {
            item.removeEventListener('click', this.handleItemClick);
            item.addEventListener('click', this.handleItemClick.bind(this));
        });
    },
    
    /**
     * Handle breadcrumb item click
     */
    handleItemClick(e) {
        e.preventDefault();
        
        const element = e.currentTarget;
        const type = element.dataset.type;
        const levelType = element.dataset.levelType;
        const id = element.dataset.id;
        const index = parseInt(element.dataset.index);
        
        // ניווט לרמה (הצגת כל הפריטים מאותה רמה)
        if (type === 'level') {
            this.navigateToLevel(levelType || this.currentPath[index].levelType);
            return;
        }
        
        // ניווט לפריט ספציפי
        this.navigateToItem(type, id, index);
    },
    
    /**
     * Navigate to a hierarchy level (show all items)
     */
    navigateToLevel(levelType) {
        console.log('🔄 Navigating to level:', levelType);
        
        // נקה את הבחירות מרמה זו ומטה
        if (window.selectedItems) {
            const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
            const levelIndex = order.indexOf(levelType);
            
            for (let i = levelIndex; i < order.length; i++) {
                delete window.selectedItems[order[i]];
            }
        }
        
        // קרא לפונקציה המתאימה
        switch(levelType) {
            case 'cemetery':
                if (typeof loadAllCemeteries === 'function') {
                    loadAllCemeteries();
                }
                break;
            case 'block':
                if (typeof loadAllBlocks === 'function') {
                    loadAllBlocks();
                }
                break;
            case 'plot':
                if (typeof loadAllPlots === 'function') {
                    loadAllPlots();
                }
                break;
            case 'areaGrave':
                if (typeof loadAllAreaGraves === 'function') {
                    loadAllAreaGraves();
                }
                break;
            case 'grave':
                if (typeof loadAllGraves === 'function') {
                    loadAllGraves();
                }
                break;
        }
    },
    
    /**
     * Navigate to specific item
     */
    navigateToItem(type, id, index) {
        console.log('🔄 Navigating to item:', type, id);
        
        // חתוך את הנתיב עד הפריט שנלחץ
        this.currentPath = this.currentPath.slice(0, index + 1);
        
        // עדכן את הבחירות הגלובליות
        if (window.selectedItems) {
            const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
            let foundCurrent = false;
            
            for (let itemType of order) {
                if (itemType === type) {
                    foundCurrent = true;
                } else if (foundCurrent) {
                    delete window.selectedItems[itemType];
                }
            }
        }
        
        // קרא לפונקציית הניווט המתאימה
        const item = this.currentPath[index].data;
        if (item) {
            this.openItem(type, item);
        }
    },
    
    /**
     * Open specific item type
     */
    openItem(type, item) {
        console.log('📂 Opening item:', type, item);
        
        switch(type) {
            case 'cemetery':
                if (typeof openCemetery === 'function') {
                    openCemetery(item.id, item.name);
                }
                break;
            case 'block':
                if (typeof openBlock === 'function') {
                    openBlock(item.id, item.name);
                }
                break;
            case 'plot':
                if (typeof openPlot === 'function') {
                    openPlot(item.id, item.name);
                }
                break;
            case 'areaGrave':
                if (typeof openAreaGrave === 'function') {
                    openAreaGrave(item.id, item.name);
                }
                break;
            case 'grave':
                if (typeof viewGraveDetails === 'function') {
                    viewGraveDetails(item.id);
                }
                break;
        }
    },
    
    /**
     * Get current breadcrumb as string
     */
    toString() {
        return this.currentPath
            .map(item => item.name)
            .join(' ' + this.config.separator + ' ');
    },
    
    /**
     * Bind global event handlers
     */
    bindEventHandlers() {
        // שמור את הנתיב לפני יציאה
        window.addEventListener('beforeunload', () => {
            if (window.selectedItems) {
                sessionStorage.setItem('breadcrumbPath', JSON.stringify(window.selectedItems));
                sessionStorage.setItem('breadcrumbStartLevel', this.startingLevel);
            }
        });
    }
};

// ==========================================
// פונקציה גלובלית לתאימות אחורה
// ==========================================

function updateBreadcrumb(pathOrItems) {
    if (typeof pathOrItems === 'object' && pathOrItems !== null) {
        BreadcrumbManager.update(pathOrItems);
    } else if (!pathOrItems || pathOrItems === 'ראשי') {
        BreadcrumbManager.reset();
    } else {
        // עבור נתיבי string, נסה להשתמש ב-selectedItems
        if (window.selectedItems && Object.keys(window.selectedItems).length > 0) {
            BreadcrumbManager.update(window.selectedItems);
        }
    }
}

// ==========================================
// אתחול בטעינת הדף
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing improved breadcrumb system');
    BreadcrumbManager.init();
    
    // שחזר נתיב שמור אם קיים
    const savedPath = sessionStorage.getItem('breadcrumbPath');
    const savedStartLevel = sessionStorage.getItem('breadcrumbStartLevel');
    
    if (savedPath) {
        try {
            const items = JSON.parse(savedPath);
            window.selectedItems = items;
            
            if (savedStartLevel) {
                BreadcrumbManager.startingLevel = savedStartLevel;
            }
            
            BreadcrumbManager.update(items);
            console.log('✅ Breadcrumb restored from session');
        } catch (e) {
            console.error('Failed to restore breadcrumb:', e);
        }
    }
});

// ==========================================
// ייצוא לשימוש גלובלי
// ==========================================

window.BreadcrumbManager = BreadcrumbManager;
window.updateBreadcrumb = updateBreadcrumb;

// ==========================================
// דוגמאות שימוש
// ==========================================

/**
 * דוגמאות לשימוש:
 * 
 * 1. כשנכנסים לרשימת בתי עלמין:
 *    BreadcrumbManager.update({}, 'cemetery');
 *    // יציג: בתי עלמין >
 * 
 * 2. כשבוחרים בית עלמין ספציפי:
 *    BreadcrumbManager.update({ cemetery: {id: 1, name: 'בית עלמין א'} });
 *    // יציג: בתי עלמין > בית עלמין א
 * 
 * 3. כשנכנסים ישירות לרשימת גושים:
 *    BreadcrumbManager.update({}, 'block');
 *    // יציג: גושים >
 * 
 * 4. כשבוחרים גוש ספציפי (מתוך רשימת גושים):
 *    BreadcrumbManager.update({ block: {id: 5, name: 'גוש א'} });
 *    // יציג: גושים > גוש א
 * 
 * 5. המשך ניווט עמוק:
 *    BreadcrumbManager.update({
 *        cemetery: {id: 1, name: 'בית עלמין א'},
 *        block: {id: 5, name: 'גוש א'},
 *        plot: {id: 10, name: 'חלקה א'}
 *    });
 *    // יציג: בתי עלמין > בית עלמין א > גוש א > חלקה א
 */