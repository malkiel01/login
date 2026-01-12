/**
 * Map Editor v2 - Main Module
 * עורך מפות גרסה 2 - מודול ראשי
 */

class MapEditor {
    constructor(config) {
        this.config = config;
        this.canvas = null;
        this.isEditMode = false;
        this.isDrawingBoundary = false;
        this.isEditingBoundary = false;
        this.isEditingBackground = false;

        // Map data
        this.boundary = null;           // Polygon object for boundary
        this.boundaryPoints = [];       // Points array while drawing
        this.grayMask = null;           // Gray mask outside boundary
        this.backgroundImage = null;    // Background image object
        this.anchorPoints = [];         // Anchor point circles for editing
        this.previewLine = null;        // Preview line while drawing
        this.contextMenu = null;        // Context menu element

        // DOM elements
        this.elements = {};

        // Initialize
        this.init();
    }

    /**
     * Initialize the editor
     */
    init() {
        console.log('MapEditor v2 initializing...');

        // Cache DOM elements
        this.cacheElements();

        // Setup canvas
        this.setupCanvas();

        // Setup event listeners
        this.setupEventListeners();

        // Load existing map data if entity is selected
        if (this.config.entityId) {
            this.loadMapData();
        }

        // Update UI state
        this.updateUIState();

        console.log('MapEditor v2 ready!');
        this.setStatus('מוכן');
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.elements = {
            canvasContainer: document.getElementById('canvasContainer'),
            canvas: document.getElementById('mapCanvas'),
            statusBar: document.getElementById('statusBar'),
            statusText: document.getElementById('statusText'),
            zoomDisplay: document.getElementById('zoomDisplay'),
            editMenus: document.getElementById('editMenus'),
            fileInput: document.getElementById('fileInput'),
            pdfModal: document.getElementById('pdfModal'),
            pdfPages: document.getElementById('pdfPages'),

            // Buttons
            btnZoomIn: document.getElementById('btnZoomIn'),
            btnZoomOut: document.getElementById('btnZoomOut'),
            btnZoomFit: document.getElementById('btnZoomFit'),
            btnEditMode: document.getElementById('btnEditMode'),
            btnSave: document.getElementById('btnSave'),

            // Boundary menu
            btnBoundaryMenu: document.getElementById('btnBoundaryMenu'),
            boundaryMenu: document.getElementById('boundaryMenu'),
            btnAddBoundary: document.getElementById('btnAddBoundary'),
            btnEditBoundary: document.getElementById('btnEditBoundary'),
            btnRemoveBoundary: document.getElementById('btnRemoveBoundary'),

            // Background menu
            btnBackgroundMenu: document.getElementById('btnBackgroundMenu'),
            backgroundMenu: document.getElementById('backgroundMenu'),
            btnAddBackground: document.getElementById('btnAddBackground'),
            btnEditBackground: document.getElementById('btnEditBackground'),
            btnRemoveBackground: document.getElementById('btnRemoveBackground'),

            closePdfModal: document.getElementById('closePdfModal')
        };
    }

    /**
     * Setup Fabric.js canvas
     */
    setupCanvas() {
        const container = this.elements.canvasContainer;
        const width = container.clientWidth;
        const height = container.clientHeight;

        this.canvas = new fabric.Canvas('mapCanvas', {
            width: width,
            height: height,
            backgroundColor: '#f8fafc',
            selection: false
        });

        // Handle resize
        window.addEventListener('resize', () => this.resizeCanvas());
    }

