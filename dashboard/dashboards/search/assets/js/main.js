/**
 * קובץ JavaScript ראשי למערכת החיפוש
 * search/assets/js/main.js
 */

// אתחול המערכת
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * אתחול האפליקציה
 */
function initializeApp() {
    // טעינת הגדרות
    loadCityAndCemeteryData();
    
    // הגדרת מאזינים לאירועים
    setupEventListeners();
    
    // בדיקת זמינות API
    checkAPIStatus();
    
    // טעינת סטטיסטיקות
    loadStatistics();
}

/**
 * טעינת נתוני ערים ובתי עלמין
 */
function loadCityAndCemeteryData() {
    const citySelect = document.getElementById('adv-city');
    const cemeteryData = {
        'tel-aviv': [
            { value: 'yarkon', text: 'בית עלמין ירקון' },
            { value: 'kiryat-shaul', text: 'בית עלמין קרית שאול' },
            { value: 'nahalat-yitzhak', text: 'בית עלמין נחלת יצחק' }
        ],
        'jerusalem': [
            { value: 'har-menuchot', text: 'הר המנוחות' },
            { value: 'sanhedria', text: 'סנהדריה' },
            { value: 'har-hazeitim', text: 'הר הזיתים' }
        ],
        'haifa': [
            { value: 'hof-hacarmel', text: 'חוף הכרמל' },
            { value: 'nesher', text: 'נשר' }
        ],
        'beer-sheva': [
            { value: 'beer-sheva-old', text: 'בית עלמין ישן' },
            { value: 'beer-sheva-new', text: 'בית עלמין חדש' }
        ],
        'netanya': [
            { value: 'netanya', text: 'בית עלמין נתניה' }
        ],
        'rishon': [
            { value: 'rishon', text: 'בית עלמין ראשון לציון' }
        ],
        'petah-tikva': [
            { value: 'segula', text: 'בית עלמין סגולה' }
        ],
        'ashdod': [
            { value: 'ashdod', text: 'בית עלמין אשדוד' }
        ],
        'bnei-brak': [
            { value: 'ponevezh', text: 'בית עלמין פונביז\'' },
            { value: 'zichron-meir', text: 'בית עלמין זכרון מאיר' }
        ],
        'holon': [
            { value: 'holon', text: 'בית עלמין חולון' }
        ]
    };
    
    // הוספת ערים לרשימה
    const cities = [
        { value: 'tel-aviv', text: 'תל אביב' },
        { value: 'jerusalem', text: 'ירושלים' },
        { value: 'haifa', text: 'חיפה' },
        { value: 'beer-sheva', text: 'באר שבע' },
        { value: 'netanya', text: 'נתניה' },
        { value: 'rishon', text: 'ראשון לציון' },
        { value: 'petah-tikva', text: 'פתח תקווה' },
        { value: 'ashdod', text: 'אשדוד' },
        { value: 'bnei-brak', text: 'בני ברק' },
        { value: 'holon', text: 'חולון' }
    ];
    
    cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.value;
        option.textContent = city.text;
        citySelect.appendChild(option);
    });
    
    // עדכון בתי עלמין בשינוי עיר
    citySelect.addEventListener('change', function() {
        const cemeterySelect = document.getElementById('adv-cemetery');
        cemeterySelect.innerHTML = '<option value="">בחר בית עלמין</option>';
        
        const selectedCity = this.value;
        if (selectedCity && cemeteryData[selectedCity]) {
            cemeteryData[selectedCity].forEach(cemetery => {
                const option = document.createElement('option');
                option.value = cemetery.value;
                option.textContent = cemetery.text;
                cemeterySelect.appendChild(option);
            });
        }
    });
}

/**
 * הגדרת מאזינים לאירועים
 */
