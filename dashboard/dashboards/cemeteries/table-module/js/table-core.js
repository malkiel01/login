/*
 * File: table-module/js/table-core.js
 * Version: 3.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: מנוע טבלאות מרכזי - גרסה מודולרית עם תיקוני באגים
 *
 * שינויים מ-v2.1.0:
 * - תיקון Race Condition ב-infinite scroll
 * - תיקון Memory Leak - ניקוי event listeners
 * - תמיכה בהעדפות משתמש
 * - תמיכה בהרשאות צפיה/עריכה
 * - מבנה מודולרי
 */

class TableManager {
    static instances = new Map();
    static instanceCounter = 0;

    constructor(config) {
        // מזהה ייחודי לכל instance
        this.instanceId = `table_${++TableManager.instanceCounter}`;

        // שמירת reference לניקוי
        TableManager.instances.set(this.instanceId, this);

        // ניהול event listeners לניקוי
        this._boundHandlers = new Map();
        this._abortController = new AbortController();

        this.config = {
            tableSelector: null,
            columns: [],
            data: [],

            // הגדרות תצוגה
            containerWidth: '100%',
            containerPadding: '0',
            tableHeight: 'calc(100vh - 250px)',
            tableMinHeight: '500px',

            // פרמטרים לטעינת נתונים
            totalItems: null,
            scrollLoadBatch: 100,
            scrollThreshold: 100,

            // Pagination
            itemsPerPage: 999999,
            showPagination: false,
            paginationOptions: [25, 50, 100, 200, 500, 'all'],

            // הגדרות כלליות
            sortable: true,
            resizable: true,
            reorderable: true,
            filterable: true,
            multiSelect: false,

            // Callbacks
            renderCell: null,
            onSort: null,
            onRowDoubleClick: null,
            onFilter: null,
            onColumnReorder: null,
            onLoadMore: null,
            onPageChange: null,
            onSelectionChange: null,

            // === חדש v3.0.0 ===
            // הרשאות
            permissions: {
                canView: true,
                canEdit: true,
                canDelete: true,
                canExport: true,
                visibleColumns: null  // null = הכל, או מערך של field names
            },

            // העדפות משתמש (נטענות אוטומטית)
            userPreferences: {
                enabled: true,
                storageKey: null  // יחושב אוטומטית לפי entityType
            },

            // סוג entity לשמירת העדפות
            entityType: null,

            ...config
        };

        // חישוב totalItems אם לא סופק
        if (this.config.totalItems === null) {
            this.config.totalItems = this.config.data.length;
        }

        // הפעלת pagination אוטומטית
        if (this.config.itemsPerPage < 999999) {
            this.config.showPagination = true;
        }

        this.state = {
            sortColumn: null,
            sortOrder: 'asc',
            columnWidths: {},
            columnOrder: [],
            columnVisibility: {},
            filters: new Map(),
            isResizing: false,
            isDragging: false,

            // Pagination
            currentPage: 1,
            totalPages: 1,

            // Scroll loading - תיקון Race Condition
            isLoading: false,
            loadingPromise: null,  // שמירת Promise למניעת קריאות כפולות
            hasMoreData: true,

            // בחירה מרובה
            multiSelectEnabled: false,
            selectedRows: new Set(),

            filteredData: [],
            displayedData: []
        };

        this.elements = {
            table: null,
            thead: null,
            tbody: null,
            wrapper: null,
            headerContainer: null,
            bodyContainer: null,
            headerTable: null,
            bodyTable: null,
            paginationFooter: null
        };

        // טעינת העדפות משתמש לפני אתחול
        this._loadUserPreferences().then(() => {
            this.init();
        });
    }

    /**
     * טעינת העדפות משתמש
     */
    async _loadUserPreferences() {
        if (!this.config.userPreferences.enabled) return;

        try {
            if (typeof UserSettings !== 'undefined') {
                // שימוש בהגדרות המשתמש הגלובליות
                const tableRowsPerPage = await UserSettings.getAsync('tableRowsPerPage', 25);

                // החלת העדפות
                if (tableRowsPerPage && this.config.itemsPerPage === 999999) {
                    this.config.itemsPerPage = parseInt(tableRowsPerPage) || 25;
                    if (this.config.itemsPerPage < 999999) {
                        this.config.showPagination = true;
                    }
                }
            }
        } catch (error) {
            console.warn('TableManager: Failed to load user preferences', error);
        }
    }

