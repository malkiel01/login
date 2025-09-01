/**
 * קובץ קונפיגורציה מרכזי למערכת החיפוש
 * dashboard/dashboards/search/assets/js/search-config.js
 */

const SearchConfig = {
    /**
     * הגדרות חיפושים שונים
     */
    searches: {
        // החיפוש הקיים שלך
        standard: {
            name: 'חיפוש סטנדרטי',
            filters: {}, // אין תנאי סינון מיוחדים
            searchFields: {
                simple: ['c_firstName', 'c_lastName', 'c_fullNameHe'],
                advanced: {
                    firstName: 'c_firstName',
                    lastName: 'c_lastName',
                    fatherName: 'c_nameFather',
                    motherName: 'c_nameMother',
                    cemetery: 'cemeteryNameHe'
                }
            },
            returnFields: [
                'c_firstName',
                'c_lastName',
                'c_nameFather',
                'c_nameMother',
                'graveNameHe',
                'cemeteryNameHe',
                'b_dateDeath',
                'b_dateBurial'
            ]
        },
        
        // החיפוש החדש שלך
        purchased_graves: {
            name: 'קברים שנרכשו',
            // תנאי סינון - רק רשומות שעומדות בתנאים אלו יוחזרו
            filters: {
                required: {
                    'p_clientId': { operator: '!=', value: null },
                    'graveStatus': { operator: '=', value: '2' }
                }
            },
            // שדות לחיפוש
            searchFields: {
                simple: [
                    'c_firstName', 
                    'c_lastName', 
                    'c_fullNameHe',
                    'graveNameHe',
                    'areaGraveNameHe',
                    'plotNameHe',
                    'blockNameHe',
                    'cemeteryNameHe'
                ],
                advanced: {
                    // מיפוי מהממשק לשדות במסד
                    firstName: 'c_firstName',
                    lastName: 'c_lastName',
                    graveName: 'graveNameHe',
                    areaName: 'areaGraveNameHe',
                    lineName: 'lineNameHe',
                    plotName: 'plotNameHe',
                    blockName: 'blockNameHe',
                    cemeteryName: 'cemeteryNameHe'
                }
            },
            // שדות להחזרה בתוצאות
            returnFields: [
                'c_firstName',
                'c_lastName',
                'graveNameHe',
                'areaGraveNameHe',
                'lineNameHe',
                'plotNameHe',
                'blockNameHe',
                'cemeteryNameHe',
                'p_price',
                'p_deedNum',
                'p_purchaseStatus'
            ],
            // שדות להצגה בממשק (עם תרגום)
            displayFields: {
                'c_firstName': 'שם פרטי',
                'c_lastName': 'שם משפחה',
                'graveNameHe': 'מספר קבר',
                'areaGraveNameHe': 'אזור',
                'lineNameHe': 'שורה',
                'plotNameHe': 'חלקה',
                'blockNameHe': 'גוש',
                'cemeteryNameHe': 'בית עלמין',
                'p_price': 'מחיר',
                'p_deedNum': 'מספר שטר',
                'p_purchaseStatus': 'סטטוס רכישה'
            }
        },
        
        // חיפוש נפטרים
        deceased_search: {
            name: 'חיפוש נפטרים',
            filters: {
                required: {
                    'b_clientId': { operator: '!=', value: null },
                    'graveStatus': { operator: '=', value: '3' }
                }
            },
            searchFields: {
                simple: [
                    'c_firstName', 
                    'c_lastName', 
                    'c_fullNameHe',
                    'c_nameFather',
                    'c_nameMother',
                    'graveNameHe',
                    'areaGraveNameHe',
                    'plotNameHe',
                    'blockNameHe',
                    'cemeteryNameHe'
                ],
                advanced: {
                    // פרטי הנפטר
                    firstName: 'c_firstName',
                    lastName: 'c_lastName',
                    fatherName: 'c_nameFather',
                    motherName: 'c_nameMother',
                    // מיקום הקבר
                    graveName: 'graveNameHe',
                    areaName: 'areaGraveNameHe',
                    lineName: 'lineNameHe',
                    plotName: 'plotNameHe',
                    blockName: 'blockNameHe',
                    cemeteryName: 'cemeteryNameHe',
                    // תאריכים
                    deathDate: 'b_dateDeath',
                    burialDate: 'b_dateBurial'
                }
            },
            returnFields: [
                'c_firstName',
                'c_lastName',
                'c_nameFather',
                'c_nameMother',
                'graveNameHe',
                'areaGraveNameHe',
                'lineNameHe',
                'plotNameHe',
                'blockNameHe',
                'cemeteryNameHe',
                'b_dateDeath',
                'b_timeDeath',
                'b_dateBurial',
                'b_timeBurial',
                'c_dateBirth',
                'c_comment'
            ],
            displayFields: {
                'c_firstName': 'שם פרטי',
                'c_lastName': 'שם משפחה',
                'c_nameFather': 'שם האב',
                'c_nameMother': 'שם האם',
                'graveNameHe': 'מספר קבר',
                'areaGraveNameHe': 'אזור',
                'lineNameHe': 'שורה',
                'plotNameHe': 'חלקה',
                'blockNameHe': 'גוש',
                'cemeteryNameHe': 'בית עלמין',
                'b_dateDeath': 'תאריך פטירה',
                'b_timeDeath': 'שעת פטירה',
                'b_dateBurial': 'תאריך קבורה',
                'b_timeBurial': 'שעת קבורה',
                'c_dateBirth': 'תאריך לידה',
                'c_comment': 'הערות'
            }
        },
        
        // קברים פנויים
        available_graves: {
            name: 'קברים פנויים',
            filters: {
                required: {
                    'graveStatus': { operator: '=', value: '1' },
                    'p_clientId': { operator: '=', value: null }
                }
            },
            searchFields: {
                simple: ['cemeteryNameHe', 'blockNameHe', 'plotNameHe'],
                advanced: {
                    cemetery: 'cemeteryNameHe',
                    block: 'blockNameHe',
                    plot: 'plotNameHe',
                    area: 'areaGraveNameHe'
                }
            },
            returnFields: [
                'graveId',
                'graveNameHe',
                'areaGraveNameHe',
                'plotNameHe',
                'blockNameHe',
                'cemeteryNameHe',
                'graveStatus'
            ],
            displayFields: {
                'graveId': 'מזהה קבר',
                'graveNameHe': 'מספר קבר',
                'areaGraveNameHe': 'אזור',
                'plotNameHe': 'חלקה',
                'blockNameHe': 'גוש',
                'cemeteryNameHe': 'בית עלמין',
                'graveStatus': 'סטטוס'
            }
        }
    },
    
    /**
     * מיפוי סטטוסים
     */
    statusMappings: {
        graveStatus: {
            '1': 'פנוי',
            '2': 'תפוס',
            '3': 'שמור',
            '4': 'נרכש'
        },
        p_purchaseStatus: {
            1: 'פעיל',
            2: 'ממתין',
            3: 'הושלם',
            4: 'בוטל'
        }
    },
    
    /**
     * הגדרות כלליות
     */
    settings: {
        defaultLimit: 50,
        maxLimit: 100,
        minSearchLength: 2,
        cacheTimeout: 5 * 60 * 1000, // 5 דקות
        excludeFromGeneralSearch: [ // שדות שלא לכלול בחיפוש כללי
            'graveId',
            'audit_log_id',
            'createDate',
            'updateDate',
            'inactiveDate'
        ]
    }
};

