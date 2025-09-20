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
        }, 50);
        
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
         
        if (type === 'purchase' && !itemId) {
            window.isEditMode = false;
            window.purchasePayments = [];
            window.selectedGraveData = null;
            console.log('🆕 Opening NEW purchase form - cleared globals');
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
                console.log("case 'customer': ", itemId);
                
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
                            Object.keys(result.data).forEach(key => {
                                const field = form.elements[key];
                                if (field) {
                                    if (field.type === 'checkbox') {
                                        field.checked = result.data[key] == 1;
                                    } else if (field.type === 'select-one') {
                                        field.value = result.data[key] || '';
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
        // בדיוק כמו לקוח - חכה ל-fieldset עם הנתונים
        this.waitForElement('#grave-selector-fieldset', (fieldset) => {
            // פונקציה לסינון ההיררכיה         
            if (fieldset.dataset.hierarchy) {
                window.hierarchyData = JSON.parse(fieldset.dataset.hierarchy);
            } else {
                alert('No hierarchy data found in fieldset!');
                return;
            }
            
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
                }
            }

            // מילוי גושים
            window.populateBlocks = function(cemeteryId = null) {

                const blockSelect = document.getElementById('blockSelect');
                if (!blockSelect) return;
                
                blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
                
                const blocks = cemeteryId 
                    ? window.hierarchyData.blocks.filter(b => b.cemeteryId == cemeteryId)
                    : window.hierarchyData.blocks;


                blocks.forEach(block => {
                    const hasAvailableGraves = checkBlockHasGraves(block.unicId);
                    const option = document.createElement('option');
                    option.value = block.unicId;
                    option.textContent = block.blockNameHe + (!hasAvailableGraves ? ' (אין קברים פנויים)' : '');

                    
                    if (!hasAvailableGraves) {
                        option.disabled = true;
                        option.style.color = '#999';
                    }
                    
                    blockSelect.appendChild(option);
                });
            }

            // מילוי חלקות
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
                    const hasAvailableGraves = checkPlotHasGraves(plot.unicId);
                    const option = document.createElement('option');
                    option.value = plot.unicId;
                    option.textContent = plot.name + (!hasAvailableGraves ? ' (אין קברים פנויים)' : '');
                    
                    if (!hasAvailableGraves) {
                        option.disabled = true;
                        option.style.color = '#999';
                    }
                    
                    plotSelect.appendChild(option);
                });
            }

            // בדיקת קברים פנויים בגוש
            window.checkBlockHasGraves = function(blockId) {
                const blockPlots = window.hierarchyData.plots.filter(p => p.blockId == blockId);

                for (let plot of blockPlots) {
                    if (checkPlotHasGraves(plot.unicId)) {
                        return true;
                    }
                }
                return false;
            }

            // בדיקת קברים פנויים בחלקה
            window.checkPlotHasGraves = function(plotId) {
                const plotRows = window.hierarchyData.rows.filter(r => r.plotId == plotId);
                
                for (let row of plotRows) {
                    const rowAreaGraves = window.hierarchyData.areaGraves.filter(ag => ag.lineId == row.unicId);
                    
                    for (let areaGrave of rowAreaGraves) {
                        const graves = window.hierarchyData.graves.filter(g => g.areaGraveId == areaGrave.unicId);
                        if (graves.length > 0) {
                            return true;
                        }
                    }
                }
                return false;
            }

            // מילוי שורות
            window.populateRows = function(plotId) {
                const rowSelect = document.getElementById('rowSelect');
                if (!rowSelect) return;
                
                rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
                
                const rows = window.hierarchyData.rows.filter(r => r.plot_id == plotId);
                
                rows.forEach(row => {
                    const hasAvailableGraves = checkRowHasGraves(row.unicId);
                    
                    if (hasAvailableGraves) {
                        const option = document.createElement('option');
                        option.value = row.unicId;
                        option.textContent = row.name;
                        rowSelect.appendChild(option);
                    }
                });
                
                if (rowSelect.options.length === 1) {
                    rowSelect.innerHTML = '<option value="">-- אין שורות עם קברים פנויים --</option>';
                }
            }

            // בדיקת קברים פנויים בשורה
            window.checkRowHasGraves = function(rowId) {
                const rowAreaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
                
                for (let areaGrave of rowAreaGraves) {
                    const graves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGrave.unicId);
                    if (graves.length > 0) {
                        return true;
                    }
                }
                return false;
            }

            // מילוי אחוזות קבר
            window.populateAreaGraves = function(rowId) {
                const areaGraveSelect = document.getElementById('areaGraveSelect');
                if (!areaGraveSelect) return;
                
                areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
                
                const areaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
                
                areaGraves.forEach(areaGrave => {
                    const availableGraves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGrave.unicId);
                    
                    if (availableGraves.length > 0) {
                        const option = document.createElement('option');
                        option.value = areaGrave.unicId;
                        option.textContent = areaGrave.name + ` (${availableGraves.length} קברים פנויים)`;
                        areaGraveSelect.appendChild(option);
                    }
                });
                
                if (areaGraveSelect.options.length === 1) {
                    areaGraveSelect.innerHTML = '<option value="">-- אין אחוזות קבר פנויות --</option>';
                }
            }

            // מילוי קברים
            window.populateGraves = function(areaGraveId) {
                const graveSelect = document.getElementById('graveSelect');
                if (!graveSelect) return;
                
                graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
                
                const graves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGraveId);
                
                graves.forEach(grave => {
                    const option = document.createElement('option');
                    option.value = grave.unicId;
                    option.textContent = `קבר ${grave.graveNameHe}`;
                    graveSelect.appendChild(option);
                });
            }

            // ניקוי בוררים
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
            }

            // משתנים גלובליים לתשלומים
            window.purchasePayments = [];
            window.selectedGraveData = null;

            // כשנבחר קבר
            window.onGraveSelected = async function(graveId) {
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
                        if (window.updatePaymentParameters) {
                            window.updatePaymentParameters();
                        }

                        // אם לא במצב עריכה - חשב תשלומים אוטומטית
                        if (!window.isEditMode) {
                            try {
                                const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        plotType: window.selectedGraveData.plotType,
                                        graveType: window.selectedGraveData.graveType,
                                        resident: 1,
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
                                            isPaymentComplete: false
                                        });
                                    });
                                    
                                    // עדכן תצוגה
                                    if (window.displayPaymentsSummary) {
                                        document.getElementById('paymentsDisplay').innerHTML = window.displayPaymentsSummary();
                                    }
                                    document.getElementById('total_price').value = window.calculatePaymentsTotal();
                                    document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
                                }
                            } catch (error) {
                                console.error('Error calculating payments:', error);
                            }
                        }
                    }
                } else {
                    // אם ביטלו בחירת קבר - נקה הכל
                    window.selectedGraveData = null;
                    
                    if (!window.isEditMode) {
                        window.purchasePayments = [];
                        document.getElementById('total_price').value = '0.00';
                        document.getElementById('paymentsDisplay').innerHTML = '<p style="color: #999;">לא הוגדרו תשלומים</p>';
                        document.getElementById('payments_data').value = '[]';
                    }
                    
                    const paramsElement = document.getElementById('selectedParameters');
                    if (paramsElement) {
                        paramsElement.style.display = 'none';
                    }
                }
            }

            window.updatePaymentParameters = function() {
                if (window.selectedGraveData) {
                    const plotTypes = {1: 'פטורה', 2: 'חריגה', 3: 'סגורה'};
                    const graveTypes = {1: 'שדה', 2: 'רוויה', 3: 'סנהדרין'};
                    
                    const displayElement = document.getElementById('parametersDisplay');
                    if (displayElement) {
                        displayElement.innerHTML = `
                            <span style="margin-right: 10px;">📍 חלקה: ${plotTypes[window.selectedGraveData.plotType] || 'לא ידוע'}</span>
                            <span style="margin-right: 10px;">⚰️ סוג קבר: ${graveTypes[window.selectedGraveData.graveType] || 'לא ידוע'}</span>
                            <span>👤 תושב: ירושלים</span>
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

                // בדוק מצב עריכה
                const isEditMode = window.isEditMode === true;

                // 👈 הוסף את הדיבוג המורחב
                console.log('=== Payment Manager Debug ===');
                console.log('Mode:', isEditMode ? 'EDIT' : 'NEW');
                console.log('isEditMode value:', window.isEditMode);
                console.log('Existing payments count:', window.purchasePayments?.length || 0);
                console.log('============================');

                // השאר את הלוגיקה הקיימת אבל עם שינוי קטן
                // if (isEditMode && window.purchasePayments && window.purchasePayments.length > 0) {
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
                                plotType: window.selectedGraveData.plotType,
                                graveType: window.selectedGraveData.graveType,
                                resident: 1,
                                buyerStatus: document.querySelector('[name="buyer_status"]').value || null
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success && data.payments && data.payments.length > 0) {
                            console.log('Found', data.payments.length, 'payment definitions');
                            showSmartPaymentsModal(data.payments);
                        } else {
                            alert('לא נמצאו הגדרות תשלום מתאימות לפרמטרים שנבחרו');
                            console.error('No matching payments found for parameters:', {
                                plotType: window.selectedGraveData.plotType,
                                graveType: window.selectedGraveData.graveType
                            });
                        }
                        
                    } catch (error) {
                        console.error('Error loading payments:', error);
                        alert('שגיאה בטעינת התשלומים');
                    }
                }
            }

            // עדכון הסכום הכולל במודל החכם - גרסה מתוקנת
            window.updateSmartTotal = function() {
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
            }

            // החלת התשלומים שנבחרו - הגדר כפונקציה גלובלית
            window.applySmartPayments = function(mandatoryPaymentsJSON) {
                // פענח את ה-JSON אם צריך
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
                
                // הוסף תשלומי חובה
                mandatoryPayments.forEach(payment => {
                    window.purchasePayments.push({
                        type: 'auto_' + payment.priceDefinition,
                        type_name: payment.name,
                        amount: parseFloat(payment.price),
                        mandatory: true,
                        date: new Date().toISOString()
                    });
                });
                
                // הוסף תשלומים אופציונליים שנבחרו
                const modal = document.getElementById('smartPaymentsModal');
                if (modal) {
                    const selectedOptional = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
                    selectedOptional.forEach(cb => {
                        window.purchasePayments.push({
                            type: cb.dataset.custom ? 'custom' : 'auto_' + cb.dataset.definition,
                            type_name: cb.dataset.name,
                            amount: parseFloat(cb.dataset.price),
                            mandatory: false,
                            custom: cb.dataset.custom === 'true',
                            date: new Date().toISOString()
                        });
                    });
                }
                
                // עדכן תצוגה בטופס הראשי
                document.getElementById('total_price').value = calculatePaymentsTotal();
                document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
                document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
                
                // סגור מודל
                if (modal) {
                    modal.remove();
                }
                
                // הודעה
                const total = window.purchasePayments.reduce((sum, p) => sum + p.amount, 0);
            }

            // מודול תשלומים ליצירת רכישה
            function showSmartPaymentsModal(availablePayments) {
                // חלק את התשלומים לחובה ואופציונלי
                const mandatoryPayments = availablePayments.filter(p => p.mandatory);
                const optionalPayments = availablePayments.filter(p => !p.mandatory);
                
                // יצירת המודל
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
                
                // חשב סכום התחלתי (רק תשלומי חובה)
                let currentTotal = mandatoryPayments.reduce((sum, p) => sum + parseFloat(p.price || 0), 0);
                
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
                            <button onclick="closeSmartPaymentsModal()" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                            ">×</button>
                        </div>
                        
                        <!-- הצגת הפרמטרים -->
                        <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                            <strong>פרמטרים נבחרים:</strong><br>
                            סוג חלקה: ${window.selectedGraveData.plotType == 1 ? 'פטורה' : window.selectedGraveData.plotType == 2 ? 'חריגה' : 'סגורה'} | 
                            סוג קבר: ${window.selectedGraveData.graveType == 1 ? 'שדה' : window.selectedGraveData.graveType == 2 ? 'רוויה' : 'סנהדרין'} | 
                            תושבות: ירושלים
                        </div>
                        
                        ${mandatoryPayments.length > 0 ? `
                            <!-- תשלומי חובה -->
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
                        
                        <!-- תשלומים אופציונליים כולל הוספה מותאמת -->
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #28a745; margin-bottom: 10px;">
                                <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">אופציונלי</span>
                                תשלומים נוספים
                            </h4>
                            <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                                <div id="optionalPaymentsList">
                                    ${optionalPayments.map((payment, index) => `
                                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb;">
                                            <label style="display: flex; align-items: center; cursor: pointer;">
                                                <input type="checkbox" 
                                                    data-price="${payment.price}"
                                                    data-name="${payment.name}"
                                                    data-definition="${payment.priceDefinition}"
                                                    onchange="updateSmartTotal()"
                                                    style="margin-left: 10px;">
                                                <span style="margin-right: 10px;">${payment.name}</span>
                                            </label>
                                            <span>₪${parseFloat(payment.price).toLocaleString()}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                
                                <!-- הוספת תשלום מותאם -->
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
                                        <button onclick="addCustomPaymentToList()" style="
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
                            </div>
                        </div>
                        
                        <!-- סיכום -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold;">
                                סה"כ לתשלום: ₪<span id="smartModalTotal">${currentTotal.toLocaleString()}</span>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                כולל ${mandatoryPayments.length} תשלומי חובה
                                <span id="optionalCount"></span>
                            </div>
                        </div>
                        
                        <!-- כפתורים -->
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="closeSmartPaymentsModal()" style="
                                padding: 10px 30px;
                                background: #6c757d;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">ביטול</button>
                            <button onclick="applySmartPayments('${JSON.stringify(mandatoryPayments).replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" style="
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
            }

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
                    const mandatoryPayments = window.purchasePayments.filter(p => p.mandatory === true);
                    const editablePayments = window.purchasePayments.filter(p => p.mandatory !== true);
                    
                    console.log('📊 Mandatory payments:', mandatoryPayments.length);
                    console.log('📝 Editable payments:', editablePayments.length);
                    
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
                        const amount = Number(p.amount) || 0;
                        console.log(`Payment: ${p.type_name} = ${amount}`);
                        return sum + amount;
                    }, 0);
                    
                    console.log('💰 Total amount:', currentTotal);
                    
                    // בניית HTML
                    modal.innerHTML = this.buildModalHTML(mandatoryPayments, editablePayments, currentTotal);
                    
                    // הוספה ל-DOM
                    document.body.appendChild(modal);
                    
                    // הוספת חלון דיבאג
                    this.addDebugWindow();
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
                            <!-- חלון דיבאג -->
                            <div id="debugPanel" style="
                                position: absolute;
                                top: 10px;
                                right: 50px;
                                background: #333;
                                color: #0f0;
                                padding: 5px 10px;
                                border-radius: 4px;
                                font-family: monospace;
                                font-size: 11px;
                                cursor: pointer;
                                z-index: 1000;
                            " onclick="ExistingPaymentsManager.toggleDebug()">
                                🐛 Debug
                            </div>
                            
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
                    
                    const plotType = window.selectedGraveData?.plotType || -1;
                    const graveType = window.selectedGraveData?.graveType || -1;
                    
                    return `סוג חלקה: ${plotTypes[plotType]} | סוג קבר: ${graveTypes[graveType]} | תושבות: ירושלים`;
                },

                // // TODO 1
                // // בניית סקציית תשלומי חובה
                // buildMandatorySection: function(payments) {
                //     return `
                //         <div style="margin-bottom: 20px;">
                //             <h4 style="color: #dc3545; margin-bottom: 10px;">
                //                 <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">נעול</span>
                //                 תשלומי חובה מקוריים
                //             </h4>
                //             <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                //                 ${payments.map(payment => `
                //                     <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ffe5b4;">
                //                         <span style="font-weight: bold;">${payment.customPaymentType || `תשלום מסוג ${payment.paymentType}`}</span>
                //                         <div>
                //                             <span style="font-weight: bold; color: #dc3545;">₪${Number(payment.paymentAmount).toLocaleString()}</span>
                //                             <span style="margin-left: 10px; background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">🔒</span>
                //                         </div>
                //                     </div>
                //                 `).join('')}
                //             </div>
                //         </div>
                //     `;
                // },

                buildMandatorySection: function(payments) {
                    // הגדרת שמות התשלומים
                    const paymentTypeNames = {
                        1: 'עלות קבר',
                        2: 'שירותי לוויה',
                        3: 'שירותי קבורה',
                        4: 'אגרת מצבה',
                        5: 'בדיקת עומק',
                        6: 'פירוק מצבה',
                        7: 'הובלה מנתב״ג',
                        8: 'טהרה',
                        9: 'תכריכים',
                        10: 'החלפת שם',
                        99: 'אחר'
                    };
                    
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #dc3545; margin-bottom: 10px;">
                                <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">נעול</span>
                                תשלומי חובה מקוריים
                            </h4>
                            <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                                ${payments.map(payment => {
                                    const displayName = payment.customPaymentType || 
                                                    paymentTypeNames[payment.paymentType] || 
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
                
                // TODO 1
                // שורת תשלום לעריכה
                // buildEditablePaymentRow: function(payment, index) {
                //     return `
                //         <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb;">
                //             <input type="text" 
                //                 value="${payment.type_name}"
                //                 onchange="ExistingPaymentsManager.updateName(${index}, this.value)"
                //                 style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px; margin-left: 10px;">
                //             <input type="number" 
                //                 value="${payment.amount}"
                //                 step="0.01"
                //                 onchange="ExistingPaymentsManager.updateAmount(${index}, this.value)"
                //                 style="width: 120px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; margin-left: 10px;">
                //             <button onclick="ExistingPaymentsManager.removePayment(${index})" style="
                //                 padding: 6px 12px;
                //                 background: #dc3545;
                //                 color: white;
                //                 border: none;
                //                 border-radius: 4px;
                //                 cursor: pointer;
                //                 margin-right: 10px;
                //             ">הסר</button>
                //         </div>
                //     `;
                // },

                // שורת תשלום לעריכה
                buildEditablePaymentRow: function(payment, index) {
                    return `
                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb;">
                            <input type="text" 
                                value="${payment.customPaymentType || `תשלום מסוג ${payment.paymentType}`}"
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
                
                // חלון דיבאג
                addDebugWindow: function() {
                    const debugDiv = document.createElement('div');
                    debugDiv.id = 'paymentsDebugWindow';
                    debugDiv.style.cssText = `
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        width: 400px;
                        max-height: 300px;
                        background: #000;
                        color: #0f0;
                        padding: 10px;
                        border-radius: 4px;
                        font-family: monospace;
                        font-size: 11px;
                        overflow-y: auto;
                        display: none;
                        z-index: 10002;
                    `;
                    
                    debugDiv.innerHTML = `
                        <div style="border-bottom: 1px solid #0f0; margin-bottom: 5px;">🐛 DEBUG CONSOLE</div>
                        <pre>${JSON.stringify({
                            isEditMode: window.isEditMode,
                            paymentsCount: window.purchasePayments?.length || 0,
                            payments: window.purchasePayments,
                            selectedGraveData: window.selectedGraveData
                        }, null, 2)}</pre>
                    `;
                    
                    document.body.appendChild(debugDiv);
                },
                
                // הצג/הסתר דיבאג
                toggleDebug: function() {
                    const debug = document.getElementById('paymentsDebugWindow');
                    if (debug) {
                        debug.style.display = debug.style.display === 'none' ? 'block' : 'none';
                    }
                },

                updateName: function(index, value) {
                    const editablePayments = window.purchasePayments.filter(p => !p.required);
                    if (editablePayments[index]) {
                        const paymentIndex = window.purchasePayments.indexOf(editablePayments[index]);
                        window.purchasePayments[paymentIndex].customPaymentType = value;
                    }
                },

                updateAmount: function(index, value) {
                    const editablePayments = window.purchasePayments.filter(p => !p.required);
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
                    const editablePayments = window.purchasePayments.filter(p => !p.required);
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
                    document.getElementById('total_price').value = calculatePaymentsTotal();
                    document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
                    document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
                    this.close();
                },
                
                close: function() {
                    const modal = document.getElementById('existingPaymentsModal');
                    const debug = document.getElementById('paymentsDebugWindow');
                    if (modal) modal.remove();
                    if (debug) debug.remove();
                },
            };

            // הגדרה גלובלית
            window.ExistingPaymentsManager = ExistingPaymentsManager;

            // פונקציה חדשה להוספת תשלום מותאם לרשימה
            window.addCustomPaymentToList = function() {
                const name = document.getElementById('customPaymentName').value.trim();
                const amount = parseFloat(document.getElementById('customPaymentAmount').value);
                
                if (!name || !amount || amount <= 0) {
                    alert('יש למלא שם וסכום תקין');
                    return;
                }
                
                // הוסף לרשימה
                const optionalList = document.getElementById('optionalPaymentsList');
                const newPaymentId = 'custom_' + Date.now();
                
                const newPaymentHTML = `
                    <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb; background: #ffffcc;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                checked
                                data-price="${amount}"
                                data-name="${name}"
                                data-definition="custom"
                                data-custom="true"
                                onchange="updateSmartTotal()"
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
                updateSmartTotal();
            }

            // פונקציה לסגירת המודל
            window.closeSmartPaymentsModal = function() {
                const modal = document.getElementById('smartPaymentsModal');
                if (modal) {
                    modal.remove();
                }
            }

            // --------------------------------------------------------
            // -------- תחילת בדיקה
            // --------------------------------------------------------

            // פונקציה חדשה להצגת תשלומים במצב עריכה
            window.displayPaymentsListForEdit = function() {
                if (window.purchasePayments.length === 0) {
                    return '<p style="text-align: center; color: #999;">אין תשלומים</p>';
                }
                
                return `
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 8px; text-align: right; border-bottom: 2px solid #dee2e6;">סוג תשלום</th>
                                <th style="padding: 8px; text-align: right; border-bottom: 2px solid #dee2e6;">סכום</th>
                                <th style="padding: 8px; text-align: center; border-bottom: 2px solid #dee2e6;">סטטוס</th>
                                <th style="padding: 8px; text-align: center; border-bottom: 2px solid #dee2e6;">פעולה</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${window.purchasePayments.map((payment, index) => {
                                const isMandatory = payment.mandatory === true;
                                const isLocked = window.isEditMode && isMandatory;
                                
                                return `
                                    <tr style="${isLocked ? 'background: #ffe5e5;' : ''}">
                                        <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                            ${payment.type_name}
                                            ${isLocked ? '🔒' : ''}
                                        </td>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                            ${isLocked ? 
                                                `₪${payment.amount.toFixed(2)}` :
                                                `<input type="number" 
                                                    value="${payment.amount}" 
                                                    step="0.01"
                                                    onchange="updatePaymentAmount(${index}, this.value)"
                                                    style="width: 100px; padding: 4px; border: 1px solid #ddd;">`
                                            }
                                        </td>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">
                                            ${isMandatory ? 
                                                '<span style="color: #dc3545; font-weight: bold;">חובה</span>' : 
                                                '<span style="color: #28a745;">רשות</span>'}
                                        </td>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">
                                            ${isLocked ? 
                                                '<span style="color: #999;">נעול</span>' :
                                                `<button onclick="removePayment(${index})" style="
                                                    background: #dc3545;
                                                    color: white;
                                                    border: none;
                                                    padding: 4px 8px;
                                                    border-radius: 4px;
                                                    cursor: pointer;
                                                ">הסר</button>`
                                            }
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                `;
            }

            // פונקציה לעדכון סכום תשלום
            window.updatePaymentAmount = function(index, newAmount) {
                if (window.purchasePayments[index] && !window.purchasePayments[index].mandatory) {
                    window.purchasePayments[index].amount = parseFloat(newAmount) || 0;
                    document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
                }
            }

            // עדכון פונקציית הסרת תשלום
            window.removePayment = function(index) {
                // בדוק אם זה תשלום חובה במצב עריכה
                if (window.isEditMode && window.purchasePayments[index].mandatory === true) {
                    alert('לא ניתן להסיר תשלום חובה!\nתשלומי חובה נקבעים בעת יצירת הרכישה ולא ניתנים לשינוי.');
                    return;
                }
                
                window.purchasePayments.splice(index, 1);
                document.getElementById('paymentsList').innerHTML = displayPaymentsListForEdit();
                document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
            }

            // --------------------------------------------------------
            // -------- סוף בדיקה
            // --------------------------------------------------------


            window.closePaymentsManager = function() {
                const modal = document.getElementById('paymentsManagerModal');
                if (modal) {
                    modal.remove();
                    document.getElementById('total_price').value = calculatePaymentsTotal();
                    document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
                    document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
                }
            }

            window.addPayment = function(event) {
                event.preventDefault();
                
                const type = document.getElementById('payment_type').value;
                const amount = parseFloat(document.getElementById('payment_amount').value);
                
                const typeNames = {
                    'grave_cost': 'עלות קבר',
                    'service_cost': 'עלות שירות',
                    'tombstone_cost': 'עלות מצבה',
                    'maintenance': 'תחזוקה',
                    'other': 'אחר'
                };

                window.purchasePayments.push({
                    type: type,
                    type_name: typeNames[type],
                    amount: amount,
                    mandatory: false,  // תשלום חדש הוא תמיד לא חובה
                    date: new Date().toISOString()
                });

                // שנה את זה:
                document.getElementById('payments_data').innerHTML = displayPaymentsListForEdit();  // לא displayPaymentsList
                document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
                document.getElementById('payment_type').value = '';
                document.getElementById('payment_amount').value = '';
            }

            window.displayPaymentsList = function() {
                if (window.purchasePayments.length === 0) {
                    return '<p style="text-align: center; color: #999;">אין תשלומים</p>';
                }
                
                return `
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">סוג</th>
                                <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">סכום</th>
                                <th style="padding: 8px; text-align: center; border-bottom: 1px solid #ddd;">פעולה</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${window.purchasePayments.map((payment, index) => `
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;">${payment.type_name}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;">₪${payment.amount.toFixed(2)}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">
                                        <button onclick="removePayment(${index})" style="
                                            background: #dc3545;
                                            color: white;
                                            border: none;
                                            padding: 4px 8px;
                                            border-radius: 4px;
                                            cursor: pointer;
                                        ">הסר</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }

            window.displayPaymentsSummary = function() {
                if (!window.purchasePayments || window.purchasePayments.length === 0) {
                    return '<p style="color: #999;">לא הוגדרו תשלומים</p>';
                }

                // הגדרת שמות התשלומים מהקונפיג
                const paymentTypeNames = {
                    1: 'עלות קבר',
                    2: 'שירותי לוויה',
                    3: 'שירותי קבורה',
                    4: 'אגרת מצבה',
                    5: 'בדיקת עומק',
                    6: 'פירוק מצבה',
                    7: 'הובלה מנתב״ג',
                    8: 'טהרה',
                    9: 'תכריכים',
                    10: 'החלפת שם',
                    99: 'אחר'
                };

                const summary = {};
                window.purchasePayments.forEach(payment => {
                    // תחילה נסה customPaymentType, אחרי זה בדוק בקונפיג, ורק אז ברירת מחדל
                    const name = payment.customPaymentType || 
                                paymentTypeNames[payment.paymentType] || 
                                `תשלום מסוג ${payment.paymentType}`;
                    
                    const amount = parseFloat(payment.paymentAmount) || 0;
                    
                    if (!summary[name]) {
                        summary[name] = 0;
                    }
                    summary[name] += amount;
                });

                // TODO 1
                // const summary = {};
                // window.purchasePayments.forEach(payment => {
                //     const name = payment.customPaymentType || `תשלום מסוג ${payment.paymentType}`;
                //     const amount = parseFloat(payment.paymentAmount) || 0;
                    
                //     if (!summary[name]) {
                //         summary[name] = 0;
                //     }
                //     summary[name] += amount;
                // });
                
                return Object.entries(summary).map(([type, amount]) => 
                    `${type}: ₪${amount.toFixed(2)}`
                ).join(' | ') + `<br><strong>סה"כ: ₪${calculatePaymentsTotal()}</strong>`;
            }

            window.calculatePaymentsTotal = function() {
                if (!window.purchasePayments) return '0.00';
                
                const total = window.purchasePayments.reduce((sum, payment) => {
                    const amount = parseFloat(payment.paymentAmount) || 0;
                    return sum + amount;
                }, 0);
                
                return total.toFixed(2);
            }

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

                                // טען תשלומים קיימים
                                if (data.paymentsList) {
                                    try {
                                        window.purchasePayments = JSON.parse(data.paymentsList);                                  
                                        window.existingPayments = JSON.parse(data.paymentsList);

                                        alert('Raw paymentsList: ' + data.paymentsList);
                                        alert('Raw paymentsList JSON: ' + JSON.stringify(data.paymentsList));

                                        const parsedPayments = JSON.parse(data.paymentsList);
                                        alert('Parsed payments count: ' + parsedPayments.length);
                                        
                                        // בדוק את המבנה של התשלום הראשון
                                        if (parsedPayments.length > 0) {
                                            alert('First payment structure: ' + JSON.stringify(parsedPayments[0]));
                                        }
                                        
                                        // עדכן תצוגה
                                        if (window.displayPaymentsSummary) {
                                            document.getElementById('paymentsDisplay').innerHTML = window.displayPaymentsSummary();
                                        }
                                        
                                        // עדכן סכום
                                        document.getElementById('total_price').value = data.price || window.calculatePaymentsTotal();
                                        
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
                            debugLog(`ERROR loading purchase: ${error.message}`, 'error');
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

// פונקציות גלובליות לתאימות אחורה
window.openFormModal = function(type, parentId, itemId) {
    FormHandler.openForm(type, parentId, itemId);
};

window.closeFormModal = function(type) {
    FormHandler.closeForm(type);
};

console.log('FormHandler loaded:', typeof FormHandler);