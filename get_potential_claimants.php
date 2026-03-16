<?php
/**
 * Get potential claimants for a found item.
 *
 * Matching logic (all four must match when both have values):
 * - Category: item_type (Electronics & Gadgets, etc.)
 * - Item Type: from item_description "Item Type: X"
 * - Color
 * - Brand
 *
 * Returns reporters whose lost item reports match the found item.
 * Display format: Student Number + @ub.edu.ph
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
    echo json_encode(['ok' => false, 'error' => 'Missing item id']);
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

function extractStudentNumber($desc) {
    if (!is_string($desc) || $desc === '') return '';
    if (preg_match('/^Student Number:\s*(.+?)(?:\n|$)/m', $desc, $m)) {
        return trim($m[1]);
    }
    return '';
}

function formatClaimantEmail($userId, $itemDesc) {
    $studentNum = extractStudentNumber($itemDesc);
    if ($studentNum !== '') {
        return $studentNum . '@ub.edu.ph';
    }
    if ($userId !== '' && strpos($userId, '@ub.edu.ph') !== false) {
        return $userId;
    }
    return $userId ?: '';
}

try {
    $stmt = $pdo->prepare("SELECT id, item_type, color, brand, item_description FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $foundItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$foundItem) {
        echo json_encode(['ok' => false, 'error' => 'Item not found']);
        exit;
    }

    $cat = trim($foundItem['item_type'] ?? '');
    $color = trim($foundItem['color'] ?? '');
    $brand = trim($foundItem['brand'] ?? '');
    $foundDesc = $foundItem['item_description'] ?? '';
    $foundItemType = extractItemType($foundDesc);

    $reportsStmt = $pdo->prepare("
        SELECT id, user_id, item_type, color, brand, item_description
        FROM items
        WHERE id LIKE 'REF-%'
        ORDER BY created_at DESC
    ");
    $reportsStmt->execute();
    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

    $claimants = [];
    foreach ($reports as $r) {
        $rCat = trim($r['item_type'] ?? '');
        $rColor = trim($r['color'] ?? '');
        $rBrand = trim($r['brand'] ?? '');
        $rDesc = $r['item_description'] ?? '';
        $userId = trim($r['user_id'] ?? '');

        $itemTypeLabel = extractItemType($rDesc);
        $displayEmail = formatClaimantEmail($userId, $rDesc);
        if ($displayEmail === '' && $userId === '') continue;

        // Category: must match when both have values
        $catMatch = (!$cat && !$rCat) || ($cat && $rCat && strcasecmp($cat, $rCat) === 0);
        // Item Type: must match when both have values (from item_description "Item Type: X")
        $itemTypeMatch = true;
        if ($foundItemType !== '' && $itemTypeLabel !== '') {
            $itemTypeMatch = strcasecmp($foundItemType, $itemTypeLabel) === 0;
        }
        // Color: must match when both have values
        $colorMatch = !$color || !$rColor || strcasecmp($color, $rColor) === 0;
        // Brand: must match when both have values
        $brandMatch = !$brand || !$rBrand || strcasecmp($brand, $rBrand) === 0;

        $match = $catMatch && $itemTypeMatch && $colorMatch && $brandMatch;

        if ($match) {
            $claimants[] = [
                'report_id' => $r['id'],
                'user_id' => $displayEmail,
                'email' => $displayEmail,
                'item_type' => $rCat,
                'item_type_label' => $itemTypeLabel ?: $rCat,
            ];
        }
    }

    echo json_encode(['ok' => true, 'claimants' => $claimants]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not fetch claimants']);
}
