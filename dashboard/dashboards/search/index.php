<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש מותאם</title>
    <link rel="stylesheet" href="dashboards/search/assets/css/search.css">
    <link rel="stylesheet" href="dashboards/search/assets/css/custom-search.css">
    <link rel="stylesheet" href="dashboards/search/assets/css/modal.css">
</head>
<body>
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
            <button class="search-type-tab" data-search-type="available_graves">
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
        <!-- <p>מחפש...</p> -->
    </div>

    <!-- הוסף אחרי ה-loading div -->
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
    <script src="dashboards/search/assets/js/search-config.js"></script>
    <script src="dashboards/search/assets/templates/deceased-card.js"></script>
    <script src="dashboards/search/assets/templates/deceased-table.js"></script>
    <script src="dashboards/search/assets/templates/purchased-card.js"></script>
    <script src="dashboards/search/assets/templates/purchased-table.js"></script>
    <script src="dashboards/search/assets/templates/available-card.js"></script>
    <script src="dashboards/search/assets/templates/available-table.js"></script>
    <script src="dashboards/search/assets/js/search-api.js"></script>
    <script src="dashboards/search/assets/js/search-ui.js"></script>
    <script src="dashboards/search/assets/js/search-main.js"></script>
    <!-- <script src="dashboards/search/assets/js/modal.js"></script> -->
</body>
</html>