<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/graveCard-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: כרטיס קבר - דף עצמאי לטעינה ב-iframe (פופאפ גנרי)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;

if (!$itemId) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: מזהה קבר חסר</body></html>');
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT g.*,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe, agv.areaGraveNameHe,
            agv.unicId as areaGraveUnicId
        FROM graves g
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE g.unicId = :id
    ");
    $stmt->execute(['id' => $itemId]);
    $grave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grave) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הקבר לא נמצא</body></html>');
    }

    // שליפת רכישה משויכת
    $stmt = $conn->prepare("
        SELECT p.*, c.fullNameHe as clientName, c.numId as clientNumId
        FROM purchases p
        LEFT JOIN customers c ON p.clientId = c.unicId
        WHERE p.graveId = :graveId AND p.isActive = 1
        ORDER BY p.dateOpening DESC
        LIMIT 1
    ");
    $stmt->execute(['graveId' => $grave['unicId']]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    // שליפת קבורה משויכת
    $stmt = $conn->prepare("
        SELECT b.*, c.fullNameHe as deceasedName, c.numId as deceasedNumId
        FROM burials b
        LEFT JOIN customers c ON b.clientId = c.unicId
        WHERE b.graveId = :graveId AND b.isActive = 1
        ORDER BY b.dateBurial DESC
        LIMIT 1
    ");
    $stmt->execute(['graveId' => $grave['unicId']]);
    $burial = $stmt->fetch(PDO::FETCH_ASSOC);

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

$graveStatusNames = [1 => 'פנוי', 2 => 'נרכש', 3 => 'קבור', 4 => 'שמור'];
$graveStatusColors = [1 => '#10b981', 2 => '#3b82f6', 3 => '#ef4444', 4 => '#f59e0b'];
$currentStatus = $grave['graveStatus'] ?? 1;
$statusName = $graveStatusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $graveStatusColors[$currentStatus] ?? '#64748b';

$plotTypeNames = [1 => 'פטורה', 2 => 'חריגה', 3 => 'סגורה'];
$graveLocationNames = [1 => 'עליון', 2 => 'תחתון', 3 => 'אמצעי'];

$graveLocation = [];
if ($grave['cemeteryNameHe']) $graveLocation[] = $grave['cemeteryNameHe'];
if ($grave['blockNameHe']) $graveLocation[] = 'גוש ' . $grave['blockNameHe'];
if ($grave['plotNameHe']) $graveLocation[] = 'חלקה ' . $grave['plotNameHe'];
if ($grave['lineNameHe']) $graveLocation[] = 'שורה ' . $grave['lineNameHe'];
$graveLocationStr = implode(' / ', $graveLocation) ?: '-';

$purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
$purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כרטיס קבר - <?= htmlspecialchars($grave['graveNameHe'] ?? 'קבר') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="sortable-sections" id="graveCardSortableSections">
        <!-- פרטי הקבר -->
        <div class="sortable-section section-grave section-purple">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-monument"></i> <?= htmlspecialchars($grave['graveNameHe'] ?? 'קבר') ?></span>
            </div>
            <div class="section-content">
                <div class="card-header-row grave">
                    <h2><i class="fas fa-monument"></i> <?= htmlspecialchars($grave['graveNameHe'] ?? 'קבר') ?></h2>
                    <span class="status-badge" style="background: <?= $statusColor ?>"><?= $statusName ?></span>
                </div>
                <div class="info-grid">
                    <div class="info-card span-2"><div class="label">מיקום מלא</div><div class="value"><?= $graveLocationStr ?></div></div>
                    <div class="info-card"><div class="label">בית עלמין</div><div class="value"><?= htmlspecialchars($grave['cemeteryNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">גוש</div><div class="value"><?= htmlspecialchars($grave['blockNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">חלקה</div><div class="value"><?= htmlspecialchars($grave['plotNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">שורה</div><div class="value"><?= htmlspecialchars($grave['lineNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">סוג חלקה</div><div class="value"><?= $plotTypeNames[$grave['plotType'] ?? 1] ?? '-' ?></div></div>
                    <div class="info-card"><div class="label">מיקום בשורה</div><div class="value"><?= $graveLocationNames[$grave['graveLocation'] ?? 0] ?? '-' ?></div></div>
                    <div class="info-card"><div class="label">עלות בנייה</div><div class="value"><?= formatPrice($grave['constructionCost']) ?></div></div>
                    <div class="info-card"><div class="label">קבר קטן</div><div class="value"><?= ($grave['isSmallGrave'] ?? 0) ? 'כן' : 'לא' ?></div></div>
                </div>
                <?php if (!empty($grave['comments'])): ?>
                <div class="info-card comments-box"><div class="label">הערות</div><div class="value"><?= nl2br(htmlspecialchars($grave['comments'])) ?></div></div>
                <?php endif; ?>
                <div class="card-actions"><?php if (hasModulePermission('graves', 'edit')): ?><button class="btn btn-purple" onclick="editGrave('<?= $grave['unicId'] ?>')"><i class="fas fa-edit"></i> ערוך קבר</button><?php endif; ?></div>
            </div>
        </div>

        <!-- פרטי רכישה -->
        <?php if ($purchase): ?>
        <div class="sortable-section section-purchase section-green">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-shopping-cart"></i> רכישה</span>
            </div>
            <div class="section-content">
                <?php $pStatus = $purchase['purchaseStatus'] ?? 1; $pStatusName = $purchaseStatusNames[$pStatus] ?? 'לא ידוע'; $pStatusColor = $purchaseStatusColors[$pStatus] ?? '#64748b'; ?>
                <div class="card-header-row grave">
                    <h2><i class="fas fa-shopping-cart"></i> רכישה #<?= htmlspecialchars($purchase['serialPurchaseId'] ?? '-') ?></h2>
                    <span class="status-badge" style="background: <?= $pStatusColor ?>"><?= $pStatusName ?></span>
                </div>
                <div class="info-grid">
                    <div class="info-card"><div class="label">מספר רכישה</div><div class="value"><?= htmlspecialchars($purchase['serialPurchaseId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">תאריך פתיחה</div><div class="value"><?= formatHebrewDate($purchase['dateOpening']) ?></div></div>
                    <div class="info-card"><div class="label">מחיר</div><div class="value value-lg"><?= formatPrice($purchase['price']) ?></div></div>
                    <div class="info-card"><div class="label">שם הרוכש</div><div class="value"><?= htmlspecialchars($purchase['clientName'] ?? '-') ?></div></div>
                </div>
                <div class="card-actions"><button class="btn btn-success" onclick="viewPurchase('<?= $purchase['unicId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס רכישה</button></div>
            </div>
        </div>
        <?php else: ?>
        <div class="sortable-section section-purchase section-green">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-shopping-cart"></i> רכישה</span>
            </div>
            <div class="section-content">
                <div class="empty-state"><i class="fas fa-inbox"></i>אין רכישה משויכת לקבר זה</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- תיק קבורה -->
        <?php if ($burial): ?>
        <div class="sortable-section section-burial section-orange">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-cross"></i> קבורה</span>
            </div>
            <div class="section-content">
                <div class="card-header-row grave">
                    <h2><i class="fas fa-cross"></i> <?= htmlspecialchars($burial['deceasedName'] ?? 'נפטר/ת') ?></h2>
                </div>
                <div class="info-grid">
                    <div class="info-card span-2"><div class="label">שם הנפטר/ת</div><div class="value value-lg"><?= htmlspecialchars($burial['deceasedName'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">מספר קבורה</div><div class="value"><?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">תאריך קבורה</div><div class="value"><?= formatHebrewDate($burial['dateBurial']) ?></div></div>
                </div>
                <div class="card-actions"><button class="btn btn-warning" onclick="viewBurial('<?= $burial['unicId'] ?>')"><i class="fas fa-eye"></i> צפה בתיק קבורה</button></div>
            </div>
        </div>
        <?php else: ?>
        <div class="sortable-section section-burial section-orange">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-cross"></i> קבורה</span>
            </div>
            <div class="section-content">
                <div class="empty-state"><i class="fas fa-inbox"></i>אין קבורה משויכת לקבר זה</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- מסמכים -->
        <div class="sortable-section section-gray">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
            </div>
            <div class="section-content">
                <div id="graveExplorer" class="min-h-200">
                    <div class="loading-state lg">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>טוען סייר קבצים...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const graveId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') PopupAPI.setTitle('כרטיס קבר - <?= addslashes($grave['graveNameHe'] ?? 'קבר') ?>');
            initFileExplorer();
        });

        function initFileExplorer() {
            if (!graveId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.graveExplorer = new FileExplorer('graveExplorer', graveId, {});
                    window.explorer = window.graveExplorer;
                } else {
                    document.getElementById('graveExplorer').innerHTML = '<div class="error-state lg"><i class="fas fa-exclamation-triangle"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
                }
            };
            script.onerror = function() {
                document.getElementById('graveExplorer').innerHTML = '<div class="error-state lg"><i class="fas fa-exclamation-triangle"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
            };
            document.head.appendChild(script);
        }

        function toggleSection(btn) { btn.closest('.sortable-section').classList.toggle('collapsed'); }
        function editGrave(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({
                    id: 'graveForm-' + id,
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/forms/iframe/graveForm-iframe.php?itemId=' + id,
                    title: 'עריכת קבר',
                    width: 900,
                    height: 700
                });
            }
        }
        function viewPurchase(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({ id: 'purchaseCard-' + id, type: 'iframe', src: '/dashboard/dashboards/cemeteries/forms/iframe/purchaseCard-iframe.php?itemId=' + id, title: 'כרטיס רכישה', width: 1200, height: 700 });
            }
        }
        function viewBurial(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({ id: 'burialCard-' + id, type: 'iframe', src: '/dashboard/dashboards/cemeteries/forms/iframe/burialCard-iframe.php?itemId=' + id, title: 'כרטיס קבורה', width: 1200, height: 700 });
            }
        }
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('graveCardSortableSections', 'graveCard');
            }
        });
    </script>
</body>
</html>
