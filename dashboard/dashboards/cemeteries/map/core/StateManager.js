/**
 * State Manager - ניהול State מרכזי של המפה
 * Version: 1.0.0
 *
 * מחליף את כל המשתנים הגלובליים בקוד הישן
 * Usage: const state = new StateManager();
 */

export class StateManager {
    constructor(options = {}) {
        // Entity context
        this.entityType = options.entityType || null;
        this.entityId = options.entityId || null;

        // View mode
        this.mode = options.mode || 'view';
        this.isEditMode = false;

        // Zoom
        this.zoom = 1;

        // Canvas reference
        this.canvas = {
            instance: null,

            // Background
            background: {
                image: null,
                isEditMode: false,
                pdfContext: null,
                pdfDoc: null
            },

            // Boundary
            boundary: {
                outline: null,
                clipPath: null,
                grayMask: null,
                isEditMode: false,
                lastValidState: null
            },

            // Parent boundary
            parent: {
                points: null,
                outline: null
            }
        };

        // Polygon drawing
        this.polygon = {
            isDrawing: false,
            points: [],
            previewLine: null
        };

        // History
        this.history = {
            states: [],
            currentIndex: -1,
            maxStates: options.maxHistory || 30
        };
    }

    // Zoom
    getZoom() { return this.zoom; }
    setZoom(level) { this.zoom = level; }

    // Canvas
    getCanvas() { return this.canvas.instance; }
    setCanvas(canvas) { this.canvas.instance = canvas; }

    // Background
    getBackgroundImage() { return this.canvas.background.image; }
    setBackgroundImage(image) { this.canvas.background.image = image; }
    hasBackgroundImage() { return this.canvas.background.image !== null; }

    // Boundary
    getBoundaryOutline() { return this.canvas.boundary.outline; }
    setBoundaryOutline(outline) { this.canvas.boundary.outline = outline; }
    hasBoundary() { return this.canvas.boundary.outline !== null; }

    getGrayMask() { return this.canvas.boundary.grayMask; }
    setGrayMask(mask) { this.canvas.boundary.grayMask = mask; }

    // Entity
    getCurrentEntity() { return { type: this.entityType, id: this.entityId }; }
    setEntity(type, id) {
        this.entityType = type;
        this.entityId = id;
    }

    // Reset
    reset() {
        this.zoom = 1;
        this.isEditMode = false;
        this.canvas.background.image = null;
        this.canvas.boundary.outline = null;
        this.canvas.boundary.grayMask = null;
        this.polygon.isDrawing = false;
        this.polygon.points = [];
        this.history.states = [];
        this.history.currentIndex = -1;
    }

    // Debug
    debug() {
        console.group('🗄️ StateManager');
        console.log('Entity:', this.getCurrentEntity());
        console.log('Zoom:', this.zoom);
        console.log('Has Background:', this.hasBackgroundImage());
        console.log('Has Boundary:', this.hasBoundary());
        console.groupEnd();
    }
}
