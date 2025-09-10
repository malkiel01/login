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
    
    public function renderModal() {
        $html = '<div class="modal fade" id="' . $this->formId . 'Modal">';
        $html .= '<div class="modal-dialog">';
        $html .= '<div class="modal-content">';
        
        // Header
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title">' . $this->formTitle . '</h5>';
        $html .= '<button type="button" class="close" data-dismiss="modal">&times;</button>';
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
        $html .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">ביטול</button>';
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
            case 'checkbox':
                $html .= $this->renderCheckbox($field);
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
            $html .= 'placeholder="' . $field['placeholder'] . '" ';
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
        $html .= 'value="' . $field['value'] . '" ';
        if ($field['min'] !== null) {
            $html .= 'min="' . $field['min'] . '" ';
        }
        if ($field['max'] !== null) {
            $html .= 'max="' . $field['max'] . '" ';
        }
        if ($field['step'] !== null) {
            $html .= 'step="' . $field['step'] . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
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
            $html .= 'placeholder="' . $field['placeholder'] . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
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
            $html .= 'placeholder="' . $field['placeholder'] . '" ';
        }
        if ($field['required']) {
            $html .= 'required ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderDate($field) {
        $html = '<input type="date" class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="' . $field['value'] . '" ';
        if ($field['required']) {
            $html .= 'required ';
        }
        $html .= '>';
        return $html;
    }
    
    private function renderSelect($field) {
        $html = '<select class="form-control" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        if ($field['required']) {
            $html .= 'required';
        }
        $html .= '>';
        
        if (!$field['required']) {
            $html .= '<option value="">בחר...</option>';
        }
        
        foreach ($field['options'] as $value => $label) {
            $selected = ($field['value'] == $value) ? 'selected' : '';
            $html .= '<option value="' . $value . '" ' . $selected . '>';
            $html .= $label;
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
        if ($field['required']) {
            $html .= 'required';
        }
        $html .= '>';
        $html .= htmlspecialchars($field['value']);
        $html .= '</textarea>';
        return $html;
    }
    
    private function renderCheckbox($field) {
        $html = '<div class="form-check">';
        $html .= '<input type="checkbox" class="form-check-input" ';
        $html .= 'id="' . $field['name'] . '" ';
        $html .= 'name="' . $field['name'] . '" ';
        $html .= 'value="1" ';
        if ($field['value']) {
            $html .= 'checked';
        }
        $html .= '>';
        $html .= '<label class="form-check-label" for="' . $field['name'] . '">';
        $html .= $field['label'];
        $html .= '</label>';
        $html .= '</div>';
        return $html;
    }
}
?>