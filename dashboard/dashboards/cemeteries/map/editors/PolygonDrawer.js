/**
 * PolygonDrawer - ציור פוליגונים על המפה
 * Version: 1.0.0
 *
 * מחלקה לציור פוליגונים (גבולות) על המפה עם preview ו-validation
 * Usage:
 *   const drawer = new PolygonDrawer(canvas, {
 *     color: '#3b82f6',
 *     strokeWidth: 2,
 *     onFinish: (points) => {...}
 *   });
 *   drawer.start();
 */

export class PolygonDrawer {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.options = {
            color: options.color || '#3b82f6',
            strokeWidth: options.strokeWidth || 2,
            pointRadius: options.pointRadius || 5,
            minPoints: options.minPoints || 3,
            onFinish: options.onFinish || null,
            onCancel: options.onCancel || null,
            parentBoundary: options.parentBoundary || null, // גבול הורה לבדיקה
            requireDoubleClick: options.requireDoubleClick !== false
        };

        this.isDrawing = false;
        this.points = [];
        this.previewLine = null;
        this.tempPoints = []; // visual point objects
        this.tempLines = [];  // visual line objects
    }

    /**
     * התחלת ציור פוליגון
     */
    start() {
        if (!this.canvas) {
            console.error('Canvas not available');
            return;
        }

        this.isDrawing = true;
        this.points = [];
        this.tempPoints = [];
        this.tempLines = [];
        this.previewLine = null;

        console.log('Started polygon drawing');
    }

    /**
     * טיפול בלחיצה על ה-Canvas
     * @param {Object} options - Fabric.js event options
     */
    handleClick(options) {
        if (!this.isDrawing) return;

        const pointer = this.canvas.getPointer(options.e);
        const newPoint = { x: pointer.x, y: pointer.y };

        this.points.push(newPoint);
        this.drawTempPoint(pointer);
        this.drawTempLine();

        // Double click to finish (if enabled)
        if (this.options.requireDoubleClick && options.e.detail === 2 && this.points.length >= this.options.minPoints) {
            this.finish();
        }
    }

    /**
     * טיפול בתנועת עכבר - preview line
     * @param {Object} options - Fabric.js event options
     */
    handleMouseMove(options) {
        if (!this.isDrawing || this.points.length === 0) return;

        const pointer = this.canvas.getPointer(options.e);
        this.updatePreviewLine(pointer);
    }

    /**
     * ציור נקודה ויזואלית זמנית
     */
    drawTempPoint(pointer) {
        const point = new fabric.Circle({
            left: pointer.x,
            top: pointer.y,
            radius: this.options.pointRadius,
            fill: this.options.color,
            stroke: this.getDarkerColor(this.options.color),
            strokeWidth: 2,
            originX: 'center',
            originY: 'center',
            selectable: false,
            evented: false,
            polygonPoint: true // identifier
        });

        this.tempPoints.push(point);
        this.canvas.add(point);
        this.canvas.renderAll();
    }

    /**
     * ציור קו זמני בין נקודות
     */
    drawTempLine() {
        if (this.points.length < 2) return;

        const lastIdx = this.points.length - 1;
        const line = new fabric.Line([
            this.points[lastIdx - 1].x,
            this.points[lastIdx - 1].y,
            this.points[lastIdx].x,
            this.points[lastIdx].y
        ], {
            stroke: this.options.color,
            strokeWidth: this.options.strokeWidth,
            selectable: false,
            evented: false,
            polygonLine: true // identifier
        });

        this.tempLines.push(line);
        this.canvas.add(line);
        this.canvas.renderAll();
    }

    /**
     * עדכון קו preview (מהנקודה האחרונה לעכבר)
     */
    updatePreviewLine(pointer) {
        // הסרת קו preview קודם
        if (this.previewLine) {
            this.canvas.remove(this.previewLine);
            this.previewLine = null;
        }

        const lastPoint = this.points[this.points.length - 1];

        const newPreviewLine = new fabric.Line([
            lastPoint.x,
            lastPoint.y,
            pointer.x,
            pointer.y
        ], {
            stroke: this.options.color,
            strokeWidth: this.options.strokeWidth,
            strokeDashArray: [5, 5], // dashed line
            selectable: false,
            evented: false,
            previewLine: true // identifier
        });

        this.previewLine = newPreviewLine;
        this.canvas.add(this.previewLine);
        this.canvas.renderAll();
    }

    /**
     * סיום ציור הפוליגון
     */
    finish() {
        if (this.points.length < this.options.minPoints) {
            alert(`נדרשות לפחות ${this.options.minPoints} נקודות ליצירת גבול`);
            return false;
        }

        // בדיקת validation - כל הנקודות בתוך גבול ההורה
        if (this.options.parentBoundary && this.options.parentBoundary.length > 0) {
            const pointsOutside = this.points.filter(p =>
                !this.isPointInPolygon(p, this.options.parentBoundary)
            );

            if (pointsOutside.length > 0) {
                alert(`לא ניתן ליצור גבול מחוץ לגבול ההורה.\n\n${pointsOutside.length} נקודות נמצאות מחוץ לגבול המותר.`);
                return false;
            }
        }

        const finalPoints = [...this.points];
        this.cleanup();
        this.isDrawing = false;

        // Callback
        if (this.options.onFinish) {
            this.options.onFinish(finalPoints);
        }

        console.log('Polygon drawing finished with', finalPoints.length, 'points');
        return finalPoints;
    }

    /**
     * ביטול ציור
     */
    cancel() {
        this.cleanup();
        this.isDrawing = false;
        this.points = [];

        if (this.options.onCancel) {
            this.options.onCancel();
        }

        console.log('Polygon drawing cancelled');
    }

    /**
     * ניקוי כל האובייקטים הזמניים
     */
    cleanup() {
        // Remove preview line
        if (this.previewLine) {
            this.canvas.remove(this.previewLine);
            this.previewLine = null;
        }

        // Remove temp points and lines
        const objects = this.canvas.getObjects();
        objects.forEach(obj => {
            if (obj.polygonPoint || obj.polygonLine) {
                this.canvas.remove(obj);
            }
        });

        this.tempPoints = [];
        this.tempLines = [];
        this.canvas.renderAll();
    }

    /**
     * בדיקה אם נקודה נמצאת בתוך פוליגון
     * Ray casting algorithm
     */
    isPointInPolygon(point, polygon) {
        if (!polygon || polygon.length < 3) return true;

        let inside = false;
        const x = point.x, y = point.y;

        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i].x, yi = polygon[i].y;
            const xj = polygon[j].x, yj = polygon[j].y;

            if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }

        return inside;
    }

    /**
     * קבלת צבע כהה יותר (לשימוש ב-stroke)
     */
    getDarkerColor(color) {
        // Simple darkening - could be improved
        if (color === '#3b82f6') return '#1e40af';
        if (color === '#10b981') return '#047857';
        if (color === '#f59e0b') return '#b45309';
        return color;
    }

    /**
     * קבלת state נוכחי
     */
    getState() {
        return {
            isDrawing: this.isDrawing,
            points: [...this.points],
            pointsCount: this.points.length
        };
    }

    /**
     * האם כרגע מצייר
     */
    isActive() {
        return this.isDrawing;
    }

    /**
     * Debug info
     */
    debug() {
        console.group('✏️ PolygonDrawer');
        console.log('Is Drawing:', this.isDrawing);
        console.log('Points:', this.points.length);
        console.log('Temp Objects:', this.tempPoints.length, 'points,', this.tempLines.length, 'lines');
        console.log('Has Preview:', !!this.previewLine);
        console.groupEnd();
    }
}
