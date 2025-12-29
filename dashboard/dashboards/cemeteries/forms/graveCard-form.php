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

// === בניית כל ה-HTML כמחרוזת אחת ===
// הערה: SortableJS נטען דינמית ב-form-handler.js
$allSectionsHTML = '
<style>
    /* תיקון שם המודל - graveCardModal ולא graveCardFormModal */
    #graveCardFormModal .modal-dialog {
        max-width: 95% !important;
        width: 1400px !important;
    }
    #graveCardFormModal .modal-body {
        max-height: 85vh !important;
        padding: 20px !important;
    }

    /* סקשנים ניתנים לגרירה */
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
        border-radius: 12px;
    }

    .sortable-section.sortable-chosen {
        box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
        border: 2px solid #3b82f6 !important;
        transform: scale(1.01);
        z-index: 1000;
    }

    .sortable-section.sortable-drag {
        box-shadow: 0 15px 50px rgba(0,0,0,0.25);
        border: 2px solid #2563eb !important;
        opacity: 0.95;
    }

    /* ידית גרירה - פס עליון ברור */
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

    .section-drag-handle:hover::before {
        background: #64748b;
    }

    .section-drag-handle:active {
        cursor: grabbing;
        background: #94a3b8;
    }

    /* כפתור צימצום/הרחבה */
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

    /* כותרת הסקשן במצב מצומצם */
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

    /* תוכן הסקשן */
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

     /* מיכל ראשי - תמונה + פרטים */
     .grave-header-container {
         display: grid;
         grid-template-columns: 280px 1fr;
         gap: 20px;
         margin-bottom: 20px;
     }

     /* מציג תמונות */
     .grave-image-viewer {
         background: #1e293b;
         border-radius: 12px;
         overflow: hidden;
         height: 320px;
         display: flex;
         flex-direction: column;
     }

     .grave-image-main {
         flex: 1;
         display: flex;
         align-items: center;
         justify-content: center;
         position: relative;
         overflow: hidden;
     }

     .grave-image-main img {
         max-width: 100%;
         max-height: 100%;
         object-fit: contain;
     }

     .grave-image-placeholder {
         color: #64748b;
         text-align: center;
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

     .grave-image-main img {
         cursor: pointer;
     }

     /* Lightbox - תצוגה מוגדלת */
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

     .lightbox-image:active {
         cursor: grabbing;
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
         transition: background 0.2s;
         z-index: 10001;
     }

     .lightbox-close:hover {
         background: rgba(255, 255, 255, 0.3);
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
         transition: background 0.2s, transform 0.2s;
     }

     .lightbox-btn:hover {
         background: rgba(255, 255, 255, 0.35);
         transform: scale(1.1);
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
         transition: background 0.2s;
         z-index: 10001;
     }

     .lightbox-nav:hover {
         background: rgba(255, 255, 255, 0.3);
     }

     .lightbox-nav.prev {
         right: 30px;
     }

     .lightbox-nav.next {
         left: 30px;
     }

     /* פרטי הקבר */
     .grave-details-container {
         display: flex;
         flex-direction: column;
         gap: 15px;
     }

     /* כותרת קבר */
     .grave-title-bar {
         display: flex;
         align-items: center;
         justify-content: space-between;
         padding: 15px;
         background: linear-gradient(135deg, #f8fafc, #f1f5f9);
         border-radius: 10px;
         border: 1px solid #e2e8f0;
     }

     .grave-title {
         font-size: 20px;
         font-weight: 700;
         color: #1e293b;
     }

     .grave-status-badge {
         padding: 8px 16px;
         border-radius: 20px;
         font-weight: 600;
         font-size: 14px;
         color: white;
     }

     @media (max-width: 900px) {
         .grave-header-container {
             grid-template-columns: 1fr;
         }
         .grave-image-viewer {
             height: 250px;
         }
     }

     /* תמיכה במובייל - שם מודל נכון: graveCardModal */
     @media (max-width: 768px) {
         /* המודל עצמו - מסך מלא */
         #graveCardFormModal {
             padding: 0 !important;
         }

         #graveCardFormModal .modal-dialog {
             max-width: 100% !important;
             width: 100% !important;
             height: 100% !important;
             margin: 0 !important;
         }

         #graveCardFormModal .modal-content {
             height: 100% !important;
             max-height: 100% !important;
             border-radius: 0 !important;
             display: flex !important;
             flex-direction: column !important;
         }

         /* כותרת קבועה עליונה */
         #graveCardFormModal .modal-header {
             flex-shrink: 0 !important;
             position: relative !important;
             z-index: 10 !important;
             padding: 12px 15px !important;
             min-height: 50px !important;
         }

         #graveCardFormModal .modal-title {
             font-size: 16px !important;
         }

         #graveCardFormModal .modal-header .close {
             font-size: 28px !important;
             width: 40px !important;
             height: 40px !important;
             opacity: 1 !important;
         }

         /* תוכן גולל באמצע */
         #graveCardFormModal .modal-body {
             flex: 1 !important;
             overflow-y: auto !important;
             overflow-x: hidden !important;
             padding: 10px !important;
             padding-bottom: 20px !important;
             -webkit-overflow-scrolling: touch !important;
         }

         /* כפתורים קבועים תחתונים */
         #graveCardFormModal .modal-footer {
             flex-shrink: 0 !important;
             position: relative !important;
             z-index: 10 !important;
             padding: 10px 15px !important;
             background: #f8fafc !important;
             border-top: 1px solid #e2e8f0 !important;
         }

         /* כפתור צימצום/הרחבה - נגיש למובייל */
         .section-toggle-btn {
             width: 32px !important;
             height: 32px !important;
             font-size: 14px !important;
             -webkit-tap-highlight-color: transparent;
             touch-action: manipulation;
             z-index: 100 !important;
         }

         /* ידית גרירה דקה יותר במובייל */
         .section-drag-handle {
             height: 32px !important;
         }

         /* ידית שינוי גובה גדולה יותר למובייל */
         .section-resize-handle {
             height: 24px !important;
             touch-action: none;
         }

         .section-resize-handle::after {
             opacity: 1 !important;
             width: 60px !important;
             height: 6px !important;
             bottom: 9px !important;
         }

         /* כותרת סקשן במובייל */
         .section-title {
             right: 55px !important;
             font-size: 12px !important;
         }

         /* היררכיה - 2 עמודות במובייל */
         .grave-header-container > .grave-details-container > fieldset > div {
             grid-template-columns: repeat(2, 1fr) !important;
         }

         /* פרטי קבר - 2 עמודות במובייל */
         .grave-details-container > div[style*="grid-template-columns"] {
             grid-template-columns: repeat(2, 1fr) !important;
         }

         /* הוספת רווח תחתון */
         .sortable-sections {
             padding-bottom: 10px !important;
         }

         .sortable-section:last-child {
             margin-bottom: 10px !important;
         }
     }
 </style>

 <!-- מיכל לכל הסקשנים הניתנים לגרירה -->
 <div class="sortable-sections" id="graveSortableSections">

 <!-- סקשן 1: פרטי קבר ותמונה -->
