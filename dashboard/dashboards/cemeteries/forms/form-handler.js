// /dashboards/cemeteries/forms/form-handler.js
// טיפול בטפסים בצד הלקוח

// פונקציה לבדיקת תעודת זהות ישראלית
function validateIsraeliId(id) {
    // ניקוי והמרה למחרוזת
    id = String(id).trim();
    
    // בדיקה: בדיוק 9 ספרות
    if (id.length !== 9 || !/^\d+$/.test(id)) {
        return false;
    }
    
    // אלגוריתם Luhn (ספרת ביקורת)
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        let digit = parseInt(id[i]);
        digit *= ((i % 2) + 1); // x1, x2, x1, x2...
        
        if (digit > 9) {
            digit = Math.floor(digit / 10) + (digit % 10);
        }
        sum += digit;
    }
    
    return (sum % 10 === 0);
}

const FormHandler = {

    // פונקציה עזר אחידה לחכות לאלמנט או פונקציה
    waitForElement: function(selector, callback, timeout = 5000) {
        const element = document.querySelector(selector);
        if (element) {
            callback(element);
            return;
        }
        
        const observer = new MutationObserver((mutations, obs) => {
            const element = document.querySelector(selector);
            if (element) {
                callback(element);
                obs.disconnect();
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        setTimeout(() => observer.disconnect(), timeout);
    },

    handleParentSelection: function() {
        const select = document.getElementById('selected_parent');
        const selectedParentId = select ? select.value : null;
        
        if (!selectedParentId) {
            alert('יש לבחור הורה');
            return;
        }
        
        const childType = window.pendingChildType;
        this.closeForm('parent_selector');
        this.openForm(childType, selectedParentId, null);
    },

    openForm: async function(type, parentId = null, itemId = null) {    
        
        console.log('step 1 - openForm :: type: ',type, 'parentId: ',parentId, 'itemId: ',itemId );
        
        
        if (type === 'purchase' && !itemId) {
            window.isEditMode = false;
            window.purchasePayments = [];
            window.selectedGraveData = null;
        }
        
        if (!type || typeof type !== 'string') {
            console.error('Invalid type:', type);
            this.showMessage('שגיאה: סוג הטופס לא תקין', 'error');
            return;
        }

        try {
            const params = new URLSearchParams({
                formType: type,
                ...(itemId && { itemId: itemId }),
                ...(parentId && { parent_id: parentId })
            });

            const response = await fetch(`/dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);
     
            if (!response.ok) {
                return;
            }
        
            const html = await response.text();
            
            // בדוק מה יש ב-HTML
            if (html.includes('error') || html.includes('Error')) {
                // console.log('⚠️ Error found in HTML');
                const errorMatch = html.match(/error[^<]*/gi);
                if (errorMatch) {
                    // console.log('Error text found:', errorMatch);
                }
            }

            // צור container זמני לפירוק ה-HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            // הסר טופס קיים אם יש
            const existingModal = document.getElementById(type + 'FormModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // הסר style קיים אם יש
            const existingStyle = document.getElementById(type + 'FormStyle');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            // חפש את ה-style tag
            const styleTag = tempDiv.querySelector('style');
            if (styleTag) {
                styleTag.id = type + 'FormStyle';
                document.head.appendChild(styleTag);
            }
            
            // חפש את המודאל
            const modal = tempDiv.querySelector('#' + type + 'FormModal');

            if (modal) {
                document.body.appendChild(modal);
                document.body.style.overflow = 'hidden';
                
                // 🚀 פתרון יציב: requestAnimationFrame מבטיח שהדפדפן סיים לרנדר
                requestAnimationFrame(() => {
                    const form = modal.querySelector('form');
                    
                    if (form && window.FormValidations) {
                        // ✅ הטופס מוכן - אתחל וולידציות
                        FormValidations.init(form);
                        console.log('✅ FormValidations initialized for', type);
                        
                        // שלח custom event שהטופס מוכן (למי שרוצה להאזין)
                        const event = new CustomEvent('formReady', { 
                            detail: { type, form, formId: form.id } 
                        });
                        document.dispatchEvent(event);
                        
                    } else {
                        if (!form) console.error('❌ Form element not found in modal');
                        if (!window.FormValidations) console.error('❌ FormValidations not loaded');
                    }
                });
                
                this.handleFormSpecificLogic(type, parentId, itemId);
                
            } else {
                console.error('❌ Modal not found in HTML');
                const allModals = tempDiv.querySelectorAll('.modal');
                allModals.forEach(m => {
                    // console.log('Modal id:', m.id);
                });
            }
    
            
        } catch (error) {
            console.error('❌ Error in openForm:', error);
            this.showMessage('שגיאה בטעינת הטופס', 'error');
        }
    },

    handleFormSpecificLogic: async function(type, parentId, itemId) {
            switch(type) {

                case 'areaGrave':
                    // ⭐ אם זה עריכה אבל אין parentId - שלוף אותו מה-API
                    if (itemId && !parentId) {
                        console.log('🔍 [areaGrave] מצב עריכה ללא parentId - שולף מה-API...');
                        try {
                            const response = await fetch(`${API_BASE}areaGraves-api.php?action=get&id=${itemId}`);
                            const result = await response.json();
                            
                            if (result.success && result.data) {
                                // ⭐ שלוף את ה-lineId (זה ה-parentId!)
                                parentId = result.data.lineId || result.data.line_id || result.data.rowId || result.data.row_id;
                                console.log('✅ נמצא parentId מה-API:', parentId);
                                
                                // ⭐⭐⭐ עדכן את התצוגה בטופס!
                                await this.updateParentDisplay(type, parentId);
                            } else {
                                console.warn('⚠️ לא נמצא parentId ב-API response');
                            }
                        } catch (error) {
                            console.error('❌ שגיאה בשליפת parentId:', error);
                        }
                    }
                    
                    // עכשיו אתחל עם parentId נכון
                    if (itemId) {
                        // מצב עריכה - טען נתונים ואתחל מערכת קברים
                        this.loadFormData(type, itemId);
                        
                        if (parentId) {
                            this.handleAreaGraveForm(parentId);
                            console.log('✅ handleAreaGraveForm called with parentId:', parentId);
                        } else {
                            console.error('❌ אין parentId! לא ניתן לאתחל מערכת קברים');
                            this.showMessage('שגיאה: לא נמצא מזהה השורה של אחוזת הקבר', 'error');
                        }
                    } else if (parentId) {
                        // מצב הוספה חדשה - אתחל מערכת קברים בלבד
                        this.handleAreaGraveForm(parentId);
                    }
                    break;

                case 'customer':
                    this.handleCustomerForm(itemId);
                    break;
                    
                case 'purchase':
                    this.handlePurchaseForm(itemId);
                    break;  

                case 'burial':
                    this.handleBurialForm(itemId);
                    break;

                case 'payment':
                    this.handlePaymentForm(itemId);
                    break;
                
                case 'graveCard':
                    this.handleGraveCardForm(itemId);
                    break;

                default:
                    if (itemId) {
                        this.loadFormData(type, itemId);
                    }
                    break;
            }
    },

    loadAreaGraveWithGraves: async function(areaGraveId) {
        console.log('📦 Loading area grave with graves:', areaGraveId);
        
        try {
            if (typeof window.validateGravesData === 'function') {
                    console.log('🔍 Running graves validation...');
                    
                    if (!window.validateGravesData()) {
                        console.error('❌ Graves validation failed');
                        return false;
                    }
                    
                    console.log('✅ Graves validation passed');
                    
                    // ⭐ תיקון: קרא gravesData אחרי ולידציה
                    const gravesDataInput = document.getElementById('gravesData');
                    if (gravesDataInput && gravesDataInput.value) {
                        console.log('📥 Reading gravesData from hidden input after validation');
                        console.log('📊 gravesData length:', gravesDataInput.value.length, 'chars');
                        
                        formData.set('gravesData', gravesDataInput.value);
                        
                        console.log('✅ gravesData added to formData');
                    } else {
                        console.error('❌ gravesData input not found or empty!');
                        this.showMessage('שגיאה: נתוני הקברים לא נמצאו', 'error');
                        return false;
                    }
            } else {
                console.error('❌ validateGravesData function not found!');
                this.showMessage('שגיאה: פונקציית ולידציה לא נמצאה', 'error');
                return false;
            }         
        } catch (error) {
            console.error('❌ Error loading area grave data:', error);
        }
    },

    updateParentDisplay: async function(type, parentId) {
        if (!parentId) return;
        
        console.log('🔄 מעדכן תצוגת הורה:', parentId);
        
        try {
            // שלוף את שם ההורה מה-API
            let parentName = '';
            
            if (type === 'areaGrave') {
                // ההורה הוא שורה
                const response = await fetch(`${API_BASE}rows-api.php?action=get&id=${parentId}`);
                const result = await response.json();
                
                if (result.success && result.data) {
                    parentName = result.data.lineNameHe || result.data.rowNameHe || `שורה ${result.data.serialNumber}`;
                }
            }
            
            // עדכן את האלמנט בטופס
            const parentNameElement = document.getElementById('currentParentName');
            if (parentNameElement && parentName) {
                parentNameElement.textContent = parentName;
                console.log('✅ עודכן שם הורה:', parentName);
            }
            
            // עדכן hidden field אם יש
            const parentIdField = document.querySelector('input[name="parentId"]');
            if (parentIdField) {
                parentIdField.value = parentId;
                console.log('✅ עודכן parentId בשדה:', parentId);
            }
            
            // עדכן hidden field של lineId
            const lineIdField = document.querySelector('input[name="lineId"]');
            if (lineIdField) {
                lineIdField.value = parentId;
                console.log('✅ עודכן lineId בשדה:', parentId);
            }
            
        } catch (error) {
            console.error('❌ שגיאה בעדכון תצוגת הורה:', error);
        }
    },

    /**
     * טיפול בטופס אחוזת קבר
     * מאתחל את כל הפונקציונליות של ניהול קברים באחוזה
     */
    handleAreaGraveForm: function(itemId) {
        console.log('🪦 Initializing Area Grave Form...', 'itemId:', itemId);
        
        const self = this; // שמירת reference ל-FormHandler
        
        // חכה שה-fieldset יטען
        this.waitForElement('#graves-fieldset', (fieldset) => {
            console.log('✅ Graves fieldset found');
            
            // קרא את הקונפיגורציה מה-data attribute
            if (!fieldset.dataset.gravesConfig) {
                console.error('❌ No graves config found!');
                return;
            }
            
            let config;
            try {
                config = JSON.parse(fieldset.dataset.gravesConfig);
                console.log('📋 Loaded config:', config);
            } catch (e) {
                console.error('❌ Failed to parse graves config:', e);
                return;
            }
            
            // =========================================
            // הגדרת משתנה גלובלי לקונפיגורציה
            // =========================================
            window.GRAVES_CONFIG = {
                existing: config.existing || [],
                isEdit: config.isEdit || false,
                current: [],
                MAX: config.max || 5,
                areaGraveId: config.areaGraveId || null
            };
            
            console.log('🔧 GRAVES_CONFIG initialized:', window.GRAVES_CONFIG);
            
            // =========================================
            // פונקציה: אתחול מערכת הקברים
            // =========================================
            function initGravesSystem() {
                console.log('📋 Initializing graves system...');
                
                // אם זה מצב עריכה וקיימים קברים - טען אותם
                if (window.GRAVES_CONFIG.isEdit && window.GRAVES_CONFIG.existing.length > 0) {
                    console.log('📥 Loading', window.GRAVES_CONFIG.existing.length, 'existing graves');
                    
                    window.GRAVES_CONFIG.existing.forEach(function(grave) {
                        window.GRAVES_CONFIG.current.push({
                            id: grave.unicId || null,
                            graveNameHe: grave.graveNameHe || '',
                            plotType: parseInt(grave.plotType) || 1,
                            graveStatus: parseInt(grave.graveStatus) || 1,
                            isSmallGrave: grave.isSmallGrave == 1,
                            constructionCost: grave.constructionCost || '',
                            isExisting: true
                        });
                    });
                    
                    console.log('✅ Loaded existing graves:', window.GRAVES_CONFIG.current);
                } else {
                    // אחוזת קבר חדשה - צור קבר ראשון
                    console.log('➕ Creating first grave for new area');
                    window.GRAVES_CONFIG.current.push({
                        id: null,
                        graveNameHe: '',
                        plotType: 1,
                        graveStatus: 1,
                        isSmallGrave: false,
                        constructionCost: '',
                        isExisting: false
                    });
                }
                
                // רנדר את הטבלה
                renderGraves();
                
                // עדכן מונה
                updateCounter();
                
                // חבר את כפתור ההוספה
                const btnAdd = document.getElementById('btnAddGrave');
                if (btnAdd) {
                    btnAdd.onclick = addGrave;
                    console.log('✅ Add button connected');
                } else {
                    console.error('❌ Add button not found');
                }
                
                console.log('✅ Graves system initialized successfully');
            }
            
            // =========================================
            // פונקציה: הוספת קבר חדש
            // =========================================
            function addGrave() {
                console.log('➕ Adding new grave...');
                
                // בדיקת מגבלת מקסימום
                if (window.GRAVES_CONFIG.current.length >= window.GRAVES_CONFIG.MAX) {
                    alert('ניתן להוסיף עד ' + window.GRAVES_CONFIG.MAX + ' קברים בלבד');
                    console.warn('⚠️ Maximum graves reached');
                    return;
                }
                
                // הוסף קבר חדש למערך
                window.GRAVES_CONFIG.current.push({
                    id: null,
                    graveNameHe: '',
                    plotType: 1,
                    graveStatus: 1,
                    isSmallGrave: false,
                    constructionCost: '',
                    isExisting: false
                });
                
                console.log('✅ Grave added. Total:', window.GRAVES_CONFIG.current.length);
                
                // רנדר מחדש
                renderGraves();
                updateCounter();
            }
            
            // =========================================
            // פונקציה: מחיקת קבר
            // =========================================
            function deleteGrave(idx) {
                console.log('🗑️ Attempting to delete grave at index:', idx);
                
                const grave = window.GRAVES_CONFIG.current[idx];
                
                // בדיקות מניעה
                if (idx === 0) {
                    alert('לא ניתן למחוק את הקבר הראשון');
                    console.warn('⚠️ Cannot delete first grave');
                    return;
                }
                
                // במצב עריכה - לא ניתן למחוק קבר שאינו פנוי
                if (window.GRAVES_CONFIG.isEdit && grave.isExisting && grave.graveStatus !== 1) {
                    alert('לא ניתן למחוק קבר לא פנוי (סטטוס: ' + getStatusName(grave.graveStatus) + ')');
                    console.warn('⚠️ Cannot delete non-available grave');
                    return;
                }
                
                // אישור משתמש
                if (!confirm('האם אתה בטוח שברצונך למחוק קבר זה?')) {
                    console.log('❌ Deletion cancelled by user');
                    return;
                }
                
                // מחיקה
                window.GRAVES_CONFIG.current.splice(idx, 1);
                console.log('✅ Grave deleted. Remaining:', window.GRAVES_CONFIG.current.length);
                
                // רנדר מחדש
                renderGraves();
                updateCounter();
            }
            
            // =========================================
            // פונקציה: רינדור טבלת הקברים
            // =========================================
            function renderGraves() {
                console.log('🎨 Rendering graves table...');
                
                const tbody = document.getElementById('gravesBody');
                if (!tbody) {
                    console.error('❌ Graves tbody not found!');
                    return;
                }
                
                // נקה טבלה
                tbody.innerHTML = '';
                
                // בנה שורות
                window.GRAVES_CONFIG.current.forEach(function(grave, index) {
                    const tr = document.createElement('tr');
                    
                    // ========== עמודה 1: מספר סידורי ==========
                    const tdNumber = document.createElement('td');
                    tdNumber.style.textAlign = 'center';
                    tdNumber.style.fontWeight = 'bold';
                    tdNumber.style.color = '#667eea';
                    tdNumber.textContent = index + 1;
                    tr.appendChild(tdNumber);
                    
                    // ========== עמודה 2: שם קבר ==========
                    const tdName = document.createElement('td');
                    const inputName = document.createElement('input');
                    inputName.type = 'text';
                    inputName.value = grave.graveNameHe || '';
                    inputName.placeholder = 'שם קבר (חובה)';
                    inputName.required = true;
                    inputName.onchange = (function(idx) {
                        return function() {
                            window.GRAVES_CONFIG.current[idx].graveNameHe = this.value;
                            console.log('📝 Updated grave name at', idx, ':', this.value);
                        };
                    })(index);
                    tdName.appendChild(inputName);
                    tr.appendChild(tdName);
                    
                    // ========== עמודה 3: סוג חלקה ==========
                    const tdPlotType = document.createElement('td');
                    const selectPlotType = document.createElement('select');
                    selectPlotType.required = true;
                    selectPlotType.innerHTML = 
                        '<option value="1"' + (grave.plotType == 1 ? ' selected' : '') + '>פטורה</option>' +
                        '<option value="2"' + (grave.plotType == 2 ? ' selected' : '') + '>חריגה</option>' +
                        '<option value="3"' + (grave.plotType == 3 ? ' selected' : '') + '>סגורה</option>';
                    selectPlotType.onchange = (function(idx) {
                        return function() {
                            window.GRAVES_CONFIG.current[idx].plotType = parseInt(this.value);
                            console.log('📝 Updated plot type at', idx, ':', this.value);
                        };
                    })(index);
                    tdPlotType.appendChild(selectPlotType);
                    tr.appendChild(tdPlotType);
                    
                    // ========== עמודה 4: סטטוס (רק במצב עריכה) ==========
                    if (window.GRAVES_CONFIG.isEdit) {
                        const tdStatus = document.createElement('td');
                        tdStatus.style.textAlign = 'center';
                        
                        const statusNames = {1: 'פנוי', 2: 'נרכש', 3: 'קבור'};
                        const statusClasses = {1: 'available', 2: 'purchased', 3: 'buried'};
                        
                        const badge = document.createElement('span');
                        badge.className = 'status-badge status-' + statusClasses[grave.graveStatus];
                        badge.textContent = statusNames[grave.graveStatus];
                        
                        tdStatus.appendChild(badge);
                        tr.appendChild(tdStatus);
                    }
                    
                    // ========== עמודה 5: קבר קטן ==========
                    const tdSmall = document.createElement('td');
                    tdSmall.style.textAlign = 'center';
                    const checkboxSmall = document.createElement('input');
                    checkboxSmall.type = 'checkbox';
                    checkboxSmall.checked = grave.isSmallGrave;
                    checkboxSmall.onchange = (function(idx) {
                        return function() {
                            window.GRAVES_CONFIG.current[idx].isSmallGrave = this.checked;
                            console.log('📝 Updated small grave at', idx, ':', this.checked);
                        };
                    })(index);
                    tdSmall.appendChild(checkboxSmall);
                    tr.appendChild(tdSmall);
                    
                    // ========== עמודה 6: עלות בנייה ==========
                    const tdCost = document.createElement('td');
                    const inputCost = document.createElement('input');
                    inputCost.type = 'number';
                    inputCost.step = '0.01';
                    inputCost.value = grave.constructionCost || '';
                    inputCost.placeholder = '0.00';
                    inputCost.onchange = (function(idx) {
                        return function() {
                            window.GRAVES_CONFIG.current[idx].constructionCost = this.value;
                            console.log('📝 Updated construction cost at', idx, ':', this.value);
                        };
                    })(index);
                    tdCost.appendChild(inputCost);
                    tr.appendChild(tdCost);
                    
                    // ========== עמודה 7: פעולות (מחיקה) ==========
                    const tdActions = document.createElement('td');
                    tdActions.style.textAlign = 'center';
                    
                    const btnDelete = document.createElement('button');
                    btnDelete.type = 'button';
                    btnDelete.className = 'btn-delete';
                    btnDelete.textContent = '🗑️';
                    
                    // קבע אם ניתן למחוק
                    const canDelete = index > 0 && 
                        (!window.GRAVES_CONFIG.isEdit || !grave.isExisting || grave.graveStatus === 1);
                    
                    btnDelete.disabled = !canDelete;
                    btnDelete.onclick = (function(idx) {
                        return function() {
                            deleteGrave(idx);
                        };
                    })(index);
                    
                    tdActions.appendChild(btnDelete);
                    tr.appendChild(tdActions);
                    
                    // הוסף שורה לטבלה
                    tbody.appendChild(tr);
                });
                
                console.log('✅ Rendered', window.GRAVES_CONFIG.current.length, 'graves');
            }
            
            // =========================================
            // פונקציה: עדכון מונה קברים
            // =========================================
            function updateCounter() {
                const btnAdd = document.getElementById('btnAddGrave');
                const counter = document.getElementById('graveCount');
                
                const currentCount = window.GRAVES_CONFIG.current.length;
                const maxCount = window.GRAVES_CONFIG.MAX;
                
                // עדכן טקסט מונה
                if (counter) {
                    counter.textContent = '(' + currentCount + '/' + maxCount + ' קברים)';
                }
                
                // עדכן מצב כפתור
                if (btnAdd) {
                    btnAdd.disabled = currentCount >= maxCount;
                    
                    if (currentCount >= maxCount) {
                        btnAdd.style.opacity = '0.5';
                        btnAdd.style.cursor = 'not-allowed';
                    } else {
                        btnAdd.style.opacity = '1';
                        btnAdd.style.cursor = 'pointer';
                    }
                }
                
                console.log('🔢 Counter updated:', currentCount + '/' + maxCount);
            }
            
            // =========================================
            // פונקציה עזר: קבלת שם סטטוס
            // =========================================
            function getStatusName(status) {
                const names = {1: 'פנוי', 2: 'נרכש', 3: 'קבור'};
                return names[status] || 'לא ידוע';
            }
            
            // =========================================
            // פונקציה גלובלית: ולידציה לפני שמירה
            // =========================================
            window.validateGravesData2 = function() {
                console.log('🔍 Validating graves data...');
                
                // בדיקה 1: חייב להיות לפחות קבר אחד
                if (window.GRAVES_CONFIG.current.length === 0) {
                    alert('חובה לפחות קבר אחד באחוזה');
                    console.error('❌ Validation failed: No graves');
                    return false;
                }
                
                // בדיקה 2: כל הקברים חייבים להיות עם שם
                for (let i = 0; i < window.GRAVES_CONFIG.current.length; i++) {
                    const grave = window.GRAVES_CONFIG.current[i];
                    
                    if (!grave.graveNameHe || !grave.graveNameHe.trim()) {
                        alert('שם קבר ' + (i + 1) + ' הוא חובה');
                        console.error('❌ Validation failed: Grave', i, 'has no name');
                        return false;
                    }
                }
                
                // בדיקה 3: שמות קברים חייבים להיות ייחודיים
                const names = window.GRAVES_CONFIG.current.map(function(g) {
                    return g.graveNameHe.trim().toLowerCase();
                });
                
                const uniqueNames = names.filter(function(value, index, self) {
                    return self.indexOf(value) === index;
                });
                
                if (names.length !== uniqueNames.length) {
                    alert('שמות קברים חייבים להיות ייחודיים באחוזה');
                    console.error('❌ Validation failed: Duplicate grave names');
                    return false;
                }
                
                // הכנס את הנתונים ל-hidden input
                const hiddenInput = document.getElementById('gravesData');
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(window.GRAVES_CONFIG.current);
                    console.log('✅ Graves data serialized:', hiddenInput.value);
                } else {
                    console.error('❌ Hidden input #gravesData not found!');
                    return false;
                }
                
                console.log('✅ Validation passed');
                return true;
            };

            // =========================================
            // פונקציה: validateGravesData - גרסה מתוקנת
            // תיקון: קריאת ערכים ישירות מה-DOM לפני ולידציה
            // מיקום: בתוך handleAreaGraveForm, במקום הפונקציה הקיימת
            // =========================================

            window.validateGravesData = function() {
                console.log('🔍 Validating graves data...');
                
                // ========================================
                // ⭐ תיקון חשוב: קרא ערכים מה-DOM תחילה!
                // ========================================
                console.log('📥 Reading values from DOM inputs...');
                
                const tbody = document.getElementById('gravesBody');
                if (!tbody) {
                    console.error('❌ gravesBody not found!');
                    return false;
                }
                
                const rows = tbody.querySelectorAll('tr');
                
                // עדכן את כל הערכים מה-DOM
                rows.forEach(function(row, index) {
                    if (index >= window.GRAVES_CONFIG.current.length) {
                        console.warn('⚠️ Row index', index, 'out of bounds');
                        return;
                    }
                    
                    // קרא שם קבר
                    const nameInput = row.querySelector('input[type="text"]');
                    if (nameInput) {
                        window.GRAVES_CONFIG.current[index].graveNameHe = nameInput.value;
                        console.log('📝 Row', index, 'name:', nameInput.value);
                    }
                    
                    // קרא סוג חלקה
                    const plotTypeSelect = row.querySelector('select');
                    if (plotTypeSelect) {
                        window.GRAVES_CONFIG.current[index].plotType = parseInt(plotTypeSelect.value);
                        console.log('📝 Row', index, 'plotType:', plotTypeSelect.value);
                    }
                    
                    // קרא קבר קטן
                    const smallCheckbox = row.querySelector('input[type="checkbox"]');
                    if (smallCheckbox) {
                        window.GRAVES_CONFIG.current[index].isSmallGrave = smallCheckbox.checked;
                        console.log('📝 Row', index, 'isSmallGrave:', smallCheckbox.checked);
                    }
                    
                    // קרא עלות
                    const costInput = row.querySelector('input[type="number"]');
                    if (costInput) {
                        window.GRAVES_CONFIG.current[index].constructionCost = costInput.value;
                        console.log('📝 Row', index, 'cost:', costInput.value);
                    }
                });
                
                console.log('✅ All values read from DOM');
                console.log('📊 Current data:', window.GRAVES_CONFIG.current);
                
                // ========================================
                // בדיקה 1: לפחות קבר אחד
                // ========================================
                if (window.GRAVES_CONFIG.current.length === 0) {
                    alert('חובה לפחות קבר אחד באחוזה');
                    console.error('❌ Validation failed: No graves');
                    return false;
                }
                
                // ========================================
                // בדיקה 2: כל הקברים חייבים להיות עם שם
                // ========================================
                for (let i = 0; i < window.GRAVES_CONFIG.current.length; i++) {
                    const grave = window.GRAVES_CONFIG.current[i];
                    
                    if (!grave.graveNameHe || !grave.graveNameHe.trim()) {
                        alert('שם קבר ' + (i + 1) + ' הוא חובה');
                        console.error('❌ Validation failed: Grave', i, 'has no name');
                        
                        // הדגש את השדה הבעייתי
                        const tbody = document.getElementById('gravesBody');
                        const row = tbody.querySelectorAll('tr')[i];
                        if (row) {
                            const input = row.querySelector('input[type="text"]');
                            if (input) {
                                input.focus();
                                input.style.border = '2px solid red';
                                setTimeout(function() {
                                    input.style.border = '';
                                }, 2000);
                            }
                        }
                        
                        return false;
                    }
                }
                
                // ========================================
                // בדיקה 3: שמות קברים חייבים להיות ייחודיים
                // ========================================
                const names = window.GRAVES_CONFIG.current.map(function(g) {
                    return g.graveNameHe.trim().toLowerCase();
                });
                
                const uniqueNames = names.filter(function(value, index, self) {
                    return self.indexOf(value) === index;
                });
                
                if (names.length !== uniqueNames.length) {
                    alert('שמות קברים חייבים להיות ייחודיים באחוזה');
                    console.error('❌ Validation failed: Duplicate grave names');
                    console.error('Names:', names);
                    return false;
                }
                
                // ========================================
                // הכנס את הנתונים ל-hidden input
                // ========================================
                const hiddenInput = document.getElementById('gravesData');
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(window.GRAVES_CONFIG.current);
                    console.log('✅ Graves data serialized:', hiddenInput.value);
                } else {
                    console.error('❌ Hidden input #gravesData not found!');
                    return false;
                }
                
                console.log('✅ Validation passed successfully');
                return true;
            };
            
            // =========================================
            // אתחול המערכת
            // =========================================
            initGravesSystem();
            
            console.log('🎉 Area Grave Form initialized successfully');
        });
    },

    handleCustomerForm: function(itemId) {
        console.log('👤 handleCustomerForm called with itemId:', itemId);
        
        // ======================================
        // אתחול משתנים גלובליים
        // ======================================
        window.locationsData = {
            countries: [],
            cities: []
        };
   
        // איפוס SmartSelect instances
        if (window.SmartSelectManager && window.SmartSelectManager.instances) {
            delete window.SmartSelectManager.instances['countryId'];
            delete window.SmartSelectManager.instances['cityId'];
            console.log('🗑️ SmartSelect instances cleared');
        }
        
        console.log('✅ handleCustomerForm initialized with clean state');
    
        // ⭐ הסר event listeners ישנים מהשדות
        const oldCountryInput = document.getElementById('countryId');
        const oldCityInput = document.getElementById('cityId');
        
        if (oldCountryInput) {
            const newCountryInput = oldCountryInput.cloneNode(true);
            oldCountryInput.parentNode.replaceChild(newCountryInput, oldCountryInput);
        }
        
        if (oldCityInput) {
            const newCityInput = oldCityInput.cloneNode(true);
            oldCityInput.parentNode.replaceChild(newCityInput, oldCityInput);
        }
        
        console.log('✅ Form initialized - all previous state cleared');
        
        // ======================================
        // פונקציות עזר לטיפול במדינות וערים
        // ======================================

        window.populateCountries = function() {
            console.log('🌍 populateCountries called');
            
            if (!window.locationsData?.countries) {
                console.warn('⚠️ Countries data not loaded yet');
                return;
            }
            
            const countryInstance = window.SmartSelectManager?.instances['countryId'];
            
            if (!countryInstance) {
                console.warn('⚠️ Country SmartSelect instance not found');
                return;
            }
            
            // נקה אופציות
            countryInstance.optionsContainer.innerHTML = '';
            countryInstance.allOptions = [];
            
            // מלא מדינות
            window.locationsData.countries.forEach(country => {
                const option = document.createElement('div');
                option.className = 'smart-select-option';
                option.dataset.value = country.unicId;
                option.textContent = country.countryNameHe;
                
                option.addEventListener('click', function() {
                    window.SmartSelectManager.select('countryId', country.unicId);
                });
                
                countryInstance.optionsContainer.appendChild(option);
                countryInstance.allOptions.push(option);
            });
            
            // עדכן טקסט ל-"בחר מדינה..."
            countryInstance.valueSpan.textContent = 'בחר מדינה...';
            countryInstance.hiddenInput.value = '';
            
            console.log(`✅ Populated ${window.locationsData.countries.length} countries`);
        };
        
        window.loadCitiesForCountry = async function(countryId) {
            console.log('🏙️ Loading cities for country:', countryId);
    
            const cityInstance = window.SmartSelectManager?.instances['cityId'];
            
            if (!cityInstance) {
                console.warn('⚠️ City SmartSelect instance not found');
                return;
            }
            
            if (!countryId) {
                cityInstance.wrapper.classList.add('disabled');
                cityInstance.hiddenInput.disabled = true;
                cityInstance.hiddenInput.value = '';
                cityInstance.valueSpan.textContent = 'בחר קודם מדינה...';
                cityInstance.optionsContainer.innerHTML = '';
                return;
            }
            
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/cities-api.php?action=select&countryId=${countryId}`);
                const result = await response.json();
                
                if (!result.success) {
                    console.error('❌ Failed to load cities');
                    return;
                }
                
                const cities = result.data || [];
                console.log(`✅ Loaded ${cities.length} cities for country ${countryId}`);
                
                // נקה ומלא ערים
                cityInstance.optionsContainer.innerHTML = '';
                cityInstance.allOptions = [];
                
                cities.forEach(city => {
                    const option = document.createElement('div');
                    option.className = 'smart-select-option';
                    option.dataset.value = city.unicId;
                    option.textContent = city.cityNameHe;
                    
                    option.addEventListener('click', function() {
                        window.SmartSelectManager.select('cityId', city.unicId);
                    });
                    
                    cityInstance.optionsContainer.appendChild(option);
                    cityInstance.allOptions.push(option);
                });
                
                // הפעל את בחירת העיר
                cityInstance.wrapper.classList.remove('disabled');
                cityInstance.hiddenInput.disabled = false;
                
                // ⭐ תקן: עדכן טקסט ל-"בחר עיר..."
                cityInstance.hiddenInput.value = '';
                cityInstance.valueSpan.textContent = 'בחר עיר...';

                // ⭐ הסר ספינר
                hideSelectSpinner('cityId');
                
                console.log('✅ Cities populated successfully');
                
            } catch (error) {
                console.error('❌ Error loading cities:', error);
            }
        };

        // ⭐ פונקציה מתוקנת: בחירת מדינה
        window.selectCountry = function(countryId) {
            console.log('🎯 Selecting country:', countryId);
            
            const countryInput = document.getElementById('countryId');
            const countryInstance = window.SmartSelectManager?.instances['countryId'];
            
            if (!countryInput || !countryInstance) {
                console.warn('⚠️ Country input or instance not found');
                return;
            }
            
            // מצא את המדינה
            const selectedCountry = window.locationsData.countries.find(
                c => c.unicId == countryId
            );
            
            if (!selectedCountry) {
                console.warn('⚠️ Country not found in data:', countryId);
                return;
            }
            
            // ⭐ עדכן ידנית - ללא change event!
            countryInput.value = countryId;
            countryInstance.valueSpan.textContent = selectedCountry.countryNameHe;
            countryInstance.hiddenInput.value = countryId;
            
            // ⭐ סמן את האופציה הנכונה
            countryInstance.optionsContainer.querySelectorAll('.smart-select-option').forEach(opt => {
                if (opt.dataset.value == countryId) {
                    opt.classList.add('selected');
                } else {
                    opt.classList.remove('selected');
                }
            });
            
            console.log('✅ Country selected manually:', selectedCountry.countryNameHe);
        };

        // ⭐ פונקציה מתוקנת: בחירת עיר
        window.selectCity = function(cityId) {
            console.log('🎯 Selecting city:', cityId);
            
            const cityInput = document.getElementById('cityId');
            const cityInstance = window.SmartSelectManager?.instances['cityId'];
            
            if (!cityInput || !cityInstance) {
                console.warn('⚠️ City input or instance not found');
                return;
            }
            
            // מצא את שם העיר
            const selectedCityOption = Array.from(cityInstance.optionsContainer.children)
                .find(opt => opt.dataset.value == cityId);
            
            if (!selectedCityOption) {
                console.warn('⚠️ City option not found:', cityId);
                return;
            }
            
            // ⭐ עדכן ידנית - ללא change event!
            cityInput.value = cityId;
            cityInstance.valueSpan.textContent = selectedCityOption.textContent;
            cityInstance.hiddenInput.value = cityId;
            
            // ⭐ סמן את האופציה הנכונה
            cityInstance.optionsContainer.querySelectorAll('.smart-select-option').forEach(opt => {
                if (opt.dataset.value == cityId) {
                    opt.classList.add('selected');
                } else {
                    opt.classList.remove('selected');
                }
            });
            
            console.log('✅ City selected manually:', selectedCityOption.textContent);
        };
        
        // ======================================
        // חישוב תושבות - רק ללקוח חדש
        // ======================================
        if (!itemId) {
            console.log('➕ Setting up residency calculation for new customer');
            
            FormHandler.waitForElement('#customerFormModal form', (form) => {
                const typeSelect = form.elements['typeId'];
                const countrySelect = form.elements['countryId'];
                const citySelect = form.elements['cityId'];
                const residentField = form.elements['resident'];
                
                function calculateResidency() {
                    const typeId = typeSelect?.value;
                    const countryId = countrySelect?.value;
                    const cityId = citySelect?.value;
                    
                    console.log('🧮 Calculating residency:', { typeId, countryId, cityId });
                    
                    if (typeId == 2) {
                        updateResidencyField(3);
                        return;
                    }
                    
                    if (!countryId) {
                        updateResidencyField(3);
                        return;
                    }
                    
                    fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=calculate_residency', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ typeId, countryId, cityId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.residency) {
                            updateResidencyField(data.residency);
                        }
                    })
                    .catch(error => console.error('Error calculating residency:', error));
                }
                
                function updateResidencyField(value) {
                    if (residentField) {
                        residentField.value = value;
                        const colors = {
                            1: '#e8f5e9',
                            2: '#e3f2fd',
                            3: '#fff3e0'
                        };
                        residentField.style.backgroundColor = colors[value] || '#f5f5f5';
                        console.log('✅ Residency updated:', value);
                    }
                }
                
                if (typeSelect) typeSelect.addEventListener('change', calculateResidency);
                if (countrySelect) countrySelect.addEventListener('change', calculateResidency);
                if (citySelect) citySelect.addEventListener('change', calculateResidency);
                
                calculateResidency();
            });
        }
        
        // ======================================
        // טעינת מדינות מה-API
        // ======================================
        (async function loadLocations() {
            try {
                console.log('🌐 Starting to load countries from API...');

                // ⭐ הוסף ספינר למדינות
                const countryInput = document.getElementById('countryId');
                if (countryInput) {
                    showSelectSpinner('countryId');
                }
                
                const countriesResponse = await fetch('/dashboard/dashboards/cemeteries/api/countries-api.php?action=select');
                const countriesResult = await countriesResponse.json();
                
                if (!countriesResult.success) {
                    console.error('❌ Failed to load countries data');
                    return;
                }
                
                window.locationsData.countries = countriesResult.data || [];
                
                console.log(`✅ Loaded ${window.locationsData.countries.length} countries`);
                
                if (!countryInput) {
                    console.warn('⚠️ Country input not found yet, will retry...');
                    setTimeout(loadLocations, 500);
                    return;
                }
                
                // אתחל SmartSelect
                if (window.SmartSelectManager) {
                    SmartSelectManager.init();
                    console.log('✅ SmartSelect initialized');
                }
                
                // אכלס מדינות
                window.populateCountries();

                // ⭐ הסר ספינר
                hideSelectSpinner('countryId');
                
                // הגדר listener לשינוי מדינה
                countryInput.addEventListener('change', async function() {
                    const countryId = this.value;
                    console.log('🌍 Country changed:', countryId);
                    await window.loadCitiesForCountry(countryId);
                });
                
                console.log('✅ Country-City dependency set up');
                
                // ======================================
                // אם זה עריכה - טען נתוני לקוח
                // ======================================
                if (itemId) {
                    console.log('📋 Loading customer data for edit mode...');
                    
                    const form = document.querySelector('#customerFormModal form');
                    if (!form) {
                        console.error('❌ Form not found');
                        return;
                    }
                    
                    console.log('📋 [BEFORE] firstName:', form.elements['firstName']?.value);
                    console.log('📋 [BEFORE] lastName:', form.elements['lastName']?.value);
                    
                    const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${itemId}`);
                    const result = await response.json();
                    
                    if (!result.success || !result.data) {
                        console.error('❌ Failed to load customer data:', result);
                        alert('שגיאה בטעינת נתוני הלקוח');
                        return;
                    }
                    
                    const customer = result.data;
                    
                    console.log('✅ [API returned] unicId:', customer.unicId);
                    console.log('✅ [API returned] firstName:', customer.firstName);
                    console.log('✅ [API returned] lastName:', customer.lastName);
                    console.log('✅ [API returned] countryId:', customer.countryId);
                    console.log('✅ [API returned] cityId:', customer.cityId);
                    
                    // מלא את כל השדות
                    Object.keys(customer).forEach(key => {
                        const field = form.elements[key];
                        if (!field) return;
                        
                        if (field.type === 'checkbox') {
                            field.checked = customer[key] == 1;
                        } else if (field.type === 'select-one') {
                            field.value = customer[key] || '';
                            
                            if (key === 'resident' && field.disabled) {
                                field.value = customer[key] || 3;
                                const colors = {
                                    '1': '#e8f5e9',
                                    '2': '#e3f2fd',
                                    '3': '#fff3e0'
                                };
                                field.style.backgroundColor = colors[customer[key]] || '#f5f5f5';
                                
                                const hiddenField = form.elements['resident_hidden'];
                                if (hiddenField) {
                                    hiddenField.value = customer[key] || 3;
                                }
                            }
                        } else {
                            field.value = customer[key] || '';
                        }
                    });

                    // ⭐ טען מדינה ועיר בצורה נכונה
                    if (customer.countryId) {
                        // בחר מדינה
                        window.selectCountry(customer.countryId);
                        
                        // טען ערים למדינה זו
                        await window.loadCitiesForCountry(customer.countryId);
                        
                        // המתן רגע ואז בחר עיר
                        if (customer.cityId) {
                            setTimeout(() => {
                                window.selectCity(customer.cityId);  // ⭐ פשוט יותר!
                            }, 500);  // ⭐ תן יותר זמן (500ms)
                        }
                    }
                }
                
            } catch (error) {
                console.error('❌ Error loading locations:', error);
                alert('שגיאה בטעינת רשימת המדינות והערים');
            }
        })();
    },

    /**
     * טיפול בכרטיס קבר
     * ⭐ גרסה מתוקנת - שולפת areaGraveId מהשרת
     * @param {string} itemId - מזהה הקבר (unicId)
     */
    handleGraveCardForm: async function(itemId) {
        console.log('🪦 [GraveCard] אתחול כרטיס קבר:', itemId);
        
        // ⭐⭐⭐ שלוף את נתוני הקבר מהשרת תחילה!
        let graveData = null;
        try {
            console.log('🔍 שולף נתוני קבר מהשרת...');
            const response = await fetch(`${API_BASE}graves-api.php?action=get&id=${itemId}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                graveData = result.data;
                console.log('✅ נתוני קבר נשלפו:', graveData);
            } else {
                console.error('❌ לא הצלחנו לשלוף נתוני קבר');
            }
        } catch (error) {
            console.error('❌ שגיאה בשליפת נתוני קבר:', error);
        }
        
        // חכה שהטופס יהיה מוכן
        this.waitForElement('#graveCardFormModal', (modal) => {
            console.log('✅ [GraveCard] Modal נטען');
            
            // קרא נתונים מה-hidden fields
            const unicIdField = modal.querySelector('input[name="unicId"]');
            const statusField = modal.querySelector('input[name="currentGraveStatus"]');
            
            if (!unicIdField || !statusField) {
                console.error('❌ [GraveCard] Hidden fields לא נמצאו!');
                return;
            }
            
            const currentGrave = {
                unicId: unicIdField.value,
                graveStatus: parseInt(statusField.value),
                areaGraveId: graveData?.areaGraveId || graveData?.area_grave_id  // ⭐ הוסף!
            };
            
            console.log('📋 [GraveCard] נתוני קבר:', currentGrave);
            
            // ⭐⭐⭐ עדכן את hidden field של areaGraveId (אם לא קיים)
            let areaGraveIdField = modal.querySelector('input[name="areaGraveId"]');
            if (!areaGraveIdField && currentGrave.areaGraveId) {
                console.log('⚙️ יוצר hidden field ל-areaGraveId');
                areaGraveIdField = document.createElement('input');
                areaGraveIdField.type = 'hidden';
                areaGraveIdField.name = 'areaGraveId';
                areaGraveIdField.value = currentGrave.areaGraveId;
                modal.querySelector('form').appendChild(areaGraveIdField);
            }
            
            // החלף כפתורים בפוטר
            updateGraveCardFooter(modal, currentGrave);
            
            // הגדר פונקציות לכפתורים
            setupGraveCardButtons(modal, currentGrave);
        });
        
        // ========================================
        // פונקציה: עדכון כפתורים בפוטר
        // ========================================
        function updateGraveCardFooter(modal, grave) {
            const footer = modal.querySelector('.modal-footer');
            if (!footer) return;
            
            const status = grave.graveStatus;
            let buttonsHTML = '';
            
            // כפתור סגור - תמיד
            buttonsHTML += '<button type="button" class="btn btn-secondary" onclick="FormHandler.closeForm(\'graveCard\')"><i class="fas fa-times"></i> סגור</button>';
            
            // לפי סטטוס
            if (status === 1) {
                // פנוי - כל האופציות
                buttonsHTML += '<button type="button" class="btn btn-warning" id="btnSaveGrave"><i class="fas fa-bookmark"></i> שמור קבר</button>';
                buttonsHTML += `<button type="button" class="btn btn-success btn-open-purchase"><i class="fas fa-shopping-cart"></i> רכישה חדשה</button>`
                buttonsHTML += `<button type="button" class="btn btn-info btn-open-burial"><i class="fas fa-cross"></i> + קבורה חדשה</button>`
            } else if (status === 2) {
                // נרכש - רק קבורה
                buttonsHTML += `<button type="button" class="btn btn-info btn-open-burial"><i class="fas fa-cross"></i> + קבורה חדשה</button>`
            } else if (status === 4) {
                // שמור - בטל שמירה
                buttonsHTML += '<button type="button" class="btn btn-danger" id="btnCancelSaved"><i class="fas fa-ban"></i> בטל שמירה</button>';
            }
            // סטטוס 3 (קבור) - אין כפתורים נוספים
            
            footer.innerHTML = buttonsHTML;
            console.log('✅ [GraveCard] כפתורים עודכנו לסטטוס:', status);
        }
        
        // ========================================
        // פונקציה: הגדרת אירועים לכפתורים
        // ========================================
        function setupGraveCardButtons(modal, grave) {
            // כפתור שמור קבר
            const btnSave = modal.querySelector('#btnSaveGrave');
            if (btnSave) {
                btnSave.onclick = async function() {
                    try {
                        const response = await fetch('/dashboard/dashboards/cemeteries/api/graves-api.php?action=update', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                unicId: grave.unicId,
                                graveStatus: 4,
                                saveDate: new Date().toISOString().split('T')[0]
                            })
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            FormHandler.showMessage('הקבר נשמר בהצלחה', 'success');
                            FormHandler.closeForm('graveCard');
                            if (typeof refreshData === 'function') refreshData();
                        } else {
                            FormHandler.showMessage('שגיאה: ' + result.error, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        FormHandler.showMessage('שגיאה בשמירת הקבר', 'error');
                    }
                };
            }
            
            // כפתור ביטול שמירה
            const btnCancel = modal.querySelector('#btnCancelSaved');
            if (btnCancel) {
                btnCancel.onclick = async function() {
                    try {
                        const response = await fetch('/dashboard/dashboards/cemeteries/api/graves-api.php?action=update', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                unicId: grave.unicId,
                                graveStatus: 1,
                                saveDate: null
                            })
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            FormHandler.showMessage('השמירה בוטלה בהצלחה', 'success');
                            FormHandler.closeForm('graveCard');
                            if (typeof refreshData === 'function') refreshData();
                        } else {
                            FormHandler.showMessage('שגיאה: ' + result.error, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        FormHandler.showMessage('שגיאה בביטול השמירה', 'error');
                    }
                };
            }

            // כפתורי רכישה
            const purchaseButtons = modal.querySelectorAll('.btn-open-purchase');
            purchaseButtons.forEach(btn => {
                btn.onclick = function() {
                    FormHandler.closeForm('graveCard');
                    FormHandler.openForm('purchase', grave.unicId, null);
                };
            });

            // כפתורי קבורה
            const burialButtons = modal.querySelectorAll('.btn-open-burial');
            burialButtons.forEach(btn => {
                btn.onclick = function() {
                    FormHandler.closeForm('graveCard');
                    FormHandler.openForm('burial', grave.unicId, null);
                };
            });
            
            // כפתורי עריכה (אם קיימים)
            const btnEditPurchase = modal.querySelector('[onclick*="editPurchase"]');
            if (btnEditPurchase) {
                const purchaseId = btnEditPurchase.getAttribute('onclick').match(/'([^']+)'/)[1];
                btnEditPurchase.onclick = function() {
                    FormHandler.closeForm('graveCard');
                    FormHandler.openForm('purchase', null, purchaseId);
                };
            }
            
            const btnEditBurial = modal.querySelector('[onclick*="editBurial"]');
            if (btnEditBurial) {
                const burialId = btnEditBurial.getAttribute('onclick').match(/'([^']+)'/)[1];
                btnEditBurial.onclick = function() {
                    FormHandler.closeForm('graveCard');
                    FormHandler.openForm('burial', null, burialId);
                };
            }
        }
    },

    /**
     * מילוי מדינות מה-API
     */
    populateCountriesFromAPI: function(countries, selectedValue = '') {
        const countryInstance = window.SmartSelectManager?.instances['countryId'];
        
        if (!countryInstance) {
            console.warn('⚠️ Country SmartSelect not found');
            return;
        }
        
        // נקה אופציות
        countryInstance.optionsContainer.innerHTML = '';
        countryInstance.allOptions = [];
        
        // מלא מדינות
        countries.forEach(country => {
            const option = document.createElement('div');
            option.className = 'smart-select-option';
            option.dataset.value = country.unicId;
            option.textContent = country.countryNameHe;
            
            if (country.unicId == selectedValue) {
                option.classList.add('selected');
            }
            
            option.addEventListener('click', function() {
                window.SmartSelectManager.select('countryId', country.unicId);
            });
            
            countryInstance.optionsContainer.appendChild(option);
            countryInstance.allOptions.push(option);
        });
        
        // עדכן תצוגה
        if (selectedValue) {
            const selected = countries.find(c => c.unicId == selectedValue);
            if (selected) {
                countryInstance.valueSpan.textContent = selected.countryNameHe;
                countryInstance.hiddenInput.value = selectedValue;
            }
        } else {
            countryInstance.valueSpan.textContent = 'בחר מדינה...';
        }
        
        console.log('✅ Populated', countries.length, 'countries');
    },

    /**
     * הגדרת תלות מדינה←→עיר מה-API
     */
    setupCountryCityFromAPI: function(initialCountryId = '', initialCityId = '') {
        const countryInput = document.getElementById('countryId');
        const cityInstance = window.SmartSelectManager?.instances['cityId'];
        
        if (!countryInput || !cityInstance) {
            console.warn('⚠️ Country or City SmartSelect not found');
            return;
        }
        
        // פונקציה לטעינת ערים
        const loadCities = (countryId, selectCityId = '') => {
            if (!countryId) {
                cityInstance.wrapper.classList.add('disabled');
                cityInstance.hiddenInput.disabled = true;
                cityInstance.valueSpan.textContent = 'בחר קודם מדינה...';
                cityInstance.optionsContainer.innerHTML = '';
                return;
            }
            
            console.log('🏙️ Loading cities for country:', countryId);
            
            fetch(`/dashboard/dashboards/cemeteries/api/locations-api.php?action=getCities&countryId=${countryId}`)
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error('Failed to load cities');
                    }
                    
                    const cities = result.data;
                    console.log('✅ Loaded', cities.length, 'cities');
                    
                    // נקה אופציות
                    cityInstance.optionsContainer.innerHTML = '';
                    cityInstance.allOptions = [];
                    
                    // מלא ערים
                    cities.forEach(city => {
                        const option = document.createElement('div');
                        option.className = 'smart-select-option';
                        option.dataset.value = city.unicId;
                        option.textContent = city.cityNameHe;
                        
                        if (city.unicId == selectCityId) {
                            option.classList.add('selected');
                        }
                        
                        option.addEventListener('click', function() {
                            window.SmartSelectManager.select('cityId', city.unicId);
                        });
                        
                        cityInstance.optionsContainer.appendChild(option);
                        cityInstance.allOptions.push(option);
                    });
                    
                    // הפעל את ה-select
                    cityInstance.wrapper.classList.remove('disabled');
                    cityInstance.hiddenInput.disabled = false;
                    
                    // עדכן ערך נבחר
                    if (selectCityId) {
                        const selected = cities.find(c => c.unicId == selectCityId);
                        if (selected) {
                            cityInstance.valueSpan.textContent = selected.cityNameHe;
                            cityInstance.hiddenInput.value = selectCityId;
                        }
                    } else {
                        cityInstance.valueSpan.textContent = 'בחר עיר...';
                    }
                })
                .catch(error => {
                    console.error('❌ Error loading cities:', error);
                    alert('שגיאה בטעינת רשימת הערים');
                });
        };
        
        // האזן לשינוי מדינה
        countryInput.addEventListener('change', function() {
            loadCities(this.value);
        });
        
        // אם בעריכה - טען ערים של המדינה הנוכחית
        if (initialCountryId) {
            setTimeout(() => {
                loadCities(initialCountryId, initialCityId);
            }, 100);
        }
        
        console.log('✅ Country-City dependency set up');
    },

    handlePurchaseForm: function(itemId) {
        // אתחול משתנים גלובליים
        window.formInitialized = false;
        window.purchasePayments = [];
        window.selectedGraveData = null;
        window.selectedCustomerData = null;
        window.isEditMode = !!itemId;
        window.hierarchyData = {
            cemeteries: [],
            blocks: [],
            plots: [],
            rows: [],
            areaGraves: [],
            graves: []
        };

        // ✅ הוסף את זה כאן - מיד בהתחלה!
        // ===========================================================
        // פונקציות להיררכית בתי עלמין
        // ===========================================================
        
        // פונקציות placeholder להיררכיה
        window.filterHierarchy = function(level) {
            console.log(`📍 filterHierarchy called with level: ${level}`);
            
            const clearSelect = (selectId) => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = '<option value="">-- בחר --</option>';
                }
            };
            
            switch(level) {
                case 'cemetery':
                    window.populateBlocks();
                    clearSelect('plotSelect');
                    clearSelect('rowSelect');
                    clearSelect('areaGraveSelect');
                    clearSelect('graveSelect');
                    // ✅ כבה את כל השדות למטה
                    window.toggleSelectState('rowSelect', false);
                    window.toggleSelectState('areaGraveSelect', false);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'block':
                    window.populatePlots();
                    clearSelect('rowSelect');
                    clearSelect('areaGraveSelect');
                    clearSelect('graveSelect');
                    // ✅ כבה את כל השדות למטה
                    window.toggleSelectState('rowSelect', false);
                    window.toggleSelectState('areaGraveSelect', false);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'plot':
                    window.populateRows();
                    clearSelect('areaGraveSelect');
                    clearSelect('graveSelect');
                    // ✅ הפעל שורות, כבה את השאר
                    window.toggleSelectState('rowSelect', true);
                    window.toggleSelectState('areaGraveSelect', false);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'row':
                    window.populateAreaGraves();
                    clearSelect('graveSelect');
                    // ✅ הפעל אחוזות, כבה קברים
                    window.toggleSelectState('areaGraveSelect', true);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'areaGrave':
                    window.populateGraves();
                    // ✅ הפעל קברים
                    window.toggleSelectState('graveSelect', true);
                    break;
            }
        };
 
        // ✅ פונקציית עזר להפעלה/כיבוי של select
        window.toggleSelectState = function(selectId, enabled) {
            const select = document.getElementById(selectId);
            if (select) {
                select.disabled = !enabled;
                if (!enabled) {
                    select.style.opacity = '0.5';
                    select.style.cursor = 'not-allowed';
                } else {
                    select.style.opacity = '1';
                    select.style.cursor = 'pointer';
                }
            }
        };

        window.populateBlocks = function() {
            console.log('📦 populateBlocks called');
            
            if (!window.hierarchyData?.blocks) {
                console.warn('⚠️ Blocks data not loaded yet');
                return;
            }
            
            const cemeteryId = document.getElementById('cemeterySelect')?.value;
            const blockSelect = document.getElementById('blockSelect');
            
            if (!blockSelect || !cemeteryId) {
                console.warn('⚠️ Block select or cemetery not found');
                return;
            }
            
            blockSelect.innerHTML = '<option value="">-- בחר גוש --</option>';
            
            const relevantBlocks = window.hierarchyData.blocks.filter(block => {
                return block.cemetery_id == cemeteryId ||
                    block.cemeteryId == cemeteryId ||
                    block.cemId == cemeteryId ||
                    block.cemetery == cemeteryId;
            });
            
            console.log(`📦 Found ${relevantBlocks.length} blocks`);
            
            relevantBlocks.forEach(block => {
                const option = document.createElement('option');
                option.value = block.unicId;
                option.textContent = block.blockNameHe;
                
                if (block.has_current_grave) {
                    option.selected = true;
                }
                
                blockSelect.appendChild(option);
            });
            
            console.log('✅ Blocks populated successfully');
            window.toggleSelectState('blockSelect', true);
        };

        window.populatePlots = function() {
            console.log('📊 populatePlots called');
            
            if (!window.hierarchyData?.plots) {
                console.warn('⚠️ Plots data not loaded yet');
                return;
            }
            
            const blockId = document.getElementById('blockSelect')?.value;
            const plotSelect = document.getElementById('plotSelect');
            
            if (!plotSelect || !blockId) {
                console.warn('⚠️ Plot select or block not found');
                return;
            }
            
            plotSelect.innerHTML = '<option value="">-- בחר חלקה --</option>';
            
            const relevantPlots = window.hierarchyData.plots.filter(plot => {
                return plot.blockId == blockId ||
                    plot.block_id == blockId ||
                    plot.unicBlockId == blockId;
            });
            
            console.log(`📊 Found ${relevantPlots.length} plots`);
            
            relevantPlots.forEach(plot => {
                const option = document.createElement('option');
                option.value = plot.unicId;
                option.textContent = plot.plotNameHe;
                
                if (plot.has_current_grave) {
                    option.selected = true;
                }
                
                plotSelect.appendChild(option);
            });
            
            console.log('✅ Plots populated successfully');
            window.toggleSelectState('plotSelect', true);
        };

        window.populateRows = function() {
            console.log('📏 populateRows called');
            
            if (!window.hierarchyData?.rows) {
                console.warn('⚠️ Rows data not loaded yet');
                return;
            }
            
            const plotId = document.getElementById('plotSelect')?.value;
            const rowSelect = document.getElementById('rowSelect');
            
            if (!rowSelect || !plotId) {
                console.warn('⚠️ Row select or plot not found');
                return;
            }
            
            rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
            
            const relevantRows = window.hierarchyData.rows.filter(row => {
                return row.plotId == plotId ||
                    row.plot_id == plotId ||
                    row.unicPlotId == plotId;
            });
            
            console.log(`📏 Found ${relevantRows.length} rows`);
            
            relevantRows.forEach(row => {
                const option = document.createElement('option');
                option.value = row.unicId;
                option.textContent = row.lineNameHe || row.rowNameHe || `שורה ${row.serialNumber}`;
                
                if (row.has_current_grave) {
                    option.selected = true;
                }
                
                rowSelect.appendChild(option);
            });
            
            console.log('✅ Rows populated successfully');
        };

        window.populateAreaGraves = function() {
            console.log('🏘️ populateAreaGraves called');
            
            if (!window.hierarchyData?.areaGraves) {
                console.warn('⚠️ AreaGraves data not loaded yet');
                return;
            }
            
            const rowId = document.getElementById('rowSelect')?.value;
            const areaGraveSelect = document.getElementById('areaGraveSelect');
            
            if (!areaGraveSelect || !rowId) {
                console.warn('⚠️ AreaGrave select or row not found');
                return;
            }
            
            areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
            
            const relevantAreaGraves = window.hierarchyData.areaGraves.filter(ag => {
                return ag.lineId == rowId ||
                    ag.line_id == rowId ||
                    ag.rowId == rowId ||
                    ag.row_id == rowId ||
                    ag.unicLineId == rowId;
            });
            
            console.log(`🏘️ Found ${relevantAreaGraves.length} areaGraves`);
            
            relevantAreaGraves.forEach(ag => {
                const option = document.createElement('option');
                option.value = ag.unicId;
                option.textContent = ag.areaGraveNameHe || `אחוזה ${ag.serialNumber}`;
                
                if (ag.has_current_grave) {
                    option.selected = true;
                }
                
                areaGraveSelect.appendChild(option);
            });
            
            console.log('✅ AreaGraves populated successfully');
        };

        window.populateGraves = function() {
            console.log('⚰️ populateGraves called');
            
            if (!window.hierarchyData?.graves) {
                console.warn('⚠️ Graves data not loaded yet');
                return;
            }
            
            const areaGraveId = document.getElementById('areaGraveSelect')?.value;
            const graveSelect = document.getElementById('graveSelect');
            
            if (!graveSelect || !areaGraveId) {
                console.warn('⚠️ Grave select or areaGrave not found');
                return;
            }
            
            graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
            
            const relevantGraves = window.hierarchyData.graves.filter(grave => {
                return grave.areaGraveId == areaGraveId ||
                    grave.area_grave_id == areaGraveId ||
                    grave.unicAreaGraveId == areaGraveId;
            });
            
            console.log(`⚰️ Found ${relevantGraves.length} graves`);
            
            relevantGraves.forEach(grave => {
                const option = document.createElement('option');
                option.value = grave.unicId;
                option.textContent = `קבר ${grave.graveNameHe || grave.serialNumber}`;
                
                if (grave.is_current) {
                    option.selected = true;
                }
                
                graveSelect.appendChild(option);
            });
            
            console.log('✅ Graves populated successfully');
        };

        // ⭐ פונקציה למילוי לקוחות ב-SmartSelect
        window.populateCustomers = function(customers) {
            console.log('👥 populateCustomers called with', customers.length, 'customers');
            
            const customerInstance = window.SmartSelectManager?.instances['clientId'];
            
            if (!customerInstance) {
                console.warn('⚠️ Customer SmartSelect instance not found');
                return;
            }
            
            // נקה אופציות
            customerInstance.optionsContainer.innerHTML = '';
            customerInstance.allOptions = [];
            
            // מלא לקוחות
            customers.forEach(customer => {
                const option = document.createElement('div');
                option.className = 'smart-select-option';
                option.dataset.value = customer.unicId;
                option.dataset.resident = customer.resident || 3;
                
                let displayText = `${customer.firstName} ${customer.lastName}`;
                if (customer.phone || customer.phoneMobile) {
                    displayText += ` - ${customer.phone || customer.phoneMobile}`;
                }
                
                option.textContent = displayText;
                
                // ⭐ סמן אם זה לקוח נוכחי
                if (customer.is_current) {
                    option.classList.add('selected');
                }
                
                option.addEventListener('click', function() {
                    window.SmartSelectManager.select('clientId', customer.unicId);
                    
                    // ⭐ שמור את נתוני הלקוח
                    window.selectedCustomerData = {
                        id: customer.unicId,
                        resident: customer.resident || 3,
                        name: `${customer.firstName} ${customer.lastName}`
                    };
                    
                    console.log('👤 לקוח נבחר:', window.selectedCustomerData);
                    
                    // עדכן פרמטרים וחשב תשלומים
                    if (window.selectedGraveData && window.updatePaymentParameters) {
                        window.updatePaymentParameters();
                    }
                    window.tryCalculatePayments();
                });
                
                customerInstance.optionsContainer.appendChild(option);
                customerInstance.allOptions.push(option);
            });
            
            // עדכן טקסט ל-"בחר לקוח..."
            if (!customers.some(c => c.is_current)) {
                customerInstance.valueSpan.textContent = 'בחר לקוח...';
                customerInstance.hiddenInput.value = '';
            }
            
            console.log(`✅ Populated ${customers.length} customers`);
        };

        // ⭐ פונקציה לבחירת לקוח (במצב עריכה)
        window.selectCustomer = function(customerId, customerName) {
            console.log('🎯 Selecting customer:', customerId, customerName);
            
            const customerInput = document.getElementById('clientId');
            const customerInstance = window.SmartSelectManager?.instances['clientId'];
            
            if (!customerInput || !customerInstance) {
                console.warn('⚠️ Customer input or instance not found');
                return;
            }
            
            // עדכן ידנית
            customerInput.value = customerId;
            customerInstance.valueSpan.textContent = customerName;
            customerInstance.hiddenInput.value = customerId;
            
            // סמן את האופציה הנכונה
            customerInstance.optionsContainer.querySelectorAll('.smart-select-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.value == customerId);
            });
            
            console.log('✅ Customer selected:', customerName);
        };
  
        // ===========================================================
        // סוף פונקציות להיררכית בתי עלמין
        // ===========================================================

        // פונקציה לחישוב תשלומים
        window.tryCalculatePayments = async function() {
            if (window.isEditMode) return;
            if (!window.formInitialized) return;
            if (!window.selectedGraveData || !window.selectedCustomerData) return;
            
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        plotType: window.selectedGraveData.plotType,
                        graveType: window.selectedGraveData.graveType,
                        resident: window.selectedCustomerData?.resident || 3,
                        buyerStatus: document.querySelector('[name="buyer_status"]')?.value || null
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.payments) {
                    window.purchasePayments = [];
                    const mandatoryPayments = data.payments.filter(p => p.mandatory);
                    
                    mandatoryPayments.forEach(payment => {
                        window.purchasePayments.push({
                            locked: false,
                            required: true,
                            paymentDate: "",
                            paymentType: payment.priceDefinition || 1,
                            paymentAmount: parseFloat(payment.price) || 0,
                            receiptDocuments: [],
                            customPaymentType: payment.name,
                            isPaymentComplete: false,
                            mandatory: true
                        });
                    });
                    
                    if (window.displayPaymentsSummary) {
                        document.getElementById('paymentsDisplay').innerHTML = 
                            PaymentDisplayManager.render(window.purchasePayments, 'summary');
                    }
                    document.getElementById('total_price').value = PaymentDisplayManager.calculateTotal();
                    document.getElementById('paymentsList').value = JSON.stringify(window.purchasePayments);
                }
            } catch (error) {
                console.error('Error calculating payments:', error);
            }
        };

        // פונקציה לעדכון תצוגת פרמטרים
        window.updatePaymentParameters = function() {
            if (window.selectedGraveData) {
                const plotTypes = {1: 'פטורה', 2: 'חריגה', 3: 'סגורה'};
                const graveTypes = {1: 'שדה', 2: 'רוויה', 3: 'סנהדרין'};
                const residentTypes = {1: 'ירושלים', 2: 'ישראל', 3: 'חו"ל'};

                const residentValue = window.selectedCustomerData?.resident || 3;
                const residentText = residentTypes[residentValue] || 'לא ידוע';
                
                const displayElement = document.getElementById('parametersDisplay');
                if (displayElement) {
                    displayElement.innerHTML = `
                        <span style="margin-right: 10px;">📍 חלקה: ${plotTypes[window.selectedGraveData.plotType] || 'לא ידוע'}</span>
                        <span style="margin-right: 10px;">⚰️ סוג קבר: ${graveTypes[window.selectedGraveData.graveType] || 'לא ידוע'}</span>
                        <span>👤 תושב: ${residentText}</span>
                    `;
                }
                
                const paramsElement = document.getElementById('selectedParameters');
                if (paramsElement) {
                    paramsElement.style.display = 'block';
                }
                
                const buttonText = document.getElementById('paymentsButtonText');
                if (buttonText) {
                    buttonText.textContent = 'חשב מחדש תשלומים';
                }
            }
        };

        // פתיחת מנהל תשלומים חכם
        window.openSmartPaymentsManager = async function() {
            // בינתיים ללא בדיקת קבר - נוסיף אחר כך
            
            if (!window.selectedCustomerData?.resident) {
                alert('יש לבחור לקוח תחילה');
                return;
            }

            const isEditMode = window.isEditMode === true;

            if (isEditMode) {
                ExistingPaymentsManager.open();
            } else {
                try {
                    const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            plotType: window.selectedGraveData?.plotType || -1,
                            graveType: window.selectedGraveData?.graveType || -1,
                            resident: window.selectedCustomerData?.resident,
                            buyerStatus: document.querySelector('[name="buyer_status"]')?.value || null
                        })
                    });
                    
                    const data = await response.json();

                    if (data.success && data.payments) {
                        SmartPaymentsManager.open(data.payments || []);
                    } else if (data.success && !data.payments) {
                        SmartPaymentsManager.open([]);
                    } else {
                        alert('שגיאה בטעינת הגדרות תשלום');
                        console.error('Error loading payments:', data);
                    }
                } catch (error) {
                    console.error('Error loading payments:', error);
                    alert('שגיאה בטעינת התשלומים');
                }
            }
        };

        // אירוע לבחירת לקוח
        const setupCustomerListener = function() {
            const customerSelect = document.querySelector('[name="clientId"]');
            if (customerSelect) {
                customerSelect.addEventListener('change', async function() {
                    const customerId = this.value;
                    
                    if (customerId) {
                        // ✅ קרא את resident ישירות מה-option
                        const selectedOption = this.options[this.selectedIndex];
                        const resident = parseInt(selectedOption.dataset.resident) || 3;
                        
                        // ✅ שמור מיד ללא קריאת API נוספת
                        window.selectedCustomerData = {
                            id: customerId,
                            resident: resident,
                            name: selectedOption.textContent.split(' - ')[0] // חתוך את הטלפון
                        };
                        
                        console.log('👤 לקוח נבחר:', window.selectedCustomerData);
                        
                        // ✅ עדכן פרמטרים
                        if (window.selectedGraveData && window.updatePaymentParameters) {
                            window.updatePaymentParameters();
                        }
                        
                        // ✅ חשב תשלומים
                        await window.tryCalculatePayments();
                        
                    } else {
                        // ✅ ניקוי בחירה
                        window.selectedCustomerData = null;
                        
                        if (!window.isEditMode) {
                            window.purchasePayments = [];
                            document.getElementById('total_price').value = '0.00';
                            document.getElementById('paymentsDisplay').innerHTML = '<p style="color: #999;">לא הוגדרו תשלומים</p>';
                            document.getElementById('paymentsList').value = '[]';
                        }
                    }
                });
            }
        };

        // מנהל תשלומים חכם לרכישה חדשה
        const SmartPaymentsManager = {
            open: function(availablePayments) {
                const mandatoryPayments = availablePayments.filter(p => p.mandatory);
                const optionalPayments = availablePayments.filter(p => !p.mandatory);
                
                const modal = document.createElement('div');
                modal.id = 'smartPaymentsModal';
                modal.className = 'modal-overlay';
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10001;
                `;
                
                const currentTotal = mandatoryPayments.reduce((sum, p) => sum + parseFloat(p.price || 0), 0);
                
                modal.innerHTML = `
                    <div class="modal-content" style="
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        width: 700px;
                        max-height: 90vh;
                        overflow-y: auto;
                        margin: 20px;
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;">חישוב תשלומים אוטומטי</h3>
                            <button onclick="SmartPaymentsManager.close()" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                            ">×</button>
                        </div>
                        
                        ${mandatoryPayments.length > 0 ? `
                            <div style="margin-bottom: 20px;">
                                <h4 style="color: #dc3545; margin-bottom: 10px;">
                                    <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">חובה</span>
                                    תשלומים הכרחיים
                                </h4>
                                <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                                    ${mandatoryPayments.map(payment => `
                                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ffe5b4;">
                                            <label style="display: flex; align-items: center;">
                                                <input type="checkbox" checked disabled style="margin-left: 10px;">
                                                <span style="font-weight: bold; margin-right: 10px;">${payment.name}</span>
                                            </label>
                                            <span style="font-weight: bold; color: #dc3545;">₪${parseFloat(payment.price).toLocaleString()}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #28a745; margin-bottom: 10px;">
                                <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">אופציונלי</span>
                                תשלומים נוספים
                            </h4>
                            <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                                <div id="optionalPaymentsList">
                                    ${optionalPayments.map(payment => `
                                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb;">
                                            <label style="display: flex; align-items: center; cursor: pointer;">
                                                <input type="checkbox" 
                                                    data-price="${payment.price}"
                                                    data-name="${payment.name}"
                                                    data-definition="${payment.priceDefinition}"
                                                    onchange="SmartPaymentsManager.updateTotal()"
                                                    style="margin-left: 10px;">
                                                <span style="margin-right: 10px;">${payment.name}</span>
                                            </label>
                                            <span>₪${parseFloat(payment.price).toLocaleString()}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                <div style="border-top: 2px solid #28a745; margin-top: 15px; padding-top: 15px;">
                                    <h5 style="margin-bottom: 10px;">הוסף תשלום מותאם:</h5>
                                    <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end;">
                                        <div>
                                            <input type="text" id="customPaymentName" placeholder="סיבת תשלום" 
                                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        <div>
                                            <input type="number" id="customPaymentAmount" step="0.01" min="0" placeholder="0.00" 
                                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        <button onclick="SmartPaymentsManager.addCustomPayment()" style="
                                            padding: 8px 15px;
                                            background: #17a2b8;
                                            color: white;
                                            border: none;
                                            border-radius: 4px;
                                            cursor: pointer;
                                        ">+ הוסף</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold;">
                                סה"כ לתשלום: ₪<span id="smartModalTotal">${currentTotal.toLocaleString()}</span>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                כולל ${mandatoryPayments.length} תשלומי חובה
                                <span id="optionalCount"></span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="SmartPaymentsManager.close()" style="
                                padding: 10px 30px;
                                background: #6c757d;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">ביטול</button>
                            <button onclick="SmartPaymentsManager.apply('${JSON.stringify(mandatoryPayments).replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" style="
                                padding: 10px 30px;
                                background: #28a745;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                                font-weight: bold;
                            ">אישור ושמירה</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            },
            
            updateTotal: function() {
                let total = 0;
                let optionalCount = 0;
                
                const modal = document.getElementById('smartPaymentsModal');
                if (!modal) return;
                
                const mandatoryCheckboxes = modal.querySelectorAll('input[type="checkbox"]:disabled:checked');
                mandatoryCheckboxes.forEach(cb => {
                    const parentDiv = cb.closest('div[style*="padding"]');
                    if (parentDiv) {
                        const spans = parentDiv.querySelectorAll('span');
                        const priceSpan = spans[spans.length - 1];
                        if (priceSpan) {
                            const cleanPrice = priceSpan.textContent.replace(/[₪,\s]/g, '');
                            const price = parseFloat(cleanPrice);
                            if (!isNaN(price)) total += price;
                        }
                    }
                });
                
                const optionalCheckboxes = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
                optionalCheckboxes.forEach(cb => {
                    const price = parseFloat(cb.dataset.price);
                    if (!isNaN(price)) {
                        total += price;
                        optionalCount++;
                    }
                });
                
                const totalElement = document.getElementById('smartModalTotal');
                if (totalElement) totalElement.textContent = total.toLocaleString();
                
                const optionalCountElement = document.getElementById('optionalCount');
                if (optionalCountElement) {
                    optionalCountElement.textContent = optionalCount > 0 ? ` + ${optionalCount} תשלומים נוספים` : '';
                }
            },
            
            addCustomPayment: function() {
                const name = document.getElementById('customPaymentName').value.trim();
                const amount = parseFloat(document.getElementById('customPaymentAmount').value);
                
                if (!name || !amount || amount <= 0) {
                    alert('יש למלא שם וסכום תקין');
                    return;
                }
                
                const optionalList = document.getElementById('optionalPaymentsList');
                optionalList.insertAdjacentHTML('beforeend', `
                    <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb; background: #ffffcc;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" checked
                                data-price="${amount}"
                                data-name="${name}"
                                data-definition="custom"
                                data-custom="true"
                                onchange="SmartPaymentsManager.updateTotal()"
                                style="margin-left: 10px;">
                            <span style="margin-right: 10px;">${name} (מותאם)</span>
                        </label>
                        <span>₪${amount.toLocaleString()}</span>
                    </div>
                `);
                
                document.getElementById('customPaymentName').value = '';
                document.getElementById('customPaymentAmount').value = '';
                this.updateTotal();
            },

            apply: function(mandatoryPaymentsJSON) {
                let mandatoryPayments;
                try {
                    mandatoryPayments = typeof mandatoryPaymentsJSON === 'string' 
                        ? JSON.parse(mandatoryPaymentsJSON.replace(/&quot;/g, '"'))
                        : mandatoryPaymentsJSON || [];
                } catch (e) {
                    console.error('Error parsing mandatory payments:', e);
                    mandatoryPayments = [];
                }
                
                window.purchasePayments = [];
                
                mandatoryPayments.forEach(payment => {
                    window.purchasePayments.push({
                        locked: false,
                        required: true,
                        paymentDate: new Date().toISOString(),
                        paymentType: payment.priceDefinition || 1,
                        paymentAmount: parseFloat(payment.price),
                        receiptDocuments: [],
                        customPaymentType: payment.name,
                        isPaymentComplete: false,
                        mandatory: true
                    });
                });
                
                const modal = document.getElementById('smartPaymentsModal');
                if (modal) {
                    const selectedOptional = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
                    selectedOptional.forEach(cb => {
                        window.purchasePayments.push({
                            locked: false,
                            required: false,
                            paymentDate: new Date().toISOString(),
                            paymentType: cb.dataset.custom ? 5 : cb.dataset.definition,
                            paymentAmount: parseFloat(cb.dataset.price),
                            receiptDocuments: [],
                            customPaymentType: cb.dataset.name,
                            isPaymentComplete: false,
                            mandatory: false,
                            custom: cb.dataset.custom === 'true'
                        });
                    });
                }
                
                document.getElementById('total_price').value = PaymentDisplayManager.calculateTotal();
                document.getElementById('paymentsDisplay').innerHTML = PaymentDisplayManager.render(window.purchasePayments, 'summary');
                document.getElementById('paymentsList').value = JSON.stringify(window.purchasePayments);
                
                this.close();
            },
            
            close: function() {
                const modal = document.getElementById('smartPaymentsModal');
                if (modal) modal.remove();
            }
        };

        window.SmartPaymentsManager = SmartPaymentsManager;

        // מנהל תשלומים קיימים
        const ExistingPaymentsManager = {
            open: function() {
                if (!window.purchasePayments) window.purchasePayments = [];
                
                const mandatoryPayments = window.purchasePayments.filter(p => p.mandatory === true || p.required === true);
                const editablePayments = window.purchasePayments.filter(p => p.mandatory !== true && p.required !== true);
                
                const modal = document.createElement('div');
                modal.id = 'existingPaymentsModal';
                modal.className = 'modal-overlay';
                modal.style.cssText = `
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.5); display: flex; align-items: center;
                    justify-content: center; z-index: 10001;
                `;
                
                const currentTotal = window.purchasePayments.reduce((sum, p) => sum + (Number(p.paymentAmount) || 0), 0);
                
                modal.innerHTML = `
                    <div style="background: white; padding: 30px; border-radius: 8px; width: 700px; max-height: 90vh; overflow-y: auto;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                            <h3 style="margin: 0;">ניהול תשלומים קיימים</h3>
                            <button onclick="ExistingPaymentsManager.close()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
                        </div>
                        
                        ${mandatoryPayments.length > 0 ? `
                            <div style="margin-bottom: 20px;">
                                <h4 style="color: #dc3545;">תשלומי חובה מקוריים</h4>
                                <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                                    ${mandatoryPayments.map(p => `
                                        <div style="padding: 8px 0; display: flex; justify-content: space-between; border-bottom: 1px solid #ffe5b4;">
                                            <span style="font-weight: bold;">${p.customPaymentType || 'תשלום'}</span>
                                            <span style="font-weight: bold; color: #dc3545;">₪${Number(p.paymentAmount).toLocaleString()}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #28a745;">תשלומים נוספים</h4>
                            <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                                <div id="editablePaymentsList">
                                    ${editablePayments.length > 0 ? editablePayments.map((p, i) => `
                                        <div style="padding: 8px 0; display: flex; gap: 10px; align-items: center; border-bottom: 1px solid #c3e6cb;">
                                            <input type="text" value="${p.customPaymentType || ''}" 
                                                onchange="ExistingPaymentsManager.updateName(${i}, this.value)"
                                                style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                                            <input type="number" value="${p.paymentAmount}" step="0.01"
                                                onchange="ExistingPaymentsManager.updateAmount(${i}, this.value)"
                                                style="width: 120px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                                            <button onclick="ExistingPaymentsManager.removePayment(${i})" 
                                                style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">הסר</button>
                                        </div>
                                    `).join('') : '<p style="text-align: center; color: #999;">אין תשלומים נוספים</p>'}
                                </div>
                                
                                <div style="border-top: 2px solid #28a745; margin-top: 15px; padding-top: 15px;">
                                    <h5>הוסף תשלום חדש:</h5>
                                    <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px;">
                                        <input type="text" id="newPaymentName" placeholder="סיבת תשלום" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <input type="number" id="newPaymentAmount" step="0.01" min="0" placeholder="סכום" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <button onclick="ExistingPaymentsManager.addPayment()" style="padding: 8px 15px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">+ הוסף</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; text-align: center; margin: 20px 0;">
                            <div style="font-size: 24px; font-weight: bold;">
                                סה"כ: ₪<span id="existingModalTotal">${currentTotal.toLocaleString()}</span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: space-between;">
                            <button onclick="ExistingPaymentsManager.recalculate()" style="padding: 10px 20px; background: #ff9800; color: white; border: none; border-radius: 4px; cursor: pointer;">🔄 חשב מחדש</button>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="ExistingPaymentsManager.close()" style="padding: 10px 30px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">ביטול</button>
                                <button onclick="ExistingPaymentsManager.save()" style="padding: 10px 30px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">שמור</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            },
            
            updateName: function(index, value) {
                const editable = window.purchasePayments.filter(p => p.mandatory !== true && p.required !== true);
                if (editable[index]) {
                    const idx = window.purchasePayments.indexOf(editable[index]);
                    window.purchasePayments[idx].customPaymentType = value;
                }
            },
            
            updateAmount: function(index, value) {
                const editable = window.purchasePayments.filter(p => p.mandatory !== true && p.required !== true);
                if (editable[index]) {
                    const idx = window.purchasePayments.indexOf(editable[index]);
                    window.purchasePayments[idx].paymentAmount = Number(value) || 0;
                    this.updateTotal();
                }
            },
            
            updateTotal: function() {
                const total = window.purchasePayments.reduce((sum, p) => sum + (Number(p.paymentAmount) || 0), 0);
                const el = document.getElementById('existingModalTotal');
                if (el) el.textContent = total.toLocaleString();
            },
            
            removePayment: function(index) {
                const editable = window.purchasePayments.filter(p => p.mandatory !== true && p.required !== true);
                if (editable[index]) {
                    const idx = window.purchasePayments.indexOf(editable[index]);
                    window.purchasePayments.splice(idx, 1);
                    this.close();
                    this.open();
                }
            },
            
            addPayment: function() {
                const name = document.getElementById('newPaymentName').value.trim();
                const amount = Number(document.getElementById('newPaymentAmount').value);
                
                if (!name || amount <= 0) {
                    alert('יש למלא שם וסכום תקין');
                    return;
                }
                
                window.purchasePayments.push({
                    locked: false,
                    required: false,
                    paymentDate: new Date().toISOString(),
                    paymentType: 5,
                    paymentAmount: amount,
                    receiptDocuments: [],
                    customPaymentType: name,
                    isPaymentComplete: false
                });
                
                this.close();
                this.open();
            },
            
            recalculate: function() {
                if (confirm('האם למחוק הכל ולחשב מחדש?')) {
                    window.purchasePayments = [];
                    window.isEditMode = false;
                    this.close();
                    window.openSmartPaymentsManager();
                }
            },
            
            save: function() {
                document.getElementById('total_price').value = PaymentDisplayManager.calculateTotal();
                document.getElementById('paymentsDisplay').innerHTML = PaymentDisplayManager.render(window.purchasePayments, 'summary');
                document.getElementById('paymentsList').value = JSON.stringify(window.purchasePayments);
                this.close();
            },
            
            close: function() {
                const modal = document.getElementById('existingPaymentsModal');
                if (modal) modal.remove();
            }
        };

        window.ExistingPaymentsManager = ExistingPaymentsManager;

        // אתחול הטופס
        setupCustomerListener();
        window.formInitialized = true;

        
        // ===========================================================
        // פונקציות טוענות נתונים
        // ===========================================================

        (async function loadHierarchy() {
            try {
                console.log('🌐 Starting to load full hierarchy from APIs...');
                
                showSelectSpinner('cemeterySelect');
                
                const hierarchySelects = ['blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect'];
                hierarchySelects.forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        select.disabled = true;
                        select.style.opacity = '0.5';
                    }
                });

                // ✅ קבל קבר נוכחי
                let currentGraveId = null;

                // אם לא נמצא, ובמצב עריכה - שלוף מה-API
                if (!currentGraveId && window.isEditMode && itemId) {
                    try {
                        const purchaseResponse = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${itemId}`);
                        const purchaseData = await purchaseResponse.json();
                        
                        if (purchaseData.success && purchaseData.data?.graveId) {
                            currentGraveId = purchaseData.data.graveId;
                            console.log('✅ [Purchase] נמצא graveId מ-API:', currentGraveId);
                        }
                    } catch (error) {
                        console.warn('⚠️ Could not load current grave:', error);
                    }
                }
                
                // ✅ בנה URLs עם currentGraveId
                const graveParam = currentGraveId ? `&currentGraveId=${currentGraveId}` : '';
                
                // ✅ טען הכל עם available
                const [cemResponse, blocksResponse, plotsResponse, rowsResponse, areaGravesResponse, gravesResponse] = await Promise.all([
                    fetch(`/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=available${graveParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/blocks-api.php?action=available${graveParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/plots-api.php?action=available${graveParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/rows-api.php?action=available${graveParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=available${graveParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=available${graveParam}`)
                ]);
                
                const [cemResult, blocksResult, plotsResult, rowsResult, areaGravesResult, gravesResult] = await Promise.all([
                    cemResponse.json(),
                    blocksResponse.json(),
                    plotsResponse.json(),
                    rowsResponse.json(),
                    areaGravesResponse.json(),
                    gravesResponse.json()
                ]);
                
                if (!cemResult.success || !blocksResult.success || !plotsResult.success || 
                    !rowsResult.success || !areaGravesResult.success || !gravesResult.success) {
                    console.error('❌ Failed to load hierarchy data');
                    hideSelectSpinner('cemeterySelect');
                    return;
                }
                
                window.hierarchyData.cemeteries = cemResult.data || [];
                window.hierarchyData.blocks = blocksResult.data || [];
                window.hierarchyData.plots = plotsResult.data || [];
                window.hierarchyData.rows = rowsResult.data || [];
                window.hierarchyData.areaGraves = areaGravesResult.data || [];
                window.hierarchyData.graves = gravesResult.data || [];
                
                console.log(`✅ Loaded ${window.hierarchyData.cemeteries.length} available cemeteries`);
                console.log(`✅ Loaded ${window.hierarchyData.blocks.length} available blocks`);
                console.log(`✅ Loaded ${window.hierarchyData.plots.length} available plots`);
                console.log(`✅ Loaded ${window.hierarchyData.rows.length} available rows`);
                console.log(`✅ Loaded ${window.hierarchyData.areaGraves.length} available areaGraves`);
                console.log(`✅ Loaded ${window.hierarchyData.graves.length} available graves`);
                
                const cemeterySelect = document.getElementById('cemeterySelect');
                
                if (!cemeterySelect) {
                    console.warn('⚠️ Cemetery select not found yet, will retry...');
                    setTimeout(loadHierarchy, 500);
                    return;
                }

                // נסה לקרוא מה-data attribute
                const fieldset = document.querySelector('#grave-selector-fieldset');
                if (fieldset) {
                    const dataGraveId = fieldset.getAttribute('data-purchase-grave-id');
                    if (dataGraveId && dataGraveId.trim() !== '') {
                        currentGraveId = dataGraveId;
                        console.log('✅ [Purchase] נמצא graveId מ-data attribute:', currentGraveId);
                    }
                }
                
                // ✅ מלא בתי עלמין - כולם פעילים כבר!
                cemeterySelect.innerHTML = '<option value="">-- בחר בית עלמין --</option>';

                window.hierarchyData.cemeteries.forEach(cemetery => {
                    const option = document.createElement('option');
                    option.value = cemetery.unicId;
                    option.textContent = cemetery.cemeteryNameHe;
                    
                    // ✅ סמן אם נוכחי
                    if (cemetery.has_current_grave) {
                        option.selected = true;
                    }
                    
                    cemeterySelect.appendChild(option);
                });
                
                cemeterySelect.addEventListener('change', function() {
                    if (this.value && window.filterHierarchy) {
                        window.filterHierarchy('cemetery');
                    }
                });
                
                console.log('✅ Full hierarchy loaded');
                hideSelectSpinner('cemeterySelect');

                // ✅ אם יש קבר נוכחי, טען את ההיררכיה
                if (currentGraveId) {
                    const currentGrave = window.hierarchyData.graves.find(g => g.unicId == currentGraveId);
                    if (currentGrave) {
                        // מצא את כל הערכים
                        const areaGrave = window.hierarchyData.areaGraves.find(ag => ag.unicId == currentGrave.areaGraveId);
                        const row = window.hierarchyData.rows.find(r => r.unicId == areaGrave?.lineId);
                        const plot = window.hierarchyData.plots.find(p => p.unicId == row?.plotId);
                        const block = window.hierarchyData.blocks.find(b => b.unicId == plot?.blockId);
                        const cemetery = window.hierarchyData.cemeteries.find(c => c.unicId == block?.cemeteryId);
                        
                        // ⭐ בחר את בית העלמין תחילה!
                        document.getElementById('cemeterySelect').value = cemetery?.unicId;

                        // טען גושים
                        window.populateBlocks();
                        document.getElementById('blockSelect').value = block?.unicId;
                        
                        // טען חלקות
                        window.populatePlots();
                        document.getElementById('plotSelect').value = plot?.unicId;
                        
                        // טען שורות
                        window.populateRows();
                        document.getElementById('rowSelect').value = row?.unicId;
                        window.toggleSelectState('rowSelect', true); // ← הוסף את זה!
                        
                        // טען אחוזות
                        window.populateAreaGraves();
                        document.getElementById('areaGraveSelect').value = areaGrave?.unicId;
                        window.toggleSelectState('areaGraveSelect', true); // ← הוסף את זה!
                        
                        // טען קברים
                        window.populateGraves();
                        document.getElementById('graveSelect').value = currentGrave.unicId;
                        window.toggleSelectState('graveSelect', true); // ← הוסף את זה!
                        
                        console.log('✅ Current hierarchy selections loaded');
                    }
                }

            } catch (error) {
                console.error('❌ Error loading hierarchy:', error);
                hideSelectSpinner('cemeterySelect');
            }
        })();

        (async function loadAvailableCustomers() {
            try {
                console.log('👥 מתחיל לטעון לקוחות פנויים מה-API...');
                
                // ✅ הוסף ספינר
                showSelectSpinner('clientId');
                
                // ✅ בנה URL עם הלקוח הנוכחי אם במצב עריכה
                let apiUrl = '/dashboard/dashboards/cemeteries/api/customers-api.php?action=available';
                if (window.isEditMode && itemId) {
                    const purchaseResponse = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${itemId}`);
                    const purchaseData = await purchaseResponse.json();
                    
                    if (purchaseData.success && purchaseData.data?.clientId) {
                        apiUrl += `&currentClientId=${purchaseData.data.clientId}`;
                    }
                }
                
                // ✅ קריאה ל-API
                const response = await fetch(apiUrl);
                const result = await response.json();
                
                if (!result.success) {
                    console.error('❌ שגיאה בטעינת לקוחות:', result.error);
                    hideSelectSpinner('clientId');
                    return;
                }
                
                console.log(`✅ נטענו ${result.data.length} לקוחות`);
                
                // ⭐ המתן ל-SmartSelect
                const customerInput = document.getElementById('clientId');
                
                if (!customerInput) {
                    console.warn('⚠️ Customer input לא נמצא עדיין, ננסה שוב...');
                    setTimeout(loadAvailableCustomers, 500);
                    return;
                }
                
                // ⭐ אתחל SmartSelect
                if (window.SmartSelectManager) {
                    SmartSelectManager.init();
                    console.log('✅ SmartSelect initialized for customers');
                }
                
                // ⭐ אכלס לקוחות
                window.populateCustomers(result.data);
                
                // ⭐ אם יש לקוח נוכחי - שמור את הנתונים
                const currentCustomer = result.data.find(c => c.is_current);
                if (currentCustomer) {
                    window.selectedCustomerData = {
                        id: currentCustomer.unicId,
                        resident: currentCustomer.resident || 3,
                        name: `${currentCustomer.firstName} ${currentCustomer.lastName}`
                    };
                    
                    console.log('👤 לקוח נוכחי נבחר:', window.selectedCustomerData);
                }
                
                // ✅ הסר ספינר
                hideSelectSpinner('clientId');
                
                console.log('✅ לקוחות נטענו בהצלחה');
                
            } catch (error) {
                console.error('❌ שגיאה בטעינת לקוחות:', error);
                hideSelectSpinner('clientId');
            }
        })();

        // טיפול בעריכה
        if (itemId) {
            const loadPurchaseData = () => {
                const form = document.querySelector('#purchaseFormModal form');
                if (!form || !form.elements || form.elements.length < 5) return false;
                
                fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${itemId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const data = result.data;
                            
                            Object.keys(data).forEach(key => {
                                const field = form.elements[key];
                                if (field && data[key] !== null) field.value = data[key];
                            });

                            if (data.clientId) {
                                fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${data.clientId}`)
                                    .then(r => r.json())
                                    .then(cr => {
                                        if (cr.success && cr.data) {
                                            window.selectedCustomerData = {
                                                id: data.clientId,
                                                resident: cr.data.resident || 3,
                                                name: cr.data.firstName + ' ' + cr.data.lastName
                                            };
                                            if (window.updatePaymentParameters) window.updatePaymentParameters();
                                        }
                                    });
                            }

                            if (data.paymentsList) {
                                try {
                                    window.purchasePayments = JSON.parse(data.paymentsList);
                                    if (window.displayPaymentsSummary) {
                                        document.getElementById('paymentsDisplay').innerHTML = 
                                            PaymentDisplayManager.render(window.purchasePayments, 'summary');
                                    }
                                    document.getElementById('total_price').value = data.price || PaymentDisplayManager.calculateTotal();
                                    const btn = document.getElementById('paymentsButtonText');
                                    if (btn) btn.textContent = 'ערוך תשלומים';
                                } catch(e) {
                                    console.error('Error parsing payments:', e);
                                }
                            }
                        }
                    });
                
                return true;
            };
            
            if (!loadPurchaseData()) {
                const observer = new MutationObserver((mutations, obs) => {
                    if (loadPurchaseData()) obs.disconnect();
                });
                const modal = document.getElementById('purchaseFormModal');
                if (modal) observer.observe(modal, { childList: true, subtree: true });
                setTimeout(() => observer.disconnect(), 10000);
            }
        }
    },

    handleBurialForm: function(itemId) {
        // אתחול משתנים גלובליים
        window.formInitialized = false;
        window.selectedGraveData = null;
        window.selectedCustomerData = null;
        window.isEditMode = !!itemId;
        window.hierarchyData = {
            cemeteries: [],
            blocks: [],
            plots: [],
            rows: [],
            areaGraves: [],
            graves: []
        };

        // ===========================================================
        // פונקציות להיררכית בתי עלמין (זהה לרכישה)
        // ===========================================================
        
        window.filterHierarchy = function(level) {
            console.log(`📍 filterHierarchy called with level: ${level}`);
            
            const clearSelect = (selectId) => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = '<option value="">-- בחר --</option>';
                }
            };

            // ✅ פונקציה לאיפוס לקוח (רק אם לא במצב עריכה)
            const clearCustomer = () => {
                if (!window.isEditMode) {
                    const customerSelect = document.querySelector('[name="clientId"]');
                    if (customerSelect) {
                        customerSelect.value = '';
                        console.log('🧹 לקוח אופס בגלל שינוי בהיררכיה');
                    }
                    window.selectedCustomerData = null;
                }
            };
            
            switch(level) {
                case 'cemetery':
                    window.populateBlocks();
                    clearSelect('plotSelect');
                    clearSelect('rowSelect');
                    clearSelect('areaGraveSelect');
                    clearSelect('graveSelect');
                    clearCustomer();
                    window.toggleSelectState('rowSelect', false);
                    window.toggleSelectState('areaGraveSelect', false);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'block':
                    window.populatePlots();
                    clearSelect('rowSelect');
                    clearSelect('areaGraveSelect');
                    clearSelect('graveSelect');
                    clearCustomer();
                    window.toggleSelectState('rowSelect', false);
                    window.toggleSelectState('areaGraveSelect', false);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'plot':
                    window.populateRows();
                    clearSelect('areaGraveSelect');
                    clearSelect('graveSelect');
                    clearCustomer();
                    window.toggleSelectState('rowSelect', true);
                    window.toggleSelectState('areaGraveSelect', false);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'row':
                    window.populateAreaGraves();
                    clearSelect('graveSelect');
                    clearCustomer();
                    window.toggleSelectState('areaGraveSelect', true);
                    window.toggleSelectState('graveSelect', false);
                    break;
                    
                case 'areaGrave':
                    window.populateGraves();
                    clearCustomer();
                    window.toggleSelectState('graveSelect', true);
                    break;
            }
        };

        window.toggleSelectState = function(selectId, enabled) {
            const select = document.getElementById(selectId);
            if (select) {
                select.disabled = !enabled;
                if (!enabled) {
                    select.style.opacity = '0.5';
                    select.style.cursor = 'not-allowed';
                } else {
                    select.style.opacity = '1';
                    select.style.cursor = 'pointer';
                }
            }
        };

        window.populateBlocks = function() {
            console.log('📦 populateBlocks called');
            
            if (!window.hierarchyData?.blocks) {
                console.warn('⚠️ Blocks data not loaded yet');
                return;
            }
            
            const cemeteryId = document.getElementById('cemeterySelect')?.value;
            const blockSelect = document.getElementById('blockSelect');
            
            if (!blockSelect || !cemeteryId) {
                console.warn('⚠️ Block select or cemetery not found');
                return;
            }
            
            blockSelect.innerHTML = '<option value="">-- בחר גוש --</option>';
            
            const relevantBlocks = window.hierarchyData.blocks.filter(block => {
                return block.cemetery_id == cemeteryId ||
                    block.cemeteryId == cemeteryId ||
                    block.cemId == cemeteryId ||
                    block.cemetery == cemeteryId;
            });
            
            console.log(`📦 Found ${relevantBlocks.length} blocks`);
            
            relevantBlocks.forEach(block => {
                const option = document.createElement('option');
                option.value = block.unicId;
                option.textContent = block.blockNameHe;
                
                if (block.has_current_grave) {
                    option.selected = true;
                }
                
                blockSelect.appendChild(option);
            });
            
            console.log('✅ Blocks populated successfully');
            window.toggleSelectState('blockSelect', true);
        };

        window.populatePlots = function() {
            console.log('📊 populatePlots called');
            
            if (!window.hierarchyData?.plots) {
                console.warn('⚠️ Plots data not loaded yet');
                return;
            }
            
            const blockId = document.getElementById('blockSelect')?.value;
            const plotSelect = document.getElementById('plotSelect');
            
            if (!plotSelect || !blockId) {
                console.warn('⚠️ Plot select or block not found');
                return;
            }
            
            plotSelect.innerHTML = '<option value="">-- בחר חלקה --</option>';
            
            const relevantPlots = window.hierarchyData.plots.filter(plot => {
                return plot.blockId == blockId ||
                    plot.block_id == blockId ||
                    plot.unicBlockId == blockId;
            });
            
            console.log(`📊 Found ${relevantPlots.length} plots`);
            
            relevantPlots.forEach(plot => {
                const option = document.createElement('option');
                option.value = plot.unicId;
                option.textContent = plot.plotNameHe;
                
                if (plot.has_current_grave) {
                    option.selected = true;
                }
                
                plotSelect.appendChild(option);
            });
            
            console.log('✅ Plots populated successfully');
            window.toggleSelectState('plotSelect', true);
        };

        window.populateRows = function() {
            console.log('📏 populateRows called');
            
            if (!window.hierarchyData?.rows) {
                console.warn('⚠️ Rows data not loaded yet');
                return;
            }
            
            const plotId = document.getElementById('plotSelect')?.value;
            const rowSelect = document.getElementById('rowSelect');
            
            if (!rowSelect || !plotId) {
                console.warn('⚠️ Row select or plot not found');
                return;
            }
            
            rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
            
            const relevantRows = window.hierarchyData.rows.filter(row => {
                return row.plotId == plotId ||
                    row.plot_id == plotId ||
                    row.unicPlotId == plotId;
            });
            
            console.log(`📏 Found ${relevantRows.length} rows`);
            
            relevantRows.forEach(row => {
                const option = document.createElement('option');
                option.value = row.unicId;
                option.textContent = row.lineNameHe || row.rowNameHe || `שורה ${row.serialNumber}`;
                
                if (row.has_current_grave) {
                    option.selected = true;
                }
                
                rowSelect.appendChild(option);
            });
            
            console.log('✅ Rows populated successfully');
        };

        window.populateAreaGraves = function() {
            console.log('🏘️ populateAreaGraves called');
            
            if (!window.hierarchyData?.areaGraves) {
                console.warn('⚠️ AreaGraves data not loaded yet');
                return;
            }
            
            const rowId = document.getElementById('rowSelect')?.value;
            const areaGraveSelect = document.getElementById('areaGraveSelect');
            
            if (!areaGraveSelect || !rowId) {
                console.warn('⚠️ AreaGrave select or row not found');
                return;
            }
            
            areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
            
            const relevantAreaGraves = window.hierarchyData.areaGraves.filter(ag => {
                return ag.lineId == rowId ||
                    ag.line_id == rowId ||
                    ag.rowId == rowId ||
                    ag.row_id == rowId ||
                    ag.unicLineId == rowId;
            });
            
            console.log(`🏘️ Found ${relevantAreaGraves.length} areaGraves`);
            
            relevantAreaGraves.forEach(ag => {
                const option = document.createElement('option');
                option.value = ag.unicId;
                option.textContent = ag.areaGraveNameHe || `אחוזה ${ag.serialNumber}`;
                
                if (ag.has_current_grave) {
                    option.selected = true;
                }
                
                areaGraveSelect.appendChild(option);
            });
            
            console.log('✅ AreaGraves populated successfully');
        };

        window.populateGraves = function() {
            console.log('⚰️ populateGraves called');
            
            if (!window.hierarchyData?.graves) {
                console.warn('⚠️ Graves data not loaded yet');
                return;
            }
            
            const areaGraveId = document.getElementById('areaGraveSelect')?.value;
            const graveSelect = document.getElementById('graveSelect');
            
            if (!graveSelect || !areaGraveId) {
                console.warn('⚠️ Grave select or areaGrave not found');
                return;
            }
            
            graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
            
            const relevantGraves = window.hierarchyData.graves.filter(grave => {
                return grave.areaGraveId == areaGraveId ||
                    grave.area_grave_id == areaGraveId ||
                    grave.unicAreaGraveId == areaGraveId;
            });
            
            console.log(`⚰️ Found ${relevantGraves.length} graves`);
            
            relevantGraves.forEach(grave => {
                const option = document.createElement('option');
                option.value = grave.unicId;
                option.textContent = `קבר ${grave.graveNameHe || grave.serialNumber}`;
                
                if (grave.is_current) {
                    option.selected = true;
                }
                
                graveSelect.appendChild(option);
            });
            
            console.log('✅ Graves populated successfully');
        };

        // ⭐ פונקציה למילוי לקוחות ב-SmartSelect (מתוקנת!)
        window.populateCustomers = function(customers) {
            console.log('👥 populateCustomers called with', customers.length, 'customers');
            
            const customerInstance = window.SmartSelectManager?.instances['clientId'];
            
            if (!customerInstance) {
                console.warn('⚠️ Customer SmartSelect instance not found');
                return;
            }
            
            // נקה אופציות
            customerInstance.optionsContainer.innerHTML = '';
            customerInstance.allOptions = [];
            
            // מלא לקוחות
            customers.forEach(customer => {
                const option = document.createElement('div');
                option.className = 'smart-select-option';
                option.dataset.value = customer.unicId;
                
                let displayText = `${customer.firstName} ${customer.lastName}`;
                if (customer.phone || customer.phoneMobile) {
                    displayText += ` - ${customer.phone || customer.phoneMobile}`;
                }
                
                option.textContent = displayText;
                
                // ⭐ סמן אם זה לקוח נוכחי
                if (customer.is_current) {
                    option.classList.add('selected');
                }
                
                // ⭐⭐⭐ הוסף את הלוגיקה של setupCustomerListener כאן!
                option.addEventListener('click', async function() {
                    window.SmartSelectManager.select('clientId', customer.unicId);
                    
                    const customerId = customer.unicId;
                    
                    // ⭐ שמור את נתוני הלקוח
                    window.selectedCustomerData = {
                        id: customerId,
                        name: `${customer.firstName} ${customer.lastName}`
                    };
                    
                    console.log('👤 נפטר/ת נבחר/ה:', window.selectedCustomerData);
                    
                    // ⭐ בדוק אם ללקוח יש רכישה פעילה
                    try {
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=getByCustomer&customerId=${customerId}`);
                        const data = await response.json();
                        
                        if (data.success && data.data) {
                            const purchase = data.data;
                            
                            // ✅ יש רכישה - מלא את הקבר
                            if (purchase.graveId) {
                                const grave = window.hierarchyData.graves.find(g => g.unicId == purchase.graveId);
                                
                                if (grave) {
                                    await fillGraveHierarchy(purchase.graveId);
                                    console.log('✅ קבר מולא אוטומטית מרכישה:', purchase.graveId);
                                    showNotification('info', `קבר "${purchase.grave_name || ''}" מולא אוטומטית על פי הרכישה`);
                                }
                            }
                        } else {
                            // ✅ אין רכישה - אפס את ההיררכיה (רק אם לא במצב עריכה)
                            if (!window.isEditMode) {
                                console.log('ℹ️ ללקוח אין רכישה - מאפס היררכיה');
                                clearGraveHierarchy();
                            }
                        }
                    } catch (error) {
                        console.error('❌ שגיאה בטעינת רכישת לקוח:', error);
                        // במקרה של שגיאה - אפס (רק אם לא במצב עריכה)
                        if (!window.isEditMode) {
                            clearGraveHierarchy();
                        }
                    }
                });
                
                customerInstance.optionsContainer.appendChild(option);
                customerInstance.allOptions.push(option);
            });
            
            // עדכן טקסט ל-"בחר נפטר/ת..."
            if (!customers.some(c => c.is_current)) {
                customerInstance.valueSpan.textContent = 'בחר נפטר/ת...';
                customerInstance.hiddenInput.value = '';
            }
            
            console.log(`✅ Populated ${customers.length} customers (burial)`);
        };

        // ⭐ פונקציה לבחירת לקוח (במצב עריכה)
        window.selectCustomer = function(customerId, customerName) {
            console.log('🎯 Selecting customer:', customerId, customerName);
            
            const customerInput = document.getElementById('clientId');
            const customerInstance = window.SmartSelectManager?.instances['clientId'];
            
            if (!customerInput || !customerInstance) {
                console.warn('⚠️ Customer input or instance not found');
                return;
            }
            
            // עדכן ידנית
            customerInput.value = customerId;
            customerInstance.valueSpan.textContent = customerName;
            customerInstance.hiddenInput.value = customerId;
            
            // סמן את האופציה הנכונה
            customerInstance.optionsContainer.querySelectorAll('.smart-select-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.value == customerId);
            });
            
            console.log('✅ Customer selected:', customerName);
        };
            
        // ===========================================================
        // סוף פונקציות להיררכית בתי עלמין
        // ===========================================================

        // אתחול הטופס
        window.formInitialized = true;

        // ===========================================================
        // פונקציות טוענות נתונים
        // ===========================================================

        // ✅ הוסף מאזין לבחירת לקוח (מתוקן)
        const setupCustomerListener = function() {
            const customerSelect = document.querySelector('[name="clientId"]');
            if (customerSelect) {
                customerSelect.addEventListener('change', async function() {
                    const customerId = this.value;
                    
                    if (!customerId) {
                        // נוקה קבר רק אם לא במצב עריכה
                        if (!window.isEditMode) {
                            clearGraveHierarchy();
                        }
                        window.selectedCustomerData = null;
                        return;
                    }
                    
                    window.selectedCustomerData = {
                        id: customerId,
                        name: this.options[this.selectedIndex].textContent.split(' - ')[0]
                    };
                    
                    console.log('👤 לקוח נבחר:', window.selectedCustomerData);
                    
                    // ✅ בדוק אם ללקוח יש רכישה פעילה
                    try {
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=getByCustomer&customerId=${customerId}`);
                        const data = await response.json();
                        
                        if (data.success && data.data) {
                            const purchase = data.data;
                            
                            // ✅ יש רכישה - מלא את הקבר
                            if (purchase.graveId) {
                                const grave = window.hierarchyData.graves.find(g => g.unicId == purchase.graveId);
                                
                                if (grave) {
                                    await fillGraveHierarchy(purchase.graveId);
                                    console.log('✅ קבר מולא אוטומטית מרכישה:', purchase.graveId);
                                    showNotification('info', `קבר "${purchase.grave_name || ''}" מולא אוטומטית על פי הרכישה`);
                                }
                            }
                        } else {
                            // ✅ אין רכישה - אפס את ההיררכיה (רק אם לא במצב עריכה)
                            if (!window.isEditMode) {
                                console.log('ℹ️ ללקוח אין רכישה - מאפס היררכיה');
                                clearGraveHierarchy();
                            }
                        }
                    } catch (error) {
                        console.error('❌ שגיאה בטעינת רכישת לקוח:', error);
                        // במקרה של שגיאה - אפס (רק אם לא במצב עריכה)
                        if (!window.isEditMode) {
                            clearGraveHierarchy();
                        }
                    }
                });
            }
        };

        // ✅ הוסף מאזין לבחירת קבר (מתוקן עם המתנה)
        const setupGraveListener = function() {
            const graveSelect = document.getElementById('graveSelect');
            if (graveSelect) {
                graveSelect.addEventListener('change', async function() {
                    const graveId = this.value;
                    
                    console.log('🔵 GRAVE CHANGED:', graveId);
                    
                    if (!graveId) {
                        if (!window.isEditMode) {
                            const customerSelect = document.querySelector('[name="clientId"]');
                            if (customerSelect) customerSelect.value = '';
                            window.selectedCustomerData = null;
                        }
                        window.selectedGraveData = null;
                        return;
                    }
                    
                    const grave = window.hierarchyData.graves.find(g => g.unicId == graveId);
                    console.log('🔵 FOUND GRAVE:', grave);
                    
                    if (!grave) return;
                    
                    window.selectedGraveData = {
                        graveId: graveId,
                        graveStatus: grave.graveStatus
                    };
                    
                    console.log('🔵 GRAVE STATUS:', grave.graveStatus);
                    
                    // ✅ בדוק אם לקבר יש רכישה
                    try {
                        const url = `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=getByGrave&graveId=${graveId}`;
                        console.log('🔵 FETCHING:', url);
                        
                        const response = await fetch(url);
                        const data = await response.json();
                        
                        console.log('🔵 API RESPONSE:', data);
                        
                        if (data.success && data.data) {
                            const purchase = data.data;
                            console.log('🔵 PURCHASE FOUND:', purchase);
                            console.log('🔵 CLIENT ID:', purchase.clientId);
                            
                            const customerSelect = document.querySelector('[name="clientId"]');
                            console.log('🔵 CUSTOMER SELECT:', customerSelect);
                            
                            if (customerSelect && purchase.clientId) {
                                console.log('🔵 CURRENT OPTIONS:', Array.from(customerSelect.options).map(o => ({value: o.value, text: o.textContent})));
                                
                                // בדוק אם הלקוח כבר ברשימה
                                const existingOption = Array.from(customerSelect.options).find(
                                    opt => opt.value === purchase.clientId
                                );
                                
                                console.log('🔵 EXISTING OPTION:', existingOption);
                                
                                if (existingOption) {
                                    // הלקוח קיים
                                    customerSelect.value = purchase.clientId;
                                    console.log('✅ CUSTOMER SELECTED FROM LIST:', purchase.clientId);
                                } else {
                                    // הלקוח לא קיים - הוסף אותו
                                    console.log('⚠️ CUSTOMER NOT IN LIST, ADDING...');
                                    
                                    const newOption = document.createElement('option');
                                    newOption.value = purchase.clientId;
                                    newOption.textContent = purchase.customer_name;
                                    customerSelect.appendChild(newOption);
                                    
                                    customerSelect.value = purchase.clientId;
                                    console.log('✅ CUSTOMER ADDED AND SELECTED:', purchase.clientId);
                                }
                                
                                window.selectedCustomerData = {
                                    id: purchase.clientId,
                                    name: purchase.customer_name || ''
                                };
                                
                                console.log('✅ selectedCustomerData:', window.selectedCustomerData);
                                showNotification('info', `הלקוח "${purchase.customer_name}" מולא אוטומטית`);
                            }
                        } else {
                            console.log('ℹ️ NO PURCHASE FOR THIS GRAVE');
                            if (!window.isEditMode) {
                                const customerSelect = document.querySelector('[name="clientId"]');
                                if (customerSelect) customerSelect.value = '';
                                window.selectedCustomerData = null;
                            }
                        }
                    } catch (error) {
                        console.error('❌ ERROR:', error);
                    }
                });
            }
        };

        // ✅ פונקציה לאיפוס היררכיית קברים
        function clearGraveHierarchy() {
            console.log('🧹 מאפס היררכיית קברים');
            
            document.getElementById('cemeterySelect').value = '';
            document.getElementById('blockSelect').innerHTML = '<option value="">-- בחר בית עלמין תחילה --</option>';
            document.getElementById('plotSelect').innerHTML = '<option value="">-- בחר בית עלמין תחילה --</option>';
            document.getElementById('rowSelect').innerHTML = '<option value="">-- בחר חלקה תחילה --</option>';
            document.getElementById('areaGraveSelect').innerHTML = '<option value="">-- בחר שורה תחילה --</option>';
            document.getElementById('graveSelect').innerHTML = '<option value="">-- בחר אחוזת קבר תחילה --</option>';
            
            // השבת למצב מושבת
            window.toggleSelectState('blockSelect', false);
            window.toggleSelectState('plotSelect', false);
            window.toggleSelectState('rowSelect', false);
            window.toggleSelectState('areaGraveSelect', false);
            window.toggleSelectState('graveSelect', false);
            
            window.selectedGraveData = null;
        }
        
        // ✅ פונקציה למילוי היררכיית קבר
        async function fillGraveHierarchy(graveId) {
            if (!window.hierarchyData || !graveId) return;
            
            const grave = window.hierarchyData.graves.find(g => g.unicId === graveId);
            if (!grave) return;
            
            const areaGrave = window.hierarchyData.areaGraves.find(ag => ag.unicId === grave.areaGraveId);
            if (!areaGrave) return;
            
            const row = window.hierarchyData.rows.find(r => r.unicId === areaGrave.lineId);
            if (!row) return;
            
            const plot = window.hierarchyData.plots.find(p => p.unicId === row.plotId);
            if (!plot) return;
            
            const block = window.hierarchyData.blocks.find(b => b.unicId === plot.blockId);
            if (!block) return;
            
            // מצא את בית העלמין
            const cemetery = window.hierarchyData.cemeteries.find(c => 
                c.unicId === block.cemeteryId || c.unicId === block.cemetery_id
            );
            if (!cemetery) return;
            
            // מלא בסדר היררכי
            document.getElementById('cemeterySelect').value = cemetery.unicId;
            window.filterHierarchy('cemetery');
            
            await new Promise(resolve => setTimeout(resolve, 50));
            document.getElementById('blockSelect').value = block.unicId;
            window.filterHierarchy('block');
            
            await new Promise(resolve => setTimeout(resolve, 50));
            document.getElementById('plotSelect').value = plot.unicId;
            window.filterHierarchy('plot');
            
            await new Promise(resolve => setTimeout(resolve, 50));
            document.getElementById('rowSelect').value = row.unicId;
            window.filterHierarchy('row');
            
            await new Promise(resolve => setTimeout(resolve, 50));
            document.getElementById('areaGraveSelect').value = areaGrave.unicId;
            window.filterHierarchy('areaGrave');
            
            await new Promise(resolve => setTimeout(resolve, 50));
            document.getElementById('graveSelect').value = grave.unicId;
            
            // עדכן selectedGraveData
            window.selectedGraveData = {
                graveId: graveId,
                graveStatus: grave.graveStatus
            };
        }
        
        // ✅ פונקציה להצגת הודעות
        function showNotification(type, message) {
            const notificationId = 'burialNotification';
            let notification = document.getElementById(notificationId);
            
            if (!notification) {
                notification = document.createElement('div');
                notification.id = notificationId;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 400px;
                    padding: 15px 20px;
                    border-radius: 8px;
                    color: white;
                    font-size: 14px;
                    font-weight: 500;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    transition: all 0.3s ease;
                `;
                document.body.appendChild(notification);
            }
            
            const colors = {
                'info': '#3b82f6',
                'success': '#10b981',
                'warning': '#f59e0b',
                'error': '#ef4444'
            };
            
            notification.style.backgroundColor = colors[type] || colors['info'];
            notification.textContent = message;
            notification.style.display = 'block';
            notification.style.opacity = '1';
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            }, 4000);
        }
        
        // ✅ אתחל את המאזינים
        setTimeout(() => {
            setupGraveListener();
            setupCustomerListener();
        }, 500);

        (async function loadHierarchy() {
            try {
                console.log('🌐 Starting to load full hierarchy from APIs...');
                
                showSelectSpinner('cemeterySelect');
                
                const hierarchySelects = ['blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect'];
                hierarchySelects.forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        select.disabled = true;
                        select.style.opacity = '0.5';
                    }
                });

                // ✅ קבל קבר נוכחי
                let currentGraveId = null;    

                // אם לא נמצא, ובמצב עריכה - שלוף מה-API
                if (!currentGraveId && window.isEditMode && itemId) {
                    try {
                        const burialResponse = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=get&id=${itemId}`);
                        const burialData = await burialResponse.json();
                        
                        if (burialData.success && burialData.data?.graveId) {
                            currentGraveId = burialData.data.graveId;
                            console.log('✅ [Burial] נמצא graveId מ-API:', currentGraveId);
                        }
                    } catch (error) {
                        console.warn('⚠️ Could not load current grave:', error);
                    }
                }
                
                // ✅ בנה URLs עם currentGraveId + type=burial
                const graveParam = currentGraveId ? `&currentGraveId=${currentGraveId}` : '';
                const typeParam = '&type=burial';
                
                // ✅ טען הכל עם available + burial
                const [cemResponse, blocksResponse, plotsResponse, rowsResponse, areaGravesResponse, gravesResponse] = await Promise.all([
                    fetch(`/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=available${graveParam}${typeParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/blocks-api.php?action=available${graveParam}${typeParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/plots-api.php?action=available${graveParam}${typeParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/rows-api.php?action=available${graveParam}${typeParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=available${graveParam}${typeParam}`),
                    fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=available${graveParam}${typeParam}`)
                ]);
                
                const [cemResult, blocksResult, plotsResult, rowsResult, areaGravesResult, gravesResult] = await Promise.all([
                    cemResponse.json(),
                    blocksResponse.json(),
                    plotsResponse.json(),
                    rowsResponse.json(),
                    areaGravesResponse.json(),
                    gravesResponse.json()
                ]);
                
                if (!cemResult.success || !blocksResult.success || !plotsResult.success || 
                    !rowsResult.success || !areaGravesResult.success || !gravesResult.success) {
                    console.error('❌ Failed to load hierarchy data');
                    hideSelectSpinner('cemeterySelect');
                    return;
                }
                
                window.hierarchyData.cemeteries = cemResult.data || [];
                window.hierarchyData.blocks = blocksResult.data || [];
                window.hierarchyData.plots = plotsResult.data || [];
                window.hierarchyData.rows = rowsResult.data || [];
                window.hierarchyData.areaGraves = areaGravesResult.data || [];
                window.hierarchyData.graves = gravesResult.data || [];
                
                const cemeterySelect = document.getElementById('cemeterySelect');
                
                if (!cemeterySelect) {
                    console.warn('⚠️ Cemetery select not found yet, will retry...');
                    setTimeout(loadHierarchy, 500);
                    return;
                }

                // נסה לקרוא מה-data attribute
                const fieldset = document.querySelector('#grave-selector-fieldset'); 
                if (fieldset) {
                    const dataGraveId = fieldset.getAttribute('data-burial-grave-id');
                    if (dataGraveId && dataGraveId.trim() !== '') {
                        currentGraveId = dataGraveId;
                        console.log('✅ [Burial] נמצא graveId מ-data attribute:', currentGraveId);
                    }
                }    
                
                // ✅ מלא בתי עלמין - כולם פעילים כבר!
                cemeterySelect.innerHTML = '<option value="">-- בחר בית עלמין --</option>';

                window.hierarchyData.cemeteries.forEach(cemetery => {
                    const option = document.createElement('option');
                    option.value = cemetery.unicId;
                    option.textContent = cemetery.cemeteryNameHe;
                    
                    if (cemetery.has_current_grave) {
                        option.selected = true;
                    }
                    
                    cemeterySelect.appendChild(option);
                });
                
                cemeterySelect.addEventListener('change', function() {
                    if (this.value && window.filterHierarchy) {
                        window.filterHierarchy('cemetery');
                    }
                });
                
                hideSelectSpinner('cemeterySelect');


                // ✅ אם יש קבר נוכחי, טען את ההיררכיה
                if (currentGraveId) {
                    const currentGrave = window.hierarchyData.graves.find(g => g.unicId == currentGraveId);

                    if (currentGrave) {

                        // מצא את כל הערכים 
                        const areaGrave = window.hierarchyData.areaGraves.find(ag => ag.unicId == currentGrave.areaGraveId);
                        const row = window.hierarchyData.rows.find(r => r.unicId == areaGrave?.lineId);
                        const plot = window.hierarchyData.plots.find(p => p.unicId == row?.plotId);
                        const block = window.hierarchyData.blocks.find(b => b.unicId == plot?.blockId);
                        const cemetery = window.hierarchyData.cemeteries.find(c => c.unicId == block?.cemeteryId);
                                               
                        // ⭐ בחר את בית העלמין תחילה!
                        document.getElementById('cemeterySelect').value = cemetery?.unicId;
                        
                        window.populateBlocks();
                        document.getElementById('blockSelect').value = block?.unicId;
                        
                        window.populatePlots();
                        document.getElementById('plotSelect').value = plot?.unicId;
                        
                        window.populateRows();
                        document.getElementById('rowSelect').value = row?.unicId;
                        window.toggleSelectState('rowSelect', true);
                        
                        window.populateAreaGraves();
                        document.getElementById('areaGraveSelect').value = areaGrave?.unicId;
                        window.toggleSelectState('areaGraveSelect', true);
                        
                        window.populateGraves();
                        document.getElementById('graveSelect').value = currentGrave.unicId;
                        window.toggleSelectState('graveSelect', true);
                        
                        console.log('✅ Current hierarchy selections loaded');
                    }
                }

            } catch (error) {
                hideSelectSpinner('cemeterySelect');
            }
        })();

        (async function loadAvailableCustomers() {
            try {
                console.log('👥 מתחיל לטעון לקוחות מה-API...');
                
                // ✅ הוסף ספינר
                showSelectSpinner('clientId');
                
                // ✅ בנה URL עם הלקוח הנוכחי אם במצב עריכה + type=burial
                let apiUrl = '/dashboard/dashboards/cemeteries/api/customers-api.php?action=available&type=burial';
                if (window.isEditMode && itemId) {
                    const burialResponse = await fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=get&id=${itemId}`);
                    const burialData = await burialResponse.json();
                    
                    if (burialData.success && burialData.data?.clientId) {
                        apiUrl += `&currentClientId=${burialData.data.clientId}`;
                    }
                }
                
                // ✅ קריאה ל-API
                const response = await fetch(apiUrl);
                const result = await response.json();
                
                if (!result.success) {
                    console.error('❌ שגיאה בטעינת לקוחות:', result.error);
                    hideSelectSpinner('clientId');
                    return;
                }
                
                console.log(`✅ נטענו ${result.data.length} לקוחות`);
                
                // ⭐ המתן ל-SmartSelect
                const customerInput = document.getElementById('clientId');
                
                if (!customerInput) {
                    console.warn('⚠️ Customer input לא נמצא עדיין, ננסה שוב...');
                    setTimeout(loadAvailableCustomers, 500);
                    return;
                }
                
                // ⭐ אתחל SmartSelect
                if (window.SmartSelectManager) {
                    SmartSelectManager.init();
                    console.log('✅ SmartSelect initialized for customers (burial)');
                }
                
                // ⭐ אכלס לקוחות
                window.populateCustomers(result.data);
                
                // ⭐ אם יש לקוח נוכחי - שמור את הנתונים
                const currentCustomer = result.data.find(c => c.is_current);
                if (currentCustomer) {
                    window.selectedCustomerData = {
                        id: currentCustomer.unicId,
                        name: `${currentCustomer.firstName} ${currentCustomer.lastName}`
                    };
                    
                    console.log('👤 לקוח נוכחי נבחר:', window.selectedCustomerData);
                }
                
                // ✅ הסר ספינר
                hideSelectSpinner('clientId');
                
                console.log('✅ לקוחות נטענו בהצלחה');

                // ⭐ אם לא במצב עריכה - בדוק אם יש רכישה לקבר
                if (!window.isEditMode) {
                    const fieldset = document.querySelector('#grave-selector-fieldset');
                    const graveId = fieldset?.getAttribute('data-burial-grave-id');
                    
                    if (graveId && graveId.trim() !== '') {
                        try {
                            const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=getByGrave&graveId=${graveId}`);
                            const data = await response.json();
                            
                            if (data.success && data.data) {
                                const purchase = data.data;
                                
                                // ⭐ מלא את הלקוח (SmartSelect כבר מוכן כאן!)
                                if (purchase.clientId && window.SmartSelectManager?.instances['clientId']) {
                                    window.SmartSelectManager.select('clientId', purchase.clientId);
                                    
                                    window.selectedCustomerData = {
                                        id: purchase.clientId,
                                        name: purchase.customer_name || ''
                                    };
                                    
                                    console.log('✅ [Burial] לקוח מולא אוטומטית מרכישה:', purchase.customer_name);
                                } else {
                                    console.log('ℹ️ [Burial] לא נמצאה רכישה לקבר זה');
                                }
                            }
                        } catch (error) {
                            console.error('❌ שגיאה בטעינת רכישה:', error);
                        }
                    }
                }
                
            } catch (error) {
                console.error('❌ שגיאה בטעינת לקוחות:', error);
                hideSelectSpinner('clientId');
            }
        })();

        // טיפול בעריכה
        if (itemId) {
            const loadBurialData = () => {
                const form = document.querySelector('#burialFormModal form');
                if (!form || !form.elements || form.elements.length < 5) return false;
                
                fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=get&id=${itemId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const data = result.data;
                            
                            Object.keys(data).forEach(key => {
                                const field = form.elements[key];
                                if (field && data[key] !== null) field.value = data[key];
                            });
                        }
                    });
                
                return true;
            };
            
            if (!loadBurialData()) {
                const observer = new MutationObserver((mutations, obs) => {
                    if (loadBurialData()) obs.disconnect();
                });
                const modal = document.getElementById('burialFormModal');
                if (modal) observer.observe(modal, { childList: true, subtree: true });
                setTimeout(() => observer.disconnect(), 10000);
            }
        }
    },

    handlePaymentForm: function(itemId) {
        // חכה ל-fieldset עם הנתונים
        this.waitForElement('#payment-location-fieldset', (fieldset) => {
            if (fieldset.dataset.hierarchy) {
                window.paymentHierarchy = JSON.parse(fieldset.dataset.hierarchy);
            } else {
                console.error('No hierarchy data found in payment fieldset!');
                return;
            }
            
            // פונקציה לסינון המיקום
            window.filterPaymentLocation = function(level) {
                const cemeteryId = document.getElementById("paymentCemeterySelect").value;
                const blockId = document.getElementById("paymentBlockSelect").value;
                const plotId = document.getElementById("paymentPlotSelect").value;
                
                switch(level) {
                    case "cemetery":
                        // נקה ילדים
                        populatePaymentBlocks(cemeteryId);
                        populatePaymentPlots(cemeteryId, "-1");
                        document.getElementById("paymentLineSelect").innerHTML = '<option value="-1">-- בחר חלקה תחילה --</option>';
                        document.getElementById("paymentLineSelect").disabled = true;
                        break;
                        
                    case "block":
                        if (blockId !== "-1") {
                            populatePaymentPlots("-1", blockId);
                        } else if (cemeteryId !== "-1") {
                            populatePaymentPlots(cemeteryId, "-1");
                        } else {
                            populatePaymentPlots("-1", "-1");
                        }
                        document.getElementById("paymentLineSelect").innerHTML = '<option value="-1">-- בחר חלקה תחילה --</option>';
                        document.getElementById("paymentLineSelect").disabled = true;
                        break;
                        
                    case "plot":
                        if (plotId !== "-1") {
                            document.getElementById("paymentLineSelect").disabled = false;
                            populatePaymentRows(plotId);
                        } else {
                            document.getElementById("paymentLineSelect").innerHTML = '<option value="-1">-- בחר חלקה תחילה --</option>';
                            document.getElementById("paymentLineSelect").disabled = true;
                        }
                        break;
                }
            }
            
            // מילוי גושים
            window.populatePaymentBlocks = function(cemeteryId) {
                const select = document.getElementById("paymentBlockSelect");
                select.innerHTML = '<option value="-1">-- כל הגושים --</option>';
                
                if (cemeteryId === "-1") return;
                
                const blocks = window.paymentHierarchy.blocks.filter(b => b.cemeteryId === cemeteryId);
                blocks.forEach(block => {
                    const option = document.createElement("option");
                    option.value = block.unicId;
                    option.textContent = block.blockNameHe;
                    select.appendChild(option);
                });
            }
            
            // מילוי חלקות
            window.populatePaymentPlots = function(cemeteryId, blockId) {
                const select = document.getElementById("paymentPlotSelect");
                select.innerHTML = '<option value="-1">-- כל החלקות --</option>';
                
                let plots = window.paymentHierarchy.plots;
                
                if (blockId !== "-1") {
                    plots = plots.filter(p => p.blockId === blockId);
                } else if (cemeteryId !== "-1") {
                    const blockIds = window.paymentHierarchy.blocks
                        .filter(b => b.cemeteryId === cemeteryId)
                        .map(b => b.unicId);
                    plots = plots.filter(p => blockIds.includes(p.blockId));
                }
                
                plots.forEach(plot => {
                    const option = document.createElement("option");
                    option.value = plot.unicId;
                    option.textContent = plot.plotNameHe;
                    select.appendChild(option);
                });
            }
            
            // מילוי שורות
            window.populatePaymentRows = function(plotId) {
                const select = document.getElementById("paymentLineSelect");
                select.innerHTML = '<option value="-1">-- כל השורות --</option>';
                
                const rows = window.paymentHierarchy.rows.filter(r => r.plotId === plotId);
                rows.forEach(row => {
                    const option = document.createElement("option");
                    option.value = row.unicId;
                    option.textContent = row.lineNameHe || "שורה " + row.serialNumber;
                    select.appendChild(option);
                });
            }
            
            // אם זה עריכה, טען את הערכים השמורים
            if (itemId) {
                this.waitForElement('#paymentFormModal form', (form) => {
                    fetch(`/dashboard/dashboards/cemeteries/api/payments-api.php?action=get&id=${itemId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success && result.data) {
                                const payment = result.data;
                                
                                // מלא שדות רגילים
                                Object.keys(payment).forEach(key => {
                                    const field = form.elements[key];
                                    if (field) {
                                        field.value = payment[key] || '';
                                    }
                                });
                                
                                // טען היררכיה אם יש
                                if (payment.cemeteryId && payment.cemeteryId !== "-1") {
                                    setTimeout(() => {
                                        document.getElementById("paymentCemeterySelect").value = payment.cemeteryId;
                                        filterPaymentLocation("cemetery");
                                        
                                        setTimeout(() => {
                                            if (payment.blockId && payment.blockId !== "-1") {
                                                document.getElementById("paymentBlockSelect").value = payment.blockId;
                                                filterPaymentLocation("block");
                                            }
                                            
                                            setTimeout(() => {
                                                if (payment.plotId && payment.plotId !== "-1") {
                                                    document.getElementById("paymentPlotSelect").value = payment.plotId;
                                                    filterPaymentLocation("plot");
                                                }
                                                
                                                setTimeout(() => {
                                                    if (payment.lineId && payment.lineId !== "-1") {
                                                        document.getElementById("paymentLineSelect").value = payment.lineId;
                                                    }
                                                }, 100);
                                            }, 100);
                                        }, 100);
                                    }, 100);
                                }
                            }
                        })
                        .catch(error => console.error('Error loading payment data:', error));
                });
            }
        });
    },
    
    loadFormData: function(type, itemId) {
        this.waitForElement(`#${type}FormModal form`, (form) => {

            // ✅ מיפוי API לפי סוג הישות
            const apiEndpoints = {
                'cemetery': 'cemeteries-api.php',
                'block': 'blocks-api.php',
                'plot': 'plots-api.php',
                'areaGrave': 'areaGraves-api.php',
                'grave': 'graves-api.php',
                'customer': 'customers-api.php',
                'purchase': 'purchases-api.php',
                'burial': 'burials-api.php',
                'payment': 'payments-api.php'
            };

            // קבל את ה-endpoint הנכון או fallback ל-cemetery-hierarchy
            const endpoint = apiEndpoints[type];
            
            if (!endpoint) {
                console.warn(`⚠️ No specific API for type: ${type}, using cemetery-hierarchy`);
                fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=${type}&id=${itemId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            this.populateFormFields(form, result.data);
                        }
                    })
                    .catch(error => console.error('Error loading item data:', error));
                return;
            }

            // ✅ קרא ל-API הייעודי
            fetch(`${API_BASE}${endpoint}?action=get&id=${itemId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        this.populateFormFields(form, result.data);
                    }
                })
                .catch(error => console.error('Error loading item data:', error));
        });
    },

    // פונקציה עזר למילוי שדות (DRY) - גרסה מתוקנת
    populateFormFields: function(form, data, parentId = null) {
        // console.log('📝 Populating form fields:', { data });
        
        Object.keys(data).forEach(key => {
            const field = form.elements[key];
            if (!field) return; // אין שדה כזה - דלג
            
            // ⭐ אם השדה כבר מלא ויש לו ערך - אל תדרוס!
            if (field.value && field.value !== '') {
                // console.log(`⏭️ Skipping ${key} - already has value: ${field.value}`);
                return;
            }
            
            // מלא רק אם השדה ריק
            if (field.type === 'checkbox') {
                field.checked = data[key] == 1;
            } else {
                field.value = data[key] || '';
            }
        });
        
        // הוסף unicId אם חסר
        if (data.unicId && !form.elements['unicId']) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = 'unicId';
            hiddenField.value = data.unicId;
            form.appendChild(hiddenField);
        }
    },

    // ========================================================
    // --- START patch v1.8.0 (הוספת פונקציות שינוי הורה)
    // ========================================================

    // הוסף את הפונקציות האלה בתוך const FormHandler = { ... }
    // למשל אחרי הפונקציה closeForm או בסוף ה-object לפני הסגירה שלו
    changeParent: async function(type, itemId, currentParentId) {
        console.log('changeParent called:', type, itemId, currentParentId);
        
        // קבע מה סוג ההורה לפי סוג הפריט
        const parentTypeMap = {
            'block': 'cemetery',
            'plot': 'block',
            'row': 'plot',
            'areaGrave': 'row',
            'grave': 'areaGrave'
        };
        
        const parentType = parentTypeMap[type];
        if (!parentType) {
            alert('לא ניתן לשנות הורה לסוג זה');
            return;
        }
        
        // 🆕 טיפול מיוחד באחוזת קבר - שלוף את ה-lineId הנוכחי וה-plotId
        let actualParentId = currentParentId;
        let filterByParentId = null;
        
        if (type === 'areaGrave') {
            try {
                // שלוף את פרטי אחוזת הקבר כולל ה-lineId וה-plotId
                const response = await fetch(`${API_BASE}areaGraves-api.php?action=get&id=${itemId}`);
                const data = await response.json();

                if (data.success && data.data) {
                    actualParentId = data.data.lineId;  // ה-lineId הנוכחי
                    filterByParentId = data.data.plot_id || data.data.plotId || currentParentId;  // ⭐ קודם כל נבדוק plot_id
                    console.log('🔍 Area grave details:', { lineId: actualParentId, plotId: filterByParentId });
                }
            } catch (error) {
                console.error('Error fetching area grave details:', error);
            }
        }
        
        // שמור את המידע הנוכחי
        window.changingParentFor = {
            type: type,
            itemId: itemId,
            currentParentId: actualParentId,
            filterByParentId: filterByParentId
        };
        
        // פתח dialog לבחירת הורה חדש
        this.openParentChangeDialog(parentType, actualParentId, filterByParentId);
    },

    openParentChangeDialog: async function(parentType, currentParentId, filterByParentId = null) {
        try {
            // הסר מודל קיים אם יש
            const existingModal = document.getElementById('changeParentModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // הסר סטייל קיים
            const existingStyle = document.getElementById('changeParentModalStyle');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            // צור סטייל למודל
            const style = document.createElement('style');
            style.id = 'changeParentModalStyle';
            style.textContent = `
                #changeParentModal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 10000;
                    display: flex !important;
                    align-items: center;
                    justify-content: center;
                    animation: fadeIn 0.3s ease;
                }
                
                #changeParentModal .modal-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(5px);
                }
                
                #changeParentModal .modal-dialog {
                    position: relative;
                    z-index: 10001;
                    max-width: 500px;
                    width: 90%;
                    margin: 20px;
                }
                
                #changeParentModal .modal-content {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
                    overflow: hidden;
                    animation: slideIn 0.3s ease;
                }
                
                #changeParentModal .modal-header {
                    padding: 1.5rem;
                    border-bottom: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                
                #changeParentModal .modal-title {
                    margin: 0;
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: white;
                }
                
                #changeParentModal .close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: white;
                    opacity: 0.8;
                    padding: 0;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                #changeParentModal .close:hover {
                    opacity: 1;
                }
                
                #changeParentModal .modal-body {
                    padding: 1.5rem;
                }
                
                #changeParentModal .form-group {
                    margin-bottom: 1rem;
                }
                
                #changeParentModal .form-group label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-weight: 500;
                    color: #475569;
                }
                
                #changeParentModal .form-control {
                    width: 100%;
                    padding: 0.625rem 0.875rem;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 0.875rem;
                    direction: rtl;
                }
                
                #changeParentModal .form-control:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                #changeParentModal .alert {
                    padding: 12px;
                    background: #e3f2fd;
                    border-right: 4px solid #2196f3;
                    border-radius: 8px;
                    font-size: 0.875rem;
                    color: #1565c0;
                }
                
                #changeParentModal .modal-footer {
                    padding: 1rem 1.5rem;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.75rem;
                    background: #f8fafc;
                }
                
                #changeParentModal .btn {
                    padding: 0.625rem 1.25rem;
                    border: none;
                    border-radius: 8px;
                    font-size: 0.875rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }
                
                #changeParentModal .btn-secondary {
                    background: #f1f5f9;
                    color: #475569;
                }
                
                #changeParentModal .btn-secondary:hover {
                    background: #e2e8f0;
                }
                
                #changeParentModal .btn-primary {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                }
                
                #changeParentModal .btn-primary:hover {
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
                    transform: translateY(-1px);
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
            
            // צור modal HTML
            const modalHtml = `
                <div id="changeParentModal">
                    <div class="modal-overlay" onclick="FormHandler.closeParentChangeDialog()"></div>
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">בחר ${this.getParentLabel(parentType)} חדש</h5>
                                <button type="button" class="close" onclick="FormHandler.closeParentChangeDialog()">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>בחר ${this.getParentLabel(parentType)}:</label>
                                    <select id="newParentSelect" class="form-control">
                                        <option value="">-- טוען רשימה --</option>
                                    </select>
                                </div>
                                <div id="parentChangeWarning" class="alert" style="display: none;">
                                    <small>שינוי ההורה יעביר את הפריט למיקום חדש במערכת.</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="FormHandler.closeParentChangeDialog()">ביטול</button>
                                <button type="button" class="btn btn-primary" onclick="FormHandler.confirmParentChange()">אישור שינוי</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // הוסף את המודל לדף
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = modalHtml;
            document.body.appendChild(tempDiv.firstElementChild);
            document.body.style.overflow = 'hidden';
            
            // טען את רשימת ההורים האפשריים
            // await this.loadParentOptions(parentType, currentParentId);

            // const filterByParentId = window.changingParentFor?.filterByParentId || null;
            await this.loadParentOptions(parentType, currentParentId, filterByParentId);
            
        } catch (error) {
            console.error('Error opening parent change dialog:', error);
            alert('שגיאה בפתיחת חלון בחירת הורה');
        }
    },

    loadParentOptions: async function(parentType, currentParentId, filterByParentId = null) {
        try {
            let url = '';
            
            // 🔥 טיפול מיוחד בשורות - נשתמש ב-plots-api
            if (parentType === 'row') {
                if (!filterByParentId) {
                    throw new Error('לא ניתן לטעון שורות ללא מזהה חלקה');
                }
                url = `${API_BASE}plots-api.php?action=list_rows&plotId=${filterByParentId}`;
            } else {
                // שאר הסוגים - משתמשים ב-API הרגיל שלהם
                const apiMap = {
                    'cemetery': 'cemeteries-api.php',
                    'block': 'blocks-api.php',
                    'plot': 'plots-api.php',
                    'areaGrave': 'areaGraves-api.php',
                    'grave': 'graves-api.php'
                };
                
                const apiFile = apiMap[parentType];
                if (!apiFile) {
                    throw new Error(`לא נמצא API עבור סוג: ${parentType}`);
                }
                
                url = `${API_BASE}${apiFile}?action=list&limit=1000`;
                
                // הוספת סינון אופציונלי
                if (parentType === 'plot' && filterByParentId) {
                    url += `&blockId=${filterByParentId}`;
                } else if (parentType === 'block' && filterByParentId) {
                    url += `&cemeteryId=${filterByParentId}`;
                }
            }
            
            console.log('🔍 Loading parent options from:', url);
            
            const response = await fetch(url);
            const data = await response.json();
            
            const select = document.getElementById('newParentSelect');
            if (!select) {
                console.error('Select element not found');
                return;
            }
            
            // נקה את הסלקט
            select.innerHTML = '<option value="">-- בחר --</option>';
            
            if (data.success && data.data) {
                console.log(`✅ Loaded ${data.data.length} ${parentType} options`);
                
                data.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.unicId || item.id;
                    
                    // זיהוי שם הפריט
                    let displayName = item.name || item.nameHe || '';
                    if (!displayName) {
                        switch(parentType) {
                            case 'cemetery':
                                displayName = item.cemeteryNameHe;
                                break;
                            case 'block':
                                displayName = item.blockNameHe;
                                break;
                            case 'plot':
                                displayName = item.plotNameHe;
                                break;
                            case 'row':
                                displayName = item.lineNameHe || `שורה ${item.serialNumber}`;
                                break;
                            case 'areaGrave':
                                displayName = item.areaGraveNameHe;
                                break;
                            case 'grave':
                                displayName = item.graveNameHe;
                                break;
                            default:
                                displayName = `${parentType} ${item.id}`;
                        }
                    }
                    
                    option.textContent = displayName;
                    
                    // סמן את ההורה הנוכחי
                    if (option.value === currentParentId) {
                        option.textContent += ' (נוכחי)';
                        option.disabled = true;
                    }
                    
                    select.appendChild(option);
                });
                
                // הצג אזהרה
                document.getElementById('parentChangeWarning').style.display = 'block';
            } else {
                select.innerHTML = '<option value="">אין נתונים זמינים</option>';
            }
        } catch (error) {
            console.error('Error loading parent options:', error);
            const select = document.getElementById('newParentSelect');
            if (select) {
                select.innerHTML = '<option value="">שגיאה בטעינת הנתונים</option>';
            }
        }
    },

    confirmParentChange: function() {
        const select = document.getElementById('newParentSelect');
        const newParentId = select ? select.value : null;
        
        if (!newParentId) {
            alert('יש לבחור הורה חדש');
            return;
        }
        
        // עדכן את השם בטופס הראשי
        const selectedOption = select.options[select.selectedIndex];
        const newParentName = selectedOption.textContent.replace(' (נוכחי)', '');
        
        // עדכן את התצוגה
        const parentNameElement = document.getElementById('currentParentName');
        if (parentNameElement) {
            parentNameElement.textContent = newParentName;
        }
        
        // עדכן את ה-hidden field
        const newParentIdField = document.getElementById('newParentId');
        if (newParentIdField) {
            newParentIdField.value = newParentId;
        }
        
        // עדכן את ה-parentId הרגיל
        const parentIdField = document.querySelector('input[name="parentId"]');
        if (parentIdField) {
            parentIdField.value = newParentId;
        }
        
        // סגור את החלון
        this.closeParentChangeDialog();
        
        // הצג הודעה
        this.showMessage('ההורה שונה בהצלחה. יש לשמור את הטופס כדי לעדכן את השינוי.', 'info');
    },

    closeParentChangeDialog: function() {
        const modal = document.getElementById('changeParentModal');
        if (modal) {
            modal.remove();
        }
        
        const style = document.getElementById('changeParentModalStyle');
        if (style) {
            style.remove();
        }
        
        document.body.style.overflow = '';
        window.changingParentFor = null;
    },

    getParentLabel: function(parentType) {
        const labels = {
            'cemetery': 'בית עלמין',
            'block': 'גוש',
            'plot': 'חלקה',
            'row': 'שורה',
            'areaGrave': 'אחוזת קבר'
        };
        return labels[parentType] || parentType;
    },

    // ========================================================
    // --- END patch v1.8.0
    // ========================================================
    
    closeForm: function(type) {
        // console.log('Closing form:', type);
        
        const modal = document.getElementById(type + 'FormModal');
        if (modal) {
            modal.remove();
        }
        
        const style = document.getElementById(type + 'FormStyle');
        if (style) {
            style.remove();
        }
        
        document.body.style.overflow = '';

        if (type === 'purchase') {
            window.isEditMode = false;
            window.purchasePayments = [];
            window.selectedGraveData = null;
        }
    },
    
    saveForm: async function(formData, type) {
        try {
            const isEdit = formData.has('id') || formData.has('itemId');
            const action = isEdit ? 'update' : 'create';
            
            const data = {};
            let newParentId = null;

            // =========================================
            // ⭐ הוספה חדשה - ולידציה לאחוזת קבר
            // =========================================

            // שורות 3396-3427 (עם התיקון)
            if (type === 'areaGrave') {
                if (typeof window.validateGravesData === 'function') {
                    console.log('🔍 Running graves validation...');
                    
                    if (!window.validateGravesData()) {
                        console.error('❌ Graves validation failed');
                        return;
                    }
                    
                    console.log('✅ Graves validation passed');
                    
                    // ⭐⭐⭐ זה החלק שחסר! ⭐⭐⭐
                    const gravesDataInput = document.getElementById('gravesData');
                    if (gravesDataInput && gravesDataInput.value) {
                        console.log('📥 Reading gravesData from hidden input after validation');
                        console.log('📊 gravesData length:', gravesDataInput.value.length, 'chars');
                        
                        formData.set('gravesData', gravesDataInput.value);
                        
                        console.log('✅ gravesData added to formData');
                    } else {
                        console.error('❌ gravesData input not found or empty!');
                        this.showMessage('שגיאה: נתוני הקברים לא נמצאו', 'error');
                        return false;
                    }
                } else {
                    console.error('❌ validateGravesData function not found!');
                    this.showMessage('שגיאה: פונקציית ולידציה לא נמצאה', 'error');
                    return false;
                }
            }

            for (let [key, value] of formData.entries()) {
                if (key === 'formType' || key === 'itemId') {
                    continue;
                }

                // תפוס את ה-parentId החדש אם יש
                if (key === 'newParentId' && value) {
                    newParentId = value;
                    continue; // אל תשלח את newParentId עצמו
                }
                
                if (key === 'is_small_grave' || key === 'isSmallGrave') {
                    data[key] = value === 'on' ? 1 : 0;
                } else if (value !== '') {
                    data[key] = value;
                }
            }

            // אם יש parentId חדש (מכפתור השינוי) - השתמש בו
            if (newParentId) {
                data['parentId'] = newParentId;
            }
            
            // טיפול ב-parent_id לפי סוג
            if (data.parentId || data.parent_id) {
                const parentValue = data.parentId || data.parent_id;
                const parentColumn = this.getParentColumn(type);
                
                // if (parentColumn) {
                //     data[parentColumn] = parentValue;
                //     delete data.parentId;
                //     delete data.parent_id;
                // }
                if (parentColumn) {
                    // ⭐ תיקון: אל תדרוס אם השדה כבר קיים!
                    if (!data[parentColumn] || data[parentColumn] === parentValue) {
                        data[parentColumn] = parentValue;
                        console.log(`✅ Set ${parentColumn} = ${parentValue}`);
                    } else {
                        console.log(`⚠️ Skipping - ${parentColumn} already has value: ${data[parentColumn]}`);
                    }
                    delete data.parentId;
                    delete data.parent_id;
                }
            }

            
            let url;
            if (type === 'areaGrave') {
                url = `/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=${action}`;
            } else if (type === 'grave') {
                url = `/dashboard/dashboards/cemeteries/api/graves-api.php?action=${action}`;
            } else if (type === 'customer') {
                url = `/dashboard/dashboards/cemeteries/api/customers-api.php?action=${action}`;
            } else if (type === 'purchase') {
                url = `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=${action}`;
            } else if (type === 'burial') {
                url = `/dashboard/dashboards/cemeteries/api/burials-api.php?action=${action}`;
            } else if (type === 'payment') {
                url = `/dashboard/dashboards/cemeteries/api/payments-api.php?action=${action}`;
            } else if (type === 'cemetery') {
                url = `/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=${action}`;
            } else if (type === 'block') {
                url = `/dashboard/dashboards/cemeteries/api/blocks-api.php?action=${action}`;
            } else if (type === 'plot') {
                url = `/dashboard/dashboards/cemeteries/api/plots-api.php?action=${action}`;
            } else {
                url = `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=${action}&type=${type}`;
            }
            
            if (isEdit) {
                const unicId = formData.get('unicId');
                if (!unicId) {
                    this.showMessage('שגיאה: חסר מזהה ייחודי', 'error');
                    return false;
                }
                url += `&id=${unicId}`;
            }

            console.log('📤 Sending data:', data);
            console.log('🌐 URL:', url);
            
            const response = await fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage(
                    isEdit ? 'הפריט עודכן בהצלחה' : 'הפריט נוסף בהצלחה',
                    'success'
                );
                
                this.closeForm(type);
                
                if (typeof refreshData === 'function') {
                    refreshData();
                } else if (typeof tableRenderer !== 'undefined' && tableRenderer.loadAndDisplay) {
                    tableRenderer.loadAndDisplay(window.currentType, window.currentParentId);
                } else if (typeof loadData === 'function') {
                    loadData();
                } else {
                    location.reload();
                }
                
                return true;
            } else {
                this.showMessage(result.error || 'שגיאה בשמירה', 'error');
                return false;
            }
            
        } catch (error) {
            console.error('Error saving form:', error);
            this.showMessage('שגיאה בשמירת הנתונים', 'error');
            return false;
        }
    },
    
    getParentColumn: function(type) {
        const columns = {
            'block': 'cemeteryId',
            'plot': 'blockId',
            'row': 'plotId',
            'areaGrave': 'lineId',
            'grave': 'areaGraveId',
            'purchase': 'customerId',
            'burial': 'graveId'
        };
        return columns[type] || null;
    },
    
    showMessage: function(message, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(type, message);
        } else if (typeof showSuccess === 'function' && type === 'success') {
            showSuccess(message);
        } else if (typeof showError === 'function' && type === 'error') {
            showError(message);
        } else {
            alert(message);
        }
    }
};

// פונקציה גלובלית לטיפול בבחירת קבר - להתאמה עם ההיררכיה הקיימת
window.onGraveSelected = function(graveId) {
    // קרא לפונקציה המקורית של GraveHierarchyManager אם יש
    if (window.GraveHierarchyManager && window.GraveHierarchyManager.onGraveSelected) {
        window.GraveHierarchyManager.onGraveSelected(graveId);
    }
    
    // בדוק אם אנחנו בטופס קבורה
    if (document.getElementById('burialFormModal')) {
        // הפונקציה תופעל אוטומטית דרך הלוגיקה החדשה
        return;
    }
};

/**
 * פתיחת טופס עריכת אחוזת קבר (דרך כפתור בכרטיס קבר)
 * ⭐ גרסה מתוקנת - שולפת parentId לפני פתיחת הטופס
 */
window.openGraveEdit = async function(graveId) {
    console.log('📝 פותח עריכת אחוזת קבר עבור קבר:', graveId);
    
    // 1️⃣ קרא את ה-areaGraveId מה-hidden field
    const modal = document.getElementById('graveCardFormModal');
    if (!modal) {
        console.error('❌ Modal לא נמצא!');
        alert('שגיאה: חלון כרטיס הקבר לא נמצא');
        return;
    }
    
    const areaGraveIdField = modal.querySelector('input[name="areaGraveId"]');
    if (!areaGraveIdField || !areaGraveIdField.value) {
        console.error('❌ areaGraveId לא נמצא!');
        alert('שגיאה: לא נמצא מזהה אחוזת הקבר');
        return;
    }
    
    const areaGraveId = areaGraveIdField.value;
    console.log('✅ נמצא areaGraveId:', areaGraveId);
    
    // 2️⃣ ⭐ שלוף את ה-parentId מה-API לפני פתיחת הטופס!
    try {
        console.log('🔍 שולף parentId לפני פתיחת הטופס...');
        
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=get&id=${areaGraveId}`);
        const result = await response.json();
        
        let parentId = null;
        
        if (result.success && result.data) {
            // ⭐ שלוף את ה-lineId (ההורה)
            parentId = result.data.lineId || result.data.line_id || result.data.rowId || result.data.row_id;
            console.log('✅ נמצא parentId:', parentId);
        } else {
            console.warn('⚠️ לא הצלחנו לשלוף parentId מה-API');
        }
        
        // 3️⃣ סגור את כרטיס הקבר
        FormHandler.closeForm('graveCard');
        
        // 4️⃣ ⭐ פתח עריכת אחוזת הקבר עם parentId נכון!
        FormHandler.openForm('areaGrave', parentId, areaGraveId);
        
    } catch (error) {
        console.error('❌ שגיאה בשליפת parentId:', error);
        
        // במקרה של שגיאה - פתח בלי parentId
        FormHandler.closeForm('graveCard');
        FormHandler.openForm('areaGrave', null, areaGraveId);
    }
};

