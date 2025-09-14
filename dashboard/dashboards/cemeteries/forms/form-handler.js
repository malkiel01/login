// /dashboards/cemeteries/forms/form-handler.js
// טיפול בטפסים בצד הלקוח

const FormHandler = {
    // הוסף את הפונקציה החדשה כאן:
    handleParentSelection: function() {
        const select = document.getElementById('selected_parent');
        const selectedParentId = select ? select.value : null;
        
        if (!selectedParentId) {
            alert('יש לבחור הורה');
            return;
        }
        
        // קבל את הסוג הילד ששמרנו
        const childType = window.pendingChildType;
        
        // סגור את טופס הבחירה
        FormHandler.closeForm('parent_selector');
        
        // פתח את הטופס האמיתי עם ההורה שנבחר
        FormHandler.openForm(childType, selectedParentId, null);
    },
    openForm: async function(type, parentId = null, itemId = null) {
        if (!type || typeof type !== 'string') {
            console.error('Invalid type:', type);
            this.showMessage('שגיאה: סוג הטופס לא תקין', 'error');
            return;
        }
        
        console.log('FormHandler.openForm called with:', {type, parentId, itemId});
        
        try {
            const params = new URLSearchParams({
                type: type,
                ...(itemId && { id: itemId }),
                ...(parentId && { parent_id: parentId })
            });
            
            const response = await fetch(`/dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);
            const html = await response.text();
            
            console.log('Received HTML, looking for modal...');
            
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
            
            // צור container זמני לפירוק ה-HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // חפש את ה-style tag
            const styleTag = tempDiv.querySelector('style');
            if (styleTag) {
                styleTag.id = type + 'FormStyle';
                document.head.appendChild(styleTag);
                console.log('Style tag added to head');
            }
            
            // חפש את המודאל
            const modal = tempDiv.querySelector('#' + type + 'FormModal');
            
            if (modal) {
                console.log('Modal found, displaying...');
                
                // הוסף את המודאל ל-body
                document.body.appendChild(modal);
                
                // מנע גלילה בדף הראשי
                document.body.style.overflow = 'hidden';

                // טעינת שורות דינמית עבור אחוזת קבר
                if (type === 'area_grave' && parentId) {
                    setTimeout(() => {
                        const lineSelect = document.querySelector('#area_graveFormModal select[name="lineId"]');
                        if (lineSelect) {
                            fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${parentId}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.data.length > 0) {
                                        lineSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
                                        data.data.forEach(row => {
                                            const option = document.createElement('option');
                                            option.value = row.unicId;
                                            option.textContent = row.lineNameHe || `שורה ${row.serialNumber}`;
                                            lineSelect.appendChild(option);
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading rows:', error);
                                });
                        }
                    }, 300);
                }
                
                // טען נתונים אם זה טופס עריכה
                if (itemId) {
                    console.log('Loading data for edit, itemId:', itemId);
                    setTimeout(() => {
                        const url = `${API_BASE}cemetery-hierarchy.php?action=get&type=${type}&id=${itemId}`;
                        console.log('Fetching from:', url);
                        
                        fetch(url)
                            .then(response => response.json())
                            .then(result => {
                                console.log('Received data:', result);
                                
                                if (result.success && result.data) {
                                    const form = document.querySelector(`#${type}FormModal form`);
                                    console.log('Found form:', form);
                                    
                                    if (form) {
                                        // מלא את השדות
                                        Object.keys(result.data).forEach(key => {
                                            const field = form.elements[key];
                                            if (field) {
                                                console.log(`Setting field ${key} to:`, result.data[key]);
                                                if (field.type === 'checkbox') {
                                                    field.checked = result.data[key] == 1;
                                                } else {
                                                    field.value = result.data[key] || '';
                                                }
                                            } else {
                                                console.log(`Field ${key} not found in form`);
                                            }
                                        });
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error loading item data:', error);
                            });
                    }, 500); // הגדלתי את הזמן ל-500ms
                }
                
            } else {
                console.error('Modal not found in HTML, trying alternative approach...');
                
                // אם לא נמצא מודאל עם ID, חפש לפי class
                const alternativeModal = tempDiv.querySelector('.modal');
                if (alternativeModal) {
                    alternativeModal.id = type + 'FormModal';
                    document.body.appendChild(alternativeModal);
                    document.body.style.overflow = 'hidden';
                } else {
                    console.error('No modal found at all');
                    const content = tempDiv.firstElementChild;
                    if (content) {
                        document.body.appendChild(content);
                    }
                }
            }
            
        } catch (error) {
            console.error('Error loading form:', error);
            this.showMessage('שגיאה בטעינת הטופס', 'error');
        }
    },
    
    closeForm: function(type) {
        console.log('Closing form:', type);
        
        // הסר את המודאל
        const modal = document.getElementById(type + 'FormModal');
        if (modal) {
            modal.remove();
        }
        
        // הסר את ה-style
        const style = document.getElementById(type + 'FormStyle');
        if (style) {
            style.remove();
        }
        
        // החזר גלילה לדף
        document.body.style.overflow = '';
    },
    
    saveForm: async function(formData, type) {
        try {
            const isEdit = formData.has('id');
            const action = isEdit ? 'update' : 'create';
            
            // המר FormData לאובייקט
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'type') {
                    if (key === 'is_small_grave' || key === 'isSmallGrave') {
                        data[key] = value === 'on' ? 1 : 0;
                    } else if (value !== '') {
                        data[key] = value;
                    }
                }
            }
            
            // טיפול בשדה parent_id
            if (data.parent_id) {
                const parentColumn = this.getParentColumn(type);
                if (parentColumn) {
                    // עבור area_grave, אל תדרוס את lineId אם הוא נבחר מהטופס
                    if (type === 'area_grave' && data.lineId) {
                        // lineId כבר נבחר מהטופס, אל תדרוס אותו
                        delete data.parent_id;
                    } else {
                        data[parentColumn] = data.parent_id;
                        delete data.parent_id;
                    }
                }
            }
            
            // תיקון שמות types
            if (type === 'areaGrave') type = 'area_grave';
            
            // הוסף שדות ברירת מחדל
            data.isActive = 1;
            
            // בנה URL
            let url;
            if (type === 'customer') {
                url = `/dashboard/dashboards/cemeteries/api/customers-api.php?action=${action}`;
            } else if (type === 'purchase') {
                url = `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=${action}`;
            } else if (type === 'payment') {
                url = `/dashboard/dashboards/cemeteries/api/payments-api.php?action=${action}`;
            } else {
                url = `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=${action}&type=${type}`;
            }
            
            if (isEdit) {
                url += `&id=${formData.get('id')}`;
            }
            
            console.log('Saving to:', url);
            console.log('Data:', data);
            
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
                
                // רענן נתונים
                if (typeof tableRenderer !== 'undefined' && tableRenderer.loadAndDisplay) {
                    tableRenderer.loadAndDisplay(window.currentType, window.currentParentId);
                } else if (typeof refreshData === 'function') {
                    refreshData();
                } else if (typeof loadAllData === 'function') {
                    loadAllData();
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
            'area_grave': 'lineId',
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

// הגדר גלובלית
window.FormHandler = FormHandler;

// פונקציה גלובלית לטיפול בשליחת טופס
window.handleFormSubmit = function(event, type) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    FormHandler.saveForm(formData, type);
};

// האזן לאירועי DOM
document.addEventListener('DOMContentLoaded', function() {
    // האזן לקליקים על כפתורי סגירה
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-dismiss="modal"]')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                const type = modal.id.replace('FormModal', '');
                FormHandler.closeForm(type);
            }
        }
    });
    
    // האזן ל-ESC לסגירת מודל
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal.show');
            if (!modal) {
                // אם אין modal עם class show, חפש כל modal
                const anyModal = document.querySelector('.modal');
                if (anyModal) {
                    const type = anyModal.id.replace('FormModal', '');
                    FormHandler.closeForm(type);
                }
            } else {
                const type = modal.id.replace('FormModal', '');
                FormHandler.closeForm(type);
            }
        }
    });
});

// פונקציות גלובליות לתאימות אחורה
window.openFormModal = function(type, parentId, itemId) {
    FormHandler.openForm(type, parentId, itemId);
};

window.closeFormModal = function(type) {
    FormHandler.closeForm(type);
};
