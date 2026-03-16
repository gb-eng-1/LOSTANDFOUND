<?php
/**
 * search_items.php — Universal item search endpoint (Student side)
 *
 * Scope: students can only search:
 *   (a) Found items matched to their own REF- lost reports
 *       (via matched_barcode_id column OR item_matches junction table)
 *   (b) Their own REF- lost reports
 *
 * Word-based matching: all space-separated words must match in
 * at least one of: id (barcode/ticket), item_type (name), brand.
 *
 * GET ?q=<search_term>  (min 2 chars)
 * Returns JSON array of matching items.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }
$q = substr($q, 0, 80);

// Identify the current student
$studentEmail  = $_SESSION['student_email'] ?? '';
$studentId     = (int)($_SESSION['student_id'] ?? 0);
if (!$studentEmail) { echo json_encode([]); exit; }

// Resolve student number for alternate user_id format
$studentNumber = null;
try {
    $s = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
    $s->execute([$studentId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['student_id'])) $studentNumber = trim($row['student_id']);
} catch (PDOException $e) {}

$userIds = [$studentEmail];
if ($studentNumber) $userIds[] = $studentNumber . '@ub.edu.ph';
$ph = implode(',', array_fill(0, count($userIds), '?'));

// Split query into words — ALL must match
$words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
if (empty($words)) { echo json_encode([]); exit; }

// Build per-word match condition
function wordCondition(string $field, array &$params, string $word): string {
    $params[] = '%' . $word . '%';
    return "LOWER($field) LIKE ?";
}

try {
    // ── Check if matched_barcode_id column exists ──────────────────────────
    $hasMatchedCol = false;
    try {
        $c = $pdo->query("SHOW COLUMNS FROM items LIKE 'matched_barcode_id'");
        $hasMatchedCol = $c && $c->rowCount() > 0;
    } catch (PDOException $e) {}

    // ── Check if item_matches table exists ────────────────────────────────
    $hasMatchesTable = false;
    try {
        $c = $pdo->query("SHOW TABLES LIKE 'item_matches'");
        $hasMatchesTable = $c && $c->rowCount() > 0;
    } catch (PDOException $e) {}

    $results = [];
    $seenIds = [];

    // ─────────────────────────────────────────────────────────────────────
    // PART A: Found items matched to this student's REF- reports
    // ─────────────────────────────────────────────────────────────────────
    $foundCandidates = [];

    // A1: via matched_barcode_id (legacy single-match column)
    if ($hasMatchedCol) {
        $params = array_merge($userIds, [$studentEmail]);
        $stmt = $pdo->prepare("
            SELECT f.id, f.item_type, f.brand, f.color, f.item_description,
                   f.found_at, f.date_encoded, f.status
            FROM items ref
            JOIN items f ON f.id = ref.matched_barcode_id
            WHERE ref.id LIKE 'REF-%'
              AND ref.matched_barcode_id IS NOT NULL
              AND (ref.user_id IN ($ph) OR LOWER(TRIM(ref.user_id)) = LOWER(?))
              AND f.status NOT IN ('Disposed','Cancelled')
            ORDER BY f.date_encoded DESC
        ");
        $stmt->execute($params);
        $foundCandidates = array_merge($foundCandidates, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // A2: via item_matches junction table
    if ($hasMatchesTable) {
        $params = array_merge($userIds, [$studentEmail]);
        $stmt = $pdo->prepare("
            SELECT DISTINCT f.id, f.item_type, f.brand, f.color, f.item_description,
                   f.found_at, f.date_encoded, f.status
            FROM item_matches m
            JOIN items ref ON ref.id = m.lost_report_id
            JOIN items f   ON f.id   = m.found_item_id
            WHERE ref.id LIKE 'REF-%'
              AND m.status != 'Rejected'
              AND (ref.user_id IN ($ph) OR LOWER(TRIM(ref.user_id)) = LOWER(?))
              AND f.status NOT IN ('Disposed','Cancelled')
            ORDER BY f.date_encoded DESC
        ");
        $stmt->execute($params);
        $foundCandidates = array_merge($foundCandidates, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Filter found items by word query (item_type, id, brand)
    foreach ($foundCandidates as $r) {
        if (isset($seenIds[$r['id']])) continue;
        $haystack = strtolower(($r['id'] ?? '') . ' ' . ($r['item_type'] ?? '') . ' ' . ($r['brand'] ?? ''));
        $allMatch = true;
        foreach ($words as $w) {
            if (strpos($haystack, strtolower($w)) === false) { $allMatch = false; break; }
        }
        if (!$allMatch) continue;
        $seenIds[$r['id']] = true;
        $desc = trim($r['item_description'] ?? '');
        $results[] = [
            'id'          => $r['id'],
            'item_type'   => $r['item_type']  ?? '',
            'brand'       => $r['brand']       ?? '',
            'color'       => $r['color']       ?? '',
            'description' => mb_strlen($desc) > 90 ? mb_substr($desc, 0, 90) . '…' : $desc,
            'found_at'    => $r['found_at']    ?? '',
            'date'        => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '',
            'status'      => $r['status']      ?? '',
            'type'        => 'found',
        ];
        if (count($results) >= 8) break;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PART B: This student's own REF- lost reports (if room left)
    // ─────────────────────────────────────────────────────────────────────
    if (count($results) < 8) {
        $params = array_merge($userIds, [$studentEmail]);
        $stmt = $pdo->prepare("
            SELECT r.id, r.item_type, r.brand, r.color, r.item_description,
                   r.found_at, r.date_lost AS date_encoded, r.status
            FROM items r
            WHERE r.id LIKE 'REF-%'
              AND (r.user_id IN ($ph) OR LOWER(TRIM(r.user_id)) = LOWER(?))
              AND r.status != 'Cancelled'
            ORDER BY r.created_at DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $refRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($refRows as $r) {
            if (isset($seenIds[$r['id']])) continue;
            // Parse item name from item_description if brand/item_type is missing
            $desc = $r['item_description'] ?? '';
            $itemType = $r['item_type'] ?? '';
            if (!$itemType && preg_match('/Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) {
                $itemType = trim($m[1]);
            }
            $haystack = strtolower(($r['id'] ?? '') . ' ' . $itemType . ' ' . ($r['brand'] ?? ''));
            $allMatch = true;
            foreach ($words as $w) {
                if (strpos($haystack, strtolower($w)) === false) { $allMatch = false; break; }
            }
            if (!$allMatch) continue;
            $seenIds[$r['id']] = true;
            $cleanDesc = preg_replace('/^(Full Name|Student Number|Student ID|Item Type|Item Name|Contact|Department|Name):[^\n]*\n?/m', '', $desc);
            $cleanDesc = trim($cleanDesc);
            $results[] = [
                'id'          => $r['id'],
                'item_type'   => $itemType ?: 'Lost Report',
                'brand'       => $r['brand'] ?? '',
                'color'       => $r['color'] ?? '',
                'description' => mb_strlen($cleanDesc) > 90 ? mb_substr($cleanDesc, 0, 90) . '…' : $cleanDesc,
                'found_at'    => '',
                'date'        => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '',
                'status'      => $r['status'] ?? '',
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