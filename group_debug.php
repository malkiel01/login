<?php
// הפעלת דיווח שגיאות מלא
error_reporting(E_ALL);
ini_set('display_errors', 1);

// דיבאג - נבדוק אם זו בקשת AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// אם זו בקשת POST עם action, נרשום לוג
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // רישום לקובץ לוג
    $log_data = [
        'time' => date('Y-m-d H:i:s'),
        'action' => $_POST['action'],
        'is_ajax' => $is_ajax,
        'post_data' => $_POST,
        'headers' => getallheaders()
    ];
    
    error_log("=== GROUP DEBUG START ===\n" . print_r($log_data, true) . "\n=== GROUP DEBUG END ===\n", 3, "group_debug.log");
    
    // אם זו בקשת AJAX, נטפל בה ונצא מיד
    header('Content-Type: application/json');
    
    // תגובת בדיקה פשוטה
    echo json_encode([
        'debug' => true,
        'success' => true,
        'action' => $_POST['action'],
        'message' => 'Debug response - action received: ' . $_POST['action'],
        'post_data' => $_POST
    ]);
    
    exit(); // חשוב! יציאה מיידית
}

// אם הגענו לכאן, זו לא בקשת AJAX - נמשיך עם הקוד הרגיל
session_start();

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// בדיקת ID קבוצה
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';
$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$group_id = $_GET['id'];

// שאר הקוד...
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Group Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            direction: rtl;
        }
        .debug-info {
            background: #f0f0f0;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        #response {
            background: #e8f5e9;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            white-space: pre-wrap;
            display: none;
        }
        #response.error {
            background: #ffebee;
        }
    </style>
</head>
<body>
    <h1>דף בדיקת קבוצה - Debug Mode</h1>
    
    <div class="debug-info">
        <h3>מידע דיבאג:</h3>
        <p>Group ID: <?php echo htmlspecialchars($_GET['id'] ?? 'לא הוגדר'); ?></p>
        <p>User ID: <?php echo $_SESSION['user_id'] ?? 'לא מחובר'; ?></p>
        <p>Request Method: <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
    </div>

    <h2>בדיקת הוספת משתתף</h2>
    
    <button class="btn" onclick="testAddMember()">בדיקת הוספת משתתף (AJAX)</button>
    <button class="btn" onclick="testAddMemberFetch()">בדיקת הוספת משתתף (Fetch API)</button>
    <button class="btn" onclick="testAddMemberForm()">בדיקת הוספת משתתף (Form)</button>
    
    <div id="response"></div>

    <script>
        const groupId = <?php echo isset($_GET['id']) ? $_GET['id'] : 'null'; ?>;
        
        // בדיקה עם XMLHttpRequest
        function testAddMember() {
            console.log('Starting AJAX test...');
            
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            
            formData.append('action', 'addMember');
            formData.append('email', 'test@example.com');
            formData.append('nickname', 'Test User');
            formData.append('participation_type', 'percentage');
            formData.append('participation_value', '25');
            
            xhr.open('POST', 'group_debug.php?id=' + groupId, true);
            
            xhr.onload = function() {
                console.log('Response status:', xhr.status);
                console.log('Response headers:', xhr.getAllResponseHeaders());
                console.log('Response text:', xhr.responseText);
                
                const responseDiv = document.getElementById('response');
                responseDiv.style.display = 'block';
                
                try {
                    const data = JSON.parse(xhr.responseText);
                    responseDiv.className = '';
                    responseDiv.textContent = 'Success!\n' + JSON.stringify(data, null, 2);
                } catch(e) {
                    responseDiv.className = 'error';
                    responseDiv.textContent = 'Error parsing JSON:\n' + e.message + '\n\nRaw response:\n' + xhr.responseText;
                }
            };
            
            xhr.onerror = function() {
                console.error('XHR Error:', xhr.statusText);
            };
            
            xhr.send(formData);
        }
        
        // בדיקה עם Fetch API
        function testAddMemberFetch() {
            console.log('Starting Fetch API test...');
            
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', 'test@example.com');
            formData.append('nickname', 'Test User');
            formData.append('participation_type', 'percentage');
            formData.append('participation_value', '25');
            
            fetch('group_debug.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text(); // קודם נקבל כטקסט
            })
            .then(text => {
                console.log('Raw response:', text);
                
                const responseDiv = document.getElementById('response');
                responseDiv.style.display = 'block';
                
                try {
                    const data = JSON.parse(text);
                    responseDiv.className = '';
                    responseDiv.textContent = 'Success!\n' + JSON.stringify(data, null, 2);
                } catch(e) {
                    responseDiv.className = 'error';
                    responseDiv.textContent = 'Error parsing JSON:\n' + e.message + '\n\nRaw response:\n' + text;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                const responseDiv = document.getElementById('response');
                responseDiv.style.display = 'block';
                responseDiv.className = 'error';
                responseDiv.textContent = 'Fetch Error: ' + error.message;
            });
        }
        
        // בדיקה עם Form רגיל
        function testAddMemberForm() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'group_debug.php?id=' + groupId;
            
            const fields = {
                'action': 'addMember',
                'email': 'test@example.com',
                'nickname': 'Test User',
                'participation_type': 'percentage',
                'participation_value': '25'
            };
            
            for (let key in fields) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>