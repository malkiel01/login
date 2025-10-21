/**
 * TableManager - עדכון: תמיכה בהגדרת רוחב דינמי
 * 
 * שימוש:
 * 
 * new TableManager({
 *     tableSelector: '#mainTable',
 *     containerWidth: '100%',      // או '500px', '80vw', 'auto'
 *     containerPadding: '16px',    // או '0', '20px 10px'
 *     columns: [...],
 *     data: [...]
 * });
 */

class TableManager {
    constructor(config) {
        this.config = {
            tableSelector: null,
            columns: [],
            data: [],
            
            // ⭐ הגדרות רוחב חדשות
            containerWidth: '100%',      // ברירת מחדל: תופס את כל הרוחב
            containerPadding: '16px',    // ברירת מחדל: padding סביב
            
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
        
        // ... שאר הקוד זהה
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
    
    init() {
        this.elements.table = document.querySelector(this.config.tableSelector);
        
        if (!this.elements.table) {
            console.error('Table not found:', this.config.tableSelector);
            return;
        }
        
        this.state.columnOrder = this.config.columns.map((col, index) => index);
        
        this.config.columns.forEach((col, index) => {
            this.state.columnWidths[index] = col.width || 'auto';
        });
        
        this.buildTable();
        this.bindEvents();
        
        if (this.config.infiniteScroll) {
            this.initInfiniteScroll();
        }
        
        console.log('✅ TableManager initialized');
        console.log('📐 Container width:', this.config.containerWidth);
        console.log('📦 Container padding:', this.config.containerPadding);
    }
    
    /**
     * בניית מבנה הטבלה - עם תמיכה ברוחב דינמי
     */
    buildTable() {
        console.log('🏗️ Building table structure...');
        
        let parent = this.elements.table.parentNode;
        let currentParent = parent;
        let fixed = [];
        
        while (currentParent && currentParent !== document.body) {
            const styles = window.getComputedStyle(currentParent);
            
            // ⭐ שימוש בפרמטרים מה-config
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
                `.trim());
                fixed.push('table-container');
            }
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
        
        // צור את המבנה החדש
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        wrapper.setAttribute('data-fixed-width', 'true');
        
        wrapper.setAttribute('style', `
            display: flex !important; 
            flex-direction: column !important; 
            width: 100% !important; 
            height: calc(100vh - 250px) !important; 
            min-height: 500px !important; 
            border: 1px solid #e5e7eb !important; 
            border-radius: 8px !important; 
            overflow: hidden !important; 
            background: white !important; 
            position: relative !important; 
            box-sizing: border-box !important;
        `.trim());
        
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
        
        const bodyContainer = document.createElement('div');
        bodyContainer.className = 'table-body-container';
        bodyContainer.style.cssText = `
            flex: 1 !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
            position: relative !important;
            height: 100% !important;
        `;
        
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
        
        wrapper.appendChild(headerContainer);
        wrapper.appendChild(bodyContainer);
        
        parent.insertBefore(wrapper, this.elements.table);
        this.elements.table.style.display = 'none';
        
        this.elements.wrapper = wrapper;
        this.elements.headerContainer = headerContainer;
        this.elements.bodyContainer = bodyContainer;
        this.elements.headerTable = headerTable;
        this.elements.bodyTable = bodyTable;
        this.elements.thead = thead;
        this.elements.tbody = tbody;
        
        this.syncHorizontalScroll();
        this.renderHeaders();
        this.loadInitialData();
        
        console.log('🎉 Table structure complete!');
    }
    
    // ... שאר הפונקציות נשארות זהות (לא משתנות)
}

window.TableManager = TableManager;