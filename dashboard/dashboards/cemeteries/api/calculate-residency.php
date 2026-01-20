<?php
/*
 * API לחישוב תושבות בזמן אמת
 *
 * לוגיקה:
 * 1. בדיקת סוג זיהוי:
 *    - דרכון (2) או אלמוני (3) = תמיד חו"ל (3)
 *    - ת.ז. (1) או תינוק (4) = ממשיך לבדיקות הבאות
 *
 * 2. בדיקת מדינה:
 *    - אם למדינה יש הגדרת תושבות = תושב חוץ לעיר (2)
 *
 * 3. בדיקת עיר:
 *    - אם לעיר יש הגדרת תושבות = תושב העיר (1)
 *    - אחרת נשאר תושב חוץ לעיר (2)
 *
 * 4. ברירת מחדל (ללא הגדרות) = חו"ל (3)
 *
 * תושבות:
 * 1 = תושב העיר (ירושלים והסביבה)
 * 2 = תושב חוץ לעיר
 * 3 = תושב חו"ל
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config.php';

try {
    $typeId = isset($_GET['typeId']) ? (int)$_GET['typeId'] : 1;
    $countryId = $_GET['countryId'] ?? null;
    $cityId = $_GET['cityId'] ?? null;

    // ברירת מחדל: חו"ל
    $residency = 3;
    $reason = 'ברירת מחדל';

    // שלב 1: בדיקת סוג זיהוי
    // דרכון (2) או אלמוני (3) = תמיד חו"ל
    if ($typeId == 2 || $typeId == 3) {
        echo json_encode([
            'success' => true,
            'residency' => 3,
            'label' => 'תושב חו״ל',
            'reason' => 'סוג זיהוי דרכון/אלמוני - תמיד חו"ל'
        ]);
        exit;
    }

    // שלב 2+3: סוג זיהוי ת.ז. (1) או תינוק (4) - בדיקה לפי מדינה ועיר
    if ($countryId || $cityId) {
        $conn = getDBConnection();

        // שלב 3: בדיקת עיר קודם (ספציפי יותר)
        if ($cityId) {
            $stmt = $conn->prepare("
                SELECT residencyType
                FROM residency_settings
                WHERE cityId = :cityId AND isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['cityId' => $cityId]);
            $cityResult = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cityResult) {
                // לעיר יש הגדרת תושבות - השתמש בערך מהטבלה
                $residency = (int)$cityResult['residencyType'];
                $reason = 'הגדרת תושבות לפי עיר';
            }
        }

        // שלב 2: בדיקת מדינה (רק אם לא נמצא לעיר)
        if ($residency == 3 && $countryId) {
            $stmt = $conn->prepare("
                SELECT residencyType
                FROM residency_settings
                WHERE countryId = :countryId AND (cityId IS NULL OR cityId = '') AND isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['countryId' => $countryId]);
            $countryResult = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($countryResult) {
                // למדינה יש הגדרת תושבות - השתמש בערך מהטבלה
                $residency = (int)$countryResult['residencyType'];
                $reason = 'הגדרת תושבות לפי מדינה';
            }
        }
        // אם אין הגדרות = חו"ל (3)
    }

    // תרגום לתווית
    $labels = [
        1 => 'תושב העיר',
        2 => 'תושב חוץ לעיר',
        3 => 'תושב חו״ל'
    ];
    $residencyLabel = $labels[$residency] ?? 'תושב חו״ל';

    echo json_encode([
        'success' => true,
        'residency' => $residency,
        'label' => $residencyLabel,
        'reason' => $reason
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
