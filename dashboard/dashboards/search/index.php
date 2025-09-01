<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מערכת חיפוש מתקדמת</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="dashboards/search/assets/css/search.css">
    <link rel="stylesheet" href="dashboards/search/assets/css/custom-search.css">
</head>
<body>
    <!-- בורר סוג חיפוש -->
    <div class="search-type-selector">
        <h2>בחר סוג חיפוש</h2>
        <div class="search-type-cards">
            <div class="search-type-card" onclick="searchApp.switchSearchType('deceased_search')">
                <div class="card-icon">🪦</div>
                <div class="card-title">חיפוש נפטרים</div>
                <div class="card-description">חיפוש פרטי נפטרים ומיקום קבורה</div>
            </div>
            <div class="search-type-card" onclick="searchApp.switchSearchType('purchased_graves')">
                <div class="card-icon">💰</div>
                <div class="card-title">חיפוש רכישות</div>
                <div class="card-description">קברים שנרכשו ופרטי רכישה</div>
            </div>
            <div class="search-type-card" onclick="searchApp.switchSearchType('available_graves')">
                <div class="card-icon">✅</div>
                <div class="card-title">קברים פנויים</div>
                <div class="card-description">קברים זמינים לרכישה</div>
            </div>
        </div>
    </div>

    <!-- כרטיסיות חיפוש -->
    <div class="search-container">
        <div class="search-tabs">
            <button class="search-tab active" onclick="searchApp.switchTab('simple')">
                חיפוש מהיר
            </button>
            <button class="search-tab" onclick="searchApp.switchTab('advanced')">
                חיפוש מתקדם
            </button>
        </div>
        
        <!-- חיפוש פשוט -->
        <div id="simple-search" class="search-content active">
            <div class="search-input-wrapper">
                <input type="text" 
                       id="simple-query" 
                       class="search-input" 
                       placeholder="הקלד טקסט לחיפוש..."
                       onkeypress="if(event.key === 'Enter') searchApp.performSimpleSearch()">
                <button class="search-button" onclick="searchApp.performSimpleSearch()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- חיפוש מתקדם -->
        <div id="advanced-search" class="search-content">
            <div id="advanced-fields" class="field-grid">
                <!-- השדות יתווספו דינמית לפי סוג החיפוש -->
            </div>
            <div class="form-actions">
                <button class="submit-button" onclick="searchApp.performAdvancedSearch()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    חפש
                </button>
                <button class="clear-button" onclick="searchApp.clearAdvancedForm()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6M14 11v6"/>
                    </svg>
                    נקה
                </button>
            </div>
        </div>
    </div>

    <!-- תוצאות -->
    <div id="results-section" class="results-section" style="display: none;">
        <div class="results-header">
            <h2>תוצאות החיפוש</h2>
            <div class="results-info">
                <span>נמצאו <strong id="result-count">0</strong> תוצאות</span>
                <span>זמן חיפוש: <strong id="search-time">0</strong> שניות</span>
                <span>מקור: <strong id="result-source">-</strong></span>
            </div>
            <div class="results-actions">
                <button onclick="searchApp.exportToExcel()" class="export-btn">
                    ייצוא לאקסל
                </button>
                <button onclick="searchApp.printResults()" class="print-btn">
                    הדפסה
                </button>
            </div>
        </div>
        
        <div id="results-container">
            <!-- התוצאות יוצגו כאן -->
        </div>
    </div>

    <!-- טעינה -->
    <div id="loading" class="loading" style="display: none;">
        <div class="spinner"></div>
        <p>מחפש...</p>
    </div>

    <!-- הודעות -->
    <div id="toast-container" class="toast-container"></div>

    <!-- JavaScript Files -->
    <script src="dashboards/search/assets/js/search-config.js"></script>
    <script src="dashboards/search/assets/js/search-results-table.js"></script>
    <script src="dashboards/search/assets/js/custom-search-app.js"></script>
    
    <!-- Initialize -->
    <script>
        // אתחול האפליקציה
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SearchApp === 'undefined') {
                document.body.innerHTML = `
                    <div style="text-align: center; padding: 50px;">
                        <h1>שגיאה בטעינת המערכת</h1>
                        <p>אחד או יותר מקבצי המערכת לא נטענו כראוי</p>
                        <p>וודא שהקבצים הבאים קיימים:</p>
                        <ul style="list-style: none;">
                            <li>dashboards/search/assets/js/search-config.js</li>
                            <li>dashboards/search/assets/js/search-results-table.js</li>
                            <li>dashboards/search/assets/js/custom-search-app.js</li>
                        </ul>
                    </div>
                `;
                return;
            }
            
            // יצירת instance של האפליקציה
            window.searchApp = new SearchApp();
            window.searchApp.init();
        });
    </script>
</body>
</html>