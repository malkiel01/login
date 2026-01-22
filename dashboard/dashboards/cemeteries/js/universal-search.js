/**
 * UniversalSearch - מערכת חיפוש אוניברסלית מתקדמת
 * @version 1.1.0
 * @updated 2025-11-12
 * @author Malkiel
 * 
 * שינויים ב-v1.1.0:
 * - שינוי itemsPerPage ל-apiLimit (ברור יותר)
 * - apiLimit: כמה רשומות לטעון מהשרת בכל בקשה
 * - תמיכה מלאה ב-Infinite Scroll עם טעינה מדורגת
 */

class UniversalSearch {
    constructor(config) {
        // ברירות מחדל
        this.config = {
            // מקור נתונים
            dataSource: {
                type: 'api',
                endpoint: '',
                action: 'search',
                tables: [],
                joins: [],
                method: 'POST'
            },
            
            // שדות חיפוש
            searchableFields: [],
            
            // הצגה
            display: {
                containerSelector: null,
                showFilters: true,
                showAdvanced: true,
                layout: 'horizontal',
                placeholder: 'חיפוש...',
                minSearchLength: 2,
                debounceDelay: 300
            },
            
            // תוצאות
            results: {
                containerSelector: null,
                apiLimit: 200,              // ⭐ כמה רשומות לטעון מהשרת בכל בקשה
                showPagination: true,
                renderFunction: null,
                columns: [],
                showCounter: true
            },
            
            // התנהגות
            behavior: {
                realTime: true,
                autoSubmit: true,
                saveHistory: false,
                exportable: false,
                highlightResults: true
            },
            
            // callbacks
            callbacks: {
                onSearch: null,
                onResults: null,
                onError: null,
                onEmpty: null,
                onInit: null
            },
            
            ...config
        };

        // State
        this.state = {
            currentQuery: '',
            activeFilters: new Map(),
            results: [],
            totalResults: 0,
            currentPage: 1,
            totalPages: 1,  // ⭐ הוסף
            isSearching: false,
            lastSearchTime: null
        };
        
        // DOM Elements
        this.elements = {};
        
        // Debounce timer
        this.debounceTimer = null;

        // ⭐ AbortController לביטול בקשות
        this.abortController = null;
        
        // Initialize
        this.init();
    }
    
    /**
     * אתחול המערכת
     */
    init() {
        
        // ולידציה
        if (!this.validate()) {
            console.error('❌ UniversalSearch: Invalid configuration');
            return;
        }
        
        // בניית UI
        this.buildUI();
        
        // קישור אירועים
        this.bindEvents();
        
        // callback
        if (this.config.callbacks.onInit) {
            this.config.callbacks.onInit(this);
        }
        
    }
    
    /**
     * ולידציה של התצורה
     */
    validate() {
        if (!this.config.dataSource.endpoint) {
            console.error('Missing dataSource.endpoint');
            return false;
        }
        
        if (!this.config.display.containerSelector) {
            console.error('Missing display.containerSelector');
            return false;
        }
        
        if (!this.config.results.containerSelector) {
            console.error('Missing results.containerSelector');
            return false;
        }
        
        if (!this.config.searchableFields || this.config.searchableFields.length === 0) {
            console.error('No searchable fields defined');
            return false;
        }
        
        return true;
    }
    
