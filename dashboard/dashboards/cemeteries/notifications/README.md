# Notifications System - מערכת התראות

מערכת התראות מלאה המאפשרת שליחת Push Notifications למשתמשים עם תמיכה בתזמון, אישורים ביומטריים, ומעקב אחר משלוחים.

## מבנה התיקיות

```
notifications/
│
├── core/                           # תשתית ליבה
│   ├── NotificationService.php     # שירות ראשי - Singleton לשליחת התראות
│   ├── NotificationLogger.php      # לוגר אירועים - מעקב אחר מחזור חיי ההתראות
│   ├── WebPush.php                 # מימוש פרוטוקול Web Push (RFC 8030)
│   ├── UserAgentParser.php         # פרסור User-Agent לזיהוי מכשירים
│   └── push-log.php                # לוגינג לקבצים (debug)
│
├── api/                            # נקודות קצה API
│   ├── notifications-api.php       # CRUD להתראות מתוזמנות
│   ├── approval-api.php            # טיפול באישורים/דחיות
│   ├── logs-api.php                # גישה ללוגים
│   ├── test-api.php                # הרצת בדיקות אוטומטיות
│   └── feedback-api.php            # פידבק לשולח
│
├── tests/                          # מערכת בדיקות
│   ├── NotificationTester.php      # מנוע בדיקות אוטומטי
│   ├── NotificationsApiTest.php    # בדיקות API
│   ├── NotificationsValidationTest.php
│   └── test-runner.php             # מריץ בדיקות CLI
│
├── cron/                           # משימות מתוזמנות
│   └── notification-retry.php      # ניסיון חוזר למשלוחים שנכשלו
│
├── css/                            # עיצוב
│   ├── notifications.css
│   └── logs.css
│
├── js/                             # JavaScript
│   └── notifications.js
│
└── *.php                           # דפי UI
    ├── index.php                   # ניהול התראות (ראשי)
    ├── logs.php                    # מרכז לוגים
    ├── approve.php                 # דף אישור להתראות
    ├── notification-view.php       # צפייה בהתראה בודדת
    ├── test-automation.php         # ממשק בדיקות
    ├── admin-cleanup.php           # ניקוי נתונים (אדמין)
    ├── my-feedbacks.php            # פידבקים שהתקבלו
    ├── approval-history.php        # היסטוריית אישורים
    └── entity-approve.php          # אישור ישויות (לא חלק מהתראות)
```

## רכיבי הליבה

### NotificationService.php
**המיקום:** `core/NotificationService.php`
**תפקיד:** שירות ראשי לשליחת התראות - Singleton

```php
$service = NotificationService::getInstance();

// שליחת התראת מידע
$service->sendInfo([1, 2, 3], 'כותרת', 'תוכן', '/url');

// שליחת התראת אישור
$service->sendApproval([5], 'בקשת אישור', 'אנא אשר', [
    'notifySender' => true,
    'expiresIn' => 24  // שעות
]);
```

### NotificationLogger.php
**המיקום:** `core/NotificationLogger.php`
**תפקיד:** רישום כל אירועי ההתראות - Singleton

```php
$logger = NotificationLogger::getInstance();
$logger->logCreated($notificationId, $userId, $notification);
$logger->logDelivered($notificationId, $userId);
$logger->logApproved($notificationId, $userId, $biometric);
```

**סוגי אירועים:**
- `created` - התראה נוצרה
- `send_attempt` - ניסיון שליחה
- `delivered` - נמסר בהצלחה
- `failed` - שליחה נכשלה
- `viewed` - נצפה
- `approved` / `rejected` - אושר/נדחה
- `expired` - פג תוקף

### WebPush.php
**המיקום:** `core/WebPush.php`
**תפקיד:** מימוש פרוטוקול Web Push לפי RFC 8030

- הצפנת Payload עם ECDH + AES-128-GCM
- אימות VAPID (Voluntary Application Server Identification)
- תמיכה ב-FCM, Mozilla Push, Apple

### UserAgentParser.php
**המיקום:** `core/UserAgentParser.php`
**תפקיד:** פרסור User-Agent לזיהוי מכשיר/מערכת/דפדפן

```php
$info = UserAgentParser::parse($userAgent);
// Returns: ['device' => 'iPhone', 'os' => 'iOS', 'browser' => 'Safari']

$deviceType = UserAgentParser::detectDeviceType();
// Returns: 'mobile' or 'desktop'
```

