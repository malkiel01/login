<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/burialCard-form.php
 * Version: 1.0.0
 * Updated: 2025-12-30
 * Author: Malkiel
 * Description: כרטיס קבורה - תצוגת פרטי קבורה עם סקשנים ניתנים לגרירה
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$formType = 'burialCard';

if (!$itemId) {
    die('<div class="error-message">שגיאה: מזהה קבורה חסר</div>');
}

try {
    $conn = getDBConnection();

    // שליפת נתוני הקבורה עם פרטי נפטר, קבר ורכישה
    $stmt = $conn->prepare("
        SELECT b.*,
            c.firstName, c.lastName, c.fullNameHe as deceasedFullNameHe,
            c.numId as deceasedNumId, c.phone as deceasedPhone, c.phoneMobile as deceasedPhoneMobile,
            c.dateBirth as deceasedDateBirth, c.nameFather as deceasedNameFather, c.nameMother as deceasedNameMother,
            c.address as deceasedAddress, c.gender as deceasedGender,
            g.graveNameHe, g.graveStatus, g.unicId as graveUnicId,
            agv.cemeteryNameHe, agv.blockNameHe, agv.plotNameHe, agv.lineNameHe, agv.areaGraveNameHe,
            p.unicId as purchaseUnicId, p.serialPurchaseId, p.purchaseStatus, p.price as purchasePrice,
            p.dateOpening as purchaseDateOpening, p.numOfPayments,
            pc.fullNameHe as purchaserFullNameHe, pc.numId as purchaserNumId
        FROM burials b
        LEFT JOIN customers c ON b.clientId = c.unicId
        LEFT JOIN graves g ON b.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        LEFT JOIN purchases p ON b.purchaseId = p.unicId AND p.isActive = 1
        LEFT JOIN customers pc ON p.clientId = pc.unicId
        WHERE b.unicId = :id AND b.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $burial = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$burial) {
        die('<div class="error-message">שגיאה: הקבורה לא נמצאה</div>');
    }

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
$formBuilder = new FormBuilder('burialCard', $itemId, null);

// סטטוסי קבורה
$burialStatusNames = [1 => 'פתוח', 2 => 'בתהליך', 3 => 'הושלם', 4 => 'בוטל'];
$burialStatusColors = [1 => '#3b82f6', 2 => '#f59e0b', 3 => '#10b981', 4 => '#ef4444'];
$currentStatus = $burial['burialStatus'] ?? 1;
$statusName = $burialStatusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $burialStatusColors[$currentStatus] ?? '#64748b';

// סטטוסי רכישה
$purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];

// שם הנפטר
$deceasedName = htmlspecialchars($burial['deceasedFullNameHe'] ?? ($burial['firstName'] . ' ' . $burial['lastName']) ?? 'לא ידוע');

// מיקום הקבר
$graveLocation = [];
if ($burial['cemeteryNameHe']) $graveLocation[] = $burial['cemeteryNameHe'];
if ($burial['blockNameHe']) $graveLocation[] = 'גוש ' . $burial['blockNameHe'];
if ($burial['plotNameHe']) $graveLocation[] = 'חלקה ' . $burial['plotNameHe'];
if ($burial['lineNameHe']) $graveLocation[] = 'שורה ' . $burial['lineNameHe'];
$graveLocationStr = implode(' / ', $graveLocation) ?: '-';

// מגדר
$genderNames = ['male' => 'זכר', 'female' => 'נקבה', 'M' => 'זכר', 'F' => 'נקבה', 1 => 'זכר', 2 => 'נקבה'];
$genderName = $genderNames[$burial['deceasedGender'] ?? ''] ?? '-';

// === בניית HTML ===
$allSectionsHTML = '
<style>
    /* רוחב המודל */
    #burialCardFormModal .modal-dialog {
        max-width: 95% !important;
        width: 1400px !important;
    }
    #burialCardFormModal .modal-body {
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

    /* ידית שינוי גובה */
    .section-resize-handle {
        height: 8px;
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        cursor: ns-resize;
        border-radius: 0 0 10px 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-top: 1px solid #cbd5e1;
    }

    .section-resize-handle::before {
        content: "";
        width: 30px;
        height: 3px;
        background: #94a3b8;
        border-radius: 2px;
    }

    /* תוכן סקשן */
    .section-content {
        overflow: auto;
        transition: max-height 0.3s;
    }
</style>

<div class="sortable-sections" id="burialSortableSections">

<!-- סקשן 1: פרטי קבורה -->
<div class="sortable-section" data-section="burial">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-cross"></i> פרטי קבורה</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #fdf4ff, #fae8ff);">
            <legend style="padding: 0 15px; font-weight: bold; color: #86198f; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-cross"></i> קבורה #' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '
                <span style="background: ' . $statusColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">' . $statusName . '</span>
            </legend>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #f0abfc;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר קבורה</div>
                    <div style="font-weight: 600; color: #86198f;">' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #f0abfc;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך פטירה</div>
                    <div style="font-weight: 600; color: #86198f;">' . formatHebrewDate($burial['dateDeath']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #f0abfc;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך קבורה</div>
                    <div style="font-weight: 600; color: #86198f;">' . formatHebrewDate($burial['dateBurial']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #f0abfc;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שעת קבורה</div>
                    <div style="font-weight: 600; color: #86198f;">' . htmlspecialchars($burial['timeBurial'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #f0abfc; grid-column: span 2;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">קבר</div>
                    <div style="font-weight: 600; color: #86198f;">
                        <i class="fas fa-monument" style="margin-left: 5px;"></i>
                        ' . htmlspecialchars($burial['graveNameHe'] ?? '-') . '
                        <span style="color: #64748b; font-weight: normal; font-size: 12px;">(' . $graveLocationStr . ')</span>
                    </div>
                </div>
            </div>';

