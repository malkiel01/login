# 🧩 Components Documentation - PDF Editor System

תיעוד מפורט של כל הקומפוננטות במערכת (Frontend & Backend).

---

## 📋 Table of Contents

- [Frontend Components](#frontend-components)
  - [Core](#core)
  - [Models](#models)
  - [Managers](#managers)
  - [UI Components](#ui-components)
  - [Canvas](#canvas)
  - [Utils](#utils)
- [Backend Components](#backend-components)
  - [Core](#backend-core)
  - [Models](#backend-models)
  - [Services](#services)
  - [API Endpoints](#api-endpoints)

---

# Frontend Components

## Core

### EventBus
**Path:** `src/js/core/EventBus.js`

מערכת אירועים גלובלית לתקשורת בין קומפוננטות.

```javascript
class EventBus {
    on(event, callback)      // הרשמה לאירוע
    off(event, callback)     // ביטול הרשמה
    emit(event, data)        // שידור אירוע
}
```

**Events:**
- `file:selected` - קובץ נבחר
- `pdf:loaded` - PDF נטען
- `item:added` - פריט נוסף
- `item:updated` - פריט עודכן
- `item:removed` - פריט הוסר
- `item:selected` - פריט נבחר
- `template:saved` - תבנית נשמרה
- `template:loaded` - תבנית נטענה

**Example:**
```javascript
eventBus.on('item:added', (item) => {
    console.log('New item:', item);
});

eventBus.emit('item:added', newItem);
```

---

### State
**Path:** `src/js/core/State.js`

ניהול state גלובלי של האפליקציה.

```javascript
class State {
    get(key)                 // קבלת ערך
    set(key, value)          // עדכון ערך
    subscribe(key, callback) // הרשמה לשינויים
}
```

**State Keys:**
- `currentFile` - הקובץ הנוכחי
- `pdfDocument` - מסמך PDF נטען
- `currentPage` - עמוד נוכחי
- `zoom` - רמת זום
- `selectedItemId` - ID של פריט נבחר
- `processedFileName` - שם קובץ מעובד
- `isProcessing` - האם בעיבוד

**Example:**
```javascript
state.set('zoom', 1.5);
const zoom = state.get('zoom');

state.subscribe('zoom', (newZoom) => {
    updateZoomDisplay(newZoom);
});
```

---

### API
**Path:** `src/js/core/API.js`

שכבת תקשורת עם השרת.

```javascript
class API {
    async processPDF(file, items)
    async downloadPDF(filename)
    async deleteFile(type, filename)
    async saveTemplate(templateData, pdfFile)
    async getTemplates(templateId?)
    async deleteTemplate(templateId)
}
```

**Example:**
```javascript
const result = await API.processPDF(pdfFile, allItems);
if (result.success) {
    console.log('Processed:', result.output_file);
}
```

---

## Models

### TextItem
**Path:** `src/js/models/TextItem.js`

מודל לפריט טקסט.

```javascript
class TextItem {
    id: number
    type: 'text'
    text: string
    font: string
    size: number
    color: string
    top: number
    right: number
    page: number
    align: 'right' | 'left'
}
```

**Methods:**
```javascript
toJSON()                     // ייצוא ל-JSON
validate()                   // ולידציה
clone()                      // שכפול
```

---

### ImageItem
**Path:** `src/js/models/ImageItem.js`

מודל לפריט תמונה.

```javascript
class ImageItem {
    id: number
    type: 'image'
    fileName: string
    base64: string
    top: number
    left: number
    width: number
    height: number
    page: number
    opacity: number
}
```

**Methods:**
```javascript
toJSON()
validate()
clone()
loadFromFile(file)           // טעינה מקובץ
```

---

### Template
**Path:** `src/js/models/Template.js`

מודל לתבנית.

```javascript
class Template {
    template_id: string
    name: string
    description: string
    created_at: string
    page_count: number
    pdf_dimensions: {width, height}
    allItems: Array<TextItem|ImageItem>
}
```

**Methods:**
```javascript
toJSON()
validate()
apply()                      // החלת התבנית
```

---

### PDFDocument
**Path:** `src/js/models/PDFDocument.js`

מודל למסמך PDF.

```javascript
class PDFDocument {
    file: File
    numPages: number
    dimensions: {width, height}
    currentPage: number
}
```

**Methods:**
```javascript
async load(file)
getPage(pageNum)
getTotalPages()
getDimensions()
```

---

## Managers

### FontManager
**Path:** `src/js/managers/FontManager.js`

ניהול פונטים.

**Properties:**
```javascript
fonts: Array               // רשימת פונטים
loaded: boolean            // האם נטענו
```

**Methods:**
```javascript
async loadFonts()          // טעינת פונטים מ-fonts.json
async registerFont(font)   // רישום פונט בודד
getFonts()                 // קבלת כל הפונטים
getFont(id)                // קבלת פונט לפי ID
```

**Example:**
```javascript
const fontManager = new FontManager();
await fontManager.loadFonts();

const fonts = fontManager.getFonts();
// [{ id: 'david', name: 'דיויד', path: '...' }, ...]
```

---

### FileManager
**Path:** `src/js/managers/FileManager.js`

ניהול קבצים.

**Methods:**
```javascript
async uploadFile(file)
async downloadFile(filename)
async deleteFile(type, filename)
validateFile(file)
formatFileSize(bytes)
```

**Example:**
```javascript
const fileManager = new FileManager();

if (fileManager.validateFile(file)) {
    await fileManager.uploadFile(file);
}
```

---

### ItemsManager
**Path:** `src/js/managers/ItemsManager.js`

ניהול פריטים (טקסטים ותמונות).

**Properties:**
```javascript
items: Array               // כל הפריטים
selectedItemId: number     // פריט נבחר
nextId: number             // ID הבא
```

**Methods:**
```javascript
addItem(itemData)          // הוספת פריט
updateItem(id, updates)    // עדכון פריט
removeItem(id)             // מחיקת פריט
getItems()                 // קבלת כל הפריטים
getItemsByPage(pageNum)    // פריטים לפי עמוד
selectItem(id)             // בחירת פריט
reorderItems(fromId, toId) // סידור מחדש
```

**Example:**
```javascript
const itemsManager = new ItemsManager(eventBus);

const textItem = itemsManager.addItem({
    type: 'text',
    text: 'שלום',
    font: 'david',
    size: 48
});

itemsManager.updateItem(textItem.id, { size: 60 });
```

---

### TemplateManager
**Path:** `src/js/managers/TemplateManager.js`

ניהול תבניות.

**Methods:**
```javascript
async saveTemplate(name, description, items, pdfFile)
async loadTemplate(templateId)
async getTemplates()
async deleteTemplate(templateId)
applyTemplate(template)
```

**Example:**
```javascript
const templateManager = new TemplateManager();

await templateManager.saveTemplate(
    'תעודת פטירה',
    'תבנית לתעודת פטירה',
    allItems,
    pdfFile
);

const templates = await templateManager.getTemplates();
```

---

## UI Components

### FileUploader
**Path:** `src/js/components/FileUploader.js`

קומפוננטה להעלאת קבצים.

**Properties:**
```javascript
element: HTMLElement       // אלמנט ההעלאה
selectedFile: File         // קובץ נבחר
```

**Methods:**
```javascript
init()                     // אתחול
handleClick()              // טיפול בלחיצה
handleDragOver(e)          // גרירה מעל
handleDrop(e)              // שחרור קובץ
handleFileChange(e)        // שינוי קובץ
validateFile(file)         // ולידציה
```

**Events Emitted:**
- `file:selected`
- `file:error`

**Example:**
```javascript
const uploader = new FileUploader('#uploadArea', eventBus);
uploader.init();
```

---

### PDFPreview
**Path:** `src/js/components/PDFPreview.js`

תצוגה מקדימה של PDF.

**Properties:**
```javascript
canvas: HTMLCanvasElement
ctx: CanvasRenderingContext2D
pdfDoc: PDFDocument
currentPage: number
scale: number
```

**Methods:**
```javascript
async loadPDF(file)
async renderPage(pageNum)
nextPage()
prevPage()
zoomIn()
zoomOut()
setScale(scale)
```

**Example:**
```javascript
const preview = new PDFPreview('#pdfCanvas', eventBus);
await preview.loadPDF(file);
preview.zoomIn();
```

---

### ItemsList
**Path:** `src/js/components/ItemsList.js`

רשימת פריטים עם drag & drop.

**Methods:**
```javascript
render()                   // רינדור הרשימה
renderItem(item)           // רינדור פריט בודד
handleDragStart(e)         // התחלת גרירה
handleDrop(e)              // שחרור
toggleCollapse(itemId)     // פתיחה/סגירה
updateLayerNumbers()       // עדכון מספרי שכבות
```

**Example:**
```javascript
const itemsList = new ItemsList('#textsList', itemsManager);
itemsList.render();
```

---

### TextItemForm
**Path:** `src/js/components/TextItemForm.js`

טופס עריכת טקסט.

**Fields:**
- תוכן הטקסט
- פונט
- גודל
- צבע
- מיקום (top, right)
- עמוד
- יישור

**Methods:**
```javascript
render(item)
handleChange(field, value)
validate()
```

---

### ImageItemForm
**Path:** `src/js/components/ImageItemForm.js`

טופס עריכת תמונה.

**Fields:**
- רוחב
- גובה
- מיקום (top, left)
- עמוד
- שקיפות

**Methods:**
```javascript
render(item)
handleChange(field, value)
validate()
```

---

### Modal
**Path:** `src/js/components/Modal.js`

מודל גנרי.

**Methods:**
```javascript
show()                     // הצגה
hide()                     // הסתרה
setTitle(title)            // עדכון כותרת
setContent(html)           // עדכון תוכן
onConfirm(callback)        // אישור
onCancel(callback)         // ביטול
```

**Example:**
```javascript
const modal = new Modal('#saveTemplateModal');
modal.setTitle('שמירת תבנית');
modal.show();

modal.onConfirm(async () => {
    await saveTemplate();
    modal.hide();
});
```

---

### Toast
**Path:** `src/js/components/Toast.js`

הודעות קופצות.

**Methods:**
```javascript
success(message, duration)
error(message, duration)
info(message, duration)
warning(message, duration)
```

**Example:**
```javascript
Toast.success('התבנית נשמרה בהצלחה!', 3000);
Toast.error('שגיאה בעיבוד הקובץ');
```

---

## Canvas

### CanvasRenderer
**Path:** `src/js/canvas/CanvasRenderer.js`

רינדור פריטים על Canvas.

**Methods:**
```javascript
renderText(item, viewport)
renderImage(item, viewport)
renderSelectionBox(item)
renderResizeHandles(item)
clear()
```

**Example:**
```javascript
const renderer = new CanvasRenderer(canvas, ctx);
renderer.renderText(textItem, viewport);
renderer.renderSelectionBox(selectedItem);
```

---

### CanvasInteraction
**Path:** `src/js/canvas/CanvasInteraction.js`

אינטראקציות על Canvas (גרירה, שינוי גודל).

**Methods:**
```javascript
handleMouseDown(e)
handleMouseMove(e)
handleMouseUp(e)
findItemAtPosition(x, y)
findResizeHandle(x, y, item)
dragItem(item, deltaX, deltaY)
resizeItem(item, handle, deltaX, deltaY)
```

**Events Emitted:**
- `item:drag:start`
- `item:drag`
- `item:drag:end`
- `item:resize:start`
- `item:resize`
- `item:resize:end`

---

### LayerManager
**Path:** `src/js/canvas/LayerManager.js`

ניהול שכבות (z-index).

**Methods:**
```javascript
moveToFront(itemId)
moveToBack(itemId)
moveUp(itemId)
moveDown(itemId)
getLayerOrder()
setLayerOrder(order)
```

---

## Utils

### DOMHelpers
**Path:** `src/js/utils/DOMHelpers.js`

עוזרים ל-DOM.

```javascript
$(selector)                // querySelector wrapper
$$(selector)               // querySelectorAll wrapper
createElement(tag, props)  // יצירת אלמנט
addClass(el, className)
removeClass(el, className)
toggleClass(el, className)
on(el, event, handler)     // addEventListener wrapper
off(el, event, handler)
```

---

### Validators
**Path:** `src/js/utils/Validators.js`

ולידציות.

```javascript
isValidPDF(file)
isValidImage(file)
isValidTemplateName(name)
isValidColor(color)
isValidNumber(value, min, max)
isValidPosition(value)
```

---

### Formatters
**Path:** `src/js/utils/Formatters.js`

פורמטים.

```javascript
formatFileSize(bytes)
formatDate(date)
formatNumber(num)
formatColor(color)
```

---

### DragAndDrop
**Path:** `src/js/utils/DragAndDrop.js`

מערכת drag & drop.

```javascript
makeDraggable(element, options)
makeDroppable(element, options)
```

---

# Backend Components

## Backend Core

### Config
**Path:** `src/php/core/Config.php`

הגדרות מערכת (ראה [config/config.php](../config/config.php)).

---

### Response
**Path:** `src/php/core/Response.php`

טיפול בתשובות JSON.

```php
class Response {
    static success($data, $message = '')
    static error($error, $code = 400)
    static json($data)
}
```

**Example:**
```php
Response::success([
    'output_file' => $filename
], 'הקובץ עובד בהצלחה');

Response::error('הקובץ חייב להיות PDF', 400);
```

---

### ErrorHandler
**Path:** `src/php/core/ErrorHandler.php`

טיפול בשגיאות.

```php
class ErrorHandler {
    static register()
    static handleException($e)
    static handleError($errno, $errstr, $errfile, $errline)
    static log($message, $level)
}
```

---

## Backend Models

### PDFProcessor
**Path:** `src/php/models/PDFProcessor.php`

עיבוד PDF.

```php
class PDFProcessor {
    process($inputFile, $outputFile, $items)
    callPython($args)
    validate($file)
}
```

---

### Template
**Path:** `src/php/models/Template.php`

מודל תבנית.

```php
class Template {
    save($data, $pdfFile)
    load($templateId)
    delete($templateId)
    getAll()
    validate($data)
}
```

---

### File
**Path:** `src/php/models/File.php`

ניהול קבצים.

```php
class File {
    save($uploadedFile, $dir)
    delete($path)
    exists($path)
    getMetadata($path)
    cleanOld($dir, $maxAge)
}
```

---

## Services

### PDFService
**Path:** `src/php/services/PDFService.php`

שירות עיבוד PDF.

```php
class PDFService {
    process($file, $items)
    callPythonProcessor($inputPath, $outputPath, $dataPath)
    parseOutput($output)
}
```

---

### TemplateService
**Path:** `src/php/services/TemplateService.php`

שירות תבניות.

```php
class TemplateService {
    save($templateData, $pdfFile)
    get($templateId = null)
    delete($templateId)
    validateName($name)
}
```

---

### FileService
**Path:** `src/php/services/FileService.php`

שירות קבצים.

```php
class FileService {
    upload($file, $dir)
    download($filename, $dir)
    delete($filename, $dir)
    cleanOld($dir, $maxAge)
    generateUniqueId()
}
```

---

### ValidationService
**Path:** `src/php/services/ValidationService.php`

שירות ולידציות.

```php
class ValidationService {
    validatePDFFile($file)
    validateFilename($filename)
    validateTemplateId($templateId)
    validateTemplateName($name)
    validateItems($items)
}
```

---

## API Endpoints

ראה [API.md](./API.md) לתיעוד מלא.

---

## 🎯 Component Lifecycle

### Frontend Component Lifecycle

```
1. Constructor
   └─> Initialize properties

2. init()
   └─> Setup event listeners
   └─> Bind DOM elements

3. render()
   └─> Update DOM

4. destroy()
   └─> Cleanup
   └─> Remove event listeners
```

### Backend Service Lifecycle

```
1. Constructor
   └─> Initialize dependencies

2. Method Call
   └─> Validate input
   └─> Execute logic
   └─> Return response

3. Exception Handling
   └─> Catch errors
   └─> Log
   └─> Return error response
```

---

## 📚 Related Documentation

- [API.md](./API.md) - API documentation
- [ARCHITECTURE.md](./ARCHITECTURE.md) - System architecture
- [DEVELOPMENT.md](./DEVELOPMENT.md) - Development guide

---

Last Updated: Phase 1 - December 2025
