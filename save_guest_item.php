<?php
/**
 * Save guest item (Encode ID) to database.
 * Called from FoundAdmin when encoding a guest-surrendered ID.
 * POST body: JSON with barcode_id (optional), id_type, fullname, color, storage_location, encoded_by, date_surrendered, imageDataUrl
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

// Barcode ID: use provided or auto-generate (UB + 5-digit, same format as internal items)
$barcodeId = !empty($item['barcode_id']) ? trim($item['barcode_id']) : null;
if (!$barcodeId) {
    try {
        $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) AS max_num FROM items WHERE id LIKE 'UB%' AND LENGTH(id) = 7");
        $row = $stmtMax ? $stmtMax->fetch(PDO::FETCH_ASSOC) : null;
        $nextNum = $row && $row['max_num'] !== null ? (int) $row['max_num'] + 1 : 10000;
        $barcodeId = 'UB' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        $barcodeId = 'UB' . str_pad(10000 + (int) mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
    }
}

// Map Encode ID form fields to items table
$itemType = $item['item'] ?? $item['id_type'] ?? null;
$fullname = $item['fullname'] ?? null;
$color = $item['color'] ?? null;
$brand = $item['brand'] ?? null;
$storageLocation = $item['storage_location'] ?? null;
$encodedBy = $item['encoded_by'] ?? null;
$foundAt = $item['found_at'] ?? null;
$dateSurrendered = !empty($item['date_surrendered']) ? $item['date_surrendered'] : date('Y-m-d');
$itemDescription = $item['item_description'] ?? null;
$imageDataUrl = $item['imageDataUrl'] ?? null;

$sql = 'INSERT INTO items (id, user_id, item_type, color, brand, found_at, found_by, date_encoded, date_lost, item_description, storage_location, image_data, status) 
        VALUES (:id, :user_id, :item_type, :color, :brand, :found_at, :found_by, :date_encoded, :date_lost, :item_description, :storage_location, :image_data, :status)';

$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([
        ':id'               => $barcodeId,
        ':user_id'          => $fullname,
        ':item_type'        => $itemType,
        ':color'            => $color,
        ':brand'            => $brand,
        ':found_at'         => $foundAt,
        ':found_by'         => $encodedBy,
        ':date_encoded'     => $dateSurrendered,
        ':date_lost'        => null,
        ':item_description' => $itemDescription,
        ':storage_location' => $storageLocation,
        ':image_data'       => $imageDataUrl,
        ':status'           => 'Unclaimed IDs External',
    ]);

    echo json_encode(['ok' => true, 'id' => $barcodeId]);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, "enum('Unclaimed Items'") !== false || strpos($msg, 'Data truncated') !== false) {
        $msg = "Status 'Unclaimed IDs External' not in database. Run database/guest_items_migration.sql in phpMyAdmin.";
    } elseif (strpos($msg, "doesn't exist") !== false) {
        $msg = 'Database or table not found. Run database/lostandfound.sql first.';
    } elseif (strpos($msg, 'Duplicate entry') !== false) {
        $msg = 'Barcode ID already exists. Use a different ID or leave blank to auto-generate.';
    } else {
        $msg = 'Database error: ' . $msg;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $msg]);
}
