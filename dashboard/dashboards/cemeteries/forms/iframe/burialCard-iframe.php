<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/burialCard-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: כרטיס קבורה - דף עצמאי לטעינה ב-iframe (פופאפ גנרי)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;

if (!$itemId) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: מזהה קבורה חסר</body></html>');
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT b.*,
            c.firstName, c.lastName, c.fullNameHe as deceasedFullNameHe,
            c.numId as deceasedNumId, c.phone as deceasedPhone, c.phoneMobile as deceasedPhoneMobile,
            c.dateBirth as deceasedDateBirth, c.nameFather as deceasedNameFather, c.nameMother as deceasedNameMother,
            c.address as deceasedAddress, c.gender as deceasedGender,
            g.graveNameHe, g.graveStatus, g.unicId as graveUnicId,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe, agv.areaGraveNameHe,
            p.unicId as purchaseUnicId, p.serialPurchaseId, p.purchaseStatus, p.price as purchasePrice,
            p.dateOpening as purchaseDateOpening, p.numOfPayments,
            pc.fullNameHe as purchaserFullNameHe, pc.numId as purchaserNumId
        FROM burials b
        LEFT JOIN customers c ON b.clientId = c.unicId
        LEFT JOIN graves g ON b.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        LEFT JOIN purchases p ON b.purchaseId = p.unicId AND p.isActive = 1
        LEFT JOIN customers pc ON p.clientId = pc.unicId
        WHERE b.unicId = :id AND b.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $burial = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$burial) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הקבורה לא נמצאה</body></html>');
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

function formatPhone($phone) {
    if (!$phone) return '-';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) return substr($phone, 0, 3) . '-' . substr($phone, 3);
    return $phone;
}

$burialStatusNames = [1 => 'פתוח', 2 => 'בתהליך', 3 => 'הושלם', 4 => 'בוטל'];
$burialStatusColors = [1 => '#3b82f6', 2 => '#f59e0b', 3 => '#10b981', 4 => '#ef4444'];
$currentStatus = $burial['burialStatus'] ?? 1;
$statusName = $burialStatusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $burialStatusColors[$currentStatus] ?? '#64748b';

$purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
$purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];

$deceasedName = htmlspecialchars($burial['deceasedFullNameHe'] ?? ($burial['firstName'] . ' ' . $burial['lastName']) ?? 'לא ידוע');

$graveLocation = [];
if ($burial['cemeteryNameHe']) $graveLocation[] = $burial['cemeteryNameHe'];
if ($burial['blockNameHe']) $graveLocation[] = 'גוש ' . $burial['blockNameHe'];
if ($burial['plotNameHe']) $graveLocation[] = 'חלקה ' . $burial['plotNameHe'];
if ($burial['lineNameHe']) $graveLocation[] = 'שורה ' . $burial['lineNameHe'];
$graveLocationStr = implode(' / ', $graveLocation) ?: '-';

