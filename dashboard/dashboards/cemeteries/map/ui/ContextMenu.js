/**
 * ContextMenu - תפריט הקשר (Right-click menu)
 * Version: 1.0.0
 *
 * מנהל תפריט קונטקסט עבור המפה
 */

export class ContextMenu {
    constructor(options = {}) {
        this.options = {
            menuId: options.menuId || 'mapContextMenu',
            contentId: options.contentId || 'contextMenuContent',
            onAction: options.onAction || null,
            checkBoundary: options.checkBoundary || null
        };

        this.menu = null;
        this.content = null;
        this.currentTarget = null;
        this.currentPosition = null;

        this.init();
    }

    /**
     * אתחול התפריט
     */
    init() {
        // צור אלמנטים אם לא קיימים
        if (!document.getElementById(this.options.menuId)) {
            this.createMenu();
        }

        this.menu = document.getElementById(this.options.menuId);
        this.content = document.getElementById(this.options.contentId);

        // Attach global click listener to hide menu
        document.addEventListener('click', () => this.hide());

        console.log(' ContextMenu initialized');
    }

    /**
     * יצירת HTML של התפריט
     */
    createMenu() {
        const menuHTML = `
            <div id="${this.options.menuId}" class="map-context-menu">
                <div id="${this.options.contentId}" class="context-menu-content"></div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', menuHTML);
    }

    /**
     * הצגת התפריט במיקום ריק (ללא אובייקט)
     * @param {number} clientX - Mouse X position
     * @param {number} clientY - Mouse Y position
     * @param {boolean} isInsideBoundary - האם בתוך הגבול
     */
    showForEmpty(clientX, clientY, isInsideBoundary) {
        if (!this.menu || !this.content) return;

        this.currentTarget = null;
        this.currentPosition = { x: clientX, y: clientY };

        // בדוק אם יש גבול
        const hasBoundary = this.options.checkBoundary ? this.options.checkBoundary() : true;

        if (!hasBoundary) {
            // אין גבול - רק הודעה
            this.content.innerHTML = `
                <div class="context-menu-item disabled">
                    <span class="context-menu-icon">⚠</span>
                    <span>יש להגדיר גבול קודם לפני הוספה</span>
                </div>
            `;
        } else if (isInsideBoundary) {
            // בתוך הגבול - אפשר הוספה
            this.content.innerHTML = `
                <div class="context-menu-item" data-action="addImage">
                    <span class="context-menu-icon">🖼️</span>
                    <span>הוסף תמונה / PDF</span>
                </div>
                <div class="context-menu-item" data-action="addText">
                    <span class="context-menu-icon">🔤</span>
                    <span>הוסף טקסט</span>
                </div>
                <div class="context-menu-separator"></div>
                <div class="context-menu-item" data-action="addRect">
                    <span class="context-menu-icon">◻</span>
                    <span>הוסף מלבן</span>
                </div>
                <div class="context-menu-item" data-action="addCircle">
                    <span class="context-menu-icon">◯</span>
                    <span>הוסף עיגול</span>
                </div>
                <div class="context-menu-item" data-action="addLine">
                    <span class="context-menu-icon">📏</span>
                    <span>הוסף קו</span>
                </div>
            `;

            // Attach event listeners
            this.attachActionListeners();
        } else {
            // מחוץ לגבול
            this.content.innerHTML = `
                <div class="context-menu-item disabled">
                    <span class="context-menu-icon no-entry-icon">🚫</span>
                    <span>לא ניתן להוסיף מחוץ לגבול</span>
                </div>
            `;
        }

        // הצג בעמדה הנכונה
        this.position(clientX, clientY);
    }

    /**
     * הצגת התפריט עבור אובייקט (עם אופציות מחיקה, העברה לקדמה, העברה לאחור)
     * @param {number} clientX - Mouse X position
     * @param {number} clientY - Mouse Y position
     * @param {object} targetObject - Fabric object
     */
    showForObject(clientX, clientY, targetObject) {
        if (!this.menu || !this.content) return;

        // שמור את האובייקט
        this.currentTarget = targetObject;

        // תפריט עם אופציות לאובייקט
        this.content.innerHTML = `
            <div class="context-menu-item" data-action="deleteObject">
                <span class="context-menu-icon">🗑️</span>
                <span>מחק אובייקט</span>
            </div>
            <div class="context-menu-separator"></div>
            <div class="context-menu-item" data-action="bringToFront">
                <span class="context-menu-icon">⬆</span>
                <span>העבר לקדימה</span>
            </div>
            <div class="context-menu-item" data-action="sendToBack">
                <span class="context-menu-icon">⬇</span>
                <span>העבר לאחור</span>
            </div>
        `;

        // Attach event listeners
        this.attachActionListeners();

        // הצג בעמדה הנכונה
        this.position(clientX, clientY);
    }

    /**
     * מיקום התפריט ליד העכבר (מתאים לגבולות המסך)
     * @param {number} clientX
     * @param {number} clientY
     * @private
     */
    position(clientX, clientY) {
        // מיקום ראשוני
        this.menu.style.position = 'fixed';
        this.menu.style.left = clientX + 'px';
        this.menu.style.top = clientY + 'px';
        this.menu.style.display = 'block';

        // בדיקה אם התפריט חורג מהמסך
        const menuRect = this.menu.getBoundingClientRect();

        // התאמה ימינה
        if (menuRect.right > window.innerWidth) {
            this.menu.style.left = (clientX - menuRect.width) + 'px';
        }

        // התאמה למטה
        if (menuRect.bottom > window.innerHeight) {
            this.menu.style.top = (clientY - menuRect.height) + 'px';
        }
    }

    /**
     * חיבור event listeners לאייטמים
     * @private
     */
    attachActionListeners() {
        const items = this.content.querySelectorAll('.context-menu-item:not(.disabled)');

        items.forEach(item => {
            item.addEventListener('click', () => {
                const action = item.getAttribute('data-action');

                if (action && this.options.onAction) {
                    this.options.onAction(action, {
                        target: this.currentTarget,
                        position: this.currentPosition
                    });
                }

                this.hide();
            });
        });
    }

    /**
     * הסתרת התפריט
     */
    hide() {
        if (this.menu) {
            this.menu.style.display = 'none';
        }
        this.currentTarget = null;
    }

    /**
     * בדיקה האם התפריט מוצג
     */
    isVisible() {
        return this.menu && this.menu.style.display === 'block';
    }

    /**
     * ניקוי
     */
    destroy() {
        if (this.menu) {
            this.menu.remove();
        }
        this.menu = null;
        this.content = null;
    }
}
