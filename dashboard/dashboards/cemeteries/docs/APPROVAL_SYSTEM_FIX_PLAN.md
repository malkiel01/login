# תוכנית עבודה - תיקון מערכת האישורים

## סטטוס כללי
- **תאריך יצירה:** 2026-02-02
- **סטטוס:** ✅ הושלם
- **תאריך השלמה:** 2026-02-02

---

## 📋 רשימת הפערים לתיקון

| # | פער | חומרה | סטטוס |
|---|-----|-------|-------|
| 1 | מניעת כפילויות - purchases | קריטי | ✅ הושלם |
| 2 | מניעת כפילויות - burials | קריטי | ✅ הושלם |
| 3 | סטטוס "ממתין" לקבר | גבוה | ✅ הושלם |
| 4 | סטטוס "ממתין" ללקוח | גבוה | ✅ הושלם |
| 5 | ביטול התראות אחרי אישור/דחייה | בינוני | ✅ הושלם |
| 6 | Rollback סטטוס אחרי דחייה/ביטול/פקיעה | בינוני | ✅ הושלם |

---

## 🔧 שלב 1: מניעת כפילויות ב-purchases-api.php

### 1.1 הוספת בדיקת כפילות לפעולת CREATE
**קובץ:** `api/purchases-api.php`
**מיקום:** לפני הקוד של `createPendingOperation` (בערך שורה 365)

**קוד להוסיף:**
```php
// === בדיקת כפילויות לפני יצירת pending ===
// בדיקה 1: האם יש pending על הקבר הזה?
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'purchases'
      AND action = 'create'
      AND status = 'pending'
      AND JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.graveId')) = ?
");
$stmt->execute([$data['graveId']]);
$existingGravePending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingGravePending) {
    throw new Exception('כבר קיימת בקשה ממתינה לרכישה על קבר זה (מזהה: ' . $existingGravePending['id'] . ')');
}

// בדיקה 2: האם יש pending על הלקוח הזה (רכישה או קבורה)?
$stmt = $pdo->prepare("
    SELECT id, entity_type FROM pending_entity_operations
    WHERE entity_type IN ('purchases', 'burials')
      AND action = 'create'
      AND status = 'pending'
      AND JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.clientId')) = ?
");
$stmt->execute([$data['clientId']]);
$existingClientPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingClientPending) {
    $entityLabel = $existingClientPending['entity_type'] === 'purchases' ? 'רכישה' : 'קבורה';
    throw new Exception('כבר קיימת בקשה ממתינה ל' . $entityLabel . ' עבור לקוח זה (מזהה: ' . $existingClientPending['id'] . ')');
}
// === סוף בדיקת כפילויות ===
```

### 1.2 הוספת בדיקת כפילות לפעולת UPDATE
**מיקום:** לפני הקוד של `createPendingOperation` ב-case 'update' (בערך שורה 489)

**קוד להוסיף:**
```php
// בדיקה אם כבר קיימת בקשת עריכה ממתינה עבור רכישה זו
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'purchases'
      AND action = 'edit'
      AND entity_id = ?
      AND status = 'pending'
");
$stmt->execute([$id]);
$existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingPending) {
    throw new Exception('כבר קיימת בקשה ממתינה לעריכת רכישה זו (מזהה: ' . $existingPending['id'] . ')');
}
```

### 1.3 הוספת בדיקת כפילות לפעולת DELETE
**מיקום:** לפני הקוד של `createPendingOperation` ב-case 'delete' (בערך שורה 546)

**קוד להוסיף:**
```php
// בדיקה אם כבר קיימת בקשת מחיקה ממתינה עבור רכישה זו
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'purchases'
      AND action = 'delete'
      AND entity_id = ?
      AND status = 'pending'
");
$stmt->execute([$id]);
$existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingPending) {
    throw new Exception('כבר קיימת בקשה ממתינה למחיקת רכישה זו (מזהה: ' . $existingPending['id'] . ')');
}
```

**סטטוס שלב 1:** ⏳ ממתין

---

## 🔧 שלב 2: מניעת כפילויות ב-burials-api.php

