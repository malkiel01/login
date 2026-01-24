/*
 * File: table-module/js/table-core.js
 * Version: 3.2.0
 * Created: 2026-01-23
 * Updated: 2026-01-24
 * Author: Malkiel
 * Description: מנוע טבלאות מרכזי - גרסה מודולרית עם תיקוני באגים
 *
 * שינויים מ-v3.1.0:
 * - ⭐ תצוגת כרטיסים למובייל (cards view)
 * - ⭐ מעבר בין תצוגת רשימה לכרטיסים בהגדרות (רק בפלאפון)
 * - ⭐ שמירת העדפת תצוגת מובייל לכל entity
 * - ⭐ עיצוב כרטיסים עם Dark Mode
 *
 * שינויים מ-v3.0.0:
 * - תפריט עמודה (⋮) עם מיון וסינון
 * - פילטרים מתקדמים: טקסט, מספר, תאריך, enum
 * - אינדיקטור פילטר פעיל בכותרת
 * - תמיכה בסינון "בין" לתאריכים ומספרים
 * - תמיכה בתאריך משוער (±2.5 שנים)
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

            // ⭐ מצב תצוגה לפלאפון (cards/list)
            mobileViewMode: 'list', // 'list' או 'cards'

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
            headerColgroup: null,
            bodyColgroup: null,
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

        const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;

        try {
            if (typeof UserSettings !== 'undefined') {
                // שימוש בהגדרות המשתמש הגלובליות
                // ⭐ ברירת מחדל null - רק אם המשתמש בחר במפורש, נשנה את המצב
                const tableRowsPerPage = await UserSettings.getAsync('tableRowsPerPage', null);

                // החלת העדפות גלובלי - רק אם המשתמש שמר העדפה במפורש
                if (tableRowsPerPage !== null && this.config.itemsPerPage === 999999) {
                    const rows = parseInt(tableRowsPerPage);
                    if (rows && rows < 999999) {
                        this.config.itemsPerPage = rows;
                        this.config.showPagination = true;
                    }
                }

                // ⭐ טעינת מצב תצוגה לפי entity (עדיפות על גלובלי)
                const savedDisplayMode = await UserSettings.getAsync(`${storageKey}_displayMode`, null);
                if (savedDisplayMode !== null) {
                    const rows = parseInt(savedDisplayMode);
                    if (rows >= 999999) {
                        // מצב infinite scroll
                        this.config.itemsPerPage = 999999;
                        this.config.showPagination = false;
                    } else if (rows > 0) {
                        // מצב pagination
                        this.config.itemsPerPage = rows;
                        this.config.showPagination = true;
                    }
                }

                // טעינת רוחב עמודות שנשמר
                const savedColumnWidths = await UserSettings.getAsync(`${storageKey}_columnWidths`, null);
                if (savedColumnWidths) {
                    this._savedColumnWidths = JSON.parse(savedColumnWidths);
                }

                // טעינת נראות עמודות שנשמרה
                const savedColumnVisibility = await UserSettings.getAsync(`${storageKey}_columnVisibility`, null);
                if (savedColumnVisibility) {
                    this._savedColumnVisibility = JSON.parse(savedColumnVisibility);
                }

                // ⭐ טעינת מצב תצוגה למובייל
                const savedMobileViewMode = await UserSettings.getAsync(`${storageKey}_mobileViewMode`, null);
                if (savedMobileViewMode) {
                    this.state.mobileViewMode = savedMobileViewMode;
                }
            }
        } catch (error) {
            console.warn('TableManager: Failed to load user preferences', error);
        }
    }

    /**
     * ⭐ בדיקה אם מדובר במכשיר מובייל
     */
    _isMobileDevice() {
        return window.innerWidth <= 768 ||
               /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
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

        // אתחול Infinite Scroll - רק אם לא במצב pagination!
        if (this.config.scrollLoadBatch > 0 && !this.config.showPagination) {
            this.initInfiniteScroll();
        }

        // ⭐ הפעלת מצב כרטיסים אם במובייל ונבחר
        if (this._isMobileDevice() && this.state.mobileViewMode === 'cards') {
            this._refreshMobileView();
        }

        console.log(`TableManager [${this.instanceId}]: Initialized`, {
            mode: this.config.showPagination ? 'pagination' : 'infinite-scroll',
            itemsPerPage: this.config.itemsPerPage,
            mobileViewMode: this.state.mobileViewMode
        });
    }

    /**
     * אתחול עמודות עם בדיקת הרשאות והעדפות שמורות
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

            // ⭐ טען רוחב עמודה מהעדפות שמורות אם יש
            const fieldName = col.field || `col_${index}`;
            if (this._savedColumnWidths && this._savedColumnWidths[fieldName]) {
                this.state.columnWidths[index] = this._savedColumnWidths[fieldName];
            } else {
                this.state.columnWidths[index] = col.width || 'auto';
            }

            // ⭐ טען נראות עמודה מהעדפות שמורות אם יש
            if (this._savedColumnVisibility && typeof this._savedColumnVisibility[fieldName] === 'boolean') {
                this.state.columnVisibility[index] = this._savedColumnVisibility[fieldName] && isAllowed;
            } else {
                this.state.columnVisibility[index] = isVisible;
            }
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

        // בניית סרגל כלים
        const toolbar = this._buildToolbar();

        // הכנסה ל-DOM
        parent.insertBefore(fixedContainer, this.elements.table);
        fixedContainer.appendChild(wrapper);
        wrapper.appendChild(toolbar);
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
            min-height: ${this.config.tableMinHeight} !important;
            border: 1px solid var(--border-color, #e5e7eb) !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            background: var(--bg-primary, white) !important;
            position: relative !important;
            box-sizing: border-box !important;
        `;

        // ⭐ גובה דינמי - 50px מתחתית הדף
        this._setDynamicHeight = () => {
            const rect = wrapper.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const dynamicHeight = viewportHeight - rect.top - 50;
            wrapper.style.height = `${Math.max(dynamicHeight, parseInt(this.config.tableMinHeight) || 400)}px`;
        };

        // חישוב גובה לאחר הוספה ל-DOM
        requestAnimationFrame(() => {
            this._setDynamicHeight();
        });

        // עדכון גובה בשינוי גודל חלון
        window.addEventListener('resize', this._setDynamicHeight, {
            signal: this._abortController.signal
        });

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

        // ציור כותרות (לפני colgroup כדי שנוכל למדוד רוחב)
        this.renderHeaders();

        // יצירת colgroup לשתי הטבלאות (סנכרון רוחב עמודות)
        this._createColgroups();

        // ⭐ המרת רוחב auto לפיקסלים - מונע שינוי בעמודות אחרות
        this._convertAutoWidthsToPixels();
    }

    /**
     * ⭐ המרת רוחב auto לפיקסלים אחרי רינדור ראשוני
     * מונע מצב שעמודות אחרות משתנות כשמשנים עמודה אחת
     */
    _convertAutoWidthsToPixels() {
        const headerCells = this.elements.thead.querySelectorAll('.tm-header-cell');

        headerCells.forEach(th => {
            const colIndex = parseInt(th.dataset.colIndex);
            if (isNaN(colIndex)) return;

            // אם הרוחב הוא auto - המר לפיקסלים
            const currentWidth = this.state.columnWidths[colIndex];
            if (currentWidth === 'auto' || currentWidth === '') {
                const actualWidth = th.offsetWidth;
                this.state.columnWidths[colIndex] = actualWidth + 'px';
            }
        });

        // סנכרן מחדש את ה-colgroup עם הערכים החדשים
        this._syncColumnWidths();
    }

    /**
     * יצירת colgroup לסנכרון רוחב עמודות
     */
    _createColgroups() {
        // colgroup לטבלת כותרת
        const headerColgroup = document.createElement('colgroup');
        headerColgroup.className = 'tm-colgroup';
        this.elements.headerTable.insertBefore(headerColgroup, this.elements.thead);
        this.elements.headerColgroup = headerColgroup;

        // colgroup לטבלת גוף
        const bodyColgroup = document.createElement('colgroup');
        bodyColgroup.className = 'tm-colgroup';
        this.elements.bodyTable.insertBefore(bodyColgroup, this.elements.tbody);
        this.elements.bodyColgroup = bodyColgroup;

        // בניית col elements
        this._syncColumnWidths();
    }

    /**
     * סנכרון רוחב עמודות דרך colgroup
     */
    _syncColumnWidths() {
        // ניקוי colgroups קיימים
        if (this.elements.headerColgroup) {
            this.elements.headerColgroup.innerHTML = '';
        }
        if (this.elements.bodyColgroup) {
            this.elements.bodyColgroup.innerHTML = '';
        }

        // עמודת checkbox אם יש multiSelect
        if (this.state.multiSelectEnabled) {
            const headerCol = document.createElement('col');
            headerCol.style.width = '40px';
            this.elements.headerColgroup.appendChild(headerCol);

            const bodyCol = document.createElement('col');
            bodyCol.style.width = '40px';
            this.elements.bodyColgroup.appendChild(bodyCol);
        }

        // עמודות רגילות
        this.state.columnOrder.forEach(colIndex => {
            if (!this.state.columnVisibility[colIndex]) return;

            const width = this.state.columnWidths[colIndex];
            const widthStr = typeof width === 'number' ? width + 'px' : width;

            const headerCol = document.createElement('col');
            headerCol.style.width = widthStr;
            headerCol.setAttribute('data-col-index', colIndex);
            this.elements.headerColgroup.appendChild(headerCol);

            const bodyCol = document.createElement('col');
            bodyCol.style.width = widthStr;
            bodyCol.setAttribute('data-col-index', colIndex);
            this.elements.bodyColgroup.appendChild(bodyCol);
        });
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
     * סנכרון גלילה אופקית - דו-כיווני
     */
    _syncHorizontalScroll() {
        const headerContainer = this.elements.headerContainer;
        const bodyContainer = this.elements.bodyContainer;

        // מניעת לולאה אינסופית
        let isSyncing = false;

        // סנכרון מהתוכן לכותרת
        const bodyScrollHandler = () => {
            if (isSyncing) return;
            isSyncing = true;
            headerContainer.scrollLeft = bodyContainer.scrollLeft;
            isSyncing = false;
        };

        // ⭐ סנכרון מהכותרת לתוכן
        const headerScrollHandler = () => {
            if (isSyncing) return;
            isSyncing = true;
            bodyContainer.scrollLeft = headerContainer.scrollLeft;
            isSyncing = false;
        };

        this._boundHandlers.set('bodyScroll', bodyScrollHandler);
        this._boundHandlers.set('headerScroll', headerScrollHandler);

        bodyContainer.addEventListener('scroll', bodyScrollHandler, {
            signal: this._abortController.signal
        });

        headerContainer.addEventListener('scroll', headerScrollHandler, {
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
        // רוחב נקבע דרך colgroup - לא צריך להגדיר כאן

        // wrapper לתוכן
        const wrapper = document.createElement('div');
        wrapper.className = 'tm-header-wrapper';
        wrapper.style.cssText = `
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4px;
            width: 100%;
        `;

        // תוכן הכותרת (שם + אייקון מיון)
        const labelContainer = document.createElement('div');
        labelContainer.className = 'tm-header-label-container';
        labelContainer.style.cssText = `
            display: flex;
            align-items: center;
            gap: 4px;
            flex: 1;
            overflow: hidden;
        `;

        const label = document.createElement('span');
        label.className = 'tm-header-label';
        label.textContent = col.label || col.field;
        label.style.cssText = `overflow: hidden; text-overflow: ellipsis; white-space: nowrap;`;
        labelContainer.appendChild(label);

        // אייקון מיון
        if (this.config.sortable && col.sortable !== false) {
            const sortIcon = document.createElement('span');
            sortIcon.className = 'tm-sort-icon';
            labelContainer.appendChild(sortIcon);
        }

        wrapper.appendChild(labelContainer);

        // ⭐ כפתור תפריט עמודה (פילטר/מיון)
        if (this.config.filterable || this.config.sortable) {
            const menuBtn = document.createElement('button');
            menuBtn.className = 'tm-column-menu-btn';
            menuBtn.innerHTML = '⋮';
            menuBtn.style.cssText = `
                background: none;
                border: none;
                cursor: pointer;
                padding: 2px 6px;
                font-size: 14px;
                color: var(--text-muted, #6b7280);
                opacity: 0.6;
                transition: opacity 0.2s, color 0.2s;
                border-radius: 4px;
                flex-shrink: 0;
            `;
            menuBtn.onmouseover = () => {
                menuBtn.style.opacity = '1';
                menuBtn.style.background = 'var(--bg-tertiary, #e5e7eb)';
            };
            menuBtn.onmouseout = () => {
                menuBtn.style.opacity = '0.6';
                menuBtn.style.background = 'none';
            };
            menuBtn.onclick = (e) => {
                e.stopPropagation();
                this._showColumnMenu(colIndex, menuBtn, col);
            };

            // ⭐ אינדיקטור פילטר פעיל
            const hasFilter = this.state.filters.has(col.field);
            if (hasFilter) {
                menuBtn.innerHTML = '🔍';
                menuBtn.style.opacity = '1';
                menuBtn.style.color = 'var(--primary-color, #3b82f6)';
            }

            wrapper.appendChild(menuBtn);
        }

        th.appendChild(wrapper);

        // Handle לשינוי גודל
        if (this.config.resizable && col.resizable !== false) {
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'tm-resize-handle';
            th.appendChild(resizeHandle);
        }

        return th;
    }

    /**
     * ⭐ הצגת תפריט עמודה (מיון/סינון)
     */
    _showColumnMenu(colIndex, button, column) {
        // סגור תפריטים קיימים
        document.querySelectorAll('.tm-column-menu').forEach(m => m.remove());

        const rect = button.getBoundingClientRect();
        const menu = document.createElement('div');
        menu.className = 'tm-column-menu';
        menu.style.cssText = `
            position: fixed;
            top: ${rect.bottom + 5}px;
            right: ${window.innerWidth - rect.right}px;
            background: var(--bg-primary, white);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 1001;
            direction: rtl;
            overflow: hidden;
        `;

        // ⭐ אפשרויות מיון
        if (this.config.sortable && column.sortable !== false) {
            const sortAscItem = this._createMenuItem('▲ מיין עולה', () => {
                this.state.sortColumn = colIndex;
                this.state.sortOrder = 'asc';
                this._applySorting();
                menu.remove();
            });
            menu.appendChild(sortAscItem);

            const sortDescItem = this._createMenuItem('▼ מיין יורד', () => {
                this.state.sortColumn = colIndex;
                this.state.sortOrder = 'desc';
                this._applySorting();
                menu.remove();
            });
            menu.appendChild(sortDescItem);

            // מפריד
            if (this.config.filterable) {
                const divider = document.createElement('div');
                divider.style.cssText = `height: 1px; background: var(--border-color, #e5e7eb); margin: 4px 0;`;
                menu.appendChild(divider);
            }
        }

        // ⭐ אפשרויות סינון
        if (this.config.filterable) {
            const filterItem = this._createMenuItem('🔍 סינון...', () => {
                menu.remove();
                this._showFilterDialog(colIndex, column);
            });
            menu.appendChild(filterItem);

            // הצג "נקה סינון" רק אם יש פילטר פעיל
            if (this.state.filters.has(column.field)) {
                const clearFilterItem = this._createMenuItem('✕ נקה סינון', () => {
                    this.state.filters.delete(column.field);
                    this.state.currentPage = 1;
                    this.loadInitialData();
                    this.renderHeaders(); // רענן כותרות לעדכון אינדיקטור
                    menu.remove();
                });
                clearFilterItem.style.color = 'var(--danger-color, #dc2626)';
                menu.appendChild(clearFilterItem);
            }
        }

        document.body.appendChild(menu);

        // סגירה בלחיצה מחוץ לתפריט
        const closeHandler = (e) => {
            if (!menu.contains(e.target) && e.target !== button) {
                menu.remove();
                document.removeEventListener('click', closeHandler);
            }
        };
        setTimeout(() => document.addEventListener('click', closeHandler), 10);
    }

    /**
     * יצירת פריט בתפריט
     */
    _createMenuItem(text, onClick) {
        const item = document.createElement('div');
        item.className = 'tm-menu-item';
        item.textContent = text;
        item.style.cssText = `
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
            color: var(--text-primary, #1f2937);
        `;
        item.onmouseover = () => item.style.background = 'var(--bg-secondary, #f3f4f6)';
        item.onmouseout = () => item.style.background = 'transparent';
        item.onclick = onClick;
        return item;
    }

    /**
     * ⭐ החלת מיון
     */
    _applySorting() {
        const col = this.config.columns[this.state.sortColumn];
        if (!col) return;

        const field = col.field;

        this.state.filteredData.sort((a, b) => {
            let valA = a[field];
            let valB = b[field];

            if (valA == null) return this.state.sortOrder === 'asc' ? 1 : -1;
            if (valB == null) return this.state.sortOrder === 'asc' ? -1 : 1;

            // לפי סוג
            if (col.type === 'number' || typeof valA === 'number') {
                const numA = parseFloat(valA) || 0;
                const numB = parseFloat(valB) || 0;
                return this.state.sortOrder === 'asc' ? numA - numB : numB - numA;
            }

            if (col.type === 'date') {
                const dateA = new Date(valA).getTime() || 0;
                const dateB = new Date(valB).getTime() || 0;
                return this.state.sortOrder === 'asc' ? dateA - dateB : dateB - dateA;
            }

            // טקסט
            const strA = String(valA).toLowerCase();
            const strB = String(valB).toLowerCase();
            const cmp = strA.localeCompare(strB, 'he');
            return this.state.sortOrder === 'asc' ? cmp : -cmp;
        });

        this._updateSortIcons();
        this.state.currentPage = 1;
        this.loadInitialData();

        if (this.config.onSort) {
            this.config.onSort(field, this.state.sortOrder);
        }
    }

    /**
     * ⭐ דיאלוג סינון מתקדם
     */
    _showFilterDialog(colIndex, column) {
        // סגור דיאלוגים קיימים
        document.querySelectorAll('.tm-filter-dialog, .tm-filter-overlay').forEach(d => d.remove());

        const filterType = column.filterType || column.type || 'text';

        // Overlay
        const overlay = document.createElement('div');
        overlay.className = 'tm-filter-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1999;
        `;

        // דיאלוג
        const dialog = document.createElement('div');
        dialog.className = 'tm-filter-dialog';
        dialog.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg-primary, white);
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            min-width: 320px;
            max-width: 400px;
            z-index: 2000;
            direction: rtl;
        `;

        // כותרת
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            font-weight: 600;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-primary, #1f2937);
        `;
        header.innerHTML = `<span>🔍 סינון: ${column.label || column.field}</span>`;

        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '✕';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--text-muted, #6b7280);
            padding: 4px;
        `;
        closeBtn.onclick = () => { dialog.remove(); overlay.remove(); };
        header.appendChild(closeBtn);
        dialog.appendChild(header);

        // תוכן לפי סוג
        const content = document.createElement('div');
        content.style.cssText = `padding: 20px;`;

        switch (filterType) {
            case 'number':
                this._buildNumberFilterContent(content, column);
                break;
            case 'date':
                this._buildDateFilterContent(content, column);
                break;
            case 'enum':
            case 'select':
                this._buildEnumFilterContent(content, column);
                break;
            default:
                this._buildTextFilterContent(content, column);
        }

        dialog.appendChild(content);

        // כפתורי פעולה
        const actions = document.createElement('div');
        actions.style.cssText = `
            padding: 16px 20px;
            border-top: 1px solid var(--border-color, #e5e7eb);
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        `;

        const applyBtn = document.createElement('button');
        applyBtn.textContent = 'החל סינון';
        applyBtn.style.cssText = `
            padding: 10px 20px;
            background: var(--primary-color, #3b82f6);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        `;
        applyBtn.onclick = () => {
            this._applyFilterFromDialog(column, filterType, dialog);
            dialog.remove();
            overlay.remove();
        };

        const clearBtn = document.createElement('button');
        clearBtn.textContent = 'נקה';
        clearBtn.style.cssText = `
            padding: 10px 20px;
            background: var(--bg-primary, white);
            color: var(--text-primary, #374151);
            border: 1px solid var(--border-color, #d1d5db);
            border-radius: 6px;
            cursor: pointer;
        `;
        clearBtn.onclick = () => {
            this.state.filters.delete(column.field);
            this.state.currentPage = 1;
            this.loadInitialData();
            this.renderHeaders();
            dialog.remove();
            overlay.remove();
        };

        actions.appendChild(applyBtn);
        actions.appendChild(clearBtn);
        dialog.appendChild(actions);

        overlay.onclick = () => { dialog.remove(); overlay.remove(); };

        document.body.appendChild(overlay);
        document.body.appendChild(dialog);
    }

    /**
     * בניית תוכן פילטר טקסט
     */
    _buildTextFilterContent(container, column) {
        const currentFilter = this.state.filters.get(column.field) || {};

        container.innerHTML = `
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">סוג סינון:</label>
                <select class="filter-operator" style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; background: var(--bg-primary, white);">
                    <option value="contains" ${currentFilter.operator === 'contains' ? 'selected' : ''}>מכיל</option>
                    <option value="exact" ${currentFilter.operator === 'exact' ? 'selected' : ''}>ערך מדויק</option>
                    <option value="starts" ${currentFilter.operator === 'starts' ? 'selected' : ''}>מתחיל ב</option>
                    <option value="ends" ${currentFilter.operator === 'ends' ? 'selected' : ''}>מסתיים ב</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">ערך:</label>
                <input type="text" class="filter-value" value="${currentFilter.value || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; box-sizing: border-box;"
                    placeholder="הזן ערך לסינון...">
            </div>
        `;
    }

    /**
     * בניית תוכן פילטר מספרי
     */
    _buildNumberFilterContent(container, column) {
        const currentFilter = this.state.filters.get(column.field) || {};

        container.innerHTML = `
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">סוג סינון:</label>
                <select class="filter-operator" style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; background: var(--bg-primary, white);">
                    <option value="equals" ${currentFilter.operator === 'equals' ? 'selected' : ''}>שווה ל</option>
                    <option value="less" ${currentFilter.operator === 'less' ? 'selected' : ''}>קטן מ</option>
                    <option value="greater" ${currentFilter.operator === 'greater' ? 'selected' : ''}>גדול מ</option>
                    <option value="between" ${currentFilter.operator === 'between' ? 'selected' : ''}>בין ... לבין ...</option>
                </select>
            </div>
            <div class="filter-value-container">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">ערך:</label>
                <input type="number" class="filter-value" value="${currentFilter.value || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; box-sizing: border-box;">
            </div>
            <div class="filter-value2-container" style="margin-top: 15px; display: ${currentFilter.operator === 'between' ? 'block' : 'none'};">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">עד ערך:</label>
                <input type="number" class="filter-value2" value="${currentFilter.value2 || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; box-sizing: border-box;">
            </div>
        `;

        const operatorSelect = container.querySelector('.filter-operator');
        const value2Container = container.querySelector('.filter-value2-container');
        operatorSelect.onchange = () => {
            value2Container.style.display = operatorSelect.value === 'between' ? 'block' : 'none';
        };
    }

    /**
     * בניית תוכן פילטר תאריך
     */
    _buildDateFilterContent(container, column) {
        const currentFilter = this.state.filters.get(column.field) || {};

        container.innerHTML = `
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">סוג סינון:</label>
                <select class="filter-operator" style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; background: var(--bg-primary, white);">
                    <option value="exact" ${currentFilter.operator === 'exact' ? 'selected' : ''}>תאריך מדויק</option>
                    <option value="approximate" ${currentFilter.operator === 'approximate' ? 'selected' : ''}>תאריך משוער (±2.5 שנים)</option>
                    <option value="between" ${currentFilter.operator === 'between' ? 'selected' : ''}>בין תאריכים</option>
                    <option value="before" ${currentFilter.operator === 'before' ? 'selected' : ''}>לפני תאריך</option>
                    <option value="after" ${currentFilter.operator === 'after' ? 'selected' : ''}>אחרי תאריך</option>
                </select>
            </div>
            <div class="filter-value-container">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">תאריך:</label>
                <input type="date" class="filter-value" value="${currentFilter.value || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; box-sizing: border-box;">
            </div>
            <div class="filter-value2-container" style="margin-top: 15px; display: ${currentFilter.operator === 'between' ? 'block' : 'none'};">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary, #1f2937);">עד תאריך:</label>
                <input type="date" class="filter-value2" value="${currentFilter.value2 || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #d1d5db); border-radius: 6px; box-sizing: border-box;">
            </div>
        `;

        const operatorSelect = container.querySelector('.filter-operator');
        const value2Container = container.querySelector('.filter-value2-container');
        operatorSelect.onchange = () => {
            value2Container.style.display = operatorSelect.value === 'between' ? 'block' : 'none';
        };
    }

    /**
     * בניית תוכן פילטר enum (בחירה מרשימת ערכים)
     */
    _buildEnumFilterContent(container, column) {
        const currentFilter = this.state.filters.get(column.field) || { selectedValues: [] };
        const field = column.field;

        // איסוף ערכים ייחודיים
        const uniqueValues = new Set();
        this.config.data.forEach(row => {
            const val = row[field];
            if (val !== null && val !== undefined && val !== '') {
                uniqueValues.add(String(val));
            }
        });

        const sortedValues = Array.from(uniqueValues).sort((a, b) => a.localeCompare(b, 'he'));

        let checkboxesHtml = '';
        sortedValues.forEach(val => {
            const isChecked = currentFilter.selectedValues && currentFilter.selectedValues.includes(val);
            checkboxesHtml += `
                <label style="display: flex; align-items: center; gap: 8px; padding: 8px 0; cursor: pointer; border-bottom: 1px solid var(--bg-secondary, #f3f4f6);">
                    <input type="checkbox" class="enum-checkbox" value="${val}" ${isChecked ? 'checked' : ''}
                        style="width: 18px; height: 18px;">
                    <span style="color: var(--text-primary, #1f2937);">${val}</span>
                </label>
            `;
        });

        container.innerHTML = `
            <div style="margin-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding-bottom: 10px; border-bottom: 2px solid var(--border-color, #e5e7eb); font-weight: 600; color: var(--text-primary, #1f2937);">
                    <input type="checkbox" class="select-all-enum" style="width: 18px; height: 18px;">
                    <span>בחר הכל</span>
                </label>
            </div>
            <div style="max-height: 250px; overflow-y: auto;">
                ${checkboxesHtml || '<p style="color: var(--text-muted, #999); text-align: center;">אין ערכים זמינים</p>'}
            </div>
        `;

        const selectAllCheckbox = container.querySelector('.select-all-enum');
        const enumCheckboxes = container.querySelectorAll('.enum-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.onchange = () => {
                enumCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
            };
        }
    }

    /**
     * החלת פילטר מהדיאלוג
     */
    _applyFilterFromDialog(column, filterType, dialog) {
        let filterData = { type: filterType };

        if (filterType === 'enum' || filterType === 'select') {
            const checkboxes = dialog.querySelectorAll('.enum-checkbox:checked');
            filterData.selectedValues = Array.from(checkboxes).map(cb => cb.value);
            if (filterData.selectedValues.length === 0) {
                this.state.filters.delete(column.field);
                this.state.currentPage = 1;
                this.loadInitialData();
                this.renderHeaders();
                return;
            }
        } else {
            const operator = dialog.querySelector('.filter-operator')?.value;
            const value = dialog.querySelector('.filter-value')?.value;
            const value2 = dialog.querySelector('.filter-value2')?.value;

            if (!value && !value2) {
                this.state.filters.delete(column.field);
                this.state.currentPage = 1;
                this.loadInitialData();
                this.renderHeaders();
                return;
            }

            filterData.operator = operator;
            filterData.value = value;
            if (value2) filterData.value2 = value2;
        }

        this.state.filters.set(column.field, filterData);
        this.state.currentPage = 1;
        this.loadInitialData();
        this.renderHeaders(); // עדכון אינדיקטורים

        if (this.config.onFilter) {
            this.config.onFilter(column.field, filterData);
        }
    }

    /**
     * ציור שורות
     */
    renderRows(append = false) {
        // ⭐ בדיקה אם במצב כרטיסים במובייל
        if (this._isMobileDevice() && this.state.mobileViewMode === 'cards') {
            this._renderCardsView();
            return;
        }

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
        // רוחב נקבע דרך colgroup - לא צריך להגדיר כאן

        let value = rowData[col.field];
        let content = '';

        // Custom renderer
        if (this.config.renderCell) {
            content = this.config.renderCell(col, rowData, value);
        } else if (col.render) {
            // תאימות לאחור: שלח את rowData כפרמטר ראשון (כמו table-manager.js הישן)
            content = col.render(rowData);
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
     * בדיקת התאמה לפילטר - תומך בכל סוגי הפילטרים
     */
    _matchesFilter(value, filterConfig) {
        const { type, value: filterValue, value2: filterValue2, operator, selectedValues } = filterConfig;

        // פילטר enum
        if (type === 'enum' || type === 'select') {
            if (!selectedValues || selectedValues.length === 0) return true;
            return selectedValues.includes(String(value || ''));
        }

        if (filterValue === '' || filterValue === null || filterValue === undefined) return true;

        switch (type) {
            case 'text':
                return this._matchTextFilter(value, filterValue, operator);

            case 'number':
                return this._matchNumberFilter(value, filterValue, filterValue2, operator);

            case 'date':
                return this._matchDateFilter(value, filterValue, filterValue2, operator);

            default:
                // ברירת מחדל - חיפוש טקסט contains
                return String(value || '').toLowerCase().includes(String(filterValue).toLowerCase());
        }
    }

    /**
     * התאמת פילטר טקסט
     */
    _matchTextFilter(value, filterValue, operator) {
        const cellStr = String(value || '').toLowerCase();
        const filterStr = String(filterValue).toLowerCase();

        switch (operator) {
            case 'exact':
                return cellStr === filterStr;
            case 'starts':
                return cellStr.startsWith(filterStr);
            case 'ends':
                return cellStr.endsWith(filterStr);
            case 'contains':
            default:
                return cellStr.includes(filterStr);
        }
    }

    /**
     * התאמת פילטר מספרי
     */
    _matchNumberFilter(value, filterValue, filterValue2, operator) {
        const num = parseFloat(value);
        const filterNum = parseFloat(filterValue);
        const filterNum2 = parseFloat(filterValue2);

        if (isNaN(num)) return false;

        switch (operator) {
            case 'equals':
                return num === filterNum;
            case 'less':
                return num < filterNum;
            case 'greater':
                return num > filterNum;
            case 'between':
                if (isNaN(filterNum2)) return num >= filterNum;
                return num >= filterNum && num <= filterNum2;
            default:
                return num === filterNum;
        }
    }

    /**
     * התאמת פילטר תאריך
     */
    _matchDateFilter(value, filterValue, filterValue2, operator) {
        if (!value) return false;

        const cellDate = new Date(value);
        if (isNaN(cellDate.getTime())) return false;

        const filterDate = new Date(filterValue);
        const filterDate2 = filterValue2 ? new Date(filterValue2) : null;

        switch (operator) {
            case 'exact':
                return cellDate.toDateString() === filterDate.toDateString();

            case 'approximate':
                // ±2.5 שנים
                const yearsInMs = 2.5 * 365 * 24 * 60 * 60 * 1000;
                const minDate = new Date(filterDate.getTime() - yearsInMs);
                const maxDate = new Date(filterDate.getTime() + yearsInMs);
                return cellDate >= minDate && cellDate <= maxDate;

            case 'between':
                if (!filterDate2) return cellDate >= filterDate;
                return cellDate >= filterDate && cellDate <= filterDate2;

            case 'before':
                return cellDate < filterDate;

            case 'after':
                return cellDate > filterDate;

            default:
                return cellDate.toDateString() === filterDate.toDateString();
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
        let colIndex = null;

        const onMouseMove = (e) => {
            if (!isResizing) return;

            // RTL: כיוון הפוך - גרירה שמאלה מגדילה, ימינה מקטינה
            const diff = e.pageX - startX;
            const newWidth = Math.max(80, startWidth - diff);

            // עדכון state
            this.state.columnWidths[colIndex] = newWidth + 'px';

            // עדכון דרך colgroup (סנכרון אוטומטי בין header ו-body)
            const headerCol = this.elements.headerColgroup?.querySelector(`col[data-col-index="${colIndex}"]`);
            const bodyCol = this.elements.bodyColgroup?.querySelector(`col[data-col-index="${colIndex}"]`);

            if (headerCol) {
                headerCol.style.width = newWidth + 'px';
            }
            if (bodyCol) {
                bodyCol.style.width = newWidth + 'px';
            }

            // עדכון רוחב הטבלאות
            this._updateTableWidths();
        };

        const onMouseUp = () => {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);

            // ⭐ שמירת רוחב עמודות למשתמש
            this._saveColumnWidths();
        };

        this.elements.thead.addEventListener('mousedown', (e) => {
            if (!e.target.classList.contains('tm-resize-handle')) return;

            isResizing = true;
            currentTh = e.target.closest('.tm-header-cell');
            colIndex = parseInt(currentTh.dataset.colIndex);
            startX = e.pageX;
            startWidth = currentTh.offsetWidth;

            // מניעת בחירת טקסט בזמן גרירה
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }, { signal: this._abortController.signal });
    }

    /**
     * קבלת אינדקס העמודה הנראית (כולל checkbox)
     */
    _getVisibleColumnIndex(colIndex) {
        let visibleIndex = this.state.multiSelectEnabled ? 1 : 0;
        for (let i = 0; i < this.state.columnOrder.length; i++) {
            const idx = this.state.columnOrder[i];
            if (idx === colIndex) break;
            if (this.state.columnVisibility[idx]) {
                visibleIndex++;
            }
        }
        return visibleIndex;
    }

    /**
     * עדכון רוחב הטבלאות
     */
    _updateTableWidths() {
        const newWidth = this._calculateTableWidth();
        if (this.elements.headerTable) {
            this.elements.headerTable.style.width = newWidth + 'px';
            this.elements.headerTable.style.minWidth = newWidth + 'px';
        }
        if (this.elements.bodyTable) {
            this.elements.bodyTable.style.width = newWidth + 'px';
            this.elements.bodyTable.style.minWidth = newWidth + 'px';
        }
    }

    // ====================================
    // סרגל כלים (Toolbar)
    // ====================================

    /**
     * בניית סרגל כלים מעל הטבלה
     */
    _buildToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'table-toolbar';
        toolbar.style.cssText = `
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 16px !important;
            background: var(--bg-secondary, #f9fafb) !important;
            border-bottom: 1px solid var(--border-color, #e5e7eb) !important;
            flex-shrink: 0 !important;
            direction: rtl !important;
        `;

        // צד ימין - ריק לעת עתה
        const rightSide = document.createElement('div');
        rightSide.className = 'toolbar-right';

        // צד שמאל - כפתורי פעולות
        const leftSide = document.createElement('div');
        leftSide.className = 'toolbar-left';
        leftSide.style.cssText = `
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
        `;

        // כפתור הגדרות
        const settingsBtn = this._createToolbarButton('⚙️', 'הגדרות', (e) => this._toggleSettingsMenu(e));

        // כפתור יצוא לאקסל
        const excelBtn = this._createToolbarButton('📊', 'יצוא לאקסל', () => this._handleExportExcel());

        // כפתור יצוא ל-PDF
        const pdfBtn = this._createToolbarButton('📄', 'יצוא ל-PDF', () => this._handleExportPDF());

        leftSide.appendChild(settingsBtn);
        leftSide.appendChild(excelBtn);
        leftSide.appendChild(pdfBtn);

        toolbar.appendChild(rightSide);
        toolbar.appendChild(leftSide);

        this.elements.toolbar = toolbar;
        return toolbar;
    }

    /**
     * יצירת כפתור לסרגל כלים
     */
    _createToolbarButton(icon, title, onClick) {
        const wrapper = document.createElement('div');
        wrapper.style.cssText = `position: relative !important; display: inline-block !important;`;

        const btn = document.createElement('button');
        btn.className = 'toolbar-btn';
        btn.innerHTML = icon;
        btn.style.cssText = `
            padding: 8px 12px !important;
            border: 1px solid var(--border-color, #d1d5db) !important;
            border-radius: 6px !important;
            background: var(--bg-primary, white) !important;
            cursor: pointer !important;
            font-size: 16px !important;
            transition: all 0.2s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        `;

        const tooltip = document.createElement('div');
        tooltip.className = 'toolbar-tooltip';
        tooltip.textContent = title;
        tooltip.style.cssText = `
            position: absolute !important;
            bottom: -35px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            background: #1f2937 !important;
            color: white !important;
            padding: 6px 10px !important;
            border-radius: 4px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: opacity 0.15s, visibility 0.15s !important;
            z-index: 1000 !important;
            pointer-events: none !important;
        `;

        btn.onmouseover = () => {
            btn.style.background = 'var(--bg-tertiary, #e5e7eb)';
            tooltip.style.opacity = '1';
            tooltip.style.visibility = 'visible';
        };
        btn.onmouseout = () => {
            btn.style.background = 'var(--bg-primary, white)';
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        };
        btn.onclick = onClick;

        wrapper.appendChild(btn);
        wrapper.appendChild(tooltip);
        return wrapper;
    }

    /**
     * תפריט הגדרות
     */
    _toggleSettingsMenu(e) {
        e.stopPropagation();

        // סגור תפריט קיים
        const existingMenu = document.querySelector('.tm-settings-menu');
        if (existingMenu) {
            existingMenu.remove();
            return;
        }

        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();

        const menu = document.createElement('div');
        menu.className = 'tm-settings-menu';
        menu.style.cssText = `
            position: fixed;
            top: ${rect.bottom + 5}px;
            left: ${rect.left}px;
            background: var(--bg-primary, white);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 260px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 1000;
            direction: rtl;
        `;

        // כותרת
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 12px 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            background: var(--bg-secondary, #f9fafb);
            color: var(--text-primary, #1f2937);
        `;
        header.textContent = 'הגדרות טבלה';
        menu.appendChild(header);

        // ===================================================================
        // סקשן 1: מצב תצוגה (טעינה) - מוסתר במצב כרטיסים במובייל
        // ===================================================================
        const isCardsMode = this._isMobileDevice() && this.state.mobileViewMode === 'cards';

        if (!isCardsMode) {
            const displayModeSection = document.createElement('div');
            displayModeSection.style.cssText = `padding: 12px 16px; border-bottom: 1px solid var(--border-color, #e5e7eb);`;

            const displayModeTitle = document.createElement('div');
            displayModeTitle.style.cssText = `font-weight: 600; margin-bottom: 10px; color: var(--text-primary, #1f2937); font-size: 13px;`;
            displayModeTitle.textContent = '📄 מצב תצוגה';
            displayModeSection.appendChild(displayModeTitle);

        // אופציה 1: דף אחד עם גלילה
        const infiniteOption = this._createRadioOption(
            'displayMode',
            'infinite',
            'הכל בדף אחד (גלילה)',
            !this.config.showPagination,
            () => this._setDisplayMode('infinite', menu)
        );
        displayModeSection.appendChild(infiniteOption);

        // אופציה 2: עמודים
        const paginationOption = this._createRadioOption(
            'displayMode',
            'pagination',
            'חלוקה לעמודים',
            this.config.showPagination,
            () => this._setDisplayMode('pagination', menu)
        );
        displayModeSection.appendChild(paginationOption);

        // בחירת כמות לעמוד (מוצג רק במצב עמודים)
        if (this.config.showPagination) {
            const pageSizeContainer = document.createElement('div');
            pageSizeContainer.style.cssText = `margin-top: 10px; padding-right: 24px;`;

            const pageSizeLabel = document.createElement('span');
            pageSizeLabel.textContent = 'שורות בעמוד: ';
            pageSizeLabel.style.cssText = `font-size: 13px; color: var(--text-secondary, #4b5563);`;

            const pageSizeSelect = document.createElement('select');
            pageSizeSelect.style.cssText = `
                padding: 4px 8px; border: 1px solid var(--border-color, #d1d5db);
                border-radius: 4px; font-size: 13px; cursor: pointer;
            `;
            [25, 50, 100, 200, 500].forEach(num => {
                const opt = document.createElement('option');
                opt.value = num;
                opt.textContent = num;
                opt.selected = this.config.itemsPerPage === num;
                pageSizeSelect.appendChild(opt);
            });
            pageSizeSelect.onchange = () => {
                const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;
                this.config.itemsPerPage = parseInt(pageSizeSelect.value);
                this.calculateTotalPages();
                this.goToPage(1);
                // ⭐ שמירה לפי entity
                this._saveUserPreference(`${storageKey}_displayMode`, this.config.itemsPerPage);
            };

            pageSizeContainer.appendChild(pageSizeLabel);
            pageSizeContainer.appendChild(pageSizeSelect);
            displayModeSection.appendChild(pageSizeContainer);
        }

            menu.appendChild(displayModeSection);
        } // סוף if (!isCardsMode)

        // ===================================================================
        // סקשן 2: בחירה מרובה
        // ===================================================================
        const multiSelectSection = document.createElement('div');
        multiSelectSection.style.cssText = `padding: 12px 16px; border-bottom: 1px solid var(--border-color, #e5e7eb);`;

        const multiSelectLabel = document.createElement('label');
        multiSelectLabel.style.cssText = `display: flex; align-items: center; gap: 10px; cursor: pointer;`;

        const multiSelectCheckbox = document.createElement('input');
        multiSelectCheckbox.type = 'checkbox';
        multiSelectCheckbox.checked = this.state.multiSelectEnabled;
        multiSelectCheckbox.style.cssText = `width: 16px; height: 16px; cursor: pointer;`;
        multiSelectCheckbox.onchange = () => {
            this.state.multiSelectEnabled = multiSelectCheckbox.checked;
            this.state.selectedRows.clear();
            this._refreshTable();
        };

        const multiSelectText = document.createElement('span');
        multiSelectText.textContent = '☑️ בחירה מרובה';
        multiSelectText.style.cssText = `font-weight: 500; color: var(--text-primary, #1f2937); font-size: 13px;`;

        multiSelectLabel.appendChild(multiSelectCheckbox);
        multiSelectLabel.appendChild(multiSelectText);
        multiSelectSection.appendChild(multiSelectLabel);
        menu.appendChild(multiSelectSection);

        // ===================================================================
        // סקשן 3: עמודות (מתקפל)
        // ===================================================================
        const columnsSection = document.createElement('div');
        columnsSection.style.cssText = `border-bottom: 1px solid var(--border-color, #e5e7eb);`;

        // כותרת מתקפלת
        const columnsHeader = document.createElement('div');
        columnsHeader.style.cssText = `
            padding: 12px 16px; cursor: pointer; display: flex; justify-content: space-between;
            align-items: center; font-weight: 600; color: var(--text-primary, #1f2937); font-size: 13px;
        `;
        columnsHeader.innerHTML = `<span>📊 עמודות</span><span class="tm-toggle-arrow">▼</span>`;

        // תוכן מתקפל
        const columnsContent = document.createElement('div');
        columnsContent.className = 'tm-columns-content';
        columnsContent.style.cssText = `display: none; padding: 0 0 8px 0;`;

        // רשימת עמודות
        this.config.columns.forEach((col, index) => {
            const item = document.createElement('label');
            item.style.cssText = `
                display: flex; align-items: center; gap: 10px; padding: 6px 16px;
                cursor: pointer; transition: background 0.2s; color: var(--text-primary, #1f2937); font-size: 13px;
            `;
            item.onmouseover = () => item.style.background = 'var(--bg-secondary, #f3f4f6)';
            item.onmouseout = () => item.style.background = 'transparent';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = this.state.columnVisibility[index];
            checkbox.style.cssText = `width: 14px; height: 14px; cursor: pointer;`;
            checkbox.onchange = () => {
                this.state.columnVisibility[index] = checkbox.checked;
                this._refreshTable();
                this._saveColumnVisibility();
            };

            const label = document.createElement('span');
            label.textContent = col.label || col.field;

            item.appendChild(checkbox);
            item.appendChild(label);
            columnsContent.appendChild(item);
        });

        // כפתורי הצג/הסתר הכל
        const actions = document.createElement('div');
        actions.style.cssText = `padding: 8px 16px; display: flex; gap: 8px; justify-content: center;`;

        const showAllBtn = document.createElement('button');
        showAllBtn.textContent = 'הצג הכל';
        showAllBtn.style.cssText = `
            padding: 4px 10px; border: 1px solid var(--border-color, #d1d5db);
            border-radius: 4px; background: var(--bg-primary, white); cursor: pointer; font-size: 12px;
        `;
        showAllBtn.onclick = () => {
            this.config.columns.forEach((_, i) => this.state.columnVisibility[i] = true);
            this._refreshTable();
            this._saveColumnVisibility();
            columnsContent.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
        };

        const hideAllBtn = document.createElement('button');
        hideAllBtn.textContent = 'הסתר הכל';
        hideAllBtn.style.cssText = `
            padding: 4px 10px; border: 1px solid var(--border-color, #d1d5db);
            border-radius: 4px; background: var(--bg-primary, white); cursor: pointer; font-size: 12px;
        `;
        hideAllBtn.onclick = () => {
            this.config.columns.forEach((_, i) => this.state.columnVisibility[i] = false);
            this._refreshTable();
            this._saveColumnVisibility();
            columnsContent.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        };

        actions.appendChild(showAllBtn);
        actions.appendChild(hideAllBtn);
        columnsContent.appendChild(actions);

        // Toggle עמודות
        columnsHeader.onclick = () => {
            const isVisible = columnsContent.style.display !== 'none';
            columnsContent.style.display = isVisible ? 'none' : 'block';
            columnsHeader.querySelector('.tm-toggle-arrow').textContent = isVisible ? '▼' : '▲';
        };

        columnsSection.appendChild(columnsHeader);
        columnsSection.appendChild(columnsContent);
        menu.appendChild(columnsSection);

        // ===================================================================
        // סקשן 4: תצוגת מובייל (רק בפלאפון)
        // ===================================================================
        if (this._isMobileDevice()) {
            const mobileSection = document.createElement('div');
            mobileSection.style.cssText = `padding: 12px 16px; border-bottom: 1px solid var(--border-color, #e5e7eb);`;

            const mobileTitle = document.createElement('div');
            mobileTitle.style.cssText = `font-weight: 600; margin-bottom: 10px; color: var(--text-primary, #1f2937); font-size: 13px;`;
            mobileTitle.textContent = '📱 תצוגת מובייל';
            mobileSection.appendChild(mobileTitle);

            // אופציה 1: תצוגת רשימה (ברירת מחדל)
            const listOption = this._createRadioOption(
                'mobileViewMode',
                'list',
                '📋 תצוגת רשימה (טבלה)',
                this.state.mobileViewMode === 'list',
                () => this._setMobileViewMode('list', menu)
            );
            mobileSection.appendChild(listOption);

            // אופציה 2: תצוגת כרטיסים
            const cardsOption = this._createRadioOption(
                'mobileViewMode',
                'cards',
                '🃏 תצוגת כרטיסים',
                this.state.mobileViewMode === 'cards',
                () => this._setMobileViewMode('cards', menu)
            );
            mobileSection.appendChild(cardsOption);

            menu.appendChild(mobileSection);
        }

        document.body.appendChild(menu);

        // סגירה בלחיצה מחוץ לתפריט
        const closeHandler = (event) => {
            if (!menu.contains(event.target) && !btn.contains(event.target)) {
                menu.remove();
                document.removeEventListener('click', closeHandler);
            }
        };
        setTimeout(() => document.addEventListener('click', closeHandler), 0);
    }

    /**
     * יצירת אופציית רדיו
     */
    _createRadioOption(name, value, label, checked, onChange) {
        const container = document.createElement('label');
        container.style.cssText = `
            display: flex; align-items: center; gap: 8px; padding: 6px 0;
            cursor: pointer; font-size: 13px; color: var(--text-primary, #1f2937);
        `;

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = name;
        radio.value = value;
        radio.checked = checked;
        radio.style.cssText = `width: 16px; height: 16px; cursor: pointer;`;
        radio.onchange = onChange;

        const text = document.createElement('span');
        text.textContent = label;

        container.appendChild(radio);
        container.appendChild(text);
        return container;
    }

    /**
     * ⭐ שינוי מצב תצוגה מובייל (cards/list)
     */
    _setMobileViewMode(mode, menu) {
        const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;
        this.state.mobileViewMode = mode;

        // ⭐ במצב כרטיסים - כפה גלילה אינסופית (לא pagination)
        if (mode === 'cards') {
            this.config.showPagination = false;
            this.config.itemsPerPage = 999999;
            // הסר footer אם קיים
            if (this.elements.paginationFooter) {
                this.elements.paginationFooter.remove();
                this.elements.paginationFooter = null;
            }
        }

        // שמירת העדפה
        this._saveUserPreference(`${storageKey}_mobileViewMode`, mode);

        // רענון תצוגה
        this._refreshMobileView();

        // סגירת תפריט
        if (menu) menu.remove();

        // הודעה למשתמש
        if (typeof showToast === 'function') {
            const msg = mode === 'cards' ? 'מצב תצוגת כרטיסים' : 'מצב תצוגת רשימה';
            showToast(msg, 'info');
        }
    }

    /**
     * ⭐ רענון תצוגת מובייל
     */
    _refreshMobileView() {
        if (this._isMobileDevice() && this.state.mobileViewMode === 'cards') {
            // הסתר טבלה והצג כרטיסים
            if (this.elements.headerContainer) {
                this.elements.headerContainer.style.display = 'none';
            }
            this._renderCardsView();
        } else {
            // הצג טבלה
            if (this.elements.headerContainer) {
                this.elements.headerContainer.style.display = '';
            }
            this.renderRows(false);
        }
    }

    /**
     * ⭐ רינדור תצוגת כרטיסים למובייל
     */
    _renderCardsView() {
        const container = this.elements.bodyContainer;
        if (!container) return;

        // ניקוי ויצירת container לכרטיסים
        container.innerHTML = '';

        const cardsWrapper = document.createElement('div');
        cardsWrapper.className = 'tm-cards-wrapper';
        cardsWrapper.style.cssText = `
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px;
        `;

        if (this.state.displayedData.length === 0) {
            cardsWrapper.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--text-muted, #6b7280);">
                    <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                    <div>אין נתונים להצגה</div>
                </div>
            `;
        } else {
            this.state.displayedData.forEach((row, index) => {
                const card = this._createCard(row, index);
                cardsWrapper.appendChild(card);
            });
        }

        container.appendChild(cardsWrapper);

        // עדכון footer
        this._updateFooterInfo();
    }

    /**
     * ⭐ יצירת כרטיס למובייל
     */
    _createCard(rowData, rowIndex) {
        const card = document.createElement('div');
        card.className = 'tm-card';
        const rowId = rowData.id || rowData.unicId || rowIndex;
        card.setAttribute('data-row-id', rowId);

        card.style.cssText = `
            background: var(--bg-primary, white);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.2s, transform 0.2s;
        `;

        // בדיקת בחירה
        if (this.state.selectedRows.has(rowId)) {
            card.style.background = 'rgba(102, 126, 234, 0.1)';
            card.style.borderColor = 'var(--primary-color, #667eea)';
        }

        // ===================================================================
        // כותרת הכרטיס - שדה ראשי (nameField או שדה ראשון)
        // ===================================================================
        const headerField = this.config.columns.find(c => c.isPrimary || c.field === 'name' || c.field === 'firstName')
            || this.config.columns[0];

        if (headerField) {
            const headerValue = rowData[headerField.field];
            const headerDiv = document.createElement('div');
            headerDiv.className = 'tm-card-header';
            headerDiv.style.cssText = `
                font-size: 16px;
                font-weight: 600;
                color: var(--text-primary, #1f2937);
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid var(--border-color, #e5e7eb);
            `;
            headerDiv.textContent = headerValue || 'ללא שם';
            card.appendChild(headerDiv);
        }

        // ===================================================================
        // תוכן הכרטיס - שאר השדות
        // ===================================================================
        const contentDiv = document.createElement('div');
        contentDiv.className = 'tm-card-content';
        contentDiv.style.cssText = `display: flex; flex-direction: column; gap: 8px;`;

        this.state.columnOrder.forEach(colIndex => {
            if (!this.state.columnVisibility[colIndex]) return;

            const col = this.config.columns[colIndex];
            // דלג על שדה הכותרת
            if (col === headerField) return;

            const value = rowData[col.field];
            let displayValue = '';

            // Custom renderer
            if (col.render) {
                displayValue = col.render(rowData);
            } else {
                displayValue = value !== null && value !== undefined ? String(value) : '-';
            }

            const row = document.createElement('div');
            row.className = 'tm-card-row';
            row.style.cssText = `
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 14px;
            `;

            const labelSpan = document.createElement('span');
            labelSpan.className = 'tm-card-label';
            labelSpan.style.cssText = `color: var(--text-muted, #6b7280); font-weight: 500;`;
            labelSpan.textContent = col.label || col.field;

            const valueSpan = document.createElement('span');
            valueSpan.className = 'tm-card-value';
            valueSpan.style.cssText = `color: var(--text-primary, #1f2937); text-align: left; max-width: 60%;`;
            valueSpan.innerHTML = displayValue;

            row.appendChild(labelSpan);
            row.appendChild(valueSpan);
            contentDiv.appendChild(row);
        });

        card.appendChild(contentDiv);

        // ===================================================================
        // אירועים
        // ===================================================================
        card.addEventListener('click', () => {
            if (this.state.multiSelectEnabled) {
                const isSelected = this.state.selectedRows.has(rowId);
                this.toggleRowSelection(rowId, !isSelected);
                card.style.background = !isSelected ? 'rgba(102, 126, 234, 0.1)' : 'var(--bg-primary, white)';
                card.style.borderColor = !isSelected ? 'var(--primary-color, #667eea)' : 'var(--border-color, #e5e7eb)';
            }
        });

        card.addEventListener('dblclick', () => {
            if (this.config.onRowDoubleClick) {
                this.config.onRowDoubleClick(rowData, rowIndex);
            }
        });

        // Hover effect
        card.addEventListener('mouseenter', () => {
            card.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
            card.style.transform = 'translateY(-2px)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.05)';
            card.style.transform = '';
        });

        return card;
    }

    /**
     * שינוי מצב תצוגה (infinite scroll / pagination)
     */
    _setDisplayMode(mode, menu) {
        const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;

        if (mode === 'infinite') {
            // מצב גלילה אינסופית
            this.config.showPagination = false;
            this.config.itemsPerPage = 999999;

            // הסר footer אם קיים
            if (this.elements.paginationFooter) {
                this.elements.paginationFooter.remove();
                this.elements.paginationFooter = null;
            }

            // אתחל infinite scroll אם לא פעיל
            if (this.config.scrollLoadBatch > 0) {
                this.initInfiniteScroll();
            }

            // ⭐ שמור העדפה לפי entity (999999 = infinite scroll)
            this._saveUserPreference(`${storageKey}_displayMode`, 999999);

        } else if (mode === 'pagination') {
            // מצב עמודים
            this.config.showPagination = true;
            this.config.itemsPerPage = 25; // ברירת מחדל

            // בנה footer אם לא קיים
            if (!this.elements.paginationFooter) {
                this._buildPaginationFooter(this.elements.wrapper);
            }

            // ⭐ שמור העדפה לפי entity
            this._saveUserPreference(`${storageKey}_displayMode`, this.config.itemsPerPage);
        }

        // חישוב מחדש ורענון
        this.calculateTotalPages();
        this.state.hasMoreData = true;
        this.loadInitialData();

        // סגור תפריט
        if (menu) menu.remove();

        // הודעה למשתמש
        if (typeof showToast === 'function') {
            const msg = mode === 'infinite' ? 'מצב גלילה - כל הנתונים בדף אחד' : 'מצב עמודים - 25 שורות לעמוד';
            showToast(msg, 'info');
        }
    }

    /**
     * יצוא לאקסל (CSV)
     */
    _handleExportExcel() {
        const data = this.getFilteredData();
        const columns = this.config.columns.filter((_, i) => this.state.columnVisibility[i]);

        // יצירת CSV
        let csv = '\ufeff'; // BOM for Hebrew support

        // כותרות
        csv += columns.map(col => `"${col.label || col.field}"`).join(',') + '\n';

        // נתונים
        data.forEach(row => {
            csv += columns.map(col => {
                let val = row[col.field] || '';
                val = String(val).replace(/"/g, '""');
                return `"${val}"`;
            }).join(',') + '\n';
        });

        // הורדה
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `export_${new Date().toISOString().slice(0,10)}.csv`;
        link.click();

        if (typeof showToast === 'function') {
            showToast('הקובץ יוצא בהצלחה', 'success');
        }
    }

    /**
     * יצוא ל-PDF (דרך הדפסה)
     */
    _handleExportPDF() {
        if (typeof showToast === 'function') {
            showToast('בחר "שמור כ-PDF" בחלון ההדפסה', 'info');
        }
        window.print();
    }

    /**
     * רענון הטבלה
     */
    _refreshTable() {
        this._syncColumnWidths();
        this.renderHeaders();
        this.renderRows(false);
        this._updateTableWidths();
    }

    /**
     * מיון לפי עמודה (נקרא מלחיצה על הכותרת)
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

        this._applySorting();
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
                    ${this.config.paginationOptions.map(opt => {
                        const optValue = opt === 'all' ? 999999 : opt;
                        const isSelected = optValue === this.config.itemsPerPage;
                        return `<option value="${optValue}" ${isSelected ? 'selected' : ''}>
                            ${opt === 'all' ? 'הכל' : opt}
                        </option>`;
                    }).join('')}
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
            const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;
            this.config.itemsPerPage = parseInt(e.target.value);
            this.calculateTotalPages();
            this.goToPage(1);

            // ⭐ שמירת העדפה לפי entity
            this._saveUserPreference(`${storageKey}_displayMode`, this.config.itemsPerPage);
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

    /**
     * ⭐ שמירת רוחב עמודות למשתמש
     */
    async _saveColumnWidths() {
        if (!this.config.userPreferences.enabled) return;

        const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;

        // בניית אובייקט רוחב עמודות לפי שם שדה
        const widthsByField = {};
        this.config.columns.forEach((col, index) => {
            const fieldName = col.field || `col_${index}`;
            widthsByField[fieldName] = this.state.columnWidths[index];
        });

        await this._saveUserPreference(`${storageKey}_columnWidths`, JSON.stringify(widthsByField));
        console.log('TableManager: Column widths saved for', storageKey);
    }

    /**
     * ⭐ שמירת נראות עמודות למשתמש
     */
    async _saveColumnVisibility() {
        if (!this.config.userPreferences.enabled) return;

        const storageKey = this.config.userPreferences.storageKey || `table_${this.config.entityType}`;

        // בניית אובייקט נראות עמודות לפי שם שדה
        const visibilityByField = {};
        this.config.columns.forEach((col, index) => {
            const fieldName = col.field || `col_${index}`;
            visibilityByField[fieldName] = this.state.columnVisibility[index];
        });

        await this._saveUserPreference(`${storageKey}_columnVisibility`, JSON.stringify(visibilityByField));
        console.log('TableManager: Column visibility saved for', storageKey);
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
