/**
 * Polygon Editor - עורך פוליגונים
 * מאפשר ציור ועריכה של פוליגונים על המפה
 */

class PolygonEditor {
    constructor(cemeteryMap) {
        this.map = cemeteryMap;
        this.canvas = cemeteryMap.canvas;
        this.isDrawing = false;
        this.points = [];
        this.tempLine = null;
        this.pointCircles = [];
        this.currentPolygon = null;
        this.editingPolygon = null;
        this.editPoints = [];

        this.setupEventListeners();
    }

    setupEventListeners() {
        // Double click to finish polygon
        this.canvas.on('mouse:dblclick', (opt) => {
            if (this.isDrawing && this.points.length >= 3) {
                this.finishDrawing();
            }
        });
    }

    // =====================================================
    // Drawing Mode
    // =====================================================

    startDrawing(entityData = null) {
        this.isDrawing = true;
        this.points = [];
        this.entityData = entityData;
        this.clearTempObjects();
        this.showDrawingIndicator();
    }

    handleClick(opt) {
        if (!this.isDrawing) return;

        const pointer = this.canvas.getPointer(opt.e);
        this.addPoint(pointer.x, pointer.y);
    }

    handleMove(opt) {
        if (!this.isDrawing || this.points.length === 0) return;

        const pointer = this.canvas.getPointer(opt.e);
        this.updateTempLine(pointer.x, pointer.y);
    }

    addPoint(x, y) {
        this.points.push({ x, y });

        // Add visual point marker
        const circle = new fabric.Circle({
            left: x - 5,
            top: y - 5,
            radius: 5,
            fill: '#1976D2',
            stroke: '#ffffff',
            strokeWidth: 2,
            selectable: false,
            evented: false,
            isDrawingPoint: true
        });
        this.pointCircles.push(circle);
        this.canvas.add(circle);

        // Draw line from previous point
        if (this.points.length > 1) {
            const prevPoint = this.points[this.points.length - 2];
            const line = new fabric.Line([prevPoint.x, prevPoint.y, x, y], {
                stroke: '#1976D2',
                strokeWidth: 2,
                selectable: false,
                evented: false,
                isDrawingLine: true
            });
            this.canvas.add(line);
        }

        // Update temp line
        this.updateTempLine(x, y);
        this.canvas.renderAll();
    }

    updateTempLine(x, y) {
        // Remove old temp line
        if (this.tempLine) {
            this.canvas.remove(this.tempLine);
        }

        if (this.points.length === 0) return;

        // Draw line from last point to cursor
        const lastPoint = this.points[this.points.length - 1];
        this.tempLine = new fabric.Line([lastPoint.x, lastPoint.y, x, y], {
            stroke: '#1976D2',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            selectable: false,
            evented: false
        });
        this.canvas.add(this.tempLine);

        // Also draw closing line if we have 3+ points
        if (this.points.length >= 3) {
            const firstPoint = this.points[0];
            const closingLine = new fabric.Line([x, y, firstPoint.x, firstPoint.y], {
                stroke: '#1976D2',
                strokeWidth: 1,
                strokeDashArray: [3, 3],
                opacity: 0.5,
                selectable: false,
                evented: false,
                isTempClosing: true
            });
            // Remove old closing line
            this.canvas.getObjects().filter(o => o.isTempClosing).forEach(o => this.canvas.remove(o));
            this.canvas.add(closingLine);
        }

        this.canvas.renderAll();
    }

    finishDrawing() {
        if (this.points.length < 3) {
            this.cancelDrawing();
            return;
        }

        this.isDrawing = false;
        this.clearTempObjects();
        this.hideDrawingIndicator();

        // Show entity selection dialog if no entity was specified
        if (!this.entityData) {
            this.showEntitySelector();
        } else {
            this.createPolygon(this.entityData);
        }
    }

    cancelDrawing() {
        this.isDrawing = false;
        this.points = [];
        this.entityData = null;
        this.clearTempObjects();
        this.hideDrawingIndicator();
        this.map.setTool('select');
    }

    clearTempObjects() {
        // Remove temp line
        if (this.tempLine) {
            this.canvas.remove(this.tempLine);
            this.tempLine = null;
        }

        // Remove point circles
        this.pointCircles.forEach(c => this.canvas.remove(c));
        this.pointCircles = [];

        // Remove drawing lines
        this.canvas.getObjects().filter(o => o.isDrawingLine || o.isDrawingPoint || o.isTempClosing)
            .forEach(o => this.canvas.remove(o));

        this.canvas.renderAll();
    }

