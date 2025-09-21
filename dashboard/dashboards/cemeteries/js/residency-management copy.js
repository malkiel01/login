// dashboards/cemeteries/js/residency-management.js
// ניהול הגדרות תושבות

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

// טעינת הגדרות תושבות
async function loadResidencies() {
    console.log('Loading residency settings...');
    
    // עדכן את הסוג הנוכחי
    window.currentType = 'residency';
    window.currentParentId = null;
    DashboardCleaner.clear({ targetLevel: 'residency' });
    
    // נקה את כל הסידבר
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }
    
    // עדכן את כפתור ההוספה
    if (typeof updateAddButtonText === 'function') {
        updateAddButtonText();
    }
    
    // עדכן breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({ residency: { name: 'הגדרות תושבות' } });
    }
    
    // עדכון כותרת החלון
    document.title = 'הגדרות תושבות - מערכת בתי עלמין';
    
    // וודא שמבנה הטבלה קיים
    const table = document.getElementById('mainTable');
    if (table) {
        let thead = table.querySelector('thead');
        if (!thead) {
            thead = document.createElement('thead');
            table.insertBefore(thead, table.querySelector('tbody'));
        }
        
        let headerRow = thead.querySelector('tr');
        if (!headerRow) {
            headerRow = document.createElement('tr');
            headerRow.id = 'tableHeaders';
            thead.appendChild(headerRow);
        }
        
        // עדכן את הכותרות
        headerRow.innerHTML = `
            
                
            
            שם הגדרה
            מדינה
            עיר
            סוג תושבות
            תיאור
            סטטוס
            תאריך יצירה
            פעולות
        `;
    }
    
    await fetchResidencies();
}

// שליפת נתוני תושבות מהשרת
async function fetchResidencies(page = 1) {
    try {
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
    
    tableBody.setAttribute('data-residency-view', 'true');
    
    if (residencies.length === 0) {
        tableBody.innerHTML = `
            
                
                    
                        🏠
                        לא נמצאו הגדרות תושבות
                        
                            הוסף הגדרת תושבות חדשה
                        
                    
                
            
        `;
        return;
    }

    tableBody.innerHTML = residencies.map(residency => `
        
            
            
                ${residency.residencyName || '-'}
            
            ${residency.countryNameHe || '-'}
            ${residency.cityNameHe || '-'}
            
                
                    ${RESIDENCY_TYPES[residency.residencyType] || residency.residencyType}
                
            
            ${residency.description || '-'}
            
                
                    ${residency.isActive ? 'פעיל' : 'לא פעיל'}
                
            
            ${formatDate(residency.createDate)}
            
                
                    
                        
                    
                    
                        
                    
                    
                        
                    
                
            
        
    `).join('');
}

// פתיחת טופס הוספת הגדרת תושבות
function openAddResidency() {
    window.currentType = 'residency';
    window.currentParentId = null;
    
    FormHandler.openForm('residency', null, null);
}

// עריכת הגדרת תושבות
async function editResidency(id) {
    window.currentType = 'residency';
    FormHandler.openForm('residency', null, id);
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
            fetchResidencies();
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
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
    
    modal.innerHTML = `
        
            
                פרטי הגדרת תושבות - ${residency.residencyName || ''}
            
            
                
                    
                        פרטי ההגדרה
                        
                            שם הגדרה: ${residency.residencyName || '-'}
                            סוג תושבות: ${RESIDENCY_TYPES[residency.residencyType] || residency.residencyType}
                            מדינה: ${residency.countryNameHe || '-'}
                            עיר: ${residency.cityNameHe || '-'}
                            סטטוס: 
                                
                                    ${residency.isActive ? 'פעיל' : 'לא פעיל'}
                                
                            
                            תאריך יצירה: ${formatDate(residency.createDate)}
                        
                    
                    
                    ${residency.description ? `
                    
                        תיאור
                        ${residency.description}
                    
                    ` : ''}
                
            
            
                
                    ערוך
                
                סגור
            
        
    `;
    
    document.body.appendChild(modal);
}

// פונקציות עזר
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('he-IL');
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.residency-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
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
        z-index: 10000;
        animation: slideDown 0.3s ease;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}