<div class="sortable-section" data-section="header">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-monument"></i> פרטי קבר</span>
    </div>
    <div class="section-content">
    <div class="grave-header-container">
     <!-- מציג תמונות -->
     <div class="grave-image-viewer" id="graveImageViewer" data-unic-id="' . htmlspecialchars($grave['unicId']) . '">
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

     <!-- Lightbox לתצוגה מוגדלת -->
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
     </div>

     <!-- פרטים והיררכיה -->
     <div class="grave-details-container">
         <!-- כותרת וסטטוס -->
         <div class="grave-title-bar">
             <span class="grave-title">
                 <i class="fas fa-monument" style="color: #64748b; margin-left: 8px;"></i>
                 ' . htmlspecialchars($grave['graveNameHe'] ?? 'קבר') . '
             </span>
             <span class="grave-status-badge" style="background: ' . $statusColor . ';">
                 ' . $statusName . '
             </span>
         </div>

         <!-- היררכיה -->
         <fieldset class="form-section" style="border: 2px solid #e0f2fe; border-radius: 12px; padding: 15px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); margin: 0;">
             <legend style="padding: 0 10px; font-weight: bold; color: #0284c7; font-size: 14px;">
                 <i class="fas fa-sitemap"></i> מיקום בהיררכיה
             </legend>
             <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;">
                 <div style="background: white; padding: 10px; border-radius: 8px; border: 1px solid #bae6fd; text-align: center;">
                     <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">בית עלמין</div>
                     <div style="font-weight: 600; color: #0c4a6e; font-size: 13px;">' . htmlspecialchars($grave['cemeteryNameHe'] ?? '-') . '</div>
                 </div>
                 <div style="background: white; padding: 10px; border-radius: 8px; border: 1px solid #bae6fd; text-align: center;">
                     <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">גוש</div>
                     <div style="font-weight: 600; color: #0c4a6e; font-size: 13px;">' . htmlspecialchars($grave['blockNameHe'] ?? '-') . '</div>
                 </div>
                 <div style="background: white; padding: 10px; border-radius: 8px; border: 1px solid #bae6fd; text-align: center;">
                     <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">חלקה</div>
                     <div style="font-weight: 600; color: #0c4a6e; font-size: 13px;">' . htmlspecialchars($grave['plotNameHe'] ?? '-') . '</div>
                 </div>
                 <div style="background: white; padding: 10px; border-radius: 8px; border: 1px solid #bae6fd; text-align: center;">
                     <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">שורה</div>
                     <div style="font-weight: 600; color: #0c4a6e; font-size: 13px;">' . htmlspecialchars($grave['lineNameHe'] ?? '-') . '</div>
                 </div>
                 <div style="background: white; padding: 10px; border-radius: 8px; border: 1px solid #bae6fd; text-align: center;">
                     <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">אחוזת קבר</div>
                     <div style="font-weight: 600; color: #0c4a6e; font-size: 13px;">' . htmlspecialchars($grave['areaGraveNameHe'] ?? '-') . '</div>
                 </div>
             </div>
         </fieldset>

         <!-- פרטי קבר -->
         <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
             <div style="background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                 <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">סוג חלקה</div>
                 <div style="font-weight: 600; color: #334155; font-size: 13px;">' . ($grave['plotType'] == 1 ? 'פטורה' : ($grave['plotType'] == 2 ? 'חריגה' : 'סגורה')) . '</div>
             </div>
             <div style="background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                 <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">מיקום בשורה</div>
                 <div style="font-weight: 600; color: #334155; font-size: 13px;">' . ($grave['graveLocation'] == 1 ? 'עליון' : ($grave['graveLocation'] == 2 ? 'תחתון' : 'אמצעי')) . '</div>
             </div>
             <div style="background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                 <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">קבר קטן</div>
                 <div style="font-weight: 600; color: #334155; font-size: 13px;">' . ($grave['isSmallGrave'] ? 'כן' : 'לא') . '</div>
             </div>
             <div style="background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                 <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">עלות בנייה</div>
                 <div style="font-weight: 600; color: #059669; font-size: 13px;">' . formatPrice($grave['constructionCost']) . '</div>
             </div>
         </div>

         <!-- כפתור עריכה -->
         <button type="button"
                 class="btn btn-sm btn-primary"
                 onclick="openGraveEdit(\'' . $grave['areaGraveId'] . '\')"
                 style="background: linear-gradient(135deg, #0284c7, #0369a1); border: none; padding: 10px; font-weight: 600;">
             <i class="fas fa-edit"></i> ערוך אחוזת קבר
         </button>
     </div>
    </div>
    </div><!-- סוף section-content -->
    <div class="section-resize-handle"></div>
