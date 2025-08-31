/**
 * Data Service - שירות נתונים למערכת חיפוש נפטרים
 * מותאם למבנה הנתונים החדש
 */

class DataService {
    constructor() {
        // this.apiEndpoint = '/search/api/deceased-search.php';
        // this.apiEndpoint = '/dashboards/search/api/deceased-search.php';
        this.apiEndpoint = window.searchConfig.api.searchEndpoint;
        this.dataSource = localStorage.getItem('dataSource') || 'json';
        this.cache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 דקות
    }

    /**
     * שינוי מקור הנתונים
     */
    setDataSource(source) {
        this.dataSource = source;
        localStorage.setItem('dataSource', source);
        this.clearCache();
    }

    /**
     * חיפוש פשוט
     */
    async simpleSearch(query, limit = 50, offset = 0) {
        if (!query || query.trim().length < 2) {
            throw new Error('יש להזין לפחות 2 תווים לחיפוש');
        }

        const cacheKey = `simple_${query}_${limit}_${offset}`;
        
        // בדיקת cache
        if (this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.data;
            }
        }

        try {
            const params = new URLSearchParams({
                action: 'search',
                q: query.trim(),
                limit: limit,
                offset: offset
            });

            const response = await fetch(`${this.apiEndpoint}?${params}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Data-Source': this.dataSource
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'שגיאה בחיפוש');
            }

            // שמירה ב-cache
            this.cache.set(cacheKey, {
                data: result.data,
                timestamp: Date.now()
            });

            return this.formatSearchResults(result.data);

        } catch (error) {
            console.error('Search error:', error);
            throw error;
        }
    }

    /**
     * חיפוש מתקדם
     */
    async advancedSearch(params) {
        const searchParams = this.prepareAdvancedSearchParams(params);
        
        try {
            const response = await fetch(this.apiEndpoint + '?action=search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Data-Source': this.dataSource
                },
                body: JSON.stringify(searchParams)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'שגיאה בחיפוש מתקדם');
            }

            return this.formatSearchResults(result.data);

        } catch (error) {
            console.error('Advanced search error:', error);
            throw error;
        }
    }

    /**
     * הכנת פרמטרים לחיפוש מתקדם
     */
    prepareAdvancedSearchParams(params) {
        const searchParams = {
            limit: params.limit || 50,
            offset: params.offset || 0
        };

        // שמות
        if (params.firstName) searchParams.first_name = params.firstName;
        if (params.lastName) searchParams.last_name = params.lastName;
        if (params.fatherName) searchParams.father_name = params.fatherName;
        if (params.motherName) searchParams.mother_name = params.motherName;

        // תאריכים
        if (params.dateType === 'range') {
            if (params.fromYear) {
                searchParams.from_date = `${params.fromYear}-01-01`;
            }
            if (params.toYear) {
                searchParams.to_date = `${params.toYear}-12-31`;
            }
        } else if (params.dateType === 'estimated' && params.estimatedYear) {
            const year = parseInt(params.estimatedYear);
            searchParams.from_date = `${year - 5}-01-01`;
            searchParams.to_date = `${year + 5}-12-31`;
        } else if (params.deathDate) {
            searchParams.death_date = params.deathDate;
        }

        // מיקום
        if (params.cemetery) searchParams.cemetery = params.cemetery;
        if (params.plot) searchParams.plot = params.plot;
        if (params.city) searchParams.city = params.city;

        return searchParams;
    }

    /**
     * עיצוב תוצאות החיפוש למבנה אחיד
     */
    formatSearchResults(data) {
        if (!data) return { total: 0, results: [] };

        const results = data.data || data.results || data;
        const total = data.total || results.length;

        // אם התוצאות כבר מעוצבות
        if (results.length > 0 && results[0].grave) {
            return { total, results };
        }

        // עיצוב תוצאות לא מעוצבות
        const formattedResults = results.map(record => this.formatSingleRecord(record));
        
        return {
            total: total,
            results: formattedResults
        };
    }

    /**
     * עיצוב רשומה בודדת
     */
    formatSingleRecord(record) {
        // אם הרשומה כבר מעוצבת
        if (record.grave && record.deceased) {
            return record;
        }

        // עיצוב רשומה גולמית
        return {
            grave: {
                id: record.graveId || record.id,
                unicId: record.graveUnicId,
                name: record.graveNameHe,
                area: record.areaGraveNameHe,
                line: record.lineNameHe,
                plot: record.plotNameHe,
                block: record.blockNameHe,
                cemetery: record.cemeteryNameHe,
                status: this.translateGraveStatus(record.graveStatus),
                location: this.buildFullLocation(record)
            },
            deceased: {
                id: record.c_customerId,
                firstName: record.c_firstName || record.first_name,
                lastName: record.c_lastName || record.last_name,
                fullName: record.c_fullNameHe || `${record.c_firstName || ''} ${record.c_lastName || ''}`.trim(),
                fatherName: record.c_nameFather || record.father_name,
                motherName: record.c_nameMother || record.mother_name,
                birthDate: record.c_dateBirth || record.birth_date,
                birthDateHe: record.c_dateBirthHe,
                gender: record.c_gender,
                address: record.c_address,
                phone: record.c_phone,
                mobile: record.c_phoneMobile
            },
            burial: {
                id: record.b_burialId,
                serialNumber: record.b_serialBurialId,
                deathDate: record.b_dateDeath || record.death_date,
                deathTime: record.b_timeDeath,
                burialDate: record.b_dateBurial || record.burial_date,
                burialTime: record.b_timeBurial,
                deathPlace: record.b_placeDeath,
                burialLicense: record.b_buriaLicense
            },
            purchase: {
                serialNumber: record.p_serialPurchaseId,
                deedNumber: record.p_deedNum,
                status: this.translatePurchaseStatus(record.p_purchaseStatus),
                price: record.p_price
            }
        };
    }

    /**
     * בניית מיקום מלא
     */
    buildFullLocation(record) {
        const parts = [];
        
        if (record.cemeteryNameHe) parts.push(record.cemeteryNameHe);
        if (record.blockNameHe) parts.push(`גוש: ${record.blockNameHe}`);
        if (record.plotNameHe) parts.push(`חלקה: ${record.plotNameHe}`);
        if (record.lineNameHe && record.lineNameHe !== '0') parts.push(`שורה: ${record.lineNameHe}`);
        if (record.areaGraveNameHe) parts.push(`אזור: ${record.areaGraveNameHe}`);
        if (record.graveNameHe) parts.push(`קבר: ${record.graveNameHe}`);
        
        return parts.join(', ');
    }

    /**
     * תרגום סטטוס קבר
     */
    translateGraveStatus(status) {
        const statuses = {
            '1': 'פנוי',
            '2': 'תפוס',
            '3': 'שמור',
            '4': 'נרכש'
        };
        return statuses[status] || 'לא ידוע';
    }

    /**
     * תרגום סטטוס רכישה
     */
    translatePurchaseStatus(status) {
        const statuses = {
            1: 'פעיל',
            2: 'ממתין',
            3: 'הושלם',
            4: 'בוטל'
        };
        return statuses[status] || 'לא ידוע';
    }

    /**
     * בדיקת חיבור למערכת
     */
    async testConnection() {
        try {
            const response = await fetch(this.apiEndpoint + '?action=test', {
                headers: {
                    'X-Data-Source': this.dataSource
                }
            });
            
            const result = await response.json();
            return result.success;
        } catch (error) {
            console.error('Connection test failed:', error);
            return false;
        }
    }

    /**
     * קבלת מידע על המערכת
     */
    async getSystemInfo() {
        try {
            const response = await fetch(this.apiEndpoint + '?action=info', {
                headers: {
                    'X-Data-Source': this.dataSource
                }
            });
            
            const result = await response.json();
            return result.data;
        } catch (error) {
            console.error('Failed to get system info:', error);
            return null;
        }
    }

    /**
     * ניקוי cache
     */
    clearCache() {
        this.cache.clear();
    }

    /**
     * עיצוב תאריך לתצוגה
     */
    formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return '';
        
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            return date.toLocaleDateString('he-IL', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        } catch (error) {
            return dateString;
        }
    }

    /**
     * עיצוב שעה לתצוגה
     */
    formatTime(timeString) {
        if (!timeString || timeString === '00:00:00') return '';
        
        try {
            const [hours, minutes] = timeString.split(':');
            return `${hours}:${minutes}`;
        } catch (error) {
            return timeString;
        }
    }
}

// יצירת instance גלובלי
window.dataService = new DataService();