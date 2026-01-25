<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/cityForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-21
 * Author: Malkiel
 * Description: טופס עיר (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? $_GET['countryId'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$city = null;
$countries = [];

try {
    $conn = getDBConnection();

    // טען מדינות
    $countriesStmt = $conn->prepare("
        SELECT unicId, countryNameHe
        FROM countries
        WHERE isActive = 1
        ORDER BY countryNameHe
    ");
    $countriesStmt->execute();
    $countries = $countriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען עיר אם בעריכה
    if ($isEditMode) {
        $stmt = $conn->prepare("
            SELECT c.*, co.countryNameHe
            FROM cities c
            LEFT JOIN countries co ON c.countryId = co.unicId
            WHERE c.unicId = ? AND c.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$city) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: העיר לא נמצאה</body></html>');
        }

        // אם נמצאה עיר, שמור את ה-countryId שלה
        if (!$parentId) {
            $parentId = $city['countryId'];
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת עיר' : 'הוספת עיר חדשה';
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

        <form id="cityForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($city['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="cityFormSortableSections">
                <!-- סקשן: פרטי העיר -->
                <div class="sortable-section section-green" data-section="details">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-city"></i> פרטי העיר
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>מדינה <span class="required">*</span></label>
                                <select name="countryId" id="countryId" class="form-control" required <?= !empty($parentId) ? 'disabled' : '' ?>>
                                    <option value="">-- בחר מדינה --</option>
                                    <?php foreach ($countries as $co): ?>
                                        <option value="<?= htmlspecialchars($co['unicId']) ?>"
                                            <?= ($parentId == $co['unicId'] || ($city && $city['countryId'] == $co['unicId'])) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($co['countryNameHe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($parentId)): ?>
                                    <input type="hidden" name="countryId" value="<?= htmlspecialchars($parentId) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>שם עיר בעברית <span class="required">*</span></label>
                                <input type="text" name="cityNameHe" class="form-control" required
                                    placeholder="לדוגמה: ירושלים"
                                    value="<?= htmlspecialchars($city['cityNameHe'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם עיר באנגלית <span class="required">*</span></label>
                                <input type="text" name="cityNameEn" class="form-control" required
                                    placeholder="Example: Jerusalem" dir="ltr"
                                    value="<?= htmlspecialchars($city['cityNameEn'] ?? '') ?>">
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
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן עיר' : 'הוסף עיר' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const cityId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // שליחת הטופס
        document.getElementById('cityForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const countryId = this.querySelector('[name="countryId"]:not([disabled])')?.value ||
                              this.querySelector('input[name="countryId"]')?.value;
            const cityNameHe = this.querySelector('[name="cityNameHe"]').value.trim();
            const cityNameEn = this.querySelector('[name="cityNameEn"]').value.trim();

            if (!countryId) {
                showAlert('מדינה היא שדה חובה', 'error');
                return;
            }

            if (!cityNameHe) {
                showAlert('שם עיר בעברית הוא שדה חובה', 'error');
                return;
            }

            if (!cityNameEn) {
                showAlert('שם עיר באנגלית הוא שדה חובה', 'error');
                return;
            }

            const data = {
                countryId: countryId,
                cityNameHe: cityNameHe,
                cityNameEn: cityNameEn
            };

            if (isEditMode) {
                data.unicId = cityId;
            }

            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/cities-api.php?action=${action}${isEditMode ? '&id=' + cityId : ''}`;

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
                            window.parent.EntityManager.refresh('city');
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
                SortableSections.init('cityFormSortableSections', 'cityForm');
            }
        });
    </script>
</body>
</html>
