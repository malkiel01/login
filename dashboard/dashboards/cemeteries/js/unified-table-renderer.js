// dashboard/dashboards/cemeteries/js/unified-table-renderer.js
// מערכת תצוגה אחידה המבוססת על הקונפיג המרכזי

class UnifiedTableRenderer {
    constructor() {
        this.config = null;
        this.currentType = 'cemetery';
        this.currentData = [];
    }
    
    /**
     * טעינת הקונפיג מהשרת
     */
    async loadConfig(type) {
        try {
            const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get_config&type=${type}`);
            const data = await response.json();
            
            if (data.success) {
                this.config = data.config;
                this.currentType = type;
                return true;
            }
        } catch (error) {
            console.error('Error loading config:', error);
        }
        return false;
    }
    
    /**
     * טעינת נתונים וציור טבלה
     */
    async loadAndDisplay(type, parentId = null) {
        // טען קונפיג אם צריך
        if (!this.config || this.currentType !== type) {
            await this.loadConfig(type);
        }
        
        // טען נתונים
        const data = await this.loadData(type, parentId);
        if (data) {
            this.displayTable(data);
        }
    }
    
    /**
     * טעינת נתונים מהשרת
     */
    async loadData(type, parentId = null) {
        try {
            let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=${type}`;
            if (parentId) {
                url += `&parent_id=${parentId}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.currentData = data.data;
                return data.data;
            }
        } catch (error) {
            console.error('Error loading data:', error);
            showError('שגיאה בטעינת נתונים');
        }
        return null;
    }
    
    /**
     * ציור הטבלה על פי הקונפיג
     */
    displayTable(data) {
        const tbody = document.getElementById('tableBody');
        const thead = document.getElementById('tableHeaders');
        
        if (!tbody || !thead || !this.config) return;
        
        // ציור כותרות
        this.renderHeaders(thead);
        
        // ציור שורות
        this.renderRows(tbody, data);
        
        // עדכון breadcrumb
        updateBreadcrumb(window.selectedItems);
        
        // עדכון כפתור הוספה
        updateAddButtonText();
    }
    
    /**
     * ציור כותרות הטבלה
     */
    renderHeaders(thead) {
        let html = '';
        
        this.config.table_columns.forEach(column => {
            const width = column.width ? `style="width: ${column.width}"` : '';
            html += `<th ${width}>${column.title}</th>`;
        });
        
        thead.innerHTML = html;
    }
    
    /**
     * ציור שורות הטבלה
     */
    renderRows(tbody, data) {
        tbody.innerHTML = '';
        
        // אם אין נתונים
        if (data.length === 0) {
            this.renderEmptyState(tbody);
            return;
        }
        
        // ציור כל שורה
        data.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            
            // קבל את ה-unicId או id
            const itemId = item.unicId || item.id;
            const itemName = this.getItemName(item);
            
            // הגדר אירועים
            tr.ondblclick = () => this.openItem(itemId, itemName);
            tr.onclick = () => selectTableRow(tr);
            
            // ציור העמודות
            let html = '';
            this.config.table_columns.forEach(column => {
                html += this.renderCell(column, item, index);
            });
            
            tr.innerHTML = html;
            tbody.appendChild(tr);
        });
    }
    
    /**
     * ציור תא בטבלה
     */
    renderCell(column, item, index) {
        let content = '';
        
        switch (column.type) {
            case 'index':
                content = index + 1;
                break;
                
            case 'actions':
                content = this.renderActions(item);
                break;
                
            case 'status':
                const value = item[column.field];
                if (column.badges && column.badges[value]) {
                    content = `<span class="badge ${column.badges[value].class}">
                              ${column.badges[value].text}</span>`;
                } else {
                    content = value;
                }
                break;
                
            case 'date':
                content = formatDate(item[column.field]);
                break;
                
            case 'currency':
                const amount = item[column.field];
                content = amount ? `₪${Number(amount).toLocaleString()}` : '-';
                break;
                
            case 'boolean':
                const boolValue = item[column.field];
                if (column.icons) {
                    content = column.icons[boolValue] || '-';
                } else {
                    content = boolValue ? 'כן' : 'לא';
                }
                break;
                
            case 'select':
                const selectValue = item[column.field];
                if (column.options) {
                    content = column.options[selectValue] || selectValue;
                } else {
                    content = selectValue;
                }
                break;
                
            default:
                content = item[column.field] || '-';
                
                // הוספת שדה משני אם מוגדר
                if (column.show_secondary && item[column.show_secondary]) {
                    const icon = column.icon_secondary || '';
                    content += `<br><small class="text-muted">${icon} ${item[column.show_secondary]}</small>`;
                }
        }
        
        // עטוף בתא
        return `<td>${content}</td>`;
    }
    
    /**
     * ציור כפתורי פעולות
     */
    renderActions(item) {
        const itemId = item.unicId || item.id;
        const itemName = this.getItemName(item);
        const type = this.currentType;
        
        let html = '';
        
        // כפתור עריכה
        if (this.config.permissions.can_edit) {
            html += `
                <button class="btn btn-sm btn-secondary" 
                        onclick="event.stopPropagation(); tableRenderer.editItem('${itemId}')">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
            `;
        }
        
        // כפתור מחיקה
        if (this.config.permissions.can_delete) {
            html += `
                <button class="btn btn-sm btn-danger" 
                        onclick="event.stopPropagation(); tableRenderer.deleteItem('${itemId}')">
                    <svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>
                </button>
            `;
        }
        
        // כפתור כניסה (לא לקברים)
        if (type !== 'grave') {
            html += `
                <button class="btn btn-sm btn-primary" 
                        onclick="event.stopPropagation(); tableRenderer.openItem('${itemId}', '${itemName}')">
                    <svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg>
                    כניסה
                </button>
            `;
        }
        
        return html;
    }
    
    /**
     * מצב ריק
     */
    renderEmptyState(tbody) {
        const typeName = this.config.title || 'פריטים';
        const icon = this.config.icon || '📄';
        const parentName = this.getParentName();
        
        let html = `
            <tr>
                <td colspan="${this.config.table_columns.length}" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">${icon}</div>
                        <div>אין ${typeName} ${parentName ? `ב${parentName}` : 'במערכת'}</div>
        `;
        
        // כפתור הוספה אם יש הרשאה
        if (this.config.permissions.can_create && window.currentParentId) {
            html += `
                <button class="btn btn-primary mt-3" 
                        onclick="tableRenderer.addItem()">
                    הוסף ${this.config.singular}
                </button>
            `;
        }
        
        html += `
                    </div>
                </td>
            </tr>
        `;
        
        tbody.innerHTML = html;
    }
    
    /**
     * קבלת שם הפריט
     */
    getItemName(item) {
        // נסה לפי displayFields בקונפיג
        if (this.config.displayFields && this.config.displayFields.name) {
            return item[this.config.displayFields.name] || item.name || '';
        }
        
        // נסה שדות סטנדרטיים
        const nameFields = [
            'cemeteryNameHe', 'blockNameHe', 'plotNameHe', 
            'lineNameHe', 'areaGraveNameHe', 'graveNameHe',
            'name', 'grave_number'
        ];
        
        for (let field of nameFields) {
            if (item[field]) return item[field];
        }
        
        return 'ללא שם';
    }
    
    /**
     * קבלת שם ההורה
     */
    getParentName() {
        const parentMap = {
            'block': window.selectedItems.cemetery?.name,
            'plot': window.selectedItems.block?.name,
            'row': window.selectedItems.plot?.name,
            'area_grave': window.selectedItems.row?.name,
            'grave': window.selectedItems.areaGrave?.name
        };
        
        return parentMap[this.currentType] || '';
    }

    // פתיחת טופס הוספה - הפונקציה הראשית
    openAddModal() {
        const type = this.currentType || window.currentType;
        const parentId = window.currentParentId;
        
        console.log('openAddModal - type:', type, 'parentId:', parentId);
        
        if (!type) {
            console.error('No type defined');
            return;
        }
        
        // בדוק אם צריך לבחור הורה קודם
        if (!parentId && type !== 'cemetery') {
            this.openParentSelectionDialog(type);
            return;
        }
        
        // פתח את הטופס ישירות
        FormHandler.openForm(type, parentId, null);
    }
    
    /**
     * פתיחת פריט
     */
    openItem(itemId, itemName) {
        console.log('Opening item:', this.currentType, itemId, itemName);
        
        // שמור בחירה
        window.selectedItems[this.currentType] = {
            id: itemId,
            name: itemName
        };
        
        // שמור את ה-unicId הספציפי
        switch(this.currentType) {
            case 'cemetery':
                window.currentCemeteryId = itemId;
                break;
            case 'block':
                window.currentBlockId = itemId;
                break;
            case 'plot':
                window.currentPlotId = itemId;
                break;
            case 'row':
                window.currentRowId = itemId;
                break;
            case 'area_grave':
                window.currentAreaGraveId = itemId;
                break;
        }
        
        // קבע את הסוג הבא בהיררכיה
        const nextType = this.getNextType();
        if (nextType) {
            window.currentType = nextType;
            window.currentParentId = itemId;  // זה ה-unicId
            
            // עדכן סידבר
            updateSidebarSelection(this.currentType, itemId, itemName);
            
            // טען את הרמה הבאה
            this.loadAndDisplay(nextType, itemId);
        }
    }
    
    /**
     * קבלת הסוג הבא בהיררכיה
     */
    getNextType() {
        const hierarchy = {
            'cemetery': 'block',
            'block': 'plot',
            'plot': 'area_grave',
            'area_grave': 'grave',
            'grave': null
        };
        
        return hierarchy[this.currentType];
    }
    
    /**
     * הוספת פריט חדש
     */
    // addItem() {
    //     const type = this.currentType;
    //     const parentId = window.currentParentId;
        
    //     console.log('addItem - type:', type, 'parentId:', parentId);
        
    //     // קריאה פשוטה עם פרמטרים
    //     FormHandler.openForm(type, parentId, null);
    // }

    // הוסף את הפונקציות האלה בתוך ה-class UnifiedTableRenderer:

// פונקציה לקבלת סוג ההורה
    getParentType(type) {
        const hierarchy = {
            'block': 'cemetery',
            'plot': 'block',
            'row': 'plot',
            'area_grave': 'row',
            'grave': 'area_grave'
        };
        return hierarchy[type];
    }

    // פונקציה לקבלת שם ההורה בעברית
    getParentTypeName(parentType) {
        const names = {
            'cemetery': 'בית עלמין',
            'block': 'גוש',
            'plot': 'חלקה',
            'row': 'שורה',
            'area_grave': 'אחוזת קבר'
        };
        return names[parentType] || 'פריט הורה';
    }

    // פונקציה להצגת הודעה
    showMessage(message, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(type, message);
        } else if (typeof showError === 'function' && type === 'error') {
            showError(message);
        } else if (typeof showSuccess === 'function' && type === 'success') {
            showSuccess(message);
        } else {
            alert(message);
        }
    }


    addItem() {
        const type = this.currentType;
        const parentId = window.currentParentId;
        
        console.log('addItem - type:', type, 'parentId:', parentId);
        console.log('Type is not cemetery?', type !== 'cemetery');
        console.log('No parentId?', !parentId);
        console.log('Should open dialog?', !parentId && type !== 'cemetery');
        
        // בדוק אם צריך לבחור הורה קודם
        if (!parentId && type !== 'cemetery') {
            console.log('Opening parent selection dialog...');
            // פתח דיאלוג בחירת הורה
            this.openParentSelectionDialog(type);
            return;
        }
        
        console.log('Opening form directly...');
        FormHandler.openForm(type, parentId, null);
    }

    // async openParentSelectionDialog2(type) {
    //     console.log('test111');
        
    //     const parentType = this.getParentType(type);
        
    //     // טען רשימת הורים אפשריים
    //     const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=${parentType}`);
    //     const data = await response.json();
        
    //     if (data.success && data.data.length > 0) {
    //         // יצירת דיאלוג בחירה
    //         const modal = document.createElement('div');
    //         modal.className = 'modal show';
    //         modal.style.display = 'flex';
    //         modal.innerHTML = `
    //             <div class="modal-dialog">
    //                 <div class="modal-content">
    //                     <div class="modal-header">
    //                         <h5>בחר ${this.getParentTypeName(parentType)}</h5>
    //                         <button onclick="this.closest('.modal').remove()">×</button>
    //                     </div>
    //                     <div class="modal-body">
    //                         <select id="parentSelector" class="form-control">
    //                             <option value="">בחר...</option>
    //                             ${data.data.map(item => 
    //                                 `<option value="${item.unicId}">${item[this.config.displayFields.name]}</option>`
    //                             ).join('')}
    //                         </select>
    //                     </div>
    //                     <div class="modal-footer">
    //                         <button class="btn btn-primary" onclick="
    //                             const selected = document.getElementById('parentSelector').value;
    //                             if(selected) {
    //                                 window.currentParentId = selected;
    //                                 FormHandler.openForm('${type}', selected, null);
    //                                 this.closest('.modal').remove();
    //                             }
    //                         ">המשך</button>
    //                     </div>
    //                 </div>
    //             </div>
    //         `;
    //         document.body.appendChild(modal);
    //     } else {
    //         this.showMessage('אין פריטי הורה זמינים', 'error');
    //     }
    // }
    async openParentSelectionDialog(type) {
        console.log('Opening parent selection dialog for type:', type);
        
        const parentType = this.getParentType(type);
        
        // טען רשימת הורים אפשריים
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=${parentType}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            // קבע את שדה השם הנכון לפי הסוג
            const nameFields = {
                'cemetery': 'cemeteryNameHe',
                'block': 'blockNameHe',
                'plot': 'plotNameHe',
                'row': 'lineNameHe',
                'area_grave': 'areaGraveNameHe'
            };
            
            const nameField = nameFields[parentType] || 'name';
            
            // יצירת דיאלוג בחירה
            const modal = document.createElement('div');
            modal.className = 'modal show';
            modal.style.display = 'flex';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.zIndex = '10000';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
            
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>בחר ${this.getParentTypeName(parentType)}</h5>
                            <button onclick="this.closest('.modal').remove()">×</button>
                        </div>
                        <div class="modal-body">
                            <select id="parentSelector" class="form-control">
                                <option value="">בחר...</option>
                                ${data.data.map(item => 
                                    `<option value="${item.unicId || item.id}">${item[nameField] || item.name || 'ללא שם'}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary" onclick="
                                const selected = document.getElementById('parentSelector').value;
                                if(selected) {
                                    window.currentParentId = selected;
                                    FormHandler.openForm('${type}', selected, null);
                                    this.closest('.modal').remove();
                                }
                            ">המשך</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            this.showMessage('אין פריטי הורה זמינים', 'error');
        }
    }
    
    /**
     * עריכת פריט
     */
    editItem(itemId) {
        const type = this.currentType;
        const parentId = window.currentParentId;
        
        console.log('editItem - type:', type, 'itemId:', itemId);
        
        // קריאה פשוטה עם פרמטרים
        FormHandler.openForm(type, parentId, itemId);
    }
    
    /**
     * מחיקת פריט
     */
    async deleteItem(itemId) {
        if (!confirm(`האם אתה בטוח שברצונך למחוק ${this.config.singular} זה?`)) return;
        
        try {
            const response = await fetch(
                `${API_BASE}cemetery-hierarchy.php?action=delete&type=${this.currentType}&id=${itemId}`,
                { method: 'DELETE' }
            );
            
            const data = await response.json();
            
            if (data.success) {
                showSuccess(`ה${this.config.singular} נמחק בהצלחה`);
                // רענן תצוגה
                this.loadAndDisplay(this.currentType, window.currentParentId);
            } else {
                showError(data.error || 'שגיאה במחיקה');
            }
        } catch (error) {
            console.error('Error deleting item:', error);
            showError('שגיאה במחיקת הפריט');
        }
    }
}

// יצירת מופע גלובלי
window.tableRenderer = new UnifiedTableRenderer();

// פונקציות גלובליות לתאימות לאחור
window.loadAllCemeteries = async function() {
    window.currentType = 'cemetery';
    window.currentParentId = null;
    clearAllSidebarSelections();
    await tableRenderer.loadAndDisplay('cemetery');
};

window.loadAllBlocks = async function() {
    window.currentType = 'block';
    window.currentParentId = null;
    clearAllSidebarSelections();
    await tableRenderer.loadAndDisplay('block');
};

window.loadAllPlots = async function() {
    window.currentType = 'plot';
    window.currentParentId = null;
    clearAllSidebarSelections();
    await tableRenderer.loadAndDisplay('plot');
};

window.loadAllAreaGraves = async function() {
    window.currentType = 'area_grave';
    window.currentParentId = null;
    clearAllSidebarSelections();
    await tableRenderer.loadAndDisplay('area_grave');
};

window.loadAllGraves = async function() {
    window.currentType = 'grave';
    window.currentParentId = null;
    clearAllSidebarSelections();
    await tableRenderer.loadAndDisplay('grave');
};

// פונקציות לטעינה עם הורה
window.loadBlocksForCemetery = async function(cemeteryId) {
    window.currentCemeteryId = cemeteryId;  // הוסף את זה
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    await tableRenderer.loadAndDisplay('block', cemeteryId);
};

window.loadPlotsForBlock = async function(blockId) {
    window.currentBlockId = blockId;  // הוסף את זה
    window.currentType = 'plot';
    window.currentParentId = blockId;
    await tableRenderer.loadAndDisplay('plot', blockId);
};

window.loadAreaGravesForPlot = async function(plotId) {
    window.currentPlotId = plotId;  // הוסף את זה
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
    await tableRenderer.loadAndDisplay('area_grave', plotId);
};

window.loadGravesForAreaGrave = async function(areaGraveId) {
    window.currentAreaGraveId = areaGraveId;  // הוסף את זה
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    await tableRenderer.loadAndDisplay('grave', areaGraveId);
};