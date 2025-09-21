// ========================================
// קובץ 1: dashboard/dashboards/cemeteries/js/cities-management.js
// ניהול ערים - עם תמיכה בהוספה מכרטיס מדינה
// ========================================

// משתנים גלובליים
let currentCities = [];
let currentCityPage = 1;
let editingCityId = null;
let filterByCountryId = null; // לסינון לפי מדינה

// טעינת ערים - פונקציה ראשית
async function loadCities(countryId = null) {
    console.log('Loading cities...', countryId ? `for country: ${countryId}` : 'all');
    
    // שמור את המדינה לסינון
    filterByCountryId = countryId;
    
    // ========================================
    // שלב 1: ניקוי מלא של הדף
    // ========================================
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'city';
    window.currentParentId = countryId; // שמור את המדינה כהורה
    
    // נקה את כל התוכן הקיים
    if (typeof DashboardCleaner !== 'undefined' && DashboardCleaner.clear) {
        DashboardCleaner.clear({ targetLevel: 'city' });
    }
    
    // נקה את הכרטיס אם קיים (רק אם לא באים מכרטיס מדינה)
    if (!countryId) {
        const cardContainer = document.querySelector('.entity-card-container');
        if (cardContainer) {
            cardContainer.innerHTML = '';
            cardContainer.style.display = 'none';
        }
    }
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // סמן את הכפתור הנוכחי בסיידבר כפעיל
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
    const cityItem = document.getElementById('cityItem');
    if (cityItem) {
        cityItem.classList.add('active');
    }
    
    // ========================================
    // שלב 2: עדכון כפתור ההוספה
    // ========================================
    
    // עדכן את טקסט כפתור ההוספה
    const addButton = document.querySelector('.btn-add-entity');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-plus"></i> הוספת עיר';
        addButton.onclick = () => openAddCity(countryId);
    }
    
    // אם יש פונקציה גלובלית לעדכון כפתור
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // ========================================
    // שלב 3: עדכון ה-Breadcrumb
    // ========================================
    
    // אם יש מדינה, הצג אותה ב-breadcrumb
    let breadcrumbHtml = `
        <a href="/dashboard">דשבורד</a>
        <span class="separator">/</span>
        <a href="/dashboard/dashboards/cemeteries">בתי עלמין</a>
    `;
    
    if (countryId) {
        // טען את שם המדינה
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/countries-api.php?action=get&id=${countryId}`);
            const result = await response.json();
            if (result.success) {
                breadcrumbHtml += `
                    <span class="separator">/</span>
                    <a href="#" onclick="loadCountries()">${result.data.countryNameHe}</a>
                `;
            }
        } catch (error) {
            console.error('Error loading country name:', error);
        }
    }
    
    breadcrumbHtml += `
        <span class="separator">/</span>
        <span class="current">ניהול ערים</span>
    `;
    
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ city: { name: 'ניהול ערים' } });
    } else {
        const breadcrumb = document.querySelector('.breadcrumb, .dashboard-breadcrumb');
        if (breadcrumb) {
            breadcrumb.innerHTML = breadcrumbHtml;
        }
    }
    
    // ========================================
    // שלב 4: עדכון כותרת החלון
    // ========================================
    
    document.title = 'ניהול ערים - מערכת בתי עלמין';
    
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
                <input type="checkbox" id="selectAll" onchange="toggleSelectAllCities()">
            </th>
            <th>שם בעברית</th>
            <th>שם באנגלית</th>
            <th>מדינה</th>
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
    
    await fetchCities(countryId);
    
    // עדכון מונה בסיידבר
    updateCityCount();
}

// שליפת נתוני ערים מהשרת
async function fetchCities(countryId = null, page = 1) {
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
        
        // בנה את ה-URL עם פרמטרים
        let url = `/dashboard/dashboards/cemeteries/api/cities-api.php?action=list&page=${page}`;
        if (countryId) {
            url += `&countryId=${countryId}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            currentCities = result.data;
            displayCitiesInTable(result.data);
            
            // עדכון מונה בסיידבר
            const countElement = document.getElementById('cityCount');
            if (countElement) {
                countElement.textContent = result.pagination ? result.pagination.total : result.data.length;
            }
        } else {
            showError(result.error || 'שגיאה בטעינת ערים');
        }
    } catch (error) {
        console.error('Error loading cities:', error);
        showError('שגיאה בטעינת נתונים');
    }
}

