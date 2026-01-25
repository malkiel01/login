<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/cemeteryForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-23
 * Author: Malkiel
 * Description: טופס בית עלמין (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$cemetery = null;

try {
    $conn = getDBConnection();

    // טען בית עלמין אם בעריכה
    if ($isEditMode) {
        $stmt = $conn->prepare("
            SELECT * FROM cemeteries
            WHERE unicId = ? AND isActive = 1
        ");
        $stmt->execute([$itemId]);
        $cemetery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cemetery) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: בית העלמין לא נמצא</body></html>');
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת בית עלמין' : 'הוספת בית עלמין חדש';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
    <!-- All section styles now come from popup-sections.css -->
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="cemeteryForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($cemetery['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="cemeteryFormSortableSections">
                <!-- סקשן: פרטי בית העלמין -->
                <div class="sortable-section section-green" data-section="details">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-landmark"></i> פרטי בית העלמין
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>שם בית עלמין בעברית <span class="required">*</span></label>
                                <input type="text" name="cemeteryNameHe" class="form-control" required
                                    placeholder="לדוגמה: בית עלמין המרכזי"
                                    value="<?= htmlspecialchars($cemetery['cemeteryNameHe'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם בית עלמין באנגלית</label>
                                <input type="text" name="cemeteryNameEn" class="form-control"
                                    placeholder="Example: Central Cemetery" dir="ltr"
                                    value="<?= htmlspecialchars($cemetery['cemeteryNameEn'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קוד בית עלמין</label>
                                <input type="text" name="cemeteryCode" class="form-control"
                                    placeholder="לדוגמה: CEM-001"
                                    value="<?= htmlspecialchars($cemetery['cemeteryCode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>כתובת</label>
                                <input type="text" name="address" class="form-control"
                                    placeholder="לדוגמה: רחוב הראשי 1, תל אביב"
                                    value="<?= htmlspecialchars($cemetery['address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן: פרטי קשר -->
                <div class="sortable-section section-blue" data-section="contact">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-phone"></i> פרטי קשר
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>שם איש קשר</label>
                                <input type="text" name="contactName" class="form-control"
                                    placeholder="שם מלא"
                                    value="<?= htmlspecialchars($cemetery['contactName'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>טלפון איש קשר</label>
                                <input type="tel" name="contactPhoneName" class="form-control"
                                    placeholder="לדוגמה: 050-1234567" dir="ltr"
                                    value="<?= htmlspecialchars($cemetery['contactPhoneName'] ?? '') ?>">
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
                                    value="<?= htmlspecialchars($cemetery['nationalInsuranceCode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קואורדינטות</label>
                                <input type="text" name="coordinates" class="form-control"
                                    placeholder="לדוגמה: 32.0853, 34.7818" dir="ltr"
                                    value="<?= htmlspecialchars($cemetery['coordinates'] ?? '') ?>">
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
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן בית עלמין' : 'הוסף בית עלמין' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const cemeteryId = '<?= addslashes($itemId ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // שליחת הטופס
        document.getElementById('cemeteryForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const cemeteryNameHe = this.querySelector('[name="cemeteryNameHe"]').value.trim();

            if (!cemeteryNameHe) {
                showAlert('שם בית עלמין בעברית הוא שדה חובה', 'error');
                return;
            }

            const data = {
                cemeteryNameHe: cemeteryNameHe,
                cemeteryNameEn: this.querySelector('[name="cemeteryNameEn"]').value.trim(),
                cemeteryCode: this.querySelector('[name="cemeteryCode"]').value.trim(),
                address: this.querySelector('[name="address"]').value.trim(),
                contactName: this.querySelector('[name="contactName"]').value.trim(),
                contactPhoneName: this.querySelector('[name="contactPhoneName"]').value.trim(),
                nationalInsuranceCode: this.querySelector('[name="nationalInsuranceCode"]').value.trim(),
                coordinates: this.querySelector('[name="coordinates"]').value.trim()
            };

            if (isEditMode) {
                data.unicId = cemeteryId;
            }

            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=${action}${isEditMode ? '&id=' + cemeteryId : ''}`;

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
                            window.parent.EntityManager.refresh('cemetery');
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
                SortableSections.init('cemeteryFormSortableSections', 'cemeteryForm');
            }
        });
    </script>
</body>
</html>