// טען את מנהל התשלומים
if (typeof PaymentDisplayManager !== 'undefined') {
    window.PaymentDisplayManager = PaymentDisplayManager;
}

// טען את מנהל התשלומים
if (typeof PaymentDisplayManager !== 'undefined') {
    window.PaymentDisplayManager = PaymentDisplayManager;
}

// grave-hierarchy-manager.js - מנהל היררכיית קברים משותף
const GraveHierarchyManager = {
    
    // הגדרות
    allowedStatuses: [1], // ברירת מחדל - רק פנויים
    excludeGraveId: null, // הקבר להתעלם ממנו (במצב עריכה)
    onGraveSelected: null, // callback כשנבחר קבר
    
    // אתחול המנהל
    init: function(options = {}) {
        
        this.allowedStatuses = options.allowedStatuses || [1];
        this.excludeGraveId = options.excludeGraveId || null;
        this.onGraveSelected = options.onGraveSelected || null;
        
        // בדוק שיש נתונים
        const fieldset = document.getElementById('grave-selector-fieldset');
        if (!fieldset || !fieldset.dataset.hierarchy) {
            console.error('No hierarchy data found!');
            return false;
        }
        
        window.hierarchyData = JSON.parse(fieldset.dataset.hierarchy);
        
        // הגדר את כל הפונקציות
        this.setupAllFunctions();
        
        // אתחל בוררים
        window.populateBlocks();
        window.populatePlots();
        
        return true;
    },
    
    // הגדרת כל הפונקציות
    setupAllFunctions: function() {
        const self = this; // שמור reference למנהל
        
        // ========== פילטור היררכיה ==========
        window.filterHierarchy = function(level) {
            const cemetery = document.getElementById('cemeterySelect').value;
            const block = document.getElementById('blockSelect').value;
            const plot = document.getElementById('plotSelect').value;
            const row = document.getElementById('rowSelect').value;
            const areaGrave = document.getElementById('areaGraveSelect').value;
            
            switch(level) {
                case 'cemetery':
                    populateBlocks(cemetery);
                    populatePlots(cemetery, null);
                    clearSelectors(['row', 'areaGrave', 'grave']);
                    // נקה גם את נתוני הלקוח והקבר
                    clearCustomerAndGraveData();
                    break;
                    
                case 'block':
                    if (block) {
                        const selectedBlock = window.hierarchyData.blocks.find(b => b.unicId == block);
                        if (selectedBlock && selectedBlock.cemetery_id) {
                            document.getElementById('cemeterySelect').value = selectedBlock.cemeteryId;
                            populateBlocks(selectedBlock.cemeteryId);
                            document.getElementById('blockSelect').value = block;
                        }
                    }
                    populatePlots(null, block);
                    clearSelectors(['row', 'areaGrave', 'grave']);
                    // נקה גם את נתוני הלקוח והקבר
                    clearCustomerAndGraveData();
                    break;
                    
                case 'plot':
                    if (plot) {
                        const selectedPlot = window.hierarchyData.plots.find(p => p.unicId == plot);
                        if (selectedPlot) {
                            if (selectedPlot.blockId && document.getElementById('blockSelect').value != selectedPlot.blockId) {
                                document.getElementById('blockSelect').value = selectedPlot.blockId;
                                
                                const selectedBlock = window.hierarchyData.blocks.find(b => b.unicId == selectedPlot.blockId);
                                if (selectedBlock && selectedBlock.cemeteryId) {
                                    document.getElementById('cemeterySelect').value = selectedBlock.cemeteryId;
                                    populateBlocks(selectedBlock.cemeteryId);
                                    document.getElementById('blockSelect').value = selectedPlot.blockId;
                                }
                            }
                            
                            populatePlots(null, selectedPlot.blockId);
                            document.getElementById('plotSelect').value = plot;
                        }
                        
                        populateRows(plot);
                        document.getElementById('rowSelect').disabled = false;
                    } else {
                        clearSelectors(['row', 'areaGrave', 'grave']);
                        document.getElementById('rowSelect').disabled = true;
                    }
                    // נקה גם את נתוני הלקוח והקבר
                    clearCustomerAndGraveData();
                    break;
                    
                case 'row':
                    if (row) {
                        populateAreaGraves(row);
                        document.getElementById('areaGraveSelect').disabled = false;
                        // נקה רק אחרי שמילאנו את האחוזות החדשות
                        setTimeout(() => clearCustomerAndGraveData(), 50);
                    } else {
                        clearSelectors(['areaGrave', 'grave']);
                        document.getElementById('areaGraveSelect').disabled = true;
                        // נקה מיד כי אין שורה
                        clearCustomerAndGraveData();
                    }
                    break;
                    
                case 'areaGrave':
                    if (areaGrave) {
                        populateGraves(areaGrave);
                        document.getElementById('graveSelect').disabled = false;
                        // נקה רק אחרי שמילאנו את הקברים החדשים
                        setTimeout(() => clearCustomerAndGraveData(), 50);
                    } else {
                        clearSelectors(['grave']);
                        document.getElementById('graveSelect').disabled = true;
                        // נקה מיד כי אין אחוזת קבר
                        clearCustomerAndGraveData();
                    }
                    break;
                    
                case 'grave':
                    // כשנבחר קבר - קרא ל-callback
                    const graveId = document.getElementById('graveSelect').value;
                    if (graveId && self.onGraveSelected) {
                        self.onGraveSelected(graveId);
                    } else if (!graveId) {
                        // אם ביטלו בחירת קבר - נקה נתונים
                        clearCustomerAndGraveData();
                    }
                    break;
            }
        };

        // פונקציה חדשה לניקוי נתוני לקוח וקבר
        function clearCustomerAndGraveData() {
            // נקה את נתוני הקבר הנבחר
            window.selectedGraveData = null;
            
            // נקה את בחירת הלקוח (רק אם לא במצב עריכה)
            if (!window.isEditMode) {
                const customerSelect = document.querySelector('[name="clientId"]');
                if (customerSelect) {
                    customerSelect.value = '';
                }
                window.selectedCustomerData = null;
            }
            
            // קרא ל-callback עם null כדי לנקות
            if (window.GraveHierarchyManager && window.GraveHierarchyManager.onGraveSelected) {
                window.GraveHierarchyManager.onGraveSelected(null);
            }
            
            // נקה הודעות סטטוס
            const statusDiv = document.getElementById('graveStatusNotification');
            if (statusDiv) {
                statusDiv.style.display = 'none';
            }
        }
        
        // ========== מילוי גושים ==========
        window.populateBlocks = function(cemeteryId = null) {
            const blockSelect = document.getElementById('blockSelect');
            if (!blockSelect) return;
            
            blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
            
            const blocks = cemeteryId 
                ? window.hierarchyData.blocks.filter(b => b.cemeteryId == cemeteryId)
                : window.hierarchyData.blocks;
            
            blocks.forEach(block => {
                const hasAvailableGraves = self.checkBlockHasGraves(block.unicId);
                const option = document.createElement('option');
                option.value = block.unicId;
                option.textContent = block.blockNameHe + (!hasAvailableGraves ? ' (אין קברים זמינים)' : '');
                
                if (!hasAvailableGraves) {
                    option.disabled = true;
                    option.style.color = '#999';
                }
                
                blockSelect.appendChild(option);
            });
        };
        
        // ========== מילוי חלקות ==========
        window.populatePlots = function(cemeteryId = null, blockId = null) {
            const plotSelect = document.getElementById('plotSelect');
            if (!plotSelect) return;
            
            plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
            
            let plots = window.hierarchyData.plots;
            
            if (blockId) {
                plots = plots.filter(p => p.blockId == blockId);
            } else if (cemeteryId) {
                plots = plots.filter(p => p.cemeteryId == cemeteryId);
            }
            
            plots.forEach(plot => {
                const hasAvailableGraves = self.checkPlotHasGraves(plot.unicId);
                const option = document.createElement('option');
                option.value = plot.unicId;
                option.textContent = plot.name + (!hasAvailableGraves ? ' (אין קברים זמינים)' : '');
                
                if (!hasAvailableGraves) {
                    option.disabled = true;
                    option.style.color = '#999';
                }
                
                plotSelect.appendChild(option);
            });
        };
        
        // ========== מילוי שורות ==========
        window.populateRows = function(plotId) {
            const rowSelect = document.getElementById('rowSelect');
            if (!rowSelect) return;
            
            rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
            
            const rows = window.hierarchyData.rows.filter(r => r.plot_id == plotId);
            
            rows.forEach(row => {
                const hasAvailableGraves = self.checkRowHasGraves(row.unicId);
                
                if (hasAvailableGraves) {
                    const option = document.createElement('option');
                    option.value = row.unicId;
                    option.textContent = row.name;
                    rowSelect.appendChild(option);
                }
            });
            
            if (rowSelect.options.length === 1) {
                rowSelect.innerHTML = '<option value="">-- אין שורות עם קברים זמינים --</option>';
            }
            
            // ⚠️ הוסף את זה בסוף הפונקציה:
            // נקה את האחוזות והקברים כי שינוי חלקה = אין אחוזת קבר או קבר נבחרים
            const areaGraveSelect = document.getElementById('areaGraveSelect');
            if (areaGraveSelect) {
                areaGraveSelect.innerHTML = '<option value="">-- בחר שורה תחילה --</option>';
                areaGraveSelect.value = '';
                areaGraveSelect.disabled = true;
            }

            const graveSelect = document.getElementById('graveSelect');
            if (graveSelect) {
                graveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר תחילה --</option>';
                graveSelect.value = '';
                graveSelect.disabled = true;
            }
        };

        // ========== מילוי אחוזות קבר ==========
        window.populateAreaGraves = function(rowId) {
            const areaGraveSelect = document.getElementById('areaGraveSelect');
            if (!areaGraveSelect) return;
            
            areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
            
            const areaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
            
            areaGraves.forEach(areaGrave => {
                const availableGraves = window.hierarchyData.graves.filter(g => 
                    g.area_grave_id == areaGrave.unicId && 
                    self.isGraveAvailable(g)  // תיקון - משתמש ב-isGraveAvailable
                );
                
                if (availableGraves.length > 0) {
                    const option = document.createElement('option');
                    option.value = areaGrave.unicId;
                    option.textContent = areaGrave.name + ` (${availableGraves.length} קברים זמינים)`;
                    areaGraveSelect.appendChild(option);
                }
            });
            
            if (areaGraveSelect.options.length === 1) {
                areaGraveSelect.innerHTML = '<option value="">-- אין אחוזות קבר פנויות --</option>';
            }
            
            // נקה את הקברים כי שינוי שורה = אין קבר נבחר
            const graveSelect = document.getElementById('graveSelect');
            if (graveSelect) {
                graveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר תחילה --</option>';
                graveSelect.value = '';
                graveSelect.disabled = true;
                
                // הפעל את האירוע change כדי שהמערכת תבין שהקבר התרוקן
                graveSelect.dispatchEvent(new Event('change'));
            }
        };
        
        // ========== מילוי קברים ==========
        window.populateGraves = function(areaGraveId) {
            const graveSelect = document.getElementById('graveSelect');
            if (!graveSelect) return;
            
            graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
            
            const graves = window.hierarchyData.graves.filter(g => 
                g.area_grave_id == areaGraveId &&
                self.isGraveAvailable(g)  // תיקון - משתמש ב-isGraveAvailable
            );
            
            graves.forEach(grave => {
                const option = document.createElement('option');
                option.value = grave.unicId;
                
                // הוסף תיאור לפי סטטוס
                let statusText = '';
                if (self.excludeGraveId && grave.unicId == self.excludeGraveId) {
                    statusText = ' (הקבר הנוכחי)';
                } else if (grave.graveStatus == 2) {
                    statusText = ' (רכישה)';
                } else if (grave.graveStatus == 3) {
                    statusText = ' (תפוס)';
                }
                
                option.textContent = `קבר ${grave.graveNameHe}${statusText}`;
                graveSelect.appendChild(option);
            });
            
            // הוסף listener לשינוי
            graveSelect.onchange = function() {
                if (this.value && self.onGraveSelected) {
                    self.onGraveSelected(this.value);
                }
            };
        };
        
        // ========== ניקוי בוררים ==========
        window.clearSelectors = function(levels) {
            const configs = {
                'row': { id: 'rowSelect', default: '-- בחר חלקה תחילה --', disabled: true },
                'areaGrave': { id: 'areaGraveSelect', default: '-- בחר שורה תחילה --', disabled: true },
                'grave': { id: 'graveSelect', default: '-- בחר אחוזת קבר תחילה --', disabled: true }
            };
            
            levels.forEach(level => {
                const config = configs[level];
                if (config) {
                    const element = document.getElementById(config.id);
                    if (element) {
                        element.innerHTML = `<option value="">${config.default}</option>`;
                        element.disabled = config.disabled;
                    }
                }
            });
        };
    },

    // פונקציה לבדיקה האם קבר זמין
    isGraveAvailable: function(grave) {
        // אם זה הקבר שצריך להתעלם ממנו - הוא זמין!
        if (this.excludeGraveId && grave.unicId == this.excludeGraveId) {
            return true;
        }
        
        // אחרת - בדוק לפי סטטוס
        return this.allowedStatuses.includes(grave.graveStatus);
    },
    
    // ========== פונקציות בדיקה ==========
    checkBlockHasGraves: function(blockId) {
        const blockPlots = window.hierarchyData.plots.filter(p => p.blockId == blockId);
        
        for (let plot of blockPlots) {
            if (this.checkPlotHasGraves(plot.unicId)) {
                return true;
            }
        }
        return false;
    },
    
    checkPlotHasGraves: function(plotId) {
        const plotRows = window.hierarchyData.rows.filter(r => r.plotId == plotId);
        
        for (let row of plotRows) {
            const rowAreaGraves = window.hierarchyData.areaGraves.filter(ag => ag.lineId == row.unicId);
            
            for (let areaGrave of rowAreaGraves) {
                const graves = window.hierarchyData.graves.filter(g => 
                    g.areaGraveId == areaGrave.unicId &&
                    this.isGraveAvailable(g)  // תיקון - משתמש ב-isGraveAvailable
                );
                if (graves.length > 0) {
                    return true;
                }
            }
        }
        return false;
    },
    
    checkAreaGraveHasGraves: function(areaGraveId) {
        const graves = window.hierarchyData.graves.filter(g => 
            g.area_grave_id == areaGraveId &&
            this.isGraveAvailable(g)  // משתמש ב-isGraveAvailable
        );
        return graves.length > 0;
    },
    
    checkRowHasGraves: function(rowId) {
        const rowAreaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
        
        for (let areaGrave of rowAreaGraves) {
            const graves = window.hierarchyData.graves.filter(g => 
                g.area_grave_id == areaGrave.unicId &&
                this.isGraveAvailable(g)  // תיקון - משתמש ב-isGraveAvailable
            );
            if (graves.length > 0) {
                return true;
            }
        }
        return false;
    }
};