### 2.1 הוספת בדיקת כפילות לפעולת CREATE
**קובץ:** `api/burials-api.php`
**מיקום:** לפני הקוד של `createPendingOperation` (בערך שורה 286)

**קוד להוסיף:**
```php
// === בדיקת כפילויות לפני יצירת pending ===
// בדיקה 1: האם יש pending על הקבר הזה?
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'burials'
      AND action = 'create'
      AND status = 'pending'
      AND JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.graveId')) = ?
");
$stmt->execute([$data['graveId']]);
$existingGravePending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingGravePending) {
    throw new Exception('כבר קיימת בקשה ממתינה לקבורה על קבר זה (מזהה: ' . $existingGravePending['id'] . ')');
}

// בדיקה 2: האם יש pending על הלקוח הזה?
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'burials'
      AND action = 'create'
      AND status = 'pending'
      AND JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.clientId')) = ?
");
$stmt->execute([$data['clientId']]);
$existingClientPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingClientPending) {
    throw new Exception('כבר קיימת בקשה ממתינה לקבורה עבור לקוח זה (מזהה: ' . $existingClientPending['id'] . ')');
}
// === סוף בדיקת כפילויות ===
```

### 2.2 הוספת בדיקת כפילות לפעולת UPDATE
**מיקום:** לפני הקוד של `createPendingOperation` ב-case 'update' (בערך שורה 399)

**קוד להוסיף:**
```php
// בדיקה אם כבר קיימת בקשת עריכה ממתינה עבור קבורה זו
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'burials'
      AND action = 'edit'
      AND entity_id = ?
      AND status = 'pending'
");
$stmt->execute([$id]);
$existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingPending) {
    throw new Exception('כבר קיימת בקשה ממתינה לעריכת קבורה זו (מזהה: ' . $existingPending['id'] . ')');
}
```

### 2.3 הוספת בדיקת כפילות לפעולת DELETE
**מיקום:** לפני הקוד של `createPendingOperation` ב-case 'delete' (בערך שורה 453)

**קוד להוסיף:**
```php
// בדיקה אם כבר קיימת בקשת מחיקה ממתינה עבור קבורה זו
$stmt = $pdo->prepare("
    SELECT id FROM pending_entity_operations
    WHERE entity_type = 'burials'
      AND action = 'delete'
      AND entity_id = ?
      AND status = 'pending'
");
$stmt->execute([$id]);
$existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingPending) {
    throw new Exception('כבר קיימת בקשה ממתינה למחיקת קבורה זו (מזהה: ' . $existingPending['id'] . ')');
}
```

**סטטוס שלב 2:** ⏳ ממתין

---

## 🔧 שלב 3: הוספת סטטוס "ממתין" לקבר

### 3.1 הגדרת ערכי הסטטוס החדשים
**ערכים חדשים ל-graveStatus:**
- 4 = ממתין לאישור רכישה
- 5 = ממתין לאישור קבורה

### 3.2 עדכון EntityApprovalService - createPendingOperation
**קובץ:** `api/services/EntityApprovalService.php`
**מיקום:** בתוך הפונקציה `createPendingOperation`, אחרי ה-INSERT (בערך שורה 155)

**קוד להוסיף:**
```php
// === עדכון סטטוס ישויות קשורות ל"ממתין" ===
if ($entityType === 'purchases' && $action === 'create' && !empty($operationData['graveId'])) {
    $this->pdo->prepare("UPDATE graves SET graveStatus = 4 WHERE unicId = ? AND graveStatus = 1")
              ->execute([$operationData['graveId']]);
}

if ($entityType === 'burials' && $action === 'create' && !empty($operationData['graveId'])) {
    $this->pdo->prepare("UPDATE graves SET graveStatus = 5 WHERE unicId = ? AND graveStatus IN (1, 2)")
              ->execute([$operationData['graveId']]);
}
// === סוף עדכון סטטוס ישויות קשורות ===
```

### 3.3 עדכון config.php - הוספת תוויות לסטטוסים החדשים
**קובץ:** `config.php`
**הוסף תחת הגדרות קיימות:**

