<?php
/**
 * CancelReport.php — Student: cancel a lost-item report
 * POST  (JSON body)  { "id": "REF-xxxx" }
 * Returns JSON { ok: true } | { ok: false, message: "..." }
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

function notifyAdmin($pdo, $type, $title, $message, $relatedId = null) {
    try {
        $aid = $pdo->query('SELECT id FROM admins ORDER BY id LIMIT 1')->fetchColumn();
        if ($aid) {
            $pdo->prepare(
                "INSERT INTO notifications (recipient_id, recipient_type, type, title, message, related_id, created_at)
                 VALUES (?, 'admin', ?, ?, ?, ?, NOW())"
            )->execute([(int)$aid, $type, $title, $message, $relatedId]);
        }
    } catch (Exception $e) { /* non-fatal */ }
}

header('Content-Type: application/json');

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$body     = json_decode(file_get_contents('php://input'), true);
$reportId = trim($body['id'] ?? '');

if ($reportId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing report ID.']);
    exit;
}

// ── Student identity ──────────────────────────────────────────────────────────
$studentId    = (int)($_SESSION['student_id']    ?? 0);
$studentEmail = trim($_SESSION['student_email']  ?? '');

if (!$studentId || $studentEmail === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
    exit;
}

try {
    // Build the same user_id patterns used throughout the app
    $studentNumber = null;
    $s = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
    $s->execute([$studentId]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['student_id'])) {
        $studentNumber = trim($r['student_id']);
    }

    $userIds = [$studentEmail];
    if ($studentNumber) {
        $userIds[] = $studentNumber . '@ub.edu.ph';
    }
    $ph = implode(',', array_fill(0, count($userIds), '?'));

    // ── Fetch the report and verify ownership ─────────────────────────────────
    $s = $pdo->prepare(
        "SELECT id, status, matched_barcode_id, created_at FROM items
          WHERE id = ?
            AND id LIKE 'REF-%'
            AND (user_id IN ($ph) OR LOWER(TRIM(user_id)) = LOWER(?))"
    );
    $params = array_merge([$reportId], $userIds, [$studentEmail]);
    $s->execute($params);
    $report = $s->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        echo json_encode(['ok' => false, 'message' => 'Report not found or you do not have permission to cancel it.']);
        exit;
    }

    // ── Guard: 24-hour cooldown (student only) ────────────────────────────────
    $createdTs = (int)strtotime($report['created_at'] ?? '');
    if ($createdTs > 0 && ($createdTs + 86400) > time()) {
        $cooldownUntil = date('M j, Y \a\t g:i A', $createdTs + 86400);
        echo json_encode(['ok' => false, 'message' => 'Reports can only be cancelled 24 hours after submission. You may cancel this report after: ' . $cooldownUntil . '.']);
        exit;
    }

    // ── Guard: check claims table for a finalized claim ───────────────────────
    try {
        $s = $pdo->prepare("SELECT status FROM claims WHERE lost_report_id = ? AND status IN ('Approved', 'Resolved') LIMIT 1");
        $s->execute([$reportId]);
        $claim = $s->fetch(PDO::FETCH_ASSOC);
        if ($claim) {
            echo json_encode(['ok' => false, 'message' => 'This report is part of a claim that has been ' . strtolower($claim['status']) . ' and cannot be cancelled.']);
            exit;
        }
    } catch (PDOException $e) {
        // If claims table doesn't exist or there's an error, we can ignore and proceed.
        // The status check below is the primary guard.
        error_log("CancelReport.php: Could not check claims table. " . $e->getMessage());
    }

    // ── Guard: non-cancellable statuses ───────────────────────────────────────
    $nonCancellable = ['Claimed', 'Disposed', 'Cancelled', 'Resolved'];
    if (in_array($report['status'], $nonCancellable, true)) {
        echo json_encode(['ok' => false, 'message' => 'This report has already been ' . strtolower($report['status']) . ' and cannot be cancelled.']);
        exit;
    }

    // ── Update + log inside a transaction ─────────────────────────────────────
    $pdo->beginTransaction();

    // 1) Set the lost report's status to 'Cancelled'
    $pdo->prepare(
        "UPDATE items SET status = 'Cancelled', updated_at = NOW() WHERE id = ?"
    )->execute([$reportId]);

    // 2) If the report was matched, revert the found item's status
    $matchedItemId = $report['matched_barcode_id'] ?? null;
    if ($matchedItemId) {
        // Revert status to 'Found' so it can be matched with other reports
        $pdo->prepare(
            "UPDATE items SET status = 'Found', updated_at = NOW() WHERE id = ? AND id NOT LIKE 'REF-%'"
        )->execute([$matchedItemId]);

        // Log this action for the found item's history
        $pdo->prepare(
            "INSERT INTO activity_log (item_id, action, details) VALUES (?, 'match_cancelled', ?)"
        )->execute([
            $matchedItemId,
            json_encode(['reason' => 'Lost report ' . $reportId . ' was cancelled by the student.'])
        ]);
    }

    // 3) Log the cancellation to activity_log for the lost report
    $pdo->prepare(
        "INSERT INTO activity_log (item_id, action, details)
         VALUES (?, 'cancelled', ?)"
    )->execute([
        $reportId,
        json_encode([
            'student_id'    => $studentId,
            'student_email' => $studentEmail,
            'cancelled_at'  => date('Y-m-d H:i:s'),
        ])
    ]);

    $pdo->commit();

    notifyAdmin($pdo, 'report_cancelled',
        'Report Cancelled by Student',
        'Report ' . $reportId . ' has been cancelled by the student.',
        $reportId
    );

    echo json_encode(['ok' => true, 'message' => 'Report cancelled successfully.']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('CancelReport.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error. Please try again.']);
}