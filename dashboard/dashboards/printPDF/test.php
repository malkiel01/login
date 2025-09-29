<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דיבוג שמירה - PDF Editor</title>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Rubik', sans-serif;
            direction: rtl;
            padding: 20px;
            background: #f5f5f5;
        }
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .test-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #667eea;
        }
        button {
            padding: 8px 16px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            background: #667eea;
            color: white;
            cursor: pointer;
            font-family: 'Rubik', sans-serif;
        }
        button:hover {
            background: #5a67d8;
        }
        .success {
            background: #48bb78 !important;
        }
        .error {
            background: #f56565 !important;
        }
        .log {
            margin-top: 10px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            direction: ltr;
            text-align: left;
        }
        .log-entry {
            margin: 2px 0;
            padding: 2px 5px;
        }
        .log-success {
            background: #c6f6d5;
        }
        .log-error {
            background: #feb2b2;
        }
        .log-info {
            background: #bee3f8;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            margin: 5px;
        }
        .status.ok {
            background: #c6f6d5;
            color: #22543d;
        }
        .status.fail {
            background: #feb2b2;
            color: #742a2a;
        }
        #mainLog {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            max-height: 300px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔧 דיבוג מערכת שמירה - PDF Editor</h1>
        
        <!-- בדיקת קבצים -->
        <div class="test-section">
            <div class="test-title">1. בדיקת טעינת קבצים</div>
            <button onclick="checkFiles()">בדוק קבצים</button>
            <div id="filesStatus"></div>
            <div class="log" id="filesLog"></div>
        </div>

        <!-- בדיקת API -->
        <div class="test-section">
            <div class="test-title">2. בדיקת API Connector</div>
            <button onclick="checkAPI()">בדוק API</button>
            <div id="apiStatus"></div>
            <div class="log" id="apiLog"></div>
        </div>

        <!-- בדיקת CloudSaveManager -->
        <div class="test-section">
            <div class="test-title">3. בדיקת CloudSaveManager</div>
            <button onclick="checkCloudManager()">בדוק Cloud Manager</button>
            <div id="cloudStatus"></div>
            <div class="log" id="cloudLog"></div>
        </div>

        <!-- בדיקת שמירה בסיסית -->
        <div class="test-section">
            <div class="test-title">4. בדיקת שמירה ל-localStorage</div>
            <button onclick="testLocalSave()">בדוק שמירה מקומית</button>
            <button onclick="clearLocalStorage()">נקה localStorage</button>
            <div id="localStatus"></div>
            <div class="log" id="localLog"></div>
        </div>

        <!-- בדיקת שמירה לשרת -->
        <div class="test-section">
            <div class="test-title">5. בדיקת שמירה לשרת</div>
            <button onclick="testServerSave()">בדוק שמירה לשרת</button>
            <div id="serverStatus"></div>
            <div class="log" id="serverLog"></div>
        </div>

        <!-- בדיקת Canvas -->
        <div class="test-section">
            <div class="test-title">6. בדיקת Canvas Manager</div>
            <button onclick="checkCanvas()">בדוק Canvas</button>
            <div id="canvasStatus"></div>
            <div class="log" id="canvasLog"></div>
        </div>

        <!-- בדיקה מלאה -->
        <div class="test-section">
            <div class="test-title">7. בדיקת תהליך שמירה מלא</div>
            <button onclick="testFullSave()">הרץ בדיקה מלאה</button>
            <button onclick="simulateSaveClick()">סימולציה של לחיצת שמירה</button>
            <div id="fullStatus"></div>
            <div class="log" id="fullLog"></div>
        </div>
    </div>

    <!-- Log גלובלי -->
    <div id="mainLog">
        <strong>📋 לוג ראשי</strong>
        <div id="mainLogContent"></div>
    </div>

    <!-- Load all scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/canvas-manager.js"></script>
    <script src="assets/js/api-connector-fixed.js"></script>
    <script src="assets/js/cloud-save-manager-fixed.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        // Logging helper
        function log(message, type = 'info', targetId = 'mainLogContent') {
            const logElement = document.getElementById(targetId);
            if (logElement) {
                const entry = document.createElement('div');
                entry.className = `log-entry log-${type}`;
                entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
                logElement.appendChild(entry);
                logElement.scrollTop = logElement.scrollHeight;
            }
            console.log(`[${type}] ${message}`);
        }

        // 1. Check Files
        function checkFiles() {
            log('בודק קבצים...', 'info', 'filesLog');
            
            const files = [
                'config.js',
                'canvas-manager.js', 
                'api-connector-fixed.js',
                'cloud-save-manager-fixed.js',
                'app.js'
            ];
            
            let allLoaded = true;
            const status = document.getElementById('filesStatus');
            status.innerHTML = '';
            
            files.forEach(file => {
                // Check if script exists
                const scripts = Array.from(document.scripts);
                const exists = scripts.some(s => s.src.includes(file));
                
                if (exists) {
                    status.innerHTML += `<span class="status ok">✓ ${file}</span>`;
                    log(`✓ ${file} נטען`, 'success', 'filesLog');
                } else {
                    status.innerHTML += `<span class="status fail">✗ ${file}</span>`;
                    log(`✗ ${file} לא נמצא`, 'error', 'filesLog');
                    allLoaded = false;
                }
            });
            
            if (allLoaded) {
                log('כל הקבצים נטענו בהצלחה!', 'success', 'filesLog');
            } else {
                log('חלק מהקבצים חסרים!', 'error', 'filesLog');
            }
        }

        // 2. Check API
        function checkAPI() {
            log('בודק API Connector...', 'info', 'apiLog');
            const status = document.getElementById('apiStatus');
            
            try {
                // Check if APIConnector exists
                if (typeof APIConnector !== 'undefined') {
                    log('✓ APIConnector class exists', 'success', 'apiLog');
                    
                    // Try to create instance
                    const api = new APIConnector();
                    log('✓ APIConnector instance created', 'success', 'apiLog');
                    
                    // Check methods
                    const methods = ['uploadFile', 'saveProject', 'loadProject', 'request'];
                    methods.forEach(method => {
                        if (typeof api[method] === 'function') {
                            log(`✓ Method ${method} exists`, 'success', 'apiLog');
                        } else {
                            log(`✗ Method ${method} missing`, 'error', 'apiLog');
                        }
                    });
                    
                    // Check endpoints
                    log('Endpoints:', 'info', 'apiLog');
                    for (const [key, value] of Object.entries(api.endpoints)) {
                        log(`  ${key}: ${value}`, 'info', 'apiLog');
                    }
                    
                    status.innerHTML = '<span class="status ok">✓ API Connector תקין</span>';
                    
                    // Make global
                    window.testApi = api;
                    log('API instance saved to window.testApi', 'info', 'apiLog');
                    
                } else {
                    status.innerHTML = '<span class="status fail">✗ APIConnector לא נמצא</span>';
                    log('✗ APIConnector class not found', 'error', 'apiLog');
                }
            } catch (error) {
                status.innerHTML = '<span class="status fail">✗ שגיאה ב-API</span>';
                log(`Error: ${error.message}`, 'error', 'apiLog');
            }
        }

        // 3. Check CloudSaveManager
        function checkCloudManager() {
            log('בודק CloudSaveManager...', 'info', 'cloudLog');
            const status = document.getElementById('cloudStatus');
            
            try {
                if (typeof CloudSaveManager !== 'undefined') {
                    log('✓ CloudSaveManager class exists', 'success', 'cloudLog');
                    
                    // Create API first
                    const api = new APIConnector();
                    
                    // Create CloudSaveManager
                    const csm = new CloudSaveManager(null, api);
                    log('✓ CloudSaveManager instance created', 'success', 'cloudLog');
                    
                    // Check methods
                    const methods = ['saveProject', 'loadProject', 'saveToLocalStorage'];
                    methods.forEach(method => {
                        if (typeof csm[method] === 'function') {
                            log(`✓ Method ${method} exists`, 'success', 'cloudLog');
                        } else {
                            log(`✗ Method ${method} missing`, 'error', 'cloudLog');
                        }
                    });
                    
                    // Check API connection
                    if (csm.api) {
                        log('✓ API connected to CloudSaveManager', 'success', 'cloudLog');
                        if (typeof csm.api.saveProject === 'function') {
                            log('✓ api.saveProject method exists', 'success', 'cloudLog');
                        } else {
                            log('✗ api.saveProject method missing', 'error', 'cloudLog');
                        }
                    } else {
                        log('✗ API not connected', 'error', 'cloudLog');
                    }
                    
                    status.innerHTML = '<span class="status ok">✓ CloudSaveManager תקין</span>';
                    
                    // Make global
                    window.testCloudManager = csm;
                    log('CloudSaveManager saved to window.testCloudManager', 'info', 'cloudLog');
                    
                } else {
                    status.innerHTML = '<span class="status fail">✗ CloudSaveManager לא נמצא</span>';
                    log('✗ CloudSaveManager class not found', 'error', 'cloudLog');
                }
            } catch (error) {
                status.innerHTML = '<span class="status fail">✗ שגיאה</span>';
                log(`Error: ${error.message}`, 'error', 'cloudLog');
                console.error(error);
            }
        }

        // 4. Test Local Save
        function testLocalSave() {
            log('בודק שמירה מקומית...', 'info', 'localLog');
            
            try {
                const testData = {
                    id: 'test_' + Date.now(),
                    name: 'Test Project',
                    data: { test: true },
                    timestamp: new Date().toISOString()
                };
                
                // Save to localStorage
                localStorage.setItem('pdf_test', JSON.stringify(testData));
                log('✓ Data saved to localStorage', 'success', 'localLog');
                
                // Read back
                const retrieved = localStorage.getItem('pdf_test');
                if (retrieved) {
                    const parsed = JSON.parse(retrieved);
                    if (parsed.id === testData.id) {
                        log('✓ Data retrieved successfully', 'success', 'localLog');
                        log(`Data: ${JSON.stringify(parsed)}`, 'info', 'localLog');
                        document.getElementById('localStatus').innerHTML = 
                            '<span class="status ok">✓ localStorage עובד</span>';
                    }
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error', 'localLog');
                document.getElementById('localStatus').innerHTML = 
                    '<span class="status fail">✗ localStorage נכשל</span>';
            }
        }

        // 5. Test Server Save
        async function testServerSave() {
            log('בודק שמירה לשרת...', 'info', 'serverLog');
            const status = document.getElementById('serverStatus');
            
            try {
                const api = new APIConnector();
                
                const testData = {
                    id: 'server_test_' + Date.now(),
                    name: 'Server Test Project',
                    canvas: { test: true },
                    timestamp: new Date().toISOString()
                };
                
                log('Sending test data to server...', 'info', 'serverLog');
                log(`Endpoint: ${api.endpoints.cloudSync}`, 'info', 'serverLog');
                
                const response = await api.saveProject(testData);
                
                if (response.success) {
                    log('✓ Server save successful!', 'success', 'serverLog');
                    log(`Response: ${JSON.stringify(response)}`, 'info', 'serverLog');
                    status.innerHTML = '<span class="status ok">✓ שמירת שרת עובדת</span>';
                } else {
                    log('✗ Server save failed', 'error', 'serverLog');
                    log(`Response: ${JSON.stringify(response)}`, 'error', 'serverLog');
                    status.innerHTML = '<span class="status fail">✗ שמירת שרת נכשלה</span>';
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error', 'serverLog');
                status.innerHTML = '<span class="status fail">✗ שגיאת שרת</span>';
                
                // Try to understand the error
                if (error.message.includes('404')) {
                    log('API endpoint not found - check file exists', 'error', 'serverLog');
                } else if (error.message.includes('500')) {
                    log('Server error - check PHP logs', 'error', 'serverLog');
                } else if (error.message.includes('fetch')) {
                    log('Network error - check connection', 'error', 'serverLog');
                }
            }
        }

        // 6. Check Canvas
        function checkCanvas() {
            log('בודק Canvas Manager...', 'info', 'canvasLog');
            const status = document.getElementById('canvasStatus');
            
            try {
                // Create a temporary canvas
                const tempCanvas = document.createElement('canvas');
                tempCanvas.id = 'tempCanvas';
                document.body.appendChild(tempCanvas);
                
                if (typeof CanvasManager !== 'undefined') {
                    log('✓ CanvasManager class exists', 'success', 'canvasLog');
                    
                    const cm = new CanvasManager('tempCanvas');
                    log('✓ CanvasManager instance created', 'success', 'canvasLog');
                    
                    // Check methods
                    if (typeof cm.getCanvasJSON === 'function') {
                        log('✓ getCanvasJSON method exists', 'success', 'canvasLog');
                        
                        const json = cm.getCanvasJSON();
                        log(`Canvas JSON: ${JSON.stringify(json).substring(0, 100)}...`, 'info', 'canvasLog');
                    }
                    
                    status.innerHTML = '<span class="status ok">✓ Canvas Manager תקין</span>';
                } else {
                    status.innerHTML = '<span class="status fail">✗ CanvasManager לא נמצא</span>';
                }
                
                // Clean up
                document.body.removeChild(tempCanvas);
                
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error', 'canvasLog');
                status.innerHTML = '<span class="status fail">✗ שגיאה</span>';
            }
        }

        // 7. Test Full Save
        async function testFullSave() {
            log('מריץ בדיקה מלאה...', 'info', 'fullLog');
            const status = document.getElementById('fullStatus');
            
            try {
                // Step 1: Create instances
                log('Step 1: Creating instances...', 'info', 'fullLog');
                const api = new APIConnector();
                const cloudManager = new CloudSaveManager(null, api);
                
                // Step 2: Prepare test data
                log('Step 2: Preparing data...', 'info', 'fullLog');
                const testProject = {
                    id: 'full_test_' + Date.now(),
                    name: 'Full Test Project',
                    canvas: { 
                        objects: [],
                        version: '5.3.0'
                    }
                };
                
                // Step 3: Try to save
                log('Step 3: Attempting save...', 'info', 'fullLog');
                
                // Override to use test data
                cloudManager.currentProject = testProject;
                
                // Mock canvas data
                const originalGetCanvas = window.app?.canvasManager?.getCanvasJSON;
                if (window.app && window.app.canvasManager) {
                    window.app.canvasManager.getCanvasJSON = () => testProject.canvas;
                }
                
                await cloudManager.saveProject();
                
                log('✓ Full save completed!', 'success', 'fullLog');
                status.innerHTML = '<span class="status ok">✓ בדיקה מלאה הצליחה</span>';
                
                // Restore
                if (originalGetCanvas && window.app && window.app.canvasManager) {
                    window.app.canvasManager.getCanvasJSON = originalGetCanvas;
                }
                
            } catch (error) {
                log(`✗ Full save failed: ${error.message}`, 'error', 'fullLog');
                status.innerHTML = '<span class="status fail">✗ בדיקה מלאה נכשלה</span>';
                console.error(error);
            }
        }

        // Simulate Save Click
        function simulateSaveClick() {
            log('מדמה לחיצת כפתור שמירה...', 'info', 'fullLog');
            
            // Check if save button exists
            const saveBtn = document.getElementById('btnSave');
            if (saveBtn) {
                log('Found save button, clicking...', 'info', 'fullLog');
                saveBtn.click();
            } else {
                log('Save button not found', 'error', 'fullLog');
                
                // Try direct call
                if (window.cloudSaveManager) {
                    log('Calling cloudSaveManager.saveProject() directly', 'info', 'fullLog');
                    window.cloudSaveManager.saveProject();
                } else if (window.app && window.app.saveDocument) {
                    log('Calling app.saveDocument() directly', 'info', 'fullLog');
                    window.app.saveDocument();
                } else {
                    log('No save method available', 'error', 'fullLog');
                }
            }
        }

        // Clear localStorage
        function clearLocalStorage() {
            const keys = Object.keys(localStorage).filter(k => k.includes('pdf'));
            keys.forEach(key => {
                localStorage.removeItem(key);
                log(`Removed: ${key}`, 'info', 'localLog');
            });
            log(`Cleared ${keys.length} items from localStorage`, 'success', 'localLog');
        }

        // Auto-run basic checks
        window.addEventListener('DOMContentLoaded', function() {
            log('=== Debug Page Loaded ===', 'info');
            log('Starting automatic checks...', 'info');
            
            setTimeout(() => {
                checkFiles();
                setTimeout(() => {
                    checkAPI();
                    setTimeout(() => {
                        checkCloudManager();
                    }, 500);
                }, 500);
            }, 500);
        });
    </script>
</body>
</html>