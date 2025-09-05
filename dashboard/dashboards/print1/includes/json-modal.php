<!-- JSON Input Section (Hidden by default) -->
<div class="section" id="jsonSection" style="grid-column: 1 / -1; display: none;">
    <h2 class="section-title">📋 קלט JSON</h2>
    
    <div class="form-group">
        <label for="jsonInput">הדבק JSON:</label>
        <textarea id="jsonInput" placeholder='{
    "filename": "https://example.com/file.pdf",
    "orientation": "P",
    "method": "mpdf",
    "language": "he",
    "values": [
        {
            "text": "שלום עולם",
            "x": 100,
            "y": 200,
            "fontSize": 16,
            "color": "#FF0000"
        }
    ]
}' style="min-height: 250px; font-family: 'Courier New', monospace;"></textarea>
    </div>

    <div class="button-group">
        <button class="btn btn-primary" onclick="processJson()">
            📄 עבד JSON
        </button>
        <button class="btn btn-secondary" onclick="hideJsonInput()">
            ❌ סגור
        </button>
    </div>
</div>