$genderNames = ['male' => 'זכר', 'female' => 'נקבה', 'M' => 'זכר', 'F' => 'נקבה', 1 => 'זכר', 2 => 'נקבה'];
$genderName = $genderNames[$burial['deceasedGender'] ?? ''] ?? '-';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כרטיס קבורה - <?= $deceasedName ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="sortable-sections" id="burialCardSortableSections">
        <!-- פרטי קבורה -->
        <div class="sortable-section section-burial section-orange">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-cross"></i> קבורה #<?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></span>
            </div>
            <div class="section-content">
                <div class="card-header-row burial">
                    <h2><i class="fas fa-cross"></i> קבורה #<?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></h2>
                    <span class="status-badge" style="background: <?= $statusColor ?>"><?= $statusName ?></span>
                </div>
                <div class="info-grid">
                    <div class="info-card"><div class="label">מספר קבורה</div><div class="value"><?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">תאריך פטירה</div><div class="value"><?= formatHebrewDate($burial['dateDeath']) ?></div></div>
                    <div class="info-card"><div class="label">תאריך קבורה</div><div class="value"><?= formatHebrewDate($burial['dateBurial']) ?></div></div>
                    <div class="info-card"><div class="label">שעת קבורה</div><div class="value"><?= htmlspecialchars($burial['timeBurial'] ?? '-') ?></div></div>
                </div>
                <div class="card-actions"><?php if (hasModulePermission('burials', 'edit')): ?><button class="btn btn-warning" onclick="editBurial('<?= $burial['unicId'] ?>')"><i class="fas fa-edit"></i> ערוך קבורה</button><?php endif; ?></div>
            </div>
        </div>

        <!-- פרטי הנפטר -->
        <div class="sortable-section section-deceased section-purple">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-user"></i> פרטי הנפטר/ת</span>
            </div>
            <div class="section-content">
                <div class="card-header-row customer">
                    <h2><i class="fas fa-user"></i> <?= $deceasedName ?></h2>
                </div>
                <div class="info-grid">
                    <div class="info-card span-2"><div class="label">שם מלא</div><div class="value value-lg"><?= $deceasedName ?></div></div>
                    <div class="info-card"><div class="label">ת.ז.</div><div class="value"><?= htmlspecialchars($burial['deceasedNumId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">מגדר</div><div class="value"><?= $genderName ?></div></div>
                    <div class="info-card"><div class="label">תאריך לידה</div><div class="value"><?= formatHebrewDate($burial['deceasedDateBirth']) ?></div></div>
                    <div class="info-card"><div class="label">שם האב</div><div class="value"><?= htmlspecialchars($burial['deceasedNameFather'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">שם האם</div><div class="value"><?= htmlspecialchars($burial['deceasedNameMother'] ?? '-') ?></div></div>
                </div>
                <?php if ($burial['clientId']): ?>
                <div class="card-actions"><button class="btn btn-purple" onclick="viewCustomer('<?= $burial['clientId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס לקוח</button></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- פרטי הקבר -->
        <div class="sortable-section section-grave section-green">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-monument"></i> פרטי הקבר</span>
            </div>
            <div class="section-content">
                <div class="card-header-row grave">
                    <h2><i class="fas fa-monument"></i> <?= htmlspecialchars($burial['graveNameHe'] ?? 'קבר') ?></h2>
                </div>
                <div class="info-grid">
                    <div class="info-card span-2"><div class="label">מיקום מלא</div><div class="value"><?= $graveLocationStr ?></div></div>
                    <div class="info-card"><div class="label">בית עלמין</div><div class="value"><?= htmlspecialchars($burial['cemeteryNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">גוש</div><div class="value"><?= htmlspecialchars($burial['blockNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">חלקה</div><div class="value"><?= htmlspecialchars($burial['plotNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">שורה</div><div class="value"><?= htmlspecialchars($burial['lineNameHe'] ?? '-') ?></div></div>
                </div>
                <?php if ($burial['graveUnicId']): ?>
                <div class="card-actions"><button class="btn btn-success" onclick="viewGrave('<?= $burial['graveUnicId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס קבר</button></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- פרטי הרכישה -->
        <?php if ($burial['purchaseUnicId']): ?>
        <div class="sortable-section section-purchase section-blue">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-shopping-cart"></i> פרטי הרכישה</span>
            </div>
            <div class="section-content">
                <div class="card-header-row purchase">
                    <h2><i class="fas fa-shopping-cart"></i> רכישה #<?= htmlspecialchars($burial['serialPurchaseId'] ?? '-') ?></h2>
                    <?php $pStatus = $burial['purchaseStatus'] ?? 1; $pStatusName = $purchaseStatusNames[$pStatus] ?? 'לא ידוע'; $pStatusColor = $purchaseStatusColors[$pStatus] ?? '#64748b'; ?>
                    <span class="status-badge" style="background: <?= $pStatusColor ?>"><?= $pStatusName ?></span>
                </div>
                <div class="info-grid">
                    <div class="info-card"><div class="label">מספר רכישה</div><div class="value"><?= htmlspecialchars($burial['serialPurchaseId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">תאריך פתיחה</div><div class="value"><?= formatHebrewDate($burial['purchaseDateOpening']) ?></div></div>
                    <div class="info-card"><div class="label">מחיר</div><div class="value value-lg"><?= formatPrice($burial['purchasePrice']) ?></div></div>
                    <div class="info-card"><div class="label">שם הרוכש</div><div class="value"><?= htmlspecialchars($burial['purchaserFullNameHe'] ?? '-') ?></div></div>
                </div>
                <div class="card-actions"><button class="btn btn-primary" onclick="viewPurchase('<?= $burial['purchaseUnicId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס רכישה</button></div>
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
                <div id="burialExplorer" class="min-h-200">
                    <div class="loading-state lg">
                        <i class="fas fa-spinner fa-spin"></i>
                        טוען סייר קבצים...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const burialId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') PopupAPI.setTitle('כרטיס קבורה - <?= addslashes($deceasedName) ?>');
            initFileExplorer();
        });

        function initFileExplorer() {
            if (!burialId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.burialExplorer = new FileExplorer('burialExplorer', burialId, {});
                    window.explorer = window.burialExplorer;
                } else {
                    document.getElementById('burialExplorer').innerHTML = '<div class="error-state lg"><i class="fas fa-exclamation-triangle"></i>שגיאה בטעינת סייר הקבצים</div>';
                }
            };
            script.onerror = function() {
                document.getElementById('burialExplorer').innerHTML = '<div class="error-state lg"><i class="fas fa-exclamation-triangle"></i>שגיאה בטעינת סייר הקבצים</div>';
            };
            document.head.appendChild(script);
        }

        function toggleSection(btn) { btn.closest('.sortable-section').classList.toggle('collapsed'); }
        function editBurial(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({ id: 'burialForm-' + id, type: 'iframe', src: '/dashboard/dashboards/cemeteries/forms/iframe/burialForm-iframe.php?itemId=' + id, title: 'עריכת קבורה', width: 900, height: 700 });
            }
        }
        function viewCustomer(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({ id: 'customerCard-' + id, type: 'iframe', src: '/dashboard/dashboards/cemeteries/forms/iframe/customerCard-iframe.php?itemId=' + id, title: 'כרטיס לקוח', width: 1000, height: 700 });
            }
        }
        function viewGrave(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({ id: 'graveCard-' + id, type: 'iframe', src: '/dashboard/dashboards/cemeteries/forms/iframe/graveCard-iframe.php?itemId=' + id, title: 'כרטיס קבר', width: 1200, height: 700 });
            }
        }
        function viewPurchase(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({ id: 'purchaseCard-' + id, type: 'iframe', src: '/dashboard/dashboards/cemeteries/forms/iframe/purchaseCard-iframe.php?itemId=' + id, title: 'כרטיס רכישה', width: 1200, height: 700 });
            }
        }
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('burialCardSortableSections', 'burialCard');
            }
        });
    </script>
</body>
</html>
