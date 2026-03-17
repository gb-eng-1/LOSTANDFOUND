<?php
/**
 * Notifications.php — Full notification history for students
 * Uses the same sidebar + topbar layout as all other student pages.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$studentId    = (int) ($_SESSION['student_id'] ?? 0);
$studentEmail = $_SESSION['student_email'] ?? '';
$studentName  = $_SESSION['student_name']  ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <!-- FA-JS deferred: all inline scripts below run first — critical for bell icon fix -->
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="StudentDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <style>
    /* ── Force FA icons visible ── */
    .fa-solid,.fa-regular,.fa-brands { display: inline-block !important; }

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
    .topbar-right .topbar-icon-btn { color: #ffffff !important; }
    .topbar-right .topbar-icon-btn i { color: #ffffff !important; }
    /* FA-JS replaces <i> with inline <svg>. Target svg AND path children. */
    .topbar-right .topbar-icon-btn svg,
    .topbar-right .topbar-icon-btn svg path,
    .topbar-right .topbar-icon-btn .svg-inline--fa,
    #notifTrigger svg,
    #notifTrigger svg path,
    #notifTrigger .svg-inline--fa { fill: #ffffff !important; color: #ffffff !important; }

    /* ── Sidebar mobile fix ── */
    @media (max-width: 900px) {
      .sidebar { min-height: 0 !important; height: auto !important; }
    }

    /* ── Search dropdown ── */
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

    /* ── Page content ── */
    .notif-page-wrap {
      padding: 28px 32px 48px;
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
    }
    .notif-page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 22px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .notif-page-title {
      font-size: 26px; font-weight: 700; color: #111; margin: 0;
    }
    .notif-mark-all-btn {
      padding: 8px 18px;
      background: #8b0000;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-family: Poppins, sans-serif;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.15s;
    }
    .notif-mark-all-btn:hover { opacity: 0.88; }
    .notif-mark-all-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .notif-toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(80px);
      background: #111827;
      color: #fff;
      font-family: Poppins, sans-serif;
      font-size: 13px;
      font-weight: 500;
      padding: 10px 22px;
      border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.25);
      transition: transform 0.25s ease;
      z-index: 9999;
      pointer-events: none;
    }
    .notif-toast.show { transform: translateX(-50%) translateY(0); }

    /* ── Notification card list ── */
    .notif-card-list {
      display: flex; flex-direction: column; gap: 12px;
      max-width: 860px;
    }
    .notif-card {
      display: flex; align-items: flex-start; gap: 16px;
      background: #ffffff;
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
      border: 1px solid #e5e7eb;
      transition: box-shadow 0.15s;
    }
    .notif-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.11); }
    .notif-card.is-new { border-left: 3px solid #8b0000; }

    /* Thumbnail */
    .notif-card-thumb {
      flex-shrink: 0; width: 72px; height: 72px;
      border-radius: 10px; overflow: hidden;
      background: #f3f4f6;
      display: flex; align-items: center; justify-content: center;
    }
    .notif-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .notif-card-thumb-placeholder { font-size: 26px; color: #9ca3af; }

    /* Content */
    .notif-card-body { flex: 1; min-width: 0; }
    .notif-card-top {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 5px; flex-wrap: wrap;
    }
    .notif-card-title { font-size: 15px; font-weight: 700; color: #111; }
    .notif-new-pill {
      font-size: 11px; font-weight: 600; color: #374151;
      background: #e5e7eb; border-radius: 20px; padding: 2px 10px;
    }
    .notif-card-date {
      font-size: 12px; color: #9ca3af;
      margin-left: auto; white-space: nowrap; align-self: flex-start;
    }
    .notif-card-message { font-size: 13px; color: #374151; line-height: 1.55; }
    .notif-detail-link {
      color: #1d4ed8; font-weight: 500; text-decoration: none; margin-left: 4px;
    }
    .notif-detail-link:hover { text-decoration: underline; }

    /* States */
    .notif-loading, .notif-empty {
      text-align: center; padding: 60px 24px; font-size: 15px; color: #9ca3af;
    }
    .notif-empty i { font-size: 40px; margin-bottom: 14px; display: block; color: #d1d5db; }
  </style>
</head>
<body data-student-email="<?= htmlspecialchars($studentEmail) ?>">
<div class="layout">

  <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
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

  <!-- ── Main ──────────────────────────────────────────────────────── -->
  <main class="main">

    <!-- Topbar -->
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-left">
        <form class="search-form" id="searchForm" autocomplete="off">
          <input id="adminSearchInput" type="text" class="search-input"
                 placeholder="Search items by name or barcode…" autocomplete="off">
          <div id="searchDropdown" class="search-dropdown"></div>
          <button id="adminSearchClear" class="search-clear" type="button"
                  title="Clear" aria-label="Clear search">
            <i class="fa-solid fa-xmark"></i>
          </button>
          <button class="search-submit" type="submit" title="Search" aria-label="Search">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </form>
      </div>
      <div class="topbar-right">
        <?php require_once __DIR__ . '/includes/notifications_dropdown.php'; ?>
        <div class="admin-dropdown" id="adminDropdown">
          <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger"
                  aria-expanded="false" aria-haspopup="true" aria-label="Student menu">
            <i class="fa-regular fa-user"></i>
            <span class="admin-name"><?php echo htmlspecialchars($studentName ?: $studentEmail); ?></span>
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
    <div class="notif-page-wrap">
      <div class="notif-page-header">
        <h1 class="notif-page-title">Notifications</h1>
        <button type="button" class="notif-mark-all-btn" id="markAllBtn">
          <i class="fa-solid fa-check-double" style="margin-right:6px;"></i>Mark All as Read
        </button>
      </div>
      <div id="notifCardList" class="notif-card-list">
        <div class="notif-loading">
          <i class="fa-solid fa-spinner fa-spin"></i>&nbsp; Loading…
        </div>
      </div>
    </div>

  </main>
</div>

<!-- ── Admin dropdown ─────────────────────────────────────────────── -->
<script>
(function() {
  var dd = document.getElementById('adminDropdown');
  var tg = dd && dd.querySelector('.admin-dropdown-trigger');
  if (!tg) return;
  tg.addEventListener('click', function(e) {
    e.stopPropagation();
    dd.classList.toggle('open');
    tg.setAttribute('aria-expanded', dd.classList.contains('open'));
  });
  document.addEventListener('click', function(e) {
    if (!dd.contains(e.target)) {
      dd.classList.remove('open');
      tg.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>

<!-- ── Notification bell toggle ──────────────────────────────────────── -->
<script>
(function() {
  var tg = document.getElementById('notifTrigger');
  var pn = document.getElementById('notifPanel');
  if (!tg || !pn) return;
  tg.addEventListener('click', function(e) {
    e.stopPropagation();
    var open = pn.classList.toggle('open');
    tg.setAttribute('aria-expanded', open);
    pn.setAttribute('aria-hidden', !open);
  });
  document.addEventListener('click', function(e) {
    if (!pn.contains(e.target) && e.target !== tg) {
      pn.classList.remove('open');
      tg.setAttribute('aria-expanded', 'false');
      pn.setAttribute('aria-hidden', 'true');
    }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      pn.classList.remove('open');
      tg.setAttribute('aria-expanded', 'false');
      pn.setAttribute('aria-hidden', 'true');
    }
  });
  function patchFooter() {
    pn.querySelectorAll('a').forEach(function(a) {
      if (!a.closest('.notif-list')) {
        a.textContent = 'View other notifications';
        a.href = 'Notifications.php';
      }
    });
  }
  patchFooter();
  tg.addEventListener('click', function() { setTimeout(patchFooter, 80); });
})();
</script>

<!-- ── Universal search bar ──────────────────────────────────────────── -->
<script>
(function() {
  var input    = document.getElementById('adminSearchInput');
  var clearBtn = document.getElementById('adminSearchClear');
  var dropdown = document.getElementById('searchDropdown');
  var form     = document.getElementById('searchForm');
  if (!input || !dropdown) return;

  var timer = null, lastQ = '';

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function(c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
    });
  }

  function render(items, q) {
    if (!items || !items.length) {
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
      if (item.date)     meta += '<span class="sd-meta-item"><i class="fa-regular fa-calendar"></i>'   + esc(item.date) + '</span>';
      return '<div class="search-dropdown-item" data-id="' + esc(item.id) + '">' +
        '<div class="sd-icon"><i class="fa-regular fa-file-lines"></i></div>' +
        '<div class="sd-info">' +
          '<div class="sd-barcode">' + esc(item.id) + '</div>' +
          '<div class="sd-title">'   + esc(name)    + '</div>' +
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
      .then(function(r) { return r.json(); })
      .then(function(data) { render(data, q); })
      .catch(function() { dropdown.style.display = 'none'; });
  }

  input.addEventListener('input', function() {
    var v = this.value.trim();
    if (clearBtn) clearBtn.style.display = v ? 'flex' : 'none';
    clearTimeout(timer);
    if (v.length < 2) { dropdown.style.display = 'none'; lastQ = ''; return; }
    timer = setTimeout(function() { doSearch(v); }, 220);
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

<!-- ── Bell icon: pre-fix + SVG fill + path fill ────────────────────────
     Inline scripts run BEFORE deferred scripts (FA-JS).
     Swap fa-regular → fa-solid so FA-JS renders the FILLED bell.
     Then force white on svg + svg path once FA-JS fires at DOMContentLoaded.
     MutationObserver covers any future re-renders. -->
<script>
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
      btn.querySelectorAll('svg').forEach(function(s) { s.style.fill = '#fff'; s.style.color = '#fff'; });
      btn.querySelectorAll('svg path').forEach(function(p) { p.style.fill = '#fff'; });
    }
    applyWhite();
    new MutationObserver(applyWhite).observe(btn, { childList: true, subtree: true });
  });
})();
</script>

<!-- Toast -->
<div class="notif-toast" id="notifToast"></div>

<!-- ── Load all notifications ─────────────────────────────────────────── -->
<script>
(function() {
  var list       = document.getElementById('notifCardList');
  var markAllBtn = document.getElementById('markAllBtn');
  var toast      = document.getElementById('notifToast');
  var _allNotifs = [];

  function fmtDate(str) {
    if (!str) return '';
    var d = new Date(str), now = new Date();
    var diffDays = Math.floor((now - d) / 86400000);
    if (diffDays === 0) {
      var diffMin = Math.floor((now - d) / 60000);
      if (diffMin < 1)  return 'Just now';
      if (diffMin < 60) return diffMin + 'm ago';
      return Math.floor(diffMin / 60) + 'h ago';
    }
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7)   return diffDays + ' days ago';
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(function() { toast.classList.remove('show'); }, 2500);
  }

  function render(notifs) {
    list.innerHTML = '';
    if (!notifs || !notifs.length) {
      list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>No notifications yet.</div>';
      return;
    }
    notifs.forEach(function(n) {
      var isNew  = !n.is_read;
      var link   = (n.type && n.type.includes('match')) ? 'StudentsReport.php?filter=matched' : 'ClaimHistory.php';
      var imgSrc = n.image_url || 'images/notif_placeholder.jpg';
      var card   = document.createElement('div');
      card.className  = 'notif-card' + (isNew ? ' is-new' : '');
      card.dataset.id = n.id;
      card.innerHTML =
        '<div class="notif-card-thumb">'
        + '<img src="' + imgSrc + '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">'
        + '<div class="notif-card-thumb-placeholder" style="display:none;"><i class="fa-solid fa-box-open"></i></div>'
        + '</div>'
        + '<div class="notif-card-body">'
        +   '<div class="notif-card-top">'
        +     '<span class="notif-card-title">' + (n.title || 'Notification') + '</span>'
        +     (isNew ? '<span class="notif-new-pill">New</span>' : '')
        +     '<span class="notif-card-date">' + fmtDate(n.created_at) + '</span>'
        +   '</div>'
        +   '<div class="notif-card-message">'
        +     (n.message || '')
        +     ' <a href="' + link + '" class="notif-detail-link">View Details</a>'
        +   '</div>'
        + '</div>';
      list.appendChild(card);
    });

    /* Auto-mark unread as read after 2 s */
    setTimeout(function() {
      list.querySelectorAll('.notif-card.is-new').forEach(function(card) {
        var id = card.dataset.id;
        if (!id) return;
        fetch('/LOSTANDFOUND/api/notifications/' + id + '/read', { method: 'PUT', credentials: 'include' })
          .then(function(r) { return r.json(); })
          .then(function(j) {
            if (j.ok) {
              card.classList.remove('is-new');
              var pill = card.querySelector('.notif-new-pill');
              if (pill) pill.remove();
            }
          });
      });
      /* Clear bell badge */
      var badge = document.getElementById('notifBadge') || (document.getElementById('notifTrigger') || {}).querySelector && document.getElementById('notifTrigger').querySelector('.notif-badge');
      if (badge) badge.remove();
    }, 2000);
  }

  /* ── Mark All as Read ── */
  if (markAllBtn) {
    markAllBtn.addEventListener('click', function() {
      var unread = _allNotifs.filter(function(n) { return !n.is_read; });
      if (!unread.length) { showToast('All notifications are already read.'); return; }
      markAllBtn.disabled = true;
      var done = 0;
      unread.forEach(function(n) {
        fetch('/LOSTANDFOUND/api/notifications/' + n.id + '/read', { method: 'PUT', credentials: 'include' })
          .then(function(r) { return r.json(); })
          .then(function(j) {
            if (j.ok) n.is_read = true;
            done++;
            if (done === unread.length) {
              render(_allNotifs);
              markAllBtn.disabled = false;
              var badge = document.getElementById('notifTrigger') && document.getElementById('notifTrigger').querySelector('.notif-badge');
              if (badge) badge.remove();
              showToast('All notifications marked as read.');
            }
          })
          .catch(function() { done++; if (done === unread.length) markAllBtn.disabled = false; });
      });
    });
  }

  /* ── Fetch & init ── */
  document.addEventListener('DOMContentLoaded', function() {
    fetch('/LOSTANDFOUND/api/notifications', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        if (j.ok) { _allNotifs = j.data || []; render(_allNotifs); }
        else list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>Could not load notifications.</div>';
      })
      .catch(function() {
        list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>Could not load notifications.</div>';
      });
  });
})();
</script>

</body>
</html>