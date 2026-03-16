<?php
// FoundAdmin.php - Found Items view for UB Lost and Found System (database)
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';
// Recovered Items (Internal) – found items only (exclude REF- lost reports; those show on Reports page)
$allFromDb = get_items($pdo, null);
$encodedItems = array_values(array_filter($allFromDb, function ($it) {
    $id = $it['id'] ?? '';
    return $id === '' || strpos($id, 'REF-') !== 0;
}));
// Guest Items – only items categorised as 'ID & Nameplate'
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
    error_log('FoundAdmin guestItems: ' . $e->getMessage());
}
$today = date('Y-m-d');
$in30  = date('Y-m-d', strtotime('+30 days'));
$overdueCount   = 0;
$expiringItems  = [];
foreach ($encodedItems as $it) {
    $dateEnc = $it['dateEncoded'] ?? null;
    $retEnd  = $dateEnc ? date('Y-m-d', strtotime($dateEnc . ' +2 years')) : '';
    if ($retEnd && $retEnd < $today) {
        $overdueCount++;
    } elseif ($retEnd && $retEnd <= $in30) {
        $expiringItems[] = array_merge($it, ['_retEnd' => $retEnd, '_source' => 'internal']);
    }
}
foreach ($guestItems as $it) {
    $dateEnc = $it['dateEncoded'] ?? null;
    $retEnd  = $dateEnc ? date('Y-m-d', strtotime($dateEnc . ' +1 year')) : '';
    if ($retEnd && $retEnd < $today) {
        $overdueCount++;
    } elseif ($retEnd && $retEnd <= $in30) {
        $expiringItems[] = array_merge($it, ['_retEnd' => $retEnd, '_source' => 'guest']);
    }
}
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
$viewItemBaseUrl = $protocol . '://' . $host . $basePath;
$saveEncodedItemUrl = $viewItemBaseUrl . '/save_encoded_item.php';
$saveGuestItemUrl = $viewItemBaseUrl . '/save_guest_item.php';
$deleteItemUrl = $viewItemBaseUrl . '/delete_item.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UB Lost and Found System - Found Items</title>
    <link rel="stylesheet" href="AdminDashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="FoundAdmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <style>.fa-solid,.fa-regular,.fa-brands{display:inline-block!important;font-style:normal!important;font-variant:normal!important;text-rendering:auto!important;-webkit-font-smoothing:antialiased;}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
<style>
/* ── Issue 1: Sidebar mobile height fix ── */
@media (max-width: 900px) {
  .sidebar  { min-height: 0 !important; height: auto !important; }
  .nav-menu { flex: none !important; }
}

/* ── Issue 2: Responsive layout ── */
.main-content-wrap { min-width: 0; }

/* Header row: title on top, tabs+actions row below */
.found-header-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

/* Tabs + action buttons on one line */
.found-tabs-actions-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

