# PDF Editor - מערכת עיבוד PDF מתקדמת 📄

מערכת מקצועית להוספת טקסטים ותמונות על קבצי PDF, עם תמיכה בתבניות, שכבות ועריכה חזותית.

## ✨ תכונות

- 📄 **עיבוד PDF** - הוספת טקסטים ותמונות על כל דף
- 🎨 **עריכה חזותית** - גרירה והזזה של אלמנטים בזמן אמת
- 📝 **פונטים עבריים** - דיויד, רוביק, פרנקנתן בית אלף ועוד
- 🖼️ **תמונות** - העלאה, שינוי גודל, מיקום חופשי
- 💾 **תבניות** - שמירה וטעינה של תבניות מוכנות
- 🔄 **ניהול שכבות** - בחירה, מיקום והסתרה של שכבות
- 🔍 **Zoom** - הגדלה והקטנה של תצוגת ה-PDF
- 📱 **Responsive** - עובד על מסכים שונים
- ⚡ **Real-time Preview** - רינדור מיידי של שינויים
- 🎯 **Drag & Drop** - גרירת קבצים וגרירת אלמנטים

## 🏗️ ארכיטקטורה מודולרית

הפרויקט עבר רפקטורינג מקיף ב-5 שלבים והוא מאורגן בצורה מקצועית:

```
├── config/
│   └── config.php                 # הגדרות מערכת מרכזיות
├── docs/                          # תיעוד מפורט
│   ├── API.md                     # תיעוד API מלא
│   ├── ARCHITECTURE.md            # תיאור ארכיטקטורה
│   ├── COMPONENTS.md              # תיעוד קומפוננטות
│   └── REFACTORING_PLAN.md        # תוכנית הרפקטורינג
├── src/
│   ├── css/                       # CSS מודולרי
│   │   ├── base/                  # Reset, Variables, Typography
│   │   ├── layout/                # Main layout structures
│   │   ├── components/            # UI components
│   │   └── main.css               # Entry point
│   ├── js/                        # JavaScript ES6 Modules
│   │   ├── core/                  # Core functionality (ready)
│   │   ├── modules/               # Feature modules (ready)
│   │   ├── utils/                 # Utilities (ready)
│   │   ├── legacy.js              # Original code (1763 lines)
│   │   └── main.js                # Module entry point
│   └── php/                       # Backend Services
│       ├── core/
│       │   └── Response.php       # JSON response standardization
│       ├── services/
│       │   ├── ValidationService.php  # Input validation
│       │   ├── FileService.php        # File management
│       │   └── PDFService.php         # PDF processing
│       └── bootstrap.php          # PSR-4 autoloader + init
├── python/
│   └── add_text_to_pdf.py        # Python PDF processor
├── uploads/                       # Uploaded PDFs (auto-cleanup)
├── outputs/                       # Processed PDFs (auto-cleanup)
├── templates/                     # Saved templates
├── logs/                          # Error logs
├── index.html                     # Main interface
└── process.php                    # API endpoint (with fallback)
```

## 🚀 התקנה

### דרישות מקדימות

- **PHP** 7.4+
- **Python** 3.8+
- **Web Server** (Apache/Nginx)
- **Python Packages**: `pypdf`, `reportlab`

### צעדי התקנה

1. **Clone או Download הפרויקט**
```bash
git clone [repository-url]
cd dashboard/dashboards/cemeteries/files/
```

2. **התקנת חבילות Python**
```bash
pip install pypdf reportlab
# או
pip install --break-system-packages pypdf reportlab
```

3. **הגדרת הרשאות**
```bash
chmod 755 uploads/ outputs/ templates/ logs/
chmod +x python/add_text_to_pdf.py
```

4. **עדכון הגדרות (אופציונלי)**
ערוך את `config/config.php` והתאם נתיבים ל-Python virtual environment אם נדרש.

### הרצה מקומית

```bash
php -S localhost:8000
```

גש ל-`http://localhost:8000`

## 📖 תיעוד מפורט

- **[API.md](docs/API.md)** - תיעוד מלא של כל ה-endpoints
- **[ARCHITECTURE.md](docs/ARCHITECTURE.md)** - תרשימים ותיאור ארכיטקטורה
- **[COMPONENTS.md](docs/COMPONENTS.md)** - תיעוד Frontend + Backend components
- **[REFACTORING_PLAN.md](docs/REFACTORING_PLAN.md)** - תוכנית רפקטורינג של 5 שלבים

## 🔧 טכנולוגיות

### Frontend
- **HTML5** - Semantic markup
- **CSS3** - Modular CSS with Variables
- **JavaScript ES6** - Modules + Modern syntax
- **PDF.js** - Client-side PDF rendering

### Backend
- **PHP 7.4+** - Service layer architecture
- **PSR-4 Autoloading** - Namespace-based class loading
- **Service Pattern** - ValidationService, FileService, PDFService

### Python
- **pypdf** - PDF manipulation
- **reportlab** - PDF content generation

## 🎯 Refactoring Phases (Completed)

הפרויקט עבר 5 שלבי רפקטורינג מקיפים:

- ✅ **Phase 1**: Infrastructure - config, docs, structure
- ✅ **Phase 2**: Backend - PHP services architecture
  - ✅ **Phase 2.1**: Exception handler conflict fix
  - ✅ **Phase 2.2**: Autoloader case-sensitivity fix
- ✅ **Phase 3**: CSS - Modular structure with variables
- ✅ **Phase 4**: JavaScript - ES6 modules preparation
- ✅ **Phase 5**: Documentation & final touches

## 🔐 אבטחה

- ✅ Input validation על כל הקלטים
- ✅ File type validation (MIME + extension)
- ✅ Path sanitization
- ✅ Automatic file cleanup (1 hour TTL)
- ✅ Error logging without sensitive data exposure
- ✅ Prepared statements (ready for DB)

## 🐛 Debugging

להפעלת debug mode, ערוך `config/config.php`:

```php
const DEBUG_MODE = true;
const LOG_ERRORS = true;
```

לוגים נשמרים ב-`logs/error.log`

## 📚 כיצד להשתמש

1. פתח את `index.html`
2. העלה קובץ PDF (drag & drop או בחירה)
3. הוסף טקסטים ותמונות
4. גרור ושנה מיקום/גודל
5. שמור כתבנית (אופציונלי)
6. לחץ "עבד קובץ"
7. הורד PDF מעובד

## 🚧 עבודה עתידית (Phase 4.1+, 3.1+)

### JavaScript Modules
- חילוץ modules מ-legacy.js:
  - `core/config.js` - קבועים ומשתנים
  - `core/state.js` - State management
  - `modules/pdf-viewer.js` - PDF rendering
  - `modules/text-editor.js` - Text editing
  - `modules/image-handler.js` - Image handling
  - `utils/drag-drop.js` - Drag utilities
  - `utils/fonts.js` - Font loading

### CSS Components
- חילוץ components מ-legacy.css:
  - `components/upload-area.css`
  - `components/buttons.css`
  - `components/pdf-viewer.css`
  - `components/toolbar.css`
  - `components/modals.css`

## 🙏 תודות

- **Claude Code** - Refactoring & Architecture
- **Original Developer** - Initial implementation
- **Community** - Testing & Feedback

## 📝 License

[ציין רישיון לפי הצורך]

---

**נבנה עם ❤️ ו-קוד נקי**

_Refactored: December 2024_