// הגדר גלובלית
window.GraveHierarchyManager = GraveHierarchyManager;

// הגדר גלובלית
// window.FormHandler = FormHandler;

// פונקציה גלובלית לטיפול בשליחת טופס
window.handleFormSubmit = function(event, type) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    FormHandler.saveForm(formData, type);
};

// האזן לאירועי DOM
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-dismiss="modal"]')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                const type = modal.id.replace('FormModal', '');
                FormHandler.closeForm(type);
            }
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal.show') || document.querySelector('.modal');
            if (modal) {
                const type = modal.id.replace('FormModal', '');
                FormHandler.closeForm(type);
            }
        }
    });
});

// ===========================================================
// 🔄 פונקציות גנריות לניהול ספינרים
// ===========================================================

/**
 * הוסף ספינר לשדה select (רגיל או SmartSelect)
 * @param {string} selectId - ID או name של ה-select
 */
window.showSelectSpinner = function(selectId) {
    const input = document.getElementById(selectId) || 
                  document.querySelector(`[name="${selectId}"]`);
    
    if (!input) {
        console.warn(`⚠️ Select ${selectId} not found`);
        return;
    }
    
    // ⭐ זיהוי SmartSelect
    const smartWrapper = input.closest('.smart-select-wrapper');
    
    if (smartWrapper) {
        // ⭐ זה SmartSelect - הוסף ספינר ל-display
        const display = smartWrapper.querySelector('.smart-select-display');
        const valueSpan = smartWrapper.querySelector('.smart-select-value');
        
        if (!display) {
            console.warn(`⚠️ SmartSelect display not found for ${selectId}`);
            return;
        }
        
        // בדוק אם כבר יש ספינר
        if (display.querySelector('.loading-spinner')) {
            console.log(`⚠️ Spinner already exists for ${selectId}`);
            return;
        }
        
        // שמור את הטקסט המקורי
        if (valueSpan) {
            valueSpan.dataset.originalText = valueSpan.textContent;
        }
        
        // יצירת ספינר
        const spinner = document.createElement('span');
        spinner.className = 'loading-spinner';
        spinner.id = `${selectId}-spinner`;
        spinner.style.cssText = `
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        `;
        
        // הוסף לתצוגה
        display.appendChild(spinner);
        
        // כיבוי
        smartWrapper.classList.add('disabled');
        input.disabled = true;
        display.style.opacity = '0.7';
        display.style.cursor = 'not-allowed';
        
        console.log(`🔄 SmartSelect spinner added to ${selectId}`);
        
    } else {
        // ⭐ זה select רגיל - הקוד המקורי
        let wrapper = input.parentElement;
        
        if (!wrapper || wrapper.tagName === 'FORM' || wrapper.classList.contains('form-group')) {
            const newWrapper = document.createElement('div');
            newWrapper.style.position = 'relative';
            newWrapper.style.display = 'block';
            input.parentNode.insertBefore(newWrapper, input);
            newWrapper.appendChild(input);
            wrapper = newWrapper;
        }
        
        if (wrapper.querySelector('.loading-spinner')) {
            console.log(`⚠️ Spinner already exists for ${selectId}`);
            return;
        }
        
        const spinner = document.createElement('span');
        spinner.className = 'loading-spinner loading-spinner-overlay';
        spinner.id = `${selectId}-spinner`;
        
        wrapper.style.position = 'relative';
        wrapper.appendChild(spinner);
        
        input.disabled = true;
        input.style.opacity = '0.7';
        
        console.log(`🔄 Regular select spinner added to ${selectId}`);
    }
};

