/**
 * Toolbar - סרגל כלים למפה
 * Version: 1.0.0
 *
 * מחלקה לניהול סרגל הכלים עם כפתורי זום, רקע, גבול, והיסטוריה
 * Usage:
 *   const toolbar = new Toolbar(container, {
 *     onZoomIn: () => {...},
 *     onZoomOut: () => {...},
 *     onSave: () => {...},
 *     ...
 *   });
 */

export class Toolbar {
    constructor(containerElement, handlers = {}) {
        this.container = containerElement;
        this.handlers = {
            // Zoom handlers
            onZoomIn: handlers.onZoomIn || (() => {}),
            onZoomOut: handlers.onZoomOut || (() => {}),
            onEditZoomLevel: handlers.onEditZoomLevel || (() => {}),

            // Background handlers
            onUploadBackground: handlers.onUploadBackground || (() => {}),
            onToggleBackgroundEdit: handlers.onToggleBackgroundEdit || (() => {}),
            onDeleteBackground: handlers.onDeleteBackground || (() => {}),

            // Boundary handlers
            onStartDrawPolygon: handlers.onStartDrawPolygon || (() => {}),
            onToggleBoundaryEdit: handlers.onToggleBoundaryEdit || (() => {}),
            onDeleteBoundary: handlers.onDeleteBoundary || (() => {}),

            // History handlers
            onUndo: handlers.onUndo || (() => {}),
            onRedo: handlers.onRedo || (() => {}),

            // Save handler
            onSave: handlers.onSave || (() => {})
        };

        this.render();
        this.attachEventListeners();
    }

    /**
     * רינדור הטולבר
     */
    render() {
        console.log('🔧 [Toolbar] render() called');
        console.log('   [Toolbar] container:', this.container ? {
            id: this.container.id,
            exists: true,
            childCount: this.container.children.length
        } : '❌ null');

        if (!this.container) {
            console.error('❌ [Toolbar] Container not found!');
            return;
        }

        const html = this.getToolbarHTML();
        console.log('   [Toolbar] Generated HTML length:', html.length, 'chars');

        this.container.innerHTML = html;

        console.log('   [Toolbar] After innerHTML - childCount:', this.container.children.length);

        if (this.container.children.length === 0) {
            console.error('❌ [Toolbar] innerHTML set but no children created!');
        } else {
            console.log('✅ [Toolbar] Rendered successfully with', this.container.children.length, 'children');
        }
    }

    /**
     * בניית HTML של הטולבר
     */
    getToolbarHTML() {
        return `
        <div class="map-toolbar">
            ${this.getZoomGroup()}
            ${this.getBackgroundBoundaryGroup()}
            ${this.getHistoryGroup()}
        </div>
        `;
    }

    /**
     * קבוצת זום - תמיד מוצגת
     */
    getZoomGroup() {
        return `
            <!-- גרופ זום - תמיד מוצג -->
            <div class="map-toolbar-group">
                <button class="map-tool-btn" data-action="zoom-in" title="הגדל">+</button>
                <span id="mapZoomLevel" class="map-zoom-level" data-action="edit-zoom" title="דאבל קליק לעריכה ידנית">100%</span>
                <button class="map-tool-btn" data-action="zoom-out" title="הקטן">−</button>
            </div>
        `;
    }

    /**
     * קבוצת רקע וגבול - במצב עריכה בלבד
     */
    getBackgroundBoundaryGroup() {
        return `
            <!-- גרופ רקע וגבול - במצב עריכה -->
            <div class="map-toolbar-group edit-only">
                <!-- תמונת רקע -->
                <button class="map-tool-btn" data-action="upload-background" title="הוסף תמונת רקע / PDF">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                </button>
                <button class="map-tool-btn hidden-btn" id="editBackgroundBtn" data-action="toggle-background-edit" title="עריכת תמונת רקע">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="map-tool-btn hidden-btn" id="deleteBackgroundBtn" data-action="delete-background" title="הסר תמונת רקע">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                    </svg>
                </button>

                <div class="toolbar-separator"></div>

                <!-- גבול מפה -->
                <button class="map-tool-btn" id="drawPolygonBtn" data-action="start-draw-polygon" title="הגדר גבול מפה">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/>
                    </svg>
                </button>
                <button class="map-tool-btn hidden-btn" id="editBoundaryBtn" data-action="toggle-boundary-edit" title="עריכת גבול">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="map-tool-btn hidden-btn" id="deleteBoundaryBtn" data-action="delete-boundary" title="הסר גבול">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                    </svg>
                </button>
            </div>
        `;
    }

