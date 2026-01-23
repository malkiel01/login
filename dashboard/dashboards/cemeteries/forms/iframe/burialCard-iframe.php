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
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
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
        .btn-warning { background: #f59e0b; color: white; }
        .btn-purple { background: #7c3aed; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-primary { background: #3b82f6; color: white; }
        .empty-state { text-align: center; padding: 30px; color: #94a3b8; }
        .empty-state i { font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5; }

        .section-burial .info-card { border: 1px solid #fde68a; }
        .section-burial .info-card .value { color: #92400e; }
        .section-deceased .info-card { border: 1px solid #c4b5fd; }
        .section-deceased .info-card .value { color: #5b21b6; }
        .section-grave .info-card { border: 1px solid #bbf7d0; }
        .section-grave .info-card .value { color: #166534; }
        .section-purchase .info-card { border: 1px solid #bfdbfe; }
        .section-purchase .info-card .value { color: #1e40af; }
    </style>
</head>
<body>
    <div class="sortable-sections" id="burialCardSortableSections">
        <!-- פרטי קבורה -->
        <div class="sortable-section section-burial">
            <div class="section-drag-handle" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title" style="color: #92400e;"><i class="fas fa-cross"></i> קבורה #<?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></span>
            </div>
            <div class="section-content" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <h2 style="margin: 0; color: #92400e;"><i class="fas fa-cross"></i> קבורה #<?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></h2>
                    <span class="status-badge" style="background: <?= $statusColor ?>"><?= $statusName ?></span>
                </div>
                <div class="info-grid">
                    <div class="info-card"><div class="label">מספר קבורה</div><div class="value"><?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">תאריך פטירה</div><div class="value"><?= formatHebrewDate($burial['dateDeath']) ?></div></div>
                    <div class="info-card"><div class="label">תאריך קבורה</div><div class="value"><?= formatHebrewDate($burial['dateBurial']) ?></div></div>
                    <div class="info-card"><div class="label">שעת קבורה</div><div class="value"><?= htmlspecialchars($burial['timeBurial'] ?? '-') ?></div></div>
                </div>
                <div style="margin-top: 15px;"><button class="btn btn-warning" onclick="editBurial('<?= $burial['unicId'] ?>')"><i class="fas fa-edit"></i> ערוך קבורה</button></div>
            </div>
        </div>

        <!-- פרטי הנפטר -->
        <div class="sortable-section section-deceased">
            <div class="section-drag-handle" style="background: linear-gradient(135deg, #ede9fe, #c4b5fd);">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title" style="color: #5b21b6;"><i class="fas fa-user"></i> פרטי הנפטר/ת</span>
            </div>
            <div class="section-content" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <h2 style="margin: 0; color: #5b21b6;"><i class="fas fa-user"></i> <?= $deceasedName ?></h2>
                </div>
                <div class="info-grid">
                    <div class="info-card span-2"><div class="label">שם מלא</div><div class="value" style="font-size: 16px;"><?= $deceasedName ?></div></div>
                    <div class="info-card"><div class="label">ת.ז.</div><div class="value"><?= htmlspecialchars($burial['deceasedNumId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">מגדר</div><div class="value"><?= $genderName ?></div></div>
                    <div class="info-card"><div class="label">תאריך לידה</div><div class="value"><?= formatHebrewDate($burial['deceasedDateBirth']) ?></div></div>
                    <div class="info-card"><div class="label">שם האב</div><div class="value"><?= htmlspecialchars($burial['deceasedNameFather'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">שם האם</div><div class="value"><?= htmlspecialchars($burial['deceasedNameMother'] ?? '-') ?></div></div>
                </div>
                <?php if ($burial['clientId']): ?>
                <div style="margin-top: 15px;"><button class="btn btn-purple" onclick="viewCustomer('<?= $burial['clientId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס לקוח</button></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- פרטי הקבר -->
        <div class="sortable-section section-grave">
            <div class="section-drag-handle" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title" style="color: #166534;"><i class="fas fa-monument"></i> פרטי הקבר</span>
            </div>
            <div class="section-content" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <h2 style="margin: 0; color: #166534;"><i class="fas fa-monument"></i> <?= htmlspecialchars($burial['graveNameHe'] ?? 'קבר') ?></h2>
                </div>
                <div class="info-grid">
                    <div class="info-card span-2"><div class="label">מיקום מלא</div><div class="value"><?= $graveLocationStr ?></div></div>
                    <div class="info-card"><div class="label">בית עלמין</div><div class="value"><?= htmlspecialchars($burial['cemeteryNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">גוש</div><div class="value"><?= htmlspecialchars($burial['blockNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">חלקה</div><div class="value"><?= htmlspecialchars($burial['plotNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">שורה</div><div class="value"><?= htmlspecialchars($burial['lineNameHe'] ?? '-') ?></div></div>
                </div>
                <?php if ($burial['graveUnicId']): ?>
                <div style="margin-top: 15px;"><button class="btn btn-success" onclick="viewGrave('<?= $burial['graveUnicId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס קבר</button></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- פרטי הרכישה -->
        <?php if ($burial['purchaseUnicId']): ?>
        <div class="sortable-section section-purchase">
            <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title" style="color: #1e40af;"><i class="fas fa-shopping-cart"></i> פרטי הרכישה</span>
            </div>
            <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <h2 style="margin: 0; color: #1e40af;"><i class="fas fa-shopping-cart"></i> רכישה #<?= htmlspecialchars($burial['serialPurchaseId'] ?? '-') ?></h2>
                    <?php $pStatus = $burial['purchaseStatus'] ?? 1; $pStatusName = $purchaseStatusNames[$pStatus] ?? 'לא ידוע'; $pStatusColor = $purchaseStatusColors[$pStatus] ?? '#64748b'; ?>
                    <span class="status-badge" style="background: <?= $pStatusColor ?>"><?= $pStatusName ?></span>
                </div>
                <div class="info-grid">
                    <div class="info-card"><div class="label">מספר רכישה</div><div class="value"><?= htmlspecialchars($burial['serialPurchaseId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">תאריך פתיחה</div><div class="value"><?= formatHebrewDate($burial['purchaseDateOpening']) ?></div></div>
                    <div class="info-card"><div class="label">מחיר</div><div class="value" style="font-size: 16px;"><?= formatPrice($burial['purchasePrice']) ?></div></div>
                    <div class="info-card"><div class="label">שם הרוכש</div><div class="value"><?= htmlspecialchars($burial['purchaserFullNameHe'] ?? '-') ?></div></div>
                </div>
                <div style="margin-top: 15px;"><button class="btn btn-primary" onclick="viewPurchase('<?= $burial['purchaseUnicId'] ?>')"><i class="fas fa-eye"></i> צפה בכרטיס רכישה</button></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- מסמכים -->
        <div class="sortable-section">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)"><i class="fas fa-chevron-down"></i></button>
                <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
            </div>
            <div class="section-content">
                <div id="burialExplorer" style="min-height: 200px;">
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
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
                    document.getElementById('burialExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>שגיאה בטעינת סייר הקבצים</div>';
                }
            };
            script.onerror = function() {
                document.getElementById('burialExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>שגיאה בטעינת סייר הקבצים</div>';
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
