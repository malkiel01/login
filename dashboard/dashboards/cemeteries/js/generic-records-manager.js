/**
 * generic-records-manager.js
 * מערכת גנרית לניהול כל סוגי הרשומות במערכת
 * תומכת בחיפוש, ייצוא, דוחות ופעולות נוספות
 */

class GenericRecordsManager {
    constructor(config) {
        this.config = config;
        this.currentData = [];
        this.searchInstance = null;
        this.tableInstance = null;
        this.editingItemId = null;
        
        console.log(`✅ GenericRecordsManager created for: ${config.type}`);
    }
    
    /**
     * טעינה ראשונית
     */
    async load() {
        console.log(`📋 Loading ${this.config.title}...`);
        
        // עדכון תצוגה
        this.updateUI();
        
        // בניית מבנה
        await this.buildContainer();
        
        // אתחול חיפוש
        if (this.config.features.search) {
            await this.initSearch();
        } else {
            await this.loadData();
        }
        
        // טעינת סטטיסטיקות
        if (this.config.features.stats) {
            await this.loadStats();
        }
    }
    
    /**
     * עדכון UI (sidebar, breadcrumb, title)
     */
    updateUI() {
        // Sidebar
        if (typeof setActiveMenuItem === 'function') {
            setActiveMenuItem(this.config.menuItemId);
        }
        
        // משתני מערכת
        window.currentType = this.config.type;
        window.currentParentId = null;
        
        // ניקוי
        if (typeof DashboardCleaner !== 'undefined') {
            DashboardCleaner.clear({ targetLevel: this.config.type });
        }
        
        // Sidebar selections
        if (typeof clearAllSidebarSelections === 'function') {
            clearAllSidebarSelections();
        }
        
        // כפתור הוספה
        if (typeof updateAddButtonText === 'function') {
            updateAddButtonText();
        }
        
        // Breadcrumb
        if (typeof updateBreadcrumb === 'function') {
            const breadcrumbData = {};
            breadcrumbData[this.config.type] = { name: this.config.title };
            updateBreadcrumb(breadcrumbData);
        }
        
        // כותרת דף
        document.title = `${this.config.title} - מערכת בתי עלמין`;
    }
    
    /**
     * בניית מבנה ה-container
     */
    async buildContainer() {
        console.log(`🏗️ Building ${this.config.type} container...`);
        
        let mainContainer = document.querySelector('.main-container');
        
        if (!mainContainer) {
            console.log('⚠️ main-container not found, creating one...');
            const mainContent = document.querySelector('.main-content');
            mainContainer = document.createElement('div');
            mainContainer.className = 'main-container';
            
            const actionBar = mainContent.querySelector('.action-bar');
            if (actionBar) {
                actionBar.insertAdjacentElement('afterend', mainContainer);
            } else {
                mainContent.appendChild(mainContainer);
            }
        }
        
        // בניית HTML
        let html = '';
        
        // סקשן חיפוש
        if (this.config.features.search) {
            html += `<div id="${this.config.type}SearchSection" class="search-section"></div>`;
        }
        
        // כפתורי פעולה (ייצוא, דוחות)
        if (this.config.features.export || this.config.features.reports) {
            html += this.buildActionButtons();
        }
        
        // טבלה
        html += `
            <div class="table-container">
                <table id="mainTable" class="data-table">
                    <thead>
                        <tr id="tableHeaders">
                            <th style="text-align: center;">טוען...</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td style="text-align: center; padding: 40px;">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">טוען ${this.config.title}...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
        
        mainContainer.innerHTML = html;
        
        console.log(`✅ ${this.config.type} container built`);
    }
    
    /**
     * בניית כפתורי פעולה
     */
    buildActionButtons() {
        let buttons = '<div class="action-buttons" style="padding: 10px 0; display: flex; gap: 10px;">';
        
        if (this.config.features.export) {
            buttons += `
                <button class="btn btn-success" onclick="window.${this.config.type}Manager.exportData()">
                    <i class="fas fa-file-excel"></i> ייצוא לאקסל
                </button>
            `;
        }
        
        if (this.config.features.reports) {
            buttons += `
                <button class="btn btn-info" onclick="window.${this.config.type}Manager.generateReport()">
                    <i class="fas fa-file-pdf"></i> יצירת דוח
                </button>
            `;
        }
        
        buttons += '</div>';
        return buttons;
    }
    
    /**
     * אתחול מנוע חיפוש
     */
    async initSearch() {
        this.searchInstance = new UniversalSearch({
            dataSource: {
                type: 'api',
                endpoint: `${this.config.api.endpoint}&type=${this.config.type}`,
                action: 'list',
                method: 'GET',
                tables: [this.config.api.table],
                joins: []
            },
            
            searchableFields: this.config.searchFields,
            
            display: {
                containerSelector: `#${this.config.type}SearchSection`,
                showAdvanced: true,
                showFilters: true,
                placeholder: this.config.searchPlaceholder,
                layout: 'horizontal',
                minSearchLength: 0
            },
            
