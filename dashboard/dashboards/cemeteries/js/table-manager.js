/*
 * File: dashboards/dashboard/cemeteries/assets/js/table-manager.js
 * Version: 2.1.0
 * Updated: 2025-11-12
 * Author: Malkiel
 * Change Summary:
 * - v2.1.0: הבהרת פרמטרים - הפרדה ברורה בין תפקידים
 *   - totalItems: סה"כ רשומות במערכת (מה-API)
 *   - scrollLoadBatch: כמה שורות להוסיף בכל גלילה (client-side)
 *   - itemsPerPage: כמה שורות בעמוד (pagination) - לא קשור ל-API!
 *   - הערה: UniversalSearch משתמש ב-apiLimit לקביעת כמות מהשרת
 * - v2.0.1: תיקון קריטי - חישוב אוטומטי של totalItems
 *   - אם totalItems=null/0 → מחושב מ-data.length
 *   - תיקון: "100/0" → "100/20483" (תצוגה נכונה)
 *   - ברירת מחדל: Infinite Scroll (showPagination: false)
 *   - אם showPagination: true → itemsPerPage ברירת מחדל 200
 *   - סלקט pagination: [25, 50, 100, 200, 500, "הכל"]
 *   - הפרדה מלאה בין showPagination ל-itemsPerPage
 * - v2.0.0: הפרדה מלאה בין totalItems, scrollLoadBatch, itemsPerPage
 *   - תמיכה במצב hybrid: scroll + pagination
 *   - footer אוטומטי עם כפתורי ניווט
 */

/**
 * TableManager - מערכת טבלאות מתקדמת
 * תכונות: מיון, שינוי גודל, שינוי סדר, תפריט עמודה, סינון
 * תמיכה ב-Infinite Scroll + Pagination מלא
 */
class TableManager {
    constructor(config) {
        this.config = {
            tableSelector: null,
            columns: [],
            data: [],

            // ============================================
            // ⭐ הגדרות תצוגה
            // ============================================
            containerWidth: '100%',
            containerPadding: '0',
            tableHeight: 'calc(100vh - 250px)',  // ⭐ חדש! ברירת מחדל דינמית
            tableMinHeight: '500px',              // ⭐ חדש! גובה מינימלי
            
            // ============================================
            // ⭐ 3 פרמטרים נפרדים - ארכיטקטורה חדשה
            // ============================================
            
            // 1️⃣ סה"כ רשומות במערכת (מה-API)
            totalItems: null,           // אם null → data.length
            
            // 2️⃣ כמות שורות להוסיף בגלילה (client-side rendering)
            //    זה קובע כמה שורות TableManager יוסיף לטבלה בכל גלילה
            //    לדוגמה: יש 1000 רשומות בזיכרון, scrollLoadBatch=100 → יציג 100 בכל גלילה
            scrollLoadBatch: 100,       // 0 = ללא infinite scroll
            scrollThreshold: 100,       // מרחק מתחתית להתחלת טעינה (px)
            
            // 3️⃣ כמות רשומות לעמוד (pagination UI)
            //    זה קובע כמה שורות בעמוד אם יש pagination
            //    ⚠️ לא קשור ל-API! זה רק לתצוגה
            itemsPerPage: 999999,       // 999999 = עמוד אחד, אחרת מחולק לעמודים
            showPagination: false,      // ⭐ ברירת מחדל: Infinite Scroll (false)
            paginationOptions: [25, 50, 100, 200, 500, 'all'],  // ⭐ אפשרויות בסלקט + "הכל"
            
            // 📝 הערה חשובה:
            //    UniversalSearch משתמש ב-apiLimit לקביעת כמות רשומות לטעון מהשרת
            //    TableManager משתמש ב-scrollLoadBatch לקביעת כמות שורות להציג בכל גלילה
            //    אלה 2 דברים שונים לחלוטין!
            
            // ============================================
            // הגדרות כלליות
            // ============================================

            sortable: true,
            resizable: true,
            reorderable: true,
            filterable: true,
            multiSelect: false,         // ⭐ בחירה מרובה של שורות
            renderCell: null,
            onSort: null,
            onRowDoubleClick: null,
            onFilter: null,
            onColumnReorder: null,
            onLoadMore: null,           // ⭐ callback לטעינת עוד נתונים מ-API
            onPageChange: null,         // ⭐ callback לשינוי עמוד
            onSelectionChange: null,    // ⭐ callback לשינוי בחירה מרובה

            ...config
        };
        
        // ⭐ אם לא קיבלנו totalItems, השתמש ב-data.length
        if (this.config.totalItems === null) {
            this.config.totalItems = this.config.data.length;
        }
        
        // ⭐ אם itemsPerPage < 999999, הפעל pagination אוטומטית
        if (this.config.itemsPerPage < 999999) {
            this.config.showPagination = true;
        }
        
        this.state = {
            sortColumn: null,
            sortOrder: 'asc',
            columnWidths: {},
            columnOrder: [],
            filters: new Map(),
            isResizing: false,
            isDragging: false,
            
            // ⭐ מצב pagination
            currentPage: 1,
            totalPages: 1,
            
            // ⭐ מצב scroll loading
            isLoading: false,
            hasMoreData: true,

            // ⭐ בחירה מרובה
            multiSelectEnabled: false,  // האם מופעל כרגע
            selectedRows: new Set(),    // שורות נבחרות (לפי מזהה)

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
            paginationFooter: null
        };
        
        this.init();
    }
    
    /**
     * אתחול
     */
    init() {
        this.elements.table = document.querySelector(this.config.tableSelector);
        
        if (!this.elements.table) {
            console.error('❌ Table not found:', this.config.tableSelector);
            return;
        }
        
        // ⭐ תיקון v2.0.1: חישוב אוטומטי של totalItems
        if (this.config.totalItems === null || this.config.totalItems === undefined || this.config.totalItems === 0) {
            this.config.totalItems = this.config.data.length;
        }
        
        // אתחול סדר עמודות
        this.state.columnOrder = this.config.columns.map((col, index) => index);

        // אתחול רוחב עמודות
        this.config.columns.forEach((col, index) => {
            this.state.columnWidths[index] = col.width || 'auto';
        });

        // ⭐ אתחול נראות עמודות (visible: true בברירת מחדל)
        this.state.columnVisibility = {};
        this.config.columns.forEach((col, index) => {
            this.state.columnVisibility[index] = col.visible !== false; // ברירת מחדל: מוצג
        });

        // ⭐ אתחול בחירה מרובה מהקונפיג
        this.state.multiSelectEnabled = this.config.multiSelect || false;

        // חישוב עמודים
        this.calculateTotalPages();
        
        // בניית הטבלה
        this.buildTable();
        
        // קישור אירועים
        this.bindEvents();
        
        // אתחול Infinite Scroll (אם מופעל)
        if (this.config.scrollLoadBatch > 0) {
            this.initInfiniteScroll();
        }
        
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
        let currentParent = parent;
        let fixed = [];
        
        while (currentParent && currentParent !== document.body) {
            const styles = window.getComputedStyle(currentParent);
            
            if (currentParent.classList.contains('table-container')) {
                currentParent.setAttribute('style', `
                    width: ${this.config.containerWidth} !important;
                    padding: ${this.config.containerPadding} !important;
                    margin: 0 !important;
                    overflow: hidden !important;
                    max-height: none !important;
                    height: auto !important;
                    box-sizing: border-box !important;
                    background: transparent !important;
                    border: none !important;
                `.replace(/\s+/g, ' ').trim());
                fixed.push('table-container');
                break; // ⭐ לא להמשיך לעדכן הורים - רק את table-container
            }
            
            currentParent = currentParent.parentElement;
        }
        
        if (fixed.length > 0) {
        }

        // ⭐ קונטיינר חיצוני קבוע - לוקח את רוחב ההורה בפיקסלים ולא משתנה!
        const fixedContainer = document.createElement('div');
        fixedContainer.className = 'table-fixed-container';
        const parentWidth = parent.offsetWidth;
        fixedContainer.style.cssText = `
            width: ${parentWidth}px !important;
            max-width: ${parentWidth}px !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        `;

        // צור wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        wrapper.setAttribute('data-table-manager', 'v2.1.0');
        wrapper.setAttribute('data-fixed-width', 'true');
        wrapper.setAttribute('style', `
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
            height: ${this.config.tableHeight} !important;
            min-height: ${this.config.tableMinHeight} !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            background: white !important;
            position: relative !important;
            box-sizing: border-box !important;
        `.replace(/\s+/g, ' ').trim());

        // קונטיינר כותרת
        const headerContainer = document.createElement('div');
        headerContainer.className = 'table-header-container';
        headerContainer.style.cssText = `
            flex-shrink: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
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
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
            position: relative !important;
            height: 100% !important;
        `;
        
        // טבלת כותרת
        const headerTable = document.createElement('table');
        headerTable.className = 'tm-table tm-header-table';
        headerTable.id = 'headerTable';

        // ⭐ חישוב רוחב טבלה התחלתי מסכום רוחבי העמודות (רק מוצגות)
        let initialWidth = 0;

        // ⭐ עמודת בחירה מרובה
        if (this.state.multiSelectEnabled) {
            initialWidth += 50;
        }

        this.config.columns.forEach((col, index) => {
            // ⭐ דלג על עמודות מוסתרות
            if (!this.state.columnVisibility[index]) return;

            const w = this.state.columnWidths[index];
            if (typeof w === 'string' && w.endsWith('px')) {
                initialWidth += parseInt(w);
            } else if (typeof w === 'number') {
                initialWidth += w;
            } else {
                initialWidth += 100; // ברירת מחדל לעמודות auto
            }
        });

        headerTable.style.cssText = `
            width: ${initialWidth}px !important;
            min-width: ${initialWidth}px !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: white !important;
            table-layout: fixed !important;
        `;
        const thead = document.createElement('thead');
        headerTable.appendChild(thead);
        headerContainer.appendChild(headerTable);

        // טבלת תוכן
        const bodyTable = document.createElement('table');
        bodyTable.className = 'tm-table tm-body-table';
        bodyTable.id = 'bodyTable';
        bodyTable.style.cssText = `
            width: ${initialWidth}px !important;
            min-width: ${initialWidth}px !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: white !important;
            table-layout: fixed !important;
        `;
        const tbody = document.createElement('tbody');
        bodyTable.appendChild(tbody);
        bodyContainer.appendChild(bodyTable);

        // ⭐ סרגל כלים מעל הטבלה
        const toolbar = this.buildToolbar();

        // הרכבה
        wrapper.appendChild(toolbar);
        wrapper.appendChild(headerContainer);
        wrapper.appendChild(bodyContainer);

        // ⭐ הוסף pagination footer אם מופעל
        if (this.config.showPagination) {
            const paginationFooter = this.buildPaginationFooter();
            wrapper.appendChild(paginationFooter);
            this.elements.paginationFooter = paginationFooter;
        }

        // ⭐ הכנס את ה-wrapper לתוך ה-fixedContainer
        fixedContainer.appendChild(wrapper);

        // החלף את הטבלה המקורית
        parent.insertBefore(fixedContainer, this.elements.table);
        this.elements.table.style.display = 'none';

        // שמור references
        this.elements.fixedContainer = fixedContainer;
        this.elements.wrapper = wrapper;
        this.elements.headerContainer = headerContainer;
        this.elements.bodyContainer = bodyContainer;
        this.elements.headerTable = headerTable;
        this.elements.bodyTable = bodyTable;
        this.elements.thead = thead;
        this.elements.tbody = tbody;
        
        // סנכרן גלילה אופקית
        this.syncHorizontalScroll();

        // רינדור כותרות
        this.renderHeaders();

        // ⭐ קבע רוחב טבלה התחלתי לפי סכום העמודות
        this.updateTableWidth();

        // טען נתונים ראשוניים
        this.loadInitialData();
        
    }
    
