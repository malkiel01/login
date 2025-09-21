// dashboard/dashboards/cemeteries/js/residency-management.js
// ניהול הגדרות תושבות - אינטגרציה מלאה

// משתנים גלובליים
let currentResidencies = [];
let currentResidencyPage = 1;
let editingResidencyId = null;

// קונפיגורציה של סוגי תושבות
const RESIDENCY_TYPES = {
    'jerusalem_area': 'תושבי ירושלים והסביבה',
    'israel': 'תושבי ישראל', 
    'abroad': 'תושבי חו״ל'
};

// טעינת הגדרות תושבות - פונקציה ראשית
async function loadResidencies() {
    console.log('Loading residency settings...');
    
    // ========================================
    // שלב 1: ניקוי מלא של הדף
    // ========================================
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'residency';
    window.currentParentId = null;
    
    // נקה את כל התוכן הקיים
    if (typeof DashboardCleaner !== 'undefined' && DashboardCleaner.clear) {
        DashboardCleaner.clear({ targetLevel: 'residency' });
    }
    
    // נקה את הכרטיס אם קיים
    const cardContainer = document.querySelector('.entity-card-container');
    if (cardContainer) {
        cardContainer.innerHTML = '';
        cardContainer.style.display = 'none';
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // סמן את הכפתור הנוכחי בסיידבר כפעיל
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
    const residencyItem = document.getElementById('residencyItem');
    if (residencyItem) {
        residencyItem.classList.add('active');
    }
    
    // ========================================
    // שלב 2: עדכון כפתור ההוספה
    // ========================================
    
    // עדכן את טקסט כפתור ההוספה
    const addButton = document.querySelector('.btn-add-entity');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-plus"></i> הוספת חוק תושבות';
        addButton.onclick = openAddResidency;
    }
    
    // אם יש פונקציה גלובלית לעדכון כפתור
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // ========================================
    // שלב 3: עדכון ה-Breadcrumb
    // ========================================
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ residency: { name: 'הגדרות תושבות' } });
    } else {
        // עדכון ידני של breadcrumb
        const breadcrumb = document.querySelector('.breadcrumb, .dashboard-breadcrumb');
        if (breadcrumb) {
            breadcrumb.innerHTML = `
                <a href="/dashboard">דשבורד</a>
                <span class="separator">/</span>
                <a href="/dashboard/dashboards/cemeteries">בתי עלמין</a>
                <span class="separator">/</span>
                <span class="current">הגדרות תושבות</span>
            `;
        }
    }
    
    // ========================================
    // שלב 4: עדכון כותרת החלון
    // ========================================
    
    document.title = 'הגדרות תושבות - מערכת בתי עלמין';
    
    // ========================================
    // שלב 5: הכנת מבנה הטבלה
    // ========================================
    
    const table = document.getElementById('mainTable');
    if (table) {
        // וודא שיש thead
        let thead = table.querySelector('thead');
        if (!thead) {
            thead = document.createElement('thead');
            table.insertBefore(thead, table.querySelector('tbody'));
        }
        
        // נקה ועדכן את הכותרות
        thead.innerHTML = '';
        const headerRow = document.createElement('tr');
        headerRow.id = 'tableHeaders';
        headerRow.innerHTML = `
            <th style="width: 40px;">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAllResidencies()">
            </th>
            <th>שם הגדרה</th>
            <th>מדינה</th>
            <th>עיר</th>
            <th>סוג תושבות</th>
            <th>תיאור</th>
            <th>סטטוס</th>
            <th>תאריך יצירה</th>
            <th style="width: 120px;">פעולות</th>
        `;
        thead.appendChild(headerRow);
        
        // וודא שיש tbody
        let tbody = table.querySelector('tbody');
        if (!tbody) {
            tbody = document.createElement('tbody');
            tbody.id = 'tableBody';
            table.appendChild(tbody);
        }
    }
    
    // ========================================
    // שלב 6: טעינת הנתונים
    // ========================================
    
    await fetchResidencies();
    
    // עדכון מונה בסיידבר
    updateResidencyCount();
}

// שליפת נתוני תושבות מהשרת
async function fetchResidencies(page = 1) {
    try {
        // הצג לודר
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">טוען...</span>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/residency-api.php?action=list&page=${page}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Failed to parse JSON:', text);
            showError('שגיאה בטעינת נתונים');
            return;
        }
        
        if (data.success) {
            currentResidencies = data.data;
            displayResidenciesInTable(data.data);
            
            // עדכון מונה בסיידבר
            const countElement = document.getElementById('residencyCount');
            if (countElement) {
                countElement.textContent = data.total || data.data.length;
            }
        } else {
            showError(data.error || 'שגיאה בטעינת הגדרות תושבות');
        }
    } catch (error) {
        console.error('Error loading residencies:', error);
        showError('שגיאה בטעינת נתונים');
    }
}

