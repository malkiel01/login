<?php
// dashboard/dashboards/cemeteries/forms/FormBuilder.php
// מחלקה מרכזית לבניית טפסים דינמיים - משתמשת בקונפיג המרכזי

class FormBuilder {
    private $formId;
    private $formTitle;
    private $fields = [];
    private $action;
    private $method = 'POST';
    private $type;
    private $itemId;
    private $parentId;
    private $config;
    
    public function __construct($type, $itemId = null, $parentId = null) {
        $this->type = $type;
        $this->itemId = $itemId;
        $this->parentId = $parentId;
        $this->formId = $type . 'Form';
        
        // טען את הקונפיג המרכזי
        $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
        $this->config = require $configPath;
        
        $this->setFormTitle();
    }
    
    private function setFormTitle() {
        // קבל כותרת מהקונפיג
        if (isset($this->config[$this->type])) {
            $singular = $this->config[$this->type]['singular'] ?? 'פריט';
            $this->formTitle = $this->itemId ? "עריכת $singular" : "הוספת $singular";
        } else {
            // fallback
            $this->formTitle = $this->itemId ? 'עריכת פריט' : 'הוספת פריט';
        }
    }
    
    /**
     * קבלת שם ההורה מהקונפיג
     */
    private function getParentInfo() {
        if (!$this->parentId || !isset($this->config[$this->type])) {
            return null;
        }
        
        $typeConfig = $this->config[$this->type];
        $parentKey = $typeConfig['parentKey'] ?? null;
        
        if (!$parentKey) {
            return null;
        }
        
        // מצא את סוג ההורה
        $parentType = null;
        foreach ($this->config as $key => $conf) {
            if (isset($conf['table'])) {
                // בדוק אם ה-parentKey מתאים לטבלה
                $tableName = $conf['table'];
                // המר את שם השדה לשם טבלה (לדוגמה: cemeteryId -> cemeteries)
                if (strpos($parentKey, 'Id') !== false) {
                    $expectedTable = str_replace('Id', '', $parentKey);
                    // בדוק התאמות אפשריות
                    if ($expectedTable === 'cemetery' && $tableName === 'cemeteries') {
                        $parentType = $key;
                        break;
                    } elseif ($expectedTable === 'block' && $tableName === 'blocks') {
                        $parentType = $key;
                        break;
                    } elseif ($expectedTable === 'plot' && $tableName === 'plots') {
                        $parentType = $key;
                        break;
                    } elseif ($expectedTable === 'line' && $tableName === 'rows') {
                        $parentType = $key;
                        break;
                    } elseif ($expectedTable === 'areaGrave' && $tableName === 'areaGraves') {
                        $parentType = $key;
                        break;
                    }
                }
            }
        }
        
        if (!$parentType) {
            return null;
        }
        
        try {
            $parentConfig = $this->config[$parentType];
            $parentTable = $parentConfig['table'];
            $parentPrimaryKey = $parentConfig['primaryKey'] ?? 'id';
            $parentDisplayField = $parentConfig['displayFields']['name'] ?? 'name';
            
            // חבר למסד נתונים
            require_once dirname(__DIR__) . '/config.php';
            $pdo = getDBConnection();
            
            $sql = "SELECT $parentDisplayField as name FROM $parentTable 
                    WHERE $parentPrimaryKey = :id LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $this->parentId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? [
                'name' => $result['name'],
                'type' => $parentType,
                'field' => $parentKey
            ] : null;
            
        } catch (Exception $e) {
            error_log('Error getting parent info: ' . $e->getMessage());
            return null;
        }
    }
    
