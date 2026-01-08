/**
 * PolygonClipper - חיתוך פוליגון לפי גבול הורה
 * משתמש באלגוריתם Sutherland-Hodgman
 */

export class PolygonClipper {
    /**
     * חותך פוליגון לפי גבול (clip polygon)
     * @param {Array} subjectPoints - נקודות הפוליגון לחיתוך [{x, y}, ...]
     * @param {Array} clipPoints - נקודות הגבול החותך [{x, y}, ...]
     * @returns {Array} - נקודות הפוליגון החתוך
     */
    static clip(subjectPoints, clipPoints) {
        if (!subjectPoints || subjectPoints.length < 3) return subjectPoints;
        if (!clipPoints || clipPoints.length < 3) return subjectPoints;

        let output = [...subjectPoints];

        // עבור על כל קצה בגבול החותך
        for (let i = 0; i < clipPoints.length; i++) {
            if (output.length === 0) break;

            const clipEdgeStart = clipPoints[i];
            const clipEdgeEnd = clipPoints[(i + 1) % clipPoints.length];

            const input = output;
            output = [];

            // עבור על כל קצה בפוליגון הנוכחי
            for (let j = 0; j < input.length; j++) {
                const current = input[j];
                const next = input[(j + 1) % input.length];

                const currentInside = this.isPointOnLeft(current, clipEdgeStart, clipEdgeEnd);
                const nextInside = this.isPointOnLeft(next, clipEdgeStart, clipEdgeEnd);

                if (currentInside) {
                    output.push(current);
                    if (!nextInside) {
                        // יוצא מהגבול - הוסף נקודת חיתוך
                        const intersection = this.lineIntersection(
                            current, next, clipEdgeStart, clipEdgeEnd
                        );
                        if (intersection) output.push(intersection);
                    }
                } else if (nextInside) {
                    // נכנס לגבול - הוסף נקודת חיתוך
                    const intersection = this.lineIntersection(
                        current, next, clipEdgeStart, clipEdgeEnd
                    );
                    if (intersection) output.push(intersection);
                }
            }
        }

        return output;
    }

    /**
     * בודק אם נקודה נמצאת בצד שמאל של קו (בתוך הפוליגון בכיוון שעון)
     */
    static isPointOnLeft(point, lineStart, lineEnd) {
        return ((lineEnd.x - lineStart.x) * (point.y - lineStart.y) -
                (lineEnd.y - lineStart.y) * (point.x - lineStart.x)) >= 0;
    }

    /**
     * מוצא נקודת חיתוך בין שני קווים
     */
    static lineIntersection(p1, p2, p3, p4) {
        const d1x = p2.x - p1.x;
        const d1y = p2.y - p1.y;
        const d2x = p4.x - p3.x;
        const d2y = p4.y - p3.y;

        const cross = d1x * d2y - d1y * d2x;
        if (Math.abs(cross) < 1e-10) return null; // קווים מקבילים

        const dx = p3.x - p1.x;
        const dy = p3.y - p1.y;

        const t = (dx * d2y - dy * d2x) / cross;

        return {
            x: p1.x + t * d1x,
            y: p1.y + t * d1y
        };
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
    static isPolygonInsidePolygon(inner, outer) {
        if (!inner || !outer) return true;
        return inner.every(point => this.isPointInPolygon(point, outer));
    }

    /**
     * בודק אם יש חפיפה בין שני פוליגונים
     */
    static polygonsOverlap(poly1, poly2) {
        // בדוק אם לפחות נקודה אחת מכל פוליגון בתוך השני
        const p1InP2 = poly1.some(p => this.isPointInPolygon(p, poly2));
        const p2InP1 = poly2.some(p => this.isPointInPolygon(p, poly1));
        return p1InP2 || p2InP1;
    }

    /**
     * מעגל קואורדינטות לפיקסלים שלמים
     */
    static roundPoints(points) {
        return points.map(p => ({
            x: Math.round(p.x),
            y: Math.round(p.y)
        }));
    }
}

// Export for non-module usage
window.PolygonClipperClass = PolygonClipper;
