<div class="section preview-section" style="grid-column: 1 / -1;">
    <h2 class="section-title">👁️ תצוגה מקדימה</h2>
    
    <div class="preview-controls">
        <button class="btn btn-info btn-small" onclick="loadPdfPreview()">
            📄 טען PDF
        </button>
        <button class="btn btn-secondary btn-small" onclick="zoomIn()">
            🔍+ הגדל
        </button>
        <button class="btn btn-secondary btn-small" onclick="zoomOut()">
            🔍- הקטן
        </button>
        <button class="btn btn-secondary btn-small" onclick="resetZoom()">
            🔄 איפוס
        </button>
        <span class="zoom-level">100%</span>
    </div>
    
    <div class="canvas-container" id="canvasContainer">
        <canvas id="pdfCanvas"></canvas>
        <div id="textOverlay" class="text-overlay"></div>
    </div>
    
    <div class="preview-info">
        <small>גרור טקסטים כדי לשנות מיקום | לחץ על טקסט לעריכה</small>
    </div>
</div>