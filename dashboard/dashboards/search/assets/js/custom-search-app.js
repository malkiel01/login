/**
 * Custom Search Application
 * assets/js/custom-search-app.js
 */

class SearchApp {
    constructor() {
        this.currentSearch = null;
        this.currentSearchType = 'standard';
        this.currentDataSource = 'JSON';
        this.resultsTable = window.resultsTable || new ResultsTable();
        this.jsonData = null;
        this.lastSearchTime = null;
        
        // הגדרת מקורות המידע
        this.dataSources = {
            API: {
                name: 'API Server',
                endpoint: '/dashboard/dashboards/search/api/deceased-search.php',
                active: false,
                method: 'POST'
            },
            JSON: {
                name: 'JSON File',
                endpoint: '/dashboard/dashboards/search/data/data.json',
                active: true,
                method: 'GET'
            }
        };
    }

    /**
     * אתחול האפליקציה
     */
    init() {
        // בדיקת קונפיגורציה
        if (!this.checkConfig()) {
            this.showError('הקונפיגורציה לא נטענה כראוי');
            return;
        }
        
        // יצירת כפתורי סוגי חיפוש
        this.createSearchTypeButtons();
        
        // אתחול ברירת מחדל
        this.switchSearchType('standard');
        
        // טעינת נתוני JSON
        this.preloadJSONData();
        
        // הוספת מאזינים
        this.attachEventListeners();
        
        console.log('SearchApp initialized successfully');
    }

    /**
     * בדיקת קונפיגורציה
     */
    checkConfig() {
        if (typeof SearchConfig === 'undefined') {
            console.error('SearchConfig not found');
            return false;
        }
        if (typeof ConfigurableSearch === 'undefined') {
            console.error('ConfigurableSearch class not found');
            return false;
        }
        return true;
    }

    /**
     * יצירת כפתורי סוגי חיפוש
     */
    createSearchTypeButtons() {
        const container = document.getElementById('search-type-buttons');
        if (!container) return;
        
        container.innerHTML = '';
        
        Object.entries(SearchConfig.searches).forEach(([key, config]) => {
            const button = document.createElement('button');
            button.className = 'search-type-btn';
            button.dataset.searchType = key;
            button.onclick = () => this.switchSearchType(key);
            
            // הוספת אייקון לפי סוג
            const icons = {
                standard: '🔍',
                deceased_search: '🪦',
                purchased_graves: '💰',
                available_graves: '✅'
            };
            
            button.innerHTML = `${icons[key] || ''} ${config.name}`;
            container.appendChild(button);
        });
    }

    /**
     * החלפת סוג חיפוש
     */
    switchSearchType(searchType) {
        this.currentSearchType = searchType;
        
        try {
            this.currentSearch = new ConfigurableSearch(searchType);
        } catch (error) {
            this.showError(`שגיאה בטעינת סוג חיפוש: ${error.message}`);
            return;
        }
        
        // עדכון כפתורים
        document.querySelectorAll('.search-type-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.searchType === searchType);
        });
        
        // עדכון תנאי סינון
        this.updateFilterDisplay();
        
        // עדכון שדות מתקדמים
        this.updateAdvancedFields();
        
