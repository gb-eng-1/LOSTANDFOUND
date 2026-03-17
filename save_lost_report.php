<?php
/**
 * Save lost item report to database.
 * Called from student portals and FoundAdmin (Encode Report).
 * POST body: JSON with student_email, category/item_type, full_name, contact_number, department, id, item, item_description, color, brand, date_lost, imageDataUrl
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing data']);
    exit;
}

require __DIR__ . '/config/database.php';

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

// Generate REF-[10 digits] id for lost reports (e.g. REF-0000000001)
$refId = null;
try {
    $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)) AS max_num FROM items WHERE id LIKE 'REF-%'");
    $row = $stmtMax ? $stmtMax->fetch(PDO::FETCH_ASSOC) : null;
    $nextNum = $row && $row['max_num'] !== null ? (int) $row['max_num'] + 1 : 1;
    $refId = 'REF-' . str_pad($nextNum, 10, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $refId = 'REF-' . str_pad(1000 + (int) mt_rand(0, 9999), 10, '0', STR_PAD_LEFT);
}

$itemDescription = trim($data['item_description'] ?? '');
$contact = trim($data['contact_number'] ?? '');
$dept = trim($data['department'] ?? '');
$studentNumber = trim($data['id'] ?? '');
$itemName = trim($data['item'] ?? '');
$prepend = [];
if ($studentNumber) $prepend[] = 'Student Number: ' . $studentNumber;
if ($itemName) $prepend[] = 'Item Type: ' . $itemName;
if (!empty($prepend)) $itemDescription = implode("\n", $prepend) . "\n" . $itemDescription;
if ($contact || $dept) {
    $itemDescription .= ($itemDescription ? "\n" : '') . 'Contact: ' . $contact . ($dept ? "\nDepartment: " . $dept : '');
}

$studentEmail = trim($data['student_email'] ?? '');
// If a student session is active, always use the session email (most reliable)
if (!empty($_SESSION['student_email'])) {
    $studentEmail = trim($_SESSION['student_email']);
}
$userId = trim($data['full_name'] ?? '');
// Link report to student: prefer student_email so "My Reports" can find it
if ($studentEmail !== '') {
    $userId = $studentEmail;
} elseif ($studentNumber !== '') {
    $userId = $studentNumber . '@ub.edu.ph';
} elseif ($userId === '') {
    $userId = null;
}
$itemType = trim($data['category'] ?? $data['item_type'] ?? $data['item'] ?? '');
$color = trim($data['color'] ?? '');
$brand = trim($data['brand'] ?? '');
$foundAt = trim($data['found_at'] ?? '');
$storageLocation = trim($data['storage_location'] ?? '');
$dateLost = !empty($data['date_lost']) ? $data['date_lost'] : null;
$imageData = $data['imageDataUrl'] ?? null;

$sql = 'INSERT INTO items (id, user_id, item_type, color, brand, found_at, found_by, date_encoded, date_lost, item_description, storage_location, image_data, status) 
        VALUES (:id, :user_id, :item_type, :color, :brand, :found_at, :found_by, :date_encoded, :date_lost, :item_description, :storage_location, :image_data, :status)';

$stmt = $pdo->prepare($sql);
try {
    $stmt->execute([
        ':id'               => $refId,
        ':user_id'          => $userId ?: null,
        ':item_type'        => $itemType ?: null,
        ':color'            => $color ?: null,
        ':brand'            => $brand ?: null,
        ':found_at'         => $foundAt ?: null,
        ':found_by'         => null,
        ':date_encoded'     => date('Y-m-d'),
        ':date_lost'        => $dateLost,
        ':item_description' => $itemDescription ?: null,
        ':storage_location' => $storageLocation ?: null,
        ':image_data'       => $imageData,
        ':status'           => 'Unclaimed Items',
    ]);
    echo json_encode(['ok' => true, 'id' => $refId]);
    notifyAdmin($pdo, 'lost_report_created',
        'New Lost Report Submitted',
        'A new lost item report (' . $refId . ') has been submitted' . ($itemType ? ' for a ' . $itemType : '') . '.',
        $refId
    );
} catch (PDOException $e) {
    http_response_code(500);
    $msg = 'Could not save report.';
    if (defined('DEBUG') && DEBUG) {
        $msg .= ' ' . $e->getMessage();
    }
    echo json_encode(['ok' => false, 'error' => $msg]);
}
