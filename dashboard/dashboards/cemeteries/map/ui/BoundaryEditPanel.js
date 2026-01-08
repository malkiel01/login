/**
 * BoundaryEditPanel - חלון נגרר לעריכת גבול
 * Version: 3.0.0
 *
 * מצב עריכת נקודות (Toggle ON):
 *   - לחיצה כפולה על קו = הוסף נקודה
 *   - לחיצה ימנית על נקודה = תפריט הסרה
 *   - גרירת נקודה = שינוי מיקום
 *
 * מצב עריכת גבול (Toggle OFF):
 *   - נקודות קטנות להמחשה בלבד
 *   - הגבול ניתן לגרירה, הגדלה, הקטנה וסיבוב
 */

import { FloatingPanel } from './FloatingPanel.js';

export class BoundaryEditPanel extends FloatingPanel {
    constructor(canvas, options = {}) {
        super({
            title: 'עריכת גבול',
            width: 220,
            position: { top: 80, right: 20 },
            headerColor: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            className: 'boundary-edit-panel',
            onClose: () => this.onPanelClose()
        });

        this.canvas = canvas;
        this.panelOptions = {
            onPointsChanged: options.onPointsChanged || null,
            onMaskChanged: options.onMaskChanged || null,
            onClose: options.onClose || null
        };

        this.boundaryOutline = null;
        this.grayMask = null;
        this.isPointEditMode = false; // Toggle state
        this.pointMarkers = [];
        this.previewLines = [];
        this.pointsCountEl = null;
        this.contextMenu = null;
        this.selectedPointIndex = null;

        // Bind methods
        this.handleDoubleClick = this.handleDoubleClick.bind(this);
        this.handleMarkerDrag = this.handleMarkerDrag.bind(this);
        this.handleMarkerDragEnd = this.handleMarkerDragEnd.bind(this);
        this.handleCanvasMouseDown = this.handleCanvasMouseDown.bind(this);
        this.handleContextMenu = this.handleContextMenu.bind(this);
        this.handleBoundaryTransform = this.handleBoundaryTransform.bind(this);

        BoundaryEditPanel.injectPanelCSS();
    }

    static injectPanelCSS() {
        if (document.getElementById('boundaryEditPanelStyles')) return;

        const styles = document.createElement('style');
        styles.id = 'boundaryEditPanelStyles';
        styles.textContent = `
            .boundary-edit-panel .points-count-value {
                font-weight: bold;
                color: #3b82f6;
            }
            .boundary-edit-panel .toggle-btn {
                width: 100%;
                padding: 14px 16px;
                border: 2px solid #e5e7eb;
                background: white;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                transition: all 0.2s;
                color: #374151;
            }
            .boundary-edit-panel .toggle-btn:hover {
                border-color: #3b82f6;
                background: #eff6ff;
            }
            .boundary-edit-panel .toggle-btn.active {
                border-color: #10b981;
                background: #10b981;
                color: white;
            }
            .boundary-edit-panel .mode-info {
                background: #f3f4f6;
                border-radius: 8px;
                padding: 12px;
                margin-top: 12px;
                font-size: 12px;
                line-height: 1.6;
            }
            .boundary-edit-panel .mode-info-title {
                font-weight: 600;
                margin-bottom: 6px;
                color: #374151;
            }
            .boundary-edit-panel .mode-info-item {
                color: #6b7280;
                display: flex;
                align-items: center;
                gap: 6px;
                margin: 4px 0;
            }
            .boundary-point-context-menu {
                position: absolute;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                z-index: 2000;
                overflow: hidden;
                min-width: 140px;
            }
            .boundary-point-context-menu-item {
                padding: 10px 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: #374151;
                transition: background 0.15s;
            }
            .boundary-point-context-menu-item:hover {
                background: #f3f4f6;
            }
            .boundary-point-context-menu-item.danger {
                color: #ef4444;
            }
            .boundary-point-context-menu-item.danger:hover {
                background: #fef2f2;
            }
        `;
        document.head.appendChild(styles);
    }