    /**
     * בניית ה-UI
     */
    buildUI() {
        const container = document.querySelector(this.config.display.containerSelector);
        if (!container) {
            console.error('Container not found:', this.config.display.containerSelector);
            return;
        }
        
        // נקה קונטיינר
        container.innerHTML = '';
        container.classList.add('universal-search-container');
        
        // HTML Structure
        const html = `
            <!-- כותרת סקשן חיפוש עם כפתור צימצום -->
            <div class="search-section-header">
                <div class="search-section-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <span>חיפוש מתקדם</span>
                </div>
                <button class="btn-collapse-search" onclick="UniversalSearch.toggleSearchSection(this)" title="צמצם חיפוש">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="18 15 12 9 6 15"></polyline>
                    </svg>
                    <span>צמצם</span>
                </button>
            </div>
            <div class="us-search-wrapper ${this.config.display.layout}">
                <!-- שדה חיפוש ראשי -->
                <div class="us-main-search">
                    <div class="us-search-input-wrapper">
                        <input 
                            type="text" 
                            class="us-search-input" 
                            placeholder="${this.config.display.placeholder}"
                            autocomplete="off"
                        />
                        <svg class="us-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <button class="us-clear-btn" style="display: none;">✕</button>
                    </div>
                    
                    ${this.config.display.showAdvanced ? `
                    <button class="us-advanced-toggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="4" y1="6" x2="20" y2="6"></line>
                            <line x1="4" y1="12" x2="20" y2="12"></line>
                            <line x1="4" y1="18" x2="20" y2="18"></line>
                        </svg>
                        פילטרים מתקדמים
                    </button>
                    ` : ''}
                </div>
                
                <!-- פילטרים מתקדמים -->
                ${this.config.display.showAdvanced ? `
                <div class="us-advanced-panel" style="display: none;">
                    <div class="us-filters-container">
                        ${this.buildFiltersHTML()}
                    </div>
                    <div class="us-actions">
                        <button class="us-btn us-btn-primary us-apply-filters">החל פילטרים</button>
                        <button class="us-btn us-btn-secondary us-reset-filters">נקה הכל</button>
                    </div>
                </div>
                ` : ''}
                
                <!-- מונה תוצאות -->
                ${this.config.results.showCounter ? `
                <div class="us-results-counter" style="display: none;">
                    <span class="us-counter-text"></span>
                </div>
                ` : ''}
            </div>
        `;
        
        container.innerHTML = html;
        
        // שמירת אלמנטים
        this.elements = {
            container: container,
            searchInput: container.querySelector('.us-search-input'),
            clearBtn: container.querySelector('.us-clear-btn'),
            advancedToggle: container.querySelector('.us-advanced-toggle'),
            advancedPanel: container.querySelector('.us-advanced-panel'),
            filtersContainer: container.querySelector('.us-filters-container'),
            applyFiltersBtn: container.querySelector('.us-apply-filters'),
            resetFiltersBtn: container.querySelector('.us-reset-filters'),
            resultsCounter: container.querySelector('.us-results-counter'),
            counterText: container.querySelector('.us-counter-text'),
            resultsContainer: document.querySelector(this.config.results.containerSelector)
        };
    }
    
    /**
     * בניית HTML של פילטרים
     */
    buildFiltersHTML() {
        return this.config.searchableFields.map(field => {
            const fieldId = `us-filter-${field.name}`;
            
            let inputHTML = '';
            
            switch (field.type) {
                case 'text':
                    inputHTML = `
                        <input 
                            type="text" 
                            id="${fieldId}" 
                            class="us-filter-input" 
                            data-field="${field.name}"
                            placeholder="${field.label}"
                        />
                        <select class="us-match-type" data-field="${field.name}">
                            ${(field.matchType || ['fuzzy']).map(type => `
                                <option value="${type}">${this.getMatchTypeLabel(type)}</option>
                            `).join('')}
                        </select>
                    `;
                    break;
                    
                case 'number':
                    inputHTML = `
                        <input 
                            type="number" 
                            id="${fieldId}" 
                            class="us-filter-input" 
                            data-field="${field.name}"
                            placeholder="${field.label}"
                        />
                        <select class="us-match-type" data-field="${field.name}">
                            ${(field.matchType || ['exact']).map(type => `
                                <option value="${type}">${this.getMatchTypeLabel(type)}</option>
                            `).join('')}
                        </select>
                    `;
                    break;
                    
                case 'date':
                    inputHTML = `
                        <input 
                            type="date" 
                            id="${fieldId}" 
                            class="us-filter-input" 
                            data-field="${field.name}"
                        />
                        <select class="us-match-type" data-field="${field.name}">
                            ${(field.matchType || ['exact']).map(type => `
                                <option value="${type}">${this.getMatchTypeLabel(type)}</option>
                            `).join('')}
                        </select>
                        <input 
                            type="date" 
                            id="${fieldId}-end" 
                            class="us-filter-input us-date-end" 
                            data-field="${field.name}"
                            style="display: none;"
                            placeholder="עד תאריך"
                        />
                    `;
                    break;
                    
                case 'select':
                    inputHTML = `
                        <select id="${fieldId}" class="us-filter-input" data-field="${field.name}">
                            <option value="">הכל</option>
                            ${(field.options || []).map(opt => `
                                <option value="${opt.value}">${opt.label}</option>
                            `).join('')}
                        </select>
                    `;
                    break;
            }
            
            return `
                <div class="us-filter-group">
                    <label class="us-filter-label">${field.label}</label>
                    <div class="us-filter-controls">
                        ${inputHTML}
                    </div>
                </div>
            `;
        }).join('');
    }
    
