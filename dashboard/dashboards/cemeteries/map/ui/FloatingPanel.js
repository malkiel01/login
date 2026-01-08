/**
 * FloatingPanel - חלון צף גנרי וניתן לגרירה
 * Version: 1.0.0
 *
 * קומפוננטת בסיס לחלונות צפים בתוך המפה
 * Usage:
 *   const panel = new FloatingPanel({
 *     title: 'כותרת',
 *     width: 200,
 *     position: { top: 80, right: 20 },
 *     onClose: () => {...}
 *   });
 *   panel.setContent('<div>תוכן</div>');
 *   panel.show();
 *
 * Or extend it:
 *   class MyPanel extends FloatingPanel {
 *     constructor(options) {
 *       super({ title: 'My Panel', ...options });
 *       this.render();
 *     }
 *   }
 */

export class FloatingPanel {
    static cssInjected = false;

    constructor(options = {}) {
        this.options = {
            title: options.title || 'חלון',
            width: options.width || 220,
            position: options.position || { top: 80, right: 20 },
            headerColor: options.headerColor || 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            container: options.container || null,
            closable: options.closable !== false,
            draggable: options.draggable !== false,
            className: options.className || '',
            onClose: options.onClose || null,
            onShow: options.onShow || null,
            onHide: options.onHide || null
        };

        this.panel = null;
        this.contentEl = null;
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };

        // Bind methods
        this.handleMouseDown = this.handleMouseDown.bind(this);
        this.handleMouseMove = this.handleMouseMove.bind(this);
        this.handleMouseUp = this.handleMouseUp.bind(this);

