<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/areaGraveForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-21
 * Author: Malkiel
 * Description: טופס אחוזת קבר (יצירה/עריכה) עם ניהול קברים - דף עצמאי לטעינה ב-iframe
 * Rules:
 * - מינימום 1 קבר, מקסימום 5
 * - קבר ראשון לא ניתן למחיקה
 * - קברים אחרים ניתן למחוק רק אם סטטוס=פנוי ואין רכישה/קבורה פעילים
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? $_GET['lineId'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$areaGrave = null;
$graves = [];
$rows = [];
$hierarchyPath = null;

try {
    $conn = getDBConnection();

    // טען אחוזת קבר קיימת אם בעריכה
    if ($isEditMode) {
        $stmt = $conn->prepare("
            SELECT ag.*,
                   r.lineNameHe,
                   r.plotId,
                   p.plotNameHe,
                   b.blockNameHe,
                   c.cemeteryNameHe
            FROM areaGraves ag
            LEFT JOIN `rows` r ON ag.lineId = r.unicId
            LEFT JOIN plots p ON r.plotId = p.unicId
            LEFT JOIN blocks b ON p.blockId = b.unicId
            LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
            WHERE ag.unicId = ? AND ag.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $areaGrave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$areaGrave) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: אחוזת הקבר לא נמצאה</body></html>');
        }

        // שמור את ה-lineId כ-parentId
        if (!$parentId) {
            $parentId = $areaGrave['lineId'];
        }

        // טען קברים קיימים עם בדיקת רכישות וקבורות
        $stmt = $conn->prepare("
            SELECT g.*,
                   (SELECT COUNT(*) FROM purchases p WHERE p.graveId = g.unicId AND p.isActive = 1) as hasPurchase,
                   (SELECT COUNT(*) FROM burials b WHERE b.graveId = g.unicId AND b.isActive = 1) as hasBurial
            FROM graves g
            WHERE g.areaGraveId = ? AND g.isActive = 1
            ORDER BY g.id ASC
        ");
        $stmt->execute([$itemId]);
        $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // טען היררכיה אם יש parentId - ואמת שהוא קיים בטבלת rows
    $validatedLineId = null;
    if ($parentId) {
        $stmt = $conn->prepare("
            SELECT
                r.unicId,
                r.lineNameHe,
                r.plotId,
                p.plotNameHe,
                b.blockNameHe,
                c.cemeteryNameHe
            FROM `rows` r
            LEFT JOIN plots p ON r.plotId = p.unicId
            LEFT JOIN blocks b ON p.blockId = b.unicId
            LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
            WHERE r.unicId = ? AND r.isActive = 1
        ");
        $stmt->execute([$parentId]);
        $hierarchyPath = $stmt->fetch(PDO::FETCH_ASSOC);

        // אם נמצא - ה-parentId תקין
        if ($hierarchyPath) {
            $validatedLineId = $parentId;
        }

        // טען שורות מאותה חלקה
        if ($hierarchyPath && $hierarchyPath['plotId']) {
            $stmt = $conn->prepare("
                SELECT unicId, lineNameHe, serialNumber
                FROM `rows`
                WHERE plotId = ? AND isActive = 1
                ORDER BY serialNumber, lineNameHe
            ");
            $stmt->execute([$hierarchyPath['plotId']]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[$row['unicId']] = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
            }
        }
    }

    // במצב הוספה - חובה שיהיה parentId תקין
    if (!$isEditMode && !$validatedLineId) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: לא ניתן להוסיף אחוזת קבר ללא שורה תקינה. יש לבחור שורה מתוך הטבלה.</body></html>');
    }

} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת אחוזת קבר' : 'הוספת אחוזת קבר חדשה';

// מיפויים
$graveTypes = ['' => '-- בחר סוג --', 1 => 'שדה', 2 => 'רוויה', 3 => 'סנהדרין'];
$plotTypes = [1 => 'פטורה', 2 => 'חריגה', 3 => 'סגורה'];
$graveStatuses = [1 => 'פנוי', 2 => 'נרכש', 3 => 'קבור', 4 => 'שמור'];

// JSON לקברים קיימים
$gravesJson = json_encode($graves, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f1f5f9;
            color: #334155;
            padding: 20px;
            direction: rtl;
        }

        .form-container { max-width: 100%; }
        .sortable-sections { display: flex; flex-direction: column; gap: 15px; }
        .sortable-section {
            background: white;
            border-radius: 12px;
            border: 2px solid transparent;
            overflow: visible;
            transition: border-color 0.2s;
        }
        .sortable-section:hover { border-color: #94a3b8; }

        .section-drag-handle {
            height: 32px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
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
            background: rgba(100,116,139,0.2);
            border-radius: 4px;
            cursor: pointer;
            color: #64748b;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .section-toggle-btn:hover { background: rgba(100,116,139,0.4); }
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group.span-2 { grid-column: span 2; }
        .form-group label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group label .required { color: #ef4444; }

        .form-control {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .form-control:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }

        textarea.form-control { resize: vertical; min-height: 80px; }

        .location-display {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #86efac;
            color: #166534;
            font-size: 13px;
        }
        .location-display i { margin-left: 8px; color: #22c55e; }

        /* טבלת קברים */
        .graves-section {
            background: linear-gradient(135deg, #faf5ff, #f3e8ff);
            border: 2px solid #c084fc;
            border-radius: 12px;
            padding: 20px;
        }
        .graves-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .graves-title {
            font-size: 16px;
            font-weight: 600;
            color: #7c3aed;
        }
        .graves-count {
            font-size: 13px;
            color: #8b5cf6;
        }
        .btn-add-grave {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-add-grave:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-add-grave:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        .graves-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .graves-table thead {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            color: white;
        }
        .graves-table th {
            padding: 12px;
            text-align: right;
            font-weight: 600;
            font-size: 13px;
        }
        .graves-table th.center { text-align: center; }
        .graves-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .graves-table tr:last-child td { border-bottom: none; }
        .graves-table input, .graves-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }
        .graves-table input:focus, .graves-table select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.1);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-1 { background: #dcfce7; color: #166534; } /* פנוי */
        .status-2 { background: #dbeafe; color: #1e40af; } /* נרכש */
        .status-3 { background: #f3f4f6; color: #374151; } /* קבור */
        .status-4 { background: #fef3c7; color: #92400e; } /* שמור */

        .btn-delete-grave {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-delete-grave:hover:not(:disabled) { background: #dc2626; }
        .btn-delete-grave:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }

        .checkbox-cell {
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-primary:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-secondary:hover { background: #475569; }

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
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.show { display: block; }

        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-graves {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }
        .empty-graves i { font-size: 48px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="areaGraveForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($areaGrave['unicId'] ?? '') ?>">
            <input type="hidden" name="lineId" id="lineId" value="<?= htmlspecialchars($validatedLineId ?? $areaGrave['lineId'] ?? '') ?>">

            <div class="sortable-sections">
                <!-- סקשן 1: פרטי אחוזת קבר -->
                <div class="sortable-section" data-section="details">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #1e40af;">
                            <i class="fas fa-layer-group"></i> פרטי אחוזת קבר
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="form-grid">
                            <?php if ($hierarchyPath): ?>
                            <!-- תצוגת היררכיה -->
                            <div class="form-group span-2">
                                <label>מיקום</label>
                                <div class="location-display">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php
                                    $parts = [];
                                    if (!empty($hierarchyPath['cemeteryNameHe'])) $parts[] = $hierarchyPath['cemeteryNameHe'];
                                    if (!empty($hierarchyPath['blockNameHe'])) $parts[] = 'גוש ' . $hierarchyPath['blockNameHe'];
                                    if (!empty($hierarchyPath['plotNameHe'])) $parts[] = 'חלקה ' . $hierarchyPath['plotNameHe'];
                                    if (!empty($hierarchyPath['lineNameHe'])) $parts[] = '<strong>שורה ' . htmlspecialchars($hierarchyPath['lineNameHe']) . '</strong>';
                                    echo implode(' &larr; ', $parts);
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!$isEditMode && !empty($rows)): ?>
                            <!-- בחירת שורה במצב הוספה -->
                            <div class="form-group span-2">
                                <label><span class="required">*</span> שורה</label>
                                <select name="lineId" id="lineIdSelect" class="form-control" required>
                                    <?php foreach ($rows as $id => $name): ?>
                                        <option value="<?= htmlspecialchars($id) ?>" <?= ($validatedLineId == $id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label><span class="required">*</span> שם אחוזת קבר</label>
                                <input type="text" name="areaGraveNameHe" id="areaGraveNameHe" class="form-control" required
                                       placeholder="הזן שם אחוזת קבר"
                                       value="<?= htmlspecialchars($areaGrave['areaGraveNameHe'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label><span class="required">*</span> סוג אחוזת קבר</label>
                                <select name="graveType" id="graveType" class="form-control" required>
                                    <?php foreach ($graveTypes as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= (($areaGrave['graveType'] ?? '') == $value) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>קואורדינטות</label>
                                <input type="text" name="coordinates" id="coordinates" class="form-control"
                                       placeholder="הזן קואורדינטות"
                                       value="<?= htmlspecialchars($areaGrave['coordinates'] ?? '') ?>">
                            </div>

                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comments" id="comments" class="form-control" rows="2"
                                          placeholder="הערות נוספות"><?= htmlspecialchars($areaGrave['comments'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: קברים באחוזה -->
                <div class="sortable-section" data-section="graves">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #f3e8ff, #e9d5ff);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #7c3aed;">
                            <i class="fas fa-monument"></i> קברים באחוזה (1-5)
                        </span>
                    </div>
                    <div class="section-content graves-section">
                        <div class="graves-header">
                            <div>
                                <span class="graves-title">קברים באחוזה</span>
                                <span class="graves-count" id="gravesCount">(0/5)</span>
                            </div>
                            <button type="button" class="btn-add-grave" id="btnAddGrave" onclick="addGraveRow()">
                                <i class="fas fa-plus"></i> הוסף קבר
                            </button>
                        </div>

                        <table class="graves-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="center">#</th>
                                    <th>שם קבר <span style="color: #fbbf24;">*</span></th>
                                    <th style="width: 130px;">סוג חלקה <span style="color: #fbbf24;">*</span></th>
                                    <?php if ($isEditMode): ?>
                                    <th style="width: 90px;" class="center">סטטוס</th>
                                    <?php endif; ?>
                                    <th style="width: 80px;" class="center">קבר קטן</th>
                                    <th style="width: 120px;">עלות בנייה</th>
                                    <th style="width: 70px;" class="center">פעולות</th>
                                </tr>
                            </thead>
                            <tbody id="gravesBody">
                                <!-- שורות קברים יתווספו דינמית -->
                            </tbody>
                        </table>

                        <div class="empty-graves" id="emptyGraves" style="display: none;">
                            <i class="fas fa-monument"></i>
                            <div>לחץ "הוסף קבר" כדי להתחיל</div>
                        </div>

                        <input type="hidden" name="gravesData" id="gravesData">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeForm()">
                    <i class="fas fa-times"></i> ביטול
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן אחוזת קבר' : 'צור אחוזת קבר' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const areaGraveId = '<?= addslashes($itemId ?? '') ?>';
        const parentLineId = '<?= addslashes($validatedLineId ?? $areaGrave['lineId'] ?? '') ?>';
        const existingGraves = <?= $gravesJson ?>;
        const plotTypes = <?= json_encode($plotTypes, JSON_UNESCAPED_UNICODE) ?>;
        const graveStatuses = <?= json_encode($graveStatuses, JSON_UNESCAPED_UNICODE) ?>;

        const MAX_GRAVES = 5;
        const MIN_GRAVES = 1;

        let gravesData = [];
        let graveCounter = 0;

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            // טען קברים קיימים או צור אחד חדש
            if (existingGraves && existingGraves.length > 0) {
                existingGraves.forEach((grave, index) => {
                    addGraveRow(grave, index === 0);
                });
            } else {
                // במצב הוספה - צור קבר ראשון חובה
                addGraveRow(null, true);
            }

            updateGravesCount();

            // עדכון lineId מהסלקט אם קיים
            const lineIdSelect = document.getElementById('lineIdSelect');
            if (lineIdSelect) {
                lineIdSelect.addEventListener('change', function() {
                    document.getElementById('lineId').value = this.value;
                });
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // הוספת שורת קבר
        function addGraveRow(graveData = null, isFirst = false) {
            if (gravesData.length >= MAX_GRAVES) {
                showAlert('ניתן להוסיף עד 5 קברים לאחוזת קבר', 'error');
                return;
            }

            const tbody = document.getElementById('gravesBody');
            const rowIndex = graveCounter++;

            // בדיקה אם הקבר ניתן למחיקה
            let canDelete = !isFirst;
            let deleteReason = isFirst ? 'קבר ראשון חובה' : '';

            if (graveData && isEditMode) {
                const status = parseInt(graveData.graveStatus) || 1;
                const hasPurchase = parseInt(graveData.hasPurchase) > 0;
                const hasBurial = parseInt(graveData.hasBurial) > 0;

                if (status > 1) {
                    canDelete = false;
                    deleteReason = 'קבר ' + (graveStatuses[status] || 'בשימוש');
                } else if (hasPurchase) {
                    canDelete = false;
                    deleteReason = 'יש רכישה פעילה';
                } else if (hasBurial) {
                    canDelete = false;
                    deleteReason = 'יש קבורה פעילה';
                }
            }

            // הוסף למערך
            gravesData.push({
                index: rowIndex,
                unicId: graveData?.unicId || '',
                graveNameHe: graveData?.graveNameHe || '',
                plotType: graveData?.plotType || 1,
                graveStatus: graveData?.graveStatus || 1,
                isSmallGrave: graveData?.isSmallGrave || 0,
                constructionCost: graveData?.constructionCost || '',
                isFirst: isFirst,
                canDelete: canDelete
            });

            // צור שורת HTML
            const tr = document.createElement('tr');
            tr.id = `graveRow_${rowIndex}`;
            tr.dataset.index = rowIndex;

            let statusCell = '';
            if (isEditMode) {
                const status = parseInt(graveData?.graveStatus) || 1;
                statusCell = `<td class="center">
                    <span class="status-badge status-${status}">${graveStatuses[status] || 'לא ידוע'}</span>
                </td>`;
            }

            let plotTypeOptions = '';
            for (const [val, label] of Object.entries(plotTypes)) {
                const selected = (graveData?.plotType == val) ? 'selected' : '';
                plotTypeOptions += `<option value="${val}" ${selected}>${label}</option>`;
            }

            tr.innerHTML = `
                <td class="center">${gravesData.length}</td>
                <td>
                    <input type="text" class="grave-name" data-index="${rowIndex}"
                           value="${escapeHtml(graveData?.graveNameHe || '')}"
                           placeholder="שם הקבר" required>
                </td>
                <td>
                    <select class="grave-plotType" data-index="${rowIndex}">
                        ${plotTypeOptions}
                    </select>
                </td>
                ${statusCell}
                <td class="checkbox-cell">
                    <input type="checkbox" class="grave-isSmall" data-index="${rowIndex}"
                           ${(graveData?.isSmallGrave == 1) ? 'checked' : ''}>
                </td>
                <td>
                    <input type="number" class="grave-cost" data-index="${rowIndex}"
                           value="${graveData?.constructionCost || ''}"
                           placeholder="0" min="0" step="0.01">
                </td>
                <td class="center">
                    <button type="button" class="btn-delete-grave" data-index="${rowIndex}"
                            onclick="deleteGraveRow(${rowIndex})"
                            ${!canDelete ? 'disabled title="' + deleteReason + '"' : ''}>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

            tbody.appendChild(tr);

            // האזנה לשינויים
            tr.querySelector('.grave-name').addEventListener('input', (e) => updateGraveData(rowIndex, 'graveNameHe', e.target.value));
            tr.querySelector('.grave-plotType').addEventListener('change', (e) => updateGraveData(rowIndex, 'plotType', e.target.value));
            tr.querySelector('.grave-isSmall').addEventListener('change', (e) => updateGraveData(rowIndex, 'isSmallGrave', e.target.checked ? 1 : 0));
            tr.querySelector('.grave-cost').addEventListener('input', (e) => updateGraveData(rowIndex, 'constructionCost', e.target.value));

            updateGravesCount();
            document.getElementById('emptyGraves').style.display = 'none';
        }

        // עדכון נתוני קבר
        function updateGraveData(index, field, value) {
            const grave = gravesData.find(g => g.index === index);
            if (grave) {
                grave[field] = value;
            }
        }

        // מחיקת שורת קבר
        function deleteGraveRow(index) {
            const grave = gravesData.find(g => g.index === index);

            if (!grave) return;

            if (grave.isFirst) {
                showAlert('לא ניתן למחוק את הקבר הראשון', 'error');
                return;
            }

            if (!grave.canDelete) {
                showAlert('לא ניתן למחוק קבר זה - יש רכישה או קבורה פעילים', 'error');
                return;
            }

            if (gravesData.length <= MIN_GRAVES) {
                showAlert('חייב להיות לפחות קבר אחד באחוזה', 'error');
                return;
            }

            // הסר מהמערך
            gravesData = gravesData.filter(g => g.index !== index);

            // הסר מה-DOM
            const row = document.getElementById(`graveRow_${index}`);
            if (row) row.remove();

            // עדכון מספור
            renumberGraves();
            updateGravesCount();

            if (gravesData.length === 0) {
                document.getElementById('emptyGraves').style.display = 'block';
            }
        }

        // מספור מחדש של הקברים
        function renumberGraves() {
            const rows = document.querySelectorAll('#gravesBody tr');
            rows.forEach((row, idx) => {
                row.querySelector('td:first-child').textContent = idx + 1;
            });
        }

        // עדכון מונה קברים
        function updateGravesCount() {
            const count = gravesData.length;
            document.getElementById('gravesCount').textContent = `(${count}/${MAX_GRAVES})`;

            const addBtn = document.getElementById('btnAddGrave');
            if (count >= MAX_GRAVES) {
                addBtn.disabled = true;
                addBtn.title = 'הגעת למקסימום 5 קברים';
            } else {
                addBtn.disabled = false;
                addBtn.title = '';
            }
        }

        // escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // שליחת הטופס
        document.getElementById('areaGraveForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // ולידציה
            const areaGraveName = document.getElementById('areaGraveNameHe').value.trim();
            const graveType = document.getElementById('graveType').value;
            const lineId = document.getElementById('lineId').value || document.getElementById('lineIdSelect')?.value;

            if (!areaGraveName) {
                showAlert('שם אחוזת קבר הוא שדה חובה', 'error');
                return;
            }

            if (!graveType) {
                showAlert('סוג אחוזת קבר הוא שדה חובה', 'error');
                return;
            }

            if (!lineId) {
                showAlert('שורה היא שדה חובה', 'error');
                return;
            }

            if (gravesData.length < MIN_GRAVES) {
                showAlert('חייב להיות לפחות קבר אחד באחוזה', 'error');
                return;
            }

            // ולידציה של שמות קברים
            for (const grave of gravesData) {
                if (!grave.graveNameHe || grave.graveNameHe.trim() === '') {
                    showAlert('שם קבר הוא שדה חובה לכל קבר', 'error');
                    return;
                }
            }

            // הכנת נתונים
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                if (value !== '' && key !== 'gravesData') {
                    data[key] = value;
                }
            });

            // הוסף lineId
            data.lineId = lineId;

            // הכן נתוני קברים
            const gravesToSend = gravesData.map(g => ({
                unicId: g.unicId || '',
                graveNameHe: g.graveNameHe,
                plotType: g.plotType,
                isSmallGrave: g.isSmallGrave,
                constructionCost: g.constructionCost || 0
            }));

            data.gravesData = JSON.stringify(gravesToSend);

            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=${action}${isEditMode ? '&id=' + areaGraveId : ''}`;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'הפעולה בוצעה בהצלחה', 'success');

                    // רענון
                    if (window.parent) {
                        if (window.parent.EntityManager) {
                            window.parent.EntityManager.refresh('areaGrave');
                        }
                        if (window.parent.refreshTable) {
                            window.parent.refreshTable();
                        }
                        if (window.parent.tableRenderer) {
                            window.parent.tableRenderer.loadAndDisplay('areaGrave', parentLineId);
                        }
                    }

                    setTimeout(() => {
                        closeForm();
                    }, 1500);
                } else {
                    throw new Error(result.error || result.message || 'שגיאה בשמירה');
                }
            } catch (error) {
                showAlert(error.message, 'error');
            } finally {
                showLoading(false);
                document.getElementById('submitBtn').disabled = false;
            }
        });

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;

            if (type === 'success') {
                setTimeout(() => alertBox.classList.remove('show'), 3000);
            }
        }

        function showLoading(show) {
            document.getElementById('loadingOverlay').classList.toggle('show', show);
        }

        function closeForm() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.close();
            }
        }
    </script>
</body>
</html>
