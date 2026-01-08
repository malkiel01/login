/**
 * BoundaryEditPanel - חלון נגרר לעריכת נקודות גבול
 * Version: 2.0.0
 *
 * מבוסס על FloatingPanel - חלון צף שמאפשר הוספה והסרה של נקודות בגבול המפה
 * Usage:
 *   const panel = new BoundaryEditPanel(canvas, {
 *     onPointsChanged: (points) => {...}
 *   });
 *   panel.show(boundaryOutline);
 */

import { FloatingPanel } from './FloatingPanel.js';

export class BoundaryEditPanel extends FloatingPanel {
    constructor(canvas, options = {}) {
        // Initialize parent with panel options
        super({
            title: 'עריכת נקודות גבול',
            width: 200,
            position: { top: 80, right: 20 },
            headerColor: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            className: 'boundary-edit-panel',
            onClose: () => this.onPanelClose()
        });

        this.canvas = canvas;
        this.panelOptions = {
            onPointsChanged: options.onPointsChanged || null,
            onClose: options.onClose || null
        };

        this.boundaryOutline = null;
        this.grayMask = null;
        this.mode = null; // 'add' | 'remove' | null
        this.pointMarkers = [];

        // Bind methods
        this.handleCanvasClick = this.handleCanvasClick.bind(this);

        // Inject additional CSS
        BoundaryEditPanel.injectPanelCSS();
    }

