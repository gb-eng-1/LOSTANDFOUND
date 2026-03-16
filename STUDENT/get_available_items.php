<?php
/**
 * Get Available Items for Claiming
 * Returns a list of items available for claiming
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

try {
    // Get available items to claim (found items that are unclaimed)
    $stmt = $pdo->query("SELECT id, item_type, color, brand, item_description, found_at, date_encoded, image_data FROM items WHERE id NOT LIKE 'REF-%' AND status IN ('Unclaimed Items', 'For Verification', 'Found') ORDER BY date_encoded DESC, created_at DESC LIMIT 20");
    $items = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    echo json_encode(['ok' => true, 'items' => $items]);
} catch (PDOException $e) {
    error_log("get_available_items.php: Database error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}