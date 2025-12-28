<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/graveCard-form.php
 * Version: 1.0.0
 * Updated: 2025-11-25
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת כרטיס קבר עם FormBuilder
 *   - תצוגת פרטי קבר + היררכיה
 *   - הצגת תיק רכישה (אם קיים)
 *   - הצגת תיק קבורה (אם קיים)
 *   - כפתורים דינמיים לפי סטטוס
 *   - אפשרות שמירת קבר (סטטוס 1→4)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$formType = 'graveCard';

if (!$itemId) {
    die('<div class="error-message">שגיאה: מזהה קבר חסר</div>');
}

try {
    $conn = getDBConnection();
    
    // שליפת נתוני הקבר עם היררכיה מלאה
    $stmt = $conn->prepare("
        SELECT 
            g.*,
            agv.areaGraveNameHe,
            agv.lineNameHe,
            agv.plotNameHe,
            agv.blockNameHe,
            agv.cemeteryNameHe,
            agv.cemeteryId,
            agv.blockId,
            agv.plotId,
            agv.lineId
        FROM graves g
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE g.unicId = :id
        AND g.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $grave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$grave) {
        die('<div class="error-message">שגיאה: הקבר לא נמצא</div>');
    }
    
    // שליפת פרטי רכישה אם קיימים
    $purchase = null;
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            c.fullNameHe as clientFullNameHe,
            c.numId as clientNumId,
            c.phone,
            c.phoneMobile,
            contact.fullNameHe as contactFullNameHe
        FROM purchases p
        LEFT JOIN customers c ON p.clientId = c.unicId
        LEFT JOIN customers contact ON p.contactId = contact.unicId
        WHERE p.graveId = :graveId AND p.isActive = 1
        LIMIT 1
    ");
    $stmt->execute(['graveId' => $grave['unicId']]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // שליפת פרטי קבורה אם קיימים
    $burial = null;
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            c.fullNameHe as clientFullNameHe,
            c.numId as clientNumId,
            c.nameFather as clientNameFather,
            c.nameMother as clientNameMother,
            contact.fullNameHe as contactFullNameHe
        FROM burials b
        LEFT JOIN customers c ON b.clientId = c.unicId
        LEFT JOIN customers contact ON b.contactId = contact.unicId
        WHERE b.graveId = :graveId AND b.isActive = 1
        LIMIT 1
    ");
    $stmt->execute(['graveId' => $grave['unicId']]);
    $burial = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// פונקציות עזר
function formatHebrewDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function formatPrice($price) {
    if (!$price || $price == 0) {
        return '-';
    }
    return '₪' . number_format($price, 2);
}

function formatPhone($phone) {
    if (!$phone) return '-';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        return substr($phone, 0, 3) . '-' . substr($phone, 3);
    }
    return $phone;
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('graveCard', $itemId, null);

// HTML מותאם - היררכיה
$hierarchyHTML = '
 <style>
     #graveCardFormModal .modal-dialog {
         max-width: 95% !important;
         width: 1200px !important;
     }
     #graveCardFormModal .modal-body {
         max-height: 80vh !important;
     }
 </style>

 <fieldset class="form-section" style="border: 2px solid #e0f2fe; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
     <legend style="padding: 0 15px; font-weight: bold; color: #0284c7; font-size: 16px;">
         <i class="fas fa-sitemap"></i> מיקום בהיררכיה
     </legend>
     <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
         <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bae6fd;">
             <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">בית עלמין</div>
             <div style="font-weight: 600; color: #0c4a6e;">' . htmlspecialchars($grave['cemeteryNameHe'] ?? '-') . '</div>
         </div>
         <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bae6fd;">
             <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">גוש</div>
             <div style="font-weight: 600; color: #0c4a6e;">' . htmlspecialchars($grave['blockNameHe'] ?? '-') . '</div>
         </div>
         <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bae6fd;">
             <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">חלקה</div>
             <div style="font-weight: 600; color: #0c4a6e;">' . htmlspecialchars($grave['plotNameHe'] ?? '-') . '</div>
         </div>
         <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bae6fd;">
             <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שורה</div>
             <div style="font-weight: 600; color: #0c4a6e;">' . htmlspecialchars($grave['lineNameHe'] ?? '-') . '</div>
         </div>
         <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bae6fd;">
             <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">אחוזת קבר</div>
             <div style="font-weight: 600; color: #0c4a6e;">' . htmlspecialchars($grave['areaGraveNameHe'] ?? '-') . '</div>
         </div>
     </div>

    <!-- ⭐ כפתור עריכת אחוזת קבר -->
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #bae6fd;">
        <button type="button" 
                class="btn btn-sm btn-primary" 
                onclick="openGraveEdit(\'' . $grave['areaGraveId'] . '\')"
                style="width: 100%; background: linear-gradient(135deg, #0284c7, #0369a1); border: none; padding: 10px; font-weight: 600;">
            <i class="fas fa-edit"></i> ערוך אחוזת קבר
        </button>
    </div>
 </fieldset>';