```php
// סטטוסי קבר
if (!defined('GRAVE_STATUS_LABELS')) {
    define('GRAVE_STATUS_LABELS', [
        1 => 'פנוי',
        2 => 'נרכש',
        3 => 'תפוס (קבור)',
        4 => 'ממתין לאישור רכישה',
        5 => 'ממתין לאישור קבורה'
    ]);
}
```

### 3.4 עדכון ה-UI להצגת הסטטוסים החדשים
**קבצים לעדכון:**
- `js/entities-framework/graves-entity.js` - להוסיף צבעים/אייקונים
- CSS רלוונטי

**סטטוס שלב 3:** ⏳ ממתין

---

## 🔧 שלב 4: הוספת סטטוס "ממתין" ללקוח

### 4.1 הגדרת ערכי הסטטוס החדשים
**ערכים חדשים ל-statusCustomer:**
- 4 = ממתין לאישור רכישה
- 5 = ממתין לאישור קבורה

### 4.2 עדכון EntityApprovalService - createPendingOperation
**מיקום:** באותו מקום כמו שלב 3.2

**קוד להוסיף (להוסיף אחרי הקוד של הקבר):**
```php
if ($entityType === 'purchases' && $action === 'create' && !empty($operationData['clientId'])) {
    $this->pdo->prepare("UPDATE customers SET statusCustomer = 4 WHERE unicId = ? AND statusCustomer = 1")
              ->execute([$operationData['clientId']]);
}

if ($entityType === 'burials' && $action === 'create' && !empty($operationData['clientId'])) {
    $this->pdo->prepare("UPDATE customers SET statusCustomer = 5 WHERE unicId = ?")
              ->execute([$operationData['clientId']]);
}
```

### 4.3 עדכון config.php
```php
// סטטוסי לקוח
if (!defined('CUSTOMER_STATUS_LABELS')) {
    define('CUSTOMER_STATUS_LABELS', [
        1 => 'פעיל',
        2 => 'רוכש',
        3 => 'נפטר',
        4 => 'ממתין לאישור רכישה',
        5 => 'ממתין לאישור קבורה'
    ]);
}
```

**סטטוס שלב 4:** ⏳ ממתין

---

## 🔧 שלב 5: ביטול התראות אחרי אישור/דחייה

### 5.1 יצירת פונקציה חדשה ב-entity-approval-api.php
**קובץ:** `api/entity-approval-api.php`
**מיקום:** בסוף הקובץ, אחרי הפונקציה `markEntityApprovalNotificationAsRead`

**קוד להוסיף:**
```php
/**
 * Mark ALL push notifications related to an entity approval as read
 * This is called when the operation is approved, rejected, cancelled, or expired
 * to invalidate notifications for ALL authorizers
 */
function markAllApprovalNotificationsAsRead(PDO $pdo, int $pendingId): void {
    $urlPattern = "%entity-approve.php?id={$pendingId}%";

    $stmt = $pdo->prepare("
        UPDATE push_notifications pn
        JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
        SET pn.is_read = 1
        WHERE pn.is_read = 0
          AND sn.url LIKE ?
    ");
    $stmt->execute([$urlPattern]);

    $updated = $stmt->rowCount();
    if ($updated > 0) {
        error_log("[EntityApprovalAPI] Marked ALL $updated notification(s) as read for pending $pendingId");
    }
}
```

### 5.2 עדכון case 'approve'
**מיקום:** שורה ~210

**שינוי:**
```php
// לפני:
markEntityApprovalNotificationAsRead($pdo, $pendingId, $userId);

// אחרי (להוסיף אחרי):
// אם הפעולה הושלמה - בטל את כל ההתראות
if ($result['complete'] ?? false) {
    markAllApprovalNotificationsAsRead($pdo, $pendingId);
}
```

### 5.3 עדכון case 'reject'
**מיקום:** שורה ~227

**שינוי:**
```php
// לפני:
markEntityApprovalNotificationAsRead($pdo, $pendingId, $userId);

// אחרי (להחליף ב):
markAllApprovalNotificationsAsRead($pdo, $pendingId);
```

