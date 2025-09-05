/**
 * Canvas Preview System - Fixed Coordinate System
 * Ensures 1:1 mapping between canvas display and mPDF output
 */

class PDFCanvasPreview {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.textOverlay = null;
        this.canvasWrapper = null;
        
        this.pdfDoc = null;
        this.pageNum = 1;
        this.scale = 1.0;
        
        // PDF dimensions in points (1/72 inch)
        this.pdfWidth = 0;
        this.pdfHeight = 0;
        
        // Conversion factors
        this.POINTS_TO_MM = 0.352778; // 1 point = 0.352778 mm
        this.PIXELS_TO_MM = 0.264583; // at 96 DPI (standard web)
        
        // Store current PDF URL
        this.currentPdfUrl = null;
    }
    
    init() {
        // Create wrapper structure if doesn't exist
        const container = document.getElementById('canvasContainer');
        if (!container) return;
        
        // Update HTML structure
        container.innerHTML = `
            <div class="canvas-wrapper" style="
                width: 100%;
                max-width: 900px;
                height: 600px;
                margin: 0 auto;
                border: 2px solid #e1e8ed;
                border-radius: 8px;
                overflow: auto;
                background: #f5f5f5;
                position: relative;
            ">
                <div class="canvas-holder" id="canvasHolder" style="
                    position: relative;
                    display: inline-block;
                    background: white;
                    margin: 20px;
                ">
                    <canvas id="pdfCanvas"></canvas>
                    <div id="textOverlay" class="text-overlay" style="
                        position: absolute;
                        top: 0;
                        left: 0;
                        pointer-events: none;
                    "></div>
                </div>
            </div>
        `;
        
        this.canvas = document.getElementById('pdfCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.textOverlay = document.getElementById('textOverlay');
        this.canvasWrapper = container.querySelector('.canvas-wrapper');
        this.canvasHolder = document.getElementById('canvasHolder');
        
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
            showStatus('נא להזין כתובת PDF', 'error');
            return;
        }
        
        this.currentPdfUrl = url;
        
        try {
            // Use proxy to avoid CORS
            const proxyUrl = 'api/pdf-proxy.php?url=' + encodeURIComponent(url);
            
            const loadingTask = pdfjsLib.getDocument(proxyUrl);
            this.pdfDoc = await loadingTask.promise;
            
            console.log('PDF loaded, pages:', this.pdfDoc.numPages);
            
            await this.renderPage();
            this.syncWithValues();
            
            showStatus('PDF נטען בהצלחה', 'success');
            debugLog(`PDF loaded: ${url}`, 'success');
            
        } catch (error) {
            console.error('Error loading PDF:', error);
            showStatus('שגיאה בטעינת PDF', 'error');
            debugLog(`PDF load error: ${error.message}`, 'error');
        }
    }
    
    async renderPage() {
        if (!this.pdfDoc) return;
        
        const page = await this.pdfDoc.getPage(this.pageNum);
        
        // Get original viewport (scale = 1.0 gives us PDF points)
        const originalViewport = page.getViewport({ scale: 1.0 });
        
        // Store PDF dimensions in points
        this.pdfWidth = originalViewport.width;
        this.pdfHeight = originalViewport.height;
        
        // Calculate scale to fit in wrapper
        const wrapperWidth = this.canvasWrapper.clientWidth - 40; // Account for margins
        const wrapperHeight = this.canvasWrapper.clientHeight - 40;
        
        const scaleX = wrapperWidth / this.pdfWidth;
        const scaleY = wrapperHeight / this.pdfHeight;
        this.scale = Math.min(scaleX, scaleY, 2.0); // Cap at 2x zoom
        
        // Get scaled viewport
        const viewport = page.getViewport({ scale: this.scale });
        
        // Set canvas dimensions
        this.canvas.width = viewport.width;
        this.canvas.height = viewport.height;
        
        // Set holder and overlay dimensions
        this.canvasHolder.style.width = viewport.width + 'px';
        this.canvasHolder.style.height = viewport.height + 'px';
        this.textOverlay.style.width = viewport.width + 'px';
        this.textOverlay.style.height = viewport.height + 'px';
        
        // Render PDF
        const renderContext = {
            canvasContext: this.ctx,
            viewport: viewport
        };
        
        await page.render(renderContext).promise;
        
        // Update debug info
        this.updateDebugInfo();
        
        // Re-render texts with new scale
        this.renderAllTexts();
    }
    
    // Convert coordinates from pixels to mm for mPDF
    pixelsToMm(pixels) {
        return pixels * this.PIXELS_TO_MM;
    }
    
    // Convert coordinates from mm to canvas pixels
    mmToCanvasPixels(mm) {
        // Convert mm to PDF points, then apply canvas scale
        const points = mm / this.POINTS_TO_MM;
        return points * this.scale;
    }
    
    // Add or update text on canvas
    addTextToCanvas(value, index) {
        // Remove existing element if updating
        const existingElement = document.getElementById(`canvas-text-${index}`);
        if (existingElement) {
            existingElement.remove();
        }
        
        // Convert stored pixel coordinates to canvas coordinates
        const x_canvas = this.mmToCanvasPixels(value.x * this.PIXELS_TO_MM);
        const y_canvas = this.mmToCanvasPixels(value.y * this.PIXELS_TO_MM);
        
        const textElement = document.createElement('div');
        textElement.className = 'draggable-text';
        textElement.id = `canvas-text-${index}`;
        textElement.textContent = value.text;
        textElement.style.position = 'absolute';
        textElement.style.left = x_canvas + 'px';
        textElement.style.top = y_canvas + 'px';
        textElement.style.fontSize = (value.fontSize * this.scale) + 'px';
        textElement.style.color = value.color;
        textElement.style.fontFamily = value.fontFamily || 'Arial';
        textElement.style.cursor = 'move';
        textElement.style.pointerEvents = 'all';
        textElement.style.userSelect = 'none';
        
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
        
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            
            const x = e.pageX - startX;
            const y = e.pageY - startY;
            
            // Constrain to canvas bounds
            const maxX = this.canvas.width - element.offsetWidth;
            const maxY = this.canvas.height - element.offsetHeight;
            
            element.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
            element.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
        });
        
        document.addEventListener('mouseup', (e) => {
            if (!isDragging) return;
            
            isDragging = false;
            element.classList.remove('dragging');
            
            // Update stored position
            const x_canvas = parseFloat(element.style.left);
            const y_canvas = parseFloat(element.style.top);
            
            // Convert canvas pixels to mm, then to storage pixels
            const x_mm = (x_canvas / this.scale) * this.POINTS_TO_MM;
            const y_mm = (y_canvas / this.scale) * this.POINTS_TO_MM;
            
            // Store as pixels (for compatibility with existing system)
            if (values[index]) {
                values[index].x = Math.round(x_mm / this.PIXELS_TO_MM);
                values[index].y = Math.round(y_mm / this.PIXELS_TO_MM);
                
                // Update list display
                updateValuesList();
                saveState();
                
                debugLog(`Text moved: "${values[index].text}" to (${values[index].x}px, ${values[index].y}px) = (${x_mm.toFixed(1)}mm, ${y_mm.toFixed(1)}mm)`, 'info');
            }
        });
        
        // Double-click to edit
        element.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            const newText = prompt('ערוך טקסט:', element.textContent);
            if (newText && values[index]) {
                values[index].text = newText;
                element.textContent = newText;
                updateValuesList();
                saveState();
            }
        });
    }
    
    renderAllTexts() {
        if (!this.textOverlay) return;
        
        // Clear overlay
        this.textOverlay.innerHTML = '';
        
        // Add all values
        values.forEach((value, index) => {
            this.addTextToCanvas(value, index);
        });
    }
    
    syncWithValues() {
        // Sync canvas with current values array
        this.renderAllTexts();
    }
    
    updateDebugInfo() {
        const pdfSizeMm = {
            width: this.pdfWidth * this.POINTS_TO_MM,
            height: this.pdfHeight * this.POINTS_TO_MM
        };
        
        debugLog(`PDF Dimensions: ${this.pdfWidth.toFixed(0)}×${this.pdfHeight.toFixed(0)} points = ${pdfSizeMm.width.toFixed(1)}×${pdfSizeMm.height.toFixed(1)} mm`, 'info');
        debugLog(`Canvas Scale: ${this.scale.toFixed(2)}x`, 'info');
        debugLog(`Canvas Size: ${this.canvas.width}×${this.canvas.height} pixels`, 'info');
        
        // Update zoom level display
        const zoomSpan = document.querySelector('.zoom-level');
        if (zoomSpan) {
            zoomSpan.textContent = Math.round(this.scale * 100) + '%';
        }
    }
    
    // Zoom functions
    zoomIn() {
        this.scale *= 1.2;
        if (this.pdfDoc) this.renderPage();
    }
    
    zoomOut() {
        this.scale *= 0.8;
        if (this.pdfDoc) this.renderPage();
    }
    
    resetZoom() {
        this.scale = 1.0;
        if (this.pdfDoc) this.renderPage();
    }
}

