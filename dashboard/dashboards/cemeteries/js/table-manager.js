/**
 * TableManager - מערכת טבלאות מתקדמת
 * תכונות: מיון, שינוי גודל, שינוי סדר, תפריט עמודה, סינון, Infinite Scroll
 * תמיכה מלאה ב-RTL + תמיכה ברוחב דינמי
 * 
 * Version: 1.1.0
 * Updated: 2025-10-26
 * Change Summary:
 * - v1.1.0: הוספת תמיכה ב-onRowDoubleClick callback
 */

class TableManager {
    constructor(config) {
        this.config = {
            tableSelector: null,
            columns: [],
            data: [],
            
            // ⭐ הוספת totalItems - הסכום האמיתי מה-API
            totalItems: null,  // אם null, נשתמש ב-data.length
            
            // הגדרות רוחב
            containerWidth: '100%',
            containerPadding: '16px',
            
            sortable: true,
            resizable: true,
            reorderable: true,
            filterable: true,
            renderCell: null,
            onSort: null,
            onFilter: null,
            onColumnReorder: null,
            
            // ⭐ חדש! callback לdouble-click על שורה
            onRowDoubleClick: null,
            
            infiniteScroll: true,
            itemsPerPage: 100,
            scrollThreshold: 301,
            ...config
        };
        
        // ⭐ אם לא קיבלנו totalItems, השתמש ב-data.length
        if (this.config.totalItems === null) {
            this.config.totalItems = this.config.data.length;
        }
        
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
        
        console.log('✅ TableManager initialized with fixed header');
        console.log('📐 Container width:', this.config.containerWidth);
        console.log('📦 Container padding:', this.config.containerPadding);
    }
    
    /**
     * בניית מבנה הטבלה
     */
    buildTable() {
        // נקה את הטבלה
        this.elements.table.innerHTML = '';
        
        // צור thead
        this.elements.thead = document.createElement('thead');
        this.elements.table.appendChild(this.elements.thead);
        
        // צור tbody
        this.elements.tbody = document.createElement('tbody');
        this.elements.table.appendChild(this.elements.tbody);
        
        // בנה כותרות
        this.renderHeaders();
        
        // הצג נתונים
        this.setData(this.config.data);
    }
    
    /**
     * רינדור כותרות
     */
    renderHeaders() {
        const headerRow = document.createElement('tr');
        
        this.state.columnOrder.forEach(colIndex => {
            const column = this.config.columns[colIndex];
            const th = document.createElement('th');
            th.className = 'tm-header';
            th.dataset.columnIndex = colIndex;
            
            // תוכן הכותרת
            const headerContent = document.createElement('div');
            headerContent.className = 'tm-header-content';
            
            // טקסט הכותרת
            const label = document.createElement('span');
            label.className = 'tm-header-label';
            label.textContent = column.label;
            headerContent.appendChild(label);
            
            // אייקון מיון
            if (this.config.sortable && column.sortable !== false) {
                const sortIcon = document.createElement('span');
                sortIcon.className = 'tm-sort-icon';
                sortIcon.innerHTML = '⇅';
                headerContent.appendChild(sortIcon);
            }
            
            th.appendChild(headerContent);
            
            // תפריט עמודה
            if (this.config.filterable && column.sortable !== false) {
                const menuBtn = document.createElement('button');
                menuBtn.className = 'tm-column-menu-btn';
                menuBtn.innerHTML = '⋮';
                menuBtn.onclick = (e) => this.showColumnMenu(e, colIndex);
                th.appendChild(menuBtn);
            }
            
            // resizer
            if (this.config.resizable) {
                const resizer = document.createElement('div');
                resizer.className = 'tm-resizer';
                resizer.dataset.columnIndex = colIndex;
                th.appendChild(resizer);
            }
            
            headerRow.appendChild(th);
        });
        
        this.elements.thead.innerHTML = '';
        this.elements.thead.appendChild(headerRow);
        
        // עדכן רוחב עמודות
        this.applyColumnWidths();
    }
    
    /**
     * הגדרת נתונים
     */
    setData(data) {
        this.config.data = data;
        this.state.filteredData = this.filterData(data);
        this.state.displayedData = this.sortData(this.state.filteredData);
        this.state.currentPage = 1;
        this.renderRows();
        this.updateSortIcons();
    }
    
