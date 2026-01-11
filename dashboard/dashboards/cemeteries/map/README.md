# ארכיטקטורת מפות - תיעוד טכני

## סקירה כללית

מערכת המפות עברה רפקטורינג מלא להפוך אותה למודולרית, גנרית וניתנת לתחזוקה.
הקוד המקורי (2,967 שורות במחלקה אחת עם 22 משתנים גלובליים) פוצל למודולים עצמאיים ונקיים.

---

## מבנה התיקיות

```
map/
├── core/                         # מודולי ליבה
│   ├── MapManager.js            # מנהל המפה המרכזי
│   ├── HistoryManager.js        # ניהול Undo/Redo
│   ├── BoundaryManager.js       # ניהול גבולות ואילוצים
│   └── BackgroundManager.js     # ניהול תמונות רקע ו-PDF
│
├── api/                          # תקשורת שרת
│   └── MapAPI.js                # ממשק API מבוסס Promise
│
├── config/                       # קונפיגורציה
│   └── EntityConfig.js          # הגדרות ישויות גנריות
│
├── editors/                      # עורכים
│   ├── PolygonEditor.js         # עורך פוליגונים
│   └── ContextMenu.js           # תפריט הקשר
│
├── utils/                        # עזרים
│   ├── geometry.js              # חישובים גיאומטריים
│   └── canvas.js                # פעולות Fabric.js
│
├── ui/                           # ממשק משתמש (לפיתוח עתידי)
│   ├── MapModal.js
│   ├── MapToolbar.js
│   └── MapControls.js
│
├── css/                          # עיצוב
│   └── cemetery-map.css
│
└── index.php                     # נקודת כניסה

```

---

## שימוש בסיסי

### יצירת מפה חדשה

```javascript
import { MapManager } from './core/MapManager.js';
import { EntityConfig } from './config/EntityConfig.js';

// יצירת מפה
const mapManager = new MapManager({
    entityType: 'cemetery',
    entityId: 'abc123',
    mode: 'view',  // או 'edit'
    canvasId: 'mapCanvas'
});

// אתחול
await mapManager.init();

// האזנה לאירועים
mapManager.on('data:loaded', (data) => {
    console.log('Data loaded:', data);
});

mapManager.on('map:saved', (data) => {
    console.log('Map saved:', data);
});
```

### עבודה עם היררכיית ישויות

```javascript
import { EntityConfig, CEMETERY_ENTITIES } from './config/EntityConfig.js';

// יצירת קונפיגורציה
const entityConfig = new EntityConfig(CEMETERY_ENTITIES);

// קבלת מידע על ישות
const cemeteryDef = entityConfig.get('cemetery');
console.log(cemeteryDef.color);      // '#1976D2'
console.log(cemeteryDef.childType);  // 'block'

// בדיקות היררכיה
console.log(entityConfig.isParentOf('cemetery', 'block'));  // true
console.log(entityConfig.getParentType('block'));           // 'cemetery'
console.log(entityConfig.getAllTypes());                    // ['cemetery', 'block', 'plot', ...]
```

### שימוש ב-API

```javascript
import { MapAPI, EntityAPI } from './api/MapAPI.js';

const mapAPI = new MapAPI();

// טעינת מפה
const data = await mapAPI.loadMap('cemetery', 'abc123', true);

// שמירת פוליגון
await mapAPI.savePolygon('cemetery', 'abc123', {
    points: [{x: 0, y: 0}, {x: 100, y: 0}, {x: 100, y: 100}, {x: 0, y: 100}],
    style: {
        fillColor: '#1976D2',
        fillOpacity: 0.3,
        strokeColor: '#0D47A1',
        strokeWidth: 2
    }
});

// העלאת תמונת רקע
const fileInput = document.getElementById('fileInput');
const file = fileInput.files[0];
await mapAPI.uploadBackground('cemetery', 'abc123', file);
```

### עבודה עם HistoryManager

```javascript
// ההיסטוריה נוצרת אוטומטית על ידי MapManager
const history = mapManager.history;

// Undo/Redo
history.undo();
history.redo();

// בדיקות
console.log(history.canUndo());  // true/false
console.log(history.canRedo());  // true/false

// קבלת מידע
const info = history.getInfo();
console.log(info.totalStates);    // מספר המצבים
console.log(info.currentIndex);   // אינדקס נוכחי

// שמירה/טעינה מ-LocalStorage
history.saveToLocalStorage('map-history-abc123');
history.loadFromLocalStorage('map-history-abc123');
```