/* Tabs pill group */
.found-tabs {
  display: flex;
  align-items: center;
  gap: 0;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 3px;
  flex-shrink: 0;
  margin-right: 8px; /* visual gap separating tabs from filter/actions */
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
}
.found-tab-text.found-tab-active {
  background: #fff;
  color: #111827;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Category filter — inline beside tabs */
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

/* Action buttons — right-aligned inside the same tabs row */
.found-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
  flex-shrink: 0;
}
.found-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 6px 13px;
  border-radius: 7px;
  font-family: Poppins, sans-serif;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  text-decoration: none;
  white-space: nowrap;
  transition: opacity 0.15s;
}
.found-btn:hover { opacity: 0.88; }
.found-btn-encode { background: #8b0000; color: #fff; }
.found-btn-report { background: #374151; color: #fff; }

/* ── Expiry Items popup ── */
.expiry-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.42);
  z-index: 1200;
  align-items: center;
  justify-content: center;
}
.expiry-overlay.open { display: flex; }
.expiry-popup {
  background: #fff;
  border-radius: 14px;
  padding: 24px 26px 28px;
  width: min(720px, 96vw);
  max-height: 88vh;
  overflow-y: auto;
  box-shadow: 0 12px 40px rgba(0,0,0,0.2);
  position: relative;
}
.expiry-popup-hdr {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.expiry-popup-title {
  font-size: 16px;
  font-weight: 700;
  color: #111;
}
.expiry-popup-close {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  color: #6b7280;
  padding: 2px 7px;
  border-radius: 5px;
  line-height: 1;
  transition: background 0.15s;
}
.expiry-popup-close:hover { background: #f3f4f6; color: #111; }

/* Cards grid — same aesthetic as StudentDashboard matched-item-card */
.expiry-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 14px;
}
.expiry-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.expiry-card-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #111;
  display: flex;
  align-items: center;
  gap: 9px;
}
.expiry-card-title i { font-size: 16px; color: #374151; }
.expiry-card-desc {
  display: flex;
  gap: 9px;
  align-items: flex-start;
  color: #333;
  font-size: 13px;
  font-style: italic;
}
.expiry-card-desc i { margin-top: 2px; color: #555; font-size: 13px; }
.expiry-card-meta {
  display: flex;
  align-items: center;
  gap: 9px;
  color: #555;
  font-size: 13px;
}
.expiry-card-meta i { width: 16px; text-align: center; }
.expiry-card-badge {
  display: inline-block;
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffc107;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  align-self: flex-start;
  letter-spacing: 0.02em;
}
.expiry-card-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 4px;
}
.btn-dispose-item {
  background: #8b0000;
  color: #fff;
  border: none;
  padding: 8px 20px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: Poppins, sans-serif;
  transition: opacity 0.15s;
}
.btn-dispose-item:hover { opacity: 0.85; }
.btn-dispose-item:disabled { opacity: 0.5; cursor: not-allowed; }
.expiry-empty-msg {
  color: #9ca3af;
  font-size: 13px;
  font-style: italic;
  padding: 4px 0;
}

/* Date filter (Guest tab — shown/hidden by tab JS) */
.found-guest-filter {
  display: flex;
  align-items: center;
}
.found-filter-date {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 7px;
  font-family: Poppins, sans-serif;
  font-size: 12px;
  color: #374151;
  background: #fff;
  cursor: pointer;
}

/* Table: horizontal scroll on small screens */
.table-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.found-table {
  min-width: 680px;
  width: 100%;
  border-collapse: collapse;
}
.found-table th,
.found-table td {
  white-space: nowrap;
  padding: 10px 12px;
  font-size: 13px;
}

/* ── Issue 5: Even-width View / Cancel action buttons ── */
.found-action-cell {
  display: flex;
  gap: 6px;
  align-items: center;
}
.found-btn-view,
.found-btn-cancel {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 68px;
  padding: 5px 0;
  border-radius: 6px;
  font-family: Poppins, sans-serif;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  flex: 1 1 0;
}
.found-btn-view   { background: #8b0000; color: #fff; }
.found-btn-cancel { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }

/* Responsive: buttons stay centered on small screens */
@media (max-width: 640px) {
  .found-tabs-actions-row { flex-wrap: wrap; }
  .found-actions          { margin-left: 0; }
  .found-btn              { font-size: 11px; padding: 5px 9px; }
  .expiry-cards-grid      { grid-template-columns: 1fr; }
}

/* ── Guest Item Details Modal ────────────────────────────────────────────── */
.guest-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 1500;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,0.5);
}
.guest-modal-overlay.open { display: flex; }
.guest-modal {
  background: #fff;
  border-radius: 12px;
  width: min(640px, 96vw);
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 16px 48px rgba(0,0,0,0.22);
  display: flex;
  flex-direction: column;
}
.guest-modal-header {
  background: #8b0000;
  border-radius: 12px 12px 0 0;
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.guest-modal-header-title {
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  margin: 0;
}
.guest-modal-header-close {
  background: none;
  border: none;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 4px;
  line-height: 1;
  opacity: 0.85;
  transition: opacity 0.15s;
}
.guest-modal-header-close:hover { opacity: 1; }
.guest-modal-body {
  display: flex;
  gap: 0;
  padding: 0;
  flex: 1;
}
.guest-modal-left {
  width: 35%;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding: 28px 16px 24px;
  border-right: 1px solid #e5e7eb;
  background: #fafafa;
  border-radius: 0 0 0 12px;
}
.guest-modal-photo {
  width: 140px;
  height: 100px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #e0e0e0;
  background: #f3f4f6;
}
.guest-modal-photo-placeholder {
  width: 140px;
  height: 100px;
  background: #f3f4f6;
  border-radius: 6px;
  border: 1px solid #e0e0e0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: #9ca3af;
  font-size: 11px;
}
.guest-modal-barcode-label {
  margin-top: 10px;
  font-size: 13px;
  color: #374151;
  font-weight: 500;
  text-align: center;
}
.guest-modal-right {
  flex: 1;
  padding: 28px 28px 24px;
  display: flex;
  flex-direction: column;
  border-radius: 0 0 12px 0;
}
.guest-modal-section-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 18px;
  text-align: center;
}
.guest-modal-info-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #f3f4f6;
}
.guest-modal-info-row:last-child { border-bottom: none; }
.guest-modal-info-label {
  font-size: 13px;
  color: #6b7280;
  flex-shrink: 0;
  min-width: 120px;
}
.guest-modal-info-value {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  text-align: right;
  flex: 1;
}
@media (max-width: 520px) {
  .guest-modal-body { flex-direction: column; }
  .guest-modal-left { width: 100%; border-right: none; border-bottom: 1px solid #e5e7eb; border-radius: 0; }
  .guest-modal-right { border-radius: 0 0 12px 12px; }
}
</style>
</head>
<body>
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
            <li>
                <a class="nav-item" href="AdminDashboard.php">
                    <div class="nav-item-icon"><i class="fa-solid fa-house"></i></div>
                    <div class="nav-item-label">Dashboard</div>
                </a>
            </li>
            <li>
                <a class="nav-item active" href="FoundAdmin.php">
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
                <a class="nav-item" href="ItemMatchedAdmin.php">
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

    <main class="main">
        <div class="topbar topbar-maroon">
            <div class="topbar-search-wrap topbar-search-left">
                <form class="search-form" action="FoundAdmin.php" method="get">
                    <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search" autocomplete="off">
                    <button id="adminSearchClear" class="search-clear" type="button" title="Clear" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
                    <button class="search-submit" type="submit" title="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>
            <div class="topbar-right">
                <?php include __DIR__ . '/includes/notifications_dropdown.php'; ?>
                <div class="admin-dropdown" id="adminDropdown">
                    <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger" aria-expanded="false" aria-haspopup="true" aria-label="Admin menu">
                        <i class="fa-regular fa-user"></i>
                        <span class="admin-name">Admin</span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 11px;"></i>
                    </button>
                    <div class="admin-dropdown-menu" role="menu">
                        <a href="logout.php" role="menuitem" class="admin-dropdown-item"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content-wrap">
        <div class="found-header-row">
            <h2 class="page-title">Found Items</h2>
            <div class="found-retention-bar">
                <span class="found-retention-text">There are <strong><?php echo (int)$overdueCount; ?></strong> Item<?php echo $overdueCount !== 1 ? 's' : ''; ?> that have exceeded the retention policy.</span>
                <?php if (!empty($expiringItems)): ?>
                <a href="#" class="found-dispose-link" id="expiryTriggerLink">View Items</a>
                <?php endif; ?>
            </div>
            <!-- Single row: tabs + category filter + guest date filter + action buttons (right-aligned) -->
            <div class="found-tabs-actions-row">
                <div class="found-tabs">
                    <span class="found-tab-text found-tab-active" id="allItemsTab">All Items</span>
                    <span class="found-tab-text" id="guestItemsTab">Guest Items</span>
                </div>
                <select class="found-filter-select" id="foundCategoryFilter" aria-label="Filter by category">
                    <option value="">All Categories</option>
                    <?php
                    $filterCategories = require dirname(__DIR__) . '/config/categories.php';
                    foreach ($filterCategories as $fc):
                    ?>
                    <option value="<?php echo htmlspecialchars($fc); ?>"><?php echo htmlspecialchars($fc); ?></option>
                    <?php endforeach; ?>
                </select>
                <!-- guestItemsFilter ID must stay here — tab-switching JS shows/hides it -->
                <div class="found-guest-filter" id="guestItemsFilter" style="display: none;">
                    <select class="found-filter-date" id="guestFilterByDate" aria-label="Filter by date">
                        <option value="">Filter By Date</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="3months">Last 3 Months</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <!-- Action buttons pushed right via margin-left:auto on .found-actions -->
                <!-- allItemsActions ID must stay — tab-switching JS references it -->
                <div class="found-actions" id="allItemsActions">
                    <button type="button" class="found-btn found-btn-encode" id="encodeNewItemBtn"><i class="fa-solid fa-plus"></i> Encode Item</button>
                    <button type="button" class="found-btn found-btn-report" id="encodeReportBtn"><i class="fa-solid fa-file-pen"></i> Encode Report</button>
                </div>
            </div>
        </div>

        <!-- Recovered Items (Internal) - shown when All Items tab active -->
        <div class="found-card found-section-internal" id="recoveredSection">
            <div class="found-title">Recovered Items (Internal)</div>
            <div class="table-wrapper">
                <table class="found-table">
                <thead>
                <tr>
                    <th>Barcode ID</th>
                    <th>Category</th>
                    <th>Found At</th>
                    <th>Date Found</th>
                    <th>Retention End</th>
                    <th>Storage Location</th>
                    <th>Timestamp</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="inventoryTableBody">
                <?php
                if (empty($encodedItems)) {
                    echo '<tr><td colspan="8" class="table-empty">No items yet. Click Encode Item to add one.</td></tr>';
                } else {
                    foreach ($encodedItems as $it) {
                        $barcodeId = htmlspecialchars($it['id'] ?? '');
                        $cat = htmlspecialchars($it['item_type'] ?? '');
                        $foundAt = htmlspecialchars($it['found_at'] ?? '');
                        $dateEncoded = $it['dateEncoded'] ?? '';
                        $retentionEnd = $dateEncoded ? date('Y-m-d', strtotime($dateEncoded . ' +2 years')) : '';
                        $storage = htmlspecialchars($it['storage_location'] ?? '');
                        $timestamp = htmlspecialchars($it['created_at'] ?? $it['dateEncoded'] ?? '');
                        if ($timestamp && strlen($timestamp) === 10) $timestamp .= ' 00:00:00';
                        $img = isset($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'], ENT_QUOTES, 'UTF-8') : '';
                        $color = htmlspecialchars($it['color'] ?? '');
                        $brand = htmlspecialchars($it['brand'] ?? '');
                        $foundBy = htmlspecialchars($it['found_by'] ?? '');
                        $dataAttrs = ' data-id="' . $barcodeId . '" data-color="' . $color . '" data-brand="' . $brand . '" data-found-by="' . $foundBy . '" data-date-encoded="' . htmlspecialchars($dateEncoded) . '" data-category="' . $cat . '" data-storage-location="' . htmlspecialchars($storage) . '"';
                        if ($img) $dataAttrs .= ' data-image="' . $img . '"';
                        $rawStorage = $it['storage_location'] ?? '';
                        $cancelBtn = (trim($rawStorage) === '') ? '<button type="button" class="found-btn-cancel">Cancel</button>' : '';
                        echo '<tr' . $dataAttrs . '><td>' . $barcodeId . '</td><td>' . $cat . '</td><td>' . $foundAt . '</td><td>' . htmlspecialchars($dateEncoded) . '</td><td>' . htmlspecialchars($retentionEnd) . '</td><td>' . $storage . '</td><td>' . $timestamp . '</td><td class="found-action-cell"><button type="button" class="found-btn-view">View</button>' . $cancelBtn . '</td></tr>';
                    }
                }
                ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Guest Items - shown when Guest Items tab active -->
        <div class="found-card found-section-guest" id="guestSection" style="display: none;">
            <div class="found-title found-title-guest">Recovered IDs (External)</div>
            <div class="table-wrapper">
                <table class="found-table found-table-guest">
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
                <tbody id="guestTableBody">
                <?php
                if (empty($guestItems)) {
                    echo '<tr><td colspan="7" class="table-empty">No guest items.</td></tr>';
                } else {
                    foreach ($guestItems as $it) {
                        $barcodeId       = htmlspecialchars($it['id'] ?? '');
                        $encodedBy       = htmlspecialchars($it['found_by'] ?? '');
                        $dateSurrendered = $it['dateEncoded'] ?? $it['date_encoded'] ?? '';
                        $retentionEnd    = $dateSurrendered ? date('Y-m-d', strtotime($dateSurrendered . ' +1 year')) : '';
                        $storage         = htmlspecialchars($it['storage_location'] ?? '');
                        $timestamp       = htmlspecialchars($it['created_at'] ?? $it['dateEncoded'] ?? '');
                        if ($timestamp && strlen($timestamp) === 10) $timestamp .= ' 00:00:00';
                        $img    = !empty($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'], ENT_QUOTES, 'UTF-8')
                                : (!empty($it['image_data'])   ? htmlspecialchars($it['image_data'],   ENT_QUOTES, 'UTF-8') : '');
                        $color  = htmlspecialchars($it['color'] ?? '');
                        $brand  = htmlspecialchars($it['brand'] ?? '');
                        $foundBy = htmlspecialchars($it['found_by'] ?? '');
                        // Parse ID Type and Fullname out of item_description
                        $gDesc    = $it['item_description'] ?? $it['itemDescription'] ?? '';
                        $gIdType  = preg_match('/^ID Type:\s*(.+?)(?:\n|$)/m',  $gDesc, $gm) ? trim($gm[1]) : '';
                        $gFullname = preg_match('/^Fullname:\s*(.+?)(?:\n|$)/m', $gDesc, $gm) ? trim($gm[1]) : '';
                        $dataAttrs = ' data-id="' . $barcodeId
                            . '" data-color="'            . $color
                            . '" data-found-by="'         . $foundBy
                            . '" data-date-encoded="'     . htmlspecialchars($dateSurrendered)
                            . '" data-category="'         . htmlspecialchars($it['item_type'] ?? '')
                            . '" data-storage-location="' . htmlspecialchars($storage)
                            . '" data-id-type="'          . htmlspecialchars($gIdType)
                            . '" data-fullname="'         . htmlspecialchars($gFullname) . '"';
                        if ($img) $dataAttrs .= ' data-image="' . $img . '"';
                        $rawStorageG = $it['storage_location'] ?? '';
                        $cancelBtnG  = (trim($rawStorageG) === '') ? '<button type="button" class="found-btn-cancel">Cancel</button>' : '';
                        echo '<tr' . $dataAttrs . '><td>' . $barcodeId . '</td><td>' . $encodedBy . '</td><td>' . htmlspecialchars($dateSurrendered) . '</td><td>' . htmlspecialchars($retentionEnd) . '</td><td>' . $storage . '</td><td>' . $timestamp . '</td><td class="found-action-cell"><button type="button" class="found-btn-view guest-view-btn">View</button>' . $cancelBtnG . '</td></tr>';
                    }
                }
                ?>
                </tbody>
            </table>
            </div>
        </div>

        </div>
    </main>
</div>

<!-- Expiry Items popup (items approaching retention end within 30 days) -->
<div class="expiry-overlay" id="expiryOverlay" role="dialog" aria-modal="true" aria-labelledby="expiryPopupTitle" onclick="if(event.target===this)document.getElementById('expiryOverlay').classList.remove('open')">
    <div class="expiry-popup">
        <div class="expiry-popup-hdr">
            <span class="expiry-popup-title" id="expiryPopupTitle">Items with Approaching Retention Dates</span>
            <button type="button" class="expiry-popup-close" id="expiryPopupClose" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
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
    $eiImg   = isset($ei['imageDataUrl']) ? htmlspecialchars($ei['imageDataUrl'], ENT_QUOTES, 'UTF-8') : '';
    $eiColor = htmlspecialchars($ei['color'] ?? '');
    $eiStore = htmlspecialchars($ei['storage_location'] ?? '');
?>
            <div class="expiry-card"
                 data-id="<?php echo $eiId; ?>"
                 data-category="<?php echo $eiCat; ?>"
                 data-color="<?php echo $eiColor; ?>"
                 data-storage-location="<?php echo $eiStore; ?>"
                 <?php if ($eiImg): ?>data-image="<?php echo $eiImg; ?>"<?php endif; ?>>
                <h4 class="expiry-card-title">
                    <i class="fa-regular fa-file-lines"></i>
                    <?php echo $eiCat; ?>
                </h4>
                <?php if ($eiShort): ?>
                <div class="expiry-card-desc">
                    <i class="fa-regular fa-file-lines"></i>
                    <span><?php echo $eiShort; ?></span>
                </div>
                <?php endif; ?>
                <div class="expiry-card-meta">
                    <i class="fa-solid fa-location-dot"></i>
                    <span><?php echo $eiLoc; ?></span>
                </div>
                <div class="expiry-card-meta">
                    <i class="fa-regular fa-calendar"></i>
                    <span>Expires: <?php echo $eiDate; ?></span>
                </div>
                <span class="expiry-card-badge">Expiring Soon</span>
                <div class="expiry-card-footer">
                    <button type="button" class="btn-dispose-item" data-dispose-id="<?php echo $eiId; ?>">Dispose Item</button>
                </div>
            </div>
<?php endforeach; ?>
<?php endif; ?>
        </div>
    </div>
</div>

<!-- Scan Item / Upload modal -->
<div id="scanUploadModal" class="scan-upload-overlay" role="dialog" aria-modal="true" aria-labelledby="scanUploadModalTitle">
    <div class="scan-upload-modal" onclick="event.stopPropagation()">
        <div class="scan-upload-header">
            <h2 id="scanUploadModalTitle" class="scan-upload-title">Scan Item / Upload</h2>
            <button type="button" class="scan-upload-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="scan-upload-body">
            <div class="scan-upload-frame" id="scanUploadFrame">
                <span class="scan-frame-corner scan-frame-tl"></span>
                <span class="scan-frame-corner scan-frame-tr"></span>
                <span class="scan-frame-corner scan-frame-bl"></span>
                <span class="scan-frame-corner scan-frame-br"></span>
                <video id="scanCameraVideo" class="scan-camera-video" autoplay playsinline muted></video>
                <div class="scan-upload-preview" id="scanUploadPreview"></div>
            </div>
            <p class="scan-upload-instruction">Use the camera to scan the item, or upload an image (JPG/PNG) below.</p>
            <div class="scan-upload-actions">
                <button type="button" class="scan-upload-btn scan-btn-camera" id="scanCameraBtn">
                    <i class="fa-solid fa-camera"></i>
                    <span id="scanCameraBtnText">Use camera</span>
                </button>
                <button type="button" class="scan-upload-btn scan-btn-capture" id="scanCaptureBtn" style="display: none;">
                    <i class="fa-solid fa-circle-dot"></i>
                    <span>Capture</span>
                </button>
                <button type="button" class="scan-upload-btn" id="scanUploadBtn">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    <span>Upload Image</span>
                </button>
                <button type="button" class="scan-upload-btn scan-btn-continue" id="scanContinueBtn" style="display: none;">
                    <i class="fa-solid fa-check"></i>
                    <span>Confirm</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Image modal -->
<div id="uploadImageModal" class="upload-image-overlay" role="dialog" aria-modal="true" aria-labelledby="uploadImageModalTitle">
    <div class="upload-image-modal" onclick="event.stopPropagation()">
        <div class="upload-image-header">
            <h2 id="uploadImageModalTitle" class="upload-image-title">Upload Image</h2>
            <button type="button" class="upload-image-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="upload-image-body">
            <div class="upload-image-dropzone" id="uploadImageDropzone">
                <input type="file" id="uploadImageInput" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="upload-image-input" aria-label="Choose image file">
                <i class="fa-solid fa-image upload-image-dropzone-icon"></i>
                <p class="upload-image-dropzone-text">Upload your file here.</p>
                <p class="upload-image-dropzone-hint">JPG or PNG</p>
            </div>
            <div class="upload-image-staged" id="uploadImageStaged">
                <div class="upload-image-staged-inner" id="uploadImageStagedInner">
                    <i class="fa-solid fa-file-image upload-image-file-icon"></i>
                    <div class="upload-image-file-info">
                        <span class="upload-image-file-name" id="uploadImageFileName"></span>
                        <span class="upload-image-file-size" id="uploadImageFileSize"></span>
                    </div>
                    <button type="button" class="upload-image-remove" id="uploadImageRemove" aria-label="Remove file"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="upload-image-footer">
                <button type="button" class="upload-image-cancel" id="uploadImageCancel">Cancel</button>
                <button type="button" class="upload-image-next" id="uploadImageNext">Next</button>
            </div>
        </div>
    </div>
</div>

<!-- Encode ID modal (Item Lost Report - Guest Items) -->
<div id="encodeIdModal" class="report-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeIdModalTitle">
    <div class="report-modal" onclick="event.stopPropagation()">
        <div class="report-modal-header">
            <h2 id="encodeIdModalTitle" class="report-modal-title">Item Lost Report</h2>
            <button type="button" class="report-modal-close encode-id-modal-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="encodeIdForm" class="report-modal-body">
            <div class="report-form-row">
                <label class="report-form-label" for="encodeBarcodeId">Barcode ID:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeBarcodeId" name="barcode_id" class="report-input" placeholder="e.g. UB0019">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeIdType">ID Type:</label>
                <div class="report-form-field">
                    <select id="encodeIdType" name="id_type" class="report-input report-select">
                        <option value="">— Select ID Type —</option>
                        <option value="Student ID">Student ID</option>
                        <option value="Faculty ID">Faculty ID</option>
                        <option value="Staff ID">Staff ID</option>
                        <option value="Employee ID">Employee ID</option>
                        <option value="Visitor ID">Visitor ID</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Passport">Passport</option>
                        <option value="SSS ID">SSS ID</option>
                        <option value="GSIS ID">GSIS ID</option>
                        <option value="PhilHealth ID">PhilHealth ID</option>
                        <option value="Pag-IBIG ID">Pag-IBIG ID</option>
                        <option value="Postal ID">Postal ID</option>
                        <option value="Voter's ID">Voter's ID</option>
                        <option value="Senior Citizen ID">Senior Citizen ID</option>
                        <option value="PWD ID">PWD ID</option>
                        <option value="National ID (PhilSys)">National ID (PhilSys)</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeFullname">Fullname: <span class="report-required">*</span></label>
                <div class="report-form-field">
                    <input type="text" id="encodeFullname" name="fullname" class="report-input" required placeholder="As printed on the ID">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeColor">Color: <span class="report-required">*</span></label>
                <div class="report-form-field">
                    <input type="text" id="encodeColor" name="color" class="report-input" required placeholder="e.g. Blue, White">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeStorageLocation">Storage Location:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeStorageLocation" name="storage_location" class="report-input" placeholder="e.g. Shelf A-1">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeEncodedBy">Encoded By:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeEncodedBy" name="encoded_by" class="report-input" placeholder="e.g. J. Dela Cruz">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeDateSurrendered">Date Surrendered:</label>
                <div class="report-form-field report-date-wrap">
                    <input type="date" id="encodeDateSurrendered" name="date_surrendered" class="report-input report-date" title="Pick a date">
                    <span class="report-date-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                </div>
            </div>
            <!-- Photo indicator — shown after image captured/uploaded, hidden initially -->
            <div class="report-form-row" id="encodeIdPhotoRow" style="display:none;">
                <label class="report-form-label">Photo:</label>
                <div class="report-form-field" style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-circle-check" style="color:#16a34a;font-size:15px;"></i>
                    <span id="encodeIdPhotoLabel" style="font-size:13px;color:#374151;font-weight:500;">Photo ready</span>
                    <button type="button" id="encodeIdPhotoRemove" title="Remove photo"
                            style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:14px;padding:0 4px;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-btn-cancel encode-id-cancel" id="encodeIdCancel">Cancel</button>
                <!-- Next: validate → open scan modal -->
                <button type="button" class="report-btn-confirm" id="encodeIdNext">Next</button>
                <!-- Confirm: shown after photo acquired, replaces Next -->
                <button type="button" class="report-btn-confirm" id="encodeIdDirectConfirm"
                        style="display:none;background:#16a34a;">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Encode ID: Scan Item / Upload modal -->
<div id="encodeIdScanModal" class="scan-upload-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeIdScanModalTitle">
    <div class="scan-upload-modal" onclick="event.stopPropagation()">
        <div class="scan-upload-header">
            <h2 id="encodeIdScanModalTitle" class="scan-upload-title">Scan Item / Upload</h2>
            <button type="button" class="scan-upload-close encode-id-scan-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="scan-upload-body">
            <div class="scan-upload-frame encode-id-scan-frame" id="encodeIdScanFrame">
                <span class="scan-frame-corner scan-frame-tl"></span>
                <span class="scan-frame-corner scan-frame-tr"></span>
                <span class="scan-frame-corner scan-frame-bl"></span>
                <span class="scan-frame-corner scan-frame-br"></span>
                <video id="encodeIdCameraVideo" class="scan-camera-video" autoplay playsinline muted></video>
                <div class="scan-upload-preview" id="encodeIdScanPreview"></div>
            </div>
            <p class="scan-upload-instruction">Align the Item within the frame to scan</p>
            <div class="scan-upload-actions">
                <button type="button" class="scan-upload-btn scan-btn-camera" id="encodeIdCameraBtn">
                    <i class="fa-solid fa-camera"></i>
                    <span id="encodeIdCameraBtnText">Use Camera</span>
                </button>
                <button type="button" class="scan-upload-btn scan-btn-capture" id="encodeIdCaptureBtn" style="display: none;">
                    <i class="fa-solid fa-circle-dot"></i>
                    <span>Capture</span>
                </button>
                <button type="button" class="scan-upload-btn" id="encodeIdUploadBtn">
                    <i class="fa-solid fa-image"></i>
                    <span>Upload Image</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Encode ID: Upload Image modal -->
<div id="encodeIdUploadModal" class="upload-image-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeIdUploadModalTitle">
    <div class="upload-image-modal" onclick="event.stopPropagation()">
        <div class="upload-image-header">
            <h2 id="encodeIdUploadModalTitle" class="upload-image-title">Upload Image</h2>
            <button type="button" class="upload-image-close encode-id-upload-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="upload-image-body">
            <div class="upload-image-dropzone" id="encodeIdUploadDropzone">
                <input type="file" id="encodeIdUploadInput" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="upload-image-input" aria-label="Choose image file">
                <i class="fa-solid fa-image upload-image-dropzone-icon"></i>
                <p class="upload-image-dropzone-text">Upload your file here.</p>
                <p class="upload-image-dropzone-hint">JPG or PNG</p>
            </div>
            <div class="upload-image-staged" id="encodeIdUploadStaged">
                <div class="upload-image-staged-inner" id="encodeIdUploadStagedInner">
                    <i class="fa-solid fa-file-image upload-image-file-icon"></i>
                    <div class="upload-image-file-info">
                        <span class="upload-image-file-name" id="encodeIdUploadFileName"></span>
                        <span class="upload-image-file-size" id="encodeIdUploadFileSize"></span>
                    </div>
                    <button type="button" class="upload-image-remove" id="encodeIdUploadRemove" aria-label="Remove file"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="upload-image-footer">
                <button type="button" class="upload-image-cancel encode-id-upload-cancel" id="encodeIdUploadCancel">Cancel</button>
                <button type="button" class="upload-image-next encode-id-upload-next" id="encodeIdUploadNext">Next</button>
            </div>
        </div>
    </div>
</div>

<!-- Encode ID: Confirmation form (Item Lost Report - review before posting) -->
<div id="encodeIdConfirmModal" class="report-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeIdConfirmModalTitle">
    <div class="report-modal" onclick="event.stopPropagation()">
        <div class="report-modal-header">
            <h2 id="encodeIdConfirmModalTitle" class="report-modal-title">Item Lost Report</h2>
            <button type="button" class="report-modal-close encode-id-confirm-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="encodeIdConfirmForm" class="report-modal-body">
            <div class="report-form-row">
                <label class="report-form-label" for="confirmBarcodeId">Barcode ID:</label>
                <div class="report-form-field">
                    <input type="text" id="confirmBarcodeId" name="barcode_id" class="report-input" readonly>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmIdType">ID Type:</label>
                <div class="report-form-field">
                    <select id="confirmIdType" name="id_type" class="report-input report-select">
                        <option value="">Select</option>
                        <option value="Student ID">Student ID</option>
                        <option value="Faculty ID">Faculty ID</option>
                        <option value="Staff ID">Staff ID</option>
                        <option value="Visitor ID">Visitor ID</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmFullname">Fullname:</label>
                <div class="report-form-field">
                    <input type="text" id="confirmFullname" name="fullname" class="report-input" required>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmColor">Color:</label>
                <div class="report-form-field">
                    <input type="text" id="confirmColor" name="color" class="report-input" required>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmStorageLocation">Storage Location:</label>
                <div class="report-form-field">
                    <input type="text" id="confirmStorageLocation" name="storage_location" class="report-input" placeholder="e.g. Shelf A-1">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmEncodedBy">Encoded By:</label>
                <div class="report-form-field">
                    <input type="text" id="confirmEncodedBy" name="encoded_by" class="report-input" placeholder="e.g. J. Dela Cruz">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmDateSurrendered">Date Surrendered:</label>
                <div class="report-form-field report-date-wrap">
                    <input type="date" id="confirmDateSurrendered" name="date_surrendered" class="report-input report-date" title="Pick a date">
                    <span class="report-date-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="confirmImage">Image:</label>
                <div class="report-form-field encode-image-field" id="confirmImageField">
                    <span class="encode-image-name" id="confirmImageName"></span>
                    <button type="button" class="encode-image-remove" id="confirmImageRemove" aria-label="Remove file" style="display: none;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-btn-cancel encode-id-confirm-cancel" id="encodeIdConfirmCancel">Cancel</button>
                <button type="button" class="report-btn-confirm encode-id-confirm-btn" id="encodeIdConfirmBtn">Next</button>
            </div>
        </form>
    </div>
</div>

<!-- Encode ID: Success modal -->
<div id="encodeIdSuccessModal" class="success-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeIdSuccessModalTitle">
    <div class="success-modal" onclick="event.stopPropagation()">
        <button type="button" class="success-modal-close" id="encodeIdSuccessClose" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        <div class="success-modal-icon"><i class="fa-solid fa-check"></i></div>
        <h3 id="encodeIdSuccessModalTitle" class="success-modal-title">Success</h3>
        <p class="success-modal-message">Item has been encoded successfully!</p>
        <p class="success-modal-barcode" id="encodeIdSuccessBarcode">Barcode ID: <strong></strong></p>
        <div class="success-modal-footer">
            <button type="button" class="report-btn-cancel success-modal-cancel" id="encodeIdSuccessCancel">Cancel</button>
            <button type="button" class="report-btn-confirm success-modal-confirm" id="encodeIdSuccessConfirm">Confirm</button>
        </div>
    </div>
</div>

<!-- Encode Report modal (Item Lost Report) -->
<div id="encodeReportModal" class="report-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeReportModalTitle">
    <div class="report-modal" onclick="event.stopPropagation()">
        <div class="report-modal-header">
            <h2 id="encodeReportModalTitle" class="report-modal-title">Item Lost Report</h2>
            <button type="button" class="report-modal-close encode-report-modal-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="encodeReportForm" class="report-modal-body">
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportCategory">Category:</label>
                <div class="report-form-field">
                    <select id="encodeReportCategory" name="category" class="report-input report-select">
                        <option value="">Select</option>
                        <?php
                        $itemCategories = require dirname(__DIR__) . '/config/categories.php';
                        foreach ($itemCategories as $c):
                        ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportFullName">Full Name:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportFullName" name="full_name" class="report-input">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportContactNumber">Contact Number:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportContactNumber" name="contact_number" class="report-input" required>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportDepartment">Department:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportDepartment" name="department" class="report-input" required>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportId">ID:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportId" name="id" class="report-input">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportItem">Item:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportItem" name="item" class="report-input">
                </div>
            </div>
            <div class="report-form-row report-form-row-textarea">
                <label class="report-form-label" for="encodeReportItemDescription">Item Description:</label>
                <div class="report-form-field">
                    <textarea id="encodeReportItemDescription" name="item_description" class="report-input report-textarea" rows="4" required></textarea>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportColor">Color:</label>
                <div class="report-form-field">
                    <select id="encodeReportColor" name="color" class="report-input report-select">
                        <option value="">Select</option>
                        <option value="Black">Black</option>
                        <option value="White">White</option>
                        <option value="Blue">Blue</option>
                        <option value="Red">Red</option>
                        <option value="Green">Green</option>
                        <option value="Gray">Gray</option>
                        <option value="Brown">Brown</option>
                        <option value="Silver">Silver</option>
                        <option value="Gold">Gold</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportBrand">Brand:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportBrand" name="brand" class="report-input">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportFoundAt">Found At:</label>
                <div class="report-form-field">
                    <select id="encodeReportFoundAt" name="found_at" class="report-input report-select">
                        <option value="">Select</option>
                        <option value="H102">H102</option>
                        <option value="H205">H205</option>
                        <option value="Canteen">Canteen</option>
                        <option value="Library">Library</option>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportStorageLocation">Storage Location:</label>
                <div class="report-form-field">
                    <input type="text" id="encodeReportStorageLocation" name="storage_location" class="report-input" placeholder="e.g. Shelf A-1">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="encodeReportDateLost">Date Lost:</label>
                <div class="report-form-field report-date-wrap">
                    <input type="date" id="encodeReportDateLost" name="date_lost" class="report-input report-date" title="Pick a date">
                    <span class="report-date-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-btn-cancel encode-report-cancel" id="encodeReportCancel">Cancel</button>
                <button type="button" class="report-btn-confirm encode-report-next" id="encodeReportNext">Next</button>
            </div>
        </form>
    </div>
</div>

<!-- Encode Report: Scan Item / Upload modal -->
<div id="encodeReportScanModal" class="scan-upload-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeReportScanModalTitle">
    <div class="scan-upload-modal" onclick="event.stopPropagation()">
        <div class="scan-upload-header">
            <h2 id="encodeReportScanModalTitle" class="scan-upload-title">Scan Item / Upload</h2>
            <button type="button" class="scan-upload-close encode-report-scan-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="scan-upload-body">
            <div class="scan-upload-frame encode-report-scan-frame" id="encodeReportScanFrame">
                <span class="scan-frame-corner scan-frame-tl"></span>
                <span class="scan-frame-corner scan-frame-tr"></span>
                <span class="scan-frame-corner scan-frame-bl"></span>
                <span class="scan-frame-corner scan-frame-br"></span>
                <video id="encodeReportCameraVideo" class="scan-camera-video" autoplay playsinline muted></video>
                <div class="scan-upload-preview" id="encodeReportScanPreview"></div>
            </div>
            <p class="scan-upload-instruction">Align the Item within the frame to scan</p>
            <div class="scan-upload-actions">
                <button type="button" class="scan-upload-btn scan-btn-camera" id="encodeReportCameraBtn">
                    <i class="fa-solid fa-camera"></i>
                    <span id="encodeReportCameraBtnText">Use Camera</span>
                </button>
                <button type="button" class="scan-upload-btn scan-btn-capture" id="encodeReportCaptureBtn" style="display: none;">
                    <i class="fa-solid fa-circle-dot"></i>
                    <span>Capture</span>
                </button>
                <button type="button" class="scan-upload-btn" id="encodeReportUploadBtn">
                    <i class="fa-solid fa-image"></i>
                    <span>Upload Image</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Encode Report: Confirmation form (before submit) -->
<div id="encodeReportConfirmModal" class="report-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeReportConfirmModalTitle">
    <div class="report-modal" onclick="event.stopPropagation()">
        <div class="report-modal-header">
            <h2 id="encodeReportConfirmModalTitle" class="report-modal-title">Item Lost Report</h2>
            <button type="button" class="report-modal-close encode-report-confirm-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="encodeReportConfirmForm" class="report-modal-body">
            <div class="report-form-row">
                <label class="report-form-label">Category:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportCategory"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Full Name:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportFullName"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Contact Number:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportContactNumber"></span>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Department:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportDepartment"></span>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">ID:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportId"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Item:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportItem"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Item Description:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportItemDescription"></span>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Color:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportColor"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Brand:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportBrand"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Found At:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportFoundAt"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Storage Location:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportStorageLocation"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Date Lost:</label>
                <div class="report-form-field">
                    <span class="report-confirm-value" id="confirmReportDateLost"></span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label">Upload Image:</label>
                <div class="report-form-field encode-image-field">
                    <span class="encode-image-name" id="confirmReportImageName"></span>
                </div>
            </div>
            <div class="report-form-row report-form-row-checkbox">
                <div class="report-form-field">
                    <label class="report-checkbox-label">
                        <input type="checkbox" id="encodeReportAuthorizeCheck" name="authorize">
                        <span>I hereby authorize that the above details are accurate and correct.</span>
                    </label>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-btn-cancel encode-report-confirm-cancel" id="encodeReportConfirmCancel">Cancel</button>
                <button type="button" class="report-btn-confirm encode-report-confirm-submit" id="encodeReportConfirmSubmit" disabled>Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Encode Report: Success modal (auto-closes after 3 sec, no buttons) -->
<div id="encodeReportSuccessModal" class="success-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeReportSuccessModalTitle">
    <div class="success-modal success-modal-no-footer" onclick="event.stopPropagation()">
        <button type="button" class="success-modal-close" id="encodeReportSuccessClose" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        <div class="success-modal-icon"><i class="fa-solid fa-check"></i></div>
        <h3 id="encodeReportSuccessModalTitle" class="success-modal-title">Success</h3>
        <p class="success-modal-message">Report has been submitted successfully!</p>
        <p class="success-modal-barcode" id="encodeReportSuccessRefId">REF: <strong></strong></p>
    </div>
</div>

<!-- Encode Report: Upload Image modal -->
<div id="encodeReportUploadModal" class="upload-image-overlay" role="dialog" aria-modal="true" aria-labelledby="encodeReportUploadModalTitle">
    <div class="upload-image-modal" onclick="event.stopPropagation()">
        <div class="upload-image-header">
            <h2 id="encodeReportUploadModalTitle" class="upload-image-title">Upload Image</h2>
            <button type="button" class="upload-image-close encode-report-upload-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="upload-image-body">
            <div class="upload-image-dropzone" id="encodeReportUploadDropzone">
                <input type="file" id="encodeReportUploadInput" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="upload-image-input" aria-label="Choose image file">
                <i class="fa-solid fa-image upload-image-dropzone-icon"></i>
                <p class="upload-image-dropzone-text">Upload your file here.</p>
                <p class="upload-image-dropzone-hint">JPG or PNG</p>
            </div>
            <div class="upload-image-staged" id="encodeReportUploadStaged">
                <div class="upload-image-staged-inner" id="encodeReportUploadStagedInner">
                    <i class="fa-solid fa-file-image upload-image-file-icon"></i>
                    <div class="upload-image-file-info">
                        <span class="upload-image-file-name" id="encodeReportUploadFileName"></span>
                        <span class="upload-image-file-size" id="encodeReportUploadFileSize"></span>
                    </div>
                    <button type="button" class="upload-image-remove" id="encodeReportUploadRemove" aria-label="Remove file"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="upload-image-footer">
                <button type="button" class="upload-image-cancel encode-report-upload-cancel" id="encodeReportUploadCancel">Cancel</button>
                <button type="button" class="upload-image-next encode-report-upload-next" id="encodeReportUploadNext">Next</button>
            </div>
        </div>
    </div>
</div>

<!-- Item Lost Report modal (Encode Item - Internal) -->
<div id="itemLostReportModal" class="report-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="itemLostReportTitle">
    <div class="report-modal" onclick="event.stopPropagation()">
        <div class="report-modal-header">
            <h2 id="itemLostReportTitle" class="report-modal-title">Item Lost Report</h2>
            <button type="button" class="report-modal-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="itemLostReportForm" class="report-modal-body">
            <div class="report-form-row">
                <label class="report-form-label" for="reportUserId">User ID:</label>
                <div class="report-form-field">
                    <input type="text" id="reportUserId" name="user_id" class="report-input" required>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportItemType">Category:</label>
                <div class="report-form-field">
                    <select id="reportItemType" name="item_type" class="report-input report-select">
                        <option value="">Select</option>
                        <?php
                        $itemCategories = require dirname(__DIR__) . '/config/categories.php';
                        foreach ($itemCategories as $c):
                        ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportItem">Item Type:</label>
                <div class="report-form-field">
                    <input type="text" id="reportItem" name="item" class="report-input" placeholder="e.g. Tumbler, Wallet, ID">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportColor">Color:</label>
                <div class="report-form-field">
                    <input type="text" id="reportColor" name="color" class="report-input">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportBrand">Brand:</label>
                <div class="report-form-field">
                    <input type="text" id="reportBrand" name="brand" class="report-input">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportFoundAt">Found At:</label>
                <div class="report-form-field">
                    <select id="reportFoundAt" name="found_at" class="report-input report-select">
                        <option value="">Select</option>
                        <option value="H102">H102</option>
                        <option value="H205">H205</option>
                        <option value="Canteen">Canteen</option>
                        <option value="Library">Library</option>
                    </select>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportFoundBy">Found By:</label>
                <div class="report-form-field">
                    <input type="text" id="reportFoundBy" name="found_by" class="report-input" placeholder="e.g. 200981@ub.edu.ph">
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportDateLost">Date Lost:</label>
                <div class="report-form-field report-date-wrap">
                    <input type="date" id="reportDateLost" name="date_lost" class="report-input report-date" title="Pick a date">
                    <span class="report-date-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                </div>
            </div>
            <div class="report-form-row report-form-row-textarea">
                <label class="report-form-label" for="reportDescription">Item Description:</label>
                <div class="report-form-field">
                    <textarea id="reportDescription" name="item_description" class="report-input report-textarea" rows="4" required></textarea>
                    <span class="report-required">*</span>
                </div>
            </div>
            <div class="report-form-row">
                <label class="report-form-label" for="reportStorageLocation">Storage Location:</label>
                <div class="report-form-field">
                    <input type="text" id="reportStorageLocation" name="storage_location" class="report-input" placeholder="e.g. Shelf A-1">
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-btn-cancel" id="reportCancel">Cancel</button>
                <button type="button" class="report-btn-confirm" id="reportNext">Next</button>
            </div>
        </form>
    </div>
</div>

<!-- Item Details modal -->
<!-- Guest Item Details Modal -->
<div id="guestViewModal" class="guest-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="guestViewModalTitle" onclick="if(event.target===this)closeGuestModal()">
    <div class="guest-modal" onclick="event.stopPropagation()">
        <div class="guest-modal-header">
            <h3 id="guestViewModalTitle" class="guest-modal-header-title">Item Details</h3>
            <button type="button" class="guest-modal-header-close" onclick="closeGuestModal()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="guest-modal-body">
            <!-- Left: photo + barcode -->
            <div class="guest-modal-left">
                <div id="guestModalPhotoWrap">
                    <div class="guest-modal-photo-placeholder" id="guestModalPhotoPlaceholder">
                        <i class="fa-regular fa-id-card" style="font-size:28px;"></i>
                        <span>No photo</span>
                    </div>
                    <img id="guestModalPhoto" class="guest-modal-photo" src="" alt="ID photo" style="display:none;">
                </div>
                <p class="guest-modal-barcode-label" id="guestModalBarcodeLabel">Barcode ID: —</p>
            </div>
            <!-- Right: general information -->
            <div class="guest-modal-right">
                <h4 class="guest-modal-section-title">General Information</h4>
                <div class="guest-modal-info-row">
                    <span class="guest-modal-info-label">ID Type:</span>
                    <span class="guest-modal-info-value" id="guestModalIdType">—</span>
                </div>
                <div class="guest-modal-info-row">
                    <span class="guest-modal-info-label">Fullname:</span>
                    <span class="guest-modal-info-value" id="guestModalFullname">—</span>
                </div>
                <div class="guest-modal-info-row">
                    <span class="guest-modal-info-label">Color:</span>
                    <span class="guest-modal-info-value" id="guestModalColor">—</span>
                </div>
                <div class="guest-modal-info-row">
                    <span class="guest-modal-info-label">Storage Location:</span>
                    <span class="guest-modal-info-value" id="guestModalStorageLocation">—</span>
                </div>
                <div class="guest-modal-info-row">
                    <span class="guest-modal-info-label">Encoded By:</span>
                    <span class="guest-modal-info-value" id="guestModalEncodedBy">—</span>
                </div>
                <div class="guest-modal-info-row">
                    <span class="guest-modal-info-label">Date Surrendered:</span>
                    <span class="guest-modal-info-value" id="guestModalDateSurrendered">—</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="viewModal" class="view-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="view-modal" onclick="event.stopPropagation()">
        <div class="view-modal-header">
            <h3 id="viewModalTitle" class="view-modal-title">Item Details</h3>
            <button type="button" class="view-modal-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="view-modal-content">
            <div class="view-modal-left">
                <h4 class="view-modal-section-title">General Information</h4>
                <div id="viewModalBody" class="view-modal-body"></div>
            </div>
            <div class="view-modal-right">
                <div id="viewModalImage" class="view-modal-image"></div>
                <div class="view-modal-print-wrap">
                    <button type="button" class="view-modal-cancel" id="viewModalCancel">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var LOSTANDFOUND_SAVE_GUEST_URL = <?php echo json_encode($saveGuestItemUrl); ?>;
var LOSTANDFOUND_SAVE_LOST_REPORT_URL = <?php echo json_encode($saveLostReportUrl); ?>;
var LOSTANDFOUND_DELETE_ITEM_URL = <?php echo json_encode($deleteItemUrl); ?>;
</script>
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
            trigger.setAttribute('aria-expanded', 'false');
        });
    }
})();