// הצגת תושבויות בטבלה
function displayResidenciesInTable(residencies) {
    const tableBody = document.getElementById('tableBody');
    
    if (!tableBody) {
        console.error('Table body not found');
        return;
    }
    
    // נקה את התוכן הקיים
    tableBody.innerHTML = '';
    tableBody.setAttribute('data-residency-view', 'true');
    
    if (residencies.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🏠</div>
                        <div style="font-size: 18px; margin-bottom: 10px;">לא נמצאו הגדרות תושבות</div>
                        <button class="btn btn-primary mt-3" onclick="openAddResidency()">
                            <i class="fas fa-plus"></i> הוסף חוק תושבות חדש
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    // הצג את הרשומות
    residencies.forEach((residency, index) => {
        const row = document.createElement('tr');
        row.setAttribute('data-id', residency.unicId);
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="residency-checkbox" value="${residency.unicId}">
            </td>
            <td>
                <strong>${residency.residencyName || '-'}</strong>
            </td>
            <td>${residency.countryNameHe || '-'}</td>
            <td>${residency.cityNameHe || '-'}</td>
            <td>
                <span class="badge badge-info">
                    ${RESIDENCY_TYPES[residency.residencyType] || residency.residencyType}
                </span>
            </td>
            <td>
                <span class="text-muted" style="font-size: 0.9em;">
                    ${residency.description ? truncateText(residency.description, 50) : '-'}
                </span>
            </td>
            <td>
                <span class="badge ${residency.isActive == 1 ? 'badge-success' : 'badge-danger'}">
                    ${residency.isActive == 1 ? 'פעיל' : 'לא פעיל'}
                </span>
            </td>
            <td>${formatDate(residency.createDate)}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-info" onclick="viewResidency('${residency.unicId}')" title="צפייה">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning" onclick="editResidency('${residency.unicId}')" title="עריכה">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger" onclick="deleteResidency('${residency.unicId}')" title="מחיקה">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

// ========================================
// פונקציות CRUD
// ========================================

// פתיחת טופס הוספת חוק תושבות
function openAddResidency() {
    console.log('Opening add residency form');
    
    window.currentType = 'residency';
    window.currentParentId = null;
    
    // אם יש FormHandler, השתמש בו
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('residency', null, null);
    } else {
        // אחרת, פתח טופס מותאם אישית
        openResidencyFormModal();
    }
}

// עריכת הגדרת תושבות
async function editResidency(id) {
    console.log('Editing residency:', id);
    
    window.currentType = 'residency';
    
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('residency', null, id);
    } else {
        // טען את הנתונים ופתח טופס עריכה
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/residency-api.php?action=get&id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                openResidencyFormModal(data.data);
            } else {
                showError('שגיאה בטעינת הנתונים');
            }
        } catch (error) {
            console.error('Error loading residency:', error);
            showError('שגיאה בטעינת הנתונים');
        }
    }
}

// מחיקת הגדרת תושבות
async function deleteResidency(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק הגדרת תושבות זו?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/residency-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('הגדרת התושבות נמחקה בהצלחה');
            
            // רענן את הטבלה
            await fetchResidencies();
            
            // עדכן מונה
            updateResidencyCount();
        } else {
            showError(result.error || 'שגיאה במחיקת הגדרת התושבות');
        }
    } catch (error) {
        console.error('Error deleting residency:', error);
        showError('שגיאה במחיקה');
    }
}

// צפייה בהגדרת תושבות
async function viewResidency(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/residency-api.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            showResidencyDetails(data.data);
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי הגדרת התושבות');
    }
}

