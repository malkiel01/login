/**
 * TableManager - מערכת טבלאות מתקדמת
 * תכונות: מיון, שינוי גודל, שינוי סדר, תפריט עמודה, סינון, Infinite Scroll
 * תמיכה מלאה ב-RTL + תמיכה ברוחב דינמי
 */

class TableManager {
    // constructor(config) {
    //     this.config = {
    //         tableSelector: null,
    //         columns: [],
    //         data: [],
            
    //         // ⭐ הגדרות רוחב חדשות
    //         containerWidth: '100%',      // ברירת מחדל: תופס את כל הרוחב
    //         containerPadding: '16px',    // ברירת מחדל: padding סביב
            
    //         sortable: true,
    //         resizable: true,
    //         reorderable: true,
    //         filterable: true,
    //         renderCell: null,
    //         onSort: null,
    //         onFilter: null,
    //         onColumnReorder: null,
    //         infiniteScroll: true,
    //         itemsPerPage: 100,
    //         scrollThreshold: 200, // פיקסלים מהתחתית לטעינה
    //         ...config
    //     };
        
    //     this.state = {
    //         sortColumn: null,
    //         sortOrder: 'asc',
    //         columnWidths: {},
    //         columnOrder: [],
    //         filters: new Map(),
    //         isResizing: false,
    //         isDragging: false,
    //         currentPage: 1,
    //         isLoading: false,
    //         filteredData: [],
    //         displayedData: []
    //     };
        
    //     this.elements = {
    //         table: null,
    //         thead: null,
    //         tbody: null,
    //         scrollContainer: null
    //     };
        
    //     this.init();
    // }
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
            infiniteScroll: true,
            itemsPerPage: 100,
            scrollThreshold: 200,
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
        console.log('🏗️ Building new table structure...');
        
        // מצא את ההורה של הטבלה המקורית
        let parent = this.elements.table.parentNode;
        
        // תקן את כל ההורים עד שמוצאים אחד ללא overflow
        let currentParent = parent;
        let fixed = [];
        
        while (currentParent && currentParent !== document.body) {
            const styles = window.getComputedStyle(currentParent);
            
            // ⭐ אם זה table-container, תן לו את הרוחב והפדינג מה-config
            if (currentParent.classList.contains('table-container')) {
                console.log('🎯 Setting .table-container with custom dimensions');
                currentParent.setAttribute('style', `
                    width: ${this.config.containerWidth} !important; 
                    padding: ${this.config.containerPadding} !important; 
                    margin: 0 !important; 
                    overflow: visible !important; 
                    max-height: none !important; 
                    height: auto !important; 
                    box-sizing: border-box !important; 
                    border: 1px solid #ddd !important; 
                    background: #f5f5f5 !important;
                `.replace(/\s+/g, ' ').trim());
                fixed.push('table-container');
            }
            // אם יש overflow אחר, תקן אותו
            else if (styles.overflow !== 'visible' || styles.overflowY !== 'visible' || styles.maxHeight !== 'none') {
                console.log(`🔧 Fixing parent: ${currentParent.className || currentParent.tagName}`);
                currentParent.style.cssText += `
                    overflow: visible !important;
                    max-height: none !important;
                    height: auto !important;
                `;
                fixed.push(currentParent.className || currentParent.tagName);
            }
            
            currentParent = currentParent.parentElement;
        }
        
        if (fixed.length > 0) {
            console.log('✅ Fixed overflow on:', fixed.join(', '));
        }
        
        // צור את המבנה החדש: wrapper > header-container + body-container
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        wrapper.setAttribute('data-fixed-width', 'true');
        
        // הוסף CSS inline - wrapper יהיה 100% כדי להתאים לפנים ה-container
        wrapper.setAttribute('style', 'display: flex !important; flex-direction: column !important; width: 100% !important; height: calc(100vh - 250px) !important; min-height: 500px !important; border: 1px solid #e5e7eb !important; border-radius: 8px !important; overflow: hidden !important; background: white !important; position: relative !important; box-sizing: border-box !important;');
        
        console.log('📦 Created wrapper');
        
        // קונטיינר כותרת
        const headerContainer = document.createElement('div');
        headerContainer.className = 'table-header-container';
        headerContainer.style.cssText = `
            flex-shrink: 0 !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            background: white !important;
            border-bottom: 2px solid #e5e7eb !important;
            position: relative !important;
            z-index: 100 !important;
        `;
        
        // קונטיינר תוכן
        const bodyContainer = document.createElement('div');
        bodyContainer.className = 'table-body-container';
        bodyContainer.style.cssText = `
            flex: 1 !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
            position: relative !important;
            height: 100% !important;
        `;
        
        console.log('📦 Created header and body containers');
        
        // טבלת כותרת
        const headerTable = document.createElement('table');
        headerTable.className = 'tm-table tm-header-table';
        headerTable.id = 'headerTable';
        headerTable.style.cssText = `
            width: max-content !important;
            min-width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: white !important;
            table-layout: fixed !important;
        `;
        const thead = document.createElement('thead');
        headerTable.appendChild(thead);
        headerContainer.appendChild(headerTable);
        
        console.log('📋 Created header table');
        
        // טבלת תוכן
        const bodyTable = document.createElement('table');
        bodyTable.className = 'tm-table tm-body-table';
        bodyTable.id = 'bodyTable';
        bodyTable.style.cssText = `
            width: max-content !important;
            min-width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: white !important;
            table-layout: fixed !important;
        `;
        const tbody = document.createElement('tbody');
        bodyTable.appendChild(tbody);
        bodyContainer.appendChild(bodyTable);
        
