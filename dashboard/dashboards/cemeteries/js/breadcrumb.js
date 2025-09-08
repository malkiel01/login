/**
 * מערכת Breadcrumb מרכזית
 * קובץ: /dashboard/dashboards/cemeteries/js/breadcrumb.js
 */

// אובייקט ראשי לניהול ה-Breadcrumb
const BreadcrumbManager = {
    
    // הגדרות בסיסיות
    config: {
        homeUrl: '/dashboard/',
        homeTitle: 'דף ראשי',
        homeIcon: '🏠',
        separator: '›',
        containerSelector: '#breadcrumb'
    },
    
    // מבנה הנתיבים - ההיררכיה המלאה
    hierarchy: {
        cemetery: {
            name: 'בתי עלמין',
            icon: '🏛️',
            url: '/dashboard/dashboards/cemeteries/',
            plural: 'בתי עלמין'
        },
        block: {
            name: 'גוש',
            icon: '📦',
            parent: 'cemetery',
            plural: 'גושים'
        },
        plot: {
            name: 'חלקה',
            icon: '📋',
            parent: 'block',
            plural: 'חלקות'
        },
        areaGrave: {
            name: 'אחוזת קבר',
            icon: '🏘️',
            parent: 'plot',
            plural: 'אחוזות קבר'
        },
        grave: {
            name: 'קבר',
            icon: '⚰️',
            parent: 'areaGrave',
            plural: 'קברים'
        }
    },
    
    // הנתיב הנוכחי
    currentPath: [],
    
    /**
     * איפוס הנתיב
     */
    reset() {
        this.currentPath = [];
        this.render();
    },
    
    /**
     * הגדרת נתיב מלא
     * @param {Object} items - אובייקט עם כל הפריטים הנבחרים
     * דוגמה: { cemetery: {id: 1, name: 'בית עלמין א'}, block: {id: 2, name: 'גוש 1'} }
     */
    setPath(items) {
        this.currentPath = [];
        
        // הוסף את דף הבית תמיד
        this.currentPath.push({
            type: 'home',
            name: this.config.homeTitle,
            icon: this.config.homeIcon,
            url: this.config.homeUrl,
            clickable: true
        });
        
        // בנה את הנתיב לפי ההיררכיה
        const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        
        for (let type of order) {
            if (items[type]) {
                const hierarchyItem = this.hierarchy[type];
                
                // הוסף את השם של הרמה (למשל "בתי עלמין")
                if (type === 'cemetery') {
                    this.currentPath.push({
                        type: 'level',
                        name: hierarchyItem.plural,
                        icon: hierarchyItem.icon,
                        url: hierarchyItem.url,
                        clickable: true
                    });
                }
                
                // הוסף את הפריט הספציפי
                this.currentPath.push({
                    type: type,
                    id: items[type].id,
                    name: items[type].name,
                    icon: hierarchyItem.icon,
                    clickable: true,
                    item: items[type]
                });
            } else {
                // אם הגענו לפריט שלא קיים, נעצור
                break;
            }
        }
        
        // הפריט האחרון לא צריך להיות לחיץ
        if (this.currentPath.length > 0) {
            this.currentPath[this.currentPath.length - 1].clickable = false;
        }
        
        this.render();
    },
    
    /**
     * הוספת פריט בודד לנתיב
     * @param {string} type - סוג הפריט
     * @param {Object} item - הפריט עצמו
     */
    addItem(type, item) {
        const hierarchyItem = this.hierarchy[type];
        if (!hierarchyItem) return;
        
        this.currentPath.push({
            type: type,
            id: item.id,
            name: item.name,
            icon: hierarchyItem.icon,
            clickable: true,
            item: item
        });
        
        this.render();
    },
    
    /**
     * עיבוד והצגת ה-Breadcrumb
     */
    render() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        // אם אין נתיב, הצג רק דף ראשי
        if (this.currentPath.length === 0) {
            container.innerHTML = `
                <a href="${this.config.homeUrl}" class="breadcrumb-item breadcrumb-home">
                    <span class="breadcrumb-icon">${this.config.homeIcon}</span>
                    <span class="breadcrumb-text">${this.config.homeTitle}</span>
                </a>
            `;
            return;
        }
        
        // בנה את ה-HTML
        let html = '';
        
        this.currentPath.forEach((item, index) => {
            // הוסף מפריד (חוץ מהפריט הראשון)
            if (index > 0) {
                html += `<span class="breadcrumb-separator">${this.config.separator}</span>`;
            }
            
            // בדוק אם הפריט לחיץ
            if (item.clickable) {
                html += `
                    <a href="#" 
                       class="breadcrumb-item breadcrumb-clickable ${item.type === 'home' ? 'breadcrumb-home' : ''}"
                       data-type="${item.type}"
                       data-id="${item.id || ''}"
                       data-index="${index}">
                        ${item.icon ? `<span class="breadcrumb-icon">${item.icon}</span>` : ''}
                        <span class="breadcrumb-text">${item.name}</span>
                    </a>
                `;
            } else {
                // פריט לא לחיץ (הנוכחי)
                html += `
                    <span class="breadcrumb-item breadcrumb-current">
                        ${item.icon ? `<span class="breadcrumb-icon">${item.icon}</span>` : ''}
                        <span class="breadcrumb-text">${item.name}</span>
                    </span>
                `;
            }
        });
        
        container.innerHTML = html;
        
        // הוסף מאזינים לקליקים
        this.attachClickHandlers();
    },
    
    /**
     * הוספת מאזינים לקליקים על פריטי Breadcrumb
     */
    attachClickHandlers() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        container.querySelectorAll('.breadcrumb-clickable').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleClick(item);
            });
        });
    },
    
    /**
     * טיפול בלחיצה על פריט Breadcrumb
     */
    handleClick(element) {
        const type = element.dataset.type;
        const id = element.dataset.id;
        const index = parseInt(element.dataset.index);
        
        console.log('Breadcrumb clicked:', type, id, index);
        
        // אם זה דף הבית
        if (type === 'home') {
            window.location.href = this.config.homeUrl;
            return;
        }
        
        // אם זה "בתי עלמין" הראשי
        if (type === 'level' && this.currentPath[index].name === 'בתי עלמין') {
            // טען את כל בתי העלמין
            if (typeof loadAllCemeteries === 'function') {
                loadAllCemeteries();
            }
            return;
        }
        
        // נווט לפריט הספציפי
        this.navigateToItem(type, id, index);
    },
    
    /**
     * ניווט לפריט ספציפי
     */
    navigateToItem(type, id, index) {
        // שמור רק את הנתיב עד הפריט שנלחץ
        this.currentPath = this.currentPath.slice(0, index + 1);
        
        // עדכן את הבחירות הגלובליות
        if (window.selectedItems) {
            // נקה את כל הבחירות אחרי הפריט הנוכחי
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
        
        // קרא לפונקציה המתאימה לפתיחת הפריט
        const item = this.currentPath[index].item;
        if (item) {
            this.openItem(type, item);
        }
    },
    
    /**
     * פתיחת פריט לפי הסוג שלו
     */
    openItem(type, item) {
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
     * עדכון Breadcrumb מפשוט - לשימוש מהקוד הקיים
     * @param {string} pathString - מחרוזת נתיב כמו "בתי עלמין › גוש 1"
     */
    updateFromString(pathString) {
        // אם יש selectedItems, השתמש ב-setPath הרגיל
        if (window.selectedItems && Object.keys(window.selectedItems).length > 0) {
            this.setPath(window.selectedItems);
            return;
        }
        
        // אחרת, נסה לבנות נתיב מהמחרוזת
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        // תמיד התחל עם דף הבית (לחיץ)
        let html = `
            <a href="${this.config.homeUrl}" 
               class="breadcrumb-item breadcrumb-clickable breadcrumb-home">
                <span class="breadcrumb-icon">${this.config.homeIcon}</span>
                <span class="breadcrumb-text">${this.config.homeTitle}</span>
            </a>
        `;
        
        // פרוק המחרוזת לחלקים
        const parts = pathString.split(' › ');
        
        parts.forEach((part, index) => {
            // הוסף מפריד
            html += `<span class="breadcrumb-separator">${this.config.separator}</span>`;
            
            // נסה לזהות את סוג הפריט לפי הטקסט
            let type = this.detectTypeFromText(part);
            
            if (index === parts.length - 1) {
                // הפריט האחרון - לא לחיץ
                html += `
                    <span class="breadcrumb-item breadcrumb-current">
                        ${this.getIconForText(part)}
                        <span class="breadcrumb-text">${part}</span>
                    </span>
                `;
            } else {
                // פריטים באמצע - לחיצים
                html += `
                    <a href="#" 
                       class="breadcrumb-item breadcrumb-clickable"
                       onclick="BreadcrumbManager.handleTextClick('${part}', '${type}'); return false;">
                        ${this.getIconForText(part)}
                        <span class="breadcrumb-text">${part}</span>
                    </a>
                `;
            }
        });
        
        container.innerHTML = html;
    },
    
    /**
     * זיהוי סוג הפריט מהטקסט
     */
    detectTypeFromText(text) {
        if (text.includes('בתי עלמין') || text.includes('בית עלמין')) return 'cemetery';
        if (text.includes('גוש') || text.includes('גושים')) return 'block';
        if (text.includes('חלק') || text.includes('חלקות')) return 'plot';
        if (text.includes('אחוזת') || text.includes('אחוזות')) return 'areaGrave';
        if (text.includes('קבר') || text.includes('קברים')) return 'grave';
        return 'unknown';
    },
    
    /**
     * קבלת אייקון לפי הטקסט
     */
    getIconForText(text) {
        if (text.includes('בתי עלמין') || text.includes('בית עלמין')) 
            return '<span class="breadcrumb-icon">🏛️</span>';
        if (text.includes('גוש'))
            return '<span class="breadcrumb-icon">📦</span>';
        if (text.includes('חלק'))
            return '<span class="breadcrumb-icon">📋</span>';
        if (text.includes('אחוז'))
            return '<span class="breadcrumb-icon">🏘️</span>';
        if (text.includes('קבר'))
            return '<span class="breadcrumb-icon">⚰️</span>';
        return '';
    },
    
    /**
     * טיפול בלחיצה על טקסט
     */
    handleTextClick(text, type) {
        console.log('Breadcrumb text clicked:', text, type);
        
        // נסה לטעון לפי הטקסט
        if (text === 'בתי עלמין' && typeof loadAllCemeteries === 'function') {
            loadAllCemeteries();
        } else if (text === 'גושים' && typeof loadAllBlocks === 'function') {
            loadAllBlocks();
        } else if (text === 'חלקות' && typeof loadAllPlots === 'function') {
            loadAllPlots();
        }
        // אפשר להוסיף עוד מקרים לפי הצורך
    }
};

// פונקציה גלובלית לתאימות אחורה
function updateBreadcrumb(pathString) {
    if (pathString && typeof pathString === 'string') {
        BreadcrumbManager.updateFromString(pathString);
    } else if (typeof pathString === 'object') {
        BreadcrumbManager.setPath(pathString);
    } else {
        BreadcrumbManager.setPath(window.selectedItems || {});
    }
}

// אתחול בטעינת הדף
document.addEventListener('DOMContentLoaded', function() {
    BreadcrumbManager.reset();
});