    /**
     * אתחול
     */
    init() {
        this.elements.table = document.querySelector(this.config.tableSelector);

        if (!this.elements.table) {
            console.error('TableManager: Table not found:', this.config.tableSelector);
            return;
        }

        // חישוב totalItems
        if (this.config.totalItems === null || this.config.totalItems === 0) {
            this.config.totalItems = this.config.data.length;
        }

        // אתחול סדר עמודות (עם בדיקת הרשאות)
        this._initColumns();

        // אתחול בחירה מרובה
        this.state.multiSelectEnabled = this.config.multiSelect || false;

        // חישוב עמודים
        this.calculateTotalPages();

        // בניית הטבלה
        this.buildTable();

        // קישור אירועים
        this.bindEvents();

        // אתחול Infinite Scroll
        if (this.config.scrollLoadBatch > 0) {
            this.initInfiniteScroll();
        }

        console.log(`TableManager [${this.instanceId}]: Initialized`);
    }

    /**
     * אתחול עמודות עם בדיקת הרשאות
     */
    _initColumns() {
        const visibleCols = this.config.permissions.visibleColumns;

        this.state.columnOrder = [];
        this.state.columnWidths = {};
        this.state.columnVisibility = {};

        this.config.columns.forEach((col, index) => {
            // בדיקת הרשאה לראות עמודה
            const isAllowed = visibleCols === null || visibleCols.includes(col.field);
            const isVisible = col.visible !== false && isAllowed;

            this.state.columnOrder.push(index);
            this.state.columnWidths[index] = col.width || 'auto';
            this.state.columnVisibility[index] = isVisible;
        });
    }

    /**
     * חישוב מספר עמודים
     */
    calculateTotalPages() {
        if (this.config.itemsPerPage >= 999999) {
            this.state.totalPages = 1;
        } else {
            this.state.totalPages = Math.ceil(this.config.totalItems / this.config.itemsPerPage);
        }
    }

    /**
     * בניית מבנה הטבלה
     */
    buildTable() {
        let parent = this.elements.table.parentNode;

        // תיקון overflow של הורים
        this._fixParentOverflow(parent);

        // יצירת קונטיינרים
        const { fixedContainer, wrapper, headerContainer, bodyContainer } = this._createContainers(parent);

        // יצירת טבלאות
        this._createTables(headerContainer, bodyContainer);

        // הכנסה ל-DOM
        parent.insertBefore(fixedContainer, this.elements.table);
        fixedContainer.appendChild(wrapper);
        wrapper.appendChild(headerContainer);
        wrapper.appendChild(bodyContainer);

        // הסרת הטבלה המקורית
        this.elements.table.style.display = 'none';

        // שמירת references
        this.elements.wrapper = wrapper;
        this.elements.headerContainer = headerContainer;
        this.elements.bodyContainer = bodyContainer;

        // סנכרון גלילה אופקית
        this._syncHorizontalScroll();

        // Pagination footer
        if (this.config.showPagination) {
            this._buildPaginationFooter(wrapper);
        }

        // טעינת נתונים ראשונית
        this.loadInitialData();
    }

    /**
     * תיקון overflow של הורים
     */
    _fixParentOverflow(parent) {
        let currentParent = parent;

        while (currentParent && currentParent !== document.body) {
            if (currentParent.classList.contains('table-container')) {
                currentParent.style.cssText = `
                    width: ${this.config.containerWidth} !important;
                    padding: ${this.config.containerPadding} !important;
                    margin: 0 !important;
                    overflow: hidden !important;
                    max-height: none !important;
                    height: auto !important;
                    box-sizing: border-box !important;
                `;
                break;
            }
            currentParent = currentParent.parentElement;
        }
    }

