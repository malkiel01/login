<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת שרת ותקשורת - MBE Plus</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .test-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .test-title {
            font-size: 1.5em;
            color: #333;
            font-weight: 600;
        }
        
        .test-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .status-testing {
            background: #ffc107;
            color: white;
            animation: pulse 1.5s infinite;
        }
        
        .status-success {
            background: #4CAF50;
            color: white;
        }
        
        .status-error {
            background: #f44336;
            color: white;
        }
        
        .status-warning {
            background: #ff9800;
            color: white;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .test-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .test-item:hover {
            background: #e9ecef;
            transform: translateX(-5px);
        }
        
        .test-name {
            font-weight: 500;
            color: #495057;
        }
        
        .test-result {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .icon-success {
            background: #4CAF50;
            color: white;
        }
        
        .icon-error {
            background: #f44336;
            color: white;
        }
        
        .icon-warning {
            background: #ff9800;
            color: white;
        }
        
        .icon-loading {
            background: #2196F3;
            color: white;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .result-text {
            font-size: 0.9em;
            color: #6c757d;
            max-width: 400px;
            text-align: left;
        }
        
        .error-details {
            background: #ffebee;
            border-right: 4px solid #f44336;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            color: #c62828;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .success-details {
            background: #e8f5e9;
            border-right: 4px solid #4CAF50;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            font-size: 0.85em;
            color: #2e7d32;
        }
        
        .control-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        button {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        button:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }
        
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .json-viewer {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.85em;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .collapsible {
            cursor: pointer;
            user-select: none;
        }
        
        .collapsible:hover {
            opacity: 0.8;
        }
        
        .content {
            display: none;
            margin-top: 15px;
        }
        
        .content.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 בדיקת שרת ותקשורת מקיפה</h1>
        
        <div class="control-buttons">
            <button onclick="runAllTests()">▶️ הרץ את כל הבדיקות</button>
            <button onclick="clearResults()">🗑️ נקה תוצאות</button>
            <button onclick="exportResults()">💾 ייצא תוצאות</button>
        </div>

        <!-- בדיקת ENV וקונפיגורציה -->
        <div class="test-section" id="env-section">
            <div class="test-header">
                <div class="test-title">⚙️ בדיקת קובץ ENV וקונפיגורציה</div>
                <div class="test-status status-testing" id="env-status">בודק...</div>
            </div>
            <div id="env-tests"></div>
        </div>

        <!-- בדיקת חיבור למסד נתונים -->
        <div class="test-section" id="db-section">
            <div class="test-header">
                <div class="test-title">🗄️ בדיקת מסד נתונים</div>
                <div class="test-status status-testing" id="db-status">בודק...</div>
            </div>
            <div id="db-tests"></div>
        </div>

        <!-- בדיקת טבלאות -->
        <div class="test-section" id="tables-section">
            <div class="test-header">
                <div class="test-title">📊 בדיקת טבלאות במסד נתונים</div>
                <div class="test-status status-testing" id="tables-status">בודק...</div>
            </div>
            <div id="tables-tests"></div>
        </div>

        <!-- בדיקת API Endpoints -->
        <div class="test-section" id="api-section">
            <div class="test-header">
                <div class="test-title">🌐 בדיקת API Endpoints</div>
                <div class="test-status status-testing" id="api-status">בודק...</div>
            </div>
            <div id="api-tests"></div>
        </div>

        <!-- בדיקת הרשאות וקבצים -->
        <div class="test-section" id="files-section">
            <div class="test-header">
                <div class="test-title">📁 בדיקת קבצים והרשאות</div>
                <div class="test-status status-testing" id="files-status">בודק...</div>
            </div>
            <div id="files-tests"></div>
        </div>

        <!-- בדיקת Google Auth -->
        <div class="test-section" id="google-section">
            <div class="test-header">
                <div class="test-title">🔐 בדיקת Google Authentication</div>
                <div class="test-status status-testing" id="google-status">בודק...</div>
            </div>
            <div id="google-tests"></div>
        </div>

        <!-- סיכום -->
        <div class="summary" id="summary" style="display: none;">
            <h2>📋 סיכום בדיקות</h2>
            <div class="summary-stats">
                <div class="stat">
                    <div class="stat-number" id="total-tests">0</div>
                    <div class="stat-label">סה"כ בדיקות</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="passed-tests">0</div>
                    <div class="stat-label">עברו בהצלחה</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="failed-tests">0</div>
                    <div class="stat-label">נכשלו</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="warning-tests">0</div>
                    <div class="stat-label">אזהרות</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let testResults = {
            total: 0,
            passed: 0,
            failed: 0,
            warnings: 0,
            details: {}
        };

        // בדיקות ENV
        async function testEnv() {
            const section = document.getElementById('env-tests');
            section.innerHTML = '<div class="test-item"><span>בודק קובץ ENV...</span></div>';
            
            try {
                const response = await fetch('test-server-api.php?test=env');
                const data = await response.json();
                
                let html = '';
                if (data.success) {
                    html += createTestItem('קובץ .env נמצא', 'success', data.env_exists ? 'קיים' : 'חסר');
                    html += createTestItem('DB_HOST', data.config.DB_HOST ? 'success' : 'error', data.config.DB_HOST || 'לא מוגדר');
                    html += createTestItem('DB_NAME', data.config.DB_NAME ? 'success' : 'error', data.config.DB_NAME || 'לא מוגדר');
                    html += createTestItem('DB_USER', data.config.DB_USER ? 'success' : 'error', data.config.DB_USER || 'לא מוגדר');
                    html += createTestItem('CLIENT_ID (Google)', data.config.CLIENT_ID ? 'success' : 'warning', data.config.CLIENT_ID ? 'מוגדר' : 'לא מוגדר');
                    
                    if (data.config.PORT) {
                        html += createTestItem('PORT', 'warning', `${data.config.PORT} - יש להסיר! MySQL לא משתמש בפורט זה`);
                    }
                    
                    updateSectionStatus('env', data.warnings > 0 ? 'warning' : 'success');
                } else {
                    html = createTestItem('שגיאה בטעינת ENV', 'error', data.error);
                    updateSectionStatus('env', 'error');
                }
                
                section.innerHTML = html;
            } catch (error) {
                section.innerHTML = createTestItem('שגיאת תקשורת', 'error', error.message);
                updateSectionStatus('env', 'error');
            }
        }

        // בדיקת מסד נתונים
        async function testDatabase() {
            const section = document.getElementById('db-tests');
            section.innerHTML = '<div class="test-item"><span>בודק חיבור למסד נתונים...</span></div>';
            
            try {
                const response = await fetch('test-server-api.php?test=database');
                const data = await response.json();
                
                let html = '';
                if (data.success) {
                    html += createTestItem('חיבור למסד נתונים', 'success', 'מחובר בהצלחה');
                    html += createTestItem('שם מסד נתונים', 'success', data.database_name);
                    html += createTestItem('גרסת MySQL', 'success', data.mysql_version);
                    html += createTestItem('Charset', 'success', data.charset);
                    html += createTestItem('Collation', 'success', data.collation);
                    
                    updateSectionStatus('db', 'success');
                } else {
                    html = createTestItem('חיבור נכשל', 'error', data.error);
                    if (data.details) {
                        html += `<div class="error-details">${data.details}</div>`;
                    }
                    updateSectionStatus('db', 'error');
                }
                
                section.innerHTML = html;
            } catch (error) {
                section.innerHTML = createTestItem('שגיאת תקשורת', 'error', error.message);
                updateSectionStatus('db', 'error');
            }
        }

        // בדיקת טבלאות
        async function testTables() {
            const section = document.getElementById('tables-tests');
            section.innerHTML = '<div class="test-item"><span>בודק טבלאות...</span></div>';
            
            try {
                const response = await fetch('test-server-api.php?test=tables');
                const data = await response.json();
                
                let html = '';
                if (data.success) {
                    // טבלת users
                    html += createTestItem('טבלת users', data.tables.users.exists ? 'success' : 'error', 
                        data.tables.users.exists ? `${data.tables.users.count} רשומות` : 'לא קיימת');
                    
                    if (data.tables.users.columns) {
                        html += '<div class="collapsible test-item" onclick="toggleContent(this)">';
                        html += '<span>📋 עמודות בטבלת users (לחץ לפתיחה)</span>';
                        html += '</div>';
                        html += '<div class="content json-viewer">';
                        html += data.tables.users.columns.join(', ');
                        html += '</div>';
                    }
                    
                    // טבלאות נוספות
                    const otherTables = ['cemeteries', 'graves', 'customers', 'purchases'];
                    for (const table of otherTables) {
                        if (data.tables[table]) {
                            html += createTestItem(`טבלת ${table}`, 
                                data.tables[table].exists ? 'success' : 'warning',
                                data.tables[table].exists ? `${data.tables[table].count} רשומות` : 'לא קיימת');
                        }
                    }
                    
                    updateSectionStatus('tables', 'success');
                } else {
                    html = createTestItem('שגיאה בבדיקת טבלאות', 'error', data.error);
                    updateSectionStatus('tables', 'error');
                }
                
                section.innerHTML = html;
            } catch (error) {
                section.innerHTML = createTestItem('שגיאת תקשורת', 'error', error.message);
                updateSectionStatus('tables', 'error');
            }
        }

        // בדיקת API Endpoints
        async function testAPIs() {
            const section = document.getElementById('api-tests');
            section.innerHTML = '<div class="test-item"><span>בודק API endpoints...</span></div>';
            
            const endpoints = [
                { name: 'Dashboard Stats', url: 'dashboard/api/get_stats.php' },
                { name: 'Cemeteries API', url: 'dashboard/api/get_cemeteries.php' },
                { name: 'Google Auth', url: 'auth/google-auth.php', method: 'POST' },
                { name: 'Permissions API', url: 'permissions2/api/check-permissions.php' }
            ];
            
            let html = '';
            
            for (const endpoint of endpoints) {
                try {
                    const options = endpoint.method === 'POST' ? {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ test: true })
                    } : {};
                    
                    const response = await fetch(endpoint.url, options);
                    const contentType = response.headers.get('content-type');
                    
                    if (contentType && contentType.includes('application/json')) {
                        const data = await response.text();
                        try {
                            JSON.parse(data);
                            html += createTestItem(endpoint.name, 'success', 'מחזיר JSON תקין');
                        } catch {
                            html += createTestItem(endpoint.name, 'error', 'JSON לא תקין');
                        }
                    } else {
                        html += createTestItem(endpoint.name, 'error', `Content-Type: ${contentType || 'לא מוגדר'}`);
                    }
                } catch (error) {
                    html += createTestItem(endpoint.name, 'error', error.message);
                }
            }
            
            section.innerHTML = html;
            updateSectionStatus('api', 'success');
        }

        // בדיקת קבצים
        async function testFiles() {
            const section = document.getElementById('files-tests');
            section.innerHTML = '<div class="test-item"><span>בודק קבצים והרשאות...</span></div>';
            
            try {
                const response = await fetch('test-server-api.php?test=files');
                const data = await response.json();
                
                let html = '';
                if (data.success) {
                    for (const [file, info] of Object.entries(data.files)) {
                        html += createTestItem(
                            file,
                            info.exists ? 'success' : 'error',
                            info.exists ? `${info.readable ? 'ניתן לקריאה' : 'לא ניתן לקריאה'}, ${info.size} bytes` : 'לא קיים'
                        );
                    }
                    
                    html += createTestItem('תיקיית uploads', data.uploads.writable ? 'success' : 'warning', 
                        data.uploads.writable ? 'ניתן לכתיבה' : 'לא ניתן לכתיבה');
                    
                    updateSectionStatus('files', 'success');
                } else {
                    html = createTestItem('שגיאה בבדיקת קבצים', 'error', data.error);
                    updateSectionStatus('files', 'error');
                }
                
                section.innerHTML = html;
            } catch (error) {
                section.innerHTML = createTestItem('שגיאת תקשורת', 'error', error.message);
                updateSectionStatus('files', 'error');
            }
        }

        // בדיקת Google Auth
        async function testGoogle() {
            const section = document.getElementById('google-tests');
            section.innerHTML = '<div class="test-item"><span>בודק Google Authentication...</span></div>';
            
            try {
                const response = await fetch('test-server-api.php?test=google');
                const data = await response.json();
                
                let html = '';
                if (data.success) {
                    html += createTestItem('CLIENT_ID', data.client_id_exists ? 'success' : 'error', 
                        data.client_id_exists ? 'מוגדר' : 'חסר');
                    html += createTestItem('Google API Script', data.google_script_loaded ? 'success' : 'warning', 
                        data.google_script_loaded ? 'נטען' : 'לא נטען');
                    html += createTestItem('HTTPS', data.is_https ? 'success' : 'warning', 
                        data.is_https ? 'מאובטח' : 'לא מאובטח - חלק מהתכונות לא יעבדו');
                    
                    updateSectionStatus('google', data.client_id_exists ? 'success' : 'warning');
                } else {
                    html = createTestItem('שגיאה בבדיקת Google Auth', 'error', data.error);
                    updateSectionStatus('google', 'error');
                }
                
                section.innerHTML = html;
            } catch (error) {
                section.innerHTML = createTestItem('שגיאת תקשורת', 'error', error.message);
                updateSectionStatus('google', 'error');
            }
        }

        // פונקציות עזר
        function createTestItem(name, status, result) {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                loading: '⏳'
            };
            
            // עדכון סטטיסטיקות
            testResults.total++;
            if (status === 'success') testResults.passed++;
            else if (status === 'error') testResults.failed++;
            else if (status === 'warning') testResults.warnings++;
            
            return `
                <div class="test-item">
                    <span class="test-name">${name}</span>
                    <div class="test-result">
                        <span class="result-text">${result}</span>
                        <div class="icon icon-${status}">${icons[status]}</div>
                    </div>
                </div>
            `;
        }

        function updateSectionStatus(section, status) {
            const statusElement = document.getElementById(`${section}-status`);
            statusElement.className = `test-status status-${status}`;
            statusElement.textContent = status === 'success' ? 'תקין' : 
                                       status === 'error' ? 'שגיאה' : 
                                       status === 'warning' ? 'אזהרה' : 'בודק...';
        }

        function toggleContent(element) {
            const content = element.nextElementSibling;
            content.classList.toggle('show');
        }

        async function runAllTests() {
            // איפוס סטטיסטיקות
            testResults = {
                total: 0,
                passed: 0,
                failed: 0,
                warnings: 0,
                details: {}
            };
            
            // הסתרת סיכום
            document.getElementById('summary').style.display = 'none';
            
            // איפוס סטטוסים
            const sections = ['env', 'db', 'tables', 'api', 'files', 'google'];
            sections.forEach(section => {
                updateSectionStatus(section, 'testing');
                document.getElementById(`${section}-tests`).innerHTML = '<div class="test-item"><span>בודק...</span></div>';
            });
            
            // הרצת בדיקות
            await testEnv();
            await testDatabase();
            await testTables();
            await testAPIs();
            await testFiles();
            await testGoogle();
            
            // הצגת סיכום
            showSummary();
        }

        function showSummary() {
            document.getElementById('total-tests').textContent = testResults.total;
            document.getElementById('passed-tests').textContent = testResults.passed;
            document.getElementById('failed-tests').textContent = testResults.failed;
            document.getElementById('warning-tests').textContent = testResults.warnings;
            document.getElementById('summary').style.display = 'block';
            
            // גלילה לסיכום
            document.getElementById('summary').scrollIntoView({ behavior: 'smooth' });
        }

        function clearResults() {
            const sections = ['env', 'db', 'tables', 'api', 'files', 'google'];
            sections.forEach(section => {
                document.getElementById(`${section}-tests`).innerHTML = '';
                updateSectionStatus(section, 'testing');
            });
            document.getElementById('summary').style.display = 'none';
        }

        function exportResults() {
            const results = {
                timestamp: new Date().toISOString(),
                url: window.location.href,
                statistics: testResults,
                sections: {}
            };
            
            // אסוף תוצאות מכל הסקציות
            const sections = ['env', 'db', 'tables', 'api', 'files', 'google'];
            sections.forEach(section => {
                const sectionElement = document.getElementById(`${section}-tests`);
                results.sections[section] = sectionElement.innerHTML;
            });
            
            // הורדה כקובץ JSON
            const blob = new Blob([JSON.stringify(results, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `server-test-${Date.now()}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }

        // הרצה אוטומטית בטעינה
        window.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Starting server tests...');
            runAllTests();
        });
    </script>
</body>
</html>