    show(boundaryOutline, grayMask) {
        if (!boundaryOutline) {
            console.error('No boundary to edit');
            return;
        }

        this.boundaryOutline = boundaryOutline;
        this.grayMask = grayMask;
        this.isPointEditMode = false;

        if (this.canvas.wrapperEl?.parentElement) {
            this.setContainer(this.canvas.wrapperEl.parentElement);
        }

        this.buildContent();
        super.show();
        this.updatePointsCount();
        this.applyCurrentMode();

        console.log('📐 BoundaryEditPanel shown');
    }

    buildContent() {
        this.clearContent();

        // Toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'toggle-btn';
        toggleBtn.id = 'pointEditToggle';
        toggleBtn.innerHTML = '<span>✎</span> עריכת נקודות';
        toggleBtn.addEventListener('click', () => this.togglePointEditMode());
        this.appendContent(toggleBtn);

        // Mode info
        const modeInfo = document.createElement('div');
        modeInfo.className = 'mode-info';
        modeInfo.id = 'modeInfo';
        this.appendContent(modeInfo);

        // Points count footer
        const footer = document.createElement('div');
        footer.className = 'floating-panel-footer';
        footer.innerHTML = 'נקודות: <span class="points-count-value">0</span>';
        this.pointsCountEl = footer.querySelector('.points-count-value');
        this.appendContent(footer);

        this.updateModeInfo();
    }

    updateModeInfo() {
        const modeInfo = this.getContentElement().querySelector('#modeInfo');
        if (!modeInfo) return;

        if (this.isPointEditMode) {
            modeInfo.innerHTML = `
                <div class="mode-info-title">🟢 מצב עריכת נקודות</div>
                <div class="mode-info-item">👆👆 לחיצה כפולה על קו = הוסף</div>
                <div class="mode-info-item">🖱️ קליק ימני על נקודה = הסר</div>
                <div class="mode-info-item">✥ גרור נקודה = הזז</div>
            `;
        } else {
            modeInfo.innerHTML = `
                <div class="mode-info-title">🔵 מצב עריכת גבול</div>
                <div class="mode-info-item">↔️ גרור את הגבול להזזה</div>
                <div class="mode-info-item">⤡ משוך פינה להגדלה/הקטנה</div>
                <div class="mode-info-item">↻ סובב באמצעות הידית העליונה</div>
            `;
        }
    }

    togglePointEditMode() {
        this.isPointEditMode = !this.isPointEditMode;

        const toggleBtn = this.getContentElement().querySelector('#pointEditToggle');
        if (toggleBtn) {
            toggleBtn.classList.toggle('active', this.isPointEditMode);
            toggleBtn.innerHTML = this.isPointEditMode
                ? '<span>✓</span> עריכת נקודות פעילה'
                : '<span>✎</span> עריכת נקודות';
        }

        this.updateModeInfo();
        this.applyCurrentMode();
    }

    applyCurrentMode() {
        this.hideContextMenu();
        this.hidePointMarkers();
        this.hideDragPreview();

        if (this.isPointEditMode) {
            // Point edit mode - points are interactive
            this.showPointMarkers(true);
            this.addPointEditListeners();
            this.lockBoundaryForPointEdit();
        } else {
            // Boundary edit mode - show small indicator points, boundary is draggable/scalable
            this.showPointMarkers(false);
            this.removePointEditListeners();
            this.unlockBoundaryForEdit();
        }

        this.canvas.renderAll();
    }

    showPointMarkers(interactive = false) {
        this.hidePointMarkers();

        if (!this.boundaryOutline || !this.boundaryOutline.points) return;

        const points = this.getTransformedPoints();

        points.forEach((point, index) => {
            const marker = new fabric.Circle({
                left: point.x,
                top: point.y,
                radius: interactive ? 10 : 4,
                fill: interactive ? '#10b981' : '#94a3b8',
                stroke: 'white',
                strokeWidth: interactive ? 2 : 1,
                originX: 'center',
                originY: 'center',
                selectable: interactive,
                evented: interactive,
                hasControls: false,
                hasBorders: false,
                objectType: 'pointMarker',
                pointIndex: index,
                hoverCursor: interactive ? 'grab' : 'default'
            });

            if (interactive) {
                marker.on('moving', () => this.handleMarkerDrag(marker));
                marker.on('modified', () => this.handleMarkerDragEnd(marker));
            }

            this.pointMarkers.push(marker);
            this.canvas.add(marker);
        });

        this.canvas.renderAll();
    }