    /**
     * יצירת קונטיינרים
     */
    _createContainers(parent) {
        const parentWidth = parent.offsetWidth;

        const fixedContainer = document.createElement('div');
        fixedContainer.className = 'table-fixed-container';
        fixedContainer.style.cssText = `
            width: ${parentWidth}px !important;
            max-width: ${parentWidth}px !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        `;

        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        wrapper.setAttribute('data-table-manager', 'v3.0.0');
        wrapper.setAttribute('data-instance-id', this.instanceId);
        wrapper.style.cssText = `
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
            height: ${this.config.tableHeight} !important;
            min-height: ${this.config.tableMinHeight} !important;
            border: 1px solid var(--border-color, #e5e7eb) !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            background: var(--bg-primary, white) !important;
            position: relative !important;
            box-sizing: border-box !important;
        `;

        const headerContainer = document.createElement('div');
        headerContainer.className = 'table-header-container';
        headerContainer.style.cssText = `
            flex-shrink: 0 !important;
            width: 100% !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            background: var(--bg-primary, white) !important;
            border-bottom: 2px solid var(--border-color, #e5e7eb) !important;
            position: relative !important;
            z-index: 100 !important;
        `;

        const bodyContainer = document.createElement('div');
        bodyContainer.className = 'table-body-container';
        bodyContainer.style.cssText = `
            flex: 1 !important;
            width: 100% !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
            position: relative !important;
            height: 100% !important;
        `;

        return { fixedContainer, wrapper, headerContainer, bodyContainer };
    }

    /**
     * יצירת טבלאות
     */
    _createTables(headerContainer, bodyContainer) {
        // חישוב רוחב טבלה
        let initialWidth = this._calculateTableWidth();

        // טבלת כותרת
        const headerTable = document.createElement('table');
        headerTable.className = 'tm-table tm-header-table';
        headerTable.style.cssText = `
            width: ${initialWidth}px;
            min-width: ${initialWidth}px;
            table-layout: fixed;
            border-collapse: collapse;
        `;

        const thead = document.createElement('thead');
        thead.className = 'tm-thead';
        headerTable.appendChild(thead);
        headerContainer.appendChild(headerTable);

        // טבלת גוף
        const bodyTable = document.createElement('table');
        bodyTable.className = 'tm-table tm-body-table';
        bodyTable.style.cssText = `
            width: ${initialWidth}px;
            min-width: ${initialWidth}px;
            table-layout: fixed;
            border-collapse: collapse;
        `;

        const tbody = document.createElement('tbody');
        tbody.className = 'tm-tbody';
        bodyTable.appendChild(tbody);
        bodyContainer.appendChild(bodyTable);

        // שמירת references
        this.elements.headerTable = headerTable;
        this.elements.bodyTable = bodyTable;
        this.elements.thead = thead;
        this.elements.tbody = tbody;

        // ציור כותרות
        this.renderHeaders();
    }

    /**
     * חישוב רוחב טבלה
     */
    _calculateTableWidth() {
        let width = 0;

        this.state.columnOrder.forEach(colIndex => {
            if (!this.state.columnVisibility[colIndex]) return;

            const col = this.config.columns[colIndex];
            const colWidth = this.state.columnWidths[colIndex];

            if (typeof colWidth === 'number') {
                width += colWidth;
            } else if (typeof colWidth === 'string' && colWidth.endsWith('px')) {
                width += parseInt(colWidth);
            } else {
                width += col.minWidth || 100;
            }
        });

        return width + 2;  // מרווח קטן
    }

    /**
     * סנכרון גלילה אופקית - עם ניקוי event listeners
     */
    _syncHorizontalScroll() {
        const headerContainer = this.elements.headerContainer;
        const bodyContainer = this.elements.bodyContainer;

        // שמירת handler לניקוי עתידי
        const scrollHandler = () => {
            headerContainer.scrollLeft = bodyContainer.scrollLeft;
        };

        this._boundHandlers.set('bodyScroll', scrollHandler);

        bodyContainer.addEventListener('scroll', scrollHandler, {
            signal: this._abortController.signal
        });
    }

    /**
     * אתחול Infinite Scroll - עם תיקון Race Condition
     */
    initInfiniteScroll() {
        const scrollHandler = () => {
            // תיקון: בדיקה אטומית למניעת race condition
            if (this.state.isLoading || !this.state.hasMoreData) return;

            const { scrollTop, scrollHeight, clientHeight } = this.elements.bodyContainer;
            const distanceFromBottom = scrollHeight - (scrollTop + clientHeight);

            if (distanceFromBottom < this.config.scrollThreshold) {
                this.loadMoreData();
            }
        };

        this._boundHandlers.set('infiniteScroll', scrollHandler);

        this.elements.bodyContainer.addEventListener('scroll', scrollHandler, {
            signal: this._abortController.signal
        });
    }