(function () {
    var input = document.getElementById('adminSearchInput');
    var clearBtn = document.getElementById('adminSearchClear');
    if (!input || !clearBtn) return;
    function syncClear() {
        clearBtn.style.display = input.value ? 'flex' : 'none';
    }
    clearBtn.addEventListener('click', function () {
        input.value = '';
        input.focus();
        syncClear();
    });
    input.addEventListener('input', syncClear);
    syncClear();
    var searchForm = input.closest('form');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            var q = (input.value || '').trim().toUpperCase();
            if (window.__encodedItems && q && window.__encodedItems[q]) {
                e.preventDefault();
                if (window.openViewModalForEncodedItem) window.openViewModalForEncodedItem(window.__encodedItems[q]);
            }
        });
    }
})();

(function () {
    var allItemsTab = document.getElementById('allItemsTab');
    var guestItemsTab = document.getElementById('guestItemsTab');
    var allItemsActions = document.getElementById('allItemsActions');
    var guestItemsFilter = document.getElementById('guestItemsFilter');
    var recoveredSection = document.getElementById('recoveredSection');
    var guestSection = document.getElementById('guestSection');
    if (!allItemsTab || !guestItemsTab || !recoveredSection || !guestSection) return;

    function showAllItems() {
        allItemsTab.classList.add('found-tab-active');
        guestItemsTab.classList.remove('found-tab-active');
        recoveredSection.style.display = '';
        guestSection.style.display = 'none';
        if (guestItemsFilter) guestItemsFilter.style.display = 'none';
    }
    function showGuestItems() {
        guestItemsTab.classList.add('found-tab-active');
        allItemsTab.classList.remove('found-tab-active');
        guestSection.style.display = '';
        recoveredSection.style.display = 'none';
        if (guestItemsFilter) guestItemsFilter.style.display = 'flex';
    }
    allItemsTab.addEventListener('click', showAllItems);
    guestItemsTab.addEventListener('click', showGuestItems);
    if (window.location.hash === '#guest') showGuestItems();
    window.addEventListener('hashchange', function () { if (window.location.hash === '#guest') showGuestItems(); else showAllItems(); });
})();

