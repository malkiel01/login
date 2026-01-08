/**
 * PolygonClipper - חיתוך פוליגון בן בתוך גבול הורה
 * Version: 1.0.0
 *
 * משתמש באלגוריתם Sutherland-Hodgman לחיתוך פוליגון
 * כאשר גבול הבן חורג מגבול ההורה, הוא נחתך אוטומטית
 */

export class PolygonClipper {
    /**
     * חותך פוליגון subject לפי פוליגון clip
     * @param {Array} subjectPolygon - הפוליגון שיחתך [{x, y}, ...]
     * @param {Array} clipPolygon - הפוליגון שמגדיר את אזור החיתוך [{x, y}, ...]
     * @returns {Array} - הפוליגון החתוך
     */
    static clip(subjectPolygon, clipPolygon) {
        if (!subjectPolygon || subjectPolygon.length < 3) {
            console.warn('PolygonClipper: Invalid subject polygon');
            return subjectPolygon;
        }

        if (!clipPolygon || clipPolygon.length < 3) {
            console.warn('PolygonClipper: Invalid clip polygon');
            return subjectPolygon;
        }

        // וודא שהפוליגונים במבנה נכון
        let subject = subjectPolygon.map(p => ({ x: p.x, y: p.y }));
        let clip = clipPolygon.map(p => ({ x: p.x, y: p.y }));

        // קבע את כיוון הסיבוב של פוליגון החיתוך
        const clipArea = this.signedArea(clip);
        const isClipCCW = clipArea > 0;

        // אלגוריתם Sutherland-Hodgman
        let outputList = subject;

        for (let i = 0; i < clip.length; i++) {
            if (outputList.length === 0) break;

            const inputList = outputList;
            outputList = [];

            const edgeStart = clip[i];
            const edgeEnd = clip[(i + 1) % clip.length];

            for (let j = 0; j < inputList.length; j++) {
                const current = inputList[j];
                const previous = inputList[(j - 1 + inputList.length) % inputList.length];

                const currentInside = this.isInside(current, edgeStart, edgeEnd, isClipCCW);
                const previousInside = this.isInside(previous, edgeStart, edgeEnd, isClipCCW);

                if (currentInside) {
                    if (!previousInside) {
                        // נכנסים לאזור - הוסף נקודת חיתוך
                        const intersection = this.lineIntersection(previous, current, edgeStart, edgeEnd);
                        if (intersection) {
                            outputList.push(intersection);
                        }
                    }
                    // הנקודה הנוכחית בפנים - הוסף אותה
                    outputList.push(current);
                } else if (previousInside) {
                    // יוצאים מהאזור - הוסף נקודת חיתוך
                    const intersection = this.lineIntersection(previous, current, edgeStart, edgeEnd);
                    if (intersection) {
                        outputList.push(intersection);
                    }
                }
            }
        }

        // הסר נקודות כפולות
        if (outputList.length > 0) {
            outputList = this.removeDuplicatePoints(outputList);
        }

        return outputList;
    }

    /**
     * בדיקה אם נקודה נמצאת בצד הפנימי של קצה
     */
    static isInside(point, edgeStart, edgeEnd, isClipCCW) {
        const cross = this.crossProduct(edgeStart, edgeEnd, point);
        // עבור CCW: פנימי = שמאל (cross > 0)
        // עבור CW: פנימי = ימין (cross < 0)
        if (isClipCCW) {
            return cross >= 0; // כולל על הקו
        } else {
            return cross <= 0; // כולל על הקו
        }
    }

