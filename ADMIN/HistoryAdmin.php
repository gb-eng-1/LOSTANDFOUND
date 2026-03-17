<?php
// HistoryAdmin.php - Claimed Items (History)
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

// All Items tab: non-REF- items with status Claimed (physical found items)
$allClaimed = array_values(array_filter(get_items($pdo, 'Claimed'), function($it){
    return strpos($it['id'] ?? '', 'REF-') !== 0;
}));

// Guest Items tab: admin-encoded reports (REF- with no student web account) that are Claimed/Resolved
try {
    $gs = $pdo->query(
        "SELECT id, user_id, item_type, color, brand, found_at, found_by,
                date_encoded, date_lost, item_description, storage_location,
                image_data, status, created_at, updated_at
         FROM items
         WHERE id LIKE 'REF-%'
           AND (user_id IS NULL OR user_id = '')
           AND status IN ('Claimed','Resolved')
         ORDER BY updated_at DESC"
    );
    $guestClaimed = [];
    while ($r = $gs->fetch(PDO::FETCH_ASSOC)) {
        $desc = $r['item_description'] ?? '';
        $get  = function($k) use ($desc){ preg_match('/^'.preg_quote($k,'/').':\s*(.+?)(?:\n|$)/m',$desc,$m); return isset($m[1])?trim($m[1]):''; };
        $guestClaimed[] = [
            'id'             => $r['id'],
            'item_type'      => $r['item_type'],
            'department'     => $get('Department'),
            'student_number' => $get('Student Number') ?: $get('Student ID'),
            'contact'        => $get('Contact'),
            'updated_at'     => $r['updated_at'],
            'created_at'     => $r['created_at'],
            'imageDataUrl'   => $r['image_data'],
        ];
    }
} catch (PDOException $e) { $guestClaimed = []; }

$itemCategories = require dirname(__DIR__) . '/config/categories.php';
$adminName = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UB Lost and Found System - History</title>
    <link rel="stylesheet" href="AdminDashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="ItemMatchedAdmin.css">
    <link rel="stylesheet" href="FoundAdmin.css">
    <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
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

