<?php
// /dashboards/cemeteries/forms/form-loader.php
// טוען טופס דינמי לפי סוג

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/forms-config.php';


// קבלת פרמטרים
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? null;
$parent_id = $_GET['parent_id'] ?? null;

// בדיקת סוג
if (!$type) {
    die(json_encode(['error' => 'Type is required']));
}

// טעינת נתונים אם מדובר בעריכה
$data = null;
if ($id) {
    $data = getFormData($type, $id);
}

// בניית הטופס
$formBuilder = new FormBuilder($type, $id, $parent_id);

// הוספת השדות לפי הסוג
$fields = getFormFields($type, $data);
foreach ($fields as $field) {
    $options = [
        'required' => $field['required'] ?? false,
        'value' => $data ? ($data[$field['name']] ?? '') : '',
        'placeholder' => $field['placeholder'] ?? '',
        'options' => $field['options'] ?? [],
        'class' => $field['class'] ?? '',
        'readonly' => $field['readonly'] ?? false,
        'min' => $field['min'] ?? null,
        'max' => $field['max'] ?? null,
        'step' => $field['step'] ?? null,
        'rows' => $field['rows'] ?? 3
    ];
    
    $formBuilder->addField(
        $field['name'],
        $field['label'],
        $field['type'],
        $options
    );
}

// החזרת ה-HTML של הטופס
echo $formBuilder->renderModal();
?>