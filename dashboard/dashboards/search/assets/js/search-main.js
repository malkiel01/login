// משתנים גלובליים
let currentSearch = null;
let currentSearchType = 'deceased_search';
let currentTab = 'simple';
let currentView = 'cards';

// אתחול
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ConfigurableSearch === 'undefined' || typeof SearchConfig === 'undefined') {
        console.error('Configuration file not loaded!');
        alert('שגיאה בטעינת קובץ הקונפיגורציה. נא לרענן את הדף.');
        return;
    }
    
    SearchUI.init();
    initializeSearch('deceased_search');

    // אתחול המודאל
    DeceasedModal.init();
});

// אתחול החיפוש
function initializeSearch(searchType) {
    currentSearchType = searchType;
    
    try {
        currentSearch = new ConfigurableSearch(searchType);
        SearchUI.updateAdvancedFields(currentSearch);
    } catch (error) {
        console.error('Error initializing search:', error);
        alert('שגיאה באתחול החיפוש: ' + error.message);
    }
}

// פונקציות גלובליות שנקראות מה-HTML
window.switchSearchType = function(searchType) {
    initializeSearch(searchType);
    SearchUI.switchSearchType(searchType);
};

window.switchTab = function(tabName) {
    currentTab = tabName;
    SearchUI.switchTab(tabName);
};

window.switchView = function(viewType) {
    currentView = viewType;
    SearchUI.switchView(viewType);
};

window.performConfigurableSearch = async function() {
    const query = document.getElementById('simple-query').value.trim();
    
    if (!query || query.length < SearchConfig.settings.minSearchLength) {
        alert(`יש להזין לפחות ${SearchConfig.settings.minSearchLength} תווים לחיפוש`);
        return;
    }
    
    SearchUI.showLoading(true);
    
    try {
        const results = await SearchAPI.search(currentSearch, query, 'simple');
        SearchUI.displayResults(results, currentSearch, currentSearchType, currentView);
    } catch (error) {
        console.error('Search error:', error);
        alert('אירעה שגיאה בחיפוש');
    } finally {
        SearchUI.showLoading(false);
    }
};

window.performAdvancedConfigurableSearch = async function() {
    const params = SearchUI.collectAdvancedParams(currentSearch, currentSearchType);
    
    if (Object.keys(params).length === 0) {
        alert('יש למלא לפחות שדה אחד');
        return;
    }
    
    SearchUI.showLoading(true);
    
    try {
        const results = await SearchAPI.search(currentSearch, params, 'advanced');
        SearchUI.displayResults(results, currentSearch, currentSearchType, currentView);
    } catch (error) {
        console.error('Advanced search error:', error);
        alert('אירעה שגיאה בחיפוש');
    } finally {
        SearchUI.showLoading(false);
    }
};

window.clearAdvancedForm = function() {
    SearchUI.clearAdvancedForm();
};