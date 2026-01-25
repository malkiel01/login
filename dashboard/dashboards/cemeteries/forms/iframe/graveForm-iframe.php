<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/graveForm-iframe.php
 * Version: 1.0.1
 * Updated: 2026-01-21
 * Author: Malkiel
 * Description: טופס הוספה/עריכה של קבר - דף עצמאי לטעינה ב-iframe (פופאפ גנרי)
 * Changes:
 * - v1.0.1: הוספת אחוזת קבר לתצוגת היררכיה + סטטוס קריאה בלבד
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null; // areaGraveId
$popupId = $_GET['popupId'] ?? null;

$grave = null;
$areaGraves = [];

try {
    $conn = getDBConnection();

    // טען קבר קיים אם בעריכה
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT g.*, agv.areaGraveNameHe,
                agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe
            FROM graves g
            LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
            WHERE g.unicId = :id AND g.isActive = 1
        ");
        $stmt->execute(['id' => $itemId]);
        $grave = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($grave && !$parentId) {
            $parentId = $grave['areaGraveId'];
        }
    }

    // טען אחוזות קבר לבחירה
    $stmt = $conn->query("
        SELECT ag.unicId, ag.areaGraveNameHe,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe
        FROM areaGraves ag
        LEFT JOIN areaGraves_view agv ON ag.unicId = agv.unicId
        WHERE ag.isActive = 1
        ORDER BY agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $location = [];
        if ($row['cemeteryNameHe']) $location[] = $row['cemeteryNameHe'];
        if ($row['blockNameHe']) $location[] = 'גוש ' . $row['blockNameHe'];
        if ($row['plotNameHe']) $location[] = 'חלקה ' . $row['plotNameHe'];
        if ($row['lineNameHe']) $location[] = 'שורה ' . $row['lineNameHe'];
        $locationStr = implode(' / ', $location);

        $areaGraves[$row['unicId']] = [
            'name' => $row['areaGraveNameHe'],
            'location' => $locationStr
        ];
    }

} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$isEdit = !empty($grave);
$pageTitle = $isEdit ? 'עריכת קבר' : 'הוספת קבר חדש';

// מיפויים
$plotTypes = [
    '' => '-- בחר סוג חלקה --',
    1 => 'פטורה',
    2 => 'חריגה',
    3 => 'סגורה'
];

$graveStatuses = [
    1 => 'פנוי',
    2 => 'נרכש',
    3 => 'קבור',
    4 => 'שמור'
];