$formBuilder->addCustomHTML($hierarchyHTML);

// שדות קבר בסיסיים
$formBuilder->addField('graveNameHe', 'שם הקבר', 'text', [
    'value' => $grave['graveNameHe'] ?? '',
    'readonly' => true
]);

$formBuilder->addField('plotType', 'סוג חלקה', 'select', [
    'options' => [
        1 => 'פטורה',
        2 => 'חריגה',
        3 => 'סגורה'
    ],
    'value' => $grave['plotType'] ?? 1,
    'readonly' => true
]);

$formBuilder->addField('graveStatus', 'סטטוס קבר', 'select', [
    'options' => [
        1 => 'פנוי',
        2 => 'נרכש',
        3 => 'קבור',
        4 => 'שמור'
    ],
    'value' => $grave['graveStatus'] ?? 1,
    'readonly' => true
]);

$formBuilder->addField('graveLocation', 'מיקום בשורה', 'select', [
    'options' => [
        1 => 'עליון',
        2 => 'תחתון',
        3 => 'אמצעי'
    ],
    'value' => $grave['graveLocation'] ?? 1,
    'readonly' => true
]);

$formBuilder->addField('isSmallGrave', 'קבר קטן', 'select', [
    'options' => [
        0 => 'לא',
        1 => 'כן'
    ],
    'value' => $grave['isSmallGrave'] ?? 0,
    'readonly' => true
]);

$formBuilder->addField('constructionCost', 'עלות בנייה', 'text', [
    'value' => formatPrice($grave['constructionCost']),
    'readonly' => true
]);

$formBuilder->addField('createDate', 'תאריך יצירה', 'text', [
    'value' => formatHebrewDate($grave['createDate']),
    'readonly' => true
]);

// תיק שמירה (רק אם סטטוס 4)
if ($grave['graveStatus'] == 4) {
    $savedHTML = '
    <fieldset class="form-section" style="border: 2px solid #e9d5ff; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);">
        <legend style="padding: 0 15px; font-weight: bold; color: #7c3aed; font-size: 16px;">
            <i class="fas fa-bookmark"></i> תיק שמירה
        </legend>
        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e9d5ff;">
            <div style="font-size: 13px; color: #64748b; margin-bottom: 6px;">תאריך שמירה</div>
            <div style="font-weight: 600; color: #6b21a8; font-size: 15px;">' . formatHebrewDate($grave['saveDate']) . '</div>
        </div>
    </fieldset>';
    $formBuilder->addCustomHTML($savedHTML);
}