### 5.4 עדכון case 'cancel'
**מיקום:** שורה ~240

**להוסיף אחרי ה-cancelOperation:**
```php
// בטל את כל ההתראות
markAllApprovalNotificationsAsRead($pdo, $pendingId);
```

**סטטוס שלב 5:** ⏳ ממתין

---

## 🔧 שלב 6: Rollback סטטוס אחרי דחייה/ביטול/פקיעה

### 6.1 עדכון EntityApprovalService - rejectOperation
**קובץ:** `api/services/EntityApprovalService.php`
**מיקום:** בפונקציה `rejectOperation`, לפני ה-return (בערך שורה 360)

**קוד להוסיף:**
```php
// === Rollback סטטוס ישויות קשורות ===
$operationData = json_decode($pending['operation_data'], true) ?? [];

if ($pending['entity_type'] === 'purchases' && $pending['action'] === 'create') {
    // החזר קבר לפנוי
    if (!empty($operationData['graveId'])) {
        $this->pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = ? AND graveStatus = 4")
                  ->execute([$operationData['graveId']]);
    }
    // החזר לקוח לפעיל
    if (!empty($operationData['clientId'])) {
        $this->pdo->prepare("UPDATE customers SET statusCustomer = 1 WHERE unicId = ? AND statusCustomer = 4")
                  ->execute([$operationData['clientId']]);
    }
}

if ($pending['entity_type'] === 'burials' && $pending['action'] === 'create') {
    // החזר קבר לסטטוס קודם (נרכש או פנוי)
    if (!empty($operationData['graveId'])) {
        // בדוק אם יש רכישה פעילה
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE graveId = ? AND isActive = 1");
        $stmt->execute([$operationData['graveId']]);
        $hasPurchase = $stmt->fetchColumn() > 0;

        $newStatus = $hasPurchase ? 2 : 1;
        $this->pdo->prepare("UPDATE graves SET graveStatus = ? WHERE unicId = ? AND graveStatus = 5")
                  ->execute([$newStatus, $operationData['graveId']]);
    }
    // החזר לקוח לסטטוס קודם
    if (!empty($operationData['clientId'])) {
        // בדוק אם יש רכישה פעילה
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE clientId = ? AND isActive = 1");
        $stmt->execute([$operationData['clientId']]);
        $hasPurchase = $stmt->fetchColumn() > 0;

        $newStatus = $hasPurchase ? 2 : 1;
        $this->pdo->prepare("UPDATE customers SET statusCustomer = ? WHERE unicId = ? AND statusCustomer = 5")
                  ->execute([$newStatus, $operationData['clientId']]);
    }
}
// === סוף Rollback ===
```

### 6.2 עדכון EntityApprovalService - cancelOperation
**מיקום:** בפונקציה `cancelOperation`, לפני ה-return (בערך שורה 390)

**קוד להוסיף:** (אותו קוד כמו ב-6.1)

### 6.3 עדכון EntityApprovalService - processExpiredOperations
**מיקום:** בפונקציה `processExpiredOperations` (בערך שורה 1214)

