<?php
/**
 * Get found items that match a lost report (REF-xxx).
 *
 * Scoring logic (20 pts each trait, 100 pts max):
 * - Category (item_type)
 * - Item Name (from item_description "Item Type: X")
 * - Color
 * - Brand
 * - Description keyword overlap (Jaccard similarity × 20)
 *
 * Returns ALL found items with score > 0, sorted by score descending.
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

/**
 * Jaccard keyword similarity between two text strings.
 * Returns 0.0–1.0. Words ≤3 chars and metadata prefixes are ignored.
 */
function descriptionSimilarity($a, $b) {
    $stopPrefixes = ['item type:', 'student number:', 'contact:', 'department:', '--- claim record ---', 'claimed by:', 'email:', 'date accomplished:'];
    $clean = function($t) use ($stopPrefixes) {
        $t = strtolower(strip_tags($t ?? ''));
        foreach ($stopPrefixes as $p) $t = str_replace($p, ' ', $t);
        preg_match_all('/[a-z]{4,}/', $t, $m);
        return array_unique($m[0]);
    };
    $w1 = $clean($a);
    $w2 = $clean($b);
    if (!$w1 || !$w2) return 0.0;
    $inter = array_intersect($w1, $w2);
    $union = array_unique(array_merge($w1, $w2));
    return count($inter) / count($union);
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
        $fCat      = trim($f['item_type'] ?? '');
        $fColor    = trim($f['color']     ?? '');
        $fBrand    = trim($f['brand']     ?? '');
        $fDesc     = $f['item_description'] ?? '';
        $fItemType = extractItemType($fDesc);

        $score = 0;

        // Category (20 pts) — must match when both present
        if ($cat && $fCat && strcasecmp($cat, $fCat) === 0)                           $score += 20;
        // Item Name (20 pts)
        if ($reportItemType && $fItemType && strcasecmp($reportItemType, $fItemType) === 0) $score += 20;
        // Color (20 pts)
        if ($color && $fColor && strcasecmp($color, $fColor) === 0)                   $score += 20;
        // Brand (20 pts)
        if ($brand && $fBrand && strcasecmp($brand, $fBrand) === 0)                   $score += 20;
        // Description keyword overlap (up to 20 pts)
        $score += (int) round(descriptionSimilarity($reportDesc, $fDesc) * 20);

        if ($score > 0) {
            $matches[] = [
                'id'               => $f['id'],
                'found_at'         => $f['found_at'],
                'storage_location' => $f['storage_location'],
                'score'            => $score,
            ];
        }
    }

    // Sort by score descending (best matches first)
    usort($matches, fn($a, $b) => $b['score'] - $a['score']);

    echo json_encode(['ok' => true, 'found_items' => $matches]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not fetch matching items']);
}
