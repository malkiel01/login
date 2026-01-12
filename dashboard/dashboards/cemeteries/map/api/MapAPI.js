/**
 * Map API Client - ממשק API מבוסס Promise
 * מספק גישה נקייה ומודרנית ל-API של המפות
 */

export class MapAPI {
    constructor(baseUrl = '../api/map-api.php') {
        this.baseUrl = baseUrl;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    /**
     * בקשת GET כללית
     */
    async get(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = `${this.baseUrl}?${queryString}`;

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: this.defaultHeaders
            });

            return await this.handleResponse(response);
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * בקשת POST כללית
     */
    async post(action, data = {}) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify({ action, ...data })
            });

            return await this.handleResponse(response);
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * טעינת נתוני מפה לישות
     * @param {string} entityType - סוג הישות (cemetery, block, plot, etc.)
     * @param {string} entityId - מזהה הישות (unicId)
     * @param {boolean} includeChildren - האם לכלול ישויות בנות
     * @returns {Promise<Object>} נתוני המפה
     */
    async loadMap(entityType, entityId, includeChildren = true) {
        return await this.get({
            type: entityType,
            id: entityId,
            children: includeChildren ? '1' : undefined
        });
    }

    /**
     * שמירת מפה שלמה (כולל כל הפוליגונים והגדרות)
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @param {Object} mapData - נתוני המפה
     * @returns {Promise<Object>}
     */
    async saveMap(entityType, entityId, mapData) {
        return await this.post('saveMap', {
            entityType,
            entityId,
            mapData
        });
    }

    /**
     * שמירת פוליגון בודד
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @param {Object} polygonData - נתוני הפוליגון
     * @returns {Promise<Object>}
     */
    async savePolygon(entityType, entityId, polygonData) {
        return await this.post('savePolygon', {
            entityType,
            entityId,
            polygon: polygonData
        });
    }

    /**
     * מחיקת פוליגון
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<Object>}
     */
    async deletePolygon(entityType, entityId) {
        return await this.post('deletePolygon', {
            entityType,
            entityId
        });
    }

    /**
     * העלאת תמונת רקע
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @param {File} file - קובץ התמונה
     * @param {Object} settings - הגדרות נוספות
     * @returns {Promise<Object>}
     */
    async uploadBackground(entityType, entityId, file, settings = {}) {
        const formData = new FormData();
        // Note: No action needed - PHP detects file upload via $_FILES['backgroundImage']
        formData.append('entityType', entityType);
        formData.append('entityId', entityId);
        formData.append('backgroundImage', file);
        formData.append('settings', JSON.stringify(settings));

        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                body: formData
                // לא שולחים Content-Type כי הדפדפן ימלא אותו אוטומטית עם boundary
            });

            return await this.handleResponse(response);
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * מחיקת תמונת רקע
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<Object>}
     */
    async deleteBackground(entityType, entityId) {
        return await this.post('deleteBackground', {
            entityType,
            entityId
        });
    }

    /**
     * שמירת הגדרות מפה
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @param {Object} settings - הגדרות המפה
     * @returns {Promise<Object>}
     */
    async saveSettings(entityType, entityId, settings) {
        return await this.post('saveSettings', {
            entityType,
            entityId,
            settings
        });
    }

    /**
     * שמירת נתוני Canvas מלאים (Fabric.js)
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @param {Object} canvasData - נתוני Canvas מ-toJSON()
     * @returns {Promise<Object>}
     */
    async saveCanvasData(entityType, entityId, canvasData) {
        return await this.post('saveCanvasData', {
            entityType,
            entityId,
            canvasData
        });
    }

    /**
     * טעינת גבול הורה (לאילוצי ציור)
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<Object>}
     */
    async loadParentBoundary(entityType, entityId) {
        return await this.get({
            action: 'getParentBoundary',
            type: entityType,
            id: entityId
        });
    }

    /**
     * בדיקת קיום ישות
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<boolean>}
     */
    async entityExists(entityType, entityId) {
        try {
            const result = await this.get({
                action: 'checkEntity',
                type: entityType,
                id: entityId
            });
            return result.exists === true;
        } catch (error) {
            return false;
        }
    }

    /**
     * טיפול בתגובת השרת
     */
    async handleResponse(response) {
        if (!response.ok) {
            const error = await response.text();
            throw new Error(`HTTP Error ${response.status}: ${error}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        return data;
    }

    /**
     * טיפול בשגיאות
     */
    handleError(error) {
        console.error('Map API Error:', error);

        if (error.name === 'TypeError' && error.message === 'Failed to fetch') {
            return new Error('שגיאת רשת: לא ניתן להתחבר לשרת');
        }

        if (error instanceof Error) {
            return error;
        }

        return new Error('שגיאה לא ידועה');
    }
}

/**
 * Entity API - פעולות ספציפיות לישויות
 */
export class EntityAPI extends MapAPI {
    /**
     * קבלת רשימת כל הישויות לפי סוג
     * @param {string} entityType - סוג הישות (cemetery, block, plot, etc.)
     * @returns {Promise<Array>} - מערך של ישויות עם {unicId, name}
     */
    async getEntitiesByType(entityType) {
        const result = await this.get({
            action: 'listEntities',
            type: entityType
        });
        return result.entities || [];
    }

    /**
     * קבלת רשימת ישויות בנות
     * @param {string} parentType - סוג ישות ההורה
     * @param {string} parentId - מזהה ההורה
     * @param {string} childType - סוג הישות הבת
     * @returns {Promise<Array>}
     */
    async getChildren(parentType, parentId, childType) {
        return await this.get({
            action: 'getChildren',
            parentType,
            parentId,
            childType
        });
    }

    /**
     * קבלת נתוני הורה
     * @param {string} childType - סוג הישות
     * @param {string} childId - מזהה הישות
     * @returns {Promise<Object>}
     */
    async getParent(childType, childId) {
        return await this.get({
            action: 'getParent',
            type: childType,
            id: childId
        });
    }

    /**
     * קבלת שרשרת הורים (breadcrumb)
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<Array>}
     */
    async getBreadcrumb(entityType, entityId) {
        return await this.get({
            action: 'getBreadcrumb',
            type: entityType,
            id: entityId
        });
    }

    /**
     * חיפוש ישויות
     * @param {string} entityType - סוג הישות
     * @param {string} searchTerm - ביטוי לחיפוש
     * @param {Object} filters - פילטרים נוספים
     * @returns {Promise<Array>}
     */
    async searchEntities(entityType, searchTerm, filters = {}) {
        return await this.post('search', {
            entityType,
            searchTerm,
            filters
        });
    }
}

/**
 * אינסטנסים גלובליים (ברירת מחדל)
 */
export const mapAPI = new MapAPI();
export const entityAPI = new EntityAPI();