**שינוי מלא:**
```php
public function processExpiredOperations(): int
{
    // שליפת הפעולות שעומדות לפוג
    $stmt = $this->pdo->query("
        SELECT id, entity_type, action, operation_data
        FROM pending_entity_operations
        WHERE status = 'pending' AND expires_at < NOW()
    ");
    $expiredOps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($expiredOps as $op) {
        // עדכון סטטוס ל-expired
        $this->pdo->prepare("
            UPDATE pending_entity_operations
            SET status = 'expired', completed_at = NOW()
            WHERE id = ?
        ")->execute([$op['id']]);

        // Rollback סטטוס ישויות קשורות
        $operationData = json_decode($op['operation_data'], true) ?? [];

        if ($op['entity_type'] === 'purchases' && $op['action'] === 'create') {
            if (!empty($operationData['graveId'])) {
                $this->pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = ? AND graveStatus = 4")
                          ->execute([$operationData['graveId']]);
            }
            if (!empty($operationData['clientId'])) {
                $this->pdo->prepare("UPDATE customers SET statusCustomer = 1 WHERE unicId = ? AND statusCustomer = 4")
                          ->execute([$operationData['clientId']]);
            }
        }

        if ($op['entity_type'] === 'burials' && $op['action'] === 'create') {
            if (!empty($operationData['graveId'])) {
                $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE graveId = ? AND isActive = 1");
                $stmt2->execute([$operationData['graveId']]);
                $newStatus = $stmt2->fetchColumn() > 0 ? 2 : 1;
                $this->pdo->prepare("UPDATE graves SET graveStatus = ? WHERE unicId = ? AND graveStatus = 5")
                          ->execute([$newStatus, $operationData['graveId']]);
            }
            if (!empty($operationData['clientId'])) {
                $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE clientId = ? AND isActive = 1");
                $stmt2->execute([$operationData['clientId']]);
                $newStatus = $stmt2->fetchColumn() > 0 ? 2 : 1;
                $this->pdo->prepare("UPDATE customers SET statusCustomer = ? WHERE unicId = ? AND statusCustomer = 5")
                          ->execute([$newStatus, $operationData['clientId']]);
            }
        }

        $count++;
    }

    return $count;
}
```

**סטטוס שלב 6:** ⏳ ממתין

---

## 🔧 שלב 7 (בונוס): עדכון UI להצגת סטטוסים

### 7.1 עדכון CSS
**קובץ:** `css/dashboard.css` או קובץ CSS רלוונטי

```css
/* סטטוסי קבר */
.grave-status-4 { background: #fef3c7; color: #92400e; } /* ממתין לרכישה */
.grave-status-5 { background: #fee2e2; color: #991b1b; } /* ממתין לקבורה */

/* סטטוסי לקוח */
.customer-status-4 { background: #fef3c7; color: #92400e; } /* ממתין לרכישה */
.customer-status-5 { background: #fee2e2; color: #991b1b; } /* ממתין לקבורה */
```

### 7.2 עדכון JS
**קובץ:** `js/entities-framework/graves-entity.js`

הוספת הסטטוסים החדשים למיפוי.

**סטטוס שלב 7:** ⏳ ממתין

---

## 📝 סדר ביצוע מומלץ

1. ✅ שלב 1: מניעת כפילויות purchases
2. ✅ שלב 2: מניעת כפילויות burials
3. ✅ שלב 5: ביטול התראות (קל יחסית)
4. ✅ שלב 3+4: סטטוסים ממתינים (יחד)
5. ✅ שלב 6: Rollback
6. ✅ שלב 7: UI

---

## 🧪 בדיקות לאחר כל שלב

### בדיקות שלב 1+2:
- [ ] נסה ליצור 2 בקשות pending לאותו קבר - צפוי: שגיאה
- [ ] נסה ליצור 2 בקשות pending לאותו לקוח - צפוי: שגיאה
- [ ] נסה ליצור בקשת edit כשיש כבר pending edit - צפוי: שגיאה

### בדיקות שלב 3+4:
- [ ] צור pending רכישה - בדוק שהקבר הפך לסטטוס 4
- [ ] צור pending קבורה - בדוק שהקבר הפך לסטטוס 5
- [ ] בדוק שהלקוח קיבל סטטוס מתאים

### בדיקות שלב 5:
- [ ] צור pending, תן למאשר 1 לאשר, בדוק שההתראות של מאשר 2 נעלמו

### בדיקות שלב 6:
- [ ] צור pending רכישה → דחה → בדוק שהקבר חזר לפנוי
- [ ] צור pending קבורה → בטל → בדוק שהקבר חזר לסטטוס קודם
- [ ] המתן לפקיעה → בדוק שהסטטוסים התאפסו

---

## 📌 הערות חשובות

1. **גיבוי לפני כל שינוי** - לעשות commit לפני כל שלב
2. **בדיקה בסביבת פיתוח** - לבדוק לפני deploy
3. **תיעוד** - לעדכן את הקובץ הזה אחרי כל שלב

---

**נוצר על ידי:** Claude Code
**עודכן לאחרונה:** 2026-02-02
