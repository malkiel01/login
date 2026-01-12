/**
 * Boundary Manager - ניהול גבולות ואילוצי ציור
 * מטפל בגבול הורה, מסכה אפורה, ואילוצי גרירה
 */

export class BoundaryManager {
    constructor(canvas, mapAPI) {
        this.canvas = canvas;
        this.mapAPI = mapAPI;
        this.boundary = null;              // הפוליגון של הגבול
        this.boundaryOutline = null;       // קו התיאור של הגבול
        this.grayMask = null;              // המסכה האפורה
        this.clipPath = null;              // מסכת חיתוך
        this.isEditMode = false;           // האם במצב עריכת גבול
        this.isVertexEditMode = false;     // האם במצב עריכת נקודות
        this.lastValidState = null;        // מצב אחרון תקין
        this.editPoints = [];              // נקודות עריכה (עיגולים)
    }

    /**
     * טעינת גבול הורה
     * @param {string} parentType - סוג ישות ההורה
     * @param {string} parentId - מזהה ההורה
     */
    async loadParentBoundary(parentType, parentId) {
        if (!parentType || !parentId) {
            this.clearBoundary();
            return null;
        }

        try {
            const parentData = await this.mapAPI.loadMap(parentType, parentId, false);

            if (parentData && parentData.entity && parentData.entity.mapPolygon) {
                const polygon = parentData.entity.mapPolygon;
                this.setBoundary(polygon.points, {
                    name: parentData.entity.name || 'גבול הורה',
                    color: polygon.style?.strokeColor || '#FF0000'
                });
                return polygon.points;
            }

            return null;
        } catch (error) {
            console.error('Error loading parent boundary:', error);
            return null;
        }
    }

    /**
     * הגדרת גבול
     * @param {Array} points - נקודות הגבול
     * @param {Object} options - אפשרויות נוספות
     */
    setBoundary(points, options = {}) {
        if (!points || points.length < 3) {
            this.clearBoundary();
            return;
        }

        this.clearBoundary();

        const {
            name = 'גבול',
            color = '#FF0000',
            showOutline = true,
            showMask = true
        } = options;

        // עיגול נקודות לפיקסלים שלמים למניעת טשטוש
        const roundedPoints = points.map(p => ({
            x: Math.round(p.x),
            y: Math.round(p.y)
        }));

        // יצירת קו תיאור הגבול
        if (showOutline) {
            this.boundaryOutline = new fabric.Polygon(roundedPoints, {
                fill: 'transparent',
                stroke: color,
                strokeWidth: 3,
                strokeDashArray: [10, 5],
                selectable: false,
                evented: false,
                objectCaching: false,
                isBoundary: true,
                name: name
            });
            this.canvas.add(this.boundaryOutline);
        }

        // יצירת מסכת חיתוך (גם מעוגלת!)
        this.clipPath = new fabric.Polygon(roundedPoints, {
            absolutePositioned: true,
            inverted: false
        });

        // יצירת מסכה אפורה (מקבלת נקודות מעוגלות)
        if (showMask) {
            this.createGrayMask(roundedPoints);
        }

        // שמירת נקודות הגבול (כבר מעוגלות)
        this.boundary = {
            points: roundedPoints.map(p => ({ ...p })),
            name,
            color
        };

        this.reorderLayers();
    }

    /**
     * יצירת מסכה אפורה מחוץ לגבול
     * @param {Array} points - נקודות הגבול (כבר מעוגלות!)
     */
    createGrayMask(points) {
        // הנקודות כבר מעוגלות ב-setBoundary()!

        // נרחיב את הגבולות של המסכה כדי לכסות את כל ה-viewport
        const canvasWidth = this.canvas.width * 10;
        const canvasHeight = this.canvas.height * 10;

        // יצירת מלבן גדול עם חור בצורת הפוליגון
        const outerRect = [
            { x: -canvasWidth, y: -canvasHeight },
            { x: canvasWidth * 2, y: -canvasHeight },
            { x: canvasWidth * 2, y: canvasHeight * 2 },
            { x: -canvasWidth, y: canvasHeight * 2 }
        ];

        // מיזוג הנקודות: תחילה המלבן החיצוני, אחר כך הפוליגון הפנימי בכיוון הפוך
        const allPoints = [
            ...outerRect,
            { x: -canvasWidth, y: -canvasHeight }, // סגירת המלבן
            ...points.slice().reverse(), // הפוליגון בכיוון הפוך (כבר מעוגל!)
            points[points.length - 1] // סגירת הפוליגון
        ];

        this.grayMask = new fabric.Polygon(allPoints, {
            fill: 'rgba(0, 0, 0, 0.5)',
            stroke: null,
            strokeWidth: 0,
            selectable: false,
            evented: false,
            objectCaching: false,
            fillRule: 'evenodd',
            isGrayMask: true,
            left: Math.round(this.canvas.width / 2),
            top: Math.round(this.canvas.height / 2),
            originX: 'center',
            originY: 'center'
        });

        this.canvas.add(this.grayMask);
    }