    /**
     * סינון נתונים
     */
    filterData(data) {
        if (this.state.filters.size === 0) {
            return data;
        }
        
        return data.filter(row => {
            for (let [colIndex, filterValue] of this.state.filters) {
                const column = this.config.columns[colIndex];
                const cellValue = String(row[column.field] || '').toLowerCase();
                const filter = String(filterValue).toLowerCase();
                
                if (!cellValue.includes(filter)) {
                    return false;
                }
            }
            return true;
        });
    }
    
    /**
     * עדכון אייקוני מיון
     */
    updateSortIcons() {
        const headers = this.elements.thead.querySelectorAll('.tm-header');
        headers.forEach(th => {
            const colIndex = parseInt(th.dataset.columnIndex);
            const sortIcon = th.querySelector('.tm-sort-icon');
            
            if (sortIcon) {
                if (colIndex === this.state.sortColumn) {
                    sortIcon.innerHTML = this.state.sortOrder === 'asc' ? '↑' : '↓';
                    sortIcon.style.opacity = '1';
                } else {
                    sortIcon.innerHTML = '⇅';
                    sortIcon.style.opacity = '0.3';
                }
            }
        });
    }
    
    /**
     * קישור אירועים
     */
    bindEvents() {
        // מיון
        if (this.config.sortable) {
            this.elements.thead.addEventListener('click', (e) => {
                const th = e.target.closest('.tm-header');
                if (th && !e.target.closest('.tm-column-menu-btn')) {
                    const colIndex = parseInt(th.dataset.columnIndex);
                    const column = this.config.columns[colIndex];
                    
                    if (column.sortable !== false) {
                        this.toggleSort(colIndex);
                    }
                }
            });
        }
        
        // שינוי גודל
        if (this.config.resizable) {
            this.bindResizeEvents();
        }
        
        // שינוי סדר
        if (this.config.reorderable) {
            this.bindReorderEvents();
        }
    }
    
    /**
     * מיון
     */
    toggleSort(colIndex) {
        if (this.state.sortColumn === colIndex) {
            this.state.sortOrder = this.state.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.state.sortColumn = colIndex;
            this.state.sortOrder = 'asc';
        }
        
        this.state.displayedData = this.sortData(this.state.filteredData);
        this.renderRows();
        this.updateSortIcons();
        
        if (this.config.onSort) {
            const column = this.config.columns[colIndex];
            this.config.onSort(column.field, this.state.sortOrder);
        }
    }
    