(function () {
    var encodeIdModal        = document.getElementById('encodeIdModal');
    var encodeIdNext         = document.getElementById('encodeIdNext');
    var encodeIdDirectConfirm = document.getElementById('encodeIdDirectConfirm');
    var encodeIdPhotoRow     = document.getElementById('encodeIdPhotoRow');
    var encodeIdPhotoLabel   = document.getElementById('encodeIdPhotoLabel');
    var encodeIdPhotoRemove  = document.getElementById('encodeIdPhotoRemove');
    var encodeIdScanModal    = document.getElementById('encodeIdScanModal');
    var encodeIdUploadModal  = document.getElementById('encodeIdUploadModal');
    if (!encodeIdModal) return;

    /* Stored form data and captured image */
    window.__encodeIdFormData  = null;
    window.__encodeIdImage     = null;   /* dataURL from camera or upload */

    /* ── Open / Close ─────────────────────────────────────────────────── */
    function openEncodeIdModal() {
        document.getElementById('encodeIdForm').reset();
        resetPhotoState();
        encodeIdModal.classList.add('report-modal-open');
    }
    function closeEncodeIdModal() {
        encodeIdModal.classList.remove('report-modal-open');
    }
    window.openEncodeIdModalFn = openEncodeIdModal;

    var encodeIdClose  = encodeIdModal.querySelector('.encode-id-modal-close');
    var encodeIdCancel = document.getElementById('encodeIdCancel');
    if (encodeIdClose)  encodeIdClose.addEventListener('click', closeEncodeIdModal);
    if (encodeIdCancel) encodeIdCancel.addEventListener('click', closeEncodeIdModal);
    encodeIdModal.addEventListener('click', function (e) { if (e.target === encodeIdModal) closeEncodeIdModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && encodeIdModal.classList.contains('report-modal-open')) closeEncodeIdModal();
    });

    /* ── Photo state helpers ──────────────────────────────────────────── */
    function resetPhotoState() {
        window.__encodeIdImage = null;
        if (encodeIdPhotoRow)    encodeIdPhotoRow.style.display    = 'none';
        if (encodeIdNext)        encodeIdNext.style.display        = '';
        if (encodeIdDirectConfirm) encodeIdDirectConfirm.style.display = 'none';
    }

    function setPhotoAcquired(dataUrl, label) {
        window.__encodeIdImage = dataUrl;
        if (encodeIdPhotoRow)    encodeIdPhotoRow.style.display    = 'flex';
        if (encodeIdPhotoLabel)  encodeIdPhotoLabel.textContent    = label || 'Photo ready';
        if (encodeIdNext)        encodeIdNext.style.display        = 'none';
        if (encodeIdDirectConfirm) encodeIdDirectConfirm.style.display = '';
    }
    window.__setEncodeIdPhotoAcquired = setPhotoAcquired;

    if (encodeIdPhotoRemove) {
        encodeIdPhotoRemove.addEventListener('click', function () {
            resetPhotoState();
        });
    }

    /* ── Success modal ────────────────────────────────────────────────── */
    var encodeIdSuccessModal    = document.getElementById('encodeIdSuccessModal');
    var encodeIdSuccessBarcodeEl = document.getElementById('encodeIdSuccessBarcode');
    var encodeIdSuccessTimer    = null;

    function closeEncodeIdSuccessModal() {
        if (encodeIdSuccessModal) encodeIdSuccessModal.classList.remove('success-modal-open');
        if (encodeIdSuccessTimer) { clearTimeout(encodeIdSuccessTimer); encodeIdSuccessTimer = null; }
    }
    function showEncodeIdSuccessModal(barcodeId) {
        if (!encodeIdSuccessModal || !encodeIdSuccessBarcodeEl) return;
        var strong = encodeIdSuccessBarcodeEl.querySelector('strong');
        if (strong) strong.textContent = barcodeId || '';
        encodeIdSuccessModal.classList.add('success-modal-open');
        encodeIdSuccessTimer = setTimeout(closeEncodeIdSuccessModal, 3500);
    }
    if (encodeIdSuccessModal) {
        ['encodeIdSuccessClose','encodeIdSuccessCancel','encodeIdSuccessConfirm'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', closeEncodeIdSuccessModal);
        });
        encodeIdSuccessModal.addEventListener('click', function (e) {
            if (e.target === encodeIdSuccessModal) closeEncodeIdSuccessModal();
        });
    }

    /* ── NEXT: validate → save form data → open scan modal ───────────── */
    if (encodeIdNext) {
        encodeIdNext.addEventListener('click', function () {
            var fullname = document.getElementById('encodeFullname');
            var color    = document.getElementById('encodeColor');
            if (!fullname || !fullname.value.trim()) { fullname.focus(); return; }
            if (!color    || !color.value.trim())    { color.focus();    return; }
            window.__encodeIdFormData = {
                barcode_id:       (document.getElementById('encodeBarcodeId')      || {}).value || '',
                id_type:          (document.getElementById('encodeIdType')          || {}).value || '',
                fullname:         fullname.value.trim(),
                color:            color.value.trim(),
                storage_location: (document.getElementById('encodeStorageLocation') || {}).value || '',
                encoded_by:       (document.getElementById('encodeEncodedBy')       || {}).value || '',
                date_surrendered: (document.getElementById('encodeDateSurrendered') || {}).value || ''
            };
            closeEncodeIdModal();
            if (encodeIdScanModal) encodeIdScanModal.classList.add('scan-upload-open');
        });
    }

    /* ── CONFIRM: submit to DB ────────────────────────────────────────── */
    if (encodeIdDirectConfirm) {
        encodeIdDirectConfirm.addEventListener('click', function () {
            var data = window.__encodeIdFormData;
            if (!data) return;
            var fullname = document.getElementById('encodeFullname');
            var color    = document.getElementById('encodeColor');
            if (!fullname || !fullname.value.trim()) { fullname.focus(); return; }
            if (!color    || !color.value.trim())    { color.focus();    return; }
            var payload = {
                barcode_id:       (document.getElementById('encodeBarcodeId')      || {}).value || data.barcode_id,
                id_type:          (document.getElementById('encodeIdType')          || {}).value || data.id_type,
                fullname:         fullname.value.trim(),
                color:            color.value.trim(),
                storage_location: (document.getElementById('encodeStorageLocation') || {}).value || data.storage_location,
                encoded_by:       (document.getElementById('encodeEncodedBy')       || {}).value || data.encoded_by,
                date_surrendered: (document.getElementById('encodeDateSurrendered') || {}).value || data.date_surrendered,
                imageDataUrl:     window.__encodeIdImage || null
            };
            submitGuestItem(payload);
        });
    }

    /* ── Submit to DB ─────────────────────────────────────────────────── */
    function submitGuestItem(payload) {
        var saveUrl = typeof LOSTANDFOUND_SAVE_GUEST_URL !== 'undefined'
            ? LOSTANDFOUND_SAVE_GUEST_URL : '../save_guest_item.php';
        var activeBtn = window.__encodeIdImage ? encodeIdDirectConfirm : encodeIdNext;
        if (activeBtn) activeBtn.disabled = true;

        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) {
                if (activeBtn) activeBtn.disabled = false;
                if (!res.ok) { alert('Could not save: ' + (data.error || res.status)); return; }
                closeEncodeIdModal();
                document.getElementById('encodeIdForm').reset();
                resetPhotoState();
                if (encodeIdScanModal)   encodeIdScanModal.classList.remove('scan-upload-open');
                if (encodeIdUploadModal) encodeIdUploadModal.classList.remove('upload-image-open');

                var newItem = {
                    id:               data.id,
                    id_type:          payload.id_type,
                    fullname:         payload.fullname,
                    encoded_by:       payload.encoded_by,
                    dateEncoded:      payload.date_surrendered || new Date().toISOString().slice(0, 10),
                    storage_location: payload.storage_location,
                    imageDataUrl:     payload.imageDataUrl,
                    color:            payload.color,
                    item_type:        'ID & Nameplate'
                };
                appendGuestItemRow(newItem);

                var guestItemsTab = document.getElementById('guestItemsTab');
                var guestSection  = document.getElementById('guestSection');
                if (guestItemsTab && guestSection && guestSection.style.display === 'none') {
                    guestItemsTab.click();
                }
                showEncodeIdSuccessModal(data.id);
            });
        }).catch(function (err) {
            if (activeBtn) activeBtn.disabled = false;
            alert('Could not save (network error). Try again.');
            console.error(err);
        });
    }

    /* ── appendGuestItemRow ───────────────────────────────────────────── */
    function appendGuestItemRow(item) {
        var tbody = document.getElementById('guestTableBody');
        if (!tbody) return;
        var emptyRow = tbody.querySelector('tr td[colspan]');
        if (emptyRow && emptyRow.parentNode) emptyRow.parentNode.removeChild(emptyRow.closest('tr'));

        var retEnd = item.dateEncoded
            ? (function(d){ var y=new Date(d); y.setFullYear(y.getFullYear()+1); return y.toISOString().slice(0,10); })(item.dateEncoded)
            : '';
        var timestamp = (item.created_at || item.dateEncoded || '').toString();
        if (timestamp && timestamp.length === 10) timestamp += ' 00:00:00';

        var tr = document.createElement('tr');
        tr.setAttribute('data-id',               item.id               || '');
        tr.setAttribute('data-color',            item.color            || '');
        tr.setAttribute('data-found-by',         item.encoded_by       || '');
        tr.setAttribute('data-date-encoded',     item.dateEncoded      || '');
        tr.setAttribute('data-category',         item.item_type        || '');
        tr.setAttribute('data-storage-location', item.storage_location || '');
        tr.setAttribute('data-id-type',          item.id_type          || '');
        tr.setAttribute('data-fullname',         item.fullname         || '');
        if (item.imageDataUrl) tr.setAttribute('data-image', item.imageDataUrl);

        tr.innerHTML = '<td>' + (item.id || '') + '</td>'
            + '<td>' + (item.encoded_by || '') + '</td>'
            + '<td>' + (item.dateEncoded || '') + '</td>'
            + '<td>' + retEnd + '</td>'
            + '<td>' + (item.storage_location || '') + '</td>'
            + '<td>' + timestamp + '</td>'
            + '<td class="found-action-cell"><button type="button" class="found-btn-view guest-view-btn">View</button>'
            + '<button type="button" class="found-btn-cancel">Cancel</button></td>';
        tbody.insertBefore(tr, tbody.firstChild);
    }
    window.__submitGuestItemEncodeId = submitGuestItem;
})();

