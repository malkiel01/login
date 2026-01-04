/**
 * Canvas Utilities - פונקציות עזר ל-Fabric.js Canvas
 * פעולות שימושיות על Canvas ואובייקטים
 */

/**
 * הגדרת viewport center
 * @param {fabric.Canvas} canvas
 * @param {Object} point - {x, y}
 */
export function setCanvasCenter(canvas, point) {
    const zoom = canvas.getZoom();
    canvas.absolutePan({
        x: -point.x * zoom + canvas.width / 2,
        y: -point.y * zoom + canvas.height / 2
    });
}

/**
 * קבלת מרכז viewport
 * @param {fabric.Canvas} canvas
 * @returns {Object} - {x, y}
 */
export function getCanvasCenter(canvas) {
    const vpt = canvas.viewportTransform;
    const zoom = canvas.getZoom();

    return {
        x: (-vpt[4] + canvas.width / 2) / zoom,
        y: (-vpt[5] + canvas.height / 2) / zoom
    };
}

/**
 * קבלת כל האובייקטים לפי תכונה
 * @param {fabric.Canvas} canvas
 * @param {string} property - שם התכונה
 * @param {*} value - ערך התכונה
 * @returns {Array}
 */
export function getObjectsByProperty(canvas, property, value) {
    return canvas.getObjects().filter(obj => obj[property] === value);
}

/**
 * הסרת כל האובייקטים לפי תכונה
 * @param {fabric.Canvas} canvas
 * @param {string} property - שם התכונה
 * @param {*} value - ערך התכונה
 */
export function removeObjectsByProperty(canvas, property, value) {
    const objects = getObjectsByProperty(canvas, property, value);
    objects.forEach(obj => canvas.remove(obj));
}

/**
 * העברת אובייקט לשכבה העליונה
 * @param {fabric.Canvas} canvas
 * @param {fabric.Object} object
 */
export function bringToFront(canvas, object) {
    canvas.bringToFront(object);
    canvas.renderAll();
}

/**
 * העברת אובייקט לשכבה התחתונה
 * @param {fabric.Canvas} canvas
 * @param {fabric.Object} object
 */
export function sendToBack(canvas, object) {
    canvas.sendToBack(object);
    canvas.renderAll();
}

/**
 * העברת מספר אובייקטים לשכבה העליונה לפי סדר
 * @param {fabric.Canvas} canvas
 * @param {Array} objects
 */
export function bringObjectsToFront(canvas, objects) {
    objects.forEach(obj => canvas.bringToFront(obj));
    canvas.renderAll();
}

/**
 * שמירת Canvas כתמונה (PNG)
 * @param {fabric.Canvas} canvas
 * @param {Object} options
 * @returns {string} - Data URL
 */
export function exportCanvasAsImage(canvas, options = {}) {
    const {
        format = 'png',
        quality = 1,
        multiplier = 1,
        backgroundColor = null
    } = options;

    const dataURL = canvas.toDataURL({
        format,
        quality,
        multiplier,
        backgroundColor: backgroundColor || canvas.backgroundColor
    });

    return dataURL;
}

/**
 * הורדת Canvas כקובץ תמונה
 * @param {fabric.Canvas} canvas
 * @param {string} filename
 * @param {Object} options
 */