### עבודה עם BoundaryManager

```javascript
const boundary = mapManager.boundary;

// טעינת גבול הורה
await boundary.loadParentBoundary('cemetery', 'parent-id');

// הגדרת גבול מותאם אישית
boundary.setBoundary([
    {x: 0, y: 0},
    {x: 500, y: 0},
    {x: 500, y: 500},
    {x: 0, y: 500}
], {
    name: 'גבול מותאם',
    color: '#FF0000',
    showOutline: true,
    showMask: true
});

// בדיקות
const isInside = boundary.isPointInsideBoundary({x: 100, y: 100});
const polygonValid = boundary.isPolygonInsideBoundary([
    {x: 50, y: 50},
    {x: 150, y: 50},
    {x: 150, y: 150},
    {x: 50, y: 150}
]);

// מצב עריכה
boundary.enterEditMode();
boundary.exitEditMode();
```

### עבודה עם BackgroundManager

```javascript
const background = mapManager.background;

// טעינת תמונה מנתיב
await background.loadBackground({
    path: '/uploads/maps/bg.jpg',
    width: 2000,
    height: 1500,
    offsetX: 0,
    offsetY: 0,
    scale: 1
});

// העלאת תמונה חדשה
const file = document.getElementById('bgInput').files[0];
await background.uploadImage(file, 'cemetery', 'abc123');

// העלאת PDF
const pdfFile = document.getElementById('pdfInput').files[0];
await background.uploadPDF(pdfFile, 'cemetery', 'abc123');

// מצב עריכה
background.enterEditMode();
background.exitEditMode();

// התאמה ל-Canvas
background.fitToCanvas();

// מחיקה
await background.deleteBackground('cemetery', 'abc123');
```

### פונקציות גיאומטריה

```javascript
import * as geometry from './utils/geometry.js';

// בדיקת נקודה בפוליגון
const inside = geometry.isPointInPolygon(
    {x: 50, y: 50},
    [{x: 0, y: 0}, {x: 100, y: 0}, {x: 100, y: 100}, {x: 0, y: 100}]
);

// חישוב שטח
const area = geometry.getPolygonArea(polygon);

// חישוב מרכז
const center = geometry.getPolygonCenter(polygon);

// מרחק בין נקודות
const dist = geometry.distance({x: 0, y: 0}, {x: 100, y: 100});

// סיבוב פוליגון
const rotated = geometry.rotatePolygon(polygon, Math.PI / 4);

// סקייל פוליגון
const scaled = geometry.scalePolygon(polygon, 1.5);

// פישוט פוליגון (Douglas-Peucker)
const simplified = geometry.simplifyPolygon(polygon, 2.0);
```

### פונקציות Canvas

```javascript
import * as canvasUtils from './utils/canvas.js';

// זום להתאמה
canvasUtils.zoomToFitObjects(canvas);

// יצירת רשת
const grid = canvasUtils.createGrid(canvas, 50, {
    color: '#e0e0e0',
    strokeWidth: 1
});
canvas.add(grid);

// ייצוא כתמונה
const dataURL = canvasUtils.exportCanvasAsImage(canvas, {
    format: 'png',
    quality: 1,
    multiplier: 2
});

// הורדה כקובץ
canvasUtils.downloadCanvasAsImage(canvas, 'my-map.png');

// אנימציה
await canvasUtils.animateObject(object, {
    left: 200,
    top: 200,
    opacity: 0.5
}, {
    duration: 500
});

// הדגשה
canvasUtils.highlightObject(object, {
    strokeColor: '#FFD700',
    strokeWidth: 4,
    duration: 500
});
```

---

## אירועים (Events)

### MapManager Events

```javascript
mapManager.on('init:complete', (data) => {
    // אתחול הושלם
});

mapManager.on('data:loaded', (data) => {
    // נתונים נטענו
    console.log(data.entity, data.children);
});

mapManager.on('map:saved', (data) => {
    // מפה נשמרה
});

mapManager.on('zoom:changed', (data) => {
    // זום השתנה
    console.log(data.zoom);
});

mapManager.on('mode:edit', () => {
    // נכנס למצב עריכה
});

mapManager.on('mode:view', () => {
    // נכנס למצב צפייה
});

mapManager.on('entity:click', (data) => {
    // לחיצה על ישות
    console.log(data.entityType, data.entityId);
});
```

