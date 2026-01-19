<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/purchaseCard-form.php
 * Version: 1.0.0
 * Updated: 2025-12-29
 * Author: Malkiel
 * Description: כרטיס רכישה - תצוגת פרטי רכישה עם סקשנים ניתנים לגרירה
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$formType = 'purchaseCard';

// DEBUG
error_log("purchaseCard-form.php - itemId: " . var_export($itemId, true));
error_log("purchaseCard-form.php - GET: " . json_encode($_GET));

if (!$itemId) {
    die('<div class="error-message">שגיאה: מזהה רכישה חסר. GET=' . htmlspecialchars(json_encode($_GET)) . '</div>');
}

try {
    $conn = getDBConnection();

    // שליפת נתוני הרכישה עם פרטי קבר
    $stmt = $conn->prepare("
        SELECT p.*,
            g.unicId as graveUnicId, g.graveNameHe, g.graveStatus, g.graveType, g.graveSize,
            g.gravePrice, g.graveDirection, g.comment as graveComment,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe, agv.areaGraveNameHe,
            agv.unicId as areaGraveUnicId
        FROM purchases p
        LEFT JOIN graves g ON p.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE p.unicId = :id AND p.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        error_log("purchaseCard-form.php - Purchase NOT FOUND for unicId: $itemId");
        die('<div class="error-message">שגיאה: הרכישה לא נמצאה (unicId=' . htmlspecialchars($itemId) . ')</div>');
    }

    // שליפת קבורה משויכת (אם קיימת)
    $stmt = $conn->prepare("
        SELECT b.*,
            c.fullNameHe as deceasedName, c.numId as deceasedNumId,
            c.dateBirth, c.nameFather, c.nameMother
        FROM burials b
        LEFT JOIN customers c ON b.clientId = c.unicId
        WHERE b.purchaseId = :purchaseId AND b.isActive = 1
    ");
    $stmt->execute(['purchaseId' => $purchase['unicId']]);
    $burial = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    FormUtils::handleError($e);
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

// יצירת FormBuilder
$formBuilder = new FormBuilder('purchaseCard', $itemId, null);

// סטטוסים רכישה
$purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
$purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];
$currentStatus = $purchase['purchaseStatus'] ?? 1;
$statusName = $purchaseStatusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $purchaseStatusColors[$currentStatus] ?? '#64748b';

$buyerStatusNames = [1 => 'רוכש לעצמו', 2 => 'רוכש לאחר'];

// סטטוסים קבר
$graveStatusNames = [1 => 'פנוי', 2 => 'תפוס', 3 => 'שמור', 4 => 'לא פעיל'];
$graveStatusColors = [1 => '#10b981', 2 => '#ef4444', 3 => '#f59e0b', 4 => '#64748b'];
$graveTypeNames = [1 => 'יחיד', 2 => 'זוגי', 3 => 'משפחתי', 4 => 'סנהדרין'];
$graveDirectionNames = [1 => 'צפון', 2 => 'דרום', 3 => 'מזרח', 4 => 'מערב'];

// מיקום הקבר
$graveLocation = [];
if ($purchase['cemeteryNameHe']) $graveLocation[] = $purchase['cemeteryNameHe'];
if ($purchase['blockNameHe']) $graveLocation[] = 'גוש ' . $purchase['blockNameHe'];
if ($purchase['plotNameHe']) $graveLocation[] = 'חלקה ' . $purchase['plotNameHe'];
if ($purchase['lineNameHe']) $graveLocation[] = 'שורה ' . $purchase['lineNameHe'];
$graveLocationStr = implode(' / ', $graveLocation) ?: '-';

// === בניית HTML ===
$allSectionsHTML = '
<style>
    /* רוחב המודל */
    #purchaseCardFormModal .modal-dialog {
        max-width: 95% !important;
        width: 1400px !important;
    }
    #purchaseCardFormModal .modal-body {
        max-height: 85vh !important;
        padding: 20px !important;
    }

    /* סקשנים */
    .sortable-sections {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .sortable-section {
        position: relative;
        margin-bottom: 15px;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.2s, transform 0.2s;
        border: 2px solid transparent;
    }

    .sortable-section:hover {
        border-color: #94a3b8;
    }

    .sortable-section.sortable-ghost {
        opacity: 0.5;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px dashed #3b82f6 !important;
    }

    .sortable-section.sortable-chosen {
        box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
        border: 2px solid #3b82f6 !important;
        transform: scale(1.01);
        z-index: 1000;
    }

    /* ידית גרירה */
    .section-drag-handle {
        height: 28px;
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        cursor: grab;
        border-radius: 10px 10px 0 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #cbd5e1;
        transition: background 0.2s;
        position: relative;
    }

    .section-drag-handle::before {
        content: "";
        width: 40px;
        height: 4px;
        background: #94a3b8;
        border-radius: 2px;
    }

    .section-drag-handle:hover {
        background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
    }

    /* כפתור צימצום */
    .section-toggle-btn {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        border: none;
        background: rgba(100, 116, 139, 0.2);
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 10px;
        transition: all 0.2s;
        padding: 0;
    }

    .section-toggle-btn:hover {
        background: rgba(100, 116, 139, 0.4);
        color: #334155;
    }

    .section-toggle-btn i {
        transition: transform 0.3s;
    }

    /* כותרת סקשן */
    .section-title {
        position: absolute;
        right: 35px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
        white-space: nowrap;
    }

    .sortable-section.collapsed .section-title {
        opacity: 1;
    }

    /* מצב מצומצם */
    .sortable-section.collapsed .section-content {
        display: none;
    }

    .sortable-section.collapsed .section-toggle-btn i {
        transform: rotate(-90deg);
    }

    .sortable-section.collapsed .section-drag-handle {
        border-radius: 10px;
        border-bottom: none;
    }

    .sortable-section.collapsed .section-resize-handle {
        display: none;
    }

    /* תוכן */
    .section-content {
        transition: all 0.3s ease;
        overflow: auto;
        min-height: 50px;
    }

    /* ידית שינוי גובה */
    .section-resize-handle {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 8px;
        cursor: ns-resize;
        background: transparent;
        transition: background 0.2s;
        border-radius: 0 0 10px 10px;
    }

    .section-resize-handle:hover {
        background: linear-gradient(to top, rgba(59, 130, 246, 0.3), transparent);
    }

    .section-resize-handle::after {
        content: "";
        position: absolute;
        bottom: 3px;
        left: 50%;
        transform: translateX(-50%);
        width: 30px;
        height: 3px;
        background: #cbd5e1;
        border-radius: 2px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .section-resize-handle:hover::after {
        opacity: 1;
    }

    .sortable-section.resizing {
        user-select: none;
    }

    .sortable-section.resizing .section-content {
        transition: none;
    }

    /* תמיכה במובייל */
    @media (max-width: 768px) {
        #purchaseCardFormModal {
            padding: 0 !important;
        }

        #purchaseCardFormModal .modal-dialog {
            max-width: 100% !important;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
        }

        #purchaseCardFormModal .modal-content {
            height: 100% !important;
            max-height: 100% !important;
            border-radius: 0 !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #purchaseCardFormModal .modal-header {
            flex-shrink: 0 !important;
            padding: 12px 15px !important;
            min-height: 50px !important;
        }

        #purchaseCardFormModal .modal-body {
            flex: 1 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding: 10px !important;
            -webkit-overflow-scrolling: touch !important;
        }

        #purchaseCardFormModal .modal-footer {
            flex-shrink: 0 !important;
            padding: 10px 15px !important;
        }

        .section-toggle-btn {
            width: 32px !important;
            height: 32px !important;
            font-size: 14px !important;
        }

        .section-drag-handle {
            height: 32px !important;
        }
    }