    /**
     * תרגום סוג התאמה לעברית
     */
    getMatchTypeLabel(type) {
        const labels = {
            exact: 'מדויק',
            fuzzy: 'משוער',
            startsWith: 'מתחיל ב',
            endsWith: 'מסתיים ב',
            greaterThan: 'גדול מ',
            lessThan: 'קטן מ',
            between: 'בין',
            notBetween: 'מחוץ לטווח',
            before: 'לפני',
            after: 'אחרי',
            today: 'היום',
            thisWeek: 'השבוע',
            thisMonth: 'החודש',
            thisYear: 'השנה'
        };
        
        return labels[type] || type;
    }
    
    /**
     * קישור אירועים
     */
    bindEvents() {
        // חיפוש ראשי
        this.elements.searchInput.addEventListener('input', (e) => {
            this.handleMainSearch(e.target.value);
            this.toggleClearButton(e.target.value);
        });
        
        // כפתור ניקוי
        if (this.elements.clearBtn) {
            this.elements.clearBtn.addEventListener('click', () => {
                this.clear();
            });
        }
        
        // toggle פילטרים מתקדמים
        if (this.elements.advancedToggle) {
            this.elements.advancedToggle.addEventListener('click', () => {
                this.toggleAdvancedPanel();
            });
        }
        
        // החלת פילטרים
        if (this.elements.applyFiltersBtn) {
            this.elements.applyFiltersBtn.addEventListener('click', () => {
                this.applyFilters();
            });
        }
        
        // איפוס פילטרים
        if (this.elements.resetFiltersBtn) {
            this.elements.resetFiltersBtn.addEventListener('click', () => {
                this.resetFilters();
            });
        }
        
        // שינוי סוג התאמה בתאריך - הצג/הסתר שדה שני
        if (this.elements.filtersContainer) {
            this.elements.filtersContainer.querySelectorAll('.us-match-type').forEach(select => {
                select.addEventListener('change', (e) => {
                    const field = e.target.dataset.field;
                    const matchType = e.target.value;
                    const fieldConfig = this.config.searchableFields.find(f => f.name === field);
                    
                    if (fieldConfig && fieldConfig.type === 'date') {
                        const endDateInput = e.target.closest('.us-filter-controls').querySelector('.us-date-end');
                        if (endDateInput) {
                            endDateInput.style.display = ['between', 'notBetween'].includes(matchType) ? 'block' : 'none';
                        }
                    }
                });
            });
        }
    }
    
    /**
     * טיפול בחיפוש ראשי
     */
    handleMainSearch(query) {
        this.state.currentQuery = query;
        
        if (query.length < this.config.display.minSearchLength && query.length > 0) {
            return;
        }
        
        if (this.config.behavior.realTime) {
            this.debouncedSearch();
        }
    }
    
    /**
     * חיפוש עם debounce
     */
    debouncedSearch() {
        clearTimeout(this.debounceTimer);
        
        // ⭐ בטל בקשה קודמת אם קיימת
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        
        this.debounceTimer = setTimeout(() => {
            this.search();
        }, this.config.display.debounceDelay);
    }
    
