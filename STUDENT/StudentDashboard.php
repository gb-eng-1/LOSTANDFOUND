<?php
/**
 * Student Dashboard - UB Lost and Found System (STUDENT POV)
 * Displayed when a student logs in.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$studentId = (int) $_SESSION['student_id'];
$studentEmail = $_SESSION['student_email'] ?? '';
$studentName = $_SESSION['student_name'] ?? '';

// Get student_id for matching reports
$studentNumber = null;
$stmt = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
$stmt->execute([$studentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && !empty($row['student_id'])) {
    $studentNumber = trim($row['student_id']);
}

// Build user_id patterns: email or student_number@ub.edu.ph
$userIds = [$studentEmail];
if ($studentNumber) {
    $userIds[] = $studentNumber . '@ub.edu.ph';
}
$placeholders = implode(',', array_fill(0, count($userIds), '?'));

// --- Check for matched_barcode_id column ---
$hasMatchedColumn = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM items LIKE 'matched_barcode_id'");
    $hasMatchedColumn = $cols && $cols->rowCount() > 0;
} catch (PDOException $e) {}

// My Reports: REF-xxx items where user_id matches this student (full info for table)
$myReports = [];
try {
    if ($hasMatchedColumn) {
        $sql = "SELECT r.id, r.item_type, r.date_lost, r.item_description, r.matched_barcode_id, r.status, r.created_at, r.storage_location, f.status AS found_status, c.status AS claim_status
                FROM items r
                LEFT JOIN items f ON r.matched_barcode_id = f.id
                LEFT JOIN claims c ON r.id = c.lost_report_id
                WHERE r.id LIKE 'REF-%' AND r.status != 'Cancelled' AND (r.user_id IN ($placeholders) OR LOWER(TRIM(r.user_id)) = LOWER(?))
                ORDER BY r.created_at DESC LIMIT 10";
    } else {
        $sql = "SELECT r.id, r.item_type, r.date_lost, r.item_description, NULL AS matched_barcode_id, r.status, r.created_at, r.storage_location, NULL AS found_status, c.status AS claim_status
                FROM items r
                LEFT JOIN claims c ON r.id = c.lost_report_id
                WHERE r.id LIKE 'REF-%' AND r.status != 'Cancelled' AND (r.user_id IN ($placeholders) OR LOWER(TRIM(r.user_id)) = LOWER(?))
                ORDER BY created_at DESC LIMIT 10";
    }
    $params = array_merge($userIds, [$studentEmail]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $desc = $r['item_description'] ?? '';
        $studentNum = $contact = $dept = '';
        if (preg_match('/Student Number:\s*(.+?)(?:\n|$)/m', $desc, $m)) $studentNum = trim($m[1]);
        if (preg_match('/Contact:\s*(.+?)(?:\n|$)/m', $desc, $m)) $contact = trim($m[1]);
        if (preg_match('/Department:\s*(.+?)(?:\n|$)/m', $desc, $m)) $dept = trim($m[1]);
        $myReports[] = [
            'id'               => $r['id'],
            'ticket_id'        => $r['id'],
            'category'         => $r['item_type'] ?? 'Miscellaneous',
            'department'       => $dept ?: '-',
            'id_num'           => $studentNum ?: '-',
            'contact_number'   => $contact ?: '-',
            'date_lost'        => $r['date_lost'] ? date('Y-m-d', strtotime($r['date_lost'])) : '-',
            'status'           => $r['status'] ?? '',
            'matched'          => $hasMatchedColumn && !empty($r['matched_barcode_id'] ?? null),
            'found_status'     => $r['found_status'] ?? '',
            'claim_status'     => $r['claim_status'] ?? '',
            'storage_location' => $r['storage_location'] ?? '',
        ];
    }
} catch (PDOException $e) {
    $myReports = [];
}

// Recently Matched Items — only show items actually matched to THIS student's reports
$recentlyMatched = [];
$recentlyMatchedIsPersonal = false;
if ($hasMatchedColumn) {
    try {
        $stmt = $pdo->prepare("
            SELECT f.id, f.item_type, f.brand, f.color, f.item_description, f.found_at, f.date_encoded,
                   ref.id AS ref_id
            FROM items ref
            JOIN items f ON f.id = ref.matched_barcode_id
            WHERE ref.id LIKE 'REF-%'
              AND ref.matched_barcode_id IS NOT NULL
              AND (ref.user_id IN ($placeholders) OR LOWER(ref.user_id) = LOWER(?))
            ORDER BY f.date_encoded DESC LIMIT 3
        ");
        $params = array_merge($userIds, [$studentEmail]);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $recentlyMatched = $rows;
            $recentlyMatchedIsPersonal = true;
        }
    } catch (PDOException $e) {}
}
// No fallback — if the student has no personal matches, the section shows empty state.

// Build matched pairs for comparison modal (found item + REF- lost report, with images)
$matchedPairsJson = '[]';
if ($hasMatchedColumn) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ref.id AS ref_id, ref.item_type AS ref_item_type, ref.brand AS ref_brand,
                ref.color AS ref_color, ref.date_lost AS ref_date_lost,
                ref.item_description AS ref_desc, ref.image_data AS ref_image,
                f.id AS found_id, f.item_type AS found_item_type, f.brand AS found_brand,
                f.color AS found_color, f.date_encoded AS found_date,
                f.item_description AS found_desc, f.found_at AS found_at,
                f.image_data AS found_image
            FROM items ref
            JOIN items f ON f.id = ref.matched_barcode_id
            WHERE ref.id LIKE 'REF-%'
              AND ref.matched_barcode_id IS NOT NULL
              AND (ref.user_id IN ($placeholders) OR LOWER(ref.user_id) = LOWER(?))
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

// Recent Activity — only real matches via matched_barcode_id
$recentActivity = [];
if ($hasMatchedColumn) {
    try {
        $stmt = $pdo->prepare("
            SELECT f.id AS found_id, f.item_type, f.color, f.brand, ref.id AS ref_id
            FROM items ref
            JOIN items f ON f.id = ref.matched_barcode_id
            WHERE ref.id LIKE 'REF-%'
              AND ref.matched_barcode_id IS NOT NULL
              AND (ref.user_id IN ($placeholders) OR LOWER(ref.user_id) = LOWER(?))
            ORDER BY f.date_encoded DESC LIMIT 3
        ");
        $params = array_merge($userIds, [$studentEmail]);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $label = trim(($row['color'] ? $row['color'] . ' ' : '') . ($row['item_type'] ?: 'Item'));
            $recentActivity[] = ['id' => $row['found_id'], 'label' => $label, 'report_id' => $row['ref_id']];
        }
    } catch (PDOException $e) {}
}

// Prepare data for JS search dropdown
$jsSearchData = [];
foreach ($myReports as $r) {
    $jsSearchData[] = [
        'id' => $r['ticket_id'],
        'ticket_id' => $r['ticket_id'],
        'category' => $r['category'],
        'item_type' => $r['category'],
    ];
}
foreach ($recentlyMatched as $item) {
    $jsSearchData[] = [
        'id' => $item['id'],
        'ticket_id' => null,
        'category' => $item['item_type'],
        'item_type' => $item['item_type'],
    ];
}

function extractItemName($desc, $itemType) {
    if (is_string($desc) && preg_match('/^Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) return trim($m[1]);
    return $itemType ?: 'Item';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <!-- FA JS renderer: injects inline SVGs, bypasses CORS font-face issues -->
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="StudentDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/photo-picker.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ReportLostModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="FoundItemModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ItemDetailsModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ClaimItemModal.css?v=<?php echo time(); ?>">
  <script>
    var searchData = <?php echo json_encode(array_values(array_reduce($jsSearchData, function ($c, $i) { $c[$i['id']] = $i; return $c; }, []))); ?>;
    // Matched pairs data from PHP (used by comparison modal)
    var matchedPairsData = <?php echo $matchedPairsJson; ?>;
    // Recently matched found items
    var recentlyMatchedData = <?php echo json_encode($recentlyMatched, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    // Whether recentlyMatched are personal matches or generic fallback
    var recentlyMatchedIsPersonal = <?php echo json_encode($recentlyMatchedIsPersonal ?? false); ?>;
  </script>
  <style>
    /* ── Nav colours ── */
    .nav-item.active .nav-item-icon,
    .nav-item.active .nav-item-icon i,
    .nav-item.active .nav-item-label { color: #ffffff !important; }
    .nav-menu .nav-item:not(.active) .nav-item-icon,
    .nav-menu .nav-item:not(.active) .nav-item-icon i,
    .nav-menu .nav-item:not(.active) .nav-item-label { color: #8b0000 !important; }

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


    /* ── Sidebar: clear min-height in mobile/column layout ── */
    @media (max-width: 900px) {
      .sidebar { min-height: 0 !important; height: auto !important; }
    }

    /* ── Main content area ── */
    .main-content-wrap {
      padding: 24px 28px 32px;
      flex: 1;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
    }
    .dashboard-header  { font-size: 26px; font-weight: 700; color: #111; margin-bottom: 4px; }
    .dashboard-welcome { font-size: 15px; color: #6b7280; margin-bottom: 28px; }

    /* ── Action Cards ── */
    .action-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 28px;
    }
    .action-card {
      border-radius: 14px;
      padding: 28px 20px 22px;
      text-align: center;
      text-decoration: none;
      color: #111;
      font-size: 16px;
      font-weight: 500;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
      background: #ffffff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
      border: 1px solid #e5e7eb;
      transition: transform .2s, box-shadow .2s;
    }
    .action-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); color: #111; }
    .action-card-icon { position: relative; display: inline-flex; align-items: center; justify-content: center; font-size: 46px;}
    .action-card-icon > i:first-child { font-size: 1em; }
    .action-card.lost  .action-card-icon > i:first-child { color: #8b0000; }
    .action-card.lost  .arrow-icon { position: absolute; bottom: -0.06em; right: -0.043em; color: #8b0000; }
    .action-card.lost  strong { color: #8b0000; }
    .action-card.found .action-card-icon > i:first-child { color: #1e40af; }
    .action-card.found .check-icon { position: absolute; bottom: -3px; right: -12px; font-size: 18px; color: #1e40af; }
    .action-card.found strong { color: #1e40af; }
    .action-card.claim .action-card-icon > i:first-child { color: #15803d; }
    .action-card.claim strong { color: #15803d; }

    /* ── Dashboard grid ── */
    .dashboard-grid {
      display: grid;
      grid-template-columns: minmax(0,1fr) 280px;
      gap: 24px;
      min-width: 0;
    }
    .dashboard-main { min-width: 0; }

    /* ── Section card ── */
    .section-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border: 1px solid #e5e7eb;
    }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .section-title  { font-size: 16px; font-weight: 600; color: #111; }
    .section-link   { font-size: 13px; color: #8b0000; text-decoration: none; font-weight: 500; }
    .section-link:hover { text-decoration: underline; }

    /* ── Bottom grid ── */
    .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .bottom-grid .section-card { margin-bottom: 0; }

    /* ── How-to steps ── */
    .steps-list { display: flex; flex-direction: column; }
    .step-item { display: flex; gap: 14px; align-items: flex-start; padding: 14px 0; border-bottom: 1px solid #f0f0f0; }
    .step-item:last-child { border-bottom: none; padding-bottom: 0; }
    .step-icon { width: 36px; height: 36px; flex-shrink: 0; background: #f3f4f6; color: #8b0000; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .step-text { font-size: 13px; color: #374151; line-height: 1.55; }
    .step-highlight { color: #8b0000; }

    /* ── My Reports table ── */
    .reports-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .reports-table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 13px; }
    .reports-table thead tr { background: #f3f4f6; }
    .reports-table th { padding: 12px 14px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #d1d5db; white-space: nowrap; }
    .reports-table td { padding: 12px 14px; color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .reports-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .reports-table tbody tr:last-child td { border-bottom: none; }
    .reports-table tbody tr:hover td { background: #f3f4f6; }
    .table-empty { color: #9ca3af; text-align: center; padding: 24px; }

    /* ── Ticket ID link ── */
    .ticket-id-link {
      color: #8b0000;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px dotted #8b0000;
    }
    .ticket-id-link:hover { border-bottom-style: solid; }

    /* ── Recently Matched Items row ── */
    .matched-items-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      overflow-x: auto;
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    .matched-items-row::-webkit-scrollbar { display: none; }
    .matched-item-card {
      background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;
      padding: 16px; min-width: 0;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      display: flex; flex-direction: column; gap: 8px;
    }
    /* On narrow screens collapse to 1 scrollable column of fixed-width cards */
    @media (max-width: 900px) {
      .matched-items-row {
        display: flex;
        gap: 14px;
      }
      .matched-item-card { min-width: 260px; flex-shrink: 0; }
    }
    .matched-item-card h4 { margin: 0; font-size: 16px; display: flex; align-items: center; gap: 10px; font-weight: 700; color: #333; }
    .matched-item-card h4 i { font-size: 18px; color: #333; }
    .matched-item-card .desc-row { display: flex; gap: 10px; align-items: flex-start; color: #333; font-size: 14px; font-style: italic; margin-bottom: 4px; }
    .matched-item-card .desc-row i { margin-top: 3px; color: #333; }
    .matched-item-card .meta { display: flex; align-items: center; gap: 10px; color: #555; font-size: 14px; }
    .matched-item-card .meta i { width: 18px; text-align: center; }
    .matched-item-card .btn-view {
      background: #007bff; color: #fff; border: none;
      padding: 8px 24px; border-radius: 6px; font-size: 14px; font-weight: 600;
      cursor: pointer; align-self: flex-end; margin-top: 8px;
    }
    .matched-item-card .btn-view:hover { background: #0069d9; }

    /* ── Activity Sidebar ── */
    .activity-sidebar { align-self: start; position: sticky; top: 24px; min-width: 0; }
    .activity-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; }
    .activity-title { font-size: 15px; font-weight: 600; color: #111; margin-bottom: 16px; }
    .activity-item { padding: 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; margin-bottom: 12px; font-size: 13px; color: #92400e; display: flex; align-items: flex-start; gap: 10px; }
    .activity-item:last-child { margin-bottom: 0; }
    .activity-icon { width: 32px; height: 32px; flex-shrink: 0; background: #fefce8; color: #a16207; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; }
    .activity-text strong { display: block; margin-bottom: 4px; color: #b45309; }
    .activity-text p { margin: 0 0 6px; line-height: 1.4; }
    .activity-text a { color: #8b0000; font-weight: 500; font-size: 13px; text-decoration: none; }
    .activity-text a:hover { text-decoration: underline; }

    /* ── Misc ── */
    .empty-text { color: #9ca3af; font-size: 14px; font-style: italic; padding-left: 10px; }

    /* ── Responsive ── */
    @media (max-width: 1100px) {
      .dashboard-grid { grid-template-columns: 1fr; }
      .activity-sidebar { position: static; }
      .bottom-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .action-cards { grid-template-columns: 1fr; }
      .main-content-wrap { padding: 16px 20px 24px; }
    }
  </style>
</head>
<body data-student-email="<?= htmlspecialchars($studentEmail) ?>">
<div class="layout">

  <!-- Sidebar (same design as AdminDashboard) -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"></div>
      <div class="sidebar-title">
        <span class="sidebar-title-line1">University of</span>
        <span class="sidebar-title-line2">Batangas</span>
      </div>
    </div>

    <ul class="nav-menu">
      <li>
        <a class="nav-item active" href="StudentDashboard.php">
          <div class="nav-item-icon"><i class="fa-solid fa-house"></i></div>
          <div class="nav-item-label">Dashboard</div>
        </a>
      </li>
      <li>
        <a class="nav-item" href="StudentsReport.php">
          <div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div>
          <div class="nav-item-label">My Reports</div>
        </a>
      </li>
      <li>
        <a class="nav-item" href="ClaimHistory.php">
          <div class="nav-item-icon"><i class="fa-regular fa-calendar"></i></div>
          <div class="nav-item-label">Claim History</div>
        </a>
      </li>
      <li>
        <a class="nav-item" href="HelpSupport.php">
          <div class="nav-item-icon"><i class="fa-solid fa-circle-question"></i></div>
          <div class="nav-item-label">Help and Support</div>
        </a>
      </li>
    </ul>
  </aside>

  <!-- Main content -->
  <main class="main">
    <!-- Top bar (same design as AdminDashboard) -->
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-left">
        <form class="search-form" action="StudentsReport.php" method="get">
          <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search items by name or barcode…" autocomplete="off" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
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
            <span class="admin-name"><?php echo htmlspecialchars($studentName ?: $studentEmail); ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size: 11px;"></i>
          </button>
          <div class="admin-dropdown-menu" role="menu">
            <a href="logout.php" role="menuitem" class="admin-dropdown-item"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Dashboard Content -->
    <div class="main-content-wrap">
      <h1 class="dashboard-header">Dashboard</h1>
      <p class="dashboard-welcome">Welcome to UB Lost and Found System, <?php echo htmlspecialchars($studentName ?: explode('@', $studentEmail)[0]); ?>!</p>

      <!-- Action Cards -->
      <div class="action-cards">
        <a href="#" class="action-card lost" data-open-report-lost>
          <div class="action-card-icon">
            <i class="fa-solid fa-circle-question"></i>
            <i class="fa-solid fa-location-arrow arrow-icon"></i>
          </div>
          <span>I <strong>LOST</strong> an Item</span>
        </a>
        <a href="#" class="action-card found" data-open-found-item>
          <div class="action-card-icon">
            <i class="fa-regular fa-file-lines"></i>
            <i class="fa-solid fa-circle-check check-icon"></i>
          </div>
          <span>I <strong>FOUND</strong> an Item</span>
        </a>
        <a href="#" class="action-card claim" data-open-claim-item>
          <div class="action-card-icon">
            <i class="fa-solid fa-bag-shopping"></i>
          </div>
          <span><strong>CLAIM</strong> an Item</span>
        </a>
      </div>

      <!-- Main Grid -->
      <div class="dashboard-grid">
        <div class="dashboard-main">

          <!-- Recently Matched Items -->
          <section class="section-card">
            <div class="section-header">
              <h2 class="section-title">Recently Matched Item</h2>
              <a href="StudentsReport.php?filter=matched" class="section-link">see all</a>
            </div>
            <div class="matched-items-row">
              <?php if (empty($recentlyMatched)): ?>
                <p class="empty-text" style="padding:16px 0;">No matched items yet. When the office matches a found item to your report, it will appear here.</p>
              <?php else: ?>
                <?php foreach ($recentlyMatched as $item): ?>
                  <div class="matched-item-card">
                      <h4>
                          <i class="fa-regular fa-file-lines"></i>
                          <?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['item_type'] ?? 'Item'))) ?>
                      </h4>
                      <div class="desc-row">
                        <i class="fa-regular fa-file-lines"></i>
                        <?php 
                          $descText = $item['item_description'] ?? 'No description.';
                          echo htmlspecialchars(strlen($descText) > 60 ? substr($descText, 0, 60) . '...' : $descText);
                        ?>
                      </div>
                      <div class="meta">
                          <i class="fa-solid fa-location-dot"></i>
                          <span><?= htmlspecialchars($item['found_at'] ?? 'N/A') ?></span>
                      </div>
                      <div class="meta">
                          <i class="fa-regular fa-calendar"></i>
                          <span><?= htmlspecialchars($item['date_encoded'] ? date('Y-m-d', strtotime($item['date_encoded'])) : 'N/A') ?></span>
                      </div>
                      <button type="button" class="btn-view" 
                          data-view-matched-item="<?= htmlspecialchars($item['id']) ?>"
                          <?php if (!empty($item['ref_id'])): ?>data-ref-id="<?= htmlspecialchars($item['ref_id']) ?>"<?php endif; ?>>View</button>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>

          <!-- Two-column: How to Report + My Reports -->
          <div class="bottom-grid">

            <!-- How to Report Lost Item -->
            <section class="section-card how-to-card">
              <h2 class="section-title" style="margin-bottom:16px;">How to Report Lost Item</h2>
              <div class="steps-list">
                <div class="step-item">
                  <div class="step-icon"><i class="fa-solid fa-user"></i></div>
                  <div class="step-text"><strong>Step 1: <span class="step-highlight">Log in to the Dashboard.</span></strong> Access the system using your official student credentials.</div>
                </div>
                <div class="step-item">
                  <div class="step-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                  <div class="step-text"><strong>Step 2: <span class="step-highlight">Select "Report Lost Item".</span></strong> Click this button to open the reporting form and fill out item details.</div>
                </div>
                <div class="step-item">
                  <div class="step-icon"><i class="fa-solid fa-upload"></i></div>
                  <div class="step-text"><strong>Step 3: <span class="step-highlight">Upload a Photo.</span></strong> Attach a picture of your missing item or a similar reference image to help the system's matching process.</div>
                </div>
                <div class="step-item">
                  <div class="step-icon"><i class="fa-solid fa-paper-plane"></i></div>
                  <div class="step-text"><strong>Step 4: <span class="step-highlight">Submit the Report.</span></strong> After verifying all details, submit your report. You will receive updates when a potential match is found.</div>
                </div>
              </div>
            </section>

            <!-- My Reports -->
            <section class="section-card">
              <div class="section-header">
                <h2 class="section-title">My Reports</h2>
                <a href="StudentsReport.php" class="section-link">see all</a>
              </div>
              <div class="reports-table-wrapper">
                <table class="reports-table">
                  <thead>
                    <tr>
                      <th>Ticket ID</th>
                      <th>Category</th>
                      <th>Department</th>
                      <th>ID</th>
                      <th>Contact Number</th>
                      <th>Date Lost</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($myReports)): ?>
                      <tr><td colspan="6" class="table-empty">No reports yet.</td></tr>
                    <?php else: ?>
                      <?php
                      $nonCancellableStatuses = ['Claimed', 'Disposed', 'Cancelled', 'Resolved'];
                      $finalClaimStatuses = ['Approved', 'Resolved'];
                      foreach ($myReports as $r):
                          $refStatus = $r['status'] ?? '';
                          $foundStatus = $r['found_status'] ?? '';
                          $claimStatus = $r['claim_status'] ?? '';
                          $isRefNonCancellable = in_array($refStatus, $nonCancellableStatuses, true);
                          // A report cannot be cancelled if its matched item has been finalized (Claimed/Resolved),
                          // or if a formal claim record has been finalized (Approved/Resolved).
                          $isFoundItemClaimed = !empty($r['matched']) && in_array($foundStatus, ['Claimed', 'Resolved'], true);
                          // Also check if there's a finalized claim record for this report
                          $isClaimFinalized = in_array($claimStatus, $finalClaimStatuses, true);

                          // Also block cancel if the report's matched found item is already catalogued (has a storage location)
          $isCatalogued = !empty(trim($r['storage_location'] ?? ''));
          $canCancel = !$isRefNonCancellable && !$isFoundItemClaimed && !$isClaimFinalized && !$isCatalogued;
                      ?>
                        <tr>
                          <td><a href="#" class="ticket-id-link" data-view-report="<?php echo htmlspecialchars($r['id']); ?>"><?php echo htmlspecialchars($r['ticket_id']); ?></a></td>
                          <td><?php echo htmlspecialchars($r['category']); ?></td>
                          <td><?php echo htmlspecialchars($r['department']); ?></td>
                          <td><?php echo htmlspecialchars($r['id_num']); ?></td>
                          <td><?php echo htmlspecialchars($r['contact_number']); ?></td>
                          <td><?php echo htmlspecialchars($r['date_lost']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </section>

          </div><!-- /.bottom-grid -->
        </div><!-- /.dashboard-main -->

        <!-- Right Sidebar - Recent Activity -->
        <aside class="activity-sidebar">
          <div class="activity-card">
            <h3 class="activity-title">Recent Activity</h3>
            <?php if (empty($recentActivity)): ?>
              <p class="empty-text">No recent activity.</p>
            <?php else: ?>
              <?php foreach ($recentActivity as $act): ?>
                <div class="activity-item">
                  <div class="activity-icon"><i class="fa-solid fa-lightbulb"></i></div>
                  <div class="activity-text">
                    <strong>Potential Match!</strong>
                    <p><?php echo htmlspecialchars($act['id']); ?> (<?php echo htmlspecialchars($act['label']); ?>) match your report.</p>
                    <a href="#" class="activity-view-link" 
                       data-view-comparison="<?php echo htmlspecialchars($act['id']); ?>"
                       data-ref-id="<?php echo htmlspecialchars($act['report_id']); ?>">View Details</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </aside>

      </div><!-- /.dashboard-grid -->
    </div><!-- /.main-content-wrap -->
  </main>
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
    if (typeof showItemDetailsModal === 'function') {
      showItemDetailsModal(id, { showClaimButton: false });
    }
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
<?php require_once __DIR__ . '/includes/report_lost_modal.php'; ?>
<?php require_once __DIR__ . '/includes/found_item_modal.php'; ?>
<?php require_once __DIR__ . '/includes/claim_item_modal.php'; ?>
<script src="ItemDetailsModal.js?v=<?php echo time(); ?>"></script>
<script src="../assets/photo-picker.js?v=<?php echo time(); ?>"></script>
<script src="ReportLostModal.js?v=<?php echo time(); ?>"></script>
<script src="FoundItemModal.js?v=<?php echo time(); ?>"></script>
<script src="ClaimItemModal.js?v=<?php echo time(); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const notifTrigger = document.getElementById('notifTrigger');
    const notifPanel = document.getElementById('notifPanel');
    const notifList = notifPanel.querySelector('.notif-list');
    const notifBadge = notifTrigger.querySelector('.notif-badge');
    const notifPanelCount = notifPanel.querySelector('.notif-panel-count');

    let lastUnreadCount = -1;

    function fetchNotifications() {
        fetch('/LOSTANDFOUND/api/notifications')
            .then(res => res.json())
            .then(json => {
                if (json.ok) {
                    renderNotifications(json.data);
                }
            })
            .catch(err => console.error('Failed to fetch notifications', err));
    }

    function renderNotifications(notifications) {
        notifList.innerHTML = '';
        if (!notifications || notifications.length === 0) {
            notifList.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            return;
        }

        // Limit dropdown to 3 most recent notifications
        const recent = notifications.slice(0, 3);

        recent.forEach(n => {
            const isNew = !n.is_read;
            const time = new Date(n.created_at);
            const now = new Date();
            const diff = Math.floor((now - time) / 1000);
            let timeAgo = '';
            if (diff < 60) timeAgo = 'Just now';
            else if (diff < 3600) timeAgo = Math.floor(diff / 60) + 'm ago';
            else if (diff < 86400) timeAgo = Math.floor(diff / 3600) + 'h ago';
            else timeAgo = time.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

            const link = (n.type && n.type.includes('match')) ? 'StudentsReport.php?filter=matched' : 'ClaimHistory.php';
            const imgSrc = n.image_url || 'images/notif_placeholder.jpg';
            const itemHtml = `
                <div class="notif-item ${isNew ? 'notif-item-new' : ''}" data-id="${n.id}">
                    <div class="notif-item-thumb">
                        <img src="${imgSrc}" alt="Item" class="notif-thumb-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="notif-thumb-placeholder" style="display:none;"><i class="fa-solid fa-box-open"></i></div>
                    </div>
                    <div class="notif-item-body">
                        <div class="notif-item-top">
                            <span class="notif-item-title">${n.title}</span>
                            ${isNew ? '<span class="notif-item-new-badge">New</span>' : ''}
                            <span class="notif-item-time">${timeAgo}</span>
                        </div>
                        <div class="notif-item-message">
                            ${n.message}
                            <a href="${link}" class="notif-view-link">View Details</a>
                        </div>
                    </div>
                </div>
            `;
            notifList.insertAdjacentHTML('beforeend', itemHtml);
        });

        // Override the static "View all matched items" footer link from the PHP include
        const footerLink = notifPanel.querySelector('.notif-footer a, .notif-view-all, [class*="notif-footer"] a');
        if (footerLink) {
            footerLink.textContent = 'View other notifications';
            footerLink.href = 'Notifications.php';
        }
    }

    function markNotificationsAsRead() {
        const unreadItems = notifList.querySelectorAll('.notif-item-new');
        unreadItems.forEach(item => {
            const notifId = item.getAttribute('data-id');
            if (notifId) {
                fetch(`/LOSTANDFOUND/api/notifications/${notifId}/read`, { method: 'PUT' })
                    .then(res => res.json())
                    .then(json => {
                        if (json.ok) {
                            item.classList.remove('notif-item-new');
                            item.querySelector('.notif-item-new-badge')?.remove();
                        }
                    });
            }
        });
        updateUnreadCount(0);
    }
    
    function updateUnreadCount(count) {
        if (count > 0) {
            if (!notifBadge) {
                const newBadge = document.createElement('span');
                newBadge.className = 'notif-badge';
                notifTrigger.appendChild(newBadge);
            }
            notifTrigger.querySelector('.notif-badge').textContent = count;
            if (notifPanelCount) notifPanelCount.textContent = `${count} new`;
        } else {
            notifTrigger.querySelector('.notif-badge')?.remove();
            if (notifPanelCount) notifPanelCount.textContent = '';
        }
    }

    function pollForUnreadCount() {
        fetch('/LOSTANDFOUND/api/notifications/count')
            .then(res => res.json())
            .then(json => {
                if (json.ok) {
                    const count = json.data.unread_count;
                    if (count !== lastUnreadCount) {
                        updateUnreadCount(count);
                        lastUnreadCount = count;
                    }
                }
            })
            .catch(err => console.error('Failed to poll notification count', err));
    }

    notifTrigger.addEventListener('click', function() {
        const isPanelOpen = notifPanel.classList.contains('open');
        if (!isPanelOpen) {
            fetchNotifications();
            setTimeout(markNotificationsAsRead, 2000);
        }
    });

    pollForUnreadCount();
    setInterval(pollForUnreadCount, 15000);

    // ── Override "View all matched items" footer link ──────────────────────
    // Scan all <a> tags in #notifPanel that are outside .notif-list
    function patchNotifFooter() {
        if (!notifPanel) return;
        notifPanel.querySelectorAll('a').forEach(function(a) {
            if (!a.closest('.notif-list')) {
                a.textContent = 'View other notifications';
                a.href = 'Notifications.php';
            }
        });
    }
    patchNotifFooter();
    notifTrigger.addEventListener('click', function() { setTimeout(patchNotifFooter, 50); });

    // ── Bell icon: force white fill on SVG + path elements ───────────────
    function fixBellSvg() {
        if (!notifTrigger) return;
        notifTrigger.querySelectorAll('svg').forEach(function(s) { s.style.fill='#fff'; s.style.color='#fff'; });
        notifTrigger.querySelectorAll('svg path').forEach(function(p) { p.style.fill='#fff'; });
    }
    fixBellSvg();
    var bellObserver = new MutationObserver(fixBellSvg);
    if (notifTrigger) bellObserver.observe(notifTrigger, { childList: true, subtree: true });
});

// ── Comparison Modal (Found item on top + Student's lost report on bottom) ──
function showComparisonModal(foundId, refId) {
    const pairs = window.matchedPairsData || [];
    let pair = pairs.find(p => p.found_id === foundId);

    if (!pair) {
        const found = (window.recentlyMatchedData || []).find(i => i.id === foundId);
        if (found) {
            pair = {
                found_id:        found.id,
                found_item_type: found.item_type || 'Item',
                found_brand:     found.brand || '-',
                found_color:     found.color || '-',
                found_date:      found.date_encoded ? found.date_encoded.split(' ')[0] : '-',
                found_image:     found.image_data || null,
                ref_id:          refId || null,
                ref_item_type:   null,
                ref_brand:       null,
                ref_color:       null,
                ref_date_lost:   null,
                ref_image:       null,
            };
        }
    }

    if (!pair) {
        window.location.href = 'StudentsReport.php?filter=matched';
        return;
    }

    // Resolve the ticket ID shown to user — prefer ref_id, fallback to found_id
    const ticketId = pair.ref_id || pair.found_id;

    function imgBox(imageData) {
        if (imageData) {
            return `<img src="${imageData}" alt="Item"
                        style="width:min(100px,30vw);height:min(100px,30vw);
                               object-fit:cover;border-radius:8px;display:block;">`;
        }
        return `<div style="width:min(100px,30vw);height:min(100px,30vw);
                             background:#e5e7eb;border-radius:8px;
                             display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-box" style="font-size:clamp(20px,5vw,32px);color:#9ca3af;"></i>
                </div>`;
    }

    function infoTable(rows) {
        return rows.map(([label, val]) => `
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:5px 0;border-bottom:1px solid #f0f0f0;">
                <span style="color:#6b7280;font-size:clamp(11px,2.5vw,13px);">${label}:</span>
                <span style="font-weight:700;font-size:clamp(11px,2.5vw,13px);text-align:right;
                             max-width:55%;word-break:break-word;">${val || '-'}</span>
            </div>`).join('');
    }

    function buildPanel(imgData, labelLine1, labelLine2, infoRows) {
        return `
        <div style="display:flex;align-items:stretch;">
            <!-- Image col -->
            <div style="flex-shrink:0;width:clamp(110px,28%,150px);background:#f5f5f5;
                        display:flex;flex-direction:column;align-items:center;justify-content:center;
                        padding:14px 10px;gap:8px;border-radius:8px;margin-right:14px;">
                ${imgBox(imgData)}
                <div style="font-size:clamp(10px,2.2vw,12px);color:#374151;font-weight:700;
                            text-align:center;line-height:1.4;">
                    ${labelLine1}<br><span style="font-weight:600;color:#555;">${labelLine2}</span>
                </div>
            </div>
            <!-- Info col -->
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:clamp(12px,2.8vw,14px);margin-bottom:10px;
                            padding-bottom:6px;border-bottom:1px solid #e5e7eb;">General Information</div>
                ${infoTable(infoRows)}
            </div>
        </div>`;
    }

    const foundPanel = buildPanel(
        pair.found_image,
        'Barcode ID:',
        pair.found_id,
        [
            ['Category',   pair.found_item_type],
            ['Item',       pair.found_item_type],
            ['Color',      pair.found_color],
            ['Brand',      pair.found_brand],
            ['Date Found', pair.found_date],
        ]
    );

    const refPanel = pair.ref_id ? buildPanel(
        pair.ref_image,
        'Ticket ID:',
        pair.ref_id,
        [
            ['Category',  pair.ref_item_type  || pair.found_item_type],
            ['Item',      pair.ref_item_type  || pair.found_item_type],
            ['Color',     pair.ref_color      || pair.found_color],
            ['Brand',     pair.ref_brand      || pair.found_brand],
            ['Date Lost', pair.ref_date_lost],
        ]
    ) : '';

    const modalHTML = `
        <div id="comparisonModal" role="dialog" aria-modal="true"
             style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;
                    justify-content:center;background:rgba(0,0,0,0.55);padding:12px;">
            <div style="background:#fff;border-radius:12px;width:100%;max-width:580px;
                        box-shadow:0 24px 64px rgba(0,0,0,0.35);
                        display:flex;flex-direction:column;max-height:92vh;overflow:hidden;">

                <!-- Header -->
                <div style="background:#8b0000;padding:15px 20px;display:flex;align-items:center;
                            justify-content:space-between;flex-shrink:0;border-radius:12px 12px 0 0;">
                    <h2 style="margin:0;color:#fff;font-size:clamp(15px,3.5vw,17px);
                               font-weight:700;font-family:inherit;">Item Details</h2>
                    <button type="button" onclick="closeComparisonModal()" aria-label="Close"
                            style="background:none;border:2px solid rgba(255,255,255,0.6);color:#fff;
                                   font-size:13px;cursor:pointer;width:28px;height:28px;border-radius:50%;
                                   display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Body -->
                <div style="overflow-y:auto;flex:1;padding:20px 20px 4px 20px;">
                    <!-- Found item panel -->
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;
                                padding:16px;margin-bottom:14px;">
                        ${foundPanel}
                    </div>
                    <!-- Lost report panel (if available) -->
                    ${pair.ref_id ? `
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;
                                padding:16px;margin-bottom:14px;">
                        ${refPanel}
                    </div>` : ''}
                </div>

                <!-- Footer -->
                <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;
                            border-top:1px solid #e5e7eb;background:#fff;flex-shrink:0;
                            border-radius:0 0 12px 12px;">
                    <button type="button" onclick="closeComparisonModal()"
                        style="padding:9px 28px;border:1px solid #9ca3af;border-radius:7px;
                               background:#fff;color:#374151;font-size:14px;font-weight:600;
                               cursor:pointer;font-family:inherit;">Cancel</button>
                    <button type="button"
                        onclick="closeComparisonModal();showClaimConfirmModal('${ticketId}')"
                        style="padding:9px 28px;border:none;border-radius:7px;background:#16a34a;
                               color:#fff;font-size:14px;font-weight:600;cursor:pointer;
                               font-family:inherit;">Claim</button>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('comparisonModal').addEventListener('click', function(e) {
        if (e.target === this) closeComparisonModal();
    });
}

function closeComparisonModal() {
    const m = document.getElementById('comparisonModal');
    if (m) m.remove();
}

// ── Claim Confirmation Modal ──
function showClaimConfirmModal(ticketId) {
    const existing = document.getElementById('claimConfirmModal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="claimConfirmModal" role="dialog" aria-modal="true"
             style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;
                    justify-content:center;background:rgba(0,0,0,0.5);padding:16px;">
            <div style="background:#fff;border-radius:16px;width:100%;max-width:400px;
                        padding:36px 32px 28px;text-align:center;position:relative;
                        box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <button type="button" id="claimConfirmClose" aria-label="Close"
                        style="position:absolute;top:14px;right:14px;background:none;
                               border:none;font-size:18px;color:#9ca3af;cursor:pointer;
                               width:28px;height:28px;display:flex;align-items:center;
                               justify-content:center;border-radius:50%;">
                    <i class="fa-solid fa-circle-xmark"></i>
                </button>
                <div id="claimConfirmBody">
                    <!-- Confirm state -->
                    <div style="width:72px;height:72px;background:#fff7ed;border-radius:50%;
                                display:flex;align-items:center;justify-content:center;
                                margin:0 auto 18px;">
                        <i class="fa-solid fa-bag-shopping" style="font-size:30px;color:#f59e0b;"></i>
                    </div>
                    <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;
                               font-family:inherit;color:#111;">Claim This Item?</h3>
                    <p style="margin:0 0 6px;font-size:14px;color:#374151;line-height:1.6;">
                        Submitting this claim will notify the Lost &amp; Found office.
                    </p>
                    <p style="margin:0 0 26px;font-size:13px;font-weight:700;color:#555;">
                        Ticket ID: ${ticketId}
                    </p>
                    <div style="display:flex;justify-content:center;gap:12px;">
                        <button type="button" id="claimConfirmCancelBtn"
                            style="padding:10px 28px;border:1px solid #9ca3af;border-radius:8px;
                                   background:#fff;color:#374151;font-size:14px;font-weight:600;
                                   cursor:pointer;font-family:inherit;">Cancel</button>
                        <button type="button" id="claimConfirmOkBtn"
                            style="padding:10px 28px;border:none;border-radius:8px;background:#8b0000;
                                   color:#fff;font-size:14px;font-weight:600;cursor:pointer;
                                   font-family:inherit;">Confirm Claim</button>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('claimConfirmModal');
    document.getElementById('claimConfirmClose').addEventListener('click', closeClaimConfirmModal);
    document.getElementById('claimConfirmCancelBtn').addEventListener('click', closeClaimConfirmModal);
    document.getElementById('claimConfirmOkBtn').addEventListener('click', function() {
        submitClaimConfirm(ticketId);
    });
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeClaimConfirmModal();
    });
}

function closeClaimConfirmModal() {
    const m = document.getElementById('claimConfirmModal');
    if (m) m.remove();
}

function submitClaimConfirm(ticketId) {
    const body   = document.getElementById('claimConfirmBody');
    const closeX = document.getElementById('claimConfirmClose');
    const modal  = document.getElementById('claimConfirmModal');

    // Prevent backdrop close while loading
    modal.onclick = null;

    // Inject spinner keyframe once
    if (!document.getElementById('claimSpinStyle')) {
        const s = document.createElement('style');
        s.id = 'claimSpinStyle';
        s.textContent = '@keyframes claimSpin{to{transform:rotate(360deg)}}';
        document.head.appendChild(s);
    }

    body.innerHTML = `
        <div style="padding:20px 0 10px;">
            <div style="width:52px;height:52px;border:5px solid #e5e7eb;border-top-color:#8b0000;
                        border-radius:50%;animation:claimSpin 0.8s linear infinite;margin:0 auto 18px;">
            </div>
            <p style="font-size:14px;color:#555;margin:0;">Submitting your claim&hellip;</p>
        </div>`;

    // POST to backend — same folder, relative URL
    fetch('SubmitClaim.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId })
    })
    .then(res => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    })
    .then(json => {
        if (json.ok || json.success) {
            showClaimResult(body, closeX, modal, true,
                'Item Claimed!',
                'Your claim has been recorded. Please present your ticket ID at the Lost &amp; Found office to collect your item.',
                ticketId);
        } else {
            showClaimResult(body, closeX, modal, false,
                'Already Submitted',
                json.message || 'This claim may have already been recorded. Please visit the office with your ticket ID.',
                ticketId);
        }
    })
    .catch(() => {
        showClaimResult(body, closeX, modal, false,
            'Connection Error',
            'We could not reach the server. Please visit the Lost &amp; Found office and present your ticket ID.',
            ticketId);
    });
}

function showClaimResult(body, closeX, modal, isSuccess, title, message, ticketId) {
    const iconBg    = isSuccess ? '#22c55e' : '#f59e0b';
    const iconClass = isSuccess ? 'fa-circle-check' : 'fa-exclamation';

    body.innerHTML = `
        <div style="width:72px;height:72px;background:${iconBg};border-radius:50%;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
            <i class="fa-solid ${iconClass}" style="font-size:32px;color:#fff;"></i>
        </div>
        <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;font-family:inherit;color:#111;">
            ${title}
        </h3>
        <p style="margin:0 0 8px;font-size:13px;color:#555;line-height:1.6;">${message}</p>
        <p style="margin:0 0 26px;font-size:13px;font-weight:700;color:#374151;">
            Ticket ID: ${ticketId}
        </p>
        <button type="button" id="claimResultDoneBtn"
            style="padding:10px 36px;border:none;border-radius:8px;background:#8b0000;
                   color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">
            Done
        </button>`;

    function onDone() {
        closeClaimConfirmModal();
        if (isSuccess) window.location.href = 'ClaimHistory.php';
    }
    document.getElementById('claimResultDoneBtn').addEventListener('click', onDone);
    if (closeX) { closeX.onclick = null; closeX.addEventListener('click', onDone); }
    modal.addEventListener('click', function(e) { if (e.target === this) onDone(); });
}

// ── FIX 5: Handle "View" buttons on matched item cards ──
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-view-matched-item]');
    if (btn) {
        e.preventDefault();
        const foundId = btn.getAttribute('data-view-matched-item');
        const refId   = btn.getAttribute('data-ref-id') || null;
        if (refId || (window.matchedPairsData || []).some(p => p.found_id === foundId)) {
            // Has a personal match pair → show comparison modal
            showComparisonModal(foundId, refId);
        } else {
            // Fallback unclaimed item → show standard item details with claim option
            showItemDetailsModal(foundId, {
                showClaimButton: true,
                onClaim: function(item) {
                    closeItemDetailsModal();
                    showClaimItemModal(item.id);
                }
            });
        }
    }
});

// ── FIX 2: Handle "View Details" in Recent Activity ──
document.addEventListener('click', function(e) {
    const link = e.target.closest('[data-view-comparison]');
    if (link) {
        e.preventDefault();
        const foundId = link.getAttribute('data-view-comparison');
        const refId   = link.getAttribute('data-ref-id') || null;
        showComparisonModal(foundId, refId);
    }
});

// ── Handle "View" buttons in My Reports table ──
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-view-report]');
    if (btn && !btn.hasAttribute('data-view-matched-item')) {
        e.preventDefault();
        var reportId = btn.getAttribute('data-view-report');
        showItemDetailsModal(reportId, { showClaimButton: false });
    }
});
</script>
<!-- Cancel Confirmation Modal -->
<div id="dashCancelModal" role="dialog" aria-modal="true" aria-hidden="true"
     style="display:none;position:fixed;inset:0;z-index:9998;align-items:center;
            justify-content:center;background:rgba(0,0,0,0.45);">
  <div style="background:#fff;border-radius:14px;width:100%;max-width:380px;
              padding:32px 28px 24px;text-align:center;position:relative;
              box-shadow:0 16px 48px rgba(0,0,0,0.25);margin:16px;">
    <!-- Icon -->
    <div style="width:64px;height:64px;background:#fff7ed;border-radius:50%;
                display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <i class="fa-solid fa-triangle-exclamation" style="font-size:28px;color:#f59e0b;"></i>
    </div>
    <h3 style="margin:0 0 10px;font-size:18px;font-weight:700;font-family:inherit;color:#111;">
      Confirm Action
    </h3>
    <p id="dashCancelMsg" style="margin:0 0 24px;font-size:14px;color:#555;line-height:1.6;">
      Are you sure you want to cancel this report?
    </p>
    <div style="display:flex;justify-content:center;gap:12px;">
      <button type="button" id="dashCancelNo"
        style="padding:9px 26px;border:1px solid #9ca3af;border-radius:7px;
               background:#fff;color:#374151;font-size:14px;font-weight:600;
               cursor:pointer;font-family:inherit;">Cancel</button>
      <button type="button" id="dashCancelYes"
        style="padding:9px 26px;border:none;border-radius:7px;background:#8b0000;
               color:#fff;font-size:14px;font-weight:600;cursor:pointer;
               font-family:inherit;">Confirm</button>
    </div>
  </div>
</div>

<script>
// ── Dashboard My Reports: cancel with AJAX + DOM removal ──────────────────────
(function () {
  var modal     = document.getElementById('dashCancelModal');
  var msgEl     = document.getElementById('dashCancelMsg');
  var btnNo     = document.getElementById('dashCancelNo');
  var btnYes    = document.getElementById('dashCancelYes');
  var pendingCb = null;

  function openModal(msg, cb) {
    msgEl.textContent = msg;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    pendingCb = cb;
  }
  function closeModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    pendingCb = null;
  }

  btnNo.addEventListener('click', closeModal);
  btnYes.addEventListener('click', function () {
    if (pendingCb) pendingCb();
    closeModal();
  });
  modal.addEventListener('click', function (e) {
    if (e.target === this) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
  });

  // ── Use CAPTURE PHASE (true) so this fires before any modal JS that might
  //    call stopPropagation/stopImmediatePropagation on the same click ─────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-cancel-report]');
    if (!btn) return;

    // Only handle Cancel buttons, not other data-cancel-report uses
    if (!btn.classList.contains('btn-cancel')) return;

    e.preventDefault();
    e.stopPropagation();

    var reportId = btn.getAttribute('data-cancel-report');
    if (!reportId) return;

    openModal('Are you sure you want to cancel this report?', function () {
      btn.disabled    = true;
      btn.textContent = 'Cancelling…';

      fetch('CancelReport.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: reportId })
      })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (json) {
        if (json.ok) {
          // Show toast first, then fade out row
          showDashToast('Report cancelled successfully.', 'success');
          var row = btn.closest('tr');
          if (row) {
            setTimeout(function () {
              row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
              row.style.opacity    = '0';
              row.style.transform  = 'translateX(16px)';
              setTimeout(function () {
                row.remove();
                var tbody = document.querySelector('.reports-table tbody');
                if (tbody && !tbody.querySelector('tr')) {
                  var cols = tbody.closest('table').querySelectorAll('thead th').length;
                  var empty = document.createElement('tr');
                  empty.innerHTML = '<td colspan="' + cols + '" class="table-empty">No reports yet.</td>';
                  tbody.appendChild(empty);
                }
              }, 360);
            }, 100);
          }
        } else {
          btn.disabled    = false;
          btn.textContent = 'Cancel';
          showDashToast(json.message || 'Could not cancel this report.', 'error');
        }
      })
      .catch(function (err) {
        btn.disabled    = false;
        btn.textContent = 'Cancel';
        showDashToast('Network error. Please try again.', 'error');
      });
    });
  }, true); // ← capture phase

  // ── Toast notification ────────────────────────────────────────────────────
  function showDashToast(msg, type) {
    var old = document.getElementById('dashCancelToast');
    if (old) old.remove();
    var bg   = type === 'success' ? '#16a34a' : '#dc2626';
    var icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
    var t    = document.createElement('div');
    t.id = 'dashCancelToast';
    t.setAttribute('style',
      'position:fixed;top:22px;right:22px;z-index:99999;' +
      'display:flex;align-items:center;gap:10px;' +
      'background:' + bg + ';color:#fff;' +
      'padding:13px 20px;border-radius:8px;font-size:14px;font-weight:600;' +
      'box-shadow:0 6px 24px rgba(0,0,0,0.22);font-family:inherit;max-width:360px;' +
      'opacity:0;transform:translateY(-10px);transition:opacity .25s,transform .25s;');
    t.innerHTML = '<i class="fa-solid ' + icon + '"></i>&nbsp;&nbsp;' + msg;
    document.body.appendChild(t);
    // Force reflow then animate in
    void t.offsetHeight;
    t.style.opacity   = '1';
    t.style.transform = 'translateY(0)';
    setTimeout(function () {
      t.style.opacity   = '0';
      t.style.transform = 'translateY(-10px)';
      setTimeout(function () { if (t.parentNode) t.remove(); }, 300);
    }, 3500);
  }
})();
</script>

<script>
/* ── Bell icon: pre-fix + SVG fill + footer link (StudentDashboard) ────────
   Inline scripts run BEFORE defer scripts (FA-JS). Swap class early so FA-JS
   sees fa-solid and renders the filled bell. Then force white fill on the
   generated SVG and its path children at DOMContentLoaded. */
(function() {
  function swapBellClass() {
    var btn = document.getElementById('notifTrigger');
    if (!btn) return;
    var ico = btn.querySelector('i');
    if (ico) { ico.classList.remove('fa-regular'); ico.classList.add('fa-solid'); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', swapBellClass);
  else swapBellClass();

  document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('notifTrigger');
    if (!btn) return;
    function applyWhite() {
      btn.querySelectorAll('svg').forEach(function(s) { s.style.fill='#fff'; s.style.color='#fff'; });
      btn.querySelectorAll('svg path').forEach(function(p) { p.style.fill='#fff'; });
    }
    applyWhite();
    new MutationObserver(applyWhite).observe(btn, { childList:true, subtree:true });
  });
})();
</script>
</body>
</html>