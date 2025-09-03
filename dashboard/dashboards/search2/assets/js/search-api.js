window.SearchAPI = {
    /**
     * טעינת נתוני JSON
     */
    async loadJSONData() {
        try {
            const response = await fetch('/dashboard/dashboards/search/data/data.json');
            if (!response.ok) {
                throw new Error('Failed to load JSON data');
            }
            const data = await response.json();
            console.log('JSON data loaded:', data.length, 'records');
            return data;
        } catch (error) {
            console.error('Error loading JSON:', error);
            return [];
        }
    },
    
    /**
     * ביצוע חיפוש
     */
    async search(searchInstance, queryOrParams, searchMode) {
        if (!searchInstance) {
            throw new Error('No search configuration loaded');
        }
        
        const startTime = performance.now();
        
        try {
            const jsonData = await this.loadJSONData();
            
            let results = [];
            
            if (searchMode === 'simple') {
                results = searchInstance.simpleSearch(queryOrParams, jsonData);
            } else {
                results = searchInstance.advancedSearch(queryOrParams, jsonData);
            }
            
            // עיצוב התוצאות
            const formattedResults = searchInstance.formatResults(results);
            
            const endTime = performance.now();
            const searchTime = ((endTime - startTime) / 1000).toFixed(2);
            
            console.log(`Search completed: ${formattedResults.length} results in ${searchTime}s`);
            
            return {
                success: true,
                results: formattedResults,
                searchTime: searchTime
            };
            
        } catch (error) {
            console.error('Search error:', error);
            return {
                success: false,
                results: [],
                error: error.message
            };
        }
    },
    
    /**
     * טעינת נתונים מהשרת (לעתיד)
     */
    async searchFromServer(searchType, params) {
        try {
            const response = await fetch('/api/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    searchType: searchType,
                    params: params
                })
            });
            
            if (!response.ok) {
                throw new Error('Server error');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Server search error:', error);
            throw error;
        }
    }
};