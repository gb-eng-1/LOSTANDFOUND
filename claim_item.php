<?php
/**
 * Claim an item - update status to Claimed, log to activity_log.
 * Called from Matching page when admin clicks Claim.
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
    $stmt = $pdo->prepare("UPDATE items SET status = 'Claimed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'Item not found or already claimed']);
        exit;
    }
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (item_id, action, details) VALUES (?, 'claimed', ?)");
    $stmtLog->execute([$id, 'Item claimed by admin']);
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Data truncated') !== false || strpos($e->getMessage(), 'enum') !== false) {
        echo json_encode(['ok' => false, 'error' => 'Please run database/claimed_status_migration.sql to add Claimed status']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not claim item']);
}