## מערכת ניסיון חוזר (Retry)

**קובץ:** `cron/notification-retry.php`
**הרצה:** Cron כל 5 דקות

```bash
*/5 * * * * php /path/to/notification-retry.php
```

**Exponential Backoff:**
| ניסיון | זמן המתנה |
|--------|-----------|
| 1      | 1 דקה     |
| 2      | 5 דקות    |
| 3      | 15 דקות   |
| 4      | שעה       |
| 5      | 4 שעות    |

## מערכת בדיקות

**קבצים:**
- `tests/NotificationTester.php` - מנוע הבדיקות
- `api/test-api.php` - API להרצת בדיקות
- `test-automation.php` - ממשק UI

**בדיקות זמינות:**
1. `basic_send` - שליחה בסיסית
2. `multiple_users` - שליחה למספר משתמשים
3. `approval` - התראת אישור
4. `scheduled` - התראה מתוזמנת
5. `delivery_logging` - רישום לוגים

## טבלאות Database

```sql
scheduled_notifications    -- התראות מתוזמנות (מקור)
push_notifications        -- רשומות משלוח לכל משתמש
notification_approvals    -- סטטוס אישורים
notification_deliveries   -- מעקב משלוחים (retry)
notification_logs         -- לוג אירועים מפורט
notification_test_runs    -- היסטוריית בדיקות
push_subscriptions        -- מנויי Push של משתמשים
```

## תרשים זרימה

```
                    ┌─────────────────┐
                    │   Admin/User    │
                    │  creates alert  │
                    └────────┬────────┘
                             │
                             ▼
              ┌──────────────────────────┐
              │   notifications-api.php   │
              │       (create)            │
              └────────────┬─────────────┘
                           │
                           ▼
              ┌──────────────────────────┐
              │  NotificationService     │
              │   .sendToUsers()         │
              └────────────┬─────────────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
         ┌────────┐  ┌────────┐  ┌────────┐
         │ User 1 │  │ User 2 │  │ User N │
         └───┬────┘  └───┬────┘  └───┬────┘
             │           │           │
             └───────────┼───────────┘
                         │
                         ▼
              ┌──────────────────────────┐
              │      WebPush.php         │
              │   (encrypt & send)       │
              └────────────┬─────────────┘
                           │
            ┌──────────────┼──────────────┐
            │              │              │
            ▼              ▼              ▼
      ┌──────────┐  ┌──────────┐  ┌──────────┐
      │ Success  │  │  Failed  │  │ No Sub   │
      │ 201      │  │ 4xx/5xx  │  │          │
      └────┬─────┘  └────┬─────┘  └────┬─────┘
           │             │             │
           ▼             ▼             ▼
      ┌─────────────────────────────────────┐
      │       NotificationLogger            │
      │  (log delivered/failed/no_sub)      │
      └─────────────────────────────────────┘
                         │
                         ▼ (if failed)
              ┌──────────────────────────┐
              │  notification-retry.php  │
              │   (cron every 5 min)     │
              └──────────────────────────┘
```

## שימוש

### שליחת התראה פשוטה
```php
require_once 'core/NotificationService.php';

$service = NotificationService::getInstance();
$result = $service->sendInfo(
    [1, 2, 3],           // user IDs
    'כותרת',             // title
    'תוכן ההודעה',        // body
    '/dashboard/page'    // URL (optional)
);
```

### שליחת התראת אישור
```php
$result = $service->sendApproval(
    [5],                          // user ID
    'אישור נדרש',                  // title
    'אנא אשר פעולה זו',            // body
    [
        'notifySender' => true,   // שלח פידבק לשולח
        'expiresIn' => 24,        // תוקף בשעות
        'approvalMessage' => 'הודעה מותאמת'
    ]
);
```

### קריאת לוגים
```php
require_once 'core/NotificationLogger.php';

$logger = NotificationLogger::getInstance();

// לוגים לפי התראה
$logs = $logger->getLogs(['notification_id' => 123]);

// סטטיסטיקות
$stats = $logger->getStats('24h');

// Timeline מלא
$timeline = $logger->getTimeline(123);
```

## קובץ NOTES

**entity-approve.php** - זהו קובץ אישור ישויות (כמו מבקרים) ולא חלק ממערכת ההתראות.
הוא משתמש בתשתית ההתראות אך הלוגיקה העסקית שלו שונה.

---

**גרסה:** 2.0.0
**עדכון אחרון:** 2026-02-10
