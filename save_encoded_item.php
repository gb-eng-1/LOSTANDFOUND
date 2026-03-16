<?php
/**
 * Save encoded item to database (called from FoundAdmin when encoding new item)
 * Barcode ID is generated on the server to avoid duplicates.
 * POST body: JSON with user_id, item_type, color, brand, found_at, found_by, date_lost, item_description, storage_location, imageDataUrl, dateEncoded
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$item = json_decode($raw, true);
if (!is_array($item)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing item data']);
    exit;
}

require __DIR__ . '/config/database.php';

// Generate next unique barcode ID (UB + 5-digit number)
try {
    $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) AS max_num FROM items WHERE id LIKE 'UB%' AND LENGTH(id) = 7");
    $row = $stmtMax ? $stmtMax->fetch(PDO::FETCH_ASSOC) : null;
    $nextNum = $row && $row['max_num'] !== null ? (int) $row['max_num'] + 1 : 10000;
    $id = 'UB' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $id = 'UB' . str_pad(10000 + (int) mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
}

$sql = 'INSERT INTO items (id, user_id, item_type, color, brand, found_at, found_by, date_encoded, date_lost, item_description, storage_location, image_data, status) 
        VALUES (:id, :user_id, :item_type, :color, :brand, :found_at, :found_by, :date_encoded, :date_lost, :item_description, :storage_location, :image_data, :status)';

$stmt = $pdo->prepare($sql);
$dateEncoded = isset($item['dateEncoded']) ? $item['dateEncoded'] : null;
$dateLost = null;
if (!empty($item['date_lost'])) {
    $dateLost = $item['date_lost'];
}

try {
    $stmt->execute([
        ':id'               => $id,
        ':user_id'          => $item['user_id'] ?? null,
        ':item_type'        => $item['item_type'] ?? null,
        ':color'            => $item['color'] ?? null,
        ':brand'            => $item['brand'] ?? null,
        ':found_at'         => $item['found_at'] ?? null,
        ':found_by'         => $item['found_by'] ?? null,
        ':date_encoded'     => $dateEncoded ?: date('Y-m-d'),
        ':date_lost'        => $dateLost,
        ':item_description' => $item['item_description'] ?? null,
        ':storage_location' => $item['storage_location'] ?? null,
        ':image_data'       => $item['imageDataUrl'] ?? null,
        ':status'           => 'Unclaimed Items',
    ]);

    // Auto-save barcode/QR image to folder so it displays from server
    $barcodeDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'barcodes';
    if (!is_dir($barcodeDir)) {
        @mkdir($barcodeDir, 0755, true);
    }
    $barcodeFile = $barcodeDir . DIRECTORY_SEPARATOR . $id . '.png';
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($id);
    $qrImg = @file_get_contents($qrUrl);
    if ($qrImg !== false && strlen($qrImg) > 100) {
        @file_put_contents($barcodeFile, $qrImg);
    }

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (PDOException $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    // Shorten for common cases so user knows what to fix
    if (strpos($msg, 'doesn\'t exist') !== false) {
        $msg = 'Database or table not found. Run database/lostandfound.sql in phpMyAdmin to create lostandfound_db and the items table.';
    } elseif (strpos($msg, 'Unknown column') !== false) {
        $msg = 'Table structure mismatch: ' . $msg;
    } else {
        $msg = 'Database error: ' . $msg;
    }
    echo json_encode(['ok' => false, 'error' => $msg]);
}
