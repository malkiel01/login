<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Text Printer - הדפסת טקסט על PDF</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }

        .section {
            background: #f7f9fc;
            border-radius: 12px;
            padding: 25px;
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-2px);
        }

        .section-title {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: 'Courier New', monospace;
        }

        .values-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }

        .value-item {
            background: #f0f4f8;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .value-item:hover {
            background: #e2e8f0;
        }

        .value-item-text {
            flex: 1;
            margin-left: 10px;
        }

        .value-item-coords {
            color: #667eea;
            font-weight: 500;
            font-family: 'Courier New', monospace;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #48bb78;
            color: white;
        }

        .btn-secondary:hover {
            background: #38a169;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background: #e53e3e;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .debug-section {
            grid-column: 1 / -1;
            background: #2d3748;
            color: #fff;
            border-radius: 12px;
            padding: 25px;
        }

        .debug-output {
            background: #1a202c;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 15px;
        }

        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-success {
            background: #c6f6d5;
            color: #22543d;
            border: 2px solid #9ae6b4;
        }

        .status-error {
            background: #fed7d7;
            color: #742a2a;
            border: 2px solid #fc8181;
        }

        .coordinates-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .preview-area {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #718096;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .debug-section {
                grid-column: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖨️ PDF Text Printer</h1>
            <p>הדפסת טקסט על קבצי PDF עם תמיכה בעברית ואנגלית</p>
        </div>

        <div class="main-content">
            <div id="statusMessage" class="status-message"></div>

            <div class="section">
                <h2 class="section-title">⚙️ הגדרות בסיסיות</h2>
                
                <div class="form-group">
                    <label for="pdfUrl">כתובת קובץ PDF:</label>
                    <input type="text" id="pdfUrl" placeholder="https://example.com/file.pdf" dir="ltr">
                </div>

                <div class="form-group">
                    <label for="language">שפה:</label>
                    <select id="language">
                        <option value="he">עברית (RTL)</option>
                        <option value="en">English (LTR)</option>
                    </select>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">➕ הוספת ערכים</h2>
                
                <div class="form-group">
                    <label for="textValue">טקסט להדפסה:</label>
                    <input type="text" id="textValue" placeholder="הכנס טקסט...">
                </div>

                <div class="form-group">
                    <label>קואורדינטות (X, Y):</label>
                    <div class="coordinates-inputs">
                        <input type="number" id="xCoord" placeholder="X" min="0">
                        <input type="number" id="yCoord" placeholder="Y" min="0">
                    </div>
                </div>

                <button class="btn btn-secondary" onclick="addValue()">
                    ➕ הוסף ערך
                </button>

                <div class="preview-area" id="previewArea">
                    <span>אין ערכים להצגה</span>
                </div>
            </div>

            <div class="section" style="grid-column: 1 / -1;">
                <h2 class="section-title">📝 רשימת ערכים</h2>
                
                <div class="values-list" id="valuesList">
                    <p style="text-align: center; color: #718096;">לא הוספו ערכים עדיין</p>
                </div>

                <div class="button-group">
                    <button class="btn btn-primary" onclick="processManual()">
                        🚀 עבד PDF (ממשק)
                    </button>
                    <button class="btn btn-secondary" onclick="showJsonInput()">
                        📄 הזן JSON
                    </button>
                    <button class="btn btn-danger" onclick="clearAll()">
                        🗑️ נקה הכל
                    </button>
                </div>
            </div>

            <div class="section" id="jsonSection" style="grid-column: 1 / -1; display: none;">
                <h2 class="section-title">📋 קלט JSON</h2>
                
                <div class="form-group">
                    <label for="jsonInput">הדבק JSON:</label>
                    <textarea id="jsonInput" placeholder='{
    "filename": "https://example.com/file.pdf",
    "language": "he",
    "values": [
        {
            "text": "שלום עולם",
            "x": 100,
            "y": 200
        }
    ]
}'></textarea>
                </div>

                <div class="button-group">
                    <button class="btn btn-primary" onclick="processJson()">
                        🔄 עבד JSON
                    </button>
                    <button class="btn btn-secondary" onclick="hideJsonInput()">
                        ❌ סגור
                    </button>
                </div>
            </div>

            <div class="debug-section">
                <h2 class="section-title" style="color: white; border-color: #48bb78;">🐛 Debug Console</h2>
                
                <div class="button-group">
                    <button class="btn btn-secondary btn-small" onclick="clearDebug()">
                        🧹 נקה קונסול
                    </button>
                    <button class="btn btn-secondary btn-small" onclick="testConnection()">
                        🔌 בדוק חיבור
                    </button>
                </div>

                <div class="debug-output" id="debugOutput">
                    Debug console ready...
                </div>
            </div>
        </div>
    </div>

    <script>
        let values = [];
        const API_URL = 'process-pdf-simple.php'; // שנה לכתובת השרת שלך

        function debugLog(message, type = 'info') {
            const debugOutput = document.getElementById('debugOutput');
            const timestamp = new Date().toLocaleTimeString();
            const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
            debugOutput.innerHTML += `\n[${timestamp}] ${prefix} ${message}`;
            debugOutput.scrollTop = debugOutput.scrollHeight;
        }

        function showStatus(message, type = 'success') {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.textContent = message;
            statusDiv.className = `status-message status-${type}`;
            statusDiv.style.display = 'block';
            
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }

        function addValue() {
            const text = document.getElementById('textValue').value;
            const x = parseInt(document.getElementById('xCoord').value);
            const y = parseInt(document.getElementById('yCoord').value);

            if (!text || isNaN(x) || isNaN(y)) {
                showStatus('נא למלא את כל השדות', 'error');
                debugLog('Failed to add value: missing fields', 'error');
                return;
            }

            const value = { text, x, y };
            values.push(value);
            
            debugLog(`Added value: ${JSON.stringify(value)}`, 'success');
            updateValuesList();
            clearInputs();
            showStatus('הערך נוסף בהצלחה', 'success');
        }

        function updateValuesList() {
            const listDiv = document.getElementById('valuesList');
            const previewDiv = document.getElementById('previewArea');
            
            if (values.length === 0) {
                listDiv.innerHTML = '<p style="text-align: center; color: #718096;">לא הוספו ערכים עדיין</p>';
                previewDiv.innerHTML = '<span>אין ערכים להצגה</span>';
                return;
            }

            listDiv.innerHTML = values.map((val, index) => `
                <div class="value-item">
                    <span class="value-item-text">${val.text}</span>
                    <span class="value-item-coords">(${val.x}, ${val.y})</span>
                    <button class="btn btn-danger btn-small" onclick="removeValue(${index})">
                        הסר
                    </button>
                </div>
            `).join('');

            previewDiv.innerHTML = `<strong>סה"כ ערכים: ${values.length}</strong>`;
        }

        function removeValue(index) {
            const removed = values.splice(index, 1)[0];
            debugLog(`Removed value: ${JSON.stringify(removed)}`, 'info');
            updateValuesList();
            showStatus('הערך הוסר', 'success');
        }

        function clearInputs() {
            document.getElementById('textValue').value = '';
            document.getElementById('xCoord').value = '';
            document.getElementById('yCoord').value = '';
        }

        function clearAll() {
            if (confirm('האם אתה בטוח שברצונך לנקות את כל הערכים?')) {
                values = [];
                updateValuesList();
                clearInputs();
                document.getElementById('pdfUrl').value = '';
                debugLog('Cleared all values', 'info');
                showStatus('כל הנתונים נוקו', 'success');
            }
        }

        function showJsonInput() {
            document.getElementById('jsonSection').style.display = 'block';
            document.getElementById('jsonSection').scrollIntoView({ behavior: 'smooth' });
        }

        function hideJsonInput() {
            document.getElementById('jsonSection').style.display = 'none';
        }

        async function processManual() {
            const pdfUrl = document.getElementById('pdfUrl').value;
            const language = document.getElementById('language').value;

            if (!pdfUrl) {
                showStatus('נא להזין כתובת PDF', 'error');
                return;
            }

            if (values.length === 0) {
                showStatus('נא להוסיף לפחות ערך אחד', 'error');
                return;
            }

            const data = {
                filename: pdfUrl,
                language: language,
                values: values
            };

            debugLog(`Sending data: ${JSON.stringify(data, null, 2)}`, 'info');
            await sendToServer(data);
        }

        async function processJson() {
            const jsonInput = document.getElementById('jsonInput').value;

            try {
                const data = JSON.parse(jsonInput);
                debugLog(`Parsed JSON: ${JSON.stringify(data, null, 2)}`, 'success');
                
                if (!data.filename || !data.values || !Array.isArray(data.values)) {
                    throw new Error('Invalid JSON structure');
                }

                await sendToServer(data);
            } catch (e) {
                showStatus('שגיאה בפענוח JSON', 'error');
                debugLog(`JSON parse error: ${e.message}`, 'error');
            }
        }

        async function sendToServer(data) {
            debugLog('Sending request to server...', 'info');
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                
                if (result.success) {
                    showStatus('הקובץ עובד בהצלחה!', 'success');
                    debugLog(`Success: ${result.message || 'PDF processed successfully'}`, 'success');
                    
                    if (result.download_url) {
                        debugLog(`Download URL: ${result.download_url}`, 'success');
                        window.open(result.download_url, '_blank');
                    }
                } else {
                    throw new Error(result.error || 'Unknown error');
                }
            } catch (error) {
                showStatus('שגיאה בעיבוד הקובץ', 'error');
                debugLog(`Server error: ${error.message}`, 'error');
            }
        }

        async function testConnection() {
            debugLog('Testing server connection...', 'info');
            
            try {
                const response = await fetch(API_URL + '?test=1');
                if (response.ok) {
                    debugLog('Server connection successful!', 'success');
                    showStatus('החיבור לשרת תקין', 'success');
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                debugLog(`Connection failed: ${error.message}`, 'error');
                showStatus('החיבור לשרת נכשל', 'error');
            }
        }

        function clearDebug() {
            document.getElementById('debugOutput').innerHTML = 'Debug console cleared...';
            debugLog('Console cleared', 'info');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            debugLog('Application initialized', 'success');
            updateValuesList();
        });
    </script>
</body>
</html>