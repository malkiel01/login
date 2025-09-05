<?php
// שמור את הנתונים בדיוק כמו שהם נשלחים
$data = json_encode([
    'language' => 'he',
    'orientation' => 'L',
    'values' => [
        [
            'text' => 'ggfg',
            'x' => 100,
            'y' => 100,
            'fontSize' => 12,
            'color' => '#000000',
            'fontFamily' => 'heebo',
            'fontUrl' => null
        ]
    ],
    'filename' => 'https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf'
]);

// שלח את הנתונים ל-pdf-mpdf-overlay.php
$ch = curl_init('http://localhost' . dirname($_SERVER['SCRIPT_NAME']) . '/pdf-mpdf-overlay.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";
echo "Response:\n";
echo $response;