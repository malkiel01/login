/**
 * Canvas Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/canvas-manager.js
 */

class CanvasManager {
    constructor(canvasId) {
        this.canvasId = canvasId;
        this.canvas = null;
        this.currentTool = 'select';
        this.currentZoom = 100;
        this.gridVisible = false;
        this.guidesVisible = false;
        this.currentDocument = null;
        this.selectedObjects = [];
        this.clipboard = null;
        this.isDrawing = false;
        
        // Initialize canvas
        this.initCanvas();
        
        // Bind events
        this.bindEvents();
    }

    initCanvas() {
        // Create Fabric canvas
        this.canvas = new fabric.Canvas(this.canvasId, {
            backgroundColor: '#ffffff',
            preserveObjectStacking: true,
            selection: true,
            selectionColor: 'rgba(102, 126, 234, 0.3)',
            selectionBorderColor: '#667eea',
            selectionLineWidth: 2
        });

        // Set default size
        this.setCanvasSize(595, 842); // A4 size in points

        // Add grid (initially hidden)
        this.createGrid();

        // Setup zoom
        this.setupZoom();

        // Setup keyboard shortcuts
        this.setupKeyboardShortcuts();
    }

    setCanvasSize(width, height) {
        this.canvas.setWidth(width);
        this.canvas.setHeight(height);
        this.canvas.renderAll();
    }

    createGrid() {
        const gridSize = PDFEditorConfig.canvas.gridSize;
        const width = this.canvas.width;
        const height = this.canvas.height;

        // Create grid group
        const gridLines = [];

        // Vertical lines
        for (let i = 0; i <= width; i += gridSize) {
            gridLines.push(new fabric.Line([i, 0, i, height], {
                stroke: '#e0e0e0',
                strokeWidth: 0.5,
                selectable: false,
                evented: false,
                excludeFromExport: true
            }));
        }

        // Horizontal lines
        for (let i = 0; i <= height; i += gridSize) {
            gridLines.push(new fabric.Line([0, i, width, i], {
                stroke: '#e0e0e0',
                strokeWidth: 0.5,
                selectable: false,
                evented: false,
                excludeFromExport: true
            }));
        }

        this.gridGroup = new fabric.Group(gridLines, {
            selectable: false,
            evented: false,
            excludeFromExport: true,
            visible: false,
            id: 'grid'
        });

        this.canvas.add(this.gridGroup);
        this.canvas.sendToBack(this.gridGroup);
    }

    toggleGrid() {
        this.gridVisible = !this.gridVisible;
        if (this.gridGroup) {
            this.gridGroup.visible = this.gridVisible;
            this.canvas.renderAll();
        }
    }

    setupZoom() {
        const self = this;
        
        // Mouse wheel zoom
        this.canvas.on('mouse:wheel', function(opt) {
            const delta = opt.e.deltaY;
            let zoom = self.canvas.getZoom();
            zoom *= 0.999 ** delta;
            
            // Limit zoom
            if (zoom > 5) zoom = 5;
            if (zoom < 0.1) zoom = 0.1;
            
            self.canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
            self.currentZoom = Math.round(zoom * 100);
            self.updateZoomDisplay();
            
            opt.e.preventDefault();
            opt.e.stopPropagation();
        });
    }

    setZoom(zoomLevel) {
        const zoom = zoomLevel / 100;
        this.canvas.setZoom(zoom);
        this.currentZoom = zoomLevel;
        this.updateZoomDisplay();
        this.canvas.renderAll();
    }

    zoomIn() {
        const newZoom = Math.min(this.currentZoom + 10, 500);
        this.setZoom(newZoom);
    }

    zoomOut() {
        const newZoom = Math.max(this.currentZoom - 10, 10);
        this.setZoom(newZoom);
    }

