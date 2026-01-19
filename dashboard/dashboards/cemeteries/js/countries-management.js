/*
 * File: dashboards/dashboard/cemeteries/assets/js/countries-management.js
 * Version: 5.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Change Summary:
 * - v5.0.0: מעבר לשיטה החדשה - EntityManager + UniversalSearch + TableManager
 *   ✅ שימוש ב-EntityManager.load('country')
 *   ✅ תמיכה מלאה ב-UniversalSearch
 *   ✅ תמיכה ב-Infinite Scroll
 *   ✅ פונקציות render לעמודות מיוחדות
 */


// ===================================================================
// משתנים גלובליים
// ===================================================================
let currentCountries = [];
let countrySearch = null;
let countriesTable = null;
let editingCountryId = null;

let countriesIsSearchMode = false;
let countriesCurrentQuery = '';
let countriesSearchResults = [];

let countriesCurrentPage = 1;
let countriesTotalPages = 1;
let countriesIsLoadingMore = false;


// ===================================================================
// פונקציות עזר לתגיות סטטוס (Badge Renderers)
// ===================================================================

function getCountryStatusBadge(isActive) {
    const active = isActive == 1;
    const color = active ? '#10b981' : '#ef4444';
    const label = active ? 'פעיל' : 'לא פעיל';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${label}</span>`;
}

function getCitiesCountBadge(count) {
    const color = '#6b7280';
    return `<span style="background: ${color}20; color: ${color}; padding: 4px 12px; border-radius: 20px; font-size: 12px;">${count || 0}</span>`;
}


// ===================================================================
// פונקציות CRUD
// ===================================================================

// פתיחת טופס הוספת מדינה
function openAddCountry() {
    window.currentType = 'country';
    window.currentParentId = null;
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('country', null, null);
    }
}

// עריכת מדינה
async function editCountry(id) {
    window.currentType = 'country';
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('country', null, id);
    }
}

// צפייה במדינה - פתיחת כרטיס
async function viewCountry(id) {
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('countryCard', null, id);
    } else {
        editCountry(id);
    }
}

// דאבל-קליק על שורת מדינה
async function handleCountryDoubleClick(country) {
    const countryId = typeof country === 'object' ? country.unicId : country;

    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('countryCard', null, countryId);
    }
}


// ===================================================================
// פונקציות Render לעמודות מיוחדות
// ===================================================================

// רינדור עמודות מיוחדות - נקרא מ-EntityRenderer
function renderCountryColumn(country, column) {
    switch(column.field) {
        case 'isActive':
            return getCountryStatusBadge(country.isActive);
        case 'cities_count':
            return getCitiesCountBadge(country.cities_count);
        default:
            return null; // תן ל-EntityRenderer לטפל
    }
}


// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.countrySearch = countrySearch;
window.countriesTable = countriesTable;
window.currentCountries = currentCountries;

window.getCountryStatusBadge = getCountryStatusBadge;
window.getCitiesCountBadge = getCitiesCountBadge;

window.openAddCountry = openAddCountry;
window.editCountry = editCountry;
window.viewCountry = viewCountry;
window.handleCountryDoubleClick = handleCountryDoubleClick;
window.renderCountryColumn = renderCountryColumn;