    /**
     * ביצוע חיפוש
     */
    async search() {
        // ⭐ אם כבר מחפשים, דלג
        if (this.state.isSearching) {
            return;
        }
        
        // ⭐ בטל בקשה קודמת אם קיימת
        if (this.abortController) {
            this.abortController.abort();
        }
        
        // ⭐ צור AbortController חדש
        this.abortController = new AbortController();
        const signal = this.abortController.signal;
        
        // callback לפני חיפוש
        if (this.config.callbacks.onSearch) {
            this.config.callbacks.onSearch(this.state.currentQuery, this.state.activeFilters);
        }
        
        this.state.isSearching = true;
        this.showLoading();
        
        try {
            // בניית payload
            const payload = this.buildSearchPayload();
            
            
            let response;
            
            // בדוק אם זה GET או POST
            if (this.config.dataSource.method === 'GET') {
                // שליחת GET עם query parameters
                const params = new URLSearchParams();
                params.append('action', payload.action);
                
                if (payload.query) {
                    params.append('search', payload.query);
                }
                
                if (payload.page) {
                    params.append('page', payload.page);
                }
                
                if (payload.limit) {
                    params.append('limit', payload.limit);
                }
                
                // ⭐ הוסף plotId אם קיים
                if (payload.plotId) {
                    params.append('plotId', payload.plotId);
                }
                
                // הוסף פילטרים
                payload.filters.forEach((filter, index) => {
                    params.append(`filter_${index}_field`, filter.field);
                    params.append(`filter_${index}_value`, filter.value);
                    params.append(`filter_${index}_type`, filter.matchType);
                });

                // ⭐ הוסף את כל ה-params מה-dataSource (במקום רק plotId)!
                if (this.config.dataSource.params) {
                    Object.entries(this.config.dataSource.params).forEach(([key, value]) => {
                        params.append(key, value);
                    });
                }
                
                const url = `${this.config.dataSource.endpoint}?${params.toString()}`;
                
                // ⭐ הוסף signal ל-fetch!
                response = await fetch(url, { signal });
                
            } else {
                // שליחת POST עם body
                response = await fetch(this.config.dataSource.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                    signal  // ⭐ הוסף signal!
                });
            }
            
            const data = await response.json();
            
            
            // ⭐ עדכן pagination state - כאן! אחרי fetch!
            if (data.pagination) {
                this.state.totalPages = data.pagination.pages || 1;
                this.state.currentPage = data.pagination.page || 1;
            }
            
            if (data.success) {
                this.state.results = data.data || [];
                this.state.totalResults = data.pagination?.total || data.total || data.data.length;
                this.state.lastSearchTime = Date.now();
                
                // ⭐ העבר גם pagination!
                this.renderResults(data.data, data.pagination);

                // ⭐ קרא ל-onResults לפני updateCounter!
                    if (this.config.callbacks.onResults) {
                        this.config.callbacks.onResults(data);
                    }
                    
                    // ⭐ עכשיו עדכן את המונה (אחרי onResults)
                    this.updateCounter();
                
                if (this.config.callbacks.onDataLoaded) {
                    this.config.callbacks.onDataLoaded(data);
                }
                
                if (data.data.length === 0 && this.config.callbacks.onEmpty) {
                    this.config.callbacks.onEmpty();
                }
            } else {
                throw new Error(data.error || 'Search failed');
            }
            
        } catch (error) {
            // ⭐ אם זה AbortError, זה לא באמת שגיאה!
            if (error.name === 'AbortError') {
                return;
            }
            
            console.error('❌ Search error:', error);
            
            if (this.config.callbacks.onError) {
                this.config.callbacks.onError(error);
            }
            
            this.showError(error.message);
            
        } finally {
            this.state.isSearching = false;
            this.hideLoading();
            
            // ⭐ נקה את ה-AbortController
            if (this.abortController) {
                this.abortController = null;
            }
        }
    }
    
    /**
     * בניית payload לשליחה
     */
    buildSearchPayload() {
        const payload = {
            action: this.config.dataSource.action,
            query: this.state.currentQuery,
            tables: this.config.dataSource.tables,
            joins: this.config.dataSource.joins,
            filters: [],
            page: this.state.currentPage,
            apiLimit: this.config.results.apiLimit,
            ...this.config.dataSource.params
        };
        
        // הוספת פילטרים
        this.state.activeFilters.forEach((filter, fieldName) => {
            payload.filters.push({
                field: fieldName,
                value: filter.value,
                matchType: filter.matchType,
                valueEnd: filter.valueEnd || null,
                table: filter.table
            });
        });
        
        return payload;
    }
    
    /**
     * החלת פילטרים מתקדמים
     */
    applyFilters() {
        this.state.activeFilters.clear();
        
        this.config.searchableFields.forEach(field => {
            const input = this.elements.filtersContainer.querySelector(`[data-field="${field.name}"]`);
            const matchTypeSelect = this.elements.filtersContainer.querySelector(`.us-match-type[data-field="${field.name}"]`);
            
            if (input && input.value) {
                const filter = {
                    value: input.value,
                    matchType: matchTypeSelect ? matchTypeSelect.value : 'exact',
                    table: field.table || this.config.dataSource.tables[0]
                };
                
                // אם זה תאריך עם טווח
                if (field.type === 'date' && ['between', 'notBetween'].includes(filter.matchType)) {
                    const endInput = this.elements.filtersContainer.querySelector(`#us-filter-${field.name}-end`);
                    if (endInput && endInput.value) {
                        filter.valueEnd = endInput.value;
                    }
                }
                
                this.state.activeFilters.set(field.name, filter);
            }
        });
        
        
        this.search();
    }
    
    /**
     * איפוס פילטרים
     */
    resetFilters() {
        this.state.activeFilters.clear();
        
        // נקה את כל השדות
        this.elements.filtersContainer.querySelectorAll('.us-filter-input').forEach(input => {
            input.value = '';
        });
        
        this.elements.filtersContainer.querySelectorAll('.us-match-type').forEach(select => {
            select.selectedIndex = 0;
        });
        
        // הסתר שדות תאריך שניים
        this.elements.filtersContainer.querySelectorAll('.us-date-end').forEach(input => {
            input.style.display = 'none';
        });
        
        this.search();
    }
    
    /**
     * רינדור תוצאות
     */
    renderResults(data, pagination = null) {
        if (!this.elements.resultsContainer) return;
        
        if (this.config.results.renderFunction) {
            // ✅ העבר pagination!
            this.config.results.renderFunction(
                data, 
                this.elements.resultsContainer,
                pagination,
                null  // signal
            );
        } else {
            this.defaultRender(data);
        }
    }
    
    /**
     * רינדור ברירת מחדל
     */
    defaultRender(data) {
        if (data.length === 0) {
            this.elements.resultsContainer.innerHTML = `
                <tr>
                    <td colspan="100" style="text-align: center; padding: 40px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 10px;">🔍</div>
                        <div>לא נמצאו תוצאות</div>
                    </td>
                </tr>
            `;
            return;
        }
        
        const html = data.map(item => {
            const cells = this.config.results.columns.map(col => {
                let value = item[col] || '-';
                
                // הדגשת תוצאות
                if (this.config.behavior.highlightResults && this.state.currentQuery) {
                    value = this.highlightText(String(value), this.state.currentQuery);
                }
                
                return `<td>${value}</td>`;
            }).join('');
            
            return `<tr data-id="${item.unicId || item.id}">${cells}</tr>`;
        }).join('');
        
        this.elements.resultsContainer.innerHTML = html;
    }
    
    /**
     * הדגשת טקסט
     */
    highlightText(text, query) {
        if (!query) return text;
        
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    /**
     * עדכון מונה
     */
    updateCounter() {
        if (!this.elements.resultsCounter) return;
        
        if (this.state.totalResults > 0) {
            this.elements.counterText.textContent = `נמצאו ${this.state.totalResults} תוצאות`;
            this.elements.resultsCounter.style.display = 'block';
        } else {
            this.elements.resultsCounter.style.display = 'none';
        }
    }
    
    /**
     * toggle פאנל מתקדם
     */
    toggleAdvancedPanel() {
        const panel = this.elements.advancedPanel;
        const isVisible = panel.style.display !== 'none';
        
        panel.style.display = isVisible ? 'none' : 'block';
        this.elements.advancedToggle.classList.toggle('active', !isVisible);
    }
    
    /**
     * toggle כפתור ניקוי
     */
    toggleClearButton(value) {
        if (this.elements.clearBtn) {
            this.elements.clearBtn.style.display = value ? 'block' : 'none';
        }
    }
    
    /**
     * ניקוי חיפוש
     */
    clear() {
        this.elements.searchInput.value = '';
        this.state.currentQuery = '';
        this.toggleClearButton('');
        this.resetFilters();
    }
    
    /**
     * הצגת loading
     */
    showLoading() {
        if (this.elements.resultsContainer) {
            this.elements.resultsContainer.classList.add('us-loading');
        }
    }
    
    /**
     * הסרת loading
     */
    hideLoading() {
        if (this.elements.resultsContainer) {
            this.elements.resultsContainer.classList.remove('us-loading');
        }
    }
    
    /**
     * הצגת שגיאה
     */
    showError(message) {
        if (this.elements.resultsContainer) {
            this.elements.resultsContainer.innerHTML = `
                <tr>
                    <td colspan="100" style="text-align: center; padding: 40px; color: #ef4444;">
                        <div style="font-size: 48px; margin-bottom: 10px;">⚠️</div>
                        <div><strong>שגיאה בחיפוש:</strong></div>
                        <div style="margin-top: 10px;">${message}</div>
                    </td>
                </tr>
            `;
        }
    }
    
    /**
     * API ציבורי
     */
    
    // הוספת פילטר
    addFilter(field, matchType, value, valueEnd = null) {
        const fieldConfig = this.config.searchableFields.find(f => f.name === field);
        if (!fieldConfig) {
            return;
        }
        
        this.state.activeFilters.set(field, {
            value,
            matchType,
            valueEnd,
            table: fieldConfig.table || this.config.dataSource.tables[0]
        });
        
        this.search();
    }
    
    // הסרת פילטר
    removeFilter(field) {
        this.state.activeFilters.delete(field);
        this.search();
    }
    
    // קבלת תוצאות
    getResults() {
        return this.state.results;
    }
    
    // רענון
    refresh() {
        this.search();
    }
}

