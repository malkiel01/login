<?php
header("Location: search/index.php");
exit();
?>

<!-- <!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
        }
        
        .dashboard-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .dashboard-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        p {
            color: #666;
            line-height: 1.6;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .logout-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="dashboard-box">
        <div class="dashboard-icon">🏠</div>
        <h1>דשבורד ברירת מחדל</h1>
        <p>זהו דשבורד בסיסי. פנה למנהל המערכת כדי לקבל הרשאות נוספות.</p>
        
        <div class="user-info">
            <strong>משתמש מחובר:</strong><br>
            < ?php echo htmlspecialchars($_SESSION['username'] ?? 'משתמש'); ?><br>
            <small>ID: < ?php echo $_SESSION['user_id']; ?></small>
        </div>
        
        <a href="/auth/logout.php" class="logout-btn">יציאה</a>
    </div>
</body>
</html> -->