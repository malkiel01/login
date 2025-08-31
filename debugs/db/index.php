<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database to JSON Streamer</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .form-container {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 1.1em;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4facfe;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.2);
        }
        
        .btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 12px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .progress-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            display: none;
            border: 2px solid #e9ecef;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 15px;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            transition: width 0.3s ease;
            width: 0%;
            border-radius: 15px;
        }
        
        .progress-text {
            text-align: center;
            font-weight: 600;
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .result-container {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px solid #e9ecef;
            display: none;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .action-btn.download-btn {
            background: #17a2b8;
        }
        
        .action-btn.preview-btn {
            background: #6f42c1;
        }
        
        .json-preview {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            border: 1px solid #4a5568;
            direction: ltr;
            text-align: left;
            margin-top: 15px;
            display: none;
        }
        
        .error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        
        .success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .loading-animation {
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4facfe;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Database JSON Pro</h1>
            <p>הזרמה מתקדמת לנתונים גדולים עם מד התקדמות</p>
        </div>
        
        <div class="form-container">
            <div class="warning">
                ⚡ מותאם לטיפול ב-21,000+ רשומות ללא תקיעות
            </div>
            
            <form id="dbForm">
                <div class="form-group">
                    <label for="dbType">סוג מסד נתונים:</label>
                    <select id="dbType" name="dbType" required>
                        <option value="">בחר סוג מסד נתונים</option>
                        <option value="mysql">MySQL</option>
                        <option value="postgresql">PostgreSQL</option>
                        <option value="sqlserver">SQL Server</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="host">שרת:</label>
                    <input type="text" id="host" name="host" placeholder="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="port">פורט:</label>
                    <input type="number" id="port" name="port" placeholder="3306" required>
                </div>
                
                <div class="form-group">
                    <label for="database">מסד נתונים:</label>
                    <input type="text" id="database" name="database" placeholder="my_database" required>
                </div>
                
                <div class="form-group">
                    <label for="username">משתמש:</label>
                    <input type="text" id="username" name="username" placeholder="root" required>
                </div>
                
                <div class="form-group">
                    <label for="password">סיסמה:</label>
                    <input type="password" id="password" name="password">
                </div>
                
                <div class="form-group">
                    <label for="query">שאילתה:</label>
                    <textarea id="query" name="query" placeholder="graves_view או SELECT * FROM graves_view WHERE condition = 1" required rows="3"></textarea>
                </div>
                
                <button type="button" class="btn" id="connectBtn" onclick="startDataStream()">
                    🔥 התחל הזרמה
                </button>
            </form>
            
            <!-- Progress Section -->
            <div class="progress-container" id="progressContainer">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="recordsCount">0</div>
                        <div class="stat-label">רשומות נטענו</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="dataSize">0 KB</div>
                        <div class="stat-label">גודל נתונים</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="speed">0/s</div>
                        <div class="stat-label">מהירות</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="timeElapsed">0s</div>
                        <div class="stat-label">זמן שעבר</div>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">מתכונן להזרמה...</div>
                
                <div class="loading-animation" id="loadingAnimation">
                    <div class="spinner"></div>
                    <div class="loading-text">טוען נתונים מהשרת...</div>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="result-container" id="resultContainer">
                <div class="result-header">
                    <h3>✅ ההזרמה הושלמה!</h3>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="copyToClipboard()">📋 העתק הכל</button>
                        <button class="action-btn download-btn" onclick="downloadJSON()">💾 הורד קובץ</button>
                        <button class="action-btn preview-btn" onclick="togglePreview()">👁️ תצוגה מקדימה</button>
                    </div>
                </div>
                
                <div class="stats-grid" id="finalStats"></div>
                <div class="json-preview" id="jsonPreview"></div>
            </div>
            
            <div id="errorContainer"></div>
        </div>
    </div>

    <script>
        let streamedData = '';
        let recordCount = 0;
        let startTime = 0;
        let progressInterval = null;
        
        const defaultPorts = {
            mysql: 3306,
            postgresql: 5432,
            sqlserver: 1433
        };
        
        // Auto-update ports
        document.getElementById('dbType').addEventListener('change', function() {
            const port = defaultPorts[this.value];
            if (port) {
                document.getElementById('port').value = port;
            }
        });
        
        async function startDataStream() {
            const form = document.getElementById('dbForm');
            const formData = new FormData(form);
            const connectBtn = document.getElementById('connectBtn');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Reset state
            streamedData = '';
            recordCount = 0;
            startTime = Date.now();
            
            // Show progress and disable button
            connectBtn.disabled = true;
            connectBtn.textContent = 'מעבד...';
            showProgress();
            hideResults();
            hideError();
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP Error Response:', errorText);
                    throw new Error(`שגיאת HTTP ${response.status}: ${errorText}`);
                }
                
                const contentType = response.headers.get('Content-Type') || '';
                console.log('Content-Type received:', contentType);
                
                // Get response text for debugging
                const responseText = await response.text();
                console.log('Full response:', responseText.substring(0, 500) + '...');
                
                if (contentType.includes('application/json')) {
                    try {
                        const jsonResponse = JSON.parse(responseText);
                        if (jsonResponse.error) {
                            throw new Error(jsonResponse.message);
                        }
                        // It's valid JSON data
                        streamedData = responseText;
                        recordCount = countRecordsInJSON(responseText);
                        showResults();
                    } catch (parseError) {
                        console.error('JSON Parse Error:', parseError);
                        // Maybe it's streaming JSON, try to use as-is
                        streamedData = responseText;
                        recordCount = countRecordsInJSON(responseText);
                        showResults();
                    }
                } else if (responseText.trim().startsWith('[') || responseText.trim().startsWith('{')) {
                    // Looks like JSON even if Content-Type is wrong
                    console.log('Detected JSON despite wrong Content-Type');
                    streamedData = responseText;
                    recordCount = countRecordsInJSON(responseText);
                    showResults();
                } else {
                    // Show what we actually received
                    console.error('Unexpected response type. Content-Type:', contentType);
                    console.error('Response preview:', responseText.substring(0, 1000));
                    throw new Error('תגובה לא צפויה מהשרת. בדוק את ה-Console לפרטים נוספים.');
                }
                
            } catch (error) {
                console.error('Stream error:', error);
                showError(error.message);
            } finally {
                connectBtn.disabled = false;
                connectBtn.textContent = '🔥 התחל הזרמה';
                if (progressInterval) {
                    clearInterval(progressInterval);
                }
            }
        }
        
        async function processStream(response) {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            
            let buffer = '';
            let isFirstChunk = true;
            
            // Start progress updates
            progressInterval = setInterval(updateProgress, 1000);
            
            while (true) {
                const { done, value } = await reader.read();
                
                if (done) break;
                
                const chunk = decoder.decode(value, { stream: true });
                buffer += chunk;
                streamedData += chunk;
                
                // Count records (estimate by counting record separators)
                if (!isFirstChunk) {
                    const newRecords = (chunk.match(/},/g) || []).length;
                    recordCount += newRecords;
                } else {
                    isFirstChunk = false;
                }
                
                // Update real-time stats
                updateRealtimeStats();
            }
            
            // Final count
            recordCount = countRecordsInJSON(streamedData);
            showResults();
        }
        
        function countRecordsInJSON(jsonString) {
            try {
                const data = JSON.parse(jsonString);
                return Array.isArray(data) ? data.length : 1;
            } catch (e) {
                // Fallback: count by pattern matching
                return (jsonString.match(/},/g) || []).length + 1;
            }
        }
        
        function updateRealtimeStats() {
            document.getElementById('recordsCount').textContent = recordCount.toLocaleString();
            
            const sizeKB = Math.round(streamedData.length / 1024);
            document.getElementById('dataSize').textContent = sizeKB.toLocaleString() + ' KB';
        }
        
        function updateProgress() {
            const elapsed = (Date.now() - startTime) / 1000;
            document.getElementById('timeElapsed').textContent = elapsed.toFixed(1) + 's';
            
            if (recordCount > 0 && elapsed > 0) {
                const speed = Math.round(recordCount / elapsed);
                document.getElementById('speed').textContent = speed + '/s';
            }
            
            document.getElementById('progressText').textContent = 
                `עיבוד נתונים... ${recordCount.toLocaleString()} רשומות נטענו`;
        }
        
        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('progressFill').style.width = '0%';
            document.getElementById('progressText').textContent = 'מתחבר למסד הנתונים...';
            
            // Reset stats
            document.getElementById('recordsCount').textContent = '0';
            document.getElementById('dataSize').textContent = '0 KB';
            document.getElementById('speed').textContent = '0/s';
            document.getElementById('timeElapsed').textContent = '0s';
        }
        
        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
        }
        
        function showResults() {
            hideProgress();
            document.getElementById('resultContainer').style.display = 'block';
            
            const elapsed = (Date.now() - startTime) / 1000;
            const sizeKB = Math.round(streamedData.length / 1024);
            const avgSpeed = Math.round(recordCount / elapsed);
            
            document.getElementById('finalStats').innerHTML = `
                <div class="stat-card">
                    <div class="stat-number">${recordCount.toLocaleString()}</div>
                    <div class="stat-label">סה"כ רשומות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${sizeKB.toLocaleString()} KB</div>
                    <div class="stat-label">גודל קובץ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${elapsed.toFixed(1)}s</div>
                    <div class="stat-label">זמן הזרמה</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${avgSpeed}/s</div>
                    <div class="stat-label">מהירות ממוצעת</div>
                </div>
            `;
        }
        
        function hideResults() {
            document.getElementById('resultContainer').style.display = 'none';
        }
        
        function showError(message) {
            hideProgress();
            hideResults();
            document.getElementById('errorContainer').innerHTML = `
                <div class="error">
                    <h3>❌ שגיאה בהזרמה</h3>
                    <p>${message}</p>
                    <p style="margin-top: 10px; font-size: 14px;">בדוק את פרטי החיבור ונסה שוב</p>
                </div>
            `;
        }
        
        function hideError() {
            document.getElementById('errorContainer').innerHTML = '';
        }
        
        function copyToClipboard() {
            if (streamedData) {
                navigator.clipboard.writeText(streamedData).then(() => {
                    const copyBtn = document.querySelector('.action-btn');
                    const originalText = copyBtn.textContent;
                    copyBtn.textContent = '✅ הועתק!';
                    copyBtn.style.background = '#28a745';
                    
                    setTimeout(() => {
                        copyBtn.textContent = originalText;
                        copyBtn.style.background = '#28a745';
                    }, 2000);
                }).catch(err => {
                    alert('שגיאה בהעתקה: ' + err.message);
                });
            }
        }
        
        function downloadJSON() {
            if (streamedData) {
                const blob = new Blob([streamedData], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'database_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                // Visual feedback
                const downloadBtn = document.querySelector('.download-btn');
                const originalText = downloadBtn.textContent;
                downloadBtn.textContent = '✅ הורד!';
                setTimeout(() => {
                    downloadBtn.textContent = originalText;
                }, 2000);
            }
        }
        
        function togglePreview() {
            const previewDiv = document.getElementById('jsonPreview');
            const previewBtn = document.querySelector('.preview-btn');
            
            if (previewDiv.style.display === 'none' || !previewDiv.style.display) {
                if (streamedData) {
                    // Show first 2000 characters
                    let preview = streamedData.substring(0, 2000);
                    if (streamedData.length > 2000) {
                        preview += '\n\n... (מוצגים 2000 תווים ראשונים מתוך ' + streamedData.length.toLocaleString() + ')';
                    }
                    
                    previewDiv.textContent = preview;
                    previewDiv.style.display = 'block';
                    previewBtn.textContent = '🙈 הסתר תצוגה';
                }
            } else {
                previewDiv.style.display = 'none';
                previewBtn.textContent = '👁️ תצוגה מקדימה';
            }
        }
    </script>

    <?php
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Process POST requests (both AJAX and regular)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $dbType = $_POST['dbType'] ?? '';
        $host = $_POST['host'] ?? '';
        $port = $_POST['port'] ?? '';
        $database = $_POST['database'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $query = trim($_POST['query'] ?? '');
        
        // Debug: Log received data
        error_log("Received POST data: " . print_r($_POST, true));
        
        // Validate required fields
        if (empty($dbType) || empty($host) || empty($port) || empty($database) || empty($username) || empty($query)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => 'חסרים שדות נדרשים']);
            exit;
        }
        
        try {
            // Create connection
            $pdo = createConnection($dbType, $host, $port, $database, $username, $password);
            
            // Test connection first
            $testQuery = "SELECT 1 as test";
            $testStmt = $pdo->prepare($testQuery);
            $testStmt->execute();
            $testResult = $testStmt->fetch();
            
            if (!$testResult) {
                throw new Exception('מבחן החיבור נכשל');
            }
            
            // Stream JSON data
            streamJSONData($pdo, $query);
            
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true, 
                'message' => 'שגיאת חיבור למסד הנתונים: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true, 
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    function createConnection($dbType, $host, $port, $database, $username, $password) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 300
        ];
        
        switch ($dbType) {
            case 'mysql':
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
                break;
                
            case 'postgresql':
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                break;
                
            case 'sqlserver':
                $dsn = "sqlsrv:Server={$host},{$port};Database={$database}";
                break;
                
            default:
                throw new Exception("סוג מסד נתונים לא נתמך: {$dbType}");
        }
        
        return new PDO($dsn, $username, $password, $options);
    }
    
    function streamJSONData($pdo, $query) {
        // Prepare query - validate and sanitize
        $finalQuery = prepareQuery($query);
        
        // Set headers for JSON streaming
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        // Increase limits for large data
        set_time_limit(600);
        ini_set('memory_limit', '1G');
        
        // Disable all output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        try {
            $stmt = $pdo->prepare($finalQuery);
            $stmt->execute();
            
            // Start JSON array
            echo '[';
            $first = true;
            $batchCount = 0;
            
            while ($row = $stmt->fetch()) {
                if (!$first) {
                    echo ',';
                }
                
                echo json_encode($row, JSON_UNESCAPED_UNICODE);
                $first = false;
                $batchCount++;
                
                // Flush every 100 records for smooth streaming
                if ($batchCount % 100 === 0) {
                    flush();
                    // Small delay to prevent overwhelming the browser
                    usleep(10000); // 10ms
                }
            }
            
            // Close JSON array properly
            echo ']';
            flush();
            
        } catch (Exception $e) {
            // If we already started outputting, we can't change headers
            // Just output error in JSON format
            if (!$first) {
                echo ',{"error": "' . addslashes($e->getMessage()) . '"}';
            } else {
                echo '{"error": "' . addslashes($e->getMessage()) . '"}';
            }
            echo ']';
        }
    }
    
    function prepareQuery($query) {
        // Clean query
        $query = trim($query);
        
        // If it's just a table/view name, create SELECT query
        if (!preg_match('/^\s*SELECT/i', $query)) {
            // Sanitize table name
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $query);
            return "SELECT * FROM `{$tableName}`";
        }
        
        return $query;
    }
    ?>
</body>
</html>