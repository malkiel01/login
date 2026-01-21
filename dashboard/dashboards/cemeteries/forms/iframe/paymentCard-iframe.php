<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/paymentCard-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: כרטיס תשלום - דף עצמאי לטעינה ב-iframe (פופאפ גנרי)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;

if (!$itemId) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: מזהה תשלום חסר</body></html>');
}

try {
    $conn = getDBConnection();

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
        WHERE p.unicId = :id AND p.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: התשלום לא נמצא</body></html>');
    }

} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

function formatHebrewDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function formatPrice($price) {
    if (!$price || $price == 0) return '-';
    return '₪' . number_format($price, 2);
}

// מיפויים
$plotTypes = [-1 => 'כל הסוגים', 1 => 'פטורה', 2 => 'חריגה', 3 => 'סגורה'];
$graveTypes = [-1 => 'כל הסוגים', 1 => 'שדה', 2 => 'רוויה', 3 => 'סנהדרין'];
$residentTypes = [-1 => 'כל הסוגים', 1 => 'ירושלים והסביבה', 2 => 'תושב חוץ', 3 => 'תושב חו"ל'];
$buyerStatusTypes = [-1 => 'כל הסוגים', 1 => 'בחיים', 2 => 'לאחר פטירה', 3 => 'בן/בת זוג נפטר'];
$priceDefinitions = [
    1 => 'עלות קבר', 2 => 'שירותי לוויה', 3 => 'שירותי קבורה',
    4 => 'אגרת מצבה', 5 => 'בדיקת עומק', 6 => 'פירוק מצבה',
    7 => 'הובלה מנתב"ג', 8 => 'טהרה', 9 => 'תכריכים', 10 => 'החלפת שם'
];

$plotTypeName = $plotTypes[$payment['plotType'] ?? -1] ?? '-';
$graveTypeName = $graveTypes[$payment['graveType'] ?? -1] ?? '-';
$residentTypeName = $residentTypes[$payment['resident'] ?? -1] ?? '-';
$buyerStatusName = $buyerStatusTypes[$payment['buyerStatus'] ?? -1] ?? '-';
$priceDefinitionName = $priceDefinitions[$payment['priceDefinition'] ?? 0] ?? '-';

$plotTypeColors = [-1 => '#64748b', 1 => '#10b981', 2 => '#f97316', 3 => '#dc2626'];
$plotTypeColor = $plotTypeColors[$payment['plotType'] ?? -1] ?? '#64748b';

$location = [];
if ($payment['cemeteryNameHe']) $location[] = $payment['cemeteryNameHe'];
if ($payment['blockNameHe']) $location[] = 'גוש ' . $payment['blockNameHe'];
if ($payment['plotNameHe']) $location[] = 'חלקה ' . $payment['plotNameHe'];
if ($payment['lineNameHe']) $location[] = 'שורה ' . $payment['lineNameHe'];
$locationStr = implode(' / ', $location) ?: 'כל המיקומים';

