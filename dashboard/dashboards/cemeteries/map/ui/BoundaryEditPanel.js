/**
 * BoundaryEditPanel - חלון נגרר לעריכת נקודות גבול
 * Version: 1.0.0
 *
 * חלון צף שמאפשר הוספה והסרה של נקודות בגבול המפה
 * Usage:
 *   const panel = new BoundaryEditPanel(canvas, {
 *     onPointsChanged: (points) => {...}
 *   });
 *   panel.show(boundaryOutline);
 */

export class BoundaryEditPanel {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.options = {
            onPointsChanged: options.onPointsChanged || null,
            onClose: options.onClose || null
        };

        this.boundaryOutline = null;
        this.grayMask = null;
        this.mode = null; // 'add' | 'remove' | null
        this.pointMarkers = [];
        this.panel = null;
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };

        // Bind methods
        this.handleCanvasClick = this.handleCanvasClick.bind(this);
        this.handlePanelMouseDown = this.handlePanelMouseDown.bind(this);
        this.handlePanelMouseMove = this.handlePanelMouseMove.bind(this);
        this.handlePanelMouseUp = this.handlePanelMouseUp.bind(this);
    }

    /**
     * הזרקת CSS
     */
    static injectCSS() {
        if (document.getElementById('boundaryEditPanelStyles')) return;

        const styles = document.createElement('style');
        styles.id = 'boundaryEditPanelStyles';
        styles.textContent = `
            .boundary-edit-panel {
                position: absolute;
                top: 80px;
                right: 20px;
                width: 200px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                z-index: 1000;
                overflow: hidden;
                font-family: inherit;
            }
            .boundary-edit-panel-header {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                padding: 12px 16px;
                font-weight: 600;
                font-size: 14px;
                cursor: move;
                display: flex;
                justify-content: space-between;
                align-items: center;
                user-select: none;
            }
            .boundary-edit-panel-close {
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .boundary-edit-panel-close:hover {
                background: rgba(255,255,255,0.3);
            }
            .boundary-edit-panel-content {
                padding: 16px;
            }
            .boundary-edit-panel-info {
                font-size: 12px;
                color: #6b7280;
                margin-bottom: 12px;
                line-height: 1.5;
            }
            .boundary-edit-btn {
                width: 100%;
                padding: 12px 16px;
                margin-bottom: 8px;
                border: 2px solid #e5e7eb;
                background: white;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.2s;
                color: #374151;
            }
            .boundary-edit-btn:hover {
                border-color: #3b82f6;
                background: #eff6ff;
            }
            .boundary-edit-btn.active {
                border-color: #3b82f6;
                background: #3b82f6;
                color: white;
            }
            .boundary-edit-btn-icon {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }
            .boundary-edit-btn:not(.active) .boundary-edit-btn-icon {
                background: #f3f4f6;
            }
            .boundary-edit-btn.active .boundary-edit-btn-icon {
                background: rgba(255,255,255,0.2);
            }
            .boundary-edit-points-count {
                background: #f3f4f6;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                color: #4b5563;
                text-align: center;
                margin-top: 8px;
            }
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

        BoundaryEditPanel.injectCSS();

        this.boundaryOutline = boundaryOutline;
        this.grayMask = grayMask;
        this.mode = null;

        // Create panel if not exists
        if (!this.panel) {
            this.createPanel();
        }

        this.panel.style.display = 'block';
        this.updatePointsCount();
        this.showPointMarkers();

        console.log('📐 BoundaryEditPanel shown');
    }

    /**
     * הסתרת הפאנל
     */
    hide() {
        if (this.panel) {
            this.panel.style.display = 'none';
        }
        this.mode = null;
        this.hidePointMarkers();
        this.removeCanvasListeners();

        if (this.options.onClose) {
            this.options.onClose();
        }

        console.log('📐 BoundaryEditPanel hidden');
    }

    /**
     * יצירת הפאנל
     */
    createPanel() {
        const container = this.canvas.wrapperEl?.parentElement || document.body;

        this.panel = document.createElement('div');
        this.panel.className = 'boundary-edit-panel';
        this.panel.innerHTML = `
            <div class="boundary-edit-panel-header">
                <span>עריכת נקודות גבול</span>
                <button class="boundary-edit-panel-close">✕</button>
            </div>
            <div class="boundary-edit-panel-content">
                <div class="boundary-edit-panel-info">
                    בחר מצב עריכה ולחץ על המפה
                </div>
                <button class="boundary-edit-btn" data-mode="add">
                    <span class="boundary-edit-btn-icon">➕</span>
                    <span>הוסף נקודה</span>
                </button>
                <button class="boundary-edit-btn" data-mode="remove">
                    <span class="boundary-edit-btn-icon">➖</span>
                    <span>הסר נקודה</span>
                </button>
                <div class="boundary-edit-points-count">
                    נקודות: <span id="boundaryPointsCount">0</span>
                </div>
            </div>
        `;

        container.appendChild(this.panel);

        // Event listeners
        const header = this.panel.querySelector('.boundary-edit-panel-header');
        header.addEventListener('mousedown', this.handlePanelMouseDown);

        const closeBtn = this.panel.querySelector('.boundary-edit-panel-close');
        closeBtn.addEventListener('click', () => this.hide());

        const buttons = this.panel.querySelectorAll('.boundary-edit-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => this.setMode(btn.dataset.mode));
        });
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
        const buttons = this.panel.querySelectorAll('.boundary-edit-btn');
        buttons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === this.mode);
        });

        // Update info text
        const info = this.panel.querySelector('.boundary-edit-panel-info');
        if (this.mode === 'add') {
            info.textContent = 'לחץ על קו הגבול להוספת נקודה';
            this.canvas.defaultCursor = 'crosshair';
        } else if (this.mode === 'remove') {
            info.textContent = 'לחץ על נקודה כדי להסיר אותה';
            this.canvas.defaultCursor = 'pointer';
        } else {
            info.textContent = 'בחר מצב עריכה ולחץ על המפה';
            this.canvas.defaultCursor = 'default';
        }

        // Update markers visibility
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

            if (dist < minDist && dist < 20) { // Max 20px from line
                minDist = dist;
                insertIndex = i + 1;
            }
        }

        if (insertIndex === -1) {
            return; // Not close enough to any line
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

            if (dist < minDist && dist < 20) { // Max 20px from point
                minDist = dist;
                removeIndex = i;
            }
        }

        if (removeIndex === -1) {
            return; // Not close enough to any point
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
        // Store current position
        const currentLeft = this.boundaryOutline.left;
        const currentTop = this.boundaryOutline.top;

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
        if (this.options.onPointsChanged) {
            this.options.onPointsChanged(newPoints, newPolygon);
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
     * המרת קואורדינטות גלובליות לקואורדינטות מקומיות של הפוליגון
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
        const countEl = this.panel?.querySelector('#boundaryPointsCount');
        if (countEl && this.boundaryOutline) {
            countEl.textContent = this.boundaryOutline.points?.length || 0;
        }
    }

    /**
     * Drag handlers for panel
     */
    handlePanelMouseDown(e) {
        if (e.target.classList.contains('boundary-edit-panel-close')) return;

        this.isDragging = true;
        const rect = this.panel.getBoundingClientRect();
        this.dragOffset = {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };

        document.addEventListener('mousemove', this.handlePanelMouseMove);
        document.addEventListener('mouseup', this.handlePanelMouseUp);
    }

    handlePanelMouseMove(e) {
        if (!this.isDragging) return;

        const container = this.panel.parentElement;
        const containerRect = container.getBoundingClientRect();

        let newLeft = e.clientX - containerRect.left - this.dragOffset.x;
        let newTop = e.clientY - containerRect.top - this.dragOffset.y;

        // Keep within bounds
        newLeft = Math.max(0, Math.min(newLeft, containerRect.width - this.panel.offsetWidth));
        newTop = Math.max(0, Math.min(newTop, containerRect.height - this.panel.offsetHeight));

        this.panel.style.left = newLeft + 'px';
        this.panel.style.top = newTop + 'px';
        this.panel.style.right = 'auto';
    }

    handlePanelMouseUp() {
        this.isDragging = false;
        document.removeEventListener('mousemove', this.handlePanelMouseMove);
        document.removeEventListener('mouseup', this.handlePanelMouseUp);
    }

    /**
     * קבלת מצב נוכחי
     */
    getState() {
        return {
            isVisible: this.panel?.style.display !== 'none',
            mode: this.mode,
            pointsCount: this.boundaryOutline?.points?.length || 0
        };
    }

    /**
     * Debug info
     */
    debug() {
        console.group('📐 BoundaryEditPanel');
        console.log('Visible:', this.panel?.style.display !== 'none');
        console.log('Mode:', this.mode);
        console.log('Points:', this.boundaryOutline?.points?.length || 0);
        console.log('Markers:', this.pointMarkers.length);
        console.groupEnd();
    }
}
