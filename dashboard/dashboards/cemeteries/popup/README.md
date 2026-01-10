# 🎯 Popup Manager - מודול פופ-אפ גנרי

מנהל פופ-אפ חזק וגמיש לחלוטין עם תמיכה ב-iframe, HTML ישיר ו-AJAX, כולל תקשורת דו-כיוונית בין הפופ-אפ לתוכן.

## ✨ תכונות עיקריות

- 🪟 **Container טהור** - לא קשור לתוכן, עובד עם כל דבר
- 🔄 **Hybrid Content** - תמיכה ב-iframe, HTML ישיר ו-AJAX
- 📡 **תקשורת דו-כיוונית** - postMessage + Custom Events
- 🎨 **Draggable & Resizable** - גרירה ושינוי גודל מלא
- 🔽 **Minimize** - מזעור לפס תחתון
- ⛶ **Maximize** - מסך מלא בתוך הדף
- ↗ **Detach** - העברה לחלון דפדפן נפרד
- 🎯 **Z-Index Smart** - ניהול אוטומטי של סדר חלונות
- 📱 **Responsive** - התאמה אוטומטית למסכים קטנים
- 🌙 **Dark Mode** - תמיכה ב-dark mode

## 📁 מבנה קבצים

```
popup/
├── popup-manager.js       # המנוע הראשי - ניהול פופ-אפים
├── popup-api.js          # API לתקשורת מהתוכן
├── popup.css            # עיצוב ואנימציות
├── popup-detached.php   # עמוד לחלון נפרד
├── demo.html           # דף דמו עם דוגמאות
├── demo-content.html   # תוכן לדוגמה עם PopupAPI
└── README.md          # תיעוד זה
```

## 🚀 התקנה

### 1. טעינת קבצים

הוסף לתוך ה-`<head>` של העמוד שלך:

```html
<!-- CSS -->
<link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup.css">

<!-- JavaScript -->
<script src="/dashboard/dashboards/cemeteries/popup/popup-manager.js"></script>
```

### 2. שימוש בסיסי

```javascript
// יצירת popup פשוט
const popup = PopupManager.create({
    type: 'iframe',
    src: '/path/to/content.php',
    title: 'כותרת הפופ-אפ',
    width: 800,
    height: 600
});
```

## 📖 שימוש מתקדם

### יצירת Popup עם iframe

```javascript
const popup = PopupManager.create({
    type: 'iframe',
    src: '/forms/edit-grave.php?id=123',
    title: 'עריכת קבר',
    width: 900,
    height: 700,
    position: { x: 'center', y: 'center' },
    draggable: true,
    resizable: true,
    controls: {
        minimize: true,
        maximize: true,
        detach: true,
        close: true
    },
    onClose: function(popup) {
        // בדיקה לפני סגירה
        if (hasUnsavedChanges()) {
            return confirm('יש שינויים שלא נשמרו. האם לסגור?');
        }
        return true;
    }
});
```

### יצירת Popup עם HTML ישיר

```javascript
const popup = PopupManager.create({
    type: 'html',
    content: `
        <div style="padding: 30px;">
            <h2>כותרת</h2>
            <p>תוכן HTML כלשהו...</p>
            <button onclick="doSomething()">פעולה</button>
        </div>
    `,
    title: 'תוכן HTML',
    width: 600,
    height: 400
});
```

### יצירת Popup עם AJAX

```javascript
const popup = PopupManager.create({
    type: 'ajax',
    url: '/api/get-report.php?id=456',
    title: 'דוח',
    width: 1000,
    height: 800,
    onLoad: function(popup) {
        console.log('התוכן נטען בהצלחה!');
    }
});
```

## 🎛️ קונפיגורציה

### אפשרויות מלאות

```javascript
{
    // === תוכן ===
    type: 'iframe',           // iframe | html | ajax
    src: null,                // URL ל-iframe
    content: '',              // HTML ישיר
    url: null,                // URL ל-AJAX

    // === מראה ===
    title: 'Popup',           // כותרת
    width: 800,               // רוחב בפיקסלים
    height: 600,              // גובה בפיקסלים
    minWidth: 400,            // רוחב מינימלי
    minHeight: 300,           // גובה מינימלי
    maxWidth: null,           // רוחב מקסימלי (null = ללא הגבלה)
    maxHeight: null,          // גובה מקסימלי

    // === מיקום ===
    position: {
        x: 'center',          // 'center' או מספר (פיקסלים)
        y: 'center'           // 'center' או מספר
    },

    // === התנהגות ===
    draggable: true,          // אפשר גרירה
    resizable: true,          // אפשר שינוי גודל

    // === כפתורי בקרה ===
    controls: {
        minimize: true,       // כפתור מזעור
        maximize: true,       // כפתור מסך מלא
        detach: true,         // כפתור ניתוק לחלון נפרד
        close: true           // כפתור סגירה
    },

    // === Callbacks ===
    onMinimize: null,         // נקרא בעת מזעור
    onMaximize: null,         // נקרא בעת מסך מלא
    onRestore: null,          // נקרא בעת שחזור
    onDetach: null,           // נקרא בעת ניתוק
    onClose: null,            // נקרא לפני סגירה (return false לביטול)
    onLoad: null              // נקרא לאחר טעינת תוכן
}
```

