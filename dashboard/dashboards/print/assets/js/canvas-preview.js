/**
 * Canvas Preview System - Fixed Scale
 * Version 4 - Proper sizing
 */

// Clear any existing instance
if (window.pdfPreview) {
    window.pdfPreview = null;
}

class PDFCanvasPreview {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.textOverlay = null;
        this.canvasContainer = null;
        
        this.pdfDoc = null;
        this.pageNum = 1;
        this.scale = 1.5; // Start with better default scale
        
        // PDF dimensions in points (1/72 inch)
        this.pdfWidth = 0;
        this.pdfHeight = 0;
        
        // Conversion factors - CRITICAL FOR ACCURACY
        this.POINTS_TO_MM = 0.352778; // 1 point = 0.352778 mm
        this.PIXELS_TO_MM = 0.264583; // at 96 DPI
        
        this.currentPdfUrl = null;
    }
    
    init() {
        // Get container
        this.canvasContainer = document.getElementById('canvasContainer');
        if (!this.canvasContainer) {
            console.error('Canvas container not found');
            return;
        }
        
        // Check if canvas already exists
        this.canvas = document.getElementById('pdfCanvas');
        this.textOverlay = document.getElementById('textOverlay');
        
        if (!this.canvas) {
            // Create canvas structure
            this.canvasContainer.innerHTML = `
                <canvas id="pdfCanvas" style="
                    display: block;
                    margin: 0 auto;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                "></canvas>
                <div id="textOverlay" class="text-overlay" style="
                    position: absolute;
                    top: 0;
                    left: 0;
                    pointer-events: none;
                "></div>
            `;
            
            this.canvas = document.getElementById('pdfCanvas');
            this.textOverlay = document.getElementById('textOverlay');
        }
        
        // Make container relative for overlay positioning
        this.canvasContainer.style.position = 'relative';
        
        this.ctx = this.canvas.getContext('2d');
        
        // Load PDF.js if not loaded
        if (!window.pdfjsLib) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
            script.onload = () => {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                console.log('PDF.js loaded');
            };
            document.head.appendChild(script);
        }
    }
    
    async loadPdf(url) {
        if (!url) {
            if (typeof showStatus !== 'undefined') {
                showStatus('נא להזין כתובת PDF', 'error');
            }
            return;
        }
        
        this.currentPdfUrl = url;
        
        try {
            // Use proxy to avoid CORS
            const proxyUrl = 'api/pdf-proxy.php?url=' + encodeURIComponent(url);
            
            const loadingTask = pdfjsLib.getDocument(proxyUrl);
            this.pdfDoc = await loadingTask.promise;
            
            console.log('PDF loaded, pages:', this.pdfDoc.numPages);
            
            // Set initial scale based on container
            this.calculateOptimalScale();
            
            await this.renderPage();
            this.syncWithValues();
            
            if (typeof showStatus !== 'undefined') {
                showStatus('PDF נטען בהצלחה', 'success');
            }
            
        } catch (error) {
            console.error('Error loading PDF:', error);
            if (typeof showStatus !== 'undefined') {
                showStatus('שגיאה בטעינת PDF', 'error');
            }
        }
    }
    
    calculateOptimalScale() {
        if (!this.pdfDoc) return;
        
        // Get container dimensions
        const containerWidth = this.canvasContainer.clientWidth || 800;
        const containerHeight = window.innerHeight * 0.6; // 60% of viewport height
        
        // We'll calculate this properly after getting page dimensions
        // For now, set a reasonable default
        this.scale = 1.5;
    }
    
    async renderPage() {
        if (!this.pdfDoc) return;
        
        const page = await this.pdfDoc.getPage(this.pageNum);
        
        // Get original viewport
        const originalViewport = page.getViewport({ scale: 1.0 });
        
        // Store PDF dimensions in points
        this.pdfWidth = originalViewport.width;
        this.pdfHeight = originalViewport.height;
        
        // Calculate optimal scale to fit container
        const containerWidth = this.canvasContainer.clientWidth || 800;
        const containerHeight = Math.min(window.innerHeight * 0.7, 800); // Max 70% of viewport or 800px
        
        const scaleX = (containerWidth - 20) / this.pdfWidth; // Leave some margin
        const scaleY = (containerHeight - 20) / this.pdfHeight;
        
        // Use the smaller scale to ensure PDF fits, but not too small
        this.scale = Math.max(0.5, Math.min(scaleX, scaleY, 2.0));
        
        console.log(`Container: ${containerWidth}x${containerHeight}, PDF: ${this.pdfWidth}x${this.pdfHeight}, Scale: ${this.scale}`);
        
        // Get scaled viewport
        const viewport = page.getViewport({ scale: this.scale });
        
        // Set canvas dimensions
        this.canvas.width = viewport.width;
        this.canvas.height = viewport.height;
        
        // Position overlay to match canvas
        const canvasRect = this.canvas.getBoundingClientRect();
        const containerRect = this.canvasContainer.getBoundingClientRect();
        
        this.textOverlay.style.width = viewport.width + 'px';
        this.textOverlay.style.height = viewport.height + 'px';
        this.textOverlay.style.position = 'absolute';
        
        // Center the overlay over the canvas
        const offsetLeft = (containerRect.width - viewport.width) / 2;
        this.textOverlay.style.left = offsetLeft + 'px';
        this.textOverlay.style.top = '0px';
        
        // Render PDF
        const renderContext = {
            canvasContext: this.ctx,
            viewport: viewport
        };
        
        await page.render(renderContext).promise;
        
        console.log(`Canvas rendered: ${viewport.width}x${viewport.height}px at scale ${this.scale}`);
        
        // Update debug info
        this.updateDebugInfo();
        
        // Re-render texts
        this.renderAllTexts();
    }
    
    // Convert pixels to canvas coordinates
    pixelsToCanvas(x_px, y_px) {
        // Input is in pixels (as stored in values array)
        // Convert to mm first
        const x_mm = x_px * this.PIXELS_TO_MM;
        const y_mm = y_px * this.PIXELS_TO_MM;
        
        // Convert mm to points
        const x_points = x_mm / this.POINTS_TO_MM;
        const y_points = y_mm / this.POINTS_TO_MM;
        
        // Apply canvas scale
        const x_canvas = x_points * this.scale;
        const y_canvas = y_points * this.scale;
        
        return { x: x_canvas, y: y_canvas };
    }
    
    // Convert canvas coordinates back to pixels
    canvasToPixels(x_canvas, y_canvas) {
        // Remove scale to get points
        const x_points = x_canvas / this.scale;
        const y_points = y_canvas / this.scale;
        
        // Convert points to mm
        const x_mm = x_points * this.POINTS_TO_MM;
        const y_mm = y_points * this.POINTS_TO_MM;
        
        // Convert mm to pixels
        const x_px = x_mm / this.PIXELS_TO_MM;
        const y_px = y_mm / this.PIXELS_TO_MM;
        
        return { x: Math.round(x_px), y: Math.round(y_px) };
    }
    
    addTextToCanvas(value, index) {
        // Remove existing element
        const existingElement = document.getElementById(`canvas-text-${index}`);
        if (existingElement) {
            existingElement.remove();
        }
        
        // Convert stored pixels to canvas coordinates
        const canvasCoords = this.pixelsToCanvas(value.x, value.y);
        
        const textElement = document.createElement('div');
        textElement.className = 'draggable-text';
        textElement.id = `canvas-text-${index}`;
        textElement.textContent = value.text;
        textElement.style.position = 'absolute';
        textElement.style.left = canvasCoords.x + 'px';
        textElement.style.top = canvasCoords.y + 'px';
        textElement.style.fontSize = (value.fontSize * this.scale) + 'px';
        textElement.style.color = value.color;
        textElement.style.fontFamily = value.fontFamily || 'Arial';
        textElement.style.cursor = 'move';
        textElement.style.pointerEvents = 'all';
        textElement.style.userSelect = 'none';
        textElement.style.whiteSpace = 'nowrap';
        
        this.makeDraggable(textElement, index);
        this.textOverlay.appendChild(textElement);
    }
    
    makeDraggable(element, index) {
        let isDragging = false;
        let startX, startY;
        
        element.addEventListener('mousedown', (e) => {
            isDragging = true;
            element.classList.add('dragging');
            startX = e.pageX - element.offsetLeft;
            startY = e.pageY - element.offsetTop;
            e.preventDefault();
        });
        
        const handleMouseMove = (e) => {
            if (!isDragging) return;
            
            const x = e.pageX - startX;
            const y = e.pageY - startY;
            
            // Constrain to canvas bounds
            const maxX = this.canvas.width - element.offsetWidth;
            const maxY = this.canvas.height - element.offsetHeight;
            
            element.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
            element.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
        };
        
        const handleMouseUp = (e) => {
            if (!isDragging) return;
            
            isDragging = false;
            element.classList.remove('dragging');
            
            // Get new canvas position
            const x_canvas = parseFloat(element.style.left);
            const y_canvas = parseFloat(element.style.top);
            
            // Convert back to pixels for storage
            const pixelCoords = this.canvasToPixels(x_canvas, y_canvas);
            
            if (typeof values !== 'undefined' && values[index]) {
                values[index].x = pixelCoords.x;
                values[index].y = pixelCoords.y;
                
                // Update UI
                if (typeof updateValuesList !== 'undefined') {
                    updateValuesList();
                }
                if (typeof saveState !== 'undefined') {
                    saveState();
                }
                
                console.log(`Text moved to: ${pixelCoords.x}px, ${pixelCoords.y}px`);
            }
            
            // Clean up listeners
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
        };
        
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
        
        // Double-click to edit
        element.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            const newText = prompt('ערוך טקסט:', element.textContent);
            if (newText && typeof values !== 'undefined' && values[index]) {
                values[index].text = newText;
                element.textContent = newText;
                if (typeof updateValuesList !== 'undefined') {
                    updateValuesList();
                }
                if (typeof saveState !== 'undefined') {
                    saveState();
                }
            }
        });
    }
    
    renderAllTexts() {
        if (!this.textOverlay) return;
        
        // Clear overlay
        this.textOverlay.innerHTML = '';
        
        // Add all values
        if (typeof values !== 'undefined' && values) {
            values.forEach((value, index) => {
                this.addTextToCanvas(value, index);
            });
        }
    }
    
    syncWithValues() {
        this.renderAllTexts();
    }
    
    updateDebugInfo() {
        const pdfSizeMm = {
            width: this.pdfWidth * this.POINTS_TO_MM,
            height: this.pdfHeight * this.POINTS_TO_MM
        };
        
        const info = `PDF: ${this.pdfWidth.toFixed(0)}×${this.pdfHeight.toFixed(0)}pt (${pdfSizeMm.width.toFixed(1)}×${pdfSizeMm.height.toFixed(1)}mm) | Scale: ${this.scale.toFixed(2)}x | Canvas: ${this.canvas.width}×${this.canvas.height}px`;
        
        console.log(info);
        
        if (typeof debugLog !== 'undefined') {
            debugLog(info, 'info');
        }
        
        // Update zoom display
        const zoomSpan = document.querySelector('.zoom-level');
        if (zoomSpan) {
            zoomSpan.textContent = Math.round(this.scale * 100) + '%';
        }
    }
    
    // Zoom functions
    zoomIn() {
        this.scale = Math.min(this.scale * 1.2, 3.0);
        if (this.pdfDoc) this.renderPage();
    }
    
    zoomOut() {
        this.scale = Math.max(this.scale * 0.8, 0.3);
        if (this.pdfDoc) this.renderPage();
    }
    
    resetZoom() {
        this.calculateOptimalScale();
        if (this.pdfDoc) this.renderPage();
    }
}