    createPolygon(entityData) {
        const color = this.map.config.colors[entityData.type] || '#999999';

        const polygon = new fabric.Polygon(this.points, {
            fill: this.map.hexToRgba(color, 0.2),
            stroke: color,
            strokeWidth: 2,
            selectable: true,
            hasControls: true,
            objectCaching: false,
            entityData: entityData
        });

        // Create label
        const bounds = polygon.getBoundingRect();
        const label = new fabric.Text(entityData.name || 'חדש', {
            left: bounds.left + bounds.width / 2,
            top: bounds.top + bounds.height / 2,
            fontSize: 14,
            fontFamily: 'Arial, sans-serif',
            fill: '#333333',
            textAlign: 'center',
            originX: 'center',
            originY: 'center',
            selectable: false,
            evented: false
        });

        // Group polygon and label
        const group = new fabric.Group([polygon, label], {
            selectable: true,
            hasControls: true,
            entityData: entityData
        });

        this.canvas.add(group);
        this.canvas.setActiveObject(group);
        this.canvas.renderAll();

        // Reset for next drawing
        this.points = [];
        this.entityData = null;

        return group;
    }

    // =====================================================
    // Entity Selector Dialog
    // =====================================================

    showEntitySelector() {
        // Create modal for selecting which entity to assign the polygon to
        const entities = this.map.entities.filter(e => !e.mapPolygon);

        if (entities.length === 0) {
            alert('אין ישויות זמינות ללא פוליגון');
            this.cancelDrawing();
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'entity-selector-modal';
        modal.innerHTML = `
            <div class="entity-selector-content">
                <h3>בחר ישות לפוליגון</h3>
                <div class="entity-selector-list">
                    ${entities.map(e => `
                        <div class="entity-selector-item" data-id="${e.unicId}">
                            <span class="item-name">${this.map.getEntityName(e, this.map.config.entityConfig[this.map.config.entityType]?.children)}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="entity-selector-actions">
                    <button class="btn-secondary" id="cancelEntitySelect">ביטול</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Add styles if not already added
        if (!document.getElementById('entitySelectorStyles')) {
            const styles = document.createElement('style');
            styles.id = 'entitySelectorStyles';
            styles.textContent = `
                .entity-selector-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 3000;
                }
                .entity-selector-content {
                    background: #ffffff;
                    border-radius: 8px;
                    padding: 20px;
                    max-width: 400px;
                    width: 90%;
                    max-height: 80vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                }
                .entity-selector-content h3 {
                    margin: 0 0 16px 0;
                    font-size: 18px;
                }
                .entity-selector-list {
                    flex: 1;
                    overflow-y: auto;
                    max-height: 300px;
                }
                .entity-selector-item {
                    padding: 12px;
                    border: 1px solid #e0e0e0;
                    border-radius: 6px;
                    margin-bottom: 8px;
                    cursor: pointer;
                    transition: 0.2s;
                }
                .entity-selector-item:hover {
                    background: #f5f5f5;
                    border-color: #1976D2;
                }
                .entity-selector-actions {
                    margin-top: 16px;
                    display: flex;
                    justify-content: flex-end;
                }
            `;
            document.head.appendChild(styles);
        }

        // Event handlers
        modal.querySelectorAll('.entity-selector-item').forEach(item => {
            item.addEventListener('click', () => {
                const unicId = item.dataset.id;
                const entity = entities.find(e => e.unicId === unicId);
                const childType = this.map.config.entityConfig[this.map.config.entityType]?.children;

                this.createPolygon({
                    id: entity.id,
                    unicId: entity.unicId,
                    type: childType,
                    name: this.map.getEntityName(entity, childType),
                    data: entity
                });

                modal.remove();
            });
        });

        document.getElementById('cancelEntitySelect').addEventListener('click', () => {
            this.points = [];
            modal.remove();
        });
    }

    // =====================================================
    // Editing Mode
    // =====================================================

    editPolygon(unicId) {
        // Find the polygon group
        const group = this.canvas.getObjects().find(o => o.entityData?.unicId === unicId);
        if (!group || !group._objects) return;

        const polygon = group._objects.find(o => o.type === 'polygon');
        if (!polygon) return;

        this.editingPolygon = polygon;
        this.editingGroup = group;

        // Ungroup to access polygon directly
        this.canvas.remove(group);

        // Add polygon separately
        polygon.set({
            selectable: true,
            hasControls: false,
            hasBorders: true,
            lockMovementX: false,
            lockMovementY: false
        });
        this.canvas.add(polygon);

        // Add control points at each vertex
        this.showEditPoints(polygon);

        this.canvas.renderAll();
    }

    showEditPoints(polygon) {
        this.clearEditPoints();

        const points = polygon.points;
        const matrix = polygon.calcTransformMatrix();

        points.forEach((point, index) => {
            // Transform point to canvas coordinates
            const transformed = fabric.util.transformPoint(
                { x: point.x - polygon.pathOffset.x, y: point.y - polygon.pathOffset.y },
                matrix
            );

            const circle = new fabric.Circle({
                left: transformed.x - 6,
                top: transformed.y - 6,
                radius: 6,
                fill: '#ffffff',
                stroke: '#1976D2',
                strokeWidth: 2,
                selectable: true,
                hasControls: false,
                hasBorders: false,
                originX: 'center',
                originY: 'center',
                isEditPoint: true,
                pointIndex: index,
                parentPolygon: polygon
            });

            circle.on('moving', (e) => this.onEditPointMove(e, circle, polygon));
            circle.on('modified', () => this.onEditPointModified(polygon));

            this.editPoints.push(circle);
            this.canvas.add(circle);
        });

        this.canvas.renderAll();
    }

    onEditPointMove(e, circle, polygon) {
        const index = circle.pointIndex;
        const matrix = polygon.calcTransformMatrix();
        const invertedMatrix = fabric.util.invertTransform(matrix);

        // Transform canvas coordinates back to polygon coordinates
        const transformed = fabric.util.transformPoint(
            { x: circle.left, y: circle.top },
            invertedMatrix
        );

        polygon.points[index] = {
            x: transformed.x + polygon.pathOffset.x,
            y: transformed.y + polygon.pathOffset.y
        };

        this.canvas.renderAll();
    }

    onEditPointModified(polygon) {
        // Update polygon after point modification
        polygon.setCoords();
        this.canvas.renderAll();
    }

    finishEditing() {
        if (!this.editingPolygon) return;

        // Clear edit points
        this.clearEditPoints();

        // Find associated label (if any)
        const label = this.editingGroup?._objects?.find(o => o.type === 'text');

        // Recreate group with updated polygon
        if (label) {
            // Update label position
            const bounds = this.editingPolygon.getBoundingRect();
            label.set({
                left: bounds.left + bounds.width / 2,
                top: bounds.top + bounds.height / 2
            });

            this.canvas.remove(this.editingPolygon);

            const newGroup = new fabric.Group([this.editingPolygon, label], {
                selectable: true,
                hasControls: true,
                entityData: this.editingGroup?.entityData
            });

            this.canvas.add(newGroup);
        }

        this.editingPolygon = null;
        this.editingGroup = null;
        this.canvas.renderAll();
    }

    clearEditPoints() {
        this.editPoints.forEach(point => this.canvas.remove(point));
        this.editPoints = [];
    }

    // =====================================================
    // Drawing Indicator
    // =====================================================

    showDrawingIndicator() {
        let indicator = document.querySelector('.drawing-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'drawing-indicator';
            indicator.innerHTML = `
                <span>לחץ להוספת נקודות | לחץ פעמיים לסיום | ESC לביטול</span>
            `;
            document.body.appendChild(indicator);
        }
        indicator.classList.add('visible');
    }

    hideDrawingIndicator() {
        const indicator = document.querySelector('.drawing-indicator');
        if (indicator) {
            indicator.classList.remove('visible');
        }
    }

    // =====================================================
    // Add point to existing polygon
    // =====================================================

    addPointToPolygon(polygon, x, y, afterIndex) {
        const points = [...polygon.points];
        points.splice(afterIndex + 1, 0, { x, y });
        polygon.set({ points });
        this.showEditPoints(polygon);
    }

    removePointFromPolygon(polygon, index) {
        if (polygon.points.length <= 3) {
            alert('פוליגון חייב להכיל לפחות 3 נקודות');
            return;
        }

        const points = [...polygon.points];
        points.splice(index, 1);
        polygon.set({ points });
        this.showEditPoints(polygon);
    }
}

// Initialize polygon editor when map is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait for cemeteryMap to be initialized
    const checkInterval = setInterval(() => {
        if (typeof cemeteryMap !== 'undefined' && cemeteryMap.canvas) {
            window.polygonEditor = new PolygonEditor(cemeteryMap);

            // Connect draw button
            document.getElementById('btnDraw')?.addEventListener('click', () => {
                if (cemeteryMap.currentTool === 'draw') {
                    window.polygonEditor.startDrawing();
                }
            });

            clearInterval(checkInterval);
        }
    }, 100);
});
