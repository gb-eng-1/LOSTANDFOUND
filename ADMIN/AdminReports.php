<?php
// AdminReports.php - Reports view for UB Lost and Found System (database)
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

$today = date('Y-m-d');

// ── Lost reports (all REF- items, both web-submitted and admin-encoded) ────────
$stmt = $pdo->query("SELECT id, user_id, item_type, color, brand, found_at, found_by, date_encoded, date_lost, item_description, storage_location, image_data, status, created_at FROM items WHERE id LIKE 'REF-%' ORDER BY created_at DESC");
$encodedItems = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $desc = $row['item_description'] ?? '';
    $get  = function($key) use ($desc) {
        if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+?)(?:\n|$)/m', $desc, $m)) return trim($m[1]);
        return '';
    };
    $studentNumber = $get('Student Number') ?: $get('Student ID');
    $department    = $get('Department');
    $contact       = $get('Contact');
    $fullName      = $get('Full Name') ?: $get('Name');
    $itemTypeLabel = $get('Item Type') ?: $get('Item Name') ?: ($row['item_type'] ?? '');
    $encodedItems[] = [
        'id'               => $row['id'],
        'user_id'          => $row['user_id'],
        'item_type'        => $row['item_type'],
        'item_type_label'  => $itemTypeLabel,
        'student_number'   => $studentNumber,
        'department'       => $department,
        'contact'          => $contact,
        'full_name'        => $fullName,
        'color'            => $row['color'],
        'brand'            => $row['brand'],
        'date_lost'        => $row['date_lost'],
        'item_description' => $desc,
        'imageDataUrl'     => $row['image_data'],
        'dateEncoded'      => $row['date_encoded'],
        'storage_location' => $row['storage_location'],
        'found_by'         => $row['found_by'],
    ];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UB Lost and Found System - Reports</title>
    <link rel="stylesheet" href="AdminDashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="AdminReports.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="NotificationsDropdown.css?v=<?php echo time(); ?>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons (Font Awesome) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
    <style>
        /* Inline safety net: force Cancel button styles regardless of any global resets */
        .reports-action-cell button.reports-btn-cancel {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 6px 14px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            font-family: inherit !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            background-color: #b91c1c !important;
            color: #ffffff !important;
            border: none !important;
            outline: none !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            transition: background-color 0.15s ease, box-shadow 0.15s ease !important;
            line-height: 1.4 !important;
            box-sizing: border-box !important;
        }
        .reports-action-cell button.reports-btn-cancel:hover {
            background-color: #991b1b !important;
            box-shadow: 0 1px 3px rgba(185, 28, 28, 0.4) !important;
        }
        .reports-action-cell button.reports-btn-cancel:disabled {
            opacity: 0.65 !important;
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
  white-space: nowrap;
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
<body>
<div class="layout">
    <!-- Sidebar (same as Dashboard) -->
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
                <a class="nav-item active" href="AdminReports.php">
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

    <!-- Main content -->
    <main class="main">
        <!-- Top bar (same as Dashboard) -->
        <div class="topbar topbar-maroon">
            <div class="topbar-search-wrap topbar-search-left">
                <form class="search-form" action="AdminReports.php" method="get">
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
                <!-- Lost Items header -->
                <h2 class="page-title">Lost Items</h2>
                <div class="found-tabs-actions-row">
                    <div class="found-tabs">
                        <span class="found-tab-text found-tab-active">All Items</span>
                    </div>
                    <select class="found-filter-select" id="reportsCategoryFilter" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        <?php
                        $itemCategories = require dirname(__DIR__) . '/config/categories.php';
                        foreach ($itemCategories as $c):
                        ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Reports table card -->
                <div class="inventory-card reports-card">
                    <div class="inventory-title">Reports</div>
                    <div class="table-wrapper">
                        <table class="reports-table" data-modal-prefix="Report Details">
                            <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Category</th>
                                <th>Department</th>
                                <th>ID</th>
                                <th>Contact Number</th>
                                <th>Date Lost</th>
                                <th>Retention End</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            if (empty($encodedItems)) {
                                echo '<tr><td colspan="8" class="table-empty">No reports yet. Encode Report in Found to add lost item reports.</td></tr>';
                            } else {
                                foreach ($encodedItems as $it) {
                                    $refId      = htmlspecialchars($it['id'] ?? '');
                                    $cat        = htmlspecialchars($it['item_type'] ?? '');
                                    $dept       = htmlspecialchars($it['department'] ?? '');
                                    $studentNum = htmlspecialchars($it['student_number'] ?? '');
                                    $contact    = htmlspecialchars($it['contact'] ?? '');
                                    $dateLost   = htmlspecialchars($it['date_lost'] ?? '');
                                    $dateEnc    = $it['dateEncoded'] ?? '';
                                    $retEnd     = $dateEnc ? date('Y-m-d', strtotime($dateEnc . ' +1 year')) : '';
                                    $isOverdueR  = $retEnd && $retEnd < $today;
                                    $isExpiringR = !$isOverdueR && $retEnd && $retEnd <= date('Y-m-d', strtotime('+30 days'));
                                    $retBadgeR  = '';
                                    if ($isOverdueR) {
                                        $retBadgeR = ' <span style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;vertical-align:middle;">EXPIRED</span>';
                                    } elseif ($isExpiringR) {
                                        $retBadgeR = ' <span style="background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;vertical-align:middle;">EXPIRING</span>';
                                    }
                                    $img        = isset($it['imageDataUrl']) ? htmlspecialchars($it['imageDataUrl'], ENT_QUOTES, 'UTF-8') : '';
                                    $dataAttrs  = ' data-id="' . $refId . '"'
                                               . ' data-category="' . $cat . '"'
                                               . ' data-color="' . htmlspecialchars($it['color'] ?? '') . '"'
                                               . ' data-brand="' . htmlspecialchars($it['brand'] ?? '') . '"'
                                               . ' data-user-id="' . htmlspecialchars($it['user_id'] ?? '') . '"'
                                               . ' data-date-lost="' . $dateLost . '"'
                                               . ' data-item-description="' . htmlspecialchars($it['item_description'] ?? '', ENT_QUOTES, 'UTF-8') . '"'
                                               . ' data-storage-location="' . htmlspecialchars($it['storage_location'] ?? '') . '"';
                                    if ($img) $dataAttrs .= ' data-image="' . $img . '"';
                                    echo '<tr' . $dataAttrs . '>'
                                       . '<td>' . $refId . '</td>'
                                       . '<td>' . $cat . '</td>'
                                       . '<td>' . $dept . '</td>'
                                       . '<td>' . $studentNum . '</td>'
                                       . '<td>' . $contact . '</td>'
                                       . '<td>' . $dateLost . '</td>'
                                       . '<td>' . htmlspecialchars($retEnd) . $retBadgeR . '</td>'
                                       . '<td class="reports-action-cell">'
                                       .   '<a href="#" class="reports-btn reports-btn-view btn-view">View</a>'
                                       .   '<button type="button" class="reports-btn reports-btn-cancel btn-cancel-reports">Cancel</button>'
                                       . '</td>'
                                       . '</tr>';
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
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
    /* Admin dropdown */
    (function () {
        var dropdown = document.getElementById('adminDropdown');
        var trigger  = dropdown && dropdown.querySelector('.admin-dropdown-trigger');
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

    /* Category filter */
    (function () {
        var select = document.getElementById('reportsCategoryFilter');
        var table  = document.querySelector('.reports-table');
        if (!select || !table) return;
        var tbody = table.querySelector('tbody');
        var rows  = tbody ? tbody.querySelectorAll('tr') : [];
        function filterByCategory() {
            var value = (select.value || '').trim();
            rows.forEach(function (row) {
                if (row.querySelector('td[colspan]')) { row.style.display = value ? 'none' : ''; return; }
                var categoryCell = row.querySelector('td:nth-child(3)');
                var category     = categoryCell ? categoryCell.textContent.trim() : '';
                row.style.display = (!value || category === value) ? '' : 'none';
            });
        }
        select.addEventListener('change', filterByCategory);
    })();

    /* View modal */
    (function () {
        var modal   = document.getElementById('viewModal');
        var imageEl = document.getElementById('viewModalImage');
        var bodyEl  = document.getElementById('viewModalBody');
        if (!modal || !bodyEl) return;

        function esc(s) {
            return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function getThLabel(th) {
            if (!th) return '';
            return (th.innerHTML || '').replace(/<br\s*\/?>/gi, ' / ').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
        }

        function openModal(row) {
            var table   = row.closest('table');
            var headers = table ? table.querySelectorAll('thead th') : [];
            var cells   = row.querySelectorAll('td');
            var imgUrl  = row.getAttribute('data-image');

            if (imgUrl) {
                imageEl.innerHTML = '<img src="' + esc(imgUrl) + '" alt="Item">';
                imageEl.classList.remove('view-modal-image-placeholder');
            } else {
                imageEl.innerHTML = '<div class="view-modal-image-placeholder-inner"><span class="view-modal-image-icon" aria-hidden="true">&#128230;</span><span>Item image</span></div>';
                imageEl.classList.add('view-modal-image-placeholder');
            }

            var pairs = [], seen = {};
            var extra = [
                { key: 'Category',         attr: 'data-category' },
                { key: 'Color',            attr: 'data-color' },
                { key: 'Brand',            attr: 'data-brand' },
                { key: 'Found By',         attr: 'data-found-by' },
                { key: 'Encoded By',       attr: 'data-encoded-by' },
                { key: 'Date Encoded',     attr: 'data-date-encoded' },
                { key: 'Storage Location', attr: 'data-storage-location' }
            ];

            for (var i = 0; i < cells.length - 1; i++) {
                var label = getThLabel(headers[i]);
                var val   = (cells[i] && cells[i].textContent) ? cells[i].textContent.trim() : '';
                if (label) { pairs.push({ label: label, value: val }); seen[label] = true; }
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

        function closeModal() { modal.classList.remove('view-modal-open'); }

        document.querySelectorAll('.btn-view').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var r = e.target.closest('tr');
                if (r) openModal(r);
            });
        });

        modal.querySelector('.view-modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

        var cancelBtn = document.getElementById('viewModalCancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    })();


    /* ── Shared confirmation modal ──────────────────────────────────── */
    window.confirmAction = window.confirmAction || (function () {
        var modal = document.getElementById('confirmActionModal');
        var msgEl = document.getElementById('confirmActionMsg');
        var btnNo = document.getElementById('confirmActionNo');
        var btnYes = document.getElementById('confirmActionYes');
        var cb = null;
        if (modal) {
            btnNo.addEventListener('click', close);
            btnYes.addEventListener('click', function () { if (cb) cb(); close(); });
            modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display === 'flex') close(); });
        }
        function close() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); } cb = null; }
        return function (msg, onConfirm) {
            if (!modal) { if (onConfirm && window.confirm(msg)) onConfirm(); return; }
            msgEl.textContent = msg;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden','false');
            cb = onConfirm;
        };
    })();

    /* Cancel / delete row */
    (function () {
        var table = document.querySelector('.reports-table');
        if (!table) return;
        var tbody     = table.querySelector('tbody');
        if (!tbody) return;
        var deleteUrl = '../delete_item.php';

        function ensureEmptyRow() {
            var rows       = tbody.querySelectorAll('tr');
            var hasDataRow = false;
            for (var i = 0; i < rows.length; i++) {
                if (!rows[i].querySelector('td[colspan]')) { hasDataRow = true; break; }
            }
            if (!hasDataRow) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="7" class="table-empty">No reports yet. Encode Report in Found to add lost item reports.</td>';
                tbody.appendChild(tr);
            }
        }

        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-cancel-reports');
            if (!btn) return;
            e.preventDefault();
            var row = btn.closest('tr');
            if (!row || row.querySelector('td[colspan]')) return;
            var id  = row.getAttribute('data-id') || (row.querySelector('td:nth-child(2)') && row.querySelector('td:nth-child(2)').textContent.trim());
            if (!id) return;
            window.confirmAction('Are you sure you want to delete this report? This cannot be undone.', function () {
            btn.disabled = true;
            fetch(deleteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    row.remove();
                    ensureEmptyRow();
                } else {
                    btn.disabled = false;
                    alert(data && data.error ? data.error : 'Could not delete report.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Could not delete report. Try again.');
            });
            }); // end confirmAction
        });
    })();
</script>

<!-- Confirmation modal -->
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
<script src="NotificationsDropdown.js"></script>
</body>
</html>