    /**
     * Resize canvas to fit container
     */
    resizeCanvas() {
        const container = this.elements.canvasContainer;
        this.canvas.setDimensions({
            width: container.clientWidth,
            height: container.clientHeight
        });
        this.canvas.renderAll();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Zoom controls
        this.elements.btnZoomIn.addEventListener('click', () => this.zoomIn());
        this.elements.btnZoomOut.addEventListener('click', () => this.zoomOut());
        this.elements.btnZoomFit.addEventListener('click', () => this.zoomFit());

        // Mouse wheel zoom
        this.canvas.on('mouse:wheel', (opt) => this.handleMouseWheel(opt));

        // Edit mode toggle
        this.elements.btnEditMode.addEventListener('click', () => this.toggleEditMode());

        // Save button
        this.elements.btnSave.addEventListener('click', () => this.saveMapData());

        // Dropdown menus
        this.setupDropdownMenu('btnBoundaryMenu', 'boundaryMenu');
        this.setupDropdownMenu('btnBackgroundMenu', 'backgroundMenu');

        // Boundary actions
        this.elements.btnAddBoundary.addEventListener('click', () => this.startDrawingBoundary());
        this.elements.btnEditBoundary.addEventListener('click', () => this.startEditingBoundary());
        this.elements.btnRemoveBoundary.addEventListener('click', () => this.removeBoundary());

        // Background actions
        this.elements.btnAddBackground.addEventListener('click', () => this.openFileDialog());
        this.elements.btnEditBackground.addEventListener('click', () => this.toggleBackgroundEditing());
        this.elements.btnRemoveBackground.addEventListener('click', () => this.removeBackground());

        // File input
        this.elements.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));

        // PDF modal
        this.elements.closePdfModal.addEventListener('click', () => this.closePdfModal());

        // Canvas events for drawing
        this.canvas.on('mouse:down', (opt) => this.handleCanvasClick(opt));
        this.canvas.on('mouse:move', (opt) => this.handleCanvasMouseMove(opt));
        this.canvas.on('mouse:dblclick', () => this.handleCanvasDoubleClick());

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => this.handleDocumentClick(e));

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));

        // Right-click context menu on canvas container
        this.elements.canvasContainer.addEventListener('contextmenu', (e) => {
            if (this.isEditingBoundary) {
                e.preventDefault();
                e.stopPropagation();
                this.handleRightClick(e);
            }
        });

        // Double-click on boundary edge to add anchor point
        this.canvas.on('mouse:dblclick', (opt) => {
            if (this.isEditingBoundary && !this.isDrawingBoundary) {
                this.handleBoundaryDoubleClick(opt);
            }
        });
    }

    /**
     * Setup dropdown menu
     */
    setupDropdownMenu(buttonId, menuId) {
        const button = this.elements[buttonId];
        const dropdown = button.closest('.dropdown');

        button.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close other dropdowns
            document.querySelectorAll('.dropdown.open').forEach(d => {
                if (d !== dropdown) d.classList.remove('open');
            });
            dropdown.classList.toggle('open');
        });
    }

    /**
     * Handle document click (close dropdowns)
     */
    handleDocumentClick(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
        }
        // Close context menu on click outside
        if (this.contextMenu && !e.target.closest('.context-menu')) {
            this.hideContextMenu();
        }
    }

    /**
     * Handle right-click on anchor point
     */
    handleRightClick(e) {
        // Get canvas pointer from browser event
        const rect = this.canvas.upperCanvasEl.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Account for canvas zoom and pan
        const zoom = this.canvas.getZoom();
        const vpt = this.canvas.viewportTransform;
        const canvasX = (x - vpt[4]) / zoom;
        const canvasY = (y - vpt[5]) / zoom;

        // Find anchor point at this position
        let target = null;
        for (const anchor of this.anchorPoints) {
            const dx = canvasX - anchor.left;
            const dy = canvasY - anchor.top;
            const distance = Math.sqrt(dx * dx + dy * dy);
            if (distance <= anchor.radius + 5) {
                target = anchor;
                break;
            }
        }

        if (!target || !target.isAnchorPoint) return;

        this.showContextMenu(e.clientX, e.clientY, target);
    }

    /**
     * Show context menu
     */
    showContextMenu(x, y, anchorPoint) {
        this.hideContextMenu();

        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'context-menu';
        this.contextMenu.innerHTML = `
            <button class="context-menu-item danger" data-action="remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                הסר נקודת עיגון
            </button>
        `;

        // Position the menu
        this.contextMenu.style.left = `${x}px`;
        this.contextMenu.style.top = `${y}px`;

        // Add click handler
        this.contextMenu.querySelector('[data-action="remove"]').addEventListener('click', () => {
            this.removeAnchorPointByObject(anchorPoint);
            this.hideContextMenu();
        });

        document.body.appendChild(this.contextMenu);

        // Adjust position if menu goes off screen
        const rect = this.contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            this.contextMenu.style.left = `${x - rect.width}px`;
        }
        if (rect.bottom > window.innerHeight) {
            this.contextMenu.style.top = `${y - rect.height}px`;
        }
    }

    /**
     * Hide context menu
     */
    hideContextMenu() {
        if (this.contextMenu) {
            this.contextMenu.remove();
            this.contextMenu = null;
        }
    }

    /**
     * Remove anchor point by object reference
     */
    removeAnchorPointByObject(anchorPoint) {
        if (this.boundary.points.length <= 3) {
            alert('לא ניתן למחוק - גבול חייב להכיל לפחות 3 נקודות');
            return;
        }

        const index = anchorPoint.pointIndex;

        // Remove point from boundary
        this.boundary.points.splice(index, 1);
        this.boundary.set({ dirty: true });

        // Refresh anchor points
        this.showAnchorPoints();

        // Update gray mask
        this.updateGrayMask();

        this.setStatus(`נקודה ${index + 1} נמחקה`, 'editing');
    }

    /**
     * Handle double-click on boundary edge to add anchor point
     */
    handleBoundaryDoubleClick(opt) {
        if (!this.boundary) return;

        const pointer = this.canvas.getPointer(opt.e);
        const clickPoint = { x: pointer.x, y: pointer.y };

        // Find the closest edge using transformation matrix
        const points = this.boundary.points;
        const matrix = this.boundary.calcTransformMatrix();
        const pathOffset = this.boundary.pathOffset || { x: 0, y: 0 };

        let closestEdgeIndex = -1;
        let minDistance = Infinity;

        for (let i = 0; i < points.length; i++) {
            // Transform points to absolute coordinates
            const t1 = fabric.util.transformPoint(
                { x: points[i].x - pathOffset.x, y: points[i].y - pathOffset.y },
                matrix
            );
            const t2 = fabric.util.transformPoint(
                { x: points[(i + 1) % points.length].x - pathOffset.x, y: points[(i + 1) % points.length].y - pathOffset.y },
                matrix
            );

            const dist = this.pointToLineDistance(clickPoint, t1, t2);
            if (dist < minDistance) {
                minDistance = dist;
                closestEdgeIndex = i;
            }
        }

        // Only add point if click is close to an edge (within 20 pixels)
        if (minDistance < 20 && closestEdgeIndex !== -1) {
            this.addAnchorPoint(closestEdgeIndex);
        }
    }

    /**
     * Calculate distance from point to line segment
     */
    pointToLineDistance(point, lineStart, lineEnd) {
        const A = point.x - lineStart.x;
        const B = point.y - lineStart.y;
        const C = lineEnd.x - lineStart.x;
        const D = lineEnd.y - lineStart.y;

        const dot = A * C + B * D;
        const lenSq = C * C + D * D;
        let param = -1;

        if (lenSq !== 0) {
            param = dot / lenSq;
        }

        let xx, yy;

        if (param < 0) {
            xx = lineStart.x;
            yy = lineStart.y;
        } else if (param > 1) {
            xx = lineEnd.x;
            yy = lineEnd.y;
        } else {
            xx = lineStart.x + param * C;
            yy = lineStart.y + param * D;
        }

        const dx = point.x - xx;
        const dy = point.y - yy;

        return Math.sqrt(dx * dx + dy * dy);
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyDown(e) {
        // Escape - cancel current action
        if (e.key === 'Escape') {
            if (this.isDrawingBoundary) {
                this.cancelDrawingBoundary();
            } else if (this.isEditingBoundary) {
                this.stopEditingBoundary();
            } else if (this.isEditingBackground) {
                this.toggleBackgroundEditing();
            }
        }

        // Delete - remove selected anchor point
        if (e.key === 'Delete' && this.isEditingBoundary) {
            this.removeSelectedAnchorPoint();
        }
    }

    // ============================================
    // ZOOM CONTROLS
    // ============================================

    zoomIn() {
        const zoom = this.canvas.getZoom() * 1.2;
        this.canvas.setZoom(Math.min(zoom, 5));
        this.updateZoomDisplay();
    }

    zoomOut() {
        const zoom = this.canvas.getZoom() / 1.2;
        this.canvas.setZoom(Math.max(zoom, 0.1));
        this.updateZoomDisplay();
    }

    zoomFit() {
        this.canvas.setZoom(1);
        this.canvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        this.updateZoomDisplay();
    }

    handleMouseWheel(opt) {
        const delta = opt.e.deltaY;
        let zoom = this.canvas.getZoom();
        zoom *= 0.999 ** delta;
        zoom = Math.min(Math.max(zoom, 0.1), 5);

        this.canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
        this.updateZoomDisplay();

        opt.e.preventDefault();
        opt.e.stopPropagation();
    }

    updateZoomDisplay() {
        const zoom = Math.round(this.canvas.getZoom() * 100);
        this.elements.zoomDisplay.textContent = `${zoom}%`;
    }

    // ============================================
    // EDIT MODE
    // ============================================

    toggleEditMode() {
        this.isEditMode = !this.isEditMode;
        this.elements.btnEditMode.classList.toggle('active', this.isEditMode);
        this.elements.editMenus.style.display = this.isEditMode ? 'flex' : 'none';

        if (!this.isEditMode) {
            // Exit all editing modes
            if (this.isDrawingBoundary) this.cancelDrawingBoundary();
            if (this.isEditingBoundary) this.stopEditingBoundary();
            if (this.isEditingBackground) this.toggleBackgroundEditing();
        }

        this.updateUIState();
        this.setStatus(this.isEditMode ? 'מצב עריכה פעיל' : 'מצב צפייה');
    }

    // ============================================
    // BOUNDARY - DRAWING
    // ============================================

    startDrawingBoundary() {
        if (this.boundary) {
            alert('כבר קיים גבול. יש להסירו לפני הוספת גבול חדש.');
            return;
        }

        this.isDrawingBoundary = true;
        this.boundaryPoints = [];
        this.elements.canvasContainer.classList.add('drawing-mode');

        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));

        this.setStatus('לחץ על הקנבס להוספת נקודות עיגון. לחיצה כפולה לסגירת הגבול.', 'drawing');
        this.updateUIState();
    }

    handleCanvasClick(opt) {
        if (!this.isDrawingBoundary) return;

        const pointer = this.canvas.getPointer(opt.e);
        const point = { x: pointer.x, y: pointer.y };

        // Add point
        this.boundaryPoints.push(point);

        // Draw anchor point
        const circle = new fabric.Circle({
            left: point.x,
            top: point.y,
            radius: 6,
            fill: '#3b82f6',
            stroke: '#fff',
            strokeWidth: 2,
            originX: 'center',
            originY: 'center',
            selectable: false,
            evented: false,
            isAnchorPreview: true
        });
        this.canvas.add(circle);

        // Draw line from previous point
        if (this.boundaryPoints.length > 1) {
            const prev = this.boundaryPoints[this.boundaryPoints.length - 2];
            const line = new fabric.Line([prev.x, prev.y, point.x, point.y], {
                stroke: '#3b82f6',
                strokeWidth: 2,
                selectable: false,
                evented: false,
                isLinePreview: true
            });
            this.canvas.add(line);
        }

        this.canvas.renderAll();
        this.setStatus(`נוספה נקודה ${this.boundaryPoints.length}. לחץ כפול לסגירת הגבול.`, 'drawing');
    }

    handleCanvasMouseMove(opt) {
        if (!this.isDrawingBoundary || this.boundaryPoints.length === 0) return;

        const pointer = this.canvas.getPointer(opt.e);
        const lastPoint = this.boundaryPoints[this.boundaryPoints.length - 1];

        // Remove previous preview line
        if (this.previewLine) {
            this.canvas.remove(this.previewLine);
        }

        // Draw preview line (dashed)
        this.previewLine = new fabric.Line([lastPoint.x, lastPoint.y, pointer.x, pointer.y], {
            stroke: '#3b82f6',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            selectable: false,
            evented: false
        });
        this.canvas.add(this.previewLine);
        this.canvas.renderAll();
    }

    handleCanvasDoubleClick() {
        if (!this.isDrawingBoundary) return;

        if (this.boundaryPoints.length < 3) {
            alert('נדרשות לפחות 3 נקודות ליצירת גבול');
            return;
        }

        this.finishDrawingBoundary();
    }

    finishDrawingBoundary() {
        // Remove preview elements
        this.canvas.getObjects().forEach(obj => {
            if (obj.isAnchorPreview || obj.isLinePreview) {
                this.canvas.remove(obj);
            }
        });
        if (this.previewLine) {
            this.canvas.remove(this.previewLine);
            this.previewLine = null;
        }

        // Create boundary polygon
        this.boundary = new fabric.Polygon(this.boundaryPoints, {
            fill: 'transparent',
            stroke: '#3b82f6',
            strokeWidth: 3,
            selectable: false,
            evented: false,
            objectCaching: false,
            isBoundary: true
        });
        this.canvas.add(this.boundary);

        // Create gray mask
        this.createGrayMask();

        // Reset drawing state
        this.isDrawingBoundary = false;
        this.elements.canvasContainer.classList.remove('drawing-mode');

        this.updateUIState();
        this.setStatus('הגבול נוצר בהצלחה!', 'success');

        setTimeout(() => this.setStatus('מוכן'), 2000);
    }

    cancelDrawingBoundary() {
        // Remove preview elements
        this.canvas.getObjects().forEach(obj => {
            if (obj.isAnchorPreview || obj.isLinePreview) {
                this.canvas.remove(obj);
            }
        });
        if (this.previewLine) {
            this.canvas.remove(this.previewLine);
            this.previewLine = null;
        }

        this.isDrawingBoundary = false;
        this.boundaryPoints = [];
        this.elements.canvasContainer.classList.remove('drawing-mode');

        this.canvas.renderAll();
        this.updateUIState();
        this.setStatus('ציור הגבול בוטל');
    }

    // ============================================
    // BOUNDARY - GRAY MASK
    // ============================================

    createGrayMask() {
        if (!this.boundary || !this.boundaryPoints.length) return;

        // Remove existing mask
        if (this.grayMask) {
            this.canvas.remove(this.grayMask);
        }

        const canvasWidth = this.canvas.width * 10;
        const canvasHeight = this.canvas.height * 10;

        // Create outer rectangle
        const outerRect = [
            { x: -canvasWidth, y: -canvasHeight },
            { x: canvasWidth * 2, y: -canvasHeight },
            { x: canvasWidth * 2, y: canvasHeight * 2 },
            { x: -canvasWidth, y: canvasHeight * 2 }
        ];

        // Merge points: outer rectangle + inner polygon (reversed)
        const allPoints = [
            ...outerRect,
            { x: -canvasWidth, y: -canvasHeight }, // Close outer rect
            ...this.boundaryPoints.slice().reverse(),
            this.boundaryPoints[this.boundaryPoints.length - 1]
        ];

        this.grayMask = new fabric.Polygon(allPoints, {
            fill: 'rgba(0, 0, 0, 0.6)',
            selectable: false,
            evented: false,
            objectCaching: false,
            isGrayMask: true
        });

        this.canvas.add(this.grayMask);

        // Ensure correct layer order
        this.reorderLayers();
    }

    updateGrayMask() {
        if (!this.boundary) return;

        // Get current boundary points using transformation matrix
        const matrix = this.boundary.calcTransformMatrix();
        const pathOffset = this.boundary.pathOffset || { x: 0, y: 0 };

        this.boundaryPoints = this.boundary.points.map(p => {
            const transformed = fabric.util.transformPoint(
                { x: p.x - pathOffset.x, y: p.y - pathOffset.y },
                matrix
            );
            return { x: transformed.x, y: transformed.y };
        });

        // Recreate mask
        this.createGrayMask();
    }

    // ============================================
    // BOUNDARY - EDITING
    // ============================================

    startEditingBoundary() {
        if (!this.boundary) return;

        this.isEditingBoundary = true;
        this.elements.canvasContainer.classList.add('editing-mode');

        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));

        // Make boundary selectable and draggable
        this.boundary.set({
            selectable: true,
            evented: true,
            hasControls: false,
            hasBorders: true,
            borderColor: '#3b82f6',
            borderDashArray: [5, 5],
            hoverCursor: 'move'
        });

        // Handle boundary movement
        this.boundary.on('moving', () => this.onBoundaryMove());
        this.boundary.on('modified', () => this.onBoundaryModified());

        // Show anchor points
        this.showAnchorPoints();

        this.setStatus('לחץ על הגבול לבחירה וגרירה. גרור נקודות עיגון לעריכה. Escape לסיום.', 'editing');
        this.updateUIState();
    }

    showAnchorPoints() {
        this.clearAnchorPoints();

        if (!this.boundary) return;

        const points = this.boundary.points;
        // Use Fabric.js transformation matrix to get correct absolute coordinates
        const matrix = this.boundary.calcTransformMatrix();
        const pathOffset = this.boundary.pathOffset || { x: 0, y: 0 };

        points.forEach((point, index) => {
            // Transform point using polygon's matrix
            const transformed = fabric.util.transformPoint(
                { x: point.x - pathOffset.x, y: point.y - pathOffset.y },
                matrix
            );
            const absX = transformed.x;
            const absY = transformed.y;

            const circle = new fabric.Circle({
                left: absX,
                top: absY,
                radius: 8,
                fill: '#fff',
                stroke: '#3b82f6',
                strokeWidth: 2,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                hasControls: false,
                hasBorders: false,
                isAnchorPoint: true,
                pointIndex: index,
                hoverCursor: 'move'
            });

            // Handle dragging
            circle.on('moving', () => this.onAnchorPointMove(circle));
            circle.on('modified', () => this.onAnchorPointModified());

            this.anchorPoints.push(circle);
            this.canvas.add(circle);
        });

        this.canvas.renderAll();
    }

    onAnchorPointMove(circle) {
        const index = circle.pointIndex;
        const pathOffset = this.boundary.pathOffset || { x: 0, y: 0 };

        // Get inverse transformation matrix to convert screen coords back to polygon coords
        const matrix = this.boundary.calcTransformMatrix();
        const invertedMatrix = fabric.util.invertTransform(matrix);

        // Transform anchor point position back to polygon coordinate space
        const transformed = fabric.util.transformPoint(
            { x: circle.left, y: circle.top },
            invertedMatrix
        );

        // Update boundary point
        this.boundary.points[index] = {
            x: transformed.x + pathOffset.x,
            y: transformed.y + pathOffset.y
        };

        // Update boundary path
        this.boundary.set({ dirty: true });

        // Update gray mask
        this.updateGrayMask();

        this.canvas.renderAll();
    }

    onAnchorPointModified() {
        this.canvas.renderAll();
    }

    /**
     * Handle boundary movement - update gray mask and anchor points
     */
    onBoundaryMove() {
        // Update gray mask to follow boundary
        this.updateGrayMask();

        // Update anchor points positions
        this.showAnchorPoints();

        this.canvas.renderAll();
    }

    /**
     * Handle boundary modification complete
     */
    onBoundaryModified() {
        this.updateGrayMask();
        this.showAnchorPoints();
        this.canvas.renderAll();
    }

    clearAnchorPoints() {
        this.anchorPoints.forEach(circle => {
            circle.off('moving');
            circle.off('modified');
            this.canvas.remove(circle);
        });
        this.anchorPoints = [];
    }

    removeSelectedAnchorPoint() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject || !activeObject.isAnchorPoint) return;

        if (this.boundary.points.length <= 3) {
            alert('לא ניתן למחוק - גבול חייב להכיל לפחות 3 נקודות');
            return;
        }

        const index = activeObject.pointIndex;

        // Remove point from boundary
        this.boundary.points.splice(index, 1);
        this.boundary.set({ dirty: true });

        // Refresh anchor points
        this.showAnchorPoints();

        // Update gray mask
        this.updateGrayMask();

        this.setStatus(`נקודה ${index + 1} נמחקה`, 'editing');
    }

    addAnchorPoint(afterIndex) {
        const points = this.boundary.points;
        const nextIndex = (afterIndex + 1) % points.length;

        const p1 = points[afterIndex];
        const p2 = points[nextIndex];

        const newPoint = {
            x: (p1.x + p2.x) / 2,
            y: (p1.y + p2.y) / 2
        };

        points.splice(afterIndex + 1, 0, newPoint);
        this.boundary.set({ dirty: true });

        // Refresh anchor points
        this.showAnchorPoints();

        // Update gray mask
        this.updateGrayMask();

        this.setStatus(`נקודה חדשה נוספה`, 'editing');
    }

    stopEditingBoundary() {
        this.isEditingBoundary = false;
        this.elements.canvasContainer.classList.remove('editing-mode');

        // Reset boundary to non-selectable
        if (this.boundary) {
            this.boundary.off('moving');
            this.boundary.off('modified');
            this.boundary.set({
                selectable: false,
                evented: false,
                hasBorders: false
            });
        }

        this.clearAnchorPoints();
        this.canvas.discardActiveObject();
        this.canvas.renderAll();

        this.updateUIState();
        this.setStatus('עריכת הגבול הסתיימה');
    }

    // ============================================
    // BOUNDARY - REMOVE
    // ============================================

    removeBoundary() {
        if (!this.boundary) return;

        if (!confirm('האם להסיר את הגבול?')) return;

        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));

        // Stop editing if active
        if (this.isEditingBoundary) {
            this.stopEditingBoundary();
        }

        // Remove boundary
        this.canvas.remove(this.boundary);
        this.boundary = null;
        this.boundaryPoints = [];

        // Remove gray mask
        if (this.grayMask) {
            this.canvas.remove(this.grayMask);
            this.grayMask = null;
        }

        this.canvas.renderAll();
        this.updateUIState();
        this.setStatus('הגבול הוסר', 'success');
    }

    // ============================================
    // BACKGROUND IMAGE
    // ============================================

    openFileDialog() {
        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));

        this.elements.fileInput.click();
    }

    handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        if (file.type === 'application/pdf') {
            this.handlePdfFile(file);
        } else if (file.type.startsWith('image/')) {
            this.handleImageFile(file);
        } else {
            alert('סוג קובץ לא נתמך. יש להעלות תמונה או PDF.');
        }

        // Reset input
        this.elements.fileInput.value = '';
    }

    handleImageFile(file) {
        const reader = new FileReader();

        reader.onload = (e) => {
            fabric.Image.fromURL(e.target.result, (img) => {
                this.setBackgroundImage(img);
            });
        };

        reader.readAsDataURL(file);
    }

    async handlePdfFile(file) {
        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

            if (pdf.numPages === 1) {
                // Single page - use directly
                const pageImage = await this.renderPdfPage(pdf, 1);
                this.setBackgroundImage(pageImage);
            } else {
                // Multiple pages - show selector
                this.showPdfPageSelector(pdf);
            }
        } catch (error) {
            console.error('Error loading PDF:', error);
            alert('שגיאה בטעינת קובץ PDF');
        }
    }

    async renderPdfPage(pdf, pageNum) {
        const page = await pdf.getPage(pageNum);
        const scale = 2;
        const viewport = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const context = canvas.getContext('2d');
        await page.render({ canvasContext: context, viewport }).promise;

        return new Promise((resolve) => {
            fabric.Image.fromURL(canvas.toDataURL(), (img) => {
                resolve(img);
            });
        });
    }

    async showPdfPageSelector(pdf) {
        this.elements.pdfPages.innerHTML = '';

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const scale = 0.5;
            const viewport = page.getViewport({ scale });

            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            const context = canvas.getContext('2d');
            await page.render({ canvasContext: context, viewport }).promise;

            const pageDiv = document.createElement('div');
            pageDiv.className = 'pdf-page';
            pageDiv.innerHTML = `
                <img src="${canvas.toDataURL()}" alt="עמוד ${i}">
                <div class="pdf-page-number">עמוד ${i}</div>
            `;
            pageDiv.addEventListener('click', async () => {
                const pageImage = await this.renderPdfPage(pdf, i);
                this.setBackgroundImage(pageImage);
                this.closePdfModal();
            });

            this.elements.pdfPages.appendChild(pageDiv);
        }

        this.elements.pdfModal.style.display = 'flex';
    }

    closePdfModal() {
        this.elements.pdfModal.style.display = 'none';
    }

    setBackgroundImage(img) {
        // Remove existing background
        if (this.backgroundImage) {
            this.canvas.remove(this.backgroundImage);
        }

        // Scale to fit canvas
        const maxWidth = this.canvas.width * 0.9;
        const maxHeight = this.canvas.height * 0.9;
        const scale = Math.min(maxWidth / img.width, maxHeight / img.height);

        img.set({
            left: this.canvas.width / 2,
            top: this.canvas.height / 2,
            originX: 'center',
            originY: 'center',
            scaleX: scale,
            scaleY: scale,
            selectable: false,
            evented: false,
            isBackgroundImage: true
        });

        this.backgroundImage = img;
        this.canvas.add(img);

        // Ensure correct layer order
        this.reorderLayers();

        this.updateUIState();
        this.setStatus('תמונת רקע נוספה בהצלחה!', 'success');

        setTimeout(() => this.setStatus('מוכן'), 2000);
    }

    toggleBackgroundEditing() {
        if (!this.backgroundImage) return;

        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));

        this.isEditingBackground = !this.isEditingBackground;

        this.backgroundImage.set({
            selectable: this.isEditingBackground,
            evented: this.isEditingBackground,
            hasControls: this.isEditingBackground,
            hasBorders: this.isEditingBackground
        });

        if (this.isEditingBackground) {
            this.canvas.setActiveObject(this.backgroundImage);
            this.setStatus('גרור ושנה גודל לתמונת הרקע. Escape לסיום.', 'editing');
        } else {
            this.canvas.discardActiveObject();
            this.setStatus('עריכת הרקע הסתיימה');
        }

        this.canvas.renderAll();
        this.updateUIState();
    }

    removeBackground() {
        if (!this.backgroundImage) return;

        if (!confirm('האם להסיר את תמונת הרקע?')) return;

        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));

        // Stop editing if active
        if (this.isEditingBackground) {
            this.isEditingBackground = false;
        }

        this.canvas.remove(this.backgroundImage);
        this.backgroundImage = null;

        this.canvas.renderAll();
        this.updateUIState();
        this.setStatus('תמונת הרקע הוסרה', 'success');
    }

    // ============================================
    // LAYER ORDER
    // ============================================

    reorderLayers() {
        // Order: background -> boundary -> gray mask -> anchor points
        if (this.backgroundImage) {
            this.canvas.sendToBack(this.backgroundImage);
        }

        if (this.boundary) {
            this.canvas.bringToFront(this.boundary);
        }

        if (this.grayMask) {
            this.canvas.bringToFront(this.grayMask);
        }

        this.anchorPoints.forEach(point => {
            this.canvas.bringToFront(point);
        });

        this.canvas.renderAll();
    }

    // ============================================
    // UI STATE
    // ============================================

    updateUIState() {
        const hasBoundary = !!this.boundary;
        const hasBackground = !!this.backgroundImage;

        // Boundary buttons
        this.elements.btnAddBoundary.disabled = hasBoundary || this.isDrawingBoundary;
        this.elements.btnEditBoundary.disabled = !hasBoundary || this.isDrawingBoundary;
        this.elements.btnRemoveBoundary.disabled = !hasBoundary || this.isDrawingBoundary;

        // Background buttons
        this.elements.btnAddBackground.disabled = hasBackground;
        this.elements.btnEditBackground.disabled = !hasBackground;
        this.elements.btnRemoveBackground.disabled = !hasBackground;

        // Update button states visually
        if (this.isEditingBoundary) {
            this.elements.btnEditBoundary.classList.add('active');
        } else {
            this.elements.btnEditBoundary.classList.remove('active');
        }

        if (this.isEditingBackground) {
            this.elements.btnEditBackground.classList.add('active');
        } else {
            this.elements.btnEditBackground.classList.remove('active');
        }
    }

    setStatus(text, type = '') {
        this.elements.statusText.textContent = text;
        this.elements.statusBar.className = 'status-bar';
        if (type) {
            this.elements.statusBar.classList.add(type);
        }
    }

    // ============================================
    // SAVE / LOAD
    // ============================================

    async loadMapData() {
        if (!this.config.entityType || !this.config.entityId) return;

        try {
            this.setStatus('טוען נתוני מפה...');

            const response = await fetch(`${this.config.apiBase}map-data.php?action=load&type=${this.config.entityType}&id=${this.config.entityId}`);
            const data = await response.json();

            if (data.success && data.mapData) {
                this.restoreMapData(data.mapData);
                this.setStatus('נתוני המפה נטענו', 'success');
            } else {
                this.setStatus('אין נתוני מפה קיימים');
            }
        } catch (error) {
            console.error('Error loading map data:', error);
            this.setStatus('שגיאה בטעינת נתוני מפה');
        }
    }

    restoreMapData(mapData) {
        // Restore boundary
        if (mapData.boundary && mapData.boundary.points) {
            this.boundaryPoints = mapData.boundary.points;

            this.boundary = new fabric.Polygon(this.boundaryPoints, {
                fill: 'transparent',
                stroke: '#3b82f6',
                strokeWidth: 3,
                selectable: false,
                evented: false,
                objectCaching: false,
                isBoundary: true
            });
            this.canvas.add(this.boundary);

            this.createGrayMask();
        }

        // Restore background image
        if (mapData.background && mapData.background.src) {
            fabric.Image.fromURL(mapData.background.src, (img) => {
                img.set({
                    left: mapData.background.left || this.canvas.width / 2,
                    top: mapData.background.top || this.canvas.height / 2,
                    originX: 'center',
                    originY: 'center',
                    scaleX: mapData.background.scaleX || 1,
                    scaleY: mapData.background.scaleY || 1,
                    angle: mapData.background.angle || 0,
                    selectable: false,
                    evented: false,
                    isBackgroundImage: true
                });

                this.backgroundImage = img;
                this.canvas.add(img);
                this.reorderLayers();
            });
        }

        this.updateUIState();
    }

    async saveMapData() {
        if (!this.config.entityType || !this.config.entityId) {
            alert('לא נבחרה ישות לשמירה');
            return;
        }

        try {
            this.elements.btnSave.disabled = true;
            this.setStatus('שומר...');

            const mapData = this.getMapData();

            const response = await fetch(`${this.config.apiBase}map-data.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save',
                    type: this.config.entityType,
                    id: this.config.entityId,
                    mapData: mapData
                })
            });

            const result = await response.json();

            if (result.success) {
                this.setStatus('נשמר בהצלחה!', 'success');
            } else {
                throw new Error(result.error || 'שגיאה בשמירה');
            }
        } catch (error) {
            console.error('Error saving map data:', error);
            this.setStatus('שגיאה בשמירה: ' + error.message);
        } finally {
            this.elements.btnSave.disabled = false;
            setTimeout(() => this.setStatus('מוכן'), 2000);
        }
    }

    getMapData() {
        const mapData = {};

        // Boundary data
        if (this.boundary) {
            mapData.boundary = {
                points: this.boundaryPoints
            };
        }

        // Background data
        if (this.backgroundImage) {
            mapData.background = {
                src: this.backgroundImage.toDataURL(),
                left: this.backgroundImage.left,
                top: this.backgroundImage.top,
                scaleX: this.backgroundImage.scaleX,
                scaleY: this.backgroundImage.scaleY,
                angle: this.backgroundImage.angle || 0
            };
        }

        return mapData;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.mapEditor = new MapEditor(MAP_CONFIG);
});
