<?php
/*
 * API לחישוב תושבות בזמן אמת
 *
 * לוגיקה:
 * - סוג זיהוי דרכון (2) או אלמוני (3) = תמיד חו"ל (3)
 * - סוג זיהוי ת.ז. (1) או תינוק (4) = לפי טבלת תושבות
 *
 * תושבות:
 * 1 = ירושלים והסביבה
 * 2 = תושב חוץ (ישראל)
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
    $residencyLabel = 'תושב חו״ל';

    // סוג זיהוי דרכון (2) או אלמוני (3) = תמיד חו"ל
    if ($typeId == 2 || $typeId == 3) {
        echo json_encode([
            'success' => true,
            'residency' => 3,
            'label' => 'תושב חו״ל',
            'reason' => 'סוג זיהוי דרכון/אלמוני - תמיד חו"ל'
        ]);
        exit;
    }

    // סוג זיהוי ת.ז. (1) או תינוק (4) = לפי טבלת תושבות
    if ($countryId || $cityId) {
        $conn = getDBConnection();

        // חפש בטבלת הגדרות תושבות
        // קודם לפי עיר ספציפית, אחר כך לפי מדינה
        if ($cityId) {
            $stmt = $conn->prepare("
                SELECT residencyType
                FROM residency_settings
                WHERE cityId = :cityId AND isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['cityId' => $cityId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $residency = (int)$result['residencyType'];
            }
        }

        // אם לא נמצא לפי עיר, חפש לפי מדינה
        if ($residency == 3 && $countryId) {
            $stmt = $conn->prepare("
                SELECT residencyType
                FROM residency_settings
                WHERE countryId = :countryId AND cityId IS NULL AND isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['countryId' => $countryId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $residency = (int)$result['residencyType'];
            }
        }

        // אם עדיין לא נמצא - בדוק אם ישראל
        if ($residency == 3 && $countryId) {
            $stmt = $conn->prepare("
                SELECT countryNameHe FROM countries WHERE unicId = :countryId
            ");
            $stmt->execute(['countryId' => $countryId]);
            $country = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($country && $country['countryNameHe'] == 'ישראל') {
                // ישראל ללא הגדרה ספציפית = תושב חוץ
                $residency = 2;
            }
        }
    }

    // תרגום לתווית
    $labels = [
        1 => 'ירושלים והסביבה',
        2 => 'תושב חוץ',
        3 => 'תושב חו״ל'
    ];
    $residencyLabel = $labels[$residency] ?? 'תושב חו״ל';

    echo json_encode([
        'success' => true,
        'residency' => $residency,
        'label' => $residencyLabel
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
