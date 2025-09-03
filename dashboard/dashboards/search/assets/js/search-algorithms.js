/**
 * אלגוריתמי חיפוש מתקדמים לשמות עבריים
 * search/assets/js/search-algorithms.js
 */

class HebrewSearchAlgorithms {
    constructor() {
        // מילון תווים מיוחדים וחלופות
        this.specialCharacters = {
            'ג׳': ['ג', 'גי'],
            'ז׳': ['ז', 'זי'],
            'צ׳': ['צ', 'צי'],
            'ג\'': ['ג', 'גי'],
            'ז\'': ['ז', 'זי'],
            'צ\'': ['צ', 'צי']
        };

        // מילון שגיאות כתיב נפוצות
        this.commonMistakes = {
            // כפל אותיות
            'וו': ['ו'],
            'יי': ['י'],
            'לל': ['ל'],
            'ממ': ['מ'],
            'נן': ['נ'],
            'ננ': ['נ'],
            'סס': ['ס'],
            'פפ': ['פ'],
            'צצ': ['צ'],
            'קק': ['ק'],
            'רר': ['ר'],
            'שש': ['ש'],
            'תת': ['ת'],
            
            // החלפות נפוצות
            'כ': ['ח'],
            'ח': ['כ'],
            'ט': ['ת'],
            'ת': ['ט'],
            'ס': ['ש', 'צ'],
            'ש': ['ס'],
            'צ': ['ס', 'ז'],
            'ק': ['כ'],
            'ב': ['ו'],
            'ו': ['ב'],
            
            // אותיות סופיות
            'ך': ['כ'],
            'ם': ['מ'],
            'ן': ['נ'],
            'ף': ['פ'],
            'ץ': ['צ']
        };

        // שמות משפחה עם וריאציות נפוצות
        this.nameVariations = {
            'כהן': ['כהנ', 'כוהן', 'כאהן', 'קהן'],
            'לוי': ['לויי', 'לווי'],
            'ועקנין': ['וועקנין', 'ואקנין', 'וקנין'],
            'אבישלום': ['אבשלום', 'אבישלם', 'אבשלם'],
            'דהאן': ['דהן', 'דאהן', 'דהאנ'],
            'אברהם': ['אברהמ', 'אברם'],
            'יצחק': ['יצחאק', 'יצאק', 'יצכק'],
            'שמואל': ['שמואיל', 'שמוא', 'שמאל'],
            'ישראל': ['ישראיל', 'ישראלי'],
            'יוסף': ['יוסיף', 'יוספ'],
            'משה': ['מושה', 'מושי', 'מוישה'],
            'דוד': ['דויד', 'דווד', 'דאוד'],
            'חיים': ['חייים', 'חאיים', 'חיימ'],
            'יעקב': ['יעקוב', 'יאקוב', 'יעקב'],
            'בנימין': ['בנימן', 'בנימיני', 'בנימינ'],
            'אליהו': ['אליהוא', 'אליאו', 'אליו'],
            'שלמה': ['שלומה', 'שלמא'],
            'מרדכי': ['מרדכאי', 'מרדקי'],
            'אהרן': ['אהרון', 'אהארן', 'אהרונ'],
            'שאול': ['שאואל', 'סאול'],
            'פרץ': ['פרצ', 'פרס'],
            'עזרא': ['עזרה', 'עזראה'],
            'מזרחי': ['מזרכי', 'מזראחי'],
            'ביטון': ['ביטן', 'ביטונ', 'בטון'],
            'אזולאי': ['אזולאיי', 'אזולי', 'אזולאי'],
            'אוחיון': ['אוחיונ', 'אוחיון', 'אוכיון'],
            'אמסלם': ['אמסלמ', 'אמסלמי'],
            'אלבז': ['אלבאז', 'אלבז'],
            'גבאי': ['גבאיי', 'גבאי'],
            'חדד': ['חדאד', 'חדד'],
            'טולדנו': ['טולידנו', 'טולדנו'],
            'עמר': ['עמאר', 'עומר'],
            'פינטו': ['פינטו', 'פינטא'],
            'אשכנזי': ['אשכנז', 'אשכנזיי'],
            'ספרדי': ['ספרד', 'ספרדיי'],
            'מרוקאי': ['מרוקאיי', 'מרוקו'],
            'תימני': ['תימנ', 'תימניי', 'תימן']
        };

        // פרפיקסים נפוצים בשמות
        this.prefixes = ['בן', 'בר', 'אבו', 'אבי', 'אל'];
    }

