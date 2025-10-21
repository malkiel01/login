/**
 * TableManager - מערכת טבלאות מתקדמת
 * תכונות: מיון, שינוי גודל, שינוי סדר, תפריט עמודה, סינון, Infinite Scroll
 * תמיכה מלאה ב-RTL
 */

class TableManager {
    constructor(config) {
        this.config = {
            tableSelector: null,
            columns: [],
            data: [],
            sortable: true,
            resizable: true,
            reorderable: true,
            filterable: true,
            renderCell: null,
            onSort: null,
            onFilter: null,
            onColumnReorder: null,
            infiniteScroll: true,
            itemsPerPage: 100,
            scrollThreshold: 200, // פיקסלים מהתחתית לטעינה
            ...config
        };
        
        this.state = {
            sortColumn: null,
            sortOrder: 'asc',
            columnWidths: {},
            columnOrder: [],
            filters: new Map(),
            isResizing: false,
            isDragging: false,
            currentPage: 1,
            isLoading: false,
            filteredData: [],
            displayedData: []
        };
        
        this.elements = {
            table: null,
            thead: null,
            tbody: null,
            scrollContainer: null
        };
        
        this.init();
    }
    
    /**
     * אתחול
     */
    init() {
        this.elements.table = document.querySelector(this.config.tableSelector);
        
        if (!this.elements.table) {
            console.error('Table not found:', this.config.tableSelector);
            return;
        }
        
        // וודא שהטבלה בתוך .table-container
        this.ensureTableContainer();
        
        // אתחול סדר עמודות
        this.state.columnOrder = this.config.columns.map((col, index) => index);
        
        // אתחול רוחב עמודות
        this.config.columns.forEach((col, index) => {
            this.state.columnWidths[index] = col.width || 'auto';
        });
        
        // בניית הטבלה
        this.buildTable();
        
        // קישור אירועים
        this.bindEvents();
        
        // אתחול Infinite Scroll
        if (this.config.infiniteScroll) {
            this.initInfiniteScroll();
        }
        
        console.log('✅ TableManager initialized');
    }
    
    /**
     * וודא שהטבלה בתוך container מתאים
     */
    ensureTableContainer() {
        let container = this.elements.table.closest('.table-container');
        
        if (!container) {
            console.warn('⚠️ No .table-container found! Creating one...');
            
            // צור wrapper
            container = document.createElement('div');
            container.className = 'table-container';
            
            // עטוף את הטבלה
            this.elements.table.parentNode.insertBefore(container, this.elements.table);
            container.appendChild(this.elements.table);
            
            console.log('✅ Created .table-container wrapper');
        }
        
        // וודא שיש overflow
        const style = window.getComputedStyle(container);
        if (style.overflow !== 'auto' && style.overflowY !== 'auto') {
            console.warn('⚠️ .table-container needs overflow! Adding styles...');
            container.style.overflowX = 'auto';
            container.style.overflowY = 'auto';
            container.style.maxHeight = 'calc(100vh - 250px)';
            container.style.position = 'relative';
        }
    }
    
    /**
     * בניית מבנה הטבלה
     */
    buildTable() {
        // thead
        let thead = this.elements.table.querySelector('thead');
        if (!thead) {
            thead = document.createElement('thead');
            this.elements.table.appendChild(thead);
        }
        this.elements.thead = thead;
        
        // tbody
        let tbody = this.elements.table.querySelector('tbody');
        if (!tbody) {
            tbody = document.createElement('tbody');
            this.elements.table.appendChild(tbody);
        }
        this.elements.tbody = tbody;
        
        // רינדור כותרות
        this.renderHeaders();
        
        // טען נתונים ראשוניים
        this.loadInitialData();
    }
    
    /**
     * טעינת נתונים ראשוניים
     */
    loadInitialData() {
        // סינון
        this.state.filteredData = this.filterData(this.config.data);
        
        // מיון
        if (this.state.sortColumn !== null) {
            this.state.filteredData = this.sortData(this.state.filteredData);
        }
        
        // טען עמוד ראשון
        this.state.currentPage = 1;
        this.state.displayedData = this.state.filteredData.slice(0, this.config.itemsPerPage);
        
        // רינדור
        this.renderRows();
    }
    
