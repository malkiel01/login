<?php
    session_start();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];

    // שליפת נתוני המשתמש
    $stmt = $pdo->prepare("SELECT name, username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // פונקציית יצירת לוגו עיגול עם אות ראשונה
    function getAvatar($name) {
        $char = mb_substr($name, 0, 1, 'UTF-8');
        return strtoupper($char);
    }
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד ברירת מחדל</title>
    <link rel="stylesheet" href="/dashboard/dashboards/default/assets/css/default.css">
    <link rel="stylesheet" href="/dashboard/dashboards/default/assets/css_search/search.css">
    <link rel="stylesheet" href="/dashboard/dashboards/default/assets/css_search/custom-search.css">
    <link rel="stylesheet" href="/dashboard/dashboards/default/assets/css_search/modal.css">
</head>
<body>

    <!-- HEADER חדש -->
    <div class="mobile-header">
        <div class="header-right">
            <div class="user-avatar">
                <?php echo getAvatar($user['name'] ?? $user['username']); ?>
            </div>
            <div class="user-info-text">
                <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
        </div>

        <div class="header-left">
            <a href="/auth/logout.php" class="logout-btn">התנתק</a>
        </div>
    </div>

    <!-- בורר סוג חיפוש -->
    <div class="search-type-selector">
        <div class="search-type-tabs">
            <button class="search-type-tab active" data-search-type="deceased_search">
                <div class="icon">🪦</div>
                <div class="label">חיפוש נפטרים</div>
            </button>
            <button class="search-type-tab" data-search-type="purchased_graves">
                <div class="icon">💰</div>
                <div class="label">קברים שנרכשו</div>
            </button>
            <button class="search-type-tab" data-search-type="available_graves" disabled>
                <div class="icon">✅</div>
                <div class="label">קברים פנויים</div>
            </button>
        </div>
    </div>

    <!-- טאבים לבחירת סוג חיפוש -->
    <div class="search-tabs">
        <button class="search-tab active" data-tab="simple">
            <span class="tab-icon">⚡</span>
            <span class="tab-text">חיפוש מהיר</span>
        </button>
        <button class="search-tab" data-tab="advanced">
            <span class="tab-icon">🎯</span>
            <span class="tab-text">חיפוש מתקדם</span>
        </button>
    </div>

    <!-- תוכן הטאבים -->
    <div class="search-container">
        <div id="simple-tab" class="tab-content active"></div>
        <div id="advanced-tab" class="tab-content"></div>
    </div>

    <!-- תוצאות -->
    <div id="results-section" class="results-section" style="display: none;">
        <div class="results-header">
            <h2>תוצאות החיפוש</h2>
            <div class="results-info">
                <span>נמצאו <strong id="result-count">0</strong> תוצאות</span>
                <span>זמן חיפוש: <strong id="search-time">0</strong> שניות</span>
            </div>
            <div class="view-selector">
                <button class="view-btn active" data-view="cards">
                    <span>📇</span> כרטיסים
                </button>
                <button class="view-btn" data-view="table">
                    <span>📊</span> טבלה
                </button>
            </div>
        </div>
        <div id="results-container"></div>
    </div>

    <!-- טעינה -->
    <div id="loading" class="loading" style="display: none;">
        <div class="spinner"></div>
    </div>

    <!-- מודאל לפרטי נפטר -->
    <div id="deceased-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div id="modal-body">
                <!-- התוכן יוזרק כאן דינמית -->
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/dashboard/dashboards/default/assets/js/search-config.js"></script>
    <script src="/dashboard/dashboards/default/assets/templates/deceased-card.js"></script>
    <script src="/dashboard/dashboards/default/assets/templates/deceased-table.js"></script>
    <script src="/dashboard/dashboards/default/assets/templates/purchased-card.js"></script>
    <script src="/dashboard/dashboards/default/assets/templates/purchased-table.js"></script>
    <script src="/dashboard/dashboards/default/assets/templates/available-card.js"></script>
    <script src="/dashboard/dashboards/default/assets/templates/available-table.js"></script>
    <script src="/dashboard/dashboards/default/assets/js/search-api.js"></script>
    <script src="/dashboard/dashboards/default/assets/js/search-ui.js"></script>
    <script src="/dashboard/dashboards/default/assets/js/search-main.js"></script>
    <script src="/dashboard/dashboards/default/assets/js/modal.js"></script>
</body>
</html>