    zoomToFit() {
        const canvasWidth = this.canvas.getWidth();
        const canvasHeight = this.canvas.getHeight();
        const containerWidth = this.canvas.wrapperEl.offsetWidth;
        const containerHeight = this.canvas.wrapperEl.offsetHeight;
        
        const scaleX = (containerWidth - 100) / canvasWidth;
        const scaleY = (containerHeight - 100) / canvasHeight;
        const zoom = Math.min(scaleX, scaleY);
        
        this.canvas.setZoom(zoom);
        this.currentZoom = Math.round(zoom * 100);
        this.updateZoomDisplay();
        
        // Center the canvas
        this.canvas.absolutePan(new fabric.Point(0, 0));
        this.canvas.renderAll();
    }

    updateZoomDisplay() {
        const zoomDisplay = document.getElementById('zoomDisplay');
        if (zoomDisplay) {
            zoomDisplay.textContent = `${this.currentZoom}%`;
        }
    }

    setupKeyboardShortcuts() {
        const self = this;
        
        document.addEventListener('keydown', function(e) {
            // Check if user is typing in an input field
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            const key = e.key.toLowerCase();
            const ctrl = e.ctrlKey || e.metaKey;
            const shift = e.shiftKey;

            // Ctrl+Z - Undo
            if (ctrl && key === 'z' && !shift) {
                e.preventDefault();
                self.undo();
            }
            // Ctrl+Y or Ctrl+Shift+Z - Redo
            else if ((ctrl && key === 'y') || (ctrl && shift && key === 'z')) {
                e.preventDefault();
                self.redo();
            }
            // Ctrl+C - Copy
            else if (ctrl && key === 'c') {
                e.preventDefault();
                self.copy();
            }
            // Ctrl+V - Paste
            else if (ctrl && key === 'v') {
                e.preventDefault();
                self.paste();
            }
            // Ctrl+X - Cut
            else if (ctrl && key === 'x') {
                e.preventDefault();
                self.cut();
            }
            // Ctrl+A - Select All
            else if (ctrl && key === 'a') {
                e.preventDefault();
                self.selectAll();
            }
            // Delete - Delete selected
            else if (key === 'delete' || key === 'backspace') {
                e.preventDefault();
                self.deleteSelected();
            }
            // Ctrl+D - Duplicate
            else if (ctrl && key === 'd') {
                e.preventDefault();
                self.duplicateSelected();
            }
            // G - Toggle Grid
            else if (!ctrl && key === 'g') {
                e.preventDefault();
                self.toggleGrid();
            }
            // T - Text tool
            else if (!ctrl && key === 't') {
                e.preventDefault();
                self.setTool('text');
            }
            // V - Select tool
            else if (!ctrl && key === 'v') {
                e.preventDefault();
                self.setTool('select');
            }
            // I - Image tool
            else if (!ctrl && key === 'i') {
                e.preventDefault();
                self.setTool('image');
            }
            // Escape - Deselect
            else if (key === 'escape') {
                e.preventDefault();
                self.deselect();
            }
        });
    }

