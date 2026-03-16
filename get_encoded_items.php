<?php
// get_encoded_items.php - return items from database as JSON (for admin tables)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/config/database.php';

$status = isset($_GET['status']) ? trim($_GET['status']) : null;
if ($status === '') {
    $status = null;
}
$items = get_items($pdo, $status);
echo json_encode($items);
