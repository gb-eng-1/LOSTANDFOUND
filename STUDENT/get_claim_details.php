<?php
/**
 * get_claim_details.php
 * Fetches comprehensive details for a claim, including found item,
 * lost report, claimant, and admin who encoded the item.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'ID required.']);
    exit;
}

try {
    // The ID can be a found item ID (UB-...) or a lost report ID (REF-...)
    $found_item_id = null;
    $lost_report_id = null;
    $user_id_from_report = null;

    // Find the formal claim record in the `claims` table first.
    $sql = "
        SELECT 
            c.id as claim_id,
            c.reference_id as claim_ref_id,
            c.status as claim_status,
            c.claim_date,
            c.resolution_date,
            c.found_item_id,
            c.lost_report_id,
            s.name as claimant_name,
            s.phone as claimant_phone
        FROM claims c
        JOIN students s ON c.student_id = s.id
        WHERE c.found_item_id = ? OR c.lost_report_id = ?
        ORDER BY c.claim_date DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $id]);
    $claim_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($claim_data) {
        $found_item_id = $claim_data['found_item_id'];
        $lost_report_id = $claim_data['lost_report_id'];
    } else {
        // If no formal claim, check for a pending claim via item status 'Unresolved Claimants'
        $item_stmt = $pdo->prepare("SELECT id, status, matched_barcode_id, user_id FROM items WHERE id = ?");
        $item_stmt->execute([$id]);
        $item = $item_stmt->fetch(PDO::FETCH_ASSOC);

        if ($item && $item['status'] === 'Unresolved Claimants') {
            $claim_data['claim_status'] = 'Pending';
            if (strpos($id, 'REF-') === 0) { // ID is a lost report
                $lost_report_id = $id;
                $found_item_id = $item['matched_barcode_id'];
                $user_id_from_report = $item['user_id'];
            } else { // ID is a found item
                $found_item_id = $id;
                $ref_stmt = $pdo->prepare("SELECT id, user_id FROM items WHERE matched_barcode_id = ? AND status = 'Unresolved Claimants' LIMIT 1");
                $ref_stmt->execute([$id]);
                if ($ref_item = $ref_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $lost_report_id = $ref_item['id'];
                    $user_id_from_report = $ref_item['user_id'];
                }
            }

            if ($user_id_from_report) {
                $student_stmt = $pdo->prepare("SELECT name, phone FROM students WHERE email = ?");
                $student_stmt->execute([$user_id_from_report]);
                if ($student = $student_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $claim_data['claimant_name'] = $student['name'];
                    $claim_data['claimant_phone'] = $student['phone'];
                }
            }
        }
    }

    if (!$found_item_id && strpos($id, 'UB-') === 0) $found_item_id = $id;
    if (!$lost_report_id && strpos($id, 'REF-') === 0) $lost_report_id = $id;

    if (!$found_item_id && !$lost_report_id) {
        echo json_encode(['ok' => false, 'error' => 'Could not resolve item or claim.']);
        exit;
    }

    $response = ['claim' => $claim_data ?: null];

    if ($found_item_id) {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$found_item_id]);
        $response['found_item'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Fetch 'Encoded By' from activity log for the found item
        $stmt = $pdo->prepare("SELECT al.actor_id FROM activity_log al WHERE al.item_id = ? AND al.action = 'encoded' AND al.actor_type = 'admin' ORDER BY al.created_at ASC LIMIT 1");
        $stmt->execute([$found_item_id]);
        if ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $pdo->prepare("SELECT name FROM admins WHERE id = ?");
            $stmt->execute([$log['actor_id']]);
            if ($admin = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $response['encoded_by'] = $admin['name'];
            }
        }
    }

    if ($lost_report_id) {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$lost_report_id]);
        $response['lost_report'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode(['ok' => true, 'data' => $response]);

} catch (PDOException $e) {
    error_log("get_claim_details.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
    exit;
}