// Create global instance
let pdfPreview = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    pdfPreview = new PDFCanvasPreview();
    pdfPreview.init();
});

// Global functions for UI buttons
window.loadPdfPreview = function() {
    if (!pdfPreview) {
        pdfPreview = new PDFCanvasPreview();
        pdfPreview.init();
    }
    
    const pdfUrl = document.getElementById('pdfUrl').value;
    if (pdfUrl) {
        pdfPreview.loadPdf(pdfUrl);
    }
};

window.zoomIn = function() {
    if (pdfPreview) pdfPreview.zoomIn();
};

window.zoomOut = function() {
    if (pdfPreview) pdfPreview.zoomOut();
};

window.resetZoom = function() {
    if (pdfPreview) pdfPreview.resetZoom();
};

// Override addValue to update canvas
const originalAddValue = window.addValue;
window.addValue = function() {
    originalAddValue();
    if (pdfPreview) pdfPreview.syncWithValues();
};

// Override removeValue
const originalRemoveValue = window.removeValue;
window.removeValue = function(index) {
    originalRemoveValue(index);
    if (pdfPreview) pdfPreview.syncWithValues();
};

// Override clearAll
const originalClearAll = window.clearAll;
window.clearAll = function() {
    originalClearAll();
    if (pdfPreview) pdfPreview.syncWithValues();
};