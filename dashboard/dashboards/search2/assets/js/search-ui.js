window.SearchUI = {
    init() {
        this.setupEventListeners();
        this.renderSimpleSearch();
    },
    
    setupEventListeners() {
        // טאבים של סוג חיפוש
        document.querySelectorAll('.search-type-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const searchType = e.currentTarget.dataset.searchType;
                window.switchSearchType(searchType);
            });
        });
        
        // טאבים של פשוט/מתקדם
        document.querySelectorAll('.search-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabName = e.currentTarget.dataset.tab;
                window.switchTab(tabName);
            });
        });
        
        // כפתורי תצוגה - תיקון
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const viewType = e.currentTarget.dataset.view;
                window.currentView = viewType; // עדכון ישיר של המשתנה
                this.switchView(viewType);
            });
        });
    },
    
    renderSimpleSearch() {
        const container = document.getElementById('simple-tab');
        container.innerHTML = `
            <div class="search-section">
                <h2>חיפוש מהיר</h2>
                <p style="color: #666; margin-bottom: 20px;">הקלד טקסט חופשי לחיפוש בכל השדות הרלוונטיים</p>
                <div class="search-wrapper">
                    <input type="text" 
                           id="simple-query" 
                           class="search-input" 
                           placeholder="הקלד שם, מספר קבר, בית עלמין, תעודת זהות..."
                           onkeypress="if(event.key === 'Enter') performConfigurableSearch()">
                    <button class="search-button" onclick="performConfigurableSearch()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    },
    
    updateAdvancedFields(searchInstance) {
        const container = document.getElementById('advanced-tab');
        const fields = searchInstance.config.searchFields.advanced;
        const displayLabels = searchInstance.getDisplayLabels();
        const searchType = searchInstance.searchType;
        
        let html = `
            <div class="search-section">
                <h2>חיפוש מתקדם</h2>
                <p style="color: #666; margin-bottom: 20px;">חפש לפי שדות ספציפיים לתוצאות מדויקות יותר</p>
                <div id="advanced-fields">
        `;
        
        if (searchType === 'deceased_search') {
            // פרטי הנפטר
            html += `
                <div class="field-section">
                    <h4>👤 פרטי הנפטר:</h4>
                    <div class="field-grid">
            `;
            
            ['firstName', 'lastName', 'fatherName', 'motherName', 'numId'].forEach(key => {
                if (fields[key]) {
                    html += this.createFieldHTML(key, fields[key], displayLabels);
                }
            });
            
            html += `
                    </div>
                </div>
                <div class="field-section">
                    <h4>📍 מיקום הקבר:</h4>
                    <div class="field-grid">
            `;
            
            ['cemeteryName', 'blockName', 'plotName', 'areaName', 'lineName', 'graveName'].forEach(key => {
                if (fields[key]) {
                    html += this.createFieldHTML(key, fields[key], displayLabels);
                }
            });
            
            html += `
                    </div>
                </div>
            `;
            
            // תאריכים
            if (searchInstance.config.searchFields.special?.dateSearch) {
                const dateConfig = searchInstance.config.searchFields.special.dateSearch;
                html += `
                    <div class="field-section">
                        <h4>📅 ${dateConfig.label}:</h4>
                        <div style="margin-bottom: 15px;">
                            <label class="form-label">בחר חודש ושנה:</label>
                            <input type="month" id="adv-deathMonth" class="form-input" style="margin-bottom: 10px;">
                            
                            <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="dateAccuracy" value="exact" checked>
                                    <span>תאריך מדויק</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="dateAccuracy" value="approximate">
                                    <span>תאריך משוער (±2.5 שנים)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                `;
            }
        } else {
            // שאר סוגי החיפוש
            html += '<div class="field-grid">';
            for (const [key, dbField] of Object.entries(fields)) {
                html += this.createFieldHTML(key, dbField, displayLabels);
            }
            html += '</div>';
        }
        
        html += `
                </div>
                <div style="margin-top: 20px;">
                    <button class="submit-button" onclick="performAdvancedConfigurableSearch()">חפש</button>
                    <button class="clear-button" onclick="clearAdvancedForm()">נקה</button>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    },
    
    createFieldHTML(key, dbField, displayLabels) {
        const label = displayLabels[dbField] || key;
        return `
            <div class="form-group">
                <label class="form-label">${label}</label>
                <input type="text" 
                       id="adv-${key}" 
                       class="form-input" 
                       placeholder="הקלד ${label}...">
            </div>
        `;
    },
    
    collectAdvancedParams(searchInstance, searchType) {
        const params = {};
        const fields = searchInstance.config.searchFields.advanced;
        
        // טיפול מיוחד בתאריך פטירה
        if (searchType === 'deceased_search') {
            const monthInput = document.getElementById('adv-deathMonth');
            const accuracy = document.querySelector('input[name="dateAccuracy"]:checked');
            
            if (monthInput?.value) {
                const [year, month] = monthInput.value.split('-');
                
                if (accuracy?.value === 'approximate') {
                    params.deathDateRange = {
                        from: `${parseInt(year) - 3}-${month}`,
                        to: `${parseInt(year) + 2}-${month}`
                    };
                } else {
                    params.deathDateExact = monthInput.value;
                }
            }
        }
        
        // איסוף שאר השדות
        for (const key of Object.keys(fields)) {
            const input = document.getElementById(`adv-${key}`);
            if (input?.value.trim()) {
                params[key] = input.value.trim();
            }
        }
        
        return params;
    },
    
    displayResults(data, searchInstance, searchType, viewType) {
        const resultsSection = document.getElementById('results-section');
        const resultsContainer = document.getElementById('results-container');
        const resultCount = document.getElementById('result-count');
        const searchTime = document.getElementById('search-time');
        
        // שמירת התוצאות האחרונות
        window.lastSearchResults = data;
        
        resultsSection.style.display = 'block';
        resultCount.textContent = data.results ? data.results.length : 0;
        searchTime.textContent = data.searchTime || '0';
        
        if (!data.results || data.results.length === 0) {
            resultsContainer.innerHTML = '<p style="text-align: center; padding: 20px;">לא נמצאו תוצאות</p>';
            return;
        }
        
        // בחירת הטמפלייט המתאים
        let template;
        const displayLabels = searchInstance.getDisplayLabels();
        const returnFields = searchInstance.config.returnFields;
        
        if (viewType === 'cards') {
            // תצוגת כרטיסים
            let html = '<div class="results-cards">';
            
            data.results.forEach(record => {
                switch(searchType) {
                    case 'deceased_search':
                        html += DeceasedCardTemplate.render(record);
                        break;
                    case 'purchased_graves':
                        html += PurchasedCardTemplate.render(record);
                        break;
                    case 'available_graves':
                        html += AvailableCardTemplate.render(record);
                        break;
                }
            });
            
            html += '</div>';
            resultsContainer.innerHTML = html;
        } else {
            // תצוגת טבלה
            switch(searchType) {
                case 'deceased_search':
                    resultsContainer.innerHTML = DeceasedTableTemplate.render(data.results, displayLabels, returnFields);
                    break;
                case 'purchased_graves':
                    resultsContainer.innerHTML = PurchasedTableTemplate.render(data.results, displayLabels, returnFields);
                    break;
                case 'available_graves':
                    resultsContainer.innerHTML = AvailableTableTemplate.render(data.results, displayLabels, returnFields);
                    break;
            }
        }
    },
    
    switchSearchType(searchType) {
        // עדכון טאבים
        document.querySelectorAll('.search-type-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.searchType === searchType) {
                tab.classList.add('active');
            }
        });
        
        // ניקוי תוצאות
        document.getElementById('results-section').style.display = 'none';
    },
    
    switchTab(tabName) {
        // עדכון כפתורי הטאבים
        document.querySelectorAll('.search-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active');
            }
        });
        
        // עדכון תוכן הטאבים
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');
    },

    // עדכן את הפונקציה switchView
    switchView(viewType) {
        // עדכון כפתורים
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.view === viewType) {
                btn.classList.add('active');
            }
        });
        
        // עדכון המשתנה הגלובלי
        window.currentView = viewType;
        
        // רענון התוצאות בתצוגה החדשה
        const lastResults = window.lastSearchResults;
        if (lastResults && window.currentSearch) {
            this.displayResults(
                lastResults, 
                window.currentSearch, 
                window.currentSearchType, 
                viewType  // שימוש בפרמטר שהועבר
            );
        }
    },
    
    clearAdvancedForm() {
        document.querySelectorAll('#advanced-fields input').forEach(input => {
            if (input.type === 'radio') {
                input.checked = input.value === 'exact';
            } else {
                input.value = '';
            }
        });
    },
    
    showLoading(show) {
        document.getElementById('loading').style.display = show ? 'flex' : 'none';
    }
};