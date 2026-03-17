<?php
/**
 * Claim Item - Browse Available Items
 * UB Lost and Found System - Student POV
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$studentId = (int) $_SESSION['student_id'];
$studentEmail = $_SESSION['student_email'] ?? '';
$studentName = $_SESSION['student_name'] ?? '';

// Get available items to claim (found items that are unclaimed)
$availableItems = [];
try {
    $stmt = $pdo->query("SELECT id, item_type, color, brand, item_description, found_at, date_encoded, image_data FROM items WHERE id NOT LIKE 'REF-%' AND status IN ('Unclaimed Items', 'For Verification', 'Found') ORDER BY date_encoded DESC, created_at DESC");
    $availableItems = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    error_log("ClaimItem.php: Database error: " . $e->getMessage());
    $availableItems = [];
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $searchLower = strtolower($search);
    $availableItems = array_filter($availableItems, function ($item) use ($searchLower) {
        return strpos(strtolower($item['item_type'] ?? ''), $searchLower) !== false
            || strpos(strtolower($item['color'] ?? ''), $searchLower) !== false
            || strpos(strtolower($item['brand'] ?? ''), $searchLower) !== false
            || strpos(strtolower($item['id'] ?? ''), $searchLower) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Claim Item - UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="StudentsReport.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ItemDetailsModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ClaimItemModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <style>
    .nav-item.active .nav-item-icon, .nav-item.active .nav-item-icon i, .nav-item.active .nav-item-label { color: #ffffff !important; }
    .nav-menu .nav-item:not(.active) .nav-item-icon, .nav-menu .nav-item:not(.active) .nav-item-icon i, .nav-menu .nav-item:not(.active) .nav-item-label { color: #8b0000 !important; }
    .main .topbar { flex-shrink: 0; z-index: 10; }
    .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    .item-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
    .item-card-image { width: 100%; height: 200px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; }
    .item-card-image img { width: 100%; height: 100%; object-fit: cover; }
    .item-card-placeholder { color: #999; font-size: 48px; }
    .item-card-body { padding: 15px; }
    .item-card-title { font-weight: 600; margin-bottom: 10px; }
    .item-card-details { font-size: 14px; color: #666; margin-bottom: 15px; }
    .item-card-actions { display: flex; gap: 10px; }
    .btn-claim { background: #4caf50; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
    .btn-claim:hover { background: #45a049; }
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
      <li><a class="nav-item" href="StudentDashboard.php"><div class="nav-item-icon"><i class="fa-solid fa-house"></i></div><div class="nav-item-label">Dashboard</div></a></li>
      <li><a class="nav-item" href="StudentsReport.php"><div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div><div class="nav-item-label">My Reports</div></a></li>
      <li><a class="nav-item" href="ClaimHistory.php"><div class="nav-item-icon"><i class="fa-regular fa-calendar"></i></div><div class="nav-item-label">Claim History</div></a></li>
      <li><a class="nav-item" href="HelpSupport.php"><div class="nav-item-icon"><i class="fa-solid fa-circle-question"></i></div><div class="nav-item-label">Help and Support</div></a></li>
    </ul>
  </aside>

  <main class="main">
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-right">
        <form class="search-form" action="ClaimItem.php" method="get">
          <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search items..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
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

    <div class="main-content-wrap">
      <h1 class="page-title-browse">Available Items to Claim</h1>
      
      <div class="items-grid">
        <?php if (empty($availableItems)): ?>
          <p>No items available for claiming at this time.</p>
        <?php else: ?>
          <?php foreach ($availableItems as $item): ?>
            <div class="item-card">
              <div class="item-card-image">
                <?php if (!empty($item['image_data'])): ?>
                  <?php
                    $src = $item['image_data'];
                    if (strpos($src, 'data:') !== 0) $src = 'data:image/jpeg;base64,' . $src;
                  ?>
                  <img src="<?php echo htmlspecialchars($src); ?>" alt="Item">
                <?php else: ?>
                  <div class="item-card-placeholder"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
              </div>
              <div class="item-card-body">
                <div class="item-card-title"><?php echo htmlspecialchars($item['id']); ?></div>
                <div class="item-card-details">
                  <strong>Type:</strong> <?php echo htmlspecialchars($item['item_type'] ?? 'Unknown'); ?><br>
                  <strong>Color:</strong> <?php echo htmlspecialchars($item['color'] ?? '-'); ?><br>
                  <strong>Brand:</strong> <?php echo htmlspecialchars($item['brand'] ?? '-'); ?><br>
                  <strong>Found:</strong> <?php echo htmlspecialchars($item['found_at'] ?? '-'); ?>
                </div>
                <div class="item-card-actions">
                  <button type="button" class="btn-view" data-view-item="<?php echo htmlspecialchars($item['id']); ?>">View Details</button>
                  <button type="button" class="btn-claim" data-claim-item="<?php echo htmlspecialchars($item['id']); ?>">Claim</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
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
    var input = document.getElementById('adminSearchInput');
    var clearBtn = document.getElementById('adminSearchClear');
    if (!input || !clearBtn) return;
    function syncClear() { clearBtn.style.display = input.value ? 'flex' : 'none'; }
    clearBtn.addEventListener('click', function () { input.value = ''; input.focus(); syncClear(); });
    input.addEventListener('input', syncClear);
    syncClear();
})();
</script>
<script src="ItemDetailsModal.js?v=<?php echo time(); ?>"></script>
<script src="ClaimItemModal.js?v=<?php echo time(); ?>"></script>
<script>
// Handle view item buttons
document.addEventListener('click', function(e) {
  if (e.target.matches('[data-view-item]')) {
    e.preventDefault();
    var itemId = e.target.getAttribute('data-view-item');
    showItemDetailsModal(itemId, {
      showClaimButton: true,
      onClaim: function(item) {
        closeItemDetailsModal();
        showClaimItemModal(item.id);
      }
    });
  }
});

// Handle claim item buttons
document.addEventListener('click', function(e) {
  if (e.target.matches('[data-claim-item]')) {
    e.preventDefault();
    var itemId = e.target.getAttribute('data-claim-item');
    showClaimItemModal(itemId);
  }
});
</script>
</body>
</html>
