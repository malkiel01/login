<?php
/**
 * SmartSelect Component - Full Featured
 * סלקט חכם עם חיפוש מתקדם ועיצוב מותאם אישית
 * 
 * תכונות:
 * - חיפוש בזמן אמת
 * - עיצוב מותאם אישית (לא ברירת מחדל של הדפדפן)
 * - תמיכה באלפי אופציות
 * - חיפוש חכם (גם באמצע המילים)
 * - RTL מלא
 * - תמיכה ב-AJAX
 * 
 * @version 2.0.0
 */

class SmartSelect {
    
    private $name;
    private $label;
    private $options = [];
    private $config = [];
    private static $instanceCounter = 0;
    
    // הגדרות ברירת מחדל
    private $defaults = [
        'searchable' => true,              // חיפוש מופעל כברירת מחדל
        'placeholder' => 'בחר...',
        'search_placeholder' => 'חפש...',
        'no_results_text' => 'לא נמצאו תוצאות',
        'required' => false,
        'disabled' => false,
        'value' => null,
        'display_mode' => 'advanced',      // simple | advanced
        'ajax_url' => null,
        'depends_on' => null,              // תלות בשדה אחר
        'min_search_length' => 1,          // מינימום תווים לחיפוש
        'max_results' => 100,              // מקסימום תוצאות להצגה
        'allow_clear' => true,
        'rtl' => true
    ];
    
    /**
     * Constructor
     */
    public function __construct($name, $label, $options = [], $config = []) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->config = array_merge($this->defaults, $config);
        
