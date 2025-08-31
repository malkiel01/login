/**
 * הוסף את הקוד הזה ל-main.js או כקובץ נפרד
 * auto-search.js
 */

// משתנים גלובליים לניהול החיפוש האוטומטי
let searchTimeout = null;
const SEARCH_DELAY = 500; // השהייה של 500ms אחרי הפסקת הקלדה
const MIN_SEARCH_LENGTH = 2; // מינימום 2 תווים לחיפוש

/**
 * אתחול חיפוש אוטומטי
 */
function initAutoSearch() {
    const searchInput = document.getElementById('simple-query');
    
    if (!searchInput) return;
    
    // הסרת האירוע הישן של Enter
    searchInput.removeEventListener('keypress', handleEnterKey);
    
    // הוספת מאזין לכל שינוי בשדה
    searchInput.addEventListener('input', handleAutoSearch);
    
    // הוספת מאזין למחיקת התוכן
    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Escape') {
            clearSearch();
        }
    });
    
    console.log('Auto-search initialized');
}

/**
 * טיפול בחיפוש אוטומטי
 */
function handleAutoSearch(event) {
    const query = event.target.value.trim();
    
    // ביטול טיימר קודם אם קיים
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // אם המחרוזת ריקה, נקה תוצאות
    if (query.length === 0) {
        clearSearchResults();
        return;
    }
    
    // אם המחרוזת קצרה מדי, הצג הודעה
    if (query.length < MIN_SEARCH_LENGTH) {
        showSearchHint(`הקלד עוד ${MIN_SEARCH_LENGTH - query.length} תווים לחיפוש`);
        return;
    }
    
    // הצג אינדיקציה שמחפש
    showSearchingIndicator();
    
    // הפעל חיפוש אחרי השהייה
    searchTimeout = setTimeout(() => {
        performAutoSearch(query);
    }, SEARCH_DELAY);
}

/**
 * ביצוע החיפוש האוטומטי
 */
async function performAutoSearch(query) {
    try {
        // הצג סימן טעינה קטן ליד שדה החיפוש
        showInlineLoader();
        
        if (!window.dataService) {
            throw new Error('Data service not initialized');
        }
        
        const results = await window.dataService.simpleSearch(query);
        
        // הסתר סימן טעינה
        hideInlineLoader();
        
        // הצג תוצאות
        displayResults(results);
        
        // הוסף הודעה על מספר התוצאות
        if (results.total > 0) {
            showSearchStatus(`נמצאו ${results.total} תוצאות`);
        } else {
            showSearchStatus('לא נמצאו תוצאות');
        }
        
    } catch (error) {
        console.error('Auto-search error:', error);
        hideInlineLoader();
        showSearchStatus('שגיאה בחיפוש', 'error');
    }
}

/**
 * הצגת אינדיקציה שמחפש
 */
function showSearchingIndicator() {
    const searchInput = document.getElementById('simple-query');
    if (searchInput) {
        searchInput.style.backgroundImage = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'20\' height=\'20\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\' stroke-width=\'2\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'/%3E%3Cpath d=\'m21 21-4.35-4.35\'/%3E%3C/svg%3E")';
        searchInput.style.backgroundPosition = 'left 10px center';
        searchInput.style.backgroundRepeat = 'no-repeat';
        searchInput.style.paddingLeft = '40px';
    }
}

/**
 * הצגת טעינה מינימלית
 */
function showInlineLoader() {
    const searchButton = document.querySelector('.search-button');
    if (searchButton) {
        searchButton.innerHTML = '<div class="mini-spinner"></div>';
        searchButton.disabled = true;
    }
}

/**
 * הסתרת טעינה מינימלית
 */
function hideInlineLoader() {
    const searchButton = document.querySelector('.search-button');
    if (searchButton) {
        searchButton.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
        `;
        searchButton.disabled = false;
    }
}

/**
 * הצגת רמז לחיפוש
 */
function showSearchHint(message) {
    const hintsElement = document.querySelector('.search-hints');
    if (hintsElement) {
        hintsElement.textContent = message;
        hintsElement.style.color = '#666';
    }
}

/**
 * הצגת סטטוס חיפוש
 */
function showSearchStatus(message, type = 'info') {
    const hintsElement = document.querySelector('.search-hints');
    if (hintsElement) {
        hintsElement.textContent = message;
        hintsElement.style.color = type === 'error' ? '#e74c3c' : '#27ae60';
    }
}

/**
 * ניקוי תוצאות חיפוש
 */
function clearSearchResults() {
    const resultsSection = document.getElementById('results-section');
    if (resultsSection) {
        resultsSection.style.display = 'none';
    }
    
    const resultsGrid = document.getElementById('results-grid');
    if (resultsGrid) {
        resultsGrid.innerHTML = '';
    }
    
    showSearchHint('💡 הקלד לפחות 2 תווים והחיפוש יתחיל אוטומטית');
}

/**
 * ניקוי מלא של החיפוש
 */
function clearSearch() {
    const searchInput = document.getElementById('simple-query');
    if (searchInput) {
        searchInput.value = '';
    }
    clearSearchResults();
}

/**
 * CSS נוסף לספינר קטן
 */
const miniSpinnerCSS = `
    <style>
    .mini-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #667eea;
        border-radius: 50%;
        animation: mini-spin 1s linear infinite;
    }
    
    @keyframes mini-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .search-hints {
        transition: color 0.3s ease;
    }
    
    .search-input {
        transition: all 0.3s ease;
    }
    
    .search-input:focus {
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    </style>
`;

// הוספת הסגנונות לדף
if (!document.getElementById('auto-search-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'auto-search-styles';
    styleElement.innerHTML = miniSpinnerCSS;
    document.head.appendChild(styleElement.firstElementChild);
}

// אתחול כשהדף נטען
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAutoSearch);
} else {
    initAutoSearch();
}

// חשיפת הפונקציות לשימוש גלובלי
window.initAutoSearch = initAutoSearch;
window.clearSearch = clearSearch;

console.log('Auto-search module loaded');