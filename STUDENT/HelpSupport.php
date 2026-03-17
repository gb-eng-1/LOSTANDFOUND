<?php
/**
 * Help and Support - UB Lost and Found System (Student POV)
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$studentEmail = $_SESSION['student_email'] ?? '';
$studentName  = $_SESSION['student_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help and Support - UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <!-- FA JS renderer: injects inline SVGs, bypasses CORS font-face issues -->
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
  <style>.fa-solid,.fa-regular,.fa-brands{display:inline-block!important;font-style:normal!important;}</style>
  <link rel="stylesheet" href="ItemDetailsModal.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../ADMIN/AdminDashboard.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="HelpSupport.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
  <style>
    .nav-item.active .nav-item-icon,.nav-item.active .nav-item-icon i,.nav-item.active .nav-item-label{color:#ffffff!important}
    .nav-menu .nav-item:not(.active) .nav-item-icon,.nav-menu .nav-item:not(.active) .nav-item-icon i,.nav-menu .nav-item:not(.active) .nav-item-label{color:#8b0000!important}
    .main .topbar{flex-shrink:0;z-index:10}

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
    .hs-card.highlighted {
        transition: box-shadow 0.3s ease-in-out;
        box-shadow: 0 0 0 3px #8b0000;
    }
  </style>
</head>
<body>
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
      <li><a class="nav-item" href="StudentDashboard.php"><div class="nav-item-icon"><i class="fa-solid fa-house"></i></div><div class="nav-item-label">Dashboard</div></a></li>
      <li><a class="nav-item" href="StudentsReport.php"><div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div><div class="nav-item-label">My Reports</div></a></li>
      <li><a class="nav-item" href="ClaimHistory.php"><div class="nav-item-icon"><i class="fa-regular fa-calendar"></i></div><div class="nav-item-label">Claim History</div></a></li>
      <li><a class="nav-item active" href="HelpSupport.php"><div class="nav-item-icon"><i class="fa-solid fa-circle-question"></i></div><div class="nav-item-label">Help and Support</div></a></li>
    </ul>
  </aside>

  <main class="main">
    <!-- Topbar -->
    <div class="topbar topbar-maroon">
      <div class="topbar-search-wrap topbar-search-left">
        <form class="search-form" id="searchForm" autocomplete="off" style="position: relative;">
          <input id="adminSearchInput" type="text" class="search-input" placeholder="Search items by name or barcode…" autocomplete="off" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
          <div id="searchDropdown" class="search-dropdown"></div>
          <button id="adminSearchClear" class="search-clear" type="button" title="Clear"><i class="fa-solid fa-xmark"></i></button>
          <button class="search-submit" type="submit" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>
      <div class="topbar-right">
        <?php require_once __DIR__ . '/includes/notifications_dropdown.php'; ?>
        <div class="admin-dropdown" id="adminDropdown">
          <button type="button" class="admin-link admin-dropdown-trigger topbar-admin-trigger" aria-expanded="false" aria-haspopup="true">
            <i class="fa-regular fa-user"></i>
            <span class="admin-name"><?php echo htmlspecialchars($studentName ?: $studentEmail); ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size:11px"></i>
          </button>
          <div class="admin-dropdown-menu" role="menu">
            <a href="logout.php" role="menuitem" class="admin-dropdown-item"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="main-content-wrap hs-wrap">
      <h1 class="hs-page-title">Help and Support</h1>

      <div class="hs-grid">
        <!-- LEFT COLUMN -->
        <div class="hs-left">

          <!-- How to Report Lost Item -->
          <div class="hs-card">
            <h2 class="hs-card-title">How to Report Lost Item</h2>
            <ol class="hs-steps">
              <li class="hs-step">
                <div class="hs-step-icon"><i class="fa-regular fa-user"></i></div>
                <div class="hs-step-body">
                  <p><strong>Step 1: <span class="hs-highlight">Log in to the Dashboard.</span></strong> Access the system using your official student credentials.</p>
                </div>
              </li>
              <li class="hs-step">
                <div class="hs-step-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                <div class="hs-step-body">
                  <p><strong>Step 2: <span class="hs-highlight">Select "Report Lost Item".</span></strong> Click this button to open the reporting form and fill out item details.</p>
                </div>
              </li>
              <li class="hs-step">
                <div class="hs-step-icon"><i class="fa-solid fa-upload"></i></div>
                <div class="hs-step-body">
                  <p><strong>Step 3: <span class="hs-highlight">Upload a Photo.</span></strong> Attach a picture of your missing item or a similar reference image to help the system's matching process.</p>
                </div>
              </li>
              <li class="hs-step">
                <div class="hs-step-icon"><i class="fa-regular fa-file-lines"></i></div>
                <div class="hs-step-body">
                  <p><strong>Step 4: <span class="hs-highlight">Submit the Report.</span></strong> After verifying all details, click Submit.</p>
                </div>
              </li>
              <li class="hs-step">
                <div class="hs-step-icon"><i class="fa-solid fa-hand"></i></div>
                <div class="hs-step-body">
                  <p><strong>Step 5: <span class="hs-highlight">Receive a Ticket ID.</span></strong> The system will generate a unique Ticket ID (e.g., RE26-001), which serves as your ticket for claiming the item at the Security Office.</p>
                </div>
              </li>
            </ol>
          </div>

          <!-- What to do with lost ATMs -->
          <div class="hs-card hs-card-atm">
            <h2 class="hs-card-title">What to do with lost ATMs?</h2>
            <p class="hs-atm-label">Immediate Steps to Take:</p>
            <ul class="hs-atm-list">
              <li>Check your surroundings — it might be misplaced in your bag, wallet, or car.</li>
              <li>Contact your bank immediately via:
                <ul class="hs-atm-sublist">
                  <li>Helpline: Call your bank's 24/7 customer service number.</li>
                  <li>Mobile app or online banking: Use the app or website to block the card instantly.</li>
                </ul>
              </li>
              <li>Report it to the Security Office so it can be logged.</li>
              <li>Request a card replacement from your bank branch.</li>
            </ul>
          </div>

        </div><!-- /.hs-left -->

        <!-- RIGHT COLUMN -->
        <div class="hs-right">

          <!-- Map + Office Info -->
          <div class="hs-card hs-card-map">
            <div class="hs-map-embed">
              <iframe
                title="University of Batangas Gate - Security Office"
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d964.4914155049485!2d121.04916297083327!3d13.785499699999998!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd0be70b6a19cb%3A0xd25b2b6c3b2fcf5c!2sUniversity%20of%20Batangas!5e0!3m2!1sen!2sph!4v1708900000000!5m2!1sen!2sph"
                width="100%"
                height="200"
                style="border:0; display:block;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
              </iframe>
            </div>
            <div class="hs-office-info">
              <div class="hs-office-row">
                <span class="hs-office-label">Building:</span>
                <span class="hs-office-value">Security Office, Ground Floor, Gate</span>
              </div>
              <div class="hs-office-row">
                <span class="hs-office-label">Hours:</span>
                <div class="hs-office-value">
                  <span>Monday–Friday, 9:00 AM – 5:00 PM</span>
                  <span>Saturday, 9:00 AM – 12:00 PM</span>
                </div>
              </div>
              <div class="hs-office-open">
                <span class="hs-open-dot"></span>
                <span class="hs-open-text">Open Now</span>
                <a href="https://maps.google.com/?q=University+of+Batangas" target="_blank" rel="noopener noreferrer" class="hs-directions-btn">Get Directions</a>
              </div>
            </div>
          </div>

          <!-- What to Bring to Claim -->
          <div class="hs-card hs-card-bring">
            <h2 class="hs-card-title">What to Bring to Claim</h2>
            <div class="hs-bring-row">
              <div class="hs-bring-item">
                <span class="hs-bring-icon"><i class="fa-regular fa-square"></i></span>
                <span class="hs-bring-label">Student ID</span>
              </div>
              <div class="hs-bring-item">
                <span class="hs-bring-icon"><i class="fa-regular fa-square"></i></span>
                <span class="hs-bring-label">Ticket ID</span>
              </div>
            </div>
          </div>

          <!-- Call & Email -->
          <div class="hs-card hs-card-contact">
            <div class="hs-contact-item">
              <div class="hs-contact-icon"><i class="fa-solid fa-phone"></i></div>
              <div class="hs-contact-body">
                <span class="hs-contact-label">Call Office</span>
                <a href="tel:0737866675" class="hs-contact-value">073-7866-675</a>
              </div>
            </div>
            <div class="hs-contact-divider"></div>
            <div class="hs-contact-item">
              <div class="hs-contact-icon"><i class="fa-regular fa-envelope"></i></div>
              <div class="hs-contact-body">
                <span class="hs-contact-label">Email Support</span>
                <a href="mailto:ssd@ub.edu.ph" class="hs-contact-value">ssd@ub.edu.ph</a>
              </div>
            </div>
          </div>

        </div><!-- /.hs-right -->
      </div><!-- /.hs-grid -->
    </div><!-- /.hs-wrap -->
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
  var form     = document.getElementById('searchForm');
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
<script src="ItemDetailsModal.js?v=<?php echo time(); ?>"></script>

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