// הצגת פרטי הגדרת תושבות
function showResidencyDetails(residency) {
    // נקה מודלים קיימים
    document.querySelectorAll('.modal.residency-modal').forEach(modal => modal.remove());
    
    const modal = document.createElement('div');
    modal.className = 'modal show residency-modal';
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
        z-index: 9999;
        animation: fadeIn 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            max-width: 700px; 
            width: 90%;
            max-height: 90vh; 
            overflow-y: auto;
            animation: slideDown 0.3s ease;
        ">
            <div class="modal-header" style="
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            ">
                <h2 style="margin: 0; color: #333;">
                    <i class="fas fa-home" style="color: #667eea; margin-left: 10px;"></i>
                    פרטי הגדרת תושבות
                </h2>
            </div>
            
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <!-- פרטי ההגדרה -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">
                            <i class="fas fa-info-circle"></i> פרטי ההגדרה
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">שם הגדרה:</label>
                                <div style="font-size: 1.1em;">${residency.residencyName || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">סוג תושבות:</label>
                                <div>
                                    <span class="badge badge-primary" style="font-size: 1em;">
                                        ${RESIDENCY_TYPES[residency.residencyType] || residency.residencyType}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">מדינה:</label>
                                <div style="font-size: 1.1em;">${residency.countryNameHe || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">עיר:</label>
                                <div style="font-size: 1.1em;">${residency.cityNameHe || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">סטטוס:</label>
                                <div>
                                    <span class="badge ${residency.isActive == 1 ? 'badge-success' : 'badge-danger'}">
                                        ${residency.isActive == 1 ? 'פעיל' : 'לא פעיל'}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">תאריך יצירה:</label>
                                <div style="font-size: 1.1em;">${formatDate(residency.createDate)}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- תיאור -->
                    ${residency.description ? `
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">
                            <i class="fas fa-align-left"></i> תיאור
                        </h4>
                        <div style="line-height: 1.6; color: #555;">
                            ${residency.description}
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="modal-footer" style="
                display: flex; 
                gap: 10px; 
                justify-content: flex-end; 
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            ">
                <button class="btn btn-warning" onclick="
                    this.closest('.modal').remove(); 
                    editResidency('${residency.unicId}');
                ">
                    <i class="fas fa-edit"></i> ערוך
                </button>
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">
                    <i class="fas fa-times"></i> סגור
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// ========================================
// טופס מותאם אישית (אם FormHandler לא זמין)
// ========================================

function openResidencyFormModal(data = null) {
    const isEdit = data !== null;
    
    // נקה מודלים קיימים
    document.querySelectorAll('.modal.residency-form-modal').forEach(modal => modal.remove());
    
    const modal = document.createElement('div');
    modal.className = 'modal show residency-form-modal';
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
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        ">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">
                    <i class="fas fa-${isEdit ? 'edit' : 'plus'}"></i>
                    ${isEdit ? 'עריכת חוק תושבות' : 'הוספת חוק תושבות חדש'}
                </h2>
            </div>
            
            <form id="residencyForm" onsubmit="saveResidency(event, ${isEdit ? "'" + data.unicId + "'" : 'null'})">
                <div class="form-group">
                    <label>שם הגדרת תושבות <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           name="residencyName" 
                           value="${data ? data.residencyName : ''}"
                           required>
                </div>
                
                <div class="form-group">
                    <label>סוג תושבות <span class="text-danger">*</span></label>
                    <select class="form-control" name="residencyType" required>
                        <option value="">-- בחר סוג תושבות --</option>
                        <option value="jerusalem_area" ${data && data.residencyType === 'jerusalem_area' ? 'selected' : ''}>
                            תושבי ירושלים והסביבה
                        </option>
                        <option value="israel" ${data && data.residencyType === 'israel' ? 'selected' : ''}>
                            תושבי ישראל
                        </option>
                        <option value="abroad" ${data && data.residencyType === 'abroad' ? 'selected' : ''}>
                            תושבי חו״ל
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>מדינה</label>
                    <select class="form-control" 
                            name="countryId" 
                            id="countrySelect" 
                            onchange="loadCitiesByCountry(this.value)">
                        <option value="">-- בחר מדינה --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>עיר</label>
                    <select class="form-control" name="cityId" id="citySelect">
                        <option value="">-- בחר עיר --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>תיאור</label>
                    <textarea class="form-control" 
                              name="description" 
                              rows="4">${data ? data.description || '' : ''}</textarea>
                </div>
                
                <div class="modal-footer" style="
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #f0f0f0;
                ">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> שמור
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i> ביטול
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // טען מדינות וערים
    loadCountries(data ? data.countryId : null);
    if (data && data.countryId) {
        loadCitiesByCountry(data.countryId, data.cityId);
    } else {
        loadAllCities();
    }
}

// ========================================
// פונקציות עזר
// ========================================

// טעינת מדינות
async function loadCountries(selectedId = null) {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/get-countries.php');
        const countries = await response.json();
        
        const select = document.getElementById('countrySelect');
        if (select) {
            select.innerHTML = '<option value="">-- בחר מדינה --</option>';
            countries.forEach(country => {
                const option = document.createElement('option');
                option.value = country.unicId;
                option.textContent = country.countryNameHe;
                if (selectedId && country.unicId === selectedId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading countries:', error);
    }
}

// טעינת ערים לפי מדינה
async function loadCitiesByCountry(countryId, selectedCityId = null) {
    const citySelect = document.getElementById('citySelect');
    if (!citySelect) return;
    
    if (!countryId) {
        loadAllCities();
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/get-cities.php?countryId=${countryId}`);
        const cities = await response.json();
        
        citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
        cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.unicId;
            option.textContent = city.cityNameHe;
            if (selectedCityId && city.unicId === selectedCityId) {
                option.selected = true;
            }
            citySelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading cities:', error);
    }
}

// טעינת כל הערים
async function loadAllCities() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/get-cities.php');
        const cities = await response.json();
        
        const citySelect = document.getElementById('citySelect');
        if (citySelect) {
            citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.unicId;
                option.textContent = city.cityNameHe;
                citySelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading cities:', error);
    }
}

// שמירת הגדרת תושבות
async function saveResidency(event, id = null) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // הכן את הנתונים
    const data = {
        residencyName: formData.get('residencyName'),
        residencyType: formData.get('residencyType'),
        countryId: formData.get('countryId'),
        cityId: formData.get('cityId'),
        description: formData.get('description')
    };
    
    // אם זו עריכה, הוסף את ה-ID
    if (id) {
        data.unicId = id;
    }
    
    // קבל את שמות המדינה והעיר
    const countrySelect = document.getElementById('countrySelect');
    const citySelect = document.getElementById('citySelect');
    
    if (countrySelect && data.countryId) {
        const selectedOption = countrySelect.options[countrySelect.selectedIndex];
        data.countryNameHe = selectedOption.text;
    }
    
    if (citySelect && data.cityId) {
        const selectedOption = citySelect.options[citySelect.selectedIndex];
        data.cityNameHe = selectedOption.text;
    }
    
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/residency-api.php?action=save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(id ? 'הגדרת התושבות עודכנה בהצלחה' : 'הגדרת התושבות נוספה בהצלחה');
            
            // סגור את המודל
            document.querySelector('.residency-form-modal')?.remove();
            
            // רענן את הטבלה
            await fetchResidencies();
            
            // עדכן מונה
            updateResidencyCount();
        } else {
            showError(result.error || 'שגיאה בשמירת הנתונים');
        }
    } catch (error) {
        console.error('Error saving residency:', error);
        showError('שגיאה בשמירת הנתונים');
    }
}