UniversalSearch.prototype.loadNextPage = async function() {
    if (this.state.currentPage >= this.state.totalPages) {
        return false;
    }

    this.state.currentPage++;
    await this.search();
    return true;
};

/**
 * צמצום/הרחבה של סקשן החיפוש (static method)
 * @param {HTMLElement} btn - הכפתור שנלחץ
 */
UniversalSearch.toggleSearchSection = function(btn) {
    const searchSection = btn.closest('.search-section');
    if (!searchSection) return;

    const isCollapsed = searchSection.classList.toggle('collapsed');

    // עדכון כפתור הצמצום
    const btnText = btn.querySelector('span');
    const btnIcon = btn.querySelector('svg');

    if (isCollapsed) {
        if (btnText) btnText.textContent = 'הרחב';
        if (btnIcon) btnIcon.style.transform = 'rotate(180deg)';
    } else {
        if (btnText) btnText.textContent = 'צמצם';
        if (btnIcon) btnIcon.style.transform = 'rotate(0deg)';
    }

    // עדכון כפתור "הצג חיפוש" בשורת הפעולות
    const showSearchBtn = document.querySelector('.btn-show-search');
    if (showSearchBtn) {
        showSearchBtn.classList.toggle('visible', isCollapsed);
    }

    // שמירה ב-localStorage
    const entityType = searchSection.id.replace('SearchSection', '');
    const storageKey = 'searchSectionCollapsed';
    const collapsedSections = JSON.parse(localStorage.getItem(storageKey) || '{}');
    collapsedSections[entityType] = isCollapsed;
    localStorage.setItem(storageKey, JSON.stringify(collapsedSections));
};