    /**
     * קבוצת היסטוריה ושמירה - במצב עריכה בלבד
     */
    getHistoryGroup() {
        return `
            <!-- גרופ היסטוריה ושמירה - במצב עריכה -->
            <div class="map-toolbar-group edit-only">
                <button class="map-tool-btn" id="undoBtn" data-action="undo" title="בטל פעולה" disabled>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 7v6h6"/>
                        <path d="M3 13a9 9 0 1 0 3-7.7L3 7"/>
                    </svg>
                </button>
                <button class="map-tool-btn" id="redoBtn" data-action="redo" title="בצע שוב" disabled>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 7v6h-6"/>
                        <path d="M21 13a9 9 0 1 1-3-7.7L21 7"/>
                    </svg>
                </button>
                <button class="map-tool-btn" data-action="save" title="שמור מפה">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                </button>
            </div>
        `;
    }

    /**
     * חיבור event listeners
     */
    attachEventListeners() {
        if (!this.container) return;

        // Event delegation על כל הטולבר
        this.container.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action]');
            if (!button) return;

            const action = button.dataset.action;
            this.handleAction(action);
        });

        // Double click על zoom level
        this.container.addEventListener('dblclick', (e) => {
            if (e.target.id === 'mapZoomLevel' || e.target.dataset.action === 'edit-zoom') {
                this.handlers.onEditZoomLevel();
            }
        });
    }

    /**
     * טיפול באקשן של כפתור
     */
    handleAction(action) {
        const actionMap = {
            'zoom-in': this.handlers.onZoomIn,
            'zoom-out': this.handlers.onZoomOut,
            'upload-background': this.handlers.onUploadBackground,
            'toggle-background-edit': this.handlers.onToggleBackgroundEdit,
            'delete-background': this.handlers.onDeleteBackground,
            'start-draw-polygon': this.handlers.onStartDrawPolygon,
            'toggle-boundary-edit': this.handlers.onToggleBoundaryEdit,
            'delete-boundary': this.handlers.onDeleteBoundary,
            'undo': this.handlers.onUndo,
            'redo': this.handlers.onRedo,
            'save': this.handlers.onSave
        };

        const handler = actionMap[action];
        if (handler) {
            handler();
        } else {
            console.warn('Unknown toolbar action:', action);
        }
    }

    /**
     * עדכון תצוגת רמת הזום
     */
    updateZoomDisplay(zoom) {
        const zoomElement = this.container.querySelector('#mapZoomLevel');
        if (zoomElement) {
            zoomElement.textContent = Math.round(zoom * 100) + '%';
        }
    }

    /**
     * הפעלת כפתור
     */
    enableButton(id) {
        const button = this.container.querySelector(`#${id}`);
        if (button) {
            button.disabled = false;
        }
    }

    /**
     * השבתת כפתור
     */
    disableButton(id) {
        const button = this.container.querySelector(`#${id}`);
        if (button) {
            button.disabled = true;
        }
    }

    /**
     * הצגת כפתור מוסתר
     */
    showButton(id) {
        const button = this.container.querySelector(`#${id}`);
        if (button) {
            button.classList.remove('hidden-btn');
        }
    }

    /**
     * הסתרת כפתור
     */
    hideButton(id) {
        const button = this.container.querySelector(`#${id}`);
        if (button) {
            button.classList.add('hidden-btn');
        }
    }

    /**
     * עדכון מצב עריכה
     */
    setEditMode(enabled) {
        // CSS class on container handles visibility of .edit-only groups
        // This is just a helper if needed for additional logic
        console.log('Toolbar edit mode:', enabled);
    }
}