/**
 * מחלקה לניהול החיפוש לפי הקונפיגורציה
 */
class ConfigurableSearch {
    constructor(searchType = 'standard') {
        this.searchType = searchType;
        this.config = SearchConfig.searches[searchType];
        
        if (!this.config) {
            throw new Error(`Search type "${searchType}" not found in configuration`);
        }
    }
    
    /**
     * בדיקה אם רשומה עומדת בתנאי הסינון
     */
    matchesFilters(record) {
        if (!this.config.filters || !this.config.filters.required) {
            return true;
        }
        
        for (const [field, condition] of Object.entries(this.config.filters.required)) {
            const recordValue = record[field];
            const { operator, value } = condition;
            
            switch (operator) {
                case '=':
                    if (recordValue != value) return false;
                    break;
                case '!=':
                    if (recordValue == value) return false;
                    break;
                case '>':
                    if (!(recordValue > value)) return false;
                    break;
                case '<':
                    if (!(recordValue < value)) return false;
                    break;
                case 'contains':
                    if (!recordValue || !recordValue.includes(value)) return false;
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * חיפוש פשוט
     */
    simpleSearch(query, database) {
        const searchTerms = query.toLowerCase().split(' ').filter(t => t);
        const searchFields = this.config.searchFields.simple;
        
        return database.filter(record => {
            // בדיקת תנאי סינון
            if (!this.matchesFilters(record)) {
                return false;
            }
            
            // בניית טקסט לחיפוש
            const searchableText = searchFields
                .map(field => record[field] || '')
                .join(' ')
                .toLowerCase();
            
            // בדיקה שכל המילים נמצאות
            return searchTerms.every(term => searchableText.includes(term));
        });
    }
    
    /**
     * חיפוש מתקדם
     */
    advancedSearch(params, database) {
        const fieldMapping = this.config.searchFields.advanced;
        
        return database.filter(record => {
            // בדיקת תנאי סינון
            if (!this.matchesFilters(record)) {
                return false;
            }
            
            // בדיקת כל פרמטר חיפוש
            for (const [paramKey, dbField] of Object.entries(fieldMapping)) {
                if (params[paramKey]) {
                    const searchValue = params[paramKey].toLowerCase();
                    const recordValue = (record[dbField] || '').toLowerCase();
                    
                    if (!recordValue.includes(searchValue)) {
                        return false;
                    }
                }
            }
            
            return true;
        });
    }
    
    /**
     * עיצוב תוצאות - החזרת רק השדות הרצויים
     */
    formatResults(results) {
        const returnFields = this.config.returnFields;
        
        return results.map(record => {
            const formattedRecord = {};
            
            returnFields.forEach(field => {
                formattedRecord[field] = record[field] || null;
                
                // תרגום סטטוסים אם נדרש
                if (SearchConfig.statusMappings[field]) {
                    const value = record[field];
                    formattedRecord[field + '_display'] = 
                        SearchConfig.statusMappings[field][value] || value;
                }
            });
            
            return formattedRecord;
        });
    }
    
    /**
     * קבלת תוויות לתצוגה
     */
    getDisplayLabels() {
        return this.config.displayFields || {};
    }
    
    /**
     * הכנת פרמטרים ל-API
     */
    prepareApiParams(params) {
        const apiParams = {
            searchType: this.searchType,
            filters: this.config.filters,
            limit: params.limit || SearchConfig.settings.defaultLimit,
            offset: params.offset || 0
        };
        
        // המרת שמות שדות מהממשק ל-API
        const fieldMapping = this.config.searchFields.advanced;
        
        Object.entries(params).forEach(([key, value]) => {
            if (fieldMapping[key]) {
                apiParams[fieldMapping[key]] = value;
            } else if (key !== 'limit' && key !== 'offset') {
                apiParams[key] = value;
            }
        });
        
        return apiParams;
    }
}

// ייצוא גלובלי
window.SearchConfig = SearchConfig;
window.ConfigurableSearch = ConfigurableSearch;