        // ניקוי תוצאות
        this.hideResults();
    }

    /**
     * עדכון תצוגת תנאי סינון
     */
    updateFilterDisplay() {
        const filterInfo = document.getElementById('filter-info');
        const filterList = document.getElementById('filter-list');
        
        if (!this.currentSearch.config.filters?.required) {
            filterInfo.style.display = 'none';
            return;
        }
        
        filterInfo.style.display = 'block';
        filterList.innerHTML = '';
        
        Object.entries(this.currentSearch.config.filters.required).forEach(([field, condition]) => {
            const div = document.createElement('div');
            div.innerHTML = `• <strong>${field}</strong> ${condition.operator} ${condition.value ?? 'null'}`;
            filterList.appendChild(div);
        });
    }

    /**
     * עדכון שדות החיפוש המתקדם
     */
    updateAdvancedFields() {
        const container = document.getElementById('advanced-fields');
        if (!container) return;
        
        container.innerHTML = '';
        
        const fields = this.currentSearch.config.searchFields.advanced;
        const displayLabels = this.currentSearch.getDisplayLabels();
        
        // בדיקה אם זה חיפוש נפטרים - קיבוץ לקטגוריות
        if (this.currentSearchType === 'deceased_search') {
            this.createGroupedFields(container, fields, displayLabels);
        } else {
            this.createSimpleFields(container, fields, displayLabels);
        }
    }

    /**
     * יצירת שדות מקובצים
     */
    createGroupedFields(container, fields, displayLabels) {
        const groups = {
            personal: {
                title: '👤 פרטי הנפטר',
                fields: ['firstName', 'lastName', 'fatherName', 'motherName']
            },
            location: {
                title: '📍 מיקום הקבר',
                fields: ['cemeteryName', 'blockName', 'plotName', 'areaName', 'lineName', 'graveName']
            },
            dates: {
                title: '📅 תאריכים',
                fields: ['deathDate', 'burialDate']
            }
        };
        
        Object.entries(groups).forEach(([groupKey, group]) => {
            const section = document.createElement('div');
            section.className = 'field-section';
            
            const title = document.createElement('h4');
            title.innerHTML = group.title;
            section.appendChild(title);
            
            const grid = document.createElement('div');
            grid.className = 'field-grid';
            
            group.fields.forEach(fieldKey => {
                if (fields[fieldKey]) {
                    const fieldElement = this.createFieldElement(
                        fieldKey,
                        fields[fieldKey],
                        displayLabels,
                        fieldKey.includes('Date') ? 'date' : 'text'
                    );
                    grid.appendChild(fieldElement);
                }
            });
            
            section.appendChild(grid);
            container.appendChild(section);
        });
    }

    /**
     * יצירת שדות פשוטים
     */
    createSimpleFields(container, fields, displayLabels) {
        Object.entries(fields).forEach(([key, dbField]) => {
            const fieldElement = this.createFieldElement(key, dbField, displayLabels);
            container.appendChild(fieldElement);
        });
    }

    /**
     * יצירת אלמנט שדה
     */
    createFieldElement(key, dbField, displayLabels, type = 'text') {
        const div = document.createElement('div');
        div.className = 'form-group';
        
        const label = document.createElement('label');
        label.className = 'form-label';
        label.htmlFor = `adv-${key}`;
        label.textContent = displayLabels[dbField] || this.formatFieldName(dbField);
        
        const input = document.createElement('input');
        input.type = type;
        input.id = `adv-${key}`;
        input.className = 'form-input';
        input.placeholder = type === 'date' ? '' : `הקלד ${label.textContent}...`;
        
        div.appendChild(label);
        div.appendChild(input);
        
        return div;
    }

    /**
     * חיפוש פשוט
     */
    async performSimpleSearch() {
        const query = document.getElementById('simple-query')?.value.trim();
        
        if (!query || query.length < SearchConfig.settings.minSearchLength) {
            this.showToast(`יש להזין לפחות ${SearchConfig.settings.minSearchLength} תווים`, 'warning');
            return;
        }
        
        this.showLoading(true);
        
        try {
            const results = await this.executeSearch(query, 'simple');
            this.displayResults(results);
        } catch (error) {
            this.showError(`שגיאה בחיפוש: ${error.message}`);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * חיפוש מתקדם
     */
    async performAdvancedSearch() {
        const params = this.collectAdvancedParams();
        
        if (Object.keys(params).length === 0) {
            this.showToast('יש למלא לפחות שדה אחד', 'warning');
            return;
        }
        
        this.showLoading(true);
        
        try {
            const results = await this.executeSearch(params, 'advanced');
            this.displayResults(results);
        } catch (error) {
            this.showError(`שגיאה בחיפוש מתקדם: ${error.message}`);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * איסוף פרמטרים מתקדמים
     */
    collectAdvancedParams() {
        const params = {};
        const fields = this.currentSearch.config.searchFields.advanced;
        
        Object.keys(fields).forEach(key => {
            const input = document.getElementById(`adv-${key}`);
            if (input?.value.trim()) {
                params[key] = input.value.trim();
            }
        });
        
        return params;
    }

    /**
     * ביצוע החיפוש
     */
    async executeSearch(queryOrParams, mode) {
        const startTime = performance.now();
        
        let results = [];
        
        if (this.currentDataSource === 'JSON') {
            // חיפוש ב-JSON
            const data = await this.getJSONData();
            const searchParams = mode === 'simple' 
                ? { query: queryOrParams }
                : queryOrParams;
            
            results = this.searchInJSON(data, searchParams, mode);
        } else {
            // חיפוש דרך API
            results = await this.searchViaAPI(queryOrParams, mode);
        }
        
        const endTime = performance.now();
        this.lastSearchTime = ((endTime - startTime) / 1000).toFixed(2);
        
        return results;
    }

    /**
     * חיפוש בנתוני JSON
     */
    searchInJSON(data, searchParams, mode) {
        let results = [];
        
        if (mode === 'simple') {
            results = this.simpleJSONSearch(data, searchParams.query);
        } else {
            results = this.advancedJSONSearch(data, searchParams);
        }
        
        // החזרת רק השדות הרצויים
        const returnFields = this.currentSearch.config.returnFields;
        return results.map(record => {
            const formatted = {};
            returnFields.forEach(field => {
                formatted[field] = record[field] ?? null;
            });
            return formatted;
        });
    }

    /**
     * חיפוש פשוט ב-JSON
     */
    simpleJSONSearch(data, query) {
        const searchTerms = query.toLowerCase().split(' ').filter(t => t);
        const searchFields = this.currentSearch.config.searchFields.simple;
        
        return data.filter(record => {
            // בדיקת תנאי סינון
            if (!this.currentSearch.matchesFilters(record)) {
                return false;
            }
            
            // בניית טקסט לחיפוש
            const searchText = searchFields
                .map(field => (record[field] ?? '').toString())
                .join(' ')
                .toLowerCase();
            
            // בדיקה שכל המילים נמצאות
            return searchTerms.every(term => searchText.includes(term));
        });
    }

    /**
     * חיפוש מתקדם ב-JSON
     */
    advancedJSONSearch(data, params) {
        const fieldMapping = this.currentSearch.config.searchFields.advanced;
        
        return data.filter(record => {
            // בדיקת תנאי סינון
            if (!this.currentSearch.matchesFilters(record)) {
                return false;
            }
            
            // בדיקת כל פרמטר
            for (const [uiField, dbField] of Object.entries(fieldMapping)) {
                if (params[uiField]) {
                    const searchValue = params[uiField].toLowerCase();
                    const recordValue = (record[dbField] ?? '').toString().toLowerCase();
                    
                    if (!recordValue.includes(searchValue)) {
                        return false;
                    }
                }
            }
            
            return true;
        });
    }

    /**
     * טעינת נתוני JSON
     */
    async getJSONData() {
        if (this.jsonData) {
            return this.jsonData;
        }
        
        try {
            const response = await fetch(this.dataSources.JSON.endpoint);
            if (!response.ok) {
                throw new Error('Failed to load JSON data');
            }
            this.jsonData = await response.json();
            console.log(`Loaded ${this.jsonData.length} records from JSON`);
            return this.jsonData;
        } catch (error) {
            console.error('Error loading JSON:', error);
            return [];
        }
    }

    /**
     * טעינה מוקדמת של נתוני JSON
     */
    async preloadJSONData() {
        if (this.currentDataSource === 'JSON') {
            await this.getJSONData();
        }
    }

    /**
     * הצגת תוצאות
     */
    displayResults(results) {
        // עדכון מספרים
        document.getElementById('result-count').textContent = results.length;
        document.getElementById('search-time').textContent = this.lastSearchTime;
        document.getElementById('result-source').textContent = this.dataSources[this.currentDataSource].name;
        
        // הצגת הסעיף
        document.getElementById('results-section').style.display = 'block';
        
        // הצגת הטבלה
        this.resultsTable.display(results, this.currentSearch.config, this.currentSearchType);
        
        // גלילה לתוצאות
        document.getElementById('results-section').scrollIntoView({ behavior: 'smooth' });
    }

    /**
     * החלפת מקור נתונים
     */
    toggleDataSource() {
        const toggle = document.getElementById('dataSourceToggle');
        const newSource = toggle.checked ? 'API' : 'JSON';
        
        // בדיקה אם המקור פעיל
        if (!this.dataSources[newSource].active) {
            this.showToast(`${this.dataSources[newSource].name} אינו פעיל כרגע`, 'warning');
            toggle.checked = !toggle.checked;
            return;
        }
        
        this.currentDataSource = newSource;
        
        // עדכון תצוגה
        document.getElementById('currentSource').textContent = this.dataSources[newSource].name;
        document.getElementById('sourceStatus').className = 'source-status source-active';
        
        console.log('Data source switched to:', newSource);
    }

    /**
     * ניקוי טופס מתקדם
     */
    clearAdvancedForm() {
        document.querySelectorAll('#advanced-fields input').forEach(input => {
            input.value = '';
        });
        this.showToast('הטופס נוקה', 'success');
    }

    /**
     * ייצוא לאקסל
     */
    exportToExcel() {
        this.resultsTable.exportToExcel();
        this.showToast('הקובץ הורד בהצלחה', 'success');
    }

    /**
     * הדפסה
     */
    printResults() {
        this.resultsTable.printResults();
    }

    /**
     * הצגת/הסתרת טעינה
     */
    showLoading(show) {
        const loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = show ? 'flex' : 'none';
        }
    }

    /**
     * הסתרת תוצאות
     */
    hideResults() {
        const resultsSection = document.getElementById('results-section');
        if (resultsSection) {
            resultsSection.style.display = 'none';
        }
    }

    /**
     * הצגת הודעה
     */
    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * הצגת שגיאה
     */
    showError(message) {
        console.error(message);
        this.showToast(message, 'error');
    }

    /**
     * עיצוב שם שדה
     */
    formatFieldName(field) {
        return field
            .replace(/_/g, ' ')
            .replace(/([A-Z])/g, ' $1')
            .trim()
            .replace(/^./, str => str.toUpperCase());
    }

    /**
     * הוספת מאזינים
     */
    attachEventListeners() {
        // מאזין לבחירת שורה
        document.addEventListener('rowSelected', (e) => {
            console.log('Row selected:', e.detail);
        });
    }
}

// ייצוא המחלקה
window.SearchApp = SearchApp;