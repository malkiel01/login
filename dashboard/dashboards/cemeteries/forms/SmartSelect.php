<?php
/**
 * SmartSelect - רכיב Select עם חיפוש
 * גרסה פשוטה ועובדת
 */
class SmartSelect {
    
    private $name;
    private $label;
    private $options;
    private $config;
    private static $counter = 0;
    
    public function __construct($name, $label, $options = [], $config = []) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->config = array_merge([
            'searchable' => true,
            'placeholder' => 'בחר...',
            'search_placeholder' => 'חפש...',
            'required' => false,
            'value' => '',
            'disabled' => false
        ], $config);
        
        self::$counter++;
    }
    
    public function render() {
        $uniqueId = $this->name . '_' . self::$counter;
        
        $html = '<div class="form-group">';
        
        // Label
        $html .= '<label for="' . $this->name . '">';
        $html .= htmlspecialchars($this->label);
        if ($this->config['required']) {
            $html .= ' <span style="color: red;">*</span>';
        }
        $html .= '</label>';
        
        if ($this->config['searchable']) {
            // SmartSelect עם חיפוש
            $html .= $this->renderSmartSelect($uniqueId);
        } else {
            // Select רגיל
            $html .= $this->renderRegularSelect();
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderSmartSelect($uniqueId) {
        $disabled = $this->config['disabled'] ? 'disabled' : '';
        $required = $this->config['required'] ? 'required' : '';
        
        $html = '<div class="smart-select-wrapper" data-name="' . $this->name . '">';
        
        // Hidden input - זה מה שנשלח בטופס
        $html .= '<input type="hidden" ';
        $html .= 'id="' . $this->name . '" ';
        $html .= 'name="' . $this->name . '" ';
        $html .= 'value="' . htmlspecialchars($this->config['value']) . '" ';
        $html .= $required . ' ' . $disabled . '>';
        
        // תצוגה
        $selectedText = $this->getSelectedText();
        $html .= '<div class="smart-select-display" data-smart-id="' . $uniqueId . '">';
        $html .= '<span class="smart-select-value">' . htmlspecialchars($selectedText) . '</span>';
        $html .= '<span class="smart-select-arrow">▼</span>';
        $html .= '</div>';
        
        // Dropdown
        $html .= '<div class="smart-select-dropdown" style="display: none;">';
        
        // שדה חיפוש
        $html .= '<div class="smart-select-search-box">';
        $html .= '<input type="text" class="smart-select-search-input" ';
        $html .= 'placeholder="' . htmlspecialchars($this->config['search_placeholder']) . '">';
        $html .= '</div>';
        
        // אופציות
        $html .= '<div class="smart-select-options">';
        foreach ($this->options as $value => $text) {
            $selected = ($this->config['value'] == $value) ? 'selected' : '';
            $html .= '<div class="smart-select-option ' . $selected . '" data-value="' . htmlspecialchars($value) . '">';
            $html .= htmlspecialchars($text);
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // הודעת "אין תוצאות"
        $html .= '<div class="smart-select-no-results" style="display: none;">לא נמצאו תוצאות</div>';
        
        $html .= '</div>'; // close dropdown
        $html .= '</div>'; // close wrapper
        
        return $html;
    }
    
    private function renderRegularSelect() {
        $html = '<select class="form-control" ';
        $html .= 'id="' . $this->name . '" ';
        $html .= 'name="' . $this->name . '"';
        if ($this->config['required']) $html .= ' required';
        if ($this->config['disabled']) $html .= ' disabled';
        $html .= '>';
        
        if (!$this->config['required']) {
            $html .= '<option value="">' . $this->config['placeholder'] . '</option>';
        }
        
        foreach ($this->options as $value => $text) {
            $selected = ($this->config['value'] == $value) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>';
            $html .= htmlspecialchars($text);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }
    
    private function getSelectedText() {
        if (empty($this->config['value'])) {
            return $this->config['placeholder'];
        }
        return $this->options[$this->config['value']] ?? $this->config['placeholder'];
    }
}
?>