    hidePointMarkers() {
        this.hideDragPreview();
        this.pointMarkers.forEach(marker => {
            marker.off('moving');
            marker.off('modified');
            this.canvas.remove(marker);
        });
        this.pointMarkers = [];
    }

    lockBoundaryForPointEdit() {
        if (!this.boundaryOutline) return;

        // Remove transform listeners
        this.boundaryOutline.off('moving', this.handleBoundaryTransform);
        this.boundaryOutline.off('scaling', this.handleBoundaryTransform);
        this.boundaryOutline.off('rotating', this.handleBoundaryTransform);
        this.boundaryOutline.off('modified', this.handleBoundaryTransform);

        this.boundaryOutline.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });
    }

    unlockBoundaryForEdit() {
        if (!this.boundaryOutline) return;

        this.boundaryOutline.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            lockRotation: false,
            cornerStyle: 'circle',
            cornerSize: 12,
            cornerColor: '#3b82f6',
            transparentCorners: false
        });

        // Listen for boundary transforms to update mask
        this.boundaryOutline.on('moving', this.handleBoundaryTransform);
        this.boundaryOutline.on('scaling', this.handleBoundaryTransform);
        this.boundaryOutline.on('rotating', this.handleBoundaryTransform);
        this.boundaryOutline.on('modified', this.handleBoundaryTransform);

        this.canvas.setActiveObject(this.boundaryOutline);
    }

    handleBoundaryTransform() {
        this.updateGrayMask();
        this.showPointMarkers(false); // Update indicator points positions
        this.canvas.renderAll();
    }

    addPointEditListeners() {
        this.canvas.on('mouse:dblclick', this.handleDoubleClick);
        this.canvas.on('mouse:down', this.handleCanvasMouseDown);
        // Handle right-click context menu
        this.canvas.upperCanvasEl.addEventListener('contextmenu', this.handleContextMenu);
    }

    removePointEditListeners() {
        this.canvas.off('mouse:dblclick', this.handleDoubleClick);
        this.canvas.off('mouse:down', this.handleCanvasMouseDown);
        this.canvas.upperCanvasEl?.removeEventListener('contextmenu', this.handleContextMenu);
    }

    handleContextMenu(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!this.isPointEditMode) return;

        // Find if we clicked on a point marker
        const pointer = this.canvas.getPointer(e);
        const clickedMarker = this.findMarkerAtPoint(pointer);

        if (clickedMarker !== null) {
            this.showContextMenu(e.clientX, e.clientY, clickedMarker);
        }
    }

    handleCanvasMouseDown(options) {
        // Close context menu on any click
        if (this.contextMenu) {
            this.hideContextMenu();
        }
    }

    findMarkerAtPoint(pointer) {
        for (let i = 0; i < this.pointMarkers.length; i++) {
            const marker = this.pointMarkers[i];
            const dist = Math.sqrt(
                Math.pow(pointer.x - marker.left, 2) +
                Math.pow(pointer.y - marker.top, 2)
            );
            if (dist <= marker.radius + 5) {
                return marker.pointIndex;
            }
        }
        return null;
    }

    handleDoubleClick(options) {
        if (!this.isPointEditMode || !this.boundaryOutline) return;

        const pointer = this.canvas.getPointer(options.e);
        const points = this.getTransformedPoints();

        // Find closest line segment
        let minDist = Infinity;
        let insertIndex = -1;

        for (let i = 0; i < points.length; i++) {
            const p1 = points[i];
            const p2 = points[(i + 1) % points.length];
            const dist = this.pointToLineDistance(pointer, p1, p2);

            if (dist < minDist && dist < 25) {
                minDist = dist;
                insertIndex = i + 1;
            }
        }

        if (insertIndex === -1) return;

        const localPoint = this.globalToLocal(pointer);
        const newPoints = [...this.boundaryOutline.points];
        newPoints.splice(insertIndex, 0, localPoint);

        this.updateBoundaryPoints(newPoints);
        console.log(`➕ Added point at index ${insertIndex}`);
    }

    showContextMenu(x, y, pointIndex) {
        this.hideContextMenu();
        this.selectedPointIndex = pointIndex;

        const container = this.canvas.wrapperEl?.parentElement || document.body;
        const containerRect = container.getBoundingClientRect();

        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'boundary-point-context-menu';
        this.contextMenu.style.left = (x - containerRect.left) + 'px';
        this.contextMenu.style.top = (y - containerRect.top) + 'px';

        const removeItem = document.createElement('div');
        removeItem.className = 'boundary-point-context-menu-item danger';
        removeItem.innerHTML = '<span>➖</span> הסר נקודה';
        removeItem.addEventListener('click', () => {
            this.removePointAtIndex(this.selectedPointIndex);
            this.hideContextMenu();
        });

        this.contextMenu.appendChild(removeItem);
        container.appendChild(this.contextMenu);

        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', this.hideContextMenuHandler);
            document.addEventListener('contextmenu', this.hideContextMenuHandler);
        }, 10);
    }

    hideContextMenuHandler = () => {
        this.hideContextMenu();
    }

    hideContextMenu() {
        if (this.contextMenu) {
            this.contextMenu.remove();
            this.contextMenu = null;
        }
        document.removeEventListener('click', this.hideContextMenuHandler);
        document.removeEventListener('contextmenu', this.hideContextMenuHandler);
        this.selectedPointIndex = null;
    }

    removePointAtIndex(index) {
        if (index === null || index === undefined) return;

        if (this.boundaryOutline.points.length <= 3) {
            alert('לא ניתן להסיר - נדרשות לפחות 3 נקודות לגבול');
            return;
        }

        const newPoints = [...this.boundaryOutline.points];
        newPoints.splice(index, 1);
        this.updateBoundaryPoints(newPoints);
        console.log(`➖ Removed point at index ${index}`);
    }

    handleMarkerDrag(marker) {
        const index = marker.pointIndex;
        if (index === undefined) return;

        marker.set({ fill: '#f59e0b' });

        const points = this.getTransformedPoints();
        points[index] = { x: marker.left, y: marker.top };
        this.showDragPreview(points);

        this.canvas.renderAll();
    }

    handleMarkerDragEnd(marker) {
        const index = marker.pointIndex;
        if (index === undefined) return;

        this.hideDragPreview();

        const newPos = { x: marker.left, y: marker.top };
        const localPoint = this.globalToLocal(newPos);

        const newPoints = [...this.boundaryOutline.points];
        newPoints[index] = localPoint;

        this.updateBoundaryPoints(newPoints);
        console.log(`✥ Moved point ${index}`);
    }

    showDragPreview(points) {
        this.hideDragPreview();

        for (let i = 0; i < points.length; i++) {
            const p1 = points[i];
            const p2 = points[(i + 1) % points.length];

            const line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
                stroke: '#f59e0b',
                strokeWidth: 2,
                strokeDashArray: [8, 4],
                selectable: false,
                evented: false,
                objectType: 'dragPreviewLine'
            });

            this.previewLines.push(line);
            this.canvas.add(line);
        }

        this.pointMarkers.forEach(m => this.canvas.bringToFront(m));
    }

    hideDragPreview() {
        this.previewLines.forEach(line => this.canvas.remove(line));
        this.previewLines = [];
    }

    updateBoundaryPoints(newPoints) {
        const newPolygon = new fabric.Polygon(newPoints, {
            fill: 'transparent',
            stroke: this.boundaryOutline.stroke || '#ef4444',
            strokeWidth: this.boundaryOutline.strokeWidth || 3,
            objectType: 'boundaryOutline',
            selectable: !this.isPointEditMode,
            evented: !this.isPointEditMode,
            hasControls: !this.isPointEditMode,
            hasBorders: !this.isPointEditMode,
            lockRotation: false
        });

        this.canvas.remove(this.boundaryOutline);
        this.canvas.add(newPolygon);
        this.boundaryOutline = newPolygon;

        this.updateGrayMask();
        this.showPointMarkers(this.isPointEditMode);
        this.updatePointsCount();

        if (this.panelOptions.onPointsChanged) {
            this.panelOptions.onPointsChanged(newPoints, newPolygon);
        }

        this.canvas.renderAll();
    }

    updateGrayMask() {
        if (!this.grayMask || !this.boundaryOutline) return;

        const points = this.getTransformedPoints();
        if (points.length < 3) return;

        const canvasWidth = this.canvas.width;
        const canvasHeight = this.canvas.height;
        const maskSize = 10000;

        // Build path data for mask with hole
        let pathData = `M ${-maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${canvasHeight + maskSize} L ${-maskSize} ${canvasHeight + maskSize} Z `;
        pathData += `M ${Math.round(points[0].x)} ${Math.round(points[0].y)} `;
        for (let i = points.length - 1; i >= 0; i--) {
            pathData += `L ${Math.round(points[i].x)} ${Math.round(points[i].y)} `;
        }
        pathData += 'Z';

        // Remove old mask and create new one (more reliable than updating path)
        const oldMask = this.grayMask;
        const newMask = new fabric.Path(pathData, {
            fill: 'rgba(128, 128, 128, 0.5)',
            stroke: null,
            strokeWidth: 0,
            selectable: false,
            evented: false,
            objectType: 'grayMask'
        });

        // Insert new mask at same position
        const objects = this.canvas.getObjects();
        const oldIndex = objects.indexOf(oldMask);
        this.canvas.remove(oldMask);
        this.canvas.insertAt(newMask, oldIndex >= 0 ? oldIndex : 0);

        this.grayMask = newMask;

        // Notify about mask change
        if (this.panelOptions.onMaskChanged) {
            this.panelOptions.onMaskChanged(newMask);
        }
    }

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

    globalToLocal(point) {
        const matrix = this.boundaryOutline.calcTransformMatrix();
        const invertedMatrix = fabric.util.invertTransform(matrix);
        const localPoint = fabric.util.transformPoint(point, invertedMatrix);

        return {
            x: localPoint.x + this.boundaryOutline.pathOffset.x,
            y: localPoint.y + this.boundaryOutline.pathOffset.y
        };
    }

    pointToLineDistance(point, lineStart, lineEnd) {
        const A = point.x - lineStart.x;
        const B = point.y - lineStart.y;
        const C = lineEnd.x - lineStart.x;
        const D = lineEnd.y - lineStart.y;

        const dot = A * C + B * D;
        const lenSq = C * C + D * D;
        let param = lenSq !== 0 ? dot / lenSq : -1;

        let xx, yy;
        if (param < 0) {
            xx = lineStart.x; yy = lineStart.y;
        } else if (param > 1) {
            xx = lineEnd.x; yy = lineEnd.y;
        } else {
            xx = lineStart.x + param * C;
            yy = lineStart.y + param * D;
        }

        return Math.sqrt((point.x - xx) ** 2 + (point.y - yy) ** 2);
    }

    updatePointsCount() {
        if (this.pointsCountEl && this.boundaryOutline) {
            this.pointsCountEl.textContent = this.boundaryOutline.points?.length || 0;
        }
    }

    onPanelClose() {
        this.hidePointMarkers();
        this.hideContextMenu();
        this.removePointEditListeners();

        // Remove boundary transform listeners
        if (this.boundaryOutline) {
            this.boundaryOutline.off('moving', this.handleBoundaryTransform);
            this.boundaryOutline.off('scaling', this.handleBoundaryTransform);
            this.boundaryOutline.off('rotating', this.handleBoundaryTransform);
            this.boundaryOutline.off('modified', this.handleBoundaryTransform);
        }

        this.canvas.defaultCursor = 'default';

        if (this.panelOptions.onClose) {
            this.panelOptions.onClose();
        }
    }

    hide() {
        super.hide();
        this.onPanelClose();
    }

    getState() {
        return {
            isVisible: this.isVisible(),
            isPointEditMode: this.isPointEditMode,
            pointsCount: this.boundaryOutline?.points?.length || 0
        };
    }

    debug() {
        console.group('📐 BoundaryEditPanel');
        console.log('Visible:', this.isVisible());
        console.log('Point Edit Mode:', this.isPointEditMode);
        console.log('Points:', this.boundaryOutline?.points?.length || 0);
        console.groupEnd();
    }
}
