<?php
// ItemMatchedAdmin.php - Matched Items (same data as other admin pages, 6-month retention)
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$itemCategories = require dirname(__DIR__) . '/config/categories.php';
$today = date('Y-m-d');

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

// Guest Items = same data as Found (Unclaimed IDs External)
$guestItems = get_items($pdo, 'Unclaimed IDs External');

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
    <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
    <style>
        /* Safety-net inline overrides for action buttons */
        .found-action-cell .found-btn-view {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 6px 16px !important;
            border-radius: 6px !important;
            background-color: #1976d2 !important;
            color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            font-family: inherit !important;
            border: none !important;
            cursor: pointer !important;
        }
        .found-action-cell .found-btn-claim {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 6px 16px !important;
            border-radius: 6px !important;
            background-color: #22c55e !important;
            color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            font-family: inherit !important;
            border: none !important;
            cursor: pointer !important;
        }
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
  background: #fff;
  color: #111827;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            <div class="content-row content-row-single">
                <section class="content-left">

                    <h2 class="page-title">Matched Items</h2>

                    <!-- Header: tabs + filter -->
                    <div class="found-tabs-actions-row">
                        <div class="found-tabs">
                            <span class="found-tab-text found-tab-active" id="allItemsTab">All Items</span>
                            <span class="found-tab-text" id="guestItemsTab">Guest Items</span>
                        </div>
                        <select id="matchedCategoryFilter" class="found-filter-select" aria-label="Filter by category">
                            <option value="">All Categories</option>
                            <?php foreach ($itemCategories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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

                                        $itemDesc = htmlspecialchars($it['item_description'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $dataAttrs = ' data-id="' . $barcodeId . '"'
                                                   . ' data-category="' . $catFilter . '"'
                                                   . ' data-color="' . $color . '"'
                                                   . ' data-brand="' . $brand . '"'
                                                   . ' data-found-by="' . $foundBy . '"'
                                                   . ' data-date-encoded="' . htmlspecialchars($dateEncoded) . '"'
                                                   . ' data-storage-location="' . $storage . '"'
                                                   . ' data-status="' . $statusLabel . '"'
                                                   . ' data-item-description="' . $itemDesc . '"'
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
                                           . '<button type="button" class="found-btn-view">View</button>'
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
                                        $dateSurrendered = $it['dateEncoded'] ?? '';
                                        $retentionEnd    = $dateSurrendered ? date('Y-m-d', strtotime($dateSurrendered . ' +1 year')) : '';
                                        $isOverdue       = $retentionEnd && $retentionEnd < $today;
                                        $isExpiring      = !$isOverdue && $retentionEnd && $retentionEnd <= date('Y-m-d', strtotime('+30 days'));
                                        $storage         = htmlspecialchars($it['storage_location'] ?? '');
                                        $timestamp       = htmlspecialchars($it['created_at'] ?? $it['dateEncoded'] ?? '');
                                        if ($timestamp && strlen($timestamp) === 10) $timestamp .= ' 00:00:00';
                                        $img    = isset($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'], ENT_QUOTES, 'UTF-8') : '';
                                        $color  = htmlspecialchars($it['color'] ?? '');
                                        $brand  = htmlspecialchars($it['brand'] ?? '');
                                        $foundBy = htmlspecialchars($it['found_by'] ?? '');

                                        $itemDesc = htmlspecialchars($it['item_description'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $dataAttrs = ' data-id="' . $barcodeId . '"'
                                                   . ' data-category="' . htmlspecialchars($it['item_type'] ?? '') . '"'
                                                   . ' data-color="' . $color . '"'
                                                   . ' data-brand="' . $brand . '"'
                                                   . ' data-found-by="' . $foundBy . '"'
                                                   . ' data-date-encoded="' . htmlspecialchars($dateSurrendered) . '"'
                                                   . ' data-storage-location="' . $storage . '"'
                                                   . ' data-item-description="' . $itemDesc . '"';
                                        if ($img) $dataAttrs .= ' data-image="' . $img . '"';

                                        echo '<tr class="matched-data-row"' . $dataAttrs . '>';
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
                                        echo '<td class="found-action-cell">'
                                           . '<button type="button" class="found-btn-view">View</button>'
                                           . '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="matched-footer">
                        <span class="matched-footer-text">There are <strong><?php echo (int) $overdueCount; ?></strong> Item<?php echo $overdueCount !== 1 ? 's' : ''; ?> that have exceeded the retention policy.</span>
                        <a href="HistoryAdmin.php" class="matched-dispose-link" id="matchedDisposeLink">View Claimed Items</a>
                    </div>

                </section>
            </div>
        </div>
    </main>
</div>

<!-- Item Details modal -->
<div id="viewModal" class="view-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="view-modal view-modal-matching" onclick="event.stopPropagation()">
        <div class="view-modal-header">
            <h3 id="viewModalTitle" class="view-modal-title">Item Details</h3>
            <button type="button" class="view-modal-close" aria-label="Close" title="Close">&times;</button>
        </div>
        <div class="view-modal-content">
            <div class="view-modal-left">
                <div class="view-modal-image-wrap">
                    <div id="viewModalImage" class="view-modal-image"></div>
                    <div id="viewModalBarcode" class="view-modal-barcode"></div>
                </div>
                <h4 class="view-modal-section-title">General Information</h4>
                <div id="viewModalBody" class="view-modal-body"></div>
                <h4 class="view-modal-section-title">Potential Claimants</h4>
                <div id="viewModalClaimants" class="view-modal-claimants">
                    <p class="view-modal-loading">Loading potential claimants…</p>
                </div>
            </div>
        </div>
        <div class="view-modal-footer">
            <button type="button" class="view-modal-btn-cancel" id="viewModalCancel">Cancel</button>
            <button type="button" class="view-modal-btn-next" id="viewModalNext">Next</button>
        </div>
    </div>
</div>

<script>
/* Admin dropdown */
(function () {
    var dropdown = document.getElementById('adminDropdown');
    var trigger  = dropdown && dropdown.querySelector('.admin-dropdown-trigger');
    if (!dropdown || !trigger) return;
    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', dropdown.classList.contains('open'));
    });
    document.addEventListener('click', function () {
        dropdown.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });
})();

/* Search clear */
(function () {
    var input    = document.getElementById('adminSearchInput');
    var clearBtn = document.getElementById('adminSearchClear');
    if (!input || !clearBtn) return;
    function syncClear() { clearBtn.style.display = input.value ? 'flex' : 'none'; }
    clearBtn.addEventListener('click', function () { input.value = ''; input.focus(); syncClear(); });
    input.addEventListener('input', syncClear);
    syncClear();
})();

/* Category filter */
(function () {
    var filter = document.getElementById('matchedCategoryFilter');
    if (!filter) return;
    filter.addEventListener('change', function () {
        var val = (filter.value || '').trim();
        document.querySelectorAll('.matched-data-row').forEach(function (row) {
            var cat = (row.getAttribute('data-category') || '').trim();
            row.style.display = (!val || cat === val) ? '' : 'none';
        });
    });
})();

/* View modal with Potential Claimants / Matching Found Items */
(function () {
    var modal      = document.getElementById('viewModal');
    var imageEl    = document.getElementById('viewModalImage');
    var barcodeEl  = document.getElementById('viewModalBarcode');
    var bodyEl     = document.getElementById('viewModalBody');
    var claimantsEl = document.getElementById('viewModalClaimants');
    var sectionTitle = claimantsEl ? claimantsEl.previousElementSibling : null;
    var claimUrl   = '../get_potential_claimants.php';
    var foundItemsUrl = '../get_matching_found_items.php';
    if (!modal || !bodyEl || !claimantsEl) return;

    function esc(s) {
        return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function parseItemFromDesc(desc) {
        if (!desc) return '';
        var m = desc.match(/Item Type:\s*(.+?)(?:\n|$)/);
        return m ? m[1].trim() : '';
    }

    function openModalFromRow(row) {
        var cells   = row.querySelectorAll('td');
        var barcodeId = row.getAttribute('data-id') || (cells[0] ? cells[0].textContent.trim() : '');
        var imgUrl  = row.getAttribute('data-image');
        var itemDesc = row.getAttribute('data-item-description') || '';
        var isReport = row.getAttribute('data-is-report') === '1';

        if (imgUrl) {
            imageEl.innerHTML = '<img src="' + esc(imgUrl) + '" alt="Item">';
            imageEl.classList.remove('view-modal-image-placeholder');
        } else {
            imageEl.innerHTML = '<div class="view-modal-image-placeholder-inner"><span class="view-modal-image-icon" aria-hidden="true">&#128230;</span><span>Item image</span></div>';
            imageEl.classList.add('view-modal-image-placeholder');
        }
        barcodeEl.textContent = (isReport ? 'Reference ID: ' : 'Barcode ID: ') + (barcodeId || '—');
        barcodeEl.style.display = 'block';

        var isGuest = row.closest('#guestReportsTable') !== null;
        var itemLabel = parseItemFromDesc(itemDesc) || row.getAttribute('data-category') || '';
        var pairs;
        if (isReport) {
            pairs = [
                { label: 'Category',       value: row.getAttribute('data-category') || '' },
                { label: 'Item Type',     value: itemLabel },
                { label: 'Color',          value: row.getAttribute('data-color') || '' },
                { label: 'Brand',         value: row.getAttribute('data-brand') || '' },
                { label: 'Student Number', value: row.getAttribute('data-user-id') || '' },
                { label: 'Date Lost',     value: row.getAttribute('data-date-lost') || cells[3] ? cells[3].textContent.trim() : '' }
            ];
        } else if (isGuest) {
            pairs = [
                { label: 'Category',          value: row.getAttribute('data-category') || '' },
                { label: 'Item',              value: itemLabel },
                { label: 'Color',             value: row.getAttribute('data-color') || '' },
                { label: 'Brand',             value: row.getAttribute('data-brand') || '' },
                { label: 'Storage Location',  value: cells[4] ? cells[4].textContent.trim() : '' },
                { label: 'Encoded By',        value: cells[1] ? cells[1].textContent.trim() : '' },
                { label: 'Date Surrendered',  value: cells[2] ? cells[2].textContent.trim() : '' }
            ];
        } else {
            pairs = [
                { label: 'Category',          value: row.getAttribute('data-category') || '' },
                { label: 'Item',              value: itemLabel },
                { label: 'Color',             value: row.getAttribute('data-color') || '' },
                { label: 'Brand',             value: row.getAttribute('data-brand') || '' },
                { label: 'Item Description',  value: itemDesc.replace(/^Item Type:.*$/m, '').trim() || '' },
                { label: 'Storage Location',  value: row.getAttribute('data-storage-location') || '' },
                { label: 'Found At',          value: cells[2] ? cells[2].textContent.trim() : '' },
                { label: 'Found By',          value: row.getAttribute('data-found-by') || '' },
                { label: 'Date Found',        value: cells[3] ? cells[3].textContent.trim() : '' }
            ];
        }

        bodyEl.innerHTML = pairs
            .filter(function (p) { return p.value && String(p.value).trim() !== ''; })
            .map(function (p) {
                return '<div class="view-modal-row"><span class="view-modal-label">' + esc(p.label) + ':</span><span class="view-modal-value">' + esc(String(p.value)) + '</span></div>';
            }).join('') || '<p class="view-modal-empty">No details available.</p>';

        modal.classList.add('view-modal-open');
        modal.setAttribute('data-current-barcode', barcodeId);
        modal.setAttribute('data-is-report', isReport ? '1' : '0');

        if (sectionTitle) sectionTitle.textContent = isReport ? 'Matching Found Items' : 'Potential Claimants';
        var nextBtn = document.getElementById('viewModalNext');
        if (nextBtn) nextBtn.style.display = isReport ? 'none' : '';

        if (isReport) {
            claimantsEl.innerHTML = '<p class="view-modal-loading">Loading matching found items…</p>';
            fetch(foundItemsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: barcodeId })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok && data.found_items && data.found_items.length > 0) {
                    var html = '<div class="view-modal-claimants-list">';
                    data.found_items.forEach(function (f) {
                        html += '<div class="view-modal-claimant-item"><span>' + esc(f.id) + '</span></div>';
                    });
                    html += '</div>';
                    claimantsEl.innerHTML = html;
                } else {
                    claimantsEl.innerHTML = '<p class="view-modal-empty">No matching found items yet.</p>';
                }
            })
            .catch(function () {
                claimantsEl.innerHTML = '<p class="view-modal-empty">Could not load matching found items.</p>';
            });
        } else {
            claimantsEl.innerHTML = '<p class="view-modal-loading">Loading potential claimants…</p>';
            fetch(claimUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: barcodeId })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok && data.claimants && data.claimants.length > 0) {
                    var html = '<div class="view-modal-claimants-list">';
                    data.claimants.forEach(function (c) {
                        var email = c.email || c.user_id || '';
                        html += '<label class="view-modal-claimant-item"><input type="radio" name="claimant" value="' + esc(email) + '" data-report-id="' + esc(c.report_id || '') + '"><span>' + esc(email) + '</span></label>';
                    });
                    html += '</div>';
                    claimantsEl.innerHTML = html;
                } else {
                    claimantsEl.innerHTML = '<p class="view-modal-empty">No potential claimants found based on report data.</p>';
                }
            })
            .catch(function () {
                claimantsEl.innerHTML = '<p class="view-modal-empty">Could not load potential claimants.</p>';
            });
        }
    }

    function closeModal() { modal.classList.remove('view-modal-open'); }

    function bindViewToTable(tbl) {
        if (!tbl) return;
        tbl.addEventListener('click', function (e) {
            var btn = e.target.closest('.found-btn-view');
            if (!btn) return;
            e.preventDefault();
            var r = btn.closest('tr');
            if (r && !r.querySelector('td[colspan]')) openModalFromRow(r);
        });
    }
    bindViewToTable(document.getElementById('matchedReportsTable'));
    bindViewToTable(document.getElementById('guestReportsTable'));

    var closeBtn = modal.querySelector('.view-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    var cancelBtn = document.getElementById('viewModalCancel');
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    var nextBtn = document.getElementById('viewModalNext');
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            var selected = modal.querySelector('input[name="claimant"]:checked');
            var barcode = modal.getAttribute('data-current-barcode');
            if (selected && barcode) {
                closeModal();
            } else {
                closeModal();
            }
        });
    }
})();

