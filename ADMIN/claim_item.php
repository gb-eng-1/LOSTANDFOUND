<?php
/**
 * claim_item.php  — place in /LOSTANDFOUND/ (root, same level as save_guest_item.php)
 *
 * Called by ItemMatchedAdmin.php → Confirm Item Claim modal.
 * Sets item status = 'Claimed' and appends claimant details to item_description.
 * No new DB columns needed — works on existing schema.
 *
 * POST JSON:
 *   id                string  Barcode ID (required)
 *   claimant_name     string  Required
 *   ub_mail           string  Optional — must be @ub.edu.ph if supplied
 *   contact_number    string  Optional
 *   date_accomplished string  Optional ISO date
 *   imageDataUrl      string  Required — base64 proof image
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require dirname(__DIR__) . '/config/database.php';

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);

if (!$in || empty($in['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing item ID.']);
    exit;
}

$id           = trim($in['id']);
$claimantName = trim($in['claimant_name']     ?? '');
$ubMail       = trim($in['ub_mail']           ?? '');
$contact      = trim($in['contact_number']    ?? '');
$dateAccomp   = trim($in['date_accomplished'] ?? '');
$imageDataUrl = $in['imageDataUrl']           ?? null;

/* Validate */
if ($claimantName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Claimant name is required.']);
    exit;
}
if ($ubMail !== '' && !preg_match('/^[^@]+@ub\.edu\.ph$/i', $ubMail)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'UB Mail must end with @ub.edu.ph.']);
    exit;
}
if (empty($imageDataUrl)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'A proof image is required.']);
    exit;
}

/* Fetch item */
try {
    $chk = $pdo->prepare('SELECT id, item_description, status FROM items WHERE id = ?');
    $chk->execute([$id]);
    $item = $chk->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

if (!$item) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => "Item '{$id}' not found."]);
    exit;
}

/* Build appended description */
$note  = "\n\n--- Claim Record ---";
$note .= "\nClaimed By: {$claimantName}";
if ($ubMail)    $note .= "\nUB Mail: {$ubMail}";
if ($contact)   $note .= "\nContact: {$contact}";
if ($dateAccomp) $note .= "\nDate Accomplished: {$dateAccomp}";
$updatedDesc = ($item['item_description'] ?? '') . $note;

/* Update DB */
try {
    $pdo->beginTransaction();

    /* Try with image_data column (stores proof photo) */
    try {
        $s = $pdo->prepare(
            "UPDATE items
             SET status = 'Claimed',
                 item_description = :desc,
                 image_data = :img,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $s->execute([':desc' => $updatedDesc, ':img' => $imageDataUrl, ':id' => $id]);
    } catch (PDOException $colErr) {
        /* Fallback: image_data column may not exist */
        $s2 = $pdo->prepare(
            "UPDATE items
             SET status = 'Claimed',
                 item_description = :desc,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $s2->execute([':desc' => $updatedDesc, ':id' => $id]);
    }

    /* Optional activity log */
    try {
        $pdo->prepare(
            "INSERT INTO activity_log (action, item_id, detail, created_at)
             VALUES ('claimed', :iid, :det, NOW())"
        )->execute([
            ':iid' => $id,
            ':det' => "Claimed by {$claimantName}" . ($ubMail ? " ({$ubMail})" : ''),
        ]);
    } catch (PDOException $e) { /* non-fatal */ }

    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update: ' . $e->getMessage()]);
}