---

## הרחבה והתאמה אישית

### יצירת קונפיגורציית ישויות חדשה

```javascript
const MY_ENTITIES = {
    country: {
        table: 'countries',
        nameField: 'countryName',
        parentField: null,
        color: '#FF5722',
        fillOpacity: 0.3,
        strokeColor: '#D84315',
        strokeWidth: 2,
        icon: '🌍',
        labelHe: 'מדינה',
        labelEn: 'Country',
        minZoom: 0,
        hasChildren: true,
        childType: 'city'
    },

    city: {
        table: 'cities',
        nameField: 'cityName',
        parentField: 'countryId',
        color: '#4CAF50',
        fillOpacity: 0.3,
        strokeColor: '#388E3C',
        strokeWidth: 2,
        icon: '🏙️',
        labelHe: 'עיר',
        labelEn: 'City',
        minZoom: 0.5,
        hasChildren: true,
        childType: 'district'
    }
};

const entityConfig = new EntityConfig(MY_ENTITIES);
const mapManager = new MapManager({
    entityType: 'country',
    entityId: 'israel',
    entityConfig: entityConfig
});
```

### הרחבת MapManager

```javascript
class CustomMapManager extends MapManager {
    async init() {
        await super.init();

        // הוספת פונקציונליות מותאמת אישית
        this.setupCustomFeatures();
    }

    setupCustomFeatures() {
        // תכונות נוספות
    }

    async customSave() {
        // לוגיקת שמירה מותאמת
        const data = await this.save();
        // עיבוד נוסף...
        return data;
    }
}
```

---

## ביצועים ואופטימיזציה

### טיפים לשיפור ביצועים

1. **השבתת objectCaching** - השתמש ב-`objectCaching: false` לאובייקטים דינמיים
2. **Debouncing** - HistoryManager משתמש ב-debounce אוטומטי (500ms)
3. **עיגול קואורדינטות** - השתמש ב-`geometry.roundPoints()` למניעת בעיות rendering
4. **הגבלת זום** - הגדר `minZoom` ו-`maxZoom` הגיוניים
5. **פישוט פוליגונים** - השתמש ב-`simplifyPolygon()` לפוליגונים מורכבים

### דוגמה

```javascript
// פישוט פוליגון לפני שמירה
const simplified = geometry.simplifyPolygon(complexPolygon, 2.0);
await mapAPI.savePolygon(entityType, entityId, {
    points: simplified,
    style: {...}
});
```

---

## טיפול בשגיאות

```javascript
try {
    await mapManager.init();
} catch (error) {
    console.error('Failed to initialize map:', error);
    // טיפול בשגיאה
}

mapManager.on('init:error', (data) => {
    console.error('Init error:', data.error);
    alert('שגיאה בטעינת המפה');
});

mapManager.on('data:error', (data) => {
    console.error('Data error:', data.error);
});

mapManager.on('map:save-error', (data) => {
    console.error('Save error:', data.error);
    alert('שגיאה בשמירת המפה');
});
```

---

## סיכום שינויים

### לפני הרפקטורינג
- **map-launcher.js**: 2,967 שורות, 22 משתנים גלובליים, 50+ פונקציות גלובליות
- קוד מעורבב (UI + Logic + Data)
- שמות ספציפיים ל-Cemetery
- קשה לתחזוקה והרחבה

### אחרי הרפקטורינג
- **8 מודולים מרכזיים** במבנה נקי
- **קונפיגורציה גנרית** - ניתן לשימוש חוזר
- **הפרדת אחריות** - כל מודול עם תפקיד ברור
- **Promise-based API** - קוד מודרני ונקי
- **תיעוד מלא** - JSDoc על כל פונקציה
- **קל להרחבה** - ניתן להוסיף תכונות בקלות

---

## רישיון ותמיכה

מערכת זו פותחה כחלק מפרויקט ניהול בתי עלמין.
לשאלות ותמיכה: צור קשר עם צוות הפיתוח.

**גרסה**: 2.0.0
**תאריך עדכון**: ינואר 2026
