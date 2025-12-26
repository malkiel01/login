# 🏗️ Architecture Documentation - PDF Editor System

תיעוד מפורט של הארכיטקטורה והמבנה הטכני של המערכת.

---

## 📊 System Overview

מערכת עריכת PDF מבוססת web המאפשרת:
- ✅ העלאה של קבצי PDF
- ✅ הוספת טקסטים עבריים עם פונטים מותאמים
- ✅ הוספת תמונות
- ✅ גרירה ושינוי גודל אינטראקטיבי
- ✅ שמירת תבניות לשימוש חוזר
- ✅ ייצוא PDF מעובד להורדה

---

## 🎯 Architecture Principles

### 1. **Separation of Concerns**
- Frontend: UI ואינטראקציה עם משתמש
- Backend: לוגיקה עסקית ועיבוד קבצים
- Processing: עיבוד PDF בפועל (Python)

### 2. **Modularity**
- כל module עושה דבר אחד בלבד
- קוד reusable
- dependency injection

### 3. **Progressive Enhancement**
- עבודה ללא JavaScript (fallback)
- Responsive design
- Accessibility

### 4. **Security First**
- Validation של כל input
- Sanitization של קבצים
- Prevention של injection attacks

---

## 📁 Directory Structure

```
files/
│
├── index.html                  # נקודת כניסה ראשית
│
├── config/                     # 🆕 הגדרות מערכת
│   └── config.php              # קבועים וקונפיגורציה
│
├── src/                        # 🆕 קוד מקור מאורגן
│   ├── js/                     # JavaScript מודולרי
│   │   ├── app.js              # נקודת כניסה
│   │   ├── config.js           # הגדרות JS
│   │   │
│   │   ├── core/               # ליבת המערכת
│   │   │   ├── EventBus.js     # Event system
│   │   │   ├── State.js        # State management
│   │   │   └── API.js          # HTTP requests
│   │   │
│   │   ├── models/             # Data models
│   │   │   ├── TextItem.js
│   │   │   ├── ImageItem.js
│   │   │   ├── Template.js
│   │   │   └── PDFDocument.js
│   │   │
│   │   ├── managers/           # Business logic
│   │   │   ├── FontManager.js
│   │   │   ├── FileManager.js
│   │   │   ├── TemplateManager.js
│   │   │   └── ItemsManager.js
│   │   │
│   │   ├── components/         # UI Components
│   │   │   ├── FileUploader.js
│   │   │   ├── PDFPreview.js
│   │   │   ├── ItemsList.js
│   │   │   ├── TextItemForm.js
│   │   │   ├── ImageItemForm.js
│   │   │   ├── Modal.js
│   │   │   └── Toast.js
│   │   │
│   │   ├── canvas/             # Canvas rendering
│   │   │   ├── CanvasRenderer.js
│   │   │   ├── CanvasInteraction.js
│   │   │   └── LayerManager.js
│   │   │
│   │   └── utils/              # Helper functions
│   │       ├── DOMHelpers.js
│   │       ├── Validators.js
│   │       ├── Formatters.js
│   │       └── DragAndDrop.js
│   │
│   └── php/                    # 🆕 PHP מסודר
│       ├── bootstrap.php       # Autoloader
│       │
│       ├── core/               # Core functionality
│       │   ├── Config.php
│       │   ├── Response.php
│       │   └── ErrorHandler.php
│       │
│       ├── models/             # Business models
│       │   ├── PDFProcessor.php
│       │   ├── Template.php
│       │   └── File.php
│       │
│       ├── services/           # Business logic
│       │   ├── PDFService.php
│       │   ├── TemplateService.php
│       │   ├── FileService.php
│       │   └── ValidationService.php
│       │
│       └── api/                # API endpoints
│           ├── process.php
│           ├── templates.php
│           ├── files.php
│           └── download.php
│
├── assets/                     # 🆕 Static assets
│   ├── css/
│   │   ├── main.css
│   │   ├── components.css
│   │   └── responsive.css
│   │
│   └── images/
│
├── python/                     # 🆕 Python processing
│   ├── pdf_processor.py
│   └── requirements.txt
│
├── fonts/                      # Font files
│   ├── system/
│   ├── custom/
│   └── ...
│
├── templates/                  # Saved templates
│   └── template_xxx/
│       ├── template.pdf
│       └── config.json
│
├── uploads/                    # Temporary uploads
├── outputs/                    # Processed PDFs
│
├── docs/                       # 🆕 Documentation
│   ├── API.md
│   ├── ARCHITECTURE.md         # (this file)
│   ├── COMPONENTS.md
│   └── DEVELOPMENT.md
│
├── fonts.json                  # Font configuration
├── templates.json              # Templates list
│
└── Legacy files (Phase 1):
    ├── script.js               # ⚠️ Will be replaced
    ├── styles.css              # ⚠️ Will be replaced
    ├── process.php             # ⚠️ Will be refactored
    └── add_text_to_pdf.py      # ⚠️ Will be replaced
```