    /**
     * בניית Pagination Footer
     */
    buildPaginationFooter() {
        const footer = document.createElement('div');
        footer.className = 'table-pagination-footer';
        footer.style.cssText = `
            flex-shrink: 0 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 12px 20px !important;
            background: #f9fafb !important;
            border-top: 1px solid #e5e7eb !important;
            direction: rtl !important;
        `;
        
        // ימין: מידע על רשומות
        const infoDiv = document.createElement('div');
        infoDiv.className = 'pagination-info';
        infoDiv.style.cssText = `
            font-size: 14px !important;
            color: #6b7280 !important;
        `;
        infoDiv.innerHTML = this.getPaginationInfoText();
        
        // שמאל: כפתורי ניווט
        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'pagination-controls';
        controlsDiv.style.cssText = `
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        `;
        
        // כפתור עמוד ראשון
        const btnFirst = this.createPaginationButton('⏮', 'עמוד ראשון', () => this.goToPage(1));
        
        // כפתור עמוד קודם
        const btnPrev = this.createPaginationButton('◀', 'עמוד קודם', () => this.goToPage(this.state.currentPage - 1));
        
        // סלקט בחירת עמוד
        const pageSelect = document.createElement('select');
        pageSelect.className = 'page-selector';
        pageSelect.style.cssText = `
            padding: 6px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            background: white !important;
            cursor: pointer !important;
            font-size: 14px !important;
        `;
        for (let i = 1; i <= this.state.totalPages; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = `עמוד ${i}`;
            if (i === this.state.currentPage) option.selected = true;
            pageSelect.appendChild(option);
        }
        pageSelect.onchange = (e) => this.goToPage(parseInt(e.target.value));
        
        // כפתור עמוד הבא
        const btnNext = this.createPaginationButton('▶', 'עמוד הבא', () => this.goToPage(this.state.currentPage + 1));
        
        // כפתור עמוד אחרון
        const btnLast = this.createPaginationButton('⏭', 'עמוד אחרון', () => this.goToPage(this.state.totalPages));
        
        // סלקט כמות רשומות לעמוד
        const perPageSelect = document.createElement('select');
        perPageSelect.className = 'per-page-selector';
        perPageSelect.style.cssText = `
            padding: 6px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            background: white !important;
            cursor: pointer !important;
            font-size: 14px !important;
            margin-right: 12px !important;
        `;
        this.config.paginationOptions.forEach(num => {
            const option = document.createElement('option');
            option.value = num === 'all' ? 999999 : num;
            option.textContent = num === 'all' ? 'הכל' : `${num} רשומות`;
            if ((num === 'all' && this.config.itemsPerPage >= 999999) || num === this.config.itemsPerPage) {
                option.selected = true;
            }
            perPageSelect.appendChild(option);
        });
        perPageSelect.onchange = (e) => this.changeItemsPerPage(parseInt(e.target.value));
        
        // הרכבה
        controlsDiv.appendChild(btnFirst);
        controlsDiv.appendChild(btnPrev);
        controlsDiv.appendChild(pageSelect);
        controlsDiv.appendChild(btnNext);
        controlsDiv.appendChild(btnLast);
        controlsDiv.appendChild(perPageSelect);
        
        footer.appendChild(infoDiv);
        footer.appendChild(controlsDiv);

        return footer;
    }

    /**
     * ⭐ בניית סרגל כלים מעל הטבלה
     */
    buildToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'table-toolbar';
        toolbar.style.cssText = `
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 16px !important;
            background: #f9fafb !important;
            border-bottom: 1px solid #e5e7eb !important;
            flex-shrink: 0 !important;
            direction: rtl !important;
        `;

        // צד ימין - כפתור ניקוי מסננים (מוצג רק כשיש מסננים פעילים)
        const rightSide = document.createElement('div');
        rightSide.className = 'toolbar-right';
        rightSide.style.cssText = `
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
        `;

        // כפתור ניקוי כל המסננים
        const clearFiltersBtn = document.createElement('button');
        clearFiltersBtn.className = 'clear-filters-btn';
        clearFiltersBtn.innerHTML = '✕ נקה מסננים';
        clearFiltersBtn.style.cssText = `
            display: none !important;
            padding: 6px 14px !important;
            background: #fef2f2 !important;
            color: #dc2626 !important;
            border: 1px solid #fecaca !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
        `;
        clearFiltersBtn.onmouseover = () => {
            clearFiltersBtn.style.background = '#fee2e2';
            clearFiltersBtn.style.borderColor = '#f87171';
        };
        clearFiltersBtn.onmouseout = () => {
            clearFiltersBtn.style.background = '#fef2f2';
            clearFiltersBtn.style.borderColor = '#fecaca';
        };
        clearFiltersBtn.onclick = () => this.clearAllFilters();

        rightSide.appendChild(clearFiltersBtn);
        this.elements.clearFiltersBtn = clearFiltersBtn;

        // צד שמאל - כפתורי פעולות
        const leftSide = document.createElement('div');
        leftSide.className = 'toolbar-left';
        leftSide.style.cssText = `
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
        `;

        // כפתור בחירת עמודות
        const columnsBtn = this.createToolbarButton('⚙️', 'הצג/הסתר עמודות', (e) => this.toggleColumnsMenu(e));

        // כפתור הדפסה
        const printBtn = this.createToolbarButton('🖨️', 'הדפסה', () => this.handlePrint());

        // כפתור יצוא לאקסל
        const excelBtn = this.createToolbarButton('📊', 'יצוא לאקסל', () => this.handleExportExcel());

        // כפתור יצוא ל-PDF
        const pdfBtn = this.createToolbarButton('📄', 'יצוא ל-PDF', () => this.handleExportPDF());

        leftSide.appendChild(columnsBtn);
        leftSide.appendChild(printBtn);
        leftSide.appendChild(excelBtn);
        leftSide.appendChild(pdfBtn);

        toolbar.appendChild(rightSide);
        toolbar.appendChild(leftSide);

        this.elements.toolbar = toolbar;

