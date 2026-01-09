/**
 * BoundaryEditor - עריכת גבולות המפה
 * Version: 1.0.0
 *
 * מחלקה לניהול עריכה של גבולות קיימים (הזזה, שינוי גודל, מחיקה)
 * Usage:
 *   const editor = new BoundaryEditor(canvas, {
 *     parentBoundary: parentBoundaryPoints,
 *     onUpdate: (newState) => {...},
 *     onDelete: () => {...}
 *   });
 *   editor.enableEditMode(boundaryOutline, grayMask);
 */

export class BoundaryEditor {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.options = {
            parentBoundary: options.parentBoundary || null,
            onUpdate: options.onUpdate || null,
            onDelete: options.onDelete || null,
            validateWithinParent: options.validateWithinParent !== false
        };

        this.isEditMode = false;
        this.boundaryOutline = null;
        this.grayMask = null;
        this.boundaryClipPath = null;
        this.lastValidState = null;

        // Bind methods
        this.handleMove = this.handleMove.bind(this);
        this.handleScale = this.handleScale.bind(this);
    }

    /**
     * הפעלת מצב עריכה
     * @param {fabric.Object} boundaryOutline - קו הגבול
     * @param {fabric.Object} grayMask - המסכה האפורה
     * @param {fabric.Object} clipPath - clipPath לשימוש עתידי
     */
    enableEditMode(boundaryOutline, grayMask, clipPath = null) {
        if (!boundaryOutline || !grayMask) {
            console.error('Missing boundary or mask objects');
            return false;
        }

        // סנכרון עם הקנבס - אולי האובייקטים נוצרו מחדש ע"י loadFromJSON
        const canvasBoundary = this.canvas.getObjects().find(obj => obj.objectType === 'boundaryOutline');
        const canvasMask = this.canvas.getObjects().find(obj => obj.objectType === 'grayMask');

        // השתמש באובייקטים מהקנבס אם קיימים
        const actualBoundary = canvasBoundary || boundaryOutline;
        const actualMask = canvasMask || grayMask;

        this.isEditMode = true;
        this.boundaryOutline = actualBoundary;
        this.grayMask = actualMask;
        this.boundaryClipPath = clipPath;

        // שמור מצב התחלתי (למקרה של גרירה מחוץ לגבול הורה)
        this.lastValidState = {
            left: actualBoundary.left,
            top: actualBoundary.top,
            scaleX: actualBoundary.scaleX,
            scaleY: actualBoundary.scaleY
        };

        // הפוך רק את הגבול לניתן לבחירה
        actualBoundary.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            lockRotation: true,
            cornerStyle: 'circle',
            cornerSize: 12,
            cornerColor: '#3b82f6',
            transparentCorners: false
        });

        // עדכון קואורדינטות הפקדים
        actualBoundary.setCoords();
        actualBoundary.dirty = true;

        // המסכה האפורה תמיד נשארת נעולה לחלוטין!
        actualMask.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        // בחר את הגבול
        this.canvas.setActiveObject(actualBoundary);

        // האזן לשינויים בגבול - המסכה תעודכן אוטומטית
        actualBoundary.on('moving', this.handleMove);
        actualBoundary.on('scaling', this.handleScale);

        this.canvas.renderAll();
        console.log('✅ Boundary edit mode: ON');
        return true;
    }

    /**
     * כיבוי מצב עריכה
     */
    disableEditMode() {
        if (!this.boundaryOutline) {
            return false;
        }

        // הסר האזנה
        this.boundaryOutline.off('moving', this.handleMove);
        this.boundaryOutline.off('scaling', this.handleScale);

        // בטל בחירה
        this.canvas.discardActiveObject();

        // נעל את הגבול והמסכה
        this.boundaryOutline.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        if (this.grayMask) {
            this.grayMask.set({
                selectable: false,
                evented: false
            });
        }

        this.isEditMode = false;
        this.canvas.renderAll();
        console.log('✅ Boundary edit mode: OFF');
        return true;
    }

    /**
     * טיפול בהזזת הגבול
     */
    handleMove() {
        this.updateMaskPosition();
    }

    /**
     * טיפול בשינוי גודל הגבול
     */
    handleScale() {
        this.updateMaskPosition();
    }

    /**
     * עדכון מיקום המסכה בעת הזזת/שינוי גודל של הגבול
     */
    updateMaskPosition() {
        if (!this.boundaryOutline || !this.grayMask) return;

        // קבל את הנקודות החדשות של הגבול
        const matrix = this.boundaryOutline.calcTransformMatrix();
        const points = this.boundaryOutline.points.map(p => {
            const transformed = fabric.util.transformPoint(
                { x: p.x - this.boundaryOutline.pathOffset.x, y: p.y - this.boundaryOutline.pathOffset.y },
                matrix
            );
            return transformed;
        });

        // בדיקה אם הגבול יוצא מגבול ההורה (אם קיים)
        if (this.options.validateWithinParent && this.options.parentBoundary && this.options.parentBoundary.length > 0) {
            const pointsOutside = points.filter(p => !this.isPointInPolygon(p, this.options.parentBoundary));
            if (pointsOutside.length > 0) {
                // שחזר למצב האחרון התקין
                if (this.lastValidState) {
                    this.boundaryOutline.set({
                        left: this.lastValidState.left,
                        top: this.lastValidState.top,
                        scaleX: this.lastValidState.scaleX,
                        scaleY: this.lastValidState.scaleY
                    });
                    this.boundaryOutline.setCoords();
                }
                return;
            }
        }

        // שמור מצב תקין
        this.lastValidState = {
            left: this.boundaryOutline.left,
            top: this.boundaryOutline.top,
            scaleX: this.boundaryOutline.scaleX,
            scaleY: this.boundaryOutline.scaleY
        };

        // בנה מחדש את המסכה
        const canvasWidth = this.canvas.width;
        const canvasHeight = this.canvas.height;
        const maskSize = 10000; // גודל ענק שיכסה בכל מצב זום

        let pathData = `M ${-maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${canvasHeight + maskSize} L ${-maskSize} ${canvasHeight + maskSize} Z `;
        pathData += `M ${Math.round(points[0].x)} ${Math.round(points[0].y)} `;
        for (let i = points.length - 1; i >= 0; i--) {
            pathData += `L ${Math.round(points[i].x)} ${Math.round(points[i].y)} `;
        }
        pathData += 'Z';

        // עדכן את נתיב המסכה
        this.grayMask.set({
            path: fabric.util.parsePath(pathData),
            stroke: null,
            strokeWidth: 0
        });
        this.canvas.renderAll();

        // Callback for update
        if (this.options.onUpdate) {
            this.options.onUpdate(this.lastValidState);
        }
    }

    /**
     * מחיקת הגבול והמסכה
     */
    delete() {
        if (!this.canvas) return false;

        // כבה מצב עריכה אם פעיל
        if (this.isEditMode) {
            this.disableEditMode();
        }

        // מחק את האובייקטים
        const objects = this.canvas.getObjects();
        objects.forEach(obj => {
            if (obj.objectType === 'boundary' ||
                obj.objectType === 'grayMask' ||
                obj.objectType === 'boundaryOutline' ||
                obj.polygonPoint ||
                obj.polygonLine) {
                this.canvas.remove(obj);
            }
        });

        // איפוס משתנים
        this.boundaryClipPath = null;
        this.grayMask = null;
        this.boundaryOutline = null;
        this.lastValidState = null;

        this.canvas.renderAll();

        // Callback for delete
        if (this.options.onDelete) {
            this.options.onDelete();
        }

        console.log('✅ Boundary deleted');
        return true;
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
     * קבלת state נוכחי
     */
    getState() {
        return {
            isEditMode: this.isEditMode,
            hasBoundary: !!this.boundaryOutline,
            lastValidState: this.lastValidState
        };
    }

    /**
     * עדכון גבול הורה
     */
    setParentBoundary(parentBoundary) {
        this.options.parentBoundary = parentBoundary;
    }

    /**
     * Debug info
     */
    debug() {
        console.group('📐 BoundaryEditor');
        console.log('Edit Mode:', this.isEditMode);
        console.log('Has Boundary:', !!this.boundaryOutline);
        console.log('Has Mask:', !!this.grayMask);
        console.log('Last Valid State:', this.lastValidState);
        console.groupEnd();
    }
}