$serialId = htmlspecialchars($payment['serialPaymentId'] ?? $payment['unicId']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כרטיס תשלום #<?= $serialId ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f1f5f9; color: #334155; padding: 20px; direction: rtl; }
        .sortable-sections { display: flex; flex-direction: column; gap: 15px; }
        .sortable-section { background: white; border-radius: 12px; border: 2px solid transparent; overflow: hidden; }
        .sortable-section:hover { border-color: #94a3b8; }
        .section-drag-handle { height: 32px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1); cursor: grab; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #cbd5e1; position: relative; }
        .section-drag-handle::before { content: ""; width: 40px; height: 4px; background: #94a3b8; border-radius: 2px; }
        .section-toggle-btn { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 24px; height: 24px; border: none; background: rgba(100,116,139,0.2); border-radius: 4px; cursor: pointer; color: #64748b; font-size: 12px; display: flex; align-items: center; justify-content: center; }
        .section-title { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 13px; font-weight: 600; }
        .section-content { padding: 20px; }
        .sortable-section.collapsed .section-content { display: none; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .info-card { padding: 12px; border-radius: 8px; background: white; }
        .info-card .label { font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .info-card .value { font-weight: 600; }
        .info-card.span-2 { grid-column: span 2; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; color: white; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-success { background: #10b981; color: white; }
        .empty-state { text-align: center; padding: 30px; color: #94a3b8; }
        .empty-state i { font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5; }

        .section-payment .info-card { border: 1px solid #a7f3d0; }
        .section-payment .info-card .value { color: #065f46; }

        .price-box {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid #10b981;
        }
        .price-box .price-label { font-size: 14px; color: #64748b; margin-bottom: 5px; }
        .price-box .price-value { font-size: 36px; font-weight: 700; color: #065f46; }
        .price-box .price-type { font-size: 14px; color: #10b981; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="sortable-sections">
        <!-- פרטי התשלום -->
        <div class="sortable-section section-payment">
            <div class="section-drag-handle" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title" style="color: #065f46;">
                    <i class="fas fa-money-bill-wave"></i> הגדרת תשלום #<?= $serialId ?>
                    <span class="status-badge" style="background: <?= $plotTypeColor ?>"><?= $plotTypeName ?></span>
                </span>
            </div>
            <div class="section-content" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <h2 style="margin: 0; color: #065f46;"><i class="fas fa-money-bill-wave"></i> הגדרת תשלום #<?= $serialId ?></h2>
                    <span class="status-badge" style="background: <?= $plotTypeColor ?>"><?= $plotTypeName ?></span>
                </div>

                <div class="price-box">
                    <div class="price-label">מחיר</div>
                    <div class="price-value"><?= formatPrice($payment['price']) ?></div>
                    <div class="price-type"><?= $priceDefinitionName ?></div>
                </div>

                <div class="info-grid">
                    <div class="info-card"><div class="label">הגדרת מחיר</div><div class="value"><?= $priceDefinitionName ?></div></div>
                    <div class="info-card"><div class="label">סוג חלקה</div><div class="value"><?= $plotTypeName ?></div></div>
                    <div class="info-card"><div class="label">סוג קבר</div><div class="value"><?= $graveTypeName ?></div></div>
                    <div class="info-card"><div class="label">סוג תושב</div><div class="value"><?= $residentTypeName ?></div></div>
                    <div class="info-card"><div class="label">סטטוס רוכש</div><div class="value"><?= $buyerStatusName ?></div></div>
                    <div class="info-card"><div class="label">תאריך התחלת תשלום</div><div class="value"><?= formatHebrewDate($payment['startPayment']) ?></div></div>
                    <div class="info-card span-2">
                        <div class="label">מיקום</div>
                        <div class="value"><i class="fas fa-map-marker-alt" style="margin-left: 5px;"></i><?= htmlspecialchars($locationStr) ?></div>
                    </div>
                </div>

                <div class="info-grid" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #a7f3d0;">
                    <div class="info-card"><div class="label">תאריך יצירה</div><div class="value"><?= formatHebrewDate($payment['createDate']) ?></div></div>
                    <div class="info-card"><div class="label">תאריך עדכון</div><div class="value"><?= formatHebrewDate($payment['updateDate']) ?></div></div>
                </div>

                <div style="margin-top: 15px;">
                    <button class="btn btn-success" onclick="editPayment('<?= $payment['unicId'] ?>')">
                        <i class="fas fa-edit"></i> ערוך תשלום
                    </button>
                </div>
            </div>
        </div>

        <!-- מסמכים -->
        <div class="sortable-section">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
            </div>
            <div class="section-content">
                <div id="paymentExplorer" style="min-height: 200px;">
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                        טוען סייר קבצים...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const paymentId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') PopupAPI.setTitle('כרטיס תשלום #<?= addslashes($serialId) ?>');
            initFileExplorer();
        });

        function initFileExplorer() {
            if (!paymentId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.paymentExplorer = new FileExplorer('paymentExplorer', paymentId, {});
                    window.explorer = window.paymentExplorer;
                } else {
                    document.getElementById('paymentExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>שגיאה בטעינת סייר הקבצים</div>';
                }
            };
            script.onerror = function() {
                document.getElementById('paymentExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>שגיאה בטעינת סייר הקבצים</div>';
            };
            document.head.appendChild(script);
        }

        function toggleSection(btn) { btn.closest('.sortable-section').classList.toggle('collapsed'); }
        function editPayment(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({
                    id: 'paymentForm-' + id,
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/forms/iframe/paymentForm-iframe.php?itemId=' + id,
                    title: 'עריכת הגדרת תשלום',
                    width: 800,
                    height: 600
                });
            } else if (window.parent && window.parent.FormHandler) {
                window.parent.FormHandler.openForm('payment', null, id);
            }
        }
    </script>
</body>
</html>
