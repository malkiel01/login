<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/blockForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-23
 * Author: Malkiel
 * Description: טופס גוש (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? $_GET['cemeteryId'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$block = null;
$cemeteries = [];

try {
    $conn = getDBConnection();

    // טען בתי עלמין
    $cemeteriesStmt = $conn->prepare("
        SELECT unicId, cemeteryNameHe
        FROM cemeteries
        WHERE isActive = 1
        ORDER BY cemeteryNameHe
    ");
    $cemeteriesStmt->execute();
    $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען גוש אם בעריכה
    if ($isEditMode) {
        $stmt = $conn->prepare("
            SELECT b.*, c.cemeteryNameHe
            FROM blocks b
            LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
            WHERE b.unicId = ? AND b.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $block = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$block) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הגוש לא נמצא</body></html>');
        }

        // אם נמצא גוש, שמור את ה-cemeteryId שלו
        if (!$parentId) {
            $parentId = $block['cemeteryId'];
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת גוש' : 'הוספת גוש חדש';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="blockForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($block['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="blockFormSortableSections">
                <!-- סקשן: פרטי הגוש -->
                <div class="sortable-section section-orange" data-section="details">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-cube"></i> פרטי הגוש
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>בית עלמין <span class="required">*</span></label>
                                <select name="cemeteryId" id="cemeteryId" class="form-control" required <?= !empty($parentId) ? 'disabled' : '' ?>>
                                    <option value="">-- בחר בית עלמין --</option>
                                    <?php foreach ($cemeteries as $cem): ?>
                                        <option value="<?= htmlspecialchars($cem['unicId']) ?>"
                                            <?= ($parentId == $cem['unicId'] || ($block && $block['cemeteryId'] == $cem['unicId'])) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cem['cemeteryNameHe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($parentId)): ?>
                                    <input type="hidden" name="cemeteryId" value="<?= htmlspecialchars($parentId) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>שם גוש בעברית <span class="required">*</span></label>
                                <input type="text" name="blockNameHe" class="form-control" required
                                    placeholder="לדוגמה: גוש א'"
                                    value="<?= htmlspecialchars($block['blockNameHe'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם גוש באנגלית</label>
                                <input type="text" name="blockNameEn" class="form-control"
                                    placeholder="Example: Block A" dir="ltr"
                                    value="<?= htmlspecialchars($block['blockNameEn'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קוד גוש</label>
                                <input type="text" name="blockCode" class="form-control"
                                    placeholder="לדוגמה: BLK-001"
                                    value="<?= htmlspecialchars($block['blockCode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>מיקום</label>
                                <input type="text" name="blockLocation" class="form-control"
                                    placeholder="לדוגמה: צפון"
                                    value="<?= htmlspecialchars($block['blockLocation'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן: פרטים נוספים -->
                <div class="sortable-section section-purple" data-section="additional">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-info-circle"></i> פרטים נוספים
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>קוד ביטוח לאומי</label>
                                <input type="text" name="nationalInsuranceCode" class="form-control"
                                    placeholder="קוד ביטוח לאומי"
                                    value="<?= htmlspecialchars($block['nationalInsuranceCode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קואורדינטות</label>
                                <input type="text" name="coordinates" class="form-control"
                                    placeholder="לדוגמה: 32.0853, 34.7818" dir="ltr"
                                    value="<?= htmlspecialchars($block['coordinates'] ?? '') ?>">
                            </div>
                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comments" class="form-control"
                                    placeholder="הערות נוספות..."><?= htmlspecialchars($block['comments'] ?? '') ?></textarea>
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
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן גוש' : 'הוסף גוש' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const blockId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // שליחת הטופס
        document.getElementById('blockForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const cemeteryId = this.querySelector('[name="cemeteryId"]:not([disabled])')?.value ||
                              this.querySelector('input[name="cemeteryId"]')?.value;
            const blockNameHe = this.querySelector('[name="blockNameHe"]').value.trim();

            if (!cemeteryId) {
                showAlert('בית עלמין הוא שדה חובה', 'error');
                return;
            }

            if (!blockNameHe) {
                showAlert('שם גוש בעברית הוא שדה חובה', 'error');
                return;
            }

            const data = {
                cemeteryId: cemeteryId,
                blockNameHe: blockNameHe,
                blockNameEn: this.querySelector('[name="blockNameEn"]').value.trim(),
                blockCode: this.querySelector('[name="blockCode"]').value.trim(),
                blockLocation: this.querySelector('[name="blockLocation"]').value.trim(),
                nationalInsuranceCode: this.querySelector('[name="nationalInsuranceCode"]').value.trim(),
                coordinates: this.querySelector('[name="coordinates"]').value.trim(),
                comments: this.querySelector('[name="comments"]').value.trim()
            };

            if (isEditMode) {
                data.unicId = blockId;
            }

            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/blocks-api.php?action=${action}${isEditMode ? '&id=' + blockId : ''}`;

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
                            window.parent.EntityManager.refresh('block');
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
                SortableSections.init('blockFormSortableSections', 'blockForm');
            }
        });
    </script>
</body>
</html>