    /**
     * CSS נוסף ספציפי לפאנל הזה
     */
    static injectPanelCSS() {
        if (document.getElementById('boundaryEditPanelStyles')) return;

        const styles = document.createElement('style');
        styles.id = 'boundaryEditPanelStyles';
        styles.textContent = `
            .boundary-point-marker {
                pointer-events: auto !important;
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * הצגת הפאנל
     */
    show(boundaryOutline, grayMask) {
        if (!boundaryOutline) {
            console.error('No boundary to edit');
            return;
        }

        this.boundaryOutline = boundaryOutline;
        this.grayMask = grayMask;
        this.mode = null;

        // Set container to canvas parent
        if (this.canvas.wrapperEl?.parentElement) {
            this.setContainer(this.canvas.wrapperEl.parentElement);
        }

        // Build content
        this.buildContent();

        // Show panel (parent method)
        super.show();

        this.updatePointsCount();
        this.showPointMarkers();

        console.log('📐 BoundaryEditPanel shown');
    }

    /**
     * בניית תוכן הפאנל
     */
    buildContent() {
        this.clearContent();

        // Info text
        const info = FloatingPanel.createInfo('בחר מצב עריכה ולחץ על המפה');
        info.id = 'boundaryEditInfo';
        this.appendContent(info);

        // Add point button
        const addBtn = FloatingPanel.createButton({
            icon: '➕',
            text: 'הוסף נקודה',
            onClick: () => this.setMode('add')
        });
        addBtn.dataset.mode = 'add';
        this.appendContent(addBtn);

        // Remove point button
        const removeBtn = FloatingPanel.createButton({
            icon: '➖',
            text: 'הסר נקודה',
            danger: true,
            onClick: () => this.setMode('remove')
        });
        removeBtn.dataset.mode = 'remove';
        this.appendContent(removeBtn);

        // Points count footer
        const footer = FloatingPanel.createFooter('נקודות: <span id="boundaryPointsCount">0</span>');
        this.appendContent(footer);
    }

    /**
     * סגירת הפאנל
     */
    onPanelClose() {
        this.mode = null;
        this.hidePointMarkers();
        this.removeCanvasListeners();

        if (this.panelOptions.onClose) {
            this.panelOptions.onClose();
        }

        console.log('📐 BoundaryEditPanel hidden');
    }

    /**
     * הסתרת הפאנל (override)
     */
    hide() {
        super.hide();
        this.onPanelClose();
    }

    /**
     * בחירת מצב עריכה
     */
    setMode(mode) {
        // Toggle mode
        if (this.mode === mode) {
            this.mode = null;
        } else {
            this.mode = mode;
        }

        // Update buttons
        const content = this.getContentElement();
        const buttons = content.querySelectorAll('.floating-panel-btn');
        buttons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === this.mode);
        });

        // Update info text
        const info = content.querySelector('#boundaryEditInfo');
        if (info) {
            if (this.mode === 'add') {
                info.textContent = 'לחץ על קו הגבול להוספת נקודה';
            } else if (this.mode === 'remove') {
                info.textContent = 'לחץ על נקודה כדי להסיר אותה';
            } else {
                info.textContent = 'בחר מצב עריכה ולחץ על המפה';
            }
        }

        // Update cursor
        if (this.mode === 'add') {
            this.canvas.defaultCursor = 'crosshair';
        } else if (this.mode === 'remove') {
            this.canvas.defaultCursor = 'pointer';
        } else {
            this.canvas.defaultCursor = 'default';
        }

        // Update markers and listeners
        if (this.mode) {
            this.showPointMarkers();
            this.addCanvasListeners();
        } else {
            this.hidePointMarkers();
            this.removeCanvasListeners();
        }
    }

    /**
     * הצגת סמני נקודות
     */
    showPointMarkers() {
        this.hidePointMarkers();

        if (!this.boundaryOutline || !this.boundaryOutline.points) return;

        const points = this.getTransformedPoints();

        points.forEach((point, index) => {
            const marker = new fabric.Circle({
                left: point.x,
                top: point.y,
                radius: 8,
                fill: this.mode === 'remove' ? '#ef4444' : '#3b82f6',
                stroke: 'white',
                strokeWidth: 2,
                originX: 'center',
                originY: 'center',
                selectable: false,
                evented: false,
                objectType: 'pointMarker',
                pointIndex: index,
                hoverCursor: this.mode === 'remove' ? 'pointer' : 'default'
            });

            this.pointMarkers.push(marker);
            this.canvas.add(marker);
        });

        this.canvas.renderAll();
    }

    /**
     * הסתרת סמני נקודות
     */
    hidePointMarkers() {
        this.pointMarkers.forEach(marker => {
            this.canvas.remove(marker);
        });
        this.pointMarkers = [];
        this.canvas.renderAll();
    }

    /**
     * הוספת האזנה לcanvas
     */
    addCanvasListeners() {
        this.canvas.on('mouse:down', this.handleCanvasClick);
    }

    /**
     * הסרת האזנה מcanvas
     */
    removeCanvasListeners() {
        this.canvas.off('mouse:down', this.handleCanvasClick);
    }

    /**
     * טיפול בלחיצה על canvas
     */
    handleCanvasClick(options) {
        if (!this.mode || !this.boundaryOutline) return;

        const pointer = this.canvas.getPointer(options.e);

        if (this.mode === 'add') {
            this.addPointAtPosition(pointer);
        } else if (this.mode === 'remove') {
            this.removePointAtPosition(pointer);
        }
    }

    /**
     * הוספת נקודה על קו הגבול
     */
    addPointAtPosition(pointer) {
        const points = this.getTransformedPoints();

        // Find closest line segment
        let minDist = Infinity;
        let insertIndex = -1;

        for (let i = 0; i < points.length; i++) {
            const p1 = points[i];
            const p2 = points[(i + 1) % points.length];

            const dist = this.pointToLineDistance(pointer, p1, p2);

            if (dist < minDist && dist < 20) {
                minDist = dist;
                insertIndex = i + 1;
            }
        }

        if (insertIndex === -1) {
            return;
        }

        // Convert pointer to local coordinates
        const localPoint = this.globalToLocal(pointer);

        // Insert new point
        const newPoints = [...this.boundaryOutline.points];
        newPoints.splice(insertIndex, 0, localPoint);

        // Update polygon
        this.updateBoundaryPoints(newPoints);

        console.log(`➕ Added point at index ${insertIndex}`);
    }

    /**
     * הסרת נקודה מהגבול
     */
    removePointAtPosition(pointer) {
        if (this.boundaryOutline.points.length <= 3) {
            alert('לא ניתן להסיר - נדרשות לפחות 3 נקודות לגבול');
            return;
        }

        const points = this.getTransformedPoints();

        // Find closest point
        let minDist = Infinity;
        let removeIndex = -1;

        for (let i = 0; i < points.length; i++) {
            const dist = Math.sqrt(
                Math.pow(pointer.x - points[i].x, 2) +
                Math.pow(pointer.y - points[i].y, 2)
            );

            if (dist < minDist && dist < 20) {
                minDist = dist;
                removeIndex = i;
            }
        }

        if (removeIndex === -1) {
            return;
        }

        // Remove point
        const newPoints = [...this.boundaryOutline.points];
        newPoints.splice(removeIndex, 1);

        // Update polygon
        this.updateBoundaryPoints(newPoints);

        console.log(`➖ Removed point at index ${removeIndex}`);
    }

    /**
     * עדכון נקודות הגבול
     */
    updateBoundaryPoints(newPoints) {
        // Create new polygon with updated points
        const newPolygon = new fabric.Polygon(newPoints, {
            fill: 'transparent',
            stroke: this.boundaryOutline.stroke || '#ef4444',
            strokeWidth: this.boundaryOutline.strokeWidth || 3,
            objectType: 'boundaryOutline',
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            lockRotation: true
        });

        // Remove old polygon and add new one
        this.canvas.remove(this.boundaryOutline);
        this.canvas.add(newPolygon);
        this.boundaryOutline = newPolygon;

        // Update gray mask
        this.updateGrayMask();

        // Update markers
        this.showPointMarkers();
        this.updatePointsCount();

        // Callback
        if (this.panelOptions.onPointsChanged) {
            this.panelOptions.onPointsChanged(newPoints, newPolygon);
        }

        this.canvas.renderAll();
    }

    /**
     * עדכון המסכה האפורה
     */
    updateGrayMask() {
        if (!this.grayMask || !this.boundaryOutline) return;

        const points = this.getTransformedPoints();
        const canvasWidth = this.canvas.width;
        const canvasHeight = this.canvas.height;
        const maskSize = 10000;

        let pathData = `M ${-maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${canvasHeight + maskSize} L ${-maskSize} ${canvasHeight + maskSize} Z `;
        pathData += `M ${Math.round(points[0].x)} ${Math.round(points[0].y)} `;
        for (let i = points.length - 1; i >= 0; i--) {
            pathData += `L ${Math.round(points[i].x)} ${Math.round(points[i].y)} `;
        }
        pathData += 'Z';

        this.grayMask.set({
            path: fabric.util.parsePath(pathData),
            stroke: null,
            strokeWidth: 0
        });
    }

    /**
     * קבלת נקודות מותמרות (world coordinates)
     */
    getTransformedPoints() {
        if (!this.boundaryOutline || !this.boundaryOutline.points) return [];

        const matrix = this.boundaryOutline.calcTransformMatrix();
        return this.boundaryOutline.points.map(p => {
            return fabric.util.transformPoint(
                { x: p.x - this.boundaryOutline.pathOffset.x, y: p.y - this.boundaryOutline.pathOffset.y },
                matrix
            );
        });
    }

    /**
     * המרת קואורדינטות גלובליות לקואורדינטות מקומיות
     */
    globalToLocal(point) {
        const matrix = this.boundaryOutline.calcTransformMatrix();
        const invertedMatrix = fabric.util.invertTransform(matrix);
        const localPoint = fabric.util.transformPoint(point, invertedMatrix);

        return {
            x: localPoint.x + this.boundaryOutline.pathOffset.x,
            y: localPoint.y + this.boundaryOutline.pathOffset.y
        };
    }

    /**
     * מרחק נקודה מקו
     */
    pointToLineDistance(point, lineStart, lineEnd) {
        const A = point.x - lineStart.x;
        const B = point.y - lineStart.y;
        const C = lineEnd.x - lineStart.x;
        const D = lineEnd.y - lineStart.y;

        const dot = A * C + B * D;
        const lenSq = C * C + D * D;
        let param = -1;

        if (lenSq !== 0) {
            param = dot / lenSq;
        }

        let xx, yy;

        if (param < 0) {
            xx = lineStart.x;
            yy = lineStart.y;
        } else if (param > 1) {
            xx = lineEnd.x;
            yy = lineEnd.y;
        } else {
            xx = lineStart.x + param * C;
            yy = lineStart.y + param * D;
        }

        const dx = point.x - xx;
        const dy = point.y - yy;

        return Math.sqrt(dx * dx + dy * dy);
    }

    /**
     * עדכון מונה נקודות
     */
    updatePointsCount() {
        const countEl = document.getElementById('boundaryPointsCount');
        if (countEl && this.boundaryOutline) {
            countEl.textContent = this.boundaryOutline.points?.length || 0;
        }
    }

    /**
     * קבלת מצב נוכחי
     */
    getState() {
        return {
            isVisible: this.isVisible(),
            mode: this.mode,
            pointsCount: this.boundaryOutline?.points?.length || 0
        };
    }

    /**
     * Debug info
     */
    debug() {
        console.group('📐 BoundaryEditPanel');
        console.log('Visible:', this.isVisible());
        console.log('Mode:', this.mode);
        console.log('Points:', this.boundaryOutline?.points?.length || 0);
        console.log('Markers:', this.pointMarkers.length);
        console.groupEnd();
    }
}