            results: {
                containerSelector: '#tableBody',
                itemsPerPage: 10000,
                showPagination: false,
                showCounter: true,
                columns: this.config.columns.map(c => c.field),
                renderFunction: (data, container) => this.renderRows(data, container)
            },
            
            behavior: {
                realTime: true,
                autoSubmit: true,
                highlightResults: true
            },
            
            callbacks: {
                onInit: () => {
                    console.log(`✅ UniversalSearch initialized for ${this.config.type}`);
                },
                
                onSearch: (query, filters) => {
                    console.log('🔍 Searching:', { query, filters: Array.from(filters.entries()) });
                },
                
                onResults: (data) => {
                    console.log(`📦 Results: ${data.pagination?.total || data.total || 0} found`);
                    this.currentData = data.data;
                },
                
                onError: (error) => {
                    console.error('❌ Search error:', error);
                    this.showToast('שגיאה בחיפוש: ' + error.message, 'error');
                },
                
                onEmpty: () => {
                    console.log('📭 No results');
                }
            }
        });
        
        this.searchInstance.search();
        return this.searchInstance;
    }
    
    /**
     * טעינת נתונים ללא חיפוש
     */
    async loadData() {
        try {
            const response = await fetch(`${this.config.api.endpoint}&type=${this.config.type}&action=list`);
            const data = await response.json();
            
            if (data.success) {
                this.currentData = data.data;
                this.renderRows(data.data, document.getElementById('tableBody'));
            } else {
                throw new Error(data.error || 'שגיאה בטעינת נתונים');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            this.showToast('שגיאה בטעינת נתונים', 'error');
        }
    }
    
    /**
     * רינדור שורות הטבלה
     */
    renderRows(data, container) {
        console.log(`🎨 renderRows called with ${data.length} items`);
        
        if (data.length === 0) {
            if (this.tableInstance) {
                this.tableInstance.setData([]);
            }
            
            container.innerHTML = `
                <tr>
                    <td colspan="20" style="text-align: center; padding: 60px;">
                        <div style="color: #9ca3af;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                            <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        // בדיקת DOM
        const tableWrapperExists = document.querySelector('.table-wrapper[data-fixed-width="true"]');
        
        if (!tableWrapperExists && this.tableInstance) {
            console.log('🗑️ TableManager DOM was deleted, resetting');
            this.tableInstance = null;
        }
        
        if (!this.tableInstance || !tableWrapperExists) {
            console.log(`✅ Creating new TableManager with ${data.length} items`);
            this.initTable(data);
        } else {
            console.log(`🔄 Updating existing TableManager with ${data.length} items`);
            this.tableInstance.setData(data);
        }
    }
    
    /**
     * אתחול טבלה
     */
    initTable(data) {
        if (this.tableInstance) {
            this.tableInstance.setData(data);
            return this.tableInstance;
        }
        
        this.tableInstance = new TableManager({
            tableSelector: '#mainTable',
            containerWidth: '100%',
            fixedLayout: true,
            
            scrolling: {
                enabled: true,
                headerHeight: '50px',
                itemsPerPage: 50,
                scrollThreshold: 300
            },
            
            columns: this.buildTableColumns(),
            data: data,
            
            sortable: true,
            resizable: true,
            reorderable: false,
            filterable: true,
            
            onSort: (field, order) => {
                console.log(`📊 Sorted by ${field} ${order}`);
                this.showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
            },
            
            onFilter: (filters) => {
                console.log('🔍 Active filters:', filters);
                const count = this.tableInstance.getFilteredData().length;
                this.showToast(`נמצאו ${count} תוצאות`, 'info');
            }
        });
        
        console.log(`📊 Total loaded: ${data.length}`);
        return this.tableInstance;
    }
    
    /**
     * בניית עמודות הטבלה
     */
    buildTableColumns() {
        const columns = [];
        
        // עמודת אינדקס
        columns.push({
            field: 'index',
            label: 'מס׳',
            width: '60px',
            type: 'index',
            sortable: false
        });
        
        // עמודות מהקונפיג
        this.config.columns.forEach(col => {
            const column = {
                field: col.field,
                label: col.label,
                width: col.width || '150px',
                type: col.type || 'text',
                sortable: col.sortable !== false
            };
            
            // פונקציית render מותאמת אישית
            if (col.render) {
                column.render = col.render;
            } else if (col.secondaryField) {
                column.render = (item) => {
                    const main = item[col.field] || '';
                    const secondary = item[col.secondaryField] ? 
                        `<div style="font-size: 0.85em; color: #6b7280;">${col.secondaryIcon || ''}${item[col.secondaryField]}</div>` : '';
                    return main + secondary;
                };
            }
            
            columns.push(column);
        });
        
        // עמודת פעולות
        if (this.config.features.actions) {
            columns.push({
                field: 'actions',
                label: 'פעולות',
                width: '180px',
                sortable: false,
                render: (item) => this.renderActions(item)
            });
        }
        
        return columns;
    }
    
    /**
     * רינדור כפתורי פעולות
     */
    renderActions(item) {
        const itemId = item[this.config.api.primaryKey] || item.unicId || item.id;
        const itemName = item[this.config.nameField] || '';
        
        let html = '';
        
        // עריכה
        if (this.config.features.edit) {
            html += `
                <button class="btn btn-sm btn-secondary" 
                        onclick="window.${this.config.type}Manager.edit('${itemId}')" 
                        title="עריכה">
                    <i class="fas fa-edit"></i>
                </button>
            `;
        }
        
        // מחיקה
        if (this.config.features.delete) {
            html += `
                <button class="btn btn-sm btn-danger" 
                        onclick="window.${this.config.type}Manager.delete('${itemId}')" 
                        title="מחיקה">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        }
        
        // כניסה (אם יש children)
        if (this.config.features.open) {
            html += `
                <button class="btn btn-sm btn-primary" 
                        onclick="window.${this.config.type}Manager.open('${itemId}', '${itemName}')" 
                        title="פתח">
                    <i class="fas fa-folder-open"></i> כניסה
                </button>
            `;
        }
        
        return html;
    }
    
    /**
     * פעולות CRUD
     */
    async edit(id) {
        console.log(`✏️ Editing ${this.config.type}:`, id);
        this.editingItemId = id;
        this.showToast('עריכה בפיתוח...', 'info');
    }
    
    async delete(id) {
        if (!confirm(`האם אתה בטוח שברצונך למחוק ${this.config.singular} זה?`)) {
            return;
        }
        
        try {
            const response = await fetch(
                `${this.config.api.endpoint}&type=${this.config.type}&action=delete&id=${id}`,
                { method: 'DELETE' }
            );
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast(`${this.config.singular} נמחק בהצלחה`, 'success');
                this.refresh();
            } else {
                throw new Error(data.error || 'שגיאה במחיקה');
            }
        } catch (error) {
            console.error('Error deleting:', error);
            this.showToast('שגיאה במחיקה', 'error');
        }
    }
    
    async open(id, name) {
        console.log(`📂 Opening ${this.config.type}:`, id, name);
        
        if (this.config.onOpen) {
            this.config.onOpen(id, name);
        } else {
            this.showToast('פתיחה בפיתוח...', 'info');
        }
    }
    
    /**
     * ייצוא לאקסל
     */
    async exportData() {
        console.log('📊 Exporting data to Excel...');
        this.showToast('ייצוא לאקסל בפיתוח...', 'info');
    }
    
    /**
     * יצירת דוח
     */
    async generateReport() {
        console.log('📄 Generating report...');
        this.showToast('יצירת דוח בפיתוח...', 'info');
    }
    
    /**
     * רענון נתונים
     */
    async refresh() {
        if (this.searchInstance) {
            this.searchInstance.refresh();
        } else {
            await this.loadData();
        }
    }
    
    /**
     * טעינת סטטיסטיקות
     */
    async loadStats() {
        try {
            const response = await fetch(
                `${this.config.api.endpoint}&type=${this.config.type}&action=stats`
            );
            const data = await response.json();
            
            if (data.success) {
                console.log(`${this.config.type} stats:`, data.data);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    /**
     * הצגת Toast
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
        `;
        
        toast.innerHTML = `
            <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// ============================================
// Helper Functions
// ============================================

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

function formatNumber(num) {
    return num ? num.toLocaleString('he-IL') : '0';
}

function formatCurrency(amount) {
    return amount ? `₪${amount.toLocaleString('he-IL')}` : '₪0';
}

console.log('✅ GenericRecordsManager Class Loaded');