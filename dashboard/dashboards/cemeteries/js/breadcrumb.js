/**
 * Fixed Breadcrumb Management System
 * File: /dashboard/dashboards/cemeteries/js/breadcrumb-fixed.js
 * 
 * גרסה מתוקנת ללא בעיות של sessionStorage וללא עדכונים כפולים
 */

const BreadcrumbManager = {
    
    // Configuration
    config: {
        separator: '›',
        containerSelector: '#breadcrumb',
        debug: true // להצגת לוגים לדיבוג
    },
    
    // Hierarchy structure
    hierarchy: {
        cemetery: {
            name: 'בית עלמין',
            icon: '🏛️',
            plural: 'בתי עלמין'
        },
        block: {
            name: 'גוש',
            icon: '📦',
            plural: 'גושים'
        },
        plot: {
            name: 'חלקה',
            icon: '📋',
            plural: 'חלקות'
        },
        areaGrave: {
            name: 'אחוזת קבר',
            icon: '🏘️',
            plural: 'אחוזות קבר'
        },
        grave: {
            name: 'קבר',
            icon: '⚰️',
            plural: 'קברים'
        },
        customer: {
            name: 'לקוח',
            icon: '👤',
            plural: 'לקוחות'
        },
        purchase: {
            name: 'רכישה',
            icon: '🛒',
            plural: 'רכישות'
        },
        burial: {
            name: 'קבורה',
            icon: '⚰️',
            plural: 'קבורות'
        },
        payment: {
            name: 'תשלום',
            icon: '💰',
            plural: 'תשלומים'
        },
        residency: {
            name: 'הגדרת תושבות',
            icon: '🏠',
            plural: 'הגדרות תושבות'
        },
        country: {
            name: 'מדינה',
            icon: '🌍',
            plural: 'מדינות'
        },
        city: {
            name: 'עיר',
            icon: '🏙️',
            plural: 'ערים'
        }
    },
    
    // Current breadcrumb path
    currentPath: [],
    
    // Initialization flag
    isInitialized: false,
    
    /**
     * Initialize breadcrumb
     */
    init() {
        if (this.isInitialized) {
            return;
        }
        
        this.log('🚀 Initializing BreadcrumbManager');
        this.reset();
        this.isInitialized = true;
        
        // אל תטען נתיב שמור! זה גורם לבעיות
        // sessionStorage.removeItem('breadcrumbPath'); // נקה נתונים ישנים
    },
    
    /**
     * Logger for debugging
     */
    log(message, data = null) {
        if (this.config.debug) {
            if (data) {
            } else {
            }
        }
    },
    
    /**
     * Reset breadcrumb completely
     */
    reset() {
        this.log('🔄 Resetting breadcrumb');
        this.currentPath = [];
        this.render();
    },
    
    /**
     * Smart update based on context
     * @param {Object} items - Selected items (optional)
     * @param {string} forceType - Force display of specific type
     */
    update(items = null, forceType = null) {
        this.log('📝 Update called with:', { items, forceType });
        
        // מנע עדכונים כפולים
        if (this._updateTimeout) {
            clearTimeout(this._updateTimeout);
        }
        
        this._updateTimeout = setTimeout(() => {
            this._performUpdate(items, forceType);
        }, 10); // delay קטן למניעת עדכונים כפולים
    },
    
    /**
     * Perform the actual update
     */
    _performUpdate(items, forceType) {
        this.currentPath = [];
        
        // אם forceType מוגדר, הצג רק את הרמה
        if (forceType && this.hierarchy[forceType]) {
            this.log(`🎯 Forcing display of level: ${forceType}`);
            this.currentPath = [{
                type: 'level',
                levelType: forceType,
                name: this.hierarchy[forceType].plural,
                icon: this.hierarchy[forceType].icon,
                clickable: false
            }];
            this.render();
            return;
        }
        
        // אם אין items, נסה להבין מה להציג מ-window.currentType
        if (!items || Object.keys(items).length === 0) {
            if (window.currentType && this.hierarchy[window.currentType]) {
                this.log(`📍 No items, showing current type: ${window.currentType}`);
                this.currentPath = [{
                    type: 'level',
                    levelType: window.currentType,
                    name: this.hierarchy[window.currentType].plural,
                    icon: this.hierarchy[window.currentType].icon,
                    clickable: false
                }];
            }
            this.render();
            return;
        }
        
        // בנה נתיב מה-items
        this._buildPathFromItems(items);
        this.render();
    },
    
    /**
     * Build path from selected items
     */
    _buildPathFromItems(items) {
        // סדר היררכי לישויות עם parent
        const hierarchyOrder = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        // ישויות עצמאיות (ללא parent)
        const standaloneTypes = ['customer', 'purchase', 'burial', 'payment', 'residency', 'country', 'city'];

        let firstFound = null;
        let lastFound = null;

        // בדוק קודם ישויות עצמאיות
        for (let type of standaloneTypes) {
            if (items[type] && this.hierarchy[type]) {
                this.currentPath.push({
                    type: 'level',
                    levelType: type,
                    name: this.hierarchy[type].plural,
                    icon: this.hierarchy[type].icon,
                    clickable: false
                });
                this.log(`📍 Standalone entity: ${type}`);
                return;
            }
        }

        // מצא את הרמה הראשונה והאחרונה בהיררכיה
        for (let type of hierarchyOrder) {
            if (items[type]) {
                if (!firstFound) firstFound = type;
                lastFound = type;
            }
        }

        if (!firstFound) {
            this.log('⚠️ No valid items found');
            return;
        }
        
        this.log(`🔍 Building path from ${firstFound} to ${lastFound}`);
        
        // הוסף את שם הרמה ברבים בהתחלה
        this.currentPath.push({
            type: 'level',
            levelType: firstFound,
            name: this.hierarchy[firstFound].plural,
            icon: this.hierarchy[firstFound].icon,
            clickable: true
        });
        
        // הוסף את הפריטים שנבחרו
        let pathStarted = false;
        for (let type of hierarchyOrder) {
            if (type === firstFound) pathStarted = true;
            
            if (pathStarted && items[type]) {
                this.currentPath.push({
                    type: type,
                    id: items[type].id,
                    name: items[type].name,
                    icon: this.hierarchy[type].icon,
                    clickable: true,
                    data: items[type]
                });
                
                if (type === lastFound) break;
            }
        }
        
        // הפריט האחרון לא ניתן ללחיצה
        if (this.currentPath.length > 0) {
            this.currentPath[this.currentPath.length - 1].clickable = false;
        }
    },
    
    /**
     * Render breadcrumb to DOM
     */
    render() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) {
            this.log('⚠️ Container not found:', this.config.containerSelector);
            return;
        }
        
        // אם אין נתיב, נקה והצג
        if (this.currentPath.length === 0) {
            container.innerHTML = '';
            this.log('✓ Breadcrumb cleared (empty path)');
            return;
        }
        
        let html = '';
        
        this.currentPath.forEach((item, index) => {
            // מפריד (לא לפני הראשון)
            if (index > 0) {
                html += `<span class="breadcrumb-separator">${this.config.separator}</span>`;
            }
            
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
        
        this.log('✓ Breadcrumb rendered:', this.currentPath.map(p => p.name).join(' > '));
    },
    
    /**
     * Attach click handlers
     */
    attachEventHandlers() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        container.querySelectorAll('.breadcrumb-clickable').forEach(item => {
            item.onclick = (e) => this.handleItemClick(e);
        });
    },
    
    /**
     * Handle breadcrumb click
     */
    handleItemClick(e) {
        e.preventDefault();
        
        const element = e.currentTarget;
        const type = element.dataset.type;
        const levelType = element.dataset.levelType;
        const id = element.dataset.id;
        const index = parseInt(element.dataset.index);
        
        this.log('👆 Clicked:', { type, levelType, id, index });
        
        if (type === 'level') {
            this.navigateToLevel(levelType);
        } else {
            this.navigateToItem(type, id, index);
        }
    },
    
    /**
     * Navigate to hierarchy level
     */
    navigateToLevel(levelType) {
        this.log(`🚶 Navigating to level: ${levelType}`);
        
        // נקה את הבחירות מרמה זו ומטה
        if (window.selectedItems) {
            const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
            const startIndex = order.indexOf(levelType);
            
            for (let i = startIndex; i < order.length; i++) {
                delete window.selectedItems[order[i]];
            }
        }
        
        // קרא לפונקציה המתאימה
        const functions = {
            cemetery: 'loadCemeteries',
            block: 'loadBlocks',
            plot: 'loadPlots',
            areaGrave: 'loadAreaGraves',
            grave: 'loadGraves'
        };
        
        const funcName = functions[levelType];
        if (funcName && typeof window[funcName] === 'function') {
            this.log(`✓ Calling ${funcName}`);
            window[funcName]();
        } else {
            this.log(`⚠️ Function not found: ${funcName}`);
        }
    },
    
    /**
     * Navigate to specific item
     */
    navigateToItem(type, id, index) {
        this.log(`🚶 Navigating to item: ${type} #${id}`);
        
        // חתוך את הנתיב
        this.currentPath = this.currentPath.slice(0, index + 1);
        
        // נקה בחירות מתחת
        if (window.selectedItems) {
            const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
            const startIndex = order.indexOf(type) + 1;
            
            for (let i = startIndex; i < order.length; i++) {
                delete window.selectedItems[order[i]];
            }
        }
        
        // קרא לפונקציה המתאימה
        const item = this.currentPath[index].data;
        if (item) {
            this.openItem(type, item);
        }
    },
    
    /**
     * Open specific item
     */
    openItem(type, item) {
        this.log(`📂 Opening ${type}:`, item);
        
        const functions = {
            cemetery: 'openCemetery',
            block: 'openBlock',
            plot: 'openPlot',
            areaGrave: 'openAreaGrave',
            grave: 'viewGraveDetails'
        };
        
        const funcName = functions[type];
        if (funcName && typeof window[funcName] === 'function') {
            window[funcName](item.id, item.name);
        } else {
            this.log(`⚠️ Function not found: ${funcName}`);
        }
    }
};

// ==========================================
// פונקציה גלובלית לתאימות אחורה
// ==========================================

window.updateBreadcrumb = function(pathOrItems) {
    // מנע קריאות כפולות
    if (window._breadcrumbUpdateLock) {
        return;
    }
    
    window._breadcrumbUpdateLock = true;
    setTimeout(() => {
        window._breadcrumbUpdateLock = false;
    }, 50);
    
    if (typeof pathOrItems === 'object' && pathOrItems !== null) {
        BreadcrumbManager.update(pathOrItems);
    } else if (!pathOrItems || pathOrItems === 'ראשי') {
        BreadcrumbManager.reset();
    } else {
        BreadcrumbManager.update(window.selectedItems);
    }
};

// ==========================================
// אתחול בטעינת הדף
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    
    // נקה נתונים ישנים מ-sessionStorage
    sessionStorage.removeItem('breadcrumbPath');
    sessionStorage.removeItem('breadcrumbStartLevel');
    
    // אתחל את המערכת
    BreadcrumbManager.init();
    
});

// ייצוא גלובלי
window.BreadcrumbManager = BreadcrumbManager;