    /**
     * חיפוש חכם עם כל האלגוריתמים
     */
    smartSearch(query, database) {
        const results = new Set();
        const searchTerms = this.normalizeQuery(query);
        
        // חיפוש רגיל
        this.basicSearch(searchTerms, database).forEach(r => results.add(r));
        
        // חיפוש עם היפוך סדר
        this.permutationSearch(searchTerms, database).forEach(r => results.add(r));
        
        // חיפוש עם תיקון שגיאות
        this.fuzzySearch(searchTerms, database).forEach(r => results.add(r));
        
        // חיפוש עם וריאציות שמות
        this.variationSearch(searchTerms, database).forEach(r => results.add(r));
        
        // דירוג התוצאות
        return this.rankResults(Array.from(results), searchTerms);
    }

    /**
     * נרמול שאילתת החיפוש
     */
    normalizeQuery(query) {
        // הסרת רווחים מיותרים
        query = query.trim().replace(/\s+/g, ' ');
        
        // פיצול למילים
        return query.split(' ').filter(term => term.length > 0);
    }

    /**
     * חיפוש בסיסי
     */
    basicSearch(searchTerms, database) {
        return database.filter(record => {
            const fullName = `${record.first_name} ${record.last_name}`.toLowerCase();
            return searchTerms.every(term => 
                fullName.includes(term.toLowerCase())
            );
        });
    }

    /**
     * חיפוש עם כל הפרמוטציות של הסדר
     */
    permutationSearch(searchTerms, database) {
        const results = [];
        const permutations = this.getPermutations(searchTerms);
        
        database.forEach(record => {
            const recordNames = this.extractNames(record);
            
            for (let perm of permutations) {
                if (this.matchesPermutation(perm, recordNames)) {
                    results.push(record);
                    break;
                }
            }
        });
        
        return results;
    }

    /**
     * חיפוש עם תיקון שגיאות כתיב
     */
    fuzzySearch(searchTerms, database) {
        const results = [];
        const fuzzyTerms = searchTerms.map(term => this.generateFuzzyVariations(term));
        
        database.forEach(record => {
            const recordText = `${record.first_name} ${record.last_name}`.toLowerCase();
            
            let match = true;
            for (let i = 0; i < fuzzyTerms.length; i++) {
                let termMatch = false;
                for (let variant of fuzzyTerms[i]) {
                    if (recordText.includes(variant.toLowerCase())) {
                        termMatch = true;
                        break;
                    }
                }
                if (!termMatch) {
                    match = false;
                    break;
                }
            }
            
            if (match) {
                results.push(record);
            }
        });
        
        return results;
    }

    /**
     * חיפוש עם וריאציות שמות ידועות
     */
    variationSearch(searchTerms, database) {
        const results = [];
        const expandedTerms = searchTerms.map(term => this.getNameVariations(term));
        
        database.forEach(record => {
            const recordText = `${record.first_name} ${record.last_name}`.toLowerCase();
            
            let match = true;
            for (let variations of expandedTerms) {
                let termMatch = false;
                for (let variant of variations) {
                    if (recordText.includes(variant.toLowerCase())) {
                        termMatch = true;
                        break;
                    }
                }
                if (!termMatch) {
                    match = false;
                    break;
                }
            }
            
            if (match) {
                results.push(record);
            }
        });
        
        return results;
    }

    /**
     * יצירת פרמוטציות
     */
    getPermutations(arr) {
        if (arr.length <= 1) return [arr];
        
        const result = [];
        for (let i = 0; i < arr.length; i++) {
            const current = arr[i];
            const remaining = arr.slice(0, i).concat(arr.slice(i + 1));
            const remainingPerms = this.getPermutations(remaining);
            
            for (let perm of remainingPerms) {
                result.push([current].concat(perm));
            }
        }
        
        return result;
    }

