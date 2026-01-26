/**
 * Quick Share - שיתוף מהיר לפריטים אחרונים
 * דומה לשיתוף המהיר בוואטסאפ
 *
 * @version 1.0.0
 */

class QuickShare {
    constructor() {
        this.apiUrl = '/api/recent/items.php';
        this.recentItems = [];
        this.maxRecentItems = 8;
        this.cacheKey = 'quick_share_recent';
        this.cacheExpiry = 5 * 60 * 1000; // 5 דקות

        this.init();
    }

    /**
     * אתחול
     */
    async init() {
        this.loadFromCache();
        await this.fetchRecentItems();
    }

    /**
     * טעינה מ-cache
     */
    loadFromCache() {
        try {
            const cached = localStorage.getItem(this.cacheKey);
            if (cached) {
                const data = JSON.parse(cached);
                if (Date.now() - data.timestamp < this.cacheExpiry) {
                    this.recentItems = data.items;
                }
            }
        } catch (e) {
            console.warn('[QuickShare] Cache load error:', e);
        }
    }

    /**
     * שמירה ל-cache
     */
    saveToCache() {
        try {
            localStorage.setItem(this.cacheKey, JSON.stringify({
                items: this.recentItems,
                timestamp: Date.now()
            }));
        } catch (e) {
            console.warn('[QuickShare] Cache save error:', e);
        }
    }

    /**
     * טעינת פריטים אחרונים מהשרת
     */
    async fetchRecentItems() {
        try {
            const response = await fetch(`${this.apiUrl}?action=get&limit=${this.maxRecentItems}`, {
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Failed to fetch recent items');
            }

            const data = await response.json();

            if (data.success) {
                this.recentItems = data.items;
                this.saveToCache();
            }

            return this.recentItems;
        } catch (error) {
            console.error('[QuickShare] Fetch error:', error);
            return this.recentItems; // החזר את ה-cache אם יש
        }
    }

    /**
     * קבלת פריטים אחרונים
     */
    getRecentItems() {
        return this.recentItems;
    }

    /**
     * שיתוף מהיר לפריט
     *
     * @param {Object} item - הפריט לשיתוף אליו
     * @param {Object} shareData - הנתונים לשיתוף
     * @returns {Promise<Object>}
     */
    async quickShareTo(item, shareData) {
        try {
            const formData = new FormData();
            formData.append('item_id', item.id);
            formData.append('item_type', item.type);
            formData.append('title', shareData.title || '');
            formData.append('text', shareData.text || '');
            formData.append('url', shareData.url || '');

            if (shareData.files) {
                formData.append('shared_files', JSON.stringify(shareData.files));
            }

            // קבע את ה-endpoint לפי סוג הפריט
            const endpoint = this.getEndpointForType(item.type);

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const result = await response.json();

            if (result.success) {
                // עדכן את זמן השימוש האחרון
                await this.touchItem(item.id, item.type);

                // רשום בהיסטוריה
                await this.logShare(item.id, item.type, 'quick_share');
            }

            return result;
        } catch (error) {
            console.error('[QuickShare] Share error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * קבלת endpoint לפי סוג פריט
     */
    getEndpointForType(type) {
        const endpoints = {
            'grave': '/api/graves/attach.php',
            'note': '/api/notes/add.php',
            'file': '/api/files/upload.php',
            'shopping': '/api/shopping/add.php'
        };

        return endpoints[type] || '/api/items/add.php';
    }

    /**
     * עדכון זמן שימוש אחרון
     */
    async touchItem(itemId, itemType) {
        try {
            await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'touch',
                    item_id: itemId,
                    item_type: itemType
                }),
                credentials: 'include'
            });
        } catch (e) {
            // לא קריטי
        }
    }

    /**
     * רישום שיתוף בהיסטוריה
     */
    async logShare(itemId, itemType, method) {
        try {
            await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'log_share',
                    item_id: itemId,
                    item_type: itemType,
                    share_method: method
                }),
                credentials: 'include'
            });
        } catch (e) {
            // לא קריטי
        }
    }

    /**
     * קבלת אייקון לפריט
     */
    getItemIcon(item) {
        const icons = {
            grave: `<svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>`,
            note: `<svg viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>`,
            file: `<svg viewBox="0 0 24 24"><path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>`,
            image: `<svg viewBox="0 0 24 24"><path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>`,
            pdf: `<svg viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5c0 .83-.67 1.5-1.5 1.5H7v2H5.5V9H8c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V9H13c.83 0 1.5.67 1.5 1.5v3zm4-3H17v1h1.5v1H17v2h-1.5V9h3v1.5z"/></svg>`,
            shopping: `<svg viewBox="0 0 24 24"><path fill="currentColor" d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>`
        };

        return icons[item.icon] || icons[item.type] || icons.file;
    }
}

// יצירת instance גלובלי
window.quickShare = new QuickShare();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = QuickShare;
}
