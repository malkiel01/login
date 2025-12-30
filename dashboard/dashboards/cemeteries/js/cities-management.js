/*
 * File: dashboards/dashboard/cemeteries/assets/js/cities-management.js
 * Version: 5.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v5.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('city', countryId)
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ תמיכה בסינון לפי מדינה (parent)
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCities = [];
let citySearch = null;
let citiesTable = null;
let editingCityId = null;
let filterByCountryId = null;

let citiesIsSearchMode = false;
let citiesCurrentQuery = '';
let citiesSearchResults = [];

let citiesCurrentPage = 1;
let citiesTotalPages = 1;
let citiesIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getCityStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getCountryBadge(countryName) {
    const color = '#3b82f6';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${countryName || '-'}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

// פתיחת טופס הוספת עיר
function openAddCity(countryId = null) {
    window.currentType = 'city';
    window.currentParentId = countryId;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('city', countryId, null);
    }
}

// עריכת עיר
async function editCity(id) {
    window.currentType = 'city';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('city', null, id);
    }
}

// צפייה בעיר - פתיחת כרטיס
async function viewCity(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cityCard', null, id);
    } else {
        editCity(id);
    }
}

// דאבל-קליק על שורת עיר
async function handleCityDoubleClick(city) {
    const cityId = typeof city === 'object' ? (city.id || city.unicId) : city;

    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cityCard', null, cityId);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

// רינדור עמודות מיוחדות - נקרא מ-EntityRenderer
function renderCityColumn(city, column) {
    switch(column.field) {
        case 'isActive':
            return getCityStatusBadge(city.isActive);
        case 'countryNameHe':
            return getCountryBadge(city.countryNameHe || city.country_name);
        default:
            return null; // תן ל-EntityRenderer לטפל
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.citySearch = citySearch;
window.citiesTable = citiesTable;
window.currentCities = currentCities;
window.filterByCountryId = filterByCountryId;

window.getCityStatusBadge = getCityStatusBadge;
window.getCountryBadge = getCountryBadge;

window.openAddCity = openAddCity;
window.editCity = editCity;
window.viewCity = viewCity;
window.handleCityDoubleClick = handleCityDoubleClick;
window.renderCityColumn = renderCityColumn;
