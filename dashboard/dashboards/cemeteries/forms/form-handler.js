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
                type: type,
                ...(itemId && { item_id: itemId }),
                ...(parentId && { parent_id: parentId })
            });

            const response = await fetch(`/dashboard/dashboards/cemeteries/forms/form-loader.php?${params}`);
     
            if (!response.ok) {
                return;
            }
        
            const html = await response.text();
            
            // בדוק מה יש ב-HTML
            if (html.includes('error') || html.includes('Error')) {
                console.log('⚠️ Error found in HTML');
                const errorMatch = html.match(/error[^<]*/gi);
                if (errorMatch) {
                    console.log('Error text found:', errorMatch);
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
                
                this.handleFormSpecificLogic(type, parentId, itemId);
                
            } else {
                console.error('❌ Modal not found in HTML');
   
                const allModals = tempDiv.querySelectorAll('.modal');
                console.log('Found modals:', allModals.length);
                allModals.forEach(m => {
                    console.log('Modal id:', m.id);
                });
            }
    
            
        } catch (error) {
            console.error('❌ Error in openForm:', error);
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

                case 'burial':
                    this.handleBurialForm(itemId);
                    break;

                case 'payment':  // הוסף את זה
                    this.handlePaymentForm(itemId);
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

        // הוסף כאן את חישוב התושבות - רק להוספת לקוח חדש
        if (!itemId) {  // רק אם זה לא עריכה
            this.waitForElement('#customerFormModal form', (form) => {
                // חישוב תושבות אוטומטי
                const typeSelect = form.elements['typeId'];
                const countrySelect = form.elements['countryId'];
                const citySelect = form.elements['cityId'];
                const residentField = form.elements['resident'];
                
                function calculateResidency() {
                    const typeId = typeSelect?.value;
                    const countryId = countrySelect?.value;
                    const cityId = citySelect?.value;
                    
                    console.log("Calculating residency:", {typeId, countryId, cityId});
                    
                    // אם סוג הזיהוי הוא דרכון (2) - תמיד תושב חו"ל
                    if (typeId == 2) {
                        updateResidencyField(3);
                        return;
                    }
                    
                    // אם אין מדינה - תושב חו"ל
                    if (!countryId) {
                        updateResidencyField(3);
                        return;
                    }
                    
                    // שלח לשרת לחישוב
                    fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=calculate_residency', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({typeId, countryId, cityId})
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
                        
                        // עדכון צבע רקע
                        const colors = {
                            1: '#e8f5e9', // ירוק - ירושלים
                            2: '#e3f2fd', // כחול - ישראל
                            3: '#fff3e0'  // כתום - חו"ל
                        };
                        residentField.style.backgroundColor = colors[value] || '#f5f5f5';
                    }
                }
                
                // הוסף מאזינים
                if (typeSelect) typeSelect.addEventListener('change', calculateResidency);
                if (countrySelect) countrySelect.addEventListener('change', calculateResidency);
                if (citySelect) citySelect.addEventListener('change', calculateResidency);
                
                // חישוב ראשוני
                calculateResidency();
            });
        }

        // טען נתונים אם זה עריכה
        if (itemId) {
            this.waitForElement('#customerFormModal form', (form) => {
                fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${itemId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            Object.keys(result.data).forEach(key => {
                                const field = form.elements[key];
                                if (field) {
                                    if (field.type === 'checkbox') {
                                        field.checked = result.data[key] == 1;
                                    } else if (field.type === 'select-one') {
                                        field.value = result.data[key] || '';

                                        // טיפול מיוחד בשדה תושבות
                                        if (key === 'resident' && field.disabled) {

                                            // עדכן גם אם השדה disabled
                                            field.value = result.data[key] || 3;
                                            
                                            // עדכן צבע רקע
                                            const colors = {
                                                '1': '#e8f5e9', // ירוק - ירושלים
                                                '2': '#e3f2fd', // כחול - ישראל
                                                '3': '#fff3e0'  // כתום - חו"ל
                                            };
                                            field.style.backgroundColor = colors[result.data[key]] || '#f5f5f5';
                                            
                                            // עדכן גם את השדה הנסתר
                                            const hiddenField = form.elements['resident_hidden'];
                                            if (hiddenField) {
                                                hiddenField.value = result.data[key] || 3;
                                            }
                                        }

                                        if (key === 'countryId' && window.filterCities) {
                                            window.filterCities();
                                            setTimeout(() => {
                                                if (result.data.cityId && form.elements['cityId']) {
                                                    form.elements['cityId'].value = result.data.cityId;
                                                }
                                            }, 50);
                                        }
                                    } else {
                                        field.value = result.data[key] || '';
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
        // הוסף את 2 השורות האלה
        window.formInitialized = false;


        // פונקציה מרכזית לחישוב תשלומים - תקרא משני המקומות
        window.tryCalculatePayments = async function() {
            // בדוק תנאים בסיסיים
            if (window.isEditMode) {
                console.log('Edit mode - skipping auto calculation');
                return;
            }
            
            if (!window.formInitialized) {
                console.log('Form not initialized yet - skipping');
                return;
            }
            
            // הכי חשוב - בדוק ששני השדות מלאים!
            if (!window.selectedGraveData || !window.selectedCustomerData) {
                console.log('Missing grave or customer data - skipping calculation');
                return;
            }
            
            // אם הגענו לכאן - יש לנו את כל מה שצריך!
            console.log('All conditions met - calculating payments...');
            
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
                    // נקה תשלומים קיימים
                    window.purchasePayments = [];
                    
                    // הוסף רק תשלומי חובה
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
                    
                    // עדכן תצוגה
                    if (window.displayPaymentsSummary) {
                        document.getElementById('paymentsDisplay').innerHTML = 
                            PaymentDisplayManager.render(window.purchasePayments, 'summary');
                    }
                    document.getElementById('total_price').value = PaymentDisplayManager.calculateTotal();
                    document.getElementById('paymentsList').value = JSON.stringify(window.purchasePayments);
                    
                    console.log('Payments calculated successfully');
                }
            } catch (error) {
                console.error('Error calculating payments:', error);
            }
        };

        
        this.waitForElement('#grave-selector-fieldset', (fieldset) => {
            // פונקציה לסינון ההיררכיה         
            if (fieldset.dataset.hierarchy) {
                window.hierarchyData = JSON.parse(fieldset.dataset.hierarchy);
            } else {
                return;
            }

            // אתחל את מנהל ההיררכיה - רק קברים פנויים (סטטוס 1)
            GraveHierarchyManager.init({
                allowedStatuses: [1], // רק פנויים לרכישות
                excludeGraveId: null,
                onGraveSelected: async function(graveId) {
                    if (graveId) {
                        // מצא את פרטי הקבר
                        const grave = window.hierarchyData.graves.find(g => g.unicId == graveId);
                        const areaGrave = window.hierarchyData.areaGraves.find(
                            ag => ag.unicId == grave.areaGraveId
                        );

                        if (grave) {
                            // עדכן את הפרמטרים לתשלומים החכמים
                            window.selectedGraveData = {
                                graveId: graveId,
                                plotType: grave.plotType || -1,
                                graveType: areaGrave.graveType || -1
                            };

                            // עדכן תצוגת פרמטרים
                            if (window.selectedCustomerData && window.updatePaymentParameters) {
                                window.updatePaymentParameters();
                            }

                            // נסה לחשב תשלומים (יקרה רק אם יש גם לקוח)
                            await window.tryCalculatePayments();
                        }
                    } else {
                        // אם ביטלו בחירת קבר - נקה הכל
                        window.selectedGraveData = null;
                        
                        if (!window.isEditMode) {
                            window.purchasePayments = [];
                            document.getElementById('total_price').value = '0.00';
                            document.getElementById('paymentsDisplay').innerHTML = '<p style="color: #999;">לא הוגדרו תשלומים</p>';
                            document.getElementById('paymentsList').value = '[]';
                        }
                        
                        const paramsElement = document.getElementById('selectedParameters');
                        if (paramsElement) {
                            paramsElement.style.display = 'none';
                        }
                    }
                }
            });

            // אחרי שה-fieldset נטען
            const customerSelect = document.querySelector('[name="clientId"]');
            if (customerSelect) {
                customerSelect.addEventListener('change', async function() {
                    const customerId = this.value;
                    if (customerId) {
                        try {
                            const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${customerId}`);
                            const data = await response.json();
                            if (data.success && data.data) {
                                window.selectedCustomerData = {
                                    id: customerId,
                                    resident: data.data.resident || 3,
                                    name: data.data.firstName + ' ' + data.data.lastName
                                };
                                
                                // עדכן תצוגת פרמטרים אם יש קבר נבחר
                                if (window.selectedGraveData && window.updatePaymentParameters) {
                                    window.updatePaymentParameters();
                                }
                                
                                // נסה לחשב תשלומים (יקרה רק אם יש גם קבר)
                                await window.tryCalculatePayments();
                            }
                        } catch (error) {
                            console.error('Error loading customer data:', error);
                        }
                    } else {
                        window.selectedCustomerData = null;
                        
                        // אם אין לקוח, נקה תשלומים גם אם יש קבר
                        if (!window.isEditMode) {
                            window.purchasePayments = [];
                            document.getElementById('total_price').value = '0.00';
                            document.getElementById('paymentsDisplay').innerHTML = '<p style="color: #999;">לא הוגדרו תשלומים</p>';
                            document.getElementById('paymentsList').value = '[]';
                        }
                    }
                });
            }

            // משתנים גלובליים לתשלומים
            window.purchasePayments = [];
            window.selectedGraveData = null;
            window.selectedCustomerData = null;

            window.updatePaymentParameters = function() {
                if (window.selectedGraveData) {
                    const plotTypes = {1: 'פטורה', 2: 'חריגה', 3: 'סגורה'};
                    const graveTypes = {1: 'שדה', 2: 'רוויה', 3: 'סנהדרין'};
                    const residentTypes = {1: 'ירושלים', 2: 'ישראל', 3: 'חו"ל'};

                    // קבע תושבות - מהלקוח או ברירת מחדל
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
            }

            // פתיחת מנהל תשלומים חכם
            window.openSmartPaymentsManager = async function() {

                // בדיקות ראשוניות
                const graveSelect = document.getElementById('graveSelect');
                const graveId = graveSelect ? graveSelect.value : null;
                
                if (!graveId || !window.selectedGraveData) {
                    alert('יש לבחור קבר תחילה');
                    return;
                }
                                
                if (!window.selectedCustomerData?.resident) {
                    alert('יש לבחור לקוח תחילה');
                    return;
                }

                // בדוק מצב עריכה
                const isEditMode = window.isEditMode === true;

                // השאר את הלוגיקה הקיימת אבל עם שינוי קטן
                if (isEditMode) {
                    // מצב עריכה - פתח ישירות את מנהל התשלומים הקיימים
                    console.log('Opening existing payments manager for editing');
                    ExistingPaymentsManager.open();
                    
                } else {
                    // מצב רכישה חדשה - טען תשלומים מה-API
                    try {
                        const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                plotType: window.selectedGraveData?.plotType,
                                graveType: window.selectedGraveData?.graveType,
                                resident: window.selectedCustomerData?.resident,
                                buyerStatus: document.querySelector('[name="buyer_status"]').value || null
                            })
                        });
                        
                        const data = await response.json();

                        if (data.success && data.payments) {
                            // תמיד פתח את המודל, גם אם אין תשלומים
                            SmartPaymentsManager.open(data.payments || []);
                        } else if (data.success && !data.payments) {
                            // אין תשלומים אבל הבקשה הצליחה - פתח מודל ריק
                            console.log('No payment definitions found, opening empty modal');
                            SmartPaymentsManager.open([]);
                        } else {
                            // רק אם יש שגיאה אמיתית
                            alert('שגיאה בטעינת הגדרות תשלום');
                            console.error('Error loading payments:', data);
                        }
                        
                    } catch (error) {
                        console.error('Error loading payments:', error);
                        alert('שגיאה בטעינת התשלומים');
                    }
                }
            }

            // מנהל תשלומים חכם לרכישה חדשה
            const SmartPaymentsManager = {
                // פתיחת המודל
                open: function(availablePayments) {
                    // חלק את התשלומים לחובה ואופציונלי
                    const mandatoryPayments = availablePayments.filter(p => p.mandatory);
                    const optionalPayments = availablePayments.filter(p => !p.mandatory);
                    
                    // יצירת המודל
                    const modal = document.createElement('div');
                    modal.id = 'smartPaymentsModal';
                    modal.className = 'modal-overlay';
                    modal.style.cssText = this.getModalStyle();
                    
                    // חשב סכום התחלתי
                    const currentTotal = this.calculateInitialTotal(mandatoryPayments);
                    
                    // בנה HTML
                    modal.innerHTML = this.buildModalHTML(mandatoryPayments, optionalPayments, currentTotal);
                    
                    // הוסף ל-DOM
                    document.body.appendChild(modal);
                },
                
                // סטיילים
                getModalStyle: function() {
                    return `
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
                },
                
                // חישוב סכום התחלתי
                calculateInitialTotal: function(mandatoryPayments) {
                    return mandatoryPayments.reduce((sum, p) => sum + parseFloat(p.price || 0), 0);
                },
                
                // בניית HTML
                buildModalHTML: function(mandatoryPayments, optionalPayments, currentTotal) {
                    return `
                        <div class="modal-content" style="${this.getContentStyle()}">
                            ${this.buildHeader()}
                            ${this.buildParametersSection()}
                            ${mandatoryPayments.length > 0 ? this.buildMandatorySection(mandatoryPayments) : ''}
                            ${this.buildOptionalSection(optionalPayments)}
                            ${this.buildSummarySection(currentTotal, mandatoryPayments.length)}
                            ${this.buildButtonsSection(mandatoryPayments)}
                        </div>
                    `;
                },
                
                // סטייל תוכן
                getContentStyle: function() {
                    return `
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        width: 700px;
                        max-height: 90vh;
                        overflow-y: auto;
                        margin: 20px;
                    `;
                },
                
                // כותרת
                buildHeader: function() {
                    return `
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;">חישוב תשלומים אוטומטי</h3>
                            <button onclick="SmartPaymentsManager.close()" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                            ">×</button>
                        </div>
                    `;
                },
                
                // סקציית פרמטרים
                buildParametersSection: function() {
                    const plotTypes = {1: 'פטורה', 2: 'חריגה', 3: 'סגורה'};
                    const graveTypes = {1: 'שדה', 2: 'רוויה', 3: 'סנהדרין'};
                    const residentTypes = {1: 'ירושלים', 2: 'ישראל', 3: 'חו"ל'};

                    // קבע תושבות - מהלקוח או ברירת מחדל
                    const residentValue = window.selectedCustomerData?.resident || 3;
                    const residentText = residentTypes[residentValue] || 'לא ידוע';
                    
                    return `
                        <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                            <strong>פרמטרים נבחרים:</strong><br>
                            סוג חלקה: ${plotTypes[window.selectedGraveData.plotType] || 'לא ידוע'} | 
                            סוג קבר: ${graveTypes[window.selectedGraveData.graveType] || 'לא ידוע'} | 
                            תושבות: ${residentText}
                        </div>
                    `;
                },
                
                // תשלומי חובה
                buildMandatorySection: function(payments) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #dc3545; margin-bottom: 10px;">
                                <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">חובה</span>
                                תשלומים הכרחיים
                            </h4>
                            <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                                ${payments.map(payment => this.buildMandatoryRow(payment)).join('')}
                            </div>
                        </div>
                    `;
                },
                
                // שורת תשלום חובה
                buildMandatoryRow: function(payment) {
                    return `
                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ffe5b4;">
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" checked disabled style="margin-left: 10px;">
                                <span style="font-weight: bold; margin-right: 10px;">${payment.name}</span>
                            </label>
                            <span style="font-weight: bold; color: #dc3545;">₪${parseFloat(payment.price).toLocaleString()}</span>
                        </div>
                    `;
                },
                
                // תשלומים אופציונליים
                buildOptionalSection: function(payments) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #28a745; margin-bottom: 10px;">
                                <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">אופציונלי</span>
                                תשלומים נוספים
                            </h4>
                            <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                                <div id="optionalPaymentsList">
                                    ${payments.map(payment => this.buildOptionalRow(payment)).join('')}
                                </div>
                                ${this.buildCustomPaymentSection()}
                            </div>
                        </div>
                    `;
                },
                
                // שורת תשלום אופציונלי
                buildOptionalRow: function(payment) {
                    return `
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
                    `;
                },
                
                // הוספת תשלום מותאם
                buildCustomPaymentSection: function() {
                    return `
                        <div style="border-top: 2px solid #28a745; margin-top: 15px; padding-top: 15px;">
                            <h5 style="margin-bottom: 10px;">הוסף תשלום מותאם:</h5>
                            <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 12px;">סיבת תשלום</label>
                                    <input type="text" id="customPaymentName" 
                                        list="paymentReasons"
                                        placeholder="בחר או הקלד סיבה" 
                                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <datalist id="paymentReasons">
                                        <option value="דמי רישום">
                                        <option value="עלויות ניהול">
                                        <option value="תחזוקה שנתית">
                                        <option value="שירותים נוספים">
                                        <option value="הובלה">
                                        <option value="טקס מיוחד">
                                    </datalist>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 12px;">סכום</label>
                                    <input type="number" id="customPaymentAmount" 
                                        step="0.01" min="0"
                                        placeholder="0.00" 
                                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <button onclick="SmartPaymentsManager.addCustomPayment()" style="
                                    padding: 8px 15px;
                                    background: #17a2b8;
                                    color: white;
                                    border: none;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    white-space: nowrap;
                                ">+ הוסף</button>
                            </div>
                        </div>
                    `;
                },
                
                // סיכום
                buildSummarySection: function(currentTotal, mandatoryCount) {
                    return `
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold;">
                                סה"כ לתשלום: ₪<span id="smartModalTotal">${currentTotal.toLocaleString()}</span>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                כולל ${mandatoryCount} תשלומי חובה
                                <span id="optionalCount"></span>
                            </div>
                        </div>
                    `;
                },
                
                // כפתורים
                buildButtonsSection: function(mandatoryPayments) {
                    return `
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
                    `;
                },
                
                // עדכון סכום
                updateTotal: function() {
                    let total = 0;
                    let optionalCount = 0;
                    
                    const modal = document.getElementById('smartPaymentsModal');
                    if (!modal) return;
                    
                    // סכום תשלומי חובה
                    const mandatoryCheckboxes = modal.querySelectorAll('input[type="checkbox"]:disabled:checked');
                    mandatoryCheckboxes.forEach(cb => {
                        // חפש את המחיר בתוך אותו div של הצ'קבוקס
                        const parentDiv = cb.closest('div[style*="padding"]');
                        if (parentDiv) {
                            // חפש את כל ה-spans בתוך ה-div
                            const spans = parentDiv.querySelectorAll('span');
                            // המחיר נמצא בדרך כלל ב-span האחרון
                            const priceSpan = spans[spans.length - 1];
                            if (priceSpan) {
                                const priceText = priceSpan.textContent;
                                // הסר סמל מטבע, פסיקים ורווחים
                                const cleanPrice = priceText.replace(/[₪,\s]/g, '');
                                const price = parseFloat(cleanPrice);
                                
                                console.log('Mandatory payment found:', priceText, '→', price); // דיבוג
                                
                                if (!isNaN(price)) {
                                    total += price;
                                }
                            }
                        }
                    });
                    
                    // סכום תשלומים אופציונליים שנבחרו
                    const optionalCheckboxes = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
                    optionalCheckboxes.forEach(cb => {
                        const price = parseFloat(cb.dataset.price);
                        
                        console.log('Optional payment:', cb.dataset.name, '→', price); // דיבוג
                        
                        if (!isNaN(price)) {
                            total += price;
                            optionalCount++;
                        }
                    });
                    
                    console.log('Total calculated:', total); // דיבוג
                    
                    // עדכן התצוגה
                    const totalElement = document.getElementById('smartModalTotal');
                    if (totalElement) {
                        totalElement.textContent = total.toLocaleString();
                    }
                    
                    const optionalCountElement = document.getElementById('optionalCount');
                    if (optionalCountElement) {
                        const optionalText = optionalCount > 0 ? ` + ${optionalCount} תשלומים נוספים` : '';
                        optionalCountElement.textContent = optionalText;
                    }
                },
                
                // הוספת תשלום מותאם
                addCustomPayment: function() {
                    const name = document.getElementById('customPaymentName').value.trim();
                    const amount = parseFloat(document.getElementById('customPaymentAmount').value);
                    
                    if (!name || !amount || amount <= 0) {
                        alert('יש למלא שם וסכום תקין');
                        return;
                    }
                    
                    // הוסף לרשימה
                    const optionalList = document.getElementById('optionalPaymentsList');
                    
                    const newPaymentHTML = `
                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb; background: #ffffcc;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" 
                                    checked
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
                    `;
                    
                    optionalList.insertAdjacentHTML('beforeend', newPaymentHTML);
                    
                    // נקה את השדות
                    document.getElementById('customPaymentName').value = '';
                    document.getElementById('customPaymentAmount').value = '';
                    
                    // עדכן סכום
                    this.updateTotal();
                },
     
                apply: function(mandatoryPaymentsJSON) {
                    // פענח את ה-JSON
                    let mandatoryPayments;
                    if (typeof mandatoryPaymentsJSON === 'string') {
                        try {
                            mandatoryPayments = JSON.parse(mandatoryPaymentsJSON.replace(/&quot;/g, '"'));
                        } catch (e) {
                            console.error('Error parsing mandatory payments:', e);
                            mandatoryPayments = [];
                        }
                    } else {
                        mandatoryPayments = mandatoryPaymentsJSON || [];
                    }
                    
                    // נקה תשלומים קיימים
                    window.purchasePayments = [];
                    
                    // הוסף תשלומי חובה - תקן את השדות!
                    mandatoryPayments.forEach(payment => {
                        window.purchasePayments.push({
                            locked: false,
                            required: true,
                            paymentDate: new Date().toISOString(),
                            paymentType: payment.priceDefinition || 1,
                            paymentAmount: parseFloat(payment.price),  // ⚠️ paymentAmount, לא amount
                            receiptDocuments: [],
                            customPaymentType: payment.name,
                            isPaymentComplete: false,
                            mandatory: true
                        });
                    });
                    
                    // הוסף תשלומים אופציונליים שנבחרו
                    const modal = document.getElementById('smartPaymentsModal');
                    if (modal) {
                        const selectedOptional = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
                        selectedOptional.forEach(cb => {
                            window.purchasePayments.push({
                                locked: false,
                                required: false,
                                paymentDate: new Date().toISOString(),
                                paymentType: cb.dataset.custom ? 5 : cb.dataset.definition,
                                paymentAmount: parseFloat(cb.dataset.price),  // ⚠️ paymentAmount, לא amount
                                receiptDocuments: [],
                                customPaymentType: cb.dataset.name,
                                isPaymentComplete: false,
                                mandatory: false,
                                custom: cb.dataset.custom === 'true'
                            });
                        });
                    }
                    
                    // עדכן תצוגה בטופס הראשי
                    document.getElementById('total_price').value = PaymentDisplayManager.calculateTotal();
                    document.getElementById('paymentsDisplay').innerHTML = 
                        PaymentDisplayManager.render(window.purchasePayments, 'summary');
                    document.getElementById('paymentsList').value = JSON.stringify(window.purchasePayments);
                    
                    // סגור מודל
                    this.close();
                },
                
                // סגירת המודל
                close: function() {
                    const modal = document.getElementById('smartPaymentsModal');
                    if (modal) modal.remove();
                }
            };

            // הגדרה גלובלית
            window.SmartPaymentsManager = SmartPaymentsManager;

            // מודול מלא לניהול תשלומים במצב עריכה
            const ExistingPaymentsManager = {
                // פתיחת המודל
                open: function() {
                    console.log('🔍 DEBUG: Opening existing payments manager');
                    console.log('Current payments:', window.purchasePayments);
                    
                    // אם אין תשלומים - צור מערך ריק
                    if (!window.purchasePayments) {
                        window.purchasePayments = [];
                        console.log('⚠️ No payments found, initialized empty array');
                    }
                    
                    // חלוקת תשלומים
                    const mandatoryPayments = window.purchasePayments.filter(p => 
                        p.mandatory === true || p.required === true
                    );
                    const editablePayments = window.purchasePayments.filter(p => 
                        p.mandatory !== true && p.required !== true
                    );
                    
                    // יצירת המודל
                    const modal = document.createElement('div');
                    modal.id = 'existingPaymentsModal';
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
                    
                    // חישוב סכום
                    let currentTotal = window.purchasePayments.reduce((sum, p) => {
                        const amount = Number(p.paymentAmount) || 0;
                        return sum + amount;
                    }, 0);
                    
                    console.log('💰 Total amount:', currentTotal);
                    
                    // בניית HTML
                    modal.innerHTML = this.buildModalHTML(mandatoryPayments, editablePayments, currentTotal);
                    
                    // הוספה ל-DOM
                    document.body.appendChild(modal);
                },
                
                // בניית HTML של המודל
                buildModalHTML: function(mandatoryPayments, editablePayments, currentTotal) {
                    return `
                        <div class="modal-content" style="
                            background: white;
                            padding: 30px;
                            border-radius: 8px;
                            width: 700px;
                            max-height: 90vh;
                            overflow-y: auto;
                            margin: 20px;
                            position: relative;
                        ">                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h3 style="margin: 0;">ניהול תשלומים קיימים</h3>
                                <button onclick="ExistingPaymentsManager.close()" style="
                                    background: none;
                                    border: none;
                                    font-size: 24px;
                                    cursor: pointer;
                                ">×</button>
                            </div>
                            
                            <!-- פרמטרים -->
                            <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                                <strong>פרטי הרכישה:</strong><br>
                                ${this.getParametersDisplay()}
                            </div>
                            
                            ${mandatoryPayments.length > 0 ? this.buildMandatorySection(mandatoryPayments) : ''}
                            
                            ${this.buildEditableSection(editablePayments)}
                            
                            <!-- סיכום -->
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold;">
                                    סה"כ: ₪<span id="existingModalTotal">${currentTotal.toLocaleString()}</span>
                                </div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                    ${mandatoryPayments.length} תשלומי חובה + 
                                    <span id="editableCount">${editablePayments.length}</span> תשלומים נוספים
                                </div>
                            </div>
                            
                            <!-- כפתורים -->
                            ${this.buildButtonsSection()}
                        </div>
                    `;
                },
                
                // הצגת פרמטרים
                getParametersDisplay: function() {
                    const plotTypes = {1: 'פטורה', 2: 'חריגה', 3: 'סגורה', '-1': 'לא מוגדר'};
                    const graveTypes = {1: 'שדה', 2: 'רוויה', 3: 'סנהדרין', '-1': 'לא מוגדר'};
                    const residentTypes = {1: 'ירושלים', 2: 'ישראל', 3: 'חו"ל'};

                    // קבע תושבות - מהלקוח או ברירת מחדל
                    const residentValue = window.selectedCustomerData?.resident || 3;
                    const residentText = residentTypes[residentValue] || 'לא ידוע';
                    
                    const plotType = window.selectedGraveData?.plotType || -1;
                    const graveType = window.selectedGraveData?.graveType || -1;
                    
                    return `סוג חלקה: ${plotTypes[plotType]} | סוג קבר: ${graveTypes[graveType]} | תושבות: ${residentText}`;
                },

                buildMandatorySection: function(payments) {
                    // השתמש בקונפיג הגלובלי
                    const paymentTypes = window.PAYMENT_TYPES_CONFIG || {};
                    
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #dc3545; margin-bottom: 10px;">
                                <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">נעול</span>
                                תשלומי חובה מקוריים
                            </h4>
                            <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                                ${payments.map(payment => {
                                    const displayName = payment.customPaymentType || 
                                                    (paymentTypes[payment.paymentType] && paymentTypes[payment.paymentType].name) || 
                                                    `תשלום מסוג ${payment.paymentType}`;
                                    return `
                                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ffe5b4;">
                                            <span style="font-weight: bold;">${displayName}</span>
                                            <div>
                                                <span style="font-weight: bold; color: #dc3545;">₪${Number(payment.paymentAmount).toLocaleString()}</span>
                                                <span style="margin-left: 10px; background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">🔒</span>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                },
                
                // בניית סקציית תשלומים לעריכה
                buildEditableSection: function(payments) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #28a745; margin-bottom: 10px;">
                                <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">ניתן לעריכה</span>
                                תשלומים נוספים
                            </h4>
                            <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                                <div id="editablePaymentsList">
                                    ${payments.length > 0 ? 
                                        payments.map((payment, index) => this.buildEditablePaymentRow(payment, index)).join('') :
                                        '<p style="text-align: center; color: #999; margin: 20px 0;">אין תשלומים נוספים - הוסף תשלום חדש למטה</p>'
                                    }
                                </div>
                                
                                <!-- הוספת תשלום חדש -->
                                <div style="border-top: 2px solid #28a745; margin-top: 15px; padding-top: 15px;">
                                    <h5 style="margin-bottom: 10px;">הוסף תשלום חדש:</h5>
                                    <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end;">
                                        <div>
                                            <input type="text" id="newPaymentName" 
                                                list="paymentReasons"
                                                placeholder="סיבת תשלום" 
                                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                            <datalist id="paymentReasons">
                                                <option value="דמי רישום">
                                                <option value="עלויות ניהול">
                                                <option value="תחזוקה שנתית">
                                                <option value="שירותים נוספים">
                                            </datalist>
                                        </div>
                                        <div>
                                            <input type="number" id="newPaymentAmount" 
                                                step="0.01" min="0"
                                                placeholder="סכום" 
                                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        <button onclick="ExistingPaymentsManager.addPayment()" style="
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
                    `;
                },

                buildEditablePaymentRow: function(payment, index) {
                    // השתמש בקונפיג הגלובלי
                    const paymentTypes = window.PAYMENT_TYPES_CONFIG || {};
                    
                    // חפש את השם בכל המקומות האפשריים
                    const displayName = payment.customPaymentType || 
                                    payment.type_name ||
                                    (paymentTypes[payment.paymentType] && paymentTypes[payment.paymentType].name) || 
                                    `תשלום מסוג ${payment.paymentType}`;
                    
                    return `
                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb;">
                            <input type="text" 
                                value="${displayName}"
                                onchange="ExistingPaymentsManager.updateName(${index}, this.value)"
                                style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px; margin-left: 10px;">
                            <input type="number" 
                                value="${payment.paymentAmount}"
                                step="0.01"
                                onchange="ExistingPaymentsManager.updateAmount(${index}, this.value)"
                                style="width: 120px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; margin-left: 10px;">
                            <button onclick="ExistingPaymentsManager.removePayment(${index})" style="
                                padding: 6px 12px;
                                background: #dc3545;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                                margin-right: 10px;
                            ">הסר</button>
                        </div>
                    `;
                },
                
                // כפתורים
                buildButtonsSection: function() {
                    return `
                        <div style="display: flex; gap: 10px; justify-content: space-between;">
                            <button onclick="ExistingPaymentsManager.recalculate()" style="
                                padding: 10px 20px;
                                background: #ff9800;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">🔄 חשב מחדש</button>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="ExistingPaymentsManager.close()" style="
                                    padding: 10px 30px;
                                    background: #6c757d;
                                    color: white;
                                    border: none;
                                    border-radius: 4px;
                                    cursor: pointer;
                                ">ביטול</button>
                                <button onclick="ExistingPaymentsManager.save()" style="
                                    padding: 10px 30px;
                                    background: #28a745;
                                    color: white;
                                    border: none;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    font-weight: bold;
                                ">שמור</button>
                            </div>
                        </div>
                    `;
                },

                updateName: function(index, value) {
                    const editablePayments = window.purchasePayments.filter(p => 
                        p.mandatory !== true && p.required !== true
                    );
                    if (editablePayments[index]) {
                        const paymentIndex = window.purchasePayments.indexOf(editablePayments[index]);
                        window.purchasePayments[paymentIndex].customPaymentType = value;
                    }
                },

                updateAmount: function(index, value) {
                    const editablePayments = window.purchasePayments.filter(p => 
                        p.mandatory !== true && p.required !== true
                    );
                    if (editablePayments[index]) {
                        const paymentIndex = window.purchasePayments.indexOf(editablePayments[index]);
                        window.purchasePayments[paymentIndex].paymentAmount = Number(value) || 0;
                        this.updateTotal();
                    }
                },

                updateTotal: function() {
                    const total = window.purchasePayments.reduce((sum, p) => sum + (Number(p.paymentAmount) || 0), 0);
                    const element = document.getElementById('existingModalTotal');
                    if (element) element.textContent = total.toLocaleString();
                },

                removePayment: function(index) {
                    // תקן את הפילטור
                    const editablePayments = window.purchasePayments.filter(p => 
                        p.mandatory !== true && p.required !== true
                    );
                    if (editablePayments[index]) {
                        const paymentIndex = window.purchasePayments.indexOf(editablePayments[index]);
                        window.purchasePayments.splice(paymentIndex, 1);
                        this.close();
                        this.open(); // רענן
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
                        paymentType: 5, // אחר
                        paymentAmount: amount,
                        receiptDocuments: [],
                        customPaymentType: name,
                        isPaymentComplete: false
                    });
                    
                    this.close();
                    this.open(); // רענן
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
                    document.getElementById('paymentsDisplay').innerHTML = 
                        PaymentDisplayManager.render(window.purchasePayments, 'summary');
                    document.getElementById('paymentsList').value = JSON.stringify(window.purchasePayments);
                    this.close();
                },
                
                close: function() {
                    const modal = document.getElementById('existingPaymentsModal');
                    if (modal) modal.remove();
                },
            };

            // הגדרה גלובלית
            window.ExistingPaymentsManager = ExistingPaymentsManager;

            // סמן שהטופס מוכן אחרי חצי שנייה
            setTimeout(() => {
                window.formInitialized = true;
            }, 500);
            // אתחל
            window.populateBlocks();
            window.populatePlots();
         });

        // טען נתונים אם זה עריכה
        if (itemId) {
            // סמן שזו עריכה - אסור לחשב מחדש!
            window.isEditMode = true;

            const loadPurchaseData = () => {
                const form = document.querySelector('#purchaseFormModal form');
                
                if (form && form.elements && form.elements.length > 5) {
                    fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${itemId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success && result.data) {
                                const data = result.data;
                               
                                // מלא שדות רגילים
                                Object.keys(data).forEach(key => {
                                    const field = form.elements[key];
                                    if (field && data[key] !== null) {
                                        field.value = data[key];
                                    }
                                });

                                // *** הוסף כאן - אחרי מילוי השדות ***
                                // טען גם נתוני לקוח
                                if (data.clientId) {
                                    fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${data.clientId}`)
                                        .then(response => response.json())
                                        .then(customerResult => {
                                            if (customerResult.success && customerResult.data) {
                                                window.selectedCustomerData = {
                                                    id: data.clientId,
                                                    resident: customerResult.data.resident || 3,
                                                    name: customerResult.data.firstName + ' ' + customerResult.data.lastName
                                                };
                                                
                                                // עדכן תצוגת פרמטרים
                                                if (window.updatePaymentParameters) {
                                                    window.updatePaymentParameters();
                                                }
                                            }
                                        })
                                        .catch(error => console.error('Error loading customer data:', error));
                                }

                                // טען תשלומים קיימים
                                if (data.paymentsList) {
                                    try {
                                        window.purchasePayments = JSON.parse(data.paymentsList);
                                        
                                        // הוסף את השדה mandatory מהקונפיג לכל תשלום
                                        window.purchasePayments.forEach(payment => {

                                            if (payment.paymentType && window.PAYMENT_TYPES_CONFIG) {
                                                const config = window.PAYMENT_TYPES_CONFIG[payment.paymentType];
                                                if (config) {
                                                    // הוסף את השדה mandatory מהקונפיג
                                                    payment.mandatory = config.mandatory || false;
                                                    // הוסף גם required לתאימות אחורה
                                                    payment.required = config.mandatory || false;
                                                }
                                            }
                                        });
                                        
                                        // עדכן תצוגה
                                        if (window.displayPaymentsSummary) {
                                            document.getElementById('paymentsDisplay').innerHTML = 
                                                PaymentDisplayManager.render(window.purchasePayments, 'summary');
                                        }
                                        
                                        // עדכן סכום
                                        document.getElementById('total_price').value = data.price || PaymentDisplayManager.calculateTotal();
                                        
                                        // שנה טקסט כפתור
                                        const btn = document.getElementById('paymentsButtonText');
                                        if (btn) {
                                            btn.textContent = 'ערוך תשלומים';
                                        }
                                    } catch(e) {
                                        console.error('Error parsing payments data:', e);
                                    }
                                }
                                
                                // אם יש קבר, מצא את ההיררכיה שלו
                                if (data.graveId && window.hierarchyData) {
                                    GraveHierarchyManager.init({
                                        allowedStatuses: [1], // רק פנויים
                                        excludeGraveId: data.graveId, // ← התעלם מהקבר הנוכחי!
                                        onGraveSelected: async function(graveId) {
                                            // אותה לוגיקה של onGraveSelected כמו למעלה
                                            if (graveId) {
                                                const grave = window.hierarchyData.graves.find(g => g.unicId == graveId);
                                                const areaGrave = window.hierarchyData.areaGraves.find(
                                                    ag => ag.unicId == grave.areaGraveId
                                                );
                                                
                                                if (grave) {
                                                    window.selectedGraveData = {
                                                        graveId: graveId,
                                                        plotType: grave.plotType || -1,
                                                        graveType: areaGrave.graveType || -1
                                                    };
                                                    
                                                    if (window.updatePaymentParameters) {
                                                        window.updatePaymentParameters();
                                                    }
                                                    
                                                    // במצב עריכה לא מחשבים מחדש תשלומים אוטומטית
                                                }
                                            } else {
                                                window.selectedGraveData = null;
                                                const paramsElement = document.getElementById('selectedParameters');
                                                if (paramsElement) {
                                                    paramsElement.style.display = 'none';
                                                }
                                            }
                                        }
                                    });

                                    // 1. מצא את הקבר
                                    const grave = window.hierarchyData.graves.find(g => g.unicId === data.graveId);
                                    if (!grave) return;
                                    // 2. מצא את אחוזת הקבר
                                    const areaGrave = window.hierarchyData.areaGraves.find(ag => ag.unicId === grave.area_grave_id);
                                    if (!areaGrave) return;
                                    // 3. מצא את השורה
                                    const row = window.hierarchyData.rows.find(r => r.unicId === areaGrave.row_id);
                                    if (!row) return;
                                    // 4. מצא את החלקה
                                    const plot = window.hierarchyData.plots.find(p => p.unicId === row.plot_id);
                                    if (!plot) return;
                                    // 5. מצא את הגוש
                                    const block = window.hierarchyData.blocks.find(b => b.unicId === plot.blockId);
                                    if (!block) return;
                                    

                                    // עכשיו תבחר את הערכים בסלקטים
                                    setTimeout(() => {
                                        
                                        // בחר בית עלמין
                                        if (block.cemetery_id) {
                                            document.getElementById('cemeterySelect').value = block.cemetery_id;
                                            window.filterHierarchy('cemetery');
                                        }
                                        
                                        // בחר גוש
                                        setTimeout(() => {
                                            document.getElementById('blockSelect').value = block.unicId;
                                            window.filterHierarchy('block');
                                            
                                            // בחר חלקה
                                            setTimeout(() => {
                                                document.getElementById('plotSelect').value = plot.unicId;
                                                window.filterHierarchy('plot');
                                                
                                                // בחר שורה
                                                setTimeout(() => {
                                                    document.getElementById('rowSelect').value = row.unicId;
                                                    window.filterHierarchy('row');
                                                    
                                                    // בחר אחוזת קבר
                                                    setTimeout(() => {
                                                        document.getElementById('areaGraveSelect').value = areaGrave.unicId;
                                                        window.filterHierarchy('area_grave');

                                                        // בחר קבר
                                                        setTimeout(() => {
                                                            document.getElementById('graveSelect').value = grave.unicId;
                                                            window.currentGraveId = data.graveId;
        
                                                            // הוסף את זה - הגדר את הנתונים לתשלומים
                                                            window.selectedGraveData = {
                                                                graveId: grave.unicId,
                                                                plotType: grave.plotType || -1,
                                                                graveType: areaGrave.graveType || -1
                                                            };
                                                            
                                                            // עדכן תצוגת פרמטרים
                                                            if (window.updatePaymentParameters) {
                                                                window.updatePaymentParameters();
                                                            }
                                                        }, 50);
                                                    }, 50);
                                                }, 50);
                                            }, 50);
                                        }, 50);
                                    }, 250);
                                }
                            }
                        })
                        .catch(error => {
                            console.error('ERROR loading purchase:', error.message);
                        });
                    
                    return true;
                }
                return false;
            };
            
            // נסה לטעון מיד
            if (!loadPurchaseData()) {
                // אם לא הצליח, השתמש ב-MutationObserver
                const observer = new MutationObserver((mutations, obs) => {
                    if (loadPurchaseData()) {
                        obs.disconnect(); // הפסק לצפות
                    }
                });
                
                // התחל לצפות בשינויים
                const modal = document.getElementById('purchaseFormModal');
                if (modal) {
                    observer.observe(modal, {
                        childList: true,
                        subtree: true
                    });
                }
                
                // הגבלת זמן של 10 שניות
                setTimeout(() => observer.disconnect(), 10000);
            }
        }
    },

    handleBurialForm: function(itemId) {
        this.waitForElement('#grave-selector-fieldset', (fieldset) => {
            // טען את נתוני ההיררכיה
            if (fieldset.dataset.hierarchy) {
                window.hierarchyData = JSON.parse(fieldset.dataset.hierarchy);
            } else {
                return;
            }

            // אתחל את מנהל ההיררכיה - קברים פנויים ונרכשים (סטטוס 1, 2)
            GraveHierarchyManager.init({
                allowedStatuses: [1, 2], // פנויים ונרכשים
                onGraveSelected: async function(graveId) {
                    await handleGraveSelection(graveId);
                }
            });

            // מאזין לשינויים בבחירת לקוח
            const customerSelect = document.querySelector('[name="clientId"]');
            if (customerSelect) {
                customerSelect.addEventListener('change', async function() {
                    await handleCustomerSelection(this.value);
                });
            }

            // אתחל
            window.populateBlocks();
            window.populatePlots();
        });
        
        // טען נתונים אם זו עריכה
        if (itemId) {
            window.isEditMode = true;
            loadBurialData(itemId);
        }
        
        // === פונקציות עזר לסינכרון דו-כיווני ===
        
        // טיפול בבחירת לקוח
        async function handleCustomerSelection(customerId) {
            if (!customerId) {
                clearGraveSelection();
                return;
            }
            
            try {
                // בדוק אם ללקוח יש רכישה פעילה
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=getByCustomer&customerId=${customerId}`);
                const data = await response.json();
                
                if (data.success && data.purchase) {
                    const purchase = data.purchase;
                    
                    // הצג הודעה למשתמש
                    showSyncNotification('info', 'נתוני הקבר התמלאו אוטומטית לפי הרכישה של הלקוח');
                    
                    // מלא אוטומטית את היררכיית הקבר
                    await fillGraveHierarchy(purchase.graveId);
                    
                    // עדכן את שדה הרכישה הקשורה אם קיים
                    const purchaseSelect = document.querySelector('[name="purchaseId"]');
                    if (purchaseSelect) {
                        purchaseSelect.value = purchase.unicId;
                    }
                } else {
                    // לקוח ללא רכישה - נקה בחירת קבר רק אם לא במצב עריכה
                    if (!window.isEditMode) {
                        clearGraveSelection();
                    }
                }
                
                // טען נתוני לקוח עבור שדות נוספים
                const customerResponse = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${customerId}`);
                const customerData = await customerResponse.json();
                if (customerData.success && customerData.data) {
                    window.selectedCustomerData = {
                        id: customerId,
                        name: customerData.data.firstName + ' ' + customerData.data.lastName,
                        statusCustomer: customerData.data.statusCustomer
                    };
                }
            } catch (error) {
                console.error('Error loading customer purchase data:', error);
            }
        }
        
        // טיפול בבחירת קבר
        async function handleGraveSelection(graveId) {
            if (!graveId) {
                window.selectedGraveData = null;
                hideGraveStatusNotification();
                return;
            }
            
            try {
                // מצא את פרטי הקבר
                const grave = window.hierarchyData.graves.find(g => g.unicId == graveId);
                if (grave) {
                    window.selectedGraveData = {
                        graveId: graveId,
                        graveStatus: grave.graveStatus
                    };
                    
                    // הצג סטטוס הקבר
                    if (grave.graveStatus == 2) {
                        showGraveStatusNotification('warning', 'שים לב: קבר זה נמצא בסטטוס נרכש');
                    } else if (grave.graveStatus == 1) {
                        showGraveStatusNotification('success', 'קבר פנוי');
                    }
                    
                    // בדוק אם לקבר יש רכישה פעילה
                    const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=getByGrave&graveId=${graveId}`);
                    const data = await response.json();
                    
                    if (data.success && data.purchase) {
                        const purchase = data.purchase;
                        
                        // הצג הודעה למשתמש
                        showSyncNotification('info', 'נתוני הלקוח התמלאו אוטומטית לפי הרכישה של הקבר');
                        
                        // מלא אוטומטית את נתוני הלקוח
                        const customerSelect = document.querySelector('[name="clientId"]');
                        if (customerSelect) {
                            customerSelect.value = purchase.clientId;
                            
                            // עדכן נתוני הלקוח הנבחר
                            window.selectedCustomerData = {
                                id: purchase.clientId,
                                name: purchase.customerName
                            };
                        }
                        
                        // עדכן את שדה הרכישה הקשורה אם קיים
                        const purchaseSelect = document.querySelector('[name="purchaseId"]');
                        if (purchaseSelect) {
                            purchaseSelect.value = purchase.unicId;
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading grave purchase data:', error);
            }
        }
        
        // מילוי היררכיית הקבר לפי graveId
        async function fillGraveHierarchy(graveId) {
            if (!window.hierarchyData || !graveId) return;
            
            // מצא את הקבר
            const grave = window.hierarchyData.graves.find(g => g.unicId === graveId);
            if (!grave) return;
            
            // מצא את אחוזת הקבר
            const areaGrave = window.hierarchyData.areaGraves.find(ag => ag.unicId === grave.area_grave_id);
            if (!areaGrave) return;
            
            // מצא את השורה
            const row = window.hierarchyData.rows.find(r => r.unicId === areaGrave.row_id);
            if (!row) return;
            
            // מצא את החלקה
            const plot = window.hierarchyData.plots.find(p => p.unicId === row.plot_id);
            if (!plot) return;
            
            // מצא את הגוש
            const block = window.hierarchyData.blocks.find(b => b.unicId === plot.blockId);
            if (!block) return;
            
            // מלא את הסלקטים בסדר היררכי עם השהיות
            setTimeout(() => {
                document.getElementById('cemeterySelect').value = block.cemetery_id;
                window.filterHierarchy('cemetery');
                
                setTimeout(() => {
                    document.getElementById('blockSelect').value = block.unicId;
                    window.filterHierarchy('block');
                    
                    setTimeout(() => {
                        document.getElementById('plotSelect').value = plot.unicId;
                        window.filterHierarchy('plot');
                        
                        setTimeout(() => {
                            document.getElementById('rowSelect').value = row.unicId;
                            window.filterHierarchy('row');
                            
                            setTimeout(() => {
                                document.getElementById('areaGraveSelect').value = areaGrave.unicId;
                                window.filterHierarchy('area_grave');
                                
                                setTimeout(() => {
                                    document.getElementById('graveSelect').value = grave.unicId;
                                }, 50);
                            }, 50);
                        }, 50);
                    }, 50);
                }, 50);
            }, 100);
        }
        
        // ניקוי בחירת קבר
        function clearGraveSelection() {
            document.getElementById('cemeterySelect').value = '';
            document.getElementById('blockSelect').value = '';
            document.getElementById('plotSelect').value = '';
            document.getElementById('rowSelect').value = '';
            document.getElementById('areaGraveSelect').value = '';
            document.getElementById('graveSelect').value = '';
            
            // נקה את מצב הבחירה
            window.selectedGraveData = null;
            hideGraveStatusNotification();
        }
        
        // הצגת הודעות סינכרון
        function showSyncNotification(type, message) {
            const notificationId = 'syncNotification';
            let notification = document.getElementById(notificationId);
            
            if (!notification) {
                notification = document.createElement('div');
                notification.id = notificationId;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 300px;
                    padding: 12px 16px;
                    border-radius: 6px;
                    color: white;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                `;
                document.body.appendChild(notification);
            }
            
            // עיצוב לפי סוג
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
            
            // הסתר אחרי 4 שניות
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            }, 4000);
        }
        
        // הצגת סטטוס קבר
        function showGraveStatusNotification(type, message) {
            let statusDiv = document.getElementById('graveStatusNotification');
            
            if (!statusDiv) {
                statusDiv = document.createElement('div');
                statusDiv.id = 'graveStatusNotification';
                statusDiv.style.cssText = 'margin-top: 10px; border-radius: 5px; padding: 10px;';
                
                const graveFieldset = document.getElementById('grave-selector-fieldset');
                if (graveFieldset) {
                    graveFieldset.appendChild(statusDiv);
                }
            }
            
            const colors = {
                'success': '#d1fae5',
                'warning': '#fef3c7',
                'error': '#fecaca'
            };
            
            const textColors = {
                'success': '#065f46',
                'warning': '#92400e',
                'error': '#991b1b'
            };
            
            statusDiv.style.backgroundColor = colors[type] || colors['info'];
            statusDiv.style.color = textColors[type] || textColors['info'];
            statusDiv.innerHTML = `<strong>${type === 'success' ? '✅' : type === 'warning' ? '⚠️' : '❌'}</strong> ${message}`;
            statusDiv.style.display = 'block';
        }
        
        // הסתרת סטטוס קבר
        function hideGraveStatusNotification() {
            const statusDiv = document.getElementById('graveStatusNotification');
            if (statusDiv) {
                statusDiv.style.display = 'none';
            }
        }
        
        // טעינת נתוני קבורה לעריכה
        function loadBurialData(itemId) {
            const loadData = () => {
                const form = document.querySelector('#burialFormModal form');
                
                if (form && form.elements && form.elements.length > 5) {
                    fetch(`/dashboard/dashboards/cemeteries/api/burials-api.php?action=get&id=${itemId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success && result.data) {
                                const data = result.data;
                            
                                // מלא שדות רגילים
                                Object.keys(data).forEach(key => {
                                    const field = form.elements[key];
                                    if (field && data[key] !== null) {
                                        field.value = data[key];
                                    }
                                });

                                // טען נתוני לקוח
                                if (data.clientId) {
                                    handleCustomerSelection(data.clientId);
                                }
                                
                                // אם יש קבר, מצא את ההיררכיה שלו
                                if (data.graveId && window.hierarchyData) {
                                    setTimeout(() => {
                                        fillGraveHierarchy(data.graveId).then(() => {
                                            // עדכן נתוני הקבר הנבחר
                                            const grave = window.hierarchyData.graves.find(g => g.unicId === data.graveId);
                                            if (grave) {
                                                window.selectedGraveData = {
                                                    graveId: data.graveId,
                                                    graveStatus: grave.graveStatus
                                                };
                                            }
                                        });
                                    }, 500);
                                }
                            }
                        })
                        .catch(error => console.error('Error loading burial data:', error));
                    return true;
                }
                return false;
            };
            
            if (!loadData()) {
                const observer = new MutationObserver((mutations, obs) => {
                    if (loadData()) {
                        obs.disconnect();
                    }
                });
                
                const modal = document.getElementById('burialFormModal');
                if (modal) {
                    observer.observe(modal, {
                        childList: true,
                        subtree: true
                    });
                }
                
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

        if (type === 'purchase') {
            window.isEditMode = false;
            window.purchasePayments = [];
            window.selectedGraveData = null;
            console.log('✨ Cleared purchase form globals');
        }
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
            } else if (type === 'burial') {
                url = `/dashboard/dashboards/cemeteries/api/burials-api.php?action=${action}`;
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
        // options = {
        //     allowedStatuses: [1, 2], // אילו סטטוסים מותרים
        //     excludeGraveId: 'abc123', // קבר להתעלם ממנו (בעריכה)
        //     onGraveSelected: function(graveId) { ... } // מה לעשות כשנבחר קבר
        // }
        
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
        window.filterHierarchy2 = function(level) {
            const cemetery = document.getElementById('cemeterySelect').value;
            const block = document.getElementById('blockSelect').value;
            const plot = document.getElementById('plotSelect').value;
            const row = document.getElementById('rowSelect').value;
            const areaGrave = document.getElementById('areaGraveSelect').value;
            
            switch(level) {
                case 'cemetery':
                    populateBlocks(cemetery);
                    populatePlots(cemetery, null);
                    clearSelectors(['row', 'area_grave', 'grave']);
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
                    clearSelectors(['row', 'area_grave', 'grave']);
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
                        clearSelectors(['row', 'area_grave', 'grave']);
                        document.getElementById('rowSelect').disabled = true;
                    }
                    break;
                    
                case 'row':
                    if (row) {
                        populateAreaGraves(row);
                        document.getElementById('areaGraveSelect').disabled = false;
                    } else {
                        clearSelectors(['area_grave', 'grave']);
                        document.getElementById('areaGraveSelect').disabled = true;
                    }
                    break;
                    
                case 'area_grave':
                    if (areaGrave) {
                        populateGraves(areaGrave);
                        document.getElementById('graveSelect').disabled = false;
                    } else {
                        clearSelectors(['grave']);
                        document.getElementById('graveSelect').disabled = true;
                    }
                    break;
                    
                case 'grave':
                    // כשנבחר קבר - קרא ל-callback
                    const graveId = document.getElementById('graveSelect').value;
                    if (graveId && self.onGraveSelected) {
                        self.onGraveSelected(graveId);
                    }
                    break;
            }
        };

        // תיקון לפונקציה filterHierarchy ב-GraveHierarchyManager
        // החלף את הפונקציה filterHierarchy עם הגרסה המתוקנת:

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
                    clearSelectors(['row', 'area_grave', 'grave']);
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
                    clearSelectors(['row', 'area_grave', 'grave']);
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
                        clearSelectors(['row', 'area_grave', 'grave']);
                        document.getElementById('rowSelect').disabled = true;
                    }
                    // נקה גם את נתוני הלקוח והקבר
                    clearCustomerAndGraveData();
                    break;
                    
                case 'row':
                    if (row) {
                        populateAreaGraves(row);
                        document.getElementById('areaGraveSelect').disabled = false;
                    } else {
                        clearSelectors(['area_grave', 'grave']);
                        document.getElementById('areaGraveSelect').disabled = true;
                    }
                    // נקה גם את נתוני הלקוח והקבר כי אין קבר נבחר
                    clearCustomerAndGraveData();
                    break;
                    
                case 'area_grave':
                    if (areaGrave) {
                        populateGraves(areaGrave);
                        document.getElementById('graveSelect').disabled = false;
                    } else {
                        clearSelectors(['grave']);
                        document.getElementById('graveSelect').disabled = true;
                        // נקה גם את נתוני הלקוח והקבר כי אין קבר נבחר
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
                'area_grave': { id: 'areaGraveSelect', default: '-- בחר שורה תחילה --', disabled: true },
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