// הצגת ערים בטבלה
function displayCitiesInTable(cities) {
    const tableBody = document.getElementById('tableBody');
    
    if (!tableBody) {
        console.error('Table body not found');
        return;
    }
    
    // נקה את התוכן הקיים
    tableBody.innerHTML = '';
    tableBody.setAttribute('data-city-view', 'true');
    
    if (cities.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🏙️</div>
                        <div style="font-size: 18px; margin-bottom: 10px;">לא נמצאו ערים</div>
                        <button class="btn btn-primary mt-3" onclick="openAddCity(${filterByCountryId ? "'" + filterByCountryId + "'" : 'null'})">
                            <i class="fas fa-plus"></i> הוסף עיר חדשה
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    // הצג את הרשומות
    cities.forEach((city, index) => {
        const row = document.createElement('tr');
        row.setAttribute('data-id', city.unicId);
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="city-checkbox" value="${city.unicId}">
            </td>
            <td>
                <strong>${city.cityNameHe || '-'}</strong>
            </td>
            <td>${city.cityNameEn || '-'}</td>
            <td>
                <span class="badge badge-info">${city.country_name || city.countryNameHe || '-'}</span>
            </td>
            <td>
                <span class="badge ${city.isActive == 1 ? 'badge-success' : 'badge-danger'}">
                    ${city.isActive == 1 ? 'פעיל' : 'לא פעיל'}
                </span>
            </td>
            <td>${formatDate(city.createDate)}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-info" onclick="viewCity('${city.unicId}')" title="צפייה">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning" onclick="editCity('${city.unicId}')" title="עריכה">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger" onclick="deleteCity('${city.unicId}')" title="מחיקה">
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

// פתיחת טופס הוספת עיר
function openAddCity(countryId = null) {
    console.log('Opening add city form', countryId ? `with country: ${countryId}` : 'without country');
    
    window.currentType = 'city';
    window.currentParentId = countryId;
    
    // אם יש FormHandler, השתמש בו
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        // אם יש מדינה, העבר אותה כ-parent_id
        if (countryId) {
            FormHandler.openForm('city', countryId, null);
        } else {
            // אם אין מדינה, פתח טופס רגיל שידרוש בחירת מדינה
            FormHandler.openForm('city', null, null);
        }
    } else {
        showError('FormHandler לא זמין');
    }
}

// עריכת עיר
async function editCity(id) {
    console.log('Editing city:', id);
    
    window.currentType = 'city';
    
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('city', null, id);
    } else {
        showError('FormHandler לא זמין');
    }
}

// מחיקת עיר
async function deleteCity(id) {
    if (!confirm('האם אתה בטוח שברצונך למחוק עיר זו?')) {
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cities-api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('העיר נמחקה בהצלחה');
            
            // רענן את הטבלה
            await fetchCities(filterByCountryId);
            
            // עדכן מונה
            updateCityCount();
        } else {
            showError(result.error || 'שגיאה במחיקת העיר');
        }
    } catch (error) {
        console.error('Error deleting city:', error);
        showError('שגיאה במחיקה');
    }
}

// צפייה בעיר
async function viewCity(id) {
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cities-api.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            showCityDetails(result.data);
        } else {
            showError(result.error || 'שגיאה בטעינת פרטי העיר');
        }
    } catch (error) {
        showError('שגיאה בטעינת פרטי העיר');
    }
}

// הצגת פרטי עיר
function showCityDetails(city) {
    // נקה מודלים קיימים
    document.querySelectorAll('.modal.city-modal').forEach(modal => modal.remove());
    
    const modal = document.createElement('div');
    modal.className = 'modal show city-modal';
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
                    <i class="fas fa-city" style="color: #667eea; margin-left: 10px;"></i>
                    פרטי עיר
                </h2>
            </div>
            
            <div class="modal-body">
                <div style="display: grid; gap: 20px;">
                    <!-- פרטי העיר -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #667eea;">
                            <i class="fas fa-info-circle"></i> פרטי העיר
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">שם בעברית:</label>
                                <div style="font-size: 1.1em;">${city.cityNameHe || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">שם באנגלית:</label>
                                <div style="font-size: 1.1em;">${city.cityNameEn || '-'}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">מדינה:</label>
                                <div>
                                    <span class="badge badge-primary" style="font-size: 1em;">
                                        ${city.country_name || city.countryNameHe || '-'}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">סטטוס:</label>
                                <div>
                                    <span class="badge ${city.isActive == 1 ? 'badge-success' : 'badge-danger'}">
                                        ${city.isActive == 1 ? 'פעיל' : 'לא פעיל'}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">תאריך יצירה:</label>
                                <div style="font-size: 1.1em;">${formatDate(city.createDate)}</div>
                            </div>
                            <div>
                                <label style="font-weight: bold; color: #666; font-size: 0.9em;">עדכון אחרון:</label>
                                <div style="font-size: 1.1em;">${formatDate(city.updateDate)}</div>
                            </div>
                        </div>
                    </div>
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
                    editCity('${city.unicId}');
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
function updateCityCount() {
    const countElement = document.getElementById('cityCount');
    if (countElement && currentCities) {
        countElement.textContent = currentCities.length;
    }
}

// בחירת כל הרשומות
function toggleSelectAllCities() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.city-checkbox');
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

console.log('Cities Management Module Loaded');