<?php
header('Content-Type: application/json; charset=utf-8');

$fonts_dir = __DIR__ . '/fonts/custom/';
$fonts_json = __DIR__ . '/fonts.json';

if (!file_exists($fonts_dir)) {
    mkdir($fonts_dir, 0755, true);
}

if (!isset($_FILES['font']) || $_FILES['font']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'לא התקבל קובץ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['font'];
$font_name = $_POST['name'] ?? 'פונט חדש';

// בדיקת סוג קובץ
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['ttf', 'otf'])) {
    echo json_encode(['success' => false, 'error' => 'רק קבצי TTF או OTF'], JSON_UNESCAPED_UNICODE);
    exit;
}

// שם ייחודי
$unique_id = uniqid('font_');
$filename = $unique_id . '.' . $ext;
$filepath = $fonts_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'שגיאה בשמירה'], JSON_UNESCAPED_UNICODE);
    exit;
}

// עדכן fonts.json
$fonts_data = file_exists($fonts_json) ? json_decode(file_get_contents($fonts_json), true) : ['fonts' => []];

$fonts_data['fonts'][] = [
    'id' => $unique_id,
    'name' => $font_name,
    'filename' => $filename,
    'path' => 'fonts/custom/' . $filename,
    'type' => 'custom'
];

file_put_contents($fonts_json, json_encode($fonts_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'font_id' => $unique_id], JSON_UNESCAPED_UNICODE);