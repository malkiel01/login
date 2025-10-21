/**
 * TableManager - מערכת טבלאות מתקדמת
 * תכונות: מיון, שינוי גודל, שינוי סדר, תפריט עמודה, סינון
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
            ...config
        };
        
        this.state = {
            sortColumn: null,
            sortOrder: 'asc', // 'asc' or 'desc'
            columnWidths: {},
            columnOrder: [],
            filters: new Map(),
            isResizing: false,
            isDragging: false
        };
        
        this.elements = {
            table: null,
            thead: null,
            tbody: null
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
        
        console.log('✅ TableManager initialized');
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
        
        // רינדור נתונים
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
            th.style.width = this.state.columnWidths[colIndex];
            
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
            
            // תפיסה לשינוי גודל
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
    }
    
    /**
     * רינדור שורות
     */
    renderRows() {
        if (!this.config.data || this.config.data.length === 0) {
            this.elements.tbody.innerHTML = '<tr><td colspan="100" style="text-align: center; padding: 40px; color: #999;">אין נתונים להצגה</td></tr>';
            return;
        }
        
        // מיון הנתונים
        let sortedData = [...this.config.data];
        if (this.state.sortColumn !== null) {
            sortedData = this.sortData(sortedData);
        }
        
        // סינון
        if (this.state.filters.size > 0) {
            sortedData = this.filterData(sortedData);
        }
        
        // בניית שורות
        const rows = sortedData.map(rowData => {
            const tr = document.createElement('tr');
            tr.className = 'tm-row';
            
            this.state.columnOrder.forEach(colIndex => {
                const column = this.config.columns[colIndex];
                const td = document.createElement('td');
                td.className = 'tm-cell';
                
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
        
        this.elements.tbody.innerHTML = '';
        rows.forEach(row => this.elements.tbody.appendChild(row));
    }
    
    /**
     * מיון נתונים
     */
    sortData(data) {
        const column = this.config.columns[this.state.sortColumn];
        const field = column.field;
        
        return data.sort((a, b) => {
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
            if (valA > valB) comparison = 1;
            if (valA < valB) comparison = -1;
            
            return this.state.sortOrder === 'asc' ? comparison : -comparison;
        });
    }
    
    /**
     * סינון נתונים
     */
    filterData(data) {
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
        
        // שינוי סדר עמודות
        if (this.config.reorderable) {
            this.bindReorderEvents();
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
        
        // עדכון תצוגה
        this.renderHeaders();
        this.renderRows();
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
        menu.style.left = `${rect.left - 150}px`;
        
        // אירועים
        menu.addEventListener('click', (e) => {
            const item = e.target.closest('.tm-menu-item');
            if (!item) return;
            
            const action = item.dataset.action;
            
            switch (action) {
                case 'sort-asc':
                    this.state.sortColumn = colIndex;
                    this.state.sortOrder = 'asc';
                    this.renderHeaders();
                    this.renderRows();
                    break;
                    
                case 'sort-desc':
                    this.state.sortColumn = colIndex;
                    this.state.sortOrder = 'desc';
                    this.renderHeaders();
                    this.renderRows();
                    break;
                    
                case 'filter':
                    this.showFilterDialog(colIndex);
                    break;
                    
                case 'clear-filter':
                    this.state.filters.delete(colIndex);
                    this.renderRows();
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
            
            this.renderRows();
        }
    }
    
    /**
     * קישור אירועי שינוי גודל
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
            
            const diff = e.pageX - startX;
            const newWidth = Math.max(50, startWidth + diff);
            this.state.columnWidths[colIndex] = `${newWidth}px`;
            
            const th = this.elements.thead.querySelector(`th[data-column-index="${colIndex}"]`);
            if (th) th.style.width = `${newWidth}px`;
        };
        
        const onMouseUp = () => {
            this.state.isResizing = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
        
        this.elements.thead.addEventListener('mousedown', onMouseDown);
    }
    
    /**
     * קישור אירועי שינוי סדר
     */
    bindReorderEvents() {
        // TODO: drag & drop לשינוי סדר עמודות
        // נוסיף בשלב הבא אם צריך
    }
    
    /**
     * API ציבורי
     */
    
    setData(data) {
        this.config.data = data;
        this.renderRows();
    }
    
    refresh() {
        this.renderRows();
    }
    
    clearFilters() {
        this.state.filters.clear();
        this.renderRows();
    }
    
    clearSort() {
        this.state.sortColumn = null;
        this.renderHeaders();
        this.renderRows();
    }
}

// הפוך לגלובלי
window.TableManager = TableManager;