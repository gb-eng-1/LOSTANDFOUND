<?php
/**
 * Delete a found item (or guest item) by Barcode ID.
 * Called from FoundAdmin when user clicks Cancel on an item row.
 * POST body: JSON with id (barcode id)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing item id']);
    exit;
}

$id = trim($data['id']);
if ($id === '') {
    echo json_encode(['ok' => false, 'error' => 'Invalid item id']);
    exit;
}

require __DIR__ . '/config/database.php';

try {
    $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'Item not found or already deleted']);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not delete item']);
}