    /**
     * טעינת עוד נתונים - תיקון Race Condition
     */
    async loadMoreData() {
        // תיקון קריטי: בדיקה וקביעה אטומית
        if (this.state.isLoading) {
            return this.state.loadingPromise;  // החזר Promise קיים
        }

        this.state.isLoading = true;

        // שמור Promise למניעת קריאות כפולות
        this.state.loadingPromise = this._doLoadMoreData();

        try {
            await this.state.loadingPromise;
        } finally {
            this.state.isLoading = false;
            this.state.loadingPromise = null;
        }
    }

    /**
     * ביצוע טעינת נתונים בפועל
     */
    async _doLoadMoreData() {
        const loadedItems = this.state.displayedData.length;
        const totalAvailable = this.state.filteredData.length;

        // בדיקה 1: הגענו לסוף?
        if (loadedItems >= this.config.totalItems) {
            this.state.hasMoreData = false;
            return;
        }

        // בדיקה 2: צריך לקרוא מה-API?
        if (loadedItems >= totalAvailable) {
            if (this.config.onLoadMore) {
                this.showLoadingIndicator();

                try {
                    await this.config.onLoadMore();
                } catch (error) {
                    console.error('TableManager: Error loading more data:', error);
                } finally {
                    this.hideLoadingIndicator();
                }
            }
            return;
        }

        // בדיקה 3: יש עוד נתונים ב-filteredData
        this.showLoadingIndicator();

        // המתנה קצרה לשיפור UX
        await new Promise(resolve => setTimeout(resolve, 100));

        const nextBatch = this.state.filteredData.slice(
            loadedItems,
            loadedItems + this.config.scrollLoadBatch
        );

        this.state.displayedData = [...this.state.displayedData, ...nextBatch];

        this.renderRows(true);
        this.hideLoadingIndicator();
    }

    /**
     * ציור כותרות
     */
    renderHeaders() {
        const thead = this.elements.thead;
        thead.innerHTML = '';

        const tr = document.createElement('tr');
        tr.className = 'tm-header-row';

        // עמודת checkbox אם יש multiSelect
        if (this.state.multiSelectEnabled) {
            const th = document.createElement('th');
            th.className = 'tm-header-cell tm-checkbox-cell';
            th.style.width = '40px';
            th.innerHTML = `
                <input type="checkbox" class="tm-select-all"
                       onchange="TableManager.instances.get('${this.instanceId}').toggleSelectAll(this.checked)">
            `;
            tr.appendChild(th);
        }

        // עמודות רגילות
        this.state.columnOrder.forEach(colIndex => {
            if (!this.state.columnVisibility[colIndex]) return;

            const col = this.config.columns[colIndex];
            const th = this._createHeaderCell(col, colIndex);
            tr.appendChild(th);
        });

        thead.appendChild(tr);
    }

    /**
     * יצירת תא כותרת
     */
    _createHeaderCell(col, colIndex) {
        const th = document.createElement('th');
        th.className = 'tm-header-cell';
        th.setAttribute('data-col-index', colIndex);
        th.style.width = this.state.columnWidths[colIndex];

        // תוכן
        let content = `<span class="tm-header-label">${col.label || col.field}</span>`;

        // אייקון מיון
        if (this.config.sortable && col.sortable !== false) {
            content += `<span class="tm-sort-icon"></span>`;
        }

        th.innerHTML = content;

        // Handle לשינוי גודל
        if (this.config.resizable && col.resizable !== false) {
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'tm-resize-handle';
            th.appendChild(resizeHandle);
        }

        return th;
    }

    /**
     * ציור שורות
     */
    renderRows(append = false) {
        const tbody = this.elements.tbody;

        if (!append) {
            tbody.innerHTML = '';
        }

        const startIndex = append ? tbody.querySelectorAll('tr:not(.tm-loading-indicator)').length : 0;
        const dataToRender = append
            ? this.state.displayedData.slice(startIndex)
            : this.state.displayedData;

        if (dataToRender.length === 0 && !append) {
            this._renderEmptyState();
            return;
        }

        const fragment = document.createDocumentFragment();

        dataToRender.forEach((row, index) => {
            const tr = this._createRow(row, startIndex + index);
            fragment.appendChild(tr);
        });

        tbody.appendChild(fragment);

        // עדכון footer
        this._updateFooterInfo();
    }