</style>

<div class="sortable-sections" id="purchaseSortableSections">

<!-- סקשן 1: פרטי רכישה -->
<div class="sortable-section" data-section="purchase">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-shopping-cart"></i> פרטי רכישה</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
            <legend style="padding: 0 15px; font-weight: bold; color: #166534; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-shopping-cart"></i> רכישה #' . htmlspecialchars($purchase['serialPurchaseId'] ?? '-') . '
                <span style="background: ' . $statusColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">' . $statusName . '</span>
            </legend>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר רכישה</div>
                    <div style="font-weight: 600; color: #166534;">' . htmlspecialchars($purchase['serialPurchaseId'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך פתיחה</div>
                    <div style="font-weight: 600; color: #166534;">' . formatHebrewDate($purchase['dateOpening']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סכום כולל</div>
                    <div style="font-weight: 700; color: #166534; font-size: 18px;">' . formatPrice($purchase['price']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר תשלומים</div>
                    <div style="font-weight: 600; color: #166534;">' . htmlspecialchars($purchase['numOfPayments'] ?? '1') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סטטוס רוכש</div>
                    <div style="font-weight: 600; color: #166534;">' . ($buyerStatusNames[$purchase['buyer_status'] ?? 1] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0; grid-column: span 2;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">קבר</div>
                    <div style="font-weight: 600; color: #166534;">
                        <i class="fas fa-monument" style="margin-left: 5px;"></i>
                        ' . htmlspecialchars($purchase['graveNameHe'] ?? '-') . '
                        <span style="color: #64748b; font-weight: normal; font-size: 12px;">(' . $graveLocationStr . ')</span>
                    </div>
                </div>
            </div>';

if (!empty($purchase['comment'])) {
    $allSectionsHTML .= '
            <div style="margin-top: 15px; background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">הערות</div>
                <div style="color: #334155;">' . nl2br(htmlspecialchars($purchase['comment'])) . '</div>
            </div>';
}