(function () {
    var encodeIdScanModal = document.getElementById('encodeIdScanModal');
    var encodeIdUploadModal = document.getElementById('encodeIdUploadModal');
    var encodeIdConfirmModal = document.getElementById('encodeIdConfirmModal');
    var encodeIdScanClose = encodeIdScanModal && encodeIdScanModal.querySelector('.encode-id-scan-close');
    var encodeIdCameraBtn = document.getElementById('encodeIdCameraBtn');
    var encodeIdCameraBtnText = document.getElementById('encodeIdCameraBtnText');
    var encodeIdCaptureBtn = document.getElementById('encodeIdCaptureBtn');
    var encodeIdUploadBtn = document.getElementById('encodeIdUploadBtn');
    var encodeIdCameraVideo = document.getElementById('encodeIdCameraVideo');
    var encodeIdScanPreview = document.getElementById('encodeIdScanPreview');
    var encodeIdUploadClose = encodeIdUploadModal && encodeIdUploadModal.querySelector('.encode-id-upload-close');
    var encodeIdUploadDropzone = document.getElementById('encodeIdUploadDropzone');
    var encodeIdUploadInput = document.getElementById('encodeIdUploadInput');
    var encodeIdUploadStaged = document.getElementById('encodeIdUploadStaged');
    var encodeIdUploadFileName = document.getElementById('encodeIdUploadFileName');
    var encodeIdUploadFileSize = document.getElementById('encodeIdUploadFileSize');
    var encodeIdUploadRemove = document.getElementById('encodeIdUploadRemove');
    var encodeIdUploadCancel = document.getElementById('encodeIdUploadCancel');
    var encodeIdUploadNext = document.getElementById('encodeIdUploadNext');
    var encodeIdConfirmClose = encodeIdConfirmModal && encodeIdConfirmModal.querySelector('.encode-id-confirm-close');
    var encodeIdConfirmCancel = document.getElementById('encodeIdConfirmCancel');
    var encodeIdConfirmBtn = document.getElementById('encodeIdConfirmBtn');
    var confirmImageName = document.getElementById('confirmImageName');
    var confirmImageRemove = document.getElementById('confirmImageRemove');
    var cameraStream = null;
    var stagedEncodeIdFile = null;
    var encodeIdImageDataUrl = null;

    function stopEncodeIdCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (t) { t.stop(); });
            cameraStream = null;
        }
        if (encodeIdCameraVideo) encodeIdCameraVideo.srcObject = null;
        if (encodeIdCameraVideo) encodeIdCameraVideo.style.display = 'none';
        if (encodeIdScanPreview) encodeIdScanPreview.style.display = '';
        if (encodeIdCameraBtnText) encodeIdCameraBtnText.textContent = 'Use Camera';
        if (encodeIdCaptureBtn) encodeIdCaptureBtn.style.display = 'none';
    }

    function closeEncodeIdScanModal() {
        stopEncodeIdCamera();
        if (encodeIdScanModal) encodeIdScanModal.classList.remove('scan-upload-open');
    }

    if (encodeIdScanClose) encodeIdScanClose.addEventListener('click', closeEncodeIdScanModal);
    if (encodeIdScanModal) encodeIdScanModal.addEventListener('click', function (e) { if (e.target === encodeIdScanModal) closeEncodeIdScanModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && encodeIdScanModal && encodeIdScanModal.classList.contains('scan-upload-open')) closeEncodeIdScanModal(); });

    if (encodeIdCameraBtn) {
        encodeIdCameraBtn.addEventListener('click', function () {
            if (cameraStream) {
                stopEncodeIdCamera();
                return;
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera is not supported in this browser.');
                return;
            }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
                cameraStream = stream;
                if (encodeIdCameraVideo) { encodeIdCameraVideo.srcObject = stream; encodeIdCameraVideo.style.display = 'block'; }
                if (encodeIdScanPreview) { encodeIdScanPreview.innerHTML = ''; encodeIdScanPreview.style.display = 'none'; }
                if (encodeIdCameraBtnText) encodeIdCameraBtnText.textContent = 'Stop Camera';
                if (encodeIdCaptureBtn) encodeIdCaptureBtn.style.display = 'flex';
            }).catch(function () { alert('Could not access camera.'); });
        });
    }

    if (encodeIdCaptureBtn && encodeIdCameraVideo && encodeIdScanPreview) {
        encodeIdCaptureBtn.addEventListener('click', function () {
            if (!cameraStream || !encodeIdCameraVideo.videoWidth) return;
            var canvas = document.createElement('canvas');
            canvas.width = encodeIdCameraVideo.videoWidth;
            canvas.height = encodeIdCameraVideo.videoHeight;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(encodeIdCameraVideo, 0, 0);
            encodeIdImageDataUrl = canvas.toDataURL('image/jpeg');
            encodeIdScanPreview.innerHTML = '<img src="' + encodeIdImageDataUrl + '" alt="Captured">';
            encodeIdScanPreview.classList.add('has-image');
            encodeIdScanPreview.style.display = 'flex';
            stopEncodeIdCamera();
            closeEncodeIdScanModal();
            /* Return to the form with Confirm button */
            if (window.__setEncodeIdPhotoAcquired) window.__setEncodeIdPhotoAcquired(encodeIdImageDataUrl, 'Camera photo ready');
            var encodeIdModal = document.getElementById('encodeIdModal');
            if (encodeIdModal) encodeIdModal.classList.add('report-modal-open');
        });
    }

    if (encodeIdUploadBtn) {
        encodeIdUploadBtn.addEventListener('click', function () {
            if (encodeIdUploadStaged) encodeIdUploadStaged.style.display = 'none';
            if (encodeIdUploadInput) encodeIdUploadInput.value = '';
            stagedEncodeIdFile = null;
            if (encodeIdUploadModal) encodeIdUploadModal.classList.add('upload-image-open');
        });
    }

    function closeEncodeIdUploadModal() {
        if (encodeIdUploadModal) encodeIdUploadModal.classList.remove('upload-image-open');
        stagedEncodeIdFile = null;
        if (encodeIdUploadInput) encodeIdUploadInput.value = '';
        if (encodeIdUploadStaged) encodeIdUploadStaged.style.display = 'none';
    }

    if (encodeIdUploadClose) encodeIdUploadClose.addEventListener('click', closeEncodeIdUploadModal);
    if (encodeIdUploadModal) encodeIdUploadModal.addEventListener('click', function (e) { if (e.target === encodeIdUploadModal) closeEncodeIdUploadModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && encodeIdUploadModal && encodeIdUploadModal.classList.contains('upload-image-open')) closeEncodeIdUploadModal(); });
    if (encodeIdUploadCancel) encodeIdUploadCancel.addEventListener('click', closeEncodeIdUploadModal);

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    if (encodeIdUploadDropzone && encodeIdUploadInput) {
        encodeIdUploadDropzone.addEventListener('click', function () { encodeIdUploadInput.click(); });
        encodeIdUploadInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (file && (file.type === 'image/jpeg' || file.type === 'image/png' || /\.(jpe?g|png)$/i.test(file.name))) {
                stagedEncodeIdFile = file;
                if (encodeIdUploadFileName) encodeIdUploadFileName.textContent = file.name;
                if (encodeIdUploadFileSize) encodeIdUploadFileSize.textContent = formatSize(file.size);
                if (encodeIdUploadStaged) encodeIdUploadStaged.style.display = 'block';
            }
        });
        encodeIdUploadDropzone.addEventListener('dragover', function (e) { e.preventDefault(); encodeIdUploadDropzone.classList.add('upload-image-dragover'); });
        encodeIdUploadDropzone.addEventListener('dragleave', function () { encodeIdUploadDropzone.classList.remove('upload-image-dragover'); });
        encodeIdUploadDropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            encodeIdUploadDropzone.classList.remove('upload-image-dragover');
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file && (file.type === 'image/jpeg' || file.type === 'image/png' || /\.(jpe?g|png)$/i.test(file.name))) {
                stagedEncodeIdFile = file;
                if (encodeIdUploadFileName) encodeIdUploadFileName.textContent = file.name;
                if (encodeIdUploadFileSize) encodeIdUploadFileSize.textContent = formatSize(file.size);
                if (encodeIdUploadStaged) encodeIdUploadStaged.style.display = 'block';
            }
        });
    }

    if (encodeIdUploadRemove) encodeIdUploadRemove.addEventListener('click', function () {
        stagedEncodeIdFile = null;
        encodeIdUploadInput.value = '';
        encodeIdUploadStaged.style.display = 'none';
    });

    if (encodeIdUploadNext) {
        encodeIdUploadNext.addEventListener('click', function () {
            if (!stagedEncodeIdFile) return;
            var reader = new FileReader();
            reader.onload = function () {
                encodeIdImageDataUrl = reader.result;
                closeEncodeIdUploadModal();
                closeEncodeIdScanModal();
                /* Return to form with Confirm button */
                if (window.__setEncodeIdPhotoAcquired) window.__setEncodeIdPhotoAcquired(encodeIdImageDataUrl, 'Uploaded photo ready');
                var encodeIdModal = document.getElementById('encodeIdModal');
                if (encodeIdModal) encodeIdModal.classList.add('report-modal-open');
            };
            reader.readAsDataURL(stagedEncodeIdFile);
        });
    }

    function openEncodeIdConfirmModal(imageDataUrl) {
        var data = window.__encodeIdFormData;
        if (!data) return;
        var bid = document.getElementById('confirmBarcodeId');
        var idType = document.getElementById('confirmIdType');
        var fullname = document.getElementById('confirmFullname');
        var color = document.getElementById('confirmColor');
        var storage = document.getElementById('confirmStorageLocation');
        var encodedBy = document.getElementById('confirmEncodedBy');
        var dateSur = document.getElementById('confirmDateSurrendered');
        if (bid) bid.value = data.barcode_id || '';
        if (idType) idType.value = data.id_type || '';
        if (fullname) fullname.value = data.fullname || '';
        if (color) color.value = data.color || '';
        if (storage) storage.value = data.storage_location || '';
        if (encodedBy) encodedBy.value = data.encoded_by || '';
        if (dateSur) dateSur.value = data.date_surrendered || '';
        if (confirmImageName) {
            confirmImageName.textContent = imageDataUrl ? 'file.png' : '';
            confirmImageRemove.style.display = imageDataUrl ? 'flex' : 'none';
        }
        window.__encodeIdConfirmImage = imageDataUrl;
        if (encodeIdConfirmModal) encodeIdConfirmModal.classList.add('report-modal-open');
    }

    function closeEncodeIdConfirmModal() {
        if (encodeIdConfirmModal) encodeIdConfirmModal.classList.remove('report-modal-open');
        window.__encodeIdConfirmImage = null;
    }

    if (encodeIdConfirmClose) encodeIdConfirmClose.addEventListener('click', closeEncodeIdConfirmModal);
    if (encodeIdConfirmModal) encodeIdConfirmModal.addEventListener('click', function (e) { if (e.target === encodeIdConfirmModal) closeEncodeIdConfirmModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && encodeIdConfirmModal && encodeIdConfirmModal.classList.contains('report-modal-open')) closeEncodeIdConfirmModal(); });
    if (encodeIdConfirmCancel) encodeIdConfirmCancel.addEventListener('click', closeEncodeIdConfirmModal);

    if (encodeIdConfirmBtn) {
        encodeIdConfirmBtn.addEventListener('click', function () {
            var fullname = document.getElementById('confirmFullname');
            var color = document.getElementById('confirmColor');
            if (!fullname || !fullname.value.trim()) { fullname.focus(); return; }
            if (!color || !color.value.trim()) { color.focus(); return; }

            var payload = {
                barcode_id: (document.getElementById('confirmBarcodeId') && document.getElementById('confirmBarcodeId').value) || '',
                id_type: (document.getElementById('confirmIdType') && document.getElementById('confirmIdType').value) || '',
                fullname: fullname.value.trim(),
                color: color.value.trim(),
                storage_location: (document.getElementById('confirmStorageLocation') && document.getElementById('confirmStorageLocation').value) || '',
                encoded_by: (document.getElementById('confirmEncodedBy') && document.getElementById('confirmEncodedBy').value) || '',
                date_surrendered: (document.getElementById('confirmDateSurrendered') && document.getElementById('confirmDateSurrendered').value) || '',
                imageDataUrl: window.__encodeIdConfirmImage || null
            };
            closeEncodeIdConfirmModal();
            if (window.__submitGuestItemEncodeId) window.__submitGuestItemEncodeId(payload);
        });
    }

    if (confirmImageRemove) {
        confirmImageRemove.addEventListener('click', function () {
            window.__encodeIdConfirmImage = null;
            confirmImageName.textContent = '';
            confirmImageRemove.style.display = 'none';
        });
    }
})();