    /**
     * יצירת שורה
     */
    _createRow(rowData, rowIndex) {
        const tr = document.createElement('tr');
        tr.className = 'tm-row';
        tr.setAttribute('data-row-index', rowIndex);

        const rowId = rowData.id || rowData.unicId || rowIndex;
        tr.setAttribute('data-row-id', rowId);

        // בדיקת בחירה
        if (this.state.selectedRows.has(rowId)) {
            tr.classList.add('tm-selected');
        }

        // עמודת checkbox
        if (this.state.multiSelectEnabled) {
            const td = document.createElement('td');
            td.className = 'tm-cell tm-checkbox-cell';
            td.innerHTML = `
                <input type="checkbox" class="tm-row-checkbox"
                       ${this.state.selectedRows.has(rowId) ? 'checked' : ''}
                       onchange="TableManager.instances.get('${this.instanceId}').toggleRowSelection('${rowId}', this.checked)">
            `;
            tr.appendChild(td);
        }

        // עמודות רגילות
        this.state.columnOrder.forEach(colIndex => {
            if (!this.state.columnVisibility[colIndex]) return;

            const col = this.config.columns[colIndex];
            const td = this._createCell(col, rowData, colIndex);
            tr.appendChild(td);
        });

        // אירועים
        tr.addEventListener('dblclick', () => {
            if (this.config.onRowDoubleClick) {
                this.config.onRowDoubleClick(rowData, rowIndex);
            }
        });

        return tr;
    }

    /**
     * יצירת תא
     */
    _createCell(col, rowData, colIndex) {
        const td = document.createElement('td');
        td.className = 'tm-cell';
        td.style.width = this.state.columnWidths[colIndex];

        let value = rowData[col.field];
        let content = '';

        // Custom renderer
        if (this.config.renderCell) {
            content = this.config.renderCell(col, rowData, value);
        } else if (col.render) {
            content = col.render(value, rowData);
        } else {
            // ערך ברירת מחדל עם בדיקת null
            content = value !== null && value !== undefined ? value : '';
        }

        td.innerHTML = content;
        return td;
    }

