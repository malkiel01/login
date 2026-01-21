<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/grave-form.php
 * Version: 1.0.3
 * Updated: 2026-01-21
 * Author: Malkiel
 * Change Summary:
 * - v1.0.3: הוספת הצגת נתיב היררכיה מלא כולל אחוזת קבר (ההורה הישיר)
 * - v1.0.2: הוספת error handling משופר
 * - v1.0.1: תיקון SQL והוספת error handling
 * - v1.0.0: יצירת טופס הוספה/עריכה של קבר בודד
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// Error handler for catching all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("grave-form.php Error [$errno]: $errstr in $errfile:$errline");
    return false;
});

try {
    require_once __DIR__ . '/FormBuilder.php';
    require_once __DIR__ . '/FormUtils.php';
    require_once dirname(__DIR__) . '/config.php';
} catch (Throwable $e) {
    error_log("grave-form.php require error: " . $e->getMessage());
    die('<div class="error-message">שגיאה בטעינת קבצי מערכת: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null; // areaGraveId

$grave = null;
$areaGraves = [];
$hierarchyPath = null; // נתיב היררכיה מלא

try {
    $conn = getDBConnection();

    // טען קבר קיים אם בעריכה
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT * FROM graves
            WHERE unicId = ? AND isActive = 1
        ");
        $stmt->execute([$itemId]);
        $grave = $stmt->fetch(PDO::FETCH_ASSOC);

        // אם נמצא קבר, שמור את ה-areaGraveId שלו
        if ($grave && !$parentId) {
            $parentId = $grave['areaGraveId'];
        }
    }

    // טען אחוזות קבר לבחירה
    if ($parentId) {
        // טען רק את האחוזה הספציפית - שאילתה פשוטה יותר
        $stmt = $conn->prepare("
            SELECT unicId, areaGraveNameHe
            FROM areaGraves
            WHERE unicId = ? AND isActive = 1
        ");
        $stmt->execute([$parentId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $areaGraves[$row['unicId']] = $row['areaGraveNameHe'];
        }

        // טען נתיב היררכיה מלא כולל ההורה הישיר (אחוזת קבר)
        $stmt = $conn->prepare("
            SELECT
                ag.areaGraveNameHe,
                l.lineNameHe,
                p.plotNameHe,
                b.blockNameHe,
                c.cemeteryNameHe
            FROM areaGraves ag
            LEFT JOIN `lines` l ON ag.lineId = l.unicId
            LEFT JOIN plots p ON l.plotId = p.unicId
            LEFT JOIN blocks b ON p.blockId = b.unicId
            LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
            WHERE ag.unicId = ?
        ");
        $stmt->execute([$parentId]);
        $hierarchyPath = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // טען את כל האחוזות - שאילתה פשוטה יותר
        $stmt = $conn->query("
            SELECT unicId, areaGraveNameHe
            FROM areaGraves
            WHERE isActive = 1
            ORDER BY areaGraveNameHe
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $areaGraves[$row['unicId']] = $row['areaGraveNameHe'];
        }
    }

} catch (Throwable $e) {
    error_log("grave-form.php DB error: " . $e->getMessage());
    die('<div class="error-message">שגיאה בטעינת נתונים: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

try {
    // יצירת FormBuilder
    $formBuilder = new FormBuilder('grave', $itemId, $parentId);

    // הצגת נתיב היררכיה מלא (אם קיים parentId)
    if ($hierarchyPath) {
        $breadcrumbParts = [];
        if (!empty($hierarchyPath['cemeteryNameHe'])) {
            $breadcrumbParts[] = htmlspecialchars($hierarchyPath['cemeteryNameHe']);
        }
        if (!empty($hierarchyPath['blockNameHe'])) {
            $breadcrumbParts[] = htmlspecialchars($hierarchyPath['blockNameHe']);
        }
        if (!empty($hierarchyPath['plotNameHe'])) {
            $breadcrumbParts[] = htmlspecialchars($hierarchyPath['plotNameHe']);
        }
        if (!empty($hierarchyPath['lineNameHe'])) {
            $breadcrumbParts[] = htmlspecialchars($hierarchyPath['lineNameHe']);
        }
        if (!empty($hierarchyPath['areaGraveNameHe'])) {
            $breadcrumbParts[] = '<strong>' . htmlspecialchars($hierarchyPath['areaGraveNameHe']) . '</strong>';
        }

        if (!empty($breadcrumbParts)) {
            $breadcrumbHtml = '<div class="hierarchy-breadcrumb" style="background: #f8f9fa; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; border-right: 4px solid #007bff; font-size: 14px;">'
                . '<span style="color: #6c757d; margin-left: 8px;">📍 מיקום:</span>'
                . implode(' ← ', $breadcrumbParts)
                . '</div>';
            $formBuilder->addCustomHTML($breadcrumbHtml);
        }
    }

    // שדה אחוזת קבר
    $formBuilder->addField('areaGraveId', 'אחוזת קבר', 'select', [
        'required' => true,
        'options' => array_merge(
            ['' => '-- בחר אחוזת קבר --'],
            $areaGraves
        ),
        'value' => $parentId ?? ($grave['areaGraveId'] ?? ''),
        'readonly' => !empty($parentId)
    ]);

    // שם קבר
    $formBuilder->addField('graveNameHe', 'שם קבר', 'text', [
        'required' => true,
        'placeholder' => 'הזן שם קבר',
        'value' => $grave['graveNameHe'] ?? ''
    ]);

    // סוג חלקה
    $formBuilder->addField('plotType', 'סוג חלקה', 'select', [
        'required' => true,
        'options' => [
            '' => '-- בחר סוג --',
            1 => 'פטורה',
            2 => 'חריגה',
            3 => 'סגורה'
        ],
        'value' => $grave['plotType'] ?? ''
    ]);

    // סטטוס קבר - לצפייה בלבד (מנוהל אוטומטית)
    // ביצירה: פנוי, ברכישה: נרכש, בקבורה: קבור
    if ($grave) {
        // במצב עריכה - הצג את הסטטוס כשדה לקריאה בלבד
        $statusLabels = [
            1 => 'פנוי',
            2 => 'נרכש',
            3 => 'קבור',
            4 => 'שמור'
        ];
        $currentStatus = $grave['graveStatus'] ?? 1;
        $formBuilder->addField('graveStatusDisplay', 'סטטוס קבר', 'text', [
            'value' => $statusLabels[$currentStatus] ?? 'לא ידוע',
            'readonly' => true,
            'disabled' => true,
            'class' => 'status-display'
        ]);
    }
    // ביצירה - לא מציגים את השדה, הסטטוס יהיה 1 (פנוי) אוטומטית

    // מיקום בשורה
    $formBuilder->addField('graveLocation', 'מיקום בשורה', 'select', [
        'options' => [
            '' => '-- בחר מיקום --',
            1 => 'עליון',
            2 => 'תחתון',
            3 => 'אמצעי'
        ],
        'value' => $grave['graveLocation'] ?? ''
    ]);

    // קבר קטן
    $formBuilder->addField('isSmallGrave', 'קבר קטן', 'select', [
        'options' => [
            0 => 'לא',
            1 => 'כן'
        ],
        'value' => $grave['isSmallGrave'] ?? 0
    ]);

    // עלות בנייה
    $formBuilder->addField('constructionCost', 'עלות בנייה', 'number', [
        'placeholder' => 'הזן עלות',
        'value' => $grave['constructionCost'] ?? '',
        'min' => 0,
        'step' => 0.01
    ]);

    // הערות
    $formBuilder->addField('comments', 'הערות', 'textarea', [
        'rows' => 3,
        'value' => $grave['comments'] ?? ''
    ]);

    // אם זו עריכה, הוסף את ה-unicId
    if ($grave && isset($grave['unicId'])) {
        $formBuilder->addField('unicId', '', 'hidden', [
            'value' => $grave['unicId']
        ]);
    }

    // הצג את הטופס
    echo $formBuilder->renderModal();

} catch (Throwable $e) {
    error_log("grave-form.php FormBuilder error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    die('<div class="error-message">שגיאה בבניית הטופס: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>
