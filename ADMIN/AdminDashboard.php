<?php
/**
 * Admin Dashboard - UB Lost and Found System
 * All data from live database — no hardcoded values.
 */
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/config/database.php';

function notifyAdmin($pdo, $type, $title, $message, $relatedId = null) {
    try {
        $aid = $pdo->query('SELECT id FROM admins ORDER BY id LIMIT 1')->fetchColumn();
        if ($aid) {
            $pdo->prepare(
                "INSERT INTO notifications (recipient_id, recipient_type, type, title, message, related_id, created_at)
                 VALUES (?, 'admin', ?, ?, ?, ?, NOW())"
            )->execute([(int)$aid, $type, $title, $message, $relatedId]);
        }
    } catch (Exception $e) { /* non-fatal */ }
}

// ─── AJAX: Item Details ───────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'item' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $itemId = trim($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $item
            ? json_encode(['ok' => true,  'data' => $item])
            : json_encode(['ok' => false, 'message' => 'Item not found.']);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ─── AJAX: Encode New Item ────────────────────────────────────────────────────
if (isset($_POST['ajax']) && $_POST['ajax'] === 'encode') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $barcodeId    = trim($_POST['barcode_id']       ?? '');
        $itemType     = trim($_POST['category']          ?? '');
        $itemName     = trim($_POST['item_name']         ?? '');
        $color        = trim($_POST['color']             ?? '');
        $brand        = trim($_POST['brand']             ?? '');
        $rawDesc      = trim($_POST['item_description']  ?? '');
        $storage      = trim($_POST['storage_location']  ?? '');
        $foundAt      = trim($_POST['found_at']          ?? '');
        $foundBy      = trim($_POST['found_by']          ?? '');
        $dateFnd      = trim($_POST['date_found']        ?? '');
        $imageData    = trim($_POST['image_data']        ?? '');

        // Required
        if (!$barcodeId) throw new Exception('Barcode ID is required.');
        if (!$itemName)  throw new Exception('Item is required.');
        if (!$rawDesc)   throw new Exception('Item Description is required.');

        // Check if barcode already exists in system
        $chk = $pdo->prepare("SELECT id, status FROM items WHERE id = ? LIMIT 1");
        $chk->execute([$barcodeId]);
        $existingItem = $chk->fetch(PDO::FETCH_ASSOC);

        // A barcode sticker can be re-used only when its previous record is fully closed.
        // This supports Issue #6: encoding from paper with a pre-existing sticker.
        $reclaimableStatuses = ['Claimed', 'Resolved', 'Disposed', 'Cancelled'];
        $reEncode = false;
        if ($existingItem) {
            if (!in_array($existingItem['status'], $reclaimableStatuses)) {
                throw new Exception(
                    'Barcode ' . $barcodeId . ' is still active (Status: ' . $existingItem['status'] . '). '
                    . 'Please check the inventory or verify the sticker belongs to this item.'
                );
            }
            $reEncode = true;
        }

        // Embed item name as first line of description so we can parse it back
        $fullDesc = "Item: {$itemName}\n{$rawDesc}";

        // Validate date: must not be in the future
        $today = date('Y-m-d');
        if ($dateFnd && $dateFnd > $today) throw new Exception('Date Found cannot be in the future.');

        if ($reEncode) {
            // Re-encode: update the existing record and reset to Unclaimed Items
            $stmt = $pdo->prepare("
                UPDATE items SET
                    item_type = ?, color = ?, brand = ?, item_description = ?,
                    storage_location = ?, found_at = ?, found_by = ?, date_encoded = ?,
                    image_data = ?, status = 'Unclaimed Items', user_id = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $itemType, $color, $brand, $fullDesc,
                $storage, $foundAt, $foundBy, ($dateFnd ?: null), ($imageData ?: null),
                $barcodeId
            ]);
            // Clear stale matches so this item starts fresh in the matching queue
            try {
                $pdo->prepare("DELETE FROM item_matches WHERE found_item_id = ?")->execute([$barcodeId]);
            } catch (PDOException $e) { /* item_matches may not exist yet */ }
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO items
                    (id, item_type, color, brand, item_description,
                     storage_location, found_at, found_by, date_encoded, image_data, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unclaimed Items')
            ");
            $stmt->execute([
                $barcodeId, $itemType, $color, $brand, $fullDesc,
                $storage, $foundAt, $foundBy, ($dateFnd ?: null), ($imageData ?: null)
            ]);
        }

        // ── Find potential matching lost reports (Issue #4 & #5) ──────────────
        // Surface REF- reports with the same category so admin can link immediately.
        $potentialTickets = [];
        try {
            $tSql    = "SELECT id, item_type, item_description, date_lost, user_id
                        FROM items
                        WHERE id LIKE 'REF-%'
                          AND status NOT IN ('Cancelled','Claimed','Resolved','Disposed')";
            $tParams = [];
            if ($itemType) { $tSql .= " AND item_type = ?"; $tParams[] = $itemType; }
            $tSql .= " ORDER BY created_at DESC LIMIT 10";
            $tStmt = $pdo->prepare($tSql);
            $tStmt->execute($tParams);
            foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $desc = $t['item_description'] ?? '';
                $parsedName = '';
                if (preg_match('/^Item:\s*(.+?)(?:\n|$)/m',      $desc, $m)) $parsedName = trim($m[1]);
                elseif (preg_match('/^Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) $parsedName = trim($m[1]);
                $potentialTickets[] = [
                    'id'        => $t['id'],
                    'category'  => $t['item_type'] ?? '-',
                    'item_name' => $parsedName ?: ($t['item_type'] ?? 'Unknown'),
                    'date_lost' => $t['date_lost'] ?? null,
                    'reporter'  => $t['user_id']   ?? '',
                ];
            }
        } catch (PDOException $e) { /* non-fatal */ }

        echo json_encode([
            'ok'                => true,
            'message'           => $reEncode ? 'Item re-encoded successfully.' : 'Item encoded successfully.',
            'id'                => $barcodeId,
            're_encoded'        => $reEncode,
            'potential_tickets' => $potentialTickets,
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── AJAX: Link Found Item → Lost Report Ticket (Issue #5) ────────────────────
// Called from the encode-success overlay when admin picks a matching REF- ticket.
if (isset($_POST['ajax']) && $_POST['ajax'] === 'link_ticket') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $foundItemId  = trim($_POST['found_item_id']  ?? '');
        $lostReportId = trim($_POST['lost_report_id'] ?? '');
        if (!$foundItemId)  throw new Exception('Found item ID is required.');
        if (!$lostReportId) throw new Exception('Ticket/report ID is required.');

        // Verify both records exist
        $chk = $pdo->prepare("SELECT id, status FROM items WHERE id = ? LIMIT 1");
        $chk->execute([$foundItemId]);
        $foundRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$foundRow) throw new Exception('Found item not found in the system.');

        $chk->execute([$lostReportId]);
        $reportRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$reportRow) throw new Exception('Ticket/report not found in the system.');
        if (!str_starts_with($lostReportId, 'REF-'))
            throw new Exception('Ticket ID must be a valid REF- report.');

        // Insert into item_matches junction table (supports many-to-many, Issue #3)
        $pdo->prepare("
            INSERT INTO item_matches (lost_report_id, found_item_id, status, matched_by)
            VALUES (?, ?, 'Pending', ?)
            ON DUPLICATE KEY UPDATE status = 'Pending', matched_by = VALUES(matched_by), updated_at = NOW()
        ")->execute([$lostReportId, $foundItemId, $adminName ?? 'admin']);

        // Advance statuses: found item → For Verification, lost report → Unresolved Claimants
        $pdo->prepare("UPDATE items SET status = 'For Verification'     WHERE id = ?")->execute([$foundItemId]);
        $pdo->prepare("UPDATE items SET status = 'Unresolved Claimants' WHERE id = ?")->execute([$lostReportId]);

        notifyAdmin($pdo, 'match_linked',
            'Items Matched',
            'Lost report ' . $lostReportId . ' has been matched with found item ' . $foundItemId . '.',
            $lostReportId
        );

        echo json_encode([
            'ok'      => true,
            'message' => 'Item linked to ticket ' . $lostReportId . '. Status updated to For Verification.',
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';

// ─── Summary Stats ───────────────────────────────────────────────────────────
$stats = ['internal_recovered' => 0, 'external_ids' => 0, 'unresolved' => 0, 'verification' => 0];
try {
    $stats['internal_recovered'] = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE id NOT LIKE 'REF-%' AND status NOT IN ('Claimed','Resolved','Cancelled')")->fetchColumn();
    $stats['external_ids']       = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE id NOT LIKE 'REF-%' AND item_type = 'Document & Identification' AND status NOT IN ('Claimed','Resolved','Cancelled','Disposed')")->fetchColumn();
    $stats['unresolved']         = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status = 'Unresolved Claimants'")->fetchColumn();
    $stats['verification']       = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status = 'For Verification'")->fetchColumn();
} catch (PDOException $e) { error_log("AdminDashboard stats: " . $e->getMessage()); }

// ─── PIE Chart: Status distribution ──────────────────────────────────────────
// Unclaimed Items (orange), Unresolved Claimants (red), Unclaimed IDs (purple), For Verification (green)
$pieData = [];
try {
    $unclaimed   = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status = 'Unclaimed Items'")->fetchColumn();
    $unclaimedId = $stats['external_ids'];
    $unresolved  = $stats['unresolved'];
    $forVerif    = $stats['verification'];
    $pieTotal    = $unclaimed + $unclaimedId + $unresolved + $forVerif;
    if ($pieTotal > 0) {
        $pieData = [
            ['label' => 'Unclaimed Items',      'pct' => round($unclaimed   / $pieTotal * 100), 'color' => '#F57C00'],
            ['label' => 'Unclaimed IDs',        'pct' => round($unclaimedId / $pieTotal * 100), 'color' => '#9C27B0'],
            ['label' => 'Unresolved Claimants', 'pct' => round($unresolved  / $pieTotal * 100), 'color' => '#E55C5C'],
            ['label' => 'For Verification',     'pct' => round($forVerif    / $pieTotal * 100), 'color' => '#8BC34A'],
        ];
        // Correct rounding drift
        $diff = 100 - array_sum(array_column($pieData, 'pct'));
        if ($diff) $pieData[0]['pct'] += $diff;
    }
} catch (PDOException $e) { error_log("AdminDashboard pie: " . $e->getMessage()); }

$pieCaption = 'No data available.';
if (!empty($pieData)) {
    $topPie = array_reduce($pieData, fn($c, $i) => (!$c || $i['pct'] > $c['pct']) ? $i : $c);
    $pieCaption = $topPie['label'] . ' has the most percent!';
}
$pieDataJson = json_encode($pieData);

// ─── BAR Chart: Only the 5 canonical categories (as per config/categories.php) ─
// Categories: Electronics & Gadgets, Document & Identification, Personal Belongings,
//             Apparel & Accessories, Miscellaneous
$canonicalCategories = [
    'Electronics & Gadgets'     => '#9B8FE8',
    'Document & Identification' => '#F0C930',
    'Personal Belongings'       => '#5BC8D4',
    'Apparel & Accessories'     => '#9CAF60',
    'Miscellaneous'             => '#F4A4A4',
];
$barData = [];
try {
    // Build a safe IN clause for exactly these 5 categories
    $catPlaceholders = implode(',', array_fill(0, count($canonicalCategories), '?'));
    $catKeys = array_keys($canonicalCategories);

    $stmt = $pdo->prepare("
        SELECT item_type, COUNT(*) AS cnt
        FROM items
        WHERE item_type IN ($catPlaceholders)
          AND status NOT IN ('Cancelled','Disposed')
        GROUP BY item_type
        ORDER BY cnt DESC
    ");
    $stmt->execute($catKeys);
    $catRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure all 5 categories appear (zero if no data)
    $catMap = [];
    foreach ($catRows as $row) { $catMap[$row['item_type']] = (int)$row['cnt']; }
    foreach ($canonicalCategories as $cat => $color) {
        if (!isset($catMap[$cat])) $catMap[$cat] = 0;
    }

    $barTotal = array_sum($catMap);
    foreach ($canonicalCategories as $cat => $color) {
        $cnt = $catMap[$cat] ?? 0;
        $pct = $barTotal > 0 ? round($cnt / $barTotal * 100, 2) : 0;
        $barData[] = ['label' => $cat, 'pct' => $pct, 'color' => $color];
    }
    // Sort by pct DESC for a cleaner bar chart
    usort($barData, fn($a, $b) => $b['pct'] <=> $a['pct']);
} catch (PDOException $e) { error_log("AdminDashboard bar: " . $e->getMessage()); }

$barCaption = 'No data available.';
if (!empty($barData)) {
    $topBar = array_reduce($barData, fn($c, $i) => (!$c || $i['pct'] > $c['pct']) ? $i : $c);
    $barCaption = 'Category of ' . $topBar['label'] . ' has the most percent of being unclaimed.';
}
$barDataJson = json_encode($barData);

// ─── Recovered Item (Internal) ────────────────────────────────────────────────
$recoveredInternal = [];
try {
    $stmt = $pdo->query("SELECT id, item_type, found_at, date_encoded FROM items WHERE id NOT LIKE 'REF-%' ORDER BY date_encoded DESC, created_at DESC LIMIT 7");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $recoveredInternal[] = [
            'id'            => $r['id'],
            'item_type'     => $r['item_type'] ?? '-',
            'found_at'      => $r['found_at']  ?? '-',
            'date_encoded'  => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '-',
            'retention_end' => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'] . ' +6 months')) : 'N/A',
        ];
    }
} catch (PDOException $e) { error_log("AdminDashboard internal: " . $e->getMessage()); }

// ─── Recovered IDs (External) ─────────────────────────────────────────────────
$recoveredExternal = [];
try {
    $rows = $pdo->query("SELECT id, found_by, storage_location, date_encoded FROM items WHERE id NOT LIKE 'REF-%' AND item_type = 'Document & Identification' AND status NOT IN ('Claimed','Resolved','Cancelled','Disposed') ORDER BY date_encoded DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $recoveredExternal[] = [
            'id'               => $r['id'],
            'found_by'         => $r['found_by']         ?? '-',
            'storage_location' => $r['storage_location'] ?? '-',
            'date_encoded'     => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '-',
            'retention_end'    => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'] . ' +6 months')) : 'N/A',
        ];
    }
} catch (PDOException $e) { error_log("AdminDashboard external: " . $e->getMessage()); }

// ─── Unresolved Claimants ─────────────────────────────────────────────────────
// Includes BOTH:
//   - Web reports (REF- items submitted digitally by students)
//   - Manual reports (items encoded at the office, non-REF- IDs with this status)
$unresolvedRows = [];
try {
    $stmt = $pdo->query("SELECT id, item_type, item_description FROM items WHERE status = 'Unresolved Claimants' ORDER BY created_at DESC LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $desc = $r['item_description'] ?? '';
        $dept = $contact = $idNum = '';
        if (preg_match('/Department:\s*(.+?)(?:\n|$)/m', $desc, $m)) $dept    = trim($m[1]);
        if (preg_match('/Contact:\s*(.+?)(?:\n|$)/m',    $desc, $m)) $contact = trim($m[1]);
        if (preg_match('/Student Number:\s*(.+?)(?:\n|$)/m', $desc, $m)) $idNum = trim($m[1]);
        // Derive source: REF- prefix = web report, anything else = manual (office-encoded)
        $source = str_starts_with($r['id'], 'REF-') ? 'Web' : 'Manual';
        $unresolvedRows[] = [
            'ticket_id'      => $r['id'],
            'category'       => $r['item_type'] ?? '-',
            'department'     => $dept    ?: '-',
            'id_num'         => $idNum   ?: '-',
            'contact_number' => $contact ?: '-',
            'source'         => $source,
        ];
    }
} catch (PDOException $e) { error_log("AdminDashboard unresolved: " . $e->getMessage()); }

// ─── For Verification ─────────────────────────────────────────────────────────
$verificationRows = [];
try {
    $stmt = $pdo->query("SELECT id, item_type, found_at, date_encoded, storage_location FROM items WHERE status = 'For Verification' ORDER BY created_at DESC LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $verificationRows[] = [
            'id'            => $r['id'],
            'item_type'     => $r['item_type'] ?? '-',
            'found_at'      => $r['found_at']  ?? '-',
            'retention_end' => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'] . ' +6 months')) : 'N/A',
            'storage'       => $r['storage_location'] ?? '-',
            'date_found'    => $r['date_encoded'] ? date('Y-m-d', strtotime($r['date_encoded'])) : '-',
        ];
    }
} catch (PDOException $e) { error_log("AdminDashboard verification: " . $e->getMessage()); }

// ─── Recent Activity (100% from live DB) ─────────────────────────────────────
$recentActivity = [];
try {
    // Check for matched_barcode_id column
    $hasMatchedCol = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM items LIKE 'matched_barcode_id'");
        $hasMatchedCol = $chk && $chk->rowCount() > 0;
    } catch (PDOException $e) {}

    $buildName = function(array $r): string {
        // Use only the category (item_type) to keep activity text short
        return trim($r['item_type'] ?? '') ?: '';
    };

    $activities = [];

    // 1. Matched: non-REF items with matched_barcode_id set
    if ($hasMatchedCol) {
        $rows = $pdo->query("SELECT id, item_type, color, brand, created_at FROM items WHERE id NOT LIKE 'REF-%' AND matched_barcode_id IS NOT NULL AND matched_barcode_id != '' ORDER BY created_at DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $activities[] = ['action' => 'matched', 'item_id' => $r['id'], 'item_name' => $buildName($r), 'created_at' => $r['created_at']];
        }
    }

    // 2. Lost: REF- items (lost reports submitted by students)
    $rows = $pdo->query("SELECT id, item_type, color, brand, created_at FROM items WHERE id LIKE 'REF-%' ORDER BY created_at DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $activities[] = ['action' => 'lost', 'item_id' => $r['id'], 'item_name' => $buildName($r), 'created_at' => $r['created_at']];
    }

    // 3. Found: non-REF items (encoded by admin)
    $noMatchClause = $hasMatchedCol ? "AND (matched_barcode_id IS NULL OR matched_barcode_id = '')" : '';
    $rows = $pdo->query("SELECT id, item_type, color, brand, created_at FROM items WHERE id NOT LIKE 'REF-%' $noMatchClause ORDER BY created_at DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $activities[] = ['action' => 'found', 'item_id' => $r['id'], 'item_name' => $buildName($r), 'created_at' => $r['created_at']];
    }

    // Sort by created_at DESC, keep 8
    usort($activities, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    $recentActivity = array_slice($activities, 0, 8);

} catch (PDOException $e) { error_log("AdminDashboard activity: " . $e->getMessage()); }

function fmtDateTime($d) { return $d ? date('M d, Y \a\t g:i A', strtotime($d)) : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - UB Lost and Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <style>.fa-solid,.fa-regular,.fa-brands{display:inline-block!important;font-style:normal!important;font-variant:normal!important;text-rendering:auto!important;-webkit-font-smoothing:antialiased;}</style>
  <link rel="stylesheet" href="AdminDashboard.css?v=<?= time() ?>">
  <link rel="stylesheet" href="../assets/photo-picker.css?v=<?= time() ?>">
  <link rel="stylesheet" href="NotificationsDropdown.css?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/js/all.min.js"></script>
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
    <nav>
      <ul class="nav-menu">
        <li><a class="nav-item active" href="AdminDashboard.php"><div class="nav-item-icon"><i class="fa-solid fa-house"></i></div><div class="nav-item-label">Dashboard</div></a></li>
        <li><a class="nav-item" href="FoundAdmin.php"><div class="nav-item-icon"><i class="fa-solid fa-folder"></i></div><div class="nav-item-label">Found</div></a></li>
        <li><a class="nav-item" href="AdminReports.php"><div class="nav-item-icon"><i class="fa-regular fa-file-lines"></i></div><div class="nav-item-label">Reports</div></a></li>
        <li><a class="nav-item" href="ItemMatchedAdmin.php"><div class="nav-item-icon"><i class="fa-regular fa-circle-check"></i></div><div class="nav-item-label">Matching</div></a></li>
        <li><a class="nav-item" href="HistoryAdmin.php"><div class="nav-item-icon"><i class="fa-regular fa-calendar"></i></div><div class="nav-item-label">History</div></a></li>
      </ul>
    </nav>
  </aside>

  <main class="main">

    <!-- Topbar -->
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
            <i class="fa-solid fa-chevron-down" style="font-size:11px;"></i>
          </button>
          <div class="admin-dropdown-menu" role="menu">
            <a href="logout.php" role="menuitem" class="admin-dropdown-item"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
          </div>
        </div>
      </div>
    </div>

    <div class="main-content-wrap">

      <div class="dashboard-header-row">
        <h1 class="page-title">Dashboard</h1>
        <a href="AdminDashboard.php" class="dashboard-encode-btn" onclick="openEncodeModal(event)">
          <i class="fa-solid fa-plus" style="margin-right:6px;"></i>Encode Item
        </a>
      </div>

      <!-- Summary Cards -->
      <div class="summary-cards-four">
        <a href="FoundAdmin.php" class="summary-card-link">
          <div class="summary-card">
            <div class="summary-card-text"><h3 class="summary-title">Recovered Item</h3><p class="summary-sub-title">Internal (The School)</p><p class="summary-value"><?= number_format($stats['internal_recovered']) ?></p></div>
            <div class="summary-icon-wrap unclaimed"><i class="fa-solid fa-box-archive"></i></div>
            <div class="summary-bg-icon unclaimed"><i class="fa-solid fa-box-archive"></i></div>
          </div>
        </a>
        <a href="FoundAdmin.php#guest" class="summary-card-link">
          <div class="summary-card">
            <div class="summary-card-text"><h3 class="summary-title">Recovered IDs</h3><p class="summary-sub-title">External (The Guests)</p><p class="summary-value"><?= number_format($stats['external_ids']) ?></p></div>
            <div class="summary-icon-wrap external"><i class="fa-regular fa-id-card"></i></div>
            <div class="summary-bg-icon external"><i class="fa-regular fa-id-card"></i></div>
          </div>
        </a>
        <a href="AdminReports.php" class="summary-card-link">
          <div class="summary-card">
            <div class="summary-card-text"><h3 class="summary-title">Unresolved<br>Claimants</h3><p class="summary-value"><?= number_format($stats['unresolved']) ?></p></div>
            <div class="summary-icon-wrap unresolved"><i class="fa-solid fa-users"></i></div>
            <div class="summary-bg-icon unresolved"><i class="fa-solid fa-users"></i></div>
          </div>
        </a>
        <a href="ItemMatchedAdmin.php" class="summary-card-link">
          <div class="summary-card">
            <div class="summary-card-text"><h3 class="summary-title">For Verification</h3><p class="summary-value"><?= number_format($stats['verification']) ?></p></div>
            <div class="summary-icon-wrap verification"><i class="fa-solid fa-circle-check"></i></div>
            <div class="summary-bg-icon verification"><i class="fa-solid fa-circle-check"></i></div>
          </div>
        </a>
      </div>

      <!-- 3-column grid -->
      <div class="dashboard-3col-grid">

        <!-- LEFT: Internal + External tables -->
        <div class="dashboard-left-col">
          <div class="dash-table-card">
            <div class="dash-table-header">
              <span class="dash-table-title">Recovered Item (Internal)</span>
              <a href="FoundAdmin.php" class="dash-see-all">see all</a>
            </div>
            <div class="dash-table-scroll">
              <table class="dash-table">
                <thead><tr><th>Barcode ID</th><th>Category</th><th>Found At</th><th>Date Found</th><th>Retention End</th></tr></thead>
                <tbody>
                  <?php if (empty($recoveredInternal)): ?>
                    <tr><td colspan="5" class="td-empty">No items yet.</td></tr>
                  <?php else: foreach ($recoveredInternal as $r): ?>
                    <tr>
                      <td><a href="#" class="tbl-item-link" data-item-id="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['id']) ?></a></td>
                      <td><?= htmlspecialchars($r['item_type']) ?></td>
                      <td><?= htmlspecialchars($r['found_at']) ?></td>
                      <td><?= htmlspecialchars($r['date_encoded']) ?></td>
                      <td><?= htmlspecialchars($r['retention_end']) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="dash-table-card">
            <div class="dash-table-header">
              <span class="dash-table-title">Recovered IDs (External)</span>
              <a href="FoundAdmin.php#guest" class="dash-see-all">see all</a>
            </div>
            <div class="dash-table-scroll">
              <table class="dash-table">
                <thead><tr><th>Barcode ID</th><th>Encoded By</th><th>Storage</th><th>Date Surrendered</th><th>Retention End</th></tr></thead>
                <tbody>
                  <?php if (empty($recoveredExternal)): ?>
                    <tr><td colspan="5" class="td-empty">No items yet.</td></tr>
                  <?php else: foreach ($recoveredExternal as $r): ?>
                    <tr>
                      <td><a href="#" class="tbl-item-link" data-item-id="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['id']) ?></a></td>
                      <td><?= htmlspecialchars($r['found_by']) ?></td>
                      <td><?= htmlspecialchars($r['storage_location']) ?></td>
                      <td><?= htmlspecialchars($r['date_encoded']) ?></td>
                      <td><?= htmlspecialchars($r['retention_end']) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- CENTER: Chart + Unresolved + Verification -->
        <div class="dashboard-center-col">
          <div class="dash-chart-card">
            <div class="chart-tabs">
              <button class="chart-tab active" data-chart="pie">Pie Graph</button>
              <button class="chart-tab" data-chart="bar">Bar Graph</button>
            </div>
            <div class="chart-body"><canvas id="statusChart"></canvas></div>
            <p class="chart-caption" id="chartCaption"><?= htmlspecialchars($pieCaption) ?></p>
          </div>

          <div class="dash-table-card">
            <div class="dash-table-header">
              <span class="dash-table-title">Unresolved Claimants</span>
              <a href="AdminReports.php" class="dash-see-all">see all</a>
            </div>
            <div class="dash-table-scroll">
              <table class="dash-table">
                <thead><tr><th>Ticket ID</th><th>Category</th><th>Department</th><th>ID</th><th>Contact Number</th></tr></thead>
                <tbody>
                  <?php if (empty($unresolvedRows)): ?>
                    <tr><td colspan="5" class="td-empty">No unresolved claimants.</td></tr>
                  <?php else: foreach ($unresolvedRows as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['ticket_id']) ?></td>
                      <td><?= htmlspecialchars($r['category']) ?></td>
                      <td><?= htmlspecialchars($r['department']) ?></td>
                      <td><?= htmlspecialchars($r['id_num']) ?></td>
                      <td><?= htmlspecialchars($r['contact_number']) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="dash-table-card">
            <div class="dash-table-header">
              <span class="dash-table-title">For Verification</span>
              <a href="ItemMatchedAdmin.php" class="dash-see-all">see all</a>
            </div>
            <div class="dash-table-scroll">
              <table class="dash-table">
                <thead><tr><th>Barcode ID</th><th>Category</th><th>Found At</th><th>Retention End</th><th>Storage</th><th>Date Found</th></tr></thead>
                <tbody>
                  <?php if (empty($verificationRows)): ?>
                    <tr><td colspan="6" class="td-empty">No items for verification.</td></tr>
                  <?php else: foreach ($verificationRows as $r): ?>
                    <tr>
                      <td><a href="#" class="tbl-item-link" data-item-id="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['id']) ?></a></td>
                      <td><?= htmlspecialchars($r['item_type']) ?></td>
                      <td><?= htmlspecialchars($r['found_at']) ?></td>
                      <td><?= htmlspecialchars($r['retention_end']) ?></td>
                      <td><?= htmlspecialchars($r['storage']) ?></td>
                      <td><?= htmlspecialchars($r['date_found']) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- RIGHT: Recent Activity -->
        <div class="dashboard-right-col">
          <div class="activity-card">
            <h3 class="activity-title">Recent Activity</h3>
            <div class="activity-list">
              <?php if (empty($recentActivity)): ?>
                <p class="activity-empty">No recent activity.</p>
              <?php else: ?>
                <?php foreach ($recentActivity as $act):
                    $action   = strtolower($act['action'] ?? 'found');
                    $itemId   = $act['item_id']   ?? '';
                    $itemName = $act['item_name'] ?? '';
                    $dt       = fmtDateTime($act['created_at'] ?? '');
                    $safeId   = htmlspecialchars($itemId);
                    $safeName = htmlspecialchars($itemName);
                    $linkOpen = '<a href="#" class="activity-item-link" data-item-id="' . $safeId . '">';
                    $linkClose= '</a>';
                    $nameStr  = ($safeName && $safeName !== $safeId) ? ' (' . $safeName . ')' : '';

                    if ($action === 'matched' || $action === 'match') {
                        $label = 'Potential Match!';
                        // Format: UB0019 (Blue Tumbler) matched a user report.
                        $text  = $linkOpen . $safeId . $nameStr . $linkClose . ' matched a user report.';
                    } elseif ($action === 'lost' || $action === 'reported') {
                        $label = 'Lost Item!';
                        // Format: UB0222 (Black Wallet) has been reported to be lost.
                        $text  = $linkOpen . $safeId . $nameStr . $linkClose . ' has been reported to be lost.';
                    } else {
                        $label = 'Found Item!';
                        // Format: You have recently inputted the item (UB0019).
                        $text  = 'You have recently inputted the item (' . $linkOpen . $safeId . $linkClose . ').';
                    }
                ?>
                  <div class="activity-item">
                    <p class="activity-label"><?= $label ?></p>
                    <p class="activity-text"><?= $text ?></p>
                    <?php if ($dt): ?><p class="activity-time"><?= htmlspecialchars($dt) ?></p><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- /.dashboard-3col-grid -->
    </div><!-- /.main-content-wrap -->
  </main>
</div><!-- /.layout -->

<script>
var _pieData = <?= $pieDataJson ?>;
var _barData = <?= $barDataJson ?>;
</script>

<script>
// ── Self-contained Admin Item Details Modal ──────────────────────────────────
// Fetches from AdminDashboard.php?ajax=item&id=XXX (same file, admin session)
// Falls back gracefully if showItemDetailsModal from student JS exists.
(function() {
    // Only define our admin version if the student version isn't loaded
    if (typeof window.showItemDetailsModal === 'function') return;

    window.showItemDetailsModal = function(itemId, opts) {
        opts = opts || {};
        // Show loading overlay
        var overlay = document.createElement('div');
        overlay.id = 'adminItemModal';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:24px;';
        overlay.innerHTML = '<div style="background:#fff;border-radius:12px;padding:32px;text-align:center;min-width:260px;"><i class="fa-solid fa-spinner fa-spin" style="font-size:28px;color:#8b0000;"></i><p style="margin-top:12px;color:#6b7280;font-family:Poppins,sans-serif;">Loading...</p></div>';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function(e){ if(e.target===overlay) closeAdminItemModal(); });

        fetch('AdminDashboard.php?ajax=item&id=' + encodeURIComponent(itemId))
            .then(function(r){ return r.json(); })
            .then(function(json) {
                if (!json.ok) { showAdminItemError(json.message || 'Item not found.'); return; }
                renderAdminItemModal(json.data, opts);
            })
            .catch(function() { showAdminItemError('Could not load item details.'); });
    };

    function showAdminItemError(msg) {
        var overlay = document.getElementById('adminItemModal');
        if (!overlay) return;
        overlay.innerHTML = '<div style="background:#fff;border-radius:12px;padding:32px 40px;text-align:center;max-width:360px;font-family:Poppins,sans-serif;">'
            + '<i class="fa-solid fa-circle-exclamation" style="font-size:32px;color:#ef4444;margin-bottom:12px;display:block;"></i>'
            + '<p style="color:#111827;font-weight:600;margin-bottom:8px;">Could not load item</p>'
            + '<p style="color:#6b7280;font-size:13px;margin-bottom:20px;">' + msg + '</p>'
            + '<button onclick="closeAdminItemModal()" style="padding:8px 28px;background:#8b0000;color:#fff;border:none;border-radius:8px;font-family:Poppins,sans-serif;font-size:14px;font-weight:600;cursor:pointer;">Close</button>'
            + '</div>';
    }

    function renderAdminItemModal(item, opts) {
        var overlay = document.getElementById('adminItemModal');
        if (!overlay) return;

        function row(label, value) {
            if (!value || value === '-' || value === 'null') value = '-';
            return '<div style="display:flex;justify-content:space-between;align-items:baseline;padding:6px 0;border-bottom:1px solid #e5e7eb;gap:16px;">'
                + '<span style="font-size:13px;color:#374151;">' + label + '</span>'
                + '<span style="font-size:13px;font-weight:700;color:#111827;text-align:right;max-width:65%;word-break:break-word;white-space:pre-wrap;">' + escHtml(String(value)) + '</span>'
                + '</div>';
        }
        function escHtml(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── Parse item name from description (stored as "Item: xxx\n...") ──
        function parseItemName(desc) {
            if (!desc) return '';
            var m = desc.match(/^Item:\s*(.+?)(?:\n|$)/m);
            return m ? m[1].trim() : '';
        }

        // ── Strip known structural metadata lines, return clean user description ──
        function cleanDescription(desc) {
            if (!desc) return '-';
            return desc
                .replace(/^Item:\s*.+?(\n|$)/m,           '')
                .replace(/^Item Type:\s*.+?(\n|$)/m,       '')
                .replace(/^Student Number:\s*.+?(\n|$)/m,  '')
                .replace(/^Contact:\s*.+?(\n|$)/m,         '')
                .replace(/^Department:\s*.+?(\n|$)/m,      '')
                .trim() || '-';
        }

        var itemName    = parseItemName(item.item_description) || item.brand || '-';
        var cleanDesc   = cleanDescription(item.item_description);

        var imgHtml = '';
        if (item.image_data) {
            imgHtml = '<img src="' + escHtml(item.image_data) + '" alt="Item" style="max-width:100%;max-height:180px;border-radius:6px;object-fit:contain;">';
        } else {
            imgHtml = '<div style="width:100%;height:120px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;">'
                + '<i class="fa-solid fa-box-open" style="font-size:40px;color:#d1d5db;"></i></div>';
        }

        overlay.innerHTML = '<div style="background:#fff;border-radius:12px;max-width:600px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 48px rgba(0,0,0,0.18);overflow:hidden;font-family:Poppins,sans-serif;">'
            // Header
            + '<div style="background:#8b0000;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">'
            +   '<h2 style="margin:0;color:#fff;font-size:17px;font-weight:700;">Item Details</h2>'
            +   '<button onclick="closeAdminItemModal()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-xmark"></i></button>'
            + '</div>'
            // Body: two columns
            + '<div style="display:grid;grid-template-columns:200px 1fr;flex:1;min-height:0;overflow:hidden;">'
            //   Left image pane
            +   '<div style="background:#fafafa;border-right:1px solid #e5e7eb;padding:20px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:12px;overflow-y:auto;">'
            +     imgHtml
            +     '<div style="margin-top:12px;text-align:center;">'
            +       '<span style="font-size:15px;font-weight:700;color:#374151;display:block;">' + escHtml(item.id||'') + '</span>'
            +       '<span style="font-size:11px;color:#6b7280;">Barcode ID</span>'
            +     '</div>'
            +   '</div>'
            //   Right info pane
            +   '<div style="padding:16px 20px;overflow-y:auto;">'
            +     '<p style="font-size:14px;font-weight:700;color:#111827;margin:0 0 12px;text-align:center;">General Information</p>'
            +     row('Category',         item.item_type)
            +     row('Item',             itemName)
            +     row('Color',            item.color)
            +     row('Brand',            item.brand)
            +     row('Item Description', cleanDesc)
            +     row('Storage Location', item.storage_location)
            +     row('Found At',         item.found_at)
            +     row('Found By',         item.found_by)
            +     row('Encoded By',       item.user_id)
            +     row('Date Found',       item.date_encoded)
            +   '</div>'
            + '</div>'
            + '</div>';
    }

    window.closeAdminItemModal = function() {
        var m = document.getElementById('adminItemModal');
        if (m) m.remove();
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAdminItemModal();
    });
})();
</script>

<script>
/* Dropdown */
(function(){
    var dd=document.getElementById('adminDropdown'),tr=dd&&dd.querySelector('.admin-dropdown-trigger');
    if(!dd||!tr)return;
    tr.addEventListener('click',function(e){e.stopPropagation();dd.classList.toggle('open');tr.setAttribute('aria-expanded',dd.classList.contains('open'));});
    document.addEventListener('click',function(){dd.classList.remove('open');if(tr)tr.setAttribute('aria-expanded','false');});
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

/* Item Details modal — triggered by [data-item-id] clicks anywhere on page */
document.addEventListener('click', function(e) {
    var link = e.target.closest('[data-item-id]');
    if (!link) return;
    e.preventDefault();
    var id = link.getAttribute('data-item-id');
    if (!id) return;
    if (typeof showItemDetailsModal === 'function') {
        showItemDetailsModal(id, { showClaimButton: false });
    } else {
        window.location.href = 'FoundAdmin.php?q=' + encodeURIComponent(id);
    }
});

/* Charts */
(function(){
    var canvas = document.getElementById('statusChart');
    if (!canvas) return;
    var captionEl = document.getElementById('chartCaption');
    var pieCaption = <?= json_encode($pieCaption) ?>;
    var barCaption = <?= json_encode($barCaption) ?>;
    var chart;

    /* ── PIE ── */
    function makePie() {
        if (chart) chart.destroy();
        if (!_pieData || !_pieData.length) {
            canvas.parentElement.innerHTML = '<p style="text-align:center;color:#9ca3af;font-style:italic;padding:20px;">No data.</p>';
            return;
        }
        var labels = _pieData.map(function(d){ return d.label; });
        var data   = _pieData.map(function(d){ return d.pct; });
        var colors = _pieData.map(function(d){ return d.color; });

        chart = new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderColor: '#fff', borderWidth: 3 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 12, family: 'Poppins' },
                            padding: 16,
                            boxWidth: 14,
                            usePointStyle: false
                        }
                    },
                    tooltip: {
                        callbacks: { label: function(c){ return ' ' + c.label + ': ' + c.raw + '%'; } }
                    }
                }
            },
            plugins: [{
                /* Draw percentage labels on each slice */
                afterDatasetsDraw: function(ch) {
                    var ctx2 = ch.ctx, ds = ch.data.datasets[0], meta = ch.getDatasetMeta(0);
                    ctx2.save();
                    meta.data.forEach(function(arc, i) {
                        var val = ds.data[i];
                        if (!val) return;
                        var mid = (arc.startAngle + arc.endAngle) / 2;
                        var r   = (arc.outerRadius - (arc.innerRadius || 0)) * 0.6 + (arc.innerRadius || 0);
                        var x   = arc.x + Math.cos(mid) * r;
                        var y   = arc.y + Math.sin(mid) * r;
                        ctx2.font = 'bold 13px Poppins,sans-serif';
                        ctx2.fillStyle = '#fff';
                        ctx2.shadowColor = 'rgba(0,0,0,0.4)';
                        ctx2.shadowBlur = 3;
                        ctx2.textAlign = 'center';
                        ctx2.textBaseline = 'middle';
                        ctx2.fillText(val + '%', x, y);
                    });
                    ctx2.restore();
                }
            }]
        });
        if (captionEl) captionEl.textContent = pieCaption;
    }

    /* ── BAR ── */
    function makeBar() {
        if (chart) chart.destroy();
        if (!_barData || !_barData.length) {
            canvas.parentElement.innerHTML = '<p style="text-align:center;color:#9ca3af;font-style:italic;padding:20px;">No data.</p>';
            return;
        }
        var labels = _barData.map(function(d){ return d.label; });
        var data   = _barData.map(function(d){ return parseFloat(d.pct); });
        var colors = _barData.map(function(d){ return d.color; });
        var maxVal = Math.max.apply(null, data);

        chart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 0,
                    borderRadius: 3,
                    barPercentage: 0.5,       /* thickness of each bar within its slot */
                    categoryPercentage: 0.8   /* slot size, leaves clear gaps between bars */
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 50 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: function(c){ return ' ' + c.raw.toFixed(2) + '%'; } }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: Math.min(100, Math.ceil(maxVal * 1.25)),
                        ticks: { font: { size: 10, family: 'Poppins' }, color: '#9ca3af' },
                        grid: { color: '#f0f0f0' },
                        border: { display: false }
                    },
                    y: {
                        ticks: {
                            font: { size: 11, family: 'Poppins' },
                            color: '#374151',
                            padding: 6
                        },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            },
            plugins: [{
                afterDatasetsDraw: function(ch) {
                    var ctx2 = ch.ctx;
                    ctx2.save();
                    ctx2.font = '600 11px Poppins,sans-serif';
                    ctx2.fillStyle = '#374151';
                    ctx2.textAlign = 'left';
                    ctx2.textBaseline = 'middle';
                    ch.data.datasets.forEach(function(ds, di) {
                        ch.getDatasetMeta(di).data.forEach(function(bar, idx) {
                            var val = ds.data[idx];
                            if (val == null) return;
                            ctx2.fillText(val.toFixed(2), bar.x + 5, bar.y);
                        });
                    });
                    ctx2.restore();
                }
            }]
        });
        if (captionEl) captionEl.textContent = barCaption;
    }

    makePie();

    document.querySelectorAll('.chart-tab').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.chart-tab').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            btn.getAttribute('data-chart') === 'bar' ? makeBar() : makePie();
        });
    });
})();
</script>
<!-- ══════════════════════════════════════════════════════════════════════════
     ENCODE ITEM MODAL  (multi-step: Form → Scanner → Upload → Form → Success)
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="encodeItemOverlay" role="dialog" aria-modal="true"
     style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.55);
            align-items:center;justify-content:center;padding:16px;font-family:Poppins,sans-serif;">

  <!-- ── STEP 1: Fill-up Form ──────────────────────────────────────────────── -->
  <div id="ei-step-form" class="ei-card" style="max-width:480px;">

    <div class="ei-header">
      <h2 class="ei-header-title">Item Lost Report</h2>
      <button type="button" class="ei-close-btn" onclick="closeEncodeModal()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="ei-body">
      <div id="encodeError" class="ei-error-banner" style="display:none;"></div>

      <div class="ei-field">
        <label class="ei-label">Barcode ID</label>
        <input id="ei_barcode" type="text" class="ei-input" placeholder="e.g. UB0001" maxlength="50">
      </div>

      <div class="ei-field">
        <label class="ei-label">Category</label>
        <select id="ei_category" class="ei-input">
          <option value="">— Select Category —</option>
          <option>Electronics &amp; Gadgets</option>
          <option>Document &amp; Identification</option>
          <option>Personal Belongings</option>
          <option>Apparel &amp; Accessories</option>
          <option>Miscellaneous</option>
        </select>
      </div>

      <div class="ei-field" id="eiDocTypeField" style="display:none">
        <label class="ei-label">Document Type</label>
        <select id="ei_doc_type" class="ei-input">
          <option value="">Select document type</option>
          <option>Student ID</option>
          <option>Driver's License</option>
          <option>Passport</option>
          <option>Person's With Disability (PWD) ID</option>
          <option>Voter's ID</option>
          <option>Company/Employee ID</option>
          <option>National ID</option>
          <option>Senior Citizen ID</option>
        </select>
      </div>

      <div class="ei-field">
        <label class="ei-label">Item <span class="ei-req">*</span></label>
        <input id="ei_item" type="text" class="ei-input" placeholder="e.g. Umbrella, Water Bottle">
      </div>

      <div class="ei-field">
        <label class="ei-label">Color <span class="ei-req">*</span></label>
        <select id="ei_color" class="ei-input">
          <option value="">Select</option>
          <option>Red</option>
          <option>Orange</option>
          <option>Yellow</option>
          <option>Green</option>
          <option>Blue</option>
          <option>Violet</option>
          <option>Black</option>
          <option>White</option>
          <option>Brown</option>
          <option>Rainbow</option>
          <option>Multi</option>
          <option>Other</option>
        </select>
      </div>

      <div class="ei-field">
        <label class="ei-label">Brand</label>
        <input id="ei_brand" type="text" class="ei-input" placeholder="e.g. Samsung, Jansport">
      </div>

      <div class="ei-field">
        <label class="ei-label">Item Description <span class="ei-req">*</span></label>
        <textarea id="ei_desc" class="ei-input" rows="3"
                  placeholder="Describe the item — distinguishing marks, contents, condition, etc."
                  style="resize:vertical;"></textarea>
      </div>

      <div class="ei-field">
        <label class="ei-label">Storage Location</label>
        <input id="ei_storage" type="text" class="ei-input" placeholder="e.g. Cabinet A, Shelf 2">
      </div>

      <div class="ei-field">
        <label class="ei-label">Found At</label>
        <select id="ei_found_at" class="ei-input">
          <option value="">— Select Location —</option>
          <option>Library</option>
          <option>Cafeteria / Canteen</option>
          <option>Gymnasium</option>
          <option>Main Building Lobby</option>
          <option>Classroom</option>
          <option>Comfort Room</option>
          <option>Parking Area</option>
          <option>Chapel</option>
          <option>Quadrangle</option>
          <option>Hallway / Corridor</option>
          <option>School Grounds</option>
          <option>Other</option>
        </select>
      </div>

      <div class="ei-field">
        <label class="ei-label">Found By</label>
        <input id="ei_found_by" type="text" class="ei-input" placeholder="Name or email of finder">
      </div>

      <div class="ei-field">
        <label class="ei-label">Date Found</label>
        <input id="ei_date" type="date" class="ei-input">
      </div>

      <!-- ── Inline Item Photo ──────────────────────────────────────────── -->
      <div class="pp-photo-row">
        <label class="pp-photo-label">Item Photo</label>
        <div class="pp-wrap" id="eiPhotoPicker">
          <div class="pp-idle">
            <i class="fa-regular fa-image pp-icon"></i>
            <p class="pp-hint">No photo yet</p>
            <div class="pp-btn-row">
              <button type="button" class="pp-btn pp-btn--cam" data-pp="camera"><i class="fa-solid fa-camera"></i> Camera</button>
              <button type="button" class="pp-btn pp-btn--upload" data-pp="upload"><i class="fa-solid fa-upload"></i> Upload</button>
            </div>
          </div>
          <div class="pp-preview" style="display:none">
            <img class="pp-preview-img" src="" alt="Photo preview">
            <div class="pp-preview-actions">
              <button type="button" class="pp-btn pp-btn--sm" data-pp="camera"><i class="fa-solid fa-camera"></i> Retake</button>
              <button type="button" class="pp-btn pp-btn--sm" data-pp="upload"><i class="fa-solid fa-upload"></i> Change</button>
              <button type="button" class="pp-btn pp-btn--del" data-pp="remove"><i class="fa-solid fa-xmark"></i></button>
            </div>
          </div>
          <input type="file" class="pp-file" accept="image/*" style="display:none">
        </div>
      </div>

    </div><!-- /ei-body -->

    <div class="ei-footer">
      <button type="button" onclick="closeEncodeModal()" class="ei-btn-cancel">Cancel</button>
      <button type="button" id="ei_confirm_btn" onclick="confirmEncode()" class="ei-btn-primary"
              style="background:#8b0000;">Confirm</button>
    </div>
  </div>