    /**
     * הצגת תפריט עמודה
     */
    showColumnMenu(e, colIndex) {
        e.stopPropagation();
        
        // הסר תפריטים קיימים
        document.querySelectorAll('.tm-column-menu').forEach(menu => menu.remove());
        
        const column = this.config.columns[colIndex];
        const menu = document.createElement('div');
        menu.className = 'tm-column-menu';
        
        // סינון
        const filterInput = document.createElement('input');
        filterInput.type = 'text';
        filterInput.placeholder = `סנן ${column.label}...`;
        filterInput.className = 'tm-filter-input';
        filterInput.value = this.state.filters.get(colIndex) || '';
        
        filterInput.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (value) {
                this.state.filters.set(colIndex, value);
            } else {
                this.state.filters.delete(colIndex);
            }
            
            this.state.filteredData = this.filterData(this.config.data);
            this.state.displayedData = this.sortData(this.state.filteredData);
            this.renderRows();
            
            if (this.config.onFilter) {
                this.config.onFilter(this.state.filters);
            }
        });
        
        menu.appendChild(filterInput);
        
        // מיקום
        const rect = e.target.getBoundingClientRect();
        menu.style.top = rect.bottom + 'px';
        menu.style.left = rect.left + 'px';
        
        document.body.appendChild(menu);
        filterInput.focus();
        
        // סגירה בלחיצה מחוץ לתפריט
        setTimeout(() => {
            const closeMenu = (event) => {
                if (!menu.contains(event.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            };
            document.addEventListener('click', closeMenu);
        }, 100);
    }
    
    /**
     * קישור אירועי שינוי גודל
     */
    bindResizeEvents() {
        const resizers = this.elements.thead.querySelectorAll('.tm-resizer');
        
        resizers.forEach(resizer => {
            resizer.addEventListener('mousedown', (e) => {
                e.preventDefault();
                this.state.isResizing = true;
                
                const colIndex = parseInt(resizer.dataset.columnIndex);
                const startX = e.pageX;
                const th = resizer.parentElement;
                const startWidth = th.offsetWidth;
                
                const onMouseMove = (e) => {
                    if (!this.state.isResizing) return;
                    
                    const diff = e.pageX - startX;
                    const newWidth = Math.max(50, startWidth + diff);
                    
                    this.state.columnWidths[colIndex] = newWidth + 'px';
                    this.applyColumnWidths();
                };
                
                const onMouseUp = () => {
                    this.state.isResizing = false;
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                };
                
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });
    }
    
    /**
     * קישור אירועי שינוי סדר
     */
    bindReorderEvents() {
        const headers = this.elements.thead.querySelectorAll('.tm-header');
        
        headers.forEach(th => {
            th.draggable = true;
            
            th.addEventListener('dragstart', (e) => {
                this.state.isDragging = true;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', th.dataset.columnIndex);
                th.classList.add('tm-dragging');
            });
            
            th.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            th.addEventListener('drop', (e) => {
                e.preventDefault();
                const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
                const toIndex = parseInt(th.dataset.columnIndex);
                
                if (fromIndex !== toIndex) {
                    this.reorderColumns(fromIndex, toIndex);
                }
            });
            
            th.addEventListener('dragend', (e) => {
                this.state.isDragging = false;
                th.classList.remove('tm-dragging');
            });
        });
    }
    
    /**
     * שינוי סדר עמודות
     */
    reorderColumns(fromIndex, toIndex) {
        const newOrder = [...this.state.columnOrder];
        const [moved] = newOrder.splice(fromIndex, 1);
        newOrder.splice(toIndex, 0, moved);
        
        this.state.columnOrder = newOrder;
        this.renderHeaders();
        this.renderRows();
        
        if (this.config.onColumnReorder) {
            this.config.onColumnReorder(this.state.columnOrder);
        }
    }
    
    /**
     * החלת רוחב עמודות
     */
    applyColumnWidths() {
        const headers = this.elements.thead.querySelectorAll('.tm-header');
        
        headers.forEach((th, displayIndex) => {
            const colIndex = this.state.columnOrder[displayIndex];
            const width = this.state.columnWidths[colIndex];
            th.style.width = width;
            th.style.minWidth = width;
        });
        
        // עדכן גם תאי הגוף
        const bodyContainer = this.elements.table.closest('.table-body-container');
        if (bodyContainer) {
            const bodyCols = bodyContainer.querySelectorAll('.tm-cell');
            
            // עדכן קיימים
            bodyCols.forEach((col, index) => {
                const width = this.state.columnWidths[index];
                col.style.width = width;
                col.style.minWidth = width;
            });
        }
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
            
            // ⭐ חדש! הוספת double-click handler
            if (this.config.onRowDoubleClick) {
                tr.style.cursor = 'pointer';
                tr.ondblclick = () => {
                    console.log('🖱️ Double-click detected on row:', rowData);
                    this.config.onRowDoubleClick(rowData);
                };
            }
            
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
            if (valA > valB) comparison = 1;
            if (valA < valB) comparison = -1;
            
            return this.state.sortOrder === 'asc' ? comparison : -comparison;
        });
    }
    
    /**
     * אתחול Infinite Scroll
     */
    initInfiniteScroll() {
        const bodyContainer = this.elements.table.closest('.table-body-container');
        if (!bodyContainer) return;
        
        bodyContainer.addEventListener('scroll', () => {
            if (this.state.isLoading) return;
            
            const scrollTop = bodyContainer.scrollTop;
            const scrollHeight = bodyContainer.scrollHeight;
            const clientHeight = bodyContainer.clientHeight;
            
            const scrollBottom = scrollHeight - scrollTop - clientHeight;
            
            if (scrollBottom < this.config.scrollThreshold) {
                this.loadMoreData();
            }
        });
    }
    
    /**
     * טעינת נתונים נוספים
     */
    loadMoreData() {
        if (this.state.isLoading) return;
        
        const maxPages = Math.ceil(this.state.displayedData.length / this.config.itemsPerPage);
        if (this.state.currentPage >= maxPages) return;
        
        this.state.isLoading = true;
        this.state.currentPage++;
        
        setTimeout(() => {
            this.renderRows(true);
            this.state.isLoading = false;
        }, 100);
    }
    
    /**
     * קבלת נתונים מסוננים
     */
    getFilteredData() {
        return this.state.filteredData;
    }
    
    /**
     * ריענון הטבלה
     */
    refresh() {
        this.renderRows();
    }
}

// ייצוא גלובלי
window.TableManager = TableManager;