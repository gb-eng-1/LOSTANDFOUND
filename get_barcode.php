<?php
/**
 * Serve barcode/QR image for an item. Uses saved file in uploads/barcodes/ or generates and saves on first request.
 * Usage: get_barcode.php?id=UB10924
 */
$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id) || strlen($id) > 50) {
    http_response_code(400);
    exit('Invalid id');
}

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'barcodes';
$file = $baseDir . DIRECTORY_SEPARATOR . $id . '.png';

if (file_exists($file) && is_readable($file)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

// Generate QR image and save
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($id);
$img = @file_get_contents($qrUrl);
if ($img === false || strlen($img) < 100) {
    http_response_code(502);
    exit('Could not generate barcode image');
}

if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0755, true);
}
@file_put_contents($file, $img);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
echo $img;
