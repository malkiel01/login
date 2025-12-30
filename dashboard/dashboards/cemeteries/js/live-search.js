/**
 * LiveSearch Class
 * Real-time search with debouncing and pagination
 */
class LiveSearch {
    constructor(config) {
        this.config = {
            searchInputId: 'searchInput',
            counterElementId: 'searchCounter',
            resultContainerId: 'tableBody',
            paginationContainerId: 'paginationContainer',
            apiEndpoint: '/api/search',
            debounceDelay: 300,
            itemsPerPage: 50,
            minSearchLength: 2,
            instanceName: 'liveSearch',
            renderFunction: this.defaultRender,
            ...config
        };
        
        this.currentPage = 1;
        this.totalResults = 0;
        this.totalAll = 0;
        this.isLoading = false;
        this.debounceTimer = null;
        this.lastQuery = '';
        
        this.init();
    }
    
    init() {
        const searchInput = document.getElementById(this.config.searchInputId);
        if (!searchInput) {
            console.error('LiveSearch: Input not found -', this.config.searchInputId);
            return;
        }
        
        searchInput.addEventListener('input', (e) => {
            this.handleSearchInput(e.target.value);
        });
        
        this.loadData('', 1);
    }
    
    handleSearchInput(query) {
        if (this.debounceTimer) clearTimeout(this.debounceTimer);
        
        this.debounceTimer = setTimeout(() => {
            if (query.length >= this.config.minSearchLength || query.length === 0) {
                this.currentPage = 1;
                this.lastQuery = query;
                this.loadData(query, 1);
            }
        }, this.config.debounceDelay);
    }
    
    async loadData(searchQuery = '', page = 1) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const params = new URLSearchParams({
                action: 'list',
                search: searchQuery,
                page: page,
                limit: this.config.itemsPerPage
            });
            
            const response = await fetch(this.config.apiEndpoint + '?' + params);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            
            const data = await response.json();
            
            if (data.success) {
                this.totalResults = data.pagination?.total || 0;
                this.totalAll = data.pagination?.totalAll || this.totalResults;
                this.currentPage = page;
                
                this.updateCounter(searchQuery);
                this.renderResults(data.data || []);
                this.renderPagination(data.pagination);
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        } catch (error) {
            console.error('LiveSearch error:', error);
            this.showError('שגיאה בטעינת נתונים');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }
    
    updateCounter(searchQuery) {
        const counter = document.getElementById(this.config.counterElementId);
        if (!counter) return;
        
        if (searchQuery) {
            counter.innerHTML = 
                '<span class="counter-filtered">נמצאו ' + this.totalResults + ' תוצאות</span>' +
                '<span class="counter-separator"> מתוך </span>' +
                '<span class="counter-total">' + this.totalAll + ' סה"כ</span>';
            counter.classList.add('active');
        } else {
            counter.innerHTML = '<span class="counter-total">סה"כ ' + this.totalAll + ' רשומות</span>';
            counter.classList.remove('active');
        }
    }
    
    renderResults(data) {
        const container = document.getElementById(this.config.resultContainerId);
        if (!container) return;
        
        if (data.length === 0) {
            container.innerHTML = this.getEmptyMessage();
            return;
        }
        
        this.config.renderFunction.call(this, data, container);
    }
    
    defaultRender(data, container) {
        container.innerHTML = data.map(item => 
            '<tr><td>' + (item.id || '-') + '</td><td>' + (item.name || '-') + '</td></tr>'
        ).join('');
    }
    
    renderPagination(pagination) {
        const container = document.getElementById(this.config.paginationContainerId);
        if (!container || !pagination) return;
        
        const totalPages = pagination.pages || 1;
        const currentPage = pagination.page || 1;
        
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<div class="pagination">';
        
        if (currentPage > 1) {
            html += '<button class="btn-pagination" onclick="' + this.config.instanceName + '.goToPage(' + (currentPage - 1) + ')">הקודם</button>';
        }
        
        html += '<span class="pagination-info">עמוד ' + currentPage + ' מתוך ' + totalPages + '</span>';
        
        if (currentPage < totalPages) {
            html += '<button class="btn-pagination" onclick="' + this.config.instanceName + '.goToPage(' + (currentPage + 1) + ')">הבא</button>';
        }
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    goToPage(page) {
        if (page < 1 || this.isLoading) return;
        this.loadData(this.lastQuery, page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    refresh() {
        this.loadData(this.lastQuery, this.currentPage);
    }
    
    showLoading() {
        const container = document.getElementById(this.config.resultContainerId);
        if (container) {
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';
        }
    }
    
    hideLoading() {
        const container = document.getElementById(this.config.resultContainerId);
        if (container) {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        }
    }
    
    showError(message) {
        const container = document.getElementById(this.config.resultContainerId);
        if (container) {
            container.innerHTML = 
                '<tr><td colspan="10" style="text-align:center;padding:40px;color:#dc2626;">' +
                '<div style="font-size:48px;margin-bottom:20px;">⚠️</div>' +
                '<div>' + message + '</div>' +
                '</td></tr>';
        }
    }
    
    getEmptyMessage() {
        return '<tr><td colspan="10" style="text-align:center;padding:40px;color:#999;">' +
               '<div style="font-size:48px;margin-bottom:20px;">🔍</div>' +
               '<div>לא נמצאו תוצאות</div>' +
               '</td></tr>';
    }
}

// Export to global scope
window.LiveSearch = LiveSearch;

