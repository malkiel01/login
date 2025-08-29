# 📊 Dashboard Management System

מערכת דשבורד מודולרית וגמישה לניהול משתמשים ואפליקציות.

## 🚀 התקנה

### מבנה התיקיות
```
dashboard/
├── index.php                 # קובץ ראשי
├── .htaccess                # הגדרות Apache
├── README.md                # תיעוד
│
├── api/                     # API מרכזי
│   └── index.php           # נקודת כניסה ל-API
│
├── assets/                  # קבצים סטטיים
│   ├── css/
│   │   └── dashboard.css   # עיצוב
│   └── js/
│       └── dashboard.js    # לוגיקה
│
└── includes/               # קבצי PHP
    └── functions.php      # פונקציות עזר
```

### דרישות מערכת
- PHP 7.4+
- MySQL 5.7+
- Apache עם mod_rewrite
- חיבור SSL (מומלץ)

### הגדרה ראשונית
1. העתק את תיקיית `dashboard` לשורש הפרויקט
2. וודא שקובץ `config.php` קיים בתיקייה הראשית
3. וודא שטבלת `users` קיימת במסד הנתונים

## 🔐 אבטחה

### הגנות מובנות
- ✅ בדיקת אימות בכל בקשה
- ✅ הגנת CSRF
- ✅ סניטציה של קלט
- ✅ הגנה על תיקיות רגישות
- ✅ Headers אבטחה
- ✅ Session timeout

### הרשאות
המערכת תומכת במערכת הרשאות גמישה:
```php
if (checkPermission('admin')) {
    // פעולות מנהל
}
```

## 📡 API Documentation

### Base URL
```
/dashboard/api/
```

### Authentication
כל הבקשות דורשות session פעיל (cookie-based).

### Endpoints

#### 👤 Users

##### Get User
```http
GET /api/user/{id}
```
Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "auth_type": "local"
  }
}
```

##### Update User
```http
PUT /api/user/{id}/update
Content-Type: application/json

{
  "name": "New Name",
  "email": "new@email.com"
}
```

##### Create User (Admin Only)
```http
POST /api/user/create
Content-Type: application/json

{
  "username": "newuser",
  "email": "new@example.com",
  "password": "secure123",
  "name": "New User"
}
```

##### Delete User (Admin Only)
```http
DELETE /api/user/{id}
```

#### 📊 Statistics

##### Get Stats
```http
GET /api/stats
```
Response:
```json
{
  "success": true,
  "data": {
    "total_users": 150,
    "active_users": 120,
    "google_users": 45,
    "today_logins": 23
  }
}
```

#### 📝 Activity Log

##### Get Activities
```http
GET /api/activity?limit=20
```

##### Log Activity
```http
POST /api/activity
Content-Type: application/json

{
  "action": "user_login",
  "details": "Login from mobile device"
}
```

#### 🔧 System

##### Get System Info (Admin Only)
```http
GET /api/system
```

### Error Responses
```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

HTTP Status Codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Server Error

## 🎨 התאמה אישית

### שינוי צבעים (CSS Variables)
```css
:root {
    --primary-color: #667eea;
    --primary-dark: #764ba2;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
}
```

### הוספת כרטיס סטטיסטיקה
```javascript
// dashboard.js
const newStat = {
    icon: 'fas fa-chart-line',
    color: 'blue',
    value: '1,234',
    label: 'סטטיסטיקה חדשה'
};
```

### הוספת Endpoint חדש
```php
// api/index.php
case 'custom':
    handleCustomEndpoint($method, $input);
    break;
```

## 🔌 אינטגרציות

### Google OAuth
המערכת תומכת בהתחברות עם Google OAuth:
- משתמשי Google נשמרים עם `auth_type = 'google'`
- תמונות פרופיל נשמרות אוטומטית

### PWA Support
המערכת מוכנה לעבודה כ-Progressive Web App:
- manifest.json
- Service Worker support
- Offline capabilities

## 🛠️ טיפים לפיתוח

### הוספת טבלת activity_logs
```sql
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Debug Mode
```php
// config.php
define('DEBUG_MODE', true);

// בדשבורד
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
```

### Cache Control
```php
// הוספת cache headers
header('Cache-Control: private, max-age=3600');
header('ETag: "' . md5($content) . '"');
```

## 📱 Responsive Design

הדשבורד מותאם לכל המכשירים:
- 📱 Mobile: < 480px
- 📱 Tablet: < 768px
- 💻 Desktop: < 1024px
- 🖥️ Wide: > 1024px

## 🚦 Performance

### טעינה מהירה
- CSS/JS minification
- GZIP compression
- Browser caching
- Lazy loading

### אופטימיזציה
- מינימום queries למסד
- Session caching
- CDN לספריות חיצוניות

## 🐛 Troubleshooting

### בעיות נפוצות

#### Session לא נשמר
```php
// בדוק את הגדרות PHP
session.save_path = "/tmp"
session.gc_maxlifetime = 1440
```

#### API מחזיר 404
```apache
# וודא ש-mod_rewrite פעיל
a2enmod rewrite
service apache2 restart
```

#### CORS Issues
```php
// הוסף ל-API
header('Access-Control-Allow-Origin: https://yourdomain.com');
```

## 📄 רישיון

המערכת מסופקת AS-IS לשימוש חופשי.

## 👨‍💻 תמיכה

לשאלות ובעיות, פתח Issue ב-GitHub או צור קשר עם צוות הפיתוח.

---

**גרסה:** 1.0.0  
**עדכון אחרון:** 2024  
**נבנה עם ❤️ עבור ניהול קל ויעיל**