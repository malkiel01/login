<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/customerCard-form.php
 * Version: 1.0.0
 * Updated: 2025-12-29
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת כרטיס לקוח עם FormBuilder
 *   - תצוגת פרטי לקוח
 *   - הצגת תיקי רכישה משויכים
 *   - הצגת תיקי קבורה משויכים
 *   - מסמכים
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$formType = 'customerCard';

if (!$itemId) {
    die('<div class="error-message">שגיאה: מזהה לקוח חסר</div>');
}

try {
    $conn = getDBConnection();

    // שליפת נתוני הלקוח
    $stmt = $conn->prepare("
        SELECT
            c.*,
            c.countryNameHe,
            c.cityNameHe
        FROM customers c
        WHERE c.unicId = :id
        AND c.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        die('<div class="error-message">שגיאה: הלקוח לא נמצא</div>');
    }

    // שליפת רכישות של הלקוח
    $stmt = $conn->prepare("
        SELECT
            p.*,
            g.graveNameHe,
            agv.cemeteryNameHe,
            agv.blockNameHe,
            agv.plotNameHe,
            agv.lineNameHe
        FROM purchases p
        LEFT JOIN graves g ON p.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE p.clientId = :customerId AND p.isActive = 1
        ORDER BY p.dateOpening DESC
    ");
    $stmt->execute(['customerId' => $customer['unicId']]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // שליפת קבורות של הלקוח (כנפטר)
    $stmt = $conn->prepare("
        SELECT
            b.*,
            g.graveNameHe,
            agv.cemeteryNameHe,
            agv.blockNameHe,
            agv.plotNameHe,
            agv.lineNameHe
        FROM burials b
        LEFT JOIN graves g ON b.graveId = g.unicId
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE b.clientId = :customerId AND b.isActive = 1
        ORDER BY b.dateBurial DESC
    ");
    $stmt->execute(['customerId' => $customer['unicId']]);
    $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    FormUtils::handleError($e);
}

// פונקציות עזר
function formatHebrewDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function formatPrice($price) {
    if (!$price || $price == 0) {
        return '-';
    }
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
$formBuilder = new FormBuilder('customerCard', $itemId, null);

// סטטוסים וצבעים
$statusNames = [1 => 'פעיל', 2 => 'רוכש', 3 => 'נפטר'];
$statusColors = [1 => '#22c55e', 2 => '#3b82f6', 3 => '#64748b'];
$currentStatus = $customer['statusCustomer'] ?? 1;
$statusName = $statusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $statusColors[$currentStatus] ?? '#64748b';

// מגדר
$genderNames = [1 => 'זכר', 2 => 'נקבה'];
$genderName = $genderNames[$customer['gender'] ?? 0] ?? '-';

// === בניית כל ה-HTML כמחרוזת אחת ===
$allSectionsHTML = '
<style>
    /* תיקון שם המודל */
    #customerCardModal .modal-dialog {
        max-width: 95% !important;
        width: 1200px !important;
    }
    #customerCardModal .modal-body {
        max-height: 85vh !important;
        padding: 20px !important;
    }

    /* סקשנים ניתנים לגרירה */
    .customer-sortable-sections {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .customer-sortable-section {
        position: relative;
        margin-bottom: 15px;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.2s, transform 0.2s;
        border: 2px solid transparent;
    }

    .customer-sortable-section:hover {
        border-color: #94a3b8;
    }

    .customer-sortable-section.sortable-ghost {
        opacity: 0.5;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px dashed #3b82f6 !important;
    }

    .customer-sortable-section.sortable-chosen {
        box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
        border: 2px solid #3b82f6 !important;
        transform: scale(1.01);
        z-index: 1000;
    }

    /* ידית גרירה */
    .customer-section-drag-handle {
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

    .customer-section-drag-handle::before {
        content: "";
        width: 40px;
        height: 4px;
        background: #94a3b8;
        border-radius: 2px;
    }

    .customer-section-drag-handle:hover {
        background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
    }

    /* כפתור צימצום/הרחבה */
    .customer-section-toggle-btn {
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

    .customer-section-toggle-btn:hover {
        background: rgba(100, 116, 139, 0.4);
        color: #334155;
    }

    .customer-section-toggle-btn i {
        transition: transform 0.3s;
    }

    /* כותרת הסקשן במצב מצומצם */
    .customer-section-title {
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

    .customer-sortable-section.collapsed .customer-section-title {
        opacity: 1;
    }

    /* מצב מצומצם */
    .customer-sortable-section.collapsed .customer-section-content {
        display: none;
    }

    .customer-sortable-section.collapsed .customer-section-toggle-btn i {
        transform: rotate(-90deg);
    }

    .customer-sortable-section.collapsed .customer-section-drag-handle {
        border-radius: 10px;
        border-bottom: none;
    }

    .customer-sortable-section.collapsed .customer-section-resize-handle {
        display: none;
    }

    /* תוכן הסקשן */
    .customer-section-content {
        transition: all 0.3s ease;
        overflow: auto;
        min-height: 50px;
    }

    /* ידית שינוי גובה */
    .customer-section-resize-handle {
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

    .customer-section-resize-handle:hover {
        background: linear-gradient(to top, rgba(59, 130, 246, 0.3), transparent);
    }

    .customer-section-resize-handle::after {
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

    .customer-section-resize-handle:hover::after {
        opacity: 1;
    }

    /* כרטיס לקוח */
    .customer-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .customer-info-item {
        background: #f8fafc;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .customer-info-item .label {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 4px;
    }

    .customer-info-item .value {
        font-weight: 600;
        color: #334155;
        font-size: 14px;
    }

    /* רשימת רכישות/קבורות */
    .related-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
        transition: box-shadow 0.2s;
    }

    .related-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .related-item:last-child {
        margin-bottom: 0;
    }

    .related-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .related-item-title {
        font-weight: 600;
        color: #1e293b;
    }

    .related-item-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        color: white;
    }

    .related-item-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 8px;
        font-size: 12px;
    }

    .related-item-detail {
        color: #64748b;
    }

    .related-item-detail strong {
        color: #334155;
    }

    /* תמיכה במובייל */
    @media (max-width: 768px) {
        #customerCardModal {
            padding: 0 !important;
        }

        #customerCardModal .modal-dialog {
            max-width: 100% !important;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
        }

        #customerCardModal .modal-content {
            height: 100% !important;
            max-height: 100% !important;
            border-radius: 0 !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #customerCardModal .modal-header {
            flex-shrink: 0 !important;
            padding: 12px 15px !important;
            min-height: 50px !important;
        }

        #customerCardModal .modal-body {
            flex: 1 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding: 10px !important;
            -webkit-overflow-scrolling: touch !important;
        }

        #customerCardModal .modal-footer {
            flex-shrink: 0 !important;
            padding: 10px 15px !important;
        }

        .customer-section-toggle-btn {
            width: 32px !important;
            height: 32px !important;
            font-size: 14px !important;
        }

        .customer-section-drag-handle {
            height: 32px !important;
        }

        .customer-info-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
</style>

<!-- מיכל לכל הסקשנים -->
<div class="customer-sortable-sections" id="customerSortableSections">

<!-- סקשן 1: פרטי לקוח -->
<div class="customer-sortable-section" data-section="details">
    <div class="customer-section-drag-handle">
        <button type="button" class="customer-section-toggle-btn" onclick="toggleCustomerSection(this)" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="customer-section-title"><i class="fas fa-user"></i> פרטי לקוח</span>
    </div>
    <div class="customer-section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
            <legend style="padding: 0 15px; font-weight: bold; color: #166534; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-user"></i> פרטי לקוח
                <span style="background: ' . $statusColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">' . $statusName . '</span>
            </legend>

            <div class="customer-info-grid">
                <div class="customer-info-item" style="grid-column: span 2;">
                    <div class="label">שם מלא</div>
                    <div class="value" style="font-size: 18px; color: #166534;">' . htmlspecialchars($customer['fullNameHe'] ?? ($customer['firstName'] . ' ' . $customer['lastName'])) . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">ת.ז.</div>
                    <div class="value">' . htmlspecialchars($customer['numId'] ?? '-') . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">מגדר</div>
                    <div class="value">' . $genderName . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">תאריך לידה</div>
                    <div class="value">' . formatHebrewDate($customer['dateBirth']) . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">שם האב</div>
                    <div class="value">' . htmlspecialchars($customer['nameFather'] ?? '-') . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">שם האם</div>
                    <div class="value">' . htmlspecialchars($customer['nameMother'] ?? '-') . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">טלפון</div>
                    <div class="value">' . formatPhone($customer['phone']) . '</div>
                </div>
                <div class="customer-info-item">
                    <div class="label">טלפון נייד</div>
                    <div class="value">' . formatPhone($customer['phoneMobile']) . '</div>
                </div>
                <div class="customer-info-item" style="grid-column: span 2;">
                    <div class="label">כתובת</div>
                    <div class="value">' . htmlspecialchars(trim(($customer['address'] ?? '') . ', ' . ($customer['cityNameHe'] ?? '') . ', ' . ($customer['countryNameHe'] ?? ''), ', ')) . '</div>
                </div>
            </div>

            <div style="margin-top: 15px;">
                <button type="button" class="btn btn-sm btn-primary" onclick="CustomerCardHandler.editCustomer(\'' . $customer['unicId'] . '\')">
                    <i class="fas fa-edit"></i> ערוך לקוח
                </button>
            </div>
        </fieldset>
    </div>
    <div class="customer-section-resize-handle"></div>
</div>
';

// === סקשן 2: תיקי רכישה ===
$purchaseCount = count($purchases);
$allSectionsHTML .= '
<!-- סקשן 2: תיקי רכישה -->
<div class="customer-sortable-section" data-section="purchases">
    <div class="customer-section-drag-handle">
        <button type="button" class="customer-section-toggle-btn" onclick="toggleCustomerSection(this)" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="customer-section-title"><i class="fas fa-shopping-cart"></i> תיקי רכישה (' . $purchaseCount . ')</span>
    </div>
    <div class="customer-section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
            <legend style="padding: 0 15px; font-weight: bold; color: #1e40af; font-size: 16px;">
                <i class="fas fa-shopping-cart"></i> תיקי רכישה
                <span style="background: #3b82f6; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-right: 10px;">' . $purchaseCount . '</span>
            </legend>';

if ($purchaseCount > 0) {
    $purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
    $purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];

    foreach ($purchases as $purchase) {
        $pStatusName = $purchaseStatusNames[$purchase['purchaseStatus']] ?? 'לא ידוע';
        $pStatusColor = $purchaseStatusColors[$purchase['purchaseStatus']] ?? '#64748b';

        $allSectionsHTML .= '
            <div class="related-item">
                <div class="related-item-header">
                    <span class="related-item-title">
                        <i class="fas fa-monument" style="color: #64748b;"></i>
                        ' . htmlspecialchars($purchase['graveNameHe'] ?? 'קבר') . '
                    </span>
                    <span class="related-item-badge" style="background: ' . $pStatusColor . ';">' . $pStatusName . '</span>
                </div>
                <div class="related-item-details">
                    <div class="related-item-detail"><strong>מס׳ רכישה:</strong> ' . htmlspecialchars($purchase['serialPurchaseId'] ?? '-') . '</div>
                    <div class="related-item-detail"><strong>תאריך:</strong> ' . formatHebrewDate($purchase['dateOpening']) . '</div>
                    <div class="related-item-detail"><strong>מחיר:</strong> ' . formatPrice($purchase['price']) . '</div>
                    <div class="related-item-detail"><strong>מיקום:</strong> ' . htmlspecialchars(($purchase['cemeteryNameHe'] ?? '') . ' / ' . ($purchase['blockNameHe'] ?? '')) . '</div>
                </div>
                <div style="margin-top: 8px;">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="CustomerCardHandler.viewPurchase(\'' . $purchase['unicId'] . '\')">
                        <i class="fas fa-eye"></i> צפה
                    </button>
                </div>
            </div>';
    }
} else {
    $allSectionsHTML .= '
            <div style="text-align: center; padding: 20px; color: #64748b;">
                <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                אין רכישות משויכות ללקוח זה
            </div>';
}

