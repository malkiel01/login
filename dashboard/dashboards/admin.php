<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד מנהל מערכת</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container admin-dashboard">
        <header class="dashboard-header">
            <h1>👨‍💼 דשבורד מנהל מערכת</h1>
            <div class="user-info">
                שלום, <?php echo $_SESSION['user_name'] ?? 'מנהל'; ?>
            </div>
        </header>
        
        <main class="dashboard-content">
            <div class="dashboard-card">
                <h2>ניהול מערכת</h2>
                <p>גישה מלאה לכל המערכת: ניהול משתמשים, הגדרות מערכת, דוחות מתקדמים, ניטור ביצועים וניהול הרשאות.</p>
                
                <div class="admin-features">
                    <a href="permissions/manage.php" class="feature-link">
                        ⚙️ ניהול הרשאות
                    </a>
                    <a href="#" class="feature-link">
                        👥 ניהול משתמשים
                    </a>
                    <a href="#" class="feature-link">
                        📊 דוחות מערכת
                    </a>
                    <a href="#" class="feature-link">
                        🔧 הגדרות
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>