    /**
     * עדכון מיקום המסכה (בעת zoom/pan)
     */
    updateMaskPosition() {
        if (!this.grayMask) return;

        const vpt = this.canvas.viewportTransform;
        const center = this.canvas.getCenter();

        this.grayMask.set({
            left: Math.round(center.left),
            top: Math.round(center.top)
        });

        this.grayMask.setCoords();
        this.canvas.renderAll();
    }

    /**
     * סידור שכבות (layers)
     */
    reorderLayers() {
        if (this.grayMask) {
            this.canvas.bringToFront(this.grayMask);
        }

        if (this.boundaryOutline) {
            this.canvas.bringToFront(this.boundaryOutline);
        }
    }

    /**
     * בדיקה אם נקודה נמצאת בתוך הגבול
     * @param {Object} point - {x, y}
     * @returns {boolean}
     */
    isPointInsideBoundary(point) {
        if (!this.boundary || !this.boundary.points) {
            return true; // אין גבול - הכל מותר
        }

        return this.isPointInPolygon(point, this.boundary.points);
    }

    /**
     * בדיקה אם פוליגון שלם נמצא בתוך הגבול
     * @param {Array} polygonPoints - נקודות הפוליגון לבדיקה
     * @returns {boolean}
     */
    isPolygonInsideBoundary(polygonPoints) {
        if (!this.boundary || !this.boundary.points) {
            return true; // אין גבול - הכל מותר
        }

        // בדיקה שכל הנקודות נמצאות בתוך הגבול
        return polygonPoints.every(point =>
            this.isPointInsideBoundary(point)
        );
    }

    /**
     * בדיקת נקודה בפוליגון (Ray casting algorithm)
     * @param {Object} point - {x, y}
     * @param {Array} polygon - מערך נקודות [{x, y}, ...]
     * @returns {boolean}
     */
    isPointInPolygon(point, polygon) {
        let inside = false;
        const x = point.x;
        const y = point.y;

        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i].x;
            const yi = polygon[i].y;
            const xj = polygon[j].x;
            const yj = polygon[j].y;

            const intersect = ((yi > y) !== (yj > y)) &&
                (x < (xj - xi) * (y - yi) / (yj - yi) + xi);

