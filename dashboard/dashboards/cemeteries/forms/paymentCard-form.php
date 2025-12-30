<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/paymentCard-form.php
 * Version: 1.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Description: כרטיס תשלום - תצוגת פרטי הגדרת תשלום עם סקשנים ניתנים לגרירה
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$formType = 'paymentCard';

if (!$itemId) {
    die('<div class="error-message">שגיאה: מזהה תשלום חסר</div>');
}

try {
    $conn = getDBConnection();

    // שליפת נתוני התשלום עם פרטי מיקום
    $stmt = $conn->prepare("
        SELECT p.*,
            c.cemeteryNameHe,
            b.blockNameHe,
            pl.plotNameHe,
            r.lineNameHe
        FROM payments p
        LEFT JOIN cemeteries c ON p.cemeteryId = c.unicId
        LEFT JOIN blocks b ON p.blockId = b.unicId
        LEFT JOIN plots pl ON p.plotId = pl.unicId
        LEFT JOIN rows r ON p.lineId = r.unicId
        WHERE p.id = :id AND p.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die('<div class="error-message">שגיאה: התשלום לא נמצא</div>');
    }

} catch (Exception $e) {
    FormUtils::handleError($e);
}

// פונקציות עזר
function formatHebrewDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function formatPrice($price) {
    if (!$price || $price == 0) return '-';
    return '₪' . number_format($price, 2);
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('paymentCard', $itemId, null);

// מיפוי ערכים
$plotTypes = [-1 => 'כל הסוגים', 1 => 'פטורה', 2 => 'חריגה', 3 => 'סגורה'];
$graveTypes = [-1 => 'כל הסוגים', 1 => 'שדה', 2 => 'רוויה', 3 => 'סנהדרין'];
$residentTypes = [-1 => 'כל הסוגים', 1 => 'ירושלים והסביבה', 2 => 'תושב חוץ', 3 => 'תושב חו"ל'];
$buyerStatusTypes = [-1 => 'כל הסוגים', 1 => 'בחיים', 2 => 'לאחר פטירה', 3 => 'בן זוג נפטר'];
$priceDefinitions = [
    1 => 'עלות קבר',
    2 => 'שירותי לוויה',
    3 => 'שירותי קבורה',
    4 => 'אגרת מצבה',
    5 => 'בדיקת עומק',
    6 => 'פירוק מצבה',
    7 => 'הובלה מנתב"ג',
    8 => 'טהרה',
    9 => 'תכריכים',
    10 => 'החלפת שם'
];

// קבלת ערכים
$plotTypeName = $plotTypes[$payment['plotType'] ?? -1] ?? '-';
$graveTypeName = $graveTypes[$payment['graveType'] ?? -1] ?? '-';
$residentTypeName = $residentTypes[$payment['resident'] ?? -1] ?? '-';
$buyerStatusName = $buyerStatusTypes[$payment['buyerStatus'] ?? -1] ?? '-';
$priceDefinitionName = $priceDefinitions[$payment['priceDefinition'] ?? 0] ?? '-';

// צבעים לפי סוג
$plotTypeColors = [-1 => '#64748b', 1 => '#10b981', 2 => '#f97316', 3 => '#dc2626'];
$plotTypeColor = $plotTypeColors[$payment['plotType'] ?? -1] ?? '#64748b';

// מיקום
$location = [];
if ($payment['cemeteryNameHe']) $location[] = $payment['cemeteryNameHe'];
if ($payment['blockNameHe']) $location[] = 'גוש ' . $payment['blockNameHe'];
if ($payment['plotNameHe']) $location[] = 'חלקה ' . $payment['plotNameHe'];
if ($payment['lineNameHe']) $location[] = 'שורה ' . $payment['lineNameHe'];
$locationStr = implode(' / ', $location) ?: 'כל המיקומים';

// === בניית HTML ===
$allSectionsHTML = '
<style>
    /* רוחב המודל */
    #paymentCardFormModal .modal-dialog {
        max-width: 95% !important;
        width: 1200px !important;
    }
    #paymentCardFormModal .modal-body {
        max-height: 85vh !important;
        padding: 20px !important;
    }

    /* סקשנים */
    .sortable-sections {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .sortable-section {
        position: relative;
        margin-bottom: 15px;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.2s, transform 0.2s;
        border: 2px solid transparent;
    }

    .sortable-section:hover {
        border-color: #94a3b8;
    }

    .sortable-section.sortable-ghost {
        opacity: 0.5;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px dashed #3b82f6 !important;
    }

    .sortable-section.sortable-chosen {
        box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
        border: 2px solid #3b82f6 !important;
        transform: scale(1.01);
        z-index: 1000;
    }

    /* ידית גרירה */
    .section-drag-handle {
        height: 28px;
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        cursor: grab;
        border-radius: 10px 10px 0 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #cbd5e1;
        transition: background 0.2s;
        position: relative;
    }

    .section-drag-handle::before {
        content: "";
        width: 40px;
        height: 4px;
        background: #94a3b8;
        border-radius: 2px;
    }

    .section-drag-handle:hover {
        background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
    }

    /* כפתור צימצום */
    .section-toggle-btn {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        border: none;
        background: rgba(100, 116, 139, 0.2);
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 10px;
        transition: all 0.2s;
        padding: 0;
    }

    .section-toggle-btn:hover {
        background: rgba(100, 116, 139, 0.4);
        color: #334155;
    }

    .section-toggle-btn i {
        transition: transform 0.3s;
    }

    /* כותרת סקשן */
    .section-title {
        position: absolute;
        right: 35px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
        white-space: nowrap;
    }

    .sortable-section.collapsed .section-title {
        opacity: 1;
    }

    /* מצב מצומצם */
    .sortable-section.collapsed .section-content {
        display: none;
    }

    .sortable-section.collapsed .section-toggle-btn i {
        transform: rotate(-90deg);
    }

    .sortable-section.collapsed .section-drag-handle {
        border-radius: 10px;
        border-bottom: none;
    }

    .sortable-section.collapsed .section-resize-handle {
        display: none;
    }

    /* ידית שינוי גובה */
    .section-resize-handle {
        height: 8px;
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        cursor: ns-resize;
        border-radius: 0 0 10px 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-top: 1px solid #cbd5e1;
    }

    .section-resize-handle::before {
        content: "";
        width: 30px;
        height: 3px;
        background: #94a3b8;
        border-radius: 2px;
    }

    /* תוכן סקשן */
    .section-content {
        overflow: auto;
        transition: max-height 0.3s;
    }
</style>

<div class="sortable-sections" id="paymentSortableSections">

<!-- סקשן 1: פרטי התשלום -->
<div class="sortable-section" data-section="payment">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-money-bill-wave"></i> פרטי התשלום</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
            <legend style="padding: 0 15px; font-weight: bold; color: #065f46; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-money-bill-wave"></i> הגדרת תשלום #' . htmlspecialchars($payment['id']) . '
                <span style="background: ' . $plotTypeColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">' . $plotTypeName . '</span>
            </legend>

            <!-- מחיר גדול -->
            <div style="text-align: center; padding: 20px; background: white; border-radius: 12px; margin-bottom: 20px; border: 2px solid #10b981;">
                <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">מחיר</div>
                <div style="font-size: 36px; font-weight: 700; color: #065f46;">' . formatPrice($payment['price']) . '</div>
                <div style="font-size: 14px; color: #10b981; margin-top: 5px;">' . $priceDefinitionName . '</div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">הגדרת מחיר</div>
                    <div style="font-weight: 600; color: #065f46;">' . $priceDefinitionName . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סוג חלקה</div>
                    <div style="font-weight: 600; color: #065f46;">' . $plotTypeName . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סוג קבר</div>
                    <div style="font-weight: 600; color: #065f46;">' . $graveTypeName . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סוג תושב</div>
                    <div style="font-weight: 600; color: #065f46;">' . $residentTypeName . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סטטוס רוכש</div>
                    <div style="font-weight: 600; color: #065f46;">' . $buyerStatusName . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך התחלת תשלום</div>
                    <div style="font-weight: 600; color: #065f46;">' . formatHebrewDate($payment['startPayment']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0; grid-column: span 2;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מיקום</div>
                    <div style="font-weight: 600; color: #065f46;">
                        <i class="fas fa-map-marker-alt" style="margin-left: 5px;"></i>
                        ' . htmlspecialchars($locationStr) . '
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #a7f3d0;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך יצירה</div>
                    <div style="font-weight: 600; color: #065f46;">' . formatHebrewDate($payment['createDate']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך עדכון</div>
                    <div style="font-weight: 600; color: #065f46;">' . formatHebrewDate($payment['updateDate']) . '</div>
                </div>
            </div>

            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-success" onclick="PaymentCardHandler.editPayment(' . $payment['id'] . ')">
                    <i class="fas fa-edit"></i> ערוך תשלום
                </button>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 2: מסמכים ===
$explorerUnicId = 'payment_' . $payment['id'];
$allSectionsHTML .= '
<!-- סקשן 2: מסמכים -->
<div class="sortable-section" data-section="documents">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
            <legend style="padding: 0 15px; font-weight: bold; color: #1e293b; font-size: 16px;">
                <i class="fas fa-folder-open"></i> מסמכים
            </legend>
            <div id="paymentExplorer" data-unic-id="' . $explorerUnicId . '" data-entity-type="payment">
                <div style="text-align: center; padding: 20px; color: #64748b;">
                    <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                    טוען סייר קבצים...
                </div>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>

</div>
<!-- סוף מיכל הסקשנים -->
';

$formBuilder->addCustomHTML($allSectionsHTML);

$formBuilder->addField('id', '', 'hidden', [
    'value' => $payment['id']
]);

echo $formBuilder->renderModal();
?>