$graveLocations = [
    '' => '-- בחר מיקום --',
    1 => 'עליון',
    2 => 'תחתון',
    3 => 'אמצעי'
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">

    <!-- Popup API -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: #334155;
            padding: 20px;
            direction: rtl;
        }

        /* Form styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .form-group { display: flex; flex-direction: column; }
        .form-group.span-2 { grid-column: span 2; }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }
        .form-group label .required { color: #ef4444; margin-right: 2px; }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        .form-group input:disabled,
        .form-group select:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input.error,
        .form-group select.error { border-color: #ef4444; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-purple { background: #7c3aed; color: white; }
        .btn-purple:hover { background: #6d28d9; }
        .btn-purple:disabled { background: #a78bfa; cursor: not-allowed; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .location-display {
            padding: 10px 12px;
            background: #f5f3ff;
            border: 1px solid #c4b5fd;
            border-radius: 8px;
            font-size: 13px;
            color: #5b21b6;
        }
    </style>
</head>
<body>
    <div id="alertContainer">
        <div id="successAlert" class="alert alert-success"></div>
        <div id="errorAlert" class="alert alert-error"></div>
    </div>

    <form id="graveForm">
        <input type="hidden" name="unicId" value="<?= htmlspecialchars($grave['unicId'] ?? '') ?>">

        <div class="sortable-sections" id="graveSortableSections">
            <!-- פרטי הקבר -->
            <div class="sortable-section section-purple">
                <div class="section-drag-handle">
                    <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <span class="section-title"><i class="fas fa-monument"></i> פרטי הקבר</span>
                </div>
                <div class="section-content">
                    <div class="form-grid">
                        <?php if ($isEdit): ?>
                        <!-- במצב עריכה - הצג רק את המיקום המלא (כולל אחוזת קבר) -->
                        <input type="hidden" name="areaGraveId" value="<?= htmlspecialchars($grave['areaGraveId'] ?? $parentId ?? '') ?>">
                        <div class="form-group span-2">
                            <label>מיקום נוכחי</label>
                            <div class="location-display">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php
                                $locationParts = [];
                                if (!empty($grave['cemeteryNameHe'])) $locationParts[] = $grave['cemeteryNameHe'];
                                if (!empty($grave['blockNameHe'])) $locationParts[] = 'גוש ' . $grave['blockNameHe'];
                                if (!empty($grave['plotNameHe'])) $locationParts[] = 'חלקה ' . $grave['plotNameHe'];
                                if (!empty($grave['lineNameHe'])) $locationParts[] = 'שורה ' . $grave['lineNameHe'];
                                if (!empty($grave['areaGraveNameHe'])) $locationParts[] = '<strong>אחוזת קבר ' . htmlspecialchars($grave['areaGraveNameHe']) . '</strong>';
                                echo implode(' ← ', $locationParts);
                                ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- במצב הוספה - הצג בחירת אחוזת קבר -->
                        <div class="form-group span-2">
                            <label><span class="required">*</span> אחוזת קבר</label>
                            <select name="areaGraveId" id="areaGraveId" required <?= !empty($parentId) ? 'disabled' : '' ?>>
                                <option value="">-- בחר אחוזת קבר --</option>
                                <?php foreach ($areaGraves as $id => $data): ?>
                                    <option value="<?= htmlspecialchars($id) ?>"
                                            data-location="<?= htmlspecialchars($data['location']) ?>"
                                            <?= ($parentId == $id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($data['name']) ?> - <?= htmlspecialchars($data['location']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($parentId)): ?>
                                <input type="hidden" name="areaGraveId" value="<?= htmlspecialchars($parentId) ?>">
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label><span class="required">*</span> שם קבר</label>
                            <input type="text" name="graveNameHe" id="graveNameHe" required
                                   placeholder="הזן שם קבר"
                                   value="<?= htmlspecialchars($grave['graveNameHe'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label><span class="required">*</span> סוג חלקה</label>
                            <select name="plotType" id="plotType" required>
                                <?php foreach ($plotTypes as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>"
                                            <?= ($grave['plotType'] ?? '') == $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($isEdit): ?>
                        <div class="form-group">
                            <label>סטטוס קבר</label>
                            <input type="text" value="<?= htmlspecialchars($graveStatuses[$grave['graveStatus'] ?? 1] ?? 'לא ידוע') ?>" disabled readonly
                                   style="background: #f1f5f9; color: #64748b;">
                            <small style="color: #94a3b8; font-size: 11px;">מנוהל אוטומטית ע"י רכישה/קבורה</small>
                        </div>
                        <?php endif; ?>
                        <!-- ביצירה - הסטטוס יהיה פנוי (1) אוטומטית -->

                        <div class="form-group">
                            <label>מיקום בשורה</label>
                            <select name="graveLocation" id="graveLocation">
                                <?php foreach ($graveLocations as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>"
                                            <?= ($grave['graveLocation'] ?? '') == $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>קבר קטן</label>
                            <select name="isSmallGrave" id="isSmallGrave">
                                <option value="0" <?= ($grave['isSmallGrave'] ?? 0) == 0 ? 'selected' : '' ?>>לא</option>
                                <option value="1" <?= ($grave['isSmallGrave'] ?? 0) == 1 ? 'selected' : '' ?>>כן</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>עלות בנייה</label>
                            <input type="number" name="constructionCost" id="constructionCost"
                                   placeholder="הזן עלות"
                                   min="0" step="0.01"
                                   value="<?= htmlspecialchars($grave['constructionCost'] ?? '') ?>">
                        </div>

                        <div class="form-group span-2">
                            <label>הערות</label>
                            <textarea name="comments" id="comments" rows="3"
                                      placeholder="הערות נוספות..."><?= htmlspecialchars($grave['comments'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <!-- מסמכים -->
            <div class="sortable-section section-gray">
                <div class="section-drag-handle">
                    <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
                </div>
                <div class="section-content">
                    <div id="graveExplorer" style="min-height: 200px;">
                        <div style="text-align: center; color: #94a3b8; padding: 40px;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                            <span>טוען סייר קבצים...</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-purple" id="submitBtn">
                <i class="fas fa-save"></i>
                <?= $isEdit ? 'שמור שינויים' : 'הוסף קבר' ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="closePopup()">
                <i class="fas fa-times"></i> ביטול
            </button>
        </div>
    </form>

    <script>
        const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
        const graveId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            <?php if ($isEdit): ?>
            initFileExplorer();
            <?php endif; ?>
        });

        <?php if ($isEdit): ?>
        function initFileExplorer() {
            if (!graveId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.graveExplorer = new FileExplorer('graveExplorer', graveId, {});
                    window.explorer = window.graveExplorer;
                } else {
                    document.getElementById('graveExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
                }
            };
            script.onerror = function() {
                document.getElementById('graveExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i><span>שגיאה בטעינת סייר הקבצים</span></div>';
            };
            document.head.appendChild(script);
        }
        <?php endif; ?>

        function toggleSection(btn) {
            const section = btn.closest('.sortable-section');
            section.classList.toggle('collapsed');
        }

        function showAlert(type, message) {
            const alert = document.getElementById(type + 'Alert');
            alert.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
            alert.style.display = 'block';

            if (type === 'success') {
                setTimeout(() => { alert.style.display = 'none'; }, 3000);
            }
        }

        function hideAlerts() {
            document.getElementById('successAlert').style.display = 'none';
            document.getElementById('errorAlert').style.display = 'none';
        }

        function closePopup() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.close();
            } else if (window.parent && window.parent.PopupManager) {
                const popupId = new URLSearchParams(window.location.search).get('popupId');
                if (popupId) window.parent.PopupManager.close(popupId);
            }
        }

        // Form submission
        document.getElementById('graveForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            hideAlerts();

            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> שומר...';

            try {
                const formData = new FormData(this);
                const data = {};

                formData.forEach((value, key) => {
                    if (value !== '' && value !== null) {
                        data[key] = value;
                    }
                });

                // Determine API action
                const action = isEdit ? 'update' : 'create';
                let url = '/dashboard/dashboards/cemeteries/api/graves-api.php?action=' + action;
                if (isEdit && graveId) {
                    url += '&id=' + graveId;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', result.message || (isEdit ? 'הקבר עודכן בהצלחה!' : 'הקבר נוסף בהצלחה!'));

                    // Refresh parent table
                    if (window.parent) {
                        if (window.parent.EntityManager && typeof window.parent.EntityManager.refresh === 'function') {
                            window.parent.EntityManager.refresh();
                        }
                        if (window.parent.loadGraves && typeof window.parent.loadGraves === 'function') {
                            window.parent.loadGraves();
                        }
                    }

                    setTimeout(closePopup, 1500);
                } else {
                    showAlert('error', result.error || 'שגיאה בשמירת הנתונים');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }

            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'שגיאה בתקשורת עם השרת');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('graveSortableSections', 'graveForm');
            }
        });
    </script>
</body>
</html>
