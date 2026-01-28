/**
 * UI Controller - בקר ממשק המשתמש
 * מותאם למבנה הנתונים החדש
 */

class UIController {
    constructor() {
        this.currentTab = 'simple';
        this.isLoading = false;
        this.currentResults = [];
        this.init();
    }

    init() {
        // אתחול אירועים
        this.attachEventListeners();
        
        // טעינת הגדרות
        this.loadSettings();
        
        // בדיקת חיבור
        this.testConnection();
    }

    /**
     * חיבור אירועים
     */
    attachEventListeners() {
        // Date type radio buttons
        const dateRadios = document.querySelectorAll('input[name="date-type"]');
        dateRadios.forEach(radio => {
            radio.addEventListener('change', (e) => this.handleDateTypeChange(e.target.value));
        });

        // Enter key for simple search
        const simpleInput = document.getElementById('simple-query');
        if (simpleInput) {
            simpleInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    window.performSimpleSearch();
                }
            });
        }
    }

    /**
     * החלפת טאב
     */
    switchTab(tab) {
        this.currentTab = tab;
        
        // עדכון כפתורי הטאבים
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // עדכון תוכן
        document.querySelectorAll('.search-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tab}-search`).classList.add('active');
    }

    /**
     * שינוי סוג תאריך
     */
    handleDateTypeChange(type) {
        // הסתרת כל שדות התאריך
        document.getElementById('date-range-fields').style.display = 'none';
        document.getElementById('date-estimated-fields').style.display = 'none';
        
        // הצגת השדות הרלוונטיים
        if (type === 'range') {
            document.getElementById('date-range-fields').style.display = 'block';
        } else if (type === 'estimated') {
            document.getElementById('date-estimated-fields').style.display = 'block';
        }
    }

    /**
     * ביצוע חיפוש פשוט
     */
    async performSimpleSearch() {
        const query = document.getElementById('simple-query').value.trim();
        
        if (!query) {
            this.showMessage('יש להזין טקסט לחיפוש', 'warning');
            return;
        }

        if (query.length < 2) {
            this.showMessage('יש להזין לפחות 2 תווים', 'warning');
            return;
        }

        this.setLoading(true);
        const startTime = Date.now();

        try {
            const results = await window.dataService.simpleSearch(query);
            const searchTime = Date.now() - startTime;
            
            this.displayResults(results, searchTime);
            
        } catch (error) {
            console.error('Search error:', error);
            this.showMessage('שגיאה בביצוע החיפוש: ' + error.message, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * ביצוע חיפוש מתקדם
     */
    async performAdvancedSearch() {
        const params = this.collectAdvancedSearchParams();
        
        // בדיקה שיש לפחות פרמטר אחד
        if (!this.hasSearchParams(params)) {
            this.showMessage('יש למלא לפחות שדה אחד לחיפוש', 'warning');
            return;
        }

        this.setLoading(true);
        const startTime = Date.now();

        try {
            const results = await window.dataService.advancedSearch(params);
            const searchTime = Date.now() - startTime;
            
            this.displayResults(results, searchTime);
            
        } catch (error) {
            console.error('Advanced search error:', error);
            this.showMessage('שגיאה בביצוע החיפוש המתקדם: ' + error.message, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * איסוף פרמטרים לחיפוש מתקדם
     */
    collectAdvancedSearchParams() {
        const params = {};
        
        // שמות
        const firstName = document.getElementById('adv-first-name').value.trim();
        if (firstName) params.firstName = firstName;
        
        const lastName = document.getElementById('adv-last-name').value.trim();
        if (lastName) params.lastName = lastName;
        
        const fatherName = document.getElementById('adv-father-name').value.trim();
        if (fatherName) params.fatherName = fatherName;
        
        const motherName = document.getElementById('adv-mother-name').value.trim();
        if (motherName) params.motherName = motherName;
        
        // תאריכים
        const dateType = document.querySelector('input[name="date-type"]:checked').value;
        params.dateType = dateType;
        
        if (dateType === 'range') {
            const fromYear = document.getElementById('adv-from-year').value;
            const toYear = document.getElementById('adv-to-year').value;
            if (fromYear) params.fromYear = fromYear;
            if (toYear) params.toYear = toYear;
        } else if (dateType === 'estimated') {
            const estimatedYear = document.getElementById('adv-estimated-year').value;
            if (estimatedYear) params.estimatedYear = estimatedYear;
        }
        
        // מיקום
        const city = document.getElementById('adv-city').value;
        if (city) params.city = city;
        
        const cemetery = document.getElementById('adv-cemetery').value;
        if (cemetery) params.cemetery = cemetery;
        
        return params;
    }

    /**
     * בדיקה האם יש פרמטרים לחיפוש
     */
    hasSearchParams(params) {
        const relevantParams = Object.keys(params).filter(key => 
            key !== 'dateType' && key !== 'limit' && key !== 'offset'
        );
        return relevantParams.length > 0;
    }

    /**
     * הצגת תוצאות
     */
    displayResults(data, searchTime) {
        const resultsSection = document.getElementById('results-section');
        const resultsGrid = document.getElementById('results-grid');
        const resultsCount = document.getElementById('results-count');
        const searchTimeEl = document.getElementById('search-time');
        const noResults = document.getElementById('no-results');
        
        // הצגת סעיף התוצאות
        resultsSection.style.display = 'block';
        
        // עדכון מספר תוצאות וזמן חיפוש
        resultsCount.textContent = data.total || 0;
        searchTimeEl.textContent = `(${(searchTime / 1000).toFixed(2)} שניות)`;
        
        // ניקוי תוצאות קודמות
        resultsGrid.innerHTML = '';
        
        if (!data.results || data.results.length === 0) {
            resultsGrid.style.display = 'none';
            noResults.style.display = 'block';
            return;
        }
        
        resultsGrid.style.display = 'grid';
        noResults.style.display = 'none';
        
        // יצירת כרטיסי תוצאות
        data.results.forEach(result => {
            const card = this.createResultCard(result);
            resultsGrid.appendChild(card);
        });
        
        // גלילה לתוצאות
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * יצירת כרטיס תוצאה
     */
    createResultCard(result) {
        const card = document.createElement('div');
        card.className = 'result-card';
        
        // כותרת - שם הנפטר
        const fullName = result.deceased?.fullName || 
                         `${result.deceased?.firstName || ''} ${result.deceased?.lastName || ''}`.trim() ||
                         'ללא שם';
        
        // תאריכים
        const birthDate = this.formatDate(result.deceased?.birthDate);
        const deathDate = this.formatDate(result.burial?.deathDate);
        const birthDateHe = result.deceased?.birthDateHe;
        
        // מיקום
        const location = result.grave?.location || this.buildLocation(result.grave);
        
        // הורים
        const fatherName = result.deceased?.fatherName || '';
        const motherName = result.deceased?.motherName || '';
        
        card.innerHTML = `
            <div class="result-header">
                <h3 class="result-name">${fullName}</h3>
                <span class="result-id">#${result.grave?.id || ''}</span>
            </div>
            
            <div class="result-details">
                ${birthDate || birthDateHe ? `
                    <div class="detail-row">
                        <span class="detail-label">תאריך לידה:</span>
                        <span class="detail-value">
                            ${birthDate}
                            ${birthDateHe ? `<span class="hebrew-date">(${birthDateHe})</span>` : ''}
                        </span>
                    </div>
                ` : ''}
                
                ${deathDate ? `
                    <div class="detail-row">
                        <span class="detail-label">תאריך פטירה:</span>
                        <span class="detail-value">${deathDate}</span>
                    </div>
                ` : ''}
                
                ${fatherName || motherName ? `
                    <div class="detail-row">
                        <span class="detail-label">הורים:</span>
                        <span class="detail-value">
                            ${fatherName ? `אב: ${fatherName}` : ''}
                            ${fatherName && motherName ? ' | ' : ''}
                            ${motherName ? `אם: ${motherName}` : ''}
                        </span>
                    </div>
                ` : ''}
                
                ${location ? `
                    <div class="detail-row">
                        <span class="detail-label">מיקום:</span>
                        <span class="detail-value location">${location}</span>
                    </div>
                ` : ''}
                
                ${result.burial?.burialLicense ? `
                    <div class="detail-row">
                        <span class="detail-label">רישיון קבורה:</span>
                        <span class="detail-value">${result.burial.burialLicense}</span>
                    </div>
                ` : ''}
            </div>
            
            <div class="result-footer">
                <span class="grave-status status-${result.grave?.status || 'unknown'}">
                    ${result.grave?.status || 'לא ידוע'}
                </span>
            </div>
        `;
        
        return card;
    }

    /**
     * בניית מיקום
     */
    buildLocation(grave) {
        if (!grave) return '';
        
        const parts = [];
        if (grave.cemetery) parts.push(grave.cemetery);
        if (grave.block) parts.push(`גוש: ${grave.block}`);
        if (grave.plot) parts.push(`חלקה: ${grave.plot}`);
        if (grave.line && grave.line !== '0') parts.push(`שורה: ${grave.line}`);
        if (grave.area) parts.push(`אזור: ${grave.area}`);
        if (grave.name) parts.push(`קבר: ${grave.name}`);
        
        return parts.join(', ');
    }

    /**
     * עיצוב תאריך
     */
    formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return '';
        
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            return date.toLocaleDateString('he-IL');
        } catch (error) {
            return dateString;
        }
    }

    /**
     * ניקוי טופס מתקדם
     */
    clearAdvancedForm() {
        document.getElementById('adv-first-name').value = '';
        document.getElementById('adv-last-name').value = '';
        document.getElementById('adv-father-name').value = '';
        document.getElementById('adv-mother-name').value = '';
        document.getElementById('adv-from-year').value = '';
        document.getElementById('adv-to-year').value = '';
        document.getElementById('adv-estimated-year').value = '';
        document.getElementById('adv-city').value = '';
        document.getElementById('adv-cemetery').value = '';
        document.getElementById('date-none').checked = true;
        this.handleDateTypeChange('none');
    }

    /**
     * הצגת/הסתרת טעינה
     */
    setLoading(loading) {
        this.isLoading = loading;
        const loadingEl = document.getElementById('loading');
        const resultsGrid = document.getElementById('results-grid');
        
        if (loading) {
            loadingEl.style.display = 'block';
            resultsGrid.style.display = 'none';
            document.getElementById('no-results').style.display = 'none';
        } else {
            loadingEl.style.display = 'none';
        }
    }

    /**
     * הצגת הודעה
     */
    showMessage(message, type = 'info') {
        // יצירת אלמנט הודעה
        const messageEl = document.createElement('div');
        messageEl.className = `message message-${type}`;
        messageEl.textContent = message;
        
        // הוספה לגוף הדף
        document.body.appendChild(messageEl);
        
        // הסרה אחרי 3 שניות
        setTimeout(() => {
            messageEl.remove();
        }, 3000);
    }

    /**
     * בדיקת חיבור
     */
    async testConnection() {
        try {
            const isConnected = await window.dataService.testConnection();
            if (isConnected) {
                console.log('✅ המערכת מחוברת ופעילה');
            } else {
                console.warn('⚠️ בעיית חיבור למערכת');
            }
        } catch (error) {
            console.error('❌ שגיאה בבדיקת חיבור:', error);
        }
    }

    /**
     * טעינת הגדרות
     */
    loadSettings() {
        // טעינת מקור נתונים
        const dataSource = localStorage.getItem('dataSource') || 'json';
        const toggle = document.getElementById('dataSourceToggle');
        if (toggle) {
            toggle.checked = dataSource === 'api';
        }
        this.updateDataSourceDisplay(dataSource);
    }

    /**
     * החלפת מקור נתונים
     */
    toggleDataSource() {
        const toggle = document.getElementById('dataSourceToggle');
        const source = toggle.checked ? 'api' : 'json';
        
        window.dataService.setDataSource(source);
        this.updateDataSourceDisplay(source);
        
        // ניקוי תוצאות
        document.getElementById('results-section').style.display = 'none';
        
        this.showMessage(`מקור נתונים שונה ל-${source.toUpperCase()}`, 'success');
    }

    /**
     * עדכון תצוגת מקור נתונים
     */
    updateDataSourceDisplay(source) {
        const currentSourceEl = document.getElementById('currentSource');
        if (currentSourceEl) {
            currentSourceEl.textContent = source === 'api' ? 
                'API (מסד נתונים)' : 'JSON (בדיקות)';
        }
    }
}

// יצירת instance גלובלי
window.uiController = new UIController();

// פונקציות גלובליות (לקריאה מה-HTML)
window.switchTab = (tab) => window.uiController.switchTab(tab);
window.performSimpleSearch = () => window.uiController.performSimpleSearch();
window.performAdvancedSearch = () => window.uiController.performAdvancedSearch();
window.clearAdvancedForm = () => window.uiController.clearAdvancedForm();
window.toggleDataSource = () => window.uiController.toggleDataSource();