$allSectionsHTML .= '
            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-success" onclick="PurchaseCardHandler.editPurchase(\'' . $purchase['unicId'] . '\')">
                    <i class="fas fa-edit"></i> ערוך רכישה
                </button>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 2: פרטי הקבר ===
$graveStatus = $purchase['graveStatus'] ?? 1;
$graveStatusName = $graveStatusNames[$graveStatus] ?? 'לא ידוע';
$graveStatusColor = $graveStatusColors[$graveStatus] ?? '#64748b';

$allSectionsHTML .= '
<!-- סקשן 2: פרטי הקבר -->
<div class="sortable-section" data-section="grave">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-monument"></i> פרטי הקבר</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f5f3ff, #ede9fe);">
            <legend style="padding: 0 15px; font-weight: bold; color: #5b21b6; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-monument"></i> ' . htmlspecialchars($purchase['graveNameHe'] ?? 'קבר') . '
                <span style="background: ' . $graveStatusColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">' . $graveStatusName . '</span>
            </legend>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd; grid-column: span 2;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מיקום מלא</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . $graveLocationStr . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">בית עלמין</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . htmlspecialchars($purchase['cemeteryNameHe'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">גוש</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . htmlspecialchars($purchase['blockNameHe'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">חלקה</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . htmlspecialchars($purchase['plotNameHe'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שורה</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . htmlspecialchars($purchase['lineNameHe'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סוג קבר</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . ($graveTypeNames[$purchase['graveType'] ?? 1] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">כיוון</div>
                    <div style="font-weight: 600; color: #5b21b6;">' . ($graveDirectionNames[$purchase['graveDirection'] ?? 0] ?? '-') . '</div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-purple" style="background: #7c3aed; border-color: #7c3aed; color: white;" onclick="PurchaseCardHandler.viewGrave(\'' . $purchase['graveUnicId'] . '\')">
                    <i class="fas fa-eye"></i> צפה בכרטיס קבר
                </button>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 3: תיק קבורה ===
$allSectionsHTML .= '
<!-- סקשן 3: תיק קבורה -->
<div class="sortable-section" data-section="burial">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-cross"></i> תיק קבורה</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #fffbeb, #fef3c7);">
            <legend style="padding: 0 15px; font-weight: bold; color: #92400e; font-size: 16px;">
                <i class="fas fa-cross"></i> תיק קבורה
            </legend>';

if ($burial) {
    $allSectionsHTML .= '
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a; grid-column: span 2;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם הנפטר/ת</div>
                    <div style="font-weight: 700; color: #92400e; font-size: 16px;">' . htmlspecialchars($burial['deceasedName'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">ת.ז.</div>
                    <div style="font-weight: 600; color: #92400e;">' . htmlspecialchars($burial['deceasedNumId'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר קבורה</div>
                    <div style="font-weight: 600; color: #92400e;">' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך פטירה</div>
                    <div style="font-weight: 600; color: #92400e;">' . formatHebrewDate($burial['dateDeath']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך קבורה</div>
                    <div style="font-weight: 600; color: #92400e;">' . formatHebrewDate($burial['dateBurial']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האב</div>
                    <div style="font-weight: 600; color: #92400e;">' . htmlspecialchars($burial['nameFather'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האם</div>
                    <div style="font-weight: 600; color: #92400e;">' . htmlspecialchars($burial['nameMother'] ?? '-') . '</div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-warning" onclick="PurchaseCardHandler.viewBurial(\'' . $burial['unicId'] . '\')">
                    <i class="fas fa-eye"></i> צפה בתיק קבורה
                </button>
            </div>';
} else {
    $allSectionsHTML .= '
            <div style="text-align: center; padding: 30px; color: #92400e;">
                <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                אין קבורה משויכת לרכישה זו
            </div>
            <div style="text-align: center;">
                <button type="button" class="btn btn-warning" onclick="PurchaseCardHandler.addBurial(\'' . $purchase['unicId'] . '\', \'' . $purchase['graveId'] . '\')">
                    <i class="fas fa-plus"></i> הוסף קבורה
                </button>
            </div>';
}

$allSectionsHTML .= '
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 4: מסמכים ===
$explorerUnicId = htmlspecialchars($purchase['unicId']);
$allSectionsHTML .= '
<!-- סקשן 4: מסמכים -->
<div class="sortable-section" data-section="documents">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
            <legend style="padding: 0 15px; font-weight: bold; color: #1e293b; font-size: 16px;">
                <i class="fas fa-folder-open"></i> מסמכים
            </legend>
            <div id="purchaseExplorer" data-unic-id="' . $explorerUnicId . '" data-entity-type="purchase">
                <div style="text-align: center; padding: 20px; color: #64748b;">
                    <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                    טוען סייר קבצים...
                </div>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>

</div>
<!-- סוף מיכל הסקשנים -->
';

$formBuilder->addCustomHTML($allSectionsHTML);

$formBuilder->addField('unicId', '', 'hidden', [
    'value' => $purchase['unicId']
]);

echo $formBuilder->renderModal();
?>
