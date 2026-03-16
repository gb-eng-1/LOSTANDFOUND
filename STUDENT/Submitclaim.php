<?php
/**
 * SubmitClaim.php — Student submits a claim on a matched found item.
 *
 * Accepts POST JSON:  { "ticket_id": "REF-xxxx" }
 *
 * ticket_id is ALWAYS the REF- lost report ID (sent from both
 * StudentDashboard.php and StudentsReport.php claim modals).
 *
 * Logic:
 *   1. Auth-check: student must be logged in.
 *   2. Validate ticket_id is a real REF- report owned by this student.
 *   3. Resolve found_item_id via matched_barcode_id column (primary)
 *      OR item_matches junction table (fallback).
 *   4. Prevent duplicate active claims (Pending / Approved).
 *   5. Insert into claims table — returns { ok: true, claim_id: n }.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';           // sets $_SESSION['student_email'] etc.
require_once dirname(__DIR__) . '/config/database.php'; // provides $pdo

/* ── Helpers ── */
function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}
function json_ok(array $data): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

/* ── Only accept POST ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Method not allowed', 405);
}

/* ── Parse body ── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    json_err('Invalid JSON body');
}

$ticketId = isset($body['ticket_id']) ? trim((string)$body['ticket_id']) : '';
if ($ticketId === '' || strpos($ticketId, 'REF-') !== 0) {
    json_err('Missing or invalid ticket_id — must be a REF- lost report ID');
}

/* ── Session data ── */
$studentEmail = trim($_SESSION['student_email'] ?? '');
$studentId    = (int)($_SESSION['student_id']    ?? 0);

// Only email is required — student_id may not be in every session implementation
if (!$studentEmail) {
    json_err('Not authenticated', 401);
}

/* ── Resolve student_number (alternate user_id: 2021-00001@ub.edu.ph) ── */
$studentNumber = null;
try {
    if ($studentId > 0) {
        $s = $pdo->prepare('SELECT student_id FROM students WHERE id = ? LIMIT 1');
        $s->execute([$studentId]);
    } else {
        $s = $pdo->prepare('SELECT student_id FROM students WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1');
        $s->execute([$studentEmail]);
    }
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['student_id'])) {
        $studentNumber = trim($row['student_id']);
    }
} catch (PDOException $e) { /* non-fatal — claim proceeds with email only */ }

$userIds  = [strtolower($studentEmail)];   // always use lower-case for DB comparisons
if ($studentNumber) {
    $userIds[] = strtolower($studentNumber . '@ub.edu.ph');
}
$ph = implode(',', array_fill(0, count($userIds), '?'));

/* ── 1. Verify the REF- report belongs to this student ── */
$report = null;
try {
    $stmt = $pdo->prepare(
        "SELECT id, status, matched_barcode_id
         FROM items
         WHERE id = ?
           AND id LIKE 'REF-%'
           AND LOWER(TRIM(user_id)) IN ($ph)
         LIMIT 1"
    );
    $params = array_merge([$ticketId], $userIds);
    $stmt->execute($params);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    json_err('Database error while verifying report', 500);
}

if (!$report) {
    json_err('Report not found or you do not have permission to claim it', 403);
}

/* ── 2. Guard: report must not already be Cancelled/Disposed/Claimed ── */
$nonClaimable = ['Cancelled', 'Disposed', 'Claimed', 'Resolved'];
if (in_array($report['status'], $nonClaimable, true)) {
    json_err('This report is ' . $report['status'] . ' and cannot be claimed');
}

/* ── 3. Resolve found_item_id ────────────────────────────────────────────
   Priority A: matched_barcode_id column on items (legacy / primary path)
   Priority B: item_matches junction table (new M:N path) ── */
$foundItemId = null;

// Path A — direct column
if (!empty($report['matched_barcode_id'])) {
    $foundItemId = trim($report['matched_barcode_id']);
}

// Path B — item_matches junction table (if Path A gave nothing)
if (!$foundItemId) {
    try {
        $hasTable = false;
        $chk = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'item_matches'"
        );
        $hasTable = $chk && (int)$chk->fetchColumn() > 0;

        if ($hasTable) {
            $s2 = $pdo->prepare(
                "SELECT found_item_id FROM item_matches
                 WHERE lost_report_id = ?
                   AND status != 'Rejected'
                 ORDER BY (status = 'Confirmed') DESC, created_at DESC
                 LIMIT 1"
            );
            $s2->execute([$ticketId]);
            $row2 = $s2->fetch(PDO::FETCH_ASSOC);
            if ($row2) $foundItemId = trim($row2['found_item_id']);
        }
    } catch (PDOException $e) { /* non-fatal */ }
}

if (!$foundItemId) {
    json_err('No matched found item for this report — the office has not yet linked a found item to your report');
}

/* ── 4. Prevent duplicate active claims ── */
try {
    $dup = $pdo->prepare(
        "SELECT id FROM claims
         WHERE lost_report_id = ?
           AND LOWER(TRIM(claimant_id)) IN ($ph)
           AND status NOT IN ('Rejected')
         LIMIT 1"
    );
    $dupParams = array_merge([$ticketId], $userIds);
    $dup->execute($dupParams);
    if ($dup->fetch()) {
        json_err('You have already submitted a claim for this report. Please visit the Lost & Found office with your Ticket ID.');
    }
} catch (PDOException $e) {
    json_err('Database error while checking for duplicates', 500);
}

/* ── 5. Insert the claim — always store claimant_id as the original email ── */
try {
    $ins = $pdo->prepare(
        "INSERT INTO claims (lost_report_id, found_item_id, claimant_id, status, notes)
         VALUES (?, ?, ?, 'Pending', ?)"
    );
    $notes = 'Claim submitted by student via online portal.';
    $ins->execute([$ticketId, $foundItemId, $studentEmail, $notes]);
    $claimId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    json_err('Could not save the claim — please try again', 500);
}

/* ── 6. Optionally update the found item status to "Unresolved Claimants" ──
   Only if it is currently Unclaimed Items. This signals to admin that
   a student has come forward. Does not transition away from For Verification. */
try {
    $upd = $pdo->prepare(
        "UPDATE items SET status = 'Unresolved Claimants', updated_at = NOW()
         WHERE id = ? AND status = 'Unclaimed Items'"
    );
    $upd->execute([$foundItemId]);
} catch (PDOException $e) { /* non-fatal — claim was still saved */ }

json_ok([
    'claim_id'       => $claimId,
    'lost_report_id' => $ticketId,
    'found_item_id'  => $foundItemId,
    'message'        => 'Claim submitted successfully',
]);