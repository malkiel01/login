# 📡 API Documentation - PDF Editor System

תיעוד מלא של כל נקודות הקצה (Endpoints) במערכת.

---

## 🔗 Base URL

```
/dashboard/dashboards/cemeteries/files/
```

---

## 📋 Endpoints Overview

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/process.php` | POST | עיבוד קובץ PDF עם טקסטים ותמונות | ✅ Active |
| `/download.php` | GET | הורדת קובץ PDF מעובד | ✅ Active |
| `/delete.php` | POST | מחיקת קובץ (מקור או מעובד) | ✅ Active |
| `/save_template.php` | POST | שמירת תבנית חדשה | ✅ Active |
| `/get_templates.php` | GET | קבלת רשימת תבניות או תבנית בודדת | ✅ Active |
| `/delete_template.php` | POST | מחיקת תבנית | ✅ Active |

---

## 1️⃣ Process PDF

עיבוד קובץ PDF והוספת טקסטים ותמונות.

### Endpoint
```
POST /process.php
```

### Request

**Content-Type:** `multipart/form-data`

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `pdf` | File | ✅ Yes | קובץ PDF להעלאה |
| `texts` | JSON String | ⚠️ Optional | מערך של פריטי טקסט (deprecated - use allItems) |
| `images` | JSON String | ⚠️ Optional | מערך של פריטי תמונה (deprecated - use allItems) |
| `allItems` | JSON String | ✅ Yes | מערך של כל הפריטים (טקסטים + תמונות) בסדר השכבות |

**allItems Format:**
```json
[
  {
    "id": 1,
    "type": "text",
    "text": "ניסיון",
    "font": "david",
    "size": 48,
    "color": "#808080",
    "top": 300,
    "right": 200,
    "page": 1,
    "align": "right"
  },
  {
    "id": 2,
    "type": "image",
    "base64": "data:image/png;base64,iVBORw0KGg...",
    "top": 100,
    "left": 100,
    "width": 200,
    "height": 200,
    "page": 1,
    "opacity": 1.0
  }
]
```

### Response

**Success (200):**
```json
{
  "success": true,
  "pages": 5,
  "width": 595.28,
  "height": 841.89,
  "output_file": "pdf_abc123_output.pdf"
}
```

**Error (400/500):**
```json
{
  "success": false,
  "error": "הקובץ חייב להיות PDF"
}
```

### Example Usage

```javascript
const formData = new FormData();
formData.append('pdf', pdfFile);
formData.append('allItems', JSON.stringify(allItems));

const response = await fetch('process.php', {
  method: 'POST',
  body: formData
});

