<?php
$file = __DIR__ . '/pdf-mpdf-overlay.php';
$output = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
echo $output;