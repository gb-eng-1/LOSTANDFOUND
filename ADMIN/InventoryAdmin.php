<?php
// InventoryAdmin.php - Inventory view for UB Lost and Found System (view recovered items)
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$encodedItems = get_items($pdo, null);
$today = date('Y-m-d');
$overdueCount = 0;
foreach ($encodedItems as $it) {
    $dateEnc = $it['dateEncoded'] ?? null;
    $retEnd = $dateEnc ? date('Y-m-d', strtotime($dateEnc . ' +2 years')) : '';
    if ($retEnd && $retEnd < $today) $overdueCount++;
}

$itemCategories = require dirname(__DIR__) . '/config/categories.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UB Lost and Found System - Inventory</title>
    <link rel="stylesheet" href="AdminDashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="InventoryAdmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
/* Sidebar mobile: cancel min-height so no blank gap below nav */
@media (max-width: 900px) {
  .sidebar  { min-height: 0 !important; height: auto !important; }
  .nav-menu { flex: none !important; }
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
                    <div class="nav-item-icon"><i class="fa-regular fa-circle-check"></i></div>
                    <div class="nav-item-label">Matching</div>
                </a>
            </li>
            <li>
                <a class="nav-item" href="HistoryAdmin.php">
                    <div class="nav-item-icon"><i class="fa-regular fa-clock-rotate-left"></i></div>
                    <div class="nav-item-label">History</div>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main">
        <div class="topbar topbar-maroon">
            <div class="topbar-search-wrap topbar-search-left">
                <form class="search-form" action="InventoryAdmin.php" method="get">
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
            <div class="inventory-header-row">
                <h2 class="inventory-page-title">Inventory</h2>
                <div class="inventory-filter-wrap">
                    <select class="inventory-filter-select" id="inventoryCategoryFilter" aria-label="Filter by category">
                        <option value="">Filter By</option>
                        <?php foreach ($itemCategories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Retention policy alert -->
            <div class="inventory-retention-alert">
                <span class="inventory-retention-text">There are <strong><?php echo (int)$overdueCount; ?></strong> Item<?php echo $overdueCount !== 1 ? 's' : ''; ?> that have exceeded the retention policy.</span>
                <a href="#" class="inventory-dispose-link" id="inventoryDisposeLink">Dispose Items</a>
            </div>

            <!-- Recovered Items (Internal) -->
            <div class="inventory-card">
                <div class="inventory-section-title">Recovered Items (Internal)</div>
                <div class="table-wrapper">
                    <table class="inventory-table">
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
                        echo '<tr><td colspan="8" class="table-empty">No items in inventory.</td></tr>';
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
                            echo '<tr' . $dataAttrs . '><td>' . $barcodeId . '</td><td>' . $cat . '</td><td>' . $foundAt . '</td><td>' . htmlspecialchars($dateEncoded) . '</td><td>' . htmlspecialchars($retentionEnd) . '</td><td>' . $storage . '</td><td>' . $timestamp . '</td><td><button type="button" class="inventory-btn-view">View</button></td></tr>';
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

<!-- Item Details modal -->
<div id="viewModal" class="view-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="view-modal" onclick="event.stopPropagation()">
        <div class="view-modal-header">
            <h3 id="viewModalTitle" class="view-modal-title">Item Details</h3>
            <button type="button" class="view-modal-close" aria-label="Close" title="Close">&times;</button>
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
})();

(function () {
    var categorySelect = document.getElementById('inventoryCategoryFilter');
    var tbody = document.getElementById('inventoryTableBody');
    if (!tbody || !categorySelect) return;

    function applyFilter() {
        var rows = tbody.querySelectorAll('tr');
        var categoryValue = (categorySelect.value || '').trim();
        rows.forEach(function (row) {
            if (row.querySelector('td[colspan]')) return;
            var category = row.getAttribute('data-category');
            if (category === null) {
                row.style.display = categoryValue ? 'none' : '';
                return;
            }
            row.style.display = (!categoryValue || category === categoryValue) ? '' : 'none';
        });
    }

    categorySelect.addEventListener('change', applyFilter);
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
        if (imgUrl) {
            imageEl.innerHTML = '<img src="' + esc(imgUrl) + '" alt="Item">';
            imageEl.classList.remove('view-modal-image-placeholder');
        } else {
            imageEl.innerHTML = '<div class="view-modal-image-placeholder-inner"><span class="view-modal-image-icon" aria-hidden="true">&#128230;</span><span>Item image</span></div>';
            imageEl.classList.add('view-modal-image-placeholder');
        }
        var pairs = [], seen = {};
        var extra = [{ key: 'Barcode ID', attr: 'data-id' }, { key: 'Category', attr: 'data-category' }, { key: 'Color', attr: 'data-color' }, { key: 'Brand', attr: 'data-brand' }, { key: 'Found By', attr: 'data-found-by' }, { key: 'Date Encoded', attr: 'data-date-encoded' }, { key: 'Storage Location', attr: 'data-storage-location' }];
        for (var i = 0; i < cells.length - 1; i++) {
            var label = getThLabel(headers[i]);
            var val = (cells[i] && cells[i].textContent) ? cells[i].textContent.trim() : '';
            if (label && label !== 'Action') {
                pairs.push({ label: label, value: val });
                seen[label] = true;
            }
        }
        for (var j = 0; j < extra.length; j++) {
            if (seen[extra[j].key]) continue;
            var v = row.getAttribute(extra[j].attr);
            if (v) pairs.push({ label: extra[j].key, value: v });
        }
        bodyEl.innerHTML = pairs.map(function (p) {
            return '<div class="view-modal-row"><span class="view-modal-label">' + esc(p.label) + ':</span><span class="view-modal-value">' + esc(p.value) + '</span></div>';
        }).join('') || '<p class="view-modal-empty">No details available.</p>';
        modal.classList.add('view-modal-open');
    }

    function closeModal() {
        modal.classList.remove('view-modal-open');
    }

    document.querySelectorAll('.inventory-btn-view').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var r = e.target.closest('tr');
            if (r) openModal(r);
        });
    });

    modal.querySelector('.view-modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    var cancelBtn = document.getElementById('viewModalCancel');
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>
<script src="NotificationsDropdown.js"></script>
</body>
</html>