// Initialize global instance
window.pdfPreview = new PDFCanvasPreview();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.pdfPreview.init();
});

// Global functions
window.loadPdfPreview = function() {
    const pdfUrl = document.getElementById('pdfUrl')?.value;
    if (pdfUrl && window.pdfPreview) {
        window.pdfPreview.loadPdf(pdfUrl);
    }
};

window.zoomIn = function() {
    if (window.pdfPreview) window.pdfPreview.zoomIn();
};

window.zoomOut = function() {
    if (window.pdfPreview) window.pdfPreview.zoomOut();
};

window.resetZoom = function() {
    if (window.pdfPreview) window.pdfPreview.resetZoom();
};

// Hook into existing functions - store originals once
if (!window._addValueOriginal) {
    window._addValueOriginal = window.addValue;
}
if (!window._removeValueOriginal) {
    window._removeValueOriginal = window.removeValue;  
}
if (!window._clearAllOriginal) {
    window._clearAllOriginal = window.clearAll;
}

// Override functions
window.addValue = function() {
    if (window._addValueOriginal) {
        window._addValueOriginal();
    }
    if (window.pdfPreview) {
        window.pdfPreview.syncWithValues();
    }
};

window.removeValue = function(index) {
    if (window._removeValueOriginal) {
        window._removeValueOriginal(index);
    }
    if (window.pdfPreview) {
        window.pdfPreview.syncWithValues();
    }
};

window.clearAll = function() {
    if (window._clearAllOriginal) {
        window._clearAllOriginal();
    }
    if (window.pdfPreview) {
        window.pdfPreview.syncWithValues();
    }
};