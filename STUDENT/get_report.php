<?php
/**
 * Get Report Details
 * Simple endpoint to fetch report details for modal display
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'No ID provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Item not found']);
        exit;
    }
    
    echo json_encode(['ok' => true, 'data' => $item]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
