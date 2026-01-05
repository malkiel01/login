/**
 * HistoryManager - ניהול היסטוריית שינויים (Undo/Redo)
 * Version: 1.0.0
 *
 * מחלקה לניהול היסטוריית Canvas עם תמיכה ב-undo/redo
 * Usage:
 *   const history = new HistoryManager(canvas, {
 *     maxHistory: 30,
 *     onChange: (state) => {...},
 *     onRestore: (restoredObjects) => {...}
 *   });
 *   history.save();
 *   history.undo();
 *   history.redo();
 */

export class HistoryManager {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.stateManager = options.stateManager || null;  // NEW: StateManager integration
        this.options = {
            maxHistory: options.maxHistory || 30,
            onChange: options.onChange || null,      // Called when undo/redo state changes
            onRestore: options.onRestore || null,    // Called after state restoration
            customProperties: options.customProperties || ['objectType', 'polygonPoint', 'polygonLine']
        };

        this.history = [];
        this.currentIndex = -1;
    }

    /**
     * שמירת מצב הקנבס הנוכחי להיסטוריה
     * @returns {boolean} - האם השמירה הצליחה
     */
    save() {
        if (!this.canvas) {
            console.warn('Cannot save: no canvas');
            return false;
        }

        // מחיקת עתיד אם חזרנו אחורה ועשינו שינוי חדש
        if (this.currentIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.currentIndex + 1);
        }

        // שמירת מצב נוכחי כ-JSON
        const state = JSON.stringify(
            this.canvas.toJSON(this.options.customProperties)
        );
        this.history.push(state);

        // הגבלת גודל ההיסטוריה
        if (this.history.length > this.options.maxHistory) {
            this.history.shift();
        } else {
            this.currentIndex++;
        }

        // הודעה על שינוי
        this.notifyChange();

        console.log(`💾 History saved (${this.currentIndex + 1}/${this.history.length})`);
        return true;
    }

    /**
     * ביטול פעולה אחרונה (Undo)
     * @returns {boolean} - האם הביטול הצליח
     */
    undo() {
        if (!this.canUndo()) {
            console.warn('Cannot undo: at beginning of history');
            return false;
        }

        this.currentIndex--;
        this.restore(this.history[this.currentIndex]);

        console.log(`⬅️ Undo to state ${this.currentIndex + 1}/${this.history.length}`);
        return true;
    }

    /**
     * ביצוע מחדש של פעולה (Redo)
     * @returns {boolean} - האם הביצוע הצליח
     */
    redo() {
        if (!this.canRedo()) {
            console.warn('Cannot redo: at end of history');
            return false;
        }

        this.currentIndex++;
        this.restore(this.history[this.currentIndex]);

        console.log(`➡️ Redo to state ${this.currentIndex + 1}/${this.history.length}`);
        return true;
    }

    /**
     * שחזור מצב מההיסטוריה
     * @param {string} state - JSON של מצב הקנבס
     * @private
     */
    restore(state) {
        if (!state || !this.canvas) return;

        this.canvas.loadFromJSON(JSON.parse(state), () => {
            // אחזור אובייקטים שנטענו
            const restoredObjects = {
                backgroundImage: null,
                grayMask: null,
                boundaryOutline: null,
                allObjects: this.canvas.getObjects()
            };

            // זיהוי אובייקטי מערכת
            this.canvas.getObjects().forEach(obj => {
                if (obj.objectType === 'backgroundLayer') {
                    restoredObjects.backgroundImage = obj;
                } else if (obj.objectType === 'grayMask') {
                    restoredObjects.grayMask = obj;
                } else if (obj.objectType === 'boundaryOutline') {
                    restoredObjects.boundaryOutline = obj;
                }
            });

            // Sync StateManager if available
            if (this.stateManager && this.stateManager.syncFromCanvas) {
                this.stateManager.syncFromCanvas();
            }

            // רינדור
            this.canvas.renderAll();

            // הודעה על שינוי
            this.notifyChange();

            // Callback עם האובייקטים שנשחזרו
            if (this.options.onRestore) {
                this.options.onRestore(restoredObjects);
            }

            console.log('✅ Canvas state restored' + (this.stateManager ? ' (StateManager synced)' : ''));
        });
    }

    /**
     * בדיקה אם ניתן לבטל פעולה
     * @returns {boolean}
     */
    canUndo() {
        return this.currentIndex > 0;
    }

    /**
     * בדיקה אם ניתן לבצע מחדש פעולה
     * @returns {boolean}
     */
    canRedo() {
        return this.currentIndex < this.history.length - 1;
    }

    /**
     * ניקוי כל ההיסטוריה
     */
    clear() {
        this.history = [];
        this.currentIndex = -1;
        this.notifyChange();
        console.log('🗑️ History cleared');
    }

    /**
     * קבלת state נוכחי
     */
    getState() {
        return {
            canUndo: this.canUndo(),
            canRedo: this.canRedo(),
            historyLength: this.history.length,
            currentIndex: this.currentIndex,
            maxHistory: this.options.maxHistory
        };
    }

    /**
     * הגדרת Canvas חדש (למשל אחרי יצירה מחדש)
     */
    setCanvas(canvas) {
        this.canvas = canvas;
        console.log('Canvas reference updated in HistoryManager');
    }

    /**
     * הגדרת StateManager (לשימוש בסנכרון state)
     */
    setStateManager(stateManager) {
        this.stateManager = stateManager;
        console.log('StateManager reference added to HistoryManager');
    }

    /**
     * הודעה על שינוי ב-state
     * @private
     */
    notifyChange() {
        if (this.options.onChange) {
            this.options.onChange(this.getState());
        }
    }

    /**
     * קבלת גודל ההיסטוריה (למטרות debug)
     */
    getHistorySize() {
        const size = this.history.reduce((total, state) => total + state.length, 0);
        return {
            bytes: size,
            kb: (size / 1024).toFixed(2),
            states: this.history.length
        };
    }

    /**
     * Debug info
     */
    debug() {
        const size = this.getHistorySize();
        console.group('📚 HistoryManager');
        console.log('States:', this.history.length, '/', this.options.maxHistory);
        console.log('Current Index:', this.currentIndex);
        console.log('Can Undo:', this.canUndo());
        console.log('Can Redo:', this.canRedo());
        console.log('Size:', size.kb, 'KB');
        console.groupEnd();
    }
}