/**
 * הרחבת סקשן החיפוש (קריאה מכפתור "הצג חיפוש")
 * @param {string} entityType - סוג היישות
 */
UniversalSearch.expandSearchSection = function(entityType) {
    const searchSection = document.getElementById(entityType + 'SearchSection');
    if (!searchSection) return;

    // הרחב את הסקשן
    searchSection.classList.remove('collapsed');

    // עדכון כפתור הצמצום בתוך הסקשן
    const collapseBtn = searchSection.querySelector('.btn-collapse-search');
    if (collapseBtn) {
        const btnText = collapseBtn.querySelector('span');
        const btnIcon = collapseBtn.querySelector('svg');
        if (btnText) btnText.textContent = 'צמצם';
        if (btnIcon) btnIcon.style.transform = 'rotate(0deg)';
    }

    // הסתר כפתור "הצג חיפוש"
    const showSearchBtn = document.querySelector('.btn-show-search');
    if (showSearchBtn) {
        showSearchBtn.classList.remove('visible');
    }

    // עדכון localStorage
    const storageKey = 'searchSectionCollapsed';
    const collapsedSections = JSON.parse(localStorage.getItem(storageKey) || '{}');
    collapsedSections[entityType] = false;
    localStorage.setItem(storageKey, JSON.stringify(collapsedSections));
};