    public function addField($name, $label, $type = 'text', $options = []) {
        $this->fields[] = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'required' => $options['required'] ?? false,
            'value' => $options['value'] ?? '',
            'placeholder' => $options['placeholder'] ?? '',
            'options' => $options['options'] ?? [],
            'class' => $options['class'] ?? '',
            'readonly' => $options['readonly'] ?? false,
            'min' => $options['min'] ?? null,
            'max' => $options['max'] ?? null,
            'step' => $options['step'] ?? null,
            'rows' => $options['rows'] ?? 3
        ];
    }
    
    private function getModalCSS() {
        return '
        <style>
            /* Modal Container */
            #' . $this->formId . 'Modal {
                display: flex !important;
                align-items: center;
                justify-content: center;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 9999;
                animation: fadeIn 0.3s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            /* Modal Overlay */
            #' . $this->formId . 'Modal .modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(5px);
                -webkit-backdrop-filter: blur(5px);
            }
            
            /* Modal Dialog */
            #' . $this->formId . 'Modal .modal-dialog {
                position: relative;
                margin: 30px;
                max-width: 600px;
                width: 100%;
                z-index: 10000;
            }
            
            /* Modal Content */
            #' . $this->formId . 'Modal .modal-content {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
                overflow: hidden;
                animation: modalSlideIn 0.3s ease;
                border: none;
            }
            
            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Modal Header */
            #' . $this->formId . 'Modal .modal-header {
                padding: 1.5rem;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            #' . $this->formId . 'Modal .modal-title {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 600;
                color: white;
            }
            
            #' . $this->formId . 'Modal .close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: white;
                opacity: 0.8;
                transition: all 0.3s;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                padding: 0;
                line-height: 1;
            }
            
            #' . $this->formId . 'Modal .close:hover {
                opacity: 1;
                background: rgba(255, 255, 255, 0.2);
            }
            
            /* Modal Body */
            #' . $this->formId . 'Modal .modal-body {
                padding: 1.5rem;
                max-height: calc(90vh - 140px);
                overflow-y: auto;
            }
            
            /* Parent Info Alert */
            #' . $this->formId . 'Modal .parent-info {
                margin-bottom: 15px;
                padding: 12px 15px;
                background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
                border-right: 4px solid #667eea;
                border-radius: 8px;
                font-size: 0.9rem;
                color: #475569;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            #' . $this->formId . 'Modal .parent-info-icon {
                font-size: 1.2rem;
            }
            
            #' . $this->formId . 'Modal .parent-info strong {
                color: #667eea;
                font-weight: 600;
            }
            
            /* Modal Footer */
            #' . $this->formId . 'Modal .modal-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
                background: #f8fafc;
            }
            
            /* Form Elements */
            #' . $this->formId . 'Modal .form-group {
                margin-bottom: 1.25rem;
            }
            
            #' . $this->formId . 'Modal .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: #475569;
            }
            
            #' . $this->formId . 'Modal .form-control {
                width: 100%;
                padding: 0.625rem 0.875rem;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.875rem;
                transition: all 0.3s ease;
                background: white;
                font-family: inherit;
                direction: rtl;
            }
            
            #' . $this->formId . 'Modal .form-control:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            #' . $this->formId . 'Modal .form-control:disabled {
                background: #f1f5f9;
                cursor: not-allowed;
                opacity: 0.7;
            }
            
            #' . $this->formId . 'Modal textarea.form-control {
                resize: vertical;
                min-height: 80px;
            }
            
            #' . $this->formId . 'Modal select.form-control {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23475569\' d=\'M7 10l5 5 5-5H7z\'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: left 0.75rem center;
                background-size: 20px;
                padding-left: 2.5rem;
            }
            
            /* Buttons */
            #' . $this->formId . 'Modal .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.625rem 1.25rem;
                border: none;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                white-space: nowrap;
                font-family: inherit;
            }
            
            #' . $this->formId . 'Modal .btn:hover {
                transform: translateY(-1px);
            }
            
            #' . $this->formId . 'Modal .btn-primary {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
            }
            
            #' . $this->formId . 'Modal .btn-primary:hover {
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }
            
            #' . $this->formId . 'Modal .btn-secondary {
                background: #f1f5f9;
                color: #475569;
            }
            
            #' . $this->formId . 'Modal .btn-secondary:hover {
                background: #e2e8f0;
            }
            
            /* Required field indicator */
            #' . $this->formId . 'Modal .text-danger {
                color: #dc2626;
            }
            
            /* Checkbox */
            #' . $this->formId . 'Modal .form-check {
                display: flex;
                align-items: center;
                margin-bottom: 0.75rem;
            }
            
            #' . $this->formId . 'Modal .form-check-input {
                width: 1.125rem;
                height: 1.125rem;
                margin-left: 0.5rem;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                cursor: pointer;
            }
            
            #' . $this->formId . 'Modal .form-check-label {
                cursor: pointer;
                font-size: 0.875rem;
            }

            /* Parent info styles */
            /**
    #' . $this->formId . 'Modal .parent-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #dee2e6;
    }

    #' . $this->formId . 'Modal .parent-info-icon {
        margin-left: 6px;
        font-size: 16px;
    }

    #' . $this->formId . 'Modal .parent-info strong {
        color: #495057;
        margin-right: 4px;
    }

    #' . $this->formId . 'Modal .parent-info .btn-outline-primary {
        padding: 4px 12px;
        font-size: 14px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    #' . $this->formId . 'Modal .parent-info .btn-outline-primary:hover {
        background: #007bff;
        color: white;
        transform: translateY(-1px);
    }
        **/
        </style>
        ';
    }

    // ב-FormBuilder.php, הוסף פונקציה חדשה:
    public function setCustomButtons($buttons) {
        $this->customButtons = $buttons;
    }
    
    public function renderModal() {
        // Start with loading CSS
        $html = $this->getModalCSS();
        
        // Modal structure
        $html .= '<div class="modal show" id="' . $this->formId . 'Modal">';
        $html .= '<div class="modal-overlay" onclick="FormHandler.closeForm(\'' . $this->type . '\')"></div>';
        $html .= '<div class="modal-dialog">';
        $html .= '<div class="modal-content">';
        
        // Header
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title">' . $this->formTitle . '</h5>';
        $html .= '<button type="button" class="close" data-dismiss="modal" onclick="FormHandler.closeForm(\'' . $this->type . '\')">&times;</button>';
        $html .= '</div>';
        
        // Body with form
        $html .= '<form id="' . $this->formId . '" onsubmit="handleFormSubmit(event, \'' . $this->type . '\')">';
        $html .= '<div class="modal-body">';
        
        // הצג מידע על ההורה אם קיים
        if ($this->parentId && !$this->itemId) {
            $parentInfo = $this->getParentInfo();
            if ($parentInfo) {
                $html .= '<div class="parent-info">';
                $html .= '<span class="parent-info-icon">📍</span>';
                $html .= 'הוספה ל: <strong>' . htmlspecialchars($parentInfo['name']) . '</strong>';
                $html .= '</div>';
                
                // הוסף דיבוג כהערה
                $html .= '<!-- Parent field: ' . $parentInfo['field'] . ' = ' . $this->parentId . ' -->';
            }
        }

        // הצג מידע על ההורה אם קיים - במצב עריכה
        if ($this->parentId && $this->itemId) {
            $parentInfo = $this->getParentInfo();
            if ($parentInfo) {
                $html .= '<div class="parent-info" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">';
                $html .= '<div>';
                $html .= '<span class="parent-info-icon">📍</span>';
                $html .= 'משויך ל: <strong id="currentParentName">' . htmlspecialchars($parentInfo['name']) . '</strong>';
                $html .= '</div>';
                
                // כפתור שינוי - רק אם זה לא סוג שלא צריך הורה
                $typesWithoutParent = ['cemetery', 'payment', 'customer', 'purchase', 'residency', 'burial'];
                if (!in_array($this->type, $typesWithoutParent)) {
                    $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="FormHandler.changeParent(\'' . $this->type . '\', \'' . $this->itemId . '\', \'' . $this->parentId . '\')">';
                    $html .= '<svg style="width: 14px; height: 14px; margin-left: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                    $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>';
                    $html .= '</svg>';
                    $html .= ' שינוי';
                    $html .= '</button>';
                }
                
                $html .= '</div>';
                
                // שדה hidden לשמירת ה-parentId החדש
                $html .= '<input type="hidden" id="newParentId" name="newParentId" value="">';
                
                // הוסף דיבוג כהערה
                $html .= '<!-- Parent field: ' . $parentInfo['field'] . ' = ' . $this->parentId . ' -->';
            }
        }
        
        // Hidden fields
        $html .= '<input type="hidden" name="formType" value="' . $this->type . '">';
        if ($this->itemId) {
            $html .= '<input type="hidden" name="itemId" value="' . $this->itemId . '">';
        }
        if ($this->parentId) {
            $html .= '<input type="hidden" name="parentId" value="' . $this->parentId . '">';
        }
        
        // Render fields
        foreach ($this->fields as $field) {
            $html .= $this->renderField($field);
        }
        
        $html .= '</div>';
        
        // Footer 
        $html .= '<div class="modal-footer">';
        if ($this->type === 'parent_selector') {
            $html .= '<button type="button" class="btn btn-secondary" onclick="FormHandler.closeForm(\'parent_selector\')">ביטול</button>';
            $html .= '<button type="button" class="btn btn-primary" onclick="FormHandler.handleParentSelection()">המשך</button>';
        } else {
            // הכפתורים הרגילים
            $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="FormHandler.closeForm(\'' . $this->type . '\')">ביטול</button>';
            $html .= '<button type="submit" class="btn btn-primary">';
            $html .= $this->itemId ? 'עדכן' : 'שמור';
            $html .= '</button>';
        }
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderField($field) {
        // For checkbox, handle differently
        if ($field['type'] === 'checkbox') {
            return $this->renderCheckbox($field);
        }
        
        $html = '<div class="form-group ' . $field['class'] . '">';
        $html .= '<label for="' . $field['name'] . '">';
        $html .= $field['label'];
        if ($field['required']) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
        
        switch ($field['type']) {
            case 'select':
                $html .= $this->renderSelect($field);
                break;
            case 'textarea':
                $html .= $this->renderTextarea($field);
                break;
            case 'number':
                $html .= $this->renderNumber($field);
                break;
            case 'date':
                $html .= $this->renderDate($field);
                break;
            case 'email':
                $html .= $this->renderEmail($field);
                break;
            case 'tel':
                $html .= $this->renderTel($field);
                break;
            case 'time':
                $html .= $this->renderTime($field);
                break;
            case 'custom_html':
                $html .= $field['html'];
                break;
            default:
                $html .= $this->renderText($field);
        }
        
        $html .= '</div>';
        return $html;
    }
    
    private function renderText($field) {
        $html = '<input type="text" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . htmlspecialchars($field['value']) . '" ';
        if ($field['placeholder']) {
            $html .= 'placeholder="' . htmlspecialchars($field['placeholder']) . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderNumber($field) {
        $html = '<input type="number" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . htmlspecialchars($field['value']) . '" ';
        if ($field['min'] !== null) {
            $html .= 'min="' . $field['min'] . '" ';
        }
        if ($field['max'] !== null) {
            $html .= 'max="' . $field['max'] . '" ';
        }
        if ($field['step'] !== null) {
            $html .= 'step="' . $field['step'] . '" ';
        }
        if ($field['placeholder']) {
            $html .= 'placeholder="' . htmlspecialchars($field['placeholder']) . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderEmail($field) {
        $html = '<input type="email" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . htmlspecialchars($field['value']) . '" ';
        if ($field['placeholder']) {
            $html .= 'placeholder="' . htmlspecialchars($field['placeholder']) . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderTel($field) {
        $html = '<input type="tel" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . htmlspecialchars($field['value']) . '" ';
        if ($field['placeholder']) {
            $html .= 'placeholder="' . htmlspecialchars($field['placeholder']) . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderDate($field) {
        $html = '<input type="date" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . htmlspecialchars($field['value']) . '" ';
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderTime($field) {
        $html = '<input type="time" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . htmlspecialchars($field['value']) . '" ';
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderSelect($field) {
        $html = '<select class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'disabled ';
        }
        $html .= '>';
        
        if (!$field['required']) {
            $html .= '<option value="">בחר...</option>';
        }
        
        foreach ($field['options'] as $value => $label) {
            $selected = ($field['value'] == $value) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>';
            $html .= htmlspecialchars($label);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }
    
    private function renderTextarea($field) {
        $html = '<textarea class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'rows="' . $field['rows'] . '" ';
        if ($field['placeholder']) {
            $html .= 'placeholder="' . htmlspecialchars($field['placeholder']) . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
        }
        if ($field['readonly']) {
            $html .= 'readonly ';
        }
        $html .= '>';
        $html .= htmlspecialchars($field['value']);
        $html .= '</textarea>';
        return $html;
    }
    
    private function renderCheckbox($field) {
        $html = '<div class="form-group ' . $field['class'] . '">';
        $html .= '<div class="form-check">';
        $html .= '<input type="checkbox" class="form-check-input" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="1" ';
        if ($field['value']) {
            $html .= 'checked ';
        }
        if ($field['readonly']) {
            $html .= 'disabled ';
        }
        $html .= '>';
        $html .= '<label class="form-check-label" for="' . $field['name'] . '">';
        $html .= $field['label'];
        if ($field['required']) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    public function addCustomHTML($html) {
        $this->fields[] = [
            'type' => 'custom_html',
            'html' => $html
        ];
    }
}
?>
    private function renderSmartSelect($field) {
        $smartSelect = new SmartSelect(
            $field["name"],
            $field["label"],
            $field["options"] ?? [],
            [
                "searchable" => $field["searchable"] ?? false,
                "placeholder" => $field["placeholder"] ?? "בחר...",
                "required" => $field["required"] ?? false,
                "display_mode" => $field["display_mode"] ?? "simple"
            ]
        );
        return $smartSelect->render();
    }

    /**
     * Add field group
     */
    public function addFieldGroup($id, $title, $config = []) {
        $html = '<fieldset class="field-group ' . ($config['collapsible'] ?? '') . '" id="' 
. $id . '-group">';
        $html .= '<legend>' . $title . '</legend>';
        $html .= '<div class="form-fields ' . ($config['layout'] ?? '') . '">';
        
        if (isset($config['fields'])) {
            foreach ($config['fields'] as $field) {
                // Handle span
                $wrapperClass = 'form-field';
                if (isset($field['span'])) {
                    $wrapperClass .= ' span-' . $field['span'];
                }
                
                $html .= '<div class="' . $wrapperClass . '">';
                
                // Check if it's smart_select
                if ($field['type'] === 'smart_select') {
                    $smartSelect = new SmartSelect(
                        $field['name'],
                        $field['label'],
                        $field['options'] ?? [],
                        [
                            'searchable' => $field['searchable'] ?? false,
                            'placeholder' => $field['placeholder'] ?? 'בחר...',
                            'required' => $field['required'] ?? false,
                            'display_mode' => $field['display_mode'] ?? 'simple',
                            'depends_on' => $field['depends_on'] ?? null,
                            'ajax_url' => $field['ajax_url'] ?? null
                        ]
                    );
                    $html .= $smartSelect->render();
                } else {
                    // Regular field
                    $this->addField(
                        $field['name'],
                        $field['label'],
                        $field['type'],
                        $field
                    );
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '</div></fieldset>';
        $this->customHTML[] = $html;
        return $this;
    }
