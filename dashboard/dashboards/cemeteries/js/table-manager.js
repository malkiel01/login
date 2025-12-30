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
            renderCell: null,
            onSort: null,
            onRowDoubleClick: null,
            onFilter: null,
            onColumnReorder: null,
            onLoadMore: null,           // ⭐ callback לטעינת עוד נתונים מ-API
            onPageChange: null,         // ⭐ callback לשינוי עמוד
            
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

        // ⭐ חישוב רוחב טבלה התחלתי מסכום רוחבי העמודות
        let initialWidth = 0;
        this.config.columns.forEach((col, index) => {
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
        
        // הרכבה
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
        
        this.state.columnOrder.forEach(colIndex => {
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
        const bodyCols = this.elements.bodyTable.querySelectorAll('colgroup col');
        
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
            ? this.state.displayedData.slice((this.state.currentPage - 1) * this.config.scrollLoadBatch)
            : this.state.displayedData;
        
        const rows = dataToRender.map(rowData => {
            const tr = document.createElement('tr');
            tr.className = 'tm-row';
            
            if (this.config.onRowDoubleClick) {
                tr.style.cursor = 'pointer';
                tr.ondblclick = () => {
                    this.config.onRowDoubleClick(rowData);
                };
            }
            
            this.state.columnOrder.forEach(colIndex => {
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
     * סינון נתונים
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
     * הצגת תפריט עמודה
     */
    showColumnMenu(colIndex, button) {
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
        
        const rect = button.getBoundingClientRect();
        menu.style.top = `${rect.bottom + 5}px`;
        menu.style.right = `${window.innerWidth - rect.right}px`;
        
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
            
            this.loadInitialData();
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
            const newWidth = Math.max(50, startWidth - diff);
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
     * מחשב את סכום כל רוחבי העמודות ומעדכן את רוחב שתי הטבלאות
     * הטבלה יכולה להתרחב - ה-fixedContainer מונע הרחבת הדף
     */
    updateTableWidth() {
        // חישוב רוחב כולל מכותרות הטבלה בפועל
        let totalWidth = 0;
        const headerCells = this.elements.headerTable.querySelectorAll('th.tm-header-cell');

        headerCells.forEach(th => {
            totalWidth += th.offsetWidth;
        });

        // הוספת מרווח קטן למניעת חיתוך
        totalWidth += 2;

        // עדכון רוחב שתי הטבלאות - כולל min-width כדי שעמודות לא יתכווצו
        // ה-fixedContainer עם overflow:hidden מונע הרחבת הדף
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