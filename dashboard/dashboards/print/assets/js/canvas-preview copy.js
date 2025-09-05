/**
 * Canvas Preview System v1
 */

let pdfDoc = null;
let pageNum = 1;
let pageRendering = false;
let pageNumPending = null;
let scale = 1.0;
let canvas = null;
let ctx = null;
let textOverlay = null;
let currentPdfUrl = null;

// Initialize canvas
function initCanvas() {
    canvas = document.getElementById('pdfCanvas');
    ctx = canvas.getContext('2d');
    textOverlay = document.getElementById('textOverlay');
    
    // Load PDF.js library
    if (!window.pdfjsLib) {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = () => {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            console.log('PDF.js loaded');
        };
        document.head.appendChild(script);
    }
}

// Load PDF preview
async function loadPdfPreview() {
    const pdfUrl = document.getElementById('pdfUrl').value;
    
    if (!pdfUrl) {
        showStatus('נא להזין כתובת PDF', 'error');
        return;
    }
    
    currentPdfUrl = pdfUrl;
    
    try {
        // Use proxy to avoid CORS issues
        const proxyUrl = 'api/pdf-proxy.php?url=' + encodeURIComponent(pdfUrl);
        
        const loadingTask = pdfjsLib.getDocument(proxyUrl);
        pdfDoc = await loadingTask.promise;
        
        console.log('PDF loaded, pages:', pdfDoc.numPages);
        
        // Render first page
        renderPage(pageNum);
        
        // After PDF loads, add existing text overlays
        updateCanvasTexts();
        
        showStatus('PDF נטען בהצלחה', 'success');
    } catch (error) {
        console.error('Error loading PDF:', error);
        showStatus('שגיאה בטעינת PDF', 'error');
    }
}

// Render PDF page
function renderPage(num) {
    pageRendering = true;
    
    pdfDoc.getPage(num).then(page => {
        const viewport = page.getViewport({ scale: scale });
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        // Update overlay size
        textOverlay.style.width = viewport.width + 'px';
        textOverlay.style.height = viewport.height + 'px';
        
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        
        const renderTask = page.render(renderContext);
        
        renderTask.promise.then(() => {
            pageRendering = false;
            if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
            
            // Update text positions after render
            updateCanvasTexts();
        });
    });
}

// Add text to canvas overlay
function addTextToCanvas(value, index) {
    const textElement = document.createElement('div');
    textElement.className = 'draggable-text';
    textElement.id = `canvas-text-${index}`;
    textElement.textContent = value.text;
    textElement.style.left = value.x + 'px';
    textElement.style.top = value.y + 'px';
    textElement.style.fontSize = value.fontSize + 'px';
    textElement.style.color = value.color;
    textElement.style.fontFamily = value.fontFamily || 'Arial';
    
    // Make draggable
    makeDraggable(textElement, index);
    
    textOverlay.appendChild(textElement);
}

// Make element draggable
function makeDraggable(element, index) {
    let isDragging = false;
    let startX, startY, initialX, initialY;
    
    element.addEventListener('mousedown', (e) => {
        isDragging = true;
        element.classList.add('dragging');
        
        startX = e.clientX;
        startY = e.clientY;
        
        const rect = element.getBoundingClientRect();
        const parentRect = textOverlay.getBoundingClientRect();
        initialX = rect.left - parentRect.left;
        initialY = rect.top - parentRect.top;
        
        e.preventDefault();
    });
    
    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        
        const newX = initialX + dx;
        const newY = initialY + dy;
        
        element.style.left = newX + 'px';
        element.style.top = newY + 'px';
    });
    
    document.addEventListener('mouseup', (e) => {
        if (!isDragging) return;
        
        isDragging = false;
        element.classList.remove('dragging');
        
        // Update value in array
        const newX = parseInt(element.style.left);
        const newY = parseInt(element.style.top);
        
        if (values[index]) {
            values[index].x = newX;
            values[index].y = newY;
            
            // Update list display
            updateValuesList();
            saveState();
            
            debugLog(`Text moved: ${values[index].text} to (${newX}, ${newY})`, 'info');
        }
    });
    
    // Double click to edit
    element.addEventListener('dblclick', (e) => {
        const newText = prompt('ערוך טקסט:', element.textContent);
        if (newText && values[index]) {
            values[index].text = newText;
            element.textContent = newText;
            updateValuesList();
            saveState();
        }
    });
}

// Update canvas texts
function updateCanvasTexts() {
    if (!textOverlay) return;
    
    // Clear existing
    textOverlay.innerHTML = '';
    
    // Add all values
    values.forEach((value, index) => {
        addTextToCanvas(value, index);
    });
}

// Zoom functions
function zoomIn() {
    scale *= 1.2;
    updateZoomLevel();
    if (pdfDoc) renderPage(pageNum);
}

function zoomOut() {
    scale *= 0.8;
    updateZoomLevel();
    if (pdfDoc) renderPage(pageNum);
}

function resetZoom() {
    scale = 1.0;
    updateZoomLevel();
    if (pdfDoc) renderPage(pageNum);
}

function updateZoomLevel() {
    const zoomSpan = document.querySelector('.zoom-level');
    if (zoomSpan) {
        zoomSpan.textContent = Math.round(scale * 100) + '%';
    }
}

// Hook into existing addValue function
const originalAddValue2 = window.addValue;
window.addValue = function() {
    originalAddValue2();
    updateCanvasTexts();
};

// Hook into removeValue
const originalRemoveValue = window.removeValue;
window.removeValue = function(index) {
    originalRemoveValue(index);
    updateCanvasTexts();
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    initCanvas();
});