/**
 * הסר ספינר משדה select (רגיל או SmartSelect)
 * @param {string} selectId - ID או name של ה-select
 */
window.hideSelectSpinner = function(selectId) {
    const input = document.getElementById(selectId) || 
                  document.querySelector(`[name="${selectId}"]`);
    
    if (!input) {
        console.warn(`⚠️ Select ${selectId} not found`);
        return;
    }
    
    // ⭐ זיהוי SmartSelect
    const smartWrapper = input.closest('.smart-select-wrapper');
    
    if (smartWrapper) {
        // ⭐ זה SmartSelect
        const display = smartWrapper.querySelector('.smart-select-display');
        const spinner = display?.querySelector('.loading-spinner') || 
                       document.getElementById(`${selectId}-spinner`);
        
        if (spinner) {
            spinner.remove();
            console.log(`✅ SmartSelect spinner removed from ${selectId}`);
        }
        
        // שחזר טקסט מקורי
        const valueSpan = smartWrapper.querySelector('.smart-select-value');
        if (valueSpan && valueSpan.dataset.originalText) {
            // אל תשחזר את הטקסט - נניח שהוא עודכן
            delete valueSpan.dataset.originalText;
        }
        
        // הפעל
        smartWrapper.classList.remove('disabled');
        input.disabled = false;
        display.style.opacity = '1';
        display.style.cursor = 'pointer';
        
    } else {
        // ⭐ זה select רגיל - הקוד המקורי
        const wrapper = input.parentElement;
        if (!wrapper) return;
        
        const spinner = wrapper.querySelector('.loading-spinner') || 
                       document.getElementById(`${selectId}-spinner`);
        
        if (spinner) {
            spinner.remove();
            console.log(`✅ Regular select spinner removed from ${selectId}`);
        }
        
        input.disabled = false;
        input.style.opacity = '1';
    }
};