$allSectionsHTML .= '
        </fieldset>
    </div>
    <div class="customer-section-resize-handle"></div>
</div>
';

// === סקשן 3: תיקי קבורה ===
$burialCount = count($burials);
$allSectionsHTML .= '
<!-- סקשן 3: תיקי קבורה -->
<div class="customer-sortable-section" data-section="burials">
    <div class="customer-section-drag-handle">
        <button type="button" class="customer-section-toggle-btn" onclick="toggleCustomerSection(this)" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="customer-section-title"><i class="fas fa-cross"></i> תיקי קבורה (' . $burialCount . ')</span>
    </div>
    <div class="customer-section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">
            <legend style="padding: 0 15px; font-weight: bold; color: #92400e; font-size: 16px;">
                <i class="fas fa-cross"></i> תיקי קבורה
                <span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-right: 10px;">' . $burialCount . '</span>
            </legend>';

if ($burialCount > 0) {
    foreach ($burials as $burial) {
        $allSectionsHTML .= '
            <div class="related-item">
                <div class="related-item-header">
                    <span class="related-item-title">
                        <i class="fas fa-monument" style="color: #64748b;"></i>
                        ' . htmlspecialchars($burial['graveNameHe'] ?? 'קבר') . '
                    </span>
                </div>
                <div class="related-item-details">
                    <div class="related-item-detail"><strong>מס׳ קבורה:</strong> ' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '</div>
                    <div class="related-item-detail"><strong>תאריך פטירה:</strong> ' . formatHebrewDate($burial['dateDeath']) . '</div>
                    <div class="related-item-detail"><strong>תאריך קבורה:</strong> ' . formatHebrewDate($burial['dateBurial']) . '</div>
                    <div class="related-item-detail"><strong>מיקום:</strong> ' . htmlspecialchars(($burial['cemeteryNameHe'] ?? '') . ' / ' . ($burial['blockNameHe'] ?? '')) . '</div>
                </div>
                <div style="margin-top: 8px;">
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="CustomerCardHandler.viewBurial(\'' . $burial['unicId'] . '\')">
                        <i class="fas fa-eye"></i> צפה
                    </button>
                </div>
            </div>';
    }
} else {
    $allSectionsHTML .= '
            <div style="text-align: center; padding: 20px; color: #64748b;">
                <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                אין קבורות משויכות ללקוח זה
            </div>';
}

