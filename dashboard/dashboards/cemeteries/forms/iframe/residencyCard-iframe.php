<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/residencyCard-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-20
 * Author: Malkiel
 * Description: כרטיס חוק תושבות - דף עצמאי לטעינה ב-iframe (פופאפ גנרי)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;

if (!$itemId) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: מזהה חוק תושבות חסר</body></html>');
}

try {
    $conn = getDBConnection();

    // שליפת נתוני חוק התושבות
    $stmt = $conn->prepare("
        SELECT r.*,
            c.countryNameHe,
            ct.cityNameHe
        FROM residency_settings r
        LEFT JOIN countries c ON r.countryId = c.unicId
        LEFT JOIN cities ct ON r.cityId = ct.unicId
        WHERE r.unicId = :id AND r.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $residency = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$residency) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: חוק התושבות לא נמצא</body></html>');
    }

} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

// פונקציות עזר
function formatHebrewDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

// מיפויים
$residencyTypes = [
    1 => 'תושב העיר',
    2 => 'תושב חוץ לעיר',
    3 => 'תושב חו"ל'
];

$residencyTypeColors = [
    1 => '#10b981', // ירוק
    2 => '#3b82f6', // כחול
    3 => '#f59e0b'  // כתום
];

$residencyTypeIcons = [
    1 => 'fa-home',
    2 => 'fa-car',
    3 => 'fa-plane'
];

$residencyType = $residency['residencyType'] ?? 0;
$residencyTypeName = $residencyTypes[$residencyType] ?? 'לא מוגדר';
$residencyTypeColor = $residencyTypeColors[$residencyType] ?? '#64748b';
$residencyTypeIcon = $residencyTypeIcons[$residencyType] ?? 'fa-question';

$residencyName = htmlspecialchars($residency['residencyName'] ?? 'חוק תושבות');

// מיקום
$locationParts = [];
if (!empty($residency['countryNameHe'])) $locationParts[] = $residency['countryNameHe'];
if (!empty($residency['cityNameHe'])) $locationParts[] = $residency['cityNameHe'];
$locationStr = implode(' - ', $locationParts) ?: 'כל המיקומים';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כרטיס חוק תושבות - <?= $residencyName ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">

    <!-- Popup API -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>

</head>
<body>
    <div class="sortable-sections" id="residencySortableSections">

        <!-- סקשן 1: פרטי חוק תושבות -->
        <div class="sortable-section section-blue section-residency" data-section="details">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title"><i class="fas fa-balance-scale"></i> פרטי חוק תושבות</span>
            </div>
            <div class="section-content">
                <div class="header-title">
                    <h2><i class="fas fa-balance-scale"></i> <?= $residencyName ?></h2>
                    <span class="status-badge" style="background: <?= $residencyTypeColor ?>">
                        <i class="fas <?= $residencyTypeIcon ?>"></i> <?= $residencyTypeName ?>
                    </span>
                </div>

                <div class="type-box">
                    <div class="type-icon"><i class="fas <?= $residencyTypeIcon ?>"></i></div>
                    <div class="type-label">סוג תושבות</div>
                    <div class="type-value"><?= $residencyTypeName ?></div>
                </div>

                <div class="info-grid">
                    <div class="info-card"><div class="label">שם חוק התושבות</div><div class="value"><?= $residencyName ?></div></div>
                    <div class="info-card"><div class="label">סוג תושבות</div><div class="value"><?= $residencyTypeName ?></div></div>
                    <div class="info-card"><div class="label">מדינה</div><div class="value"><?= htmlspecialchars($residency['countryNameHe'] ?? '-') ?></div></div>
                    <div class="info-card"><div class="label">עיר</div><div class="value"><?= htmlspecialchars($residency['cityNameHe'] ?? '-') ?></div></div>
                    <div class="info-card span-2">
                        <div class="label">מיקום מלא</div>
                        <div class="value"><i class="fas fa-map-marker-alt" style="margin-left: 5px;"></i><?= htmlspecialchars($locationStr) ?></div>
                    </div>
                </div>

                <?php if (!empty($residency['description'])): ?>
                <div class="info-grid" style="margin-top: 15px;">
                    <div class="info-card span-2">
                        <div class="label">תיאור</div>
                        <div class="value" style="font-weight: normal; white-space: pre-wrap;"><?= htmlspecialchars($residency['description']) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-grid" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #bfdbfe;">
                    <div class="info-card"><div class="label">תאריך יצירה</div><div class="value"><?= formatHebrewDate($residency['createDate']) ?></div></div>
                    <div class="info-card"><div class="label">תאריך עדכון</div><div class="value"><?= formatHebrewDate($residency['updateDate']) ?></div></div>
                </div>

                <div style="margin-top: 15px;">
                    <button class="btn btn-primary" onclick="editResidency('<?= $residency['unicId'] ?>')">
                        <i class="fas fa-edit"></i> ערוך חוק תושבות
                    </button>
                </div>
            </div>
        </div>

        <!-- סקשן 2: מסמכים -->
        <div class="sortable-section section-gray" data-section="documents">
            <div class="section-drag-handle">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
            </div>
            <div class="section-content">
                <div id="residencyExplorer" style="min-height: 200px;">
                    <div style="text-align: center; color: #94a3b8; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        <span>טוען סייר קבצים...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const residencyId = '<?= addslashes($itemId ?? '') ?>';

        // עדכון כותרת הפופאפ
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('כרטיס חוק תושבות - <?= addslashes($residencyName) ?>');
            }
            // טעינת סייר מסמכים
            initFileExplorer();
        });

        function initFileExplorer() {
            if (!residencyId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.residencyExplorer = new FileExplorer('residencyExplorer', residencyId, {});
                    window.explorer = window.residencyExplorer;
                } else {
                    document.getElementById('residencyExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
                }
            };
            script.onerror = function() {
                document.getElementById('residencyExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
            };
            document.head.appendChild(script);
        }

        // Toggle section
        function toggleSection(btn) {
            const section = btn.closest('.sortable-section');
            section.classList.toggle('collapsed');
        }

        // עריכת חוק תושבות
        function editResidency(id) {
            if (window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.create({
                    id: 'residencyForm-' + id,
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/forms/iframe/residencyForm-iframe.php?itemId=' + id,
                    title: 'עריכת חוק תושבות',
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
                SortableSections.init('residencySortableSections', 'residencyCard');
            }
        });
    </script>
</body>
</html>