/**
 * טעינת מצב שמור של סקשן החיפוש
 * @param {string} entityType - סוג היישות
 */
UniversalSearch.loadSearchSectionState = function(entityType) {
    const storageKey = 'searchSectionCollapsed';
    const collapsedSections = JSON.parse(localStorage.getItem(storageKey) || '{}');
    const isMobile = window.innerWidth <= 768;

    // במסכים קטנים - ברירת מחדל מכווץ (אלא אם נשמר אחרת)
    // במסכים גדולים - ברירת מחדל פתוח (אלא אם נשמר אחרת)
    const shouldCollapse = collapsedSections[entityType] !== undefined
        ? collapsedSections[entityType]
        : isMobile; // ברירת מחדל: מכווץ במובייל, פתוח בדסקטופ

    if (shouldCollapse) {
        const searchSection = document.getElementById(entityType + 'SearchSection');
        if (searchSection) {
            searchSection.classList.add('collapsed');

            // עדכון כפתור הצמצום
            const collapseBtn = searchSection.querySelector('.btn-collapse-search');
            if (collapseBtn) {
                const btnText = collapseBtn.querySelector('span');
                const btnIcon = collapseBtn.querySelector('svg');
                if (btnText) btnText.textContent = 'הרחב';
                if (btnIcon) btnIcon.style.transform = 'rotate(180deg)';
            }

            // הצג כפתור "הצג חיפוש"
            const showSearchBtn = document.querySelector('.btn-show-search');
            if (showSearchBtn) {
                showSearchBtn.classList.add('visible');
            }
        }
    }
};

/**
 * טוגל או פוקוס בחיפוש - אם מכווץ יפתח, אם פתוח יתמקד בשדה
 * @param {string} entityType - סוג היישות
 */
UniversalSearch.toggleOrFocusSearch = function(entityType) {
    const searchSection = document.getElementById(entityType + 'SearchSection');
    if (!searchSection) return;

    const isCollapsed = searchSection.classList.contains('collapsed');

    if (isCollapsed) {
        // פתח את החיפוש
        UniversalSearch.expandSearchSection(entityType);
    }

    // תמיד התמקד בשדה החיפוש
    setTimeout(() => {
        const searchInput = searchSection.querySelector('.us-search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }, isCollapsed ? 350 : 0); // המתן לאנימציה אם היה מכווץ
};

// הפוך לגלובלי
window.UniversalSearch = UniversalSearch;