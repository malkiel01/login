<?php
/**
 * SmartSelect Component - Minimal Version
 * For quick installation
 */
class SmartSelect {
    private $name;
    private $label;
    private $options = [];
    private $config = [];
    
    public function __construct($name, $label, $options = [], $config = []) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->config = array_merge([
            'searchable' => false,
            'placeholder' => 'בחר...',
            'required' => false,
            'display_mode' => 'simple'
        ], $config);
    }
    
    public function render() {
        $html = '<div class="smart-select-container">';
        $html .= '<label for="' . $this->name . '">' . $this->label;
        if ($this->config['required']) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
        
        $html .= '<select id="' . $this->name . '" name="' . $this->name . '" 
class="form-control smart-select"';
        if ($this->config['searchable']) {
            $html .= ' data-searchable="true"';
        }
        if ($this->config['required']) {
            $html .= ' required';
        }
        $html .= '>';
        
        if (!$this->config['required']) {
            $html .= '<option value="">' . $this->config['placeholder'] . '</option>';
        }
        
        foreach ($this->options as $value => $label) {
            if (is_array($label)) {
                $text = $label['text'] ?? $label['name'] ?? '';
                $html .= '<option value="' . htmlspecialchars($value) . '"';
                if (isset($label['subtitle'])) {
                    $html .= ' data-subtitle="' . htmlspecialchars($label['subtitle']) . 
'"';
                }
                $html .= '>' . htmlspecialchars($text) . '</option>';
            } else {
                $html .= '<option value="' . htmlspecialchars($value) . '">' . 
htmlspecialchars($label) . '</option>';
            }
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    public static function create($name, $label, $options = [], $config = []) {
        return new self($name, $label, $options, $config);
    }
}
?>
