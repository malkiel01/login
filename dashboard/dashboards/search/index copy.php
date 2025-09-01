<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש מותאם - קברים שנרכשו</title>
    <link rel="stylesheet" href="dashboards/search/assets/css/search.css">
    <link rel="stylesheet" href="dashboards/search/assets/css/custom-search.css">
</head>
<body>
    <!-- בורר סוג חיפוש - טאבים -->
    <div class="search-type-selector">
        <div class="search-type-tabs">
            <button class="search-type-tab active" onclick="switchSearchType('deceased_search')">
                <div class="icon">🪦</div>
                <div class="label">חיפוש נפטרים</div>
            </button>
            <button class="search-type-tab" onclick="switchSearchType('purchased_graves')">
                <div class="icon">💰</div>
                <div class="label">קברים שנרכשו</div>
            </button>
            <button class="search-type-tab" onclick="switchSearchType('available_graves')">
                <div class="icon">✅</div>
                <div class="label">קברים פנויים</div>
            </button>
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
            <!-- בורר תצוגה -->
            <div class="view-selector">
                <button class="view-btn active" onclick="switchView('cards')">
                    <span>📇</span> כרטיסים
                </button>
                <button class="view-btn" onclick="switchView('table')">
                    <span>📊</span> טבלה
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

    <!-- Scripts -->
    <!-- טוען את קובץ הקונפיגורציה החיצוני -->
    <script src="dashboards/search/assets/js/search-config.js"></script>
    
    <script>
        let currentSearch = null;
        let currentSearchType = 'deceased_search';
        let currentTab = 'simple';
        let currentView = 'cards'; // ברירת מחדל - תצוגת כרטיסים
        
        // אתחול
        document.addEventListener('DOMContentLoaded', function() {
            // בדיקה שהקונפיגורציה נטענה
            if (typeof ConfigurableSearch === 'undefined' || typeof SearchConfig === 'undefined') {
                console.error('Configuration file not loaded!');
                alert('שגיאה בטעינת קובץ הקונפיגורציה. נא לרענן את הדף.');
                return;
            }
            
            // אתחול עם סוג החיפוש הראשוני
            initializeSearch('deceased_search');
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
                
                // עדכון טאבים
                if (event && event.target) {
                    document.querySelectorAll('.search-type-tab').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    event.target.closest('.search-type-tab').classList.add('active');
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
         * החלפת תצוגת תוצאות
         */
        function switchView(viewType) {
            currentView = viewType;
            
            // עדכון כפתורים
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.view-btn').classList.add('active');
            
            // רענון התוצאות בתצוגה החדשה
            const lastResults = window.lastSearchResults;
            if (lastResults) {
                displayConfigurableResults(lastResults);
            }
        }
        
        /**
         * יצירת ראשי תיבות משם
         */
        function getInitials(firstName, lastName) {
            const first = firstName ? firstName.charAt(0) : '';
            const last = lastName ? lastName.charAt(0) : '';
            return (first + last) || '?';
        }
        
        /**
         * פורמט תאריך
         */
        function formatDate(dateStr) {
            if (!dateStr) return '-----';
            try {
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) {
                    return '-----';
                }
                return date.toLocaleDateString('he-IL');
            } catch {
                return '-----';
            }
        }
        
        /**
         * הצגת תוצאות
         */
        function displayConfigurableResults(data) {
            const resultsSection = document.getElementById('results-section');
            const resultsContainer = document.getElementById('results-container');
            const resultCount = document.getElementById('result-count');
            
            // שמירת התוצאות האחרונות
            window.lastSearchResults = data;
            
            resultsSection.style.display = 'block';
            resultCount.textContent = data.results ? data.results.length : 0;
            
            if (!data.results || data.results.length === 0) {
                resultsContainer.innerHTML = '<p style="text-align: center; padding: 20px;">לא נמצאו תוצאות</p>';
                return;
            }
            
            // בחירת תצוגה לפי currentView
            if (currentView === 'cards') {
                displayCardsView(data.results, resultsContainer);
            } else {
                displayTableView(data.results, resultsContainer);
            }
        }
        
        /**
         * תצוגת כרטיסים
         */
        function displayCardsView(results, container) {
            const cardsContainer = document.createElement('div');
            cardsContainer.className = 'results-cards';
            
            const displayLabels = currentSearch.getDisplayLabels();
            
            results.forEach(record => {
                const card = document.createElement('div');
                card.className = 'result-card';
                
                // ראשי תיבות או תמונה
                const initials = getInitials(record.c_firstName, record.c_lastName);
                
                // בניית תוכן לפי סוג החיפוש
                let cardHTML = `
                    <div class="image-placeholder">
                        <span class="initials">${initials}</span>
                    </div>
                    <div class="card-content">
                `;
                
                if (currentSearchType === 'deceased_search') {
                    // כרטיס נפטר
                    cardHTML += `
                        <div class="name">${record.c_firstName || ''} ${record.c_lastName || ''}</div>
                        <div class="parents">
                            ${record.c_nameFather ? `בן ${record.c_nameFather}` : ''}
                            ${record.c_nameMother ? ` ו${record.c_nameMother}` : ''}
                        </div>
                        <div class="dates">
                            ${record.c_dateBirth ? `נולד: ${formatDate(record.c_dateBirth)}` : ''}
                            </br>
                            ${record.b_dateDeath ? `נפטר: ${formatDate(record.b_dateDeath)}` : ''}
                        </div>
                        <div class="location">
                            <span class="location-icon">📍</span>
                            <span>
                                ${record.cemeteryNameHe || ''}
                                ${record.blockNameHe ? `, גוש ${record.blockNameHe}` : ''}
                                ${record.plotNameHe ? `, חלקה ${record.plotNameHe}` : ''}
                                ${record.lineNameHe ? `, שורה ${record.lineNameHe}` : ''}
                                ${record.graveNameHe ? `, קבר ${record.graveNameHe}` : ''}
                            </span>
                        </div>
                    `;
                } else if (currentSearchType === 'purchased_graves') {
                    // כרטיס רכישה
                    cardHTML += `
                        <div class="name">${record.c_firstName || ''} ${record.c_lastName || ''}</div>
                        <div class="parents">רוכש הקבר</div>
                        <div class="dates">
                            ${record.p_price ? `מחיר: ₪${record.p_price}` : ''}
                            ${record.p_purchaseStatus_display ? ` | ${record.p_purchaseStatus_display}` : ''}
                        </div>
                        <div class="location">
                            <span class="location-icon">📍</span>
                            <span>
                                ${record.cemeteryNameHe || ''}
                                ${record.graveNameHe ? `, קבר ${record.graveNameHe}` : ''}
                            </span>
                        </div>
                    `;
                } else if (currentSearchType === 'available_graves') {
                    // כרטיס קבר פנוי
                    cardHTML += `
                        <div class="name">קבר פנוי #${record.graveNameHe || record.graveId}</div>
                        <div class="parents">סטטוס: ${record.graveStatus_display || 'פנוי'}</div>
                        <div class="location">
                            <span class="location-icon">📍</span>
                            <span>
                                ${record.cemeteryNameHe || ''}
                                ${record.blockNameHe ? `, גוש ${record.blockNameHe}` : ''}
                                ${record.plotNameHe ? `, חלקה ${record.plotNameHe}` : ''}
                                ${record.areaGraveNameHe ? `, אזור ${record.areaGraveNameHe}` : ''}
                            </span>
                        </div>
                    `;
                }
                
                cardHTML += `</div>`;
                card.innerHTML = cardHTML;
                cardsContainer.appendChild(card);
            });
            
            container.innerHTML = '';
            container.appendChild(cardsContainer);
        }
        
        /**
         * תצוגת טבלה
         */
        function displayTableView(results, container) {
            const tableContainer = document.createElement('div');
            tableContainer.className = 'results-table-container';
            
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
            for (const record of results) {
                const row = document.createElement('tr');
                
                for (const field of currentSearch.config.returnFields) {
                    const td = document.createElement('td');
                    
                    // בדיקה אם יש תצוגה מתורגמת
                    if (record[field + '_display']) {
                        td.textContent = record[field + '_display'];
                    } else if (field.includes('Date') && record[field]) {
                        td.textContent = formatDate(record[field]);
                    } else {
                        td.textContent = record[field] || '-';
                    }
                    
                    row.appendChild(td);
                }
                
                tbody.appendChild(row);
            }
            table.appendChild(tbody);
            
            tableContainer.appendChild(table);
            container.innerHTML = '';
            container.appendChild(tableContainer);
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