            if (intersect) inside = !inside;
        }

        return inside;
    }

    /**
     * קליפת פוליגון לפי הגבול (פשוט - החזרת נקודות שבתוך הגבול בלבד)
     * @param {Array} polygonPoints - נקודות לקליפה
     * @returns {Array} - נקודות שנמצאות בתוך הגבול
     */
    clipPolygonToBoundary(polygonPoints) {
        if (!this.boundary || !this.boundary.points) {
            return polygonPoints;
        }

        return polygonPoints.filter(point =>
            this.isPointInsideBoundary(point)
        );
    }

    /**
     * אילוץ אובייקט להישאר בתוך הגבול
     * @param {fabric.Object} object - האובייקט להגבלה
     * @returns {boolean} - האם האובייקט בתוך הגבול
     */
    constrainObjectToBoundary(object) {
        if (!this.boundary || !object) return true;

        // בדיקה לפי סוג האובייקט
        if (object.type === 'polygon') {
            const points = object.points.map(p => ({
                x: object.left + p.x,
                y: object.top + p.y
            }));

            return this.isPolygonInsideBoundary(points);
        }

        // עבור אובייקטים אחרים - בדיקת נקודת המרכז
        const center = object.getCenterPoint();
        return this.isPointInsideBoundary(center);
    }

    /**
     * שמירת מצב תקין
     */
    saveValidState(object) {
        if (!object) return;

        this.lastValidState = {
            left: object.left,
            top: object.top,
            points: object.points ? object.points.map(p => ({ ...p })) : null
        };
    }

    /**
     * שחזור מצב תקין
     */
    restoreValidState(object) {
        if (!object || !this.lastValidState) return false;

        object.set({
            left: this.lastValidState.left,
            top: this.lastValidState.top
        });

        if (this.lastValidState.points && object.points) {
            object.points = this.lastValidState.points.map(p => ({ ...p }));
        }

        object.setCoords();
        this.canvas.renderAll();
        return true;
    }

    /**
     * התחלת מצב עריכת גבול
     */
    enterEditMode() {
        this.isEditMode = true;

        if (this.boundaryOutline) {
            // שמירת מצב התחלתי
            this.lastValidState = {
                left: this.boundaryOutline.left,
                top: this.boundaryOutline.top,
                scaleX: this.boundaryOutline.scaleX,
                scaleY: this.boundaryOutline.scaleY
            };

            this.boundaryOutline.set({
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

            // האזנה להזזה ושינוי גודל - עדכון המסכה
            this.boundaryOutline.on('moving', () => this.updateGrayMaskFromBoundary());
            this.boundaryOutline.on('scaling', () => this.updateGrayMaskFromBoundary());

            this.canvas.setActiveObject(this.boundaryOutline);
        }

        this.canvas.renderAll();
    }

    /**
     * עדכון המסכה האפורה לפי מיקום הגבול הנוכחי
     */
    updateGrayMaskFromBoundary() {
        if (!this.boundaryOutline || !this.grayMask) return;

        // קבלת הנקודות החדשות של הגבול לפי הטרנספורמציה
        const matrix = this.boundaryOutline.calcTransformMatrix();
        const points = this.boundaryOutline.points.map(p => {
            const transformed = fabric.util.transformPoint(
                { x: p.x - this.boundaryOutline.pathOffset.x, y: p.y - this.boundaryOutline.pathOffset.y },
                matrix
            );
            return { x: Math.round(transformed.x), y: Math.round(transformed.y) };
        });

        // שמירת מצב תקין
        this.lastValidState = {
            left: this.boundaryOutline.left,
            top: this.boundaryOutline.top,
            scaleX: this.boundaryOutline.scaleX,
            scaleY: this.boundaryOutline.scaleY
        };

        // עדכון נתוני הגבול
        this.boundary.points = points;

        // בנייה מחדש של המסכה האפורה
        this.canvas.remove(this.grayMask);
        this.createGrayMask(points);
        this.reorderLayers();
    }

    /**
     * יציאה ממצב עריכת גבול
     */
    exitEditMode() {
        this.isEditMode = false;

        if (this.boundaryOutline) {
            // הסרת האזנות
            this.boundaryOutline.off('moving');
            this.boundaryOutline.off('scaling');

            this.boundaryOutline.set({
                selectable: false,
                evented: false,
                hasControls: false,
                hasBorders: false
            });

            this.canvas.discardActiveObject();
        }

        this.canvas.renderAll();
    }

    /**
     * קבלת נתוני הגבול
     */
    getBoundaryData() {
        if (!this.boundary) return null;

        return {
            points: this.boundary.points.map(p => ({ ...p })),
            name: this.boundary.name,
            color: this.boundary.color
        };
    }

    /**
     * הצגה/הסתרה של הגבול
     */
    setVisible(visible) {
        if (this.boundaryOutline) {
            this.boundaryOutline.set({ visible });
        }

        if (this.grayMask) {
            this.grayMask.set({ visible });
        }

        this.canvas.renderAll();
    }

    /**
     * ניקוי הגבול
     */
    clearBoundary() {
        this.clearEditPoints();

        if (this.boundaryOutline) {
            this.canvas.remove(this.boundaryOutline);
            this.boundaryOutline = null;
        }

        if (this.grayMask) {
            this.canvas.remove(this.grayMask);
            this.grayMask = null;
        }

        this.boundary = null;
        this.clipPath = null;
        this.lastValidState = null;
    }

    // =====================================================
    // Vertex Editing Mode - עריכת נקודות עוגן
    // =====================================================

    /**
     * כניסה למצב עריכת נקודות (vertex editing)
     */
    enterVertexEditMode() {
        if (!this.boundaryOutline) return false;

        this.isVertexEditMode = true;

        // הסתר controls רגילים
        this.boundaryOutline.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        // הצג נקודות עריכה
        this.showEditPoints();
        this.canvas.renderAll();

        console.log('✅ Vertex edit mode: ON');
        return true;
    }

    /**
     * יציאה ממצב עריכת נקודות
     */
    exitVertexEditMode() {
        this.isVertexEditMode = false;
        this.clearEditPoints();

        if (this.boundaryOutline) {
            this.boundaryOutline.set({
                selectable: false,
                evented: false
            });
        }

        this.canvas.renderAll();
        console.log('✅ Vertex edit mode: OFF');
    }

    /**
     * הצגת נקודות עריכה על הפוליגון
     */
    showEditPoints() {
        this.clearEditPoints();

        if (!this.boundaryOutline || !this.boundaryOutline.points) return;

        const points = this.boundaryOutline.points;
        const matrix = this.boundaryOutline.calcTransformMatrix();

        points.forEach((point, index) => {
            // טרנספורמציה לקואורדינטות canvas
            const transformed = fabric.util.transformPoint(
                { x: point.x - this.boundaryOutline.pathOffset.x, y: point.y - this.boundaryOutline.pathOffset.y },
                matrix
            );

            const circle = new fabric.Circle({
                left: transformed.x,
                top: transformed.y,
                radius: 8,
                fill: '#ffffff',
                stroke: '#3b82f6',
                strokeWidth: 2,
                selectable: true,
                hasControls: false,
                hasBorders: false,
                originX: 'center',
                originY: 'center',
                isEditPoint: true,
                pointIndex: index,
                hoverCursor: 'move'
            });

            // האזנה לגרירה
            circle.on('moving', () => this.onEditPointMove(circle));
            circle.on('modified', () => this.onEditPointModified());

            this.editPoints.push(circle);
            this.canvas.add(circle);
        });

        this.canvas.renderAll();
    }

    /**
     * ניקוי נקודות העריכה
     */
    clearEditPoints() {
        this.editPoints.forEach(point => {
            point.off('moving');
            point.off('modified');
            this.canvas.remove(point);
        });
        this.editPoints = [];
    }

    /**
     * טיפול בהזזת נקודת עריכה
     */
    onEditPointMove(circle) {
        const index = circle.pointIndex;
        const matrix = this.boundaryOutline.calcTransformMatrix();
        const invertedMatrix = fabric.util.invertTransform(matrix);

        // טרנספורמציה מקואורדינטות canvas לקואורדינטות polygon
        const transformed = fabric.util.transformPoint(
            { x: circle.left, y: circle.top },
            invertedMatrix
        );

        // עדכון הנקודה בפוליגון
        this.boundaryOutline.points[index] = {
            x: transformed.x + this.boundaryOutline.pathOffset.x,
            y: transformed.y + this.boundaryOutline.pathOffset.y
        };

        // עדכון המסכה
        this.updateGrayMaskFromBoundary();
        this.canvas.renderAll();
    }

    /**
     * טיפול בסיום הזזת נקודה
     */
    onEditPointModified() {
        if (this.boundaryOutline) {
            this.boundaryOutline.setCoords();
        }
        this.canvas.renderAll();
    }

    /**
     * הוספת נקודה לפוליגון
     * @param {number} afterIndex - אינדקס הנקודה שאחריה תתווסף הנקודה החדשה
     */
    addPoint(afterIndex) {
        if (!this.boundaryOutline || !this.boundaryOutline.points) return;

        const points = this.boundaryOutline.points;
        const nextIndex = (afterIndex + 1) % points.length;

        // חישוב נקודת האמצע בין שתי הנקודות
        const p1 = points[afterIndex];
        const p2 = points[nextIndex];
        const newPoint = {
            x: (p1.x + p2.x) / 2,
            y: (p1.y + p2.y) / 2
        };

        // הוספת הנקודה
        points.splice(afterIndex + 1, 0, newPoint);
        this.boundaryOutline.set({ points: [...points] });

        // עדכון נתוני הגבול
        this.updateGrayMaskFromBoundary();

        // רענון נקודות העריכה
        this.showEditPoints();

        console.log(`✅ Added point at index ${afterIndex + 1}`);
    }

    /**
     * הסרת נקודה מהפוליגון
     * @param {number} index - אינדקס הנקודה להסרה
     */
    removePoint(index) {
        if (!this.boundaryOutline || !this.boundaryOutline.points) return;

        const points = this.boundaryOutline.points;

        // פוליגון חייב להכיל לפחות 3 נקודות
        if (points.length <= 3) {
            console.warn('פוליגון חייב להכיל לפחות 3 נקודות');
            return false;
        }

        // הסרת הנקודה
        points.splice(index, 1);
        this.boundaryOutline.set({ points: [...points] });

        // עדכון נתוני הגבול
        this.updateGrayMaskFromBoundary();

        // רענון נקודות העריכה
        this.showEditPoints();

        console.log(`✅ Removed point at index ${index}`);
        return true;
    }

    /**
     * השמדת המנהל
     */
    destroy() {
        this.clearEditPoints();
        this.clearBoundary();
        this.canvas = null;
        this.mapAPI = null;
    }
}