/* ── Item Details Modal (History) ──────────────────────────── */
.hdm-overlay {
    display: none; position: fixed; inset: 0; z-index: 1500;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,0.48);
}
.hdm-overlay.open { display: flex; }
.hdm-modal {
    background: #fff; border-radius: 12px;
    width: min(720px, 97vw); max-height: 92vh; overflow-y: auto;
    box-shadow: 0 16px 48px rgba(0,0,0,0.22);
    display: flex; flex-direction: column;
}
.hdm-header {
    background: #8b0000; border-radius: 12px 12px 0 0;
    padding: 14px 20px; display: flex;
    align-items: center; justify-content: space-between; flex-shrink: 0;
}
.hdm-header-title { color: #fff; font-size: 16px; font-weight: 700; margin: 0; }
.hdm-close-btn {
    background: none; border: none; color: #fff; font-size: 18px;
    cursor: pointer; padding: 2px 6px; border-radius: 4px;
    opacity: 0.85; transition: opacity 0.15s; line-height: 1;
}
.hdm-close-btn:hover { opacity: 1; }
.hdm-body { display: flex; flex: 1; min-height: 0; }
.hdm-left {
    width: 40%; flex-shrink: 0;
    display: flex; flex-direction: column; align-items: center;
    padding: 24px 18px 20px;
    border-right: 1px solid #e5e7eb; background: #fafafa;
}
.hdm-photo {
    width: 160px; height: 120px; object-fit: cover;
    border-radius: 8px; border: 1px solid #e0e0e0;
}
.hdm-photo-placeholder {
    width: 160px; height: 120px; background: #f3f4f6;
    border-radius: 8px; border: 1px solid #e0e0e0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 6px; color: #9ca3af; font-size: 11px;
}
.hdm-barcode {
    margin: 10px 0 0; font-size: 13px; color: #374151;
    font-weight: 600; text-align: center;
}
.hdm-claimant-section {
    width: 100%; margin-top: 18px;
}
.hdm-claimant-title {
    font-size: 13px; font-weight: 700; color: #8b0000;
    margin: 0 0 12px; text-align: center;
    padding-bottom: 6px; border-bottom: 1px solid #e5e7eb;
}
.hdm-claimant-field { margin-bottom: 10px; }
.hdm-claimant-label {
    display: block; font-size: 11px; color: #6b7280;
    font-weight: 500; margin-bottom: 4px;
}
.hdm-claimant-box {
    width: 100%; box-sizing: border-box;
    padding: 7px 10px; border: 1px solid #d1d5db;
    border-radius: 6px; background: #fff;
    font-size: 13px; font-weight: 600; color: #111827;
    min-height: 34px;
}
.hdm-right {
    flex: 1; padding: 24px 26px 20px;
    display: flex; flex-direction: column;
}
.hdm-section-title {
    font-size: 15px; font-weight: 700; color: #111827;
    margin: 0 0 14px; text-align: center;
}
.hdm-info-row {
    display: flex; align-items: baseline; gap: 8px;
    padding: 7px 0; border-bottom: 1px solid #f3f4f6;
}
.hdm-info-row:last-child { border-bottom: none; }
.hdm-info-label { font-size: 13px; color: #6b7280; min-width: 120px; flex-shrink: 0; }
.hdm-info-value { font-size: 13px; font-weight: 700; color: #111827; text-align: right; flex: 1; }
.hdm-footer {
    display: flex; justify-content: flex-end;
    padding: 14px 24px 18px; border-top: 1px solid #e5e7eb; flex-shrink: 0;
}
.hdm-btn-close {
    padding: 9px 24px; border: 1px solid #d1d5db; border-radius: 7px;
    background: #fff; color: #374151; font-family: Poppins, sans-serif;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.hdm-btn-close:hover { background: #f3f4f6; }
@media (max-width: 560px) {
    .hdm-body { flex-direction: column; }
    .hdm-left { width: 100%; border-right: none; border-bottom: 1px solid #e5e7eb; }
}
</style>
</head>
<body class="history-page">
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
            <li><a class="nav-item" href="AdminDashboard.php"><div class="nav-item-icon"><i class="fa-solid fa-house"></i></div><div class="nav-item-label">Dashboard</div></a></li>
            <li><a class="nav-item" href="FoundAdmin.php"><div class="nav-item-icon"><i class="fa-solid fa-folder"></i></div><div class="nav-item-label">Found</div></a></li>
            <li><a class="nav-item" href="AdminReports.php"><div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div><div class="nav-item-label">Reports</div></a></li>
            <li><a class="nav-item" href="ItemMatchedAdmin.php"><div class="nav-item-icon"><i class="fa-solid fa-circle-check"></i></div><div class="nav-item-label">Matching</div></a></li>
            <li><a class="nav-item active" href="HistoryAdmin.php"><div class="nav-item-icon"><i class="fa-solid fa-clock-rotate-left"></i></div><div class="nav-item-label">History</div></a></li>
        </ul>
    </aside>

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
                    <h2 class="page-title">History</h2>

                    <!-- Tab bar -->
                    <div class="found-tabs-actions-row">
                        <div class="found-tabs">
                            <span class="found-tab-text found-tab-active" id="histAllTab"><i class="fa-solid fa-list" style="margin-right:5px;font-size:12px;"></i>All Items</span>
                            <span class="found-tab-text" id="histGuestTab"><i class="fa-solid fa-user-group" style="margin-right:5px;font-size:12px;"></i>Guest Items</span>
                        </div>
                        <select id="historyCategoryFilter" class="found-filter-select" aria-label="Filter by category">
                            <option value="">All Categories</option>
                            <?php foreach ($itemCategories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ── ALL ITEMS TABLE ─────────────────────────────────── -->
                    <div id="histAllSection">
                      <div class="inventory-card matched-reports-card">
                        <div class="inventory-title">Claimed Items</div>
                        <div class="table-wrapper">
                          <table class="matched-reports-table" id="historyTable">
                            <thead><tr>
                                <th>Barcode ID</th>
                                <th>Category</th>
                                <th>Found At</th>
                                <th>Date Found</th>
                                <th>Date Claimed</th>
                                <th>Storage Location</th>
                                <th>Timestamp</th>
                                <th>Action</th>
                            </tr></thead>
                            <tbody>
                            <?php
                            if (empty($allClaimed)) {
                                echo '<tr><td colspan="8" class="table-empty">No claimed items yet.</td></tr>';
                            } else {
                                foreach ($allClaimed as $it) {
                                    $bid       = htmlspecialchars($it['id'] ?? '');
                                    $cat       = htmlspecialchars($it['item_type'] ?? '');
                                    $foundAt   = htmlspecialchars($it['found_at'] ?? '');
                                    $dateFnd   = htmlspecialchars($it['dateEncoded'] ?? '');
                                    $dateClaim = htmlspecialchars($it['updated_at'] ?? '');
                                    $storage   = htmlspecialchars($it['storage_location'] ?? '');
                                    $ts        = htmlspecialchars($it['created_at'] ?? '');
                                    $color     = htmlspecialchars($it['color'] ?? '');
                                    $brand     = htmlspecialchars($it['brand'] ?? '');
                                    $foundBy   = htmlspecialchars($it['found_by'] ?? '');
                                    $img       = isset($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'],ENT_QUOTES,'UTF-8') : '';

                                    // Parse item_description for modal fields
                                    $rawDesc = $it['item_description'] ?? '';
                                    $itemName = '';
                                    if (preg_match('/^Item(?:\s+Type)?:\s*(.+?)(?:\n|$)/mi', $rawDesc, $dm)) $itemName = trim($dm[1]);
                                    $claimantName = $claimContact = $dateAccomp = '';
                                    $claimPos = stripos($rawDesc, '--- Claim Record ---');
                                    if ($claimPos !== false) {
                                        $cs = substr($rawDesc, $claimPos);
                                        if (preg_match('/^Claimed\s+By:\s*(.+?)(?:\n|$)/mi', $cs, $dm)) $claimantName = trim($dm[1]);
                                        if (preg_match('/^Contact:\s*(.+?)(?:\n|$)/mi', $cs, $dm)) $claimContact = trim($dm[1]);
                                        if (preg_match('/^Date\s+Accomplished:\s*(.+?)(?:\n|$)/mi', $cs, $dm)) $dateAccomp = trim($dm[1]);
                                    }
                                    // Clean description: remove claim record + known metadata prefixes
                                    $cleanDesc = $claimPos !== false ? substr($rawDesc, 0, $claimPos) : $rawDesc;
                                    $cleanDesc = preg_replace('/^(Item(?:\s+Type)?|Student Number|Contact|Department):\s*.+\n?/mi', '', $cleanDesc);
                                    $cleanDesc = trim($cleanDesc);

                                    $da = ' data-id="' . $bid . '" data-category="' . $cat . '" data-color="' . $color . '" data-brand="' . $brand . '" data-found-by="' . $foundBy . '" data-date-encoded="' . $dateFnd . '" data-storage-location="' . $storage . '" data-found-at="' . htmlspecialchars($it['found_at'] ?? '') . '" data-item-name="' . htmlspecialchars($itemName) . '" data-claimant-name="' . htmlspecialchars($claimantName) . '" data-contact="' . htmlspecialchars($claimContact) . '" data-date-accomplished="' . htmlspecialchars($dateAccomp) . '" data-item-description="' . htmlspecialchars($cleanDesc) . '"';
                                    if ($img) $da .= ' data-image="' . $img . '"';
                                    echo '<tr class="matched-data-row"' . $da . '>';
                                    echo '<td>' . $bid . '</td>';
                                    echo '<td>' . $cat . '</td>';
                                    echo '<td>' . $foundAt . '</td>';
                                    echo '<td>' . $dateFnd . '</td>';
                                    echo '<td>' . $dateClaim . '</td>';
                                    echo '<td>' . $storage . '</td>';
                                    echo '<td>' . $ts . '</td>';
                                    echo '<td class="found-action-cell"><button type="button" class="found-btn-view">View</button></td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div><!-- /#histAllSection -->

                    <!-- ── GUEST ITEMS TABLE ──────────────────────────────── -->
                    <div id="histGuestSection" style="display:none;">
                      <div class="inventory-card matched-reports-card">
                        <div class="inventory-title">Guest Reports (Claimed)</div>
                        <div class="table-wrapper">
                          <table class="matched-reports-table" id="historyGuestTable">
                            <thead><tr>
                                <th>Ticket ID</th>
                                <th>Category</th>
                                <th>Department</th>
                                <th>ID</th>
                                <th>Contact Number</th>
                                <th>Date Claimed</th>
                                <th>Action</th>
                            </tr></thead>
                            <tbody>
                            <?php
                            if (empty($guestClaimed)) {
                                echo '<tr><td colspan="7" class="table-empty">No claimed guest reports yet.</td></tr>';
                            } else {
                                foreach ($guestClaimed as $it) {
                                    $tid  = htmlspecialchars($it['id'] ?? '');
                                    $cat  = htmlspecialchars($it['item_type'] ?? '');
                                    $dept = htmlspecialchars($it['department'] ?? '');
                                    $sid  = htmlspecialchars($it['student_number'] ?? '');
                                    $con  = htmlspecialchars($it['contact'] ?? '');
                                    $dc   = htmlspecialchars($it['updated_at'] ?? '');
                                    $img  = isset($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'],ENT_QUOTES,'UTF-8') : '';
                                    $da   = ' data-id="' . $tid . '" data-category="' . $cat . '"';
                                    if ($img) $da .= ' data-image="' . $img . '"';
                                    echo '<tr class="matched-data-row"' . $da . '>';
                                    echo '<td>' . $tid . '</td>';
                                    echo '<td>' . $cat . '</td>';
                                    echo '<td>' . $dept . '</td>';
                                    echo '<td>' . $sid . '</td>';
                                    echo '<td>' . $con . '</td>';
                                    echo '<td>' . $dc . '</td>';
                                    echo '<td class="found-action-cell"><button type="button" class="found-btn-view-guest">View</button></td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div><!-- /#histGuestSection -->

                </section>
            </div>
        </div>
    </main>
</div>

<!-- Item Details Modal (All Items) -->
<div id="viewModal" class="hdm-overlay" role="dialog" aria-modal="true" aria-labelledby="hdmTitle"
     onclick="if(event.target===this)closeHdm()">
    <div class="hdm-modal" onclick="event.stopPropagation()">
        <div class="hdm-header">
            <h3 class="hdm-header-title" id="hdmTitle">Item Details</h3>
            <button type="button" class="hdm-close-btn" id="hdmCloseBtn" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="hdm-body">
            <!-- Left: image + barcode + claimant info -->
            <div class="hdm-left">
                <div id="hdmPhotoPlaceholder" class="hdm-photo-placeholder">
                    <i class="fa-solid fa-box-open" style="font-size:28px;"></i>
                    <span>No photo</span>
                </div>
                <img id="hdmPhoto" class="hdm-photo" src="" alt="Item photo" style="display:none;">
                <p class="hdm-barcode" id="hdmBarcode">Barcode ID: —</p>

                <div class="hdm-claimant-section">
                    <h4 class="hdm-claimant-title">Claimant's Information</h4>
                    <div class="hdm-claimant-field">
                        <label class="hdm-claimant-label">Name:</label>
                        <div class="hdm-claimant-box" id="hdmClaimantName">—</div>
                    </div>
                    <div class="hdm-claimant-field">
                        <label class="hdm-claimant-label">Contact Number:</label>
                        <div class="hdm-claimant-box" id="hdmContact">—</div>
                    </div>
                    <div class="hdm-claimant-field">
                        <label class="hdm-claimant-label">Date of Accomplishment:</label>
                        <div class="hdm-claimant-box" id="hdmDateAccomp">—</div>
                    </div>
                </div>
            </div>
            <!-- Right: general information -->
            <div class="hdm-right">
                <h4 class="hdm-section-title">General Information</h4>
                <div id="hdmInfoRows"></div>
            </div>
        </div>
        <div class="hdm-footer">
            <button type="button" class="hdm-btn-close" id="hdmCloseFooter">Close</button>
        </div>
    </div>
</div>

<script>
/* Admin dropdown */
(function(){
    var d=document.getElementById('adminDropdown'), t=d&&d.querySelector('.admin-dropdown-trigger');
    if(!d||!t) return;
    t.addEventListener('click',function(e){e.stopPropagation();d.classList.toggle('open');t.setAttribute('aria-expanded',d.classList.contains('open'));});
    document.addEventListener('click',function(){d.classList.remove('open');t.setAttribute('aria-expanded','false');});
})();

/* Tab switching */
(function(){
    var allTab=document.getElementById('histAllTab'),
        guestTab=document.getElementById('histGuestTab'),
        allSec=document.getElementById('histAllSection'),
        gstSec=document.getElementById('histGuestSection');
    if(!allTab||!guestTab||!allSec||!gstSec) return;
    function showAll(){
        allTab.classList.add('found-tab-active');   guestTab.classList.remove('found-tab-active');
        allSec.style.display='';                    gstSec.style.display='none';
    }
    function showGuest(){
        guestTab.classList.add('found-tab-active'); allTab.classList.remove('found-tab-active');
        gstSec.style.display='';                    allSec.style.display='none';
    }
    allTab.addEventListener('click',showAll);
    guestTab.addEventListener('click',showGuest);
    if(window.location.hash==='#guest') showGuest();
})();

/* Category filter – applies to both visible tables */
(function(){
    var filter=document.getElementById('historyCategoryFilter');
    if(!filter) return;
    filter.addEventListener('change',function(){
        var val=(filter.value||'').trim();
        ['historyTable','historyGuestTable'].forEach(function(id){
            var t=document.getElementById(id); if(!t) return;
            t.querySelectorAll('.matched-data-row').forEach(function(r){
                var cat=(r.getAttribute('data-category')||'').trim();
                r.style.display=(!val||cat===val)?'':'none';
            });
        });
    });
})();

/* ── Item Details Modal (All Items) ─────────────────────────── */
window.closeHdm = function() {
    var m = document.getElementById('viewModal');
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
};
(function(){
    var modal = document.getElementById('viewModal');
    if (!modal) return;

    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function infoRow(label, val){
        if (!val || !String(val).trim()) return '';
        return '<div class="hdm-info-row"><span class="hdm-info-label">'+esc(label)+'</span><span class="hdm-info-value">'+esc(String(val))+'</span></div>';
    }

    function openAll(row){
        var ph  = document.getElementById('hdmPhotoPlaceholder');
        var img = document.getElementById('hdmPhoto');
        var bar = document.getElementById('hdmBarcode');
        var cn  = document.getElementById('hdmClaimantName');
        var ct  = document.getElementById('hdmContact');
        var da  = document.getElementById('hdmDateAccomp');
        var inf = document.getElementById('hdmInfoRows');

        var imgUrl = row.getAttribute('data-image');
        if (imgUrl) { img.src = imgUrl; img.style.display = 'block'; if(ph) ph.style.display = 'none'; }
        else         { if(img) img.style.display = 'none'; if(ph) ph.style.display = 'flex'; }

        var bid = row.getAttribute('data-id') || '—';
        if (bar) bar.textContent = 'Barcode ID: ' + bid;

        if (cn) cn.textContent = row.getAttribute('data-claimant-name') || '—';
        if (ct) ct.textContent = row.getAttribute('data-contact')       || '—';
        if (da) da.textContent = row.getAttribute('data-date-accomplished') || '—';

        if (inf) inf.innerHTML = [
            infoRow('Category:',         row.getAttribute('data-category')),
            infoRow('Item:',             row.getAttribute('data-item-name')),
            infoRow('Color:',            row.getAttribute('data-color')),
            infoRow('Brand:',            row.getAttribute('data-brand')),
            infoRow('Item Description:', row.getAttribute('data-item-description')),
            infoRow('Storage Location:', row.getAttribute('data-storage-location')),
            infoRow('Found At:',         row.getAttribute('data-found-at')),
            infoRow('Found By:',         row.getAttribute('data-found-by')),
            infoRow('Date Found:',       row.getAttribute('data-date-encoded')),
        ].join('') || '<p style="color:#9ca3af;font-size:13px;">No details available.</p>';

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    /* Guest modal (separate simple modal kept for guest tab) */
    function openGuest(row){
        var cells = row.querySelectorAll('td');
        var ph    = document.getElementById('hdmPhotoPlaceholder');
        var img   = document.getElementById('hdmPhoto');
        var bar   = document.getElementById('hdmBarcode');
        var cn    = document.getElementById('hdmClaimantName');
        var ct    = document.getElementById('hdmContact');
        var da    = document.getElementById('hdmDateAccomp');
        var inf   = document.getElementById('hdmInfoRows');

        var imgUrl = row.getAttribute('data-image');
        if (imgUrl) { img.src = imgUrl; img.style.display = 'block'; if(ph) ph.style.display = 'none'; }
        else         { if(img) img.style.display = 'none'; if(ph) ph.style.display = 'flex'; }

        var bid = row.getAttribute('data-id') || '—';
        if (bar) bar.textContent = 'Barcode ID: ' + bid;

        /* Guest items: claimant section is not applicable — hide it */
        if (cn) cn.textContent = '—';
        if (ct) ct.textContent = '—';
        if (da) da.textContent = cells[5] ? cells[5].textContent.trim() : '—';

        if (inf) inf.innerHTML = [
            infoRow('Category:',     row.getAttribute('data-category')),
            infoRow('Department:',   cells[2] ? cells[2].textContent.trim() : ''),
            infoRow('Student ID:',   cells[3] ? cells[3].textContent.trim() : ''),
            infoRow('Contact:',      cells[4] ? cells[4].textContent.trim() : ''),
            infoRow('Date Claimed:', cells[5] ? cells[5].textContent.trim() : ''),
        ].join('') || '<p style="color:#9ca3af;font-size:13px;">No details available.</p>';

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    var tAll   = document.getElementById('historyTable');
    var tGuest = document.getElementById('historyGuestTable');
    if (tAll) tAll.addEventListener('click', function(e){
        var btn = e.target.closest('.found-btn-view'); if (!btn) return; e.preventDefault();
        var row = btn.closest('tr'); if (row && !row.querySelector('td[colspan]')) openAll(row);
    });
    if (tGuest) tGuest.addEventListener('click', function(e){
        var btn = e.target.closest('.found-btn-view-guest'); if (!btn) return; e.preventDefault();
        var row = btn.closest('tr'); if (row && !row.querySelector('td[colspan]')) openGuest(row);
    });

    var closeBtn = document.getElementById('hdmCloseBtn');
    var closeFooter = document.getElementById('hdmCloseFooter');
    if (closeBtn)    closeBtn.addEventListener('click', window.closeHdm);
    if (closeFooter) closeFooter.addEventListener('click', window.closeHdm);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') window.closeHdm(); });
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
<script src="NotificationsDropdown.js"></script>
</body>
</html>