        self::$instanceCounter++;
    }
    
    /**
     * Render המרכיב
     */
    public function render() {
        $uniqueId = $this->name . '_' . self::$instanceCounter;
        
        $html = '<div class="smart-select-wrapper" id="wrapper_' . $uniqueId . '" data-name="' . $this->name . '">';
        
        // Label
        if (!empty($this->label)) {
            $html .= $this->renderLabel();
        }
        
        // Hidden Input - זה מה שבאמת נשלח בטופס
        $html .= '<input type="hidden" id="' . $this->name . '" name="' . $this->name . '" value="' . 
                 htmlspecialchars($this->config['value'] ?? '') . '">';
        
        // Custom Select Container
        $html .= $this->renderCustomSelect($uniqueId);
        
        // Options Data (נשמר כ-JSON)
        $html .= $this->renderOptionsData();
        
        // JavaScript Initialization
        $html .= $this->renderScript($uniqueId);
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render Label
     */
    private function renderLabel() {
        $html = '<label class="smart-select-label" for="' . $this->name . '">';
        $html .= htmlspecialchars($this->label);
        
        if ($this->config['required']) {
            $html .= ' <span class="text-danger">*</span>';
        }
        
        $html .= '</label>';
        
        return $html;
    }
    
    /**
     * Render Custom Select
     */
    private function renderCustomSelect($uniqueId) {
        $disabled = $this->config['disabled'] ? 'disabled' : '';
        $selectedText = $this->getSelectedText();
        
        $html = '<div class="smart-select-container ' . $disabled . '" id="container_' . $uniqueId . '">';
        
        // Selected Display (מה שנראה כשהסלקט סגור)
        $html .= '<div class="smart-select-display" id="display_' . $uniqueId . '">';
        $html .= '<span class="smart-select-value">' . htmlspecialchars($selectedText) . '</span>';
        $html .= '<span class="smart-select-arrow">▼</span>';
        $html .= '</div>';
        
        // Dropdown (מה שנפתח כשלוחצים)
        $html .= '<div class="smart-select-dropdown" id="dropdown_' . $uniqueId . '" style="display: none;">';
        
        // Search Box
        if ($this->config['searchable']) {
            $html .= '<div class="smart-select-search-box">';
            $html .= '<input type="text" class="smart-select-search-input" ';
            $html .= 'id="search_' . $uniqueId . '" ';
            $html .= 'placeholder="' . htmlspecialchars($this->config['search_placeholder']) . '" ';
            $html .= 'autocomplete="off">';
            $html .= '</div>';
        }
        
        // Options List
        $html .= '<div class="smart-select-options" id="options_' . $uniqueId . '">';
        $html .= $this->renderOptions();
        $html .= '</div>';
        
        // No Results Message
        $html .= '<div class="smart-select-no-results" id="noresults_' . $uniqueId . '" style="display: none;">';
        $html .= htmlspecialchars($this->config['no_results_text']);
        $html .= '</div>';
        
        $html .= '</div>'; // close dropdown
        
        $html .= '</div>'; // close container
        
        return $html;
    }
    
    /**
     * Render Options
     */
    private function renderOptions() {
        $html = '';
        $selectedValue = $this->config['value'] ?? '';
        
        // Add empty option if not required
        if (!$this->config['required'] && $this->config['allow_clear']) {
            $selected = empty($selectedValue) ? 'selected' : '';
            $html .= '<div class="smart-select-option ' . $selected . '" data-value="">';
            $html .= htmlspecialchars($this->config['placeholder']);
            $html .= '</div>';
        }
        
        // Add all options
        foreach ($this->options as $value => $option) {
            $selected = ($value == $selectedValue) ? 'selected' : '';
            
            if (is_array($option)) {
                // Advanced format
                $text = $option['text'] ?? $option['name'] ?? '';
                $subtitle = $option['subtitle'] ?? '';
                $badge = $option['badge'] ?? '';
                
                $html .= '<div class="smart-select-option ' . $selected . '" ';
                $html .= 'data-value="' . htmlspecialchars($value) . '" ';
                $html .= 'data-search="' . htmlspecialchars(strtolower($text . ' ' . $subtitle)) . '">';
                
                $html .= '<div class="smart-select-option-content">';
                $html .= '<div class="smart-select-option-text">' . htmlspecialchars($text) . '</div>';
                
                if (!empty($subtitle)) {
                    $html .= '<div class="smart-select-option-subtitle">' . htmlspecialchars($subtitle) . '</div>';
                }
                
                $html .= '</div>';
                
                if (!empty($badge)) {
                    $html .= '<span class="smart-select-option-badge">' . htmlspecialchars($badge) . '</span>';
                }
                
                $html .= '</div>';
                
            } else {
                // Simple format
                $html .= '<div class="smart-select-option ' . $selected . '" ';
                $html .= 'data-value="' . htmlspecialchars($value) . '" ';
                $html .= 'data-search="' . htmlspecialchars(strtolower($option)) . '">';
                $html .= htmlspecialchars($option);
                $html .= '</div>';
            }
        }
        
        return $html;
    }
    
    /**
     * Get Selected Text
     */
    private function getSelectedText() {
        $value = $this->config['value'] ?? '';
        
        if (empty($value)) {
            return $this->config['placeholder'];
        }
        
        if (isset($this->options[$value])) {
            $option = $this->options[$value];
            
            if (is_array($option)) {
                return $option['text'] ?? $option['name'] ?? $this->config['placeholder'];
            }
            
            return $option;
        }
        
        return $this->config['placeholder'];
    }
    
    /**
     * Render Options as JSON (for AJAX)
     */
    private function renderOptionsData() {
        $data = [
            'options' => $this->options,
            'config' => $this->config
        ];
        
        return '<script type="application/json" id="data_' . $this->name . '">' . 
               json_encode($data, JSON_UNESCAPED_UNICODE) . 
               '</script>';
    }
    
    /**
     * Render JavaScript
     */
    private function renderScript($uniqueId) {
        $config = json_encode($this->config, JSON_UNESCAPED_UNICODE);
        
        return '<script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof SmartSelectManager !== "undefined") {
                SmartSelectManager.init("' . $this->name . '", ' . $config . ');
            }
        });
        </script>';
    }
    
    /**
     * Static Factory Method
     */
    public static function create($name, $label, $options = [], $config = []) {
        return new self($name, $label, $options, $config);
    }
    
    /**
     * Set Value
     */
    public function setValue($value) {
        $this->config['value'] = $value;
        return $this;
    }
    
    /**
     * Set Options
     */
    public function setOptions($options) {
        $this->options = $options;
        return $this;
    }
    
    /**
     * Enable Search
     */
    public function enableSearch($placeholder = 'חפש...') {
        $this->config['searchable'] = true;
        $this->config['search_placeholder'] = $placeholder;
        return $this;
    }
    
    /**
     * Disable Search
     */
    public function disableSearch() {
        $this->config['searchable'] = false;
        return $this;
    }
    
    /**
     * Set as Required
     */
    public function setRequired($required = true) {
        $this->config['required'] = $required;
        return $this;
    }
    
    /**
     * Set AJAX URL
     */
    public function setAjaxUrl($url) {
        $this->config['ajax_url'] = $url;
        return $this;
    }
    
    /**
     * Set Dependency
     */
    public function dependsOn($fieldName) {
        $this->config['depends_on'] = $fieldName;
        return $this;
    }
}