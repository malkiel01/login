<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/plotForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-23
 * Author: Malkiel
 * Description: טופס חלקה (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? $_GET['blockId'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$plot = null;
$blocks = [];

try {
    $conn = getDBConnection();

    // טען גושים עם שם בית עלמין
    $blocksStmt = $conn->prepare("
        SELECT b.unicId, b.blockNameHe, c.cemeteryNameHe
        FROM blocks b
        LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
        WHERE b.isActive = 1
        ORDER BY c.cemeteryNameHe, b.blockNameHe
    ");
    $blocksStmt->execute();
    $blocks = $blocksStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען חלקה אם בעריכה
    if ($isEditMode) {
        $stmt = $conn->prepare("
            SELECT p.*, b.blockNameHe, c.cemeteryNameHe
            FROM plots p
            LEFT JOIN blocks b ON p.blockId = b.unicId
            LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
            WHERE p.unicId = ? AND p.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $plot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plot) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: החלקה לא נמצאה</body></html>');
        }

        // אם נמצאה חלקה, שמור את ה-blockId שלה
        if (!$parentId) {
            $parentId = $plot['blockId'];
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת חלקה' : 'הוספת חלקה חדשה';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
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
            overflow: hidden;
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
        .form-control.error { border-color: #ef4444; }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="plotForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($plot['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="plotFormSortableSections">
                <!-- סקשן: פרטי החלקה -->
                <div class="sortable-section" data-section="details">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #1e40af;">
                            <i class="fas fa-th-large"></i> פרטי החלקה
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>גוש <span class="required">*</span></label>
                                <select name="blockId" id="blockId" class="form-control" required <?= !empty($parentId) ? 'disabled' : '' ?>>
                                    <option value="">-- בחר גוש --</option>
                                    <?php foreach ($blocks as $blk): ?>
                                        <option value="<?= htmlspecialchars($blk['unicId']) ?>"
                                            <?= ($parentId == $blk['unicId'] || ($plot && $plot['blockId'] == $blk['unicId'])) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($blk['blockNameHe']) ?> (<?= htmlspecialchars($blk['cemeteryNameHe']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($parentId)): ?>
                                    <input type="hidden" name="blockId" value="<?= htmlspecialchars($parentId) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>שם חלקה בעברית <span class="required">*</span></label>
                                <input type="text" name="plotNameHe" class="form-control" required
                                    placeholder="לדוגמה: חלקה א'"
                                    value="<?= htmlspecialchars($plot['plotNameHe'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם חלקה באנגלית</label>
                                <input type="text" name="plotNameEn" class="form-control"
                                    placeholder="Example: Plot A" dir="ltr"
                                    value="<?= htmlspecialchars($plot['plotNameEn'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קוד חלקה</label>
                                <input type="text" name="plotCode" class="form-control"
                                    placeholder="לדוגמה: PLT-001"
                                    value="<?= htmlspecialchars($plot['plotCode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>מיקום</label>
                                <input type="text" name="plotLocation" class="form-control"
                                    placeholder="לדוגמה: מזרח"
                                    value="<?= htmlspecialchars($plot['plotLocation'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן: פרטים נוספים -->
                <div class="sortable-section" data-section="additional">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #e0e7ff, #c7d2fe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #3730a3;">
                            <i class="fas fa-info-circle"></i> פרטים נוספים
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eef2ff, #e0e7ff);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>קוד ביטוח לאומי</label>
                                <input type="text" name="nationalInsuranceCode" class="form-control"
                                    placeholder="קוד ביטוח לאומי"
                                    value="<?= htmlspecialchars($plot['nationalInsuranceCode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קואורדינטות</label>
                                <input type="text" name="coordinates" class="form-control"
                                    placeholder="לדוגמה: 32.0853, 34.7818" dir="ltr"
                                    value="<?= htmlspecialchars($plot['coordinates'] ?? '') ?>">
                            </div>
                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comments" class="form-control"
                                    placeholder="הערות נוספות..."><?= htmlspecialchars($plot['comments'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeForm()">
                    <i class="fas fa-times"></i> ביטול
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן חלקה' : 'הוסף חלקה' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const plotId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // שליחת הטופס
        document.getElementById('plotForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const blockId = this.querySelector('[name="blockId"]:not([disabled])')?.value ||
                           this.querySelector('input[name="blockId"]')?.value;
            const plotNameHe = this.querySelector('[name="plotNameHe"]').value.trim();

            if (!blockId) {
                showAlert('גוש הוא שדה חובה', 'error');
                return;
            }

            if (!plotNameHe) {
                showAlert('שם חלקה בעברית הוא שדה חובה', 'error');
                return;
            }

            const data = {
                blockId: blockId,
                plotNameHe: plotNameHe,
                plotNameEn: this.querySelector('[name="plotNameEn"]').value.trim(),
                plotCode: this.querySelector('[name="plotCode"]').value.trim(),
                plotLocation: this.querySelector('[name="plotLocation"]').value.trim(),
                nationalInsuranceCode: this.querySelector('[name="nationalInsuranceCode"]').value.trim(),
                coordinates: this.querySelector('[name="coordinates"]').value.trim(),
                comments: this.querySelector('[name="comments"]').value.trim()
            };

            if (isEditMode) {
                data.unicId = plotId;
            }

            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/plots-api.php?action=${action}${isEditMode ? '&id=' + plotId : ''}`;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'הפעולה בוצעה בהצלחה', 'success');

                    if (window.parent) {
                        if (window.parent.EntityManager) {
                            window.parent.EntityManager.refresh('plot');
                        }
                        if (window.parent.refreshTable) {
                            window.parent.refreshTable();
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
                setTimeout(() => {
                    alertBox.classList.remove('show');
                }, 3000);
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
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('plotFormSortableSections', 'plotForm');
            }
        });
    </script>
</body>
</html>
