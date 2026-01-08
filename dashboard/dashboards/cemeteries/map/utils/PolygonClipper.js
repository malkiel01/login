/**
 * PolygonClipper - חיתוך פוליגון ילד לפי גבול הורה
 * משתמש באלגוריתם Sutherland-Hodgman
 *
 * כשהגבול הילד יוצא מגבול ההורה:
 * - נוצרות נקודות עיגון חדשות על קו גבול ההורה
 * - החלקים שמחוץ לגבול ההורה נחתכים
 */

export class PolygonClipper {
    /**
     * חותך פוליגון לפי גבול הורה
     * @param {Array} childPoints - נקודות הפוליגון הילד [{x, y}, ...]
     * @param {Array} parentPoints - נקודות גבול ההורה [{x, y}, ...]
     * @returns {Array} - נקודות הפוליגון החתוך (תמיד בתוך גבול ההורה)
     */
    static clip(childPoints, parentPoints) {
        if (!childPoints || childPoints.length < 3) return childPoints;
        if (!parentPoints || parentPoints.length < 3) return childPoints;

        // וודא שגבול ההורה בכיוון CCW (נגד כיוון השעון)
        const clipPoly = this.ensureCCW([...parentPoints]);

        let outputList = childPoints.map(p => ({ x: p.x, y: p.y }));

        // עבור על כל צלע בגבול ההורה
        for (let i = 0; i < clipPoly.length; i++) {
            if (outputList.length === 0) return [];

            const edgeStart = clipPoly[i];
            const edgeEnd = clipPoly[(i + 1) % clipPoly.length];

            const inputList = outputList;
            outputList = [];

            for (let j = 0; j < inputList.length; j++) {
                const current = inputList[j];
                const next = inputList[(j + 1) % inputList.length];

                const currInside = this.isLeft(current, edgeStart, edgeEnd);
                const nextInside = this.isLeft(next, edgeStart, edgeEnd);

                if (currInside) {
                    // הנקודה הנוכחית בפנים
                    outputList.push({ x: current.x, y: current.y });

                    if (!nextInside) {
                        // יוצאים מהגבול - הוסף נקודת חיתוך
                        const intersect = this.lineIntersect(current, next, edgeStart, edgeEnd);
                        if (intersect) {
                            outputList.push(intersect);
                        }
                    }
                } else if (nextInside) {
                    // נכנסים לגבול - הוסף נקודת חיתוך
                    const intersect = this.lineIntersect(current, next, edgeStart, edgeEnd);
                    if (intersect) {
                        outputList.push(intersect);
                    }
                }
                // אם שניהם בחוץ - לא מוסיפים כלום
            }
        }

        // עגל לפיקסלים שלמים והסר נקודות כפולות
        return this.cleanupPoints(outputList);
    }

    /**
     * בודק אם נקודה משמאל לקו (בתוך פוליגון CCW)
     */
    static isLeft(point, lineStart, lineEnd) {
        const val = (lineEnd.x - lineStart.x) * (point.y - lineStart.y) -
                    (lineEnd.y - lineStart.y) * (point.x - lineStart.x);
        return val >= 0;
    }

    /**
     * מוצא נקודת חיתוך בין שני קטעי קו
     */
    static lineIntersect(p1, p2, p3, p4) {
        const x1 = p1.x, y1 = p1.y;
        const x2 = p2.x, y2 = p2.y;
        const x3 = p3.x, y3 = p3.y;
        const x4 = p4.x, y4 = p4.y;

        const denom = (x1 - x2) * (y3 - y4) - (y1 - y2) * (x3 - x4);
        if (Math.abs(denom) < 0.0001) return null;

        const t = ((x1 - x3) * (y3 - y4) - (y1 - y3) * (x3 - x4)) / denom;

        return {
            x: x1 + t * (x2 - x1),
            y: y1 + t * (y2 - y1)
        };
    }

    /**
     * מחשב שטח מכוון (חיובי = CCW, שלילי = CW)
     */
    static signedArea(points) {
        let area = 0;
        for (let i = 0; i < points.length; i++) {
            const j = (i + 1) % points.length;
            area += points[i].x * points[j].y;
            area -= points[j].x * points[i].y;
        }
        return area / 2;
    }

    /**
     * מוודא שהפוליגון בכיוון CCW
     */
    static ensureCCW(points) {
        if (this.signedArea(points) < 0) {
            return points.reverse();
        }
        return points;
    }

    /**
     * מנקה נקודות - מעגל ומסיר כפולות
     */
    static cleanupPoints(points) {
        if (points.length < 3) return points;

        const result = [];
        for (let i = 0; i < points.length; i++) {
            const p = {
                x: Math.round(points[i].x),
                y: Math.round(points[i].y)
            };

            // בדוק שלא כפול לנקודה הקודמת
            if (result.length === 0) {
                result.push(p);
            } else {
                const last = result[result.length - 1];
                if (Math.abs(p.x - last.x) > 1 || Math.abs(p.y - last.y) > 1) {
                    result.push(p);
                }
            }
        }

        // בדוק שהנקודה האחרונה לא כפולה לראשונה
        if (result.length > 1) {
            const first = result[0];
            const last = result[result.length - 1];
            if (Math.abs(first.x - last.x) <= 1 && Math.abs(first.y - last.y) <= 1) {
                result.pop();
            }
        }

        return result.length >= 3 ? result : points;
    }

    /**
     * בודק אם נקודה בתוך פוליגון
     */
    static isPointInPolygon(point, polygon) {
        if (!polygon || polygon.length < 3) return false;

        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i].x, yi = polygon[i].y;
            const xj = polygon[j].x, yj = polygon[j].y;

            if (((yi > point.y) !== (yj > point.y)) &&
                (point.x < (xj - xi) * (point.y - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }
        return inside;
    }

    /**
     * בודק אם כל הפוליגון בתוך גבול ההורה
     */
    static isFullyInside(child, parent) {
        return child.every(p => this.isPointInPolygon(p, parent));
    }
}

// Export for non-module usage
window.PolygonClipperClass = PolygonClipper;
