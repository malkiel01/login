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
                console.log("case 'purchase': ", itemId);

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
                                            }, 300);
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

        alert('step 1')
        // בדיוק כמו לקוח - חכה ל-fieldset עם הנתונים
        this.waitForElement('#grave-selector-fieldset', (fieldset) => {
            if (fieldset.dataset.hierarchy) {
                window.hierarchyData = JSON.parse(fieldset.dataset.hierarchy);
                console.log('Hierarchy data loaded from fieldset');
            } else {
                console.error('No hierarchy data in fieldset');
                return;
            }
            
            // פונקציה לסינון ההיררכיה
            window.filterHierarchy = function(level) {
                const cemetery = document.getElementById('cemeterySelect').value;
                const block = document.getElementById('blockSelect').value;
                const plot = document.getElementById('plotSelect').value;
                const row = document.getElementById('rowSelect').value;
                const areaGrave = document.getElementById('areaGraveSelect').value;
                
                switch(level) {
                    case 'cemetery':
                        console.log('cemetery: ', cemetery);
                        
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
                    option.textContent = `קבר ${grave.grave_number}`;
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

            // כשנבחר קבר
            window.onGraveSelected = function(graveId) {
                if (graveId) {
                    // מצא את פרטי הקבר
                    const grave = window.hierarchyData.graves.find(g => g.unicId == graveId);
                    if (grave) {
                        // עדכן את הפרמטרים לתשלומים החכמים
                        window.selectedGraveData = {
                            graveId: graveId,
                            plotType: grave.plot_type || 1,
                            graveType: grave.grave_type || 1
                        };
                        
                        // הצג פרמטרים
                        updatePaymentParameters();
                    }
                } else {
                    window.selectedGraveData = null;
                    const paramsElement = document.getElementById('selectedParameters');
                    if (paramsElement) {
                        paramsElement.style.display = 'none';
                    }
                }
            }

            // עדכון תצוגת פרמטרים
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

            // משתנים גלובליים לתשלומים
            window.purchasePayments = [];
            window.selectedGraveData = null;

            // פתיחת מנהל תשלומים חכם
            window.openSmartPaymentsManager = async function() {
                const graveSelect = document.getElementById('graveSelect');
                const graveId = graveSelect ? graveSelect.value : null;
                
                if (!graveId || !window.selectedGraveData) {
                    alert('יש לבחור קבר תחילה');
                    return;
                }
                
                // טען תשלומים מתאימים מהשרת
                try {
                    const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            plotType: window.selectedGraveData.plotType,
                            graveType: window.selectedGraveData.graveType,
                            resident: 1, // תושב ירושלים
                            buyerStatus: document.querySelector('[name="buyer_status"]').value || null
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.payments.length > 0) {
                        // הצג את התשלומים שנמצאו
                        showSmartPaymentsModal(data.payments);
                    } else {
                        alert('לא נמצאו הגדרות תשלום מתאימות. השתמש בניהול ידני.');
                        openPaymentsManager();
                    }
                } catch (error) {
                    console.error('Error loading payments:', error);
                    openPaymentsManager();
                }
            };

            
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
                            <button onclick="document.getElementById('smartPaymentsModal').remove()" style="
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
                        
                        ${optionalPayments.length > 0 ? `
                            <!-- תשלומים אופציונליים -->
                            <div style="margin-bottom: 20px;">
                                <h4 style="color: #28a745; margin-bottom: 10px;">
                                    <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">אופציונלי</span>
                                    תשלומים נוספים
                                </h4>
                                <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                                    ${optionalPayments.map((payment, index) => `
                                        <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; ${index < optionalPayments.length - 1 ? 'border-bottom: 1px solid #c3e6cb;' : ''}">
                                            <label style="display: flex; align-items: center; cursor: pointer;">
                                                <input type="checkbox" 
                                                    id="optional_${payment.id || index}"
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
                            </div>
                        ` : ''}
                        
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
                        <div style="display: flex; gap: 10px; justify-content: space-between;">
                            <button onclick="addCustomPaymentInSmart()" style="
                                padding: 10px 20px;
                                background: #6c757d;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">+ הוסף תשלום מותאם</button>
                            
                            <div style="display: flex; gap: 10px;">
                                <button onclick="document.getElementById('smartPaymentsModal').remove()" style="
                                    padding: 10px 30px;
                                    background: #dc3545;
                                    color: white;
                                    border: none;
                                    border-radius: 4px;
                                    cursor: pointer;
                                ">ביטול</button>
                                <button onclick="applySmartPayments(${JSON.stringify(mandatoryPayments).replace(/"/g, '&quot;')})" style="
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
                    </div>
                `;
                
                document.body.appendChild(modal);
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

            // הוספת תשלום מותאם בתוך המודל החכם
            window.addCustomPaymentInSmart = function() {
                document.getElementById('smartPaymentsModal').remove();
                openPaymentsManager();
            }

            // הפונקציות הקיימות לניהול תשלומים ידני
            window.openPaymentsManager = function() {
                const modal = document.createElement('div');
                modal.id = 'paymentsManagerModal';
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
                
                modal.innerHTML = `
                    <div class="modal-content" style="
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        width: 600px;
                        max-height: 80vh;
                        overflow-y: auto;
                    ">
                        <h3 style="margin-bottom: 20px;">ניהול תשלומים</h3>
                        
                        <form onsubmit="addPayment(event)">
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px;">סוג תשלום</label>
                                    <select id="payment_type" required style="
                                        width: 100%;
                                        padding: 8px;
                                        border: 1px solid #ddd;
                                        border-radius: 4px;
                                    ">
                                        <option value="">-- בחר --</option>
                                        <option value="grave_cost">עלות קבר</option>
                                        <option value="service_cost">עלות שירות</option>
                                        <option value="tombstone_cost">עלות מצבה</option>
                                        <option value="maintenance">תחזוקה</option>
                                        <option value="other">אחר</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px;">סכום</label>
                                    <input type="number" id="payment_amount" step="0.01" required style="
                                        width: 100%;
                                        padding: 8px;
                                        border: 1px solid #ddd;
                                        border-radius: 4px;
                                    ">
                                </div>
                                <div>
                                    <button type="submit" style="
                                        margin-top: 24px;
                                        padding: 8px 15px;
                                        background: #28a745;
                                        color: white;
                                        border: none;
                                        border-radius: 4px;
                                        cursor: pointer;
                                        width: 100%;
                                    ">הוסף</button>
                                </div>
                            </div>
                        </form>
                        
                        <div id="paymentsList" style="
                            max-height: 300px;
                            overflow-y: auto;
                            margin-bottom: 20px;
                        ">
                            ${displayPaymentsList()}
                        </div>
                        
                        <div style="
                            padding: 10px;
                            background: #f8f9fa;
                            border-radius: 4px;
                            margin-bottom: 20px;
                            font-weight: bold;
                        ">
                            סה"כ: ₪<span id="paymentsTotal">${calculatePaymentsTotal()}</span>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="closePaymentsManager()" style="
                                padding: 10px 30px;
                                background: #667eea;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">אישור</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            }

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
                    date: new Date().toISOString()
                });
                
                document.getElementById('paymentsList').innerHTML = displayPaymentsList();
                document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
                document.getElementById('payment_type').value = '';
                document.getElementById('payment_amount').value = '';
            }

            window.removePayment = function(index) {
                window.purchasePayments.splice(index, 1);
                document.getElementById('paymentsList').innerHTML = displayPaymentsList();
                document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
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
                if (window.purchasePayments.length === 0) {
                    return '<p style="color: #999;">לא הוגדרו תשלומים</p>';
                }
                
                const summary = {};
                window.purchasePayments.forEach(payment => {
                    if (!summary[payment.type_name]) {
                        summary[payment.type_name] = 0;
                    }
                    summary[payment.type_name] += payment.amount;
                });
                
                return Object.entries(summary).map(([type, amount]) => 
                    `${type}: ₪${amount.toFixed(2)}`
                ).join(' | ') + `<br><strong>סה"כ: ₪${calculatePaymentsTotal()}</strong>`;
            }

            window.calculatePaymentsTotal = function() {
                return window.purchasePayments.reduce((total, payment) => total + payment.amount, 0).toFixed(2);
            }

            // אתחל
            window.populateBlocks();
            window.populatePlots();
        });

        // טען נתונים אם זה עריכה
        if (itemId) {
                    alert('step 2', itemId)
            console.log('temp id: ', itemId);
            
            const loadPurchaseData = () => {
                const form = document.querySelector('#purchaseFormModal form');
                console.log('Checking purchase form readiness:', form ? 'found' : 'not found');
                
                // בדוק שהטופס מוכן עם מספיק שדות
                if (form && form.elements && form.elements.length > 5) {
                    console.log('Purchase form is ready, loading data...');
                    
                    fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=get&id=${itemId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success && result.data) {
                                const data = result.data;
                                console.log('Filling purchase form with data:', Object.entries(data));
                                
                                // מלא את כל השדות
                                Object.keys(data).forEach(key => {
                                    const field = form.elements[key];
                                    if (field && data[key] !== null && data[key] !== undefined) {
                                        if (field.type === 'checkbox') {
                                            field.checked = data[key] == 1;
                                        } else {
                                            field.value = data[key];
                                        }
                                    }
                                });
                                
                                // טען תשלומים אם יש
                                if (data.payments_data) {
                                    try {
                                        window.purchasePayments = JSON.parse(data.payments_data);
                                        if (window.displayPaymentsSummary) {
                                            document.getElementById('paymentsDisplay').innerHTML = window.displayPaymentsSummary();
                                        }
                                    } catch(e) {
                                        console.error('Error parsing payments data:', e);
                                    }
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error loading purchase data:', error);
                        });
                    
                    return true; // הצלחנו לטעון
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