(function () {
    var encodeReportBtn = document.getElementById('encodeReportBtn');
    var encodeReportModal = document.getElementById('encodeReportModal');
    var encodeReportScanModal = document.getElementById('encodeReportScanModal');
    var encodeReportUploadModal = document.getElementById('encodeReportUploadModal');
    var encodeReportConfirmModal = document.getElementById('encodeReportConfirmModal');
    var encodeReportClose = encodeReportModal && encodeReportModal.querySelector('.encode-report-modal-close');
    var encodeReportCancel = document.getElementById('encodeReportCancel');
    var encodeReportNext = document.getElementById('encodeReportNext');
    if (!encodeReportModal) return;

    function closeEncodeReportModal() {
        encodeReportModal.classList.remove('report-modal-open');
    }
    function closeEncodeReportScanModal() {
        if (encodeReportScanModal) encodeReportScanModal.classList.remove('scan-upload-open');
        if (window.stopEncodeReportCamera) window.stopEncodeReportCamera();
    }
    function closeEncodeReportUploadModal() {
        if (encodeReportUploadModal) encodeReportUploadModal.classList.remove('upload-image-open');
    }
    function closeEncodeReportConfirmModal() {
        if (encodeReportConfirmModal) encodeReportConfirmModal.classList.remove('report-modal-open');
        var cb = document.getElementById('encodeReportAuthorizeCheck');
        if (cb) cb.checked = false;
        var submitBtn = document.getElementById('encodeReportConfirmSubmit');
        if (submitBtn) submitBtn.disabled = true;
    }

    function openEncodeReportConfirmModal(imageDataUrl) {
        var data = window.__encodeReportFormData;
        if (!data) return;
        function set(id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val || '—';
        }
        set('confirmReportCategory', data.category);
        set('confirmReportFullName', data.full_name);
        set('confirmReportContactNumber', data.contact_number);
        set('confirmReportDepartment', data.department);
        set('confirmReportId', data.id);
        set('confirmReportItem', data.item);
        set('confirmReportItemDescription', data.item_description);
        set('confirmReportColor', data.color);
        set('confirmReportBrand', data.brand);
        set('confirmReportFoundAt', data.found_at);
        set('confirmReportStorageLocation', data.storage_location);
        set('confirmReportDateLost', data.date_lost);
        var imgName = document.getElementById('confirmReportImageName');
        if (imgName) imgName.textContent = imageDataUrl ? 'file.png' : '—';
        window.__encodeReportConfirmImage = imageDataUrl;
        if (encodeReportConfirmModal) encodeReportConfirmModal.classList.add('report-modal-open');
    }
    window.openEncodeReportConfirmModal = openEncodeReportConfirmModal;

    var encodeReportSuccessModal = document.getElementById('encodeReportSuccessModal');
    var encodeReportSuccessRefEl = document.getElementById('encodeReportSuccessRefId');
    var encodeReportSuccessAutoCloseTimer = null;

    function closeEncodeReportSuccessModal() {
        if (encodeReportSuccessModal) encodeReportSuccessModal.classList.remove('success-modal-open');
        if (encodeReportSuccessAutoCloseTimer) {
            clearTimeout(encodeReportSuccessAutoCloseTimer);
            encodeReportSuccessAutoCloseTimer = null;
        }
    }

    function showEncodeReportSuccessModal(refId) {
        if (!encodeReportSuccessModal || !encodeReportSuccessRefEl) return;
        var strong = encodeReportSuccessRefEl.querySelector('strong');
        if (strong) strong.textContent = refId || '';
        encodeReportSuccessModal.classList.add('success-modal-open');
        encodeReportSuccessAutoCloseTimer = setTimeout(closeEncodeReportSuccessModal, 3000);
    }
    window.showEncodeReportSuccessModal = showEncodeReportSuccessModal;

    if (encodeReportSuccessModal) {
        var successClose = document.getElementById('encodeReportSuccessClose');
        if (successClose) successClose.addEventListener('click', closeEncodeReportSuccessModal);
        encodeReportSuccessModal.addEventListener('click', function (e) { if (e.target === encodeReportSuccessModal) closeEncodeReportSuccessModal(); });
    }

    if (encodeReportConfirmModal) {
        var confirmClose = encodeReportConfirmModal.querySelector('.encode-report-confirm-close');
        var confirmCancel = document.getElementById('encodeReportConfirmCancel');
        var authorizeCheck = document.getElementById('encodeReportAuthorizeCheck');
        var confirmSubmit = document.getElementById('encodeReportConfirmSubmit');
        if (confirmClose) confirmClose.addEventListener('click', closeEncodeReportConfirmModal);
        if (confirmCancel) confirmCancel.addEventListener('click', closeEncodeReportConfirmModal);
        encodeReportConfirmModal.addEventListener('click', function (e) { if (e.target === encodeReportConfirmModal) closeEncodeReportConfirmModal(); });
        if (authorizeCheck && confirmSubmit) {
            authorizeCheck.addEventListener('change', function () {
                confirmSubmit.disabled = !authorizeCheck.checked;
            });
        }
        if (confirmSubmit) {
            confirmSubmit.addEventListener('click', function () {
                if (authorizeCheck && !authorizeCheck.checked) return;
                if (!confirm('Are you sure you want to submit this report?')) return;
                var img = window.__encodeReportConfirmImage;
                closeEncodeReportConfirmModal();
                if (window.submitEncodeReport) window.submitEncodeReport(img);
            });
        }
    }

    if (encodeReportBtn) encodeReportBtn.addEventListener('click', function () {
        encodeReportModal.classList.add('report-modal-open');
    });
    if (encodeReportClose) encodeReportClose.addEventListener('click', closeEncodeReportModal);
    encodeReportModal.addEventListener('click', function (e) { if (e.target === encodeReportModal) closeEncodeReportModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && encodeReportModal.classList.contains('report-modal-open')) closeEncodeReportModal();
        if (e.key === 'Escape' && encodeReportScanModal && encodeReportScanModal.classList.contains('scan-upload-open')) closeEncodeReportScanModal();
        if (e.key === 'Escape' && encodeReportUploadModal && encodeReportUploadModal.classList.contains('upload-image-open')) closeEncodeReportUploadModal();
        if (e.key === 'Escape' && encodeReportConfirmModal && encodeReportConfirmModal.classList.contains('report-modal-open')) closeEncodeReportConfirmModal();
    });
    if (encodeReportCancel) encodeReportCancel.addEventListener('click', closeEncodeReportModal);

    if (encodeReportNext) {
        encodeReportNext.addEventListener('click', function () {
            var contact = document.getElementById('encodeReportContactNumber');
            var department = document.getElementById('encodeReportDepartment');
            var description = document.getElementById('encodeReportItemDescription');
            if (!contact || !contact.value.trim()) { contact.focus(); return; }
            if (!department || !department.value.trim()) { department.focus(); return; }
            if (!description || !description.value.trim()) { description.focus(); return; }
            window.__encodeReportFormData = {
                category: (document.getElementById('encodeReportCategory') && document.getElementById('encodeReportCategory').value) || '',
                full_name: (document.getElementById('encodeReportFullName') && document.getElementById('encodeReportFullName').value) || '',
                contact_number: (contact && contact.value) || '',
                department: (department && department.value) || '',
                id: (document.getElementById('encodeReportId') && document.getElementById('encodeReportId').value) || '',
                item: (document.getElementById('encodeReportItem') && document.getElementById('encodeReportItem').value) || '',
                item_description: (description && description.value) || '',
                color: (document.getElementById('encodeReportColor') && document.getElementById('encodeReportColor').value) || '',
                brand: (document.getElementById('encodeReportBrand') && document.getElementById('encodeReportBrand').value) || '',
                found_at: (document.getElementById('encodeReportFoundAt') && document.getElementById('encodeReportFoundAt').value) || '',
                storage_location: (document.getElementById('encodeReportStorageLocation') && document.getElementById('encodeReportStorageLocation').value) || '',
                date_lost: (document.getElementById('encodeReportDateLost') && document.getElementById('encodeReportDateLost').value) || ''
            };
            closeEncodeReportModal();
            if (encodeReportScanModal) encodeReportScanModal.classList.add('scan-upload-open');
        });
    }

    (function () {
        var scanModal = encodeReportScanModal;
        var uploadModal = encodeReportUploadModal;
        var scanClose = scanModal && scanModal.querySelector('.encode-report-scan-close');
        var cameraBtn = document.getElementById('encodeReportCameraBtn');
        var cameraBtnText = document.getElementById('encodeReportCameraBtnText');
        var captureBtn = document.getElementById('encodeReportCaptureBtn');
        var uploadBtn = document.getElementById('encodeReportUploadBtn');
        var cameraVideo = document.getElementById('encodeReportCameraVideo');
        var scanPreview = document.getElementById('encodeReportScanPreview');
        var uploadClose = uploadModal && uploadModal.querySelector('.encode-report-upload-close');
        var dropzone = document.getElementById('encodeReportUploadDropzone');
        var uploadInput = document.getElementById('encodeReportUploadInput');
        var uploadStaged = document.getElementById('encodeReportUploadStaged');
        var uploadFileName = document.getElementById('encodeReportUploadFileName');
        var uploadFileSize = document.getElementById('encodeReportUploadFileSize');
        var uploadRemove = document.getElementById('encodeReportUploadRemove');
        var uploadCancel = document.getElementById('encodeReportUploadCancel');
        var uploadNext = document.getElementById('encodeReportUploadNext');
        var cameraStream = null;
        var stagedFile = null;

        function stopEncodeReportCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(function (t) { t.stop(); });
                cameraStream = null;
            }
            if (cameraVideo) cameraVideo.srcObject = null;
            if (cameraVideo) cameraVideo.style.display = 'none';
            if (scanPreview) { scanPreview.innerHTML = ''; scanPreview.style.display = 'none'; }
            if (cameraBtnText) cameraBtnText.textContent = 'Use Camera';
            if (captureBtn) captureBtn.style.display = 'none';
        }
        window.stopEncodeReportCamera = stopEncodeReportCamera;

        if (scanClose) scanClose.addEventListener('click', function () { closeEncodeReportScanModal(); closeEncodeReportUploadModal(); });
        if (scanModal) scanModal.addEventListener('click', function (e) { if (e.target === scanModal) { closeEncodeReportScanModal(); closeEncodeReportUploadModal(); } });

        if (cameraBtn && cameraVideo && scanPreview) {
            cameraBtn.addEventListener('click', function () {
                if (cameraStream) {
                    stopEncodeReportCamera();
                    return;
                }
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Camera is not supported.');
                    return;
                }
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
                    cameraStream = stream;
                    if (cameraVideo) { cameraVideo.srcObject = stream; cameraVideo.style.display = 'block'; }
                    if (scanPreview) { scanPreview.innerHTML = ''; scanPreview.style.display = 'none'; }
                    if (cameraBtnText) cameraBtnText.textContent = 'Stop Camera';
                    if (captureBtn) captureBtn.style.display = 'flex';
                }).catch(function () { alert('Could not access camera.'); });
            });
        }
        if (captureBtn && cameraVideo && scanPreview) {
            captureBtn.addEventListener('click', function () {
                if (!cameraStream || !cameraVideo.videoWidth) return;
                var canvas = document.createElement('canvas');
                canvas.width = cameraVideo.videoWidth;
                canvas.height = cameraVideo.videoHeight;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(cameraVideo, 0, 0);
                var reportImageDataUrl = canvas.toDataURL('image/jpeg');
                scanPreview.innerHTML = '<img src="' + reportImageDataUrl + '" alt="Captured">';
                scanPreview.classList.add('has-image');
                scanPreview.style.display = 'flex';
                stopEncodeReportCamera();
                closeEncodeReportScanModal();
                if (window.openEncodeReportConfirmModal) window.openEncodeReportConfirmModal(reportImageDataUrl);
            });
        }
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function () {
                if (scanModal) scanModal.classList.remove('scan-upload-open');
                if (uploadModal) {
                    stagedFile = null;
                    if (uploadStaged) uploadStaged.style.display = 'none';
                    if (uploadInput) uploadInput.value = '';
                    uploadModal.classList.add('upload-image-open');
                }
            });
        }
        if (uploadClose) uploadClose.addEventListener('click', function () { closeEncodeReportUploadModal(); if (scanModal) scanModal.classList.add('scan-upload-open'); });
        if (uploadModal) uploadModal.addEventListener('click', function (e) { if (e.target === uploadModal) { closeEncodeReportUploadModal(); if (scanModal) scanModal.classList.add('scan-upload-open'); } });
        if (uploadCancel) uploadCancel.addEventListener('click', function () { closeEncodeReportUploadModal(); if (scanModal) scanModal.classList.add('scan-upload-open'); });

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
        if (dropzone && uploadInput) {
            dropzone.addEventListener('click', function () { uploadInput.click(); });
            uploadInput.addEventListener('change', function () {
                var file = this.files && this.files[0];
                if (file && (file.type === 'image/jpeg' || file.type === 'image/png' || /\.(jpe?g|png)$/i.test(file.name))) {
                    stagedFile = file;
                    if (uploadFileName) uploadFileName.textContent = file.name;
                    if (uploadFileSize) uploadFileSize.textContent = formatSize(file.size);
                    if (uploadStaged) uploadStaged.style.display = 'block';
                }
            });
            dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('upload-image-dragover'); });
            dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('upload-image-dragover'); });
            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropzone.classList.remove('upload-image-dragover');
                var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                if (file && (file.type === 'image/jpeg' || file.type === 'image/png' || /\.(jpe?g|png)$/i.test(file.name))) {
                    stagedFile = file;
                    if (uploadFileName) uploadFileName.textContent = file.name;
                    if (uploadFileSize) uploadFileSize.textContent = formatSize(file.size);
                    if (uploadStaged) uploadStaged.style.display = 'block';
                }
            });
        }
        if (uploadRemove) uploadRemove.addEventListener('click', function () { stagedFile = null; if (uploadInput) uploadInput.value = ''; if (uploadStaged) uploadStaged.style.display = 'none'; });
        if (uploadNext) {
            uploadNext.addEventListener('click', function () {
                if (!stagedFile) return;
                var reader = new FileReader();
                reader.onload = function () {
                    var reportImageDataUrl = reader.result;
                    closeEncodeReportUploadModal();
                    closeEncodeReportScanModal();
                    if (window.openEncodeReportConfirmModal) window.openEncodeReportConfirmModal(reportImageDataUrl);
                };
                reader.readAsDataURL(stagedFile);
            });
        }

        function submitEncodeReport(imageDataUrl) {
            var data = window.__encodeReportFormData;
            if (!data) return;
            var payload = {
                category: data.category,
                full_name: data.full_name,
                contact_number: data.contact_number,
                department: data.department,
                id: data.id,
                item: data.item,
                item_description: data.item_description,
                color: data.color,
                brand: data.brand,
                found_at: data.found_at,
                storage_location: data.storage_location,
                date_lost: data.date_lost,
                imageDataUrl: imageDataUrl || null
            };
            var saveUrl = typeof LOSTANDFOUND_SAVE_LOST_REPORT_URL !== 'undefined' ? LOSTANDFOUND_SAVE_LOST_REPORT_URL : '../save_lost_report.php';
            fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (res) { return res.json().catch(function () { return { ok: false, error: 'Invalid response' }; });             }).then(function (result) {
                if (result && result.ok) {
                    document.getElementById('encodeReportForm').reset();
                    if (window.showEncodeReportSuccessModal) window.showEncodeReportSuccessModal(result.id);
                } else {
                    alert(result && result.error ? result.error : 'Could not submit report. Try again.');
                }
            }).catch(function () {
                alert('Could not submit report. Try again.');
            });
        }
        window.submitEncodeReport = submitEncodeReport;
    })();
})();

