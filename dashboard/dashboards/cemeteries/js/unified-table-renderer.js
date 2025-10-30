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
    renderActions2(item) {
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

    renderActions(item) {
        const itemId = item.unicId || item.id;
        const itemName = this.getItemName(item);
        const type = this.currentType;
        
        let html = '';
        
        // כפתור עריכה - עכשיו async!
        if (this.config.permissions.can_edit) {
            html += `
                <button class="btn btn-sm btn-secondary" 
                        onclick="event.stopPropagation(); tableRenderer.editItem('${itemId}')">
                    <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                </button>
            `;
        }
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
        // const type = this.currentType || window.currentType;
        const type = window.currentType || this.currentType;
        const parentId = window.currentParentId;
        
        console.log('openAddModal - type:', type, 'parentId:', parentId);

        // לקוחות ורכישות לא צריכים הורה
        const typesWithoutParent = ['cemetery', 'payment', 'customer', 'purchase', 'residency', 'burial'];

        if (!type) {
            console.error('No type defined');
            return;
        }
        
        if (typesWithoutParent.includes(type)) {
            console.log('About to call FormHandler.openForm for burial');
            
            // בדוק אם FormHandler קיים
            if (typeof FormHandler === 'undefined') {
                alert('ERROR: FormHandler is undefined!');
                console.error('FormHandler not found!');
                return;
            }
            
            if (typeof FormHandler.openForm !== 'function') {
                alert('ERROR: FormHandler.openForm is not a function!');
                console.error('FormHandler.openForm is not a function!');
                return;
            }
            
            console.log('FormHandler exists, calling openForm...');
            FormHandler.openForm(type, null, null);
            console.log('FormHandler.openForm was called');
            return;
        }

        if (typesWithoutParent.includes(type)) {
            FormHandler.openForm(type, null, null);
            return;
        }

        // בדוק אם צריך לבחור הורה קודם
        if (!parentId) {
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
        const type = window.currentType || this.currentType;
        const parentId = window.currentParentId;
        
        console.log('addItem - type:', type, 'parentId:', parentId);

        // לקוחות ורכישות לא צריכים הורה
        const typesWithoutParent = ['cemetery', 'payment', 'customer', 'purchase', 'residency', 'burial'];

        
        if (typesWithoutParent.includes(type)) {
            // פתח ישירות בלי הורה
            FormHandler.openForm(type, null, null);
            return;
        }
        
        // בדוק אם צריך לבחור הורה קודם
        if (!parentId && !typesWithoutParent.includes(type)) {
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

    /**
     * עריכת פריט
     */
    editItem2(itemId) {
        const type = this.currentType;
        const parentId = window.currentParentId;
        
        console.log('editItem - type:', type, 'itemId:', itemId);
        
        // קריאה פשוטה עם פרמטרים
        FormHandler.openForm(type, parentId, itemId);
    }

    /**
     * עריכת פריט - טוען נתונים מהשרת לפני פתיחת הטופס
     */
    async editItem3(itemId) {
        const type = this.currentType;
        
        console.log('📝 editItem - type:', type, 'itemId:', itemId);
        
        try {
            // 1️⃣ קבל את ה-API הנכון לפי הסוג
            const apiFile = this.getApiFile(type);
            
            if (!apiFile) {
                throw new Error(`לא נמצא API עבור סוג: ${type}`);
            }
            
            // 2️⃣ טען את נתוני הפריט מהשרת
            const response = await fetch(
                `${API_BASE}${apiFile}?action=get&id=${itemId}`
            );
            const data = await response.json();
            
            if (!data.success || !data.data) {
                throw new Error('לא נמצאו נתוני הפריט');
            }
            
            const item = data.data;
            
            // 3️⃣ חלץ את ה-parent_id האמיתי של הפריט
            const parentId = this.extractParentId(item, type);
            
            console.log('✅ Parent ID found:', parentId);
            
            // 4️⃣ פתח את הטופס עם ההורה הנכון
            FormHandler.openForm(type, parentId, itemId);
            
        } catch (error) {
            console.error('❌ Error loading item data:', error);
            showError('שגיאה בטעינת נתוני הפריט');
        }
    }

    /**
     * עריכת פריט - טוען נתונים מהשרת לפני פתיחת הטופס
     * גרסת דיבוג מפורטת
     */
    async editItem(itemId) {
        // console.log('═══════════════════════════════════════════════');
        // console.log('🚀 START editItem');
        // console.log('═══════════════════════════════════════════════');
        
        // שלב 1: זיהוי הסוג
        const type = this.currentType;
        // console.log('1️⃣ STEP 1: Identify type');
        // console.log('   📌 this.currentType:', this.currentType);
        // console.log('   📌 window.currentType:', window.currentType);
        // console.log('   📌 Final type:', type);
        // console.log('   📌 Item ID:', itemId);
        
        if (!type) {
            console.error('❌ ERROR: No type defined!');
            // console.log('   this:', this);
            // console.log('   window.currentType:', window.currentType);
            alert('שגיאה: לא ניתן לזהות את סוג הפריט');
            return;
        }
        
        try {
            // שלב 2: קבלת קובץ ה-API
            // console.log('2️⃣ STEP 2: Get API file');
            const apiFile = this.getApiFile(type);
            // console.log('   📌 API file:', apiFile);
            
            if (!apiFile) {
                console.error('❌ ERROR: No API file found for type:', type);
                throw new Error(`לא נמצא API עבור סוג: ${type}`);
            }
            
            // שלב 3: בניית ה-URL
            // console.log('3️⃣ STEP 3: Build URL');
            const url = `${apiFile}?action=get&id=${itemId}`;
            // console.log('   📌 Full URL:', url);
            // console.log('   📌 Absolute URL:', window.location.origin + url);
            
            // שלב 4: שליחת הבקשה
            // console.log('4️⃣ STEP 4: Send request');
            // console.log('   ⏳ Fetching...');
            
            const response = await fetch(url);
            
            // console.log('   ✅ Response received');
            // console.log('   📌 Status:', response.status);
            // console.log('   📌 Status Text:', response.statusText);
            // console.log('   📌 OK:', response.ok);
            // console.log('   📌 Headers:', [...response.headers.entries()]);
            
            // שלב 5: בדיקת סטטוס
            // console.log('5️⃣ STEP 5: Check response status');
            if (!response.ok) {
                console.error('❌ ERROR: Response not OK');
                const text = await response.text();
                console.error('   📌 Response text:', text);
                
                try {
                    const errorJson = JSON.parse(text);
                    console.error('   📌 Error JSON:', errorJson);
                    throw new Error(errorJson.error || `שגיאת שרת: ${response.status}`);
                } catch (parseError) {
                    console.error('   📌 Could not parse error as JSON');
                    throw new Error(`שגיאת שרת: ${response.status} - ${text.substring(0, 100)}`);
                }
            }
            
            // שלב 6: פירוק ה-JSON
            // console.log('6️⃣ STEP 6: Parse JSON');
            const data = await response.json();
            // console.log('   ✅ JSON parsed successfully');
            // console.log('   📌 Full response:', data);
            // console.log('   📌 Success:', data.success);
            // console.log('   📌 Has data:', !!data.data);
            
            // שלב 7: בדיקת תקינות הנתונים
            // console.log('7️⃣ STEP 7: Validate data');
            if (!data.success) {
                console.error('❌ ERROR: API returned success=false');
                console.error('   📌 Error message:', data.error);
                throw new Error(data.error || 'API returned success=false');
            }
            
            if (!data.data) {
                console.error('❌ ERROR: No data in response');
                throw new Error('לא נמצאו נתוני הפריט');
            }
            
            const item = data.data;
            // console.log('   ✅ Item data valid');
            // console.log('   📌 Item keys:', Object.keys(item));
            // console.log('   📌 Item sample:', {
            //     unicId: item.unicId,
            //     id: item.id,
            //     name: item.cemeteryNameHe || item.blockNameHe || item.plotNameHe || 'N/A'
            // });
            
            // // שלב 8: חילוץ parent_id
            // console.log('8️⃣ STEP 8: Extract parent ID');
            // const parentId = this.extractParentId(item, type);
            // console.log('   📌 Extracted parent ID:', parentId);
            
            // if (parentId === null) {
            //     console.log('   ℹ️ No parent (root entity)');
            // } else if (parentId === undefined) {
            //     console.warn('   ⚠️ Parent ID is undefined - might be a problem');
            // } else {
            //     console.log('   ✅ Valid parent ID found');
            // }

            // שלב 8: חילוץ parent_id ושם ההורה
            // console.log('8️⃣ STEP 8: Extract parent ID and name');
            const parentId = this.extractParentId(item, type);
            const parentName = this.extractParentName(item, type);
            // console.log('   📌 Extracted parent ID:', parentId);
            // console.log('   📌 Extracted parent name:', parentName);

            if (parentId === null) {
                // console.log('   ℹ️ No parent (root entity)');
            } else if (parentId === undefined) {
                console.warn('   ⚠️ Parent ID is undefined - might be a problem');
            } else {
                // console.log('   ✅ Valid parent ID found');
            }
            
            // שלב 9: פתיחת הטופס
            // console.log('9️⃣ STEP 9: Open form');
            // console.log('   📌 FormHandler exists:', typeof FormHandler !== 'undefined');
            // console.log('   📌 FormHandler.openForm exists:', typeof FormHandler?.openForm === 'function');
            // console.log('   📌 Calling FormHandler.openForm with:');
            // console.log('      - type:', type);
            // console.log('      - parentId:', parentId);
            // console.log('      - itemId:', itemId);
            
            if (typeof FormHandler?.openForm !== 'function') {
                console.error('❌ ERROR: FormHandler.openForm is not available');
                alert('שגיאה: FormHandler לא זמין');
                return;
            }
            
            // FormHandler.openForm(type, parentId, itemId);
            FormHandler.openForm(type, parentId, itemId, parentName);
            
            // console.log('   ✅ FormHandler.openForm called successfully');
            // console.log('═══════════════════════════════════════════════');
            // console.log('✅ END editItem - SUCCESS');
            // console.log('═══════════════════════════════════════════════');
            
        } catch (error) {
            // console.log('═══════════════════════════════════════════════');
            console.error('❌ END editItem - ERROR');
            // console.log('═══════════════════════════════════════════════');
            console.error('💥 Error object:', error);
            console.error('💥 Error message:', error.message);
            console.error('💥 Error stack:', error.stack);
            
            showError('שגיאה בטעינת נתוני הפריט: ' + error.message);
        }
    }

    /**
     * קבלת שם קובץ API לפי סוג הפריט
     */
    getApiFile2(type) {
        const apiMap = {
            'cemetery': 'cemeteries-api.php',
            'block': 'blocks-api.php',
            'plot': 'plots-api.php',
            'row': 'rows-api.php',           // לעתיד
            'area_grave': 'area-graves-api.php',
            'grave': 'graves-api.php',
            'customer': 'customers-api.php',
            'purchase': 'purchases-api.php',
            'burial': 'burials-api.php',
            'residency': 'residencies-api.php',
            'payment': 'payments-api.php'
        };
        
        return apiMap[type] || null;
    }
    getApiFile(type) {
    const apiMap = {
        'cemetery': '/dashboard/dashboards/cemeteries/api/cemeteries-api.php',
        'block': '/dashboard/dashboards/cemeteries/api/blocks-api.php',
        'plot': '/dashboard/dashboards/cemeteries/api/plots-api.php',
        'row': '/dashboard/dashboards/cemeteries/api/rows-api.php',
        'area_grave': '/dashboard/dashboards/cemeteries/api/area-graves-api.php',
        'grave': '/dashboard/dashboards/cemeteries/api/graves-api.php',
        'customer': '/dashboard/dashboards/cemeteries/api/customers-api.php',
        'purchase': '/dashboard/dashboards/cemeteries/api/purchases-api.php',
        'burial': '/dashboard/dashboards/cemeteries/api/burials-api.php',
        'residency': '/dashboard/dashboards/cemeteries/api/residencies-api.php',
        'payment': '/dashboard/dashboards/cemeteries/api/payments-api.php'
    };
    
    return apiMap[type] || null;
}

    /**
     * חילוץ parent_id מנתוני פריט לפי הסוג שלו
     */
    extractParentId2(item, type) {
        // מפת שדות parent לפי סוג
        const parentFieldMap = {
            'cemetery': null,                                    // בית עלמין אין לו הורה
            'block': ['cemeteryId', 'cemetery_id'],             // גוש → בית עלמין
            'plot': ['blockId', 'block_id'],                    // חלקה → גוש
            'row': ['plotId', 'plot_id'],                       // שורה → חלקה
            'area_grave': ['lineId', 'line_id', 'rowId', 'row_id'], // אחוזת קבר → שורה
            'grave': ['areaGraveId', 'area_grave_id'],          // קבר → אחוזת קבר
            'customer': null,                                    // לקוח אין לו הורה
            'purchase': null,                                    // רכישה אין לה הורה
            'burial': null,                                      // קבורה אין לה הורה
            'residency': null,                                   // חוק תושבות אין לו הורה
            'payment': null                                      // חוק תשלום אין לו הורה
        };
        
        const fields = parentFieldMap[type];
        
        // אם אין הורה לסוג הזה
        if (fields === null) {
            return null;
        }
        
        // אם לא הוגדרו שדות - נסה parent_id כברירת מחדל
        if (!fields || fields.length === 0) {
            return item.parent_id || null;
        }
        
        // נסה למצוא את הערך הראשון שקיים
        for (let field of fields) {
            if (item[field]) {
                return item[field];
            }
        }
        
        // אם לא מצאנו שום שדה - נסה parent_id כ-fallback
        return item.parent_id || null;
    }

    extractParentId(item, type) {
        // מפת שדות parent לפי סוג
        const parentFieldMap = {
            'cemetery': null, // בית עלמין אין לו הורה
            'block': ['cemeteryId', 'cemetery_id', 'parent_id'],
            'plot': ['blockId', 'block_id', 'parent_id'],
            'row': ['plotId', 'plot_id', 'parent_id'],
            'area_grave': ['rowId', 'row_id', 'lineId', 'line_id', 'parent_id'],
            'grave': ['areaGraveId', 'area_grave_id', 'parent_id'],
            'customer': null, // לקוח אין לו הורה
            'purchase': null, // רכישה אין לה הורה
            'burial': null, // קבורה אין לה הורה
            'residency': null, // חוק תושבות אין לו הורה
            'payment': null // חוק תשלום אין לו הורה
        };
        
        const fields = parentFieldMap[type];
        
        // אם אין הורה לסוג הזה
        if (fields === null) {
            return null;
        }
        
        // אם לא הוגדרו שדות - נסה parent_id כברירת מחדל
        if (!fields || fields.length === 0) {
            return item.parent_id || null;
        }
        
        // נסה למצוא את הערך הראשון שקיים
        for (let field of fields) {
            if (item[field]) {
                return item[field];
            }
        }
        
        return null;
    }

    /**
     * חילוץ parent_name מנתוני פריט לפי הסוג שלו
     */
    extractParentName(item, type) {
        // מפת שדות שם ההורה לפי סוג
        const parentNameFieldMap = {
            'cemetery': null,                                    // בית עלמין אין לו הורה
            'block': ['cemetery_name', 'cemeteryNameHe'],       // גוש → שם בית עלמין
            'plot': ['block_name', 'blockNameHe'],              // חלקה → שם גוש
            'row': ['plot_name', 'plotNameHe'],                 // שורה → שם חלקה
            'area_grave': ['row_name', 'lineNameHe'],           // אחוזת קבר → שם שורה
            'grave': ['area_grave_name', 'areaGraveNameHe'],    // קבר → שם אחוזת קבר
            'customer': null,
            'purchase': null,
            'burial': null,
            'residency': null,
            'payment': null
        };
        
        const fields = parentNameFieldMap[type];
        
        // אם אין הורה לסוג הזה
        if (fields === null) {
            return null;
        }
        
        // אם לא הוגדרו שדות
        if (!fields || fields.length === 0) {
            return null;
        }
        
        // נסה למצוא את הערך הראשון שקיים
        for (let field of fields) {
            if (item[field]) {
                return item[field];
            }
        }
        
        return null;
    }
    
    /**
     * מחיקת פריט
     */
    async deleteItem(itemId) {
        // בדוק אם יש ילדים
        const childType = this.getChildType();
        if (childType) {
            try {
                const response = await fetch(
                    `${API_BASE}cemetery-hierarchy.php?action=list&type=${childType}&parent_id=${itemId}`
                );
                const data = await response.json();
                if (data.success && data.data && data.data.length > 0) {
                    showError(`לא ניתן למחוק ${this.config.singular} שמכיל ${data.data.length} פריטים משויכים`);
                    return;
                }
            } catch (error) {
                console.error('Error checking children:', error);
            }
        }
        
        // המשך עם המחיקה הרגילה...
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
// ⭐ פונקציה חדשה - בניית מבנה היררכיה (STEP C)
// ==========================================

/**
 * בניית המבנה הבסיסי של היררכיה ב-main-container
 */
function buildHierarchyContainer() {
    console.log('🏗️ Building hierarchy container...');
    
    // מצא את main-container (צריך להיות קיים אחרי clear)
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
    
    // ⭐ בנה את המבנה הבסיסי של טבלה
    mainContainer.innerHTML = `
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
                                <span class="visually-hidden">טוען נתונים...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('✅ Hierarchy container built');
}


// ==========================================
// פונקציות גלובליות - טעינת כל הפריטים
// ==========================================

window.loadAllCemeteries2 = async function() {
    console.log('📍 Loading all cemeteries - STEP C');
    setActiveMenuItem('cemeteryItem');
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {};

    // ⭐ נקה (מוחק main-container או מנקה שיטה ישנה)
    DashboardCleaner.clear({ targetLevel: 'cemetery' });
    
    // ⭐ בנה את המבנה החדש
    buildHierarchyContainer();
    
    // עדכן breadcrumb
    BreadcrumbManager.update({}, 'cemetery');
    
    // טען נתונים
    await tableRenderer.loadAndDisplay('cemetery');
};
 
window.loadAllBlocks2 = async function() {
    console.log('📍 Loading all blocks - STEP C');
    setActiveMenuItem('blockItem');
    window.currentType = 'block';
    window.currentParentId = null;

    // שמור רק את בית העלמין אם קיים
    const temp = window.selectedItems?.cemetery;
    window.selectedItems = {};
    if (temp) window.selectedItems.cemetery = temp;
    
    // ⭐ נקה ובנה מחדש
    DashboardCleaner.clear({ targetLevel: 'block' });
    buildHierarchyContainer();
    
    BreadcrumbManager.update({}, 'block');
    await tableRenderer.loadAndDisplay('block');
};

window.loadAllPlots2 = async function() {
    console.log('📍 Loading all plots - STEP C');
    setActiveMenuItem('plotItem');
    window.currentType = 'plot';
    window.currentParentId = null;

    // שמור רק עד גוש
    const tempCemetery = window.selectedItems?.cemetery;
    const tempBlock = window.selectedItems?.block;
    window.selectedItems = {};
    if (tempCemetery) window.selectedItems.cemetery = tempCemetery;
    if (tempBlock) window.selectedItems.block = tempBlock;
    
    // ⭐ נקה ובנה מחדש
    DashboardCleaner.clear({ targetLevel: 'plot' });
    buildHierarchyContainer();
    
    BreadcrumbManager.update({}, 'plot');
    await tableRenderer.loadAndDisplay('plot');
};

window.loadAllAreaGraves2 = async function() {
    console.log('📍 Loading all area graves - STEP C');
    setActiveMenuItem('areaGraveItem');
    window.currentType = 'area_grave';
    window.currentParentId = null;

    // שמור עד חלקה
    const temp = { ...window.selectedItems };
    window.selectedItems = {};
    if (temp.cemetery) window.selectedItems.cemetery = temp.cemetery;
    if (temp.block) window.selectedItems.block = temp.block;
    if (temp.plot) window.selectedItems.plot = temp.plot;
    
    // ⭐ נקה ובנה מחדש
    DashboardCleaner.clear({ targetLevel: 'area_grave' });
    DashboardCleaner.clearCards();
    buildHierarchyContainer();
    
    BreadcrumbManager.update({}, 'area_grave');
    await tableRenderer.loadAndDisplay('area_grave');
};

window.loadAllGraves2 = async function() {
    console.log('📍 Loading all graves - STEP C');
    setActiveMenuItem('graveItem');
    window.currentType = 'grave';
    window.currentParentId = null;
    
    // שמור עד אחוזת קבר
    const temp = { ...window.selectedItems };
    window.selectedItems = {};
    if (temp.cemetery) window.selectedItems.cemetery = temp.cemetery;
    if (temp.block) window.selectedItems.block = temp.block;
    if (temp.plot) window.selectedItems.plot = temp.plot;
    if (temp.areaGrave) window.selectedItems.areaGrave = temp.areaGrave;
    
    // ⭐ נקה ובנה מחדש
    DashboardCleaner.clear({ targetLevel: 'grave' });
    buildHierarchyContainer();
    
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
    window.tableRenderer.loadAndDisplay('block', cemeteryId);
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
    window.tableRenderer.loadAndDisplay('plot', blockId);
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
    window.tableRenderer.loadAndDisplay('area_grave', plotId);
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
    window.tableRenderer.loadAndDisplay('grave', areaGraveId);
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
