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
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">

    <!-- Popup API -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: #334155;
            padding: 20px;
            direction: rtl;
        }
        .sortable-sections { display: flex; flex-direction: column; gap: 15px; }
        .sortable-section {
            background: white;
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.2s;
            overflow: hidden;
        }
        .sortable-section:hover { border-color: #94a3b8; }
        .section-drag-handle {
            height: 32px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #cbd5e1;
            position: relative;
        }
        .section-drag-handle::before {
            content: "";
            width: 40px;
            height: 4px;
            background: #94a3b8;
            border-radius: 2px;
        }
        .section-toggle-btn {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border: none;
            background: rgba(100, 116, 139, 0.2);
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 12px;
        }
        .section-toggle-btn:hover { background: rgba(100, 116, 139, 0.4); }
        .section-title {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        .section-content { padding: 20px; }
        .sortable-section.collapsed .section-content { display: none; }
        .sortable-section.collapsed .section-toggle-btn i { transform: rotate(-90deg); }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .info-card {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .info-card .label { font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .info-card .value { font-weight: 600; color: #334155; }
        .info-card.span-2 { grid-column: span 2; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .header-title h2 { margin: 0; font-size: 18px; color: #1e293b; }

        .section-residency .info-card { border: 1px solid #bfdbfe; }
        .section-residency .info-card .value { color: #1e40af; }

        .type-box {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid <?= $residencyTypeColor ?>;
        }
        .type-box .type-icon { font-size: 36px; margin-bottom: 10px; color: <?= $residencyTypeColor ?>; }
        .type-box .type-label { font-size: 14px; color: #64748b; margin-bottom: 5px; }
        .type-box .type-value { font-size: 24px; font-weight: 700; color: <?= $residencyTypeColor ?>; }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="sortable-sections" id="residencySortableSections">

        <!-- סקשן 1: פרטי חוק תושבות -->
        <div class="sortable-section section-residency" data-section="details">
            <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
                <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <span class="section-title" style="color: #1e40af;"><i class="fas fa-balance-scale"></i> פרטי חוק תושבות</span>
            </div>
            <div class="section-content" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
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
        <div class="sortable-section" data-section="documents">
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
