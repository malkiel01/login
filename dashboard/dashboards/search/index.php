<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש מותאם - קברים שנרכשו</title>
    <link rel="stylesheet" href="assets/css/search.css">
    <style>
        .search-type-selector {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .search-type-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-type-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .search-type-btn.active {
            background: #4a90e2;
            color: white;
            border-color: #4a90e2;
        }
        
        .filter-info {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #4a90e2;
        }
        
        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
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
        }
        
        .result-table tr:hover {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <!-- בורר סוג חיפוש -->
    <div class="search-type-selector">
        <h3>בחר סוג חיפוש:</h3>
        <div class="search-type-buttons">
            <button class="search-type-btn active" onclick="switchSearchType('standard')">
                חיפוש סטנדרטי
            </button>
            <button class="search-type-btn" onclick="switchSearchType('purchased_graves')">
                קברים שנרכשו
            </button>
            <button class="search-type-btn" onclick="switchSearchType('available_graves')">
                קברים פנויים
            </button>
        </div>
        
        <div id="filter-info" class="filter-info" style="display: none;">
            <strong>תנאי סינון פעילים:</strong>
            <div id="filter-list"></div>
        </div>
    </div>

    <!-- חיפוש פשוט -->
    <div class="search-section">
        <h2>חיפוש פשוט</h2>
        <div class="search-wrapper">
            <input type="text" 
                   id="simple-query" 
                   class="search-input" 
                   placeholder="הקלד טקסט לחיפוש..."
                   onkeypress="if(event.key === 'Enter') performConfigurableSearch()">
            <button class="search-button" onclick="performConfigurableSearch()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- חיפוש מתקדם -->
    <div class="search-section">
        <h2>חיפוש מתקדם</h2>
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
    <script src="assets/js/search-config.js"></script>
    <script>
        let currentSearch = null;
        let currentSearchType = 'standard';
        
        // אתחול
        document.addEventListener('DOMContentLoaded', function() {
            switchSearchType('purchased_graves'); // ברירת מחדל
        });
        
        /**
         * החלפת סוג חיפוש
         */
        function switchSearchType(searchType) {
            currentSearchType = searchType;
            currentSearch = new ConfigurableSearch(searchType);
            
            // עדכון כפתורים
            document.querySelectorAll('.search-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // עדכון תנאי סינון
            updateFilterDisplay();
            
            // עדכון שדות מתקדמים
            updateAdvancedFields();
            
            // ניקוי תוצאות
            document.getElementById('results-section').style.display = 'none';
        }
        
        /**
         * עדכון תצוגת תנאי סינון
         */
        function updateFilterDisplay() {
            const filterInfo = document.getElementById('filter-info');
            const filterList = document.getElementById('filter-list');
            
            if (currentSearch.config.filters && currentSearch.config.filters.required) {
                filterInfo.style.display = 'block';
                filterList.innerHTML = '';
                
                for (const [field, condition] of Object.entries(currentSearch.config.filters.required)) {
                    const item = document.createElement('div');
                    item.innerHTML = `• ${field} ${condition.operator} ${condition.value || 'null'}`;
                    filterList.appendChild(item);
                }
            } else {
                filterInfo.style.display = 'none';
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
            
            for (const [key, dbField] of Object.entries(fields)) {
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'form-group';
                
                const label = displayLabels[dbField] || key;
                
                fieldDiv.innerHTML = `
                    <label class="form-label">${label}</label>
                    <input type="text" 
                           id="adv-${key}" 
                           class="form-input" 
                           placeholder="הקלד ${label}...">
                `;
                
                container.appendChild(fieldDiv);
            }
        }
        
        /**
         * חיפוש פשוט
         */
        async function performConfigurableSearch() {
            const query = document.getElementById('simple-query').value.trim();
            
            if (!query || query.length < 2) {
                alert('יש להזין לפחות 2 תווים לחיפוש');
                return;
            }
            
            showLoading(true);
            
            try {
                // כאן תקרא לפונקציה הקיימת שלך עם הפרמטרים החדשים
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
         * ביצוע חיפוש עם קונפיגורציה
         */
        async function searchWithConfig(queryOrParams, searchMode) {
            // הכנת הפרמטרים ל-API
            const apiParams = currentSearch.prepareApiParams(
                searchMode === 'simple' 
                    ? { query: queryOrParams }
                    : queryOrParams
            );
            
            // כאן תקרא ל-API שלך
            // לדוגמה:
            const response = await fetch('/dashboard/dashboards/search/api/configurable-search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(apiParams)
            });
            
            const data = await response.json();
            return data;
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