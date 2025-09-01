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
        
        /* סגנון ל-toggle של מקור הנתונים */
        .data-source-toggle {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #ccc;
            border-radius: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .toggle-switch input {
            display: none;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            transform: translateX(30px);
        }
        
        .toggle-switch input:checked ~ .toggle-bg {
            background: #4a90e2;
        }
        
        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        
        .toggle-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            border-radius: 15px;
            transition: background 0.3s;
        }
        
        .source-label {
            font-weight: bold;
            color: #333;
        }
        
        .source-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .source-active {
            background: #4caf50;
            color: white;
        }
        
        .source-inactive {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <!-- בורר מקור נתונים -->
    <div class="data-source-toggle">
        <span class="source-label">מקור נתונים:</span>
        <label class="toggle-switch">
            <input type="checkbox" id="dataSourceToggle" onchange="toggleDataSource()">
            <span class="toggle-bg"></span>
            <span class="toggle-slider"></span>
        </label>
        <span id="currentSource">JSON File</span>
        <span class="source-status source-active">פעיל</span>
    </div>

    <!-- בורר סוג חיפוש -->
    <div class="search-type-selector" style="margin-top: 70px;">
        <h3>בחר סוג חיפוש:</h3>
        <div class="search-type-buttons">
            <button class="search-type-btn active" onclick="switchSearchType('standard')">
                חיפוש סטנדרטי
            </button>
            <button class="search-type-btn" onclick="switchSearchType('deceased_search')">
                🪦 חיפוש נפטרים
            </button>
            <button class="search-type-btn" onclick="switchSearchType('purchased_graves')">
                💰 קברים שנרכשו
            </button>
            <button class="search-type-btn" onclick="switchSearchType('available_graves')">
                ✅ קברים פנויים
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
    <script>
        // טעינת הקונפיגורציה ישירות בדף (למקרה שהקובץ החיצוני לא נטען)
        function loadConfigInline() {
            window.SearchConfig = {
                searches: {
                    standard: {
                        name: 'חיפוש סטנדרטי',
                        filters: {},
                        searchFields: {
                            simple: ['c_firstName', 'c_lastName', 'c_fullNameHe'],
                            advanced: {
                                firstName: 'c_firstName',
                                lastName: 'c_lastName',
                                fatherName: 'c_nameFather',
                                motherName: 'c_nameMother',
                                cemetery: 'cemeteryNameHe'
                            }
                        },
                        returnFields: [
                            'c_firstName', 'c_lastName', 'graveNameHe', 'cemeteryNameHe'
                        ]
                    },
                    purchased_graves: {
                        name: 'קברים שנרכשו',
                        filters: {
                            required: {
                                'p_clientId': { operator: '!=', value: null },
                                'graveStatus': { operator: '=', value: '2' }
                            }
                        },
                        searchFields: {
                            simple: ['c_firstName', 'c_lastName', 'graveNameHe', 'cemeteryNameHe'],
                            advanced: {
                                firstName: 'c_firstName',
                                lastName: 'c_lastName',
                                graveName: 'graveNameHe',
                                cemeteryName: 'cemeteryNameHe'
                            }
                        },
                        returnFields: [
                            'c_firstName', 'c_lastName', 'graveNameHe', 'cemeteryNameHe', 'p_price'
                        ],
                        displayFields: {
                            'c_firstName': 'שם פרטי',
                            'c_lastName': 'שם משפחה',
                            'graveNameHe': 'מספר קבר',
                            'cemeteryNameHe': 'בית עלמין',
                            'p_price': 'מחיר'
                        }
                    },
                    
                    // חיפוש נפטרים חדש
                    deceased_search: {
                        name: 'חיפוש נפטרים',
                        filters: {
                            required: {
                                'b_clientId': { operator: '!=', value: null },
                                'graveStatus': { operator: '=', value: '3' }
                            }
                        },
                        searchFields: {
                            simple: [
                                'c_firstName', 
                                'c_lastName', 
                                'c_fullNameHe',
                                'c_nameFather',
                                'c_nameMother',
                                'graveNameHe',
                                'areaGraveNameHe',
                                'plotNameHe',
                                'blockNameHe',
                                'cemeteryNameHe'
                            ],
                            advanced: {
                                // פרטי הנפטר
                                firstName: 'c_firstName',
                                lastName: 'c_lastName',
                                fatherName: 'c_nameFather',
                                motherName: 'c_nameMother',
                                // מיקום הקבר
                                graveName: 'graveNameHe',
                                areaName: 'areaGraveNameHe',
                                lineName: 'lineNameHe',
                                plotName: 'plotNameHe',
                                blockName: 'blockNameHe',
                                cemeteryName: 'cemeteryNameHe',
                                // תאריכים
                                deathDate: 'b_dateDeath',
                                burialDate: 'b_dateBurial'
                            }
                        },
                        returnFields: [
                            'c_firstName',
                            'c_lastName',
                            'c_nameFather',
                            'c_nameMother',
                            'graveNameHe',
                            'areaGraveNameHe',
                            'lineNameHe',
                            'plotNameHe',
                            'blockNameHe',
                            'cemeteryNameHe',
                            'b_dateDeath',
                            'b_timeDeath',
                            'b_dateBurial',
                            'b_timeBurial',
                            'c_dateBirth',
                            'c_comment'
                        ],
                        displayFields: {
                            'c_firstName': 'שם פרטי',
                            'c_lastName': 'שם משפחה',
                            'c_nameFather': 'שם האב',
                            'c_nameMother': 'שם האם',
                            'graveNameHe': 'מספר קבר',
                            'areaGraveNameHe': 'אזור',
                            'lineNameHe': 'שורה',
                            'plotNameHe': 'חלקה',
                            'blockNameHe': 'גוש',
                            'cemeteryNameHe': 'בית עלמין',
                            'b_dateDeath': 'תאריך פטירה',
                            'b_timeDeath': 'שעת פטירה',
                            'b_dateBurial': 'תאריך קבורה',
                            'b_timeBurial': 'שעת קבורה',
                            'c_dateBirth': 'תאריך לידה',
                            'c_comment': 'הערות'
                        }
                    },
                    
                    available_graves: {
                        name: 'קברים פנויים',
                        filters: {
                            required: {
                                'graveStatus': { operator: '=', value: '1' },
                                'p_clientId': { operator: '=', value: null }
                            }
                        },
                        searchFields: {
                            simple: ['cemeteryNameHe', 'blockNameHe', 'plotNameHe'],
                            advanced: {
                                cemetery: 'cemeteryNameHe',
                                block: 'blockNameHe',
                                plot: 'plotNameHe',
                                area: 'areaGraveNameHe'
                            }
                        },
                        returnFields: [
                            'graveId',
                            'graveNameHe',
                            'areaGraveNameHe',
                            'plotNameHe',
                            'blockNameHe',
                            'cemeteryNameHe',
                            'graveStatus'
                        ]
                    }
                },
                settings: {
                    defaultLimit: 50,
                    maxLimit: 100,
                    minSearchLength: 2,
                    // שדות שלא לכלול בחיפוש כללי
                    excludeFromGeneralSearch: [
                        'graveId',
                        'audit_log_id',
                        'createDate',
                        'updateDate',
                        'inactiveDate',
                        'saveDate',
                        'clientId',
                        'p_unicId',
                        'b_burialId',
                        'c_customerId',
                        'c_unicId',
                        'graveUnicId',
                        'areaGraveId',
                        'plotType',
                        'graveStatus',
                        'graveLocation',
                        'isSmallGrave',
                        'isActive',
                        'documentsList',
                        'p_paymentsList',
                        'p_additionalpaymentsList',
                        'p_historyList',
                        'p_savedGravesList',
                        'b_savedGravesList',
                        'b_historyList',
                        'b_documentsList'
                    ]
                }
            };

            // הגדרת המחלקה
            window.ConfigurableSearch = class {
                constructor(searchType = 'standard') {
                    this.searchType = searchType;
                    this.config = SearchConfig.searches[searchType];
                    if (!this.config) {
                        throw new Error(`Search type "${searchType}" not found`);
                    }
                }
                
                matchesFilters(record) {
                    if (!this.config.filters || !this.config.filters.required) {
                        return true;
                    }
                    
                    for (const [field, condition] of Object.entries(this.config.filters.required)) {
                        const recordValue = record[field];
                        const { operator, value } = condition;
                        
                        switch (operator) {
                            case '=':
                                if (recordValue != value) return false;
                                break;
                            case '!=':
                                if (recordValue == value) return false;
                                break;
                        }
                    }
                    return true;
                }
                
                getDisplayLabels() {
                    return this.config.displayFields || {};
                }
                
                prepareApiParams(params) {
                    const apiParams = {
                        searchType: this.searchType,
                        filters: this.config.filters,
                        limit: params.limit || SearchConfig.settings.defaultLimit,
                        offset: params.offset || 0
                    };
                    
                    const fieldMapping = this.config.searchFields.advanced;
                    
                    Object.entries(params).forEach(([key, value]) => {
                        if (fieldMapping && fieldMapping[key]) {
                            apiParams[fieldMapping[key]] = value;
                        } else if (key !== 'limit' && key !== 'offset') {
                            apiParams[key] = value;
                        }
                    });
                    
                    return apiParams;
                }
            };
        }
        
        // טען את הקונפיגורציה מיד
        loadConfigInline();
    </script>
    
    <!-- ניסיון לטעון את הקובץ החיצוני (אופציונלי) -->
    <script src="assets/js/search-config.js" onerror="console.log('External config not found, using inline')"></script>
    
    <script>
        let currentSearch = null;
        let currentSearchType = 'standard';
        
        // אתחול - מחכים שהקובץ יטען
        document.addEventListener('DOMContentLoaded', function() {
            // בדיקה שהקונפיגורציה נטענה
            if (typeof ConfigurableSearch === 'undefined') {
                console.error('ConfigurableSearch not loaded. Creating inline...');
                // טעינת הקונפיגורציה ישירות אם הקובץ לא נטען
                loadConfigInline();
            }
            
            // אתחול ללא event (כי אין כפתור שנלחץ)
            initializeSearch('purchased_graves');
        });
        
        /**
         * אתחול החיפוש
         */
        function initializeSearch(searchType) {
            currentSearchType = searchType;
            
            // בדיקה שהמחלקה קיימת
            if (typeof ConfigurableSearch !== 'undefined') {
                currentSearch = new ConfigurableSearch(searchType);
                updateFilterDisplay();
                updateAdvancedFields();
            } else {
                console.error('ConfigurableSearch class not available');
            }
        }
        
        /**
         * החלפת סוג חיפוש
         */
        function switchSearchType(searchType) {
            // בדיקה שהמחלקה קיימת
            if (typeof ConfigurableSearch === 'undefined') {
                alert('מערכת החיפוש לא נטענה כראוי. נא לרענן את הדף.');
                return;
            }
            
            currentSearchType = searchType;
            currentSearch = new ConfigurableSearch(searchType);
            
            // עדכון כפתורים - רק אם יש event
            if (event && event.target) {
                document.querySelectorAll('.search-type-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                event.target.classList.add('active');
            }
            
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
        
        // הגדרת מקורות המידע
        const DATA_SOURCES = {
            API: {
                name: 'API Server',
                endpoint: '/dashboard/dashboards/search/api/deceased-search.php',
                active: false, // כרגע לא פעיל
                method: 'POST'
            },
            JSON: {
                name: 'JSON File',
                endpoint: '/dashboard/dashboards/search/data/data.json',
                active: true, // פעיל
                method: 'GET'
            }
        };
        
        // מקור נתונים נוכחי (ברירת מחדל JSON)
        let currentDataSource = 'JSON';
        
        /**
         * החלפת מקור נתונים
         */
        function toggleDataSource() {
            const toggle = document.getElementById('dataSourceToggle');
            if (toggle) {
                currentDataSource = toggle.checked ? 'API' : 'JSON';
                console.log('Data source switched to:', currentDataSource);
                
                // עדכון תצוגה
                const sourceDisplay = document.getElementById('currentSource');
                if (sourceDisplay) {
                    sourceDisplay.textContent = DATA_SOURCES[currentDataSource].name;
                }
                
                // בדיקה אם המקור פעיל
                if (!DATA_SOURCES[currentDataSource].active) {
                    alert(`מקור הנתונים ${DATA_SOURCES[currentDataSource].name} אינו פעיל כרגע`);
                    // חזרה למקור הקודם
                    currentDataSource = currentDataSource === 'API' ? 'JSON' : 'API';
                    toggle.checked = currentDataSource === 'API';
                }
            }
        }
        
        /**
         * טעינת נתוני JSON
         */
        async function loadJSONData() {
            try {
                const response = await fetch(DATA_SOURCES.JSON.endpoint);
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
         * חיפוש ב-JSON
         */
        function searchInJSON(data, searchParams, searchMode) {
            let results = [];
            
            if (searchMode === 'simple') {
                const query = searchParams.query.toLowerCase();
                const searchTerms = query.split(' ').filter(t => t);
                
                // קבלת שדות לחיפוש
                let searchFields = currentSearch.config.searchFields.simple;
                
                // אם אין הגדרה ספציפית, חפש בכל השדות (פחות אלו שמוחרגים)
                if (!searchFields || searchFields.length === 0) {
                    searchFields = [];
                    const excludeFields = SearchConfig.settings.excludeFromGeneralSearch || [];
                    
                    // לקחת רשומה לדוגמה כדי לדעת אילו שדות יש
                    if (data.length > 0) {
                        Object.keys(data[0]).forEach(field => {
                            if (!excludeFields.includes(field)) {
                                searchFields.push(field);
                            }
                        });
                    }
                }
                
                results = data.filter(record => {
                    // בדיקת תנאי סינון
                    if (!currentSearch.matchesFilters(record)) {
                        return false;
                    }
                    
                    // בניית טקסט לחיפוש
                    const searchableText = searchFields
                        .map(field => {
                            const value = record[field];
                            // המרת ערכים null או undefined למחרוזת ריקה
                            if (value === null || value === undefined) return '';
                            // המרת מספרים ובוליאנים למחרוזת
                            return value.toString();
                        })
                        .join(' ')
                        .toLowerCase();
                    
                    // בדיקה שכל המילים נמצאות
                    return searchTerms.every(term => searchableText.includes(term));
                });
            } else {
                // חיפוש מתקדם
                const fieldMapping = currentSearch.config.searchFields.advanced;
                
                results = data.filter(record => {
                    // בדיקת תנאי סינון
                    if (!currentSearch.matchesFilters(record)) {
                        return false;
                    }
                    
                    // בדיקת כל פרמטר חיפוש
                    for (const [uiField, dbField] of Object.entries(fieldMapping)) {
                        if (searchParams[uiField]) {
                            const searchValue = searchParams[uiField].toLowerCase();
                            const recordValue = (record[dbField] || '').toString().toLowerCase();
                            
                            // לתאריכים - השוואה מדויקת או חלקית
                            if (uiField.includes('Date') && recordValue) {
                                // אם החיפוש הוא רק שנה, חפש את השנה בתאריך
                                if (searchValue.length === 4) {
                                    if (!recordValue.includes(searchValue)) {
                                        return false;
                                    }
                                } else {
                                    // אחרת בדוק התאמה רגילה
                                    if (!recordValue.includes(searchValue)) {
                                        return false;
                                    }
                                }
                            } else {
                                // לשאר השדות - חיפוש טקסט רגיל
                                if (!recordValue.includes(searchValue)) {
                                    return false;
                                }
                            }
                        }
                    }
                    
                    return true;
                });
            }
            
            // עיצוב התוצאות - החזרת רק השדות הרצויים
            const returnFields = currentSearch.config.returnFields;
            const formattedResults = results.map(record => {
                const formattedRecord = {};
                returnFields.forEach(field => {
                    formattedRecord[field] = record[field] || null;
                });
                return formattedRecord;
            });
            
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
                let results = [];
                
                if (currentDataSource === 'JSON') {
                    // חיפוש ב-JSON
                    const jsonData = await loadJSONData();
                    
                    const searchParams = searchMode === 'simple' 
                        ? { query: queryOrParams }
                        : queryOrParams;
                    
                    results = searchInJSON(jsonData, searchParams, searchMode);
                    
                } else if (currentDataSource === 'API') {
                    // חיפוש דרך API (כרגע לא פעיל)
                    const apiParams = currentSearch.prepareApiParams(
                        searchMode === 'simple' 
                            ? { query: queryOrParams }
                            : queryOrParams
                    );
                    
                    const response = await fetch(DATA_SOURCES.API.endpoint, {
                        method: DATA_SOURCES.API.method,
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(apiParams)
                    });
                    
                    const data = await response.json();
                    results = data.results || [];
                }
                
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
                    searchTime: searchTime,
                    source: currentDataSource
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