<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מערכת חיפוש מתקדמת</title>
    
    <!-- CSS Files -->
    <!-- <link rel="stylesheet" href="dashboards/search/assets/css/search.css"> -->
    <link rel="stylesheet" href="dashboards/search/assets/css/custom-search.css">
</head>
<body>
    <div class="main-container">
        <!-- כותרת ראשית -->
        <header class="main-header">
            <h1>מערכת חיפוש מתקדמת</h1>
        </header>

        <!-- לשוניות סוג חיפוש - גרופ כפתורים -->
        <div class="search-type-wrapper">
            <div class="search-type-group">
                <button class="type-btn active" data-type="deceased_search" onclick="searchApp.switchSearchType('deceased_search')">
                    נפטרים
                </button>
                <button class="type-btn" data-type="purchased_graves" onclick="searchApp.switchSearchType('purchased_graves')">
                    רכישות
                </button>
                <button class="type-btn" data-type="available_graves" onclick="searchApp.switchSearchType('available_graves')">
                    פנויים
                </button>
            </div>
        </div>

        <!-- אזור החיפוש -->
        <div class="search-area">
            <!-- טאבים של סוג החיפוש -->
            <div class="search-mode-tabs">
                <button class="mode-tab active" data-mode="simple" onclick="searchApp.switchMode('simple')">
                    חיפוש מהיר
                </button>
                <button class="mode-tab" data-mode="advanced" onclick="searchApp.switchMode('advanced')">
                    חיפוש מתקדם
                </button>
            </div>

            <!-- תוכן החיפוש -->
            <div class="search-content">
                <!-- חיפוש מהיר -->
                <div id="simple-search" class="search-panel active">
                    <div class="simple-search-box">
                        <input type="text" 
                               id="simple-query" 
                               class="search-input" 
                               placeholder="הקלד טקסט לחיפוש..."
                               onkeypress="if(event.key === 'Enter') searchApp.performSimpleSearch()">
                        <button class="search-btn" onclick="searchApp.performSimpleSearch()">
                            חפש
                        </button>
                    </div>
                </div>

                <!-- חיפוש מתקדם -->
                <div id="advanced-search" class="search-panel">
                    <div id="advanced-fields" class="advanced-fields">
                        <!-- השדות יתווספו דינמית מהקונפיגורציה -->
                    </div>
                    <div class="search-actions">
                        <button class="btn-primary" onclick="searchApp.performAdvancedSearch()">
                            בצע חיפוש
                        </button>
                        <button class="btn-secondary" onclick="searchApp.clearAdvancedForm()">
                            נקה טופס
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- תוצאות -->
        <div id="results-section" class="results-section" style="display: none;">
            <div class="results-header">
                <h2>תוצאות החיפוש</h2>
                <div class="results-meta">
                    <span>נמצאו: <strong id="result-count">0</strong></span>
                    <span>זמן: <strong id="search-time">0</strong> שניות</span>
                </div>
            </div>
            
            <div id="results-container">
                <!-- התוצאות יוצגו כאן דרך search-results-table.js -->
            </div>
        </div>

        <!-- טעינה -->
        <div id="loading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p>מחפש...</p>
        </div>

        <!-- הודעות -->
        <div id="toast-container" class="toast-container"></div>
    </div>

    <!-- JavaScript Files - חיבור לכל הקבצים -->
    <script src="dashboards/search/assets/js/search-config.js"></script>
    <script src="dashboards/search/assets/js/search-results-table.js"></script>
    <script src="dashboards/search/assets/js/custom-search-app.js"></script>
    
    <!-- Initialize -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // בדיקה שכל הקבצים נטענו
            if (typeof SearchApp === 'undefined' || 
                typeof SearchConfig === 'undefined' || 
                typeof ResultsTable === 'undefined') {
                console.error('Missing required files');
                alert('שגיאה בטעינת קבצי המערכת');
                return;
            }
            
            // יצירת האפליקציה
            window.searchApp = new SearchApp();
            window.searchApp.init();
        });
    </script>
</body>
</html>