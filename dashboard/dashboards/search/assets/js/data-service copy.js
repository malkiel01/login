/**
 * שירות נתונים - מנהל את המעבר בין JSON ל-API
 * search/assets/js/data-service.js
 */

class DataService {
    constructor() {
        this.useAPI = false; // ברירת מחדל - JSON
        this.apiEndpoint = '/dashboards/search/api/deceased-search.php';
        // this.apiEndpoint = 'api/deceased-search.php';  // נתיב יחסי
        this.jsonPath = '/search/data/data.json';
        this.cache = null;
        this.cacheTimeout = 5 * 60 * 1000; // 5 דקות
        this.lastCacheTime = null;
    }

    /**
     * החלפת מקור הנתונים
     */
    toggleDataSource() {
        this.useAPI = !this.useAPI;
        this.clearCache();
        return this.useAPI ? 'API' : 'JSON';
    }

    /**
     * קבלת מקור הנתונים הנוכחי
     */
    getCurrentSource() {
        return this.useAPI ? 'API' : 'JSON';
    }

    /**
     * ניקוי המטמון
     */
    clearCache() {
        this.cache = null;
        this.lastCacheTime = null;
    }

    /**
     * חיפוש פשוט
     */
    async simpleSearch(query) {
        const startTime = performance.now();
        
        try {
            let results;
            
            if (this.useAPI) {
                results = await this.searchViaAPI(query);
            } else {
                results = await this.searchViaJSON(query);
            }
            
            const endTime = performance.now();
            const searchTime = ((endTime - startTime) / 1000).toFixed(2);
            
            return {
                success: true,
                data: results,
                searchTime: searchTime,
                source: this.getCurrentSource()
            };
        } catch (error) {
            console.error('Search error:', error);
            return {
                success: false,
                error: error.message,
                data: [],
                source: this.getCurrentSource()
            };
        }
    }

    /**
     * חיפוש מתקדם
     */
    async advancedSearch(params) {
        const startTime = performance.now();
        
        try {
            let results;
            
            if (this.useAPI) {
                results = await this.advancedSearchViaAPI(params);
            } else {
                results = await this.advancedSearchViaJSON(params);
            }
            
            const endTime = performance.now();
            const searchTime = ((endTime - startTime) / 1000).toFixed(2);
            
            return {
                success: true,
                data: results,
                searchTime: searchTime,
                source: this.getCurrentSource()
            };
        } catch (error) {
            console.error('Advanced search error:', error);
            return {
                success: false,
                error: error.message,
                data: [],
                source: this.getCurrentSource()
            };
        }
    }

