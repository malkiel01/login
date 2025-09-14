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
        // נקה כרטיסים קיימים
        if (typeof clearAllHierarchyCards === 'function') {
            clearAllHierarchyCards();
        }

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
            // let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=${type}`;
            // if (parentId) {
            //     url += `&parent_id=${parentId}`;
            // }
            let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=${type}`;
            if (parentId) {
                // עבור אחוזות קבר, שלח plot_id במקום parent_id
                if (type === 'area_grave') {
                    url += `&plot_id=${parentId}`;
                } else {
                    url += `&parent_id=${parentId}`;
                }
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
        
        // הסרנו את updateBreadcrumb מכאן! זה כבר נקרא במקום אחר
        
        // עדכון כפתור הוספה
        if (typeof updateAddButtonText === 'function') {
            updateAddButtonText();
        }
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
     * פתיחת פריט - כניסה לרמה הבאה
     */    /**
     * קבלת סוג הילד
     */
    getChildType() {
        const hierarchy = {
            'cemetery': 'block',
            'block': 'plot',
            'plot': 'area_grave',
            'area_grave': 'grave',
            'grave': null
        };
        
        return hierarchy[this.currentType];
    }
    
    // openItem2(itemId, itemName) {
    //     const nextType = this.getChildType();
    //     if (!nextType) return;
        
    //     // עדכן את הבחירה הגלובלית
    //     window.selectedItems[this.currentType] = { 
    //         id: itemId, 
    //         name: itemName 
    //     };
        
    //     // עדכן משתנים גלובליים
    //     window.currentType = nextType;
    //     window.currentParentId = itemId;
        
    //     // שמור ID ספציפי לכל רמה (לתאימות אחורה)
    //     const idMapping = {
    //         'cemetery': 'currentCemeteryId',
    //         'block': 'currentBlockId',
    //         'plot': 'currentPlotId',
    //         'area_grave': 'currentAreaGraveId'
    //     };
        
    //     if (idMapping[this.currentType]) {
    //         window[idMapping[this.currentType]] = itemId;
    //     }
        
    //     // עדכן את ה-Breadcrumb פעם אחת
    //     if (window.BreadcrumbManager) {
    //         window.BreadcrumbManager.update(window.selectedItems);
    //     }
        
    //     // טען את הנתונים הבאים
    //     this.loadAndDisplay(nextType, itemId);
    // }

    openItem(itemId, itemName) {
        const nextType = this.getChildType();
        if (!nextType) return;
        
        // עדכן את הבחירה הגלובלית
        window.selectedItems[this.currentType] = { 
            id: itemId, 
            name: itemName 
        };
        
        // עדכן משתנים גלובליים
        window.currentType = nextType;
        window.currentParentId = itemId;
        
        // שמור ID ספציפי לכל רמה (לתאימות אחורה)
        const idMapping = {
            'cemetery': 'currentCemeteryId',
            'block': 'currentBlockId',
            'plot': 'currentPlotId',
            'area_grave': 'currentAreaGraveId'
        };
        
        if (idMapping[this.currentType]) {
            window[idMapping[this.currentType]] = itemId;
        }
        
        // עדכן את ה-Breadcrumb פעם אחת
        if (window.BreadcrumbManager) {
            window.BreadcrumbManager.update(window.selectedItems);
        }
        
        // *** הוסף כאן: צור והצג את הכרטיס המתאים ***
        this.displayItemCard(this.currentType, itemId, itemName);
        
        // טען את הנתונים הבאים
        this.loadAndDisplay(nextType, itemId);
    }

    // /**
    //  * הצגת כרטיס לפריט שנבחר
    //  */
    // displayItemCard2(type, itemId, itemName) {
    //     let cardHtml = '';
        
    //     switch(type) {
    //         case 'cemetery':
    //             if (typeof createCemeteryCard === 'function') {
    //                 cardHtml = createCemeteryCard({ 
    //                     id: itemId, 
    //                     name: itemName 
    //                 });
    //             }
    //             break;
                
    //         case 'block':
    //             if (typeof createBlockCard === 'function') {
    //                 cardHtml = createBlockCard({ 
    //                     id: itemId, 
    //                     name: itemName 
    //                 });
    //             }
    //             break;
                
    //         case 'plot':
    //             if (typeof createPlotCard === 'function') {
    //                 cardHtml = createPlotCard({ 
    //                     id: itemId, 
    //                     name: itemName 
    //                 });
    //             }
    //             break;
                
    //         case 'area_grave':
    //             if (typeof createAreaGraveCard === 'function') {
    //                 cardHtml = createAreaGraveCard({ 
    //                     id: itemId, 
    //                     name: itemName 
    //                 });
    //             }
    //             break;
    //     }
        
    //     if (cardHtml && typeof displayHierarchyCard === 'function') {
    //         displayHierarchyCard(cardHtml);
    //     }
    // }
    /**
     * הצגת כרטיס לפריט שנבחר
     */
    displayItemCard(type, itemId, itemName) {
        // קרא לפונקציה המקורית מ-cards.js עם ID בלבד
        let cardPromise = null;
        
        switch(type) {
            case 'cemetery':
                cardPromise = createCemeteryCard(itemId); // שלח רק ID
                break;
            case 'block':
                cardPromise = createBlockCard(itemId); // שלח רק ID
                break;
            case 'plot':
                cardPromise = createPlotCard(itemId); // שלח רק ID
                break;
            case 'area_grave':
                cardPromise = createAreaGraveCard(itemId); // שלח רק ID
                break;
        }
        
        // טפל ב-Promise שמוחזר מהפונקציות האסינכרוניות
        if (cardPromise && cardPromise instanceof Promise) {
            cardPromise.then(cardHtml => {
                if (cardHtml && typeof displayHierarchyCard === 'function') {
                    displayHierarchyCard(cardHtml);
                }
            }).catch(error => {
                console.error('Error creating card:', error);
            });
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
            
            // יצירת דיאלוג בחירה עם עיצוב מלא
            const modal = document.createElement('div');
            modal.className = 'modal show';
            modal.id = 'parentSelectionModal';
            
            // הוסף CSS ישירות
            const style = document.createElement('style');
            style.textContent = `
                #parentSelectionModal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(0, 0, 0, 0.5);
                }
                
                #parentSelectionModal .modal-dialog {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    max-width: 500px;
                    width: 90%;
                    margin: 20px;
                }
                
                #parentSelectionModal .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 12px 12px 0 0;
                }
                
                #parentSelectionModal .modal-header h3 {
                    margin: 0;
                    font-size: 1.25rem;
                    font-weight: 600;
                }
                
                #parentSelectionModal .close-btn {
                    background: rgba(255,255,255,0.2);
                    border: none;
                    color: white;
                    width: 32px;
                    height: 32px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.3s;
                }
                
                #parentSelectionModal .close-btn:hover {
                    background: rgba(255,255,255,0.3);
                }
                
                #parentSelectionModal .modal-body {
                    padding: 25px;
                }
                
                #parentSelectionModal .modal-body p {
                    margin: 0 0 20px 0;
                    color: #64748b;
                    font-size: 0.95rem;
                }
                
                #parentSelectionModal .form-control {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 1rem;
                    direction: rtl;
                    transition: all 0.3s;
                }
                
                #parentSelectionModal .form-control:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                #parentSelectionModal .modal-footer {
                    padding: 20px;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: flex-end;
                    gap: 12px;
                }
                
                #parentSelectionModal .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                    font-size: 0.9rem;
                }
                
                #parentSelectionModal .btn-secondary {
                    background: #f1f5f9;
                    color: #475569;
                }
                
                #parentSelectionModal .btn-secondary:hover {
                    background: #e2e8f0;
                }
                
                #parentSelectionModal .btn-primary {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                }
                
                #parentSelectionModal .btn-primary:hover {
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                    transform: translateY(-1px);
                }
            `;
            document.head.appendChild(style);
            
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-header">
                        <h3>בחר ${this.getParentTypeName(parentType)}</h3>
                        <button class="close-btn" onclick="document.getElementById('parentSelectionModal').remove()">×</button>
                    </div>
                    <div class="modal-body">
                        <p>יש לבחור ${this.getParentTypeName(parentType)} להוספת ${this.getParentTypeName(type)}:</p>
                        <select id="parentSelector" class="form-control">
                            <option value="">-- בחר ${this.getParentTypeName(parentType)} --</option>
                            ${data.data.map(item => 
                                `<option value="${item.unicId || item.id}">${item[nameField] || item.name || 'ללא שם'}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('parentSelectionModal').remove()">
                            ביטול
                        </button>
                        <button class="btn btn-primary" onclick="
                            const selected = document.getElementById('parentSelector').value;
                            if(selected) {
                                window.currentParentId = selected;
                                FormHandler.openForm('${type}', selected, null);
                                document.getElementById('parentSelectionModal').remove();
                            } else {
                                alert('יש לבחור ${this.getParentTypeName(parentType)}');
                            }
                        ">
                            המשך
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        } else {
            this.showMessage('אין פריטי הורה זמינים', 'error');
        }
    }
    async openParentSelectionDialog2(type) {
        const parentType = this.getParentType(type);
        
        // במקום לבנות דיאלוג ידנית, פשוט:
        // 1. שמור את הנתונים בזיכרון
        window.pendingChildType = type;
        window.pendingParentType = parentType;
        
        // 2. פתח טופס רגיל מסוג parent_selector
        FormHandler.openForm('parent_selector', parentType, null);
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


// ==========================================
// פונקציות גלובליות - טעינת כל הפריטים
// ==========================================

window.loadAllCemeteries = async function() {
    console.log('📍 Loading all cemeteries');
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {};

    // נקה כרטיסים
    if (typeof clearAllHierarchyCards === 'function') {
        clearAllHierarchyCards();
    }
    
    DashboardCleaner.clear({ targetLevel: 'cemetery' });
    BreadcrumbManager.update({}, 'cemetery');
    await tableRenderer.loadAndDisplay('cemetery');
};

window.loadAllBlocks = async function() {
    console.log('📍 Loading all blocks');
    window.currentType = 'block';
    window.currentParentId = null;


    // נקה כרטיסים
    if (typeof clearAllHierarchyCards === 'function') {
        clearAllHierarchyCards();
    }

    // שמור רק את בית העלמין אם קיים
    const temp = window.selectedItems?.cemetery;
    window.selectedItems = {};
    if (temp) window.selectedItems.cemetery = temp;
    
    DashboardCleaner.clear({ targetLevel: 'block' });
    BreadcrumbManager.update({}, 'block');
    await tableRenderer.loadAndDisplay('block');
};

window.loadAllPlots = async function() {
    console.log('📍 Loading all plots');
    window.currentType = 'plot';
    window.currentParentId = null;

    // נקה כרטיסים
    if (typeof clearAllHierarchyCards === 'function') {
        clearAllHierarchyCards();
    }

    // שמור רק עד גוש
    const tempCemetery = window.selectedItems?.cemetery;
    const tempBlock = window.selectedItems?.block;
    window.selectedItems = {};
    if (tempCemetery) window.selectedItems.cemetery = tempCemetery;
    if (tempBlock) window.selectedItems.block = tempBlock;
    
    DashboardCleaner.clear({ targetLevel: 'plot' });
    BreadcrumbManager.update({}, 'plot');
    await tableRenderer.loadAndDisplay('plot');
};

window.loadAllAreaGraves = async function() {
    console.log('📍 Loading all area graves');
    window.currentType = 'area_grave';
    window.currentParentId = null;

    // נקה כרטיסים
    if (typeof clearAllHierarchyCards === 'function') {
        clearAllHierarchyCards();
    }

    // שמור עד חלקה
    const temp = { ...window.selectedItems };
    window.selectedItems = {};
    if (temp.cemetery) window.selectedItems.cemetery = temp.cemetery;
    if (temp.block) window.selectedItems.block = temp.block;
    if (temp.plot) window.selectedItems.plot = temp.plot;
    
    DashboardCleaner.clear({ targetLevel: 'area_grave' });
    BreadcrumbManager.update({}, 'area_grave');
    await tableRenderer.loadAndDisplay('area_grave');
};

window.loadAllGraves = async function() {
    console.log('📍 Loading all graves');
    window.currentType = 'grave';
    window.currentParentId = null;

    // נקה כרטיסים
    if (typeof clearAllHierarchyCards === 'function') {
        clearAllHierarchyCards();
    }
    
    // שמור עד אחוזת קבר
    const temp = { ...window.selectedItems };
    window.selectedItems = {};
    if (temp.cemetery) window.selectedItems.cemetery = temp.cemetery;
    if (temp.block) window.selectedItems.block = temp.block;
    if (temp.plot) window.selectedItems.plot = temp.plot;
    if (temp.areaGrave) window.selectedItems.areaGrave = temp.areaGrave;
    
    DashboardCleaner.clear({ targetLevel: 'grave' });
    BreadcrumbManager.update({}, 'grave');
    await tableRenderer.loadAndDisplay('grave');
};


// ==========================================
// פונקציות גלובליות - פתיחת פריט ספציפי
// ==========================================

window.openCemetery = function(cemeteryId, cemeteryName) {
    console.log('🏛️ Opening cemetery:', cemeteryId, cemeteryName);
    
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    window.currentCemeteryId = cemeteryId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען גושים
    tableRenderer.loadAndDisplay('block', cemeteryId);
};

window.openBlock = function(blockId, blockName) {
    console.log('📦 Opening block:', blockId, blockName);
    
    window.selectedItems.block = { id: blockId, name: blockName };
    window.currentType = 'plot';
    window.currentParentId = blockId;
    window.currentBlockId = blockId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען חלקות
    tableRenderer.loadAndDisplay('plot', blockId);
};

window.openPlot = function(plotId, plotName) {
    console.log('📋 Opening plot:', plotId, plotName);
    
    window.selectedItems.plot = { id: plotId, name: plotName };
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
    window.currentPlotId = plotId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען אחוזות קבר
    tableRenderer.loadAndDisplay('area_grave', plotId);
};

window.openAreaGrave = function(areaGraveId, areaGraveName) {
    console.log('🏘️ Opening area grave:', areaGraveId, areaGraveName);
    
    window.selectedItems.areaGrave = { id: areaGraveId, name: areaGraveName };
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    window.currentAreaGraveId = areaGraveId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען קברים
    tableRenderer.loadAndDisplay('grave', areaGraveId);
};

window.viewGraveDetails = function(graveId) {
    console.log('⚰️ Viewing grave details:', graveId);
    // כאן אפשר להציג מודל או כרטיס עם פרטי הקבר
    alert('פרטי קבר: ' + graveId);
};

// ==========================================
// פונקציות גלובליות - טעינה עם הורה (לתאימות אחורה)
// ==========================================

window.loadBlocksForCemetery = async function(cemeteryId) {
    console.log('📦 Loading blocks for cemetery:', cemeteryId);
    window.currentCemeteryId = cemeteryId;
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.cemetery) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('block', cemeteryId);
};

window.loadPlotsForBlock = async function(blockId) {
    console.log('📋 Loading plots for block:', blockId);
    window.currentBlockId = blockId;
    window.currentType = 'plot';
    window.currentParentId = blockId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.block) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('plot', blockId);
};

window.loadAreaGravesForPlot = async function(plotId) {
    console.log('🏘️ Loading area graves for plot:', plotId);
    window.currentPlotId = plotId;
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.plot) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('area_grave', plotId);
};

window.loadGravesForAreaGrave = async function(areaGraveId) {
    console.log('⚰️ Loading graves for area grave:', areaGraveId);
    window.currentAreaGraveId = areaGraveId;
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.areaGrave) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('grave', areaGraveId);
};