/* Claim button */
(function () {
    var claimUrl = '../claim_item.php';

    function onClaimClick(e) {
        var btn = e.target.closest('.found-btn-claim');
        if (!btn || btn.disabled || btn.classList.contains('btn-claim-expired')) return;
        e.preventDefault();
        var tr = btn.closest('tr');
        if (!tr || tr.querySelector('td[colspan]')) return;
        var id = tr.getAttribute('data-id') || (tr.querySelector('td') && tr.querySelector('td').textContent.trim());
        if (!id) return;
        if (!confirm('Claim this item? It will be moved to History as Claimed.')) return;
        btn.disabled = true;
        fetch(claimUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.ok) {
                var tbody    = tr.parentNode;
                var colCount = tr.querySelectorAll('td').length;
                tr.remove();
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    var empty = document.createElement('tr');
                    empty.innerHTML = '<td colspan="' + colCount + '" class="table-empty">No items.</td>';
                    tbody.appendChild(empty);
                }
            } else {
                btn.disabled = false;
                alert(data.error || 'Could not claim item.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            alert('Could not claim item. Try again.');
        });
    }

    var tbl1 = document.getElementById('matchedReportsTable');
    var tbl2 = document.getElementById('guestReportsTable');
    if (tbl1) tbl1.addEventListener('click', onClaimClick);
    if (tbl2) tbl2.addEventListener('click', onClaimClick);
})();

/* Tab switching */
(function () {
    var allTab    = document.getElementById('allItemsTab');
    var guestTab  = document.getElementById('guestItemsTab');
    var recovered = document.getElementById('recoveredSection');
    var guest     = document.getElementById('guestSection');
    if (!allTab || !guestTab || !recovered || !guest) return;

    function showAll() {
        allTab.classList.add('found-tab-active');
        guestTab.classList.remove('found-tab-active');
        recovered.style.display = '';
        guest.style.display     = 'none';
    }
    function showGuest() {
        guestTab.classList.add('found-tab-active');
        allTab.classList.remove('found-tab-active');
        guest.style.display     = '';
        recovered.style.display = 'none';
    }

    allTab.addEventListener('click', showAll);
    guestTab.addEventListener('click', showGuest);
    if (window.location.hash === '#guest') showGuest();
})();
</script>
<script src="NotificationsDropdown.js"></script>
</body>
</html>