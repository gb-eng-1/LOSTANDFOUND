<?php
/**
 * Students Report - Browse Items / My Reports
 * UB Lost and Found System - Student POV
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$studentId = (int) $_SESSION['student_id'];
$studentEmail = $_SESSION['student_email'] ?? '';
$studentName = $_SESSION['student_name'] ?? '';

$studentNumber = null;
$stmt = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
$stmt->execute([$studentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && !empty($row['student_id'])) {
    $studentNumber = trim($row['student_id']);
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
        $sql = "SELECT r.id, r.item_type, r.date_lost, r.item_description, r.matched_barcode_id, r.status, r.created_at, f.status AS found_status, c.status AS claim_status
                FROM items r
                LEFT JOIN items f ON r.matched_barcode_id = f.id
                LEFT JOIN claims c ON r.id = c.lost_report_id
                WHERE r.id LIKE 'REF-%' AND r.status != 'Cancelled' AND (r.user_id IN ($placeholders) OR LOWER(TRIM(user_id)) = LOWER(?))
                ORDER BY r.created_at DESC";
    } else {
        $sql = "SELECT r.id, r.item_type, r.date_lost, r.item_description, NULL AS matched_barcode_id, r.status, r.created_at, NULL AS found_status, c.status AS claim_status
                FROM items r
                LEFT JOIN claims c ON r.id = c.lost_report_id
                WHERE r.id LIKE 'REF-%' AND r.status != 'Cancelled' AND (r.user_id IN ($placeholders) OR LOWER(TRIM(user_id)) = LOWER(?))
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
        ];
    }
} catch (PDOException $e) {
    error_log("StudentsReport_fixed.php: Database error: " . $e->getMessage());
    $myReports = [];
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $searchLower = strtolower($search);
    $myReports = array_filter($myReports, function ($r) use ($searchLower) {
        return strpos(strtolower($r['ticket_id']), $searchLower) !== false
            || strpos(strtolower($r['category']), $searchLower) !== false
            || strpos(strtolower($r['department']), $searchLower) !== false;
    });
}
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="StudentsReport.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ReportLostModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="ViewReport.css?v=<?php echo time(); ?>">
  <style>
    .nav-item.active .nav-item-icon, .nav-item.active .nav-item-icon i, .nav-item.active .nav-item-label { color: #ffffff !important; }
    .nav-menu .nav-item:not(.active) .nav-item-icon, .nav-menu .nav-item:not(.active) .nav-item-icon i, .nav-menu .nav-item:not(.active) .nav-item-label { color: #8b0000 !important; }
    .main .topbar { flex-shrink: 0; z-index: 10; }
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
        <form class="search-form" action="StudentsReport.php" method="get">
          <?php if ($filter): ?><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"><?php endif; ?>
          <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search" autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
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

      <div class="report-tabs">
        <a href="StudentsReport.php?filter=all" class="report-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All Reports</a>
        <a href="StudentsReport.php?filter=matched" class="report-tab <?php echo $filter === 'matched' ? 'active' : ''; ?>">Matched Reports</a>
        <a href="#" class="report-tab report-tab-primary" data-open-report-lost>Report Lost Item</a>
      </div>

      <h2 class="section-title-my">My Reports</h2>

      <div class="table-wrapper">
        <table class="reports-data-table">
          <thead>
            <tr>
              <th>Ticket ID</th>
              <th>Category</th>
              <th>Department</th>
              <th>ID</th>
              <th>Contact Number</th>
              <th>Date Lost</th>
              <?php if ($filter === 'matched'): ?><th>Status</th><?php endif; ?>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $colSpan = $filter === 'matched' ? 8 : 7; ?>
            <?php if (empty($myReports)): ?>
              <tr><td colspan="<?php echo $colSpan; ?>" class="table-empty">No reports yet.</td></tr>
            <?php else: ?>
              <?php foreach (array_values($myReports) as $i => $r): ?>
                <tr class="<?php echo $i % 2 === 0 ? 'row-even' : 'row-odd'; ?>">
                  <td><?php echo htmlspecialchars($r['ticket_id']); ?></td>
                  <td><?php echo htmlspecialchars($r['category']); ?></td>
                  <td><?php echo htmlspecialchars($r['department']); ?></td>
                  <td><?php echo htmlspecialchars($r['id_num']); ?></td>
                  <td><?php echo htmlspecialchars($r['contact_number']); ?></td>
                  <td><?php echo htmlspecialchars($r['date_lost']); ?></td>
                  <?php if ($filter === 'matched'): ?>
                  <td>
                    <?php if (!empty($r['matched'])): ?>
                      <span class="badge-matched">Matched</span>
                    <?php else: ?>
                      <span class="badge-pending">Pending</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  <td class="action-cell">
                    <a href="#" class="btn-view" data-view-report-id="<?php echo htmlspecialchars($r['id']); ?>">View</a>
                    <a href="#" class="btn-cancel" data-cancel-report="<?php echo urlencode($r['id']); ?>">Cancel</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="report-success-overlay" aria-hidden="true">
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
    var input = document.getElementById('adminSearchInput');
    var clearBtn = document.getElementById('adminSearchClear');
    if (!input || !clearBtn) return;
    function syncClear() { clearBtn.style.display = input.value ? 'flex' : 'none'; }
    clearBtn.addEventListener('click', function () { input.value = ''; input.focus(); syncClear(); });
    input.addEventListener('input', syncClear);
    syncClear();
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
  document.addEventListener('click', function (e) {.
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
  // Custom confirmation modal
  var confirmModal = document.getElementById('confirmModal');
  var confirmMessage = document.getElementById('confirmMessage');
  var confirmCancel = document.getElementById('confirmCancel');
  var confirmOk = document.getElementById('confirmOk');
  var pendingAction = null;

  function showConfirm(message, callback) {
    if (!confirmModal || !confirmMessage) return;
    confirmMessage.textContent = message;
    confirmModal.classList.add('open');
    confirmModal.setAttribute('aria-hidden', 'false');
    pendingAction = callback;
  }

  function hideConfirm() {
    if (!confirmModal) return;
    confirmModal.classList.remove('open');
    confirmModal.setAttribute('aria-hidden', 'true');
    pendingAction = null;
  }

  if (confirmCancel) {
    confirmCancel.addEventListener('click', hideConfirm);
  }

  if (confirmOk) {
    confirmOk.addEventListener('click', function() {
      if (pendingAction) {
        pendingAction();
      }
      hideConfirm();
    });
  }

  // Handle cancel report buttons
  document.addEventListener('click', function(e) {
    if (e.target.matches('[data-cancel-report]')) {
      e.preventDefault();
      var reportId = e.target.getAttribute('data-cancel-report');
      showConfirm('Are you sure you want to cancel this report?', function() {
        window.location.href = 'CancelReport.php?id=' + encodeURIComponent(reportId);
      });
    }
  });

  // ESC key handling
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && confirmModal && confirmModal.classList.contains('open')) {
      hideConfirm();
    }
  });
})();
</script>
<div id="viewReportModal" class="view-report-card" aria-hidden="true" style="display:none;">
  <header class="view-report-header">
    <h1 class="view-report-title">Item Details</h1>
    <button type="button" class="view-report-close" aria-label="Close" data-close-view-report><i class="fa-solid fa-xmark"></i></button>
  </header>
  <div class="view-report-body">
    <div class="view-report-left">
      <div class="view-report-image-wrap">
        <div class="view-report-image-placeholder" data-field="image-placeholder"><i class="fa-solid fa-image"></i><span>No image</span></div>
        <img src="" alt="Item" class="view-report-image" data-field="image" style="display:none;">
      </div>
      <p class="view-report-ticket-id" data-field="ticket-id"></p>
    </div>
    <div class="view-report-right">
      <h2 class="view-report-info-title">General Information</h2>
      <dl class="view-report-info-list">
        <div class="view-report-info-row"><dt>Category</dt><dd data-field="category">—</dd></div>
        <div class="view-report-info-row"><dt>Full Name</dt><dd data-field="fullname">—</dd></div>
        <div class="view-report-info-row"><dt>Contact Number</dt><dd data-field="contact">—</dd></div>
        <div class="view-report-info-row"><dt>Department</dt><dd data-field="department">—</dd></div>
        <div class="view-report-info-row"><dt>ID</dt><dd data-field="id_num">—</dd></div>
        <div class="view-report-info-row"><dt>Item</dt><dd data-field="item_name">—</dd></div>
        <div class="view-report-info-row"><dt>Color</dt><dd data-field="color">—</dd></div>
        <div class="view-report-info-row"><dt>Brand</dt><dd data-field="brand">—</dd></div>
        <div class="view-report-info-row"><dt>Item Description</dt><dd data-field="description">—</dd></div>
        <div class="view-report-info-row"><dt>Date Lost</dt><dd data-field="date_lost">—</dd></div>
      </dl>
    </div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('viewReportModal');
  if (!modal) return;

  var fields = {
    'image-placeholder': modal.querySelector('[data-field="image-placeholder"]'),
    'image': modal.querySelector('[data-field="image"]'),
    'ticket-id': modal.querySelector('[data-field="ticket-id"]'),
    'category': modal.querySelector('[data-field="category"]'),
    'fullname': modal.querySelector('[data-field="fullname"]'),
    'contact': modal.querySelector('[data-field="contact"]'),
    'department': modal.querySelector('[data-field="department"]'),
    'id_num': modal.querySelector('[data-field="id_num"]'),
    'item_name': modal.querySelector('[data-field="item_name"]'),
    'color': modal.querySelector('[data-field="color"]'),
    'brand': modal.querySelector('[data-field="brand"]'),
    'description': modal.querySelector('[data-field="description"]'),
    'date_lost': modal.querySelector('[data-field="date_lost"]'),
  };

  function showModal() {
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
  }

  function hideModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }
  
  function populateModal(data) {
    var studentName = document.body.getAttribute('data-student-name') || '—';
    var studentEmail = document.body.getAttribute('data-student-email') || '';

    var desc = data.item_description || '';
    var studentNum = '';
    var contact = '';
    var dept = '';
    var itemName = '';
    var mainDesc = '';

    var m;
    if (m = desc.match(/Student Number:\\s*(.+?)(?:\\n|$)/m)) { studentNum = m[1].trim(); }
    if (m = desc.match(/Item Type:\\s*(.+?)(?:\\n|$)/m)) { itemName = m[1].trim(); }
    if (m = desc.match(/Contact:\\s*(.+?)(?:\\n|$)/m)) { contact = m[1].trim(); }
    if (m = desc.match(/Department:\\s*(.+?)(?:\\n|$)/m)) { dept = m[1].trim(); }

    mainDesc = desc.replace(/\\nContact:[^\\n]*(?:\\nDepartment:\\s*[^\n]*)?$/s, '');
    mainDesc = mainDesc.replace(/^(?:Student Number:|Item Type:)[^\\n]*\\n?/m, '').trim();

    var isOwnReport = (data.user_id || '').toLowerCase().trim() === studentEmail.toLowerCase().trim();
    var fullName = isOwnReport ? studentName : '—';

    fields['ticket-id'].textContent = data.id || '—';
    fields['category'].textContent = data.item_type || '—';
    fields['fullname'].textContent = fullName;
    fields['contact'].textContent = contact || '—';
    fields['department'].textContent = dept || '—';
    fields['id_num'].textContent = studentNum || '—';
    fields['item_name'].textContent = itemName || '—';
    fields['color'].textContent = data.color || '—';
    fields['brand'].textContent = data.brand || '—';
    fields['description'].textContent = mainDesc || '—';
    fields['date_lost'].textContent = data.date_lost ? data.date_lost.split(' ')[0] : '—';

    if (data.image_data) {
      var src = data.image_data;
      if (src.indexOf('data:') !== 0) {
        src = 'data:image/jpeg;base64,' + src;
      }
      fields['image'].src = src;
      fields['image'].style.display = 'block';
      fields['image-placeholder'].style.display = 'none';
    } else {
      fields['image'].style.display = 'none';
      fields['image-placeholder'].style.display = 'block';
    }
  }

  document.addEventListener('click', function(e) {
    if (e.target.closest('[data-view-report-id]')) {
      e.preventDefault();
      var reportId = e.target.closest('[data-view-report-id]').getAttribute('data-view-report-id');
      
      // Here you would fetch data from the backend.
      // For now, we'll use a dummy object.
      // In a real app, this would be an API call:
      // fetch('/api/student/reports/' + reportId)
      //   .then(res => res.json())
      //   .then(json => {
      //      if (json.ok) {
      //        populateModal(json.data);
      //        showModal();
      //      } else {
      //        alert('Error: ' + json.error);
      //      }
      //   });
      
      var report = <?php echo json_encode($myReports); ?>.find(r => r.id === reportId);
      if (report) {
        populateModal(report);
        showModal();
      } else {
        alert('Report not found!');
      }
    }

    if (e.target.closest('[data-close-view-report]')) {
      e.preventDefault();
      hideModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
      hideModal();
    }
  });
})();
</script>

<?php require_once __DIR__ . '/includes/report_lost_modal.php'; ?>
<script src="ReportLostModal.js?v=<?php echo time(); ?>"></script>
</body>
</html>
