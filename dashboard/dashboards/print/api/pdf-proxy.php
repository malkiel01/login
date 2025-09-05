<?php
header('Access-Control-Allow-Origin: *');

$url = $_GET['url'] ?? '';
if (empty($url)) {
    http_response_code(400);
    die('No URL provided');
}

// Get PDF content
$content = file_get_contents($url);
if ($content === false) {
    http_response_code(404);
    die('Could not fetch PDF');
}

// Send PDF
header('Content-Type: application/pdf');
echo $content;