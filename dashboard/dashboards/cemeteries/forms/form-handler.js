// /dashboards/cemeteries/forms/form-handler.js
// טיפול בטפסים בצד הלקוח

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

    // פונקציה עזר לחכות לפונקציה גלובלית
    waitForFunction: function(functionName, callback, timeout = 5000) {
        if (typeof window[functionName] === 'function') {
            callback();
            return;
        }
        
        const checkInterval = setInterval(() => {
            if (typeof window[functionName] === 'function') {
                clearInterval(checkInterval);
                callback();
            }
        }, 100);
        
        setTimeout(() => clearInterval(checkInterval), timeout);
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
            }
            
            // חפש את המודאל
            const modal = tempDiv.querySelector('#' + type + 'FormModal');
            
            if (modal) {
                // הוסף את המודאל ל-body
                document.body.appendChild(modal);
                
                // מנע גלילה בדף הראשי
                document.body.style.overflow = 'hidden';

                // טיפול לפי סוג הטופס
                this.handleFormSpecificLogic(type, parentId, itemId);
                
            } else {
                console.error('Modal not found in HTML');
                const alternativeModal = tempDiv.querySelector('.modal');
                if (alternativeModal) {
                    alternativeModal.id = type + 'FormModal';
                    document.body.appendChild(alternativeModal);
                    document.body.style.overflow = 'hidden';
                }
            }
            
        } catch (error) {
            console.error('Error loading form:', error);
            this.showMessage('שגיאה בטעינת הטופס', 'error');
        }
    },

    handleFormSpecificLogic: function(type, parentId, itemId) {
        switch(type) {
            case 'area_grave':
                this.handleAreaGraveForm(parentId);
                break;
                
            case 'customer':
                this.handleCustomerForm(itemId);
                break;
                
            case 'purchase':
                this.handlePurchaseForm(itemId);
                break;
                
            default:
                if (itemId) {
                    this.loadFormData(type, itemId);
                }
                break;
        }
    },

    handleAreaGraveForm: function(parentId) {
        if (!parentId) return;
        
        this.waitForElement('#area_graveFormModal select[name="lineId"]', (lineSelect) => {
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
                .catch(error => console.error('Error loading rows:', error));
        });
    },

    handleCustomerForm: function(itemId) {
        // טיפול בפילטור ערים
        this.waitForElement('#address-fieldset', (fieldset) => {
            if (fieldset.dataset.cities) {
                const citiesData = JSON.parse(fieldset.dataset.cities);
                
                window.filterCities = function() {
                    const countrySelect = document.getElementById('countrySelect');
                    const citySelect = document.getElementById('citySelect');
                    
                    if (!countrySelect || !citySelect) return;
                    
                    const selectedCountry = countrySelect.value;
                    citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
                    
                    if (!selectedCountry) {
                        citySelect.innerHTML = '<option value="">-- בחר קודם מדינה --</option>';
                        return;
                    }
                    
                    const filteredCities = citiesData.filter(city => city.countryId === selectedCountry);
                    
                    if (filteredCities.length === 0) {
                        citySelect.innerHTML = '<option value="">-- אין ערים למדינה זו --</option>';
                        return;
                    }
                    
                    filteredCities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.unicId;
                        option.textContent = city.cityNameHe;
                        citySelect.appendChild(option);
                    });
                };
                
                const countrySelect = document.getElementById('countrySelect');
                if (countrySelect) {
                    countrySelect.addEventListener('change', window.filterCities);
                }
            }
        });

        // טען נתונים אם זה עריכה
        if (itemId) {
            this.waitForElement('#customerFormModal form', (form) => {
                fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${itemId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const data = result.data;
                            Object.keys(data).forEach(key => {
                                const field = form.elements[key];
                                if (field) {
                                    if (field.type === 'checkbox') {
                                        field.checked = data[key] == 1;
                                    } else if (field.type === 'select-one') {
                                        field.value = data[key] || '';
                                        if (key === 'countryId' && window.filterCities) {
                                            window.filterCities();
                                            setTimeout(() => {
                                                if (data.cityId && form.elements['cityId']) {
                                                    form.elements['cityId'].value = data.cityId;
                                                }
                                            }, 300);
                                        }
                                    } else {
                                        field.value = data[key] || '';
                                    }
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Error loading customer data:', error));
            });
        }
    },

    handlePurchaseForm: function(itemId) {
        // אתחול הפונקציות לטופס חדש
        this.waitForFunction('populateBlocks', () => {
            if (window.hierarchyData) {
                window.populateBlocks();
                window.populatePlots();
                document.dispatchEvent(new Event('purchaseModalReady'));
            }
        });

        // טען נתונים אם זה עריכה
        if (itemId) {
            this.waitForElement('#purchaseFormModal form', (form) => {
                fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${itemId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const data = result.data;
                            Object.keys(data).forEach(key => {
                                const field = form.elements[key];
                                if (field) {
                                    field.value = data[key] || '';
                                }
                            });
                            
                            // טיפול מיוחד בבחירת קבר
                            if (data.graveId) {
                                // TODO: צריך לטעון את ההיררכיה הנכונה לפי הקבר שנבחר
                                console.log('Need to load hierarchy for grave:', data.graveId);
                            }
                        }
                    })
                    .catch(error => console.error('Error loading purchase data:', error));
            });
        }
    },

    loadFormData: function(type, itemId) {
        this.waitForElement(`#${type}FormModal form`, (form) => {
            fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=${type}&id=${itemId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        Object.keys(result.data).forEach(key => {
                            const field = form.elements[key];
                            if (field) {
                                if (field.type === 'checkbox') {
                                    field.checked = result.data[key] == 1;
                                } else {
                                    field.value = result.data[key] || '';
                                }
                            }
                        });
                        
                        if (result.data.unicId && !form.elements['unicId']) {
                            const hiddenField = document.createElement('input');
                            hiddenField.type = 'hidden';
                            hiddenField.name = 'unicId';
                            hiddenField.value = result.data.unicId;
                            form.appendChild(hiddenField);
                        }
                    }
                })
                .catch(error => console.error('Error loading item data:', error));
        });
    },
    
    closeForm: function(type) {
        console.log('Closing form:', type);
        
        const modal = document.getElementById(type + 'FormModal');
        if (modal) {
            modal.remove();
        }
        
        const style = document.getElementById(type + 'FormStyle');
        if (style) {
            style.remove();
        }
        
        document.body.style.overflow = '';
    },
    
    saveForm: async function(formData, type) {
        try {
            const isEdit = formData.has('id');
            const action = isEdit ? 'update' : 'create';
            
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key === 'type' || key === 'id') {
                    continue;
                }
                
                if (key === 'is_small_grave' || key === 'isSmallGrave') {
                    data[key] = value === 'on' ? 1 : 0;
                } else if (value !== '') {
                    data[key] = value;
                }
            }
            
            if (data.parent_id) {
                const parentColumn = this.getParentColumn(type);
                if (parentColumn) {
                    if (type === 'area_grave' && data.lineId) {
                        delete data.parent_id;
                    } else {
                        data[parentColumn] = data.parent_id;
                        delete data.parent_id;
                    }
                }
            }
            
            if (type === 'areaGrave') type = 'area_grave';
            
            data.isActive = 1;
            
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
                const unicId = formData.get('unicId');
                if (!unicId) {
                    this.showMessage('שגיאה: חסר מזהה ייחודי', 'error');
                    return false;
                }
                url += `&id=${unicId}`;
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
                
                if (typeof refreshData === 'function') {
                    refreshData();
                } else if (typeof tableRenderer !== 'undefined' && tableRenderer.loadAndDisplay) {
                    tableRenderer.loadAndDisplay(window.currentType, window.currentParentId);
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

// פונקציות גלובליות לתאימות אחורה
window.openFormModal = function(type, parentId, itemId) {
    FormHandler.openForm(type, parentId, itemId);
};

window.closeFormModal = function(type) {
    FormHandler.closeForm(type);
};