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
            textStyle: { visible: false, position: { x: 20, y: 100 }, docked: false, dockSide: null },
            elementStyle: { visible: false, position: { x: 20, y: 100 }, docked: false, dockSide: null },
            layers: { visible: false, position: { x: 20, y: 100 }, docked: false, dockSide: null },
            children: { visible: false, position: { x: 20, y: 100 }, docked: false, dockSide: null }
        };
        this.draggedPanel = null;
        this.dragOffset = { x: 0, y: 0 };

        // Children panel state
        this.childrenPanel = {
            children: [],              // List of child entities from DB
            selectedChild: null,       // Currently selected child {id, name, type, hasPolygon, polygon}
            childBoundaries: {},       // Map of childId → fabric.Polygon
            isDrawingChildBoundary: false,
            childBoundaryPoints: [],   // Points while drawing child boundary
            isEditingChildBoundary: false,
            childAnchorPoints: []      // Anchor points while editing child boundary
        };

        // Dock zones state
        this.dockZones = {
            left: { panels: [], activeTab: null },
            right: { panels: [], activeTab: null }
        };
        this.activeDockIndicator = null;
        this.dockPreview = null;

        // Panel metadata for tabs
        this.panelMeta = {
            textStyle: {
                title: 'עיצוב כתב',
                icon: '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M9.93 13.5h4.14L12 7.98 9.93 13.5zM20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-4.05 16.5l-1.14-3H9.17l-1.12 3H5.96l5.11-13h1.86l5.11 13h-2.09z"/></svg>'
            },
            elementStyle: {
                title: 'עיצוב אלמנטים',
                icon: '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8z"/></svg>'
            },
            layers: {
                title: 'שכבות',
                icon: '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/></svg>'
            },
            children: {
                title: 'ילדים',
                icon: '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>'
            }
        };

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
            btnChildrenPanel: document.getElementById('btnChildrenPanel'),

            // Floating panels
            textStylePanel: document.getElementById('textStylePanel'),
            elementStylePanel: document.getElementById('elementStylePanel'),
            layersPanel: document.getElementById('layersPanel'),
            childrenPanel: document.getElementById('childrenPanel'),

            // Children panel elements
            childrenNoParentBoundary: document.getElementById('childrenNoParentBoundary'),
            childrenLoading: document.getElementById('childrenLoading'),
            childrenListContainer: document.getElementById('childrenListContainer'),
            childrenList: document.getElementById('childrenList'),
            childrenEmpty: document.getElementById('childrenEmpty'),

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
            layersList: document.getElementById('layersList'),

            // Dock zones
            dockZoneLeft: document.getElementById('dockZoneLeft'),
            dockZoneRight: document.getElementById('dockZoneRight'),
            dockPanelsLeft: document.getElementById('dockPanelsLeft'),
            dockPanelsRight: document.getElementById('dockPanelsRight')
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

        // Double-click on zoom display to enter manual zoom
        this.elements.zoomDisplay.addEventListener('dblclick', () => this.showZoomInput());

        // Mouse wheel pan
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
            } else if (this.childrenPanel.isEditingChildBoundary) {
                this.handleChildBoundaryDoubleClick(opt);
            }
        });

        // Windows menu
        this.setupDropdownMenu('btnWindowsMenu', 'windowsMenu');

        // Panel toggle buttons
        this.elements.btnTextStylePanel.addEventListener('click', () => this.togglePanel('textStyle'));
        this.elements.btnElementStylePanel.addEventListener('click', () => this.togglePanel('elementStyle'));
        this.elements.btnLayersPanel.addEventListener('click', () => this.togglePanel('layers'));
        this.elements.btnChildrenPanel.addEventListener('click', () => this.togglePanel('children'));

        // Children panel control buttons are handled via dropdown menus in renderChildrenList()

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

        // Find anchor point at this position (when editing boundary or child boundary)
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

        // Find child anchor point at this position (when editing child boundary)
        if (this.childrenPanel.isEditingChildBoundary) {
            let childAnchorTarget = null;
            for (const anchor of this.childrenPanel.childAnchorPoints) {
                const dx = canvasX - anchor.left;
                const dy = canvasY - anchor.top;
                const distance = Math.sqrt(dx * dx + dy * dy);
                if (distance <= anchor.radius + 5) {
                    childAnchorTarget = anchor;
                    break;
                }
            }

            // If clicked on child anchor point, show anchor menu
            if (childAnchorTarget && childAnchorTarget.isChildAnchorPoint) {
                this.showChildAnchorContextMenu(e.clientX, e.clientY, childAnchorTarget);
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
            this.boundary.on('scaling', () => this.onBoundaryTransform());
            this.boundary.on('rotating', () => this.onBoundaryTransform());
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
            if (this.childrenPanel.isDrawingChildBoundary) {
                this.cancelDrawingChildBoundary();
            } else if (this.childrenPanel.isEditingChildBoundary) {
                this.stopEditingChildBoundary();
            } else if (this.canvas.isDrawingMode) {
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
        // Pan the canvas instead of zooming
        const e = opt.e;
        const vpt = this.canvas.viewportTransform;

        // Use deltaY for vertical scroll, deltaX for horizontal scroll
        // Shift+wheel can be used for horizontal scrolling on mice without horizontal wheel
        if (e.shiftKey) {
            // Horizontal pan when holding shift
            vpt[4] -= e.deltaY;
        } else {
            // Normal scroll: vertical pan with deltaY, horizontal with deltaX
            vpt[4] -= e.deltaX || 0;  // Horizontal pan
            vpt[5] -= e.deltaY;        // Vertical pan
        }

        this.canvas.setViewportTransform(vpt);
        this.canvas.renderAll();

        opt.e.preventDefault();
        opt.e.stopPropagation();
    }

    updateZoomDisplay() {
        const zoom = Math.round(this.canvas.getZoom() * 100);
        this.elements.zoomDisplay.textContent = `${zoom}%`;
    }

    showZoomInput() {
        const currentZoom = Math.round(this.canvas.getZoom() * 100);
        const zoomDisplay = this.elements.zoomDisplay;

        // Create input element
        const input = document.createElement('input');
        input.type = 'number';
        input.value = currentZoom;
        input.min = 10;
        input.max = 500;
        input.style.cssText = `
            width: 50px;
            padding: 2px 4px;
            border: 1px solid #3b82f6;
            border-radius: 4px;
            text-align: center;
            font-size: 12px;
            outline: none;
        `;

        // Save original content
        const originalText = zoomDisplay.textContent;

        // Replace text with input
        zoomDisplay.textContent = '';
        zoomDisplay.appendChild(input);
        input.focus();
        input.select();

        // Apply zoom function
        const applyZoom = () => {
            let value = parseInt(input.value, 10);
            if (isNaN(value)) value = currentZoom;

            // Clamp between 10% and 500%
            value = Math.max(10, Math.min(500, value));

            // Apply zoom
            this.canvas.setZoom(value / 100);

            // Restore display
            zoomDisplay.textContent = `${value}%`;
        };

        // Event listeners
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyZoom();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                zoomDisplay.textContent = originalText;
            }
        });

        input.addEventListener('blur', applyZoom);
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
        // Handle child boundary drawing first
        if (this.childrenPanel.isDrawingChildBoundary) {
            this.handleChildBoundaryClick(opt);
            return;
        }

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
        // Handle child boundary drawing first
        if (this.childrenPanel.isDrawingChildBoundary) {
            this.finishDrawingChildBoundary();
            return;
        }

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

        // Make boundary selectable and draggable with resize/rotate controls
        this.boundary.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            borderColor: '#2563eb',
            borderScaleFactor: 1.5,
            borderDashArray: [6, 4],
            cornerColor: '#3b82f6',
            cornerStrokeColor: '#fff',
            cornerStyle: 'circle',
            cornerSize: 10,
            transparentCorners: false,
            hoverCursor: 'move'
        });

        // Handle boundary movement, scaling, and rotation
        this.boundary.on('moving', () => this.onBoundaryMove());
        this.boundary.on('scaling', () => this.onBoundaryTransform());
        this.boundary.on('rotating', () => this.onBoundaryTransform());
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

            // Prevent boundary selection when clicking anchor point
            circle.on('mousedown', () => {
                this.canvas.setActiveObject(circle);
            });

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

        // Mark polygon as dirty to trigger re-render (without repositioning)
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
     * Handle boundary scaling or rotation - update gray mask and anchor points
     */
    onBoundaryTransform() {
        // Update gray mask to follow boundary
        this.updateGrayMask();

        // Update anchor points positions
        this.showAnchorPoints();

        this.canvas.renderAll();
    }

    /**
     * Handle boundary modification complete
     * Bakes transformation into points after scale/rotate
     */
    onBoundaryModified() {
        // Check if boundary was scaled or rotated (not just moved)
        const wasTransformed = this.boundary.scaleX !== 1 || this.boundary.scaleY !== 1 || this.boundary.angle !== 0;

        if (wasTransformed) {
            // Bake transformation into points
            const matrix = this.boundary.calcTransformMatrix();
            const pathOffset = this.boundary.pathOffset || { x: 0, y: 0 };

            // Convert all points to absolute coordinates
            const absolutePoints = this.boundary.points.map(p => {
                return fabric.util.transformPoint(
                    { x: p.x - pathOffset.x, y: p.y - pathOffset.y },
                    matrix
                );
            });

            // Recreate boundary with absolute points (no transformation)
            const oldBoundary = this.boundary;
            this.boundary = new fabric.Polygon(absolutePoints, {
                fill: oldBoundary.fill,
                stroke: oldBoundary.stroke,
                strokeWidth: oldBoundary.strokeWidth,
                selectable: true,
                evented: true,
                hasControls: true,
                hasBorders: true,
                borderColor: '#2563eb',
                borderScaleFactor: 1.5,
                borderDashArray: [6, 4],
                cornerColor: '#3b82f6',
                cornerStrokeColor: '#fff',
                cornerStyle: 'circle',
                cornerSize: 10,
                transparentCorners: false,
                hoverCursor: 'move',
                objectCaching: false,
                isBoundary: true
            });

            this.boundary.on('moving', () => this.onBoundaryMove());
            this.boundary.on('scaling', () => this.onBoundaryTransform());
            this.boundary.on('rotating', () => this.onBoundaryTransform());
            this.boundary.on('modified', () => this.onBoundaryModified());

            // Replace in canvas
            this.canvas.remove(oldBoundary);
            this.canvas.add(this.boundary);

            // Update boundaryPoints for gray mask
            this.boundaryPoints = absolutePoints;

            // Select the new boundary
            this.canvas.setActiveObject(this.boundary);
        }

        this.updateGrayMask();
        this.showAnchorPoints();
        this.reorderLayers();
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
            this.boundary.on('scaling', () => this.onBoundaryTransform());
            this.boundary.on('rotating', () => this.onBoundaryTransform());
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

            // Sync boundaryPoints with edited boundary
            this.boundaryPoints = this.boundary.points.map(p => ({ x: p.x, y: p.y }));

            // Validate child boundaries are still contained
            if (!this.canModifyParentBoundary(this.boundaryPoints)) {
                this.setStatus('אזהרה: ייתכן שגבולות ילדים יצאו מחוץ לגבול ההורה!', 'error');
            }
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

        // Check if children have boundaries
        if (!this.canDeleteParentBoundary()) {
            alert('לא ניתן להסיר את הגבול - יש ילדים עם גבולות מוגדרים.\nיש למחוק קודם את גבולות הילדים.');
            return;
        }

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
        // Order: background -> child boundaries -> boundary -> gray mask -> anchor points
        if (this.backgroundImage) {
            this.canvas.sendToBack(this.backgroundImage);
        }

        // Child boundaries should be above background but below parent boundary
        Object.values(this.childrenPanel.childBoundaries).forEach(polygon => {
            this.canvas.bringToFront(polygon);
        });

        if (this.boundary) {
            this.canvas.bringToFront(this.boundary);
        }

        if (this.grayMask) {
            this.canvas.bringToFront(this.grayMask);
        }

        this.anchorPoints.forEach(point => {
            this.canvas.bringToFront(point);
        });

        // Child anchor points should be on top of everything
        this.childrenPanel.childAnchorPoints.forEach(point => {
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
            layers: this.elements.layersPanel,
            children: this.elements.childrenPanel
        };
        const btnMap = {
            textStyle: this.elements.btnTextStylePanel,
            elementStyle: this.elements.btnElementStylePanel,
            layers: this.elements.btnLayersPanel,
            children: this.elements.btnChildrenPanel
        };

        const panel = panelMap[panelName];
        const btn = btnMap[panelName];
        if (!panel) return;

        const panelState = this.panels[panelName];

        // If panel is docked, undock it instead of toggling
        if (panelState.docked) {
            this.undockPanel(panelName);
            // Close dropdown
            document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
            return;
        }

        panelState.visible = !panelState.visible;

        if (panelState.visible) {
            panel.style.display = 'block';
            panel.style.left = panelState.position.x + 'px';
            panel.style.top = panelState.position.y + 'px';
            btn.classList.add('checked');

            // Update panel content
            if (panelName === 'layers') {
                this.updateLayersPanel();
            } else if (panelName === 'children') {
                this.loadChildren();
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

        // Check for docking proximity
        const DOCK_THRESHOLD = 40;
        const nearLeft = e.clientX < DOCK_THRESHOLD;
        const nearRight = e.clientX > window.innerWidth - DOCK_THRESHOLD;

        if (nearLeft) {
            this.showDockIndicator('left');
        } else if (nearRight) {
            this.showDockIndicator('right');
        } else {
            this.hideDockIndicator();
        }
    }

    /**
     * Stop dragging a panel
     */
    stopPanelDrag() {
        if (this.draggedPanel) {
            const panelId = this.draggedPanel.id;
            const panelName = panelId.replace('Panel', '');

            // Check if should dock
            if (this.activeDockIndicator) {
                this.dockPanel(panelName, this.activeDockIndicator);
            }

            this.draggedPanel.style.opacity = '1';
            this.draggedPanel = null;
            this.hideDockIndicator();
        }
    }

    // ============================================
    // DOCKING SYSTEM
    // ============================================

    /**
     * Show dock indicator when panel is near edge
     */
    showDockIndicator(side) {
        if (this.activeDockIndicator === side) return;

        this.hideDockIndicator();
        this.activeDockIndicator = side;

        // Create indicator
        const indicator = document.createElement('div');
        indicator.className = `dock-indicator dock-indicator-${side}`;
        indicator.id = 'dockIndicator';
        document.body.appendChild(indicator);

        // Create preview area
        const preview = document.createElement('div');
        preview.className = `dock-preview dock-preview-${side}`;
        preview.id = 'dockPreview';
        document.body.appendChild(preview);
    }

    /**
     * Hide dock indicator
     */
    hideDockIndicator() {
        this.activeDockIndicator = null;

        const indicator = document.getElementById('dockIndicator');
        if (indicator) indicator.remove();

        const preview = document.getElementById('dockPreview');
        if (preview) preview.remove();
    }

    /**
     * Dock a panel to a side
     */
    dockPanel(panelName, side) {
        const panel = this.panels[panelName];
        if (!panel || panel.docked) return;

        // Update panel state
        panel.docked = true;
        panel.dockSide = side;

        // Add to dock zone
        this.dockZones[side].panels.push(panelName);

        // If this is the first panel, set it as active
        if (this.dockZones[side].panels.length === 1) {
            this.dockZones[side].activeTab = panelName;
        }

        // Hide the floating panel
        const panelElement = this.elements[`${panelName}Panel`];
        if (panelElement) {
            panelElement.classList.add('docked');
        }

        // Update dock zone UI
        this.updateDockZone(side);

        // Update menu button state
        this.updatePanelMenuState(panelName);

        this.setStatus(`פאנל "${this.panelMeta[panelName].title}" הוצמד`);
    }

    /**
     * Undock a panel from dock zone
     */
    undockPanel(panelName) {
        const panel = this.panels[panelName];
        if (!panel || !panel.docked) return;

        const side = panel.dockSide;

        // Update panel state
        panel.docked = false;
        panel.dockSide = null;

        // Remove from dock zone
        const zoneIndex = this.dockZones[side].panels.indexOf(panelName);
        if (zoneIndex > -1) {
            this.dockZones[side].panels.splice(zoneIndex, 1);
        }

        // Update active tab if needed
        if (this.dockZones[side].activeTab === panelName) {
            this.dockZones[side].activeTab = this.dockZones[side].panels[0] || null;
        }

        // Show the floating panel
        const panelElement = this.elements[`${panelName}Panel`];
        if (panelElement) {
            panelElement.classList.remove('docked');
            // Position it somewhere reasonable
            panel.position = { x: 100, y: 100 };
            panelElement.style.left = panel.position.x + 'px';
            panelElement.style.top = panel.position.y + 'px';
            panelElement.style.display = 'block';
        }

        // Update dock zone UI
        this.updateDockZone(side);

        // Update menu button state
        this.updatePanelMenuState(panelName);

        this.setStatus(`פאנל "${this.panelMeta[panelName].title}" שוחרר`);
    }

    /**
     * Update dock zone UI (accordion panels)
     */
    updateDockZone(side) {
        const zone = this.dockZones[side];
        const zoneElement = this.elements[`dockZone${side.charAt(0).toUpperCase() + side.slice(1)}`];
        const panelsContainer = this.elements[`dockPanels${side.charAt(0).toUpperCase() + side.slice(1)}`];

        if (!zoneElement || !panelsContainer) return;

        // Show/hide zone based on whether it has panels
        if (zone.panels.length === 0) {
            zoneElement.classList.remove('active');
            panelsContainer.innerHTML = '';
            return;
        }

        zoneElement.classList.add('active');

        // Render accordion panels
        panelsContainer.innerHTML = zone.panels.map(panelName => {
            const meta = this.panelMeta[panelName];
            const isCollapsed = zone.collapsed && zone.collapsed[panelName];
            return `
                <div class="dock-panel-item ${isCollapsed ? 'collapsed' : ''}" data-panel="${panelName}">
                    <div class="dock-panel-header" data-panel="${panelName}">
                        <svg class="dock-panel-toggle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                        <span class="dock-panel-icon">${meta.icon}</span>
                        <span class="dock-panel-title">${meta.title}</span>
                        <button class="dock-panel-undock" data-panel="${panelName}" title="שחרר פאנל">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                            </svg>
                        </button>
                    </div>
                    <div class="dock-panel-content" data-panel="${panelName}"></div>
                </div>
            `;
        }).join('');

        // Add header click handlers (toggle collapse)
        panelsContainer.querySelectorAll('.dock-panel-header').forEach(header => {
            header.addEventListener('click', (e) => {
                if (!e.target.closest('.dock-panel-undock')) {
                    const panelName = header.dataset.panel;
                    this.toggleDockPanelCollapse(side, panelName);
                }
            });
        });

        // Add undock button handlers
        panelsContainer.querySelectorAll('.dock-panel-undock').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const panelName = btn.dataset.panel;
                this.undockPanel(panelName);
            });
        });

        // Render content for all panels (non-collapsed ones will be visible)
        this.renderAllDockPanelContents(side);
    }

    /**
     * Toggle collapse state of a docked panel
     */
    toggleDockPanelCollapse(side, panelName) {
        if (!this.dockZones[side].collapsed) {
            this.dockZones[side].collapsed = {};
        }

        this.dockZones[side].collapsed[panelName] = !this.dockZones[side].collapsed[panelName];

        const panelsContainer = this.elements[`dockPanels${side.charAt(0).toUpperCase() + side.slice(1)}`];
        const panelItem = panelsContainer.querySelector(`.dock-panel-item[data-panel="${panelName}"]`);

        if (panelItem) {
            panelItem.classList.toggle('collapsed', this.dockZones[side].collapsed[panelName]);
        }
    }

    /**
     * Render content for all docked panels
     */
    renderAllDockPanelContents(side) {
        const zone = this.dockZones[side];
        const panelsContainer = this.elements[`dockPanels${side.charAt(0).toUpperCase() + side.slice(1)}`];

        zone.panels.forEach(panelName => {
            const contentElement = panelsContainer.querySelector(`.dock-panel-content[data-panel="${panelName}"]`);
            if (!contentElement) return;

            // Get the panel body content
            const panelElement = this.elements[`${panelName}Panel`];
            if (!panelElement) return;

            const panelBody = panelElement.querySelector('.floating-panel-body');
            if (panelBody) {
                // Clone the content
                contentElement.innerHTML = panelBody.innerHTML;

                // Re-attach event listeners for the cloned controls
                this.attachDockContentListeners(panelName, contentElement);

                // Update content based on selection
                if (panelName === 'layers') {
                    this.updateDockedLayersPanelContent(contentElement);
                } else {
                    this.updateDockedPanelContentElement(panelName, contentElement);
                }
            }
        });
    }

    /**
     * Update content for a specific docked panel element
     */
    updateDockedPanelContentElement(panelName, contentElement) {
        const activeObject = this.canvas.getActiveObject();

        if (panelName === 'textStyle') {
            const controls = contentElement.querySelector('#textControls') || contentElement.querySelector('.panel-controls');
            const message = contentElement.querySelector('#textPanelMessage') || contentElement.querySelector('.panel-message');

            if (activeObject && activeObject.isMapElement && activeObject.elementType === 'text') {
                if (message) message.style.display = 'none';
                if (controls) {
                    controls.style.display = 'block';
                    // Load values
                    const fontFamily = contentElement.querySelector('#fontFamily');
                    const fontSize = contentElement.querySelector('#fontSize');
                    const fontColor = contentElement.querySelector('#fontColor');
                    const letterSpacing = contentElement.querySelector('#letterSpacing');

                    if (fontFamily) fontFamily.value = activeObject.fontFamily || 'Arial, sans-serif';
                    if (fontSize) fontSize.value = activeObject.fontSize || 16;
                    if (fontColor) fontColor.value = activeObject.fill || '#1e293b';
                    if (letterSpacing) letterSpacing.value = activeObject.charSpacing ? activeObject.charSpacing / 10 : 0;
                }
            } else {
                if (message) message.style.display = 'block';
                if (controls) controls.style.display = 'none';
            }
        } else if (panelName === 'elementStyle') {
            const controls = contentElement.querySelector('#elementControls') || contentElement.querySelector('.panel-controls');
            const message = contentElement.querySelector('#elementPanelMessage') || contentElement.querySelector('.panel-message');

            const isShape = activeObject && activeObject.isMapElement &&
                ['line', 'circle', 'rect', 'freedraw'].includes(activeObject.elementType);

            if (isShape) {
                if (message) message.style.display = 'none';
                if (controls) {
                    controls.style.display = 'block';
                    // Load values
                    const strokeWidth = contentElement.querySelector('#strokeWidth');
                    const strokeColor = contentElement.querySelector('#strokeColor');
                    const strokeStyle = contentElement.querySelector('#strokeStyle');

                    if (strokeWidth) strokeWidth.value = activeObject.strokeWidth || 2;
                    if (strokeColor) strokeColor.value = activeObject.stroke || '#3b82f6';
                    if (strokeStyle) {
                        if (activeObject.strokeDashArray) {
                            strokeStyle.value = activeObject.strokeDashArray[0] === 1 ? 'dotted' : 'dashed';
                        } else {
                            strokeStyle.value = 'solid';
                        }
                    }
                }
            } else {
                if (message) message.style.display = 'block';
                if (controls) controls.style.display = 'none';
            }
        }
    }

    /**
     * Update layers panel content element
     */
    updateDockedLayersPanelContent(contentElement) {
        const layersList = contentElement.querySelector('#layersList') || contentElement.querySelector('.layers-list');
        if (!layersList) return;

        const objects = this.canvas.getObjects().filter(obj =>
            obj.isMapElement && !obj.isBoundary && !obj.isGrayMask && !obj.isBackgroundImage && !obj.isAnchorPoint
        );

        if (objects.length === 0) {
            layersList.innerHTML = '<div class="panel-message">אין שכבות</div>';
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

        const reversedObjects = [...objects].reverse();

        layersList.innerHTML = reversedObjects.map((obj, index) => {
            const isSelected = obj === activeObject;
            const type = obj.elementType || 'unknown';
            const icon = icons[type] || icons.rect;
            const typeName = typeNames[type] || type;
            const name = obj.text ? `${typeName}: ${obj.text.substring(0, 15)}` : typeName;

            return `
                <div class="layer-item ${isSelected ? 'selected' : ''}"
                     data-index="${objects.length - 1 - index}">
                    <div class="layer-icon">${icon}</div>
                    <span class="layer-name">${name}</span>
                </div>
            `;
        }).join('');

        // Add click handlers
        layersList.querySelectorAll('.layer-item').forEach(item => {
            item.addEventListener('click', () => {
                const index = parseInt(item.dataset.index);
                const obj = objects[index];
                if (obj) {
                    this.canvas.setActiveObject(obj);
                    this.canvas.renderAll();
                }
            });
        });
    }

    /**
     * Attach event listeners to docked panel content
     */
    attachDockContentListeners(panelName, contentElement) {
        if (panelName === 'textStyle') {
            const fontFamily = contentElement.querySelector('#fontFamily');
            const fontSize = contentElement.querySelector('#fontSize');
            const fontColor = contentElement.querySelector('#fontColor');
            const letterSpacing = contentElement.querySelector('#letterSpacing');

            if (fontFamily) fontFamily.addEventListener('change', () => this.applyTextStyleFromDock(contentElement));
            if (fontSize) fontSize.addEventListener('input', () => this.applyTextStyleFromDock(contentElement));
            if (fontColor) fontColor.addEventListener('input', () => this.applyTextStyleFromDock(contentElement));
            if (letterSpacing) letterSpacing.addEventListener('input', () => this.applyTextStyleFromDock(contentElement));
        } else if (panelName === 'elementStyle') {
            const strokeWidth = contentElement.querySelector('#strokeWidth');
            const strokeColor = contentElement.querySelector('#strokeColor');
            const strokeStyle = contentElement.querySelector('#strokeStyle');

            if (strokeWidth) strokeWidth.addEventListener('input', () => this.applyElementStyleFromDock(contentElement));
            if (strokeColor) strokeColor.addEventListener('input', () => this.applyElementStyleFromDock(contentElement));
            if (strokeStyle) strokeStyle.addEventListener('change', () => this.applyElementStyleFromDock(contentElement));
        }
        // layers panel doesn't need special listeners - it updates automatically
    }

    /**
     * Apply text style from dock panel controls
     */
    applyTextStyleFromDock(contentElement) {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject || activeObject.elementType !== 'text') return;

        const fontFamily = contentElement.querySelector('#fontFamily');
        const fontSize = contentElement.querySelector('#fontSize');
        const fontColor = contentElement.querySelector('#fontColor');
        const letterSpacing = contentElement.querySelector('#letterSpacing');

        activeObject.set({
            fontFamily: fontFamily?.value || 'Arial, sans-serif',
            fontSize: parseInt(fontSize?.value || 16),
            fill: fontColor?.value || '#1e293b',
            charSpacing: parseFloat(letterSpacing?.value || 0) * 10
        });

        this.canvas.renderAll();
    }

    /**
     * Apply element style from dock panel controls
     */
    applyElementStyleFromDock(contentElement) {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject || !activeObject.isMapElement) return;

        const strokeWidth = contentElement.querySelector('#strokeWidth');
        const strokeColor = contentElement.querySelector('#strokeColor');
        const strokeStyle = contentElement.querySelector('#strokeStyle');

        let strokeDashArray = null;
        const style = strokeStyle?.value;
        if (style === 'dashed') {
            strokeDashArray = [8, 4];
        } else if (style === 'dotted') {
            strokeDashArray = [1, 4];
        }

        activeObject.set({
            strokeWidth: parseInt(strokeWidth?.value || 2),
            stroke: strokeColor?.value || '#3b82f6',
            strokeDashArray: strokeDashArray
        });

        this.canvas.renderAll();
    }

    /**
     * Update all docked panels content (accordion mode)
     */
    updateDockedPanelContent(side) {
        const zone = this.dockZones[side];
        if (zone.panels.length === 0) return;

        const panelsContainer = this.elements[`dockPanels${side.charAt(0).toUpperCase() + side.slice(1)}`];
        if (!panelsContainer) return;

        // Update each panel's content
        zone.panels.forEach(panelName => {
            const contentElement = panelsContainer.querySelector(`.dock-panel-content[data-panel="${panelName}"]`);
            if (!contentElement) return;

            if (panelName === 'layers') {
                this.updateDockedLayersPanelContent(contentElement);
            } else {
                this.updateDockedPanelContentElement(panelName, contentElement);
            }
        });
    }

    /**
     * Update docked layers panel (accordion mode)
     */
    updateDockedLayersPanel(side) {
        const panelsContainer = this.elements[`dockPanels${side.charAt(0).toUpperCase() + side.slice(1)}`];
        if (!panelsContainer) return;

        const contentElement = panelsContainer.querySelector('.dock-panel-content[data-panel="layers"]');
        if (contentElement) {
            this.updateDockedLayersPanelContent(contentElement);
        }
    }

    /**
     * Update panel menu button state (checked/unchecked)
     */
    updatePanelMenuState(panelName) {
        const btn = this.elements[`btn${panelName.charAt(0).toUpperCase() + panelName.slice(1)}Panel`];
        if (!btn) return;

        const panel = this.panels[panelName];
        const isActive = panel.visible || panel.docked;
        btn.classList.toggle('checked', isActive);
    }

    /**
     * Handle selection change for panels
     */
    onSelectionChanged() {
        const activeObject = this.canvas.getActiveObject();

        // Text style panel (floating)
        if (this.panels.textStyle.visible && !this.panels.textStyle.docked) {
            if (activeObject && activeObject.isMapElement && activeObject.elementType === 'text') {
                this.elements.textPanelMessage.style.display = 'none';
                this.elements.textControls.style.display = 'block';
                this.loadTextStyles(activeObject);
            } else {
                this.elements.textPanelMessage.style.display = 'block';
                this.elements.textControls.style.display = 'none';
            }
        }

        // Element style panel (floating)
        if (this.panels.elementStyle.visible && !this.panels.elementStyle.docked) {
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

        // Update layers panel selection (floating)
        if (this.panels.layers.visible && !this.panels.layers.docked) {
            this.updateLayersPanel();
        }

        // Update docked panels (accordion mode - update all panels)
        ['left', 'right'].forEach(side => {
            if (this.dockZones[side].panels.length > 0) {
                this.updateDockedPanelContent(side);
            }
        });
    }

    /**
     * Handle selection cleared
     */
    onSelectionCleared() {
        // Hide controls in floating panels
        if (this.panels.textStyle.visible && !this.panels.textStyle.docked) {
            this.elements.textPanelMessage.style.display = 'block';
            this.elements.textControls.style.display = 'none';
        }
        if (this.panels.elementStyle.visible && !this.panels.elementStyle.docked) {
            this.elements.elementPanelMessage.style.display = 'block';
            this.elements.elementControls.style.display = 'none';
        }
        if (this.panels.layers.visible && !this.panels.layers.docked) {
            this.updateLayersPanel();
        }

        // Update docked panels (accordion mode - update all panels)
        ['left', 'right'].forEach(side => {
            if (this.dockZones[side].panels.length > 0) {
                this.updateDockedPanelContent(side);
            }
        });
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

            const url = `${this.config.apiBase}map-data.php?action=load&type=${this.config.entityType}&id=${this.config.entityId}`;
            console.log('Loading map data from:', url);

            const response = await fetch(url);
            console.log('Response status:', response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const data = await response.json();
            console.log('Loaded data:', data);

            if (data.success && data.mapData) {
                this.restoreMapData(data.mapData);
                this.setStatus('נתוני המפה נטענו', 'success');
            } else if (data.warning) {
                console.warn('API Warning:', data.warning);
                this.setStatus(data.warning || 'אין נתוני מפה קיימים');
            } else {
                this.setStatus('אין נתוני מפה קיימים');
            }
        } catch (error) {
            console.error('Error loading map data:', error);
            this.setStatus('שגיאה בטעינת נתוני מפה: ' + error.message);
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

            // Load children boundaries automatically
            this.loadChildren();
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

    // ============================================
    // CHILDREN PANEL
    // ============================================

    /**
     * Get child entity type based on parent type
     */
    getChildType(parentType) {
        const childTypeMap = {
            'cemetery': 'block',
            'block': 'plot',
            'plot': 'areaGrave'
        };
        return childTypeMap[parentType] || null;
    }

    /**
     * Get display name for child type
     */
    getChildTypeName(childType) {
        const typeNames = {
            'block': 'גושים',
            'plot': 'חלקות',
            'areaGrave': 'אחוזות קבר'
        };
        return typeNames[childType] || childType;
    }

    /**
     * Load children from API
     */
    async loadChildren() {
        const isPanelVisible = this.panels.children.visible;

        // Check if parent has boundary
        if (!this.boundary) {
            if (isPanelVisible) {
                this.showChildrenNoParentBoundary();
            }
            return;
        }

        // Check if this entity type can have children
        const childType = this.getChildType(this.config.entityType);
        if (!childType) {
            if (isPanelVisible) {
                this.showChildrenEmpty('לסוג ישות זה אין ילדים');
            }
            return;
        }

        // If children are already loaded, just render without re-fetching
        if (this.childrenPanel.children.length > 0) {
            if (isPanelVisible) {
                this.renderChildrenList();
            }
            this.renderChildBoundaries();
            return;
        }

        if (isPanelVisible) {
            this.showChildrenLoading();
        }

        try {
            const url = `${this.config.apiBase}map-data.php?action=getChildren&parentType=${this.config.entityType}&parentId=${this.config.entityId}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                this.childrenPanel.children = data.children.map(child => ({
                    id: child.id,
                    name: child.name || child.id,
                    type: child.type,
                    hasPolygon: child.hasPolygon,
                    polygon: child.polygon
                }));

                if (this.childrenPanel.children.length === 0) {
                    if (isPanelVisible) {
                        this.showChildrenEmpty(`אין ${this.getChildTypeName(childType)}`);
                    }
                } else {
                    if (isPanelVisible) {
                        this.renderChildrenList();
                    }
                    // Always render boundaries on canvas
                    this.renderChildBoundaries();
                }
            } else {
                throw new Error(data.error || 'שגיאה בטעינה');
            }
        } catch (error) {
            console.error('Error loading children:', error);
            if (isPanelVisible) {
                this.showChildrenEmpty('שגיאה בטעינת ילדים: ' + error.message);
            }
        }
    }

    /**
     * Show message when parent has no boundary
     */
    showChildrenNoParentBoundary() {
        this.elements.childrenNoParentBoundary.style.display = 'block';
        this.elements.childrenLoading.style.display = 'none';
        this.elements.childrenListContainer.style.display = 'none';
        this.elements.childrenEmpty.style.display = 'none';
    }

    /**
     * Show loading indicator
     */
    showChildrenLoading() {
        this.elements.childrenNoParentBoundary.style.display = 'none';
        this.elements.childrenLoading.style.display = 'flex';
        this.elements.childrenListContainer.style.display = 'none';
        this.elements.childrenEmpty.style.display = 'none';
    }

    /**
     * Show empty message
     */
    showChildrenEmpty(message = 'אין ילדים') {
        this.elements.childrenNoParentBoundary.style.display = 'none';
        this.elements.childrenLoading.style.display = 'none';
        this.elements.childrenListContainer.style.display = 'none';
        this.elements.childrenEmpty.style.display = 'block';
        this.elements.childrenEmpty.textContent = message;
    }

    /**
     * Render children list
     */
    renderChildrenList() {
        this.elements.childrenNoParentBoundary.style.display = 'none';
        this.elements.childrenLoading.style.display = 'none';
        this.elements.childrenListContainer.style.display = 'block';
        this.elements.childrenEmpty.style.display = 'none';

        const list = this.elements.childrenList;
        const isEditing = this.childrenPanel.isEditingChildBoundary;
        const editingChildId = isEditing ? this.childrenPanel.selectedChild?.id : null;

        list.innerHTML = this.childrenPanel.children.map(child => {
            const isSelected = child.id === this.childrenPanel.selectedChild?.id;
            const isBeingEdited = child.id === editingChildId;

            return `
            <div class="child-item ${isSelected ? 'selected' : ''} ${isBeingEdited ? 'editing' : ''}"
                 data-id="${child.id}">
                <span class="status-dot ${child.hasPolygon ? 'has-polygon' : 'no-polygon'}"></span>
                <span class="child-name">${child.name}</span>
                ${isBeingEdited ? '<span class="editing-badge">עריכה</span>' : ''}
                <div class="child-dropdown">
                    <button class="child-dropdown-btn" data-id="${child.id}" title="אפשרויות">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M7 10l5 5 5-5z"/>
                        </svg>
                    </button>
                    <div class="child-dropdown-menu" data-id="${child.id}">
                        ${!child.hasPolygon ? `
                            <button class="child-dropdown-item" data-action="add" data-id="${child.id}">
                                <svg viewBox="0 0 24 24" width="14" height="14">
                                    <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                                הוספת גבול
                            </button>
                        ` : `
                            <button class="child-dropdown-item ${isBeingEdited ? 'active' : ''}" data-action="edit" data-id="${child.id}">
                                <svg viewBox="0 0 24 24" width="14" height="14">
                                    <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                </svg>
                                ${isBeingEdited ? 'סיום עריכה' : 'עריכת גבול'}
                            </button>
                            <button class="child-dropdown-item danger" data-action="delete" data-id="${child.id}" ${isBeingEdited ? 'disabled' : ''}>
                                <svg viewBox="0 0 24 24" width="14" height="14">
                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                                הסרת גבול
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `}).join('');

        // Add click handlers for child items (selecting)
        list.querySelectorAll('.child-item').forEach(item => {
            item.addEventListener('click', (e) => {
                // Don't select when clicking dropdown
                if (e.target.closest('.child-dropdown')) return;
                this.selectChild(item.dataset.id);
            });
        });

        // Add click handlers for dropdown buttons
        list.querySelectorAll('.child-dropdown-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleChildDropdown(btn.dataset.id);
            });
        });

        // Add click handlers for dropdown items
        list.querySelectorAll('.child-dropdown-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = item.dataset.action;
                const childId = item.dataset.id;
                this.closeAllChildDropdowns();
                this.handleChildAction(action, childId);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => this.closeAllChildDropdowns());
    }

    /**
     * Toggle child dropdown menu
     */
    toggleChildDropdown(childId) {
        const btn = this.elements.childrenList.querySelector(`.child-dropdown-btn[data-id="${childId}"]`);
        const menu = this.elements.childrenList.querySelector(`.child-dropdown-menu[data-id="${childId}"]`);
        const wasOpen = menu.classList.contains('open');

        // Close all dropdowns first
        this.closeAllChildDropdowns();

        // Toggle this one
        if (!wasOpen) {
            // Calculate position using fixed positioning
            const btnRect = btn.getBoundingClientRect();
            const menuHeight = 120; // Approximate menu height
            const viewportHeight = window.innerHeight;

            // Position horizontally aligned with button
            menu.style.left = `${btnRect.left}px`;

            // Check if menu would go below viewport
            if (btnRect.bottom + menuHeight > viewportHeight) {
                // Position above the button
                menu.style.top = `${btnRect.top - menuHeight}px`;
            } else {
                // Position below the button
                menu.style.top = `${btnRect.bottom + 4}px`;
            }

            menu.classList.add('open');
        }
    }

    /**
     * Close all child dropdown menus
     */
    closeAllChildDropdowns() {
        this.elements.childrenList.querySelectorAll('.child-dropdown-menu').forEach(menu => {
            menu.classList.remove('open');
        });
    }

    /**
     * Handle child action from dropdown
     */
    handleChildAction(action, childId) {
        // First select the child
        this.selectChild(childId);

        switch (action) {
            case 'add':
                this.startDrawingChildBoundary();
                break;
            case 'edit':
                // Toggle editing mode
                if (this.childrenPanel.isEditingChildBoundary && this.childrenPanel.selectedChild?.id === childId) {
                    this.stopEditingChildBoundary();
                } else {
                    this.startEditingChildBoundary();
                }
                break;
            case 'delete':
                this.deleteChildBoundary();
                break;
        }
    }

    /**
     * Select a child entity
     */
    selectChild(childId) {
        const child = this.childrenPanel.children.find(c => c.id === childId);
        this.childrenPanel.selectedChild = child;

        // Update list UI
        this.elements.childrenList.querySelectorAll('.child-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.id === childId);
        });

        // Highlight boundary on canvas
        this.highlightChildBoundary(childId);
    }

    /**
     * Update child controls based on selection (deprecated - now using dropdowns)
     */
    updateChildControls() {
        // No longer needed - dropdowns handle their own visibility per child item
    }

    /**
     * Render all child boundaries on canvas
     */
    renderChildBoundaries() {
        // Clear existing
        this.clearChildBoundaries();

        const selectedId = this.childrenPanel.selectedChild?.id;

        this.childrenPanel.children
            .filter(c => c.hasPolygon && c.polygon)
            .forEach(child => {
                const isSelected = child.id === selectedId;
                const points = child.polygon.points || child.polygon;

                const polygon = new fabric.Polygon(points, {
                    fill: isSelected ? 'rgba(59, 130, 246, 0.25)' : 'rgba(59, 130, 246, 0.1)',
                    stroke: '#3b82f6',
                    strokeWidth: isSelected ? 2 : 1,
                    opacity: isSelected ? 1 : 0.5,
                    selectable: false,
                    evented: true,  // Enable events for double-click selection
                    objectCaching: false,
                    isChildBoundary: true,
                    childId: child.id,
                    hoverCursor: 'pointer'
                });

                // Add double-click handler to select child
                polygon.on('mousedblclick', () => {
                    this.selectChild(child.id);
                    // Open children panel if not visible
                    if (!this.panels.children.visible) {
                        this.togglePanel('children');
                    }
                });

                this.canvas.add(polygon);
                this.childrenPanel.childBoundaries[child.id] = polygon;
            });

        this.reorderLayers();
        this.canvas.renderAll();
    }

    /**
     * Clear all child boundaries from canvas
     */
    clearChildBoundaries() {
        Object.values(this.childrenPanel.childBoundaries).forEach(polygon => {
            this.canvas.remove(polygon);
        });
        this.childrenPanel.childBoundaries = {};
    }

    /**
     * Highlight a specific child boundary
     */
    highlightChildBoundary(childId) {
        // Update all boundary styles
        Object.entries(this.childrenPanel.childBoundaries).forEach(([id, polygon]) => {
            const isSelected = id === childId;
            polygon.set({
                fill: isSelected ? 'rgba(59, 130, 246, 0.25)' : 'rgba(59, 130, 246, 0.1)',
                strokeWidth: isSelected ? 2 : 1,
                opacity: isSelected ? 1 : 0.5
            });
        });

        this.canvas.renderAll();
    }

    /**
     * Start drawing a child boundary
     */
    startDrawingChildBoundary() {
        if (!this.childrenPanel.selectedChild) {
            this.setStatus('יש לבחור ילד תחילה');
            return;
        }

        if (!this.boundary) {
            this.setStatus('יש להגדיר גבול מפה תחילה');
            return;
        }

        this.childrenPanel.isDrawingChildBoundary = true;
        this.childrenPanel.childBoundaryPoints = [];

        // Change cursor
        this.canvas.defaultCursor = 'crosshair';

        this.setStatus('לחץ להוספת נקודות. לחיצה כפולה לסיום.');
    }

    /**
     * Handle canvas click while drawing child boundary
     */
    handleChildBoundaryClick(e) {
        if (!this.childrenPanel.isDrawingChildBoundary) return false;

        const pointer = this.canvas.getPointer(e.e);
        const point = { x: pointer.x, y: pointer.y };

        // Validate point is inside parent boundary
        if (!this.isPointInsideParentBoundary(point)) {
            this.setStatus('הנקודה חייבת להיות בתוך גבול ההורה!');
            return true; // Handled but invalid
        }

        this.childrenPanel.childBoundaryPoints.push(point);

        // Draw temporary marker
        const marker = new fabric.Circle({
            left: point.x - 4,
            top: point.y - 4,
            radius: 4,
            fill: '#22c55e',
            stroke: '#16a34a',
            strokeWidth: 1,
            selectable: false,
            evented: false,
            isChildBoundaryMarker: true
        });
        this.canvas.add(marker);

        // Draw preview line if we have at least 2 points
        if (this.childrenPanel.childBoundaryPoints.length >= 2) {
            const points = this.childrenPanel.childBoundaryPoints;
            const lastTwo = [points[points.length - 2], points[points.length - 1]];

            const line = new fabric.Line(
                [lastTwo[0].x, lastTwo[0].y, lastTwo[1].x, lastTwo[1].y],
                {
                    stroke: '#22c55e',
                    strokeWidth: 2,
                    selectable: false,
                    evented: false,
                    isChildBoundaryLine: true
                }
            );
            this.canvas.add(line);
        }

        this.canvas.renderAll();
        this.setStatus(`נוספו ${this.childrenPanel.childBoundaryPoints.length} נקודות. לחיצה כפולה לסיום.`);

        return true; // Handled
    }

    /**
     * Finish drawing child boundary (on double-click)
     */
    async finishDrawingChildBoundary() {
        if (!this.childrenPanel.isDrawingChildBoundary) return;

        const points = this.childrenPanel.childBoundaryPoints;

        if (points.length < 3) {
            this.setStatus('נדרשות לפחות 3 נקודות לגבול');
            return;
        }

        // Validate all points are inside parent
        if (!this.validateChildBoundary(points)) {
            this.setStatus('כל נקודות הגבול חייבות להיות בתוך גבול ההורה!');
            return;
        }

        // Clear temporary markers and lines
        this.canvas.getObjects().filter(obj =>
            obj.isChildBoundaryMarker || obj.isChildBoundaryLine
        ).forEach(obj => this.canvas.remove(obj));

        // Save to server
        const child = this.childrenPanel.selectedChild;
        const polygon = { points: points };

        try {
            await this.saveChildPolygon(child.id, polygon);

            // Update local state
            child.hasPolygon = true;
            child.polygon = polygon;

            // Re-render
            this.renderChildrenList();
            this.renderChildBoundaries();

            this.setStatus('גבול הילד נשמר בהצלחה!', 'success');
        } catch (error) {
            console.error('Error saving child polygon:', error);
            this.setStatus('שגיאה בשמירת גבול: ' + error.message);
        } finally {
            // Reset state
            this.childrenPanel.isDrawingChildBoundary = false;
            this.childrenPanel.childBoundaryPoints = [];
            this.canvas.defaultCursor = 'default';
        }
    }

    /**
     * Cancel drawing child boundary
     */
    cancelDrawingChildBoundary() {
        // Clear temporary markers and lines
        this.canvas.getObjects().filter(obj =>
            obj.isChildBoundaryMarker || obj.isChildBoundaryLine
        ).forEach(obj => this.canvas.remove(obj));

        this.childrenPanel.isDrawingChildBoundary = false;
        this.childrenPanel.childBoundaryPoints = [];
        this.canvas.defaultCursor = 'default';

        this.setStatus('ציור גבול בוטל');
        this.canvas.renderAll();
    }

    /**
     * Start editing child boundary
     */
    startEditingChildBoundary() {
        const child = this.childrenPanel.selectedChild;
        if (!child || !child.hasPolygon) return;

        const polygon = this.childrenPanel.childBoundaries[child.id];
        if (!polygon) return;

        this.childrenPanel.isEditingChildBoundary = true;
        this.childrenPanel.editingPolygon = polygon;

        // Make polygon selectable and draggable with resize/rotate controls
        polygon.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            borderColor: '#2563eb',
            borderScaleFactor: 1.5,
            borderDashArray: [6, 4],
            cornerColor: '#3b82f6',
            cornerStrokeColor: '#fff',
            cornerStyle: 'circle',
            cornerSize: 10,
            transparentCorners: false,
            hoverCursor: 'move',
            objectCaching: false,
            perPixelTargetFind: true
        });

        // Handle polygon movement, scaling, and rotation - update anchor points
        polygon.on('moving', () => this.onChildBoundaryMove());
        polygon.on('scaling', () => this.onChildBoundaryTransform());
        polygon.on('rotating', () => this.onChildBoundaryTransform());
        polygon.on('modified', () => this.onChildBoundaryModified());

        // Show anchor points
        this.showChildAnchorPoints(polygon);

        // Automatically select the polygon so it can be dragged immediately
        this.canvas.setActiveObject(polygon);
        this.canvas.renderAll();

        // Update children list to show editing indicator
        this.renderChildrenList();

        this.setStatus('לחץ לבחירה וגרירה. גרור נקודות עיגון. דאבל-קליק להוספה. Escape לסיום.', 'editing');
    }

    /**
     * Handle child boundary polygon move - update anchor points
     */
    onChildBoundaryMove() {
        const polygon = this.childrenPanel.editingPolygon;
        if (!polygon) return;

        // Update anchor points to follow the polygon
        this.showChildAnchorPoints(polygon);
    }

    /**
     * Handle child boundary scaling or rotation - update anchor points
     */
    onChildBoundaryTransform() {
        const polygon = this.childrenPanel.editingPolygon;
        if (!polygon) return;

        // Update anchor points to follow the polygon
        this.showChildAnchorPoints(polygon);
        this.canvas.renderAll();
    }

    /**
     * Handle child boundary polygon modification complete
     * Bakes transformation into points after scale/rotate
     */
    onChildBoundaryModified() {
        const polygon = this.childrenPanel.editingPolygon;
        if (!polygon) return;

        // Check if polygon was scaled or rotated (not just moved)
        const wasTransformed = polygon.scaleX !== 1 || polygon.scaleY !== 1 || polygon.angle !== 0;

        if (wasTransformed) {
            // Bake transformation into points
            const matrix = polygon.calcTransformMatrix();
            const pathOffset = polygon.pathOffset || { x: 0, y: 0 };

            // Convert all points to absolute coordinates
            const absolutePoints = polygon.points.map(p => {
                return fabric.util.transformPoint(
                    { x: p.x - pathOffset.x, y: p.y - pathOffset.y },
                    matrix
                );
            });

            // Recreate polygon with absolute points (no transformation)
            const oldPolygon = polygon;
            const newPolygon = new fabric.Polygon(absolutePoints, {
                fill: oldPolygon.fill,
                stroke: oldPolygon.stroke,
                strokeWidth: oldPolygon.strokeWidth,
                selectable: true,
                evented: true,
                hasControls: true,
                hasBorders: true,
                borderColor: '#2563eb',
                borderScaleFactor: 1.5,
                borderDashArray: [6, 4],
                cornerColor: '#3b82f6',
                cornerStrokeColor: '#fff',
                cornerStyle: 'circle',
                cornerSize: 10,
                transparentCorners: false,
                hoverCursor: 'move',
                objectCaching: false,
                perPixelTargetFind: true,
                isChildBoundary: true,
                childId: oldPolygon.childId
            });

            newPolygon.on('moving', () => this.onChildBoundaryMove());
            newPolygon.on('scaling', () => this.onChildBoundaryTransform());
            newPolygon.on('rotating', () => this.onChildBoundaryTransform());
            newPolygon.on('modified', () => this.onChildBoundaryModified());

            // Replace in canvas and state
            this.canvas.remove(oldPolygon);
            this.canvas.add(newPolygon);

            const childId = oldPolygon.childId;
            this.childrenPanel.childBoundaries[childId] = newPolygon;
            this.childrenPanel.editingPolygon = newPolygon;

            // Select the new polygon
            this.canvas.setActiveObject(newPolygon);
        }

        this.showChildAnchorPoints(this.childrenPanel.editingPolygon);
        this.reorderLayers();
        this.canvas.renderAll();
    }

    /**
     * Stop editing child boundary
     */
    async stopEditingChildBoundary() {
        if (!this.childrenPanel.isEditingChildBoundary) return;

        const child = this.childrenPanel.selectedChild;
        const polygon = this.childrenPanel.editingPolygon;

        if (polygon) {
            // Remove event handlers
            polygon.off('moving');
            polygon.off('modified');

            // Get updated points from polygon
            const updatedPoints = polygon.points.map(p => ({ x: p.x, y: p.y }));

            // Reset polygon to non-selectable
            polygon.set({
                selectable: false,
                evented: false,
                hasBorders: false
            });

            // Update child data
            if (child) {
                child.polygon = { points: updatedPoints };

                // Save to server
                try {
                    await this.saveChildPolygon(child.id, child.polygon);
                    this.setStatus('גבול הילד נשמר בהצלחה', 'success');
                } catch (error) {
                    console.error('Error saving child boundary:', error);
                    this.setStatus('שגיאה בשמירת גבול: ' + error.message);
                }
            }
        }

        // Clear anchor points
        this.clearChildAnchorPoints();

        this.childrenPanel.isEditingChildBoundary = false;
        this.childrenPanel.editingPolygon = null;
        this.canvas.discardActiveObject();
        this.canvas.renderAll();

        // Update children list to remove editing indicator
        this.renderChildrenList();

        this.setStatus('עריכת גבול הילד הסתיימה');
    }

    /**
     * Show anchor points for child boundary editing
     */
    showChildAnchorPoints(polygon) {
        this.clearChildAnchorPoints();

        const points = polygon.points;
        // Use transformation matrix for correct absolute coordinates
        const matrix = polygon.calcTransformMatrix();
        const pathOffset = polygon.pathOffset || { x: 0, y: 0 };

        points.forEach((point, index) => {
            // Transform point using polygon's matrix
            const transformed = fabric.util.transformPoint(
                { x: point.x - pathOffset.x, y: point.y - pathOffset.y },
                matrix
            );

            const circle = new fabric.Circle({
                left: transformed.x,
                top: transformed.y,
                radius: 5,
                fill: '#3b82f6',
                stroke: '#fff',
                strokeWidth: 2,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                hasBorders: false,
                hasControls: false,
                hoverCursor: 'move',
                isChildAnchorPoint: true,
                pointIndex: index,
                shadow: new fabric.Shadow({
                    color: 'rgba(0,0,0,0.3)',
                    blur: 3,
                    offsetX: 0,
                    offsetY: 1
                })
            });

            circle.on('moving', () => this.onChildAnchorPointMove(circle, polygon));
            circle.on('modified', () => this.onChildAnchorPointModified());

            // Prevent polygon selection when clicking anchor point
            circle.on('mousedown', () => {
                // Keep only anchor point selected, not the polygon
                this.canvas.setActiveObject(circle);
            });

            this.canvas.add(circle);
            this.childrenPanel.childAnchorPoints.push(circle);
        });

        this.canvas.renderAll();
    }

    /**
     * Clear child anchor points
     */
    clearChildAnchorPoints() {
        // Remove all anchor points from canvas
        while (this.childrenPanel.childAnchorPoints.length > 0) {
            const circle = this.childrenPanel.childAnchorPoints.pop();
            circle.off('moving');
            circle.off('modified');
            this.canvas.remove(circle);
        }

        // Also find and remove any orphaned child anchor points on the canvas
        const orphanedAnchors = this.canvas.getObjects().filter(obj => obj.isChildAnchorPoint);
        orphanedAnchors.forEach(anchor => {
            anchor.off('moving');
            anchor.off('modified');
            this.canvas.remove(anchor);
        });
    }

    /**
     * Handle child anchor point movement - constrain to parent boundary
     */
    onChildAnchorPointMove(circle, polygon) {
        const index = circle.pointIndex;
        let screenPoint = { x: circle.left, y: circle.top };

        // Check if new position is inside parent boundary
        if (!this.isPointInsideParentBoundary(screenPoint)) {
            // Find closest point inside parent boundary
            const constrainedPoint = this.constrainPointToParentBoundary(screenPoint);
            circle.set({ left: constrainedPoint.x, top: constrainedPoint.y });
            screenPoint = constrainedPoint;
        }

        // Get pathOffset for the polygon
        const pathOffset = polygon.pathOffset || { x: 0, y: 0 };

        // Get inverse transformation matrix to convert screen coords back to polygon coords
        const matrix = polygon.calcTransformMatrix();
        const invertedMatrix = fabric.util.invertTransform(matrix);

        // Transform anchor point position back to polygon coordinate space
        const transformed = fabric.util.transformPoint(screenPoint, invertedMatrix);

        // Update polygon point in polygon coordinate space
        polygon.points[index] = {
            x: transformed.x + pathOffset.x,
            y: transformed.y + pathOffset.y
        };

        // Mark polygon as dirty to trigger re-render (without repositioning)
        polygon.set({ dirty: true });

        this.canvas.renderAll();
    }

    /**
     * Handle child anchor point modification complete
     */
    onChildAnchorPointModified() {
        this.canvas.renderAll();
    }

    /**
     * Handle double-click on child boundary edge to add anchor point
     */
    handleChildBoundaryDoubleClick(opt) {
        const polygon = this.childrenPanel.editingPolygon;
        if (!polygon) return;

        // Immediately deselect any active object (including the boundary)
        this.canvas.discardActiveObject();
        this.canvas.renderAll();

        const pointer = this.canvas.getPointer(opt.e);
        const clickPoint = { x: pointer.x, y: pointer.y };

        // Find the closest edge
        const points = polygon.points;
        const matrix = polygon.calcTransformMatrix();
        const pathOffset = polygon.pathOffset || { x: 0, y: 0 };

        let closestEdgeIndex = -1;
        let minDistance = Infinity;

        for (let i = 0; i < points.length; i++) {
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
            this.addChildAnchorPoint(closestEdgeIndex);
        }
    }

    /**
     * Show context menu for child anchor point
     */
    showChildAnchorContextMenu(clientX, clientY, anchor) {
        this.hideContextMenu();

        const menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.innerHTML = `
            <div class="context-menu-item" data-action="remove-child-anchor">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M19 13H5v-2h14v2z"/>
                </svg>
                <span>הסר נקודת עיגון</span>
            </div>
        `;
        menu.style.left = `${clientX}px`;
        menu.style.top = `${clientY}px`;

        document.body.appendChild(menu);
        this.contextMenu = menu;

        menu.querySelector('[data-action="remove-child-anchor"]').addEventListener('click', () => {
            this.removeChildAnchorPoint(anchor);
            this.hideContextMenu();
        });

        // Close menu on click outside
        setTimeout(() => {
            document.addEventListener('click', () => this.hideContextMenu(), { once: true });
        }, 0);
    }

    /**
     * Add anchor point to child boundary
     */
    addChildAnchorPoint(afterIndex) {
        const polygon = this.childrenPanel.editingPolygon;
        if (!polygon) return;

        const points = polygon.points;
        const matrix = polygon.calcTransformMatrix();
        const pathOffset = polygon.pathOffset || { x: 0, y: 0 };
        const nextIndex = (afterIndex + 1) % points.length;

        // Transform the two edge points to absolute coordinates
        const p1Abs = fabric.util.transformPoint(
            { x: points[afterIndex].x - pathOffset.x, y: points[afterIndex].y - pathOffset.y },
            matrix
        );
        const p2Abs = fabric.util.transformPoint(
            { x: points[nextIndex].x - pathOffset.x, y: points[nextIndex].y - pathOffset.y },
            matrix
        );

        // Calculate midpoint in absolute coordinates
        const newPointAbs = {
            x: (p1Abs.x + p2Abs.x) / 2,
            y: (p1Abs.y + p2Abs.y) / 2
        };

        // Check if new point is inside parent boundary
        if (!this.isPointInsideParentBoundary(newPointAbs)) {
            this.setStatus('לא ניתן להוסיף נקודה מחוץ לגבול ההורה');
            return;
        }

        // Convert all existing points to absolute coordinates
        const absolutePoints = points.map(p => {
            return fabric.util.transformPoint(
                { x: p.x - pathOffset.x, y: p.y - pathOffset.y },
                matrix
            );
        });

        // Insert new point
        absolutePoints.splice(afterIndex + 1, 0, newPointAbs);

        // Recreate polygon with absolute points (no transformation needed)
        const oldPolygon = polygon;
        const polygonProps = {
            fill: oldPolygon.fill,
            stroke: oldPolygon.stroke,
            strokeWidth: oldPolygon.strokeWidth,
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            borderColor: '#2563eb',
            borderScaleFactor: 1.5,
            borderDashArray: [6, 4],
            cornerColor: '#3b82f6',
            cornerStrokeColor: '#fff',
            cornerStyle: 'circle',
            cornerSize: 10,
            transparentCorners: false,
            hoverCursor: 'move',
            objectCaching: false,
            perPixelTargetFind: true,
            isChildBoundary: true,
            childId: oldPolygon.childId
        };

        const newPolygon = new fabric.Polygon(absolutePoints, polygonProps);
        newPolygon.on('moving', () => this.onChildBoundaryMove());
        newPolygon.on('scaling', () => this.onChildBoundaryTransform());
        newPolygon.on('rotating', () => this.onChildBoundaryTransform());
        newPolygon.on('modified', () => this.onChildBoundaryModified());

        // Replace in canvas and state
        this.canvas.remove(oldPolygon);
        this.canvas.add(newPolygon);

        const childId = oldPolygon.childId;
        this.childrenPanel.childBoundaries[childId] = newPolygon;
        this.childrenPanel.editingPolygon = newPolygon;

        // Refresh anchor points (don't select polygon - let user interact with anchors)
        this.showChildAnchorPoints(newPolygon);
        this.reorderLayers();
        this.canvas.renderAll();

        this.setStatus('נקודה חדשה נוספה', 'editing');
    }

    /**
     * Remove anchor point from child boundary
     */
    removeChildAnchorPoint(anchor) {
        const polygon = this.childrenPanel.editingPolygon;
        if (!polygon) return;

        const index = anchor.pointIndex;
        const points = polygon.points;

        // Must have at least 3 points
        if (points.length <= 3) {
            this.setStatus('לא ניתן להסיר - נדרשות לפחות 3 נקודות');
            return;
        }

        // Get transformation data
        const matrix = polygon.calcTransformMatrix();
        const pathOffset = polygon.pathOffset || { x: 0, y: 0 };

        // Convert all points to absolute coordinates, excluding the removed point
        const absolutePoints = points
            .filter((_, i) => i !== index)
            .map(p => {
                return fabric.util.transformPoint(
                    { x: p.x - pathOffset.x, y: p.y - pathOffset.y },
                    matrix
                );
            });

        // Recreate polygon with absolute points
        const oldPolygon = polygon;
        const polygonProps = {
            fill: oldPolygon.fill,
            stroke: oldPolygon.stroke,
            strokeWidth: oldPolygon.strokeWidth,
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            borderColor: '#2563eb',
            borderScaleFactor: 1.5,
            borderDashArray: [6, 4],
            cornerColor: '#3b82f6',
            cornerStrokeColor: '#fff',
            cornerStyle: 'circle',
            cornerSize: 10,
            transparentCorners: false,
            hoverCursor: 'move',
            objectCaching: false,
            perPixelTargetFind: true,
            isChildBoundary: true,
            childId: oldPolygon.childId
        };

        const newPolygon = new fabric.Polygon(absolutePoints, polygonProps);
        newPolygon.on('moving', () => this.onChildBoundaryMove());
        newPolygon.on('scaling', () => this.onChildBoundaryTransform());
        newPolygon.on('rotating', () => this.onChildBoundaryTransform());
        newPolygon.on('modified', () => this.onChildBoundaryModified());

        // Replace in canvas and state
        this.canvas.remove(oldPolygon);
        this.canvas.add(newPolygon);

        const childId = oldPolygon.childId;
        this.childrenPanel.childBoundaries[childId] = newPolygon;
        this.childrenPanel.editingPolygon = newPolygon;

        // Refresh anchor points (don't select polygon - let user interact with anchors)
        this.showChildAnchorPoints(newPolygon);
        this.reorderLayers();
        this.canvas.renderAll();

        this.setStatus(`נקודה ${index + 1} נמחקה`, 'editing');
    }

    /**
     * Constrain a point to be inside parent boundary
     */
    constrainPointToParentBoundary(point) {
        if (!this.boundaryPoints || this.boundaryPoints.length < 3) {
            return point;
        }

        // If point is inside, return as-is
        if (this.isPointInsideParentBoundary(point)) {
            return point;
        }

        // Find closest point on parent boundary edges
        let closestPoint = point;
        let minDist = Infinity;

        for (let i = 0; i < this.boundaryPoints.length; i++) {
            const p1 = this.boundaryPoints[i];
            const p2 = this.boundaryPoints[(i + 1) % this.boundaryPoints.length];

            const closest = this.closestPointOnSegment(point, p1, p2);
            const dist = Math.hypot(closest.x - point.x, closest.y - point.y);

            if (dist < minDist) {
                minDist = dist;
                closestPoint = closest;
            }
        }

        // Move slightly inside
        const center = this.getPolygonCenter(this.boundaryPoints);
        const dx = center.x - closestPoint.x;
        const dy = center.y - closestPoint.y;
        const len = Math.hypot(dx, dy);
        if (len > 0) {
            closestPoint.x += (dx / len) * 5;
            closestPoint.y += (dy / len) * 5;
        }

        return closestPoint;
    }

    /**
     * Get closest point on a line segment
     */
    closestPointOnSegment(point, segStart, segEnd) {
        const dx = segEnd.x - segStart.x;
        const dy = segEnd.y - segStart.y;
        const lenSq = dx * dx + dy * dy;

        if (lenSq === 0) return { x: segStart.x, y: segStart.y };

        let t = ((point.x - segStart.x) * dx + (point.y - segStart.y) * dy) / lenSq;
        t = Math.max(0, Math.min(1, t));

        return {
            x: segStart.x + t * dx,
            y: segStart.y + t * dy
        };
    }

    /**
     * Get center of polygon
     */
    getPolygonCenter(points) {
        let cx = 0, cy = 0;
        points.forEach(p => { cx += p.x; cy += p.y; });
        return { x: cx / points.length, y: cy / points.length };
    }

    /**
     * Save child polygon to server
     */
    async saveChildPolygon(childId, polygon) {
        const child = this.childrenPanel.children.find(c => c.id === childId);
        if (!child) throw new Error('ילד לא נמצא');

        const response = await fetch(`${this.config.apiBase}map-data.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'saveChildPolygon',
                childType: child.type,
                childId: childId,
                polygon: polygon
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'שגיאה בשמירה');
        }

        return result;
    }

    /**
     * Delete child boundary
     */
    async deleteChildBoundary() {
        const child = this.childrenPanel.selectedChild;
        if (!child || !child.hasPolygon) return;

        if (!confirm(`למחוק את גבול "${child.name}"?`)) return;

        try {
            const response = await fetch(`${this.config.apiBase}map-data.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'deleteChildPolygon',
                    childType: child.type,
                    childId: child.id
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'שגיאה במחיקה');
            }

            // Remove from canvas
            const polygon = this.childrenPanel.childBoundaries[child.id];
            if (polygon) {
                this.canvas.remove(polygon);
                delete this.childrenPanel.childBoundaries[child.id];
            }

            // Update state
            child.hasPolygon = false;
            child.polygon = null;

            // Update UI
            this.renderChildrenList();
            this.updateChildControls();
            this.canvas.renderAll();

            this.setStatus('הגבול נמחק בהצלחה', 'success');
        } catch (error) {
            console.error('Error deleting child polygon:', error);
            this.setStatus('שגיאה במחיקת גבול: ' + error.message);
        }
    }

    /**
     * Check if a point is inside the parent boundary
     */
    isPointInsideParentBoundary(point) {
        if (!this.boundary || !this.boundaryPoints || this.boundaryPoints.length < 3) {
            return false;
        }
        return this.isPointInPolygonPoints(point, this.boundaryPoints);
    }

    /**
     * Point-in-polygon test using ray casting algorithm
     */
    isPointInPolygonPoints(point, polygonPoints) {
        let inside = false;
        const x = point.x;
        const y = point.y;
        const n = polygonPoints.length;

        for (let i = 0, j = n - 1; i < n; j = i++) {
            const xi = polygonPoints[i].x;
            const yi = polygonPoints[i].y;
            const xj = polygonPoints[j].x;
            const yj = polygonPoints[j].y;

            if (((yi > y) !== (yj > y)) &&
                (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }

        return inside;
    }

    /**
     * Validate that all points of a child boundary are inside parent
     */
    validateChildBoundary(points) {
        return points.every(point => this.isPointInsideParentBoundary(point));
    }

    /**
     * Check if parent boundary can be modified to new points
     * (without leaving any child boundaries outside)
     */
    canModifyParentBoundary(newPoints) {
        const childrenWithBoundaries = this.childrenPanel.children.filter(c => c.hasPolygon && c.polygon);

        if (childrenWithBoundaries.length === 0) return true;

        // Check each child's boundary points are inside new parent boundary
        for (const child of childrenWithBoundaries) {
            const childPoints = child.polygon.points || child.polygon;

            for (const point of childPoints) {
                if (!this.isPointInPolygonPoints(point, newPoints)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if parent boundary can be deleted
     */
    canDeleteParentBoundary() {
        const childrenWithBoundaries = this.childrenPanel.children.filter(c => c.hasPolygon);
        return childrenWithBoundaries.length === 0;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.mapEditor = new MapEditor(MAP_CONFIG);
});
