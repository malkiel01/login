<?php
// /dashboards/cemeteries/forms/FormBuilder.php
// מחלקה מרכזית לבניית טפסים דינמיים

class FormBuilder {
    private $formId;
    private $formTitle;
    private $fields = [];
    private $action;
    private $method = 'POST';
    private $type;
    private $itemId;
    private $parentId;
    
    public function __construct($type, $itemId = null, $parentId = null) {
        $this->type = $type;
        $this->itemId = $itemId;
        $this->parentId = $parentId;
        $this->formId = $type . 'Form';
        $this->setFormTitle();
    }
    
    private function setFormTitle() {
        $titles = [
            'cemetery' => 'בית עלמין',
            'block' => 'גוש',
            'plot' => 'חלקה',
            'row' => 'שורה',
            'area_grave' => 'אחוזת קבר',
            'grave' => 'קבר',
            'customer' => 'לקוח',
            'purchase' => 'רכישה',
            'burial' => 'קבורה'
        ];
        
        $baseTitle = $titles[$this->type] ?? 'פריט';
        $this->formTitle = $this->itemId ? "עריכת $baseTitle" : "הוספת $baseTitle";
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
            
            /* Scrollbar styling */
            #' . $this->formId . 'Modal .modal-body::-webkit-scrollbar {
                width: 8px;
            }
            
            #' . $this->formId . 'Modal .modal-body::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 4px;
            }
            
            #' . $this->formId . 'Modal .modal-body::-webkit-scrollbar-thumb {
                background: #cbd5e0;
                border-radius: 4px;
            }
            
            #' . $this->formId . 'Modal .modal-body::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            
            /* Mobile responsiveness */
            @media (max-width: 640px) {
                #' . $this->formId . 'Modal .modal-dialog {
                    margin: 10px;
                }
                
                #' . $this->formId . 'Modal .modal-content {
                    border-radius: 12px;
                }
                
                #' . $this->formId . 'Modal .modal-body {
                    padding: 1rem;
                }
            }
        </style>
        ';
    }
    
    public function renderModal() {
        // Start with CSS
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
        
        // Hidden fields
        $html .= '<input type="hidden" name="type" value="' . $this->type . '">';
        if ($this->itemId) {
            $html .= '<input type="hidden" name="id" value="' . $this->itemId . '">';
        }
        if ($this->parentId) {
            $html .= '<input type="hidden" name="parent_id" value="' . $this->parentId . '">';
        }
        
        // Render fields
        foreach ($this->fields as $field) {
            $html .= $this->renderField($field);
        }
        
        $html .= '</div>';
        
        // Footer
        $html .= '<div class="modal-footer">';
        $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="FormHandler.closeForm(\'' . $this->type . '\')">ביטול</button>';
        $html .= '<button type="submit" class="btn btn-primary">';
        $html .= $this->itemId ? 'עדכן' : 'שמור';
        $html .= '</button>';
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
}
?>