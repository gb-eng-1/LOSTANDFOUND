<?php
/**
 * ClaimHistory.php — Student Claim History
 * UB Lost and Found System – Student POV
 * Shows all claims filed by this student (from claims table),
 * with filter by category and filter by status.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$studentId    = (int)($_SESSION['student_id']  ?? 0);
$studentEmail = $_SESSION['student_email'] ?? '';
$studentName  = $_SESSION['student_name']  ?? '';

if (!$studentId || !$studentEmail) {
    header('Location: login.php');
    exit;
}

// Resolve student number from students table
$studentNumber = null;
try {
    $s = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
    $s->execute([$studentId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['student_id'])) $studentNumber = trim($row['student_id']);
} catch (PDOException $e) {}

// Build all user_id variants for matching REF- items owned by this student
$userIds = [strtolower($studentEmail)];
if ($studentNumber) $userIds[] = strtolower($studentNumber . '@ub.edu.ph');
$ph = implode(',', array_fill(0, count($userIds), '?'));

// Filters from GET
$filterCat    = trim($_GET['category'] ?? '');
$filterStatus = trim($_GET['status']   ?? '');

// ── Main query: claims + found item details + lost report ─────────────────
// Join: claims → found item (items f) → lost report (items r)
$sql = "
    SELECT
        c.id            AS claim_id,
        c.lost_report_id,
        c.found_item_id,
        c.claimant_id,
        c.status        AS claim_status,
        c.notes,
        c.created_at    AS claim_date,
        c.updated_at    AS claim_updated,

        -- found item columns
        f.item_type,
        f.color,
        f.brand,
        f.found_at,
        f.found_by,
        f.storage_location,
        f.date_encoded,
        f.item_description AS found_desc,
        f.image_data,

        -- lost report ticket
        r.id               AS ref_ticket_id,
        r.item_description AS ref_desc,

        -- student name from students table
        st.name AS student_full_name,
        st.student_id AS student_number
    FROM claims c
    LEFT JOIN items  f  ON f.id  = c.found_item_id
    LEFT JOIN items  r  ON r.id  = c.lost_report_id
    LEFT JOIN students st ON LOWER(TRIM(st.email)) = LOWER(TRIM(c.claimant_id))
    WHERE LOWER(TRIM(c.claimant_id)) IN ($ph)
";
$params = $userIds;

if ($filterCat    !== '') { $sql .= ' AND f.item_type = ?'; $params[] = $filterCat; }
if ($filterStatus === 'Pending') {
    $sql .= ' AND c.status = ?'; $params[] = 'Pending';
} elseif ($filterStatus === 'Claimed') {
    // Claimed = item status is Claimed OR claim was Approved/Resolved
    $sql .= " AND (f.status = 'Claimed' OR c.status IN ('Approved','Resolved'))";
}

$sql .= ' ORDER BY c.created_at DESC';

$claims = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $claims = [];
}

// ── Categories for filter dropdown ────────────────────────────────────────
// Fixed list matching the system's category config; the DB query only returned
// categories already present in the student's claims (often empty).
$allCategories = [
    'Electronics & Gadgets',
    'Document & Identification',
    'Personal Belongings',
    'Apparel & Accessories',
    'Miscellaneous',
    'ID & Nameplate',
];

// ── Statuses for filter dropdown ─────────────────────────────────────────
// 'Pending' = claim awaiting decision; 'Claimed' = item successfully claimed
$allStatuses = ['Pending', 'Claimed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Claim History – UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- FontAwesome 6 Free — CSS + JS both required for icon rendering -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
  <style>.fa-solid,.fa-regular,.fa-brands{display:inline-block!important;font-style:normal!important;}</style>

  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="StudentDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ItemDetailsModal.css?v=<?php echo time(); ?>">

  <style>
    /* ── Nav colours ── */
    .nav-item.active .nav-item-icon,
    .nav-item.active .nav-item-icon i,
    .nav-item.active .nav-item-label { color: #ffffff !important; }
    .nav-menu .nav-item:not(.active) .nav-item-icon,
    .nav-menu .nav-item:not(.active) .nav-item-icon i,
    .nav-menu .nav-item:not(.active) .nav-item-label { color: #8b0000 !important; }

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

    /* ── Page wrap ── */
    .ch-wrap {
      padding: 28px 32px 40px;
      flex: 1; overflow-y: auto; overflow-x: hidden;
    }
    .ch-title {
      font-size: 26px; font-weight: 700; color: #111; margin-bottom: 20px;
    }

    /* ── Filter row ── */
    .ch-filters {
      display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;
      align-items: center;
    }
    .ch-filter-select {
      padding: 8px 32px 8px 12px;
      border: 1px solid #d1d5db; border-radius: 7px;
      font-size: 13px; font-family: inherit; color: #374151;
      background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 10px center;
      appearance: none; cursor: pointer;
      transition: border-color 0.15s;
    }
    .ch-filter-select:focus { outline: none; border-color: #8b0000; }
    .ch-filter-btn {
      padding: 8px 18px; border-radius: 7px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: inherit; border: none;
      background: #8b0000; color: #fff; transition: background 0.15s;
    }
    .ch-filter-btn:hover { background: #6d0000; }
    .ch-filter-reset {
      padding: 8px 14px; border-radius: 7px; font-size: 13px; font-weight: 500;
      cursor: pointer; font-family: inherit;
      background: #fff; color: #6b7280; border: 1px solid #d1d5db;
      text-decoration: none; transition: background 0.15s;
    }
    .ch-filter-reset:hover { background: #f3f4f6; color: #374151; text-decoration: none; }

    /* ── Table wrapper ── */
    .ch-table-wrap {
      overflow-x: auto; border-radius: 10px;
      border: 1px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .ch-table {
      width: 100%; min-width: 720px; border-collapse: collapse;
      font-size: 13px; font-family: inherit; background: #fff;
    }
    .ch-table thead tr { background: #f9fafb; }
    .ch-table th {
      padding: 12px 14px; text-align: left; font-weight: 600;
      color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .ch-table th:last-child { text-align: center; }
    .ch-table td {
      padding: 12px 14px; color: #374151; border-bottom: 1px solid #f0f0f0; white-space: nowrap;
    }
    .ch-table td.cell-center { text-align: center; }
    .ch-table tbody tr:last-child td { border-bottom: none; }
    .ch-table tbody tr:nth-child(odd)  { background: #fff; }
    .ch-table tbody tr:nth-child(even) { background: #fafafa; }
    .ch-table tbody tr:hover td { background: #f3f4f6 !important; }
    .ch-table-empty {
      text-align: center; color: #9ca3af; padding: 36px; font-style: italic;
    }

    /* ── Status badges ── */
    .badge {
      display: inline-block; padding: 3px 11px; border-radius: 20px;
      font-size: 12px; font-weight: 600; white-space: nowrap;
    }
    .badge-pending  { background: #fef9c3; color: #a16207; }
    .badge-claimed  { background: #dcfce7; color: #15803d; }
    .badge-approved { background: #dcfce7; color: #15803d; }
    .badge-rejected { background: #fee2e2; color: #b91c1c; }
    .badge-resolved { background: #dbeafe; color: #1d4ed8; }

    /* ── View button ── */
    .ch-btn-view {
      display: inline-block; padding: 6px 18px;
      background: #007bff; color: #fff; border: none; border-radius: 6px;
      font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
      transition: background 0.15s;
    }
    .ch-btn-view:hover { background: #0069d9; }

    /* ════════════════════════════════════════════════
       Claim Details Modal
       Layout mirrors ItemDetailsModal: left col (image +
       IDs + claimant section) + right col (general info)
       ════════════════════════════════════════════════ */
    .chm-overlay {
      display: none; position: fixed; inset: 0; z-index: 9990;
      align-items: center; justify-content: center;
      background: rgba(0,0,0,0.55); padding: 12px;
    }
    .chm-overlay.chm-open { display: flex; }
    .chm-dialog {
      background: #fff; border-radius: 14px; width: 100%; max-width: 680px;
      max-height: 92vh; display: flex; flex-direction: column;
      box-shadow: 0 24px 64px rgba(0,0,0,0.32); overflow: hidden;
    }
    .chm-header {
      background: #8b0000; padding: 15px 20px;
      display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
    }
    .chm-header h2 { margin: 0; color: #fff; font-size: 17px; font-weight: 700; font-family: inherit; }
    .chm-close {
      background: none; border: 2px solid rgba(255,255,255,0.6); color: #fff;
      font-size: 13px; cursor: pointer; width: 28px; height: 28px;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      padding: 0;
    }
    .chm-close:hover { border-color: #fff; background: rgba(255,255,255,0.1); }

    /* Body — two column layout */
    .chm-body {
      display: flex; overflow-y: auto; flex: 1;
      padding: 20px; gap: 20px;
    }

    /* Left column */
    .chm-left {
      width: 200px; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 10px;
    }
    .chm-img-wrap {
      width: 180px; height: 140px; border-radius: 10px; overflow: hidden;
      border: 1px solid #e5e7eb; background: #f3f4f6;
      display: flex; align-items: center; justify-content: center;
    }
    .chm-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .chm-img-placeholder { display: flex; flex-direction: column; align-items: center; gap: 6px; color: #9ca3af; font-size: 13px; }
    .chm-img-placeholder i { font-size: 28px; }

    /* IDs below image */
    .chm-ids { text-align: center; font-size: 13px; font-weight: 600; color: #374151; line-height: 1.8; }

    /* Separator between IDs and claimant info */
    .chm-divider { width: 100%; border: none; border-top: 1px solid #e5e7eb; margin: 4px 0; }

    /* Claimant Info section */
    .chm-claimant-title {
      font-size: 14px; font-weight: 700; color: #111; align-self: flex-start; margin-bottom: 2px;
    }
    .chm-claimant-fields { width: 100%; display: flex; flex-direction: column; gap: 10px; }
    .chm-claimant-field label {
      display: block; font-size: 12px; color: #6b7280; margin-bottom: 3px;
    }
    .chm-claimant-input {
      width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 6px;
      font-size: 13px; font-family: inherit; color: #374151; background: #fff;
      box-sizing: border-box;
    }

    /* Right column */
    .chm-right {
      flex: 1; min-width: 0;
    }
    .chm-info-title {
      font-size: 15px; font-weight: 700; color: #111; text-align: center; margin: 0 0 10px;
    }
    .chm-info-divider { border: none; border-top: 1px solid #d1d5db; margin: 0 0 12px; }
    .chm-info-list { display: flex; flex-direction: column; gap: 0; }
    .chm-info-row {
      display: flex; justify-content: space-between; align-items: flex-start;
      padding: 7px 0; border-bottom: 1px solid #f0f0f0;
    }
    .chm-info-row:last-child { border-bottom: none; }
    .chm-info-label { font-size: 13px; color: #6b7280; flex-shrink: 0; padding-right: 10px; }
    .chm-info-value { font-size: 13px; font-weight: 700; color: #111; text-align: right; word-break: break-word; max-width: 55%; }

    /* Footer */
    .chm-footer {
      padding: 12px 20px; display: flex; justify-content: flex-end;
      border-top: 1px solid #e5e7eb; flex-shrink: 0;
    }
    .chm-status-btn {
      padding: 9px 28px; border: none; border-radius: 7px;
      font-size: 14px; font-weight: 700; font-family: inherit; cursor: default;
    }
    .chm-status-pending  { background: #f59e0b; color: #fff; }
    .chm-status-approved { background: #16a34a; color: #fff; }
    .chm-status-rejected { background: #dc2626; color: #fff; }
    .chm-status-resolved { background: #2563eb; color: #fff; }

    /* ── Responsive ── */
    @media (max-width: 560px) {
      .chm-body { flex-direction: column; }
      .chm-left { width: 100%; }
    }
  </style>
</head>
<body data-student-email="<?= htmlspecialchars($studentEmail) ?>">
<div class="layout">

  <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
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
        <a class="nav-item" href="StudentDashboard.php">
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
        <a class="nav-item active" href="ClaimHistory.php">
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

  <!-- ── Main ──────────────────────────────────────────────────────────── -->
  <main class="main">
    <!-- Topbar -->
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-left">
        <form class="search-form" id="searchForm" autocomplete="off">
          <input id="adminSearchInput" type="text" class="search-input" placeholder="Search items by name or barcode…" autocomplete="off">
          <div id="searchDropdown" class="search-dropdown"></div>
          <button id="adminSearchClear" class="search-clear" type="button" title="Clear" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
          <button class="search-submit" type="submit" title="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>
      <div class="topbar-right">
        <?php require_once __DIR__ . '/includes/notifications_dropdown.php'; ?>
        <div class="admin-dropdown" id="adminDropdown">
          <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger"
                  aria-expanded="false" aria-haspopup="true" aria-label="Student menu">
            <i class="fa-regular fa-user"></i>
            <span class="admin-name"><?= htmlspecialchars($studentName ?: $studentEmail) ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size:11px;"></i>
          </button>
          <div class="admin-dropdown-menu" role="menu">
            <a href="logout.php" role="menuitem" class="admin-dropdown-item">
              <i class="fa-solid fa-right-from-bracket"></i> Log Out
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Page content -->
    <div class="ch-wrap">
      <h1 class="ch-title">Claim History</h1>

      <!-- ── Filters ──────────────────────────────────────────────────── -->
      <form method="get" action="ClaimHistory.php" class="ch-filters">
        <!-- Filter by Category -->
        <select name="category" class="ch-filter-select" aria-label="Filter by Category">
          <option value="">Filter by Category</option>
          <?php foreach ($allCategories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"
              <?= ($filterCat === $cat ? 'selected' : '') ?>>
              <?= htmlspecialchars($cat) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Filter by Status -->
        <select name="status" class="ch-filter-select" aria-label="Filter by Status">
          <option value="">Filter by Status</option>
          <?php foreach ($allStatuses as $st): ?>
            <option value="<?= htmlspecialchars($st) ?>"
              <?= ($filterStatus === $st ? 'selected' : '') ?>>
              <?= htmlspecialchars($st) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="ch-filter-btn">
          <i class="fa-solid fa-filter"></i> Apply
        </button>
        <?php if ($filterCat !== '' || $filterStatus !== ''): ?>
          <a href="ClaimHistory.php" class="ch-filter-reset">
            <i class="fa-solid fa-xmark"></i> Clear
          </a>
        <?php endif; ?>
      </form>

      <!-- ── Claims table ──────────────────────────────────────────────── -->
      <div class="ch-table-wrap">
        <table class="ch-table">
          <thead>
            <tr>
              <th>Barcode ID</th>
              <th>Ticket ID</th>
              <th>Category</th>
              <th>Found At</th>
              <th>Date Claimed</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($claims)): ?>
              <tr><td colspan="7" class="ch-table-empty">No claim history yet.</td></tr>
            <?php else: ?>
              <?php foreach ($claims as $cl):
                $statusLower = strtolower($cl['claim_status'] ?? 'pending');
                $badgeClass  = 'badge-' . $statusLower;
                $claimDate   = $cl['claim_date'] ? date('Y-m-d', strtotime($cl['claim_date'])) : '-';
                $foundAt     = $cl['found_at'] ?? '-';
                $category    = $cl['item_type'] ?? '-';
                $barcodeId   = $cl['found_item_id'] ?? '-';
                $ticketId    = $cl['ref_ticket_id'] ?? $cl['lost_report_id'] ?? '-';
              ?>
              <tr>
                <td><?= htmlspecialchars($barcodeId) ?></td>
                <td><?= htmlspecialchars($ticketId) ?></td>
                <td><?= htmlspecialchars($category) ?></td>
                <td><?= htmlspecialchars($foundAt) ?></td>
                <td><?= htmlspecialchars($claimDate) ?></td>
                <td><span class="badge <?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars(ucfirst($cl['claim_status'] ?? 'Pending')) ?></span></td>
                <td class="cell-center">
                  <button type="button" class="ch-btn-view"
                    data-claim-id="<?= htmlspecialchars((string)$cl['claim_id']) ?>"
                    data-barcode-id="<?= htmlspecialchars($barcodeId) ?>"
                    data-ticket-id="<?= htmlspecialchars($ticketId) ?>"
                    data-category="<?= htmlspecialchars($category) ?>"
                    data-item="<?= htmlspecialchars(htmlspecialchars_decode($cl['found_desc'] ?? '')) ?>"
                    data-color="<?= htmlspecialchars($cl['color'] ?? '') ?>"
                    data-brand="<?= htmlspecialchars($cl['brand'] ?? '') ?>"
                    data-found-at="<?= htmlspecialchars($foundAt) ?>"
                    data-found-by="<?= htmlspecialchars($cl['found_by'] ?? '') ?>"
                    data-storage="<?= htmlspecialchars($cl['storage_location'] ?? '') ?>"
                    data-date-found="<?= htmlspecialchars($cl['date_encoded'] ? date('Y-m-d', strtotime($cl['date_encoded'])) : '') ?>"
                    data-status="<?= htmlspecialchars(ucfirst($cl['claim_status'] ?? 'Pending')) ?>"
                    data-image="<?= htmlspecialchars($cl['image_data'] ?? '') ?>"
                    data-claimant-name="<?= htmlspecialchars($cl['student_full_name'] ?? $studentName) ?>"
                    data-claimant-contact="<?= htmlspecialchars(self_extract_contact($cl['ref_desc'] ?? $cl['found_desc'] ?? '', $studentEmail)) ?>"
                    data-claimant-dept="<?= htmlspecialchars(self_extract_department($cl['ref_desc'] ?? '')) ?>"
                    data-claim-date="<?= htmlspecialchars($claimDate) ?>"
                    data-encoded-by="<?= htmlspecialchars($cl['found_by'] ?? '') ?>"
                  >View</button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div><!-- /.ch-wrap -->
  </main>
</div>

<!-- ══ Claim Details Modal ═══════════════════════════════════════════════════ -->
<div id="chmOverlay" class="chm-overlay" role="dialog" aria-modal="true" aria-label="Item Details">
  <div class="chm-dialog">
    <div class="chm-header">
      <h2>Item Details</h2>
      <button type="button" class="chm-close" id="chmClose" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="chm-body">
      <!-- Left -->
      <div class="chm-left">
        <div class="chm-img-wrap" id="chmImgWrap">
          <div class="chm-img-placeholder">
            <i class="fa-regular fa-image"></i>
            <span>No image</span>
          </div>
        </div>
        <div class="chm-ids" id="chmIds"></div>
        <hr class="chm-divider">
        <div class="chm-claimant-title">Claimant's Information</div>
        <hr class="chm-divider" style="margin-top:2px;">
        <div class="chm-claimant-fields">
          <div class="chm-claimant-field">
            <label>Name:</label>
            <input type="text" class="chm-claimant-input" id="chmClaimantName" readonly>
          </div>
          <div class="chm-claimant-field">
            <label>Contact Number:</label>
            <input type="text" class="chm-claimant-input" id="chmClaimantContact" readonly>
          </div>
          <div class="chm-claimant-field">
            <label>Department:</label>
            <input type="text" class="chm-claimant-input" id="chmClaimantDept" readonly>
          </div>
          <div class="chm-claimant-field">
            <label>Date of Accomplishment:</label>
            <input type="text" class="chm-claimant-input" id="chmClaimDate" readonly>
          </div>
        </div>
      </div>

      <!-- Right -->
      <div class="chm-right">
        <h3 class="chm-info-title">General Information</h3>
        <hr class="chm-info-divider">
        <div class="chm-info-list" id="chmInfoList"></div>
      </div>
    </div>
    <div class="chm-footer">
      <button type="button" class="chm-status-btn" id="chmStatusBtn">Pending</button>
    </div>
  </div>
</div>

<?php
// ── Helper functions ──────────────────────────────────────────────────────
function self_extract_contact(string $desc, string $fallback = ''): string {
    if (preg_match('/Contact:\s*(.+?)(?:\n|$)/m', $desc, $m)) return trim($m[1]);
    return $fallback;
}
function self_extract_department(string $desc): string {
    if (preg_match('/Department:\s*(.+?)(?:\n|$)/m', $desc, $m)) return trim($m[1]);
    return '';
}
?>

<script>
(function () {
  // ── Admin dropdown ───────────────────────────────────────────────────────
  var dd  = document.getElementById('adminDropdown');
  var trg = dd && dd.querySelector('.admin-dropdown-trigger');
  if (dd && trg) {
    trg.addEventListener('click', function (e) {
      e.stopPropagation();
      dd.classList.toggle('open');
      trg.setAttribute('aria-expanded', dd.classList.contains('open'));
    });
    document.addEventListener('click', function () {
      dd.classList.remove('open');
      if (trg) trg.setAttribute('aria-expanded', 'false');
    });
  }
})();

(function () {
  // ── Notifications ────────────────────────────────────────────────────────
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

(function () {
  // ── Claim Details Modal ──────────────────────────────────────────────────
  var overlay      = document.getElementById('chmOverlay');
  var closeBtn     = document.getElementById('chmClose');
  var imgWrap      = document.getElementById('chmImgWrap');
  var idsEl        = document.getElementById('chmIds');
  var infoList     = document.getElementById('chmInfoList');
  var nameInput    = document.getElementById('chmClaimantName');
  var contactInput = document.getElementById('chmClaimantContact');
  var deptInput    = document.getElementById('chmClaimantDept');
  var dateInput    = document.getElementById('chmClaimDate');
  var statusBtn    = document.getElementById('chmStatusBtn');

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
    });
  }

  function makeRow(label, value) {
    return '<div class="chm-info-row">' +
      '<span class="chm-info-label">' + esc(label) + ':</span>' +
      '<span class="chm-info-value">'  + esc(value || '-') + '</span>' +
    '</div>';
  }

  function openModal(btn) {
    var barcodeId  = btn.getAttribute('data-barcode-id')  || '';
    var ticketId   = btn.getAttribute('data-ticket-id')   || '';
    var category   = btn.getAttribute('data-category')    || '';
    var color      = btn.getAttribute('data-color')       || '';
    var brand      = btn.getAttribute('data-brand')       || '';
    var foundAt    = btn.getAttribute('data-found-at')    || '';
    var foundBy    = btn.getAttribute('data-found-by')    || '';
    var storage    = btn.getAttribute('data-storage')     || '';
    var dateFound  = btn.getAttribute('data-date-found')  || '';
    var encodedBy  = btn.getAttribute('data-encoded-by')  || '';
    var status     = btn.getAttribute('data-status')      || 'Pending';
    var imageRaw   = btn.getAttribute('data-image')       || '';
    var claimName  = btn.getAttribute('data-claimant-name')    || '';
    var claimCont  = btn.getAttribute('data-claimant-contact') || '';
    var claimDept  = btn.getAttribute('data-claimant-dept')    || '';
    var claimDate  = btn.getAttribute('data-claim-date')       || '';

    // --- parse item description for the "Item" field ---
    var descRaw = btn.getAttribute('data-item') || '';
    var itemName = '';
    var mainDesc = descRaw;
    var m = descRaw.match(/Item Type:\s*(.+?)(?:\n|$)/);
    if (m) { itemName = m[1].trim(); }
    mainDesc = descRaw
      .replace(/Full Name:[^\n]*\n?/g, '')
      .replace(/Student Number:[^\n]*\n?/g, '')
      .replace(/Student ID:[^\n]*\n?/g, '')
      .replace(/Item Type:[^\n]*\n?/g, '')
      .replace(/Item Name:[^\n]*\n?/g, '')
      .replace(/Contact:[^\n]*\n?/g, '')
      .replace(/Department:[^\n]*\n?/g, '')
      .replace(/Full Name:[^\n]*\n?/g, '')
      .replace(/Name:[^\n]*\n?/g, '')
      .trim();
    if (!itemName) itemName = category;

    // --- image ---
    if (imageRaw) {
      var src = imageRaw.startsWith('data:') ? imageRaw : 'data:image/jpeg;base64,' + imageRaw;
      imgWrap.innerHTML = '<img src="' + src + '" alt="Item">';
    } else {
      imgWrap.innerHTML =
        '<div class="chm-img-placeholder">' +
        '<i class="fa-regular fa-image"></i><span>No image</span></div>';
    }

    // --- IDs ---
    idsEl.innerHTML =
      (barcodeId ? 'Barcode ID: ' + esc(barcodeId) + '<br>' : '') +
      (ticketId  ? 'Ticket ID: '  + esc(ticketId)            : '');

    // --- claimant info ---
    nameInput.value    = claimName;
    contactInput.value = claimCont;
    if (deptInput) deptInput.value = claimDept;
    dateInput.value    = claimDate;

    // --- general info rows ---
    var rows = [
      ['Category',         category],
      ['Item',             itemName],
      ['Color',            color],
      ['Brand',            brand],
      ['Item Description', mainDesc],
      ['Storage Location', storage],
      ['Found At',         foundAt],
      ['Found By',         foundBy],
      ['Encoded By',       encodedBy],
      ['Date Found',       dateFound],
    ];
    infoList.innerHTML = rows.map(function (r) { return makeRow(r[0], r[1]); }).join('');

    // --- status button ---
    var sl = status.toLowerCase();
    statusBtn.textContent = status;
    statusBtn.className   = 'chm-status-btn chm-status-' + sl;

    overlay.classList.add('chm-open');
  }

  function closeModal() { overlay.classList.remove('chm-open'); }

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('chm-open')) closeModal();
  });

  // ── View button delegation ────────────────────────────────────────────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-claim-id]');
    if (btn) { e.preventDefault(); openModal(btn); }
  });
})();
</script>
<script src="ItemDetailsModal.js?v=<?php echo time(); ?>"></script>
<script>
/* ── Universal search bar ─────────────────────────────────────────────────── */
(function () {
  var input    = document.getElementById('adminSearchInput');
  var clearBtn = document.getElementById('adminSearchClear');
  var dropdown = document.getElementById('searchDropdown');
  var form     = document.getElementById('searchForm');
  if (!input || !dropdown) return;

  var timer    = null;
  var lastQ    = '';

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

  function search(q) {
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
    timer = setTimeout(function(){ search(v); }, 220);
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