// עדכון מונה בסיידבר
function updateResidencyCount() {
    const countElement = document.getElementById('residencyCount');
    if (countElement && currentResidencies) {
        countElement.textContent = currentResidencies.length;
    }
}

// בחירת כל הרשומות
function toggleSelectAllResidencies() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.residency-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// חיתוך טקסט
function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

// פורמט תאריך
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('he-IL');
}

// פונקציות הודעות
function showSuccess(message) {
    showToast('success', message);
}

function showError(message) {
    showToast('error', message);
}

function showInfo(message) {
    showToast('info', message);
}

function showToast(type, message) {
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10001;
        animation: slideDown 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    // הוסף אייקון
    const icon = document.createElement('i');
    icon.className = type === 'success' ? 'fas fa-check-circle' : type === 'error' ? 'fas fa-times-circle' : 'fas fa-info-circle';
    toast.appendChild(icon);
    
    // הוסף טקסט
    const text = document.createElement('span');
    text.textContent = message;
    toast.appendChild(text);
    
    document.body.appendChild(toast);
    
    // הסר אוטומטית אחרי 3 שניות
    setTimeout(() => {
        toast.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// הוסף אנימציות CSS
if (!document.querySelector('#residency-animations')) {
    const style = document.createElement('style');
    style.id = 'residency-animations';
    style.innerHTML = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px) translateX(-50%);
            }
            to {
                opacity: 1;
                transform: translateY(0) translateX(-50%);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 1;
                transform: translateY(0) translateX(-50%);
            }
            to {
                opacity: 0;
                transform: translateY(-20px) translateX(-50%);
            }
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge-primary { background: #667eea; color: white; }
        .badge-info { background: #3b82f6; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-success { background: #10b981; color: white; }
        .badge-danger { background: #ef4444; color: white; }
    `;
    document.head.appendChild(style);
}

console.log('Residency Management Module Loaded - Full Integration');