</div>
<!-- סוף סקשן 1 -->
';

// === סקשן 2: תיק רכישה ===
if ($purchase) {
    $purchaseStatusNames = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];
    $purchaseStatusColors = [1 => '#3b82f6', 2 => '#10b981', 3 => '#64748b', 4 => '#ef4444'];
    $pStatusName = $purchaseStatusNames[$purchase['purchaseStatus']] ?? 'לא ידוע';
    $pStatusColor = $purchaseStatusColors[$purchase['purchaseStatus']] ?? '#64748b';

    $allSectionsHTML .= '
<!-- סקשן 2: תיק רכישה -->
<div class="sortable-section" data-section="purchase">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-shopping-cart"></i> תיק רכישה</span>
    </div>
    <div class="section-content">
    <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
        <legend style="padding: 0 15px; font-weight: bold; color: #1e40af; font-size: 16px;">
            <i class="fas fa-shopping-cart"></i> תיק רכישה
            <span style="background: ' . $pStatusColor . '; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-right: 10px;">' . $pStatusName . '</span>
        </legend>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם הרוכש</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . htmlspecialchars($purchase['clientFullNameHe'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">ת.ז. רוכש</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . htmlspecialchars($purchase['clientNumId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר רכישה</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . htmlspecialchars($purchase['serialPurchaseId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מחיר רכישה</div>
            <div style="font-weight: 600; color: #059669; font-size: 15px;">' . formatPrice($purchase['price']) . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך רכישה</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . formatHebrewDate($purchase['dateOpening']) . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">טלפון</div>
                <div style="font-weight: 600; color: #1e3a8a;">' . formatPhone($purchase['phone'] ?? $purchase['phoneMobile']) . '</div>
            </div>
        </div>
        <div style="margin-top: 12px; display: flex; gap: 10px;">
            <button type="button" class="btn btn-sm btn-primary" onclick="GraveCardHandler.editPurchase(\'' . $purchase['unicId'] . '\')">
                <i class="fas fa-edit"></i> ערוך רכישה
            </button>
        </div>
    </fieldset>
    </div><!-- סוף section-content -->
    <div class="section-resize-handle"></div>
</div>
';
} elseif ($grave['graveStatus'] == 1) {
    // אין רכישה והקבר פנוי - הצג כפתור הוספה
    $allSectionsHTML .= '
<!-- סקשן 2: תיק רכישה (ריק) -->
<div class="sortable-section" data-section="purchase">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-shopping-cart"></i> תיק רכישה</span>
    </div>
    <div class="section-content">
    <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 30px; margin: 0; background: #f8fafc; text-align: center;">
        <legend style="padding: 0 15px; font-weight: bold; color: #94a3b8; font-size: 16px;">
            <i class="fas fa-shopping-cart"></i> תיק רכישה
        </legend>
        <div style="color: #64748b; margin-bottom: 15px;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
            אין רכישה מקושרת לקבר זה
        </div>
        <button type="button" class="btn btn-success btn-open-purchase" style="padding: 10px 24px; font-size: 15px;">
            <i class="fas fa-plus"></i> הוסף רכישה
        </button>
    </fieldset>
    </div><!-- סוף section-content -->
    <div class="section-resize-handle"></div>
</div>
';
}

// === סקשן 3: תיק קבורה ===
if ($burial) {
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
    <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">
        <legend style="padding: 0 15px; font-weight: bold; color: #92400e; font-size: 16px;">
            <i class="fas fa-cross"></i> תיק קבורה
        </legend>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a; grid-column: span 2;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם הנפטר/ת</div>
                <div style="font-weight: 700; color: #78350f; font-size: 16px;">' . htmlspecialchars($burial['clientFullNameHe'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">ת.ז. נפטר</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['clientNumId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">מספר קבורה</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['serialBurialId'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האב</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['clientNameFather'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">שם האם</div>
                <div style="font-weight: 600; color: #78350f;">' . htmlspecialchars($burial['clientNameMother'] ?? '-') . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך פטירה</div>
                <div style="font-weight: 600; color: #78350f;">' . formatHebrewDate($burial['dateDeath']) . '</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">תאריך קבורה</div>
                <div style="font-weight: 600; color: #78350f;">' . formatHebrewDate($burial['dateBurial']) . '</div>
            </div>
        </div>
        <div style="margin-top: 12px; display: flex; gap: 10px;">
            <button type="button" class="btn btn-sm btn-primary" onclick="GraveCardHandler.editBurial(\'' . $burial['unicId'] . '\')">
                <i class="fas fa-edit"></i> ערוך קבורה
            </button>
        </div>
    </fieldset>
    </div><!-- סוף section-content -->
    <div class="section-resize-handle"></div>
</div>
';
} elseif ($grave['graveStatus'] == 1 || $grave['graveStatus'] == 2) {
    // אין קבורה והקבר פנוי או נרכש - הצג כפתור הוספה
    $allSectionsHTML .= '
<!-- סקשן 3: תיק קבורה (ריק) -->
<div class="sortable-section" data-section="burial">
    <div class="section-drag-handle">
        <button type="button" class="section-toggle-btn" title="צמצם/הרחב">
            <i class="fas fa-chevron-down"></i>
        </button>
        <span class="section-title"><i class="fas fa-cross"></i> תיק קבורה</span>
    </div>
    <div class="section-content">
    <fieldset class="form-section" style="border: 2px dashed #fde68a; border-radius: 12px; padding: 30px; margin: 0; background: #fffef5; text-align: center;">
        <legend style="padding: 0 15px; font-weight: bold; color: #94a3b8; font-size: 16px;">
            <i class="fas fa-cross"></i> תיק קבורה
        </legend>
        <div style="color: #64748b; margin-bottom: 15px;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                אין קבורה מקושרת לקבר זה
            </div>
    <button type="button" class="btn btn-info btn-open-burial" style="padding: 10px 24px; font-size: 15px;">
        <i class="fas fa-plus"></i> הוסף קבורה
    </button>
    </fieldset>
    </div><!-- סוף section-content -->
    <div class="section-resize-handle"></div>
</div>
';
}

// === סקשן 4: מסמכים (סייר קבצים) ===
$explorerUnicId = htmlspecialchars($grave['unicId']);
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
    <fieldset class="form-section" style="border: none; border-radius: 0 0 10px 10px; padding: 20px; margin: 0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
        <legend style="padding: 0 15px; font-weight: bold; color: #475569; font-size: 16px;">
            <i class="fas fa-folder-open"></i> מסמכים
        </legend>
        <div id="graveExplorer" data-unic-id="' . $explorerUnicId . '">
            <div style="text-align: center; padding: 20px; color: #666;">
                <i class="fas fa-spinner fa-spin"></i> טוען סייר קבצים...
            </div>
        </div>
    </fieldset>
    </div><!-- סוף section-content -->
    <div class="section-resize-handle"></div>
</div>

</div>
<!-- סוף מיכל הסקשנים הניתנים לגרירה -->

<!-- טעינת קוד משותף לסקשנים -->
<script>
(function() {
    console.log("🚀 [GraveCard] מתחיל טעינת sortable-sections.js");

    function initSections() {
        console.log("📌 [GraveCard] initSections נקרא");
        var container = document.getElementById("graveSortableSections");
        console.log("📌 [GraveCard] container:", container ? "נמצא" : "לא נמצא!");

        if (typeof SortableSections !== "undefined") {
            console.log("✅ [GraveCard] SortableSections קיים, מאתחל...");
            SortableSections.init("graveSortableSections", "graveCard");
        } else {
            console.error("❌ [GraveCard] SortableSections לא מוגדר!");
        }
    }

    // בדוק אם הסקריפט כבר נטען
    if (typeof SortableSections !== "undefined") {
        console.log("📌 [GraveCard] SortableSections כבר נטען");
        initSections();
    } else {
        // טען את הסקריפט דינמית עם cache buster
        var cacheBuster = "v=" + Date.now();
        var script = document.createElement("script");
        script.src = "/dashboard/dashboards/cemeteries/forms/sortable-sections.js?" + cacheBuster;
        script.onload = function() {
            console.log("✅ [GraveCard] סקריפט נטען בהצלחה");
            initSections();
        };
        script.onerror = function() {
            console.error("❌ [GraveCard] שגיאה בטעינת sortable-sections.js");
        };
        document.head.appendChild(script);
        console.log("📌 [GraveCard] סקריפט נוסף ל-head");
    }
})();
</script>
';

// === הוספת כל הסקשנים כ-HTML אחד ===
$formBuilder->addCustomHTML($allSectionsHTML);

// הערות (מחוץ לאזור הניתן לגרירה)
if (!empty($grave['comments'])) {
    $formBuilder->addField('comments', 'הערות', 'textarea', [
        'value' => $grave['comments'],
        'rows' => 3,
        'readonly' => true
    ]);
}

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