## 🔧 PopupManager API

### יצירה וניהול

```javascript
// יצירת popup
const popup = PopupManager.create(config);

// קבלת popup לפי ID
const popup = PopupManager.get('popup-123');

// סגירת popup
PopupManager.close('popup-123');

// סגירת כל הפופ-אפים
PopupManager.closeAll();
```

### Popup Instance Methods

```javascript
// בקרה
popup.minimize();              // מזעור
popup.maximize();              // מסך מלא
popup.restore();               // שחזור למצב רגיל
popup.toggleMaximize();        // toggle בין רגיל למקסימום
popup.detach();                // ניתוק לחלון נפרד
popup.close();                 // סגירה

// עדכונים
popup.setTitle('כותרת חדשה');  // שינוי כותרת
popup.setContent('<div>...</div>'); // שינוי תוכן (רק HTML)
popup.resize(900, 700);        // שינוי גודל
popup.position(100, 100);      // שינוי מיקום

// מידע
popup.focus();                 // הבאה לחזית
console.log(popup.id);         // ID של הפופ-אפ
console.log(popup.state);      // מצב נוכחי
```

## 📡 PopupAPI - תקשורת מהתוכן

### הכללה בתוכן

בתוך התוכן שלך (iframe או HTML), כלול את PopupAPI:

```html
<script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>
```

PopupAPI מזהה אוטומטית את ה-popup ID מה-URL.

### שימוש ב-PopupAPI

```javascript
// שינוי כותרת
PopupAPI.setTitle('כותרת חדשה');

// שינוי גודל
PopupAPI.resize(900, 700);

// בקרה
PopupAPI.minimize();
PopupAPI.maximize();
PopupAPI.restore();
PopupAPI.close();
PopupAPI.detach();

// מידע
const info = PopupAPI.getInfo();
console.log(info.popupId);      // ID של הפופ-אפ
console.log(info.isInIframe);   // האם בתוך iframe
console.log(info.isDetached);   // האם חלון מנותק
console.log(info.isInPopup);    // האם בתוך popup
```

### האזנה לאירועים

```javascript
// האזנה לאירוע
PopupAPI.on('minimized', (data) => {
    console.log('הפופ-אפ מוזער!');
});

PopupAPI.on('maximized', (data) => {
    console.log('הפופ-אפ במסך מלא!');
});

PopupAPI.on('restored', (data) => {
    console.log('הפופ-אפ שוחזר!');
});

PopupAPI.on('closing', (data) => {
    // נקרא לפני סגירה - אפשר לבצע פעולות ניקוי
    console.log('הפופ-אפ עומד להיסגר!');
});

PopupAPI.on('detached', (data) => {
    console.log('הפופ-אפ נותק לחלון נפרד!');
});

// הסרת listener
PopupAPI.off('minimized', callback);
```

### Shortcuts גלובליים

PopupAPI מספק גם shortcuts גלובליים:

```javascript
popupSetTitle('כותרת');
popupResize(800, 600);
popupMinimize();
popupMaximize();
popupRestore();
popupClose();
popupDetach();
```

## 💡 דוגמאות שימוש

### 1. טופס עריכה

```javascript
// פתיחת טופס עריכה
const popup = PopupManager.create({
    type: 'iframe',
    src: '/forms/edit-customer.php?id=789',
    title: 'עריכת לקוח',
    width: 800,
    height: 600,
    onClose: function(popup) {
        // רענן טבלה אחרי סגירה
        reloadCustomersTable();
        return true;
    }
});
```

בתוך `edit-customer.php`:

```html
<script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>

<form onsubmit="handleSubmit(event)">
    <!-- שדות הטופס -->
    <button type="submit">שמור</button>
</form>

<script>
function handleSubmit(event) {
    event.preventDefault();

    // שמירת נתונים
    saveData().then(() => {
        // סגור את הפופ-אפ
        PopupAPI.close();
    });
}
</script>
```

### 2. דוח דינמי

