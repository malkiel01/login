<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש נפטרים - מערכת ניהול בית עלמין</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="dashboards/search/assets/css/search.css">
    <link rel="stylesheet" href="dashboards/search/assets/css/animations.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <h1>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                חיפוש נפטרים
            </h1>
            <div class="subtitle">מערכת חיפוש מתקדמת לאיתור נפטרים בבתי העלמין</div>
            
            <!-- Data Source Toggle -->
            <div class="data-source-toggle">
                <label class="toggle-label">מקור נתונים:</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="dataSourceToggle" onchange="toggleDataSource()">
                    <label for="dataSourceToggle" class="toggle-slider">
                        <span class="toggle-text json">JSON</span>
                        <span class="toggle-text api">API</span>
                    </label>
                </div>
                <span id="currentSource" class="current-source">JSON (בדיקות)</span>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-tabs">
                <button class="tab-button active" onclick="switchTab('simple')">
                    🔍 חיפוש מהיר
                </button>
                <button class="tab-button" onclick="switchTab('advanced')">
                    ⚙️ חיפוש מתקדם
                </button>
            </div>

            <!-- Simple Search -->
            <div id="simple-search" class="search-content active">
                <div class="simple-search">
                    <div class="search-input-wrapper">
                        <input type="text" 
                               id="simple-query" 
                               class="search-input" 
                               placeholder="הקלד שם פרטי ו/או שם משפחה..."
                               onkeypress="if(event.key === 'Enter') performSimpleSearch()">
                        <button class="search-button" onclick="performSimpleSearch()">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="search-hints">
                        💡 ניתן לחפש בכל סדר: "משה כהן" או "כהן משה"
                    </div>
                </div>
            </div>

            <!-- Advanced Search -->
            <div id="advanced-search" class="search-content">
                <div class="advanced-search">
                    <!-- פרטים אישיים -->
                    <div class="form-section">
                        <div class="section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            פרטים אישיים
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">שם פרטי</label>
                                <input type="text" id="adv-first-name" class="form-input" placeholder="לדוגמה: משה חיים">
                            </div>
                            <div class="form-group">
                                <label class="form-label">שם משפחה</label>
                                <input type="text" id="adv-last-name" class="form-input" placeholder="לדוגמה: כהן">
                            </div>
                            <div class="form-group">
                                <label class="form-label">שם האב</label>
                                <input type="text" id="adv-father-name" class="form-input" placeholder="לדוגמה: אברהם">
                            </div>
                            <div class="form-group">
                                <label class="form-label">שם האם</label>
                                <input type="text" id="adv-mother-name" class="form-input" placeholder="לדוגמה: שרה">
                            </div>
                        </div>
                    </div>

                    <!-- תאריך פטירה -->
                    <div class="form-section">
                        <div class="section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            תאריך פטירה
                        </div>
                        
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="date-none" name="date-type" value="none" checked>
                                <label for="date-none">ללא תאריך</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="date-range" name="date-type" value="range">
                                <label for="date-range">טווח תאריכים</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="date-estimated" name="date-type" value="estimated">
                                <label for="date-estimated">שנה משוערת</label>
                            </div>
                        </div>

                        <div id="date-range-fields" class="form-grid" style="display: none;">
                            <div class="date-range-group">
                                <div class="form-group">
                                    <label class="form-label">משנה</label>
                                    <input type="number" id="adv-from-year" class="form-input" placeholder="שנה" min="1900" max="2025">
                                </div>
                                <div class="date-separator">עד</div>
                                <div class="form-group">
                                    <label class="form-label">עד שנה</label>
                                    <input type="number" id="adv-to-year" class="form-input" placeholder="שנה" min="1900" max="2025">
                                </div>
                            </div>
                        </div>

                        <div id="date-estimated-fields" class="form-grid" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">שנה משוערת</label>
                                <input type="number" id="adv-estimated-year" class="form-input" placeholder="לדוגמה: 2020" min="1900" max="2025">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="year-range-5" checked disabled>
                                    <label for="year-range-5">חיפוש בטווח של ±5 שנים</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- מיקום -->
                    <div class="form-section">
                        <div class="section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            מיקום קבורה
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">עיר</label>
                                <select id="adv-city" class="form-select">
                                    <option value="">בחר עיר</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">בית עלמין</label>
                                <select id="adv-cemetery" class="form-select">
                                    <option value="">בחר בית עלמין</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="clear-button" onclick="clearAdvancedForm()">
                            נקה טופס
                        </button>
                        <button class="submit-button" onclick="performAdvancedSearch()">
                            🔍 חפש נפטרים
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div id="results-section" class="results-section">
            <div class="results-header">
                <h2>תוצאות חיפוש</h2>
                <div class="results-info">
                    <span class="results-count">נמצאו <span id="results-count">0</span> תוצאות</span>
                    <span id="search-time" class="search-time"></span>
                </div>
            </div>
            
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>מחפש במאגר הנתונים...</p>
            </div>

            <div id="results-grid" class="results-grid"></div>
            
            <div id="no-results" class="no-results" style="display: none;">
                <div class="no-results-icon">🔍</div>
                <h3>לא נמצאו תוצאות</h3>
                <p>נסה לשנות את פרטי החיפוש או להשתמש בפחות מסננים</p>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script>
        console.log('=== Starting to load scripts ===');
        console.log('Current path:', window.location.pathname);
    </script>

    <script src="/dashboard/dashboards/search/assets/js/config.js" 
            onerror="console.error('❌ Failed to load config.js');" 
            onload="console.log('✅ config.js loaded');"></script>

    <script src="/dashboard/dashboards/search/assets/js/search-algorithms.js"
            onerror="console.error('❌ Failed to load search-algorithms.js');"
            onload="console.log('✅ search-algorithms.js loaded');"></script>

    <script src="/dashboard/dashboards/search/assets/js/data-service.js"
            onerror="console.error('❌ Failed to load data-service.js');"
            onload="console.log('✅ data-service.js loaded');"></script>

    <script src="/dashboard/dashboards/search/assets/js/ui-controller.js"
            onerror="console.error('❌ Failed to load ui-controller.js');"
            onload="console.log('✅ ui-controller.js loaded');"></script>

    <script src="/dashboard/dashboards/search/assets/js/main.js"
            onerror="console.error('❌ Failed to load main.js');"
            onload="console.log('✅ main.js loaded');"></script>

    <script>
        // Check if everything loaded after 2 seconds
        setTimeout(function() {
            console.log('=== Final check after 2 seconds ===');
            console.log('window.dataService exists:', typeof window.dataService !== 'undefined');
            console.log('window.uiController exists:', typeof window.uiController !== 'undefined');
            
            if (typeof window.dataService === 'undefined') {
                console.error('dataService is missing - check if data-service.js loaded correctly');
            }
            if (typeof window.uiController === 'undefined') {
                console.error('uiController is missing - check if ui-controller.js loaded correctly');
            }
        }, 2000);
    </script>
</body>
</html>