    /**
     * מצב ריק
     */
    _renderEmptyState() {
        const tr = document.createElement('tr');
        tr.className = 'tm-empty-row';
        tr.innerHTML = `
            <td colspan="100" style="text-align: center; padding: 40px; color: var(--text-muted, #6b7280);">
                <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                <div>אין נתונים להצגה</div>
            </td>
        `;
        this.elements.tbody.appendChild(tr);
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
                <div style="margin-top: 10px; color: var(--text-muted, #6b7280);">טוען עוד נתונים...</div>
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
     * טעינת נתונים ראשונית
     */
    loadInitialData() {
        // סינון
        this.state.filteredData = this._applyFilters(this.config.data);

        // Pagination או infinite scroll
        if (this.config.showPagination) {
            const start = (this.state.currentPage - 1) * this.config.itemsPerPage;
            const end = start + this.config.itemsPerPage;
            this.state.displayedData = this.state.filteredData.slice(start, end);
        } else if (this.config.scrollLoadBatch > 0) {
            this.state.displayedData = this.state.filteredData.slice(0, this.config.scrollLoadBatch);
            this.state.hasMoreData = this.state.displayedData.length < this.state.filteredData.length;
        } else {
            this.state.displayedData = this.state.filteredData;
        }

        // ציור
        this.renderRows(false);
    }

    /**
     * החלת פילטרים
     */
    _applyFilters(data) {
        if (this.state.filters.size === 0) {
            return [...data];
        }

        return data.filter(row => {
            for (const [field, filterConfig] of this.state.filters) {
                const value = row[field];

                if (!this._matchesFilter(value, filterConfig)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * בדיקת התאמה לפילטר
     */
    _matchesFilter(value, filterConfig) {
        const { type, value: filterValue, operator } = filterConfig;

        if (filterValue === '' || filterValue === null) return true;

        switch (type) {
            case 'text':
                return String(value || '').toLowerCase().includes(String(filterValue).toLowerCase());

            case 'number':
                const numVal = parseFloat(value);
                const numFilter = parseFloat(filterValue);
                if (isNaN(numVal)) return false;

                switch (operator) {
                    case 'eq': return numVal === numFilter;
                    case 'gt': return numVal > numFilter;
                    case 'lt': return numVal < numFilter;
                    case 'gte': return numVal >= numFilter;
                    case 'lte': return numVal <= numFilter;
                    default: return numVal === numFilter;
                }

            case 'select':
                return String(value) === String(filterValue);

            default:
                return true;
        }
    }

    /**
     * קישור אירועים
     */
    bindEvents() {
        // מיון
        if (this.config.sortable) {
            this.elements.thead.addEventListener('click', (e) => {
                const th = e.target.closest('.tm-header-cell');
                if (!th || e.target.closest('.tm-resize-handle')) return;

                const colIndex = parseInt(th.dataset.colIndex);
                this.sortByColumn(colIndex);
            }, { signal: this._abortController.signal });
        }

        // שינוי גודל
        if (this.config.resizable) {
            this._initResizing();
        }
    }

    /**
     * אתחול שינוי גודל עמודות
     */
    _initResizing() {
        let isResizing = false;
        let currentTh = null;
        let startX = 0;
        let startWidth = 0;

        const onMouseMove = (e) => {
            if (!isResizing) return;

            const diff = e.pageX - startX;
            const newWidth = Math.max(50, startWidth + diff);

            const colIndex = currentTh.dataset.colIndex;
            this.state.columnWidths[colIndex] = newWidth + 'px';
            currentTh.style.width = newWidth + 'px';

            // עדכון עמודה מקבילה בגוף
            const bodyCell = this.elements.tbody.querySelector(`td[data-col-index="${colIndex}"]`);
            if (bodyCell) {
                bodyCell.style.width = newWidth + 'px';
            }
        };

        const onMouseUp = () => {
            isResizing = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        this.elements.thead.addEventListener('mousedown', (e) => {
            if (!e.target.classList.contains('tm-resize-handle')) return;

            isResizing = true;
            currentTh = e.target.closest('.tm-header-cell');
            startX = e.pageX;
            startWidth = currentTh.offsetWidth;

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }, { signal: this._abortController.signal });
    }

    /**
     * מיון לפי עמודה
     */
    sortByColumn(colIndex) {
        const col = this.config.columns[colIndex];
        if (!col || col.sortable === false) return;

        // החלפת כיוון
        if (this.state.sortColumn === colIndex) {
            this.state.sortOrder = this.state.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.state.sortColumn = colIndex;
            this.state.sortOrder = 'asc';
        }

        // מיון
        const field = col.field;
        this.state.filteredData.sort((a, b) => {
            let valA = a[field];
            let valB = b[field];

            // טיפול ב-null/undefined
            if (valA == null) return this.state.sortOrder === 'asc' ? 1 : -1;
            if (valB == null) return this.state.sortOrder === 'asc' ? -1 : 1;

            // השוואה
            if (typeof valA === 'number' && typeof valB === 'number') {
                return this.state.sortOrder === 'asc' ? valA - valB : valB - valA;
            }

            const strA = String(valA).toLowerCase();
            const strB = String(valB).toLowerCase();
            const cmp = strA.localeCompare(strB, 'he');

            return this.state.sortOrder === 'asc' ? cmp : -cmp;
        });

        // עדכון אייקון מיון
        this._updateSortIcons();

        // טעינה מחדש
        this.state.currentPage = 1;
        this.loadInitialData();

        // Callback
        if (this.config.onSort) {
            this.config.onSort(field, this.state.sortOrder);
        }
    }

    /**
     * עדכון אייקוני מיון
     */
    _updateSortIcons() {
        this.elements.thead.querySelectorAll('.tm-sort-icon').forEach(icon => {
            icon.classList.remove('asc', 'desc');
        });

        if (this.state.sortColumn !== null) {
            const th = this.elements.thead.querySelector(`[data-col-index="${this.state.sortColumn}"]`);
            if (th) {
                const icon = th.querySelector('.tm-sort-icon');
                if (icon) {
                    icon.classList.add(this.state.sortOrder);
                }
            }
        }
    }

    /**
     * בניית footer pagination
     */
    _buildPaginationFooter(wrapper) {
        const footer = document.createElement('div');
        footer.className = 'tm-pagination-footer';
        footer.style.cssText = `
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: var(--bg-secondary, #f9fafb);
            border-top: 1px solid var(--border-color, #e5e7eb);
            font-size: 14px;
        `;

        footer.innerHTML = `
            <div class="tm-pagination-info">
                <span class="tm-showing">מציג 0-0</span>
                <span class="tm-total">מתוך 0</span>
            </div>
            <div class="tm-pagination-controls">
                <button class="tm-page-btn tm-first" title="ראשון">⏮</button>
                <button class="tm-page-btn tm-prev" title="הקודם">◀</button>
                <span class="tm-page-info">עמוד 1 מ-1</span>
                <button class="tm-page-btn tm-next" title="הבא">▶</button>
                <button class="tm-page-btn tm-last" title="אחרון">⏭</button>
                <select class="tm-page-size">
                    ${this.config.paginationOptions.map(opt =>
                        `<option value="${opt === 'all' ? 999999 : opt}"
                                 ${opt === this.config.itemsPerPage ? 'selected' : ''}>
                            ${opt === 'all' ? 'הכל' : opt}
                        </option>`
                    ).join('')}
                </select>
            </div>
        `;

        wrapper.appendChild(footer);
        this.elements.paginationFooter = footer;

        // אירועים
        footer.querySelector('.tm-first').addEventListener('click', () => this.goToPage(1));
        footer.querySelector('.tm-prev').addEventListener('click', () => this.goToPage(this.state.currentPage - 1));
        footer.querySelector('.tm-next').addEventListener('click', () => this.goToPage(this.state.currentPage + 1));
        footer.querySelector('.tm-last').addEventListener('click', () => this.goToPage(this.state.totalPages));

        footer.querySelector('.tm-page-size').addEventListener('change', (e) => {
            this.config.itemsPerPage = parseInt(e.target.value);
            this.calculateTotalPages();
            this.goToPage(1);

            // שמירת העדפה
            this._saveUserPreference('tableRowsPerPage', this.config.itemsPerPage);
        });
    }

    /**
     * מעבר לעמוד
     */
    goToPage(page) {
        page = Math.max(1, Math.min(page, this.state.totalPages));

        if (page === this.state.currentPage) return;

        this.state.currentPage = page;
        this.loadInitialData();

        if (this.config.onPageChange) {
            this.config.onPageChange(page);
        }
    }

    /**
     * עדכון מידע footer
     */
    _updateFooterInfo() {
        if (!this.elements.paginationFooter) return;

        const total = this.config.totalItems;
        const displayed = this.state.displayedData.length;
        const start = this.config.showPagination
            ? (this.state.currentPage - 1) * this.config.itemsPerPage + 1
            : 1;
        const end = this.config.showPagination
            ? Math.min(start + this.config.itemsPerPage - 1, total)
            : displayed;

        const showing = this.elements.paginationFooter.querySelector('.tm-showing');
        const totalEl = this.elements.paginationFooter.querySelector('.tm-total');
        const pageInfo = this.elements.paginationFooter.querySelector('.tm-page-info');

        if (showing) showing.textContent = `מציג ${start}-${end}`;
        if (totalEl) totalEl.textContent = `מתוך ${total.toLocaleString()}`;
        if (pageInfo) pageInfo.textContent = `עמוד ${this.state.currentPage} מ-${this.state.totalPages}`;

        // הפעלת/כיבוי כפתורים
        const firstBtn = this.elements.paginationFooter.querySelector('.tm-first');
        const prevBtn = this.elements.paginationFooter.querySelector('.tm-prev');
        const nextBtn = this.elements.paginationFooter.querySelector('.tm-next');
        const lastBtn = this.elements.paginationFooter.querySelector('.tm-last');

        if (firstBtn) firstBtn.disabled = this.state.currentPage <= 1;
        if (prevBtn) prevBtn.disabled = this.state.currentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.state.currentPage >= this.state.totalPages;
        if (lastBtn) lastBtn.disabled = this.state.currentPage >= this.state.totalPages;
    }

    /**
     * שמירת העדפת משתמש
     */
    async _saveUserPreference(key, value) {
        if (!this.config.userPreferences.enabled) return;

        try {
            if (typeof UserSettings !== 'undefined') {
                await UserSettings.set(key, value);
            }
        } catch (error) {
            console.warn('TableManager: Failed to save user preference', error);
        }
    }

    // ====================================
    // בחירה מרובה
    // ====================================

    toggleSelectAll(checked) {
        if (checked) {
            this.state.displayedData.forEach(row => {
                const id = row.id || row.unicId;
                if (id) this.state.selectedRows.add(id);
            });
        } else {
            this.state.selectedRows.clear();
        }

        this._updateRowSelections();
        this._notifySelectionChange();
    }

    toggleRowSelection(rowId, checked) {
        if (checked) {
            this.state.selectedRows.add(rowId);
        } else {
            this.state.selectedRows.delete(rowId);
        }

        const row = this.elements.tbody.querySelector(`[data-row-id="${rowId}"]`);
        if (row) {
            row.classList.toggle('tm-selected', checked);
        }

        this._updateSelectAllCheckbox();
        this._notifySelectionChange();
    }

    _updateRowSelections() {
        this.elements.tbody.querySelectorAll('.tm-row').forEach(row => {
            const rowId = row.dataset.rowId;
            const isSelected = this.state.selectedRows.has(rowId);

            row.classList.toggle('tm-selected', isSelected);

            const checkbox = row.querySelector('.tm-row-checkbox');
            if (checkbox) {
                checkbox.checked = isSelected;
            }
        });
    }

    _updateSelectAllCheckbox() {
        const selectAll = this.elements.thead.querySelector('.tm-select-all');
        if (!selectAll) return;

        const displayedCount = this.state.displayedData.length;
        const selectedCount = this.state.selectedRows.size;

        selectAll.checked = displayedCount > 0 && selectedCount >= displayedCount;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < displayedCount;
    }

    _notifySelectionChange() {
        if (this.config.onSelectionChange) {
            this.config.onSelectionChange(Array.from(this.state.selectedRows));
        }
    }

    getSelectedRows() {
        return Array.from(this.state.selectedRows);
    }

    clearSelection() {
        this.state.selectedRows.clear();
        this._updateRowSelections();
        this._updateSelectAllCheckbox();
        this._notifySelectionChange();
    }

    // ====================================
    // API ציבורי
    // ====================================

    setData(data, totalItems = null) {
        this.config.data = data;
        if (totalItems !== null) {
            this.config.totalItems = totalItems;
        } else {
            this.config.totalItems = data.length;
        }
        this.calculateTotalPages();
        this.state.hasMoreData = true;
        this.loadInitialData();
    }

    appendData(newData) {
        this.config.data = [...this.config.data, ...newData];
        this.state.filteredData = this._applyFilters(this.config.data);
        // לא צריך לרנדר - infinite scroll יטפל
    }

    refresh() {
        this.loadInitialData();
    }

    setFilter(field, filterConfig) {
        if (filterConfig.value === '' || filterConfig.value === null) {
            this.state.filters.delete(field);
        } else {
            this.state.filters.set(field, filterConfig);
        }

        this.state.currentPage = 1;
        this.loadInitialData();

        if (this.config.onFilter) {
            this.config.onFilter(field, filterConfig);
        }
    }

    clearFilters() {
        this.state.filters.clear();
        this.state.currentPage = 1;
        this.loadInitialData();
    }

    /**
     * קבלת נתונים מסוננים
     * @returns {Array} הנתונים לאחר סינון
     */
    getFilteredData() {
        return this.state.filteredData;
    }

    /**
     * קבלת נתונים מוצגים
     * @returns {Array} הנתונים המוצגים כרגע
     */
    getDisplayedData() {
        return this.state.displayedData;
    }

    getColumnWidths() {
        const widths = {};
        this.config.columns.forEach((col, index) => {
            widths[col.field || col.label] = this.state.columnWidths[index];
        });
        return widths;
    }

    setColumnVisibility(field, visible) {
        const index = this.config.columns.findIndex(c => c.field === field);
        if (index !== -1) {
            this.state.columnVisibility[index] = visible;
            this.renderHeaders();
            this.renderRows(false);
        }
    }

    /**
     * הרס - ניקוי כל המשאבים
     */
    destroy() {
        console.log(`TableManager [${this.instanceId}]: Destroying...`);

        // ביטול כל ה-event listeners
        this._abortController.abort();

        // ניקוי DOM
        if (this.elements.wrapper) {
            this.elements.wrapper.remove();
        }

        // הצגת הטבלה המקורית
        if (this.elements.table) {
            this.elements.table.style.display = '';
        }

        // הסרה מה-instances
        TableManager.instances.delete(this.instanceId);

        console.log(`TableManager [${this.instanceId}]: Destroyed`);
    }

    /**
     * הרס כל ה-instances
     */
    static destroyAll() {
        TableManager.instances.forEach(instance => {
            instance.destroy();
        });
    }
}

// Export
if (typeof window !== 'undefined') {
    window.TableManager = TableManager;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = TableManager;
}