🆕 = New in Phase 1 refactoring
⚠️ = Legacy code (to be phased out)

---

## 🔄 Data Flow

### 1. PDF Upload & Processing Flow

```
User
 │
 ├─> [Upload PDF] ────────────┐
 │                             │
 ├─> [Add Texts/Images] ──────┤
 │                             │
 └─> [Click "Process"] ────────┤
                               │
                               ↓
                    ┌──────────────────┐
                    │  Frontend (JS)   │
                    │  FileUploader    │
                    └────────┬─────────┘
                             │
                             │ FormData (pdf + allItems)
                             ↓
                    ┌──────────────────┐
                    │  Backend (PHP)   │
                    │  process.php     │
                    └────────┬─────────┘
                             │
                             │ Validate & Save
                             ↓
                    ┌──────────────────┐
                    │  Python Script   │
                    │  pdf_processor.py│
                    └────────┬─────────┘
                             │
                             │ pypdf + reportlab
                             ↓
                    ┌──────────────────┐
                    │  Output PDF      │
                    │  (with overlays) │
                    └────────┬─────────┘
                             │
                             │ Return metadata
                             ↓
                    ┌──────────────────┐
                    │  Backend (PHP)   │
                    │  JSON Response   │
                    └────────┬─────────┘
                             │
                             ↓
                    ┌──────────────────┐
                    │  Frontend (JS)   │
                    │  Show Results    │
                    └──────────────────┘
                             │
                             ├─> [Download]
                             └─> [Save Template]
```

### 2. Template Save Flow

```
User clicks "Save Template"
 │
 ├─> Modal opens
 ├─> Enter name & description
 └─> Click "Save"
      │
      ↓
Frontend (TemplateManager)
 │ - Collect all items
 │ - Collect PDF metadata
 │ - Create FormData
 └─> POST to save_template.php
      │
      ↓
Backend (save_template.php)
 │ - Validate template name
 │ - Check duplicates
 │ - Generate template_id
 │ - Create template folder
 │ - Save PDF file
 │ - Save config.json
 └─> Update templates.json
      │
      ↓
Return success + template_id
```

### 3. Canvas Rendering Flow

```
PDF Loaded (PDF.js)
 │
 ├─> Get page
 ├─> Create viewport
 └─> Render to canvas
      │
      ↓
For each item in allItems (sorted by layer):
 │
 ├─> If type === "text":
 │    ├─> Calculate position
 │    ├─> Set font & color
 │    └─> Draw text
 │
 └─> If type === "image":
      ├─> Load image from base64
      ├─> Calculate position
      └─> Draw image
           │
           ↓
If item is selected:
 │
 └─> Draw selection box + resize handles
```

---

## 🧩 Component Interactions

### Frontend Components

```
App (Main Controller)
 │
 ├─> EventBus (Global events)
 │
 ├─> State (Global state)
 │
 ├─> FontManager
 │    └─> Loads fonts.json
 │         └─> Registers fonts with document.fonts
 │
 ├─> FileUploader
 │    ├─> Handles drag & drop
 │    ├─> Validates file type
 │    └─> Emits "file:selected"
 │
 ├─> PDFPreview
 │    ├─> Uses PDF.js
 │    ├─> Manages canvas
 │    ├─> Handles zoom
 │    └─> Renders items overlay
 │
 ├─> ItemsManager
 │    ├─> Manages textItems[]
 │    ├─> Manages imageItems[]
 │    ├─> Manages allItems[] (layer order)
 │    └─> Emits "items:changed"
 │
 ├─> ItemsList (UI)
 │    ├─> Renders item forms
 │    ├─> Handles drag & drop reorder
 │    └─> Collapse/expand
 │
 ├─> CanvasInteraction
 │    ├─> Mouse events
 │    ├─> Drag items
 │    ├─> Resize items
 │    └─> Select items
 │
 └─> TemplateManager
      ├─> Save template
      ├─> Load template
      └─> Delete template
```

### Backend Services

```
API Endpoint (process.php)
 │
 ├─> ValidationService
 │    ├─> validatePDFFile()
 │    └─> validateItems()
 │
 ├─> FileService
 │    ├─> saveUploadedFile()
 │    ├─> cleanOldFiles()
 │    └─> generateUniqueId()
 │
 └─> PDFService
      ├─> process($file, $items)
      ├─> callPythonProcessor()
      └─> parseOutput()
```

