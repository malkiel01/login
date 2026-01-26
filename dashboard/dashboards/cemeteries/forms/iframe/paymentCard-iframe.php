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
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="sortable-sections" id="paymentCardSortableSections">
        <!-- פרטי התשלום -->
        <div class="sortable-section section-green section-payment">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title">
                    <i class="fas fa-money-bill-wave"></i> הגדרת תשלום #<?= $serialId ?>
                    <span class="status-badge" style="background: <?= $plotTypeColor ?>"><?= $plotTypeName ?></span>
                </span>
            </div>
            <div class="section-content">
                <div class="card-header-row payment">
                    <h2><i class="fas fa-money-bill-wave"></i> הגדרת תשלום #<?= $serialId ?></h2>
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
                        <div class="value"><i class="fas fa-map-marker-alt icon-mr"></i><?= htmlspecialchars($locationStr) ?></div>
                    </div>
                </div>

                <div class="info-grid with-green-border">
                    <div class="info-card"><div class="label">תאריך יצירה</div><div class="value"><?= formatHebrewDate($payment['createDate']) ?></div></div>
                    <div class="info-card"><div class="label">תאריך עדכון</div><div class="value"><?= formatHebrewDate($payment['updateDate']) ?></div></div>
                </div>

                <div class="card-actions">
                    <button class="btn btn-success" onclick="editPayment('<?= $payment['unicId'] ?>')">
                        <i class="fas fa-edit"></i> ערוך תשלום
                    </button>
                </div>
            </div>
        </div>

        <!-- מסמכים -->
        <div class="sortable-section section-gray">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
            </div>
            <div class="section-content">
                <div id="paymentExplorer" class="min-h-200">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>טוען סייר קבצים...</span>
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
                    document.getElementById('paymentExplorer').innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
                }
            };
            script.onerror = function() {
                document.getElementById('paymentExplorer').innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
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
            }
        }
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('paymentCardSortableSections', 'paymentCard');
            }
        });
    </script>
</body>
</html>