    /**
     * חילוץ שמות מרשומה
     */
    extractNames(record) {
        const names = [];
        
        // פיצול שמות פרטיים מרובים
        if (record.first_name) {
            record.first_name.split(' ').forEach(name => {
                if (name.trim()) names.push(name.trim().toLowerCase());
            });
        }
        
        // הוספת שם משפחה
        if (record.last_name) {
            names.push(record.last_name.toLowerCase());
        }
        
        return names;
    }

    /**
     * בדיקת התאמה לפרמוטציה
     */
    matchesPermutation(permutation, recordNames) {
        // בדיקה אם כל המילים בפרמוטציה קיימות ברשומה
        return permutation.every(term => 
            recordNames.some(name => 
                name.includes(term.toLowerCase()) || 
                term.toLowerCase().includes(name)
            )
        );
    }

    /**
     * יצירת וריאציות פאזיות
     */
    generateFuzzyVariations(term) {
        const variations = new Set([term]);
        
        // החלפת תווים מיוחדים
        for (let [special, replacements] of Object.entries(this.specialCharacters)) {
            if (term.includes(special)) {
                replacements.forEach(rep => {
                    variations.add(term.replace(special, rep));
                });
            }
        }
        
        // תיקון שגיאות נפוצות
        for (let [mistake, corrections] of Object.entries(this.commonMistakes)) {
            if (term.includes(mistake)) {
                corrections.forEach(correction => {
                    variations.add(term.replace(mistake, correction));
                });
            }
        }
        
        // הסרת אותיות כפולות
        variations.add(this.removeDuplicateLetters(term));
        
        // הוספת/הסרת ה' הידיעה
        if (term.startsWith('ה')) {
            variations.add(term.substring(1));
        } else {
            variations.add('ה' + term);
        }
        
        // הוספת/הסרת יו"ד בסוף
        if (term.endsWith('י')) {
            variations.add(term.substring(0, term.length - 1));
        } else {
            variations.add(term + 'י');
        }
        
        return Array.from(variations);
    }

    /**
     * קבלת וריאציות של שמות ידועים
     */
    getNameVariations(term) {
        const variations = new Set([term]);
        const lowerTerm = term.toLowerCase();
        
        // חיפוש במילון הוריאציות
        for (let [name, variants] of Object.entries(this.nameVariations)) {
            if (name === lowerTerm || variants.includes(lowerTerm)) {
                variations.add(name);
                variants.forEach(v => variations.add(v));
            }
        }
        
        return Array.from(variations);
    }

    /**
     * הסרת אותיות כפולות
     */
    removeDuplicateLetters(text) {
        return text.replace(/(.)\1+/g, '$1');
    }

    /**
     * דירוג תוצאות לפי רלוונטיות
     */
    rankResults(results, searchTerms) {
        return results.map(result => {
            let score = 0;
            const fullName = `${result.first_name} ${result.last_name}`.toLowerCase();
            const searchQuery = searchTerms.join(' ').toLowerCase();
            
            // התאמה מדויקת
            if (fullName === searchQuery) {
                score += 100;
            }
            
            // התאמה של כל המילים
            searchTerms.forEach(term => {
                if (fullName.includes(term.toLowerCase())) {
                    score += 10;
                }
            });
            
            // התאמה בתחילת שם
            if (result.first_name.toLowerCase().startsWith(searchTerms[0].toLowerCase())) {
                score += 5;
            }
            
            // התאמה בשם משפחה
            if (searchTerms.some(term => 
                result.last_name.toLowerCase().includes(term.toLowerCase()))) {
                score += 3;
            }
            
            return { ...result, relevanceScore: score };
        }).sort((a, b) => b.relevanceScore - a.relevanceScore);
    }

