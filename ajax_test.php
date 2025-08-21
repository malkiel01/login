<?php
// ajax_test.php - בדיקת AJAX פשוטה

// מניעת כל פלט לפני ה-JSON
ob_start();

// בדיקה אם זו בקשת POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ניקוי כל פלט קודם
    ob_clean();
    
    // הגדרת headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // תגובה פשוטה
    $response = [
        'success' => true,
        'message' => 'AJAX works!',
        'received_data' => $_POST,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    exit();
}

// אם זו לא בקשת POST, הצג דף בדיקה
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>בדיקת AJAX</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        #result {
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>בדיקת AJAX</h1>
    
    <button onclick="testSimpleAjax()">בדיקה פשוטה</button>
    <button onclick="testWithData()">בדיקה עם נתונים</button>
    <button onclick="testDirectUrl()">בדיקה ישירה</button>
    
    <div id="result"></div>

    <script>
        function showResult(data, isError = false) {
            const resultDiv = document.getElementById('result');
            resultDiv.className = isError ? 'error' : 'success';
            resultDiv.textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
        }

        function testSimpleAjax() {
            console.log('Starting simple AJAX test...');
            
            fetch('ajax_test.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'test=1'
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    showResult(data);
                } catch(e) {
                    showResult('Error parsing JSON: ' + e.message + '\n\nRaw response:\n' + text, true);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showResult('Fetch error: ' + error.message, true);
            });
        }

        function testWithData() {
            console.log('Starting AJAX test with data...');
            
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', 'test@example.com');
            formData.append('nickname', 'Test User');
            
            fetch('ajax_test.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    showResult(data);
                } catch(e) {
                    showResult('Error parsing JSON: ' + e.message + '\n\nRaw response:\n' + text, true);
                }
            })
            .catch(error => {
                showResult('Fetch error: ' + error.message, true);
            });
        }

        function testDirectUrl() {
            // בדיקה ישירה לכתובת המקורית
            const currentUrl = window.location.href;
            const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/'));
            const targetUrl = baseUrl + '/group.php?id=1';
            
            console.log('Testing direct URL:', targetUrl);
            
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', 'test@example.com');
            
            fetch(targetUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log('Raw response from group.php:', text);
                showResult('Response from group.php:\n' + text, text.includes('<!DOCTYPE'));
            })
            .catch(error => {
                showResult('Error: ' + error.message, true);
            });
        }
    </script>
</body>
</html>