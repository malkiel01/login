/**
 * Geometry Utilities - פונקציות עזר גיאומטריות
 * חישובים גיאומטריים לפוליגונים, נקודות וקווים
 */

/**
 * בדיקה אם נקודה נמצאת בתוך פוליגון (Ray Casting Algorithm)
 * @param {Object} point - {x, y}
 * @param {Array} polygon - [{x, y}, ...]
 * @returns {boolean}
 */
export function isPointInPolygon(point, polygon) {
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
 * חישוב שטח פוליגון
 * @param {Array} polygon - [{x, y}, ...]
 * @returns {number} - השטח
 */
export function getPolygonArea(polygon) {
    let area = 0;

    for (let i = 0; i < polygon.length; i++) {
        const j = (i + 1) % polygon.length;
        area += polygon[i].x * polygon[j].y;
        area -= polygon[j].x * polygon[i].y;
    }

    return Math.abs(area / 2);
}

/**
 * חישוב מרכז פוליגון (centroid)
 * @param {Array} polygon - [{x, y}, ...]
 * @returns {Object} - {x, y}
 */
export function getPolygonCenter(polygon) {
    let x = 0;
    let y = 0;

    polygon.forEach(point => {
        x += point.x;
        y += point.y;
    });

    return {
        x: x / polygon.length,
        y: y / polygon.length
    };
}

/**
 * חישוב מרחק בין שתי נקודות
 * @param {Object} p1 - {x, y}
 * @param {Object} p2 - {x, y}
 * @returns {number}
 */
export function distance(p1, p2) {
    const dx = p2.x - p1.x;
    const dy = p2.y - p1.y;
    return Math.sqrt(dx * dx + dy * dy);
}

/**
 * בדיקה אם נקודה קרובה לקו
 * @param {Object} point - {x, y}
 * @param {Object} lineStart - {x, y}
 * @param {Object} lineEnd - {x, y}
 * @param {number} threshold - סף המרחק
 * @returns {boolean}
 */
export function isPointNearLine(point, lineStart, lineEnd, threshold = 5) {
    const dist = distanceToLine(point, lineStart, lineEnd);
    return dist <= threshold;
}

/**
 * חישוב מרחק מנקודה לקו
 * @param {Object} point - {x, y}
 * @param {Object} lineStart - {x, y}
 * @param {Object} lineEnd - {x, y}
 * @returns {number}
 */
export function distanceToLine(point, lineStart, lineEnd) {
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
 * קבלת נקודות תיבה תוחמת (bounding box)
 * @param {Array} points - [{x, y}, ...]
 * @returns {Object} - {minX, minY, maxX, maxY, width, height}
 */
export function getBoundingBox(points) {
    if (points.length === 0) {
        return { minX: 0, minY: 0, maxX: 0, maxY: 0, width: 0, height: 0 };
    }

    let minX = points[0].x;
    let minY = points[0].y;
    let maxX = points[0].x;
    let maxY = points[0].y;

    points.forEach(point => {
        minX = Math.min(minX, point.x);
        minY = Math.min(minY, point.y);
        maxX = Math.max(maxX, point.x);
        maxY = Math.max(maxY, point.y);
    });

    return {
        minX,
        minY,
        maxX,
        maxY,
        width: maxX - minX,
        height: maxY - minY
    };
}

/**
 * נרמול פוליגון (העברה לראשית)
 * @param {Array} polygon - [{x, y}, ...]
 * @returns {Array} - פוליגון מנורמל
 */
export function normalizePolygon(polygon) {
    const bbox = getBoundingBox(polygon);

    return polygon.map(point => ({
        x: point.x - bbox.minX,
        y: point.y - bbox.minY
    }));
}

/**
 * סיבוב נקודה סביב מרכז
 * @param {Object} point - {x, y}
 * @param {Object} center - {x, y}
 * @param {number} angle - זווית ברדיאנים
 * @returns {Object} - {x, y}
 */
export function rotatePoint(point, center, angle) {
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);

    const dx = point.x - center.x;
    const dy = point.y - center.y;

    return {
        x: center.x + dx * cos - dy * sin,
        y: center.y + dx * sin + dy * cos
    };
}

/**
 * סיבוב פוליגון סביב מרכז
 * @param {Array} polygon - [{x, y}, ...]
 * @param {number} angle - זווית ברדיאנים
 * @param {Object} center - {x, y} (אופציונלי, ברירת מחדל: מרכז הפוליגון)
 * @returns {Array} - פוליגון מסובב
 */
export function rotatePolygon(polygon, angle, center = null) {
    const rotationCenter = center || getPolygonCenter(polygon);

    return polygon.map(point => rotatePoint(point, rotationCenter, angle));
}

/**
 * סקייל פוליגון
 * @param {Array} polygon - [{x, y}, ...]
 * @param {number} scaleX - סקייל X
 * @param {number} scaleY - סקייל Y (אופציונלי, ברירת מחדל: scaleX)
 * @param {Object} center - {x, y} (אופציונלי, ברירת מחדל: מרכז הפוליגון)
 * @returns {Array} - פוליגון מוגדל
 */
export function scalePolygon(polygon, scaleX, scaleY = null, center = null) {
    const sy = scaleY || scaleX;
    const scaleCenter = center || getPolygonCenter(polygon);

    return polygon.map(point => ({
        x: scaleCenter.x + (point.x - scaleCenter.x) * scaleX,
        y: scaleCenter.y + (point.y - scaleCenter.y) * sy
    }));
}

/**
 * הזזת פוליגון
 * @param {Array} polygon - [{x, y}, ...]
 * @param {number} dx - הזזה ב-X
 * @param {number} dy - הזזה ב-Y
 * @returns {Array} - פוליגון מוזז
 */
export function translatePolygon(polygon, dx, dy) {
    return polygon.map(point => ({
        x: point.x + dx,
        y: point.y + dy
    }));
}

/**
 * פישוט פוליגון (הסרת נקודות מיותרות) - Douglas-Peucker Algorithm
 * @param {Array} points - [{x, y}, ...]
 * @param {number} tolerance - סף הסובלנות
 * @returns {Array} - נקודות מפושטות
 */
export function simplifyPolygon(points, tolerance = 1.0) {
    if (points.length < 3) return points;

    const sqTolerance = tolerance * tolerance;

    function getSqDist(p1, p2) {
        const dx = p1.x - p2.x;
        const dy = p1.y - p2.y;
        return dx * dx + dy * dy;
    }

    function simplifyDouglasPeucker(points, sqTolerance) {
        const len = points.length;
        const markers = new Array(len);
        let first = 0;
        let last = len - 1;
        let index;
        const stack = [];
        const newPoints = [];

        markers[first] = markers[last] = 1;

        while (last) {
            let maxSqDist = 0;

            for (let i = first + 1; i < last; i++) {
                const sqDist = getSqSegDist(points[i], points[first], points[last]);

                if (sqDist > maxSqDist) {
                    index = i;
                    maxSqDist = sqDist;
                }
            }

            if (maxSqDist > sqTolerance) {
                markers[index] = 1;
                stack.push(first, index, index, last);
            }

            last = stack.pop();
            first = stack.pop();
        }

        for (let i = 0; i < len; i++) {
            if (markers[i]) {
                newPoints.push(points[i]);
            }
        }

        return newPoints;
    }

    function getSqSegDist(p, p1, p2) {
        let x = p1.x;
        let y = p1.y;
        let dx = p2.x - x;
        let dy = p2.y - y;

        if (dx !== 0 || dy !== 0) {
            const t = ((p.x - x) * dx + (p.y - y) * dy) / (dx * dx + dy * dy);

            if (t > 1) {
                x = p2.x;
                y = p2.y;
            } else if (t > 0) {
                x += dx * t;
                y += dy * t;
            }
        }

        dx = p.x - x;
        dy = p.y - y;

        return dx * dx + dy * dy;
    }

    return simplifyDouglasPeucker(points, sqTolerance);
}

/**
 * בדיקת חיתוך בין שני קווים
 * @param {Object} line1Start - {x, y}
 * @param {Object} line1End - {x, y}
 * @param {Object} line2Start - {x, y}
 * @param {Object} line2End - {x, y}
 * @returns {Object|null} - נקודת החיתוך או null
 */
export function getLineIntersection(line1Start, line1End, line2Start, line2End) {
    const x1 = line1Start.x;
    const y1 = line1Start.y;
    const x2 = line1End.x;
    const y2 = line1End.y;
    const x3 = line2Start.x;
    const y3 = line2Start.y;
    const x4 = line2End.x;
    const y4 = line2End.y;

    const denom = (y4 - y3) * (x2 - x1) - (x4 - x3) * (y2 - y1);

    if (denom === 0) return null; // קווים מקבילים

    const ua = ((x4 - x3) * (y1 - y3) - (y4 - y3) * (x1 - x3)) / denom;
    const ub = ((x2 - x1) * (y1 - y3) - (y2 - y1) * (x1 - x3)) / denom;

    if (ua >= 0 && ua <= 1 && ub >= 0 && ub <= 1) {
        return {
            x: x1 + ua * (x2 - x1),
            y: y1 + ua * (y2 - y1)
        };
    }

    return null;
}

/**
 * בדיקה אם שני פוליגונים מתנגשים
 * @param {Array} polygon1 - [{x, y}, ...]
 * @param {Array} polygon2 - [{x, y}, ...]
 * @returns {boolean}
 */
export function doPolygonsIntersect(polygon1, polygon2) {
    // בדיקה אם יש נקודה של פוליגון 1 בתוך פוליגון 2
    for (const point of polygon1) {
        if (isPointInPolygon(point, polygon2)) return true;
    }

    // בדיקה אם יש נקודה של פוליגון 2 בתוך פוליגון 1
    for (const point of polygon2) {
        if (isPointInPolygon(point, polygon1)) return true;
    }

    // בדיקת חיתוך קווים
    for (let i = 0; i < polygon1.length; i++) {
        const p1Start = polygon1[i];
        const p1End = polygon1[(i + 1) % polygon1.length];

        for (let j = 0; j < polygon2.length; j++) {
            const p2Start = polygon2[j];
            const p2End = polygon2[(j + 1) % polygon2.length];

            if (getLineIntersection(p1Start, p1End, p2Start, p2End)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * עיגול נקודות למספרים שלמים (למניעת בעיות rendering)
 * @param {Array} points - [{x, y}, ...]
 * @returns {Array} - נקודות מעוגלות
 */
export function roundPoints(points) {
    return points.map(point => ({
        x: Math.round(point.x),
        y: Math.round(point.y)
    }));
}
