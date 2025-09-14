// /dashboards/cemeteries/forms/form-handler.js
// טיפול בטפסים בצד הלקוח

const FormHandler2 = {
    // פתיחת טופס
    openForm: async function(type, parentId = null, itemId = null) {
        console.log('FormHandler.openForm called with:', {type, parentId, itemId});
        try {
            // טען את הטופס מהשרת
            const params = new URLSearchParams({
                type: type,
                ...(itemId && { id: itemId }),
                ...(parentId && { parent_id: parentId })
            });
            
            console.log('Fetching form from:', `dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);
            
            const response = await fetch(`/dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);

            
            console.log('Response status:', response.status);
            
            const html = await response.text();
            
            console.log('Received HTML length:', html.length);
            console.log('First 100 chars:', html.substring(0, 100));
            
            // הסר טופס קיים אם יש
            const existingModal = document.getElementById(type + 'FormModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // הוסף את הטופס ל-DOM
            const range = document.createRange();
            const fragment = range.createContextualFragment(html);
            document.body.appendChild(fragment);
            
            // הצג את המודל
            const modal = document.getElementById(type + 'FormModal');
            if (modal) {
                // אם יש Bootstrap
                if (typeof bootstrap !== 'undefined') {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                } else {
                    // השתמש באותו מנגנון כמו במודל המקורי
                    modal.classList.add('show');
                }
            }
            
        } catch (error) {
            console.error('Error loading form:', error);
            this.showMessage('שגיאה בטעינת הטופס', 'error');
        }
    },
    
    // סגירת טופס
    closeForm: function(type) {
        const modal = document.getElementById(type + 'FormModal');
        if (modal) {
            if (typeof bootstrap !== 'undefined') {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            } else {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
            
            // הסר מה-DOM אחרי סגירה
            setTimeout(() => modal.remove(), 300);
        }
    },
    
    // שמירת טופס
    saveForm: async function(formData, type) {
        try {
            // קבע את ה-API endpoint
            const isEdit = formData.has('id');
            const action = isEdit ? 'update' : 'create';
            
            // המר FormData לאובייקט
            const data = {};
            for (let [key, value] of formData.entries()) {
                // דלג על השדה type
                if (key !== 'type') {
                    // טיפול מיוחד בשדות מסוימים
                    if (key === 'is_small_grave') {
                        data[key] = value === 'on' ? 1 : 0;
                    } else if (value !== '') {
                        data[key] = value;
                    }
                }
            }
            
            // טיפול מיוחד בטבלת graves - אין עמודת name!
            if (type === 'grave' && data.grave_number && !data.name) {
                // לא להוסיף name לטבלת graves
                delete data.name;
            }
            
            // הוסף parent_id אם נדרש
            const parentColumn = this.getParentColumn(type);
            if (parentColumn && data.parent_id) {
                data[parentColumn] = data.parent_id;
                delete data.parent_id;
            }
            
            // תיקון שמות types
            if (type === 'areaGrave') type = 'area_grave';


            // הוסף is_active
            data.is_active = 1;
     
            // אחרי השורה של area_grave, הוסף:
            if (type === 'customer') {
                url = `/dashboard/dashboards/cemeteries/api/customers-api.php?action=${action}`;
                if (isEdit) {
                    url += `&id=${formData.get('id')}`;
                }
            } else if (type === 'purchase') {
                url = `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=${action}`;
                if (isEdit) {
                    url += `&id=${formData.get('id')}`;
                }
            } else if (type === 'payment') {
                // טיפול בתשלומים
                if (isEdit) {
                    url = `/dashboard/dashboards/cemeteries/api/payments-api.php?action=update&id=${formData.get('id')}`;
                    method = 'PUT';
                } else {
                    url = '/dashboard/dashboards/cemeteries/api/payments-api.php?action=create';
                    method = 'POST';
                }
            } else {
                // הקוד הקיים - cemetery-hierarchy
                url = `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=${action}&type=${type}`;
                if (isEdit) {
                    url += `&id=${formData.get('id')}`;
                }
            }            
            
            if (isEdit) {
                url += `&id=${formData.get('id')}`;
            }
            
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
                
                // סגור את הטופס
                this.closeForm(type);
                
                // רענן את הנתונים
                if (typeof refreshData === 'function') {
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
    
    // קבלת עמודת ההורה לפי סוג
    getParentColumn: function(type) {
        const columns = {
            'block': 'cemetery_id',
            'plot': 'block_id',
            'row': 'plot_id',
            'area_grave': 'row_id',
            'grave': 'area_grave_id',
            'purchase': 'customer_id',
            'burial': 'grave_id'
        };
        return columns[type] || null;
    },
    
    // הצגת הודעה
    showMessage: function(message, type = 'info') {
        // בדוק אם יש פונקציה קיימת להודעות
        if (typeof showToast === 'function') {
            showToast(type, message);
        } else if (typeof showSuccess === 'function' && type === 'success') {
            showSuccess(message);
        } else if (typeof showError === 'function' && type === 'error') {
            showError(message);
        } else {
            // הודעה בסיסית
            alert(message);
        }
    }
};

const FormHandler = {
    // פתיחת טופס
    openForm: async function(type, parentId = null, itemId = null) {
        console.log('FormHandler.openForm called with:', {type, parentId, itemId});
        try {
            // טען את הטופס מהשרת
            const params = new URLSearchParams({
                type: type,
                ...(itemId && { id: itemId }),
                ...(parentId && { parent_id: parentId })
            });
            
            console.log('Fetching form from:', `/dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);
            
            const response = await fetch(`/dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);
            
            console.log('Response status:', response.status);
            
            const html = await response.text();
            
            console.log('Received HTML length:', html.length);
            
            // הסר טופס קיים אם יש
            const existingModal = document.getElementById(type + 'FormModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // הוסף את הטופס ל-DOM
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // חפש את המודאל בתוך ה-HTML שהתקבל
            const modalElement = tempDiv.querySelector('.modal');
            if (modalElement) {
                document.body.appendChild(modalElement);
                
                // הצג את המודאל
                modalElement.style.display = 'flex';
                modalElement.classList.add('show');
                
                // הוסף backdrop אם אין
                if (!document.querySelector('.modal-backdrop')) {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            } else {
                console.error('Modal element not found in received HTML');
                // אם אין modal element, פשוט הוסף את כל ה-HTML
                document.body.appendChild(tempDiv);
            }
            
        } catch (error) {
            console.error('Error loading form:', error);
            this.showMessage('שגיאה בטעינת הטופס', 'error');
        }
    },
    
    // סגירת טופס
    closeForm: function(type) {
        console.log('Closing form:', type);
        
        // נסה למצוא את המודאל לפי מספר דרכים
        let modal = document.getElementById(type + 'FormModal');
        if (!modal) {
            modal = document.querySelector('.modal.show');
        }
        
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
        
        // הסר backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    },
    
    // שמירת טופס
    saveForm: async function(formData, type) {
        try {
            // קבע את ה-API endpoint
            const isEdit = formData.has('id');
            const action = isEdit ? 'update' : 'create';
            
            // המר FormData לאובייקט
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'type') {
                    if (key === 'is_small_grave') {
                        data[key] = value === 'on' ? 1 : 0;
                    } else if (value !== '') {
                        data[key] = value;
                    }
                }
            }
            
            // טיפול בשדה parent_id - המרה לשם השדה הנכון
            if (data.parent_id) {
                const parentColumn = this.getParentColumn(type);
                if (parentColumn) {
                    data[parentColumn] = data.parent_id;
                    delete data.parent_id;
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
                
                // רענן את הנתונים
                if (typeof tableRenderer !== 'undefined' && tableRenderer.loadAndDisplay) {
                    tableRenderer.loadAndDisplay(window.currentType, window.currentParentId);
                } else if (typeof refreshData === 'function') {
                    refreshData();
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
    
    // קבלת עמודת ההורה לפי סוג
    getParentColumn: function(type) {
        const columns = {
            'block': 'cemeteryId',      // שינוי חשוב!
            'plot': 'blockId',           // שינוי חשוב!
            'row': 'plotId',             // שינוי חשוב!
            'area_grave': 'lineId',      // שינוי חשוב!
            'grave': 'areaGraveId',      // שינוי חשוב!
            'purchase': 'customerId',
            'burial': 'graveId'
        };
        return columns[type] || null;
    }
};

// הוסף את FormHandler לחלון הגלובלי
window.FormHandler = FormHandler;


// פונקציה גלובלית לטיפול בשליחת טופס
function handleFormSubmit(event, type) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    FormHandler.saveForm(formData, type);
}

// פונקציות גלובליות לתאימות אחורה
window.openFormModal = function(type, parentId, itemId) {
    FormHandler.openForm(type, parentId, itemId);
};

window.closeFormModal = function(type) {
    FormHandler.closeForm(type);
};

// אתחול בטעינת הדף
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
            if (modal) {
                const type = modal.id.replace('FormModal', '');
                FormHandler.closeForm(type);
            }
        }
    });
});