```javascript
// פתיחת דוח
const popup = PopupManager.create({
    type: 'ajax',
    url: '/api/get-report.php?type=inventory',
    title: 'דוח מלאי',
    width: 1200,
    height: 800,
    controls: {
        minimize: true,
        maximize: true,
        detach: true,  // מאפשר פתיחה בחלון נפרד
        close: true
    }
});
```

### 3. מספר פופ-אפים במקביל

```javascript
// פתיחת מספר פופ-אפים
const popup1 = PopupManager.create({
    id: 'customer-123',
    type: 'iframe',
    src: '/customer-details.php?id=123',
    title: 'לקוח #123',
    width: 700,
    height: 500,
    position: { x: 100, y: 100 }
});

const popup2 = PopupManager.create({
    id: 'grave-456',
    type: 'iframe',
    src: '/grave-details.php?id=456',
    title: 'קבר #456',
    width: 700,
    height: 500,
    position: { x: 150, y: 150 }
});

// גישה לפופ-אפים
PopupManager.get('customer-123').minimize();
PopupManager.get('grave-456').maximize();
```

### 4. תוכן דינמי

```javascript
// יצירת תוכן דינמי
function showConfirmation(message, onConfirm) {
    const popup = PopupManager.create({
        type: 'html',
        content: `
            <div style="padding: 40px; text-align: center;">
                <h2 style="color: #667eea; margin-bottom: 20px;">אישור</h2>
                <p style="color: #64748b; font-size: 16px; margin-bottom: 30px;">
                    ${message}
                </p>
                <button onclick="handleConfirm()" style="
                    background: #667eea;
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 16px;
                    margin-left: 10px;
                ">אישור</button>
                <button onclick="popupClose()" style="
                    background: #94a3b8;
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 16px;
                ">ביטול</button>
            </div>
            <script>
                function handleConfirm() {
                    window.parent.${onConfirm.name}();
                    popupClose();
                }
            </script>
        `,
        title: 'אישור פעולה',
        width: 500,
        height: 300,
        draggable: true,
        controls: {
            close: true
        }
    });
}

// שימוש
showConfirmation('האם אתה בטוח?', function onConfirmDelete() {
    deleteItem();
});
```

## 🎨 התאמה אישית

### עיצוב מותאם

ניתן לשנות את העיצוב על ידי override של ה-CSS:

```css
/* שינוי צבע header */
.popup-header {
    background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
}

/* שינוי צבע כפתורים */
.popup-control-btn {
    background: rgba(255, 255, 255, 0.2);
}

/* שינוי צללית */
.popup-container {
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}
```

### גודל ברירת מחדל

```javascript
// קבע ברירות מחדל חדשות
PopupManager.defaultConfig = {
    width: 900,
    height: 700,
    position: { x: 'center', y: 100 }
};
```

## 🔍 Troubleshooting

### הפופ-אפ לא נפתח

1. בדוק שטענת את כל הקבצים הנדרשים (CSS + JS)
2. בדוק קונסול לשגיאות
3. ודא שה-src נכון (אם משתמש ב-iframe)

### PopupAPI לא עובד

1. ודא שכללת את `popup-api.js` בתוכן
2. בדוק שה-URL מכיל את `popupId` (נוסף אוטומטית על ידי PopupManager)
3. בדוק שאין בעיות CORS (אם iframe מדומיין אחר)

### Detach לא עובד

1. בדוק שחוסם הפופ-אפ לא חוסם את החלון
2. ודא ש-localStorage זמין
3. בדוק ש-`popup-detached.php` נמצא בנתיב הנכון

## 🧪 בדיקות

פתח את `demo.html` לבדיקת כל התכונות:

```
/dashboard/dashboards/cemeteries/popup/demo.html
```

הדמו כולל:
- דוגמאות לכל סוגי התוכן (iframe, HTML, AJAX)
- בדיקת כל הכפתורים והפעולות
- דוגמאות קוד מוכנות
- תיעוד API מלא

## 📱 תמיכה במסכים

- **Desktop** - תמיכה מלאה בכל התכונות
- **Tablet** - draggable + resizable
- **Mobile** - מסך מלא אוטומטי (responsive)

## 🌍 תמיכה בדפדפנים

- ✅ Chrome / Edge (גרסאות עדכניות)
- ✅ Firefox (גרסאות עדכניות)
- ✅ Safari (גרסאות עדכניות)
- ⚠️ IE11 - לא נתמך

## 📄 רישיון

MIT License - שימוש חופשי

## 🤝 תרומה

באגים או בקשות לתכונות? פתח issue בגיטהאב.

---

**Version:** 1.0.0
**Last Updated:** 2026-01-10
**Author:** Cemetery Management System