</div><!-- /encodeItemOverlay -->

<!-- ── SUCCESS DIALOG ──────────────────────────────────────────────────────── -->
<div id="encodeSuccessOverlay" style="display:none;position:fixed;inset:0;z-index:9100;
     background:rgba(0,0,0,0.55);align-items:center;justify-content:center;font-family:Poppins,sans-serif;">

  <!-- ── Panel A: Encode confirmed ── -->
  <div id="encodeSuccessPanel" style="background:#fff;border-radius:16px;padding:40px 44px;max-width:420px;width:100%;
              text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.22);position:relative;">
    <button onclick="cancelEncodeSuccess()" style="position:absolute;top:14px;right:14px;
            background:none;border:none;color:#9ca3af;font-size:20px;cursor:pointer;">
      <i class="fa-regular fa-circle-xmark"></i>
    </button>
    <div style="width:72px;height:72px;background:#22c55e;border-radius:50%;
                display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <i class="fa-solid fa-check" style="color:#fff;font-size:32px;"></i>
    </div>
    <h3 id="encodeSuccessHeading" style="margin:0 0 10px;font-size:22px;font-weight:700;color:#111827;">Success</h3>
    <p id="encodeSuccessMsg" style="margin:0 0 6px;font-size:14px;color:#374151;">Item has been encoded successfully!</p>
    <p style="margin:0 0 20px;font-size:14px;color:#111827;font-weight:700;" id="encodeSuccessBid"></p>

    <!-- Ticket-match notice (shown when potential matches exist) -->
    <div id="encodeTicketNotice" style="display:none;background:#fffbeb;border:1px solid #fde68a;
         border-radius:10px;padding:14px 16px;margin-bottom:20px;text-align:left;">
      <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#92400e;">
        <i class="fa-solid fa-triangle-exclamation" style="margin-right:5px;"></i>
        Matching lost report(s) found!
      </p>
      <p style="margin:0 0 10px;font-size:12px;color:#78350f;line-height:1.5;">
        The following tickets were reported for the same category. Link this item to a ticket to move it to <strong>For Verification</strong>.
      </p>
      <div id="encodeTicketList" style="display:flex;flex-direction:column;gap:6px;max-height:160px;overflow-y:auto;"></div>
      <div style="margin-top:10px;display:flex;align-items:center;gap:8px;">
        <input id="encodeManualTicket" type="text" placeholder="Or type a ticket ID (REF-…)"
               style="flex:1;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;
                      font-family:Poppins,sans-serif;font-size:12px;color:#111827;outline:none;">
        <button onclick="linkManualTicket()"
                style="padding:7px 14px;background:#8b0000;color:#fff;border:none;border-radius:6px;
                       font-family:Poppins,sans-serif;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
          Link
        </button>
      </div>
      <p id="encodeLinkMsg" style="display:none;margin:8px 0 0;font-size:12px;font-weight:600;"></p>
    </div>

    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="cancelEncodeSuccess()"
              style="padding:10px 28px;border:1px solid #d1d5db;border-radius:8px;background:#fff;
                     color:#374151;font-family:Poppins,sans-serif;font-size:14px;font-weight:600;cursor:pointer;">
        Close
      </button>
      <button onclick="confirmEncodeSuccess()"
              style="padding:10px 28px;border:none;border-radius:8px;background:#8b0000;
                     color:#fff;font-family:Poppins,sans-serif;font-size:14px;font-weight:600;cursor:pointer;">
        Done
      </button>
    </div>
  </div>