        return toolbar;
    }

    /**
     * יצירת כפתור לסרגל כלים עם tooltip מעוצב
     */
    createToolbarButton(icon, title, onClick) {
        // wrapper לכפתור + tooltip
        const wrapper = document.createElement('div');
        wrapper.style.cssText = `
            position: relative !important;
            display: inline-block !important;
        `;

        const btn = document.createElement('button');
        btn.className = 'toolbar-btn';
        btn.innerHTML = icon;
        btn.style.cssText = `
            padding: 8px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            background: white !important;
            cursor: pointer !important;
            font-size: 16px !important;
            transition: all 0.2s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        `;

        // tooltip מעוצב
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
            btn.style.background = '#e5e7eb';
            btn.style.borderColor = '#9ca3af';
            tooltip.style.opacity = '1';
            tooltip.style.visibility = 'visible';
        };
        btn.onmouseout = () => {
            btn.style.background = 'white';
            btn.style.borderColor = '#d1d5db';
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        };
        btn.onclick = onClick;

        wrapper.appendChild(btn);
        wrapper.appendChild(tooltip);

        return wrapper;
    }

    /**
     * טיפול בהדפסה
     */
    handlePrint() {
        window.print();
    }

    /**
     * טיפול ביצוא לאקסל
     */
    handleExportExcel() {
        const data = this.getFilteredData();
        const columns = this.config.columns;

        // יצירת CSV
        let csv = '\ufeff'; // BOM for Hebrew support

        // כותרות
        csv += columns.map(col => `"${col.label}"`).join(',') + '\n';

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
     * טיפול ביצוא ל-PDF
     */
    handleExportPDF() {
        // לעת עתה - הדפסה כ-PDF
        if (typeof showToast === 'function') {
            showToast('בחר "שמור כ-PDF" בחלון ההדפסה', 'info');
        }
        window.print();
    }

    /**
     * ⭐ פתיחת/סגירת תפריט עמודות
     */
    toggleColumnsMenu(e) {
        e.stopPropagation();

        // סגור תפריט קיים
        const existingMenu = document.querySelector('.columns-visibility-menu');
        if (existingMenu) {
            existingMenu.remove();
            return;
        }

        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();

        const menu = document.createElement('div');
        menu.className = 'columns-visibility-menu';
        menu.style.cssText = `
            position: fixed;
            top: ${rect.bottom + 5}px;
            left: ${rect.left}px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            direction: rtl;
        `;

        // כותרת
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 12px 16px;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        `;
        header.textContent = 'הגדרות טבלה';
        menu.appendChild(header);

        // ⭐ אפשרות בחירה מרובה
        const multiSelectSection = document.createElement('div');
        multiSelectSection.style.cssText = `
            padding: 8px 16px;
            border-bottom: 1px solid #e5e7eb;
        `;

        const multiSelectLabel = document.createElement('label');
        multiSelectLabel.style.cssText = `
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        `;

        const multiSelectCheckbox = document.createElement('input');
        multiSelectCheckbox.type = 'checkbox';
        multiSelectCheckbox.checked = this.state.multiSelectEnabled;
        multiSelectCheckbox.style.cssText = `
            width: 16px;
            height: 16px;
            cursor: pointer;
        `;
        multiSelectCheckbox.onchange = () => {
            this.state.multiSelectEnabled = multiSelectCheckbox.checked;
            this.state.selectedRows.clear(); // נקה בחירות קודמות
            this.refreshTable();
        };

        const multiSelectText = document.createElement('span');
        multiSelectText.textContent = 'בחירה מרובה';
        multiSelectText.style.fontWeight = '500';

        multiSelectLabel.appendChild(multiSelectCheckbox);
        multiSelectLabel.appendChild(multiSelectText);
        multiSelectSection.appendChild(multiSelectLabel);
        menu.appendChild(multiSelectSection);

        // כותרת משנה לעמודות
        const columnsHeader = document.createElement('div');
        columnsHeader.style.cssText = `
            padding: 8px 16px 4px;
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        `;
        columnsHeader.textContent = 'עמודות';
        menu.appendChild(columnsHeader);

        // רשימת עמודות
        const list = document.createElement('div');
        list.style.cssText = `padding: 0 0 8px 0;`;

        this.config.columns.forEach((col, index) => {
            const item = document.createElement('label');
            item.style.cssText = `
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 16px;
                cursor: pointer;
                transition: background 0.2s;
            `;
            item.onmouseover = () => item.style.background = '#f3f4f6';
            item.onmouseout = () => item.style.background = 'transparent';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = this.state.columnVisibility[index];
            checkbox.style.cssText = `
                width: 16px;
                height: 16px;
                cursor: pointer;
            `;
            checkbox.onchange = () => {
                this.state.columnVisibility[index] = checkbox.checked;
                this.refreshTable();
            };

            const label = document.createElement('span');
            label.textContent = col.label;

            item.appendChild(checkbox);
            item.appendChild(label);
            list.appendChild(item);
        });

        menu.appendChild(list);

        // כפתורי פעולה
        const actions = document.createElement('div');
        actions.style.cssText = `
            padding: 8px 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 8px;
            justify-content: space-between;
        `;

        const showAllBtn = document.createElement('button');
        showAllBtn.textContent = 'הצג הכל';
        showAllBtn.style.cssText = `
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
        `;
        showAllBtn.onclick = () => {
            this.config.columns.forEach((_, index) => {
                this.state.columnVisibility[index] = true;
            });
            menu.remove();
            this.refreshTable();
        };

        const hideAllBtn = document.createElement('button');
        hideAllBtn.textContent = 'הסתר הכל';
        hideAllBtn.style.cssText = `
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
        `;
        hideAllBtn.onclick = () => {
            this.config.columns.forEach((_, index) => {
                this.state.columnVisibility[index] = false;
            });
            menu.remove();
            this.refreshTable();
        };

        actions.appendChild(showAllBtn);
        actions.appendChild(hideAllBtn);
        menu.appendChild(actions);

        document.body.appendChild(menu);

        // סגירה בלחיצה מחוץ לתפריט
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
     * ⭐ רענון הטבלה (לאחר שינוי נראות עמודות)
     */
    refreshTable() {
        this.updateTableWidth();
        this.renderHeaders();
        this.syncColumnWidths();
        this.renderRows();
    }

    // ============================================
    // ⭐ פונקציות בחירה מרובה
    // ============================================

    /**
     * בדיקה אם כל השורות נבחרו
     */
    isAllSelected() {
        if (this.state.displayedData.length === 0) return false;
        return this.state.displayedData.every(rowData => {
            const rowId = rowData.id || rowData.unicId || rowData._id || JSON.stringify(rowData);
            return this.state.selectedRows.has(rowId);
        });
    }

    /**
     * בחירת/ביטול כל השורות
     */
    toggleSelectAll(checked) {
        if (checked) {
            this.state.displayedData.forEach(rowData => {
                const rowId = rowData.id || rowData.unicId || rowData._id || JSON.stringify(rowData);
                this.state.selectedRows.add(rowId);
            });
        } else {
            this.state.selectedRows.clear();
        }
        this.renderRows();
        this.updateSelectAllCheckbox();
    }

    /**
     * בחירת/ביטול שורה בודדת
     */
    toggleRowSelection(rowId, checked, rowData) {
        if (checked) {
            this.state.selectedRows.add(rowId);
        } else {
            this.state.selectedRows.delete(rowId);
        }
        this.updateSelectAllCheckbox();

        // callback אם הוגדר
        if (this.config.onSelectionChange) {
            this.config.onSelectionChange(this.getSelectedRows());
        }
    }

    /**
     * עדכון checkbox של "בחר הכל"
     */
    updateSelectAllCheckbox() {
        const selectAllCheckbox = this.elements.thead.querySelector('.tm-select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = this.isAllSelected();
        }
    }

    /**
     * קבלת רשימת השורות הנבחרות
     */
    getSelectedRows() {
        return this.state.displayedData.filter(rowData => {
            const rowId = rowData.id || rowData.unicId || rowData._id || JSON.stringify(rowData);
            return this.state.selectedRows.has(rowId);
        });
    }

    /**
     * קבלת מזהי השורות הנבחרות
     */
    getSelectedRowIds() {
        return Array.from(this.state.selectedRows);
    }

    /**
     * ניקוי כל הבחירות
     */
    clearSelection() {
        this.state.selectedRows.clear();
        this.renderRows();
        this.updateSelectAllCheckbox();
    }

    /**
     * יצירת כפתור pagination
     */
    createPaginationButton(text, title, onClick) {
        const btn = document.createElement('button');
        btn.className = 'pagination-btn';
        btn.textContent = text;
        btn.title = title;
        btn.style.cssText = `
            padding: 6px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            background: white !important;
            cursor: pointer !important;
            font-size: 14px !important;
            transition: all 0.2s !important;
        `;
        btn.onmouseover = () => btn.style.background = '#f3f4f6';
        btn.onmouseout = () => btn.style.background = 'white';
        btn.onclick = onClick;
        return btn;
    }
    
    /**
     * טקסט מידע על רשומות
     */
    getPaginationInfoText() {
        const start = ((this.state.currentPage - 1) * this.config.itemsPerPage) + 1;
        const end = Math.min(this.state.currentPage * this.config.itemsPerPage, this.config.totalItems);
        return `מציג <strong>${start}-${end}</strong> מתוך <strong>${this.config.totalItems}</strong>`;
    }
    
    /**
     * עדכון footer
     */
    updatePaginationFooter() {
        if (!this.elements.paginationFooter) return;
        
        const infoDiv = this.elements.paginationFooter.querySelector('.pagination-info');
        if (infoDiv) {
            infoDiv.innerHTML = this.getPaginationInfoText();
        }
        
        const pageSelect = this.elements.paginationFooter.querySelector('.page-selector');
        if (pageSelect) {
            pageSelect.value = this.state.currentPage;
        }
        
        // עדכן מצב כפתורים
        const btnFirst = this.elements.paginationFooter.querySelectorAll('.pagination-btn')[0];
        const btnPrev = this.elements.paginationFooter.querySelectorAll('.pagination-btn')[1];
        const btnNext = this.elements.paginationFooter.querySelectorAll('.pagination-btn')[2];
        const btnLast = this.elements.paginationFooter.querySelectorAll('.pagination-btn')[3];
        
        if (btnFirst) btnFirst.disabled = this.state.currentPage === 1;
        if (btnPrev) btnPrev.disabled = this.state.currentPage === 1;
        if (btnNext) btnNext.disabled = this.state.currentPage === this.state.totalPages;
        if (btnLast) btnLast.disabled = this.state.currentPage === this.state.totalPages;
    }
    
    /**
     * מעבר לעמוד
     */
    goToPage(pageNum) {
        if (pageNum < 1 || pageNum > this.state.totalPages) return;
        
        this.state.currentPage = pageNum;
        
        // callback
        if (this.config.onPageChange) {
            this.config.onPageChange(pageNum);
        }
        
        this.loadCurrentPageData();
        this.updatePaginationFooter();
    }
    
    /**
     * שינוי כמות רשומות לעמוד
     */
    changeItemsPerPage(newAmount) {
        
        this.config.itemsPerPage = newAmount;
        this.state.currentPage = 1;
        
        // חשב מחדש עמודים
        this.calculateTotalPages();
        
        // בנה מחדש את הסלקט של העמודים
        const pageSelect = this.elements.paginationFooter.querySelector('.page-selector');
        if (pageSelect) {
            pageSelect.innerHTML = '';
            for (let i = 1; i <= this.state.totalPages; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = `עמוד ${i}`;
                if (i === 1) option.selected = true;
                pageSelect.appendChild(option);
            }
        }
        
        this.loadCurrentPageData();
        this.updatePaginationFooter();
    }
    
    /**
     * סנכרון גלילה אופקית
     */
    syncHorizontalScroll() {
        this.elements.headerContainer.addEventListener('scroll', () => {
            this.elements.bodyContainer.scrollLeft = this.elements.headerContainer.scrollLeft;
        });
        
        this.elements.bodyContainer.addEventListener('scroll', () => {
            this.elements.headerContainer.scrollLeft = this.elements.bodyContainer.scrollLeft;
        });
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
        
        // טען עמוד נוכחי
        this.loadCurrentPageData();
    }
    
    /**
     * טעינת נתונים של העמוד הנוכחי
     */
    loadCurrentPageData() {
        if (this.config.itemsPerPage >= 999999) {
            // מצב infinite scroll - טען לפי scrollLoadBatch
            this.state.displayedData = this.state.filteredData.slice(0, this.config.scrollLoadBatch);
        } else {
            // מצב pagination - טען עמוד מלא
            const start = (this.state.currentPage - 1) * this.config.itemsPerPage;
            const end = start + this.config.itemsPerPage;
            this.state.displayedData = this.state.filteredData.slice(start, end);
        }
        
        // רינדור
        this.renderRows();
    }
    
    /**
     * רינדור כותרות
     */
    renderHeaders() {
        const headerRow = document.createElement('tr');
        headerRow.className = 'tm-header-row';

        // ⭐ עמודת בחירה מרובה
        if (this.state.multiSelectEnabled) {
            const selectAllTh = document.createElement('th');
            selectAllTh.className = 'tm-header-cell tm-select-cell';
            selectAllTh.style.cssText = `
                width: 50px !important;
                min-width: 50px !important;
                max-width: 50px !important;
                text-align: center !important;
            `;

            const selectAllCheckbox = document.createElement('input');
            selectAllCheckbox.type = 'checkbox';
            selectAllCheckbox.className = 'tm-select-all';
            selectAllCheckbox.style.cssText = `
                width: 18px;
                height: 18px;
                cursor: pointer;
            `;
            selectAllCheckbox.checked = this.isAllSelected();
            selectAllCheckbox.onchange = () => this.toggleSelectAll(selectAllCheckbox.checked);

            selectAllTh.appendChild(selectAllCheckbox);
            headerRow.appendChild(selectAllTh);
        }

        this.state.columnOrder.forEach(colIndex => {
            // ⭐ דלג על עמודות מוסתרות
            if (!this.state.columnVisibility[colIndex]) return;

            const column = this.config.columns[colIndex];
            const th = document.createElement('th');
            th.className = 'tm-header-cell';
            th.dataset.columnIndex = colIndex;
            
            const width = this.state.columnWidths[colIndex];
            th.style.width = width;
            th.style.minWidth = width;
            
            const wrapper = document.createElement('div');
            wrapper.className = 'tm-header-wrapper';
            
            const label = document.createElement('span');
            label.className = 'tm-header-label';
            label.textContent = column.label;
            wrapper.appendChild(label);
            
            if (this.config.sortable && column.sortable !== false) {
                const sortIcon = document.createElement('span');
                sortIcon.className = 'tm-sort-icon';
                sortIcon.innerHTML = this.getSortIcon(colIndex);
                wrapper.appendChild(sortIcon);
            }
            
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
        
        this.syncColumnWidths();
    }
    
    /**
     * סנכרן רוחבי עמודות
     */
    syncColumnWidths() {
        // ⭐ מחק colgroup קיים ובנה מחדש (נדרש כשמשתנה נראות עמודות)
        const existingColgroup = this.elements.bodyTable.querySelector('colgroup');
        if (existingColgroup) {
            existingColgroup.remove();
        }

        const colgroup = document.createElement('colgroup');

        // ⭐ עמודת בחירה מרובה
        if (this.state.multiSelectEnabled) {
            const selectCol = document.createElement('col');
            selectCol.style.width = '50px';
            selectCol.style.minWidth = '50px';
            colgroup.appendChild(selectCol);
        }

        this.state.columnOrder.forEach(colIndex => {
            // ⭐ דלג על עמודות מוסתרות
            if (!this.state.columnVisibility[colIndex]) return;

            const col = document.createElement('col');
            const width = this.state.columnWidths[colIndex];
            col.style.width = width;
            col.style.minWidth = width;
            colgroup.appendChild(col);
        });
        this.elements.bodyTable.insertBefore(colgroup, this.elements.tbody);
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
            ? this.state.displayedData.slice((this.state.currentPage - 1) * this.config.scrollLoadBatch)
            : this.state.displayedData;
        
        const rows = dataToRender.map(rowData => {
            const tr = document.createElement('tr');
            tr.className = 'tm-row';

            // ⭐ מזהה ייחודי לשורה
            const rowId = rowData.id || rowData.unicId || rowData._id || JSON.stringify(rowData);

            if (this.config.onRowDoubleClick) {
                tr.style.cursor = 'pointer';
                tr.ondblclick = () => {
                    this.config.onRowDoubleClick(rowData);
                };
            }

            // ⭐ תא בחירה מרובה
            if (this.state.multiSelectEnabled) {
                const selectTd = document.createElement('td');
                selectTd.className = 'tm-cell tm-select-cell';
                selectTd.style.cssText = `
                    width: 50px !important;
                    min-width: 50px !important;
                    max-width: 50px !important;
                    text-align: center !important;
                `;

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'tm-row-select';
                checkbox.style.cssText = `
                    width: 18px;
                    height: 18px;
                    cursor: pointer;
                `;
                checkbox.checked = this.state.selectedRows.has(rowId);
                checkbox.onchange = (e) => {
                    e.stopPropagation();
                    this.toggleRowSelection(rowId, checkbox.checked, rowData);
                };

                // מנע double-click מלפתוח את השורה
                selectTd.ondblclick = (e) => e.stopPropagation();

                selectTd.appendChild(checkbox);
                tr.appendChild(selectTd);
            }

            this.state.columnOrder.forEach(colIndex => {
                // ⭐ דלג על עמודות מוסתרות
                if (!this.state.columnVisibility[colIndex]) return;

                const column = this.config.columns[colIndex];
                const td = document.createElement('td');
                td.className = 'tm-cell';
                
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
     * מיון נתונים
     */
    sortData(data) {
        const column = this.config.columns[this.state.sortColumn];
        const field = column.field;
        
        return [...data].sort((a, b) => {
            let valA = a[field];
            let valB = b[field];
            
            if (valA == null) valA = '';
            if (valB == null) valB = '';
            
            if (column.type === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            }
            
            if (column.type === 'date') {
                valA = new Date(valA).getTime() || 0;
                valB = new Date(valB).getTime() || 0;
            }
            
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
     * ⭐ סינון נתונים - תומך בכל סוגי הפילטרים
     */
    filterData(data) {
        if (this.state.filters.size === 0) {
            return data;
        }

        return data.filter(row => {
            let matches = true;

            this.state.filters.forEach((filter, colIndex) => {
                if (!matches) return; // כבר לא מתאים

                const column = this.config.columns[colIndex];
                const cellValue = row[column.field];
                const filterType = filter.type || 'text';

                switch (filterType) {
                    case 'text':
                        matches = this.matchTextFilter(cellValue, filter);
                        break;
                    case 'number':
                        matches = this.matchNumberFilter(cellValue, filter);
                        break;
                    case 'date':
                        matches = this.matchDateFilter(cellValue, filter);
                        break;
                    case 'enum':
                    case 'select':
                        matches = this.matchEnumFilter(cellValue, filter);
                        break;
                    default:
                        // תאימות לאחור - פילטר ישן (מחרוזת פשוטה)
                        if (typeof filter === 'string') {
                            const cellStr = String(cellValue || '').toLowerCase();
                            matches = cellStr.includes(filter.toLowerCase());
                        }
                }
            });

            return matches;
        });
    }

    /**
     * התאמת פילטר טקסט
     */
    matchTextFilter(cellValue, filter) {
        const cellStr = String(cellValue || '').toLowerCase();
        const filterValue = String(filter.value || '').toLowerCase();

        switch (filter.operator) {
            case 'exact':
                return cellStr === filterValue;
            case 'contains':
                return cellStr.includes(filterValue);
            case 'starts':
                return cellStr.startsWith(filterValue);
            case 'ends':
                return cellStr.endsWith(filterValue);
            default:
                return cellStr.includes(filterValue);
        }
    }

    /**
     * התאמת פילטר מספרי
     */
    matchNumberFilter(cellValue, filter) {
        const num = parseFloat(cellValue);
        const filterNum = parseFloat(filter.value);
        const filterNum2 = parseFloat(filter.value2);

        if (isNaN(num)) return false;

        switch (filter.operator) {
            case 'equals':
                return num === filterNum;
            case 'less':
                return num < filterNum;
            case 'greater':
                return num > filterNum;
            case 'between':
                return num >= filterNum && num <= filterNum2;
            default:
                return num === filterNum;
        }
    }

    /**
     * התאמת פילטר תאריך
     */
    matchDateFilter(cellValue, filter) {
        if (!cellValue) return false;

        const cellDate = new Date(cellValue);
        if (isNaN(cellDate.getTime())) return false;

        const filterDate = new Date(filter.value);
        const filterDate2 = filter.value2 ? new Date(filter.value2) : null;

        switch (filter.operator) {
            case 'exact':
                return cellDate.toDateString() === filterDate.toDateString();

            case 'approximate':
                // ±2.5 שנים
                const yearsInMs = 2.5 * 365 * 24 * 60 * 60 * 1000;
                const minDate = new Date(filterDate.getTime() - yearsInMs);
                const maxDate = new Date(filterDate.getTime() + yearsInMs);
                return cellDate >= minDate && cellDate <= maxDate;

            case 'between':
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
     * התאמת פילטר enum (בחירה מרובה)
     */
    matchEnumFilter(cellValue, filter) {
        if (!filter.selectedValues || filter.selectedValues.length === 0) {
            return true;
        }
        const cellStr = String(cellValue || '');
        return filter.selectedValues.includes(cellStr);
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
        if (this.config.sortable) {
            this.elements.thead.addEventListener('click', (e) => {
                const th = e.target.closest('.tm-header-cell');
                if (th && !e.target.closest('.tm-menu-btn')) {
                    const colIndex = parseInt(th.dataset.columnIndex);
                    this.handleSort(colIndex);
                }
            });
        }
        
        if (this.config.resizable) {
            this.bindResizeEvents();
        }
    }
    
    /**
     * טיפול במיון
     */
    handleSort(colIndex) {
        if (this.state.sortColumn === colIndex) {
            this.state.sortOrder = this.state.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.state.sortColumn = colIndex;
            this.state.sortOrder = 'asc';
        }
        
        if (this.config.onSort) {
            const column = this.config.columns[colIndex];
            this.config.onSort(column.field, this.state.sortOrder);
        }
        
        this.loadInitialData();
        this.renderHeaders();
    }

    /**
     * הצגת תפריט עמודה עם תפריט משנה לסינון
     */
    showColumnMenu(colIndex, button) {
        document.querySelectorAll('.tm-column-menu').forEach(m => m.remove());
        document.querySelectorAll('.tm-filter-submenu').forEach(m => m.remove());

        const column = this.config.columns[colIndex];
        const filterType = column.filterType || 'text';
        const hasFilter = this.state.filters.has(colIndex);

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
            <div class="tm-menu-item tm-has-submenu" data-action="filter">
                <span>🔍</span> סינון
                <span style="margin-right: auto; margin-left: 0;">◀</span>
            </div>
            <div class="tm-menu-item" data-action="clear-filter" style="${hasFilter ? 'color: #dc2626;' : 'color: #9ca3af; pointer-events: none;'}">
                <span>✕</span> נקה סינון
            </div>
        `;

        const rect = button.getBoundingClientRect();
        menu.style.top = `${rect.bottom + 5}px`;
        menu.style.right = `${window.innerWidth - rect.right}px`;

        // טיפול בפריטי תפריט
        menu.addEventListener('click', (e) => {
            const item = e.target.closest('.tm-menu-item');
            if (!item) return;

            const action = item.dataset.action;

            // אם זה סינון - לא לסגור, להציג תפריט משנה
            if (action === 'filter') {
                e.stopPropagation();
                this.showFilterSubmenu(colIndex, item, menu);
                return;
            }

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

                case 'clear-filter':
                    this.state.filters.delete(colIndex);
                    this.loadInitialData();
                    this.updateClearFiltersButton();
                    break;
            }

            menu.remove();
            document.querySelectorAll('.tm-filter-submenu').forEach(m => m.remove());
        });

        // פתיחת תפריט משנה ב-hover
        const filterItem = menu.querySelector('[data-action="filter"]');
        if (filterItem) {
            filterItem.addEventListener('mouseenter', () => {
                this.showFilterSubmenu(colIndex, filterItem, menu);
            });
        }

        document.body.appendChild(menu);

        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && !e.target.closest('.tm-filter-submenu')) {
                    menu.remove();
                    document.querySelectorAll('.tm-filter-submenu').forEach(m => m.remove());
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }

    /**
     * ⭐ תפריט משנה לסינון
     */
    showFilterSubmenu(colIndex, parentItem, parentMenu) {
        // הסר תפריט משנה קיים
        document.querySelectorAll('.tm-filter-submenu').forEach(m => m.remove());

        const column = this.config.columns[colIndex];
        const filterType = column.filterType || 'text';
        const currentFilter = this.state.filters.get(colIndex) || {};

        const submenu = document.createElement('div');
        submenu.className = 'tm-filter-submenu';

        const parentRect = parentItem.getBoundingClientRect();
        const menuRect = parentMenu.getBoundingClientRect();

        submenu.style.cssText = `
            position: fixed;
            top: ${parentRect.top}px;
            right: ${window.innerWidth - menuRect.left + 5}px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 280px;
            max-width: 350px;
            z-index: 1001;
            direction: rtl;
        `;

        // כותרת
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 12px 16px;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 8px 8px 0 0;
        `;
        header.textContent = `סינון: ${column.label}`;
        submenu.appendChild(header);

        // תוכן הסינון
        const content = document.createElement('div');
        content.style.cssText = `padding: 16px;`;

        switch (filterType) {
            case 'text':
                this.buildTextFilterContent(content, colIndex, column);
                break;
            case 'number':
                this.buildNumberFilterContent(content, colIndex, column);
                break;
            case 'date':
                this.buildDateFilterContent(content, colIndex, column);
                break;
            case 'enum':
            case 'select':
                this.buildEnumFilterContent(content, colIndex, column);
                break;
            default:
                this.buildTextFilterContent(content, colIndex, column);
        }

        submenu.appendChild(content);

        // כפתורי פעולה
        const actions = document.createElement('div');
        actions.style.cssText = `
            padding: 12px 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        `;

        const applyBtn = document.createElement('button');
        applyBtn.textContent = 'החל';
        applyBtn.style.cssText = `
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        `;
        applyBtn.onclick = () => {
            const success = this.applyFilterFromSubmenu(colIndex, content, filterType);
            if (success !== false) {
                parentMenu.remove();
                submenu.remove();
            }
        };

        const clearBtn = document.createElement('button');
        clearBtn.textContent = 'נקה';
        clearBtn.style.cssText = `
            padding: 8px 16px;
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
        `;
        clearBtn.onclick = () => {
            this.state.filters.delete(colIndex);
            this.loadInitialData();
            this.updateClearFiltersButton();
            parentMenu.remove();
            submenu.remove();
        };

        actions.appendChild(applyBtn);
        actions.appendChild(clearBtn);
        submenu.appendChild(actions);

        document.body.appendChild(submenu);

        // וודא שהתפריט לא יוצא מהמסך
        const submenuRect = submenu.getBoundingClientRect();
        if (submenuRect.left < 0) {
            submenu.style.right = 'auto';
            submenu.style.left = `${menuRect.right + 5}px`;
        }
        if (submenuRect.bottom > window.innerHeight) {
            submenu.style.top = `${window.innerHeight - submenuRect.height - 10}px`;
        }
    }

    /**
     * ⭐ החלת סינון מתפריט משנה
     */
    applyFilterFromSubmenu(colIndex, container, filterType) {
        const column = this.config.columns[colIndex];

        let filterData = { type: filterType };

        if (filterType === 'enum' || filterType === 'select') {
            const checkboxes = container.querySelectorAll('.enum-checkbox:checked');
            filterData.selectedValues = Array.from(checkboxes).map(cb => cb.value);
            if (filterData.selectedValues.length === 0) {
                this.state.filters.delete(colIndex);
                this.loadInitialData();
                this.updateClearFiltersButton();
                return true;
            }
        } else if (filterType === 'date') {
            const operator = container.querySelector('.filter-operator')?.value;
            filterData.operator = operator;

            if (operator === 'between') {
                const valueFrom = container.querySelector('.filter-value-from')?.value;
                const valueTo = container.querySelector('.filter-value-to')?.value;

                if (!valueFrom || !valueTo) {
                    if (typeof showToast === 'function') {
                        showToast('יש לבחור את שני התאריכים', 'warning');
                    }
                    return false;
                }

                filterData.value = valueFrom;
                filterData.value2 = valueTo;
            } else {
                const value = container.querySelector('.filter-value')?.value;
                if (!value) {
                    this.state.filters.delete(colIndex);
                    this.loadInitialData();
                    this.updateClearFiltersButton();
                    return true;
                }
                filterData.value = value;
            }
        } else {
            const operator = container.querySelector('.filter-operator')?.value;
            const value = container.querySelector('.filter-value')?.value;
            const value2 = container.querySelector('.filter-value2')?.value;

            if (!value && !value2) {
                this.state.filters.delete(colIndex);
                this.loadInitialData();
                this.updateClearFiltersButton();
                return true;
            }

            filterData.operator = operator;
            filterData.value = value;
            if (value2) filterData.value2 = value2;
        }

        this.state.filters.set(colIndex, filterData);

        if (this.config.onFilter) {
            this.config.onFilter(Array.from(this.state.filters.entries()));
        }

        this.loadInitialData();
        this.updateClearFiltersButton();
        return true;
    }
    
    /**
     * ⭐ דיאלוג סינון מתקדם - לפי סוג השדה
     * filterType: 'text' | 'number' | 'date' | 'enum'
     */
    showFilterDialog(colIndex) {
        const column = this.config.columns[colIndex];
        const filterType = column.filterType || 'text';

        // סגור דיאלוגים קודמים
        document.querySelectorAll('.tm-filter-dialog, .tm-filter-overlay').forEach(d => d.remove());

        const dialog = document.createElement('div');
        dialog.className = 'tm-filter-dialog';
        dialog.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            min-width: 320px;
            max-width: 400px;
            z-index: 2000;
            direction: rtl;
        `;

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
        overlay.onclick = () => {
            dialog.remove();
            overlay.remove();
        };

        // כותרת
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;
        header.innerHTML = `<span>סינון: ${column.label}</span>`;

        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '✕';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #6b7280;
        `;
        closeBtn.onclick = () => {
            dialog.remove();
            overlay.remove();
        };
        header.appendChild(closeBtn);
        dialog.appendChild(header);

        // תוכן לפי סוג הפילטר
        const content = document.createElement('div');
        content.style.cssText = `padding: 20px;`;

        switch (filterType) {
            case 'text':
                this.buildTextFilterContent(content, colIndex, column);
                break;
            case 'number':
                this.buildNumberFilterContent(content, colIndex, column);
                break;
            case 'date':
                this.buildDateFilterContent(content, colIndex, column);
                break;
            case 'enum':
            case 'select':
                this.buildEnumFilterContent(content, colIndex, column);
                break;
            default:
                this.buildTextFilterContent(content, colIndex, column);
        }

        dialog.appendChild(content);

        // כפתורי פעולה
        const actions = document.createElement('div');
        actions.style.cssText = `
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        `;

        const applyBtn = document.createElement('button');
        applyBtn.textContent = 'החל סינון';
        applyBtn.style.cssText = `
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        `;
        applyBtn.onclick = () => {
            const success = this.applyFilter(colIndex, dialog);
            if (success !== false) {
                dialog.remove();
                overlay.remove();
            }
        };

        const clearBtn = document.createElement('button');
        clearBtn.textContent = 'נקה';
        clearBtn.style.cssText = `
            padding: 10px 20px;
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
        `;
        clearBtn.onclick = () => {
            this.state.filters.delete(colIndex);
            this.loadInitialData();
            this.updateClearFiltersButton();
            dialog.remove();
            overlay.remove();
        };

        actions.appendChild(applyBtn);
        actions.appendChild(clearBtn);
        dialog.appendChild(actions);

        document.body.appendChild(overlay);
        document.body.appendChild(dialog);
    }

    /**
     * בניית תוכן פילטר טקסט
     */
    buildTextFilterContent(container, colIndex, column) {
        const currentFilter = this.state.filters.get(colIndex) || {};

        container.innerHTML = `
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">סוג סינון:</label>
                <select class="filter-operator" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="contains" ${currentFilter.operator === 'contains' ? 'selected' : ''}>מכיל</option>
                    <option value="exact" ${currentFilter.operator === 'exact' ? 'selected' : ''}>ערך מדויק</option>
                    <option value="starts" ${currentFilter.operator === 'starts' ? 'selected' : ''}>מתחיל ב</option>
                    <option value="ends" ${currentFilter.operator === 'ends' ? 'selected' : ''}>מסתיים ב</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">ערך:</label>
                <input type="text" class="filter-value" value="${currentFilter.value || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;"
                    placeholder="הזן ערך לסינון...">
            </div>
        `;
    }

    /**
     * בניית תוכן פילטר מספרי
     */
    buildNumberFilterContent(container, colIndex, column) {
        const currentFilter = this.state.filters.get(colIndex) || {};

        container.innerHTML = `
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">סוג סינון:</label>
                <select class="filter-operator" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="equals" ${currentFilter.operator === 'equals' ? 'selected' : ''}>שווה ל</option>
                    <option value="less" ${currentFilter.operator === 'less' ? 'selected' : ''}>קטן מ</option>
                    <option value="greater" ${currentFilter.operator === 'greater' ? 'selected' : ''}>גדול מ</option>
                    <option value="between" ${currentFilter.operator === 'between' ? 'selected' : ''}>בין ... לבין ...</option>
                </select>
            </div>
            <div class="filter-value-container">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">ערך:</label>
                <input type="number" class="filter-value" value="${currentFilter.value || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;">
            </div>
            <div class="filter-value2-container" style="margin-top: 15px; display: ${currentFilter.operator === 'between' ? 'block' : 'none'};">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">עד ערך:</label>
                <input type="number" class="filter-value2" value="${currentFilter.value2 || ''}"
                    style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;">
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
    buildDateFilterContent(container, colIndex, column) {
        const currentFilter = this.state.filters.get(colIndex) || {};
        const isBetween = currentFilter.operator === 'between';

        container.innerHTML = `
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">סוג סינון:</label>
                <select class="filter-operator" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="exact" ${currentFilter.operator === 'exact' ? 'selected' : ''}>תאריך מדויק</option>
                    <option value="approximate" ${currentFilter.operator === 'approximate' ? 'selected' : ''}>תאריך משוער (±2.5 שנים)</option>
                    <option value="between" ${currentFilter.operator === 'between' ? 'selected' : ''}>בין תאריכים</option>
                    <option value="before" ${currentFilter.operator === 'before' ? 'selected' : ''}>לפני תאריך</option>
                    <option value="after" ${currentFilter.operator === 'after' ? 'selected' : ''}>אחרי תאריך</option>
                </select>
            </div>

            <!-- שדה תאריך בודד (לא בין תאריכים) -->
            <div class="single-date-container" style="display: ${isBetween ? 'none' : 'block'};">
                <input type="hidden" class="filter-value" value="${currentFilter.value || ''}">
                <button type="button" class="single-date-trigger" style="
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    background: white;
                    cursor: pointer;
                    text-align: right;
                    font-size: 14px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                ">
                    <span class="single-date-text" style="color: ${currentFilter.value ? '#1f2937' : '#9ca3af'};">
                        ${currentFilter.value ? this.formatDateHebrew(currentFilter.value) : 'לחץ לבחירת תאריך'}
                    </span>
                    <span>📅</span>
                </button>
            </div>

            <!-- בחירת טווח תאריכים - כפתור שפותח חלונית כפולה -->
            <div class="between-dates-container" style="display: ${isBetween ? 'block' : 'none'};">
                <input type="hidden" class="filter-value-from" value="${currentFilter.value || ''}">
                <input type="hidden" class="filter-value-to" value="${currentFilter.value2 || ''}">

                <button type="button" class="date-range-trigger" style="
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    background: white;
                    cursor: pointer;
                    text-align: right;
                    font-size: 14px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                ">
                    <span class="date-range-text" style="color: ${currentFilter.value ? '#1f2937' : '#9ca3af'};">
                        ${currentFilter.value && currentFilter.value2
                            ? this.formatDateHebrew(currentFilter.value) + ' — ' + this.formatDateHebrew(currentFilter.value2)
                            : 'לחץ לבחירת טווח תאריכים'}
                    </span>
                    <span>📅</span>
                </button>
            </div>
        `;

        const operatorSelect = container.querySelector('.filter-operator');
        const singleDateContainer = container.querySelector('.single-date-container');
        const betweenDatesContainer = container.querySelector('.between-dates-container');
        const singleDateTrigger = container.querySelector('.single-date-trigger');
        const dateRangeTrigger = container.querySelector('.date-range-trigger');

        operatorSelect.onchange = () => {
            const isBetweenNow = operatorSelect.value === 'between';
            singleDateContainer.style.display = isBetweenNow ? 'none' : 'block';
            betweenDatesContainer.style.display = isBetweenNow ? 'block' : 'none';
        };

        if (singleDateTrigger) {
            singleDateTrigger.onclick = () => {
                this.showSingleDatePicker(container, currentFilter.value);
            };
        }

        if (dateRangeTrigger) {
            dateRangeTrigger.onclick = () => {
                this.showDateRangePicker(container, currentFilter.value, currentFilter.value2);
            };
        }
    }

    /**
     * פורמט תאריך לעברית
     */
    formatDateHebrew(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('he-IL');
    }

    /**
     * ⭐ המרת תאריך למחרוזת YYYY-MM-DD בלי בעיות timezone
     */
    formatDateISO(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * ⭐ חלונית בחירת תאריך בודד - לוח שנה אחד
     */
    showSingleDatePicker(container, initialDate) {
        // הסר picker קיים
        document.querySelectorAll('.tm-date-picker').forEach(p => p.remove());

        const trigger = container.querySelector('.single-date-trigger');
        const rect = trigger.getBoundingClientRect();

        const picker = document.createElement('div');
        picker.className = 'tm-date-picker';
        picker.style.cssText = `
            position: fixed;
            top: ${Math.min(rect.bottom + 5, window.innerHeight - 380)}px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
            z-index: 3000;
            direction: rtl;
            padding: 20px;
            min-width: 300px;
        `;

        let selectedDate = initialDate ? new Date(initialDate) : null;
        let currentMonth = selectedDate ? new Date(selectedDate) : new Date();

        const self = this;

        const renderPicker = () => {
            picker.innerHTML = `
                <div class="calendar-panel" data-side="single">
                    <div style="text-align: center; margin-bottom: 12px; padding: 8px; background: #eff6ff; border-radius: 6px;">
                        <span style="font-weight: 600; color: #3b82f6;">📅 בחר תאריך</span>
                    </div>
                    ${self.renderCalendarMonth(currentMonth, selectedDate, null, 'single')}
                </div>

                <!-- תצוגת בחירה וכפתורים -->
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 15px; font-weight: 500;">
                        ${selectedDate
                            ? '<span style="color: #3b82f6;">' + self.formatDateHebrew(selectedDate) + '</span>'
                            : '<span style="color: #9ca3af;">לא נבחר תאריך</span>'}
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="picker-cancel" style="
                            padding: 10px 20px;
                            border: 1px solid #d1d5db;
                            border-radius: 6px;
                            background: white;
                            cursor: pointer;
                            font-size: 14px;
                        ">ביטול</button>
                        <button class="picker-confirm" style="
                            padding: 10px 20px;
                            border: none;
                            border-radius: 6px;
                            background: ${selectedDate ? '#3b82f6' : '#d1d5db'};
                            color: ${selectedDate ? 'white' : '#9ca3af'};
                            cursor: ${selectedDate ? 'pointer' : 'not-allowed'};
                            font-size: 14px;
                            font-weight: 500;
                        " ${!selectedDate ? 'disabled' : ''}>אישור</button>
                    </div>
                </div>
            `;

            // אירועי ניווט חודשים
            picker.querySelectorAll('.calendar-nav').forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const dir = parseInt(btn.dataset.dir);
                    currentMonth.setMonth(currentMonth.getMonth() + dir);
                    renderPicker();
                };
            });

            // אירועי בחירת יום
            picker.querySelectorAll('.calendar-day:not(.empty)').forEach(dayEl => {
                dayEl.onclick = (e) => {
                    e.stopPropagation();
                    const dateStr = dayEl.dataset.date;
                    selectedDate = new Date(dateStr);
                    renderPicker();
                };
            });

            // כפתור ביטול
            picker.querySelector('.picker-cancel').onclick = () => picker.remove();

            // כפתור אישור
            const confirmBtn = picker.querySelector('.picker-confirm');
            confirmBtn.onclick = () => {
                if (selectedDate) {
                    const dateStr = self.formatDateISO(selectedDate);

                    container.querySelector('.filter-value').value = dateStr;

                    const dateText = container.querySelector('.single-date-text');
                    dateText.textContent = self.formatDateHebrew(dateStr);
                    dateText.style.color = '#1f2937';

                    picker.remove();
                }
            };
        };

        renderPicker();
        document.body.appendChild(picker);

        // סגירה בלחיצה מחוץ לחלונית
        setTimeout(() => {
            const closePicker = (e) => {
                if (!picker.contains(e.target) && !trigger.contains(e.target)) {
                    picker.remove();
                    document.removeEventListener('click', closePicker);
                }
            };
            document.addEventListener('click', closePicker);
        }, 50);
    }

    /**
     * ⭐ חלונית בחירת טווח תאריכים - שני לוחות שנה בחלון אחד
     */
    showDateRangePicker(container, initialFrom, initialTo) {
        // הסר picker קיים
        document.querySelectorAll('.tm-date-range-picker').forEach(p => p.remove());

        const trigger = container.querySelector('.date-range-trigger');
        const rect = trigger.getBoundingClientRect();

        const picker = document.createElement('div');
        picker.className = 'tm-date-range-picker';
        picker.style.cssText = `
            position: fixed;
            top: ${Math.min(rect.bottom + 5, window.innerHeight - 450)}px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
            z-index: 3000;
            direction: rtl;
            padding: 20px;
        `;

        // תאריכים נבחרים
        let selectedFrom = initialFrom ? new Date(initialFrom) : null;
        let selectedTo = initialTo ? new Date(initialTo) : null;

        // חודשים מוצגים (ימין = מתאריך, שמאל = עד תאריך)
        // תמיד יום 1 למניעת גלישת ימים בין חודשים
        const now = new Date();
        let rightMonth = selectedFrom
            ? new Date(selectedFrom.getFullYear(), selectedFrom.getMonth(), 1)
            : new Date(now.getFullYear(), now.getMonth(), 1);
        let leftMonth = new Date(rightMonth.getFullYear(), rightMonth.getMonth() + 1, 1);

        const self = this;

        const renderPicker = () => {
            picker.innerHTML = `
                <div style="display: flex; gap: 30px;">
                    <!-- לוח ימין - מתאריך -->
                    <div class="calendar-panel" data-side="from" style="min-width: 280px;">
                        <div style="text-align: center; margin-bottom: 12px; padding: 8px; background: #ecfdf5; border-radius: 6px;">
                            <span style="font-weight: 600; color: #059669;">📅 מתאריך</span>
                        </div>
                        ${self.renderCalendarMonth(rightMonth, selectedFrom, selectedTo, 'from')}
                    </div>

                    <!-- קו מפריד -->
                    <div style="width: 1px; background: #e5e7eb;"></div>

                    <!-- לוח שמאל - עד תאריך -->
                    <div class="calendar-panel" data-side="to" style="min-width: 280px;">
                        <div style="text-align: center; margin-bottom: 12px; padding: 8px; background: #fef2f2; border-radius: 6px;">
                            <span style="font-weight: 600; color: #dc2626;">📅 עד תאריך</span>
                        </div>
                        ${self.renderCalendarMonth(leftMonth, selectedFrom, selectedTo, 'to')}
                    </div>
                </div>

                <!-- תצוגת בחירה וכפתורים -->
                <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 15px; font-weight: 500;">
                        ${selectedFrom && selectedTo
                            ? '<span style="color: #059669;">' + self.formatDateHebrew(selectedFrom) + '</span> <span style="color: #6b7280;">עד</span> <span style="color: #dc2626;">' + self.formatDateHebrew(selectedTo) + '</span>'
                            : selectedFrom
                                ? '<span style="color: #059669;">' + self.formatDateHebrew(selectedFrom) + '</span> <span style="color: #9ca3af;">← בחר תאריך סיום</span>'
                                : '<span style="color: #9ca3af;">לחץ לבחירת תאריך התחלה</span>'}
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="picker-cancel" style="
                            padding: 10px 20px;
                            border: 1px solid #d1d5db;
                            border-radius: 6px;
                            background: white;
                            cursor: pointer;
                            font-size: 14px;
                        ">ביטול</button>
                        <button class="picker-confirm" style="
                            padding: 10px 20px;
                            border: none;
                            border-radius: 6px;
                            background: ${selectedFrom && selectedTo ? '#3b82f6' : '#d1d5db'};
                            color: ${selectedFrom && selectedTo ? 'white' : '#9ca3af'};
                            cursor: ${selectedFrom && selectedTo ? 'pointer' : 'not-allowed'};
                            font-size: 14px;
                            font-weight: 500;
                        " ${!selectedFrom || !selectedTo ? 'disabled' : ''}>אישור</button>
                    </div>
                </div>
            `;

            // אירועי ניווט חודשים - שומר שתמיד יהיה הבדל של חודש לפחות
            picker.querySelectorAll('.calendar-nav').forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const dir = parseInt(btn.dataset.dir);
                    const side = btn.closest('.calendar-panel').dataset.side;

                    if (side === 'from') {
                        // הזזת לוח תאריך התחלה (יום 1 למניעת גלישת ימים)
                        rightMonth = new Date(rightMonth.getFullYear(), rightMonth.getMonth() + dir, 1);
                        // אם הגיע לאותו חודש כמו סיום או אחריו - דחוף את הסיום קדימה
                        if (rightMonth.getFullYear() > leftMonth.getFullYear() ||
                            (rightMonth.getFullYear() === leftMonth.getFullYear() && rightMonth.getMonth() >= leftMonth.getMonth())) {
                            leftMonth = new Date(rightMonth.getFullYear(), rightMonth.getMonth() + 1, 1);
                        }
                    } else {
                        // הזזת לוח תאריך סיום (יום 1 למניעת גלישת ימים)
                        leftMonth = new Date(leftMonth.getFullYear(), leftMonth.getMonth() + dir, 1);
                        // אם הגיע לאותו חודש כמו התחלה או לפניו - דחוף את ההתחלה אחורה
                        if (leftMonth.getFullYear() < rightMonth.getFullYear() ||
                            (leftMonth.getFullYear() === rightMonth.getFullYear() && leftMonth.getMonth() <= rightMonth.getMonth())) {
                            rightMonth = new Date(leftMonth.getFullYear(), leftMonth.getMonth() - 1, 1);
                        }
                    }
                    renderPicker();
                };
            });

            // אירועי בחירת יום - לוגיקה חכמה
            // 1. לחיצה ראשונה = תאריך התחלה
            // 2. לחיצה שנייה = אם מאוחר מההתחלה → תאריך סיום, אם מוקדם → מחליף את ההתחלה
            // 3. אם שניהם כבר נבחרו = איפוס, התאריך החדש הופך להתחלה
            picker.querySelectorAll('.calendar-day:not(.empty)').forEach(dayEl => {
                dayEl.onclick = (e) => {
                    e.stopPropagation();
                    const dateStr = dayEl.dataset.date;
                    const date = new Date(dateStr);

                    if (selectedFrom && selectedTo) {
                        // שני התאריכים כבר נבחרו - איפוס, התאריך החדש = התחלה
                        selectedFrom = date;
                        selectedTo = null;
                    } else if (!selectedFrom) {
                        // אין תאריך התחלה - זו לחיצה ראשונה
                        selectedFrom = date;
                    } else {
                        // יש תאריך התחלה, אין סיום
                        if (date > selectedFrom) {
                            // התאריך מאוחר מההתחלה - הופך לסיום
                            selectedTo = date;
                        } else {
                            // התאריך מוקדם או שווה להתחלה - מחליף את ההתחלה
                            selectedFrom = date;
                            selectedTo = null;
                        }
                    }
                    renderPicker();
                };
            });

            // כפתור ביטול
            picker.querySelector('.picker-cancel').onclick = () => picker.remove();

            // כפתור אישור
            const confirmBtn = picker.querySelector('.picker-confirm');
            confirmBtn.onclick = () => {
                if (selectedFrom && selectedTo) {
                    const fromStr = self.formatDateISO(selectedFrom);
                    const toStr = self.formatDateISO(selectedTo);

                    container.querySelector('.filter-value-from').value = fromStr;
                    container.querySelector('.filter-value-to').value = toStr;

                    const rangeText = container.querySelector('.date-range-text');
                    rangeText.textContent = self.formatDateHebrew(fromStr) + ' — ' + self.formatDateHebrew(toStr);
                    rangeText.style.color = '#1f2937';

                    picker.remove();
                }
            };
        };

        renderPicker();
        document.body.appendChild(picker);

        // סגירה בלחיצה מחוץ לחלונית
        setTimeout(() => {
            const closePicker = (e) => {
                if (!picker.contains(e.target) && !trigger.contains(e.target)) {
                    picker.remove();
                    document.removeEventListener('click', closePicker);
                }
            };
            document.addEventListener('click', closePicker);
        }, 50);
    }

    /**
     * רינדור חודש בלוח שנה
     */
    renderCalendarMonth(monthDate, selectedFrom, selectedTo, side) {
        const year = monthDate.getFullYear();
        const month = monthDate.getMonth();

        const monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני',
                           'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
        const dayNames = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳', 'ש׳'];

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDayOfWeek = firstDay.getDay();

        let html = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 0 5px;">
                <button type="button" class="calendar-nav" data-dir="-1" style="
                    border: none; background: #f3f4f6; border-radius: 50%; width: 32px; height: 32px;
                    cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center;
                ">◀</button>
                <span style="font-weight: 600; font-size: 15px;">${monthNames[month]} ${year}</span>
                <button type="button" class="calendar-nav" data-dir="1" style="
                    border: none; background: #f3f4f6; border-radius: 50%; width: 32px; height: 32px;
                    cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center;
                ">▶</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; direction: ltr;">
        `;

        // כותרות ימים
        dayNames.forEach(day => {
            html += `<div style="text-align: center; font-size: 12px; color: #6b7280; padding: 8px 0; font-weight: 500;">${day}</div>`;
        });

        // תאים ריקים לפני תחילת החודש
        for (let i = 0; i < startDayOfWeek; i++) {
            html += `<div class="calendar-day empty" style="padding: 10px;"></div>`;
        }

        // ימי החודש
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dateStr = this.formatDateISO(date);

            let bgColor = 'transparent';
            let textColor = '#1f2937';
            let borderRadius = '6px';

            if (side === 'single') {
                // תאריך בודד - כחול
                if (selectedFrom && date.toDateString() === selectedFrom.toDateString()) {
                    bgColor = '#3b82f6';
                    textColor = 'white';
                    borderRadius = '50%';
                }
            } else {
                // טווח תאריכים - ירוק/אדום
                let isSelectedFrom = selectedFrom && date.toDateString() === selectedFrom.toDateString();
                let isSelectedTo = selectedTo && date.toDateString() === selectedTo.toDateString();
                let isInRange = selectedFrom && selectedTo && date > selectedFrom && date < selectedTo;

                if (isSelectedFrom) {
                    bgColor = '#059669';
                    textColor = 'white';
                    borderRadius = '50%';
                } else if (isSelectedTo) {
                    bgColor = '#dc2626';
                    textColor = 'white';
                    borderRadius = '50%';
                } else if (isInRange) {
                    bgColor = '#dbeafe';
                }
            }

            html += `
                <div class="calendar-day" data-date="${dateStr}" style="
                    text-align: center;
                    padding: 10px 0;
                    cursor: pointer;
                    border-radius: ${borderRadius};
                    background: ${bgColor};
                    color: ${textColor};
                    font-size: 14px;
                    transition: all 0.15s;
                ">${day}</div>
            `;
        }

        html += `</div>`;
        return html;
    }

    /**
     * בניית תוכן פילטר enum (בחירה מרשימת ערכים ייחודיים)
     */
    buildEnumFilterContent(container, colIndex, column) {
        const currentFilter = this.state.filters.get(colIndex) || { selectedValues: [] };
        const field = column.field;

        // איסוף ערכים ייחודיים מהנתונים
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
                <label style="display: flex; align-items: center; gap: 8px; padding: 8px 0; cursor: pointer; border-bottom: 1px solid #f3f4f6;">
                    <input type="checkbox" class="enum-checkbox" value="${val}" ${isChecked ? 'checked' : ''}
                        style="width: 18px; height: 18px;">
                    <span>${val}</span>
                </label>
            `;
        });

        container.innerHTML = `
            <div style="margin-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb; font-weight: 600;">
                    <input type="checkbox" class="select-all-enum" style="width: 18px; height: 18px;">
                    <span>בחר הכל</span>
                </label>
            </div>
            <div style="max-height: 250px; overflow-y: auto;">
                ${checkboxesHtml || '<p style="color: #999; text-align: center;">אין ערכים זמינים</p>'}
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
     * החלת הסינון
     */
    applyFilter(colIndex, dialog) {
        const column = this.config.columns[colIndex];
        const filterType = column.filterType || 'text';

        let filterData = { type: filterType };

        if (filterType === 'enum' || filterType === 'select') {
            const checkboxes = dialog.querySelectorAll('.enum-checkbox:checked');
            filterData.selectedValues = Array.from(checkboxes).map(cb => cb.value);
            if (filterData.selectedValues.length === 0) {
                this.state.filters.delete(colIndex);
                this.loadInitialData();
                this.updateClearFiltersButton();
                return;
            }
        } else if (filterType === 'date') {
            // ⭐ טיפול מיוחד בפילטר תאריך
            const operator = dialog.querySelector('.filter-operator')?.value;
            filterData.operator = operator;

            if (operator === 'between') {
                // בין תאריכים - קריאה משדות כפולים
                const valueFrom = dialog.querySelector('.filter-value-from')?.value;
                const valueTo = dialog.querySelector('.filter-value-to')?.value;

                if (!valueFrom || !valueTo) {
                    // שני התאריכים נדרשים
                    if (typeof showToast === 'function') {
                        showToast('יש לבחור את שני התאריכים', 'warning');
                    }
                    return false; // לא לסגור את הדיאלוג
                }

                filterData.value = valueFrom;
                filterData.value2 = valueTo;
            } else {
                // תאריך בודד
                const value = dialog.querySelector('.filter-value')?.value;
                if (!value) {
                    this.state.filters.delete(colIndex);
                    this.loadInitialData();
                    this.updateClearFiltersButton();
                    return;
                }
                filterData.value = value;
            }
        } else {
            // פילטר טקסט או מספר
            const operator = dialog.querySelector('.filter-operator')?.value;
            const value = dialog.querySelector('.filter-value')?.value;
            const value2 = dialog.querySelector('.filter-value2')?.value;

            if (!value && !value2) {
                this.state.filters.delete(colIndex);
                this.loadInitialData();
                this.updateClearFiltersButton();
                return;
            }

            filterData.operator = operator;
            filterData.value = value;
            if (value2) filterData.value2 = value2;
        }

        this.state.filters.set(colIndex, filterData);

        if (this.config.onFilter) {
            this.config.onFilter(Array.from(this.state.filters.entries()));
        }

        this.loadInitialData();
        this.updateClearFiltersButton();
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
            const newWidth = Math.max(80, startWidth - diff);  // מינימום 80px
            this.state.columnWidths[colIndex] = `${newWidth}px`;

            const th = this.elements.headerTable.querySelector(`th[data-column-index="${colIndex}"]`);
            if (th) {
                th.style.width = `${newWidth}px`;
                th.style.minWidth = `${newWidth}px`;
            }

            const col = this.elements.bodyTable.querySelector(`colgroup col:nth-child(${colIndex + 1})`);
            if (col) {
                col.style.width = `${newWidth}px`;
                col.style.minWidth = `${newWidth}px`;
            }

            // ⭐ עדכון רוחב הטבלה הכולל - הטבלה תתרחב ולא תצמצם עמודות אחרות
            this.updateTableWidth();
        };
        
        const onMouseUp = () => {
            this.state.isResizing = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            
        };
        
        this.elements.headerTable.addEventListener('mousedown', onMouseDown);
    }

    /**
     * ⭐ עדכון רוחב הטבלה הכולל
     * מחשב את סכום כל רוחבי העמודות מה-STATE (לא מהרינדור!)
     * הטבלה יכולה להתרחב - ה-fixedContainer מונע הרחבת הדף
     */
    updateTableWidth() {
        // ⭐ חישוב רוחב כולל מה-state.columnWidths - לא מ-offsetWidth!
        // זה מבטיח שהרוחבים המוגדרים נשמרים ולא מושפעים מהדפדפן
        let totalWidth = 0;

        // ⭐ עמודת בחירה מרובה
        if (this.state.multiSelectEnabled) {
            totalWidth += 50;
        }

        this.state.columnOrder.forEach(colIndex => {
            // ⭐ דלג על עמודות מוסתרות
            if (!this.state.columnVisibility[colIndex]) return;

            const w = this.state.columnWidths[colIndex];
            if (typeof w === 'string' && w.endsWith('px')) {
                totalWidth += parseInt(w);
            } else if (typeof w === 'number') {
                totalWidth += w;
            } else {
                // עמודות auto - קח את הרוחב בפועל מהרינדור
                const th = this.elements.headerTable.querySelector(`th[data-column-index="${colIndex}"]`);
                totalWidth += th ? th.offsetWidth : 100;
            }
        });

        // הוספת מרווח קטן למניעת חיתוך
        totalWidth += 2;

        // עדכון רוחב שתי הטבלאות
        const widthStyle = `${totalWidth}px`;

        if (this.elements.headerTable) {
            this.elements.headerTable.style.width = widthStyle;
            this.elements.headerTable.style.minWidth = widthStyle;
        }

        if (this.elements.bodyTable) {
            this.elements.bodyTable.style.width = widthStyle;
            this.elements.bodyTable.style.minWidth = widthStyle;
        }
    }

    /**
     * אתחול Infinite Scroll
     */
    initInfiniteScroll() {
        this.elements.bodyContainer.addEventListener('scroll', () => {
            if (this.state.isLoading) return;
            if (!this.state.hasMoreData) return;
            
            const { scrollTop, scrollHeight, clientHeight } = this.elements.bodyContainer;
            const distanceFromBottom = scrollHeight - (scrollTop + clientHeight);
            
            if (distanceFromBottom < this.config.scrollThreshold) {
                this.loadMoreData();
            }
        });
        
    }
    
    /**
     * ⭐ טעינת עוד נתונים - infinite scroll
     */
    async loadMoreData() {
        const loadedItems = this.state.displayedData.length;
        const totalAvailable = this.state.filteredData.length;
        
        
        // בדיקה 1: האם הגענו לסוף הנתונים הכולל?
        if (loadedItems >= this.config.totalItems) {
            this.state.hasMoreData = false;
            return;
        }
        
        // בדיקה 2: האם צריך לקרוא עוד נתונים מה-API?
        if (loadedItems >= totalAvailable) {
            
            if (this.config.onLoadMore) {
                this.state.isLoading = true;
                this.showLoadingIndicator();
                
                try {
                    await this.config.onLoadMore();
                    // לאחר הטעינה, הנתונים יתעדכנו דרך setData()
                } catch (error) {
                    console.error('❌ Error loading more data:', error);
                }
                
                this.hideLoadingIndicator();
                this.state.isLoading = false;
            }
            
            return;
        }
        
        // בדיקה 3: יש עוד נתונים ב-filteredData - טען אותם
        this.state.isLoading = true;
        
        this.showLoadingIndicator();
        
        await new Promise(resolve => setTimeout(resolve, 200));
        
        const nextBatch = this.state.filteredData.slice(
            loadedItems,
            loadedItems + this.config.scrollLoadBatch
        );
        
        this.state.displayedData = [...this.state.displayedData, ...nextBatch];
        
        this.renderRows(true);
        
        this.hideLoadingIndicator();
        
        this.state.isLoading = false;
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
     * קבל רוחבי עמודות
     */
    getColumnWidths() {
        const widths = {};
        this.config.columns.forEach((col, index) => {
            widths[col.field || col.label] = this.state.columnWidths[index];
        });
        return widths;
    }
    
    // ============================================
    // API ציבורי
    // ============================================
    
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
        this.updateClearFiltersButton();
    }

    /**
     * ⭐ ניקוי כל המסננים (כפתור בסרגל כלים)
     */
    clearAllFilters() {
        this.state.filters.clear();
        this.loadInitialData();
        this.updateClearFiltersButton();

        if (typeof showToast === 'function') {
            showToast('כל המסננים נוקו', 'success');
        }
    }

    /**
     * ⭐ עדכון נראות כפתור ניקוי מסננים
     * מוצג רק כשיש מסנן אחד או יותר פעיל
     */
    updateClearFiltersButton() {
        if (!this.elements.clearFiltersBtn) return;

        const hasActiveFilters = this.state.filters.size > 0;
        this.elements.clearFiltersBtn.style.display = hasActiveFilters ? 'inline-flex' : 'none';
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
    
    /**
     * ⭐ עדכון totalItems (כשמקבלים נתונים חדשים מה-API)
     */
    updateTotalItems(newTotal) {
        this.config.totalItems = newTotal;
        this.calculateTotalPages();
        
        if (this.config.showPagination) {
            this.updatePaginationFooter();
        }
        
    }
}

// הפוך לגלובלי
window.TableManager = TableManager;