        // Inject CSS once
        FloatingPanel.injectCSS();
    }

    /**
     * הזרקת CSS (פעם אחת לכל הקלאס)
     */
    static injectCSS() {
        if (FloatingPanel.cssInjected) return;
        FloatingPanel.cssInjected = true;

        const styles = document.createElement('style');
        styles.id = 'floatingPanelStyles';
        styles.textContent = `
            .floating-panel {
                position: absolute;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                z-index: 1000;
                overflow: hidden;
                font-family: inherit;
                display: none;
            }
            .floating-panel.visible {
                display: block;
            }
            .floating-panel-header {
                color: white;
                padding: 12px 16px;
                font-weight: 600;
                font-size: 14px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                user-select: none;
            }
            .floating-panel-header.draggable {
                cursor: move;
            }
            .floating-panel-title {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .floating-panel-title-icon {
                font-size: 16px;
            }
            .floating-panel-close {
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            .floating-panel-close:hover {
                background: rgba(255,255,255,0.3);
            }
            .floating-panel-content {
                padding: 16px;
            }
            .floating-panel-info {
                font-size: 12px;
                color: #6b7280;
                margin-bottom: 12px;
                line-height: 1.5;
            }
            .floating-panel-btn {
                width: 100%;
                padding: 12px 16px;
                margin-bottom: 8px;
                border: 2px solid #e5e7eb;
                background: white;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.2s;
                color: #374151;
            }
            .floating-panel-btn:last-child {
                margin-bottom: 0;
            }
            .floating-panel-btn:hover {
                border-color: #3b82f6;
                background: #eff6ff;
            }
            .floating-panel-btn.active {
                border-color: #3b82f6;
                background: #3b82f6;
                color: white;
            }
            .floating-panel-btn.danger:hover {
                border-color: #ef4444;
                background: #fef2f2;
            }
            .floating-panel-btn.danger.active {
                border-color: #ef4444;
                background: #ef4444;
                color: white;
            }
            .floating-panel-btn-icon {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }
            .floating-panel-btn:not(.active) .floating-panel-btn-icon {
                background: #f3f4f6;
            }
            .floating-panel-btn.active .floating-panel-btn-icon {
                background: rgba(255,255,255,0.2);
            }
            .floating-panel-footer {
                background: #f3f4f6;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                color: #4b5563;
                text-align: center;
                margin-top: 8px;
            }
            .floating-panel-divider {
                height: 1px;
                background: #e5e7eb;
                margin: 12px 0;
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * יצירת הפאנל
     */
    create() {
        if (this.panel) return this.panel;

        this.panel = document.createElement('div');
        this.panel.className = `floating-panel ${this.options.className}`.trim();
        this.panel.style.width = this.options.width + 'px';

        // Set initial position
        if (this.options.position.top !== undefined) {
            this.panel.style.top = this.options.position.top + 'px';
        }
        if (this.options.position.right !== undefined) {
            this.panel.style.right = this.options.position.right + 'px';
        }
        if (this.options.position.bottom !== undefined) {
            this.panel.style.bottom = this.options.position.bottom + 'px';
        }
        if (this.options.position.left !== undefined) {
            this.panel.style.left = this.options.position.left + 'px';
        }

        // Build HTML
        this.panel.innerHTML = `
            <div class="floating-panel-header ${this.options.draggable ? 'draggable' : ''}"
                 style="background: ${this.options.headerColor}">
                <div class="floating-panel-title">
                    <span class="floating-panel-title-text">${this.options.title}</span>
                </div>
                ${this.options.closable ? '<button class="floating-panel-close">✕</button>' : ''}
            </div>
            <div class="floating-panel-content"></div>
        `;

        this.contentEl = this.panel.querySelector('.floating-panel-content');

        // Add to container
        const container = this.options.container || document.body;
        container.appendChild(this.panel);

        // Bind events
        this.bindEvents();

        return this.panel;
    }

    /**
     * חיבור אירועים
     */
    bindEvents() {
        if (!this.panel) return;

        // Close button
        if (this.options.closable) {
            const closeBtn = this.panel.querySelector('.floating-panel-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.hide());
            }
        }

        // Dragging
        if (this.options.draggable) {
            const header = this.panel.querySelector('.floating-panel-header');
            if (header) {
                header.addEventListener('mousedown', this.handleMouseDown);
            }
        }
    }

    /**
     * הצגת הפאנל
     */
    show() {
        if (!this.panel) {
            this.create();
        }
        this.panel.classList.add('visible');

        if (this.options.onShow) {
            this.options.onShow();
        }
    }

    /**
     * הסתרת הפאנל
     */
    hide() {
        if (this.panel) {
            this.panel.classList.remove('visible');
        }

        if (this.options.onClose) {
            this.options.onClose();
        }
        if (this.options.onHide) {
            this.options.onHide();
        }
    }

    /**
     * בדיקה אם גלוי
     */
    isVisible() {
        return this.panel?.classList.contains('visible') || false;
    }

    /**
     * הגדרת תוכן (HTML string)
     */
    setContent(html) {
        if (!this.panel) {
            this.create();
        }
        this.contentEl.innerHTML = html;
    }

    /**
     * הוספת אלמנט לתוכן
     */
    appendContent(element) {
        if (!this.panel) {
            this.create();
        }
        if (typeof element === 'string') {
            this.contentEl.insertAdjacentHTML('beforeend', element);
        } else {
            this.contentEl.appendChild(element);
        }
    }

    /**
     * ניקוי תוכן
     */
    clearContent() {
        if (this.contentEl) {
            this.contentEl.innerHTML = '';
        }
    }

    /**
     * עדכון כותרת
     */
    setTitle(title, icon = null) {
        const titleEl = this.panel?.querySelector('.floating-panel-title-text');
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (icon) {
            const iconEl = this.panel?.querySelector('.floating-panel-title-icon');
            if (iconEl) {
                iconEl.textContent = icon;
            }
        }
    }

    /**
     * עדכון צבע הכותרת
     */
    setHeaderColor(color) {
        const header = this.panel?.querySelector('.floating-panel-header');
        if (header) {
            header.style.background = color;
        }
    }

    /**
     * קבלת אלמנט התוכן
     */
    getContentElement() {
        return this.contentEl;
    }

    /**
     * קבלת אלמנט הפאנל
     */
    getElement() {
        return this.panel;
    }

    /**
     * הגדרת container (לאחר יצירה)
     */
    setContainer(container) {
        if (this.panel && this.panel.parentElement !== container) {
            container.appendChild(this.panel);
        }
        this.options.container = container;
    }

    /**
     * מיקום מחדש
     */
    setPosition(position) {
        if (!this.panel) return;

        // Reset all positions
        this.panel.style.top = '';
        this.panel.style.right = '';
        this.panel.style.bottom = '';
        this.panel.style.left = '';

        if (position.top !== undefined) {
            this.panel.style.top = position.top + 'px';
        }
        if (position.right !== undefined) {
            this.panel.style.right = position.right + 'px';
        }
        if (position.bottom !== undefined) {
            this.panel.style.bottom = position.bottom + 'px';
        }
        if (position.left !== undefined) {
            this.panel.style.left = position.left + 'px';
        }

        this.options.position = position;
    }

    /**
     * Drag handlers
     */
    handleMouseDown(e) {
        if (e.target.classList.contains('floating-panel-close')) return;

        this.isDragging = true;
        const rect = this.panel.getBoundingClientRect();
        this.dragOffset = {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };

        // Clear right/bottom positioning when starting drag
        this.panel.style.right = '';
        this.panel.style.bottom = '';

        document.addEventListener('mousemove', this.handleMouseMove);
        document.addEventListener('mouseup', this.handleMouseUp);
    }

    handleMouseMove(e) {
        if (!this.isDragging) return;

        const container = this.panel.parentElement;
        const containerRect = container.getBoundingClientRect();

        let newLeft = e.clientX - containerRect.left - this.dragOffset.x;
        let newTop = e.clientY - containerRect.top - this.dragOffset.y;

        // Keep within bounds
        newLeft = Math.max(0, Math.min(newLeft, containerRect.width - this.panel.offsetWidth));
        newTop = Math.max(0, Math.min(newTop, containerRect.height - this.panel.offsetHeight));

        this.panel.style.left = newLeft + 'px';
        this.panel.style.top = newTop + 'px';
    }

    handleMouseUp() {
        this.isDragging = false;
        document.removeEventListener('mousemove', this.handleMouseMove);
        document.removeEventListener('mouseup', this.handleMouseUp);
    }

    /**
     * הריסת הפאנל
     */
    destroy() {
        if (this.panel) {
            this.panel.remove();
            this.panel = null;
            this.contentEl = null;
        }
        document.removeEventListener('mousemove', this.handleMouseMove);
        document.removeEventListener('mouseup', this.handleMouseUp);
    }

    // ═══════════════════════════════════════════════════════════════
    // Helper methods for building common UI elements
    // ═══════════════════════════════════════════════════════════════

    /**
     * יצירת כפתור סטנדרטי
     */
    static createButton(options = {}) {
        const {
            icon = '',
            text = '',
            className = '',
            danger = false,
            onClick = null
        } = options;

        const btn = document.createElement('button');
        btn.className = `floating-panel-btn ${danger ? 'danger' : ''} ${className}`.trim();
        btn.innerHTML = `
            <span class="floating-panel-btn-icon">${icon}</span>
            <span>${text}</span>
        `;

        if (onClick) {
            btn.addEventListener('click', onClick);
        }

        return btn;
    }

    /**
     * יצירת הודעת מידע
     */
    static createInfo(text) {
        const info = document.createElement('div');
        info.className = 'floating-panel-info';
        info.textContent = text;
        return info;
    }

    /**
     * יצירת footer
     */
    static createFooter(html) {
        const footer = document.createElement('div');
        footer.className = 'floating-panel-footer';
        footer.innerHTML = html;
        return footer;
    }

    /**
     * יצירת קו מפריד
     */
    static createDivider() {
        const divider = document.createElement('div');
        divider.className = 'floating-panel-divider';
        return divider;
    }
}