    /**
     * חיפוש דרך API
     */
    async searchViaAPI(query) {
        const response = await fetch(`${this.apiEndpoint}?q=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-System-ID': 'search-dashboard'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'API request failed');
        }

        return data.data.results || [];
    }

    /**
     * חיפוש מתקדם דרך API
     */
    async advancedSearchViaAPI(params) {
        const response = await fetch(this.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-System-ID': 'search-dashboard'
            },
            credentials: 'same-origin',
            body: JSON.stringify(params)
        });

        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'API request failed');
        }

        return data.data.results || [];
    }

    /**
     * חיפוש דרך JSON
     */
    async searchViaJSON(query) {
        const database = await this.loadJSONData();
        
        // שימוש באלגוריתמי החיפוש החכמים
        if (window.SearchAlgorithms) {
            return window.SearchAlgorithms.smartSearch(query, database);
        }
        
        // חיפוש בסיסי אם האלגוריתמים לא זמינים
        return this.basicJSONSearch(query, database);
    }

    /**
     * חיפוש מתקדם דרך JSON
     */
    async advancedSearchViaJSON(params) {
        const database = await this.loadJSONData();
        
        // שימוש באלגוריתמי החיפוש החכמים
        if (window.SearchAlgorithms) {
            return window.SearchAlgorithms.advancedSearch(params, database);
        }
        
        // חיפוש בסיסי אם האלגוריתמים לא זמינים
        return this.basicAdvancedJSONSearch(params, database);
    }

    /**
     * טעינת נתוני JSON
     */
    async loadJSONData() {
        // בדיקת מטמון
        if (this.cache && this.lastCacheTime) {
            const now = Date.now();
            if (now - this.lastCacheTime < this.cacheTimeout) {
                return this.cache;
            }
        }

        try {
            const response = await fetch(this.jsonPath);
            if (!response.ok) {
                throw new Error(`Failed to load JSON data: ${response.status}`);
            }
            
            const data = await response.json();
            
            // שמירה במטמון
            this.cache = data;
            this.lastCacheTime = Date.now();
            
            return data;
        } catch (error) {
            console.error('Error loading JSON data:', error);
            
            // החזרת נתונים דיפולטיביים אם הטעינה נכשלה
            return this.getDefaultData();
        }
    }

    /**
     * חיפוש בסיסי ב-JSON (fallback)
     */
    basicJSONSearch(query, database) {
        const searchTerms = query.toLowerCase().split(' ').filter(t => t);
        
        return database.filter(record => {
            const fullName = `${record.first_name} ${record.last_name}`.toLowerCase();
            const reverseName = `${record.last_name} ${record.first_name}`.toLowerCase();
            
            return searchTerms.every(term => 
                fullName.includes(term) || reverseName.includes(term)
            );
        });
    }

    /**
     * חיפוש מתקדם בסיסי ב-JSON (fallback)
     */
    basicAdvancedJSONSearch(params, database) {
        let results = [...database];
        
        // סינון לפי שם פרטי
        if (params.first_name) {
            const term = params.first_name.toLowerCase();
            results = results.filter(r => 
                r.first_name.toLowerCase().includes(term)
            );
        }
        
        // סינון לפי שם משפחה
        if (params.last_name) {
            const term = params.last_name.toLowerCase();
            results = results.filter(r => 
                r.last_name.toLowerCase().includes(term)
            );
        }
        
        // סינון לפי שם אב
        if (params.father_name) {
            const term = params.father_name.toLowerCase();
            results = results.filter(r => 
                r.father_name && r.father_name.toLowerCase().includes(term)
            );
        }
        
        // סינון לפי שם אם
        if (params.mother_name) {
            const term = params.mother_name.toLowerCase();
            results = results.filter(r => 
                r.mother_name && r.mother_name.toLowerCase().includes(term)
            );
        }
        
        // סינון לפי תאריך
        if (params.date_type === 'range') {
            if (params.from_year) {
                results = results.filter(r => {
                    const year = parseInt(r.death_date?.substring(0, 4) || 0);
                    return year >= parseInt(params.from_year);
                });
            }
            if (params.to_year) {
                results = results.filter(r => {
                    const year = parseInt(r.death_date?.substring(0, 4) || 0);
                    return year <= parseInt(params.to_year);
                });
            }
        } else if (params.date_type === 'estimated' && params.estimated_year) {
            const targetYear = parseInt(params.estimated_year);
            results = results.filter(r => {
                const year = parseInt(r.death_date?.substring(0, 4) || 0);
                return Math.abs(year - targetYear) <= 5;
            });
        }
        
        return results;
    }

    /**
     * נתונים דיפולטיביים
     */
    getDefaultData() {
        return [
            {
                id: '1',
                first_name: 'משה חיים',
                last_name: 'כהן',
                death_date: '2020-03-15',
                birth_date: '1945-07-20',
                father_name: 'אברהם',
                mother_name: 'שרה',
                burial_location: 'בית עלמין ירקון',
                plot_section: 'חלקה א',
                plot_row: '12',
                plot_number: '45'
            },
            {
                id: '2',
                first_name: 'חיים',
                last_name: 'כהן',
                death_date: '2019-11-08',
                birth_date: '1932-04-15',
                father_name: 'יצחק',
                mother_name: 'רבקה',
                burial_location: 'בית עלמין ירקון',
                plot_section: 'חלקה ב',
                plot_row: '8',
                plot_number: '23'
            },
            {
                id: '3',
                first_name: 'שרה',
                last_name: 'לוי',
                death_date: '2021-06-22',
                birth_date: '1950-09-10',
                father_name: 'יעקב',
                mother_name: 'לאה',
                burial_location: 'בית עלמין כנרת',
                plot_section: 'חלקה ג',
                plot_row: '15',
                plot_number: '67'
            },
            {
                id: '4',
                first_name: 'אברהם יוסף',
                last_name: 'גולדברג',
                death_date: '2018-12-30',
                birth_date: '1940-02-28',
                father_name: 'משה',
                mother_name: 'רחל',
                burial_location: 'בית עלמין השרון',
                plot_section: 'חלקה ד',
                plot_row: '20',
                plot_number: '89'
            },
            {
                id: '5',
                first_name: 'מרים',
                last_name: 'רוזנברג',
                death_date: '2022-04-18',
                birth_date: '1955-11-05',
                father_name: 'דוד',
                mother_name: 'אסתר',
                burial_location: 'בית עלמין ירקון',
                plot_section: 'חלקה ה',
                plot_row: '5',
                plot_number: '12'
            }
        ];
    }

    /**
     * בדיקת זמינות ה-API
     */
    async checkAPIAvailability() {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'OPTIONS',
                headers: {
                    'X-System-ID': 'search-dashboard'
                }
            });
            
            return response.ok;
        } catch (error) {
            console.error('API availability check failed:', error);
            return false;
        }
    }

    /**
     * קבלת סטטיסטיקות
     */
    async getStatistics() {
        try {
            const database = await this.loadJSONData();
            
            return {
                totalRecords: database.length,
                cities: this.extractUniqueCities(database),
                cemeteries: this.extractUniqueCemeteries(database),
                yearRange: this.extractYearRange(database)
            };
        } catch (error) {
            console.error('Error getting statistics:', error);
            return {
                totalRecords: 0,
                cities: [],
                cemeteries: [],
                yearRange: { min: null, max: null }
            };
        }
    }

    /**
     * חילוץ ערים ייחודיות
     */
    extractUniqueCities(database) {
        const cities = new Set();
        database.forEach(record => {
            if (record.burial_location) {
                // ניסיון לחלץ את שם העיר מהמיקום
                const cityMatch = record.burial_location.match(/בית עלמין\s+(.+)/);
                if (cityMatch) {
                    cities.add(cityMatch[1]);
                }
            }
        });
        return Array.from(cities);
    }

    /**
     * חילוץ בתי עלמין ייחודיים
     */
    extractUniqueCemeteries(database) {
        const cemeteries = new Set();
        database.forEach(record => {
            if (record.burial_location) {
                cemeteries.add(record.burial_location);
            }
        });
        return Array.from(cemeteries);
    }

    /**
     * חילוץ טווח שנים
     */
    extractYearRange(database) {
        let minYear = null;
        let maxYear = null;
        
        database.forEach(record => {
            if (record.death_date) {
                const year = parseInt(record.death_date.substring(0, 4));
                if (!minYear || year < minYear) minYear = year;
                if (!maxYear || year > maxYear) maxYear = year;
            }
        });
        
        return { min: minYear, max: maxYear };
    }
}

// יצוא האובייקט
window.DataService = new DataService();