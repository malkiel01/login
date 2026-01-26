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
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
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

        <form id="plotForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($plot['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="plotFormSortableSections">
                <!-- סקשן: פרטי החלקה -->
                <div class="sortable-section section-blue" data-section="details">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-th-large"></i> פרטי החלקה
                        </span>
                    </div>
                    <div class="section-content">
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
    <!-- DEBUG SCRIPT - DETAILED -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('%c=== DEBUG: plotForm-iframe.php ===', 'background: #3b82f6; color: white; padding: 8px 15px; font-size: 16px; font-weight: bold;');

            // בדיקת CSS files שנטענו
            console.log('%c📁 CSS Files Loaded:', 'font-weight: bold; color: #f59e0b;');
            document.querySelectorAll('link[rel="stylesheet"]').forEach((link, i) => {
                console.log(`   ${i+1}. ${link.href}`);
            });

            // בדיקת style tags
            const styleTags = document.querySelectorAll('style');
            console.log('%c📝 Inline Style Tags:', 'font-weight: bold; color: #ef4444;');
            console.log(`   Count: ${styleTags.length}`);
            styleTags.forEach((style, i) => {
                console.log(`   Tag ${i+1}: ${style.innerHTML.length} characters`);
            });

            console.log('%c📊 Computed Styles:', 'font-weight: bold; color: #10b981;');

            // Body
            const body = document.body;
            const bodyS = getComputedStyle(body);
            console.log('BODY:', {
                padding: bodyS.padding,
                paddingTop: bodyS.paddingTop,
                paddingRight: bodyS.paddingRight,
                paddingBottom: bodyS.paddingBottom,
                paddingLeft: bodyS.paddingLeft,
                background: bodyS.backgroundColor
            });

            // Section
            const section = document.querySelector('.sortable-section');
            if (section) {
                const sS = getComputedStyle(section);
                console.log('.sortable-section:', {
                    borderRadius: sS.borderRadius,
                    border: sS.border,
                    background: sS.backgroundColor
                });
            }

            // Section Title
            const title = document.querySelector('.section-title');
            if (title) {
                const tS = getComputedStyle(title);
                console.log('.section-title:', {
                    fontSize: tS.fontSize,
                    fontWeight: tS.fontWeight,
                    color: tS.color
                });
            }

            // Section Content
            const content = document.querySelector('.section-content');
            if (content) {
                const cS = getComputedStyle(content);
                console.log('.section-content:', {
                    padding: cS.padding,
                    background: cS.backgroundColor
                });
            }

            // Buttons
            const btn = document.querySelector('.btn');
            if (btn) {
                const bS = getComputedStyle(btn);
                console.log('.btn:', {
                    padding: bS.padding,
                    minHeight: bS.minHeight,
                    fontSize: bS.fontSize
                });
            }

            // Form Actions
            const actions = document.querySelector('.form-actions');
            if (actions) {
                const aS = getComputedStyle(actions);
                console.log('.form-actions:', {
                    position: aS.position,
                    padding: aS.padding,
                    bottom: aS.bottom,
                    left: aS.left,
                    right: aS.right,
                    background: aS.backgroundColor,
                    boxShadow: aS.boxShadow
                });
            }

            // Window size
            console.log('%c📐 Window Size:', 'font-weight: bold; color: #8b5cf6;');
            console.log(`   innerWidth: ${window.innerWidth}px`);
            console.log(`   innerHeight: ${window.innerHeight}px`);

            console.log('%c=== END DEBUG ===', 'background: #3b82f6; color: white; padding: 5px 10px;');
        });
    </script>
</body>
</html>