---

## 🎨 Design Patterns Used

### 1. **Module Pattern** (JavaScript)
```javascript
// Each file exports a module
export class FontManager {
    // ...
}
```

### 2. **Observer Pattern** (EventBus)
```javascript
eventBus.on('item:added', (item) => {
    // React to event
});

eventBus.emit('item:added', newItem);
```

### 3. **Singleton Pattern** (Config, State)
```javascript
// Only one instance of Config/State
const state = State.getInstance();
```

### 4. **Factory Pattern** (Item creation)
```javascript
ItemFactory.create('text', {...});
ItemFactory.create('image', {...});
```

### 5. **Strategy Pattern** (Rendering)
```javascript
// Different rendering strategies for text vs image
TextRenderer.render(item, canvas);
ImageRenderer.render(item, canvas);
```

---

## 🔐 Security Architecture

### Input Validation Layers

```
Layer 1: Frontend Validation
 │ - File type check
 │ - File size check
 │ - Form validation
 ↓
Layer 2: Backend Validation
 │ - MIME type verification
 │ - Filename sanitization
 │ - JSON structure validation
 ↓
Layer 3: Processing Validation
 │ - escapeshellarg() for Python calls
 │ - Path traversal prevention
 │ - Regex validation
```

### File Management Security

```
Uploads/
 ├─> Unique filenames (uniqid)
 ├─> MIME type check
 ├─> Auto-cleanup (1 hour)
 └─> No direct access (outside web root)

Templates/
 ├─> Template ID validation
 ├─> No executable files
 └─> JSON validation
```

---

## 📦 Dependencies

### Frontend
- **PDF.js** - PDF rendering in browser
- **Native ES6 Modules** - No bundler needed (Phase 1)

### Backend
- **PHP 7.4+** - Server-side logic
- **Python 3.8+** - PDF processing
  - pypdf - PDF manipulation
  - reportlab - PDF generation
  - Pillow - Image handling

### Future Dependencies (Phase 2+)
- Webpack/Vite - Module bundling
- ESLint - Code linting
- PHPUnit - Unit testing

---

## 🚀 Performance Considerations

### Frontend Optimization
- ✅ Lazy loading of PDF pages
- ✅ Debouncing of drag events
- ✅ Canvas rendering optimization
- 🔜 Virtual scrolling for long item lists
- 🔜 Web Workers for heavy operations

### Backend Optimization
- ✅ Auto-cleanup of old files
- ✅ Streaming file downloads
- 🔜 Caching of processed PDFs
- 🔜 Queue system for batch processing

---

## 🧪 Testing Strategy

### Phase 1 (Current)
- ✅ Manual testing
- ✅ Cross-browser testing
- ✅ Different PDF files

### Phase 2+ (Planned)
- Unit tests (PHPUnit)
- Integration tests
- E2E tests (Playwright/Cypress)
- Performance testing

---

## 🔄 Migration Strategy (Strangler Fig)

```
Old System (Legacy)          New System (Refactored)
┌──────────────┐            ┌──────────────┐
│ script.js    │            │ src/js/*     │
│ (1764 lines) │ ────────>  │ (modular)    │
└──────────────┘            └──────────────┘
       │                            │
       │                            │
       ├─> Both work in parallel    │
       │                            │
       └────────> Gradual migration ┘
```

### Phase by Phase:
1. ✅ **Phase 1** - Create new structure (current)
2. **Phase 2** - Migrate Backend
3. **Phase 3** - Migrate CSS
4. **Phase 4** - Migrate JavaScript
5. **Phase 5** - Remove old code

---

## 📚 Related Documentation

- [API.md](./API.md) - API endpoints documentation
- [COMPONENTS.md](./COMPONENTS.md) - Component documentation
- [DEVELOPMENT.md](./DEVELOPMENT.md) - Development guide
- [../REFACTORING_PLAN.md](../REFACTORING_PLAN.md) - Refactoring strategy

---

## 🔮 Future Architecture

### Planned Improvements

1. **Microservices** (Optional)
   - Separate PDF processing service
   - Template service
   - File storage service

2. **API Versioning**
   - `/api/v1/process`
   - `/api/v2/process`

3. **WebSocket Support**
   - Real-time collaboration
   - Live preview sync

4. **Cloud Storage Integration**
   - S3/Google Cloud Storage
   - CDN for fonts

---

Last Updated: Phase 1 - December 2025
