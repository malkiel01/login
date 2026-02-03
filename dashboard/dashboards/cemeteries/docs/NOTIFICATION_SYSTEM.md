# מערכת התראות - סיכום מקיף

## סקירה כללית

מערכת ההתראות מורכבת ממספר רכיבים שעובדים יחד:
1. **ניהול התראות** (Admin) - יצירה ושליחת התראות למשתמשים
2. **התראות המשתמש** (My Notifications) - צפייה בהתראות שהתקבלו
3. **תבניות תצוגה** (Templates) - סוגי תצוגה שונים להתראות
4. **מודלים קופצים** (Modals) - הצגת התראות בפופאפ

---

## 1. קבצי Backend (PHP APIs)

### notifications-api.php
**מיקום:** `/notifications/api/notifications-api.php`

**פעולות זמינות:**
- `get_users` - קבלת רשימת משתמשים לבחירה
- `list` - רשימת כל ההתראות (עם פילטר סטטוס)
- `get_delivery_status` - סטטוס שליחה למשתמשים
- `create` - יצירת התראה חדשה
- `update` - עדכון התראה קיימת
- `cancel` - ביטול התראה ממתינה
- `delete` - מחיקה מההיסטוריה
- `resend_to_user` - שליחה חוזרת למשתמש ספציפי

### my-notifications-api.php
**מיקום:** `/my-notifications/api/my-notifications-api.php`

**פעולות זמינות:**
- `get_unread` - התראות שלא נקראו (עד 50)
- `get_history` - היסטוריית התראות (עם pagination)
- `mark_read` - סימון התראה כנקראה
- `mark_all_read` - סימון כל ההתראות כנקראו

### טבלאות מסד נתונים קשורות
- `scheduled_notifications` - התראות מתוזמנות
- `push_notifications` - התראות שנשלחו למשתמשים
- `notification_approvals` - תגובות לאישורים

---

## 2. סוגי התראות (Types)

### INFO (מידע)
- תצוגת Toast בתחתית המסך
- נסגר אוטומטית אחרי 5 שניות
- לא דורש אישור

### APPROVAL (אישור כללי)
- מסך מלא עם כפתורי אישור/דחייה
- דורש תגובה מהמשתמש
- תמיכה באימות ביומטרי

### ENTITY_APPROVAL (אישור ישות)
- מסך מלא עם פרטי הישות המלאים
- משמש למערכת אישורי Entity (רכישות/קבורות/לקוחות)
- כולל השוואת שינויים (לפעולות עריכה)

---

## 3. קבצי JavaScript

### notification-templates.js
**מיקום:** `/notifications/templates/notification-templates.js`

**תפקיד:** מנהל ראשי של תבניות התראות

**מתודות עיקריות:**
```javascript
NotificationTemplates.show(notification, options)  // הצג התראה לפי סוג
NotificationTemplates.detectType(notification)     // זיהוי סוג התראה
NotificationTemplates.close()                      // סגירת המודאל הפעיל
```

### info-notification.js
**מיקום:** `/notifications/templates/info-notification.js`

**תפקיד:** תצוגת התראות מידע (Toast)

**מאפיינים:**
- מופיע בתחתית המסך
- גובה מקסימלי 50vh
- נסגר אוטומטית (ברירת מחדל: 5 שניות)
- תמיכה בכפתור פעולה מותאם

### approval-notification.js
**מיקום:** `/notifications/templates/approval-notification.js`

**תפקיד:** התראות שדורשות אישור

### entity-approval-notification.js
**מיקום:** `/notifications/templates/entity-approval-notification.js`

**תפקיד:** אישורי ישויות (Entity Approval System)

### notification-modal.js
**מיקום:** `/js/notification-modal.js`

**תפקיד:** מודאל כללי להצגת התראות

**מתודות עיקריות:**
```javascript
NotificationModal.show(data)       // הצגת המודאל
NotificationModal.close()          // סגירה עם סימון כנקרא
NotificationModal.dismiss()        // סגירה ללא סימון
NotificationModal.markAsRead(id)   // סימון כנקרא
```

### load-templates.js
**מיקום:** `/notifications/templates/load-templates.js`

**תפקיד:** טעינת כל תבניות ההתראות בסדר הנכון

---

## 4. זרימת הצגת התראה

