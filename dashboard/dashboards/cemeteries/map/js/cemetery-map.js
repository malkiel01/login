/**
 * Cemetery Map - Main JavaScript
 * מפת בית עלמין אינטראקטיבית
 */

class CemeteryMap {
    constructor(config) {
        this.config = config;
        this.canvas = null;
        this.entities = [];
        this.selectedEntity = null;
        this.currentZoom = 1;
        this.minZoom = 0.1;
        this.maxZoom = 5;
        this.isPanning = false;
        this.lastPosX = 0;
        this.lastPosY = 0;
        this.mode = config.mode || 'view';
        this.currentTool = 'select';

        // Zoom levels for different detail displays
        this.zoomLevels = {
            blocks: 0.3,      // Show blocks at this zoom and above
            plots: 0.6,       // Show plots at this zoom and above
            areaGraves: 1.2,  // Show areaGraves at this zoom and above
            details: 2        // Show details at this zoom and above
        };

        this.init();
    }

    async init() {
        this.setupCanvas();
        this.setupEventListeners();
        this.setupKeyboardShortcuts();

        if (this.mode === 'edit') {
            document.body.classList.add('edit-mode');
        }

        await this.loadData();
        this.hideLoading();
    }

    setupCanvas() {
        const container = document.getElementById('canvasWrapper');
        const containerRect = container.getBoundingClientRect();

        // Create Fabric.js canvas
        this.canvas = new fabric.Canvas('mapCanvas', {
            width: containerRect.width,
            height: containerRect.height,
            backgroundColor: '#f8f9fa',
            selection: this.mode === 'edit',
            preserveObjectStacking: true
        });

        // Handle window resize
        window.addEventListener('resize', () => this.handleResize());
    }

    setupEventListeners() {
        // Zoom controls
        document.getElementById('btnZoomIn')?.addEventListener('click', () => this.zoom(0.2));
        document.getElementById('btnZoomOut')?.addEventListener('click', () => this.zoom(-0.2));
        document.getElementById('btnZoomFit')?.addEventListener('click', () => this.zoomToFit());

        // Navigation
        document.getElementById('btnBack')?.addEventListener('click', () => this.goBack());
        document.getElementById('btnEditMode')?.addEventListener('click', () => this.switchToEditMode());
        document.getElementById('btnSave')?.addEventListener('click', () => this.saveMap());

        // Tool buttons
        document.getElementById('btnSelect')?.addEventListener('click', () => this.setTool('select'));
        document.getElementById('btnDraw')?.addEventListener('click', () => this.setTool('draw'));
        document.getElementById('btnEdit')?.addEventListener('click', () => this.setTool('edit'));
        document.getElementById('btnAddImage')?.addEventListener('click', () => this.addBackgroundImage());

        // Sidebar
        document.getElementById('btnToggleSidebar')?.addEventListener('click', () => this.toggleSidebar());
        document.getElementById('sidebarSearch')?.addEventListener('input', (e) => this.filterSidebar(e.target.value));

        // Info panel
        document.getElementById('btnCloseInfo')?.addEventListener('click', () => this.hideInfoPanel());

        // Background image input
        document.getElementById('backgroundImageInput')?.addEventListener('change', (e) => this.handleBackgroundImage(e));

        // Canvas events
        this.canvas.on('mouse:wheel', (opt) => this.handleMouseWheel(opt));
        this.canvas.on('mouse:down', (opt) => this.handleMouseDown(opt));
        this.canvas.on('mouse:move', (opt) => this.handleMouseMove(opt));
        this.canvas.on('mouse:up', (opt) => this.handleMouseUp(opt));
        this.canvas.on('mouse:over', (opt) => this.handleMouseOver(opt));
        this.canvas.on('mouse:out', (opt) => this.handleMouseOut(opt));
        this.canvas.on('selection:created', (opt) => this.handleSelection(opt));
        this.canvas.on('selection:updated', (opt) => this.handleSelection(opt));
        this.canvas.on('selection:cleared', () => this.clearSelection());
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Escape - cancel drawing or clear selection
            if (e.key === 'Escape') {
                if (this.currentTool === 'draw' && window.polygonEditor) {
                    window.polygonEditor.cancelDrawing();
                }
                this.canvas.discardActiveObject();
                this.canvas.renderAll();
                this.hideContextMenu();
            }

            // Delete - remove selected polygon
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.mode === 'edit') {
                const activeObject = this.canvas.getActiveObject();
                if (activeObject && activeObject.entityData) {
                    this.deletePolygon(activeObject);
                }
            }

