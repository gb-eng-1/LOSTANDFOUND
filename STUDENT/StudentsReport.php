<?php
/**
 * Students Report - Browse Items / My Reports
 * UB Lost and Found System - Student POV
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

// Debug session
if (empty($_SESSION['student_id']) || empty($_SESSION['student_email'])) {
    error_log("StudentsReport.php: Session missing student_id or student_email");
    header('Location: login.php');
    exit;
}

$studentId = (int) $_SESSION['student_id'];
$studentEmail = $_SESSION['student_email'] ?? '';
$studentName = $_SESSION['student_name'] ?? '';

error_log("StudentsReport.php: studentId=$studentId, studentEmail=$studentEmail");

// Handle success/error messages
$message = '';
$messageType = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'cancelled':
            $message = 'Report cancelled successfully.';
            $messageType = 'success';
            break;
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_found':
            $message = 'Report not found or you do not have permission to cancel it.';
            $messageType = 'error';
            break;
        case 'cannot_cancel':
            $message = 'This report cannot be cancelled. Only reports that are not yet claimed or disposed can be cancelled.';
            $messageType = 'error';
            break;
        case 'database':
            $message = 'A database error occurred. Please try again.';
            $messageType = 'error';
            break;
        default:
            $message = 'An error occurred. Please try again.';
            $messageType = 'error';
            break;
    }
}

$studentNumber = null;
try {
    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['student_id'])) {
        $studentNumber = trim($row['student_id']);
    }
} catch (PDOException $e) {
    // Log the error but don't crash the page
    error_log("Database error in StudentsReport.php: " . $e->getMessage());
    // Continue with empty student number
}

$userIds = [$studentEmail];
if ($studentNumber) {
    $userIds[] = $studentNumber . '@ub.edu.ph';
}
$placeholders = implode(',', array_fill(0, count($userIds), '?'));

$filter = $_GET['filter'] ?? 'all'; // all | matched

$hasMatchedCol = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM items LIKE 'matched_barcode_id'");
    $hasMatchedCol = $cols && $cols->rowCount() > 0;
} catch (PDOException $e) {}

$myReports = [];
try {
    if ($hasMatchedCol) { // Query for when matched_barcode_id column exists
        $sql = "SELECT r.id, r.item_type, r.date_lost, r.item_description, r.matched_barcode_id, r.status, r.created_at, f.status AS found_status, NULL AS claim_status
                FROM items r
                LEFT JOIN items f ON r.matched_barcode_id = f.id
                WHERE r.id LIKE 'REF-%' AND r.status != 'Cancelled' AND (r.user_id IN ($placeholders) OR LOWER(TRIM(r.user_id)) = LOWER(?))
                ORDER BY r.created_at DESC";
    } else {
        $sql = "SELECT r.id, r.item_type, r.date_lost, r.item_description, NULL AS matched_barcode_id, r.status, r.created_at, NULL AS found_status, NULL AS claim_status
                FROM items r
                WHERE r.id LIKE 'REF-%' AND r.status != 'Cancelled' AND (r.user_id IN ($placeholders) OR LOWER(TRIM(r.user_id)) = LOWER(?))
                ORDER BY created_at DESC";
    }
    $params = array_merge($userIds, [$studentEmail]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if ($filter === 'matched' && $hasMatchedCol && empty($r['matched_barcode_id'] ?? null)) continue;
        $desc = $r['item_description'] ?? '';
        $studentNum = $contact = $dept = '';
        if (preg_match('/Student Number:\s*(.+?)(?:\n|$)/m', $desc, $m)) $studentNum = trim($m[1]);
        if (preg_match('/Contact:\s*(.+?)(?:\n|$)/m', $desc, $m)) $contact = trim($m[1]);
        if (preg_match('/Department:\s*(.+?)(?:\n|$)/m', $desc, $m)) $dept = trim($m[1]);
        $myReports[] = [
            'id'             => $r['id'],
            'ticket_id'      => $r['id'],
            'category'       => $r['item_type'] ?? 'Miscellaneous',
            'department'     => $dept ?: '-',
            'id_num'         => $studentNum ?: '-',
            'contact_number' => $contact ?: '-',
            'date_lost'      => $r['date_lost'] ? date('Y-m-d', strtotime($r['date_lost'])) : '-',
            'matched'        => $hasMatchedCol && !empty($r['matched_barcode_id'] ?? null),
            'status'         => $r['status'] ?? '',
            'found_status'   => $r['found_status'] ?? '',
            'claim_status'   => $r['claim_status'] ?? '',
            'created_at'     => $r['created_at'] ?? '',
        ];
    }
} catch (PDOException $e) {
    $myReports = [];
}

$search = trim($_GET['q'] ?? '');

// Build matched pairs JSON for comparison modal (same pattern as StudentDashboard)
$matchedPairsJson = '[]';
if ($hasMatchedCol) {
    try {
        $colsImg = '';
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM items LIKE 'image_data'");
            if ($chk && $chk->rowCount() > 0) $colsImg = ', ref.image_data AS ref_image, f.image_data AS found_image';
        } catch (PDOException $e) {}

        $stmt = $pdo->prepare("
            SELECT
                ref.id AS ref_id, ref.item_type AS ref_item_type, ref.brand AS ref_brand,
                ref.color AS ref_color, ref.date_lost AS ref_date_lost,
                ref.item_description AS ref_desc
                $colsImg,
                f.id AS found_id, f.item_type AS found_item_type, f.brand AS found_brand,
                f.color AS found_color, f.date_encoded AS found_date,
                f.item_description AS found_desc, f.found_at AS found_at
            FROM items ref
            JOIN items f ON f.id = ref.matched_barcode_id
            WHERE ref.id LIKE 'REF-%'
              AND ref.matched_barcode_id IS NOT NULL
              AND (ref.user_id IN ($placeholders) OR LOWER(TRIM(ref.user_id)) = LOWER(?))
            ORDER BY f.date_encoded DESC
        ");
        $params = array_merge($userIds, [$studentEmail]);
        $stmt->execute($params);
        $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $matchedPairsJson = json_encode($pairs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    } catch (PDOException $e) {
        $matchedPairsJson = '[]';
    }
}

// Matched Reports tab: fetch actual found items linked to this student's REF- reports
$matchedFoundItems = [];
if ($filter === 'matched' && $hasMatchedCol) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                f.id            AS barcode_id,
                f.item_type     AS category,
                f.found_at,
                f.date_encoded  AS date_found,
                f.storage_location,
                DATE_FORMAT(DATE_ADD(f.date_encoded, INTERVAL 2 YEAR), '%Y-%m-%d') AS retention_end,
                ref.id          AS ref_id,
                ref.status      AS ref_status,
                f.status        AS found_status,
                NULL            AS claim_status
            FROM items ref
            JOIN items f ON f.id = ref.matched_barcode_id
            WHERE ref.id LIKE 'REF-%'
              AND ref.matched_barcode_id IS NOT NULL
              AND (ref.user_id IN ($placeholders) OR LOWER(TRIM(ref.user_id)) = LOWER(?))
            ORDER BY f.date_encoded DESC
        ");
        $params = array_merge($userIds, [$studentEmail]);
        $stmt->execute($params);
        $matchedFoundItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $matchedFoundItems = [];
    }
}

// Prepare data for JS search dropdown
$jsSearchData = ($filter === 'matched') ? $matchedFoundItems : $myReports;

// Categories for the inline Report Lost modal dropdown
$rlmCategories = [
    'Electronics & Gadgets',
    'Document & Identification',
    'Personal Belongings',
    'Apparel & Accessories',
    'Miscellaneous',
    'ID & Nameplate',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Students Report - UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <!-- FA JS renderer: injects inline SVGs, bypasses CORS font-face issues -->
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/photo-picker.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="StudentsReport.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ItemDetailsModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <script>
    var reportSearchData = <?php echo json_encode(array_values($jsSearchData)); ?>;
    var matchedPairsData = <?php echo $matchedPairsJson; ?>;
    var myReportsData = <?php echo json_encode($myReports); ?>;
  </script>
  <style>
    /* ── Nav active/inactive colors ── */
    .nav-item.active .nav-item-icon,
    .nav-item.active .nav-item-icon i,
    .nav-item.active .nav-item-label { color: #ffffff !important; }
    .nav-menu .nav-item:not(.active) .nav-item-icon,
    .nav-menu .nav-item:not(.active) .nav-item-icon i,
    .nav-menu .nav-item:not(.active) .nav-item-label { color: #8b0000 !important; }


    /* ── Sidebar: clear min-height in mobile/column layout ── */
    @media (max-width: 900px) {
      .sidebar { min-height: 0 !important; height: auto !important; }
    }

    /* ── Universal search dropdown ── */
    .search-form { position: relative; }
    .search-dropdown {
      position: absolute; top: 100%; left: 0; right: 0;
      background: #fff; border: 1px solid #e5e7eb;
      border-radius: 0 0 10px 10px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.13); z-index: 300;
      max-height: 380px; overflow-y: auto; display: none; margin-top: 1px;
    }
    .search-dropdown-item {
      padding: 10px 14px; border-bottom: 1px solid #f3f4f6; cursor: pointer;
      display: flex; gap: 11px; align-items: flex-start; transition: background 0.12s;
    }
    .search-dropdown-item:last-child { border-bottom: none; }
    .search-dropdown-item:hover { background-color: #fafafa; }
    .sd-icon {
      width: 34px; height: 34px; background: #f3f4f6; border-radius: 7px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      color: #8b0000; font-size: 14px; margin-top: 1px;
    }
    .sd-info { flex: 1; min-width: 0; }
    .sd-barcode { font-size: 11px; color: #9ca3af; font-weight: 500; margin-bottom: 1px; }
    .sd-title   { font-size: 13px; font-weight: 700; color: #111; margin-bottom: 2px;
                  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sd-desc    { font-size: 12px; color: #6b7280; font-style: italic; margin-bottom: 3px;
                  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sd-meta    { display: flex; gap: 10px; flex-wrap: wrap; }
    .sd-meta-item { font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: 3px; }
    .sd-meta-item i { color: #9ca3af; font-size: 10px; }
    .sd-no-results { padding: 14px 16px; font-size: 13px; color: #9ca3af; font-style: italic; }

    .main .topbar { flex-shrink: 0; z-index: 10; }

    /* ── Search bar right-aligned ── */
    .topbar-search-left { justify-content: flex-start !important; padding-left: 0 !important; }
    .topbar-search-left .search-form { max-width: 420px; }

    /* ── Topbar right: white icons + spacing ── */
    .topbar-right { gap: 32px !important; }
    .topbar-right .admin-link,
    .topbar-right .admin-link i,
    .topbar-right .admin-name { color: #ffffff !important; }
    /* Bell button and profile button: white text/icon */
    .topbar-right .topbar-icon-btn { color: #ffffff !important; }
    .topbar-right .topbar-icon-btn i { color: #ffffff !important; }
    /* FA-JS replaces <i> with inline <svg>. The paths inside use fill="currentColor".
       We must target BOTH the svg AND its path children to guarantee white fill.
       CSS fill: white !important overrides SVG presentation attributes. */
    .topbar-right .topbar-icon-btn svg,
    .topbar-right .topbar-icon-btn svg path,
    .topbar-right .topbar-icon-btn .svg-inline--fa,
    #notifTrigger svg,
    #notifTrigger svg path,
    #notifTrigger .svg-inline--fa { fill: #ffffff !important; color: #ffffff !important; }

    /* ── Page layout ── */
    .students-report-wrap {
      padding: 28px 32px 40px;
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
    }
    .page-title-browse {
      font-size: 26px;
      font-weight: 700;
      color: #111;
      margin-bottom: 18px;
    }

    /* ── Tabs (pill style, matches admin pages) ── */
    .report-tabs-row {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 22px;
    }
    .report-tabs-pill {
      display: flex;
      align-items: center;
      background: #f3f4f6;
      border-radius: 8px;
      padding: 3px;
      gap: 0;
      flex-shrink: 0;
    }
    .report-tab {
      padding: 6px 16px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      text-decoration: none;
      color: #6b7280;
      background: transparent;
      border: none;
      white-space: nowrap;
      transition: background 0.15s, color 0.15s;
      cursor: pointer;
    }
    .report-tab:hover { background: #e5e7eb; color: #374151; text-decoration: none; }
    .report-tab.active {
      background: #8b0000;
      color: #fff;
      font-weight: 600;
      box-shadow: 0 1px 4px rgba(139,0,0,0.25);
    }
    .report-tab-action {
      display: inline-flex;
      align-items: center;
      padding: 6px 16px;
      background: #8b0000;
      color: #fff;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      border: none;
      transition: background 0.15s;
    }
    .report-tab-action:hover { background: #6d0000; color: #fff; text-decoration: none; }

    /* ── Section heading & help text ── */
    .section-title-my {
      font-size: 18px;
      font-weight: 700;
      color: #111;
      margin-bottom: 6px;
    }
    .cancel-help-text {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 16px;
      line-height: 1.5;
    }

    /* ── Table wrapper ── */
    .table-wrapper {
      overflow-x: auto;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    /* ── Data table ── */
    .reports-data-table {
      width: 100%;
      min-width: 700px;
      border-collapse: collapse;
      font-size: 13px;
      font-family: inherit;
      background: #fff;
    }
    .reports-data-table thead tr {
      background: #f3f4f6;
    }
    .reports-data-table th {
      padding: 12px 14px;
      text-align: left;
      font-weight: 600;
      color: #374151;
      border-bottom: 1px solid #d1d5db;
      white-space: nowrap;
    }
    .reports-data-table th:last-child { text-align: center; }
    .reports-data-table td {
      padding: 12px 14px;
      color: #374151;
      border-bottom: 1px solid #e5e7eb;
      white-space: nowrap;
    }
    .reports-data-table td.action-cell { text-align: center; white-space: nowrap; }
    .reports-data-table tbody tr:last-child td { border-bottom: none; }
    .reports-data-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .reports-data-table tbody tr:hover td { background: #f3f4f6 !important; }
    .table-empty {
      text-align: center;
      color: #9ca3af;
      padding: 32px;
      font-style: italic;
    }

    /* ── Status badges ── */
    .badge-matched {
      display: inline-block;
      padding: 3px 10px;
      background: #dcfce7;
      color: #15803d;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    .badge-pending {
      display: inline-block;
      padding: 3px 10px;
      background: #fef9c3;
      color: #a16207;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    /* ── Action buttons ── */
    .btn-view {
      display: inline-block;
      padding: 6px 18px;
      background: #8b0000;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      text-decoration: none;
      transition: background 0.15s;
    }
    .btn-view:hover { background: #6d0000; color: #fff; }

    .btn-cancel {
      display: inline-block;
      padding: 6px 14px;
      background: #fff;
      color: #dc2626;
      border: 1px solid #dc2626;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      margin-left: 6px;
      transition: background 0.15s;
    }
    .btn-cancel:hover { background: #fef2f2; }
    .btn-cancel:disabled, .btn-cancel[disabled], .btn-cancel--cooldown {
      opacity: 0.45; cursor: not-allowed;
      background: #f9fafb; color: #9ca3af; border-color: #d1d5db;
    }

    .btn-claim {
      display: inline-block;
      padding: 6px 14px;
      background: #15803d;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      margin-left: 6px;
      transition: background 0.15s;
    }
    .btn-claim:hover { background: #166534; }

    /* ── Alert messages ── */
    .alert {
      margin-bottom: 20px;
      padding: 13px 18px;
      border-radius: 6px;
      font-size: 14px;
    }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    /* ════════════════════════════════════════════════
       Inline Report Lost Modal — three screens
       ════════════════════════════════════════════════ */
    .rlm-overlay {
      display: none; position: fixed; inset: 0; z-index: 9990;
      align-items: center; justify-content: center;
      background: rgba(0,0,0,0.55); padding: 12px;
    }
    .rlm-overlay.rlm-open { display: flex; }
    .rlm-dialog {
      background: #fff; border-radius: 14px; width: 100%; max-width: 560px;
      max-height: 92vh; display: flex; flex-direction: column;
      box-shadow: 0 24px 64px rgba(0,0,0,0.32); overflow: hidden;
    }
    .rlm-header {
      background: #8b0000; padding: 16px 20px;
      display: flex; align-items: center; justify-content: space-between;
      flex-shrink: 0;
    }
    .rlm-header h2 { margin: 0; color: #fff; font-size: 17px; font-weight: 700; font-family: inherit; }
    .rlm-close-btn {
      background: none; border: 2px solid rgba(255,255,255,0.6);
      color: #fff; font-size: 13px; cursor: pointer;
      width: 28px; height: 28px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      padding: 0; line-height: 1;
    }
    .rlm-close-btn:hover { border-color: #fff; background: rgba(255,255,255,0.1); }
    .rlm-body { padding: 24px 24px 8px; overflow-y: auto; flex: 1; }
    .rlm-footer {
      padding: 14px 24px; display: flex; justify-content: flex-end; gap: 12px;
      border-top: 1px solid #e5e7eb; flex-shrink: 0;
    }

    /* Form fields */
    .rlm-row { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }
    .rlm-label {
      width: 140px; flex-shrink: 0; font-size: 14px; font-weight: 500;
      color: #374151; padding-top: 9px;
    }
    .rlm-field { flex: 1; min-width: 0; }
    .rlm-input, .rlm-select, .rlm-textarea {
      width: 100%; padding: 8px 12px; border: 1px solid #d1d5db;
      border-radius: 7px; font-size: 14px; font-family: inherit;
      color: #111; background: #fff; box-sizing: border-box;
      transition: border-color 0.15s;
    }
    .rlm-input:focus, .rlm-select:focus, .rlm-textarea:focus {
      outline: none; border-color: #8b0000; box-shadow: 0 0 0 3px rgba(139,0,0,0.08);
    }
    .rlm-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
    .rlm-textarea { resize: vertical; min-height: 72px; }
    .rlm-required { color: #dc2626; margin-left: 4px; font-size: 13px; }

    /* Upload field trigger */
    .rlm-upload-trigger {
      width: 100%; padding: 8px 12px; border: 1px solid #d1d5db;
      border-radius: 7px; font-size: 14px; font-family: inherit;
      color: #6b7280; background: #fff; box-sizing: border-box;
      cursor: pointer; text-align: left;
      display: flex; align-items: center; justify-content: space-between;
      transition: border-color 0.15s;
    }
    .rlm-upload-trigger:hover { border-color: #9ca3af; }
    .rlm-upload-trigger.has-file { color: #111; }

    /* Footer buttons */
    .rlm-btn {
      padding: 9px 28px; border-radius: 7px; font-size: 14px;
      font-weight: 600; cursor: pointer; font-family: inherit; border: none;
    }
    .rlm-btn-cancel { background: #fff; color: #374151; border: 1px solid #9ca3af; }
    .rlm-btn-cancel:hover { background: #f9fafb; }
    .rlm-btn-primary { background: #8b0000; color: #fff; }
    .rlm-btn-primary:hover { background: #6d0000; }
    .rlm-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

    /* ── Upload sub-modal ───────────────────────────────────────── */
    .rlm-upload-dialog { max-width: 500px; }
    .rlm-drop-zone {
      border: 2px dashed #d1d5db; border-radius: 10px; padding: 40px 20px;
      text-align: center; background: #fafafa; cursor: pointer;
      transition: border-color 0.2s, background 0.2s; margin-bottom: 16px;
    }
    .rlm-drop-zone.drag-over { border-color: #8b0000; background: #fff5f5; }
    .rlm-drop-icon {
      font-size: 40px; color: #374151; display: block; margin-bottom: 10px;
    }
    .rlm-drop-text { font-size: 14px; color: #9ca3af; }
    .rlm-file-list { display: flex; flex-direction: column; gap: 8px; }
    .rlm-file-item {
      display: flex; align-items: center; gap: 12px;
      background: #f3f4f6; border-radius: 8px; padding: 10px 14px;
    }
    .rlm-file-thumb {
      width: 40px; height: 40px; border-radius: 6px; background: #e5e7eb;
      flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center;
    }
    .rlm-file-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .rlm-file-info { flex: 1; min-width: 0; }
    .rlm-file-name { font-size: 13px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rlm-file-size { font-size: 12px; color: #6b7280; }
    .rlm-file-remove {
      background: none; border: none; color: #9ca3af; cursor: pointer;
      font-size: 18px; padding: 0; display: flex; align-items: center;
    }
    .rlm-file-remove:hover { color: #dc2626; }

    /* ── Success modal ─────────────────────────────────────────── */
    .rlm-success-body { padding: 36px 24px 24px; text-align: center; }
    .rlm-success-icon {
      width: 72px; height: 72px; background: #22c55e; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
    }
    .rlm-success-icon i { font-size: 32px; color: #fff; }
    .rlm-success-title { font-size: 22px; font-weight: 700; color: #111; margin: 0 0 10px; font-family: inherit; }
    .rlm-success-msg { font-size: 14px; color: #555; margin: 0 0 10px; line-height: 1.6; }
    .rlm-success-ticket { font-size: 15px; font-weight: 700; color: #111; margin: 0 0 24px; }
    .rlm-success-footer { display: flex; justify-content: center; }
  </style>
</head>
<body data-student-email="<?= htmlspecialchars($studentEmail) ?>">
<div class="layout">

  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"></div>
      <div class="sidebar-title">
        <span class="sidebar-title-line1">University of</span>
        <span class="sidebar-title-line2">Batangas</span>
      </div>
    </div>
    <ul class="nav-menu">
      <li><a class="nav-item" href="StudentDashboard.php"><div class="nav-item-icon"><i class="fa-solid fa-house"></i></div><div class="nav-item-label">Dashboard</div></a></li>
      <li><a class="nav-item active" href="StudentsReport.php"><div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div><div class="nav-item-label">My Reports</div></a></li>
      <li><a class="nav-item" href="ClaimHistory.php"><div class="nav-item-icon"><i class="fa-regular fa-calendar"></i></div><div class="nav-item-label">Claim History</div></a></li>
      <li><a class="nav-item" href="HelpSupport.php"><div class="nav-item-icon"><i class="fa-solid fa-circle-question"></i></div><div class="nav-item-label">Help and Support</div></a></li>
    </ul>
  </aside>

  <main class="main">
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-left">
        <form class="search-form" action="StudentsReport.php" method="get" style="position: relative;">
          <?php if ($filter): ?><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"><?php endif; ?>
          <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search items by name or barcode…" autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
          <div id="searchDropdown" class="search-dropdown"></div>
          <button id="adminSearchClear" class="search-clear" type="button" title="Clear" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
          <button class="search-submit" type="submit" title="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>
      <div class="topbar-right">
        <?php require_once __DIR__ . '/includes/notifications_dropdown.php'; ?>
        <div class="admin-dropdown" id="adminDropdown">
          <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger" aria-expanded="false" aria-haspopup="true" aria-label="Student menu">
            <i class="fa-regular fa-user"></i>
            <span class="admin-name">Student</span>
            <i class="fa-solid fa-chevron-down" style="font-size: 11px;"></i>
          </button>
          <div class="admin-dropdown-menu" role="menu">
            <a href="logout.php" role="menuitem" class="admin-dropdown-item"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
          </div>
        </div>
      </div>
    </div>

    <div class="main-content-wrap students-report-wrap">
      <h1 class="page-title-browse">Browse Items</h1>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <div class="report-tabs-row">
        <div class="report-tabs-pill">
          <a href="StudentsReport.php?filter=all" class="report-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All Reports</a>
          <a href="StudentsReport.php?filter=matched" class="report-tab <?php echo $filter === 'matched' ? 'active' : ''; ?>">Matched Reports</a>
        </div>
        <a href="#" class="report-tab-action" data-open-report-lost>Report Lost Item</a>
      </div>

      <h2 class="section-title-my"><?php echo $filter === 'matched' ? 'Matched Reports' : 'My Reports'; ?></h2>
      <?php if ($filter !== 'matched'): ?>
      <p class="cancel-help-text">You can cancel reports that are not yet claimed, disposed, or already cancelled. Reports that are no longer eligible will show a greyed-out Cancel button.</p>
      <?php endif; ?>

      <div class="table-wrapper">
        <?php if ($filter === 'matched'): ?>
        <!-- ── Matched Reports: show found item details ── -->
        <table class="reports-data-table">
          <thead>
            <tr>
              <th>Barcode ID</th>
              <th>Category</th>
              <th>Found At</th>
              <th>Date Found</th>
              <th>Retention End</th>
              <th>Storage Location</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($matchedFoundItems)): ?>
              <tr><td colspan="7" class="table-empty">No matched reports yet.</td></tr>
            <?php else: ?>
              <?php foreach (array_values($matchedFoundItems) as $i => $f): ?>
                <tr class="<?php echo $i % 2 === 0 ? 'row-even' : 'row-odd'; ?>">
                  <td><?php echo htmlspecialchars($f['barcode_id']); ?></td>
                  <td><?php echo htmlspecialchars($f['category'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($f['found_at'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($f['date_found'] ? date('Y-m-d', strtotime($f['date_found'])) : '-'); ?></td>
                  <td><?php echo htmlspecialchars($f['retention_end'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($f['storage_location'] ?? '-'); ?></td>
                  <td class="action-cell">
                    <button type="button" class="btn-view"
                        data-view-matched-pair="<?php echo htmlspecialchars($f['ref_id']); ?>">View</button>
                    <?php
                      $refStatus = $f['ref_status'] ?? '';
                      $foundStatus = $f['found_status'] ?? '';
                      $claimStatus = $f['claim_status'] ?? '';
                      // An item is not claimable if it has already been successfully claimed/resolved.
                      // A pending claim ('Unresolved Claimants') does not hide the button,
                      // though the backend will prevent a duplicate submission.
                      $isItemFinalized = in_array($refStatus, ['Claimed', 'Resolved'], true) || in_array($foundStatus, ['Claimed', 'Resolved'], true);
                      $isClaimFinalized = in_array($claimStatus, ['Approved', 'Resolved'], true);
                      $isNotClaimable = $isItemFinalized || $isClaimFinalized;
                    ?>
                    <?php if (!$isNotClaimable): ?>
                      <button type="button" class="btn-claim"
                          data-claim-report="<?php echo htmlspecialchars($f['ref_id']); ?>">Claim</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <?php else: ?>
        <!-- ── All Reports: show REF- report details ── -->
        <table class="reports-data-table">
          <thead>
            <tr>
              <th>Ticket ID</th>
              <th>Category</th>
              <th>Department</th>
              <th>ID</th>
              <th>Contact Number</th>
              <th>Date Lost</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($myReports)): ?>
              <tr><td colspan="7" class="table-empty">No reports yet.</td></tr>
            <?php else: ?>
              <?php
              $nonCancellableStatuses = ['Claimed', 'Disposed', 'Cancelled', 'Resolved'];
              $finalClaimStatuses = ['Approved', 'Resolved'];
              foreach (array_values($myReports) as $i => $r):
                  $refStatus = $r['status'] ?? '';
                  $foundStatus = $r['found_status'] ?? '';
                  $claimStatus = $r['claim_status'] ?? '';
                  // A report cannot be cancelled if its own status is final, if its matched item has been
                  // finalized (Claimed/Resolved), or if a formal claim record has been finalized (Approved/Resolved).
                  $isRefNonCancellable = in_array($refStatus, $nonCancellableStatuses, true);
                  $isFoundItemClaimed = $r['matched'] && in_array($foundStatus, ['Claimed', 'Resolved'], true);
                  $isClaimFinalized = in_array($claimStatus, $finalClaimStatuses, true);

                  $canCancel = !$isRefNonCancellable && !$isFoundItemClaimed && !$isClaimFinalized;
                  $createdTs     = $canCancel ? (int)strtotime($r['created_at'] ?? '') : 0;
                  $isInCooldown  = $createdTs > 0 && ($createdTs + 86400) > time();
                  $cooldownUntil = $isInCooldown ? date('M j, Y \a\t g:i A', $createdTs + 86400) : '';
              ?>
                <tr class="<?php echo $i % 2 === 0 ? 'row-even' : 'row-odd'; ?>">
                  <td><?php echo htmlspecialchars($r['ticket_id']); ?></td>
                  <td><?php echo htmlspecialchars($r['category']); ?></td>
                  <td><?php echo htmlspecialchars($r['department']); ?></td>
                  <td><?php echo htmlspecialchars($r['id_num']); ?></td>
                  <td><?php echo htmlspecialchars($r['contact_number']); ?></td>
                  <td><?php echo htmlspecialchars($r['date_lost']); ?></td>
                  <td class="action-cell">
                    <button type="button" class="btn-view"
                        data-view-report="<?php echo htmlspecialchars($r['id']); ?>">View</button>
                    <button type="button" class="btn-cancel<?php if ($isInCooldown): ?> btn-cancel--cooldown<?php endif; ?>"
                        data-cancel-report="<?php echo htmlspecialchars($r['id']); ?>"
                        <?php if (!$canCancel && !$isInCooldown): ?>disabled<?php endif; ?>
                        <?php if ($isInCooldown): ?>data-cooldown-until="<?php echo htmlspecialchars($cooldownUntil); ?>"<?php endif; ?>
                        <?php if (!$canCancel && !$isInCooldown): ?>title="This report cannot be cancelled (already claimed, disposed, or resolved)"<?php endif; ?>>
                      Cancel
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="report-success-overlay" aria-hidden="true" style="display: none;">
  <div class="report-success-dialog">
    <div class="report-success-icon" style="background-color: #ff9800;"><i class="fa-solid fa-question"></i></div>
    <h3 class="report-success-title">Confirm Action</h3>
    <p class="report-success-message" id="confirmMessage">Are you sure you want to cancel this report?</p>
    <div class="report-success-footer">
      <button type="button" class="report-lost-btn report-lost-btn-cancel" id="confirmCancel">Cancel</button>
      <button type="button" class="report-lost-btn report-lost-btn-submit" id="confirmOk">Confirm</button>
    </div>
  </div>
</div>

<script>
(function () {
    var dropdown = document.getElementById('adminDropdown');
    var trigger = dropdown && dropdown.querySelector('.admin-dropdown-trigger');
    if (dropdown && trigger) {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('open');
            trigger.setAttribute('aria-expanded', dropdown.classList.contains('open'));
        });
        document.addEventListener('click', function () {
            dropdown.classList.remove('open');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    }
})();
(function () {
  /* ── Universal search bar ──────────────────────────────────────────────── */
  var input    = document.getElementById('adminSearchInput');
  var clearBtn = document.getElementById('adminSearchClear');
  var dropdown = document.getElementById('searchDropdown');
  var form     = input ? input.closest('form') : null;
  if (!input || !dropdown) return;

  var timer = null;
  var lastQ = '';

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
    });
  }

  function render(items, q) {
    if (!items || items.length === 0) {
      dropdown.innerHTML = '<div class="sd-no-results">No items found for "' + esc(q) + '"</div>';
      dropdown.style.display = 'block';
      return;
    }
    dropdown.innerHTML = items.map(function(item) {
      var name = item.item_type || 'Item';
      if (item.brand) name += ' \u2013 ' + item.brand;
      if (item.color) name += ' (' + item.color + ')';
      var meta = '';
      if (item.found_at) meta += '<span class="sd-meta-item"><i class="fa-solid fa-location-dot"></i>' + esc(item.found_at) + '</span>';
      if (item.date)     meta += '<span class="sd-meta-item"><i class="fa-regular fa-calendar"></i>' + esc(item.date) + '</span>';
      return '<div class="search-dropdown-item" data-id="' + esc(item.id) + '">' +
        '<div class="sd-icon"><i class="fa-regular fa-file-lines"></i></div>' +
        '<div class="sd-info">' +
          '<div class="sd-barcode">' + esc(item.id) + '</div>' +
          '<div class="sd-title">' + esc(name) + '</div>' +
          (item.description ? '<div class="sd-desc">' + esc(item.description) + '</div>' : '') +
          (meta ? '<div class="sd-meta">' + meta + '</div>' : '') +
        '</div></div>';
    }).join('');
    dropdown.style.display = 'block';
  }

  function doSearch(q) {
    if (q === lastQ) return;
    lastQ = q;
    fetch('search_items.php?q=' + encodeURIComponent(q), { credentials: 'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){ render(data, q); })
      .catch(function(){ dropdown.style.display = 'none'; });
  }

  input.addEventListener('input', function() {
    var v = this.value.trim();
    if (clearBtn) clearBtn.style.display = v ? 'flex' : 'none';
    clearTimeout(timer);
    if (v.length < 2) { dropdown.style.display = 'none'; lastQ = ''; return; }
    timer = setTimeout(function(){ doSearch(v); }, 220);
  });

  dropdown.addEventListener('click', function(e) {
    var row = e.target.closest('.search-dropdown-item');
    if (!row) return;
    var id = row.getAttribute('data-id');
    if (!id) return;
    input.value = id;
    dropdown.style.display = 'none';
    if (clearBtn) clearBtn.style.display = 'flex';
    if (typeof showItemDetailsModal === 'function') showItemDetailsModal(id, { showClaimButton: false });
  });

  document.addEventListener('click', function(e) {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      input.value = ''; dropdown.style.display = 'none'; lastQ = '';
      clearBtn.style.display = 'none'; input.focus();
    });
    clearBtn.style.display = input.value ? 'flex' : 'none';
  }

  if (form) form.addEventListener('submit', function(e) {
    e.preventDefault();
    var first = dropdown.querySelector('.search-dropdown-item');
    if (first) first.click();
  });
})();
(function () {
  var trigger = document.getElementById('notifTrigger');
  var panel   = document.getElementById('notifPanel');
  if (!trigger || !panel) return;
  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = panel.classList.toggle('open');
    trigger.setAttribute('aria-expanded', open);
    panel.setAttribute('aria-hidden', !open);
  });
  document.addEventListener('click', function (e) {
    if (!panel.contains(e.target) && e.target !== trigger) {
      panel.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      panel.setAttribute('aria-hidden', 'true');
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      panel.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      panel.setAttribute('aria-hidden', 'true');
    }
  });
})();
</script>
<?php
// ── Inline Report Lost Modal — replaces external report_lost_modal.php ──────
// Three screens: (1) form  (2) upload sub-modal  (3) success
?>

<!-- ══ SCREEN 1 — Report Lost Form ══════════════════════════════════════════ -->
<div id="rlmFormOverlay" class="rlm-overlay" role="dialog" aria-modal="true" aria-label="Report Lost Item">
  <div class="rlm-dialog">
    <div class="rlm-header">
      <h2>Item Lost Report</h2>
      <button type="button" class="rlm-close-btn" id="rlmFormClose" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="rlm-body" id="rlmFormBody">
      <!-- Category -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmCategory">Category:</label>
        <div class="rlm-field">
          <select id="rlmCategory" class="rlm-select">
            <option value=""></option>
            <?php foreach ($rlmCategories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <!-- Document Type (shown only when Document & Identification is selected) -->
      <div class="rlm-row" id="rlmDocTypeRow" style="display:none">
        <label class="rlm-label" for="rlmDocType">Document Type:</label>
        <div class="rlm-field">
          <select id="rlmDocType" class="rlm-select">
            <option value="">Select document type</option>
            <option>Student ID</option>
            <option>Driver's License</option>
            <option>Passport</option>
            <option>Person's With Disability (PWD) ID</option>
            <option>Voter's ID</option>
            <option>Company/Employee ID</option>
            <option>National ID</option>
            <option>Senior Citizen ID</option>
          </select>
        </div>
      </div>
      <!-- Full Name -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmFullName">Full Name:</label>
        <div class="rlm-field">
          <input type="text" id="rlmFullName" class="rlm-input"
                 value="<?= htmlspecialchars($studentName ?? '') ?>">
        </div>
      </div>
      <!-- Contact Number -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmContact">Contact Number:<span class="rlm-required">*</span></label>
        <div class="rlm-field">
          <input type="text" id="rlmContact" class="rlm-input" required>
        </div>
      </div>
      <!-- Department -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmDepartment">Department:<span class="rlm-required">*</span></label>
        <div class="rlm-field">
          <input type="text" id="rlmDepartment" class="rlm-input" required>
        </div>
      </div>
      <!-- ID -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmIdNum">ID:</label>
        <div class="rlm-field">
          <input type="text" id="rlmIdNum" class="rlm-input"
                 value="<?= htmlspecialchars($studentNumber ?? '') ?>">
        </div>
      </div>
      <!-- Item -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmItem">Item:</label>
        <div class="rlm-field">
          <input type="text" id="rlmItem" class="rlm-input">
        </div>
      </div>
      <!-- Item Description -->
      <div class="rlm-row" style="align-items:flex-start;">
        <label class="rlm-label" for="rlmDescription" style="padding-top:9px;">Item Description:<span class="rlm-required">*</span></label>
        <div class="rlm-field">
          <textarea id="rlmDescription" class="rlm-textarea" rows="3" required></textarea>
        </div>
      </div>
      <!-- Color -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmColor">Color:</label>
        <div class="rlm-field">
          <select id="rlmColor" class="rlm-select">
            <option value="">Select</option>
            <option>Red</option>
            <option>Orange</option>
            <option>Yellow</option>
            <option>Green</option>
            <option>Blue</option>
            <option>Violet</option>
            <option>Black</option>
            <option>White</option>
            <option>Brown</option>
            <option>Rainbow</option>
            <option>Multi</option>
            <option>Other</option>
          </select>
        </div>
      </div>
      <!-- Brand -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmBrand">Brand:</label>
        <div class="rlm-field">
          <input type="text" id="rlmBrand" class="rlm-input">
        </div>
      </div>
      <!-- Date Lost -->
      <div class="rlm-row">
        <label class="rlm-label" for="rlmDateLost">Date Lost:</label>
        <div class="rlm-field">
          <input type="date" id="rlmDateLost" class="rlm-input">
        </div>
      </div>
      <!-- Inline photo picker -->
      <div class="pp-photo-row">
        <label class="pp-photo-label">Photo <span class="pp-optional">(optional)</span></label>
        <div class="pp-wrap" id="rlmPhotoPicker">
          <div class="pp-idle">
            <i class="fa-regular fa-image pp-icon"></i>
            <p class="pp-hint">No photo yet</p>
            <div class="pp-btn-row">
              <button type="button" class="pp-btn pp-btn--cam" data-pp="camera"><i class="fa-solid fa-camera"></i> Camera</button>
              <button type="button" class="pp-btn pp-btn--upload" data-pp="upload"><i class="fa-solid fa-upload"></i> Upload</button>
            </div>
          </div>
          <div class="pp-preview" style="display:none">
            <img class="pp-preview-img" src="" alt="Photo preview">
            <div class="pp-preview-actions">
              <button type="button" class="pp-btn pp-btn--sm" data-pp="camera"><i class="fa-solid fa-camera"></i> Retake</button>
              <button type="button" class="pp-btn pp-btn--sm" data-pp="upload"><i class="fa-solid fa-upload"></i> Change</button>
              <button type="button" class="pp-btn pp-btn--del" data-pp="remove"><i class="fa-solid fa-xmark"></i></button>
            </div>
          </div>
          <input type="file" class="pp-file" accept="image/*" style="display:none">
        </div>
      </div>
    </div><!-- /.rlm-body -->
    <div class="rlm-footer">
      <button type="button" class="rlm-btn rlm-btn-cancel" id="rlmFormCancel">Cancel</button>
      <button type="button" class="rlm-btn rlm-btn-primary" id="rlmFormNext">Next</button>
    </div>
  </div>
</div>

<!-- ══ SCREEN 3 — Success Modal ══════════════════════════════════════════════ -->
<div id="rlmSuccessOverlay" class="rlm-overlay" role="dialog" aria-modal="true" aria-label="Report Submitted">
  <div class="rlm-dialog" style="max-width:400px;">
    <div class="rlm-header" style="background:transparent;padding:14px 14px 0;justify-content:flex-end;">
      <button type="button" class="rlm-close-btn" id="rlmSuccessClose"
              style="border-color:#9ca3af;color:#9ca3af;" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="rlm-success-body">
      <div class="rlm-success-icon"><i class="fa-solid fa-check"></i></div>
      <h3 class="rlm-success-title">Success</h3>
      <p class="rlm-success-msg">Report has been submitted successfully!</p>
      <p class="rlm-success-ticket" id="rlmSuccessTicket"></p>
      <div class="rlm-success-footer">
        <button type="button" class="rlm-btn rlm-btn-primary" id="rlmSuccessDone">Done</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/photo-picker.js?v=<?php echo time(); ?>"></script>
<script>
(function () {
  'use strict';

  // ── References ────────────────────────────────────────────────────────────
  var formOverlay    = document.getElementById('rlmFormOverlay');
  var successOverlay = document.getElementById('rlmSuccessOverlay');

  // form elements
  var fCategory    = document.getElementById('rlmCategory');
  var fDocTypeRow  = document.getElementById('rlmDocTypeRow');
  var fDocType     = document.getElementById('rlmDocType');
  var fFullName    = document.getElementById('rlmFullName');
  var fContact     = document.getElementById('rlmContact');
  var fDepartment  = document.getElementById('rlmDepartment');
  var fIdNum       = document.getElementById('rlmIdNum');
  var fItem        = document.getElementById('rlmItem');
  var fDescription = document.getElementById('rlmDescription');
  var fColor       = document.getElementById('rlmColor');
  var fBrand       = document.getElementById('rlmBrand');
  var fDateLost    = document.getElementById('rlmDateLost');

  // Document & Identification sub-dropdown logic
  function syncRlmDocType() {
    if (!fDocTypeRow) return;
    var isDoc = fCategory && fCategory.value === 'Document & Identification';
    fDocTypeRow.style.display = isDoc ? '' : 'none';
    if (!isDoc && fDocType) fDocType.value = '';
  }
  if (fCategory) fCategory.addEventListener('change', syncRlmDocType);
  if (fDocType) {
    fDocType.addEventListener('change', function () {
      if (fItem) fItem.value = this.value;
    });
  }

  // success elements
  var successTicket = document.getElementById('rlmSuccessTicket');

  // ── Photo picker ───────────────────────────────────────────────────────────
  var selectedDataUrl = null;
  var _rlmPP = PhotoPicker.init({
    el: 'rlmPhotoPicker',
    onChange: function (dataUrl) { selectedDataUrl = dataUrl || null; }
  });

  // ── Open / close helpers ─────────────────────────────────────────────────
  function openForm() {
    formOverlay.classList.add('rlm-open');
  }
  function closeForm() {
    formOverlay.classList.remove('rlm-open');
  }
  function openSuccess(ticketId) {
    successTicket.textContent = ticketId || '';
    successOverlay.classList.add('rlm-open');
  }
  function closeSuccess() {
    successOverlay.classList.remove('rlm-open');
  }
  function closeAll() {
    closeForm();
    closeSuccess();
    resetForm();
  }

  function resetForm() {
    if (fCategory)    fCategory.value    = '';
    if (fDocTypeRow)  fDocTypeRow.style.display = 'none';
    if (fDocType)     fDocType.value     = '';
    if (fContact)     fContact.value     = '';
    if (fDepartment)  fDepartment.value  = '';
    if (fItem)        fItem.value        = '';
    if (fDescription) fDescription.value = '';
    if (fColor)       fColor.value       = '';
    if (fBrand)       fBrand.value       = '';
    if (fDateLost)    fDateLost.value    = '';
    _rlmPP.clear();
  }

  // ── Form submit (Next button) ─────────────────────────────────────────────
  document.getElementById('rlmFormNext').addEventListener('click', function () {
    // Validate required fields
    var contact = fContact ? fContact.value.trim() : '';
    var dept    = fDepartment ? fDepartment.value.trim() : '';
    var desc    = fDescription ? fDescription.value.trim() : '';
    if (!contact || !dept || !desc) {
      var first = (!contact && fContact) || (!dept && fDepartment) || (!desc && fDescription);
      if (first) first.focus();
      return;
    }

    var nextBtn = document.getElementById('rlmFormNext');
    nextBtn.disabled = true;
    nextBtn.textContent = 'Submitting…';

    // Assemble item_description text block (matches ItemDetailsModal.js parser)
    var fullName   = fFullName    ? fFullName.value.trim()    : '';
    var idNum      = fIdNum       ? fIdNum.value.trim()       : '';
    var docType    = fDocType     ? fDocType.value            : '';
    var item       = fItem        ? fItem.value.trim()        : '';
    if (docType && !item) item = docType;
    var descParts  = [];
    if (fullName)  descParts.push('Full Name: ' + fullName);
    if (idNum)     descParts.push('Student Number: ' + idNum);
    if (contact)   descParts.push('Contact: ' + contact);
    if (dept)      descParts.push('Department: ' + dept);
    if (item)      descParts.push('Item Type: ' + item);
    descParts.push(desc);
    var fullDesc = descParts.join('\n');

    var payload = {
      student_email:    '<?php echo addslashes($studentEmail); ?>',
      item_type:        fCategory  ? fCategory.value  : '',
      color:            fColor     ? fColor.value.trim()     : '',
      brand:            fBrand     ? fBrand.value.trim()     : '',
      date_lost:        fDateLost  ? fDateLost.value         : '',
      item_description: fullDesc,
      imageDataUrl:     selectedDataUrl || null,
      dateEncoded:      new Date().toISOString().slice(0, 10)
    };

    fetch('../save_lost_report.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      nextBtn.disabled    = false;
      nextBtn.textContent = 'Next';
      if (data.ok || data.id) {
        closeForm();
        resetForm();
        openSuccess(data.id || data.ticket_id || '');
      } else {
        alert(data.error || data.message || 'Could not submit the report. Please try again.');
      }
    })
    .catch(function () {
      nextBtn.disabled    = false;
      nextBtn.textContent = 'Next';
      alert('Network error — please check your connection and try again.');
    });
  });

  // ── Form Cancel button ────────────────────────────────────────────────────
  document.getElementById('rlmFormCancel').addEventListener('click', closeAll);
  document.getElementById('rlmFormClose').addEventListener('click',  closeAll);

  // ── Success modal dismiss ─────────────────────────────────────────────────
  document.getElementById('rlmSuccessClose').addEventListener('click', function () {
    closeSuccess();
    window.location.reload(); // refresh to show new report in table
  });
  document.getElementById('rlmSuccessDone').addEventListener('click', function () {
    closeSuccess();
    window.location.reload();
  });

  // ── Backdrop click ────────────────────────────────────────────────────────
  formOverlay.addEventListener('click', function (e) { if (e.target === formOverlay) closeAll(); });
  // Success overlay: clicking backdrop does NOT dismiss — user must press Done.
  successOverlay.addEventListener('click', function (e) { e.stopPropagation(); });

  // ── ESC key ───────────────────────────────────────────────────────────────
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    // ESC does NOT close the success modal — only the Done button does.
    if (formOverlay.classList.contains('rlm-open')) { closeAll(); }
  });

  // ── Wire up "Report Lost Item" tab trigger ────────────────────────────────
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-open-report-lost]');
    if (!trigger) return;
    e.preventDefault();
    openForm();
  });

  // Expose for external calls (e.g. StudentDashboard.php shared trigger)
  window.openReportLostModal = openForm;

})();
</script>
<script src="ItemDetailsModal.js?v=<?php echo time(); ?>"></script>
<script>
// ── All Reports tab: View → item details modal only ─────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-view-report]');
    if (!btn) return;
    e.preventDefault();
    showItemDetailsModal(btn.getAttribute('data-view-report'), { showClaimButton: false });
});

// ── Matched tab: View → comparison modal only ───────────────────────────────
// Uses data-view-matched-pair — ItemDetailsModal.js never sees this attribute.
// All Reports tab View buttons use data-view-report and are handled exclusively
// by ItemDetailsModal.js — we do NOT add any handler for that attribute here.
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-view-matched-pair]');
    if (!btn) return;
    e.preventDefault();
    var refId = btn.getAttribute('data-view-matched-pair');
    var pair  = (window.matchedPairsData || []).find(function(p) { return p.ref_id === refId; });
    if (pair) {
        showReportComparisonModal(pair);
    } else {
        showItemDetailsModal(refId, { showClaimButton: false });
    }
});

// ── Claim button handler ────────────────────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-claim-report]');
    if (!btn) return;
    e.preventDefault();
    var refId = btn.getAttribute('data-claim-report');
    showReportClaimModal(refId);
});

// ── Comparison modal (same design as Dashboard) ─────────────────────────────
function showReportComparisonModal(pair) {
    var existing = document.getElementById('reportCompModal');
    if (existing) existing.remove();

    var ticketId = pair.ref_id || pair.found_id;

    function imgBox(img) {
        if (img) return '<img src="' + img + '" alt="Item" style="width:90px;height:90px;object-fit:cover;border-radius:8px;display:block;">';
        return '<div style="width:90px;height:90px;background:#e5e7eb;border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-box" style="font-size:28px;color:#9ca3af;"></i></div>';
    }
    function infoRows(rows) {
        return rows.map(function(r) {
            return '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;">' +
                '<span style="color:#6b7280;font-size:13px;">' + r[0] + ':</span>' +
                '<span style="font-weight:700;font-size:13px;text-align:right;max-width:55%;word-break:break-word;">' + (r[1] || '-') + '</span></div>';
        }).join('');
    }
    function panel(img, label1, label2, rows) {
        return '<div style="display:flex;align-items:stretch;">' +
            '<div style="flex-shrink:0;width:120px;background:#f5f5f5;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px 10px;gap:8px;border-radius:8px;margin-right:14px;">' +
            imgBox(img) +
            '<div style="font-size:11px;color:#374151;font-weight:700;text-align:center;line-height:1.4;">' + label1 + '<br><span style="font-weight:600;color:#555;">' + label2 + '</span></div></div>' +
            '<div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:14px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #e5e7eb;">General Information</div>' +
            infoRows(rows) + '</div></div>';
    }

    var foundPanel = panel(pair.found_image || '', 'Barcode ID:', pair.found_id,
        [['Category', pair.found_item_type], ['Color', pair.found_color], ['Brand', pair.found_brand], ['Date Found', pair.found_date]]);
    var refPanel = pair.ref_id ? panel(pair.ref_image || '', 'Ticket ID:', pair.ref_id,
        [['Category', pair.ref_item_type || pair.found_item_type], ['Color', pair.ref_color || pair.found_color], ['Brand', pair.ref_brand || pair.found_brand], ['Date Lost', pair.ref_date_lost]]) : '';

    var html = '<div id="reportCompModal" role="dialog" aria-modal="true" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.55);padding:12px;">' +
        '<div style="background:#fff;border-radius:12px;width:100%;max-width:580px;box-shadow:0 24px 64px rgba(0,0,0,0.35);display:flex;flex-direction:column;max-height:92vh;overflow:hidden;">' +
        '<div style="background:#8b0000;padding:15px 20px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;border-radius:12px 12px 0 0;">' +
        '<h2 style="margin:0;color:#fff;font-size:17px;font-weight:700;font-family:inherit;">Item Details</h2>' +
        '<button type="button" onclick="closeReportCompModal()" style="background:none;border:2px solid rgba(255,255,255,0.6);color:#fff;font-size:13px;cursor:pointer;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-xmark"></i></button></div>' +
        '<div style="overflow-y:auto;flex:1;padding:20px 20px 4px;">' +
        '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:14px;">' + foundPanel + '</div>' +
        (pair.ref_id ? '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:14px;">' + refPanel + '</div>' : '') +
        '</div>' +
        '<div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #e5e7eb;background:#fff;flex-shrink:0;border-radius:0 0 12px 12px;">' +
        '<button type="button" onclick="closeReportCompModal()" style="padding:9px 28px;border:1px solid #9ca3af;border-radius:7px;background:#fff;color:#374151;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Cancel</button>' +
        '<button type="button" onclick="closeReportCompModal();showReportClaimModal(\'' + ticketId + '\')" style="padding:9px 28px;border:none;border-radius:7px;background:#16a34a;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Claim</button>' +
        '</div></div></div>';

    document.body.insertAdjacentHTML('beforeend', html);
    document.getElementById('reportCompModal').addEventListener('click', function(e) {
        if (e.target === this) closeReportCompModal();
    });
}
function closeReportCompModal() {
    var m = document.getElementById('reportCompModal');
    if (m) m.remove();
}

// ── Claim flow (confirm → spinner → result) ─────────────────────────────────
function showReportClaimModal(ticketId) {
    var existing = document.getElementById('reportClaimModal');
    if (existing) existing.remove();

    var html = '<div id="reportClaimModal" role="dialog" aria-modal="true" style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);padding:16px;">' +
        '<div style="background:#fff;border-radius:16px;width:100%;max-width:400px;padding:36px 32px 28px;text-align:center;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.3);">' +
        '<button type="button" id="rclmClose" aria-label="Close" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:18px;color:#9ca3af;cursor:pointer;"><i class="fa-solid fa-circle-xmark"></i></button>' +
        '<div id="rclmBody">' +
        '<div style="width:72px;height:72px;background:#fff7ed;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;"><i class="fa-solid fa-bag-shopping" style="font-size:30px;color:#f59e0b;"></i></div>' +
        '<h3 style="margin:0 0 10px;font-size:20px;font-weight:700;font-family:inherit;color:#111;">Claim This Item?</h3>' +
        '<p style="margin:0 0 6px;font-size:14px;color:#374151;line-height:1.6;">Submitting this claim will notify the Lost &amp; Found office.</p>' +
        '<p style="margin:0 0 26px;font-size:13px;font-weight:700;color:#555;">Ticket ID: ' + ticketId + '</p>' +
        '<div style="display:flex;justify-content:center;gap:12px;">' +
        '<button type="button" id="rclmCancel" style="padding:10px 28px;border:1px solid #9ca3af;border-radius:8px;background:#fff;color:#374151;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Cancel</button>' +
        '<button type="button" id="rclmOk" style="padding:10px 28px;border:none;border-radius:8px;background:#8b0000;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Confirm Claim</button>' +
        '</div></div></div></div>';

    document.body.insertAdjacentHTML('beforeend', html);
    var modal = document.getElementById('reportClaimModal');
    document.getElementById('rclmClose').addEventListener('click', closeReportClaimModal);
    document.getElementById('rclmCancel').addEventListener('click', closeReportClaimModal);
    document.getElementById('rclmOk').addEventListener('click', function() { submitReportClaim(ticketId); });
    modal.addEventListener('click', function(e) { if (e.target === this) closeReportClaimModal(); });
}
function closeReportClaimModal() {
    var m = document.getElementById('reportClaimModal');
    if (m) m.remove();
}
function submitReportClaim(ticketId) {
    var body  = document.getElementById('rclmBody');
    var modal = document.getElementById('reportClaimModal');
    modal.onclick = null;

    if (!document.getElementById('rclmSpinStyle')) {
        var s = document.createElement('style');
        s.id = 'rclmSpinStyle';
        s.textContent = '@keyframes rclmSpin{to{transform:rotate(360deg)}}';
        document.head.appendChild(s);
    }
    body.innerHTML = '<div style="padding:20px 0 10px;"><div style="width:52px;height:52px;border:5px solid #e5e7eb;border-top-color:#8b0000;border-radius:50%;animation:rclmSpin 0.8s linear infinite;margin:0 auto 18px;"></div><p style="font-size:14px;color:#555;margin:0;">Submitting your claim…</p></div>';

    fetch('SubmitClaim.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId })
    })
    .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
    .then(function(json) {
        if (json.ok || json.success) {
            showReportClaimResult(body, modal, true, 'Item Claimed!',
                'Your claim has been recorded. Please present your ticket ID at the Lost &amp; Found office.', ticketId);
        } else {
            showReportClaimResult(body, modal, false, 'Already Submitted',
                json.message || 'This claim may have already been recorded. Please visit the office.', ticketId);
        }
    })
    .catch(function() {
        showReportClaimResult(body, modal, false, 'Connection Error',
            'We could not reach the server. Please visit the Lost &amp; Found office with your ticket ID.', ticketId);
    });
}
function showReportClaimResult(body, modal, ok, title, msg, ticketId) {
    var bg   = ok ? '#22c55e' : '#f59e0b';
    var icon = ok ? 'fa-circle-check' : 'fa-exclamation';
    body.innerHTML = '<div style="width:72px;height:72px;background:' + bg + ';border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;"><i class="fa-solid ' + icon + '" style="font-size:32px;color:#fff;"></i></div>' +
        '<h3 style="margin:0 0 10px;font-size:20px;font-weight:700;font-family:inherit;color:#111;">' + title + '</h3>' +
        '<p style="margin:0 0 8px;font-size:13px;color:#555;line-height:1.6;">' + msg + '</p>' +
        '<p style="margin:0 0 26px;font-size:13px;font-weight:700;color:#374151;">Ticket ID: ' + ticketId + '</p>' +
        '<button type="button" id="rclmDone" style="padding:10px 36px;border:none;border-radius:8px;background:#8b0000;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Done</button>';
    function onDone() { closeReportClaimModal(); if (ok) window.location.href = 'ClaimHistory.php'; }
    document.getElementById('rclmDone').addEventListener('click', onDone);
    modal.addEventListener('click', function(e) { if (e.target === this) onDone(); });
}
</script>
<script>
(function () {
  // ── Confirmation modal wiring ─────────────────────────────────────────────
  var confirmModal   = document.getElementById('confirmModal');
  var confirmMessage = document.getElementById('confirmMessage');
  var confirmCancel  = document.getElementById('confirmCancel');
  var confirmOk      = document.getElementById('confirmOk');
  var pendingAction  = null;

  function showConfirm(message, callback) {
    if (!confirmModal || !confirmMessage) return;
    confirmMessage.textContent = message;
    confirmModal.style.display = 'flex';
    confirmModal.setAttribute('aria-hidden', 'false');
    pendingAction = callback;
  }

  function hideConfirm() {
    if (!confirmModal) return;
    confirmModal.style.display = 'none';
    confirmModal.setAttribute('aria-hidden', 'true');
    pendingAction = null;
  }

  if (confirmCancel) confirmCancel.addEventListener('click', hideConfirm);

  if (confirmOk) {
    confirmOk.addEventListener('click', function () {
      if (pendingAction) pendingAction();
      hideConfirm();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && confirmModal && confirmModal.style.display === 'flex') {
      hideConfirm();
    }
  });

  // ── Cancel report — AJAX, then remove row from DOM ────────────────────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-cancel-report]');
    if (!btn) return;
    var cooldownUntil = btn.getAttribute('data-cooldown-until');
    if (cooldownUntil) {
      showInlineToast(
        'Reports can only be cancelled 24 hours after submission. You may cancel this after: ' + cooldownUntil + '.',
        'warning'
      );
      return;
    }
    if (btn.disabled || btn.hasAttribute('disabled')) return;
    e.preventDefault();

    var reportId = btn.getAttribute('data-cancel-report');
    if (!reportId) return;

    showConfirm('Are you sure you want to cancel this report?', function () {
      // Disable button immediately to prevent double-clicks
      btn.disabled = true;
      btn.textContent = 'Cancelling…';

      fetch('CancelReport.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: reportId })
      })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.ok) {
          // Find the row and fade it out, then remove it
          var row = btn.closest('tr');
          if (row) {
            row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
            row.style.opacity    = '0';
            row.style.transform  = 'translateX(16px)';
            setTimeout(function () {
              row.remove();
              // If table is now empty, show the empty state
              var tbody = document.querySelector('.reports-data-table tbody');
              if (tbody && !tbody.querySelector('tr')) {
                var colSpan = tbody.closest('table').querySelectorAll('thead th').length;
                var emptyRow = document.createElement('tr');
                emptyRow.innerHTML = '<td colspan="' + colSpan + '" class="table-empty">No reports yet.</td>';
                tbody.appendChild(emptyRow);
              }
            }, 380);
          }
          showInlineToast('Report cancelled successfully.', 'success');
        } else {
          btn.disabled    = false;
          btn.textContent = 'Cancel';
          showInlineToast(json.message || 'Could not cancel this report.', 'error');
        }
      })
      .catch(function () {
        btn.disabled    = false;
        btn.textContent = 'Cancel';
        showInlineToast('Network error. Please try again.', 'error');
      });
    });
  });

  // ── Inline toast (no system alert) ───────────────────────────────────────
  function showInlineToast(msg, type) {
    var existing = document.getElementById('cancelToast');
    if (existing) existing.remove();

    var bg   = type === 'success' ? '#16a34a' : type === 'warning' ? '#d97706' : '#dc2626';
    var icon = type === 'success' ? 'fa-circle-check' : type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark';

    var toast = document.createElement('div');
    toast.id = 'cancelToast';
    toast.style.cssText =
      'position:fixed;top:22px;right:22px;z-index:99999;' +
      'display:flex;align-items:center;gap:10px;' +
      'background:' + bg + ';color:#fff;' +
      'padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;' +
      'box-shadow:0 6px 24px rgba(0,0,0,0.22);' +
      'opacity:0;transform:translateY(-8px);transition:opacity 0.25s,transform 0.25s;' +
      'font-family:inherit;max-width:340px;';
    toast.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + msg;
    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        toast.style.opacity   = '1';
        toast.style.transform = 'translateY(0)';
      });
    });

    // Auto-dismiss after 3 s
    setTimeout(function () {
      toast.style.opacity   = '0';
      toast.style.transform = 'translateY(-8px)';
      setTimeout(function () { if (toast.parentNode) toast.remove(); }, 280);
    }, 3000);
  }
})();
</script>

<script>
/* ── Bell icon: pre-fix + SVG fill + footer link ───────────────────────────
   Inline scripts run BEFORE deferred scripts (FA-JS).
   Step 1: swap fa-regular → fa-solid so FA-JS renders the FILLED bell SVG.
   Step 2: once FA-JS has injected the SVG (at DOMContentLoaded), set fill
           on BOTH the <svg> element AND its <path> children (which carry
           fill="currentColor"; targeting paths directly is the reliable fix).
   Step 3: MutationObserver watches for any future SVG re-injection.
   Step 4: patch the notification panel footer link text + href.
*/
(function() {
  /* Step 1 – swap icon class before FA-JS processes it */
  function swapBellClass() {
    var btn = document.getElementById('notifTrigger');
    if (!btn) return;
    var ico = btn.querySelector('i');
    if (ico) { ico.classList.remove('fa-regular'); ico.classList.add('fa-solid'); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', swapBellClass);
  else swapBellClass();

  /* Steps 2 & 3 – force white fill on SVG + paths after FA-JS fires */
  document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('notifTrigger');
    if (!btn) return;

    function applyWhite() {
      /* Target the <svg> element AND every <path> inside it.
         Setting color on <svg> makes currentColor white for child elements.
         Setting fill on <path> overrides the fill="currentColor" attribute. */
      btn.querySelectorAll('svg').forEach(function(s) {
        s.style.fill  = '#ffffff';
        s.style.color = '#ffffff';
      });
      btn.querySelectorAll('svg path').forEach(function(p) {
        p.style.fill = '#ffffff';
      });
    }

    applyWhite(); /* catch SVGs already rendered by FA-JS (defer fires before DOMContentLoaded) */
    new MutationObserver(applyWhite).observe(btn, { childList: true, subtree: true });

    /* Step 4 – patch footer link inside notification panel */
    var panel = document.getElementById('notifPanel');
    if (!panel) return;
    function patchFooter() {
      panel.querySelectorAll('a').forEach(function(a) {
        if (!a.closest('.notif-list')) {
          a.textContent = 'View other notifications';
          a.href = 'Notifications.php';
        }
      });
    }
    patchFooter();
    btn.addEventListener('click', function() { setTimeout(patchFooter, 80); });
  });
})();
</script>
</body>
</html>