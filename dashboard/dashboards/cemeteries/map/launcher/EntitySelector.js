/**
 * Entity Selector - טעינה והצגה דינמית של ישויות
 * Version: 1.0.0
 *
 * מחלקה לניהול בחירת ישויות (בית עלמין, גוש, חלקה, אחוזת קבר)
 * Usage:
 *   const selector = new EntitySelector({ apiEndpoint: 'api/map-api.php' });
 *   await selector.loadAndRender('cemetery', containerElement);
 */

export class EntitySelector {
    constructor(config = {}) {
        this.apiEndpoint = config.apiEndpoint || 'api/map-api.php';
        this.currentEntities = [];
    }

    /**
     * טעינת ישויות לפי סוג
     * @param {string} entityType - סוג הישות (cemetery/block/plot/areaGrave)
     * @returns {Promise<Array>} רשימת ישויות
     */
    async loadEntitiesByType(entityType) {
        if (!entityType) {
            return [];
        }

        try {
            const response = await fetch(`${this.apiEndpoint}?action=listEntities&type=${entityType}`);
            const data = await response.json();

            if (data.success && data.entities) {
                this.currentEntities = data.entities.map(entity => ({
                    id: entity.unicId,
                    name: entity.name || entity.unicId,
                    type: entityType,
                    raw: entity // שמירת הנתונים המקוריים
                }));
                return this.currentEntities;
            } else {
                throw new Error(data.error || 'שגיאה בטעינת הישויות');
            }
        } catch (error) {
            console.error('Error loading entities:', error);
            throw error;
        }
    }

    /**
     * רינדור dropdown עם הישויות
     * @param {HTMLSelectElement} selectElement - אלמנט ה-select
     * @param {Array} entities - רשימת ישויות
     * @param {Object} options - אפשרויות נוספות
     */
    renderDropdown(selectElement, entities, options = {}) {
        const defaultOption = options.defaultOption || '-- בחר ישות --';
        const emptyMessage = options.emptyMessage || '-- לא נמצאו ישויות --';

        // איפוס
        selectElement.innerHTML = '';

        // אופציה ברירת מחדל
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = defaultOption;
        selectElement.appendChild(defaultOpt);

        // מילוי עם ישויות
        if (entities && entities.length > 0) {
            entities.forEach(entity => {
                const option = document.createElement('option');
                option.value = entity.id;
                option.textContent = entity.name;
                option.dataset.type = entity.type;
                selectElement.appendChild(option);
            });
            selectElement.disabled = false;
        } else {
            const emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = emptyMessage;
            selectElement.appendChild(emptyOpt);
            selectElement.disabled = true;
        }
    }

    /**
     * טעינה ורינדור בפעולה אחת
     * @param {string} entityType - סוג הישות
     * @param {HTMLSelectElement} selectElement - אלמנט ה-select
     * @param {HTMLElement} loadingIndicator - אינדיקטור טעינה (אופציונלי)
     * @param {Object} options - אפשרויות נוספות
     */
    async loadAndRender(entityType, selectElement, loadingIndicator = null, options = {}) {
        // איפוס
        selectElement.innerHTML = '<option value="">-- בחר ישות --</option>';
        selectElement.disabled = true;

        if (!entityType) {
            selectElement.innerHTML = '<option value="">-- תחילה בחר סוג ישות --</option>';
            return;
        }

        // הצגת אינדיקטור טעינה
        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }

        try {
            const entities = await this.loadEntitiesByType(entityType);
            this.renderDropdown(selectElement, entities, options);
        } catch (error) {
            selectElement.innerHTML = '<option value="">-- שגיאה בטעינה --</option>';
            throw error;
        } finally {
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        }
    }

    /**
     * קבלת ישות לפי ID
     * @param {string} entityId - מזהה הישות
     * @returns {Object|null} הישות או null
     */
    getEntityById(entityId) {
        return this.currentEntities.find(e => e.id === entityId) || null;
    }

    /**
     * איפוס המצב
     */
    reset() {
        this.currentEntities = [];
    }
}
