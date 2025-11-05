<?php
/*
 * File: dashboards/dashboard/cemeteries/forms/areaGrave-form.php
 * Version: 3.0.0
 * Updated: 2025-11-05
 * Author: Malkiel
 * Change Summary:
 * - הוצא כל ה-JavaScript לקובץ form-handler.js
 * - נותרו רק HTML + CSS + נתוני קונפיגורציה
 * - שיטה אחידה עם שאר הטפסים
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

try {
    $conn = getDBConnection();
    $itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
    $parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
    
    $areaGrave = null;
    $graves = [];
    
    // טען אחוזת קבר קיימת אם יש
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM areaGraves WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $areaGrave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // טען קברים קיימים
        if ($areaGrave) {
            $stmt = $conn->prepare("SELECT * FROM graves WHERE areaGraveId = ? AND isActive = 1 ORDER BY id ASC");
            $stmt->execute([$areaGrave['unicId']]);
            $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // טען שורות
    $rows = [];
    $actualPlotId = $parentId; // ברירת מחדל

    // 🔥 במצב עריכה - שלוף את ה-plotId האמיתי דרך השורה
    if ($itemId && $areaGrave && $areaGrave['lineId']) {
        $stmt = $conn->prepare("SELECT plotId FROM rows WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$areaGrave['lineId']]);
        $plotIdResult = $stmt->fetchColumn();
        if ($plotIdResult) {
            $actualPlotId = $plotIdResult;
        }
    }

    // טען שורות לפי החלקה
    if ($actualPlotId) {
        $stmt = $conn->prepare("SELECT r.unicId, r.lineNameHe, r.serialNumber FROM rows r WHERE r.plotId = ? AND r.isActive = 1 ORDER BY r.serialNumber, r.lineNameHe");
        $stmt->execute([$actualPlotId]);
    } else {
        $stmt = $conn->query("SELECT r.unicId, r.lineNameHe, r.serialNumber FROM rows r WHERE r.isActive = 1 ORDER BY r.serialNumber, r.lineNameHe");
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
        $rows[$row['unicId']] = $label;
    }


    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// =========================================
// הכנת קונפיגורציית קברים ל-JavaScript
// =========================================
$isEditMode = !empty($itemId);
$gravesConfig = [
    'existing' => $graves,
    'isEdit' => $isEditMode,
    'max' => 5,
    'areaGraveId' => $itemId
];
$gravesConfigJson = json_encode($gravesConfig, JSON_UNESCAPED_UNICODE);

// =========================================
// בניית הטופס
// =========================================
$formBuilder = new FormBuilder('areaGrave', $itemId, $parentId);

$formBuilder->addField('areaGraveNameHe', 'שם אחוזת קבר', 'text', [
    'required' => true,
    'placeholder' => 'הזן שם אחוזת קבר',
    'value' => $areaGrave['areaGraveNameHe'] ?? ''
]);

$formBuilder->addField('graveType', 'סוג אחוזת קבר', 'select', [
    'required' => true,
    'options' => ['' => '-- בחר סוג --', 1 => 'שדה', 2 => 'רוויה', 3 => 'סנהדרין'],
    'value' => $areaGrave['graveType'] ?? ''
]);

$formBuilder->addField('coordinates', 'קואורדינטות', 'text', [
    'placeholder' => 'הזן קואורדינטות',
    'value' => $areaGrave['coordinates'] ?? ''
]);

$formBuilder->addField('comments', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $areaGrave['comments'] ?? ''
]);

// ========================================
// HTML של טבלת קברים (ללא JavaScript!)
// ========================================

$gravesHTML = '
    <fieldset class="graves-section" 
        id="graves-fieldset"
        data-graves-config=\'' . htmlspecialchars($gravesConfigJson, ENT_QUOTES, 'UTF-8') . '\'
        style="border: 2px solid #667eea; border-radius: 12px; padding: 20px; margin: 20px 0; background: #f8f9ff;">
        <legend style="padding: 0 10px; font-weight: bold; color: #667eea; font-size: 1.1em;">🪦 קברים באחוזה (חובה לפחות 1, מקסימום 5)</legend>
        
        <div style="display: flex; gap: 15px; margin-bottom: 15px; align-items: center;">
            <button type="button" id="btnAddGrave" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                ➕ הוסף קבר
            </button>
            <span id="graveCount" style="color: #666; font-weight: 500;"></span>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="graves-table" style="width: 100%; border-collapse: collapse; background: white; min-width: 800px;">
                <thead style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <tr>
                        <th style="padding: 10px; width: 50px; text-align: center;">#</th>
                        <th style="padding: 10px; text-align: right;">שם קבר <span style="color: #ffd700;">*</span></th>
                        <th style="padding: 10px; width: 150px; text-align: right;">סוג חלקה <span style="color: #ffd700;">*</span></th>
                        ' . ($isEditMode ? '<th style="padding: 10px; width: 100px; text-align: center;">סטטוס</th>' : '') . '
                        <th style="padding: 10px; width: 100px; text-align: center;">קבר קטן</th>
                        <th style="padding: 10px; width: 130px; text-align: right;">עלות בנייה</th>
                        <th style="padding: 10px; width: 80px; text-align: center;">פעולות</th>
                    </tr>
                </thead>
                <tbody id="gravesBody"></tbody>
            </table>
        </div>
        
        <input type="hidden" name="gravesData" id="gravesData">
    </fieldset>

    <style>
        #areaGraveFormModal .modal-dialog {
            max-width: 95% !important;
            width: 1200px !important;
        }

        #areaGraveFormModal .modal-body {
            max-height: 80vh !important;
        }

        .graves-table td {
            padding: 10px;
            border: 1px solid #e2e8f0;
        }

        .graves-table input,
        .graves-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .graves-table input:focus,
        .graves-table select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-available { background: #dcfce7; color: #166534; }
        .status-purchased { background: #dbeafe; color: #1e40af; }
        .status-buried { background: #f3f4f6; color: #374151; }

        .btn-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-delete:hover:not(:disabled) {
            background: #dc2626;
        }

        .btn-delete:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
';

$formBuilder->addCustomHTML($gravesHTML);

// הוסף unicId אם עריכה
if ($areaGrave && $areaGrave['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', ['value' => $areaGrave['unicId']]);
}


$formBuilder->addField('lineId', 'שורה', 'text', [
    'required' => true,
    'value' => $areaGrave['unicId'],
    // 'hideInEdit' => true  // ← זה החדש! מסתיר בעריכה
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>