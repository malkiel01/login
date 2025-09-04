<?php
require_once __DIR__ . '/vendor/autoload.php';

class PDF extends \Mpdf\Mpdf {
    use \Mpdf\FpdiTrait;
}

header('Content-Type: application/json');

try {
    $pdf = new PDF(['mode' => 'utf-8']);
    echo json_encode(['success' => true, 'message' => 'mPDF with FPDI works!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}