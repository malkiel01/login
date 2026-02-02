# תוכנית השלמת מערכת האישורים וההרשאות

**תאריך:** 2026-02-02
**גרסה:** 1.0.0
**סטטוס:** ממתין לאישור

---

## רקע

מערכת האישורים מאפשרת:
- **מורשי חתימה** (Authorizers) - יכולים לבצע פעולות ישירות או לאשר פעולות של אחרים
- **עובדים עם הרשאות** - יכולים לבצע ללא אישור (לפי הגדרות)
- **עובדים רגילים** - יכולים רק לשלוח בקשות לאישור

### זרימת העבודה
1. עובד מבקש פעולה (יצירה/עריכה/מחיקה)
2. אם דרוש אישור - הרשומה נשמרת ב-`pending_entity_operations`
3. נשלחות התראות למורשי החתימה
4. כשמספר האישורים מגיע לנדרש - הפעולה מתבצעת
5. אם נדחה/בוטל/פג תוקף - הסטטוסים חוזרים למצב קודם

---

## סיכום פערים שזוהו

| # | פער | עדיפות | מורכבות |
|---|-----|--------|---------|
| 1 | כיסוי ישויות חלקי | בינונית | גבוהה |
| 2 | בדיקת כפילויות חלשה ל-EDIT/DELETE | גבוהה | נמוכה |
| 3 | אין בדיקת הרשאה ברמת משתמש | בינונית | בינונית |
| 4 | פערים בלוגיקת rollback | גבוהה | נמוכה |
| 5 | אכיפת מאשרים חלקית | בינונית | בינונית |
| 6 | אין שמירת היסטוריית אישורים | נמוכה | נמוכה |
| 7 | נתוני פעולה כפולים (JSON) | נמוכה | גבוהה |
| 8 | אין נעילה לפעולות מקבילות | גבוהה | בינונית |
| 9 | ניקוי התראות חלקי | בינונית | נמוכה |
| 10 | טעות בלוגיקת סטטוס לקוח | גבוהה | נמוכה |

---

## שלב 1: תיקון פערים בעדיפות גבוהה

### 1.1 תיקון לוגיקת rollback ללקוחות (פער #4, #10)

**קבצים:** `api/services/EntityApprovalService.php`

**בעיה:**
- ב-rollback של קבורות, לא בודקים סטטוס קיים של לקוח לפני עדכון
- חסרה בדיקה אם הלקוח כבר בסטטוס "רכש" (2) או "נפטר" (3)

**פתרון:**
```php
// ב-rollbackRelatedEntityStatuses, עבור burials create:
if (!empty($operationData['clientId'])) {
    // בדוק אם הלקוח כבר נפטר (סטטוס 3) - לא לשנות!
    $stmt = $this->pdo->prepare("SELECT statusCustomer FROM customers WHERE unicId = ?");
    $stmt->execute([$operationData['clientId']]);
    $currentStatus = $stmt->fetchColumn();

    // רק אם הסטטוס הנוכחי הוא 5 (ממתין לקבורה) - נחזיר לסטטוס קודם
    if ($currentStatus == 5) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE clientId = ? AND isActive = 1");
        $stmt->execute([$operationData['clientId']]);
        $hasPurchase = $stmt->fetchColumn() > 0;

        $newStatus = $hasPurchase ? 2 : 1;
        $this->pdo->prepare("UPDATE customers SET statusCustomer = ? WHERE unicId = ?")
                  ->execute([$newStatus, $operationData['clientId']]);
    }
}
```

**משימות:**
- [ ] עדכן `rollbackRelatedEntityStatuses()` לבדוק סטטוס נוכחי
- [ ] הוסף בדיקה שלא לשנות סטטוס "נפטר" (3)
- [ ] כתוב טסטים

---

### 1.2 הוספת נעילה אופטימיסטית (פער #8)

**קבצים:** `api/purchases-api.php`, `api/burials-api.php`

**בעיה:** Race condition - שני משתמשים יכולים ליצור pending על אותו קבר באותו רגע

**פתרון:**
```php
// שימוש ב-SELECT ... FOR UPDATE
$pdo->beginTransaction();
try {
    // נעילה על הקבר
    $stmt = $pdo->prepare("SELECT unicId FROM graves WHERE unicId = ? FOR UPDATE");
    $stmt->execute([$graveId]);

    // בדיקת כפילויות (כעת עם נעילה)
    $stmt = $pdo->prepare("
        SELECT id FROM pending_entity_operations
        WHERE entity_type = 'purchases'
          AND action = 'create'
          AND status = 'pending'
          AND JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.graveId')) = ?
    ");
    $stmt->execute([$graveId]);
    // ...

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

**משימות:**
- [ ] עטוף את בדיקת הכפילויות ויצירת pending ב-transaction
- [ ] הוסף `FOR UPDATE` על הקבר/לקוח
- [ ] בדוק שה-transaction לא מפריע לזרימה הרגילה

---

### 1.3 שיפור בדיקת כפילויות ל-EDIT/DELETE (פער #2)

**קבצים:** `api/purchases-api.php`, `api/burials-api.php`

**בעיה:**
- EDIT לא בודק אם יש pending CREATE על אותו רשומה
- DELETE לא בודק אם יש pending EDIT על אותו רשומה

**פתרון:**
```php
// ב-EDIT - בדוק גם אם יש pending CREATE או DELETE
$stmt = $pdo->prepare("
    SELECT id, action FROM pending_entity_operations
    WHERE entity_type = 'purchases'
      AND entity_id = ?
      AND status = 'pending'
");
$stmt->execute([$id]);
$existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existingPending) {
    $actionLabel = [
        'create' => 'יצירה',
        'edit' => 'עריכה',
        'delete' => 'מחיקה'
    ][$existingPending['action']] ?? $existingPending['action'];
    throw new Exception("כבר קיימת בקשה ממתינה ל{$actionLabel} של רכישה זו");
}

