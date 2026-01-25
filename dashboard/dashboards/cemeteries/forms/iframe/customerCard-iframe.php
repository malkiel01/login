<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/customerCard-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: כרטיס לקוח - דף עצמאי לטעינה ב-iframe (פופאפ גנרי)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;

if (!$itemId) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: מזהה לקוח חסר</body></html>');
}

try {
    $conn = getDBConnection();

    // שליפת נתוני הלקוח
    $stmt = $conn->prepare("
        SELECT c.*, c.countryNameHe, c.cityNameHe
        FROM customers c
        WHERE c.unicId = :id AND c.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הלקוח לא נמצא</body></html>');
    }

    // שליפת רכישות של הלקוח
    $stmt = $conn->prepare("
        SELECT p.*, g.graveNameHe,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe
        FROM purchases p
        LEFT JOIN graves g ON p.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE p.clientId = :customerId AND p.isActive = 1
        ORDER BY p.dateOpening DESC
    ");
    $stmt->execute(['customerId' => $customer['unicId']]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // שליפת קבורות של הלקוח (כנפטר)
    $stmt = $conn->prepare("
        SELECT b.*, g.graveNameHe,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe
        FROM burials b
        LEFT JOIN graves g ON b.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE b.clientId = :customerId AND b.isActive = 1
        ORDER BY b.dateBurial DESC
    ");
    $stmt->execute(['customerId' => $customer['unicId']]);
    $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
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

function formatPhone($phone) {
    if (!$phone) return '-';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        return substr($phone, 0, 3) . '-' . substr($phone, 3);
    }
    return $phone;
}

// סטטוסים
$statusNames = [1 => 'פעיל', 2 => 'רוכש', 3 => 'נפטר'];
$statusColors = [1 => '#22c55e', 2 => '#3b82f6', 3 => '#64748b'];
$currentStatus = $customer['statusCustomer'] ?? 1;
$statusName = $statusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $statusColors[$currentStatus] ?? '#64748b';

$genderNames = [1 => 'זכר', 2 => 'נקבה'];
$genderName = $genderNames[$customer['gender'] ?? 0] ?? '-';

$fullName = htmlspecialchars($customer['fullNameHe'] ?? ($customer['firstName'] . ' ' . $customer['lastName']));

$purchaseCount = count($purchases);
$burialCount = count($burials);

$purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
$purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כרטיס לקוח - <?= $fullName ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">

    <!-- Popup API -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>

</head>
<body>
    <div class="sortable-sections" id="customerSortableSections">

        <!-- סקשן 1: פרטי לקוח -->
        <div class="sortable-section section-purple" data-section="details">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title"><i class="fas fa-user"></i> פרטי לקוח</span>
            </div>
            <div class="section-content">
                <div class="header-title">
                    <h2><i class="fas fa-user"></i> <?= $fullName ?></h2>
                    <span class="status-badge" style="background: <?= $statusColor ?>"><?= $statusName ?></span>
                </div>

                <div class="info-grid">
                    <div class="info-card"><div class="label">ת.ז.</div><div class="value"><?= htmlspecialchars($customer['numId'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">מגדר</div><div class="value"><?= $genderName ?></div></div>
                    <div class="info-card"><div class="label">תאריך לידה</div><div class="value"><?= formatHebrewDate($customer['dateBirth']) ?></div></div>
                    <div class="info-card"><div class="label">שם האב</div><div class="value"><?= htmlspecialchars($customer['nameFather'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">שם האם</div><div class="value"><?= htmlspecialchars($customer['nameMother'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">טלפון</div><div class="value"><?= formatPhone($customer['phone']) ?></div></div>
                    <div class="info-card"><div class="label">טלפון נייד</div><div class="value"><?= formatPhone($customer['phoneMobile']) ?></div></div>
                    <div class="info-card"><div class="label">כתובת</div><div class="value"><?= htmlspecialchars($customer['address'] ?? '-') ?></div></div>
                </div>

                <div style="margin-top: 15px;">
                    <button class="btn btn-primary" onclick="editCustomer('<?= $customer['unicId'] ?>')">
                        <i class="fas fa-edit"></i> ערוך לקוח
                    </button>
                </div>
            </div>
        </div>

        <!-- סקשן 2: תיקי רכישה -->
        <div class="sortable-section section-blue" data-section="purchases">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title">
                    <i class="fas fa-shopping-cart"></i> תיקי רכישה
                    <span class="status-badge" style="background: #3b82f6"><?= $purchaseCount ?></span>
                </span>
            </div>
            <div class="section-content">
                <?php if ($purchaseCount > 0): ?>
                    <?php foreach ($purchases as $purchase):
                        $pStatusName = $purchaseStatusNames[$purchase['purchaseStatus']] ?? 'לא ידוע';
                        $pStatusColor = $purchaseStatusColors[$purchase['purchaseStatus']] ?? '#64748b';
                    ?>
                    <div class="purchase-card">
                        <div class="card-header">
                            <span style="font-weight: 600;">
                                <i class="fas fa-monument" style="color: #64748b;"></i>
                                <?= htmlspecialchars($purchase['graveNameHe'] ?? 'קבר') ?>
                            </span>
                            <span class="status-badge" style="background: <?= $pStatusColor ?>"><?= $pStatusName ?></span>
                        </div>
                        <div class="card-details">
                            <div><strong>מס׳:</strong> <?= htmlspecialchars($purchase['serialPurchaseId'] ?? '-') ?></div>
                            <div><strong>תאריך:</strong> <?= formatHebrewDate($purchase['dateOpening']) ?></div>
                            <div><strong>מחיר:</strong> <?= formatPrice($purchase['price']) ?></div>
                            <div><strong>מיקום:</strong> <?= htmlspecialchars($purchase['cemeteryNameHe'] ?? '') ?></div>
                        </div>
                        <div style="margin-top: 10px;">
                            <button class="btn btn-outline" onclick="viewPurchase('<?= $purchase['unicId'] ?>')">
                                <i class="fas fa-eye"></i> צפה
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        אין רכישות משויכות ללקוח זה
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- סקשן 3: תיקי קבורה -->
        <div class="sortable-section section-orange" data-section="burials">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title">
                    <i class="fas fa-cross"></i> תיקי קבורה
                    <span class="status-badge" style="background: #f59e0b"><?= $burialCount ?></span>
                </span>
            </div>
            <div class="section-content">
                <?php if ($burialCount > 0): ?>
                    <?php foreach ($burials as $burial): ?>
                    <div class="burial-card">
                        <div class="card-header">
                            <span style="font-weight: 600;">
                                <i class="fas fa-monument" style="color: #64748b;"></i>
                                <?= htmlspecialchars($burial['graveNameHe'] ?? 'קבר') ?>
                            </span>
                        </div>
                        <div class="card-details">
                            <div><strong>מס׳:</strong> <?= htmlspecialchars($burial['serialBurialId'] ?? '-') ?></div>
                            <div><strong>תאריך פטירה:</strong> <?= formatHebrewDate($burial['dateDeath']) ?></div>
                            <div><strong>תאריך קבורה:</strong> <?= formatHebrewDate($burial['dateBurial']) ?></div>
                            <div><strong>מיקום:</strong> <?= htmlspecialchars($burial['cemeteryNameHe'] ?? '') ?></div>
                        </div>
                        <div style="margin-top: 10px;">
                            <button class="btn btn-outline" onclick="viewBurial('<?= $burial['unicId'] ?>')">
                                <i class="fas fa-eye"></i> צפה
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        אין קבורות משויכות ללקוח זה
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- סקשן 4: מסמכים -->
        <div class="sortable-section section-gray" data-section="documents">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
            </div>
            <div class="section-content">
                <div id="customerExplorer" style="min-height: 300px;">
                    <div style="text-align: center; color: #94a3b8; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        <span>טוען סייר קבצים...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const customerId = '<?= addslashes($itemId ?? '') ?>';

        // עדכון כותרת הפופאפ
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('כרטיס לקוח - <?= addslashes($fullName) ?>');
            }
            // טעינת סייר מסמכים
            initFileExplorer();
        });

        function initFileExplorer() {
            if (!customerId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.customerExplorer = new FileExplorer('customerExplorer', customerId, {});
                    window.explorer = window.customerExplorer;
                } else {
                    document.getElementById('customerExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
                }
            };
            script.onerror = function() {
                document.getElementById('customerExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
            };
            document.head.appendChild(script);
        }

        // Toggle section
        function toggleSection(btn) {
            const section = btn.closest('.sortable-section');
            section.classList.toggle('collapsed');
        }

        // פונקציות פעולה - שליחה לחלון ההורה
        function editCustomer(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({
                    id: 'customerForm-' + id,
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/forms/iframe/customerForm-iframe.php?itemId=' + id,
                    title: 'עריכת לקוח',
                    width: 900,
                    height: 700
                });
            }
        }

        function viewPurchase(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({
                    id: 'purchaseCard-' + id,
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/forms/iframe/purchaseCard-iframe.php?itemId=' + id,
                    title: 'כרטיס רכישה',
                    width: 1200,
                    height: 700
                });
            }
        }

        function viewBurial(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({
                    id: 'burialCard-' + id,
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/forms/iframe/burialCard-iframe.php?itemId=' + id,
                    title: 'כרטיס קבורה',
                    width: 1200,
                    height: 700
                });
            }
        }
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('customerSortableSections', 'customerCard');
            }
        });
    </script>
</body>
</html>
