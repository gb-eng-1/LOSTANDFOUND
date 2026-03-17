<?php
// ItemMatchedAdmin.php - Matched Items (same data as other admin pages, 6-month retention)
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$itemCategories = require dirname(__DIR__) . '/config/categories.php';
$today = date('Y-m-d');
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// 1. Found items (UB-xxx): For Verification + Unclaimed Items
$forVerification = get_items($pdo, 'For Verification');
$unclaimed = get_items($pdo, 'Unclaimed Items');
$allInternal = array_merge($forVerification, $unclaimed);
$allInternal = array_values(array_filter($allInternal, function ($it) {
    $id = $it['id'] ?? '';
    return $id === '' || strpos($id, 'REF-') !== 0;
}));

// 2. Reports (REF-xxx): lost item reports from Reports page
$reportsStmt = $pdo->query("SELECT id, user_id, item_type, color, brand, found_at, found_by, date_encoded, date_lost, item_description, storage_location, image_data, status, created_at FROM items WHERE id LIKE 'REF-%' ORDER BY created_at DESC");
$reports = [];
while ($row = $reportsStmt->fetch(PDO::FETCH_ASSOC)) {
    $desc = $row['item_description'] ?? '';
    $itemTypeLabel = '';
    if (preg_match('/^Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) $itemTypeLabel = trim($m[1]);
    if (!$itemTypeLabel) $itemTypeLabel = $row['item_type'] ?? '';
    $dateEncoded = $row['date_encoded'] ?? null;
    $retentionEnd = $dateEncoded ? date('Y-m-d', strtotime($dateEncoded . ' +1 year')) : '—';
    $isOverdue = $retentionEnd && $retentionEnd !== '—' && $retentionEnd < $today;
    $isExpiring = !$isOverdue && $retentionEnd && $retentionEnd !== '—' && $retentionEnd <= date('Y-m-d', strtotime('+30 days'));
    $reports[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'item_type' => $row['item_type'],
        'item_type_label' => $itemTypeLabel,
        'color' => $row['color'],
        'brand' => $row['brand'],
        'found_at' => $row['found_at'],
        'found_by' => $row['found_by'],
        'dateEncoded' => $row['date_encoded'],
        'date_lost' => $row['date_lost'],
        'item_description' => $desc,
        'storage_location' => $row['storage_location'],
        'imageDataUrl' => $row['image_data'],
        'status' => $row['status'],
        'retention_end' => $retentionEnd,
        '_is_expiring' => $isExpiring,
        '_is_overdue' => $isOverdue,
        '_is_report' => true,
    ];
}

$matchedItems = [];
foreach ($allInternal as $it) {
    $dateEncoded  = $it['dateEncoded'] ?? '';
    $retentionEnd = $dateEncoded ? date('Y-m-d', strtotime($dateEncoded . ' +2 years')) : '';
    $isOverdue    = $retentionEnd && $retentionEnd < $today;
    $isExpiring   = !$isOverdue && $retentionEnd && $retentionEnd <= date('Y-m-d', strtotime('+30 days'));
    $matchedItems[] = array_merge($it, [
        'retention_end' => $retentionEnd,
        '_is_expiring'  => $isExpiring,
        '_is_overdue'   => $isOverdue,
        '_is_report'    => false,
    ]);
}
// Append reports to All Items
$matchedItems = array_merge($matchedItems, $reports);

$overdueCount = count(array_filter($matchedItems, fn($i) => !empty($i['_is_overdue'])));

// Guest Items = items categorised as 'ID & Nameplate'
$guestItems = [];
try {
    $gStmt = $pdo->prepare(
        "SELECT *, date_encoded AS dateEncoded, image_data AS imageDataUrl,
                item_description AS itemDescription
         FROM items
         WHERE item_type = 'ID & Nameplate'
           AND id NOT LIKE 'REF-%'
         ORDER BY created_at DESC"
    );
    $gStmt->execute();
    $guestItems = $gStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('ItemMatchedAdmin guestItems: ' . $e->getMessage());
}

// Build expiringItems list (approaching retention within 30 days)
$in30 = date('Y-m-d', strtotime('+30 days'));
$expiringItems = [];
foreach ($matchedItems as $it) {
    $retEnd = $it['retention_end'] ?? '';
    if ($retEnd && $retEnd !== '—' && $retEnd >= $today && $retEnd <= $in30) {
        $expiringItems[] = array_merge($it, ['_retEnd' => $retEnd]);
    }
}
foreach ($guestItems as $it) {
    $dateEnc = $it['dateEncoded'] ?? $it['date_encoded'] ?? null;
    $retEnd  = $dateEnc ? date('Y-m-d', strtotime($dateEnc . ' +1 year')) : '';
    if ($retEnd && $retEnd >= $today && $retEnd <= $in30) {
        $expiringItems[] = array_merge($it, ['_retEnd' => $retEnd]);
    }
}

// Items resolved this month: from activity_log if available, else 0
$itemsResolvedThisMonth = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE action = 'claimed' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
    $itemsResolvedThisMonth = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    $itemsResolvedThisMonth = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UB Lost and Found System - Matching</title>
    <link rel="stylesheet" href="AdminDashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="ItemMatchedAdmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/photo-picker.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
    <style>
        /* Action button overrides */
        .found-action-cell .found-btn-view {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 6px 16px !important;
            border-radius: 6px !important;
            background-color: #8b0000 !important;
            color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            font-family: inherit !important;
            border: none !important;
            cursor: pointer !important;
        }
        .found-action-cell .found-btn-view:hover { background-color: #6d0000 !important; }
        .found-action-cell .found-btn-claim {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 6px 16px !important;
            border-radius: 6px !important;
            background-color: #15803d !important;
            color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            font-family: inherit !important;
            border: none !important;
            cursor: pointer !important;
        }
        .found-action-cell .found-btn-claim:hover { background-color: #166534 !important; }
        .found-action-cell .found-btn-claim.btn-claim-expired {
            background-color: #9ca3af !important;
            cursor: not-allowed !important;
        }
        .found-action-cell .found-btn-claim:disabled {
            opacity: 0.7 !important;
            cursor: not-allowed !important;
        }
    </style>
<style>
/* Sidebar mobile: cancel min-height so no blank gap below nav */
@media (max-width: 900px) {
  .sidebar  { min-height: 0 !important; height: auto !important; }
  .nav-menu { flex: none !important; }
}

/* ── Unified tab + filter header row (matches FoundAdmin) ── */
.found-tabs-actions-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}
.found-tabs {
  display: flex;
  align-items: center;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 3px;
  flex-shrink: 0;
}
.found-tab-text {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
  user-select: none;
}
.found-tab-text.found-tab-active {
  background: #8b0000;
  color: #fff;
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(139,0,0,0.25);
}
.found-filter-select {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 7px;
  font-family: Poppins, sans-serif;
  font-size: 12px;
  color: #374151;
  background: #fff;
  cursor: pointer;
  min-width: 130px;
}

/* ── Expiry / Retention popup (matches FoundAdmin) ────────── */
.expiry-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.42); z-index: 1200;
  align-items: center; justify-content: center;
}
.expiry-overlay.open { display: flex; }
.expiry-popup {
  background: #fff; border-radius: 14px;
  padding: 24px 26px 28px; width: min(720px, 96vw);
  max-height: 88vh; overflow-y: auto;
  box-shadow: 0 12px 40px rgba(0,0,0,0.2);
}
.expiry-popup-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.expiry-popup-title { font-size:16px; font-weight:700; color:#111; }
.expiry-popup-close {
  background:none; border:none; cursor:pointer;
  font-size:18px; color:#6b7280; padding:2px 7px;
  border-radius:5px; line-height:1; transition:background 0.15s;
}
.expiry-popup-close:hover { background:#f3f4f6; color:#111; }
.expiry-cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:14px; }
.expiry-card {
  background:#fff; border:1px solid #e0e0e0; border-radius:8px;
  padding:16px; box-shadow:0 2px 5px rgba(0,0,0,0.05);
  display:flex; flex-direction:column; gap:8px;
}
.expiry-card-title { margin:0; font-size:15px; font-weight:700; color:#111; display:flex; align-items:center; gap:9px; }
.expiry-card-title i { font-size:16px; color:#374151; }
.expiry-card-meta { display:flex; align-items:center; gap:9px; color:#555; font-size:13px; }
.expiry-card-meta i { width:16px; text-align:center; }
.expiry-card-badge {
  display:inline-block; background:#fff3cd; color:#856404;
  border:1px solid #ffc107; font-size:10px; font-weight:700;
  padding:2px 8px; border-radius:10px; align-self:flex-start;
}
.expiry-card-footer { display:flex; justify-content:flex-end; margin-top:4px; }
.btn-dispose-item {
  background:#8b0000; color:#fff; border:none;
  padding:8px 20px; border-radius:6px; font-size:13px;
  font-weight:600; cursor:pointer; font-family:Poppins,sans-serif;
  transition:opacity 0.15s;
}
.btn-dispose-item:hover { opacity:0.85; }
.btn-dispose-item:disabled { opacity:0.5; cursor:not-allowed; }
.expiry-empty-msg { color:#9ca3af; font-size:13px; font-style:italic; }
.found-header-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

/* ── Tab icon sizing + button UI consistency ──────────────── */
.found-tab-text i { font-size: 11px; }
.found-action-cell .found-btn-view,
.found-action-cell .found-btn-claim,
.found-action-cell .ima-guest-view-btn {
    min-width: 64px;
    height: 32px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 7px;
    letter-spacing: 0.01em;
}

/* ── Recent Action Banner ─────────────────────────────────── */
.recent-action-banner {
    display: flex; align-items: flex-start; gap: 12px;
    background: #f0fdf4; border: 1px solid #86efac;
    border-radius: 8px; padding: 11px 14px;
    margin-bottom: 10px;
}
.rab-icon { color: #16a34a; font-size: 17px; flex-shrink: 0; margin-top: 1px; }
.rab-text { flex: 1; font-size: 13px; color: #166534; line-height: 1.5; }
.rab-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.rab-mark-read {
    font-size: 11px; font-weight: 600; color: #166534;
    background: none; border: 1px solid #86efac;
    border-radius: 5px; padding: 4px 10px; cursor: pointer;
    font-family: Poppins, sans-serif; transition: background 0.15s;
    white-space: nowrap;
}
.rab-mark-read:hover { background: #dcfce7; }
.rab-dismiss {
    background: none; border: none; cursor: pointer;
    color: #6b7280; font-size: 14px; padding: 3px 7px;
    border-radius: 4px; line-height: 1; transition: background 0.15s, color 0.15s;
}
.rab-dismiss:hover { background: #dcfce7; color: #166534; }
</style>
</head>
<body class="item-matched-page">
<div class="layout">
    <!-- Sidebar -->
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
                <a class="nav-item" href="AdminDashboard.php">
                    <div class="nav-item-icon"><i class="fa-solid fa-house"></i></div>
                    <div class="nav-item-label">Dashboard</div>
                </a>
            </li>
            <li>
                <a class="nav-item" href="FoundAdmin.php">
                    <div class="nav-item-icon"><i class="fa-solid fa-folder"></i></div>
                    <div class="nav-item-label">Found</div>
                </a>
            </li>
            <li>
                <a class="nav-item" href="AdminReports.php">
                    <div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div>
                    <div class="nav-item-label">Reports</div>
                </a>
            </li>
            <li>
                <a class="nav-item active" href="ItemMatchedAdmin.php">
                    <div class="nav-item-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="nav-item-label">Matching</div>
                </a>
            </li>
            <li>
                <a class="nav-item" href="HistoryAdmin.php">
                    <div class="nav-item-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="nav-item-label">History</div>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main content -->
    <main class="main">
        <div class="topbar topbar-maroon">
            <div class="topbar-search-wrap topbar-search-left">
                <form class="search-form" action="FoundAdmin.php" method="get">
                    <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search" autocomplete="off">
                    <div id="searchDropdown" class="search-dropdown"></div>
                    <button id="adminSearchClear" class="search-clear" type="button" title="Clear" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
                    <button class="search-submit" type="submit" title="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>
            <div class="topbar-right">
                <?php include __DIR__ . '/includes/notifications_dropdown.php'; ?>
                <div class="admin-dropdown" id="adminDropdown">
                    <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger" aria-expanded="false" aria-haspopup="true" aria-label="Admin menu">
                        <i class="fa-regular fa-user"></i>
                        <span class="admin-name"><?php echo htmlspecialchars($adminName); ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 11px;"></i>
                    </button>
                    <div class="admin-dropdown-menu" role="menu">
                        <a href="logout.php" role="menuitem" class="admin-dropdown-item"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content-wrap">
            <div class="content-row content-row-single">
                <section class="content-left">

                    <div class="found-header-row">
                    <h2 class="page-title">Matched Items</h2>

                    <!-- Recent action banner (shown after a claim is confirmed) -->
                    <div id="recentActionBanner" class="recent-action-banner" style="display:none;" role="status" aria-live="polite">
                        <div class="rab-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="rab-text" id="recentActionText"></div>
                        <div class="rab-actions">
                            <button type="button" class="rab-mark-read" id="rabMarkRead">Mark as Read</button>
                            <button type="button" class="rab-dismiss" id="rabDismiss" aria-label="Dismiss notification"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>

                    <!-- Retention policy bar -->
                    <div class="found-retention-bar">
                        <span class="found-retention-text">There are <strong><?php echo (int)$overdueCount; ?></strong> Item<?php echo $overdueCount !== 1 ? 's' : ''; ?> that have exceeded the retention policy.</span>
                        <?php if (!empty($expiringItems)): ?>
                        <a href="#" class="found-dispose-link" id="expiryTriggerLink">View Items</a>
                        <?php endif; ?>
                    </div>

                    <!-- Header: tabs + View Inventory + dual filters -->
                    <div class="found-tabs-actions-row">
                        <div class="found-tabs">
                            <span class="found-tab-text found-tab-active" id="allItemsTab"><i class="fa-solid fa-list" style="margin-right:5px;font-size:12px;"></i>All Items</span>
                            <span class="found-tab-text" id="guestItemsTab"><i class="fa-solid fa-user-group" style="margin-right:5px;font-size:12px;"></i>Guest Items</span>
                        </div>
                        <a href="FoundAdmin.php" class="ima-view-inventory-btn">View Inventory</a>
                        <!-- Category filter (All Items) -->
                        <select id="matchedCategoryFilter" class="found-filter-select" aria-label="Filter by category">
                            <option value="">Filter By Category</option>
                            <?php foreach ($itemCategories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Date filter (Guest Items) — toggled by JS -->
                        <select id="guestDateFilter" class="found-filter-select" style="display:none;" aria-label="Filter by date">
                            <option value="">Filter By Date</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="3months">Last 3 Months</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                    </div><!-- /.found-header-row -->

                    <!-- All Items: For Claiming (Internal) -->
                    <div class="inventory-card matched-reports-card" id="recoveredSection">
                        <div class="inventory-title">For Claiming (Internal)</div>
                        <div class="table-wrapper">
                            <table class="matched-reports-table" id="matchedReportsTable">
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
                                <?php
                                if (empty($matchedItems)) {
                                    echo '<tr><td colspan="7" class="table-empty">No matching items.</td></tr>';
                                } else {
                                    foreach ($matchedItems as $it) {
                                        $isReport    = !empty($it['_is_report']);
                                        $barcodeId   = htmlspecialchars($it['id'] ?? '');
                                        $itemType    = htmlspecialchars($it['item_type'] ?? '');
                                        $foundAt     = htmlspecialchars($isReport ? ($it['found_at'] ?? 'Report') : ($it['found_at'] ?? ''));
                                        $dateEncoded = $isReport ? ($it['date_lost'] ?? $it['dateEncoded'] ?? '') : ($it['dateEncoded'] ?? '');
                                        $retentionEnd = $it['retention_end'] ?? '—';
                                        $storage     = htmlspecialchars($it['storage_location'] ?? '');
                                        $img         = isset($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'], ENT_QUOTES, 'UTF-8') : '';
                                        $color       = htmlspecialchars($it['color'] ?? '');
                                        $brand       = htmlspecialchars($it['brand'] ?? '');
                                        $foundBy     = htmlspecialchars($it['found_by'] ?? '');
                                        $statusLabel = htmlspecialchars($it['status'] ?? '');
                                        $isExpiring  = !empty($it['_is_expiring']);
                                        $isOverdue   = !empty($it['_is_overdue']);
                                        $rowClass    = $isOverdue ? ' matched-row-overdue' : '';
                                        $catFilter   = htmlspecialchars($it['item_type'] ?? '');

                                        $claimClass   = $isOverdue ? 'found-btn-claim btn-claim-expired' : 'found-btn-claim';
                                        $claimDisabled = $isOverdue ? ' disabled title="Retention period exceeded"' : '';

                                        $rawDesc = $it['item_description'] ?? '';
                                        $itemDesc = htmlspecialchars($rawDesc, ENT_QUOTES, 'UTF-8');
                                        // Extract "Item Type: X" as display name
                                        $parsedItemName = '';
                                        if (preg_match('/^Item(?:\s+Type)?:\s*(.+?)(?:\n|$)/mi', $rawDesc, $inm)) {
                                            $parsedItemName = trim($inm[1]);
                                        }
                                        $dataAttrs = ' data-id="' . $barcodeId . '"'
                                                   . ' data-category="' . $catFilter . '"'
                                                   . ' data-color="' . $color . '"'
                                                   . ' data-brand="' . $brand . '"'
                                                   . ' data-found-by="' . $foundBy . '"'
                                                   . ' data-date-encoded="' . htmlspecialchars($dateEncoded) . '"'
                                                   . ' data-storage-location="' . $storage . '"'
                                                   . ' data-status="' . $statusLabel . '"'
                                                   . ' data-item-description="' . $itemDesc . '"'
                                                   . ' data-item-name="' . htmlspecialchars($parsedItemName) . '"'
                                                   . ' data-is-report="' . ($isReport ? '1' : '0') . '"'
                                                   . ($isReport ? ' data-user-id="' . htmlspecialchars($it['user_id'] ?? '') . '" data-date-lost="' . htmlspecialchars($it['date_lost'] ?? '') . '"' : '');
                                        if ($img) $dataAttrs .= ' data-image="' . $img . '"';

                                        echo '<tr class="matched-data-row' . $rowClass . '"' . $dataAttrs . '>';
                                        echo '<td>' . $barcodeId . '</td>';
                                        echo '<td>' . $itemType . '</td>';
                                        echo '<td>' . $foundAt . '</td>';
                                        echo '<td>' . htmlspecialchars($dateEncoded) . '</td>';
                                        echo '<td class="retention-cell">';
                                        echo htmlspecialchars($retentionEnd);
                                        if ($isOverdue) {
                                            echo ' <span class="matched-pill-expiring" style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;vertical-align:middle;">EXPIRED</span>';
                                        } elseif ($isExpiring) {
                                            echo ' <span class="matched-pill-expiring" style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;vertical-align:middle;">EXPIRING</span>';
                                        }
                                        echo '</td>';
                                        echo '<td>' . $storage . '</td>';
                                        echo '<td class="found-action-cell">'
                                           . '<button type="button" class="' . $claimClass . '"' . $claimDisabled . '>Claim</button>'
                                           . '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Guest Items: For Claiming (External) -->
                    <div class="inventory-card matched-reports-card" id="guestSection" style="display: none;">
                        <div class="inventory-title found-title-guest">For Claiming (External)</div>
                        <div class="table-wrapper">
                            <table class="matched-reports-table" id="guestReportsTable">
                                <thead>
                                <tr>
                                    <th>Barcode ID</th>
                                    <th>Encoded By</th>
                                    <th>Date Surrendered</th>
                                    <th>Retention End</th>
                                    <th>Storage Location</th>
                                    <th>Timestamp</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                if (empty($guestItems)) {
                                    echo '<tr><td colspan="7" class="table-empty">No guest items.</td></tr>';
                                } else {
                                    foreach ($guestItems as $it) {
                                        $barcodeId       = htmlspecialchars($it['id'] ?? '');
                                        $encodedBy       = htmlspecialchars($it['found_by'] ?? '');
                                        $dateSurrendered = $it['dateEncoded'] ?? $it['date_encoded'] ?? '';
                                        $retentionEnd    = $dateSurrendered ? date('Y-m-d', strtotime($dateSurrendered . ' +1 year')) : '';
                                        $isOverdue       = $retentionEnd && $retentionEnd < $today;
                                        $isExpiring      = !$isOverdue && $retentionEnd && $retentionEnd <= date('Y-m-d', strtotime('+30 days'));
                                        $storage         = htmlspecialchars($it['storage_location'] ?? '');
                                        $timestamp       = htmlspecialchars($it['created_at'] ?? $it['dateEncoded'] ?? '');
                                        if ($timestamp && strlen($timestamp) === 10) $timestamp .= ' 00:00:00';
                                        $img    = !empty($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'], ENT_QUOTES, 'UTF-8')
                                                : (!empty($it['image_data']) ? htmlspecialchars($it['image_data'], ENT_QUOTES, 'UTF-8') : '');
                                        $color  = htmlspecialchars($it['color'] ?? '');
                                        $foundBy = htmlspecialchars($it['found_by'] ?? '');
                                        // Parse ID Type + Fullname from item_description
                                        $gDesc     = $it['item_description'] ?? $it['itemDescription'] ?? '';
                                        $gIdType   = preg_match('/^ID Type:\s*(.+?)(?:\n|$)/m', $gDesc, $gm) ? trim($gm[1]) : '';
                                        $gFullname = preg_match('/^Fullname:\s*(.+?)(?:\n|$)/m',  $gDesc, $gm) ? trim($gm[1]) : '';
                                        $dataAttrs = ' data-id="' . $barcodeId . '"'
                                                   . ' data-color="' . $color . '"'
                                                   . ' data-found-by="' . $foundBy . '"'
                                                   . ' data-date-encoded="' . htmlspecialchars($dateSurrendered) . '"'
                                                   . ' data-storage-location="' . $storage . '"'
                                                   . ' data-id-type="' . htmlspecialchars($gIdType) . '"'
                                                   . ' data-fullname="' . htmlspecialchars($gFullname) . '"';
                                        if ($img) $dataAttrs .= ' data-image="' . $img . '"';

                                        echo '<tr class="matched-data-row guest-row"' . $dataAttrs . '>';
                                        echo '<td>' . $barcodeId . '</td>';
                                        echo '<td>' . $encodedBy . '</td>';
                                        echo '<td>' . htmlspecialchars($dateSurrendered) . '</td>';
                                        echo '<td>';
                                        echo htmlspecialchars($retentionEnd);
                                        if ($isOverdue) {
                                            echo ' <span class="matched-pill-expiring" style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;vertical-align:middle;">EXPIRED</span>';
                                        } elseif ($isExpiring) {
                                            echo ' <span class="matched-pill-expiring" style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;vertical-align:middle;">EXPIRING</span>';
                                        }
                                        echo '</td>';
                                        echo '<td>' . $storage . '</td>';
                                        echo '<td>' . $timestamp . '</td>';
                                        echo '<td class="found-action-cell"><button type="button" class="ima-guest-view-btn">View</button></td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>


                </section>
            </div>
        </div>
    </main>
</div>

<!-- Inline styles for new UI elements -->
<style>
/* View Inventory button */
.ima-view-inventory-btn {
    display: inline-flex;
    align-items: center;
    padding: 7px 16px;
    background: #8b0000;
    color: #fff;
    border-radius: 8px;
    font-family: Poppins, sans-serif;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: opacity 0.15s;
    margin-left: 4px;
}
.ima-view-inventory-btn:hover { opacity: 0.88; }

/* Guest View button (blue) */
.ima-guest-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 16px;
    border-radius: 6px;
    background: #1976d2;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    font-family: Poppins, sans-serif;
    border: none;
    cursor: pointer;
    transition: opacity 0.15s;
}
.ima-guest-view-btn:hover { opacity: 0.85; }

/* ── Guest Item Details Modal ──────────────────────────────────── */
.gdm-overlay {
    display: none; position: fixed; inset: 0; z-index: 1500;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,0.5);
}
.gdm-overlay.open { display: flex; }
.gdm-modal {
    background: #fff; border-radius: 12px;
    width: min(640px, 96vw); max-height: 90vh; overflow-y: auto;
    box-shadow: 0 16px 48px rgba(0,0,0,0.22);
    display: flex; flex-direction: column;
}
.gdm-header {
    background: #8b0000; border-radius: 12px 12px 0 0;
    padding: 14px 20px; display: flex;
    align-items: center; justify-content: space-between; flex-shrink: 0;
}
.gdm-header-title { color: #fff; font-size: 16px; font-weight: 700; margin: 0; }
.gdm-close-btn {
    background: none; border: none; color: #fff; font-size: 18px;
    cursor: pointer; padding: 2px 6px; border-radius: 4px;
    opacity: 0.85; transition: opacity 0.15s; line-height: 1;
}
.gdm-close-btn:hover { opacity: 1; }
.gdm-body { display: flex; flex: 1; }
.gdm-left {
    width: 38%; flex-shrink: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: flex-start;
    padding: 28px 16px 24px;
    border-right: 1px solid #e5e7eb; background: #fafafa;
}
.gdm-photo {
    width: 150px; height: 110px; object-fit: cover;
    border-radius: 6px; border: 1px solid #e0e0e0;
}
.gdm-photo-placeholder {
    width: 150px; height: 110px; background: #f3f4f6;
    border-radius: 6px; border: 1px solid #e0e0e0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 6px; color: #9ca3af; font-size: 11px;
}
.gdm-barcode {
    margin-top: 10px; font-size: 13px; color: #374151;
    font-weight: 500; text-align: center;
}
.gdm-right {
    flex: 1; padding: 28px 28px 20px;
    display: flex; flex-direction: column;
}
.gdm-section-title {
    font-size: 15px; font-weight: 700; color: #111827;
    margin: 0 0 16px; text-align: center;
}
.gdm-info-row {
    display: flex; align-items: baseline; gap: 8px;
    padding: 7px 0; border-bottom: 1px solid #f3f4f6;
}
.gdm-info-row:last-child { border-bottom: none; }
.gdm-info-label { font-size: 13px; color: #6b7280; min-width: 130px; flex-shrink: 0; }
.gdm-info-value { font-size: 13px; font-weight: 700; color: #111827; text-align: right; flex: 1; }
.gdm-footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 16px 24px; border-top: 1px solid #e5e7eb;
}
.gdm-btn-cancel {
    padding: 9px 22px; border: 1px solid #d1d5db; border-radius: 7px;
    background: #fff; color: #374151; font-family: Poppins, sans-serif;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.gdm-btn-cancel:hover { background: #f3f4f6; }
.gdm-btn-claim {
    padding: 9px 22px; border: none; border-radius: 7px;
    background: #8b0000; color: #fff; font-family: Poppins, sans-serif;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity 0.15s;
}
.gdm-btn-claim:hover { opacity: 0.88; }

/* ── Confirm Item Claim Modal ─────────────────────────────────── */
.ccm-overlay {
    display: none; position: fixed; inset: 0; z-index: 1600;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,0.5);
}
.ccm-overlay.open { display: flex; }
.ccm-modal {
    background: #fff; border-radius: 12px;
    width: min(520px, 96vw); max-height: 92vh; overflow-y: auto;
    box-shadow: 0 16px 48px rgba(0,0,0,0.25);
    display: flex; flex-direction: column;
}
.ccm-header {
    background: #8b0000; border-radius: 12px 12px 0 0;
    padding: 14px 20px; display: flex;
    align-items: center; justify-content: space-between;
}
.ccm-header-title { color: #fff; font-size: 15px; font-weight: 700; margin: 0; }
.ccm-close-btn {
    background: none; border: none; color: #fff; font-size: 18px;
    cursor: pointer; padding: 2px 6px; border-radius: 4px;
    opacity: 0.85; transition: opacity 0.15s; line-height: 1;
}
.ccm-close-btn:hover { opacity: 1; }
.ccm-body { padding: 20px 24px 8px; }
.ccm-item-summary {
    display: flex; align-items: center; gap: 14px;
    padding: 12px; background: #f9fafb;
    border-radius: 8px; border: 1px solid #e5e7eb;
    margin-bottom: 18px;
}
.ccm-thumb {
    width: 52px; height: 52px; border-radius: 6px;
    object-fit: cover; flex-shrink: 0;
}
.ccm-thumb-placeholder {
    width: 52px; height: 52px; border-radius: 6px;
    background: #e5e7eb; display: flex;
    align-items: center; justify-content: center;
    color: #9ca3af; font-size: 20px; flex-shrink: 0;
}
.ccm-item-info { display: flex; flex-direction: column; gap: 3px; }
.ccm-item-name { font-size: 14px; font-weight: 700; color: #111827; }
.ccm-item-sub  { font-size: 12px; color: #6b7280; }
/* Yellow verification block */
.ccm-section-yellow {
    background: #fffbeb; border: 1px solid #fcd34d;
    border-radius: 8px; padding: 14px 16px; margin-bottom: 14px;
}
.ccm-section-title-yellow {
    font-size: 13px; font-weight: 700; color: #92400e;
    margin: 0 0 14px; text-align: center;
}
.ccm-form-row {
    display: flex; align-items: center;
    gap: 10px; margin-bottom: 10px;
}
.ccm-form-row:last-child { margin-bottom: 0; }
.ccm-label {
    font-size: 12px; color: #374151; font-weight: 500;
    min-width: 118px; flex-shrink: 0;
}
.ccm-input {
    flex: 1; padding: 7px 10px;
    border: 1px solid #d1d5db; border-radius: 6px;
    font-family: Poppins, sans-serif; font-size: 12px;
    color: #111827; background: #fff; outline: none;
    transition: border-color 0.15s;
}
.ccm-input:focus { border-color: #8b0000; }
.ccm-required { color: #dc2626; font-size: 12px; }
.ccm-file-row {
    display: flex; align-items: center; gap: 6px; flex: 1;
    padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 6px;
    background: #fff; font-size: 12px; color: #374151; cursor: pointer;
    position: relative; overflow: hidden;
}
.ccm-file-input {
    position: absolute; inset: 0; opacity: 0;
    cursor: pointer; width: 100%; height: 100%;
}
.ccm-file-clear {
    margin-left: auto; background: none; border: none;
    cursor: pointer; color: #9ca3af; font-size: 13px;
    padding: 0; flex-shrink: 0; display: none;
    z-index: 1;
}
/* Gray action taken block */
.ccm-section-gray {
    background: #f3f4f6; border-radius: 8px;
    padding: 14px 16px; margin-bottom: 14px;
}
.ccm-section-title-gray {
    font-size: 13px; font-weight: 700; color: #374151; margin: 0 0 14px;
}

.ccm-footer {
    display: flex; justify-content: flex-end;
    gap: 10px; padding: 14px 24px 20px;
}
.ccm-btn-cancel {
    padding: 9px 22px; border: 1px solid #d1d5db; border-radius: 7px;
    background: #fff; color: #374151; font-family: Poppins, sans-serif;
    font-size: 13px; font-weight: 600; cursor: pointer;
}
.ccm-btn-cancel:hover { background: #f3f4f6; }
.ccm-btn-confirm {
    padding: 9px 22px; border: none; border-radius: 7px;
    background: #8b0000; color: #fff; font-family: Poppins, sans-serif;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity 0.15s;
}
.ccm-btn-confirm:hover { opacity: 0.88; }
.ccm-btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 520px) {
    .gdm-body { flex-direction: column; }
    .gdm-left { width: 100%; border-right: none; border-bottom: 1px solid #e5e7eb; }
}
</style>

<!-- Expiry Items popup -->
<div class="expiry-overlay" id="expiryOverlay" role="dialog" aria-modal="true"
     aria-labelledby="expiryPopupTitle"
     onclick="if(event.target===this)document.getElementById('expiryOverlay').classList.remove('open')">
    <div class="expiry-popup">
        <div class="expiry-popup-hdr">
            <span class="expiry-popup-title" id="expiryPopupTitle">Items with Approaching Retention Dates</span>
            <button type="button" class="expiry-popup-close" id="expiryPopupClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="expiry-cards-grid" id="expiryCardsGrid">
<?php if (empty($expiringItems)): ?>
            <p class="expiry-empty-msg">No items approaching expiry within the next 30 days.</p>
<?php else: ?>
<?php foreach ($expiringItems as $ei):
    $eiId    = htmlspecialchars($ei['id'] ?? '');
    $eiCat   = htmlspecialchars($ei['item_type'] ?? 'Item');
    $eiDesc  = $ei['item_description'] ?? $ei['itemDescription'] ?? '';
    $eiShort = htmlspecialchars(mb_strlen($eiDesc) > 60 ? mb_substr($eiDesc, 0, 60) . '...' : $eiDesc);
    $eiLoc   = htmlspecialchars($ei['found_at'] ?? $ei['storage_location'] ?? 'N/A');
    $eiDate  = htmlspecialchars($ei['_retEnd'] ?? '');
    $eiStore = htmlspecialchars($ei['storage_location'] ?? '');
?>
            <div class="expiry-card" data-id="<?php echo $eiId; ?>"
                 data-storage-location="<?php echo $eiStore; ?>">
                <h4 class="expiry-card-title">
                    <i class="fa-regular fa-file-lines"></i>
                    <?php echo $eiCat; ?>
                </h4>
                <div class="expiry-card-meta">
                    <i class="fa-solid fa-location-dot"></i>
                    <span><?php echo $eiLoc; ?></span>
                </div>
                <div class="expiry-card-meta">
                    <i class="fa-regular fa-calendar"></i>
                    <span>Expires: <?php echo $eiDate; ?></span>
                </div>
                <span class="expiry-card-badge">Expiring Soon</span>
            </div>
<?php endforeach; ?>
<?php endif; ?>
        </div>
    </div>
</div>

<!-- Guest Item Details Modal (Image 1) -->
<div id="guestDetailsModal" class="gdm-overlay" role="dialog" aria-modal="true"
     onclick="if(event.target===this)closeGuestDetailsModal()">
    <div class="gdm-modal" onclick="event.stopPropagation()">
        <div class="gdm-header">
            <h3 class="gdm-header-title">Item Details</h3>
            <button type="button" class="gdm-close-btn" onclick="closeGuestDetailsModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="gdm-body">
            <div class="gdm-left">
                <div id="gdmPhotoPlaceholder" class="gdm-photo-placeholder">
                    <i class="fa-regular fa-id-card" style="font-size:28px;"></i>
                    <span>No photo</span>
                </div>
                <img id="gdmPhoto" class="gdm-photo" src="" alt="ID photo" style="display:none;">
                <p class="gdm-barcode" id="gdmBarcode">Barcode ID: —</p>
            </div>
            <div class="gdm-right">
                <h4 class="gdm-section-title">General Information</h4>
                <div class="gdm-info-row">
                    <span class="gdm-info-label">ID Type:</span>
                    <span class="gdm-info-value" id="gdmIdType">—</span>
                </div>
                <div class="gdm-info-row">
                    <span class="gdm-info-label">Fullname:</span>
                    <span class="gdm-info-value" id="gdmFullname">—</span>
                </div>
                <div class="gdm-info-row">
                    <span class="gdm-info-label">Color:</span>
                    <span class="gdm-info-value" id="gdmColor">—</span>
                </div>
                <div class="gdm-info-row">
                    <span class="gdm-info-label">Storage Location:</span>
                    <span class="gdm-info-value" id="gdmStorage">—</span>
                </div>
                <div class="gdm-info-row">
                    <span class="gdm-info-label">Encoded By:</span>
                    <span class="gdm-info-value" id="gdmEncodedBy">—</span>
                </div>
                <div class="gdm-info-row">
                    <span class="gdm-info-label">Date Surrendered:</span>
                    <span class="gdm-info-value" id="gdmDateSurrendered">—</span>
                </div>
            </div>
        </div>
        <div class="gdm-footer">
            <button type="button" class="gdm-btn-cancel" onclick="closeGuestDetailsModal()">Cancel</button>
            <button type="button" class="gdm-btn-claim" id="gdmClaimBtn">Claim</button>
        </div>
    </div>
</div>

<!-- Confirm Item Claim Modal (Image 2) -->
<div id="confirmClaimModal" class="ccm-overlay" role="dialog" aria-modal="true"
     onclick="if(event.target===this)closeConfirmClaimModal()">
    <div class="ccm-modal" onclick="event.stopPropagation()">
        <div class="ccm-header">
            <h3 class="ccm-header-title">Confirm Item Claim</h3>
            <button type="button" class="ccm-close-btn" onclick="closeConfirmClaimModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="ccm-body">
            <!-- Item summary strip -->
            <div class="ccm-item-summary">
                <div class="ccm-thumb-placeholder" id="ccmThumbPlaceholder">
                    <i class="fa-solid fa-box"></i>
                </div>
                <img id="ccmThumb" class="ccm-thumb" src="" alt="" style="display:none;">
                <div class="ccm-item-info">
                    <span class="ccm-item-name" id="ccmItemName">—</span>
                    <span class="ccm-item-sub"  id="ccmItemSub">—</span>
                </div>
            </div>

            <!-- Verification of Claimant's Identity -->
            <div class="ccm-section-yellow">
                <p class="ccm-section-title-yellow">Verification of Claimant's Identity</p>
                <div class="ccm-form-row">
                    <label class="ccm-label" for="ccmClaimantName">Claimant's Name:</label>
                    <div style="flex:1;position:relative;">
                        <input type="text" id="ccmClaimantName" class="ccm-input" style="padding-right:22px;width:100%;box-sizing:border-box;" required>
                        <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#dc2626;font-size:13px;pointer-events:none;">*</span>
                    </div>
                </div>
                <div class="ccm-form-row" style="align-items:flex-start;">
                    <label class="ccm-label" for="ccmUbMail" style="padding-top:8px;">Email:</label>
                    <div style="flex:1;">
                        <input type="text" id="ccmUbMail" class="ccm-input" style="width:100%;box-sizing:border-box;" placeholder="e.g. 200981@ub.edu.ph">
                        <span id="ccmUbMailError" style="display:none;font-size:11px;color:#dc2626;margin-top:3px;display:block;">
                            Must be a valid @ub.edu.ph email address.
                        </span>
                    </div>
                </div>
                <div class="ccm-form-row">
                    <label class="ccm-label" for="ccmContactNumber">Contact Number:</label>
                    <input type="text" id="ccmContactNumber" class="ccm-input">
                </div>
                <div class="ccm-form-row" style="align-items:flex-start;">
                    <label class="ccm-label" style="padding-top:8px;">Photo: <span style="color:#dc2626;">*</span></label>
                    <div style="flex:1;">
                        <div class="pp-wrap" id="ccmPhotoPicker">
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
                        <span id="ccmFileError" style="display:none;font-size:11px;color:#dc2626;margin-top:4px;display:block;">A photo is required to confirm the claim.</span>
                    </div>
                </div>
            </div>

            <!-- Action Taken -->
            <div class="ccm-section-gray">
                <p class="ccm-section-title-gray">Action Taken</p>
                <div class="ccm-form-row">
                    <label class="ccm-label" for="ccmDateAccomplishment">Date of Accomplishment:</label>
                    <input type="date" id="ccmDateAccomplishment" class="ccm-input" style="flex:1;" max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
        </div>
        <div class="ccm-footer">
            <button type="button" class="ccm-btn-cancel" onclick="closeConfirmClaimModal()">Cancel</button>
            <button type="button" class="ccm-btn-confirm" id="ccmConfirmBtn">Confirm</button>
        </div>
    </div>
</div>

<script src="../assets/photo-picker.js?v=<?php echo time(); ?>"></script>
<script>
/* Admin dropdown */
(function () {
    var dd = document.getElementById('adminDropdown');
    var tr = dd && dd.querySelector('.admin-dropdown-trigger');
    if (!dd || !tr) return;
    tr.addEventListener('click', function (e) {
        e.stopPropagation();
        dd.classList.toggle('open');
        tr.setAttribute('aria-expanded', dd.classList.contains('open'));
    });
    document.addEventListener('click', function () {
        dd.classList.remove('open');
        tr.setAttribute('aria-expanded', 'false');
    });
})();

/* Search autofill */
(function(){
  var input=document.getElementById('adminSearchInput');
  var clearBtn=document.getElementById('adminSearchClear');
  var dropdown=document.getElementById('searchDropdown');
  var form=input?input.closest('form'):null;
  if(!input||!dropdown)return;
  var timer=null,lastQ='';
  function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function render(items,q){
    if(!items||!items.length){dropdown.innerHTML='<div class="sd-no-results">No results for "'+esc(q)+'"</div>';dropdown.style.display='block';return;}
    dropdown.innerHTML=items.map(function(item){
      var name=item.item_type||'Item';
      if(item.brand)name+=' \u2013 '+item.brand;
      if(item.color)name+=' ('+item.color+')';
      var meta='';
      if(item.found_at)meta+='<span class="sd-meta-item"><i class="fa-solid fa-location-dot"></i>'+esc(item.found_at)+'</span>';
      if(item.date)meta+='<span class="sd-meta-item"><i class="fa-regular fa-calendar"></i>'+esc(item.date)+'</span>';
      return '<div class="search-dropdown-item" data-id="'+esc(item.id)+'">'+
        '<div class="sd-icon"><i class="fa-regular fa-file-lines"></i></div>'+
        '<div class="sd-info">'+
          '<div class="sd-barcode">'+esc(item.id)+'</div>'+
          '<div class="sd-title">'+esc(name)+'</div>'+
          (item.description?'<div class="sd-desc">'+esc(item.description)+'</div>':'')+
          (meta?'<div class="sd-meta">'+meta+'</div>':'')+
        '</div></div>';
    }).join('');
    dropdown.style.display='block';
  }
  function doSearch(q){if(q===lastQ)return;lastQ=q;
    fetch('search_items.php?q='+encodeURIComponent(q),{credentials:'include'})
      .then(function(r){return r.json();}).then(function(d){render(d,q);})
      .catch(function(){dropdown.style.display='none';});
  }
  input.addEventListener('input',function(){
    var v=this.value.trim();
    if(clearBtn)clearBtn.style.display=v?'flex':'none';
    clearTimeout(timer);
    if(v.length<2){dropdown.style.display='none';lastQ='';return;}
    timer=setTimeout(function(){doSearch(v);},220);
  });
  dropdown.addEventListener('click',function(e){
    var row=e.target.closest('.search-dropdown-item');
    if(!row)return;
    var id=row.getAttribute('data-id');
    if(!id)return;
    input.value=id;dropdown.style.display='none';
    if(clearBtn)clearBtn.style.display='flex';
    var tableRow=document.querySelector('tr[data-id="'+id+'"]');
    if(tableRow){tableRow.click();return;}
    if(window.__encodedItems&&window.__encodedItems[id]&&window.openViewModalForEncodedItem){window.openViewModalForEncodedItem(window.__encodedItems[id]);return;}
    if(form)form.submit();
  });
  document.addEventListener('click',function(e){
    if(!input.contains(e.target)&&!dropdown.contains(e.target))dropdown.style.display='none';
  });
  if(clearBtn){
    clearBtn.addEventListener('click',function(){input.value='';dropdown.style.display='none';lastQ='';clearBtn.style.display='none';input.focus();});
    clearBtn.style.display=input.value?'flex':'none';
  }
})();

/* ── Tab switching: show/hide sections + swap filters ─────────── */
(function () {
    var allTab    = document.getElementById('allItemsTab');
    var guestTab  = document.getElementById('guestItemsTab');
    var recovered = document.getElementById('recoveredSection');
    var guest     = document.getElementById('guestSection');
    var catFilter  = document.getElementById('matchedCategoryFilter');
    var dateFilter = document.getElementById('guestDateFilter');
    if (!allTab || !guestTab || !recovered || !guest) return;

    function showAll() {
        allTab.classList.add('found-tab-active');
        guestTab.classList.remove('found-tab-active');
        recovered.style.display = '';
        guest.style.display     = 'none';
        if (catFilter)  catFilter.style.display  = '';
        if (dateFilter) dateFilter.style.display = 'none';
    }
    function showGuest() {
        guestTab.classList.add('found-tab-active');
        allTab.classList.remove('found-tab-active');
        guest.style.display     = '';
        recovered.style.display = 'none';
        if (catFilter)  catFilter.style.display  = 'none';
        if (dateFilter) dateFilter.style.display = '';
    }
    allTab.addEventListener('click', showAll);
    guestTab.addEventListener('click', showGuest);
    if (window.location.hash === '#guest') showGuest();
})();

/* ── Category filter (All Items) ─────────────────────────────── */
(function () {
    var f = document.getElementById('matchedCategoryFilter');
    if (!f) return;
    f.addEventListener('change', function () {
        var val = f.value.trim();
        document.querySelectorAll('#recoveredSection .matched-data-row').forEach(function (row) {
            var cat = (row.getAttribute('data-category') || '').trim();
            row.style.display = (!val || cat === val) ? '' : 'none';
        });
    });
})();

/* ── Date filter (Guest Items) ───────────────────────────────── */
(function () {
    var f = document.getElementById('guestDateFilter');
    if (!f) return;
    f.addEventListener('change', function () {
        var val = f.value.trim();
        var now = new Date();
        document.querySelectorAll('#guestSection .guest-row').forEach(function (row) {
            var ds = row.getAttribute('data-date-encoded') || '';
            var d  = ds ? new Date(ds) : null;
            var show = true;
            if (val && d) {
                if      (val === 'today')   { show = d.toDateString() === now.toDateString(); }
                else if (val === 'week')    { var w = new Date(now); w.setDate(w.getDate()-7); show = d >= w; }
                else if (val === 'month')   { var m = new Date(now); m.setMonth(m.getMonth()-1); show = d >= m; }
                else if (val === '3months') { var q = new Date(now); q.setMonth(q.getMonth()-3); show = d >= q; }
                else if (val === 'year')    { var y = new Date(now); y.setFullYear(y.getFullYear()-1); show = d >= y; }
            } else if (val && !d) { show = false; }
            row.style.display = show ? '' : 'none';
        });
    });
})();

/* ── Guest Item Details Modal ─────────────────────────────────── */
window._gdmRow = null;

window.openGuestDetailsModal = function (row) {
    window._gdmRow = row;
    var o = document.getElementById('guestDetailsModal');
    if (!o) return;
    var ph   = document.getElementById('gdmPhotoPlaceholder');
    var img  = document.getElementById('gdmPhoto');
    var bid  = document.getElementById('gdmBarcode');
    var idT  = document.getElementById('gdmIdType');
    var fn   = document.getElementById('gdmFullname');
    var col  = document.getElementById('gdmColor');
    var sto  = document.getElementById('gdmStorage');
    var enc  = document.getElementById('gdmEncodedBy');
    var ds   = document.getElementById('gdmDateSurrendered');

    var imgUrl = row.getAttribute('data-image');
    if (imgUrl) { img.src = imgUrl; img.style.display = 'block'; ph.style.display = 'none'; }
    else        { img.style.display = 'none'; ph.style.display = 'flex'; }

    function v(a) { return row.getAttribute(a) || '—'; }
    if (bid) bid.textContent = 'Barcode ID: ' + (row.getAttribute('data-id') || '—');
    if (idT) idT.textContent = v('data-id-type');
    if (fn)  fn.textContent  = v('data-fullname');
    if (col) col.textContent = v('data-color');
    if (sto) sto.textContent = v('data-storage-location');
    if (enc) enc.textContent = v('data-found-by');
    if (ds)  ds.textContent  = v('data-date-encoded');

    o.classList.add('open');
    document.body.style.overflow = 'hidden';
};
window.closeGuestDetailsModal = function () {
    var o = document.getElementById('guestDetailsModal');
    if (o) o.classList.remove('open');
    document.body.style.overflow = '';
};

/* Wire Guest View buttons */
(function () {
    var tbody = document.querySelector('#guestSection tbody');
    if (!tbody) return;
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.ima-guest-view-btn');
        if (!btn) return;
        e.preventDefault();
        var r = btn.closest('tr');
        if (r) window.openGuestDetailsModal(r);
    });
})();

/* Guest modal Claim → opens Confirm modal */
(function () {
    var btn = document.getElementById('gdmClaimBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var row = window._gdmRow;
        if (!row) return;
        closeGuestDetailsModal();
        openConfirmClaimModal(row);
    });
})();

/* ── Confirm Item Claim Modal ─────────────────────────────────── */
window._ccmRow = null;
window._ccmImg = null;

window.openConfirmClaimModal = function (row) {
    window._ccmRow = row;
    window._ccmImg = null;

    var o   = document.getElementById('confirmClaimModal');
    var th  = document.getElementById('ccmThumb');
    var thP = document.getElementById('ccmThumbPlaceholder');
    var nm  = document.getElementById('ccmItemName');
    var sb  = document.getElementById('ccmItemSub');

    var imgUrl   = row.getAttribute('data-image');
    var bid      = row.getAttribute('data-id')               || '';
    var color    = row.getAttribute('data-color')            || '';
    var storage  = row.getAttribute('data-storage-location') || '';
    var fullname = row.getAttribute('data-fullname')         || '';
    var category = row.getAttribute('data-category')         || '';
    var isGuest  = row.classList.contains('guest-row');

    if (imgUrl) { th.src = imgUrl; th.style.display = 'block'; thP.style.display = 'none'; }
    else        { th.style.display = 'none'; thP.style.display = 'flex'; }

    /* Use parsed item name if available, otherwise fall back gracefully */
    var itemName = row.getAttribute('data-item-name') || '';
    var displayName;
    if (isGuest && fullname) {
        displayName = bid + ': ' + fullname + (color ? ' (' + color + ')' : '');
    } else if (itemName) {
        displayName = bid + ': ' + itemName + (color ? ' — ' + color : '');
    } else {
        displayName = bid + ': ' + (category || 'Item');
    }
    if (nm) nm.textContent = displayName;
    if (sb) sb.textContent = storage || '—';

    /* Reset form */
    ['ccmClaimantName','ccmUbMail','ccmContactNumber'].forEach(function (id) {
        var el = document.getElementById(id); if (el) { el.value = ''; el.style.borderColor = ''; }
    });
    var ubErr = document.getElementById('ccmUbMailError');
    if (ubErr) ubErr.style.display = 'none';
    var fileErr = document.getElementById('ccmFileError');
    if (fileErr) fileErr.style.display = 'none';
    clearCcmFile();
    var dt = document.getElementById('ccmDateAccomplishment');
    if (dt) { dt.value = new Date().toISOString().slice(0, 10); dt.max = new Date().toISOString().slice(0, 10); }

    o.classList.add('open');
    document.body.style.overflow = 'hidden';
};
/* UB mail live validation */
(function () {
    var ubInput = document.getElementById('ccmUbMail');
    var ubError = document.getElementById('ccmUbMailError');
    if (!ubInput || !ubError) return;
    ubInput.addEventListener('input', function () {
        var v = this.value.trim();
        if (v && !/^[^@]+@ub\.edu\.ph$/i.test(v)) {
            ubError.style.display = 'block';
            ubInput.style.borderColor = '#dc2626';
        } else {
            ubError.style.display = 'none';
            ubInput.style.borderColor = '';
        }
    });
})();

window.closeConfirmClaimModal = function () {
    var o = document.getElementById('confirmClaimModal');
    if (o) o.classList.remove('open');
    document.body.style.overflow = '';
};

/* Photo picker for claim confirmation modal */
window._ccmPP = PhotoPicker.init({
    el: 'ccmPhotoPicker',
    onChange: function (dataUrl) { window._ccmImg = dataUrl || null; }
});
window.clearCcmFile = function () {
    window._ccmImg = null;
    if (window._ccmPP) window._ccmPP.clear();
};

/* Confirm submit */
(function () {
    var btn      = document.getElementById('ccmConfirmBtn');
    var claimUrl = 'claim_item.php';
    if (!btn) return;
    btn.addEventListener('click', function () {
        var cName     = document.getElementById('ccmClaimantName');
        var ubMail    = document.getElementById('ccmUbMail');
        var ubError   = document.getElementById('ccmUbMailError');
        var fileError = document.getElementById('ccmFileError');
        var valid = true;

        if (!cName || !cName.value.trim()) {
            if (cName) cName.focus();
            valid = false;
        }

        /* UB Mail: optional but if filled must end with @ub.edu.ph */
        var mailVal = (ubMail ? ubMail.value.trim() : '');
        if (mailVal && !/^[^@]+@ub\.edu\.ph$/i.test(mailVal)) {
            if (ubError) { ubError.style.display = 'block'; }
            if (ubMail)  { ubMail.style.borderColor = '#dc2626'; ubMail.focus(); }
            valid = false;
        } else {
            if (ubError) ubError.style.display = 'none';
            if (ubMail)  ubMail.style.borderColor = '';
        }

        /* Image is required */
        if (!window._ccmImg) {
            if (fileError) fileError.style.display = 'block';
            valid = false;
        } else {
            if (fileError) fileError.style.display = 'none';
        }

        if (!valid) return;

        var row = window._ccmRow;
        if (!row) return;
        var id = row.getAttribute('data-id') || '';
        if (!id) return;

        /* Capture display values before closing the modal */
        var claimantNameVal = cName.value.trim();
        var dateAccompVal   = (document.getElementById('ccmDateAccomplishment') || {}).value || '';
        var itemNameVal     = row.getAttribute('data-item-name') || row.getAttribute('data-category') || 'Item';
        var barcodeVal      = id;

        btn.disabled = true; btn.textContent = 'Saving…';
        fetch(claimUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id:                id,
                claimant_name:     cName.value.trim(),
                ub_mail:           mailVal,
                contact_number:    (document.getElementById('ccmContactNumber')     || {}).value || '',
                date_accomplished: (document.getElementById('ccmDateAccomplishment')|| {}).value || '',
                imageDataUrl:      window._ccmImg || null
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            btn.disabled = false; btn.textContent = 'Confirm';
            if (data.ok) {
                closeConfirmClaimModal();
                var tbody    = row.parentNode;
                var colCount = row.querySelectorAll('td').length;
                row.remove();
                if (tbody && !tbody.querySelector('tr:not([style*="display: none"])')) {
                    var empty = document.createElement('tr');
                    empty.innerHTML = '<td colspan="' + colCount + '" class="table-empty">No items.</td>';
                    tbody.appendChild(empty);
                }
                /* Show recent action banner */
                var banner     = document.getElementById('recentActionBanner');
                var bannerText = document.getElementById('recentActionText');
                if (banner && bannerText) {
                    bannerText.textContent = itemNameVal + ' (Barcode: ' + barcodeVal + ') has been successfully claimed by ' + claimantNameVal + (dateAccompVal ? ' on ' + dateAccompVal : '') + '.';
                    banner.style.display = 'flex';
                    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            } else {
                alert(data.error || 'Could not claim item. Try again.');
            }
        })
        .catch(function () {
            btn.disabled = false; btn.textContent = 'Confirm';
            alert('Network error. Please try again.');
        });
    });
})();

/* ── All Items Claim button → open Confirm modal ─────────────── */
(function () {
    var tbl = document.getElementById('matchedReportsTable');
    if (!tbl) return;
    tbl.addEventListener('click', function (e) {
        var btn = e.target.closest('.found-btn-claim');
        if (!btn || btn.disabled || btn.classList.contains('btn-claim-expired')) return;
        e.preventDefault();
        var row = btn.closest('tr');
        if (row && !row.querySelector('td[colspan]')) openConfirmClaimModal(row);
    });
})();


/* ── Expiry popup ──────────────────────────────────────────── */
(function () {
    var overlay     = document.getElementById('expiryOverlay');
    var closeBtn    = document.getElementById('expiryPopupClose');
    var triggerLink = document.getElementById('expiryTriggerLink');
    if (!overlay) return;
    function openExpiry()  { overlay.classList.add('open'); }
    function closeExpiry() { overlay.classList.remove('open'); }
    if (triggerLink) triggerLink.addEventListener('click', function (e) { e.preventDefault(); openExpiry(); });
    if (closeBtn)    closeBtn.addEventListener('click', closeExpiry);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeExpiry(); });
})();

/* ── Recent action banner dismiss ──────────────────────────── */
(function () {
    function dismissBanner() {
        var b = document.getElementById('recentActionBanner');
        if (b) b.style.display = 'none';
    }
    var rabDismiss = document.getElementById('rabDismiss');
    var rabMark    = document.getElementById('rabMarkRead');
    if (rabDismiss) rabDismiss.addEventListener('click', dismissBanner);
    if (rabMark)    rabMark.addEventListener('click', dismissBanner);
})();

/* Keyboard close */
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeConfirmClaimModal(); closeGuestDetailsModal(); }
});
</script>

<script src="NotificationsDropdown.js"></script>
</body>
</html>