function setupEventListeners() {
    // מאזין לרדיו של סוג התאריך
    document.querySelectorAll('input[name="date-type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('date-range-fields').style.display = 'none';
            document.getElementById('date-estimated-fields').style.display = 'none';
            
            if (this.value === 'range') {
                document.getElementById('date-range-fields').style.display = 'block';
            } else if (this.value === 'estimated') {
                document.getElementById('date-estimated-fields').style.display = 'block';
            }
        });
    });
}

/**
 * החלפת מקור נתונים
 */
async function toggleDataSource() {
    const toggle = document.getElementById('dataSourceToggle');
    const currentSourceEl = document.getElementById('currentSource');
    
    const newSource = window.DataService.toggleDataSource();
    
    if (newSource === 'API') {
        // בדיקת זמינות API
        const isAvailable = await window.DataService.checkAPIAvailability();
        if (!isAvailable) {
            alert('שירות ה-API אינו זמין כרגע. ממשיך עם נתוני JSON.');
            window.DataService.toggleDataSource(); // חזרה ל-JSON
            toggle.checked = false;
            currentSourceEl.textContent = 'JSON (בדיקות)';
            return;
        }
        currentSourceEl.textContent = 'API (חי)';
    } else {
        currentSourceEl.textContent = 'JSON (בדיקות)';
    }
}

/**
 * החלפת טאב
 */