</div>

<style>
/* ── Shared card & field styles ─────────────────────────────────────────────── */
.ei-card {
  background:#fff; border-radius:14px; width:100%; max-height:95vh;
  display:flex; flex-direction:column;
  box-shadow:0 24px 64px rgba(0,0,0,0.22); overflow:hidden;
}
.ei-header {
  background:#8b0000; padding:16px 22px;
  display:flex; align-items:center; justify-content:space-between; flex-shrink:0;
}
.ei-header-title { margin:0; color:#fff; font-size:18px; font-weight:700; }
.ei-close-btn {
  background:rgba(255,255,255,0.18); border:2px solid rgba(255,255,255,0.5);
  color:#fff; width:32px; height:32px; border-radius:50%; font-size:15px;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
}
.ei-body { overflow-y:auto; flex:1; padding:24px 28px 20px; display:flex; flex-direction:column; gap:14px; }
.ei-footer {
  padding:14px 22px; border-top:1px solid #e5e7eb;
  display:flex; justify-content:flex-end; gap:12px; flex-shrink:0; background:#fff;
}
.ei-row2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.ei-field { display:flex; flex-direction:column; gap:5px; }
.ei-label { font-size:13px; font-weight:600; color:#374151; }
.ei-req   { color:#dc2626; }
.ei-input {
  width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px;
  font-family:Poppins,sans-serif; font-size:13px; color:#111827;
  background:#fff; outline:none; transition:border-color .15s, box-shadow .15s; box-sizing:border-box;
}
.ei-input:focus { border-color:#8b0000; box-shadow:0 0 0 3px rgba(139,0,0,0.12); }
.ei-input.ei-error { border-color:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,0.12); }
textarea.ei-input { resize:vertical; }
.ei-btn-cancel {
  padding:10px 24px; border:1px solid #d1d5db; border-radius:8px; background:#fff;
  color:#374151; font-family:Poppins,sans-serif; font-size:14px; font-weight:600; cursor:pointer;
}
.ei-btn-primary {
  padding:10px 32px; border:none; border-radius:8px; background:#8b0000;
  color:#fff; font-family:Poppins,sans-serif; font-size:14px; font-weight:600; cursor:pointer;
}
.ei-btn-scanner {
  display:inline-flex; align-items:center; gap:10px;
  padding:12px 24px; border:none; border-radius:10px; background:#8b0000;
  color:#fff; font-family:Poppins,sans-serif; font-size:14px; font-weight:600; cursor:pointer;
  min-width:160px; justify-content:center;
}
.ei-btn-scanner:hover { background:#6e0000; }
.ei-img-pill {
  display:flex; align-items:center; gap:10px;
  flex:1; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;
}
.ei-error-banner {
  background:#fef2f2; border:1px solid #fca5a5; color:#991b1b;
  border-radius:8px; padding:10px 14px; font-size:13px;
}

/* ── Scanner frame with corner brackets ─────────────────────────────────────── */
.ei-scan-frame {
  position:relative; width:280px; height:210px; margin:0 auto;
}
.ei-scan-frame::before, .ei-scan-frame::after,
.ei-scan-frame > span::before, .ei-scan-frame > span::after {
  content:''; position:absolute; width:32px; height:32px; border-color:#222; border-style:solid;
}
.ei-scan-frame::before  { top:0;    left:0;  border-width:3px 0 0 3px; }
.ei-scan-frame::after   { top:0;    right:0; border-width:3px 3px 0 0; }
.ei-scan-frame > span::before { bottom:0; left:0;  border-width:0 0 3px 3px; }
.ei-scan-frame > span::after  { bottom:0; right:0; border-width:0 3px 3px 0; }


/* ── Sidebar mobile: cancel min-height so no blank gap below nav ── */
@media (max-width: 900px) {
  .sidebar  { min-height: 0 !important; height: auto !important; }
  .nav-menu { flex: none !important; }
}

/* ── Quick-button icon colours ── */
/* Recovered Item       → amber        */
.summary-icon-wrap.unclaimed  i,
.summary-bg-icon.unclaimed    i { color: #F59E0B !important; }
/* Recovered IDs        → iris         */
.summary-icon-wrap.external   i,
.summary-bg-icon.external     i { color: #5C5FA8 !important; }
/* Unresolved Claimants → cadmium red  */
.summary-icon-wrap.unresolved i,
.summary-bg-icon.unresolved   i { color: #E30022 !important; }
/* For Verification     → emerald green */
.summary-icon-wrap.verification i,
.summary-bg-icon.verification   i { color: #50C878 !important; }
</style>

<script src="../assets/photo-picker.js?v=<?= time() ?>"></script>
<script>
/* ════════════════════════════════════════════════════════════════════════════
   ENCODE ITEM — modal controller (single-step form with inline photo)
   ════════════════════════════════════════════════════════════════════════════ */
(function(){
var overlay        = document.getElementById('encodeItemOverlay');
var stepForm       = document.getElementById('ei-step-form');
var successOverlay = document.getElementById('encodeSuccessOverlay');

var todayStr = new Date().toISOString().split('T')[0];
document.getElementById('ei_date').setAttribute('max', todayStr);

window._eiImageData = '';
var _eiPP = PhotoPicker.init({
    el: 'eiPhotoPicker',
    onChange: function(dataUrl) { window._eiImageData = dataUrl || ''; }
});

/* ── Error banner ──────────────────────────────────────────────────────── */
function showEncodeError(msg) {
    var el = document.getElementById('encodeError');
    el.textContent = msg; el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function hideEncodeError() {
    var el = document.getElementById('encodeError');
    el.style.display = 'none'; el.textContent = '';
}
function clearFormFields() {
    ['ei_barcode','ei_category','ei_doc_type','ei_item','ei_color','ei_brand',
     'ei_storage','ei_found_at','ei_found_by','ei_date','ei_desc']
        .forEach(function(id){
            var el = document.getElementById(id);
            if (el) { el.value = ''; el.disabled = false; }
        });
    var eiDocTypeField = document.getElementById('eiDocTypeField');
    if (eiDocTypeField) eiDocTypeField.style.display = 'none';
    document.querySelectorAll('.ei-input').forEach(function(el){
        el.classList.remove('ei-error');
    });
    hideEncodeError();
}

// Document & Identification sub-dropdown for Encode Item
(function () {
    var catSel  = document.getElementById('ei_category');
    var docField = document.getElementById('eiDocTypeField');
    var docSel  = document.getElementById('ei_doc_type');
    var itemEl  = document.getElementById('ei_item');
    function syncEiDocType() {
        if (!docField) return;
        var isDoc = catSel && catSel.value === 'Document & Identification';
        docField.style.display = isDoc ? 'flex' : 'none';
        if (!isDoc && docSel) docSel.value = '';
    }
    if (catSel) catSel.addEventListener('change', syncEiDocType);
    if (docSel) docSel.addEventListener('change', function () {
        if (itemEl) itemEl.value = this.value;
    });
})();

/* ── Open / Close encode modal ─────────────────────────────────────────── */
window.openEncodeModal = function(e) {
    if (e) e.preventDefault();
    clearFormFields();
    _eiPP.clear();
    stepForm.style.display = 'flex';
    overlay.style.display  = 'flex';
    document.body.style.overflow = 'hidden';
};
window.closeEncodeModal = function() {
    overlay.style.display    = 'none';
    document.body.style.overflow = '';
};

overlay.addEventListener('click', function(e){
    if (e.target === overlay) closeEncodeModal();
});
successOverlay.addEventListener('click', function(e){
    if (e.target === successOverlay) cancelEncodeSuccess();
});
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        if (overlay.style.display    === 'flex') { closeEncodeModal(); return; }
        if (successOverlay.style.display === 'flex') cancelEncodeSuccess();
    }
});

/* ── Barcode field: DB look-up on blur to auto-fill form ──────────────── */
var barcodeInput = document.getElementById('ei_barcode');
if (barcodeInput) {
    barcodeInput.addEventListener('blur', function() {
        var code = this.value.trim();
        if (!code) return;
        fetch('AdminDashboard.php?ajax=item&id=' + encodeURIComponent(code))
            .then(function(r){ return r.json(); })
            .then(function(json){ if (json.ok && json.data) autoFillForm(json.data); })
            .catch(function(){});
    });
}

/* ── Confirm: validate + submit ───────────────────────────────────────── */
window.confirmEncode = function() {
    hideEncodeError();
    document.querySelectorAll('.ei-input').forEach(function(el){ el.classList.remove('ei-error'); });

    var barcode = document.getElementById('ei_barcode').value.trim();
    var item    = document.getElementById('ei_item').value.trim();
    var color   = document.getElementById('ei_color').value.trim();
    var desc    = document.getElementById('ei_desc').value.trim();

    var errors = [];
    if (!barcode) { errors.push('Barcode ID'); document.getElementById('ei_barcode').classList.add('ei-error'); }
    if (!item)    { errors.push('Item');       document.getElementById('ei_item').classList.add('ei-error'); }
    if (!color)   { errors.push('Color');      document.getElementById('ei_color').classList.add('ei-error'); }
    if (!desc)    { errors.push('Item Description'); document.getElementById('ei_desc').classList.add('ei-error'); }
    if (errors.length) { showEncodeError('Required field(s) missing: ' + errors.join(', ') + '.'); return; }

    var dateFnd = document.getElementById('ei_date').value.trim();
    if (dateFnd && dateFnd > todayStr) {
        showEncodeError('Date Found cannot be in the future.');
        document.getElementById('ei_date').classList.add('ei-error'); return;
    }

    var btn = document.getElementById('ei_confirm_btn');
    btn.disabled = true; btn.textContent = 'Saving…';

    var fd = new FormData();
    fd.append('ajax',             'encode');
    fd.append('barcode_id',       barcode);
    fd.append('category',         document.getElementById('ei_category').value.trim());
    fd.append('item_name',        item);
    fd.append('color',            color);
    fd.append('brand',            document.getElementById('ei_brand').value.trim());
    fd.append('item_description', desc);
    fd.append('storage_location', document.getElementById('ei_storage').value.trim());
    fd.append('found_at',         document.getElementById('ei_found_at').value.trim());
    fd.append('found_by',         document.getElementById('ei_found_by').value.trim());
    fd.append('date_found',       dateFnd);
    fd.append('image_data',       window._eiImageData || '');

    fetch('AdminDashboard.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(json){
            btn.disabled = false; btn.textContent = 'Confirm';
            if (json.ok) {
                overlay.style.display = 'none';
                var heading = document.getElementById('encodeSuccessHeading');
                var msg     = document.getElementById('encodeSuccessMsg');
                if (heading) heading.textContent = json.re_encoded ? 'Re-encoded!' : 'Success';
                if (msg)     msg.textContent     = json.re_encoded
                    ? 'This item has been re-encoded and reset to Unclaimed.'
                    : 'Item has been encoded successfully!';
                document.getElementById('encodeSuccessBid').textContent = 'Barcode ID: ' + barcode;

                window._encodedBarcodeId = barcode;
                var notice = document.getElementById('encodeTicketNotice');
                if (notice) {
                    if (json.potential_tickets && json.potential_tickets.length > 0) {
                        renderPotentialTickets(json.potential_tickets);
                        notice.style.display = 'block';
                    } else {
                        notice.style.display = 'none';
                    }
                }
                successOverlay.style.display = 'flex';
            } else {
                showEncodeError(json.message || 'An error occurred. Please try again.');
            }
        })
        .catch(function(){
            btn.disabled = false; btn.textContent = 'Confirm';
            showEncodeError('Network error. Check your connection and try again.');
        });
};

/* ── Success overlay actions ──────────────────────────────────────────── */
window.cancelEncodeSuccess = function() {
    successOverlay.style.display = 'none';
    document.body.style.overflow = '';
};
window.confirmEncodeSuccess = function() {
    successOverlay.style.display = 'none';
    document.body.style.overflow = '';
    window.location.reload();
};

/* ── Ticket linking ───────────────────────────────────────────────────── */
function renderPotentialTickets(tickets) {
    var list = document.getElementById('encodeTicketList');
    if (!list) return;
    list.innerHTML = '';
    tickets.forEach(function(t) {
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;'
            + 'background:#fff;border:1px solid #e5e7eb;border-radius:7px;padding:7px 10px;gap:10px;';
        row.innerHTML =
            '<div style="min-width:0;flex:1;">'
            + '<span style="font-size:12px;font-weight:700;color:#111827;display:block;">' + escStr(t.id) + '</span>'
            + '<span style="font-size:11px;color:#6b7280;">' + escStr(t.item_name) + ' &mdash; ' + escStr(t.category)
            + (t.date_lost ? ' &mdash; Lost: ' + escStr(t.date_lost) : '') + '</span>'
            + '</div>'
            + '<button onclick="linkToTicket(\'' + escStr(t.id) + '\')" '
            + 'style="flex-shrink:0;padding:5px 12px;background:#8b0000;color:#fff;border:none;'
            + 'border-radius:5px;font-family:Poppins,sans-serif;font-size:11px;font-weight:600;cursor:pointer;">'
            + 'Link</button>';
        list.appendChild(row);
    });
}
function escStr(s) {
    return String(s || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
window.linkToTicket = function(ticketId) { doLinkTicket(ticketId); };
window.linkManualTicket = function() {
    var val = (document.getElementById('encodeManualTicket').value || '').trim();
    if (!val) { showLinkMsg('Please enter a ticket ID.', false); return; }
    if (!val.startsWith('REF-')) { showLinkMsg('Ticket ID must start with REF-.', false); return; }
    doLinkTicket(val);
};
function doLinkTicket(ticketId) {
    showLinkMsg('Linking…', null);
    var fd = new FormData();
    fd.append('ajax',           'link_ticket');
    fd.append('found_item_id',  window._encodedBarcodeId || '');
    fd.append('lost_report_id', ticketId);
    fetch('AdminDashboard.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(json){
            if (json.ok) {
                showLinkMsg('✓ ' + json.message, true);
                var list = document.getElementById('encodeTicketList');
                if (list) list.querySelectorAll('button').forEach(function(b){
                    b.disabled = true; b.style.opacity = '0.4';
                });
            } else {
                showLinkMsg('Error: ' + json.message, false);
            }
        })
        .catch(function(){ showLinkMsg('Network error. Try again.', false); });
}
function showLinkMsg(text, success) {
    var el = document.getElementById('encodeLinkMsg');
    if (!el) return;
    el.textContent = text;
    el.style.color = success === true ? '#15803d' : success === false ? '#dc2626' : '#6b7280';
    el.style.display = 'block';
}

function autoFillForm(item) {
    function setVal(id, val) {
        var el = document.getElementById(id); if (el && val) el.value = val;
    }
    function parseItemName(desc) {
        if (!desc) return '';
        var m = desc.match(/^Item:\s*(.+?)(\n|$)/m);
        return m ? m[1].trim() : '';
    }
    function cleanDesc(desc) {
        if (!desc) return '';
        return desc
            .replace(/^Item:\s*.+?(\n|$)/m, '')
            .replace(/^Item Type:\s*.+?(\n|$)/m, '')
            .replace(/^Student Number:\s*.+?(\n|$)/m, '')
            .replace(/^Contact:\s*.+?(\n|$)/m, '')
            .replace(/^Department:\s*.+?(\n|$)/m, '')
            .trim();
    }
    setVal('ei_category', item.item_type);
    setVal('ei_item',     parseItemName(item.item_description) || item.brand);
    setVal('ei_color',    item.color);
    setVal('ei_brand',    item.brand);
    setVal('ei_storage',  item.storage_location);
    setVal('ei_found_at', item.found_at);
    setVal('ei_found_by', item.found_by);
    setVal('ei_date',     item.date_encoded ? item.date_encoded.split(' ')[0] : '');
    setVal('ei_desc',     cleanDesc(item.item_description));
    if (item.image_data && !window._eiImageData) {
        _eiPP.setPhoto(item.image_data);
    }
}

})(); /* end IIFE */

</script>

<script src="NotificationsDropdown.js"></script>
</body>
</html>