(function () {
    var filterSelect = document.getElementById('guestFilterByDate');
    var guestTableBody = document.getElementById('guestTableBody');
    if (!filterSelect || !guestTableBody) return;

    function parseDate(str) {
        if (!str) return null;
        var d = new Date(str);
        return isNaN(d.getTime()) ? null : d;
    }

    function applyDateFilter() {
        var value = (filterSelect.value || '').trim();
        var rows = guestTableBody.querySelectorAll('tr');
        var now = new Date();
        rows.forEach(function (row) {
            if (row.querySelector('td[colspan]')) {
                row.style.display = value ? 'none' : '';
                return;
            }
            var dateEncoded = row.getAttribute('data-date-encoded');
            var d = parseDate(dateEncoded);
            if (!d) {
                row.style.display = value ? 'none' : '';
                return;
            }
            var show = true;
            if (value === 'today') {
                show = d.toDateString() === now.toDateString();
            } else if (value === 'week') {
                var weekAgo = new Date(now);
                weekAgo.setDate(weekAgo.getDate() - 7);
                show = d >= weekAgo;
            } else if (value === 'month') {
                var monthAgo = new Date(now);
                monthAgo.setMonth(monthAgo.getMonth() - 1);
                show = d >= monthAgo;
            } else if (value === '3months') {
                var threeMonthsAgo = new Date(now);
                threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
                show = d >= threeMonthsAgo;
            } else if (value === 'year') {
                var yearAgo = new Date(now);
                yearAgo.setFullYear(yearAgo.getFullYear() - 1);
                show = d >= yearAgo;
            }
            row.style.display = show ? '' : 'none';
        });
    }

    filterSelect.addEventListener('change', applyDateFilter);
})();

/* ── Category filter — filters both tables ── */
(function () {
    var catFilter = document.getElementById('foundCategoryFilter');
    if (!catFilter) return;
    catFilter.addEventListener('change', function () {
        var val = (catFilter.value || '').trim();
        ['inventoryTableBody', 'guestTableBody'].forEach(function (tbodyId) {
            var tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.querySelectorAll('tr').forEach(function (row) {
                if (row.querySelector('td[colspan]')) return;
                var cat = (row.getAttribute('data-category') || '').trim();
                row.style.display = (!val || cat === val) ? '' : 'none';
            });
        });
    });
})();

(function () {
    var modal = document.getElementById('viewModal');
    var imageEl = document.getElementById('viewModalImage');
    var bodyEl = document.getElementById('viewModalBody');
    if (!modal || !bodyEl) return;
    function esc(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function getThLabel(th) { if (!th) return ''; return (th.innerHTML || '').replace(/<br\s*\/?>/gi, ' / ').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim(); }
    function openModal(row) {
        var table = row.closest('table');
        var headers = table ? table.querySelectorAll('thead th') : [];
        var cells = row.querySelectorAll('td');
        var imgUrl = row.getAttribute('data-image');
        if (imgUrl) { imageEl.innerHTML = '<img src="' + esc(imgUrl) + '" alt="Item">'; imageEl.classList.remove('view-modal-image-placeholder'); } else { imageEl.innerHTML = '<div class="view-modal-image-placeholder-inner"><span class="view-modal-image-icon" aria-hidden="true">&#128230;</span><span>Item image</span></div>'; imageEl.classList.add('view-modal-image-placeholder'); }
        var pairs = [], seen = {};
        var extra = [{ key: 'Barcode ID', attr: 'data-id' }, { key: 'Fullname', attr: 'data-user-id' }, { key: 'Category', attr: 'data-category' }, { key: 'Color', attr: 'data-color' }, { key: 'Brand', attr: 'data-brand' }, { key: 'Found By', attr: 'data-found-by' }, { key: 'Date Encoded', attr: 'data-date-encoded' }, { key: 'Storage Location', attr: 'data-storage-location' }];
        for (var i = 0; i < cells.length - 1; i++) { var label = getThLabel(headers[i]); var val = (cells[i] && cells[i].textContent) ? cells[i].textContent.trim() : ''; if (label && label !== 'Action') { pairs.push({ label: label, value: val }); seen[label] = true; } }
        for (var j = 0; j < extra.length; j++) { if (seen[extra[j].key]) continue; var v = row.getAttribute(extra[j].attr); if (v) pairs.push({ label: extra[j].key, value: v }); }
        bodyEl.innerHTML = pairs.map(function (p) { return '<div class="view-modal-row"><span class="view-modal-label">' + esc(p.label) + ':</span><span class="view-modal-value">' + esc(p.value) + '</span></div>'; }).join('') || '<p class="view-modal-empty">No details available.</p>';
        modal.classList.add('view-modal-open');
    }
    function closeModal() { modal.classList.remove('view-modal-open'); }
    function openModalFromEncodedItem(item) {
        if (!item || !imageEl || !bodyEl) return;
        if (item.imageDataUrl) { imageEl.innerHTML = '<img src="' + esc(item.imageDataUrl) + '" alt="Item">'; imageEl.classList.remove('view-modal-image-placeholder'); } else { imageEl.innerHTML = '<div class="view-modal-image-placeholder-inner"><span class="view-modal-image-icon" aria-hidden="true">&#128230;</span><span>Item image</span></div>'; imageEl.classList.add('view-modal-image-placeholder'); }
        var pairs = [
            { label: 'Barcode ID', value: item.id },
            { label: 'Category', value: item.item_type },
            { label: 'Color', value: item.color },
            { label: 'Brand', value: item.brand },
            { label: 'Found At', value: item.found_at },
            { label: 'Found By', value: item.found_by },
            { label: 'Date Lost', value: item.date_lost },
            { label: 'Date Encoded', value: item.dateEncoded },
            { label: 'Storage Location', value: item.storage_location },
            { label: 'Item Description', value: item.item_description }
        ];
        bodyEl.innerHTML = pairs.filter(function (p) { return p.value != null && p.value !== ''; }).map(function (p) { return '<div class="view-modal-row"><span class="view-modal-label">' + esc(p.label) + ':</span><span class="view-modal-value">' + esc(p.value) + '</span></div>'; }).join('') || '<p class="view-modal-empty">No details available.</p>';
        modal.classList.add('view-modal-open');
    }
    window.openViewModalForEncodedItem = openModalFromEncodedItem;
    window.openViewModalFromRow = openModal;
    document.querySelectorAll('#recoveredSection .found-btn-view').forEach(function (btn) {
        btn.addEventListener('click', function (e) { e.preventDefault(); var r = e.target.closest('tr'); if (r) openModal(r); });
    });
    /* Guest table view handled by openGuestModal delegation below */
    (function () {
        var deleteUrl = typeof LOSTANDFOUND_DELETE_ITEM_URL !== 'undefined' ? LOSTANDFOUND_DELETE_ITEM_URL : '../delete_item.php';
        function ensureEmptyRow(tbody, colCount, emptyText) {
            if (!tbody) return;
            var rows = tbody.querySelectorAll('tr');
            var hasDataRow = false;
            for (var i = 0; i < rows.length; i++) {
                if (!rows[i].querySelector('td[colspan]')) { hasDataRow = true; break; }
            }
            if (!hasDataRow) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="' + colCount + '" class="table-empty">' + emptyText + '</td>';
                tbody.appendChild(tr);
            }
        }
        function onCancelClick(e) {
            var btn = e.target.closest('.found-btn-cancel');
            if (!btn) return;
            e.preventDefault();
            var tr = btn.closest('tr');
            if (!tr || tr.querySelector('td[colspan]')) return;
            var id = tr.getAttribute('data-id') || (tr.querySelector('td') && tr.querySelector('td').textContent.trim());
            if (!id) return;
            window.confirmAction('Are you sure you want to remove this item? This cannot be undone.', function () {
                btn.disabled = true;
                fetch(deleteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (data.ok) {
                        var tbody = tr.parentNode;
                        tr.remove();
                        if (tbody && tbody.id === 'inventoryTableBody') ensureEmptyRow(tbody, 8, 'No items yet. Click Encode Item to add one.');
                        if (tbody && tbody.id === 'guestTableBody') ensureEmptyRow(tbody, 7, 'No guest items.');
                    } else {
                        btn.disabled = false;
                        alert(data.error || 'Could not delete item.');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    alert('Could not delete item. Try again.');
                });
            }); // end confirmAction
        }
        var invBody = document.getElementById('inventoryTableBody');
        var guestBody = document.getElementById('guestTableBody');
        if (invBody) invBody.addEventListener('click', onCancelClick);
        if (guestBody) guestBody.addEventListener('click', onCancelClick);
    })();
    modal.querySelector('.view-modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    var cancelBtn = document.getElementById('viewModalCancel');
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();

(function () {
    var encodeBtn = document.getElementById('encodeNewItemBtn');
    var reportModalEl = document.getElementById('itemLostReportModal');
    if (!encodeBtn || !reportModalEl) return;
    encodeBtn.addEventListener('click', function () {
        reportModalEl.classList.add('report-modal-open');
    });
})();

(function () {
    var scanModal = document.getElementById('scanUploadModal');
    var scanClose = scanModal && scanModal.querySelector('.scan-upload-close');
    var scanUploadBtn = document.getElementById('scanUploadBtn');
    var scanUploadPreview = document.getElementById('scanUploadPreview');
    var scanCameraBtn = document.getElementById('scanCameraBtn');
    var scanCameraBtnText = document.getElementById('scanCameraBtnText');
    var scanCameraVideo = document.getElementById('scanCameraVideo');
    var scanCaptureBtn = document.getElementById('scanCaptureBtn');
    var scanContinueBtn = document.getElementById('scanContinueBtn');
    var cameraStream = null;

    function updateContinueButton() {
        if (!scanContinueBtn || !scanUploadPreview) return;
        var hasImage = scanUploadPreview.querySelector('img') || (scanUploadPreview.classList && scanUploadPreview.classList.contains('has-image'));
        scanContinueBtn.style.display = hasImage ? 'flex' : 'none';
    }
    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (t) { t.stop(); });
            cameraStream = null;
        }
        if (scanCameraVideo) scanCameraVideo.srcObject = null;
        if (scanCameraVideo) scanCameraVideo.style.display = 'none';
        if (scanUploadPreview) scanUploadPreview.style.display = '';
        if (scanCameraBtnText) scanCameraBtnText.textContent = 'Use camera';
        if (scanCaptureBtn) scanCaptureBtn.style.display = 'none';
        updateContinueButton();
    }
    function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Camera is not supported in this browser.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
            cameraStream = stream;
            if (scanCameraVideo) { scanCameraVideo.srcObject = stream; scanCameraVideo.style.display = 'block'; }
            if (scanUploadPreview) { scanUploadPreview.innerHTML = ''; scanUploadPreview.style.display = 'none'; }
            if (scanCameraBtnText) scanCameraBtnText.textContent = 'Stop camera';
            if (scanCaptureBtn) scanCaptureBtn.style.display = 'flex';
        }).catch(function () { alert('Could not access camera.'); });
    }
    if (scanClose) scanClose.addEventListener('click', function () { stopCamera(); scanModal.classList.remove('scan-upload-open'); });
    if (scanModal) scanModal.addEventListener('click', function (e) { if (e.target === scanModal) { stopCamera(); scanModal.classList.remove('scan-upload-open'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { stopCamera(); scanModal.classList.remove('scan-upload-open'); } });
    if (scanCameraBtn) scanCameraBtn.addEventListener('click', function () { if (cameraStream) stopCamera(); else startCamera(); });
    if (scanCaptureBtn && scanCameraVideo && scanUploadPreview) {
        scanCaptureBtn.addEventListener('click', function () {
            if (!cameraStream || !scanCameraVideo.videoWidth) return;
            var canvas = document.createElement('canvas');
            canvas.width = scanCameraVideo.videoWidth;
            canvas.height = scanCameraVideo.videoHeight;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(scanCameraVideo, 0, 0);
            var dataUrl = canvas.toDataURL('image/jpeg');
            scanUploadPreview.innerHTML = '<img src="' + dataUrl + '" alt="Captured">';
            scanUploadPreview.classList.add('has-image');
            scanUploadPreview.style.display = 'flex';
            updateContinueButton();
            stopCamera();
            setReportImageName('capture.jpg');
        });
    }
    if (scanContinueBtn) {
        scanContinueBtn.addEventListener('click', function () {
            if (scanModal) scanModal.classList.remove('scan-upload-open');
            var reportForm = document.getElementById('itemLostReportForm');
            if (reportForm) {
                if (typeof reportForm.requestSubmit === 'function') reportForm.requestSubmit();
                else reportForm.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    }
    if (scanUploadBtn) {
        scanUploadBtn.addEventListener('click', function () {
            var uploadModal = document.getElementById('uploadImageModal');
            if (uploadModal) {
                document.getElementById('uploadImageStaged').style.display = 'none';
                document.getElementById('uploadImageInput').value = '';
                uploadModal.classList.add('upload-image-open');
            }
        });
    }

    var uploadModal = document.getElementById('uploadImageModal');
    var uploadClose = uploadModal && uploadModal.querySelector('.upload-image-close');
    var uploadDropzone = document.getElementById('uploadImageDropzone');
    var uploadInput = document.getElementById('uploadImageInput');
    var uploadStaged = document.getElementById('uploadImageStaged');
    var uploadFileName = document.getElementById('uploadImageFileName');
    var uploadFileSize = document.getElementById('uploadImageFileSize');
    var uploadRemove = document.getElementById('uploadImageRemove');
    var uploadCancel = document.getElementById('uploadImageCancel');
    var uploadNext = document.getElementById('uploadImageNext');
    var stagedFile = null;

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    function showStaged(file) {
        stagedFile = file;
        if (uploadFileName) uploadFileName.textContent = file.name;
        if (uploadFileSize) uploadFileSize.textContent = formatSize(file.size);
        if (uploadStaged) uploadStaged.style.display = 'block';
    }
    function clearStaged() {
        stagedFile = null;
        if (uploadInput) uploadInput.value = '';
        if (uploadStaged) uploadStaged.style.display = 'none';
    }
    function closeUploadModal() {
        if (uploadModal) uploadModal.classList.remove('upload-image-open');
        clearStaged();
    }
    if (uploadClose) uploadClose.addEventListener('click', closeUploadModal);
    if (uploadModal) uploadModal.addEventListener('click', function (e) { if (e.target === uploadModal) closeUploadModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && uploadModal && uploadModal.classList.contains('upload-image-open')) closeUploadModal(); });
    if (uploadDropzone && uploadInput) {
        uploadDropzone.addEventListener('click', function () { uploadInput.click(); });
        uploadInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (file && (file.type === 'image/jpeg' || file.type === 'image/png' || /\.(jpe?g|png)$/i.test(file.name))) showStaged(file);
        });
        uploadDropzone.addEventListener('dragover', function (e) { e.preventDefault(); uploadDropzone.classList.add('upload-image-dragover'); });
        uploadDropzone.addEventListener('dragleave', function () { uploadDropzone.classList.remove('upload-image-dragover'); });
        uploadDropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            uploadDropzone.classList.remove('upload-image-dragover');
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file && (file.type === 'image/jpeg' || file.type === 'image/png' || /\.(jpe?g|png)$/i.test(file.name))) showStaged(file);
        });
    }
    if (uploadRemove) uploadRemove.addEventListener('click', clearStaged);
    if (uploadCancel) uploadCancel.addEventListener('click', closeUploadModal);
    if (uploadNext) {
        uploadNext.addEventListener('click', function () {
            if (!stagedFile) { closeUploadModal(); return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                if (scanUploadPreview) {
                    scanUploadPreview.innerHTML = '<img src="' + e.target.result + '" alt="Item preview">';
                    scanUploadPreview.classList.add('has-image');
                    scanUploadPreview.style.display = 'flex';
                }
                if (scanCameraVideo) scanCameraVideo.style.display = 'none';
                stopCamera();
                closeUploadModal();
                if (scanContinueBtn) scanContinueBtn.style.display = 'flex';
                setReportImageName(stagedFile.name);
            };
            reader.readAsDataURL(stagedFile);
        });
    }

    function setReportImageName(imageName) {
        var reportImageNameEl = document.getElementById('reportImageName');
        var reportImageFieldEl = document.getElementById('reportImageRemove') && document.getElementById('reportImageRemove').closest('.report-image-field');
        if (reportImageNameEl) reportImageNameEl.textContent = imageName;
        if (reportImageFieldEl) reportImageFieldEl.classList.add('has-image');
    }

    var reportModal = document.getElementById('itemLostReportModal');
    var reportClose = reportModal && reportModal.querySelector('.report-modal-close');
    var reportCancel = document.getElementById('reportCancel');
    var reportNext = document.getElementById('reportNext');
    var reportForm = document.getElementById('itemLostReportForm');

    function closeReportModal() {
        if (reportModal) reportModal.classList.remove('report-modal-open');
    }
    if (reportClose) reportClose.addEventListener('click', closeReportModal);
    if (reportModal) reportModal.addEventListener('click', function (e) { if (e.target === reportModal) closeReportModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && reportModal && reportModal.classList.contains('report-modal-open')) closeReportModal(); });
    if (reportCancel) reportCancel.addEventListener('click', closeReportModal);
    if (reportNext) {
        reportNext.addEventListener('click', function () {
            stopCamera();
            if (scanUploadPreview) { scanUploadPreview.innerHTML = ''; scanUploadPreview.classList.remove('has-image'); }
            if (scanModal) scanModal.classList.add('scan-upload-open');
            if (reportModal) reportModal.classList.remove('report-modal-open');
        });
    }

    window.__encodedItems = window.__encodedItems || {};
    var saveEncodedItemUrl = typeof LOSTANDFOUND_SAVE_URL !== 'undefined' ? LOSTANDFOUND_SAVE_URL : '../save_encoded_item.php';

    function getReportImageDataUrl() {
        var preview = document.getElementById('scanUploadPreview');
        var img = preview && preview.querySelector('img');
        return img && img.src ? img.src : null;
    }

    if (reportForm) {
        reportForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var userId = document.getElementById('reportUserId') && document.getElementById('reportUserId').value;
            var itemType = document.getElementById('reportItemType') && document.getElementById('reportItemType').value;
            var reportItem = document.getElementById('reportItem') && document.getElementById('reportItem').value;
            var color = document.getElementById('reportColor') && document.getElementById('reportColor').value;
            var brand = document.getElementById('reportBrand') && document.getElementById('reportBrand').value;
            var foundAt = document.getElementById('reportFoundAt') && document.getElementById('reportFoundAt').value;
            var foundBy = document.getElementById('reportFoundBy') && document.getElementById('reportFoundBy').value;
            var dateLost = document.getElementById('reportDateLost') && document.getElementById('reportDateLost').value;
            var description = document.getElementById('reportDescription') && document.getElementById('reportDescription').value;
            var storageLocation = document.getElementById('reportStorageLocation') && document.getElementById('reportStorageLocation').value;
            var imageDataUrl = getReportImageDataUrl();
            var dateEncoded = new Date().toISOString().slice(0, 10);
            if (reportItem && reportItem.trim()) {
                description = 'Item Type: ' + reportItem.trim() + '\n' + (description || '');
            }
            var item = {
                user_id: userId,
                item_type: itemType,
                color: color,
                brand: brand,
                found_at: foundAt,
                found_by: foundBy,
                date_lost: dateLost,
                item_description: description,
                storage_location: storageLocation,
                imageDataUrl: imageDataUrl,
                dateEncoded: dateEncoded
            };
            closeReportModal();
            if (scanModal) scanModal.classList.remove('scan-upload-open');
            fetch(saveEncodedItemUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(item)
            }).then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        alert('Could not save item: ' + (data.error || res.status || 'Unknown error') + '. Check that the database is set up and try again.');
                        return;
                    }
                    item.id = data.id;
                    window.__encodedItems[data.id] = item;
                    appendEncodedItemRow(item);
                    var tbody = document.getElementById('inventoryTableBody');
                    var lastRow = tbody && tbody.lastElementChild;
                    if (lastRow && lastRow.scrollIntoView) lastRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            }).catch(function (err) {
                alert('Could not save item (network or server error). Try again or refresh the page.');
                console.error(err);
            });
        });
    }

    function appendEncodedItemRow(item) {
        var tbody = document.getElementById('inventoryTableBody');
        if (!tbody) return;
        var emptyRow = tbody.querySelector('tr td[colspan]');
        if (emptyRow && emptyRow.parentNode) emptyRow.parentNode.remove();
        var retEnd = item.dateEncoded ? (function (d) { var y = new Date(d); y.setFullYear(y.getFullYear() + 2); return y.toISOString().slice(0, 10); })(item.dateEncoded) : '';
        var tr = document.createElement('tr');
        tr.setAttribute('data-id', item.id || '');
        tr.setAttribute('data-color', item.color || '');
        tr.setAttribute('data-brand', item.brand || '');
        tr.setAttribute('data-found-by', item.found_by || '');
        tr.setAttribute('data-date-encoded', item.dateEncoded || '');
        tr.setAttribute('data-category', item.item_type || '');
        tr.setAttribute('data-storage-location', item.storage_location || '');
        if (item.imageDataUrl) tr.setAttribute('data-image', item.imageDataUrl);
        var timestamp = (item.created_at || item.dateEncoded || '').toString();
        if (timestamp && timestamp.length === 10) timestamp += ' 00:00:00';
        tr.innerHTML = '<td>' + (item.id || '') + '</td><td>' + (item.item_type || '') + '</td><td>' + (item.found_at || '') + '</td><td>' + (item.dateEncoded || '') + '</td><td>' + retEnd + '</td><td>' + (item.storage_location || '') + '</td><td>' + timestamp + '</td><td class="found-action-cell"><button type="button" class="found-btn-view">View</button><button type="button" class="found-btn-cancel">Cancel</button></td>';
        tbody.appendChild(tr);

        var viewBtn = tr.querySelector('.found-btn-view');
        if (viewBtn) viewBtn.addEventListener('click', function (ev) { ev.preventDefault(); var r = ev.target.closest('tr'); if (r && window.openViewModalForEncodedItem) window.openViewModalForEncodedItem(item); });
        // Cancel is handled by event delegation on inventoryTableBody (delete from DB then remove row)
    }
})();

