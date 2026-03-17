<?php
/**
 * search_items.php — Universal item search endpoint (Admin side)
 *
 * Scope: admins can search ALL items:
 *   (a) All found items (non-REF barcodes)
 *   (b) All lost reports (REF- tickets)
 *
 * Word-based matching: all space-separated words must match in
 * at least one of: id (barcode/ticket), item_type (name), brand.
 *
 * GET ?q=<search_term>  (min 2 chars)
 * Returns JSON array of up to 8 matching items.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }
$q = substr($q, 0, 80);

// Split query into words — ALL must match
$words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
if (empty($words)) { echo json_encode([]); exit; }

try {
    $results = [];
    $seenIds = [];

    // ─────────────────────────────────────────────────────────────────────
    // PART A: All found items (non-REF barcodes)
    // ─────────────────────────────────────────────────────────────────────
    $params = [];
    $wordConds = [];
    foreach ($words as $w) {
        $params[] = '%' . $w . '%';
        $params[] = '%' . $w . '%';
        $params[] = '%' . $w . '%';
        $wordConds[] = "(LOWER(id) LIKE ? OR LOWER(item_type) LIKE ? OR LOWER(IFNULL(brand,'')) LIKE ?)";
    }
    $whereSql = implode(' AND ', $wordConds);

    $stmt = $pdo->prepare("
        SELECT id, item_type, brand, color, item_description,
               found_at, date_encoded, status
        FROM items
        WHERE id NOT LIKE 'REF-%'
          AND status NOT IN ('Disposed','Cancelled')
          AND ($whereSql)
        ORDER BY date_encoded DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $foundRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($foundRows as $r) {
        if (isset($seenIds[$r['id']])) continue;
        $seenIds[$r['id']] = true;
        $desc = trim($r['item_description'] ?? '');
        $results[] = [
            'id'          => $r['id'],
            'item_type'   => $r['item_type']   ?? '',
            'brand'       => $r['brand']        ?? '',
            'color'       => $r['color']        ?? '',
            'description' => mb_strlen($desc) > 90 ? mb_substr($desc, 0, 90) . '…' : $desc,
            'found_at'    => $r['found_at']     ?? '',
            'date'        => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '',
            'status'      => $r['status']       ?? '',
            'type'        => 'found',
        ];
        if (count($results) >= 8) break;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PART B: All lost reports (REF- tickets), if room left
    // ─────────────────────────────────────────────────────────────────────
    if (count($results) < 8) {
        $params2 = [];
        $wordConds2 = [];
        foreach ($words as $w) {
            $params2[] = '%' . $w . '%';
            $params2[] = '%' . $w . '%';
            $params2[] = '%' . $w . '%';
            $wordConds2[] = "(LOWER(id) LIKE ? OR LOWER(item_type) LIKE ? OR LOWER(IFNULL(brand,'')) LIKE ?)";
        }
        $whereSql2 = implode(' AND ', $wordConds2);

        $stmt2 = $pdo->prepare("
            SELECT id, item_type, brand, color, item_description,
                   found_at, date_lost AS date_encoded, status
            FROM items
            WHERE id LIKE 'REF-%'
              AND status != 'Cancelled'
              AND ($whereSql2)
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt2->execute($params2);
        $refRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach ($refRows as $r) {
            if (isset($seenIds[$r['id']])) continue;
            $desc = $r['item_description'] ?? '';
            $itemType = $r['item_type'] ?? '';
            if (!$itemType && preg_match('/Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) {
                $itemType = trim($m[1]);
            }
            $seenIds[$r['id']] = true;
            $cleanDesc = preg_replace('/^(Full Name|Student Number|Student ID|Item Type|Item Name|Contact|Department|Name):[^\n]*\n?/m', '', $desc);
            $cleanDesc = trim($cleanDesc);
            $results[] = [
                'id'          => $r['id'],
                'item_type'   => $itemType ?: 'Lost Report',
                'brand'       => $r['brand']        ?? '',
                'color'       => $r['color']        ?? '',
                'description' => mb_strlen($cleanDesc) > 90 ? mb_substr($cleanDesc, 0, 90) . '…' : $cleanDesc,
                'found_at'    => '',
                'date'        => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '',
                'status'      => $r['status']       ?? '',
                'type'        => 'report',
            ];
            if (count($results) >= 8) break;
        }
    }

    echo json_encode($results);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([]);
}