if (!empty($burial['comment'])) {
    $allSectionsHTML .= '
            <div style="margin-top: 15px; background: white; padding: 12px; border-radius: 8px; border: 1px solid #f0abfc;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">הערות</div>
                <div style="color: #334155;">' . nl2br(htmlspecialchars($burial['comment'])) . '</div>
            </div>';
}

$allSectionsHTML .= '
            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm" style="background: #86198f; color: white;" onclick="BurialCardHandler.editBurial(\'' . $burial['unicId'] . '\')">
                    <i class="fas fa-edit"></i> ערוך קבורה
                </button>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 2: פרטי הנפטר ===
$allSectionsHTML .= '
<!-- סקשן 2: פרטי הנפטר -->
<div class="sortable-section" data-section="deceased">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-user"></i> פרטי הנפטר/ת</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #eff6ff, #dbeafe);">
            <legend style="padding: 0 15px; font-weight: bold; color: #1e40af; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-user"></i> ' . $deceasedName . '
            </legend>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">ת.ז.</div>
                    <div style="font-weight: 600; color: #1e40af;">' . htmlspecialchars($burial['deceasedNumId'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מגדר</div>
                    <div style="font-weight: 600; color: #1e40af;">' . $genderName . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך לידה</div>
                    <div style="font-weight: 600; color: #1e40af;">' . formatHebrewDate($burial['deceasedDateBirth']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האב</div>
                    <div style="font-weight: 600; color: #1e40af;">' . htmlspecialchars($burial['deceasedNameFather'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האם</div>
                    <div style="font-weight: 600; color: #1e40af;">' . htmlspecialchars($burial['deceasedNameMother'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">טלפון</div>
                    <div style="font-weight: 600; color: #1e40af;">' . formatPhone($burial['deceasedPhone']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe; grid-column: span 2;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">כתובת</div>
                    <div style="font-weight: 600; color: #1e40af;">' . htmlspecialchars($burial['deceasedAddress'] ?? '-') . '</div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-primary" onclick="BurialCardHandler.viewCustomer(\'' . $burial['clientId'] . '\')">
                    <i class="fas fa-eye"></i> צפה בכרטיס לקוח
                </button>
            </div>
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 3: פרטי רכישה ===
$allSectionsHTML .= '
<!-- סקשן 3: פרטי רכישה -->
<div class="sortable-section" data-section="purchase">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-shopping-cart"></i> פרטי רכישה</span>
    </div>
    <div class="section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
            <legend style="padding: 0 15px; font-weight: bold; color: #166534; font-size: 16px;">
                <i class="fas fa-shopping-cart"></i> רכישה
            </legend>';

if ($burial['purchaseUnicId']) {
    $purchaseStatus = $purchaseStatusNames[$burial['purchaseStatus'] ?? 1] ?? '-';
    $allSectionsHTML .= '
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר רכישה</div>
                    <div style="font-weight: 600; color: #166534;">' . htmlspecialchars($burial['serialPurchaseId'] ?? '-') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סטטוס</div>
                    <div style="font-weight: 600; color: #166534;">' . $purchaseStatus . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך פתיחה</div>
                    <div style="font-weight: 600; color: #166534;">' . formatHebrewDate($burial['purchaseDateOpening']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">סכום</div>
                    <div style="font-weight: 700; color: #166534; font-size: 18px;">' . formatPrice($burial['purchasePrice']) . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר תשלומים</div>
                    <div style="font-weight: 600; color: #166534;">' . htmlspecialchars($burial['numOfPayments'] ?? '1') . '</div>
                </div>
                <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">רוכש</div>
                    <div style="font-weight: 600; color: #166534;">' . htmlspecialchars($burial['purchaserFullNameHe'] ?? '-') . '</div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-success" onclick="BurialCardHandler.viewPurchase(\'' . $burial['purchaseUnicId'] . '\')">
                    <i class="fas fa-eye"></i> צפה בכרטיס רכישה
                </button>
            </div>';
} else {
    $allSectionsHTML .= '
            <div style="text-align: center; padding: 30px; color: #166534;">
                <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                <div style="font-size: 16px; font-weight: 500;">אין רכישה משויכת לקבורה זו</div>
                <div style="font-size: 13px; color: #64748b; margin-top: 5px;">הקבורה בוצעה ללא רכישה מקדימה</div>
            </div>';
}

$allSectionsHTML .= '
        </fieldset>
    </div>
    <div class="section-resize-handle"></div>
</div>
';

// === סקשן 4: מסמכים ===
$explorerUnicId = htmlspecialchars($burial['unicId']);
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
            <div id="burialExplorer" data-unic-id="' . $explorerUnicId . '" data-entity-type="burial">
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
    'value' => $burial['unicId']
]);

echo $formBuilder->renderModal();
?>
