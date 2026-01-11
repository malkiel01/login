/**
 * ContextMenu - תפריט הקשר (Right-click menu)
 * Version: 1.0.0
 *
 * מנהל תפריט קונטקסט עבור המפה
 */

export class ContextMenu {
    /**
     * הזרקת CSS לעמוד (נקרא פעם אחת)
     */
    static injectCSS() {
        // בדוק אם כבר הוזרק
        if (document.getElementById('mapContextMenuStyles')) {
            console.log('ℹ️ [ContextMenu] CSS already injected - skipping');
            return;
        }

        console.log('🎨 [ContextMenu] Injecting CSS...');

        const styles = document.createElement('style');
        styles.id = 'mapContextMenuStyles';
        styles.textContent = `
            /* Context Menu */
            .map-context-menu {
                position: absolute;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 1000;
                min-width: 180px;
                overflow: hidden;
                border: 1px solid #e5e7eb;
            }
            .context-menu-content {
                padding: 4px 0;
            }
            .context-menu-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 16px;
                cursor: pointer;
                transition: background 0.15s;
                font-size: 14px;
                color: #374151;
            }
            .context-menu-item:hover {
                background: #f3f4f6;
            }
            .context-menu-item.disabled {
                color: #9ca3af;
                cursor: not-allowed;
                background: #f9fafb;
            }
            .context-menu-separator {
                height: 1px;
                background: #e5e7eb;
                margin: 4px 0;
            }
        `;

        document.head.appendChild(styles);

        console.log('✅ [ContextMenu] CSS injected successfully!');
        console.log('   [ContextMenu] Style element ID: mapContextMenuStyles');
        console.log('   [ContextMenu] CSS length:', styles.textContent.length, 'chars');
        console.log('   [ContextMenu] In document.head:', document.head.contains(styles));

        // Test CSS application
        setTimeout(() => {
            const testDiv = document.createElement('div');
            testDiv.className = 'map-context-menu';
            testDiv.style.position = 'absolute';
            testDiv.style.visibility = 'hidden';
            document.body.appendChild(testDiv);

            const computedStyle = window.getComputedStyle(testDiv);
            console.log('🧪 [ContextMenu] CSS TEST - .map-context-menu computed:', {
                background: computedStyle.backgroundColor,
                borderRadius: computedStyle.borderRadius,
                boxShadow: computedStyle.boxShadow,
                minWidth: computedStyle.minWidth
            });

            if (computedStyle.backgroundColor === 'rgb(255, 255, 255)' && computedStyle.borderRadius === '8px') {
                console.log('✅ [ContextMenu] CSS TEST PASSED - styles applied correctly!');
            } else {
                console.error('❌ [ContextMenu] CSS TEST FAILED - styles not applied!');
            }

            document.body.removeChild(testDiv);
        }, 50);
    }

    constructor(options = {}) {
        // הזרק CSS אם עדיין לא הוזרק
        ContextMenu.injectCSS();

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
