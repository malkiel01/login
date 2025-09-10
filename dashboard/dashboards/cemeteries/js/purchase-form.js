// purchase-form.js - טופס רכישה מתקדם עם בחירה היררכית

// משתנים גלובליים לטופס רכישה
let selectedGraveData = {
    cemetery_id: null,
    block_id: null,
    plot_id: null,
    row_id: null,
    area_grave_id: null,
    grave_id: null
};

let purchasePayments = [];

// פתיחת טופס רכישה חדשה
async function openAddPurchase() {
    console.log('Opening purchase form...');
    
    try {
        // טען רשימת לקוחות פנויים
        const customersResponse = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=list&status=1');
        const customersData = await customersResponse.json();
        
        // טען רשימת בתי עלמין
        const cemeteriesResponse = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=cemetery');
        const cemeteriesData = await cemeteriesResponse.json();
        
        createPurchaseFormModal(customersData.data || [], cemeteriesData.data || []);
    } catch (error) {
        console.error('Error loading form data:', error);
        alert('שגיאה בטעינת נתוני הטופס');
    }
}

// יצירת מודל טופס רכישה
function createPurchaseFormModal(customers, cemeteries, purchaseData = null) {
    // נקה נתונים קודמים
    selectedGraveData = {
        cemetery_id: null,
        block_id: null,
        plot_id: null,
        row_id: null,
        area_grave_id: null,
        grave_id: null
    };
    purchasePayments = purchaseData?.payments_data ? JSON.parse(purchaseData.payments_data) : [];
    
    const modal = document.createElement('div');
    modal.id = 'purchaseFormModal';
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
        z-index: 10000;
        overflow-y: auto;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="
            background: white;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">${purchaseData ? 'עריכת רכישה' : 'רכישה חדשה'}</h2>
                <button onclick="closePurchaseForm()" style="
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #999;
                ">×</button>
            </div>
            
            <form id="purchaseForm" onsubmit="submitPurchaseForm(event)">
                ${purchaseData ? `<input type="hidden" name="id" value="${purchaseData.id}">` : ''}
                
                <!-- בחירת לקוח -->
                <fieldset style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                    <legend style="padding: 0 10px; font-weight: bold;">פרטי לקוח</legend>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">
                            בחר לקוח <span style="color: red;">*</span>
                        </label>
                        <div style="display: flex; gap: 10px;">
                            <select name="customer_id" id="customer_id" required style="
                                flex: 1;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            " onchange="updateCustomerInfo(this.value)">
                                <option value="">-- בחר לקוח --</option>
                                ${customers.map(customer => `
                                    <option value="${customer.id}" 
                                            ${purchaseData?.customer_id == customer.id ? 'selected' : ''}
                                            ${customer.customer_status != 1 ? 'disabled' : ''}>
                                        ${customer.first_name} ${customer.last_name} 
                                        ${customer.id_number ? `(${customer.id_number})` : ''}
                                        ${customer.customer_status != 1 ? ' - לא זמין' : ''}
                                    </option>
                                `).join('')}
                            </select>
                            <button type="button" onclick="openNewCustomerForm()" style="
                                padding: 8px 15px;
                                background: #28a745;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">+ לקוח חדש</button>
                        </div>
                        <div id="customerInfo" style="
                            margin-top: 10px;
                            padding: 10px;
                            background: #f8f9fa;
                            border-radius: 4px;
                            display: none;
                        "></div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">קרבה לנפטר</label>
                        <input type="text" name="kinship" value="${purchaseData?.kinship || ''}" 
                               placeholder="למשל: בן, בת, אח, הורה..." style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        ">
                    </div>
                </fieldset>
                
                <!-- בחירת קבר היררכית -->
                <fieldset style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                    <legend style="padding: 0 10px; font-weight: bold;">בחירת מיקום קבר</legend>
                    
                    <!-- בית עלמין -->
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">
                            בית עלמין <span style="color: red;">*</span>
                        </label>
                        <select id="cemetery_select" required onchange="loadBlocks(this.value)" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        ">
                            <option value="">-- בחר בית עלמין --</option>
                            ${cemeteries.map(cem => `
                                <option value="${cem.id}">${cem.name}</option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <!-- גוש -->
                    <div class="form-group" style="margin-bottom: 15px; display: none;" id="block_group">
                        <label style="display: block; margin-bottom: 5px;">
                            גוש <span style="color: red;">*</span>
                        </label>
                        <select id="block_select" required onchange="loadPlots(this.value)" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        " disabled>
                            <option value="">-- בחר קודם בית עלמין --</option>
                        </select>
                    </div>
                    
                    <!-- חלקה -->
                    <div class="form-group" style="margin-bottom: 15px; display: none;" id="plot_group">
                        <label style="display: block; margin-bottom: 5px;">
                            חלקה <span style="color: red;">*</span>
                        </label>
                        <select id="plot_select" required onchange="loadRows(this.value)" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        " disabled>
                            <option value="">-- בחר קודם גוש --</option>
                        </select>
                    </div>
                    
                    <!-- שורה -->
                    <div class="form-group" style="margin-bottom: 15px; display: none;" id="row_group">
                        <label style="display: block; margin-bottom: 5px;">
                            שורה <span style="color: red;">*</span>
                        </label>
                        <select id="row_select" required onchange="loadAreaGraves(this.value)" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        " disabled>
                            <option value="">-- בחר קודם חלקה --</option>
                        </select>
                    </div>
                    
                    <!-- אחוזת קבר -->
                    <div class="form-group" style="margin-bottom: 15px; display: none;" id="area_grave_group">
                        <label style="display: block; margin-bottom: 5px;">
                            אחוזת קבר <span style="color: red;">*</span>
                        </label>
                        <select id="area_grave_select" required onchange="loadGraves(this.value)" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        " disabled>
                            <option value="">-- בחר קודם שורה --</option>
                        </select>
                    </div>
                    
                    <!-- קבר -->
                    <div class="form-group" style="margin-bottom: 15px; display: none;" id="grave_group">
                        <label style="display: block; margin-bottom: 5px;">
                            קבר <span style="color: red;">*</span>
                        </label>
                        <select name="grave_id" id="grave_select" required onchange="updateGraveInfo(this.value)" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        " disabled>
                            <option value="">-- בחר קודם אחוזת קבר --</option>
                        </select>
                        <div id="graveInfo" style="
                            margin-top: 10px;
                            padding: 10px;
                            background: #f8f9fa;
                            border-radius: 4px;
                            display: none;
                        "></div>
                    </div>
                </fieldset>
                
                <!-- פרטי רכישה -->
                <fieldset style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                    <legend style="padding: 0 10px; font-weight: bold;">פרטי רכישה</legend>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">מספר רכישה</label>
                            <input type="text" name="purchase_number" value="${purchaseData?.purchase_number || ''}" 
                                   placeholder="יוצר אוטומטית אם ריק" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">תאריך פתיחה</label>
                            <input type="date" name="opening_date" 
                                   value="${purchaseData?.opening_date || new Date().toISOString().split('T')[0]}" 
                                   style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">סטטוס רכישה</label>
                            <select name="purchase_status" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                                <option value="1" ${purchaseData?.purchase_status == 1 ? 'selected' : ''}>טיוטה</option>
                                <option value="2" ${purchaseData?.purchase_status == 2 ? 'selected' : ''}>אושר</option>
                                <option value="3" ${purchaseData?.purchase_status == 3 ? 'selected' : ''}>שולם</option>
                                <option value="4" ${purchaseData?.purchase_status == 4 ? 'selected' : ''}>בוטל</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">סטטוס רוכש</label>
                            <select name="buyer_status" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                                <option value="">-- בחר --</option>
                                <option value="1" ${purchaseData?.buyer_status == 1 ? 'selected' : ''}>רוכש לעצמו</option>
                                <option value="2" ${purchaseData?.buyer_status == 2 ? 'selected' : ''}>רוכש לאחר</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">מספר שטר</label>
                            <input type="text" name="deed_number" value="${purchaseData?.deed_number || ''}" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="has_certificate" value="1" 
                                       ${purchaseData?.has_certificate ? 'checked' : ''}>
                                יש תעודה
                            </label>
                        </div>
                    </div>
                </fieldset>
                
                <!-- תשלומים -->
                <fieldset style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                    <legend style="padding: 0 10px; font-weight: bold;">תשלומים</legend>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">סכום כולל</label>
                            <input type="number" name="price" id="total_price" 
                                   value="${purchaseData?.price || ''}" 
                                   step="0.01" readonly style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                                background: #f8f9fa;
                            ">
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px;">מספר תשלומים</label>
                            <input type="number" name="num_payments" 
                                   value="${purchaseData?.num_payments || 1}" 
                                   min="1" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                        </div>
                    </div>
                    
                    <button type="button" onclick="openPaymentsManager()" style="
                        padding: 10px 20px;
                        background: #17a2b8;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        margin-bottom: 10px;
                    ">
                        ניהול תשלומים מפורט
                    </button>
                    
                    <div id="paymentsDisplay" style="
                        background: #f8f9fa;
                        padding: 10px;
                        border-radius: 4px;
                        min-height: 50px;
                    ">
                        ${displayPaymentsSummary()}
                    </div>
                    
                    <input type="hidden" name="payments_data" id="payments_data" 
                           value='${JSON.stringify(purchasePayments)}'>
                </fieldset>
                
                <!-- הערות -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px;">הערות</label>
                    <textarea name="comments" rows="3" style="
                        width: 100%;
                        padding: 8px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                    ">${purchaseData?.comments || ''}</textarea>
                </div>
                
                <!-- כפתורים -->
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closePurchaseForm()" style="
                        padding: 10px 30px;
                        background: #6c757d;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">ביטול</button>
                    <button type="submit" style="
                        padding: 10px 30px;
                        background: #667eea;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">${purchaseData ? 'עדכן רכישה' : 'שמור רכישה'}</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // אם יש נתוני רכישה, טען את ההיררכיה
    if (purchaseData && purchaseData.grave_id) {
        loadExistingGraveHierarchy(purchaseData.grave_id);
    }
}

// טעינת גושים לפי בית עלמין
async function loadBlocks(cemeteryId) {
    if (!cemeteryId) {
        document.getElementById('block_group').style.display = 'none';
        return;
    }
    
    selectedGraveData.cemetery_id = cemeteryId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
        const data = await response.json();
        
        const blockSelect = document.getElementById('block_select');
        blockSelect.innerHTML = '<option value="">-- בחר גוש --</option>';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(block => {
                blockSelect.innerHTML += `<option value="${block.id}">${block.name}</option>`;
            });
            blockSelect.disabled = false;
            document.getElementById('block_group').style.display = 'block';
        } else {
            alert('לא נמצאו גושים בבית העלמין הנבחר');
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

// טעינת חלקות לפי גוש
async function loadPlots(blockId) {
    if (!blockId) {
        document.getElementById('plot_group').style.display = 'none';
        return;
    }
    
    selectedGraveData.block_id = blockId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}`);
        const data = await response.json();
        
        const plotSelect = document.getElementById('plot_select');
        plotSelect.innerHTML = '<option value="">-- בחר חלקה --</option>';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(plot => {
                plotSelect.innerHTML += `<option value="${plot.id}">${plot.name}</option>`;
            });
            plotSelect.disabled = false;
            document.getElementById('plot_group').style.display = 'block';
        } else {
            alert('לא נמצאו חלקות בגוש הנבחר');
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

// טעינת שורות לפי חלקה
async function loadRows(plotId) {
    if (!plotId) {
        document.getElementById('row_group').style.display = 'none';
        return;
    }
    
    selectedGraveData.plot_id = plotId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        const rowSelect = document.getElementById('row_select');
        rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(row => {
                rowSelect.innerHTML += `<option value="${row.id}">${row.name}</option>`;
            });
            rowSelect.disabled = false;
            document.getElementById('row_group').style.display = 'block';
        } else {
            alert('לא נמצאו שורות בחלקה הנבחרת');
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
}

// טעינת אחוזות קבר לפי שורה - רק עם קברים פנויים
async function loadAreaGraves(rowId) {
    if (!rowId) {
        document.getElementById('area_grave_group').style.display = 'none';
        return;
    }
    
    selectedGraveData.row_id = rowId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
        const data = await response.json();
        
        const areaGraveSelect = document.getElementById('area_grave_select');
        areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
        
        if (data.success && data.data.length > 0) {
            // בדוק לכל אחוזת קבר אם יש לה קברים פנויים
            for (const areaGrave of data.data) {
                const gravesResponse = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGrave.id}`);
                const gravesData = await gravesResponse.json();
                
                // ספור קברים פנויים
                const availableGraves = gravesData.data?.filter(g => g.grave_status == 1) || [];
                
                if (availableGraves.length > 0) {
                    areaGraveSelect.innerHTML += `
                        <option value="${areaGrave.id}">
                            ${areaGrave.name} (${availableGraves.length} קברים פנויים)
                        </option>
                    `;
                }
            }
            
            if (areaGraveSelect.options.length > 1) {
                areaGraveSelect.disabled = false;
                document.getElementById('area_grave_group').style.display = 'block';
            } else {
                alert('לא נמצאו אחוזות קבר עם קברים פנויים בשורה הנבחרת');
            }
        } else {
            alert('לא נמצאו אחוזות קבר בשורה הנבחרת');
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
    }
}

// טעינת קברים לפי אחוזת קבר - רק פנויים
async function loadGraves(areaGraveId) {
    if (!areaGraveId) {
        document.getElementById('grave_group').style.display = 'none';
        return;
    }
    
    selectedGraveData.area_grave_id = areaGraveId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
        const data = await response.json();
        
        const graveSelect = document.getElementById('grave_select');
        graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
        
        if (data.success && data.data.length > 0) {
            // רק קברים פנויים (סטטוס 1)
            const availableGraves = data.data.filter(g => g.grave_status == 1);
            
            if (availableGraves.length > 0) {
                availableGraves.forEach(grave => {
                    graveSelect.innerHTML += `
                        <option value="${grave.id}">
                            קבר מספר ${grave.grave_number}
                        </option>
                    `;
                });
                graveSelect.disabled = false;
                document.getElementById('grave_group').style.display = 'block';
            } else {
                alert('לא נמצאו קברים פנויים באחוזת הקבר הנבחרת');
            }
        } else {
            alert('לא נמצאו קברים באחוזת הקבר הנבחרת');
        }
    } catch (error) {
        console.error('Error loading graves:', error);
    }
}

// עדכון מידע על קבר נבחר
async function updateGraveInfo(graveId) {
    if (!graveId) {
        document.getElementById('graveInfo').style.display = 'none';
        return;
    }
    
    selectedGraveData.grave_id = graveId;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=get&type=grave&id=${graveId}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const grave = data.data;
            document.getElementById('graveInfo').innerHTML = `
                <strong>פרטי הקבר:</strong><br>
                מספר קבר: ${grave.grave_number}<br>
                ${grave.grave_location ? `מיקום: ${grave.grave_location}<br>` : ''}
                סטטוס: <span style="color: green;">פנוי</span>
            `;
            document.getElementById('graveInfo').style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading grave info:', error);
    }
}

// עדכון מידע על לקוח נבחר
async function updateCustomerInfo(customerId) {
    if (!customerId) {
        document.getElementById('customerInfo').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${customerId}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const customer = data.data;
            document.getElementById('customerInfo').innerHTML = `
                <strong>פרטי לקוח:</strong><br>
                ${customer.first_name} ${customer.last_name}<br>
                ${customer.id_number ? `ת.ז: ${customer.id_number}<br>` : ''}
                ${customer.phone ? `טלפון: ${customer.phone}<br>` : ''}
                ${customer.email ? `אימייל: ${customer.email}` : ''}
            `;
            document.getElementById('customerInfo').style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading customer info:', error);
    }
}

// פתיחת מנהל תשלומים
function openPaymentsManager() {
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

// סגירת מנהל תשלומים
function closePaymentsManager() {
    const modal = document.getElementById('paymentsManagerModal');
    if (modal) {
        modal.remove();
        
        // עדכן את הסכום הכולל
        document.getElementById('total_price').value = calculatePaymentsTotal();
        
        // עדכן את התצוגה
        document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
        
        // עדכן את ה-JSON
        document.getElementById('payments_data').value = JSON.stringify(purchasePayments);
    }
}

// הוספת תשלום
function addPayment(event) {
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
    
    purchasePayments.push({
        type: type,
        type_name: typeNames[type],
        amount: amount,
        date: new Date().toISOString()
    });
    
    // רענן את התצוגה
    document.getElementById('paymentsList').innerHTML = displayPaymentsList();
    document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
    
    // נקה את הטופס
    document.getElementById('payment_type').value = '';
    document.getElementById('payment_amount').value = '';
}

// הסרת תשלום
function removePayment(index) {
    purchasePayments.splice(index, 1);
    document.getElementById('paymentsList').innerHTML = displayPaymentsList();
    document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
}

// הצגת רשימת תשלומים
function displayPaymentsList() {
    if (purchasePayments.length === 0) {
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
                ${purchasePayments.map((payment, index) => `
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

// הצגת סיכום תשלומים
function displayPaymentsSummary() {
    if (purchasePayments.length === 0) {
        return '<p style="color: #999;">לא הוגדרו תשלומים</p>';
    }
    
    const summary = {};
    purchasePayments.forEach(payment => {
        if (!summary[payment.type_name]) {
            summary[payment.type_name] = 0;
        }
        summary[payment.type_name] += payment.amount;
    });
    
    return Object.entries(summary).map(([type, amount]) => 
        `${type}: ₪${amount.toFixed(2)}`
    ).join(' | ') + `<br><strong>סה"כ: ₪${calculatePaymentsTotal()}</strong>`;
}

// חישוב סכום כולל
function calculatePaymentsTotal() {
    return purchasePayments.reduce((total, payment) => total + payment.amount, 0).toFixed(2);
}

// סגירת טופס רכישה
function closePurchaseForm() {
    const modal = document.getElementById('purchaseFormModal');
    if (modal) {
        modal.remove();
    }
}

// שליחת טופס רכישה
async function submitPurchaseForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const isEdit = formData.get('id');
    
    // וידוא שנבחר קבר
    if (!formData.get('grave_id')) {
        alert('חובה לבחור קבר');
        return;
    }
    
    const data = {
        customer_id: formData.get('customer_id'),
        grave_id: formData.get('grave_id'),
        purchase_number: formData.get('purchase_number') || null,
        purchase_status: formData.get('purchase_status') || 1,
        buyer_status: formData.get('buyer_status') || null,
        price: parseFloat(document.getElementById('total_price').value) || null,
        num_payments: formData.get('num_payments') || 1,
        opening_date: formData.get('opening_date') || null,
        has_certificate: formData.get('has_certificate') ? 1 : 0,
        deed_number: formData.get('deed_number') || null,
        kinship: formData.get('kinship') || null,
        comments: formData.get('comments') || null,
        payments_data: document.getElementById('payments_data').value,
        is_active: 1
    };
    
    try {
        const url = isEdit 
            ? `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=update&id=${formData.get('id')}`
            : `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=create`;
            
        const method = isEdit ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closePurchaseForm();
            alert(isEdit ? 'הרכישה עודכנה בהצלחה' : 'הרכישה נוספה בהצלחה');
            
            // רענן את רשימת הרכישות
            if (typeof loadAllPurchases === 'function') {
                loadAllPurchases();
            }
        } else {
            alert('שגיאה: ' + (result.error || 'שגיאה בשמירת הרכישה'));
        }
    } catch (error) {
        console.error('Error saving purchase:', error);
        alert('שגיאה בשמירת הרכישה');
    }
}

// פתיחת טופס לקוח חדש
function openNewCustomerForm() {
    alert('פונקציה זו תיושם בקרוב - יצירת לקוח חדש');
    // כאן תוסיף את הלוגיקה לפתיחת טופס יצירת לקוח חדש
}

// טעינת היררכיה עבור קבר קיים (בעריכה)
async function loadExistingGraveHierarchy(graveId) {
    // כאן תוסיף לוגיקה לטעינת כל ההיררכיה של קבר קיים
    console.log('Loading hierarchy for grave:', graveId);
}