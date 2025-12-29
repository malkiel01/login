<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/graveCard-form.php
 * Version: 1.0.0
 * Updated: 2025-11-25
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירת כרטיס קבר עם FormBuilder
 *   - תצוגת פרטי קבר + היררכיה
 *   - הצגת תיק רכישה (אם קיים)
 *   - הצגת תיק קבורה (אם קיים)
 *   - כפתורים דינמיים לפי סטטוס
 *   - אפשרות שמירת קבר (סטטוס 1→4)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// === קבלת פרמטרים ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$formType = 'graveCard';

if (!$itemId) {
    die('<div class="error-message">שגיאה: מזהה קבר חסר</div>');
}

try {
    $conn = getDBConnection();
    
    // שליפת נתוני הקבר עם היררכיה מלאה
    $stmt = $conn->prepare("
        SELECT 
            g.*,
            agv.areaGraveNameHe,
            agv.lineNameHe,
            agv.plotNameHe,
            agv.blockNameHe,
            agv.cemeteryNameHe,
            agv.cemeteryId,
            agv.blockId,
            agv.plotId,
            agv.lineId
        FROM graves g
        LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
        WHERE g.unicId = :id
        AND g.isActive = 1
    ");
    $stmt->execute(['id' => $itemId]);
    $grave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$grave) {
        die('<div class="error-message">שגיאה: הקבר לא נמצא</div>');
    }
    
    // שליפת פרטי רכישה אם קיימים
    $purchase = null;
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            c.fullNameHe as clientFullNameHe,
            c.numId as clientNumId,
            c.phone,
            c.phoneMobile,
            contact.fullNameHe as contactFullNameHe
        FROM purchases p
        LEFT JOIN customers c ON p.clientId = c.unicId
        LEFT JOIN customers contact ON p.contactId = contact.unicId
        WHERE p.graveId = :graveId AND p.isActive = 1
        LIMIT 1
    ");
    $stmt->execute(['graveId' => $grave['unicId']]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // שליפת פרטי קבורה אם קיימים
    $burial = null;
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            c.fullNameHe as clientFullNameHe,
            c.numId as clientNumId,
            c.nameFather as clientNameFather,
            c.nameMother as clientNameMother,
            contact.fullNameHe as contactFullNameHe
        FROM burials b
        LEFT JOIN customers c ON b.clientId = c.unicId
        LEFT JOIN customers contact ON b.contactId = contact.unicId
        WHERE b.graveId = :graveId AND b.isActive = 1
        LIMIT 1
    ");
    $stmt->execute(['graveId' => $grave['unicId']]);
    $burial = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
$formBuilder = new FormBuilder('graveCard', $itemId, null);

// סטטוסים וצבעים
$statusNames = [1 => 'פנוי', 2 => 'נרכש', 3 => 'קבור', 4 => 'שמור'];
$statusColors = [1 => '#22c55e', 2 => '#3b82f6', 3 => '#f59e0b', 4 => '#8b5cf6'];
$currentStatus = $grave['graveStatus'] ?? 1;
$statusName = $statusNames[$currentStatus] ?? 'לא ידוע';
$statusColor = $statusColors[$currentStatus] ?? '#64748b';

// HTML מותאם - עם GridStack לגמישות מקסימלית
$headerHTML = '
 <!-- GridStack CSS & JS -->
 <link href="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack.min.css" rel="stylesheet"/>
 <link href="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack-extra.min.css" rel="stylesheet"/>
 <script src="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack-all.js"></script>

 <style>
     #graveCardFormModal .modal-dialog {
         max-width: 98% !important;
         width: 1500px !important;
     }
     #graveCardFormModal .modal-body {
         max-height: 90vh !important;
         padding: 15px !important;
         overflow-y: auto;
     }

     /* GridStack Customization */
     .grid-stack {
         background: transparent;
     }

     .grid-stack-item-content {
         background: white;
         border-radius: 12px;
         box-shadow: 0 2px 8px rgba(0,0,0,0.08);
         border: 1px solid #e2e8f0;
         overflow: hidden;
         display: flex;
         flex-direction: column;
     }

     /* כותרת פאנל - ניתנת לגרירה */
     .panel-header {
         background: linear-gradient(135deg, #f8fafc, #f1f5f9);
         padding: 12px 15px;
         border-bottom: 1px solid #e2e8f0;
         cursor: grab;
         display: flex;
         align-items: center;
         justify-content: space-between;
         user-select: none;
     }

     .panel-header:active {
         cursor: grabbing;
     }

     .panel-header-title {
         font-weight: 700;
         font-size: 14px;
         color: #1e293b;
         display: flex;
         align-items: center;
         gap: 8px;
     }

     .panel-header-title i {
         font-size: 16px;
     }

     .panel-header-badge {
         padding: 4px 10px;
         border-radius: 12px;
         font-size: 11px;
         font-weight: 600;
         color: white;
     }

     .panel-header-actions {
         display: flex;
         gap: 5px;
     }

     .panel-drag-handle {
         color: #94a3b8;
         font-size: 12px;
     }

     .panel-body {
         padding: 15px;
         flex: 1;
         overflow: auto;
     }

     /* פאנל תמונה */
     .panel-image .panel-header {
         background: linear-gradient(135deg, #1e293b, #334155);
         border-bottom: none;
     }

     .panel-image .panel-header-title {
         color: white;
     }

     .panel-image .panel-drag-handle {
         color: #94a3b8;
     }

     .panel-image .panel-body {
         background: #1e293b;
         padding: 0;
     }

     /* פאנל רכישה */
     .panel-purchase .panel-header {
         background: linear-gradient(135deg, #eff6ff, #dbeafe);
         border-bottom: 1px solid #bfdbfe;
     }

     .panel-purchase .panel-header-title {
         color: #1e40af;
     }

     .panel-purchase .grid-stack-item-content {
         border-color: #bfdbfe;
     }

     /* פאנל קבורה */
     .panel-burial .panel-header {
         background: linear-gradient(135deg, #fffbeb, #fef3c7);
         border-bottom: 1px solid #fde68a;
     }

     .panel-burial .panel-header-title {
         color: #92400e;
     }

     .panel-burial .grid-stack-item-content {
         border-color: #fde68a;
     }

     /* פאנל מסמכים */
     .panel-documents .panel-header {
         background: linear-gradient(135deg, #f0fdf4, #dcfce7);
         border-bottom: 1px solid #bbf7d0;
     }

     .panel-documents .panel-header-title {
         color: #166534;
     }

     .panel-documents .grid-stack-item-content {
         border-color: #bbf7d0;
     }

     /* פאנל פרטים */
     .panel-details .panel-header {
         background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
         border-bottom: 1px solid #bae6fd;
     }

     .panel-details .panel-header-title {
         color: #0369a1;
     }

     .panel-details .grid-stack-item-content {
         border-color: #bae6fd;
     }

     /* תוכן פאנלים */
     .info-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
         gap: 10px;
     }

     .info-item {
         background: #f8fafc;
         padding: 10px;
         border-radius: 8px;
         border: 1px solid #e2e8f0;
     }

     .info-item-label {
         font-size: 10px;
         color: #64748b;
         margin-bottom: 2px;
     }

     .info-item-value {
         font-weight: 600;
         color: #334155;
         font-size: 13px;
     }

     /* מציג תמונות */
     .grave-image-main {
         flex: 1;
         display: flex;
         align-items: center;
         justify-content: center;
         position: relative;
         overflow: hidden;
         min-height: 200px;
     }

     .grave-image-main img {
         max-width: 100%;
         max-height: 100%;
         object-fit: contain;
         cursor: pointer;
     }

     .grave-image-placeholder {
         color: #64748b;
         text-align: center;
         cursor: pointer;
     }

     .grave-image-placeholder i {
         font-size: 48px;
         margin-bottom: 10px;
         display: block;
     }

     .grave-image-nav {
         position: absolute;
         top: 50%;
         transform: translateY(-50%);
         background: rgba(0,0,0,0.5);
         color: white;
         border: none;
         width: 32px;
         height: 32px;
         border-radius: 50%;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
         transition: background 0.2s;
     }

     .grave-image-nav:hover {
         background: rgba(0,0,0,0.8);
     }

     .grave-image-nav.prev { right: 8px; }
     .grave-image-nav.next { left: 8px; }

     .grave-image-controls {
         background: #334155;
         padding: 10px;
         display: flex;
         justify-content: space-between;
         align-items: center;
     }

     .grave-image-counter {
         color: #94a3b8;
         font-size: 12px;
     }

     .grave-image-actions {
         display: flex;
         gap: 8px;
     }

     .grave-image-btn {
         background: #475569;
         color: white;
         border: none;
         padding: 6px 10px;
         border-radius: 6px;
         font-size: 12px;
         cursor: pointer;
         transition: background 0.2s;
     }

     .grave-image-btn:hover {
         background: #64748b;
     }

     .grave-image-btn.danger:hover {
         background: #ef4444;
     }

     /* Lightbox */
     .image-lightbox {
         display: none;
         position: fixed;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         background: rgba(0, 0, 0, 0.95);
         z-index: 10000;
         justify-content: center;
         align-items: center;
         flex-direction: column;
     }

     .image-lightbox.active {
         display: flex;
     }

     .lightbox-container {
         position: relative;
         max-width: 95%;
         max-height: 85%;
         overflow: hidden;
         display: flex;
         justify-content: center;
         align-items: center;
     }

     .lightbox-image {
         max-width: 100%;
         max-height: 100%;
         object-fit: contain;
         transition: transform 0.3s ease;
         cursor: grab;
     }

     .lightbox-close {
         position: absolute;
         top: 20px;
         left: 20px;
         background: rgba(255, 255, 255, 0.15);
         color: white;
         border: none;
         width: 50px;
         height: 50px;
         border-radius: 50%;
         font-size: 24px;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
         z-index: 10001;
     }

     .lightbox-controls {
         position: absolute;
         bottom: 30px;
         left: 50%;
         transform: translateX(-50%);
         display: flex;
         gap: 15px;
         background: rgba(0, 0, 0, 0.7);
         padding: 12px 20px;
         border-radius: 30px;
         z-index: 10001;
     }

     .lightbox-btn {
         background: rgba(255, 255, 255, 0.2);
         color: white;
         border: none;
         width: 44px;
         height: 44px;
         border-radius: 50%;
         font-size: 16px;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
     }

     .lightbox-zoom-level {
         color: white;
         font-size: 14px;
         display: flex;
         align-items: center;
         padding: 0 10px;
         min-width: 60px;
         justify-content: center;
     }

     .lightbox-nav {
         position: absolute;
         top: 50%;
         transform: translateY(-50%);
         background: rgba(255, 255, 255, 0.15);
         color: white;
         border: none;
         width: 50px;
         height: 50px;
         border-radius: 50%;
         font-size: 20px;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
         z-index: 10001;
     }

     .lightbox-nav.prev { right: 30px; }
     .lightbox-nav.next { left: 30px; }

     /* כפתור איפוס לייאאוט */
     .layout-controls {
         display: flex;
         gap: 10px;
         margin-bottom: 10px;
         justify-content: flex-end;
     }

     .layout-btn {
         background: #f1f5f9;
         border: 1px solid #e2e8f0;
         color: #64748b;
         padding: 6px 12px;
         border-radius: 6px;
         font-size: 12px;
         cursor: pointer;
         display: flex;
         align-items: center;
         gap: 5px;
     }

     .layout-btn:hover {
         background: #e2e8f0;
         color: #334155;
     }

     /* Responsive */
     @media (max-width: 768px) {
         .grid-stack > .grid-stack-item {
             width: 100% !important;
             position: relative !important;
             left: 0 !important;
         }
         .info-grid {
             grid-template-columns: repeat(2, 1fr);
         }
     }

     /* סטטוס badge */
     .grave-status-badge {
         padding: 6px 12px;
         border-radius: 15px;
         font-weight: 600;
         font-size: 12px;
         color: white;
     }
 </style>

 <!-- כפתורי שליטה בלייאאוט -->
 <div class="layout-controls">
     <button type="button" class="layout-btn" onclick="GraveCardLayout.resetLayout()" title="איפוס פריסה">
         <i class="fas fa-undo"></i> איפוס פריסה
     </button>
     <button type="button" class="layout-btn" onclick="GraveCardLayout.saveLayout()" title="שמור פריסה">
         <i class="fas fa-save"></i> שמור פריסה
     </button>
 </div>

 <!-- GridStack Container -->
 <div class="grid-stack" id="graveCardGrid">

     <!-- פאנל תמונות -->
     <div class="grid-stack-item panel-image" gs-x="0" gs-y="0" gs-w="3" gs-h="4" gs-id="image">
         <div class="grid-stack-item-content">
             <div class="panel-header">
                 <span class="panel-header-title">
                     <i class="fas fa-image"></i> תמונות
                 </span>
                 <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
             </div>
             <div class="panel-body" id="graveImageViewer" data-unic-id="' . htmlspecialchars($grave['unicId']) . '">
                 <div class="grave-image-main">
                     <div class="grave-image-placeholder" id="graveImagePlaceholder">
                         <i class="fas fa-image"></i>
                         <div>אין תמונות</div>
                         <div style="font-size: 11px; margin-top: 5px;">לחץ להעלאה</div>
                     </div>
                     <img id="graveImageDisplay" style="display: none;" />
                     <button type="button" class="grave-image-nav prev" id="graveImagePrev" style="display: none;">
                         <i class="fas fa-chevron-right"></i>
                     </button>
                     <button type="button" class="grave-image-nav next" id="graveImageNext" style="display: none;">
                         <i class="fas fa-chevron-left"></i>
                     </button>
                 </div>
                 <div class="grave-image-controls">
                     <span class="grave-image-counter" id="graveImageCounter">0 / 0</span>
                     <div class="grave-image-actions">
                         <button type="button" class="grave-image-btn" onclick="GraveImageViewer.upload()" title="העלה תמונה">
                             <i class="fas fa-upload"></i>
                         </button>
                         <button type="button" class="grave-image-btn danger" onclick="GraveImageViewer.delete()" title="מחק תמונה" id="graveImageDeleteBtn" style="display: none;">
                             <i class="fas fa-trash"></i>
                         </button>
                     </div>
                 </div>
                 <input type="file" id="graveImageInput" accept="image/*" style="display: none;" onchange="GraveImageViewer.handleUpload(event)" />
             </div>
         </div>
     </div>

     <!-- פאנל פרטי קבר -->
     <div class="grid-stack-item panel-details" gs-x="3" gs-y="0" gs-w="9" gs-h="4" gs-id="details">
         <div class="grid-stack-item-content">
             <div class="panel-header">
                 <span class="panel-header-title">
                     <i class="fas fa-monument"></i>
                     ' . htmlspecialchars($grave['graveNameHe'] ?? 'קבר') . '
                 </span>
                 <div style="display: flex; align-items: center; gap: 10px;">
                     <span class="grave-status-badge" style="background: ' . $statusColor . ';">' . $statusName . '</span>
                     <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                 </div>
             </div>
             <div class="panel-body">
                 <!-- היררכיה -->
                 <div style="margin-bottom: 15px;">
                     <div style="font-size: 12px; color: #64748b; margin-bottom: 8px; font-weight: 600;">
                         <i class="fas fa-sitemap"></i> מיקום בהיררכיה
                     </div>
                     <div class="info-grid" style="grid-template-columns: repeat(5, 1fr);">
                         <div class="info-item" style="background: #f0f9ff; border-color: #bae6fd;">
                             <div class="info-item-label">בית עלמין</div>
                             <div class="info-item-value" style="color: #0c4a6e;">' . htmlspecialchars($grave['cemeteryNameHe'] ?? '-') . '</div>
                         </div>
                         <div class="info-item" style="background: #f0f9ff; border-color: #bae6fd;">
                             <div class="info-item-label">גוש</div>
                             <div class="info-item-value" style="color: #0c4a6e;">' . htmlspecialchars($grave['blockNameHe'] ?? '-') . '</div>
                         </div>
                         <div class="info-item" style="background: #f0f9ff; border-color: #bae6fd;">
                             <div class="info-item-label">חלקה</div>
                             <div class="info-item-value" style="color: #0c4a6e;">' . htmlspecialchars($grave['plotNameHe'] ?? '-') . '</div>
                         </div>
                         <div class="info-item" style="background: #f0f9ff; border-color: #bae6fd;">
                             <div class="info-item-label">שורה</div>
                             <div class="info-item-value" style="color: #0c4a6e;">' . htmlspecialchars($grave['lineNameHe'] ?? '-') . '</div>
                         </div>
                         <div class="info-item" style="background: #f0f9ff; border-color: #bae6fd;">
                             <div class="info-item-label">אחוזת קבר</div>
                             <div class="info-item-value" style="color: #0c4a6e;">' . htmlspecialchars($grave['areaGraveNameHe'] ?? '-') . '</div>
                         </div>
                     </div>
                 </div>

                 <!-- פרטי קבר -->
                 <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
                     <div class="info-item">
                         <div class="info-item-label">סוג חלקה</div>
                         <div class="info-item-value">' . ($grave['plotType'] == 1 ? 'פטורה' : ($grave['plotType'] == 2 ? 'חריגה' : 'סגורה')) . '</div>
                     </div>
                     <div class="info-item">
                         <div class="info-item-label">מיקום בשורה</div>
                         <div class="info-item-value">' . ($grave['graveLocation'] == 1 ? 'עליון' : ($grave['graveLocation'] == 2 ? 'תחתון' : 'אמצעי')) . '</div>
                     </div>
                     <div class="info-item">
                         <div class="info-item-label">קבר קטן</div>
                         <div class="info-item-value">' . ($grave['isSmallGrave'] ? 'כן' : 'לא') . '</div>
                     </div>
                     <div class="info-item">
                         <div class="info-item-label">עלות בנייה</div>
                         <div class="info-item-value" style="color: #059669;">' . formatPrice($grave['constructionCost']) . '</div>
                     </div>
                 </div>

                 <button type="button" class="btn btn-sm btn-primary" onclick="openGraveEdit(\'' . $grave['areaGraveId'] . '\')" style="margin-top: 15px; background: linear-gradient(135deg, #0284c7, #0369a1); border: none; padding: 8px 16px;">
                     <i class="fas fa-edit"></i> ערוך אחוזת קבר
                 </button>
             </div>
         </div>
     </div>
 </div>

 <!-- Lightbox -->
 <div class="image-lightbox" id="imageLightbox">
     <button type="button" class="lightbox-close" onclick="GraveImageViewer.closeLightbox()" title="סגור">
         <i class="fas fa-times"></i>
     </button>
     <button type="button" class="lightbox-nav prev" id="lightboxPrev" onclick="GraveImageViewer.lightboxPrev()">
         <i class="fas fa-chevron-right"></i>
     </button>
     <button type="button" class="lightbox-nav next" id="lightboxNext" onclick="GraveImageViewer.lightboxNext()">
         <i class="fas fa-chevron-left"></i>
     </button>
     <div class="lightbox-container">
         <img class="lightbox-image" id="lightboxImage" />
     </div>
     <div class="lightbox-controls">
         <button type="button" class="lightbox-btn" onclick="GraveImageViewer.zoomOut()" title="הקטן">
             <i class="fas fa-search-minus"></i>
         </button>
         <span class="lightbox-zoom-level" id="lightboxZoomLevel">100%</span>
         <button type="button" class="lightbox-btn" onclick="GraveImageViewer.zoomIn()" title="הגדל">
             <i class="fas fa-search-plus"></i>
         </button>
         <button type="button" class="lightbox-btn" onclick="GraveImageViewer.zoomReset()" title="אפס">
             <i class="fas fa-expand"></i>
         </button>
     </div>
 </div>';

$formBuilder->addCustomHTML($headerHTML);

// בניית פאנלים דינמיים ל-GridStack

// הכנת נתוני רכישה
$purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
$purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];

// הכנת HTML לפאנלים
$gridPanels = '';

// פאנל רכישה
if ($purchase) {
    $pStatusName = $purchaseStatusNames[$purchase['purchaseStatus']] ?? 'לא ידוע';
    $pStatusColor = $purchaseStatusColors[$purchase['purchaseStatus']] ?? '#64748b';

    $gridPanels .= '
    <div class="grid-stack-item panel-purchase" gs-x="0" gs-y="4" gs-w="6" gs-h="3" gs-id="purchase">
        <div class="grid-stack-item-content">
            <div class="panel-header">
                <span class="panel-header-title">
                    <i class="fas fa-shopping-cart"></i> תיק רכישה
                </span>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="panel-header-badge" style="background: ' . $pStatusColor . ';">' . $pStatusName . '</span>
                    <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                </div>
            </div>
            <div class="panel-body">
                <div class="info-grid">
                    <div class="info-item" style="background: #eff6ff; border-color: #bfdbfe;">
                        <div class="info-item-label">שם הרוכש</div>
                        <div class="info-item-value" style="color: #1e3a8a;">' . htmlspecialchars($purchase['clientFullNameHe'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #eff6ff; border-color: #bfdbfe;">
                        <div class="info-item-label">ת.ז. רוכש</div>
                        <div class="info-item-value" style="color: #1e3a8a;">' . htmlspecialchars($purchase['clientNumId'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #eff6ff; border-color: #bfdbfe;">
                        <div class="info-item-label">מספר רכישה</div>
                        <div class="info-item-value" style="color: #1e3a8a;">' . htmlspecialchars($purchase['serialPurchaseId'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #eff6ff; border-color: #bfdbfe;">
                        <div class="info-item-label">מחיר</div>
                        <div class="info-item-value" style="color: #059669;">' . formatPrice($purchase['price']) . '</div>
                    </div>
                    <div class="info-item" style="background: #eff6ff; border-color: #bfdbfe;">
                        <div class="info-item-label">תאריך רכישה</div>
                        <div class="info-item-value" style="color: #1e3a8a;">' . formatHebrewDate($purchase['dateOpening']) . '</div>
                    </div>
                    <div class="info-item" style="background: #eff6ff; border-color: #bfdbfe;">
                        <div class="info-item-label">טלפון</div>
                        <div class="info-item-value" style="color: #1e3a8a;">' . formatPhone($purchase['phone'] ?? $purchase['phoneMobile']) . '</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-primary" onclick="GraveCardHandler.editPurchase(\'' . $purchase['unicId'] . '\')" style="margin-top: 12px;">
                    <i class="fas fa-edit"></i> ערוך רכישה
                </button>
            </div>
        </div>
    </div>';
} elseif ($grave['graveStatus'] == 1) {
    $gridPanels .= '
    <div class="grid-stack-item panel-purchase" gs-x="0" gs-y="4" gs-w="6" gs-h="3" gs-id="purchase">
        <div class="grid-stack-item-content">
            <div class="panel-header">
                <span class="panel-header-title">
                    <i class="fas fa-shopping-cart"></i> תיק רכישה
                </span>
                <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
            </div>
            <div class="panel-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <i class="fas fa-inbox" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                <div style="color: #64748b; margin-bottom: 15px;">אין רכישה מקושרת לקבר זה</div>
                <button type="button" class="btn btn-success btn-open-purchase">
                    <i class="fas fa-plus"></i> הוסף רכישה
                </button>
            </div>
        </div>
    </div>';
}

// פאנל קבורה
if ($burial) {
    $gridPanels .= '
    <div class="grid-stack-item panel-burial" gs-x="6" gs-y="4" gs-w="6" gs-h="3" gs-id="burial">
        <div class="grid-stack-item-content">
            <div class="panel-header">
                <span class="panel-header-title">
                    <i class="fas fa-cross"></i> תיק קבורה
                </span>
                <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
            </div>
            <div class="panel-body">
                <div style="font-weight: 700; color: #78350f; font-size: 16px; margin-bottom: 12px;">
                    ' . htmlspecialchars($burial['clientFullNameHe'] ?? '-') . '
                </div>
                <div class="info-grid">
                    <div class="info-item" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="info-item-label">ת.ז. נפטר</div>
                        <div class="info-item-value" style="color: #78350f;">' . htmlspecialchars($burial['clientNumId'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="info-item-label">מספר קבורה</div>
                        <div class="info-item-value" style="color: #78350f;">' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="info-item-label">שם האב</div>
                        <div class="info-item-value" style="color: #78350f;">' . htmlspecialchars($burial['clientNameFather'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="info-item-label">שם האם</div>
                        <div class="info-item-value" style="color: #78350f;">' . htmlspecialchars($burial['clientNameMother'] ?? '-') . '</div>
                    </div>
                    <div class="info-item" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="info-item-label">תאריך פטירה</div>
                        <div class="info-item-value" style="color: #78350f;">' . formatHebrewDate($burial['dateDeath']) . '</div>
                    </div>
                    <div class="info-item" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="info-item-label">תאריך קבורה</div>
                        <div class="info-item-value" style="color: #78350f;">' . formatHebrewDate($burial['dateBurial']) . '</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-primary" onclick="GraveCardHandler.editBurial(\'' . $burial['unicId'] . '\')" style="margin-top: 12px;">
                    <i class="fas fa-edit"></i> ערוך קבורה
                </button>
            </div>
        </div>
    </div>';
} elseif ($grave['graveStatus'] == 1 || $grave['graveStatus'] == 2) {
    $gridPanels .= '
    <div class="grid-stack-item panel-burial" gs-x="6" gs-y="4" gs-w="6" gs-h="3" gs-id="burial">
        <div class="grid-stack-item-content">
            <div class="panel-header">
                <span class="panel-header-title">
                    <i class="fas fa-cross"></i> תיק קבורה
                </span>
                <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
            </div>
            <div class="panel-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <i class="fas fa-inbox" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                <div style="color: #64748b; margin-bottom: 15px;">אין קבורה מקושרת לקבר זה</div>
                <button type="button" class="btn btn-info btn-open-burial">
                    <i class="fas fa-plus"></i> הוסף קבורה
                </button>
            </div>
        </div>
    </div>';
}

// פאנל מסמכים
$explorerUnicId = htmlspecialchars($grave['unicId']);
$gridPanels .= '
<div class="grid-stack-item panel-documents" gs-x="0" gs-y="7" gs-w="12" gs-h="4" gs-id="documents">
    <div class="grid-stack-item-content">
        <div class="panel-header">
            <span class="panel-header-title">
                <i class="fas fa-folder-open"></i> מסמכים
            </span>
            <span class="panel-drag-handle"><i class="fas fa-grip-vertical"></i></span>
        </div>
        <div class="panel-body" style="padding: 10px;">
            <div id="graveExplorer" data-unic-id="' . $explorerUnicId . '">
                <div style="text-align: center; padding: 20px; color: #666;">
                    <i class="fas fa-spinner fa-spin"></i> טוען סייר קבצים...
                </div>
            </div>
        </div>
    </div>
</div>';

// הוספת הפאנלים לגריד ואתחול GridStack
$gridInitScript = '
<script>
window.GraveCardLayout = {
    grid: null,
    defaultLayout: null,
    storageKey: "graveCardLayout",

    init: function() {
        const gridEl = document.getElementById("graveCardGrid");
        if (!gridEl || typeof GridStack === "undefined") {
            console.error("GridStack not loaded or grid element not found");
            return;
        }

        // הוסף פאנלים לגריד
        gridEl.insertAdjacentHTML("beforeend", `' . str_replace(["\n", "'"], ["", "\\'"], $gridPanels) . '`);

        // אתחול GridStack
        this.grid = GridStack.init({
            column: 12,
            cellHeight: 60,
            margin: 8,
            float: true,
            animate: true,
            draggable: {
                handle: ".panel-header"
            },
            resizable: {
                handles: "e,se,s,sw,w"
            }
        }, gridEl);

        // שמור לייאאוט ברירת מחדל
        this.defaultLayout = this.grid.save();

        // טען לייאאוט שמור
        this.loadLayout();

        // שמור בעת שינוי
        this.grid.on("change", () => {
            this.saveLayout();
        });

        console.log("📐 [GraveCardLayout] GridStack initialized");
    },

    saveLayout: function() {
        if (!this.grid) return;
        const layout = this.grid.save();
        localStorage.setItem(this.storageKey, JSON.stringify(layout));
        console.log("📐 [GraveCardLayout] Layout saved");
    },

    loadLayout: function() {
        const saved = localStorage.getItem(this.storageKey);
        if (saved && this.grid) {
            try {
                const layout = JSON.parse(saved);
                this.grid.load(layout);
                console.log("📐 [GraveCardLayout] Layout loaded from storage");
            } catch (e) {
                console.error("📐 [GraveCardLayout] Error loading layout:", e);
            }
        }
    },

    resetLayout: function() {
        if (!this.grid || !this.defaultLayout) return;
        this.grid.load(this.defaultLayout);
        localStorage.removeItem(this.storageKey);
        console.log("📐 [GraveCardLayout] Layout reset to default");
    }
};

// אתחול כשה-DOM מוכן
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(() => GraveCardLayout.init(), 100);
});
</script>';

$formBuilder->addCustomHTML($gridInitScript);

// שדה מוסתר - unicId
$formBuilder->addField('unicId', '', 'hidden', [
    'value' => $grave['unicId']
]);

// שדה מוסתר - graveStatus (לשימוש ב-JS)
$formBuilder->addField('currentGraveStatus', '', 'hidden', [
    'value' => $grave['graveStatus']
]);

// שדה מוסתר - areaGraveId (למעבר לעריכת אחוזת קבר)
$formBuilder->addField('areaGraveId', '', 'hidden', [
    'value' => $grave['areaGraveId']
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>