        console.log('📋 Created body table');
        
        // הרכבה
        wrapper.appendChild(headerContainer);
        wrapper.appendChild(bodyContainer);
        
        // החלף את הטבלה המקורית
        parent.insertBefore(wrapper, this.elements.table);
        this.elements.table.style.display = 'none';
        
        console.log('✅ New structure inserted, original table hidden');
        
        // שמור references
        this.elements.wrapper = wrapper;
        this.elements.headerContainer = headerContainer;
        this.elements.bodyContainer = bodyContainer;
        this.elements.headerTable = headerTable;
        this.elements.bodyTable = bodyTable;
        this.elements.thead = thead;
        this.elements.tbody = tbody;
        
        console.log('📌 References saved');
        console.log('📊 Checking computed styles...');
        
        // בדוק שה-CSS אכן הוחל
        setTimeout(() => {
            const wrapperStyles = window.getComputedStyle(wrapper);
            const headerStyles = window.getComputedStyle(headerContainer);
            const bodyStyles = window.getComputedStyle(bodyContainer);
            const parentStyles = window.getComputedStyle(parent);
            
            console.log('Parent overflow:', parentStyles.overflow, parentStyles.overflowY);
            console.log('Wrapper display:', wrapperStyles.display);
            console.log('Wrapper height:', wrapperStyles.height);
            console.log('Wrapper overflow:', wrapperStyles.overflow);
            console.log('Header overflow:', headerStyles.overflow, 'Y:', headerStyles.overflowY);
            console.log('Body overflow:', bodyStyles.overflow, 'Y:', bodyStyles.overflowY);
            console.log('Body flex:', bodyStyles.flex);
            
            if (parentStyles.overflow !== 'visible') {
                console.warn('⚠️ Parent still has overflow! Trying to fix again...');
                parent.style.overflow = 'visible';
                parent.style.maxHeight = 'none';
            }
            
            if (wrapperStyles.display !== 'flex') {
                console.warn('⚠️ Wrapper is not flex! CSS might not be loaded.');
            } else {
                console.log('✅ CSS applied correctly!');
            }
        }, 100);
        
        // סנכרן גלילה אופקית
        this.syncHorizontalScroll();
        
        console.log('🔄 Horizontal scroll synced');
        
        // רינדור כותרות
        this.renderHeaders();
        
        // טען נתונים ראשוניים
        this.loadInitialData();
        
        console.log('🎉 Table structure complete!');
    }
    
    /**
     * סנכרון גלילה אופקית בין כותרת לתוכן
     */
    syncHorizontalScroll() {
        this.elements.headerContainer.addEventListener('scroll', () => {
            this.elements.bodyContainer.scrollLeft = this.elements.headerContainer.scrollLeft;
        });
        
        this.elements.bodyContainer.addEventListener('scroll', () => {
            this.elements.headerContainer.scrollLeft = this.elements.bodyContainer.scrollLeft;
        });
        
        console.log('🔗 Scroll sync listeners added');
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
            th.style.minWidth = width;
            
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
        
        // סנכרן רוחבים עם טבלת התוכן
        this.syncColumnWidths();
        
        // הדפס את רוחבי העמודות לקונסול
        console.log('📏 Column Widths:', this.getColumnWidths());
    }
    
    /**
     * סנכרן רוחבי עמודות בין הכותרת לתוכן
     */
    syncColumnWidths() {
        // יישם את אותם רוחבים על שתי הטבלאות
        const headerCells = this.elements.headerTable.querySelectorAll('th');
        const bodyCols = this.elements.bodyTable.querySelectorAll('colgroup col');
        
        // אם אין colgroup, צור אחד
        if (bodyCols.length === 0) {
            const colgroup = document.createElement('colgroup');
            this.state.columnOrder.forEach(colIndex => {
                const col = document.createElement('col');
                const width = this.state.columnWidths[colIndex];
                col.style.width = width;
                col.style.minWidth = width;
                colgroup.appendChild(col);
            });
            this.elements.bodyTable.insertBefore(colgroup, this.elements.tbody);
        } else {
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
            
            // RTL FIX
            const diff = e.pageX - startX;
            const newWidth = Math.max(50, startWidth - diff);
            this.state.columnWidths[colIndex] = `${newWidth}px`;
            
            // עדכן כותרת
            const th = this.elements.headerTable.querySelector(`th[data-column-index="${colIndex}"]`);
            if (th) {
                th.style.width = `${newWidth}px`;
                th.style.minWidth = `${newWidth}px`;
            }
            
            // עדכן colgroup
            const col = this.elements.bodyTable.querySelector(`colgroup col:nth-child(${colIndex + 1})`);
            if (col) {
                col.style.width = `${newWidth}px`;
                col.style.minWidth = `${newWidth}px`;
            }
        };
        
        const onMouseUp = () => {
            this.state.isResizing = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            
            // הדפס את המידות החדשות
            console.log('📏 Updated Column Widths:', this.getColumnWidths());
        };
        
        this.elements.headerTable.addEventListener('mousedown', onMouseDown);
    }
    
    /**
     * אתחול Infinite Scroll
     */
    initInfiniteScroll() {
        // גלילה על ה-body container
        this.elements.bodyContainer.addEventListener('scroll', () => {
            if (this.state.isLoading) return;
            
            const { scrollTop, scrollHeight, clientHeight } = this.elements.bodyContainer;
            const distanceFromBottom = scrollHeight - (scrollTop + clientHeight);
            
            if (distanceFromBottom < this.config.scrollThreshold) {
                this.loadMoreData();
            }
        });
        
        console.log('📜 Infinite scroll initialized on body container');
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