// ========================================
// קובץ 1: dashboard/dashboards/cemeteries/js/countries-management.js
// ניהול מדינות - מבוסס על residency-management.js
// ========================================

// משתנים גלובליים
let currentCountries = [];
let currentCountryPage = 1;
let editingCountryId = null;

// טעינת מדינות - פונקציה ראשית
async function loadCountries() {

    setActiveMenuItem('countryItem'); // ✅ הוסף
    
    // ========================================
    // שלב 1: ניקוי מלא של הדף
    // ========================================
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'country';
    window.currentParentId = null;
    
    // נקה את כל התוכן הקיים
    if (typeof DashboardCleaner !== 'undefined' && DashboardCleaner.clear) {
        DashboardCleaner.clear({ targetLevel: 'country' });
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
    const countryItem = document.getElementById('countryItem');
    if (countryItem) {
        countryItem.classList.add('active');
    }
    
    // ========================================
    // שלב 2: עדכון כפתור ההוספה
    // ========================================
    
    // עדכן את טקסט כפתור ההוספה
    const addButton = document.querySelector('.btn-add-entity');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-plus"></i> הוספת מדינה';
        addButton.onclick = openAddCountry;
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
        updateBreadcrumb({ country: { name: 'ניהול מדינות' } });
    } else {
        // עדכון ידני של breadcrumb
        const breadcrumb = document.querySelector('.breadcrumb, .dashboard-breadcrumb');
        if (breadcrumb) {
            breadcrumb.innerHTML = `
                <a href="/dashboard">דשבורד</a>
                <span class="separator">/</span>
                <a href="/dashboard/dashboards/cemeteries">בתי עלמין</a>
                <span class="separator">/</span>
                <span class="current">ניהול מדינות</span>
            `;
        }
    }
    
    // ========================================
    // שלב 4: עדכון כותרת החלון
    // ========================================
    
    document.title = 'ניהול מדינות - מערכת בתי עלמין';
    
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
                <input type="checkbox" id="selectAll" onchange="toggleSelectAllCountries()">
            </th>
            <th>שם בעברית</th>
            <th>שם באנגלית</th>
            <th>מספר ערים</th>
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
    
    await fetchCountries();
    
    // עדכון מונה בסיידבר
    updateCountryCount();
}

// שליפת נתוני מדינות מהשרת
async function fetchCountries(page = 1) {
    try {
        // הצג לודר
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">טוען...</span>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/countries-api.php?action=list&page=${page}`);
        const result = await response.json();
        
        if (result.success) {
            currentCountries = result.data;
            displayCountriesInTable(result.data);
            
            // עדכון מונה בסיידבר
            const countElement = document.getElementById('countryCount');
            if (countElement) {
                countElement.textContent = result.pagination ? result.pagination.total : result.data.length;
            }
        } else {
            showError(result.error || 'שגיאה בטעינת מדינות');
        }
    } catch (error) {
        console.error('Error loading countries:', error);
        showError('שגיאה בטעינת נתונים');
    }
}