    /**
     * רינדור כותרות
     */
    renderHeaders() {
        const headerRow = document.createElement('tr');
        headerRow.className = 'tm-header-row';
        
        this.state.columnOrder.forEach(colIndex => {
            const column = this.config.columns[colIndex];
            const th = document.createElement('th');
            th.className = 'tm-header-cell';
            th.dataset.columnIndex = colIndex;
            
            // קבע רוחב מינימלי אם לא הוגדר
            const width = this.state.columnWidths[colIndex];
            th.style.width = width;
            th.style.minWidth = width; // מבטיח שהעמודה לא תתכווץ
            
            // wrapper פנימי
            const wrapper = document.createElement('div');
            wrapper.className = 'tm-header-wrapper';
            
            // תווית
            const label = document.createElement('span');
            label.className = 'tm-header-label';
            label.textContent = column.label;
            wrapper.appendChild(label);
            
            // אייקון מיון
            if (this.config.sortable && column.sortable !== false) {
                const sortIcon = document.createElement('span');
                sortIcon.className = 'tm-sort-icon';
                sortIcon.innerHTML = this.getSortIcon(colIndex);
                wrapper.appendChild(sortIcon);
            }
            
            // כפתור תפריט
            if (this.config.filterable) {
                const menuBtn = document.createElement('button');
                menuBtn.className = 'tm-menu-btn';
                menuBtn.innerHTML = '⋮';
                menuBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.showColumnMenu(colIndex, menuBtn);
                };
                wrapper.appendChild(menuBtn);
            }
            
            th.appendChild(wrapper);
            
            // תפיסה לשינוי גודל (RTL FIX)
            if (this.config.resizable) {
                const resizeHandle = document.createElement('div');
                resizeHandle.className = 'tm-resize-handle';
                resizeHandle.dataset.columnIndex = colIndex;
                th.appendChild(resizeHandle);
            }
            
            headerRow.appendChild(th);
        });
        
        this.elements.thead.innerHTML = '';
        this.elements.thead.appendChild(headerRow);
        
