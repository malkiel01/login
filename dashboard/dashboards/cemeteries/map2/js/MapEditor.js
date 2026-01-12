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

        // Floating panels state
        this.panels = {
            textStyle: { visible: false, position: { x: 20, y: 100 } },
            elementStyle: { visible: false, position: { x: 20, y: 100 } },
            layers: { visible: false, position: { x: 20, y: 100 } }
        };
        this.draggedPanel = null;
        this.dragOffset = { x: 0, y: 0 };

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

            closePdfModal: document.getElementById('closePdfModal'),

            // Windows menu
            btnWindowsMenu: document.getElementById('btnWindowsMenu'),
            btnTextStylePanel: document.getElementById('btnTextStylePanel'),
            btnElementStylePanel: document.getElementById('btnElementStylePanel'),
            btnLayersPanel: document.getElementById('btnLayersPanel'),

            // Floating panels
            textStylePanel: document.getElementById('textStylePanel'),
            elementStylePanel: document.getElementById('elementStylePanel'),
            layersPanel: document.getElementById('layersPanel'),

            // Text style controls
            textControls: document.getElementById('textControls'),
            textPanelMessage: document.getElementById('textPanelMessage'),
            fontFamily: document.getElementById('fontFamily'),
            fontSize: document.getElementById('fontSize'),
            fontColor: document.getElementById('fontColor'),
            letterSpacing: document.getElementById('letterSpacing'),

            // Element style controls
            elementControls: document.getElementById('elementControls'),
            elementPanelMessage: document.getElementById('elementPanelMessage'),
            strokeWidth: document.getElementById('strokeWidth'),
            strokeColor: document.getElementById('strokeColor'),
            strokeStyle: document.getElementById('strokeStyle'),

            // Layers
            layersList: document.getElementById('layersList')
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
        this.elements.btnEditBoundary.addEventListener('click', () => this.toggleEditingBoundary());
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
            e.preventDefault();
            e.stopPropagation();
            this.handleRightClick(e);
        });

        // Double-click on boundary edge to add anchor point
        this.canvas.on('mouse:dblclick', (opt) => {
            if (this.isEditingBoundary && !this.isDrawingBoundary) {
                this.handleBoundaryDoubleClick(opt);
            }
        });

        // Windows menu
        this.setupDropdownMenu('btnWindowsMenu', 'windowsMenu');

        // Panel toggle buttons
        this.elements.btnTextStylePanel.addEventListener('click', () => this.togglePanel('textStyle'));
        this.elements.btnElementStylePanel.addEventListener('click', () => this.togglePanel('elementStyle'));
        this.elements.btnLayersPanel.addEventListener('click', () => this.togglePanel('layers'));

        // Panel close buttons
        document.querySelectorAll('.floating-panel-close').forEach(btn => {
            btn.addEventListener('click', () => {
                const panelName = btn.dataset.panel;
                this.togglePanel(panelName);
            });
        });

        // Panel dragging
        document.querySelectorAll('.floating-panel-header').forEach(header => {
            header.addEventListener('mousedown', (e) => this.startPanelDrag(e));
        });
        document.addEventListener('mousemove', (e) => this.handlePanelDrag(e));
        document.addEventListener('mouseup', () => this.stopPanelDrag());

        // Text style controls
        this.elements.fontFamily.addEventListener('change', () => this.applyTextStyle());
        this.elements.fontSize.addEventListener('input', () => this.applyTextStyle());
        this.elements.fontColor.addEventListener('input', () => this.applyTextStyle());
        this.elements.letterSpacing.addEventListener('input', () => this.applyTextStyle());

        // Element style controls
        this.elements.strokeWidth.addEventListener('input', () => this.applyElementStyle());
        this.elements.strokeColor.addEventListener('input', () => this.applyElementStyle());
        this.elements.strokeStyle.addEventListener('change', () => this.applyElementStyle());

        // Canvas selection events for panels
        this.canvas.on('selection:created', () => this.onSelectionChanged());
        this.canvas.on('selection:updated', () => this.onSelectionChanged());
        this.canvas.on('selection:cleared', () => this.onSelectionCleared());

        // Update layers when objects added/removed
        this.canvas.on('object:added', () => this.updateLayersPanel());
        this.canvas.on('object:removed', () => this.updateLayersPanel());
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
     * Handle right-click on canvas
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

        // Find anchor point at this position (only when editing boundary)
        if (this.isEditingBoundary) {
            let anchorTarget = null;
            for (const anchor of this.anchorPoints) {
                const dx = canvasX - anchor.left;
                const dy = canvasY - anchor.top;
                const distance = Math.sqrt(dx * dx + dy * dy);
                if (distance <= anchor.radius + 5) {
                    anchorTarget = anchor;
                    break;
                }
            }

            // If clicked on anchor point, show anchor menu
            if (anchorTarget && anchorTarget.isAnchorPoint) {
                this.showAnchorContextMenu(e.clientX, e.clientY, anchorTarget);
                return;
            }
        }

        // Check if clicked on a map element
        const clickedObject = this.canvas.findTarget(e, false);
        if (clickedObject && clickedObject.isMapElement) {
            this.showElementContextMenu(e.clientX, e.clientY, clickedObject);
            return;
        }

        // Check if boundary exists
        if (!this.boundary) {
            // No boundary yet - show "not allowed" feedback
            this.showNotAllowedFeedback(e.clientX, e.clientY);
            return;
        }

        // Check if clicked inside boundary
        if (this.isPointInsideBoundary(canvasX, canvasY)) {
            this.showShapeContextMenu(e.clientX, e.clientY, canvasX, canvasY);
        } else {
            // Outside boundary - show "not allowed" feedback
            this.showNotAllowedFeedback(e.clientX, e.clientY);
        }
    }

    /**
     * Show context menu for map elements (remove option)
     */
    showElementContextMenu(screenX, screenY, element) {
        this.hideContextMenu();

        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'context-menu';
        this.contextMenu.innerHTML = `
            <button class="context-menu-item danger" data-action="remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                הסר אלמנט
            </button>
        `;

        // Position the menu
        this.contextMenu.style.left = `${screenX}px`;
        this.contextMenu.style.top = `${screenY}px`;

        // Add click handler
        this.contextMenu.querySelector('[data-action="remove"]').addEventListener('click', () => {
            this.canvas.remove(element);
            this.canvas.renderAll();
            this.setStatus('אלמנט הוסר');
            this.hideContextMenu();
        });

        document.body.appendChild(this.contextMenu);
        this.adjustContextMenuPosition(screenX, screenY);
    }

    /**
     * Show "not allowed" visual feedback
     */
    showNotAllowedFeedback(x, y) {
        // Create temporary "not allowed" indicator
        const indicator = document.createElement('div');
        indicator.className = 'not-allowed-indicator';
        indicator.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
        `;
        indicator.style.cssText = `
            position: fixed;
            left: ${x - 16}px;
            top: ${y - 16}px;
            width: 32px;
            height: 32px;
            color: #ef4444;
            pointer-events: none;
            z-index: 9999;
            animation: notAllowedFade 0.5s ease-out forwards;
        `;

        // Add animation style if not exists
        if (!document.getElementById('not-allowed-animation')) {
            const style = document.createElement('style');
            style.id = 'not-allowed-animation';
            style.textContent = `
                @keyframes notAllowedFade {
                    0% { opacity: 1; transform: scale(1); }
                    100% { opacity: 0; transform: scale(1.5); }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(indicator);

        // Remove after animation
        setTimeout(() => indicator.remove(), 500);
    }

    /**
     * Check if a point is inside the boundary polygon
     */
    isPointInsideBoundary(x, y) {
        if (!this.boundary) return false;

        const points = this.boundaryPoints;
        if (!points || points.length < 3) return false;

        let inside = false;
        for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
            const xi = points[i].x, yi = points[i].y;
            const xj = points[j].x, yj = points[j].y;

            if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }
        return inside;
    }

    /**
     * Show anchor point context menu
     */
    showAnchorContextMenu(x, y, anchorPoint) {
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
        this.adjustContextMenuPosition(x, y);
    }

    /**
     * Show shape context menu for adding elements
     */
    showShapeContextMenu(screenX, screenY, canvasX, canvasY) {
        this.hideContextMenu();

        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'context-menu';
        this.contextMenu.innerHTML = `
            <button class="context-menu-item" data-action="add-text">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="4 7 4 4 20 4 20 7"/>
                    <line x1="9" y1="20" x2="15" y2="20"/>
                    <line x1="12" y1="4" x2="12" y2="20"/>
                </svg>
                טקסט
            </button>
            <button class="context-menu-item" data-action="add-line">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="15 8 19 12 15 16"/>
                </svg>
                קו
            </button>
            <button class="context-menu-item" data-action="add-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9"/>
                    <circle cx="12" cy="12" r="3" fill="currentColor"/>
                </svg>
                עיגול
            </button>
            <button class="context-menu-item" data-action="add-rect">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                    <line x1="3" y1="10" x2="21" y2="10" stroke-dasharray="2 2"/>
                </svg>
                מלבן
            </button>
            <button class="context-menu-item" data-action="add-freedraw">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                    <path d="M2 2l7.586 7.586"/>
                    <circle cx="11" cy="11" r="2"/>
                </svg>
                ציור חופשי
            </button>
        `;

        // Position the menu
        this.contextMenu.style.left = `${screenX}px`;
        this.contextMenu.style.top = `${screenY}px`;

        // Add click handlers
        this.contextMenu.querySelector('[data-action="add-text"]').addEventListener('click', () => {
            this.addTextElement(canvasX, canvasY);
            this.hideContextMenu();
        });
        this.contextMenu.querySelector('[data-action="add-line"]').addEventListener('click', () => {
            this.addLineElement(canvasX, canvasY);
            this.hideContextMenu();
        });
        this.contextMenu.querySelector('[data-action="add-circle"]').addEventListener('click', () => {
            this.addCircleElement(canvasX, canvasY);
            this.hideContextMenu();
        });
        this.contextMenu.querySelector('[data-action="add-rect"]').addEventListener('click', () => {
            this.addRectElement(canvasX, canvasY);
            this.hideContextMenu();
        });
        this.contextMenu.querySelector('[data-action="add-freedraw"]').addEventListener('click', () => {
            this.startFreeDrawing();
            this.hideContextMenu();
        });

        document.body.appendChild(this.contextMenu);
        this.adjustContextMenuPosition(screenX, screenY);
    }

    /**
     * Adjust context menu position if it goes off screen
     */
    adjustContextMenuPosition(x, y) {
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
        const newPoints = [...this.boundary.points];
        newPoints.splice(index, 1);

        // Recreate boundary with new points (Fabric.js requires this for proper update)
        const oldBoundary = this.boundary;
        const boundaryProps = {
            left: oldBoundary.left,
            top: oldBoundary.top,
            fill: oldBoundary.fill,
            stroke: oldBoundary.stroke,
            strokeWidth: oldBoundary.strokeWidth,
            selectable: oldBoundary.selectable,
            evented: oldBoundary.evented,
            hasControls: oldBoundary.hasControls,
            hasBorders: oldBoundary.hasBorders,
            borderColor: oldBoundary.borderColor,
            borderDashArray: oldBoundary.borderDashArray,
            hoverCursor: oldBoundary.hoverCursor,
            objectCaching: false,
            isBoundary: true
        };

        // Remove old boundary
        this.canvas.remove(oldBoundary);

        // Create new boundary with updated points
        this.boundary = new fabric.Polygon(newPoints, boundaryProps);

        // Re-attach event handlers if in editing mode
        if (this.isEditingBoundary) {
            this.boundary.on('moving', () => this.onBoundaryMove());
            this.boundary.on('modified', () => this.onBoundaryModified());
        }

        this.canvas.add(this.boundary);

        // Update boundaryPoints for gray mask
        this.boundaryPoints = newPoints;

        // Refresh anchor points
        this.showAnchorPoints();

        // Update gray mask
        this.updateGrayMask();

        // Reorder layers
        this.reorderLayers();

        this.canvas.renderAll();
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
            // Deselect boundary to prevent selection on double-click
            this.canvas.discardActiveObject();
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
            if (this.canvas.isDrawingMode) {
                this.stopFreeDrawing();
            } else if (this.isDrawingBoundary) {
                this.cancelDrawingBoundary();
            } else if (this.isEditingBoundary) {
                this.stopEditingBoundary();
            } else if (this.isEditingBackground) {
                this.toggleBackgroundEditing();
            }
        }

        // Delete - remove selected object
        if (e.key === 'Delete') {
            if (this.isEditingBoundary) {
                this.removeSelectedAnchorPoint();
            } else {
                this.deleteSelectedElement();
            }
        }
    }

    /**
     * Delete selected map element
     */
    deleteSelectedElement() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject) return;

        // Don't delete boundary, mask, background, or anchor points
        if (activeObject.isBoundary || activeObject.isGrayMask ||
            activeObject.isBackgroundImage || activeObject.isAnchorPoint) {
            return;
        }

        // Only delete map elements
        if (activeObject.isMapElement) {
            this.canvas.remove(activeObject);
            this.canvas.renderAll();
            this.setStatus('אלמנט נמחק');
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
            fill: 'rgba(0, 0, 0, 0.15)',
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

    toggleEditingBoundary() {
        if (!this.boundary) return;

        if (this.isEditingBoundary) {
            this.stopEditingBoundary();
        } else {
            this.startEditingBoundary();
        }
    }

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
                radius: 5,
                fill: '#3b82f6',
                stroke: '#fff',
                strokeWidth: 2,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                hasControls: false,
                hasBorders: false,
                isAnchorPoint: true,
                pointIndex: index,
                hoverCursor: 'move',
                shadow: new fabric.Shadow({
                    color: 'rgba(0,0,0,0.3)',
                    blur: 3,
                    offsetX: 0,
                    offsetY: 1
                })
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

        // Use the same method as context menu removal
        this.removeAnchorPointByObject(activeObject);
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

        // Create new points array with the new point
        const newPoints = [...points];
        newPoints.splice(afterIndex + 1, 0, newPoint);

        // Recreate boundary with new points (Fabric.js requires this for proper update)
        const oldBoundary = this.boundary;
        const boundaryProps = {
            left: oldBoundary.left,
            top: oldBoundary.top,
            fill: oldBoundary.fill,
            stroke: oldBoundary.stroke,
            strokeWidth: oldBoundary.strokeWidth,
            selectable: oldBoundary.selectable,
            evented: oldBoundary.evented,
            hasControls: oldBoundary.hasControls,
            hasBorders: oldBoundary.hasBorders,
            borderColor: oldBoundary.borderColor,
            borderDashArray: oldBoundary.borderDashArray,
            hoverCursor: oldBoundary.hoverCursor,
            objectCaching: false,
            isBoundary: true
        };

        // Remove old boundary
        this.canvas.remove(oldBoundary);

        // Create new boundary with updated points
        this.boundary = new fabric.Polygon(newPoints, boundaryProps);

        // Re-attach event handlers if in editing mode
        if (this.isEditingBoundary) {
            this.boundary.on('moving', () => this.onBoundaryMove());
            this.boundary.on('modified', () => this.onBoundaryModified());
        }

        this.canvas.add(this.boundary);

        // Update boundaryPoints for gray mask
        this.boundaryPoints = newPoints;

        // Refresh anchor points
        this.showAnchorPoints();

        // Update gray mask
        this.updateGrayMask();

        // Reorder layers
        this.reorderLayers();

        this.canvas.renderAll();
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
    // MAP ELEMENTS - ADD SHAPES
    // ============================================

    /**
     * Add text element at position
     */
    addTextElement(x, y) {
        const text = new fabric.IText('טקסט', {
            left: x,
            top: y,
            fontSize: 16,
            fontFamily: 'Arial, sans-serif',
            fill: '#1e293b',
            direction: 'rtl',
            textAlign: 'right',
            selectable: true,
            evented: true,
            isMapElement: true,
            elementType: 'text'
        });

        this.canvas.add(text);
        this.canvas.setActiveObject(text);
        text.enterEditing();
        text.selectAll();
        this.canvas.renderAll();
        this.setStatus('נוסף טקסט - לחץ פעמיים לעריכה', 'editing');
    }

    /**
     * Add line element at position
     */
    addLineElement(x, y) {
        const line = new fabric.Line([x, y, x + 100, y], {
            stroke: '#3b82f6',
            strokeWidth: 2,
            strokeUniform: true,
            selectable: true,
            evented: true,
            hasControls: true,
            isMapElement: true,
            elementType: 'line'
        });

        this.canvas.add(line);
        this.canvas.setActiveObject(line);
        this.canvas.renderAll();
        this.setStatus('נוסף קו - גרור את הקצוות לשינוי', 'editing');
    }

    /**
     * Add circle element at position
     */
    addCircleElement(x, y) {
        const circle = new fabric.Circle({
            left: x,
            top: y,
            radius: 30,
            fill: 'transparent',
            stroke: '#3b82f6',
            strokeWidth: 2,
            strokeUniform: true,
            originX: 'center',
            originY: 'center',
            selectable: true,
            evented: true,
            isMapElement: true,
            elementType: 'circle'
        });

        this.canvas.add(circle);
        this.canvas.setActiveObject(circle);
        this.canvas.renderAll();
        this.setStatus('נוסף עיגול', 'editing');
    }

    /**
     * Add rectangle element at position
     */
    addRectElement(x, y) {
        const rect = new fabric.Rect({
            left: x,
            top: y,
            width: 80,
            height: 50,
            fill: 'transparent',
            stroke: '#3b82f6',
            strokeWidth: 2,
            strokeUniform: true,
            originX: 'center',
            originY: 'center',
            selectable: true,
            evented: true,
            isMapElement: true,
            elementType: 'rect'
        });

        this.canvas.add(rect);
        this.canvas.setActiveObject(rect);
        this.canvas.renderAll();
        this.setStatus('נוסף מלבן', 'editing');
    }

    /**
     * Start free drawing mode
     */
    startFreeDrawing() {
        this.canvas.isDrawingMode = true;
        this.canvas.freeDrawingBrush.color = '#3b82f6';
        this.canvas.freeDrawingBrush.width = 2;

        this.setStatus('מצב ציור חופשי - צייר עם העכבר. לחץ Escape לסיום.', 'drawing');

        // Listen for path created to mark it as map element
        const pathCreatedHandler = (e) => {
            if (e.path) {
                e.path.set({
                    isMapElement: true,
                    elementType: 'freedraw',
                    strokeUniform: true
                });
            }
        };
        this.canvas.on('path:created', pathCreatedHandler);

        // Store handler for cleanup
        this._freeDrawPathHandler = pathCreatedHandler;
    }

    /**
     * Stop free drawing mode
     */
    stopFreeDrawing() {
        this.canvas.isDrawingMode = false;

        // Remove path created handler
        if (this._freeDrawPathHandler) {
            this.canvas.off('path:created', this._freeDrawPathHandler);
            this._freeDrawPathHandler = null;
        }

        this.setStatus('ציור חופשי הסתיים', 'editing');
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
    // FLOATING PANELS
    // ============================================

    /**
     * Toggle panel visibility
     */
    togglePanel(panelName) {
        const panelMap = {
            textStyle: this.elements.textStylePanel,
            elementStyle: this.elements.elementStylePanel,
            layers: this.elements.layersPanel
        };
        const btnMap = {
            textStyle: this.elements.btnTextStylePanel,
            elementStyle: this.elements.btnElementStylePanel,
            layers: this.elements.btnLayersPanel
        };

        const panel = panelMap[panelName];
        const btn = btnMap[panelName];
        if (!panel) return;

        this.panels[panelName].visible = !this.panels[panelName].visible;

        if (this.panels[panelName].visible) {
            panel.style.display = 'block';
            panel.style.left = this.panels[panelName].position.x + 'px';
            panel.style.top = this.panels[panelName].position.y + 'px';
            btn.classList.add('checked');

            // Update panel content
            if (panelName === 'layers') {
                this.updateLayersPanel();
            } else {
                this.onSelectionChanged();
            }
        } else {
            panel.style.display = 'none';
            btn.classList.remove('checked');
        }

        // Close dropdown
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
    }

    /**
     * Start dragging a panel
     */
    startPanelDrag(e) {
        const panel = e.target.closest('.floating-panel');
        if (!panel) return;

        this.draggedPanel = panel;
        const rect = panel.getBoundingClientRect();
        this.dragOffset = {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };

        panel.style.opacity = '0.9';
    }

    /**
     * Handle panel dragging
     */
    handlePanelDrag(e) {
        if (!this.draggedPanel) return;

        const x = e.clientX - this.dragOffset.x;
        const y = e.clientY - this.dragOffset.y;

        // Keep panel within viewport
        const maxX = window.innerWidth - this.draggedPanel.offsetWidth;
        const maxY = window.innerHeight - this.draggedPanel.offsetHeight;

        const boundedX = Math.max(0, Math.min(x, maxX));
        const boundedY = Math.max(0, Math.min(y, maxY));

        this.draggedPanel.style.left = boundedX + 'px';
        this.draggedPanel.style.top = boundedY + 'px';

        // Save position
        const panelId = this.draggedPanel.id;
        const panelName = panelId.replace('Panel', '');
        if (this.panels[panelName]) {
            this.panels[panelName].position = { x: boundedX, y: boundedY };
        }
    }

    /**
     * Stop dragging a panel
     */
    stopPanelDrag() {
        if (this.draggedPanel) {
            this.draggedPanel.style.opacity = '1';
            this.draggedPanel = null;
        }
    }

    /**
     * Handle selection change for panels
     */
    onSelectionChanged() {
        const activeObject = this.canvas.getActiveObject();

        // Text style panel
        if (this.panels.textStyle.visible) {
            if (activeObject && activeObject.isMapElement && activeObject.elementType === 'text') {
                this.elements.textPanelMessage.style.display = 'none';
                this.elements.textControls.style.display = 'block';
                this.loadTextStyles(activeObject);
            } else {
                this.elements.textPanelMessage.style.display = 'block';
                this.elements.textControls.style.display = 'none';
            }
        }

        // Element style panel
        if (this.panels.elementStyle.visible) {
            const isShape = activeObject && activeObject.isMapElement &&
                ['line', 'circle', 'rect', 'freedraw'].includes(activeObject.elementType);
            if (isShape) {
                this.elements.elementPanelMessage.style.display = 'none';
                this.elements.elementControls.style.display = 'block';
                this.loadElementStyles(activeObject);
            } else {
                this.elements.elementPanelMessage.style.display = 'block';
                this.elements.elementControls.style.display = 'none';
            }
        }

        // Update layers panel selection
        if (this.panels.layers.visible) {
            this.updateLayersPanel();
        }
    }

    /**
     * Handle selection cleared
     */
    onSelectionCleared() {
        // Hide controls in panels
        if (this.panels.textStyle.visible) {
            this.elements.textPanelMessage.style.display = 'block';
            this.elements.textControls.style.display = 'none';
        }
        if (this.panels.elementStyle.visible) {
            this.elements.elementPanelMessage.style.display = 'block';
            this.elements.elementControls.style.display = 'none';
        }
        if (this.panels.layers.visible) {
            this.updateLayersPanel();
        }
    }

    /**
     * Load text styles into panel controls
     */
    loadTextStyles(textObj) {
        this.elements.fontFamily.value = textObj.fontFamily || 'Arial, sans-serif';
        this.elements.fontSize.value = textObj.fontSize || 16;
        this.elements.fontColor.value = textObj.fill || '#1e293b';
        this.elements.letterSpacing.value = textObj.charSpacing ? textObj.charSpacing / 10 : 0;
    }

    /**
     * Apply text styles from panel to selected text
     */
    applyTextStyle() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject || activeObject.elementType !== 'text') return;

        activeObject.set({
            fontFamily: this.elements.fontFamily.value,
            fontSize: parseInt(this.elements.fontSize.value),
            fill: this.elements.fontColor.value,
            charSpacing: parseFloat(this.elements.letterSpacing.value) * 10
        });

        this.canvas.renderAll();
    }

    /**
     * Load element styles into panel controls
     */
    loadElementStyles(obj) {
        this.elements.strokeWidth.value = obj.strokeWidth || 2;
        this.elements.strokeColor.value = obj.stroke || '#3b82f6';

        // Determine stroke style
        if (obj.strokeDashArray) {
            if (obj.strokeDashArray[0] === 1) {
                this.elements.strokeStyle.value = 'dotted';
            } else {
                this.elements.strokeStyle.value = 'dashed';
            }
        } else {
            this.elements.strokeStyle.value = 'solid';
        }
    }

    /**
     * Apply element styles from panel to selected element
     */
    applyElementStyle() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject || !activeObject.isMapElement) return;

        const strokeStyle = this.elements.strokeStyle.value;
        let strokeDashArray = null;
        if (strokeStyle === 'dashed') {
            strokeDashArray = [8, 4];
        } else if (strokeStyle === 'dotted') {
            strokeDashArray = [1, 4];
        }

        activeObject.set({
            strokeWidth: parseInt(this.elements.strokeWidth.value),
            stroke: this.elements.strokeColor.value,
            strokeDashArray: strokeDashArray
        });

        this.canvas.renderAll();
    }

    /**
     * Update layers panel
     */
    updateLayersPanel() {
        if (!this.panels.layers.visible) return;

        const objects = this.canvas.getObjects().filter(obj =>
            obj.isMapElement && !obj.isBoundary && !obj.isGrayMask && !obj.isBackgroundImage && !obj.isAnchorPoint
        );

        if (objects.length === 0) {
            this.elements.layersList.innerHTML = '<div class="panel-message">אין שכבות</div>';
            return;
        }

        const activeObject = this.canvas.getActiveObject();

        const icons = {
            text: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3M9 20h6M12 4v16"/></svg>',
            line: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>',
            circle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>',
            rect: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/></svg>',
            freedraw: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17c3-3 6-7 9-7s6 4 9 7"/></svg>'
        };

        const typeNames = {
            text: 'טקסט',
            line: 'קו',
            circle: 'עיגול',
            rect: 'מלבן',
            freedraw: 'ציור חופשי'
        };

        // Reverse to show top layers first
        const reversedObjects = [...objects].reverse();

        this.elements.layersList.innerHTML = reversedObjects.map((obj, index) => {
            const isSelected = obj === activeObject;
            const type = obj.elementType || 'unknown';
            const icon = icons[type] || icons.rect;
            const typeName = typeNames[type] || type;
            const name = obj.text ? `${typeName}: ${obj.text.substring(0, 15)}` : typeName;

            return `
                <div class="layer-item ${isSelected ? 'selected' : ''}"
                     data-index="${objects.length - 1 - index}"
                     draggable="true">
                    <div class="layer-icon">${icon}</div>
                    <span class="layer-name">${name}</span>
                    <div class="layer-drag-handle">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                        </svg>
                    </div>
                </div>
            `;
        }).join('');

        // Add click to select
        this.elements.layersList.querySelectorAll('.layer-item').forEach(item => {
            item.addEventListener('click', () => {
                const index = parseInt(item.dataset.index);
                const obj = objects[index];
                if (obj) {
                    this.canvas.setActiveObject(obj);
                    this.canvas.renderAll();
                }
            });
        });

        // Add drag and drop for reordering
        this.setupLayerDragAndDrop(objects);
    }

    /**
     * Setup drag and drop for layer reordering
     */
    setupLayerDragAndDrop(objects) {
        const items = this.elements.layersList.querySelectorAll('.layer-item');
        let draggedItem = null;

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                draggedItem = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                items.forEach(i => i.classList.remove('drag-over'));
                draggedItem = null;
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (item !== draggedItem) {
                    item.classList.add('drag-over');
                }
            });

            item.addEventListener('dragleave', () => {
                item.classList.remove('drag-over');
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                item.classList.remove('drag-over');

                if (!draggedItem || item === draggedItem) return;

                const fromIndex = parseInt(draggedItem.dataset.index);
                const toIndex = parseInt(item.dataset.index);

                const fromObj = objects[fromIndex];
                const toObj = objects[toIndex];

                if (fromObj && toObj) {
                    // Swap z-index
                    const fromZIndex = this.canvas.getObjects().indexOf(fromObj);
                    const toZIndex = this.canvas.getObjects().indexOf(toObj);

                    if (fromZIndex < toZIndex) {
                        fromObj.moveTo(toZIndex);
                    } else {
                        fromObj.moveTo(toZIndex);
                    }

                    this.canvas.renderAll();
                    this.updateLayersPanel();
                }
            });
        });
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