    setTool(tool) {
        this.currentTool = tool;
        
        // Update UI
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`[data-tool="${tool}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Configure canvas based on tool
        switch (tool) {
            case 'select':
                this.canvas.isDrawingMode = false;
                this.canvas.selection = true;
                this.canvas.defaultCursor = 'default';
                break;
            case 'text':
                this.canvas.isDrawingMode = false;
                this.canvas.selection = false;
                this.canvas.defaultCursor = 'text';
                break;
            case 'image':
                this.canvas.isDrawingMode = false;
                this.canvas.selection = false;
                this.canvas.defaultCursor = 'crosshair';
                break;
            case 'draw':
                this.canvas.isDrawingMode = true;
                this.canvas.freeDrawingBrush.width = 2;
                this.canvas.freeDrawingBrush.color = '#000000';
                break;
            case 'shape':
                this.canvas.isDrawingMode = false;
                this.canvas.selection = false;
                this.canvas.defaultCursor = 'crosshair';
                break;
        }
    }

    addText(text = 'טקסט חדש', options = {}) {
        const isRTL = languageManager.isRTL();
        
        const defaults = {
            left: 100,
            top: 100,
            fontFamily: isRTL ? 'Rubik' : 'Arial',
            fontSize: 20,
            fill: '#000000',
            textAlign: isRTL ? 'right' : 'left',
            direction: isRTL ? 'rtl' : 'ltr',
            editable: true,
            selectable: true
        };

        const textObj = new fabric.IText(text, { ...defaults, ...options });
        
        this.canvas.add(textObj);
        this.canvas.setActiveObject(textObj);
        this.canvas.renderAll();
        
        // Enter edit mode
        textObj.enterEditing();
        textObj.selectAll();
        
        // Fire event
        this.fireEvent('object:added', { object: textObj });
        
        return textObj;
    }

    addImage(url, options = {}) {
        fabric.Image.fromURL(url, (img) => {
            const defaults = {
                left: 100,
                top: 100,
                scaleX: 1,
                scaleY: 1,
                selectable: true
            };

            // Apply options
            Object.assign(img, { ...defaults, ...options });

            // Scale if too large
            const maxWidth = this.canvas.width * 0.8;
            const maxHeight = this.canvas.height * 0.8;
            
            if (img.width > maxWidth || img.height > maxHeight) {
                const scale = Math.min(maxWidth / img.width, maxHeight / img.height);
                img.scaleX = scale;
                img.scaleY = scale;
            }

            this.canvas.add(img);
            this.canvas.setActiveObject(img);
            this.canvas.renderAll();
            
            // Fire event
            this.fireEvent('object:added', { object: img });
        });
    }

    loadPDF(pdfData) {
        // Check if PDF.js is available
        if (typeof pdfjsLib === 'undefined') {
            console.error('PDF.js library is not loaded');
            if (window.notificationManager) {
                window.notificationManager.error('ספריית PDF לא זמינה');
            }
            return;
        }
        
        // This would integrate with PDF.js to load PDF
        // For now, we'll use it as a background image
        pdfjsLib.getDocument({ data: pdfData }).promise.then((pdf) => {
            pdf.getPage(1).then((page) => {
                const viewport = page.getViewport({ scale: 1 });
                const tempCanvas = document.createElement('canvas');
                const context = tempCanvas.getContext('2d');
                
                tempCanvas.width = viewport.width;
                tempCanvas.height = viewport.height;
                
                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                
                page.render(renderContext).promise.then(() => {
                    const imgData = tempCanvas.toDataURL('image/png');
                    
                    fabric.Image.fromURL(imgData, (img) => {
                        // Set canvas size to match PDF
                        this.setCanvasSize(viewport.width, viewport.height);
                        
                        // Add as background
                        this.canvas.setBackgroundImage(img, () => {
                            this.canvas.renderAll();
                        });
                        
                        // Store PDF data
                        this.currentDocument = {
                            type: 'pdf',
                            data: pdfData,
                            width: viewport.width,
                            height: viewport.height
                        };
                    });
                });
            });
        }).catch(error => {
            console.error('Failed to load PDF:', error);
            if (window.notificationManager) {
                window.notificationManager.error('שגיאה בטעינת PDF');
            }
        });
    }

    loadImage(imageUrl) {
        fabric.Image.fromURL(imageUrl, (img) => {
            // Set canvas size to match image
            this.setCanvasSize(img.width, img.height);
            
            // Set as background
            this.canvas.setBackgroundImage(img, () => {
                this.canvas.renderAll();
            });
            
            // Store document data
            this.currentDocument = {
                type: 'image',
                url: imageUrl,
                width: img.width,
                height: img.height
            };
        });
    }

    copy() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject) return;

        activeObject.clone((cloned) => {
            this.clipboard = cloned;
        });
    }

    paste() {
        if (!this.clipboard) return;

        this.clipboard.clone((clonedObj) => {
            this.canvas.discardActiveObject();
            
            clonedObj.set({
                left: clonedObj.left + 10,
                top: clonedObj.top + 10,
                evented: true
            });
            
            if (clonedObj.type === 'activeSelection') {
                clonedObj.canvas = this.canvas;
                clonedObj.forEachObject((obj) => {
                    this.canvas.add(obj);
                });
                clonedObj.setCoords();
            } else {
                this.canvas.add(clonedObj);
            }
            
            this.clipboard.top += 10;
            this.clipboard.left += 10;
            this.canvas.setActiveObject(clonedObj);
            this.canvas.requestRenderAll();
        });
    }

    cut() {
        this.copy();
        this.deleteSelected();
    }

    deleteSelected() {
        const activeObjects = this.canvas.getActiveObjects();
        if (activeObjects.length === 0) return;

        activeObjects.forEach((obj) => {
            this.canvas.remove(obj);
        });
        
        this.canvas.discardActiveObject();
        this.canvas.renderAll();
        
        // Fire event
        this.fireEvent('object:deleted', { objects: activeObjects });
    }

    duplicateSelected() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject) return;

        activeObject.clone((cloned) => {
            cloned.set({
                left: cloned.left + 10,
                top: cloned.top + 10
            });
            
            if (cloned.type === 'activeSelection') {
                cloned.canvas = this.canvas;
                cloned.forEachObject((obj) => {
                    this.canvas.add(obj);
                });
                cloned.setCoords();
            } else {
                this.canvas.add(cloned);
            }
            
            this.canvas.setActiveObject(cloned);
            this.canvas.renderAll();
        });
    }

    selectAll() {
        this.canvas.discardActiveObject();
        const allObjects = this.canvas.getObjects().filter(obj => 
            obj.selectable && obj.id !== 'grid'
        );
        
        if (allObjects.length > 0) {
            const selection = new fabric.ActiveSelection(allObjects, {
                canvas: this.canvas
            });
            this.canvas.setActiveObject(selection);
            this.canvas.renderAll();
        }
    }

    deselect() {
        this.canvas.discardActiveObject();
        this.canvas.renderAll();
    }

    undo() {
        // This will be handled by UndoRedoManager
        if (window.undoRedoManager) {
            window.undoRedoManager.undo();
        }
    }

    redo() {
        // This will be handled by UndoRedoManager
        if (window.undoRedoManager) {
            window.undoRedoManager.redo();
        }
    }

    getCanvasJSON() {
        return this.canvas.toJSON([
            'selectable',
            'evented',
            'id',
            'direction',
            'textAlign'
        ]);
    }

    loadFromJSON(json) {
        this.canvas.loadFromJSON(json, () => {
            this.canvas.renderAll();
        });
    }

    exportAsPNG() {
        return this.canvas.toDataURL({
            format: 'png',
            quality: 1,
            multiplier: 2 // For retina displays
        });
    }

    exportAsJPEG() {
        return this.canvas.toDataURL({
            format: 'jpeg',
            quality: 0.95
        });
    }

    exportAsPDF() {
        // This will be handled by the API
        const canvasData = this.getCanvasJSON();
        return canvasData;
    }

    clear() {
        this.canvas.clear();
        this.canvas.backgroundColor = '#ffffff';
        this.createGrid();
        this.canvas.renderAll();
    }

    bindEvents() {
        const self = this;

        // Canvas click events based on current tool
        this.canvas.on('mouse:down', function(options) {
            if (self.currentTool === 'text' && !options.target) {
                const pointer = self.canvas.getPointer(options.e);
                self.addText('טקסט חדש', {
                    left: pointer.x,
                    top: pointer.y
                });
                self.setTool('select');
            }
        });

        // Object selection
        this.canvas.on('selection:created', function(e) {
            self.updatePropertiesPanel(e.selected);
        });

        this.canvas.on('selection:updated', function(e) {
            self.updatePropertiesPanel(e.selected);
        });

        this.canvas.on('selection:cleared', function() {
            self.clearPropertiesPanel();
        });

        // Object modification
        this.canvas.on('object:modified', function(e) {
            self.fireEvent('object:modified', { object: e.target });
        });
    }

    updatePropertiesPanel(objects) {
        // This will be implemented to update the properties panel
        // based on selected objects
        if (window.propertiesManager) {
            window.propertiesManager.update(objects);
        }
    }

    clearPropertiesPanel() {
        if (window.propertiesManager) {
            window.propertiesManager.clear();
        }
    }

    fireEvent(eventName, data) {
        window.dispatchEvent(new CustomEvent(eventName, { detail: data }));
    }

    destroy() {
        this.canvas.dispose();
    }
}

// Export for global use
window.CanvasManager = CanvasManager;