/**
 * PolygonClipper - חיתוך פוליגון לפי גבול הורה
 * משתמש באלגוריתם Sutherland-Hodgman
 */

export class PolygonClipper {
    /**
     * חותך פוליגון לפי גבול (clip polygon)
     * הפוליגון הילד יחתך כך שישאר בתוך גבול ההורה
     * @param {Array} subjectPoints - נקודות הפוליגון לחיתוך [{x, y}, ...]
     * @param {Array} clipPoints - נקודות גבול ההורה [{x, y}, ...]
     * @returns {Array} - נקודות הפוליגון החתוך
     */
    static clip(subjectPoints, clipPoints) {
        if (!subjectPoints || subjectPoints.length < 3) return subjectPoints;
        if (!clipPoints || clipPoints.length < 3) return subjectPoints;

        // וודא שהפוליגון החותך בכיוון הנכון (counter-clockwise)
        const clipPolygon = this.ensureCounterClockwise(clipPoints);

        let output = [...subjectPoints];

        // עבור על כל קצה בגבול החותך
        for (let i = 0; i < clipPolygon.length; i++) {
            if (output.length === 0) break;

            const edgeStart = clipPolygon[i];
            const edgeEnd = clipPolygon[(i + 1) % clipPolygon.length];

            const input = output;
            output = [];

            // עבור על כל קצה בפוליגון הנוכחי
            for (let j = 0; j < input.length; j++) {
                const current = input[j];
                const next = input[(j + 1) % input.length];

                const currentInside = this.isInside(current, edgeStart, edgeEnd);
                const nextInside = this.isInside(next, edgeStart, edgeEnd);

                if (currentInside) {
                    output.push({ x: current.x, y: current.y });
                    if (!nextInside) {
                        // יוצא מהגבול - הוסף נקודת חיתוך
                        const intersection = this.getIntersection(
                            current, next, edgeStart, edgeEnd
                        );
                        if (intersection) {
                            output.push(intersection);
                        }
                    }
                } else if (nextInside) {
                    // נכנס לגבול - הוסף נקודת חיתוך
                    const intersection = this.getIntersection(
                        current, next, edgeStart, edgeEnd
                    );
                    if (intersection) {
                        output.push(intersection);
                    }
                }
            }
        }

        // עיגול לפיקסלים שלמים
        return output.map(p => ({
            x: Math.round(p.x),
            y: Math.round(p.y)
        }));
    }

    /**
     * בודק אם נקודה בצד הפנימי של קצה (שמאל עבור CCW)
     */
    static isInside(point, edgeStart, edgeEnd) {
        return (edgeEnd.x - edgeStart.x) * (point.y - edgeStart.y) -
               (edgeEnd.y - edgeStart.y) * (point.x - edgeStart.x) >= 0;
    }

    /**
     * מוצא נקודת חיתוך בין שני קטעי קו
     */
    static getIntersection(p1, p2, p3, p4) {
        const x1 = p1.x, y1 = p1.y;
        const x2 = p2.x, y2 = p2.y;
        const x3 = p3.x, y3 = p3.y;
        const x4 = p4.x, y4 = p4.y;

        const denom = (x1 - x2) * (y3 - y4) - (y1 - y2) * (x3 - x4);
        if (Math.abs(denom) < 1e-10) return null;

        const t = ((x1 - x3) * (y3 - y4) - (y1 - y3) * (x3 - x4)) / denom;

        return {
            x: x1 + t * (x2 - x1),
            y: y1 + t * (y2 - y1)
        };
    }

    /**
     * מחשב את השטח המכוון של פוליגון (חיובי = CCW, שלילי = CW)
     */
    static getSignedArea(points) {
        let area = 0;
        for (let i = 0; i < points.length; i++) {
            const j = (i + 1) % points.length;
            area += points[i].x * points[j].y;
            area -= points[j].x * points[i].y;
        }
        return area / 2;
    }

    /**
     * מוודא שהפוליגון בכיוון counter-clockwise
     */
    static ensureCounterClockwise(points) {
        const area = this.getSignedArea(points);
        if (area < 0) {
            // הפוליגון בכיוון CW, הפוך אותו
            return [...points].reverse();
        }
        return points;
    }

    /**
     * בודק אם נקודה בתוך פוליגון (Ray Casting)
     */
    static isPointInPolygon(point, polygon) {
        if (!polygon || polygon.length < 3) return false;

        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i].x, yi = polygon[i].y;
            const xj = polygon[j].x, yj = polygon[j].y;

            const intersect = ((yi > point.y) !== (yj > point.y)) &&
                (point.x < (xj - xi) * (point.y - yi) / (yj - yi) + xi);

            if (intersect) inside = !inside;
        }

        return inside;
    }

    /**
     * בודק אם כל נקודות הפוליגון בתוך הגבול
     */
    static isPolygonFullyInside(inner, outer) {
        if (!inner || !outer) return true;
        return inner.every(point => this.isPointInPolygon(point, outer));
    }
}

// Export for non-module usage
window.PolygonClipperClass = PolygonClipper;
