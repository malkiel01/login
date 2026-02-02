# תיקון באג - אופציות סלקט נחתכות בתוך סקציה

## תיאור הבעיה
בפופאפ המודולרי, כאשר יש custom select לבחירת לקוח בתוך סקציה, האופציות של הסלקט נחתכות ולא נראות.
הסיבה: `overflow: hidden` על אלמנטים הורים חותך את התוכן שגולש מחוץ לגבולות הסקציה.

---

## קבצים לתיקון

### 1. popup-forms.css - שורה 1157
**נתיב:** `dashboard/dashboards/cemeteries/popup/popup-forms.css`

**מצב נוכחי:**
```css
.custom-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    /* ... */
    z-index: 9999;
    max-height: 300px;
    overflow: hidden;  /* <-- הבעיה */
}
```

**לשנות ל:**
```css
.custom-select-dropdown {
    position: fixed;  /* שינוי מ-absolute ל-fixed */
    /* ... */
    z-index: 99999;  /* להעלות את ה-z-index */
    max-height: 300px;
    overflow: visible;  /* שינוי מ-hidden ל-visible */
}
```

---

### 2. popup-forms.css - שורה 103
**נתיב:** `dashboard/dashboards/cemeteries/popup/popup-forms.css`

**מצב נוכחי:**
```css
.sortable-section {
    overflow: hidden;
}
```

**לשנות ל:**
```css
.sortable-section {
    overflow: visible;
}
```

---

### 3. popup-sections.css - שורה 40
**נתיב:** `dashboard/dashboards/cemeteries/popup/popup-sections.css`

**מצב נוכחי:**
```css
.sortable-section {
    overflow: hidden;
}
```

**לשנות ל:**
```css
.sortable-section {
    overflow: visible;
}
```

---

## עדכון JavaScript (אם בוחרים position: fixed)

אם משנים את `.custom-select-dropdown` ל-`position: fixed`, צריך לעדכן את הפונקציה שפותחת את הדרופדאון כדי לחשב מיקום אבסולוטי ביחס לחלון.

**לחפש את הפונקציה:** פונקציה שמטפלת בפתיחת הדרופדאון (כנראה בקובץ JS של הפופאפ או הטפסים)

**להוסיף לוגיקה:**
```javascript
function openCustomSelectDropdown(selectElement) {
    const dropdown = selectElement.querySelector('.custom-select-dropdown');
    const trigger = selectElement.querySelector('.custom-select-trigger');
    const rect = trigger.getBoundingClientRect();

    dropdown.style.position = 'fixed';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.width = rect.width + 'px';
    dropdown.style.display = 'flex';
}
```

---

## פתרון אלטרנטיבי (פשוט יותר)

אם לא רוצים לשנות ל-`position: fixed`, אפשר רק לשנות את ה-overflow על הסקציות:

1. בקובץ `popup-forms.css` שורה 103 - שנה `overflow: hidden` ל-`overflow: visible`
2. בקובץ `popup-sections.css` שורה 40 - שנה `overflow: hidden` ל-`overflow: visible`

**שים לב:** פתרון זה עלול להשפיע על אלמנטים אחרים בסקציות. יש לבדוק שאין תופעות לוואי.

---

## בדיקות לאחר התיקון

- [ ] פתיחת פופאפ רכישה חדשה - וידוא שהאופציות נראות
- [ ] פתיחת פופאפ קבורה חדשה - וידוא שהאופציות נראות
- [ ] בדיקה שהאופציות לא גולשות מחוץ לחלון
- [ ] בדיקה שסגירת הדרופדאון עובדת כשלוחצים מחוץ לאזור
- [ ] בדיקה במצב dark mode
- [ ] בדיקה בגלילה של הפופאפ - האם הדרופדאון נשאר במקום הנכון

---

## סיכום השינויים

| קובץ | שורה | שינוי |
|------|------|-------|
| popup-forms.css | 1157 | `overflow: hidden` → `overflow: visible` |
| popup-forms.css | 103 | `overflow: hidden` → `overflow: visible` |
| popup-sections.css | 40 | `overflow: hidden` → `overflow: visible` |

**אופציונלי (לפתרון מלא):**
| קובץ | שורה | שינוי |
|------|------|-------|
| popup-forms.css | 1145 | `position: absolute` → `position: fixed` |
| popup-forms.css | 1154 | `z-index: 9999` → `z-index: 99999` |
| קובץ JS רלוונטי | - | הוספת חישוב מיקום דינמי |
