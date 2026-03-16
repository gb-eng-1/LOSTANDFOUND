<?php
/**
 * Get found items that match a lost report (REF-xxx).
 *
 * Matching logic (all four must match when both have values):
 * - Category: item_type (Electronics & Gadgets, etc.)
 * - Item Type: from item_description "Item Type: X"
 * - Color
 * - Brand
 *
 * Used when viewing a report on the Matching page - shows which found items match.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id = isset($data['id']) ? trim($data['id']) : null;
} else {
    $id = isset($_GET['id']) ? trim($_GET['id']) : null;
}

if (!$id || $id === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing report id']);
    exit;
}

require __DIR__ . '/config/database.php';

function extractItemType($desc) {
    if (!is_string($desc) || $desc === '') return '';
    if (preg_match('/^Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) {
        return trim($m[1]);
    }
    return '';
}

try {
    $stmt = $pdo->prepare("SELECT id, item_type, color, brand, item_description, found_at, storage_location FROM items WHERE id = ? AND id LIKE 'REF-%'");
    $stmt->execute([$id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        echo json_encode(['ok' => false, 'error' => 'Report not found']);
        exit;
    }

    $cat = trim($report['item_type'] ?? '');
    $color = trim($report['color'] ?? '');
    $brand = trim($report['brand'] ?? '');
    $reportDesc = $report['item_description'] ?? '';
    $reportItemType = extractItemType($reportDesc);

    $foundStmt = $pdo->prepare("
        SELECT id, item_type, color, brand, item_description, found_at, storage_location
        FROM items
        WHERE id NOT LIKE 'REF-%'
        AND status IN ('Unclaimed Items', 'For Verification')
        ORDER BY date_encoded DESC, created_at DESC
    ");
    $foundStmt->execute();
    $allFound = $foundStmt->fetchAll(PDO::FETCH_ASSOC);

    $matches = [];
    foreach ($allFound as $f) {
        $fCat = trim($f['item_type'] ?? '');
        $fColor = trim($f['color'] ?? '');
        $fBrand = trim($f['brand'] ?? '');
        $fDesc = $f['item_description'] ?? '';
        $fItemType = extractItemType($fDesc);

        // Category, Item Type, Color, Brand - must match when both have values
        $catMatch = (!$cat && !$fCat) || ($cat && $fCat && strcasecmp($cat, $fCat) === 0);
        $itemTypeMatch = true;
        if ($reportItemType !== '' && $fItemType !== '') {
            $itemTypeMatch = strcasecmp($reportItemType, $fItemType) === 0;
        }
        $colorMatch = !$color || !$fColor || strcasecmp($color, $fColor) === 0;
        $brandMatch = !$brand || !$fBrand || strcasecmp($brand, $fBrand) === 0;

        if ($catMatch && $itemTypeMatch && $colorMatch && $brandMatch) {
            $matches[] = [
                'id' => $f['id'],
                'found_at' => $f['found_at'],
                'storage_location' => $f['storage_location'],
            ];
        }
    }

    echo json_encode(['ok' => true, 'found_items' => $matches]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not fetch matching items']);
}
