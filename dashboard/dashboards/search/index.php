<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש מותאם - קברים שנרכשו</title>
    <link rel="stylesheet" href="dashboards/search/assets/css/search.css">
    <style>
        /* כרטיסיות סוג חיפוש - עיצוב מותאם למובייל */
        .search-type-selector {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .search-type-selector h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .search-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }
        
        .search-type-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px 10px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            min-height: 80px;
            justify-content: center;
        }
        
        .search-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .search-type-card.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .search-type-card .icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .search-type-card .label {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* טאבים - עיצוב מותאם למובייל */
        .search-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
            background: #f5f5f5;
            padding: 4px;
            border-radius: 10px;
        }
        
        .search-tab {
            padding: 12px 10px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 44px;
        }
        
        .search-tab .tab-icon {
            font-size: 18px;
        }
        
        .search-tab .tab-text {
            white-space: nowrap;
        }
        
        .search-tab:hover {
            background: rgba(255,255,255,0.5);
        }
        
        .search-tab.active {
            background: white;
            color: #4a90e2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* שדה חיפוש מהיר - תיקון כפתור */
        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 50px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        .search-button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border: none;
            background: #4a90e2;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .search-button:hover {
            background: #357abd;
        }
        
        .search-button:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        /* תוכן הטאבים */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* שדות בחיפוש מתקדם */
        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        /* כפתורי פעולה */
        .submit-button, .clear-button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        
        .submit-button {
            background: #4a90e2;
            color: white;
        }
        
        .submit-button:hover {
            background: #357abd;
        }
        
        .clear-button {
            background: #f5f5f5;
            color: #666;
        }
        
        .clear-button:hover {
            background: #e0e0e0;
        }
        
        /* טבלת תוצאות - מותאמת למובייל */
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .result-table th,
        .result-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .result-table th {
            background: #f5f5f5;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .result-table tr:hover {
            background: #f9f9f9;
        }
        
        /* התאמות למובייל */
        @media (max-width: 768px) {
            .search-type-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .search-tab {
                font-size: 13px;
                padding: 10px 5px;
            }
            
            .search-tab .tab-icon {
                font-size: 16px;
            }
            
            .search-tab .tab-text {
                font-size: 13px;
            }
            
            .field-grid {
                grid-template-columns: 1fr;
            }
            
            .result-table {
                font-size: 14px;
            }
            
            .result-table th,
            .result-table td {
                padding: 8px;
            }
            
            /* גלילה אופקית לטבלה במובייל */
            .results-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        @media (max-width: 480px) {
            .search-type-selector h3 {
                font-size: 16px;
            }
            
            .search-type-card {
                min-height: 70px;
                padding: 12px 8px;
            }
            
            .search-type-card .icon {
                font-size: 20px;
            }
            
            .search-type-card .label {
                font-size: 12px;
            }
            
            .submit-button, .clear-button {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- בורר סוג חיפוש - כרטיסיות -->
    <div class="search-type-selector">
        <h3>בחר סוג חיפוש:</h3>
        <div class="search-type-cards">
            <div class="search-type-card active" onclick="switchSearchType('standard')">
                <div class="icon">📋</div>
                <div class="label">חיפוש סטנדרטי</div>
            </div>
            <div class="search-type-card" onclick="switchSearchType('deceased_search')">
                <div class="icon">🪦</div>
                <div class="label">חיפוש נפטרים</div>
            </div>
            <div class="search-type-card" onclick="switchSearchType('purchased_graves')">
                <div class="icon">💰</div>
                <div class="label">קברים שנרכשו</div>
            </div>
            <div class="search-type-card" onclick="switchSearchType('available_graves')">
                <div class="icon">✅</div>
                <div class="label">קברים פנויים</div>
            </div>
        </div>
    </div>

    <!-- טאבים לבחירת סוג חיפוש -->
    <div class="search-tabs">
        <button class="search-tab active" onclick="switchTab('simple')">
            <span class="tab-icon">⚡</span>
            <span class="tab-text">חיפוש מהיר</span>
        </button>
        <button class="search-tab" onclick="switchTab('advanced')">
            <span class="tab-icon">🎯</span>
            <span class="tab-text">חיפוש מתקדם</span>
        </button>
    </div>

    <!-- תוכן הטאבים -->
    <div class="search-container">
        <!-- טאב חיפוש מהיר -->
        <div id="simple-tab" class="tab-content active">
            <div class="search-section">
                <h2>חיפוש מהיר</h2>
                <p style="color: #666; margin-bottom: 20px;">הקלד טקסט חופשי לחיפוש בכל השדות הרלוונטיים</p>
                <div class="search-wrapper">
                    <input type="text" 
                           id="simple-query" 
                           class="search-input" 
                           placeholder="הקלד שם, מספר קבר, בית עלמין..."
                           onkeypress="if(event.key === 'Enter') performConfigurableSearch()">
                    <button class="search-button" onclick="performConfigurableSearch()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- טאב חיפוש מתקדם -->
        <div id="advanced-tab" class="tab-content">
            <div class="search-section">
                <h2>חיפוש מתקדם</h2>
                <p style="color: #666; margin-bottom: 20px;">חפש לפי שדות ספציפיים לתוצאות מדויקות יותר</p>
                <div id="advanced-fields" class="field-grid">
                    <!-- השדות יתווספו דינמית לפי סוג החיפוש -->
                </div>
                <div style="margin-top: 20px;">
                    <button class="submit-button" onclick="performAdvancedConfigurableSearch()">
                        חפש
                    </button>
                    <button class="clear-button" onclick="clearAdvancedForm()">
                        נקה
                    </button>
                </div>
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

    <!-- Scripts -->
    <!-- טוען את קובץ הקונפיגורציה החיצוני -->
    <script src="dashboards/search/assets/js/search-config.js"></script>
    
    <script>
        let currentSearch = null;
        let currentSearchType = 'standard';
        let currentTab = 'simple';
        
        // אתחול
        document.addEventListener('DOMContentLoaded', function() {
            // בדיקה שהקונפיגורציה נטענה
            if (typeof ConfigurableSearch === 'undefined' || typeof SearchConfig === 'undefined') {
                console.error('Configuration file not loaded!');
                alert('שגיאה בטעינת קובץ הקונפיגורציה. נא לרענן את הדף.');
                return;
            }
            
            // אתחול עם סוג החיפוש הראשוני
            initializeSearch('purchased_graves');
        });
        
        /**
         * החלפת טאב
         */
        function switchTab(tabName) {
            currentTab = tabName;
            
            // עדכון כפתורי הטאבים
            document.querySelectorAll('.search-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // עדכון תוכן הטאבים
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }
        
        // אתחול
        document.addEventListener('DOMContentLoaded', function() {
            // בדיקה שהקונפיגורציה נטענה
            if (typeof ConfigurableSearch === 'undefined' || typeof SearchConfig === 'undefined') {
                console.error('Configuration file not loaded!');
                alert('שגיאה בטעינת קובץ הקונפיגורציה. נא לרענן את הדף.');
                return;
            }
            
            // אתחול עם סוג החיפוש הראשוני
            initializeSearch('purchased_graves');
        });
        
        /**
         * אתחול החיפוש
         */
        function initializeSearch(searchType) {
            currentSearchType = searchType;
            
            try {
                currentSearch = new ConfigurableSearch(searchType);
                updateAdvancedFields();
            } catch (error) {
                console.error('Error initializing search:', error);
                alert('שגיאה באתחול החיפוש: ' + error.message);
            }
        }
        
        /**
         * החלפת סוג חיפוש
         */
        function switchSearchType(searchType) {
            currentSearchType = searchType;
            
            try {
                currentSearch = new ConfigurableSearch(searchType);
                
                // עדכון כפתורים
                if (event && event.target) {
                    document.querySelectorAll('.search-type-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    event.target.classList.add('active');
                }
                
                // עדכון שדות מתקדמים
                updateAdvancedFields();
                
                // ניקוי תוצאות
                document.getElementById('results-section').style.display = 'none';
            } catch (error) {
                console.error('Error switching search type:', error);
                alert('שגיאה בהחלפת סוג החיפוש: ' + error.message);
            }
        }
        
        /**
         * עדכון שדות החיפוש המתקדם
         */
        function updateAdvancedFields() {
            const container = document.getElementById('advanced-fields');
            container.innerHTML = '';
            
            const fields = currentSearch.config.searchFields.advanced;
            const displayLabels = currentSearch.getDisplayLabels();
            
            // קיבוץ שדות לפי קטגוריות (אם זה חיפוש נפטרים)
            if (currentSearchType === 'deceased_search') {
                // פרטי הנפטר
                const personalSection = document.createElement('div');
                personalSection.innerHTML = '<h4 style="margin-bottom: 10px;">👤 פרטי הנפטר:</h4>';
                personalSection.className = 'field-section';
                
                const personalGrid = document.createElement('div');
                personalGrid.className = 'field-grid';
                
                ['firstName', 'lastName', 'fatherName', 'motherName'].forEach(key => {
                    if (fields[key]) {
                        const fieldDiv = createFieldElement(key, fields[key], displayLabels);
                        personalGrid.appendChild(fieldDiv);
                    }
                });
                personalSection.appendChild(personalGrid);
                container.appendChild(personalSection);
                
                // מיקום הקבר
                const locationSection = document.createElement('div');
                locationSection.innerHTML = '<h4 style="margin-top: 20px; margin-bottom: 10px;">📍 מיקום הקבר:</h4>';
                locationSection.className = 'field-section';
                
                const locationGrid = document.createElement('div');
                locationGrid.className = 'field-grid';
                
                ['cemeteryName', 'blockName', 'plotName', 'areaName', 'lineName', 'graveName'].forEach(key => {
                    if (fields[key]) {
                        const fieldDiv = createFieldElement(key, fields[key], displayLabels);
                        locationGrid.appendChild(fieldDiv);
                    }
                });
                locationSection.appendChild(locationGrid);
                container.appendChild(locationSection);
                
                // תאריכים
                const datesSection = document.createElement('div');
                datesSection.innerHTML = '<h4 style="margin-top: 20px; margin-bottom: 10px;">📅 תאריכים:</h4>';
                datesSection.className = 'field-section';
                
                const datesGrid = document.createElement('div');
                datesGrid.className = 'field-grid';
                
                ['deathDate', 'burialDate'].forEach(key => {
                    if (fields[key]) {
                        const fieldDiv = createFieldElement(key, fields[key], displayLabels, 'date');
                        datesGrid.appendChild(fieldDiv);
                    }
                });
                datesSection.appendChild(datesGrid);
                container.appendChild(datesSection);
                
            } else {
                // לשאר סוגי החיפוש - הצגה רגילה
                for (const [key, dbField] of Object.entries(fields)) {
                    const fieldDiv = createFieldElement(key, dbField, displayLabels);
                    container.appendChild(fieldDiv);
                }
            }
        }
        
        /**
         * יצירת אלמנט שדה
         */
        function createFieldElement(key, dbField, displayLabels, type = 'text') {
            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'form-group';
            
            const label = displayLabels[dbField] || key;
            const inputType = type === 'date' ? 'date' : 'text';
            const placeholder = type === 'date' ? '' : `הקלד ${label}...`;
            
            fieldDiv.innerHTML = `
                <label class="form-label">${label}</label>
                <input type="${inputType}" 
                       id="adv-${key}" 
                       class="form-input" 
                       placeholder="${placeholder}">
            `;
            
            return fieldDiv;
        }
        
        /**
         * חיפוש פשוט
         */
        async function performConfigurableSearch() {
            const query = document.getElementById('simple-query').value.trim();
            
            if (!query || query.length < SearchConfig.settings.minSearchLength) {
                alert(`יש להזין לפחות ${SearchConfig.settings.minSearchLength} תווים לחיפוש`);
                return;
            }
            
            showLoading(true);
            
            try {
                const results = await searchWithConfig(query, 'simple');
                displayConfigurableResults(results);
            } catch (error) {
                console.error('Search error:', error);
                alert('אירעה שגיאה בחיפוש');
            } finally {
                showLoading(false);
            }
        }
        
        /**
         * חיפוש מתקדם
         */
        async function performAdvancedConfigurableSearch() {
            const params = {};
            const fields = currentSearch.config.searchFields.advanced;
            
            // איסוף ערכים מהשדות
            for (const key of Object.keys(fields)) {
                const input = document.getElementById(`adv-${key}`);
                if (input && input.value.trim()) {
                    params[key] = input.value.trim();
                }
            }
            
            if (Object.keys(params).length === 0) {
                alert('יש למלא לפחות שדה אחד');
                return;
            }
            
            showLoading(true);
            
            try {
                const results = await searchWithConfig(params, 'advanced');
                displayConfigurableResults(results);
            } catch (error) {
                console.error('Advanced search error:', error);
                alert('אירעה שגיאה בחיפוש');
            } finally {
                showLoading(false);
            }
        }
        
        /**
         * טעינת נתוני JSON
         */
        async function loadJSONData() {
            try {
                const response = await fetch('/dashboard/dashboards/search/data/data.json');
                if (!response.ok) {
                    throw new Error('Failed to load JSON data');
                }
                const data = await response.json();
                console.log('JSON data loaded:', data.length, 'records');
                return data;
            } catch (error) {
                console.error('Error loading JSON:', error);
                return [];
            }
        }
        
        /**
         * חיפוש ב-JSON באמצעות המחלקה מהקונפיג
         */
        function searchInJSON(data, searchParams, searchMode) {
            let results = [];
            
            if (searchMode === 'simple') {
                // שימוש במתודה simpleSearch מהמחלקה
                results = currentSearch.simpleSearch(searchParams.query, data);
            } else {
                // שימוש במתודה advancedSearch מהמחלקה
                results = currentSearch.advancedSearch(searchParams, data);
            }
            
            // עיצוב התוצאות באמצעות המתודה formatResults
            const formattedResults = currentSearch.formatResults(results);
            
            console.log(`Found ${formattedResults.length} results for ${currentSearchType}`);
            return formattedResults;
        }
        
        /**
         * ביצוע חיפוש עם קונפיגורציה
         */
        async function searchWithConfig(queryOrParams, searchMode) {
            // בדיקה שיש חיפוש פעיל
            if (!currentSearch) {
                throw new Error('No search configuration loaded');
            }
            
            const startTime = performance.now();
            
            try {
                // טעינת נתוני JSON
                const jsonData = await loadJSONData();
                
                const searchParams = searchMode === 'simple' 
                    ? { query: queryOrParams }
                    : queryOrParams;
                
                const results = searchInJSON(jsonData, searchParams, searchMode);
                
                const endTime = performance.now();
                const searchTime = ((endTime - startTime) / 1000).toFixed(2);
                
                console.log(`Search completed: ${results.length} results in ${searchTime}s`);
                
                // עדכון זמן החיפוש בממשק
                const searchTimeEl = document.getElementById('search-time');
                if (searchTimeEl) {
                    searchTimeEl.textContent = searchTime;
                }
                
                return {
                    success: true,
                    results: results,
                    searchTime: searchTime
                };
                
            } catch (error) {
                console.error('Search error:', error);
                return {
                    success: false,
                    results: [],
                    error: error.message
                };
            }
        }
        
        /**
         * הצגת תוצאות
         */
        function displayConfigurableResults(data) {
            const resultsSection = document.getElementById('results-section');
            const resultsContainer = document.getElementById('results-container');
            const resultCount = document.getElementById('result-count');
            
            resultsSection.style.display = 'block';
            resultCount.textContent = data.results ? data.results.length : 0;
            
            if (!data.results || data.results.length === 0) {
                resultsContainer.innerHTML = '<p>לא נמצאו תוצאות</p>';
                return;
            }
            
            // יצירת טבלת תוצאות
            const table = document.createElement('table');
            table.className = 'result-table';
            
            // כותרות
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            const displayLabels = currentSearch.getDisplayLabels();
            
            for (const field of currentSearch.config.returnFields) {
                const th = document.createElement('th');
                th.textContent = displayLabels[field] || field;
                headerRow.appendChild(th);
            }
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // נתונים
            const tbody = document.createElement('tbody');
            for (const record of data.results) {
                const row = document.createElement('tr');
                
                for (const field of currentSearch.config.returnFields) {
                    const td = document.createElement('td');
                    
                    // בדיקה אם יש תצוגה מתורגמת (לסטטוסים)
                    if (record[field + '_display']) {
                        td.textContent = record[field + '_display'];
                    } else {
                        td.textContent = record[field] || '-';
                    }
                    
                    row.appendChild(td);
                }
                
                tbody.appendChild(row);
            }
            table.appendChild(tbody);
            
            resultsContainer.innerHTML = '';
            resultsContainer.appendChild(table);
        }
        
        /**
         * ניקוי טופס
         */
        function clearAdvancedForm() {
            document.querySelectorAll('#advanced-fields input').forEach(input => {
                input.value = '';
            });
        }
        
        /**
         * הצגת/הסתרת טעינה
         */
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'flex' : 'none';
        }
    </script>
</body>
</html>