### בטעינת דף (כרגע)
1. משתמש נכנס לאפליקציה
2. לא מופעל טעינה אוטומטית של התראות ממתינות
3. משתמש צריך ללכת ל"ההתראות שלי" לראות התראות

### מ-Service Worker (Push Notification)
1. SW מקבל התראת Push
2. שולח הודעה לדף: `type: 'SHOW_NOTIFICATION_MODAL'`
3. `notification-modal.js` מאזין ומציג את המודאל

### מ-URL Parameters
```
?show_notification=123
&notification_title=כותרת
&notification_body=תוכן
&notification_url=/path
&is_approval=1
```

---

## 5. דף ההתראות שלי (my-notifications)

**מיקום:** `/my-notifications/index.php`

### מבנה הדף
- **סקשן התראות חדשות** - התראות שלא נקראו
- **סקשן היסטוריה** - התראות שנקראו (עם Load More)

### יכולות
- צפייה בפרטי התראה (מודאל)
- סימון כנקרא (בודד/כולם)
- פתיחת קישור מההתראה
- תמיכה בהתראות אישור - פתיחת מסך אישור

---

## 6. מבנה נתונים

### התראה (Notification Object)
```javascript
{
    id: number,                    // ID בטבלת push_notifications
    scheduled_notification_id: number,  // ID בטבלת scheduled_notifications
    title: string,                 // כותרת
    body: string,                  // תוכן
    url: string,                   // קישור (אופציונלי)
    notification_type: 'info'|'warning'|'urgent',
    requires_approval: boolean,    // דורש אישור?
    approval_status: 'pending'|'approved'|'rejected'|'expired',
    is_read: boolean,
    is_delivered: boolean,
    created_at: datetime,
    delivered_at: datetime
}
```

---

## 7. הוספת התראות בכניסה (TODO)

### מה חסר
כרגע אין מנגנון שמציג התראות אוטומטית בכניסה למערכת.

### מיקום המומלץ להוספה
**קובץ:** `/dashboard/dashboards/cemeteries/index.php` או קובץ JS נפרד

### לוגיקה מוצעת
```javascript
// בטעינת הדף (אחרי DOMContentLoaded)
async function showUnreadNotificationsOnLogin() {
    // 1. קריאה ל-API לקבלת התראות שלא נקראו
    const response = await fetch('/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php?action=get_unread');
    const data = await response.json();

    if (!data.success || !data.notifications.length) return;

    // 2. הצגת ההתראות אחת אחרי השנייה
    for (const notification of data.notifications) {
        await showNotificationAndWait(notification);
    }
}

async function showNotificationAndWait(notification) {
    return new Promise((resolve) => {
        // הצג את ההתראה
        NotificationTemplates.show(notification, {
            autoDismiss: false,  // לא לסגור אוטומטית
        });

        // הגדר callback לסגירה
        NotificationTemplates.onClose(() => {
            resolve();
        });
    });
}
```

### שיקולים
1. **מניעת הצפה** - לא להציג יותר מ-5 התראות ברצף
2. **עדיפות** - להציג קודם urgent, אחר כך warning, ואז info
3. **אישורים קודם** - התראות שדורשות אישור צריכות להופיע ראשונות
4. **זכירת מצב** - לא להציג שוב התראות שכבר נצפו (sessionStorage)

---

## 8. קבצי CSS קשורים

- `/notifications/css/notifications.css` - סגנונות ניהול התראות
- `/my-notifications/css/my-notifications.css` - סגנונות דף ההתראות שלי
- סגנונות מוטמעים בתבניות ה-JS

---

## 9. תלויות

### JavaScript
- `PopupManager` - לפופאפים
- `PushSubscriptionManager` - לרישום Push
- `ApprovalModal` - למסך אישורים
- `BiometricAuth` - לאימות ביומטרי

### Service Worker
- `/push/service-worker.js` - קבלת התראות Push
- `/push/listener.js` - האזנה להודעות

---

## 10. נקודות לשיפור

1. **התראות בכניסה** - הוספת הצגה אוטומטית של התראות ממתינות
2. **עדכון מונה בזמן אמת** - Badge בתפריט שמתעדכן
3. **קיבוץ התראות** - הצגת מספר התראות דומות כקבוצה
4. **Snooze** - אפשרות לדחות התראה לזמן מאוחר יותר