/* ── Expiry popup ─────────────────────────────────────────────── */
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

    /* Dispose Item — same delete_item.php endpoint as the Cancel button */
    var grid       = document.getElementById('expiryCardsGrid');
    var deleteUrl  = typeof window.__deleteItemUrl !== 'undefined' ? window.__deleteItemUrl : '../delete_item.php';
    if (grid) {
        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-dispose-item');
            if (!btn) return;
            var id = btn.getAttribute('data-dispose-id');
            if (!id) return;
            window.confirmAction('Permanently dispose item ' + id + '? This cannot be undone.', function () {
                btn.disabled = true;
                fetch(deleteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        var card = btn.closest('.expiry-card');
                        if (card) card.remove();
                        ['inventoryTableBody', 'guestTableBody'].forEach(function (tid) {
                            var tbody = document.getElementById(tid);
                            if (!tbody) return;
                            var row = tbody.querySelector('[data-id="' + id + '"]');
                            if (row) row.remove();
                        });
                        var remaining = grid.querySelectorAll('.expiry-card');
                        if (!remaining.length) {
                            grid.innerHTML = '<p class="expiry-empty-msg">No more items approaching expiry.</p>';
                            if (triggerLink) triggerLink.style.display = 'none';
                        }
                    } else {
                        btn.disabled = false;
                        alert((data && data.error) ? data.error : 'Could not dispose item.');
                    }
                })
                .catch(function () { btn.disabled = false; alert('Network error. Please try again.'); });
            }); // end confirmAction
        });
    }
})();
</script>

<!-- Confirmation modal (replaces browser confirm() for Cancel / Dispose) -->
<div id="confirmActionModal" role="dialog" aria-modal="true" aria-hidden="true"
     style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;
            justify-content:center;background:rgba(0,0,0,0.45);">
  <div style="background:#fff;border-radius:14px;width:100%;max-width:380px;
              padding:32px 28px 24px;text-align:center;position:relative;
              box-shadow:0 16px 48px rgba(0,0,0,0.25);margin:16px;">
    <div style="width:64px;height:64px;background:#fff7ed;border-radius:50%;
                display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <i class="fa-solid fa-triangle-exclamation" style="font-size:28px;color:#f59e0b;"></i>
    </div>
    <h3 style="margin:0 0 10px;font-size:18px;font-weight:700;font-family:inherit;color:#111;">
      Confirm Action
    </h3>
    <p id="confirmActionMsg" style="margin:0 0 24px;font-size:14px;color:#555;line-height:1.6;">
      Are you sure?
    </p>
    <div style="display:flex;justify-content:center;gap:12px;">
      <button type="button" id="confirmActionNo"
        style="padding:9px 26px;border:1px solid #9ca3af;border-radius:7px;
               background:#fff;color:#374151;font-size:14px;font-weight:600;
               cursor:pointer;font-family:inherit;">Cancel</button>
      <button type="button" id="confirmActionYes"
        style="padding:9px 26px;border:none;border-radius:7px;background:#8b0000;
               color:#fff;font-size:14px;font-weight:600;cursor:pointer;
               font-family:inherit;">Confirm</button>
    </div>
  </div>
</div>

/* ── Guest Item Details Modal ─────────────────────────────────────────────── */
(function () {
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    window.openGuestModal = function (row) {
        var overlay      = document.getElementById('guestViewModal');
        var photo        = document.getElementById('guestModalPhoto');
        var placeholder  = document.getElementById('guestModalPhotoPlaceholder');
        var barcodeLabel = document.getElementById('guestModalBarcodeLabel');
        var idType       = document.getElementById('guestModalIdType');
        var fullname     = document.getElementById('guestModalFullname');
        var color        = document.getElementById('guestModalColor');
        var storage      = document.getElementById('guestModalStorageLocation');
        var encodedBy    = document.getElementById('guestModalEncodedBy');
        var dateSurr     = document.getElementById('guestModalDateSurrendered');
        if (!overlay) return;

        /* Image */
        var imgUrl = row.getAttribute('data-image');
        if (imgUrl) {
            photo.src            = imgUrl;
            photo.style.display  = 'block';
            placeholder.style.display = 'none';
        } else {
            photo.style.display  = 'none';
            placeholder.style.display = 'flex';
        }

        /* Barcode */
        var bid = row.getAttribute('data-id') || (row.querySelector('td') ? row.querySelector('td').textContent.trim() : '');
        if (barcodeLabel) barcodeLabel.textContent = 'Barcode ID: ' + (bid || '—');

        /* Fields — pull from data attrs; fall back to table cells */
        function val(attr, cellIdx) {
            var v = row.getAttribute(attr);
            if (!v && cellIdx !== undefined) {
                var cells = row.querySelectorAll('td');
                v = cells[cellIdx] ? cells[cellIdx].textContent.trim() : '';
            }
            return v || '—';
        }

        if (idType)    idType.textContent    = val('data-id-type');
        if (fullname)  fullname.textContent  = val('data-fullname');
        if (color)     color.textContent     = val('data-color');
        if (storage)   storage.textContent   = val('data-storage-location', 4);
        if (encodedBy) encodedBy.textContent = val('data-found-by', 1);
        if (dateSurr)  dateSurr.textContent  = val('data-date-encoded', 2);

        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeGuestModal = function () {
        var overlay = document.getElementById('guestViewModal');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var overlay = document.getElementById('guestViewModal');
            if (overlay && overlay.classList.contains('open')) closeGuestModal();
        }
    });

    /* Wire guest View buttons (both static rows and dynamically added ones) */
    var guestTableBody = document.getElementById('guestTableBody');
    if (guestTableBody) {
        guestTableBody.addEventListener('click', function (e) {
            var btn = e.target.closest('.guest-view-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var r = btn.closest('tr');
            if (r) window.openGuestModal(r);
        });
    }
})();

<script src="NotificationsDropdown.js"></script>
</body>
</html>