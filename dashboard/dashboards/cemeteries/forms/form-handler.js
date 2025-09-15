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

                // טיפול בטפסים ספציפיים
                if (type === 'customer') {
                    setTimeout(() => {
                        const fieldset = document.getElementById('address-fieldset');
                        if (fieldset && fieldset.dataset.cities) {
                            const citiesData = JSON.parse(fieldset.dataset.cities);
                            console.log('Cities data loaded:', citiesData.length);
                            
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
                            
                            // הוסף event listener
                            const countrySelect = document.getElementById('countrySelect');
                            if (countrySelect) {
                                countrySelect.addEventListener('change', window.filterCities);
                            }
                        }
                    }, 100);
                }

                // טען נתונים אם זה טופס עריכה של לקוח
                if (type === 'customer' && itemId) {

                    const loadCustomerData = () => {
                        const form = document.querySelector('#customerFormModal form');
                        console.log('Checking form readiness:', form ? 'Found' : 'Not found');
                        if (!form || !form.elements || form.elements.length < 5) {
                            console.log('Form not ready yet, elements count:', form?.elements?.length);
                            return false; // הטופס עדיין לא מוכן
                        }
                        
                        // הטופס מוכן, טען נתונים
                        console.log('Fetching customer data for ID:', itemId);
                        fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${itemId}`)
                            .then(response => {
                                console.log('Response status:', response.status);
                                return response.json();
                            })
                            .then(result => {
                                console.log('Customer data received:', result);
                                if (result.success && result.data) {
                                    const data = result.data;
                                    console.log('Starting to fill form with data:', data);
                                    
                                    // מלא את השדות
                                    Object.keys(data).forEach(key => {
                                        const field = form.elements[key];
                                        if (field) {
                                            console.log(`Setting field ${key} to value:`, data[key]);
                                            if (field.type === 'checkbox') {
                                                field.checked = data[key] == 1;
                                            } else if (field.type === 'select-one') {
                                                field.value = data[key] || '';
                                                // אם זה שדה המדינה, הפעל את פילטור הערים
                                                if (key === 'countryId' && window.filterCities) {
                                                    window.filterCities();
                                                    // המתן לטעינת הערים ואז בחר
                                                    const cityWatcher = setInterval(() => {
                                                        const cityField = form.elements['cityId'];
                                                        if (cityField && cityField.options.length > 1) {
                                                            clearInterval(cityWatcher);
                                                            if (data.cityId) {
                                                                cityField.value = data.cityId;
                                                            }
                                                        }
                                                    }, 50);
                                                    // הפסק אחרי 2 שניות למקרה הביטחון
                                                    setTimeout(() => clearInterval(cityWatcher), 2000);
                                                }
                                            } else {
                                                field.value = data[key] || '';
                                            }
                                        } else {
                                            console.log(`Field ${key} not found in form`);
                                        }
                                    });

                                    // Object.keys(data).forEach(key => {
                                    //     const field = form.elements[key];
                                    //     if (field) {
                                    //         console.log(`Setting field ${key} to value:`, data[key]);
                                    //         if (field.type === 'checkbox') {
                                    //             field.checked = data[key] == 1;
                                    //         } else if (field.type === 'select-one') {
                                    //             field.value = data[key] || '';
                                    //             // אם זה שדה המדינה, הפעל את פילטור הערים
                                    //             if (key === 'countryId' && window.filterCities) {
                                    //                 window.filterCities();
                                    //                 // המתן לטעינת הערים ואז בחר
                                    //                 const cityWatcher = setInterval(() => {
                                    //                     const cityField = form.elements['cityId'];
                                    //                     if (cityField && cityField.options.length > 1) {
                                    //                         clearInterval(cityWatcher);
                                    //                         if (data.cityId) {
                                    //                             cityField.value = data.cityId;
                                    //                         }
                                    //                     }
                                    //                 }, 50);
                                    //                 // הפסק אחרי 2 שניות למקרה הביטחון
                                    //                 setTimeout(() => clearInterval(cityWatcher), 2000);
                                    //             }
                                    //         } else {
                                    //             field.value = data[key] || '';
                                    //         }
                                    //     } else {
                                    //         console.log(`Field ${key} not found in form`);
                                    //     }
                                    // });
                                    
                                }
                            })
                            .catch(error => {
                                console.error('Error loading customer data:', error);
                            });
                        
                        return true; // הצלחנו לטעון
                    };
                    
                    // נסה לטעון מיד
                    if (!loadCustomerData()) {
                        // אם לא הצליח, השתמש ב-MutationObserver
                        const observer = new MutationObserver((mutations, obs) => {
                            if (loadCustomerData()) {
                                obs.disconnect(); // הפסק לצפות
                            }
                        });
                        
                        // התחל לצפות בשינויים
                        const modal = document.getElementById('customerFormModal');
                        if (modal) {
                            observer.observe(modal, {
                                childList: true,
                                subtree: true
                            });
                        }
                        
                        // הגבלת זמן של 5 שניות
                        setTimeout(() => observer.disconnect(), 5000);
                    }
                }
                
                // טען נתונים אם זה טופס עריכה
                if (itemId && type !== 'customer') {
                    // פונקציה שמנסה למלא את הטופס
                    const fillForm = () => {
                        const form = document.querySelector(`#${type}FormModal form`);
                        
                        // בדוק אם הטופס קיים ויש בו שדות
                        if (form && form.elements && form.elements.length > 2) {
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
                                        
                                        // וודא ש-unicId נשמר כשדה מוסתר
                                        if (result.data.unicId && !form.elements['unicId']) {
                                            const hiddenField = document.createElement('input');
                                            hiddenField.type = 'hidden';
                                            hiddenField.name = 'unicId';
                                            hiddenField.value = result.data.unicId;
                                            form.appendChild(hiddenField);
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading item data:', error);
                                });
                            return true; // הצלחנו
                        }
                        return false; // עדיין לא מוכן
                    };
                    
                    // נסה מיד
                    if (!fillForm()) {
                        // אם לא הצליח, נסה כל 100ms עד 10 פעמים
                        let attempts = 0;
                        const interval = setInterval(() => {
                            attempts++;
                            if (fillForm() || attempts >= 10) {
                                clearInterval(interval);
                            }
                        }, 100);
                    }
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
                // דלג על שדות שלא צריכים להישלח
                if (key === 'type' || key === 'id') {
                    continue;
                }
                
                if (key === 'is_small_grave' || key === 'isSmallGrave') {
                    data[key] = value === 'on' ? 1 : 0;
                } else if (value !== '') {
                    data[key] = value;
                }
            }

            // דיבוג - הצג את כל השדות מהטופס
            console.log('All form fields:', Array.from(formData.entries()));
            
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
                
                // רענן נתונים
                if (type === 'customer') {
                    // לקוחות - תמיד refreshData
                    if (typeof refreshData === 'function') {
                        refreshData();
                    }
                } else if (type === 'purchase' || type === 'payment') {
                    // רכישות ותשלומים - גם refreshData
                    if (typeof refreshData === 'function') {
                        refreshData();
                    }
                } else if (typeof tableRenderer !== 'undefined' && tableRenderer.loadAndDisplay) {
                    // היררכיית בתי עלמין
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
