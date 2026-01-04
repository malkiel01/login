/**
 * ZoomControls - בקרות זום למפה
 * Version: 1.0.0
 *
 * מחלקה לניהול זום של המפה (הגדלה/הקטנה/עריכה ידנית)
 * Usage:
 *   const zoomControls = new ZoomControls(canvas, {
 *     min: 0.3,
 *     max: 3,
 *     step: 0.1,
 *     onZoomChange: (zoom) => {...}
 *   });
 */

export class ZoomControls {
    constructor(canvas, config = {}) {
        this.canvas = canvas;
        this.config = {
            min: config.min || 0.3,
            max: config.max || 3,
            step: config.step || 0.1,
            onZoomChange: config.onZoomChange || null
        };
        this.currentZoom = 1;
    }

    /**
     * קבלת רמת הזום הנוכחית
     */
    getZoom() {
        return this.currentZoom;
    }

    /**
     * הגדלת זום
     */
    zoomIn() {
        const newZoom = Math.min(this.currentZoom + this.config.step, this.config.max);
        this.setZoom(newZoom);
    }

    /**
     * הקטנת זום
     */
    zoomOut() {
        const newZoom = Math.max(this.currentZoom - this.config.step, this.config.min);
        this.setZoom(newZoom);
    }

    /**
     * הגדרת זום לרמה ספציפית
     * @param {number} zoom - רמת הזום (0.3-3)
     * @param {boolean} skipCallback - דלג על קריאה ל-callback
     */
    setZoom(zoom, skipCallback = false) {
        // הגבלת טווח
        zoom = Math.max(this.config.min, Math.min(this.config.max, zoom));
        this.currentZoom = zoom;

        // עדכון הקנבס
        if (this.canvas) {
            this.canvas.setZoom(zoom);
            this.canvas.renderAll();
        }

        // קריאה ל-callback
        if (!skipCallback && this.config.onZoomChange) {
            this.config.onZoomChange(zoom);
        }
    }

    /**
     * הגדרת זום באחוזים
     * @param {number} percent - אחוז זום (30-300)
     */
    setZoomPercent(percent) {
        const zoom = percent / 100;
        this.setZoom(zoom);
    }

    /**
     * קבלת זום באחוזים
     */
    getZoomPercent() {
        return Math.round(this.currentZoom * 100);
    }

    /**
     * עריכת זום ידנית (יצירת input field)
     * @param {HTMLElement} displayElement - האלמנט שמציג את הזום
     */
    enableManualEdit(displayElement) {
        if (!displayElement) return;

        const currentValue = this.getZoomPercent();

        // יצירת input במקום הטקסט
        const input = document.createElement('input');
        input.type = 'number';
        input.value = currentValue;
        input.min = this.config.min * 100;
        input.max = this.config.max * 100;
        input.style.cssText = 'width: 50px; text-align: center; font-size: 13px; border: 1px solid #3b82f6; border-radius: 4px; padding: 2px;';

        // החלפת התוכן
        displayElement.textContent = '';
        displayElement.appendChild(input);
        input.focus();
        input.select();

        // טיפול באישור
        const applyZoom = () => {
            let newZoom = parseInt(input.value) || 100;
            // הגבלת טווח
            newZoom = Math.max(this.config.min * 100, Math.min(this.config.max * 100, newZoom));

            this.setZoomPercent(newZoom);

            // החזרת התצוגה הרגילה
            displayElement.textContent = newZoom + '%';
        };

        // טיפול בביטול
        const cancelEdit = () => {
            displayElement.textContent = currentValue + '%';
        };

        input.addEventListener('blur', applyZoom);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                cancelEdit();
            }
        });
    }

    /**
     * עדכון הקנבס
     */
    setCanvas(canvas) {
        this.canvas = canvas;
        if (canvas) {
            canvas.setZoom(this.currentZoom);
            canvas.renderAll();
        }
    }

    /**
     * איפוס לזום ברירת מחדל
     */
    reset() {
        this.setZoom(1);
    }

    /**
     * Debug info
     */
    debug() {
        console.group('🔍 ZoomControls');
        console.log('Current Zoom:', this.currentZoom);
        console.log('Zoom %:', this.getZoomPercent() + '%');
        console.log('Config:', this.config);
        console.log('Has Canvas:', !!this.canvas);
        console.groupEnd();
    }
}