// תיק רכישה
if ($purchase) {
    $purchaseStatusNames = [
        1 => 'פתוח',
        2 => 'שולם',
        3 => 'סגור',
        4 => 'בוטל'
    ];
    $purchaseStatusColors = [
        1 => '#3b82f6',
        2 => '#10b981',
        3 => '#64748b',
        4 => '#ef4444'
    ];
    $statusName = $purchaseStatusNames[$purchase['purchaseStatus']] ?? 'לא ידוע';
    $statusColor = $purchaseStatusColors[$purchase['purchaseStatus']] ?? '#64748b';
    
    $purchaseHTML = '
    <fieldset class="form-section" style="border: 2px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
        <legend style="padding: 0 15px; font-weight: bold; color: #1e40af; font-size: 16px;">
            <i class="fas fa-shopping-cart"></i> תיק רכישה
            <span style="background: ' . $statusColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-right: 10px;">' . $statusName . '</span>
        </legend>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם הרוכש</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . htmlspecialchars($purchase['clientFullNameHe'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">ת.ז. רוכש</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . htmlspecialchars($purchase['clientNumId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר רכישה</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . htmlspecialchars($purchase['serialPurchaseId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מחיר רכישה</div>
                <div style="font-weight: 600; color: #059669; font-size: 15px;">' . formatPrice($purchase['price']) . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך רכישה</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . formatHebrewDate($purchase['dateOpening']) . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">טלפון</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . formatPhone($purchase['phone'] ?? $purchase['phoneMobile']) . '</div>
            </div>
        </div>
        <div style="margin-top: 12px; display: flex; gap: 10px;">
            <button type="button" class="btn btn-sm btn-primary" onclick="GraveCardHandler.editPurchase(\'' . $purchase['unicId'] . '\')">
                <i class="fas fa-edit"></i> ערוך רכישה
            </button>
        </div>
    </fieldset>';
    $formBuilder->addCustomHTML($purchaseHTML);
    
} elseif ($grave['graveStatus'] == 1) {
    // אין רכישה והקבר פנוי - הצג כפתור הוספה
    $noPurchaseHTML = '
    <fieldset class="form-section" style="border: 2px dashed #bfdbfe; border-radius: 12px; padding: 30px; margin-bottom: 20px; background: #f8fafc; text-align: center;">
        <legend style="padding: 0 15px; font-weight: bold; color: #94a3b8; font-size: 16px;">
            <i class="fas fa-shopping-cart"></i> תיק רכישה
        </legend>
        <div style="color: #64748b; margin-bottom: 15px;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
            אין רכישה מקושרת לקבר זה
        </div>
        <button type="button" class="btn btn-success btn-open-purchase"
            style="padding: 10px 24px; font-size: 15px;">
            <i class="fas fa-plus"></i> הוסף רכישה
        </button>
    </fieldset>';
    $formBuilder->addCustomHTML($noPurchaseHTML);
}

// תיק קבורה
if ($burial) {
    $burialHTML = '
    <fieldset class="form-section" style="border: 2px solid #fde68a; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">
        <legend style="padding: 0 15px; font-weight: bold; color: #92400e; font-size: 16px;">
            <i class="fas fa-cross"></i> תיק קבורה
        </legend>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a; grid-column: span 2;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם הנפטר/ת</div>
                <div style="font-weight: 700; color: #78350f; font-size: 16px;">' . htmlspecialchars($burial['clientFullNameHe'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">ת.ז. נפטר</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['clientNumId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר קבורה</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האב</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['clientNameFather'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האם</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['clientNameMother'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך פטירה</div>
                <div style="font-weight: 600; color: #78350f;">' . formatHebrewDate($burial['dateDeath']) . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך קבורה</div>
                <div style="font-weight: 600; color: #78350f;">' . formatHebrewDate($burial['dateBurial']) . '</div>
            </div>
        </div>
        <div style="margin-top: 12px; display: flex; gap: 10px;">
            <button type="button" class="btn btn-sm btn-primary" onclick="GraveCardHandler.editBurial(\'' . $burial['unicId'] . '\')">
                <i class="fas fa-edit"></i> ערוך קבורה
            </button>
        </div>
    </fieldset>';
    $formBuilder->addCustomHTML($burialHTML);
    
} elseif ($grave['graveStatus'] == 1 || $grave['graveStatus'] == 2) {
    // אין קבורה והקבר פנוי או נרכש - הצג כפתור הוספה
    $noBurialHTML = '
    <fieldset class="form-section" style="border: 2px dashed #fde68a; border-radius: 12px; padding: 30px; margin-bottom: 20px; background: #fffef5; text-align: center;">
        <legend style="padding: 0 15px; font-weight: bold; color: #94a3b8; font-size: 16px;">
            <i class="fas fa-cross"></i> תיק קבורה
        </legend>
        <div style="color: #64748b; margin-bottom: 15px;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
            אין קבורה מקושרת לקבר זה
        </div>
        <button type="button" class="btn btn-info btn-open-burial" 
            style="padding: 10px 24px; font-size: 15px;">
            <i class="fas fa-plus"></i> הוסף קבורה
        </button>
    </fieldset>';
    $formBuilder->addCustomHTML($noBurialHTML);
}

// הערות
if (!empty($grave['comments'])) {
    $formBuilder->addField('comments', 'הערות', 'textarea', [
        'value' => $grave['comments'],
        'rows' => 3,
        'readonly' => true
    ]);
}

// === חלון מסמכים (סייר קבצים) ===
$explorerUnicId = htmlspecialchars($grave['unicId']);
$explorerHTML = '
<fieldset class="form-section" style="border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
    <legend style="padding: 0 15px; font-weight: bold; color: #475569; font-size: 16px;">
        <i class="fas fa-folder-open"></i> מסמכים
    </legend>
    <div id="graveExplorer" data-unic-id="' . $explorerUnicId . '">
        <div style="text-align: center; padding: 20px; color: #666;">
            <i class="fas fa-spinner fa-spin"></i> טוען סייר קבצים...
        </div>
    </div>
</fieldset>
';
$formBuilder->addCustomHTML($explorerHTML);

// שדה מוסתר - unicId
$formBuilder->addField('unicId', '', 'hidden', [
    'value' => $grave['unicId']
]);

// שדה מוסתר - graveStatus (לשימוש ב-JS)
$formBuilder->addField('currentGraveStatus', '', 'hidden', [
    'value' => $grave['graveStatus']
]);

// שדה מוסתר - areaGraveId (למעבר לעריכת אחוזת קבר)
$formBuilder->addField('areaGraveId', '', 'hidden', [
    'value' => $grave['areaGraveId']
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>