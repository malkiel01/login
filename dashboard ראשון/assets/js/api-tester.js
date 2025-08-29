// dashboard/assets/js/api-tester.js - כלי בדיקת API בקונסול

/**
 * DashboardAPI - כלי לבדיקת ושימוש ב-API של הדשבורד
 * 
 * שימוש בסיסי:
 * api.get('user/10')
 * api.post('activity', {action: 'test'})
 * api.put('user/10/update', {name: 'New Name'})
 * api.delete('user/15')
 */
class DashboardAPI {
    constructor(baseURL = '/dashboard/api/') {
        this.baseURL = baseURL;
        this.headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        // יצירת shortcuts
        this.createShortcuts();
        
        // הודעת ברוכים הבאים
        this.welcome();
    }

    /**
     * GET Request
     */
    async get(endpoint, params = {}) {
        const url = new URL(this.baseURL + endpoint, window.location.origin);
        
        // הוספת query parameters
        Object.keys(params).forEach(key => 
            url.searchParams.append(key, params[key])
        );
        
        console.log(`📡 GET ${url.pathname}${url.search}`);
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: this.headers,
                credentials: 'same-origin'
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * POST Request
     */
    async post(endpoint, data = {}) {
        const url = this.baseURL + endpoint;
        console.log(`📤 POST ${url}`, data);
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: this.headers,
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * PUT Request
     */
    async put(endpoint, data = {}) {
        const url = this.baseURL + endpoint;
        console.log(`✏️ PUT ${url}`, data);
        
        try {
            const response = await fetch(url, {
                method: 'PUT',
                headers: this.headers,
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * DELETE Request
     */
    async delete(endpoint) {
        const url = this.baseURL + endpoint;
        console.log(`🗑️ DELETE ${url}`);
        
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: this.headers,
                credentials: 'same-origin'
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * טיפול בתגובה
     */
    async handleResponse(response) {
        const data = await response.json();
        
        if (response.ok) {
            console.log(`✅ Success (${response.status}):`, data);
            this.displayResult(data, 'success');
        } else {
            console.error(`❌ Error (${response.status}):`, data);
            this.displayResult(data, 'error');
        }
        
        return data;
    }

    /**
     * טיפול בשגיאות
     */
    handleError(error) {
        console.error('🔥 Request failed:', error);
        this.displayResult({
            success: false,
            message: error.message
        }, 'error');
        return null;
    }

    /**
     * הצגת תוצאה יפה בקונסול
     */
    displayResult(data, type = 'info') {
        const styles = {
            success: 'background: #10b981; color: white; padding: 5px 10px; border-radius: 3px;',
            error: 'background: #ef4444; color: white; padding: 5px 10px; border-radius: 3px;',
            info: 'background: #3b82f6; color: white; padding: 5px 10px; border-radius: 3px;'
        };
        
        console.log(`%c${type.toUpperCase()}`, styles[type]);
        
        if (data && typeof data === 'object') {
            console.table(data.data || data);
        }
    }

    /**
     * יצירת shortcuts מהירים
     */
    createShortcuts() {
        // Shortcuts for common endpoints
        this.user = {
            get: (id) => this.get(`user/${id}`),
            update: (id, data) => this.put(`user/${id}/update`, data),
            create: (data) => this.post('user/create', data),
            delete: (id) => this.delete(`user/${id}`),
            activity: (id) => this.get(`user/${id}/activity`)
        };
        
        this.users = {
            list: (params = {}) => this.get('users', params),
            search: (query) => this.get('users', { search: query })
        };
        
        this.stats = {
            get: () => this.get('stats'),
            refresh: () => {
                console.log('🔄 Refreshing stats...');
                return this.get('stats');
            }
        };
        
        this.activity = {
            get: (limit = 20) => this.get('activity', { limit }),
            log: (action, details = null) => this.post('activity', { action, details })
        };
        
        this.session = {
            info: () => this.get('session'),
            validate: () => this.get('session')
        };
        
        this.system = {
            info: () => this.get('system')
        };
    }

    /**
     * פונקציות עזר
     */
    
    // בדיקת כל ה-endpoints
    async testAll() {
        console.log('🧪 Testing all endpoints...\n');
        
        const tests = [
            { name: 'Get Current User', fn: () => this.user.get(window.dashboardData?.currentUser?.id || 1) },
            { name: 'Get Users List', fn: () => this.users.list({ limit: 5 }) },
            { name: 'Get Stats', fn: () => this.stats.get() },
            { name: 'Get Activity', fn: () => this.activity.get(5) },
            { name: 'Get Session', fn: () => this.session.info() }
        ];
        
        for (const test of tests) {
            console.log(`\n📋 Testing: ${test.name}`);
            await test.fn();
            await this.sleep(500); // המתנה בין בדיקות
        }
        
        console.log('\n✨ All tests completed!');
    }

    // המתנה
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // ניקוי קונסול
    clear() {
        console.clear();
        this.welcome();
    }

    // עזרה
    help() {
        console.log('%c📚 Dashboard API Help', 'font-size: 16px; font-weight: bold; color: #667eea;');
        
        const commands = [
            { command: 'api.get(endpoint, params)', desc: 'GET request', example: "api.get('user/1')" },
            { command: 'api.post(endpoint, data)', desc: 'POST request', example: "api.post('activity', {action: 'test'})" },
            { command: 'api.put(endpoint, data)', desc: 'PUT request', example: "api.put('user/1/update', {name: 'John'})" },
            { command: 'api.delete(endpoint)', desc: 'DELETE request', example: "api.delete('user/5')" },
            '',
            { command: '--- Shortcuts ---', desc: '', example: '' },
            { command: 'api.user.get(id)', desc: 'Get user by ID', example: 'api.user.get(10)' },
            { command: 'api.user.update(id, data)', desc: 'Update user', example: "api.user.update(10, {name: 'New'})" },
            { command: 'api.user.create(data)', desc: 'Create user', example: "api.user.create({username: 'test'})" },
            { command: 'api.users.list()', desc: 'List all users', example: 'api.users.list({limit: 10})' },
            { command: 'api.users.search(query)', desc: 'Search users', example: "api.users.search('john')" },
            { command: 'api.stats.get()', desc: 'Get statistics', example: 'api.stats.get()' },
            { command: 'api.activity.get(limit)', desc: 'Get activity log', example: 'api.activity.get(20)' },
            { command: 'api.activity.log(action)', desc: 'Log activity', example: "api.activity.log('test_action')" },
            { command: 'api.session.info()', desc: 'Get session info', example: 'api.session.info()' },
            '',
            { command: '--- Utils ---', desc: '', example: '' },
            { command: 'api.testAll()', desc: 'Test all endpoints', example: 'api.testAll()' },
            { command: 'api.clear()', desc: 'Clear console', example: 'api.clear()' },
            { command: 'api.help()', desc: 'Show this help', example: 'api.help()' }
        ];
        
        console.table(commands);
        
        console.log('\n💡 Tips:');
        console.log('• All methods return Promises - use await or .then()');
        console.log('• Check window.api for quick access');
        console.log('• Press Ctrl+L to clear console');
    }

    // הודעת ברוכים הבאים
    welcome() {
        console.log(
            '%c🚀 Dashboard API Console Tool',
            'font-size: 20px; font-weight: bold; color: #667eea; text-shadow: 2px 2px 4px rgba(0,0,0,0.1);'
        );
        console.log(
            '%cType api.help() for commands list',
            'font-size: 12px; color: #666;'
        );
        console.log('');
    }

    // דוגמאות מהירות
    examples() {
        console.log('%c📖 Quick Examples', 'font-size: 16px; font-weight: bold; color: #10b981;');
        
        const examples = `
// Get user #10
await api.get('user/10')
// או בקיצור:
await api.user.get(10)

// Update user name
await api.user.update(10, {
    name: 'John Doe',
    email: 'john@example.com'
})

// Search users
await api.users.search('admin')

// Get statistics
const stats = await api.stats.get()
console.log('Total users:', stats.data.total_users)

// Log custom activity
await api.activity.log('custom_action', 'Testing from console')

// Get last 5 activities
await api.activity.get(5)

// Test all endpoints
await api.testAll()
        `;
        
        console.log(examples);
    }
}

// יצירת instance גלובלי
window.api = new DashboardAPI();

// הוספת listener לטעינת הדף
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('💫 API Tester ready! Type api.help() to start');
    });
} else {
    console.log('💫 API Tester ready! Type api.help() to start');
}

// קיצורי דרך נוספים
window.API = window.api; // למי שמעדיף CAPS
window.dashboardAPI = window.api; // שם אלטרנטיבי

// Debugging helpers
window.apiDebug = {
    // הצגת כל המשתמשים בטבלה
    showUsers: async () => {
        const result = await api.users.list({ limit: 100 });
        if (result?.data?.users) {
            console.table(result.data.users);
        }
    },
    
    // הצגת סטטיסטיקות בגרף
    showStats: async () => {
        const result = await api.stats.get();
        if (result?.data) {
            console.log('%c📊 Dashboard Statistics', 'font-size: 14px; font-weight: bold;');
            Object.entries(result.data).forEach(([key, value]) => {
                const bar = '█'.repeat(Math.min(value, 50));
                console.log(`${key.padEnd(20)} ${bar} ${value}`);
            });
        }
    },
    
    // מעקב אחר בקשות
    monitor: {
        active: false,
        start: () => {
            apiDebug.monitor.active = true;
            console.log('🔍 Monitoring started...');
        },
        stop: () => {
            apiDebug.monitor.active = false;
            console.log('🔍 Monitoring stopped');
        }
    }
};