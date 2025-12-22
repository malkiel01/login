# מערכת עיבוד PDF - הוספת טקסט עברי

מערכת לעיבוד קבצי PDF והוספת הטקסט "ניסיון" במרכז כל דף.

## תכונות

- ✅ העלאת קבצי PDF בגרירה או בלחיצה
- ✅ הצגת מידות הקובץ ומספר דפים
- ✅ הוספת טקסט "ניסיון" בעברית במרכז כל דף
- ✅ הורדת הקובץ המעובד
- ✅ תמיכה מלאה בעברית ו-RTL
- ✅ ניקוי אוטומטי של קבצים ישנים

## דרישות מערכת

### שרת
- PHP 7.4 ומעלה
- Python 3.8 ומעלה
- גישה לפקודת `exec()` ב-PHP

### חבילות Python
```bash
pip install pypdf reportlab
```

## התקנה

1. העתק את כל הקבצים לתיקיית השרת
2. ודא שהתיקיות הבאות קיימות וניתנות לכתיבה:
   - `uploads/`
   - `outputs/`

```bash
mkdir -p uploads outputs
chmod 755 uploads outputs
```

3. ודא שהסקריפט Python ניתן להרצה:
```bash
chmod +x add_text_to_pdf.py
```

## קבצי המערכת

### קבצי Frontend
- **index.html** - דף הנחיתה עם ממשק העלאה
  - עיצוב מודרני עם גרדיאנט
  - תמיכה ב-drag & drop
  - אנימציות ומשוב ויזואלי

### קבצי Backend
- **process.php** - מעבד העלאות וקורא לסקריפט Python
  - קבלת קבצי PDF
  - ולידציה של סוג הקובץ
  - קריאה לסקריפט Python
  - החזרת מטא-דאטה
  - ניקוי קבצים ישנים

- **add_text_to_pdf.py** - סקריפט Python לעיבוד PDF
  - קריאת הקובץ המקורי
  - יצירת overlay עם טקסט עברי
  - מיזוג ה-overlay עם הקובץ המקורי
  - החזרת מידע על מספר דפים ומידות

- **download.php** - מנהל הורדות
  - ולידציה של שם הקובץ
  - הגנת אבטחה
  - הורדת הקובץ המעובד

## שימוש

1. פתח את `index.html` בדפדפן
2. העלה קובץ PDF בגרירה או בלחיצה
3. לחץ על "עבד את הקובץ"
4. המתן לסיום העיבוד
5. צפה במידע על הקובץ
6. הורד את הקובץ המעובד

## אבטחה

המערכת כוללת מספר שכבות אבטחה:

1. **ולידציה של סוג קובץ** - בדיקת MIME type
2. **ולידציה של שם קובץ** - regex validation
3. **שמות קבצים ייחודיים** - שימוש ב-uniqid
4. **ניקוי אוטומטי** - מחיקת קבצים ישנים מעל שעה
5. **escapeshellarg** - מניעת command injection

## טיפול בשגיאות

המערכת מטפלת במצבים הבאים:
- ❌ קובץ שאינו PDF
- ❌ שגיאות העלאה
- ❌ שגיאות עיבוד
- ❌ קבצים חסרים
- ❌ שמות קבצים לא תקינים

## התאמה אישית

### שינוי הטקסט המוסף
ערוך את `add_text_to_pdf.py`:
```python
text="ניסיון"  # שנה כאן
```

### שינוי גודל וצבע הפונט
ערוך את `add_text_to_pdf.py`:
```python
font_size = 48  # שנה גודל
can.setFillColorRGB(0.5, 0.5, 0.5, alpha=0.5)  # שנה צבע
```

### שינוי זמן ניקוי קבצים
ערוך את `process.php`:
```php
cleanOldFiles($upload_dir, 3600);  // 3600 שניות = 1 שעה
```

## פתרון בעיות

### Python לא מותקן
```bash
sudo apt-get install python3
```

### חבילות Python חסרות
```bash
pip install --break-system-packages pypdf reportlab
```

### שגיאת הרשאות
```bash
chmod 755 uploads outputs
chmod +x add_text_to_pdf.py
```

### שגיאת exec()
ודא ש-`exec()` מופעל ב-php.ini:
```ini
disable_functions = 
```

## טכנולוגיות

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+
- **Processing**: Python 3.8+
- **Libraries**: pypdf, reportlab

## רישיון

קוד זה נוצר על ידי Claude (Anthropic) למטרות חינוכיות ופיתוח.