function switchTab(tab) {
    // עדכון כפתורי הטאבים
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // עדכון תוכן
    document.querySelectorAll('.search-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tab}-search`).classList.add('active');

    // ניקוי תוצאות
    document.getElementById('results-section').classList.remove('active');
}

/**
 * חיפוש פשוט
 */
async function performSimpleSearch() {
    const query = document.getElementById('simple-query').value.trim();
    
    if (!query) {
        alert('אנא הזן שם לחיפוש');
        return;
    }

    showLoading();

    try {
        const response = await window.DataService.simpleSearch(query);
        
        if (response.success) {
            displayResults(response.data, response.searchTime, response.source);
        } else {
            showError(response.error);
        }
    } catch (error) {
        console.error('Search error:', error);
        showError('אירעה שגיאה בחיפוש. אנא נסה שנית.');
    }
}

/**
 * חיפוש מתקדם
 */
async function performAdvancedSearch() {
    const dateType = document.querySelector('input[name="date-type"]:checked').value;
    
    const searchParams = {
        first_name: document.getElementById('adv-first-name').value.trim(),
        last_name: document.getElementById('adv-last-name').value.trim(),
        father_name: document.getElementById('adv-father-name').value.trim(),
        mother_name: document.getElementById('adv-mother-name').value.trim(),
        city: document.getElementById('adv-city').value,
        cemetery: document.getElementById('adv-cemetery').value,
        date_type: dateType
    };

    // הוספת שדות תאריך לפי הסוג
    if (dateType === 'range') {
        searchParams.from_year = document.getElementById('adv-from-year').value;
        searchParams.to_year = document.getElementById('adv-to-year').value;
    } else if (dateType === 'estimated') {
        searchParams.estimated_year = document.getElementById('adv-estimated-year').value;
    }

    // ולידציה - לפחות שדה אחד
    const hasData = searchParams.first_name || searchParams.last_name || 
                   searchParams.father_name || searchParams.mother_name || 
                   searchParams.city || 
                   (dateType === 'range' && (searchParams.from_year || searchParams.to_year)) ||
                   (dateType === 'estimated' && searchParams.estimated_year);
    
    if (!hasData) {
        alert('אנא מלא לפחות שדה אחד לחיפוש');
        return;
    }

    showLoading();

    try {
        const response = await window.DataService.advancedSearch(searchParams);
        
        if (response.success) {
            displayResults(response.data, response.searchTime, response.source);
        } else {
            showError(response.error);
        }
    } catch (error) {
        console.error('Advanced search error:', error);
        showError('אירעה שגיאה בחיפוש. אנא נסה שנית.');
    }
}

/**
 * הצגת תוצאות
 */
function displayResults(results, searchTime, source) {
    const resultsSection = document.getElementById('results-section');
    const resultsGrid = document.getElementById('results-grid');
    const resultsCount = document.getElementById('results-count');
    const searchTimeEl = document.getElementById('search-time');
    const noResults = document.getElementById('no-results');

    hideLoading();
    resultsSection.classList.add('active');

    // עדכון מידע על החיפוש
    resultsCount.textContent = results.length;
    if (searchTime) {
        searchTimeEl.textContent = `(${searchTime} שניות | ${source})`;
    }

    if (results.length === 0) {
        resultsGrid.innerHTML = '';
        noResults.style.display = 'block';
        return;
    }

    noResults.style.display = 'none';

    // יצירת כרטיסי תוצאות
    resultsGrid.innerHTML = results.map((result, index) => `
        <div class="result-card" onclick="viewDetails('${result.id}')">
            <div class="result-number">${index + 1}</div>
            <div class="result-info">
                <div class="result-name">${result.first_name} ${result.last_name}</div>
                <div class="result-details">
                    <div class="result-detail-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        ${result.birth_date ? formatDate(result.birth_date) + ' - ' : ''}${formatDate(result.death_date)}
                    </div>
                    ${result.father_name || result.mother_name ? `
                        <div class="result-detail-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            ${result.father_name ? `בן ${result.father_name}` : ''}
                            ${result.father_name && result.mother_name ? ' ו' : ''}
                            ${result.mother_name ? `${result.mother_name}` : ''}
                        </div>
                    ` : ''}
                </div>
                ${result.additional_info ? `
                    <div class="result-details" style="margin-top: 5px; font-style: italic;">
                        ${result.additional_info}
                    </div>
                ` : ''}
            </div>
            <div class="result-location">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                ${result.burial_location}
            </div>
        </div>
    `).join('');

    // גלילה לתוצאות
    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * ניקוי טופס מתקדם
 */
function clearAdvancedForm() {
    document.getElementById('adv-first-name').value = '';
    document.getElementById('adv-last-name').value = '';
    document.getElementById('adv-father-name').value = '';
    document.getElementById('adv-mother-name').value = '';
    document.getElementById('adv-from-year').value = '';
    document.getElementById('adv-to-year').value = '';
    document.getElementById('adv-estimated-year').value = '';
    document.getElementById('adv-city').value = '';
    document.getElementById('adv-cemetery').value = '';
    document.getElementById('date-none').checked = true;
    document.getElementById('date-range-fields').style.display = 'none';
    document.getElementById('date-estimated-fields').style.display = 'none';
}

/**
 * הצגת פרטים מלאים
 */
function viewDetails(id) {
    // כאן ניתן להוסיף מודל או מעבר לעמוד פרטים
    alert(`פרטים מלאים של נפטר #${id}\n\nבעתיד יוצג כאן:\n• מידע מפורט\n• תמונות\n• מיקום מדויק\n• אפשרות להזמנת שירותים`);
}

/**
 * פורמט תאריך
 */
function formatDate(dateString) {
    if (!dateString) return 'לא ידוע';
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

/**
 * הצגת טעינה
 */
function showLoading() {
    document.getElementById('results-section').classList.add('active');
    document.getElementById('loading').classList.add('active');
    document.getElementById('results-grid').innerHTML = '';
    document.getElementById('no-results').style.display = 'none';
}

/**
 * הסתרת טעינה
 */
function hideLoading() {
    document.getElementById('loading').classList.remove('active');
}

/**
 * הצגת שגיאה
 */
function showError(message) {
    hideLoading();
    alert(message || 'אירעה שגיאה בחיפוש');
}

/**
 * בדיקת סטטוס API
 */
async function checkAPIStatus() {
    if (window.DataService) {
        const isAvailable = await window.DataService.checkAPIAvailability();
        console.log('API Status:', isAvailable ? 'Available' : 'Not Available');
    }
}

/**
 * טעינת סטטיסטיקות
 */
async function loadStatistics() {
    if (window.DataService) {
        const stats = await window.DataService.getStatistics();
        console.log('Database Statistics:', stats);
    }
}