    /**
     * חיפוש מתקדם עם פרמטרים
     */
    advancedSearch(params, database) {
        let results = [...database];
        
        // סינון לפי שם פרטי
        if (params.first_name) {
            const firstNameVariations = this.generateFuzzyVariations(params.first_name);
            results = results.filter(record => {
                const recordFirstNames = record.first_name.toLowerCase().split(' ');
                return firstNameVariations.some(variant => 
                    recordFirstNames.some(name => 
                        name.includes(variant.toLowerCase()) || 
                        variant.toLowerCase().includes(name)
                    )
                );
            });
        }
        
        // סינון לפי שם משפחה
        if (params.last_name) {
            const lastNameVariations = this.generateFuzzyVariations(params.last_name);
            results = results.filter(record => 
                lastNameVariations.some(variant => 
                    record.last_name.toLowerCase().includes(variant.toLowerCase())
                )
            );
        }
        
        // סינון לפי תאריך
        if (params.date_type === 'range') {
            results = this.filterByDateRange(results, params.from_year, params.to_year);
        } else if (params.date_type === 'estimated') {
            results = this.filterByEstimatedYear(results, params.estimated_year);
        }
        
        // סינון לפי מיקום
        if (params.city) {
            results = this.filterByCity(results, params.city);
        }
        
        if (params.cemetery) {
            results = this.filterByCemetery(results, params.cemetery);
        }
        
        // סינון לפי שמות הורים
        if (params.father_name) {
            const fatherVariations = this.generateFuzzyVariations(params.father_name);
            results = results.filter(record => 
                record.father_name && 
                fatherVariations.some(variant => 
                    record.father_name.toLowerCase().includes(variant.toLowerCase())
                )
            );
        }
        
        if (params.mother_name) {
            const motherVariations = this.generateFuzzyVariations(params.mother_name);
            results = results.filter(record => 
                record.mother_name && 
                motherVariations.some(variant => 
                    record.mother_name.toLowerCase().includes(variant.toLowerCase())
                )
            );
        }
        
        return results;
    }

    /**
     * סינון לפי טווח תאריכים
     */
    filterByDateRange(results, fromYear, toYear) {
        if (!fromYear && !toYear) return results;
        
        return results.filter(record => {
            if (!record.death_date) return false;
            const year = parseInt(record.death_date.substring(0, 4));
            
            if (fromYear && year < parseInt(fromYear)) return false;
            if (toYear && year > parseInt(toYear)) return false;
            
            return true;
        });
    }

    /**
     * סינון לפי שנה משוערת
     */
    filterByEstimatedYear(results, estimatedYear) {
        if (!estimatedYear) return results;
        
        const year = parseInt(estimatedYear);
        const range = 5; // ±5 שנים
        
        return results.filter(record => {
            if (!record.death_date) return false;
            const recordYear = parseInt(record.death_date.substring(0, 4));
            return Math.abs(recordYear - year) <= range;
        });
    }

    /**
     * סינון לפי עיר
     */
    filterByCity(results, city) {
        const cityMap = {
            'tel-aviv': ['תל אביב', 'תא', 'תל-אביב'],
            'jerusalem': ['ירושלים', 'ירושלם'],
            'haifa': ['חיפה', 'חיפא'],
            'beer-sheva': ['באר שבע', 'באר-שבע'],
            'netanya': ['נתניה', 'נתנייה'],
            'rishon': ['ראשון לציון', 'ראשון', 'ראשל"צ'],
            'petah-tikva': ['פתח תקווה', 'פתח תקוה', 'פ"ת'],
            'ashdod': ['אשדוד', 'אשדד'],
            'bnei-brak': ['בני ברק', 'בני-ברק', 'ב"ב'],
            'holon': ['חולון', 'חולן']
        };
        
        const cityVariations = cityMap[city] || [];
        
        return results.filter(record => 
            cityVariations.some(variant => 
                record.burial_location && 
                record.burial_location.includes(variant)
            )
        );
    }

    /**
     * סינון לפי בית עלמין
     */
    filterByCemetery(results, cemetery) {
        const cemeteryMap = {
            'yarkon': 'ירקון',
            'kiryat-shaul': 'קרית שאול',
            'nahalat-yitzhak': 'נחלת יצחק',
            'har-menuchot': 'הר המנוחות',
            'sanhedria': 'סנהדריה',
            'har-hazeitim': 'הר הזיתים',
            'hof-hacarmel': 'חוף הכרמל',
            'segula': 'סגולה'
        };
        
        const cemeteryName = cemeteryMap[cemetery];
        if (!cemeteryName) return results;
        
        return results.filter(record => 
            record.burial_location && 
            record.burial_location.includes(cemeteryName)
        );
    }
}

// יצוא האובייקט
window.SearchAlgorithms = new HebrewSearchAlgorithms();