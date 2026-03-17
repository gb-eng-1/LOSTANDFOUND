<?php
/**
 * AdminNotifications.php — Full notification history for admins.
 * Shows all event types: lost reports, expirations, matches, claims, etc.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$adminName = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - UB Lost and Found Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
  <link rel="stylesheet" href="AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <style>
    /* ── Force FA icons visible ── */
    .fa-solid,.fa-regular,.fa-brands { display: inline-block !important; }

    /* ── Sidebar mobile fix ── */
    @media (max-width: 900px) {
      .sidebar { min-height: 0 !important; height: auto !important; }
      .nav-menu { flex: none !important; }
    }

    /* ── Page content wrapper ── */
    .notif-page-wrap {
      padding: 28px 32px 48px;
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
    }

    /* ── Page header row ── */
    .notif-page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 22px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .notif-page-title {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      margin: 0;
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

    /* ── Filter tabs ── */
    .notif-filter-row {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }
    .notif-filter-btn {
      padding: 6px 14px;
      border-radius: 20px;
      border: 1px solid #e5e7eb;
      background: #fff;
      font-family: Poppins, sans-serif;
      font-size: 12px;
      font-weight: 500;
      color: #6b7280;
      cursor: pointer;
      transition: all 0.15s;
    }
    .notif-filter-btn:hover { border-color: #8b0000; color: #8b0000; }
    .notif-filter-btn.active {
      background: #8b0000;
      border-color: #8b0000;
      color: #fff;
      font-weight: 600;
    }

    /* ── Notification card list ── */
    .notif-card-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 900px;
    }
    .notif-card {
      display: flex;
      align-items: flex-start;
      gap: 16px;
      background: #ffffff;
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border: 1px solid #e5e7eb;
      border-left: 4px solid transparent;
      transition: box-shadow 0.15s, border-color 0.15s;
      cursor: default;
    }
    .notif-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .notif-card.is-new { border-left-color: #8b0000; background: #fffcfc; }

    /* Type icon bubble */
    .notif-card-icon {
      flex-shrink: 0;
      width: 46px;
      height: 46px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }
    .notif-icon-report   { background: #eff6ff; color: #1d4ed8; }
    .notif-icon-match    { background: #f0fdf4; color: #16a34a; }
    .notif-icon-claim    { background: #fdf4ff; color: #7e22ce; }
    .notif-icon-expiry   { background: #fffbeb; color: #b45309; }
    .notif-icon-default  { background: #f3f4f6; color: #6b7280; }

    /* Content */
    .notif-card-body { flex: 1; min-width: 0; }
    .notif-card-top {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 4px;
      flex-wrap: wrap;
    }
    .notif-card-title { font-size: 14px; font-weight: 700; color: #111827; }
    .notif-new-pill {
      font-size: 10px;
      font-weight: 700;
      color: #fff;
      background: #8b0000;
      border-radius: 20px;
      padding: 2px 8px;
      white-space: nowrap;
    }
    .notif-type-pill {
      font-size: 10px;
      font-weight: 600;
      border-radius: 20px;
      padding: 2px 8px;
      white-space: nowrap;
    }
    .notif-card-date {
      font-size: 11px;
      color: #9ca3af;
      margin-left: auto;
      white-space: nowrap;
      align-self: flex-start;
    }
    .notif-card-message {
      font-size: 13px;
      color: #374151;
      line-height: 1.55;
    }
    .notif-detail-link {
      color: #8b0000;
      font-weight: 600;
      text-decoration: none;
      margin-left: 6px;
      font-size: 12px;
    }
    .notif-detail-link:hover { text-decoration: underline; }

    /* States */
    .notif-loading, .notif-empty {
      text-align: center;
      padding: 60px 24px;
      font-size: 15px;
      color: #9ca3af;
    }
    .notif-empty i { font-size: 40px; margin-bottom: 14px; display: block; color: #d1d5db; }

    /* Toast */
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
  </style>
</head>
<body>
<div class="layout">

  <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"></div>
      <div class="sidebar-title">
        <span class="sidebar-title-line1">University of</span>
        <span class="sidebar-title-line2">Batangas</span>
      </div>
    </div>
    <nav>
      <ul class="nav-menu">
        <li><a class="nav-item" href="AdminDashboard.php"><div class="nav-item-icon"><i class="fa-solid fa-house"></i></div><div class="nav-item-label">Dashboard</div></a></li>
        <li><a class="nav-item" href="FoundAdmin.php"><div class="nav-item-icon"><i class="fa-solid fa-folder"></i></div><div class="nav-item-label">Found</div></a></li>
        <li><a class="nav-item" href="AdminReports.php"><div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div><div class="nav-item-label">Reports</div></a></li>
        <li><a class="nav-item" href="ItemMatchedAdmin.php"><div class="nav-item-icon"><i class="fa-regular fa-circle-check"></i></div><div class="nav-item-label">Matching</div></a></li>
        <li><a class="nav-item" href="HistoryAdmin.php"><div class="nav-item-icon"><i class="fa-regular fa-calendar"></i></div><div class="nav-item-label">History</div></a></li>
      </ul>
    </nav>
  </aside>

  <!-- ── Main ─────────────────────────────────────────────────────────── -->
  <main class="main">

    <!-- Topbar -->
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-left">
        <form class="search-form" action="FoundAdmin.php" method="get">
          <input id="adminSearchInput" name="q" type="text" class="search-input" placeholder="Search" autocomplete="off">
          <div id="searchDropdown" class="search-dropdown"></div>
          <button id="adminSearchClear" class="search-clear" type="button" title="Clear" aria-label="Clear search">
            <i class="fa-solid fa-xmark"></i>
          </button>
          <button class="search-submit" type="submit" title="Search" aria-label="Search">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </form>
      </div>
      <div class="topbar-right">
        <?php include __DIR__ . '/includes/notifications_dropdown.php'; ?>
        <div class="admin-dropdown" id="adminDropdown">
          <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger"
                  aria-expanded="false" aria-haspopup="true" aria-label="Admin menu">
            <i class="fa-regular fa-user"></i>
            <span class="admin-name"><?php echo htmlspecialchars($adminName); ?></span>
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

    <!-- Page body -->
    <div class="notif-page-wrap">

      <div class="notif-page-header">
        <h1 class="notif-page-title">Notifications</h1>
        <button type="button" class="notif-mark-all-btn" id="markAllBtn">
          <i class="fa-solid fa-check-double" style="margin-right:6px;"></i>Mark All as Read
        </button>
      </div>

      <!-- Filter tabs -->
      <div class="notif-filter-row" id="filterRow">
        <button class="notif-filter-btn active" data-filter="all">All</button>
        <button class="notif-filter-btn" data-filter="report">
          <i class="fa-regular fa-file-lines" style="margin-right:4px;"></i>Reports
        </button>
        <button class="notif-filter-btn" data-filter="match">
          <i class="fa-regular fa-circle-check" style="margin-right:4px;"></i>Matches
        </button>
        <button class="notif-filter-btn" data-filter="claim">
          <i class="fa-solid fa-hand-holding" style="margin-right:4px;"></i>Claims
        </button>
        <button class="notif-filter-btn" data-filter="expiry">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right:4px;"></i>Expirations
        </button>
      </div>

      <div id="notifCardList" class="notif-card-list">
        <div class="notif-loading">
          <i class="fa-solid fa-spinner fa-spin"></i>&nbsp; Loading…
        </div>
      </div>

    </div><!-- /.notif-page-wrap -->
  </main>
</div><!-- /.layout -->

<!-- Toast -->
<div class="notif-toast" id="notifToast"></div>

<!-- Admin dropdown JS -->
<script>
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
</script>

<!-- Notifications page logic -->
<script>
(function () {
  var list       = document.getElementById('notifCardList');
  var markAllBtn = document.getElementById('markAllBtn');
  var filterRow  = document.getElementById('filterRow');
  var toast      = document.getElementById('notifToast');

  var _allNotifs = [];
  var _filter    = 'all';

  /* ── Helpers ── */
  function fmtDate(str) {
    if (!str) return '';
    var d = new Date(str), now = new Date();
    var diffDays = Math.floor((now - d) / 86400000);
    if (diffDays === 0) {
      var diffMin = Math.floor((now - d) / 60000);
      if (diffMin < 1) return 'Just now';
      if (diffMin < 60) return diffMin + 'm ago';
      return Math.floor(diffMin / 60) + 'h ago';
    }
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7)  return diffDays + ' days ago';
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }

  function getTypeGroup(type) {
    type = (type || '').toLowerCase();
    if (type.includes('report') || type.includes('lost')) return 'report';
    if (type.includes('match'))                           return 'match';
    if (type.includes('claim'))                           return 'claim';
    if (type.includes('disposal') || type.includes('expir') || type.includes('retention')) return 'expiry';
    return 'other';
  }

  function getLink(type) {
    var g = getTypeGroup(type);
    if (g === 'report') return 'AdminReports.php';
    if (g === 'match')  return 'ItemMatchedAdmin.php';
    if (g === 'claim')  return 'HistoryAdmin.php';
    if (g === 'expiry') return 'FoundAdmin.php';
    return 'AdminDashboard.php';
  }

  function getIconClass(type) {
    var g = getTypeGroup(type);
    if (g === 'report') return { wrap: 'notif-icon-report', icon: 'fa-regular fa-file-lines' };
    if (g === 'match')  return { wrap: 'notif-icon-match',  icon: 'fa-regular fa-circle-check' };
    if (g === 'claim')  return { wrap: 'notif-icon-claim',  icon: 'fa-solid fa-hand-holding' };
    if (g === 'expiry') return { wrap: 'notif-icon-expiry', icon: 'fa-solid fa-triangle-exclamation' };
    return { wrap: 'notif-icon-default', icon: 'fa-regular fa-bell' };
  }

  function getTypePillHtml(type) {
    var g = getTypeGroup(type);
    var styles = {
      report: 'background:#dbeafe;color:#1e40af;',
      match:  'background:#dcfce7;color:#166534;',
      claim:  'background:#f3e8ff;color:#6b21a8;',
      expiry: 'background:#fef3c7;color:#92400e;',
      other:  'background:#f3f4f6;color:#374151;'
    };
    var labels = { report: 'Report', match: 'Match', claim: 'Claim', expiry: 'Expiry', other: 'Info' };
    return '<span class="notif-type-pill" style="' + (styles[g] || styles.other) + '">'
         + (labels[g] || 'Info') + '</span>';
  }

  /* ── Render ── */
  function render(notifs) {
    list.innerHTML = '';
    var filtered = _filter === 'all'
      ? notifs
      : notifs.filter(function (n) { return getTypeGroup(n.type) === _filter; });

    if (!filtered.length) {
      list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>'
        + (_filter === 'all' ? 'No notifications yet.' : 'No notifications in this category.')
        + '</div>';
      return;
    }

    filtered.forEach(function (n) {
      var isNew    = !n.is_read;
      var link     = getLink(n.type);
      var ic       = getIconClass(n.type);
      var card     = document.createElement('div');
      card.className  = 'notif-card' + (isNew ? ' is-new' : '');
      card.dataset.id = n.id;
      card.dataset.group = getTypeGroup(n.type);
      card.innerHTML =
        '<div class="notif-card-icon ' + ic.wrap + '">'
        +   '<i class="' + ic.icon + '"></i>'
        + '</div>'
        + '<div class="notif-card-body">'
        +   '<div class="notif-card-top">'
        +     '<span class="notif-card-title">' + esc(n.title || 'Notification') + '</span>'
        +     getTypePillHtml(n.type)
        +     (isNew ? '<span class="notif-new-pill">New</span>' : '')
        +     '<span class="notif-card-date">' + fmtDate(n.created_at) + '</span>'
        +   '</div>'
        +   '<div class="notif-card-message">'
        +     esc(n.message || '')
        +     ' <a href="' + link + '" class="notif-detail-link">View Details &rarr;</a>'
        +   '</div>'
        + '</div>';
      list.appendChild(card);
    });

    /* Mark unread as read after 2 s */
    setTimeout(function () {
      list.querySelectorAll('.notif-card.is-new').forEach(function (card) {
        var id = card.dataset.id;
        if (!id) return;
        fetch('/LOSTANDFOUND/api/notifications/' + id + '/read', {
          method: 'PUT', credentials: 'include'
        })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.ok) {
              card.classList.remove('is-new');
              var pill = card.querySelector('.notif-new-pill');
              if (pill) pill.remove();
            }
          })
          .catch(function () {});
      });
      /* Reset bell badge to 0 */
      var badge = document.getElementById('notifBadge');
      if (badge) badge.remove();
    }, 2000);
  }

  /* ── Fetch ── */
  function loadNotifications() {
    fetch('/LOSTANDFOUND/api/notifications', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          _allNotifs = j.data || [];
          render(_allNotifs);
        } else {
          list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>Could not load notifications.</div>';
        }
      })
      .catch(function () {
        list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>Could not load notifications.</div>';
      });
  }

  /* ── Filter tabs ── */
  if (filterRow) {
    filterRow.addEventListener('click', function (e) {
      var btn = e.target.closest('.notif-filter-btn');
      if (!btn) return;
      filterRow.querySelectorAll('.notif-filter-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      _filter = btn.getAttribute('data-filter') || 'all';
      render(_allNotifs);
    });
  }

  /* ── Mark All as Read ── */
  function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 2500);
  }

  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      var unread = _allNotifs.filter(function (n) { return !n.is_read; });
      if (!unread.length) { showToast('All notifications are already read.'); return; }
      markAllBtn.disabled = true;
      var done = 0;
      unread.forEach(function (n) {
        fetch('/LOSTANDFOUND/api/notifications/' + n.id + '/read', {
          method: 'PUT', credentials: 'include'
        })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.ok) n.is_read = true;
            done++;
            if (done === unread.length) {
              render(_allNotifs);
              markAllBtn.disabled = false;
              var badge = document.getElementById('notifBadge');
              if (badge) badge.remove();
              showToast('All notifications marked as read.');
            }
          })
          .catch(function () { done++; if (done === unread.length) markAllBtn.disabled = false; });
      });
    });
  }

  /* ── Init ── */
  document.addEventListener('DOMContentLoaded', loadNotifications);
})();
</script>

<script src="NotificationsDropdown.js"></script>
</body>
</html>