// ב-DELETE - בדוק גם אם יש pending CREATE או EDIT
// (אותו קוד)
```

**משימות:**
- [ ] עדכן EDIT לבדוק כל סוגי הפעולות הממתינות
- [ ] עדכן DELETE לבדוק כל סוגי הפעולות הממתינות
- [ ] הוסף הודעות שגיאה מותאמות לסוג הפעולה

---

## שלב 2: שיפורים בעדיפות בינונית

### 2.1 שילוב payments במערכת האישורים (פער #1 - חלקי)

**קבצים:** `api/payments-api.php`, `config/cemetery-hierarchy-config.php`

**בעיה:** תשלומים לא עוברים דרך מערכת האישורים

**פתרון:**

1. **הוסף לקונפיג:**
```php
'payment' => [
    // ... קונפיג קיים ...
    'approval' => [
        'create' => true,
        'edit' => true,
        'delete' => true
    ]
]
```

2. **עדכן payments-api.php:**
```php
// ב-CREATE
$approvalService = EntityApprovalService::getInstance($pdo);
$currentUserId = getCurrentUserId();
$isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'payments', 'create');

if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'payments', 'create')) {
    $result = $approvalService->createPendingOperation([
        'entity_type' => 'payments',
        'action' => 'create',
        'operation_data' => $data,
        'requested_by' => $currentUserId
    ]);
    // ...
}
```

**משימות:**
- [ ] הוסף approval config ל-payment
- [ ] עדכן payments-api.php עם בדיקות אישור
- [ ] הוסף בדיקות כפילויות
- [ ] עדכן entity_approval_rules עם ברירת מחדל

---

### 2.2 שיפור ניקוי התראות (פער #9)

**קבצים:** `api/entity-approval-api.php`

**בעיה:** מסמנים push_notifications כנקראו, אבל לא מבטלים scheduled_notifications עתידיים

**פתרון:**
```php
function cancelScheduledNotifications(PDO $pdo, int $pendingId): void {
    $urlPattern = "%entity-approve.php?id={$pendingId}%";

    // בטל scheduled_notifications שטרם נשלחו
    $stmt = $pdo->prepare("
        UPDATE scheduled_notifications
        SET status = 'cancelled'
        WHERE url LIKE ?
          AND status = 'pending'
          AND scheduled_time > NOW()
    ");
    $stmt->execute([$urlPattern]);

    $cancelled = $stmt->rowCount();
    if ($cancelled > 0) {
        error_log("[EntityApprovalAPI] Cancelled $cancelled scheduled notification(s) for pending $pendingId");
    }
}
```

**משימות:**
- [ ] הוסף פונקציה `cancelScheduledNotifications()`
- [ ] קרא אותה ב-approve, reject, cancel
- [ ] בדוק שהטבלה scheduled_notifications תומכת בסטטוס cancelled

---

### 2.3 הוספת בדיקת isAuthorizer מלאה (פער #3)

**קבצים:** `api/purchases-api.php`, `api/burials-api.php`

**בעיה:** הבדיקה קיימת אבל לא מלאה - אם המשתמש הוא מורשה חתימה, הפעולה צריכה להיחשב כ"אושרה על ידו"

**פתרון:**
```php
// כשמורשה חתימה מבצע פעולה ישירות, שמור לוג
if ($isAuthorizer) {
    // ביצוע ישיר
    // ... הקוד הקיים ...

    // שמור לוג שהפעולה בוצעה ע"י מורשה חתימה
    $stmt = $pdo->prepare("
        INSERT INTO entity_action_log
        (entity_type, entity_id, action, performed_by, is_authorizer, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute(['purchases', $newId, 'create', $currentUserId]);
}
```

**משימות:**
- [ ] צור טבלת `entity_action_log` (אופציונלי)
- [ ] הוסף לוגינג כשמורשה מבצע ישירות
- [ ] עדכן UI להציג "בוצע ע"י מורשה חתימה"

---

### 2.4 אכיפת מאשרים חובה (פער #5)

**קבצים:** `api/services/EntityApprovalService.php`

**בעיה:** שדה `is_mandatory` קיים אבל לא נאכף בבדיקת השלמה

**פתרון:**
```php
// ב-checkAndCompleteOperation
private function checkAndCompleteOperation(int $pendingId): array
{
    $pending = $this->getPendingById($pendingId);

    // ספור אישורים
    $stmt = $this->pdo->prepare("
        SELECT
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN status = 'approved' AND is_mandatory = 1 THEN 1 END) as mandatory_approved,
            COUNT(CASE WHEN is_mandatory = 1 THEN 1 END) as mandatory_total
        FROM pending_operation_approvals
        WHERE pending_id = ?
    ");
    $stmt->execute([$pendingId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // בדוק שכל החובה אישרו
    if ($counts['mandatory_approved'] < $counts['mandatory_total']) {
        return ['complete' => false, 'waiting_for_mandatory' => true];
    }

    // בדוק שהגענו למספר הנדרש
    if ($counts['approved_count'] < $pending['required_approvals']) {
        return ['complete' => false];
    }

    // השלם את הפעולה
    // ...
}
```

**משימות:**
- [ ] עדכן `checkAndCompleteOperation` לבדוק mandatory
- [ ] הוסף הודעה למשתמש כמה אישורים חסרים
- [ ] עדכן UI להציג מאשרים חובה

---

## שלב 3: שיפורים בעדיפות נמוכה

### 3.1 שמירת היסטוריית אישורים (פער #6)

**בעיה:** אין קישור בין הרשומה שנוצרה לבין ה-pending שאושר

**פתרון:**
```sql
-- הוסף עמודה לטבלאות הישויות
ALTER TABLE purchases ADD COLUMN approved_pending_id INT NULL;
ALTER TABLE burials ADD COLUMN approved_pending_id INT NULL;
```

```php
// ב-executeOperation, אחרי יצירת הרשומה:
$stmt = $pdo->prepare("UPDATE purchases SET approved_pending_id = ? WHERE id = ?");
$stmt->execute([$pendingId, $newId]);
```

**משימות:**
- [ ] הוסף עמודות לטבלאות
- [ ] עדכן executeOperation לשמור את הקישור
- [ ] הוסף API לצפייה בהיסטוריה

---

### 3.2 שילוב שאר הישויות (פער #1 - המשך)

**ישויות לשילוב:**
- [ ] graves - קברים
- [ ] blocks - גושים
- [ ] plots - חלקות
- [ ] areaGraves - אחוזות קבר
- [ ] cemeteries - בתי עלמין
- [ ] customers - לקוחות (השלמה)
- [ ] countries - מדינות
- [ ] cities - ערים
- [ ] residencies - תושבויות

**לכל ישות:**
1. הוסף approval config
2. עדכן API עם בדיקות אישור
3. הוסף בדיקות כפילויות
4. עדכן entity_approval_rules

---

### 3.3 אופטימיזציית JSON (פער #7)

**בעיה:** שאילתות JSON_EXTRACT איטיות על נפח גדול

**פתרון אפשרי:**
```sql
-- הוסף עמודות אינדקס
ALTER TABLE pending_entity_operations
ADD COLUMN grave_id VARCHAR(50) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.graveId'))) STORED,
ADD COLUMN client_id VARCHAR(50) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(operation_data, '$.clientId'))) STORED;

-- צור אינדקסים
CREATE INDEX idx_pending_grave ON pending_entity_operations(grave_id);
CREATE INDEX idx_pending_client ON pending_entity_operations(client_id);
```

**משימות:**
- [ ] בדוק אם יש בעיית ביצועים אמיתית
- [ ] אם כן - הוסף עמודות generated
- [ ] עדכן שאילתות להשתמש בעמודות החדשות

---

## סיכום משימות לפי סדר ביצוע

### אצווה 1 (עדיפות גבוהה):
1. [ ] תיקון rollback ללקוחות
2. [ ] הוספת נעילה אופטימיסטית
3. [ ] שיפור בדיקת כפילויות

### אצווה 2 (עדיפות בינונית):
4. [ ] שילוב payments
5. [ ] שיפור ניקוי התראות
6. [ ] אכיפת מאשרים חובה

### אצווה 3 (עדיפות נמוכה):
7. [ ] שמירת היסטוריית אישורים
8. [ ] שילוב שאר הישויות
9. [ ] אופטימיזציית JSON

---

## הערות נוספות

### אבטחה
- כל הפעולות חייבות לעבור דרך `api-auth.php`
- בדיקות הרשאה ברמת module
- לא לחשוף מידע רגיש בהודעות שגיאה

### ביצועים
- שאילתות JSON_EXTRACT - לעקוב אחרי ביצועים
- אינדקסים על `pending_entity_operations`
- Cache לקונפיגים

### תאימות לאחור
- לא לשבור API קיים
- להוסיף פיצ'רים בצורה הדרגתית
- לשמור על backward compatibility

---

**נוצר אוטומטית על ידי Claude Code**
**תאריך עדכון אחרון:** 2026-02-02