export function downloadCanvasAsImage(canvas, filename = 'map.png', options = {}) {
    const dataURL = exportCanvasAsImage(canvas, options);

    const link = document.createElement('a');
    link.download = filename;
    link.href = dataURL;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * ניקוי מלא של Canvas
 * @param {fabric.Canvas} canvas
 * @param {boolean} resetViewport - האם לאפס את ה-viewport
 */
export function clearCanvas(canvas, resetViewport = true) {
    canvas.clear();

    if (resetViewport) {
        canvas.setZoom(1);
        canvas.viewportTransform = [1, 0, 0, 1, 0, 0];
    }

    canvas.renderAll();
}

/**
 * קבלת גבולות כל האובייקטים (bounding box)
 * @param {fabric.Canvas} canvas
 * @param {Array} objects - אובייקטים (אופציונלי)
 * @returns {Object} - {left, top, width, height}
 */
export function getAllObjectsBounds(canvas, objects = null) {
    const objs = objects || canvas.getObjects().filter(obj => obj.visible);

    if (objs.length === 0) {
        return { left: 0, top: 0, width: canvas.width, height: canvas.height };
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    objs.forEach(obj => {
        const bounds = obj.getBoundingRect();
        minX = Math.min(minX, bounds.left);
        minY = Math.min(minY, bounds.top);
        maxX = Math.max(maxX, bounds.left + bounds.width);
        maxY = Math.max(maxY, bounds.top + bounds.height);
    });

    return {
        left: minX,
        top: minY,
        width: maxX - minX,
        height: maxY - minY
    };
}

/**
 * התאמת zoom כדי להציג את כל האובייקטים
 * @param {fabric.Canvas} canvas
 * @param {Array} objects - אובייקטים (אופציונלי)
 * @param {number} padding - ריווח (0-1)
 */
export function zoomToFitObjects(canvas, objects = null, padding = 0.1) {
    const bounds = getAllObjectsBounds(canvas, objects);

    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;

    const zoomX = canvasWidth / bounds.width;
    const zoomY = canvasHeight / bounds.height;

    let zoom = Math.min(zoomX, zoomY) * (1 - padding);
    zoom = Math.max(0.1, Math.min(5, zoom)); // הגבלת zoom

    canvas.setZoom(zoom);

    const centerX = bounds.left + bounds.width / 2;
    const centerY = bounds.top + bounds.height / 2;

    setCanvasCenter(canvas, { x: centerX, y: centerY });

    canvas.renderAll();
}

/**
 * יצירת רשת (grid)
 * @param {fabric.Canvas} canvas
 * @param {number} gridSize - גודל הרשת
 * @param {Object} options - אפשרויות
 * @returns {fabric.Group} - קבוצת קווי הרשת
 */
export function createGrid(canvas, gridSize = 50, options = {}) {
    const {
        color = '#e0e0e0',
        strokeWidth = 1,
        selectable = false,
        evented = false
    } = options;

    const width = canvas.width;
    const height = canvas.height;
    const lines = [];

    // קווים אנכיים
    for (let x = 0; x <= width; x += gridSize) {
        const line = new fabric.Line([x, 0, x, height], {
            stroke: color,
            strokeWidth,
            selectable: false,
            evented: false
        });
        lines.push(line);
    }

    // קווים אופקיים
    for (let y = 0; y <= height; y += gridSize) {
        const line = new fabric.Line([0, y, width, y], {
            stroke: color,
            strokeWidth,
            selectable: false,
            evented: false
        });
        lines.push(line);
    }

    const grid = new fabric.Group(lines, {
        selectable,
        evented,
        isGrid: true
    });

    return grid;
}

/**
 * הצמדה לרשת (snap to grid)
 * @param {number} value - ערך
 * @param {number} gridSize - גודל הרשת
 * @returns {number} - ערך מוצמד
 */
export function snapToGrid(value, gridSize) {
    return Math.round(value / gridSize) * gridSize;
}

/**
 * הצמדת נקודה לרשת
 * @param {Object} point - {x, y}
 * @param {number} gridSize - גודל הרשת
 * @returns {Object} - {x, y} מוצמדת
 */
export function snapPointToGrid(point, gridSize) {
    return {
        x: snapToGrid(point.x, gridSize),
        y: snapToGrid(point.y, gridSize)
    };
}

/**
 * יצירת tooltip לאובייקט
 * @param {fabric.Canvas} canvas
 * @param {fabric.Object} object
 * @param {string} text
 * @param {Object} options
 */
export function addTooltip(canvas, object, text, options = {}) {
    const {
        fontSize = 14,
        fill = '#000',
        backgroundColor = '#fff',
        padding = 5
    } = options;

    const tooltip = new fabric.Text(text, {
        fontSize,
        fill,
        backgroundColor,
        visible: false,
        selectable: false,
        evented: false,
        padding,
        isTooltip: true,
        targetObject: object
    });

    canvas.add(tooltip);

    object.on('mouseover', () => {
        const pointer = canvas.getPointer(event);
        tooltip.set({
            left: pointer.x + 10,
            top: pointer.y - 10,
            visible: true
        });
        canvas.bringToFront(tooltip);
        canvas.renderAll();
    });

    object.on('mouseout', () => {
        tooltip.set({ visible: false });
        canvas.renderAll();
    });

    return tooltip;
}

/**
 * יצירת cursor מותאם אישית
 * @param {string} cursorType - סוג cursor
 * @returns {string} - CSS cursor
 */
export function getCustomCursor(cursorType) {
    const cursors = {
        draw: 'crosshair',
        pan: 'grab',
        panning: 'grabbing',
        select: 'pointer',
        move: 'move',
        resize: 'nwse-resize',
        rotate: 'url("data:image/svg+xml;base64,..."), auto'
    };

    return cursors[cursorType] || 'default';
}

/**
 * שכפול אובייקט
 * @param {fabric.Object} object
 * @param {Object} options - אפשרויות override
 * @returns {Promise<fabric.Object>}
 */
export function cloneObject(object, options = {}) {
    return new Promise((resolve) => {
        object.clone((cloned) => {
            cloned.set(options);
            resolve(cloned);
        });
    });
}

/**
 * שכפול מספר אובייקטים
 * @param {Array} objects
 * @param {Object} options
 * @returns {Promise<Array>}
 */
export async function cloneObjects(objects, options = {}) {
    const promises = objects.map(obj => cloneObject(obj, options));
    return await Promise.all(promises);
}

/**
 * הגדרת opacity לאובייקטים
 * @param {fabric.Canvas} canvas
 * @param {string} property - תכונה לסינון
 * @param {*} value - ערך התכונה
 * @param {number} opacity - opacity (0-1)
 */
export function setObjectsOpacity(canvas, property, value, opacity) {
    const objects = getObjectsByProperty(canvas, property, value);
    objects.forEach(obj => obj.set({ opacity }));
    canvas.renderAll();
}

/**
 * הדגשת אובייקט (highlight)
 * @param {fabric.Canvas} canvas
 * @param {fabric.Object} object
 * @param {Object} options
 */
export function highlightObject(object, options = {}) {
    const {
        strokeColor = '#FFD700',
        strokeWidth = 4,
        duration = 500
    } = options;

    const originalStroke = object.stroke;
    const originalStrokeWidth = object.strokeWidth;

    object.set({
        stroke: strokeColor,
        strokeWidth: strokeWidth
    });

    object.canvas.renderAll();

    if (duration > 0) {
        setTimeout(() => {
            object.set({
                stroke: originalStroke,
                strokeWidth: originalStrokeWidth
            });
            object.canvas.renderAll();
        }, duration);
    }
}

/**
 * יצירת אנימציה לאובייקט
 * @param {fabric.Object} object
 * @param {Object} properties - תכונות לאנימציה
 * @param {Object} options - אפשרויות אנימציה
 * @returns {Promise}
 */
export function animateObject(object, properties, options = {}) {
    const {
        duration = 500,
        easing = fabric.util.ease.easeInOutQuad,
        onChange = null,
        onComplete = null
    } = options;

    return new Promise((resolve) => {
        object.animate(properties, {
            duration,
            easing,
            onChange: () => {
                object.canvas.renderAll();
                if (onChange) onChange();
            },
            onComplete: () => {
                if (onComplete) onComplete();
                resolve();
            }
        });
    });
}

/**
 * קבלת נקודה ב-canvas מאירוע עכבר
 * @param {fabric.Canvas} canvas
 * @param {Event} event
 * @returns {Object} - {x, y}
 */
export function getCanvasPointer(canvas, event) {
    return canvas.getPointer(event);
}

/**
 * המרת נקודה מ-screen ל-canvas coordinates
 * @param {fabric.Canvas} canvas
 * @param {Object} screenPoint - {x, y}
 * @returns {Object} - {x, y}
 */
export function screenToCanvas(canvas, screenPoint) {
    const vpt = canvas.viewportTransform;
    const zoom = canvas.getZoom();

    return {
        x: (screenPoint.x - vpt[4]) / zoom,
        y: (screenPoint.y - vpt[5]) / zoom
    };
}

/**
 * המרת נקודה מ-canvas ל-screen coordinates
 * @param {fabric.Canvas} canvas
 * @param {Object} canvasPoint - {x, y}
 * @returns {Object} - {x, y}
 */
export function canvasToScreen(canvas, canvasPoint) {
    const vpt = canvas.viewportTransform;
    const zoom = canvas.getZoom();

    return {
        x: canvasPoint.x * zoom + vpt[4],
        y: canvasPoint.y * zoom + vpt[5]
    };
}
