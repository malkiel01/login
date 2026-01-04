/**
 * History Manager - ניהול היסטוריה (Undo/Redo)
 * מאפשר שמירת מצבי Canvas וחזרה אחורה/קדימה
 */

import { HISTORY_SETTINGS } from '../config/EntityConfig.js';

export class HistoryManager {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.states = [];
        this.currentIndex = -1;
        this.maxStates = options.maxStates || HISTORY_SETTINGS.maxStates;
        this.saveDebounce = options.saveDebounce || HISTORY_SETTINGS.saveDebounce;
        this.saveTimeout = null;
        this.isRestoring = false;
        this.enabled = true;

        this.setupListeners();
    }

    /**
     * הגדרת מאזינים לשינויים ב-Canvas
     */
    setupListeners() {
        if (!this.canvas) return;

        const events = [
            'object:added',
            'object:removed',
            'object:modified',
            'object:rotated',
            'object:scaled',
            'object:moved'
        ];

        events.forEach(event => {
            this.canvas.on(event, () => {
                if (!this.isRestoring && this.enabled) {
                    this.debouncedSave();
                }
            });
        });
    }

    /**
     * שמירה עם debounce (למנוע שמירות מיותרות)
     */
    debouncedSave() {
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }

        this.saveTimeout = setTimeout(() => {
            this.saveState();
        }, this.saveDebounce);
    }

    /**
     * שמירת מצב נוכחי
     * @param {Object} metadata - מטא-דאטה נוספת לשמירה עם המצב
     */
    saveState(metadata = {}) {
        if (!this.canvas || this.isRestoring || !this.enabled) return;

        try {
            const state = {
                canvasData: this.canvas.toJSON([
                    'selectable',
                    'evented',
                    'entityType',
                    'entityId',
                    'isBackground',
                    'isBoundary',
                    'isDrawingPoint',
                    'clipPath'
                ]),
                timestamp: Date.now(),
                metadata
            };

            // אם יש מצבים אחרי האינדקס הנוכחי, נמחק אותם
            if (this.currentIndex < this.states.length - 1) {
                this.states = this.states.slice(0, this.currentIndex + 1);
            }

            // הוספת המצב החדש
            this.states.push(state);
            this.currentIndex++;

            // הגבלת מספר המצבים
            if (this.states.length > this.maxStates) {
                this.states.shift();
                this.currentIndex--;
            }

            this.triggerEvent('state:saved', state);
            return true;
        } catch (error) {
            console.error('Error saving state:', error);
            return false;
        }
    }

    /**
     * ביטול פעולה אחרונה (Undo)
     */
    undo() {
        if (!this.canUndo()) return false;

        this.currentIndex--;
        return this.restoreState(this.currentIndex);
    }

    /**
     * ביצוע מחדש (Redo)
     */
    redo() {
        if (!this.canRedo()) return false;

        this.currentIndex++;
        return this.restoreState(this.currentIndex);
    }

    /**
     * שחזור מצב לפי אינדקס
     */
    restoreState(index) {
        if (index < 0 || index >= this.states.length) return false;

        try {
            this.isRestoring = true;
            const state = this.states[index];

            this.canvas.clear();
            this.canvas.loadFromJSON(state.canvasData, () => {
                this.canvas.renderAll();
                this.isRestoring = false;
                this.triggerEvent('state:restored', state);
            });

            return true;
        } catch (error) {
            console.error('Error restoring state:', error);
            this.isRestoring = false;
            return false;
        }
    }

    /**
     * בדיקה אם ניתן לבצע Undo
     */
    canUndo() {
        return this.currentIndex > 0;
    }

    /**
     * בדיקה אם ניתן לבצע Redo
     */
    canRedo() {
        return this.currentIndex < this.states.length - 1;
    }

    /**
     * קבלת מצב נוכחי
     */
    getCurrentState() {
        if (this.currentIndex < 0 || this.currentIndex >= this.states.length) {
            return null;
        }
        return this.states[this.currentIndex];
    }

    /**
     * קבלת כל המצבים
     */
    getAllStates() {
        return [...this.states];
    }

    /**
     * ניקוי כל ההיסטוריה
     */
    clear() {
        this.states = [];
        this.currentIndex = -1;
        this.triggerEvent('history:cleared');
    }

    /**
     * קפיצה למצב ספציפי
     * @param {number} index - אינדקס המצב
     */
    goToState(index) {
        if (index < 0 || index >= this.states.length) return false;

        this.currentIndex = index;
        return this.restoreState(index);
    }

    /**
     * הפעלה/כיבוי של השמירה האוטומטית
     */
    setEnabled(enabled) {
        this.enabled = enabled;
    }

    /**
     * קבלת מידע על ההיסטוריה
     */
    getInfo() {
        return {
            totalStates: this.states.length,
            currentIndex: this.currentIndex,
            canUndo: this.canUndo(),
            canRedo: this.canRedo(),
            maxStates: this.maxStates,
            enabled: this.enabled
        };
    }

    /**
     * קבלת גודל ההיסטוריה בזיכרון (אומדן)
     */
    getMemoryUsage() {
        const jsonString = JSON.stringify(this.states);
        return jsonString.length; // בתווים
    }

    /**
     * שמירת היסטוריה ל-LocalStorage
     * @param {string} key - מפתח לשמירה
     */
    saveToLocalStorage(key) {
        try {
            const data = {
                states: this.states,
                currentIndex: this.currentIndex,
                timestamp: Date.now()
            };
            localStorage.setItem(key, JSON.stringify(data));
            return true;
        } catch (error) {
            console.error('Error saving to localStorage:', error);
            return false;
        }
    }

    /**
     * טעינת היסטוריה מ-LocalStorage
     * @param {string} key - מפתח לטעינה
     */
    loadFromLocalStorage(key) {
        try {
            const json = localStorage.getItem(key);
            if (!json) return false;

            const data = JSON.parse(json);
            this.states = data.states || [];
            this.currentIndex = data.currentIndex || -1;

            if (this.currentIndex >= 0) {
                this.restoreState(this.currentIndex);
            }

            this.triggerEvent('history:loaded', data);
            return true;
        } catch (error) {
            console.error('Error loading from localStorage:', error);
            return false;
        }
    }

    /**
     * הפקת היסטוריה ל-JSON
     */
    export() {
        return {
            states: this.states,
            currentIndex: this.currentIndex,
            exportedAt: new Date().toISOString()
        };
    }

    /**
     * ייבוא היסטוריה מ-JSON
     */
    import(data) {
        try {
            this.states = data.states || [];
            this.currentIndex = data.currentIndex || -1;

            if (this.currentIndex >= 0) {
                this.restoreState(this.currentIndex);
            }

            this.triggerEvent('history:imported', data);
            return true;
        } catch (error) {
            console.error('Error importing history:', error);
            return false;
        }
    }

    /**
     * הפעלת אירוע מותאם אישית
     */
    triggerEvent(eventName, data) {
        if (!this.canvas) return;

        this.canvas.fire(eventName, { ...data, history: this });
    }

    /**
     * השמדת המנהל
     */
    destroy() {
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        this.clear();
        this.canvas = null;
    }
}

/**
 * פונקציות עזר
 */

/**
 * יצירת snapshot מהיר של Canvas (ללא metadata)
 */
export function createSnapshot(canvas) {
    if (!canvas) return null;

    try {
        return {
            data: canvas.toJSON(),
            timestamp: Date.now()
        };
    } catch (error) {
        console.error('Error creating snapshot:', error);
        return null;
    }
}

/**
 * השוואה בין שני snapshots
 */
export function compareSnapshots(snapshot1, snapshot2) {
    if (!snapshot1 || !snapshot2) return false;

    try {
        const json1 = JSON.stringify(snapshot1.data);
        const json2 = JSON.stringify(snapshot2.data);
        return json1 === json2;
    } catch (error) {
        console.error('Error comparing snapshots:', error);
        return false;
    }
}