// הצגת מדינות בטבלה
function displayCountriesInTable(countries) {
    const tableBody = document.getElementById('tableBody');
    
    if (!tableBody) {
        console.error('Table body not found');
        return;
    }
    
    // נקה את התוכן הקיים
    tableBody.innerHTML = '';
    tableBody.setAttribute('data-country-view', 'true');
    
    if (countries.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🌍</div>
                        <div style="font-size: 18px; margin-bottom: 10px;">לא נמצאו מדינות</div>
                        <button class="btn btn-primary mt-3" onclick="openAddCountry()">
                            <i class="fas fa-plus"></i> הוסף מדינה חדשה
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    // הצג את הרשומות
    countries.forEach((country, index) => {
        const row = document.createElement('tr');
        row.setAttribute('data-id', country.unicId);
        
        // חשב מספר ערים (אם קיים)
        const citiesCount = country.cities_count || 0;
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="country-checkbox" value="${country.unicId}">
            </td>
            <td>
                <strong>${country.countryNameHe || '-'}</strong>
            </td>
            <td>${country.countryNameEn || '-'}</td>
            <td>
                <span class="badge badge-secondary">${citiesCount}</span>
            </td>
            <td>
                <span class="badge ${country.isActive == 1 ? 'badge-success' : 'badge-danger'}">
                    ${country.isActive == 1 ? 'פעיל' : 'לא פעיל'}
                </span>
            </td>
            <td>${formatDate(country.createDate)}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-info" onclick="viewCountry('${country.unicId}')" title="צפייה">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning" onclick="editCountry('${country.unicId}')" title="עריכה">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger" onclick="deleteCountry('${country.unicId}')" title="מחיקה">
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

// פתיחת טופס הוספת מדינה
function openAddCountry() {
    
    window.currentType = 'country';
    window.currentParentId = null;
    
    // אם יש FormHandler, השתמש בו
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('country', null, null);
    } else {
        // אחרת, פתח טופס מותאם אישית
        showError('FormHandler לא זמין');
    }
}

// עריכת מדינה
async function editCountry(id) {
    
    window.currentType = 'country';
    
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('country', null, id);
    } else {
        showError('FormHandler לא זמין');
    }
}

// מחיקת מדינה
async function deleteCountry(id) {
    // בדוק אם יש ערים במדינה זו
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/countries-api.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data.cities_count > 0) {
            showError(`לא ניתן למחוק מדינה עם ${result.data.cities_count} ערים. יש למחוק קודם את הערים.`);
            return;
        }
    } catch (error) {
        console.error('Error checking cities:', error);
    }
    
    if (!confirm('האם אתה בטוח שברצונך למחוק מדינה זו?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/countries-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('המדינה נמחקה בהצלחה');
            
            // רענן את הטבלה
            await fetchCountries();
            
            // עדכן מונה
            updateCountryCount();
        } else {
            showError(result.error || 'שגיאה במחיקת המדינה');
        }
    } catch (error) {
        console.error('Error deleting country:', error);
        showError('שגיאה במחיקה');
    }
}

// צפייה במדינה
async function viewCountry(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/countries-api.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            showCountryDetails(result.data);
        } else {
            showError(result.error || 'שגיאה בטעינת פרטי המדינה');
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי המדינה');
    }
}

// הצגת פרטי מדינה
function showCountryDetails(country) {
    // נקה מודלים קיימים
    document.querySelectorAll('.modal.country-modal').forEach(modal => modal.remove());
    
    const modal = document.createElement('div');
    modal.className = 'modal show country-modal';
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
                    <i class="fas fa-globe" style="color: #667eea; margin-left: 10px;"></i>
                    פרטי מדינה
                </h2>
            </div>
            
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <!-- פרטי המדינה -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">
                            <i class="fas fa-info-circle"></i> פרטי המדינה
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">שם בעברית:</label>
                                <div style="font-size: 1.1em;">${country.countryNameHe || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">שם באנגלית:</label>
                                <div style="font-size: 1.1em;">${country.countryNameEn || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">מספר ערים:</label>
                                <div>
                                    <span class="badge badge-info" style="font-size: 1em;">
                                        ${country.cities_count || 0}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">סטטוס:</label>
                                <div>
                                    <span class="badge ${country.isActive == 1 ? 'badge-success' : 'badge-danger'}">
                                        ${country.isActive == 1 ? 'פעיל' : 'לא פעיל'}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">תאריך יצירה:</label>
                                <div style="font-size: 1.1em;">${formatDate(country.createDate)}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">עדכון אחרון:</label>
                                <div style="font-size: 1.1em;">${formatDate(country.updateDate)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="
                display: flex; 
                gap: 10px; 
                justify-content: flex-start; 
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            ">
                <button class="btn btn-primary" onclick="openAddCity('${country.unicId}')">
                    <i class="fas fa-plus"></i> הוסף עיר למדינה זו
                </button>
                <button class="btn btn-info" onclick="loadCities('${country.unicId}')">
                    <i class="fas fa-list"></i> הצג ערים במדינה זו
                </button>
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
                    editCountry('${country.unicId}');
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
// פונקציות עזר
// ========================================

// עדכון מונה בסיידבר
function updateCountryCount() {
    const countElement = document.getElementById('countryCount');
    if (countElement && currentCountries) {
        countElement.textContent = currentCountries.length;
    }
}

// בחירת כל הרשומות
function toggleSelectAllCountries() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.country-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
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