const result = await response.json();
```

---

## 2️⃣ Download PDF

הורדת קובץ PDF מעובד.

### Endpoint
```
GET /download.php?file={filename}
```

### Request

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | String | ✅ Yes | שם הקובץ המעובד |

**Validation:**
- שם הקובץ חייב להתאים לתבנית: `pdf_[a-f0-9.]+_output\.pdf`

### Response

**Success (200):**
- Content-Type: `application/pdf`
- Content-Disposition: `attachment; filename="processed_YYYY-MM-DD_HH-mm-ss.pdf"`
- הקובץ מוחזר כ-binary stream

**Error (400):**
```
שם קובץ לא תקין
```

**Error (404):**
```
הקובץ לא נמצא
```

### Example Usage

```javascript
window.location.href = `download.php?file=${encodeURIComponent(filename)}`;
```

---

## 3️⃣ Delete File

מחיקת קובץ מהשרת (מקור או מעובד).

### Endpoint
```
POST /delete.php
```

### Request

**Content-Type:** `application/json`

**Body:**
```json
{
  "type": "source" | "processed",
  "file": "filename.pdf"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | String | ✅ Yes | סוג הקובץ: "source" או "processed" |
| `file` | String | ✅ Yes | שם הקובץ |

### Response

**Success (200):**
```json
{
  "success": true,
  "message": "הקובץ נמחק בהצלחה"
}
```

**Error (400/404):**
```json
{
  "success": false,
  "error": "הקובץ לא נמצא"
}
```

### Example Usage

```javascript
const response = await fetch('delete.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    type: 'processed',
    file: processedFileName
  })
});
```

---

## 4️⃣ Save Template

שמירת תבנית חדשה.

### Endpoint
```
POST /save_template.php
```

### Request

**Content-Type:** `multipart/form-data`

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `template_data` | JSON String | ✅ Yes | נתוני התבנית |
| `pdf_file` | File | ✅ Yes | קובץ PDF מקורי |

**template_data Format:**
```json
{
  "name": "תעודת פטירה",
  "description": "תבנית לתעודת פטירה עם שדות מותאמים",
  "original_filename": "certificate.pdf",
  "pdf_dimensions": {
    "width": 595.28,
    "height": 841.89
  },
  "page_count": 1,
  "allItems": [
    { "id": 1, "type": "text", ... },
    { "id": 2, "type": "image", ... }
  ]
}
```

### Response

**Success (200):**
```json
{
  "success": true,
  "template_id": "template_abc123",
  "message": "התבנית נשמרה בהצלחה"
}
```

**Error (400):**
```json
{
  "success": false,
  "error": "שם תבנית זה כבר קיים"
}
```

---

## 5️⃣ Get Templates

קבלת רשימת תבניות או תבנית בודדת.

### Endpoint
```
GET /get_templates.php
GET /get_templates.php?id={template_id}
```

### Request

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | String | ⚠️ Optional | מזהה תבנית (אם לא מצוין - מחזיר רשימה) |

### Response

**All Templates (200):**
```json
{
  "success": true,
  "templates": [
    {
      "template_id": "template_abc123",
      "name": "תעודת פטירה",
      "description": "תבנית לתעודת פטירה",
      "created_at": "2025-12-26T10:30:00",
      "page_count": 1,
      "field_count": 5,
      "text_count": 4,
      "image_count": 1
    }
  ]
}
```

**Single Template (200):**
```json
{
  "success": true,
  "template": {
    "template_id": "template_abc123",
    "name": "תעודת פטירה",
    "description": "...",
    "allItems": [...],
    "pdf_dimensions": {...},
    ...
  }
}
```

---

## 6️⃣ Delete Template

מחיקת תבנית.

### Endpoint
```
POST /delete_template.php
```

### Request

**Content-Type:** `application/json`

**Body:**
```json
{
  "template_id": "template_abc123"
}
```

### Response

**Success (200):**
```json
{
  "success": true,
  "message": "התבנית נמחקה בהצלחה"
}
```

**Error (400/404):**
```json
{
  "success": false,
  "error": "תבנית לא נמצאה"
}
```

---

## 🔒 Security Notes

### File Upload Security
- ✅ MIME type validation (`application/pdf` only)
- ✅ Filename sanitization
- ✅ Unique filenames using `uniqid()`
- ✅ Path traversal prevention
- ✅ File age cleanup (auto-delete files older than 1 hour)

### Input Validation
- ✅ Regex validation for filenames and template IDs
- ✅ basename() to prevent path traversal
- ✅ realpath() verification
- ✅ JSON validation

### Command Injection Prevention
- ✅ escapeshellarg() for all shell arguments
- ✅ No user input in shell commands directly

---

## 📊 Error Codes

| Code | Meaning | Common Causes |
|------|---------|---------------|
| 200 | Success | הכל תקין |
| 400 | Bad Request | נתונים חסרים או לא תקינים |
| 404 | Not Found | קובץ או תבנית לא נמצאו |
| 500 | Server Error | שגיאה בעיבוד, בעיה ב-Python |

---

## 🧪 Testing

### Test with cURL

**Process PDF:**
```bash
curl -X POST \
  -F "pdf=@test.pdf" \
  -F 'allItems=[{"id":1,"type":"text","text":"Test","font":"david","size":48,"color":"#000000","top":300,"right":200,"page":1,"align":"right"}]' \
  http://localhost/files/process.php
```

**Get Templates:**
```bash
curl http://localhost/files/get_templates.php
```

**Delete Template:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"template_id":"template_abc123"}' \
  http://localhost/files/delete_template.php
```

---

## 🔄 Changelog

### Version 1.0.0 (Phase 1 - Current)
- ✅ Initial API documentation
- ✅ All legacy endpoints documented
- ⚠️ Using old file structure (will change in Phase 2)

### Planned Changes (Phase 2)
- 🔜 Unified API endpoint: `/src/php/api/`
- 🔜 RESTful structure
- 🔜 Better error handling
- 🔜 API versioning

---

## 📞 Support

לבעיות או שאלות, פנה למפתח הראשי.