    /**
     * מכפלה וקטורית - קובעת את המיקום היחסי של נקודה לקו
     * חיובי = שמאל, שלילי = ימין, אפס = על הקו
     */
    static crossProduct(a, b, c) {
        return (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
    }

    /**
     * חישוב שטח חתום של פוליגון
     * חיובי = CCW, שלילי = CW
     */
    static signedArea(polygon) {
        let area = 0;
        for (let i = 0; i < polygon.length; i++) {
            const j = (i + 1) % polygon.length;
            area += polygon[i].x * polygon[j].y;
            area -= polygon[j].x * polygon[i].y;
        }
        return area / 2;
    }

    /**
     * מציאת נקודת חיתוך בין שני קווים
     */
    static lineIntersection(p1, p2, p3, p4) {
        const x1 = p1.x, y1 = p1.y;
        const x2 = p2.x, y2 = p2.y;
        const x3 = p3.x, y3 = p3.y;
        const x4 = p4.x, y4 = p4.y;

        const denom = (x1 - x2) * (y3 - y4) - (y1 - y2) * (x3 - x4);

        if (Math.abs(denom) < 0.0001) {
            // קווים מקבילים או חופפים
            return null;
        }

        const t = ((x1 - x3) * (y3 - y4) - (y1 - y3) * (x3 - x4)) / denom;

        return {
            x: x1 + t * (x2 - x1),
            y: y1 + t * (y2 - y1)
        };
    }

    /**
     * הסרת נקודות כפולות רצופות
     */
    static removeDuplicatePoints(points, tolerance = 0.5) {
        if (points.length <= 1) return points;

        const result = [points[0]];

        for (let i = 1; i < points.length; i++) {
            const prev = result[result.length - 1];
            const curr = points[i];
            const dist = Math.sqrt(
                Math.pow(curr.x - prev.x, 2) +
                Math.pow(curr.y - prev.y, 2)
            );
            if (dist > tolerance) {
                result.push(curr);
            }
        }

        // בדוק גם את הנקודה הראשונה והאחרונה
        if (result.length > 1) {
            const first = result[0];
            const last = result[result.length - 1];
            const dist = Math.sqrt(
                Math.pow(first.x - last.x, 2) +
                Math.pow(first.y - last.y, 2)
            );
            if (dist < tolerance) {
                result.pop();
            }
        }

        return result;
    }

    /**
     * בדיקה אם כל הנקודות של פוליגון נמצאות בתוך פוליגון אחר
     */
    static isFullyInside(innerPolygon, outerPolygon) {
        for (const point of innerPolygon) {
            if (!this.isPointInPolygon(point, outerPolygon)) {
                return false;
            }
        }
        return true;
    }

    /**
     * בדיקה אם נקודה נמצאת בתוך פוליגון (Ray Casting)
     */
    static isPointInPolygon(point, polygon) {
        let inside = false;
        const x = point.x, y = point.y;

        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const xi = polygon[i].x, yi = polygon[i].y;
            const xj = polygon[j].x, yj = polygon[j].y;

            if (((yi > y) !== (yj > y)) &&
                (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }

        return inside;
    }

    /**
     * בדיקה אם יש חיתוך בין שני פוליגונים
     */
    static hasIntersection(polygon1, polygon2) {
        // בדוק אם קצוות חותכים
        for (let i = 0; i < polygon1.length; i++) {
            const a1 = polygon1[i];
            const a2 = polygon1[(i + 1) % polygon1.length];

            for (let j = 0; j < polygon2.length; j++) {
                const b1 = polygon2[j];
                const b2 = polygon2[(j + 1) % polygon2.length];

                if (this.segmentsIntersect(a1, a2, b1, b2)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * בדיקה אם שני קטעי קו חותכים
     */
    static segmentsIntersect(a1, a2, b1, b2) {
        const d1 = this.crossProduct(b1, b2, a1);
        const d2 = this.crossProduct(b1, b2, a2);
        const d3 = this.crossProduct(a1, a2, b1);
        const d4 = this.crossProduct(a1, a2, b2);

        if (((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) &&
            ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0))) {
            return true;
        }

        return false;
    }

    /**
     * בדיקה אם צריך לחתוך את הפוליגון
     * @returns {boolean} true אם יש נקודות מחוץ לגבול ההורה
     */
    static needsClipping(childPolygon, parentPolygon) {
        // בדוק אם יש נקודות מחוץ להורה
        for (const point of childPolygon) {
            if (!this.isPointInPolygon(point, parentPolygon)) {
                return true;
            }
        }

        // בדוק גם אם יש חיתוכי קצוות
        return this.hasIntersection(childPolygon, parentPolygon);
    }
}
