<?php
    /*
    * File: dashboards/dashboard/cemeteries/forms/area-grave-form.php
    * Version: 1.0.0
    * Updated: 2025-11-05
    * Author: Malkiel
    * Change Summary:
    * - v1.0.0: יצירה ראשונית - טופס אחוזת קבר עם טבלת קברים מוטמעת
    *   - תמיכה ביצירת עד 5 קברים בו-זמנית
    *   - עריכה inline של קברים קיימים
    *   - ולידציה על שמות ייחודיים
    *   - מניעת מחיקת קברים לא פנויים
    */

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    require_once __DIR__ . '/FormBuilder.php';
    require_once __DIR__ . '/FormUtils.php';
    require_once dirname(__DIR__) . '/config.php';

    try {
        $conn = getDBConnection();
        
        // קבלת פרמטרים
        $itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
        $parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
        
        // טען אחוזת קבר אם קיימת
        $areaGrave = null;
        $graves = [];
        
        if ($itemId) {
            // טען אחוזת קבר
            $stmt = $conn->prepare("
                SELECT * FROM areaGraves 
                WHERE unicId = ? AND isActive = 1
            ");
            $stmt->execute([$itemId]);
            $areaGrave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($areaGrave) {
                // טען קברים קיימים
                $stmt = $conn->prepare("
                    SELECT * FROM graves 
                    WHERE areaGraveId = ? AND isActive = 1
                    ORDER BY id ASC
                ");
                $stmt->execute([$areaGrave['unicId']]);
                $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // טען שורות לבחירה
        $rows = [];
        if ($parentId) {
            // אם יש parentId (plotId) - טען רק שורות מהחלקה הזו
            $stmt = $conn->prepare("
                SELECT r.unicId, r.lineNameHe, r.serialNumber 
                FROM rows r 
                WHERE r.plotId = ? AND r.isActive = 1
                ORDER BY r.serialNumber, r.lineNameHe
            ");
            $stmt->execute([$parentId]);
        } else {
            // אחרת טען את כל השורות
            $stmt = $conn->query("
                SELECT r.unicId, r.lineNameHe, r.serialNumber 
                FROM rows r 
                WHERE r.isActive = 1
                ORDER BY r.serialNumber, r.lineNameHe
            ");
        }
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $label = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
            $rows[$row['unicId']] = $label;
        }
        
    } catch (Exception $e) {
        FormUtils::handleError($e);
    }

    // יצירת FormBuilder
    $formBuilder = new FormBuilder('areaGrave', $itemId, $parentId);

    // שדה שורה (lineId)
    $formBuilder->addField('lineId', 'שורה', 'select', [
        'required' => true,
        'options' => array_merge(
            ['' => '-- בחר שורה --'],
            $rows
        ),
        'value' => $areaGrave['lineId'] ?? ($parentId ? $parentId : '')
    ]);

    // שם אחוזת קבר
    $formBuilder->addField('areaGraveNameHe', 'שם אחוזת קבר', 'text', [
        'required' => true,
        'placeholder' => 'הזן שם אחוזת קבר',
        'value' => $areaGrave['areaGraveNameHe'] ?? ''
    ]);

    // סוג אחוזת קבר
    $formBuilder->addField('graveType', 'סוג אחוזת קבר', 'select', [
        'required' => true,
        'options' => [
            '' => '-- בחר סוג --',
            1 => 'שדה',
            2 => 'רוויה',
            3 => 'סנהדרין'
        ],
        'value' => $areaGrave['graveType'] ?? ''
    ]);

    // קואורדינטות
    $formBuilder->addField('coordinates', 'קואורדינטות', 'text', [
        'placeholder' => 'הזן קואורדינטות',
        'value' => $areaGrave['coordinates'] ?? ''
    ]);

    // הערות
    $formBuilder->addField('comments', 'הערות', 'textarea', [
        'rows' => 3,
        'value' => $areaGrave['comments'] ?? ''
    ]);

    // ================================
    // טבלת קברים דינמית
    // ================================

    $isEditMode = !empty($itemId);
    $gravesJson = json_encode($graves, JSON_UNESCAPED_UNICODE);

    $gravesTableHTML = <<<HTML
    <fieldset class="form-section" id="graves-section" style="border: 2px solid #667eea; border-radius: 12px; padding: 20px; margin: 20px 0; background: #f8f9ff;">
        <legend style="padding: 0 10px; font-weight: bold; color: #667eea; font-size: 1.1em;">🪦 קברים באחוזה (חובה לפחות 1, מקסימום 5)</legend>
        
        <div style="margin-bottom: 15px;">
            <button type="button" id="addGraveBtn" class="btn btn-success" style="background: #10b981; padding: 8px 16px; border: none; border-radius: 6px; color: white; cursor: pointer; font-weight: 600;">
                ➕ הוסף קבר
            </button>
            <span id="graveCounter" style="margin-right: 15px; color: #666; font-weight: 500;"></span>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="graves-table" style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <tr>
                        <th style="padding: 12px; text-align: center; width: 50px;">#</th>
                        <th style="padding: 12px; text-align: right; min-width: 150px;">שם קבר <span style="color: #ffd700;">*</span></th>
                        <th style="padding: 12px; text-align: right; min-width: 130px;">סוג חלקה <span style="color: #ffd700;">*</span></th>
    HTML;

    // הוסף עמודת סטטוס רק במצב עריכה
    if ($isEditMode) {
        $gravesTableHTML .= <<<HTML
                        <th style="padding: 12px; text-align: center; width: 100px;">סטטוס</th>
    HTML;
    }

    $gravesTableHTML .= <<<HTML
                        <th style="padding: 12px; text-align: center; width: 90px;">קבר קטן</th>
                        <th style="padding: 12px; text-align: right; min-width: 120px;">עלות בנייה</th>
                        <th style="padding: 12px; text-align: center; width: 80px;">פעולות</th>
                    </tr>
                </thead>
                <tbody id="gravesTableBody">
                    <!-- השורות יתווספו דינמית ב-JS -->
                </tbody>
            </table>
        </div>
        
        <input type="hidden" name="gravesData" id="gravesData" value="">
    </fieldset>

    <style>
    .graves-table tbody tr {
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.2s;
    }

    .graves-table tbody tr:hover {
        background: #f8fafc;
    }

    .graves-table input[type="text"],
    .graves-table input[type="number"],
    .graves-table select {
        width: 100%;
        padding: 8px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 14px;
        transition: border 0.3s;
    }

    .graves-table input[type="text"]:focus,
    .graves-table input[type="number"]:focus,
    .graves-table select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .graves-table input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .delete-grave-btn {
        background: #ef4444;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }

    .delete-grave-btn:hover:not(:disabled) {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
    }

    .delete-grave-btn:disabled {
        background: #d1d5db;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .status-available { background: #dcfce7; color: #166534; }
    .status-purchased { background: #dbeafe; color: #1e40af; }
    .status-buried { background: #f3f4f6; color: #374151; }

    .btn-success {
        transition: all 0.3s;
    }

    .btn-success:hover {
        background: #059669 !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    </style>

    <script>
    // נתוני קברים קיימים (במצב עריכה)
    const existingGraves = $gravesJson;
    const isEditMode = $isEditMode;
    const MAX_GRAVES = 5;

    // מערך קברים נוכחי
    let currentGraves = [];

    // אתחול הטבלה
    document.addEventListener('DOMContentLoaded', function() {
        initGravesTable();
        updateGraveCounter();
        updateAddButton();
    });

    // אתחול טבלת קברים
    function initGravesTable() {
        if (isEditMode && existingGraves.length > 0) {
            // טען קברים קיימים
            existingGraves.forEach((grave, index) => {
                currentGraves.push({
                    id: grave.unicId,
                    graveNameHe: grave.graveNameHe,
                    plotType: grave.plotType,
                    graveStatus: grave.graveStatus,
                    isSmallGrave: grave.isSmallGrave == 1,
                    constructionCost: grave.constructionCost || '',
                    isExisting: true
                });
            });
        } else {
            // צור קבר ראשון (חובה)
            addNewGrave();
        }
        
        renderGravesTable();
    }

    // הוספת קבר חדש
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'addGraveBtn') {
            addNewGrave();
        }
    });

    function addNewGrave() {
        if (currentGraves.length >= MAX_GRAVES) {
            alert('ניתן להוסיף עד 5 קברים בלבד');
            return;
        }
        
        currentGraves.push({
            id: null, // יווצר בשרת
            graveNameHe: '',
            plotType: 1, // ברירת מחדל: פטורה
            graveStatus: 1, // פנוי
            isSmallGrave: false,
            constructionCost: '',
            isExisting: false
        });
        
        renderGravesTable();
        updateGraveCounter();
        updateAddButton();
    }

    // מחיקת קבר
    function deleteGrave(index) {
        const grave = currentGraves[index];
        
        // בדיקות
        if (index === 0) {
            alert('לא ניתן למחוק את הקבר הראשון');
            return;
        }
        
        if (isEditMode && grave.isExisting && grave.graveStatus !== 1) {
            const statusNames = { 2: 'נרכש', 3: 'קבור' };
            alert(`לא ניתן למחוק קבר עם סטטוס "\${statusNames[grave.graveStatus]}"`);
            // alert(\`לא ניתן למחוק קבר עם סטטוס "\${statusNames[grave.graveStatus]}"\`);
            return;
        }
        
        if (confirm('האם אתה בטוח שברצונך למחוק קבר זה?')) {
            currentGraves.splice(index, 1);
            renderGravesTable();
            updateGraveCounter();
            updateAddButton();
        }
    }

    // רינדור הטבלה
    function renderGravesTable() {
        const tbody = document.getElementById('gravesTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        currentGraves.forEach((grave, index) => {
            const row = document.createElement('tr');
            
            // מספור
            const numCell = document.createElement('td');
            numCell.style.cssText = 'text-align: center; font-weight: bold; color: #667eea;';
            numCell.textContent = index + 1;
            row.appendChild(numCell);
            
            // שם קבר
            const nameCell = document.createElement('td');
            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.value = grave.graveNameHe || '';
            nameInput.required = true;
            nameInput.placeholder = 'שם קבר (חובה)';
            nameInput.addEventListener('input', function() {
                currentGraves[index].graveNameHe = this.value;
            });
            nameCell.appendChild(nameInput);
            row.appendChild(nameCell);
            
            // סוג חלקה
            const typeCell = document.createElement('td');
            const typeSelect = document.createElement('select');
            typeSelect.required = true;
            // typeSelect.innerHTML = \`
            //     <option value="1" \${grave.plotType == 1 ? 'selected' : ''}>פטורה</option>
            //     <option value="2" \${grave.plotType == 2 ? 'selected' : ''}>חריגה</option>
            //     <option value="3" \${grave.plotType == 3 ? 'selected' : ''}>סגורה</option>
            // \`;
            typeSelect.innerHTML = `
                <option value="1" \${grave.plotType == 1 ? 'selected' : ''}>פטורה</option>
                <option value="2" \${grave.plotType == 2 ? 'selected' : ''}>חריגה</option>
                <option value="3" \${grave.plotType == 3 ? 'selected' : ''}>סגורה</option>
            `;
            typeSelect.addEventListener('change', function() {
                currentGraves[index].plotType = parseInt(this.value);
            });
            typeCell.appendChild(typeSelect);
            row.appendChild(typeCell);
            
            // סטטוס (רק בעריכה)
            if (isEditMode) {
                const statusCell = document.createElement('td');
                statusCell.style.cssText = 'text-align: center;';
                
                const statusNames = { 1: 'פנוי', 2: 'נרכש', 3: 'קבור' };
                const statusClasses = { 1: 'available', 2: 'purchased', 3: 'buried' };
                const status = grave.graveStatus || 1;
                
                const badge = document.createElement('span');
                // badge.className = \`status-badge status-\${statusClasses[status]}\`;
                badge.className = `status-badge status-\${statusClasses[status]}`;
                badge.textContent = statusNames[status];
                
                statusCell.appendChild(badge);
                row.appendChild(statusCell);
            }
            
            // קבר קטן
            const smallCell = document.createElement('td');
            smallCell.style.cssText = 'text-align: center;';
            const smallCheckbox = document.createElement('input');
            smallCheckbox.type = 'checkbox';
            smallCheckbox.checked = grave.isSmallGrave;
            smallCheckbox.addEventListener('change', function() {
                currentGraves[index].isSmallGrave = this.checked;
            });
            smallCell.appendChild(smallCheckbox);
            row.appendChild(smallCell);
            
            // עלות בנייה
            const costCell = document.createElement('td');
            const costInput = document.createElement('input');
            costInput.type = 'number';
            costInput.value = grave.constructionCost || '';
            costInput.step = '0.01';
            costInput.min = '0';
            costInput.placeholder = '0.00';
            costInput.addEventListener('input', function() {
                currentGraves[index].constructionCost = this.value;
            });
            costCell.appendChild(costInput);
            row.appendChild(costCell);
            
            // פעולות
            const actionsCell = document.createElement('td');
            actionsCell.style.cssText = 'text-align: center;';
            
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'delete-grave-btn';
            deleteBtn.textContent = '🗑️';
            deleteBtn.title = 'מחק קבר';
            
            // בדיקות זמינות מחיקה
            const canDelete = index > 0 && (!isEditMode || !grave.isExisting || grave.graveStatus === 1);
            deleteBtn.disabled = !canDelete;
            
            if (!canDelete) {
                if (index === 0) {
                    deleteBtn.title = 'לא ניתן למחוק את הקבר הראשון';
                } else if (grave.graveStatus !== 1) {
                    deleteBtn.title = 'לא ניתן למחוק קבר לא פנוי';
                }
            }
            
            deleteBtn.addEventListener('click', function() {
                deleteGrave(index);
            });
            
            actionsCell.appendChild(deleteBtn);
            row.appendChild(actionsCell);
            
            tbody.appendChild(row);
        });
    }

    // עדכון מונה
    function updateGraveCounter() {
        const counter = document.getElementById('graveCounter');
        if (counter) {
            // counter.textContent = \`(\${currentGraves.length}/\${MAX_GRAVES} קברים)\`;
            counter.textContent = `(\${currentGraves.length}/\${MAX_GRAVES} קברים)`;
        }
    }

    // עדכון כפתור הוספה
    function updateAddButton() {
        const btn = document.getElementById('addGraveBtn');
        if (btn) {
            btn.disabled = currentGraves.length >= MAX_GRAVES;
            if (currentGraves.length >= MAX_GRAVES) {
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            } else {
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
        }
    }

    // ולידציה לפני שליחה
    window.validateGravesData = function() {
        // בדיקה שיש לפחות קבר אחד
        if (currentGraves.length === 0) {
            alert('חובה להוסיף לפחות קבר אחד');
            return false;
        }
        
        // בדיקת שמות ריקים
        for (let i = 0; i < currentGraves.length; i++) {
            if (!currentGraves[i].graveNameHe || currentGraves[i].graveNameHe.trim() === '') {
                // alert(\`שם קבר מספר \${i + 1} הוא חובה\`);
                alert(`שם קבר מספר \${i + 1} הוא חובה`);
                return false;
            }
        }
        
        // בדיקת שמות כפולים
        const names = currentGraves.map(g => g.graveNameHe.trim().toLowerCase());
        const uniqueNames = new Set(names);
        if (names.length !== uniqueNames.size) {
            alert('שמות קברים חייבים להיות ייחודיים באחוזה');
            return false;
        }
        
        // שמירת הנתונים ב-hidden field
        document.getElementById('gravesData').value = JSON.stringify(currentGraves);
        
        return true;
    };

    // הוספת ולידציה לטופס
    const form = document.querySelector('form[id$="Form"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateGravesData()) {
                e.preventDefault();
                return false;
            }
        });
    }
    </script>
    HTML;

    // הוסף את הטבלה המותאמת
    $formBuilder->addCustomHTML($gravesTableHTML);

    // אם זה עריכה, הוסף unicId מוסתר
    if ($areaGrave && $areaGrave['unicId']) {
        $formBuilder->addField('unicId', '', 'hidden', [
            'value' => $areaGrave['unicId']
        ]);
    }

    // הצג את הטופס
    echo $formBuilder->renderModal();
?>