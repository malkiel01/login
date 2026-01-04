/**
 * CanvasManager - ניהול Canvas של המפה
 * Version: 1.0.0
 *
 * מחלקה לניהול יצירה ואירועים של Fabric.js Canvas
 * Usage:
 *   const canvasManager = new CanvasManager(container, {
 *     width: 2000,
 *     height: 1500,
 *     backgroundColor: '#ffffff'
 *   });
 *   const canvas = canvasManager.create();
 */

export class CanvasManager {
    constructor(containerElement, options = {}) {
        this.container = containerElement;
        this.options = {
            canvasId: options.canvasId || 'fabricCanvas',
            backgroundColor: options.backgroundColor || '#ffffff',
            selection: options.selection !== false,
            initialText: options.initialText || 'לחץ על "מצב עריכה" כדי להתחיל'
        };
        this.canvas = null;
        this.canvasElement = null;

        // Panning state
        this.isPanning = false;
        this.lastPosX = 0;
        this.lastPosY = 0;

        // Handlers
        this.handlers = {
            onMouseDown: null,
            onMouseMove: null,
            onObjectModified: null,
            onContextMenu: null,
            onZoomChange: null
        };
    }

    /**
     * יצירת Canvas
     */
    create() {
        if (!this.container) {
            throw new Error('Container element not found');
        }

        if (typeof fabric === 'undefined') {
            throw new Error('Fabric.js not loaded');
        }

        // חישוב גודל
        const width = this.container.clientWidth;
        const height = this.container.clientHeight - 40; // minus indicator height

        // יצירת canvas element
        this.canvasElement = document.createElement('canvas');
        this.canvasElement.id = this.options.canvasId;
        this.canvasElement.width = width;
        this.canvasElement.height = height;
        this.container.appendChild(this.canvasElement);

        // יצירת Fabric canvas
        this.canvas = new fabric.Canvas(this.options.canvasId, {
            backgroundColor: this.options.backgroundColor,
            selection: this.options.selection
        });

        // הוספת טקסט התחלתי
        if (this.options.initialText) {
            const text = new fabric.Text(this.options.initialText, {
                left: width / 2,
                top: height / 2,
                fontSize: 20,
                fill: '#9ca3af',
                originX: 'center',
                originY: 'center',
                selectable: false
            });
            this.canvas.add(text);
        }

        console.log('Canvas created:', width, 'x', height);
        return this.canvas;
    }

    /**
     * חיבור event handlers
     */
    attachEventHandlers(handlers = {}) {
        if (!this.canvas) {
            console.error('Canvas not created yet');
            return;
        }

        // Store handlers
        this.handlers = { ...this.handlers, ...handlers };

        // Polygon drawing events (if provided)
        if (handlers.onMouseDown) {
            this.canvas.on('mouse:down', handlers.onMouseDown);
        }
        if (handlers.onMouseMove) {
            this.canvas.on('mouse:move', handlers.onMouseMove);
        }

        // Panning events
        this.attachPanningHandlers();

        // Zoom wheel event
        this.attachZoomWheelHandler();

        // History events (if provided)
        if (handlers.onObjectModified) {
            this.canvas.on('object:modified', (e) => {
                // התעלם מאובייקטים זמניים של ציור פוליגון
                if (e.target && !e.target.polygonPoint && !e.target.polygonLine && !e.target.previewLine) {
                    handlers.onObjectModified(e);
                }
            });
        }

        // Context menu (if provided)
        if (handlers.onContextMenu) {
            const fabricWrapper = this.container.querySelector('.canvas-container');
            const targetElement = fabricWrapper || this.container;
            targetElement.addEventListener('contextmenu', handlers.onContextMenu);
        }

        console.log('Canvas event handlers attached');
    }

    /**
     * חיבור אירועי panning (גרירה)
     */
    attachPanningHandlers() {
        // Start panning
        this.canvas.on('mouse:down', (opt) => {
            const evt = opt.e;
            const drawingPolygon = window.drawingPolygon || false;
            const isEditMode = window.isEditMode || false;

            // גרירה על רקע ריק - רק כשלא במצב עריכה ולא במצב ציור פוליגון
            if (!opt.target && !drawingPolygon && !isEditMode && evt.button === 0) {
                this.isPanning = true;
                this.lastPosX = evt.clientX;
                this.lastPosY = evt.clientY;
                this.canvas.selection = false;
                this.canvas.setCursor('grab');
            }
        });

        // Continue panning
        this.canvas.on('mouse:move', (opt) => {
            if (this.isPanning) {
                const evt = opt.e;
                const deltaX = evt.clientX - this.lastPosX;
                const deltaY = evt.clientY - this.lastPosY;

                // הזז את הקנבס
                const vpt = this.canvas.viewportTransform;
                vpt[4] += deltaX;
                vpt[5] += deltaY;

                this.canvas.requestRenderAll();
                this.lastPosX = evt.clientX;
                this.lastPosY = evt.clientY;
                this.canvas.setCursor('grabbing');
            }
        });

        // Stop panning
        this.canvas.on('mouse:up', (opt) => {
            if (this.isPanning) {
                this.isPanning = false;
                this.canvas.selection = true;
                this.canvas.setCursor('default');
            }
        });
    }

    /**
     * חיבור אירוע zoom wheel
     */
    attachZoomWheelHandler() {
        this.canvas.on('mouse:wheel', (opt) => {
            const delta = opt.e.deltaY;
            let zoom = this.canvas.getZoom();
            zoom *= 0.999 ** delta;

            // הגבלת זום
            if (zoom > 3) zoom = 3;
            if (zoom < 0.3) zoom = 0.3;

            // זום למיקום העכבר
            this.canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);

            // Notify zoom change handler
            if (this.handlers.onZoomChange) {
                this.handlers.onZoomChange(zoom);
            }

            opt.e.preventDefault();
            opt.e.stopPropagation();
        });
    }

    /**
     * קבלת ה-Canvas
     */
    getCanvas() {
        return this.canvas;
    }

    /**
     * קבלת גודל ה-Canvas
     */
    getSize() {
        if (!this.canvas) return { width: 0, height: 0 };
        return {
            width: this.canvas.getWidth(),
            height: this.canvas.getHeight()
        };
    }

    /**
     * שינוי גודל Canvas
     */
    resize(width, height) {
        if (this.canvas) {
            this.canvas.setWidth(width);
            this.canvas.setHeight(height);
            this.canvas.renderAll();
        }
    }

    /**
     * ניקוי Canvas
     */
    clear() {
        if (this.canvas) {
            this.canvas.clear();
            this.canvas.backgroundColor = this.options.backgroundColor;
            this.canvas.renderAll();
        }
    }

    /**
     * השמדת Canvas
     */
    destroy() {
        if (this.canvas) {
            this.canvas.dispose();
            this.canvas = null;
        }
        if (this.canvasElement && this.canvasElement.parentNode) {
            this.canvasElement.parentNode.removeChild(this.canvasElement);
            this.canvasElement = null;
        }
    }

    /**
     * Debug info
     */
    debug() {
        console.group('🎨 CanvasManager');
        console.log('Canvas:', this.canvas);
        console.log('Size:', this.getSize());
        console.log('Objects:', this.canvas ? this.canvas.getObjects().length : 0);
        console.log('Panning:', this.isPanning);
        console.groupEnd();
    }
}
