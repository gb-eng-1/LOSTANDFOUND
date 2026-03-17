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
                                    $da = ' data-id="' . $bid . '" data-category="' . $cat . '" data-color="' . $color . '" data-brand="' . $brand . '" data-found-by="' . $foundBy . '" data-date-encoded="' . $dateFnd . '" data-storage-location="' . $storage . '"';
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

/* Shared view modal */
(function(){
    var modal=document.getElementById('viewModal'),
        imgEl=document.getElementById('viewModalImage'),
        bidEl=document.getElementById('viewModalBid'),
        bodyEl=document.getElementById('viewModalBody');
    if(!modal||!bodyEl) return;

    function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    function r(label,val){
        if(!val||String(val).trim()==='') return '';
        return '<div class="adm-modal-row"><span class="adm-modal-lbl">'+esc(label)+'</span><span class="adm-modal-val">'+esc(String(val))+'</span></div>';
    }
    function setImg(url){
        if(url){imgEl.innerHTML='<img src="'+esc(url)+'" alt="Item" style="max-width:100%;max-height:180px;border-radius:6px;object-fit:contain;">';}
        else{imgEl.innerHTML='<div class="adm-modal-imgph"><i class="fa-solid fa-box-open"></i></div>';}
    }
    function openAll(row){
        var cells=row.querySelectorAll('td');
        setImg(row.getAttribute('data-image'));
        if(bidEl) bidEl.textContent=row.getAttribute('data-id')||(cells[0]?cells[0].textContent.trim():'');
        bodyEl.innerHTML=[
            r('Category',        row.getAttribute('data-category')),
            r('Color',           row.getAttribute('data-color')),
            r('Brand',           row.getAttribute('data-brand')),
            r('Found At',        cells[2]?cells[2].textContent.trim():''),
            r('Date Found',      cells[3]?cells[3].textContent.trim():''),
            r('Date Claimed',    cells[4]?cells[4].textContent.trim():''),
            r('Storage Location',row.getAttribute('data-storage-location')),
            r('Timestamp',       cells[6]?cells[6].textContent.trim():''),
            r('Found By',        row.getAttribute('data-found-by')),
        ].join('')||'<p style="color:#9ca3af;font-size:13px;">No details available.</p>';
        modal.classList.add('view-modal-open');
    }
    function openGuest(row){
        var cells=row.querySelectorAll('td');
        setImg(row.getAttribute('data-image'));
        if(bidEl) bidEl.textContent=row.getAttribute('data-id')||(cells[0]?cells[0].textContent.trim():'');
        bodyEl.innerHTML=[
            r('Category',       row.getAttribute('data-category')),
            r('Department',     cells[2]?cells[2].textContent.trim():''),
            r('ID',             cells[3]?cells[3].textContent.trim():''),
            r('Contact Number', cells[4]?cells[4].textContent.trim():''),
            r('Date Claimed',   cells[5]?cells[5].textContent.trim():''),
        ].join('')||'<p style="color:#9ca3af;font-size:13px;">No details available.</p>';
        modal.classList.add('view-modal-open');
    }
    function closeModal(){modal.classList.remove('view-modal-open');}

    var tAll=document.getElementById('historyTable');
    var tGuest=document.getElementById('historyGuestTable');
    if(tAll) tAll.addEventListener('click',function(e){
        var btn=e.target.closest('.found-btn-view'); if(!btn) return; e.preventDefault();
        var row=btn.closest('tr'); if(row&&!row.querySelector('td[colspan]')) openAll(row);
    });
    if(tGuest) tGuest.addEventListener('click',function(e){
        var btn=e.target.closest('.found-btn-view-guest'); if(!btn) return; e.preventDefault();
        var row=btn.closest('tr'); if(row&&!row.querySelector('td[colspan]')) openGuest(row);
    });
    var closeBtn=modal.querySelector('.view-modal-close');
    if(closeBtn) closeBtn.addEventListener('click',closeModal);
    modal.addEventListener('click',function(e){if(e.target===modal)closeModal();});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});
    var cancelBtn=document.getElementById('viewModalCancel');
    if(cancelBtn) cancelBtn.addEventListener('click',closeModal);
})();
</script>
<script src="NotificationsDropdown.js"></script>
</body>
</html>