        // הדפס את רוחבי העמודות לקונסול
        console.log('📏 Column Widths:', this.getColumnWidths());
    }
    
    /**
     * קבל רוחבי עמודות נוכחיים
     */
    getColumnWidths() {
        const widths = {};
        this.config.columns.forEach((col, index) => {
            widths[col.field || col.label] = this.state.columnWidths[index];
        });
        return widths;
    }
    
    /**
     * רינדור שורות
     */
    renderRows(append = false) {
        if (this.state.displayedData.length === 0 && !append) {
            this.elements.tbody.innerHTML = '<tr><td colspan="100" style="text-align: center; padding: 40px; color: #999;">אין נתונים להצגה</td></tr>';
            return;
        }
        
        const dataToRender = append 
            ? this.state.displayedData.slice((this.state.currentPage - 1) * this.config.itemsPerPage)
            : this.state.displayedData;
        
        // בניית שורות
        const rows = dataToRender.map(rowData => {
            const tr = document.createElement('tr');
            tr.className = 'tm-row';
            
            this.state.columnOrder.forEach(colIndex => {
                const column = this.config.columns[colIndex];
                const td = document.createElement('td');
                td.className = 'tm-cell';
                
                // החל את הרוחב מהכותרת
                const width = this.state.columnWidths[colIndex];
                td.style.width = width;
                td.style.minWidth = width;
                
                // רינדור התא
                if (this.config.renderCell) {
                    td.innerHTML = this.config.renderCell(rowData, column, colIndex);
                } else if (column.render) {
                    td.innerHTML = column.render(rowData);
                } else {
                    td.textContent = rowData[column.field] || '-';
                }
                
                tr.appendChild(td);
            });
            
            return tr;
        });
        
        if (append) {
            rows.forEach(row => this.elements.tbody.appendChild(row));
        } else {
            this.elements.tbody.innerHTML = '';
            rows.forEach(row => this.elements.tbody.appendChild(row));
        }
    }
    
    /**
     * מיון נתונים - FIX: עכשיו עובד נכון
     */
    sortData(data) {
        const column = this.config.columns[this.state.sortColumn];
        const field = column.field;
        
        return [...data].sort((a, b) => {
            let valA = a[field];
            let valB = b[field];
            
            // טיפול בערכים ריקים
            if (valA == null) valA = '';
            if (valB == null) valB = '';
            
            // המרה למספרים אם צריך
            if (column.type === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            }
            
            // המרה לתאריכים אם צריך
            if (column.type === 'date') {
                valA = new Date(valA).getTime() || 0;
                valB = new Date(valB).getTime() || 0;
            }
            
            // השוואה
            let comparison = 0;
            if (typeof valA === 'string' && typeof valB === 'string') {
                comparison = valA.localeCompare(valB, 'he');
            } else {
                if (valA > valB) comparison = 1;
                if (valA < valB) comparison = -1;
            }
            
            return this.state.sortOrder === 'asc' ? comparison : -comparison;
        });
    }
    
    /**
     * סינון נתונים - על כל הדאטה, לא רק מה שמוצג
     */
    filterData(data) {
        if (this.state.filters.size === 0) {
            return data;
        }
        
        return data.filter(row => {
            let matches = true;
            
            this.state.filters.forEach((filterValue, colIndex) => {
                const column = this.config.columns[colIndex];
                const cellValue = String(row[column.field] || '').toLowerCase();
                const filterLower = String(filterValue).toLowerCase();
                
                if (!cellValue.includes(filterLower)) {
                    matches = false;
                }
            });
            
            return matches;
        });
    }
    
    /**
     * אייקון מיון
     */
    getSortIcon(colIndex) {
        if (this.state.sortColumn === colIndex) {
            return this.state.sortOrder === 'asc' ? '▲' : '▼';
        }
        return '⇅';
    }
    
    /**
     * קישור אירועים
     */
    bindEvents() {
        // מיון בלחיצה על כותרת
        if (this.config.sortable) {
            this.elements.thead.addEventListener('click', (e) => {
                const th = e.target.closest('.tm-header-cell');
                if (th && !e.target.closest('.tm-menu-btn')) {
                    const colIndex = parseInt(th.dataset.columnIndex);
                    this.handleSort(colIndex);
                }
            });
        }
        
        // שינוי גודל עמודות
        if (this.config.resizable) {
            this.bindResizeEvents();
        }
    }
    
    /**
     * טיפול במיון
     */
    handleSort(colIndex) {
        if (this.state.sortColumn === colIndex) {
            // שינוי כיוון המיון
            this.state.sortOrder = this.state.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            // עמודה חדשה
            this.state.sortColumn = colIndex;
            this.state.sortOrder = 'asc';
        }
        
        // callback
        if (this.config.onSort) {
            const column = this.config.columns[colIndex];
            this.config.onSort(column.field, this.state.sortOrder);
        }
        
        // טען מחדש עם המיון החדש
        this.loadInitialData();
        
        // עדכן כותרות
        this.renderHeaders();
    }
    
    /**
     * הצגת תפריט עמודה
     */
    showColumnMenu(colIndex, button) {
        // הסר תפריטים קיימים
        document.querySelectorAll('.tm-column-menu').forEach(m => m.remove());
        
        const column = this.config.columns[colIndex];
        const menu = document.createElement('div');
        menu.className = 'tm-column-menu';
        
        menu.innerHTML = `
            <div class="tm-menu-item" data-action="sort-asc">
                <span>▲</span> מיין עולה
            </div>
            <div class="tm-menu-item" data-action="sort-desc">
                <span>▼</span> מיין יורד
            </div>
            <div class="tm-menu-divider"></div>
            <div class="tm-menu-item" data-action="filter">
                <span>🔍</span> סינון...
            </div>
            <div class="tm-menu-item" data-action="clear-filter">
                <span>✕</span> נקה סינון
            </div>
        `;
        
        // מיקום התפריט
        const rect = button.getBoundingClientRect();
        menu.style.top = `${rect.bottom + 5}px`;
        menu.style.right = `${window.innerWidth - rect.right}px`;
        
        // אירועים
        menu.addEventListener('click', (e) => {
            const item = e.target.closest('.tm-menu-item');
            if (!item) return;
            
            const action = item.dataset.action;
            
            switch (action) {
                case 'sort-asc':
                    this.state.sortColumn = colIndex;
                    this.state.sortOrder = 'asc';
                    this.loadInitialData();
                    this.renderHeaders();
                    break;
                    
                case 'sort-desc':
                    this.state.sortColumn = colIndex;
                    this.state.sortOrder = 'desc';
                    this.loadInitialData();
                    this.renderHeaders();
                    break;
                    
                case 'filter':
                    this.showFilterDialog(colIndex);
                    break;
                    
                case 'clear-filter':
                    this.state.filters.delete(colIndex);
                    this.loadInitialData();
                    break;
            }
            
            menu.remove();
        });
        
        document.body.appendChild(menu);
        
        // סגירה בלחיצה מחוץ
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
    
    /**
     * דיאלוג סינון
     */
    showFilterDialog(colIndex) {
        const column = this.config.columns[colIndex];
        const currentFilter = this.state.filters.get(colIndex) || '';
        
        const value = prompt(`סינון "${column.label}":`, currentFilter);
        
        if (value !== null) {
            if (value.trim() === '') {
                this.state.filters.delete(colIndex);
            } else {
                this.state.filters.set(colIndex, value);
            }
            
            if (this.config.onFilter) {
                this.config.onFilter(Array.from(this.state.filters.entries()));
            }
            
            // טען מחדש עם הסינון
            this.loadInitialData();
        }
    }
    
    /**
     * קישור אירועי שינוי גודל - RTL FIX
     */
    bindResizeEvents() {
        let startX, startWidth, colIndex;
        
        const onMouseDown = (e) => {
            const handle = e.target.closest('.tm-resize-handle');
            if (!handle) return;
            
            e.preventDefault();
            colIndex = parseInt(handle.dataset.columnIndex);
            const th = handle.closest('th');
            startX = e.pageX;
            startWidth = th.offsetWidth;
            this.state.isResizing = true;
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        };
        
        const onMouseMove = (e) => {
            if (!this.state.isResizing) return;
            
            // RTL FIX: ב-RTL, הזזה שמאלה = הגדלה, הזזה ימינה = הקטנה
            // אבל ה-handle נמצא בצד שמאל של העמודה (שזה הצד הימני ויזואלית)
            // לכן: e.pageX - startX (הפוך מהניסיון הקודם!)
            const diff = e.pageX - startX;
            const newWidth = Math.max(50, startWidth - diff); // שים לב ל-MINUS
            this.state.columnWidths[colIndex] = `${newWidth}px`;
            
            const th = this.elements.thead.querySelector(`th[data-column-index="${colIndex}"]`);
            if (th) {
                th.style.width = `${newWidth}px`;
                th.style.minWidth = `${newWidth}px`;
            }
            
            // עדכן גם את התאים בגוף הטבלה
            const cells = this.elements.tbody.querySelectorAll(`tr td:nth-child(${colIndex + 1})`);
            cells.forEach(cell => {
                cell.style.width = `${newWidth}px`;
                cell.style.minWidth = `${newWidth}px`;
            });
        };
        
        const onMouseUp = () => {
            this.state.isResizing = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            
            // הדפס את המידות החדשות
            console.log('📏 Updated Column Widths:', this.getColumnWidths());
        };
        
        this.elements.thead.addEventListener('mousedown', onMouseDown);
    }
    
    /**
     * אתחול Infinite Scroll
     */
    initInfiniteScroll() {
        // מצא את הקונטיינר הגלילה
        this.elements.scrollContainer = this.elements.table.closest('.table-container');
        
        if (!this.elements.scrollContainer) {
            console.warn('⚠️ No .table-container found, using window as scroll container');
            this.elements.scrollContainer = window;
        }
        
        const scrollElement = this.elements.scrollContainer === window 
            ? window 
            : this.elements.scrollContainer;
        
        scrollElement.addEventListener('scroll', () => {
            if (this.state.isLoading) return;
            
            // הוסף/הסר class לצל
            if (this.elements.scrollContainer !== window) {
                const scrollTop = this.elements.scrollContainer.scrollTop;
                if (scrollTop > 0) {
                    this.elements.scrollContainer.classList.add('scrolled');
                } else {
                    this.elements.scrollContainer.classList.remove('scrolled');
                }
            }
            
            const { scrollTop, scrollHeight, clientHeight } = 
                this.elements.scrollContainer === window 
                    ? { 
                        scrollTop: window.pageYOffset,
                        scrollHeight: document.documentElement.scrollHeight,
                        clientHeight: window.innerHeight
                    }
                    : this.elements.scrollContainer;
            
            const distanceFromBottom = scrollHeight - (scrollTop + clientHeight);
            
            if (distanceFromBottom < this.config.scrollThreshold) {
                this.loadMoreData();
            }
        });
        
        console.log('📜 Infinite scroll initialized on:', 
            this.elements.scrollContainer === window ? 'window' : '.table-container');
    }
    
    /**
     * טעינת עוד נתונים
     */
    async loadMoreData() {
        const totalItems = this.state.filteredData.length;
        const loadedItems = this.state.displayedData.length;
        
        if (loadedItems >= totalItems) {
            console.log('📭 All items loaded');
            return;
        }
        
        this.state.isLoading = true;
        console.log('📥 Loading more data...');
        
        // הוסף אינדיקטור טעינה
        this.showLoadingIndicator();
        
        // סימולציה של טעינה
        await new Promise(resolve => setTimeout(resolve, 300));
        
        const nextBatch = this.state.filteredData.slice(
            loadedItems,
            loadedItems + this.config.itemsPerPage
        );
        
        this.state.displayedData = [...this.state.displayedData, ...nextBatch];
        this.state.currentPage++;
        
        this.renderRows(true); // append mode
        
        // הסר אינדיקטור טעינה
        this.hideLoadingIndicator();
        
        this.state.isLoading = false;
        console.log(`✅ Loaded ${nextBatch.length} more items (${this.state.displayedData.length}/${totalItems})`);
    }
    
    /**
     * הצגת אינדיקטור טעינה
     */
    showLoadingIndicator() {
        const existing = this.elements.tbody.querySelector('.tm-loading-indicator');
        if (existing) return;
        
        const row = document.createElement('tr');
        row.className = 'tm-loading-indicator';
        row.innerHTML = `
            <td colspan="100" style="text-align: center; padding: 20px;">
                <div class="tm-loading-spinner"></div>
                <div style="margin-top: 10px; color: #6b7280;">טוען עוד נתונים...</div>
            </td>
        `;
        
        this.elements.tbody.appendChild(row);
    }
    
    /**
     * הסרת אינדיקטור טעינה
     */
    hideLoadingIndicator() {
        const indicator = this.elements.tbody.querySelector('.tm-loading-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    /**
     * API ציבורי
     */
    
    setData(data) {
        this.config.data = data;
        this.loadInitialData();
    }
    
    refresh() {
        this.loadInitialData();
    }
    
    clearFilters() {
        this.state.filters.clear();
        this.loadInitialData();
    }
    
    clearSort() {
        this.state.sortColumn = null;
        this.loadInitialData();
        this.renderHeaders();
    }
    
    getFilteredData() {
        return this.state.filteredData;
    }
    
    getDisplayedData() {
        return this.state.displayedData;
    }
    
    setColumnWidths(widths) {
        // widths הוא אובייקט עם field: width
        Object.keys(widths).forEach(field => {
            const colIndex = this.config.columns.findIndex(col => 
                (col.field || col.label) === field
            );
            if (colIndex !== -1) {
                this.state.columnWidths[colIndex] = widths[field];
            }
        });
        this.renderHeaders();
        this.renderRows();
    }
    
    resetColumnWidths() {
        this.config.columns.forEach((col, index) => {
            this.state.columnWidths[index] = col.width || 'auto';
        });
        this.renderHeaders();
        this.renderRows();
    }
}

// הפוך לגלובלי
window.TableManager = TableManager;