$allSectionsHTML .= '
        </fieldset>
    </div>
    <div class="customer-section-resize-handle"></div>
</div>
';

// === סקשן 4: מסמכים ===
$explorerUnicId = htmlspecialchars($customer['unicId']);
$allSectionsHTML .= '
<!-- סקשן 4: מסמכים -->
<div class="customer-sortable-section" data-section="documents">
    <div class="customer-section-drag-handle">
        <button type="button" class="customer-section-toggle-btn" onclick="toggleCustomerSection(this)" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="customer-section-title"><i class="fas fa-folder-open"></i> מסמכים</span>
    </div>
    <div class="customer-section-content">
        <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
            <legend style="padding: 0 15px; font-weight: bold; color: #475569; font-size: 16px;">
                <i class="fas fa-folder-open"></i> מסמכים
            </legend>
            <div id="customerExplorer" data-unic-id="' . $explorerUnicId . '" data-entity-type="customer">
                <div style="text-align: center; padding: 20px; color: #666;">
                    <i class="fas fa-spinner fa-spin"></i> טוען סייר קבצים...
                </div>
            </div>
        </fieldset>
    </div>
    <div class="customer-section-resize-handle"></div>
</div>

</div>
<!-- סוף מיכל הסקשנים -->
';

// === הוספת כל הסקשנים כ-HTML אחד ===
$formBuilder->addCustomHTML($allSectionsHTML);

// שדה מוסתר - unicId
$formBuilder->addField('unicId', '', 'hidden', [
    'value' => $customer['unicId']
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>