            // Ctrl+S - save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (this.mode === 'edit') {
                    this.saveMap();
                }
            }

            // Zoom shortcuts
            if (e.ctrlKey && (e.key === '+' || e.key === '=')) {
                e.preventDefault();
                this.zoom(0.2);
            }
            if (e.ctrlKey && e.key === '-') {
                e.preventDefault();
                this.zoom(-0.2);
            }
            if (e.ctrlKey && e.key === '0') {
                e.preventDefault();
                this.zoomToFit();
            }
        });
    }

    // =====================================================
    // Data Loading
    // =====================================================

    async loadData() {
        try {
            // Load main entity
            const mainEntity = await this.fetchEntity(this.config.entityType, this.config.entityId);
            if (!mainEntity) {
                throw new Error('Entity not found');
            }

            // Update title
            this.updateTitle(mainEntity);

            // Load map settings if available
            if (mainEntity.mapSettings) {
                this.applyMapSettings(mainEntity.mapSettings);
            }

            // Load background image if available
            if (mainEntity.mapBackgroundImage) {
                await this.loadBackgroundImage(mainEntity.mapBackgroundImage);
            }

            // Draw main entity polygon if exists
            if (mainEntity.mapPolygon) {
                this.drawEntityPolygon(mainEntity, this.config.entityType);
            }

            // Load and draw children
            const childrenType = this.config.entityConfig[this.config.entityType]?.children;
            if (childrenType) {
                const children = await this.fetchChildren(this.config.entityType, this.config.entityId, childrenType);
                this.entities = children;
                this.renderEntities(children, childrenType);
                this.updateSidebar(children, childrenType);
            }

            // Zoom to fit content
            setTimeout(() => this.zoomToFit(), 100);

        } catch (error) {
            console.error('Error loading data:', error);
            this.showError('שגיאה בטעינת המפה');
        }
    }

    async fetchEntity(type, id) {
        const apiEndpoint = this.getApiEndpoint(type);
        const response = await fetch(`${this.config.apiBase}${apiEndpoint}?unicId=${id}`);
        const data = await response.json();
        return data.data || data;
    }

    async fetchChildren(parentType, parentId, childType) {
        const apiEndpoint = this.getApiEndpoint(childType);
        const parentField = this.getParentField(childType);
        const response = await fetch(`${this.config.apiBase}${apiEndpoint}?${parentField}=${parentId}&limit=1000`);
        const data = await response.json();
        return data.data || [];
    }

    getApiEndpoint(type) {
        const endpoints = {
            'cemetery': 'cemeteries-api.php',
            'block': 'blocks-api.php',
            'plot': 'plots-api.php',
            'areaGrave': 'areaGraves-api.php',
            'row': 'rows-api.php'
        };
        return endpoints[type] || 'cemeteries-api.php';
    }

    getParentField(type) {
        const fields = {
            'block': 'cemeteryId',
            'plot': 'blockId',
            'row': 'plotId',
            'areaGrave': 'lineId'
        };
        return fields[type] || 'parentId';
    }

    // =====================================================
    // Rendering
    // =====================================================

    renderEntities(entities, type) {
        entities.forEach(entity => {
            if (entity.mapPolygon) {
                this.drawEntityPolygon(entity, type);
            } else {
                // Create placeholder for entities without polygon
                this.createPlaceholder(entity, type);
            }
        });
    }

    drawEntityPolygon(entity, type) {
        const polygonData = typeof entity.mapPolygon === 'string'
            ? JSON.parse(entity.mapPolygon)
            : entity.mapPolygon;

        if (!polygonData || !polygonData.points || polygonData.points.length < 3) {
            return null;
        }

        const color = this.config.colors[type] || '#999999';
        const style = polygonData.style || {};

        const polygon = new fabric.Polygon(polygonData.points, {
            fill: style.fillColor || this.hexToRgba(color, 0.2),
            stroke: style.strokeColor || color,
            strokeWidth: style.strokeWidth || 2,
            selectable: this.mode === 'edit',
            hasControls: this.mode === 'edit',
            hasBorders: true,
            objectCaching: false,
            // Custom data
            entityData: {
                id: entity.id,
                unicId: entity.unicId,
                type: type,
                name: this.getEntityName(entity, type),
                data: entity
            }
        });

        // Add label
        const label = this.createLabel(entity, type, polygon);

        // Group polygon and label
        const group = new fabric.Group([polygon, label], {
            selectable: this.mode === 'edit',
            hasControls: this.mode === 'edit',
            subTargetCheck: true,
            entityData: polygon.entityData
        });

        // Store reference
        polygon.labelObject = label;
        polygon.groupObject = group;

        this.canvas.add(group);
        return group;
    }

    createLabel(entity, type, polygon) {
        const name = this.getEntityName(entity, type);
        const bounds = polygon.getBoundingRect();
        const centerX = bounds.left + bounds.width / 2;
        const centerY = bounds.top + bounds.height / 2;

        const text = new fabric.Text(name, {
            left: centerX,
            top: centerY,
            fontSize: 14,
            fontFamily: 'Arial, sans-serif',
            fill: '#333333',
            textAlign: 'center',
            originX: 'center',
            originY: 'center',
            selectable: false,
            evented: false
        });

        return text;
    }

    createPlaceholder(entity, type) {
        // For entities without defined polygons, we'll add them to sidebar only
        // They can be drawn later in edit mode
    }

    getEntityName(entity, type) {
        const nameFields = {
            'cemetery': 'cemeteryNameHe',
            'block': 'blockNameHe',
            'plot': 'plotNameHe',
            'row': 'lineNameHe',
            'areaGrave': 'areaGraveNameHe'
        };
        return entity[nameFields[type]] || entity.name || 'ללא שם';
    }

    updateTitle(entity) {
        const title = this.getEntityName(entity, this.config.entityType);
        document.getElementById('entityTitle').textContent = title;
    }

    // =====================================================
    // Zoom & Pan
    // =====================================================

    handleMouseWheel(opt) {
        const delta = opt.e.deltaY;
        let zoom = this.canvas.getZoom();
        zoom *= 0.999 ** delta;

        if (zoom > this.maxZoom) zoom = this.maxZoom;
        if (zoom < this.minZoom) zoom = this.minZoom;

        this.canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
        this.currentZoom = zoom;
        this.updateZoomDisplay();
        this.updateVisibility();

        opt.e.preventDefault();
        opt.e.stopPropagation();
    }

    handleMouseDown(opt) {
        const evt = opt.e;

        // Right click - show context menu
        if (evt.button === 2) {
            evt.preventDefault();
            const target = opt.target;
            if (target && target.entityData) {
                this.showContextMenu(evt.clientX, evt.clientY, target);
            }
            return;
        }

        // Drawing mode
        if (this.currentTool === 'draw' && window.polygonEditor) {
            window.polygonEditor.handleClick(opt);
            return;
        }

        // Pan mode (middle button or space+click)
        if (evt.button === 1 || (this.currentTool === 'select' && !opt.target)) {
            this.isPanning = true;
            this.lastPosX = evt.clientX;
            this.lastPosY = evt.clientY;
            this.canvas.selection = false;
            document.getElementById('canvasWrapper').classList.add('grabbing');
        }
    }

    handleMouseMove(opt) {
        if (this.isPanning) {
            const e = opt.e;
            const vpt = this.canvas.viewportTransform;
            vpt[4] += e.clientX - this.lastPosX;
            vpt[5] += e.clientY - this.lastPosY;
            this.lastPosX = e.clientX;
            this.lastPosY = e.clientY;
            this.canvas.requestRenderAll();
        }

        // Update tooltip position
        if (opt.target && opt.target.entityData) {
            this.updateTooltip(opt.e, opt.target.entityData);
        }

        // Drawing mode
        if (this.currentTool === 'draw' && window.polygonEditor) {
            window.polygonEditor.handleMove(opt);
        }
    }

    handleMouseUp(opt) {
        this.isPanning = false;
        this.canvas.selection = this.mode === 'edit';
        document.getElementById('canvasWrapper').classList.remove('grabbing');
    }

    zoom(delta) {
        let zoom = this.canvas.getZoom();
        zoom += delta;

        if (zoom > this.maxZoom) zoom = this.maxZoom;
        if (zoom < this.minZoom) zoom = this.minZoom;

        const center = this.canvas.getCenter();
        this.canvas.zoomToPoint({ x: center.left, y: center.top }, zoom);
        this.currentZoom = zoom;
        this.updateZoomDisplay();
        this.updateVisibility();
    }

    zoomToFit() {
        const objects = this.canvas.getObjects();
        if (objects.length === 0) {
            this.canvas.setZoom(1);
            this.canvas.viewportTransform = [1, 0, 0, 1, 0, 0];
            return;
        }

        // Get bounding box of all objects
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

        objects.forEach(obj => {
            const bounds = obj.getBoundingRect();
            minX = Math.min(minX, bounds.left);
            minY = Math.min(minY, bounds.top);
            maxX = Math.max(maxX, bounds.left + bounds.width);
            maxY = Math.max(maxY, bounds.top + bounds.height);
        });

        const width = maxX - minX;
        const height = maxY - minY;
        const canvasWidth = this.canvas.getWidth();
        const canvasHeight = this.canvas.getHeight();

        const scaleX = (canvasWidth * 0.9) / width;
        const scaleY = (canvasHeight * 0.9) / height;
        const zoom = Math.min(scaleX, scaleY, this.maxZoom);

        const centerX = (minX + maxX) / 2;
        const centerY = (minY + maxY) / 2;

        this.canvas.setZoom(zoom);
        this.canvas.absolutePan({
            x: centerX * zoom - canvasWidth / 2,
            y: centerY * zoom - canvasHeight / 2
        });

        this.currentZoom = zoom;
        this.updateZoomDisplay();
        this.updateVisibility();
    }

    updateZoomDisplay() {
        const percentage = Math.round(this.currentZoom * 100);
        document.getElementById('zoomLevel').textContent = `${percentage}%`;
    }

    updateVisibility() {
        // Update visibility based on zoom level
        const zoom = this.currentZoom;

        this.canvas.getObjects().forEach(obj => {
            if (!obj.entityData) return;

            const type = obj.entityData.type;
            let minZoom = 0;

            switch (type) {
                case 'block':
                    minZoom = this.zoomLevels.blocks;
                    break;
                case 'plot':
                    minZoom = this.zoomLevels.plots;
                    break;
                case 'areaGrave':
                    minZoom = this.zoomLevels.areaGraves;
                    break;
            }

            obj.visible = zoom >= minZoom;

            // Update label visibility and size
            if (obj._objects) {
                const label = obj._objects.find(o => o.type === 'text');
                if (label) {
                    label.visible = zoom >= minZoom * 1.5;
                    label.set('fontSize', Math.max(10, Math.min(16, 14 / zoom)));
                }
            }
        });

        this.canvas.renderAll();
    }

    // =====================================================
    // Mouse Events
    // =====================================================

    handleMouseOver(opt) {
        if (opt.target && opt.target.entityData) {
            this.showTooltip(opt.e, opt.target.entityData);

            // Highlight
            if (opt.target._objects) {
                const polygon = opt.target._objects.find(o => o.type === 'polygon');
                if (polygon) {
                    polygon.set('strokeWidth', 3);
                    this.canvas.renderAll();
                }
            }
        }
    }

    handleMouseOut(opt) {
        this.hideTooltip();

        if (opt.target && opt.target._objects) {
            const polygon = opt.target._objects.find(o => o.type === 'polygon');
            if (polygon) {
                polygon.set('strokeWidth', 2);
                this.canvas.renderAll();
            }
        }
    }

    handleSelection(opt) {
        const selected = opt.selected?.[0];
        if (selected && selected.entityData) {
            this.selectedEntity = selected.entityData;
            this.showInfoPanel(selected.entityData);
            this.highlightSidebarItem(selected.entityData.unicId);
        }
    }

    clearSelection() {
        this.selectedEntity = null;
        this.hideInfoPanel();
        this.clearSidebarHighlight();
    }

    // =====================================================
    // Tooltip
    // =====================================================

    showTooltip(event, entityData) {
        const tooltip = document.getElementById('mapTooltip');
        tooltip.innerHTML = `
            <div class="tooltip-title">${entityData.name}</div>
            <div class="tooltip-info">${this.config.entityConfig[entityData.type]?.title || entityData.type}</div>
        `;
        tooltip.classList.add('visible');
        this.updateTooltip(event, entityData);
    }

    updateTooltip(event, entityData) {
        const tooltip = document.getElementById('mapTooltip');
        tooltip.style.left = `${event.clientX + 15}px`;
        tooltip.style.top = `${event.clientY + 15}px`;
    }

    hideTooltip() {
        document.getElementById('mapTooltip').classList.remove('visible');
    }

    // =====================================================
    // Context Menu
    // =====================================================

    showContextMenu(x, y, target) {
        const menu = document.getElementById('contextMenu');
        menu.style.left = `${x}px`;
        menu.style.top = `${y}px`;
        menu.classList.add('visible');
        menu.dataset.targetId = target.entityData?.unicId;

        // Update drill-down visibility
        const drillDownItem = document.getElementById('menuDrillDown');
        const hasChildren = this.config.entityConfig[target.entityData?.type]?.children;
        drillDownItem.style.display = hasChildren ? 'flex' : 'none';

        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', this.hideContextMenu);
        }, 0);
    }

    hideContextMenu = () => {
        document.getElementById('contextMenu').classList.remove('visible');
        document.removeEventListener('click', this.hideContextMenu);
    }

    // =====================================================
    // Sidebar
    // =====================================================

    updateSidebar(entities, type) {
        const list = document.getElementById('sidebarList');
        const title = document.getElementById('sidebarTitle');
        const config = this.config.entityConfig[type];

        title.textContent = config?.title || 'רשימה';

        list.innerHTML = entities.map(entity => {
            const name = this.getEntityName(entity, type);
            const hasPolygon = !!entity.mapPolygon;
            const color = this.config.colors[type];
            const count = entity.graveSum || entity.count || '';

            return `
                <div class="sidebar-item ${hasPolygon ? '' : 'no-polygon'}"
                     data-id="${entity.unicId}"
                     onclick="cemeteryMap.focusOnEntity('${entity.unicId}')">
                    <div class="item-color" style="background: ${color}"></div>
                    <span class="item-name">${name}</span>
                    ${count ? `<span class="item-count">${count}</span>` : ''}
                </div>
            `;
        }).join('');

        // Update stats
        this.updateSidebarStats(entities);
    }

    updateSidebarStats(entities) {
        const stats = document.getElementById('sidebarStats');
        const withPolygon = entities.filter(e => e.mapPolygon).length;
        const total = entities.length;

        stats.innerHTML = `
            <div class="stat-row">
                <span class="stat-label">סה"כ</span>
                <span class="stat-value">${total}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">עם פוליגון</span>
                <span class="stat-value">${withPolygon}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">ללא פוליגון</span>
                <span class="stat-value">${total - withPolygon}</span>
            </div>
        `;
    }

    filterSidebar(query) {
        const items = document.querySelectorAll('.sidebar-item');
        const lowerQuery = query.toLowerCase();

        items.forEach(item => {
            const name = item.querySelector('.item-name').textContent.toLowerCase();
            item.style.display = name.includes(lowerQuery) ? 'flex' : 'none';
        });
    }

    toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    highlightSidebarItem(unicId) {
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.toggle('active', item.dataset.id === unicId);
        });
    }

    clearSidebarHighlight() {
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.remove('active');
        });
    }

    focusOnEntity(unicId) {
        const obj = this.canvas.getObjects().find(o => o.entityData?.unicId === unicId);
        if (obj) {
            const bounds = obj.getBoundingRect();
            const zoom = Math.min(2, this.canvas.getWidth() / (bounds.width * 2));

            this.canvas.setZoom(zoom);
            this.canvas.absolutePan({
                x: (bounds.left + bounds.width / 2) * zoom - this.canvas.getWidth() / 2,
                y: (bounds.top + bounds.height / 2) * zoom - this.canvas.getHeight() / 2
            });

            this.canvas.setActiveObject(obj);
            this.currentZoom = zoom;
            this.updateZoomDisplay();
            this.canvas.renderAll();
        }
    }

    // =====================================================
    // Info Panel
    // =====================================================

    showInfoPanel(entityData) {
        const panel = document.getElementById('infoPanel');
        const title = document.getElementById('infoPanelTitle');
        const content = document.getElementById('infoPanelContent');

        title.textContent = entityData.name;

        const data = entityData.data;
        let html = `
            <div class="info-row">
                <span class="info-label">סוג</span>
                <span class="info-value">${this.config.entityConfig[entityData.type]?.title || entityData.type}</span>
            </div>
        `;

        // Add relevant stats based on entity type
        if (data.graveSum !== undefined) {
            html += `
                <div class="info-row">
                    <span class="info-label">סה"כ קברים</span>
                    <span class="info-value">${data.graveSum || 0}</span>
                </div>
            `;
        }

        if (data.availableSum !== undefined) {
            html += `
                <div class="info-row">
                    <span class="info-label">פנויים</span>
                    <span class="info-value"><span class="status-badge available">${data.availableSum || 0}</span></span>
                </div>
            `;
        }

        if (data.purchasedSum !== undefined) {
            html += `
                <div class="info-row">
                    <span class="info-label">נרכשו</span>
                    <span class="info-value"><span class="status-badge purchased">${data.purchasedSum || 0}</span></span>
                </div>
            `;
        }

        if (data.buriedSum !== undefined) {
            html += `
                <div class="info-row">
                    <span class="info-label">קבורים</span>
                    <span class="info-value"><span class="status-badge buried">${data.buriedSum || 0}</span></span>
                </div>
            `;
        }

        content.innerHTML = html;
        panel.classList.add('visible');
    }

    hideInfoPanel() {
        document.getElementById('infoPanel').classList.remove('visible');
    }

    // =====================================================
    // Tools
    // =====================================================

    setTool(tool) {
        this.currentTool = tool;

        // Update button states
        document.querySelectorAll('.btn-tool').forEach(btn => {
            btn.dataset.active = 'false';
        });
        document.getElementById(`btn${tool.charAt(0).toUpperCase() + tool.slice(1)}`)?.setAttribute('data-active', 'true');

        // Update canvas cursor
        const wrapper = document.getElementById('canvasWrapper');
        wrapper.classList.remove('drawing', 'grabbing');

        if (tool === 'draw') {
            wrapper.classList.add('drawing');
            this.canvas.selection = false;
            this.canvas.discardActiveObject();
            this.canvas.renderAll();
        } else if (tool === 'select') {
            this.canvas.selection = true;
        } else if (tool === 'edit') {
            this.canvas.selection = true;
            // Enable point editing mode
        }
    }

    // =====================================================
    // Background Image
    // =====================================================

    addBackgroundImage() {
        document.getElementById('backgroundImageInput').click();
    }

    handleBackgroundImage(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            fabric.Image.fromURL(e.target.result, (img) => {
                // Scale image to fit canvas
                const scale = Math.min(
                    this.canvas.getWidth() / img.width,
                    this.canvas.getHeight() / img.height
                );

                img.set({
                    scaleX: scale,
                    scaleY: scale,
                    left: 0,
                    top: 0,
                    selectable: this.mode === 'edit',
                    isBackgroundImage: true
                });

                // Remove existing background
                const existingBg = this.canvas.getObjects().find(o => o.isBackgroundImage);
                if (existingBg) {
                    this.canvas.remove(existingBg);
                }

                // Add and send to back
                this.canvas.add(img);
                this.canvas.sendToBack(img);
                this.canvas.renderAll();
            });
        };
        reader.readAsDataURL(file);
    }

    async loadBackgroundImage(imageData) {
        if (!imageData || !imageData.path) return;

        return new Promise((resolve) => {
            fabric.Image.fromURL(imageData.path, (img) => {
                img.set({
                    scaleX: imageData.scale || 1,
                    scaleY: imageData.scale || 1,
                    left: imageData.offsetX || 0,
                    top: imageData.offsetY || 0,
                    selectable: this.mode === 'edit',
                    isBackgroundImage: true
                });

                this.canvas.add(img);
                this.canvas.sendToBack(img);
                resolve();
            });
        });
    }

    // =====================================================
    // Save & Navigation
    // =====================================================

    async saveMap() {
        try {
            this.showLoading();

            const objects = this.canvas.getObjects();
            const updates = [];

            objects.forEach(obj => {
                if (obj.entityData && obj._objects) {
                    const polygon = obj._objects.find(o => o.type === 'polygon');
                    if (polygon) {
                        updates.push({
                            unicId: obj.entityData.unicId,
                            type: obj.entityData.type,
                            mapPolygon: {
                                points: polygon.points,
                                style: {
                                    fillColor: polygon.fill,
                                    strokeColor: polygon.stroke,
                                    strokeWidth: polygon.strokeWidth
                                }
                            }
                        });
                    }
                }
            });

            // Save background image info
            const bgImage = objects.find(o => o.isBackgroundImage);
            let backgroundData = null;
            if (bgImage) {
                backgroundData = {
                    path: bgImage.getSrc(),
                    scale: bgImage.scaleX,
                    offsetX: bgImage.left,
                    offsetY: bgImage.top
                };
            }

            // Send to API
            const response = await fetch(`${this.config.apiBase}map-api.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'saveMap',
                    entityType: this.config.entityType,
                    entityId: this.config.entityId,
                    polygons: updates,
                    backgroundImage: backgroundData,
                    mapSettings: {
                        canvasWidth: this.canvas.getWidth(),
                        canvasHeight: this.canvas.getHeight(),
                        initialZoom: this.currentZoom
                    }
                })
            });

            const result = await response.json();
            if (result.success) {
                this.showSuccess('המפה נשמרה בהצלחה');
            } else {
                throw new Error(result.error || 'שגיאה בשמירה');
            }

        } catch (error) {
            console.error('Save error:', error);
            this.showError('שגיאה בשמירת המפה');
        } finally {
            this.hideLoading();
        }
    }

    switchToEditMode() {
        const url = new URL(window.location);
        url.searchParams.set('mode', 'edit');
        window.location.href = url.toString();
    }

    goBack() {
        window.history.back();
    }

    // =====================================================
    // Utility
    // =====================================================

    hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    handleResize() {
        const container = document.getElementById('canvasWrapper');
        const rect = container.getBoundingClientRect();
        this.canvas.setDimensions({ width: rect.width, height: rect.height });
        this.canvas.renderAll();
    }

    showLoading() {
        document.getElementById('loadingOverlay').classList.remove('hidden');
    }

    hideLoading() {
        document.getElementById('loadingOverlay').classList.add('hidden');
    }

    showError(message) {
        alert(message); // TODO: Replace with toast notification
    }

    showSuccess(message) {
        alert(message); // TODO: Replace with toast notification
    }

    applyMapSettings(settings) {
        if (settings.canvasWidth && settings.canvasHeight) {
            // Apply canvas size if needed
        }
        if (settings.initialZoom) {
            this.currentZoom = settings.initialZoom;
        }
    }

    deletePolygon(obj) {
        if (confirm('האם למחוק את הפוליגון?')) {
            this.canvas.remove(obj);
            this.canvas.renderAll();
        }
    }
}

// Initialize on load
let cemeteryMap;
document.addEventListener('DOMContentLoaded', () => {
    cemeteryMap = new CemeteryMap(window.MAP_CONFIG);
});

// Context menu actions
document.getElementById('contextMenu')?.addEventListener('click', (e) => {
    const action = e.target.closest('li')?.dataset.action;
    const unicId = document.getElementById('contextMenu').dataset.targetId;

    if (!action) return;

    switch (action) {
        case 'viewCard':
            // Open entity card
            const entity = cemeteryMap.entities.find(e => e.unicId === unicId);
            if (entity) {
                window.open(`../index.php?type=${cemeteryMap.selectedEntity?.type}&id=${unicId}`, '_blank');
            }
            break;

        case 'zoomTo':
            cemeteryMap.focusOnEntity(unicId);
            break;

        case 'drillDown':
            const type = cemeteryMap.selectedEntity?.type;
            const childType = cemeteryMap.config.entityConfig[type]?.children;
            if (childType) {
                window.location.href = `index.php?type=${type}&id=${unicId}&mode=${cemeteryMap.mode}`;
            }
            break;

        case 'editPolygon':
            // Enable point editing for this polygon
            if (window.polygonEditor) {
                window.polygonEditor.editPolygon(unicId);
            }
            break;

        case 'deletePolygon':
            const obj = cemeteryMap.canvas.getObjects().find(o => o.entityData?.unicId === unicId);
            if (obj) {
                cemeteryMap.deletePolygon(obj);
            }
            break;
    }

    cemeteryMap.hideContextMenu();
});
